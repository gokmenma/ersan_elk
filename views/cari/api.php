<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Helper;
use App\Helper\Date;
use App\Model\CariModel;
use App\Model\CariHareketleriModel;

$Cari = new CariModel();
$CariHareket = new CariHareketleriModel();

$action = $_POST["action"] ?? "";

// Cari Listesi (DataTable)
if ($action == "cari-ajax-list") {
    try {
        $res = $Cari->ajaxList($_POST);
        
        $formattedData = [];
        foreach ($res['data'] as $row) {
            $enc_id = Security::encrypt($row->id);
            $bakiye = $row->bakiye ?? 0;
            $color = $bakiye < 0 ? 'danger' : ($bakiye > 0 ? 'success' : 'dark');
            
            $actions = '
                <div class="dropdown text-center">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded font-size-20 text-dark"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item hesap-hareketleri" href="index.php?p=cari/hesap-hareketleri&id=' . $enc_id . '" data-id="' . $enc_id . '">
                            <i class="bx bx-list-ul font-size-16 me-1"></i> Hareketler
                        </a>
                        <a class="dropdown-item hareket-ekle" href="#" data-id="' . $enc_id . '">
                            <i class="bx bx-plus-circle font-size-16 me-1 text-success"></i> Hareket Ekle
                        </a>
                        <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                            <i class="bx bx-edit font-size-16 me-1"></i> Düzenle
                        </a>
                        <a class="dropdown-item cari-sil" href="#" data-id="' . $enc_id . '">
                            <i class="bx bx-trash font-size-16 me-1 text-danger"></i> Sil
                        </a>
                    </div>
                </div>';

            $formattedData[] = [
                "id" => $row->id,
                "CariAdi" => $row->CariAdi,
                "Telefon" => $row->Telefon ?: '-',
                "Email" => $row->Email ?: '-',
                "Adres" => $row->Adres ?: '-',
                "bakiye" => '<span class="fw-bold text-' . $color . '">' . Helper::formattedMoney(abs($bakiye)) . 
                            ($bakiye < 0 ? ' (B)' : ($bakiye > 0 ? ' (A)' : '')) . '</span>',
                "actions" => $actions
            ];
        }
        
        $res['data'] = $formattedData;
        $res['summary'] = $Cari->summary();
        
        echo json_encode($res);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'data' => []]);
    }
    exit;
}

// Cari Kaydet (Ekle/Güncelle)
if ($action == "cari-kaydet") {
    $id = Security::decrypt($_POST["cari_id"] ?? "");
    try {
        $data = [
            "id" => $id ?: 0,
            "CariAdi" => $_POST["CariAdi"],
            "Telefon" => $_POST["Telefon"],
            "Email" => $_POST["Email"],
            "Adres" => $_POST["Adres"],
            "Aktif" => 1
        ];

        $Cari->saveWithAttr($data);
        echo json_encode(["status" => "success", "message" => "Cari başarıyla kaydedildi."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Cari Getir
if ($action == "cari-getir") {
    $id = Security::decrypt($_POST["cari_id"]);
    $data = $Cari->find($id);
    echo json_encode($data);
    exit;
}

// Cari Sil
if ($action == "cari-sil") {
    $id = $_POST["cari_id"];
    $deleteMovements = isset($_POST["delete_movements"]) && $_POST["delete_movements"] == "1";
    try {
        $decId = Security::decrypt($id);
        $Cari->softDelete($decId);
        if ($deleteMovements) {
            $db = $Cari->getDb();
            $stmt = $db->prepare("UPDATE cari_hareketleri SET silinme_tarihi = NOW() WHERE cari_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$decId]);
        }
        echo json_encode(["status" => "success", "message" => "Cari başarıyla silindi."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Cari Hareketleri (DataTable)
if ($action == "hesap-hareketleri-ajax-list") {
    $cari_id = Security::decrypt($_POST["cari_id"]);
    try {
        $_POST['cari_id'] = $cari_id; // Decrypted ID'yi set et
        $res = $CariHareket->ajaxList($_POST);
        
        $formattedData = [];
        foreach ($res['data'] as $row) {
            $enc_hareket_id = Security::encrypt($row->id);
            $actions = '
                <div class="dropdown text-center">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded font-size-20 text-dark"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item hareket-duzenle" href="#" data-id="' . $enc_hareket_id . '">
                            <i data-feather="edit-2" class="me-1" style="width: 14px; height: 14px;"></i> Düzenle
                        </a>
                        <a class="dropdown-item hareket-sil" href="#" data-id="' . $enc_hareket_id . '">
                            <i data-feather="trash-2" class="me-1 text-danger" style="width: 14px; height: 14px;"></i> Sil
                        </a>
                    </div>
                </div>';
            
            $formattedData[] = [
                "islem_tarihi" => date('d.m.Y H:i', strtotime($row->islem_tarihi)),
                "belge_no" => $row->belge_no ?: '-',
                "aciklama" => $row->aciklama ?: '-',
                "borc" => $row->borc > 0 ? Helper::formattedMoney($row->borc) : '-',
                "alacak" => $row->alacak > 0 ? Helper::formattedMoney($row->alacak) : '-',
                "yuruyen_bakiye" => '<span class="fw-bold ' . ($row->yuruyen_bakiye < 0 ? 'text-danger' : ($row->yuruyen_bakiye > 0 ? 'text-success' : '')) . '">' . 
                                    Helper::formattedMoney(abs($row->yuruyen_bakiye)) . 
                                    ($row->yuruyen_bakiye < 0 ? ' (B)' : ($row->yuruyen_bakiye > 0 ? ' (A)' : '')) . '</span>',
                "actions" => $actions
            ];
        }
        
        $res['data'] = $formattedData;
        echo json_encode($res);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'data' => []]);
    }
    exit;
}

// Hızlı Hareket Kaydet (Aldım/Verdim)
if ($action == "hizli-hareket-kaydet") {
    $hareket_id = Security::decrypt($_POST["hareket_id"] ?? "");
    $cari_id = Security::decrypt($_POST["cari_id"]);
    $type = $_POST["type"]; // aldim | verdim
    $tutar = Helper::formattedMoneyToNumber($_POST["tutar"]);
    
    // Flatpickr d.m.Y H:i gönderir, DB için Y-m-d H:i:s yapalım.
    $tarih_str = $_POST["islem_tarihi"];
    $tarih = date('Y-m-d H:i:s', strtotime($tarih_str));
    $belge_no = $_POST["belge_no"];
    $aciklama = $_POST["aciklama"];

    try {
        $data = [
            "id" => $hareket_id ?: 0,
            "cari_id" => $cari_id,
            "islem_tarihi" => $tarih,
            "belge_no" => $belge_no,
            "aciklama" => $aciklama,
            "borc" => ($type == 'verdim' ? $tutar : 0),
            "alacak" => ($type == 'aldim' ? $tutar : 0)
        ];

        $CariHareket->saveWithAttr($data);
        
        // Yeni Bakiyeleri Hesapla
        $stmt = $Cari->getDb()->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $cari_id]);
        $ozet = $stmt->fetch(PDO::FETCH_OBJ);

        echo json_encode([
            "status" => "success", 
            "message" => "İşlem başarıyla kaydedildi.",
            "new_bakiye_raw" => $ozet->bakiye ?? 0,
            "new_bakiye" => Helper::formattedMoney(abs($ozet->bakiye ?? 0)),
            "new_borc" => Helper::formattedMoney($ozet->toplam_borc ?? 0),
            "new_alacak" => Helper::formattedMoney($ozet->toplam_alacak ?? 0)
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Hareket Getir
if ($action == "hareket-getir") {
    $id = Security::decrypt($_POST["hareket_id"]);
    $data = $CariHareket->find($id);
    
    // Form için formatla
    if ($data) {
        $data->islem_tarihi = date('d.m.Y H:i', strtotime($data->islem_tarihi));
        $data->tutar = Helper::formattedMoney($data->borc > 0 ? $data->borc : $data->alacak);
        $data->type = $data->borc > 0 ? 'verdim' : 'aldim';
    }
    
    echo json_encode($data);
    exit;
}

// Hareket Sil
if ($action == "hareket-sil") {
    $id = Security::decrypt($_POST["hareket_id"]);
    try {
        $data = $CariHareket->find($id);
        $CariHareket->softDelete($id);
        
        // Yeni Bakiyeleri Hesapla
        $stmt = $Cari->getDb()->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $data->cari_id]);
        $ozet = $stmt->fetch(PDO::FETCH_OBJ);
        
        echo json_encode([
            "status" => "success", 
            "message" => "İşlem başarıyla silindi.", 
            "new_bakiye_raw" => $ozet->bakiye ?? 0,
            "new_bakiye" => Helper::formattedMoney(abs($ozet->bakiye ?? 0)),
            "new_borc" => Helper::formattedMoney($ozet->toplam_borc ?? 0),
            "new_alacak" => Helper::formattedMoney($ozet->toplam_alacak ?? 0)
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}
