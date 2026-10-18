<?php

namespace App\Service;

use App\Model\PuantajMutabakatModel;
use App\Model\SettingsModel;
use DateTimeImmutable;
use RuntimeException;
use App\Service\SayacDegisimService;

class PuantajMutabakatService
{
    private PuantajMutabakatModel $model;

    public function __construct(?PuantajMutabakatModel $model = null)
    {
        $this->model = $model ?: new PuantajMutabakatModel();
    }

    public function createPreviousMonthSnapshot(int $firmaId, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?: new DateTimeImmutable('now');
        return $this->model->createSnapshot($firmaId, $now->modify('last month')->format('Y-m'));
    }

    public function reconcile(int $firmaId, string $period, int $checkDay, callable $progress = null): array
    {
        if ($period < PuantajMutabakatModel::START_PERIOD) throw new RuntimeException('Mutabakat yalnız Ekim 2026 ve sonraki dönemler için çalışır.');
        if ($checkDay < 1 || $checkDay > 3) throw new RuntimeException('Kontrol günü yalnız 1, 2 veya 3 olabilir.');
        $periodId = $this->model->getOrCreatePeriod($firmaId, $period);
        $settings = (new SettingsModel())->getAllSettingsAsKeyValue($firmaId);
        $firstCompany = (int)($settings['online_sorgulama_firma_baslangic'] ?? 17);
        $lastCompany = (int)($settings['online_sorgulama_firma_bitis'] ?? $firstCompany);
        $start = new DateTimeImmutable($period.'-01');
        $end = $start->modify('last day of this month');
        $totalDiffs = 0;

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            foreach (['okuma','kesme','sayac','muhurleme'] as $source) {
                $ymd = $date->format('Y-m-d');
                $runId = $this->model->beginRun($periodId, $checkDay, $source, $ymd);
                try {
                    if ($source==='okuma') $items=$this->fetchReading($ymd,$firstCompany,$lastCompany);
                    elseif ($source==='sayac') $items=$this->fetchMeter($ymd);
                    else $items=array_values(array_filter($this->fetchCut($ymd,$firstCompany,$lastCompany),static fn(array $item):bool=>$item['kaynak']===$source));
                    if (!$items && $this->model->snapshotItemCount($periodId,$source,$ymd)>0) {
                        throw new RuntimeException('Snapshotta kayıt bulunan gün için dış servis boş yanıt verdi; fark üretilmedi.');
                    }
                    $this->model->storeCurrentItems($runId, $items);
                    $this->model->finishRun($runId, count($items));
                    $diffs = $this->model->compareRun($runId);
                    $totalDiffs += $diffs;
                    if ($progress) $progress($source, $ymd, count($items), $diffs);
                } catch (\Throwable $e) {
                    $this->model->failRun($runId, $e->getMessage());
                    if ($progress) $progress($source, $ymd, 0, 0, $e->getMessage());
                }
            }
        }
        return array_merge(['period_id'=>$periodId,'difference_count'=>$totalDiffs], $this->model->finalizePeriod($periodId, $checkDay));
    }

    private function fetchReading(string $date, int $firstCompany, int $lastCompany): array
    {
        $service = new EndeskOkumaService();
        $rows = $this->fetchAllPages(
            static fn(int $limit, int $offset) => $service->getData(date('d/m/Y',strtotime($date)),date('d/m/Y',strtotime($date)),$firstCompany,$lastCompany,$limit,$offset),
            100
        );
        return $this->normalizeReadingRows($rows,$date);
    }

    public function normalizeReadingRows(array $rows,string $date): array
    {
        $items=[];
        $beldeler=['939','944','946','950','951','952','986','1011','1039','1060','1201','1202','1203','1204','1205','1250','1251','1252','1253','1254','1255','1256','1258'];
        foreach ($rows as $row) {
            $bolge=trim((string)($row['BOLGE']??''));
            if ($bolge==='') continue;
            $defter=trim((string)($row['DEFTER']??''));
            if (in_array(ltrim($defter,'0'),$beldeler,true)) $bolge='BELDELER';
            $reader=trim((string)($row['OKUYUCUADI']??''));
            $readerNo=trim((string)($row['OKUYUCUNO']??''));
            $status=trim((string)($row['SAYACDURUM']??''));
            $key=md5($date.'|'.$bolge.'|'.$defter.'|'.$readerNo.'|'.$status);
            if (!isset($items[$key])) $items[$key]=['anahtar'=>$key,'miktar'=>0.0,'acik'=>0.0,'payload'=>['bolge'=>$bolge,'kullanici_adi'=>$reader,'defter'=>$defter,'sayac_durum'=>$status]];
            $items[$key]['miktar']+=(float)($row['ABONE_SAYISI']??0);
        }
        return array_values($items);
    }

    private function fetchCut(string $date, int $firstCompany, int $lastCompany): array
    {
        $service = new KesmeAcmaService();
        $rows = $this->fetchAllPages(
            static fn(int $limit, int $offset) => $service->getData(date('d/m/Y',strtotime($date)),date('d/m/Y',strtotime($date)),$firstCompany,$lastCompany,$limit,$offset),
            500
        );
        return $this->normalizeCutRows($rows,$date);
    }

    public function normalizeCutRows(array $rows,string $date): array
    {
        $items=[];
        foreach ($rows as $row) {
            $type=trim((string)($row['ISEMRITIPI']??''));
            $upper=mb_strtoupper($type,'UTF-8');
            if (str_contains($upper,'DEGISME SÖKME TAKMA') || str_contains($upper,'DEĞİŞME SÖKME TAKMA')) continue;
            $completed=(float)($row['SONUCLANMIS']??0);
            if ($completed == 0.0) continue;
            $team=trim((string)($row['EKIP']??''));
            $result=trim((string)($row['SONUC']??''));
            $key=md5($date.'|'.$team.'|'.$type.'|'.$result);
            $upperResult=mb_strtoupper($result,'UTF-8');
            $source=(str_contains($upper,'MÜHÜR')||str_contains($upper,'MUHUR')||str_contains($upperResult,'MÜHÜR')||str_contains($upperResult,'MUHUR'))?'muhurleme':'kesme';
            if (!isset($items[$key])) $items[$key]=['kaynak'=>$source,'anahtar'=>$key,'miktar'=>0.0,'acik'=>0.0,'payload'=>['ekip'=>$team,'is_emri_tipi'=>$type,'is_emri_sonucu'=>$result,'acik'=>0.0]];
            $items[$key]['miktar'] += $completed;
            $items[$key]['acik'] += (float)($row['ACIK']??0);
            $items[$key]['payload']['acik'] = $items[$key]['acik'];
        }
        return array_values($items);
    }

    private function fetchMeter(string $date): array
    {
        $service=new SayacDegisimService();
        $rows=$this->fetchAllPages(static fn(int $limit,int $offset)=>$service->getData(date('d/m/Y',strtotime($date)),date('d/m/Y',strtotime($date)),$limit,$offset),500);
        return $this->normalizeMeterRows($rows,$date);
    }

    public function normalizeMeterRows(array $rows,string $date): array
    {
        $items=[];
        foreach($rows as $row){
            $team=trim((string)($row['EKIP']??'')); $meter=trim((string)($row['TAKILAN_SAYACNO']??''));
            $subscriber=trim((string)($row['ABONE_NO']??'')); $order=trim((string)($row['ISEMRI_NO']??''));
            $key=md5($order.'|'.$subscriber.'|'.$meter.'|'.$team);
            $resultDate=null;
            if (!empty($row['SONUC_TARIHI'])) {
                $parsed=\DateTime::createFromFormat('d/m/Y H:i:s',(string)$row['SONUC_TARIHI']);
                $resultDate=$parsed?$parsed->format('Y-m-d H:i:s'):date('Y-m-d H:i:s',strtotime(str_replace('/','-',(string)$row['SONUC_TARIHI'])));
            }
            $payload=['ekip'=>$team,'isemri_no'=>$order,'abone_no'=>$subscriber,'isemri_sebep'=>trim((string)($row['ISEMRI_SEBEP']??'')),'memur'=>trim((string)($row['MEMUR']??'')),'sonuclandiran_kullanici'=>trim((string)($row['SONUCLANDIRAN_KULLANICI']??'')),'bolge'=>trim((string)($row['BOLGE']??'')),'isemri_sonucu'=>trim((string)($row['ISEMRI_SONUCU']??'')),'sonuc_aciklama'=>trim((string)($row['SONUC_ACIKLAMA']??'')),'takilan_sayacno'=>$meter,'kayit_tarihi'=>$resultDate];
            $items[$key]=['anahtar'=>$key,'miktar'=>1.0,'acik'=>0.0,'payload'=>$payload];
        }
        return array_values($items);
    }

    private function fetchAllPages(callable $request, int $limit): array
    {
        $all=[];
        for ($offset=0,$page=0; $page<1000; $offset+=$limit,$page++) {
            $response=$request($limit,$offset);
            if (!($response['success']??false)) throw new RuntimeException('Dış servis başarısız yanıt verdi.');
            $batch=$response['data']['data']??[];
            if (!is_array($batch)) throw new RuntimeException('Dış servis veri biçimi geçersiz.');
            foreach ($batch as $row) $all[]=$row;
            if (count($batch)<$limit) return $all;
        }
        throw new RuntimeException('Dış servis sayfalama güvenlik sınırına ulaştı.');
    }
}
