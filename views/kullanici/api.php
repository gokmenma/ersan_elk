<?php
require_once '../../vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;

$User = new UserModel();

// if(!Helper::isAdmin()) {
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'Unauthorized access.'
//     ]);
//     exit;
// };
session_start();

if ($_POST["action"] == "kullanici-kaydet") {
    $id = Security::decrypt($_POST['user_id']) ?? 0;

    $lastInsertedId = 0; // Son eklenen ID başlangıç değeri
    $rowData = ''; // Satır verisi başlangıç değeri

    //User_branchs multiple select, bunu virgül ile birleştir
    if(isset($_POST['user_branchs']) && is_array($_POST['user_branchs'])){

        $user_branchs = implode(',', $_POST['user_branchs']);
    }else{
     $user_branchs = '';
    }
    
    try {
        $data = [
            'id' => $id , // Eğer id varsa deşifre et
            'user_name' => $_POST['user_name'],
            'adi_soyadi' => $_POST['adi_soyadi'],
            'email_adresi' => $_POST['email_adresi'],
            'telefon' => $_POST['telefon'],
            'unvani' => $_POST['unvani'],
            'gorevi' => $_POST['gorevi'],
            'aciklama' => $_POST['aciklama'],
            'owner_id' => $_SESSION["owner_id"],
            'roles' => ($_POST['roles']),
            'sube_id' => $user_branchs
        ];
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }

        

        $lastInsertedId = $User->saveWithAttr($data) ?? $_POST['user_id'];

        //Eklenen kullanıcıya ait verileri satıra aktarmak için verileri getir
        $rowData = $User->renderUserTableRow(Security::decrypt($lastInsertedId),$id == 0 ? true : false);



        $status = "success";
        $message = "Kullanıcı başarıyla kaydedildi.";
    } catch (PDOException $ex) {

        if( $ex->getCode() == 23000) { // 23000 hata kodu, genellikle benzersiz kısıtlama ihlali anlamına gelir
            $message = "Bu kullanıcı adı veya e-posta zaten kayıtlı.";
        } else {
            $message = $ex->getMessage();
        }
        $status = "error";
    }
    $res = [
        'status' => $status,
        'message' => $message,
        'lastInsertedId' => $lastInsertedId,
        'rowData' => $rowData
    ];
    echo json_encode($res);
}


//Kullanıcı silme işlemi
if ($_POST["action"] == "kullanici-sil") {
    $id = $_POST['id'] ;

    try {
        $User->delete($id);
        $status = "success";
        $message = "Kullanıcı başarıyla silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
}
