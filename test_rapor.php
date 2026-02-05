<?php
require_once __DIR__ . '/Autoloader.php';
use App\Model\PersonelHareketleriModel;
use App\Model\PersonelModel;

$_SESSION['firma_id'] = 1;
$_POST['baslangic'] = date('Y-m-d', strtotime('-7 days'));
$_POST['bitis'] = date('Y-m-d');

$HareketModel = new PersonelHareketleriModel();
$db = (new \App\Core\Db())->db;

$personeller = $db->query("SELECT id, adi_soyadi FROM personel WHERE silinme_tarihi IS NULL AND aktif_mi = 1 AND saha_takibi = 1")->fetchAll(PDO::FETCH_OBJ);

$data = [];
foreach ($personeller as $personel) {
    echo "Processing " . $personel->adi_soyadi . " (ID: " . $personel->id . ")\n";
    $hareketler = $HareketModel->getRapor($personel->id, $_POST['baslangic'], $_POST['bitis']);
    echo "Rows: " . count($hareketler) . "\n";

    if (empty($hareketler))
        continue;

    $gunler = [];
    $toplam_dakika = 0;
    $baslama_saatleri = [];
    $bitis_saatleri = [];
    $gec_kalma_sayisi = 0;
    $limit_saat = '08:30';

    foreach ($hareketler as $h) {
        $tarih = date('Y-m-d', strtotime($h->zaman));
        $saat = date('H:i', strtotime($h->zaman));

        if (!isset($gunler[$tarih])) {
            $gunler[$tarih] = ['basla' => null, 'bitir' => null];
        }

        if ($h->islem_tipi === 'BASLA') {
            $gunler[$tarih]['basla'] = $saat;
            $baslama_saatleri[] = strtotime($saat);
            if ($saat > $limit_saat)
                $gec_kalma_sayisi++;
        } else {
            $gunler[$tarih]['bitir'] = $saat;
            $bitis_saatleri[] = strtotime($saat);
        }
    }

    foreach ($gunler as $gun) {
        if ($gun['basla'] && $gun['bitir']) {
            $start = strtotime($gun['basla']);
            $end = strtotime($gun['bitir']);
            $toplam_dakika += ($end - $start) / 60;
        }
    }

    $ort_baslama = count($baslama_saatleri) > 0 ? date('H:i', array_sum($baslama_saatleri) / count($baslama_saatleri)) : '-';
    $ort_bitis = count($bitis_saatleri) > 0 ? date('H:i', array_sum($bitis_saatleri) / count($bitis_saatleri)) : '-';

    $data[] = [
        'personel_id' => $personel->id,
        'adi_soyadi' => $personel->adi_soyadi,
        'toplam_gun' => count($gunler),
        'toplam_saat' => round($toplam_dakika / 60, 1),
        'ort_baslama' => $ort_baslama,
        'ort_bitis' => $ort_bitis,
        'gec_kalma' => $gec_kalma_sayisi
    ];
}

echo "Final Data Count: " . count($data) . "\n";
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
