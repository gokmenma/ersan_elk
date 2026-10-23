<?php

namespace App\Service;

use RuntimeException;

class YedekSifrelemeService
{
    private const IMZA = 'ERSYDK01';
    private const ALGORITMA = 'aes-256-gcm';
    private const PARCA_BOYUTU = 1024 * 1024;
    private const BASLIK_BOYUTU = 4 + 1 + 12 + 16;

    private string $anahtar;

    public function __construct()
    {
        $anahtar = base64_decode((string) ($_ENV['YEDEK_SIFRELEME_ANAHTARI'] ?? ''), true);
        if ($anahtar === false || strlen($anahtar) !== 32) {
            throw new RuntimeException('YEDEK_SIFRELEME_ANAHTARI .env icinde tanimli degil veya 32 byte base64 degil.');
        }
        if (!in_array(self::ALGORITMA, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('Sunucuda OpenSSL ' . self::ALGORITMA . ' destegi yok.');
        }
        $this->anahtar = $anahtar;
    }

    public static function anahtarUret(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function sifrele(string $kaynak, string $hedef): void
    {
        [$giris, $cikis, $gecici] = $this->dosyalariAc($kaynak, $hedef);

        try {
            $dosyaKimligi = random_bytes(16);
            fwrite($cikis, self::IMZA . $dosyaKimligi);

            $sira = 0;
            $parca = (string) fread($giris, self::PARCA_BOYUTU);
            while (true) {
                $sonraki = feof($giris) ? '' : (string) fread($giris, self::PARCA_BOYUTU);
                $son = $sonraki === '';
                $nonce = random_bytes(12);
                $etiket = '';

                $sifreli = openssl_encrypt(
                    $parca,
                    self::ALGORITMA,
                    $this->anahtar,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $etiket,
                    $this->ekVeri($dosyaKimligi, $sira, $son),
                    16
                );
                if ($sifreli === false) {
                    throw new RuntimeException('Sifreleme basarisiz.');
                }

                fwrite($cikis, pack('N', strlen($sifreli)) . ($son ? "\x01" : "\x00") . $nonce . $etiket . $sifreli);

                if ($son) {
                    break;
                }
                $parca = $sonraki;
                $sira++;
            }

            $this->tamamla($giris, $cikis, $gecici, $hedef);
        } catch (\Throwable $e) {
            $this->iptal($giris, $cikis, $gecici);
            throw $e;
        }
    }

    public function coz(string $kaynak, string $hedef): void
    {
        [$giris, $cikis, $gecici] = $this->dosyalariAc($kaynak, $hedef);

        try {
            $ust = (string) fread($giris, 24);
            if (strlen($ust) !== 24 || strncmp($ust, self::IMZA, 8) !== 0) {
                throw new RuntimeException('Dosya bu sistemin sifreli yedek bicimi degil.');
            }
            $dosyaKimligi = substr($ust, 8, 16);

            $sira = 0;
            $sonGeldi = false;
            while (($baslik = (string) fread($giris, self::BASLIK_BOYUTU)) !== '') {
                if ($sonGeldi || strlen($baslik) !== self::BASLIK_BOYUTU) {
                    throw new RuntimeException('Sifreli yedek bozuk: beklenmeyen veri.');
                }

                $uzunluk = unpack('N', substr($baslik, 0, 4))[1];
                if ($uzunluk > self::PARCA_BOYUTU) {
                    throw new RuntimeException('Sifreli yedek bozuk: gecersiz parca boyu.');
                }
                $son = $baslik[4] === "\x01";
                $sifreli = $uzunluk > 0 ? (string) stream_get_contents($giris, $uzunluk) : '';
                if (strlen($sifreli) !== $uzunluk) {
                    throw new RuntimeException('Sifreli yedek bozuk: eksik parca.');
                }

                $acik = openssl_decrypt(
                    $sifreli,
                    self::ALGORITMA,
                    $this->anahtar,
                    OPENSSL_RAW_DATA,
                    substr($baslik, 5, 12),
                    substr($baslik, 17, 16),
                    $this->ekVeri($dosyaKimligi, $sira, $son)
                );
                if ($acik === false) {
                    throw new RuntimeException('Sifre cozulemedi: anahtar yanlis veya dosya degistirilmis.');
                }

                fwrite($cikis, $acik);
                $sonGeldi = $son;
                $sira++;
            }

            if (!$sonGeldi) {
                throw new RuntimeException('Sifreli yedek kesik: son parca bulunamadi.');
            }

            $this->tamamla($giris, $cikis, $gecici, $hedef);
        } catch (\Throwable $e) {
            $this->iptal($giris, $cikis, $gecici);
            throw $e;
        }
    }

    private function ekVeri(string $dosyaKimligi, int $sira, bool $son): string
    {
        return self::IMZA . $dosyaKimligi . pack('J', $sira) . ($son ? "\x01" : "\x00");
    }

    private function dosyalariAc(string $kaynak, string $hedef): array
    {
        $giris = fopen($kaynak, 'rb');
        if ($giris === false) {
            throw new RuntimeException('Kaynak dosya acilamadi: ' . $kaynak);
        }

        $gecici = $hedef . '.tmp';
        $cikis = fopen($gecici, 'wb');
        if ($cikis === false) {
            fclose($giris);
            throw new RuntimeException('Hedef dosya acilamadi: ' . $gecici);
        }
        @chmod($gecici, 0600);

        return [$giris, $cikis, $gecici];
    }

    private function tamamla($giris, $cikis, string $gecici, string $hedef): void
    {
        fclose($giris);
        $yazildi = fflush($cikis);
        $yazildi = fclose($cikis) && $yazildi;
        if (!$yazildi || !rename($gecici, $hedef)) {
            @unlink($gecici);
            throw new RuntimeException('Hedef dosya yazilamadi: ' . $hedef);
        }
    }

    private function iptal($giris, $cikis, string $gecici): void
    {
        if (is_resource($giris)) {
            fclose($giris);
        }
        if (is_resource($cikis)) {
            fclose($cikis);
        }
        @unlink($gecici);
    }
}
