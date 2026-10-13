<?php
namespace App\Service;

use Exception;

class UetsClientService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public const URL_TEST = 'https://apipp.etebligat.gov.tr/v1';
    public const URL_PROD = 'https://api.etebligat.gov.tr/v1';

    public function __construct(string $apiKey, string $environment = 'test', int $timeout = 60)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = ($environment === 'prod') ? self::URL_PROD : self::URL_TEST;
        $this->timeout = $timeout;
    }

    /**
     * Bağlantı ve Token doğrulama (HEAD /messages)
     */
    public function testConnection(): array
    {
        $response = $this->request('HEAD', '/messages');
        $msg = $response['headers']['x-message'] ?? '';
        if (empty($msg) && !empty($response['body'])) {
            $dec = json_decode($response['body'], true);
            $msg = $dec['message'] ?? '';
        }
        return [
            'success' => $response['status_code'] >= 200 && $response['status_code'] < 300,
            'total_count' => (int) ($response['headers']['x-total-count'] ?? 0),
            'unread_count' => (int) ($response['headers']['x-unread-count'] ?? 0),
            'status_code' => $response['status_code'],
            'message' => $msg,
        ];
    }

    /**
     * Mesajları Listeleme (GET /messages)
     */
    public function getMessages(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        $endpoint = '/messages' . ($queryParams ? '?' . $queryParams : '');
        $response = $this->request('GET', $endpoint);

        if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
            $error = json_decode($response['body'], true);
            throw new Exception($error['message'] ?? ('UETS mesaj listesi alınamadı (HTTP ' . $response['status_code'] . ').'));
        }

        $messages = json_decode($response['body'], true);
        if (!is_array($messages)) {
            $messages = [];
        }

        return [
            'messages' => $messages,
            'total_count' => (int) ($response['headers']['x-total-count'] ?? count($messages)),
            'unread_count' => (int) ($response['headers']['x-unread-count'] ?? 0),
        ];
    }

    /**
     * Tekil Mesaj Detayı (GET /messages/{id})
     */
    public function getMessage(string $messageId): array
    {
        $response = $this->request('GET', '/messages/' . rawurlencode($messageId));
        return json_decode($response['body'], true) ?: [];
    }

    /**
     * Mesaj Paketini İndirme (GET /messages/{id}/_download)
     * Ham EYP (zip) dosya içeriğini ve ETag hash değerini döner
     */
    public function downloadMessage(string $messageId): array
    {
        $response = $this->request('GET', '/messages/' . rawurlencode($messageId) . '/_download', [], null, true);
        return [
            'content' => $response['body'],
            'etag' => trim((string) ($response['headers']['etag'] ?? ''), '"'),
            'status_code' => $response['status_code'],
        ];
    }

    /**
     * E-Tebligat Taslağı Oluşturma ve Dosya Yükleme (POST /messages)
     */
    public function uploadDraftMessage(string $eypFilePath, string $sha1Hash): array
    {
        if (!file_exists($eypFilePath)) {
            throw new Exception("EYP dosyası bulunamadı: " . $eypFilePath);
        }

        $headers = [
            'ETag: ' . trim($sha1Hash),
        ];

        $postFields = [
            'file' => new \CURLFile($eypFilePath, 'application/octet-stream', basename($eypFilePath)),
        ];

        $response = $this->request('POST', '/messages', $headers, $postFields, false, true);
        $data = json_decode($response['body'], true);
        if (empty($data['id'])) {
            $msg = $data['message'] ?? 'UETS taslak oluşturulamadı.';
            throw new Exception($msg);
        }
        return $data;
    }

    /**
     * E-Tebligat Gönderme (PUT /messages/{id})
     */
    public function sendMessage(string $messageId, array $payload, string $sha1Hash = ''): array
    {
        $headers = [
            'Content-Type: application/json',
        ];
        if ($sha1Hash !== '') {
            $headers[] = 'ETag: ' . trim($sha1Hash);
        }

        $response = $this->request('PUT', '/messages/' . rawurlencode($messageId), $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $data = json_decode($response['body'], true);
        if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
            $msg = $data['message'] ?? 'UETS tebligat gönderilemedi (HTTP ' . $response['status_code'] . ')';
            throw new Exception($msg);
        }
        return $data ?: [];
    }

    /**
     * Alıcı Arama (POST /clients/_recipient_search)
     */
    public function searchRecipient(array $criteria): array
    {
        $headers = ['Content-Type: application/json'];
        $response = $this->request('POST', '/clients/_recipient_search', $headers, json_encode($criteria, JSON_UNESCAPED_UNICODE));
        return json_decode($response['body'], true) ?: [];
    }

    /**
     * Bakiye Bilgisi Sorgulama (GET /clients/{id}/_balance)
     */
    public function getBalance(string $uetsAddress): array
    {
        $response = $this->request('GET', '/clients/' . rawurlencode($uetsAddress) . '/_balance');
        return json_decode($response['body'], true) ?: [];
    }

    /**
     * Mesaj Delillerini Getirme (GET /messages/{id}/evidence)
     */
    public function getEvidenceList(string $messageId): array
    {
        $response = $this->request('GET', '/messages/' . rawurlencode($messageId) . '/evidence');
        return json_decode($response['body'], true) ?: [];
    }

    /**
     * Delil PDF Dosyasını İndirme (GET /evidence/{id}/_pdf)
     */
    public function downloadEvidencePdf(string $evidenceId): string
    {
        $response = $this->request('GET', '/evidence/' . rawurlencode($evidenceId) . '/_pdf', [], null, true);
        if ($response['status_code'] !== 200) {
            throw new Exception('Delil PDF indirilemedi (HTTP ' . $response['status_code'] . ')');
        }
        return $response['body'];
    }

    /**
     * Tebligat Taşıma / Arşivleme (PUT /messages/{id})
     */
    public function moveToFolder(string $messageId, int $folderId): bool
    {
        $headers = ['Content-Type: application/json'];
        $payload = ['folders_id' => $folderId];
        $response = $this->request('PUT', '/messages/' . rawurlencode($messageId), $headers, json_encode($payload));
        return $response['status_code'] >= 200 && $response['status_code'] < 300;
    }

    /**
     * Merkezi HTTP İsteği Çalıştırıcı
     */
    private function request(
        string $method,
        string $endpoint,
        array $headers = [],
        mixed $body = null,
        bool $isBinary = false,
        bool $isMultipart = false
    ): array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
        ];
        if (!$isMultipart && !in_array('Content-Type: application/json', $headers, true)) {
            $defaultHeaders[] = 'Content-Type: application/json';
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
            case 'GET':
            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        if ($error) {
            error_log("UetsClientService cURL Error [{$url}]: " . $error);
            throw new Exception("UETS Servis İletişim Hatası: " . $error);
        }

        $headerStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        if ($statusCode >= 400 && !$isBinary) {
            $decoded = json_decode($bodyStr, true);
            $msg = $decoded['message'] ?? 'UETS API Hatası (HTTP ' . $statusCode . ')';
            error_log("UetsClientService API Error [{$url}] Status: {$statusCode}, Msg: {$msg}");
        }

        return [
            'status_code' => $statusCode,
            'headers' => $parsedHeaders,
            'body' => $bodyStr,
        ];
    }
}
