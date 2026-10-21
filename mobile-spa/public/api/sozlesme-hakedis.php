<?php

declare(strict_types=1);

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\HakedisDonemModel;
use App\Model\HakedisKalemModel;
use App\Model\HakedisMiktarModel;
use App\Model\HakedisSozlesmeModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function contractsApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkContractsAuth(): void
{
    $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
    $firmId = (int) ($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

    if ($userId <= 0 || empty($_SESSION['user'])) {
        contractsApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if ($firmId <= 0) {
        contractsApiResponse(['success' => false, 'message' => 'Lütfen önce aktif firma seçimi yapınız.'], 400);
    }

    $canAccess = Gate::isSuperAdmin() || Gate::allows('hakedis');

    if (!$canAccess) {
        contractsApiResponse(['success' => false, 'message' => 'Sözleşme & Hakediş modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        contractsApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

function parseId(mixed $rawId): int
{
    if (empty($rawId)) {
        return 0;
    }
    if (is_numeric($rawId)) {
        return (int) $rawId;
    }
    $decrypted = Security::decrypt((string) $rawId);
    return is_numeric($decrypted) ? (int) $decrypted : 0;
}

checkContractsAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'contracts_list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$firmId = (int) ($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

$aylar = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

try {
    $sozlesmeModel = new HakedisSozlesmeModel();
    $donemModel = new HakedisDonemModel();
    $kalemModel = new HakedisKalemModel();
    $miktarModel = new HakedisMiktarModel();
    $db = $sozlesmeModel->getDb();

    // 1. SÖZLEŞMELER LİSTESİ VE ÖZET
    if ($method === 'GET' && $action === 'contracts_list') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all')); // all, aktif, tamamlandi, pasif
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = "s.firma_id = :firma_id AND s.silinme_tarihi IS NULL";
        $bind = [':firma_id' => $firmId];

        if ($status === 'aktif' || $status === 'tamamlandi' || $status === 'pasif') {
            $where .= " AND s.durum = :status";
            $bind[':status'] = $status;
        }

        if ($search !== '') {
            $where .= " AND (s.idare_adi LIKE :search OR s.isin_adi LIKE :search OR s.isin_yuklenicisi LIKE :search OR s.ihale_kayit_no LIKE :search)";
            $bind[':search'] = "%{$search}%";
        }

        // Toplam Sayı
        $countSql = "SELECT COUNT(*) FROM hakedis_sozlesmeler s WHERE {$where}";
        $countStmt = $db->prepare($countSql);
        foreach ($bind as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalItems = (int) $countStmt->fetchColumn();

        // Sözleşme Listesi
        $sql = "SELECT s.*,
                (SELECT COUNT(*) FROM hakedis_donemleri d WHERE d.sozlesme_id = s.id AND d.silinme_tarihi IS NULL) as hakedis_sayisi,
                (SELECT COALESCE(SUM(m.miktar * k.teklif_edilen_birim_fiyat), 0)
                 FROM hakedis_miktarlari m
                 JOIN hakedis_kalemleri k ON m.kalem_id = k.id
                 JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
                 WHERE d.sozlesme_id = s.id AND d.silinme_tarihi IS NULL) as toplam_hakedis_tutari
                FROM hakedis_sozlesmeler s
                WHERE {$where}
                ORDER BY s.id DESC
                LIMIT :offset, :limit";
        $stmt = $db->prepare($sql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $contracts = [];
        foreach ($rows as $r) {
            $rawId = (int) $r['id'];
            $contracts[] = [
                'id' => Security::encrypt((string) $rawId),
                'raw_id' => $rawId,
                'idare_adi' => (string) ($r['idare_adi'] ?? ''),
                'idare_baskanlik_adi' => (string) ($r['idare_baskanlik_adi'] ?? ''),
                'isin_adi' => (string) ($r['isin_adi'] ?? ''),
                'isin_yuklenicisi' => (string) ($r['isin_yuklenicisi'] ?? ''),
                'ihale_kayit_no' => (string) ($r['ihale_kayit_no'] ?? ''),
                'sozlesme_bedeli' => (float) ($r['sozlesme_bedeli'] ?? 0),
                'sozlesme_bedeli_formatted' => number_format((float) ($r['sozlesme_bedeli'] ?? 0), 2, ',', '.') . ' ₺',
                'kesif_bedeli' => (float) ($r['kesif_bedeli'] ?? 0),
                'ihale_tenzilati' => (float) ($r['ihale_tenzilati'] ?? 0),
                'sozlesme_tarihi' => !empty($r['sozlesme_tarihi']) ? date('d.m.Y', strtotime($r['sozlesme_tarihi'])) : null,
                'isin_bitecegi_tarih' => !empty($r['isin_bitecegi_tarih']) ? date('d.m.Y', strtotime($r['isin_bitecegi_tarih'])) : null,
                'isin_suresi' => (int) ($r['isin_suresi'] ?? 0),
                'durum' => (string) ($r['durum'] ?? 'aktif'),
                'hakedis_sayisi' => (int) ($r['hakedis_sayisi'] ?? 0),
                'toplam_hakedis_tutari' => (float) ($r['toplam_hakedis_tutari'] ?? 0),
                'toplam_hakedis_tutari_formatted' => number_format((float) ($r['toplam_hakedis_tutari'] ?? 0), 2, ',', '.') . ' ₺',
                'temel_endeks_ay' => (int) ($r['temel_endeks_ay'] ?? 0),
                'temel_endeks_yil' => (int) ($r['temel_endeks_yil'] ?? 0),
                'temel_endeks_adi' => (!empty($r['temel_endeks_ay']) && isset($aylar[(int) $r['temel_endeks_ay']]))
                    ? $aylar[(int) $r['temel_endeks_ay']] . ' ' . ($r['temel_endeks_yil'] ?? '')
                    : '-'
            ];
        }

        // Özet İstatistikler (KPI Kartları)
        $summary = $sozlesmeModel->summary($firmId);

        contractsApiResponse([
            'success' => true,
            'summary' => [
                'toplam_sozlesme' => (int) ($summary->toplam_sozlesme ?? 0),
                'aktif_sayisi' => (int) ($summary->aktif_sayisi ?? 0),
                'aktif_bedel' => (float) ($summary->aktif_bedel ?? 0),
                'aktif_bedel_formatted' => number_format((float) ($summary->aktif_bedel ?? 0), 2, ',', '.') . ' ₺',
                'tamamlanan_sayisi' => (int) ($summary->tamamlanan_sayisi ?? 0),
                'tamamlanan_bedel' => (float) ($summary->tamamlanan_bedel ?? 0),
                'tamamlanan_bedel_formatted' => number_format((float) ($summary->tamamlanan_bedel ?? 0), 2, ',', '.') . ' ₺',
                'pasif_sayisi' => (int) ($summary->pasif_sayisi ?? 0),
                'pasif_bedel' => (float) ($summary->pasif_bedel ?? 0),
                'toplam_bedel' => (float) ($summary->toplam_bedel ?? 0),
                'toplam_bedel_formatted' => number_format((float) ($summary->toplam_bedel ?? 0), 2, ',', '.') . ' ₺',
            ],
            'contracts' => $contracts,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalItems,
                'has_more' => ($offset + count($contracts)) < $totalItems
            ]
        ]);
    }

    // 2. SÖZLEŞME DETAYI
    if ($method === 'GET' && $action === 'contract_detail') {
        $sozlesmeId = parseId($_GET['id'] ?? 0);
        if ($sozlesmeId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz sözleşme ID.'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$sozlesmeId, $firmId]);
        $sozlesme = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sozlesme) {
            contractsApiResponse(['success' => false, 'message' => 'Sözleşme bulunamadı veya erişim yetkiniz yok.'], 404);
        }

        // İş Revizyonları (İş Artış/Azalış)
        $isRevizyonlari = [];
        try {
            $revStmt = $db->prepare("
                SELECT r.*, COALESCE(SUM(d.degisim_miktari * d.birim_fiyat), 0) AS toplam_tutar_farki
                FROM hakedis_is_revizyonlari r
                LEFT JOIN hakedis_is_revizyon_kalemleri d ON d.revizyon_id = r.id
                WHERE r.sozlesme_id = ?
                GROUP BY r.id ORDER BY r.revizyon_no DESC
            ");
            $revStmt->execute([$sozlesmeId]);
            $rawRevizyonlar = $revStmt->fetchAll(PDO::FETCH_ASSOC);

            $detayStmt = $db->prepare("
                SELECT poz_no, kalem_adi, degisim_miktari, birim, birim_fiyat
                FROM hakedis_is_revizyon_kalemleri WHERE revizyon_id = ? ORDER BY id
            ");

            $kumulatifFark = array_sum(array_map(static fn($r) => (float) $r['toplam_tutar_farki'], $rawRevizyonlar));
            $baseBedel = (float) ($sozlesme['sozlesme_bedeli'] ?? 0);

            foreach ($rawRevizyonlar as $rev) {
                $revId = (int) $rev['id'];
                $fark = (float) $rev['toplam_tutar_farki'];
                $oran = $baseBedel > 0 ? ($kumulatifFark / $baseBedel) * 100 : 0;
                $kumulatifFark -= $fark;

                $detayStmt->execute([$revId]);
                $kalemler = $detayStmt->fetchAll(PDO::FETCH_ASSOC);

                $isRevizyonlari[] = [
                    'id' => Security::encrypt((string) $revId),
                    'raw_id' => $revId,
                    'revizyon_no' => (int) $rev['revizyon_no'],
                    'revizyon_tarihi' => !empty($rev['revizyon_tarihi']) ? date('d.m.Y', strtotime($rev['revizyon_tarihi'])) : '',
                    'karar_no' => (string) ($rev['karar_no'] ?? ''),
                    'aciklama' => (string) ($rev['aciklama'] ?? ''),
                    'toplam_tutar_farki' => $fark,
                    'toplam_tutar_farki_formatted' => ($fark > 0 ? '+' : '') . number_format($fark, 2, ',', '.') . ' ₺',
                    'artis_orani' => round($oran, 2),
                    'kalemler' => $kalemler
                ];
            }
        } catch (\Throwable $e) {
            $isRevizyonlari = [];
        }

        // Süre Uzatımları
        $sureUzatimlari = [];
        try {
            $sureStmt = $db->prepare("SELECT * FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ? ORDER BY uzatim_no DESC");
            $sureStmt->execute([$sozlesmeId]);
            $rawSureler = $sureStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawSureler as $su) {
                $suId = (int) $su['id'];
                $sureUzatimlari[] = [
                    'id' => Security::encrypt((string) $suId),
                    'raw_id' => $suId,
                    'uzatim_no' => (int) $su['uzatim_no'],
                    'uzatim_tarihi' => !empty($su['uzatim_tarihi']) ? date('d.m.Y', strtotime($su['uzatim_tarihi'])) : '',
                    'karar_no' => (string) ($su['karar_no'] ?? ''),
                    'aciklama' => (string) ($su['aciklama'] ?? ''),
                    'uzatim_gun' => (int) $su['uzatim_gun'],
                    'onceki_bitis_tarihi' => !empty($su['onceki_bitis_tarihi']) ? date('d.m.Y', strtotime($su['onceki_bitis_tarihi'])) : '',
                    'yeni_bitis_tarihi' => !empty($su['yeni_bitis_tarihi']) ? date('d.m.Y', strtotime($su['yeni_bitis_tarihi'])) : ''
                ];
            }
        } catch (\Throwable $e) {
            $sureUzatimlari = [];
        }

        $toplamIsArtisTutari = array_sum(array_map(static fn($r) => (float) $r['toplam_tutar_farki'], $isRevizyonlari));
        $toplamUzatimGunu = array_sum(array_map(static fn($u) => (int) $u['uzatim_gun'], $sureUzatimlari));
        $yeniSozlesmeBedeli = (float) $sozlesme['sozlesme_bedeli'] + $toplamIsArtisTutari;

        // Hakediş Dönemleri
        $donemler = [];
        $dStmt = $db->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM hakedis_miktarlari m WHERE m.hakedis_donem_id = d.id) as miktar_satir_sayisi,
                   (SELECT COALESCE(SUM(m.miktar * k.teklif_edilen_birim_fiyat), 0)
                    FROM hakedis_miktarlari m
                    JOIN hakedis_kalemleri k ON m.kalem_id = k.id
                    WHERE m.hakedis_donem_id = d.id) as imalat_donem
            FROM hakedis_donemleri d
            WHERE d.sozlesme_id = ? AND d.silinme_tarihi IS NULL
            ORDER BY d.hakedis_no DESC
        ");
        $dStmt->execute([$sozlesmeId]);
        $rawDonemler = $dStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawDonemler as $d) {
            $dId = (int) $d['id'];
            $ay = (int) ($d['hakedis_tarihi_ay'] ?? 0);
            $yil = (int) ($d['hakedis_tarihi_yil'] ?? 0);
            $donemAdi = ($ay > 0 && isset($aylar[$ay])) ? $aylar[$ay] . ' ' . $yil : "$ay/$yil";
            $imalatDonem = (float) ($d['imalat_donem'] ?? 0);
            $imalatKumulatif = (float) ($d['hakedi_tutari'] ?? 0);

            $donemler[] = [
                'id' => Security::encrypt((string) $dId),
                'raw_id' => $dId,
                'hakedis_no' => (int) $d['hakedis_no'],
                'hakedis_tarihi_ay' => $ay,
                'hakedis_tarihi_yil' => $yil,
                'donem_adi' => $donemAdi,
                'tutanak_tasdik_tarihi' => !empty($d['tutanak_tasdik_tarihi']) ? date('d.m.Y', strtotime($d['tutanak_tasdik_tarihi'])) : '-',
                'hakedi_tutari' => $imalatDonem,
                'hakedi_tutari_formatted' => number_format($imalatDonem, 2, ',', '.') . ' ₺',
                'imalat_donem' => $imalatDonem,
                'imalat_donem_formatted' => number_format($imalatDonem, 2, ',', '.') . ' ₺',
                'imalat_kumulatif' => $imalatKumulatif,
                'imalat_kumulatif_formatted' => number_format($imalatKumulatif, 2, ',', '.') . ' ₺',
                'durum' => (string) ($d['durum'] ?? 'taslak'),
                'temel_endeks_ayi' => (string) ($d['temel_endeks_ayi'] ?? ''),
                'guncel_endeks_ayi' => (string) ($d['guncel_endeks_ayi'] ?? '')
            ];
        }

        // Sözleşme Kalemleri
        $kalemler = [];
        $kStmt = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = ? AND silinme_tarihi IS NULL ORDER BY sira ASC, id ASC");
        $kStmt->execute([$sozlesmeId]);
        $rawKalemler = $kStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawKalemler as $k) {
            $kId = (int) $k['id'];
            $birimFiyat = (float) ($k['teklif_edilen_birim_fiyat'] ?? 0);
            $miktar = (float) ($k['miktari'] ?? 0);
            $tutar = $miktar * $birimFiyat;

            $kalemler[] = [
                'id' => Security::encrypt((string) $kId),
                'raw_id' => $kId,
                'poz_no' => (string) ($k['poz_no'] ?? ''),
                'kalem_adi' => (string) ($k['kalem_adi'] ?? ''),
                'birim' => (string) ($k['birim'] ?? ''),
                'miktari' => $miktar,
                'teklif_edilen_birim_fiyat' => $birimFiyat,
                'teklif_edilen_birim_fiyat_formatted' => number_format($birimFiyat, 2, ',', '.') . ' ₺',
                'toplam_tutar' => $tutar,
                'toplam_tutar_formatted' => number_format($tutar, 2, ',', '.') . ' ₺',
                'sira' => (int) ($k['sira'] ?? 0)
            ];
        }

        contractsApiResponse([
            'success' => true,
            'contract' => [
                'id' => Security::encrypt((string) $sozlesmeId),
                'raw_id' => $sozlesmeId,
                'idare_adi' => (string) $sozlesme['idare_adi'],
                'idare_baskanlik_adi' => (string) ($sozlesme['idare_baskanlik_adi'] ?? ''),
                'isin_adi' => (string) $sozlesme['isin_adi'],
                'isin_yuklenicisi' => (string) ($sozlesme['isin_yuklenicisi'] ?? ''),
                'ihale_kayit_no' => (string) ($sozlesme['ihale_kayit_no'] ?? ''),
                'yuklenici_adres' => (string) ($sozlesme['yuklenici_adres'] ?? ''),
                'yuklenici_tel' => (string) ($sozlesme['yuklenici_tel'] ?? ''),
                'sozlesme_bedeli' => (float) $sozlesme['sozlesme_bedeli'],
                'sozlesme_bedeli_formatted' => number_format((float) $sozlesme['sozlesme_bedeli'], 2, ',', '.') . ' ₺',
                'yeni_sozlesme_bedeli' => $yeniSozlesmeBedeli,
                'yeni_sozlesme_bedeli_formatted' => number_format($yeniSozlesmeBedeli, 2, ',', '.') . ' ₺',
                'kesif_bedeli' => (float) ($sozlesme['kesif_bedeli'] ?? 0),
                'ihale_tenzilati' => (float) ($sozlesme['ihale_tenzilati'] ?? 0),
                'sozlesme_tarihi' => !empty($sozlesme['sozlesme_tarihi']) ? date('d.m.Y', strtotime($sozlesme['sozlesme_tarihi'])) : '',
                'isin_bitecegi_tarih' => !empty($sozlesme['isin_bitecegi_tarih']) ? date('d.m.Y', strtotime($sozlesme['isin_bitecegi_tarih'])) : '',
                'ihale_tarihi' => !empty($sozlesme['ihale_tarihi']) ? date('d.m.Y', strtotime($sozlesme['ihale_tarihi'])) : '',
                'yer_teslim_tarihi' => !empty($sozlesme['yer_teslim_tarihi']) ? date('d.m.Y', strtotime($sozlesme['yer_teslim_tarihi'])) : '',
                'isin_suresi' => (int) ($sozlesme['isin_suresi'] ?? 0),
                'toplam_uzatim_gunu' => $toplamUzatimGunu,
                'durum' => (string) ($sozlesme['durum'] ?? 'aktif'),
                'a1_katsayisi' => (float) ($sozlesme['a1_katsayisi'] ?? 0.28),
                'b1_katsayisi' => (float) ($sozlesme['b1_katsayisi'] ?? 0.22),
                'b2_katsayisi' => (float) ($sozlesme['b2_katsayisi'] ?? 0.25),
                'c_katsayisi' => (float) ($sozlesme['c_katsayisi'] ?? 0.25),
                'asgari_ucret_temel' => (float) ($sozlesme['asgari_ucret_temel'] ?? 0),
                'motorin_temel' => (float) ($sozlesme['motorin_temel'] ?? 0),
                'ufe_genel_temel' => (float) ($sozlesme['ufe_genel_temel'] ?? 0),
                'makine_ekipman_temel' => (float) ($sozlesme['makine_ekipman_temel'] ?? 0),
                'kdv_orani' => (float) ($sozlesme['kdv_orani'] ?? 20.0),
                'tevkifat_orani' => (string) ($sozlesme['tevkifat_orani'] ?? '4/10'),
                'temel_endeks_ay' => (int) ($sozlesme['temel_endeks_ay'] ?? 0),
                'temel_endeks_yil' => (int) ($sozlesme['temel_endeks_yil'] ?? 0),
                'kontrol_teskilati' => (string) ($sozlesme['kontrol_teskilati'] ?? ''),
                'tasvip_eden' => (string) ($sozlesme['tasvip_eden'] ?? ''),
                'tasvip_eden_unvan' => (string) ($sozlesme['tasvip_eden_unvan'] ?? ''),
                'idare_onaylayan' => (string) ($sozlesme['idare_onaylayan'] ?? ''),
                'idare_onaylayan_unvan' => (string) ($sozlesme['idare_onaylayan_unvan'] ?? ''),
            ],
            'toplam_is_artis_tutari' => $toplamIsArtisTutari,
            'toplam_is_artis_tutari_formatted' => ($toplamIsArtisTutari > 0 ? '+' : '') . number_format($toplamIsArtisTutari, 2, ',', '.') . ' ₺',
            'is_revizyonlari' => $isRevizyonlari,
            'sure_uzatimlari' => $sureUzatimlari,
            'donemler' => $donemler,
            'kalemler' => $kalemler
        ]);
    }

    // 3. SÖZLEŞME KAYDET / GÜNCELLE
    if ($method === 'POST' && $action === 'save_contract') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $rawId = parseId($input['id'] ?? 0);

        $idareAdi = trim((string) ($input['idare_adi'] ?? ''));
        $isinAdi = trim((string) ($input['isin_adi'] ?? ''));
        $sozlesmeBedeli = (float) ($input['sozlesme_bedeli'] ?? 0);

        if ($idareAdi === '' || $isinAdi === '' || $sozlesmeBedeli <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'İdare Adı, İşin Adı ve Sözleşme Bedeli zorunludur.'], 422);
        }

        $data = [
            'firma_id' => $firmId,
            'idare_adi' => $idareAdi,
            'idare_baskanlik_adi' => trim((string) ($input['idare_baskanlik_adi'] ?? '')),
            'isin_adi' => $isinAdi,
            'isin_yuklenicisi' => trim((string) ($input['isin_yuklenicisi'] ?? '')),
            'ihale_kayit_no' => trim((string) ($input['ihale_kayit_no'] ?? '')),
            'kesif_bedeli' => !empty($input['kesif_bedeli']) ? (float) $input['kesif_bedeli'] : null,
            'ihale_tenzilati' => !empty($input['ihale_tenzilati']) ? (float) $input['ihale_tenzilati'] : null,
            'sozlesme_bedeli' => $sozlesmeBedeli,
            'sozlesme_tarihi' => !empty($input['sozlesme_tarihi']) ? Date::Ymd($input['sozlesme_tarihi']) : null,
            'isin_bitecegi_tarih' => !empty($input['isin_bitecegi_tarih']) ? Date::Ymd($input['isin_bitecegi_tarih']) : null,
            'ihale_tarihi' => !empty($input['ihale_tarihi']) ? Date::Ymd($input['ihale_tarihi']) : null,
            'yer_teslim_tarihi' => !empty($input['yer_teslim_tarihi']) ? Date::Ymd($input['yer_teslim_tarihi']) : null,
            'isin_suresi' => !empty($input['isin_suresi']) ? (int) $input['isin_suresi'] : null,
            'kontrol_teskilati' => (string) ($input['kontrol_teskilati'] ?? ''),
            'tasvip_eden' => (string) ($input['tasvip_eden'] ?? ''),
            'tasvip_eden_unvan' => (string) ($input['tasvip_eden_unvan'] ?? ''),
            'idare_onaylayan' => (string) ($input['idare_onaylayan'] ?? ''),
            'idare_onaylayan_unvan' => (string) ($input['idare_onaylayan_unvan'] ?? ''),
            'yuklenici_adres' => (string) ($input['yuklenici_adres'] ?? ''),
            'yuklenici_tel' => (string) ($input['yuklenici_tel'] ?? ''),
            'durum' => in_array($input['durum'] ?? '', ['aktif', 'pasif', 'tamamlandi'], true) ? $input['durum'] : 'aktif',
            'a1_katsayisi' => !empty($input['a1_katsayisi']) ? (float) $input['a1_katsayisi'] : 0.28,
            'b1_katsayisi' => !empty($input['b1_katsayisi']) ? (float) $input['b1_katsayisi'] : 0.22,
            'b2_katsayisi' => !empty($input['b2_katsayisi']) ? (float) $input['b2_katsayisi'] : 0.25,
            'c_katsayisi' => !empty($input['c_katsayisi']) ? (float) $input['c_katsayisi'] : 0.25,
            'asgari_ucret_temel' => !empty($input['asgari_ucret_temel']) ? (float) $input['asgari_ucret_temel'] : null,
            'motorin_temel' => !empty($input['motorin_temel']) ? (float) $input['motorin_temel'] : null,
            'ufe_genel_temel' => !empty($input['ufe_genel_temel']) ? (float) $input['ufe_genel_temel'] : null,
            'makine_ekipman_temel' => !empty($input['makine_ekipman_temel']) ? (float) $input['makine_ekipman_temel'] : null,
            'kdv_orani' => !empty($input['kdv_orani']) ? (float) $input['kdv_orani'] : 20.00,
            'tevkifat_orani' => (string) ($input['tevkifat_orani'] ?? '4/10'),
            'temel_endeks_ay' => !empty($input['temel_endeks_ay']) ? (int) $input['temel_endeks_ay'] : null,
            'temel_endeks_yil' => !empty($input['temel_endeks_yil']) ? (int) $input['temel_endeks_yil'] : null,
        ];

        if ($rawId > 0) {
            // Update
            $checkStmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $checkStmt->execute([$rawId, $firmId]);
            if (!$checkStmt->fetch()) {
                contractsApiResponse(['success' => false, 'message' => 'Güncellenecek sözleşme bulunamadı.'], 404);
            }

            $setCols = [];
            foreach ($data as $col => $val) {
                if ($col === 'firma_id') continue;
                $setCols[] = "{$col} = :{$col}";
            }
            $updateSql = "UPDATE hakedis_sozlesmeler SET " . implode(', ', $setCols) . " WHERE id = :target_id AND firma_id = :firma_id";
            $stmt = $db->prepare($updateSql);
            $bindParams = $data;
            $bindParams['target_id'] = $rawId;
            $stmt->execute($bindParams);

            contractsApiResponse([
                'success' => true,
                'message' => 'Sözleşme başarıyla güncellendi.',
                'id' => Security::encrypt((string) $rawId)
            ]);
        } else {
            // Insert
            $data['olusturma_tarihi'] = date('Y-m-d H:i:s');
            $cols = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $insertSql = "INSERT INTO hakedis_sozlesmeler ({$cols}) VALUES ({$placeholders})";
            $stmt = $db->prepare($insertSql);
            $stmt->execute($data);
            $newId = (int) $db->lastInsertId();

            contractsApiResponse([
                'success' => true,
                'message' => 'Sözleşme başarıyla oluşturuldu.',
                'id' => Security::encrypt((string) $newId)
            ]);
        }
    }

    // 4. SÖZLEŞME SİL (SOFT DELETE)
    if ($method === 'POST' && $action === 'delete_contract') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $rawId = parseId($input['id'] ?? 0);

        if ($rawId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz sözleşme ID.'], 400);
        }

        $stmt = $db->prepare("UPDATE hakedis_sozlesmeler SET silinme_tarihi = NOW() WHERE id = ? AND firma_id = ?");
        $stmt->execute([$rawId, $firmId]);

        contractsApiResponse(['success' => true, 'message' => 'Sözleşme başarıyla silindi.']);
    }

    // 5. İŞ ARTIŞ/AZALIŞ (REVİZYON) KAYDET
    if ($method === 'POST' && $action === 'save_is_revizyonu') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $sozlesmeId = parseId($input['sozlesme_id'] ?? 0);
        $revizyonTarihi = Date::Ymd($input['revizyon_tarihi'] ?? date('Y-m-d'));
        $kararNo = trim((string) ($input['karar_no'] ?? ''));
        $aciklama = trim((string) ($input['aciklama'] ?? ''));
        $kalemler = is_array($input['kalemler'] ?? null) ? $input['kalemler'] : [];

        if ($sozlesmeId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz sözleşme.'], 400);
        }

        $checkStmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $checkStmt->execute([$sozlesmeId, $firmId]);
        if (!$checkStmt->fetch()) {
            contractsApiResponse(['success' => false, 'message' => 'Sözleşme bulunamadı.'], 404);
        }

        // Sıradaki revizyon no
        $revNoStmt = $db->prepare("SELECT COALESCE(MAX(revizyon_no), 0) + 1 FROM hakedis_is_revizyonlari WHERE sozlesme_id = ?");
        $revNoStmt->execute([$sozlesmeId]);
        $nextRevNo = (int) $revNoStmt->fetchColumn();

        $db->beginTransaction();
        try {
            $rIns = $db->prepare("
                INSERT INTO hakedis_is_revizyonlari (sozlesme_id, revizyon_no, revizyon_tarihi, karar_no, aciklama, olusturan_personel_id, olusturma_tarihi)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $rIns->execute([$sozlesmeId, $nextRevNo, $revizyonTarihi, $kararNo, $aciklama, $currentUserId]);
            $revId = (int) $db->lastInsertId();

            $kIns = $db->prepare("
                INSERT INTO hakedis_is_revizyon_kalemleri (revizyon_id, kalem_id, poz_no, kalem_adi, birim, onceki_miktar, degisim_miktari, yeni_miktar, birim_fiyat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $kUpdate = $db->prepare("UPDATE hakedis_kalemleri SET miktari = ? WHERE id = ? AND sozlesme_id = ?");

            foreach ($kalemler as $k) {
                $kalemId = parseId($k['kalem_id'] ?? 0);
                $degisim = (float) ($k['degisim_miktari'] ?? 0);
                if ($kalemId <= 0 || $degisim == 0) continue;

                $curKStmt = $db->prepare("SELECT poz_no, kalem_adi, birim, miktari, teklif_edilen_birim_fiyat FROM hakedis_kalemleri WHERE id = ?");
                $curKStmt->execute([$kalemId]);
                $curKalem = $curKStmt->fetch(PDO::FETCH_ASSOC);
                if (!$curKalem) continue;

                $oncekiMiktar = (float) $curKalem['miktari'];
                $yeniMiktar = $oncekiMiktar + $degisim;
                $birimFiyat = (float) $curKalem['teklif_edilen_birim_fiyat'];

                $kIns->execute([
                    $revId,
                    $kalemId,
                    $curKalem['poz_no'],
                    $curKalem['kalem_adi'],
                    $curKalem['birim'],
                    $oncekiMiktar,
                    $degisim,
                    $yeniMiktar,
                    $birimFiyat
                ]);

                $kUpdate->execute([$yeniMiktar, $kalemId, $sozlesmeId]);
            }

            $db->commit();
            contractsApiResponse(['success' => true, 'message' => "{$nextRevNo}. Revizyon başarıyla kaydedildi."]);
        } catch (\Throwable $e) {
            $db->rollBack();
            contractsApiResponse(['success' => false, 'message' => 'Revizyon kaydedilirken hata oluştu: ' . $e->getMessage()], 500);
        }
    }

    // 6. SÜRE UZATIMI KAYDET
    if ($method === 'POST' && $action === 'save_sure_uzatimi') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $sozlesmeId = parseId($input['sozlesme_id'] ?? 0);
        $uzatimGun = (int) ($input['uzatim_gun'] ?? 0);
        $uzatimTarihi = Date::Ymd($input['uzatim_tarihi'] ?? date('Y-m-d'));
        $kararNo = trim((string) ($input['karar_no'] ?? ''));
        $aciklama = trim((string) ($input['aciklama'] ?? ''));

        if ($sozlesmeId <= 0 || $uzatimGun <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçerli bir sözleşme ve pozitif süre girilmelidir.'], 422);
        }

        $checkStmt = $db->prepare("SELECT id, isin_bitecegi_tarih FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $checkStmt->execute([$sozlesmeId, $firmId]);
        $soz = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$soz) {
            contractsApiResponse(['success' => false, 'message' => 'Sözleşme bulunamadı.'], 404);
        }

        $oncekiBitis = !empty($soz['isin_bitecegi_tarih']) ? $soz['isin_bitecegi_tarih'] : date('Y-m-d');
        $yeniBitis = date('Y-m-d', strtotime("{$oncekiBitis} +{$uzatimGun} days"));

        $uNoStmt = $db->prepare("SELECT COALESCE(MAX(uzatim_no), 0) + 1 FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ?");
        $uNoStmt->execute([$sozlesmeId]);
        $nextUNo = (int) $uNoStmt->fetchColumn();

        $db->beginTransaction();
        try {
            $ins = $db->prepare("
                INSERT INTO hakedis_sure_uzatimlari (sozlesme_id, uzatim_no, uzatim_tarihi, karar_no, aciklama, uzatim_gun, onceki_bitis_tarihi, yeni_bitis_tarihi, olusturan_personel_id, olusturma_tarihi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$sozlesmeId, $nextUNo, $uzatimTarihi, $kararNo, $aciklama, $uzatimGun, $oncekiBitis, $yeniBitis, $currentUserId]);

            // Sözleşme bitiş tarihini güncelle
            $up = $db->prepare("UPDATE hakedis_sozlesmeler SET isin_bitecegi_tarih = ? WHERE id = ?");
            $up->execute([$yeniBitis, $sozlesmeId]);

            $db->commit();
            contractsApiResponse(['success' => true, 'message' => "{$nextUNo}. Süre Uzatımı (+{$uzatimGun} gün) kaydedildi. Yeni bitiş: " . date('d.m.Y', strtotime($yeniBitis))]);
        } catch (\Throwable $e) {
            $db->rollBack();
            contractsApiResponse(['success' => false, 'message' => 'Süre uzatımı kaydedilirken hata oluştu: ' . $e->getMessage()], 500);
        }
    }

    // 7. HAKEDİŞ DÖNEM DETAYI VE İMALAT KALEMLERİ
    if ($method === 'GET' && $action === 'hakedis_detail') {
        $hakedisId = parseId($_GET['id'] ?? 0);
        if ($hakedisId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz Hakediş ID.'], 400);
        }

        $stmt = $db->prepare("
            SELECT d.*, s.idare_adi, s.isin_adi, s.sozlesme_bedeli, s.isin_yuklenicisi,
                   s.isin_bitecegi_tarih as s_bitis_tarihi,
                   s.a1_katsayisi as s_a1, s.b1_katsayisi as s_b1, s.b2_katsayisi as s_b2, s.c_katsayisi as s_c,
                   s.asgari_ucret_temel as s_asgari, s.motorin_temel as s_motorin, s.ufe_genel_temel as s_ufe, s.makine_ekipman_temel as s_makine,
                   s.kdv_orani as s_kdv, s.tevkifat_orani as s_tevkifat,
                   s.temel_endeks_ay as s_temel_endeks_ay, s.temel_endeks_yil as s_temel_endeks_yil
            FROM hakedis_donemleri d
            JOIN hakedis_sozlesmeler s ON d.sozlesme_id = s.id
            WHERE d.id = ? AND s.firma_id = ? AND d.silinme_tarihi IS NULL
        ");
        $stmt->execute([$hakedisId, $firmId]);
        $hakedis = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hakedis) {
            contractsApiResponse(['success' => false, 'message' => 'Hakediş bulunamadı veya yetkiniz yok.'], 404);
        }

        $sozlesmeId = (int) $hakedis['sozlesme_id'];
        $hNo = (int) $hakedis['hakedis_no'];

        // Sözleşme bazlı varsayılan endeksleri fallback olarak ata
        $hakedis['a1_katsayisi'] = (float) ($hakedis['a1_katsayisi'] ?: $hakedis['s_a1'] ?: 0.28);
        $hakedis['b1_katsayisi'] = (float) ($hakedis['b1_katsayisi'] ?: $hakedis['s_b1'] ?: 0.22);
        $hakedis['b2_katsayisi'] = (float) ($hakedis['b2_katsayisi'] ?: $hakedis['s_b2'] ?: 0.25);
        $hakedis['c_katsayisi'] = (float) ($hakedis['c_katsayisi'] ?: $hakedis['s_c'] ?: 0.25);
        $hakedis['asgari_ucret_temel'] = (float) ($hakedis['asgari_ucret_temel'] ?: $hakedis['s_asgari'] ?: 0);
        $hakedis['motorin_temel'] = (float) ($hakedis['motorin_temel'] ?: $hakedis['s_motorin'] ?: 0);
        $hakedis['ufe_genel_temel'] = (float) ($hakedis['ufe_genel_temel'] ?: $hakedis['s_ufe'] ?: 0);
        $hakedis['makine_ekipman_temel'] = (float) ($hakedis['makine_ekipman_temel'] ?: $hakedis['s_makine'] ?: 0);
        $hakedis['kdv_orani'] = (float) ($hakedis['kdv_orani'] ?: $hakedis['s_kdv'] ?: 20.0);
        $hakedis['tevkifat_orani'] = (string) ($hakedis['tevkifat_orani'] ?: $hakedis['s_tevkifat'] ?: '4/10');

        // Sözleşmeye ait tüm kalemleri çek
        $stmtKalem = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = ? AND silinme_tarihi IS NULL ORDER BY sira ASC, id ASC");
        $stmtKalem->execute([$sozlesmeId]);
        $kalemler = $stmtKalem->fetchAll(PDO::FETCH_ASSOC);

        // Mevcut dönem için girilen miktarlar
        $miktarlarMap = [];
        $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id = ?");
        $stmtMiktar->execute([$hakedisId]);
        while ($m = $stmtMiktar->fetch(PDO::FETCH_ASSOC)) {
            $miktarlarMap[(int) $m['kalem_id']] = $m;
        }

        // Önceki dönemlerin toplam miktarları
        $prevMiktarlarSum = [];
        $stmtPrevSum = $db->prepare("
            SELECT m.kalem_id, SUM(m.miktar) as toplam_prev
            FROM hakedis_miktarlari m
            JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
            WHERE d.sozlesme_id = ? AND d.hakedis_no < ? AND d.silinme_tarihi IS NULL
            GROUP BY m.kalem_id
        ");
        $stmtPrevSum->execute([$sozlesmeId, $hNo]);
        while ($row = $stmtPrevSum->fetch(PDO::FETCH_ASSOC)) {
            $prevMiktarlarSum[(int) $row['kalem_id']] = (float) $row['toplam_prev'];
        }

        // İlk hakedişteki başlangıç 'onceki_miktar'
        $baslangicMiktarlari = [];
        $stmtBaslangic = $db->prepare("
            SELECT m.kalem_id, m.onceki_miktar
            FROM hakedis_miktarlari m
            JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
            WHERE d.sozlesme_id = ? AND d.hakedis_no = 1 AND d.silinme_tarihi IS NULL
        ");
        $stmtBaslangic->execute([$sozlesmeId]);
        while ($row = $stmtBaslangic->fetch(PDO::FETCH_ASSOC)) {
            $baslangicMiktarlari[(int) $row['kalem_id']] = (float) $row['onceki_miktar'];
        }

        $items = [];
        foreach ($kalemler as $k) {
            $kalemId = (int) $k['id'];
            $curM = $miktarlarMap[$kalemId] ?? null;
            $buAyMiktar = (float) ($curM['miktar'] ?? 0);

            $oncekiMiktar = 0;
            if ($curM && isset($curM['onceki_miktar']) && $curM['onceki_miktar'] != 0) {
                $oncekiMiktar = (float) $curM['onceki_miktar'];
            } else {
                $prevSum = $prevMiktarlarSum[$kalemId] ?? 0;
                $baslangic = $baslangicMiktarlari[$kalemId] ?? 0;
                $oncekiMiktar = $prevSum + $baslangic;
            }

            $toplamMiktar = $oncekiMiktar + $buAyMiktar;
            $sozlesmeMiktari = (float) ($k['miktari'] ?? 0);
            $birimFiyat = (float) ($k['teklif_edilen_birim_fiyat'] ?? 0);
            $buAyTutar = $buAyMiktar * $birimFiyat;
            $gerceklesmeYuzdesi = $sozlesmeMiktari > 0 ? ($toplamMiktar / $sozlesmeMiktari) * 100 : 0;

            $items[] = [
                'id' => Security::encrypt((string) $kalemId),
                'raw_id' => $kalemId,
                'poz_no' => (string) ($k['poz_no'] ?? ''),
                'kalem_adi' => (string) ($k['kalem_adi'] ?? ''),
                'birim' => (string) ($k['birim'] ?? ''),
                'sozlesme_miktari' => $sozlesmeMiktari,
                'birim_fiyat' => $birimFiyat,
                'birim_fiyat_formatted' => number_format($birimFiyat, 2, ',', '.') . ' ₺',
                'onceki_miktar' => $oncekiMiktar,
                'bu_ay_miktar' => $buAyMiktar,
                'toplam_miktar' => $toplamMiktar,
                'gerceklesme_yuzdesi' => round($gerceklesmeYuzdesi, 2),
                'bu_ay_tutar' => $buAyTutar,
                'bu_ay_tutar_formatted' => number_format($buAyTutar, 2, ',', '.') . ' ₺'
            ];
        }

        // Merkezi toplam hesapları
        $totals = $donemModel->calculateTotals($hakedisId);
        $imalatDonem = (float) ($totals['imalat_donem'] ?? 0);
        $imalatKumulatif = (float) ($totals['imalat_kumulatif'] ?? 0);
        $fiyatFarki = (float) ($totals['fiyat_farki'] ?? 0);
        $fiyatFarkiDahil = $imalatDonem + $fiyatFarki;
        $kdvRate = (float) ($hakedis['kdv_orani'] ?: 20.0);
        $donemKdvDahil = round($fiyatFarkiDahil * (1 + $kdvRate / 100), 2);
        $kumulatifKdvDahil = (float) ($totals['kdv_dahil_toplam'] ?? 0);

        $sozlesmeBedeli = (float) $hakedis['sozlesme_bedeli'];
        $genelGerceklesme = $sozlesmeBedeli > 0 ? ($imalatKumulatif / $sozlesmeBedeli) * 100 : 0;

        $ay = (int) ($hakedis['hakedis_tarihi_ay'] ?? 0);
        $yil = (int) ($hakedis['hakedis_tarihi_yil'] ?? 0);
        $donemAdi = ($ay > 0 && isset($aylar[$ay])) ? $aylar[$ay] . ' ' . $yil : "$ay/$yil";

        contractsApiResponse([
            'success' => true,
            'hakedis' => [
                'id' => Security::encrypt((string) $hakedisId),
                'raw_id' => $hakedisId,
                'sozlesme_id' => Security::encrypt((string) $sozlesmeId),
                'sozlesme_raw_id' => $sozlesmeId,
                'hakedis_no' => $hNo,
                'hakedis_tarihi_ay' => $ay,
                'hakedis_tarihi_yil' => $yil,
                'donem_adi' => $donemAdi,
                'durum' => (string) ($hakedis['durum'] ?? 'taslak'),
                'tutanak_tasdik_tarihi' => !empty($hakedis['tutanak_tasdik_tarihi']) ? date('d.m.Y', strtotime($hakedis['tutanak_tasdik_tarihi'])) : '',
                'a1_katsayisi' => (float) $hakedis['a1_katsayisi'],
                'b1_katsayisi' => (float) $hakedis['b1_katsayisi'],
                'b2_katsayisi' => (float) $hakedis['b2_katsayisi'],
                'c_katsayisi' => (float) $hakedis['c_katsayisi'],
                'asgari_ucret_temel' => (float) $hakedis['asgari_ucret_temel'],
                'asgari_ucret_guncel' => (float) ($hakedis['asgari_ucret_guncel'] ?? 0),
                'motorin_temel' => (float) $hakedis['motorin_temel'],
                'motorin_guncel' => (float) ($hakedis['motorin_guncel'] ?? 0),
                'ufe_genel_temel' => (float) $hakedis['ufe_genel_temel'],
                'ufe_genel_guncel' => (float) ($hakedis['ufe_genel_guncel'] ?? 0),
                'makine_ekipman_temel' => (float) $hakedis['makine_ekipman_temel'],
                'makine_ekipman_guncel' => (float) ($hakedis['makine_ekipman_guncel'] ?? 0),
                'kdv_orani' => (float) $hakedis['kdv_orani'],
                'tevkifat_orani' => (string) $hakedis['tevkifat_orani'],
                'asgari_farki_dahil_edilsin' => !empty($hakedis['asgari_farki_dahil_edilsin']),
                'idare_adi' => (string) $hakedis['idare_adi'],
                'isin_adi' => (string) $hakedis['isin_adi'],
                'sozlesme_bedeli' => $sozlesmeBedeli,
                'sozlesme_bedeli_formatted' => number_format($sozlesmeBedeli, 2, ',', '.') . ' ₺',
            ],
            'totals' => [
                'imalat_donem' => $imalatDonem,
                'imalat_donem_formatted' => number_format($imalatDonem, 2, ',', '.') . ' ₺',
                'imalat_kumulatif' => $imalatKumulatif,
                'imalat_kumulatif_formatted' => number_format($imalatKumulatif, 2, ',', '.') . ' ₺',
                'fiyat_farki' => $fiyatFarki,
                'fiyat_farki_formatted' => number_format($fiyatFarki, 2, ',', '.') . ' ₺',
                'fiyat_farki_dahil' => $fiyatFarkiDahil,
                'fiyat_farki_dahil_formatted' => number_format($fiyatFarkiDahil, 2, ',', '.') . ' ₺',
                'donem_kdv_dahil' => $donemKdvDahil,
                'donem_kdv_dahil_formatted' => number_format($donemKdvDahil, 2, ',', '.') . ' ₺',
                'kdv_dahil_toplam' => $donemKdvDahil,
                'kdv_dahil_toplam_formatted' => number_format($donemKdvDahil, 2, ',', '.') . ' ₺',
                'kumulatif_kdv_dahil' => $kumulatifKdvDahil,
                'kumulatif_kdv_dahil_formatted' => number_format($kumulatifKdvDahil, 2, ',', '.') . ' ₺',
                'genel_gerceklesme_yuzdesi' => round($genelGerceklesme, 2)
            ],
            'items' => $items
        ]);
    }

    // 8. HAKEDİŞ DÖNEMİ OLUŞTUR / DÜZENLE
    if ($method === 'POST' && $action === 'save_hakedis') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $sozlesmeId = parseId($input['sozlesme_id'] ?? 0);
        $rawId = parseId($input['id'] ?? 0);

        if ($sozlesmeId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz sözleşme.'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$sozlesmeId, $firmId]);
        $sozlesme = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sozlesme) {
            contractsApiResponse(['success' => false, 'message' => 'Sözleşme bulunamadı.'], 404);
        }

        $hakedisNo = (int) ($input['hakedis_no'] ?? 1);
        $ay = (int) ($input['hakedis_tarihi_ay'] ?? date('n'));
        $yil = (int) ($input['hakedis_tarihi_yil'] ?? date('Y'));
        $durum = in_array($input['durum'] ?? '', ['taslak', 'hazirlandi', 'tamamlandi', 'onaylandi'], true) ? $input['durum'] : 'taslak';
        $tutanakTasdik = !empty($input['tutanak_tasdik_tarihi']) ? Date::Ymd($input['tutanak_tasdik_tarihi']) : null;

        if ($rawId > 0) {
            // Güncelleme
            $upStmt = $db->prepare("
                UPDATE hakedis_donemleri 
                SET hakedis_no = ?, hakedis_tarihi_ay = ?, hakedis_tarihi_yil = ?, durum = ?, tutanak_tasdik_tarihi = ?
                WHERE id = ? AND sozlesme_id = ? AND silinme_tarihi IS NULL
            ");
            $upStmt->execute([$hakedisNo, $ay, $yil, $durum, $tutanakTasdik, $rawId, $sozlesmeId]);

            contractsApiResponse(['success' => true, 'message' => 'Hakediş bilgileri güncellendi.', 'id' => Security::encrypt((string) $rawId)]);
        } else {
            // Yeni Hakediş Ekle
            // Sözleşmeden parametreleri devral
            $data = [
                'sozlesme_id' => $sozlesmeId,
                'hakedis_no' => $hakedisNo,
                'hakedis_tarihi_ay' => $ay,
                'hakedis_tarihi_yil' => $yil,
                'tutanak_tasdik_tarihi' => $tutanakTasdik,
                'durum' => $durum,
                'olusturan_personel_id' => $currentUserId,
                'olusturma_tarihi' => date('Y-m-d H:i:s'),
                'a1_katsayisi' => $sozlesme['a1_katsayisi'],
                'b1_katsayisi' => $sozlesme['b1_katsayisi'],
                'b2_katsayisi' => $sozlesme['b2_katsayisi'],
                'c_katsayisi' => $sozlesme['c_katsayisi'],
                'asgari_ucret_temel' => $sozlesme['asgari_ucret_temel'],
                'motorin_temel' => $sozlesme['motorin_temel'],
                'ufe_genel_temel' => $sozlesme['ufe_genel_temel'],
                'makine_ekipman_temel' => $sozlesme['makine_ekipman_temel'],
                'kdv_orani' => $sozlesme['kdv_orani'],
                'tevkifat_orani' => $sozlesme['tevkifat_orani']
            ];

            // EPDK ve TÜİK endekslerini web'den çekmeyi dene
            try {
                $endeksPathAkaryakit = $projectRoot . '/views/hakedisler/endeks_api/akaryakit.php';
                $endeksPathHizmet = $projectRoot . '/views/hakedisler/endeks_api/hizmet_endeks.php';
                if (file_exists($endeksPathAkaryakit)) {
                    require_once $endeksPathAkaryakit;
                    if (function_exists('getEpdkMotorinFiyati')) {
                        $mFiyat = getEpdkMotorinFiyati($yil, $ay);
                        if ($mFiyat !== null) $data['motorin_guncel'] = $mFiyat;
                    }
                }
                if (file_exists($endeksPathHizmet)) {
                    require_once $endeksPathHizmet;
                    if (function_exists('getHizmetEndeksleri')) {
                        $endeksler = getHizmetEndeksleri($yil, $ay);
                        if (!empty($endeksler['asgari_ucret'])) $data['asgari_ucret_guncel'] = $endeksler['asgari_ucret'];
                        if (!empty($endeksler['ufe'])) $data['ufe_genel_guncel'] = $endeksler['ufe'];
                        if (!empty($endeksler['makine'])) $data['makine_ekipman_guncel'] = $endeksler['makine'];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore web error, can be filled manually
            }

            $cols = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $insStmt = $db->prepare("INSERT INTO hakedis_donemleri ({$cols}) VALUES ({$placeholders})");
            $insStmt->execute($data);
            $newHakedisId = (int) $db->lastInsertId();

            contractsApiResponse(['success' => true, 'message' => "Hakediş #{$hakedisNo} başarıyla oluşturuldu.", 'id' => Security::encrypt((string) $newHakedisId)]);
        }
    }

    // 9. HAKEDİŞ SİL (SOFT DELETE)
    if ($method === 'POST' && $action === 'delete_hakedis') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $rawId = parseId($input['id'] ?? 0);

        if ($rawId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz Hakediş ID.'], 400);
        }

        $checkStmt = $db->prepare("
            SELECT d.id, d.durum, d.fatura_id, f.entegrator_durum_kodu 
            FROM hakedis_donemleri d
            JOIN hakedis_sozlesmeler s ON d.sozlesme_id = s.id
            LEFT JOIN faturalar f ON d.fatura_id = f.id AND f.deleted_at IS NULL
            WHERE d.id = ? AND s.firma_id = ? AND d.silinme_tarihi IS NULL
        ");
        $checkStmt->execute([$rawId, $firmId]);
        $hkd = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$hkd) {
            contractsApiResponse(['success' => false, 'message' => 'Hakediş bulunamadı.'], 404);
        }

        if (!empty($hkd['fatura_id']) && !empty($hkd['entegrator_durum_kodu']) && $hkd['entegrator_durum_kodu'] !== 'TASLAK') {
            contractsApiResponse(['success' => false, 'message' => 'Bu hakedişe ait resmi e-fatura EDM/GİB sistemine iletilmiştir. Silinemez.'], 422);
        }

        $delStmt = $db->prepare("UPDATE hakedis_donemleri SET silinme_tarihi = NOW() WHERE id = ?");
        $delStmt->execute([$rawId]);

        contractsApiResponse(['success' => true, 'message' => 'Hakediş dönemi başarıyla silindi.']);
    }

    // 10. HAKEDİŞ PARAMETRELERİ GÜNCELLE
    if ($method === 'POST' && $action === 'update_hakedis_parametreler') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $hakedisId = parseId($input['hakedis_id'] ?? $input['id'] ?? 0);

        if ($hakedisId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Geçersiz Hakediş ID.'], 400);
        }

        $fields = [
            'a1_katsayisi', 'b1_katsayisi', 'b2_katsayisi', 'c_katsayisi',
            'asgari_ucret_temel', 'asgari_ucret_guncel',
            'motorin_temel', 'motorin_guncel',
            'ufe_genel_temel', 'ufe_genel_guncel',
            'makine_ekipman_temel', 'makine_ekipman_guncel',
            'tevkifat_orani', 'kdv_orani', 'asgari_farki_dahil_edilsin'
        ];

        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if ($f === 'asgari_farki_dahil_edilsin') {
                $set[] = "{$f} = :{$f}";
                $params[":{$f}"] = !empty($input[$f]) ? 1 : 0;
            } elseif (array_key_exists($f, $input)) {
                $set[] = "{$f} = :{$f}";
                $val = $input[$f];
                $params[":{$f}"] = ($val === '' || $val === null) ? null : $val;
            }
        }

        if (!empty($set)) {
            $params[':hid'] = $hakedisId;
            $sql = "UPDATE hakedis_donemleri SET " . implode(', ', $set) . " WHERE id = :hid";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        // Toplamları yeniden hesapla ve hakedi_tutari alanını güncelle
        $totals = $donemModel->calculateTotals($hakedisId);
        if ($totals) {
            $upTut = $db->prepare("UPDATE hakedis_donemleri SET hakedi_tutari = ? WHERE id = ?");
            $upTut->execute([(float) ($totals['imalat_kumulatif'] ?? 0), $hakedisId]);
        }

        contractsApiResponse(['success' => true, 'message' => 'Parametreler kaydedildi.', 'totals' => $totals]);
    }

    // 11. KALEM İMALAT MİKTARI GÜNCELLE
    if ($method === 'POST' && $action === 'update_kalem_miktar') {
        verifyCsrfToken();

        $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
        $hakedisId = parseId($input['hakedis_id'] ?? 0);
        $kalemId = parseId($input['kalem_id'] ?? 0);
        $miktar = isset($input['bu_ay_miktar']) ? (float) $input['bu_ay_miktar'] : (isset($input['miktar']) ? (float) $input['miktar'] : 0.0);
        $oncekiMiktar = isset($input['onceki_miktar']) ? (float) $input['onceki_miktar'] : null;
        $bolge = trim((string) ($input['bolge'] ?? 'Genel'));

        if ($hakedisId <= 0 || $kalemId <= 0) {
            contractsApiResponse(['success' => false, 'message' => 'Hakediş veya Kalem ID eksik.'], 422);
        }

        $stmt = $db->prepare("SELECT id, miktar, onceki_miktar FROM hakedis_miktarlari WHERE hakedis_donem_id = ? AND kalem_id = ? AND bolge_adi = ?");
        $stmt->execute([$hakedisId, $kalemId, $bolge]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $mId = (int) $row['id'];
            $om = $oncekiMiktar !== null ? $oncekiMiktar : (float) $row['onceki_miktar'];
            $upM = $db->prepare("UPDATE hakedis_miktarlari SET miktar = ?, onceki_miktar = ? WHERE id = ?");
            $upM->execute([$miktar, $om, $mId]);
        } else {
            $insM = $db->prepare("INSERT INTO hakedis_miktarlari (hakedis_donem_id, kalem_id, bolge_adi, miktar, onceki_miktar) VALUES (?, ?, ?, ?, ?)");
            $insM->execute([$hakedisId, $kalemId, $bolge, $miktar, $oncekiMiktar ?? 0]);
        }

        // Toplamları güncelle
        $totals = $donemModel->calculateTotals($hakedisId);
        if ($totals) {
            $upTut = $db->prepare("UPDATE hakedis_donemleri SET hakedi_tutari = ? WHERE id = ?");
            $upTut->execute([(float) ($totals['imalat_kumulatif'] ?? 0), $hakedisId]);
        }

        contractsApiResponse([
            'success' => true,
            'message' => 'İmalat miktarı güncellendi.',
            'totals' => $totals
        ]);
    }

    // 12. WEB'DEN ENDEKSLERİ ÇEK (TÜİK & EPDK)
    if ($method === 'GET' && $action === 'fetch_endeks') {
        $ay = (int) ($_GET['ay'] ?? date('n'));
        $yil = (int) ($_GET['yil'] ?? date('Y'));

        $sonuc = [
            'asgari_ucret_guncel' => null,
            'motorin_guncel' => null,
            'ufe_genel_guncel' => null,
            'makine_ekipman_guncel' => null,
            'message' => ''
        ];

        $endeksPathAkaryakit = $projectRoot . '/views/hakedisler/endeks_api/akaryakit.php';
        $endeksPathHizmet = $projectRoot . '/views/hakedisler/endeks_api/hizmet_endeks.php';

        if (file_exists($endeksPathAkaryakit)) {
            require_once $endeksPathAkaryakit;
            if (function_exists('getEpdkMotorinFiyati')) {
                $sonuc['motorin_guncel'] = getEpdkMotorinFiyati($yil, $ay);
            }
        }

        if (file_exists($endeksPathHizmet)) {
            require_once $endeksPathHizmet;
            if (function_exists('getHizmetEndeksleri')) {
                $endeksler = getHizmetEndeksleri($yil, $ay);
                if (!empty($endeksler['asgari_ucret'])) $sonuc['asgari_ucret_guncel'] = $endeksler['asgari_ucret'];
                if (!empty($endeksler['ufe'])) $sonuc['ufe_genel_guncel'] = $endeksler['ufe'];
                if (!empty($endeksler['makine'])) $sonuc['makine_ekipman_guncel'] = $endeksler['makine'];
            }
        }

        $eksik = false;
        foreach (['asgari_ucret_guncel', 'motorin_guncel', 'ufe_genel_guncel', 'makine_ekipman_guncel'] as $k) {
            if ($sonuc[$k] === null) $eksik = true;
        }

        if ($eksik) {
            $sonuc['message'] = 'Bazı endeksler seçilen ay için resmî kurumlarca henüz açıklanmamış olabilir.';
        }

        contractsApiResponse(['success' => true, 'data' => $sonuc]);
    }

    contractsApiResponse(['success' => false, 'message' => "Desteklenmeyen işlem: {$action}"], 404);

} catch (\Throwable $e) {
    error_log("sozlesme-hakedis.php Hatası: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    contractsApiResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()], 500);
}
