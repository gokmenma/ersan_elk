<?php

declare(strict_types=1);

if (session_status()===PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__,2).'/bootstrap.php';

use App\Helper\Security;
use App\Model\PuantajMutabakatModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
$action=(string)($_POST['action']??'');
try {
    Gate::authorizeApiPolicy('puantaj/mutabakat-api',$action);
    $firmaId=(int)($_SESSION['firma_id']??0);
    $userId=(int)($_SESSION['user_id']??0);
    if ($firmaId<=0 || $userId<=0) throw new RuntimeException('Geçerli oturum bulunamadı.');
    $model=new PuantajMutabakatModel();
    $periodToken=(string)($_POST['period_id']??'');
    $periodId=$periodToken!=='' ? (int)Security::decrypt($periodToken) : 0;
    if ($action==='list') {
        $result=$model->ajaxList(['period_id'=>$periodId]+$_POST,$firmaId);
        foreach ($result['data'] as &$row) {
            $row+=PuantajMutabakatModel::describeDifference($row);
            $row['encrypted_id']=Security::encrypt((int)$row['id']);
            unset($row['id'],$row['eski_payload_json'],$row['yeni_payload_json']);
        }
        unset($row);
        echo json_encode($result,JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='summary') {
        $data=$model->summary($firmaId,$periodId,(string)($_POST['kaynak']??''));
        $data->donem=null;
        foreach ($model->periods($firmaId) as $period) {
            if ($periodId===0 || (int)$period['id']===$periodId) {
                $data->donem=['donem'=>$period['donem'],'durum'=>$period['durum'],'son_kontrol_tarihi'=>$period['son_kontrol_tarihi'],
                    'final_kontrol_tarihi'=>$period['final_kontrol_tarihi'],'yeniden_hesaplama_gerekli'=>(int)$period['yeniden_hesaplama_gerekli'],
                    'zaman_cizelgesi'=>$model->periodTimeline((int)$period['id'])];
                break;
            }
        }
        echo json_encode(['status'=>'success','data'=>$data],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='apply') {
        $token=(string)($_POST['csrf_token']??'');
        if ($token==='' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$token)) { http_response_code(419); throw new RuntimeException('Oturum doğrulaması başarısız.'); }
        $id=(int)Security::decrypt((string)($_POST['id']??''));
        $model->applyDifference($id,$firmaId,$userId,(string)($_POST['reason']??''));
        (new SystemLogModel())->logAction($userId,'Puantaj Mutabakatı Uygulama',"Mutabakat farkı #{$id} uygulandı. Gerekçe: ".trim((string)($_POST['reason']??'')),SystemLogModel::LEVEL_IMPORTANT);
        echo json_encode(['status'=>'success','message'=>'Güncel dış sistem verisi uygulandı. Bordro yeniden hesaplanmalıdır.'],JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action==='apply_bulk') {
        $token=(string)($_POST['csrf_token']??'');
        if ($token==='' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$token)) { http_response_code(419); throw new RuntimeException('Oturum doğrulaması başarısız.'); }
        $limit=3000;
        $params=['period_id'=>$periodId]+$_POST;
        if (($_POST['mode']??'')==='filtered') {
            $ids=$model->openDifferenceIds($params,$firmaId,$limit+1);
        } else {
            $ids=$model->filterDifferenceIds(array_map(static fn($v)=>(int)Security::decrypt((string)$v),(array)($_POST['ids']??[])),$params,$firmaId);
        }
        if (!$ids) throw new RuntimeException('Uygulanacak açık fark bulunamadı.');
        if (count($ids)>$limit) throw new RuntimeException("Tek seferde en fazla {$limit} fark uygulanabilir; filtreyi daraltın.");
        if (!empty($_POST['preview'])) {
            echo json_encode(['status'=>'success','data'=>['count'=>count($ids)]],JSON_UNESCAPED_UNICODE); exit;
        }
        set_time_limit(600);
        $reason=(string)($_POST['reason']??'');
        $result=$model->applyDifferences($ids,$firmaId,$userId,$reason);
        $failedTotal=array_sum($result['failed']);
        $source=PuantajMutabakatModel::normalizeSource((string)($_POST['kaynak']??''));
        (new SystemLogModel())->logAction($userId,'Puantaj Mutabakatı Toplu Uygulama',
            "Kaynak: {$source}, uygulanan: {$result['applied']}, uygulanamayan: {$failedTotal}. Gerekçe: ".trim($reason),SystemLogModel::LEVEL_IMPORTANT);
        echo json_encode(['status'=>'success','data'=>$result,
            'message'=>"{$result['applied']} fark uygulandı".($failedTotal?", {$failedTotal} fark uygulanamadı":'').'. Bordro yeniden hesaplanmalıdır.'],JSON_UNESCAPED_UNICODE); exit;
    }
    throw new RuntimeException('Geçersiz işlem.');
} catch (RuntimeException $e) {
    http_response_code(http_response_code()>=400?http_response_code():400);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('Puantaj mutabakat API hatası: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'İşlem sırasında beklenmeyen bir hata oluştu.'],JSON_UNESCAPED_UNICODE);
}
