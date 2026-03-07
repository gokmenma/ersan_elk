<?php

namespace App\Service;

use Exception;

class SayacDegisimService
{
    private $apiKey;
    private $apiUrl;

    public function __construct()
    {
        $settings = new \App\Model\SettingsModel();
        $all = $settings->getAllSettingsAsKeyValue();

        $this->apiKey = $all['api_sayac_degisim_sifre'] ?? 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';
        $this->apiUrl = $all['api_sayac_degisim_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_sayac_degisim.php?action=getMeterChanges';
    }

    /**
     * API'den sayaç değişim verilerini getirir.
     * 
     * @param string $startDate Başlangıç Tarihi (dd/mm/yyyy)
     * @param string $endDate Bitiş Tarihi (dd/mm/yyyy)
     * @param int $limit Kayıt sayısı limit
     * @param int $offset Kayıt başlangıç noktası
     * @return array API yanıtı
     * @throws Exception
     */

    /**
     * API'den sayaç değişim verilerini getirir.
     * 
     * @param string $startDate Başlangıç Tarihi (dd/mm/yyyy)
     * @param string $endDate Bitiş Tarihi (dd/mm/yyyy)
     * @param int $limit Kayıt sayısı limit
     * @param int $offset Kayıt başlangıç noktası
     * @return array API yanıtı
     * @throws Exception
     */
    public function getData($startDate, $endDate, $limit = 500, $offset = 0)
    {
        $apiKey = trim($this->apiKey);
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'limit' => $limit,
            'offset' => $offset
        ];

        $maxRetries = 2;
        $attempt = 0;
        $response = false;
        $httpCode = 0;
        $error_msg = '';

        while ($attempt < $maxRetries) {
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                break;
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(2);
            }
        }

        if ($httpCode !== 200) {
            $errorTitle = "API Hatası";
            if ($httpCode === 401 || $httpCode === 403) {
                $errorTitle = "API Yetkilendirme Hatası";
            } elseif ($httpCode === 504 || $httpCode === 502) {
                $errorTitle = "Zaman Aşımı (Gateway Timeout)";
            }

            if ($error_msg) {
                throw new Exception("cURL Hatası: " . $error_msg);
            }
            throw new Exception("$errorTitle (HTTP $httpCode): " . $response);
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API yanıtı JSON formatında değil: " . $response);
        }

        return $decodedResponse;
    }

    /**
     * Günlük sayaç değişim özet istatistiklerini getirir.
     */
    public function getDailyStats()
    {
        $Model = new \App\Model\SayacDegisimModel();
        return $Model->getDailyStats();
    }

    /**
     * Aylık sayaç değişim özet istatistiklerini getirir.
     */
    public function getMonthlyStats()
    {
        $Model = new \App\Model\SayacDegisimModel();
        return $Model->getMonthlyStats();
    }

    /**
     * Tarih aralığındaki sayaç değişim sayısını çeker (detaylı, isemri_sonucu'na göre gruplu)
     */
    public function getSummaryDetailedByRange($startDate, $endDate)
    {
        $Model = new \App\Model\SayacDegisimModel();
        return $Model->getSummaryDetailedByRange($startDate, $endDate);
    }

    /**
     * Veritabanında kayıtlı benzersiz iş emri sonuçlarını getirir (Rapor kolon başlıkları için)
     */
    public function getDistinctWorkTypes()
    {
        $Model = new \App\Model\SayacDegisimModel();
        return $Model->getDistinctWorkTypes();
    }
}
