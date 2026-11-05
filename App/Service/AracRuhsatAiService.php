<?php

namespace App\Service;

final class AracRuhsatAiService
{
    private const ARAC_TIPLERI = ['binek', 'kamyonet', 'kamyon', 'minibus', 'otobus', 'motosiklet', 'diger'];
    private const YAKIT_TIPLERI = ['dizel', 'benzin', 'lpg', 'elektrik', 'hibrit'];

    public function analiz(array $file): array
    {
        $okuyucu = new AiBelgeOkuyucuService();
        $mime = $okuyucu->dogrula($file, 8);
        [$apiKey, $model] = $okuyucu->ayarlar();

        $userContent = $okuyucu->kullaniciIcerigi($this->prompt(), $file, $mime);
        $sonuc = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkiye araç tescil belgelerini (ruhsat) okuyan bir belge analiz asistanısın. Yalnızca belgede açıkça yazan bilgileri raporlarsın, tahmin veya uydurma yapmazsın. Belgede olmayan veya okunamayan alanı boş bırakırsın. Yalnızca geçerli JSON üretirsin.',
            $userContent,
            0.0
        );

        $yil = (int) preg_replace('/\D/', '', $this->metin($sonuc, 'model_yili'));
        if ($yil < 1950 || $yil > (int) date('Y') + 1) {
            $yil = 0;
        }

        $aracTipi = $this->metin($sonuc, 'arac_tipi');
        $yakit = $this->metin($sonuc, 'yakit_tipi');

        return [
            'plaka' => $this->plaka($this->metin($sonuc, 'plaka')),
            'marka' => $this->metin($sonuc, 'marka'),
            'model' => $this->metin($sonuc, 'model'),
            'model_yili' => $yil > 0 ? (string) $yil : '',
            'renk' => $this->renk($this->metin($sonuc, 'renk')),
            'arac_tipi' => in_array($aracTipi, self::ARAC_TIPLERI, true) ? $aracTipi : '',
            'yakit_tipi' => in_array($yakit, self::YAKIT_TIPLERI, true) ? $yakit : '',
            'sase_no' => $this->kod($this->metin($sonuc, 'sase_no'), 17),
            'motor_no' => $this->kod($this->metin($sonuc, 'motor_no'), 50),
            'ruhsat_sahibi' => $this->metin($sonuc, 'ruhsat_sahibi'),
            'muayene_bitis_tarihi' => $this->tarih($this->metin($sonuc, 'muayene_bitis_tarihi')),
        ];
    }

    private function prompt(): string
    {
        return 'Yüklenen belge bir Türkiye araç tescil belgesidir (ruhsat); fotoğraf, tarama veya PDF olabilir ve eğik çekilmiş olabilir. '
            . 'Belgeyi dikkatle oku ve aşağıdaki alanları YALNIZCA belgede açıkça okunuyorsa doldur. Okuyamadığın her alanı boş string olarak döndür; asla tahmin etme. '
            . 'plaka: (A) alanı, boşluksuz büyük harf (örnek "01GE243"). '
            . 'marka: (D.1) alanı (örnek "KIA"). '
            . 'model: (D.3) Ticari adı (örnek "SPORTAGE"); (D.2) Tipi kodunu buraya yazma. '
            . 'model_yili: (D.4) alanı, 4 haneli yıl. '
            . 'renk: (R) alanındaki rengin Türkçe sade adı; parantez içi açıklamayı at (örnek "GRİ (AYTAŞI)" ise "Gri"). '
            . 'arac_tipi: (D.5) Cinsi ve (J) sınıfına bakarak YALNIZCA şu değerlerden birini seç: binek (otomobil), kamyonet, kamyon, minibus, otobus, motosiklet, diger. Emin değilsen boş bırak. '
            . 'yakit_tipi: (P.3) Yakıt cinsine bakarak YALNIZCA şu değerlerden birini seç: dizel, benzin, lpg, elektrik, hibrit. Emin değilsen boş bırak. '
            . 'sase_no: (E) Şase No, 17 karakterli VIN; boşluk olmadan büyük harf. '
            . 'motor_no: (P.5) Motor No, boşluksuz büyük harf. '
            . 'ruhsat_sahibi: (C.1.1) soyadı/ticari unvan ile (C.1.2) adı alanlarının birleşimi; kurumsa yalnızca ticari unvan. '
            . 'muayene_bitis_tarihi: belgenin (Z.2) Diğer Bilgiler bölümünde "mua.geç.trh" veya muayene geçerlilik tarihi olarak yazan tarih; GG.AA.YYYY biçiminde. Yoksa boş bırak. '
            . 'Şu anahtarlarla JSON nesnesi döndür: plaka, marka, model, model_yili, renk, arac_tipi, yakit_tipi, sase_no, motor_no, ruhsat_sahibi, muayene_bitis_tarihi.';
    }

    private function metin(array $sonuc, string $anahtar): string
    {
        $deger = $sonuc[$anahtar] ?? '';
        if (!is_scalar($deger)) {
            return '';
        }
        return mb_substr(trim((string) $deger), 0, 200, 'UTF-8');
    }

    private function plaka(string $deger): string
    {
        $temiz = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $deger));
        if (preg_match('/^(\d{2})([A-Z]{1,3})(\d{2,4})$/', $temiz, $m) === 1) {
            return $m[1] . ' ' . $m[2] . ' ' . $m[3];
        }
        return $temiz;
    }

    private function kod(string $deger, int $max): string
    {
        $temiz = strtoupper((string) preg_replace('/[^A-Za-z0-9\-]/', '', $deger));
        return mb_substr($temiz, 0, $max);
    }

    private function renk(string $deger): string
    {
        $deger = trim((string) preg_replace('/\(.*?\)/u', '', $deger));
        if ($deger === '') {
            return '';
        }
        $deger = str_replace(['I', 'İ'], ['ı', 'i'], $deger);
        return mb_convert_case(mb_strtolower($deger, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function tarih(string $deger): string
    {
        if ($deger === '') {
            return '';
        }
        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $bicim) {
            $tarih = \DateTime::createFromFormat('!' . $bicim, $deger);
            if ($tarih && $tarih->format($bicim) === $deger) {
                return $tarih->format('d.m.Y');
            }
        }
        return '';
    }
}
