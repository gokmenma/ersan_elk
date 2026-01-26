<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\DemirbasModel;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasKategoriModel;

$Demirbas = new DemirbasModel();
$Zimmet = new DemirbasZimmetModel();
$Kategori = new DemirbasKategoriModel();

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

// ============== DEMİRBAŞ İŞLEMLERİ ==============

// Demirbaş Kaydet/Güncelle
if ($action == "demirbas-kaydet") {
    $id = Security::decrypt($_POST["demirbas_id"]);

    try {
        $miktar = intval($_POST["miktar"] ?? 1);

        $data = [
            "id" => $id,
            "demirbas_no" => $_POST["demirbas_no"] ?? null,
            "kategori_id" => !empty($_POST["kategori_id"]) ? $_POST["kategori_id"] : null,
            "demirbas_adi" => $_POST["demirbas_adi"],
            "marka" => $_POST["marka"] ?? null,
            "model" => $_POST["model"] ?? null,
            "seri_no" => $_POST["seri_no"] ?? null,
            "edinme_tarihi" => $_POST["edinme_tarihi"] ?? null,
            "edinme_tutari" => Helper::formattedMoneyToNumber($_POST["edinme_tutari"] ?? 0),
            "miktar" => $miktar,
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
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

// Demirbaş bilgilerini getir
if ($action == "demirbas-getir") {
    $id = Security::decrypt($_POST["demirbas_id"]);

    try {
        $data = $Demirbas->find($id);
        if ($data) {
            jsonResponse("success", "Başarılı", ["data" => $data]);
        } else {
            jsonResponse("error", "Demirbaş bulunamadı.");
        }
    } catch (PDOException $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Demirbaş Sil
if ($action == "demirbas-sil") {
    $id = $_POST["id"] ?? null;

    try {
        // Önce zimmet kontrolü
        $zimmetler = $Zimmet->getByDemirbas(Security::decrypt($id));
        $aktifZimmet = array_filter($zimmetler, fn($z) => $z->durum === 'teslim');

        if (count($aktifZimmet) > 0) {
            jsonResponse("error", "Bu demirbaşın aktif zimmet kaydı bulunmaktadır. Önce zimmetleri iade alın.");
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

// ============== ZİMMET İŞLEMLERİ ==============

// Zimmet listesini getir
if ($action == "zimmet-listesi") {
    try {
        $zimmetler = $Zimmet->getAllWithDetails();
        $rows = [];

        foreach ($zimmetler as $z) {
            $enc_id = Security::encrypt($z->id);
            $teslimTarihi = date('d.m.Y', strtotime($z->teslim_tarihi));

            // Durum badge
            $durumBadges = [
                'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                'iade' => '<span class="badge bg-success">İade Edildi</span>',
                'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
            ];
            $durumBadge = $durumBadges[$z->durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';

            $iadeButton = $z->durum === 'teslim' ?
                '<a href="#" data-id="' . $enc_id . '" data-demirbas="' . htmlspecialchars($z->demirbas_adi) . '" data-personel="' . htmlspecialchars($z->personel_adi) . '" data-miktar="' . $z->teslim_miktar . '" class="dropdown-item zimmet-iade">
                    <span class="mdi mdi-undo font-size-18 text-success me-1"></span> İade Al
                </a>' : '';

            $rows[] = '<tr data-id="' . $enc_id . '">
                <td class="text-center">' . $z->id . '</td>
                <td><span class="badge bg-soft-primary text-primary">' . ($z->kategori_adi ?? '-') . '</span></td>
                <td>' . ($z->demirbas_adi ?? '-') . '</td>
                <td>' . ($z->marka ?? '-') . ' ' . ($z->model ?? '') . '</td>
                <td>' . ($z->personel_adi ?? '-') . '</td>
                <td class="text-center">' . $z->teslim_miktar . '</td>
                <td>' . $teslimTarihi . '</td>
                <td class="text-center">' . $durumBadge . '</td>
                <td class="text-center">
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            ' . $iadeButton . '
                            <a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-detay">
                                <span class="mdi mdi-eye font-size-18 text-info me-1"></span> Detay
                            </a>
                            <a href="#" class="dropdown-item zimmet-sil" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete font-size-18 text-danger me-1"></span> Sil
                            </a>
                        </div>
                    </div>
                </td>
            </tr>';
        }

        jsonResponse("success", "Başarılı", ["rows" => $rows]);
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

// Zimmet İade
if ($action == "zimmet-iade") {
    $zimmet_id = Security::decrypt($_POST["zimmet_id"]);

    try {
        $iade_tarihi = $_POST["iade_tarihi"];
        $iade_miktar = intval($_POST["iade_miktar"] ?? 1);
        $aciklama = $_POST["iade_aciklama"] ?? null;

        $result = $Zimmet->iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama);

        if ($result) {
            jsonResponse("success", "İade işlemi başarıyla tamamlandı. Stok güncellendi.");
        } else {
            jsonResponse("error", "İade işlemi başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
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

        // Eğer teslim durumundaysa, stoku geri ekle
        if ($zimmet->durum === 'teslim') {
            $db = $Demirbas->getDb();
            $sql = $db->prepare("UPDATE demirbas SET kalan_miktar = kalan_miktar + ? WHERE id = ?");
            $sql->execute([$zimmet->teslim_miktar, $zimmet->demirbas_id]);
        }

        $result = $Zimmet->delete($id);
        if ($result === true) {
            jsonResponse("success", "Zimmet kaydı başarıyla silindi.");
        } else {
            jsonResponse("error", "Silme işlemi başarısız.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Zimmet Detay
if ($action == "zimmet-detay") {
    $id = Security::decrypt($_POST["id"] ?? $_GET["id"]);

    try {
        $zimmet = $Zimmet->find($id);
        if ($zimmet) {
            // Demirbaşın tüm geçmişini getir
            $gecmis = $Zimmet->getByDemirbas($zimmet->demirbas_id);

            // Geçmiş verilerini formatla
            foreach ($gecmis as $g) {
                $g->teslim_tarihi_format = date('d.m.Y', strtotime($g->teslim_tarihi));
                $g->iade_tarihi_format = $g->iade_tarihi ? date('d.m.Y', strtotime($g->iade_tarihi)) : '-';

                $durumBadges = [
                    'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                    'iade' => '<span class="badge bg-success">İade Edildi</span>',
                    'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                    'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
                ];
                $g->durum_badge = $durumBadges[$g->durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
            }

            // Şu anki zimmet detaylarını da zenginleştir
            $zimmet->teslim_tarihi_format = date('d.m.Y', strtotime($zimmet->teslim_tarihi));
            $zimmet->durum_badge = $durumBadges[$zimmet->durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';

            // Demirbaş bilgilerini al
            $demirbas = $Demirbas->find($zimmet->demirbas_id);
            $zimmet->demirbas_detay = $demirbas;

            // Personel bilgisini al
            $personel = $Zimmet->getDb()->query("SELECT * FROM personel WHERE id = {$zimmet->personel_id}")->fetch(PDO::FETCH_OBJ);
            $zimmet->personel_detay = $personel;

            jsonResponse("success", "Başarılı", [
                "data" => $zimmet,
                "gecmis" => $gecmis
            ]);
        } else {
            jsonResponse("error", "Zimmet bulunamadı.");
        }
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// ============== ARAMA İŞLEMLERİ ==============

// Select2 için demirbaş arama
if ($action == "demirbas-ara") {
    $search = $_GET["q"] ?? $_POST["q"] ?? "";

    try {
        $results = $Demirbas->getForSelect($search);
        echo json_encode(["results" => $results]);
    } catch (Exception $ex) {
        echo json_encode(["results" => []]);
    }
    exit;
}

// Kategori listesi
if ($action == "kategori-listesi") {
    try {
        $kategoriler = $Kategori->getActiveCategories();
        jsonResponse("success", "Başarılı", ["data" => $kategoriler]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}