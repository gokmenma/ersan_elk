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

// İş Türü Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "is-turu-kaydet") {
    $id = Security::decrypt($_POST["is_turu_id"]);
    $son_kayit = null;
    $plainId = 0;
    try {
        $data = [
            "id" => $id,
            "type" => 0, // İş türü için type 0
            "grup" => "is_turu",
            "is_turu_ucret" => $_POST["is_turu_ucret"],
            "tur_adi" => $_POST["is_turu"],
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

// İş Türü Getir
if (isset($_POST["action"]) && $_POST["action"] == "is-turu-getir") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $data = $Tanimlamalar->find($id);
        // Map tur_adi to is_turu for frontend compatibility
        $data->is_turu = $data->tur_adi;
        $data->encrypted_id = $_POST["id"];
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = null;
    }
    echo json_encode(["status" => $status, "data" => $data]);
}

// İş Türü Sil
if (isset($_POST["action"]) && $_POST["action"] == "is-turu-sil") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $Tanimlamalar->softDelete($id);
        $status = "success";
        $message = "Kayıt silindi." . $id;
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    echo json_encode(["status" => $status, "message" => $message, "deleted_id" => $id]);
}

// İş Türü Excel Yükle
if (isset($_POST["action"]) && $_POST["action"] == "is-turu-excel-yukle") {
    try {
        // Dosya kontrolü
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Dosya yüklenirken bir hata oluştu.");
        }

        $file = $_FILES['excel_file'];
        $allowedExtensions = ['xlsx', 'xls'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new \Exception("Sadece Excel dosyaları (.xlsx, .xls) yüklenebilir.");
        }

        // PhpSpreadsheet ile dosyayı oku
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        // Başlık satırını kontrol et
        $headers = [];
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = trim($cell->getValue() ?? '');
            }
        }

        // Beklenen başlıklar
        $expectedHeaders = ['İş Türü', 'İş Türü Ücreti', 'Açıklama'];
        $headerMap = [];

        foreach ($expectedHeaders as $expected) {
            $found = false;
            foreach ($headers as $index => $header) {
                if (mb_strtolower($header) === mb_strtolower($expected)) {
                    $headerMap[$expected] = $index;
                    $found = true;
                    break;
                }
            }
            if (!$found && $expected === 'İş Türü') {
                throw new \Exception("Excel dosyasında 'İş Türü' sütunu bulunamadı.");
            }
        }

        $updateCount = 0;
        $insertCount = 0;
        $errorRows = [];

        // Verileri işle (2. satırdan başla - 1. satır başlık)
        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            // Sütun harflerini belirle
            $colTurAdi = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['İş Türü'] ?? 0) + 1);
            $colUcret = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['İş Türü Ücreti'] ?? 1) + 1);
            $colAciklama = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['Açıklama'] ?? 2) + 1);

            $turAdi = trim($worksheet->getCell($colTurAdi . $rowIndex)->getValue() ?? '');
            $isTuruUcret = trim($worksheet->getCell($colUcret . $rowIndex)->getValue() ?? '');
            $aciklama = trim($worksheet->getCell($colAciklama . $rowIndex)->getValue() ?? '');

            // Boş satırları atla
            if (empty($turAdi)) {
                continue;
            }

            try {
                // Tür adına göre mevcut kaydı ara
                $existingRecord = $Tanimlamalar->findByColumn('tur_adi', $turAdi, 'grup = "is_turu" AND silinme_tarihi IS NULL');

                if ($existingRecord) {
                    // Güncelle
                    $data = [
                        "id" => $existingRecord->id,
                        "type" => 0,
                        "grup" => "is_turu",
                        "tur_adi" => $turAdi,
                        "is_turu_ucret" => $isTuruUcret,
                        "aciklama" => $aciklama,
                    ];
                    $Tanimlamalar->saveWithAttr($data);
                    $updateCount++;
                } else {
                    // Yeni kayıt ekle
                    $data = [
                        "id" => 0,
                        "type" => 0,
                        "grup" => "is_turu",
                        "tur_adi" => $turAdi,
                        "is_turu_ucret" => $isTuruUcret,
                        "aciklama" => $aciklama,
                        "kayit_yapan" => $_SESSION["id"] ?? 0,
                    ];
                    $Tanimlamalar->saveWithAttr($data);
                    $insertCount++;
                }
            } catch (\Exception $e) {
                $errorRows[] = [
                    'row' => $rowIndex,
                    'tur_adi' => $turAdi,
                    'error' => $e->getMessage()
                ];
            }
        }

        $message = "";
        if ($insertCount > 0) {
            $message .= "$insertCount yeni kayıt eklendi. ";
        }
        if ($updateCount > 0) {
            $message .= "$updateCount kayıt güncellendi. ";
        }
        if (count($errorRows) > 0) {
            $message .= count($errorRows) . " satırda hata oluştu.";
        }
        if (empty($message)) {
            $message = "İşlenecek veri bulunamadı.";
        }

        $status = ($insertCount > 0 || $updateCount > 0) ? "success" : "warning";

        echo json_encode([
            "status" => $status,
            "message" => trim($message),
            "insertCount" => $insertCount,
            "updateCount" => $updateCount,
            "errorRows" => $errorRows
        ]);

    } catch (\Exception $ex) {
        echo json_encode([
            "status" => "error",
            "message" => $ex->getMessage()
        ]);
    }
    exit;
}
