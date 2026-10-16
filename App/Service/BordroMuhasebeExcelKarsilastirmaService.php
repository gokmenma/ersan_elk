<?php
namespace App\Service;

use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use DomainException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Muhasebeden dönen Mutabakat sayfasını, gönderilen listenin aynı model değerleriyle karşılaştırır. */
final class BordroMuhasebeExcelKarsilastirmaService
{
    private const MAX_FILE_SIZE = 10485760;
    private const ALANLAR = [
        'toplam_gun'=>'Toplam Gün','fiili_gun'=>'Fiilî Gün','izin_gun'=>'İzin Günü','rapor_gun'=>'Rapor Günü',
        'asgari'=>'Asgari / Normal Ücret','yemek'=>'Yemek Yardımı','es_yardimi'=>'Eş Yardımı',
        'rtc'=>'Resmî Tatil Çalışması (Net)','htc'=>'H.T. Çalışması (Net)','fazla_mesai'=>'Fazla Mesai (Net)',
        'prim'=>'Prim / İkramiye (Resmî)','diger'=>'Diğer Resmî Eklenecek','avans'=>'Avans','icra'=>'İcra',
        'diger_kesinti'=>'Diğer Kesintiler','banka'=>'Bankaya Yatacak Tutar',
    ];
    private const GUNLER = ['toplam_gun','fiili_gun','normal_gun','hafta_tatili_gun','ucretli_izin_gun','izin_gun','rapor_gun'];
    private const MUHASEBE_BORDRO_ALANLARI = [
        'toplam_gun'=>'Toplam Gün','normal_gun'=>'Normal Gün','hafta_tatili_gun'=>'Hafta Tatili',
        'ucretli_izin_gun'=>'Ücretli İzin','rapor_gun'=>'Rapor Günü',
        'yemek'=>'Yemek Yardımı','banka'=>'Net Ödenen / Bankaya Yatacak Tutar',
    ];

    public function karsilastir(array $file, int $firmaId, int $donemId): array
    {
        [$muhasebe,$meta]=$this->exceliOku($this->dosyayiDogrula($file));
        [$sistem,$donem]=$this->sistemSatirlari($firmaId,$donemId);
        $alanKeys=array_keys($meta['format']==='muhasebe_bordro'?self::MUHASEBE_BORDRO_ALANLARI:self::ALANLAR);
        $sonuclar=[]; $used=[];
        foreach($muhasebe as $excel){
            [$key,$sys,$match]=$this->eslesenSistemSatiri($excel,$sistem,$used);
            if(!$sys){$sonuclar[]=$this->sonucSatiri($excel,null,'yalniz_muhasebe','Muhasebe Excelinde var, gönderilen sistem listesinde yok.',[],$alanKeys);continue;}
            $used[$key]=true; $fark=$this->alanFarklari($excel['alanlar'],$sys['alanlar'],$alanKeys);
            $aciklama=$fark?count($fark).' alanda fark var.':'Mutabakat alanlarının tamamı aynı.';
            if($match==='ad')$aciklama.=' T.C. yerine ad-soyad ile eşleştirildi.';
            $sonuclar[]=$this->sonucSatiri($excel,$sys,$fark?'farkli':'eslesen',$aciklama,$fark,$alanKeys,$match);
        }
        foreach($sistem as $key=>$sys)if(!isset($used[$key]))$sonuclar[]=$this->sonucSatiri(null,$sys,'yalniz_sistem','Gönderilen sistem listesinde var, muhasebe Excelinde yok.',[],$alanKeys);
        $rank=['farkli'=>0,'yalniz_muhasebe'=>1,'yalniz_sistem'=>2,'eslesen'=>3];
        usort($sonuclar,static fn($a,$b)=>[$rank[$a['durum']]??9,$a['personel']]<=>[$rank[$b['durum']]??9,$b['personel']]);
        $sayilar=array_fill_keys(['toplam','eslesen','farkli','yalniz_muhasebe','yalniz_sistem'],0);$sayilar['toplam']=count($sonuclar);
        foreach($sonuclar as $row)$sayilar[$row['durum']]++;
        return ['meta'=>$meta+['dosya_adi'=>basename((string)($file['name']??'muhasebe.xlsx'))],
            'donem'=>['id'=>(int)$donem->id,'adi'=>(string)$donem->donem_adi],'sayilar'=>$sayilar,
            'alanlar'=>$meta['format']==='muhasebe_bordro'?self::MUHASEBE_BORDRO_ALANLARI:self::ALANLAR,'sonuclar'=>$sonuclar];
    }

    private function dosyayiDogrula(array $file): string
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||empty($file['tmp_name'])||!is_uploaded_file((string)$file['tmp_name']))throw new DomainException('Geçerli bir Excel dosyası yükleyin.');
        if((int)($file['size']??0)<=0||(int)$file['size']>self::MAX_FILE_SIZE)throw new DomainException('Excel dosyası boş veya 10 MB sınırını aşıyor.');
        if(mb_strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='xlsx')throw new DomainException('Yalnızca .xlsx dosyası yüklenebilir.');
        $mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        if(!in_array($mime,['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],true))throw new DomainException('Dosya geçerli bir XLSX çalışma kitabı değil.');
        return (string)$file['tmp_name'];
    }

    private function exceliOku(string $path): array
    {
        try{$reader=IOFactory::createReaderForFile($path);$reader->setReadDataOnly(true);$book=$reader->load($path);}catch(\Throwable){throw new DomainException('Excel dosyası okunamadı veya bozuk.');}
        $sheet=null;$header=0;$cols=[];$format='mutabakat';
        foreach($book->getWorksheetIterator() as $candidate){try{[$header,$cols]=$this->basliklariBul($candidate);$sheet=$candidate;break;}catch(DomainException){}}
        if(!$sheet){
            $format='muhasebe_bordro';
            foreach($book->getWorksheetIterator() as $candidate){try{[$header,$cols]=$this->muhasebeBordroBasliklariniBul($candidate);$sheet=$candidate;break;}catch(DomainException){}}
        }
        if(!$sheet){$book->disconnectWorksheets();throw new DomainException('Excel başlıkları tanınmadı. Mutabakat veya muhasebe maaş bordrosu şablonunu yükleyin.');}
        $rows=[];
        for($r=$header+1;$r<=$sheet->getHighestDataRow();$r++){
            $name=trim((string)$sheet->getCell([$cols['ad'],$r])->getFormattedValue());
            if($name===''||str_starts_with($this->normalizeBaslik($name),'toplam'))continue;
            $fieldLabels=$format==='muhasebe_bordro'?self::MUHASEBE_BORDRO_ALANLARI:self::ALANLAR;
            $fields=[];foreach($fieldLabels as $key=>$label)$fields[$key]=in_array($key,self::GUNLER,true)?$this->gun($sheet,$cols[$key],$r):$this->para($sheet,$cols[$key],$r);
            $tc=preg_replace('/\D+/','',(string)$sheet->getCell([$cols['tc'],$r])->getFormattedValue());
            if($format==='muhasebe_bordro'&&strlen($tc)!==11)continue;
            $rows[]=['satir'=>$r,'personel'=>$name,'tc'=>$tc,'normalize_ad'=>$this->normalizeAd($name),'alanlar'=>$fields];
        }
        $title=$sheet->getTitle();$book->disconnectWorksheets();
        if(!$rows)throw new DomainException('Mutabakat sayfasında karşılaştırılabilir personel satırı bulunamadı.');
        return [$rows,['sayfa'=>$title,'excel_satir_sayisi'=>count($rows),'format'=>$format]];
    }

    private function basliklariBul(Worksheet $sheet): array
    {
        $a=['ad'=>['adı soyadı','adi soyadi'],'tc'=>['tc kimlik no','t.c. kimlik no'],'toplam_gun'=>['toplam gün'],
            'fiili_gun'=>['fiilî gün','fiili gün'],'izin_gun'=>['izin günü'],'rapor_gun'=>['rapor günü'],
            'asgari'=>['asgari / normal ücret','asgari ücret'],'yemek'=>['yemek yardımı'],'es_yardimi'=>['eş yardımı'],
            'rtc'=>['resmî tatil çalışması (net)','resmi tatil çalışması (net)'],'htc'=>['h.t. çalışması (net)'],
            'fazla_mesai'=>['fazla mesai (net)'],'prim'=>['prim / ikramiye (resmî)','prim / ikramiye (resmi)'],
            'diger'=>['diğer resmî eklenecek','diğer resmi eklenecek'],'avans'=>['avans'],'icra'=>['icra'],
            'diger_kesinti'=>['diğer kesintiler'],'banka'=>['bankaya yatacak tutar']];
        for($r=1;$r<=min(40,$sheet->getHighestDataRow());$r++){
            $found=[];$max=Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for($c=1;$c<=$max;$c++){$v=$this->normalizeBaslik((string)$sheet->getCell([$c,$r])->getFormattedValue());foreach($a as $key=>$vals)if(!isset($found[$key])&&in_array($v,array_map([$this,'normalizeBaslik'],$vals),true))$found[$key]=$c;}
            if(count($found)===count($a))return[$r,$found];
        }
        throw new DomainException('Mutabakat başlıkları bulunamadı.');
    }

    private function muhasebeBordroBasliklariniBul(Worksheet $sheet): array
    {
        $aliases=['ad'=>['adı soyadı'],'tc'=>['tckn'],'toplam_gun'=>['t.gün'],'normal_gun'=>['normal gün'],
            'hafta_tatili_gun'=>['hafta tatili'],'ucretli_izin_gun'=>['ücretli izin'],
            'rapor_gun'=>['rapor'],'yemek'=>['yemek'],'banka'=>['n.ödenen']];
        for($r=1;$r<=min(40,$sheet->getHighestDataRow());$r++){
            $found=[];$max=Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for($c=1;$c<=$max;$c++){
                $v=$this->normalizeBaslik((string)$sheet->getCell([$c,$r])->getFormattedValue());
                foreach($aliases as $key=>$vals)if(!isset($found[$key])&&in_array($v,array_map([$this,'normalizeBaslik'],$vals),true))$found[$key]=$c;
            }
            if(count($found)===count($aliases)){
                // Muhasebe şablonunda kazanç tutarı, gün/adet başlığının sağındaki kolondadır.
                $found['yemek']++;
                return[$r,$found];
            }
        }
        throw new DomainException('Muhasebe bordro başlıkları bulunamadı.');
    }

    private function sistemSatirlari(int $firmaId,int $donemId): array
    {
        $donem=(new BordroDonemModel())->getDonemById($donemId);
        if(!$donem||(int)($donem->firma_id??0)!==$firmaId)throw new DomainException('Seçili bordro dönemi bulunamadı.');
        $model=new BordroPersonelModel();$param=new BordroParametreModel();$asgari=(float)($param->getGenelAyar('asgari_ucret_net',$donem->baslangic_tarihi)??17002.12);$rows=[];
        foreach($model->getPersonellerByDonem($donemId) as $p){
            $hesap=$model->hesaplaOrtakGosterimDegerleri($p,$donem,$asgari);if(empty($hesap['hesaplanmis']))continue;
            if(!array_key_exists('ucretliIzinGunu',$hesap))$hesap['ucretliIzinGunu']=$model->getUcretliIzinGunu($p->personel_id,$donem->baslangic_tarihi,$donem->bitis_tarihi);
            $ozet=$model->getMuhasebeOdemeOzeti($hesap);$fm=$model->getMuhasebeFazlaMesaiNetOzeti($p,$donem);$ozet['muhasebe_fazla_mesai_net']=(float)($fm['tutar']??0);$d=$model->getMuhasebeResmiKalemDagilimi($ozet);
            $detay=!empty($p->hesaplama_detay)?json_decode((string)$p->hesaplama_detay,true):[];
            $matrah=is_array($detay['matrahlar']??null)?$detay['matrahlar']:[];
            $rapor=(int)($matrah['rapor_gunu']??$model->getGunSayisiByKisaKod($p->personel_id,$donem->baslangic_tarihi,$donem->bitis_tarihi,'RP'));
            $toplamGun=(int)($ozet['toplam_gun']??0);
            $normalGun=(int)($matrah['normal_gun']??$ozet['fiili_gun']??0);
            $ucretliIzinGun=(int)($matrah['ucretli_izin_gunu']??$hesap['ucretliIzinGunu']??0);
            $genelTatilGun=(int)($matrah['genel_tatil_gunu']??0);
            // Eski snapshotlarda hafta tatili ayrı saklanmamış olabilir. Parasal sonuçlara
            // dokunmadan, kayıtlı toplamın normal/ücretli izin/genel tatil bakiyesi kullanılır.
            $haftaTatiliGun=(int)($matrah['hafta_tatili_gunu']??max(0,$toplamGun-$normalGun-$ucretliIzinGun-$genelTatilGun));
            $m=static fn($v)=>(int)round((float)$v*100);$key='b'.(int)$p->id;
            $rows[$key]=['personel'=>(string)$p->adi_soyadi,'tc'=>preg_replace('/\D+/','',(string)($p->tc_kimlik_no??'')),'normalize_ad'=>$this->normalizeAd((string)$p->adi_soyadi),'bordro_id'=>(int)$p->id,
                'alanlar'=>['toplam_gun'=>$toplamGun,'fiili_gun'=>(int)$ozet['fiili_gun'],'normal_gun'=>$normalGun,
                'hafta_tatili_gun'=>$haftaTatiliGun,'ucretli_izin_gun'=>$ucretliIzinGun,
                'izin_gun'=>(int)$ozet['izin_gun'],'rapor_gun'=>$rapor,
                'asgari'=>$m($d['asgari']),'yemek'=>$m($d['yemek']),'es_yardimi'=>$m($d['es_yardimi']),'rtc'=>$m($d['rtc']),'htc'=>$m($d['htc']),
                'fazla_mesai'=>$m($d['fazla_mesai']),'prim'=>$m($d['prim']),'diger'=>$m($d['diger']),'avans'=>$m($ozet['muhasebe_banka_avans']),
                'icra'=>$m($ozet['muhasebe_banka_icra']),'diger_kesinti'=>$m($ozet['muhasebe_banka_diger_kesinti']),'banka'=>$m($ozet['banka_odemesi'])]];
        }
        return[$rows,$donem];
    }

    private function eslesenSistemSatiri(array $e,array $s,array $used): array{if($e['tc']!=='')foreach($s as $k=>$r)if(!isset($used[$k])&&$r['tc']===$e['tc'])return[$k,$r,'tc'];$c=[];foreach($s as $k=>$r)if(!isset($used[$k])&&$r['normalize_ad']===$e['normalize_ad'])$c[$k]=$r;if(count($c)===1){$k=array_key_first($c);return[$k,$c[$k],'ad'];}return[null,null,null];}
    private function alanFarklari(array $e,array $s,?array $keys=null): array{$f=[];foreach($keys??array_keys(self::ALANLAR) as $k){$label=self::ALANLAR[$k]??self::MUHASEBE_BORDRO_ALANLARI[$k]??$k;if($e[$k]===$s[$k])continue;$f[]=['alan'=>$k,'etiket'=>$label,'muhasebe'=>$e[$k],'sistem'=>$s[$k],'fark'=>$e[$k]-$s[$k],'tur'=>in_array($k,self::GUNLER,true)?'sayi':'para'];}return$f;}
    private function sonucSatiri(?array $e,?array $s,string $durum,string $aciklama,array $fark,array $keys,?string $match=null): array
    {
        $values=[];
        foreach($keys as $key){$money=!in_array($key,self::GUNLER,true);$muhasebe=$e['alanlar'][$key]??null;$sistem=$s['alanlar'][$key]??null;$values[$key]=['muhasebe'=>$muhasebe,'sistem'=>$sistem,'fark'=>$muhasebe!==null&&$sistem!==null?$muhasebe-$sistem:null,'tur'=>$money?'para':'sayi'];}
        return['personel'=>(string)($s['personel']??$e['personel']??''),'tc'=>$this->maskTc((string)($s['tc']??$e['tc']??'')),'durum'=>$durum,'aciklama'=>$aciklama,'eslesme'=>$match,'excel_satir'=>$e['satir']??null,'bordro_id'=>$s['bordro_id']??null,'fark_sayisi'=>count($fark),'farklar'=>$fark,'degerler'=>$values];
    }
    private function para(Worksheet $s,int $c,int $r): int{return $this->kurus($s->getCell([$c,$r])->getCalculatedValue());}
    private function gun(Worksheet $s,int $c,int $r): int{return (int)round((float)str_replace(',','.',preg_replace('/[^0-9,.-]/u','',(string)$s->getCell([$c,$r])->getFormattedValue())));}
    private function kurus(mixed $v): int{if($v===null||$v==='')return 0;if(is_numeric($v))return(int)round((float)$v*100);$x=preg_replace('/[^0-9,.-]/u','',(string)$v);if(str_contains($x,',')&&str_contains($x,'.'))$x=strrpos($x,',')>strrpos($x,'.')?str_replace(',','.',str_replace('.','',$x)):str_replace(',','',$x);elseif(str_contains($x,','))$x=str_replace(',','.',$x);return(int)round((float)$x*100);}
    private function normalizeAd(string $v): string{$v=mb_strtoupper(trim(preg_replace('/\s+/u',' ',$v)),'UTF-8');return strtr($v,['İ'=>'I','Ş'=>'S','Ğ'=>'G','Ü'=>'U','Ö'=>'O','Ç'=>'C','Â'=>'A','Î'=>'I','Û'=>'U']);}
    private function normalizeBaslik(string $v): string
    {
        $v=str_replace("\u{0307}",'',mb_strtolower(trim(preg_replace('/\s+/u',' ',$v)),'UTF-8'));
        return strtr($v,['ı'=>'i','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c','î'=>'i','â'=>'a','û'=>'u']);
    }
    private function maskTc(string $v): string{return strlen($v)===11?substr($v,0,3).'*****'.substr($v,-3):'';}
}
