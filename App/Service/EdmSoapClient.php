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

        $params = [
            'REQUEST' => [
                'USER_NAME' => $username,
                'PASSWORD'  => $password,
            ]
        ];

        try {
            $client = $this->getClient();
            $response = $client->Login($params);

            if (isset($response->LoginResult->SESSION_ID) && !empty($response->LoginResult->SESSION_ID)) {
                $this->sessionId = (string)$response->LoginResult->SESSION_ID;
                $this->settingsModel->logSoapAction($this->firmId, null, 'Login', json_encode($params), json_encode($response), 'BASARILI');
                return $this->sessionId;
            } elseif (isset($response->SESSION_ID)) {
                $this->sessionId = (string)$response->SESSION_ID;
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
            $client->Logout([
                'REQUEST_HEADER' => [
                    'SESSION_ID' => $this->sessionId,
                ]
            ]);
            $this->sessionId = null;
            return true;
        } catch (Exception $e) {
            error_log("EdmSoapClient::logout Error: " . $e->getMessage());
            $this->sessionId = null;
            return false;
        }
    }

    /**
     * VKN / TCKN Mükellefiyet Kontrolü (CheckUser)
     */
    public function checkUser(string $vknTckn): array
    {
        $sessionId = $this->login();
        $params = [
            'REQUEST_HEADER' => [
                'SESSION_ID' => $sessionId,
            ],
            'USER' => [
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

            $userResult = $response->CheckUserResult->USER ?? ($response->USER ?? null);

            if ($userResult) {
                $isEInvoiceUser = true;
                if (is_array($userResult)) {
                    $first = $userResult[0];
                    $title = $first->TITLE ?? ($first->NAME ?? '');
                    foreach ($userResult as $u) {
                        if (!empty($u->ALIAS)) {
                            $aliases[] = $u->ALIAS;
                        }
                    }
                } else {
                    $title = $userResult->TITLE ?? ($userResult->NAME ?? '');
                    if (!empty($userResult->ALIAS)) {
                        $aliases[] = $userResult->ALIAS;
                    }
                }
            }

            return [
                'is_einvoice_user' => $isEInvoiceUser,
                'vkn_tckn'         => $vknTckn,
                'title'            => $title,
                'aliases'          => array_unique($aliases),
                'default_alias'    => !empty($aliases) ? $aliases[0] : 'urn:mail:defaultpk',
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
    public function sendInvoice(string $xmlContent, string $receiverAlias, ?string $senderAlias = null, ?int $faturaId = null): array
    {
        $sessionId = $this->login();
        $senderAlias = $senderAlias ?: ($this->settings['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb');

        $compressedXml = base64_encode(gzencode($xmlContent, 9));

        $params = [
            'REQUEST_HEADER' => [
                'SESSION_ID' => $sessionId,
            ],
            'INVOICE' => [
                'HEADER' => [
                    'SENDER'   => $senderAlias,
                    'RECEIVER' => $receiverAlias,
                    'SUPPLIER' => $this->settings['api_username'] ?? '',
                ],
                'CONTENT' => [
                    '_' => $compressedXml,
                    'contentType' => 'application/zip',
                ]
            ]
        ];

        try {
            $client = $this->getClient();
            $response = $client->SendInvoice($params);

            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'SendInvoice', 'XML Length: ' . strlen($xmlContent), json_encode($response), 'BASARILI');

            $invoiceNo = $response->SendInvoiceResult->INVOICE_NUMBER ?? ($response->INVOICE_NUMBER ?? null);
            $guid = $response->SendInvoiceResult->GUID ?? ($response->GUID ?? null);

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
     * E-Arşiv Fatura Gönderimi (ArchiveInvoice)
     */
    public function archiveInvoice(string $xmlContent, ?string $email = null, ?int $faturaId = null): array
    {
        $sessionId = $this->login();
        $compressedXml = base64_encode(gzencode($xmlContent, 9));

        $params = [
            'REQUEST_HEADER' => [
                'SESSION_ID' => $sessionId,
            ],
            'ARCHIVE_INVOICE' => [
                'HEADER' => [
                    'SUPPLIER' => $this->settings['api_username'] ?? '',
                    'SEND_TYPE' => !empty($email) ? 'ELEKTRONIK' : 'KAGIT',
                    'EMAIL' => $email,
                ],
                'CONTENT' => [
                    '_' => $compressedXml,
                    'contentType' => 'application/zip',
                ]
            ]
        ];

        try {
            $client = $this->getClient();
            $response = $client->ArchiveInvoice($params);

            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'ArchiveInvoice', 'XML Length: ' . strlen($xmlContent), json_encode($response), 'BASARILI');

            $invoiceNo = $response->ArchiveInvoiceResult->INVOICE_NUMBER ?? ($response->INVOICE_NUMBER ?? null);
            $guid = $response->ArchiveInvoiceResult->GUID ?? ($response->GUID ?? null);

            return [
                'success'      => true,
                'fatura_no'    => $invoiceNo,
                'guid'         => $guid,
                'raw_response' => $response
            ];
        } catch (SoapFault $sf) {
            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'ArchiveInvoice', 'XML Length: ' . strlen($xmlContent), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
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
        $params = [
            'REQUEST_HEADER' => [
                'SESSION_ID' => $sessionId,
            ],
            'INVOICE' => array_map(function ($uuid) {
                return ['UUID' => $uuid];
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
                        'response_code' => $item->RESPONSE_CODE ?? null, // KABUL / RED
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
        $params = [
            'REQUEST_HEADER' => [
                'SESSION_ID' => $sessionId,
            ],
            'INVOICE' => [
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
