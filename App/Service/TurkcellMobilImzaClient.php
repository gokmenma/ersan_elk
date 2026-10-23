<?php

namespace App\Service;

final class TurkcellMobilImzaClient
{
    private const PROFILE_URL = 'https://msign.turkcell.com.tr/MSSP2/services/MSS_ProfileQueryPort';
    private const SIGNATURE_URL = 'https://msign.turkcell.com.tr/MSSP2/services/MSS_Signature';
    private const STATUS_URL = 'https://msign.turkcell.com.tr/MSSP2/services/MSS_StatusPort';
    private const NS_ETSI = 'http://uri.etsi.org/TS102204/v1.1.2#';
    private const NS_SOAP = 'http://turkcelltech.com/mobilesignature/validation/soap';
    private const FORMAT_PKCS7 = 'http://uri.etsi.org/TS102204/v1.1.2#PKCS7';
    public const ERROR_CONNECTION_REFUSED = 1001;

    private const STATUS_MESSAGES = [
        101 => 'Mobil imza isteğinde hatalı parametre var.',
        102 => 'Mobil imza isteğinde eksik parametre var.',
        103 => 'İmzalanacak veri uzunluğu hatalı.',
        104 => 'Mobil imza servisine erişim yetkisi reddedildi (AP_ID/AP_PWD).',
        105 => 'Bu telefon numarası için Turkcell mobil imza hizmeti bulunamadı.',
        107 => 'İmzalanacak veri uygun değil.',
        108 => 'Mobil imza arayüz sürümü uyumsuz.',
        109 => 'Mobil imza profili desteklenmiyor.',
        208 => 'Mobil imza onayı süresi içinde verilmedi.',
        209 => 'Telefona imza mesajı iletilemedi.',
        401 => 'Mobil imza işlemi telefonda iptal edildi.',
        402 => 'Mobil imza PIN kodu bloke olmuş.',
        403 => 'SIM kart bloke olmuş.',
        404 => 'SIM kartta imza anahtarı bulunamadı.',
        406 => 'Telefonda imza oluşturulurken hata oluştu.',
        422 => 'Hatta tanımlı mobil imza sertifikası bulunamadı.',
        501 => 'Mobil imza sertifikası iptal edilmiş.',
        503 => 'Mobil imza geçersiz.',
        900 => 'Turkcell mobil imza servisinde iç hata oluştu.',
    ];

    private string $apId;
    private string $apPassword;
    private int $timeout;

    public function __construct(int $timeout = 30)
    {
        $this->apId = trim((string) ($_ENV['TURKCELL_MIMZA_AP_ID'] ?? ''));
        $this->apPassword = trim((string) ($_ENV['TURKCELL_MIMZA_AP_PWD'] ?? ''));
        $this->timeout = $timeout;
    }

    public static function isConfigured(): bool
    {
        return trim((string) ($_ENV['TURKCELL_MIMZA_AP_ID'] ?? '')) !== '' && trim((string) ($_ENV['TURKCELL_MIMZA_AP_PWD'] ?? '')) !== '';
    }

    public static function outboundIp(): string
    {
        $ch = curl_init('https://api.ipify.org');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $ip = trim((string) curl_exec($ch));
        curl_close($ch);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    public static function normalizeMsisdn(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            $digits = '0' . $digits;
        }
        if (!preg_match('/^05\d{9}$/', $digits)) {
            throw new \RuntimeException('Mobil imza telefon numarası 05XXXXXXXXX biçiminde olmalıdır.');
        }
        return $digits;
    }

    public static function maskMsisdn(string $msisdn): string
    {
        return strlen($msisdn) >= 11 ? substr($msisdn, 0, 4) . '*****' . substr($msisdn, -2) : '***';
    }

    public function profileQuery(string $msisdn): array
    {
        $msisdn = self::normalizeMsisdn($msisdn);
        $body = '<soap:MSS_ProfileQuery><MSS_ProfileReq MajorVersion="1" MinorVersion="1">'
            . $this->apInfo(false)
            . '<v1:MSSP_Info Instant="' . $this->instant() . '"><v1:MSSP_ID>' . $msisdn . '</v1:MSSP_ID></v1:MSSP_Info>'
            . '<v1:MobileUser><v1:UserIdentifier>http://www.w3.org/2001/04/xmlenc#sha256</v1:UserIdentifier><v1:MSISDN>' . $msisdn . '</v1:MSISDN></v1:MobileUser>'
            . '</MSS_ProfileReq></soap:MSS_ProfileQuery>';
        $xpath = $this->call(self::PROFILE_URL, $body);
        $status = $this->status($xpath);
        if ($status['code'] !== 100) {
            throw new \RuntimeException($this->statusMessage($status));
        }
        $certHash = base64_decode($this->text($xpath, '//*[local-name()="CertHash"]/*[local-name()="DigestValue"]'), true);
        $profile = $this->text($xpath, '//*[local-name()="SignatureProfile"]/*[local-name()="mssURI"]');
        if ($profile === '' || $certHash === false || strlen($certHash) !== 32) {
            throw new \RuntimeException('Mobil imza profil veya sertifika bilgisi alınamadı.');
        }
        return [
            'profile' => $profile,
            'cert_hash' => $certHash,
            'cert_serial' => $this->text($xpath, '//*[local-name()="CertSerialNumber"]'),
            'cert_issuer' => trim($this->text($xpath, '//*[local-name()="CertIssuerDN"]')),
        ];
    }

    public function signHash(string $msisdn, string $hash, string $displayText, string $profile, int $timeout = 120): array
    {
        $msisdn = self::normalizeMsisdn($msisdn);
        $body = '<soap:MSS_Signature><MSS_SignatureReq MajorVersion="1" MessagingMode="asynchClientServer" MinorVersion="1" TimeOut="' . $timeout . '">'
            . $this->apInfo(true)
            . '<v1:MSSP_Info><v1:MSSP_ID/></v1:MSSP_Info>'
            . '<v1:MobileUser><v1:MSISDN>' . $msisdn . '</v1:MSISDN></v1:MobileUser>'
            . '<v1:DataToBeSigned Encoding="Base64" MimeType="application/octet-stream">' . base64_encode($hash) . '</v1:DataToBeSigned>'
            . '<v1:DataToBeDisplayed Encoding="UTF-8" MimeType="text/plain">' . $this->escape($displayText) . '</v1:DataToBeDisplayed>'
            . '<v1:SignatureProfile><v1:mssURI>' . $this->escape($profile) . '</v1:mssURI></v1:SignatureProfile>'
            . '<v1:MSS_Format><v1:mssURI>' . self::FORMAT_PKCS7 . '</v1:mssURI></v1:MSS_Format>'
            . '</MSS_SignatureReq></soap:MSS_Signature>';
        $xpath = $this->call(self::SIGNATURE_URL, $body);
        $status = $this->status($xpath);
        if (!in_array($status['code'], [500, 502, 504], true)) {
            throw new \RuntimeException($this->statusMessage($status));
        }
        $transactionId = $this->text($xpath, '//*[local-name()="MSS_SignatureResp"]/@MSSP_TransID');
        if ($transactionId === '') {
            throw new \RuntimeException('Mobil imza işlem numarası alınamadı.');
        }
        return [
            'transaction_id' => $transactionId,
            'status' => $status['code'],
            'signature' => $this->signature($xpath),
        ];
    }

    public function statusQuery(string $transactionId): array
    {
        $body = '<soap:MSS_StatusQuery><MSS_StatusReq MajorVersion="1" MinorVersion="1" MSSP_TransID="' . $this->escape($transactionId) . '">'
            . $this->apInfo(true)
            . '<v1:MSSP_Info Instant="' . $this->instant() . '"><v1:MSSP_ID><v1:DNSName></v1:DNSName></v1:MSSP_ID></v1:MSSP_Info>'
            . '</MSS_StatusReq></soap:MSS_StatusQuery>';
        $xpath = $this->call(self::STATUS_URL, $body);
        $status = $this->status($xpath);
        $state = match (true) {
            in_array($status['code'], [500, 502], true) => 'imzalandi',
            $status['code'] === 504 => 'bekliyor',
            $status['code'] === 401 => 'iptal',
            $status['code'] === 208 => 'zaman_asimi',
            default => 'hata',
        };
        return [
            'state' => $state,
            'code' => $status['code'],
            'message' => $state === 'bekliyor' || $state === 'imzalandi' ? '' : $this->statusMessage($status),
            'signature' => $state === 'imzalandi' ? $this->signature($xpath) : null,
        ];
    }

    private function signature(\DOMXPath $xpath): ?string
    {
        $value = preg_replace('/\s+/', '', $this->text($xpath, '//*[local-name()="MSS_Signature"]/*[local-name()="Base64Signature"]'));
        if ($value === '') {
            return null;
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new \RuntimeException('Mobil imza yanıtı çözülemedi.');
        }
        return $decoded;
    }

    private function apInfo(bool $withUrl): string
    {
        if ($this->apId === '' || $this->apPassword === '') {
            throw new \RuntimeException('Turkcell mobil imza servis bilgileri (.env) tanımlı değil.');
        }
        return '<v1:AP_Info AP_ID="' . $this->escape($this->apId) . '" AP_PWD="' . $this->escape($this->apPassword) . '"'
            . ' AP_TransID="_' . date('YmdHis') . random_int(100000, 999999) . '"'
            . ($withUrl ? ' AP_URL=""' : '')
            . ' Instant="' . $this->instant() . '"/>';
    }

    private function call(string $url, string $body): \DOMXPath
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="' . self::NS_SOAP . '" xmlns:v1="' . self::NS_ETSI . '">'
            . '<soapenv:Header/><soapenv:Body>' . $body . '</soapenv:Body></soapenv:Envelope>';
        $url = (string) ($_ENV[match ($url) {
            self::PROFILE_URL => 'TURKCELL_MIMZA_PROFILE_URL',
            self::SIGNATURE_URL => 'TURKCELL_MIMZA_SIGNATURE_URL',
            default => 'TURKCELL_MIMZA_STATUS_URL',
        }] ?? '') ?: $url;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: ""'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $connectTime = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $tlsTime = (float) curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);
        $remoteIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            error_log('TurkcellMobilImzaClient bağlantı hatası (' . $errno . '): ' . $error);
            $reason = match ($errno) {
                CURLE_SSL_CONNECT_ERROR, CURLE_RECV_ERROR, CURLE_SEND_ERROR => 'Turkcell sunucusu bağlantıyı reddetti; sunucu IP adresinin Turkcell tarafında izinli (beyaz liste) olması gerekir.',
                CURLE_COULDNT_RESOLVE_HOST => 'msign.turkcell.com.tr adresi sunucuda çözümlenemedi (DNS).',
                CURLE_COULDNT_CONNECT => 'Sunucudan Turkcell 443 portuna çıkış yapılamadı; hosting güvenlik duvarında giden bağlantı izni gerekir.',
                CURLE_OPERATION_TIMEDOUT => 'Turkcell sunucusuna bağlantı zaman aşımına uğradı; hosting güvenlik duvarı veya Turkcell IP izni kontrol edilmelidir.',
                CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE, CURLE_SSL_PEER_CERTIFICATE => 'Sunucuda Turkcell SSL sertifikası doğrulanamadı (CA sertifika paketi eksik veya eski).',
                default => 'Turkcell mobil imza servisine bağlanılamadı.',
            };
            $stage = match (true) {
                $connectTime <= 0 => 'TCP bağlantısı kurulamadı',
                $tlsTime <= 0 => 'TCP bağlandı, TLS el sıkışması tamamlanmadı',
                default => 'TLS kuruldu, servis yanıt vermedi',
            };
            throw new \RuntimeException($reason . ' (Hata kodu: ' . $errno . '; aşama: ' . $stage . ($remoteIp !== '' ? '; hedef: ' . $remoteIp : '') . ')', self::ERROR_CONNECTION_REFUSED);
        }
        $document = new \DOMDocument();
        if (!@$document->loadXML((string) $response, LIBXML_NONET)) {
            error_log('TurkcellMobilImzaClient geçersiz yanıt (HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 500));
            throw new \RuntimeException('Turkcell mobil imza servisinden geçersiz yanıt alındı.');
        }
        $xpath = new \DOMXPath($document);
        $fault = $this->text($xpath, '//*[local-name()="Fault"]//*[local-name()="Text" or local-name()="faultstring"]');
        if ($fault !== '') {
            error_log('TurkcellMobilImzaClient SOAP hatası (HTTP ' . $httpCode . '): ' . $fault);
            throw new \RuntimeException('Turkcell mobil imza servisi isteği reddetti.');
        }
        return $xpath;
    }

    private function status(\DOMXPath $xpath): array
    {
        return [
            'code' => (int) $this->text($xpath, '//*[local-name()="Status"]/*[local-name()="StatusCode"]/@Value'),
            'message' => $this->text($xpath, '//*[local-name()="Status"]/*[local-name()="StatusMessage"]'),
        ];
    }

    private function statusMessage(array $status): string
    {
        return self::STATUS_MESSAGES[$status['code']]
            ?? 'Mobil imza servisi hata döndü (' . $status['code'] . ($status['message'] !== '' ? ' ' . $status['message'] : '') . ').';
    }

    private function text(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        return ($nodes && $nodes->length > 0) ? trim((string) $nodes->item(0)->nodeValue) : '';
    }

    private function instant(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.vP');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
