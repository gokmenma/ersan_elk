<?php
use App\Service\BordroMuhasebeExcelKarsilastirmaService;
use App\Service\BordroMuhasebeKarsilastirmaExcelService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class BordroMuhasebeExcelKarsilastirmaTest extends TestCase
{
    private string $path;
    protected function setUp(): void
    {
        $this->path=(string)tempnam(sys_get_temp_dir(),'mutabakat-');$book=new Spreadsheet();
        $book->getActiveSheet()->setTitle('Muhasebe Listesi')->setCellValue('A1','başka sayfa');
        $s=$book->createSheet();$s->setTitle('Mutabakat');
        $s->fromArray(['ADI SOYADI','TC KİMLİK NO','TOPLAM GÜN','FİİLÎ GÜN','İZİN GÜNÜ','RAPOR GÜNÜ','ASGARİ / NORMAL ÜCRET','YEMEK YARDIMI','EŞ YARDIMI','RESMÎ TATİL ÇALIŞMASI (NET)','H.T. ÇALIŞMASI (NET)','FAZLA MESAİ (NET)','PRİM / İKRAMİYE (RESMÎ)','DİĞER RESMÎ EKLENECEK','AVANS','İCRA','DİĞER KESİNTİLER','BANKAYA YATACAK TUTAR'],null,'A2');
        $s->fromArray(['İBRAHİM ŞAHİN','12345678901',30,26,3,1,28075.50,7800,3300,0,935.85,2994.72,0,0,0,9025.95,0,30372.57],null,'A3');
        (new Xlsx($book))->save($this->path);$book->disconnectWorksheets();
    }
    protected function tearDown(): void{if(is_file($this->path))unlink($this->path);}
    public function testMutabakatSayfasiVeTumGonderimAlanlariOkunur(): void
    {
        $service=new BordroMuhasebeExcelKarsilastirmaService();$method=new ReflectionMethod($service,'exceliOku');
        [$rows,$meta]=$method->invoke($service,$this->path);
        self::assertSame('Mutabakat',$meta['sayfa']);self::assertCount(1,$rows);
        self::assertSame(26,$rows[0]['alanlar']['fiili_gun']);self::assertSame(2807550,$rows[0]['alanlar']['asgari']);
        self::assertSame(902595,$rows[0]['alanlar']['icra']);self::assertSame(3037257,$rows[0]['alanlar']['banka']);
    }
    public function testFarkMuhasebeEksiGonderilenDegerOlarakUretilir(): void
    {
        $service=new BordroMuhasebeExcelKarsilastirmaService();$method=new ReflectionMethod($service,'alanFarklari');
        $excel=array_fill_keys(['toplam_gun','fiili_gun','izin_gun','rapor_gun','asgari','yemek','es_yardimi','rtc','htc','fazla_mesai','prim','diger','avans','icra','diger_kesinti','banka'],0);$system=$excel;
        $excel['banka']=100050;$system['banka']=90000;$fark=$method->invoke($service,$excel,$system);
        self::assertCount(1,$fark);self::assertSame('banka',$fark[0]['alan']);self::assertSame(10050,$fark[0]['fark']);self::assertSame('para',$fark[0]['tur']);
    }

    public function testMuhasebeMaasBordrosuOrtakMutabakatAlanlarinaDonusturulur(): void
    {
        $book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('2026-EYLÜL AYI MAAŞ BORDROSU');
        $headers=['#','Adı Soyadı','TCKN','Kanun','Ücret G/S','T.Gün','SSK M.','SSK İşçi','İşs.P.İşçi','G.V.M','Gel.Ver.','Öz.Kesinti','Damga V','N.Ödenen','Normal Gün','Normal Tutar','Hafta Tatili','HT Tutar','Yemek','Yemek Tutar','Ücretli İzin','İzin Tutar','Fazla Mesai','FM Tutar','Rapor'];
        $sheet->fromArray($headers,null,'A10');
        $sheet->fromArray(['001','ABDULLAH DOĞAN','11522818356','00000','33.030,00G','030','33,030.00','4,624.20','330.30','28,075.50','0.00','0.00','59.65','35,875.50','26G','28,626.00','4G','4,404.00','','7,859.65','','','',null,'0G'],null,'A11');
        $sheet->setCellValue('B12','Toplamlar:');
        (new Xlsx($book))->save($this->path);$book->disconnectWorksheets();
        $service=new BordroMuhasebeExcelKarsilastirmaService();$method=new ReflectionMethod($service,'exceliOku');[$rows,$meta]=$method->invoke($service,$this->path);
        self::assertSame('muhasebe_bordro',$meta['format']);self::assertCount(1,$rows);
        self::assertSame(30,$rows[0]['alanlar']['toplam_gun']);self::assertSame(26,$rows[0]['alanlar']['fiili_gun']);
        self::assertSame(785965,$rows[0]['alanlar']['yemek']);self::assertSame(3587550,$rows[0]['alanlar']['banka']);
    }

    public function testExcelOzetindeAlanKisiSayisiPersonelBasinaBenzersizdir(): void
    {
        $result=['sonuclar'=>[
            ['farklar'=>[['alan'=>'yemek','etiket'=>'Yemek Yardımı'],['alan'=>'yemek','etiket'=>'Yemek Yardımı'],['alan'=>'banka','etiket'=>'Bankaya Yatacak Tutar']]],
            ['farklar'=>[['alan'=>'yemek','etiket'=>'Yemek Yardımı']]],
        ]];
        $counts=(new BordroMuhasebeKarsilastirmaExcelService())->alanSayilari($result);
        self::assertSame('yemek',$counts[0]['alan']);self::assertSame(2,$counts[0]['adet']);
        self::assertSame('banka',$counts[1]['alan']);self::assertSame(1,$counts[1]['adet']);
    }
}
