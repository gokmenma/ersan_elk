<?php

namespace App\Service;

use App\Helper\Helper;
use Exception;

/**
 * Cari hesap ekstresi (PDF) okuma servisi.
 * Konnash / benzeri "Tedarikçi - Müşteri İşlemleri Geçmişi" çıktılarını satır satır ayrıştırır.
 */
class CariPdfImportService
{
    private const AMOUNT = '-?[\d.]*\d[.,]\d{2}';

    /**
     * PDF dosyasını okuyup hareket satırlarını döndürür.
     *
     * @param string $filePath Sunucudaki geçici dosya yolu
     * @return array ['cari_adi' => string|null, 'rows' => array, 'toplam_verdim' => float, 'toplam_aldim' => float, 'atlanan' => int]
     * @throws Exception
     */
    public function parseFile(string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new Exception("PDF dosyası okunamadı.");
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        if (trim($text) === '') {
            throw new Exception("PDF içeriği okunamadı. Taranmış (resim) PDF desteklenmiyor.");
        }

        return $this->parseText($text);
    }

    /**
     * PDF'ten çıkarılan düz metni ayrıştırır.
     *
     * @param string $text
     * @return array
     */
    public function parseText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $verdimOnce = $this->isVerdimFirst($lines);

        $rows = [];
        $atlanan = 0;
        $cariAdi = null;
        $beyan = ['aldim' => null, 'verdim' => null, 'baslangic_bakiye' => null];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[\t\x{00A0}]+/u', ' ', $line));
            $line = preg_replace('/\s{2,}/', ' ', $line);

            if ($line === '') {
                continue;
            }

            if ($cariAdi === null && preg_match('/^(?:Tedarik[çc]i|M[üu][şs]teri|Cari)\s*:?\s*(.+)$/ui', $line, $m)) {
                $ad = trim($m[1]);
                if ($ad !== '' && !preg_match('/^[\d\s.,-]+$/u', $ad)) {
                    $cariAdi = mb_substr($ad, 0, 150);
                }
                continue;
            }

            if (preg_match('/^(Ba[şs]lang[ıi][çc] bakiyesi|Ald[ıi]m|Verdim)\s*:\s*(' . self::AMOUNT . ')$/ui', $line, $m)) {
                $tutar = $this->toAmount($m[2]);
                if (mb_stripos($m[1], 'Ald') === 0) {
                    $beyan['aldim'] = $tutar;
                } elseif (mb_stripos($m[1], 'Verdim') === 0) {
                    $beyan['verdim'] = $tutar;
                } else {
                    $beyan['baslangic_bakiye'] = $tutar;
                }
                continue;
            }

            $parsed = $this->parseRow($line, $verdimOnce);
            if ($parsed === null) {
                if (preg_match('/^\d{1,5}\s+\d{2,4}[.\/-]\d{2}[.\/-]\d{2,4}\s/u', $line)) {
                    $atlanan++;
                }
                continue;
            }

            $rows[] = $parsed;
        }

        $toplamVerdim = 0.0;
        $toplamAldim = 0.0;
        foreach ($rows as $row) {
            $toplamVerdim += $row['alacak'];
            $toplamAldim += $row['borc'];
        }

        return [
            'cari_adi' => $cariAdi,
            'rows' => $rows,
            'toplam_verdim' => round($toplamVerdim, 2),
            'toplam_aldim' => round($toplamAldim, 2),
            'beyan_verdim' => $beyan['verdim'],
            'beyan_aldim' => $beyan['aldim'],
            'baslangic_bakiye' => $beyan['baslangic_bakiye'],
            'atlanan' => $atlanan
        ];
    }

    /**
     * Başlık satırına bakarak "Verdim" sütununun "Aldım" sütunundan önce gelip gelmediğini belirler.
     */
    private function isVerdimFirst(array $lines): bool
    {
        foreach ($lines as $line) {
            $verdimPos = mb_stripos($line, 'Verdim');
            $aldimPos = mb_stripos($line, 'Aldım');
            if ($aldimPos === false) {
                $aldimPos = mb_stripos($line, 'Aldim');
            }
            if ($verdimPos !== false && $aldimPos !== false && mb_stripos($line, 'Tarih') !== false) {
                return $verdimPos < $aldimPos;
            }
        }
        return true;
    }

    /**
     * Tek bir hareket satırını ayrıştırır.
     *
     * @return array|null ['sira' => int, 'tarih' => 'Y-m-d', 'aciklama' => string, 'borc' => float, 'alacak' => float]
     */
    private function parseRow(string $line, bool $verdimOnce): ?array
    {
        $amount = self::AMOUNT;
        $pattern = '/^(\d{1,5})\s+(\d{4}[.\/-]\d{1,2}[.\/-]\d{1,2}|\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4})\s+(.*)\s+(-|' . $amount . ')\s+(-|' . $amount . ')\s+(-|' . $amount . ')$/u';

        if (!preg_match($pattern, $line, $m)) {
            $pattern = '/^(\d{1,5})\s+(\d{4}[.\/-]\d{1,2}[.\/-]\d{1,2}|\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4})\s+(.*)\s+(-|' . $amount . ')\s+(-|' . $amount . ')$/u';
            if (!preg_match($pattern, $line, $m)) {
                return null;
            }
        }

        $tarih = $this->normalizeDate($m[2]);
        if ($tarih === null) {
            return null;
        }

        $aciklama = trim($m[3], " -\t");
        $tutar1 = $this->toAmount($m[4]);
        $tutar2 = $this->toAmount($m[5]);

        $verdim = $verdimOnce ? $tutar1 : $tutar2;
        $aldim = $verdimOnce ? $tutar2 : $tutar1;

        if ($verdim <= 0 && $aldim <= 0) {
            return null;
        }

        return [
            'sira' => (int) $m[1],
            'tarih' => $tarih,
            'aciklama' => mb_substr($aciklama, 0, 500),
            'borc' => round($aldim, 2),
            'alacak' => round($verdim, 2)
        ];
    }

    private function toAmount(string $value): float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return 0.0;
        }
        return (float) Helper::formattedMoneyToNumber($value);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = str_replace(['/', '.'], '-', trim($value));

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$yil, $ay, $gun] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $value, $m)) {
            [$gun, $ay, $yil] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if (!checkdate($ay, $gun, $yil)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
    }
}
