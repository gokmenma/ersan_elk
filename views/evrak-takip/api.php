<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\EvrakTakipModel;
use App\Model\EvrakSablonModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Service\MailGonderService;
use App\Helper\EmailTemplateHelper;
use App\Helper\Date;
use App\Helper\Security;
use App\Helper\RichTextSanitizer;
use App\Model\MenuModel;
use App\Model\SystemLogModel;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;
use App\Service\EvrakOnayBildirimService;
use App\Model\UetsSettingsModel;
use App\Model\UetsMesajModel;
use App\Model\UetsEvidenceModel;
use App\Model\UetsRehberModel;
use App\Service\UetsClientService;
use App\Service\UetsSyncService;
use App\Model\KepSettingsModel;
use App\Model\KepMesajModel;
use App\Model\KepDelilModel;
use App\Model\KepAdresDefteriModel;
use App\Service\KepClientService;
use App\Service\KepService;
use App\Service\PadesEImzaService;
use App\Service\MobilImzaPadesService;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['loggedin']) || empty($_SESSION['firma_id']) || empty($_SESSION['user_id']) && empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('evrak-takip/api', $action);
} elseif (!(new MenuModel())->userCanAccessMenuLink($currentUserId, 'evrak-takip/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decryptId = static function ($value): int {
    return (int) Security::decrypt((string) $value);
};

$afterPadesApproval = static function (int $id, array $approval): ?array {
    $model = new EvrakTakipModel();
    $onayBildirim = new EvrakOnayBildirimService();
    $onaylananEvrak = $model->getById($id);
    if (!$approval['completed']) {
        $siradaki = $model->getNextPendingSigner($id);
        if ($siradaki) {
            $onayBildirim->imzaSirasiGeldi($onaylananEvrak, (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $approval['total']);
        }
        return null;
    }
    $onayBildirim->onayTamamlandi($onaylananEvrak);
    if (($onaylananEvrak->gonderim_kanali ?? 'normal') !== 'kep') {
        return null;
    }
    $kepAlicilar = trim((string) ($onaylananEvrak->kep_alicilar ?: $onaylananEvrak->alici_kep));
    return $kepAlicilar !== '' ? [
        'enabled' => true, 'alici_kep' => $kepAlicilar,
        'paket_turu' => (string) ($onaylananEvrak->kep_paket_turu ?: 'Standart'),
    ] : ['enabled' => false, 'error' => 'Kayıtlı alıcı KEP adresi bulunamadı.'];
};

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Model = new EvrakTakipModel();

    try {
        switch ($action) {
            case 'evrak-sablon-listele':
                $rows = (new EvrakSablonModel())->getAllActive();
                $templates = array_map(static function ($row): array {
                    $attachmentRows = (new EvrakSablonModel())->getAttachments((int) $row->id);
                    return [
                        'id' => Security::encrypt((int) $row->id),
                        'adi' => (string) $row->adi,
                        'veri' => json_decode((string) $row->sablon_verisi, true) ?: [],
                        'ek_dosyalar' => array_map(static fn($file) => [
                            'id' => Security::encrypt((int) $file->id), 'name' => (string) $file->dosya_adi,
                            'path' => (string) $file->dosya_yolu, 'size' => (int) $file->dosya_boyutu,
                        ], $attachmentRows),
                        'guncelleme_tarihi' => !empty($row->updated_at) ? date('d.m.Y H:i', strtotime($row->updated_at)) : '',
                    ];
                }, $rows);
                echo json_encode(['status' => 'success', 'data' => $templates], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'evrak-sablon-kaydet':
                $saveMode = (string) ($_POST['kayit_modu'] ?? 'create');
                if (!in_array($saveMode, ['create', 'update'], true)) {
                    throw new Exception('Şablon kayıt modu geçersiz.');
                }
                $templateId = $saveMode === 'update' && !empty($_POST['sablon_id']) ? $decryptId($_POST['sablon_id']) : 0;
                if ($saveMode === 'update' && $templateId <= 0) {
                    throw new Exception('Güncellenecek şablon bulunamadı.');
                }
                $templateName = trim((string) ($_POST['sablon_adi'] ?? ''));
                if ($templateName === '' || mb_strlen($templateName) > 150) {
                    throw new Exception('Şablon adı zorunludur ve en fazla 150 karakter olabilir.');
                }
                $rawTemplateData = json_decode((string) ($_POST['sablon_verisi'] ?? ''), true);
                if (!is_array($rawTemplateData)) {
                    throw new Exception('Şablon verisi geçersiz.');
                }
                $allowedTemplateFields = [
                    'konu', 'kurum_adi', 'muhatap_alt_birim', 'muhatap_adres', 'ilgiler', 'ekler',
                    'tarih', 'evrak_no', 'konu', 'kurum_adi', 'muhatap_alt_birim', 'muhatap_adres', 'ilgiler', 'ekler',
                    'aciklama', 'ust_yazi_gerekli_degil', 'imza_kullanici_ids', 'imza_kimin_adina',
                    'ilgili_evrak_id', 'personel_id', 'ilgili_personel_id'
                ];
                $templateData = array_intersect_key($rawTemplateData, array_flip($allowedTemplateFields));
                $templateData['aciklama'] = RichTextSanitizer::sanitize((string) ($templateData['aciklama'] ?? ''));
                foreach (['konu', 'kurum_adi', 'muhatap_alt_birim', 'muhatap_adres', 'ilgiler', 'ekler'] as $field) {
                    $templateData[$field] = mb_substr(trim((string) ($templateData[$field] ?? '')), 0, $field === 'konu' || $field === 'kurum_adi' ? 500 : 5000);
                }
                $templateData['ust_yazi_gerekli_degil'] = !empty($templateData['ust_yazi_gerekli_degil']) ? 1 : 0;
                $signerIds = array_values(array_unique(array_filter(array_map('intval', (array) ($templateData['imza_kullanici_ids'] ?? [])))));
                $validSigners = $Model->getSigningUsersByIds(array_slice($signerIds, 0, 3));
                $templateData['imza_kullanici_ids'] = array_map(static fn($user) => (int) $user->id, $validSigners);
                $templateData['imza_kimin_adina'] = array_slice(array_map(static fn($value) => mb_substr(trim((string) $value), 0, 255), (array) ($templateData['imza_kimin_adina'] ?? [])), 0, 3);
                $savedTemplateId = (new EvrakSablonModel())->saveTemplate($templateId, $templateName, $templateData, $currentUserId);
                $templateFiles = [];
                $existingFiles = json_decode((string) ($_POST['sablon_mevcut_ekler'] ?? '[]'), true) ?: [];
                foreach ($existingFiles as $file) {
                    $attachmentId = $decryptId($file['id'] ?? '');
                    $attachment = ($file['type'] ?? '') === 'template'
                        ? (new EvrakSablonModel())->getAttachment($attachmentId)
                        : $Model->getAttachmentById($attachmentId);
                    if (!$attachment) continue;
                    $source = dirname(__DIR__, 2) . '/' . ltrim((string) $attachment->dosya_yolu, '/');
                    if (!is_file($source)) continue;
                    $templateDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/sablon-ekleri/';
                    if (!is_dir($templateDir)) @mkdir($templateDir, 0777, true);
                    $stored = 'sablon_' . $savedTemplateId . '_' . bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
                    if (copy($source, $templateDir . $stored)) {
                        $templateFiles[] = ['dosya_adi' => mb_substr((string) $attachment->dosya_adi, 0, 255), 'dosya_yolu' => 'uploads/evrak-takip/sablon-ekleri/' . $stored, 'mime_tipi' => $attachment->mime_tipi ?: (mime_content_type($source) ?: null), 'dosya_boyutu' => (int) $attachment->dosya_boyutu];
                    }
                }
                if (!empty($_FILES['sablon_yeni_ekler']['name']) && is_array($_FILES['sablon_yeni_ekler']['name'])) {
                    foreach ($_FILES['sablon_yeni_ekler']['name'] as $key => $name) {
                        $tmp = (string) ($_FILES['sablon_yeni_ekler']['tmp_name'][$key] ?? '');
                        $size = (int) ($_FILES['sablon_yeni_ekler']['size'][$key] ?? 0);
                        if (!is_uploaded_file($tmp) || $size <= 0 || $size > 10 * 1024 * 1024) continue;
                        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx','odt','ods'], true)) continue;
                        $templateDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/sablon-ekleri/';
                        if (!is_dir($templateDir)) @mkdir($templateDir, 0777, true);
                        $stored = 'sablon_' . $savedTemplateId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        if (move_uploaded_file($tmp, $templateDir . $stored)) $templateFiles[] = ['dosya_adi' => mb_substr(basename((string) $name), 0, 255), 'dosya_yolu' => 'uploads/evrak-takip/sablon-ekleri/' . $stored, 'mime_tipi' => mime_content_type($templateDir . $stored) ?: null, 'dosya_boyutu' => $size];
                    }
                }
                (new EvrakSablonModel())->replaceAttachments($savedTemplateId, $templateFiles);
                echo json_encode(['status' => 'success', 'message' => $templateId > 0 ? 'Şablon güncellendi.' : 'Şablon kaydedildi.', 'id' => Security::encrypt($savedTemplateId)], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-sablon-sil':
                $templateId = $decryptId($_POST['sablon_id'] ?? '');
                if ($templateId <= 0 || !(new EvrakSablonModel())->softDeleteTemplate($templateId, $currentUserId)) {
                    throw new Exception('Şablon bulunamadı veya daha önce silinmiş.');
                }
                echo json_encode(['status' => 'success', 'message' => 'Şablon silindi.'], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-kaydet':
                $data = $_POST;
                $attachmentOrder = json_decode((string) ($data['ek_duzen_json'] ?? '[]'), true) ?: [];
                $removedAttachmentIdsEncrypted = json_decode((string) ($data['silinen_ek_ids_json'] ?? '[]'), true) ?: [];
                $id = !empty($data['id']) ? $decryptId($data['id']) : 0;
                if ($id > 0) {
                    $existingDocument = $Model->getById($id);
                    if (!$existingDocument) {
                        throw new Exception('Evrak bulunamadı.');
                    }
                    if (($existingDocument->onay_durumu ?? 'taslak') !== 'taslak') {
                        throw new Exception('Elektronik imza onay süreci başlamış evrak değiştirilemez.');
                    }
                }
                unset(
                    $data['action'], 
                    $data['ek_duzen_json'], 
                    $data['silinen_ek_ids_json'],
                    $data['gonderim_turu_secimi'],
                    $data['kimin_adina_1'],
                    $data['kimin_adina_2'],
                    $data['kimin_adina_3']
                );
                if (empty(trim((string) ($data['konu'] ?? '')))) {
                    throw new Exception('Evrak Konusu alanını doldurunuz.');
                }
                if (empty(trim((string) ($data['kurum_adi'] ?? '')))) {
                    throw new Exception('Muhatap Kurum / Kişi alanını doldurunuz.');
                }
                $ustYaziGerekliDegil = !empty($data['ust_yazi_gerekli_degil']) ? 1 : 0;
                $data['ust_yazi_gerekli_degil'] = $ustYaziGerekliDegil;

                $gonderimKanali = (string) ($data['gonderim_kanali'] ?? 'normal');
                if (!in_array($gonderimKanali, ['normal', 'kep'], true)) {
                    throw new Exception('Gönderim kanalı geçersiz.');
                }
                $data['gonderim_kanali'] = $gonderimKanali;
                $kepAlicilar = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', (string) ($data['kep_alicilar'] ?? '')) ?: []))));
                if ($gonderimKanali === 'kep') {
                    if ($permissionPolicy->isReady()) {
                        Gate::authorizeApiPolicy('evrak-takip/api', 'kep-gonder');
                    }
                    $firmaKepAyarlari = (new KepSettingsModel())->getSettings((int) $_SESSION['firma_id']);
                    if (!$firmaKepAyarlari || empty($firmaKepAyarlari['kep_adresi'])) {
                        throw new Exception('Firma KEP ayarları tamamlanmadan KEP evrakı hazırlanamaz.');
                    }
                    if ($kepAlicilar === []) {
                        throw new Exception('KEP gönderimi için en az bir alıcı KEP adresi giriniz.');
                    }
                    foreach ($kepAlicilar as $kepAlici) {
                        if (!filter_var($kepAlici, FILTER_VALIDATE_EMAIL) || mb_strlen($kepAlici) > 255) {
                            throw new Exception('Geçersiz KEP adresi: ' . $kepAlici);
                        }
                    }
                    $data['kep_paket_turu'] = in_array(($data['kep_paket_turu'] ?? ''), ['Standart', 'EYazisma'], true) ? $data['kep_paket_turu'] : 'Standart';
                    if ($ustYaziGerekliDegil && $data['kep_paket_turu'] === 'EYazisma') {
                        throw new Exception('E-Yazışma paketi için resmî üst yazı gereklidir. Yalnızca ek gönderiminde Standart KEP paketini seçiniz.');
                    }
                    $data['kep_ileti_tipi'] = in_array(($data['kep_ileti_tipi'] ?? ''), ['resmi', 'standart'], true) ? $data['kep_ileti_tipi'] : 'resmi';
                    $data['kep_ileti_detayi'] = mb_substr(trim((string) ($data['kep_ileti_detayi'] ?? '')), 0, 500);
                    $data['kep_mesaj_metni'] = RichTextSanitizer::sanitize((string) ($data['kep_mesaj_metni'] ?? ''));
                    $data['kep_alicilar'] = implode(';', $kepAlicilar);
                    $data['alici_kep'] = $kepAlicilar[0];
                } else {
                    $data['kep_alicilar'] = $data['alici_kep'] = $data['kep_ileti_detayi'] = $data['kep_mesaj_metni'] = null;
                    $data['kep_ileti_tipi'] = 'resmi';
                    $data['kep_paket_turu'] = 'Standart';
                }

                if (($data['evrak_tipi'] ?? 'gelen') === 'giden' && !$ustYaziGerekliDegil) {
                    if (empty(trim(strip_tags((string) ($data['aciklama'] ?? ''))))) {
                        throw new Exception('Resmî Yazı Metni (İçerik) alanını doldurunuz.');
                    }
                }

                if (($data['evrak_tipi'] ?? 'gelen') === 'giden' && $ustYaziGerekliDegil) {
                    $hasExistingAttachment = false;
                    foreach ($attachmentOrder as $attachmentItem) {
                        if (!in_array(($attachmentItem['id'] ?? ''), $removedAttachmentIdsEncrypted, true)) {
                            $hasExistingAttachment = true;
                            break;
                        }
                    }
                    $hasUploadedAttachment = !empty($_FILES['ek_dosyalari']['name'])
                        && is_array($_FILES['ek_dosyalari']['name'])
                        && count(array_filter($_FILES['ek_dosyalari']['name'])) > 0;
                    if (!$hasExistingAttachment && !$hasUploadedAttachment) {
                        throw new Exception('Yalnızca eklerle gönderimde en az bir belge eklenmelidir.');
                    }
                }

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
                if (($data['evrak_tipi'] ?? 'gelen') === 'giden' && !$ustYaziGerekliDegil && $validSigners === []) {
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

                if (!empty($data['son_tarih'])) {
                    $data['son_tarih'] = Date::Ymd($data['son_tarih']);
                } else {
                    $data['son_tarih'] = null;
                }

                // Dosya yükleme işlemi
                if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] == 0) {
                    if ($_FILES['dosya']['size'] > 10 * 1024 * 1024) {
                        throw new Exception('Evrak dosyası en fazla 10 MB olabilir.');
                    }
                    $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['dosya']['tmp_name']);
                    $origExt = strtolower(pathinfo((string) ($_FILES['dosya']['name'] ?? ''), PATHINFO_EXTENSION));
                    $allowedMimeMap = [
                        'application/pdf' => 'pdf',
                        'application/x-pdf' => 'pdf',
                        'application/acrobat' => 'pdf',
                        'applications/vnd.pdf' => 'pdf',
                        'text/pdf' => 'pdf',
                        'text/x-pdf' => 'pdf',
                        'image/jpeg' => 'jpg',
                        'image/pjpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/png' => 'png',
                        'image/x-png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    $isAllowedMime = isset($allowedMimeMap[$detectedMime]);
                    $isOctetStreamWithValidExt = in_array($detectedMime, ['application/octet-stream', 'application/x-download', 'binary/octet-stream'], true) 
                        && in_array($origExt, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true);

                    if (!$isAllowedMime && !$isOctetStreamWithValidExt) {
                        throw new Exception('Yalnızca PDF, JPG, PNG ve WEBP dosyaları yüklenebilir.');
                    }
                    $upload_dir = dirname(__DIR__, 2) . '/uploads/evrak-takip/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = $allowedMimeMap[$detectedMime] ?? ($origExt === 'jpeg' ? 'jpg' : $origExt);
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

                $evrak_tipi = $data['evrak_tipi'] ?? 'gelen';
                $konu_kontrol = mb_strtolower($data['konu'] ?? '', 'UTF-8');
                if ($evrak_tipi === 'gelen' && mb_strpos($konu_kontrol, 'trafik') !== false) {
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
                $templateAttachmentPositions = [];
                foreach ($attachmentOrder as $position => $attachmentItem) {
                    if (($attachmentItem['type'] ?? '') === 'existing') {
                        $attachmentId = $decryptId($attachmentItem['id'] ?? '');
                        $orderedExistingIds[] = $attachmentId;
                    } elseif (($attachmentItem['type'] ?? '') === 'template') {
                        $orderedExistingIds[] = 0;
                        $templateAttachmentId = $decryptId($attachmentItem['id'] ?? '');
                        if ($templateAttachmentId > 0) $templateAttachmentPositions[] = ['id' => $templateAttachmentId, 'sira' => $position + 1];
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

                foreach ($templateAttachmentPositions as $templateAttachmentPosition) {
                    $templateAttachment = (new EvrakSablonModel())->getAttachment($templateAttachmentPosition['id']);
                    if (!$templateAttachment) continue;
                    $source = dirname(__DIR__, 2) . '/' . ltrim((string) $templateAttachment->dosya_yolu, '/');
                    if (!is_file($source)) continue;
                    $attachmentDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
                    if (!is_dir($attachmentDir)) @mkdir($attachmentDir, 0777, true);
                    $storedName = 'ek_' . $real_id . '_' . bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
                    if (!copy($source, $attachmentDir . $storedName)) throw new Exception('Şablon eki evraka kopyalanamadı.');
                    $Model->saveAttachment($real_id, ['dosya_adi' => $templateAttachment->dosya_adi, 'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName, 'mime_tipi' => $templateAttachment->mime_tipi, 'dosya_boyutu' => $templateAttachment->dosya_boyutu, 'sira' => $templateAttachmentPosition['sira']]);
                }

                if (!empty($_FILES['ek_dosyalari']['name']) && is_array($_FILES['ek_dosyalari']['name'])) {
                    $attachmentDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
                    if (!is_dir($attachmentDir)) {
                        @mkdir($attachmentDir, 0777, true);
                        @chmod($attachmentDir, 0777);
                    }
                    if (!is_dir($attachmentDir)) {
                        throw new Exception('Evrak eki klasörü oluşturulamadı.');
                    }
                    $allowedAttachmentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'];
                    $allowedAttachmentMimes = [
                        'application/pdf',
                        'application/x-pdf',
                        'application/acrobat',
                        'applications/vnd.pdf',
                        'text/pdf',
                        'text/x-pdf',
                        'image/jpeg',
                        'image/pjpeg',
                        'image/jpg',
                        'image/png',
                        'image/x-png',
                        'image/webp',
                        'image/gif',
                        'image/bmp',
                        'image/x-ms-bmp',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.oasis.opendocument.text',
                        'application/vnd.oasis.opendocument.spreadsheet',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',
                        'application/x-zip',
                        'application/x-zip-compressed',
                        'application/octet-stream',
                        'application/x-download',
                        'application/download',
                        'binary/octet-stream'
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
                        if (in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed'], true) && !in_array($extension, ['docx', 'odt', 'xlsx', 'ods'], true)) {
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
                $Model->resetApprovalWorkflow($real_id, array_map(fn($user) => (int) $user->id, $validSigners));

                // Trafik Cezası Otomatik Kesinti Ekleme (Yalnızca Gelen Evrak için)
                $evrak_tipi = $data['evrak_tipi'] ?? 'gelen';
                $isTrafficFine = false;

                if ($evrak_tipi === 'gelen') {
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
                } else {
                    // Evrak tipi gelen değilse (örneğin giden evrak ise) ilişkilendirilmiş eski bir kesinti varsa pasife al
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
                    if ($existing_kesinti) {
                        $del_kesinti_sql = $Model->getDb()->prepare("
                            UPDATE personel_kesintileri 
                            SET silinme_tarihi = NOW() 
                            WHERE id = :id
                        ");
                        $del_kesinti_sql->execute(['id' => $existing_kesinti->id]);
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
                                    $Bildirim->createNotificationWithPush(
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
                                    $Bildirim->createNotificationWithPush(
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

                echo json_encode(['status' => 'success', 'message' => $message, 'id' => Security::encrypt($real_id)]);
                break;

            case 'evrak-e-imza-onaya-sun':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception('Geçersiz evrak ID.');
                }
                $submission = $Model->submitForApproval($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Evrak E-İmza Onayına Sunma',
                    "Evrak ID: {$id} elektronik imza onayına sunuldu. İmzacılar: " . implode(', ', $submission['signers'])
                        . ($submission['otomatik_imza'] ? '. Evrakı oluşturan ilk imzacı olduğu için imzası otomatik atıldı.' : ''),
                    SystemLogModel::LEVEL_IMPORTANT
                );
                $siradaki = $Model->getNextPendingSigner($id);
                if ($siradaki) {
                    (new EvrakOnayBildirimService())->imzaSirasiGeldi(
                        $Model->getById($id),
                        (int) $siradaki->kullanici_id,
                        (int) $siradaki->sira,
                        (int) $submission['total']
                    );
                }
                if ($submission['completed']) {
                    $message = 'Tek imzacı siz olduğunuz için imzanız otomatik atıldı ve evrak elektronik imzalı hâle geldi.';
                } elseif ($submission['otomatik_imza']) {
                    $bekleyen = (int) $submission['total'] - 1;
                    $message = 'Evrak onaya sunuldu ve ilk imzacı olduğunuz için imzanız otomatik atıldı. ' . $bekleyen . ' imzacının onayı bekleniyor.';
                } else {
                    $message = 'Evrak elektronik imza onayına sunuldu. ' . $submission['total'] . ' imzacının onayı bekleniyor. Süreç tamamlanana kadar içerik değiştirilemez.';
                }
                echo json_encode([
                    'status' => 'success',
                    'message' => $message,
                    'data' => $submission,
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-islak-imza-onaya-sun':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) throw new Exception('Geçersiz evrak ID.');
                $submission = $Model->submitForApproval($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'islak_imza');
                (new SystemLogModel())->logAction($currentUserId, 'Evrak Islak İmza Onayına Sunma', "Evrak ID: {$id} ıslak imza onayına sunuldu. İmzacılar: " . implode(', ', $submission['signers']), SystemLogModel::LEVEL_IMPORTANT);
                $siradaki = $Model->getNextPendingSigner($id);
                if ($siradaki) (new EvrakOnayBildirimService())->imzaSirasiGeldi($Model->getById($id), (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $submission['total']);
                echo json_encode(['status' => 'success', 'message' => 'Evrak ıslak imza onayına sunuldu. ' . $submission['total'] . ' yetkilinin onayı bekleniyor.', 'data' => $submission], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-mobil-imza-onaya-sun':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) throw new Exception('Geçersiz evrak ID.');
                $submission = $Model->submitForApproval($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'mobil_imza');
                (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza Onayına Sunma', "Evrak ID: {$id} mobil imza onayına sunuldu. İmzacılar: " . implode(', ', $submission['signers']), SystemLogModel::LEVEL_IMPORTANT);
                $siradaki = $Model->getNextPendingSigner($id);
                if ($siradaki) (new EvrakOnayBildirimService())->imzaSirasiGeldi($Model->getById($id), (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $submission['total']);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Evrak mobil imza onayına sunuldu. Sıradaki yetkili telefonundaki Turkcell Mobil İmza ile imzalayabilir.',
                    'data' => $submission,
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-islak-imza-onayla':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) throw new Exception('Geçersiz evrak ID.');
                $approval = $Model->approveWithWetSignature($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                $evrak = $Model->getById($id);
                $bildirim = new EvrakOnayBildirimService();
                if ($approval['completed']) $bildirim->onayTamamlandi($evrak);
                elseif ($siradaki = $Model->getNextPendingSigner($id)) $bildirim->imzaSirasiGeldi($evrak, (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $approval['total']);
                (new SystemLogModel())->logAction($currentUserId, 'Evrak Islak İmza Onayı', "Evrak ID: {$id} ıslak imza ile onaylandı ({$approval['approved']}/{$approval['total']}).", SystemLogModel::LEVEL_IMPORTANT);
                echo json_encode(['status' => 'success', 'message' => $approval['completed'] ? 'Tüm ıslak imza onayları tamamlandı.' : 'Islak imza onayınız kaydedildi; sıradaki yetkili bekleniyor.', 'data' => $approval], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-sil':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz evrak ID.");
                }

                $deleteDocument = $Model->getById($id);
                if (!$deleteDocument) {
                    throw new Exception('Evrak bulunamadı.');
                }
                if (($deleteDocument->onay_durumu ?? 'taslak') !== 'taslak') {
                    throw new Exception('Elektronik imza onay süreci başlamış evrak silinemez.');
                }
                if (!empty($deleteDocument->kep_mesaj_id) || (($deleteDocument->evrak_tipi ?? '') === 'gelen' && ($deleteDocument->kaynak_tipi ?? '') === 'kep')) {
                    throw new Exception('KEP ile gönderilmiş veya KEP\'ten alınmış evrak silinemez.');
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

            case 'evrak-pades-hazirla':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception('Geçersiz evrak ID.');
                }
                $prepared = (new PadesEImzaService())->prepare($id, $currentUserId);
                echo json_encode(['status' => 'success', 'data' => $prepared], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'evrak-pades-tamamla':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) throw new Exception('Geçersiz evrak ID.');
                $signedPdf = (string) ($_POST['signed_pdf'] ?? '');
                if ($signedPdf === '') throw new Exception('İmzalı PDF alınamadı.');
                $tempId = (string) ($_POST['temp_id'] ?? '');
                if ($tempId === '') throw new Exception('PAdES imza oturumu bulunamadı.');
                $approval = (new PadesEImzaService())->accept(
                    $id, $currentUserId, $tempId, $signedPdf, (string) ($_SERVER['REMOTE_ADDR'] ?? '')
                );
                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Evrak PAdES E-İmza Onayı',
                    "Evrak ID: {$id} doğrulanmış PAdES imzası ile onaylandı ({$approval['approved']}/{$approval['total']} imza).",
                    SystemLogModel::LEVEL_IMPORTANT
                );
                $autoKep = $afterPadesApproval($id, $approval);
                echo json_encode([
                    'status' => 'success',
                    'message' => $approval['completed']
                        ? 'Tüm PAdES imzaları doğrulandı. Evrak elektronik imzalı olarak kilitlendi.'
                        : 'PAdES imzanız doğrulandı. Evrak diğer imzacıların imzasını bekliyor.',
                    'data' => array_merge($approval, ['auto_kep' => $autoKep]),
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-mobil-imza-baslat':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) throw new Exception('Geçersiz evrak ID.');
                try {
                    $started = (new MobilImzaPadesService())->start($id, $currentUserId);
                } catch (\RuntimeException $e) {
                    (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza Hatası', "Evrak ID: {$id} mobil imza isteği başlatılamadı: " . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
                    throw $e;
                } catch (\Throwable $e) {
                    error_log('evrak-mobil-imza-baslat: ' . $e->getMessage());
                    throw new Exception('Mobil imza isteği başlatılamadı.');
                }
                (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza İsteği', "Evrak ID: {$id} için {$started['msisdn']} hattına mobil imza isteği gönderildi.", SystemLogModel::LEVEL_IMPORTANT);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'İmza isteği telefonunuza gönderildi.',
                    'data' => $started,
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-mobil-imza-durum':
                $token = (string) ($_POST['token'] ?? '');
                try {
                    $result = (new MobilImzaPadesService())->poll($token, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                } catch (\RuntimeException $e) {
                    (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza Hatası', 'Mobil imza tamamlanamadı: ' . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
                    throw $e;
                } catch (\Throwable $e) {
                    error_log('evrak-mobil-imza-durum: ' . $e->getMessage());
                    throw new Exception('Mobil imza durumu sorgulanamadı.');
                }
                if (in_array($result['state'], ['iptal', 'zaman_asimi', 'hata'], true)) {
                    (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza Sonucu', 'Mobil imza tamamlanmadı: ' . $result['message'], SystemLogModel::LEVEL_INFO);
                }
                if ($result['state'] !== 'imzalandi') {
                    echo json_encode(['status' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $approval = $result['approval'];
                $id = (int) $result['evrak_id'];
                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Evrak Mobil İmza Onayı',
                    "Evrak ID: {$id} Turkcell mobil imza ile doğrulanmış PAdES imzası olarak onaylandı ({$approval['approved']}/{$approval['total']} imza).",
                    SystemLogModel::LEVEL_IMPORTANT
                );
                $autoKep = $afterPadesApproval($id, $approval);
                echo json_encode([
                    'status' => 'success',
                    'message' => $approval['completed']
                        ? 'Tüm imzalar doğrulandı. Evrak elektronik imzalı olarak kilitlendi.'
                        : 'Mobil imzanız doğrulandı. Evrak diğer imzacıların imzasını bekliyor.',
                    'data' => [
                        'state' => 'imzalandi',
                        'completed' => $approval['completed'],
                        'approved' => $approval['approved'],
                        'total' => $approval['total'],
                        'auto_kep' => $autoKep,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-mobil-imza-iptal':
                (new MobilImzaPadesService())->cancel((string) ($_POST['token'] ?? ''), $currentUserId);
                (new SystemLogModel())->logAction($currentUserId, 'Evrak Mobil İmza İptali', 'Bekleyen mobil imza isteği ekrandan iptal edildi.', SystemLogModel::LEVEL_INFO);
                echo json_encode(['status' => 'success', 'message' => 'Mobil imza isteği iptal edildi.'], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-e-imza-onayla':
                throw new Exception('Sanal onay kaldırıldı. Güncel e-imza uygulamasıyla PAdES imzası kullanınız.');

            case 'evrak-e-imza-iade':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception('Geçersiz evrak ID.');
                }
                $iadeEdilenEvrak = $Model->getById($id);
                if (!$iadeEdilenEvrak) {
                    throw new Exception('Evrak bulunamadı.');
                }
                $iade = $Model->rejectApproval($id, $currentUserId, (string) ($_POST['gerekce'] ?? ''));
                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Evrak E-İmza İadesi',
                    "Evrak ID: {$id} imzalanmadan düzeltilmek üzere iade edildi. Gerekçe: " . $iade['gerekce'],
                    SystemLogModel::LEVEL_IMPORTANT
                );
                (new EvrakOnayBildirimService())->evrakIadeEdildi(
                    $iadeEdilenEvrak,
                    (string) ($_SESSION['user_full_name'] ?? 'İmzacı'),
                    $iade['gerekce']
                );
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Evrak düzeltilmek üzere iade edildi. Evrakı hazırlayan kullanıcı bilgilendirildi.',
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-e-imza-geri-al':
                $id = $decryptId($_POST['id'] ?? '');
                if ($id <= 0) {
                    throw new Exception('Geçersiz evrak ID.');
                }
                $Model->revokeApproval($id, $currentUserId);
                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Evrak E-İmza Onayı Geri Alma',
                    "Evrak ID: {$id} için elektronik imza onayı geri alındı, evrak taslak durumuna döndürüldü.",
                    SystemLogModel::LEVEL_CRITICAL
                );
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Onay geri alındı. Evrak yeniden düzenlenebilir durumda.',
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'evrak-detay':
                $encryptedId = (string) ($_POST['id'] ?? '');
                $id = $decryptId($encryptedId);
                $evrak = $Model->getById($id);

                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                $imzaAkisi = $Model->getApprovalDetailMap([$id])[$id] ?? [];
                $imzaOzeti = $Model->getApprovalSummaryMap($currentUserId, [$id])[$id] ?? [];
                $evrak->imza_sirasi_bende = ($evrak->onay_durumu ?? '') === 'onay_bekliyor'
                    && !empty($imzaOzeti['sira_bende']);
                $evrak->imza_akisi = array_map(static function (array $imza): array {
                    return [
                        'sira' => (int) $imza['sira'],
                        'adi_soyadi' => (string) $imza['adi_soyadi'],
                        'unvan' => (string) $imza['imza_unvani'],
                        'durum' => (string) $imza['durum'],
                        'akisa_eklenme_tarihi' => $imza['olusturma_tarihi'],
                        'onay_tarihi' => $imza['onay_tarihi'],
                        'imza_turu' => (string) ($imza['imza_turu'] ?? ''),
                        'sertifika_ozne' => (string) ($imza['sertifika_ozne'] ?? ''),
                        'sertifika_veren' => (string) ($imza['sertifika_veren'] ?? ''),
                        'sertifika_seri_no' => (string) ($imza['sertifika_seri_no'] ?? ''),
                        'sertifika_parmak_izi' => (string) ($imza['sertifika_parmak_izi'] ?? ''),
                        'imza_dogrulama_durumu' => (string) ($imza['imza_dogrulama_durumu'] ?? ''),
                    ];
                }, $imzaAkisi);
                $evrak->id = $encryptedId;
                if (!empty($evrak->ilgili_evrak_id)) {
                    $evrak->ilgili_evrak_id = Security::encrypt($evrak->ilgili_evrak_id);
                }
                $storedSignerIds = json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: [];
                $evrak->imza_kullanici_ids = array_map(fn($signerId) => Security::encrypt((int) $signerId), $storedSignerIds);
                $evrak->dosyalar = array_map(static function ($attachment) {
                    return [
                        'id' => Security::encrypt((int) $attachment->id),
                        'type' => 'existing',
                        'name' => $attachment->dosya_adi,
                        'path' => $attachment->dosya_yolu,
                        'size' => (int) $attachment->dosya_boyutu,
                        'date' => $attachment->olusturma_tarihi,
                        'user' => $attachment->yukleyen_kullanici,
                    ];
                }, $Model->getAttachments($id));
                if (!empty($evrak->dosya_yolu)) {
                    $primaryPath = ltrim((string) $evrak->dosya_yolu, '/');
                    $primaryAlreadyListed = array_filter(
                        $evrak->dosyalar,
                        static fn(array $file): bool => ltrim((string) ($file['path'] ?? ''), '/') === $primaryPath
                    );
                    if ($primaryAlreadyListed === []) {
                        $primaryFullPath = dirname(__DIR__, 2) . '/' . $primaryPath;
                        array_unshift($evrak->dosyalar, [
                            'id' => null,
                            'type' => 'primary',
                            'name' => basename($primaryPath),
                            'path' => (string) $evrak->dosya_yolu,
                            'size' => is_file($primaryFullPath) ? (int) filesize($primaryFullPath) : 0,
                            'date' => $evrak->olusturma_tarihi ?? null,
                            'user' => 'Ana evrak dosyası',
                        ]);
                    }
                }
                echo json_encode(['status' => 'success', 'data' => $evrak]);
                break;

            case 'evrak-listesi':
                $result = $Model->ajaxList($_POST, (int) $_SESSION['firma_id'], $currentUserId);
                $ids = array_map(static fn(object $row): int => (int) $row->id, $result['rows']);
                $attachmentMap = $Model->getAttachmentMap($ids);
                $approvalMap = $Model->getApprovalSummaryMap($currentUserId, $ids);
                $approvalDetailMap = $Model->getApprovalDetailMap($ids);
                $latestKepEvidenceMap = (new KepDelilModel())->getLatestByEvrakIds($ids, (int) $_SESSION['firma_id']);
                $canSendKep = Gate::allows('evrak-takip/kep');
                $esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $dataRows = [];
                foreach ($result['rows'] as $offset => $evrak) {
                    $encryptedId = Security::encrypt((int) $evrak->id);
                    $type = (string) ($evrak->evrak_tipi ?? 'gelen');
                    $approvalStatus = (string) ($evrak->onay_durumu ?? 'taslak');
                    $approvalType = (string) ($evrak->onay_turu ?? 'eimza');
                    $approvalTypeLabel = match ($approvalType) {
                        'islak_imza' => 'ISLAK İMZA',
                        'mobil_imza' => 'MOBİL İMZA',
                        default => 'E-İMZA',
                    };
                    $approval = $approvalMap[(int) $evrak->id] ?? ['toplam' => 0, 'onaylanan' => 0, 'sira_bende' => false];
                    $myTurn = $approvalStatus === 'onay_bekliyor' && !empty($approval['sira_bende']);
                    $locked = $approvalStatus !== 'taslak';
                    $reply = $type === 'gelen' ? (!empty($evrak->cevap_verildi_mi) ? 'EVET' : 'BEKLEMEDE') : '-';
                    $isKep = ($evrak->kaynak_tipi ?? '') === 'kep' || !empty($evrak->kep_mesaj_id) || ($evrak->gonderim_kanali ?? '') === 'kep';
                    $typeHtml = $type === 'gelen'
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-down-arrow-alt"></i> GELEN</span>'
                        : ($isKep
                            ? '<span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="KEP ile gönderilen evrak"><i class="bx bx-mail-send"></i> GİDEN / KEP</span>'
                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-up-arrow-alt"></i> GİDEN</span>');
                    if (($evrak->kaynak_tipi ?? '') === 'uets' || !empty($evrak->uets_mesaj_id)) $typeHtml .= '<div class="mt-1"><span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 font-size-10"><i class="bx bx-envelope"></i> UETS</span></div>';
                    if ($type === 'gelen' && $isKep) $typeHtml .= '<div class="mt-1"><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 font-size-10"><i class="bx bx-mail-send"></i> KEP</span></div>';
                    $kepStatusHtml = '<span class="text-muted small">-</span>';
                    if ($type === 'giden' && $isKep && isset($latestKepEvidenceMap[(int) $evrak->id])) {
                        $latestEvidence = $latestKepEvidenceMap[(int) $evrak->id];
                        $evidenceStatus = match ((int) $latestEvidence['delil_tur_id']) {
                            10 => ['label' => 'KABUL EDİLDİ', 'class' => 'bg-success-subtle text-success border-success-subtle', 'icon' => 'bx-check-circle'],
                            20 => ['label' => 'KEPHS\'E AKTARILDI', 'class' => 'bg-info-subtle text-info border-info-subtle', 'icon' => 'bx-transfer'],
                            30 => ['label' => 'İLETİLDİ', 'class' => 'bg-primary-subtle text-primary border-primary-subtle', 'icon' => 'bx-send'],
                            40 => ['label' => 'TESLİM EDİLDİ', 'class' => 'bg-primary-subtle text-primary border-primary-subtle', 'icon' => 'bx-mail-send'],
                            41, 50 => ['label' => 'TESLİM EDİLEMEDİ', 'class' => 'bg-danger-subtle text-danger border-danger-subtle', 'icon' => 'bx-x-circle'],
                            60 => ['label' => 'OKUNDU', 'class' => 'bg-info-subtle text-info border-info-subtle', 'icon' => 'bx-envelope-open'],
                            70 => ['label' => 'İNDİRİLDİ', 'class' => 'bg-success-subtle text-success border-success-subtle', 'icon' => 'bx-download'],
                            default => ['label' => 'DELİL ALINDI', 'class' => 'bg-secondary-subtle text-secondary border-secondary-subtle', 'icon' => 'bx-shield-quarter'],
                        };
                        $evidenceTime = !empty($latestEvidence['olay_zamani'])
                            ? date('d.m.Y H:i', strtotime((string) $latestEvidence['olay_zamani']))
                            : '';
                        $kepStatusHtml = '<span class="badge ' . $evidenceStatus['class'] . ' border rounded-pill px-2 py-1 font-size-10 fw-semibold" title="Son KEP durumu' . ($evidenceTime !== '' ? ': ' . $esc($evidenceTime) : '') . '"><i class="bx ' . $evidenceStatus['icon'] . '"></i> ' . $evidenceStatus['label'] . '</span>';
                    }
                    $detailUrl = ($type === 'gelen' || $isKep) ? 'javascript:void(0);' : 'index?p=evrak-takip/giden-evrak&amp;id=' . $esc($encryptedId);
                    $detailClass = $isKep ? ' btn-kep-onizle' : ($type === 'gelen' ? ' evrak-duzenle' : '');
                    $subjectHtml = '<div class="d-flex flex-column"><a href="' . $detailUrl . '" class="fw-bold text-dark font-size-13 text-decoration-none evrak-konu-text' . $detailClass . '" data-id="' . $esc($encryptedId) . '">' . $esc($evrak->konu ?: '-') . '</a><span class="text-muted font-size-11"><i class="bx bx-hash"></i> ' . $esc($evrak->evrak_no ?: '-') . '</span></div>';
                    $personHtml = static function ($name, $personId, $kind, $lastDate, $encryptedId, $esc): string {
                        if (empty($name)) return '<span class="text-muted small">-</span>';
                        return '<div class="d-flex align-items-center"><span class="font-size-12 fw-semibold text-dark text-truncate" style="max-width:130px">' . $esc($name) . '</span><button type="button" class="btn btn-link text-primary p-0 ms-1 evrak-bildir-manuel" data-id="' . $esc($encryptedId) . '" data-personel-id="' . (int) $personId . '" data-type="' . $esc($kind) . '" data-last-notified="' . $esc($lastDate) . '" title="Bildirim ve Mail Gönder"><i class="bx bx-bell font-size-14"></i></button></div>';
                    };
                    $replyHtml = $reply === '-' ? '<span class="text-muted small">-</span>' : '<span class="badge ' . ($reply === 'EVET' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning') . ' border rounded-pill px-2 py-1 font-size-11 fw-semibold">' . $reply . '</span>';
                    $flowItems = '';
                    foreach ($approvalDetailMap[(int) $evrak->id] ?? [] as $signature) {
                        $flowItems .= '<li><strong>' . (int) $signature['sira'] . '. ' . $esc($signature['adi_soyadi']) . '</strong> — ' . ($signature['durum'] === 'onaylandi' ? 'İmzalandı' : 'Bekliyor') . '</li>';
                    }
                    $flowAttr = $flowItems !== '' ? ' tabindex="0" role="button" data-onay-akis="' . $esc('<ul class="list-unstyled mb-0">' . $flowItems . '</ul>') . '"' : '';
                    if ($type !== 'giden' || (int) $approval['toplam'] === 0) $approvalHtml = '<span class="text-muted small">-</span>';
                    elseif ($approvalStatus === 'onaylandi') $approvalHtml = '<span class="badge e-imza-rozet evrak-imza-onizle bg-success-subtle text-success border rounded-pill px-2 py-1 font-size-11 fw-semibold" data-id="' . $esc($encryptedId) . '" style="cursor:pointer" title="Evrakı ve imza tarihçesini görüntüle"' . $flowAttr . '><i class="bx bx-check-double"></i> ' . ($approvalType === 'eimza' ? 'ONAYLI' : $approvalTypeLabel . ' ONAYLI') . '</span>';
                    elseif ($approvalStatus === 'onay_bekliyor') $approvalHtml = '<span class="badge e-imza-rozet evrak-imza-onizle ' . ($myTurn ? 'bg-warning text-dark' : 'bg-warning-subtle text-warning border') . ' rounded-pill px-2 py-1 font-size-11 fw-semibold" data-id="' . $esc($encryptedId) . '" style="cursor:pointer" title="' . $approvalTypeLabel . ' akışı — evrakı ve tarihçeyi görüntüle"' . $flowAttr . '><i class="bx bx-time-five"></i> ' . ($approvalType === 'eimza' ? '' : $approvalTypeLabel . ' · ') . ($myTurn ? 'İMZANIZDA' : 'ONAYDA') . ' ' . (int) $approval['onaylanan'] . '/' . (int) $approval['toplam'] . '</span>';
                    else $approvalHtml = '<span class="badge e-imza-rozet evrak-imza-onizle bg-secondary-subtle text-secondary border rounded-pill px-2 py-1 font-size-11 fw-semibold" data-id="' . $esc($encryptedId) . '" style="cursor:pointer" title="Evrakı ve imza tarihçesini görüntüle"' . $flowAttr . '>TASLAK</span>';

                    $files = $attachmentMap[(int) $evrak->id] ?? [];
                    if (!empty($evrak->dosya_yolu) && !array_filter($files, static fn(object $file): bool => ltrim((string) $file->dosya_yolu, '/') === ltrim((string) $evrak->dosya_yolu, '/'))) array_unshift($files, (object) ['dosya_adi' => basename((string) $evrak->dosya_yolu), 'dosya_yolu' => $evrak->dosya_yolu]);
                    $hasUpperDocument = $type === 'giden' && empty($evrak->ust_yazi_gerekli_degil);
                    $fileHtml = '<span class="text-muted small">-</span>';
                    if ($files || $hasUpperDocument) {
                        $items = '';
                        if ($hasUpperDocument) $items .= '<li><button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-pdf-goruntule" data-id="' . $esc($encryptedId) . '"><i class="bx bx-file-blank text-danger"></i><span class="text-truncate">Üst Yazı.pdf</span></button></li>';
                        foreach ($files as $index => $file) $items .= '<li><a class="dropdown-item d-flex align-items-center gap-2" href="' . $esc($file->dosya_yolu) . '" target="_blank"><i class="bx bx-file"></i><span class="text-truncate">' . ($index + 1) . '. ' . $esc($file->dosya_adi) . '</span></a></li>';
                        $fileHtml = '<div class="dropdown d-inline-block"><button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-1 font-size-11 fw-semibold dropdown-toggle" data-bs-toggle="dropdown"><i class="bx bx-paperclip"></i> ' . (count($files) + ($hasUpperDocument ? 1 : 0)) . ' DOSYA</button><ul class="dropdown-menu dropdown-menu-end evrak-dosya-menu shadow-lg border-0">' . $items . '</ul></div>';
                    }
                    $actionItems = '';
                    if ($isKep) {
                        if (!empty($evrak->dosya_yolu)) {
                            $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-pdf-goruntule" data-id="' . $esc($encryptedId) . '" title="PDF Önizleme"><i class="bx bx-file-blank text-info"></i><span>PDF Önizleme</span></button>';
                        }
                        $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-kep-onizle" data-id="' . $esc($encryptedId) . '" title="KEP İletisi ve Delil Önizleme"><i class="bx bx-mail-send text-primary"></i><span>KEP İletisi ve Delil Önizleme</span></button>';
                    } elseif ($type === 'giden') {
                        $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-pdf-goruntule" data-id="' . $esc($encryptedId) . '" title="PDF Önizleme"><i class="bx bx-file-blank text-info"></i><span>PDF Önizleme</span></button>';
                        $actionItems .= '<a href="index?p=evrak-takip/giden-evrak&amp;id=' . $esc($encryptedId) . '" class="dropdown-item d-flex align-items-center gap-2" title="' . ($locked ? 'Evrakı Görüntüle' : 'Düzenle') . '"><i class="bx ' . ($locked ? 'bx-show text-secondary' : 'bx-edit-alt text-warning') . '"></i><span>' . ($locked ? 'Evrakı Görüntüle' : 'Düzenle') . '</span></a>';
                        if ($canSendKep && $approvalStatus === 'onaylandi' && empty($evrak->kep_mesaj_id)) {
                            $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-approved-kep-send" data-id="' . $esc($encryptedId) . '" data-subject="' . $esc($evrak->konu ?: 'Resmî Yazı') . '" title="Onaylı Evrakı KEP ile Gönder"><i class="bx bx-mail-send text-primary"></i><span>Onaylı Evrakı KEP ile Gönder</span></button>';
                        }
                    } else {
                        $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-duzenle" data-id="' . $esc($encryptedId) . '" title="Düzenle"><i class="bx bx-edit-alt text-primary"></i><span>Düzenle</span></button>';
                    }
                    if (!empty($evrak->uets_mesaj_id)) $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-uets-deliller" data-uets-id="' . $esc($evrak->uets_mesaj_id) . '" title="UETS Delillerini Görüntüle"><i class="bx bx-shield-quarter text-info"></i><span>UETS Delillerini Görüntüle</span></button>';
                    if ($myTurn) {
                        $signActionLabel = match ($approvalType) {
                            'islak_imza' => 'Islak İmza ile Onayla',
                            'mobil_imza' => 'Mobil İmza ile Onayla',
                            default => 'E-İmza ile İmzala',
                        };
                        $actionItems .= '<a href="index?p=evrak-takip/giden-evrak&amp;id=' . $esc($encryptedId) . '&amp;islem=imzala" class="dropdown-item d-flex align-items-center gap-2" title="' . $signActionLabel . '"><i class="bx bx-check text-success"></i><span>' . $signActionLabel . '</span></a><button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-e-imza-iade" data-id="' . $esc($encryptedId) . '" title="Düzeltilmek Üzere İade Et"><i class="bx bx-undo text-warning"></i><span>Düzeltilmek Üzere İade Et</span></button>';
                    }
                    if ($locked && $approvalStatus === 'onay_bekliyor' && (int) ($evrak->olusturan_kullanici_id ?? 0) === $currentUserId) $actionItems .= '<button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-e-imza-geri-al" data-id="' . $esc($encryptedId) . '" title="Evrakı Üzerime Geri Al"><i class="bx bx-revision text-info"></i><span>Evrakı Üzerime Geri Al</span></button>';
                    if (!$locked && (!$isKep || ($type === 'giden' && empty($evrak->kep_mesaj_id)))) $actionItems .= '<div class="dropdown-divider"></div><button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger evrak-sil cm-danger" data-id="' . $esc($encryptedId) . '" title="Sil"><i class="bx bx-trash"></i><span>Sil</span></button>';
                    $actions = '<div class="action-btn-group d-flex align-items-center justify-content-center"><div class="dropdown"><button type="button" class="btn btn-sm btn-outline-secondary bg-white dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="İşlemler"><i class="bx bx-cog me-1"></i> İşlemler</button><div class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-1">' . $actionItems . '</div></div></div>';
                    $createdTime = !empty($evrak->olusturma_tarihi) ? date('H:i', strtotime((string) $evrak->olusturma_tarihi)) : '-';
                    $operatorName = trim((string) ($evrak->islem_yapan_adi ?? ''));
                    if ($operatorName === '' && (($evrak->kaynak_tipi ?? '') === 'uets' || !empty($evrak->uets_mesaj_id))) {
                        $operatorName = 'Sistem / UETS';
                    }
                    $operatorHtml = $operatorName !== ''
                        ? '<div class="d-flex align-items-center gap-2"><span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary flex-shrink-0" style="width:26px;height:26px"><i class="bx bx-user"></i></span><span class="fw-semibold text-dark font-size-12">' . $esc($operatorName) . '</span></div>'
                        : '<span class="text-muted small">-</span>';
                    $dataRows[] = [
                        '<span class="fw-bold text-muted">' . ($result['start'] + $offset + 1) . '</span>', $typeHtml, $kepStatusHtml,
                        '<div class="d-flex flex-column"><span class="fw-semibold text-dark font-size-12">' . date('d.m.Y', strtotime((string) $evrak->tarih)) . '</span><span class="text-muted font-size-11">' . $createdTime . '</span></div>',
                        $subjectHtml, '<span class="fw-semibold text-dark font-size-12">' . $esc($evrak->kurum_adi ?: '-') . '</span>', $operatorHtml,
                        $personHtml($evrak->personel_adi, $evrak->personel_id, 'personel', $evrak->son_bildirim_tarihi_personel ?? '', $encryptedId, $esc),
                        $personHtml($evrak->ilgili_personel_adi, $evrak->ilgili_personel_id, 'ilgili', $evrak->son_bildirim_tarihi_ilgili ?? '', $encryptedId, $esc),
                        $replyHtml, $approvalHtml, $fileHtml, $actions,
                        'DT_RowAttr' => ['data-id' => $encryptedId, 'data-imza-bekliyor' => $myTurn ? '1' : '0', 'data-tip' => $type, 'data-cevap' => $reply],
                    ];
                }
                echo json_encode(['draw' => $result['draw'], 'recordsTotal' => $result['recordsTotal'], 'recordsFiltered' => $result['recordsFiltered'], 'data' => $dataRows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                        $Bildirim->createNotificationWithPush(
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

            case 'evrak-ai-taslak-olustur':
                $file = $_FILES['dosya'] ?? $_FILES['gelen_evrak'] ?? [];
                $talimat = (string) ($_POST['talimat'] ?? '');
                $draft = (new \App\Service\EvrakAiTaslakService())->create($file, $talimat);
                echo json_encode(['status' => 'success', 'data' => $draft], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'evrak-ai-metin-duzenle':
                $metin = (string) ($_POST['metin'] ?? $_POST['selected_text'] ?? '');
                $talimat = (string) ($_POST['talimat'] ?? $_POST['instruction'] ?? '');
                $context = (string) ($_POST['document_context'] ?? '');
                $duzenlenmis = (new \App\Service\EvrakAiTaslakService())->reviseSelection($metin, $talimat, $context);
                echo json_encode(['status' => 'success', 'duzenlenmis_metin' => $duzenlenmis, 'data' => ['html' => $duzenlenmis]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'icra-listesi':
            case 'icra-ust-yazi-listesi':
                $personelId = (int) ($_POST['personel_id'] ?? 0);
                $icraUstYaziService = new \App\Service\IcraUstYaziService();
                echo json_encode(['status' => 'success', 'data' => $icraUstYaziService->personelIcralari($personelId)], JSON_UNESCAPED_UNICODE);
                break;

            case 'taslak':
            case 'icra-ust-yazi-olustur':
                $icraIdRaw = (string) ($_POST['icra_id'] ?? '');
                $icraId = (int) \App\Helper\Security::decrypt($icraIdRaw);
                if ($icraId < 1 && is_numeric($icraIdRaw)) {
                    $icraId = (int) $icraIdRaw;
                }
                $icraUstYaziService = new \App\Service\IcraUstYaziService();
                $draft = $icraUstYaziService->build($icraId);
                echo json_encode(['status' => 'success', 'data' => $draft], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'uets-ayarlari-getir':
                $firmaId = (int) $_SESSION['firma_id'];
                $uetsSettingsModel = new UetsSettingsModel();
                $uetsSettings = $uetsSettingsModel->getSettings($firmaId);
                $hasApiKey = !empty($uetsSettings['api_key_decrypted']);
                $maskedKey = '';
                if ($hasApiKey) {
                    $raw = $uetsSettings['api_key_decrypted'];
                    $maskedKey = mb_substr($raw, 0, 4) . '••••••••' . mb_substr($raw, -4);
                }
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'uets_adresi' => $uetsSettings['uets_adresi'] ?? '',
                        'api_key_masked' => $maskedKey,
                        'has_api_key' => $hasApiKey,
                        'api_environment' => $uetsSettings['api_environment'] ?? 'test',
                        'otomatik_senkronizasyon' => (int) ($uetsSettings['otomatik_senkronizasyon'] ?? 1),
                        'bakiye' => $uetsSettings['bakiye'] ?? null,
                        'son_gelen_sync_zamani' => !empty($uetsSettings['son_gelen_sync_zamani']) ? date('d.m.Y H:i', strtotime($uetsSettings['son_gelen_sync_zamani'])) : null,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-ayarlari-kaydet':
                $firmaId = (int) $_SESSION['firma_id'];
                $uetsSettingsModel = new UetsSettingsModel();
                $uetsAdresi = trim((string) ($_POST['uets_adresi'] ?? ''));
                if ($uetsAdresi === '') {
                    throw new Exception('UETS adresi zorunludur.');
                }
                $saveData = [
                    'uets_adresi' => $uetsAdresi,
                    'api_environment' => in_array($_POST['api_environment'] ?? 'test', ['test', 'prod']) ? $_POST['api_environment'] : 'test',
                    'otomatik_senkronizasyon' => !empty($_POST['otomatik_senkronizasyon']) ? 1 : 0,
                ];
                if (!empty($_POST['api_key'])) {
                    $saveData['api_key'] = trim((string) $_POST['api_key']);
                }
                $saved = $uetsSettingsModel->saveSettings($firmaId, $saveData);
                if (!$saved) {
                    throw new Exception('UETS ayarları kaydedilemedi.');
                }
                echo json_encode(['status' => 'success', 'message' => 'UETS ayarları başarıyla kaydedildi.'], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-baglanti-test':
                $firmaId = (int) $_SESSION['firma_id'];
                $uetsSettings = (new UetsSettingsModel())->getSettings($firmaId);
                $apiKeyToTest = !empty($_POST['api_key']) ? trim((string) $_POST['api_key']) : ($uetsSettings['api_key_decrypted'] ?? '');
                $envToTest = !empty($_POST['api_environment']) ? $_POST['api_environment'] : ($uetsSettings['api_environment'] ?? 'prod');
                if (empty($apiKeyToTest)) {
                    throw new Exception('Test edilecek API anahtarı girilmedi.');
                }
                $client = new UetsClientService($apiKeyToTest, $envToTest);
                $testRes = $client->testConnection();
                $envLabel = ($envToTest === 'prod') ? 'Canlı Ortam' : 'Test Ortamı';

                if ($testRes['success']) {
                    $msg = "UETS ({$envLabel}) bağlantısı başarılı! Toplam: {$testRes['total_count']}, Okunmamış: {$testRes['unread_count']}";
                } else {
                    $detailMsg = !empty($testRes['message']) ? ': ' . $testRes['message'] : '';
                    $msg = "Bağlantı kurulamadı (HTTP {$testRes['status_code']}{$detailMsg})";
                    if ($testRes['status_code'] === 401 && $envToTest === 'test') {
                        $msg .= ". Girdiğiniz API anahtarı Canlı sisteme ait olabilir. Lütfen 'API Ortamı' alanından 'Canlı Ortam (api.etebligat.gov.tr)' seçeneğini seçip tekrar deneyiniz.";
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $testRes,
                    'message' => $msg,
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-bakiye-sorgula':
                $firmaId = (int) $_SESSION['firma_id'];
                $uetsSettingsModel = new UetsSettingsModel();
                $uetsSettings = $uetsSettingsModel->getSettings($firmaId);
                if (!$uetsSettings || empty($uetsSettings['api_key_decrypted'])) {
                    throw new Exception('UETS API anahtarı tanımlanmamış.');
                }
                $formattedAddress = UetsSettingsModel::formatUetsAddress($uetsSettings['uets_adresi'] ?? '');
                $client = new UetsClientService($uetsSettings['api_key_decrypted'], $uetsSettings['api_environment'] ?? 'prod');
                $bal = $client->getBalance($formattedAddress);
                if (isset($bal['balance'])) {
                    $uetsSettingsModel->updateBalance($firmaId, (float) $bal['balance']);
                }
                echo json_encode(['status' => 'success', 'data' => $bal], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-alici-ara':
                $firmaId = (int) $_SESSION['firma_id'];
                $query = trim((string) ($_POST['query'] ?? ''));
                if ($query === '') {
                    throw new Exception('Aranacak adres veya kimlik numarası giriniz.');
                }
                $rehberModel = new UetsRehberModel();
                $localHits = $rehberModel->searchLocal($firmaId, $query);
                if (!empty($localHits)) {
                    echo json_encode(['status' => 'success', 'data' => $localHits, 'source' => 'cache'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $uetsSettings = (new UetsSettingsModel())->getSettings($firmaId);
                if (!$uetsSettings || empty($uetsSettings['api_key_decrypted'])) {
                    echo json_encode(['status' => 'success', 'data' => [], 'message' => 'API ayarları bulunamadı.'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $client = new UetsClientService($uetsSettings['api_key_decrypted'], $uetsSettings['api_environment'] ?? 'test');
                $criteria = [];
                if (preg_match('/^\d{5}-\d{5}-\d{5}$/', $query)) {
                    $criteria['address'] = $query;
                } elseif (strlen($query) === 11 && ctype_digit($query)) {
                    $criteria['tcn'] = $query;
                } elseif (strlen($query) === 10 && ctype_digit($query)) {
                    $criteria['vgn'] = $query;
                } else {
                    $criteria['address'] = $query;
                }
                $searchRes = $client->searchRecipient($criteria);
                $results = [];
                if (!empty($searchRes['address'])) {
                    $rehberModel->saveOrUpdate($firmaId, $searchRes);
                    $results[] = $searchRes;
                } elseif (is_array($searchRes)) {
                    foreach ($searchRes as $item) {
                        if (is_array($item) && !empty($item['address'])) {
                            $rehberModel->saveOrUpdate($firmaId, $item);
                            $results[] = $item;
                        }
                    }
                }
                echo json_encode(['status' => 'success', 'data' => $results, 'source' => 'api'], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-senkronize-et':
                $firmaId = (int) $_SESSION['firma_id'];
                $syncService = new UetsSyncService();
                $syncRes = $syncService->previewIncoming($firmaId);
                echo json_encode($syncRes, JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-secilenleri-aktar':
                $firmaId = (int) $_SESSION['firma_id'];
                $encryptedIds = $_POST['selection_ids'] ?? [];
                if (!is_array($encryptedIds) || count($encryptedIds) < 1 || count($encryptedIds) > 100) {
                    throw new Exception('Aktarılacak 1 ile 100 arasında UETS evrakı seçiniz.');
                }
                $uetsMessageIds = [];
                foreach ($encryptedIds as $encryptedId) {
                    $decryptedId = trim((string) Security::decrypt((string) $encryptedId));
                    if ($decryptedId === '' || mb_strlen($decryptedId) > 64) {
                        throw new Exception('UETS evrak seçimi geçersiz. Listeyi yenileyip tekrar deneyiniz.');
                    }
                    $uetsMessageIds[] = $decryptedId;
                }
                $syncRes = (new UetsSyncService())->syncIncoming($firmaId, array_values(array_unique($uetsMessageIds)));
                echo json_encode($syncRes, JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-delil-listesi':
                $firmaId = (int) $_SESSION['firma_id'];
                $uetsMsgId = trim((string) ($_POST['uets_message_id'] ?? ''));
                $mesajIdRaw = (string) ($_POST['mesaj_id'] ?? '');
                $mesajModel = new UetsMesajModel();
                $mesaj = null;
                if ($uetsMsgId !== '') {
                    $mesaj = $mesajModel->getByUetsId($firmaId, $uetsMsgId);
                } elseif ($mesajIdRaw !== '') {
                    $localId = $decryptId($mesajIdRaw);
                    $mesaj = $mesajModel->getById($localId, $firmaId);
                }
                if (!$mesaj) {
                    throw new Exception('Tebligat kaydı bulunamadı.');
                }
                $evidenceModel = new UetsEvidenceModel();
                $evidences = $evidenceModel->getByUetsMesajId((int) $mesaj['id']);

                // Eğer yerelde delil yoksa canlı UETS'ten çekip kaydetmeyi dene
                if (empty($evidences)) {
                    $uetsSettings = (new UetsSettingsModel())->getSettings($firmaId);
                    if ($uetsSettings && !empty($uetsSettings['api_key_decrypted'])) {
                        try {
                            $client = new UetsClientService($uetsSettings['api_key_decrypted'], $uetsSettings['api_environment'] ?? 'test');
                            $liveEvids = $client->getEvidenceList($mesaj['uets_message_id']);
                            if (is_array($liveEvids)) {
                                foreach ($liveEvids as $ev) {
                                    if (!empty($ev['id'])) {
                                        $evidenceModel->upsertEvidence((int) $mesaj['id'], $ev);
                                    }
                                }
                                $evidences = $evidenceModel->getByUetsMesajId((int) $mesaj['id']);
                            }
                        } catch (\Throwable $e) {
                            error_log("Canlı delil çekme hatası: " . $e->getMessage());
                        }
                    }
                }
                echo json_encode(['status' => 'success', 'data' => $evidences, 'tebligat' => $mesaj], JSON_UNESCAPED_UNICODE);
                break;

            case 'uets-gonder':
                $firmaId = (int) $_SESSION['firma_id'];
                $evrakIdRaw = (string) ($_POST['evrak_id'] ?? '');
                $evrakId = $decryptId($evrakIdRaw);
                if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                    $evrakId = (int) $evrakIdRaw;
                }
                if ($evrakId <= 0) {
                    throw new Exception('Geçerli bir evrak seçilmedi.');
                }
                $recipientUets = trim((string) ($_POST['recipient_uets'] ?? ''));
                if ($recipientUets === '') {
                    throw new Exception('Alıcı UETS adresi zorunludur.');
                }
                $syncService = new UetsSyncService();
                $sendRes = $syncService->sendOutgoing($firmaId, $evrakId, [
                    'recipient_uets' => $recipientUets,
                    'barcode' => trim((string) ($_POST['barcode'] ?? '')),
                    'recipient_ref' => trim((string) ($_POST['recipient_ref'] ?? '')),
                ]);
                echo json_encode($sendRes, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-ayarlari-getir':
                $firmaId = (int) $_SESSION['firma_id'];
                $kepSettingsModel = new KepSettingsModel();
                $kepSettings = $kepSettingsModel->getSettings($firmaId, $currentUserId);
                $gelenOtomasyon = $kepSettingsModel->getGelenOtomasyon($firmaId);
                $hasSecrets = !empty($kepSettings['sifre_decrypted']);
                $maskedTc = '';
                if (!empty($kepSettings['yetkili_tcno_decrypted'])) {
                    $rawTc = $kepSettings['yetkili_tcno_decrypted'];
                    $maskedTc = strlen($rawTc) >= 11 ? substr($rawTc, 0, 3) . '*****' . substr($rawTc, -3) : '***';
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'kep_adresi' => $kepSettings['kep_adresi'] ?? '',
                        'yetkili_tcno' => $maskedTc,
                        'has_secrets' => $hasSecrets,
                        'credentials_owner' => 'current_user',
                        'wsdl_url' => $kepSettings['wsdl_url'] ?? KepClientService::DEFAULT_WSDL,
                        'ortam' => $kepSettings['ortam'] ?? 'prod',
                        'gelen_otomatik_aktar' => $gelenOtomasyon['otomatik_aktar'],
                        'gelen_okundu_otomatik' => $gelenOtomasyon['okundu_otomatik'],
                        'gelen_aktar_kullanici' => $gelenOtomasyon['aktar_kullanici'],
                        'gelen_aktar_baska_kullanici' => $gelenOtomasyon['otomatik_aktar'] && $gelenOtomasyon['aktar_user_id'] !== $currentUserId,
                        'bakiye_kontor' => $kepSettings['bakiye_kontor'] ?? null,
                        'bakiye_cins' => $kepSettings['bakiye_cins'] ?? 'Adet',
                        'son_bakiye_sorgu' => !empty($kepSettings['son_bakiye_sorgu']) ? date('d.m.Y H:i', strtotime($kepSettings['son_bakiye_sorgu'])) : null,
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-ayarlari-kaydet':
                $firmaId = (int) $_SESSION['firma_id'];
                $kepSettingsModel = new KepSettingsModel();
                $kepAdresi = trim((string) ($_POST['kep_adresi'] ?? ''));
                if ($kepAdresi === '' || !filter_var($kepAdresi, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Geçerli bir KEP adresi girilmelidir.');
                }

                $saveData = [
                    'kep_adresi' => $kepAdresi,
                    'wsdl_url' => trim((string) ($_POST['wsdl_url'] ?? KepClientService::DEFAULT_WSDL)),
                    'ortam' => in_array($_POST['ortam'] ?? 'prod', ['prod', 'test'], true) ? $_POST['ortam'] : 'prod',
                ];

                if (!empty($_POST['yetkili_tcno'])) {
                    $tc = trim((string) $_POST['yetkili_tcno']);
                    if (strlen($tc) !== 11 || !is_numeric($tc)) {
                        throw new Exception('Yetkili T.C. Kimlik Numarası 11 haneli olmalıdır.');
                    }
                    $saveData['yetkili_tcno'] = $tc;
                }
                if (!empty($_POST['parola'])) {
                    $saveData['parola'] = trim((string) $_POST['parola']);
                }
                if (!empty($_POST['sifre'])) {
                    $saveData['sifre'] = trim((string) $_POST['sifre']);
                }

                $currentSettings = $kepSettingsModel->getSettings($firmaId, $currentUserId);
                $hasCurrentCredentials = !empty($currentSettings['has_user_credentials']);
                if (!$hasCurrentCredentials && (empty($saveData['yetkili_tcno']) || empty($saveData['parola']) || empty($saveData['sifre']))) {
                    throw new Exception('Bu kullanıcı için T.C. No, KEP parolası ve web servis şifresi eksiksiz girilmelidir.');
                }

                $saved = $kepSettingsModel->saveSettings($firmaId, $saveData, $currentUserId);
                if (!$saved) {
                    throw new Exception('KEP ayarları kaydedilemedi.');
                }

                $gelenAktar = filter_var($_POST['gelen_otomatik_aktar'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $gelenOkundu = filter_var($_POST['gelen_okundu_otomatik'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $gelenHesapBana = filter_var($_POST['gelen_hesap_bana'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $kepSettingsModel->saveGelenOtomasyon($firmaId, $gelenAktar, $gelenOkundu, $currentUserId, $gelenHesapBana);

                echo json_encode(['status' => 'success', 'message' => 'KEP entegrasyon ayarları başarıyla kaydedildi.']);
                break;

            case 'kep-baglanti-test':
                $firmaId = (int) $_SESSION['firma_id'];
                $kepSettings = (new KepSettingsModel())->getSettings($firmaId, $currentUserId);
                $testData = [
                    'kep_adresi' => !empty($_POST['kep_adresi']) ? trim((string) $_POST['kep_adresi']) : ($kepSettings['kep_adresi'] ?? ''),
                    'yetkili_tcno' => !empty($_POST['yetkili_tcno']) ? trim((string) $_POST['yetkili_tcno']) : ($kepSettings['yetkili_tcno_decrypted'] ?? ''),
                    'parola' => !empty($_POST['parola']) ? trim((string) $_POST['parola']) : ($kepSettings['parola_decrypted'] ?? ''),
                    'sifre' => !empty($_POST['sifre']) ? trim((string) $_POST['sifre']) : ($kepSettings['sifre_decrypted'] ?? ''),
                    'wsdl_url' => !empty($_POST['wsdl_url']) ? trim((string) $_POST['wsdl_url']) : ($kepSettings['wsdl_url'] ?? KepClientService::DEFAULT_WSDL),
                    'ortam' => !empty($_POST['ortam']) ? $_POST['ortam'] : ($kepSettings['ortam'] ?? 'prod'),
                ];

                if (empty($testData['kep_adresi']) || empty($testData['yetkili_tcno']) || empty($testData['parola']) || empty($testData['sifre'])) {
                    throw new Exception('Test için KEP adresi, T.C. No, parola ve şifre girilmiş olmalıdır.');
                }

                $kepService = new KepService();
                $res = $kepService->testConnection($firmaId, $testData);
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-bakiye-sorgula':
                $firmaId = (int) $_SESSION['firma_id'];
                $kepService = new KepService();
                $res = $kepService->testConnection($firmaId);
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-alici-dogrula':
                $firmaId = (int) $_SESSION['firma_id'];
                $aliciKep = trim((string) ($_POST['alici_kep'] ?? $_POST['query'] ?? ''));
                if ($aliciKep === '') {
                    throw new Exception('Doğrulanacak KEP adresi girilmelidir.');
                }
                $kepService = new KepService();
                $res = $kepService->verifyRecipient($firmaId, $aliciKep);
                $saveToAddressBook = filter_var(
                    $_POST['adres_defterine_kaydet'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                if ($saveToAddressBook && ($res['status'] ?? '') === 'success' && !empty($res['exist']) && !empty($res['suitable'])) {
                    (new KepAdresDefteriModel())->saveVerified($firmaId, $aliciKep);
                    $res['adres_defterine_kaydedildi'] = true;
                }
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-adres-defteri-listele':
                $firmaId = (int) $_SESSION['firma_id'];
                echo json_encode([
                    'status' => 'success',
                    'data' => (new KepAdresDefteriModel())->listActive($firmaId),
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'eimza-kart-bilgisi':
                $kepService = new KepService();
                $pythonBin = $kepService->getPythonBinary();
                if ($pythonBin === null) {
                    echo json_encode([
                        'status' => 'info',
                        'message' => 'Sunucuda Python3 bulunamadı. Test ortamında imzalama yerleşik OpenSSL motoru ile otomatik gerçekleştirilir.',
                        'python_available' => false,
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $scriptPath = dirname(__DIR__, 2) . '/scripts/cades_token_signer.py';
                $output = [];
                $returnCode = 0;
                exec(escapeshellcmd($pythonBin) . ' ' . escapeshellarg($scriptPath) . ' --detect 2>&1', $output, $returnCode);
                $rawJson = implode("\n", $output);
                $cardInfo = json_decode($rawJson, true);
                if (!is_array($cardInfo)) {
                    $cardInfo = ['status' => 'error', 'message' => 'E-İmza cihazı algılanamadı.'];
                }
                echo json_encode($cardInfo, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-gonder':
                $firmaId = (int) $_SESSION['firma_id'];
                $evrakIdRaw = (string) ($_POST['evrak_id'] ?? '');
                $evrakId = $decryptId($evrakIdRaw);
                if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                    $evrakId = (int) $evrakIdRaw;
                }
                if ($evrakId <= 0) {
                    throw new Exception('Geçerli bir evrak seçilmedi.');
                }

                $evrak = $Model->getById($evrakId);
                if (!$evrak || ($evrak->evrak_tipi ?? '') !== 'giden') {
                    throw new Exception('Giden evrak bulunamadı.');
                }
                if (($evrak->onay_durumu ?? '') !== 'onaylandi') {
                    throw new Exception('Yalnızca e-imza onayı tamamlanmış giden evraklar KEP ile gönderilebilir.');
                }
                if (!empty($evrak->kep_mesaj_id) || (new KepMesajModel())->getByEvrakId($firmaId, $evrakId)) {
                    throw new Exception('Bu evrak daha önce KEP üzerinden gönderilmiş.');
                }

                $aliciKep = trim((string) ($_POST['alici_kep'] ?? ($evrak->kep_alicilar ?: $evrak->alici_kep)));
                if ($aliciKep === '') throw new Exception('Kayıtlı alıcı KEP adresi bulunamadı.');
                foreach (array_filter(array_map('trim', explode(';', $aliciKep))) as $address) {
                    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) throw new Exception('Kayıtlı alıcı KEP adresi geçersiz.');
                }

                $pin = trim((string) ($_POST['eimza_pin'] ?? $_POST['pin'] ?? ''));
                $signature = trim((string) ($_POST['signature'] ?? $_POST['imza_b64'] ?? ''));

                $kepService = new KepService();
                $sendRes = $kepService->sendOutgoingKep($firmaId, $evrakId, [
                    'alici_kep' => $aliciKep,
                    'paket_turu' => ($_POST['paket_turu'] ?? '') === 'EYazisma' ? 'EYazisma' : (string) ($evrak->kep_paket_turu ?? 'Standart'),
                    'konu' => (string) ($evrak->konu ?? ''),
                    'icerik' => (string) ($evrak->kep_mesaj_metni ?: $evrak->aciklama),
                    'eimza_pin' => $pin,
                    'imza_b64' => $signature,
                ]);

                echo json_encode($sendRes, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-mime-hazirla':
                $firmaId = (int) $_SESSION['firma_id'];
                $evrakIdRaw = (string) ($_POST['evrak_id'] ?? '');
                $evrakId = $decryptId($evrakIdRaw);
                if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                    $evrakId = (int) $evrakIdRaw;
                }

                $kepService = new KepService();
                if ($evrakId > 0) {
                    $res = $kepService->prepareOutgoingKepMime($firmaId, $evrakId, $_POST);
                } else {
                    $recipients = trim((string) ($_POST['kep_alicilar'] ?? ''));
                    $subject = trim((string) ($_POST['kep_konu'] ?? ''));
                    $content = (string) ($_POST['kep_icerik'] ?? '');
                    $detail = mb_substr(trim((string) ($_POST['kep_ileti_detayi'] ?? '')), 0, 500);
                    $messageType = in_array(($_POST['kep_ileti_tipi'] ?? ''), ['resmi', 'standart'], true) ? $_POST['kep_ileti_tipi'] : 'standart';
                    $packageType = ($_POST['kep_paket_turu'] ?? 'Standart') === 'EYazisma' ? 'EYazisma' : 'Standart';

                    $uploadedFiles = [];
                    $allowedExtensions = ['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx','odt','ods','zip','eyp'];
                    if (!empty($_FILES['kep_ekler']['name']) && is_array($_FILES['kep_ekler']['name'])) {
                        if (count($_FILES['kep_ekler']['name']) > 20) throw new Exception('Bir KEP iletisine en fazla 20 dosya eklenebilir.');
                        foreach ($_FILES['kep_ekler']['name'] as $index => $originalName) {
                            $error = (int) ($_FILES['kep_ekler']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                            if ($error === UPLOAD_ERR_NO_FILE) continue;
                            if ($error !== UPLOAD_ERR_OK) throw new Exception('KEP eki yüklenemedi: ' . basename((string) $originalName));
                            $tmpPath = (string) ($_FILES['kep_ekler']['tmp_name'][$index] ?? '');
                            $size = (int) ($_FILES['kep_ekler']['size'][$index] ?? 0);
                            $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
                            if (!is_uploaded_file($tmpPath) || $size <= 0 || !in_array($extension, $allowedExtensions, true)) throw new Exception('Geçersiz KEP eki: ' . basename((string) $originalName));
                            $uploadedFiles[] = ['path' => $tmpPath, 'name' => basename((string) $originalName), 'mime' => (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: 'application/octet-stream', 'size' => $size];
                        }
                    }

                    $res = $kepService->prepareStandaloneKepMime($firmaId, [
                        'alici_kep' => $recipients,
                        'konu' => $subject,
                        'icerik' => $content,
                        'ileti_detayi' => $detail,
                        'ileti_tipi' => $messageType,
                        'paket_turu' => $packageType,
                    ], $uploadedFiles);
                }
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-smime-sevk':
                $firmaId = (int) $_SESSION['firma_id'];
                $tempId = trim((string) ($_POST['temp_id'] ?? ''));
                $signature = trim((string) ($_POST['signature'] ?? $_POST['imza_b64'] ?? ''));
                if ($tempId === '' || $signature === '') {
                    throw new Exception('Taslak belirteci (temp_id) ve e-imza verisi (signature) zorunludur.');
                }

                $draft = $_SESSION['kep_draft_' . $tempId] ?? null;
                if (!$draft) {
                    $draftFile = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp/draft_' . $tempId . '.json';
                    $draftEvrakFile = dirname(__DIR__, 2) . '/uploads/evrak-takip/tmp/draft_evrak_' . $tempId . '.json';
                    if (file_exists($draftFile)) {
                        $draft = json_decode(file_get_contents($draftFile), true);
                    } elseif (file_exists($draftEvrakFile)) {
                        $draft = json_decode(file_get_contents($draftEvrakFile), true);
                    }
                }

                $kepService = new KepService();
                if (($draft['type'] ?? '') === 'evrak') {
                    $res = $kepService->completeOutgoingKepSend($firmaId, $tempId, $signature);
                } else {
                    $res = $kepService->completeStandaloneKepSend($firmaId, $tempId, $signature);
                    if (($res['status'] ?? '') === 'success' && !empty($draft['recipients'])) {
                        (new KepAdresDefteriModel())->markUsed($firmaId, $draft['recipients']);
                    }
                }
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-dogrudan-gonder':
                $firmaId = (int) $_SESSION['firma_id'];
                $recipients = trim((string) ($_POST['kep_alicilar'] ?? ''));
                $subject = trim((string) ($_POST['kep_konu'] ?? ''));
                $content = (string) ($_POST['kep_icerik'] ?? '');
                $detail = mb_substr(trim((string) ($_POST['kep_ileti_detayi'] ?? '')), 0, 500);
                $messageType = in_array(($_POST['kep_ileti_tipi'] ?? ''), ['resmi', 'standart'], true) ? $_POST['kep_ileti_tipi'] : 'standart';
                $packageType = ($_POST['kep_paket_turu'] ?? 'Standart') === 'EYazisma' ? 'EYazisma' : 'Standart';

                $uploadedFiles = [];
                $allowedExtensions = ['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx','odt','ods','zip','eyp'];
                if (!empty($_FILES['kep_ekler']['name']) && is_array($_FILES['kep_ekler']['name'])) {
                    if (count($_FILES['kep_ekler']['name']) > 20) throw new Exception('Bir KEP iletisine en fazla 20 dosya eklenebilir.');
                    foreach ($_FILES['kep_ekler']['name'] as $index => $originalName) {
                        $error = (int) ($_FILES['kep_ekler']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                        if ($error === UPLOAD_ERR_NO_FILE) continue;
                        if ($error !== UPLOAD_ERR_OK) throw new Exception('KEP eki yüklenemedi: ' . basename((string) $originalName));
                        $tmpPath = (string) ($_FILES['kep_ekler']['tmp_name'][$index] ?? '');
                        $size = (int) ($_FILES['kep_ekler']['size'][$index] ?? 0);
                        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
                        if (!is_uploaded_file($tmpPath) || $size <= 0 || !in_array($extension, $allowedExtensions, true)) throw new Exception('Geçersiz KEP eki: ' . basename((string) $originalName));
                        $uploadedFiles[] = ['path' => $tmpPath, 'name' => basename((string) $originalName), 'mime' => (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: 'application/octet-stream', 'size' => $size];
                    }
                }

                $pin = trim((string) ($_POST['eimza_pin'] ?? $_POST['pin'] ?? ''));
                $signature = trim((string) ($_POST['signature'] ?? $_POST['imza_b64'] ?? ''));

                $result = (new KepService())->sendStandaloneKep($firmaId, [
                    'alici_kep' => $recipients, 'konu' => $subject,
                    'icerik' => $content, 'ileti_detayi' => $detail,
                    'ileti_tipi' => $messageType, 'paket_turu' => $packageType,
                    'eimza_pin' => $pin,
                    'imza_b64' => $signature,
                ], $uploadedFiles);
                if (($result['status'] ?? '') === 'success') {
                    (new KepAdresDefteriModel())->markUsed(
                        $firmaId,
                        preg_split('/[;,\r\n]+/', $recipients) ?: []
                    );
                }
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-gelen-onizleme':
                $firmaId = (int) $_SESSION['firma_id'];
                $gun = (int) ($_POST['gun'] ?? 30);
                $res = (new KepService())->previewIncoming($firmaId, $gun);
                $autoInfo = (new KepSettingsModel())->getAutoSyncInfo($firmaId);
                $res['okundu_otomatik'] = (new KepSettingsModel())->getGelenOtomasyon($firmaId)['okundu_otomatik'];
                $res['son_senkron'] = !empty($autoInfo['son_senkron']) ? date('d.m.Y H:i', strtotime($autoInfo['son_senkron'])) : null;
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-gelen-aktar':
                $firmaId = (int) $_SESSION['firma_id'];
                $encryptedIds = $_POST['selection_ids'] ?? [];
                if (!is_array($encryptedIds) || count($encryptedIds) < 1 || count($encryptedIds) > 50) {
                    throw new Exception('Aktarılacak 1 ile 50 arasında KEP iletisi seçiniz.');
                }
                $selections = [];
                foreach ($encryptedIds as $encryptedId) {
                    $decrypted = trim((string) Security::decrypt((string) $encryptedId));
                    if ($decrypted === '' || mb_strlen($decrypted) > 160 || !preg_match('/^[^|]+\|\d+$/', $decrypted)) {
                        throw new Exception('KEP seçimi geçersiz. Listeyi yenileyip tekrar deneyiniz.');
                    }
                    $selections[] = $decrypted;
                }
                $alindiOnay = filter_var($_POST['alindi_onay'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $res = (new KepService())->syncIncoming($firmaId, $currentUserId, array_values(array_unique($selections)), $alindiOnay);
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-delil-listesi':
                $firmaId = (int) $_SESSION['firma_id'];
                $kepId = trim((string) ($_POST['kep_id'] ?? ''));
                $evrakIdRaw = (string) ($_POST['evrak_id'] ?? '');

                if ($kepId === '' && $evrakIdRaw !== '') {
                    $evrakId = $decryptId($evrakIdRaw);
                    if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                        $evrakId = (int) $evrakIdRaw;
                    }
                    if ($evrakId > 0) {
                        $mesaj = (new KepMesajModel())->getByEvrakId($firmaId, $evrakId);
                        if ($mesaj) {
                            $kepId = $mesaj['kep_id'];
                        }
                    }
                }

                if ($kepId === '') {
                    throw new Exception('KEP Takip Kimliği (KepId) belirtilmedi.');
                }

                $kepService = new KepService();
                $res = $kepService->syncEvidences($firmaId, $kepId);
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-detay-getir':
                $firmaId = (int) $_SESSION['firma_id'];
                $evrakIdRaw = (string) ($_POST['id'] ?? $_POST['evrak_id'] ?? '');
                $kepId = trim((string) ($_POST['kep_id'] ?? ''));

                $evrak = null;
                $evrakId = 0;
                if ($evrakIdRaw !== '') {
                    $evrakId = $decryptId($evrakIdRaw);
                    if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                        $evrakId = (int) $evrakIdRaw;
                    }
                    if ($evrakId > 0) {
                        $evrak = $Model->find($evrakId);
                    }
                }

                $kepMesajModel = new KepMesajModel();
                $mesaj = null;
                if ($evrakId > 0) {
                    $mesaj = $kepMesajModel->getByEvrakId($firmaId, $evrakId);
                }
                if (!$mesaj && $kepId !== '') {
                    $mesaj = $kepMesajModel->getByKepId($firmaId, $kepId);
                }
                if (!$mesaj && $evrak && !empty($evrak->kep_mesaj_id)) {
                    $mesaj = $kepMesajModel->getByKepId($firmaId, $evrak->kep_mesaj_id);
                }
                if ($mesaj && !$evrak && !empty($mesaj['evrak_id'])) {
                    $evrak = $Model->find((int) $mesaj['evrak_id']);
                }

                if (!$mesaj && !$evrak) {
                    throw new Exception('KEP iletisi veya evrak kaydı bulunamadı.');
                }

                // En güncel delilleri PTT KEP servisinden senkronize etmeyi dene
                $targetKepId = $mesaj['kep_id'] ?? $evrak->kep_mesaj_id ?? '';
                if ($targetKepId !== '') {
                    try {
                        $kepService = new KepService();
                        $syncRes = $kepService->syncEvidences($firmaId, $targetKepId);
                        if (!empty($syncRes['mesaj'])) {
                            $mesaj = $syncRes['mesaj'];
                        }
                    } catch (\Throwable $e) {
                        error_log('KEP live sync notice: ' . $e->getMessage());
                    }
                }

                // Delilleri veritabanından çek
                $delilModel = new KepDelilModel();
                $deliller = $mesaj ? $delilModel->getDelillerByMesajId((int) $mesaj['id']) : [];
                $formatliDeliller = [];
                foreach ($deliller as $delil) {
                    $turId = (int) ($delil['delil_tur_id'] ?? 0);
                    $turBilgisi = match ($turId) {
                        10 => ['ad' => 'Kabul Delili (Submission Acceptance)', 'badge' => 'bg-success-subtle text-success border-success-subtle', 'icon' => 'bx bx-check-circle'],
                        20 => ['ad' => 'KEPHS Aktarım Delili (Relay)', 'badge' => 'bg-info-subtle text-info border-info-subtle', 'icon' => 'bx bx-transfer'],
                        30 => ['ad' => 'Alıcı Sunucuya İletim (Relay To Recipient)', 'badge' => 'bg-primary-subtle text-primary border-primary-subtle', 'icon' => 'bx bx-send'],
                        40 => ['ad' => 'Alıcı Posta Kutusuna Teslim Delili (Delivery To Recipient)', 'badge' => 'bg-primary-subtle text-primary border-primary-subtle', 'icon' => 'bx bx-mail-send'],
                        50 => ['ad' => 'Teslim Edilemedi Delili (Non-Delivery)', 'badge' => 'bg-danger-subtle text-danger border-danger-subtle', 'icon' => 'bx bx-x-circle'],
                        60 => ['ad' => 'Alıcı Tarafından Açıldı / Okundu Delili (Retrieval)', 'badge' => 'bg-info-subtle text-info border-info-subtle', 'icon' => 'bx bx-envelope-open'],
                        70 => ['ad' => 'Alıcı Tarafından İndirildi (Download)', 'badge' => 'bg-success-subtle text-success border-success-subtle', 'icon' => 'bx bx-download'],
                        default => ['ad' => 'PTT Delili (' . $turId . ')', 'badge' => 'bg-secondary-subtle text-secondary border-secondary-subtle', 'icon' => 'bx bx-file'],
                    };

                    $hasXml = !empty($delil['delil_xml_yolu']) && file_exists(dirname(__DIR__, 2) . '/' . ltrim((string) $delil['delil_xml_yolu'], '/'));

                    $formatliDeliller[] = [
                        'id' => (int) $delil['id'],
                        'delil_id' => (string) $delil['delil_id'],
                        'delil_tur_id' => $turId,
                        'tur_adi' => $turBilgisi['ad'],
                        'badge' => $turBilgisi['badge'],
                        'icon' => $turBilgisi['icon'],
                        'aciklama' => (string) ($delil['delil_aciklama'] ?? ''),
                        'olay_zamani' => !empty($delil['olay_zamani']) ? date('d.m.Y H:i:s', strtotime((string) $delil['olay_zamani'])) : '-',
                        'kephs' => (string) ($delil['kephs'] ?: 'PTT KEPHS'),
                        'has_xml' => $hasXml,
                    ];
                }

                // Ekleri topla
                $ekListesi = [];
                if ($evrak && !empty($evrak->id)) {
                    $attachmentMap = $Model->getAttachmentMap([(int) $evrak->id]);
                    $evrakFiles = $attachmentMap[(int) $evrak->id] ?? [];

                    // Eğer evrak_takip_ekleri boşsa ve şifreli arşiv paketi varsa oradan tamamla (self-healing)
                    if (empty($evrakFiles) && !empty($mesaj['ham_paket_yolu'])) {
                        $encPath = dirname(__DIR__, 2) . '/' . ltrim((string) $mesaj['ham_paket_yolu'], '/');
                        if (is_file($encPath)) {
                            try {
                                $rawDec = Security::decryptFile(file_get_contents($encPath));
                                $pkg = json_decode((string) $rawDec, true);
                                if (!empty($pkg['files']) && is_array($pkg['files'])) {
                                    $attDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/ekler/';
                                    if (!is_dir($attDir)) {
                                        @mkdir($attDir, 0777, true);
                                    }
                                    foreach ($pkg['files'] as $pIdx => $pf) {
                                        $pName = (string) ($pf['name'] ?? 'belge.pdf');
                                        $pExt = strtolower(pathinfo($pName, PATHINFO_EXTENSION));
                                        $storedName = 'ek_' . $evrak->id . '_' . bin2hex(random_bytes(8)) . ($pExt !== '' ? '.' . $pExt : '');
                                        $storedFullPath = $attDir . $storedName;
                                        if (!empty($pf['content'])) {
                                            file_put_contents($storedFullPath, base64_decode($pf['content']));
                                            $Model->saveAttachment((int) $evrak->id, [
                                                'dosya_adi' => $pName,
                                                'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName,
                                                'mime_tipi' => (string) ($pf['mime'] ?? 'application/octet-stream'),
                                                'dosya_boyutu' => (int) ($pf['size'] ?? strlen(base64_decode($pf['content']))),
                                                'sira' => $pIdx + 1,
                                            ]);
                                        }
                                    }
                                    $attachmentMap = $Model->getAttachmentMap([(int) $evrak->id]);
                                    $evrakFiles = $attachmentMap[(int) $evrak->id] ?? [];
                                }
                            } catch (\Throwable $e) { /* Hata durumunda sessizce devam et */ }
                        }
                    }

                    if (!empty($evrak->dosya_yolu) && !array_filter($evrakFiles, static fn(object $f): bool => ltrim((string) $f->dosya_yolu, '/') === ltrim((string) $evrak->dosya_yolu, '/'))) {
                        array_unshift($evrakFiles, (object) [
                            'dosya_adi' => basename((string) $evrak->dosya_yolu),
                            'dosya_yolu' => $evrak->dosya_yolu,
                        ]);
                    }
                    foreach ($evrakFiles as $ef) {
                        $fullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $ef->dosya_yolu, '/');
                        $ekListesi[] = [
                            'dosya_adi' => (string) $ef->dosya_adi,
                            'dosya_yolu' => (string) $ef->dosya_yolu,
                            'boyut' => is_file($fullPath) ? (int) filesize($fullPath) : (int) ($ef->dosya_boyutu ?? 0),
                        ];
                    }
                }

                // Ham içerik
                $icerik = '';
                if ($mesaj && !empty($mesaj['icerik'])) {
                    $icerik = (string) $mesaj['icerik'];
                } elseif ($evrak && !empty($evrak->kep_mesaj_metni)) {
                    $icerik = (string) $evrak->kep_mesaj_metni;
                } elseif ($evrak && !empty($evrak->aciklama)) {
                    $icerik = (string) $evrak->aciklama;
                }

                // Alıcılar
                $rawAlicilar = $mesaj['alici_kep'] ?? $evrak->kep_alicilar ?? $evrak->alici_kep ?? '';
                $aliciListesi = array_values(array_filter(array_map('trim', preg_split('/[;,\n\r]+/', (string) $rawAlicilar))));

                // Durum rozeti
                $durum = (string) ($mesaj['durum'] ?? $evrak->islem_durumu ?? 'gonderildi');
                $durumRozeti = match ($durum) {
                    'gonderildi' => ['ad' => 'KEP İletisi Sevk Edildi', 'badge' => 'bg-primary-subtle text-primary border-primary-subtle'],
                    'teslim_edildi' => ['ad' => 'Alıcıya Teslim Edildi', 'badge' => 'bg-success-subtle text-success border-success-subtle'],
                    'okundu' => ['ad' => 'Alıcı Tarafından Açıldı', 'badge' => 'bg-info-subtle text-info border-info-subtle'],
                    'hata' => ['ad' => 'Gönderim Hatası', 'badge' => 'bg-danger-subtle text-danger border-danger-subtle'],
                    default => ['ad' => ucfirst($durum), 'badge' => 'bg-secondary-subtle text-secondary border-secondary-subtle'],
                };

                if (!empty($formatliDeliller)) {
                    $sonDelil = end($formatliDeliller);
                    if ($sonDelil['delil_tur_id'] === 60) {
                        $durumRozeti = ['ad' => 'Alıcı Tarafından Okundu / Açıldı', 'badge' => 'bg-info text-white'];
                    } elseif ($sonDelil['delil_tur_id'] === 40) {
                        $durumRozeti = ['ad' => 'Alıcı Kutusuna Teslim Edildi', 'badge' => 'bg-success text-white'];
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'evrak_id' => $evrak ? (int) $evrak->id : null,
                        'encrypted_id' => $evrak ? Security::encrypt((int) $evrak->id) : '',
                        'evrak_no' => $evrak ? (string) ($evrak->evrak_no ?: '-') : '-',
                        'kep_id' => (string) ($mesaj['kep_id'] ?? $evrak->kep_mesaj_id ?? '-'),
                        'gonderen_kep' => (string) ($mesaj['gonderen_kep'] ?? '-'),
                        'alici_kep' => (string) ($mesaj['alici_kep'] ?? $rawAlicilar),
                        'alici_unvan' => (string) ($mesaj['alici_unvan'] ?? $evrak->kurum_adi ?? ''),
                        'alici_listesi' => $aliciListesi,
                        'konu' => (string) ($mesaj['konu'] ?? $evrak->konu ?? '-'),
                        'icerik' => $icerik,
                        'paket_turu' => (string) ($mesaj['paket_turu'] ?? $evrak->kep_paket_turu ?? 'Standart'),
                        'ileti_tipi' => (string) ($evrak->kep_ileti_tipi ?? 'standart'),
                        'durum' => $durum,
                        'durum_rozeti' => $durumRozeti,
                        'delil_sayisi' => count($formatliDeliller),
                        'son_delil_durumu' => (string) ($mesaj['son_delil_durumu'] ?? ''),
                        'tarih' => !empty($mesaj['created_at']) ? date('d.m.Y H:i', strtotime((string) $mesaj['created_at'])) : (!empty($evrak->olusturma_tarihi) ? date('d.m.Y H:i', strtotime((string) $evrak->olusturma_tarihi)) : '-'),
                        'ekler' => $ekListesi,
                        'deliller' => $formatliDeliller,
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'kep-delil-indir':
                $firmaId = (int) $_SESSION['firma_id'];
                $delilId = trim((string) ($_GET['delil_id'] ?? $_POST['delil_id'] ?? ''));
                if ($delilId === '') {
                    throw new Exception('Delil kimliği belirtilmedi.');
                }
                $kepService = new KepService();
                $res = $kepService->downloadEvidenceXml($firmaId, $delilId);

                if (isset($_GET['download']) || ($_POST['mode'] ?? '') === 'download' || isset($_GET['delil_id'])) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    header('Content-Type: application/xml; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $res['filename'] . '"');
                    header('Content-Length: ' . strlen((string) $res['xml']));
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    echo $res['xml'];
                    exit;
                }

                echo json_encode(['status' => 'success', 'data' => $res], JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
