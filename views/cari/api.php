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
                        <i data-feather="more-vertical" class="text-dark" style="width: 20px; height: 20px;"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item hesap-hareketleri" href="index.php?p=cari/hesap-hareketleri&id=' . $enc_id . '" data-id="' . $enc_id . '">
                            <i data-feather="list" class="font-size-16 me-1" style="width: 14px; height: 14px;"></i> Hareketler
                        </a>
                        <a class="dropdown-item hareket-ekle" href="#" data-id="' . $enc_id . '">
                            <i data-feather="plus-circle" class="font-size-16 me-1 text-success" style="width: 14px; height: 14px;"></i> Hareket Ekle
                        </a>
                        <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                            <i data-feather="edit" class="font-size-16 me-1" style="width: 14px; height: 14px;"></i> Düzenle
                        </a>
                        <a class="dropdown-item cari-sil" href="#" data-id="' . $enc_id . '">
                            <i data-feather="trash" class="font-size-16 me-1 text-danger" style="width: 14px; height: 14px;"></i> Sil
                        </a>
                    </div>
                </div>';

            $formattedData[] = [
                "id" => $row->id,
                "CariAdi" => $row->CariAdi,
                "firma" => $row->firma ?: '-',
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
            "firma" => $_POST["firma"] ?? null,
            "Telefon" => $_POST["Telefon"],
            "Email" => $_POST["Email"],
            "Adres" => $_POST["Adres"],
            "notlar" => $_POST["notlar"] ?? null,
            "Aktif" => 1
        ];

        $Cari->saveWithAttr($data);
        echo json_encode(["status" => "success", "message" => "Cari başarıyla kaydedildi."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Cari Not Kaydet
if ($action == "cari-not-kaydet") {
    $id = Security::decrypt($_POST["cari_id"]);
    $notlar = $_POST["notlar"];
    try {
        $Cari->db->prepare("UPDATE cari SET notlar = ? WHERE id = ?")->execute([$notlar, $id]);
        echo json_encode(["status" => "success", "message" => "Not başarıyla güncellendi."]);
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
                        <i data-feather="more-vertical" class="text-dark" style="width: 20px; height: 20px;"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item hareket-duzenle" href="#" data-id="' . $enc_hareket_id . '">
                            <i data-feather="edit" class="me-1" style="width: 14px; height: 14px;"></i> Düzenle
                        </a>
                        <a class="dropdown-item hareket-sil" href="#" data-id="' . $enc_hareket_id . '">
                            <i data-feather="trash" class="me-1 text-danger" style="width: 14px; height: 14px;"></i> Sil
                        </a>
                    </div>
                </div>';
            
            $formattedData[] = [
                "islem_tarihi" => date('d.m.Y H:i', strtotime($row->islem_tarihi)),
                "belge_no" => $row->belge_no ?: '-',
                "aciklama" => $row->aciklama ?: '-',
                "dosya" => $row->dosya ?: null,
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
        // Dosya Yükleme İşlemi
        $dosya_adi = null;
        if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__, 2) . '/uploads/cari_belgeler/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES['dosya']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array($file_ext, $allowed_exts)) {
                $dosya_adi = uniqid('cari_') . '.' . $file_ext;
                if (!move_uploaded_file($_FILES['dosya']['tmp_name'], $upload_dir . $dosya_adi)) {
                    $dosya_adi = null;
                }
            }
        }

        $data = [
            "id" => $hareket_id ?: 0,
            "cari_id" => $cari_id,
            "islem_tarihi" => $tarih,
            "belge_no" => $belge_no,
            "aciklama" => $aciklama,
            "borc" => ($type == 'aldim' ? $tutar : 0),
            "alacak" => ($type == 'verdim' ? $tutar : 0)
        ];

        // Eğer yeni dosya yüklendiyse dataya ekle
        if ($dosya_adi) {
            $data["dosya"] = $dosya_adi;
        }

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
        $data->tutar_raw = $data->borc > 0 ? (float)$data->borc : (float)$data->alacak;
        $data->type = $data->borc > 0 ? 'aldim' : 'verdim';
        $data->cari_id_enc = Security::encrypt($data->cari_id);
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

// Tüm Hareketleri Getir (Global Mobil Bottom Sheet için)
if ($action == "tum-hareketler-getir") {
    $search = $_POST["search"] ?? "";
    $type = $_POST["type"] ?? "all"; // all | aldim | verdim
    $baslangic = $_POST["baslangic"] ?? "";
    $bitis = $_POST["bitis"] ?? "";
    $cari_id = Security::decrypt($_POST["cari_id"] ?? "");

    $where = "h.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL";
    $params = [];

    if (!empty($cari_id)) {
        $where .= " AND h.cari_id = :cari_id";
        $params['cari_id'] = $cari_id;
    }

    if (!empty($search)) {
        $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR h.aciklama LIKE :search OR h.belge_no LIKE :search)";
        $params['search'] = "%$search%";
    }

    if ($type == 'aldim') {
        $where .= " AND h.borc > 0";
    } elseif ($type == 'verdim') {
        $where .= " AND h.alacak > 0";
    }

    if (!empty($baslangic)) {
        $where .= " AND DATE(h.islem_tarihi) >= :baslangic";
        $params['baslangic'] = $baslangic;
    }
    if (!empty($bitis)) {
        $where .= " AND DATE(h.islem_tarihi) <= :bitis";
        $params['bitis'] = $bitis;
    }

    $sql = "SELECT h.*, c.CariAdi, c.firma,
            (SELECT SUM(h2.alacak - h2.borc) 
             FROM cari_hareketleri h2 
             JOIN cari c2 ON h2.cari_id = c2.id 
             WHERE h2.silinme_tarihi IS NULL 
               AND c2.silinme_tarihi IS NULL
               AND (h2.islem_tarihi < h.islem_tarihi OR (h2.islem_tarihi = h.islem_tarihi AND h2.id <= h.id))) as global_yuruyen_bakiye
            FROM cari_hareketleri h
            LEFT JOIN cari c ON h.cari_id = c.id
            WHERE $where
            ORDER BY h.islem_tarihi DESC, h.id DESC LIMIT 50";

    try {
        $db = $Cari->getDb();
        $stmt = $db->prepare($sql);
        foreach($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_OBJ);

        $formatted = [];
        foreach ($res as $row) {
            $formatted[] = [
                "id" => Security::encrypt($row->id),
                "CariAdi" => $row->CariAdi,
                "firma" => $row->firma,
                "aciklama" => $row->aciklama,
                "tarih" => date('d.m.Y H:i', strtotime($row->islem_tarihi)),
                "amt" => $row->borc > 0 ? (float)$row->borc : (float)$row->alacak,
                "is_borc" => $row->borc > 0,
                "belge_no" => $row->belge_no,
                "dosya" => $row->dosya,
                "yuruyen" => (float)($row->global_yuruyen_bakiye ?? 0)
            ];
        }
        echo json_encode($formatted);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

