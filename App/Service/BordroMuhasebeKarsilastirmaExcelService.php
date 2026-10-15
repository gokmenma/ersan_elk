<?php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Muhasebe karşılaştırma sonucunu özet ve açık fark satırlarıyla XLSX olarak üretir. */
final class BordroMuhasebeKarsilastirmaExcelService
{
    public function indir(array $result): never
    {
        $book=new Spreadsheet();$summary=$book->getActiveSheet();$summary->setTitle('Özet');
        $summary->fromArray(['MUHASEBE EXCEL KARŞILAŞTIRMA ÖZETİ'],null,'A1');$summary->mergeCells('A1:D1');
        $summary->fromArray(['Dönem',$result['donem']['adi']??'','Dosya',$result['meta']['dosya_adi']??''],null,'A3');
        $summary->fromArray(['Durum','Adet'],null,'A5');$row=6;
        $statusLabels=['toplam'=>'Toplam Sonuç','farkli'=>'Farklı','yalniz_muhasebe'=>'Yalnız Muhasebede','yalniz_sistem'=>'Yalnız Sistemde','eslesen'=>'Eşleşen'];
        foreach($statusLabels as $key=>$label)$summary->fromArray([$label,(int)($result['sayilar'][$key]??0)],null,'A'.$row++);
        $fieldCounts=$this->alanSayilari($result);$row+=2;$summary->fromArray(['Farklı Alan','Fark Bulunan Personel'],null,'A'.$row++);
        foreach($fieldCounts as $field)$summary->fromArray([$field['etiket'],$field['adet']],null,'A'.$row++);
        $details=$book->createSheet();$details->setTitle('Personel Karşılaştırma');
        $details->setCellValue('A2','Personel')->setCellValue('B2','T.C.')->setCellValue('C2','Durum');
        $column=4;
        foreach(($result['alanlar']??[]) as $key=>$label){$start=Coordinate::stringFromColumnIndex($column);$end=Coordinate::stringFromColumnIndex($column+2);$details->mergeCells($start.'1:'.$end.'1');$details->setCellValue($start.'1',$label);$details->fromArray(['Muhasebe','Gönderilen','Fark'],null,$start.'2');$column+=3;}
        $r=3;
        foreach(($result['sonuclar']??[]) as $person){
            $details->fromArray([$person['personel'],$person['tc'],$this->durumEtiketi($person['durum'])],null,'A'.$r);$column=4;
            foreach(array_keys($result['alanlar']??[]) as $key){$value=$person['degerler'][$key]??[];$money=($value['tur']??'')==='para';$divisor=$money?100:1;
                foreach(['muhasebe','sistem','fark'] as $part){$raw=$value[$part]??null;$cell=Coordinate::stringFromColumnIndex($column++).$r;if($raw!==null)$details->setCellValue($cell,$raw/$divisor);if($money)$details->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');if($part==='fark'&&$raw!==null&&(float)$raw!==0.0)$details->getStyle($cell)->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'DC2626']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FEE2E2']]]);}
            }$r++;
        }
        foreach([$summary,$details] as $sheet){
            $last=$sheet->getHighestDataColumn();$sheet->getStyle('A1:'.$last.'1')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'334155']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]]);
            for($c=1;$c<=Coordinate::columnIndexFromString($last);$c++)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        $summary->getStyle('A5:B5')->getFont()->setBold(true);$lastDetail=$details->getHighestDataColumn();$details->getStyle('A2:'.$lastDetail.'2')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'475569']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);$details->setAutoFilter('A2:'.$lastDetail.max(2,$r-1));$details->freezePane('D3');
        $file='muhasebe-karsilastirma-'.date('Ymd-His').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$file.'"');header('Cache-Control: no-store');
        (new Xlsx($book))->save('php://output');$book->disconnectWorksheets();exit;
    }

    public function alanSayilari(array $result): array
    {
        $counts=[];
        foreach(($result['sonuclar']??[]) as $person){$seen=[];foreach(($person['farklar']??[]) as $difference){$key=(string)$difference['alan'];if(isset($seen[$key]))continue;$seen[$key]=true;$counts[$key]=['alan'=>$key,'etiket'=>(string)$difference['etiket'],'adet'=>(int)(($counts[$key]['adet']??0)+1)];}}
        usort($counts,static fn($a,$b)=>[$b['adet'],$a['etiket']]<=>[$a['adet'],$b['etiket']]);return array_values($counts);
    }

    private function durumEtiketi(string $status): string{return ['farkli'=>'Farklı','eslesen'=>'Eşleşen','yalniz_muhasebe'=>'Yalnız Muhasebede','yalniz_sistem'=>'Yalnız Sistemde'][$status]??$status;}
}
