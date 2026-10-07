<?php
use App\Model\EInvoiceModel;
use App\Service\InvoiceCalculationService;
use App\Service\UblGeneratorService;
use App\Service\UblReaderService;
use PHPUnit\Framework\TestCase;

final class InvoiceSqlitePdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
final class InvoiceSqliteModel extends EInvoiceModel
{
    public function __construct(PDO $db) { $this->db = $db; }
}

final class SyncPersistenceSettings extends \App\Model\EInvoiceSettingsModel
{
    public function __construct() {}
}

final class EInvoicePersistenceTest extends TestCase
{
    private PDO $db;
    private EInvoiceModel $model;
    protected function setUp(): void
    {
        $this->db = new InvoiceSqlitePdo('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->sqliteCreateFunction('NOW', fn() => '2026-10-02 12:00:00');
        $this->db->exec('CREATE TABLE cari(id INTEGER PRIMARY KEY, CariAdi TEXT, Telefon TEXT, Email TEXT)');
        $columns = ['id INTEGER PRIMARY KEY AUTOINCREMENT','firm_id INT','cari_id INT','olusturan_user_id INT','is_active INT DEFAULT 1','deleted_at TEXT','created_at TEXT','updated_at TEXT'];
        foreach (['yon','belge_turu','fatura_profili','fatura_tipi','ettn','fatura_no','seri_no','fatura_tarihi','duzenleme_saati','vade_tarihi','alici_vkn_tckn','alici_unvan','alici_vergi_dairesi','alici_adres','alici_il','alici_ilce','alici_ulke','alici_eposta','alici_telefon','alici_posta_kutusu','para_birimi','doviz_kuru','satir_toplami','iskonto_toplami','kdv_matrahi','hesaplanan_kdv','tevkifat_tutari','odenecek_tutar','notlar','siparis_no','siparis_tarihi','irsaliye_no','irsaliye_tarihi','iade_fatura_no','iade_fatura_tarihi','ubl_xml_path','pdf_path','kaynak_xml','entegrator_durum_kodu','edm_durum','zarf_id','earsiv_rapor_durum','earsiv_rapor_aciklama','earsiv_iptal_rapor_durum','earsiv_iptal_rapor_aciklama','islem_belirsiz','gib_durum_kodu','gib_durum_aciklamasi','edm_referans_no','ticari_yanit'] as $name) $columns[] = "$name TEXT";
        $this->db->exec('CREATE TABLE faturalar(' . implode(',', $columns) . ', UNIQUE(firm_id,ettn))');
        $columns = ['id INTEGER PRIMARY KEY AUTOINCREMENT','fatura_id INT','sira_no INT','is_active INT DEFAULT 1','deleted_at TEXT'];
        foreach (['urun_hizmet_adi','urun_kodu','miktar','birim','birim_fiyat','iskonto_orani','iskonto_tutari','kdv_orani','kdv_tutari','tevkifat_kodu','tevkifat_orani','tevkifat_tutari','istisna_kodu','istisna_aciklama','satir_toplami'] as $name) $columns[] = "$name TEXT";
        $this->db->exec('CREATE TABLE fatura_satirlari(' . implode(',', $columns) . ')');
        $this->db->exec('CREATE TABLE IF NOT EXISTS fatura_tahsilatlari(id INTEGER PRIMARY KEY AUTOINCREMENT, firm_id INT, fatura_id INT, kasa_id INT, tutar REAL, islem_tarihi TEXT, tahsilat_tipi TEXT, aciklama TEXT, para_birimi TEXT, is_active INT DEFAULT 1, deleted_at TEXT, created_by INT, created_at TEXT)');
        $this->model = new InvoiceSqliteModel($this->db);
    }
    private function header(): array
    {
        return ['ettn'=>'12345678-1234-4234-8234-123456789012','yon'=>'GIDEN','belge_turu'=>'EFATURA','fatura_profili'=>'TICARIFATURA','fatura_tipi'=>'SATIS','fatura_no'=>'ERS2026000000001','fatura_tarihi'=>'2026-10-02','duzenleme_saati'=>'12:00:00','alici_vkn_tckn'=>'9876543210','alici_unvan'=>'Alıcı','alici_adres'=>'Adres','alici_il'=>'İstanbul','alici_ilce'=>'Şişli','alici_ulke'=>'Türkiye','para_birimi'=>'TRY','doviz_kuru'=>'1'];
    }
    private function lines(): array { return [['urun_hizmet_adi'=>'Gerçek kalem','miktar'=>'2','birim_fiyat'=>'100','birim'=>'C62','kdv_orani'=>'20','iskonto_orani'=>'10']]; }
    public function testDraftCreateEditAndSoftDeleteKeepArithmeticConsistent(): void
    {
        $id=$this->model->createInvoice(2,$this->header(),$this->lines(),3);
        self::assertNotNull($id);
        $invoice=$this->model->getInvoiceById($id,2); self::assertSame('216.00',$invoice['odenecek_tutar']);
        self::assertNull($this->model->getInvoiceById($id,9));
        $lines=$this->lines(); $lines[0]['miktar']='3';
        self::assertTrue($this->model->updateInvoice($id,2,$this->header(),$lines,3));
        $invoice=$this->model->getInvoiceById($id,2); self::assertSame('324.00',$invoice['odenecek_tutar']); self::assertCount(1,$invoice['satirlar']);
        self::assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM fatura_satirlari')->fetchColumn());
        self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM fatura_satirlari WHERE is_active=0 AND deleted_at IS NOT NULL')->fetchColumn());
        self::assertTrue($this->model->deleteDraftInvoice($id,2)); self::assertNull($this->model->getInvoiceById($id,2));
        self::assertFalse($this->model->deleteDraftInvoice($id,2));
    }

    public function testDraftPersistsVatCalculatedFromPreciseInvoiceTotal(): void
    {
        $lines = [
            ['urun_hizmet_adi'=>'A','miktar'=>'1','birim_fiyat'=>'9166.6667','birim'=>'C62','kdv_orani'=>'20'],
            ['urun_hizmet_adi'=>'B','miktar'=>'1','birim_fiyat'=>'1666.6667','birim'=>'C62','kdv_orani'=>'20'],
            ['urun_hizmet_adi'=>'C','miktar'=>'1','birim_fiyat'=>'1666.6667','birim'=>'C62','kdv_orani'=>'20'],
        ];
        $id = $this->model->createInvoice(2, $this->header(), $lines, 3);

        self::assertNotNull($id);
        $invoice = $this->model->getInvoiceById($id, 2);
        self::assertSame('12500.01', $invoice['satir_toplami']);
        self::assertSame('12500.00', $invoice['kdv_matrahi']);
        self::assertSame('2500.00', $invoice['hesaplanan_kdv']);
        self::assertSame('15000.00', $invoice['odenecek_tutar']);
        self::assertSame(['1833.33', '333.33', '333.33'], array_column($invoice['satirlar'], 'kdv_tutari'));
    }

    public function testDraftPersistsSelectedEdmSeries(): void
    {
        $header = $this->header();
        $header['seri_no'] = 'YDF';
        $id = $this->model->createInvoice(2, $header, $this->lines(), 3);

        self::assertNotNull($id);
        self::assertSame('YDF', $this->model->getInvoiceById($id, 2)['seri_no']);
    }
    public function testImportedSourceAmountsArePreservedOnRepeatedImport(): void
    {
        $calculated=(new InvoiceCalculationService())->calculate($this->lines());
        $xml=(new UblGeneratorService())->generateInvoiceXml($this->header()+$calculated['header'],['vkn_tckn'=>'1234567890','unvan'=>'Satıcı','adres'=>'Adres','il'=>'İstanbul','ilce'=>'Şişli','ulke'=>'Türkiye'],$calculated['lines']);
        // A source payable may contain a separately reported round-off. Never recalculate it.
        $xml=str_replace('>216.00</cbc:PayableAmount>','>216.01</cbc:PayableAmount>',$xml);
        $source=(new UblReaderService())->read($xml,'GELEN');
        $source['header']['entegrator_durum_kodu']='BEKLIYOR';
        $id=$this->model->importInvoice(2,$source['header'],$source['lines'],3);
        self::assertSame('216.01',$this->model->getInvoiceById($id,2)['odenecek_tutar']);
        $this->model->updateInvoiceStatus($id,2,['ticari_yanit'=>'KABUL','entegrator_durum_kodu'=>'ONAYLANDI']);
        self::assertSame($id,$this->model->importInvoice(2,$source['header'],$source['lines'],3));
        $stored=$this->model->getInvoiceById($id,2); self::assertCount(1,$stored['satirlar']); self::assertSame('KABUL',$stored['ticari_yanit']); self::assertSame('ONAYLANDI',$stored['entegrator_durum_kodu']);
        self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM faturalar')->fetchColumn());
        $other=$this->model->importInvoice(4,$source['header'],$source['lines'],3); self::assertNotSame($id,$other);
    }
    public function testBackgroundImporterUpdatesStatusWithoutDuplicatingApprovedInvoice(): void
    {
        $this->db->sqliteCreateFunction('GET_LOCK', fn() => 1);
        $this->db->sqliteCreateFunction('RELEASE_LOCK', fn() => 1);
        $root = sys_get_temp_dir() . '/efatura-sync-' . bin2hex(random_bytes(8));
        $calculated = (new InvoiceCalculationService())->calculate($this->lines());
        $xml = (new UblGeneratorService())->generateInvoiceXml($this->header() + $calculated['header'], [
            'vkn_tckn' => '1234567890', 'unvan' => 'Satıcı', 'adres' => 'Adres', 'il' => 'İstanbul', 'ilce' => 'Şişli', 'ulke' => 'Türkiye',
        ], $calculated['lines']);
        $item = ['uuid' => $this->header()['ettn'], 'xml' => $xml, 'status' => 'LOAD - SUCCEED', 'status_desc' => 'Taslak'];
        $service = new \App\Service\EInvoiceService($this->model, new SyncPersistenceSettings(), fn() => throw new RuntimeException('No network expected'), $root);
        try {
            self::assertSame('added_count', $service->importSyncedInvoice(2, $item, 3));
            $invoice = $this->model->getInvoiceByEttn($item['uuid'], 2);
            self::assertSame(3, (int)$invoice['olusturan_user_id']);
            self::assertFileExists($invoice['ubl_xml_path']);
            $item['status'] = 'SEND - SUCCEED';
            self::assertSame('updated_count', $service->importSyncedInvoice(2, $item, 3));
            self::assertSame('GONDERILDI', $this->model->getInvoiceByEttn($item['uuid'], 2)['entegrator_durum_kodu']);
            $this->model->updateInvoiceStatus((int)$invoice['id'], 2, ['entegrator_durum_kodu' => 'ONAYLANDI', 'ticari_yanit' => 'KABUL']);
            $item['status'] = 'LOAD - SUCCEED';
            $service->importSyncedInvoice(2, $item, 3);
            $stored = $this->model->getInvoiceByEttn($item['uuid'], 2);
            self::assertSame('ONAYLANDI', $stored['entegrator_durum_kodu']);
            self::assertSame('KABUL', $stored['ticari_yanit']);
            self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM faturalar')->fetchColumn());
        } finally {
            $path = $root . '/storage/invoices/2/' . date('Y/m') . '/' . $item['uuid'] . '.xml';
            if (is_file($path)) unlink($path);
            for ($dir = dirname($path); str_starts_with($dir, $root); $dir = dirname($dir)) if (is_dir($dir)) rmdir($dir);
        }
    }
    public function testDraftAndOutgoingListsAndSummariesSeparateSentInvoices(): void
    {
        $_ENV['ENCRYPTION_KEY'] ??= str_repeat('ab', 32);
        foreach (['TASLAK', 'GONDERILDI', 'ONAYLANDI'] as $index => $status) {
            $header = $this->header();
            $header['ettn'] = ($index + 1) . '2345678-1234-4234-8234-123456789012';
            $header['fatura_no'] = 'ERS202600000000' . ($index + 1);
            $id = $this->model->createInvoice(2, $header, $this->lines(), 3);
            $this->model->updateInvoiceStatus($id, 2, ['entegrator_durum_kodu' => $status]);
        }
        $draft = $this->model->ajaxList([], 2, 'GIDEN', 'taslak');
        self::assertSame(1, $draft['recordsTotal']);
        self::assertSame(1, $draft['recordsFiltered']);
        self::assertSame(['TASLAK'], array_column($draft['data'], 'entegrator_durum_kodu'));
        $outgoing = $this->model->ajaxList([], 2, 'GIDEN', 'giden');
        self::assertSame(2, $outgoing['recordsTotal']);
        self::assertSame(2, $outgoing['recordsFiltered']);
        $statuses = array_column($outgoing['data'], 'entegrator_durum_kodu'); sort($statuses);
        self::assertSame(['GONDERILDI', 'ONAYLANDI'], $statuses);
        self::assertSame(1, (int)$this->model->getSummaryStats(2, 'GIDEN', 'taslak', '2026-10-01', '2026-10-31')['toplam_adet']);
        self::assertSame(2, (int)$this->model->getSummaryStats(2, 'GIDEN', 'giden', '2026-10-01', '2026-10-31')['toplam_adet']);
    }
    public function testOutgoingSummaryUsesTableFiltersBeforePagination(): void
    {
        $_ENV['ENCRYPTION_KEY'] ??= str_repeat('ab', 32);
        foreach (['TASLAK', 'GONDERILDI', 'BEKLIYOR', 'KUYRUKTA', 'ONAYLANDI'] as $index => $status) {
            $header = $this->header();
            $header['ettn'] = ($index + 1) . '2345678-1234-4234-8234-123456789012';
            $header['fatura_no'] = 'ERS202600000000' . ($index + 1);
            $header['alici_unvan'] = $index === 1 ? 'Hedef firma' : 'Başka firma';
            if ($status === 'ONAYLANDI') $header['fatura_tarihi'] = '2026-09-30';
            $id = $this->model->createInvoice(2, $header, $this->lines(), 3);
            $this->model->updateInvoiceStatus($id, 2, ['entegrator_durum_kodu' => $status]);
        }
        $params = ['baslangic_tarihi' => '2026-10-01', 'bitis_tarihi' => '2026-10-31', 'length' => 1];
        $all = $this->model->ajaxList($params, 2, 'GIDEN', 'giden');
        self::assertCount(1, $all['data']);
        self::assertSame(3, $all['recordsFiltered']);
        self::assertSame(3, (int)$all['summary']['toplam_adet']);
        self::assertSame(3, (int)$all['summary']['bekleyen_adet']);
        self::assertSame(648.0, (float)$all['summary']['bekleyen_tutar']);
        $group = $this->model->ajaxList($params + ['durum_filtre' => 'BEKLEYEN_ILETILEN'], 2, 'GIDEN', 'giden');
        self::assertSame(3, $group['recordsFiltered']);
        $searched = $this->model->ajaxList($params + ['search' => ['value' => 'Hedef']], 2, 'GIDEN', 'giden');
        self::assertSame(1, $searched['recordsFiltered']);
        self::assertSame(1, (int)$searched['summary']['toplam_adet']);
        self::assertSame(216.0, (float)$searched['summary']['bekleyen_tutar']);
        $column = $this->model->ajaxList($params + ['columns' => [4 => ['search' => ['value' => 'Hedef']]]], 2, 'GIDEN', 'giden');
        self::assertSame(1, $column['recordsFiltered']);
        self::assertSame(1, (int)$column['summary']['toplam_adet']);
    }
    public function testImportedAndPendingDraftsCannotEditOrDelete(): void
    {
        $calc=(new InvoiceCalculationService())->calculate($this->lines());
        $header=$this->header()+$calc['header']+['kaynak_xml'=>'source','entegrator_durum_kodu'=>'TASLAK'];
        $id=$this->model->importInvoice(2,$header,$calc['lines'],3);
        self::assertFalse($this->model->updateInvoice($id,2,$this->header(),$this->lines(),3));
        self::assertFalse($this->model->deleteDraftInvoice($id,2));
        $header=$this->header(); $header['ettn']='22345678-1234-4234-8234-123456789012';
        $id=$this->model->createInvoice(2,$header,$this->lines(),3);
        $this->model->updateInvoiceStatus($id,2,['islem_belirsiz'=>'GONDERIM']);
        self::assertFalse($this->model->deleteDraftInvoice($id,2)); self::assertFalse($this->model->updateInvoice($id,2,$header,$this->lines(),3));
    }
}
