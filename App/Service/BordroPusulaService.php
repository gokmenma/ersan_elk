<?php
namespace App\Service;

use App\Model\BordroPersonelModel;
use DomainException;

/** Parasal hesap yapmaz: kayıtlı resmî banka dökümünü muhasebe şablonuna taşır. */
final class BordroPusulaService
{
    public static function olustur(object $row, object $donem, BordroPersonelModel $model): array
    {
        $detay = json_decode((string) ($row->hesaplama_detay ?? ''), true);
        if (empty($row->hesaplama_tarihi) || !is_array($detay) || empty($detay['matrahlar'])
            || (empty($detay['ortak_sonuc']['gosterim']) && empty($detay['odeme_dagilimi']))) {
            throw new DomainException('Hesaplanmadı: geçerli kayıtlı bordro dökümü bulunamadı.');
        }
        $hesap = $model->hesaplaOrtakGosterimDegerleri($row, $donem, (float) ($detay['parametreler']['asgari_ucret_net'] ?? 0));
        $ozet = $model->getMuhasebeOdemeOzeti($hesap);
        $resmi = BordroYayinIcerikService::olustur($row, $donem, $hesap, $ozet);
        $yasal = 0;
        foreach (['sgk_isci', 'issizlik_isci', 'gelir_vergisi', 'damga_vergisi'] as $key) {
            $yasal += BordroYayinIcerikService::kurus((float) ($row->$key ?? 0));
        }
        $ozel = BordroYayinIcerikService::kurus((float) $ozet['bankadan_dusulen_kesinti']);
        // Brüt sunum toplamı yalnız kayıtlı banka neti ve kayıtlı kesintilerin mutabakatıdır.
        return [
            'resmi' => $resmi, 'detay' => $detay,
            'yasal_kesinti_kurus' => $yasal, 'ozel_kesinti_kurus' => $ozel,
            'kazanc_kurus' => $resmi['banka_net_kurus'] + $yasal + $ozel,
            'gorev' => (string) ($detay['gorev'] ?? $row->gorev ?? ''),
            'departman' => (string) ($detay['departman'] ?? $row->departman ?? ''),
        ];
    }

    /**
     * Personel yayınına mühürlenecek resmî pusula özeti.
     * Tam hesaplama_detay kaydı elden/teknik iç veriler barındırabildiğinden yayınlanmaz.
     */
    public static function yayinIcerigi(object $row, object $donem, object $firma, BordroPersonelModel $model): array
    {
        $pusula = self::olustur($row, $donem, $model);
        $detay = $pusula['detay'];
        $matrahlar = $detay['matrahlar'] ?? [];
        $indirimler = $detay['indirimler'] ?? [];

        $yasalKesintiler = [];
        foreach ([
            'SGK işçi payı' => 'sgk_isci',
            'İşsizlik sigortası işçi payı' => 'issizlik_isci',
            'Gelir vergisi' => 'gelir_vergisi',
            'Damga vergisi' => 'damga_vergisi',
        ] as $etiket => $alan) {
            $kurus = BordroYayinIcerikService::kurus((float) ($row->$alan ?? 0));
            if ($kurus !== 0) $yasalKesintiler[] = ['etiket' => $etiket, 'kurus' => $kurus];
        }

        $tc = preg_replace('/\D/', '', (string) ($row->tc_kimlik_no ?? ''));
        $maskeliTc = strlen($tc) >= 4 ? str_repeat('*', max(0, strlen($tc) - 4)) . substr($tc, -4) : $tc;
        $sunumDetay = [
            'matrahlar' => $matrahlar,
            'indirimler' => $indirimler,
            'isyeri' => $detay['isyeri'] ?? [],
            'gun_tutarlari' => $detay['gun_tutarlari'] ?? [],
        ];

        return $pusula['resmi'] + [
            'icerik_turu' => 'ucret_hesap_pusulasi',
            'icerik_surumu' => 1,
            'kazanc_toplami_kurus' => $pusula['kazanc_kurus'],
            'yasal_kesinti_toplami_kurus' => $pusula['yasal_kesinti_kurus'],
            'ozel_kesinti_toplami_kurus' => $pusula['ozel_kesinti_kurus'],
            'toplam_kesinti_kurus' => $pusula['yasal_kesinti_kurus'] + $pusula['ozel_kesinti_kurus'],
            'yasal_kesintiler' => $yasalKesintiler,
            'resmi_matrahlar' => [
                'sgk_matrahi_kurus' => BordroYayinIcerikService::kurus((float) ($matrahlar['sgk_matrahi'] ?? 0)),
                'gelir_vergisi_matrahi_kurus' => BordroYayinIcerikService::kurus((float) ($matrahlar['gelir_vergisi_matrahi'] ?? 0)),
                'onceki_kumulatif_kurus' => BordroYayinIcerikService::kurus((float) ($matrahlar['onceki_kumulatif'] ?? 0)),
                'yeni_kumulatif_kurus' => BordroYayinIcerikService::kurus((float) ($matrahlar['yeni_kumulatif'] ?? 0)),
            ],
            'calisma_ve_izin_gunleri' => [
                'ssk' => (int) ($matrahlar['ssk_gunu'] ?? 0),
                'normal' => (int) ($matrahlar['normal_gun'] ?? 0),
                'hafta_tatili' => (int) ($matrahlar['hafta_tatili_gunu'] ?? 0),
                'genel_tatil' => (int) ($matrahlar['genel_tatil_gunu'] ?? 0),
                'ucretli_izin' => (int) ($matrahlar['ucretli_izin_gunu'] ?? 0),
                'ucretsiz_izin' => (int) ($matrahlar['ucretsiz_izin_gunu'] ?? 0),
                'rapor' => (int) ($matrahlar['rapor_gunu'] ?? 0),
            ],
            'vergi_indirimleri' => [
                'asgari_ucret_istisna_gv_kurus' => BordroYayinIcerikService::kurus((float) ($indirimler['asgari_ucret_istisna_gv'] ?? 0)),
                'asgari_ucret_istisna_dv_kurus' => BordroYayinIcerikService::kurus((float) ($indirimler['asgari_ucret_istisna_dv'] ?? 0)),
            ],
            // PDF yalnız bu değişmez ve sunuma özel alt kümeden tekrar üretilebilir.
            'resmi_pusula' => [
                'personel' => [
                    'adi_soyadi' => (string) ($row->adi_soyadi ?? ''),
                    'tc_kimlik_no' => $maskeliTc,
                    'sgk_no' => (string) ($row->sgk_no ?? ''),
                    'ise_giris_tarihi' => (string) ($row->ise_giris_tarihi ?? ''),
                    'isten_cikis_tarihi' => (string) ($row->isten_cikis_tarihi ?? ''),
                ],
                'donem' => [
                    'donem_adi' => (string) $donem->donem_adi,
                    'baslangic_tarihi' => (string) $donem->baslangic_tarihi,
                    'bitis_tarihi' => (string) $donem->bitis_tarihi,
                ],
                'firma' => [
                    'firma_adi' => (string) ($firma->firma_adi ?? ''),
                    'adres' => (string) ($firma->adres ?? ''),
                    'web_sitesi' => (string) ($firma->web_sitesi ?? ''),
                    'sgk_no' => (string) ($firma->sgk_no ?? ''),
                    'vergi_no' => (string) ($firma->vergi_no ?? ''),
                    'mersis_no' => (string) ($firma->mersis_no ?? ''),
                    'ticaret_sicil_no' => (string) ($firma->ticaret_sicil_no ?? ''),
                ],
                'pusula' => [
                    'resmi' => $pusula['resmi'],
                    'detay' => $sunumDetay,
                    'yasal_kesinti_kurus' => $pusula['yasal_kesinti_kurus'],
                    'ozel_kesinti_kurus' => $pusula['ozel_kesinti_kurus'],
                    'kazanc_kurus' => $pusula['kazanc_kurus'],
                    'gorev' => $pusula['gorev'],
                    'departman' => $pusula['departman'],
                ],
            ],
        ];
    }

    public static function pdf(): \Mpdf\Mpdf
    {
        $uid = function_exists('posix_geteuid') ? (string) posix_geteuid() : substr(hash('sha256', __DIR__), 0, 12);
        $dir = sys_get_temp_dir() . '/ersan-pusula-pdf-' . $uid;
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('PDF geçici dizini oluşturulamadı.');
        }
        $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'dejavusans',
            'margin_left' => 7, 'margin_right' => 7, 'margin_top' => 9, 'margin_bottom' => 10, 'tempDir' => $dir]);
        $pdf->SetTitle('Ücret Hesap Pusulası');
        return $pdf;
    }

    public static function html(object $row, object $donem, object $firma, array $pusula): string
    {
        ob_start();
        require dirname(__DIR__, 2) . '/views/bordro/partials/ucret-hesap-pusulasi.php';
        return (string) ob_get_clean();
    }
}
