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
        return [
            '601' => ['name' => 'Yapım İşleri ile Bu İşlerle Birlikte İfa Edilen Mühendislik-Mimarlık ve Etüt-Proje Hizmetleri', 'rate' => '4/10'],
            '602' => ['name' => 'Etüt, Plan-Proje, Danışmanlık, Denetim ve Benzeri Hizmetler', 'rate' => '9/10'],
            '603' => ['name' => 'Makine, Teçhizat, Demirbaş ve Taşıtlara Ait Tadil, Bakım ve Onarım Hizmetleri', 'rate' => '7/10'],
            '604' => ['name' => 'Yemek Servis ve Organizasyon Hizmetleri', 'rate' => '5/10'],
            '605' => ['name' => 'İşgücü Temin Hizmetleri', 'rate' => '9/10'],
            '606' => ['name' => 'Özel Güvenlik Hizmeti', 'rate' => '9/10'],
            '608' => ['name' => 'Temizlik Hizmeti', 'rate' => '7/10'],
            '609' => ['name' => 'Taşımacılık Hizmetleri', 'rate' => '2/10'],
            '624' => ['name' => 'Demir-Çelik Ürünlerinin Teslimi', 'rate' => '4/10'],
        ];
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
