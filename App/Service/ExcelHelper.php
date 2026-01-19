<?php

namespace App\Services; // Kendi namespace yapınıza göre düzenleyin

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Exception;

/**
 * ExcelHelper sınıfı, Excel dosyalarıyla ilgili işlemleri kolaylaştırmak için kullanılır.
 * Özellikle başlıkları okuma ve hatalı kayıtları içeren Excel dosyaları oluşturma işlevleri sağlar.
 */

class ExcelHelper
{
    /**
     * @var string Dosyaların kaydedileceği ve public olarak erişilebilecek dizin.
     */
    private string $publicDownloadsPath;

    /**
     * @var string Dosya URL'lerinin oluşturulması için temel URL.
     */
    private string $baseUrl;

    public function __construct()
    {
        // Bu yolları projenizin yapısına göre yapılandırın.
        // __DIR__ bu dosyanın bulunduğu dizindir (App/Services).
        // public klasörüne ulaşmak için 2 seviye yukarı çıkıyoruz.
        $this->publicDownloadsPath = dirname(__DIR__, 2) . '/public/downloads/';

        // Bu URL'yi projenizin domain'ine göre ayarlayın veya dinamik olarak alın.
        // Basitlik açısından şimdilik sabit bir yol varsayalım.
        $this->baseUrl = '/public/downloads/'; // Örnek: http://example.com/public/downloads/
    }

    /**
     * Verilen bir Excel dosyasının başlık satırını bir dizi olarak döndürür.
     *
     * @param string $filePath Okunacak Excel dosyasının yolu.
     * @return array Başlıklar.
     * @throws \Exception
     */
    public function getHeaders(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $headerRow = $worksheet->getRowIterator(1)->current();

        $headers = [];
        foreach ($headerRow->getCellIterator() as $cell) {
            $headers[] = trim($cell->getValue() ?? '');
        }

        return $headers;
    }

    /**
     * Hatalı satırlardan bir Excel dosyası oluşturur ve dosyanın public URL'sini döndürür.
     *
     * @param array $errorRows Hatalı satır verilerini içeren dizi.
     * @param array $originalHeader Orijinal Excel dosyasının başlıkları.
     * @return string|null Oluşturulan dosyanın public URL'si veya hata durumunda null.
     */
    public function createErrorFile(array $errorRows, array $originalHeader): ?string
    {
        if (empty($errorRows)) {
            return null;
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Hatalı Kayıtlar');

            // ...
            // 1. Orijinal başlıklardaki boş elemanları temizle.
            $filteredOriginalHeader = array_filter($originalHeader, function ($value) {
                // Sadece null veya boş değil, aynı zamanda boşluklardan oluşan değerleri de temizle
                return trim($value) !== '';
            });

            // 2. Temizlenmiş başlık listesine 'Hata Mesajı' sütununu ekle.
            $headerWithErrors = array_values($filteredOriginalHeader); // array_values() ile anahtarları sıfırla
            $headerWithErrors[] = 'Hata Mesajı';

            // 3. Yeni başlıkları sayfaya yaz.
            $sheet->fromArray($headerWithErrors, NULL, 'A1');

            $rowIndex = 2;
            foreach ($errorRows as $errorRow) {
                $rowData = [];
                // Orijinal başlık sırasına göre veriyi doldur
                foreach ($filteredOriginalHeader as $headerText) {
                    $rowData[] = $errorRow['data'][$headerText] ?? '';
                }
                $rowData[] = $errorRow['error_message'];

                $sheet->fromArray($rowData, NULL, 'A' . $rowIndex);
                $rowIndex++;
            }

            // Sütunları otomatik genişlet
            foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Kayıt dizininin varlığını kontrol et, yoksa oluştur
            if (!is_dir($this->publicDownloadsPath)) {
                if (!mkdir($this->publicDownloadsPath, 0775, true) && !is_dir($this->publicDownloadsPath)) {
                    // Loglama yap ve null dön
                    error_log("Failed to create directory: " . $this->publicDownloadsPath);
                    return null;
                }
            }

            $filename = 'hatali_kayitlar_' . uniqid() . '.xlsx';
            $filePath = $this->publicDownloadsPath . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return $this->baseUrl . $filename;
        } catch (\Exception $e) {
            error_log("ExcelHelper: Hata dosyası oluşturulamadı - " . $e->getMessage());
            return null; // Hata durumunda null döndür.
        }
    }
}
