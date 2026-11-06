<?php

namespace App\Service;

class GuvenliBelgeYuklemeService
{
    public const AZAMI_BOYUT = 8388608;
    private const AZAMI_PIKSEL = 30000000;
    private const AZAMI_KENAR = 2600;
    private const AZAMI_STREAM = 6000;
    private const AZAMI_ACILMIS = 31457280;
    private const PDF_TEHLIKELI = '#/(JavaScript|JS|Launch|EmbeddedFile|EmbeddedFiles|OpenAction|AA|RichMedia|XFA|SubmitForm|ImportData|GoToR|GoToE|Encrypt|Movie|Sound|Rendition|Flash|3D|FileAttachment)(?![A-Za-z0-9])#';

    public static function isle(array $dosya): array
    {
        if (!isset($dosya['error'], $dosya['tmp_name'], $dosya['size']) || is_array($dosya['error'])) {
            throw new \DomainException('Dosya alınamadı.');
        }
        if ($dosya['error'] === UPLOAD_ERR_INI_SIZE || $dosya['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new \DomainException('Dosya boyutu en fazla 8 MB olabilir.');
        }
        if ($dosya['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($dosya['tmp_name'])) {
            throw new \DomainException('Dosya yüklenemedi, lütfen tekrar deneyin.');
        }

        $boyut = (int) filesize($dosya['tmp_name']);
        if ($boyut <= 0) {
            throw new \DomainException('Dosya boş görünüyor.');
        }
        if ($boyut > self::AZAMI_BOYUT) {
            throw new \DomainException('Dosya boyutu en fazla 8 MB olabilir.');
        }

        $ad = (string) ($dosya['name'] ?? '');
        $uzanti = strtolower(pathinfo($ad, PATHINFO_EXTENSION));
        if (!in_array($uzanti, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            throw new \DomainException('Yalnızca JPG, PNG veya PDF dosyası yükleyebilirsiniz.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $dosya['tmp_name']);
        finfo_close($finfo);

        $izinli = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'application/pdf' => ['pdf']];
        if (!isset($izinli[$mime]) || !in_array($uzanti, $izinli[$mime], true)) {
            throw new \DomainException('Dosya türü uzantısıyla uyuşmuyor. Yalnızca JPG, PNG veya PDF yükleyebilirsiniz.');
        }

        try {
            DosyaIcerikDogrulayici::dogrula($dosya['tmp_name'], $uzanti === 'jpeg' ? 'jpg' : $uzanti);
        } catch (\DomainException $e) {
            throw new \DomainException($e->getMessage());
        }

        $icerik = (string) file_get_contents($dosya['tmp_name']);
        if ($icerik === '') {
            throw new \DomainException('Dosya okunamadı.');
        }
        if (stripos($icerik, '<?php') !== false || stripos($icerik, '<?=') !== false || stripos($icerik, '<script') !== false) {
            throw new \DomainException('Dosya güvenlik taramasından geçemedi.');
        }

        if ($mime === 'application/pdf') {
            self::pdfTara($icerik);
            return ['veri' => $icerik, 'mime' => 'application/pdf', 'uzanti' => 'pdf'];
        }

        return ['veri' => self::goruntuYenidenKodla($dosya['tmp_name'], $mime), 'mime' => 'image/jpeg', 'uzanti' => 'jpg'];
    }

    private static function goruntuYenidenKodla(string $yol, string $mime): string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            throw new \DomainException('Görsel işlenemedi.');
        }
        $bilgi = @getimagesize($yol);
        if ($bilgi === false || $bilgi[0] < 1 || $bilgi[1] < 1 || ($bilgi[0] * $bilgi[1]) > self::AZAMI_PIKSEL) {
            throw new \DomainException('Görsel çözünürlüğü desteklenmiyor.');
        }

        $img = $mime === 'image/png' ? @imagecreatefrompng($yol) : @imagecreatefromjpeg($yol);
        if (!$img) {
            throw new \DomainException('Geçerli bir görsel dosyası değil.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($yol);
            $yon = (int) ($exif['Orientation'] ?? 1);
            $aci = [3 => 180, 6 => -90, 8 => 90][$yon] ?? 0;
            if ($aci !== 0) {
                $dondu = imagerotate($img, $aci, 0);
                if ($dondu) {
                    imagedestroy($img);
                    $img = $dondu;
                }
            }
        }

        $g = imagesx($img);
        $y = imagesy($img);
        $en = max($g, $y);
        if ($en > self::AZAMI_KENAR) {
            $olcek = imagescale($img, (int) max(1, round($g * self::AZAMI_KENAR / $en)), (int) max(1, round($y * self::AZAMI_KENAR / $en)));
            if ($olcek) {
                imagedestroy($img);
                $img = $olcek;
            }
        }

        $zemin = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($zemin, 0, 0, imagecolorallocate($zemin, 255, 255, 255));
        imagecopy($zemin, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);

        ob_start();
        $ok = imagejpeg($zemin, null, 85);
        $cikti = (string) ob_get_clean();
        imagedestroy($zemin);
        if (!$ok || $cikti === '') {
            throw new \DomainException('Görsel işlenemedi.');
        }
        return $cikti;
    }

    private static function pdfTara(string $icerik): void
    {
        $bas = strpos($icerik, '%PDF-');
        if ($bas === false || $bas > 1024) {
            throw new \DomainException('Geçerli bir PDF dosyası değil.');
        }

        $yapi = '';
        $objStreamler = [];
        $konum = 0;
        $uzunluk = strlen($icerik);
        $sayac = 0;
        while ($konum < $uzunluk) {
            $s = strpos($icerik, 'stream', $konum);
            if ($s === false) {
                $yapi .= substr($icerik, $konum);
                break;
            }
            if (++$sayac > self::AZAMI_STREAM) {
                throw new \DomainException('PDF yapısı çok karmaşık, güvenlik taramasından geçemedi.');
            }
            $yapi .= substr($icerik, $konum, $s - $konum) . ' stream ';
            $veriBas = $s + 6;
            if (substr($icerik, $veriBas, 2) === "\r\n") {
                $veriBas += 2;
            } elseif (isset($icerik[$veriBas]) && ($icerik[$veriBas] === "\n" || $icerik[$veriBas] === "\r")) {
                $veriBas += 1;
            }
            $e = strpos($icerik, 'endstream', $veriBas);
            if ($e === false) {
                throw new \DomainException('PDF dosyası bozuk görünüyor.');
            }
            $sozlukBas = max($konum, $s - 600);
            $sozluk = substr($icerik, $sozlukBas, $s - $sozlukBas);
            $objSon = strrpos($sozluk, 'obj');
            if ($objSon !== false) {
                $sozluk = substr($sozluk, $objSon);
            }
            if (preg_match('#/ObjStm(?![A-Za-z0-9])#', $sozluk)) {
                $objStreamler[] = ['sozluk' => $sozluk, 'veri' => substr($icerik, $veriBas, $e - $veriBas)];
            }
            $yapi .= $sozluk . ' ';
            $konum = $e + 9;
        }

        self::pdfTokenKontrol($yapi);

        $toplam = 0;
        foreach ($objStreamler as $o) {
            if (!preg_match('#/FlateDecode(?![A-Za-z0-9])#', $o['sozluk'])) {
                throw new \DomainException('PDF dosyası güvenlik taramasından geçemedi.');
            }
            $veri = trim($o['veri'], "\r\n");
            $acik = @gzuncompress($veri, self::AZAMI_ACILMIS);
            if ($acik === false) {
                $acik = @gzinflate(substr($veri, 2), self::AZAMI_ACILMIS);
            }
            if ($acik === false) {
                throw new \DomainException('PDF dosyası güvenlik taramasından geçemedi.');
            }
            $toplam += strlen($acik);
            if ($toplam > self::AZAMI_ACILMIS) {
                throw new \DomainException('PDF dosyası güvenlik taramasından geçemedi.');
            }
            self::pdfTokenKontrol($acik);
        }
    }

    private static function pdfTokenKontrol(string $metin): void
    {
        $cozulmus = preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn($m) => chr(hexdec($m[1])), $metin);
        $cozulmus = $cozulmus ?? $metin;
        if (preg_match(self::PDF_TEHLIKELI, $cozulmus)) {
            throw new \DomainException('Betik, ek dosya veya etkin içerik barındıran PDF dosyaları yüklenemez.');
        }
    }
}
