<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

session_start();
include dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Security;
use App\Model\FormlarModel;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    die('Yetkisiz erişim.');
}

$firma_id = $_SESSION['firma_id'] ?? 1;
$formIdEnc = $_GET['id'] ?? '';

$formId = Security::decrypt($formIdEnc);
if (!$formId) {
    die('Geçersiz form ID.');
}

$Formlar = new FormlarModel();
$stmt = $Formlar->db->prepare("SELECT * FROM formlar WHERE id = :id AND firma_id = :firma_id");
$stmt->execute(['id' => $formId, 'firma_id' => $firma_id]);
$form = $stmt->fetch(PDO::FETCH_OBJ);

if (!$form) {
    die('Form bulunamadı.');
}

$filePath = dirname(__DIR__, 2) . '/' . $form->dosya_yolu;
if (!file_exists($filePath)) {
    die('Şablon dosyası sunucuda bulunamadı.');
}

$replacements = [];
$replacements['{TARIH}'] = date('d.m.Y');
$replacements['${TARIH}'] = date('d.m.Y');

$personelIdEnc = $_GET['personel_id'] ?? '';
if (!empty($personelIdEnc)) {
    $personelId = Security::decrypt($personelIdEnc);
    if ($personelId) {
        // Personel verisini al
        $stmt = $Formlar->db->prepare("SELECT * FROM personel WHERE id = :id AND firma_id = :firma_id");
        $stmt->execute(['id' => $personelId, 'firma_id' => $firma_id]);
        $personel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($personel) {
            $personelAdiSoyadi = trim($personel['adi_soyadi'] ?? '');
            $tcNo = $personel['tc_kimlik_no'] ?? '';
            $telefon = $personel['cep_telefonu'] ?? '';
            $unvan = $personel['gorev'] ?? '';
            $iseGiris = !empty($personel['ise_giris_tarihi']) ? date('d.m.Y', strtotime($personel['ise_giris_tarihi'])) : '';
            $adres = $personel['ev_adresi'] ?? ($personel['adres'] ?? '');

            $replacements += [
                '{PERSONEL_ADI}' => $personelAdiSoyadi,
                '${PERSONEL_ADI}' => $personelAdiSoyadi,
                '{AD_SOYAD}' => $personelAdiSoyadi,
                '${AD_SOYAD}' => $personelAdiSoyadi,
                '{TC_KIMLIK}' => $tcNo,
                '${TC_KIMLIK}' => $tcNo,
                '{TC_NO}' => $tcNo,
                '${TC_NO}' => $tcNo,
                '{TELEFON}' => $telefon,
                '${TELEFON}' => $telefon,
                '{UNVAN}' => $unvan,
                '${UNVAN}' => $unvan,
                '{ISE_GIRIS}' => $iseGiris,
                '${ISE_GIRIS}' => $iseGiris,
                '{ADRES}' => $adres,
                '${ADRES}' => $adres
            ];
        }
    }
}

$aracIdEnc = $_GET['arac_id'] ?? '';
if (!empty($aracIdEnc)) {
    $aracId = Security::decrypt($aracIdEnc);
    if ($aracId) {
        $stmt = $Formlar->db->prepare("SELECT * FROM araclar WHERE id = :id AND firma_id = :firma_id");
        $stmt->execute(['id' => $aracId, 'firma_id' => $firma_id]);
        $arac = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($arac) {
            $plaka = $arac['plaka'] ?? '';
            $marka = $arac['marka'] ?? '';
            $model = $arac['model'] ?? '';
            $basKm = $_GET['bas_km'] ?? '';
            $bitKm = $_GET['bit_km'] ?? '';
            $aciklama = $_GET['aciklama'] ?? '';

            $replacements += [
                '{PLAKA}' => $plaka,
                '${PLAKA}' => $plaka,
                '{MARKA}' => $marka,
                '${MARKA}' => $marka,
                '{MODEL}' => $model,
                '${MODEL}' => $model,
                '{BASLANGIC_KM}' => $basKm,
                '${BASLANGIC_KM}' => $basKm,
                '{BITIS_KM}' => $bitKm,
                '${BITIS_KM}' => $bitKm,
                '{ACIKLAMA}' => $aciklama,
                '${ACIKLAMA}' => $aciklama,
                '{ARAC_BAKIM_KM}' => $arac['guncel_km'] ?? '',
                '${ARAC_BAKIM_KM}' => $arac['guncel_km'] ?? ''
            ];
        }
    }
}

$imei = $_GET['imei'] ?? '';
$seriNo = $_GET['seri_no'] ?? '';
if (!empty($imei) || !empty($seriNo)) {
    $replacements += [
        '{IMEI}' => $imei,
        '${IMEI}' => $imei,
        '{SERI_NO}' => $seriNo,
        '${SERI_NO}' => $seriNo
    ];
}

$izinBaslangic = $_GET['izin_baslangic'] ?? '';
$izinBitis = $_GET['izin_bitis'] ?? '';
$izinIseBaslama = $_GET['izin_ise_baslama'] ?? '';
$izinGun = $_GET['izin_gun'] ?? '';
$izinNedeni = $_GET['izin_nedeni'] ?? '';

if (!empty($izinBaslangic) || !empty($izinBitis) || !empty($izinGun) || !empty($izinNedeni) || !empty($izinIseBaslama)) {
    $baslangicFmt = !empty($izinBaslangic) ? date('d.m.Y', strtotime($izinBaslangic)) : '';
    $bitisFmt = !empty($izinBitis) ? date('d.m.Y', strtotime($izinBitis)) : '';
    $iseBaslamaFmt = !empty($izinIseBaslama) ? date('d.m.Y', strtotime($izinIseBaslama)) : '';

    $replacements += [
        '{IZIN_BASLANGIC}' => $baslangicFmt,
        '${IZIN_BASLANGIC}' => $baslangicFmt,
        '{IZIN_BITIS}' => $bitisFmt,
        '${IZIN_BITIS}' => $bitisFmt,
        '{IZIN_ISE_BASLAMA}' => $iseBaslamaFmt,
        '${IZIN_ISE_BASLAMA}' => $iseBaslamaFmt,
        '{IZIN_GUN}' => $izinGun,
        '${IZIN_GUN}' => $izinGun,
        '{IZIN_NEDENI}' => $izinNedeni,
        '${IZIN_NEDENI}' => $izinNedeni
    ];
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$outputName = 'Doldurulmus_' . $form->dosya_adi;

if (in_array($ext, ['doc', 'docx'])) {
    if ($ext === 'docx') {
        try {
            $templateProcessor = new TemplateProcessor($filePath);
            foreach ($replacements as $search => $replace) {
                $templateProcessor->setValue(str_replace(['{', '}'], '', $search), $replace);
            }
            
            header("Content-Description: File Transfer");
            header('Content-Disposition: attachment; filename="' . $outputName . '"');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            
            $templateProcessor->saveAs('php://output');
            exit;
        } catch (Exception $e) {
            die('Word okuma hatası: ' . $e->getMessage());
        }
    } else {
        // Fallback for native download if not docx
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="' . $outputName . '"');
        readfile($filePath);
        exit;
    }

} elseif (in_array($ext, ['xls', 'xlsx'])) {
    try {
        $spreadsheet = IOFactory::load($filePath);
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            foreach ($worksheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = $cell->getValue();
                    if (is_string($val)) {
                        $newVal = str_replace(array_keys($replacements), array_values($replacements), $val);
                        if ($val !== $newVal) {
                            $cell->setValue($newVal);
                        }
                    }
                }
            }
        }
        
        $writerType = $ext === 'xlsx' ? 'Xlsx' : 'Xls';
        $contentType = $ext === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/vnd.ms-excel';
        
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="' . $outputName . '"');
        header("Content-Type: $contentType");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $writer = IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die('Excel okuma hatası: ' . $e->getMessage());
    }
} else {
    // For other files, just download
    header("Content-Description: File Transfer");
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    header('Content-Type: ' . finfo_file($finfo, $filePath));
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}
