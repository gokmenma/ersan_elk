<?php

use App\Service\BordroMuhasebeExcelKarsilastirmaService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class BordroMuhasebeExcelKarsilastirmaTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'bordro-karsilastirma-');
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('EYLÜL 2026');
        $headers = ['#','Adı Soyadı','TCKN','Kanun','Ücret G/S','T.Gün','SSK M.','SSK İşçi','İşs.P.İşçi','G.V.M','Gel.Ver.','Öz.Kesinti','Damga V','N.Ödenen','Normal Gün','', 'Hafta Tatili','', 'Yemek','', 'Ücretli İzin','', 'Fazla Mesai','', 'Rapor'];
        $sheet->fromArray($headers, null, 'A10');
        $sheet->fromArray(['001','İBRAHİM ŞAHİN','12345678901','00000','33.030,00G','030','33,030.00','4,624.20','330.30','28,075.50','0.00','10.00','59.65','35,875.50','26G','28,626.00','4G','4,404.00','','7,800.00','1G','1,101.00','','','0G'], null, 'A11');
        $sheet->fromArray(['002','İBRAHİM ŞAHİN','12345678901','00000','33.030,00G','001','1,101.00','154.14','11.01','935.85','0.00','0.00','0.00','1,100.85','1G','1,101.00',null,null,'','165.00',null,null,null,null,null], null, 'A12');
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) unlink($this->path);
    }

    public function testMuhasebeSablonuTurkceBasliklarlaVeKurusHassasiyetindeOkunur(): void
    {
        $service = new BordroMuhasebeExcelKarsilastirmaService();
        $method = new ReflectionMethod($service, 'exceliOku');
        [$rows, $meta] = $method->invoke($service, $this->path);
        self::assertCount(2, $rows, 'Mükerrer muhasebe satırı analizden atılmamalıdır.');
        self::assertSame('EYLÜL 2026', $meta['sayfa']);
        self::assertSame(3303000, $rows[0]['alanlar']['sgk_matrahi']);
        self::assertSame(462420, $rows[0]['alanlar']['sgk_isci']);
        self::assertSame(3587550, $rows[0]['alanlar']['net_odenen']);
        self::assertSame(780000, $rows[0]['alanlar']['yemek']);
        self::assertSame(26, $rows[0]['alanlar']['normal_gun']);
        self::assertSame(1, $rows[0]['alanlar']['ucretli_izin_gun']);
    }

    public function testHerAlanFarkiMuhasebeEksiSistemOlarakUretilir(): void
    {
        $service = new BordroMuhasebeExcelKarsilastirmaService();
        $method = new ReflectionMethod($service, 'alanFarklari');
        $excel = array_fill_keys(['toplam_gun','sgk_matrahi','sgk_isci','issizlik_isci','gelir_vergisi_matrahi','gelir_vergisi','ozel_kesinti','damga_vergisi','net_odenen','normal_gun','yemek','ucretli_izin_gun','rapor_gun'], 0);
        $system = $excel;
        $excel['net_odenen'] = 100050;
        $system['net_odenen'] = 90000;
        $farklar = $method->invoke($service, $excel, $system);
        self::assertCount(1, $farklar);
        self::assertSame('net_odenen', $farklar[0]['alan']);
        self::assertSame(10050, $farklar[0]['fark']);
        self::assertSame('para', $farklar[0]['tur']);
    }
}
