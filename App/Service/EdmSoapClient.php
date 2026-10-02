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
     * E-Fatura / E-Arşiv Gönderimi (SendInvoice)
     */
    public function sendInvoice(string $xmlContent, string $receiverVkn, string $receiverAlias = 'defaultpk', ?string $senderVkn = null, ?string $senderAlias = null, ?int $faturaId = null, ?string $uuid = null): array
    {
        $sessionId = $this->login();
        $senderAlias = $senderAlias ?: ($this->settings['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb@edmbilisim.com.tr');
        if (strpos($senderAlias, '@edmbilisim.com.tr') === false) {
            $senderAlias .= '@edmbilisim.com.tr';
        }
        $senderVkn = $senderVkn ?: ($this->settings['api_username'] ?? '3230512384');

        $invoiceItem = (object)[
            'CONTENT' => $xmlContent
        ];
        if (!empty($uuid)) {
            $invoiceItem->UUID = $uuid;
        }

        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('SendInvoice', $sessionId),
            'SENDER' => (object)[
                'vkn'   => $senderVkn,
                'alias' => $senderAlias,
            ],
            'RECEIVER' => (object)[
                'vkn'   => $receiverVkn,
                'alias' => $receiverAlias,
            ],
            'INVOICE' => [$invoiceItem]
        ];

        try {
            $client = $this->getClient();
            $response = $client->SendInvoice($params);

            $this->settingsModel->logSoapAction($this->firmId, $faturaId, 'SendInvoice', 'XML Length: ' . strlen($xmlContent), json_encode($response), 'BASARILI');

            $invoiceNo = $response->INVOICE_NUMBER ?? ($response->SendInvoiceResult->INVOICE_NUMBER ?? ($response->INVOICE->ID ?? null));
            $guid = $response->GUID ?? ($response->SendInvoiceResult->GUID ?? ($response->INVOICE->UUID ?? null));

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
        if (empty($uuids)) {
            return [];
        }

        $sessionId = $this->login();
        $invoiceParam = (count($uuids) === 1)
            ? (object)['UUID' => reset($uuids)]
            : array_map(fn($u) => (object)['UUID' => $u], $uuids);

        $params = (object)[
            'REQUEST_HEADER' => $this->buildRequestHeader('GetInvoiceStatus', $sessionId),
            'INVOICE'        => $invoiceParam
        ];

        try {
            $client = $this->getClient();
            $response = $client->GetInvoiceStatus($params);

            $this->settingsModel->logSoapAction($this->firmId, null, 'GetInvoiceStatus', json_encode($params), json_encode($response), 'BASARILI');

            $results = [];
            $items = $response->INVOICE_STATUS ?? ($response->GetInvoiceStatusResult->INVOICE_STATUS ?? []);
            if (!is_array($items) && is_object($items)) {
                $items = [$items];
            }

            foreach ($items as $item) {
                if (isset($item->UUID)) {
                    $results[$item->UUID] = [
                        'uuid'          => (string)$item->UUID,
                        'fatura_no'     => (string)($item->ID ?? ''),
                        'status'        => (string)($item->STATUS ?? ''),
                        'status_code'   => (string)($item->STATUS_CODE ?? $item->STATUS ?? ''),
                        'status_desc'   => (string)($item->STATUS_DESCRIPTION ?? ''),
                        'gib_code'      => $item->GIB_STATUS_CODE ?? null,
                        'gib_desc'      => (string)($item->GIB_STATUS_DESCRIPTION ?? ''),
                        'response_code' => (string)($item->RESPONSE_CODE ?? ''),
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

    /**
     * EDM Sisteminden Faturaları Çeker (GetInvoice) - Otomatik Sayfalama Destekli
     */
    public function getInvoices(string $direction = 'OUT', ?string $startDate = null, ?string $endDate = null, int $limit = 500, string $dateType = 'CREATE'): array
    {
        $sessionId = $this->login();
        $startDate = $startDate ?: date('Y-m-d');
        $endDate = $endDate ?: date('Y-m-d');

        // EDM kuralı: İki tarih arasındaki fark 60 günü geçemez
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if (($endTs - $startTs) > (60 * 86400)) {
            $startDate = date('Y-m-d', $endTs - (58 * 86400));
        }

        $allInvoices = [];
        $lastCDate = null;
        $maxPages = (int)ceil($limit / 50);
        if ($maxPages < 1) $maxPages = 1;
        if ($maxPages > 20) $maxPages = 20;

        try {
            $client = $this->getClient();

            for ($page = 0; $page < $maxPages; $page++) {
                $searchKey = (object)[
                    'LIMIT' => 50,
                    'DIRECTION' => $direction,
                    'READ_INCLUDED' => true
                ];

                if ($dateType === 'CREATE') {
                    $searchKey->CR_START_DATE = ($lastCDate ?: $startDate . 'T00:00:00');
                    $searchKey->CR_END_DATE = $endDate . 'T23:59:59';
                } else {
                    $searchKey->START_DATE = $startDate;
                    $searchKey->END_DATE = $endDate;
                }

                $params = (object)[
                    'REQUEST_HEADER' => $this->buildRequestHeader('GetInvoice', $sessionId),
                    'INVOICE_SEARCH_KEY' => $searchKey,
                    'HEADER_ONLY' => 'N',
                    'INVOICE_CONTENT_TYPE' => 'XML'
                ];

                $response = $client->GetInvoice($params);
                $this->settingsModel->logSoapAction($this->firmId, null, 'GetInvoice', json_encode($params), "Fetched {$direction} Page " . ($page + 1), 'BASARILI');

                $items = $response->INVOICE ?? ($response->GetInvoiceResult->INVOICE ?? []);
                if (!is_array($items) && is_object($items)) {
                    $items = [$items];
                }

                if (empty($items)) {
                    break;
                }

                $pageAdded = 0;
                $newestCDate = null;

                foreach ($items as $item) {
                    $uuid = (string)($item->UUID ?? '');
                    if (empty($uuid) || isset($allInvoices[$uuid])) {
                        continue;
                    }

                    $faturaNo = (string)($item->ID ?? '');
                    $xmlContent = '';

                    if (isset($item->CONTENT->_)) {
                        $xmlContent = (string)$item->CONTENT->_;
                    } elseif (isset($item->CONTENT->Value)) {
                        $val = $item->CONTENT->Value;
                        $decoded = is_string($val) ? base64_decode($val) : '';
                        $uncompressed = @gzdecode($decoded);
                        $xmlContent = $uncompressed ?: ($decoded ?: $val);
                    } elseif (is_string($item->CONTENT ?? null)) {
                        $xmlContent = (string)$item->CONTENT;
                    }

                    $hdr = $item->HEADER ?? null;

                    $allInvoices[$uuid] = [
                        'uuid'          => $uuid,
                        'fatura_no'     => $faturaNo,
                        'xml'           => $xmlContent,
                        'header'        => $hdr,
                        'issue_date'    => $hdr->ISSUE_DATE ?? date('Y-m-d'),
                        'payable_amount'=> (float)($hdr->PAYABLE_AMOUNT->_ ?? $hdr->PAYABLE_AMOUNT ?? 0),
                        'supplier'      => $hdr->SUPPLIER ?? '',
                        'customer'      => $hdr->CUSTOMER ?? '',
                        'sender'        => $hdr->SENDER ?? '',
                        'receiver'      => $hdr->RECEIVER ?? '',
                        'profile_id'    => $hdr->PROFILEID ?? 'TEMELFATURA',
                        'status'        => $hdr->STATUS ?? '',
                        'status_desc'   => $hdr->STATUS_DESCRIPTION ?? '',
                    ];
                    $pageAdded++;

                    if (!empty($hdr->CDATE)) {
                        $newestCDate = (string)$hdr->CDATE;
                    }
                }

                // 50'den az geldiyse veya yeni kayıt eklenemediyse veya dateType!=CREATE ise döngü tamamlandı
                if (count($items) < 50 || $pageAdded === 0 || $dateType !== 'CREATE' || empty($newestCDate) || $newestCDate === $lastCDate) {
                    break;
                }

                // Sonraki sayfa için CR_START_DATE'i 1 saniye ileri al
                $dt = new \DateTime($newestCDate);
                $dt->modify('+1 second');
                $lastCDate = $dt->format('Y-m-d\TH:i:s');
            }

            return array_values($allInvoices);
        } catch (SoapFault $sf) {
            $this->settingsModel->logSoapAction($this->firmId, null, 'GetInvoice', json_encode($params ?? []), $sf->getMessage(), 'BASARISIZ', $sf->faultcode, $sf->faultstring);
            error_log("EdmSoapClient::getInvoices Error: " . $sf->getMessage());
            return array_values($allInvoices);
        }
    }
}
