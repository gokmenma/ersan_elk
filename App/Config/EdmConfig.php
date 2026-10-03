<?php
namespace App\Config;

class EdmConfig
{
    // EDM SOAP WSDL Endpoints
    const TEST_WSDL_URL = 'https://test.edmbilisim.com.tr/EFaturaEDM21ea/EFaturaEDM.svc?wsdl';
    const LIVE_WSDL_URL = 'https://efatura.edmbilisim.com.tr/EFaturaEDM/EFaturaEDM.svc?wsdl';

    // Belge ve Profil Tipleri
    const PROFILE_TICARIFATURA = 'TICARIFATURA';
    const PROFILE_TEMELFATURA  = 'TEMELFATURA';
    const PROFILE_EARSIVFATURA = 'EARSIVFATURA';
    const PROFILE_KAMU         = 'KAMU';
    const PROFILE_IHRACAT      = 'IHRACAT';

    // Fatura Tipleri
    const TYPE_SATIS        = 'SATIS';
    const TYPE_IADE         = 'IADE';
    const TYPE_TEVKIFAT     = 'TEVKIFAT';
    const TYPE_ISTISNA      = 'ISTISNA';
    const TYPE_OZELMATRAH   = 'OZELMATRAH';
    const TYPE_IHRACKAYITLI = 'IHRACKAYITLI';

    // UN/ECE Standart Ölçü Birimleri
    public static function getUnitCodes(): array
    {
        return [
            'C62' => 'Adet',
            'KGM' => 'Kilogram',
            'MTR' => 'Metre',
            'MTK' => 'Metrekare',
            'MTQ' => 'Metreküp',
            'LTR' => 'Litre',
            'GRM' => 'Gram',
            'SET' => 'Set',
            'BX'  => 'Kutu',
            'PA'  => 'Paket',
            'HUR' => 'Saat',
            'DAY' => 'Gün',
            'MON' => 'Ay',
            'ANN' => 'Yıl',
        ];
    }

    // Yaygın KDV Tevkifat Kodları
    public static function getTevkifatCodes(): array
    {
        $codes = [];
        foreach (\App\Service\InvoiceValidationService::codes('WithholdingTaxTypeWithPercent') as $pair) {
            $code = substr($pair, 0, 3);
            if (!in_array($code, \App\Service\InvoiceValidationService::codes('WithholdingTaxType'), true)) continue;
            $codes[$code . '|' . substr($pair, 3)] = ['name' => 'Tevkifat ' . $code, 'rate' => substr($pair, 3) . '/100'];
        }
        return $codes;
    }

    // Yaygın KDV İstisna Kodları
    public static function getIstisnaCodes(): array
    {
        return [
            '301' => '11/1-a Mal İhracatı',
            '302' => '11/1-a Hizmet İhracatı',
            '303' => '11/1-a Roaming Hizmetleri',
            '350' => 'Diğerleri',
            '250' => 'Transit ve Türkiye ile Yabancı Ülkeler Arasında Yapılan Taşımacılık İşleri',
        ];
    }
}
