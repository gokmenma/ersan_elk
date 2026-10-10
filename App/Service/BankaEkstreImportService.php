<?php

namespace App\Service;

use App\Helper\Helper;
use App\Model\CariModel;
use App\Model\CariHareketleriModel;
use Exception;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Banka Hesap Ekstresi (PDF / Excel / CSV) İçe Aktarma ve Otomatik Cari Eşleştirme Servisi.
 */
class BankaEkstreImportService
{
    private CariModel $cariModel;
    private CariHareketleriModel $cariHareketModel;

    public function __construct()
    {
        $this->cariModel = new CariModel();
        $this->cariHareketModel = new CariHareketleriModel();
    }

    /**
     * Yüklenen dosyayı formatına göre analiz eder ve carilerle eşleştirilmiş hareket listesini döner.
     *
     * @param string $filePath Geçici dosya yolu
     * @param string $originalName Orijinal dosya adı
     * @return array
     * @throws Exception
     */
    public function parseFile(string $filePath, string $originalName): array
    {
        if (!is_readable($filePath)) {
            throw new Exception("Yüklenen dosya sunucuda okunamadı.");
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        $rawRows = [];
        if ($ext === 'pdf') {
            $rawRows = $this->parsePdf($filePath);
        } elseif (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $rawRows = $this->parseExcel($filePath, $ext);
        } else {
            throw new Exception("Desteklenmeyen dosya formatı. Lütfen PDF veya Excel (.xlsx, .xls, .csv) yükleyin.");
        }

        if (empty($rawRows)) {
            throw new Exception("Dosya içinde herhangi bir işlem/hareket satırı tespit edilemedi. Dosya biçimini kontrol ediniz.");
        }

        // Aktif Carileri çek
        $cariler = $this->getActiveCariler();

        // Eşleştirme ve Mükerrer Kontrolü Yap
        $matchedRows = [];
        $toplamCikis = 0.0;
        $toplamGiris = 0.0;
        $eslesenSayisi = 0;

        foreach ($rawRows as $idx => $row) {
            $matchedCari = $this->matchCariWithDescription($row['aciklama'], $cariler);
            
            $cariId = $matchedCari ? (int)$matchedCari['id'] : null;
            $cariAdi = $matchedCari ? $matchedCari['CariAdi'] : null;
            $matchReason = $matchedCari ? $matchedCari['match_reason'] : null;

            if ($cariId !== null) {
                $eslesenSayisi++;
            }

            // Tutar yönü ve hesaplama
            $rawTutar = (float)$row['tutar'];
            $isCikis = $rawTutar < 0;
            $absTutar = round(abs($rawTutar), 2);

            if ($isCikis) {
                // Hesaptan para çıktı -> Firmamız Cari'ye ödeme yaptı -> Cari'de Verdim (Alacak)
                $borc = 0.0;
                $alacak = $absTutar;
                $islemTuru = 'cikis'; // Ödeme / Çıkış (Verdim)
                $toplamCikis += $absTutar;
            } else {
                // Hesaba para girdi -> Cari firmamıza ödeme yaptı -> Cari'de Aldım (Borç)
                $borc = $absTutar;
                $alacak = 0.0;
                $islemTuru = 'giris'; // Tahsilat / Giriş (Aldım)
                $toplamGiris += $absTutar;
            }

            // Mükerrer kontrolü
            $isMukerrer = false;
            if ($cariId !== null) {
                $isMukerrer = $this->checkMukerrer($cariId, $row['tarih'], $borc, $alacak, $row['referans'] ?? null);
            }

            $matchedRows[] = [
                'sira' => $idx + 1,
                'tarih' => $row['tarih'],
                'tarih_formatli' => date('d.m.Y', strtotime($row['tarih'])),
                'aciklama' => $row['aciklama'],
                'tutar' => $absTutar,
                'tutar_raw' => $rawTutar,
                'islem_turu' => $islemTuru, // 'giris' | 'cikis'
                'borc' => $borc,
                'alacak' => $alacak,
                'bakiye' => isset($row['bakiye']) ? (float)$row['bakiye'] : null,
                'referans' => $row['referans'] ?? '',
                'cari_id' => $cariId,
                'cari_adi' => $cariAdi,
                'match_reason' => $matchReason,
                'is_mukerrer' => $isMukerrer,
                'selected' => ($cariId !== null && !$isMukerrer) // Varsayılan seçim durumu
            ];
        }

        return [
            'total_rows' => count($matchedRows),
            'matched_count' => $eslesenSayisi,
            'toplam_cikis' => round($toplamCikis, 2),
            'toplam_giris' => round($toplamGiris, 2),
            'rows' => $matchedRows,
            'cariler' => array_map(function($c) {
                return [
                    'id' => (int)$c['id'],
                    'CariAdi' => $c['CariAdi'],
                    'firma' => $c['firma'] ?? '',
                    'vkn_tckn' => $c['vkn_tckn'] ?? ''
                ];
            }, $cariler)
        ];
    }

    /**
     * PDF Ekstresini satır satır ve blok bazında ayrıştırır.
     */
    private function parsePdf(string $filePath): array
    {
        $outText = '';
        $pdftotextPaths = ['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', 'pdftotext'];
        if (function_exists('shell_exec')) {
            foreach ($pdftotextPaths as $bin) {
                $cmd = escapeshellcmd($bin) . ' -layout ' . escapeshellarg($filePath) . ' - 2>/dev/null';
                $cmdOut = @shell_exec($cmd);
                if (!empty($cmdOut) && trim($cmdOut) !== '') {
                    $outText = $cmdOut;
                    break;
                }
            }
        }

        if (trim($outText) === '') {
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($filePath);
                $outText = $pdf->getText();
            } catch (\Throwable $t) {
                error_log("PDF Parser hatası: " . $t->getMessage());
            }
        }

        if (trim($outText) === '') {
            throw new Exception("PDF içeriği okunamadı. Taranmış (resim) PDF'ler desteklenmemektedir.");
        }

        // Metin ön işleme
        $outText = preg_replace('/(\d{2}[.\/-]\d{2}[.\/-]\d{4})(\S)/u', '$1 $2', $outText);
        $outText = preg_replace('/(\d{4}[.\/-]\d{2}[.\/-]\d{2})(\S)/u', '$1 $2', $outText);
        $outText = preg_replace('/(TL|TRY)([+-]?\d)/u', '$1 $2', $outText);

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $outText));

        // Başlık satırını bul
        $headerIdx = -1;
        foreach ($lines as $i => $line) {
            if (mb_stripos($line, 'İşlem Tarihi') !== false || (mb_stripos($line, 'Tarih') !== false && mb_stripos($line, 'Tutar') !== false)) {
                $headerIdx = $i;
                break;
            }
        }

        $blocks = [];
        $current = null;

        for ($i = ($headerIdx !== -1 ? $headerIdx + 1 : 0); $i < count($lines); $i++) {
            $line = $lines[$i];
            $t = trim($line);
            if ($t === '' || $t === "\x0c" || $t === "\f") {
                continue;
            }
            if ($headerIdx !== -1 && $i <= $headerIdx + 2 && (mb_stripos($t, 'Numarası') !== false || mb_stripos($t, 'Bakiye') !== false)) {
                continue;
            }

            // Satır başında tarih tespiti (DD.MM.YYYY veya YYYY-MM-DD)
            if (preg_match('/^\s*(\d{2}[.\/-]\d{2}[.\/-]\d{4}|\d{4}[.\/-]\d{2}[.\/-]\d{2})\s*(.*)$/u', $line, $dm)) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = [
                    'date' => $this->normalizeDate($dm[1]),
                    'lines' => [$line]
                ];
            } else {
                if ($current !== null) {
                    $current['lines'][] = $line;
                }
            }
        }
        if ($current !== null) {
            $blocks[] = $current;
        }

        $results = [];
        foreach ($blocks as $idx => $b) {
            if (empty($b['date'])) {
                continue;
            }

            $descLines = [];
            $tutar = null;
            $bakiye = null;
            $referans = '';

            foreach ($b['lines'] as $lIdx => $l) {
                $cleanLine = $l;
                if ($lIdx === 0) {
                    $cleanLine = preg_replace('/^\s*(\d{2}[.\/-]\d{2}[.\/-]\d{4}|\d{4}[.\/-]\d{2}[.\/-]\d{2})\s*/u', '', $cleanLine);
                }

                // Satır içindeki tutar desenlerini ara
                if (preg_match_all('/([+-]?\s*\d{1,3}(?:\.\d{3})*,\d{2}|[+-]?\s*\d+,\d{2})\s*(?:TL|TRY)?/u', $cleanLine, $amtMatches, PREG_OFFSET_CAPTURE)) {
                    $firstAmtOffset = $amtMatches[0][0][1];
                    $dPart = trim(substr($cleanLine, 0, $firstAmtOffset));
                    if ($dPart !== '') {
                        $descLines[] = $dPart;
                    }

                    if ($tutar === null && isset($amtMatches[1][0])) {
                        $raw = str_replace([' ', '.'], ['', ''], $amtMatches[1][0][0]);
                        $raw = str_replace(',', '.', $raw);
                        $tutar = (float)$raw;
                    }
                    if ($bakiye === null && isset($amtMatches[1][1])) {
                        $rawB = str_replace([' ', '.'], ['', ''], $amtMatches[1][1][0]);
                        $rawB = str_replace(',', '.', $rawB);
                        $bakiye = (float)$rawB;
                    }

                    $lastMatch = end($amtMatches[0]);
                    $afterOffset = $lastMatch[1] + strlen($lastMatch[0]);
                    $afterPart = trim(substr($cleanLine, $afterOffset));
                    if ($afterPart !== '') {
                        if (preg_match('/([A-Z0-9]{3,20})\s*$/u', $afterPart, $rm)) {
                            $referans = $rm[1];
                        }
                    }
                } else {
                    $t = trim($cleanLine);
                    if ($t !== '' && $t !== 'TL' && $t !== 'TRY') {
                        if (preg_match('/^[A-Z0-9]{3,20}$/u', $t) && $referans === '') {
                            $referans = $t;
                        } else {
                            $descLines[] = $t;
                        }
                    }
                }
            }

            if ($tutar === null) {
                continue;
            }

            $fullDesc = implode(' ', $descLines);
            $fullDesc = preg_replace('/\b(TL|TRY)\b/u', '', $fullDesc);
            $fullDesc = preg_replace('/\s+/', ' ', $fullDesc);
            $fullDesc = trim(str_replace(["\f", "\x0c"], '', $fullDesc));

            $results[] = [
                'tarih' => $b['date'],
                'aciklama' => $fullDesc,
                'tutar' => round($tutar, 2),
                'bakiye' => $bakiye !== null ? round($bakiye, 2) : null,
                'referans' => $referans
            ];
        }

        return $results;
    }

    /**
     * Excel (.xlsx, .xls, .csv) Ekstresini ayrıştırır.
     */
    private function parseExcel(string $filePath, string $ext): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return [];
        }

        // Başlık satırını ve sütun indekslerini tespit et
        $headerRowIdx = null;
        $colDate = null;
        $colDesc = null;
        $colAmount = null;
        $colDebit = null;  // Borç / Çıkış
        $colCredit = null; // Alacak / Giriş
        $colRef = null;
        $colBalance = null;

        foreach ($rows as $rIdx => $row) {
            foreach ($row as $colKey => $cellVal) {
                if ($cellVal === null) continue;
                $val = mb_strtolower(trim((string)$cellVal), 'UTF-8');
                
                if ($colDate === null && in_array($val, ['tarih', 'işlem tarihi', 'islem tarihi', 'valör', 'valor', 'date'], true)) {
                    $colDate = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colDesc === null && in_array($val, ['açıklama', 'aciklama', 'hareket açıklaması', 'işlem açıklaması', 'detay', 'açıklamalar', 'description'], true)) {
                    $colDesc = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colAmount === null && in_array($val, ['tutar', 'işlem tutarı', 'islem tutari', 'tutar (tl)', 'tutar(tl)', 'net tutar', 'amount'], true)) {
                    $colAmount = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colDebit === null && in_array($val, ['borç', 'borc', 'çıkış', 'cikis', 'ödenen', 'borç (tl)', 'borc (tl)', 'debit'], true)) {
                    $colDebit = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colCredit === null && in_array($val, ['alacak', 'giriş', 'giris', 'tahsilat', 'alacak (tl)', 'credit'], true)) {
                    $colCredit = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colRef === null && in_array($val, ['referans', 'referans no', 'dekont no', 'fiş no', 'fis no', 'işlem no', 'belge no', 'ref'], true)) {
                    $colRef = $colKey;
                    $headerRowIdx = $rIdx;
                }
                if ($colBalance === null && in_array($val, ['bakiye', 'bakiye (tl)', 'kalan bakiye', 'balance'], true)) {
                    $colBalance = $colKey;
                }
            }
            if ($colDate !== null && ($colAmount !== null || ($colDebit !== null && $colCredit !== null))) {
                break;
            }
        }

        if ($headerRowIdx === null || $colDate === null) {
            $headerRowIdx = 1;
            $keys = array_keys(reset($rows));
            $colDate = $keys[0] ?? 'A';
            $colDesc = $keys[1] ?? 'B';
            $colAmount = $keys[2] ?? 'C';
        }

        $results = [];
        foreach ($rows as $rIdx => $row) {
            if ($rIdx <= $headerRowIdx) {
                continue;
            }

            $rawDate = trim((string)($row[$colDate] ?? ''));
            if ($rawDate === '') {
                continue;
            }

            $date = $this->normalizeDate($rawDate);
            if ($date === null) {
                continue;
            }

            $desc = $colDesc !== null ? trim((string)($row[$colDesc] ?? '')) : '';
            $ref = $colRef !== null ? trim((string)($row[$colRef] ?? '')) : '';
            $balance = $colBalance !== null ? $this->toAmount($row[$colBalance] ?? '') : null;

            $tutar = 0.0;
            if ($colAmount !== null) {
                $tutar = $this->toAmount($row[$colAmount] ?? '');
            } elseif ($colDebit !== null && $colCredit !== null) {
                $debit = $this->toAmount($row[$colDebit] ?? '');
                $credit = $this->toAmount($row[$colCredit] ?? '');
                if ($credit > 0) {
                    $tutar = $credit;
                } elseif ($debit > 0) {
                    $tutar = -$debit;
                }
            }

            if ($tutar == 0 && $desc === '') {
                continue;
            }

            $results[] = [
                'tarih' => $date,
                'aciklama' => $desc,
                'tutar' => round($tutar, 2),
                'bakiye' => $balance !== null ? round($balance, 2) : null,
                'referans' => $ref
            ];
        }

        return $results;
    }

    /**
     * Aktif cari hesap listesini veritabanından çeker.
     */
    private function getActiveCariler(): array
    {
        $db = $this->cariModel->getDb();
        $stmt = $db->query("SELECT id, CariAdi, firma, vkn_tckn, Telefon FROM cari WHERE silinme_tarihi IS NULL AND Aktif = 1 ORDER BY CariAdi ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Açıklama metnini carilerle akıllı olarak eşleştirir.
     */
    private function matchCariWithDescription(string $description, array $cariler): ?array
    {
        if (trim($description) === '') {
            return null;
        }

        $normDesc = $this->normalizeText($description);
        $bestMatch = null;
        $highestScore = 0;
        $bestReason = '';

        foreach ($cariler as $c) {
            $score = 0;
            $reason = '';

            // 1. VKN / TCKN Eşleşmesi (En yüksek öncelik)
            if (!empty($c['vkn_tckn']) && strlen($c['vkn_tckn']) >= 10) {
                if (strpos($description, $c['vkn_tckn']) !== false) {
                    return array_merge($c, [
                        'match_reason' => 'VKN/TCKN Eşleşti (' . $c['vkn_tckn'] . ')',
                        'score' => 100
                    ]);
                }
            }

            // 2. Cari Adı Birebir/Kısmi Eşleşmesi
            $normCariAdi = $this->normalizeText($c['CariAdi']);
            if ($normCariAdi !== '' && mb_strlen($normCariAdi) >= 3) {
                if (mb_strpos($normDesc, $normCariAdi) !== false) {
                    $score = max($score, 80 + mb_strlen($normCariAdi));
                    $reason = 'Cari Adı Eşleşti (' . $c['CariAdi'] . ')';
                }
            }

            // 3. Firma Ünvanı Eşleşmesi
            if (!empty($c['firma'])) {
                $normFirma = $this->normalizeText($c['firma']);
                if ($normFirma !== '' && mb_strlen($normFirma) >= 3) {
                    if (mb_strpos($normDesc, $normFirma) !== false) {
                        $score = max($score, 90 + mb_strlen($normFirma));
                        $reason = 'Firma Ünvanı Eşleşti (' . $c['firma'] . ')';
                    } else {
                        // Firma içindeki anlamlı anahtar kelimeleri karşılaştır
                        $tokens = explode(' ', $normFirma);
                        $stopWords = ['SAN', 'TIC', 'LTD', 'STI', 'AS', 'VE', 'ANONIM', 'SIRKETI', 'GENEL', 'MUDURLUGU', 'TICARET', 'SANAYI', 'LIMITED', 'ARAC', 'KIRALAMA', 'OTO', 'MOTOR'];
                        foreach ($tokens as $tok) {
                            if (mb_strlen($tok) >= 4 && !in_array($tok, $stopWords, true)) {
                                if (mb_strpos($normDesc, $tok) !== false) {
                                    $s = 60 + mb_strlen($tok);
                                    if ($s > $score) {
                                        $score = $s;
                                        $reason = 'Anahtar Kelime Eşleşti (' . $tok . ')';
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($score > $highestScore && $score >= 60) {
                $highestScore = $score;
                $bestMatch = $c;
                $bestReason = $reason;
            }
        }

        if ($bestMatch !== null) {
            return array_merge($bestMatch, [
                'match_reason' => $bestReason,
                'score' => $highestScore
            ]);
        }

        return null;
    }

    /**
     * Cari ve tutar için daha önce sisteme kaydedilmiş hareket olup olmadığını sorgular.
     */
    private function checkMukerrer(int $cariId, string $tarih, float $borc, float $alacak, ?string $belgeNo = null): bool
    {
        $db = $this->cariHareketModel->getDb();
        $dateOnly = substr($tarih, 0, 10);

        if (!empty($belgeNo) && strlen($belgeNo) >= 4) {
            $stmt = $db->prepare("SELECT id FROM cari_hareketleri WHERE cari_id = :cari_id AND belge_no = :belge_no AND silinme_tarihi IS NULL LIMIT 1");
            $stmt->execute(['cari_id' => $cariId, 'belge_no' => $belgeNo]);
            if ($stmt->fetch()) {
                return true;
            }
        }

        $stmt = $db->prepare("SELECT id FROM cari_hareketleri WHERE cari_id = :cari_id AND DATE(islem_tarihi) = :tarih AND borc = :borc AND alacak = :alacak AND silinme_tarihi IS NULL LIMIT 1");
        $stmt->execute([
            'cari_id' => $cariId,
            'tarih' => $dateOnly,
            'borc' => $borc,
            'alacak' => $alacak
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Türkçe karakterleri temizleyip normalize eder.
     */
    private function normalizeText(string $str): string
    {
        $str = mb_strtoupper($str, 'UTF-8');
        $tr = ['İ' => 'I', 'I' => 'I', 'Ş' => 'S', 'Ğ' => 'G', 'Ü' => 'U', 'Ö' => 'O', 'Ç' => 'C'];
        $str = strtr($str, $tr);
        $str = preg_replace('/[^A-Z0-9\s]/', ' ', $str);
        return preg_replace('/\s+/', ' ', trim($str));
    }

    /**
     * Para stringini float değere dönüştürür.
     */
    private function toAmount($value): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        $value = trim((string)$value);
        if ($value === '' || $value === '-') {
            return 0.0;
        }
        return (float)Helper::formattedMoneyToNumber($value);
    }

    /**
     * Tarih stringini 'Y-m-d' formatına dönüştürür.
     */
    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float)$value);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                // Hata durumunda devam et
            }
        }

        $value = trim((string)$value);
        $value = str_replace(['/', '.'], '-', $value);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            [$yil, $ay, $gun] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})/', $value, $m)) {
            [$gun, $ay, $yil] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } else {
            return null;
        }

        if (!checkdate($ay, $gun, $yil)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
    }
}
