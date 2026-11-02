<?php

declare(strict_types=1);

if (session_status()===PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__,2).'/bootstrap.php';

use App\Helper\Security;
use App\Model\PuantajMutabakatModel;
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
        echo json_encode(['status'=>'success','message'=>'Güncel dış sistem verisi uygulandı. Bordro yeniden hesaplanmalıdır.'],JSON_UNESCAPED_UNICODE); exit;
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
