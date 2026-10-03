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
        foreach (['yon','belge_turu','fatura_profili','fatura_tipi','ettn','fatura_no','fatura_tarihi','duzenleme_saati','vade_tarihi','alici_vkn_tckn','alici_unvan','alici_vergi_dairesi','alici_adres','alici_il','alici_ilce','alici_ulke','alici_eposta','alici_telefon','alici_posta_kutusu','para_birimi','doviz_kuru','satir_toplami','iskonto_toplami','kdv_matrahi','hesaplanan_kdv','tevkifat_tutari','odenecek_tutar','notlar','siparis_no','siparis_tarihi','irsaliye_no','irsaliye_tarihi','iade_fatura_no','iade_fatura_tarihi','ubl_xml_path','pdf_path','kaynak_xml','entegrator_durum_kodu','edm_durum','zarf_id','earsiv_rapor_durum','earsiv_rapor_aciklama','earsiv_iptal_rapor_durum','earsiv_iptal_rapor_aciklama','islem_belirsiz','gib_durum_kodu','gib_durum_aciklamasi','edm_referans_no','ticari_yanit'] as $name) $columns[] = "$name TEXT";
        $this->db->exec('CREATE TABLE faturalar(' . implode(',', $columns) . ', UNIQUE(firm_id,ettn))');
        $columns = ['id INTEGER PRIMARY KEY AUTOINCREMENT','fatura_id INT','sira_no INT','is_active INT DEFAULT 1','deleted_at TEXT'];
        foreach (['urun_hizmet_adi','urun_kodu','miktar','birim','birim_fiyat','iskonto_orani','iskonto_tutari','kdv_orani','kdv_tutari','tevkifat_kodu','tevkifat_orani','tevkifat_tutari','istisna_kodu','istisna_aciklama','satir_toplami'] as $name) $columns[] = "$name TEXT";
        $this->db->exec('CREATE TABLE fatura_satirlari(' . implode(',', $columns) . ')');
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
