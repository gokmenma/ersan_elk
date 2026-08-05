<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Service\MailGonderService;
use App\Helper\EmailTemplateHelper;
use App\Helper\Date;
use App\Helper\Security;
use App\Helper\RichTextSanitizer;
use App\Model\MenuModel;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['loggedin']) || empty($_SESSION['firma_id']) || empty($_SESSION['user_id']) && empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if (!(new MenuModel())->userCanAccessMenuLink($currentUserId, 'evrak-takip/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decryptId = static function ($value): int {
    return (int) Security::decrypt((string) $value);
};

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Model = new EvrakTakipModel();

    try {
        switch ($action) {
            case 'evrak-kaydet':
                $data = $_POST;
                $attachmentOrder = json_decode((string) ($data['ek_duzen_json'] ?? '[]'), true) ?: [];
                $removedAttachmentIdsEncrypted = json_decode((string) ($data['silinen_ek_ids_json'] ?? '[]'), true) ?: [];
                $id = !empty($data['id']) ? $decryptId($data['id']) : 0;
                unset(
                    $data['action'], 
                    $data['ek_duzen_json'], 
                    $data['silinen_ek_ids_json'],
                    $data['kimin_adina_1'],
                    $data['kimin_adina_2'],
                    $data['kimin_adina_3']
                );
                $data['id'] = $id;
                $data['firma_id'] = $_SESSION['firma_id'];
                $data['aciklama'] = RichTextSanitizer::sanitize($data['aciklama'] ?? '');
                $data['yazi_tipi'] = ($data['yazi_tipi'] ?? 'times_new_roman') === 'arial' ? 'arial' : 'times_new_roman';
                $encryptedSignerIds = (array) ($data['imza_kullanici_ids'] ?? []);
                $signerIds = [];
                foreach (array_slice($encryptedSignerIds, 0, 3) as $encryptedSignerId) {
                    $signerId = $decryptId($encryptedSignerId);
                    if ($signerId > 0) {
                        $signerIds[] = $signerId;
                    }
                }
                $validSigners = $Model->getSigningUsersByIds($signerIds);
                if (count($validSigners) !== count(array_unique($signerIds))) {
                    throw new Exception('İmza atacak kullanıcılardan biri geçersiz veya bu firmaya ait değil.');
                }
                if (($data['evrak_tipi'] ?? 'gelen') === 'giden' && $validSigners === []) {
                    throw new Exception('En az bir imza atacak kullanıcı seçiniz.');
                }
                $data['imza_kullanici_ids'] = json_encode(array_map(fn($user) => (int) $user->id, $validSigners), JSON_UNESCAPED_UNICODE);
                $kiminAdinaInputs = [
                    trim((string) ($_POST['kimin_adina_1'] ?? '')),
                    trim((string) ($_POST['kimin_adina_2'] ?? '')),
                    trim((string) ($_POST['kimin_adina_3'] ?? '')),
                ];
                $data['imza_kimin_adina_json'] = json_encode(array_slice($kiminAdinaInputs, 0, count($validSigners)), JSON_UNESCAPED_UNICODE);
                $data['ilgiler'] = trim((string) ($data['ilgiler'] ?? ''));
                $data['ekler'] = trim((string) ($data['ekler'] ?? ''));
                $data['muhatap_alt_birim'] = trim((string) ($data['muhatap_alt_birim'] ?? '')) ?: null;
                $data['muhatap_adres'] = trim((string) ($data['muhatap_adres'] ?? '')) ?: null;

                if (!empty($data['tarih'])) {
                    $data['tarih'] = Date::Ymd($data['tarih']);
                } else {
                    $data['tarih'] = date('Y-m-d');
                }

                // Dosya yükleme işlemi
                if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] == 0) {
                    if ($_FILES['dosya']['size'] > 10 * 1024 * 1024) {
                        throw new Exception('Evrak dosyası en fazla 10 MB olabilir.');
                    }
                    $allowedMimeTypes = [
                        'application/pdf' => 'pdf',
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                    ];
                    $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['dosya']['tmp_name']);
                    if (!isset($allowedMimeTypes[$detectedMime])) {
                        throw new Exception('Yalnızca PDF, JPG ve PNG dosyaları yüklenebilir.');
                    }
                    $upload_dir = dirname(__DIR__, 2) . '/uploads/evrak-takip/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = $allowedMimeTypes[$detectedMime];
                    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['dosya']['tmp_name'], $upload_path)) {
                        $data['dosya_yolu'] = 'uploads/evrak-takip/' . $file_name;
                    }
                }

                // Checkboxlar için varsayılan değerler
                $data['personel_bildirim_durumu'] = isset($data['personel_bildirim_durumu']) ? 1 : 0;
                $data['cevap_verildi_mi'] = isset($data['cevap_verildi_mi']) ? 1 : 0;

                if (!empty($data['cevap_tarihi'])) {
                    $data['cevap_tarihi'] = Date::Ymd($data['cevap_tarihi']);
                } else {
                    $data['cevap_tarihi'] = null;
                }

                // İlişkili evrak ID
                $data['ilgili_evrak_id'] = !empty($data['ilgili_evrak_id']) ? $decryptId($data['ilgili_evrak_id']) : null;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '' && $key != 'id' && $key != 'action') {
                        $data[$key] = null;
                    }
                }

                $cleanNum = function($val) {
                    if ($val === null || $val === '') return null;
                    if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                        $val = str_replace('.', '', $val); // Binlik noktalarını sil
                        $val = str_replace(',', '.', $val); // Virgülü noktaya çevir
                    } elseif (strpos($val, ',') !== false) {
                        $val = str_replace(',', '.', $val);
                    }
                    return floatval($val);
                };

                if (isset($data['tutar'])) {
                    $data['tutar'] = $cleanNum($data['tutar']);
                }
                if (isset($data['ceza_tutari'])) {
                    $data['ceza_tutari'] = $cleanNum($data['ceza_tutari']);
                }

                if (!empty($data['ceza_tutari']) && empty($data['tutar'])) {
                    $data['tutar'] = $data['ceza_tutari'];
                }

                $data['ceza_hedef_tipi'] = ($data['ceza_hedef_tipi'] ?? 'arac') === 'personel' ? 'personel' : 'arac';
                if ($data['ceza_hedef_tipi'] === 'personel') {
                    $data['plaka'] = null;
                    $data['ceza_personel_id'] = !empty($data['ceza_personel_id']) ? intval($data['ceza_personel_id']) : null;
                } else {
                    $data['ceza_personel_id'] = null;
                }

                $konu_kontrol = mb_strtolower($data['konu'] ?? '', 'UTF-8');
                if (mb_strpos($konu_kontrol, 'trafik') !== false) {
                    if ($data['ceza_hedef_tipi'] === 'personel' && empty($data['ceza_personel_id'])) {
                        throw new Exception('Lütfen cezanın yazıldığı personeli seçiniz.');
                    }
                    if ($data['ceza_hedef_tipi'] === 'arac' && empty($data['plaka'])) {
                        throw new Exception('Lütfen cezanın yazıldığı aracı seçiniz.');
                    }
                }

                if ($id > 0) {
                    $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla güncellendi.";
                } else {
                    $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;
                    $id = $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla kaydedildi.";
                }

                $real_id = is_numeric($id) ? intval($id) : intval(Security::decrypt($id));

                $orderedExistingIds = [];
                $newAttachmentPositions = [];
                foreach ($attachmentOrder as $position => $attachmentItem) {
                    if (($attachmentItem['type'] ?? '') === 'existing') {
                        $attachmentId = $decryptId($attachmentItem['id'] ?? '');
                        $orderedExistingIds[] = $attachmentId;
                    } else {
                        $orderedExistingIds[] = 0;
                        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($attachmentItem['key'] ?? ''));
                        if ($key !== '') {
                            $newAttachmentPositions[$key] = $position + 1;
                        }
                    }
                }
                $removedAttachmentIds = [];
                foreach ($removedAttachmentIdsEncrypted as $removedEncryptedId) {
                    $removedId = $decryptId($removedEncryptedId);
                    if ($removedId > 0) {
                        $removedAttachmentIds[] = $removedId;
                    }
                }
                $Model->syncAttachmentState($real_id, $orderedExistingIds, $removedAttachmentIds);

                if (!empty($_FILES['ek_dosyalari']['name']) && is_array($_FILES['ek_dosyalari']['name'])) {
                    $attachmentDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
                    if (!is_dir($attachmentDir)) {
                        @mkdir($attachmentDir, 0777, true);
                        @chmod($attachmentDir, 0777);
                    }
                    if (!is_dir($attachmentDir)) {
                        throw new Exception('Evrak eki klasörü oluşturulamadı.');
                    }
                    $allowedAttachmentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'odt'];
                    $allowedAttachmentMimes = [
                        'application/pdf', 'image/jpeg', 'image/png', 'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.oasis.opendocument.text', 'application/zip'
                    ];
                    foreach ($_FILES['ek_dosyalari']['name'] as $fileKey => $originalName) {
                        $error = (int) ($_FILES['ek_dosyalari']['error'][$fileKey] ?? UPLOAD_ERR_NO_FILE);
                        $size = (int) ($_FILES['ek_dosyalari']['size'][$fileKey] ?? 0);
                        $tmpName = (string) ($_FILES['ek_dosyalari']['tmp_name'][$fileKey] ?? '');
                        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > 10 * 1024 * 1024) {
                            throw new Exception($originalName . ' yüklenemedi veya 10 MB sınırını aşıyor.');
                        }
                        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                        if (!in_array($extension, $allowedAttachmentExtensions, true) || !in_array($mime, $allowedAttachmentMimes, true)) {
                            throw new Exception($originalName . ' desteklenmeyen bir dosya türünde.');
                        }
                        if ($mime === 'application/zip' && !in_array($extension, ['docx', 'odt'], true)) {
                            throw new Exception($originalName . ' geçersiz bir arşiv dosyasıdır.');
                        }
                        $storedName = 'ek_' . $real_id . '_' . bin2hex(random_bytes(8)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
                        if (!move_uploaded_file($tmpName, $attachmentDir . $storedName)) {
                            throw new Exception($originalName . ' kaydedilemedi.');
                        }
                        $safeOriginalName = mb_substr(basename((string) $originalName), 0, 255, 'UTF-8');
                        $Model->saveAttachment($real_id, [
                            'dosya_adi' => $safeOriginalName,
                            'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName,
                            'mime_tipi' => $mime,
                            'dosya_boyutu' => $size,
                            'sira' => (int) ($newAttachmentPositions[$fileKey] ?? (count($orderedExistingIds) + 1)),
                        ]);
                    }
                }

                $currentAttachments = $Model->getAttachments($real_id);
                $Model->saveWithAttr([
                    'id' => $real_id,
                    'ekler' => implode("\n", array_map(fn($attachment) => $attachment->dosya_adi, $currentAttachments)),
                ]);

                // Trafik Cezası Otomatik Kesinti Ekleme
                $isTrafficFine = false;
                $subject = mb_strtolower($data['konu'] ?? '', 'UTF-8');
                
                // Türkçe karakter duyarsızlaştırma
                $search_subject = str_replace(
                    ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'i'], 
                    ['i', 'g', 'u', 's', 'o', 'c', 'i'], 
                    $subject
                );

                if (
                    strpos($search_subject, 'trafik') !== false || 
                    strpos($search_subject, 'ceza') !== false || 
                    !empty($data['plaka'])
                ) {
                    $isTrafficFine = true;
                }

                $tutar = !empty($data['tutar']) ? floatval($data['tutar']) : (!empty($data['ceza_tutari']) ? floatval($data['ceza_tutari']) : 0);
                if (($data['ceza_hedef_tipi'] ?? 'arac') === 'personel') {
                    $personel_id = !empty($data['ceza_personel_id']) ? intval($data['ceza_personel_id']) : 0;
                } else {
                    $personel_id = !empty($data['ilgili_personel_id']) ? intval($data['ilgili_personel_id']) : (!empty($data['personel_id']) ? intval($data['personel_id']) : 0);
                }

                $log_msg = date('Y-m-d H:i:s') . " - IS_TRAFFIC: " . ($isTrafficFine ? 'TRUE' : 'FALSE') . ", TUTAR: " . $tutar . ", PERSONEL_ID: " . $personel_id . ", DATA: " . json_encode($data) . "\n";
                file_put_contents(dirname(__DIR__, 2) . '/log_debug.txt', $log_msg, FILE_APPEND);

                if ($isTrafficFine && $tutar > 0 && $personel_id > 0) {
                    $tarih_val = $data['tarih']; // Y-m-d

                    // 1. Bordro Parametresi Bul (Trafik Cezası)
                    $param_sql = $Model->getDb()->prepare("
                        SELECT id FROM bordro_parametreleri 
                        WHERE (etiket LIKE 'Trafik Cezası%' OR etiket LIKE 'Trafik Cezasi%') 
                          AND aktif = 1 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $param_sql->execute();
                    $param = $param_sql->fetch(PDO::FETCH_OBJ);
                    $param_id = $param ? $param->id : 33; // varsayılan 33

                    // 2. Dönem Bul
                    $donem_sql = $Model->getDb()->prepare("
                        SELECT id FROM bordro_donemi 
                        WHERE baslangic_tarihi <= :tarih 
                          AND bitis_tarihi >= :tarih 
                          AND silinme_tarihi IS NULL 
                          AND firma_id = :firma_id 
                        LIMIT 1
                    ");
                    $donem_sql->execute([
                        'tarih' => $tarih_val,
                        'firma_id' => $_SESSION['firma_id']
                    ]);
                    $donem = $donem_sql->fetch(PDO::FETCH_OBJ);
                    
                    if ($donem) {
                        $donem_id = $donem->id;
                    } else {
                        // Eğer girilen tarihe ait dönem henüz oluşturulmadıysa, en son oluşturulmuş aktif dönemi seç!
                        $latest_donem_sql = $Model->getDb()->prepare("
                            SELECT id FROM bordro_donemi 
                            WHERE silinme_tarihi IS NULL 
                              AND firma_id = :firma_id 
                            ORDER BY bitis_tarihi DESC 
                            LIMIT 1
                        ");
                        $latest_donem_sql->execute([
                            'firma_id' => $_SESSION['firma_id']
                        ]);
                        $latest_donem = $latest_donem_sql->fetch(PDO::FETCH_OBJ);
                        $donem_id = $latest_donem ? $latest_donem->id : null;
                    }

                    // 3. Evrak No Al
                    $evrak_no_label = $data['evrak_no'] ?? $real_id;

                    // 4. Mükerrer Kontrolü (Aynı Evrak ID için daha önce kesinti girilmiş mi?)
                    $check_kesinti_sql = $Model->getDb()->prepare("
                        SELECT id FROM personel_kesintileri 
                        WHERE aciklama LIKE :desc 
                          AND silinme_tarihi IS NULL 
                        LIMIT 1
                    ");
                    $check_kesinti_sql->execute([
                        'desc' => "%Evrak ID: {$real_id}%"
                    ]);
                    $existing_kesinti = $check_kesinti_sql->fetch(PDO::FETCH_OBJ);

                    $kesinti_data = [
                        'personel_id' => $personel_id,
                        'donem_id' => $donem_id,
                        'tur' => 'Trafik Cezası',
                        'tekrar_tipi' => 'tek_sefer',
                        'hesaplama_tipi' => 'sabit',
                        'parametre_id' => $param_id,
                        'aktif' => 1,
                        'durum' => 'onaylandi',
                        'tutar' => $tutar,
                        'aciklama' => "Evrak Takip'ten otomatik kesinti (Evrak No: #{$evrak_no_label}, Evrak ID: {$real_id})",
                        'tarih' => date('d.m.Y', strtotime($tarih_val)),
                        'kayit_yapan' => $_SESSION['user_id'] ?? null
                    ];

                    if ($existing_kesinti) {
                        // Güncelle
                        $update_sql = $Model->getDb()->prepare("
                            UPDATE personel_kesintileri 
                            SET donem_id = :donem_id, 
                                tutar = :tutar, 
                                aciklama = :aciklama, 
                                tarih = :tarih, 
                                personel_id = :personel_id
                            WHERE id = :id
                        ");
                        $update_sql->execute([
                            'donem_id' => $kesinti_data['donem_id'],
                            'tutar' => $kesinti_data['tutar'],
                            'aciklama' => $kesinti_data['aciklama'],
                            'tarih' => $kesinti_data['tarih'],
                            'personel_id' => $kesinti_data['personel_id'],
                            'id' => $existing_kesinti->id
                        ]);
                    } else {
                        // Yeni Ekle
                        $insert_sql = $Model->getDb()->prepare("
                            INSERT INTO personel_kesintileri (
                                personel_id, donem_id, tur, tekrar_tipi, hesaplama_tipi, 
                                parametre_id, aktif, durum, tutar, aciklama, tarih, kayit_yapan, olusturma_tarihi
                            ) VALUES (
                                :personel_id, :donem_id, :tur, :tekrar_tipi, :hesaplama_tipi, 
                                :parametre_id, :aktif, :durum, :tutar, :aciklama, :tarih, :kayit_yapan, NOW()
                            )
                        ");
                        $insert_sql->execute($kesinti_data);
                    }
                }

                // Otomatik bildirim gönderimi
                if ($data['personel_bildirim_durumu'] == 1) {
                    $evrak = $Model->getById($real_id);
                    $Personel = new PersonelModel();

                    // 1. Zimmetlenen Personel (Ofis)
                    if (!empty($data['personel_id'])) {
                        $personelData = $Personel->find($data['personel_id']);
                        if ($evrak && $personelData) {
                            $email = $personelData->email_adresi ?? '';
                            $adi_soyadi = $personelData->adi_soyadi;

                            // Uygulama içi bildirim
                            if (!empty($email)) {
                                $User = new UserModel();
                                $user = $User->checkUser($email);
                                if ($user) {
                                    $Bildirim = new BildirimModel();
                                    $Bildirim->createNotification(
                                        $user->id,
                                        "Yeni Evrak Zimmetlendi",
                                        "Tarafınıza '{$evrak->konu}' konulu bir evrak zimmetlenmiştir.",
                                        "index.php?p=evrak-takip/list",
                                        "file",
                                        "info",
                                        \App\Model\UserNotificationPreferenceModel::TYPE_DOCUMENT
                                    );
                                }
                            }

                            // E-Posta Bildirimi
                            if (!empty($email)) {
                                $evrak_tipi_label = $evrak->evrak_tipi == 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK';
                                $icerik = "
                                    <p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>{$adi_soyadi}</b>,</p>
                                    <p style='color: #475569; margin-bottom: 24px;'>Sistem üzerinden tarafınıza yeni bir evrak zimmetlenmiştir. Detaylar aşağıdadır:</p>
                                    <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>EVRAK TİPİ</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak_tipi_label}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>TARİH</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>" . date('d.m.Y', strtotime($evrak->tarih)) . "</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>EVRAK NO</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>#{$evrak->evrak_no}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>KONU</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak->konu}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px;'>KURUM / FİRMA</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px;'>{$evrak->kurum_adi}</td></tr>
                                        </table>
                                    </div>
                                    <p style='color: #475569;'>Evrak detaylarını görüntülemek ve diğer işlemler için sisteme giriş yapabilirsiniz.</p>";
                                $html = EmailTemplateHelper::getTemplate("Evrak Bildirimi", $icerik, "Sisteme Giriş Yap", "https://" . $_SERVER['HTTP_HOST'] . "/index.php?p=evrak-takip/list");
                                
                                // Bildirim tarihini güncelle
                                $Model->saveWithAttr([
                                    'id' => $real_id,
                                    'son_bildirim_tarihi_personel' => date('Y-m-d H:i:s')
                                ]);

                                try {
                                    MailGonderService::gonder([$email], "Evrak Bildirimi: " . $evrak->konu, $html);
                                } catch (\Throwable $e) {
                                    error_log("Zimmetli Personel Mail Hatasi: " . $e->getMessage());
                                }
                            }
                        }
                    }

                    // 2. İlgili Personel
                    if (!empty($data['ilgili_personel_id'])) {
                        $personelData = $Personel->find($data['ilgili_personel_id']);
                        if ($evrak && $personelData) {
                            $email = $personelData->email_adresi ?? '';
                            $adi_soyadi = $personelData->adi_soyadi;

                            // Uygulama içi bildirim
                            if (!empty($email)) {
                                $User = new UserModel();
                                $user = $User->checkUser($email);
                                if ($user) {
                                    $Bildirim = new BildirimModel();
                                    $Bildirim->createNotification(
                                        $user->id,
                                        "Yeni Evrak Zimmetlendi",
                                        "Tarafınıza '{$evrak->konu}' konulu bir evrak zimmetlenmiştir.",
                                        "index.php?p=evrak-takip/list",
                                        "file",
                                        "info",
                                        \App\Model\UserNotificationPreferenceModel::TYPE_DOCUMENT
                                    );
                                }
                            }

                            // E-Posta Bildirimi
                            if (!empty($email)) {
                                $evrak_tipi_label = $evrak->evrak_tipi == 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK';
                                $icerik = "
                                    <p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>{$adi_soyadi}</b>,</p>
                                    <p style='color: #475569; margin-bottom: 24px;'>Sistem üzerinden tarafınıza yeni bir evrak zimmetlenmiştir. Detaylar aşağıdadır:</p>
                                    <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>EVRAK TİPİ</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak_tipi_label}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>TARİH</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>" . date('d.m.Y', strtotime($evrak->tarih)) . "</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>EVRAK NO</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>#{$evrak->evrak_no}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>KONU</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak->konu}</td></tr>
                                            <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px;'>KURUM / FİRMA</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px;'>{$evrak->kurum_adi}</td></tr>
                                        </table>
                                    </div>
                                    <p style='color: #475569;'>Evrak detaylarını görüntülemek ve diğer işlemler için sisteme giriş yapabilirsiniz.</p>";
                                $html = EmailTemplateHelper::getTemplate("Evrak Bildirimi", $icerik, "Sisteme Giriş Yap", "https://" . $_SERVER['HTTP_HOST'] . "/index.php?p=evrak-takip/list");
                                
                                // Bildirim tarihini güncelle
                                $Model->saveWithAttr([
                                    'id' => $real_id,
                                    'son_bildirim_tarihi_ilgili' => date('Y-m-d H:i:s')
                                ]);

                                try {
                                    MailGonderService::gonder([$email], "Evrak Bildirimi: " . $evrak->konu, $html);
                                } catch (\Throwable $e) {
                                    error_log("İlgili Personel Mail Hatasi: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }

                // Eğer giden evrak ise ve ilgili bir gelen evrak seçildiyse 
                // o gelen evrakı "cevap verildi" olarak işaretle
                if ($data['evrak_tipi'] == 'giden' && !empty($data['ilgili_evrak_id'])) {
                    $Model->markAsReplied($data['ilgili_evrak_id'], $data['tarih']);
                }

                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'evrak-sil':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz evrak ID.");
                }

                $Model->softDelete($id);

                // Evrak silindiğinde ilişkili trafik cezası kesintisini de otomatik sil (soft delete)
                $delete_kesinti_sql = $Model->getDb()->prepare("
                    UPDATE personel_kesintileri 
                    SET silinme_tarihi = NOW() 
                    WHERE aciklama LIKE :desc 
                      AND silinme_tarihi IS NULL
                ");
                $delete_kesinti_sql->execute([
                    'desc' => "%Evrak ID: {$id}%"
                ]);

                echo json_encode(['status' => 'success', 'message' => 'Evrak başarıyla silindi.']);
                break;

            case 'evrak-detay':
                $encryptedId = (string) ($_POST['id'] ?? '');
                $id = $decryptId($encryptedId);
                $evrak = $Model->getById($id);

                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                $evrak->id = $encryptedId;
                if (!empty($evrak->ilgili_evrak_id)) {
                    $evrak->ilgili_evrak_id = Security::encrypt($evrak->ilgili_evrak_id);
                }
                $storedSignerIds = json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: [];
                $evrak->imza_kullanici_ids = array_map(fn($signerId) => Security::encrypt((int) $signerId), $storedSignerIds);
                echo json_encode(['status' => 'success', 'data' => $evrak]);
                break;

            case 'evrak-listesi':
                $evraklar = $Model->all();
                echo json_encode(['status' => 'success', 'data' => $evraklar]);
                break;

            case 'evrak-istatistik':
                $stats = $Model->getStats();
                echo json_encode(['status' => 'success', 'data' => $stats]);
                break;

            case 'get-next-evrak-no':
                $tip = $_POST['tip'] ?? 'gelen';
                $next_no = $Model->getMaxEvrakNo($tip);
                echo json_encode(['status' => 'success', 'next_no' => $next_no]);
                break;

            case 'get-konular':
                $konular = $Model->getDistinctKonular();
                echo json_encode(['status' => 'success', 'data' => $konular]);
                break;

            case 'evrak-bildir':
                $id = $decryptId($_POST['id'] ?? '');
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $type = $_POST['type'] ?? 'ilgili'; // 'personel' veya 'ilgili'

                if ($id <= 0 || $personel_id <= 0) {
                    throw new Exception("Geçersiz veri.");
                }

                $evrak = $Model->getById($id);
                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                $Personel = new PersonelModel();
                $personelData = $Personel->find($personel_id);
                if (!$personelData) {
                    throw new Exception("Personel bulunamadı.");
                }

                $email = $personelData->email_adresi ?? '';
                $adi_soyadi = $personelData->adi_soyadi;

                // 1. Uygulama içi bildirim (Eğer email ile eşleşen bir kullanıcı varsa)
                if (!empty($email)) {
                    $User = new UserModel();
                    $user = $User->checkUser($email);
                    
                    if ($user) {
                        $Bildirim = new BildirimModel();
                        $Bildirim->createNotification(
                            $user->id,
                            "Yeni Evrak Zimmetlendi",
                            "Tarafınıza '{$evrak->konu}' konulu bir evrak zimmetlenmiştir.",
                            "index.php?p=evrak-takip/list",
                            "file",
                            "info",
                            \App\Model\UserNotificationPreferenceModel::TYPE_DOCUMENT
                        );
                    }
                }

                // 2. E-Posta Bildirimi
                if (!empty($email)) {
                    $evrak_tipi_label = $evrak->evrak_tipi == 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK';
                    
                    $icerik = "
                        <p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>{$adi_soyadi}</b>,</p>
                        <p style='color: #475569; margin-bottom: 24px;'>Sistem üzerinden tarafınıza yeni bir evrak zimmetlenmiştir. Detaylar aşağıdadır:</p>
                        
                        <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>EVRAK TİPİ</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak_tipi_label}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>TARİH</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>" . date('d.m.Y', strtotime($evrak->tarih)) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>EVRAK NO</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>#{$evrak->evrak_no}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>KONU</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak->konu}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px;'>KURUM / FİRMA</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px;'>{$evrak->kurum_adi}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p style='color: #475569;'>Evrak detaylarını görüntülemek ve diğer işlemler için sisteme giriş yapabilirsiniz.</p>";
                    
                    $html = EmailTemplateHelper::getTemplate(
                        "Evrak Bildirimi",
                        $icerik,
                        "Sisteme Giriş Yap",
                        "https://" . $_SERVER['HTTP_HOST'] . "/index.php?p=evrak-takip/list"
                    );

                    // Bildirim tarihini güncelle
                    $column = ($type == 'personel') ? 'son_bildirim_tarihi_personel' : 'son_bildirim_tarihi_ilgili';
                    $Model->saveWithAttr([
                        'id' => $id,
                        $column => date('Y-m-d H:i:s')
                    ]);

                    MailGonderService::gonder([$email], "Evrak Bildirimi: " . $evrak->konu, $html);
                    
                    $msg = "Bildirim ve mail başarıyla gönderildi.";
                } else {
                    $msg = "Personelin e-posta adresi bulunmadığı için sadece sistem bildirimi gönderildi (Eğer kullanıcı hesabı varsa).";
                }

                echo json_encode(['status' => 'success', 'message' => $msg]);
                break;

            case 'arac-zimmet-sorgula':
                $plaka = $_POST['plaka'] ?? '';
                $tarih = $_POST['tarih'] ?? '';
                
                if (empty($plaka) || empty($tarih)) {
                    throw new Exception("Plaka ve tarih alanları gereklidir.");
                }
                
                $tarih_db = Date::Ymd($tarih);
                
                $zimmet = $Model->findVehicleAssignment($plaka, $tarih_db);
                
                if ($zimmet) {
                    echo json_encode([
                        'status' => 'success', 
                        'personel_id' => $zimmet->personel_id,
                        'personel_adi' => $zimmet->adi_soyadi
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "Bu plaka için bu tarihte zimmetli personel bulunamadı."
                    ]);
                }
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
