<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
            "tarih" => date("Y-m-d H:i:s", strtotime($_POST["islem_tarihi"])),
            "kategori" => $_POST["islem_turu"],
            "hesap_adi" => $_POST["hesap_adi"],
            "tutar" => Helper::formattedMoneyToNumber($_POST["tutar"]),
            "aciklama" => $_POST["aciklama"],
        ];
        //yeni kayıt olduğu zaman kayıt yapanı al
        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"];
        }

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

//Hesap adlarını getir
if ($_POST["action"] == "hesap-adlari-getir") {
    $veriler = $GelirGider->getUniqueValues('hesap_adi');
    echo json_encode($veriler);
}

//DataTable Benzersiz Değerleri Getir (Gelişmiş Filtreler İçin)
if ($_POST["action"] == "get-unique-values") {
    try {
        $column = $_POST['column'] ?? '';
        $values = $GelirGider->getUniqueValues($column, $_POST);
        echo json_encode(['status' => 'success', 'data' => $values]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


//Excelden gelen verileri kaydet
if ($_POST["action"] == "gelir-gider-excel-kaydet") {
        $file = $_FILES["excelFile"];
        $file_name = $file["name"];

    

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

                    $tutar = $row["E"];
                    if($tutar == 0 || $tutar == "" || $tutar == null){
                        continue;
                    }
                    //satırdaki veriyi sayıya çevir
                    $tutar = str_replace(" ", "", $tutar);
                    $tutar = str_replace("-", "", $tutar);
                    


                    //B sütunundaki veriyi kontrol et, GELİR ise 1 değilse 2 yap
                    $typeStr = mb_strtolower(trim($row["B"] ?? ''), 'UTF-8');
                    if($typeStr === "gelir"){
                        $type = 1;
                        $islem_turu = empty($row["C"]) ? 2 : $row["C"];
                    }else{
                        $type = 2;
                        $islem_turu = empty($row["C"]) ? 1 : $row["C"];
                    }

                     $data = [
                        "id" => 0,
                        "tarih" => Date::convertExcelDate($row["A"]),
                        "type" => Security::escape($type),
                        "kategori" => Security::escape($islem_turu),
                        "hesap_adi" => Security::escape($row["D"]),
                        "tutar" => Security::escape($tutar),
                        "aciklama" => Security::escape($row["F"]),
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

//Gelir gider ajax list (Server-side Datatables)
if ($_POST["action"] == "gelir-gider-ajax-list") {
    try {
        $res = $GelirGider->ajaxList($_POST);
        
        // Her satırı formatla
        $formattedData = [];
        
        foreach ($res['data'] as $row) {
            $enc_id = Security::encrypt($row->id);
            
            $bakiye = $row->bakiye;
            $color = $bakiye < 0 ? 'danger' : 'success';
            
            $actions = '
                <div class="dropdown">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                            <i class="bx bx-edit font-size-18 me-1"></i> Düzenle
                        </a>
                        <a class="dropdown-item gelir-gider-sil" href="#" data-id="' . $enc_id . '">
                            <i class="bx bx-trash font-size-18 me-1"></i> Sil
                        </a>
                    </div>
                </div>';

            $formattedData[] = [
                "id" => $row->id,
                "kayit_tarihi" => $row->kayit_tarihi,
                "type" => Helper::getBadge($row->type),
                "hesap_adi" => $row->hesap_adi ?: '-',
                "kategori_adi" => $row->kategori_adi ?: '-',
                "tarih" => $row->tarih ? date('d.m.Y H:i', strtotime($row->tarih)) : '-',
                "tutar" => Helper::formattedMoney($row->tutar),
                "bakiye" => '<span class="text-' . $color . '">' . Helper::formattedMoney($bakiye) . '</span>',
                "aciklama" => $row->aciklama ?: '-',
                "actions" => $actions
            ];
        }
        
        $res['data'] = $formattedData;
        
        // Ayrıca özeti de gönder ki JS ile kartlar güncellenebilsin
        $res['summary'] = $GelirGider->summary($_POST);
        
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res);
        exit;
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage(), 'data' => []]);
        exit;
    }
}
   