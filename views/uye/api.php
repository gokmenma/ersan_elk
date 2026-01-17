<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';



use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Model\DefineModel;

$Define = new DefineModel();

use App\Model\UyeModel;
$Uye = new UyeModel();

use App\Model\UyeTalepModel;
$UyeTalep = new UyeTalepModel();

use App\Model\UyeIslemModel;
$UyeIslem = new UyeIslemModel();

use App\Model\UyeFinansalIslemModel;
$UyeFinansalIslem = new UyeFinansalIslemModel();

use App\Model\NotModel;
$Notes = new NotModel();



//action = uye_kaydet
if ($_POST['action'] == 'uye-kaydet') {

    $lastInsertId = 0;
    $uyelik_bilgi = [];
    $id = Security::decrypt($_POST['uye_id']);
    try {
        $data = [
            'id' => $id,
            'sube_id' => $_SESSION['sube_id'],
            'uye_no' => $_POST['uye_no'],
            'adi_soyadi' => $_POST['adi_soyadi'],
            'tc_kimlik' => $_POST['tc_kimlik'],
            'dogum_tarihi' => $_POST['dogum_tarihi'],
            'unvan' => $_POST['unvan'],
            'telefon' => $_POST['telefon'],
            'email' => $_POST['email'],
            "kurumu" => $_POST['calistigi_kurum'],
            "birimi" => $_POST['calistigi_birim'],
            "il" => $_POST['uye_il'],
        ];


        $lastInsertId = $Uye->saveWithAttr($data) ?? $_POST['uye_id'];

        if ($id === 0) {
            $Define->setUyeNo();
            // ekleme başarılı ise üyelik bilgileri de eklenir
            $data = [
                'id' => 0,
                'uye_id' => Security::decrypt($lastInsertId),
                'uyelik_tarihi' => date('Ymd'),
                'istifa_tarihi' => null,
                'aciklama' => "Üye kaydı yapıldı.",
            ];
            $lastInsertId_uyelik = $UyeIslem->saveWithAttr($data);

            //Eklenen üyelik bilgisi döndürülür
            $uyelik_bilgi = $UyeIslem->getUyeIslemTableRow(Security::decrypt($lastInsertId_uyelik));


            $message = "Üye kaydedildi ve üyelik bilgileri eklendi.";
        } else {
            $message = "Üye güncellendi.";
        }

        $status = "success";

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'id' => $lastInsertId,
        'uyelik_bilgi' => $uyelik_bilgi
    ];

    echo json_encode($res);
}

//action = uye_sil
if ($_POST['action'] == 'uye_sil') {
    $id = ($_POST['id']);

    try {

        $Uye->delete($id);

        $status = "success";
        $message = "Üye silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    //Geriye mesaj ve durum bilgisi 
    $res = [
        'status' => $status,
        'message' => $message
    ];
      echo json_encode($res);

}

//Üye işlem Kaydet
if ($_POST['action'] == 'uye-islem-kaydet') {

    $lastInsertId = 0;
    $son_kayit = [];
    //Eğer islem_id 0 ise yeni kayıt, değilse güncelleme yapılıyor

    $id = $_POST["islem_id"] !== 0 ? Security::decrypt($_POST['islem_id']) : 0;
    $uye_id = Security::decrypt($_POST['uye_id']);
    $istifa_tarihi = $_POST['istifa_tarihi'] == "" ? null : Date::Ymd($_POST['istifa_tarihi']);


    //Eğer istifa tarihi boş olan bir kayıt mı kontrol et, varsaa üye olduğunu belirten mesaj döndür
    //$aktif_uye_mi = $UyeIslem->isUyeAktif($uye_id, $id);
    // if ($aktif_uye_mi > 0 && $id == 0) {
    //     $res = [
    //         'status' => "error",
    //         'message' => "Kaydetmeye çalışıtığınız üye zaten aktif ."
    //     ];
    //     echo json_encode($res);
    //     exit();
    // }


    try {
        $data = [
            'id' => $id,
            'uye_id' => $uye_id,
            'uyelik_tarihi' => Date::Ymd($_POST['uyelik_tarihi']),
            'istifa_tarihi' => $istifa_tarihi,
            'karar_tarihi_no' => $_POST['karar_tarihi_no'],
            'giden_evrak' => $_POST['giden_evrak'],
            'birim_evrak' => $_POST['birim_evrak'],
            'aciklama' => $_POST['aciklama'],
        ];

        $lastInsertId = $UyeIslem->saveWithAttr($data) ?? $_POST['islem_id'];

        $status = "success";
        $message = "Üye işlem kaydedildi."  ;

        //eklenen veriyi döndür
        $son_kayit = $UyeIslem->getUyeIslemTableRow(Security::decrypt($lastInsertId));

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'son_kayit' => $son_kayit
    ];

    echo json_encode($res);
}

//Üye işlem Bilgisi getir (güncelleme yapılacağı zaman id'ye göre bilgiler getirilir)
if ($_POST['action'] == 'uye-islem-bilgi') {

    $id = Security::decrypt($_POST['islem_id']);
    $data = $UyeIslem->find($id);


    //eğer kayıt bulunduysa status success döndür, bulunamadıysa error döndür
    if ($data) {
        $data->status = "success";
        $data->uyelik_tarihi = Date::dmY($data->uyelik_tarihi, 'd.m.Y');
        $data->istifa_tarihi = Date::dmY($data->istifa_tarihi, 'd.m.Y');
    } else {
        $data = [
            'status' => "error",
            'message' => "Kayıt bulunamadı."
        ];
    }

    echo json_encode($data);
}

//Üye işlem Sil
if ($_POST['action'] == 'uye-islem-sil') {

    $id = $_POST['islem_id'];
    try {
        $UyeIslem->delete($id);
        $status = "success";
        $message = "Üye işlem silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
    ];

    echo json_encode($res);
}

//Üye finansal islem Kaydet
if ($_POST['action'] == 'finansal-islem-kaydet') {

    $lastInsertId = 0;
    $id = $_POST["finansal_islem_id"] != 0 ? Security::decrypt($_POST['finansal_islem_id']) : 0;
    try {
        $data = [
            'id' => $id,
            'uye_id' => Security::decrypt($_POST['uye_id']),
            'type' => $_POST['type'],
            'islem_tarihi' => Date::Ymd($_POST['islem_tarihi']),
            'islem_turu' => $_POST['islem_turu'],
            'tutar' => Helper::formattedMoneyToNumber($_POST['tutar']),
            'aciklama' => $_POST['finansal_aciklama'],
        ];

        $lastInsertId = $UyeFinansalIslem->saveWithAttr($data) ?? $_POST['finansal_islem_id'];

        $status = "success";
        $message = "Üye finansal işlem kaydedildi.";

        //eklenen veriyi döndür
        $son_kayit = $UyeFinansalIslem->getUyeFinansalIslemTableRow($lastInsertId);

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'son_kayit' => $son_kayit
    ];

    echo json_encode($res);
}

//Üye finansal islem Bilgisi getir (güncelleme yapılacağı zaman id'ye göre bilgiler getirilir)
if ($_POST['action'] == 'finansal-islem-bilgi') {

    $id = Security::decrypt($_POST['finansal_islem_id']);
    $data = $UyeFinansalIslem->find($id);
    //eğer kayıt bulunduysa status success döndür, bulunamadıysa error döndür
    if ($data) {
        $data->status = "success";
        $data->islem_tarihi = Date::dmY($data->islem_tarihi, 'd.m.Y');
        $data->tutar = Helper::formattedMoney($data->tutar);
    } else {
        $data = [
            'status' => "error",
            'message' => "Kayıt bulunamadı."
        ];
    }

    echo json_encode($data);
}


//Üye finansal islem Sil
if ($_POST['action'] == 'finansal-islem-sil') {

    $id = $_POST['finansal_islem_id'];
    try {
        $UyeFinansalIslem->delete($id);
        $status = "success";
        $message = "Üye finansal işlem silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
    ];

    echo json_encode($res);
}

//action = uyelik-talep_onayla
if ($_POST['action'] == 'uyelik_onayla') {
    $id = Security::decrypt($_POST['id']);


    try {
        $data = [
            'id' => $id,
            'onaylandi_mi' => 1,
            'onay_tarihi' => date('Y-m-d H:i:s')
        ];
        $lastInsertId = $UyeTalep->saveWithAttr($data) ?? $id;

        //Eğer onaylama işlemi başarılı ise, uyelik oluştur
        if ($lastInsertId) {

            $talep = $UyeTalep->find($id);
            $data = [
                "id" => 0,
                'uye_no' => $Define->getUyeNo(),
                'adi_soyadi' => $talep->adi_soyadi,
                'tc_kimlik' => $talep->tc_kimlik,
                'dogum_tarihi' => $talep->dogum_tarihi,
                'telefon' => $talep->telefon,
                'email' => $talep->email,

            ];

            $Uye->saveWithAttr($data);
            $Define->setUyeNo();
        }


        $status = "success";
        $message = "Üyelik onaylandı.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        'status' => $status,
        'message' => $message
    ];
    echo json_encode($res);
}

//action = uyelik-talep-sil
if ($_POST['action'] == 'uyelik_talep_sil') {
    $id = ($_POST['id']);

    try {
        $UyeTalep->delete($id);
        $status = "success";
        $message = "Üyelik talebi silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        'status' => $status,
        'message' => $message
    ];
    echo json_encode($res);
}


//action notes-kaydet
if ($_POST['action'] == 'notes-kaydet') {

    $lastInsertId = 0;
    $id = $_POST["note_id"] != 0 ? Security::decrypt($_POST['note_id']) : 0;
    try {
        $data = [
            'id' => $id,
            'uye_id' => Security::decrypt($_POST['uye_id']),
            'tarih' => Date::Ymd($_POST['note_tarihi']),
            'konu' => $_POST['note_konu'],
            'not_aciklama' => $_POST['note'],
        ];

        $lastInsertId = $Notes->saveWithAttr($data) ?? $_POST['note_id'];

        $status = "success";
        $message = "Not kaydedildi.";

        //eklenen veriyi döndür
        $rowData = $Notes->getNotesTableRow($lastInsertId);
        

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'rowData' => $rowData
    ];

    echo json_encode($res);
}

//action notes-bilgi
if ($_POST['action'] == 'not-bilgisi-getir') {

    $id = Security::decrypt($_POST['note_id']);
    $data = $Notes->find($id);
    //eğer kayıt bulunduysa status success döndür, bulunamadıysa error döndür
    if ($data) {
        $data->status = "success";
        $data->tarih = Date::dmY($data->tarih, 'd.m.Y');
    } else {
        $data = [
            'status' => "error",
            'message' => "Kayıt bulunamadı."
        ];
    }

    echo json_encode($data);
}



//action notes-sil
if ($_POST['action'] == 'note-sil') {

    $id = $_POST['note_id'];
    try {
        $Notes->delete($id);
        $status = "success";
        $message = "Not silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
    ];

    echo json_encode($res);
}


//Finansal İşlem Ödendi Yap
if ($_POST['action'] == 'finansal-islem-odendi-yap') {

    $id = Security::decrypt($_POST['finansal_islem_id']);
    try {
        $data = [
            'id' => $id,
            'odendi_mi' => 1,
            'odeme_tarihi' => date('Y-m-d H:i:s')
        ];
        $lastInsertId = $UyeFinansalIslem->saveWithAttr($data) ?? $_POST['finansal_islem_id'];

        $son_kayit =$UyeFinansalIslem->getUyeFinansalIslemTableRow(Security::decrypt($lastInsertId));


        $status = "success";
        $message = "Ödendi yapıldı.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        'status' => $status,
        'message' => $message,
        'id' => $lastInsertId,
        'son_kayit' => $son_kayit ?? []
    ];
    echo json_encode($res);
}