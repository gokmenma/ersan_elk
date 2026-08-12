<?php

namespace App\Service;

use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class OkumaExcelParserService
{
    const BASLIK_ARAMA_SATIRI = 30;

    private $sutunHaritasi = [
        'okuma_zamani' => ['OKUMATARIHI', 'OKUMATARIH', 'OKUMASAATI', 'TARIH', 'OKUMAZAMANI'],
        'ekip_kodu' => ['USERID', 'KULLANICIID', 'KULLANICIKODU', 'OKUYUCUNO', 'OKUYUCUKODU', 'EKIPKODU'],
        'ekip_adi' => ['KULLANICIADI', 'KULLANICI', 'EKIPADI', 'EKIP', 'OKUYUCUADI'],
        'bolge' => ['BOLGE', 'ILCE'],
        'defter' => ['DEFTER', 'DEFTERNO'],
        'sayfa' => ['SAYFA', 'SAYFANO'],
        'mahalle' => ['MAHALLESI', 'MAHALLE'],
        'abone_no' => ['ABONENO', 'ABONE'],
        'sayac_durum' => ['SAYACDURUMU', 'SAYACDURUM'],
        'sira_no' => ['SIRANO', 'SIRA'],
        'adi' => ['ADI', 'AD'],
        'soyadi' => ['SOYADI', 'SOYAD'],
    ];

    public static function basligiNormalize($deger)
    {
        $deger = trim((string) $deger);
        if ($deger === '') {
            return '';
        }

        $deger = str_replace(
            ['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'],
            ['I', 'I', 'S', 'S', 'G', 'G', 'U', 'U', 'O', 'O', 'C', 'C'],
            $deger
        );
        $deger = mb_strtoupper($deger, 'UTF-8');

        return preg_replace('/[^A-Z0-9]/u', '', $deger);
    }

    public function ayristir($dosyaYolu, $orijinalAd)
    {
        $sonuc = [
            'satirlar' => [],
            'atlanan_tarih' => 0,
            'toplam_satir' => 0,
            'bulunan_sutunlar' => [],
            'hata' => null,
        ];

        try {
            $reader = IOFactory::createReaderForFile($dosyaYolu);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($dosyaYolu);
        } catch (Exception $e) {
            $sonuc['hata'] = 'Dosya okunamadı. Geçerli bir Excel dosyası olduğundan emin olun.';
            return $sonuc;
        }

        $sayfa = $spreadsheet->getActiveSheet();
        $satirlar = $sayfa->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($satirlar)) {
            $sonuc['hata'] = 'Dosya boş görünüyor.';
            return $sonuc;
        }

        $baslik = $this->baslikSatiriniBul($satirlar);

        if ($baslik === null) {
            $sonuc['hata'] = 'Okuma tarihi sütunu bulunamadı. İlk '
                . self::BASLIK_ARAMA_SATIRI . ' satırda başlık satırı tespit edilemedi.';
            return $sonuc;
        }

        $baslikIndex = $baslik['index'];
        $sutunlar = $baslik['sutunlar'];
        $sonuc['bulunan_sutunlar'] = array_keys($sutunlar);

        $toplam = count($satirlar);
        for ($i = $baslikIndex + 1; $i < $toplam; $i++) {
            $satir = $satirlar[$i];

            if ($this->satirBosMu($satir)) {
                continue;
            }

            $sonuc['toplam_satir']++;

            $zaman = $this->tarihCoz($this->hucre($satir, $sutunlar, 'okuma_zamani'));
            if ($zaman === null) {
                $sonuc['atlanan_tarih']++;
                continue;
            }

            $adi = trim((string) $this->hucre($satir, $sutunlar, 'adi'));
            $soyadi = trim((string) $this->hucre($satir, $sutunlar, 'soyadi'));
            $adSoyad = trim($adi . ' ' . $soyadi);

            $kayit = [
                'ekip_kodu' => $this->ekipKodu($this->hucre($satir, $sutunlar, 'ekip_kodu')),
                'ekip_adi' => $this->metin($this->hucre($satir, $sutunlar, 'ekip_adi'), 255),
                'bolge' => $this->metin($this->hucre($satir, $sutunlar, 'bolge'), 255),
                'defter' => $this->metin($this->hucre($satir, $sutunlar, 'defter'), 50),
                'sayfa' => $this->metin($this->hucre($satir, $sutunlar, 'sayfa'), 50),
                'sira_no' => $this->metin($this->hucre($satir, $sutunlar, 'sira_no'), 50),
                'mahalle' => $this->metin($this->hucre($satir, $sutunlar, 'mahalle'), 255),
                'abone_no' => $this->metin($this->hucre($satir, $sutunlar, 'abone_no'), 50),
                'abone_adsoyad' => $this->metin($adSoyad, 255),
                'sayac_durum' => $this->metin($this->hucre($satir, $sutunlar, 'sayac_durum'), 255),
                'okuma_zamani' => $zaman->format('Y-m-d H:i:s'),
                'tarih' => $zaman->format('Y-m-d'),
                'ekip_kodu_id' => null,
                'personel_id' => null,
            ];

            if ($kayit['ekip_kodu'] === '' && $kayit['ekip_adi'] === '') {
                continue;
            }

            $kayit['satir_hash'] = md5(implode('|', [
                $kayit['ekip_kodu'],
                $zaman->format('Y-m-d H:i'),
                $kayit['abone_no'],
                $kayit['defter'],
                $kayit['sayfa'],
                $kayit['sira_no'],
            ]));

            $sonuc['satirlar'][] = $kayit;
        }

        if (empty($sonuc['satirlar']) && $sonuc['atlanan_tarih'] === 0) {
            $sonuc['hata'] = 'Dosyada işlenebilir okuma satırı bulunamadı.';
        }

        return $sonuc;
    }

    private function baslikSatiriniBul(array $satirlar)
    {
        $sinir = min(self::BASLIK_ARAMA_SATIRI, count($satirlar));

        for ($i = 0; $i < $sinir; $i++) {
            $sutunlar = $this->sutunlariEslestir($satirlar[$i]);

            if (isset($sutunlar['okuma_zamani'])) {
                return ['index' => $i, 'sutunlar' => $sutunlar];
            }
        }

        return null;
    }

    private function sutunlariEslestir($baslikSatiri)
    {
        if (!is_array($baslikSatiri)) {
            return [];
        }

        $bulunan = [];

        foreach ($baslikSatiri as $index => $hucre) {
            $normal = self::basligiNormalize($hucre);
            if ($normal === '') {
                continue;
            }

            foreach ($this->sutunHaritasi as $alan => $adaylar) {
                if (isset($bulunan[$alan])) {
                    continue;
                }
                if (in_array($normal, $adaylar, true)) {
                    $bulunan[$alan] = $index;
                }
            }
        }

        return $bulunan;
    }

    private function ekipKodu($deger)
    {
        $deger = $this->metin($deger, 50);

        if ($deger === '') {
            return '';
        }

        if (ctype_digit($deger) && strlen($deger) > 8) {
            return '';
        }

        return mb_substr($deger, 0, 50, 'UTF-8');
    }

    private function metin($deger, $uzunluk)
    {
        $deger = trim((string) $deger);
        if ($deger === '') {
            return '';
        }

        $deger = preg_replace('/\s+/u', ' ', $deger);

        return mb_substr($deger, 0, $uzunluk, 'UTF-8');
    }

    private function hucre(array $satir, array $sutunlar, $alan)
    {
        if (!isset($sutunlar[$alan])) {
            return null;
        }
        return $satir[$sutunlar[$alan]] ?? null;
    }

    private function satirBosMu($satir)
    {
        if (!is_array($satir)) {
            return true;
        }

        foreach ($satir as $hucre) {
            if (trim((string) $hucre) !== '') {
                return false;
            }
        }

        return true;
    }

    public function tarihCoz($deger)
    {
        if ($deger === null || $deger === '') {
            return null;
        }

        if ($deger instanceof \DateTimeInterface) {
            return \DateTime::createFromFormat('U', (string) $deger->getTimestamp()) ?: null;
        }

        if (is_numeric($deger)) {
            $sayi = (float) $deger;
            if ($sayi > 1 && $sayi < 100000) {
                try {
                    $nesne = ExcelDate::excelToDateTimeObject($sayi);
                    return new \DateTime($nesne->format('Y-m-d H:i:s'));
                } catch (Exception $e) {
                    return null;
                }
            }
            return null;
        }

        $metin = trim((string) $deger);
        $metin = preg_replace('/\s+/', ' ', $metin);

        $bicimler = [
            'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y',
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
            'Y/m/d H:i:s', 'Y/m/d H:i',
        ];

        foreach ($bicimler as $bicim) {
            $nesne = \DateTime::createFromFormat($bicim, $metin);
            if ($nesne !== false) {
                $hatalar = \DateTime::getLastErrors();
                if (empty($hatalar['warning_count']) && empty($hatalar['error_count'])) {
                    if (strpos($bicim, 'H') === false) {
                        $nesne->setTime(0, 0, 0);
                    }
                    return $nesne;
                }
            }
        }

        return null;
    }

    public function ekipleriEslestir(array &$satirlar, array $ekipTanimlari)
    {
        $numarayaGore = [];

        foreach ($ekipTanimlari as $ekip) {
            $no = \App\Helper\EkipHelper::extractTeamNo((string) $ekip->tur_adi);
            if ($no > 0) {
                $numarayaGore[$no] = $ekip;
            }
        }

        foreach ($satirlar as &$satir) {
            $no = 0;

            if ($satir['ekip_adi'] !== '') {
                $no = \App\Helper\EkipHelper::extractTeamNo($satir['ekip_adi']);
            }

            if ($no === 0 && $satir['ekip_kodu'] !== '') {
                $kod = preg_replace('/\D/', '', $satir['ekip_kodu']);
                if ($kod !== '' && strlen($kod) >= 3) {
                    $no = (int) substr($kod, -3);
                }
            }

            if ($no > 0 && isset($numarayaGore[$no])) {
                $satir['ekip_kodu_id'] = (int) $numarayaGore[$no]->id;
            }
        }
        unset($satir);
    }
}
