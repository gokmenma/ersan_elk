<?php

namespace App\Service;

use App\Model\FirmaModel;

final class ManuelFaturaAiService
{
    public function analiz(array $file, int $firmId): array
    {
        $okuyucu = new AiBelgeOkuyucuService();
        $mime = $okuyucu->dogrula($file);
        [$apiKey, $model] = $okuyucu->ayarlar();
        $model = $this->faturaModeli($firmId, $model);

        $userContent = $okuyucu->kullaniciIcerigi($this->prompt($firmId), $file, $mime);
        $sonuc = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkiye vergi mevzuatına göre düzenlenmiş fatura, e-arşiv fatura, serbest meslek makbuzu, gider pusulası ve makbuzları okuyan bir muhasebe belge analiz asistanısın. Yalnızca belgede açıkça yazan bilgileri raporlarsın, tahmin veya uydurma yapmazsın. Belgede olmayan alanı boş bırakırsın. Yalnızca geçerli JSON üretirsin.',
            $userContent,
            0.0
        );

        $servis = new ManuelFaturaService();
        $uyarilar = [];

        $faturaNoHam = trim((string) (is_scalar($sonuc['fatura_no'] ?? null) ? $sonuc['fatura_no'] : ''));
        if (mb_strlen($faturaNoHam, 'UTF-8') > 16) {
            $uyarilar[] = 'Belge numarası 16 karakterden uzun (' . $faturaNoHam . '); kısaltılarak yazıldı, kontrol ediniz.';
        }

        $paraBirimi = strtoupper(trim((string) (is_scalar($sonuc['para_birimi'] ?? null) ? $sonuc['para_birimi'] : '')));
        if ($paraBirimi !== '' && !in_array($paraBirimi, ['TRY', 'TL'], true)) {
            $uyarilar[] = 'Belge ' . $paraBirimi . ' cinsinden görünüyor; manuel kayıt yalnızca TL desteklediği için tutarları TL karşılığı ile giriniz.';
        }

        $satirlar = [];
        foreach ((array) ($sonuc['satirlar'] ?? []) as $satir) {
            if (!is_array($satir)) {
                continue;
            }
            $miktar = $servis->sayi($satir['miktar'] ?? null) ?: 1.0;
            $birimFiyat = $servis->sayi($satir['birim_fiyat'] ?? null);
            $satirTutari = $servis->sayi($satir['satir_tutari'] ?? null);
            if ($birimFiyat === null && $satirTutari !== null) {
                $birimFiyat = $satirTutari / $miktar;
            }
            $satirlar[] = [
                'urun_hizmet_adi' => $satir['urun_hizmet_adi'] ?? '',
                'miktar' => $miktar,
                'birim' => $satir['birim'] ?? 'C62',
                'birim_fiyat' => $birimFiyat,
                'iskonto_orani' => $servis->sayi($satir['iskonto_orani'] ?? null),
                'iskonto_tutari' => $servis->sayi($satir['iskonto_tutari'] ?? null),
                'kdv_orani' => $servis->sayi($satir['kdv_orani'] ?? null) ?? 0.0,
            ];
        }
        $satirlar = $this->kdvDahilFiyatlariCoz($satirlar, $servis->sayi($sonuc['mal_hizmet_toplami'] ?? null), $servis->sayi($sonuc['odenecek_tutar'] ?? null));

        $tur = strtoupper((string) ($sonuc['belge_turu'] ?? ''));
        $stopajOrani = $servis->sayi($sonuc['stopaj_orani'] ?? null);
        $stopajTutari = $servis->sayi($sonuc['stopaj_tutari'] ?? null);
        $matrahBelge = $servis->sayi($sonuc['mal_hizmet_toplami'] ?? null);
        if ($stopajOrani === null && $stopajTutari && $matrahBelge) {
            $stopajOrani = round($stopajTutari / $matrahBelge * 100, 2);
        }
        if (in_array($tur, ['SMM', 'GIDER_PUSULASI'], true) && $stopajOrani === null) {
            $uyarilar[] = 'Stopaj oranı belgeden okunamadı; lütfen stopaj oranını giriniz.';
        }

        $notlar = array_filter([
            $this->deger($sonuc, 'plaka') !== '' ? 'Plaka: ' . strtoupper(str_replace(' ', '', $this->deger($sonuc, 'plaka'))) : '',
            $this->deger($sonuc, 'odeme_sekli') !== '' ? 'Ödeme Şekli: ' . $this->deger($sonuc, 'odeme_sekli') : '',
            $this->deger($sonuc, 'aciklama'),
        ]);

        $normal = $servis->normalize([
            'belge_turu' => $tur,
            'fatura_profili' => $sonuc['fatura_profili'] ?? '',
            'fatura_no' => $faturaNoHam,
            'fatura_tarihi' => $this->deger($sonuc, 'fatura_tarihi'),
            'ettn' => $this->deger($sonuc, 'ettn'),
            'siparis_no' => $this->deger($sonuc, 'siparis_no'),
            'alici_unvan' => $this->deger($sonuc, 'satici_unvan'),
            'alici_vkn_tckn' => $this->deger($sonuc, 'satici_vkn_tckn'),
            'alici_vergi_dairesi' => $this->deger($sonuc, 'satici_vergi_dairesi'),
            'alici_adres' => $this->deger($sonuc, 'satici_adres'),
            'alici_il' => $this->deger($sonuc, 'satici_il'),
            'alici_ilce' => $this->deger($sonuc, 'satici_ilce'),
            'notlar' => implode(' | ', $notlar),
            'stopaj_orani' => $stopajOrani,
            'satirlar' => $satirlar,
        ]);
        $header = $normal['header'];
        $lines = $normal['lines'];

        $vkn = $header['alici_vkn_tckn'];
        if ($vkn !== '' && !in_array(strlen($vkn), [10, 11], true)) {
            $uyarilar[] = 'Okunan VKN/TCKN (' . $vkn . ') 10 veya 11 haneli değil; kontrol ediniz.';
        }
        $firmaVkn = $this->firmaVkn($firmId);
        if ($vkn !== '' && $firmaVkn !== '' && $vkn === $firmaVkn) {
            $uyarilar[] = 'Okunan satıcı VKN firmamızın VKN\'si ile aynı; satıcı bilgilerini kontrol ediniz.';
        }
        if ($header['fatura_tarihi'] === '') {
            $uyarilar[] = 'Belge tarihi okunamadı.';
        }

        $belgeToplam = [
            'mal_hizmet_toplami' => $matrahBelge,
            'kdv_toplami' => $servis->sayi($sonuc['kdv_toplami'] ?? null),
            'stopaj_tutari' => $stopajTutari,
            'odenecek_tutar' => $servis->sayi($sonuc['odenecek_tutar'] ?? null),
        ];

        $hesap = ['totals' => null];
        if ($lines !== []) {
            try {
                $hesap = $servis->hesapla($header, $lines);
                $uyarilar = array_merge($uyarilar, $servis->karsilastir($belgeToplam, $hesap['totals']));
            } catch (\Throwable $e) {
                error_log('Manuel fatura AI hesaplama hatası: ' . $e->getMessage());
                $uyarilar[] = 'Kalemlerden toplam hesaplanamadı; kalemleri kontrol ediniz.';
            }
        } else {
            $uyarilar[] = 'Belgeden kalem okunamadı; kalemleri elle giriniz.';
        }

        $odenecek = (float) ($hesap['totals']['odenecek_tutar'] ?? $belgeToplam['odenecek_tutar'] ?? 0);

        $yuvarla = static function (?array $dizi, int $hane = 2): ?array {
            if ($dizi === null) {
                return null;
            }
            return array_map(static fn($v) => is_float($v) ? round($v, $hane) : $v, $dizi);
        };

        return [
            'header' => array_merge($header, [
                'fatura_tarihi' => $header['fatura_tarihi'] !== '' ? date('d.m.Y', strtotime($header['fatura_tarihi'])) : '',
            ]),
            'lines' => array_map(static fn(array $l) => $yuvarla($l, 4), $lines),
            'belge_toplam' => $yuvarla($belgeToplam),
            'hesaplanan' => $yuvarla($hesap['totals']),
            'uyarilar' => array_values(array_unique($uyarilar)),
            'eslesen_cari' => $servis->cariOnizleme($header),
            'mukerrer' => $header['fatura_tarihi'] !== '' ? $servis->mukerrer($firmId, $header, $odenecek) : null,
        ];
    }

    private function kdvDahilFiyatlariCoz(array $satirlar, ?float $matrahBelge, ?float $odenecekBelge): array
    {
        $hariçToplam = 0.0;
        $dahilMatrah = 0.0;
        $dahilToplam = 0.0;
        foreach ($satirlar as $s) {
            $tutar = (float) $s['miktar'] * (float) ($s['birim_fiyat'] ?? 0) - (float) ($s['iskonto_tutari'] ?? 0);
            $hariçToplam += $tutar;
            $dahilMatrah += $tutar / (1 + (float) $s['kdv_orani'] / 100);
            $dahilToplam += $tutar;
        }
        $hedef = $matrahBelge;
        $kdvDahil = $hedef !== null
            && abs($hariçToplam - $hedef) > 0.05
            && abs($dahilMatrah - $hedef) <= 0.05;
        if (!$kdvDahil && $hedef === null && $odenecekBelge !== null) {
            $kdvDahil = abs($dahilToplam - $odenecekBelge) <= 0.05 && $dahilMatrah < $dahilToplam;
        }

        foreach ($satirlar as &$s) {
            $fiyat = $s['birim_fiyat'];
            $iskontoTutari = $s['iskonto_tutari'];
            if ($kdvDahil && $fiyat !== null && $s['kdv_orani'] > 0) {
                $bolen = 1 + $s['kdv_orani'] / 100;
                $fiyat = $fiyat / $bolen;
                $iskontoTutari = $iskontoTutari !== null ? $iskontoTutari / $bolen : null;
            }
            if ($s['iskonto_orani'] === null && $iskontoTutari && $fiyat) {
                $s['iskonto_orani'] = round($iskontoTutari / ($s['miktar'] * $fiyat) * 100, 2);
            }
            $s['birim_fiyat'] = $fiyat !== null ? round($fiyat, 4) : null;
            unset($s['iskonto_tutari']);
        }
        unset($s);
        return $satirlar;
    }

    private function prompt(int $firmId): string
    {
        $firma = $this->firma($firmId);
        $alici = $firma['unvan'] !== ''
            ? 'Belgeyi alan (alıcı / "SAYIN" bölümündeki) firma bizim firmamızdır: "' . $firma['unvan'] . '"' . ($firma['vkn'] !== '' ? ' VKN ' . $firma['vkn'] : '') . '. Bu firmayı ASLA satıcı olarak yazma. '
            : 'Belgede "SAYIN" veya "Alıcı" bölümünde yazan firma bizim firmamızdır; onu satıcı olarak yazma. ';

        return 'Yüklenen belge firmamıza gelen bir alış belgesidir: fatura, e-arşiv fatura, e-fatura PDF çıktısı, serbest meslek makbuzu, gider pusulası veya tahsilat/ödeme makbuzu olabilir. Formatı her seferinde farklı olabilir. '
            . 'Belgeyi dikkatle oku ve aşağıdaki alanları YALNIZCA belgede açıkça yazıyorsa doldur. Okuyamadığın veya belgede bulunmayan her alanı boş string ya da null olarak döndür; asla tahmin etme. '
            . $alici
            . 'belge_turu: "EFATURA" (üzerinde e-Fatura logosu/ibaresi ve ETTN olan fatura), "EARSIV" (e-Arşiv Fatura ibaresi olan), "KAGIT" (matbu/kâğıt fatura veya diğer faturalar), "SMM" (serbest meslek makbuzu), "GIDER_PUSULASI" (gider pusulası), "MAKBUZ" (KDV\'siz tahsilat/ödeme makbuzu, aidat, harç, bağış vb.). '
            . 'fatura_profili: belgede "Senaryo" veya "Profil" olarak TEMELFATURA, TICARIFATURA veya EARSIVFATURA yazıyorsa onu yaz, yoksa boş. '
            . 'fatura_no: belge/fatura/makbuz numarası (örnek "CKF2026000062689" veya seri+sıra "A 012345"). Sipariş numarasını buraya yazma. '
            . 'fatura_tarihi: belge (fatura/düzenleme) tarihi, GG.AA.YYYY biçiminde. '
            . 'ettn: belgede ETTN veya UUID yazıyorsa 36 karakterlik değeri aynen yaz, yoksa boş. '
            . 'siparis_no: sipariş numarası varsa. '
            . 'para_birimi: tutarların para birimi (TRY, USD, EUR). TL ise "TRY". '
            . 'satici_unvan, satici_vkn_tckn, satici_vergi_dairesi, satici_adres, satici_il, satici_ilce: belgeyi DÜZENLEYEN satıcı/hizmet veren kişi veya kurumun bilgileri. Gider pusulasında malı satan/hizmeti veren kişinin adı ve TCKN\'si. VKN/TCKN yalnızca rakam. '
            . 'plaka: belgede araç plakası yazıyorsa (örnek "06CMS494"), yoksa boş. '
            . 'odeme_sekli: belgede ödeme şekli yazıyorsa (örnek "Kartlı Ödeme", "Nakit"), yoksa boş. '
            . 'satirlar: belgedeki mal/hizmet kalemleri dizisi. Her kalem için urun_hizmet_adi, miktar, birim (UBL kodu: C62 adet, KGM kilogram, LTR litre, MTR metre, MTK metrekare, MTQ metreküp, TNE ton, HUR saat, DAY gün, MON ay, BX kutu, PA paket, SET set; bilinmiyorsa C62), birim_fiyat, iskonto_orani (yüzde), iskonto_tutari, kdv_orani (yüzde, örnek 20), satir_tutari (KDV hariç kalem tutarı). '
            . 'Birim fiyatı belgede yazdığı gibi (KDV dahil ya da hariç, nasıl yazıyorsa) aynen yaz; KDV ekleme veya çıkarma hesabı YAPMA. '
            . 'KALEM KURALLARI: Kalem tablosundaki her sıra numarasını tek tek incele ve hiçbir gerçek kalemi atlama; tablonun son satırlarındaki hizmet bedeli, komisyon, kargo, nakliye gibi kalemler de kalemdir. '
            . '"KDV Tutarı" alt satırları, ara toplam satırları ve "Kartlı Ödeme Hizmetine Konu Toplam Tutar" gibi yalnızca bilgi amaçlı olup Mal Hizmet Toplam Tutarına dahil edilmeyen satırları kalem olarak YAZMA. '
            . 'Kontrol: kalemlerin miktar × birim fiyat toplamı belgedeki "Mal Hizmet Toplam Tutarı" ile eşit olmalıdır; eşit değilse atladığın veya fazladan aldığın satırı bulup düzelt. '
            . 'Serbest meslek makbuzunda brüt ücret kalem olarak yazılır; gider pusulasında alınan mal/hizmet kalem olarak yazılır. '
            . 'mal_hizmet_toplami: belgede "Mal Hizmet Toplam Tutarı", "Ara Toplam" veya "KDV Matrahı" olarak yazan KDV hariç toplam (serbest meslek makbuzu ve gider pusulasında brüt ücret); kalem satırlarından değil, toplamlar bölümünden oku. '
            . 'kdv_toplami: hesaplanan toplam KDV. '
            . 'stopaj_orani ve stopaj_tutari: gelir vergisi stopajı / tevkifatı varsa (serbest meslek makbuzu ve gider pusulasında genellikle %20), yoksa null. '
            . 'odenecek_tutar: belgedeki ödenecek / vergiler dahil toplam / net ödenen tutar. '
            . 'aciklama: belgeye özgü kısa bir not (örnek dönem, sözleşme no, abone no) varsa en fazla bir kısa cümle; kanun, mevzuat, hizmet bedeli açıklaması gibi standart/yasal bilgilendirme metinlerini YAZMA. Yoksa boş. '
            . 'Tüm tutar ve oranları JSON sayısı olarak, binlik ayırıcı olmadan, ondalık ayırıcı nokta ile döndür (örnek 2740.00). Para birimi simgesi yazma. '
            . 'Şu anahtarlarla JSON nesnesi döndür: belge_turu, fatura_profili, fatura_no, fatura_tarihi, ettn, siparis_no, para_birimi, satici_unvan, satici_vkn_tckn, satici_vergi_dairesi, satici_adres, satici_il, satici_ilce, plaka, odeme_sekli, satirlar, mal_hizmet_toplami, kdv_toplami, stopaj_orani, stopaj_tutari, odenecek_tutar, aciklama.';
    }

    private function faturaModeli(int $firmId, string $varsayilan): string
    {
        $settings = (new \App\Model\SettingsModel())->getAllSettingsAsKeyValue($firmId);
        $model = trim((string) ($settings['fatura_ai_model'] ?? ($_ENV['FATURA_AI_MODEL'] ?? 'gpt-5.4')));
        return str_starts_with($model, 'gpt-') ? $model : $varsayilan;
    }

    private function firma(int $firmId): array
    {
        try {
            $firma = (new FirmaModel())->getFirma($firmId);
        } catch (\Throwable $e) {
            error_log('Manuel fatura firma okuma hatası: ' . $e->getMessage());
            $firma = null;
        }
        $unvan = trim((string) ($firma->firma_unvan ?? '')) !== '' ? (string) $firma->firma_unvan : (string) ($firma->firma_adi ?? '');
        return ['unvan' => trim($unvan), 'vkn' => preg_replace('/\D/', '', (string) ($firma->vergi_no ?? ''))];
    }

    private function firmaVkn(int $firmId): string
    {
        return $this->firma($firmId)['vkn'];
    }

    private function deger(array $sonuc, string $anahtar): string
    {
        $deger = $sonuc[$anahtar] ?? '';
        return is_scalar($deger) ? trim((string) $deger) : '';
    }
}
