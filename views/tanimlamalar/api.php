<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Service\Gate;
use App\Model\TanimlamalarModel;
use App\Model\SettingsModel;

$Tanimlamalar = new TanimlamalarModel();
$Settings = new SettingsModel();
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
    $ekip_bolge = $_POST["ekip_bolge"] ?? '';
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

    /**Bölge kuralı validasyonu */
    if (!empty($ekip_bolge)) {
        $kurallarJson = $Settings->getSettings('ekip_kodu_bolge_kurallari') ?? '{}';
        $bolgeKurallari = json_decode($kurallarJson, true) ?: [];

        if (isset($bolgeKurallari[$ekip_bolge])) {
            // Ekip kodundan sayıyı çıkar (örn: "ER-SAN ELEKTRİK EKİP-10" -> 10)
            if (preg_match('/(\d+)$/', $tur_adi, $matches)) {
                $ekipNo = intval($matches[1]);
                $kural = $bolgeKurallari[$ekip_bolge];

                if ($ekipNo < $kural['min'] || $ekipNo > $kural['max']) {
                    $status = "error";
                    $message = "{$ekip_bolge} bölgesi için ekip numarası {$kural['min']} ile {$kural['max']} arasında olmalıdır. Girilen: {$ekipNo}";
                    echo json_encode(["status" => $status, "message" => $message]);
                    exit;
                }
            } else {
                $status = "error";
                $message = "Ekip kodunun sonunda bir sayı bulunmalıdır (örn: ER-SAN ELEKTRİK EKİP-10)";
                echo json_encode(["status" => $status, "message" => $message]);
                exit;
            }
        }
    }





    try {
        $data = [
            "id" => $id,
            "firma_id" => $firma_id,
            "type" => 0, // Ekip kodu için type 0
            "grup" => "ekip_kodu",
            "ekip_bolge" => $_POST["ekip_bolge"],
            "tur_adi" => $_POST["ekip_kodu"],
            "birden_fazla_personel_kullanabilir" => isset($_POST["birden_fazla_personel_kullanabilir"]) ? 1 : 0,
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

// Ekip Kodu Geçmişini Getir
if (isset($_POST["action"]) && $_POST["action"] == "ekip-kodu-gecmis-getir") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $data = $Tanimlamalar->getEkipGecmisi($id);
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = [];
    }
    echo json_encode(["status" => $status, "data" => $data]);
}

// Ekip Kodu Sil
if (isset($_POST["action"]) && $_POST["action"] == "ekip-kodu-sil") {
    $id = Security::decrypt($_POST["id"]);
    try {

        /**Eğer yapilan_isler veya endeks_okuma veya personel tablosundan bu ekip id kullanılıyorsa silmeye izin verme */

        /** Önce peronel tablosunu kontrol et */
        $ekipKoduKullaniliyormu = $Tanimlamalar->ekipKoduKullaniliyormu($id);
        if ($ekipKoduKullaniliyormu) {
            $status = "error";
            $message = "Bu ekip kodu kullanılıyor. Silinemez.";
            echo json_encode(["status" => $status, "message" => $message]);
            exit;
        }


        $Tanimlamalar->softDelete($id);
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
            "is_turu_ucret" => Helper::formattedMoneyToNumber($_POST["is_turu_ucret"] ?? "0"),
            "aracli_personel_is_turu_ucret" => Helper::formattedMoneyToNumber($_POST["aracli_personel_is_turu_ucret"] ?? "0"),
            "is_emri_sonucu" => $_POST["is_emri_sonucu"] ?? "",
            "tur_adi" => $_POST["is_turu"] ?? "",
            "rapor_sekmesi" => $_POST["rapor_sekmesi"] ?? "",
            "aciklama" => $_POST["aciklama"] ?? "",
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

        /** Önce yapilan_isler tablosundan bu is turu kullanılıyormu kontrol et */
        $isTuruKullaniliyor = $Tanimlamalar->isTuruKullaniliyor($id);
        if ($isTuruKullaniliyor) {
            $status = "error";
            $message = "Bu is turu kullanılıyor. Silinemez.";
            echo json_encode(["status" => $status, "message" => $message]);
            exit;
        }



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
            "kisa_kod" => $_POST["kisa_kod"],
            "aciklama" => $_POST["aciklama"],
            "ucretli_mi" => (int) ($_POST["ucretli_mi"] ?? 0),
            "normal_mesai_sayilir" => (int) ($_POST["normal_mesai_sayilir"] ?? 0),
            "personel_gorebilir" => (int) ($_POST["personel_gorebilir"] ?? 0),
            "renk" => $_POST["renk"],
            "ikon" => $_POST["ikon"],
            "yetkili_onayina_tabi" => (int) ($_POST["yetkili_onayina_tabi"] ?? 0)
        ];

        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"] ?? 0;
            $data["firma_id"] = $_SESSION["firma_id"];
            $plainId = $Tanimlamalar->saveWithAttr($data);
        } else {
            // Debug için logla (geçici)
            // error_log("Updating ID $id with yetkili_onayina_tabi: " . $_POST["yetkili_onayina_tabi"]);
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
        "is_update" => ($id != 0),
        "data" => $data
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

// Bölge Kurallarını Getir
if (isset($_POST["action"]) && $_POST["action"] == "bolge-kurallari-getir") {
    try {
        $kurallarJson = $Settings->getSettings('ekip_kodu_bolge_kurallari') ?? '{}';
        $kurallar = json_decode($kurallarJson, true) ?: [];

        echo json_encode([
            "status" => "success",
            "kurallar" => $kurallar
        ]);
    } catch (PDOException $ex) {
        echo json_encode([
            "status" => "error",
            "message" => $ex->getMessage(),
            "kurallar" => []
        ]);
    }
    exit;
}

// Bölge Kurallarını Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "bolge-kurallari-kaydet") {
    // Yetki kontrolü
    Gate::can('ekip_kodu_kurallari');

    try {
        $kurallar = $_POST["kurallar"] ?? '{}';

        // JSON formatını doğrula
        $kurallarArray = json_decode($kurallar, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Geçersiz JSON formatı");
        }

        // Kuralları kaydet
        $Settings->upsertSetting('ekip_kodu_bolge_kurallari', $kurallar);

        echo json_encode([
            "status" => "success",
            "message" => "Bölge kuralları başarıyla kaydedildi."
        ]);
    } catch (PDOException $ex) {
        echo json_encode([
            "status" => "error",
            "message" => "Veritabanı hatası: " . $ex->getMessage()
        ]);
    } catch (Exception $ex) {
        echo json_encode([
            "status" => "error",
            "message" => $ex->getMessage()
        ]);
    }
    exit;
}

// Unvan Ücret Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "unvan-ucret-kaydet") {
    $id = Security::decrypt($_POST["unvan_ucret_id"]);
    $son_kayit = null;
    $plainId = 0;
    try {
        $data = [
            "id" => $id,
            "type" => 0,
            "firma_id" => $firma_id,
            "grup" => "unvan_ucret",
            "unvan_departman" => $_POST["unvan_departman"] ?? "",
            "tur_adi" => $_POST["unvan_adi"] ?? "",
            "unvan_ucret" => Helper::formattedMoneyToNumber($_POST["unvan_ucret"] ?? "0"),
            "aciklama" => $_POST["aciklama"] ?? "",
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
    exit;
}

// Unvan Ücret Getir (tek kayıt)
if (isset($_POST["action"]) && $_POST["action"] == "unvan-ucret-getir") {
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
    exit;
}

// Unvan Ücret Sil
if (isset($_POST["action"]) && $_POST["action"] == "unvan-ucret-sil") {
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
    exit;
}

// Departmana göre unvan/ücretleri getir (personel modülü için AJAX)
if (isset($_POST["action"]) && $_POST["action"] == "unvan-ucretleri-getir") {
    $departman = $_POST["departman"] ?? "";
    try {
        $data = $Tanimlamalar->getUnvanUcretlerByDepartman($departman);
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = [];
    }
    echo json_encode(["status" => $status, "data" => $data]);
    exit;
}

// Demirbaş Kategorisi Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "demirbas-kategorisi-kaydet") {
    $id = Security::decrypt($_POST["kategori_id"]);
    $plainId = 0;
    try {
        $data = [
            "id" => $id,
            "type" => 0,
            "grup" => "demirbas_kategorisi",
            "tur_adi" => $_POST["kategori_adi"],
            "aciklama" => $_POST["aciklama"]
        ];

        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"] ?? 0;
            $data["firma_id"] = $_SESSION["firma_id"];
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
        "id" => $plainId,
        "is_update" => ($id != 0)
    ];

    echo json_encode($res);
    exit;
}

// Demirbaş Kategorisi Getir
if (isset($_POST["action"]) && $_POST["action"] == "demirbas-kategorisi-getir") {
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
    exit;
}

// Demirbaş Kategorisi Sil
if (isset($_POST["action"]) && $_POST["action"] == "demirbas-kategorisi-sil") {
    $id = Security::decrypt($_POST["id"]);
    try {
        // Kontrol et: Kategori kullanılıyor mu?
        if ($Tanimlamalar->isDemirbasKategorisiKullaniliyor($id)) {
            echo json_encode(["status" => "error", "message" => "Bu kategori demirbaşlar tarafından kullanılıyor. Önce ilgili demirbaşların kategorisini değiştirin veya silin."]);
            exit;
        }

        $Tanimlamalar->softDelete($id);
        $status = "success";
        $message = "Kayıt silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    echo json_encode(["status" => $status, "message" => $message, "deleted_id" => $id]);
    exit;
}

// Defter Kodu Kaydet
if (isset($_POST["action"]) && $_POST["action"] == "defter-kodu-kaydet") {
    $id = Security::decrypt($_POST["id"]);
    $plainId = 0;
    $son_kayit = null;
    try {
        $data = [
            "id" => $id,
            "type" => 1,
            "grup" => "defter_kodu",
            "tur_adi" => $_POST["tur_adi"],
            "defter_bolge" => $_POST["defter_bolge"],
            "defter_mahalle" => $_POST["defter_mahalle"] ?? "",
            "defter_abone_sayisi" => $_POST["defter_abone_sayisi"] ?? null,
            "baslangic_tarihi" => !empty($_POST["baslangic_tarihi"]) ? date("Y-m-d", strtotime($_POST["baslangic_tarihi"])) : null,
            "bitis_tarihi" => !empty($_POST["bitis_tarihi"]) ? date("Y-m-d", strtotime($_POST["bitis_tarihi"])) : null,
            "aciklama" => $_POST["aciklama"] ?? ""
        ];

        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"] ?? 0;
            $data["firma_id"] = $_SESSION["firma_id"];
            $plainId = $Tanimlamalar->saveWithAttr($data);
        } else {
            $Tanimlamalar->saveWithAttr($data);
            $plainId = $id;
        }

        $status = "success";
        $message = "İşlem başarılı bir şekilde gerçekleştirildi.";

        // Return HTML structure for Datatable row replacement integration
        $son_kayit = $Tanimlamalar->getDefterKoduTableRow($plainId);

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }

    echo json_encode([
        "status" => $status,
        "message" => $message,
        "id" => $plainId,
        "son_kayit" => $son_kayit,
        "is_update" => ($id != 0)
    ]);
    exit;
}

// Defter Kodu Getir
if (isset($_POST["action"]) && $_POST["action"] == "defter-kodu-getir") {
    $id = Security::decrypt($_POST["id"]);
    try {
        $data = $Tanimlamalar->find($id);
        if ($data) {
            $data->encrypted_id = $_POST["id"];
            // Formate dates for output
            if ($data->baslangic_tarihi) {
                $data->baslangic_tarihi = date("d.m.Y", strtotime($data->baslangic_tarihi));
            }
            if ($data->bitis_tarihi) {
                $data->bitis_tarihi = date("d.m.Y", strtotime($data->bitis_tarihi));
            }
        }
        $status = "success";
    } catch (PDOException $ex) {
        $status = "error";
        $data = null;
    }
    echo json_encode(["status" => $status, "data" => $data]);
    exit;
}

// Defter Kodu Sil
if (isset($_POST["action"]) && $_POST["action"] == "defter-kodu-sil") {
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
    exit;
}

// Defter Kodu Excel Yükle
if (isset($_POST["action"]) && $_POST["action"] == "defter-kodu-excel-yukle") {
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
        $expectedHeaders = ['DEFTER', 'BÖLGESİ', 'DEFTER MAHALLE', 'ABONE SAYISI', 'Başlangıç Tarihi', 'Bitiş Tarihi', 'AÇIKLAMA'];
        $headerMap = [];

        foreach ($expectedHeaders as $expected) {
            foreach ($headers as $index => $header) {
                if (mb_strtolower($header) === mb_strtolower($expected)) {
                    $headerMap[$expected] = $index;
                    break;
                }
            }
            if (!isset($headerMap[$expected]) && $expected === 'DEFTER') {
                throw new \Exception("Excel dosyasında 'DEFTER' sütunu bulunamadı.");
            }
        }

        $updateCount = 0;
        $insertCount = 0;
        $errorRows = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $colDefter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['DEFTER'] ?? 0) + 1);
            $colBolge = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['BÖLGESİ'] ?? 1) + 1);
            $colMahalle = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['DEFTER MAHALLE'] ?? 2) + 1);
            $colAbone = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['ABONE SAYISI'] ?? 3) + 1);
            $colBaslangic = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['Başlangıç Tarihi'] ?? 4) + 1);
            $colBitis = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['Bitiş Tarihi'] ?? 5) + 1);
            $colAciklama = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($headerMap['AÇIKLAMA'] ?? 6) + 1);

            $defterKodu = trim($worksheet->getCell($colDefter . $rowIndex)->getValue() ?? '');
            if (empty($defterKodu))
                continue;

            $bolgesi = trim($worksheet->getCell($colBolge . $rowIndex)->getValue() ?? '');
            $mahalle = trim($worksheet->getCell($colMahalle . $rowIndex)->getValue() ?? '');
            $aboneSayisi = trim($worksheet->getCell($colAbone . $rowIndex)->getValue() ?? '');

            // Tarih okuma
            $baslangicObj = $worksheet->getCell($colBaslangic . $rowIndex);
            $baslangicVal = $baslangicObj->getValue();
            if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($baslangicObj)) {
                $baslangicTarihi = date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($baslangicVal));
            } else {
                $baslangicTarihi = !empty($baslangicVal) ? date('Y-m-d', strtotime(str_replace('.', '-', $baslangicVal))) : null;
            }

            $bitisObj = $worksheet->getCell($colBitis . $rowIndex);
            $bitisVal = $bitisObj->getValue();
            if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($bitisObj)) {
                $bitisTarihi = date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($bitisVal));
            } else {
                $bitisTarihi = !empty($bitisVal) ? date('Y-m-d', strtotime(str_replace('.', '-', $bitisVal))) : null;
            }

            $aciklama = trim($worksheet->getCell($colAciklama . $rowIndex)->getValue() ?? '');

            try {
                $existingRecord = $Tanimlamalar->findByColumns([
                    'tur_adi' => $defterKodu,
                    'defter_mahalle' => $mahalle
                ], 'grup = "defter_kodu" AND silinme_tarihi IS NULL');

                if ($existingRecord) {
                    $data = [
                        "id" => $existingRecord->id,
                        "type" => 1,
                        "grup" => "defter_kodu",
                        "tur_adi" => $defterKodu,
                        "defter_bolge" => $bolgesi,
                        "defter_mahalle" => $mahalle,
                        "defter_abone_sayisi" => $aboneSayisi,
                        "baslangic_tarihi" => $baslangicTarihi,
                        "bitis_tarihi" => $bitisTarihi,
                        "aciklama" => $aciklama,
                    ];
                    $Tanimlamalar->saveWithAttr($data);
                    $updateCount++;
                } else {
                    $data = [
                        "id" => 0,
                        "type" => 1,
                        "grup" => "defter_kodu",
                        "tur_adi" => $defterKodu,
                        "defter_bolge" => $bolgesi,
                        "defter_mahalle" => $mahalle,
                        "defter_abone_sayisi" => $aboneSayisi,
                        "baslangic_tarihi" => $baslangicTarihi,
                        "bitis_tarihi" => $bitisTarihi,
                        "aciklama" => $aciklama,
                        "kayit_yapan" => $_SESSION["id"] ?? 0,
                        "firma_id" => $_SESSION["firma_id"] ?? 1
                    ];
                    $Tanimlamalar->saveWithAttr($data);
                    $insertCount++;
                }
            } catch (\Exception $e) {
                $errorRows[] = [
                    'row' => $rowIndex,
                    'tur_adi' => $defterKodu,
                    'error' => $e->getMessage()
                ];
            }
        }

        $message = "";
        if ($insertCount > 0)
            $message .= "$insertCount yeni kayıt eklendi. ";
        if ($updateCount > 0)
            $message .= "$updateCount kayıt güncellendi. ";
        if (count($errorRows) > 0)
            $message .= count($errorRows) . " satırda hata oluştu.";
        if (empty($message))
            $message = "İşlenecek veri bulunamadı.";

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

// Defter Kodu Listesi (Server-Side)
if (isset($_POST["action"]) && $_POST["action"] == "defter-kodu-liste") {
    $result = $Tanimlamalar->getServerSideData('defter_kodu', $_POST);

    $data = [];
    foreach ($result['data'] as $row) {
        $enc_id = Security::encrypt($row->id);
        $nestedData = [];
        $nestedData['id'] = $row->id;
        $nestedData['tur_adi'] = $row->tur_adi;
        $nestedData['defter_bolge'] = $row->defter_bolge;
        $nestedData['defter_mahalle'] = $row->defter_mahalle;
        $nestedData['defter_abone_sayisi'] = $row->defter_abone_sayisi;
        $nestedData['baslangic_tarihi'] = $row->baslangic_tarihi ? date('d.m.Y', strtotime($row->baslangic_tarihi)) : '';
        $nestedData['bitis_tarihi'] = $row->bitis_tarihi ? date('d.m.Y', strtotime($row->bitis_tarihi)) : '';
        $nestedData['aciklama'] = $row->aciklama;
        $nestedData['islem'] = '
            <div class="flex-shrink-0">
                <div class="dropdown align-self-start">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                        data-bs-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item duzenle"
                            data-id="' . $enc_id . '"><span
                                class="mdi mdi-account-edit font-size-18"></span>
                            Düzenle</a>
                        <a href="#" class="dropdown-item sil" data-id="' . $enc_id . '">
                            <span class="mdi mdi-delete font-size-18"></span>
                            Sil</a>
                    </div>
                </div>
            </div>';
        $data[] = $nestedData;
    }

    $result['data'] = $data;
    echo json_encode($result);
    exit;
}
