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

/**
 * PDF içe aktarma uçları için oturum ve yetki kontrolü. Oturumdaki kullanıcı ID'sini döndürür.
 */
$cariPdfYetkiKontrol = function (): int {
    if (empty($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        throw new Exception("Oturumunuz sonlanmış. Lütfen tekrar giriş yapın.");
    }

    $kullanici_id = (int) ($_SESSION["id"] ?? $_SESSION["user_id"] ?? ($_SESSION["user"]->id ?? 0));
    if ($kullanici_id <= 0) {
        throw new Exception("Oturumunuz sonlanmış. Lütfen tekrar giriş yapın.");
    }

    $User = new \App\Model\UserModel();
    if (!$User->hasUserPermission($kullanici_id, 'cari_hesap_hareketleri')) {
        throw new Exception("Bu işlem için yetkiniz bulunmuyor.");
    }

    return $kullanici_id;
};

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
                <div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">
                    <a href="index.php?p=cari/hesap-hareketleri&id=' . $enc_id . '" class="btn btn-subtle-primary table-action-btn hesap-hareketleri" data-id="' . $enc_id . '" title="Hesap Hareketleri">
                        <i class="bx bx-history font-size-14"></i>
                    </a>
                    <button type="button" class="btn btn-subtle-success table-action-btn hareket-ekle" data-id="' . $enc_id . '" title="Hareket Ekle">
                        <i class="bx bx-plus-circle font-size-14"></i>
                    </button>
                    <button type="button" class="btn btn-subtle-warning table-action-btn duzenle" data-id="' . $enc_id . '" title="Düzenle">
                        <i class="bx bx-edit-alt font-size-14"></i>
                    </button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn cari-sil" data-id="' . $enc_id . '" title="Sil">
                        <i class="bx bx-trash font-size-14"></i>
                    </button>
                </div>';

            $il_ilce = [];
            if (!empty($row->il)) $il_ilce[] = $row->il;
            if (!empty($row->ilce)) $il_ilce[] = $row->ilce;
            $il_ilce_str = !empty($il_ilce) ? implode(' / ', $il_ilce) : '-';

            $vknBadge = !empty($row->vkn_tckn) 
                ? '<span class="font-monospace fw-semibold text-dark">' . htmlspecialchars($row->vkn_tckn, ENT_QUOTES, 'UTF-8') . '</span>' 
                : '<span class="text-muted">-</span>';

            $formattedData[] = [
                "id" => '<span class="fw-semibold text-muted font-size-12">' . $row->id . '</span>',
                "CariAdi" => '<span class="fw-bold text-dark font-size-13" style="color: #0f172a;">' . htmlspecialchars($row->CariAdi, ENT_QUOTES, 'UTF-8') . '</span>',
                "firma" => $row->firma ? '<span class="fw-medium text-secondary font-size-12">' . htmlspecialchars($row->firma, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">-</span>',
                "vkn_tckn" => !empty($row->vkn_tckn) ? '<span class="fw-semibold text-dark font-size-12">' . htmlspecialchars($row->vkn_tckn, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">-</span>',
                "Telefon" => $row->Telefon ? '<span class="fw-medium text-dark font-size-12">' . htmlspecialchars($row->Telefon, ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">-</span>',
                "il_ilce" => '<span class="fw-medium text-secondary font-size-12">' . $il_ilce_str . '</span>',
                "bakiye" => '<span class="fw-bold font-size-13" style="' . ($bakiye < 0 ? 'color: #dc2626 !important;' : ($bakiye > 0 ? 'color: #16a34a !important;' : 'color: #0f172a !important;')) . '">' . Helper::formattedMoney(abs($bakiye)) . 
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
            "CariAdi" => trim($_POST["CariAdi"] ?? ""),
            "firma" => trim($_POST["firma"] ?? "") ?: null,
            "vkn_tckn" => trim($_POST["vkn_tckn"] ?? "") ?: null,
            "vergi_dairesi" => trim($_POST["vergi_dairesi"] ?? "") ?: null,
            "alici_turu" => in_array($_POST["alici_turu"] ?? '', ['KURUMSAL', 'BIREYSEL']) ? $_POST["alici_turu"] : 'KURUMSAL',
            "belge_turu" => in_array($_POST["belge_turu"] ?? '', ['OTOMATIK', 'EFATURA', 'EARSIV']) ? $_POST["belge_turu"] : 'OTOMATIK',
            "posta_kutusu" => trim($_POST["posta_kutusu"] ?? "") ?: null,
            "Telefon" => trim($_POST["Telefon"] ?? "") ?: null,
            "Email" => trim($_POST["Email"] ?? "") ?: null,
            "web_sitesi" => trim($_POST["web_sitesi"] ?? "") ?: null,
            "ticaret_sicil_no" => trim($_POST["ticaret_sicil_no"] ?? "") ?: null,
            "mersis_no" => trim($_POST["mersis_no"] ?? "") ?: null,
            "ulke" => trim($_POST["ulke"] ?? "") ?: 'Türkiye',
            "il" => trim($_POST["il"] ?? "") ?: null,
            "ilce" => trim($_POST["ilce"] ?? "") ?: null,
            "posta_kodu" => trim($_POST["posta_kodu"] ?? "") ?: null,
            "Adres" => trim($_POST["Adres"] ?? "") ?: null,
            "notlar" => trim($_POST["notlar"] ?? "") ?: null,
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

// VKN / TCKN Mükellef Sorgula (GİB / EDM)
if ($action == "vkn-sorgula") {
    $vkn = trim($_POST["vkn_tckn"] ?? "");
    if (empty($vkn)) {
        echo json_encode(["status" => "error", "message" => "Lütfen geçerli bir VKN veya TCKN girin."]);
        exit;
    }
    try {
        $firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 1);
        $invoiceService = new \App\Service\EInvoiceService();
        $result = $invoiceService->checkTaxpayer($firmId, $vkn);
        echo json_encode(["status" => "success", "data" => $result]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
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
                <div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">
                    <button type="button" class="btn btn-subtle-warning table-action-btn hareket-duzenle" data-id="' . $enc_hareket_id . '" title="Düzenle">
                        <i class="bx bx-edit-alt font-size-14"></i>
                    </button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn hareket-sil" data-id="' . $enc_hareket_id . '" title="Sil">
                        <i class="bx bx-trash font-size-14"></i>
                    </button>
                </div>';
            
            $belgeNoHtml = '<span class="text-muted">-</span>';
            if (!empty($row->belge_no)) {
                $belgeNoHtml = '<span class="fw-semibold text-secondary font-size-13">' . htmlspecialchars($row->belge_no, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($row->dosya) {
                $belgeNoHtml .= ' <a href="uploads/cari_belgeler/' . htmlspecialchars($row->dosya, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-subtle-primary p-0 rounded-1 ms-1" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;" title="Belgeyi Görüntüle"><i class="bx bx-paperclip font-size-13"></i></a>';
            }

            $formattedData[] = [
                "islem_tarihi" => '<span class="fw-semibold text-dark font-size-13">' . date('d.m.Y H:i', strtotime($row->islem_tarihi)) . '</span>',
                "belge_no" => $belgeNoHtml,
                "aciklama" => '<span class="fw-medium text-dark font-size-13" style="color: #0f172a;">' . htmlspecialchars($row->aciklama ?: '-', ENT_QUOTES, 'UTF-8') . '</span>',
                "dosya" => $row->dosya ?: null,
                "borc" => $row->borc > 0 ? '<span class="fw-bold font-size-13 text-success" style="color: #16a34a !important;">' . Helper::formattedMoney($row->borc) . '</span>' : '<span class="text-muted">-</span>',
                "alacak" => $row->alacak > 0 ? '<span class="fw-bold font-size-13 text-danger" style="color: #dc2626 !important;">' . Helper::formattedMoney($row->alacak) . '</span>' : '<span class="text-muted">-</span>',
                "yuruyen_bakiye" => '<span class="fw-bold font-size-13 ' . ($row->yuruyen_bakiye < 0 ? 'text-danger' : ($row->yuruyen_bakiye > 0 ? 'text-success' : 'text-dark')) . '" style="' . ($row->yuruyen_bakiye < 0 ? 'color: #dc2626 !important;' : ($row->yuruyen_bakiye > 0 ? 'color: #16a34a !important;' : 'color: #0f172a !important;')) . '">' . 
                                    Helper::formattedMoney(abs($row->yuruyen_bakiye)) . 
                                    ($row->yuruyen_bakiye < 0 ? ' (B)' : ($row->yuruyen_bakiye > 0 ? ' (A)' : '')) . '</span>',
                "actions" => $actions
            ];
        }
        
        $res['data'] = $formattedData;

        // Anlık Özet
        $stmtOzet = $Cari->getDb()->prepare("SELECT COUNT(*) as toplam_islem, SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmtOzet->execute(['cari_id' => $cari_id]);
        $ozet = $stmtOzet->fetch(PDO::FETCH_OBJ);
        
        $res['summary'] = [
            'toplam_islem' => (int)($ozet->toplam_islem ?? 0),
            'toplam_borc' => (float)($ozet->toplam_borc ?? 0),
            'toplam_alacak' => (float)($ozet->toplam_alacak ?? 0),
            'bakiye' => (float)($ozet->bakiye ?? 0)
        ];

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

// PDF Ekstre Analizi (Önizleme - kayıt yapmaz)
if ($action == "hareket-pdf-analiz") {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cariPdfYetkiKontrol();

        $cari_id = Security::decrypt($_POST["cari_id"] ?? "");
        if (!$cari_id) {
            throw new Exception("Cari bulunamadı.");
        }

        if (!isset($_FILES['pdf_dosya']) || $_FILES['pdf_dosya']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("PDF dosyası yüklenemedi veya seçilmedi.");
        }

        $ext = strtolower(pathinfo($_FILES['pdf_dosya']['name'], PATHINFO_EXTENSION));
        $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['pdf_dosya']['tmp_name']) : 'application/pdf';
        if ($ext !== 'pdf' || strpos($mime, 'pdf') === false) {
            throw new Exception("Sadece PDF dosyası yükleyebilirsiniz.");
        }

        try {
            $parser = new \App\Service\CariPdfImportService();
            $sonuc = $parser->parseFile($_FILES['pdf_dosya']['tmp_name']);
        } catch (\Throwable $t) {
            error_log("Cari PDF okuma hatası: " . $t->getMessage());
            throw new Exception("PDF okunamadı. Dosya bozuk veya taranmış (resim) bir PDF olabilir.");
        }

        if (empty($sonuc['rows'])) {
            throw new Exception("PDF içinde hareket satırı bulunamadı. Dosya formatı desteklenmiyor olabilir.");
        }

        $imzalar = $CariHareket->getKayitImzalari($cari_id);
        $sayaclar = [];
        $mukerrerSayisi = 0;
        $satirlar = [];

        foreach ($sonuc['rows'] as $row) {
            // PDF içinde birebir aynı satır birden fazla kez geçebilir; adet bazlı karşılaştırılır.
            $imza = $CariHareket->imzaOlustur($row['tarih'], $row['borc'], $row['alacak'], $row['aciklama']);
            $sayaclar[$imza] = ($sayaclar[$imza] ?? 0) + 1;
            $mukerrer = $sayaclar[$imza] <= ($imzalar[$imza] ?? 0);
            if ($mukerrer) {
                $mukerrerSayisi++;
            }

            $satirlar[] = [
                "sira" => $row['sira'],
                "tarih" => $row['tarih'],
                "tarih_gosterim" => date('d.m.Y', strtotime($row['tarih'])),
                "aciklama" => $row['aciklama'],
                "borc" => number_format($row['borc'], 2, '.', ''),
                "alacak" => number_format($row['alacak'], 2, '.', ''),
                "borc_gosterim" => $row['borc'] > 0 ? Helper::formattedMoney($row['borc']) : '-',
                "alacak_gosterim" => $row['alacak'] > 0 ? Helper::formattedMoney($row['alacak']) : '-',
                "mukerrer" => $mukerrer
            ];
        }

        $uyarilar = [];
        if ($sonuc['beyan_verdim'] !== null && abs($sonuc['beyan_verdim'] - $sonuc['toplam_verdim']) >= 0.01) {
            $uyarilar[] = "PDF'te yazan Verdim toplamı (" . Helper::formattedMoney($sonuc['beyan_verdim']) .
                ") okunan satır toplamından (" . Helper::formattedMoney($sonuc['toplam_verdim']) . ") farklı.";
        }
        if ($sonuc['beyan_aldim'] !== null && abs($sonuc['beyan_aldim'] - $sonuc['toplam_aldim']) >= 0.01) {
            $uyarilar[] = "PDF'te yazan Aldım toplamı (" . Helper::formattedMoney($sonuc['beyan_aldim']) .
                ") okunan satır toplamından (" . Helper::formattedMoney($sonuc['toplam_aldim']) . ") farklı.";
        }
        if (!empty($sonuc['baslangic_bakiye'])) {
            $uyarilar[] = "PDF'te " . Helper::formattedMoney($sonuc['baslangic_bakiye']) .
                " tutarında başlangıç bakiyesi var; bu tutar hareket olarak aktarılmaz.";
        }
        if ($sonuc['atlanan'] > 0) {
            $uyarilar[] = $sonuc['atlanan'] . " satır okunamadığı için listeye alınmadı.";
        }

        echo json_encode([
            "status" => "success",
            "cari_adi" => $sonuc['cari_adi'],
            "rows" => $satirlar,
            "toplam_verdim" => Helper::formattedMoney($sonuc['toplam_verdim']),
            "toplam_aldim" => Helper::formattedMoney($sonuc['toplam_aldim']),
            "mukerrer_sayisi" => $mukerrerSayisi,
            "uyarilar" => $uyarilar
        ]);
    } catch (Exception $e) {
        error_log("Cari PDF analiz hatası: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// PDF Ekstre İçe Aktarma (Seçilen satırları kaydeder)
if ($action == "hareket-pdf-kaydet") {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $kullanici_id = $cariPdfYetkiKontrol();

        $cari_id = Security::decrypt($_POST["cari_id"] ?? "");
        if (!$cari_id) {
            throw new Exception("Cari bulunamadı.");
        }

        $gelen = json_decode($_POST["rows"] ?? "", true);
        if (!is_array($gelen) || empty($gelen)) {
            throw new Exception("Aktarılacak satır seçilmedi.");
        }

        $belge_no = trim($_POST["belge_no"] ?? "");
        $mukerrerAtla = ($_POST["mukerrer_atla"] ?? "1") == "1";
        $imzalar = $CariHareket->getKayitImzalari($cari_id);
        $sayaclar = [];

        $kayitlar = [];
        $atlanan = 0;
        foreach ($gelen as $row) {
            $tarih = trim($row['tarih'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) {
                continue;
            }

            $borc = round((float) ($row['borc'] ?? 0), 2);
            $alacak = round((float) ($row['alacak'] ?? 0), 2);
            if ($borc <= 0 && $alacak <= 0) {
                continue;
            }

            $aciklama = mb_substr(trim((string) ($row['aciklama'] ?? '')), 0, 500);
            $imza = $CariHareket->imzaOlustur($tarih, $borc, $alacak, $aciklama);
            $sayaclar[$imza] = ($sayaclar[$imza] ?? 0) + 1;

            if ($mukerrerAtla && $sayaclar[$imza] <= ($imzalar[$imza] ?? 0)) {
                $atlanan++;
                continue;
            }

            $kayitlar[] = [
                "tarih" => $tarih,
                "aciklama" => $aciklama,
                "borc" => $borc,
                "alacak" => $alacak,
                "belge_no" => $belge_no !== '' ? mb_substr($belge_no, 0, 50) : null
            ];
        }

        if (empty($kayitlar)) {
            throw new Exception("Kaydedilecek yeni hareket bulunamadı.");
        }

        try {
            $eklenen = $CariHareket->topluEkle($cari_id, $kayitlar);
        } catch (\Throwable $t) {
            error_log("Cari PDF toplu kayıt hatası: " . $t->getMessage());
            throw new Exception("Kayıt sırasında hata oluştu, hiçbir hareket eklenmedi.");
        }

        $cariBilgi = $Cari->find($cari_id);
        (new \App\Model\SystemLogModel())->logAction(
            $kullanici_id,
            'Cari PDF İçe Aktarma',
            "'" . ($cariBilgi->CariAdi ?? $cari_id) . "' carisine PDF ekstresinden $eklenen hareket aktarıldı. ($atlanan mükerrer satır atlandı)",
            \App\Model\SystemLogModel::LEVEL_IMPORTANT
        );

        $stmt = $Cari->getDb()->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $cari_id]);
        $ozet = $stmt->fetch(PDO::FETCH_OBJ);

        echo json_encode([
            "status" => "success",
            "message" => "$eklenen hareket aktarıldı." . ($atlanan > 0 ? " $atlanan mükerrer satır atlandı." : ""),
            "eklenen" => $eklenen,
            "atlanan" => $atlanan,
            "new_bakiye_raw" => $ozet->bakiye ?? 0,
            "new_bakiye" => Helper::formattedMoney(abs($ozet->bakiye ?? 0)),
            "new_borc" => Helper::formattedMoney($ozet->toplam_borc ?? 0),
            "new_alacak" => Helper::formattedMoney($ozet->toplam_alacak ?? 0)
        ]);
    } catch (Exception $e) {
        error_log("Cari PDF içe aktarma hatası: " . $e->getMessage());
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

