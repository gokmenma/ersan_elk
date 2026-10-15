<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracKmModel;
use App\Model\AracModel;
use App\Model\AracServisModel;
use App\Model\AracYakitModel;
use App\Model\AracZimmetFotoModel;
use App\Model\AracZimmetModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function aracApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkAracAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        aracApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if (!Gate::allowsAny(['arac_takip_yonetim', 'arac-takip/list', 'vehicles'])) {
        aracApiResponse(['success' => false, 'message' => 'Araç takip modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        aracApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

checkAracAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if ($firmaId <= 0) {
    aracApiResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$Arac = new AracModel();
$Zimmet = new AracZimmetModel();
$Yakit = new AracYakitModel();
$Km = new AracKmModel();
$Servis = new AracServisModel();
$Personel = new PersonelModel();
$SystemLog = new SystemLogModel();
$db = $Arac->getDb();

// Yardımcı: Gün farkı hesaplama
function calculateDaysLeft(?string $dateStr): ?int
{
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return null;
    }
    $target = new DateTime($dateStr);
    $today = new DateTime('today');
    $diff = (int) $today->diff($target)->format('%r%a');
    return $diff;
}

// Yardımcı: Tarih periyodu
function parseDateRange(string $period, string $ay = '', string $yil = ''): array
{
    $today = date('Y-m-d');
    if ($period === 'today') {
        return [$today, $today];
    }
    if ($period === 'this_month') {
        return [date('Y-m-01'), date('Y-m-t')];
    }
    if ($period === 'custom' && !empty($yil) && !empty($ay)) {
        $first = sprintf('%04d-%02d-01', (int) $yil, (int) $ay);
        return [$first, date('Y-m-t', strtotime($first))];
    }
    return [null, null];
}

try {
    // -------------------------------------------------------------
    // 1. LİSTE & ÖZET METRİKLER (GET)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'list') {
        $tab = trim((string) ($_GET['tab'] ?? 'araclar'));
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $period = trim((string) ($_GET['period'] ?? 'this_month'));
        $ay = trim((string) ($_GET['ay'] ?? date('m')));
        $yil = trim((string) ($_GET['yil'] ?? date('Y')));

        // Genel Filo İstatistikleri
        $stats = $Arac->getStats();
        $zimmetliSayi = (int) $Arac->getZimmetliAracSayisi();
        $servistekiSayi = (int) $Arac->getServistekiAracSayisi();
        $toplamArac = (int) ($stats->toplam_arac ?? 0);
        $aktifArac = (int) ($stats->aktif_arac ?? 0);
        $pasifArac = max(0, $toplamArac - $aktifArac);
        $bostaArac = (int) ($stats->bosta_arac ?? max(0, $aktifArac - $zimmetliSayi - $servistekiSayi));
        $ikameArac = (int) ($stats->ikame_arac ?? 0);

        // Evrak uyarısı (30 gün içinde bitecek veya süresi dolmuş) araç sayısı
        $stmtEvrak = $db->prepare("
            SELECT COUNT(*) as uyari_sayisi
            FROM araclar
            WHERE firma_id = :firma_id
              AND aktif_mi = 1
              AND silinme_tarihi IS NULL
              AND (
                  (muayene_bitis_tarihi IS NOT NULL AND muayene_bitis_tarihi != '0000-00-00' AND DATEDIFF(muayene_bitis_tarihi, CURDATE()) <= 30)
                  OR (sigorta_bitis_tarihi IS NOT NULL AND sigorta_bitis_tarihi != '0000-00-00' AND DATEDIFF(sigorta_bitis_tarihi, CURDATE()) <= 30)
                  OR (kasko_bitis_tarihi IS NOT NULL AND kasko_bitis_tarihi != '0000-00-00' AND DATEDIFF(kasko_bitis_tarihi, CURDATE()) <= 30)
              )
        ");
        $stmtEvrak->execute(['firma_id' => $firmaId]);
        $evrakUyariSayisi = (int) ($stmtEvrak->fetch(PDO::FETCH_OBJ)->uyari_sayisi ?? 0);

        list($d1, $d2) = parseDateRange($period, $ay, $yil);

        // Sekme verilerini topla
        $items = [];
        $tabTotals = [
            'total_yakit_tutar' => 0,
            'total_yakit_litre' => 0,
            'total_yapilan_km' => 0,
            'aktif_servis_sayisi' => $servistekiSayi,
        ];

        if ($tab === 'araclar') {
            $sql = "
                SELECT a.*, 
                       az.id as zimmet_id,
                       az.zimmet_tarihi as zimmet_tarihi,
                       az.teslim_km as zimmet_teslim_km,
                       az.personel_id as zimmetli_personel_id,
                       p.adi_soyadi as zimmetli_personel_adi,
                       p.departman as zimmetli_personel_departman,
                       p.resim_yolu as zimmetli_personel_resim,
                       (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
                FROM araclar a
                LEFT JOIN (
                    SELECT az1.* FROM arac_zimmetleri az1
                    INNER JOIN (
                        SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' AND silinme_tarihi IS NULL GROUP BY arac_id
                    ) az2 ON az1.id = az2.max_id
                ) az ON a.id = az.arac_id
                LEFT JOIN personel p ON az.personel_id = p.id
                WHERE a.firma_id = :firma_id 
                  AND a.silinme_tarihi IS NULL
            ";
            $params = ['firma_id' => $firmaId];

            if ($status === 'aktif') {
                $sql .= " AND a.aktif_mi = 1 AND a.ikame_mi = 0";
            } elseif ($status === 'pasif') {
                $sql .= " AND a.aktif_mi = 0";
            } elseif ($status === 'ikame') {
                $sql .= " AND a.ikame_mi = 1";
            }

            if (!empty($search)) {
                $sql .= " AND (a.plaka LIKE :s OR a.marka LIKE :s OR a.model LIKE :s OR a.renk LIKE :s OR p.adi_soyadi LIKE :s)";
                $params['s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY a.plaka ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawList = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($rawList as $row) {
                $isServiste = ((int) $row->serviste_mi > 0);
                $isZimmetli = (!empty($row->zimmetli_personel_id) && !$isServiste);
                $isBosta = ($row->aktif_mi == 1 && !$isServiste && empty($row->zimmetli_personel_id));

                $muayeneKalan = calculateDaysLeft($row->muayene_bitis_tarihi);
                $sigortaKalan = calculateDaysLeft($row->sigorta_bitis_tarihi);
                $kaskoKalan = calculateDaysLeft($row->kasko_bitis_tarihi);

                $hasWarning = (
                    ($muayeneKalan !== null && $muayeneKalan <= 30) ||
                    ($sigortaKalan !== null && $sigortaKalan <= 30) ||
                    ($kaskoKalan !== null && $kaskoKalan <= 30)
                );

                if ($status === 'zimmetli' && !$isZimmetli) continue;
                if ($status === 'bosta' && !$isBosta) continue;
                if ($status === 'serviste' && !$isServiste) continue;
                if ($status === 'evrak_uyari' && !$hasWarning) continue;

                $items[] = [
                    'id' => Security::encrypt((string) $row->id),
                    'raw_id' => (int) $row->id,
                    'plaka' => (string) $row->plaka,
                    'marka' => (string) ($row->marka ?? ''),
                    'model' => (string) ($row->model ?? ''),
                    'model_yili' => (string) ($row->model_yili ?? ''),
                    'renk' => (string) ($row->renk ?? ''),
                    'arac_tipi' => (string) ($row->arac_tipi ?? 'binek'),
                    'yakit_tipi' => (string) ($row->yakit_tipi ?? 'dizel'),
                    'guncel_km' => (int) ($row->guncel_km ?? 0),
                    'baslangic_km' => (int) ($row->baslangic_km ?? 0),
                    'aktif_mi' => (bool) $row->aktif_mi,
                    'ikame_mi' => (bool) $row->ikame_mi,
                    'serviste_mi' => $isServiste,
                    'zimmetli_mi' => $isZimmetli,
                    'bosta_mi' => $isBosta,
                    'zimmetli_personel' => $row->zimmetli_personel_adi ? [
                        'id' => Security::encrypt((string) $row->zimmetli_personel_id),
                        'adi_soyadi' => (string) $row->zimmetli_personel_adi,
                        'departman' => (string) ($row->zimmetli_personel_departman ?? ''),
                        'resim_yolu' => (string) ($row->zimmetli_personel_resim ?? ''),
                        'zimmet_tarihi' => (string) ($row->zimmet_tarihi ?? ''),
                        'teslim_km' => (int) ($row->zimmet_teslim_km ?? 0),
                    ] : null,
                    'has_ruhsat' => !empty($row->ruhsat_dosya_adi),
                    'ruhsat_dosya_adi' => (string) ($row->ruhsat_orijinal_ad ?? ''),
                    'evraklar' => [
                        'muayene' => [
                            'tarih' => $row->muayene_bitis_tarihi ?: null,
                            'kalan_gun' => $muayeneKalan,
                        ],
                        'sigorta' => [
                            'tarih' => $row->sigorta_bitis_tarihi ?: null,
                            'kalan_gun' => $sigortaKalan,
                        ],
                        'kasko' => [
                            'tarih' => $row->kasko_bitis_tarihi ?: null,
                            'kalan_gun' => $kaskoKalan,
                        ],
                    ],
                    'has_warning' => $hasWarning,
                ];
            }
        } elseif ($tab === 'zimmet') {
            $sql = "
                SELECT az.*, 
                       a.plaka, a.marka, a.model, a.guncel_km as arac_guncel_km,
                       p.adi_soyadi as personel_adi, p.departman as personel_departman, p.resim_yolu as personel_resim,
                       u.user_name as olusturan_kullanici
                FROM arac_zimmetleri az
                INNER JOIN araclar a ON az.arac_id = a.id
                INNER JOIN personel p ON az.personel_id = p.id
                LEFT JOIN users u ON az.olusturan_kullanici_id = u.id
                WHERE az.firma_id = :firma_id
                  AND az.silinme_tarihi IS NULL
            ";
            $params = ['firma_id' => $firmaId];

            if ($d1 && $d2) {
                $sql .= " AND az.zimmet_tarihi BETWEEN :d1 AND :d2";
                $params['d1'] = $d1;
                $params['d2'] = $d2;
            }

            if (!empty($search)) {
                $sql .= " AND (a.plaka LIKE :s OR p.adi_soyadi LIKE :s OR az.notlar LIKE :s)";
                $params['s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY az.zimmet_tarihi DESC, az.id DESC LIMIT 200";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawList = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($rawList as $row) {
                $items[] = [
                    'id' => Security::encrypt((string) $row->id),
                    'raw_id' => (int) $row->id,
                    'arac_id' => Security::encrypt((string) $row->arac_id),
                    'plaka' => (string) $row->plaka,
                    'arac_bilgi' => trim(($row->marka ?? '') . ' ' . ($row->model ?? '')),
                    'arac_guncel_km' => (int) ($row->arac_guncel_km ?? 0),
                    'personel_id' => Security::encrypt((string) $row->personel_id),
                    'personel_adi' => (string) $row->personel_adi,
                    'personel_departman' => (string) ($row->personel_departman ?? ''),
                    'personel_resim' => (string) ($row->personel_resim ?? ''),
                    'zimmet_tarihi' => (string) $row->zimmet_tarihi,
                    'teslim_km' => (int) ($row->teslim_km ?? 0),
                    'iade_tarihi' => $row->iade_tarihi ?: null,
                    'iade_km' => $row->iade_km !== null ? (int) $row->iade_km : null,
                    'durum' => (string) ($row->durum ?? 'aktif'),
                    'notlar' => (string) ($row->notlar ?? ''),
                ];
            }
        } elseif ($tab === 'yakit') {
            $sql = "
                SELECT y.*, 
                       a.plaka, a.marka, a.model,
                       az.personel_id as aktif_personel_id,
                       p.adi_soyadi as personel_adi,
                       u.user_name as olusturan_kullanici
                FROM arac_yakit_kayitlari y
                INNER JOIN araclar a ON y.arac_id = a.id
                LEFT JOIN users u ON y.olusturan_kullanici_id = u.id
                LEFT JOIN (
                    SELECT az1.arac_id, az1.personel_id FROM arac_zimmetleri az1
                    INNER JOIN (
                        SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE silinme_tarihi IS NULL GROUP BY arac_id
                    ) az2 ON az1.id = az2.max_id
                ) az ON a.id = az.arac_id
                LEFT JOIN personel p ON az.personel_id = p.id
                WHERE y.firma_id = :firma_id
                  AND y.silinme_tarihi IS NULL
            ";
            $params = ['firma_id' => $firmaId];

            if ($d1 && $d2) {
                $sql .= " AND y.tarih BETWEEN :d1 AND :d2";
                $params['d1'] = $d1;
                $params['d2'] = $d2;
            }

            if (!empty($search)) {
                $sql .= " AND (a.plaka LIKE :s OR y.istasyon LIKE :s OR y.fatura_no LIKE :s OR p.adi_soyadi LIKE :s)";
                $params['s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY y.tarih DESC, y.id DESC LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawList = $stmt->fetchAll(PDO::FETCH_OBJ);

            $totTutar = 0.0;
            $totLitre = 0.0;
            foreach ($rawList as $row) {
                $totTutar += (float) ($row->toplam_tutar ?? 0);
                $totLitre += (float) ($row->yakit_miktari ?? 0);
                $items[] = [
                    'id' => Security::encrypt((string) $row->id),
                    'raw_id' => (int) $row->id,
                    'arac_id' => Security::encrypt((string) $row->arac_id),
                    'plaka' => (string) $row->plaka,
                    'arac_bilgi' => trim(($row->marka ?? '') . ' ' . ($row->model ?? '')),
                    'personel_adi' => (string) ($row->personel_adi ?? '-'),
                    'tarih' => (string) $row->tarih,
                    'km' => (int) ($row->km ?? 0),
                    'yakit_miktari' => (float) ($row->yakit_miktari ?? 0),
                    'birim_fiyat' => (float) ($row->birim_fiyat ?? 0),
                    'toplam_tutar' => (float) ($row->toplam_tutar ?? 0),
                    'yakit_tipi' => (string) ($row->yakit_tipi ?? 'dizel'),
                    'tam_depo_mu' => (bool) $row->tam_depo_mu,
                    'istasyon' => (string) ($row->istasyon ?? ''),
                    'fatura_no' => (string) ($row->fatura_no ?? ''),
                    'notlar' => (string) ($row->notlar ?? ''),
                ];
            }
            $tabTotals['total_yakit_tutar'] = $totTutar;
            $tabTotals['total_yakit_litre'] = $totLitre;
        } elseif ($tab === 'km') {
            $sql = "
                SELECT k.*, 
                       a.plaka, a.marka, a.model, a.guncel_km as arac_guncel_km,
                       az.personel_id as aktif_personel_id,
                       p.adi_soyadi as personel_adi,
                       u.user_name as olusturan_kullanici
                FROM arac_km_kayitlari k
                INNER JOIN araclar a ON k.arac_id = a.id
                LEFT JOIN users u ON k.olusturan_kullanici_id = u.id
                LEFT JOIN (
                    SELECT az1.arac_id, az1.personel_id FROM arac_zimmetleri az1
                    INNER JOIN (
                        SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE silinme_tarihi IS NULL GROUP BY arac_id
                    ) az2 ON az1.id = az2.max_id
                ) az ON a.id = az.arac_id
                LEFT JOIN personel p ON az.personel_id = p.id
                WHERE k.firma_id = :firma_id
                  AND k.silinme_tarihi IS NULL
            ";
            $params = ['firma_id' => $firmaId];

            if ($d1 && $d2) {
                $sql .= " AND k.tarih BETWEEN :d1 AND :d2";
                $params['d1'] = $d1;
                $params['d2'] = $d2;
            }

            if (!empty($search)) {
                $sql .= " AND (a.plaka LIKE :s OR p.adi_soyadi LIKE :s OR k.aciklama LIKE :s)";
                $params['s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY k.tarih DESC, k.id DESC LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawList = $stmt->fetchAll(PDO::FETCH_OBJ);

            $totKm = 0;
            foreach ($rawList as $row) {
                $totKm += (int) ($row->yapilan_km ?? 0);
                $items[] = [
                    'id' => Security::encrypt((string) $row->id),
                    'raw_id' => (int) $row->id,
                    'arac_id' => Security::encrypt((string) $row->arac_id),
                    'plaka' => (string) $row->plaka,
                    'arac_bilgi' => trim(($row->marka ?? '') . ' ' . ($row->model ?? '')),
                    'personel_adi' => (string) ($row->personel_adi ?? '-'),
                    'tarih' => (string) $row->tarih,
                    'baslangic_km' => (int) ($row->baslangic_km ?? 0),
                    'bitis_km' => (int) ($row->bitis_km ?? 0),
                    'yapilan_km' => (int) ($row->yapilan_km ?? 0),
                    'aciklama' => (string) ($row->aciklama ?? $row->notlar ?? ''),
                ];
            }
            $tabTotals['total_yapilan_km'] = $totKm;
        } elseif ($tab === 'servis') {
            $sql = "
                SELECT s.*, 
                       a.plaka, a.marka, a.model, a.guncel_km as arac_guncel_km,
                       u.user_name as olusturan_kullanici
                FROM arac_servis_kayitlari s
                INNER JOIN araclar a ON s.arac_id = a.id
                LEFT JOIN users u ON s.olusturan_kullanici_id = u.id
                WHERE s.firma_id = :firma_id
                  AND s.silinme_tarihi IS NULL
            ";
            $params = ['firma_id' => $firmaId];

            if ($d1 && $d2) {
                $sql .= " AND s.servis_tarihi BETWEEN :d1 AND :d2";
                $params['d1'] = $d1;
                $params['d2'] = $d2;
            }

            if (!empty($search)) {
                $sql .= " AND (a.plaka LIKE :s OR s.servis_adi LIKE :s OR s.servis_nedeni LIKE :s OR s.fatura_no LIKE :s)";
                $params['s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY (s.iade_tarihi IS NULL) DESC, s.servis_tarihi DESC, s.id DESC LIMIT 200";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawList = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($rawList as $row) {
                $isAktif = empty($row->iade_tarihi);
                $items[] = [
                    'id' => Security::encrypt((string) $row->id),
                    'raw_id' => (int) $row->id,
                    'arac_id' => Security::encrypt((string) $row->arac_id),
                    'plaka' => (string) $row->plaka,
                    'arac_bilgi' => trim(($row->marka ?? '') . ' ' . ($row->model ?? '')),
                    'servis_adi' => (string) ($row->servis_adi ?? ''),
                    'servis_nedeni' => (string) ($row->servis_nedeni ?? ''),
                    'yapilan_islemler' => (string) ($row->yapilan_islemler ?? ''),
                    'servis_tarihi' => (string) $row->servis_tarihi,
                    'iade_tarihi' => $row->iade_tarihi ?: null,
                    'giris_km' => (int) ($row->giris_km ?? 0),
                    'cikis_km' => $row->cikis_km !== null ? (int) $row->cikis_km : null,
                    'tutar' => (float) ($row->tutar ?? 0),
                    'fatura_no' => (string) ($row->fatura_no ?? ''),
                    'is_aktif' => $isAktif,
                    'ikame_arac' => !empty($row->ikame_plaka) ? [
                        'id' => $row->ikame_arac_id ? Security::encrypt((string) $row->ikame_arac_id) : null,
                        'plaka' => (string) $row->ikame_plaka,
                        'marka' => (string) ($row->ikame_marka ?? ''),
                        'model' => (string) ($row->ikame_model ?? ''),
                        'alis_tarihi' => $row->ikame_alis_tarihi ?: null,
                        'iade_tarihi' => $row->ikame_iade_tarihi ?: null,
                    ] : null,
                ];
            }
        }

        aracApiResponse([
            'success' => true,
            'tab' => $tab,
            'summary' => [
                'toplam_arac' => $toplamArac,
                'aktif_arac' => $aktifArac,
                'pasif_arac' => $pasifArac,
                'zimmetli_sayisi' => $zimmetliSayi,
                'bosta_arac' => $bostaArac,
                'servisteki_sayisi' => $servistekiSayi,
                'ikame_arac_sayisi' => $ikameArac,
                'evrak_uyari_sayisi' => $evrakUyariSayisi,
                'totals' => $tabTotals,
            ],
            'items' => $items,
        ]);
    }

    // -------------------------------------------------------------
    // 2. TEKİL ARAÇ DETAYI (GET)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'detail') {
        $rawId = Security::decrypt((string) ($_GET['id'] ?? ''));
        $aracId = (int) $rawId;
        if ($aracId <= 0) {
            aracApiResponse(['success' => false, 'message' => 'Geçersiz araç ID.'], 400);
        }

        $arac = $Arac->getById($aracId);
        if (!$arac) {
            aracApiResponse(['success' => false, 'message' => 'Araç bulunamadı.'], 404);
        }

        // Son 5 yakıt kaydı
        $stmtYakit = $db->prepare("
            SELECT * FROM arac_yakit_kayitlari 
            WHERE arac_id = :arac_id AND firma_id = :firma_id AND silinme_tarihi IS NULL 
            ORDER BY tarih DESC LIMIT 5
        ");
        $stmtYakit->execute(['arac_id' => $aracId, 'firma_id' => $firmaId]);
        $sonYakitlar = $stmtYakit->fetchAll(PDO::FETCH_OBJ);

        // Son 5 servis kaydı
        $stmtServis = $db->prepare("
            SELECT * FROM arac_servis_kayitlari 
            WHERE arac_id = :arac_id AND firma_id = :firma_id AND silinme_tarihi IS NULL 
            ORDER BY servis_tarihi DESC LIMIT 5
        ");
        $stmtServis->execute(['arac_id' => $aracId, 'firma_id' => $firmaId]);
        $sonServisler = $stmtServis->fetchAll(PDO::FETCH_OBJ);

        // Son 5 zimmet kaydı
        $stmtZimmet = $db->prepare("
            SELECT az.*, p.adi_soyadi as personel_adi, p.departman as personel_departman
            FROM arac_zimmetleri az
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE az.arac_id = :arac_id AND az.firma_id = :firma_id AND az.silinme_tarihi IS NULL
            ORDER BY az.zimmet_tarihi DESC LIMIT 5
        ");
        $stmtZimmet->execute(['arac_id' => $aracId, 'firma_id' => $firmaId]);
        $sonZimmetler = $stmtZimmet->fetchAll(PDO::FETCH_OBJ);

        $muayeneKalan = calculateDaysLeft($arac->muayene_bitis_tarihi);
        $sigortaKalan = calculateDaysLeft($arac->sigorta_bitis_tarihi);
        $kaskoKalan = calculateDaysLeft($arac->kasko_bitis_tarihi);

        aracApiResponse([
            'success' => true,
            'data' => [
                'id' => Security::encrypt((string) $arac->id),
                'raw_id' => (int) $arac->id,
                'plaka' => (string) $arac->plaka,
                'marka' => (string) ($arac->marka ?? ''),
                'model' => (string) ($arac->model ?? ''),
                'model_yili' => (string) ($arac->model_yili ?? ''),
                'renk' => (string) ($arac->renk ?? ''),
                'arac_tipi' => (string) ($arac->arac_tipi ?? 'binek'),
                'yakit_tipi' => (string) ($arac->yakit_tipi ?? 'dizel'),
                'sase_no' => (string) ($arac->sase_no ?? ''),
                'motor_no' => (string) ($arac->motor_no ?? ''),
                'ruhsat_sahibi' => (string) ($arac->ruhsat_sahibi ?? ''),
                'mulkiyet' => (string) ($arac->mulkiyet ?? ''),
                'departmani' => (string) ($arac->departmani ?? ''),
                'baslangic_km' => (int) ($arac->baslangic_km ?? 0),
                'guncel_km' => (int) ($arac->guncel_km ?? 0),
                'aktif_mi' => (bool) $arac->aktif_mi,
                'ikame_mi' => (bool) $arac->ikame_mi,
                'serviste_mi' => ((int) ($arac->serviste_mi ?? 0) > 0),
                'notlar' => (string) ($arac->notlar ?? ''),
                'has_ruhsat' => !empty($arac->ruhsat_dosya_adi),
                'ruhsat_dosya_adi' => (string) ($arac->ruhsat_orijinal_ad ?? ''),
                'ruhsat_mime' => (string) ($arac->ruhsat_mime_tipi ?? ''),
                'ruhsat_boyutu' => (int) ($arac->ruhsat_boyutu ?? 0),
                'ruhsat_url' => !empty($arac->ruhsat_dosya_adi) ? ('/views/arac-takip/ruhsat-goruntule.php?id=' . Security::encrypt((string) $arac->id)) : null,
                'zimmetli_personel' => $arac->zimmetli_personel_adi ? [
                    'id' => Security::encrypt((string) $arac->zimmetli_personel_id),
                    'adi_soyadi' => (string) $arac->zimmetli_personel_adi,
                    'departman' => (string) ($arac->zimmetli_personel_departman ?? ''),
                    'resim_yolu' => (string) ($arac->zimmetli_personel_resim ?? ''),
                ] : null,
                'evraklar' => [
                    'muayene' => [
                        'tarih' => $arac->muayene_bitis_tarihi ?: null,
                        'kalan_gun' => $muayeneKalan,
                    ],
                    'sigorta' => [
                        'tarih' => $arac->sigorta_bitis_tarihi ?: null,
                        'kalan_gun' => $sigortaKalan,
                    ],
                    'kasko' => [
                        'tarih' => $arac->kasko_bitis_tarihi ?: null,
                        'kalan_gun' => $kaskoKalan,
                    ],
                ],
                'son_hareketler' => [
                    'yakit' => array_map(static fn($y) => [
                        'id' => Security::encrypt((string) $y->id),
                        'tarih' => (string) $y->tarih,
                        'km' => (int) $y->km,
                        'yakit_miktari' => (float) $y->yakit_miktari,
                        'toplam_tutar' => (float) $y->toplam_tutar,
                        'istasyon' => (string) ($y->istasyon ?? ''),
                    ], $sonYakitlar),
                    'servis' => array_map(static fn($s) => [
                        'id' => Security::encrypt((string) $s->id),
                        'tarih' => (string) $s->servis_tarihi,
                        'servis_adi' => (string) ($s->servis_adi ?? ''),
                        'servis_nedeni' => (string) ($s->servis_nedeni ?? ''),
                        'tutar' => (float) ($s->tutar ?? 0),
                        'tamamlandi' => !empty($s->iade_tarihi),
                    ], $sonServisler),
                    'zimmet' => array_map(static fn($z) => [
                        'id' => Security::encrypt((string) $z->id),
                        'personel' => (string) ($z->personel_adi ?? '-'),
                        'tarih' => (string) $z->zimmet_tarihi,
                        'iade_tarihi' => $z->iade_tarihi ?: null,
                        'durum' => (string) ($z->durum ?? 'aktif'),
                    ], $sonZimmetler),
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------
    // 3. PERSONEL LİSTESİ (GET)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'personeller') {
        $personeller = $Personel->all(false, 'arac') ?? [];
        $res = [];
        foreach ($personeller as $p) {
            $res[] = [
                'id' => Security::encrypt((string) $p->id),
                'raw_id' => (int) $p->id,
                'name' => (string) $p->adi_soyadi,
                'department' => (string) ($p->departman ?? ''),
                'avatar' => (string) ($p->resim_yolu ?? ''),
            ];
        }
        aracApiResponse(['success' => true, 'data' => $res]);
    }

    // =============================================================
    // POST İŞLEMLERİ (CSRF Korumalı)
    // =============================================================
    if ($method === 'POST') {
        verifyCsrfToken();

        // 3.1. ARAÇ KAYDET / GÜNCELLE
        if ($action === 'arac_kaydet') {
            $rawId = !empty($_POST['id']) ? Security::decrypt((string) $_POST['id']) : 0;
            $aracId = (int) $rawId;

            $plaka = strtoupper(trim((string) ($_POST['plaka'] ?? '')));
            if (empty($plaka)) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen araç plakasını belirtin.'], 400);
            }

            // Plaka mükerrerlik kontrolü
            $stmtCheck = $db->prepare("
                SELECT id FROM araclar 
                WHERE plaka = :plaka AND firma_id = :firma_id AND silinme_tarihi IS NULL AND id != :id
            ");
            $stmtCheck->execute(['plaka' => $plaka, 'firma_id' => $firmaId, 'id' => $aracId]);
            if ($stmtCheck->fetch()) {
                aracApiResponse(['success' => false, 'message' => "Bu plaka ({$plaka}) zaten kayıtlı."], 400);
            }

            $marka = trim((string) ($_POST['marka'] ?? ''));
            $model = trim((string) ($_POST['model'] ?? ''));
            $modelYili = !empty($_POST['model_yili']) ? (int) $_POST['model_yili'] : date('Y');
            $renk = trim((string) ($_POST['renk'] ?? ''));
            $aracTipi = trim((string) ($_POST['arac_tipi'] ?? 'binek'));
            $yakitTipi = trim((string) ($_POST['yakit_tipi'] ?? 'dizel'));
            $saseNo = trim((string) ($_POST['sase_no'] ?? ''));
            $baslangicKm = (int) ($_POST['baslangic_km'] ?? 0);
            $guncelKm = (int) ($_POST['guncel_km'] ?? $baslangicKm);
            $aktifMi = isset($_POST['aktif_mi']) ? (int) $_POST['aktif_mi'] : 1;
            $ikameMi = isset($_POST['ikame_mi']) ? (int) $_POST['ikame_mi'] : 0;
            $muayeneTarihi = !empty($_POST['muayene_tarihi']) ? $_POST['muayene_tarihi'] : null;
            $sigortaTarihi = !empty($_POST['sigorta_tarihi']) ? $_POST['sigorta_tarihi'] : null;
            $kaskoTarihi = !empty($_POST['kasko_tarihi']) ? $_POST['kasko_tarihi'] : null;
            $notlar = trim((string) ($_POST['notlar'] ?? ''));

            if ($aracId > 0) {
                // Güncelleme
                $stmt = $db->prepare("
                    UPDATE araclar SET 
                        plaka = :plaka, marka = :marka, model = :model, model_yili = :model_yili,
                        renk = :renk, arac_tipi = :arac_tipi, yakit_tipi = :yakit_tipi,
                        sase_no = :sase_no, baslangic_km = :baslangic_km, guncel_km = :guncel_km,
                        aktif_mi = :aktif_mi, ikame_mi = :ikame_mi,
                        muayene_bitis_tarihi = :muayene_tarihi,
                        sigorta_bitis_tarihi = :sigorta_tarihi,
                        kasko_bitis_tarihi = :kasko_tarihi,
                        notlar = :notlar,
                        guncelleme_tarihi = NOW()
                    WHERE id = :id AND firma_id = :firma_id
                ");
                $stmt->execute([
                    'plaka' => $plaka,
                    'marka' => $marka,
                    'model' => $model,
                    'model_yili' => $modelYili,
                    'renk' => $renk,
                    'arac_tipi' => $aracTipi,
                    'yakit_tipi' => $yakitTipi,
                    'sase_no' => $saseNo,
                    'baslangic_km' => $baslangicKm,
                    'guncel_km' => $guncelKm,
                    'aktif_mi' => $aktifMi,
                    'ikame_mi' => $ikameMi,
                    'muayene_tarihi' => $muayeneTarihi,
                    'sigorta_tarihi' => $sigortaTarihi,
                    'kasko_tarihi' => $kaskoTarihi,
                    'notlar' => $notlar,
                    'id' => $aracId,
                    'firma_id' => $firmaId,
                ]);

                $SystemLog->logAction($currentUserId, 'Araç Güncelleme', "{$plaka} plakalı araç güncellendi.", SystemLogModel::LEVEL_INFO);
                aracApiResponse(['success' => true, 'message' => 'Araç bilgileri başarıyla güncellendi.', 'id' => Security::encrypt((string) $aracId)]);
            } else {
                // Yeni Araç
                $stmt = $db->prepare("
                    INSERT INTO araclar (
                        firma_id, plaka, marka, model, model_yili, renk, arac_tipi, yakit_tipi,
                        sase_no, baslangic_km, guncel_km, aktif_mi, ikame_mi,
                        muayene_bitis_tarihi, sigorta_bitis_tarihi, kasko_bitis_tarihi, notlar,
                        olusturma_tarihi
                    ) VALUES (
                        :firma_id, :plaka, :marka, :model, :model_yili, :renk, :arac_tipi, :yakit_tipi,
                        :sase_no, :baslangic_km, :guncel_km, :aktif_mi, :ikame_mi,
                        :muayene_tarihi, :sigorta_tarihi, :kasko_tarihi, :notlar,
                        NOW()
                    )
                ");
                $stmt->execute([
                    'firma_id' => $firmaId,
                    'plaka' => $plaka,
                    'marka' => $marka,
                    'model' => $model,
                    'model_yili' => $modelYili,
                    'renk' => $renk,
                    'arac_tipi' => $aracTipi,
                    'yakit_tipi' => $yakitTipi,
                    'sase_no' => $saseNo,
                    'baslangic_km' => $baslangicKm,
                    'guncel_km' => $guncelKm,
                    'aktif_mi' => $aktifMi,
                    'ikame_mi' => $ikameMi,
                    'muayene_tarihi' => $muayeneTarihi,
                    'sigorta_tarihi' => $sigortaTarihi,
                    'kasko_tarihi' => $kaskoTarihi,
                    'notlar' => $notlar,
                ]);
                $newId = (int) $db->lastInsertId();

                $SystemLog->logAction($currentUserId, 'Yeni Araç Ekleme', "{$plaka} plakalı araç sisteme eklendi.", SystemLogModel::LEVEL_IMPORTANT);
                aracApiResponse(['success' => true, 'message' => 'Araç başarıyla eklendi.', 'id' => Security::encrypt((string) $newId)]);
            }
        }

        // 3.2. ARAÇ SİL (SOFT-DELETE)
        if ($action === 'arac_sil') {
            $rawId = Security::decrypt((string) ($_POST['id'] ?? ''));
            $aracId = (int) $rawId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz araç ID.'], 400);
            }

            $stmtCheck = $db->prepare("SELECT plaka FROM araclar WHERE id = :id AND firma_id = :firma_id");
            $stmtCheck->execute(['id' => $aracId, 'firma_id' => $firmaId]);
            $arac = $stmtCheck->fetch(PDO::FETCH_OBJ);
            if (!$arac) {
                aracApiResponse(['success' => false, 'message' => 'Araç bulunamadı.'], 404);
            }

            $stmt = $db->prepare("
                UPDATE araclar SET 
                    silinme_tarihi = NOW(),
                    aktif_mi = 0
                WHERE id = :id AND firma_id = :firma_id
            ");
            $stmt->execute(['id' => $aracId, 'firma_id' => $firmaId]);

            $SystemLog->logAction($currentUserId, 'Araç Silme', "{$arac->plaka} plakalı araç silindi.", SystemLogModel::LEVEL_WARNING);
            aracApiResponse(['success' => true, 'message' => 'Araç başarıyla silindi.']);
        }

        // 3.3. RUHSAT YÜKLE (AES-256 Şifreleme)
        if ($action === 'ruhsat_yukle') {
            $rawId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $aracId = (int) $rawId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz araç ID.'], 400);
            }

            if (empty($_FILES['ruhsat_dosyasi']) || $_FILES['ruhsat_dosyasi']['error'] !== UPLOAD_ERR_OK) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir dosya seçin.'], 400);
            }

            $file = $_FILES['ruhsat_dosyasi'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                aracApiResponse(['success' => false, 'message' => 'Desteklenmeyen dosya türü (Sadece JPG, PNG, WEBP, PDF).'], 400);
            }

            if ($file['size'] > (8 * 1024 * 1024)) {
                aracApiResponse(['success' => false, 'message' => 'Dosya boyutu 8MB\'ı geçemez.'], 400);
            }

            $targetDir = dirname(__DIR__, 3) . '/files/arac_ruhsat/' . $aracId . '/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $binaryData = file_get_contents($file['tmp_name']);
            if ($binaryData === false) {
                aracApiResponse(['success' => false, 'message' => 'Dosya okunamadı.'], 500);
            }

            $encryptedData = Security::encryptFile($binaryData);
            $newFileName = bin2hex(random_bytes(16)) . '.enc';
            if (file_put_contents($targetDir . $newFileName, $encryptedData) === false) {
                aracApiResponse(['success' => false, 'message' => 'Şifreli dosya kaydedilemedi.'], 500);
            }

            // Eski ruhsatı temizle
            $mevcut = $Arac->getRuhsatSahibiArac($aracId);
            if ($mevcut && !empty($mevcut->ruhsat_dosya_adi)) {
                $oldPath = $targetDir . $mevcut->ruhsat_dosya_adi;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            $Arac->updateRuhsatBilgisi($aracId, $newFileName, $file['name'], $mimeType, $file['size'], $currentUserId);
            $SystemLog->logAction($currentUserId, 'Ruhsat Yükleme', "Araç ID {$aracId} için şifreli ruhsat yüklendi.", SystemLogModel::LEVEL_INFO);

            aracApiResponse([
                'success' => true,
                'message' => 'Ruhsat görseli şifrelenerek yüklendi.',
                'ruhsat_url' => '/views/arac-takip/ruhsat-goruntule.php?id=' . Security::encrypt((string) $aracId),
            ]);
        }

        // 3.4. RUHSAT SİL
        if ($action === 'ruhsat_sil') {
            $rawId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $aracId = (int) $rawId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz araç ID.'], 400);
            }

            $mevcut = $Arac->getRuhsatSahibiArac($aracId);
            if ($mevcut && !empty($mevcut->ruhsat_dosya_adi)) {
                $oldPath = dirname(__DIR__, 3) . '/files/arac_ruhsat/' . $aracId . '/' . $mevcut->ruhsat_dosya_adi;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            $Arac->clearRuhsatBilgisi($aracId);
            $SystemLog->logAction($currentUserId, 'Ruhsat Silme', "Araç ID {$aracId} ruhsatı silindi.", SystemLogModel::LEVEL_INFO);

            aracApiResponse(['success' => true, 'message' => 'Ruhsat belgesi silindi.']);
        }

        // 3.5. ZİMMET VER
        if ($action === 'zimmet_ver') {
            $rawAracId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $rawPersonelId = Security::decrypt((string) ($_POST['personel_id'] ?? ''));
            $aracId = (int) $rawAracId;
            $personelId = (int) $rawPersonelId;

            if ($aracId <= 0 || $personelId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir araç ve personel seçin.'], 400);
            }

            $tarih = !empty($_POST['zimmet_tarihi']) ? $_POST['zimmet_tarihi'] : date('Y-m-d');
            $km = (int) ($_POST['teslim_km'] ?? 0);
            $notlar = trim((string) ($_POST['notlar'] ?? ''));

            // Önceki aktif zimmetleri iade et
            $stmtClose = $db->prepare("
                UPDATE arac_zimmetleri SET 
                    durum = 'iade_edildi',
                    iade_tarihi = :tarih,
                    iade_km = :km,
                    guncelleme_tarihi = NOW()
                WHERE arac_id = :arac_id AND firma_id = :firma_id AND durum = 'aktif'
            ");
            $stmtClose->execute(['tarih' => $tarih, 'km' => $km, 'arac_id' => $aracId, 'firma_id' => $firmaId]);

            // Yeni zimmet aç
            $stmt = $db->prepare("
                INSERT INTO arac_zimmetleri (
                    firma_id, arac_id, personel_id, zimmet_tarihi, teslim_km, durum, notlar,
                    olusturan_kullanici_id, olusturma_tarihi
                ) VALUES (
                    :firma_id, :arac_id, :personel_id, :zimmet_tarihi, :teslim_km, 'aktif', :notlar,
                    :kullanici_id, NOW()
                )
            ");
            $stmt->execute([
                'firma_id' => $firmaId,
                'arac_id' => $aracId,
                'personel_id' => $personelId,
                'zimmet_tarihi' => $tarih,
                'teslim_km' => $km,
                'notlar' => $notlar,
                'kullanici_id' => $currentUserId,
            ]);

            // Aracın güncel KM'sini de güncelle
            if ($km > 0) {
                $db->prepare("UPDATE araclar SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km) WHERE id = :id")->execute(['km' => $km, 'id' => $aracId]);
            }

            $SystemLog->logAction($currentUserId, 'Zimmet Verme', "Araç ID {$aracId}, Personel ID {$personelId} üzerine zimmetlendi.", SystemLogModel::LEVEL_IMPORTANT);
            aracApiResponse(['success' => true, 'message' => 'Araç başarıyla zimmetlendi.']);
        }

        // 3.6. ZİMMET İADE AL
        if ($action === 'zimmet_iade') {
            $rawZimmetId = Security::decrypt((string) ($_POST['zimmet_id'] ?? ''));
            $zimmetId = (int) $rawZimmetId;
            if ($zimmetId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz zimmet ID.'], 400);
            }

            $iadeTarihi = !empty($_POST['iade_tarihi']) ? $_POST['iade_tarihi'] : date('Y-m-d');
            $iadeKm = (int) ($_POST['iade_km'] ?? 0);
            $notlar = trim((string) ($_POST['iade_notu'] ?? ''));

            $stmtGet = $db->prepare("SELECT arac_id, teslim_km FROM arac_zimmetleri WHERE id = :id AND firma_id = :firma_id");
            $stmtGet->execute(['id' => $zimmetId, 'firma_id' => $firmaId]);
            $zimmet = $stmtGet->fetch(PDO::FETCH_OBJ);
            if (!$zimmet) {
                aracApiResponse(['success' => false, 'message' => 'Zimmet kaydı bulunamadı.'], 404);
            }

            $stmt = $db->prepare("
                UPDATE arac_zimmetleri SET 
                    durum = 'iade_edildi',
                    iade_tarihi = :iade_tarihi,
                    iade_km = :iade_km,
                    notlar = CONCAT(COALESCE(notlar, ''), '\n[İade Notu: ', :notlar, ']'),
                    guncelleme_tarihi = NOW()
                WHERE id = :id AND firma_id = :firma_id
            ");
            $stmt->execute([
                'iade_tarihi' => $iadeTarihi,
                'iade_km' => $iadeKm,
                'notlar' => $notlar,
                'id' => $zimmetId,
                'firma_id' => $firmaId,
            ]);

            if ($iadeKm > 0) {
                $db->prepare("UPDATE araclar SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km) WHERE id = :id")->execute(['km' => $iadeKm, 'id' => $zimmet->arac_id]);
            }

            $SystemLog->logAction($currentUserId, 'Zimmet İade', "Zimmet ID {$zimmetId} başarıyla iade alındı.", SystemLogModel::LEVEL_IMPORTANT);
            aracApiResponse(['success' => true, 'message' => 'Araç zimmeti başarıyla iade alındı.']);
        }

        // 3.7. YAKIT KAYDET
        if ($action === 'yakit_kaydet') {
            $rawAracId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $aracId = (int) $rawAracId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir araç seçin.'], 400);
            }

            $tarih = !empty($_POST['tarih']) ? $_POST['tarih'] : date('Y-m-d');
            $km = (int) ($_POST['km'] ?? 0);
            $litre = (float) str_replace(',', '.', (string) ($_POST['yakit_miktari'] ?? 0));
            $tutar = (float) str_replace(',', '.', (string) ($_POST['toplam_tutar'] ?? 0));
            $birimFiyat = ($litre > 0 && $tutar > 0) ? round($tutar / $litre, 2) : 0;
            $istasyon = trim((string) ($_POST['istasyon'] ?? ''));
            $faturaNo = trim((string) ($_POST['fatura_no'] ?? ''));
            $yakitTipi = trim((string) ($_POST['yakit_tipi'] ?? 'dizel'));
            $tamDepo = isset($_POST['tam_depo_mu']) ? (int) $_POST['tam_depo_mu'] : 1;
            $notlar = trim((string) ($_POST['notlar'] ?? ''));

            if ($tutar <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir yakıt tutarı girin.'], 400);
            }

            $stmt = $db->prepare("
                INSERT INTO arac_yakit_kayitlari (
                    firma_id, arac_id, tarih, km, yakit_miktari, birim_fiyat, toplam_tutar,
                    yakit_tipi, tam_depo_mu, istasyon, fatura_no, notlar,
                    olusturan_kullanici_id, olusturma_tarihi
                ) VALUES (
                    :firma_id, :arac_id, :tarih, :km, :litre, :birim_fiyat, :tutar,
                    :yakit_tipi, :tam_depo, :istasyon, :fatura_no, :notlar,
                    :kullanici_id, NOW()
                )
            ");
            $stmt->execute([
                'firma_id' => $firmaId,
                'arac_id' => $aracId,
                'tarih' => $tarih,
                'km' => $km,
                'litre' => $litre,
                'birim_fiyat' => $birimFiyat,
                'tutar' => $tutar,
                'yakit_tipi' => $yakitTipi,
                'tam_depo' => $tamDepo,
                'istasyon' => $istasyon,
                'fatura_no' => $faturaNo,
                'notlar' => $notlar,
                'kullanici_id' => $currentUserId,
            ]);

            if ($km > 0) {
                $db->prepare("UPDATE araclar SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km) WHERE id = :id")->execute(['km' => $km, 'id' => $aracId]);
            }

            $SystemLog->logAction($currentUserId, 'Yakıt Girişi', "Araç ID {$aracId} için {$tutar} ₺ tutarında yakıt kaydedildi.", SystemLogModel::LEVEL_INFO);
            aracApiResponse(['success' => true, 'message' => 'Yakıt kaydı başarıyla eklendi.']);
        }

        // 3.8. YAKIT SİL
        if ($action === 'yakit_sil') {
            $rawId = Security::decrypt((string) ($_POST['id'] ?? ''));
            $id = (int) $rawId;
            if ($id <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz kayıt.'], 400);
            }

            $stmt = $db->prepare("UPDATE arac_yakit_kayitlari SET silinme_tarihi = NOW(), silen_kullanici = :u WHERE id = :id AND firma_id = :firma_id");
            $stmt->execute(['u' => $currentUserId, 'id' => $id, 'firma_id' => $firmaId]);

            $SystemLog->logAction($currentUserId, 'Yakıt Silme', "Yakıt kaydı ID {$id} silindi.", SystemLogModel::LEVEL_INFO);
            aracApiResponse(['success' => true, 'message' => 'Yakıt kaydı silindi.']);
        }

        // 3.9. KM KAYDET
        if ($action === 'km_kaydet') {
            $rawAracId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $aracId = (int) $rawAracId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir araç seçin.'], 400);
            }

            $tarih = !empty($_POST['tarih']) ? $_POST['tarih'] : date('Y-m-d');
            $baslangicKm = (int) ($_POST['baslangic_km'] ?? 0);
            $bitisKm = (int) ($_POST['bitis_km'] ?? 0);
            $yapilanKm = (int) ($_POST['yapilan_km'] ?? max(0, $bitisKm - $baslangicKm));
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));

            if ($bitisKm <= 0 && $yapilanKm <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir KM değeri girin.'], 400);
            }

            $stmt = $db->prepare("
                INSERT INTO arac_km_kayitlari (
                    firma_id, arac_id, tarih, baslangic_km, bitis_km, yapilan_km, aciklama,
                    olusturan_kullanici_id, olusturma_tarihi
                ) VALUES (
                    :firma_id, :arac_id, :tarih, :baslangic_km, :bitis_km, :yapilan_km, :aciklama,
                    :kullanici_id, NOW()
                )
            ");
            $stmt->execute([
                'firma_id' => $firmaId,
                'arac_id' => $aracId,
                'tarih' => $tarih,
                'baslangic_km' => $baslangicKm,
                'bitis_km' => $bitisKm,
                'yapilan_km' => $yapilanKm,
                'aciklama' => $aciklama,
                'kullanici_id' => $currentUserId,
            ]);

            if ($bitisKm > 0) {
                $db->prepare("UPDATE araclar SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km) WHERE id = :id")->execute(['km' => $bitisKm, 'id' => $aracId]);
            }

            $SystemLog->logAction($currentUserId, 'KM Girişi', "Araç ID {$aracId} için KM kaydı eklendi (Bitiş: {$bitisKm}).", SystemLogModel::LEVEL_INFO);
            aracApiResponse(['success' => true, 'message' => 'KM kaydı başarıyla eklendi.']);
        }

        // 3.10. SERVİS KAYDET
        if ($action === 'servis_kaydet') {
            $rawAracId = Security::decrypt((string) ($_POST['arac_id'] ?? ''));
            $aracId = (int) $rawAracId;
            if ($aracId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir araç seçin.'], 400);
            }

            $servisTarihi = !empty($_POST['servis_tarihi']) ? $_POST['servis_tarihi'] : date('Y-m-d');
            $girisKm = (int) ($_POST['giris_km'] ?? 0);
            $servisAdi = trim((string) ($_POST['servis_adi'] ?? ''));
            $servisNedeni = trim((string) ($_POST['servis_nedeni'] ?? ''));
            $yapilanIslemler = trim((string) ($_POST['yapilan_islemler'] ?? ''));
            $tutar = (float) str_replace(',', '.', (string) ($_POST['tutar'] ?? 0));
            $faturaNo = trim((string) ($_POST['fatura_no'] ?? ''));

            $stmt = $db->prepare("
                INSERT INTO arac_servis_kayitlari (
                    firma_id, arac_id, servis_tarihi, giris_km, servis_adi, servis_nedeni,
                    yapilan_islemler, tutar, fatura_no, olusturan_kullanici_id, olusturma_tarihi
                ) VALUES (
                    :firma_id, :arac_id, :servis_tarihi, :giris_km, :servis_adi, :servis_nedeni,
                    :yapilan_islemler, :tutar, :fatura_no, :kullanici_id, NOW()
                )
            ");
            $stmt->execute([
                'firma_id' => $firmaId,
                'arac_id' => $aracId,
                'servis_tarihi' => $servisTarihi,
                'giris_km' => $girisKm,
                'servis_adi' => $servisAdi,
                'servis_nedeni' => $servisNedeni,
                'yapilan_islemler' => $yapilanIslemler,
                'tutar' => $tutar,
                'fatura_no' => $faturaNo,
                'kullanici_id' => $currentUserId,
            ]);

            $SystemLog->logAction($currentUserId, 'Servis Girişi', "Araç ID {$aracId} servise alındı: {$servisAdi}.", SystemLogModel::LEVEL_IMPORTANT);
            aracApiResponse(['success' => true, 'message' => 'Araç servis kaydı başarıyla oluşturuldu.']);
        }

        // 3.11. SERVİS TAMAMLA
        if ($action === 'servis_tamamla') {
            $rawServisId = Security::decrypt((string) ($_POST['servis_id'] ?? ''));
            $servisId = (int) $rawServisId;
            if ($servisId <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz servis ID.'], 400);
            }

            $iadeTarihi = !empty($_POST['iade_tarihi']) ? $_POST['iade_tarihi'] : date('Y-m-d');
            $cikisKm = (int) ($_POST['cikis_km'] ?? 0);
            $tutar = (float) str_replace(',', '.', (string) ($_POST['tutar'] ?? 0));
            $yapilanIslemler = trim((string) ($_POST['yapilan_islemler'] ?? ''));

            $stmtGet = $db->prepare("SELECT arac_id FROM arac_servis_kayitlari WHERE id = :id AND firma_id = :firma_id");
            $stmtGet->execute(['id' => $servisId, 'firma_id' => $firmaId]);
            $servis = $stmtGet->fetch(PDO::FETCH_OBJ);
            if (!$servis) {
                aracApiResponse(['success' => false, 'message' => 'Servis kaydı bulunamadı.'], 404);
            }

            $stmt = $db->prepare("
                UPDATE arac_servis_kayitlari SET 
                    iade_tarihi = :iade_tarihi,
                    cikis_km = :cikis_km,
                    tutar = CASE WHEN :tutar > 0 THEN :tutar ELSE tutar END,
                    yapilan_islemler = CASE WHEN :yapilan != '' THEN :yapilan ELSE yapilan_islemler END,
                    guncelleme_tarihi = NOW()
                WHERE id = :id AND firma_id = :firma_id
            ");
            $stmt->execute([
                'iade_tarihi' => $iadeTarihi,
                'cikis_km' => $cikisKm,
                'tutar' => $tutar,
                'yapilan' => $yapilanIslemler,
                'id' => $servisId,
                'firma_id' => $firmaId,
            ]);

            if ($cikisKm > 0) {
                $db->prepare("UPDATE araclar SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km) WHERE id = :id")->execute(['km' => $cikisKm, 'id' => $servis->arac_id]);
            }

            $SystemLog->logAction($currentUserId, 'Servis Tamamlama', "Servis kaydı ID {$servisId} tamamlandı ve araç teslim alındı.", SystemLogModel::LEVEL_IMPORTANT);
            aracApiResponse(['success' => true, 'message' => 'Araç servisten teslim alındı.']);
        }

        // 3.12. SERVİS SİL
        if ($action === 'servis_sil') {
            $rawId = Security::decrypt((string) ($_POST['id'] ?? ''));
            $id = (int) $rawId;
            if ($id <= 0) {
                aracApiResponse(['success' => false, 'message' => 'Geçersiz servis ID.'], 400);
            }

            $stmt = $db->prepare("UPDATE arac_servis_kayitlari SET silinme_tarihi = NOW(), silen_kullanici = :u WHERE id = :id AND firma_id = :firma_id");
            $stmt->execute(['u' => $currentUserId, 'id' => $id, 'firma_id' => $firmaId]);

            $SystemLog->logAction($currentUserId, 'Servis Silme', "Servis kaydı ID {$id} silindi.", SystemLogModel::LEVEL_INFO);
            aracApiResponse(['success' => true, 'message' => 'Servis kaydı silindi.']);
        }

        aracApiResponse(['success' => false, 'message' => 'Tanımsız işlem aksiyonu.'], 400);
    }
} catch (\Throwable $e) {
    error_log('Araç Takip API Hatası: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    aracApiResponse(['success' => false, 'message' => 'İşlem sırasında sunucu hatası oluştu: ' . $e->getMessage()], 500);
}
