<?php

use App\Model\BordroOnayKilidiModel;
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
CREATE TABLE personel(id INTEGER PRIMARY KEY, firma_id INT, adi_soyadi TEXT, aktif_mi INT DEFAULT 1, isten_cikis_tarihi TEXT, silinme_tarihi TEXT);
CREATE TABLE bordro_personel(id INTEGER PRIMARY KEY, donem_id INT, personel_id INT, silinme_tarihi TEXT);
CREATE TABLE bordro_yayin_revizyon(id INTEGER PRIMARY KEY, firma_id INT, donem_id INT, tur TEXT, gerekce TEXT, yapan_id INT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, incelenen_sayisi INT DEFAULT 0, revize_sayisi INT DEFAULT 0, ic_guncellenen_sayisi INT DEFAULT 0, korunan_sayisi INT DEFAULT 0, onizleme_hash TEXT, is_active INT DEFAULT 1);
CREATE TABLE bordro_donemi(id INTEGER PRIMARY KEY, firma_id INT, kapali_mi INT, silinme_tarihi TEXT);
CREATE TABLE bordro_yayin(id INTEGER PRIMARY KEY, firma_id INT, donem_id INT, surum INT, durum TEXT DEFAULT 'yayinda', yayinlayan_id INT, yayin_tarihi TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_dokum(id INTEGER PRIMARY KEY, yayin_id INT, personel_id INT, bordro_personel_id INT, pusula_no TEXT, icerik TEXT, icerik_hash TEXT, olusturma_tarihi TEXT DEFAULT CURRENT_TIMESTAMP, gonderim_tarihi TEXT, goruntuleme_tarihi TEXT, beyan_tarihi TEXT, beyan_metni TEXT, beyan_metin_surumu INT, onay_ip TEXT, onay_user_agent TEXT, onay_session_hash TEXT, onay_icerik_hash TEXT, revizyon_tarihi TEXT, revizyon_id INT, deleted_at TEXT, is_active INT DEFAULT 1, UNIQUE(yayin_id, personel_id));
CREATE TABLE bordro_yayin_olay(id INTEGER PRIMARY KEY, dokum_id INT, tur TEXT, aktor_tipi TEXT, aktor_id INT, detay TEXT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_talep(id INTEGER PRIMARY KEY, dokum_id INT, konu TEXT DEFAULT 'diger', mesaj TEXT, durum TEXT DEFAULT 'acik', atanan_kullanici_id INT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, sonuc_tarihi TEXT, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_yanit(id INTEGER PRIMARY KEY, talep_id INT, kullanici_id INT, mesaj TEXT, tarih TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_talep_ek(id INTEGER PRIMARY KEY, talep_id INT, dosya_adi TEXT, dosya_yolu TEXT, mime_tipi TEXT, dosya_boyutu INT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, is_active INT DEFAULT 1);
CREATE TABLE bordro_yayin_kuyruk(id INTEGER PRIMARY KEY, dokum_id INT, gun INT, planlanan TEXT, durum TEXT DEFAULT 'bekliyor', deneme INT DEFAULT 0, kilit_token TEXT, son_hata TEXT, gonderim_tarihi TEXT, is_active INT DEFAULT 1);
CREATE TABLE users(id INTEGER PRIMARY KEY, adi_soyadi TEXT, user_name TEXT);
INSERT INTO personel(id, firma_id, adi_soyadi) VALUES (1,1,'Ayşe Onaylı'),(2,1,'Berk İtirazlı'),(3,2,'Diğer Firma');
INSERT INTO bordro_personel(id, donem_id, personel_id) VALUES (10,1,1),(11,1,2),(12,2,3);
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

    private function testYayiniKaydet(int $personel): array
    {
        $kaydet = new ReflectionMethod($this->model, 'testYayiniKaydet');
        $atomik = new ReflectionMethod($this->model, 'atomik');
        $secili = $this->hazirDokum();
        $secili['personel_id'] = $personel;
        $secili['bordro_id'] = 10 + $personel;
        $secili['icerik']['personel'] = 'Test Personel ' . $personel;
        return $atomik->invoke($this->model, fn() => $kaydet->invoke($this->model, 1, 1, 10, $personel, $secili));
    }

    public function testFarkliPersonellerinTestYayinlariBirbiriniPasiflestirmez(): void
    {
        $this->testYayiniKaydet(1);
        $this->testYayiniKaydet(2);
        self::assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id=d.yayin_id WHERE y.durum='test' AND d.is_active=1")->fetchColumn());

        $this->testYayiniKaydet(1);
        self::assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id=d.yayin_id WHERE y.durum='test' AND d.is_active=1")->fetchColumn());
        self::assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id=d.yayin_id WHERE y.durum='test' AND d.personel_id=2 AND d.is_active=1")->fetchColumn());
    }

    private function ikinciPersonelDokumu(): void
    {
        $i = json_encode(['personel' => 'Berk İtirazlı', 'donem' => '2026/09', 'baslangic' => '2026-09-01', 'banka_net_kurus' => 90000, 'kalemler' => [['etiket' => 'Ücret', 'kurus' => 90000]]]);
        $stmt = $this->db->prepare('INSERT INTO bordro_yayin_dokum(id,yayin_id,personel_id,bordro_personel_id,icerik,icerik_hash) VALUES (3,1,2,11,?,?)');
        $stmt->execute([$i, hash('sha256', $i)]);
    }

    private function kilit(): BordroOnayKilidiModel
    {
        return new BordroOnayKilidiModel($this->db);
    }

    public function testOnayliDokumKilitlenirAcikItirazKilidiKaldirir(): void
    {
        self::assertSame([], $this->kilit()->donemKilitleri(1));
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        self::assertSame([10], array_keys($this->kilit()->donemKilitleri(1)));
        try { $this->kilit()->dogrula(10); self::fail('Onaylı bordro kilitli olmalı.'); }
        catch (DomainException $e) { self::assertStringContainsString('Ayşe Onaylı', $e->getMessage()); }
        $this->kilit()->dogrula(11);
        $this->model->personelIslem(1,1,1,'talep','Onayladım ama prim tutarını kontrol etmenizi istiyorum.', ['konu'=>'prim']);
        self::assertSame([], $this->kilit()->donemKilitleri(1));
    }

    public function testDonemAcilincaOnayliKorunurOnaysizRevizyonaAlinir(): void
    {
        $this->ikinciPersonelDokumu();
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        $once = $this->model->detay(1,1,1);
        $sonuc = $this->model->yenidenAc(1,1,10);
        self::assertSame(['revize' => 1, 'korunan' => 1], $sonuc);
        self::assertSame(0,(int)$this->db->query('SELECT kapali_mi FROM bordro_donemi WHERE id=1')->fetchColumn());
        $onayli = $this->model->detay(1,1,1);
        self::assertSame('yayinda', $onayli['durum']);
        self::assertSame('onaylandi', $onayli['islem_durumu']);
        self::assertSame($once['icerik'], $onayli['icerik']);
        $revize = $this->model->detay(1,3,2);
        self::assertSame('revizyon', $revize['durum']);
        self::assertSame('revize_edildi', $revize['islem_durumu']);
        self::assertSame(1, $this->kayitAdedi('bordro_yayin_revizyon'));
        self::assertSame([10], array_keys($this->kilit()->donemKilitleri(1)));
    }

    public function testRevizyondakiDokumeBeyanAlinmazVeBildirimGonderilmez(): void
    {
        $this->ikinciPersonelDokumu();
        $this->model->yenidenAc(1,1,10);
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1');
        $this->model->personelIslem(1,1,1,'goruntule');
        try { $this->model->personelIslem(1,1,1,'beyan'); self::fail('Revizyondaki dökümde beyan alınmamalı.'); }
        catch (DomainException $e) { self::assertFalse($this->db->inTransaction()); }
        $this->db->exec("INSERT INTO bordro_yayin_kuyruk(id,dokum_id,gun,durum,deneme,kilit_token) VALUES(1,1,3,'isleniyor',1,'test')");
        $durum = $this->model->bildirimUygula(['id'=>1,'kilit_token'=>'test','deneme'=>1], function () { self::fail('Gönderim çalışmamalıydı.'); });
        self::assertSame('atlandi', $durum);
    }

    public function testYenidenYayindaOnayliKorunurYalnizRevizeOlanYeniSurumAlir(): void
    {
        $this->ikinciPersonelDokumu();
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        $eski = $this->model->detay(1,1,1);
        $this->model->yenidenAc(1,1,10);
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1');
        $ikinci = $this->hazirDokum(); $ikinci['personel_id'] = 2; $ikinci['bordro_id'] = 11; $ikinci['icerik']['personel'] = 'Berk İtirazlı';
        $r = $this->yayinKaydet([$this->hazirDokum(), $ikinci]);
        self::assertSame(2, $r['surum']);
        self::assertSame(1, $r['adet']);
        self::assertSame(1, $r['korunan']);
        self::assertSame('yayinda', $this->model->detay(1,1,1)['durum']);
        self::assertSame($eski['beyan_tarihi'], $this->model->detay(1,1,1)['beyan_tarihi']);
        self::assertCount(1, $this->model->personelListe(1,1));
        self::assertCount(2, $this->model->personelListe(1,2));
        self::assertSame([0,3,7],$this->db->query('SELECT gun FROM bordro_yayin_kuyruk ORDER BY gun')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(['2026-10-01 12:00:00','2026-10-04 12:00:00','2026-10-08 12:00:00'],$this->db->query('SELECT planlanan FROM bordro_yayin_kuyruk ORDER BY gun')->fetchAll(PDO::FETCH_COLUMN));
        $tekrar = $this->yayinKaydet([$this->hazirDokum(), $ikinci]);
        self::assertTrue($tekrar['mevcut']);
        self::assertSame(4, $this->kayitAdedi('bordro_yayin_dokum'));
        self::assertSame(3, $this->kayitAdedi('bordro_yayin_kuyruk'));
    }

    public function testTumDokumleriRevizeOlanEskiYayinArsivlenir(): void
    {
        $this->model->yenidenAc(1,1,10);
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1');
        $r = $this->yayinKaydet([$this->hazirDokum()]);
        self::assertSame(2, $r['surum']);
        self::assertSame('arsiv', $this->model->detay(1,1,1)['durum']);
        self::assertCount(2, $this->model->personelListe(1,1));
    }

    public function testOnayGeriAlmaAcikDonemVeGerekceIsterKilidiKaldirir(): void
    {
        $this->goruntule(); $this->model->personelIslem(1,1,1,'beyan');
        try { $this->model->onayGeriAl(1,1,10,'Fazla mesai saatleri eksik girilmiş.'); self::fail('Kapalı dönemde reddedilmeli.'); }
        catch (DomainException $e) { self::assertStringContainsString('dönemi açın', $e->getMessage()); }
        $this->model->yenidenAc(1,1,10);
        try { $this->model->onayGeriAl(1,1,10,'kısa'); self::fail('Kısa gerekçe reddedilmeli.'); }
        catch (DomainException $e) { self::assertSame([10], array_keys($this->kilit()->donemKilitleri(1))); }
        $sonuc = $this->model->onayGeriAl(1,1,10,'Fazla mesai saatleri eksik girilmiş.');
        self::assertSame('Test', $sonuc['personel']);
        self::assertSame([], $this->kilit()->donemKilitleri(1));
        $d = $this->model->detay(1,1);
        self::assertSame('revize_edildi', $d['islem_durumu']);
        self::assertSame('onay_geri_alindi', end($d['olaylar'])['tur']);
        self::assertSame('tekli', $this->db->query('SELECT tur FROM bordro_yayin_revizyon')->fetchColumn());
        $this->expectException(DomainException::class);
        $this->model->onayGeriAl(1,1,10,'Fazla mesai saatleri eksik girilmiş.');
    }

    public function testOnaylanmamisDokumdeOnayGeriAlinamaz(): void
    {
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=0 WHERE id=1');
        $this->expectException(DomainException::class);
        $this->model->onayGeriAl(1,1,10,'Fazla mesai saatleri eksik girilmiş.');
    }

    public function testParasalParmakIziFirmaBilgisindenEtkilenmez(): void
    {
        $a = ['banka_net_kurus' => 100, 'kalemler' => [['etiket' => 'Ücret', 'kurus' => 100]], 'resmi_pusula' => ['firma' => ['adres' => 'Eski']]];
        $b = $a; $b['resmi_pusula']['firma']['adres'] = 'Yeni';
        self::assertSame(BordroYayinModel::parasalParmakIzi($a), BordroYayinModel::parasalParmakIzi($b));
        $b['kalemler'][0]['kurus'] = 101;
        self::assertNotSame(BordroYayinModel::parasalParmakIzi($a), BordroYayinModel::parasalParmakIzi($b));
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
        self::assertSame(3,$this->kayitAdedi('bordro_yayin_olay'));
        $r = $this->db->query('SELECT * FROM bordro_yayin_dokum WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(BordroYayinIcerikService::BEYAN,$r['beyan_metni']); self::assertSame(BordroYayinIcerikService::METIN_SURUMU,(int)$r['beyan_metin_surumu']);
        self::assertSame('2026-10-01 12:00:00',$r['beyan_tarihi']);
    }

    public function testOnayKanitiIcerikHashiVeIslemDurumuKaydedilir(): void
    {
        $this->goruntule();
        $this->model->personelIslem(1,1,1,'beyan','',[
            'ip' => '127.0.0.1', 'user_agent' => 'PHPUnit', 'session_hash' => str_repeat('a', 64),
        ]);
        $r = $this->db->query('SELECT * FROM bordro_yayin_dokum WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('127.0.0.1', $r['onay_ip']);
        self::assertSame('PHPUnit', $r['onay_user_agent']);
        self::assertSame($r['icerik_hash'], $r['onay_icerik_hash']);
        self::assertSame('onaylandi', $this->model->detay(1,1,1)['islem_durumu']);
    }

    public function testItirazKonusuVeDurumuPusulaDurumunaYansir(): void
    {
        $this->model->personelIslem(1,1,1,'talep','Fazla mesai saatlerimin kontrolünü istiyorum.', ['konu'=>'fazla_mesai']);
        $d = $this->model->detay(1,1,1);
        self::assertSame('itiraz_edildi', $d['islem_durumu']);
        self::assertSame('fazla_mesai', $d['talepler'][0]['konu']);
        self::assertSame('alindi', $d['talepler'][0]['durum']);
    }

    public function testItirazEkiYalnizKayitSahibineDoner(): void
    {
        $talep = $this->model->personelIslem(1,1,1,'talep','Kesinti belgesinin kontrolünü istiyorum.', ['konu'=>'kesinti']);
        $this->model->talepEkiKaydet(1,(int)$talep,1,['adi'=>'belge.pdf','yol'=>'uploads/bordro-itiraz/test.enc','mime'=>'application/pdf','boyut'=>128]);
        $d = $this->model->detay(1,1,1);
        self::assertSame('belge.pdf',$d['talepler'][0]['ekler'][0]['dosya_adi']);
        $ekId = BordroYayinGuvenlik::id($d['talepler'][0]['ekler'][0]['token']);
        self::assertSame('application/pdf',$this->model->talepEki(1,$ekId,1)['mime_tipi']);
        $this->expectException(DomainException::class);
        $this->model->talepEki(1,$ekId,2);
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

    public function testYenidenAcOnaysizDokumunIceriginiKorurYeniBeyaniEngeller(): void
    {
        $this->goruntule();
        $once = $this->model->detay(1,1,1);
        $this->model->yenidenAc(1,1,10);
        $sonra = $this->model->detay(1,1,1);
        self::assertSame('revizyon',$sonra['durum']);
        self::assertSame($once['icerik'],$sonra['icerik']);
        self::assertSame(0,(int)$this->db->query('SELECT kapali_mi FROM bordro_donemi WHERE id=1')->fetchColumn());
        $this->db->exec('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1');
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
