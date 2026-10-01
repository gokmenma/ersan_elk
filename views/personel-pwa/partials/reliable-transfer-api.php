<?php
/** Included inside api.php's authenticated try block. */
use App\Model\PwaTransferModel;
use App\Model\IhbarModel;
use App\Model\KacakKontrolModel;
use App\Service\PwaChunkUploadService;

$transferActions = ['pwaTransferResolve', 'pwaTransferIdentity', 'pwaTransferPhoto', 'pwaVideoStart', 'pwaVideoStatus', 'pwaVideoChunk', 'pwaVideoComplete'];
$reliableMain = !empty($_POST['reliable_transfer']) && in_array($action, ['saveKacakBildirim', 'updateKacakBildirim', 'createIhbar', 'updateIhbar'], true);
if (!$reliableMain && !in_array($action, $transferActions, true)) return;

$transferFirma = (int) $_SESSION['firma_id'];
$transferPersonel = (int) $personel_id;
$accountKey = hash('sha256', 'pwa:' . $transferFirma . ':' . $transferPersonel);
if ($action === 'pwaTransferIdentity') response(true, ['account_key' => $accountKey]);
if (!hash_equals($accountKey, (string) ($_POST['account_key'] ?? ''))) {
    http_response_code(403);
    response(false, ['transfer_error' => 'account'], 'Gönderim hesabı değişmiş. Kaydı oluşturan hesapla giriş yapın.');
}

if ($action === 'pwaTransferResolve') {
    $legacy = (new KacakKontrolModel())->findByClientUuid((string) ($_POST['client_uuid'] ?? ''));
    if (!$legacy || (int) $legacy['firma_id'] !== $transferFirma || (int) $legacy['bildiren_personel_id'] !== $transferPersonel) {
        http_response_code(403);
        response(false, ['transfer_error' => 'permission'], 'Eski kaydın hesabı doğrulanamadı.');
    }
    // Register a receipt for already-uploaded legacy records, without recreating them.
    $legacyTransfer = new PwaTransferModel($transferFirma, $transferPersonel);
    $legacyTransfer->begin((string) ($_POST['operation_key'] ?? ''), 'saveKacakBildirim');
    $legacyToken = \App\Helper\Security::encrypt((int) $legacy['id']);
    $legacyTransfer->finish(['success' => true, 'data' => ['target_token' => $legacyToken], 'message' => 'Eski kayıt doğrulandı.']);
    response(true, ['target_token' => $legacyToken]);
}

if ($reliableMain) {
    if ($action === 'saveKacakBildirim') {
        $legacy = (new KacakKontrolModel())->findByClientUuid((string) ($_POST['client_uuid'] ?? ''), true);
        if ($legacy && ((int) $legacy['firma_id'] !== $transferFirma || (int) $legacy['bildiren_personel_id'] !== $transferPersonel || !empty($legacy['silinme_tarihi']))) {
            http_response_code(403);
            response(false, ['transfer_error' => 'permission'], 'Kayıt hesabı veya aktiflik durumu doğrulanamadı.');
        }
    }
    $GLOBALS['pwaTransfer'] = new PwaTransferModel($transferFirma, $transferPersonel);
    $cached = $GLOBALS['pwaTransfer']->begin((string) ($_POST['operation_key'] ?? ''), $action);
    if ($cached) {
        $GLOBALS['pwaTransfer']->getDb()->rollBack();
        unset($GLOBALS['pwaTransfer']);
        response($cached['success'], $cached['data'], $cached['message']);
    }
    if (in_array($action, ['updateKacakBildirim', 'updateIhbar'], true)) {
        $GLOBALS['pwaTransfer']->lockRecord($action === 'updateIhbar' ? 'ihbar' : 'kacak', (int) \App\Helper\Security::decrypt((string) ($_POST['edit_token'] ?? '')));
    }
    return; // Existing validation and business rules remain authoritative.
}

$kind = (string) ($_POST['kind'] ?? '');
if ($kind === 'ihbar' && ($personel->personel_tipi ?? '') === 'kaski_kacak') {
    http_response_code(403);
    response(false, ['transfer_error' => 'permission'], 'Bu modül için yetkiniz yok.');
}
if (!in_array($kind, ['ihbar', 'kacak'], true)) throw new RuntimeException('Geçersiz bildirim türü.');
$id = (int) \App\Helper\Security::decrypt((string) ($_POST['target_token'] ?? ''));
$model = $kind === 'ihbar' ? new IhbarModel() : new KacakKontrolModel();
$record = $kind === 'ihbar' ? $model->getById($id) : $model->getRecord($id);
$record = $record ? (array) $record : [];
if (!$record || (int) ($record['firma_id'] ?? 0) !== $transferFirma || (int) ($record['bildiren_personel_id'] ?? 0) !== $transferPersonel
    || ($kind === 'kacak' && stripos($personel->departman ?? '', 'Kaçak') === false)) {
    http_response_code(403);
    response(false, ['transfer_error' => 'permission'], 'Bu kayda dosya ekleme yetkiniz yok veya kayıt artık düzenlenemiyor.');
}

$mainAction = (string) ($_POST['main_action'] ?? '');
if (($kind === 'ihbar') !== str_contains($mainAction, 'Ihbar') || !in_array($mainAction, ['saveKacakBildirim', 'updateKacakBildirim', 'createIhbar', 'updateIhbar'], true)) throw new RuntimeException('Geçersiz ana işlem.');
$mainReceipt = (new PwaTransferModel($transferFirma, $transferPersonel))->receipt((string) ($_POST['transfer_key'] ?? ''), $mainAction);
if (!$mainReceipt || (int) \App\Helper\Security::decrypt((string) ($mainReceipt['data']['target_token'] ?? '')) !== $id) {
    http_response_code(403);
    response(false, ['transfer_error' => 'permission'], 'Dosya gönderimi bu bildirim işlemine ait değil.');
}
// A previously authorized transfer may finish its attachments after the record
// is approved/resolved. New edits still pass through the original state checks.

if (str_starts_with($action, 'pwaVideo')) {
    $completionKey = PwaTransferModel::key((string) ($_POST['video_key'] ?? '')) . '_complete';
    if ($action === 'pwaVideoComplete' && ($_POST['operation_key'] ?? '') !== $completionKey) throw new RuntimeException('Geçersiz video tamamlama anahtarı.');
    $completed = (new PwaTransferModel($transferFirma, $transferPersonel))->receipt($completionKey, 'pwaVideoComplete');
    if ($completed) {
        if ((int) \App\Helper\Security::decrypt((string) ($completed['data']['target_token'] ?? '')) !== $id) throw new RuntimeException('Video başka bir kayda ait.');
        response(true, ['completed' => true], 'Video daha önce kaydedildi.');
    }
}

if (in_array($action, ['pwaTransferPhoto', 'pwaVideoComplete'], true)) {
    $GLOBALS['pwaTransfer'] = new PwaTransferModel($transferFirma, $transferPersonel);
    $cached = $GLOBALS['pwaTransfer']->begin((string) ($_POST['operation_key'] ?? ''), $action);
    if ($cached) {
        $GLOBALS['pwaTransfer']->getDb()->rollBack();
        unset($GLOBALS['pwaTransfer']);
        if ((int) \App\Helper\Security::decrypt((string) ($cached['data']['target_token'] ?? '')) !== $id) throw new RuntimeException('İşlem başka bir kayda ait.');
        response($cached['success'], $cached['data'], $cached['message']);
    }
    $GLOBALS['pwaTransfer']->lockRecord($kind, $id);
}

if ($action === 'pwaTransferPhoto') {
    $file = $_FILES['foto'] ?? [];
    if ($kind === 'kacak' && !empty($_POST['legacy_transfer'])) {
        $legacyIndex = filter_var($_POST['sira'] ?? null, FILTER_VALIDATE_INT);
        if ($legacyIndex === false || $legacyIndex < 0 || $legacyIndex >= KacakKontrolModel::MAX_SAHA_FOTO) throw new RuntimeException('Geçersiz eski fotoğraf sırası.');
        if ($model->findPhotoBySira($id, 'saha', $legacyIndex)) response(true, ['target_token' => \App\Helper\Security::encrypt($id)], 'Fotoğraf daha önce kaydedildi.');
    }
    if ($kind === 'ihbar') {
        if ($model->countFotograflar($id) >= IhbarModel::MAX_FOTO) throw new RuntimeException('İhbar fotoğraf sınırı aşıldı.');
        $photo = IhbarModel::storeUploadedFoto($file, $id);
        $model->addFotograf($id, $photo['yol'], $photo['kucuk']);
    } else {
        if ($model->countPhotos($id, 'saha') >= KacakKontrolModel::MAX_SAHA_FOTO) throw new RuntimeException('Saha fotoğraf sınırı aşıldı.');
        $photo = $model->storeUploadedFile($file, $id, 'saha', $capture);
        $model->addPhoto($id, 'saha', $photo, $file['name'], $transferPersonel, null, null, KacakKontrolModel::cekimBilgisiCoz($capture, $_POST['foto_cekim'] ?? null));
    }
    response(true, ['target_token' => \App\Helper\Security::encrypt($id)], 'Fotoğraf kaydedildi.');
}

$videoKey = PwaTransferModel::key((string) ($_POST['video_key'] ?? ''));
$chunks = new PwaChunkUploadService();
$chunks->cleanup();
$result = $chunks->run($transferFirma, $transferPersonel, $videoKey, function (string $dir, ?array $meta) use ($chunks, $action, $kind, $id, $model, $transferPersonel): array {
    if ($meta && ($meta['kind'] !== $kind || $meta['record'] !== $id)) throw new RuntimeException('Video başka bir kayda bağlı.');
    if ($action === 'pwaVideoStart') {
        $size = filter_var($_POST['size'] ?? null, FILTER_VALIDATE_INT);
        $duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_FLOAT);
        $hash = (string) ($_POST['hash'] ?? '');
        $mime = (string) ($_POST['mime'] ?? '');
        $limit = $kind === 'ihbar' ? IhbarModel::VIDEO_MAX_BYTE : KacakKontrolModel::VIDEO_MAX_BYTE;
        if ($size === false || $size <= 0 || $size > $limit || $duration === false || $duration <= 0 || $duration > 90
            || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !in_array($mime, IhbarModel::VIDEO_MIMES, true)) throw new RuntimeException('Video boyutu, süresi veya formatı geçersiz.');
        $videoCount = $kind === 'ihbar' ? $model->countVideolar($id) : $model->countVideos($id);
        if ($videoCount >= ($kind === 'ihbar' ? IhbarModel::MAX_VIDEO : KacakKontrolModel::MAX_VIDEO)) throw new RuntimeException('Video sayısı sınırı aşıldı.');
        if ($meta && ($meta['hash'] !== $hash || $meta['size'] !== $size)) throw new RuntimeException('Video anahtarı farklı içerikle kullanılmış.');
        $meta = $meta ?: ['kind' => $kind, 'record' => $id, 'size' => $size, 'hash' => $hash, 'count' => (int) ceil($size / PwaChunkUploadService::CHUNK_SIZE), 'duration' => (int) ceil($duration), 'name' => mb_substr(basename((string) ($_POST['name'] ?? 'video')), 0, 200), 'cover' => (string) ($_POST['cover'] ?? ''), 'capture' => (string) ($_POST['capture'] ?? '')];
        if (strlen($meta['cover']) > 1048576) throw new RuntimeException('Video kapağı çok büyük.');
        $chunks->save($dir, $meta);
    }
    if (!$meta) return ['expired' => true, 'parts' => []];
    if ($action === 'pwaVideoChunk') {
        $index = filter_var($_POST['index'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false) throw new RuntimeException('Geçersiz parça sırası.');
        $chunks->put($dir, $meta, $index, $_FILES['chunk'] ?? [], (string) ($_POST['chunk_hash'] ?? ''));
        $chunks->save($dir, $meta);
    }
    if ($action === 'pwaVideoStatus') $chunks->save($dir, $meta);
    if ($action === 'pwaVideoComplete') {
        $path = $chunks->assemble($dir, $meta);
        $file = ['name' => $meta['name'], 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => $meta['size']];
        $video = $model->storeAssembledVideo($file, $id, $meta['duration'], $meta['cover']);
        if ($kind === 'ihbar') $model->addVideo($id, $video['yol'], $video['kapak'], $video['sure_saniye']);
        else $model->addVideo($id, $video['yol'], $video['kapak'], $video['sure_saniye'], $meta['name'], $transferPersonel, null, KacakKontrolModel::cekimBilgisiCoz(null, $meta['capture']));
        // Commit receipt together with the attachment before deleting staging data.
        $response = ['success' => true, 'data' => ['completed' => true, 'target_token' => \App\Helper\Security::encrypt($id)], 'message' => 'Video kaydedildi.'];
        $GLOBALS['pwaTransfer']->finish($response);
        $chunks->removeParts($dir);
        return $response['data'];
    }
    return ['parts' => $chunks->parts($dir, $meta), 'chunk_size' => PwaChunkUploadService::CHUNK_SIZE];
});
if (in_array($action, ['pwaVideoComplete', 'pwaVideoChunk'], true) && !empty($result['expired'])) {
    response(false, ['transfer_error' => 'expired'], 'Video geçici parçaları süresi dolduğu için yeniden gönderilmeli.');
}
response(true, $result, 'Video ilerlemesi kaydedildi.');
