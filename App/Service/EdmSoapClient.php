<?php
namespace App\Service;

use App\Config\EdmConfig;
use App\Model\EInvoiceSettingsModel;
use SoapClient;
use SoapFault;

class EdmSoapClient
{
    private ?string $sessionId = null;
    private ?object $client;
    private array $settings;
    private ?EInvoiceSettingsModel $settingsModel;
    private array $syncResult = ['complete' => true, 'errors' => []];

    /** Transport/settings injection keeps tests completely offline. */
    public function __construct(private int $firmId, ?object $transport = null, ?array $settings = null, ?EInvoiceSettingsModel $settingsModel = null)
    {
        $this->client = $transport;
        $this->settingsModel = $settingsModel ?? ($settings === null ? new EInvoiceSettingsModel() : null);
        $this->settings = $settings ?? ($this->settingsModel->getSettings($firmId) ?: []);
    }

    private function getClient(): object
    {
        if ($this->client) return $this->client;
        $live = ($this->settings['environment'] ?? 'TEST') === 'LIVE';
        $url = $this->settings[$live ? 'live_wsdl_url' : 'test_wsdl_url'] ?? ($live ? EdmConfig::LIVE_WSDL_URL : EdmConfig::TEST_WSDL_URL);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') throw new EdmOperationException('validation', 'EDM servis adresi HTTPS olmalıdır.');
        try {
            return $this->client = new SoapClient($url, [
                'trace' => false, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_MEMORY,
                'connection_timeout' => 30, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'stream_context' => stream_context_create([
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
                    'http' => ['timeout' => 60],
                ]),
            ]);
        } catch (\Throwable $e) {
            throw new EdmOperationException('connection', 'EDM servisine güvenli bağlantı kurulamadı.', $e);
        }
    }

    private function header(string $operation): object
    {
        return (object)[
            'SESSION_ID' => $this->sessionId ?? '0', 'CLIENT_TXN_ID' => \App\Helper\Helper::generateUuid(),
            'ACTION_DATE' => date('c'), 'APPLICATION_NAME' => 'ERSAN ERP',
            'CHANNEL_NAME' => ($this->settings['environment'] ?? 'TEST') === 'LIVE' ? 'PROD' : 'TEST',
            'HOSTNAME' => gethostname(), 'REASON' => $operation, 'COMPRESSED' => 'N',
        ];
    }

    public static function items(mixed $value): array
    {
        return $value === null ? [] : (is_array($value) ? $value : [$value]);
    }

    public static function assertSuccess(object $response): void
    {
        $ret = $response->REQUEST_RETURN ?? null;
        if (!$ret || !isset($ret->RETURN_CODE)) throw new EdmOperationException('unknown', 'EDM işlem sonucu doğrulanamadı. Durumu sorgulayın.');
        if ((string)$ret->RETURN_CODE !== '0') throw new EdmOperationException('business', 'EDM işlemi reddetti. İşlem geçmişindeki hata kodunu kontrol edin.');
    }

    private function call(string $method, array $params = [], bool $authenticate = true, bool $mutation = false, ?int $invoiceId = null, bool $isRetry = false): object
    {
        if ($authenticate) $this->login();
        $params = ['REQUEST_HEADER' => $this->header($method)] + $params;
        try {
            $result = $this->getClient()->$method((object)$params);
            $result = $result->{$method . 'Result'} ?? $result;
            if (is_array($result) && $method === 'CheckUser') $result = (object)['USER' => $result];
            if (!is_object($result)) throw new EdmOperationException('unknown', 'EDM yanıt biçimi doğrulanamadı.');
            if ($mutation) self::assertSuccess($result);
            $this->log($method, $invoiceId, $params, $result, 'BASARILI');
            return $result;
        } catch (EdmOperationException $e) {
            $this->log($method, $invoiceId, $params, ['kind' => $e->kind], 'BASARISIZ', $e->kind);
            throw $e;
        } catch (\Throwable $e) {
            // Oturum süresi dolmuş veya GetCompany sonrası oturum düşmüşse bir defa yeniden login olup tekrar dene
            if (!$isRetry && $authenticate && $e instanceof SoapFault && (str_contains((string)$e->getMessage(), 'not authenticated') || str_contains((string)($e->faultcode ?? ''), 'InvalidSecurity'))) {
                $this->sessionId = null;
                return $this->call($method, array_diff_key($params, ['REQUEST_HEADER' => 1]), $authenticate, $mutation, $invoiceId, true);
            }
            $business = $e instanceof SoapFault && !empty($e->detail);
            $kind = $business ? 'business' : ($mutation ? 'unknown' : 'connection');
            $detailObj = $business ? $e->detail : null;
            $faultMsg = null;
            if ($detailObj && isset($detailObj->RequestFault->ERROR_SHORT_DES) && !empty($detailObj->RequestFault->ERROR_SHORT_DES)) {
                $faultMsg = (string)$detailObj->RequestFault->ERROR_SHORT_DES;
            } elseif ($detailObj && isset($detailObj->RequestFault->ERROR_LONG_DES) && !empty($detailObj->RequestFault->ERROR_LONG_DES)) {
                $faultMsg = (string)$detailObj->RequestFault->ERROR_LONG_DES;
            } elseif ($e instanceof SoapFault && !empty($e->getMessage())) {
                $faultMsg = $e->getMessage();
            }
            $this->log($method, $invoiceId, $params, $business ? $e->detail : ['kind' => $kind], 'BASARISIZ', $e instanceof SoapFault ? (string)$e->faultcode : $kind);
            throw new EdmOperationException($kind, $faultMsg ?? ($business ? 'EDM işlemi reddetti. İşlem geçmişini kontrol edin.' : ($mutation ? 'EDM işlem sonucu belirsiz. Yeniden denemeden önce durumu sorgulayın.' : 'EDM servisine erişilemedi.')), $e);
        }
    }

    private function log(string $method, ?int $invoiceId, mixed $request, mixed $response, string $status, ?string $code = null): void
    {
        $this->settingsModel?->logSoapAction($this->firmId, $invoiceId, $method,
            json_encode($request, JSON_UNESCAPED_UNICODE), json_encode($response, JSON_UNESCAPED_UNICODE), $status, $code);
    }

    public function login(): string
    {
        if ($this->sessionId) return $this->sessionId;
        if (empty($this->settings['api_username']) || empty($this->settings['api_password_decrypted'])) throw new EdmOperationException('validation', 'EDM kullanıcı adı ve şifresi tanımlanmalıdır.');
        $result = $this->call('Login', ['USER_NAME' => $this->settings['api_username'], 'PASSWORD' => $this->settings['api_password_decrypted']], false);
        if (empty($result->SESSION_ID) || $result->SESSION_ID === '0') throw new EdmOperationException('business', 'EDM oturumu açılamadı.');
        return $this->sessionId = (string)$result->SESSION_ID;
    }

    public function logout(): bool
    {
        if (!$this->sessionId) return true;
        try { $this->call('Logout'); return true; }
        catch (\Throwable $e) { return false; }
        finally { $this->sessionId = null; }
    }

    public function getCompany(?string $vkn = null): object
    {
        try {
            $response = $this->call('GetCompany', [
                'USER_NAME' => $this->settings['api_username'] ?? '', 'PASSWORD' => $this->settings['api_password_decrypted'] ?? '',
                'TAXNUMBER' => $vkn ?? '', 'SETCOMPANYMM' => false, 'DELETECOMPANYMM' => false, 'KEY' => '', 'ISHASH' => false,
            ]);
            $companies = self::items($response->GetCompanyList ?? null);
            foreach ($companies as $company) {
                if ($vkn !== null && (string)($company->VKN ?? '') !== $vkn) continue;
                if (isset($company->REQUEST_RETURN)) self::assertSuccess($company);
                if (isset($company->IS_ACTIVE) && !$company->IS_ACTIVE) continue;
                if (!empty($company->VKN)) return $company;
            }
            throw new EdmOperationException('business', 'EDM hesabında aktif firma bulunamadı.');
        } finally {
            // GetCompany sonrasında EDM oturumu sıfırlanır, bir sonraki istekte otomatik taze login yapılır
            $this->sessionId = null;
        }
    }

    public function checkUser(string $vknTckn): array
    {
        $vknTckn = trim($vknTckn);
        if (!preg_match('/^\d{10,11}$/D', $vknTckn)) throw new EdmOperationException('validation', 'VKN/TCKN 10 veya 11 haneli olmalıdır.');
        $result = $this->call('CheckUser', ['USER' => (object)['IDENTIFIER' => $vknTckn]]);
        $users = self::items($result->GIBUSER ?? $result->USER ?? $result->Items ?? null);
        $active = array_values(array_filter($users, static fn($u) => empty($u->ALIAS_REMOVAL_TIME) && trim((string)($u->IDENTIFIER ?? $vknTckn)) === $vknTckn));
        $aliases = [];
        $senderAliases = [];
        foreach ($active as $u) {
            if (empty($u->ALIAS)) continue;
            if (($u->UNIT ?? '') === 'PK') $aliases[] = trim($u->ALIAS);
            if (($u->UNIT ?? '') === 'GB') $senderAliases[] = trim($u->ALIAS);
        }
        $aliases = array_values(array_unique($aliases));
        return ['is_einvoice_user' => count($active) > 0, 'vkn_tckn' => $vknTckn,
            'title' => $active[0]->TITLE ?? '', 'aliases' => $aliases,
            'sender_aliases' => array_values(array_unique($senderAliases)), 'default_alias' => $aliases[0] ?? null];
    }

    public function sendInvoice(string $xmlContent, string $receiverVkn, string $receiverAlias, ?string $senderVkn = null, ?string $senderAlias = null, ?int $faturaId = null, ?string $uuid = null): array
    {
        try {
            $response = $this->call('SendInvoice', [
                'SENDER' => (object)['vkn' => $senderVkn, 'alias' => $senderAlias],
                'RECEIVER' => (object)['vkn' => $receiverVkn, 'alias' => $receiverAlias],
                'INVOICE' => [(object)['CONTENT' => $xmlContent, 'UUID' => $uuid]],
            ], true, true, $faturaId);
            $item = self::items($response->INVOICE ?? null)[0] ?? null;
            return ['success' => true, 'fatura_no' => $item->ID ?? null, 'guid' => $item->UUID ?? $uuid];
        } catch (EdmOperationException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'kind' => $e->kind];
        }
    }

    public function respondInvoice(string $uuid, string $response, string $reason, int $invoiceId): void
    {
        if (!in_array($response, ['KABUL', 'RED'], true) || ($response === 'RED' && trim($reason) === '')) throw new EdmOperationException('validation', 'Geçerli yanıt ve ret gerekçesi gereklidir.');
        $params = ['STATUS' => $response, 'INVOICE' => [(object)['UUID' => $uuid]]];
        if ($response === 'RED') $params['DESCRIPTION'] = [$reason];
        $this->call('SendInvoiceResponseWithServerSign', $params, true, true, $invoiceId);
    }

    public function cancelInvoice(string $uuid, int $invoiceId): void
    {
        $this->call('CancelInvoice', ['INVOICE' => [(object)['UUID' => $uuid]]], true, true, $invoiceId);
    }

    public function checkCounter(): ?int
    {
        $result = $this->call('CheckCounter');
        return isset($result->COUNTER_LEFT) ? (int)$result->COUNTER_LEFT : null;
    }

    public function responseDates(string $number, string $start, string $end): array
    {
        $result = $this->call('GetInvoiceResponseDate', ['INVOICERESPONSEDATE_SEARCH_KEY' => (object)[
            'INVOICERESPONSESTARTDATE' => $start, 'INVOICERESPONSEENDDATE' => $end, 'INVOICENUMBER' => $number,
        ]]);
        return self::items($result->Items->Items ?? null);
    }

    public function getInvoiceStatus(array $uuids): array
    {
        $results = [];
        // The WSDL accepts one INVOICE, not an array.
        foreach ($uuids as $uuid) {
            try {
                $result = $this->call('GetInvoiceStatus', ['INVOICE' => (object)['UUID' => $uuid]]);
                foreach (self::items($result->INVOICE_STATUS ?? null) as $item) {
                    if ((string)($item->UUID ?? '') !== $uuid) continue;
                    $results[$uuid] = [
                        'uuid' => $uuid, 'fatura_no' => $item->ID ?? '', 'status' => $item->STATUS ?? '',
                        'status_code' => $item->STATUS ?? '', 'status_desc' => $item->STATUS_DESCRIPTION ?? '',
                        'gib_code' => $item->GIB_STATUS_CODE ?? null, 'gib_desc' => $item->GIB_STATUS_DESCRIPTION ?? '',
                        'response_code' => $item->RESPONSE_CODE ?? '', 'envelope_id' => $item->ENVELOPE_IDENTIFIER ?? null,
                        'report_status' => $item->EARCHIVE_REPORT_STATUS ?? null, 'report_desc' => $item->EARCHIVE_REPORT_STATUS_DESC ?? null,
                        'cancel_report_status' => $item->EARCHIVE_CANCEL_REPORT_STATUS ?? null, 'cancel_report_desc' => $item->EARCHIVE_CANCEL_REPORT_STATUS_DESC ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // Fatura EDM'de henüz kayıtlı değilse veya sorgu hata verirse atla
                continue;
            }
        }
        return $results;
    }

    public static function decodeContent(mixed $content): string
    {
        if (is_object($content)) $content = $content->_ ?? $content->Value ?? '';
        if (!is_string($content)) return '';
        if (str_starts_with(ltrim($content), '<') || str_starts_with($content, '%PDF-')) return $content;
        $raw = str_starts_with($content, "\x1f\x8b") ? $content : (base64_decode($content, true) ?: $content);
        return str_starts_with($raw, "\x1f\x8b") ? (@gzdecode($raw) ?: '') : $raw;
    }

    public function findOutgoingInvoiceByNumber(string $number): ?array
    {
        if (!preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D', $number)) throw new EdmOperationException('validation', 'Fatura numarası geçersiz.');
        $response = $this->call('GetInvoice', [
            'INVOICE_SEARCH_KEY' => (object)['ID' => $number, 'DIRECTION' => 'OUT', 'READ_INCLUDED' => true, 'LIMIT' => 50],
            'HEADER_ONLY' => 'Y', 'INVOICE_CONTENT_TYPE' => 'XML',
        ]);
        if (isset($response->REQUEST_RETURN->RETURN_CODE) && (string)$response->REQUEST_RETURN->RETURN_CODE !== '0') throw new EdmOperationException('business', 'EDM fatura numarası kontrolü başarısız.');
        foreach (self::items($response->INVOICE ?? null) as $item) {
            if ((string)($item->ID ?? '') !== $number) continue;
            $uuid = trim((string)($item->UUID ?? ''));
            if ($uuid === '') throw new EdmOperationException('business', 'EDM fatura kimliği doğrulanamadı.');
            return ['uuid' => $uuid, 'status' => (string)($item->HEADER->STATUS ?? '')];
        }
        return null;
    }

    public function getInvoiceXml(string $uuid, string $direction = 'OUT'): string
    {
        $result = $this->call('GetInvoice', [
            'INVOICE_SEARCH_KEY' => (object)['UUID' => $uuid, 'DIRECTION' => $direction, 'READ_INCLUDED' => true, 'LIMIT' => 1],
            'HEADER_ONLY' => 'N', 'INVOICE_CONTENT_TYPE' => 'XML',
        ]);
        $item = self::items($result->INVOICE ?? null)[0] ?? null;
        $xml = self::decodeContent($item->CONTENT ?? null);
        if (!$item || (string)($item->UUID ?? '') !== $uuid || empty($xml)) {
            throw new EdmOperationException('business', 'Bu faturanın XML içeriği EDM’den alınamadı.');
        }
        return $xml;
    }

    public function getInvoiceHtml(string $uuid, string $direction = 'OUT'): string
    {
        $result = $this->call('GetInvoice', [
            'INVOICE_SEARCH_KEY' => (object)['UUID' => $uuid, 'DIRECTION' => $direction, 'READ_INCLUDED' => true, 'LIMIT' => 1],
            'HEADER_ONLY' => 'N', 'INVOICE_CONTENT_TYPE' => 'HTML',
        ]);
        $item = self::items($result->INVOICE ?? null)[0] ?? null;
        $html = self::decodeContent($item->CONTENT ?? null);
        if (!$item || (string)($item->UUID ?? '') !== $uuid || empty($html)) {
            throw new EdmOperationException('business', 'Bu faturanın HTML içeriği EDM’den alınamadı.');
        }
        return $html;
    }

    public function getInvoicePdf(string $uuid, string $direction): string
    {
        $result = $this->call('GetInvoice', [
            'INVOICE_SEARCH_KEY' => (object)['UUID' => $uuid, 'DIRECTION' => $direction, 'READ_INCLUDED' => true, 'LIMIT' => 1],
            'HEADER_ONLY' => 'N', 'INVOICE_CONTENT_TYPE' => 'PDF',
        ]);
        $item = self::items($result->INVOICE ?? null)[0] ?? null;
        $pdf = self::decodeContent($item->CONTENT ?? null);
        if (!$item || (string)($item->UUID ?? '') !== $uuid || !str_starts_with($pdf, '%PDF-')) throw new EdmOperationException('business', 'Bu faturanın PDF belgesi EDM’den alınamadı. Taslaklar için önizlemeyi kullanın.');
        return $pdf;
    }

    /** Fetch one page without holding the entire date range in memory. */
    public function getInvoicePage(string $direction, string $startDate, string $endDate, int $offset = 0, int $limit = 50, ?string $createdBefore = null, ?string $listType = null): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $start > $end || $offset < 0 || !in_array($direction, ['IN', 'OUT'], true)) {
            throw new EdmOperationException('validation', 'Geçersiz tarih aralığı, yön veya sayfa.');
        }
        $endTime = $end->format('Y-m-d\T23:59:59');
        if ($createdBefore !== null) {
            $cutoff = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $createdBefore);
            if (!$cutoff || $cutoff->format('Y-m-d\TH:i:s') !== $createdBefore) throw new EdmOperationException('validation', 'Geçersiz aktarım üst tarihi.');
            $endTime = min($endTime, $createdBefore);
            if ($endTime < $start->format('Y-m-d\T00:00:00')) return [];
        }
        if ($listType !== null && !in_array($listType, ['taslak', 'giden'], true)) throw new EdmOperationException('validation', 'Geçersiz aktarım türü.');
        $response = $this->call('GetInvoice', [
            'INVOICE_SEARCH_KEY' => (object)(($listType === 'taslak' ? ['CONNECTORSTATUSDESCRIPTION' => 'LOAD - SUCCEED'] : []) + [
                'LIMIT' => max(1, min(50, $limit)), 'OFFSET' => $offset,
                'DIRECTION' => $direction, 'READ_INCLUDED' => true,
                'CR_START_DATE' => $start->format('Y-m-d\T00:00:00'),
                'CR_END_DATE' => $endTime,
            ]),
            'HEADER_ONLY' => $listType === 'giden' ? 'Y' : 'N', 'INVOICE_CONTENT_TYPE' => 'XML',
        ]);
        if (isset($response->REQUEST_RETURN->RETURN_CODE) && (string)$response->REQUEST_RETURN->RETURN_CODE !== '0') {
            throw new EdmOperationException('business', 'EDM fatura listesini döndürmedi. Servis işlem sonucunu kontrol edin.');
        }
        $items = self::items($response->INVOICE ?? null);
        $result = [];
        foreach ($items as $item) {
            $uuid = trim((string)($item->UUID ?? ''));
            if ($uuid === '') throw new EdmOperationException('business', 'EDM sayfasında ETTN bilgisi eksik.');
            $header = $item->HEADER ?? (object)[];
            $result[] = [
                'uuid' => $uuid, 'xml' => self::decodeContent($item->CONTENT ?? null),
                'status' => $header->STATUS ?? '', 'status_desc' => $header->STATUS_DESCRIPTION ?? '',
            ];
        }
        return $result;
    }

    public function getSyncResult(): array { return $this->syncResult; }

    public function getInvoices(string $direction = 'OUT', ?string $startDate = null, ?string $endDate = null, int $limit = 500, string $dateType = 'CREATE', string $headerOnly = 'N'): array
    {
        $this->syncResult = ['complete' => true, 'errors' => []];
        $startStr = $startDate ?: date('Y-m-d');
        $endStr = $endDate ?: date('Y-m-d');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startStr);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endStr);
        if (!$start || !$end || $start->format('Y-m-d') !== $startStr || $end->format('Y-m-d') !== $endStr || $start > $end || !in_array($direction, ['IN', 'OUT'], true)) {
            throw new EdmOperationException('validation', 'Geçersiz tarih aralığı veya yön.');
        }

        $all = [];
        $seen = [];
        $pageSize = max(1, min(100, $limit));

        // Tarih aralığını tek sorguda sayfala; boş günler için ayrı SOAP çağrısı yapma.
        // OFFSET aynı saniyede oluşturulan ve sunucu limitini aşan kayıtları da korur.
        $offset = 0;
        $previousPageSignature = null;
        $effectivePageSize = null;

        while (true) {
            $key = [
                'LIMIT'         => $pageSize,
                'OFFSET'        => $offset,
                'DIRECTION'     => $direction,
                'READ_INCLUDED' => true
            ];
            if ($dateType === 'CREATE') {
                $key += [
                    'CR_START_DATE' => $start->format('Y-m-d\T00:00:00'),
                    'CR_END_DATE'   => $end->format('Y-m-d\T23:59:59')
                ];
            } else {
                $key += [
                    'START_DATE' => $start->format('Y-m-d'),
                    'END_DATE'   => $end->format('Y-m-d')
                ];
            }

            try {
                $response = $this->call('GetInvoice', ['INVOICE_SEARCH_KEY' => (object)$key, 'HEADER_ONLY' => $headerOnly, 'INVOICE_CONTENT_TYPE' => 'XML']);
                $items = self::items($response->INVOICE ?? null);
            } catch (EdmOperationException $e) {
                $this->syncResult['complete'] = false;
                $this->syncResult['errors'][] = ['date' => $startStr, 'end_date' => $endStr, 'message' => $e->getMessage()];
                break;
            }

            $pageUuids = [];
            foreach ($items as $item) {
                $uuid = trim((string)($item->UUID ?? ''));
                if (!$uuid) continue;
                $hdr = $item->HEADER ?? (object)[];
                $issueDate = (string)($hdr->ISSUE_DATE ?? '');
                if ($dateType === 'ISSUE' && $issueDate !== '') {
                    $itemDateOnly = substr($issueDate, 0, 10);
                    if ($itemDateOnly < $startStr || $itemDateOnly > $endStr) {
                        continue;
                    }
                }
                $pageUuids[] = $uuid;
                if (isset($seen[$uuid])) continue;
                $seen[$uuid] = true;
                $all[$uuid] = [
                    'uuid'           => $uuid,
                    'fatura_no'      => $item->ID ?? '',
                    'xml'            => isset($item->CONTENT) ? self::decodeContent($item->CONTENT) : '',
                    'header'         => $hdr,
                    'status'         => $hdr->STATUS ?? '',
                    'status_desc'    => $hdr->STATUS_DESCRIPTION ?? '',
                    'issue_date'     => $issueDate,
                    'profile_id'     => $hdr->PROFILEID ?? '',
                    'payable_amount' => $hdr->PAYABLE_AMOUNT->_ ?? ($hdr->PAYABLE_AMOUNT ?? 0),
                    'supplier'       => $hdr->SUPPLIER ?? '',
                    'customer'       => $hdr->CUSTOMER ?? '',
                    'sender'         => $hdr->SENDER ?? '',
                    'receiver'       => $hdr->RECEIVER ?? ''
                ];
            }

            if (!$items) break;

            // EDM bazı hesaplarda istenen LIMIT'ten daha düşük (ör. 50)
            // sabit bir sunucu sayfa boyutu uyguluyor. İlk sayfa bu gerçek
            // boyutu belirler; yalnızca bundan kısa bir sayfa son sayfadır.
            $effectivePageSize ??= count($items);
            if (count($items) < $effectivePageSize) break;

            $pageSignature = hash('sha256', implode("\n", $pageUuids));
            if (!$pageUuids || $pageSignature === $previousPageSignature) {
                $this->syncResult['complete'] = false;
                $this->syncResult['errors'][] = [
                    'date' => $startStr, 'end_date' => $endStr,
                    'message' => 'EDM sayfalaması ilerlemedi; aynı kayıtlar tekrar döndü.'
                ];
                break;
            }
            $previousPageSignature = $pageSignature;
            $offset += count($items);
        }

        return array_values($all);
    }
}
