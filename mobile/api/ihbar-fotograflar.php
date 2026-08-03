<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
use App\Helper\Security;
use App\Model\IhbarModel;
use App\Service\Gate;
header('Content-Type: application/json; charset=utf-8');
$userId=(int)($_SESSION['user_id']??$_SESSION['id']??0);
if($userId<=0||empty($_SESSION['firma_id'])||(!Gate::allows('ihbar/list')&&!Gate::isSuperAdmin())){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Yetkisiz erişim.'],JSON_UNESCAPED_UNICODE);exit;}
$id=(int)Security::decrypt((string)($_GET['token']??''));
$model=new IhbarModel();
if($id<=0||!$model->getById($id)){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Kayıt bulunamadı.'],JSON_UNESCAPED_UNICODE);exit;}
$data=array_map(static fn($foto)=>['token'=>Security::encrypt($foto->id)],$model->getFotograflar($id));
echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
