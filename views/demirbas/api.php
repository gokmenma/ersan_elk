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
            "firma_id" => $_SESSION['firma_id'] ?? 0,
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
            "firma_id" => $_SESSION['firma_id'] ?? 0,
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

// Toplu Demirbaş Sil
if ($action == "bulk-demirbas-sil") {
    Gate::can('demirbas_toplu_islem_sil');
    $ids = $_POST["ids"] ?? [];
    if (empty($ids)) {
        jsonResponse("error", "Lütfen silmek için en az bir kayıt seçin.");
    }

    try {
        $successCount = 0;
        $errorCount = 0;
        $hatalar = [];

        $Demirbas->db->beginTransaction();

        foreach ($ids as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id) continue;

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
                $miktar = $d->miktar ?? 1;
                $kalan = $d->kalan_miktar ?? 1;
                $minStok = $d->minimun_stok_uyari_miktari ?? 0;

                // Stok durumu badge
                if ($kalan == 0) {
                    $stokBadge = '<span class="badge bg-danger">Stok Yok</span>';
                } elseif ($minStok > 0 && $kalan <= $minStok) {
                    $stokBadge = '<span class="badge bg-soft-danger text-danger border border-danger">Stok Azaldı (' . $kalan . '/' . $miktar . ')</span>';
                } elseif ($kalan < $miktar) {
                    $stokBadge = '<span class="badge bg-warning">' . $kalan . '/' . $miktar . '</span>';
                } else {
                    $stokBadge = '<span class="badge bg-success">' . $kalan . '/' . $miktar . '</span>';
                }

                // Durum badge
                $durumText = $d->durum ?? 'aktif';
                $durumMap = [
                    'aktif' => '<span class="badge bg-soft-success text-success">Aktif</span>',
                    'pasif' => '<span class="badge bg-soft-secondary text-secondary">Pasif</span>',
                    'arizali' => '<span class="badge bg-soft-warning text-warning">Arızalı</span>',
                    'hurda' => '<span class="badge bg-soft-danger text-danger">Hurda</span>',
                ];
                $durumBadge = $durumMap[strtolower($durumText)] ?? '<span class="badge bg-soft-secondary text-secondary">' . $durumText . '</span>';

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

                $markaHtml = '<div>' . ($d->marka ?? '-') . ' ' . ($d->model ?? '') . '</div><small class="text-muted">' . ($d->seri_no ? 'SN: ' . $d->seri_no : '') . '</small>';
                $demirbasAdiHtml = '<a href="#" data-id="' . $enc_id . '" class="text-dark duzenle fw-medium">' . htmlspecialchars($d->demirbas_adi) . '</a>';

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
                    "seri_no" => $d->seri_no ?? '-',
                    "stok" => '<div class="text-center">' . $stokBadge . '</div>',
                    "durum" => '<div class="text-center">' . $durumBadge . '</div>',
                    "tutar" => '<div class="text-end">' . Helper::formattedMoney($d->edinme_tutari ?? 0) . ' ₺' . '</div>',
                    "tarih" => ($d->edinme_tarihi ? date('d.m.Y', strtotime($d->edinme_tarihi)) : '-'),
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
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($ids_raw) || empty($tarih)) {
            jsonResponse("error", "Lütfen en az bir sayaç seçin ve tarih girin.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');
        $successCount = 0;
        $errorCount = 0;

        $Demirbas->db->beginTransaction();

        foreach ($ids_raw as $enc_id) {
            $id = Security::decrypt($enc_id);
            if (!$id)
                continue;

            $demirbas = $Demirbas->find($id);
            if (!$demirbas)
                continue;

            // Stok kontrolü - sadece stokta olanlar (kalan_miktar > 0) teslim edilebilir mi?
            // User requested bulk delivery, usually for meters in stock.

            $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
            $sqlUpdate->execute([$formatted_tarih, $teslim_eden, ($aciklama ?? null), $id]);

            // Hareket kaydı
            try {
                $Hareket->hareketEkle([
                    'demirbas_id' => $id,
                    'personel_id' => $_SESSION["id"] ?? 1,
                    'hareket_tipi' => 'sarf',
                    'miktar' => $demirbas->kalan_miktar > 0 ? $demirbas->kalan_miktar : 1,
                    'tarih' => $formatted_tarih,
                    'aciklama' => 'Toplu Kaskiye teslim edildi. Teslim eden: ' . $teslim_eden . '. Not: ' . ($aciklama ?? ''),
                    'islem_yapan_id' => $_SESSION["id"] ?? null,
                    'kaynak' => 'manuel',
                ]);
                $successCount++;
            } catch (Exception $e) {
                // Ignore harekete ekle errors for bulk
            }
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
                                ' . ($z->durum !== 'iade' ? '
                                <a href="#" class="dropdown-item zimmet-sil" data-id="' . $enc_id . '">
                                    <span class="mdi mdi-delete font-size-18 text-danger me-1"></span> Sil
                                </a>' : '') . '
                            </div>
                        </div>';

            $disabledCheckbox = ($z->durum === 'iade') ? 'disabled' : '';
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
            SELECT id, seri_no, kalan_miktar, durum 
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
        $koli_baslangiclar = json_decode($_POST["koli_baslangiclar"] ?? "[]", true);
        $personel_id = intval($_POST["personel_id"]);
        $teslim_tarihi = Date::Ymd($_POST["teslim_tarihi"], 'Y-m-d');
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($koli_baslangiclar) || $personel_id <= 0) {
            jsonResponse("error", "Eksik bilgi.");
        }

        $tumSeriler = [];
        $koliMap = []; // Hangi seri hangi koliye ait

        foreach ($koli_baslangiclar as $baslangic) {
            if (!preg_match('/^(.*?)(\d+)$/', $baslangic, $matches)) {
                continue; // Hatalı formatı atla
            }

            $prefix = $matches[1];
            $number = intval($matches[2]);
            $digits = strlen($matches[2]);

            for ($i = 0; $i < 10; $i++) {
                $nextNum = str_pad($number + $i, $digits, "0", STR_PAD_LEFT);
                $seri = $prefix . $nextNum;
                $tumSeriler[] = $seri;
                $koliMap[$seri] = $baslangic;
            }
        }

        if (empty($tumSeriler)) {
            jsonResponse("error", "İşlenecek seri numarası bulunamadı.");
        }

        // Veritabanından ID'leri bul
        $placeholders = implode(',', array_fill(0, count($tumSeriler), '?'));
        $sql = $Demirbas->getDb()->prepare("
            SELECT id, seri_no, kalan_miktar 
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

            $data = [
                "demirbas_id" => $rec['id'],
                "personel_id" => $personel_id,
                "teslim_tarihi" => $teslim_tarihi,
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
            if (!$id) continue;

            $zimmet = $Zimmet->find($id);
            if (!$zimmet) continue;

            if ($zimmet->durum !== 'teslim') {
                $errorCount++;
                continue;
            }

            $teslim_miktar = (int)($zimmet->teslim_miktar ?? 1);
            $mevcut_iade = (int)($zimmet->iade_miktar ?? 0);
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
                $h->id = Security::encrypt($h->id);
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

// Gelişmiş filtreler için benzersiz değerleri getir
if ($action == "get-unique-values") {
    $column = $_POST['column'] ?? '';
    if (empty($column))
        jsonResponse("error", "Column missing");

    try {
        $firma_id = $_SESSION['firma_id'];
        $rows = [];

        if ($column === 'durum') {
            $sql = "SELECT DISTINCT z.durum as val FROM demirbas_zimmet z 
                    LEFT JOIN demirbas d ON z.demirbas_id = d.id 
                    WHERE z.silinme_tarihi IS NULL AND d.firma_id = ? ORDER BY val ASC";
            $stmt = $Zimmet->getDb()->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'kategori_adi') {
            $sql = "SELECT DISTINCT k.tur_adi as val FROM demirbas d
                    INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                    WHERE d.firma_id = ? ORDER BY val ASC";
            $stmt = $Zimmet->getDb()->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'personel_adi') {
            $sql = "SELECT DISTINCT p.adi_soyadi as val FROM demirbas_zimmet z
                    INNER JOIN db_personel.personel p ON z.personel_id = p.id
                    LEFT JOIN demirbas d ON z.demirbas_id = d.id
                    WHERE z.silinme_tarihi IS NULL AND d.firma_id = ? ORDER BY val ASC"; // or p.firma_id
            $stmt = $Zimmet->getDb()->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($column === 'marka_model' || $column === 'marka_sade') {
            $sql = "SELECT DISTINCT d.marka as val FROM demirbas d 
                    WHERE d.firma_id = ? AND d.marka IS NOT NULL AND d.marka != '' ORDER BY val ASC";
            $stmt = $Zimmet->getDb()->prepare($sql);
            $stmt->execute([$firma_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            jsonResponse("error", "Invalid column: " . $column);
        }

        $results = [];
        $durumMap = [
            'teslim' => 'Zimmetli',
            'iade' => 'İade Edildi',
            'kayip' => 'Kayıp',
            'arizali' => 'Arızalı'
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
