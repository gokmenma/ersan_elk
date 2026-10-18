<?php

declare(strict_types=1);

require_once dirname(__DIR__,2).'/bootstrap.php';

use App\Model\PuantajMutabakatModel;
use App\Model\SettingsModel;
use App\Service\MailGonderService;
use App\Service\PuantajMutabakatService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Yalnız CLI.'); }
date_default_timezone_set('Europe/Istanbul');
set_time_limit(0);

$options=getopt('', ['period::','check-day::','snapshot','force']);
$now=new DateTimeImmutable('now');
$manualPeriod=(string)($options['period']??'');
$manualDay=isset($options['check-day'])?(int)$options['check-day']:0;
$force=array_key_exists('force',$options);
$snapshotOnly=array_key_exists('snapshot',$options);
$model=new PuantajMutabakatModel();
$service=new PuantajMutabakatService($model);
$lock='puantaj_mutabakat_worker';

if (!$model->acquireLock($lock)) { echo "Başka bir mutabakat worker'ı çalışıyor.\n"; exit(0); }
try {
    foreach ($model->activeCompanyIds() as $firmaId) {
        $_SESSION['firma_id']=$firmaId;
        $settings=(new SettingsModel())->getAllSettingsAsKeyValue($firmaId);
        if (($settings['puantaj_mutabakat_aktif']??'1')!=='1') continue;
        $isMonthEnd=$now->format('Y-m-d')===$now->modify('last day of this month')->format('Y-m-d');
        $snapshotTime=$now->format('H:i')>='23:55';
        if ($snapshotOnly || ($isMonthEnd && $snapshotTime)) {
            $period=$manualPeriod!==''?$manualPeriod:$now->format('Y-m');
            if ($period < PuantajMutabakatModel::START_PERIOD) continue;
            $result=$model->createSnapshot($firmaId,$period);
            echo "Firma {$firmaId} {$period} snapshot: {$result['manifest_hash']}\n";
            if ($snapshotOnly) continue;
        }
        $checkDay=$manualDay ?: (int)$now->format('j');
        $scheduled=$checkDay>=1 && $checkDay<=3 && $now->format('H:i')>='03:00' && $now->format('H:i')<'03:15';
        if (!$force && !$scheduled) continue;
        $period=$manualPeriod!==''?$manualPeriod:$now->modify('last month')->format('Y-m');
        if ($period < PuantajMutabakatModel::START_PERIOD) continue;
        $snapshot=$model->createSnapshot($firmaId,$period);
        if (!$force && $model->isCheckComplete((int)$snapshot['period_id'],$checkDay)) {
            echo "Firma {$firmaId} {$period} kontrol günü {$checkDay} daha önce tamamlandı.\n";
            continue;
        }
        $result=$service->reconcile($firmaId,$period,$checkDay,static function(string $source,string $date,int $count,int $diffs,?string $error=null):void {
            echo sprintf("%s %s: %d kayıt, %d fark%s\n",$date,$source,$count,$diffs,$error?' HATA: '.$error:'');
        });
        $recipients=array_values(array_filter(array_map('trim',preg_split('/[,;]+/',(string)($settings['puantaj_mutabakat_eposta']??'')))));
        if ($recipients && ((int)$result['difference_count']>0 || $result['status']==='hata')) {
            $subject="Puantaj Mutabakatı {$period}: ".mb_strtoupper((string)$result['status'],'UTF-8');
            $html='<h3>'.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</h3>'
                .'<p>Açık fark: '.(int)$result['open_differences'].'<br>Eksik kontrol: '.(int)$result['missing_runs'].'<br>Kontrol günü: '.$checkDay.'</p>';
            $attachments=[]; $tmpPath=null;
            $rows=$model->reportRows((int)$result['period_id'],$checkDay);
            if ($rows) {
                $tmpPath=tempnam(sys_get_temp_dir(),'mutabakat_');
                $handle=fopen($tmpPath,'wb');
                fwrite($handle,"\xEF\xBB\xBF");
                fputcsv($handle,['Kaynak','Tarih','Fark Türü','Eski Miktar','Yeni Miktar','Durum'],';');
                foreach ($rows as $row) fputcsv($handle,array_values($row),';');
                fclose($handle);
                $attachments[]=['path'=>$tmpPath,'name'=>"puantaj_mutabakat_{$period}_gun{$checkDay}.csv"];
            }
            try { MailGonderService::gonder($recipients,$subject,$html,$attachments,[],[],null,$firmaId); }
            finally { if ($tmpPath && is_file($tmpPath)) unlink($tmpPath); }
        }
    }
} finally {
    $model->releaseLock($lock);
}
