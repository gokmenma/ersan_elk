<?php
namespace App\Helper;

use App\Model\SettingsModel;

class EkipHelper
{
    /**
     * Ekip adı/kodu metninden ekip numarasını çıkarır.
     * Örnekler: "EKİP-61", "Ekip 61", "61"
     */
    public static function extractTeamNo($teamValue): int
    {
        if (is_numeric($teamValue)) {
            return (int) $teamValue;
        }

        $teamText = trim((string) $teamValue);
        if ($teamText === '') {
            return 0;
        }

        if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $teamText, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/(\d{1,4})/', $teamText, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

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
        $teamNo = self::extractTeamNo($teamNo);

        if (empty($teamNo))
            return false;

        if (!$SettingsModel) {
            $SettingsModel = new SettingsModel();
        }

        $firmaId = $_SESSION['firma_id'] ?? null;
        $allSettings = $SettingsModel->getAllSettingsAsKeyValue($firmaId);

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
        
        $firmaId = $_SESSION['firma_id'] ?? null;
        $allSettings = $SettingsModel->getAllSettingsAsKeyValue($firmaId);

        $key = ($subType === 'merkez') ? 'ekip_aralik_kesme_merkez' : 'ekip_aralik_kesme_ilce';
        $default = ($subType === 'merkez') ? '1-30' : '31-40';

        $rangeStr = $allSettings[$key] ?? $default;
        return self::checkRange($teamNo, $rangeStr);
    }
}
