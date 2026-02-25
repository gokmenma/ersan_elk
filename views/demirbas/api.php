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

$Demirbas = new DemirbasModel();
$Servis = new DemirbasServisModel();
$Zimmet = new DemirbasZimmetModel();
$Tanimlamalar = new TanimlamalarModel();
$Hareket = new DemirbasHareketModel();


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
            "minimun_stok_uyari_miktari" => intval($_POST["minimun_stok_uyari_miktari"] ?? 0),
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
            "otomatik_zimmet_is_emri" => !empty($_POST["otomatik_zimmet_is_emri"]) ? $_POST["otomatik_zimmet_is_emri"] : null,
            "otomatik_iade_is_emri" => !empty($_POST["otomatik_iade_is_emri"]) ? $_POST["otomatik_iade_is_emri"] : null,
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
            "demirbas_no" => $_POST["demirbas_no"] ?? null,
            "kategori_id" => !empty($_POST["kategori_id"]) ? $_POST["kategori_id"] : null,
            "demirbas_adi" => $_POST["demirbas_adi"],
            "marka" => $_POST["marka"] ?? null,
            "model" => $_POST["model"] ?? null,
            "edinme_tarihi" => $_POST["edinme_tarihi"] ?? null,
            "edinme_tutari" => Helper::formattedMoneyToNumber($_POST["edinme_tutari"] ?? 0),
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
            "otomatik_zimmet_is_emri" => !empty($_POST["otomatik_zimmet_is_emri"]) ? $_POST["otomatik_zimmet_is_emri"] : null,
            "otomatik_iade_is_emri" => !empty($_POST["otomatik_iade_is_emri"]) ? $_POST["otomatik_iade_is_emri"] : null,
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
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        $aciklama = $_POST["aciklama"] ?? null;

        if ($demirbas_id <= 0 || empty($tarih)) {
            jsonResponse("error", "Geçersiz parametreler. Lütfen formu eksiksiz doldurun.");
        }

        // Demirbaşı bul
        $demirbas = $Demirbas->find($demirbas_id);
        if (!$demirbas) {
            jsonResponse("error", "Demirbaş bulunamadı.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');

        // Durumu güncelle, stoğu sıfırla
        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
        $sqlUpdate->execute([$formatted_tarih, $teslim_eden, ($aciklama ?? null), $demirbas_id]);

        // Hareket kaydı oluştur (audit trail)
        try {
            $Hareket->hareketEkle([
                'demirbas_id' => $demirbas_id,
                'personel_id' => $_SESSION["id"] ?? 1,
                'hareket_tipi' => 'sarf',
                'miktar' => $demirbas->kalan_miktar > 0 ? $demirbas->kalan_miktar : 1,
                'tarih' => $formatted_tarih,
                'aciklama' => 'Kaskiye teslim edildi. Teslim eden: ' . $teslim_eden . '. Not: ' . ($aciklama ?? ''),
                'islem_yapan_id' => $_SESSION["id"] ?? null,
                'kaynak' => 'manuel',
            ]);
        } catch (Exception $e) {
            // Hareket kaydı hata verse bile ana işlemi etkilemesin
        }

        jsonResponse("success", "Sayaç başarıyla Kaskiye teslim edildi. Durum güncellendi.");
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

// ============== ZİMMET İŞLEMLERİ ==============

// Zimmet listesini getir
if ($action == "zimmet-listesi") {
    try {
        $result = $Zimmet->getDatatableList($_POST);

        $data = [];
        foreach ($result['data'] as $z) {
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

            $actions = '<div class="dropdown">
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
                        </div>';

            $data[] = [
                "id" => $z->id,
                "enc_id" => $enc_id,
                "kategori_adi" => '<span class="badge bg-soft-primary text-primary">' . ($z->kategori_adi ?? '-') . '</span>',
                "demirbas_adi" => ($z->demirbas_adi ?? '-'),
                "marka_model" => '<div>' . ($z->marka ?? '-') . ' ' . ($z->model ?? '') . '</div>' . ($z->seri_no ? '<small class="text-muted">SN: ' . $z->seri_no . '</small>' : ''),
                "personel_adi" => ($z->personel_adi ?? '-'),
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
            jsonResponse("error", "İade işlemi başarıısız.");
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

        // Eğer teslim durumundaysa, silmeye izin verme (iade alınmalı)
        if ($zimmet->durum === 'teslim') {
            jsonResponse("error", "Aktif (teslim edilmiş) durumdaki bir zimmet kaydını silemezsiniz. Lütfen önce 'İade Al' işlemini gerçekleştiriniz.");
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
            // Hareket tablosundan personel genel bakiyesini al
            $bakiye = $Hareket->getPersonelDemirbasBakiye($zimmet->personel_id, $zimmet->demirbas_id);

            // Bu zimmet kaydına ait özel hareket geçmişini al
            $hareketler = $Hareket->getZimmetHareketleri($id);

            // Hareket verilerini formatla
            foreach ($hareketler as $h) {
                $h->tarih_format = date('d.m.Y', strtotime($h->tarih));
                $h->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi);
                $h->kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);
            }


            // Sadece seçili zimmet kaydına ait geçmişi getir (aynı personel + aynı demirbaş) - eski tablo
            $gecmisSql = $Zimmet->getDb()->prepare("
                SELECT 
                    z.*,
                    p.adi_soyadi AS personel_adi,
                    p.cep_telefonu AS personel_telefon
                FROM demirbas_zimmet z
                LEFT JOIN personel p ON z.personel_id = p.id
                WHERE z.demirbas_id = ? AND z.personel_id = ?
                ORDER BY z.teslim_tarihi DESC
            ");
            $gecmisSql->execute([$zimmet->demirbas_id, $zimmet->personel_id]);
            $gecmis = $gecmisSql->fetchAll(PDO::FETCH_OBJ);

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

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (empty($row[1]))
                continue; // Demirbaş adı boşsa atla

            try {
                $data = [
                    "id" => 0,
                    "demirbas_no" => $row[0] ?? null,
                    "demirbas_adi" => $row[1],
                    "kategori_id" => null,
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

                if (!empty($row[8])) {
                    $katAdi = trim($row[8]);
                    $kat = $Tanimlamalar->getDb()->prepare("SELECT id FROM tanimlamalar WHERE tur_adi = ? AND grup = 'demirbas_kategorisi' AND firma_id = ?");
                    $kat->execute([$katAdi, $_SESSION['firma_id']]);
                    $katRes = $kat->fetch(PDO::FETCH_OBJ);
                    if ($katRes) {
                        $data["kategori_id"] = $katRes->id;
                    } else {
                        $insKat = $Tanimlamalar->getDb()->prepare("INSERT INTO tanimlamalar (tur_adi, grup, firma_id, kayit_yapan) VALUES (?, 'demirbas_kategorisi', ?, ?)");
                        $insKat->execute([$katAdi, $_SESSION['firma_id'], $_SESSION['id'] ?? 0]);
                        $data["kategori_id"] = $Tanimlamalar->getDb()->lastInsertId();
                    }
                }

                $Demirbas->saveWithAttr($data);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Satır " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        $message = "$successCount adet demirbaş başarıyla yüklendi.";
        if ($errorCount > 0) {
            $message .= " $errorCount hata oluştu.";
        }

        jsonResponse("success", $message, ["errors" => $errors]);
    } catch (Exception $ex) {
        jsonResponse("error", "Hata: " . $ex->getMessage());
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
            // Demirbaş bazlı
            $hareketler = $Hareket->getDemirbasHareketleri($demirbas_id);

            foreach ($hareketler as $h) {
                $h->tarih_format = date('d.m.Y', strtotime($h->tarih));
                $h->hareket_badge = DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi);
                $h->kaynak_badge = DemirbasHareketModel::getKaynakBadge($h->kaynak);
            }

            jsonResponse("success", "Başarılı", ["hareketler" => $hareketler]);
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
// ============== SERVİS KAYDI İŞLEMLERİ ==============

if ($action == "servis-listesi") {
    $baslangic = Date::dttoeng($_POST['baslangic'] ?? date('01.m.Y'));
    $bitis = Date::dttoeng($_POST['bitis'] ?? date('t.m.Y'));

    $kayitlar = $Servis->getByDateRange($baslangic, $bitis);
    $data = [];

    $i = 0;
    foreach ($kayitlar as $row) {
        $i++;
        $data[] = [
            "sira" => $i,
            "enc_id" => Security::encrypt($row->id),
            "demirbas_adi" => '<b>' . $row->demirbas_adi . '</b><br><small class="text-muted">' . ($row->demirbas_no ?? '-') . '</small>',
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
    $id = Security::decrypt($_POST['servis_id'] ?? '');

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
