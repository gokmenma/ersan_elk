<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\DemirbasModel;
use App\Model\DemirbasServisModel;
use App\Model\DemirbasZimmetModel;
use App\Model\TanimlamalarModel;
use App\Model\DemirbasHareketModel;
use App\Model\SystemLogModel;
use App\Model\PersonelModel;
use App\Service\Gate;

$Demirbas = new DemirbasModel();
$Servis = new DemirbasServisModel();
$Zimmet = new DemirbasZimmetModel();
$Tanimlamalar = new TanimlamalarModel();
$Hareket = new DemirbasHareketModel();
$SystemLog = new SystemLogModel();
$Personel = new PersonelModel();


$action = $_POST["action"] ?? $_GET["action"] ?? null;

// JSON yanıt helper
function jsonResponse($status, $message, $data = null)
{
    $response = [
        "status" => $status,
        "message" => $message
    ];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}

function getCategoryIdsByKeywords($db, $firmaId, $keywords)
{
    $sql = $db->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND firma_id = ? AND silinme_tarihi IS NULL");
    $sql->execute([$firmaId]);
    $rows = $sql->fetchAll(PDO::FETCH_OBJ);

    $ids = [];
    foreach ($rows as $row) {
        $name = mb_strtolower((string) ($row->tur_adi ?? ''), 'UTF-8');
        foreach ($keywords as $kw) {
            if (str_contains($name, mb_strtolower($kw, 'UTF-8'))) {
                $ids[] = (int) $row->id;
                break;
            }
        }
    }

    return array_values(array_unique($ids));
}

function buildInClause($items)
{
    return implode(',', array_fill(0, count($items), '?'));
}

// ============== DEMİRBAŞ İŞLEMLERİ ==============

// Demirbaş Kaydet/Güncelle
if ($action == "demirbas-kaydet") {
    $id = Security::decrypt($_POST["demirbas_id"]);

    try {
        // Seri No Çakışma Kontrolü
        if (!empty($_POST["seri_no"])) {
            $duplicateId = $Demirbas->checkSeriNo($_POST["seri_no"], $id);
            if ($duplicateId) {
                jsonResponse("error", "Bu seri numarası (" . $_POST["seri_no"] . ") zaten başka bir demirbaş kaydında kullanılmaktadır.");
            }
        }

        $miktar = intval($_POST["miktar"] ?? 1);

        $data = [
            "id" => $id,
            "firma_id" => $_SESSION['firma_id'] ?? 0,
            "demirbas_no" => !empty($_POST["demirbas_no"]) ? $_POST["demirbas_no"] : 'S' . date('ymdHis') . rand(10, 99),
            "kategori_id" => !empty($_POST["kategori_id"]) ? $_POST["kategori_id"] : null,
            "demirbas_adi" => $_POST["demirbas_adi"],
            "marka" => $_POST["marka"] ?? null,
            "model" => $_POST["model"] ?? null,
            "seri_no" => $_POST["seri_no"] ?? null,
            "edinme_tarihi" => $_POST["edinme_tarihi"] ?? null,
            "edinme_tutari" => Helper::formattedMoneyToNumber($_POST["edinme_tutari"] ?? 0),
            "miktar" => $miktar,
            "minimun_stok_uyari_miktari" => intval($_POST["minimun_stok_uyari_miktari"] ?? 0),
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
            "lokasyon" => $_POST["lokasyon"] ?? 'bizim_depo',
            "otomatik_zimmet_is_emri_ids" => !empty($_POST["otomatik_zimmet_is_emri_ids"]) ? implode(',', $_POST["otomatik_zimmet_is_emri_ids"]) : null,
            "otomatik_iade_is_emri_ids" => !empty($_POST["otomatik_iade_is_emri_ids"]) ? implode(',', $_POST["otomatik_iade_is_emri_ids"]) : null,
            "otomatik_zimmetten_dus_is_emri_ids" => !empty($_POST["otomatik_zimmetten_dus_is_emri_ids"]) ? implode(',', $_POST["otomatik_zimmetten_dus_is_emri_ids"]) : null,
        ];

        // Yeni kayıtta kalan_miktar = miktar
        if ($id == 0) {
            $data["kalan_miktar"] = $miktar;
            $data["kayit_yapan"] = $_SESSION["id"] ?? null;
        } else {
            // Güncelleme: miktar değiştiğinde kalan_miktar'ı da güncelle
            $existing = $Demirbas->find($id);
            if ($existing) {
                $miktarFark = $miktar - ($existing->miktar ?? 1);
                $data["kalan_miktar"] = ($existing->kalan_miktar ?? 1) + $miktarFark;
                if ($data["kalan_miktar"] < 0) {
                    $data["kalan_miktar"] = 0;
                }
            }
        }

        $lastInsertId = $Demirbas->saveWithAttr($data) ?? $_POST["demirbas_id"];
        $son_kayit = $Demirbas->getTableRow(Security::decrypt($lastInsertId));

        jsonResponse("success", "Demirbaş başarıyla kaydedildi.", ["son_kayit" => $son_kayit]);
    } catch (PDOException $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Toplu Seri ile Demirbaş Kaydet
if ($action == "demirbas-toplu-kaydet") {
    try {
        $seriListesi = json_decode($_POST["seri_listesi"] ?? "[]", true);

        if (empty($seriListesi)) {
            jsonResponse("error", "Seri numarası listesi boş.");
        }

        if (count($seriListesi) > 500) {
            jsonResponse("error", "Tek seferde en fazla 500 adet seri girilebilir.");
        }

        $baseData = [
            "firma_id" => $_SESSION['firma_id'] ?? 0,
            "demirbas_no" => !empty($_POST["demirbas_no"]) ? $_POST["demirbas_no"] : 'S' . date('ymdHis') . rand(10, 99),
            "kategori_id" => !empty($_POST["kategori_id"]) ? $_POST["kategori_id"] : null,
            "demirbas_adi" => $_POST["demirbas_adi"],
            "marka" => $_POST["marka"] ?? null,
            "model" => $_POST["model"] ?? null,
            "edinme_tarihi" => $_POST["edinme_tarihi"] ?? null,
            "edinme_tutari" => Helper::formattedMoneyToNumber($_POST["edinme_tutari"] ?? 0),
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
            "lokasyon" => $_POST["lokasyon"] ?? 'bizim_depo',
            "otomatik_zimmet_is_emri_ids" => !empty($_POST["otomatik_zimmet_is_emri_ids"]) ? implode(',', $_POST["otomatik_zimmet_is_emri_ids"]) : null,
            "otomatik_iade_is_emri_ids" => !empty($_POST["otomatik_iade_is_emri_ids"]) ? implode(',', $_POST["otomatik_iade_is_emri_ids"]) : null,
            "otomatik_zimmetten_dus_is_emri_ids" => !empty($_POST["otomatik_zimmetten_dus_is_emri_ids"]) ? implode(',', $_POST["otomatik_zimmetten_dus_is_emri_ids"]) : null,
        ];

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $Demirbas->db->beginTransaction();

        foreach ($seriListesi as $seriNo) {
            try {
                $data = array_merge($baseData, [
                    "id" => 0,
                    "seri_no" => $seriNo,
                    "miktar" => 1,
                    "kalan_miktar" => 1,
                    "kayit_yapan" => $_SESSION["id"] ?? null,
                ]);

                $Demirbas->saveWithAttr($data);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Seri $seriNo: " . $e->getMessage();
            }
        }

        $Demirbas->db->commit();

        $message = "$successCount adet demirbaş başarıyla oluşturuldu.";
        if ($errorCount > 0) {
            $message .= " $errorCount adet hata oluştu.";
        }

        jsonResponse("success", $message, ["toplam" => $successCount, "hatalar" => $errors]);
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) {
            $Demirbas->db->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Kaskiye Teslim (Sayaçları Kaskiye teslim et - stoktan çıkış)
if ($action == "kasiye-teslim") {
    try {
        $demirbas_id_raw = $_POST["demirbas_id"] ?? '';
        $demirbas_id = $demirbas_id_raw ? intval(Security::decrypt($demirbas_id_raw)) : 0;
        $tarih = $_POST["tarih"] ?? date('d.m.Y');
        
        $teslim_eden_id = intval($_POST["teslim_eden"] ?? ($_SESSION['personel_id'] ?? 0));
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        if ($teslim_eden_id > 0) {
            $pName = $Demirbas->db->query("SELECT adi_soyadi FROM personel WHERE id = $teslim_eden_id")->fetchColumn();
            if ($pName) $teslim_eden = $pName;
        }
        
        $aciklama = $_POST["aciklama"] ?? null;

        if ($demirbas_id <= 0 || empty($tarih)) {
            jsonResponse("error", "Geçersiz parametreler. Lütfen formu eksiksiz doldurun.");
        }
        if ($teslim_eden_id <= 0) {
            jsonResponse("error", "Lütfen teslim eden personeli seçin.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');
        $demirbas = $Demirbas->find($demirbas_id);
        if (!$demirbas) {
            jsonResponse("error", "Sayaç kaydı bulunamadı.");
        }
        $teslimMiktari = (int)($demirbas->kalan_miktar ?? $demirbas->miktar ?? 0);
        if ($teslimMiktari <= 0) {
            $teslimMiktari = (int)($demirbas->miktar ?? 0);
        }
        if ($teslimMiktari <= 0) {
            $teslimMiktari = 1;
        }

        // Durumu güncelle, lokasyon değiştir, stoğu sıfırla
        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', lokasyon = 'kaski', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
        $sqlUpdate->execute([$formatted_tarih, $teslim_eden, $aciklama, $demirbas_id]);

        $sqlMov = $Demirbas->db->prepare("INSERT INTO demirbas_hareketler (demirbas_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak, personel_id) VALUES (?, 'sarf', ?, ?, ?, ?, 'sistem', ?)");
        $desc = "KASKİ'ye Teslim Edildi. Teslim Eden: $teslim_eden. " . ($aciklama ? "Not: $aciklama" : "");
        $sqlMov->execute([$demirbas_id, $teslimMiktari, $formatted_tarih, $desc, $_SESSION['id'] ?? 0, $teslim_eden_id]);

        jsonResponse("success", "Sayaç başarıyla Kaskiye teslim edildi. Durum güncellendi.");
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Toplu Kaskiye Teslim
if ($action == "toplu-kasiye-teslim") {
    try {
        $ids_json = $_POST['ids'] ?? '[]';
        $ids = json_decode($ids_json, true);
        $tarih = $_POST["tarih"] ?? date('d.m.Y');
        
        $teslim_eden_id = intval($_POST["teslim_eden"] ?? ($_SESSION['personel_id'] ?? 0));
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        if ($teslim_eden_id > 0) {
            $pName = $Demirbas->db->query("SELECT adi_soyadi FROM personel WHERE id = $teslim_eden_id")->fetchColumn();
            if ($pName) $teslim_eden = $pName;
        }
        
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($ids) || empty($tarih)) {
            jsonResponse("error", "Seçili sayaç bulunamadı veya tarih eksik.");
        }
        if ($teslim_eden_id <= 0) {
            jsonResponse("error", "Lütfen teslim eden personeli seçin.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');
        $successCount = 0;

        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', lokasyon = 'kaski', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
        $sqlMov = $Demirbas->db->prepare("INSERT INTO demirbas_hareketler (demirbas_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak, personel_id) VALUES (?, 'sarf', ?, ?, ?, ?, 'sistem', ?)");

        foreach ($ids as $id_raw) {
            $id = intval(Security::decrypt($id_raw));
            if ($id > 0) {
                $demirbas = $Demirbas->find($id);
                if (!$demirbas) {
                    continue;
                }
                $teslimMiktari = (int)($demirbas->kalan_miktar ?? $demirbas->miktar ?? 0);
                if ($teslimMiktari <= 0) {
                    $teslimMiktari = (int)($demirbas->miktar ?? 0);
                }
                if ($teslimMiktari <= 0) {
                    $teslimMiktari = 1;
                }

                $sqlUpdate->execute([$formatted_tarih, $teslim_eden, $aciklama, $id]);
                
                $desc = "Toplu KASKİ'ye Teslim. Teslim Eden: $teslim_eden. " . ($aciklama ? "Not: $aciklama" : "");
                $sqlMov->execute([$id, $teslimMiktari, $formatted_tarih, $desc, $_SESSION['id'] ?? 0, $teslim_eden_id]);

                $successCount++;
            }
        }

        jsonResponse("success", "Toplam $successCount adet sayaç Kaskiye teslim edildi.");
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Manuel Kaskiye Teslim (Adet girerek toplu teslim)
if ($action == "manual-kasiye-teslim" || $action == "manual-kaski-teslim") {
    try {
        $tarih = $_POST["tarih"] ?? date('d.m.Y');
        $adet = intval($_POST["adet"] ?? 0);
        $aciklama = $_POST["aciklama"] ?? null;
        
        $teslim_eden_id = intval($_POST["teslim_eden"] ?? ($_SESSION['personel_id'] ?? 0));
        $teslim_eden = 'Sistem Kullanıcısı';
        if ($teslim_eden_id > 0) {
            $stmtP = $Demirbas->db->prepare("SELECT adi_soyadi FROM personel WHERE id = ?");
            $stmtP->execute([$teslim_eden_id]);
            $pName = $stmtP->fetchColumn();
            if ($pName) $teslim_eden = $pName;
        }

        if ($adet <= 0 || empty($tarih)) {
            jsonResponse("error", "Geçersiz adet veya tarih.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');

        // Sayaç kategori ID'sini bul
        $sqlKat = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND (LOWER(tur_adi) LIKE '%sayaç%' OR LOWER(tur_adi) LIKE '%sayac%') AND firma_id = ? LIMIT 1");
        $sqlKat->execute([$_SESSION['firma_id']]);
        $sayacKatId = $sqlKat->fetchColumn();

        $Demirbas->db->beginTransaction();

        // 1. Manuel kayıt oluştur (Kaskiye teslim edilmiş olarak)
        $sqlInsert = $Demirbas->db->prepare("
            INSERT INTO demirbas 
            (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, lokasyon, aciklama, kayit_yapan, kaskiye_teslim_tarihi, kaskiye_teslim_eden)
            VALUES (?, ?, ?, ?, ?, 'Kaskiye Teslim Edildi', 'kaski', ?, ?, ?, ?)
        ");
        $sqlInsert->execute([
            $_SESSION['firma_id'],
            $sayacKatId ?: null,
            "Hurda Sayaç (Manuel KASKİ Teslim)",
            $adet, 
            0, // Kalan miktar 0 çünkü teslim edildi
            ($aciklama ?: "Manuel Kaskiye Teslim"),
            $_SESSION['id'] ?? null,
            $formatted_tarih,
            $teslim_eden
        ]);
        $yeniId = $Demirbas->db->lastInsertId();

        // 2. Hareket ekle (Personel_id teslim eden bilgisi olarak kalır ama hesaplamalarda d.lokasyon = 'kaski' kontrol edilerek dışlanacaktır)
        $sqlMov = $Demirbas->db->prepare("INSERT INTO demirbas_hareketler (demirbas_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak, personel_id) VALUES (?, 'sarf', ?, ?, ?, ?, 'sistem', ?)");
        $desc = "[KASKI_TESLIM] Toplu KASKİ'ye Teslim. Teslim Eden: $teslim_eden. " . ($aciklama ? "Not: $aciklama" : "");
        $sqlMov->execute([$yeniId, $adet, $formatted_tarih, $desc, $_SESSION['id'] ?? 0, $teslim_eden_id]);

        $Demirbas->db->commit();
        jsonResponse("success", "$adet adet sayaç Kaskiye teslim edildi.");

    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) $Demirbas->db->rollBack();
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Get Filtered IDs for Select All Logic
if ($action == "get-filtered-sayac-ids") {
    try {
        $tab = $_POST["tab"] ?? 'sayac_bizim_depo';
        $idsRaw = $Demirbas->getFilteredIds($_POST, $tab);
        $ids = array_map(fn($id) => Security::encrypt($id), $idsRaw);
        jsonResponse("success", "Başarılı", ["ids" => $ids]);
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Get Filtered IDs for Movements
if ($action == "get-filtered-hareket-ids") {
    try {
        $idsRaw = $Hareket->getFilteredIds($_POST);
        // Hareket tablosunda numeric ID kullanıldığı için şifrelemeye gerek yok veya frontend ile uyumlu olmalı
        // Ancak güvenlik için şifreli gönderelim, backend'de çözeriz.
        $ids = array_map(fn($id) => Security::encrypt($id), $idsRaw);
        jsonResponse("success", "Başarılı", ["ids" => $ids]);
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Search Actions handled below (personel-ara, demirbas-ara)

// Zimmet Ver
if ($action == "zimmet-ver") {
    try {
        $personel_id = intval($_POST['personel_id']);
        $teslim_miktar = intval($_POST['teslim_miktar'] ?? 1);
        $tarih = $_POST['teslim_tarihi'] ?? date('Y-m-d');
        $aciklama = $_POST['aciklama'] ?? '';
        
        if (empty($personel_id)) {
            jsonResponse("error", "Personel seçimi zorunludur.");
        }

        $isTopluSecim = !empty($_POST['is_toplu_secim']) && $_POST['is_toplu_secim'] == "1";
        $secilenIds = [];
        if ($isTopluSecim && !empty($_POST['secilen_ids'])) {
            $secilenIds = json_decode($_POST['secilen_ids'], true) ?: [];
        }

        $Demirbas->db->beginTransaction();
        $successCount = 0;

        if ($isTopluSecim && count($secilenIds) > 0) {
            foreach ($secilenIds as $enc_id) {
                $id = intval(Security::decrypt($enc_id));
                if ($id > 0) {
                    $Zimmet->saveWithAttr([
                        "id" => 0,
                        "demirbas_id" => $id,
                        "personel_id" => $personel_id,
                        "teslim_miktar" => 1,
                        "teslim_tarihi" => $tarih,
                        "aciklama" => $aciklama,
                        "durum" => 'teslim'
                    ]);
                    $Hareket->saveWithAttr([
                        "id" => 0,
                        "zimmet_id" => $Zimmet->db->lastInsertId(),
                        "demirbas_id" => $id,
                        "personel_id" => $personel_id,
                        "hareket_tipi" => 'zimmet',
                        "miktar" => 1,
                        "aciklama" => $aciklama,
                        "islem_yapan_id" => $_SESSION['id'] ?? 0,
                        "tarih" => $tarih
                    ]);
                    $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Personelde' WHERE id = ?")->execute([$id]);
                    $successCount++;
                }
            }
        } elseif (!empty($_POST['demirbas_id'])) {
            // Tekli
            $id = intval($_POST['demirbas_id']);
            if ($id > 0) {
                $Zimmet->saveWithAttr([
                    "id" => 0,
                    "demirbas_id" => $id,
                    "personel_id" => $personel_id,
                    "teslim_miktar" => $teslim_miktar,
                    "teslim_tarihi" => $tarih,
                    "aciklama" => $aciklama,
                    "durum" => 'teslim'
                ]);
                $Hareket->saveWithAttr([
                    "id" => 0,
                    "zimmet_id" => $Zimmet->db->lastInsertId(),
                    "demirbas_id" => $id,
                    "personel_id" => $personel_id,
                    "hareket_tipi" => 'zimmet',
                    "miktar" => $teslim_miktar,
                    "aciklama" => $aciklama,
                    "islem_yapan_id" => $_SESSION['id'] ?? 0,
                    "tarih" => $tarih
                ]);
                $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Personelde' WHERE id = ?")->execute([$id]);
                $successCount++;
            }
        }

        $Demirbas->db->commit();
        jsonResponse("success", "Toplam $successCount adet sayaç personele zimmetlendi.");
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) $Demirbas->db->rollBack();
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Kaski Depodan Bizim Depoya Çek
if ($action == "kaskiden-depoya-cek") {
    try {
        $ids_json = $_POST['ids'] ?? '[]';
        $ids = json_decode($ids_json, true);

        if (empty($ids)) {
            jsonResponse("error", "Lütfen çekilmek istenilen sayaçları seçin.");
        }

        $successCount = 0;
        foreach ($ids as $id_raw) {
            $id = intval(Security::decrypt($id_raw));
            if ($id > 0) {
                $Demirbas->db->prepare("UPDATE demirbas SET lokasyon = 'bizim_depo' WHERE id = ?")->execute([$id]);
                $successCount++;
            }
        }

        jsonResponse("success", "$successCount adet sayaç başarıyla Bizim Depo'ya çekildi.");
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Sayaç Montaj Yap (Zimmetten düş ve hurda oluştur)
if ($action == "sayac-montaj-yap") {
    try {
        $zimmet_id = intval(Security::decrypt($_POST["zimmet_id"]));
        $miktar = intval($_POST["miktar"] ?? 1);
        $aciklama = $_POST["aciklama"] ?? 'Montaj yapıldı, hurda girişi sağlandı.';
        $tarih = $_POST["tarih"] ?? date('Y-m-d');
        
        $zimmet = $Zimmet->find($zimmet_id);
        if (!$zimmet) {
            jsonResponse("error", "Zimmet kaydı bulunamadı.");
        }

        $Demirbas->db->beginTransaction();
        
        // 1. Zimmetten sarf et (Montaj Geçmişi Oluştur)
        $Hareket->saveWithAttr([
            "id" => 0,
            "zimmet_id" => $zimmet_id,
            "demirbas_id" => $zimmet->demirbas_id,
            "personel_id" => $zimmet->personel_id,
            "hareket_tipi" => 'sarf',
            "miktar" => $miktar,
            "aciklama" => $aciklama,
            "islem_yapan_id" => $_SESSION["id"] ?? null,
            "tarih" => $tarih
        ]);

        $Demirbas->db->commit();
        jsonResponse("success", "Montaj işlemi tamamlandı, hurda takibe alındı.");
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) $Demirbas->db->rollBack();
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Sayaç Depo Hareketleri (Hareketler Sekmesi İçin)
if ($action == "sayac-depo-hareketleri") {
    try {
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $search = $_POST['search']['value'] ?? '';
        $status_filter = $_POST['status_filter'] ?? '';

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $params = [$firmaId];
        $whereSql = " WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL";

        // Sadece Sayaç kategorisindeki hareketleri göster
        $sayacCatIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (!empty($sayacCatIds)) {
            $in = buildInClause($sayacCatIds);
            $whereSql .= " AND d.kategori_id IN ($in)";
            foreach ($sayacCatIds as $catId) {
                $params[] = $catId;
            }
        }

        if (!empty($search)) {
            $whereSql .= " AND (d.demirbas_adi LIKE ? OR d.seri_no LIKE ? OR p.adi_soyadi LIKE ? OR d.lokasyon LIKE ? OR h.id LIKE ? OR h.hareket_tipi LIKE ? OR h.aciklama LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Bireysel Sütun Aramaları
        if (isset($_POST['columns']) && is_array($_POST['columns'])) {
            $columnMapping = [
                1 => 'h.id',
                2 => 'h.hareket_tipi',
                3 => 'd.demirbas_adi',
                4 => 'd.seri_no',
                5 => 'h.miktar',
                6 => 'p.adi_soyadi',
                7 => 'h.aciklama',
                8 => 'h.tarih'
            ];
            foreach ($_POST['columns'] as $idx => $col) {
                $searchVal = trim((string)($col['search']['value'] ?? ''));
                if ($searchVal !== '' && isset($columnMapping[$idx])) {
                    $field = $columnMapping[$idx];
                    $mode = 'contains';
                    $val = $searchVal;

                    if (strpos($searchVal, ':') !== false) {
                        list($mode, $val) = explode(':', $searchVal, 2);
                    }

                    if ($val === '' && !in_array($mode, ['null', 'not_null'])) continue;

                    $op = "LIKE";
                    $placeholder = "?";
                    $dbVal = "%$val%";

                    switch ($mode) {
                        case 'equals': $op = "="; $dbVal = $val; break;
                        case 'not_equals': $op = "!="; $dbVal = $val; break;
                        case 'starts_with': $dbVal = "$val%"; break;
                        case 'ends_with': $dbVal = "%$val"; break;
                        case 'greater_than': $op = ">"; $dbVal = $val; break;
                        case 'less_than': $op = "<"; $dbVal = $val; break;
                        case 'null': $whereSql .= " AND ($field IS NULL OR $field = '')"; continue 2;
                        case 'not_null': $whereSql .= " AND ($field IS NOT NULL AND $field != '')"; continue 2;
                    }

                    if ($idx == 3) { // Sayaç
                        $whereSql .= " AND (d.demirbas_adi $op ? OR d.marka $op ? OR d.model $op ?)";
                        $params[] = $dbVal; $params[] = $dbVal; $params[] = $dbVal;
                    } elseif ($idx == 4) { // Seri / Abone No
                        $whereSql .= " AND (d.seri_no $op ? OR d.demirbas_adi $op ?)";
                        $params[] = $dbVal; $params[] = $dbVal;
                    } elseif ($idx == 6) { // Lokasyon / Personel
                        $whereSql .= " AND (p.adi_soyadi $op ? OR d.lokasyon $op ?)";
                        $params[] = $dbVal; $params[] = $dbVal;
                    } else {
                        $whereSql .= " AND $field $op ?";
                        $params[] = $dbVal;
                    }
                }
            }
        }

        if ($status_filter === 'kaski') {
            $whereSql .= " AND (h.aciklama LIKE '%KASKİ%' OR h.aciklama LIKE '%KASKI%' OR d.lokasyon = 'kaski' OR d.durum = 'kaskiye teslim edildi')";
        } elseif ($status_filter === 'depo') {
            // Depo İşlemleri: Personel atanmamış, depoya iade edilmiş veya açıklaması depo içeren kayıtlar
            $whereSql .= " AND (h.personel_id IS NULL OR h.hareket_tipi = 'iade' OR (h.aciklama LIKE '%DEPO%'))";
        } elseif ($status_filter === 'zimmet') {
            // Personel İşlemleri: Personel atanmış ve zimmet/sarf tipi kayıtlar
            $whereSql .= " AND h.personel_id IS NOT NULL AND h.hareket_tipi IN ('zimmet', 'sarf')";
        }

        $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 6);
        $orderDirection = $_POST['order'][0]['dir'] ?? 'desc';
        
        $sortableColumns = [
            1 => 'h.id',
            2 => 'h.hareket_tipi',
            3 => 'd.demirbas_adi',
            4 => 'd.seri_no',
            5 => 'h.miktar',
            6 => 'p.adi_soyadi',
            7 => 'h.aciklama',
            8 => 'h.tarih'
        ];
        
        $orderBySql = $sortableColumns[$orderColumnIndex] ?? 'h.tarih';
        $orderBySql .= " " . ($orderDirection === 'asc' ? 'ASC' : 'DESC');

        $sql = "SELECT h.*, d.demirbas_adi, d.seri_no, p.adi_soyadi as personel_adi, d.lokasyon
                FROM demirbas_hareketler h
                LEFT JOIN demirbas d ON h.demirbas_id = d.id
                LEFT JOIN personel p ON h.personel_id = p.id
                $whereSql
                ORDER BY $orderBySql
                LIMIT $start, $length";

        $stmt = $Demirbas->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($rows as $row) {
            $displaySeriNo = $row->seri_no;
            $displayDemirbasAdi = $row->demirbas_adi;
            
            // Abone No bilgisini ayıkla
            if (preg_match('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', $displayDemirbasAdi, $matches)) {
                $aboneNo = $matches[1];
                if (empty($displaySeriNo) || $displaySeriNo == '-') {
                    $displaySeriNo = $aboneNo;
                }
                $displayDemirbasAdi = preg_replace('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', '', $displayDemirbasAdi);
            }

            $data[] = [
                "checkbox" => '<div class="form-check d-flex justify-content-center m-0"><input class="form-check-input hareket-select" type="checkbox" value="' . $row->id . '"></div>',
                "id" => $row->id,
                "hareket_tipi" => DemirbasHareketModel::getHareketTipiBadge($row->hareket_tipi, $row->aciklama),
                "demirbas_adi" => $displayDemirbasAdi,
                "seri_no" => $displaySeriNo ? '<code class="text-dark bg-light px-1 rounded">'.$displaySeriNo.'</code>' : '-',
                "miktar" => '<span class="fw-bold text-primary">' . (int)$row->miktar . '</span>',
                "lokasyon_personel" => $row->personel_adi ?: ($row->lokasyon === 'kaski' ? 'Kaski Depo' : 'Bizim Depo'),
                "aciklama" => '<small class="text-muted">' . htmlspecialchars($row->aciklama ?? '') . '</small>',
                "tarih" => date('d.m.Y H:i', strtotime($row->tarih)),
                "islem" => '<button type="button" class="btn btn-soft-danger btn-sm hareket-sil-btn" data-id="' . $row->id . '" title="Sil"><i class="bx bx-trash"></i></button>',
            ];
        }

        // Filtrelenmiş kayıt sayısı
        $totalSql = "SELECT COUNT(h.id) FROM demirbas_hareketler h LEFT JOIN demirbas d ON h.demirbas_id = d.id LEFT JOIN personel p ON h.personel_id = p.id $whereSql";
        $stmtTotal = $Demirbas->db->prepare($totalSql);
        $stmtTotal->execute($params);
        $recordsFiltered = (int)$stmtTotal->fetchColumn();

        // Toplam kayıt sayısı (Filtresiz)
        $totalAllSql = "SELECT COUNT(h.id) FROM demirbas_hareketler h LEFT JOIN demirbas d ON h.demirbas_id = d.id WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL";
        $stmtTotalAll = $Demirbas->db->prepare($totalAllSql);
        $stmtTotalAll->execute([$firmaId]);
        $recordsTotal = (int)$stmtTotalAll->fetchColumn();

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);
        exit;
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Sayaç Depo Hareketleri - Gruplanmış (Tarih Bazlı)
if ($action == "sayac-depo-hareketleri-grouped") {
    try {
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $status_filter = $_POST['status_filter'] ?? '';

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $params = [$firmaId];
        $whereSql = " WHERE h.silinme_tarihi IS NULL AND d.firma_id = ?";

        // Sadece Sayaç kategorisindeki hareketleri göster
        $sayacCatIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (!empty($sayacCatIds)) {
            $in = buildInClause($sayacCatIds);
            $whereSql .= " AND d.kategori_id IN ($in)";
            foreach ($sayacCatIds as $catId) {
                $params[] = $catId;
            }
        }

        if ($status_filter === 'kaski') {
            $whereSql .= " AND (h.aciklama LIKE '%KASKİ%' OR h.aciklama LIKE '%KASKI%' OR d.lokasyon = 'kaski' OR d.durum = 'kaskiye teslim edildi')";
        } elseif ($status_filter === 'depo') {
            $whereSql .= " AND (h.personel_id IS NULL OR h.hareket_tipi = 'iade' OR (h.aciklama LIKE '%DEPO%'))";
        } elseif ($status_filter === 'zimmet') {
            $whereSql .= " AND h.personel_id IS NOT NULL AND h.hareket_tipi IN ('zimmet', 'sarf')";
        }

        // Global Search (Grouped mode)
        $search = $_POST['search']['value'] ?? '';
        if (!empty($search)) {
            $whereSql .= " AND (p.adi_soyadi LIKE ? OR d.demirbas_adi LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Bireysel Sütun Aramaları (Grouped mode)
        if (isset($_POST['columns']) && is_array($_POST['columns'])) {
            $columnMapping = [
                2 => 'p.adi_soyadi',
                3 => 'DATE(h.tarih)',
                7 => 'DATE(h.tarih)'
            ];
            foreach ($_POST['columns'] as $idx => $col) {
                $searchVal = $col['search']['value'] ?? '';
                if (!empty($searchVal) && isset($columnMapping[$idx])) {
                    $colName = $columnMapping[$idx];
                    $whereSql .= " AND $colName LIKE ?";
                    $params[] = "%$searchVal%";
                }
            }
        }

        $orderColumnIndex = intval($_POST['order'][0]['column'] ?? 6);
        $orderDirection = $_POST['order'][0]['dir'] ?? 'desc';
        
        $sortableColumns = [
            2 => 'personel_adi',
            3 => 'gun',
            6 => 'gun'
        ];
        
        $orderBySql = $sortableColumns[$orderColumnIndex] ?? 'gun';
        $orderBySql .= " " . ($orderDirection === 'asc' ? 'ASC' : 'DESC');

        $sql = "SELECT p.adi_soyadi as personel_adi, h.personel_id, DATE(h.tarih) as gun, COUNT(*) as islem_sayisi, SUM(h.miktar) as toplam_miktar
                FROM demirbas_hareketler h
                INNER JOIN demirbas d ON h.demirbas_id = d.id
                LEFT JOIN personel p ON h.personel_id = p.id
                $whereSql
                GROUP BY h.personel_id, gun
                ORDER BY $orderBySql, personel_adi ASC
                LIMIT $start, $length";

        $stmt = $Demirbas->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($rows as $row) {
            $pAdi = $row->personel_adi ?: 'Genel Depo';
            $data[] = [
                "checkbox" => "",
                "id" => '-',
                "hareket_tipi" => '<span class="fw-bold text-primary"><i class="bx bx-user me-1"></i> ' . $pAdi . '</span>',
                "demirbas_adi" => '<span class="fw-bold">' . date('d.m.Y', strtotime($row->gun)) . '</span>',
                "seri_no" => '-',
                "miktar" => '<span class="fw-bold text-primary">' . (int)$row->toplam_miktar . '</span>',
                "lokasyon_personel" => '<span class="badge bg-soft-info text-info">' . $row->islem_sayisi . ' İşlem</span>',
                "tarih" => $row->gun,
                "islem" => '<button class="btn btn-sm btn-soft-primary view-details-group"><i class="bx bx-chevron-down"></i> Detaylar</button>',
                "DT_RowClass" => "group-row",
                "gun" => $row->gun,
                "personel_id" => $row->personel_id
            ];
        }

        $totalSql = "SELECT COUNT(*) FROM (SELECT h.id FROM demirbas_hareketler h INNER JOIN demirbas d ON h.demirbas_id = d.id $whereSql GROUP BY h.personel_id, DATE(h.tarih)) as sub";
        $stmtTotal = $Demirbas->db->prepare($totalSql);
        $stmtTotal->execute($params);
        $recordsFiltered = $stmtTotal->fetchColumn();

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => (int)$recordsFiltered,
            "recordsFiltered" => (int)$recordsFiltered,
            "data" => $data
        ]);
        exit;
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Gruplanmış Hareket Detayı
if ($action == "sayac-depo-hareketleri-detay") {
    try {
        $gun = $_POST['gun'] ?? '';
        $personel_id = $_POST['personel_id'] ?? null;
        $status_filter = $_POST['status_filter'] ?? '';

        $params = [$_SESSION['firma_id'] ?? 0, $gun];
        $whereSql = " WHERE h.silinme_tarihi IS NULL AND d.firma_id = ? AND DATE(h.tarih) = ?";

        if ($personel_id === "null" || is_null($personel_id) || $personel_id === "") {
            $whereSql .= " AND h.personel_id IS NULL";
        } else {
            $whereSql .= " AND h.personel_id = ?";
            $params[] = $personel_id;
        }

        if ($status_filter === 'kaski') {
            $whereSql .= " AND d.lokasyon = 'kaski'";
        } elseif ($status_filter === 'depo') {
            $whereSql .= " AND d.lokasyon = 'bizim_depo'";
        } elseif ($status_filter === 'zimmet') {
            $whereSql .= " AND h.hareket_tipi = 'zimmet'";
        }

        $sql = "SELECT h.*, d.demirbas_adi, d.seri_no, p.adi_soyadi as personel_adi, d.lokasyon
                FROM demirbas_hareketler h
                INNER JOIN demirbas d ON h.demirbas_id = d.id
                LEFT JOIN personel p ON h.personel_id = p.id
                $whereSql
                ORDER BY h.tarih DESC, h.id DESC";

        $stmt = $Demirbas->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $html = '<div class="p-2 bg-light border-top border-bottom"><table class="table table-sm table-hover mb-0 bg-white shadow-sm rounded">';
        $html .= '<thead class="table-dark"><tr>
                 <th class="text-center" style="width:30px"><i class="bx bx-check-square"></i></th>
                 <th style="width:60px">ID</th>
                 <th style="width:120px">İşlem</th>
                 <th>Sayaç / Demirbaş</th>
                 <th>Seri / Abone</th>
                 <th class="text-center" style="width:60px">Adet</th>
                 <th>Sorumlu / Yer</th>
                 <th>Açıklama</th>
                 <th class="text-center" style="width:60px">Saat</th>
                 <th class="text-center" style="width:80px">İşlem</th>
                 </tr></thead><tbody>';

        foreach ($rows as $row) {
            $tipBadge = DemirbasHareketModel::getHareketTipiBadge($row->hareket_tipi, $row->aciklama);
            $lokP = $row->personel_adi ?: ($row->lokasyon === 'kaski' ? 'Kaski Depo' : 'Bizim Depo');
            
            $displaySeriNo = $row->seri_no;
            $displayDemirbasAdi = $row->demirbas_adi;
            if (preg_match('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', $displayDemirbasAdi, $matches)) {
                $aboneNo = $matches[1];
                if (empty($displaySeriNo) || $displaySeriNo == '-') $displaySeriNo = $aboneNo;
                $displayDemirbasAdi = preg_replace('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', '', $displayDemirbasAdi);
            }

            $html .= '<tr>';
            $html .= '<td class="text-center"><div class="form-check d-flex justify-content-center m-0"><input class="form-check-input hareket-select" type="checkbox" value="' . $row->id . '"></div></td>';
            $html .= '<td>'.$row->id.'</td>';
            $html .= '<td>'.$tipBadge.'</td>';
            $html .= '<td>'.htmlspecialchars($displayDemirbasAdi).'</td>';
            $html .= '<td>'.($displaySeriNo ? '<code class="text-dark bg-light px-1">'.$displaySeriNo.'</code>' : '-').'</td>';
            $html .= '<td class="text-center fw-bold text-primary">'.(int)$row->miktar.'</td>';
            $html .= '<td>'.$lokP.'</td>';
            $html .= '<td><small class="text-muted">'.htmlspecialchars($row->aciklama ?? '').'</small></td>';
            $html .= '<td class="text-center">'.date('H:i', strtotime($row->tarih)).'</td>';
            $html .= '<td class="text-center">
                        <button class="btn btn-sm btn-soft-danger hareket-sil-btn" data-id="'.$row->id.'" title="Sil"><i class="bx bx-trash"></i></button>
                      </td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        jsonResponse("success", "Başarılı", ["html" => $html]);
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Demirbaş bilgilerini getir
if ($action == "demirbas-getir") {
    $id = Security::decrypt($_POST["demirbas_id"]);

    try {
        $data = $Demirbas->find($id);
        if ($data) {
            // Eski text alanlarını yeni ID alanlarına otomatik eşle (geriye uyumluluk)
            if (empty($data->otomatik_zimmet_is_emri_ids) && !empty($data->otomatik_zimmet_is_emri)) {
                $matchSql = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE TRIM(is_emri_sonucu) = ? AND grup = 'is_turu' AND firma_id = ? AND silinme_tarihi IS NULL LIMIT 1");
                $matchSql->execute([trim($data->otomatik_zimmet_is_emri), $_SESSION['firma_id']]);
                $matchId = $matchSql->fetchColumn();
                if ($matchId) {
                    $data->otomatik_zimmet_is_emri_ids = (string) $matchId;
                    $Demirbas->db->prepare("UPDATE demirbas SET otomatik_zimmet_is_emri_ids = ? WHERE id = ?")->execute([$matchId, $id]);
                }
            }
            if (empty($data->otomatik_iade_is_emri_ids) && !empty($data->otomatik_iade_is_emri)) {
                $matchSql = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE TRIM(is_emri_sonucu) = ? AND grup = 'is_turu' AND firma_id = ? AND silinme_tarihi IS NULL LIMIT 1");
                $matchSql->execute([trim($data->otomatik_iade_is_emri), $_SESSION['firma_id']]);
                $matchId = $matchSql->fetchColumn();
                if ($matchId) {
                    $data->otomatik_iade_is_emri_ids = (string) $matchId;
                    $Demirbas->db->prepare("UPDATE demirbas SET otomatik_iade_is_emri_ids = ? WHERE id = ?")->execute([$matchId, $id]);
                }
            }
            jsonResponse("success", "Başarılı", ["data" => $data]);
        } else {
            jsonResponse("error", "Demirbaş bulunamadı.");
        }
    } catch (PDOException $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Toplu Demirbaş Sil
if ($action == "bulk-demirbas-sil") {
    Gate::can('demirbas_toplu_islem_sil');
    $ids = $_POST["ids"] ?? [];
    
    // JSON dizesi olarak gelmiş olabilir (max_input_vars aşımı için stringified gönderiyoruz)
    if (!empty($ids) && is_string($ids)) {
        $ids = json_decode($ids, true) ?: [];
    }
    
    $allFiltered = intval($_POST["all_filtered"] ?? 0);

    if ($allFiltered === 1) {
        $tab = $_POST["tab"] ?? 'demirbas';
        $idsRaw = $Demirbas->getFilteredIds($_POST, $tab);
        // Delete metodu encrypted id beklediği için encrypt edelim
        $ids = array_map(fn($id) => Security::encrypt($id), $idsRaw);
    }

    if (empty($ids)) {
        jsonResponse("error", "Lütfen silmek için en az bir kayıt seçin.");
    }

    // Büyük veriler için zaman limitini kaldır (11.000+ kayıt için)
    set_time_limit(0);

    try {
        $successCount = 0;
        $errorCount = 0;
        $hatalar = [];

        $Demirbas->db->beginTransaction();

        // Silinmeden önce detaylarını topla (günlük kaydı için)
        $details = [];
        $idsRawForDetails = array_map(fn($enc) => Security::decrypt($enc), $ids);
        if (!empty($idsRawForDetails)) {
            $placeholders = implode(',', array_fill(0, count($idsRawForDetails), '?'));
            $sqlDetails = $Demirbas->db->prepare("SELECT demirbas_adi, seri_no FROM demirbas WHERE id IN ($placeholders)");
            $sqlDetails->execute($idsRawForDetails);
            $details = $sqlDetails->fetchAll(PDO::FETCH_OBJ);
        }

        foreach ($ids as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id)
                continue;

            $zimmetler = $Zimmet->getByDemirbas($id);
            if (count($zimmetler) > 0) {
                $errorCount++;
                continue;
            }

            if ($Demirbas->delete($enc_id)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $Demirbas->db->commit();

        if ($successCount > 0) {
            // Detaylı log mesajı oluştur
            $logItems = [];
            foreach ($details as $idx => $d) {
                if ($idx < 100) { // İlk 100 kaydı detaylı yaz
                    $logItems[] = $d->demirbas_adi . ($d->seri_no ? " (SN: " . $d->seri_no . ")" : "");
                } else {
                    $moreCount = count($details) - 100;
                    $logItems[] = "... ve $moreCount adet daha";
                    break;
                }
            }
            $detailStr = implode(", ", $logItems);

            $logMsg = "[$successCount] adet sayaç/demirbaş toplu silme işlemi ile sistemden silindi. Silinenler: $detailStr";
            if ($errorCount > 0) {
                $logMsg .= " | ($errorCount kayıt zimmet geçmişi sebebiyle silinemedi.)";
            }
            $SystemLog->logAction($_SESSION['id'], "Toplu Silme", $logMsg, SystemLogModel::LEVEL_CRITICAL);

            $msg = "$successCount kayıt başarıyla silindi.";
            if ($errorCount > 0) {
                $msg .= " ($errorCount kayıt zimmet geçmişi olduğu için silinemedi!)";
            }
            jsonResponse("success", $msg);
        } else {
            jsonResponse("error", "Seçilen kayıtlar zimmet geçmişi olduğu için silinemedi.");
        }
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) {
            $Demirbas->db->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Toplu Hareket Sil
if ($action == "bulk-hareket-sil") {
    Gate::can('demirbas_toplu_islem_sil');
    $allFiltered = intval($_POST["all_filtered"] ?? 0);
    $ids = [];

    if ($allFiltered === 1) {
        // Pagination parametrelerini temizleyelim ki model tümünü çeksin
        unset($_POST['length'], $_POST['start']);
        $idsRawList = $Hareket->getFilteredIds($_POST);
        $ids = array_map(fn($id) => (int)$id, $idsRawList);
    } else {
        $idsInput = $_POST["ids"] ?? [];
        if (!empty($idsInput) && is_string($idsInput)) {
            $idsInput = json_decode($idsInput, true) ?: [];
        }
        $ids = array_map(fn($id) => is_numeric($id) ? (int)$id : (int)Security::decrypt($id), (array)$idsInput);
    }
    $ids = array_filter($ids, fn($id) => $id > 0);

    if (empty($ids)) {
        jsonResponse("error", "Lütfen silmek için en az bir kayıt seçin.");
    }

    // Zaman ve bellek limitlerini büyük işlemler için artır (11.000+ kayıt için)
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    try {
        $successCount = 0;
        $Demirbas->db->beginTransaction();

        // 1. Tüm hareket detaylarını tek sorguda çek
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtH = $Demirbas->db->prepare("SELECT id, demirbas_id, aciklama, hareket_tipi, zimmet_id FROM demirbas_hareketler WHERE id IN ($placeholders)");
        $stmtH->execute($ids);
        $hareketler = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        $zimmetIdsToDelete = [];
        $demirbasIdsToRevertKaski = [];
        $movementIdsToSoftDelete = [];

        foreach ($hareketler as $h) {
            $aciklamaLower = mb_strtolower($h['aciklama'] ?? '', 'UTF-8');
            $tip = $h['hareket_tipi'] ?? '';
            $zimmetId = $h['zimmet_id'] ?? 0;
            $mId = (int)$h['id'];

            if ($tip === 'zimmet' && $zimmetId > 0) {
                $zimmetIdsToDelete[] = $zimmetId;
            } else {
                if (str_contains($aciklamaLower, 'kaski') && (str_contains($aciklamaLower, 'teslim') || str_contains($aciklamaLower, 'iade'))) {
                    $demirbasIdsToRevertKaski[] = $h['demirbas_id'];
                }
                $movementIdsToSoftDelete[] = $mId;
            }
        }

        // 2. Zimmet kayıtlarını sil (Eski sistem uyumu için model üzerinden)
        $uniqueZimmetIds = array_unique($zimmetIdsToDelete);
        foreach ($uniqueZimmetIds as $zId) {
            $Zimmet->delete($zId, false);
            // Bir zimmet silindiğinde ona bağlı hareketler de silinir, ancak sayacın başarısını 1 artırıyoruz (mantıksal birim)
            $successCount++; 
        }

        // 3. Kaski durumlarını toplu geri al
        $uniqueDemirbasIds = array_unique($demirbasIdsToRevertKaski);
        if (!empty($uniqueDemirbasIds)) {
            $placeholdersD = implode(',', array_fill(0, count($uniqueDemirbasIds), '?'));
            $sqlRevert = "UPDATE demirbas SET durum = 'aktif', lokasyon = 'bizim_depo', kaskiye_teslim_tarihi = NULL, kaskiye_teslim_eden = NULL, miktar = 1, kalan_miktar = 1 WHERE id IN ($placeholdersD)";
            $Demirbas->db->prepare($sqlRevert)->execute($uniqueDemirbasIds);
        }

        // 4. Kalan hareketleri toplu soft-delete yap
        if (!empty($movementIdsToSoftDelete)) {
            $placeholdersM = implode(',', array_fill(0, count($movementIdsToSoftDelete), '?'));
            $stmtM = $Demirbas->db->prepare("UPDATE demirbas_hareketler SET silinme_tarihi = NOW(), silen_kisi_id = ? WHERE id IN ($placeholdersM)");
            $stmtM->execute(array_merge([$_SESSION['id'] ?? 0], $movementIdsToSoftDelete));
            $successCount += count($movementIdsToSoftDelete);
        }

        $Demirbas->db->commit();
        
        // Log kaydı
        $SystemLog->logAction($_SESSION['id'] ?? 0, "Toplu Hareket Silme", "[$successCount] adet sayaç hareket kaydı toplu silme işlemi ile silindi.", SystemLogModel::LEVEL_CRITICAL);

        jsonResponse("success", "$successCount adet hareket kaydı başarıyla silindi. Kaski teslimatları ve zimmetler geri alındı.");
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) {
            $Demirbas->db->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Tekil Hareket Sil
if ($action == "hareket-sil") {
    Gate::can('demirbas_toplu_islem_sil');
    $id = $_POST["hareket_id"] ?? $_POST["id"] ?? null;
    if (!$id) jsonResponse("error", "Geçersiz hareket ID.");

    try {
        // Revert state logic for Kaski deliveries and Personnel assignments
        $qHareket = $Demirbas->db->prepare("SELECT demirbas_id, aciklama, hareket_tipi, zimmet_id FROM demirbas_hareketler WHERE id = ?");
        $qHareket->execute([$id]);
        $hareket = $qHareket->fetch(PDO::FETCH_ASSOC);

        if ($hareket) {
            $aciklamaLower = mb_strtolower($hareket['aciklama'] ?? '', 'UTF-8');
            $tip = $hareket['hareket_tipi'] ?? '';
            $zimmetId = $hareket['zimmet_id'] ?? 0;

            if ($tip === 'zimmet' && $zimmetId > 0) {
                // Zimmet silme işlemi (Stok ve durumu kendi içinde halleder)
                $Zimmet->delete($zimmetId, false);
            } elseif (str_contains($aciklamaLower, 'kaski') && (str_contains($aciklamaLower, 'teslim') || str_contains($aciklamaLower, 'iade'))) {
                // Meter is back in stock
                $Demirbas->db->prepare("UPDATE demirbas SET durum = 'aktif', lokasyon = 'bizim_depo', kaskiye_teslim_tarihi = NULL, kaskiye_teslim_eden = NULL, miktar = 1, kalan_miktar = 1 WHERE id = ?")->execute([$hareket['demirbas_id']]);
            }
        }

        $stmt = $Demirbas->db->prepare("UPDATE demirbas_hareketler SET silinme_tarihi = NOW(), silen_kisi_id = ? WHERE id = ?");
        if ($stmt->execute([$_SESSION['id'] ?? 0, $id])) {
            jsonResponse("success", "Hareket kaydı silindi ve işlem geri alındı.");
        } else {
            jsonResponse("error", "Silme işlemi başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Demirbaş Sil
if ($action == "demirbas-sil") {
    $id = $_POST["id"] ?? null;

    try {
        $zimmetler = $Zimmet->getByDemirbas(Security::decrypt($id));
        if (count($zimmetler) > 0) {
            jsonResponse("error", "Bu demirbaşın zimmet geçmişi (aktif veya eski) bulunmaktadır. Geçmiş verilerin korunması için silme işlemine izin verilmez. Bunun yerine durumunu 'pasif' olarak güncelleyebilirsiniz.");
        }

        $result = $Demirbas->delete($id);
        if ($result === true) {
            jsonResponse("success", "Demirbaş başarıyla silindi.");
        } else {
            jsonResponse("error", "Silme işlemi başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Demirbaş listesi (Server-side Datatables)
if ($action == "demirbas-listesi") {
    try {
        $tab = $_POST['tab'] ?? 'demirbas';

        $result = $Demirbas->getDatatableList($_POST, $tab);
        $start = intval($_POST['start'] ?? 0);

        $data = [];
        foreach ($result['data'] as $d) {
            $start++;
            $rowHtml = $Demirbas->getTableRow($d->id);
            if (!empty($rowHtml)) {
                // DOM Parsing just to extract td contents cleanly is overkill
                // Let's implement it better: we just return data arrays and datatable renders it,
                // OR we can just return what we expect. Let's return the columns directly.

                $enc_id = Security::encrypt($d->id);
                $miktar = (isset($d->miktar) && $d->miktar > 0) ? $d->miktar : (($tab === 'sayac') ? 1 : ($d->miktar ?? 1));
                $kalan = $d->kalan_miktar ?? 1;
                $minStok = $d->minimun_stok_uyari_miktari ?? 0;

                // Stok durumu badge
                $isIsimHurda = str_contains(mb_strtolower($d->demirbas_adi, 'UTF-8'), 'hurda');
                if ($isIsimHurda) {
                    $stokBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">Hurda (' . (int)$kalan . '/' . (int)$miktar . ')</span>';
                } else {
                    if ($kalan == 0) {
                        $stokBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">Stok Yok</span>';
                    } elseif ($minStok > 0 && $kalan <= $minStok) {
                        $stokBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">Stok Azaldı (' . $kalan . '/' . $miktar . ')</span>';
                    } elseif ($kalan < $miktar) {
                        $stokBadge = '<span class="badge" style="background: #f59e0b; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;"> ' . $kalan . '/' . $miktar . '</span>';
                    } else {
                        $stokBadge = '<span class="badge" style="background: #10b981; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">' . $kalan . '/' . $miktar . '</span>';
                    }
                }

                // Durum badge (İsimde 'hurda' geçiyorsa otomatik hurda say)
                $durumText = $d->durum ?? 'aktif';
                $isSayacTab = ($tab === 'sayac');
                $isIsimHurda = str_contains(mb_strtolower($d->demirbas_adi, 'UTF-8'), 'hurda');
                
                if ($isIsimHurda) {
                    $durumBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Hurda</span>';
                    $durumText = 'hurda';
                } else {
                    $durumMap = [
                        'aktif' => '<span class="badge" style="background: #10b981; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">' . ($isSayacTab ? 'Yeni' : 'Boşta') . '</span>',
                        'pasif' => '<span class="badge" style="background: #64748b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Pasif</span>',
                        'arizali' => '<span class="badge" style="background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Arızalı</span>',
                        'hurda' => '<span class="badge" style="background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Hurda</span>',
                        'personelde' => '<span class="badge" style="background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Personelde</span>',
                        'kaskiye teslim edildi' => '<span class="badge" style="background: #06b6d4; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">KASKİ\'ye İade Edildi</span>',
                    ];
                    $durumBadge = $durumMap[strtolower($durumText)] ?? '<span class="badge bg-soft-secondary text-secondary">' . $durumText . '</span>';
                }

                $actions = '';

                // Dropdown menu for actions
                if ($tab === 'sayac' || $tab === 'aparat' || $tab === 'demirbas') {
                    $actions = '<div class="dropdown d-inline-block">
                                    <button class="btn btn-soft-secondary btn-sm dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bx bx-dots-horizontal-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">';

                    if ($tab === 'sayac' && $kalan > 0) {
                        $actions .= '<li><a class="dropdown-item py-2 sayac-kasiye-teslim text-info" href="javascript:void(0);" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '">
                                        <i class="bx bx-buildings me-2"></i> Kaskiye Teslim Et
                                    </a></li>';
                        $actions .= '<li><hr class="dropdown-divider"></li>';
                    }

                    if ($kalan > 0) {
                        $actions .= '<li><a class="dropdown-item py-2 zimmet-ver text-warning" href="javascript:void(0);" data-id="' . $enc_id . '" data-raw-id="' . $d->id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '" data-kalan="' . $kalan . '">
                                        <i class="bx bx-transfer me-2"></i> Zimmet Ver
                                    </a></li>';
                    }

                    $actions .= '<li><a class="dropdown-item py-2 duzenle text-primary" href="javascript:void(0);" data-id="' . $enc_id . '">
                                    <i class="bx bx-edit me-2"></i> Düzenle
                                </a></li>';

                    $actions .= '<li><a class="dropdown-item py-2 demirbas-gecmis text-dark" href="javascript:void(0);" data-raw-id="' . $d->id . '" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '">
                                    <i class="bx bx-history me-2"></i> İşlem Geçmişi
                                </a></li>';

                    $actions .= '<li><hr class="dropdown-divider"></li>';

                    $actions .= '<li><a class="dropdown-item py-2 demirbas-sil text-danger" href="javascript:void(0);" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '">
                                    <i class="bx bx-trash me-2"></i> Sil
                                </a></li>';

                    $actions .= '</ul></div>';
                } else {
                    // Fallback for other tabs if any
                    if ($tab === 'sayac' && $kalan > 0) {
                        $actions .= '<button type="button" class="btn btn-sm btn-soft-info waves-effect waves-light sayac-kasiye-teslim" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '" title="Kaskiye Teslim"><i class="bx bx-buildings"></i></button> ';
                    }

                    if ($kalan > 0) {
                        $actions .= '<button type="button" class="btn btn-sm btn-soft-warning waves-effect waves-light zimmet-ver" data-id="' . $enc_id . '" data-raw-id="' . $d->id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '" data-kalan="' . $kalan . '" title="Zimmet Ver"><i class="bx bx-transfer"></i></button> ';
                    }

                    $actions .= '<button type="button" class="btn btn-sm btn-soft-primary waves-effect waves-light duzenle" data-id="' . $enc_id . '" title="Düzenle"><i class="bx bx-edit"></i></button> ';
                    $actions .= '<button type="button" class="btn btn-sm btn-soft-dark waves-effect waves-light demirbas-gecmis" data-raw-id="' . $d->id . '" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '" title="İşlem Geçmişi"><i class="bx bx-history"></i></button> ';
                    $actions .= '<button type="button" class="btn btn-sm btn-soft-danger waves-effect waves-light demirbas-sil" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($d->demirbas_adi) . '" title="Sil"><i class="bx bx-trash"></i></button>';
                }

                // Determine Kaskiye Teslim button for Sayac vs others
                $katBadgesHtml = '<span class="badge bg-soft-primary text-primary">' . ($d->kategori_adi ?? 'Kategorisiz') . '</span>';

                // Abone No ayıklama ve Seri No sütununa taşıma
                $displaySeriNo = $d->seri_no ?? '-';
                $displayDemirbasAdi = $d->demirbas_adi;
                $isAbone = false;
                
                if (preg_match('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', $displayDemirbasAdi, $matches)) {
                    $aboneNo = $matches[1];
                    if (empty($d->seri_no) || $d->seri_no == '-') {
                        $displaySeriNo = $aboneNo;
                        $isAbone = true;
                    }
                    $displayDemirbasAdi = preg_replace('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', '', $displayDemirbasAdi);
                }

                $markaHtml = '<div>' . ($d->marka ?? '-') . ' ' . ($d->model ?? '') . '</div><small class="text-muted">' . ($d->seri_no ? 'SN: ' . $d->seri_no : ($isAbone ? 'Abone: ' . $displaySeriNo : '')) . '</small>';
                $demirbasAdiHtml = '<a href="#" data-id="' . $enc_id . '" class="text-dark duzenle fw-medium">' . htmlspecialchars($displayDemirbasAdi) . '</a>';

                $data[] = [
                    "DT_RowId" => "row-" . $enc_id,
                    "DT_RowData" => [
                        "id" => $enc_id,
                        "kat-adi" => $d->kategori_adi ?? 'Kategorisiz',
                        "durum" => strtolower($durumText),
                        "bosta" => ($kalan > 0) ? '1' : '0',
                        "zimmetli" => ($kalan < $miktar) ? '1' : '0'
                    ],
                    "checkbox" => '
                                <div class="custom-checkbox-container d-inline-block">
                                    <input type="checkbox" class="custom-checkbox-input sayac-select" value="' . $enc_id . '" id="chk_' . $d->id . '">
                                    <label class="custom-checkbox-label" for="chk_' . $d->id . '"></label>
                                </div>',
                    "sira_no" => '<div class="text-center">' . $start . '</div>',
                    "id" => '<div class="text-center">' . $start . '</div>',
                    "demirbas_no" => '<div class="text-center">' . ($d->demirbas_no ?? '-') . '</div>',
                    "kategori_adi" => $katBadgesHtml,
                    "demirbas_adi" => $demirbasAdiHtml,
                    "marka_model" => $markaHtml,
                    "marka_sade" => '<div>' . ($d->marka ?? '-') . ' ' . ($d->model ?? '') . '</div>',
                    "seri_no" => $displaySeriNo,
                    "stok" => '<div class="text-center">' . $stokBadge . '</div>',
                    "durum" => '<div class="text-center">' . $durumBadge . '</div>',
                    "aciklama" => '<div class="text-wrap" style="max-width:200px; font-size:0.75rem;">' . htmlspecialchars($d->aciklama ?? '') . '</div>',
                    "tutar" => '<div class="text-end">' . Helper::formattedMoney($d->edinme_tutari ?? 0) . ' ₺' . '</div>',
                    "tarih" => (($d->edinme_tarihi ? date('d.m.Y', strtotime($d->edinme_tarihi)) : ($d->kayit_tarihi ? date('d.m.Y', strtotime($d->kayit_tarihi)) : '-'))),
                    "islemler" => '<div class="text-center text-nowrap">' . $actions . '</div>'
                ];
            }
        }

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 0),
            "recordsTotal" => $result['recordsTotal'],
            "recordsFiltered" => $result['recordsFiltered'],
            "data" => $data
        ]);
        exit;
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Toplu Kaskiye Teslim
if ($action == "bulk-kasiye-teslim") {
    try {
        $ids_raw = $_POST["ids"] ?? [];
        $tarih = $_POST["tarih"] ?? date('d.m.Y');
        $teslim_eden_id = intval($_POST["teslim_eden"] ?? ($_SESSION['personel_id'] ?? 0));
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        if ($teslim_eden_id > 0) {
            $pName = $Demirbas->db->query("SELECT adi_soyadi FROM personel WHERE id = $teslim_eden_id")->fetchColumn();
            if ($pName) $teslim_eden = $pName;
        }
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($ids_raw) || empty($tarih)) {
            jsonResponse("error", "Lütfen en az bir sayaç seçin ve tarih girin.");
        }
        if ($teslim_eden_id <= 0) {
            jsonResponse("error", "Lütfen teslim eden personeli seçin.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');
        $successCount = 0;
        $errorCount = 0;

        $Demirbas->db->beginTransaction();

        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', lokasyon = 'kaski', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
        $sqlMov = $Demirbas->db->prepare("INSERT INTO demirbas_hareketler (demirbas_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak, personel_id) VALUES (?, 'sarf', ?, ?, ?, ?, 'sistem', ?)");

        foreach ($ids_raw as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id)
                continue;

            $demirbas = $Demirbas->find($id);
            if (!$demirbas)
                continue;

            $teslimMiktari = (int)($demirbas->kalan_miktar ?? $demirbas->miktar ?? 0);
            if ($teslimMiktari <= 0) {
                $teslimMiktari = (int)($demirbas->miktar ?? 0);
            }
            if ($teslimMiktari <= 0) {
                $teslimMiktari = 1;
            }

            $sqlUpdate->execute([$formatted_tarih, $teslim_eden, ($aciklama ?? null), $id]);
            $sqlMov->execute([$id, $teslimMiktari, $formatted_tarih, "KASKİ'ye Teslim: " . ($aciklama ?? 'Detay yok'), ($_SESSION['id'] ?? 0), $teslim_eden_id]);
            
            $successCount++;
        }

        $Demirbas->db->commit();

        jsonResponse("success", "$successCount sayaç başarıyla Kaskiye teslim edildi.");
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) {
            $Demirbas->db->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// ============== ZİMMET İŞLEMLERİ ==============

// Zimmet listesini getir
if ($action == "zimmet-listesi") {
    try {
        $result = $Zimmet->getDatatableList($_POST);

        // Aparat zimmetleri için hareket toplamlarını tek sorguda çek (N+1 önleme)
        $hareketOzetMap = [];
        $zimmetIds = array_map(fn($row) => (int) ($row->id ?? 0), $result['data'] ?? []);
        $zimmetIds = array_values(array_filter($zimmetIds));
        if (!empty($zimmetIds)) {
            $placeholders = implode(',', array_fill(0, count($zimmetIds), '?'));
            $sqlHareketOzet = $Demirbas->db->prepare(" 
                SELECT 
                    zimmet_id,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND (aciklama IS NULL OR aciklama NOT LIKE '[DEPO_IADE]%') THEN miktar ELSE 0 END), 0) as toplam_saha_iade,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND aciklama LIKE '[DEPO_IADE]%' THEN miktar ELSE 0 END), 0) as toplam_depo_iade,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'sarf' THEN miktar ELSE 0 END), 0) as toplam_sarf,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'kayip' THEN miktar ELSE 0 END), 0) as toplam_kayip
                FROM demirbas_hareketler
                WHERE silinme_tarihi IS NULL AND zimmet_id IN ($placeholders)
                GROUP BY zimmet_id
            ");
            $sqlHareketOzet->execute($zimmetIds);
            $hareketRows = $sqlHareketOzet->fetchAll(PDO::FETCH_OBJ);
            foreach ($hareketRows as $hr) {
                $hareketOzetMap[(int) $hr->zimmet_id] = [
                    'saha_iade' => (int) ($hr->toplam_saha_iade ?? 0),
                    'depo_iade' => (int) ($hr->toplam_depo_iade ?? 0),
                    'sarf' => (int) ($hr->toplam_sarf ?? 0),
                    'kayip' => (int) ($hr->toplam_kayip ?? 0)
                ];
            }
        }

        $data = [];
        foreach ($result['data'] as $z) {
            $enc_id = Security::encrypt($z->id);
            $teslimTarihi = date('d.m.Y', strtotime($z->teslim_tarihi));

            // Aparat ve Sayaç kategorisi kontrolü
            $katAdiLower = mb_strtolower($z->kategori_adi ?? '', 'UTF-8');
            $isAparat = str_contains($katAdiLower, 'aparat');
            $isSayac = str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac');
            $effectiveDurum = $z->durum;

            // Aparatlar için etkin durumu hareket bakiyesine göre hesapla
            if ($isAparat && !in_array($z->durum, ['kayip', 'arizali'], true)) {
                $hz = $hareketOzetMap[(int) $z->id] ?? ['saha_iade' => 0, 'depo_iade' => 0, 'sarf' => 0, 'kayip' => 0];
                $aparatKalan = (int) ($z->teslim_miktar ?? 0)
                    + (int) ($hz['saha_iade'] ?? 0)
                    - (int) ($hz['depo_iade'] ?? 0)
                    - ((int) ($hz['sarf'] ?? 0) + (int) ($hz['kayip'] ?? 0));
                $effectiveDurum = $aparatKalan > 0 ? 'teslim' : 'iade';
            }

            // Durum badge - Aparat kategorisi için "İade Edildi" yerine "Tüketildi"
            if ($isAparat) {
                $durumBadges = [
                    'teslim' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Zimmetli</span>',
                    'iade' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Tüketildi</span>',
                    'kayip' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Kayıp</span>',
                    'arizali' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #64748b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Arızalı</span>'
                ];
            } else {
                $durumBadges = [
                    'teslim' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Zimmetli</span>',
                    'iade' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #10b981; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">İade Edildi</span>',
                    'kayip' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Kayıp</span>',
                    'arizali' => '<span class="badge zimmet-detay" style="cursor:pointer; background: #64748b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;" data-id="' . $enc_id . '">Arızalı</span>'
                ];
            }
            $durumBadge = $durumBadges[$effectiveDurum] ?? '<span class="badge bg-info">Bilinmiyor</span>';

            $iadeButton = '';
            if ($effectiveDurum === 'teslim') {
                if ($isAparat) {
                    $iadeButton = '
                        <a href="#" data-id="' . $enc_id . '" data-demirbas="' . htmlspecialchars($z->demirbas_adi) . '" data-personel="' . htmlspecialchars($z->personel_adi) . '" data-miktar="' . $z->teslim_miktar . '" data-is-aparat="1" data-islem-turu="tuketim" class="dropdown-item zimmet-iade">
                            <span class="mdi mdi-minus-circle font-size-18 text-info me-1"></span> Tüketildi İşaretle
                        </a>
                        <a href="#" data-id="' . $enc_id . '" data-demirbas="' . htmlspecialchars($z->demirbas_adi) . '" data-personel="' . htmlspecialchars($z->personel_adi) . '" data-miktar="' . $z->teslim_miktar . '" data-is-aparat="1" data-islem-turu="depo_iade" class="dropdown-item zimmet-iade">
                            <span class="mdi mdi-warehouse font-size-18 text-success me-1"></span> Depoya İade Al
                        </a>';
                } else {
                    $iadeButton = '<a href="#" data-id="' . $enc_id . '" data-demirbas="' . htmlspecialchars($z->demirbas_adi) . '" data-personel="' . htmlspecialchars($z->personel_adi) . '" data-miktar="' . $z->teslim_miktar . '" data-is-aparat="0" data-islem-turu="iade" class="dropdown-item zimmet-iade">
                        <span class="mdi mdi-undo font-size-18 text-success me-1"></span> İade Al
                    </a>';
                    
                    if ($isSayac) {
                        $iadeButton .= '
                        <a href="#" data-id="' . $enc_id . '" data-demirbas="' . htmlspecialchars($z->demirbas_adi) . '" data-personel="' . htmlspecialchars($z->personel_adi) . '" data-miktar="' . $z->teslim_miktar . '" data-is-aparat="0" data-islem-turu="montaj_yap" class="dropdown-item zimmet-iade">
                            <span class="mdi mdi-plus-circle font-size-18 text-warning me-1"></span> Montaj Yap (Takıldı)
                        </a>';
                    }
                }
            }


            $actions = '<div class="dropdown">
                            <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                ' . $iadeButton . '
                                <a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-detay">
                                    <span class="mdi mdi-eye font-size-18 text-info me-1"></span> Detay
                                </a>
                                ' . ($effectiveDurum !== 'iade' ? '
                                <a href="#" class="dropdown-item zimmet-sil" data-id="' . $enc_id . '">
                                    <span class="mdi mdi-delete font-size-18 text-danger me-1"></span> Sil
                                </a>' : '') . '
                            </div>
                        </div>';

            $disabledCheckbox = ($effectiveDurum === 'iade') ? 'disabled' : '';
            $data[] = [
                "checkbox" => '
                    <div class="custom-checkbox-container">
                        <input type="checkbox" ' . $disabledCheckbox . ' class="custom-checkbox-input zimmet-select" id="zimmet_check_' . $z->id . '" value="' . $enc_id . '">
                        <label for="zimmet_check_' . $z->id . '" class="custom-checkbox-label"></label>
                    </div>',
                "id" => $z->id,
                "enc_id" => $enc_id,
                "kategori_adi" => '<span class="badge bg-soft-primary text-primary">' . ($z->kategori_adi ?? '-') . '</span>',
                "demirbas_adi" => ($z->demirbas_adi ?? '-'),
                "marka_model" => '<div>' . ($z->marka ?? '-') . ' ' . ($z->model ?? '') . '</div>' . ($z->seri_no ? '<small class="text-muted">SN: ' . $z->seri_no . '</small>' : ''),
                "personel_adi" => ($z->personel_adi ?? '-'),
                "seri_no" => $z->seri_no ?? '-',
                "teslim_miktar" => '<div class="text-center">' . $z->teslim_miktar . '</div>',
                "teslim_tarihi" => $teslimTarihi,
                "durum" => '<div class="text-center">' . $durumBadge . '</div>',
                "islemler" => '<div class="text-center">' . $actions . '</div>'
            ];
        }

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 0),
            "recordsTotal" => $result['recordsTotal'],
            "recordsFiltered" => $result['recordsFiltered'],
            "data" => $data
        ]);
        exit;
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Zimmet Ver (Yeni zimmet kaydı)
if ($action == "zimmet-kaydet") {
    try {
        $data = [
            "demirbas_id" => intval($_POST["demirbas_id"]),
            "personel_id" => intval($_POST["personel_id"]),
            "teslim_tarihi" => Date::Ymd($_POST["teslim_tarihi"], 'Y-m-d'),
            "teslim_miktar" => intval($_POST["teslim_miktar"] ?? 1),
            "aciklama" => $_POST["aciklama"] ?? null,
            "teslim_eden_id" => $_SESSION["id"] ?? null
        ];

        $lastId = $Zimmet->zimmetVer($data);
        $son_kayit = $Zimmet->getTableRow(Security::decrypt($lastId));

        jsonResponse("success", "Zimmet işlemi başarıyla tamamlandı. Stok güncellendi.", ["son_kayit" => $son_kayit]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Toplu Aparat Zimmet Kaydet
if ($action == "toplu-aparat-zimmet-kaydet") {
    try {
        $items = json_decode($_POST["items"] ?? "[]", true);
        $personel_id = intval($_POST["personel_id"] ?? 0);
        $teslim_tarihi = Date::Ymd($_POST["teslim_tarihi"], 'Y-m-d');
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($items)) {
            jsonResponse("error", "Zimmetlenecek aparat listesi boş.");
        }

        if ($personel_id <= 0) {
            jsonResponse("error", "Lütfen personel seçiniz.");
        }

        if (empty($teslim_tarihi)) {
            jsonResponse("error", "Teslim tarihi zorunludur.");
        }

        $Zimmet->getDb()->beginTransaction();
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($items as $item) {
            $rawId = $item['demirbas_id'] ?? 0;
            // Eğer encrypted ise decrypt et, değilse direkt kullan
            $demirbas_id = is_numeric($rawId) ? intval($rawId) : intval(Security::decrypt($rawId));
            $miktar = intval($item['miktar'] ?? 1);

            if ($demirbas_id <= 0 || $miktar <= 0) {
                $errorCount++;
                $errors[] = "Geçersiz veri: ID=$demirbas_id, Miktar=$miktar";
                continue;
            }

            try {
                $data = [
                    "demirbas_id" => $demirbas_id,
                    "personel_id" => $personel_id,
                    "teslim_tarihi" => $teslim_tarihi,
                    "teslim_miktar" => $miktar,
                    "aciklama" => $aciklama,
                    "teslim_eden_id" => $_SESSION["id"] ?? null,
                    "kaynak" => "manuel"
                ];

                $Zimmet->zimmetVer($data);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = $e->getMessage();
            }
        }

        $Zimmet->getDb()->commit();

        $message = "$successCount adet aparat başarıyla zimmetlendi.";
        if ($errorCount > 0) {
            $message .= " $errorCount adet hata oluştu.";
        }

        jsonResponse("success", $message, ["toplam" => $successCount, "hatalar" => $errors]);
    } catch (Exception $ex) {
        if ($Zimmet->getDb()->inTransaction()) {
            $Zimmet->getDb()->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Koli Kontrol (Zimmet Modalı İçin)
if ($action == "koli-kontrol") {
    try {
        $seriler = json_decode($_POST["seriler"] ?? "[]", true);
        if (empty($seriler)) {
            jsonResponse("error", "Seri listesi boş.");
        }

        // Veritabanından bu serilere sahip ürünleri çek
        // SQL Injection'a karşı placeholder oluştur
        $placeholders = implode(',', array_fill(0, count($seriler), '?'));

        $sql = $Demirbas->getDb()->prepare("
            SELECT id, seri_no, 
            (COALESCE(miktar, 1) - COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'zimmet' AND silinme_tarihi IS NULL), 0) + COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL), 0)) as kalan_miktar, 
            durum 
            FROM demirbas 
            WHERE firma_id = ? AND seri_no IN ($placeholders)
        ");

        $params = array_merge([$_SESSION['firma_id']], $seriler);
        $sql->execute($params);
        $records = $sql->fetchAll(PDO::FETCH_ASSOC);

        // Sonuçları işle (key olarak seri no kullan)
        $dbResults = [];
        foreach ($records as $rec) {
            $dbResults[$rec['seri_no']] = $rec;
        }

        $response = [];
        foreach ($seriler as $seri) {
            if (isset($dbResults[$seri])) {
                $rec = $dbResults[$seri];
                $kalan = intval($rec['kalan_miktar']);
                $durum = strtolower($rec['durum']);

                if ($kalan > 0 && !in_array($durum, ['hurda', 'arizali'])) {
                    $response[$seri] = ["status" => "ok", "id" => $rec['id']];
                } else {
                    $response[$seri] = ["status" => "not_in_stock", "id" => $rec['id']];
                }
            } else {
                $response[$seri] = ["status" => "missing"];
            }
        }

        jsonResponse("success", "Kontrol tamamlandı", ["data" => $response]);

    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Zimmet Koli Kaydet (Çoklu Koli)
if ($action == "zimmet-koli-kaydet-coklu") {
    try {
        $koli_detaylari = json_decode($_POST["koli_detaylari"] ?? "[]", true);
        $koli_baslangiclar = json_decode($_POST["koli_baslangiclar"] ?? "[]", true);
        $personel_id = intval($_POST["personel_id"]);
        $teslim_tarihi = Date::Ymd($_POST["teslim_tarihi"], 'Y-m-d');
        $aciklama = $_POST["aciklama"] ?? null;

        if ((empty($koli_baslangiclar) && empty($koli_detaylari)) || $personel_id <= 0) {
            jsonResponse("error", "Eksik bilgi.");
        }

        $tumSeriler = [];
        $koliMap = []; // Hangi seri hangi koliye ait
        $koliTarihMap = []; // Hangi koli hangi tarihe ait

        if (!empty($koli_detaylari)) {
            foreach ($koli_detaylari as $detay) {
                $baslangic = $detay['baslangic'] ?? '';
                $tarih = $detay['tarih'] ?? '';
                if (!preg_match('/^(.*?)(\d+)$/', $baslangic, $matches)) continue;

                $prefix = $matches[1];
                $number = intval($matches[2]);
                $digits = strlen($matches[2]);

                $adet = intval($detay['adet'] ?? 10);
                for ($i = 0; $i < $adet; $i++) {
                    $nextNum = str_pad($number + $i, $digits, "0", STR_PAD_LEFT);
                    $seri = $prefix . $nextNum;
                    $tumSeriler[] = $seri;
                    $koliMap[$seri] = $baslangic;
                }
                $koliTarihMap[$baslangic] = $tarih ? Date::Ymd($tarih, 'Y-m-d') : $teslim_tarihi;
            }
        } else {
            foreach ($koli_baslangiclar as $baslangic) {
                if (!preg_match('/^(.*?)(\d+)$/', $baslangic, $matches)) continue;
                $prefix = $matches[1];
                $number = intval($matches[2]);
                $digits = strlen($matches[2]);
                for ($i = 0; $i < 10; $i++) {
                    $nextNum = str_pad($number + $i, $digits, "0", STR_PAD_LEFT);
                    $seri = $prefix . $nextNum;
                    $tumSeriler[] = $seri;
                    $koliMap[$seri] = $baslangic;
                }
                $koliTarihMap[$baslangic] = $teslim_tarihi;
            }
        }

        if (empty($tumSeriler)) {
            jsonResponse("error", "İşlenecek seri numarası bulunamadı.");
        }

        // Veritabanından ID'leri bul
        $placeholders = implode(',', array_fill(0, count($tumSeriler), '?'));
        $sql = $Demirbas->getDb()->prepare("
            SELECT id, seri_no, 
            (COALESCE(miktar, 1) - COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'zimmet' AND silinme_tarihi IS NULL), 0) + COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL), 0)) as kalan_miktar 
            FROM demirbas 
            WHERE firma_id = ? AND seri_no IN ($placeholders)
        ");
        $params = array_merge([$_SESSION['firma_id']], $tumSeriler);
        $sql->execute($params);
        $records = $sql->fetchAll(PDO::FETCH_ASSOC);

        // Stok kontrolü (Backend tarafında da tekrar kontrol edelim)
        $dbRecordsMap = [];
        foreach ($records as $rec) {
            $dbRecordsMap[$rec['seri_no']] = $rec;
        }

        $eksikSeriler = [];
        foreach ($tumSeriler as $seri) {
            if (!isset($dbRecordsMap[$seri]) || $dbRecordsMap[$seri]['kalan_miktar'] <= 0) {
                $eksikSeriler[] = $seri;
            }
        }

        if (!empty($eksikSeriler)) {
            jsonResponse("error", "Bazı sayaçlar stokta bulunamadı: " . implode(", ", array_slice($eksikSeriler, 0, 5)) . (count($eksikSeriler) > 5 ? "..." : ""));
        }

        // İşlem
        $Zimmet->getDb()->beginTransaction();
        $successCount = 0;

        foreach ($records as $rec) {
            $seri = $rec['seri_no'];
            $koliBaslangic = $koliMap[$seri] ?? '?';
            $ozelTarih = $koliTarihMap[$koliBaslangic] ?? $teslim_tarihi;

            $data = [
                "demirbas_id" => $rec['id'],
                "personel_id" => $personel_id,
                "teslim_tarihi" => $ozelTarih,
                "teslim_miktar" => 1,
                "aciklama" => $aciklama ? "$aciklama (Koli: $koliBaslangic)" : "Koli: $koliBaslangic",
                "teslim_eden_id" => $_SESSION["id"] ?? null
            ];

            $Zimmet->zimmetVer($data);
            $successCount++;
        }

        $Zimmet->getDb()->commit();

        jsonResponse("success", "$successCount adet sayaç başarıyla zimmetlendi.");

    } catch (Exception $ex) {
        if ($Zimmet->getDb()->inTransaction()) {
            $Zimmet->getDb()->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Zimmet İade
if ($action == "zimmet-iade") {
    $zimmet_id = Security::decrypt($_POST["zimmet_id"]);

    try {
        $iade_tarihi = $_POST["iade_tarihi"];
        $iade_miktar = intval($_POST["iade_miktar"] ?? 1);
        $aciklama = $_POST["iade_aciklama"] ?? null;

        $zimmetKaydi = $Zimmet->find($zimmet_id);
        if (!$zimmetKaydi) {
            jsonResponse("error", "Zimmet kaydı bulunamadı.");
        }

        if ($iade_miktar <= 0) {
            jsonResponse("error", "İşlem miktarı en az 1 olmalıdır.");
        }

        $sqlKat = $Demirbas->db->prepare("SELECT COALESCE(k.tur_adi, '') as kategori_adi
                                          FROM demirbas d
                                          LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                                          WHERE d.id = ? LIMIT 1");
        $sqlKat->execute([$zimmetKaydi->demirbas_id]);
        $kategoriAdi = (string) ($sqlKat->fetchColumn() ?? '');
        $isAparat = str_contains(mb_strtolower($kategoriAdi, 'UTF-8'), 'aparat');

        if ($isAparat) {
            // Aparat etkin bakiyesi: teslim + sahadan iade - depoya iade - sarf - kayıp
            $sqlOzet = $Demirbas->db->prepare(" 
                SELECT
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND (aciklama IS NULL OR aciklama NOT LIKE '[DEPO_IADE]%') THEN miktar ELSE 0 END), 0) as saha_iade,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND aciklama LIKE '[DEPO_IADE]%' THEN miktar ELSE 0 END), 0) as depo_iade,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'sarf' THEN miktar ELSE 0 END), 0) as sarf,
                    COALESCE(SUM(CASE WHEN hareket_tipi = 'kayip' THEN miktar ELSE 0 END), 0) as kayip
                FROM demirbas_hareketler
                WHERE zimmet_id = ? AND silinme_tarihi IS NULL
            ");
            $sqlOzet->execute([$zimmet_id]);
            $ozet = $sqlOzet->fetch(PDO::FETCH_OBJ) ?: (object) ['saha_iade' => 0, 'depo_iade' => 0, 'sarf' => 0, 'kayip' => 0];

            $kalanAparat = (int) ($zimmetKaydi->teslim_miktar ?? 0)
                + (int) ($ozet->saha_iade ?? 0)
                - (int) ($ozet->depo_iade ?? 0)
                - (int) ($ozet->sarf ?? 0)
                - (int) ($ozet->kayip ?? 0);

            if ($kalanAparat <= 0) {
                jsonResponse("error", "Bu kayıtta tüketim için aktif aparat bakiyesi bulunmuyor.");
            }
            if ($iade_miktar > $kalanAparat) {
                jsonResponse("error", "İşlem miktarı personelin mevcut aparat bakiyesinden fazla olamaz. Mevcut: $kalanAparat");
            }
        } else {
            if ($zimmetKaydi->durum !== 'teslim') {
                jsonResponse("error", "Sadece aktif zimmet kayıtlarında işlem yapılabilir.");
            }
            $kalanZimmet = (int) ($zimmetKaydi->teslim_miktar ?? 0) - (int) ($zimmetKaydi->iade_miktar ?? 0);
            if ($iade_miktar > $kalanZimmet) {
                jsonResponse("error", "İşlem miktarı zimmette kalan miktardan fazla olamaz. Kalan: $kalanZimmet");
            }
        }

        $islem_turu = $_POST["islem_turu"] ?? 'iade';

        if ($islem_turu === 'montaj_yap' || ($isAparat && $islem_turu === 'tuketim')) {
            $result = $Zimmet->tuketimYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama);
            
            // Eğer sayaç montajı yapılıyorsa otomatik hurda girişi yapalım
            if ($result && $isSayac && $islem_turu === 'montaj_yap') {
                // Sayaç kategori ID'sini bul
                $sqlKat = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND (LOWER(tur_adi) LIKE '%sayaç%' OR LOWER(tur_adi) LIKE '%sayac%') AND firma_id = ? LIMIT 1");
                $sqlKat->execute([$_SESSION['firma_id']]);
                $sayacKatId = $sqlKat->fetchColumn();

                // 1. Hurda demirbaş kaydı oluştur
                $sqlInsert = $Demirbas->db->prepare("
                    INSERT INTO demirbas 
                    (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, aciklama, kayit_yapan)
                    VALUES (?, ?, ?, ?, ?, 'hurda', ?, ?)
                ");
                $hurdaAdi = "Hurda Sayaç (" . htmlspecialchars($zimmetKaydi->demirbas_adi) . " Yerine)";
                $sqlInsert->execute([
                    $_SESSION['firma_id'],
                    $sayacKatId ?: null,
                    $hurdaAdi,
                    $iade_miktar,
                    0, // Zimmette olduğu için kalan_miktar 0
                    "Montaj işlemi sonucu sökülen hurda sayaç. " . ($aciklama ? "Not: $aciklama" : ""),
                    $_SESSION['id'] ?? null
                ]);
                $yeniDemirbasId = $Demirbas->db->lastInsertId();

                // 2. Personele zimmetle
                $Zimmet->zimmetVer([
                    'demirbas_id' => $yeniDemirbasId,
                    'personel_id' => $zimmetKaydi->personel_id,
                    'teslim_tarihi' => $iade_tarihi,
                    'teslim_miktar' => $iade_miktar,
                    'aciklama' => "Montaj işlemi sırasında sökülen hurda sayaç sistem tarafından zimmetlendi.",
                    'kaynak' => 'sistem'
                ]);
                $yeniZimmetId = $Zimmet->db->lastInsertId();

                // 3. Zimmet hareketi ekle
                $Hareket->saveWithAttr([
                    "id" => 0,
                    "zimmet_id" => $yeniZimmetId,
                    "demirbas_id" => $yeniDemirbasId,
                    "personel_id" => $zimmetKaydi->personel_id,
                    "hareket_tipi" => 'zimmet',
                    "miktar" => $iade_miktar,
                    "aciklama" => "Montaj sonucu hurda girişi",
                    "islem_yapan_id" => $_SESSION['id'] ?? 0,
                    "tarih" => $iade_tarihi
                ]);
            }
        } else {
            $result = $Zimmet->iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama);
        }

        if ($result) {
            if ($isAparat) {
                jsonResponse("success", "Tüketim işlemi başarıyla tamamlandı. Personel zimmeti güncellendi.");
            }
            jsonResponse("success", "İade işlemi başarıyla tamamlandı. Stok güncellendi.");
        } else {
            jsonResponse("error", "İşlem başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Aparat Depoya İade (personelden depoya geri alma)
if ($action == "zimmet-depoya-iade") {
    $zimmet_id = Security::decrypt($_POST["zimmet_id"] ?? '');

    try {
        $iade_tarihi = $_POST["iade_tarihi"] ?? date('d.m.Y');
        $iade_miktar = intval($_POST["iade_miktar"] ?? 1);
        $aciklama = trim($_POST["iade_aciklama"] ?? '');

        $zimmetKaydi = $Zimmet->find($zimmet_id);
        if (!$zimmetKaydi) {
            jsonResponse("error", "Zimmet kaydı bulunamadı.");
        }

        $sqlKat = $Demirbas->db->prepare("SELECT COALESCE(k.tur_adi, '') as kategori_adi
                                          FROM demirbas d
                                          LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                                          WHERE d.id = ? LIMIT 1");
        $sqlKat->execute([$zimmetKaydi->demirbas_id]);
        $kategoriAdi = (string) ($sqlKat->fetchColumn() ?? '');
        $isAparat = str_contains(mb_strtolower($kategoriAdi, 'UTF-8'), 'aparat');
        if (!$isAparat) {
            jsonResponse("error", "Depoya iade alma işlemi sadece aparat kategorisinde kullanılabilir.");
        }

        if ($iade_miktar <= 0) {
            jsonResponse("error", "İade miktarı en az 1 olmalıdır.");
        }

        // Aparat etkin bakiyesini hesapla: teslim + sahadan iade - depoya iade - sarf - kayıp
        $sqlOzet = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND (aciklama IS NULL OR aciklama NOT LIKE '[DEPO_IADE]%') THEN miktar ELSE 0 END), 0) as saha_iade,
                COALESCE(SUM(CASE WHEN hareket_tipi = 'iade' AND aciklama LIKE '[DEPO_IADE]%' THEN miktar ELSE 0 END), 0) as depo_iade,
                COALESCE(SUM(CASE WHEN hareket_tipi = 'sarf' THEN miktar ELSE 0 END), 0) as sarf,
                COALESCE(SUM(CASE WHEN hareket_tipi = 'kayip' THEN miktar ELSE 0 END), 0) as kayip
            FROM demirbas_hareketler
            WHERE zimmet_id = ? AND silinme_tarihi IS NULL
        ");
        $sqlOzet->execute([$zimmet_id]);
        $ozet = $sqlOzet->fetch(PDO::FETCH_OBJ) ?: (object) ['saha_iade' => 0, 'depo_iade' => 0, 'sarf' => 0, 'kayip' => 0];

        $kalanAparat = (int) ($zimmetKaydi->teslim_miktar ?? 0)
            + (int) ($ozet->saha_iade ?? 0)
            - (int) ($ozet->depo_iade ?? 0)
            - (int) ($ozet->sarf ?? 0)
            - (int) ($ozet->kayip ?? 0);

        if ($kalanAparat <= 0) {
            jsonResponse("error", "Sadece aktif zimmet kayıtlarında depoya iade yapılabilir.");
        }

        if ($iade_miktar > $kalanAparat) {
            jsonResponse("error", "İade miktarı personelin mevcut aparat bakiyesinden fazla olamaz. Mevcut: $kalanAparat");
        }

        $prefixAciklama = '[DEPO_IADE] ' . ($aciklama !== '' ? $aciklama : 'Aparat depoya iade alındı');
        $result = $Zimmet->iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $prefixAciklama);

        if ($result) {
            jsonResponse("success", "Depoya iade alma işlemi tamamlandı. Personel zimmeti azaldı, depo stoğu arttı.");
        }

        jsonResponse("error", "Depoya iade alma işlemi başarısız.");
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Hurda Sayaç - Personelin zimmetindeki hurda sayaçları getir
if ($action == "hurda-zimmet-listesi") {
    try {
        $personel_id = intval($_POST["personel_id"] ?? 0);
        if ($personel_id <= 0) {
            jsonResponse("error", "Geçersiz personel.");
        }

        // 1. Sayaç kategorilerini bul
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $_SESSION['firma_id'], ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse("success", "OK", ["data" => [], "elinde_hurda" => 0]);
        }
        $in = buildInClause($catIds);

        // 2. Zimmetleri getir
        $sql = $Demirbas->db->prepare("
            SELECT z.id, z.teslim_miktar, z.teslim_tarihi,
                   d.demirbas_adi, d.marka, d.model, d.seri_no, d.durum as demirbas_durum,
                   (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL) as iade_miktar,
                   (z.teslim_miktar - (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL)) as kalan_miktar
            FROM demirbas_zimmet z
            INNER JOIN demirbas d ON z.demirbas_id = d.id
            WHERE d.kategori_id IN ($in) 
              AND z.personel_id = ?
              AND z.durum = 'teslim'
              AND d.firma_id = ?
              AND (LOWER(d.durum) = 'hurda' OR LOWER(d.demirbas_adi) LIKE '%hurda%')
              AND z.silinme_tarihi IS NULL
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute(array_merge($catIds, [$personel_id, $_SESSION['firma_id']]));
        $zimmetler = $sql->fetchAll(PDO::FETCH_OBJ);

        $result = [];
        foreach ($zimmetler as $z) {
            if ($z->kalan_miktar <= 0)
                continue;
            $result[] = [
                "id" => Security::encrypt($z->id),
                "demirbas_adi" => $z->demirbas_adi,
                "marka_model" => trim(($z->marka ?? '') . ' ' . ($z->model ?? '')),
                "seri_no" => $z->seri_no ?? '-',
                "kalan_miktar" => $z->kalan_miktar,
                "teslim_tarihi" => date('d.m.Y', strtotime($z->teslim_tarihi)),
            ];
        }

        // 3. ELİNDEKİ TOPLAM HURDA SAYISINI HESAPLA (Dashboard KPI'ı ile aynı mantık)
        $sqlScrap = $Demirbas->db->prepare("
            SELECT
                (COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%' AND h.aciklama NOT LIKE '[HURDA_IADE]%'))
                    THEN h.miktar ELSE 0 END), 0))
                - COALESCE(SUM(CASE 
                    WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar
                    WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar
                    ELSE 0 END), 0) as elinde_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL 
              AND h.personel_id = ? 
              AND d.firma_id = ? 
              AND d.kategori_id IN ($in)
        ");
        $sqlScrap->execute(array_merge([$personel_id, $_SESSION['firma_id']], $catIds));
        $elindeHurdaCount = (int) $sqlScrap->fetchColumn();

        jsonResponse("success", "OK", ["data" => $result, "elinde_hurda" => max(0, $elindeHurdaCount)]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Hurda Sayaç İade Al (Personelden depoya)
if ($action == "hurda-sayac-iade") {
    // Tüm modellerin aynı DB bağlantısını kullanmasını sağla (Transaction uyumu için)
    $sharedDb = $Demirbas->db;
    $Zimmet->db = $sharedDb;
    $Hareket->db = $sharedDb;

    // Zimmet modelinin içindeki private Hareket modeline de aynı db'yi ver
    try {
        $ref = new ReflectionProperty(get_class($Zimmet), 'Hareket');
        $ref->setAccessible(true);
        $zHareket = $ref->getValue($Zimmet);
        if ($zHareket) {
            $zHareket->db = $sharedDb;
        }
    } catch (Exception $e) {
        // Reflection hatası durumunda sessizce devam et (Veya logla)
    }

    try {
        $mode = $_POST["mode"] ?? "manual"; // manual veya select

        if ($mode === "select") {
            // Seçili zimmetlerden iade
            $selectedIds = json_decode($_POST["selected_ids"] ?? "[]", true);
            $iade_tarihi = $_POST["hurda_iade_tarihi"] ?? date('d.m.Y');
            $aciklama = $_POST["hurda_aciklama"] ?? null;

            if (empty($selectedIds)) {
                jsonResponse("error", "Lütfen en az bir hurda sayaç seçin.");
            }

            $directKaski = ($_POST["direct_kaski"] ?? '0') === '1';
            $Zimmet->getDb()->beginTransaction();
            $successCount = 0;

            foreach ($selectedIds as $enc_id) {
                $zimmet_id = Security::decrypt($enc_id);
                if (!$zimmet_id)
                    continue;

                $zimmetBilgi = $Zimmet->find($zimmet_id);
                if (!$zimmetBilgi || $zimmetBilgi->durum !== 'teslim')
                    continue;

                $kalanMiktar = (int) $zimmetBilgi->teslim_miktar - (int) ($zimmetBilgi->iade_miktar ?? 0);
                if ($kalanMiktar <= 0)
                    continue;

                if ($directKaski) {
                    $Zimmet->tuketimYap(
                        $zimmet_id,
                        $iade_tarihi,
                        $kalanMiktar,
                        "[KASKI_TESLIM] Personelden doğrudan KASKİ'ye teslim. Not: " . ($aciklama ?? 'Detay yok'),
                        null,
                        null,
                        'manuel'
                    );

                    $formatted_tarih = Date::Ymd($iade_tarihi, 'Y-m-d');
                    // Demirbaşı güncelle
                    $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', lokasyon = 'kaski', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, kalan_miktar = 0 WHERE id = ?");
                    $pName = $Demirbas->db->query("SELECT adi_soyadi FROM personel WHERE id = {$zimmetBilgi->personel_id}")->fetchColumn();
                    $sqlUpdate->execute([$formatted_tarih, $pName ?: 'Personel', $zimmetBilgi->demirbas_id]);
                } else {
                    $Zimmet->iadeYap(
                        $zimmet_id,
                        $iade_tarihi,
                        $kalanMiktar,
                        $aciklama ? "[IADE] Hurda Sayaç İade: " . $aciklama : "[IADE] Hurda Sayaç İade (Manuel)",
                        null,
                        null,
                        'manuel'
                    );
                }

                $successCount++;
            }

            $Zimmet->getDb()->commit();
            jsonResponse("success", "$successCount adet hurda sayaç başarıyla " . ($directKaski ? "KASKİ'ye teslim edildi." : "depoya iade alındı."));

        } else {
            // Manuel giriş (yeni hurda kayıt oluştur ve doğrudan iade al)
            $personel_id = intval($_POST["hurda_personel_id"] ?? 0);
            $iade_tarihi = $_POST["hurda_iade_tarihi"] ?? date('d.m.Y');
            $adet = intval($_POST["hurda_iade_adet"] ?? 1);
            $sayac_adi = $_POST["hurda_sayac_adi"] ?? '';
            $aciklama = $_POST["hurda_aciklama"] ?? null;
            $directKaski = ($_POST["direct_kaski"] ?? '0') === '1';

            if ($personel_id <= 0) {
                jsonResponse("error", "Lütfen bir personel seçin.");
            }
            if ($adet <= 0) {
                jsonResponse("error", "Adet en az 1 olmalıdır.");
            }

            // Sayaç kategori ID'sini bul
            $sqlKat = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND (LOWER(tur_adi) LIKE '%sayaç%' OR LOWER(tur_adi) LIKE '%sayac%') AND firma_id = ? LIMIT 1");
            $sqlKat->execute([$_SESSION['firma_id']]);
            $sayacKatId = $sqlKat->fetchColumn();

            if (!$sayacKatId) {
                jsonResponse("error", "Sayaç kategorisi bulunamadı. Lütfen tanımlamalardan bir sayaç kategorisi oluşturun.");
            }

            $formatted_tarih = Date::Ymd($iade_tarihi, 'Y-m-d');
            if (empty($sayac_adi)) {
                $sayac_adi = "Hurda Sayaç (Manuel İade)";
            }

            $Demirbas->db->beginTransaction();

            $status = $directKaski ? 'Kaskiye Teslim Edildi' : 'hurda';
            $lokasyon = $directKaski ? 'kaski' : 'bizim_depo';
            $kalan_miktar = $directKaski ? 0 : $adet;

            // 1. Hurda sayaç demirbaş kaydı oluştur
            $sqlInsert = $Demirbas->db->prepare("
                INSERT INTO demirbas 
                (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, lokasyon, aciklama, kayit_yapan, edinme_tarihi, kaskiye_teslim_tarihi, kaskiye_teslim_eden)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $pName = $Demirbas->db->query("SELECT adi_soyadi FROM personel WHERE id = $personel_id")->fetchColumn();

            $sqlInsert->execute([
                $_SESSION['firma_id'],
                $sayacKatId,
                $sayac_adi,
                $adet,
                $kalan_miktar,
                $status,
                $lokasyon,
                $aciklama ? "Manuel Hurda İade: " . $aciklama : "Manuel Hurda Sayaç İade",
                $_SESSION['id'] ?? null,
                $formatted_tarih,
                $directKaski ? $formatted_tarih : null,
                $directKaski ? ($pName ?: null) : null
            ]);
            $yeniDemirbasId = $Demirbas->db->lastInsertId();

            // 2. İade hareketini ekle (Personelden sisteme geliş)
            // ÖNEMLİ: [HURDA_IADE] öneki ile işaretliyoruz ki KPI'lar iade olarak saysın
            $iadeAciklama = $directKaski ? "[KASKI_TESLIM] Personelden doğrudan KASKİ'ye teslim. " : "[HURDA_IADE] Hurda Sayaç İade. ";
            if ($aciklama) {
                $iadeAciklama .= "Not: " . $aciklama;
            } else {
                $iadeAciklama .= "(Manuel Giriş)";
            }

            // Özel kural: Manuel iadelerde zimmet_id olmadığı için
            // HareketModel'in "zimmet_id zorunlu" kontrolüne takılmamak adına raw SQL kullanıyoruz.
            $sqlHareket = $Demirbas->db->prepare("
                INSERT INTO demirbas_hareketler 
                (demirbas_id, personel_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($directKaski) {
                $sqlHareket->execute([
                    $yeniDemirbasId,
                    $personel_id,
                    'sarf', // Sadece sarf (Kaski) hareketi oluşturulur
                    $adet,
                    $formatted_tarih,
                    $iadeAciklama,
                    $_SESSION['id'] ?? null,
                    'manuel'
                ]);
            } else {
                $sqlHareket->execute([
                    $yeniDemirbasId,
                    $personel_id,
                    'iade', // Düz iade
                    $adet,
                    $formatted_tarih,
                    $iadeAciklama,
                    $_SESSION['id'] ?? null,
                    'manuel'
                ]);
            }

            $Demirbas->db->commit();
            jsonResponse("success", "$adet adet hurda sayaç başarıyla işlendi.", ["yeni_id" => Security::encrypt($yeniDemirbasId)]);
        }
    } catch (Exception $ex) {
        if ($Demirbas->db->inTransaction()) {
            $Demirbas->db->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Zimmet Sil
if ($action == "zimmet-sil") {
    $id = $_POST["id"] ?? null;

    try {
        // Zimmet bilgisini al
        $zimmet = $Zimmet->find(Security::decrypt($id));

        if (!$zimmet) {
            jsonResponse("error", "Zimmet kaydı bulunamadı.");
        }

        if ($zimmet->durum === 'iade') {
            jsonResponse("error", "İade alınmış (arşiv) kayıtları silemezsiniz.");
        }

        $result = $Zimmet->delete($id);
        if ($result === true) {
            jsonResponse("success", "Zimmet kaydı başarıyla silindi. Stok bilgisi güncellendi.");
        } else {
            jsonResponse("error", "Silme işlemi başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Zimmet Toplu Sil
if ($action == "bulk-zimmet-sil") {
    Gate::can('demirbas_toplu_islem_sil');
    try {
        $ids_raw = $_POST["ids"] ?? [];
        if (empty($ids_raw)) {
            jsonResponse("error", "Lütfen en az bir zimmet kaydı seçin.");
        }

        $successCount = 0;
        $errorCount = 0;

        $Zimmet->getDb()->beginTransaction();

        foreach ($ids_raw as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id)
                continue;

            $zimmet = $Zimmet->find($id);
            if (!$zimmet) {
                continue;
            }

            // User requested to NOT allow deleting iade records in bulk? 
            // "iade alındı durumundaki kayıtların seçilmesine ve silinmesine... izin verme"
            if ($zimmet->durum === 'iade') {
                $errorCount++;
                continue;
            }

            $result = $Zimmet->delete($enc_id);
            if ($result === true) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $Zimmet->getDb()->commit();

        $message = "$successCount adet aktif zimmet kaydı başarıyla silindi ve stoklar güncellendi.";
        if ($errorCount > 0) {
            $message .= " $errorCount kayıt iade edildi durumunda olduğu için veya hata nedeniyle silinemedi.";
        }

        jsonResponse("success", $message);
    } catch (Exception $ex) {
        if ($Zimmet->getDb()->inTransaction()) {
            $Zimmet->getDb()->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Zimmet Toplu İade
if ($action == "bulk-zimmet-iade") {
    Gate::can('demirbas_toplu_islem_sil');
    try {
        $ids_raw = $_POST["ids"] ?? [];
        $iade_tarihi = $_POST["iade_tarihi"] ?? date('d.m.Y');
        $aciklama = $_POST["aciklama"] ?? 'Toplu İade Alındı';

        if (empty($ids_raw)) {
            jsonResponse("error", "Lütfen en az bir zimmet kaydı seçin.");
        }

        $successCount = 0;
        $errorCount = 0;

        $Zimmet->getDb()->beginTransaction();

        foreach ($ids_raw as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id)
                continue;

            $zimmet = $Zimmet->find($id);
            if (!$zimmet)
                continue;

            if ($zimmet->durum !== 'teslim') {
                $errorCount++;
                continue;
            }

            $teslim_miktar = (int) ($zimmet->teslim_miktar ?? 1);
            $mevcut_iade = (int) ($zimmet->iade_miktar ?? 0);
            $kalan_zimmet = $teslim_miktar - $mevcut_iade;

            if ($kalan_zimmet > 0) {
                $result = $Zimmet->iadeYap($id, $iade_tarihi, $kalan_zimmet, $aciklama);
                if ($result === true) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $successCount++;
            }
        }

        $Zimmet->getDb()->commit();

        $message = "$successCount adet zimmet kaydı başarıyla iade alındı.";
        if ($errorCount > 0) {
            $message .= " $errorCount kayıt iade alınırken hata oluştu.";
        }

        jsonResponse("success", $message);
    } catch (Exception $ex) {
        if ($Zimmet->getDb()->inTransaction()) {
            $Zimmet->getDb()->rollBack();
        }
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// Zimmet Hareket Sil (İadeyi Geri Al)
if ($action == "zimmet-hareket-sil") {
    $id = Security::decrypt($_POST["id"] ?? null);

    try {
        if (!$id) {
            jsonResponse("error", "Geçersiz hareket ID.");
        }

        $result = $Zimmet->iadeSil($id);
        if ($result === true) {
            jsonResponse("success", "İşlem başarıyla geri alındı. Stok ve zimmet durumu güncellendi.");
        } else {
            jsonResponse("error", "İşlem başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Zimmet Hareket Toplu Sil
if ($action == "zimmet-hareket-toplu-sil") {
    $ids = $_POST["ids"] ?? [];

    try {
        if (empty($ids) || !is_array($ids)) {
            jsonResponse("error", "Lütfen en az bir işlem seçin.");
        }

        $successCount = 0;
        $errorCount = 0;
        
        $Zimmet->getDb()->beginTransaction();
        
        foreach($ids as $enc_id) {
            $id = Security::decrypt($enc_id);
            if(!$id) continue;
            
            $result = $Zimmet->iadeSil($id);
            if ($result === true) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        $Zimmet->getDb()->commit();
        
        $message = "$successCount işlem başarıyla geri alındı. Stok ve zimmet durumu güncellendi.";
        if($errorCount > 0) {
            $message .= " $errorCount işlem başarısız oldu.";
        }

        jsonResponse("success", $message);
    } catch (Exception $ex) {
        if ($Zimmet->getDb()->inTransaction()) {
            $Zimmet->getDb()->rollBack();
        }
        jsonResponse("error", $ex->getMessage());
    }
}


// Zimmet Detay
if ($action == "zimmet-detay") {
    $id = Security::decrypt($_POST["id"] ?? $_GET["id"]);

    try {
        $zimmet = $Zimmet->find($id);
        if ($zimmet) {
            // Hareket tablosundan personel genel bakiyesini al
            $bakiye = $Hareket->getPersonelDemirbasBakiye($zimmet->personel_id, $zimmet->demirbas_id);

            // Bu zimmet kaydına ait özel hareket geçmişini al
            $hareketler = $Hareket->getZimmetHareketleri($id);

            // Hareket verilerini formatla
            $toplamIade = 0;
            $toplamDepoIade = 0;
            $toplamSarf = 0;
            foreach ($hareketler as $h) {
                if ($h->hareket_tipi === 'iade') {
                    $isDepoIade = strpos((string) ($h->aciklama ?? ''), '[DEPO_IADE]') === 0;
                    if ($isDepoIade) {
                        $toplamDepoIade += (int) ($h->miktar ?? 0);
                    } else {
                        $toplamIade += (int) ($h->miktar ?? 0);
                    }
                }
                if ($h->hareket_tipi === 'sarf' || $h->hareket_tipi === 'kayip') {
                    $toplamSarf += (int) ($h->miktar ?? 0);
                }

                $h->id = Security::encrypt($h->id);
                $h->tarih_format = date('d.m.Y', strtotime($h->tarih));
                $h->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi, $h->aciklama);
                $h->kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);
            }


            // Personel bilgisini al
            $personel = $Zimmet->getDb()->query("SELECT * FROM personel WHERE id = {$zimmet->personel_id}")->fetch(PDO::FETCH_OBJ);
            $zimmet->personel_detay = $personel;

            // Personel+Demirbaş geçmişini 'demirbas_hareketler' tablosundan alıyoruz
            $gecmis = $Hareket->getPersonelHareketleri($zimmet->personel_id, $zimmet->demirbas_id, 100);

            // Geçmiş verilerini formatla
            foreach ($gecmis as $g) {
                $g->tarih_format = date('d.m.Y', strtotime($g->tarih));
                $g->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($g->hareket_tipi, $g->aciklama);
                $g->personel_adi = $personel->adi_soyadi ?? ''; // js de personel_adi var
                $g->personel_telefon = $personel->cep_telefonu ?? '';
            }

            // Şu anki zimmet detaylarını da zenginleştir
            $demirbasForKat = $Demirbas->find($zimmet->demirbas_id);
            $detayKatAdi = '';
            if ($demirbasForKat && $demirbasForKat->kategori_id) {
                $katSql = $Zimmet->getDb()->prepare("SELECT tur_adi FROM tanimlamalar WHERE id = ? AND grup = 'demirbas_kategorisi' LIMIT 1");
                $katSql->execute([$demirbasForKat->kategori_id]);
                $katResult = $katSql->fetch(PDO::FETCH_OBJ);
                $detayKatAdi = $katResult->tur_adi ?? '';
            }
            $isDetayAparat = str_contains(mb_strtolower($detayKatAdi, 'UTF-8'), 'aparat');

            if ($isDetayAparat) {
                $encZimmetId = Security::encrypt($zimmet->id);
                $aparatKalan = (int) ($zimmet->teslim_miktar ?? 0) + $toplamIade - $toplamDepoIade - $toplamSarf;
                if ($aparatKalan > 0) {
                    $zimmet->durum = 'teslim';
                    $zimmet->durum_badge = '<span class="badge bg-warning zimmet-detay-ac" style="cursor:pointer;" data-id="' . $encZimmetId . '">Zimmetli</span>';
                } else {
                    $zimmet->durum = 'iade';
                    $zimmet->durum_badge = '<span class="badge bg-danger zimmet-detay-ac" style="cursor:pointer;" data-id="' . $encZimmetId . '">Tüketildi</span>';
                }
            } else {
                $durumBadges = [
                    'teslim' => '<span class="badge bg-warning zimmet-detay-ac" style="cursor:pointer;" data-id="'.Security::encrypt($zimmet->id).'">Zimmetli</span>',
                    'iade' => '<span class="badge bg-success zimmet-detay-ac" style="cursor:pointer;" data-id="'.Security::encrypt($zimmet->id).'">İade Edildi</span>',
                    'kayip' => '<span class="badge bg-danger zimmet-detay-ac" style="cursor:pointer;" data-id="'.Security::encrypt($zimmet->id).'">Kayıp</span>',
                    'arizali' => '<span class="badge bg-secondary zimmet-detay-ac" style="cursor:pointer;" data-id="'.Security::encrypt($zimmet->id).'">Arızalı</span>'
                ];
                $zimmet->durum_badge = $durumBadges[$zimmet->durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
            }
            $zimmet->teslim_tarihi_format = date('d.m.Y', strtotime($zimmet->teslim_tarihi));
            $zimmet->is_aparat = $isDetayAparat ? 1 : 0;

            // Demirbaş bilgilerini al
            $demirbas = $Demirbas->find($zimmet->demirbas_id);
            $zimmet->demirbas_detay = $demirbas;

            jsonResponse("success", "Başarılı", [
                "data" => $zimmet,
                "gecmis" => $gecmis,
                "hareketler" => $hareketler,
                "bakiye" => $bakiye
            ]);
        } else {
            jsonResponse("error", "Zimmet bulunamadı.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}


// Excel'den Yükle
if ($action == "excel-upload") {
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] != 0) {
        jsonResponse("error", "Lütfen geçerli bir Excel dosyası seçin.");
    }

    try {
        $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        } else {
            throw new Exception("Excel kütüphanesi bulunamadı.");
        }

        $inputFileName = $_FILES['excelFile']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // İlk satır başlıklar, atla
        $header = array_shift($rows);

        // Tüm mevcut kategorileri ön-yükle (performans için)
        $mevcutKategoriler = [];
        $katSql = $Tanimlamalar->getDb()->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND firma_id = ? AND silinme_tarihi IS NULL");
        $katSql->execute([$_SESSION['firma_id']]);
        $katSonuclar = $katSql->fetchAll(PDO::FETCH_OBJ);
        foreach ($katSonuclar as $k) {
            $mevcutKategoriler[mb_strtolower(trim($k->tur_adi), 'UTF-8')] = $k->id;
        }

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];
        $skipped = [];

        foreach ($rows as $index => $row) {
            if (empty($row[1]))
                continue; // Demirbaş adı boşsa atla

            $satirNo = $index + 2; // Excel satır numarası (1. satır başlık)

            // Kategori eşleşme kontrolü
            $kategoriId = null;
            if (!empty($row[8])) {
                $katAdi = trim($row[8]);
                $katAdiLower = mb_strtolower($katAdi, 'UTF-8');

                if (isset($mevcutKategoriler[$katAdiLower])) {
                    $kategoriId = $mevcutKategoriler[$katAdiLower];
                } else {
                    // Kategori eşleşmedi, satırı atla
                    $skippedCount++;
                    $skipped[] = [
                        'satir' => $satirNo,
                        'demirbas_adi' => $row[1],
                        'kategori' => $katAdi,
                        'neden' => "\"$katAdi\" kategorisi tanımlamalar tablosunda bulunamadı."
                    ];
                    continue;
                }
            } else {
                // Kategori belirtilmemişse satırı atla
                $skippedCount++;
                $skipped[] = [
                    'satir' => $satirNo,
                    'demirbas_adi' => $row[1],
                    'kategori' => '-',
                    'neden' => "Kategori bilgisi boş bırakılmış."
                ];
                continue;
            }

            try {
                $data = [
                    "id" => 0,
                    "demirbas_no" => $row[0] ?? null,
                    "firma_id" => $_SESSION['firma_id'] ?? 0,
                    "demirbas_adi" => $row[1],
                    "kategori_id" => $kategoriId,
                    "marka" => $row[2] ?? null,
                    "model" => $row[3] ?? null,
                    "seri_no" => $row[4] ?? null,
                    "miktar" => intval($row[5] ?? 1),
                    "kalan_miktar" => intval($row[5] ?? 1),
                    "edinme_tutari" => floatval($row[6] ?? 0),
                    "edinme_tarihi" => !empty($row[7]) ? date('Y-m-d', strtotime($row[7])) : null,
                    "durum" => 'aktif',
                    "kayit_yapan" => $_SESSION["id"] ?? null
                ];

                $Demirbas->saveWithAttr($data);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Satır " . $satirNo . ": " . $e->getMessage();
            }
        }

        $message = "$successCount adet demirbaş başarıyla yüklendi.";
        if ($skippedCount > 0) {
            $message .= " $skippedCount satır kategori eşleşmediği için atlandı.";
        }
        if ($errorCount > 0) {
            $message .= " $errorCount hata oluştu.";
        }

        // Mevcut kategori listesini de gönder (bilgilendirme amaçlı)
        $mevcutKategoriAdlari = array_map(function ($k) {
            return $k->tur_adi;
        }, $katSonuclar);

        jsonResponse("success", $message, [
            "errors" => $errors,
            "skipped" => $skipped,
            "skippedCount" => $skippedCount,
            "successCount" => $successCount,
            "mevcutKategoriler" => $mevcutKategoriAdlari
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
    }
}

// ============== ARAMA İŞLEMLERİ ==============

// Select2 için personel arama
if ($action == "personel-ara") {
    $search = $_GET["q"] ?? $_POST["q"] ?? "";
    $type = $_GET["type"] ?? $_POST["type"] ?? "all";

    try {
        $Personel = new \App\Model\PersonelModel();
        $results = $Personel->searchForZimmet($search, $type);
        echo json_encode(["results" => $results]);
    } catch (Exception $ex) {
        echo json_encode(["results" => []]);
    }
    exit;
}

// Select2 için demirbaş arama
if ($action == "demirbas-ara") {
    $search = $_GET["q"] ?? $_POST["q"] ?? "";
    $type = $_GET["type"] ?? $_POST["type"] ?? "demirbas";

    try {
        $results = $Demirbas->getForSelect($search, $type);
        echo json_encode(["results" => $results]);
    } catch (Exception $ex) {
        echo json_encode(["results" => []]);
    }
    exit;
}

// Aparat Zimmet Kayıtları (Belirli bir aparata ait personel zimmet kayıtları)
if ($action == "aparat-zimmet-kayitlari") {
    $demirbas_id = intval($_POST["demirbas_id"] ?? 0);

    try {
        if ($demirbas_id <= 0) {
            jsonResponse("error", "Geçersiz demirbaş ID.");
        }

        // Demirbaş bilgisi
        $demirbas = $Demirbas->find($demirbas_id);
        if (!$demirbas) {
            jsonResponse("error", "Demirbaş bulunamadı.");
        }

        // Bu aparata ait zimmet kayıtları
        $sqlZimmetler = $Zimmet->getDb()->prepare("
            SELECT 
                z.id, z.demirbas_id, z.personel_id, z.teslim_tarihi, z.teslim_miktar, z.durum, z.aciklama, z.teslim_eden_id, z.kayit_tarihi, z.guncelleme_tarihi, z.silinme_tarihi,
                (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL) as iade_miktar,
                (SELECT MAX(tarih) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL) as iade_tarihi,
                p.adi_soyadi AS personel_adi,
                p.cep_telefonu AS personel_telefon
            FROM demirbas_zimmet z
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.demirbas_id = ? AND z.silinme_tarihi IS NULL
            ORDER BY z.kayit_tarihi DESC
        ");
        $sqlZimmetler->execute([$demirbas_id]);
        $zimmetler = $sqlZimmetler->fetchAll(PDO::FETCH_OBJ);

        // Kategori bilgisi (aparat kontrolü)
        $katAdi = '';
        if ($demirbas->kategori_id) {
            $katSql = $Zimmet->getDb()->prepare("SELECT tur_adi FROM tanimlamalar WHERE id = ? LIMIT 1");
            $katSql->execute([$demirbas->kategori_id]);
            $katResult = $katSql->fetch(PDO::FETCH_OBJ);
            $katAdi = $katResult->tur_adi ?? '';
        }
        $isAparat = str_contains(mb_strtolower($katAdi, 'UTF-8'), 'aparat');

        // Formatla
        foreach ($zimmetler as $z) {
            $z->enc_id = Security::encrypt($z->id);
            $z->teslim_tarihi_format = date('d.m.Y', strtotime($z->teslim_tarihi));
            $z->iade_tarihi_format = $z->iade_tarihi ? date('d.m.Y', strtotime($z->iade_tarihi)) : '-';

            if ($isAparat) {
                $durumBadges = [
                    'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                    'iade' => '<span class="badge bg-danger">Tüketildi</span>',
                    'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                    'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
                ];
            } else {
                $durumBadges = [
                    'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                    'iade' => '<span class="badge bg-success">İade Edildi</span>',
                    'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                    'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
                ];
            }
            $z->durum_badge = $durumBadges[$z->durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
        }

        // Özet
        $toplam = count($zimmetler);
        $aktif = count(array_filter($zimmetler, fn($z) => $z->durum === 'teslim'));
        $tuketilen = count(array_filter($zimmetler, fn($z) => $z->durum === 'iade'));

        jsonResponse("success", "Başarılı", [
            "demirbas" => $demirbas,
            "zimmetler" => $zimmetler,
            "ozet" => [
                "toplam" => $toplam,
                "aktif" => $aktif,
                "tuketilen" => $tuketilen
            ]
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }

}

// Kategori listesi
if ($action == "kategori-listesi") {
    try {
        $kategoriler = $Tanimlamalar->getDemirbasKategorileri();
        jsonResponse("success", "Başarılı", ["data" => $kategoriler]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// İş emri sonuçlarını getir (otomatik zimmet ayarları için)
if ($action == "is-emri-sonuclari") {
    try {
        require_once dirname(__DIR__, 2) . '/App/Model/TanimlamalarModel.php';
        $Tanimlamalar = new \App\Model\TanimlamalarModel();
        $sonuclar = $Tanimlamalar->getIsEmriSonuclari();

        $options = [['id' => '', 'text' => 'Seçiniz (Yok)']];
        foreach ($sonuclar as $sonuc) {
            $options[] = ['id' => $sonuc, 'text' => $sonuc];
        }

        jsonResponse("success", "Başarılı", ["data" => $options]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Hareket Geçmişi - Personel bazlı
if ($action == "hareket-gecmisi") {
    $personel_id = intval($_POST["personel_id"] ?? $_GET["personel_id"] ?? 0);
    $demirbas_id = intval($_POST["demirbas_id"] ?? $_GET["demirbas_id"] ?? 0);

    try {
        if ($personel_id > 0) {
            // Bakiyeyi hesapla
            $bakiyeler = $Hareket->getPersonelTumBakiyeler($personel_id);

            // Hareket geçmişini al
            $hareketler = $Hareket->getPersonelHareketleri($personel_id, $demirbas_id > 0 ? $demirbas_id : null, 200);

            // Formatla
            foreach ($hareketler as $h) {
                $h->tarih_format = date('d.m.Y', strtotime($h->tarih));
                $h->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi);
                $h->kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);
            }

            jsonResponse("success", "Başarılı", [
                "bakiyeler" => $bakiyeler,
                "hareketler" => $hareketler
            ]);
        } elseif ($demirbas_id > 0) {
            // Demirbaş DataTables server-side
            $demirbas_kaydi = $Demirbas->find($demirbas_id);
            $katAdi2 = '';
            if ($demirbas_kaydi && $demirbas_kaydi->kategori_id) {
                $katSql2 = $Demirbas->getDb()->prepare("SELECT tur_adi FROM tanimlamalar WHERE id = ? LIMIT 1");
                $katSql2->execute([$demirbas_kaydi->kategori_id]);
                $katResult2 = $katSql2->fetch(PDO::FETCH_OBJ);
                $katAdi2 = $katResult2->tur_adi ?? '';
            }
            $isDemirbasAparat = str_contains(mb_strtolower($katAdi2, 'UTF-8'), 'aparat');

            // Eğer POST içinde start ve length varsa DataTable server-side talebidir
            if (isset($_POST['draw'])) {
                $result = $Hareket->getDemirbasHareketleriDatatable($_POST, $demirbas_id, $isDemirbasAparat);

                $data = [];
                foreach ($result['data'] as $h) {
                    $tarih_format = date('d.m.Y', strtotime($h->tarih));
                    $hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi);
                    $kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);

                    $data[] = [
                        $hareket_badge,
                        '<div class="text-center fw-bold">' . $h->miktar . '</div>',
                        $tarih_format,
                        $h->personel_adi ?? '-',
                        '<span class="small">' . ($h->aciklama ?? '') . '</span>',
                        '<div class="text-end small">' . ($h->islem_yapan_adi ?? $kaynak_badge ?? '-') . '</div>'
                    ];
                }

                echo json_encode([
                    "draw" => intval($_POST['draw'] ?? 0),
                    "recordsTotal" => $result['recordsTotal'],
                    "recordsFiltered" => $result['recordsFiltered'],
                    "data" => $data
                ]);
                exit;
            } else {
                // Standart AJAX isteği (mevcut yapıyı bozmamak için - personel bazlı vs.)
                $hareketler = $Hareket->getDemirbasHareketleri($demirbas_id);
                $gosterilecekHareketler = [];
                foreach ($hareketler as $h) {
                    if ($isDemirbasAparat && $h->kaynak === 'puantaj_excel') {
                        continue;
                    }

                    $h->tarih_format = date('d.m.Y', strtotime($h->tarih));
                    $h->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi);
                    $h->kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);

                    $gosterilecekHareketler[] = $h;
                }

                jsonResponse("success", "Başarılı", ["hareketler" => $gosterilecekHareketler]);
            }
        } else {
            jsonResponse("error", "Personel veya demirbaş ID gereklidir.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Personel Demirbaş Bakiyesi
if ($action == "personel-bakiye") {
    $personel_id = intval($_POST["personel_id"] ?? $_GET["personel_id"] ?? 0);

    try {
        if ($personel_id > 0) {
            $bakiyeler = $Hareket->getPersonelTumBakiyeler($personel_id);
            jsonResponse("success", "Başarılı", ["bakiyeler" => $bakiyeler]);
        } else {
            jsonResponse("error", "Personel ID gereklidir.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// ============== PERSONEL BAZLI SAYAÇ/APARAT DEPOSU API ==============

// KASKİ Tarih Bazlı Hareket Listesi


// KASKİ Tarih Bazlı Detay Listesi (Accordion için)
if ($action == "sayac-kaski-date-details") {
    try {
        $tarih = $_POST['tarih']; // YYYY-MM-DD
                $islemTipi = trim((string)($_POST['islem_tipi'] ?? ''));
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        
        if (empty($tarih)) {
            jsonResponse("error", "Tarih bilgisi eksik.");
        }

                $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
                if (empty($catIds)) {
                        jsonResponse("success", "Başarılı", ["html" => '<div class="text-center text-muted p-3">Kayıt bulunamadı.</div>']);
                }
                $in = buildInClause($catIds);

                if ($islemTipi === 'KASKİ İşlemi') {
                        $sql = "SELECT h.id, d.demirbas_adi, d.seri_no, u.adi_soyadi as islem_yapan_adi, h.aciklama
                                        FROM demirbas_hareketler h
                                        INNER JOIN demirbas d ON h.demirbas_id = d.id
                                        LEFT JOIN personel u ON h.islem_yapan_id = u.id
                                        WHERE h.silinme_tarihi IS NULL
                                            AND d.firma_id = ?
                                            AND d.kategori_id IN ($in)
                                            AND DATE(h.tarih) = ?
                                            AND h.hareket_tipi = 'sarf'
                                            AND (
                                                        LOWER(COALESCE(h.aciklama, '')) LIKE '%kask%'
                                                        OR (LOWER(COALESCE(d.durum,'')) = 'kaskiye teslim edildi' AND DATE(d.kaskiye_teslim_tarihi) = ?)
                                                    )
                                        ORDER BY h.id ASC";
                        $params = array_merge([$firmaId], $catIds, [$tarih, $tarih]);
                } else {
                        $sql = "SELECT d.id, d.demirbas_adi, d.seri_no, u.adi_soyadi as islem_yapan_adi, d.aciklama
                                        FROM demirbas d
                                        LEFT JOIN personel u ON d.kayit_yapan = u.id
                                        WHERE d.firma_id = ?
                                            AND d.kategori_id IN ($in)
                                            AND d.silinme_tarihi IS NULL
                                            AND DATE(COALESCE(d.edinme_tarihi, d.kayit_tarihi)) = ?
                                            AND LOWER(COALESCE(d.durum,'')) NOT LIKE '%hurda%'
                                            AND LOWER(COALESCE(d.demirbas_adi,'')) NOT LIKE '%hurda%'
                                        ORDER BY d.id ASC";
                        $params = array_merge([$firmaId], $catIds, [$tarih]);
                }

                $stmt = $Demirbas->db->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="p-3 bg-light rounded"><table class="table table-sm table-bordered mb-0 bg-white shadow-sm">
                    <thead class="table-dark">
                        <tr class="small text-uppercase">
                            <th class="text-center" style="width: 50px;">ID</th>
                            <th>Sayaç Adı</th>
                            <th class="text-center">Abone No</th>
                            <th>İşlem Yapan</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        if (empty($rows)) {
            $html .= '<tr><td colspan="5" class="text-center text-muted py-3">Bu tarihte kayıt bulunamadı.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $displaySeriNo = $r['seri_no'];
                $displayDemirbasAdi = $r['demirbas_adi'];
                
                if (preg_match('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', $displayDemirbasAdi, $matches)) {
                    $aboneNo = $matches[1];
                    if (empty($displaySeriNo) || $displaySeriNo == '-') {
                        $displaySeriNo = $aboneNo;
                    }
                    $displayDemirbasAdi = preg_replace('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', '', $displayDemirbasAdi);
                }

                $html .= '<tr class="small">
                            <td class="text-center">'.$r['id'].'</td>
                            <td class="fw-bold">'.$displayDemirbasAdi.'</td>
                            <td class="text-center"><code class="text-danger fw-bold" style="font-size: 1.2rem;">'.($displaySeriNo ?: '-').'</code></td>
                            <td>'.($r['islem_yapan_adi'] ?: 'Sistem').'</td>
                            <td>'.$r['aciklama'].'</td>
                          </tr>';
            }
        }
        $html .= '</tbody></table></div>';

        jsonResponse("success", "Başarılı", ["html" => $html]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

if ($action == "sayac-global-summary") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse("success", "Başarılı", [
                'yeni_depoda' => 0,
                'hurda_depoda' => 0,
                'yeni_personelde' => 0,
                'hurda_personelde' => 0,
                'toplam_alinan' => 0,
                'toplam_hurda' => 0,
                'hurda_kaskiye' => 0,
                'kayip_yeni' => 0,
                'takilan' => 0
            ]);
        }

        $inStr = implode(',', array_map('intval', $catIds));

        $sqlStats = $Demirbas->db->prepare("
            SELECT
                (SELECT COUNT(d1.id)
                 FROM demirbas d1
                 WHERE d1.kategori_id IN ($inStr)
                   AND d1.firma_id = ?
                   AND d1.silinme_tarihi IS NULL
                   AND LOWER(COALESCE(d1.durum, '')) NOT LIKE '%hurda%'
                   AND LOWER(COALESCE(d1.demirbas_adi, '')) NOT LIKE '%manuel iade%') as toplam_alinan,

                -- Personelde kalan yeni sayaç = zimmet - personel sarf - kayıp - depoya iade
                (SELECT
                    COALESCE(SUM(CASE WHEN h2.hareket_tipi = 'zimmet' 
                        AND LOWER(COALESCE(d2.durum, '')) NOT LIKE '%kaski%' 
                        THEN h2.miktar ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN h2.hareket_tipi = 'sarf' THEN h2.miktar ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN h2.hareket_tipi = 'kayip' THEN h2.miktar ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN h2.hareket_tipi = 'iade' AND (h2.aciklama LIKE '[DEPO_IADE]%' OR h2.aciklama LIKE '%Hurda Sayaç İade%') THEN h2.miktar ELSE 0 END), 0)
                 FROM demirbas_hareketler h2
                 INNER JOIN demirbas d2 ON d2.id = h2.demirbas_id
                 WHERE d2.kategori_id IN ($inStr)
                   AND d2.firma_id = ?
                   AND h2.silinme_tarihi IS NULL
                   AND h2.personel_id IS NOT NULL
                   AND LOWER(COALESCE(d2.durum, '')) NOT LIKE '%hurda%'
                   AND LOWER(COALESCE(d2.demirbas_adi, '')) NOT LIKE '%hurda%') as yeni_personelde,

                -- Takılan yeni sayaç (yalnızca personel kaynaklı sarf, kaskiye teslimat hariç)
                (SELECT COALESCE(SUM(h3.miktar), 0)
                 FROM demirbas_hareketler h3
                 INNER JOIN demirbas d3 ON d3.id = h3.demirbas_id
                 WHERE d3.kategori_id IN ($inStr)
                   AND d3.firma_id = ?
                   AND h3.silinme_tarihi IS NULL
                   AND h3.hareket_tipi = 'sarf'
                   AND (LOWER(COALESCE(h3.aciklama, '')) NOT LIKE '%kask%' AND LOWER(COALESCE(d3.durum, '')) NOT LIKE '%kaski%')
                   AND h3.personel_id IS NOT NULL) as takilan,

                -- Personeldeki yeni sayaç kayıp
                (SELECT COALESCE(SUM(h4.miktar), 0)
                 FROM demirbas_hareketler h4
                 INNER JOIN demirbas d4 ON d4.id = h4.demirbas_id
                 WHERE d4.kategori_id IN ($inStr)
                   AND d4.firma_id = ?
                   AND h4.silinme_tarihi IS NULL
                   AND h4.hareket_tipi = 'kayip'
                   AND h4.personel_id IS NOT NULL
                   AND LOWER(COALESCE(d4.durum, '')) NOT LIKE '%hurda%'
                   AND LOWER(COALESCE(d4.demirbas_adi, '')) NOT LIKE '%hurda%') as kayip_yeni,

                -- Personelden depoya alınan hurda
                (SELECT COALESCE(SUM(h5.miktar), 0)
                 FROM demirbas_hareketler h5
                 INNER JOIN demirbas d5 ON d5.id = h5.demirbas_id
                 WHERE d5.kategori_id IN ($inStr)
                   AND d5.firma_id = ?
                   AND h5.silinme_tarihi IS NULL
                   AND h5.hareket_tipi = 'iade'
                   AND h5.personel_id IS NOT NULL
                   AND (h5.aciklama LIKE '[IADE]%' 
                        OR h5.aciklama LIKE 'Hurda Sayaç İade%' 
                        OR h5.aciklama LIKE '[HURDA_IADE]%' 
                        OR h5.aciklama LIKE '[KASKI_TESLIM]%')) as hurda_depoya_alinan,

                -- Bizim depodan KASKI'ye teslim edilen hurda (personel hariç)
                (SELECT COALESCE(SUM(h6.miktar), 0)
                 FROM demirbas_hareketler h6
                 INNER JOIN demirbas d6 ON d6.id = h6.demirbas_id
                 WHERE d6.kategori_id IN ($inStr)
                   AND d6.firma_id = ?
                   AND h6.silinme_tarihi IS NULL
                   AND h6.hareket_tipi = 'sarf'
                   AND h6.personel_id IS NULL
                   AND (
                        LOWER(COALESCE(h6.aciklama, '')) LIKE '%kask%'
                        OR d6.lokasyon = 'kaski'
                        OR LOWER(COALESCE(d6.durum, '')) = 'kaskiye teslim edildi'
                   )) as hurda_depodan_kaskiye,

                -- Personelden doğrudan KASKI'ye teslim edilen hurda
                (SELECT COALESCE(SUM(h7.miktar), 0)
                 FROM demirbas_hareketler h7
                 INNER JOIN demirbas d7 ON d7.id = h7.demirbas_id
                 WHERE d7.kategori_id IN ($inStr)
                   AND d7.firma_id = ?
                   AND h7.silinme_tarihi IS NULL
                   AND h7.hareket_tipi = 'sarf'
                   AND h7.personel_id IS NOT NULL
                   AND (
                        LOWER(COALESCE(h7.aciklama, '')) LIKE '%kask%'
                        OR d7.lokasyon = 'kaski'
                        OR LOWER(COALESCE(d7.durum, '')) = 'kaskiye teslim edildi'
                   )) as hurda_personelden_kaskiye
        ");

        $sqlStats->execute([$firmaId, $firmaId, $firmaId, $firmaId, $firmaId, $firmaId, $firmaId]);
        $res = $sqlStats->fetch(PDO::FETCH_OBJ);

        $toplamAlinanYeni = max(0, (int)($res->toplam_alinan ?? 0));
        $yPersonelde = max(0, (int)($res->yeni_personelde ?? 0));
        $takilan = max(0, (int)($res->takilan ?? 0));
        $kayipYeni = max(0, (int)($res->kayip_yeni ?? 0));

        $hurdaDepoyaAlinan = max(0, (int)($res->hurda_depoya_alinan ?? 0));
        $hurdaPersoneldenKaskiye = max(0, (int)($res->hurda_personelden_kaskiye ?? 0));
        $hurdaDepodanKaskiye = max(0, (int)($res->hurda_depodan_kaskiye ?? 0));

        // Toplam KASKI teslimatı her iki kanalın (Depo + Personel) toplamıdır
        $kaskiTeslim = $hurdaPersoneldenKaskiye + $hurdaDepodanKaskiye;

        // Akışa göre türetilen KPI'lar
        $yDepoda = max(0, $toplamAlinanYeni - $yPersonelde - $takilan - $kayipYeni);
        // Personelin üzerindeki hurda, taktığı sayaçlardan, depoya getirdikleri ve kaskiye doğrudan verdikleri çıkarılarak bulunur
        $hPersonelde = max(0, $takilan - $hurdaDepoyaAlinan - $hurdaPersoneldenKaskiye);
        // Depodaki hurda ise, personelden depoya gelenlerden kaskiye depodan gidenlerin çıkarılmasıyla bulunur
        $hDepoda = max(0, $hurdaDepoyaAlinan - $hurdaDepodanKaskiye);
        $toplamHurda = $takilan;

        jsonResponse("success", "Başarılı", [
            'yeni_depoda' => $yDepoda,
            'hurda_depoda' => $hDepoda,
            'yeni_personelde' => $yPersonelde,
            'hurda_personelde' => $hPersonelde,
            'toplam_alinan' => $toplamAlinanYeni,
            'toplam_hurda' => $toplamHurda,
            'hurda_kaskiye' => $kaskiTeslim,
            'kayip_yeni' => $kayipYeni,
            'takilan' => $toplamHurda // Takılan hurdadır
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// KASKİ Özet (Toplam Alınan Yeni + Toplam Teslim Edilen Hurda)
if ($action == "sayac-kaski-ozet") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse("success", "Başarılı", [
                'toplam_alinan_yeni' => 0,
                'toplam_teslim_hurda' => 0
            ]);
        }

        $inStr = implode(',', array_map('intval', $catIds));

        $sqlStats = $Demirbas->db->prepare("
            SELECT
                (SELECT COUNT(d1.id)
                 FROM demirbas d1
                 WHERE d1.kategori_id IN ($inStr)
                   AND d1.firma_id = ?
                   AND d1.silinme_tarihi IS NULL
                   AND LOWER(COALESCE(d1.durum, '')) NOT LIKE '%hurda%'
                   AND LOWER(COALESCE(d1.demirbas_adi, '')) NOT LIKE '%manuel iade%') as toplam_alinan,

                (SELECT COALESCE(SUM(h6.miktar), 0)
                 FROM demirbas_hareketler h6
                 INNER JOIN demirbas d4 ON d4.id = h6.demirbas_id
                 WHERE d4.kategori_id IN ($inStr)
                   AND d4.firma_id = ?
                   AND h6.silinme_tarihi IS NULL
                   AND h6.hareket_tipi = 'sarf'
                   AND (
                        LOWER(COALESCE(h6.aciklama, '')) LIKE '%kask%'
                        OR d4.lokasyon = 'kaski'
                        OR LOWER(COALESCE(d4.durum, '')) = 'kaskiye teslim edildi'
                   )) as kaski_teslim

            FROM demirbas d
            WHERE d.kategori_id IN ($inStr) AND d.firma_id = ? AND d.silinme_tarihi IS NULL
        ");

        $sqlStats->execute([$firmaId, $firmaId, $firmaId]);
        $res = $sqlStats->fetch(PDO::FETCH_OBJ);

        $kaskiTeslimCount = (int)$res->kaski_teslim;
        $toplamAlinan = (int)($res->toplam_alinan ?? 0);

        jsonResponse("success", "Başarılı", [
            'toplam_alinan_yeni' => $toplamAlinan,
            'toplam_teslim_hurda' => $kaskiTeslimCount
        ]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

// KASKİ Tarih Bazlı Döküm (DataTable)
if ($action == "sayac-kaski-tarih-list") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 25);
        $search = trim($_POST['search']['value'] ?? '');

        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
            exit;
        }

        $in = buildInClause($catIds);

        $unionSql = "
            SELECT DATE(COALESCE(edinme_tarihi, kayit_tarihi)) as tarih, 'Yeni Sayaç Girişi' as islem_tipi, COUNT(id) as adet, 'gelen' as yon
            FROM demirbas
            WHERE kategori_id IN ($in) AND firma_id = ? AND silinme_tarihi IS NULL
              AND LOWER(COALESCE(durum,'')) NOT LIKE '%hurda%'
              AND LOWER(COALESCE(demirbas_adi,'')) NOT LIKE '%manuel iade%'
            GROUP BY DATE(COALESCE(edinme_tarihi, kayit_tarihi))

            UNION ALL

            SELECT DATE(h.tarih) as tarih, 'KASKİ İşlemi' as islem_tipi, SUM(h.miktar) as adet, 'giden' as yon
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            WHERE d.kategori_id IN ($in) AND d.firma_id = ? AND h.silinme_tarihi IS NULL
                            AND h.hareket_tipi = 'sarf'
                            AND (
                                        LOWER(COALESCE(h.aciklama,'')) LIKE '%kask%'
                                        OR (LOWER(COALESCE(d.durum,'')) = 'kaskiye teslim edildi' AND DATE(d.kaskiye_teslim_tarihi) = DATE(h.tarih))
                                    )
            GROUP BY DATE(h.tarih), islem_tipi, yon
        ";

        $wrapWhere = "";
        $wrapParams = array_merge($catIds, [$firmaId], $catIds, [$firmaId]);
        if ($search !== '') {
            $wrapWhere = " WHERE DATE_FORMAT(sub.tarih, '%d.%m.%Y') LIKE ? OR sub.islem_tipi LIKE ?";
            $wrapParams[] = '%' . $search . '%';
            $wrapParams[] = '%' . $search . '%';
        }

        $countSql = $Demirbas->db->prepare("SELECT COUNT(*) FROM ($unionSql) sub $wrapWhere");
        $countSql->execute($wrapParams);
        $total = (int) $countSql->fetchColumn();

        $dataSql = $Demirbas->db->prepare("SELECT * FROM ($unionSql) sub $wrapWhere ORDER BY sub.tarih DESC LIMIT $length OFFSET $start");
        $dataSql->execute($wrapParams);
        $rows = $dataSql->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($rows as $r) {
            $yonBadge = $r->yon === 'gelen'
                ? '<span class="badge" style="background:#10b981;color:#fff;padding:4px 10px;border-radius:50px;font-weight:600;font-size:11px;"><i class="bx bx-down-arrow-alt me-1"></i>Giriş</span>'
                : '<span class="badge" style="background:#ef4444;color:#fff;padding:4px 10px;border-radius:50px;font-weight:600;font-size:11px;"><i class="bx bx-up-arrow-alt me-1"></i>Çıkış</span>';
            $data[] = [
                'tarih' => Date::dmY($r->tarih),
                'islem_tarih_raw' => $r->tarih,
                'islem_tipi_raw' => $r->islem_tipi,
                'islem_tipi' => $r->islem_tipi,
                'yon' => $yonBadge,
                'adet' => '<span class="fw-bold">' . (int)$r->adet . '</span>'
            ];
        }

        echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data]);
        exit;
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

// Tüm Personel Toplam Özet (Personel Sekmesi Üst KPI)
if ($action == "sayac-personel-all-summary") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse("success", "Başarılı", [
                'toplam_verilen' => 0, 'toplam_takilan' => 0, 'elde_kalan_yeni' => 0,
                'toplam_hurda' => 0, 'toplam_teslim_hurda' => 0, 'elde_kalan_hurda' => 0
            ]);
        }

        $in = buildInClause($catIds);
        $params = array_merge($catIds, [$firmaId]);

        $sql = $Demirbas->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as toplam_verilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0) as toplam_takilan,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elde_kalan_yeni,
                
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0) as toplam_hurda,
                
                COALESCE(SUM(CASE 
                    WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar
                    WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar
                    ELSE 0 END), 0) as toplam_teslim_hurda,
                    
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE 
                    WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar
                    WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar
                    ELSE 0 END), 0) as elde_kalan_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL AND d.kategori_id IN ($in) AND d.firma_id = ? AND h.personel_id IS NOT NULL
              AND NOT (h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%'))
        ");
        $sql->execute($params);
        $r = $sql->fetch(PDO::FETCH_OBJ);

        jsonResponse("success", "Başarılı", [
            'toplam_verilen' => (int)($r->toplam_verilen ?? 0),
            'toplam_takilan' => (int)($r->toplam_takilan ?? 0),
            'elde_kalan_yeni' => max(0, (int)($r->elde_kalan_yeni ?? 0)),
            'toplam_hurda' => max(0, (int)($r->toplam_hurda ?? 0)),
            'toplam_teslim_hurda' => max(0, (int)($r->toplam_teslim_hurda ?? 0)),
            'elde_kalan_hurda' => max(0, (int)($r->elde_kalan_hurda ?? 0))
        ]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

// Sayaç Personel Listesi (Personel Bazlı Gruplanmış)
if ($action == "sayac-personel-list") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $draw = (int) ($_POST['draw'] ?? 1);
        $start = (int) ($_POST['start'] ?? 0);
        $length = (int) ($_POST['length'] ?? 25);
        $search = trim($_POST['search']['value'] ?? '');

        $where = " WHERE h.silinme_tarihi IS NULL 
                     AND h.personel_id IS NOT NULL 
                     AND d.firma_id = ? 
                     AND (LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%')
                     AND NOT (h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%')) ";
        
        $params = [$firmaId];

        if ($search !== '') {
            $where .= " AND p.adi_soyadi LIKE ? ";
            $params[] = '%' . $search . '%';
        }

        // Bireysel Sütun Aramaları
        if (isset($_POST['columns']) && is_array($_POST['columns'])) {
            foreach ($_POST['columns'] as $idx => $col) {
                $searchVal = trim($col['search']['value'] ?? '');
                if ($searchVal !== '') {
                    if ($idx == 1) { // Personel
                         $where .= " AND p.adi_soyadi LIKE ? ";
                         $params[] = '%' . $searchVal . '%';
                    }
                }
            }
        }

        // Toplam benzersiz personel sayısı
        $sqlCount = $Demirbas->db->prepare(" 
            SELECT COUNT(DISTINCT h.personel_id)
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON p.id = h.personel_id
            $where
        ");
        $sqlCount->execute($params);
        $filtered = (int) $sqlCount->fetchColumn();

        // Personel bazlı gruplama
        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.personel_id,
                COALESCE(p.adi_soyadi, CONCAT('Personel #', h.personel_id)) as personel_adi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%'))
                    AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%'
                    THEN h.miktar ELSE 0 END), 0) as aldigi_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%'))
                    AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%')
                    THEN h.miktar ELSE 0 END), 0) as taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%'))
                    AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%'
                    THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%' AND h.aciklama NOT LIKE '[HURDA_IADE]%'))
                    THEN h.miktar ELSE 0 END), 0) as aldigi_hurda,
                    
                COALESCE(SUM(CASE 
                    WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar
                    WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar
                ELSE 0 END), 0) as teslim_hurda,
                
                (COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%' AND h.aciklama NOT LIKE '[HURDA_IADE]%'))
                    THEN h.miktar ELSE 0 END), 0))
                - COALESCE(SUM(CASE 
                    WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar
                    WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar
                ELSE 0 END), 0) as elinde_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON p.id = h.personel_id
            $where
            GROUP BY h.personel_id, p.adi_soyadi
            ORDER BY p.adi_soyadi ASC
            LIMIT ? OFFSET ?
        ");
        
        $bindIdx = 1;
        foreach ($params as $pval) {
            $sql->bindValue($bindIdx++, $pval, is_int($pval) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $sql->bindValue($bindIdx++, $length, PDO::PARAM_INT);
        $sql->bindValue($bindIdx++, $start, PDO::PARAM_INT);
        
        $sql->execute();
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        $i = (int) $start;
        foreach ($rows as $r) {
            $i++;
            $elindeYeni = max(0, (int)$r->elinde_yeni);
            $elindeHurda = max(0, (int)$r->elinde_hurda);
            $data[] = [
                'sira' => $i,
                'personel_id' => (int) $r->personel_id,
                'personel_adi' => '<a href="javascript:void(0);" class="personel-detay-link fw-semibold text-dark" data-personel-id="' . (int)$r->personel_id . '" data-personel-adi="' . htmlspecialchars((string)$r->personel_adi) . '">' . htmlspecialchars((string)$r->personel_adi) . '</a>',
                'aldigi_yeni' => '<span class="fw-bold text-info">' . (int)$r->aldigi_yeni . '</span>',
                'taktigi' => '<span class="fw-bold text-success">' . (int)$r->taktigi . '</span>',
                'elinde_yeni' => ($elindeYeni > 0 ? '<span class="badge bg-warning fw-bold px-2 py-1" style="color: #000 !important;">' . $elindeYeni . '</span>' : '<span class="text-muted">0</span>'),
                'aldigi_hurda' => '<span class="text-danger fw-bold">' . (int)$r->aldigi_hurda . '</span>',
                'teslim_hurda' => '<span class="text-muted">' . (int)$r->teslim_hurda . '</span>',
                'elinde_hurda' => ($elindeHurda > 0 ? '<span class="badge bg-danger fw-bold px-2 py-1 hurda-iade-trigger" style="cursor:pointer" data-personel-id="' . (int)$r->personel_id . '">' . $elindeHurda . '</span>' : '<span class="text-muted">0</span>')
            ];
        }

        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'draw' => (int) $draw,
            'recordsTotal' => (int) $filtered,
            'recordsFiltered' => (int) $filtered,
            'data' => $data
        ]);
        exit;
    } catch (Exception $ex) {
        if (ob_get_length()) ob_clean();
        jsonResponse("error", $ex->getMessage());
    }
}


// Detaylı Günlük Personel İşlemleri (Accordion için)
if ($action == "sayac-personel-daily-details") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $personelId = intval($_POST['personel_id'] ?? 0);
        $date = $_POST['date'] ?? '';

        if ($personelId <= 0 || empty($date)) {
            jsonResponse('error', 'Personel ve Tarih seçimi zorunludur.');
        }

        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse('success', 'Başarılı', ['data' => []]);
        }

        $in = buildInClause($catIds);
        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.tarih,
                h.hareket_tipi,
                h.miktar,
                h.aciklama,
                d.demirbas_adi,
                d.marka,
                d.model,
                d.seri_no,
                d.durum as d_durum
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND DATE(h.tarih) = ?
              AND d.kategori_id IN ($in)
              AND d.firma_id = ?
            ORDER BY h.tarih DESC
        ");
        $sql->execute(array_merge([$personelId, $date], $catIds, [$firmaId]));
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        $durumMap = [
            'aktif' => '<span class="badge" style="background: #10b981; color: #fff; padding: 4px 10px; border-radius: 50px; font-weight: 600; font-size:11px;">Boşta</span>',
            'pasif' => '<span class="badge" style="background: #64748b; color: #fff; padding: 4px 10px; border-radius: 50px; font-weight: 600; font-size:11px;">Pasif</span>',
            'arizali' => '<span class="badge" style="background: #f59e0b; color: #fff; padding: 4px 10px; border-radius: 50px; font-weight: 600; font-size:11px;">Arızalı</span>',
            'hurda' => '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 10px; border-radius: 50px; font-weight: 600; font-size:11px;">Hurda</span>',
            'kaskiye teslim edildi' => '<span class="badge" style="background: #06b6d4; color: #fff; padding: 4px 10px; border-radius: 50px; font-weight: 600; font-size:11px;">Kaskiye Teslim</span>',
        ];

        foreach ($rows as $r) {
            $data[] = [
                'tarih' => date('H:i', strtotime($r->tarih)),
                'tip' => DemirbasHareketModel::getHareketTipiBadge($r->hareket_tipi),
                'miktar' => (int) $r->miktar,
                'demirbas' => htmlspecialchars((string) $r->demirbas_adi),
                'marka_model' => htmlspecialchars((string) ($r->marka . ' ' . $r->model)),
                'seri_no' => htmlspecialchars((string) ($r->seri_no ?? '-')),
                'aciklama' => htmlspecialchars((string) ($r->aciklama ?? '')),
                'durum_badge' => $durumMap[strtolower($r->d_durum ?? 'aktif')] ?? '<span class="badge bg-secondary">' . $r->d_durum . '</span>'
            ];
        }

        jsonResponse('success', 'Başarılı', ['data' => $data]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "sayac-personel-summary") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $personelId = intval($_POST['personel_id'] ?? 0);
        if ($personelId <= 0) {
            jsonResponse('error', 'Personel seçimi zorunludur.');
        }

        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse('success', 'Başarılı', ['summary' => [
                'bizden_toplam_aldigi' => 0,
                'toplam_taktigi' => 0,
                'elinde_kalan_yeni' => 0,
                'toplam_hurda' => 0,
                'teslim_edilen_hurda' => 0,
                'elinde_kalan_hurda' => 0
            ]]);
        }

        $in = buildInClause($catIds);
        $params = array_merge($catIds, [$firmaId, $personelId]);

        $sql = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' 
                    AND LOWER(COALESCE(d.lokasyon, '')) != 'kaski'
                    AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%'
                    THEN h.miktar ELSE 0 END), 0) as bizden_toplam_aldigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%'))
                    THEN h.miktar ELSE 0 END), 0) as toplam_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as teslim_edilen_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%'))
                    AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%'
                    THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_kalan_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE '%KASK%' AND h.aciklama NOT LIKE '[KASKI_TESLIM]%')) THEN h.miktar ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%'))
                    THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%') THEN h.miktar ELSE 0 END), 0) as elinde_kalan_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND d.kategori_id IN ($in)
              AND d.firma_id = ?
              AND NOT (h.hareket_tipi = 'sarf' AND (h.aciklama LIKE '%KASK%' OR h.aciklama LIKE '[KASKI_TESLIM]%'))
        ");
        // personel id başa gelecek şekilde yeniden sırala
        $sql->execute(array_merge([$personelId], $catIds, [$firmaId]));
        $row = $sql->fetch(PDO::FETCH_OBJ) ?: (object) [];

        jsonResponse('success', 'Başarılı', ['summary' => [
            'bizden_toplam_aldigi' => (int) ($row->bizden_toplam_aldigi ?? 0),
            'toplam_taktigi' => (int) ($row->toplam_taktigi ?? 0),
            'elinde_kalan_yeni' => max(0, (int) ($row->elinde_kalan_yeni ?? 0)),
            'toplam_hurda' => max(0, (int) ($row->toplam_hurda ?? 0)),
            'teslim_edilen_hurda' => max(0, (int) ($row->teslim_edilen_hurda ?? 0)),
            'elinde_kalan_hurda' => max(0, (int) ($row->elinde_kalan_hurda ?? 0))
        ]]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "sayac-personel-history") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $personelId = intval($_POST['personel_id'] ?? 0);
        if ($personelId <= 0) {
            jsonResponse('error', 'Personel seçimi zorunludur.');
        }

        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse('success', 'Başarılı', ['rows' => []]);
        }

        $in = buildInClause($catIds);
        $sql = $Demirbas->db->prepare(" 
            SELECT
                DATE(h.tarih) as gun,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' 
                    AND (LOWER(COALESCE(d.lokasyon, '')) != 'kaski' OR d.lokasyon IS NULL)
                    AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%'
                    THEN h.miktar ELSE 0 END), 0) as alinan,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (LOWER(COALESCE(d.lokasyon, '')) != 'kaski' OR d.lokasyon IS NULL) THEN h.miktar ELSE 0 END), 0) as taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND (LOWER(COALESCE(d.lokasyon, '')) != 'kaski' OR d.lokasyon IS NULL) THEN h.miktar ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%' 
                    AND (h.aciklama IS NULL OR (h.aciklama NOT LIKE 'Hurda sayaç personelden%' AND h.aciklama NOT LIKE 'Manuel Hurda İade%'))
                    THEN h.miktar ELSE 0 END), 0) as hurda_alinan,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as hurda_teslim,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as kayip
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND d.kategori_id IN ($in)
              AND d.firma_id = ?
              AND NOT (h.hareket_tipi = 'sarf' AND LOWER(COALESCE(d.lokasyon, '')) = 'kaski')
            GROUP BY DATE(h.tarih)
            ORDER BY DATE(h.tarih) DESC
            LIMIT 180
        ");
        $sql->execute(array_merge([$personelId], $catIds, [$firmaId]));
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        foreach ($rows as $r) {
            $r->gun_format = Date::engtodt($r->gun);
            $r->net = (int) $r->alinan - (int) $r->taktigi - (int) $r->hurda_teslim - (int) $r->kayip;
        }

        jsonResponse('success', 'Başarılı', ['rows' => $rows]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "sayac-hareketler-list") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 25);

        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
            exit;
        }

        $in = buildInClause($catIds);
        $countSql = $Demirbas->db->prepare(" 
            SELECT COUNT(*)
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL AND d.kategori_id IN ($in) AND d.firma_id = ?
        ");
        $countSql->execute(array_merge($catIds, [$firmaId]));
        $total = (int) $countSql->fetchColumn();

        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.tarih,
                h.hareket_tipi,
                h.miktar,
                h.aciklama,
                COALESCE(p.adi_soyadi, '-') as personel_adi,
                COALESCE(d.demirbas_adi, '-') as demirbas_adi
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            LEFT JOIN personel p ON p.id = h.personel_id
            WHERE h.silinme_tarihi IS NULL AND d.kategori_id IN ($in) AND d.firma_id = ?
            ORDER BY h.tarih DESC
            LIMIT ? OFFSET ?
        ");
        $sql->execute(array_merge($catIds, [$firmaId, $length, $start]));
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'tarih' => Date::engtodt(date('Y-m-d', strtotime($r->tarih))),
                'personel' => htmlspecialchars((string) $r->personel_adi),
                'demirbas' => htmlspecialchars((string) $r->demirbas_adi),
                'tip' => DemirbasHareketModel::getHareketTipiBadge($r->hareket_tipi),
                'miktar' => (int) $r->miktar,
                'aciklama' => htmlspecialchars((string) ($r->aciklama ?? ''))
            ];
        }

        echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data]);
        exit;
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "aparat-global-summary") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['aparat']);
        if (empty($catIds)) {
            jsonResponse("success", "Başarılı", ['depoda' => 0, 'personelde' => 0, 'tuketilen' => 0, 'toplam_cesit' => 0]);
        }

        $in = buildInClause($catIds);
        $params = array_merge($catIds, [$firmaId]);

        $sql1 = $Demirbas->db->prepare("SELECT COALESCE(SUM(kalan_miktar),0) as depoda, COUNT(*) as toplam_cesit FROM demirbas WHERE kategori_id IN ($in) AND firma_id = ? AND silinme_tarihi IS NULL");
        $sql1->execute($params);
        $dep = $sql1->fetch(PDO::FETCH_OBJ) ?: (object) ['depoda' => 0, 'toplam_cesit' => 0];

        $sql2 = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END),0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('sarf','kayip') THEN h.miktar ELSE 0 END),0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END),0) as personelde,
                COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('sarf','kayip') THEN h.miktar ELSE 0 END),0) as tuketilen
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL AND d.kategori_id IN ($in) AND d.firma_id = ?
        ");
        $sql2->execute($params);
        $mov = $sql2->fetch(PDO::FETCH_OBJ) ?: (object) ['personelde' => 0, 'tuketilen' => 0];

        jsonResponse("success", "Başarılı", [
            'depoda' => (int) ($dep->depoda ?? 0),
            'personelde' => max(0, (int) ($mov->personelde ?? 0)),
            'tuketilen' => max(0, (int) ($mov->tuketilen ?? 0)),
            'toplam_cesit' => (int) ($dep->toplam_cesit ?? 0)
        ]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "aparat-personel-list") {
    try {
        $_POST['action'] = 'aparat-personel-ozet';
        $personel_id = intval($_POST['personel_id'] ?? 0);
        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 25);

        $whereFilter = $personel_id > 0 ? fn($r) => (int) ($r->personel_id ?? 0) === $personel_id : fn($r) => true;

        $where = " WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%' ";
        $params = [$_SESSION['firma_id']];
        if ($personel_id > 0) {
            $where .= " AND h.personel_id = ? ";
            $params[] = $personel_id;
        }

        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.personel_id,
                p.adi_soyadi as personel_adi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as toplam_verilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as toplam_tuketilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as toplam_depo_iade,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as toplam_kayip,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('sarf', 'kayip') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as kalan_miktar
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON h.personel_id = p.id
            $where
            GROUP BY h.personel_id, p.adi_soyadi
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute($params);
        $allRows = array_values(array_filter($sql->fetchAll(PDO::FETCH_OBJ), $whereFilter));

        $total = count($allRows);
        $rows = array_slice($allRows, $start, $length);
        $data = [];
        $i = $start;
        foreach ($rows as $r) {
            $i++;
            $data[] = [
                'sira' => $i,
                'personel_id' => (int) $r->personel_id,
                'personel_adi' => htmlspecialchars((string) ($r->personel_adi ?? ('Personel #' . $r->personel_id))),
                'toplam_verilen' => (int) ($r->toplam_verilen ?? 0),
                'toplam_tuketilen' => (int) ($r->toplam_tuketilen ?? 0),
                'toplam_depo_iade' => (int) ($r->toplam_depo_iade ?? 0),
                'toplam_kayip' => (int) ($r->toplam_kayip ?? 0),
                'kalan_miktar' => max(0, (int) ($r->kalan_miktar ?? 0))
            ];
        }

        echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data]);
        exit;
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}


if ($action == "aparat-personel-summary") {
    try {
        $_POST['personel_id'] = intval($_POST['personel_id'] ?? 0);
        $personel_id = intval($_POST['personel_id']);
        if ($personel_id <= 0) {
            jsonResponse('error', 'Personel seçimi zorunludur.');
        }

        $where = " WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%' AND h.personel_id = ? ";
        $sql = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as toplam_verilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as toplam_tuketilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as toplam_depo_iade,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as toplam_kayip,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('sarf', 'kayip') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as kalan_miktar
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            $where
        ");
        $sql->execute([$_SESSION['firma_id'], $personel_id]);
        $r = $sql->fetch(PDO::FETCH_OBJ) ?: (object) [];

        jsonResponse('success', 'Başarılı', ['summary' => [
            'toplam_verilen' => (int) ($r->toplam_verilen ?? 0),
            'toplam_tuketilen' => (int) ($r->toplam_tuketilen ?? 0),
            'toplam_depo_iade' => (int) ($r->toplam_depo_iade ?? 0),
            'toplam_kayip' => (int) ($r->toplam_kayip ?? 0),
            'kalan_miktar' => max(0, (int) ($r->kalan_miktar ?? 0))
        ]]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "aparat-personel-history") {
    try {
        $personelId = intval($_POST['personel_id'] ?? 0);
        if ($personelId <= 0) {
            jsonResponse('error', 'Personel seçimi zorunludur.');
        }

        $sql = $Demirbas->db->prepare(" 
            SELECT
                DATE(h.tarih) as gun,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as verilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as tuketilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as depo_iade,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as kayip
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND d.firma_id = ?
              AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%'
            GROUP BY DATE(h.tarih)
            ORDER BY DATE(h.tarih) DESC
            LIMIT 180
        ");
        $sql->execute([$personelId, $_SESSION['firma_id']]);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $r) {
            $r->gun_format = Date::engtodt($r->gun);
            $r->net = (int) $r->verilen - (int) $r->tuketilen - (int) $r->depo_iade - (int) $r->kayip;
        }

        jsonResponse('success', 'Başarılı', ['rows' => $rows]);
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}

if ($action == "aparat-hareketler-list") {
    try {
        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 25);
        $status_filter = $_POST['status_filter'] ?? '';

        $whereSearch = "";
        $whereParams = [$_SESSION['firma_id']];
        if (!empty($status_filter)) {
            $whereSearch .= " AND h.hareket_tipi = ? ";
            $whereParams[] = $status_filter;
        }

        $sqlCount = $Demirbas->db->prepare(" 
            SELECT COUNT(*)
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE h.silinme_tarihi IS NULL AND d.firma_id = ? AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%' $whereSearch
        ");
        $sqlCount->execute($whereParams);
        $total = (int) $sqlCount->fetchColumn();

        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.tarih,
                h.hareket_tipi,
                h.miktar,
                h.aciklama,
                COALESCE(p.adi_soyadi, '-') as personel_adi,
                COALESCE(d.demirbas_adi, '-') as demirbas_adi
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON p.id = h.personel_id
            WHERE h.silinme_tarihi IS NULL AND d.firma_id = ? AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%' $whereSearch
            ORDER BY h.tarih DESC
            LIMIT ? OFFSET ?
        ");
        $sqlParams = array_merge($whereParams, [$length, $start]);
        $sql->execute($sqlParams);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'tarih' => Date::engtodt(date('Y-m-d', strtotime($r->tarih))),
                'personel' => htmlspecialchars((string) $r->personel_adi),
                'demirbas' => htmlspecialchars((string) $r->demirbas_adi),
                'tip' => DemirbasHareketModel::getHareketTipiBadge($r->hareket_tipi),
                'miktar' => (int) $r->miktar,
                'aciklama' => htmlspecialchars((string) ($r->aciklama ?? ''))
            ];
        }

        echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data]);
        exit;
    } catch (Exception $ex) {
        jsonResponse('error', $ex->getMessage());
    }
}
// ============== SERVİS KAYDI İŞLEMLERİ ==============

if ($action == "servis-listesi") {
    $baslangic = Date::dttoeng($_POST['baslangic'] ?? date('01.m.Y'));
    $bitis = Date::dttoeng($_POST['bitis'] ?? date('t.m.Y'));
    $status_filter = $_POST['status_filter'] ?? 'all';

    $kayitlar = $Servis->getByDateRange($baslangic, $bitis, null, $status_filter);
    $data = [];

    $i = 0;
    foreach ($kayitlar as $row) {
        $i++;
        $data[] = [
            "sira" => $i,
            "enc_id" => Security::encrypt($row->id),
            "demirbas_adi" => '<b>' . ($row->demirbas_adi ?? 'Silinmiş Demirbaş') . '</b><br><small class="text-muted">' . ($row->demirbas_no ?? '-') . '</small>',
            "servis_tarihi" => Date::engtodt($row->servis_tarihi),
            "iade_tarihi" => $row->iade_tarihi ? Date::engtodt($row->iade_tarihi) : '<span class="badge bg-soft-warning text-warning">Serviste</span>',
            "servis_adi" => $row->servis_adi ?? '-',
            "teslim_eden" => $row->teslim_eden_adi ?? '-',
            "islem_detay" => '<b>' . ($row->servis_nedeni ?? '-') . '</b><br><small>' . ($row->yapilan_islemler ?? '-') . '</small>',
            "tutar" => Helper::formattedMoney($row->tutar) . ' ₺',
            "islemler" => '
                <div class="btn-group">
                    <button class="btn btn-soft-primary btn-sm servis-duzenle" data-id="' . Security::encrypt($row->id) . '">
                        <i class="bx bx-edit"></i>
                    </button>
                    <button class="btn btn-soft-danger btn-sm servis-sil" data-id="' . Security::encrypt($row->id) . '">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>'
        ];
    }

    $stats = $Servis->getStats($baslangic, $bitis);

    echo json_encode([
        "draw" => intval($_POST['draw'] ?? 1),
        "recordsTotal" => count($data),
        "recordsFiltered" => count($data),
        "data" => $data,
        "stats" => [
            "toplam_kayit" => $stats->toplam_kayit ?? 0,
            "aktif_sayisi" => $stats->servisteki_sayisi ?? 0,
            "toplam_maliyet" => Helper::formattedMoney($stats->toplam_maliyet ?? 0) . ' ₺'
        ]
    ]);
    exit;
}

if ($action == "servis-detay") {
    $id = Security::decrypt($_POST['id']);
    $kayit = $Servis->find($id);

    if ($kayit) {
        $demirbas = $Demirbas->find($kayit->demirbas_id);
        $kayit->demirbas_adi = $demirbas->demirbas_adi;
        $kayit->demirbas_no = $demirbas->demirbas_no;
        $kayit->servis_tarihi_formatted = Date::engtodt($kayit->servis_tarihi);
        $kayit->iade_tarihi_formatted = $kayit->iade_tarihi ? Date::engtodt($kayit->iade_tarihi) : '';
        $kayit->tutar = Helper::formattedMoney($kayit->tutar);

        jsonResponse("success", "Veri getirildi", ["data" => $kayit]);
    } else {
        jsonResponse("error", "Kayıt bulunamadı");
    }
}

if ($action == "servis-kaydet") {
    $id = Security::decrypt($_POST['id'] ?? '');

    $data = [
        "id" => $id ?: 0,
        "firma_id" => $_SESSION['firma_id'],
        "demirbas_id" => $_POST['demirbas_id'],
        "teslim_eden_personel_id" => $_POST['teslim_eden_personel_id'] ?? null,
        "servis_tarihi" => Date::dttoeng($_POST['servis_tarihi']),
        "iade_tarihi" => !empty($_POST['iade_tarihi']) ? Date::dttoeng($_POST['iade_tarihi']) : null,
        "servis_adi" => $_POST['servis_adi'] ?? null,
        "servis_nedeni" => $_POST['servis_nedeni'] ?? null,
        "yapilan_islemler" => $_POST['yapilan_islemler'] ?? null,
        "tutar" => Helper::formattedMoneyToNumber($_POST['tutar'] ?? 0),
        "fatura_no" => $_POST['fatura_no'] ?? null,
        "olusturan_kullanici_id" => $_SESSION['id'] ?? null
    ];

    try {
        $Servis->saveWithAttr($data);

        // Demirbaş durumunu güncelle
        // Eğer iade tarihi boşsa 'serviste', doluysa 'aktif' yap
        $new_status = empty($data['iade_tarihi']) ? 'serviste' : 'aktif';

        $Demirbas->saveWithAttr([
            "id" => $data['demirbas_id'],
            "durum" => $new_status
        ]);

        jsonResponse("success", "Servis kaydı başarıyla kaydedildi.");
    } catch (Exception $e) {
        jsonResponse("error", "Kaydedilemedi: " . $e->getMessage());
    }
}

if ($action == "servis-sil") {
    $id = Security::decrypt($_POST['id']);
    try {
        // Silmeden önce demirbaş ID'sini al
        $kayit = $Servis->find($id);
        if ($kayit) {
            $demirbas_id = $kayit->demirbas_id;
            $Servis->softDelete($id);

            // Eğer silinen kayıt aktif bir servis kaydıysa (iade edilmemişse), demirbaşı 'aktif'e çek
            if (empty($kayit->iade_tarihi)) {
                $Demirbas->saveWithAttr([
                    "id" => $demirbas_id,
                    "durum" => 'aktif'
                ]);
            }
        }

        jsonResponse("success", "Kayıt silindi.");
    } catch (Exception $e) {
        jsonResponse("error", "Silinemedi: " . $e->getMessage());
    }
}

// Zimmet grafik istatistikleri
if ($action == "zimmet-stats-chart") {
    try {
        $personel_id = intval($_POST["personel_id"] ?? 0);
        $where = " WHERE d.firma_id = ? ";
        $params = [$_SESSION['firma_id']];
        if ($personel_id > 0) {
            $where .= " AND z.personel_id = ? ";
            $params[] = $personel_id;
        }

        // Kategori Bazlı Dağılım
        $sqlKat = $Demirbas->db->prepare("
            SELECT COALESCE(k.tur_adi, 'Kategorisiz') as label, COUNT(*) as value
            FROM demirbas_zimmet z
            INNER JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            $where
            GROUP BY COALESCE(k.tur_adi, 'Kategorisiz')
        ");
        $sqlKat->execute($params);
        $katData = $sqlKat->fetchAll(PDO::FETCH_OBJ);

        // Durum Bazlı Dağılım
        $sqlDurum = $Demirbas->db->prepare("
            SELECT z.durum as label, COUNT(*) as value
            FROM demirbas_zimmet z
            INNER JOIN demirbas d ON z.demirbas_id = d.id
            $where
            GROUP BY z.durum
        ");
        $sqlDurum->execute($params);
        $durumData = $sqlDurum->fetchAll(PDO::FETCH_OBJ);

        // Durum label'larını türkçeleştir
        $durumMap = [
            'teslim' => 'Zimmetli',
            'iade' => 'İade Edildi',
            'kayip' => 'Kayıp',
            'arizali' => 'Arızalı'
        ];
        $durumDataFormatted = [];
        foreach ($durumData as $d) {
            $durumDataFormatted[] = [
                'label' => $durumMap[strtolower($d->label)] ?? $d->label,
                'value' => intval($d->value)
            ];
        }

        jsonResponse("success", "Başarılı", [
            "katData" => $katData,
            "durumData" => $durumDataFormatted
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Aparat personel özetleri (toplam verilen / tüketilen / iade alınan / kalan)
if ($action == "aparat-personel-ozet") {
    try {
        $personel_id = intval($_POST["personel_id"] ?? 0);

        $where = " WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL AND LOWER(COALESCE(k.tur_adi, '')) LIKE '%aparat%' ";
        $params = [$_SESSION['firma_id']];

        if ($personel_id > 0) {
            $where .= " AND h.personel_id = ? ";
            $params[] = $personel_id;
        }

        $sql = $Demirbas->db->prepare(" 
            SELECT 
                h.personel_id,
                p.adi_soyadi as personel_adi,
                COUNT(*) as islem_sayisi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as toplam_verilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as toplam_tuketilen,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as toplam_iade,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as toplam_depo_iade,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as toplam_kayip,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN h.hareket_tipi IN ('sarf', 'kayip') THEN h.miktar ELSE 0 END), 0) as kalan_miktar
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON h.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON h.personel_id = p.id
            $where
            GROUP BY h.personel_id, p.adi_soyadi
            HAVING toplam_verilen > 0 OR toplam_tuketilen > 0 OR toplam_iade > 0 OR kalan_miktar != 0
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute($params);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $totals = [
            'islem_sayisi' => 0,
            'toplam_verilen' => 0,
            'toplam_tuketilen' => 0,
            'toplam_iade' => 0,
            'toplam_depo_iade' => 0,
            'kalan_miktar' => 0
        ];

        foreach ($rows as $r) {
            $totals['islem_sayisi'] += (int) ($r->islem_sayisi ?? 0);
            $totals['toplam_verilen'] += (int) ($r->toplam_verilen ?? 0);
            $totals['toplam_tuketilen'] += (int) ($r->toplam_tuketilen ?? 0);
            $totals['toplam_iade'] += (int) ($r->toplam_iade ?? 0);
            $totals['toplam_depo_iade'] += (int) ($r->toplam_depo_iade ?? 0);
            $totals['kalan_miktar'] += (int) ($r->kalan_miktar ?? 0);
        }

        jsonResponse("success", "Başarılı", [
            'rows' => $rows,
            'totals' => $totals
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Gelişmiş filtreler için benzersiz değerleri getir
if ($action == "get-unique-values") {
    $column = $_POST['column'] ?? '';
    if (empty($column))
        jsonResponse("error", "Column missing");

    try {
        $firma_id = $_SESSION['firma_id'];
        $tab = $_POST['tab'] ?? '';
        $rows = [];

        if ($column === 'durum') {
            if ($tab === 'sayac' || $tab === 'aparat' || $tab === 'demirbas') {
                $sql = "SELECT DISTINCT durum as val FROM demirbas WHERE silinme_tarihi IS NULL AND firma_id = ? ORDER BY val ASC";
            } else {
                $sql = "SELECT DISTINCT z.durum as val FROM demirbas_zimmet z 
                        LEFT JOIN demirbas d ON z.demirbas_id = d.id 
                        WHERE z.silinme_tarihi IS NULL AND d.firma_id = ? ORDER BY val ASC";
            }
            $stmt = $Demirbas->db->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'kategori_adi') {
            $sql = "SELECT DISTINCT k.tur_adi as val FROM demirbas d
                    INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                    WHERE d.firma_id = ? ORDER BY val ASC";
            $stmt = $Demirbas->db->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'personel_adi') {
            $sql = "SELECT DISTINCT p.adi_soyadi as val FROM demirbas_zimmet z
                    INNER JOIN personel p ON z.personel_id = p.id
                    LEFT JOIN demirbas d ON z.demirbas_id = d.id
                    WHERE z.silinme_tarihi IS NULL AND d.firma_id = ? ORDER BY val ASC";
            $stmt = $Demirbas->db->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'marka_model' || $column === 'marka_sade') {
            $sql = "SELECT DISTINCT d.marka as val FROM demirbas d 
                    WHERE d.firma_id = ? AND d.marka IS NOT NULL AND d.marka != '' ORDER BY val ASC";
            $stmt = $Demirbas->db->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            jsonResponse("error", "Invalid column: " . $column);
        }

        $results = [];
        $isSayacTab = ($tab === 'sayac');

        $durumMap = [
            'aktif' => ($isSayacTab ? 'Yeni' : 'Boşta'),
            'pasif' => 'Pasif',
            'arizali' => 'Arızalı',
            'hurda' => 'Hurda',
            'personelde' => 'Personelde',
            'kaskiye teslim edildi' => 'KASKİ\'ye İade Edildi',
            // Zimmet tablosu için
            'teslim' => 'Zimmetli',
            'iade' => 'İade Edildi',
            'kayip' => 'Kayıp'
        ];

        foreach ($rows as $r) {
            $val = $r['val'] ?? '(Boş)';
            if ($column === 'durum') {
                $results[] = $durumMap[strtolower($val)] ?? $val;
            } else {
                $results[] = $val;
            }
        }

        jsonResponse("success", "OK", ["data" => array_values(array_unique($results))]);
    } catch (Exception $e) {
        jsonResponse("error", $e->getMessage());
    }
}
