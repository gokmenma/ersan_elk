<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;

use App\Model\TanimlamalarModel;

$Tanimlamalar = new TanimlamalarModel();

//Gelir gider türü kaydet
if (isset($_POST["action"]) && $_POST["action"] == "gelir-gider-turu-kaydet") {
    $id = Security::decrypt($_POST["tur_id"]);
    $son_kayit = null;
    try {

        $data = [
            "id" => $id,
            "type" => $_POST["type"],
            "tur_adi" => $_POST["gelir_gider_turu"],
            "aciklama" => $_POST["aciklama"],
        ];
        //yeni kayıt olduğu zaman kayıt yapanı al
        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"];
        }

        $lastInsertId = $Tanimlamalar->saveWithAttr($data) ?? $_POST["tur_id"];
        $status = "success";
        $message = "İşlem başarılı bir şekilde gerçekleştirildi.";

        //tabloya eklemek için eklenen veya güncellen kaydı getir
        $son_kayit = $Tanimlamalar->getTableRow(Security::decrypt($lastInsertId));

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message,
        "son_kayit" => $son_kayit
    ];

    echo json_encode($res);
}

// Ekip Kodu Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "ekip-kodu-kaydet") {
    $id = Security::decrypt($_POST["ekip_id"]);
    $son_kayit = null;
    $plainId = 0;
    try {
        $data = [
            "id" => $id,
            "type" => 0, // Ekip kodu için type 0
            "grup" => "ekip_kodu",
            "tur_adi" => $_POST["ekip_kodu"],
            "aciklama" => $_POST["aciklama"],
        ];
        
        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"] ?? 0;
            $plainId = $Tanimlamalar->saveWithAttr($data);
        } else {
            $Tanimlamalar->saveWithAttr($data);
            $plainId = $id;
        }

        $status = "success";
        $message = "İşlem başarılı bir şekilde gerçekleştirildi.";
        
     

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message,
        "son_kayit" => $son_kayit,
        "id" => $plainId,
        "is_update" => ($id != 0)
    ];

    echo json_encode($res);
}

// Ekip Kodu Getir
if (isset($_POST["action"]) && $_POST["action"] == "ekip-kodu-getir") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $data = $Tanimlamalar->find($id);
        // Map tur_adi to ekip_kodu for frontend compatibility
        $data->ekip_kodu = $data->tur_adi;
        $data->encrypted_id = $_POST["id"];
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = null;
    }
    echo json_encode(["status" => $status, "data" => $data]);
}

// Ekip Kodu Sil
if (isset($_POST["action"]) && $_POST["action"] == "ekip-kodu-sil") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $Tanimlamalar->delete($id);
        $status = "success";
        $message = "Kayıt silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    echo json_encode(["status" => $status, "message" => $message, "deleted_id" => $id]);
}
