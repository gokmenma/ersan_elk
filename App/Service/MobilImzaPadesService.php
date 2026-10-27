<?php

namespace App\Service;

use App\Helper\Security;
use App\Model\MobilImzaModel;
use App\Model\SettingsModel;

final class MobilImzaPadesService
{
    private const SIGN_TIMEOUT = 120;
    private const DISPLAY_MAX_LENGTH = 100;
    private const DEFAULT_DISPLAY_TEXT = '{evrak_no} sayılı evrakı imzalıyorsunuz';
    private const WORK_DIR = 'uploads/evrak-pades/.mobil';
    private const CERT_DIR = 'storage/mobil-imza/certs';

    private MobilImzaModel $model;
    private TurkcellMobilImzaClient $client;

    public function __construct()
    {
        $this->model = new MobilImzaModel();
        $this->client = new TurkcellMobilImzaClient();
    }

    public static function isEnabled(int $firmaId): bool
    {
        return (string) ((new SettingsModel())->getSettings('mobil_imza_aktif', $firmaId) ?? '0') === '1'
            && TurkcellMobilImzaClient::isConfigured();
    }

    public function start(int $evrakId, int $userId): array
    {
        $firmaId = (int) $_SESSION['firma_id'];
        if (!self::isEnabled($firmaId)) {
            throw new \RuntimeException('Mobil imza hizmeti etkin değil. Ayarlar > Mobil İmza sekmesinden etkinleştiriniz.');
        }
        $msisdn = $this->userMsisdn($userId);
        $this->purgeExpiredFiles();

        $pades = new PadesEImzaService();
        ['evrak' => $evrak, 'signer' => $signer, 'pdf' => $sourcePdf] = $pades->signingContext($evrakId, $userId);
        if (!in_array($evrak->onay_turu ?? 'eimza', ['eimza', 'mobil_imza'], true)) {
            throw new \RuntimeException('Bu evrak ıslak imza onay akışındadır; mobil imza uygulanamaz.');
        }

        $profile = $this->client->profileQuery($msisdn);
        $prepared = (new PdfIncrementalSigner())->prepare($sourcePdf, [
            'name' => (string) $signer->adi_soyadi,
            'reason' => 'Resmî evrak mobil imza onayı',
            'location' => 'Türkiye',
        ]);
        $signedAttributes = CmsSignedDataBuilder::signedAttributes($prepared['digest'], $profile['cert_hash'], time());

        $token = bin2hex(random_bytes(24));
        $relativePath = self::WORK_DIR . '/' . $token . '.pdf.enc';
        $absolutePath = $this->workDirectory() . '/' . $token . '.pdf.enc';
        if (file_put_contents($absolutePath, Security::encryptFile($prepared['pdf']), LOCK_EX) === false) {
            throw new \RuntimeException('İmzalanacak PDF geçici olarak saklanamadı.');
        }

        try {
            $request = $this->client->signHash(
                $msisdn,
                hash('sha256', $signedAttributes, true),
                $this->displayText($evrak, $firmaId),
                $profile['profile'],
                self::SIGN_TIMEOUT
            );
            $this->model->createTransaction([
                'firma_id' => $firmaId,
                'evrak_id' => $evrakId,
                'kullanici_id' => $userId,
                'token' => $token,
                'msisdn_maskeli' => TurkcellMobilImzaClient::maskMsisdn($msisdn),
                'mssp_trans_id' => $request['transaction_id'],
                'kaynak_pdf_sha256' => hash('sha256', $sourcePdf),
                'hazir_pdf_yolu' => $relativePath,
                'byte_range' => json_encode(['range' => $prepared['byte_range'], 'source_length' => strlen($sourcePdf)]),
                'imzali_oznitelikler' => base64_encode($signedAttributes),
                'sertifika_ozeti' => bin2hex($profile['cert_hash']),
                'son_gecerlilik' => date('Y-m-d H:i:s', time() + self::SIGN_TIMEOUT + 60),
            ]);
        } catch (\Throwable $e) {
            @unlink($absolutePath);
            throw $e;
        }

        return [
            'token' => $token,
            'msisdn' => TurkcellMobilImzaClient::maskMsisdn($msisdn),
            'timeout' => self::SIGN_TIMEOUT,
        ];
    }

    public function startKep(string $tempId, int $userId): array
    {
        $firmaId = (int) $_SESSION['firma_id'];
        if (!self::isEnabled($firmaId)) {
            throw new \RuntimeException('Mobil imza hizmeti etkin değil. Ayarlar > Mobil İmza sekmesinden etkinleştiriniz.');
        }
        $draft = $this->kepDraft($tempId, $firmaId, $userId);
        $digest = $this->kepDigest((string) ($draft['eHash'] ?? ''));
        [$signerId, $msisdn] = $this->kepSigner($userId, $firmaId);
        $this->purgeExpiredFiles();

        $profile = $this->client->profileQuery($msisdn);
        $knownCertificate = $this->knownCertificate($profile['cert_hash'], $firmaId);
        $signedAttributes = CmsSignedDataBuilder::signedAttributes($digest, $profile['cert_hash'], time(), $knownCertificate);
        $token = bin2hex(random_bytes(24));
        $subject = trim(preg_replace('/\s+/u', ' ', (string) ($draft['subject'] ?? '')));
        $displayText = mb_substr('KEP iletisini imzalıyorsunuz' . ($subject !== '' ? ': ' . $subject : ''), 0, self::DISPLAY_MAX_LENGTH);

        $request = $this->client->signHash($msisdn, hash('sha256', $signedAttributes, true), $displayText, $profile['profile'], self::SIGN_TIMEOUT);
        $this->model->createTransaction([
            'firma_id' => $firmaId,
            'evrak_id' => (int) ($draft['evrak_id'] ?? 0),
            'kullanici_id' => $userId,
            'token' => $token,
            'msisdn_maskeli' => TurkcellMobilImzaClient::maskMsisdn($msisdn),
            'mssp_trans_id' => $request['transaction_id'],
            'kaynak_pdf_sha256' => hash('sha256', $digest),
            'hazir_pdf_yolu' => '',
            'byte_range' => json_encode(['kep' => $tempId, 'imzaci' => $signerId, 'ess' => $knownCertificate !== null]),
            'imzali_oznitelikler' => base64_encode($signedAttributes),
            'sertifika_ozeti' => bin2hex($profile['cert_hash']),
            'son_gecerlilik' => date('Y-m-d H:i:s', time() + self::SIGN_TIMEOUT + 60),
        ]);

        return [
            'token' => $token,
            'msisdn' => TurkcellMobilImzaClient::maskMsisdn($msisdn),
            'timeout' => self::SIGN_TIMEOUT,
        ];
    }

    public function poll(string $token, int $userId, string $ipAddress): array
    {
        $transaction = $this->transaction($token, $userId);
        if ($transaction->durum !== 'bekliyor') {
            return [
                'state' => $transaction->durum === 'imzalandi' ? 'isleniyor' : $transaction->durum,
                'message' => (string) ($transaction->hata_mesaji ?? ''),
            ];
        }
        if (strtotime((string) $transaction->son_gecerlilik) < time()) {
            $this->close($transaction, 'zaman_asimi', 'Mobil imza onayı süresi içinde verilmedi.');
            return ['state' => 'zaman_asimi', 'message' => 'Mobil imza onayı süresi içinde verilmedi.'];
        }

        $status = $this->client->statusQuery((string) $transaction->mssp_trans_id);
        if ($status['state'] === 'bekliyor') {
            return ['state' => 'bekliyor', 'message' => ''];
        }
        if ($status['state'] !== 'imzalandi' || $status['signature'] === null) {
            $message = $status['message'] ?: 'Mobil imza tamamlanamadı.';
            $this->close($transaction, $status['state'] === 'imzalandi' ? 'hata' : $status['state'], $message);
            return ['state' => $status['state'] === 'imzalandi' ? 'hata' : $status['state'], 'message' => $message];
        }
        if (!$this->model->claimTransaction((int) $transaction->id)) {
            return ['state' => 'isleniyor', 'message' => ''];
        }

        try {
            $approval = $this->complete($transaction, $status['signature'], $userId, $ipAddress);
        } catch (\Throwable $e) {
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Mobil imza sunucuda doğrulanamadı.';
            if (!$e instanceof \RuntimeException) {
                error_log('MobilImzaPadesService::complete ' . $e->getMessage());
            }
            $this->close($transaction, 'hata', $message);
            throw new \RuntimeException($message);
        }
        $this->removeFile((string) $transaction->hazir_pdf_yolu);
        $this->model->clearFile((int) $transaction->id);

        if (isset($approval['kep_signature'])) {
            return ['state' => 'imzalandi', 'message' => '', 'kep_signature' => $approval['kep_signature'], 'kep_temp_id' => $approval['kep_temp_id']];
        }

        return ['state' => 'imzalandi', 'message' => '', 'approval' => $approval, 'evrak_id' => (int) $transaction->evrak_id];
    }

    public function cancel(string $token, int $userId): void
    {
        $transaction = $this->transaction($token, $userId);
        if ($transaction->durum === 'bekliyor') {
            $this->close($transaction, 'iptal', 'İşlem kullanıcı tarafından ekranda iptal edildi.');
        }
    }

    public function testProfile(string $msisdn): array
    {
        $profile = $this->client->profileQuery($msisdn);
        return [
            'profile' => $profile['profile'],
            'cert_serial' => $profile['cert_serial'],
            'cert_issuer' => $profile['cert_issuer'],
        ];
    }

    private function complete(object $transaction, string $pkcs7, int $userId, string $ipAddress): array
    {
        $response = $this->parseSignatureResponse($pkcs7);
        $certificate = $response['certificate'];
        foreach ($response['certificates'] as $candidate) {
            if (hash_equals((string) $transaction->sertifika_ozeti, hash('sha256', $candidate))) {
                $certificate = $candidate;
                break;
            }
        }
        if (!hash_equals((string) $transaction->sertifika_ozeti, hash('sha256', $certificate))) {
            throw new \RuntimeException('İmzalayan sertifika, profil sorgusunda bildirilen sertifika ile eşleşmiyor. Lütfen yeniden deneyiniz.');
        }
        $signedAttributes = base64_decode((string) $transaction->imzali_oznitelikler, true);
        if ($signedAttributes === false
            || openssl_verify($signedAttributes, $response['signature'], CmsSignedDataBuilder::pem($certificate), OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Mobil imza, gönderilen evrak özetine ait değil.');
        }
        $layout = json_decode((string) $transaction->byte_range, true);
        $signerId = is_array($layout) && !empty($layout['imzaci']) ? (int) $layout['imzaci'] : $userId;
        $this->validateCertificate($certificate, $response['certificates'], $signerId);
        $this->rememberCertificate($certificate, (int) $transaction->firma_id);

        if (is_array($layout) && !empty($layout['kep'])) {
            if (empty($layout['ess'])) {
                throw new \RuntimeException('Mobil imza sertifikanız ilk kez tanındı. PTT KEP, imzalayan sertifika bilgisini zorunlu tuttuğu için lütfen KEP iletisini bir kez daha imzalayarak gönderiniz.');
            }
            return [
                'kep_temp_id' => (string) $layout['kep'],
                'kep_signature' => base64_encode(CmsSignedDataBuilder::signedData($signedAttributes, $response['signature'], $certificate, $response['certificates'])),
            ];
        }
        $path = dirname(__DIR__, 2) . '/' . (string) $transaction->hazir_pdf_yolu;
        if (!is_array($layout) || !is_file($path)) {
            throw new \RuntimeException('Mobil imza işlemine ait PDF bulunamadı. Lütfen yeniden imzalayınız.');
        }
        $preparedPdf = Security::decryptFile((string) file_get_contents($path));
        $sourcePdf = substr($preparedPdf, 0, (int) ($layout['source_length'] ?? 0));
        if (!hash_equals((string) $transaction->kaynak_pdf_sha256, hash('sha256', $sourcePdf))) {
            throw new \RuntimeException('Mobil imza için hazırlanan PDF bütünlük kontrolünden geçemedi.');
        }

        $cms = CmsSignedDataBuilder::signedData($signedAttributes, $response['signature'], $certificate, $response['certificates']);
        $signedPdf = (new PdfIncrementalSigner())->embed($preparedPdf, (array) ($layout['range'] ?? []), $cms);

        return (new PadesEImzaService())->finalize((int) $transaction->evrak_id, $userId, $sourcePdf, $signedPdf, $ipAddress, 'mobil_imza');
    }

    private function knownCertificate(string $certHash, int $firmaId): ?string
    {
        $stored = (new SettingsModel())->getSettings('mobil_imza_sertifika_' . bin2hex($certHash), $firmaId);
        $der = $stored ? base64_decode($stored, true) : false;
        return ($der !== false && $der !== '' && hash_equals($certHash, hash('sha256', $der))) ? $der : null;
    }

    private function rememberCertificate(string $certificate, int $firmaId): void
    {
        $name = 'mobil_imza_sertifika_' . hash('sha256', $certificate);
        if ((new SettingsModel())->getSettings($name, $firmaId) === null) {
            (new SettingsModel())->upsertMultipleSettings([$name => base64_encode($certificate)], $firmaId);
        }
    }

    private function userMsisdn(int $userId): string
    {
        $msisdn = $this->model->getUserMsisdn($userId, (int) ($_SESSION['owner_id'] ?? 0));
        if ($msisdn === '') {
            throw new \RuntimeException('Mobil imza hattınız tanımlı değil. Ayarlar > Mobil İmza sekmesinden hattınızı tanımlatınız.');
        }
        return $msisdn;
    }

    private function kepSigner(int $userId, int $firmaId): array
    {
        $ownMsisdn = $this->model->getUserMsisdn($userId, (int) ($_SESSION['owner_id'] ?? 0));
        if ($ownMsisdn !== '') {
            return [$userId, $ownMsisdn];
        }
        $defaultId = (int) ((new SettingsModel())->getSettings('mobil_imza_kep_varsayilan_kullanici', $firmaId) ?? 0);
        if ($defaultId > 0) {
            $defaultMsisdn = $this->model->getUserMsisdn($defaultId, (int) ($_SESSION['owner_id'] ?? 0));
            if ($defaultMsisdn !== '') {
                return [$defaultId, $defaultMsisdn];
            }
        }
        throw new \RuntimeException('Mobil imza hattınız tanımlı değil ve KEP için varsayılan mobil imza kullanıcısı seçilmemiş. Evrak Takip > İşlemler > Mobil İmza Ayarları bölümünden tanımlayınız.');
    }

    private function kepDraft(string $tempId, int $firmaId, int $userId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $tempId)) {
            throw new \RuntimeException('KEP taslağı geçersiz.');
        }
        $draft = $_SESSION['kep_draft_' . $tempId] ?? null;
        if (!is_array($draft)) {
            $directory = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp/';
            foreach (['draft_', 'draft_evrak_'] as $prefix) {
                if (is_file($directory . $prefix . $tempId . '.json')) {
                    $draft = json_decode((string) file_get_contents($directory . $prefix . $tempId . '.json'), true);
                    break;
                }
            }
        }
        if (!is_array($draft) || (int) ($draft['firm_id'] ?? 0) !== $firmaId
            || (int) ($draft['user_id'] ?? 0) !== $userId || (int) ($draft['created_at'] ?? 0) < time() - 1800) {
            throw new \RuntimeException('KEP taslağı bulunamadı veya süresi doldu. Lütfen yeniden deneyiniz.');
        }
        return $draft;
    }

    private function kepDigest(string $eHash): string
    {
        $raw = base64_decode($eHash, true);
        if ($raw !== false && strlen($raw) === 32) {
            return $raw;
        }
        if (preg_match('/^[a-fA-F0-9]{64}$/', $eHash)) {
            return (string) hex2bin($eHash);
        }
        if ($eHash === '') {
            throw new \RuntimeException('KEP paketinin özet değeri alınamadı.');
        }
        return hash('sha256', $eHash, true);
    }

    private function parseSignatureResponse(string $raw): array
    {
        if ($raw !== '' && ord($raw[0]) === 0x30) {
            return CmsSignedDataBuilder::parsePkcs7($raw);
        }
        $document = new \DOMDocument();
        if (!@$document->loadXML('<MobilImzaYaniti>' . preg_replace('/^<\?xml[^>]*\?>/', '', trim($raw)) . '</MobilImzaYaniti>', LIBXML_NONET)) {
            throw new \RuntimeException('Mobil imza yanıtı çözülemedi.');
        }
        $xpath = new \DOMXPath($document);
        $read = static function (string $name) use ($xpath): string {
            $nodes = $xpath->query('//*[local-name()="' . $name . '"]');
            return ($nodes && $nodes->length > 0) ? preg_replace('/\s+/', '', (string) $nodes->item(0)->nodeValue) : '';
        };
        $signature = base64_decode($read('SignatureValue'), true);
        $certificates = [];
        $nodes = $xpath->query('//*[local-name()="X509Certificate"]');
        foreach ($nodes ?: [] as $node) {
            $der = base64_decode(preg_replace('/\s+/', '', (string) $node->nodeValue), true);
            if ($der !== false && $der !== '') {
                $certificates[] = $der;
            }
        }
        if ($signature === false || $signature === '' || $certificates === []) {
            throw new \RuntimeException('Mobil imza yanıtında imza veya sertifika bulunamadı.');
        }
        return ['signature' => $signature, 'certificate' => $certificates[0], 'certificates' => $certificates, 'has_signed_attributes' => false];
    }

    private function validateCertificate(string $certificate, array $bundled, int $userId): void
    {
        $parsed = openssl_x509_parse(CmsSignedDataBuilder::pem($certificate), false);
        if (!$parsed) {
            throw new \RuntimeException('Mobil imza sertifikası okunamadı.');
        }
        $issuerName = (string) ($parsed['issuer']['commonName'] ?? '');
        if (stripos($issuerName, 'Turkcell Mobil NES') === false) {
            throw new \RuntimeException('İmza sertifikası Turkcell Mobil NES tarafından verilmemiş.');
        }

        $certDirectory = dirname(__DIR__, 2) . '/' . self::CERT_DIR;
        if (!is_file($certDirectory . '/kok.pem')) {
            throw new \RuntimeException('Mobil imza kök sertifikaları sunucuda bulunamadı.');
        }
        $work = sys_get_temp_dir() . '/mimza_' . bin2hex(random_bytes(8));
        if (!mkdir($work, 0700)) {
            throw new \RuntimeException('Sertifika doğrulama alanı oluşturulamadı.');
        }
        try {
            $leafPath = $work . '/leaf.pem';
            $untrustedPath = $work . '/untrusted.pem';
            $issuerPath = $work . '/issuer.pem';
            file_put_contents($leafPath, CmsSignedDataBuilder::pem($certificate));
            $untrusted = (string) @file_get_contents($certDirectory . '/ara.pem');
            foreach ($bundled as $der) {
                if ($der !== $certificate) {
                    $untrusted .= CmsSignedDataBuilder::pem($der);
                }
            }
            file_put_contents($untrustedPath, $untrusted);

            $verify = $this->run(['openssl', 'verify', '-CAfile', $certDirectory . '/kok.pem', '-untrusted', $untrustedPath, $leafPath]);
            if ($verify['code'] !== 0 || !str_contains($verify['output'], ': OK')) {
                error_log('MobilImza sertifika zinciri doğrulanamadı: ' . trim($verify['output']));
                throw new \RuntimeException('Mobil imza sertifikası güvenilir E-Güven kök sertifikasına zincirlenemedi.');
            }

            $issuer = $this->findIssuer($parsed, $untrusted);
            $ocspUrl = preg_match('/OCSP\s*-\s*URI:(\S+)/', (string) ($parsed['extensions']['authorityInfoAccess'] ?? ''), $m) ? $m[1] : '';
            if ($issuer !== null && $ocspUrl !== '') {
                file_put_contents($issuerPath, $issuer);
                $ocsp = $this->run(['openssl', 'ocsp', '-issuer', $issuerPath, '-cert', $leafPath, '-url', $ocspUrl,
                    '-CAfile', $certDirectory . '/kok.pem', '-verify_other', $untrustedPath, '-no_nonce', '-timeout', '10']);
                if (str_contains($ocsp['output'], ': revoked')) {
                    throw new \RuntimeException('Mobil imza sertifikası iptal edilmiş (OCSP).');
                }
                if (!str_contains($ocsp['output'], ': good')) {
                    error_log('MobilImza OCSP sorgusu tamamlanamadı: ' . trim($ocsp['output']));
                }
            }
        } finally {
            foreach (glob($work . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($work);
        }

        $subject = $parsed['subject']['serialNumber'] ?? '';
        $tckn = preg_match('/([0-9]{11})/', is_array($subject) ? implode(' ', $subject) : (string) $subject, $m) ? $m[1] : '';
        $expected = $this->model->getUserTcHash($userId);
        if ($expected !== null && $tckn !== '' && !hash_equals($expected, hash('sha256', $tckn))) {
            throw new \RuntimeException('Mobil imza sertifikasındaki T.C. kimlik numarası, kullanıcıya bağlı personel ile eşleşmiyor.');
        }
    }

    private function findIssuer(array $leaf, string $bundle): ?string
    {
        if (!preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $bundle, $matches)) {
            return null;
        }
        foreach ($matches[0] as $pem) {
            $candidate = openssl_x509_parse($pem, false);
            if ($candidate && ($candidate['subject'] ?? null) == ($leaf['issuer'] ?? [])) {
                return $pem . "\n";
            }
        }
        return null;
    }

    private function displayText(object $evrak, int $firmaId): string
    {
        $template = trim((string) ((new SettingsModel())->getSettings('mobil_imza_gosterim_metni', $firmaId) ?? ''));
        $text = strtr($template !== '' ? $template : self::DEFAULT_DISPLAY_TEXT, [
            '{evrak_no}' => (string) ($evrak->evrak_no ?? ''),
            '{konu}' => (string) ($evrak->konu ?? ''),
            '{kurum}' => (string) ($evrak->kurum_adi ?? ''),
        ]);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_substr($text !== '' ? $text : 'Evrak imzalama', 0, self::DISPLAY_MAX_LENGTH);
    }

    private function transaction(string $token, int $userId): object
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new \RuntimeException('Mobil imza işlemi geçersiz.');
        }
        $transaction = $this->model->findTransaction($token, (int) $_SESSION['firma_id'], $userId);
        if (!$transaction) {
            throw new \RuntimeException('Mobil imza işlemi bulunamadı.');
        }
        return $transaction;
    }

    private function close(object $transaction, string $status, string $message): void
    {
        $this->model->closeTransaction((int) $transaction->id, $status, $message);
        $this->removeFile((string) $transaction->hazir_pdf_yolu);
        $this->model->clearFile((int) $transaction->id);
    }

    private function purgeExpiredFiles(): void
    {
        foreach ($this->model->expiredFiles() as $row) {
            $this->removeFile((string) $row->hazir_pdf_yolu);
            $this->model->clearFile((int) $row->id);
        }
    }

    private function removeFile(string $relativePath): void
    {
        if (preg_match('#^' . preg_quote(self::WORK_DIR, '#') . '/[a-f0-9]{48}\.pdf\.enc$#', $relativePath)) {
            @unlink(dirname(__DIR__, 2) . '/' . $relativePath);
        }
    }

    private function workDirectory(): string
    {
        $directory = dirname(__DIR__, 2) . '/' . self::WORK_DIR;
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Mobil imza çalışma klasörü oluşturulamadı.');
        }
        return $directory;
    }

    private function run(array $command): array
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return ['code' => 1, 'output' => ''];
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['code' => proc_close($process), 'output' => $output];
    }
}
