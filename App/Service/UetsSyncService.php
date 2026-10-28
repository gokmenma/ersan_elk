<?php
namespace App\Service;

use App\Model\UetsSettingsModel;
use App\Model\UetsMesajModel;
use App\Model\UetsEvidenceModel;
use App\Model\UetsRehberModel;
use App\Model\EvrakTakipModel;
use App\Model\BildirimModel;
use App\Model\SystemLogModel;
use App\Service\UetsClientService;
use App\Service\EypPackageService;
use App\Helper\Security;
use Exception;

class UetsSyncService
{
    private const LIST_INTERVAL = 7200;
    private const PAGE_SIZE = 250;
    private const MAX_PAGES = 2;

    private UetsSettingsModel $settingsModel;
    private UetsMesajModel $mesajModel;
    private UetsEvidenceModel $evidenceModel;
    private UetsRehberModel $rehberModel;
    private EvrakTakipModel $evrakModel;
    private EypPackageService $eypService;

    public function __construct()
    {
        $this->settingsModel = new UetsSettingsModel();
        $this->mesajModel = new UetsMesajModel();
        $this->evidenceModel = new UetsEvidenceModel();
        $this->rehberModel = new UetsRehberModel();
        $this->evrakModel = new EvrakTakipModel();
        $this->eypService = new EypPackageService();
    }

    /**
     * Gelen tebligatları UETS servisinden senkronize eder
     */
    public function previewIncoming(int $firmId): array
    {
        $messages = $this->fetchIncomingMessages($firmId);
        $rows = [];

        foreach ($messages['messages'] as $msg) {
            $uetsMsgId = (string) ($msg['id'] ?? '');
            if ($uetsMsgId === '') {
                continue;
            }
            $tarih = !empty($msg['inserttime']) ? date('Y-m-d', (int) $msg['inserttime']) : date('Y-m-d');
            $konu = mb_substr(trim((string) ($msg['subject'] ?? 'UETS Elektronik Tebligat')), 0, 250);
            $kurumAdi = mb_substr(trim((string) ($msg['sender_name'] ?? $msg['sender'] ?? 'UETS Gönderici')), 0, 250);
            $duplicate = $this->evrakModel->findPossibleUetsDuplicate($firmId, $uetsMsgId, $tarih, $konu, $kurumAdi);
            $alreadyImported = $duplicate && (string) ($duplicate->uets_mesaj_id ?? '') === $uetsMsgId;

            $rows[] = [
                'selection_id' => Security::encrypt($uetsMsgId),
                'tarih' => date('d.m.Y', strtotime($tarih)),
                'konu' => $konu,
                'gonderen' => $kurumAdi,
                'gonderen_uets' => (string) ($msg['sender'] ?? ''),
                'durum' => (string) ($msg['status'] ?? ''),
                'boyut' => (int) ($msg['size'] ?? 0),
                'aktarildi' => $alreadyImported,
                'olasi_mukerrer' => $duplicate !== null && !$alreadyImported,
                'eslesen_evrak' => $duplicate ? [
                    'id' => Security::encrypt((int) $duplicate->id),
                    'evrak_no' => (string) ($duplicate->evrak_no ?? ''),
                    'konu' => (string) ($duplicate->konu ?? ''),
                ] : null,
            ];
        }

        return [
            'status' => 'success',
            'data' => $rows,
            'hatalar' => $messages['errors'],
            'onbellek' => $messages['cached'],
            'sorgu_zamani' => $messages['sorgu_zamani'],
            'sonraki_sorgu' => $messages['sonraki_sorgu'],
            'message' => count($rows) . ' UETS evrakı kontrol edildi.'
                . ($messages['cached'] && $messages['sorgu_zamani'] ? ' Son UETS sorgusu: ' . $messages['sorgu_zamani'] . ' (sorgu hakkı gereği ' . $messages['sonraki_sorgu'] . ' tarihine kadar kayıtlı liste gösteriliyor).' : ''),
        ];
    }

    public function syncIncoming(int $firmId, ?array $selectedMessageIds = null): array
    {
        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['api_key_decrypted'])) {
            return [
                'status' => 'error',
                'message' => 'Firma için UETS API anahtarı veya ayarları yapılandırılmamış.',
            ];
        }

        $client = new UetsClientService($settings['api_key_decrypted'], $settings['api_environment'] ?? 'test');

        $yeniGelenSayisi = 0;
        $guncellenenSayisi = 0;
        $hataListesi = [];

        $incomingResult = $this->fetchIncomingMessages($firmId, $client);
        $allIncoming = $incomingResult['messages'];
        $hataListesi = $incomingResult['errors'];
        if ($selectedMessageIds !== null) {
            $selectedLookup = array_fill_keys(array_values(array_unique($selectedMessageIds)), true);
            $allIncoming = array_values(array_filter($allIncoming, static fn(array $msg): bool => isset($selectedLookup[(string) ($msg['id'] ?? '')])));
        } else {
            $startDate = trim((string) ($settings['otomatik_aktarim_baslangic'] ?? ''));
            $startTs = $startDate !== '' ? strtotime($startDate . ' 00:00:00') : false;
            if ($startTs === false) {
                return [
                    'status' => 'error',
                    'message' => 'UETS otomatik aktarım başlangıç tarihi tanımlı değil. Önce sql/2026_10_28_uets_otomatik_aktarim_baslangic.sql çalıştırılmalıdır.',
                ];
            }
            $allIncoming = array_values(array_filter($allIncoming, static fn(array $msg): bool => in_array((string) ($msg['status'] ?? ''), ['delivered', 'readtimeout'], true)
                && (int) ($msg['inserttime'] ?? 0) >= $startTs));
        }

        if (!$incomingResult['cached'] && $incomingResult['errors'] === []) {
            try {
                if (!empty($settings['uets_adresi'])) {
                    $balanceData = $client->getBalance($settings['uets_adresi']);
                    if (isset($balanceData['balance'])) {
                        $this->settingsModel->updateBalance($firmId, (float) $balanceData['balance']);
                    }
                }
            } catch (\Throwable $e) {
                error_log("UetsSyncService Balance Update Error: " . $e->getMessage());
            }
        }

        $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip';
        $uetsStorageDir = $uploadBaseDir . '/uets';
        if (!is_dir($uetsStorageDir)) {
            mkdir($uetsStorageDir, 0755, true);
        }

        foreach ($allIncoming as $msg) {
            $uetsMsgId = (string) ($msg['id'] ?? '');
            if ($uetsMsgId === '') {
                continue;
            }

            try {
                $existing = $this->mesajModel->getByUetsId($firmId, $uetsMsgId);
                $isNew = ($existing === null);

                // Mesajı uets_mesajlar tablosuna kaydet / güncelle
                $localMesajId = $this->mesajModel->upsertIncoming($firmId, $msg);

                // Göndereni rehbere ekle
                if (!empty($msg['sender'])) {
                    $this->rehberModel->saveOrUpdate($firmId, [
                        'address' => $msg['sender'],
                        'name' => $msg['sender_name'] ?? '',
                    ]);
                }

                // Eğer dosya henüz indirilmediyse EYP indir ve evrak oluştur
                if ($isNew || empty($existing['yerel_dosya_yolu'])) {
                    $downloadResult = $client->downloadMessage($uetsMsgId);
                    $rawEypContent = $downloadResult['content'] ?? '';

                    if (!empty($rawEypContent)) {
                        // 1. Ham EYP dosyasını ISO 27001 standardı gereği AES-256-GCM / Security::encryptFile ile şifreleyerek diske yaz
                        $encryptedEyp = Security::encryptFile($rawEypContent);
                        $eypFilename = 'eyp_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uetsMsgId) . '.enc';
                        $eypFullPath = $uetsStorageDir . '/' . $eypFilename;
                        file_put_contents($eypFullPath, $encryptedEyp);

                        // 2. Geçici dizinde paketi aç ve üst yazı PDF'ini ayıkla
                        $tmpExtractDir = sys_get_temp_dir() . '/uets_ext_' . uniqid();
                        $tmpEypPath = sys_get_temp_dir() . '/tmp_' . uniqid() . '.eyp';
                        file_put_contents($tmpEypPath, $rawEypContent);

                        $extracted = $this->eypService->extractPackage($tmpEypPath, $tmpExtractDir);
                        @unlink($tmpEypPath);

                        $pdfPathToStore = null;
                        if (!empty($extracted['ustyazi_pdf']) && file_exists($extracted['ustyazi_pdf'])) {
                            // Üst yazı PDF'ini şifreleyerek kalıcı dizine kaydet
                            $pdfBinary = file_get_contents($extracted['ustyazi_pdf']);
                            $encPdf = Security::encryptFile($pdfBinary);
                            $pdfFilename = 'uets_ustyazi_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uetsMsgId) . '.pdf';
                            $pdfFullPath = $uploadBaseDir . '/' . $pdfFilename;
                            file_put_contents($pdfFullPath, $encPdf);
                            $pdfPathToStore = 'uploads/evrak-takip/' . $pdfFilename;
                        }

                        // uets_mesajlar dosya yollarını güncelle
                        $this->mesajModel->updateLocalFiles($localMesajId, $pdfPathToStore, 'uploads/evrak-takip/uets/' . $eypFilename);

                        // 3. evrak_takip tablosuna Gelen Evrak olarak kaydet
                        $evrakNo = $this->evrakModel->getMaxEvrakNo('gelen');
                        $tarih = !empty($msg['inserttime']) ? date('Y-m-d', (int) $msg['inserttime']) : date('Y-m-d');
                        $konu = mb_substr(trim((string) ($msg['subject'] ?? 'UETS Elektronik Tebligat')), 0, 250);
                        $kurumAdi = mb_substr(trim((string) ($msg['sender_name'] ?? $msg['sender'] ?? 'UETS Gönderici')), 0, 250);

                        $newEvrakId = $this->evrakModel->createFromUets($firmId, [
                            'uets_mesaj_id' => $uetsMsgId,
                            'tarih' => $tarih,
                            'evrak_no' => $evrakNo,
                            'konu' => $konu,
                            'kurum_adi' => $kurumAdi,
                            'aciklama' => "UETS Tebligat No: {$uetsMsgId}\nGönderen UETS: " . ($msg['sender'] ?? '') . "\nAlıcı: " . ($msg['recipient'] ?? ''),
                            'dosya_yolu' => $pdfPathToStore,
                        ]);

                        // uets_mesajlar ile evrak_takip'i birbirine bağla
                        $this->mesajModel->linkToEvrak($localMesajId, $newEvrakId);

                        (new \App\Model\EvrakSevkModel())->recordHistory(
                            $firmId,
                            $newEvrakId,
                            'uets_alindi',
                            (int) ($_SESSION['user_id'] ?? 0),
                            null,
                            "UETS tebligat No: {$uetsMsgId} sisteme aktarıldı."
                        );

                        // Ekleri evrak_takip_ekleri tablosuna ekle
                        if (!empty($extracted['ekler'])) {
                            foreach ($extracted['ekler'] as $ekIdx => $ek) {
                                if (file_exists($ek['path'])) {
                                    $ekBinary = file_get_contents($ek['path']);
                                    $encEk = Security::encryptFile($ekBinary);
                                    $ekFilename = 'uets_ek_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $ek['name']);
                                    $ekFullPath = $uploadBaseDir . '/' . $ekFilename;
                                    file_put_contents($ekFullPath, $encEk);

                                    $this->evrakModel->saveAttachment($newEvrakId, [
                                        'firma_id' => $firmId,
                                        'dosya_adi' => $ek['name'],
                                        'dosya_yolu' => 'uploads/evrak-takip/' . $ekFilename,
                                        'mime_tipi' => 'application/octet-stream',
                                        'dosya_boyutu' => (int) $ek['size'],
                                        'sira' => $ekIdx + 1,
                                    ]);
                                }
                            }
                        }

                        // Geçici ayıklama dizinini temizle
                        self::deleteDir($tmpExtractDir);
                    }

                    $yeniGelenSayisi++;
                } else {
                    $guncellenenSayisi++;
                }

                if ($isNew) {
                    try {
                        $evidenceList = $client->getEvidenceList($uetsMsgId);
                        if (is_array($evidenceList)) {
                            foreach ($evidenceList as $evid) {
                                if (is_array($evid) && !empty($evid['id'])) {
                                    $this->evidenceModel->upsertEvidence($localMesajId, $evid);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log("UETS Delil Çekme Hatası [{$uetsMsgId}]: " . $e->getMessage());
                    }
                }

                // 5. Yasal Süre Aşımı Bildirimi (readtimeout durumu)
                if (($msg['status'] ?? '') === 'readtimeout') {
                    try {
                        (new BildirimModel())->sendToFirmAdmins(
                            $firmId,
                            "UETS Yasal Süre Uyarısı",
                            "UETS gelen tebligatın (ID: {$uetsMsgId}, Konu: {$msg['subject']}) 5 günlük yasal okunma süresi dolmuştur. Tebligat kanunen yapılmış sayılmıştır.",
                            "evrak-takip/list"
                        );
                    } catch (\Throwable $e) {
                        // ignore notification failure
                    }
                }

            } catch (UetsApiException $e) {
                error_log("UetsSyncService Mesaj İşleme Hatası [{$uetsMsgId}]: " . $e->getMessage());
                $hataListesi[] = "Mesaj {$uetsMsgId}: " . $e->getMessage();
                if ($e->isRateLimited()) {
                    break;
                }
            } catch (\Throwable $e) {
                error_log("UetsSyncService Mesaj İşleme Hatası [{$uetsMsgId}]: " . $e->getMessage());
                $hataListesi[] = "Mesaj {$uetsMsgId}: " . $e->getMessage();
            }
        }

        // Senkronizasyon zamanını güncelle
        $this->settingsModel->updateSyncTimes($firmId, date('Y-m-d H:i:s'), null);

        // Sistem logu oluştur
        try {
            (new SystemLogModel())->logActionForFirm(
                $firmId,
                (int) ($_SESSION['user_id'] ?? 0),
                'UETS_SENKRONIZASYON',
                "UETS Senkronizasyon tamamlandı. Yeni: {$yeniGelenSayisi}, Güncellenen: {$guncellenenSayisi}."
            );
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'status' => 'success',
            'yeni_gelen' => $yeniGelenSayisi,
            'guncellenen' => $guncellenenSayisi,
            'hatalar' => $hataListesi,
            'message' => "Senkronizasyon tamamlandı: {$yeniGelenSayisi} yeni tebligat alındı, {$guncellenenSayisi} kayıt güncellendi.",
        ];
    }

    private function fetchIncomingMessages(int $firmId, ?UetsClientService $client = null): array
    {
        if ($client === null) {
            $settings = $this->settingsModel->getSettings($firmId);
            if (!$settings || empty($settings['api_key_decrypted'])) {
                throw new Exception('Firma için UETS API anahtarı veya ayarları yapılandırılmamış.');
            }
            $client = new UetsClientService($settings['api_key_decrypted'], $settings['api_environment'] ?? 'test');
        }

        $cache = $this->settingsModel->getListCache($firmId);
        $now = time();
        $cacheTime = $cache['zaman'] ? (int) strtotime($cache['zaman']) : 0;

        if ($cache['messages'] !== null && $cacheTime > 0 && ($now - $cacheTime) < self::LIST_INTERVAL) {
            return $this->listResult($cache['messages'], [], true, $cacheTime);
        }

        $blockedUntil = $cache['engel_bitis'] ? (int) strtotime($cache['engel_bitis']) : 0;
        if ($blockedUntil > $now) {
            return $this->listResult(
                $cache['messages'] ?? [],
                ['UETS sorgu hakkı dolu. Yeni sorgu en erken ' . date('d.m.Y H:i', $blockedUntil) . ' tarihinde yapılabilir.'],
                true,
                $cacheTime
            );
        }

        $messagesById = [];
        $errors = [];
        $completed = false;
        try {
            $start = 0;
            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $response = $client->getMessages([
                    'type' => 'incoming', 'start' => $start, 'count' => self::PAGE_SIZE, 'sort' => '-inserttime',
                ]);
                $pageMessages = $response['messages'] ?? [];
                foreach ($pageMessages as $message) {
                    if (is_array($message) && !empty($message['id'])) {
                        $messagesById[(string) $message['id']] = $message;
                    }
                }
                $start += count($pageMessages);
                if ($pageMessages === [] || $start >= (int) ($response['total_count'] ?? $start)) {
                    $completed = true;
                    break;
                }
            }
            $completed = $completed || $page >= self::MAX_PAGES;
        } catch (UetsApiException $e) {
            $errors[] = $e->getMessage();
            if ($e->isRateLimited()) {
                $wait = max($e->getRetryAfter(), self::LIST_INTERVAL);
                $this->settingsModel->setListBlockedUntil($firmId, date('Y-m-d H:i:s', $now + $wait));
            }
        } catch (\Throwable $e) {
            error_log('UetsSyncService Liste Hatası: ' . $e->getMessage());
            $errors[] = 'UETS tebligat listesi alınamadı: ' . $e->getMessage();
        }

        $messages = array_values($messagesById);
        if ($completed && $errors === []) {
            $this->settingsModel->saveListCache($firmId, $messages);
            $this->logListQuery($firmId, count($messages));
            return $this->listResult($messages, [], false, $now);
        }

        if ($messages === [] && $cache['messages'] !== null) {
            return $this->listResult($cache['messages'], $errors, true, $cacheTime);
        }

        return $this->listResult($messages, $errors, false, $now);
    }

    private function listResult(array $messages, array $errors, bool $cached, int $queryTime): array
    {
        return [
            'messages' => array_values($messages),
            'errors' => $errors,
            'cached' => $cached,
            'sorgu_zamani' => $queryTime > 0 ? date('d.m.Y H:i', $queryTime) : null,
            'sonraki_sorgu' => $queryTime > 0 ? date('d.m.Y H:i', $queryTime + self::LIST_INTERVAL) : null,
        ];
    }

    private function logListQuery(int $firmId, int $count): void
    {
        try {
            (new SystemLogModel())->logActionForFirm(
                $firmId,
                (int) ($_SESSION['user_id'] ?? 0),
                'UETS_LISTE_SORGUSU',
                "UETS gelen tebligat listesi sorgulandı. Kayıt sayısı: {$count}."
            );
        } catch (\Throwable $e) {
            error_log('UetsSyncService log hatası: ' . $e->getMessage());
        }
    }

    /**
     * Giden evrakı UETS üzerinden gönderir
     */
    public function sendOutgoing(int $firmId, int $evrakId, array $options = []): array
    {
        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings || empty($settings['api_key_decrypted'])) {
            throw new Exception('Firma için UETS API anahtarı veya ayarları yapılandırılmamış.');
        }

        $evrak = $this->evrakModel->getById($evrakId);
        if (!$evrak || $evrak->evrak_tipi !== 'giden') {
            throw new Exception('Giden evrak bulunamadı.');
        }

        $recipientUets = trim((string) ($options['recipient_uets'] ?? ''));
        if ($recipientUets === '') {
            throw new Exception('Alıcı UETS adresi zorunludur.');
        }

        $client = new UetsClientService($settings['api_key_decrypted'], $settings['api_environment'] ?? 'test');

        // 1. Alıcı adresini UETS sisteminde doğrula
        $searchResult = $client->searchRecipient(['address' => $recipientUets]);
        if (empty($searchResult)) {
            throw new Exception('Belirtilen UETS adresi sistemde bulunamadı veya geçerli değil: ' . $recipientUets);
        }

        // 2. Üst yazı PDF yolunu belirle
        $pdfPath = null;
        if (!empty($evrak->dosya_yolu)) {
            $candidatePath = dirname(__DIR__, 2) . '/' . ltrim($evrak->dosya_yolu, '/');
            if (file_exists($candidatePath)) {
                $pdfPath = $candidatePath;
            }
        }

        // Eğer mevcut şifreli PDF varsa geçici olarak deşifre edip oku
        $tmpPdfPath = null;
        if ($pdfPath) {
            $rawBytes = file_get_contents($pdfPath);
            try {
                $decryptedBytes = Security::decryptFile($rawBytes);
            } catch (\Throwable $e) {
                $decryptedBytes = $rawBytes; // Şifresiz ise doğrudan al
            }
            $tmpPdfPath = sys_get_temp_dir() . '/tmp_out_' . uniqid() . '.pdf';
            file_put_contents($tmpPdfPath, $decryptedBytes);
        } else {
            // PDF yoksa EvrakPdfService ile oluştur
            $pdfService = new EvrakPdfService();
            $tmpPdfPath = $pdfService->generateTempPdf($evrakId);
        }

        // 3. Ekleri topla
        $attachments = [];
        $evrakEkleri = $this->evrakModel->getAttachments($evrakId);
        foreach ($evrakEkleri as $ek) {
            $ekPath = dirname(__DIR__, 2) . '/' . ltrim($ek->dosya_yolu, '/');
            if (file_exists($ekPath)) {
                $ekRaw = file_get_contents($ekPath);
                try {
                    $ekDec = Security::decryptFile($ekRaw);
                } catch (\Throwable $e) {
                    $ekDec = $ekRaw;
                }
                $tmpEkPath = sys_get_temp_dir() . '/tmp_ek_' . uniqid() . '_' . $ek->dosya_adi;
                file_put_contents($tmpEkPath, $ekDec);
                $attachments[] = [
                    'path' => $tmpEkPath,
                    'name' => $ek->dosya_adi,
                ];
            }
        }

        // 4. EYP Paketi oluştur
        $meta = [
            'evrak_no' => $evrak->evrak_no,
            'konu' => $evrak->konu,
            'gonderen' => $settings['uets_adresi'],
            'alici' => $recipientUets,
        ];
        $eypResult = $this->eypService->createPackage($tmpPdfPath, $attachments, null, $meta);

        // 5. UETS API: 1. Adım - POST /messages (Taslak oluşturma ve EYP yükleme)
        $draftResult = $client->uploadDraftMessage($eypResult['path'], $eypResult['sha1']);
        $uetsMessageId = $draftResult['id'] ?? '';
        if (!$uetsMessageId) {
            throw new Exception('UETS taslak tebligat kimliği alınamadı.');
        }

        // 6. UETS API: 2. Adım - PUT /messages/{id} (Tebligatı gönderme)
        $barcode = $options['barcode'] ?? ('BAR_' . date('Ymd') . '_' . $evrakId);
        $payload = [
            'recipient' => $recipientUets,
            'reference_id' => (string) ($evrak->evrak_no ?: ('REF_' . $evrakId)),
            'subject' => mb_substr((string) $evrak->konu, 0, 100),
            'attributes' => [
                'barcode' => $barcode,
                'sender_ref' => (string) $evrak->evrak_no,
                'recipient_ref' => (string) ($options['recipient_ref'] ?? ''),
            ],
        ];

        $sendResult = $client->sendMessage($uetsMessageId, $payload, $eypResult['sha1']);

        // 7. Ham EYP'yi kalıcı olarak şifreli sakla
        $uploadBaseDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/uets';
        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0755, true);
        }
        $encEyp = Security::encryptFile(file_get_contents($eypResult['path']));
        $eypFilename = 'eyp_out_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uetsMessageId) . '.enc';
        file_put_contents($uploadBaseDir . '/' . $eypFilename, $encEyp);

        // 8. uets_mesajlar tablosuna kaydet
        $this->mesajModel->saveOutgoing($firmId, [
            'evrak_id' => $evrakId,
            'uets_message_id' => $uetsMessageId,
            'sender' => $settings['uets_adresi'],
            'sender_name' => $options['sender_name'] ?? 'Kurumumuz',
            'recipient' => $recipientUets,
            'recipient_name' => $searchResult['name'] ?? $options['recipient_name'] ?? '',
            'subject' => $evrak->konu,
            'status' => 'submitted',
            'size' => $eypResult['size'],
            'etag' => $eypResult['sha1'],
            'barcode' => $barcode,
            'reference_id' => $payload['reference_id'],
            'ham_eyp_yolu' => 'uploads/evrak-takip/uets/' . $eypFilename,
        ]);

        // 9. evrak_takip tablosunu güncelle
        $db = (new \App\Core\Db())->getConnection();
        $stmtUpdate = $db->prepare(
            "UPDATE evrak_takip SET 
                kaynak_tipi = 'uets', 
                uets_mesaj_id = :uets_mesaj_id, 
                islem_durumu = 'uets_gonderildi',
                guncelleme_tarihi = NOW() 
             WHERE id = :id AND firma_id = :firma_id"
        );
        $stmtUpdate->execute([
            'uets_mesaj_id' => $uetsMessageId,
            'id' => $evrakId,
            'firma_id' => $firmId,
        ]);

        // Geçici dosyaları temizle
        @unlink($tmpPdfPath);
        @unlink($eypResult['path']);
        foreach ($attachments as $att) {
            @unlink($att['path']);
        }

        // Log kaydı
        try {
            (new SystemLogModel())->logActionForFirm(
                $firmId,
                (int) ($_SESSION['user_id'] ?? 0),
                'UETS_TEBLIGAT_GONDERIM',
                "Evrak ID: {$evrakId}, UETS Tebligat No: {$uetsMessageId} başarıyla gönderildi."
            );
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'status' => 'success',
            'uets_message_id' => $uetsMessageId,
            'barcode' => $barcode,
            'message' => 'Tebligat UETS üzerinden başarıyla gönderildi.',
        ];
    }

    private static function deleteDir(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . '/' . $file;
            is_dir($path) ? self::deleteDir($path) : @unlink($path);
        }
        @rmdir($dirPath);
    }
}
