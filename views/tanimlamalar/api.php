<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;

use App\Model\TanimlamalarModel;

$Tanimlamalar = new TanimlamalarModel();
$firma_id = $_SESSION["firma_id"];

/**firma id boş ise işlem yapma */
if ($firma_id == 0 || $firma_id == null) {
    echo json_encode(["status" => "error", "message" => "Firma bilgileri bulunamadı."]);
    exit;
}


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
    $tur_adi = $_POST["ekip_kodu"];
    $son_kayit = null;
    $plainId = 0;


    /**Ekip kodu tanımlıysa kayıt yapma */
    $isExistingTur = $Tanimlamalar->getEkipKoduVarmi($tur_adi, $id);
    if ($isExistingTur) {
        $status = "error";
        $message = "Bu ekip kodu zaten tanımlı. Başka bir kod giriniz!";
        echo json_encode(["status" => $status, "message" => $message]);
        exit;
    }




    try {
        $data = [
            "id" => $id,
            "firma_id" => $firma_id,
            "type" => 0, // Ekip kodu için type 0
            "grup" => "ekip_kodu",
            "ekip_bolge" => $_POST["ekip_bolge"],
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
            "is_turu_ucret" => Helper::formattedMoneyToNumber($_POST["is_turu_ucret"]),
            "is_emri_sonucu" => $_POST["is_emri_sonucu"],
            "tur_adi" => $_POST["is_turu"],
            "rapor_sekmesi" => $_POST["rapor_sekmesi"],
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
        $expectedHeaders = ['İş Türü', 'İş Emri Sonucu', 'İş Türü Ücreti', 'Açıklama'];
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
            $colIsEmriSonucu = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['İş Emri Sonucu'] ?? 1) + 1);
            $colUcret = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['İş Türü Ücreti'] ?? 2) + 1);
            $colAciklama = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['Açıklama'] ?? 3) + 1);

            $turAdi = trim($worksheet->getCell($colTurAdi . $rowIndex)->getValue() ?? '');
            $isEmriSonucu = trim($worksheet->getCell($colIsEmriSonucu . $rowIndex)->getValue() ?? '');
            $isTuruUcret = trim($worksheet->getCell($colUcret . $rowIndex)->getValue() ?? '');
            $aciklama = trim($worksheet->getCell($colAciklama . $rowIndex)->getValue() ?? '');

            // Boş satırları atla
            if (empty($turAdi)) {
                continue;
            }

            try {
                // Tür adı ve İş Emri Sonucuna göre mevcut kaydı ara
                $existingRecord = $Tanimlamalar->findByColumns([
                    'tur_adi' => $turAdi,
                    'is_emri_sonucu' => $isEmriSonucu
                ], 'grup = "is_turu" AND silinme_tarihi IS NULL');

                if ($existingRecord) {
                    // Güncelle
                    $data = [
                        "id" => $existingRecord->id,
                        "type" => 0,
                        "grup" => "is_turu",
                        "tur_adi" => $turAdi,
                        "is_emri_sonucu" => $isEmriSonucu,
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
                        "is_emri_sonucu" => $isEmriSonucu,
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

// İzin Türü Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "izin-turu-kaydet") {
    $id = Security::decrypt($_POST["izin_turu_id"]);
    $son_kayit = null;
    $plainId = 0;
    try {
        $data = [
            "id" => $id,
            "type" => 0,
            "grup" => "izin_turu",
            "tur_adi" => $_POST["izin_turu"],
            "aciklama" => $_POST["aciklama"],
            "ucretli_mi" => $_POST["ucretli_mi"],
            "personel_gorebilir" => $_POST["personel_gorebilir"],
            "renk" => $_POST["renk"],
            "ikon" => $_POST["ikon"]
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

// İzin Türü Getir
if (isset($_POST["action"]) && $_POST["action"] == "izin-turu-getir") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $data = $Tanimlamalar->find($id);
        $data->encrypted_id = $_POST["id"];
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = null;
    }
    echo json_encode(["status" => $status, "data" => $data]);
}

// İzin Türü Sil
if (isset($_POST["action"]) && $_POST["action"] == "izin-turu-sil") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $Tanimlamalar->softDelete($id);
        $status = "success";
        $message = "Kayıt silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    echo json_encode(["status" => $status, "message" => $message, "deleted_id" => $id]);
}
