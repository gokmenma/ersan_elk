<?php
namespace App\Service;

use App\Model\BordroPusulaModel;
use DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Muhasebe bordro Excel'ini kayıtlı bordro snapshot'larıyla salt okunur karşılaştırır. */
final class BordroMuhasebeExcelKarsilastirmaService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    private const ALANLAR = [
        'toplam_gun' => 'Toplam Gün',
        'sgk_matrahi' => 'SGK Matrahı',
        'sgk_isci' => 'SGK İşçi Primi',
        'issizlik_isci' => 'İşsizlik İşçi Primi',
        'gelir_vergisi_matrahi' => 'Gelir Vergisi Matrahı',
        'gelir_vergisi' => 'Gelir Vergisi',
        'ozel_kesinti' => 'Özel Kesinti',
        'damga_vergisi' => 'Damga Vergisi',
        'net_odenen' => 'Net Ödenen / Resmî Banka',
        'normal_gun' => 'Normal Gün',
        'yemek' => 'Yemek',
        'ucretli_izin_gun' => 'Ücretli İzin Günü',
        'rapor_gun' => 'Rapor Günü',
    ];

    public function karsilastir(array $file, int $firmaId, int $donemId): array
    {
        $path = $this->dosyayiDogrula($file);
        [$muhasebe, $meta] = $this->exceliOku($path);
        $sistemRaporu = (new BordroPusulaModel())->rapor($firmaId, $donemId);
        $sistem = $this->sistemSatirlari($sistemRaporu['kayitlar']);

        $sonuclar = [];
        $kullanilanSistem = [];
        foreach ($muhasebe as $excelRow) {
            [$systemKey, $systemRow, $eslesme] = $this->eslesenSistemSatiri($excelRow, $sistem, $kullanilanSistem);
            if ($systemRow === null) {
                $sonuclar[] = $this->sonucSatiri($excelRow, null, 'yalniz_muhasebe', 'Muhasebe Excelinde var, sistem bordrosunda yok.', []);
                continue;
            }
            $kullanilanSistem[$systemKey] = true;
            $farklar = $this->alanFarklari($excelRow['alanlar'], $systemRow['alanlar']);
            $durum = $farklar ? 'farkli' : 'eslesen';
            $aciklama = $farklar ? count($farklar) . ' alanda fark var.' : 'Karşılaştırılan alanlar aynı.';
            if ($eslesme === 'ad') $aciklama .= ' T.C. yerine ad-soyad ile eşleştirildi.';
            $sonuclar[] = $this->sonucSatiri($excelRow, $systemRow, $durum, $aciklama, $farklar, $eslesme);
        }

        foreach ($sistem as $key => $systemRow) {
            if (!isset($kullanilanSistem[$key])) {
                $sonuclar[] = $this->sonucSatiri(null, $systemRow, 'yalniz_sistem', 'Sistem bordrosunda var, muhasebe Excelinde yok.', []);
            }
        }

        usort($sonuclar, static function (array $a, array $b): int {
            $oncelik = ['farkli' => 0, 'yalniz_muhasebe' => 1, 'yalniz_sistem' => 2, 'eslesen' => 3];
            return [$oncelik[$a['durum']] ?? 9, $a['personel']] <=> [$oncelik[$b['durum']] ?? 9, $b['personel']];
        });
        $sayilar = array_fill_keys(['toplam', 'eslesen', 'farkli', 'yalniz_muhasebe', 'yalniz_sistem'], 0);
        $sayilar['toplam'] = count($sonuclar);
        foreach ($sonuclar as $row) $sayilar[$row['durum']]++;

        return [
            'meta' => $meta + ['dosya_adi' => basename((string) ($file['name'] ?? 'muhasebe.xlsx'))],
            'donem' => ['id' => (int) $sistemRaporu['donem']->id, 'adi' => (string) $sistemRaporu['donem']->donem_adi],
            'sayilar' => $sayilar,
            'alanlar' => self::ALANLAR,
            'sonuclar' => $sonuclar,
        ];
    }

    private function dosyayiDogrula(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new DomainException('Geçerli bir Excel dosyası yükleyin.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_FILE_SIZE) {
            throw new DomainException('Excel dosyası boş veya 10 MB sınırını aşıyor.');
        }
        $extension = mb_strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') throw new DomainException('Yalnızca .xlsx dosyası yüklenebilir.');
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'];
        if (!in_array($mime, $allowed, true)) throw new DomainException('Dosya geçerli bir XLSX çalışma kitabı değil.');
        return (string) $file['tmp_name'];
    }

    private function exceliOku(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new DomainException('Excel dosyası okunamadı veya bozuk.');
        }
        $sheet = $book->getSheet(0);
        [$headerRow, $columns] = $this->basliklariBul($sheet);
        $rows = [];
        for ($r = $headerRow + 1, $last = $sheet->getHighestDataRow(); $r <= $last; $r++) {
            $name = trim((string) $sheet->getCell([$columns['ad'], $r])->getFormattedValue());
            $tc = preg_replace('/\D+/', '', (string) $sheet->getCell([$columns['tc'], $r])->getFormattedValue());
            if ($name === '' || mb_stripos($name, 'toplam') !== false) continue;
            if ($tc === '' && !preg_match('/^\d+$/', trim((string) $sheet->getCell([$columns['sira'], $r])->getFormattedValue()))) continue;
            $rows[] = [
                'satir' => $r, 'personel' => $name, 'tc' => $tc, 'normalize_ad' => $this->normalizeAd($name),
                'alanlar' => [
                    'toplam_gun' => $this->gun($sheet, $columns['toplam_gun'], $r),
                    'sgk_matrahi' => $this->para($sheet, $columns['sgk_matrahi'], $r),
                    'sgk_isci' => $this->para($sheet, $columns['sgk_isci'], $r),
                    'issizlik_isci' => $this->para($sheet, $columns['issizlik_isci'], $r),
                    'gelir_vergisi_matrahi' => $this->para($sheet, $columns['gv_matrahi'], $r),
                    'gelir_vergisi' => $this->para($sheet, $columns['gelir_vergisi'], $r),
                    'ozel_kesinti' => $this->para($sheet, $columns['ozel_kesinti'], $r),
                    'damga_vergisi' => $this->para($sheet, $columns['damga'], $r),
                    'net_odenen' => $this->para($sheet, $columns['net'], $r),
                    'normal_gun' => $this->gun($sheet, $columns['normal_gun'], $r),
                    // Muhasebe şablonunda kazanç türünün tutarı başlığın sağındaki sütundadır.
                    'yemek' => $this->para($sheet, $columns['yemek'] + 1, $r),
                    'ucretli_izin_gun' => $this->gun($sheet, $columns['ucretli_izin'], $r),
                    'rapor_gun' => $this->gun($sheet, $columns['rapor'], $r),
                ],
            ];
        }
        $sheetTitle = $sheet->getTitle();
        $book->disconnectWorksheets();
        if (!$rows) throw new DomainException('Excelde karşılaştırılabilir personel satırı bulunamadı.');
        return [$rows, ['sayfa' => $sheetTitle, 'excel_satir_sayisi' => count($rows)]];
    }

    private function basliklariBul(Worksheet $sheet): array
    {
        $aliases = [
            'sira' => ['#'], 'ad' => ['adı soyadı', 'adi soyadi'], 'tc' => ['tckn', 'tc kimlik no'],
            'toplam_gun' => ['t.gün', 't.gun'], 'sgk_matrahi' => ['ssk m.', 'sgk matrahı'],
            'sgk_isci' => ['ssk işçi', 'sgk işçi'], 'issizlik_isci' => ['işs.p.işçi', 'işsizlik işçi'],
            'gv_matrahi' => ['g.v.m', 'gelir vergisi matrahı'], 'gelir_vergisi' => ['gel.ver.', 'gelir vergisi'],
            'ozel_kesinti' => ['öz.kesinti', 'özel kesinti'], 'damga' => ['damga v', 'damga vergisi'],
            'net' => ['n.ödenen', 'net ödenen'], 'normal_gun' => ['normal gün'], 'yemek' => ['yemek'],
            'ucretli_izin' => ['ücretli izin'], 'rapor' => ['rapor'],
        ];
        for ($r = 1; $r <= min(30, $sheet->getHighestDataRow()); $r++) {
            $found = [];
            for ($c = 1; $c <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()); $c++) {
                $value = $this->normalizeBaslik((string) $sheet->getCell([$c, $r])->getFormattedValue());
                foreach ($aliases as $key => $values) if (in_array($value, array_map([$this, 'normalizeBaslik'], $values), true) && !isset($found[$key])) $found[$key] = $c;
            }
            if (count($found) === count($aliases)) return [$r, $found];
        }
        throw new DomainException('Excel başlıkları tanınmadı. Muhasebe bordro şablonunu yükleyin.');
    }

    private function sistemSatirlari(array $kayitlar): array
    {
        $rows = [];
        foreach ($kayitlar as $item) {
            if (!$item['pusula']) continue;
            $row = $item['kayit']; $p = $item['pusula']; $m = $p['detay']['matrahlar'];
            $yemek = 0;
            foreach ($p['resmi']['kalemler'] as $kalem) if ($kalem['kurus'] > 0 && mb_stripos((string) $kalem['etiket'], 'yemek') !== false) $yemek += (int) $kalem['kurus'];
            $key = 'b' . (int) $row->id;
            $rows[$key] = [
                'personel' => (string) $row->adi_soyadi, 'tc' => preg_replace('/\D+/', '', (string) ($row->tc_kimlik_no ?? '')),
                'normalize_ad' => $this->normalizeAd((string) $row->adi_soyadi), 'bordro_id' => (int) $row->id,
                'alanlar' => [
                    'toplam_gun' => (int) ($m['ssk_gunu'] ?? $row->calisan_gun ?? 0),
                    'sgk_matrahi' => $this->kurus($m['sgk_matrahi'] ?? null),
                    'sgk_isci' => $this->kurus($row->sgk_isci ?? null), 'issizlik_isci' => $this->kurus($row->issizlik_isci ?? null),
                    'gelir_vergisi_matrahi' => $this->kurus($m['gelir_vergisi_matrahi'] ?? null),
                    'gelir_vergisi' => $this->kurus($row->gelir_vergisi ?? null),
                    'ozel_kesinti' => (int) $p['ozel_kesinti_kurus'], 'damga_vergisi' => $this->kurus($row->damga_vergisi ?? null),
                    'net_odenen' => (int) $p['resmi']['banka_net_kurus'], 'normal_gun' => (int) ($m['normal_gun'] ?? 0),
                    'yemek' => $yemek, 'ucretli_izin_gun' => (int) ($m['ucretli_izin_gunu'] ?? 0),
                    'rapor_gun' => (int) ($m['rapor_gunu'] ?? 0),
                ],
            ];
        }
        return $rows;
    }

    private function eslesenSistemSatiri(array $excel, array $sistem, array $used): array
    {
        if ($excel['tc'] !== '') foreach ($sistem as $key => $row) if (!isset($used[$key]) && $row['tc'] === $excel['tc']) return [$key, $row, 'tc'];
        $candidates = [];
        foreach ($sistem as $key => $row) if (!isset($used[$key]) && $row['normalize_ad'] === $excel['normalize_ad']) $candidates[$key] = $row;
        if (count($candidates) === 1) { $key = array_key_first($candidates); return [$key, $candidates[$key], 'ad']; }
        return [null, null, null];
    }

    private function alanFarklari(array $excel, array $sistem): array
    {
        $farklar = [];
        foreach (self::ALANLAR as $key => $label) {
            if ($excel[$key] === $sistem[$key]) continue;
            $isMoney = !str_ends_with($key, '_gun') && $key !== 'toplam_gun' && $key !== 'normal_gun';
            $farklar[] = ['alan' => $key, 'etiket' => $label, 'muhasebe' => $excel[$key], 'sistem' => $sistem[$key],
                'fark' => $excel[$key] - $sistem[$key], 'tur' => $isMoney ? 'para' : 'sayi'];
        }
        return $farklar;
    }

    private function sonucSatiri(?array $excel, ?array $system, string $durum, string $aciklama, array $farklar, ?string $eslesme = null): array
    {
        return ['personel' => (string) ($system['personel'] ?? $excel['personel'] ?? ''), 'tc' => $this->maskTc((string) ($system['tc'] ?? $excel['tc'] ?? '')),
            'durum' => $durum, 'aciklama' => $aciklama, 'eslesme' => $eslesme, 'excel_satir' => $excel['satir'] ?? null,
            'bordro_id' => $system['bordro_id'] ?? null, 'fark_sayisi' => count($farklar), 'farklar' => $farklar];
    }

    private function para(Worksheet $sheet, int $column, int $row): int { return $this->kurus($sheet->getCell([$column, $row])->getCalculatedValue()); }
    private function gun(Worksheet $sheet, int $column, int $row): int { return (int) preg_replace('/[^0-9-]+/', '', (string) $sheet->getCell([$column, $row])->getFormattedValue()); }
    private function kurus(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_numeric($value)) return (int) round((float) $value * 100);
        $s = preg_replace('/[^0-9,.-]/u', '', (string) $value);
        if (str_contains($s, ',') && str_contains($s, '.')) $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(',', '.', str_replace('.', '', $s)) : str_replace(',', '', $s);
        elseif (str_contains($s, ',')) $s = str_replace(',', '.', $s);
        return (int) round((float) $s * 100);
    }
    private function normalizeAd(string $value): string
    {
        $value = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
        return strtr($value, ['İ'=>'I','Ş'=>'S','Ğ'=>'G','Ü'=>'U','Ö'=>'O','Ç'=>'C','Â'=>'A','Î'=>'I','Û'=>'U']);
    }
    private function normalizeBaslik(string $value): string
    {
        return str_replace("\u{0307}", '', mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8'));
    }
    private function maskTc(string $value): string { return strlen($value) === 11 ? substr($value, 0, 3) . '*****' . substr($value, -3) : ''; }
}
