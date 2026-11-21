<?php

namespace App\Service;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as PhpWordHtml;
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

    public const HTML_MAKS_BOYUT = 4194304;

    private const IZINLI_ETIKETLER = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'span', 'h1', 'h2', 'h3', 'h4',
        'blockquote', 'ol', 'ul', 'li', 'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'colgroup', 'col', 'div', 'hr',
    ];

    private const TEHLIKELI_ETIKETLER = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg', 'math', 'video', 'audio', 'base'];

    private const IZINLI_NITELIKLER = ['class', 'style', 'href', 'src', 'alt', 'colspan', 'rowspan', 'width', 'height', 'border', 'start', 'type', 'align', 'valign', 'cellpadding', 'cellspacing', 'bgcolor', 'data-row', 'data-list', 'data-no-borders', 'data-sbp-spacer', 'data-row-height', 'data-col-width', 'target', 'rel'];

    private const IZINLI_STILLER = [
        'font-size', 'font-family', 'font-weight', 'font-style', 'text-decoration', 'color', 'background-color', 'text-align', 'text-indent',
        'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'padding', 'padding-left', 'padding-right',
        'padding-top', 'padding-bottom', 'width', 'height', 'min-height', 'min-width', 'max-width', 'border', 'border-style', 'border-width',
        'border-color', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-top-style', 'border-bottom-style', 'border-left-style', 'border-right-style', 'border-top-width', 'border-bottom-width', 'border-left-width', 'border-right-width', 'border-top-color', 'border-bottom-color', 'border-left-color', 'border-right-color', 'background', 'vertical-align', 'table-layout', 'border-collapse',
    ];

    public static function varsayilanHtml(): string
    {
        $yol = dirname(__DIR__, 2) . '/resources/sablonlar/hizmet_sozlesmesi_varsayilan.html';
        $icerik = is_file($yol) ? (string) file_get_contents($yol) : '';
        return $icerik !== '' ? trim((string) preg_replace('/>\s*\n\s*</', '><', $icerik)) : '<p></p>';
    }

    private static function htmlYukle(string $html): ?DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $onceki = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="utf-8"?><div id="kok">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);
        return $ok ? $dom : null;
    }

    private static function kokDugum(DOMDocument $dom): ?\DOMElement
    {
        $kok = $dom->getElementById('kok');
        if (!$kok) {
            foreach ($dom->getElementsByTagName('div') as $d) {
                if ($d->getAttribute('id') === 'kok') {
                    return $d;
                }
            }
        }
        return $kok;
    }

    private static function stilTemizle(string $stil): string
    {
        $sonuc = [];
        foreach (explode(';', $stil) as $parca) {
            $ayrac = strpos($parca, ':');
            if ($ayrac === false) {
                continue;
            }
            $ad = strtolower(trim(substr($parca, 0, $ayrac)));
            $deger = trim(substr($parca, $ayrac + 1));
            $deger = trim(str_ireplace('!important', '', $deger));
            if (!in_array($ad, self::IZINLI_STILLER, true) || $deger === '') {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript|@import|[<>\\\\]/i', $deger)) {
                continue;
            }
            $sonuc[] = $ad . ': ' . $deger;
        }
        return implode('; ', $sonuc);
    }

    private static function dugumTemizle(\DOMNode $dugum): void
    {
        $cocuklar = [];
        foreach ($dugum->childNodes as $c) {
            $cocuklar[] = $c;
        }
        foreach ($cocuklar as $c) {
            if ($c instanceof \DOMComment || $c instanceof \DOMProcessingInstruction) {
                $dugum->removeChild($c);
                continue;
            }
            if (!($c instanceof \DOMElement)) {
                continue;
            }
            $etiket = strtolower($c->nodeName);
            if ($c->hasAttribute('data-sbp-spacer')) {
                $dugum->removeChild($c);
                continue;
            }
            if (in_array($etiket, self::TEHLIKELI_ETIKETLER, true) || ($etiket === 'span' && preg_match('/(^|\s)ql-ui(\s|$)/', $c->getAttribute('class')))) {
                $dugum->removeChild($c);
                continue;
            }
            self::dugumTemizle($c);
            if (!in_array($etiket, self::IZINLI_ETIKETLER, true)) {
                while ($c->firstChild) {
                    $dugum->insertBefore($c->firstChild, $c);
                }
                $dugum->removeChild($c);
                continue;
            }
            $silinecek = [];
            foreach ($c->attributes as $nitelik) {
                $silinecek[] = $nitelik->nodeName;
            }
            foreach ($silinecek as $ad) {
                $kucuk = strtolower($ad);
                $deger = $c->getAttribute($ad);
                if (!in_array($kucuk, self::IZINLI_NITELIKLER, true)) {
                    $c->removeAttribute($ad);
                    continue;
                }
                if ($kucuk === 'src') {
                    if ($etiket !== 'img' || !preg_match('#^data:image/(png|jpe?g|gif);base64,[A-Za-z0-9+/=\s]+$#', $deger)) {
                        $c->removeAttribute($ad);
                    }
                } elseif ($kucuk === 'class') {
                    $siniflar = array_filter(preg_split('/\s+/', $deger), static fn($s) => preg_match('/^(ql-[a-z0-9-]+|variable-tag|has-borders|no-borders|signatures-table|first-paragraph|table-cell-inner|table|table-bordered|table-striped|table-condensed|table-hover|note-float-(left|right)|note-float-none)$/', $s));
                    $siniflar ? $c->setAttribute('class', implode(' ', $siniflar)) : $c->removeAttribute('class');
                } elseif ($kucuk === 'style') {
                    $temiz = self::stilTemizle($deger);
                    $temiz !== '' ? $c->setAttribute('style', $temiz) : $c->removeAttribute('style');
                } elseif ($kucuk === 'href') {
                    if (!preg_match('#^(https?://|mailto:|tel:)#i', trim($deger))) {
                        $c->removeAttribute('href');
                    }
                } elseif ($kucuk === 'target' || $kucuk === 'rel') {
                    $c->removeAttribute($ad);
                } elseif (!preg_match('/^[A-Za-z0-9_.%#\- ]{0,40}$/', $deger)) {
                    $c->removeAttribute($ad);
                }
            }
            if ($etiket === 'img' && !$c->hasAttribute('src')) {
                $dugum->removeChild($c);
                continue;
            }
            if ($etiket === 'a' && $c->hasAttribute('href')) {
                $c->setAttribute('rel', 'noopener noreferrer');
                $c->setAttribute('target', '_blank');
            }
        }
    }

    private static function govdeHtml(DOMDocument $dom): string
    {
        $kok = self::kokDugum($dom);
        $cikti = '';
        if ($kok) {
            foreach ($kok->childNodes as $c) {
                $cikti .= $dom->saveHTML($c);
            }
        }
        return $cikti;
    }

    public static function htmlTemizle(string $html): string
    {
        $dom = self::htmlYukle($html);
        $kok = $dom ? self::kokDugum($dom) : null;
        if (!$dom || !$kok) {
            return '';
        }
        self::dugumTemizle($kok);
        return trim(self::govdeHtml($dom));
    }

    public static function htmlYerTutucular(string $html): array
    {
        $bulunan = [];
        if (preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $html, $es)) {
            foreach ($es[1] as $anahtar) {
                $bulunan[strtoupper($anahtar)] = true;
            }
        }
        return array_keys($bulunan);
    }

    public static function htmlDogrula(string $html): array
    {
        if (trim(strip_tags($html)) === '') {
            return [[], ['Şablon içeriği boş olamaz.']];
        }
        if (strlen($html) > self::HTML_MAKS_BOYUT) {
            return [[], ['Şablon içeriği çok büyük (en fazla 1 MB).']];
        }
        $hatalar = [];
        $metin = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $hamSayi = preg_match_all('/\{\{\s*[A-Za-z0-9_]+\s*\}\}/u', $html);
        $metinSayi = preg_match_all('/\{\{\s*[A-Za-z0-9_]+\s*\}\}/u', $metin);
        if ($hamSayi !== $metinSayi || preg_match_all('/\{\{/', $metin) !== $metinSayi) {
            $hatalar[] = 'Bozuk bir yer tutucu var: {{...}} ifadeleri bölünmeden, tek parça ve biçimlendirmesiz yazılmalıdır.';
        }
        $bulunan = self::htmlYerTutucular($html);
        $gecersiz = array_values(array_diff($bulunan, array_keys(self::alanTanimlari())));
        if ($gecersiz) {
            $hatalar[] = 'Tanımsız yer tutucu(lar): {{' . implode('}}, {{', $gecersiz) . '}}. Soldaki listeden geçerli alanları kullanın.';
        }
        return [$bulunan, $hatalar];
    }

    public static function htmlDoldur(string $html, array $degerler): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u',
            static function (array $e) use ($degerler): string {
                $anahtar = strtoupper($e[1]);
                return array_key_exists($anahtar, $degerler)
                    ? htmlspecialchars((string) $degerler[$anahtar], ENT_QUOTES | ENT_XML1, 'UTF-8')
                    : $e[0];
            },
            $html
        );
    }

    public static function editorIcinHazirla(string $html): string
    {
        $dom = self::htmlYukle($html);
        $kok = $dom ? self::kokDugum($dom) : null;
        if (!$dom || !$kok) {
            return $html;
        }
        $xp = new DOMXPath($dom);
        foreach (iterator_to_array($xp->query('//span[contains(concat(" ", normalize-space(@class), " "), " ql-ui ")]')) as $d) {
            $d->parentNode->removeChild($d);
        }
        foreach (iterator_to_array($xp->query('//ol[li[@data-list="bullet"]]')) as $ol) {
            $ul = $dom->createElement('ul');
            while ($ol->firstChild) {
                $ul->appendChild($ol->firstChild);
            }
            $ol->parentNode->replaceChild($ul, $ol);
        }
        foreach (iterator_to_array($xp->query('//*[@class]')) as $d) {
            $stil = trim((string) $d->getAttribute('style'));
            $ek = [];
            $kalan = [];
            foreach (preg_split('/\s+/', trim($d->getAttribute('class'))) as $sinif) {
                if (preg_match('/^ql-align-(left|center|right|justify)$/', $sinif, $m)) {
                    $ek[] = 'text-align: ' . $m[1];
                } elseif (preg_match('/^ql-indent-(\d)$/', $sinif, $m)) {
                    $ek[] = 'margin-left: ' . ((int) $m[1] * 36) . 'px';
                } elseif ($sinif === 'ql-font-times') {
                    $ek[] = "font-family: 'Times New Roman'";
                } elseif (strpos($sinif, 'ql-') !== 0 && $sinif !== '') {
                    $kalan[] = $sinif;
                }
            }
            $kalan ? $d->setAttribute('class', implode(' ', $kalan)) : $d->removeAttribute('class');
            if ($ek) {
                $d->setAttribute('style', trim($stil . ($stil !== '' ? '; ' : '') . implode('; ', $ek), '; '));
            }
        }
        foreach (iterator_to_array($xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " has-borders ")]')) as $t) {
            $t->setAttribute('style', trim($t->getAttribute('style') . '; width: 100%; border-collapse: collapse', '; '));
            $t->setAttribute('class', 'table-bordered');
            foreach ($xp->query('.//td|.//th', $t) as $td) {
                $td->setAttribute('style', trim($td->getAttribute('style') . '; border: 1px solid #000; padding: 4px 8px', '; '));
            }
        }
        foreach (iterator_to_array($xp->query('//table[@data-no-borders or contains(concat(" ", normalize-space(@class), " "), " no-borders ")]')) as $t) {
            $t->setAttribute('style', trim($t->getAttribute('style') . '; width: 100%; border-collapse: collapse', '; '));
            foreach ($xp->query('.//td|.//th', $t) as $td) {
                $td->setAttribute('style', trim($td->getAttribute('style') . '; border: none', '; '));
            }
        }
        foreach (iterator_to_array($xp->query('//*[@data-row or @data-list or @data-no-borders]')) as $d) {
            $d->removeAttribute('data-row');
            $d->removeAttribute('data-list');
        }
        return self::govdeHtml($dom);
    }

    private static function tabloCizgiliMi(\DOMElement $tablo): bool
    {
        $sinif = ' ' . $tablo->getAttribute('class') . ' ';
        if ($tablo->hasAttribute('data-no-borders') || strpos($sinif, ' no-borders ') !== false) {
            return false;
        }
        if (strpos($sinif, ' table-bordered ') !== false || strpos($sinif, ' has-borders ') !== false || (int) $tablo->getAttribute('border') > 0) {
            return true;
        }
        $stiller = $tablo->getAttribute('style');
        foreach ($tablo->getElementsByTagName('td') as $td) {
            $stiller .= ';' . $td->getAttribute('style');
        }
        return (bool) preg_match('/border[a-z-]*\s*:\s*(?!none|0(px|pt)?\s*(;|$))[^;]*(solid|single|double|dashed|\d)/i', $stiller);
    }

    private static function docxIcinHazirla(string $html): string
    {
        $html = self::editorIcinHazirla($html);
        $dom = self::htmlYukle($html);
        $kok = $dom ? self::kokDugum($dom) : null;
        if (!$dom || !$kok) {
            return '<p></p>';
        }
        $xp = new DOMXPath($dom);

        foreach (iterator_to_array($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " ql-ui ")]')) as $d) {
            $d->parentNode->removeChild($d);
        }

        foreach (iterator_to_array($xp->query('//ol[li[@data-list="bullet"]]')) as $ol) {
            $ul = $dom->createElement('ul');
            while ($ol->firstChild) {
                $ul->appendChild($ol->firstChild);
            }
            $ol->parentNode->replaceChild($ul, $ol);
        }

        foreach (iterator_to_array($xp->query('//*[@class]')) as $d) {
            $stil = trim((string) $d->getAttribute('style'));
            $ek = [];
            foreach (preg_split('/\s+/', $d->getAttribute('class')) as $sinif) {
                if (preg_match('/^ql-align-(left|center|right|justify)$/', $sinif, $m)) {
                    $ek[] = 'text-align: ' . $m[1];
                } elseif (preg_match('/^ql-indent-(\d)$/', $sinif, $m)) {
                    $ek[] = 'margin-left: ' . ((int) $m[1] * 36) . 'px';
                }
            }
            if ($ek) {
                $d->setAttribute('style', trim($stil . ($stil !== '' ? '; ' : '') . implode('; ', $ek)));
            }
        }

        foreach (iterator_to_array($xp->query('//td|//th')) as $hucre) {
            $stil = (string) $hucre->getAttribute('style');
            if (preg_match('/(^|;)\s*width\s*:\s*([\d.]+)\s*(%|pt|px|cm|mm)/i', $stil, $mw)) {
                $deger = (float) $mw[2];
                $px = match (strtolower($mw[3])) {
                    '%' => $deger * 6.8,
                    'pt' => $deger * 96 / 72,
                    'cm' => $deger * 96 / 2.54,
                    'mm' => $deger * 96 / 25.4,
                    default => $deger,
                };
                $stil = (string) preg_replace('/(^|;)\s*width\s*:[^;]*/i', '$1', $stil);
                $hucre->setAttribute('style', trim($stil . '; width: ' . round($px) . 'px', '; '));
            }
        }
        foreach (iterator_to_array($xp->query('//table')) as $tablo) {
            $tablo->setAttribute('border', self::tabloCizgiliMi($tablo) ? '1' : '0');
            if (!$tablo->hasAttribute('width') && !preg_match('/(^|;)\s*width\s*:/', (string) $tablo->getAttribute('style'))) {
                $tablo->setAttribute('width', '100%');
            }
        }

        foreach (iterator_to_array($xp->query('//img')) as $img) {
            $ham = base64_decode((string) preg_replace('#^data:image/[a-z+]+;base64,#i', '', (string) $img->getAttribute('src')), true);
            $boyut = $ham !== false ? @getimagesizefromstring($ham) : false;
            if (!$boyut) {
                $img->parentNode->removeChild($img);
                continue;
            }
            $stil = (string) $img->getAttribute('style');
            if (!$img->hasAttribute('width') && preg_match('/(?:^|;)\s*width\s*:\s*([\d.]+)px/', $stil, $mg)) {
                $img->setAttribute('width', $mg[1]);
            }
            if (!$img->hasAttribute('height') && preg_match('/(?:^|;)\s*height\s*:\s*([\d.]+)px/', $stil, $mh)) {
                $img->setAttribute('height', $mh[1]);
            }
            $g = (float) preg_replace('/[^0-9.]/', '', (string) $img->getAttribute('width'));
            if ($g <= 0) {
                $g = min((float) $boyut[0], 300.0);
            }
            $y = (float) preg_replace('/[^0-9.]/', '', (string) $img->getAttribute('height'));
            if ($y <= 0) {
                $y = (float) $boyut[1] * ($g / max(1, $boyut[0]));
            }
            if ($g > 620) {
                $y = $y * 620 / $g;
                $g = 620;
            }
            $img->removeAttribute('style');
            $img->setAttribute('width', (string) round($g));
            $img->setAttribute('height', (string) round($y));
        }

        $govde = '';
        foreach ($kok->childNodes as $c) {
            $govde .= $dom->saveXML($c);
        }
        return $govde !== '' ? $govde : '<p></p>';
    }

    public static function htmldenDocx(string $html, array $degerler, bool $cerceve, string $hedef): bool
    {
        try {
            $govde = self::docxIcinHazirla(self::htmlDoldur(self::htmlTemizle($html), $degerler));
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Times New Roman');
            $phpWord->setDefaultFontSize(10);
            $phpWord->setDefaultParagraphStyle(['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.1]);
            $bolum = [
                'pageSizeW' => 11906, 'pageSizeH' => 16838,
                'marginTop' => 567, 'marginBottom' => 567, 'marginLeft' => 850, 'marginRight' => 850,
            ];
            if ($cerceve) {
                $bolum += ['borderSize' => 16, 'borderColor' => '000000'];
            }
            $section = $phpWord->addSection($bolum);
            PhpWordHtml::addHtml($section, $govde, false, false);
            foreach ($section->getElements() as $oge) {
                if ($oge instanceof \PhpOffice\PhpWord\Element\Table) {
                    $st = $oge->getStyle();
                    if ($st) {
                        if ($st->getBorderSize() !== null) {
                            $st->setBorderSize(4);
                            $st->setBorderColor('000000');
                        }
                        $st->setCellMarginTop(60);
                        $st->setCellMarginBottom(60);
                        $st->setCellMarginLeft(100);
                        $st->setCellMarginRight(100);
                    }
                }
            }
            $phpWord->save($hedef, 'Word2007');
            return is_file($hedef) && filesize($hedef) > 0;
        } catch (\Throwable $e) {
            error_log('Sözleşme HTML->docx hatası: ' . $e->getMessage());
            return false;
        }
    }
}
