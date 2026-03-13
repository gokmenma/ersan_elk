<?php

namespace App\Service;

use Exception;

class KesmeAcmaService
{
    private $apiKey;
    private $apiUrl;

    public function __construct()
    {
        $settings = new \App\Model\SettingsModel();
        $all = $settings->getAllSettingsAsKeyValue();

        $this->apiKey = $all['api_puantaj_sifre'] ?? 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';
        $this->apiUrl = $all['api_puantaj_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_isemri_secure.php?action=getIsEmri';
    }

    /**
     * API'den iş emri (Kesme/Açma) verilerini getirir.
     * 
     * @param string $startDate Başlangıç Tarihi (dd/mm/yyyy)
     * @param string $endDate Bitiş Tarihi (dd/mm/yyyy)
     * @param int $limit Kayıt sayısı limit
     * @param int $offset Kayıt başlangıç noktası
     * @return array API yanıtı
     * @throws Exception
     */
    public function getData($startDate, $endDate, $ilkFirma = 17, $sonFirma = 17, $limit = 100, $offset = 0)
    {
        $apiKey = trim($this->apiKey);
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'ilk_firma' => $ilkFirma,
            'son_firma' => $sonFirma,
            'limit' => $limit,
            'offset' => $offset
        ];

        $maxRetries = 2; // İlk deneme başarısız olursa 1 kez daha dene
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
                break; // Başarılı, döngüden çık
            }

            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(2); // Yeniden denemeden önce kısa bir bekleme (sunucu ısınıyor olabilir)
            }
        }

        if ($httpCode !== 200) {
            $errorTitle = "API Hatası";
            if ($httpCode === 401 || $httpCode === 403) {
                $errorTitle = "API Yetkilendirme Hatası";
            } elseif ($httpCode === 504 || $httpCode === 502) {
                $errorTitle = "Zaman Aşımı (Gateway Timeout)";
            }

            $msg = "$errorTitle (HTTP $httpCode) [$startDate - $endDate]";
            if ($error_msg) {
                throw new Exception("cURL Hatası: $error_msg ($msg)");
            }
            throw new Exception("$msg: " . substr($response, 0, 500));
        }

        if (empty(trim($response))) {
            return ['success' => true, 'data' => ['data' => []]]; // Return empty structure if body is empty but HTTP 200
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API yanıtı JSON formatında değil [$startDate - $endDate]: " . substr($response, 0, 500));
        }

        return $decodedResponse;
    }
}
