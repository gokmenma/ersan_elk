<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Model\AparatHareketModel;
use App\Model\AparatStokModel;
use App\Model\KesmeAcmaIslemModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('aparat_takip') && !Gate::allows('aparat-takip/list') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

$tip = $_GET['tip'] ?? 'stok';
$baslangic = Date::convertExcelDate($_GET['start_date'] ?? '', 'Y-m-d') ?: date('Y-m-01');
$bitis = Date::convertExcelDate($_GET['end_date'] ?? '', 'Y-m-d') ?: date('Y-m-d');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$basligStili = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F4B7C']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

function basliklariYaz($sheet, array $basliklar, array $stil): void
{
    $sheet->fromArray($basliklar, null, 'A1');
    $sonKolon = Coordinate::stringFromColumnIndex(count($basliklar));
    $sheet->getStyle('A1:' . $sonKolon . '1')->applyFromArray($stil);
    $sheet->getRowDimension(1)->setRowHeight(22);
    for ($i = 1; $i <= count($basliklar); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
}

if ($tip === 'hareket') {
    $Hareket = new AparatHareketModel();
    $kayitlar = $Hareket->listele([
        'baslangic' => $baslangic,
        'bitis' => $bitis,
        'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
        'aparat_tip_id' => (int) ($_GET['aparat_tip_id'] ?? 0),
        'hareket_tipi' => $_GET['hareket_tipi'] ?? '',
        'sahip_tipi' => $_GET['sahip_tipi'] ?? '',
    ], 20000);

    $sheet->setTitle('Hareket Dökümü');
    basliklariYaz($sheet, ['Tarih', 'Hareket Tipi', 'Havuz', 'Ekip', 'Aparat', 'Adet',
        'Personel', 'Kullanıcı', 'Referans', 'Açıklama', 'İptal'], $basligStili);

    $satir = 2;
    foreach ($kayitlar as $k) {
        $sheet->fromArray([
            date('d.m.Y H:i', strtotime($k['tarih'])),
            AparatHareketModel::HAREKET_TIPLERI[$k['hareket_tipi']] ?? $k['hareket_tipi'],
            AparatHareketModel::HAVUZLAR[$k['sahip_tipi']] ?? $k['sahip_tipi'],
            $k['ekip_adi'] ?? '',
            $k['aparat_adi'] ?? '',
            (int) $k['adet'],
            $k['personel_adi'] ?? '',
            $k['kullanici_adi'] ?? '',
            trim(($k['referans_tipi'] ?? '') . ' #' . ($k['referans_id'] ?? '')),
            $k['aciklama'] ?? '',
            (int) $k['iptal_mi'] === 1 ? 'Evet' : '',
        ], null, 'A' . $satir);
        $satir++;
    }

    $dosyaAdi = 'aparat_hareket_' . $baslangic . '_' . $bitis . '.xlsx';
} elseif ($tip === 'islem') {
    $Islem = new KesmeAcmaIslemModel();
    $kayitlar = $Islem->listele([
        'baslangic' => $baslangic,
        'bitis' => $bitis,
        'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
        'islem_tipi' => $_GET['islem_tipi'] ?? '',
        'aparat_tip_id' => (int) ($_GET['aparat_tip_id'] ?? 0),
        'durum' => $_GET['durum'] ?? '',
        'sadece_negatif' => !empty($_GET['sadece_negatif']) ? 1 : 0,
    ], 20000);

    $sheet->setTitle('Saha İşlemleri');
    basliklariYaz($sheet, ['Tarih', 'İşlem', 'Ekip', 'Personel', 'Abone No', 'Sayaç No',
        'İlçe', 'Mahalle', 'Aparat', 'Adet', 'Aparat Durumu', 'Kaynak', 'Durum',
        'Negatif Stok', 'Fotoğraf', 'Açıklama'], $basligStili);

    $satir = 2;
    foreach ($kayitlar as $k) {
        $sheet->fromArray([
            date('d.m.Y', strtotime($k['tarih'])),
            $k['islem_tipi'] === 'kesme' ? 'Kesme' : 'Açma',
            $k['ekip_kodu'] ?? $k['ekip_adi'] ?? '',
            $k['personel_adi'] ?? '',
            $k['abone_no'] ?? '',
            $k['sayac_no'] ?? '',
            $k['ilce'] ?? '',
            $k['mahalle'] ?? '',
            (int) $k['aparatsiz'] === 1 ? 'Kullanılmadı' : ($k['aparat_adi'] ?? ''),
            (int) $k['adet'],
            KesmeAcmaIslemModel::APARAT_DURUMLARI[$k['aparat_durumu']] ?? '',
            $k['kaynak'] === 'pwa' ? 'Telefon' : 'Panel',
            $k['durum'] === 'aktif' ? 'Aktif' : 'İptal',
            (int) $k['negatif_stok'] === 1 ? 'Evet' : '',
            (int) $k['foto_sayisi'],
            $k['aciklama'] ?? '',
        ], null, 'A' . $satir);
        $satir++;
    }

    $dosyaAdi = 'aparat_saha_islemleri_' . $baslangic . '_' . $bitis . '.xlsx';
} else {
    $Stok = new AparatStokModel();
    $matris = $Stok->matris();

    $sheet->setTitle('Stok Durumu');

    $basliklar = ['Sahip', 'Ekip Kodu', 'Bölge'];
    foreach ($matris['tipler'] as $t) {
        $basliklar[] = $t['ad'];
    }
    $basliklar[] = 'Toplam';

    basliklariYaz($sheet, $basliklar, $basligStili);

    $satir = 2;
    foreach ($matris['satirlar'] as $s) {
        $veri = [$s['baslik'], $s['ekip_kodu'] ?? '', $s['bolge']];
        foreach ($matris['tipler'] as $t) {
            $veri[] = (int) ($s['adetler'][$t['id']] ?? 0);
        }
        $veri[] = (int) $s['toplam'];
        $sheet->fromArray($veri, null, 'A' . $satir);
        $satir++;
    }

    $toplamSatir = ['TOPLAM', '', ''];
    foreach ($matris['tipler'] as $t) {
        $toplamSatir[] = (int) ($matris['sutun_toplam'][$t['id']] ?? 0);
    }
    $toplamSatir[] = (int) $matris['genel_toplam'];

    $sheet->fromArray($toplamSatir, null, 'A' . $satir);
    $sonKolon = Coordinate::stringFromColumnIndex(count($basliklar));
    $sheet->getStyle('A' . $satir . ':' . $sonKolon . $satir)->getFont()->setBold(true);

    $dosyaAdi = 'aparat_stok_durumu_' . date('Y-m-d') . '.xlsx';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
