<?php

namespace App\Service;

use App\Helper\Security;
use App\Model\EvrakTakipModel;
use PDO;

final class PadesEImzaService
{
    // Base64 taşıma ek yüküyle birlikte PHP'nin 40 MB POST sınırının altında kalır.
    private const MAX_PDF_BYTES = 25 * 1024 * 1024;

    public function prepare(int $evrakId, int $userId): array
    {
        $model = new EvrakTakipModel();
        $evrak = $model->getById($evrakId);
        if (!$evrak || ($evrak->onay_durumu ?? '') !== 'onay_bekliyor') {
            throw new \RuntimeException('Evrak elektronik imza sırasında değil.');
        }
        $next = $model->getNextPendingSigner($evrakId);
        if (!$next || (int) $next->kullanici_id !== $userId) {
            throw new \RuntimeException('Bu evrakta imza sırası sizde değil.');
        }

        $pdf = $this->currentPdf($evrak, $evrakId);
        if (strlen($pdf) > self::MAX_PDF_BYTES) {
            throw new \RuntimeException('İmzalanacak PDF 25 MB sınırını aşıyor.');
        }
        $tempId = bin2hex(random_bytes(24));
        $this->storePreparedPdf($tempId, $evrakId, $userId, $pdf);
        return [
            'pdf' => base64_encode($pdf),
            'sha256' => hash('sha256', $pdf),
            'temp_id' => $tempId,
            'signer_name' => (string) $next->adi_soyadi,
            'reason' => 'Resmî evrak elektronik imza onayı',
            'location' => 'Türkiye',
        ];
    }

    public function accept(int $evrakId, int $userId, string $tempId, string $signedPdfBase64, string $ipAddress): array
    {
        $signedPdf = base64_decode($signedPdfBase64, true);
        if ($signedPdf === false || strlen($signedPdf) < 100 || strlen($signedPdf) > self::MAX_PDF_BYTES) {
            throw new \RuntimeException('İmzalı PDF verisi geçersiz veya çok büyük.');
        }

        $model = new EvrakTakipModel();
        $evrak = $model->getById($evrakId);
        if (!$evrak) throw new \RuntimeException('Evrak bulunamadı.');
        $sourcePdf = $this->loadPreparedPdf($tempId, $evrakId, $userId);
        if (strlen($signedPdf) <= strlen($sourcePdf)
            || !hash_equals(hash('sha256', $sourcePdf), hash('sha256', substr($signedPdf, 0, strlen($sourcePdf))))) {
            throw new \RuntimeException('İmzalı PDF, sunucunun hazırladığı değişmez evrak sürümüne ait değil.');
        }

        $certificate = $this->verifyLatestSignature($signedPdf);
        $db = $model->getDb();
        $this->bindCertificate($db, $userId, $certificate);
        $pdfSha256 = hash('sha256', $signedPdf);
        $relativePath = 'uploads/evrak-pades/' . (int) $_SESSION['firma_id'] . '/evrak_' . $evrakId . '_' . $pdfSha256 . '.pdf.enc';
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        if (!is_dir(dirname($absolutePath)) && !mkdir(dirname($absolutePath), 0770, true) && !is_dir(dirname($absolutePath))) {
            throw new \RuntimeException('İmzalı evrak klasörü oluşturulamadı.');
        }
        $temporary = $absolutePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, Security::encryptFile($signedPdf), LOCK_EX) === false) {
            throw new \RuntimeException('İmzalı PDF güvenli depoya yazılamadı.');
        }
        if (!rename($temporary, $absolutePath)) {
            @unlink($temporary);
            throw new \RuntimeException('İmzalı PDF kalıcı depoya alınamadı.');
        }

        try {
            $result = $model->approveWithPades($evrakId, $userId, $ipAddress, $relativePath, $pdfSha256, $certificate);
            $this->deletePreparedPdf($tempId);
            return $result;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function currentPdf(object $evrak, int $evrakId): string
    {
        if (!empty($evrak->pades_pdf_yolu)) {
            $path = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->pades_pdf_yolu, '/');
            if (!is_file($path)) throw new \RuntimeException('Önceki PAdES sürümü bulunamadı.');
            return Security::decryptFile((string) file_get_contents($path));
        }
        $tmp = (new EvrakPdfService())->generateTempPdf($evrakId, true);
        try {
            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function storePreparedPdf(string $tempId, int $evrakId, int $userId, string $pdf): void
    {
        $dir = $this->preparedDirectory();
        $metadata = json_encode([
            'evrak_id' => $evrakId,
            'firma_id' => (int) $_SESSION['firma_id'],
            'user_id' => $userId,
            'sha256' => hash('sha256', $pdf),
            'expires_at' => time() + 900,
        ], JSON_UNESCAPED_SLASHES);
        if ($metadata === false
            || file_put_contents($dir . '/' . $tempId . '.pdf.enc', Security::encryptFile($pdf), LOCK_EX) === false
            || file_put_contents($dir . '/' . $tempId . '.json', $metadata, LOCK_EX) === false) {
            $this->deletePreparedPdf($tempId);
            throw new \RuntimeException('İmzalanacak PDF geçici olarak sabitlenemedi.');
        }
    }

    private function loadPreparedPdf(string $tempId, int $evrakId, int $userId): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $tempId)) {
            throw new \RuntimeException('PAdES imza oturumu geçersiz.');
        }
        $dir = $this->preparedDirectory();
        $metaPath = $dir . '/' . $tempId . '.json';
        $pdfPath = $dir . '/' . $tempId . '.pdf.enc';
        $metadata = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        if (!is_array($metadata) || !is_file($pdfPath)
            || (int) ($metadata['evrak_id'] ?? 0) !== $evrakId
            || (int) ($metadata['firma_id'] ?? 0) !== (int) $_SESSION['firma_id']
            || (int) ($metadata['user_id'] ?? 0) !== $userId
            || (int) ($metadata['expires_at'] ?? 0) < time()) {
            $this->deletePreparedPdf($tempId);
            throw new \RuntimeException('PAdES imza oturumunun süresi dolmuş veya oturum geçersiz. Lütfen yeniden imzalayınız.');
        }
        $pdf = Security::decryptFile((string) file_get_contents($pdfPath));
        if (!hash_equals((string) ($metadata['sha256'] ?? ''), hash('sha256', $pdf))) {
            $this->deletePreparedPdf($tempId);
            throw new \RuntimeException('Sabitlenmiş PDF bütünlük kontrolünden geçemedi.');
        }
        return $pdf;
    }

    private function preparedDirectory(): string
    {
        $dir = dirname(__DIR__, 2) . '/uploads/evrak-pades/.prepared';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('PAdES geçici dosya alanı oluşturulamadı.');
        }
        foreach (glob($dir . '/*.json') ?: [] as $metaPath) {
            if (filemtime($metaPath) !== false && filemtime($metaPath) < time() - 1800) {
                $id = basename($metaPath, '.json');
                if (preg_match('/^[a-f0-9]{48}$/', $id)) $this->deletePreparedPdf($id);
            }
        }
        return $dir;
    }

    private function deletePreparedPdf(string $tempId): void
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $tempId)) return;
        $dir = dirname(__DIR__, 2) . '/uploads/evrak-pades/.prepared';
        @unlink($dir . '/' . $tempId . '.pdf.enc');
        @unlink($dir . '/' . $tempId . '.json');
    }

    private function verifyLatestSignature(string $pdf): array
    {
        if (!preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $matches, PREG_SET_ORDER)) {
            throw new \RuntimeException('PDF içinde PAdES ByteRange imzası bulunamadı.');
        }
        $range = end($matches);
        [$start1, $length1, $start2, $length2] = array_map('intval', array_slice($range, 1));
        if ($start1 !== 0 || $length1 <= 0 || $start2 <= $length1 || $start2 + $length2 !== strlen($pdf)) {
            throw new \RuntimeException('PAdES ByteRange yapısı geçersiz.');
        }
        $gap = substr($pdf, $length1, $start2 - $length1);
        // PDFBox ByteRange aralığı çoğunlukla yalnızca `<...>` imza değerini dışarıda
        // bırakır; `/Contents` anahtarı imzalanan ilk aralığın içinde kalır. Bazı PDF
        // üreticileri ise anahtarı da boşluğa dahil eder. Her iki biçimi kabul et.
        if (preg_match('/\/Contents\s*<([0-9A-Fa-f\s]+)>/s', $gap, $contentMatch)
            || preg_match('/^\s*<([0-9A-Fa-f\s]+)>\s*$/s', $gap, $contentMatch)) {
            $hexContents = preg_replace('/\s+/', '', $contentMatch[1]);
        } else {
            throw new \RuntimeException('PAdES CMS imza paketi bulunamadı.');
        }
        $cms = hex2bin($hexContents);
        if ($cms === false) throw new \RuntimeException('PAdES CMS paketi çözülemedi.');
        $cms = $this->trimDerPadding($cms);
        $signedContent = substr($pdf, 0, $length1) . substr($pdf, $start2, $length2);

        $dir = sys_get_temp_dir() . '/pades_' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0700)) throw new \RuntimeException('İmza doğrulama alanı oluşturulamadı.');
        $cmsPath = $dir . '/signature.der';
        $contentPath = $dir . '/content.bin';
        $certPath = $dir . '/certificate.pem';
        file_put_contents($cmsPath, $cms);
        file_put_contents($contentPath, $signedContent);
        try {
            $verify = $this->run(['openssl', 'cms', '-verify', '-binary', '-inform', 'DER', '-in', $cmsPath, '-content', $contentPath, '-noverify', '-out', '/dev/null']);
            if ($verify['code'] !== 0) throw new \RuntimeException('PAdES kriptografik imzası doğrulanamadı.');
            $extract = $this->run(['openssl', 'pkcs7', '-inform', 'DER', '-in', $cmsPath, '-print_certs', '-out', $certPath]);
            if ($extract['code'] !== 0 || !is_file($certPath)) throw new \RuntimeException('İmza sertifikası okunamadı.');
            $pem = (string) file_get_contents($certPath);
            $cert = openssl_x509_read($pem);
            $parsed = $cert ? openssl_x509_parse($cert, false) : false;
            if (!$cert || !$parsed) throw new \RuntimeException('İmza sertifikası geçersiz.');
            $now = time();
            if ($now < (int) ($parsed['validFrom_time_t'] ?? 0) || $now > (int) ($parsed['validTo_time_t'] ?? 0)) {
                throw new \RuntimeException('İmza sertifikasının geçerlilik süresi dolmuş veya henüz başlamamış.');
            }
            $subject = $this->dn($parsed['subject'] ?? []);
            $issuer = $this->dn($parsed['issuer'] ?? []);
            return [
                'subject' => $subject,
                'issuer' => $issuer,
                'serial' => (string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''),
                'fingerprint' => strtolower(str_replace(':', '', (string) openssl_x509_fingerprint($cert, 'sha256'))),
                'valid_from' => date('Y-m-d H:i:s', (int) $parsed['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']),
                'tckn' => preg_match('/(?:SERIALNUMBER|serialNumber)\s*=\s*(?:TCKN)?\s*([0-9]{11})/i', $subject, $m) ? $m[1] : '',
            ];
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
            @rmdir($dir);
        }
    }

    private function bindCertificate(PDO $db, int $userId, array $cert): void
    {
        $bound = $db->prepare("SELECT sertifika_parmak_izi FROM kullanici_eimza_sertifikalari
            WHERE firma_id = :firma_id AND kullanici_id = :kullanici_id AND is_active = 1 AND deleted_at IS NULL");
        $bound->execute(['firma_id' => (int) $_SESSION['firma_id'], 'kullanici_id' => $userId]);
        foreach ($bound->fetchAll(PDO::FETCH_COLUMN) as $fingerprint) {
            if (!hash_equals((string) $fingerprint, (string) $cert['fingerprint'])) {
                throw new \RuntimeException('Bu kullanıcıya daha önce farklı bir e-imza sertifikası bağlanmış. Sertifika değişikliği yetkili yönetici tarafından yapılmalıdır.');
            }
        }
        $sql = $db->prepare("INSERT INTO kullanici_eimza_sertifikalari
            (firma_id, kullanici_id, sertifika_parmak_izi, sertifika_ozne, sertifika_seri_no, tckn_hash)
            VALUES (:firma_id, :kullanici_id, :fingerprint, :subject, :serial, :tckn_hash)
            ON DUPLICATE KEY UPDATE son_kullanim_tarihi = NOW(), is_active = 1, deleted_at = NULL");
        $sql->execute([
            'firma_id' => (int) $_SESSION['firma_id'], 'kullanici_id' => $userId,
            'fingerprint' => $cert['fingerprint'], 'subject' => $cert['subject'], 'serial' => $cert['serial'],
            'tckn_hash' => $cert['tckn'] !== '' ? hash('sha256', $cert['tckn']) : null,
        ]);
    }

    private function trimDerPadding(string $der): string
    {
        if (strlen($der) < 2 || ord($der[0]) !== 0x30) throw new \RuntimeException('CMS DER paketi geçersiz.');
        $first = ord($der[1]);
        if (($first & 0x80) === 0) return substr($der, 0, 2 + $first);
        $count = $first & 0x7f;
        if ($count < 1 || $count > 4 || strlen($der) < 2 + $count) throw new \RuntimeException('CMS DER uzunluğu geçersiz.');
        $length = 0;
        for ($i = 0; $i < $count; $i++) $length = ($length << 8) | ord($der[2 + $i]);
        return substr($der, 0, 2 + $count + $length);
    }

    private function run(array $command): array
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return ['code' => 1, 'output' => ''];
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return ['code' => proc_close($process), 'output' => $output];
    }

    private function dn(array $parts): string
    {
        $result = [];
        foreach ($parts as $key => $value) $result[] = $key . '=' . (is_array($value) ? implode('+', $value) : $value);
        return implode(', ', $result);
    }
}
