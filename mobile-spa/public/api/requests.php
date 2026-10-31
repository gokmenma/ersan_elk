<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\AvansModel;
use App\Model\BildirimModel;
use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Model\TalepModel;
use App\Service\Gate;
use App\Service\IzinFormuService;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function taleplerResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function taleplerErkenYanit(array $payload): void
{
    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    http_response_code(200);
    header('Content-Encoding: none');
    header('Connection: close');
    header('Content-Length: ' . strlen((string) $json));
    echo $json;
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user']) || empty($_SESSION['firma_id'])) {
    taleplerResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}

$canAvans = Gate::allows('avans_talepleri');
$canIzin = Gate::allows('izin_talepleri');
$canAriza = Gate::allows('ariza_talepleri');

if (!$canAvans && !$canIzin && !$canAriza) {
    if (!Gate::allows('talepler')) {
        taleplerResponse(['success' => false, 'message' => 'Talepleri görüntüleme yetkiniz bulunmuyor.'], 403);
    }
    // Eğer talepler ana yetkisi varsa hepsine izin ver
    $canAvans = true;
    $canIzin = true;
    $canAriza = true;
}

$currentUserId = (int) $_SESSION['user_id'];
$talepModel = new TalepModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$bildirimModel = new BildirimModel();
$systemLogModel = new SystemLogModel();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function resolvePersonelAvatar(int $personelId, ?string $personelResimYolu = null, ?string $resimYolu = null): string
{
    if ($personelId <= 0) return '';
    $candidates = [
        trim((string) $resimYolu)
    ];

    $projectRoot = realpath(dirname(__DIR__, 3));
    $hasPhoto = false;
    if ($projectRoot) {
        foreach ($candidates as $cand) {
            if ($cand === '') continue;
            $clean = ltrim(str_replace('\\', '/', $cand), '/');
            $tryFile = realpath($projectRoot . '/' . $clean);
            if ($tryFile && str_starts_with($tryFile, $projectRoot . DIRECTORY_SEPARATOR) && is_file($tryFile)) {
                $hasPhoto = true;
                break;
            }
        }
    }

    if (!$hasPhoto) {
        return '';
    }

    $encId = Security::encrypt((string) $personelId);
    return '/api/personnel-photo.php?id=' . rawurlencode($encId);
}

function formatTaleplerDate(?string $dt, bool $onlyDate = false): string
{
    if (empty($dt) || $dt === '0000-00-00' || $dt === '0000-00-00 00:00:00') return '-';
    $time = strtotime($dt);
    if (!$time) return '-';
    return date($onlyDate ? 'd.m.Y' : 'd.m.Y H:i', $time);
}

if ($method === 'GET') {
    if (($_GET['action'] ?? '') === 'izin-formu-docx-indir') {
        if (!$canIzin && !Gate::allows('talepler')) {
            taleplerResponse(['success' => false, 'message' => 'Yetkisiz erişim.'], 403);
        }
        $rawId = $_GET['id'] ?? '';
        $id = (int) $rawId;
        if ($id <= 0 && !empty($rawId)) {
            $decrypted = Security::decrypt($rawId);
            $id = (int) $decrypted;
        }
        if ($id <= 0) {
            taleplerResponse(['success' => false, 'message' => 'Geçersiz izin ID.'], 422);
        }
        try {
            $firmaId = (int) ($_SESSION['firma_id'] ?? 1);
            $izinFormuService = new IzinFormuService();
            $formData = $izinFormuService->getFormData($id, $firmaId, $currentUserId);
            $izinFormuService->generateDocx($formData);
            exit;
        } catch (\Throwable $e) {
            error_log('Mobile requests izin formu docx indirme hatası: ' . $e->getMessage());
            taleplerResponse(['success' => false, 'message' => 'Dosya oluşturulamadı: ' . $e->getMessage()], 500);
        }
    }

    $tab = (string) ($_GET['tab'] ?? 'all'); // avans, izin, talep, all
    $filter = (string) ($_GET['filter'] ?? 'pending'); // pending, approved, deleted

    $bekleyenAvanslar = $canAvans ? $avansModel->getButunBekleyenAvanslar() : [];
    $bekleyenIzinler = [];
    if ($canIzin) {
        try {
            $bekleyenIzinler = $izinModel->getButunBekleyenIzinler();
        } catch (\Throwable $e) {
            $bekleyenIzinler = [];
        }
    }
    $bekleyenTalepler = $canAriza ? $talepModel->getButunBekleyenTalepler() : [];

    $counts = [
        'avans' => count($bekleyenAvanslar),
        'izin' => count($bekleyenIzinler),
        'talep' => count($bekleyenTalepler),
        'total' => count($bekleyenAvanslar) + count($bekleyenIzinler) + count($bekleyenTalepler),
    ];

    if ($filter === 'approved') {
        $rawAvans = $canAvans ? $avansModel->getIslenmisAvanslar(60) : [];
        $rawIzin = [];
        if ($canIzin) {
            try { $rawIzin = $izinModel->getIslenmisIzinler(60); } catch (\Throwable $e) {}
        }
        $rawTalep = $canAriza ? $talepModel->getCozulmusTalepler(60) : [];
    } elseif ($filter === 'deleted') {
        $rawAvans = $canAvans ? $avansModel->getSilinmisAvanslar(60) : [];
        $rawIzin = [];
        if ($canIzin) {
            try { $rawIzin = $izinModel->getSilinmisIzinler(60); } catch (\Throwable $e) {}
        }
        $rawTalep = $canAriza ? $talepModel->getSilinmisTalepler(60) : [];
    } else {
        $rawAvans = $bekleyenAvanslar;
        $rawIzin = $bekleyenIzinler;
        $rawTalep = $bekleyenTalepler;
    }

    $formattedAvans = array_map(static function ($a) use ($filter) {
        $pId = (int) ($a->personel_id ?? 0);
        return [
            'id' => (int) $a->id,
            'type' => 'avans',
            'name' => $a->requester_name ?? 'Bilinmeyen',
            'department' => $a->departman ?? 'Departman Yok',
            'avatar' => resolvePersonelAvatar($pId, $a->personel_resim_yolu ?? null, $a->resim_yolu ?? null),
            'amount' => (float) ($a->tutar ?? 0),
            'amount_formatted' => number_format((float) ($a->tutar ?? 0), 2, ',', '.') . ' ₺',
            'date' => formatTaleplerDate($a->talep_tarihi),
            'timestamp' => strtotime($a->talep_tarihi ?? '') ?: 0,
            'description' => $a->aciklama ?? '',
            'status' => $a->durum ?? 'beklemede',
            'approval_date' => formatTaleplerDate($a->onay_tarihi ?? $a->silinme_tarihi ?? $a->islem_tarihi ?? null),
            'approval_note' => $a->onay_aciklama ?? $a->silinme_aciklama ?? '',
            'solver_name' => $a->solver_name ?? '',
            'filter_mode' => $filter,
        ];
    }, $rawAvans);

    $formattedIzin = array_map(static function ($i) use ($izinModel, $filter) {
        $pId = (int) ($i->personel_id ?? 0);
        $days = (int) ($i->toplam_gun ?? 0);
        if ($days <= 0 && !empty($i->baslangic_tarihi) && !empty($i->bitis_tarihi)) {
            $days = (int) $izinModel->hesaplaIzinGunu($i->baslangic_tarihi, $i->bitis_tarihi);
        }
        return [
            'id' => (int) $i->id,
            'type' => 'izin',
            'name' => $i->requester_name ?? 'Bilinmeyen',
            'department' => $i->departman ?? 'Departman Yok',
            'avatar' => resolvePersonelAvatar($pId, $i->personel_resim_yolu ?? null, $i->resim_yolu ?? null),
            'leave_type' => $i->izin_tipi_adi ?? $i->izin_tipi ?? 'İzin',
            'days' => $days,
            'date' => formatTaleplerDate($i->talep_tarihi),
            'timestamp' => strtotime($i->talep_tarihi ?? '') ?: 0,
            'start_date' => formatTaleplerDate($i->baslangic_tarihi, true),
            'end_date' => formatTaleplerDate($i->bitis_tarihi, true),
            'start_date_raw' => $i->baslangic_tarihi ?? '',
            'end_date_raw' => $i->bitis_tarihi ?? '',
            'description' => $i->aciklama ?? '',
            'status' => $i->onay_durumu ?? 'beklemede',
            'approval_date' => formatTaleplerDate($i->islem_tarihi ?? $i->silinme_tarihi ?? null),
            'approval_note' => $i->onay_aciklama ?? $i->silinme_aciklama ?? '',
            'solver_name' => $i->solver_name ?? '',
            'filter_mode' => $filter,
        ];
    }, $rawIzin);

    $formattedTalep = array_map(static function ($t) use ($filter) {
        $pId = (int) ($t->personel_id ?? 0);
        return [
            'id' => (int) $t->id,
            'type' => 'talep',
            'name' => $t->requester_name ?? 'Bilinmeyen',
            'department' => $t->departman ?? 'Departman Yok',
            'avatar' => resolvePersonelAvatar($pId, $t->personel_resim_yolu ?? null, $t->resim_yolu ?? null),
            'title' => $t->baslik ?? 'Genel Talep',
            'priority' => $t->oncelik ?? 'normal',
            'date' => formatTaleplerDate($t->olusturma_tarihi),
            'timestamp' => strtotime($t->olusturma_tarihi ?? '') ?: 0,
            'description' => $t->aciklama ?? '',
            'status' => $t->durum ?? 'beklemede',
            'solution' => $t->cozum_aciklama ?? $t->silinme_aciklama ?? '',
            'solution_date' => formatTaleplerDate($t->cozum_tarihi ?? $t->silinme_tarihi ?? null),
            'solver_name' => $t->solver_name ?? '',
            'photo' => !empty($t->foto) ? ('/' . ltrim($t->foto, '/.')) : '',
            'filter_mode' => $filter,
        ];
    }, $rawTalep);

    taleplerResponse([
        'success' => true,
        'counts' => $counts,
        'permissions' => [
            'avans' => $canAvans,
            'izin' => $canIzin,
            'talep' => $canAriza,
        ],
        'data' => [
            'avans' => $formattedAvans,
            'izin' => $formattedIzin,
            'talep' => $formattedTalep,
        ],
    ]);
}

if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = (string) ($input['action'] ?? '');
    $id = (int) ($input['id'] ?? 0);
    $aciklama = trim((string) ($input['aciklama'] ?? ''));

    if ($id <= 0) {
        taleplerResponse(['success' => false, 'message' => 'Geçersiz talep numarası.'], 422);
    }

    try {
        switch ($action) {
            case 'avans-onayla':
                if (!$canAvans) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                $onayTipi = (string) ($input['onay_tipi'] ?? 'ayni');
                $onaylananTutar = null;
                if ($onayTipi === 'farkli' && isset($input['farkli_tutar'])) {
                    $rawVal = str_replace(',', '.', str_replace('.', '', (string) $input['farkli_tutar']));
                    $onaylananTutar = (float) $rawVal;
                    if (empty($aciklama)) {
                        $aciklama = 'Avans talebiniz ' . number_format($onaylananTutar, 2, ',', '.') . ' TL olarak uygun görülmüştür.';
                    }
                }
                $hesabaIsle = !empty($input['hesaba_isle']);
                if ($avansModel->updateDurum($id, 'onaylandi', $aciklama, $onaylananTutar)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'avans', $id);
                    if ($hesabaIsle) {
                        $avansModel->avansHesabaIsle($id);
                    }
                    $systemLogModel->logAction($currentUserId, 'Avans Onaylama', "Avans talebi onaylandı. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'Avans talebi onaylandı.']);
                    try {
                        $av = $avansModel->find($id);
                        if ($av && $av->personel_id) {
                            (new PushNotificationService())->sendToPersonel($av->personel_id, [
                                'title' => '✅ Avans Onaylandı',
                                'body' => number_format((float) ($onaylananTutar ?: $av->tutar), 2, ',', '.') . ' TL tutarındaki avansınız onaylandı.',
                                'url' => 'index.php?page=bordro'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'Avans onaylanamadı.'], 500);

            case 'avans-reddet':
                if (!$canAvans) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                if (empty($aciklama)) taleplerResponse(['success' => false, 'message' => 'Red açıklaması zorunludur.'], 422);
                if ($avansModel->updateDurum($id, 'reddedildi', $aciklama)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'avans', $id);
                    $systemLogModel->logAction($currentUserId, 'Avans Reddetme', "Avans talebi reddedildi. ID: $id, Sebep: $aciklama", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'Avans talebi reddedildi.']);
                    try {
                        $av = $avansModel->find($id);
                        if ($av && $av->personel_id) {
                            (new PushNotificationService())->sendToPersonel($av->personel_id, [
                                'title' => '❌ Avans Reddedildi',
                                'body' => 'Avans talebiniz reddedildi: ' . $aciklama,
                                'url' => 'index.php?page=bordro'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'İşlem tamamlanamadı.'], 500);

            case 'izin-onayla':
                if (!$canIzin) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                $onayTipi = (string) ($input['onay_tipi'] ?? 'ayni');
                $farkliBas = !empty($input['farkli_baslangic']) ? (string) $input['farkli_baslangic'] : null;
                $farkliBit = !empty($input['farkli_bitis']) ? (string) $input['farkli_bitis'] : null;
                if ($onayTipi === 'farkli' && $farkliBas && $farkliBit) {
                    $gun = $izinModel->hesaplaIzinGunu($farkliBas, $farkliBit);
                    if (empty($aciklama)) {
                        $aciklama = "İzin talebiniz $farkliBas - $farkliBit arasında $gun gün olarak uygun görülmüştür.";
                    }
                }
                if ($izinModel->updateDurum($id, 'Onaylandı', $aciklama, $farkliBas, $farkliBit)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'izin', $id);
                    $systemLogModel->logAction($currentUserId, 'İzin Onaylama', "İzin talebi onaylandı. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'İzin talebi onaylandı.']);
                    try {
                        $iz = $izinModel->find($id);
                        if ($iz && $iz->personel_id) {
                            (new PushNotificationService())->sendToPersonel($iz->personel_id, [
                                'title' => '✅ İzin Onaylandı',
                                'body' => 'İzin talebiniz onaylandı.',
                                'url' => 'index.php?page=izin'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'İzin onaylanamadı.'], 500);

            case 'izin-reddet':
                if (!$canIzin) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                if (empty($aciklama)) taleplerResponse(['success' => false, 'message' => 'Red açıklaması zorunludur.'], 422);
                if ($izinModel->updateDurum($id, 'Reddedildi', $aciklama)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'izin', $id);
                    $systemLogModel->logAction($currentUserId, 'İzin Reddetme', "İzin talebi reddedildi. ID: $id, Sebep: $aciklama", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'İzin talebi reddedildi.']);
                    try {
                        $iz = $izinModel->find($id);
                        if ($iz && $iz->personel_id) {
                            (new PushNotificationService())->sendToPersonel($iz->personel_id, [
                                'title' => '❌ İzin Reddedildi',
                                'body' => 'İzin talebiniz reddedildi: ' . $aciklama,
                                'url' => 'index.php?page=izin'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'İşlem tamamlanamadı.'], 500);

            case 'talep-isleme-al':
                if (!$canAriza) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                if ($talepModel->updateDurum($id, 'islemde', null)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'talep', $id);
                    $systemLogModel->logAction($currentUserId, 'Talep İşleme Alma', "Talep işleme alındı. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'Talep işleme alındı.']);
                    try {
                        $tl = $talepModel->getTalepDetay($id);
                        if ($tl && $tl->personel_id) {
                            (new PushNotificationService())->sendToPersonel($tl->personel_id, [
                                'title' => '⚙️ Talep İşleme Alındı',
                                'body' => 'Talebiniz işleme alındı.',
                                'url' => 'index.php?page=talep'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'İşlem güncellenemedi.'], 500);

            case 'talep-beklemeye-al':
                if (!$canAriza) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                if ($talepModel->updateDurum($id, 'beklemede', null)) {
                    $systemLogModel->logAction($currentUserId, 'Talep Beklemeye Alma', "Talep tekrar beklemeye alındı. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerResponse(['success' => true, 'message' => 'Talep tekrar beklemede durumuna alındı.']);
                }
                taleplerResponse(['success' => false, 'message' => 'İşlem güncellenemedi.'], 500);

            case 'talep-cozuldu':
                if (!$canAriza) taleplerResponse(['success' => false, 'message' => 'Yetkisiz işlem.'], 403);
                if ($talepModel->updateDurum($id, 'cozuldu', $aciklama)) {
                    $bildirimModel->markRequestNotificationAsRead($currentUserId, 'talep', $id);
                    $systemLogModel->logAction($currentUserId, 'Talep Çözüldü İşaretleme', "Talep çözüldü. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                    taleplerErkenYanit(['success' => true, 'message' => 'Talep çözüldü olarak işaretlendi.']);
                    try {
                        $tl = $talepModel->find($id);
                        if ($tl && $tl->personel_id) {
                            (new PushNotificationService())->sendToPersonel($tl->personel_id, [
                                'title' => '✅ Talep Çözüldü',
                                'body' => 'Talebiniz çözüldü olarak işaretlendi.',
                                'url' => 'index.php?page=talep'
                            ]);
                        }
                    } catch (\Throwable $e) {}
                    exit;
                }
                taleplerResponse(['success' => false, 'message' => 'İşlem tamamlanamadı.'], 500);

            case 'talep-sil':
            case 'avans-sil':
            case 'izin-sil':
                if (empty($aciklama)) taleplerResponse(['success' => false, 'message' => 'Silme gerekçesi zorunludur.'], 422);
                $tbl = match ($action) {
                    'avans-sil' => 'personel_avanslari',
                    'izin-sil' => 'personel_izinleri',
                    'talep-sil' => 'personel_talepleri',
                };
                $db = $talepModel->getDb();
                $stmt = $db->prepare("UPDATE {$tbl} SET silinme_tarihi = NOW(), silen_kullanici = ?, silinme_aciklama = ? WHERE id = ?");
                $stmt->execute([$currentUserId, $aciklama, $id]);
                $systemLogModel->logAction($currentUserId, 'Talep Silme', "Talep silindi. Tablo: $tbl, ID: $id", SystemLogModel::LEVEL_IMPORTANT);
                taleplerResponse(['success' => true, 'message' => 'Kayıt başarıyla silindi.']);

            default:
                taleplerResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 400);
        }
    } catch (\Throwable $e) {
        error_log('Mobile talepler API error: ' . $e->getMessage());
        taleplerResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
    }
}
