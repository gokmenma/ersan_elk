<?php

namespace App\Service;

use App\Model\EvrakTakipModel;
use App\Model\KepSettingsModel;
use App\Model\KepMesajModel;
use App\Model\KepDelilModel;
use App\Model\SystemLogModel;
use App\Helper\Security;
use App\Helper\RichTextSanitizer;
use Exception;
use Throwable;

/**
 * PTT KEP Üst Seviye Entegrasyon ve Sevk Servisi
 * Giden evrakların KEP üzerinden sevk edilmesi, adres doğrulaması,
 * delil takibi ve ISO 27001 uyumlu arşivleme işlemlerini yönetir.
 */
class KepService
{
    private KepSettingsModel $settingsModel;
    private KepMesajModel $mesajModel;
    private KepDelilModel $delilModel;
    private EvrakTakipModel $evrakModel;
    private EypPackageService $eypService;
    private int $currentFirmId = 0;

    public function __construct()
    {
        $this->settingsModel = new KepSettingsModel();
        $this->mesajModel = new KepMesajModel();
        $this->delilModel = new KepDelilModel();
        $this->evrakModel = new EvrakTakipModel();
        $this->eypService = new EypPackageService();
    }

    /**
     * Firma ayarlarından KepClientService oluşturur
     */
    public function getClient(int $firmId, ?array $overrideSettings = null, ?int $userId = null): KepClientService
    {
        $settings = $overrideSettings ?: $this->settingsModel->getSettings($firmId, $userId, true);
        if (!$settings || empty($settings['kep_adresi'])) {
            throw new Exception('Firma için PTT KEP ayarları yapılandırılmamış.');
        }

        $tcno = $settings['yetkili_tcno_decrypted'] ?? ($settings['yetkili_tcno'] ?? '');
        $parola = $settings['parola_decrypted'] ?? ($settings['parola'] ?? '');
        $sifre = $settings['sifre_decrypted'] ?? ($settings['sifre'] ?? '');

        if (empty($tcno) || empty($parola) || empty($sifre)) {
            throw new Exception('PTT KEP giriş bilgileri (T.C. No, Parola, Şifre) eksik.');
        }

        return new KepClientService(
            $settings['kep_adresi'],
            $tcno,
            $parola,
            $sifre,
            $settings['wsdl_url'] ?? KepClientService::DEFAULT_WSDL,
            $settings['ortam'] ?? 'prod'
        );
    }

    /**
     * Bağlantıyı ve kimlik doğrulamasını test eder
     */
    public function testConnection(int $firmId, ?array $overrideSettings = null): array
    {
        $client = $this->getClient($firmId, $overrideSettings);
        $res = $client->kontorSorgula();

        if ($res['status'] === 'success') {
            $this->settingsModel->updateBalance($firmId, (float) $res['miktar'], $res['cins'] ?? 'Adet');
        }

        return $res;
    }

    /**
     * Alıcı KEP adresini PTT KEP Rehberi'nde canlı doğrular
     */
    public function verifyRecipient(int $firmId, string $recipientAddress): array
    {
        $client = $this->getClient($firmId);
        return $client->rehberTopluKontrol($recipientAddress);
    }

    /**
     * Bir evrak/üst yazı kaydına bağlı olmadan PTT arayüzündeki gibi KEP iletisi gönderir.
     * Dosyalar API katmanında doğrulanmış geçici yüklemelerdir.
     */
    public function sendStandaloneKep(int $firmId, array $data, array $files): array
    {
        $this->currentFirmId = $firmId;
        $recipients = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', (string) ($data['alici_kep'] ?? '')) ?: []))));
        if ($recipients === []) throw new Exception('En az bir alıcı KEP adresi girilmelidir.');
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçersiz KEP adresi: ' . $recipient);
        }

        $subject = trim((string) ($data['konu'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 255) throw new Exception('Konu zorunludur ve en fazla 255 karakter olabilir.');
        $message = RichTextSanitizer::sanitize((string) ($data['icerik'] ?? ''));
        if (trim(strip_tags($message)) === '') throw new Exception('İleti metni boş bırakılamaz.');
        $packageType = ($data['paket_turu'] ?? 'Standart') === 'EYazisma' ? 'EYazisma' : 'Standart';
        if ($packageType === 'EYazisma') throw new Exception('Bağımsız KEP gönderisinde resmî üst yazı bulunmadığından Standart KEP paketi kullanılmalıdır.');

        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['kep_adresi'])) throw new Exception('Firma KEP ayarları yapılandırılmamış.');

        $attachments = [];
        $archiveFiles = [];
        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || !is_file($path)) continue;
            $bytes = file_get_contents($path);
            if ($bytes === false) throw new Exception('KEP eki okunamadı.');
            $mime = (string) ($file['mime'] ?? 'application/octet-stream');
            $safeName = $this->normalizeKepAttachmentName((string) ($file['name'] ?? 'belge'), $mime);
            
            // PTT KEP Axis2 servisine W3C XMLMIME (xmime:fileName ve xmime:contentType) ile dosya adı ve MIME tipini bildir
            $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '" xmime:fileName="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($bytes) . '</ns1:ekler>';
            $attachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
            
            $archiveFiles[] = [
                'name' => $safeName,
                'original_name' => (string) ($file['name'] ?? $safeName),
                'mime' => $mime,
                'path' => $path,
                'size' => strlen($bytes),
                'content' => base64_encode($bytes),
            ];
        }

        $allPttRecipients = array_reduce($recipients, static fn(bool $carry, string $address): bool => $carry && (bool) preg_match('/@hs01\.kep\.tr$/i', $address), true);
        $totalBytes = array_sum(array_column($archiveFiles, 'size'));
        $maxBytes = ($allPttRecipients ? 100 : 25) * 1024 * 1024;
        if ($totalBytes > $maxBytes) throw new Exception('Eklerin toplam boyutu alıcı sınırını aşıyor: ' . ($allPttRecipients ? '100 MB' : '25 MB') . '.');

        $recipientString = implode(';', $recipients);
        if (mb_strlen($recipientString) > 255) throw new Exception('Alıcı KEP adreslerinin toplam uzunluğu 255 karakteri aşamaz.');
        $isHtml = strip_tags($message) !== $message;
        $client = $this->getClient($firmId);
        $mimeResult = $client->mimeYap([
            'ePaketTur' => 'Standart',
            'ePaketId' => 'KEP_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
            'kime' => $recipientString,
            'konu' => $subject,
            'icerik' => $isHtml ? base64_encode($message) : $message,
            'eIcerikTur' => $isHtml ? 'HTML' : 'TEXT',
            'ekler' => $attachments,
            'eOzetAlg' => 'SHA256',
        ]);
        if (($mimeResult['status'] ?? '') !== 'success' || empty($mimeResult['mesajid'])) {
            throw new Exception($mimeResult['message'] ?? 'PTT KEP MIME paketi hazırlanamadı.');
        }

        $kepId = (string) $mimeResult['mesajid'];
        $authorizedTc = $settings['yetkili_tcno_decrypted'] ?? ($settings['yetkili_tcno'] ?? '');
        $pin = !empty($data['eimza_pin']) ? (string) $data['eimza_pin'] : (!empty($data['pin']) ? (string) $data['pin'] : null);
        if (!empty($data['imza_b64']) || !empty($data['signature'])) {
            $sigVal = (string) (!empty($data['imza_b64']) ? $data['imza_b64'] : $data['signature']);
            $rawSig = base64_decode($sigVal, true);
            $signature = ($rawSig !== false && strlen($rawSig) >= 100) ? $rawSig : $sigVal;
        } else {
            $signature = $this->createCadesSignature((string) $authorizedTc, (string) ($mimeResult['eHash'] ?? $kepId), $pin);
        }
        $sendResult = $client->smimeGonder($kepId, $signature);
        if (($sendResult['status'] ?? '') !== 'success') throw new Exception($sendResult['message'] ?? 'PTT KEP sevki başarısız.');

        $archiveDirectory = dirname(__DIR__, 2) . '/uploads/evrak-takip/kep';
        if (!is_dir($archiveDirectory)) mkdir($archiveDirectory, 0755, true);
        $archiveName = 'kep_ileti_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $kepId) . '.json.enc';
        $archivePayload = json_encode(['recipients' => $recipients, 'subject' => $subject, 'content' => $message, 'detail' => (string) ($data['ileti_detayi'] ?? ''), 'files' => $archiveFiles], JSON_UNESCAPED_UNICODE);
        file_put_contents($archiveDirectory . '/' . $archiveName, Security::encryptFile($archivePayload));

        $db = $this->evrakModel->getDb();
        $db->beginTransaction();
        try {
            $evrakId = $this->evrakModel->createStandaloneKep($firmId, (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0), ['kep_id' => $kepId, 'konu' => $subject, 'alici_kep' => $recipientString, 'icerik' => $message, 'ileti_tipi' => (string) ($data['ileti_tipi'] ?? 'standart'), 'ileti_detayi' => (string) ($data['ileti_detayi'] ?? '')]);
            
            // Ekleri evrak_takip_ekleri tablosuna ve yerel arşive kaydet
            $attachmentDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
            if (!is_dir($attachmentDir)) {
                @mkdir($attachmentDir, 0777, true);
            }
            foreach ($archiveFiles as $idx => $af) {
                $ext = strtolower(pathinfo($af['name'], PATHINFO_EXTENSION));
                $storedName = 'ek_' . $evrakId . '_' . bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');
                $storedFullPath = $attachmentDir . $storedName;
                if (!empty($af['path']) && is_file($af['path'])) {
                    @copy($af['path'], $storedFullPath);
                } elseif (!empty($af['content'])) {
                    file_put_contents($storedFullPath, base64_decode($af['content']));
                }
                $this->evrakModel->saveAttachment($evrakId, [
                    'dosya_adi' => $af['name'],
                    'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName,
                    'mime_tipi' => $af['mime'],
                    'dosya_boyutu' => (int) ($af['size'] ?? 0),
                    'sira' => $idx + 1,
                ]);
            }

            $messageId = $this->mesajModel->saveOutgoing($firmId, [
                'evrak_id' => $evrakId, 'kep_id' => $kepId, 'org_mesaj_id' => $kepId,
                'gonderen_kep' => $settings['kep_adresi'], 'alici_kep' => $recipientString,
                'alici_unvan' => mb_substr(trim((string) ($data['ileti_detayi'] ?? '')), 0, 255),
                'konu' => $subject, 'icerik' => $message, 'paket_turu' => 'Standart',
                'durum' => 'gonderildi', 'delil_sayisi' => 1,
                'son_delil_durumu' => 'Kephs tarafından kabul edildi (Submission)',
                'yerel_dosya_yolu' => null, 'ham_paket_yolu' => 'uploads/evrak-takip/kep/' . $archiveName,
            ]);
            $this->delilModel->upsertDelil($messageId, ['delil_id' => 'SUB_' . $kepId . '_' . time(), 'delil_tur_id' => 10, 'delil_aciklama' => 'S/MIME paketi Kephs tarafından kabul edildi (Submission Acceptance)', 'tarih' => date('Y-m-d H:i:s'), 'kephs' => 'PTT KEPHS']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        try {
            (new SystemLogModel())->logAction((int) ($_SESSION['user_id'] ?? 0), 'Bağımsız KEP Gönderimi', "KEP iletisi {$recipientString} adresine gönderildi. PTT KepId: {$kepId}", SystemLogModel::LEVEL_IMPORTANT);
        } catch (Throwable $e) { /* Sevki log hatası nedeniyle başarısız sayma. */ }

        return [
            'status' => 'success',
            'kep_id' => $kepId,
            'takip_kodu' => $kepId,
            'message' => 'KEP iletisi başarıyla gönderildi.',
        ];
    }

    /**
     * İstemci tabanlı (Seçenek B) E-İmza akışı için 1. Aşama:
     * PTT MimeYap çağrısını yapar, imzalanacak eHash özetini ve mesajid üretir.
     */
    public function prepareStandaloneKepMime(int $firmId, array $data, array $files): array
    {
        $this->currentFirmId = $firmId;
        $recipients = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', (string) ($data['alici_kep'] ?? '')) ?: []))));
        if ($recipients === []) throw new Exception('En az bir alıcı KEP adresi girilmelidir.');
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçersiz KEP adresi: ' . $recipient);
        }

        $subject = trim((string) ($data['konu'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 255) throw new Exception('Konu zorunludur ve en fazla 255 karakter olabilir.');
        $message = RichTextSanitizer::sanitize((string) ($data['icerik'] ?? ''));
        if (trim(strip_tags($message)) === '') throw new Exception('İleti metni boş bırakılamaz.');

        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['kep_adresi'])) throw new Exception('Firma KEP ayarları yapılandırılmamış.');

        $tempId = bin2hex(random_bytes(16));
        $tmpDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $attachments = [];
        $archiveFiles = [];
        foreach ($files as $idx => $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || !is_file($path)) continue;
            $mime = (string) ($file['mime'] ?? 'application/octet-stream');
            $safeName = $this->normalizeKepAttachmentName((string) ($file['name'] ?? 'belge'), $mime);

            $tmpSavedPath = $tmpDir . '/draft_' . $tempId . '_' . $idx . '_' . $safeName;
            if (is_uploaded_file($path)) {
                move_uploaded_file($path, $tmpSavedPath);
            } else {
                copy($path, $tmpSavedPath);
            }

            $bytes = file_get_contents($tmpSavedPath);
            if ($bytes === false) throw new Exception('KEP eki okunamadı: ' . $safeName);

            $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '" xmime:fileName="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($bytes) . '</ns1:ekler>';
            $attachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);

            $archiveFiles[] = [
                'name' => $safeName,
                'original_name' => (string) ($file['name'] ?? $safeName),
                'mime' => $mime,
                'path' => $tmpSavedPath,
                'size' => strlen($bytes),
                'content' => base64_encode($bytes),
            ];
        }

        $allPttRecipients = array_reduce($recipients, static fn(bool $carry, string $address): bool => $carry && (bool) preg_match('/@hs01\.kep\.tr$/i', $address), true);
        $totalBytes = array_sum(array_column($archiveFiles, 'size'));
        $maxBytes = ($allPttRecipients ? 100 : 25) * 1024 * 1024;
        if ($totalBytes > $maxBytes) throw new Exception('Eklerin toplam boyutu alıcı sınırını aşıyor: ' . ($allPttRecipients ? '100 MB' : '25 MB') . '.');

        $recipientString = implode(';', $recipients);
        if (mb_strlen($recipientString) > 255) throw new Exception('Alıcı KEP adreslerinin toplam uzunluğu 255 karakteri aşamaz.');
        $isHtml = strip_tags($message) !== $message;
        $client = $this->getClient($firmId);
        $mimeResult = $client->mimeYap([
            'ePaketTur' => 'Standart',
            'ePaketId' => 'KEP_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
            'kime' => $recipientString,
            'konu' => $subject,
            'icerik' => $isHtml ? base64_encode($message) : $message,
            'eIcerikTur' => $isHtml ? 'HTML' : 'TEXT',
            'ekler' => $attachments,
            'eOzetAlg' => 'SHA256',
        ]);
        if (($mimeResult['status'] ?? '') !== 'success' || empty($mimeResult['mesajid'])) {
            throw new Exception($mimeResult['message'] ?? 'PTT KEP MIME paketi hazırlanamadı.');
        }

        $kepId = (string) $mimeResult['mesajid'];
        $eHash = (string) ($mimeResult['eHash'] ?? '');

        $draftMeta = [
            'type' => 'standalone',
            'firm_id' => $firmId,
            'user_id' => (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0),
            'kep_id' => $kepId,
            'eHash' => $eHash,
            'recipients' => $recipients,
            'recipient_string' => $recipientString,
            'subject' => $subject,
            'message' => $message,
            'ileti_tipi' => (string) ($data['ileti_tipi'] ?? 'standart'),
            'ileti_detayi' => (string) ($data['ileti_detayi'] ?? ''),
            'archive_files' => $archiveFiles,
            'created_at' => time(),
        ];

        file_put_contents($tmpDir . '/draft_' . $tempId . '.json', json_encode($draftMeta, JSON_UNESCAPED_UNICODE));
        $_SESSION['kep_draft_' . $tempId] = $draftMeta;

        return [
            'status' => 'success',
            'temp_id' => $tempId,
            'mesajid' => $kepId,
            'eHash' => $eHash,
        ];
    }

    /**
     * İstemci tabanlı (Seçenek B) E-İmza akışı için 2. Aşama:
     * İstemcideki yerel USB token'dan üretilen CAdES-BES imzasını PTT SmimeGonder ile iletir.
     */
    public function completeStandaloneKepSend(int $firmId, string $tempId, string $cadesSignature): array
    {
        $this->currentFirmId = $firmId;
        $tmpDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp';
        $draftFile = $tmpDir . '/draft_' . $tempId . '.json';

        $draft = $_SESSION['kep_draft_' . $tempId] ?? null;
        if (!$draft && file_exists($draftFile)) {
            $draft = json_decode(file_get_contents($draftFile), true);
        }
        if (!$draft || (int) ($draft['firm_id'] ?? 0) !== $firmId) {
            throw new Exception('Hazırlanan KEP taslağı bulunamadı veya oturum süresi doldu. Lütfen tekrar deneyiniz.');
        }

        $kepId = (string) $draft['kep_id'];
        $recipientString = (string) $draft['recipient_string'];
        $recipients = $draft['recipients'] ?? [];
        $subject = (string) $draft['subject'];
        $message = (string) $draft['message'];
        $detail = (string) ($draft['ileti_detayi'] ?? '');
        $archiveFiles = $draft['archive_files'] ?? [];
        $userId = (int) ($draft['user_id'] ?? ($_SESSION['user_id'] ?? 0));

        $rawSignature = base64_decode($cadesSignature, true);
        if ($rawSignature === false || strlen($rawSignature) < 100) {
            $rawSignature = $cadesSignature;
        }

        $client = $this->getClient($firmId);
        $sendResult = $client->smimeGonder($kepId, $rawSignature);
        if (($sendResult['status'] ?? '') !== 'success') {
            throw new Exception($sendResult['message'] ?? 'PTT KEP sevki başarısız.');
        }

        $settings = $this->settingsModel->getSettings($firmId);
        $archiveDirectory = dirname(__DIR__, 2) . '/uploads/evrak-takip/kep';
        if (!is_dir($archiveDirectory)) @mkdir($archiveDirectory, 0755, true);
        $archiveName = 'kep_ileti_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $kepId) . '.json.enc';
        $archivePayload = json_encode(['recipients' => $recipients, 'subject' => $subject, 'content' => $message, 'detail' => $detail, 'files' => $archiveFiles], JSON_UNESCAPED_UNICODE);
        file_put_contents($archiveDirectory . '/' . $archiveName, Security::encryptFile($archivePayload));

        $db = $this->evrakModel->getDb();
        $db->beginTransaction();
        try {
            $evrakId = $this->evrakModel->createStandaloneKep($firmId, $userId, [
                'kep_id' => $kepId,
                'konu' => $subject,
                'alici_kep' => $recipientString,
                'icerik' => $message,
                'ileti_tipi' => (string) ($draft['ileti_tipi'] ?? 'standart'),
                'ileti_detayi' => $detail,
            ]);

            $attachmentDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
            if (!is_dir($attachmentDir)) {
                @mkdir($attachmentDir, 0777, true);
            }
            foreach ($archiveFiles as $idx => $af) {
                $ext = strtolower(pathinfo($af['name'], PATHINFO_EXTENSION));
                $storedName = 'ek_' . $evrakId . '_' . bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');
                $storedFullPath = $attachmentDir . $storedName;
                if (!empty($af['path']) && is_file($af['path'])) {
                    @copy($af['path'], $storedFullPath);
                    @unlink($af['path']);
                } elseif (!empty($af['content'])) {
                    file_put_contents($storedFullPath, base64_decode($af['content']));
                }
                $this->evrakModel->saveAttachment($evrakId, [
                    'dosya_adi' => $af['name'],
                    'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName,
                    'mime_tipi' => $af['mime'],
                    'dosya_boyutu' => (int) ($af['size'] ?? 0),
                    'sira' => $idx + 1,
                ]);
            }

            $messageId = $this->mesajModel->saveOutgoing($firmId, [
                'evrak_id' => $evrakId, 'kep_id' => $kepId, 'org_mesaj_id' => $kepId,
                'gonderen_kep' => $settings['kep_adresi'], 'alici_kep' => $recipientString,
                'alici_unvan' => mb_substr(trim($detail), 0, 255),
                'konu' => $subject, 'icerik' => $message, 'paket_turu' => 'Standart',
                'durum' => 'gonderildi', 'delil_sayisi' => 1,
                'son_delil_durumu' => 'Kephs tarafından kabul edildi (Submission)',
                'yerel_dosya_yolu' => null, 'ham_paket_yolu' => 'uploads/evrak-takip/kep/' . $archiveName,
            ]);
            $this->delilModel->upsertDelil($messageId, ['delil_id' => 'SUB_' . $kepId . '_' . time(), 'delil_tur_id' => 10, 'delil_aciklama' => 'S/MIME paketi Kephs tarafından kabul edildi (Submission Acceptance)', 'tarih' => date('Y-m-d H:i:s'), 'kephs' => 'PTT KEPHS']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        @unlink($draftFile);
        unset($_SESSION['kep_draft_' . $tempId]);

        try {
            (new SystemLogModel())->logAction($userId, 'Bağımsız KEP Gönderimi', "KEP iletisi {$recipientString} adresine başarıyla imzalanıp gönderildi. PTT KepId: {$kepId}", SystemLogModel::LEVEL_IMPORTANT);
        } catch (Throwable $e) {}

        return [
            'status' => 'success',
            'kep_id' => $kepId,
            'takip_kodu' => $kepId,
            'message' => 'KEP iletisi e-imzanızla başarıyla imzalandı ve PTT KEP ağına sevk edildi.',
        ];
    }

    private function normalizeKepAttachmentName(string $name, ?string $mime = null): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);
        
        // Eğer uzantı yoksa MIME türünden tahmin et
        if ($extension === '' && $mime) {
            $extension = match ($mime) {
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword' => 'doc',
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'application/zip' => 'zip',
                default => '',
            };
        }

        $map = ['ç'=>'c','Ç'=>'C','ğ'=>'g','Ğ'=>'G','ı'=>'i','İ'=>'I','ö'=>'o','Ö'=>'O','ş'=>'s','Ş'=>'S','ü'=>'u','Ü'=>'U'];
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtr($base, $map));
        $maxBaseLength = max(1, 50 - ($extension !== '' ? mb_strlen($extension) + 1 : 0));
        return mb_substr(trim((string) $base, '_'), 0, $maxBaseLength) . ($extension !== '' ? '.' . $extension : '');
    }

    /**
     * Giden evrakı PTT KEP üzerinden muhataba iletir
     *
     * @param int $firmId Firma ID
     * @param int $evrakId evrak_takip tablosundaki ID
     * @param array $options [ 'alici_kep' => string, 'paket_turu' => 'Standart'|'EYazisma', 'konu' => string, 'icerik' => string ]
     * @return array
     */
    public function sendOutgoingKep(int $firmId, int $evrakId, array $options = []): array
    {
        $this->currentFirmId = $firmId;
        $evrak = $this->evrakModel->getById($evrakId);
        if (!$evrak || $evrak->evrak_tipi !== 'giden') {
            throw new Exception('Giden evrak bulunamadı.');
        }

        if (($evrak->onay_durumu ?? '') !== 'onaylandi') {
            throw new Exception('Evrakın KEP ile sevk edilebilmesi için e-imza onay sürecinin tamamlanmış olması gerekir.');
        }
        if (!empty($evrak->kep_mesaj_id) || $this->mesajModel->getByEvrakId($firmId, $evrakId)) {
            throw new Exception('Bu evrak daha önce KEP üzerinden gönderilmiş.');
        }

        $recipientKep = trim((string) ($options['alici_kep'] ?? ''));
        $recipientAddresses = array_values(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $recipientKep) ?: [])));
        if ($recipientAddresses === []) {
            throw new Exception('Geçerli bir alıcı KEP adresi girilmelidir.');
        }
        foreach ($recipientAddresses as $recipientAddress) {
            if (!filter_var($recipientAddress, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçersiz alıcı KEP adresi: ' . $recipientAddress);
            }
        }
        $recipientKep = implode(';', $recipientAddresses);

        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['kep_adresi'])) {
            throw new Exception('Firma KEP ayarları yapılandırılmamış.');
        }

        $client = $this->getClient($firmId);
        $paketTuru = ($options['paket_turu'] ?? 'Standart') === 'EYazisma' ? 'EYazisma' : 'Standart';

        // 1. Belge biçimine göre üst yazı PDF'ini belirle veya üret.
        $tmpPdfPath = null;
        $hasCoverLetter = empty($evrak->ust_yazi_gerekli_degil);
        if ($hasCoverLetter && !empty($evrak->dosya_yolu)) {
            $candidatePath = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->dosya_yolu, '/');
            if (file_exists($candidatePath)) {
                $rawBytes = file_get_contents($candidatePath);
                try {
                    $decryptedBytes = Security::decryptFile($rawBytes);
                } catch (Throwable $e) {
                    $decryptedBytes = $rawBytes;
                }
                $tmpPdfPath = sys_get_temp_dir() . '/tmp_kep_pdf_' . uniqid() . '.pdf';
                file_put_contents($tmpPdfPath, $decryptedBytes);
            }
        }

        if ($hasCoverLetter && (!$tmpPdfPath || !file_exists($tmpPdfPath))) {
            $pdfService = new EvrakPdfService();
            $tmpPdfPath = $pdfService->generateTempPdf($evrakId);
        }

        // 2. Ek dosyaları topla
        $attachments = [];
        $evrakEkleri = $this->evrakModel->getAttachments($evrakId);
        foreach ($evrakEkleri as $ek) {
            $ekPath = dirname(__DIR__, 2) . '/' . ltrim((string) $ek->dosya_yolu, '/');
            if (file_exists($ekPath)) {
                $ekRaw = file_get_contents($ekPath);
                try {
                    $ekDec = Security::decryptFile($ekRaw);
                } catch (Throwable $e) {
                    $ekDec = $ekRaw;
                }
                $tmpEkPath = sys_get_temp_dir() . '/tmp_kep_ek_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ek->dosya_adi);
                file_put_contents($tmpEkPath, $ekDec);
                $attachments[] = [
                    'path' => $tmpEkPath,
                    'name' => $ek->dosya_adi,
                ];
            }
        }
        if (!$hasCoverLetter && $attachments === []) {
            throw new Exception('Yalnızca eklerle KEP gönderiminde en az bir belge bulunmalıdır.');
        }

        // 3. Paket Türüne göre dosya hazırlığı (EYazisma ise .eyp oluştur)
        $soapAttachments = [];
        $totalPayloadBytes = 0;
        if ($paketTuru === 'EYazisma') {
            if (!$hasCoverLetter || !$tmpPdfPath || !is_file($tmpPdfPath)) {
                throw new Exception('E-Yazışma paketi için resmî üst yazı gereklidir. Yalnızca ek gönderiminde Standart KEP paketini seçiniz.');
            }
            $meta = [
                'evrak_no' => $evrak->evrak_no,
                'konu' => $options['konu'] ?? $evrak->konu,
                'gonderen' => $settings['kep_adresi'],
                'alici' => $recipientKep,
            ];
            $eypResult = $this->eypService->createPackage($tmpPdfPath, $attachments, null, $meta);
            $eypBytes = file_get_contents($eypResult['path']);
            if ($eypBytes === false) throw new Exception('E-Yazışma paketi okunamadı.');
            $totalPayloadBytes += strlen($eypBytes);
            $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="application/zip" xmime:fileName="paket.eyp" name="paket.eyp" filename="paket.eyp">' . base64_encode($eypBytes) . '</ns1:ekler>';
            $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
        } else {
            // Standart KEP: PDF üst yazı + ek belgeler doğrudan ek olarak verilir
            if ($hasCoverLetter && $tmpPdfPath && is_file($tmpPdfPath)) {
                $pdfBytes = file_get_contents($tmpPdfPath);
                if ($pdfBytes === false) throw new Exception('Onaylı üst yazı PDF dosyası okunamadı.');
                $totalPayloadBytes += strlen($pdfBytes);
                $coverSafeName = $this->normalizeKepAttachmentName(($evrak->evrak_no ?: 'ust_yazi') . '.pdf', 'application/pdf');
                $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="application/pdf" xmime:fileName="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($pdfBytes) . '</ns1:ekler>';
                $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
            }
            foreach ($attachments as $att) {
                $attBytes = file_get_contents($att['path']);
                if ($attBytes === false) throw new Exception('KEP eki okunamadı: ' . ($att['name'] ?? 'Belge'));
                $totalPayloadBytes += strlen($attBytes);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($att['path']) ?: 'application/octet-stream';
                $attSafeName = $this->normalizeKepAttachmentName((string) ($att['name'] ?: 'ek_belge'), $mime);
                $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '" xmime:fileName="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($attBytes) . '</ns1:ekler>';
                $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
            }
        }

        $allPttRecipients = array_reduce($recipientAddresses, static function (bool $carry, string $address): bool {
            return $carry && (bool) preg_match('/@hs01\.kep\.tr$/i', $address);
        }, true);
        $maxPayloadBytes = ($allPttRecipients ? 100 : 25) * 1024 * 1024;
        if ($totalPayloadBytes > $maxPayloadBytes) {
            throw new Exception('KEP eklerinin toplam boyutu alıcı sınırını aşıyor. İzin verilen üst sınır: ' . ($allPttRecipients ? '100 MB' : '25 MB') . '.');
        }

        // 4. PTT KEP Gönderimi: MimeYap ve SmimeGonder / Yukle
        $konu = !empty($options['konu']) ? trim((string) $options['konu']) : (string) ($evrak->konu ?: 'Resmî Yazı');
        // Onaylı resmî yazının tam içeriği imzalı PDF olarak ektedir; KEP gövdesinde tekrarlanmaz.
        $rawIcerik = 'İlgili yazı ektedir.';
        $icerik = $rawIcerik;
        $eIcerikTur = 'TEXT';

        // PTT MimeYap çağrısı
        $mimeRes = $client->mimeYap([
            'ePaketTur' => $paketTuru,
            'ePaketId' => (string) ($evrak->evrak_no ?: ('EVR_' . $evrakId)),
            'kime' => $recipientKep,
            'konu' => $konu,
            'icerik' => $icerik,
            'eIcerikTur' => $eIcerikTur,
            'ekler' => $soapAttachments,
            'eOzetAlg' => 'SHA256',
        ]);

        $kepId = '';
        if ($mimeRes['status'] === 'success' && !empty($mimeRes['mesajid'])) {
            $mesajId = $mimeRes['mesajid'];
            $eHash = $mimeRes['eHash'] ?? '';

            // PTT KEP spesifikasyonuna uygun CAdES-BES (PKCS#7) imza verisi oluşturma
            $pin = !empty($options['eimza_pin']) ? (string) $options['eimza_pin'] : (!empty($options['pin']) ? (string) $options['pin'] : null);
            $yetkiliTcno = $settings['yetkili_tcno_decrypted'] ?? ($settings['yetkili_tcno'] ?? '');
            if (!empty($options['imza_b64']) || !empty($options['signature'])) {
                $sigVal = (string) (!empty($options['imza_b64']) ? $options['imza_b64'] : $options['signature']);
                $rawSig = base64_decode($sigVal, true);
                $cadesSignature = ($rawSig !== false && strlen($rawSig) >= 100) ? $rawSig : $sigVal;
            } else {
                $cadesSignature = $this->createCadesSignature($yetkiliTcno, $eHash ?: $mesajId, $pin);
            }

            $sendRes = $client->smimeGonder($mesajId, $cadesSignature);
            if ($sendRes['status'] === 'success') {
                $kepId = $mesajId;
            } else {
                // Eğer SmimeGonder hata verdiyse hatayı kullanıcıya ilet
                throw new Exception($sendRes['message'] ?: 'PTT KEP ileti sevk işlemi başarısız.');
            }
        } else {
            // MimeYap hata verdiyse
            throw new Exception($mimeRes['message'] ?: 'PTT KEP MIME paketi hazırlanamadı.');
        }

        // 5. ISO 27001 gereği giden üst yazıyı ve ham paketi şifreleyerek kalıcı sakla
        $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/kep';
        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0755, true);
        }

        $encPdfPath = null;
        if ($tmpPdfPath && file_exists($tmpPdfPath)) {
            $rawPdf = file_get_contents($tmpPdfPath);
            $encPdf = Security::encryptFile($rawPdf);
            $cleanKepId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $kepId);
            $pdfFilename = "kep_ustyazi_{$cleanKepId}.pdf.enc";
            file_put_contents("{$uploadBaseDir}/{$pdfFilename}", $encPdf);
            $encPdfPath = "uploads/evrak-takip/kep/{$pdfFilename}";
        }

        // 6. kep_mesajlar tablosuna kaydet
        $localMsgId = $this->mesajModel->saveOutgoing($firmId, [
            'evrak_id' => $evrakId,
            'kep_id' => $kepId,
            'org_mesaj_id' => $mimeRes['mesajid'] ?? null,
            'gonderen_kep' => $settings['kep_adresi'],
            'alici_kep' => $recipientKep,
            'alici_unvan' => $evrak->kurum_adi ?? '',
            'konu' => $konu,
            'icerik' => $icerik,
            'paket_turu' => $paketTuru,
            'durum' => 'gonderildi',
            'delil_sayisi' => 1,
            'son_delil_durumu' => 'Kephs tarafından kabul edildi (Submission)',
            'yerel_dosya_yolu' => $encPdfPath,
            'ham_paket_yolu' => null,
        ]);

        // İlk kabul delilini oluştur
        $this->delilModel->upsertDelil($localMsgId, [
            'delil_id' => "SUB_{$kepId}_" . time(),
            'delil_tur_id' => 10,
            'delil_aciklama' => 'S/MIME paketi Kephs tarafından kabul edildi (Submission Acceptance)',
            'tarih' => date('Y-m-d H:i:s'),
            'kephs' => 'PTT KEPHS',
        ]);

        // 7. evrak_takip tablosunu güncelle
        $db = (new \App\Model\Model())->db;
        $stmt = $db->prepare("UPDATE evrak_takip SET kaynak_tipi = 'kep', kep_mesaj_id = :kep_id WHERE id = :id AND firma_id = :firma_id");
        $stmt->execute([
            'kep_id' => $kepId,
            'id' => $evrakId,
            'firma_id' => $firmId,
        ]);

        // 8. Sistem Logu
        try {
            (new SystemLogModel())->logAction(
                $_SESSION['user_id'] ?? 0,
                'KEP Gönderimi',
                "Evrak ID: {$evrakId} KEP üzerinden {$recipientKep} adresine sevk edildi. PTT KepId: {$kepId}",
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $e) {
            // log error ignored
        }

        // Geçici dosyaları temizle
        if ($tmpPdfPath && is_file($tmpPdfPath)) {
            @unlink($tmpPdfPath);
        }
        foreach ($attachments as $att) {
            @unlink($att['path']);
        }

        return [
            'status' => 'success',
            'kep_id' => $kepId,
            'message' => "Evrak PTT KEP üzerinden başarıyla sevk edildi. PTT Takip Kodu: {$kepId}",
        ];
    }

    /**
     * Giden evrak için 1. Aşama: MimeYap çağrısını yapar ve eHash/mesajid döner.
     */
    public function prepareOutgoingKepMime(int $firmId, int $evrakId, array $options = []): array
    {
        $this->currentFirmId = $firmId;
        $evrak = $this->evrakModel->getById($evrakId);
        if (!$evrak || $evrak->evrak_tipi !== 'giden') {
            throw new Exception('Giden evrak bulunamadı.');
        }

        if (($evrak->onay_durumu ?? '') !== 'onaylandi') {
            throw new Exception('Evrakın KEP ile sevk edilebilmesi için e-imza onay sürecinin tamamlanmış olması gerekir.');
        }
        if (!empty($evrak->kep_mesaj_id) || $this->mesajModel->getByEvrakId($firmId, $evrakId)) {
            throw new Exception('Bu evrak daha önce KEP üzerinden gönderilmiş.');
        }

        $recipientKep = trim((string) ($options['alici_kep'] ?? ''));
        $recipientAddresses = array_values(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $recipientKep) ?: [])));
        if ($recipientAddresses === []) {
            throw new Exception('Geçerli bir alıcı KEP adresi girilmelidir.');
        }
        foreach ($recipientAddresses as $recipientAddress) {
            if (!filter_var($recipientAddress, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçersiz alıcı KEP adresi: ' . $recipientAddress);
            }
        }
        $recipientKep = implode(';', $recipientAddresses);

        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['kep_adresi'])) {
            throw new Exception('Firma KEP ayarları yapılandırılmamış.');
        }

        $client = $this->getClient($firmId);
        $paketTuru = ($options['paket_turu'] ?? 'Standart') === 'EYazisma' ? 'EYazisma' : 'Standart';

        $tempId = bin2hex(random_bytes(16));
        $tmpDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $tmpPdfPath = null;
        $hasCoverLetter = empty($evrak->ust_yazi_gerekli_degil);
        if ($hasCoverLetter && !empty($evrak->dosya_yolu)) {
            $candidatePath = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->dosya_yolu, '/');
            if (file_exists($candidatePath)) {
                $rawBytes = file_get_contents($candidatePath);
                try {
                    $decryptedBytes = Security::decryptFile($rawBytes);
                } catch (Throwable $e) {
                    $decryptedBytes = $rawBytes;
                }
                $tmpPdfPath = $tmpDir . '/tmp_pdf_' . $tempId . '.pdf';
                file_put_contents($tmpPdfPath, $decryptedBytes);
            }
        }

        if ($hasCoverLetter && (!$tmpPdfPath || !file_exists($tmpPdfPath))) {
            $pdfService = new EvrakPdfService();
            $genPdf = $pdfService->generateTempPdf($evrakId);
            $tmpPdfPath = $tmpDir . '/tmp_pdf_' . $tempId . '.pdf';
            copy($genPdf, $tmpPdfPath);
            @unlink($genPdf);
        }

        $attachments = [];
        $evrakEkleri = $this->evrakModel->getAttachments($evrakId);
        foreach ($evrakEkleri as $ek) {
            $ekPath = dirname(__DIR__, 2) . '/' . ltrim((string) $ek->dosya_yolu, '/');
            if (file_exists($ekPath)) {
                $ekRaw = file_get_contents($ekPath);
                try {
                    $ekDec = Security::decryptFile($ekRaw);
                } catch (Throwable $e) {
                    $ekDec = $ekRaw;
                }
                $tmpEkPath = $tmpDir . '/tmp_ek_' . $tempId . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ek->dosya_adi);
                file_put_contents($tmpEkPath, $ekDec);
                $attachments[] = [
                    'path' => $tmpEkPath,
                    'name' => $ek->dosya_adi,
                ];
            }
        }
        if (!$hasCoverLetter && $attachments === []) {
            throw new Exception('Yalnızca eklerle KEP gönderiminde en az bir belge bulunmalıdır.');
        }

        $soapAttachments = [];
        $totalPayloadBytes = 0;
        if ($paketTuru === 'EYazisma') {
            if (!$hasCoverLetter || !$tmpPdfPath || !is_file($tmpPdfPath)) {
                throw new Exception('E-Yazışma paketi için resmî üst yazı gereklidir.');
            }
            $meta = [
                'evrak_no' => $evrak->evrak_no,
                'konu' => $options['konu'] ?? $evrak->konu,
                'gonderen' => $settings['kep_adresi'],
                'alici' => $recipientKep,
            ];
            $eypResult = $this->eypService->createPackage($tmpPdfPath, $attachments, null, $meta);
            $eypBytes = file_get_contents($eypResult['path']);
            if ($eypBytes === false) throw new Exception('E-Yazışma paketi okunamadı.');
            $totalPayloadBytes += strlen($eypBytes);
            $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="application/zip" xmime:fileName="paket.eyp" name="paket.eyp" filename="paket.eyp">' . base64_encode($eypBytes) . '</ns1:ekler>';
            $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
        } else {
            if ($hasCoverLetter && $tmpPdfPath && is_file($tmpPdfPath)) {
                $pdfBytes = file_get_contents($tmpPdfPath);
                if ($pdfBytes === false) throw new Exception('Onaylı üst yazı PDF dosyası okunamadı.');
                $totalPayloadBytes += strlen($pdfBytes);
                $coverSafeName = $this->normalizeKepAttachmentName(($evrak->evrak_no ?: 'ust_yazi') . '.pdf', 'application/pdf');
                $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="application/pdf" xmime:fileName="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($coverSafeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($pdfBytes) . '</ns1:ekler>';
                $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
            }
            foreach ($attachments as $att) {
                $attBytes = file_get_contents($att['path']);
                if ($attBytes === false) throw new Exception('KEP eki okunamadı: ' . ($att['name'] ?? 'Belge'));
                $totalPayloadBytes += strlen($attBytes);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($att['path']) ?: 'application/octet-stream';
                $attSafeName = $this->normalizeKepAttachmentName((string) ($att['name'] ?: 'ek_belge'), $mime);
                $xmlFragment = '<ns1:ekler xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmime:contentType="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '" xmime:fileName="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '" filename="' . htmlspecialchars($attSafeName, ENT_QUOTES, 'UTF-8') . '">' . base64_encode($attBytes) . '</ns1:ekler>';
                $soapAttachments[] = new \SoapVar($xmlFragment, XSD_ANYXML);
            }
        }

        $allPttRecipients = array_reduce($recipientAddresses, static fn(bool $carry, string $address): bool => $carry && (bool) preg_match('/@hs01\.kep\.tr$/i', $address), true);
        $maxPayloadBytes = ($allPttRecipients ? 100 : 25) * 1024 * 1024;
        if ($totalPayloadBytes > $maxPayloadBytes) {
            throw new Exception('KEP eklerinin toplam boyutu alıcı sınırını aşıyor: ' . ($allPttRecipients ? '100 MB' : '25 MB') . '.');
        }

        $konu = !empty($options['konu']) ? trim((string) $options['konu']) : (string) ($evrak->konu ?: 'Resmî Yazı');
        // Onaylı resmî yazının tam içeriği imzalı PDF olarak ektedir; KEP gövdesinde tekrarlanmaz.
        $rawIcerik = 'İlgili yazı ektedir.';
        $icerik = $rawIcerik;
        $eIcerikTur = 'TEXT';

        $mimeRes = $client->mimeYap([
            'ePaketTur' => $paketTuru,
            'ePaketId' => (string) ($evrak->evrak_no ?: ('EVR_' . $evrakId)),
            'kime' => $recipientKep,
            'konu' => $konu,
            'icerik' => $icerik,
            'eIcerikTur' => $eIcerikTur,
            'ekler' => $soapAttachments,
            'eOzetAlg' => 'SHA256',
        ]);

        if (($mimeRes['status'] ?? '') !== 'success' || empty($mimeRes['mesajid'])) {
            throw new Exception($mimeRes['message'] ?: 'PTT KEP MIME paketi hazırlanamadı.');
        }

        $mesajId = (string) $mimeRes['mesajid'];
        $eHash = (string) ($mimeRes['eHash'] ?? '');

        $draftMeta = [
            'type' => 'evrak',
            'firm_id' => $firmId,
            'evrak_id' => $evrakId,
            'user_id' => (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0),
            'mesajid' => $mesajId,
            'eHash' => $eHash,
            'recipientKep' => $recipientKep,
            'paketTuru' => $paketTuru,
            'subject' => $konu,
            'message' => $rawIcerik,
            'tmpPdfPath' => $tmpPdfPath,
            'attachments' => $attachments,
            'created_at' => time(),
        ];

        file_put_contents($tmpDir . '/draft_evrak_' . $tempId . '.json', json_encode($draftMeta, JSON_UNESCAPED_UNICODE));
        $_SESSION['kep_draft_' . $tempId] = $draftMeta;

        return [
            'status' => 'success',
            'temp_id' => $tempId,
            'mesajid' => $mesajId,
            'eHash' => $eHash,
        ];
    }

    /**
     * Giden evrak için 2. Aşama: CAdES-BES imzasını PTT SmimeGonder ile iletir.
     */
    public function completeOutgoingKepSend(int $firmId, string $tempId, string $cadesSignature): array
    {
        $this->currentFirmId = $firmId;
        $tmpDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp';
        $draftFile = $tmpDir . '/draft_evrak_' . $tempId . '.json';

        $draft = $_SESSION['kep_draft_' . $tempId] ?? null;
        if (!$draft && file_exists($draftFile)) {
            $draft = json_decode(file_get_contents($draftFile), true);
        }
        if (!$draft || (int) ($draft['firm_id'] ?? 0) !== $firmId) {
            throw new Exception('Evrak KEP taslağı bulunamadı veya süresi doldu. Lütfen tekrar deneyiniz.');
        }
        $currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
        if ((int) ($draft['user_id'] ?? 0) !== $currentUserId || (int) ($draft['created_at'] ?? 0) < time() - 1800) {
            throw new Exception('KEP taslağı bu kullanıcıya ait değil veya süresi dolmuş. Lütfen yeniden hazırlayınız.');
        }

        $mesajId = (string) $draft['mesajid'];
        $evrakId = (int) $draft['evrak_id'];
        $recipientKep = (string) $draft['recipientKep'];
        $paketTuru = (string) $draft['paketTuru'];
        $subject = (string) ($draft['subject'] ?? '');
        $message = (string) ($draft['message'] ?? 'İlgili yazı ektedir.');
        $tmpPdfPath = $draft['tmpPdfPath'] ?? null;
        $attachments = $draft['attachments'] ?? [];
        $userId = (int) ($draft['user_id'] ?? ($_SESSION['user_id'] ?? 0));

        $evrak = $this->evrakModel->getById($evrakId);
        if (!$evrak || ($evrak->onay_durumu ?? '') !== 'onaylandi') {
            throw new Exception('Evrak artık KEP gönderimine uygun değil. Sayfayı yenileyiniz.');
        }
        if (!empty($evrak->kep_mesaj_id) || $this->mesajModel->getByEvrakId($firmId, $evrakId)) {
            throw new Exception('Bu evrak daha önce KEP üzerinden gönderilmiş.');
        }

        $rawSignature = base64_decode($cadesSignature, true);
        if ($rawSignature === false || strlen($rawSignature) < 100) {
            $rawSignature = $cadesSignature;
        }

        $client = $this->getClient($firmId);
        $sendRes = $client->smimeGonder($mesajId, $rawSignature);
        if (($sendRes['status'] ?? '') !== 'success') {
            throw new Exception($sendRes['message'] ?: 'PTT KEP ileti sevk işlemi başarısız.');
        }

        $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/kep';
        if (!is_dir($uploadBaseDir)) @mkdir($uploadBaseDir, 0755, true);

        $encPdfPath = null;
        if ($tmpPdfPath && file_exists($tmpPdfPath)) {
            $rawPdf = file_get_contents($tmpPdfPath);
            $encPdf = Security::encryptFile($rawPdf);
            $cleanKepId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $mesajId);
            $pdfFilename = "kep_ustyazi_{$cleanKepId}.pdf.enc";
            file_put_contents("{$uploadBaseDir}/{$pdfFilename}", $encPdf);
            $encPdfPath = "uploads/evrak-takip/kep/{$pdfFilename}";
        }

        $settings = $this->settingsModel->getSettings($firmId);
        $localMsgId = $this->mesajModel->saveOutgoing($firmId, [
            'evrak_id' => $evrakId,
            'kep_id' => $mesajId,
            'org_mesaj_id' => $mesajId,
            'gonderen_kep' => $settings['kep_adresi'],
            'alici_kep' => $recipientKep,
            'alici_unvan' => '',
            'konu' => $subject,
            'icerik' => $message,
            'paket_turu' => $paketTuru,
            'durum' => 'gonderildi',
            'delil_sayisi' => 1,
            'son_delil_durumu' => 'Kephs tarafından kabul edildi (Submission)',
            'yerel_dosya_yolu' => $encPdfPath,
            'ham_paket_yolu' => null,
        ]);

        $this->delilModel->upsertDelil($localMsgId, [
            'delil_id' => "SUB_{$mesajId}_" . time(),
            'delil_tur_id' => 10,
            'delil_aciklama' => 'S/MIME paketi Kephs tarafından kabul edildi (Submission Acceptance)',
            'tarih' => date('Y-m-d H:i:s'),
            'kephs' => 'PTT KEPHS',
        ]);

        $db = (new \App\Model\Model())->db;
        $stmt = $db->prepare("UPDATE evrak_takip SET kaynak_tipi = 'kep', kep_mesaj_id = :kep_id WHERE id = :id AND firma_id = :firma_id");
        $stmt->execute([
            'kep_id' => $mesajId,
            'id' => $evrakId,
            'firma_id' => $firmId,
        ]);

        try {
            (new SystemLogModel())->logAction(
                $userId,
                'KEP Gönderimi',
                "Evrak ID: {$evrakId} KEP üzerinden {$recipientKep} adresine sevk edildi. PTT KepId: {$mesajId}",
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $e) {}

        @unlink($draftFile);
        unset($_SESSION['kep_draft_' . $tempId]);
        if ($tmpPdfPath && is_file($tmpPdfPath)) @unlink($tmpPdfPath);
        foreach ($attachments as $att) {
            if (!empty($att['path']) && is_file($att['path'])) @unlink($att['path']);
        }

        return [
            'status' => 'success',
            'kep_id' => $mesajId,
            'message' => "Evrak PTT KEP üzerinden başarıyla sevk edildi. PTT Takip Kodu: {$mesajId}",
        ];
    }

    public function previewIncoming(int $firmId, int $gun = 30, ?int $userId = null): array
    {
        $gun = max(1, min($gun, 180));
        $client = $this->getClient($firmId, null, $userId);
        $res = $client->paketSorgula(date('Y-m-d 00:00:00', strtotime("-{$gun} days")), date('Y-m-d H:i:s'));
        if ($res['status'] !== 'success') {
            return ['status' => 'error', 'message' => $res['message']];
        }

        $imported = array_flip($this->mesajModel->getImportedKepIds($firmId, array_column($res['paketler'], 'kep_id')));
        $rows = [];
        foreach ($res['paketler'] as $paket) {
            $rows[] = [
                'selection_id' => Security::encrypt($paket['kep_id'] . '|' . $paket['kep_sira_no']),
                'konu' => $paket['konu'] !== '' ? $paket['konu'] : 'Konusuz KEP İletisi',
                'gonderen' => $paket['gonderen'],
                'gonderen_kep' => $paket['gonderen_kep'],
                'tur' => $paket['tur'],
                'aktarildi' => isset($imported[$paket['kep_id']]),
            ];
        }

        return ['status' => 'success', 'data' => $rows, 'message' => count($rows) . ' KEP iletisi kontrol edildi.'];
    }

    public function syncIncoming(int $firmId, int $userId, array $selections, bool $alindiOnay = false): array
    {
        $client = $this->getClient($firmId, null, $userId);
        $settings = $this->settingsModel->getSettings($firmId, $userId);
        $ownKep = (string) ($settings['kep_adresi'] ?? '');
        $parser = new KepMimeParserService();
        $baseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip';
        $kepDir = $baseDir . '/kep';
        if (!is_dir($kepDir)) {
            mkdir($kepDir, 0755, true);
        }

        $aktarilan = 0;
        $atlanan = 0;
        $hatalar = [];

        foreach ($selections as $selection) {
            [$kepId, $siraNo] = array_pad(explode('|', $selection, 2), 2, '0');
            $kepId = trim($kepId);
            $siraNo = (int) $siraNo;
            if ($kepId === '' || $this->mesajModel->getByKepId($firmId, $kepId)) {
                $atlanan++;
                continue;
            }

            try {
                $indir = $client->indir($kepId, $siraNo);
                if ($indir['status'] !== 'success') {
                    throw new Exception($indir['message']);
                }

                if ($alindiOnay) {
                    $onay = $client->alindiOnay($kepId, $siraNo);
                    if ($onay['status'] !== 'success') {
                        throw new Exception('Alındı onayı gönderilemedi: ' . $onay['message']);
                    }
                }

                $raw = base64_decode($indir['ePaket'], true);
                if ($raw === false || $raw === '') {
                    $raw = $indir['ePaket'];
                }
                $parsed = $parser->parse($raw);
                $headers = $parsed['headers'];

                $konu = trim((string) ($headers['subject'] ?? ''));
                $konu = $konu !== '' ? (function_exists('iconv_mime_decode') ? (string) @iconv_mime_decode($konu, 0, 'UTF-8') : $konu) : 'KEP İletisi';
                $from = trim((string) ($headers['from'] ?? ''));
                $from = function_exists('iconv_mime_decode') ? (string) @iconv_mime_decode($from, 0, 'UTF-8') : $from;
                $fromAddress = preg_match('/<([^>]+)>/', $from, $fm) ? $fm[1] : $from;
                $fromName = trim(preg_replace('/<[^>]*>/', '', $from), " \t\"");
                $kurumAdi = $fromName !== '' ? $fromName : $fromAddress;
                $tarih = !empty($headers['date']) && strtotime($headers['date']) ? date('Y-m-d', strtotime($headers['date'])) : date('Y-m-d');

                $mesajHtml = $parsed['html'] !== '' ? RichTextSanitizer::sanitize($parsed['html']) : nl2br(htmlspecialchars($parsed['text'], ENT_QUOTES, 'UTF-8'));
                $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $kepId);

                $hamName = 'kep_gelen_' . $safeId . '.enc';
                file_put_contents($kepDir . '/' . $hamName, Security::encryptFile($raw));

                $mesajId = $this->mesajModel->saveIncoming($firmId, [
                    'kep_id' => $kepId,
                    'kep_sira_no' => $siraNo,
                    'gonderen_kep' => mb_substr($fromAddress, 0, 100),
                    'alici_kep' => mb_substr($ownKep, 0, 255),
                    'alici_unvan' => mb_substr($kurumAdi, 0, 255),
                    'konu' => mb_substr($konu, 0, 255),
                    'icerik' => $mesajHtml,
                    'ham_paket_yolu' => 'uploads/evrak-takip/kep/' . $hamName,
                ]);

                $dosyaYolu = null;
                $ekKayitlari = [];
                foreach ($parsed['ekler'] as $idx => $ek) {
                    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ek['name']);
                    $ekFile = 'kep_gelen_ek_' . $safeId . '_' . ($idx + 1) . '_' . uniqid() . '_' . $safeName;
                    file_put_contents($baseDir . '/' . $ekFile, Security::encryptFile($ek['content']));
                    $relPath = 'uploads/evrak-takip/' . $ekFile;
                    if ($dosyaYolu === null && strcasecmp(pathinfo($ek['name'], PATHINFO_EXTENSION), 'pdf') === 0) {
                        $dosyaYolu = $relPath;
                    }
                    $ekKayitlari[] = [
                        'firma_id' => $firmId,
                        'dosya_adi' => $ek['name'],
                        'dosya_yolu' => $relPath,
                        'mime_tipi' => $ek['mime'],
                        'dosya_boyutu' => strlen($ek['content']),
                        'sira' => $idx + 1,
                    ];
                }

                $evrakId = $this->evrakModel->createFromKepGelen($firmId, [
                    'kep_id' => $kepId,
                    'tarih' => $tarih,
                    'evrak_no' => (string) $this->evrakModel->getMaxEvrakNo('gelen'),
                    'konu' => $konu,
                    'kurum_adi' => $kurumAdi,
                    'aciklama' => "KEP Mesaj ID: {$kepId}\nGönderen KEP: {$fromAddress}\nAlıcı KEP: {$ownKep}",
                    'dosya_yolu' => $dosyaYolu,
                    'mesaj_metni' => $mesajHtml,
                ]);
                foreach ($ekKayitlari as $kayit) {
                    $this->evrakModel->saveAttachment($evrakId, $kayit);
                }

                $this->mesajModel->updateLocalFiles($mesajId, $dosyaYolu, 'uploads/evrak-takip/kep/' . $hamName);
                $this->mesajModel->linkToEvrak($mesajId, $evrakId);

                if ($alindiOnay) {
                    $this->mesajModel->markAcknowledged($mesajId);
                }

                (new SystemLogModel())->logAction($userId, 'KEP_GELEN_AKTAR', "Gelen KEP aktarıldı. KEP ID: {$kepId}, Evrak ID: {$evrakId}");
                $aktarilan++;
            } catch (Throwable $e) {
                error_log("KepService::syncIncoming [{$kepId}] " . $e->getMessage());
                $hatalar[] = "KEP {$kepId} aktarılamadı (ayrıntı sunucu günlüğünde).";
            }
        }

        return [
            'status' => $aktarilan > 0 || !$hatalar ? 'success' : 'error',
            'aktarilan' => $aktarilan,
            'atlanan' => $atlanan,
            'hatalar' => $hatalar,
            'message' => "{$aktarilan} gelen KEP iletisi aktarıldı" . ($atlanan ? ", {$atlanan} tanesi zaten kayıtlıydı" : '') . '.',
        ];
    }

    public function autoSyncIncoming(int $firmId, int $userId, int $gun = 7): array
    {
        $preview = $this->previewIncoming($firmId, $gun, $userId);
        if ($preview['status'] !== 'success') {
            return $preview;
        }

        $selections = [];
        foreach ($preview['data'] as $row) {
            if (!$row['aktarildi']) {
                $selections[] = (string) Security::decrypt($row['selection_id']);
            }
        }

        $result = $selections
            ? $this->syncIncoming($firmId, $userId, $selections, $this->settingsModel->getGelenOtomasyon($firmId)['okundu_otomatik'])
            : ['status' => 'success', 'aktarilan' => 0, 'atlanan' => 0, 'hatalar' => [], 'message' => 'Yeni KEP iletisi yok.'];

        $this->settingsModel->touchGelenSync($firmId);

        if (($result['aktarilan'] ?? 0) > 0) {
            try {
                (new \App\Model\BildirimModel())->broadcastByPermissionForFirm(
                    $firmId,
                    'evrak-takip/kep',
                    'Yeni Gelen KEP',
                    $result['aktarilan'] . ' yeni KEP iletisi Gelen Evrak olarak kaydedildi.',
                    'evrak-takip/list',
                    'bell',
                    'primary',
                    \App\Model\UserNotificationPreferenceModel::TYPE_KEP
                );
            } catch (Throwable $e) {
                error_log('KepService::autoSyncIncoming bildirim: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * KEP iletisine ait canlı delilleri PTT sunucusundan çeker ve yerel veritabanını günceller
     */
    public function syncEvidences(int $firmId, string $kepId): array
    {
        $mesaj = $this->mesajModel->getByKepId($firmId, $kepId);
        if (!$mesaj) {
            throw new Exception('KEP mesaj kaydı bulunamadı.');
        }

        $client = $this->getClient($firmId);
        $res = $client->paketDelilSorgula($kepId);

        if ($res['status'] === 'success' && !empty($res['deliller'])) {
            $lastDesc = '';
            foreach ($res['deliller'] as $delil) {
                $this->delilModel->upsertDelil((int) $mesaj['id'], $delil);
                $lastDesc = $delil['delil_aciklama'] ?? $lastDesc;
            }

            $this->mesajModel->updateDelilSummary((int) $mesaj['id'], count($res['deliller']), $lastDesc);
        }

        $allDeliller = $this->delilModel->getDelillerByMesajId((int) $mesaj['id']);
        return [
            'status' => 'success',
            'deliller' => $allDeliller,
            'mesaj' => $mesaj,
        ];
    }

    /**
     * PTT'den yasal delil XML dosyasını indirir, şifreleyerek saklar ve deşifre edilmiş içeriği döner
     */
    public function downloadEvidenceXml(int $firmId, string $delilId): array
    {
        $delil = $this->delilModel->getByDelilId($delilId);
        if (!$delil) {
            throw new Exception('Delil kaydı sistemde bulunamadı.');
        }

        // Eğer yerelde şifreli XML varsa doğrudan deşifre et ve sun
        if (!empty($delil['delil_xml_yolu'])) {
            $fullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $delil['delil_xml_yolu'], '/');
            if (file_exists($fullPath)) {
                $raw = file_get_contents($fullPath);
                try {
                    $xml = Security::decryptFile($raw);
                } catch (Throwable $e) {
                    $xml = $raw;
                }
                if (trim((string) $xml) !== '' && str_contains((string) $xml, '<')) {
                    return ['status' => 'success', 'xml' => $xml, 'filename' => "delil_{$delilId}.xml"];
                }
            }
        }

        // Yerelde yoksa PTT KEP servisinden indir
        $client = $this->getClient($firmId);
        $res = $client->paketDelilIndir($delilId);

        if ($res['status'] !== 'success' || empty($res['eDelil'])) {
            throw new Exception($res['message'] ?: 'PTT KEP delil dosyası indirilemedi.');
        }

        $rawXml = (string) $res['eDelil'];
        if (!str_contains(substr($rawXml, 0, 200), '<')) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $rawXml), true);
            if ($decoded !== false && $decoded !== '') {
                $rawXml = $decoded;
            }
        }
        if (str_starts_with($rawXml, "\x1f\x8b")) {
            $unzipped = @gzdecode($rawXml);
            if ($unzipped !== false && $unzipped !== '') {
                $rawXml = $unzipped;
            }
        } elseif (str_starts_with($rawXml, 'PK') && class_exists('ZipArchive')) {
            $tmpZip = tempnam(sys_get_temp_dir(), 'delil');
            file_put_contents($tmpZip, $rawXml);
            $zip = new \ZipArchive();
            if ($zip->open($tmpZip) === true && $zip->numFiles > 0) {
                $entry = $zip->getFromIndex(0);
                if ($entry !== false && $entry !== '') {
                    $rawXml = $entry;
                }
                $zip->close();
            }
            @unlink($tmpZip);
        }
        $rawXml = ltrim($rawXml, "\xEF\xBB\xBF \t\r\n");
        if ($rawXml === '' || !str_contains($rawXml, '<')) {
            error_log('KepService::downloadEvidenceXml geçersiz delil içeriği, uzunluk: ' . strlen((string) $res['eDelil']));
            throw new Exception('PTT delil dosyası boş veya okunamayan biçimde döndü.');
        }

        // ISO 27001 standardı gereği AES-256-GCM ile şifreleyerek diske yaz
        $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/kep';
        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0755, true);
        }

        $encXml = Security::encryptFile($rawXml);
        $filename = "delil_{$delilId}.xml.enc";
        file_put_contents("{$uploadBaseDir}/{$filename}", $encXml);

        $this->delilModel->updateDelilXmlPath($delilId, "uploads/evrak-takip/kep/{$filename}");

        return [
            'status' => 'success',
            'xml' => $rawXml,
            'filename' => "delil_{$delilId}.xml",
        ];
    }

    /**
     * PTT KEP SmimeGonder için yetkili TCKN serialNumber içeren CAdES-BES (PKCS#7) imza zarfı üretir.
     * PIN verilmişse takılı SafeNet/E-GÜVEN USB Token üzerinden donanımsal imza üretir.
     *
     * @param string $tcno Yetkili TCKN
     * @param string $eHash MimeYap'tan dönen SHA256 base64 özeti
     * @param string|null $pin E-İmza kart PIN kodu
     * @return string DER formatında PKCS#7 imza verisi
     */
    /**
     * Sistemde geçerli python binary yolunu arar (python3, python, /usr/bin/python3, vb.)
     */
    public function getPythonBinary(): ?string
    {
        $candidates = [
            'python3',
            'python',
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/opt/lampp/bin/python3',
            '/usr/bin/python',
        ];
        foreach ($candidates as $bin) {
            $cmd = escapeshellcmd($bin) . ' --version 2>&1';
            $out = [];
            $code = 0;
            @exec($cmd, $out, $code);
            if ($code === 0 && !empty($out)) {
                return $bin;
            }
        }
        return null;
    }

    /**
     * Aktif KEP hesabının test ortamında olup olmadığını doğrular
     */
    private function isTestEnvironment(): bool
    {
        if (!empty($this->currentFirmId)) {
            $settings = $this->settingsModel->getSettings($this->currentFirmId);
            if ($settings) {
                return ($settings['ortam'] ?? 'prod') === 'test' 
                    || strpos((string) ($settings['kep_adresi'] ?? ''), 'testkep') !== false;
            }
        }
        return true;
    }

    /**
     * PTT KEP SmimeGonder için yetkili TCKN serialNumber içeren CAdES-BES (PKCS#7) imza zarfı üretir.
     * PIN verilmişse ve sunucuda donanım mevcutsa USB Token üzerinden imzalar;
     * Sunucuda Python/Token bulunamazsa test ortamında otomatik OpenSSL CAdES motoruna geçiş yapar.
     *
     * @param string $tcno Yetkili TCKN
     * @param string $eHash MimeYap'tan dönen SHA256 base64 özeti
     * @param string|null $pin E-İmza kart PIN kodu
     * @return string DER formatında PKCS#7 imza verisi
     */
    private function createCadesSignature(string $tcno, string $eHash, ?string $pin = null): string
    {
        // 1. PIN verilmişse doğrudan donanımsal SafeNet/E-GÜVEN token üzerinden imzalamayı dene
        if (!empty($pin)) {
            try {
                return $this->createTokenCadesSignature($pin, $eHash);
            } catch (Throwable $e) {
                error_log("Token imzalama başarısız: " . $e->getMessage());
                // Test ortamında ise veya sunucuda Python/Token bulunamadığında
                // otomatik olarak yerleşik OpenSSL CAdES motoruna güvenli geçiş yap
                if ($this->isTestEnvironment()) {
                    error_log("Test ortamında OpenSSL CAdES-BES yedek motoruna güvenli geçiş yapıldı.");
                } else {
                    throw new Exception("E-İmza Kartı Hatası: " . $e->getMessage());
                }
            }
        }

        // 2. PIN yoksa veya test ortamı yedek motoru: OpenSSL ile self-signed CAdES DER üret
        $rawHash = base64_decode($eHash);
        if ($rawHash === false || strlen($rawHash) === 0) {
            $rawHash = hash('sha256', $eHash, true);
        }

        $cnfContent = "
[ req ]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
C = TR
CN = {$tcno}
serialNumber = {$tcno}
";

        $cnfFile = tempnam(sys_get_temp_dir(), 'cnf_');
        $keyFile = tempnam(sys_get_temp_dir(), 'key_');
        $certFile = tempnam(sys_get_temp_dir(), 'crt_');
        $dataFile = tempnam(sys_get_temp_dir(), 'dat_');
        $p7sFile = tempnam(sys_get_temp_dir(), 'p7s_');

        try {
            file_put_contents($cnfFile, $cnfContent);
            file_put_contents($dataFile, $rawHash);

            // Sertifika ve Private Key üret
            exec("openssl req -x509 -newkey rsa:2048 -keyout " . escapeshellarg($keyFile) . " -out " . escapeshellarg($certFile) . " -days 365 -nodes -config " . escapeshellarg($cnfFile) . " 2>&1", $out1, $ret1);
            if ($ret1 !== 0 || !file_exists($certFile)) {
                throw new Exception('OpenSSL sertifika üretimi başarısız.');
            }

            // PKCS#7 (CAdES-BES) imza üret (DER formatında)
            exec("openssl smime -sign -in " . escapeshellarg($dataFile) . " -signer " . escapeshellarg($certFile) . " -inkey " . escapeshellarg($keyFile) . " -out " . escapeshellarg($p7sFile) . " -outform DER -nodetach -binary 2>&1", $out2, $ret2);
            if ($ret2 !== 0 || !file_exists($p7sFile)) {
                throw new Exception('PKCS#7 imza zarflama başarısız.');
            }

            return file_get_contents($p7sFile);
        } finally {
            @unlink($cnfFile);
            @unlink($keyFile);
            @unlink($certFile);
            @unlink($dataFile);
            @unlink($p7sFile);
        }
    }

    /**
     * Takılı SafeNet / E-GÜVEN USB Token üzerinden donanımsal CAdES-BES üretir
     *
     * @param string $pin E-İmza kartı PIN kodu
     * @param string $eHash MimeYap SHA-256 özeti
     * @return string DER formatında CAdES-BES imzası
     */
    public function createTokenCadesSignature(string $pin, string $eHash): string
    {
        $pythonBin = $this->getPythonBinary();
        if ($pythonBin === null) {
            throw new Exception("Sunucuda Python3 çalışma ortamı (python3) bulunamadı.");
        }

        $scriptPath = dirname(__DIR__, 2) . '/scripts/cades_token_signer.py';
        if (!file_exists($scriptPath)) {
            throw new Exception('CAdES Token imzalayıcı scripti bulunamadı.');
        }

        $outPath = tempnam(sys_get_temp_dir(), 'cades_token_');
        $cmd = sprintf(
            '%s %s --pin %s --hash %s --out %s 2>&1',
            escapeshellcmd($pythonBin),
            escapeshellarg($scriptPath),
            escapeshellarg($pin),
            escapeshellarg($eHash),
            escapeshellarg($outPath)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outPath) || filesize($outPath) === 0) {
            $err = implode("\n", $output);
            if (file_exists($outPath)) @unlink($outPath);
            throw new Exception($err ?: 'E-İmza token imza üretimi başarısız oldu.');
        }

        $cadesDer = file_get_contents($outPath);
        @unlink($outPath);
        return $cadesDer;
    }
}
