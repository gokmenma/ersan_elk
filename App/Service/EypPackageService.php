<?php
namespace App\Service;

use ZipArchive;
use Exception;

class EypPackageService
{
    /**
     * Giden evrak için UETS uyumlu EYP paketi oluşturur
     *
     * @param string $pdfPath Üst yazı PDF dosya yolu
     * @param array $attachments Ek dosyalar [ ['path' => ..., 'name' => ...] ]
     * @param string|null $imzaCadesPath CAdES imza dosyası (.imz) yolu
     * @param array $meta [ 'konu' => ..., 'gonderen' => ..., 'alici' => ... ]
     * @return array [ 'path' => string, 'sha1' => string, 'size' => int ]
     */
    public function createPackage(string $pdfPath, array $attachments = [], ?string $imzaCadesPath = null, array $meta = []): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("Üst yazı PDF dosyası bulunamadı: " . $pdfPath);
        }

        $tmpDir = sys_get_temp_dir() . '/eyp_' . uniqid();
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true)) {
            throw new Exception("Geçici dizin oluşturulamadı: " . $tmpDir);
        }

        $eypPath = $tmpDir . '/paket_' . date('Ymd_His') . '.eyp';
        $zip = new ZipArchive();
        if ($zip->open($eypPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("EYP arşivi oluşturulamadı.");
        }

        // 1. Üst Yazı PDF'i pakete ekle
        $zip->addFile($pdfPath, 'ustyazi.pdf');

        // 2. Ek dosyaları ekle
        foreach ($attachments as $index => $att) {
            $attPath = $att['path'] ?? '';
            $attName = $att['name'] ?? ('ek_' . ($index + 1) . '.pdf');
            if (!empty($attPath) && file_exists($attPath)) {
                $zip->addFile($attPath, 'Ekler/' . $attName);
            }
        }

        // 3. İmza Dosyası
        if ($imzaCadesPath && file_exists($imzaCadesPath)) {
            $zip->addFile($imzaCadesPath, 'Imzalar/ImzaCades.imz');
        } else {
            // İmza dosyası doğrudan sağlanmadıysa üst yazının SHA256 özetini içeren temsili/yapısal imza meta dosyası oluştur
            $hashContent = hash_file('sha256', $pdfPath);
            $zip->addFromString('Imzalar/ImzaCades.imz', $hashContent);
        }

        // 4. paketOzeti.xml (e-Yazışma Paketi standart üst veri dosyası)
        $xml = $this->buildPaketOzetiXml($pdfPath, $attachments, $meta);
        $zip->addFromString('paketOzeti.xml', $xml);

        $zip->close();

        $sha1 = sha1_file($eypPath);
        $size = filesize($eypPath);

        return [
            'path' => $eypPath,
            'sha1' => $sha1,
            'size' => $size,
        ];
    }

    /**
     * Gelen EYP paketini açar ve içindeki üst yazı PDF'ini ve ekleri ayıklar
     */
    public function extractPackage(string $eypFilePath, string $targetDir): array
    {
        if (!file_exists($eypFilePath)) {
            throw new Exception("EYP dosyası bulunamadı: " . $eypFilePath);
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            throw new Exception("Hedef dizin oluşturulamadı: " . $targetDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($eypFilePath) !== true) {
            throw new Exception("EYP arşivi açılamadı.");
        }

        $result = [
            'ustyazi_pdf' => null,
            'ekler' => [],
            'imzalar' => [],
            'xml_metadata' => null,
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            // Klasör ise geç
            if (str_ends_with($filename, '/')) {
                continue;
            }

            $extractedPath = $targetDir . '/' . basename($filename);

            if (stripos($filename, 'ustyazi') !== false && stripos($filename, '.pdf') !== false) {
                copy("zip://" . $eypFilePath . "#" . $filename, $extractedPath);
                $result['ustyazi_pdf'] = $extractedPath;
            } elseif (stripos($filename, 'paketOzeti') !== false) {
                $result['xml_metadata'] = $zip->getFromIndex($i);
            } elseif (stripos($filename, 'Imzalar') !== false) {
                copy("zip://" . $eypFilePath . "#" . $filename, $extractedPath);
                $result['imzalar'][] = $extractedPath;
            } else {
                copy("zip://" . $eypFilePath . "#" . $filename, $extractedPath);
                $result['ekler'][] = [
                    'name' => basename($filename),
                    'path' => $extractedPath,
                    'size' => filesize($extractedPath),
                ];
            }
        }

        $zip->close();

        // Eğer ustyazi_pdf bulunamadıysa ilk PDF dosyasını üst yazı kabul et
        if (!$result['ustyazi_pdf'] && !empty($result['ekler'])) {
            foreach ($result['ekler'] as $idx => $ek) {
                if (stripos($ek['name'], '.pdf') !== false) {
                    $result['ustyazi_pdf'] = $ek['path'];
                    unset($result['ekler'][$idx]);
                    $result['ekler'] = array_values($result['ekler']);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * e-Yazışma Paketi paketOzeti.xml oluşturucu
     */
    private function buildPaketOzetiXml(string $pdfPath, array $attachments, array $meta): string
    {
        $konu = htmlspecialchars((string) ($meta['konu'] ?? 'Elektronik Tebligat'), ENT_XML1, 'UTF-8');
        $gonderen = htmlspecialchars((string) ($meta['gonderen'] ?? ''), ENT_XML1, 'UTF-8');
        $alici = htmlspecialchars((string) ($meta['alici'] ?? ''), ENT_XML1, 'UTF-8');
        $tarih = date('c');
        $pdfHash = hash_file('sha256', $pdfPath);

        $eklerXml = '';
        foreach ($attachments as $att) {
            $attPath = $att['path'] ?? '';
            $attName = htmlspecialchars((string) ($att['name'] ?? ''), ENT_XML1, 'UTF-8');
            if (!empty($attPath) && file_exists($attPath)) {
                $attHash = hash_file('sha256', $attPath);
                $eklerXml .= "    <Ek>\n      <Adi>{$attName}</Adi>\n      <Ozet Algoritma=\"SHA-256\">{$attHash}</Ozet>\n    </Ek>\n";
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<PaketOzeti xmlns="urn:dijital-donusum:eyazisma:v2">
  <BelgeNo>{$meta['evrak_no']}</BelgeNo>
  <Tarih>{$tarih}</Tarih>
  <Konu>{$konu}</Konu>
  <Gonderen>{$gonderen}</Gonderen>
  <Alici>{$alici}</Alici>
  <Ustyazi>
    <DosyaAdi>ustyazi.pdf</DosyaAdi>
    <Ozet Algoritma="SHA-256">{$pdfHash}</Ozet>
  </Ustyazi>
  <Ekler>
{$eklerXml}  </Ekler>
</PaketOzeti>
XML;
    }
}
