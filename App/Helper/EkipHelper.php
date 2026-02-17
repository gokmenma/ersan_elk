<?php
namespace App\Helper;

use App\Model\SettingsModel;

class EkipHelper
{
    /**
     * Verilen ekip numarasının, belirli bir tab/rapor türü için tanımlanan aralıkta olup olmadığını kontrol eder.
     * 
     * @param int|string $teamNo Ekip numarası
     * @param string $tab Rapor tabı (okuma, kesme, sokme_takma, muhurleme, kacakkontrol)
     * @param SettingsModel|null $SettingsModel Opsiyonel settings model instance
     * @return bool
     */
    public static function isTeamInTabRange($teamNo, string $tab, ?SettingsModel $SettingsModel = null): bool
    {
        if (empty($teamNo))
            return false;
        $teamNo = (int) $teamNo;

        if (!$SettingsModel) {
            $SettingsModel = new SettingsModel();
        }

        $allSettings = $SettingsModel->getAllSettingsAsKeyValue();

        $rangeKey = match ($tab) {
            'okuma' => 'ekip_aralik_okuma',
            'kesme' => 'ekip_aralik_kesme',
            'sokme_takma' => 'ekip_aralik_sayac_degisimi',
            'muhurleme' => 'ekip_aralik_muhurleme',
            'kacakkontrol' => 'ekip_aralik_kacak_kontrol',
            default => null
        };

        if (!$rangeKey)
            return true;

        $rangeStr = $allSettings[$rangeKey] ?? self::getDefaultRange($tab);
        return self::checkRange($teamNo, $rangeStr);
    }

    /**
     * Belirli bir tab için varsayılan ekip aralıklarını döndürür.
     */
    public static function getDefaultRange(string $tab): string
    {
        return match ($tab) {
            'okuma' => '101-200',
            'kesme' => '1-40',
            'sokme_takma' => '41-50',
            'muhurleme' => '1-100', // Varsayılan geniş
            'kacakkontrol' => '51-60',
            default => '1-999'
        };
    }

    /**
     * Ekip numarası verilen aralık string'inde (örn: "1-40,101-200") var mı kontrol eder.
     */
    public static function checkRange(int $teamNo, string $rangeStr): bool
    {
        if (empty(trim($rangeStr)))
            return true;

        $parts = explode(',', $rangeStr);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part))
                continue;

            if (strpos($part, '-') !== false) {
                list($min, $max) = explode('-', $part);
                if ($teamNo >= (int) $min && $teamNo <= (int) $max) {
                    return true;
                }
            } else {
                if ($teamNo === (int) $part) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Ekip numarasının Kesme alt türlerini (Merkez/İlçe) kontrol eder.
     * 
     * @param int|string $teamNo Ekip numarası
     * @param string $subType (merkez, ilce)
     * @return bool
     */
    public static function isKesmeSubType($teamNo, string $subType, ?SettingsModel $SettingsModel = null): bool
    {
        $teamNo = (int) $teamNo;
        if (!$SettingsModel)
            $SettingsModel = new SettingsModel();
        $allSettings = $SettingsModel->getAllSettingsAsKeyValue();

        $key = ($subType === 'merkez') ? 'ekip_aralik_kesme_merkez' : 'ekip_aralik_kesme_ilce';
        $default = ($subType === 'merkez') ? '1-30' : '31-40';

        $rangeStr = $allSettings[$key] ?? $default;
        return self::checkRange($teamNo, $rangeStr);
    }
}
