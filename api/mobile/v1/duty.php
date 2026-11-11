<?php
require_once __DIR__ . '/_bootstrap.php';

use App\Model\MobileDutyModel;
use App\Model\AracZimmetModel;
use App\Model\AracKmBildirimModel;
use App\Model\SystemLogModel;

$personel = mobile_require_auth();
if ((int) ($personel->saha_takibi ?? 0) !== 1) {
    mobile_fail('Bu personel için saha görev takibi açık değil.', 403);
}
$input = mobile_input();
$action = (string) ($input['action'] ?? 'status');
if (!in_array($action, ['status', 'start', 'finish'], true)) { mobile_fail('Geçersiz işlem.'); }
if ($action !== 'status' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    mobile_fail('POST isteği gerekli.', 405);
}
try {
    $model = new MobileDutyModel();
    $vehicle = (new AracZimmetModel())->getAktifZimmetByPersonel((int) $personel->id);
    $km = new AracKmBildirimModel();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $morning = !$vehicle || $km->hasValidReport($personel->id, $vehicle->arac_id, $today, 'sabah');
    $evening = !$vehicle || $km->hasValidReport($personel->id, $vehicle->arac_id, $today, 'aksam');
    $gate = $action === 'start' && !$morning;
    if ($action !== 'status' && !$gate) {
        $lat = filter_var($input['konum_enlem'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($input['konum_boylam'] ?? null, FILTER_VALIDATE_FLOAT);
        $accuracy = filter_var($input['konum_hassasiyeti'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || !is_finite($lat) || !is_finite($lng)
            || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            mobile_fail('Geçerli konum bilgisi gerekli.');
        }
        if ($action === 'finish' && !$evening && empty($input['skip_evening_km'])) {
            mobile_ok(['requires_evening_km' => true, 'message' => 'Akşam KM bildiriminiz henüz yapılmadı.']);
        }
        $changed = $model->transition($action, [
            'personel_id' => (int) $personel->id, 'firma_id' => (int) $personel->firma_id,
            'konum_enlem' => $lat, 'konum_boylam' => $lng,
            'konum_hassasiyeti' => $accuracy !== false && $accuracy >= 0 ? $accuracy : null,
            'cihaz_bilgisi' => substr((string) ($input['cihaz_bilgisi'] ?? 'React Native'), 0, 250),
            'ip_adresi' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        if ($changed && $action === 'finish' && !$evening) {
            try {
                (new SystemLogModel())->logAction(0, 'Akşam KM Hatırlatması Atlandı',
                    'Personel #' . $personel->id . ', ' . $vehicle->plaka . ' için akşam KM bildirimini erteledi.',
                    SystemLogModel::LEVEL_INFO);
            } catch (\Throwable $e) { error_log('Mobile duty KM skip log: ' . $e->getMessage()); }
        }
    }
    $active = $model->getAcikGorev((int) $personel->id);
    mobile_ok(['active' => (bool) $active,
        'started_at' => $active ? date(DATE_ATOM, strtotime($active->zaman)) : null,
        'started_time' => $active ? date('H:i', strtotime($active->zaman)) : null,
        'requires_morning_km' => $gate,
        'morning_reported' => $morning, 'evening_reported' => $evening,
        'vehicle_plate' => $vehicle ? $vehicle->plaka : null,
        'missing_yesterday_evening_km' => $vehicle && $vehicle->zimmet_tarihi <= $yesterday
            && !$km->hasValidReport($personel->id, $vehicle->arac_id, $yesterday, 'aksam'),
    ]);
} catch (\Throwable $e) {
    error_log('Mobile duty: ' . $e->getMessage());
    mobile_fail('Görev işlemi tamamlanamadı. Durumu yenileyip tekrar deneyin.', 500);
}
