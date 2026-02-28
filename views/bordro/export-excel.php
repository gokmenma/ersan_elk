<?php
/**
 * Genel Bordro Excel Export
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$donemId = $_GET['donem_id'] ?? null;
$ids = $_GET['ids'] ?? null;
$idArray = [];
if ($ids) {
    $idArray = explode(',', $ids);
    $idArray = array_filter(array_map('intval', $idArray));
}

if (!$donemId) {
    die('Dönem ID belirtilmelidir.');
}

try {
    $BordroPersonel = new BordroPersonelModel();
    $BordroDonem = new BordroDonemModel();
    $BordroParametre = new App\Model\BordroParametreModel();

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Dönemdeki personelleri getir
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    // Asgari ücreti çek
    $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donem->baslangic_tarihi) ?? 17002.12;

    // Dönem tarihlerini ve gün sayısını hesapla
    $donemBasTs = strtotime($donem->baslangic_tarihi);
    $donemBitTs = strtotime($donem->bitis_tarihi);
    $aydakiGunSayisi = date('t', $donemBasTs);

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Bordro Listesi');

    // Başlıklar (UI Table Format)
    $basliklar = [
        'A' => 'Birim',
        'B' => 'Ekip',
        'C' => 'Bölge',
        'D' => 'Personel',
        'E' => 'TC No',
        'F' => 'Maaş Tipi',
        'G' => 'Gün',
        'H' => 'Toplam Alacağı',
        'I' => 'Kesinti Tutarı',
        'J' => 'Net Alacağı',
        'K' => 'İcra Kesintisi',
        'L' => 'Banka',
        'M' => 'Sodexo',
        'N' => 'Elden'
    ];

    // Başlık stili
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4B5563']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];

    // Başlıkları yaz
    foreach ($basliklar as $kolon => $baslik) {
        $sheet->setCellValue($kolon . '1', $baslik);
        $sheet->getColumnDimension($kolon)->setAutoSize(true);
    }

    // Başlık satırına stil uygula (A1:N1)
    $sheet->getStyle('A1:N1')->applyFromArray($baslikStyle);

    // Veri stili
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ]
    ];

    // Personel verilerini ekle
    $satir = 2;
    foreach ($personeller as $personel) {
        // --- UI HESAPLAMA MANTIĞI ---
        $rawEkOdeme = floatval($personel->guncel_toplam_ek_odeme);
        $pMaasTutari = floatval($personel->maas_tutari ?? 0);
        $pToplamKesinti = floatval($personel->kesinti_tutar ?? 0);
        $pIsNet = ($personel->maas_durumu ?? '') == 'Net';
        $pIsPrimUsulu = (stripos($personel->maas_durumu ?? '', 'Prim') !== false);

        // İcra ve Kesinti
        $pIcra = floatval($personel->hd_icra_kesintisi ?? 0);
        $pKesintiHaricIcra = $pToplamKesinti - $pIcra;

        // Çalışma günü hesaplama (list.php logic)
        $pGunlukBase = 30;
        $pUcretsizIzinGunu = 0;
        $pUcretliIzinGunu = 0;
        $pIseGirisDI = false;
        $pIstenCikisDI = false;

        if ($personel->hd_ucretsiz_izin_gunu !== null) {
            $pUcretsizIzinGunu = intval($personel->hd_ucretsiz_izin_gunu);
        } elseif ($personel->hd_ucretsiz_izin_dusumu !== null && $personel->hd_nominal_maas !== null && floatval($personel->hd_nominal_maas) > 0) {
            $pUcretsizIzinGunu = round(floatval($personel->hd_ucretsiz_izin_dusumu) / (floatval($personel->hd_nominal_maas) / 30));
        }
        if ($personel->hd_ucretli_izin_gunu !== null) {
            $pUcretliIzinGunu = intval($personel->hd_ucretli_izin_gunu);
        }

        if (!empty($personel->ise_giris_tarihi)) {
            $iseGirisTs = strtotime($personel->ise_giris_tarihi);
            if ($iseGirisTs > $donemBasTs) {
                $pIseGirisDI = true;
            }
        }
        if (!empty($personel->isten_cikis_tarihi)) {
            $istenCikisTs = strtotime($personel->isten_cikis_tarihi);
            if ($istenCikisTs >= $donemBasTs && $istenCikisTs < $donemBitTs) {
                $pIstenCikisDI = true;
            }
        }

        if ($pIseGirisDI && $pIstenCikisDI) {
            $pGunlukBase = date('j', $istenCikisTs) - date('j', $iseGirisTs) + 1;
        } elseif ($pIseGirisDI) {
            $pGunlukBase = $aydakiGunSayisi - date('j', $iseGirisTs) + 1;
        } elseif ($pIstenCikisDI) {
            $pGunlukBase = date('j', $istenCikisTs);
        } elseif ($pUcretsizIzinGunu > 0 || $pUcretliIzinGunu > 0) {
            $pGunlukBase = $aydakiGunSayisi;
        } else {
            $pGunlukBase = 30;
        }
        if ($pGunlukBase < 0) $pGunlukBase = 0;

        $pCalismaGunu = $pGunlukBase;
        if ($personel->hd_fiili_calisma_gunu !== null) {
            $pCalismaGunu = intval($personel->hd_fiili_calisma_gunu);
        } elseif ($personel->hd_fiili_calisma_gunu === null) {
            $pCalismaGunu = $pGunlukBase - $pUcretsizIzinGunu - $pUcretliIzinGunu;
        }

        // Toplam Alacağı
        if ($pIsPrimUsulu) {
            $pToplamAlacagi = floatval($personel->brut_maas ?? 0) + $rawEkOdeme;
        } elseif ($pIsNet || ($personel->maas_durumu ?? '') == 'Brüt') {
            $pToplamAlacagi = (($pMaasTutari / 30) * $pCalismaGunu) + $rawEkOdeme;
        } else {
            $pToplamAlacagi = $pMaasTutari + $rawEkOdeme;
        }

        // Net alacağı
        $pNetAlacagi = $pToplamAlacagi - $pKesintiHaricIcra;

        // Dağıtım kalemleri
        $sodexoP = floatval($personel->sodexo_odemesi ?? 0);
        if ($pCalismaGunu >= 30) {
            $bankaBaz = $asgariUcretNet;
        } else {
            $bankaBaz = ($asgariUcretNet / 30) * $pCalismaGunu;
        }
        $bankaMax = max(0, $pNetAlacagi - $sodexoP);
        $bankaBaz = min($bankaBaz, $bankaMax);
        $bankaP = max(0, $bankaBaz - $pIcra);

        if (($personel->sgk_yapilan_firma ?? '') === 'İŞKUR') {
            $bankaP = 0;
        }

        $pNetMaasGercek = max(0, $pNetAlacagi - $pIcra);
        $digerP = floatval($personel->diger_odeme ?? 0);
        $eldenP = max(0, $pNetMaasGercek - $bankaP - $sodexoP - $digerP);

        // Birim Kodu
        $deptName = $personel->departman ?? '-';
        $deptUp = mb_convert_case($deptName, MB_CASE_UPPER, "UTF-8");
        $birimCode = '';
        if (strpos($deptUp, 'OKUMA') !== false) $birimCode = 'EO';
        elseif (strpos($deptUp, 'KESME') !== false) $birimCode = 'KA';
        elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false) $birimCode = 'ST';
        elseif (strpos($deptUp, 'KAÇAK') !== false) $birimCode = 'KÇ';
        else {
            $words = explode(' ', $deptUp);
            if (count($words) >= 2) {
                $birimCode = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
            } else {
                $birimCode = mb_substr($deptUp, 0, 2);
            }
        }

        // Excel Satırı Doldur
        $sheet->setCellValue('A' . $satir, $birimCode);
        $sheet->setCellValue('B' . $satir, $personel->ekip_adi ?? '');
        $sheet->setCellValue('C' . $satir, $personel->ekip_bolge ?? '');
        $sheet->setCellValue('D' . $satir, $personel->adi_soyadi);
        $sheet->setCellValueExplicit('E' . $satir, $personel->tc_kimlik_no ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('F' . $satir, $personel->maas_durumu ?? '-');
        $sheet->setCellValue('G' . $satir, $pCalismaGunu);
        $sheet->setCellValue('H' . $satir, $pToplamAlacagi);
        $sheet->setCellValue('I' . $satir, $pKesintiHaricIcra);
        $sheet->setCellValue('J' . $satir, $pNetAlacagi);
        $sheet->setCellValue('K' . $satir, $pIcra);
        $sheet->setCellValue('L' . $satir, $bankaP);
        $sheet->setCellValue('M' . $satir, $sodexoP);
        $sheet->setCellValue('N' . $satir, $eldenP);

        // Sayı formatları (H:N)
        $sheet->getStyle('H' . $satir . ':N' . $satir)->getNumberFormat()->setFormatCode('#,##0.00');

        $satir++;
    }

    // Tüm tabloya stil uygula
    $sheet->getStyle('A1:N' . ($satir - 1))->applyFromArray($dataStyle);

    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'bordro_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';

    // HTTP başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
