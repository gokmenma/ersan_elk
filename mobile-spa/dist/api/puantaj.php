<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\MenuModel;
use App\Model\PersonelFazlaMesaileriModel;
use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;
use App\Model\NobetModel;
use App\Model\PuantajModel;
use App\Model\SettingsModel;
use App\Model\TanimlamalarModel;
use App\Service\Gate;
use App\Service\OkumaHtcAutomationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function puantajApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if ($userId <= 0 || $firmaId <= 0) {
    puantajApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

$menuModel = new MenuModel();
$hasAccess = Gate::allowsAny(['puantaj_raporlama', 'puantaj_yonetim', 'puantaj/raporlar', 'puantaj/list'])
    || $menuModel->userCanAccessMenuLink($userId, 'puantaj/list')
    || $menuModel->userCanAccessMenuLink($userId, 'puantaj/raporlar');

if (!$hasAccess) {
    puantajApiResponse(['success' => false, 'message' => 'Puantaj sayfasını görüntüleme yetkiniz bulunmuyor.'], 403);
}

function verifyPuantajCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        puantajApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

function resolveStatusColor(?string $code, ?string $dbColor): string
{
    $uc = mb_strtoupper((string) ($code ?: 'X'));
    if ($dbColor && is_string($dbColor) && str_starts_with($dbColor, '#') && strlen($dbColor) >= 4) {
        $lc = strtolower($dbColor);
        if ($lc !== '#ffffff' && $lc !== '#fff' && $lc !== '#f46a6a' && $lc !== '#556ee6') {
            return $dbColor;
        }
    }

    $palette = [
        'X'   => '#2563eb', // Mavi
        'HT'  => '#d97706', // Turuncu/Amber
        'Yİ'  => '#059669', // Yeşil
        'Üİ'  => '#7c3aed', // Mor
        'SGK' => '#dc2626', // Kırmızı
        'RP'  => '#dc2626', // Kırmızı
        'Mİ'  => '#0891b2', // Siyan
        'Eİ'  => '#4f46e5', // İndigo
        'HTÇ' => '#b45309', // Koyu Amber
        'RTÇ' => '#0f766e', // Koyu Teal
        'Bİ'  => '#6d28d9', // Koyu Mor
        'D'   => '#b91c1c', // Bordo/Kırmızı
        'UZ'  => '#0284c7', // Açık Mavi
    ];

    return $palette[$uc] ?? '#2563eb';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'calendar-data');

// JSON POST payload desteği
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && $rawInput !== '') {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            foreach ($json as $k => $v) {
                if (!isset($_POST[$k])) {
                    $_POST[$k] = $v;
                }
            }
            if (!empty($json['action'])) {
                $action = (string) $json['action'];
            }
        }
    }
}

$Tanimlar = new TanimlamalarModel();
$Personel = new PersonelModel();
$Puantaj = new PuantajModel();
$PersonelIzinleri = new PersonelIzinleriModel();
$FazlaMesai = new PersonelFazlaMesaileriModel();

try {
    // 1. TANIMLAMALAR (Definitions)
    if ($action === 'definitions') {
        // İzin türleri: Önce mevcut firmaya ait varsa o, yoksa sistemdeki aktif izin türleri
        $allStmt = $Tanimlar->db->prepare(
            "SELECT id, tur_adi, kisa_kod, ucretli_mi, renk, ikon, firma_id 
             FROM tanimlamalar 
             WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL 
             ORDER BY (CASE WHEN firma_id = ? THEN 0 WHEN firma_id = 0 THEN 1 ELSE 2 END), id ASC"
        );
        $allStmt->execute([$firmaId]);
        $rawList = $allStmt->fetchAll(PDO::FETCH_OBJ);

        $seenCodes = [];
        $ucretliList = [];
        $ucretsizList = [];

        foreach ($rawList as $row) {
            $code = mb_strtoupper((string)($row->kisa_kod ?: mb_substr((string)$row->tur_adi, 0, 2)));
            if (isset($seenCodes[$code])) {
                continue;
            }
            $seenCodes[$code] = true;
            $row->id = (int) $row->id;
            $row->kisa_kod = $code;
            $row->renk = resolveStatusColor($code, $row->renk);

            if ((int) $row->ucretli_mi === 1) {
                $ucretliList[] = $row;
            } else {
                $ucretsizList[] = $row;
            }
        }

        // SGK Raporu yoksa ekle (varsa RP'nin id'sini bağla)
        if (!isset($seenCodes['SGK'])) {
            $rpId = 50;
            foreach ($ucretsizList as $u) {
                if ($u->kisa_kod === 'RP') {
                    $rpId = (int)$u->id;
                    break;
                }
            }
            $ucretsizList[] = (object) [
                'id' => $rpId,
                'tur_adi' => 'SGK Raporu',
                'kisa_kod' => 'SGK',
                'ucretli_mi' => 0,
                'renk' => '#dc2626',
                'ikon' => 'medical_services',
            ];
        }

        // Departmanlar
        $depStmt = $Personel->db->prepare(
            "SELECT DISTINCT pgg.departman 
             FROM personel_gorev_gecmisi pgg 
             INNER JOIN personel p ON pgg.personel_id = p.id 
             WHERE p.firma_id = ? AND pgg.departman IS NOT NULL AND pgg.departman != '' 
             ORDER BY pgg.departman ASC"
        );
        $depStmt->execute([$firmaId]);
        $departmanlar = $depStmt->fetchAll(PDO::FETCH_COLUMN);

        // Bölgeler
        $bolgeStmt = $Tanimlar->db->prepare(
            "SELECT DISTINCT ekip_bolge 
             FROM tanimlamalar 
             WHERE grup = 'ekip_kodu' AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL AND ekip_bolge IS NOT NULL AND ekip_bolge != '' 
             ORDER BY ekip_bolge ASC"
        );
        $bolgeStmt->execute([$firmaId]);
        $bolgeler = $bolgeStmt->fetchAll(PDO::FETCH_COLUMN);

        puantajApiResponse([
            'success' => true,
            'data' => [
                'ucretli' => $ucretliList,
                'ucretsiz' => $ucretsizList,
                'departmanlar' => $departmanlar,
                'bolgeler' => $bolgeler,
            ],
        ]);
    }

    // 2. TAKVİM VE PUANTAJ VERİLERİ (Calendar Data)
    if ($action === 'calendar-data') {
        $ay = str_pad((string) ($_GET['ay'] ?? $_POST['ay'] ?? date('m')), 2, '0', STR_PAD_LEFT);
        $yil = (string) ($_GET['yil'] ?? $_POST['yil'] ?? date('Y'));
        $departman = trim((string) ($_GET['departman'] ?? $_POST['departman'] ?? ''));
        $bolge = trim((string) ($_GET['bolge'] ?? $_POST['bolge'] ?? ''));
        $iskurDahil = (int) ($_GET['iskur_dahil'] ?? $_POST['iskur_dahil'] ?? 1);

        $startDate = "{$yil}-{$ay}-01";
        $endDate = date('Y-m-t', (int) strtotime($startDate));

        $sql = "
            SELECT p.id, p.adi_soyadi, p.resim_yolu, p.personel_resim_yolu, 
                   COALESCE(gg_dep.departman, p.departman, '') AS departman, 
                   p.ekip_no, p.tc_kimlik_no, 
                   COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) AS isten_cikis_tarihi, 
                   COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) AS ise_giris_tarihi,
                   CASE WHEN gg.personel_id IS NOT NULL THEN 1 ELSE 0 END AS gorev_gecmisi_var,
                   COALESCE(gg_days.toplam_gun, 0) AS gg_toplam_gun
            FROM personel p
            LEFT JOIN personel_calisma_gecmisi pcg ON p.id = pcg.personel_id 
                AND pcg.ise_giris_tarihi <= :end_date_cal 
                AND (pcg.isten_cikis_tarihi IS NULL OR pcg.isten_cikis_tarihi >= :start_date_cal)
            LEFT JOIN (
                SELECT DISTINCT pgg.personel_id
                FROM personel_gorev_gecmisi pgg
                WHERE pgg.baslangic_tarihi <= :end_date1
                  AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= :start_date1)
            ) gg ON p.id = gg.personel_id
            LEFT JOIN (
                SELECT pgg.personel_id, 
                       SUM(DATEDIFF(LEAST(COALESCE(pgg.bitis_tarihi, :end_date2), :end_date3), GREATEST(pgg.baslangic_tarihi, :start_date2)) + 1) AS toplam_gun
                FROM personel_gorev_gecmisi pgg
                WHERE pgg.baslangic_tarihi <= :end_date4 AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= :start_date3)
                GROUP BY pgg.personel_id
            ) gg_days ON p.id = gg_days.personel_id
            LEFT JOIN (
                SELECT pgg_dep.personel_id, pgg_dep.departman 
                FROM personel_gorev_gecmisi pgg_dep
                INNER JOIN (
                    SELECT personel_id, MAX(baslangic_tarihi) AS latest_start 
                    FROM personel_gorev_gecmisi 
                    WHERE baslangic_tarihi <= :end_date_dep AND (bitis_tarihi IS NULL OR bitis_tarihi >= :start_date_dep) 
                    GROUP BY personel_id
                ) gg_latest ON pgg_dep.personel_id = gg_latest.personel_id AND pgg_dep.baslangic_tarihi = gg_latest.latest_start
            ) gg_dep ON p.id = gg_dep.personel_id
            LEFT JOIN tanimlamalar t_ekip ON p.ekip_no = t_ekip.id AND t_ekip.grup = 'ekip_kodu' AND t_ekip.silinme_tarihi IS NULL
            WHERE p.firma_id = :firma_id 
            AND p.silinme_tarihi IS NULL 
            AND (p.aktif_mi = 1 OR (COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) IS NOT NULL AND COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) >= :start_date5))
            AND (COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) IS NULL OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) = '' OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) = '0000-00-00' OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) >= :start_date6)
            AND (COALESCE(pcg.disardan_sigortali, p.disardan_sigortali) = 0 OR FIND_IN_SET('puantaj', COALESCE(pcg.gorunum_modulleri, p.gorunum_modulleri)))
            AND (COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) IS NULL OR COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) = '' OR COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) <= :end_date5)
            AND NOT EXISTS (
                SELECT 1 FROM personel_gorev_gecmisi pgg_not
                WHERE pgg_not.personel_id = p.id
                AND pgg_not.baslangic_tarihi <= :end_date6
                AND (pgg_not.bitis_tarihi IS NULL OR pgg_not.bitis_tarihi >= :start_date7)
                AND (pgg_not.maas_durumu = 'Maaş Hesaplanmayan')
            )
        ";

        if ($iskurDahil === 0) {
            $sql .= " AND NOT (
                COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%İŞKUR%'
                OR COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%ISKUR%'
                OR COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%KUR%'
            ) ";
        }

        if ($departman !== '') {
            $sql .= ' AND gg_dep.departman = :departman ';
        }

        if ($bolge !== '') {
            $sql .= ' AND t_ekip.ekip_bolge = :bolge ';
        }

        $sql .= ' ORDER BY p.adi_soyadi ASC ';

        $stmt = $Personel->db->prepare($sql);
        $params = [
            ':end_date_cal'   => $endDate,
            ':start_date_cal' => $startDate,
            ':end_date1'      => $endDate,
            ':start_date1'    => $startDate,
            ':end_date2'      => $endDate,
            ':end_date3'      => $endDate,
            ':start_date2'    => $startDate,
            ':end_date4'      => $endDate,
            ':start_date3'    => $startDate,
            ':end_date_dep'   => $endDate,
            ':start_date_dep' => $startDate,
            ':firma_id'       => $firmaId,
            ':start_date5'    => $startDate,
            ':start_date6'    => $startDate,
            ':end_date5'      => $endDate,
            ':end_date6'      => $endDate,
            ':start_date7'    => $startDate,
        ];

        if ($departman !== '') {
            $params[':departman'] = $departman;
        }
        if ($bolge !== '') {
            $params[':bolge'] = $bolge;
        }

        $stmt->execute($params);
        $rawPersonelList = array_map(fn($r) => $Personel->decryptFields($r), $stmt->fetchAll(PDO::FETCH_OBJ));

        // Tekilleştirme
        $personelTekil = [];
        foreach ($rawPersonelList as $pRow) {
            $personelTekil[(int) $pRow->id] = $pRow;
        }
        $personelList = array_values($personelTekil);

        // Varsayılan X ve HT tanımları
        $vxStmt = $Tanimlar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'X' OR kisa_kod = 'x' OR tur_adi LIKE '%Çalışılan Gün%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $vxStmt->execute([$firmaId]);
        $xTanim = $vxStmt->fetch(PDO::FETCH_OBJ);

        $vhtStmt = $Tanimlar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'HT' OR kisa_kod = 'ht' OR tur_adi LIKE '%Hafta Tatili%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $vhtStmt->execute([$firmaId]);
        $htTanim = $vhtStmt->fetch(PDO::FETCH_OBJ);

        // İzinler
        $izinStmt = $PersonelIzinleri->db->prepare("
            SELECT pi.personel_id, pi.id, pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.tur_adi, t.kisa_kod, t.renk 
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.silinme_tarihi IS NULL AND pi.onay_durumu = 'Onaylandı'
            AND pi.personel_id IN (SELECT id FROM personel WHERE firma_id = ? AND silinme_tarihi IS NULL)
            AND (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
        ");
        $izinStmt->execute([$firmaId, $endDate, $startDate]);
        $allIzinler = $izinStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_OBJ);

        // Çalışma geçmişi
        $calismaStmt = $Personel->db->prepare("
            SELECT pcg.personel_id, pcg.ise_giris_tarihi, pcg.isten_cikis_tarihi
            FROM personel_calisma_gecmisi pcg
            INNER JOIN personel p ON p.id = pcg.personel_id
            WHERE p.firma_id = ? AND p.silinme_tarihi IS NULL
              AND pcg.ise_giris_tarihi <= ?
              AND (pcg.isten_cikis_tarihi IS NULL OR pcg.isten_cikis_tarihi = '0000-00-00' OR pcg.isten_cikis_tarihi >= ?)
            ORDER BY pcg.personel_id, pcg.ise_giris_tarihi
        ");
        $calismaStmt->execute([$firmaId, $endDate, $startDate]);
        $calismaDonemleri = $calismaStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        // Fazla mesailer
        $personelIdsAll = array_map(static fn($p) => (int) $p->id, $personelList);
        $allMesailer = $FazlaMesai->getPersonellerAyMesaileri($personelIdsAll, $startDate, $endDate, $firmaId);
        $Nobet = new NobetModel();
        $allNobetMesaileri = $Nobet->getPuantajHaftaIciMesaileri($personelIdsAll, $startDate, $endDate, $firmaId);

        $data = [];

        foreach ($personelList as $p) {
            $pCalismaDonemleri = array_map(static fn($donem) => [
                'baslangic' => $donem['ise_giris_tarihi'],
                'bitis'     => (!empty($donem['isten_cikis_tarihi']) && $donem['isten_cikis_tarihi'] !== '0000-00-00')
                    ? $donem['isten_cikis_tarihi']
                    : null,
            ], $calismaDonemleri[$p->id] ?? []);

            $resimPath = !empty($p->personel_resim_yolu) ? $p->personel_resim_yolu : ($p->resim_yolu ?? '');
            if (!empty($resimPath) && !str_starts_with($resimPath, 'http') && !str_starts_with($resimPath, '/')) {
                $resimPath = '/' . ltrim($resimPath, './');
            }

            $pInfo = [
                'id'                 => (int) $p->id,
                'encrypt_id'         => Security::encrypt((string) $p->id),
                'adi_soyadi'         => (string) $p->adi_soyadi,
                'tc_kimlik_no'       => (string) ($p->tc_kimlik_no ?? ''),
                'departman'          => (string) ($p->departman ?? ''),
                'resim'              => (string) $resimPath,
                'isten_cikis_tarihi' => $p->isten_cikis_tarihi,
                'ise_giris_tarihi'   => $p->ise_giris_tarihi,
                'gorev_gecmisi_var'  => (int) $p->gorev_gecmisi_var,
                'gg_toplam_gun'      => (int) $p->gg_toplam_gun,
                'calisma_donemleri'  => $pCalismaDonemleri,
                'entries'            => [],
                'overtimes'          => $allMesailer[(int) $p->id] ?? (object) [],
                'shift_overtimes'    => $allNobetMesaileri[(int) $p->id] ?? (object) [],
            ];

            $izinler = $allIzinler[$p->id] ?? [];
            $mevcutGunler = [];

            foreach ($izinler as $izin) {
                $cur = strtotime($izin->baslangic_tarihi);
                $end = strtotime($izin->bitis_tarihi);
                while ($cur <= $end) {
                    $dateStr = date('Y-m-d', $cur);
                    if ($dateStr >= $startDate && $dateStr <= $endDate) {
                        $pInfo['entries'][$dateStr][] = [
                            'type'     => 'izin',
                            'id'       => (int) $izin->id,
                            'tip_id'   => (int) $izin->izin_tipi_id,
                            'name'     => (string) $izin->tur_adi,
                            'kisa_kod' => (string) ($izin->kisa_kod ?: mb_substr((string) $izin->tur_adi, 0, 2)),
                            'color'    => resolveStatusColor($izin->kisa_kod, $izin->renk ?: '#34c38f'),
                        ];
                        $mevcutGunler[$dateStr] = true;
                    }
                    $cur = strtotime('+1 day', $cur);
                }
            }

            // Boş günleri X ve HT ile doldur
            $cur = strtotime($startDate);
            $end = strtotime($endDate);
            $pGiris = ($p->ise_giris_tarihi && $p->ise_giris_tarihi !== '0000-00-00') ? strtotime($p->ise_giris_tarihi) : 0;
            $pCikis = ($p->isten_cikis_tarihi && $p->isten_cikis_tarihi !== '0000-00-00') ? strtotime($p->isten_cikis_tarihi) : PHP_INT_MAX;

            while ($cur <= $end) {
                $dateStr = date('Y-m-d', $cur);
                $isWeekend = ((int) date('w', $cur) === 0); // Sadece Pazar Hafta Tatili
                if (!isset($mevcutGunler[$dateStr])) {
                    $aktifCalismaGunu = empty($pCalismaDonemleri)
                        ? ($cur >= $pGiris && $cur <= $pCikis)
                        : (bool) array_filter($pCalismaDonemleri, static function ($donem) use ($dateStr) {
                            return $dateStr >= $donem['baslangic']
                                && ($donem['bitis'] === null || $dateStr <= $donem['bitis']);
                        });

                    if ($aktifCalismaGunu) {
                        if ($isWeekend && $htTanim) {
                            $pInfo['entries'][$dateStr][] = [
                                'type'     => 'default',
                                'id'       => 0,
                                'tip_id'   => (int) $htTanim->id,
                                'name'     => (string) $htTanim->tur_adi,
                                'kisa_kod' => (string) ($htTanim->kisa_kod ?: 'HT'),
                                'color'    => resolveStatusColor($htTanim->kisa_kod ?: 'HT', $htTanim->renk ?: '#d97706'),
                            ];
                        } elseif (!$isWeekend && $xTanim) {
                            $pInfo['entries'][$dateStr][] = [
                                'type'     => 'default',
                                'id'       => 0,
                                'tip_id'   => (int) $xTanim->id,
                                'name'     => (string) $xTanim->tur_adi,
                                'kisa_kod' => (string) ($xTanim->kisa_kod ?: 'X'),
                                'color'    => resolveStatusColor($xTanim->kisa_kod ?: 'X', $xTanim->renk ?: '#2563eb'),
                            ];
                        }
                    }
                }
                $cur = strtotime('+1 day', $cur);
            }

            $data[] = $pInfo;
        }

        puantajApiResponse([
            'success' => true,
            'data' => $data,
            'period' => [
                'ay' => $ay,
                'yil' => (int) $yil,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_in_month' => (int) date('t', (int) strtotime($startDate)),
            ],
        ]);
    }

    // 3. TOPLU PUANTAJ KAYDETME (Save Bulk Entries)
    if ($action === 'save-bulk-entries' || $action === 'save-bulk') {
        verifyPuantajCsrf();

        $data = $_POST['data'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        $overtimes = $_POST['overtimes'] ?? [];
        if (is_string($overtimes)) {
            $overtimes = json_decode($overtimes, true) ?: [];
        }
        $ay = str_pad((string) ($_POST['ay'] ?? date('m')), 2, '0', STR_PAD_LEFT);
        $yil = (string) ($_POST['yil'] ?? date('Y'));

        if ((empty($data) || !is_array($data)) && (empty($overtimes) || !is_array($overtimes))) {
            puantajApiResponse(['success' => false, 'message' => 'Kaydedilecek değişiklik verisi bulunamadı.'], 400);
        }

        $startDate = "{$yil}-{$ay}-01";
        $endDate = date('Y-m-t', (int) strtotime($startDate));

        $allTypesStmt = $Tanimlar->db->query("SELECT id, kisa_kod, tur_adi FROM tanimlamalar WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL");
        $codeMap = [];
        while ($tRow = $allTypesStmt->fetch(PDO::FETCH_OBJ)) {
            $c = mb_strtoupper((string)($tRow->kisa_kod ?: mb_substr((string)$tRow->tur_adi, 0, 2)));
            if (!isset($codeMap[$c])) {
                $codeMap[$c] = (int)$tRow->id;
            }
        }
        if (!isset($codeMap['SGK']) && isset($codeMap['RP'])) {
            $codeMap['SGK'] = $codeMap['RP'];
        }

        $Puantaj->db->beginTransaction();

        try {
            $htcAutomation = new OkumaHtcAutomationService();
            $deleteStmt = $Puantaj->db->prepare("
                UPDATE personel_izinleri
                SET silinme_tarihi = NOW(), silen_kullanici = ?,
                    silinme_aciklama = 'Mobil puantaj ekranından manuel olarak değiştirildi.'
                WHERE personel_id = ? 
                AND silinme_tarihi IS NULL
                AND (
                    (baslangic_tarihi >= ? AND baslangic_tarihi <= ?)
                    OR (bitis_tarihi >= ? AND bitis_tarihi <= ?)
                    OR (baslangic_tarihi <= ? AND bitis_tarihi >= ?)
                )
            ");

            $insertStmt = $Puantaj->db->prepare("
                INSERT INTO personel_izinleri 
                (personel_id, izin_tipi_id, baslangic_tarihi, bitis_tarihi, toplam_gun, onay_durumu,
                 olusturan_id, olusturma_tarihi, kaynak, manuel_kilit) 
                VALUES (?, ?, ?, ?, ?, 'Onaylandı', ?, NOW(), 'manuel', 1)
            ");

            $savedCount = 0;

            if (!empty($data) && is_array($data)) {
                foreach ($data as $row) {
                    $personelId = (int) ($row['personel_id'] ?? 0);
                    $typeId = isset($row['type_id']) ? (int) $row['type_id'] : null;
                    $code = mb_strtoupper(trim((string) ($row['code'] ?? $row['status_code'] ?? '')));
                    $date = trim((string) ($row['date'] ?? $row['baslangic_tarihi'] ?? ''));

                    if ($personelId <= 0 || empty($date)) {
                        continue;
                    }

                    // O günkü çakışan izinleri temizle
                    $deleteStmt->execute([$userId, $personelId, $date, $date, $date, $date, $date, $date]);
                    $htcAutomation->lockManualOverride($firmaId, $personelId, $date, $userId, $code ?: 'X');

                    // Eğer code === 'X' ise sadece çakışan izin silindi, normal çalışılan güne döndü
                    if ($code === 'X') {
                        $savedCount++;
                        continue;
                    }

                    // typeId tanımsız veya 0 ise kod haritasından bul
                    if (($typeId === null || $typeId === 0) && !empty($code) && isset($codeMap[$code])) {
                        $typeId = $codeMap[$code];
                    }

                    // Eğer geçerli bir izin tipi ise kaydet
                    if ($typeId !== null && $typeId > 0) {
                        $insertStmt->execute([
                            $personelId,
                            $typeId,
                            $date,
                            $date,
                            1,
                            $userId,
                        ]);
                        $savedCount++;
                    }
                }
            }

            // Fazla Mesai Değişikliklerini İşle
            if (!empty($overtimes) && is_array($overtimes)) {
                foreach ($overtimes as $ot) {
                    $otPersonelId = (int) ($ot['personel_id'] ?? 0);
                    $otTarih = trim((string) ($ot['tarih'] ?? $ot['date'] ?? ''));
                    $otSaat = (float) ($ot['saat'] ?? 0);

                    if ($otPersonelId > 0 && !empty($otTarih)) {
                        if ($otSaat > 0) {
                            $FazlaMesai->saveMesai($otPersonelId, $otTarih, $otSaat, $firmaId, $userId);
                        } else {
                            $FazlaMesai->deleteMesai($otPersonelId, $otTarih, $userId);
                        }
                        $savedCount++;
                    }
                }
            }

            $Puantaj->db->commit();

            puantajApiResponse([
                'success' => true,
                'message' => 'Puantaj değişiklikleri başarıyla kaydedildi.',
                'saved_count' => $savedCount,
            ]);
        } catch (\Throwable $e) {
            $Puantaj->db->rollBack();
            error_log('Puantaj save error: ' . $e->getMessage());
            puantajApiResponse(['success' => false, 'message' => 'Kayıt sırasında hata oluştu: ' . $e->getMessage()], 500);
        }
    }

    // 4. SGK ONAYLANMIŞ RAPORLARI (SGK Reports)
    if ($action === 'sgk-reports' || $action === 'get-sgk-onaylanmis-raporlar') {
        $ay = str_pad((string) ($_GET['ay'] ?? $_POST['ay'] ?? date('m')), 2, '0', STR_PAD_LEFT);
        $yil = (string) ($_GET['yil'] ?? $_POST['yil'] ?? date('Y'));

        $tarih1 = new DateTime("{$yil}-{$ay}-01");
        $tarih2 = new DateTime($tarih1->format('Y-m-t'));

        $Settings = new SettingsModel();
        $allSettings = $Settings->getAllSettingsAsKeyValue($firmaId);

        $kullaniciAdi = (string) ($allSettings['sgk_kullanici_adi'] ?? '');
        $isyeriKodu = (string) ($allSettings['sgk_isyeri_kodu'] ?? '');
        $encryptedPassword = (string) ($allSettings['sgk_isyeri_sifresi'] ?? '');
        $isyeriSifresi = !empty($encryptedPassword) ? Security::decrypt($encryptedPassword) : '';

        if (empty($kullaniciAdi) || empty($isyeriKodu) || empty($isyeriSifresi)) {
            puantajApiResponse([
                'success' => false,
                'configured' => false,
                'message' => 'SGK vizite entegrasyonu henüz yapılandırılmamış. Lütfen Ayarlar > SGK Vizite Ayarları bölümünü kontrol edin.',
                'data' => [],
            ]);
        }

        try {
            $servicePath = dirname(__DIR__, 3) . '/App/Service/SgkViziteService.php';
            if (!file_exists($servicePath)) {
                throw new \Exception('SgkViziteService dosyası sunucuda bulunamadı.');
            }
            require_once $servicePath;
            $sgkService = new \SgkViziteService($kullaniciAdi, $isyeriKodu, $isyeriSifresi);
            $raporlar = $sgkService->onayliRaporlariGetir($tarih1, $tarih2);
        } catch (\Throwable $e) {
            error_log('SGK Service error: ' . $e->getMessage());
            puantajApiResponse([
                'success' => false,
                'configured' => true,
                'message' => 'SGK servisinden raporlar çekilirken hata oluştu: ' . $e->getMessage(),
                'data' => [],
            ]);
        }

        // Personel listesi (TC eşleştirmesi için)
        $personelStmt = $Personel->db->prepare("
            SELECT id, adi_soyadi, tc_hash 
            FROM personel 
            WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL 
              AND (disardan_sigortali = 0 OR FIND_IN_SET('puantaj', gorunum_modulleri))
        ");
        $personelStmt->execute([$firmaId]);
        $personelList = $personelStmt->fetchAll(PDO::FETCH_OBJ);

        $tcToPersonel = [];
        foreach ($personelList as $p) {
            if (!empty($p->tc_hash)) {
                $tcToPersonel[$p->tc_hash] = [
                    'id' => (int) $p->id,
                    'adi_soyadi' => (string) $p->adi_soyadi,
                ];
            }
        }

        $islenecekRaporlar = [];
        foreach ($raporlar as $rapor) {
            $tc = (string) ($rapor['TCKIMLIKNO'] ?? '');
            $personelData = $tc !== '' ? ($tcToPersonel[hash('sha256', $tc)] ?? null) : null;

            $baslangicRaw = '';
            $checkKeysStart = ['ABASTAR', 'istirahatBaslangicTarihi', 'POLIKLINIKTAR', 'istirahatBaslangic', 'raporBaslangicTarihi', 'YATRAPBASTAR'];
            foreach ($checkKeysStart as $key) {
                if (!empty($rapor[$key])) {
                    $baslangicRaw = (string) $rapor[$key];
                    break;
                }
            }

            $bitisRaw = '';
            $checkKeysEnd = ['ISBASKONTTAR', 'ABITTAR', 'istirahatBitisTarihi', 'istirahatBitis', 'raporBitisTarihi', 'YATRAPBITTAR'];
            foreach ($checkKeysEnd as $key) {
                if (!empty($rapor[$key])) {
                    $bitisRaw = (string) $rapor[$key];
                    break;
                }
            }

            $baslangic = '';
            $bitis = '';
            $toplamGun = 0;

            try {
                if ($baslangicRaw !== '') {
                    $bDate = (str_contains($baslangicRaw, '.')) ? DateTime::createFromFormat('d.m.Y', $baslangicRaw) : new DateTime($baslangicRaw);
                    if ($bDate) {
                        $baslangic = $bDate->format('Y-m-d');
                    }
                }
                if ($bitisRaw !== '') {
                    $eDate = (str_contains($bitisRaw, '.')) ? DateTime::createFromFormat('d.m.Y', $bitisRaw) : new DateTime($bitisRaw);
                    if ($eDate) {
                        $eDate->modify('-1 day');
                        $bitis = $eDate->format('Y-m-d');
                    }
                }
                if ($baslangic !== '' && $bitis !== '') {
                    $d1 = new DateTime($baslangic);
                    $d2 = new DateTime($bitis);
                    $toplamGun = (int) $d1->diff($d2)->days + 1;
                    if ($d1 > $d2) {
                        $toplamGun = 0;
                    }
                }
            } catch (\Throwable $e) {
            }

            $islenecekRaporlar[] = [
                'personel_id'   => $personelData ? $personelData['id'] : null,
                'ad_soyad'      => $personelData ? $personelData['adi_soyadi'] : (string) ($rapor['ADSOYAD'] ?? 'Bilinmeyen Personel'),
                'tc_kimlik'     => $tc,
                'baslangic'     => $baslangic,
                'bitis'         => $bitis,
                'baslangic_raw' => $baslangicRaw,
                'bitis_raw'     => $bitisRaw,
                'toplam_gun'    => $toplamGun,
                'vaka_adi'      => (string) ($rapor['VAKAADI'] ?? $rapor['vakaAdi'] ?? 'Hastalık'),
                'teshis'        => (string) ($rapor['TESHIS'] ?? ''),
            ];
        }

        puantajApiResponse([
            'success' => true,
            'configured' => true,
            'data' => $islenecekRaporlar,
        ]);
    }

    puantajApiResponse(['success' => false, 'message' => "Bilinmeyen işlem: {$action}"], 400);
} catch (\Throwable $e) {
    error_log('Puantaj API exception: ' . $e->getMessage());
    puantajApiResponse([
        'success' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage(),
    ], 500);
}
