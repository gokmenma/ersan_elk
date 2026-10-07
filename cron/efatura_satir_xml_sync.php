<?php
/**
 * E-Fatura Satır Kalemleri ve Açıklamaları XML Senkronizasyon Scripti
 * 
 * Bu script, sunucudaki kayıtlı tüm faturaların UBL XML dosyalarını okuyarak
 * fatura_satirlari tablosundaki urun_hizmet_adi ve urun_kodu alanlarını
 * GİB standartlarına ve XML içindeki gerçek mal/hizmet açıklamalarına (araç plakası vb.)
 * göre günceller.
 * 
 * Çalıştırma:
 * php cron/efatura_satir_xml_sync.php
 * veya
 * /opt/lampp/bin/php cron/efatura_satir_xml_sync.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu script yalnızca CLI (komut satırı) üzerinden çalıştırılabilir.\n");
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Model\EInvoiceModel;
use App\Helper\Security;

echo "=== E-Fatura Satır XML Senkronizasyonu Başlatılıyor ===\n";

$model = new EInvoiceModel();
$dbProperty = (new \ReflectionClass($model))->getProperty('db');
$dbProperty->setAccessible(true);
$pdo = $dbProperty->getValue($model);

$stmt = $pdo->query("SELECT id, fatura_no, ubl_xml_path, yon FROM faturalar WHERE ubl_xml_path IS NOT NULL AND ubl_xml_path != '' ORDER BY id ASC");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalInvoices = count($invoices);
echo "Taranacak Fatura Sayısı: {$totalInvoices}\n";

$updatedLines = 0;
$updatedInvoices = 0;
$skippedFiles = 0;

foreach ($invoices as $idx => $inv) {
    $invId = (int)$inv['id'];
    $faturaNo = $inv['fatura_no'];
    $path = $inv['ubl_xml_path'];

    if (!file_exists($path)) {
        $skippedFiles++;
        continue;
    }

    $content = file_get_contents($path);
    if (strpos($content, '<Invoice') === false && strpos($content, ':Invoice') === false) {
        try {
            $decrypted = Security::decryptFile($content);
            if ($decrypted) {
                $content = $decrypted;
            }
        } catch (\Throwable $e) {
            // Şifre çözülemezse veya dosya ham ise devam et
        }
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = @$dom->loadXML($content);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        continue;
    }

    $xp = new DOMXPath($dom);

    $lineIdx = 0;
    $invUpdated = false;

    foreach ($xp->query("//*[local-name()='InvoiceLine']") as $line) {
        $lineIdx++;
        $desc = trim($xp->evaluate("string(.//*[local-name()='Item']/*[local-name()='Description'])", $line));
        $name = trim($xp->evaluate("string(.//*[local-name()='Item']/*[local-name()='Name'])", $line));
        $code = trim($xp->evaluate("string(.//*[local-name()='Item']/*[local-name()='SellersItemIdentification']/*[local-name()='ID'])", $line));

        if (empty($code) && !empty($name) && !empty($desc)) {
            $code = $name;
        }

        $finalName = $name;
        if (!empty($desc) && ($name === $code || empty($name) || $name !== $desc)) {
            $finalName = $desc;
            if (empty($code) && !empty($name)) {
                $code = $name;
            }
        }

        if (!empty($finalName) || !empty($code)) {
            $upd = $pdo->prepare("
                UPDATE fatura_satirlari 
                SET urun_hizmet_adi = :uname, 
                    urun_kodu = CASE WHEN :ucode != '' THEN :ucode ELSE urun_kodu END 
                WHERE fatura_id = :fid 
                  AND sira_no = :sno
            ");
            $upd->execute([
                'uname' => mb_substr($finalName ?: $name, 0, 255),
                'ucode' => mb_substr($code, 0, 50),
                'fid'   => $invId,
                'sno'   => $lineIdx
            ]);

            if ($upd->rowCount() > 0) {
                $updatedLines++;
                $invUpdated = true;
            }
        }
    }

    if ($invUpdated) {
        $updatedInvoices++;
    }

    if (($idx + 1) % 100 === 0 || ($idx + 1) === $totalInvoices) {
        echo "İlerleyiş: " . ($idx + 1) . " / {$totalInvoices} fatura işlendi...\n";
    }
}

echo "\n=== Senkronizasyon Tamamlandı ===\n";
echo "Güncellenen Fatura Sayısı: {$updatedInvoices}\n";
echo "Güncellenen Satır/Kalem Sayısı: {$updatedLines}\n";
echo "Bulunamayan XML Dosyası: {$skippedFiles}\n";
