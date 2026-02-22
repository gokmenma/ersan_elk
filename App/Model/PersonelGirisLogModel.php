<?php

namespace App\Model;

use App\Model\Model;

class PersonelGirisLogModel extends Model
{
    protected $table = 'personel_giris_loglari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin giriş logunu kaydeder.
     */
    public function logLogin($personel_id, $ip_adresi, $user_agent)
    {
        $cihaz = $this->parseDevice($user_agent);
        $tarayici = $this->parseBrowser($user_agent);
        $isletim_sistemi = $this->parseOS($user_agent);

        return $this->saveWithAttr([
            'personel_id' => $personel_id,
            'ip_adresi' => $ip_adresi,
            'user_agent' => $user_agent,
            'cihaz' => $cihaz,
            'tarayici' => $tarayici,
            'isletim_sistemi' => $isletim_sistemi,
            'giris_tarihi' => date('Y-m-d H:i:s')
        ]);
    }

    private function parseDevice($agent)
    {
        if (preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $agent)) {
            return 'Mobil/Tablet';
        }
        return 'Masaüstü';
    }

    private function parseBrowser($agent)
    {
        if (preg_match('/Chrome/i', $agent) && !preg_match('/Edge/i', $agent))
            return 'Chrome';
        if (preg_match('/Safari/i', $agent) && !preg_match('/Chrome/i', $agent))
            return 'Safari';
        if (preg_match('/Firefox/i', $agent))
            return 'Firefox';
        if (preg_match('/Edge/i', $agent))
            return 'Edge';
        if (preg_match('/MSIE|Trident/i', $agent))
            return 'Internet Explorer';
        return 'Bilinmiyor';
    }

    private function parseOS($agent)
    {
        if (preg_match('/Windows NT 10.0/i', $agent))
            return 'Windows 10/11';
        if (preg_match('/Windows NT 6.3/i', $agent))
            return 'Windows 8.1';
        if (preg_match('/Windows NT 6.2/i', $agent))
            return 'Windows 8';
        if (preg_match('/Windows NT 6.1/i', $agent))
            return 'Windows 7';
        if (preg_match('/Mac OS X/i', $agent))
            return 'Mac OS X';
        if (preg_match('/Android/i', $agent))
            return 'Android';
        if (preg_match('/iPhone|iPad|iPod/i', $agent))
            return 'iOS';
        if (preg_match('/Linux/i', $agent))
            return 'Linux';
        return 'Bilinmiyor';
    }
}
