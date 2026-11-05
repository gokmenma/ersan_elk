<?php

namespace App\Service;

use App\Model\CariModel;
use App\Model\EInvoiceModel;
use Exception;

final class ManuelFaturaService
{
    public const BELGE_TURLERI = [
        'KAGIT' => 'Kâğıt / PDF Fatura',
        'EFATURA' => 'e-Fatura (PDF çıktısı)',
        'EARSIV' => 'e-Arşiv Fatura',
        'SMM' => 'Serbest Meslek Makbuzu',
        'GIDER_PUSULASI' => 'Gider Pusulası',
        'MAKBUZ' => 'Tahsilat / Ödeme Makbuzu',
    ];

    public const BIRIMLER = [
        'C62' => 'Adet',
        'KGM' => 'Kilogram',
        'LTR' => 'Litre',
        'MTR' => 'Metre',
        'MTK' => 'Metrekare',
        'MTQ' => 'Metreküp',
        'TNE' => 'Ton',
        'HUR' => 'Saat',
        'DAY' => 'Gün',
        'MON' => 'Ay',
        'BX' => 'Kutu',
        'PA' => 'Paket',
        'SET' => 'Set',
    ];

    private const STOPAJLI_TURLER = ['SMM', 'GIDER_PUSULASI'];
    private const KDVSIZ_TURLER = ['GIDER_PUSULASI', 'MAKBUZ'];
    private const TOLERANS = 0.05;

    public function normalize(array $girdi): array
    {
        $tur = strtoupper(trim((string) ($girdi['belge_turu'] ?? '')));
        if (!isset(self::BELGE_TURLERI[$tur])) {
            $tur = 'KAGIT';
        }

        $header = [
            'belge_turu' => $tur,
            'fatura_profili' => $this->profil((string) ($girdi['fatura_profili'] ?? '')),
            'fatura_no' => $this->metin($girdi['fatura_no'] ?? '', 16),
            'fatura_tarihi' => $this->tarih((string) ($girdi['fatura_tarihi'] ?? '')),
            'ettn' => $this->ettn((string) ($girdi['ettn'] ?? '')),
            'siparis_no' => $this->metin($girdi['siparis_no'] ?? '', 50),
            'para_birimi' => 'TRY',
            'alici_unvan' => $this->metin($girdi['alici_unvan'] ?? '', 255),
            'alici_vkn_tckn' => preg_replace('/\D/', '', (string) ($girdi['alici_vkn_tckn'] ?? '')),
            'alici_vergi_dairesi' => $this->metin($girdi['alici_vergi_dairesi'] ?? '', 100),
            'alici_adres' => $this->metin($girdi['alici_adres'] ?? '', 1000),
            'alici_il' => $this->metin($girdi['alici_il'] ?? '', 50),
            'alici_ilce' => $this->metin($girdi['alici_ilce'] ?? '', 50),
            'notlar' => $this->metin($girdi['notlar'] ?? '', 5000),
            'stopaj_orani' => in_array($tur, self::STOPAJLI_TURLER, true) ? $this->oran($girdi['stopaj_orani'] ?? null) : null,
        ];

        $lines = [];
        foreach ((array) ($girdi['satirlar'] ?? []) as $satir) {
            if (!is_array($satir)) {
                continue;
            }
            $ad = $this->metin($satir['urun_hizmet_adi'] ?? '', 255);
            $fiyat = $this->sayi($satir['birim_fiyat'] ?? null);
            if ($ad === '' && $fiyat === null) {
                continue;
            }
            $birim = strtoupper(trim((string) ($satir['birim'] ?? '')));
            $lines[] = [
                'urun_hizmet_adi' => $ad !== '' ? $ad : 'Hizmet bedeli',
                'miktar' => $this->sayi($satir['miktar'] ?? null) ?? 1.0,
                'birim' => isset(self::BIRIMLER[$birim]) ? $birim : 'C62',
                'birim_fiyat' => $fiyat ?? 0.0,
                'iskonto_orani' => $this->oran($satir['iskonto_orani'] ?? null) ?? 0.0,
                'kdv_orani' => in_array($tur, self::KDVSIZ_TURLER, true) ? 0.0 : ($this->oran($satir['kdv_orani'] ?? null) ?? 0.0),
            ];
        }

        return ['header' => $header, 'lines' => $lines];
    }

    public function hesapla(array $header, array $lines): array
    {
        $calcLines = array_map(static fn(array $line): array => [
            'urun_hizmet_adi' => $line['urun_hizmet_adi'],
            'birim' => $line['birim'],
            'miktar' => number_format((float) $line['miktar'], 4, '.', ''),
            'birim_fiyat' => number_format((float) $line['birim_fiyat'], 4, '.', ''),
            'iskonto_orani' => number_format((float) $line['iskonto_orani'], 2, '.', ''),
            'kdv_orani' => number_format((float) $line['kdv_orani'], 2, '.', ''),
            'tevkifat_orani' => '0',
        ], $lines);

        $calculated = (new InvoiceCalculationService())->calculate($calcLines);
        $totals = $calculated['header'];
        $matrah = (float) $totals['kdv_matrahi'];
        $stopajOrani = $header['stopaj_orani'];
        $stopaj = $stopajOrani !== null ? round($matrah * $stopajOrani / 100, 2) : 0.0;
        $odenecek = round($matrah + (float) $totals['hesaplanan_kdv'] - $stopaj, 2);

        foreach ($calculated['lines'] as &$line) {
            unset($line['_ubl_kdv_tutari']);
        }
        unset($line);

        return [
            'lines' => $calculated['lines'],
            'totals' => [
                'satir_toplami' => (float) $totals['satir_toplami'],
                'iskonto_toplami' => (float) $totals['iskonto_toplami'],
                'kdv_matrahi' => $matrah,
                'hesaplanan_kdv' => (float) $totals['hesaplanan_kdv'],
                'tevkifat_tutari' => 0.0,
                'stopaj_orani' => $stopajOrani,
                'stopaj_tutari' => $stopaj,
                'odenecek_tutar' => $odenecek,
            ],
        ];
    }

    public function dogrula(array $header, array $lines): void
    {
        if ($header['alici_unvan'] === '') {
            throw new Exception('Satıcı / düzenleyen ünvanı zorunludur.');
        }
        if ($header['fatura_tarihi'] === '') {
            throw new Exception('Geçerli bir belge tarihi giriniz.');
        }
        if ($header['fatura_tarihi'] > date('Y-m-d')) {
            throw new Exception('Belge tarihi bugünden ileri olamaz.');
        }
        $vkn = $header['alici_vkn_tckn'];
        if ($vkn !== '' && !in_array(strlen($vkn), [10, 11], true)) {
            throw new Exception('VKN 10, TCKN 11 haneli olmalıdır.');
        }
        if ($header['belge_turu'] !== 'MAKBUZ' && $vkn === '') {
            throw new Exception('Fatura ve makbuzlarda satıcının VKN/TCKN bilgisi zorunludur.');
        }
        if ($lines === []) {
            throw new Exception('En az bir kalem giriniz.');
        }
        foreach ($lines as $sira => $line) {
            if ((float) $line['miktar'] <= 0 || (float) $line['birim_fiyat'] <= 0) {
                throw new Exception(($sira + 1) . '. kalemde miktar ve birim fiyat sıfırdan büyük olmalıdır.');
            }
        }
    }

    public function karsilastir(array $belgeToplam, array $totals): array
    {
        $uyarilar = [];
        $alanlar = [
            'mal_hizmet_toplami' => ['kdv_matrahi', 'Mal/hizmet toplamı'],
            'kdv_toplami' => ['hesaplanan_kdv', 'KDV toplamı'],
            'stopaj_tutari' => ['stopaj_tutari', 'Stopaj'],
            'odenecek_tutar' => ['odenecek_tutar', 'Ödenecek tutar'],
        ];
        foreach ($alanlar as $belgeAlani => [$hesapAlani, $etiket]) {
            $belgede = $belgeToplam[$belgeAlani] ?? null;
            if ($belgede === null) {
                continue;
            }
            $hesap = (float) $totals[$hesapAlani];
            if (abs($hesap - (float) $belgede) > self::TOLERANS) {
                $uyarilar[] = sprintf(
                    '%s belgede %s ₺, kalemlerden hesaplanan %s ₺. Kalemleri kontrol ediniz.',
                    $etiket,
                    number_format((float) $belgede, 2, ',', '.'),
                    number_format($hesap, 2, ',', '.')
                );
            }
        }
        return $uyarilar;
    }

    public function cariOnizleme(array $header): ?array
    {
        if ($header['alici_unvan'] === '' && $header['alici_vkn_tckn'] === '') {
            return null;
        }
        $cari = (new CariModel())->findMatchingCari($header['alici_vkn_tckn'], $header['alici_unvan']);
        if (!$cari) {
            return null;
        }
        $ad = trim((string) ($cari->firma ?? '')) !== '' ? (string) $cari->firma : (string) ($cari->CariAdi ?? '');
        return ['ad' => $ad, 'vkn_tckn' => (string) ($cari->vkn_tckn ?? '')];
    }

    public function mukerrer(int $firmId, array $header, float $odenecek, ?int $haricId = null): ?array
    {
        $kayit = (new EInvoiceModel())->findIncomingDuplicate(
            $firmId,
            $header['ettn'],
            $header['alici_vkn_tckn'],
            $header['fatura_no'],
            $header['fatura_tarihi'],
            $odenecek,
            $haricId
        );
        if (!$kayit) {
            return null;
        }
        return [
            'fatura_no' => (string) ($kayit['fatura_no'] ?? ''),
            'fatura_tarihi' => !empty($kayit['fatura_tarihi']) ? date('d.m.Y', strtotime($kayit['fatura_tarihi'])) : '',
            'unvan' => (string) ($kayit['alici_unvan'] ?? ''),
            'tutar' => number_format((float) ($kayit['odenecek_tutar'] ?? 0), 2, ',', '.'),
            'kaynak' => (string) ($kayit['kaynak'] ?? 'ENTEGRATOR'),
        ];
    }

    public function sayi(mixed $deger): ?float
    {
        if ($deger === null || $deger === '' || is_array($deger) || is_bool($deger)) {
            return null;
        }
        if (is_int($deger) || is_float($deger)) {
            return $deger >= 0 ? round((float) $deger, 4) : null;
        }
        $temiz = (string) preg_replace('/[^\d.,]/u', '', (string) $deger);
        if ($temiz === '') {
            return null;
        }
        $sonVirgul = strrpos($temiz, ',');
        $sonNokta = strrpos($temiz, '.');
        if ($sonVirgul !== false && $sonNokta !== false) {
            $ondalik = $sonVirgul > $sonNokta ? ',' : '.';
            $binlik = $ondalik === ',' ? '.' : ',';
            $temiz = str_replace($binlik, '', $temiz);
            $temiz = str_replace(',', '.', $temiz);
        } elseif ($sonVirgul !== false) {
            $temiz = substr_count($temiz, ',') > 1 ? str_replace(',', '', $temiz) : str_replace(',', '.', $temiz);
        } elseif ($sonNokta !== false && substr_count($temiz, '.') > 1) {
            $temiz = str_replace('.', '', $temiz);
        }
        return is_numeric($temiz) ? round((float) $temiz, 4) : null;
    }

    public function tarih(string $deger): string
    {
        $deger = trim($deger);
        if ($deger === '') {
            return '';
        }
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $bicim) {
            $tarih = \DateTime::createFromFormat('!' . $bicim, $deger);
            if ($tarih && $tarih->format($bicim) === $deger) {
                return $tarih->format('Y-m-d');
            }
        }
        return '';
    }

    private function oran(mixed $deger): ?float
    {
        $sayi = $this->sayi($deger);
        if ($sayi === null || $sayi > 100) {
            return null;
        }
        return round($sayi, 2);
    }

    private function ettn(string $deger): string
    {
        $deger = strtolower(trim($deger));
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $deger) === 1 ? $deger : '';
    }

    private function profil(string $deger): string
    {
        $deger = strtoupper(trim($deger));
        return in_array($deger, ['TICARIFATURA', 'TEMELFATURA', 'EARSIVFATURA'], true) ? $deger : 'TEMELFATURA';
    }

    private function metin(mixed $deger, int $uzunluk): string
    {
        if (!is_scalar($deger)) {
            return '';
        }
        $deger = trim((string) preg_replace('/[ \t]+/u', ' ', (string) $deger));
        return mb_substr($deger, 0, $uzunluk, 'UTF-8');
    }
}
