<?php
namespace App\Service;

use App\Config\EdmConfig;
use App\Model\EInvoiceSettingsModel;
use SoapClient;
use SoapFault;
use Exception;

class EdmSoapClient
{
    private int $firmId;
    private array $settings;
    private ?SoapClient $client = null;
    private ?string $sessionId = null;
    private EInvoiceSettingsModel $settingsModel;

    public function __construct(int $firmId)
    {
        $this->firmId = $firmId;
        $this->settingsModel = new EInvoiceSettingsModel();
        $this->settings = $this->settingsModel->getSettings($firmId) ?: [];
    }

    /**
     * SoapClient Nesnesini Başlatır
     */
    private function getClient(): SoapClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $environment = $this->settings['environment'] ?? 'TEST';
        $wsdlUrl = ($environment === 'LIVE') 
            ? ($this->settings['live_wsdl_url'] ?: EdmConfig::LIVE_WSDL_URL)
            : ($this->settings['test_wsdl_url'] ?: EdmConfig::TEST_WSDL_URL);

        $options = [
            'trace'              => 1,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'stream_context'     => stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]),
        ];

        try {
            $this->client = new SoapClient($wsdlUrl, $options);
            return $this->client;
        } catch (Exception $e) {
            error_log("EdmSoapClient::getClient Error: " . $e->getMessage());
            throw new Exception("EDM Bilişim SOAP servisine bağlanılamadı: " . $e->getMessage());
        }
    }

    /**
     * EDM İstek Başlığı Üretir
     */
    private function buildRequestHeader(string $reason = 'Islem', ?string $sessionId = null): object
    {
        return (object)[
            'SESSION_ID'       => $sessionId ?? ($this->sessionId ?: '0'),
            'CLIENT_TXN_ID'    => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            ),
            'ACTION_DATE'      => date('Y-m-d\TH:i:s'),
            'APPLICATION_NAME' => 'EDM MINI CONNECTOR v1.0',
            'CHANNEL_NAME'     => (($this->settings['environment'] ?? 'TEST') === 'LIVE') ? 'PROD' : 'TEST',
            'HOSTNAME'         => 'MDORA17',
            'REASON'           => $reason,
            'COMPRESSED'       => 'N',
        ];
    }

    /**
     * EDM Sistemine Login Olur ve SESSION_ID Alır
     */
    public function login(): string
    {
        if (!empty($this->sessionId)) {
            return $this->sessionId;
        }

        $username = $this->settings['api_username'] ?? '';
        $password = $this->settings['api_password_decrypted'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception("Firma EDM API kullanıcı adı veya şifresi tanımlanmamış.");
        }

        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('Login', '0'),
            'USER_NAME'      => $username,
            'PASSWORD'       => $password,
        ];

        try {
            $client = $this->getClient();
            $response = $client->Login($params);

            if (isset($response->SESSION_ID) && !empty($response->SESSION_ID)) {
                $this->sessionId = (string)$response->SESSION_ID;
                $this->settingsModel->logSoapAction($this->firmId, null, 'Login', json_encode($params), json_encode($response), 'BASARILI');
                return $this->sessionId;
            } elseif (isset($response->LoginResult->SESSION_ID) && !empty($response->LoginResult->SESSION_ID)) {
                $this->sessionId = (string)$response->LoginResult->SESSION_ID;
                $this->settingsModel->logSoapAction($this->firmId, null, 'Login', json_encode($params), json_encode($response), 'BASARILI');
                return $this->sessionId;
            }

            $errorMsg = $response->LoginResult->ERROR_SHORT_DES ?? ($response->ERROR_SHORT_DES ?? 'Bilinmeyen EDM Login hatası');
            $this->settingsModel->logSoapAction($this->firmId, null, 'Login', json_encode($params), json_encode($response), 'BASARISIZ', null, $errorMsg);
            throw new Exception("EDM Giriş Başarısız: " . $errorMsg);
        } catch (SoapFault $sf) {
            $this->settingsModel->logSoapAction($this->firmId, null, 'Login', json_encode($params), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
            throw new Exception("EDM SOAP Hatası (Login): " . $sf->getMessage());
        }
    }

    /**
     * Oturumu Sonlandırır
     */
    public function logout(): bool
    {
        if (empty($this->sessionId)) {
            return true;
        }

        try {
            $client = $this->getClient();
            $params = (object)[
                'REQUEST_HEADER' => $this->buildRequestHeader('Logout', $this->sessionId),
            ];
            $client->Logout($params);
            $this->sessionId = null;
            return true;
        } catch (Exception $e) {
            error_log("EdmSoapClient::logout Error: " . $e->getMessage());
            $this->sessionId = null;
            return false;
        }
    }

    /**
     * Firma Bilgilerini Getirir (GetCompany)
     */
    public function getCompany(): ?object
    {
        $username = $this->settings['api_username'] ?? '';
        $password = $this->settings['api_password_decrypted'] ?? '';

        $params = (object)[
            'USER_NAME'        => $username,
            'PASSWORD'         => $password,
            'TAXNUMBER'        => '',
            'SETCOMPANYMM'     => false,
            'DELETECOMPANYMM'  => false,
            'KEY'              => '',
            'ISHASH'           => false
        ];

        try {
            $client = $this->getClient();
            return $client->GetCompany($params);
        } catch (Exception $e) {
            error_log("EdmSoapClient::getCompany Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * VKN / TCKN Mükellefiyet Kontrolü (CheckUser)
     */
    public function checkUser(string $vknTckn): array
    {
        $sessionId = $this->login();
        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('CheckUser', $sessionId),
            'USER' => (object)[
                'IDENTIFIER' => trim($vknTckn),
            ]
        ];

        try {
            $client = $this->getClient();
            $response = $client->CheckUser($params);

            $this->settingsModel->logSoapAction($this->firmId, null, 'CheckUser', json_encode($params), json_encode($response), 'BASARILI');

            // Kullanıcı var mı kontrol et
            $isEInvoiceUser = false;
            $title = '';
            $aliases = [];

            $userResult = is_array($response) ? $response : ($response->GIBUSER ?? ($response->CheckUserResult->USER ?? ($response->USER ?? null)));

            if ($userResult) {
                $isEInvoiceUser = true;
                if (is_array($userResult)) {
                    $first = $userResult[0];
                    $title = $first->TITLE ?? ($first->NAME ?? '');
                    foreach ($userResult as $u) {
                        if (!empty($u->ALIAS) && ($u->UNIT ?? 'PK') === 'PK') {
                            $aliases[] = trim($u->ALIAS);
                        }
                    }
                    if (empty($aliases)) {
                        foreach ($userResult as $u) {
                            if (!empty($u->ALIAS)) {
                                $aliases[] = trim($u->ALIAS);
                            }
                        }
                    }
                } else {
                    $title = $userResult->TITLE ?? ($userResult->NAME ?? '');
                    if (!empty($userResult->ALIAS)) {
                        $aliases[] = trim($userResult->ALIAS);
                    }
                }
            }

            return [
                'is_einvoice_user' => $isEInvoiceUser,
                'vkn_tckn'         => $vknTckn,
                'title'            => $title,
                'aliases'          => array_values(array_unique($aliases)),
                'default_alias'    => !empty($aliases) ? $aliases[0] : null,
            ];
        } catch (SoapFault $sf) {
            // Eğer kullanıcı bulunamadıysa SOAP fault dönebilir
            $this->settingsModel->logSoapAction($this->firmId, null, 'CheckUser', json_encode($params), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
            return [
                'is_einvoice_user' => false,
                'vkn_tckn'         => $vknTckn,
                'title'            => '',
                'aliases'          => [],
                'default_alias'    => null,
            ];
        }
    }

    /**
     * E-Fatura Gönderimi (SendInvoice)
     */
    public function sendInvoice(string $xmlContent, string $receiverVkn, string $receiverAlias, ?string $senderVkn = null, ?string $senderAlias = null, ?int $faturaId = null): array
    {
        $sessionId = $this->login();
        $senderAlias = $senderAlias ?: ($this->settings['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb@edmbilisim.com.tr');
        $senderVkn = $senderVkn ?: ($this->settings['vkn_tckn'] ?? '');

        // UBL XML içeriğini hazırla
        $xmlBytes = $xmlContent;

        $invoiceObj = (object)[
            'HEADER' => (object)[
                'SENDER'   => $senderVkn,
                'FROM'     => $senderAlias,
                'RECEIVER' => $receiverVkn,
                'TO'       => $receiverAlias,
            ],
            'CONTENT' => (object)[
                'Value' => $xmlBytes
            ]
        ];

        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('SendInvoice', $sessionId),
            'RECEIVER' => (object)[
                'vkn'   => $receiverVkn,
                'alias' => $receiverAlias,
            ],
            'INVOICE' => [$invoiceObj]
        ];

        try {
            $client = $this->getClient();
            $response = $client->SendInvoice($params);

            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'SendInvoice', 'XML Length: ' . strlen($xmlContent), json_encode($response), 'BASARILI');

            $invoiceNo = $response->INVOICE_NUMBER ?? ($response->SendInvoiceResult->INVOICE_NUMBER ?? null);
            $guid = $response->GUID ?? ($response->SendInvoiceResult->GUID ?? null);

            return [
                'success'        => true,
                'fatura_no'      => $invoiceNo,
                'guid'           => $guid,
                'raw_response'   => $response
            ];
        } catch (SoapFault $sf) {
            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'SendInvoice', 'XML Length: ' . strlen($xmlContent), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
            return [
                'success' => false,
                'error'   => $sf->getMessage(),
                'code'    => $sf->faultcode ?? 'SOAP_FAULT'
            ];
        }
    }

    /**
     * Fatura Durumunu Sorgular (GetInvoiceStatus)
     */
    public function getInvoiceStatus(array $uuids): array
    {
        $sessionId = $this->login();
        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('GetInvoiceStatus', $sessionId),
            'INVOICE' => array_map(function ($uuid) {
                return (object)['UUID' => $uuid];
            }, $uuids)
        ];

        try {
            $client = $this->getClient();
            $response = $client->GetInvoiceStatus($params);

            $this->settingsModel->logSoapAction($this->firmId, null, 'GetInvoiceStatus', json_encode($params), json_encode($response), 'BASARILI');

            $results = [];
            $items = $response->GetInvoiceStatusResult->INVOICE_STATUS ?? ($response->INVOICE_STATUS ?? []);
            if (!is_array($items)) {
                $items = [$items];
            }

            foreach ($items as $item) {
                if (isset($item->UUID)) {
                    $results[$item->UUID] = [
                        'uuid'        => $item->UUID,
                        'status_code' => $item->STATUS_CODE ?? null,
                        'status_desc' => $item->STATUS_DESCRIPTION ?? ($item->DESCRIPTION ?? ''),
                        'gib_code'    => $item->GIB_STATUS_CODE ?? null,
                        'gib_desc'    => $item->GIB_STATUS_DESCRIPTION ?? '',
                        'response_code' => $item->RESPONSE_CODE ?? null,
                    ];
                }
            }

            return $results;
        } catch (SoapFault $sf) {
            $this->settingsModel->logSoapAction($this->firmId, null, 'GetInvoiceStatus', json_encode($params), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
            return [];
        }
    }

    /**
     * Faturanın Resmi HTML / PDF Görünümünü Çeker
     */
    public function getInvoiceHtml(string $uuid): ?string
    {
        $sessionId = $this->login();
        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('GetInvoiceHtml', $sessionId),
            'INVOICE' => (object)[
                'UUID' => $uuid,
                'FORMAT' => 'HTML',
            ]
        ];

        try {
            $client = $this->getClient();
            $response = $client->GetInvoice($params);

            if (isset($response->GetInvoiceResult->INVOICE->CONTENT)) {
                $content = $response->GetInvoiceResult->INVOICE->CONTENT;
                $decoded = base64_decode($content);
                // Gzdecode dene, başarısızsa doğrudan HTML'dir
                $uncompressed = @gzdecode($decoded);
                return $uncompressed ?: $decoded;
            }
            return null;
        } catch (Exception $e) {
            error_log("EdmSoapClient::getInvoiceHtml Error: " . $e->getMessage());
            return null;
        }
    }
}
