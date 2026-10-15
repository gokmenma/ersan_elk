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
