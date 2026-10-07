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
use App\Model\PermissionPolicyModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Replace;
use Random\Engine\Secure;

$GelirGider = new GelirGiderModel();
$Tanimlamalar = new TanimlamalarModel();

$action = $_POST["action"] ?? "";
header('Content-Type: application/json; charset=utf-8');

$knownActions = [
    'gelir-gider-kaydet', 'gelir-gider-getir', 'gelir-gider-sil',
    'gelir-gider-toplu-sil', 'gelir-gider-turu-getir', 'hesap-adlari-getir',
    'plakalari-getir', 'bankalari-getir', 'get-unique-values',
    'gelir-gider-ajax-list', 'tum-hareketler-getir', 'gelir-gider-excel-kaydet',
];
if (!in_array($action, $knownActions, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanımsız gelir-gider API aksiyonu.']);
    exit;
}

$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('gelir-gider/api', $action);
} elseif (!Gate::allows('gelir_gider_takibi')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu finans işlemi için yetkiniz bulunmamaktadır.']);
    exit;
}

//Gelir gider kaydet
if ($_POST["action"] == "gelir-gider-kaydet") {
    $id = Security::decrypt($_POST["gelir_gider_id"]);
    $son_kayit = null;
    try {
        $data = [
            "id" => $id,
            "type" => $_POST["type"],
            "tarih" => date("Y-m-d H:i:s", strtotime($_POST["islem_tarihi"])),
            "kategori" => $_POST["islem_turu"] ?? '',
            "hesap_adi" => $_POST["hesap_adi"] ?? '',
            "tutar" => Helper::formattedMoneyToNumber($_POST["tutar"]),
            "aciklama" => $_POST["aciklama"] ?? '',
            "plaka" => $_POST["plaka"] ?? '',
            "odeme_sekli" => $_POST["odeme_sekli"] ?? '',
            "banka_adi" => $_POST["banka_adi"] ?? '',
        ];
        //yeni kayıt olduğu zaman kayıt yapanı al
        if ($id == 0) {
            $data["kayit_yapan"] = $_SESSION["id"] ?? 0;
        }

        $lastInsertId = $GelirGider->saveWithAttr($data) ?? $_POST["gelir_gider_id"];
        $status = "success";
        $message = "İşlem başarılı bir şekilde kaydedildi.";

        //tabloya eklemek için eklenen veya güncellenen kaydı getir
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
        "data" => $data ?? [],
    ];

    echo json_encode($res);
    exit;
}

//Gelir gider getir
if ($_POST["action"] == "gelir-gider-getir") {
    $id = Security::decrypt($_POST["gelir_gider_id"]);
    $data = $GelirGider->find($id);
    echo json_encode($data);
    exit;
}

//Gelir gider sil
if ($_POST["action"] == "gelir-gider-sil") {
    $id = $_POST["gelir_gider_id"];
    try {
        $GelirGider->delete($id);
        $status = "success";
        $message = "İşlem başarıyla silindi.";
    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message
    ];

    echo json_encode($res);
    exit;
}

// Toplu Silme
if ($action == "gelir-gider-toplu-sil") {
    $ids = $_POST["ids"] ?? [];
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(["status" => "error", "message" => "Lütfen silinecek en az bir kayıt seçin."]);
        exit;
    }
    try {
        $userId = $_SESSION['id'] ?? 0;
        $GelirGider->bulkDelete($ids, $userId);
        echo json_encode(["status" => "success", "message" => count($ids) . " adet kayıt başarıyla silindi."]);
    } catch (Exception $ex) {
        echo json_encode(["status" => "error", "message" => $ex->getMessage()]);
    }
    exit;
}

//Gelir gider türlerini getir
if ($_POST["action"] == "gelir-gider-turu-getir") {
    $type = $_POST["type"];
    $turler = $Tanimlamalar->getGelirGiderTurleriSelect($type);
    echo json_encode($turler);
    exit;
}

//Hesap adlarını getir
if ($_POST["action"] == "hesap-adlari-getir") {
    $veriler = $GelirGider->getUniqueValues('hesap_adi');
    echo json_encode($veriler);
    exit;
}

// Plakaları getir
if ($action == "plakalari-getir") {
    $plakalar = $GelirGider->getPlakalar();
    echo json_encode($plakalar);
    exit;
}

// Bankaları getir
if ($action == "bankalari-getir") {
    $bankalar = $GelirGider->getBankalar();
    echo json_encode($bankalar);
    exit;
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

//Gelir gider ajax list (Server-side Datatables)
if ($_POST["action"] == "gelir-gider-ajax-list") {
    try {
        $res = $GelirGider->ajaxList($_POST);
        
        // Her satırı formatla
        $formattedData = [];
        
        foreach ($res['data'] as $row) {
            $enc_id = Security::encrypt($row->id);
            
            $bakiye = (float)($row->bakiye ?? 0);
            $bakiyeColor = $bakiye < 0 ? 'text-danger' : 'text-success';
            
            $actions = '
                <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-subtle-warning table-action-btn duzenle" data-id="' . $enc_id . '" title="Düzenle">
                        <i class="bx bx-edit font-size-15"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn gelir-gider-sil" data-id="' . $enc_id . '" title="Sil">
                        <i class="bx bx-trash font-size-15"></i>
                    </button>
                </div>';

            $typeVal = (int)($row->type ?? $row->TYPE ?? 1);
            $typeBadge = ($typeVal === 1)
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-trending-up me-1"></i>Gelir</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-trending-down me-1"></i>Gider</span>';

            $tutar = (float)($row->tutar ?? $row->TUTAR ?? 0);
            $tutarColor = ($typeVal === 1) ? 'text-success' : 'text-danger';
            $tutarFormatted = '<span class="' . $tutarColor . ' fw-semibold">' . Helper::formattedMoney($tutar) . '</span>';

            $chk = '<div class="form-check text-center mb-0"><input class="form-check-input row-check" type="checkbox" value="' . $enc_id . '"></div>';

            $formattedData[] = [
                "DT_RowId" => "gelir_gider_" . $row->id,
                "DT_RowAttr" => [
                    "data-id" => $enc_id,
                    "data-title" => "#" . $row->id . " - " . ($row->hesap_adi ?: 'İşlem')
                ],
                "check" => $chk,
                "id" => $row->id,
                "kayit_tarihi" => (!empty($row->kayit_tarihi)) ? date('d.m.Y H:i', strtotime($row->kayit_tarihi)) : '-',
                "type" => $typeBadge,
                "hesap_adi" => htmlspecialchars($row->hesap_adi ?? $row->HESAP_ADI ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
                "kategori_adi" => htmlspecialchars($row->kategori_adi ?? $row->KATEGORI_ADI ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
                "plaka" => htmlspecialchars($row->plaka ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
                "odeme_sekli" => htmlspecialchars($row->odeme_sekli ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
                "banka_adi" => htmlspecialchars($row->banka_adi ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
                "tarih" => (!empty($row->tarih)) ? date('d.m.Y H:i', strtotime($row->tarih)) : '-',
                "tutar" => $tutarFormatted,
                "bakiye" => '<span class="' . $bakiyeColor . ' fw-bold">' . Helper::formattedMoney($bakiye) . '</span>',
                "aciklama" => htmlspecialchars($row->aciklama ?? $row->ACIKLAMA ?? '-', ENT_QUOTES, 'UTF-8') ?: '-',
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

// Tüm Hareketleri Getir (Global Mobil Bottom Sheet için)
if ($action == "tum-hareketler-getir") {
    $search = $_POST["search"] ?? "";
    $type = $_POST["type"] ?? "all"; // all | 1 (Gelir) | 2 (Gider)
    $baslangic = $_POST["baslangic"] ?? "";
    $bitis = $_POST["bitis"] ?? "";

    $where = "1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (g.hesap_adi LIKE :search OR g.kategori LIKE :search OR g.aciklama LIKE :search)";
        $params['search'] = "%$search%";
    }

    if ($type == '1') {
        $where .= " AND g.type = 1";
    } elseif ($type == '2') {
        $where .= " AND g.type = 2";
    }

    if (!empty($baslangic)) {
        $where .= " AND DATE(g.tarih) >= :baslangic";
        $params['baslangic'] = $baslangic;
    }
    if (!empty($bitis)) {
        $where .= " AND DATE(g.tarih) <= :bitis";
        $params['bitis'] = $bitis;
    }

    $sql = "SELECT g.*, 
            (SELECT SUM(CASE WHEN g2.type = 1 THEN CAST(g2.tutar AS DECIMAL(15,2)) ELSE -CAST(g2.tutar AS DECIMAL(15,2)) END) 
             FROM gelir_gider g2 
             WHERE (g2.tarih < g.tarih OR (g2.tarih = g.tarih AND g2.id <= g.id))) as global_yuruyen_bakiye
            FROM gelir_gider g
            WHERE $where
            ORDER BY g.tarih DESC, g.id DESC LIMIT 50";

    try {
        $db = $GelirGider->getDb();
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
                "kategori_adi" => $row->kategori,
                "hesap_adi" => $row->hesap_adi,
                "aciklama" => $row->aciklama,
                "tarih" => date('d.m.Y H:i', strtotime($row->tarih)),
                "amt" => (float)$row->tutar,
                "type" => (int)$row->type,
                "yuruyen" => (float)($row->global_yuruyen_bakiye ?? 0)
            ];
        }
        echo json_encode($formatted);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Excel'den Toplu Gelir-Gider Yükle
if ($action == "gelir-gider-excel-kaydet") {
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Lütfen geçerli bir Excel dosyası seçin.']);
        exit;
    }

    $fileTmpPath = $_FILES['excelFile']['tmp_name'];
    $fileName = $_FILES['excelFile']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Yalnızca .xlsx ve .xls uzantılı dosyalar desteklenmektedir.']);
        exit;
    }

    $userId = (int)($_SESSION['id'] ?? ($_SESSION['user_id'] ?? 0));
    $result = $GelirGider->importFromExcel($fileTmpPath, $userId);

    if ($result['status'] === 'success') {
        try {
            $logModel = new \App\Model\SystemLogModel();
            $logModel->logAction(
                $userId,
                'Excel Yükleme',
                "Gelir-Gider modülüne Excel'den {$result['count']} adet kayıt içe aktarıldı ({$fileName}).",
                \App\Model\SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (\Throwable $logEx) {
            error_log("Gelir-Gider Excel Log Error: " . $logEx->getMessage());
        }
    }

    echo json_encode($result);
    exit;
}
