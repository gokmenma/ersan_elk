<?php

namespace App\Service;

use DOMDocument;
use DOMXPath;
use ZipArchive;

class SozlesmeSablonService
{
    public const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public const MAKS_BOYUT = 5242880;

    public static function alanTanimlari(): array
    {
        return [
            'AD_SOYAD' => 'Personelin adı soyadı',
            'BABA_ADI' => 'Baba adı',
            'ANNE_ADI' => 'Anne adı',
            'DOGUM_YERI' => 'Doğum yeri (İl / İlçe)',
            'DOGUM_YILI' => 'Doğum yılı',
            'DOGUM_TARIHI' => 'Doğum tarihi (GG.AA.YYYY)',
            'DOGUM_YERI_YILI' => 'Doğum yeri ve yılı (İL / İLÇE - YIL)',
            'ADRES' => 'İkamet adresi',
            'TC_KIMLIK_NO' => 'T.C. kimlik numarası',
            'ISE_BASLAMA_TARIHI' => 'İşe başlama tarihi (GG.AA.YYYY)',
            'DEPARTMAN' => 'Departman',
            'GOREV' => 'Görev',
            'CEP_TELEFONU' => 'Cep telefonu',
            'BUGUN' => 'Sözleşmenin indirildiği tarih (GG.AA.YYYY)',
        ];
    }

    public static function degerler(object $p): array
    {
        $tarih = static function ($t): string {
            if (empty($t) || $t === '0000-00-00') {
                return '';
            }
            $ts = strtotime((string) $t);
            return $ts ? date('d.m.Y', $ts) : '';
        };
        $buyuk = static fn($s): string => mb_strtoupper(strtr(trim((string) $s), ['i' => 'İ', 'ı' => 'I']), 'UTF-8');

        $yer = $buyuk(implode(' / ', array_filter([trim((string) ($p->dogum_yeri_il ?? '')), trim((string) ($p->dogum_yeri_ilce ?? ''))])));
        $yil = (!empty($p->dogum_tarihi) && strtotime((string) $p->dogum_tarihi)) ? date('Y', strtotime((string) $p->dogum_tarihi)) : '';

        return [
            'AD_SOYAD' => $buyuk($p->adi_soyadi ?? ''),
            'BABA_ADI' => $buyuk($p->baba_adi ?? ''),
            'ANNE_ADI' => $buyuk($p->anne_adi ?? ''),
            'DOGUM_YERI' => $yer,
            'DOGUM_YILI' => $yil,
            'DOGUM_TARIHI' => $tarih($p->dogum_tarihi ?? null),
            'DOGUM_YERI_YILI' => trim($yer . ($yer !== '' && $yil !== '' ? ' - ' : '') . $yil),
            'ADRES' => trim((string) ($p->adres ?? '')),
            'TC_KIMLIK_NO' => trim((string) ($p->tc_kimlik_no ?? '')),
            'ISE_BASLAMA_TARIHI' => $tarih($p->ise_giris_tarihi ?? null),
            'DEPARTMAN' => trim((string) ($p->departman ?? '')),
            'GOREV' => trim((string) ($p->gorev ?? '')),
            'CEP_TELEFONU' => trim((string) ($p->cep_telefonu ?? '')),
            'BUGUN' => date('d.m.Y'),
        ];
    }

    private static function icerikParcalari(ZipArchive $zip): array
    {
        $adlar = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $ad = (string) $zip->getNameIndex($i);
            if (preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $ad)) {
                $adlar[] = $ad;
            }
        }
        return $adlar;
    }

    private static function paragraflariIsle(DOMDocument $dom, callable $degistir): void
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);
        foreach ($xp->query('//w:p') as $p) {
            $dugumler = [];
            $metin = '';
            foreach ($xp->query('.//w:t', $p) as $t) {
                $dugumler[] = [$t, mb_strlen($metin, 'UTF-8'), mb_strlen($t->textContent, 'UTF-8')];
                $metin .= $t->textContent;
            }
            if ($dugumler === [] || strpos($metin, '{{') === false) {
                continue;
            }
            if (!preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $metin, $es, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }
            foreach (array_reverse($es) as $e) {
                $bas = mb_strlen(substr($metin, 0, $e[0][1]), 'UTF-8');
                $son = $bas + mb_strlen($e[0][0], 'UTF-8');
                $yeni = (string) $degistir(strtoupper($e[1][0]), $e[0][0]);
                $ilk = true;
                foreach ($dugumler as [$t, $dBas, $dUzun]) {
                    $dSon = $dBas + $dUzun;
                    if ($dSon <= $bas || $dBas >= $son) {
                        continue;
                    }
                    $m = $t->textContent;
                    $kesBas = max($bas, $dBas) - $dBas;
                    $kesSon = min($son, $dSon) - $dBas;
                    $onceki = mb_substr($m, 0, $kesBas, 'UTF-8');
                    $sonraki = mb_substr($m, $kesSon, null, 'UTF-8');
                    $t->nodeValue = '';
                    $t->appendChild($dom->createTextNode($onceki . ($ilk ? $yeni : '') . $sonraki));
                    $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                    $ilk = false;
                }
                $metin = mb_substr($metin, 0, $bas, 'UTF-8') . $yeni . mb_substr($metin, $son, null, 'UTF-8');
                $dugumler = [];
                foreach ($xp->query('.//w:t', $p) as $t) {
                    $dugumler[] = [$t, 0, 0];
                }
                $o = 0;
                foreach ($dugumler as $k => [$t]) {
                    $u = mb_strlen($t->textContent, 'UTF-8');
                    $dugumler[$k] = [$t, $o, $u];
                    $o += $u;
                }
            }
        }
    }

    public static function yerTutucular(string $dosya): array
    {
        $zip = new ZipArchive();
        if ($zip->open($dosya) !== true) {
            return [];
        }
        $bulunan = [];
        foreach (self::icerikParcalari($zip) as $ad) {
            $dom = new DOMDocument();
            if (!@$dom->loadXML((string) $zip->getFromName($ad), LIBXML_NONET)) {
                continue;
            }
            self::paragraflariIsle($dom, static function ($anahtar) use (&$bulunan) {
                $bulunan[$anahtar] = true;
                return '';
            });
        }
        $zip->close();
        return array_keys($bulunan);
    }

    public static function dogrula(string $dosya): array
    {
        $hatalar = [];
        if (filesize($dosya) > self::MAKS_BOYUT) {
            return [[], ['Dosya boyutu en fazla 5 MB olabilir.']];
        }
        $zip = new ZipArchive();
        if ($zip->open($dosya) !== true) {
            return [[], ['Dosya geçerli bir Word (.docx) belgesi değil.']];
        }
        $doc = $zip->statName('word/document.xml');
        if ($doc === false || $zip->locateName('[Content_Types].xml') === false) {
            $zip->close();
            return [[], ['Dosya geçerli bir Word (.docx) belgesi değil.']];
        }
        if ($doc['size'] > 20 * 1024 * 1024) {
            $zip->close();
            return [[], ['Belge içeriği çok büyük.']];
        }
        if ($zip->locateName('word/vbaProject.bin') !== false) {
            $hatalar[] = 'Makro içeren belgeler yüklenemez.';
        }
        $dom = new DOMDocument();
        if (!@$dom->loadXML((string) $zip->getFromName('word/document.xml'), LIBXML_NONET)) {
            $hatalar[] = 'Belge içeriği okunamadı.';
        }
        $zip->close();
        if ($hatalar) {
            return [[], $hatalar];
        }

        $bulunan = self::yerTutucular($dosya);
        $gecersiz = array_values(array_diff($bulunan, array_keys(self::alanTanimlari())));
        if ($gecersiz) {
            $hatalar[] = 'Tanımsız yer tutucu(lar): {{' . implode('}}, {{', $gecersiz) . '}}. Sayfadaki listeden geçerli adları kullanın.';
        }
        return [$bulunan, $hatalar];
    }

    public static function doldur(string $sablon, array $degerler, string $hedef): bool
    {
        if (!copy($sablon, $hedef)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($hedef) !== true) {
            return false;
        }
        foreach (self::icerikParcalari($zip) as $ad) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            if (!@$dom->loadXML((string) $zip->getFromName($ad), LIBXML_NONET)) {
                continue;
            }
            self::paragraflariIsle($dom, static fn($anahtar, $ham) => $degerler[$anahtar] ?? $ham);
            $zip->addFromString($ad, $dom->saveXML());
        }
        return $zip->close();
    }
}
