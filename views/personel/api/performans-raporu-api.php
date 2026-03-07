<?php
session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Model\SayacDegisimModel;
use App\Model\PersonelModel;

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$firma_id = $_SESSION['firma_id'] ?? 0;

if (!$firma_id) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum sonlanmış.']);
    exit;
}

try {
    if ($action === 'get-performans') {
        $departman = $_GET['departman'] ?? 'kesme_acma';
        $period = $_GET['period'] ?? 'aylik';
        $tarih = $_GET['tarih'] ?? date('Y-m-d');
        $baslangicTarih = trim($_GET['baslangic_tarih'] ?? '');
        $bitisTarih = trim($_GET['bitis_tarih'] ?? '');

        $isValidDate = static function ($date) {
            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
        };

        if (!$isValidDate($tarih)) {
            $tarih = date('Y-m-d');
        }

        // Tarih aralığını hesapla (manuel aralık varsa öncelikli)
        $effectivePeriod = $period;
        if ($isValidDate($baslangicTarih) && $isValidDate($bitisTarih)) {
            $startDate = $baslangicTarih;
            $endDate = $bitisTarih;
            $effectivePeriod = 'aralik';
        } else {
            switch ($period) {
                case 'gunluk':
                    $startDate = $tarih;
                    $endDate = $tarih;
                    break;
                case 'haftalik':
                    $startDate = date('Y-m-d', strtotime('monday this week', strtotime($tarih)));
                    $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($tarih)));
                    break;
                case 'aylik':
                    $startDate = date('Y-m-01', strtotime($tarih));
                    $endDate = date('Y-m-t', strtotime($tarih));
                    break;
                case 'yillik':
                    $startDate = date('Y-01-01', strtotime($tarih));
                    $endDate = date('Y-12-31', strtotime($tarih));
                    break;
                default:
                    $startDate = date('Y-m-01');
                    $endDate = date('Y-m-t');
                    $effectivePeriod = 'aylik';
            }
        }

        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        $db = (new \App\Model\Model('personel'))->getDb();

        // Departmana göre veri çek
        $personeller = [];
        $gunlukTrend = [];
        $toplam = 0;

        if ($departman === 'kesme_acma') {
            // yapilan_isler tablosundan - Kesme Açma verileri
            $sql = "SELECT 
                        p.id as personel_id,
                        p.adi_soyadi,
                        p.resim_yolu,
                        p.departman,
                        COALESCE(SUM(t.sonuclanmis), 0) as toplam
                    FROM yapilan_isler t
                    LEFT JOIN personel p ON p.id = t.personel_id
                    LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                    WHERE t.firma_id = ?
                    AND t.tarih BETWEEN ? AND ?
                    AND t.silinme_tarihi IS NULL
                    AND tn.rapor_sekmesi = 'kesme'
                    AND tn.is_turu_ucret > 0
                    AND t.personel_id > 0
                    GROUP BY p.id, p.adi_soyadi, p.resim_yolu, p.departman
                    ORDER BY toplam DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$firma_id, $startDate, $endDate]);
            $personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Günlük trend
            $trendSql = "SELECT 
                            t.tarih,
                            COALESCE(SUM(t.sonuclanmis), 0) as toplam
                         FROM yapilan_isler t
                         LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                         WHERE t.firma_id = ?
                         AND t.tarih BETWEEN ? AND ?
                         AND t.silinme_tarihi IS NULL
                         AND tn.rapor_sekmesi = 'kesme'
                         AND tn.is_turu_ucret > 0
                         GROUP BY t.tarih
                         ORDER BY t.tarih ASC";
            $stmtTrend = $db->prepare($trendSql);
            $stmtTrend->execute([$firma_id, $startDate, $endDate]);
            $gunlukTrend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($departman === 'endeks_okuma') {
            // endeks_okuma tablosundan - Endeks Okuma verileri
            $sql = "SELECT 
                        p.id as personel_id,
                        p.adi_soyadi,
                        p.resim_yolu,
                        p.departman,
                        COALESCE(SUM(e.okunan_abone_sayisi), 0) as toplam,
                        COALESCE(AVG(e.okuma_performansi), 0) as ort_performans,
                        COALESCE(SUM(e.okunan_gun_sayisi), 0) as toplam_gun
                    FROM endeks_okuma e
                    LEFT JOIN personel p ON p.id = e.personel_id
                    WHERE e.firma_id = ?
                    AND e.tarih BETWEEN ? AND ?
                    AND e.silinme_tarihi IS NULL
                    AND e.personel_id > 0
                    GROUP BY p.id, p.adi_soyadi, p.resim_yolu, p.departman
                    ORDER BY toplam DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$firma_id, $startDate, $endDate]);
            $personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Günlük trend
            $trendSql = "SELECT 
                            e.tarih,
                            COALESCE(SUM(e.okunan_abone_sayisi), 0) as toplam
                         FROM endeks_okuma e
                         WHERE e.firma_id = ?
                         AND e.tarih BETWEEN ? AND ?
                         AND e.silinme_tarihi IS NULL
                         GROUP BY e.tarih
                         ORDER BY e.tarih ASC";
            $stmtTrend = $db->prepare($trendSql);
            $stmtTrend->execute([$firma_id, $startDate, $endDate]);
            $gunlukTrend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($departman === 'sayac_degisim') {
            // sayac_degisim tablosundan - Sayaç Sökme Takma verileri
            $sql = "SELECT 
                        p.id as personel_id,
                        p.adi_soyadi,
                        p.resim_yolu,
                        p.departman,
                        COUNT(*) as toplam
                    FROM sayac_degisim s
                    LEFT JOIN personel p ON p.id = s.personel_id
                    WHERE s.firma_id = ?
                    AND s.tarih BETWEEN ? AND ?
                    AND s.silinme_tarihi IS NULL
                    AND s.personel_id > 0
                    GROUP BY p.id, p.adi_soyadi, p.resim_yolu, p.departman
                    ORDER BY toplam DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$firma_id, $startDate, $endDate]);
            $personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Günlük trend
            $trendSql = "SELECT 
                            s.tarih,
                            COUNT(*) as toplam
                         FROM sayac_degisim s
                         WHERE s.firma_id = ?
                         AND s.tarih BETWEEN ? AND ?
                         AND s.silinme_tarihi IS NULL
                         GROUP BY s.tarih
                         ORDER BY s.tarih ASC";
            $stmtTrend = $db->prepare($trendSql);
            $stmtTrend->execute([$firma_id, $startDate, $endDate]);
            $gunlukTrend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);
        }

        // Özet hesapla
        $toplam = array_sum(array_column($personeller, 'toplam'));
        $personelSayisi = count($personeller);
        $ortalama = $personelSayisi > 0 ? round($toplam / $personelSayisi, 1) : 0;

        // En iyi ve en kötü performans
        $enIyi = !empty($personeller) ? $personeller[0] : null;
        $enKotu = !empty($personeller) ? end($personeller) : null;

        echo json_encode([
            'status' => 'success',
            'summary' => [
                'toplam' => $toplam,
                'personel_sayisi' => $personelSayisi,
                'ortalama' => $ortalama,
                'en_iyi' => $enIyi,
                'en_kotu' => $enKotu,
            ],
            'personeller' => $personeller,
            'gunluk_trend' => $gunlukTrend,
            'period' => $period,
            'effective_period' => $effectivePeriod,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'get-karsilastirma') {
        // İki dönem karşılaştırma
        $departman = $_GET['departman'] ?? 'kesme_acma';
        $period1_start = $_GET['period1_start'] ?? '';
        $period1_end = $_GET['period1_end'] ?? '';
        $period2_start = $_GET['period2_start'] ?? '';
        $period2_end = $_GET['period2_end'] ?? '';

        if (empty($period1_start) || empty($period2_start)) {
            throw new Exception('Dönem bilgileri eksik.');
        }

        $db = (new \App\Model\Model('personel'))->getDb();

        $results = [];

        // Her iki dönem için aynı sorguyu çalıştır
        foreach (['period1' => [$period1_start, $period1_end], 'period2' => [$period2_start, $period2_end]] as $key => $dates) {
            if ($departman === 'kesme_acma') {
                $sql = "SELECT 
                            p.id as personel_id,
                            p.adi_soyadi,
                            COALESCE(SUM(t.sonuclanmis), 0) as toplam
                        FROM yapilan_isler t
                        LEFT JOIN personel p ON p.id = t.personel_id
                        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                        WHERE t.firma_id = ?
                        AND t.tarih BETWEEN ? AND ?
                        AND t.silinme_tarihi IS NULL
                        AND tn.rapor_sekmesi = 'kesme'
                        AND tn.is_turu_ucret > 0
                        AND t.personel_id > 0
                        GROUP BY p.id, p.adi_soyadi
                        ORDER BY toplam DESC";
            } elseif ($departman === 'endeks_okuma') {
                $sql = "SELECT 
                            p.id as personel_id,
                            p.adi_soyadi,
                            COALESCE(SUM(e.okunan_abone_sayisi), 0) as toplam
                        FROM endeks_okuma e
                        LEFT JOIN personel p ON p.id = e.personel_id
                        WHERE e.firma_id = ?
                        AND e.tarih BETWEEN ? AND ?
                        AND e.silinme_tarihi IS NULL
                        AND e.personel_id > 0
                        GROUP BY p.id, p.adi_soyadi
                        ORDER BY toplam DESC";
            } else {
                $sql = "SELECT 
                            p.id as personel_id,
                            p.adi_soyadi,
                            COUNT(*) as toplam
                        FROM sayac_degisim s
                        LEFT JOIN personel p ON p.id = s.personel_id
                        WHERE s.firma_id = ?
                        AND s.tarih BETWEEN ? AND ?
                        AND s.silinme_tarihi IS NULL
                        AND s.personel_id > 0
                        GROUP BY p.id, p.adi_soyadi
                        ORDER BY toplam DESC";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute([$firma_id, $dates[0], $dates[1]]);
            $results[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status' => 'success',
            'period1' => $results['period1'],
            'period2' => $results['period2'],
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz action.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
