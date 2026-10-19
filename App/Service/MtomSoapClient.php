<?php

namespace App\Service;

use SoapClient;
use Exception;

/**
 * PTT KEP Axis2 MTOM/XOP multipart SOAP yanıtlarını
 * şeffaf biçimde temiz SOAP XML zarfına dönüştüren özel SoapClient sınıfı.
 */
class MtomSoapClient extends SoapClient
{
    private int $curlTimeout = 45;
    private bool $sslVerify = false;

    public function setCurlTimeout(int $seconds): void
    {
        $this->curlTimeout = $seconds;
    }

    public function setSslVerify(bool $verify): void
    {
        $this->sslVerify = $verify;
    }

    /**
     * SOAP isteğini cURL ile yürütür ve MTOM multipart yanıtından XML gövdesini ayıklar.
     */
    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
    {
        $ch = curl_init($location);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $action . '"',
            'Content-Length: ' . strlen($request),
            'Accept: multipart/related, text/xml, application/soap+xml, application/xop+xml',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curlTimeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            throw new Exception("PTT KEP Web Servisine bağlanılamadı (HTTP {$httpCode}): {$curlError}");
        }

        // Yanıt MTOM / MIME Multipart ise SOAP Envelope XML bloğunu ayıkla
        return self::extractSoapXmlFromMtom((string) $response);
    }

    /**
     * Gelen MTOM / XOP veya standart yanıttan SOAP Envelope XML içeriğini ayıklar ve XOP eklerini base64 olarak gömer.
     */
    public static function extractSoapXmlFromMtom(string $rawResponse): string
    {
        // 1. Eğer MIME Multipart ise parçaları ayrıştır
        if (preg_match('/--([a-zA-Z0-9_\-]+)/', $rawResponse, $bm)) {
            $boundary = $bm[1];
            $parts = explode('--' . $boundary, $rawResponse);
            $soapXml = null;
            $attachments = [];

            foreach ($parts as $part) {
                $trimmed = trim($part);
                if ($trimmed === '' || $trimmed === '--') continue;

                // Başlıklar ve gövde ayrımı
                $headerBodySplit = preg_split("/\r?\n\r?\n/", $part, 2);
                if (count($headerBodySplit) !== 2) continue;

                [$headers, $body] = $headerBodySplit;
                $cid = null;
                if (preg_match('/content-id:\s*<([^>]+)>/i', $headers, $cm)) {
                    $cid = trim($cm[1]);
                }

                // SOAP Envelope içeren partı bul
                if ($soapXml === null && preg_match('/<([a-zA-Z0-9_\-]+:)?Envelope\b.*?<\/\1?Envelope>/is', $body, $sm)) {
                    $soapXml = $sm[0];
                } elseif ($cid !== null) {
                    // Binary eklenti partı (sonundaki CRLF temizliği)
                    $attachments[$cid] = rtrim($body, "\r\n");
                }
            }

            if ($soapXml !== null) {
                // <xop:Include href="cid:..."/> referanslarını base64 olarak göm
                if (!empty($attachments) && preg_match('/<[a-zA-Z0-9_\-]+:Include\b/i', $soapXml)) {
                    $soapXml = preg_replace_callback(
                        '/<([a-zA-Z0-9_\-]+):Include\b[^>]*href=["\']cid:([^"\']+)["\'][^>]*>(?:<\/\1:Include>)?/is',
                        static function (array $m) use ($attachments): string {
                            $cid = trim($m[2]);
                            if (isset($attachments[$cid])) {
                                return base64_encode($attachments[$cid]);
                            }
                            return $m[0];
                        },
                        $soapXml
                    );
                }
                return $soapXml;
            }
        }

        // 2. Standart XML kontrolü
        if (preg_match('/<([a-zA-Z0-9_\-]+:)?Envelope\b.*?<\/\1?Envelope>/is', $rawResponse, $matches)) {
            return $matches[0];
        }

        return $rawResponse;
    }
}
