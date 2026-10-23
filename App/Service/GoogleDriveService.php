<?php

namespace App\Service;

use RuntimeException;

class GoogleDriveService
{
    public const KAPSAM = 'https://www.googleapis.com/auth/drive.file';
    public const YONLENDIRME = 'http://localhost';

    private const YETKI_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_URL = 'https://www.googleapis.com/drive/v3/files';
    private const YUKLEME_URL = 'https://www.googleapis.com/upload/drive/v3/files';
    private const PARCA_BOYUTU = 8 * 1024 * 1024;
    private const DENEME_SAYISI = 5;

    private string $istemciId;
    private string $istemciSirri;
    private string $yenilemeTokeni;
    private ?string $erisimTokeni = null;
    private int $tokenBitis = 0;

    public function __construct(?string $yenilemeTokeni = null)
    {
        $this->istemciId = (string) ($_ENV['GDRIVE_CLIENT_ID'] ?? '');
        $this->istemciSirri = (string) ($_ENV['GDRIVE_CLIENT_SECRET'] ?? '');
        $this->yenilemeTokeni = (string) ($yenilemeTokeni ?? ($_ENV['GDRIVE_REFRESH_TOKEN'] ?? ''));

        if ($this->istemciId === '' || $this->istemciSirri === '') {
            throw new RuntimeException('GDRIVE_CLIENT_ID / GDRIVE_CLIENT_SECRET .env icinde tanimli degil.');
        }
    }

    public function yetkilendirmeAdresi(): string
    {
        return self::YETKI_URL . '?' . http_build_query([
            'client_id' => $this->istemciId,
            'redirect_uri' => self::YONLENDIRME,
            'response_type' => 'code',
            'scope' => self::KAPSAM,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
    }

    public function koduDegistir(string $kod): string
    {
        $yanit = $this->tokenIstegi([
            'code' => $kod,
            'client_id' => $this->istemciId,
            'client_secret' => $this->istemciSirri,
            'redirect_uri' => self::YONLENDIRME,
            'grant_type' => 'authorization_code',
        ]);

        if (empty($yanit['refresh_token'])) {
            throw new RuntimeException('Google yanitinda refresh_token yok. Uygulama erisimini Google hesabindan kaldirip tekrar deneyin.');
        }

        $this->yenilemeTokeni = $yanit['refresh_token'];
        $this->erisimTokeni = $yanit['access_token'] ?? null;
        $this->tokenBitis = time() + (int) ($yanit['expires_in'] ?? 0) - 60;

        return $this->yenilemeTokeni;
    }

    public function klasorOlustur(string $ad): string
    {
        $yanit = $this->jsonIstek('POST', self::API_URL . '?fields=id', [
            'name' => $ad,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        return (string) $yanit['id'];
    }

    public function listele(string $klasorId): array
    {
        $dosyalar = [];
        $sayfa = null;

        do {
            $parametre = [
                'q' => sprintf("'%s' in parents and trashed = false", str_replace("'", "\\'", $klasorId)),
                'fields' => 'nextPageToken,files(id,name,size,md5Checksum,createdTime)',
                'pageSize' => 1000,
            ];
            if ($sayfa !== null) {
                $parametre['pageToken'] = $sayfa;
            }

            $yanit = $this->jsonIstek('GET', self::API_URL . '?' . http_build_query($parametre));
            foreach ($yanit['files'] ?? [] as $dosya) {
                $dosyalar[] = $dosya;
            }
            $sayfa = $yanit['nextPageToken'] ?? null;
        } while ($sayfa !== null);

        return $dosyalar;
    }

    public function yukle(string $yerelDosya, string $ad, string $klasorId): array
    {
        $boyut = filesize($yerelDosya);
        if ($boyut === false) {
            throw new RuntimeException('Dosya okunamadi: ' . $yerelDosya);
        }

        $oturum = $this->yuklemeOturumuAc($ad, $klasorId, $boyut);
        $kaynak = fopen($yerelDosya, 'rb');
        if ($kaynak === false) {
            throw new RuntimeException('Dosya acilamadi: ' . $yerelDosya);
        }

        try {
            $konum = 0;
            $hata = 0;

            while (true) {
                fseek($kaynak, $konum);
                $parca = $boyut > 0 ? (string) fread($kaynak, self::PARCA_BOYUTU) : '';
                $bitis = $konum + strlen($parca) - 1;
                $aralik = $boyut > 0 ? "bytes {$konum}-{$bitis}/{$boyut}" : 'bytes */0';

                $yanit = $this->istek('PUT', $oturum, $parca, ['Content-Range: ' . $aralik], 600);

                if ($yanit['durum'] === 200 || $yanit['durum'] === 201) {
                    return $this->jsonCoz($yanit['govde']);
                }

                if ($yanit['durum'] === 308) {
                    $konum = $this->sonrakiKonum($yanit['basliklar']);
                    $hata = 0;
                    continue;
                }

                if ($yanit['durum'] === 404 || $yanit['durum'] === 410 || ++$hata > self::DENEME_SAYISI) {
                    throw new RuntimeException("Drive yuklemesi basarisiz ({$yanit['durum']}): " . $this->hataMetni($yanit));
                }

                sleep(min(60, 2 ** $hata));
                $durum = $this->istek('PUT', $oturum, '', ["Content-Range: bytes */{$boyut}"], 60);
                if ($durum['durum'] === 200 || $durum['durum'] === 201) {
                    return $this->jsonCoz($durum['govde']);
                }
                $konum = $durum['durum'] === 308 ? $this->sonrakiKonum($durum['basliklar']) : $konum;
            }
        } finally {
            fclose($kaynak);
        }
    }

    public function indir(string $dosyaId, string $hedef): void
    {
        for ($deneme = 1; ; $deneme++) {
            $cikti = fopen($hedef, 'wb');
            if ($cikti === false) {
                throw new RuntimeException('Hedef dosya acilamadi: ' . $hedef);
            }

            $ch = curl_init(self::API_URL . '/' . rawurlencode($dosyaId) . '?alt=media');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->erisimTokeniAl()],
                CURLOPT_FILE => $cikti,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FAILONERROR => true,
            ]);
            $basarili = curl_exec($ch);
            $durum = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $hataMetni = curl_error($ch);
            curl_close($ch);
            fclose($cikti);

            if ($basarili !== false && $durum === 200) {
                return;
            }

            @unlink($hedef);
            if ($durum === 401) {
                $this->erisimTokeni = null;
            }
            if ($deneme >= self::DENEME_SAYISI || ($durum >= 400 && $durum < 500 && $durum !== 401 && $durum !== 429)) {
                throw new RuntimeException("Drive indirmesi basarisiz ({$durum}): {$hataMetni}");
            }
            sleep(min(60, 2 ** $deneme));
        }
    }

    public function sil(string $dosyaId): void
    {
        $yanit = $this->yetkiliIstek('DELETE', self::API_URL . '/' . rawurlencode($dosyaId));
        if ($yanit['durum'] !== 204 && $yanit['durum'] !== 404) {
            throw new RuntimeException("Drive dosyasi silinemedi ({$yanit['durum']}): " . $this->hataMetni($yanit));
        }
    }

    private function yuklemeOturumuAc(string $ad, string $klasorId, int $boyut): string
    {
        $govde = json_encode(['name' => $ad, 'parents' => [$klasorId]]);
        $yanit = $this->yetkiliIstek(
            'POST',
            self::YUKLEME_URL . '?uploadType=resumable&fields=id,name,size,md5Checksum',
            $govde,
            [
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: application/octet-stream',
                'X-Upload-Content-Length: ' . $boyut,
            ]
        );

        $konum = $yanit['basliklar']['location'] ?? '';
        if ($yanit['durum'] !== 200 || $konum === '') {
            throw new RuntimeException("Drive yukleme oturumu acilamadi ({$yanit['durum']}): " . $this->hataMetni($yanit));
        }

        return $konum;
    }

    private function sonrakiKonum(array $basliklar): int
    {
        if (preg_match('/bytes=\d+-(\d+)/', $basliklar['range'] ?? '', $m)) {
            return (int) $m[1] + 1;
        }
        return 0;
    }

    private function jsonIstek(string $yontem, string $url, ?array $govde = null): array
    {
        $yanit = $this->yetkiliIstek(
            $yontem,
            $url,
            $govde === null ? null : json_encode($govde),
            $govde === null ? [] : ['Content-Type: application/json; charset=UTF-8']
        );

        if ($yanit['durum'] < 200 || $yanit['durum'] >= 300) {
            throw new RuntimeException("Drive istegi basarisiz ({$yanit['durum']}): " . $this->hataMetni($yanit));
        }

        return $this->jsonCoz($yanit['govde']);
    }

    private function yetkiliIstek(string $yontem, string $url, ?string $govde = null, array $basliklar = []): array
    {
        for ($deneme = 1; ; $deneme++) {
            $yanit = $this->istek(
                $yontem,
                $url,
                $govde,
                array_merge($basliklar, ['Authorization: Bearer ' . $this->erisimTokeniAl()])
            );

            $tekrar = $yanit['durum'] === 0 || $yanit['durum'] === 401 || $yanit['durum'] === 429 || $yanit['durum'] >= 500;
            if (!$tekrar || $deneme >= self::DENEME_SAYISI) {
                return $yanit;
            }

            if ($yanit['durum'] === 401) {
                $this->erisimTokeni = null;
            } else {
                sleep(min(60, 2 ** $deneme));
            }
        }
    }

    private function erisimTokeniAl(): string
    {
        if ($this->erisimTokeni !== null && time() < $this->tokenBitis) {
            return $this->erisimTokeni;
        }

        if ($this->yenilemeTokeni === '') {
            throw new RuntimeException('GDRIVE_REFRESH_TOKEN .env icinde tanimli degil.');
        }

        $yanit = $this->tokenIstegi([
            'client_id' => $this->istemciId,
            'client_secret' => $this->istemciSirri,
            'refresh_token' => $this->yenilemeTokeni,
            'grant_type' => 'refresh_token',
        ]);

        $this->erisimTokeni = (string) $yanit['access_token'];
        $this->tokenBitis = time() + (int) ($yanit['expires_in'] ?? 3600) - 60;

        return $this->erisimTokeni;
    }

    private function tokenIstegi(array $alanlar): array
    {
        $yanit = $this->istek(
            'POST',
            self::TOKEN_URL,
            http_build_query($alanlar),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $veri = json_decode($yanit['govde'], true);

        if ($yanit['durum'] !== 200 || !is_array($veri) || empty($veri['access_token'])) {
            $kod = is_array($veri) ? (string) ($veri['error'] ?? '') : '';
            if ($kod === 'invalid_grant') {
                throw new RuntimeException('Google yetkisi gecersiz veya suresi dolmus (invalid_grant). cron/drive_yetkilendir.php ile yeniden yetkilendirin.');
            }
            throw new RuntimeException("Google token alinamadi ({$yanit['durum']}): " . $this->hataMetni($yanit));
        }

        return $veri;
    }

    private function istek(string $yontem, string $url, ?string $govde = null, array $basliklar = [], int $zamanAsimi = 120): array
    {
        $gelenBasliklar = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $yontem,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $basliklar,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => $zamanAsimi,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $satir) use (&$gelenBasliklar): int {
                $parca = explode(':', $satir, 2);
                if (count($parca) === 2) {
                    $gelenBasliklar[strtolower(trim($parca[0]))] = trim($parca[1]);
                }
                return strlen($satir);
            },
        ]);
        if ($govde !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $govde);
        }

        $cevap = curl_exec($ch);
        $durum = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hata = curl_error($ch);
        curl_close($ch);

        return [
            'durum' => $cevap === false ? 0 : $durum,
            'govde' => $cevap === false ? $hata : (string) $cevap,
            'basliklar' => $gelenBasliklar,
        ];
    }

    private function jsonCoz(string $govde): array
    {
        $veri = json_decode($govde, true);
        if (!is_array($veri)) {
            throw new RuntimeException('Drive yaniti cozulemedi.');
        }
        return $veri;
    }

    private function hataMetni(array $yanit): string
    {
        $veri = json_decode($yanit['govde'], true);
        if (is_array($veri)) {
            $mesaj = $veri['error']['message'] ?? $veri['error_description'] ?? $veri['error'] ?? null;
            if (is_string($mesaj)) {
                return $mesaj;
            }
        }
        return mb_substr(trim($yanit['govde']), 0, 300);
    }
}
