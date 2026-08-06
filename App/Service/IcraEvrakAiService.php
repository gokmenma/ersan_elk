<?php

namespace App\Service;

use App\Model\IcraDaireleriModel;

final class IcraEvrakAiService
{
    private const KESINTI_TIPLERI = ['tutar', 'net_yuzde', 'asgari_yuzde'];

    public function analiz(array $file): array
    {
        $okuyucu = new AiBelgeOkuyucuService();
        $mime = $okuyucu->dogrula($file);
        [$apiKey, $model] = $okuyucu->ayarlar();

        $userContent = $okuyucu->kullaniciIcerigi($this->prompt(), $file, $mime);
        $sonuc = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkiye icra hukuku yazışmalarını okuyan bir belge analiz asistanısın. Yalnızca belgede açıkça yazan bilgileri raporlarsın, tahmin veya uydurma yapmazsın. Belgede olmayan alanı boş bırakırsın. Yalnızca geçerli JSON üretirsin.',
            $userContent,
            0.0
        );

        $icraDairesi = $this->daireEslestir($this->metin($sonuc, 'icra_dairesi'));
        $kesintiTipi = (string) ($sonuc['kesinti_tipi'] ?? '');
        if (!in_array($kesintiTipi, self::KESINTI_TIPLERI, true)) {
            $kesintiTipi = '';
        }

        $kesintiOrani = $this->sayi($sonuc['kesinti_orani'] ?? null);
        if ($kesintiOrani !== null && ($kesintiOrani <= 0 || $kesintiOrani > 100)) {
            $kesintiOrani = null;
        }
        if ($kesintiTipi === '' && $kesintiOrani !== null) {
            $kesintiTipi = 'net_yuzde';
        }
        if ($kesintiTipi !== 'tutar' && $kesintiTipi !== '' && $kesintiOrani === null) {
            $kesintiOrani = 25.0;
        }

        return [
            'icra_dairesi' => $icraDairesi,
            'dosya_no' => $this->metin($sonuc, 'dosya_no'),
            'alacakli' => $this->metin($sonuc, 'alacakli'),
            'borclu' => $this->metin($sonuc, 'borclu'),
            'toplam_borc' => $this->ondalik($this->sayi($sonuc['toplam_borc'] ?? null)),
            'kesinti_tipi' => $kesintiTipi,
            'kesinti_orani' => $this->ondalik($kesintiOrani),
            'aylik_kesinti_tutari' => $this->ondalik($this->sayi($sonuc['aylik_kesinti_tutari'] ?? null)),
            'iban' => $this->iban($this->metin($sonuc, 'iban')),
            'hesap_bilgileri' => $this->metin($sonuc, 'hesap_bilgileri'),
            'baslangic_tarihi' => $this->tarih($this->metin($sonuc, 'tebligat_tarihi')),
            'aciklama' => $this->metin($sonuc, 'aciklama'),
        ];
    }

    private function prompt(): string
    {
        return 'Yüklenen belge bir icra dairesinden gönderilmiş maaş haczi / icra kesinti müzekkeresi ya da haciz ihbarnamesidir. '
            . 'Belgeyi dikkatle oku ve aşağıdaki alanları YALNIZCA belgede açıkça yazıyorsa doldur. Okuyamadığın veya belgede bulunmayan her alanı boş string ya da null olarak döndür; asla tahmin etme. '
            . 'icra_dairesi: müzekkereyi gönderen icra dairesinin/müdürlüğünün belgede yazan tam adı (örnek "Kahramanmaraş 2. İcra Dairesi"). Şehir ve varsa daire numarasını koru, "T.C." ön ekini yazma. '
            . 'dosya_no: dosyanın esas numarası (örnek "2025/16978 ESAS"). Belgede "Dosya No", "Esas No" veya "E." olarak geçer. Yalnızca numarayı ve varsa ESAS ibaresini yaz; kurum adını buraya yazma. '
            . 'alacakli: alacaklı taraf olan kurum, banka veya kişinin tam adı. Belgede "Alacaklı" başlığı altında yazar. Vekil/avukat adını değil, asıl alacaklıyı yaz. '
            . 'borclu: borçlu personelin adı soyadı. '
            . 'toplam_borc: takibe konu toplam borç/alacak tutarı; yalnızca sayı olarak, binlik ayırıcı olmadan, ondalık ayırıcı nokta ile döndür (örnek 193094.40). Para birimi yazma. '
            . 'kesinti_tipi: maaştan yapılacak kesintinin türü. Belgede maaşın belirli bir oranının (örneğin 1/4, dörtte biri, %25) haczedildiği yazıyorsa "net_yuzde"; net asgari ücret üzerinden bir oran belirtiliyorsa "asgari_yuzde"; sabit bir aylık tutar belirtiliyorsa "tutar" döndür. Anlaşılmıyorsa boş bırak. '
            . 'kesinti_orani: oran yüzde olarak sayı ile (1/4 ise 25, 1/2 ise 50, 1/3 ise 33). Oran belirtilmemişse null. '
            . 'aylik_kesinti_tutari: belgede sabit aylık kesinti tutarı yazıyorsa sayı olarak, yoksa null. '
            . 'iban: icra dairesinin ödeme yapılacak IBAN numarası; TR ile başlayan 26 haneli numara. Yoksa boş bırak. '
            . 'hesap_bilgileri: ödemenin yapılacağı banka ve şube bilgisi (örnek "Vakıfbank Adliye Şubesi"). Yoksa boş bırak. '
            . 'tebligat_tarihi: belgenin düzenlenme veya tebliğ tarihi. GG.AA.YYYY biçiminde döndür. Yoksa boş bırak. '
            . 'aciklama: dosyaya not olarak eklenecek, belgeden çıkan en fazla iki cümlelik özet. '
            . 'Şu anahtarlarla JSON nesnesi döndür: icra_dairesi, dosya_no, alacakli, borclu, toplam_borc, kesinti_tipi, kesinti_orani, aylik_kesinti_tutari, iban, hesap_bilgileri, tebligat_tarihi, aciklama.';
    }

    private function daireEslestir(string $ad): string
    {
        if ($ad === '') {
            return '';
        }
        $normal = $this->normalize($ad);
        foreach ((new IcraDaireleriModel())->where('aktif', 1) as $daire) {
            if ($this->normalize((string) $daire->daire_adi) === $normal) {
                return (string) $daire->daire_adi;
            }
        }
        return $ad;
    }

    private function normalize(string $metin): string
    {
        $metin = str_replace(['I', 'İ'], ['ı', 'i'], $metin);
        $metin = mb_strtolower($metin, 'UTF-8');
        $metin = (string) preg_replace('/\b(t\.?c\.?)\b/u', '', $metin);
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $metin));
    }

    private function metin(array $sonuc, string $anahtar): string
    {
        $deger = $sonuc[$anahtar] ?? '';
        if (!is_scalar($deger)) {
            return '';
        }
        return mb_substr(trim((string) $deger), 0, 500, 'UTF-8');
    }

    private function ondalik(?float $deger): string
    {
        return $deger === null ? '' : number_format($deger, 2, '.', '');
    }

    private function sayi($deger): ?float
    {
        if ($deger === null || $deger === '' || is_array($deger)) {
            return null;
        }
        if (is_numeric($deger)) {
            return (float) $deger > 0 ? round((float) $deger, 2) : null;
        }

        $temiz = (string) preg_replace('/[^\d.,]/u', '', (string) $deger);
        if ($temiz === '') {
            return null;
        }
        if (str_contains($temiz, '.') && str_contains($temiz, ',')) {
            $temiz = str_replace('.', '', $temiz);
            $temiz = str_replace(',', '.', $temiz);
        } elseif (str_contains($temiz, ',')) {
            $temiz = str_replace(',', '.', $temiz);
        }
        $sayi = (float) $temiz;
        return $sayi > 0 ? round($sayi, 2) : null;
    }

    private function iban(string $deger): string
    {
        $temiz = strtoupper((string) preg_replace('/\s+/', '', $deger));
        return preg_match('/^TR\d{24}$/', $temiz) === 1 ? $temiz : '';
    }

    private function tarih(string $deger): string
    {
        if ($deger === '') {
            return '';
        }
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'd-m-Y'] as $bicim) {
            $tarih = \DateTime::createFromFormat($bicim, $deger);
            if ($tarih && $tarih->format($bicim) === $deger) {
                return $tarih->format('d.m.Y');
            }
        }
        return '';
    }
}
