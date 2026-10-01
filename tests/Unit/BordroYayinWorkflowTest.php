<?php

use App\Model\BordroYayinModel;
use App\Helper\BordroYayinGuvenlik;
use App\Helper\Security;
use App\Service\BordroYayinIcerikService;
use PHPUnit\Framework\TestCase;

/** SQLite yalnız durum/erişim testleri içindir; MySQL kilit eşzamanlılığı için değildir. */
final class BordroYayinTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace(' FOR UPDATE', '', $query);
        $query = str_replace('DATE_ADD(NOW(), INTERVAL ? DAY)', "datetime(NOW(), '+' || ? || ' days')", $query);
        $query = str_replace('DATE_ADD(NOW(), INTERVAL 1 HOUR)', "datetime(NOW(), '+1 hour')", $query);
        return parent::prepare($query, $options);
    }
}

final class BordroYayinWorkflowTest extends TestCase
{
    private BordroYayinModel $model;
    private PDO $db;
    private mixed $oncekiKey;
    private array $oncekiSession;

    protected function setUp(): void
    {
        $this->oncekiKey = $_ENV['ENCRYPTION_KEY'] ?? null;
        $this->oncekiSession = $_SESSION ?? [];
        $_ENV['ENCRYPTION_KEY'] = str_repeat('ab', 32);
        $_SESSION = ['firma_id' => 1];
        $this->db = new BordroYayinTestPdo('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->sqliteCreateFunction('NOW', fn() => '2026-10-01 12:00:00');
        $this->db->sqliteCreateFunction('IF', fn($c, $yes, $no) => $c ? $yes : $no);
        $this->db->exec(<<<'SQL'
CREATE TABLE personel(id INTEGER PRIMARY KEY, firma_id INT, aktif_mi INT DEFAULT 1, isten_cikis_tarihi TEXT, silinme_tarihi TEXT);
CREATE TABLE bordro_donemi(id INTEGER PRIMARY KEY, firma_id INT, kapali_mi INT, silinme_tarihi TEXT);
CREATE TABLE bordro_yayin(id INTEGER PRIMARY KEY, firma_id INT, donem_id INT, surum INT, durum TEXT DEFAULT 'yayinda', yayinlayan_id INT, yayin_tarihi TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_dokum(id INTEGER PRIMARY KEY, yayin_id INT, personel_id INT, bordro_personel_id INT, icerik TEXT, icerik_hash TEXT, goruntuleme_tarihi TEXT, beyan_tarihi TEXT, beyan_metni TEXT, beyan_metin_surumu INT, is_active INT DEFAULT 1, UNIQUE(yayin_id, personel_id));
CREATE TABLE bordro_yayin_olay(id INTEGER PRIMARY KEY, dokum_id INT, tur TEXT, aktor_tipi TEXT, aktor_id INT, detay TEXT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_talep(id INTEGER PRIMARY KEY, dokum_id INT, mesaj TEXT, durum TEXT DEFAULT 'acik', tarih TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_yanit(id INTEGER PRIMARY KEY, talep_id INT, kullanici_id INT, mesaj TEXT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_kuyruk(id INTEGER PRIMARY KEY, dokum_id INT, gun INT, planlanan TEXT, durum TEXT DEFAULT 'bekliyor', deneme INT DEFAULT 0, kilit_token TEXT, son_hata TEXT, gonderim_tarihi TEXT, is_active INT DEFAULT 1);
CREATE TABLE users(id INTEGER PRIMARY KEY, adi_soyadi TEXT, user_name TEXT);
INSERT INTO personel(id, firma_id) VALUES (1,1),(2,1),(3,2);
INSERT INTO bordro_donemi(id,firma_id,kapali_mi) VALUES (1,1,1),(2,2,1);
INSERT INTO bordro_yayin(id,firma_id,donem_id,surum,durum,yayin_tarihi) VALUES (1,1,1,1,'yayinda','2026-09-30'),(2,2,2,1,'yayinda','2026-09-30');
SQL);
        $i = json_encode(['personel' => 'Test', 'donem' => '2026/09', 'baslangic' => '2026-09-01', 'banka_net_kurus' => 100000, 'kalemler' => [['etiket' => 'Ücret', 'kurus' => 100000]]]);
        $stmt = $this->db->prepare('INSERT INTO bordro_yayin_dokum(id,yayin_id,personel_id,icerik,icerik_hash) VALUES (1,1,1,?,?),(2,2,3,?,?)');
        $stmt->execute([$i,hash('sha256',$i),$i,hash('sha256',$i)]);
        $this->model = (new ReflectionClass(BordroYayinModel::class))->newInstanceWithoutConstructor();
        $this->model->db = $this->db;
    }

    protected function tearDown(): void
    {
        if ($this->oncekiKey === null) unset($_ENV['ENCRYPTION_KEY']); else $_ENV['ENCRYPTION_KEY'] = $this->oncekiKey;
        $_SESSION = $this->oncekiSession;
    }

    private function kayitAdedi(string $table): int { return (int) $this->db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
    private function goruntule(): void { $this->model->personelIslem(1,1,1,'goruntule'); }

    private function yayinKaydet(array $kayitlar, string $hash = 'kontrol'): array
    {
        // Hazırlık hesap testlerinden bağımsız, gerçek kalıcı yayın/queue transaction'ını sınar.
        $kaydet = new ReflectionMethod($this->model, 'yayiniKaydet');
        $atomik = new ReflectionMethod($this->model, 'atomik');
        return $atomik->invoke($this->model, fn() => $kaydet->invoke($this->model,1,1,10,$hash,['hatalar'=>[], 'onizleme_hash'=>'kontrol', 'kayitlar'=>$kayitlar]));
    }

    private function hazirDokum(): array
    {
        return ['personel_id'=>1, 'bordro_id'=>10, 'icerik'=>['personel'=>'Yeni Test', 'donem'=>'2026/09', 'baslangic'=>'2026-09-01', 'banka_net_kurus'=>120000, 'kalemler'=>[['etiket'=>'Ücret','kurus'=>120000]]]];
    }

    public function testYenidenYayinYeniSurumVeUcBildirimOlustururEskiBeyaniKorur(): void
    {
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        $eski = $this->model->detay(1,1,1);
        $this->model->yenidenAc(1,1,10);
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1');
        $r = $this->yayinKaydet([$this->hazirDokum()]);
        self::assertSame(2,$r['surum']);
        $liste = $this->model->personelListe(1,1);
        self::assertCount(2,$liste);
        self::assertNull($liste[0]['beyan_tarihi']);
        self::assertSame('arsiv',$this->model->detay(1,1,1)['durum']);
        self::assertSame($eski['beyan_tarihi'],$this->model->detay(1,1,1)['beyan_tarihi']);
        self::assertSame([0,3,7],$this->db->query('SELECT gun FROM bordro_yayin_kuyruk ORDER BY gun')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(['2026-10-01 12:00:00','2026-10-04 12:00:00','2026-10-08 12:00:00'],$this->db->query('SELECT planlanan FROM bordro_yayin_kuyruk ORDER BY gun')->fetchAll(PDO::FETCH_COLUMN));
        $this->yayinKaydet([$this->hazirDokum()]);
        self::assertSame(3,$this->kayitAdedi('bordro_yayin_dokum'));
        self::assertSame(3,$this->kayitAdedi('bordro_yayin_kuyruk'));
    }

    public function testYayinSirasindakiHataTumYayiniVeKuyruguGeriAlir(): void
    {
        $this->db->exec("UPDATE bordro_yayin SET durum='revizyon' WHERE id=1");
        try { $this->yayinKaydet([$this->hazirDokum(),$this->hazirDokum()]); self::fail('İkinci personel kaydı reddedilmeli.'); }
        catch (PDOException $e) {
            self::assertSame(2,$this->kayitAdedi('bordro_yayin_dokum'));
            self::assertSame(2,$this->kayitAdedi('bordro_yayin'));
            self::assertSame(0,$this->kayitAdedi('bordro_yayin_kuyruk'));
            self::assertSame('revizyon',$this->model->detay(1,1,1)['durum']);
        }
    }

    public function testOnizlemeDegistiyseYayinReddedilir(): void
    {
        $this->expectException(DomainException::class);
        $this->yayinKaydet([$this->hazirDokum()],'eski-onizleme');
    }

    public function testListeYalnizOturumPersoneliniVeSifreliTokeniDondurur(): void
    {
        $rows = $this->model->personelListe(1,1);
        self::assertCount(1, $rows);
        self::assertSame(1, BordroYayinGuvenlik::id($rows[0]['token']));
        self::assertArrayNotHasKey('id', $rows[0]);
        self::assertSame([], $this->model->personelListe(2,1));
    }

    public function testBaskaPersonelinDetayinaErisimReddedilir(): void
    {
        $this->expectException(DomainException::class); $this->model->detay(1,1,2);
    }

    public function testBaskaFirmaninDetayinaErisimReddedilir(): void
    {
        $this->expectException(DomainException::class); $this->model->detay(1,2);
    }

    public function testYonetimTakibiOlaylariVeBildirimDurumunuDondurur(): void
    {
        $this->goruntule();
        $this->db->exec("INSERT INTO bordro_yayin_kuyruk(id,dokum_id,gun,durum,deneme) VALUES(1,1,0,'basarisiz',5)");
        $rows=$this->model->takip(1,1);
        self::assertCount(1,$rows);
        self::assertSame('basarisiz',$rows[0]['ilk_bildirim_durumu']);
        self::assertSame(1,$rows[0]['basarisiz_bildirim']);
        self::assertCount(1,$this->model->detay(1,1)['olaylar']);
        self::assertSame([],$this->model->detay(1,1,1)['olaylar']);
    }

    public function testDetayOkumaVePdfVerisiBeyanVeyaGoruntulemeOlusturmaz(): void
    {
        $d = $this->model->detay(1,1,1);
        self::assertNull($d['beyan_tarihi']); self::assertNull($d['goruntuleme_tarihi']);
        self::assertSame(0, $this->kayitAdedi('bordro_yayin_olay'));
    }

    public function testGoruntulemeVeBeyanTekrarlariTekOlayOlusturur(): void
    {
        $this->goruntule(); $this->goruntule();
        $this->model->personelIslem(1,1,1,'beyan'); $this->model->personelIslem(1,1,1,'beyan');
        self::assertSame(2,$this->kayitAdedi('bordro_yayin_olay'));
        $r = $this->db->query('SELECT * FROM bordro_yayin_dokum WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(BordroYayinIcerikService::BEYAN,$r['beyan_metni']); self::assertSame(1,(int)$r['beyan_metin_surumu']);
        self::assertSame('2026-10-01 12:00:00',$r['beyan_tarihi']);
    }

    public function testBildrimsizTestSurumundeOkumaBeyaniAlinir(): void
    {
        $this->db->exec("UPDATE bordro_yayin SET durum='test' WHERE id=1");
        $this->goruntule();
        $this->model->personelIslem(1,1,1,'beyan');
        self::assertNotNull($this->db->query('SELECT beyan_tarihi FROM bordro_yayin_dokum WHERE id=1')->fetchColumn());
        self::assertSame(2, $this->kayitAdedi('bordro_yayin_olay'));
    }

    public function testGoruntulemedenBeyanAlinmazVeTransactionKapanir(): void
    {
        try { $this->model->personelIslem(1,1,1,'beyan'); self::fail('Beyan reddedilmeliydi.'); }
        catch (DomainException $e) { self::assertFalse($this->db->inTransaction()); self::assertSame(0,$this->kayitAdedi('bordro_yayin_olay')); }
    }

    public function testYenidenAcEskiBeyaniVeIcerigiKorurYeniBeyaniEngeller(): void
    {
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        $once = $this->model->detay(1,1,1);
        $this->model->yenidenAc(1,1,10);
        $sonra = $this->model->detay(1,1,1);
        self::assertSame('revizyon',$sonra['durum']);
        self::assertSame($once['icerik'],$sonra['icerik']); self::assertSame($once['beyan_tarihi'],$sonra['beyan_tarihi']);
        self::assertSame(0,(int)$this->db->query('SELECT kapali_mi FROM bordro_donemi WHERE id=1')->fetchColumn());
        $this->expectException(DomainException::class); $this->model->personelIslem(1,1,1,'beyan');
    }

    public function testAcikDonemYayinVeBeyanReddedilir(): void
    {
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=0 WHERE id=1');
        $this->expectException(DomainException::class); $this->model->onizle(1,1);
    }

    public function testArsivSurumuneBeyanReddedilir(): void
    {
        $this->goruntule(); $this->db->exec("UPDATE bordro_yayin SET durum='arsiv' WHERE id=1");
        $this->expectException(DomainException::class); $this->model->personelIslem(1,1,1,'beyan');
    }

    public function testIcerikDegistirilmisseDetayReddedilir(): void
    {
        $this->db->exec("UPDATE bordro_yayin_dokum SET icerik='{}' WHERE id=1");
        $this->expectException(DomainException::class); $this->model->detay(1,1,1);
    }

    public function testIncelemeTalebiBeyandanBagimsizVeYanitiPersonelGorur(): void
    {
        $this->model->personelIslem(1,1,1,'talep','Çalışma günlerinin incelenmesini istiyorum.');
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        $d = $this->model->detay(1,1,1);
        $this->model->yanitla(1,BordroYayinGuvenlik::id($d['talepler'][0]['token']),10,'Çalışma günleri kontrol edildi, düzeltme yapılacak.');
        $d = $this->model->detay(1,1,1);
        self::assertSame('sonuclandi',$d['talepler'][0]['durum']);
        self::assertCount(1,$d['talepler'][0]['yanitlar']);
        $this->model->personelIslem(1,1,1,'talep','Yanıtınızla ilgili tekrar inceleme istiyorum.');
        self::assertSame(2,$this->kayitAdedi('bordro_yayin_talep'));
    }

    public function testTekrarlananAcikTalepIkinciKayitOlusturmaz(): void
    {
        $this->model->personelIslem(1,1,1,'talep','Çalışma günlerini kontrol edin.');
        try { $this->model->personelIslem(1,1,1,'talep','Çalışma günlerini kontrol edin.'); self::fail('Talep reddedilmeliydi.'); }
        catch (DomainException $e) { self::assertSame(1,$this->kayitAdedi('bordro_yayin_talep')); }
    }

    public function testFirmaDisindakiTalepYanitlanamaz(): void
    {
        $this->model->personelIslem(1,1,1,'talep','Çalışma günlerini kontrol edin.');
        $this->expectException(DomainException::class); $this->model->yanitla(2,1,10,'Kontrol tamamlandı, kayıtlar uygundur.');
    }

    public function testBeyanVereneVeRevizyonaHatirlatmaGonderilmez(): void
    {
        foreach (['beyan','revizyon','arsiv'] as $senaryo) {
            $this->db->exec("UPDATE bordro_yayin SET durum='yayinda' WHERE id=1");
            $this->db->exec('UPDATE bordro_yayin_dokum SET beyan_tarihi=NULL WHERE id=1');
            if ($senaryo === 'beyan') $this->db->exec("UPDATE bordro_yayin_dokum SET beyan_tarihi='2026-10-01' WHERE id=1");
            else $this->db->exec("UPDATE bordro_yayin SET durum='$senaryo' WHERE id=1");
            foreach ([0,3,7] as $gun) {
                $this->db->exec('DELETE FROM bordro_yayin_kuyruk');
                $stmt=$this->db->prepare("INSERT INTO bordro_yayin_kuyruk(id,dokum_id,gun,durum,deneme,kilit_token) VALUES(1,1,?,'isleniyor',1,'test')"); $stmt->execute([$gun]);
                $durum = $this->model->bildirimUygula(['id'=>1,'kilit_token'=>'test','deneme'=>1], function () { self::fail('Gönderim çalışmamalıydı.'); });
                self::assertSame('atlandi',$durum);
            }
        }
    }

    public function testPushBasarisizligindaBeyanVeYayinGeriAlinmaz(): void
    {
        $this->db->exec("INSERT INTO bordro_yayin_kuyruk(id,dokum_id,gun,durum,deneme,kilit_token) VALUES(1,1,3,'isleniyor',1,'test')");
        $r = $this->model->bildirimUygula(['id'=>1,'kilit_token'=>'test','deneme'=>1], fn()=>'bekliyor');
        self::assertSame('bekliyor',$r); self::assertSame('yayinda',$this->model->detay(1,1,1)['durum']);
        self::assertSame('2026-10-01 13:00:00',$this->db->query('SELECT planlanan FROM bordro_yayin_kuyruk')->fetchColumn());
    }

    public function testCsrfEksikVeHamIdReddedilir(): void
    {
        $_SERVER['REQUEST_METHOD']='POST'; $_POST=[];
        try { BordroYayinGuvenlik::csrfDogrula(); self::fail('CSRF zorunlu.'); } catch (DomainException $e) { self::assertTrue(true); }
        try { BordroYayinGuvenlik::id('1'); self::fail('Ham ID reddedilmeli.'); } catch (DomainException $e) { self::assertTrue(true); }
        $_POST=['csrf_token'=>Security::csrf()]; BordroYayinGuvenlik::csrfDogrula();
    }
}
