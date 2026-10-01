<?php
/** İzole, geçici MySQL şeması: uygulama veritabanına veri yazmaz. */
if (PHP_SAPI !== 'cli' || getenv('BORDRO_YAYIN_MYSQL_TEST') !== '1') {
    fwrite(STDERR, "BORDRO_YAYIN_MYSQL_TEST=1 ile CLI üzerinden çalıştırın.\n"); exit(1);
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->load();
$_SESSION = ['firma_id' => 1];

function testDb(string $schema = ''): PDO
{
    if ($schema !== '' && !preg_match('/^codex_bordro_test_[a-f0-9]{16}$/D', $schema)) throw new RuntimeException('Geçersiz test şeması.');
    return new PDO('mysql:host=' . $_ENV['DB_HOST'] . ($schema ? ';dbname=' . $schema : '') . ';charset=utf8mb4', $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
function testModel(PDO $db): \App\Model\BordroYayinModel
{
    $m = (new ReflectionClass(\App\Model\BordroYayinModel::class))->newInstanceWithoutConstructor(); $m->db=$db; return $m;
}
function testPublish(\App\Model\BordroYayinModel $model): array
{
    $atomik = new ReflectionMethod($model, 'atomik');
    return $atomik->invoke($model, function () use ($model) {
        (new ReflectionMethod($model, 'donem'))->invoke($model,1,1,true);
        $icerik=['personel'=>'İzole Test', 'donem'=>'2026/09', 'baslangic'=>'2026-09-01', 'bitis'=>'2026-09-30', 'departman'=>'Test Birimi', 'gorev'=>'Test Personeli', 'calisma_gun'=>30, 'fiili_gun'=>22, 'banka_net_kurus'=>100000,'kalemler'=>[['etiket'=>'Resmî ücret','kurus'=>100000]]];
        return (new ReflectionMethod($model,'yayiniKaydet'))->invoke($model,1,1,10,'test', ['hatalar'=>[],'onizleme_hash'=>'test','kayitlar'=>[['personel_id'=>1,'bordro_id'=>1,'icerik'=>$icerik]]]);
    });
}
final class BordroDelayStatement extends PDOStatement
{
    private static bool $bekledi = false;
    protected function __construct() {}
    public function execute(?array $params = null): bool
    {
        $result = parent::execute($params);
        if (!self::$bekledi && str_contains($this->queryString,'SELECT * FROM bordro_donemi') && str_contains($this->queryString,'FOR UPDATE')) {
            self::$bekledi=true; echo "LOCKED\n"; fflush(STDOUT); usleep(500000);
        }
        return $result;
    }
}
if (($argv[1] ?? '') === '--worker') {
    try {
        if (($argv[3] ?? '') === 'pdf') {
            if (!preg_match('/^codex_bordro_test_[a-f0-9]{16}$/D', $argv[2])) throw new RuntimeException('Geçersiz test şeması.');
            $_ENV['DB_NAME']=$argv[2]; $_ENV['SENTRY_DSN']='';
            session_start(); $_SESSION=['personel_id'=>1,'firma_id'=>1];
            $_GET=['token'=>\App\Helper\Security::encrypt(1)];
            require dirname(__DIR__,2).'/views/personel-pwa/pages/bordro-goster.php';
            exit(0);
        }
        $db=testDb($argv[2]); $db->setAttribute(PDO::ATTR_STATEMENT_CLASS,[BordroDelayStatement::class]); $model=testModel($db);
        if (($argv[3] ?? '') === 'publish') testPublish($model);
        elseif (($argv[3] ?? '') === 'talep') $model->personelIslem(1,1,1,'talep','Çalışma günü hesabını kontrol edin.');
        else $model->yenidenAc(1,1,10);
        echo "OK\n"; exit(0);
    } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
}
function worker(string $schema, string $action): array
{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker',$schema,$action],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('İşçi başlatılamadı.');
    fclose($pipes[0]);
    if (trim((string) fgets($pipes[1])) !== 'LOCKED') {
        $error=stream_get_contents($pipes[2]); proc_terminate($process); throw new RuntimeException('İşçi kilit alamadı: '.$error);
    }
    return [$process,$pipes];
}
function bitir(array $worker): void
{
    [$process,$pipes]=$worker; $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process)!==0 || trim($out)!=='OK') throw new RuntimeException('İşçi başarısız: '.$error);
}
function kontrol(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$schema='codex_bordro_test_'.bin2hex(random_bytes(8)); $admin=testDb(); $created=false;
try {
    $admin->prepare("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")->execute(); $created=true;
    $db=testDb($schema);
    $db->prepare('CREATE TABLE personel (id INT PRIMARY KEY, firma_id INT NOT NULL, aktif_mi INT DEFAULT 1, isten_cikis_tarihi DATE NULL, silinme_tarihi DATETIME NULL) ENGINE=InnoDB')->execute();
    $db->prepare('CREATE TABLE bordro_donemi (id INT PRIMARY KEY, firma_id INT NOT NULL, kapali_mi INT DEFAULT 1, silinme_tarihi DATETIME NULL) ENGINE=InnoDB')->execute();
    $db->prepare('INSERT INTO personel (id,firma_id) VALUES (1,1)')->execute();
    $db->prepare('INSERT INTO bordro_donemi (id,firma_id) VALUES (1,1)')->execute();
    $sql=file_get_contents(dirname(__DIR__,2).'/database/migrations/2026_10_01_bordro_yayin.sql');
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $db->prepare($statement)->execute();
    $model=testModel($db);
    $w=worker($schema,'publish'); $start=microtime(true); $r=testPublish($model); bitir($w);
    kontrol(microtime(true)-$start>0.2,'İkinci yayın dönem kilidini beklemedi.');
    kontrol(!empty($r['mevcut']),'İkinci yayın idempotent değil.');
    kontrol((int)$db->query('SELECT COUNT(*) FROM bordro_yayin')->fetchColumn()===1,'Çift yayın oluştu.');
    kontrol((int)$db->query('SELECT COUNT(*) FROM bordro_yayin_dokum')->fetchColumn()===1,'Çift döküm oluştu.');
    kontrol((int)$db->query('SELECT COUNT(*) FROM bordro_yayin_kuyruk')->fetchColumn()===3,'Bildirim kuyruğu tekrarlanıyor.');
    $gunler=$db->query('SELECT TIMESTAMPDIFF(DAY,y.yayin_tarihi,q.planlanan) FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id=q.dokum_id JOIN bordro_yayin y ON y.id=d.yayin_id ORDER BY q.gun')->fetchAll(PDO::FETCH_COLUMN);
    kontrol(array_map('intval',$gunler)===[0,3,7],'Hatırlatma tarihleri hatalı.');
    echo "PASS: migration, eşzamanlı yayın, tek döküm/kuyruk ve 0/3/7 gün.\n";
    $q=$model->bildirimAl();
    kontrol($q !== null && (int)$q['gun']===0,'İlk bildirim kuyruğa alınamadı.');
    kontrol($model->bildirimUygula($q,fn()=>'gonderildi')==='gonderildi','Gönderim kaydedilemedi.');
    kontrol($model->bildirimAl()===null,'Gelecek hatırlatma erken işleniyor.');
    echo "PASS: MySQL bildirim sahiplenme, gönderim kaydı ve gelecek tarih filtresi.\n";
    $process=proc_open([PHP_BINARY,__FILE__,'--worker',$schema,'pdf'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]); $pdf=stream_get_contents($pipes[1]); $pdfError=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    kontrol(proc_close($process)===0 && str_starts_with($pdf,'%PDF-'),'Gerçek PDF endpoint çıktısı geçersiz: '.$pdfError);
    $pdfText=(new \Smalot\PdfParser\Parser())->parseContent($pdf)->getText();
    kontrol(str_contains($pdfText,'İzole Test') && str_contains($pdfText,'Bankadan ödenecek net tutar'),'PDF snapshot içeriği eksik.');
    kontrol($model->detay(1,1,1)['goruntuleme_tarihi']===null && $model->detay(1,1,1)['beyan_tarihi']===null,'PDF indirme görüntüleme/beyan oluşturdu.');
    echo "PASS: gerçek PDF endpoint, Türkçe snapshot içeriği ve beyan oluşturmama.\n";
    $w=worker($schema,'talep'); $reddedildi=false;
    try { $model->personelIslem(1,1,1,'talep','Çalışma günü hesabını tekrar kontrol edin.'); } catch (DomainException $e) { $reddedildi=true; }
    bitir($w);
    kontrol($reddedildi && (int)$db->query('SELECT COUNT(*) FROM bordro_yayin_talep')->fetchColumn()===1,'Eşzamanlı açık talepler tekilleşmedi.');
    echo "PASS: eşzamanlı inceleme talebi için güncel kilitli okuma ve tek açık kayıt.\n";
    $model->personelIslem(1,1,1,'goruntule');
    $w=worker($schema,'reopen'); $start=microtime(true); $reddedildi=false;
    try { $model->personelIslem(1,1,1,'beyan'); } catch (DomainException $e) { $reddedildi=true; }
    bitir($w);
    kontrol(microtime(true)-$start>0.2 && $reddedildi,'Yeniden açma ile yarışan beyan engellenmedi.');
    kontrol($model->detay(1,1,1)['beyan_tarihi']===null,'Revizyona yeni beyan yazıldı.');
    echo "PASS: yeniden açma ile eşzamanlı beyan kilidi ve güncel durum kontrolü.\n";
    $db->prepare('UPDATE bordro_donemi SET kapali_mi=1 WHERE id=1')->execute();
    $r=testPublish($model);
    kontrol($r['surum']===2,'Yeni sürüm oluşmadı.');
    kontrol($model->detay(1,1,1)['durum']==='arsiv','Eski sürüm arşivlenmedi.');
    kontrol((int)$db->query('SELECT COUNT(*) FROM bordro_yayin_kuyruk')->fetchColumn()===6,'Yeni sürüm kuyruğu eksik.');
    echo "PASS: revizyon sonrası yeni sürüm ve arşiv.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    $failure=true;
} finally {
    if ($created) $admin->prepare("DROP DATABASE `$schema`")->execute();
}
exit(isset($failure) ? 1 : 0);
