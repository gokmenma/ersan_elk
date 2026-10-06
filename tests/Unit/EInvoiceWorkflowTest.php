<?php

use App\Helper\EInvoiceSecurity;
use App\Helper\Security;
use App\Model\EInvoiceModel;
use App\Model\EInvoiceSettingsModel;
use App\Service\EdmOperationException;
use App\Service\EdmSoapClient;
use App\Service\EInvoiceService;
use App\Service\InvoiceCalculationService;
use App\Service\InvoiceStatusService;
use App\Service\InvoiceValidationService;
use App\Service\UblGeneratorService;
use App\Service\UblReaderService;
use PHPUnit\Framework\TestCase;

final class InvoiceOfflineTransport
{
    public array $requests = [];
    public function __construct(private Closure $handler) {}
    public function __call(string $method, array $args): mixed
    {
        $this->requests[] = [$method, $args[0]];
        return $method === 'Login' ? (object)['SESSION_ID' => 'offline-session'] : ($this->handler)($method, $args[0]);
    }
}

final class InvoiceMemoryModel extends EInvoiceModel
{
    public array $events = [];
    public bool $locked = false;
    public function __construct(public array $invoice) {}
    public function acquireInvoiceLock(int $id, int $firm): bool { if ($this->locked) return false; $this->locked = true; return true; }
    public function releaseInvoiceLock(int $id, int $firm): void { $this->locked = false; }
    public function getInvoiceById(int $id, int $firm): ?array { return $id === 1 && $firm === 2 ? $this->invoice : null; }
    public function reserveSend(int $id, int $firm): bool { $this->invoice['entegrator_durum_kodu'] = 'GONDERILIYOR'; return true; }
    public function updateInvoiceStatus(int $id, int $firm, array $data): bool { if ($firm !== 2) return false; $this->invoice = array_replace($this->invoice, $data); return true; }
    public function recordEvent(int $id, int $firm, string $action, string $result, string $description, ?string $date = null): void { $this->events[] = [$action, $result]; }
}

final class InvoiceMemorySettings extends EInvoiceSettingsModel
{
    public array $numberYears = [];
    private int $lastNumber = 0;
    public function __construct() {}
    public function getSettings(int $firm): ?array { return ['efatura_seri' => 'ERS', 'earsiv_seri' => 'ERA', 'varsayilan_gonderici_alias' => 'urn:mail:gb']; }
    public function reconcileSerial(int $firmId, string $type, string $series, int $year, int $last): void { $this->lastNumber = max($this->lastNumber, $last); }
    public function generateNextInvoiceNumber(int $firm, string $type, string $series, ?int $year = null): string { $this->numberYears[] = $year; return sprintf('%s%04d%09d', $series, $year, ++$this->lastNumber); }
}

class InvoiceOfflineService extends EInvoiceService
{
    public function supplier(int $firm): array { return ['vkn_tckn' => '1234567890', 'unvan' => 'Satıcı A & B', 'adres' => 'Adres', 'il' => 'İstanbul', 'ilce' => 'Şişli', 'ulke' => 'Türkiye']; }
}

final class EInvoiceWorkflowTest extends TestCase
{
    private function settings(): array { return ['api_username' => 'offline', 'api_password_decrypted' => 'not-a-real-password']; }
    private function header(): array
    {
        return ['yon'=>'GIDEN','belge_turu'=>'EFATURA','fatura_profili'=>'TICARIFATURA','fatura_tipi'=>'SATIS',
            'fatura_tarihi'=>'2026-10-02','fatura_no'=>'ERS2026000000001','ettn'=>'12345678-1234-4234-8234-123456789012',
            'alici_vkn_tckn'=>'9876543210','alici_unvan'=>'Alıcı <Test>','alici_adres'=>'Adres','alici_il'=>'İstanbul','alici_ilce'=>'Şişli','alici_ulke'=>'Türkiye',
            'alici_posta_kutusu'=>'urn:mail:pk','para_birimi'=>'TRY','doviz_kuru'=>'1','entegrator_durum_kodu'=>'TASLAK','ticari_yanit'=>'BEKLIYOR', 'islem_belirsiz'=>null];
    }
    private function lines(): array { return [['urun_hizmet_adi'=>'Ürün & Test','miktar'=>'2','birim_fiyat'=>'100','iskonto_orani'=>'10','kdv_orani'=>'20','birim'=>'C62']]; }
    private function xml(array $header = [], array $lines = []): array
    {
        $result = (new InvoiceCalculationService())->calculate($lines ?: $this->lines());
        $invoice = array_replace($this->header(), $header, $result['header']);
        $seller = ['vkn_tckn'=>'1234567890','unvan'=>'Satıcı A & B','adres'=>'Adres','il'=>'İstanbul','ilce'=>'Şişli','ulke'=>'Türkiye'];
        return [(new UblGeneratorService())->generateInvoiceXml($invoice, $seller, $result['lines']), $invoice, $result['lines']];
    }
    public function testIncomingPageUsesSelectedDateTypeAndHeaderOnly(): void
    {
        $transport = new InvoiceOfflineTransport(fn() => (object)['INVOICE' => []]);
        $client = new EdmSoapClient(2, $transport, $this->settings());
        $client->getIncomingInvoicePage('2026-01-01', '2026-10-06', 1000, 'ISSUE');
        $request = $transport->requests[1][1];
        self::assertSame('IN', $request->INVOICE_SEARCH_KEY->DIRECTION);
        self::assertSame(1000, $request->INVOICE_SEARCH_KEY->OFFSET);
        self::assertSame('2026-01-01', $request->INVOICE_SEARCH_KEY->START_DATE);
        self::assertSame('2026-10-06', $request->INVOICE_SEARCH_KEY->END_DATE);
        self::assertSame('Y', $request->HEADER_ONLY);
        $client->getIncomingInvoicePage('2026-01-01', '2026-10-06', 0, 'CREATE');
        $request = $transport->requests[2][1];
        self::assertSame('2026-01-01T00:00:00', $request->INVOICE_SEARCH_KEY->CR_START_DATE);
        self::assertSame('2026-10-06T23:59:59', $request->INVOICE_SEARCH_KEY->CR_END_DATE);
        self::assertFalse(isset($request->INVOICE_SEARCH_KEY->START_DATE));
    }

    public function testHighPrecisionSourceDecimalsAreAcceptedWithoutChangingInvoiceTotals(): void
    {
        [$xml] = $this->xml();
        $xml = preg_replace('/(<cbc:PriceAmount[^>]*>)[^<]+/', '${1}' . '28943.333333333333333333333333', $xml);
        $source = (new UblReaderService())->read($xml, 'GELEN');
        self::assertSame('28943.333333333333333333333333', $source['lines'][0]['birim_fiyat']);
        self::assertSame('216.00', $source['header']['odenecek_tutar']);
    }

    public function testOnlyEmbeddedImageNotesAreCompactedAndOriginalXmlIsPreserved(): void
    {
        [$xml] = $this->xml();
        $picture = 'Variable_Picture:' . base64_encode("\xFF\xD8\xFF" . str_repeat('image-fixture', 6000));
        $xml = str_replace('<cbc:UUID>', '<cbc:Note>Ordinary note</cbc:Note><cbc:Note>' . $picture . '</cbc:Note><cbc:UUID>', $xml);
        $source = (new UblReaderService())->read($xml, 'GELEN');
        self::assertStringContainsString('Ordinary note', $source['header']['notlar']);
        self::assertStringNotContainsString('Variable_Picture:', $source['header']['notlar']);
        self::assertSame($xml, $source['header']['kaynak_xml']);
        self::assertLessThan(200, strlen($source['header']['notlar']));
        self::assertSame('Variable_Picture:ordinary text', UblReaderService::compactNotes('Variable_Picture:ordinary text'));
    }

    public function testDecimalRoundingAndMultipleVatRates(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            ['urun_hizmet_adi'=>'A','miktar'=>'1','birim_fiyat'=>'0.105','kdv_orani'=>'20'],
            ['urun_hizmet_adi'=>'B','miktar'=>'2','birim_fiyat'=>'50','kdv_orani'=>'10','iskonto_orani'=>'5'],
            ['urun_hizmet_adi'=>'C','miktar'=>'1','birim_fiyat'=>'10','kdv_orani'=>'0'],
        ]);
        self::assertSame('105.11', $result['header']['kdv_matrahi']);
        self::assertSame('9.52', $result['header']['hesaplanan_kdv']);
        self::assertSame('114.63', $result['header']['odenecek_tutar']);
    }
    public function testXmlRoundTripDiscountWithholdingAndForeignCurrency(): void
    {
        $lines = $this->lines(); $lines[0] += ['tevkifat_kodu'=>'601','tevkifat_orani'=>'40'];
        [$xml,$invoice,$calculated] = $this->xml(['fatura_tipi'=>'TEVKIFAT','para_birimi'=>'USD','doviz_kuru'=>'34.1234'], $lines);
        (new InvoiceValidationService())->validateXml($xml,$invoice,$calculated);
        $source = (new UblReaderService())->read($xml,'GELEN');
        self::assertSame('36.00',$source['header']['hesaplanan_kdv']);
        self::assertSame('14.40',$source['header']['tevkifat_tutari']);
        self::assertSame('201.60',$source['header']['odenecek_tutar']);
        self::assertSame('USD',$source['header']['para_birimi']);
        self::assertSame('Satıcı A & B',$source['header']['alici_unvan']);
        self::assertSame('Ürün & Test',$source['lines'][0]['urun_hizmet_adi']);
        self::assertSame('20.00',$source['lines'][0]['iskonto_tutari']);
        self::assertStringNotContainsString('validation:Unsigned', $xml);
    }
    public function testUnnumberedEdmDraftRequiresExplicitOutgoingDraftAllowance(): void
    {
        [$xml] = $this->xml();
        $xml = str_replace('<cbc:ID>ERS2026000000001</cbc:ID>', '<cbc:ID/>', $xml);
        $reader = new UblReaderService();
        self::assertNull($reader->read($xml, 'GIDEN', true)['header']['fatura_no']);
        foreach ([['GIDEN', false], ['GELEN', true]] as [$direction, $allow]) {
            try { $reader->read($xml, $direction, $allow); self::fail('Unnumbered non-draft was accepted'); }
            catch (InvalidArgumentException $e) { self::assertSame('XML fatura numarası geçersiz.', $e->getMessage()); }
        }
        $invalid = str_replace('<cbc:ID/>', '<cbc:ID>invalid</cbc:ID>', $xml);
        $this->expectException(InvalidArgumentException::class);
        $reader->read($invalid, 'GIDEN', true);
    }
    public function testImportedQuantityPrecisionDoesNotRecalculateTotals(): void
    {
        [$xml] = $this->xml();
        $xml = preg_replace('/(<cbc:InvoicedQuantity[^>]*>).*?(<\/cbc:InvoicedQuantity>)/', '${1}12.123456789012345${2}', $xml);
        $source = (new UblReaderService())->read($xml, 'GIDEN');
        self::assertSame('12.123456789012345', $source['lines'][0]['miktar']);
        self::assertSame('216.00', $source['header']['odenecek_tutar']);
    }
    public function testExemptionAndReturnReference(): void
    {
        $lines=$this->lines(); $lines[0]['kdv_orani']='0'; $lines[0]['istisna_kodu']='350'; $lines[0]['istisna_aciklama']='Diğer istisna';
        [$xml,$invoice,$calculated]=$this->xml(['fatura_tipi'=>'ISTISNA'],$lines);
        (new InvoiceValidationService())->validateXml($xml,$invoice,$calculated);
        self::assertSame('350',(new UblReaderService())->read($xml,'GIDEN')['lines'][0]['istisna_kodu']);
        [$xml,$invoice,$calculated]=$this->xml(['fatura_tipi'=>'IADE','fatura_profili'=>'TEMELFATURA','iade_fatura_no'=>'OLD2026000000001','iade_fatura_tarihi'=>'2026-09-10']);
        (new InvoiceValidationService())->validateXml($xml,$invoice,$calculated);
        self::assertSame('OLD2026000000001',(new UblReaderService())->read($xml,'GIDEN')['header']['iade_fatura_no']);
    }
    public function testInvalidWithholdingAndMissingExemptionAreRejected(): void
    {
        $line=$this->lines(); $line[0] += ['tevkifat_kodu'=>'601','tevkifat_orani'=>'90'];
        $this->expectException(InvalidArgumentException::class);
        (new InvoiceValidationService())->validateDraft(array_replace($this->header(),['fatura_tipi'=>'TEVKIFAT']),$line);
    }
    public function testEntityAndInvalidXmlAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UblReaderService())->read('<!DOCTYPE Invoice [<!ENTITY x SYSTEM "file:///etc/passwd">]><Invoice>&x;</Invoice>','GELEN');
    }
    public function testEncryptedIdsCsrfAndMasking(): void
    {
        $old=$_ENV['ENCRYPTION_KEY'] ?? null; $session=$_SESSION ?? [];
        try {
            $_ENV['ENCRYPTION_KEY']=str_repeat('ab',32); $_SESSION['csrf_token']='test-token';
            self::assertSame(42,EInvoiceSecurity::invoiceId(Security::encrypt('42')));
            self::assertTrue(EInvoiceSecurity::validCsrf('test-token')); self::assertFalse(EInvoiceSecurity::validCsrf('wrong'));
            self::assertFalse(EInvoiceSecurity::validCsrf([]));
            $masked=EInvoiceSecurity::redact(json_encode(['REQUEST_HEADER'=>['SESSION_ID'=>'SECRET'],'PASSWORD'=>'SECRET','GIB_PASS'=>'SECRET']));
            self::assertStringNotContainsString('SECRET',$masked);
            foreach (['send_invoice','cancel_invoice','respond_commercial','save_settings'] as $action) { self::assertNotNull(EInvoiceSecurity::permission($action)); self::assertFalse(EInvoiceSecurity::readOnly($action)); }
            try { EInvoiceSecurity::invoiceId('42'); self::fail('Plain IDs must fail'); } catch (InvalidArgumentException $e) { self::assertStringContainsString('Şifreli',$e->getMessage()); }
        } finally { $_ENV['ENCRYPTION_KEY']=$old; $_SESSION=$session; }
    }
    public function testBusinessFailureUnknownResultAndSuccessfulSoapResponses(): void
    {
        foreach ([5=>false, 0=>true] as $code=>$expected) {
            $transport=new InvoiceOfflineTransport(fn()=> (object)['REQUEST_RETURN'=>(object)['RETURN_CODE'=>$code],'INVOICE'=>(object)['UUID'=>'uuid','ID'=>'no']]);
            $client=new EdmSoapClient(2,$transport,$this->settings());
            self::assertSame($expected,$client->sendInvoice('<Invoice/>','9876543210','pk','1234567890','gb',1,'uuid')['success']);
        }
        $transport=new InvoiceOfflineTransport(fn()=> (object)[]);
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertSame('unknown',$client->sendInvoice('<Invoice/>','9876543210','pk','1234567890','gb',1,'uuid')['kind']);
        $transport=new InvoiceOfflineTransport(function(){ throw new SoapFault('HTTP','timeout'); });
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertSame('unknown',$client->sendInvoice('<Invoice/>','9876543210','pk','1234567890','gb',1,'uuid')['kind']);
    }
    public function testActiveAliasesAndLookupErrors(): void
    {
        $transport=new InvoiceOfflineTransport(fn()=> (object)['USER'=>[(object)['UNIT'=>'PK','ALIAS'=>'active'],(object)['UNIT'=>'PK','ALIAS'=>'removed','ALIAS_REMOVAL_TIME'=>'2026-01-01'],(object)['UNIT'=>'GB','ALIAS'=>'sender']]]);
        $result=(new EdmSoapClient(2,$transport,$this->settings()))->checkUser('1234567890');
        self::assertSame(['active'],$result['aliases']); self::assertSame(['sender'],$result['sender_aliases']);
        $transport=new InvoiceOfflineTransport(function(){ throw new SoapFault('HTTP','timeout'); });
        $this->expectException(EdmOperationException::class);
        (new EdmSoapClient(2,$transport,$this->settings()))->checkUser('1234567890');
    }
    public function testPagingOverOneHundredSameSecondAndLongDateRange(): void
    {
        $transport=new InvoiceOfflineTransport(function($method,$request) {
            $key=$request->INVOICE_SEARCH_KEY; $items=[];
            if (str_starts_with($key->CR_START_DATE,'2026-01-01')) for($i=$key->OFFSET;$i<min($key->OFFSET+100,205);$i++) $items[]=(object)['UUID'=>'uuid-'.$i,'ID'=>'no-'.$i,'CONTENT'=>'<Invoice/>','HEADER'=>(object)['CDATE'=>'2026-01-01T12:00:00']];
            return (object)['INVOICE'=>$items];
        });
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertCount(205,$client->getInvoices('OUT','2026-01-01','2026-03-15',100));
        self::assertTrue($client->getSyncResult()['complete']);
        self::assertCount(4,$transport->requests); // Login + three pages for the entire range.
        self::assertSame('2026-03-15T23:59:59',$transport->requests[1][1]->INVOICE_SEARCH_KEY->CR_END_DATE);
        self::assertSame('2026-01-01T00:00:00',$transport->requests[1][1]->INVOICE_SEARCH_KEY->CR_START_DATE);
        self::assertSame(100,$transport->requests[2][1]->INVOICE_SEARCH_KEY->OFFSET);
    }
    public function testEmptyLongRangeNeedsOnlyOneInvoiceRequest(): void
    {
        $transport = new InvoiceOfflineTransport(fn() => (object)['INVOICE' => []]);
        $client = new EdmSoapClient(2, $transport, $this->settings());
        self::assertSame([], $client->getInvoices('OUT', '2026-01-01', '2026-03-31', 100, 'ISSUE'));
        self::assertCount(2, $transport->requests);
        $key = $transport->requests[1][1]->INVOICE_SEARCH_KEY;
        self::assertSame('2026-01-01', $key->START_DATE);
        self::assertSame('2026-03-31', $key->END_DATE);
        self::assertTrue($client->getSyncResult()['complete']);
    }
    public function testLaterPageFailurePreservesEarlierInvoices(): void
    {
        $transport = new InvoiceOfflineTransport(function($method, $request) {
            if ($request->INVOICE_SEARCH_KEY->OFFSET > 0) throw new SoapFault('HTTP', 'timeout');
            return (object)['INVOICE' => [(object)['UUID' => 'saved-page', 'CONTENT' => '<Invoice/>']]];
        });
        $client = new EdmSoapClient(2, $transport, $this->settings());
        self::assertCount(1, $client->getInvoices('OUT', '2026-01-01', '2026-03-31'));
        self::assertFalse($client->getSyncResult()['complete']);
        self::assertSame('2026-03-31', $client->getSyncResult()['errors'][0]['end_date']);
    }
    public function testStalledAndPartiallyFailedPagingIsReported(): void
    {
        $items=array_map(fn($i)=>(object)['UUID'=>'uuid-'.$i],range(1,100));
        $transport=new InvoiceOfflineTransport(fn()=> (object)['INVOICE'=>$items]);
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertCount(100,$client->getInvoices('IN','2026-01-01','2026-01-01'));
        self::assertFalse($client->getSyncResult()['complete']);
        self::assertCount(1,$client->getSyncResult()['errors']);
    }
    public function testPagingContinuesWhenEdmCapsResponsesBelowRequestedLimit(): void
    {
        $transport=new InvoiceOfflineTransport(function($method,$request) {
            $key=$request->INVOICE_SEARCH_KEY; $items=[];
            for($i=$key->OFFSET;$i<min($key->OFFSET+50,125);$i++) {
                $items[]=(object)['UUID'=>'capped-'.$i,'ID'=>'no-'.$i,'CONTENT'=>'<Invoice/>','HEADER'=>(object)[]];
            }
            return (object)['INVOICE'=>$items];
        });
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertCount(125,$client->getInvoices('OUT','2026-10-03','2026-10-03',100));
        self::assertSame(50,$transport->requests[2][1]->INVOICE_SEARCH_KEY->OFFSET);
        self::assertSame(100,$transport->requests[3][1]->INVOICE_SEARCH_KEY->OFFSET);
        self::assertTrue($client->getSyncResult()['complete']);
    }
    public function testSingleSyncPageUsesOffsetAndFullXml(): void
    {
        $transport = new InvoiceOfflineTransport(fn() => (object)['INVOICE' => (object)[
            'UUID' => 'uuid-page', 'CONTENT' => base64_encode('<Invoice/>'),
            'HEADER' => (object)['STATUS' => 'LOAD - SUCCEED'],
        ]]);
        $items = (new EdmSoapClient(2, $transport, $this->settings()))->getInvoicePage('OUT', '2026-10-01', '2026-10-04', 50, 100);
        self::assertSame('<Invoice/>', $items[0]['xml']);
        $request = $transport->requests[1][1];
        self::assertSame(50, $request->INVOICE_SEARCH_KEY->OFFSET);
        self::assertSame(50, $request->INVOICE_SEARCH_KEY->LIMIT);
        self::assertSame('2026-10-04T23:59:59', $request->INVOICE_SEARCH_KEY->CR_END_DATE);
        self::assertSame('N', $request->HEADER_ONLY);
    }
    public function testDraftPageFiltersAtEdmAndOutgoingRequestsHeadersOnly(): void
    {
        $transport = new InvoiceOfflineTransport(fn() => (object)['INVOICE' => []]);
        $client = new EdmSoapClient(2, $transport, $this->settings());
        $client->getInvoicePage('OUT', '2026-10-01', '2026-10-04', 0, 50, null, 'taslak');
        self::assertSame('LOAD - SUCCEED', $transport->requests[1][1]->INVOICE_SEARCH_KEY->CONNECTORSTATUSDESCRIPTION);
        self::assertSame('N', $transport->requests[1][1]->HEADER_ONLY);
        $client->getInvoicePage('OUT', '2026-10-01', '2026-10-04', 0, 50, null, 'giden');
        self::assertSame('Y', $transport->requests[2][1]->HEADER_ONLY);
        self::assertFalse(isset($transport->requests[2][1]->INVOICE_SEARCH_KEY->CONNECTORSTATUSDESCRIPTION));
    }

    public function testSyncPagePinsCreationTimeToJobStart(): void
    {
        $transport = new InvoiceOfflineTransport(fn() => (object)['INVOICE' => []]);
        $client = new EdmSoapClient(2, $transport, $this->settings());
        $client->getInvoicePage('OUT', '2026-10-01', '2026-10-04', 0, 50, '2026-10-04T14:25:00');
        self::assertSame('2026-10-04T14:25:00', $transport->requests[1][1]->INVOICE_SEARCH_KEY->CR_END_DATE);
    }
    public function testPdfBinaryAndCounterUnavailableAreHandled(): void
    {
        $transport=new InvoiceOfflineTransport(fn($method)=>$method==='GetInvoice' ? (object)['INVOICE'=>(object)['UUID'=>'uuid','CONTENT'=>base64_encode('%PDF-1.7 offline')]] : (object)[]);
        $client=new EdmSoapClient(2,$transport,$this->settings());
        self::assertStringStartsWith('%PDF-',$client->getInvoicePdf('uuid','IN')); self::assertNull($client->checkCounter());
        $request=$transport->requests[1][1]; self::assertSame('PDF',$request->INVOICE_CONTENT_TYPE); self::assertSame('uuid',$request->INVOICE_SEARCH_KEY->UUID);
    }
    public function testStatusResponseMappingDoesNotInventApproval(): void
    {
        self::assertSame('KABUL',InvoiceStatusService::response('ACCEPT')); self::assertSame('RED',InvoiceStatusService::response('REJECT'));
        self::assertSame('BEKLIYOR',InvoiceStatusService::map(['status'=>'UNRECOGNIZED'])['entegrator_durum_kodu']);
        self::assertSame('ONAYLANDI',InvoiceStatusService::map(['gib_code'=>1300])['entegrator_durum_kodu']);
    }
    private function actionService(InvoiceMemoryModel $model, int $code = 0, bool $timeout = false): array
    {
        $transport=new InvoiceOfflineTransport(function($method) use ($model,$code,$timeout) {
            if ($method==='GetInvoiceStatus') return (object)['INVOICE_STATUS'=>(object)['UUID'=>$model->invoice['ettn'],'STATUS'=>'SEND - SUCCEED','GIB_STATUS_CODE'=>1300]];
            if ($timeout) throw new SoapFault('HTTP','timeout');
            return (object)['REQUEST_RETURN'=>(object)['RETURN_CODE'=>$code]];
        });
        $client=new EdmSoapClient(2,$transport,$this->settings());
        return [new EInvoiceService($model,new InvoiceMemorySettings(),fn()=>$client),$transport];
    }
    public function testResponseFailureDoesNotChangeLocalResponseAndCannotRepeatUnknown(): void
    {
        $invoice=array_replace($this->header(),['yon'=>'GELEN','entegrator_durum_kodu'=>'ONAYLANDI']);
        $model=new InvoiceMemoryModel($invoice); [$service]=$this->actionService($model,5);
        self::assertFalse($service->respondToIncomingInvoice(1,2,'RED','Ret gerekçesi')['success']);
        self::assertSame('BEKLIYOR',$model->invoice['ticari_yanit']); self::assertNull($model->invoice['islem_belirsiz']);
        [$service,$transport]=$this->actionService($model,0,true);
        self::assertFalse($service->respondToIncomingInvoice(1,2,'KABUL')['success']);
        self::assertSame('YANIT:KABUL',$model->invoice['islem_belirsiz']); $count=count($transport->requests);
        self::assertFalse($service->respondToIncomingInvoice(1,2,'KABUL')['success']); self::assertCount($count,$transport->requests);
    }
    public function testSuccessfulResponseAndWrongFirmIsolation(): void
    {
        $model=new InvoiceMemoryModel(array_replace($this->header(),['yon'=>'GELEN','entegrator_durum_kodu'=>'ONAYLANDI']));
        [$service,$transport]=$this->actionService($model);
        self::assertFalse($service->respondToIncomingInvoice(1,3,'KABUL')['success']); self::assertCount(0,$transport->requests);
        self::assertTrue($service->respondToIncomingInvoice(1,2,'KABUL')['success']); self::assertSame('KABUL',$model->invoice['ticari_yanit']);
        $count=count($transport->requests); self::assertFalse($service->respondToIncomingInvoice(1,2,'KABUL')['success']); self::assertCount($count,$transport->requests);
    }
    public function testSentEInvoiceCannotCancelAndFailedArchiveCancelStaysUnchanged(): void
    {
        $model=new InvoiceMemoryModel($this->header()+['ubl_xml_path'=>'offline.xml']); [$service,$transport]=$this->actionService($model);
        self::assertFalse($service->cancelInvoice(1,2,'İptal')['success']); self::assertNotContains('CancelInvoice',array_column($transport->requests,0));
        $model->invoice['belge_turu']='EARSIV'; $model->invoice['entegrator_durum_kodu']='ONAYLANDI';
        [$service]=$this->actionService($model,5); self::assertFalse($service->cancelInvoice(1,2,'İptal')['success']);
        self::assertSame('ONAYLANDI',$model->invoice['entegrator_durum_kodu']);
        [$service]=$this->actionService($model); self::assertTrue($service->cancelInvoice(1,2,'İptal')['success']); self::assertSame('IPTAL',$model->invoice['entegrator_durum_kodu']);
    }
    public function testInvoiceLockRejectsConcurrentOperation(): void
    {
        $model=new InvoiceMemoryModel($this->header()); $model->locked=true; [$service,$transport]=$this->actionService($model);
        self::assertFalse($service->sendInvoice(1,2)['success']); self::assertCount(0,$transport->requests);
    }
    public function testSendTimeoutCannotBeRetriedAndNumberUsesInvoiceYear(): void
    {
        [$xml,$invoice,$lines]=$this->xml(['fatura_tarihi'=>'2025-12-31','fatura_no'=>null]);
        $model=new InvoiceMemoryModel($invoice+['satirlar'=>$lines]); $settings=new InvoiceMemorySettings();
        $transport=new InvoiceOfflineTransport(function($method,$request) use($invoice){
            if($method==='GetCompany')return (object)['GetCompanyList'=>(object)['VKN'=>'1234567890','EFATURA'=>70,'SERIALLİST'=>(object)['SERIAL'=>'ERS','YEAR'=>2025,'ACTIVEFLAG'=>1,'EARCHIVEFLAG'=>0,'LASTSERİAL'=>0]]];
            if($method==='CheckUser')return (object)['USER'=>[(object)['UNIT'=>'GB','ALIAS'=>'urn:mail:gb'],(object)['UNIT'=>'PK','ALIAS'=>'urn:mail:pk']]];
            if($method==='SendInvoice')throw new SoapFault('HTTP','timeout');
            return (object)[];
        });
        $client=new EdmSoapClient(2,$transport,$this->settings());
        $temporaryRoot = sys_get_temp_dir() . '/ersan-efatura-unit-' . bin2hex(random_bytes(6));
        $service=new InvoiceOfflineService($model,$settings,fn()=>$client,$temporaryRoot);
        self::assertFalse($service->sendInvoice(1,2)['success']); self::assertSame([2025],$settings->numberYears);
        self::assertSame('BELIRSIZ',$model->invoice['entegrator_durum_kodu']); self::assertSame('GONDERIM',$model->invoice['islem_belirsiz']);
        $count=count($transport->requests); self::assertFalse($service->sendInvoice(1,2)['success']); self::assertCount($count,$transport->requests);
        foreach (glob($temporaryRoot . '/storage/invoices/2/*/*/*.xml') ?: [] as $file) unlink($file);
        foreach ([$temporaryRoot . '/storage/invoices/2/' . date('Y/m'), $temporaryRoot . '/storage/invoices/2/' . date('Y'), $temporaryRoot . '/storage/invoices/2', $temporaryRoot . '/storage/invoices', $temporaryRoot . '/storage', $temporaryRoot] as $dir) if (is_dir($dir)) rmdir($dir);
    }
    public function testNumberCollisionAllocatesNextNumberAndAlreadySentIsNotResent(): void
    {
        foreach ([false, true] as $sameUuid) {
            [$xml, $invoice, $lines] = $this->xml(['fatura_tarihi' => '2025-12-31', 'fatura_no' => 'ERS2025000000001']);
            $model = new InvoiceMemoryModel($invoice + ['satirlar' => $lines]);
            $transport = new InvoiceOfflineTransport(function($method, $request) use ($invoice, $sameUuid) {
                if ($method === 'GetCompany') return (object)['GetCompanyList' => (object)['VKN'=>'1234567890', 'EFATURA'=>70, 'SERIALLİST'=>(object)['SERIAL'=>'ERS','YEAR'=>2025,'ACTIVEFLAG'=>1,'EARCHIVEFLAG'=>0,'LASTSERİAL'=>0]]];
                if ($method === 'CheckUser') return (object)['USER'=>[(object)['UNIT'=>'GB','ALIAS'=>'urn:mail:gb'],(object)['UNIT'=>'PK','ALIAS'=>'urn:mail:pk']]];
                if ($method === 'GetInvoice' && $request->INVOICE_SEARCH_KEY->ID === 'ERS2025000000001') return (object)['INVOICE'=>(object)['ID'=>'ERS2025000000001','UUID'=>$sameUuid ? $invoice['ettn'] : 'different-uuid','HEADER'=>(object)['STATUS'=>'SEND - SUCCEED']]];
                if ($method === 'SendInvoice') return (object)['REQUEST_RETURN'=>(object)['RETURN_CODE'=>0]];
                return (object)[];
            });
            $temporaryRoot = sys_get_temp_dir() . '/ersan-efatura-unit-' . bin2hex(random_bytes(6));
            $service = new InvoiceOfflineService($model, new InvoiceMemorySettings(), fn() => new EdmSoapClient(2, $transport, $this->settings()), $temporaryRoot);
            $result = $service->sendInvoice(1, 2);
            self::assertTrue($result['success']);
            if ($sameUuid) {
                self::assertNotContains('SendInvoice', array_column($transport->requests, 0));
                self::assertSame('ERS2025000000001', $model->invoice['fatura_no']);
            } else {
                self::assertSame(1, count(array_filter($transport->requests, fn($r) => $r[0] === 'SendInvoice')));
                self::assertSame('ERS2025000000002', $model->invoice['fatura_no']);
                $send = array_values(array_filter($transport->requests, fn($r) => $r[0] === 'SendInvoice'))[0][1];
                self::assertStringContainsString('ERS2025000000002', $send->INVOICE[0]->CONTENT);
            }
            self::assertSame('GONDERILDI', $model->invoice['entegrator_durum_kodu']);
            foreach (glob($temporaryRoot . '/storage/invoices/2/*/*/*.xml') ?: [] as $file) unlink($file);
            foreach ([$temporaryRoot . '/storage/invoices/2/' . date('Y/m'), $temporaryRoot . '/storage/invoices/2/' . date('Y'), $temporaryRoot . '/storage/invoices/2', $temporaryRoot . '/storage/invoices', $temporaryRoot . '/storage', $temporaryRoot] as $dir) if (is_dir($dir)) rmdir($dir);
        }
    }

    public function testTwoStepIncrementalSyncAndXmlFetch(): void
    {
        $transport = new InvoiceOfflineTransport(function($method, $request) {
            if ($method === 'GetInvoice') {
                if (($request->HEADER_ONLY ?? 'N') === 'Y') {
                    return (object)['INVOICE' => [(object)['UUID' => 'test-uuid-1', 'ID' => 'ERS2026000000099', 'HEADER' => (object)['STATUS' => 'SEND - SUCCEED']]]];
                }
                return (object)['INVOICE' => [(object)['UUID' => 'test-uuid-1', 'CONTENT' => base64_encode('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>')]]];
            }
            return (object)[];
        });
        $client = new EdmSoapClient(2, $transport, $this->settings());
        $headers = $client->getInvoices('OUT', '2026-10-01', '2026-10-04', 100, 'CREATE', 'Y');
        self::assertCount(1, $headers);
        self::assertSame('Y', $transport->requests[1][1]->HEADER_ONLY);
        self::assertSame('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>', $client->getInvoiceXml('test-uuid-1', 'OUT'));
    }
}
