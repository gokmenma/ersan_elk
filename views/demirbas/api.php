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
use App\Service\Gate;

$Demirbas = new DemirbasModel();
$Servis = new DemirbasServisModel();
$Zimmet = new DemirbasZimmetModel();
$Tanimlamalar = new TanimlamalarModel();
$Hareket = new DemirbasHareketModel();
$SystemLog = new SystemLogModel();


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
            "demirbas_no" => $_POST["demirbas_no"] ?? null,
            "kategori_id" => !empty($_POST["kategori_id"]) ? $_POST["kategori_id"] : null,
            "demirbas_adi" => $_POST["demirbas_adi"],
            "marka" => $_POST["marka"] ?? null,
            "model" => $_POST["model"] ?? null,
            "edinme_tarihi" => $_POST["edinme_tarihi"] ?? null,
            "edinme_tutari" => Helper::formattedMoneyToNumber($_POST["edinme_tutari"] ?? 0),
            "durum" => $_POST["durum"] ?? 'aktif',
            "aciklama" => $_POST["aciklama"] ?? null,
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
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        $aciklama = $_POST["aciklama"] ?? null;

        if ($demirbas_id <= 0 || empty($tarih)) {
            jsonResponse("error", "Geçersiz parametreler. Lütfen formu eksiksiz doldurun.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');

        // Durumu güncelle, stoğu sıfırla
        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");
        $sqlUpdate->execute([$formatted_tarih, $teslim_eden, $aciklama, $demirbas_id]);

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
        $teslim_eden = $_SESSION["adi_soyadi"] ?? 'Sistem Kullanıcısı';
        $aciklama = $_POST["aciklama"] ?? null;

        if (empty($ids) || empty($tarih)) {
            jsonResponse("error", "Seçili sayaç bulunamadı veya tarih eksik.");
        }

        $formatted_tarih = Date::Ymd($tarih, 'Y-m-d');
        $successCount = 0;

        $sqlUpdate = $Demirbas->db->prepare("UPDATE demirbas SET durum = 'Kaskiye Teslim Edildi', kaskiye_teslim_tarihi = ?, kaskiye_teslim_eden = ?, aciklama = ?, kalan_miktar = 0, miktar = 0 WHERE id = ?");

        foreach ($ids as $id_raw) {
            $id = intval(Security::decrypt($id_raw));
            if ($id > 0) {
                $sqlUpdate->execute([$formatted_tarih, $teslim_eden, $aciklama, $id]);
                $successCount++;
            }
        }

        jsonResponse("success", "Toplam $successCount adet sayaç Kaskiye teslim edildi.");
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
                    $stokBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">Stok Yok</span>';
                } elseif ($minStok > 0 && $kalan <= $minStok) {
                    $stokBadge = '<span class="badge" style="background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">Stok Azaldı (' . $kalan . '/' . $miktar . ')</span>';
                } elseif ($kalan < $miktar) {
                    $stokBadge = '<span class="badge" style="background: #f59e0b; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;"> ' . $kalan . '/' . $miktar . '</span>';
                } else {
                    $stokBadge = '<span class="badge" style="background: #10b981; color: #fff; padding: 4px 8px; border-radius: 6px; font-weight: 700;">' . $kalan . '/' . $miktar . '</span>';
                }

                // Durum badge
                $durumText = $d->durum ?? 'aktif';
                $durumMap = [
                    'aktif' => '<span class="badge" style="background: #10b981; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Boşta</span>',
                    'pasif' => '<span class="badge" style="background: #64748b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Pasif</span>',
                    'arizali' => '<span class="badge" style="background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Arızalı</span>',
                    'hurda' => '<span class="badge" style="background: #ef4444; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Hurda</span>',
                    'kaskiye teslim edildi' => '<span class="badge" style="background: #06b6d4; color: #fff; padding: 5px 12px; border-radius: 50px; font-weight: 600;">Kaskiye Teslim</span>',
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

            // Kaskiye teslimatı demirbaş tablosunda takip edildiği için artık demirbas_hareketler tablosuna zimmet-siz hareket kaydı eklemiyoruz.
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

            // Aparat kategorisi kontrolü
            $katAdiLower = mb_strtolower($z->kategori_adi ?? '', 'UTF-8');
            $isAparat = str_contains($katAdiLower, 'aparat');
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

        if ($isAparat) {
            $result = $Zimmet->tuketimYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama);
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

        // Sayaç kategorilerini bul
        $sqlKat = $Demirbas->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND (LOWER(tur_adi) LIKE '%sayaç%' OR LOWER(tur_adi) LIKE '%sayac%') AND firma_id = ?");
        $sqlKat->execute([$_SESSION['firma_id']]);
        $sayacKatIds = $sqlKat->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sayacKatIds)) {
            jsonResponse("success", "OK", ["data" => []]);
        }

        $katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));
        $params = $sayacKatIds;
        $params[] = $personel_id;
        $params[] = $_SESSION['firma_id'];

        $sql = $Demirbas->db->prepare("
            SELECT z.id, z.teslim_miktar, z.teslim_tarihi,
                   d.demirbas_adi, d.marka, d.model, d.seri_no, d.durum as demirbas_durum,
                   (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL) as iade_miktar,
                   (z.teslim_miktar - (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL)) as kalan_miktar
            FROM demirbas_zimmet z
            INNER JOIN demirbas d ON z.demirbas_id = d.id
            WHERE d.kategori_id IN ($katPlaceholders) 
              AND z.personel_id = ?
              AND z.durum = 'teslim'
              AND d.firma_id = ?
              AND LOWER(d.durum) = 'hurda'
              AND z.silinme_tarihi IS NULL
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute($params);
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

        jsonResponse("success", "OK", ["data" => $result]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Hurda Sayaç İade Al (Personelden depoya)
if ($action == "hurda-sayac-iade") {
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

                $Zimmet->iadeYap(
                    $zimmet_id,
                    $iade_tarihi,
                    $kalanMiktar,
                    $aciklama ? "Hurda Sayaç İade: " . $aciklama : "Hurda Sayaç İade (Manuel)",
                    null,
                    null,
                    'manuel'
                );
                $successCount++;
            }

            $Zimmet->getDb()->commit();
            jsonResponse("success", "$successCount adet hurda sayaç başarıyla depoya iade alındı.");

        } else {
            // Manuel giriş (yeni hurda kayıt oluştur ve personele zimmetle, sonra iade al)
            $personel_id = intval($_POST["hurda_personel_id"] ?? 0);
            $iade_tarihi = $_POST["hurda_iade_tarihi"] ?? date('d.m.Y');
            $adet = intval($_POST["hurda_iade_adet"] ?? 1);
            $sayac_adi = $_POST["hurda_sayac_adi"] ?? '';
            $aciklama = $_POST["hurda_aciklama"] ?? null;

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

            // 1. Hurda sayaç demirbaş kaydı oluştur (doğrudan depoya)
            $sqlInsert = $Demirbas->db->prepare("
                INSERT INTO demirbas 
                (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, aciklama, kayit_yapan, edinme_tarihi)
                VALUES (?, ?, ?, ?, ?, 'hurda', ?, ?, ?)
            ");
            $sqlInsert->execute([
                $_SESSION['firma_id'],
                $sayacKatId,
                $sayac_adi,
                $adet,
                $adet, // Önce depoda olsun zimmet verebilmek için
                $aciklama ? "Manuel Hurda İade: " . $aciklama : "Manuel Hurda Sayaç İade",
                $_SESSION['id'] ?? null,
                $formatted_tarih
            ]);
            $yeniDemirbasId = $Demirbas->db->lastInsertId();

            // 2. Personele zimmetle (Geçmişte gözükmesi için)
            $Zimmet->zimmetVer([
                'demirbas_id' => $yeniDemirbasId,
                'personel_id' => $personel_id,
                'teslim_tarihi' => $iade_tarihi, // Aynı tarih
                'teslim_miktar' => $adet,
                'aciklama' => "Hurda sayaç personelden iade alınırken sistem tarafından oluşturulan kayıt",
                'kaynak' => 'manuel'
            ]);

            // 3. Zimmet IDsini bul
            $zimmetKaydiSql = $Zimmet->getDb()->prepare("SELECT id FROM demirbas_zimmet WHERE demirbas_id = ? AND personel_id = ? ORDER BY id DESC LIMIT 1");
            $zimmetKaydiSql->execute([$yeniDemirbasId, $personel_id]);
            $yeniZimmetId = $zimmetKaydiSql->fetchColumn();

            // 4. Hemen iadesini al (Depoda stoğu geri artacaktır)
            $Zimmet->iadeYap(
                $yeniZimmetId,
                $iade_tarihi,
                $adet,
                $aciklama ? "Hurda Sayaç İade: " . $aciklama : "Hurda Sayaç İade (Manuel)",
                null,
                null,
                'manuel'
            );

            $Demirbas->db->commit();

            jsonResponse("success", "$adet adet hurda sayaç depoya başarıyla eklendi.", ["yeni_id" => Security::encrypt($yeniDemirbasId)]);
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

if ($action == "sayac-global-summary") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            jsonResponse("success", "Başarılı", [
                'yeni_depoda' => 0,
                'hurda_depoda' => 0,
                'yeni_personelde' => 0,
                'hurda_personelde' => 0
            ]);
        }

        $in = buildInClause($catIds);
        $params = $catIds;
        $params[] = $firmaId;

        $sqlDepo = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(durum,'')) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_depoda,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(durum,'')) <> 'hurda' AND LOWER(COALESCE(durum,'')) <> 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as yeni_depoda
            FROM demirbas
            WHERE kategori_id IN ($in) AND firma_id = ? AND silinme_tarihi IS NULL
        ");
        $sqlDepo->execute($params);
        $depo = $sqlDepo->fetch(PDO::FETCH_OBJ) ?: (object) ['yeni_depoda' => 0, 'hurda_depoda' => 0];

        $sqlPersonel = $Demirbas->db->prepare(" 
            SELECT
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(d.durum,'')) = 'hurda' THEN z.teslim_miktar ELSE 0 END), 0) as hurda_personelde,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(d.durum,'')) <> 'hurda' AND LOWER(COALESCE(d.durum,'')) <> 'kaskiye teslim edildi' THEN z.teslim_miktar ELSE 0 END), 0) as yeni_personelde
            FROM demirbas_zimmet z
            INNER JOIN demirbas d ON d.id = z.demirbas_id
            WHERE z.durum = 'teslim' AND d.kategori_id IN ($in) AND d.firma_id = ? AND z.silinme_tarihi IS NULL
        ");
        $sqlPersonel->execute($params);
        $pers = $sqlPersonel->fetch(PDO::FETCH_OBJ) ?: (object) ['yeni_personelde' => 0, 'hurda_personelde' => 0];

        jsonResponse("success", "Başarılı", [
            'yeni_depoda' => (int) ($depo->yeni_depoda ?? 0),
            'hurda_depoda' => (int) ($depo->hurda_depoda ?? 0),
            'yeni_personelde' => (int) ($pers->yeni_personelde ?? 0),
            'hurda_personelde' => (int) ($pers->hurda_personelde ?? 0)
        ]);
    } catch (Exception $ex) {
        jsonResponse("error", $ex->getMessage());
    }
}

// Sayaç Personel Listesi (Günlük Gruplanmış)
if ($action == "sayac-personel-list") {
    try {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $draw = (int) ($_POST['draw'] ?? 1);
        $start = (int) ($_POST['start'] ?? 0);
        $length = (int) ($_POST['length'] ?? 10);
        $search = trim($_POST['search']['value'] ?? '');

        // Kategori Join'li filtreleme (daha sağlam)
        $where = " WHERE h.silinme_tarihi IS NULL 
                     AND h.personel_id IS NOT NULL 
                     AND d.firma_id = ? 
                     AND (LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%') ";
        
        $params = [$firmaId];

        // Sütun bazlı aramalar (Advanced Filters)
        if (!empty($_POST['columns'])) {
            // Tarih (Index 1)
            $cDate = trim($_POST['columns'][1]['search']['value'] ?? '');
            if ($cDate !== '') {
                $where .= " AND DATE_FORMAT(h.tarih, '%d.%m.%Y') LIKE ? ";
                $params[] = '%' . $cDate . '%';
            }
            // Personel (Index 2)
            $cPers = trim($_POST['columns'][2]['search']['value'] ?? '');
            if ($cPers !== '') {
                $where .= " AND p.adi_soyadi LIKE ? ";
                $params[] = '%' . $cPers . '%';
            }
        }

        if ($search !== '') {
            $where .= " AND (p.adi_soyadi LIKE ? OR DATE_FORMAT(h.tarih, '%d.%m.%Y') LIKE ?) ";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        // Toplam kayıt sayısı (gruplanmış sayfa sayısı)
        $sqlCount = $Demirbas->db->prepare(" 
            SELECT COUNT(*)
            FROM (
                SELECT h.personel_id, DATE(h.tarih) as gun
                FROM demirbas_hareketler h
                INNER JOIN demirbas d ON d.id = h.demirbas_id
                INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                LEFT JOIN personel p ON p.id = h.personel_id
                $where
                GROUP BY h.personel_id, DATE(h.tarih)
            ) sub
        ");
        $sqlCount->execute($params);
        $filtered = (int) $sqlCount->fetchColumn();

        // Veri sorgusu
        $sql = $Demirbas->db->prepare(" 
            SELECT
                h.personel_id,
                DATE(h.tarih) as tarih,
                COALESCE(p.adi_soyadi, CONCAT('Personel #', h.personel_id)) as personel_adi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as bizden_toplam_aldigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as toplam_taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as toplam_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as teslim_edilen_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_kalan_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_kalan_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON p.id = h.personel_id
            $where
            GROUP BY h.personel_id, DATE(h.tarih), p.adi_soyadi
            ORDER BY h.tarih DESC, p.adi_soyadi ASC
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
            $data[] = [
                'expand_icon' => '<i class="bx bx-chevron-right fs-4 text-muted transition-all expand-icon-btn"></i>',
                'sira' => $i,
                'tarih' => Date::engtodt($r->tarih),
                'tarih_raw' => $r->tarih,
                'personel_id' => (int) $r->personel_id,
                'personel_adi' => '<span class="fw-semibold text-dark">' . htmlspecialchars((string) $r->personel_adi) . '</span>',
                'bizden_toplam_aldigi' => '<span class="fw-bold text-info">' . $r->bizden_toplam_aldigi . '</span>',
                'toplam_taktigi' => '<span class="fw-bold text-success">' . $r->toplam_taktigi . '</span>',
                'teslim_edilen_hurda' => '<span class="text-muted">' . $r->teslim_edilen_hurda . '</span>',
                'toplam_hurda' => '<span class="text-danger">' . $r->toplam_hurda . '</span>',
                'elinde_kalan_yeni' => '<strong>' . max(0, $r->elinde_kalan_yeni) . '</strong>',
                'elinde_kalan_hurda' => (int) $r->elinde_kalan_hurda
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
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as bizden_toplam_aldigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as toplam_taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as toplam_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as teslim_edilen_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_kalan_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_kalan_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND d.kategori_id IN ($in)
              AND d.firma_id = ?
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
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0) as alinan,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0) as taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as hurda_alinan,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as hurda_teslim,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0) as kayip
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            WHERE h.silinme_tarihi IS NULL
              AND h.personel_id = ?
              AND d.kategori_id IN ($in)
              AND d.firma_id = ?
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
