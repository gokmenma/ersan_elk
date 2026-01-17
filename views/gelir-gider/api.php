<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Helper\Helper;
use App\Helper\Security;
use App\Helper\Date;

use App\Model\GelirGiderModel;
use App\Model\TanimlamalarModel;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Replace;
use Random\Engine\Secure;

$GelirGider = new GelirGiderModel();
$Tanimlamalar = new TanimlamalarModel();

//Gelir gider kaydet
if ($_POST["action"] == "gelir-gider-kaydet") {
    $id = Security::decrypt($_POST["gelir_gider_id"]);
    $son_kayit = null;
    try {

        $data = [
            "id" => $id,
            "type" => $_POST["type"],
            "islem_tarihi" => date("Y-m-d H:i:s", strtotime($_POST["islem_tarihi"])),
            "islem_turu" => $_POST["islem_turu"],
            "tutar" => Helper::formattedMoneyToNumber($_POST["tutar"]),
            "aciklama" => $_POST["aciklama"],
        ];
        //yeni kayıt olduğu zaman kayıt yapanı al
        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"];
        }
        $data["hesap_adi"] = $_POST["hesap_adi_text"];
       

        $lastInsertId = $GelirGider->saveWithAttr($data) ?? $_POST["gelir_gider_id"];
        $status = "success";
        $message = "İşlem başarılı bir şekilde gerçekleştirildi.";

        //tabloya eklemek için eklenen veya güncellen kaydı getir
        $son_kayit = $GelirGider->getGelirGiderTableRow(Security::decrypt($lastInsertId));

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message,
        "son_kayit" => $son_kayit,
        "id" => $lastInsertId,
        "data" => $data,
    ];

    echo json_encode($res);
}

//Gelir gider getir
if ($_POST["action"] == "gelir-gider-getir") {
    $id = Security::decrypt($_POST["gelir_gider_id"]);
    $data = $GelirGider->find($id);
    echo json_encode($data);
}

//Gelir gider sil
if ($_POST["action"] == "gelir-gider-sil") {
    $id = $_POST["gelir_gider_id"];
    try {
        $GelirGider->delete($id);
        $status = "success";
        $message = "İşlem başarılı bir şekilde gerçekleştirildi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message
    ];

    echo json_encode($res);
}

//Gelir gider türlerini getir
if ($_POST["action"] == "gelir-gider-turu-getir") {
    $type = $_POST["type"];
    $turler = $Tanimlamalar->getGelirGiderTurleriSelect($type);
    echo json_encode($turler);
}


//Excelden gelen verileri kaydet
if ($_POST["action"] == "gelir-gider-excel-kaydet") {
        $file = $_FILES["excelFile"];
        $file_name = $file["name"];
        $kasa_id = ($_POST["kasa_id"]);
    

        $file_tmp = $file["tmp_name"];   
        $file_size = $file["size"];
        $file_error = $file["error"];
        $file_ext = explode(".", $file_name);
        $file_ext = strtolower(end($file_ext));
        $allowed = ["xls", "xlsx"];
    
         if (in_array($file_ext, $allowed)) {
            try {
                //excel dosyasını okuma
                $spreadsheet = IOFactory::load($file_tmp);
                $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
                $data = [];
                foreach ($sheetData as $key => $row) {
                    if ($key == 1) {
                        continue;
                    }

                    $tutar = $row["F"];
                    if($tutar == 0 || $tutar == "" || $tutar == null){
                        continue;
                    }
                    //satırdaki veriyi sayıya çevir
                    $tutar = str_replace(" ", "", $tutar);
                    $tutar = str_replace("-", "", $tutar);
                    


                    //B sütunundaki veriyi kontrol et, GELİR ise 1 değilse 2 yap
                    if($row["B"] === "GELİR" || $row["B"] === "Gelir"){
                        $type = 1;
                        $islem_turu = empty($row["C"]) ? 2 : $row["C"];
                    }else{
                        $type = 2;
                        $islem_turu = empty($row["C"]) ? 1 : $row["C"];
                    }

                     $data = [
                        "id" => 0,
                        "islem_tarihi" => Date::convertExcelDate($row["A"]),
                        "kasa_id" => $kasa_id,
                        "hesap_adi" => Security::escape($row["E"]),
                        "type" => Security::escape($type),
                        "islem_turu" => Security::escape($islem_turu),
                        "tutar" => Security::escape($tutar),
                        "aciklama" => Security::escape($row["G"]),
                    ];
                   $lastInsertedId = $GelirGider->saveWithAttr($data) ?? 0;
                }
    
                $status = "success";
                $message = "Dosya başarıyla yüklendi" ;
            } catch (PDOException $ex) {
                $status = "error";
                $message = $ex->getMessage();
            }
    
        } else {
            $status = "error";
            $message = "Dosya uzantısı uygun değil";
         }
    
        $res = [
            "status" => $status,
            "message" => $message,
            //"data" => $data,
        ];
    
        echo json_encode($res);
}
   