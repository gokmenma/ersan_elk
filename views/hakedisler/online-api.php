<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
session_start();

use App\Helper\Date;
use App\Model\HakedisSozlesmeModel;
use App\Model\HakedisDonemModel;
use App\Model\HakedisKalemModel;
use App\Model\HakedisMiktarModel;
use App\Helper\Helper;

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || !isset($_SESSION['firma_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.']);
    exit;
}

$firma_id = $_SESSION['firma_id'];
$type = $_REQUEST['type'] ?? '';

try {
    function convertDateToDb($date)
    {
        if (empty($date))
            return null;
        if (strpos($date, '.') !== false) {
            $parts = explode('.', $date);
            if (count($parts) == 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        }
        return $date;
    }

    function ensureSureUzatimTablosu(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS hakedis_sure_uzatimlari (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                sozlesme_id INT NOT NULL,
                uzatim_no INT UNSIGNED NOT NULL,
                uzatim_tarihi DATE NOT NULL,
                karar_no VARCHAR(100) DEFAULT NULL,
                aciklama TEXT DEFAULT NULL,
                uzatim_gun INT UNSIGNED NOT NULL,
                onceki_bitis_tarihi DATE NOT NULL,
                yeni_bitis_tarihi DATE NOT NULL,
                olusturan_personel_id INT DEFAULT NULL,
                olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_hakedis_sure_uzatimi_no (sozlesme_id, uzatim_no),
                KEY idx_hakedis_sure_uzatimi_sozlesme (sozlesme_id, uzatim_tarihi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    switch ($type) {
        case 'getSozlesmeler':
            $model = new HakedisSozlesmeModel();

            // Standard datatable parameters
            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $search = $_POST['search']['value'] ?? '';
            $orderColIdx = $_POST['order'][0]['column'] ?? 0;
            $orderDir = $_POST['order'][0]['dir'] ?? 'desc';

            $columns = [
                0 => 'idare_adi',
                1 => 'isin_adi',
                2 => 'sozlesme_tarihi',
                3 => 'isin_bitecegi_tarih',
                4 => 'sozlesme_bedeli',
                5 => 'durum',
                6 => 'id'
            ];
            $orderCol = $columns[$orderColIdx] ?? 'id';

            $db = $model->getDb();

            // Query builder
            $where = "firma_id = :firma_id AND silinme_tarihi IS NULL";
            $params = [':firma_id' => $firma_id];

            if ($search) {
                $where .= " AND (idare_adi LIKE :srch OR isin_adi LIKE :srch)";
                $params[':srch'] = "%$search%";
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM hakedis_sozlesmeler WHERE $where");
            $stmt->execute($params);
            $totalRecords = $stmt->fetchColumn();

            $sql = "SELECT * FROM hakedis_sozlesmeler WHERE $where ORDER BY $orderCol $orderDir LIMIT :start, :length";
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $totalRecords,
                "data" => $data
            ]);
            break;

        case 'saveSozlesme':
            $model = new HakedisSozlesmeModel();
            $db = $model->getDb();

            $data = [
                'firma_id' => $firma_id,
                'idare_adi' => $_POST['idare_adi'] ?? '',
                'idare_baskanlik_adi' => $_POST['idare_baskanlik_adi'] ?? '',
                'isin_adi' => $_POST['isin_adi'] ?? '',
                'isin_yuklenicisi' => $_POST['isin_yuklenicisi'] ?? '',
                'ihale_kayit_no' => $_POST['ihale_kayit_no'] ?? '',
                'kesif_bedeli' => !empty($_POST['kesif_bedeli']) ? floatval($_POST['kesif_bedeli']) : null,
                'ihale_tenzilati' => !empty($_POST['ihale_tenzilati']) ? floatval($_POST['ihale_tenzilati']) : null,
                'sozlesme_bedeli' => !empty($_POST['sozlesme_bedeli']) ? floatval($_POST['sozlesme_bedeli']) : null,
                'sozlesme_tarihi' => Date::Ymd($_POST['sozlesme_tarihi'] ?? null),
                'isin_bitecegi_tarih' => Date::Ymd($_POST['isin_bitecegi_tarih'] ?? null),
                'ihale_tarihi' => Date::Ymd($_POST['ihale_tarihi'] ?? null),
                'yer_teslim_tarihi' => Date::Ymd($_POST['yer_teslim_tarihi'] ?? null),
                'isin_suresi' => !empty($_POST['isin_suresi']) ? intval($_POST['isin_suresi']) : null,
                'kontrol_teskilati' => $_POST['kontrol_teskilati'] ?? '',
                'tasvip_eden' => $_POST['tasvip_eden'] ?? '',
                'tasvip_eden_unvan' => $_POST['tasvip_eden_unvan'] ?? '',
                'idare_onaylayan' => $_POST['idare_onaylayan'] ?? '',
                'idare_onaylayan_unvan' => $_POST['idare_onaylayan_unvan'] ?? '',
                // Yüklenici Adres ve Tel
                'yuklenici_adres' => $_POST['yuklenici_adres'] ?? null,
                'yuklenici_tel' => $_POST['yuklenici_tel'] ?? null,
                // New fields
                'yuzde_yirmi_fazla_is' => $_POST['yuzde_yirmi_fazla_is'] ?? null,
                'son_sure_uzatimi' => $_POST['son_sure_uzatimi'] ?? null,
                'gecici_kabul_tarihi' => Date::Ymd($_POST['gecici_kabul_tarihi'] ?? null),
                'gecici_kabul_itibar_tarihi' => Date::Ymd($_POST['gecici_kabul_itibar_tarihi'] ?? null),
                'gecici_kabul_onanma_tarihi' => Date::Ymd($_POST['gecici_kabul_onanma_tarihi'] ?? null),
                'durum' => $_POST['durum'] ?? 'aktif',
                'a1_katsayisi' => !empty($_POST['a1_katsayisi']) ? floatval($_POST['a1_katsayisi']) : 0.28,
                'b1_katsayisi' => !empty($_POST['b1_katsayisi']) ? floatval($_POST['b1_katsayisi']) : 0.22,
                'b2_katsayisi' => !empty($_POST['b2_katsayisi']) ? floatval($_POST['b2_katsayisi']) : 0.25,
                'c_katsayisi' => !empty($_POST['c_katsayisi']) ? floatval($_POST['c_katsayisi']) : 0.25,
                'asgari_ucret_temel' => !empty($_POST['asgari_ucret_temel']) ? floatval($_POST['asgari_ucret_temel']) : null,
                'motorin_temel' => !empty($_POST['motorin_temel']) ? floatval($_POST['motorin_temel']) : null,
                'ufe_genel_temel' => !empty($_POST['ufe_genel_temel']) ? floatval($_POST['ufe_genel_temel']) : null,
                'makine_ekipman_temel' => !empty($_POST['makine_ekipman_temel']) ? floatval($_POST['makine_ekipman_temel']) : null,
                'kdv_orani' => !empty($_POST['kdv_orani']) ? floatval($_POST['kdv_orani']) : 20.00,
                'tevkifat_orani' => $_POST['tevkifat_orani'] ?? '4/10',
                'temel_endeks_ay' => !empty($_POST['temel_endeks_ay']) ? intval($_POST['temel_endeks_ay']) : null,
                'temel_endeks_yil' => !empty($_POST['temel_endeks_yil']) ? intval($_POST['temel_endeks_yil']) : null
            ];

            if (isset($_POST['id']) && $_POST['id'] > 0) {
                // Update
                ensureSureUzatimTablosu($db);
                $uzatimKontrol = $db->prepare("SELECT COUNT(*) FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ?");
                $uzatimKontrol->execute([intval($_POST['id'])]);
                if (intval($uzatimKontrol->fetchColumn()) > 0) {
                    unset($data['isin_bitecegi_tarih'], $data['isin_suresi'], $data['son_sure_uzatimi']);
                }
                $setParts = [];
                $params = [':id' => $_POST['id'], ':firma_id' => $firma_id];
                foreach ($data as $key => $val) {
                    if ($key == 'firma_id')
                        continue;
                    $setParts[] = "$key = :$key";
                    $params[":$key"] = $val;
                }
                $sql = "UPDATE hakedis_sozlesmeler SET " . implode(", ", $setParts) . " WHERE id = :id AND firma_id = :firma_id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insert
                $data['olusturma_tarihi'] = date('Y-m-d H:i:s');
                $cols = implode(", ", array_keys($data));
                $vals = ":" . implode(", :", array_keys($data));
                $sql = "INSERT INTO hakedis_sozlesmeler ($cols) VALUES ($vals)";
                $stmt = $db->prepare($sql);
                $stmt->execute($data);

                $sozlesme_id = $db->lastInsertId();
            }


            // Kalemleri Kaydet (Birim Fiyat Cetveli Tabı)
            if (!empty($_POST['kalem_verileri'])) {
                $kalemler = json_decode($_POST['kalem_verileri'], true);
                $sid = $sozlesme_id ?? $_POST['id'];

                if (is_array($kalemler)) {
                    $revizyonKontrol = $db->prepare("SELECT COUNT(*) FROM hakedis_is_revizyonlari WHERE sozlesme_id = ?");
                    $revizyonKontrol->execute([$sid]);
                    $revizyonVar = intval($revizyonKontrol->fetchColumn()) > 0;
                    $islenen_idleri = [];
                    foreach ($kalemler as $k) {
                        $k_id = isset($k['id']) && $k['id'] > 0 ? (int) $k['id'] : 0;

                        if ($k_id > 0) {
                            // Güncelle
                            $miktarSeti = $revizyonVar ? '' : ', miktari = ?';
                            $stmtExt = $db->prepare("UPDATE hakedis_kalemleri SET poz_no = ?, kalem_adi = ?, birim = ?{$miktarSeti}, teklif_edilen_birim_fiyat = ? WHERE id = ? AND sozlesme_id = ?");
                            $degerler = [$k['poz_no'], $k['kalem_adi'], $k['birim']];
                            if (!$revizyonVar) {
                                $degerler[] = floatval($k['miktari']);
                            }
                            array_push($degerler, floatval($k['teklif_edilen_birim_fiyat']), $k_id, $sid);
                            $stmtExt->execute($degerler);
                            $islenen_idleri[] = $k_id;
                        } else {
                            // Yeni ekle
                            $stmtExt = $db->prepare("INSERT INTO hakedis_kalemleri (sozlesme_id, poz_no, kalem_adi, birim, miktari, teklif_edilen_birim_fiyat) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtExt->execute([$sid, $k['poz_no'], $k['kalem_adi'], $k['birim'], floatval($k['miktari']), floatval($k['teklif_edilen_birim_fiyat'])]);
                            $islenen_idleri[] = $db->lastInsertId();
                        }
                    }

                    // Gönderilmeyen (silinen) kalemleri temizle
                    if (!empty($islenen_idleri)) {
                        $in = str_repeat('?,', count($islenen_idleri) - 1) . '?';
                        $sql = "DELETE FROM hakedis_kalemleri WHERE sozlesme_id = ? AND id NOT IN ($in)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute(array_merge([$sid], $islenen_idleri));
                    }
                }
            }
            echo json_encode(['status' => 'success']);
            break;

        case 'getSozlesme':
            $id = $_POST['id'] ?? 0;
            if ($id) {
                $db = (new HakedisSozlesmeModel())->getDb();
                ensureSureUzatimTablosu($db);
                $stmt = $db->prepare("SELECT * FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
                $stmt->execute([$id, $firma_id]);
                $sozlesme = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sozlesme) {
                    $stmtK = $db->prepare("
                        SELECT k.*,
                               k.miktari - COALESCE((
                                   SELECT SUM(rk.degisim_miktari)
                                   FROM hakedis_is_revizyon_kalemleri rk
                                   INNER JOIN hakedis_is_revizyonlari r ON r.id = rk.revizyon_id
                                   WHERE rk.kalem_id = k.id AND r.sozlesme_id = k.sozlesme_id
                               ), 0) AS sozlesme_ilk_miktari
                        FROM hakedis_kalemleri k
                        WHERE k.sozlesme_id = ?
                        ORDER BY k.id ASC
                    ");
                    $stmtK->execute([$id]);
                    $kalemler = $stmtK->fetchAll(PDO::FETCH_ASSOC);

                    $stmtR = $db->prepare("SELECT COUNT(*) FROM hakedis_is_revizyonlari WHERE sozlesme_id = ?");
                    $stmtR->execute([$id]);
                    $revizyonSayisi = intval($stmtR->fetchColumn());
                    $stmtU = $db->prepare("SELECT COUNT(*) FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ?");
                    $stmtU->execute([$id]);
                    echo json_encode([
                        'status' => 'success',
                        'data' => $sozlesme,
                        'kalemler' => $kalemler,
                        'revizyon_sayisi' => $revizyonSayisi,
                        'sure_uzatim_sayisi' => intval($stmtU->fetchColumn())
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Sözleşme bulunamadı veya yetkiniz yok.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID eksik']);
            }
            break;

        case 'getIsRevizyonlari':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $db = (new HakedisSozlesmeModel())->getDb();
            $stmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$sozlesme_id, $firma_id]);
            if (!$stmt->fetchColumn()) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz sözleşme.']);
                break;
            }

            $stmt = $db->prepare("
                SELECT r.*,
                       COALESCE(SUM(d.degisim_miktari * d.birim_fiyat), 0) AS toplam_tutar_farki
                FROM hakedis_is_revizyonlari r
                LEFT JOIN hakedis_is_revizyon_kalemleri d ON d.revizyon_id = r.id
                WHERE r.sozlesme_id = ?
                GROUP BY r.id
                ORDER BY r.revizyon_no DESC
            ");
            $stmt->execute([$sozlesme_id]);
            $revizyonlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $bedelStmt = $db->prepare("SELECT sozlesme_bedeli FROM hakedis_sozlesmeler WHERE id = ?");
            $bedelStmt->execute([$sozlesme_id]);
            $sozlesmeBedeli = floatval($bedelStmt->fetchColumn());
            $kumulatifFark = array_sum(array_map(static function ($r) {
                return floatval($r['toplam_tutar_farki']);
            }, $revizyonlar));

            $detayStmt = $db->prepare("
                SELECT d.*
                FROM hakedis_is_revizyon_kalemleri d
                WHERE d.revizyon_id = ?
                ORDER BY d.id
            ");
            foreach ($revizyonlar as &$revizyon) {
                $detayStmt->execute([$revizyon['id']]);
                $revizyon['kalemler'] = $detayStmt->fetchAll(PDO::FETCH_ASSOC);
                $revizyon['toplam_artis_orani'] = $sozlesmeBedeli > 0
                    ? round(($kumulatifFark / $sozlesmeBedeli) * 100, 4)
                    : 0;
                $kumulatifFark -= floatval($revizyon['toplam_tutar_farki']);
            }
            unset($revizyon);
            echo json_encode(['status' => 'success', 'data' => $revizyonlar]);
            break;

        case 'deleteIsRevizyonu':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $revizyon_id = intval($_POST['revizyon_id'] ?? 0);
            if (!$sozlesme_id || !$revizyon_id) {
                echo json_encode(['status' => 'error', 'message' => 'Revizyon bilgisi eksik.']);
                break;
            }

            $db = (new HakedisSozlesmeModel())->getDb();
            $db->beginTransaction();
            try {
                $yetkiStmt = $db->prepare("
                    SELECT r.id
                    FROM hakedis_is_revizyonlari r
                    INNER JOIN hakedis_sozlesmeler s ON s.id = r.sozlesme_id
                    WHERE r.id = ? AND r.sozlesme_id = ? AND s.firma_id = ? AND s.silinme_tarihi IS NULL
                    FOR UPDATE
                ");
                $yetkiStmt->execute([$revizyon_id, $sozlesme_id, $firma_id]);
                if (!$yetkiStmt->fetchColumn()) {
                    throw new RuntimeException('Revizyon bulunamadı veya yetkiniz yok.');
                }

                $detayStmt = $db->prepare("SELECT kalem_id, degisim_miktari FROM hakedis_is_revizyon_kalemleri WHERE revizyon_id = ?");
                $detayStmt->execute([$revizyon_id]);
                $silinenDetaylar = $detayStmt->fetchAll(PDO::FETCH_ASSOC);
                $miktarStmt = $db->prepare("UPDATE hakedis_kalemleri SET miktari = miktari - ? WHERE id = ? AND sozlesme_id = ?");
                foreach ($silinenDetaylar as $detay) {
                    $miktarStmt->execute([floatval($detay['degisim_miktari']), intval($detay['kalem_id']), $sozlesme_id]);
                }
                $db->prepare("DELETE FROM hakedis_is_revizyon_kalemleri WHERE revizyon_id = ?")
                    ->execute([$revizyon_id]);
                $db->prepare("DELETE FROM hakedis_is_revizyonlari WHERE id = ? AND sozlesme_id = ?")
                    ->execute([$revizyon_id, $sozlesme_id]);

                $revStmt = $db->prepare("SELECT id FROM hakedis_is_revizyonlari WHERE sozlesme_id = ? ORDER BY revizyon_no, id");
                $revStmt->execute([$sozlesme_id]);
                $kalanRevizyonlar = $revStmt->fetchAll(PDO::FETCH_COLUMN);
                $noStmt = $db->prepare("UPDATE hakedis_is_revizyonlari SET revizyon_no = ? WHERE id = ?");
                foreach ($kalanRevizyonlar as $index => $kalanId) {
                    $noStmt->execute([$index + 1, $kalanId]);
                }

                // Kalan geçmişin önceki/yeni miktar zincirini yeniden kur.
                $kalemStmt = $db->prepare("SELECT id, miktari FROM hakedis_kalemleri WHERE sozlesme_id = ? FOR UPDATE");
                $kalemStmt->execute([$sozlesme_id]);
                $anlikMiktarlar = [];
                foreach ($kalemStmt->fetchAll(PDO::FETCH_ASSOC) as $kalem) {
                    $anlikMiktarlar[intval($kalem['id'])] = floatval($kalem['miktari']);
                }
                $toplamStmt = $db->prepare("
                    SELECT d.kalem_id, SUM(d.degisim_miktari) toplam_degisim
                    FROM hakedis_is_revizyon_kalemleri d
                    INNER JOIN hakedis_is_revizyonlari r ON r.id = d.revizyon_id
                    WHERE r.sozlesme_id = ? GROUP BY d.kalem_id
                ");
                $toplamStmt->execute([$sozlesme_id]);
                foreach ($toplamStmt->fetchAll(PDO::FETCH_ASSOC) as $toplam) {
                    $kalemId = intval($toplam['kalem_id']);
                    $anlikMiktarlar[$kalemId] -= floatval($toplam['toplam_degisim']);
                }
                $zincirStmt = $db->prepare("
                    SELECT d.id, d.kalem_id, d.degisim_miktari
                    FROM hakedis_is_revizyon_kalemleri d
                    INNER JOIN hakedis_is_revizyonlari r ON r.id = d.revizyon_id
                    WHERE r.sozlesme_id = ? ORDER BY r.revizyon_no, d.id
                ");
                $zincirStmt->execute([$sozlesme_id]);
                $snapshotStmt = $db->prepare("UPDATE hakedis_is_revizyon_kalemleri SET onceki_miktar = ?, yeni_miktar = ? WHERE id = ?");
                foreach ($zincirStmt->fetchAll(PDO::FETCH_ASSOC) as $detay) {
                    $kalemId = intval($detay['kalem_id']);
                    $onceki = $anlikMiktarlar[$kalemId] ?? 0;
                    $yeni = $onceki + floatval($detay['degisim_miktari']);
                    $snapshotStmt->execute([$onceki, $yeni, intval($detay['id'])]);
                    $anlikMiktarlar[$kalemId] = $yeni;
                }

                $db->commit();
                echo json_encode(['status' => 'success']);
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'saveIsRevizyonu':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $revizyon_tarihi = Date::Ymd($_POST['revizyon_tarihi'] ?? null);
            $kalemler = json_decode($_POST['kalemler'] ?? '[]', true);
            if (!$sozlesme_id || !$revizyon_tarihi || !is_array($kalemler) || !$kalemler) {
                echo json_encode(['status' => 'error', 'message' => 'Revizyon tarihi ve en az bir kalem zorunludur.']);
                break;
            }

            $db = (new HakedisSozlesmeModel())->getDb();
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL FOR UPDATE");
                $stmt->execute([$sozlesme_id, $firma_id]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Geçersiz sözleşme.');
                }

                $stmt = $db->prepare("SELECT COALESCE(MAX(revizyon_no), 0) + 1 FROM hakedis_is_revizyonlari WHERE sozlesme_id = ?");
                $stmt->execute([$sozlesme_id]);
                $revizyon_no = intval($stmt->fetchColumn());
                $stmt = $db->prepare("
                    INSERT INTO hakedis_is_revizyonlari
                    (sozlesme_id, revizyon_no, revizyon_tarihi, karar_no, aciklama, olusturan_personel_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sozlesme_id, $revizyon_no, $revizyon_tarihi,
                    trim($_POST['karar_no'] ?? '') ?: null,
                    trim($_POST['aciklama'] ?? '') ?: null,
                    $_SESSION['id']
                ]);
                $revizyon_id = $db->lastInsertId();

                $kalemStmt = $db->prepare("
                    SELECT id, poz_no, kalem_adi, birim, miktari, teklif_edilen_birim_fiyat
                    FROM hakedis_kalemleri
                    WHERE id = ? AND sozlesme_id = ?
                    FOR UPDATE
                ");
                $detayStmt = $db->prepare("
                    INSERT INTO hakedis_is_revizyon_kalemleri
                    (revizyon_id, kalem_id, poz_no, kalem_adi, birim,
                     onceki_miktar, degisim_miktari, yeni_miktar, birim_fiyat)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $guncelleStmt = $db->prepare("UPDATE hakedis_kalemleri SET miktari = ? WHERE id = ? AND sozlesme_id = ?");
                $islenen = [];
                foreach ($kalemler as $kalem) {
                    $kalem_id = intval($kalem['kalem_id'] ?? 0);
                    $degisim = round(floatval($kalem['degisim_miktari'] ?? 0), 4);
                    if (!$kalem_id || abs($degisim) < 0.0001 || isset($islenen[$kalem_id])) {
                        continue;
                    }
                    $islenen[$kalem_id] = true;
                    $kalemStmt->execute([$kalem_id, $sozlesme_id]);
                    $mevcut = $kalemStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$mevcut) {
                        throw new RuntimeException('Revizyondaki iş kalemlerinden biri bulunamadı.');
                    }
                    $onceki = round(floatval($mevcut['miktari']), 4);
                    $yeni = round($onceki + $degisim, 4);
                    if ($yeni < 0) {
                        throw new RuntimeException('Bir iş kaleminin yeni miktarı sıfırın altında olamaz.');
                    }
                    $detayStmt->execute([
                        $revizyon_id, $kalem_id, $mevcut['poz_no'], $mevcut['kalem_adi'], $mevcut['birim'],
                        $onceki, $degisim, $yeni,
                        floatval($mevcut['teklif_edilen_birim_fiyat'])
                    ]);
                    $guncelleStmt->execute([$yeni, $kalem_id, $sozlesme_id]);
                }
                if (!$islenen) {
                    throw new RuntimeException('Geçerli bir artış/azalış miktarı girilmedi.');
                }
                $db->commit();
                echo json_encode(['status' => 'success', 'revizyon_no' => $revizyon_no]);
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'getSureUzatimlari':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $db = (new HakedisSozlesmeModel())->getDb();
            ensureSureUzatimTablosu($db);
            $stmt = $db->prepare("
                SELECT u.* FROM hakedis_sure_uzatimlari u
                INNER JOIN hakedis_sozlesmeler s ON s.id = u.sozlesme_id
                WHERE u.sozlesme_id = ? AND s.firma_id = ? AND s.silinme_tarihi IS NULL
                ORDER BY u.uzatim_no DESC
            ");
            $stmt->execute([$sozlesme_id, $firma_id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'saveSureUzatimi':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $uzatim_tarihi = Date::Ymd($_POST['uzatim_tarihi'] ?? null);
            $uzatim_gun = intval($_POST['uzatim_gun'] ?? 0);
            if (!$sozlesme_id || !$uzatim_tarihi || $uzatim_gun <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Onay tarihi ve uzatım günü zorunludur.']);
                break;
            }
            $db = (new HakedisSozlesmeModel())->getDb();
            ensureSureUzatimTablosu($db);
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    SELECT id, isin_bitecegi_tarih FROM hakedis_sozlesmeler
                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL FOR UPDATE
                ");
                $stmt->execute([$sozlesme_id, $firma_id]);
                $sozlesme = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$sozlesme || empty($sozlesme['isin_bitecegi_tarih'])) {
                    throw new RuntimeException('Sözleşmenin bitiş tarihi tanımlı olmalıdır.');
                }
                $onceki = $sozlesme['isin_bitecegi_tarih'];
                $yeni = (new DateTimeImmutable($onceki))->modify("+{$uzatim_gun} days")->format('Y-m-d');
                $noStmt = $db->prepare("SELECT COALESCE(MAX(uzatim_no), 0) + 1 FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ?");
                $noStmt->execute([$sozlesme_id]);
                $uzatim_no = intval($noStmt->fetchColumn());
                $kararNo = trim($_POST['karar_no'] ?? '') ?: null;
                $aciklama = trim($_POST['aciklama'] ?? '') ?: null;
                $stmt = $db->prepare("
                    INSERT INTO hakedis_sure_uzatimlari
                    (sozlesme_id, uzatim_no, uzatim_tarihi, karar_no, aciklama, uzatim_gun,
                     onceki_bitis_tarihi, yeni_bitis_tarihi, olusturan_personel_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$sozlesme_id, $uzatim_no, $uzatim_tarihi, $kararNo, $aciklama,
                    $uzatim_gun, $onceki, $yeni, $_SESSION['id']]);
                $sonUzatim = date('d.m.Y', strtotime($uzatim_tarihi)) . ($kararNo ? " - {$kararNo}" : '') . " (+{$uzatim_gun} gün)";
                $stmt = $db->prepare("
                    UPDATE hakedis_sozlesmeler
                    SET isin_bitecegi_tarih = ?, isin_suresi = COALESCE(isin_suresi, 0) + ?, son_sure_uzatimi = ?
                    WHERE id = ? AND firma_id = ?
                ");
                $stmt->execute([$yeni, $uzatim_gun, $sonUzatim, $sozlesme_id, $firma_id]);
                $db->commit();
                echo json_encode(['status' => 'success', 'uzatim_no' => $uzatim_no, 'yeni_bitis_tarihi' => $yeni]);
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'deleteSureUzatimi':
            $sozlesme_id = intval($_POST['sozlesme_id'] ?? 0);
            $uzatim_id = intval($_POST['uzatim_id'] ?? 0);
            $db = (new HakedisSozlesmeModel())->getDb();
            ensureSureUzatimTablosu($db);
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    SELECT u.uzatim_gun, s.isin_bitecegi_tarih
                    FROM hakedis_sure_uzatimlari u
                    INNER JOIN hakedis_sozlesmeler s ON s.id = u.sozlesme_id
                    WHERE u.id = ? AND u.sozlesme_id = ? AND s.firma_id = ? AND s.silinme_tarihi IS NULL
                    FOR UPDATE
                ");
                $stmt->execute([$uzatim_id, $sozlesme_id, $firma_id]);
                $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$kayit) throw new RuntimeException('Süre uzatımı bulunamadı veya yetkiniz yok.');

                $yeniGuncelBitis = (new DateTimeImmutable($kayit['isin_bitecegi_tarih']))
                    ->modify('-' . intval($kayit['uzatim_gun']) . ' days')->format('Y-m-d');
                $db->prepare("DELETE FROM hakedis_sure_uzatimlari WHERE id = ? AND sozlesme_id = ?")
                    ->execute([$uzatim_id, $sozlesme_id]);

                $stmt = $db->prepare("SELECT * FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ? ORDER BY uzatim_no, id");
                $stmt->execute([$sozlesme_id]);
                $kalanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $toplamGun = array_sum(array_map(static function ($u) { return intval($u['uzatim_gun']); }, $kalanlar));
                $temelBitis = (new DateTimeImmutable($yeniGuncelBitis))->modify("-{$toplamGun} days");
                $zincirStmt = $db->prepare("
                    UPDATE hakedis_sure_uzatimlari
                    SET uzatim_no = ?, onceki_bitis_tarihi = ?, yeni_bitis_tarihi = ? WHERE id = ?
                ");
                $cursor = $temelBitis;
                foreach ($kalanlar as $index => $uzatim) {
                    $onceki = $cursor->format('Y-m-d');
                    $cursor = $cursor->modify('+' . intval($uzatim['uzatim_gun']) . ' days');
                    $zincirStmt->execute([$index + 1, $onceki, $cursor->format('Y-m-d'), $uzatim['id']]);
                }
                $son = $kalanlar ? end($kalanlar) : null;
                $sonUzatim = $son
                    ? date('d.m.Y', strtotime($son['uzatim_tarihi'])) . (!empty($son['karar_no']) ? ' - ' . $son['karar_no'] : '') . ' (+' . intval($son['uzatim_gun']) . ' gün)'
                    : null;
                $stmt = $db->prepare("
                    UPDATE hakedis_sozlesmeler
                    SET isin_bitecegi_tarih = ?, isin_suresi = GREATEST(COALESCE(isin_suresi, 0) - ?, 0), son_sure_uzatimi = ?
                    WHERE id = ? AND firma_id = ?
                ");
                $stmt->execute([$yeniGuncelBitis, intval($kayit['uzatim_gun']), $sonUzatim, $sozlesme_id, $firma_id]);
                $db->commit();
                echo json_encode(['status' => 'success', 'yeni_bitis_tarihi' => $yeniGuncelBitis]);
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'deleteSozlesme':
            $id = $_POST['id'] ?? 0;
            if ($id) {
                $model = new HakedisSozlesmeModel();
                $db = $model->getDb();
                $stmt = $db->prepare("UPDATE hakedis_sozlesmeler SET silinme_tarihi = NOW() WHERE id = ? AND firma_id = ?");
                $stmt->execute([$id, $firma_id]);
                echo json_encode(['status' => 'success']);
            }
            break;

        case 'getHakedisler':
            $model = new HakedisDonemModel();
            $db = $model->getDb();

            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $sozlesme_id = $_POST['sozlesme_id'] ?? 0;
            $orderColIdx = $_POST['order'][0]['column'] ?? 0;
            $orderDir = $_POST['order'][0]['dir'] ?? 'desc';

            $columns = [
                0 => 'hakedis_no',
                1 => 'hakedis_tarihi_yil', // sort logic simplified since ay/yil split
                2 => 'temel_endeks_ayi',
                3 => 'durum',
                4 => 'id'
            ];
            $orderCol = $columns[$orderColIdx] ?? 'id';

            // Validate Sozlesme Ownership
            $stmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ?");
            $stmt->execute([$sozlesme_id, $firma_id]);
            if (!$stmt->fetch()) {
                echo json_encode(["draw" => intval($_POST['draw'] ?? 0), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
                exit;
            }

            $where = "sozlesme_id = :sozlesme_id AND silinme_tarihi IS NULL";
            $params = [':sozlesme_id' => $sozlesme_id];

            $stmt = $db->prepare("SELECT COUNT(*) FROM hakedis_donemleri WHERE $where");
            $stmt->execute($params);
            $totalRecords = $stmt->fetchColumn();

            $sql = "SELECT hd.*, 
                (SELECT SUM(m.miktar * k.teklif_edilen_birim_fiyat) 
                 FROM hakedis_miktarlari m 
                 JOIN hakedis_kalemleri k ON m.kalem_id = k.id 
                 WHERE m.hakedis_donem_id = hd.id) as imalat_donem 
                FROM hakedis_donemleri hd 
                WHERE $where 
                ORDER BY hakedis_tarihi_yil $orderDir, hakedis_tarihi_ay $orderDir 
                LIMIT :start, :length";
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as &$row) {
                $pn = 0;
                $imalat = floatval($row['imalat_donem'] ?? 0);
                
                if (floatval($row['asgari_ucret_temel']) > 0 || floatval($row['a1_katsayisi']) > 0) {
                    $a1 = floatval($row['a1_katsayisi'] ?: 0.28);
                    if (isset($row['asgari_farki_dahil_edilsin']) && $row['asgari_farki_dahil_edilsin'] == 1 && floatval($row['asgari_ucret_temel']) > 0) {
                        $pn += $a1 * (floatval($row['asgari_ucret_guncel']) / floatval($row['asgari_ucret_temel']));
                    } else {
                        $pn += $a1;
                    }
                }
                if (floatval($row['motorin_temel']) > 0) {
                    $pn += floatval($row['b1_katsayisi'] ?: 0.22) * (floatval($row['motorin_guncel']) / floatval($row['motorin_temel']));
                }
                if (floatval($row['ufe_genel_temel']) > 0) {
                    $pn += floatval($row['b2_katsayisi'] ?: 0.25) * (floatval($row['ufe_genel_guncel']) / floatval($row['ufe_genel_temel']));
                }
                if (floatval($row['makine_ekipman_temel']) > 0) {
                    $pn += floatval($row['c_katsayisi'] ?: 0.25) * (floatval($row['makine_ekipman_guncel']) / floatval($row['makine_ekipman_temel']));
                }

                $ff = 0;
                if ($pn > 1) {
                    $ff = $imalat * 0.90 * ($pn - 1);
                }
                $row['fiyat_farki'] = $ff;
            }

            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $totalRecords,
                "data" => $data
            ]);
            break;

        case 'saveHakedis':
            $model = new HakedisDonemModel();
            $db = $model->getDb();
            $sozlesme_id = $_POST['sozlesme_id'] ?? 0;
            $hakedis_id = $_POST['id'] ?? 0;

            // Verify sozlesme belongs to firma
            $stmt = $db->prepare("SELECT id FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$sozlesme_id, $firma_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz sözleşme.']);
                exit;
            }

            $data = [
                'sozlesme_id' => $sozlesme_id,
                'hakedis_no' => intval($_POST['hakedis_no'] ?? 1),
                'hakedis_tarihi_ay' => intval($_POST['hakedis_tarihi_ay'] ?? date('n')),
                'hakedis_tarihi_yil' => intval($_POST['hakedis_tarihi_yil'] ?? date('Y')),
                'is_yapilan_ayin_son_gunu' => Date::Ymd($_POST['is_yapilan_ayin_son_gunu'] ?? null),
                'tutanak_tasdik_tarihi' => Date::Ymd($_POST['tutanak_tasdik_tarihi'] ?? null),
                'temel_endeks_ayi' => $_POST['temel_endeks_ayi'] ?? '',
                'guncel_endeks_ayi' => $_POST['guncel_endeks_ayi'] ?? '',
                'olusturan_personel_id' => $_SESSION['id'],
                'durum' => $_POST['durum'] ?? 'taslak',
                'onceki_hakedis_tutari' => Helper::formattedMoneyToNumber($_POST['onceki_hakedis_tutari']) ?: 0
            ];

            if (!$hakedis_id) {
                // New Hakedis: Inherit parameters from contract
                $stmtS = $db->prepare("SELECT a1_katsayisi, b1_katsayisi, b2_katsayisi, c_katsayisi, asgari_ucret_temel, motorin_temel, ufe_genel_temel, makine_ekipman_temel, kdv_orani, tevkifat_orani FROM hakedis_sozlesmeler WHERE id = ?");
                $stmtS->execute([$sozlesme_id]);
                if ($soz = $stmtS->fetch(PDO::FETCH_ASSOC)) {
                    $data['a1_katsayisi'] = $soz['a1_katsayisi'];
                    $data['b1_katsayisi'] = $soz['b1_katsayisi'];
                    $data['b2_katsayisi'] = $soz['b2_katsayisi'];
                    $data['c_katsayisi'] = $soz['c_katsayisi'];
                    $data['asgari_ucret_temel'] = $soz['asgari_ucret_temel'];
                    $data['motorin_temel'] = $soz['motorin_temel'];
                    $data['ufe_genel_temel'] = $soz['ufe_genel_temel'];
                    $data['makine_ekipman_temel'] = $soz['makine_ekipman_temel'];
                    $data['kdv_orani'] = $soz['kdv_orani'];
                    $data['tevkifat_orani'] = $soz['tevkifat_orani'];
                }

                // EPDK'dan hakediş ayı/yılına göre güncel endeks verilerini otomatik çek
                require_once __DIR__ . '/endeks_api/akaryakit.php';
                require_once __DIR__ . '/endeks_api/hizmet_endeks.php';

                $hakedisAy = intval($data['hakedis_tarihi_ay']);
                $hakedisYil = intval($data['hakedis_tarihi_yil']);

                // Motorin Güncel (EPDK Akaryakıt)
                $motorinFiyat = getEpdkMotorinFiyati($hakedisYil, $hakedisAy);
                if ($motorinFiyat !== null) {
                    $data['motorin_guncel'] = $motorinFiyat;
                }

                // Asgari Ücret Güncel, Yİ-ÜFE Güncel, Makine-Ekipman Güncel (Hizmet İşleri Endeksleri)
                $endeksler = getHizmetEndeksleri($hakedisYil, $hakedisAy);
                if ($endeksler['asgari_ucret'] !== null) {
                    $data['asgari_ucret_guncel'] = $endeksler['asgari_ucret'];
                }
                if ($endeksler['ufe'] !== null) {
                    $data['ufe_genel_guncel'] = $endeksler['ufe'];
                }
                if ($endeksler['makine'] !== null) {
                    $data['makine_ekipman_guncel'] = $endeksler['makine'];
                }

                // Otomatik "Önceki Hakediş Tutarı" hesapla (Eğer girilmemişse)
                if (empty($_POST['onceki_hakedis_tutari'])) {
                    $stmtPrev = $db->prepare("
                        SELECT SUM(hm.miktar * hk.teklif_edilen_birim_fiyat) as toplam
                        FROM hakedis_miktarlari hm
                        JOIN hakedis_donemleri hd ON hm.hakedis_donem_id = hd.id
                        JOIN hakedis_kalemleri hk ON hm.kalem_id = hk.id
                        WHERE hd.sozlesme_id = ? AND hd.hakedis_no < ? AND hd.silinme_tarihi IS NULL
                    ");
                    $stmtPrev->execute([$sozlesme_id, $data['hakedis_no']]);
                    $data['onceki_hakedis_tutari'] = floatval($stmtPrev->fetchColumn() ?? 0);
                }
            }

            if ($hakedis_id) {
                // Tarih değiştiyse veya endekslerde eksiklik varsa, açıklandığı varsayımıyla verileri API'den otomatik çek/güncelle
                $stmtCheckDate = $db->prepare("SELECT hakedis_tarihi_ay, hakedis_tarihi_yil, asgari_ucret_guncel, motorin_guncel, ufe_genel_guncel, makine_ekipman_guncel FROM hakedis_donemleri WHERE id = ?");
                $stmtCheckDate->execute([$hakedis_id]);
                if ($currDate = $stmtCheckDate->fetch(PDO::FETCH_ASSOC)) {
                    $dateChanged = ($currDate['hakedis_tarihi_ay'] != $data['hakedis_tarihi_ay'] || $currDate['hakedis_tarihi_yil'] != $data['hakedis_tarihi_yil']);
                    $missingData = (empty($currDate['asgari_ucret_guncel']) || empty($currDate['motorin_guncel']) || empty($currDate['ufe_genel_guncel']) || empty($currDate['makine_ekipman_guncel']));
                    
                    if ($dateChanged || $missingData) {
                        require_once __DIR__ . '/endeks_api/akaryakit.php';
                        require_once __DIR__ . '/endeks_api/hizmet_endeks.php';

                        $hakedisAy = intval($data['hakedis_tarihi_ay']);
                        $hakedisYil = intval($data['hakedis_tarihi_yil']);
                        
                        if ($dateChanged) {
                            // Ay kasten değiştirildiyse eski verilerin bir anlamı kalmaz, temizle ve yenilerini çek.
                            $data['motorin_guncel'] = null;
                            $data['asgari_ucret_guncel'] = null;
                            $data['ufe_genel_guncel'] = null;
                            $data['makine_ekipman_guncel'] = null;
                        }

                        $motorinFiyat = getEpdkMotorinFiyati($hakedisYil, $hakedisAy);
                        if ($motorinFiyat !== null) {
                            $data['motorin_guncel'] = $motorinFiyat;
                        }
                        
                        $endeksler = getHizmetEndeksleri($hakedisYil, $hakedisAy);
                        if ($endeksler['asgari_ucret'] !== null) {
                            $data['asgari_ucret_guncel'] = $endeksler['asgari_ucret'];
                        }
                        if ($endeksler['ufe'] !== null) {
                            $data['ufe_genel_guncel'] = $endeksler['ufe'];
                        }
                        if ($endeksler['makine'] !== null) {
                            $data['makine_ekipman_guncel'] = $endeksler['makine'];
                        }
                    }
                }

                // Update
                $set = [];
                $params = [];
                foreach ($data as $k => $v) {
                    $set[] = "$k = :$k";
                    $params[":$k"] = $v;
                }
                $params[':id'] = $hakedis_id;
                $sql = "UPDATE hakedis_donemleri SET " . implode(", ", $set) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $resultId = $hakedis_id;
            } else {
                // Insert
                $data['olusturma_tarihi'] = date('Y-m-d H:i:s');
                $cols = implode(", ", array_keys($data));
                $vals = ":" . implode(", :", array_keys($data));
                $sql = "INSERT INTO hakedis_donemleri ($cols) VALUES ($vals)";
                $stmt = $db->prepare($sql);
                $stmt->execute($data);
                $resultId = $db->lastInsertId();
            }

            // Hakediş onaylandığında (durum='tamamlandi') toplam tutarı hesapla ve kaydet
            if ($data['durum'] == 'tamamlandi') {
                $totals = $model->calculateTotals($resultId);
                if ($totals) {
                    $toplamHakedis = $totals['imalat_kumulatif'] ?? 0;
                    
                    // hakedi_tutari alanını güncelle
                    $stmtUpdateTotal = $db->prepare("UPDATE hakedis_donemleri SET hakedi_tutari = ? WHERE id = ?");
                    $stmtUpdateTotal->execute([$toplamHakedis, $resultId]);
                }
            }

            echo json_encode(['status' => 'success', 'hakedis_id' => $resultId]);
            break;

        case 'getHakedis':
            $id = $_POST['id'] ?? 0;
            if ($id) {
                $db = (new HakedisDonemModel())->getDb();
                $stmt = $db->prepare("
                    SELECT hd.* FROM hakedis_donemleri hd
                    JOIN hakedis_sozlesmeler hs ON hd.sozlesme_id = hs.id
                    WHERE hd.id = ? AND hs.firma_id = ?
                ");
                $stmt->execute([$id, $firma_id]);
                $hakedis = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($hakedis) {
                    echo json_encode(['status' => 'success', 'data' => $hakedis]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Hakediş bulunamadı.']);
                }
            }
            break;

        case 'deleteHakedis':
            $id = $_POST['id'] ?? 0;
            if ($id) {
                // Determine if hakedis belongs to a sozlesme of this firm and get status
                $model = new HakedisDonemModel();
                $db = $model->getDb();
                $stmt = $db->prepare("
                    SELECT hd.id, hd.durum FROM hakedis_donemleri hd
                    JOIN hakedis_sozlesmeler hs ON hd.sozlesme_id = hs.id
                    WHERE hd.id = ? AND hs.firma_id = ?
                ");
                $stmt->execute([$id, $firma_id]);
                $hakedis = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($hakedis) {
                    if ($hakedis['durum'] == 'tamamlandi') {
                        echo json_encode(['status' => 'error', 'message' => 'Tamamlanmış hakedişler silinemez. Önce durumu değiştirmeniz gerekir.']);
                        break;
                    }

                    $stmt = $db->prepare("UPDATE hakedis_donemleri SET silinme_tarihi = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Yetkisiz işlem.']);
                }
            }
            break;

        case 'updateHakedisParametreler':
            $model = new HakedisDonemModel();
            $db = $model->getDb();
            $id = $_POST['hakedis_id'] ?? 0;

            $stmt = $db->prepare("SELECT id FROM hakedis_donemleri WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Hakediş bulunamadı.']);
                exit;
            }

            $fields = [
                'a1_katsayisi',
                'a2_katsayisi',
                'b1_katsayisi',
                'b2_katsayisi',
                'c_katsayisi',
                'asgari_ucret_temel',
                'asgari_ucret_guncel',
                'motorin_temel',
                'motorin_guncel',
                'ufe_genel_temel',
                'ufe_genel_guncel',
                'makine_ekipman_temel',
                'makine_ekipman_guncel',
                'tevkifat_orani',
                'kdv_orani',
                'asgari_farki_dahil_edilsin'
            ];

            $set = [];
            $params = [];
            foreach ($fields as $f) {
                if ($f === 'asgari_farki_dahil_edilsin') {
                    $set[] = "$f = ?$f";
                    $params["?$f"] = isset($_POST[$f]) ? 1 : 0;
                } else if (isset($_POST[$f])) {
                    $set[] = "$f = ?$f";
                    $params["?$f"] = $_POST[$f] === '' ? null : $_POST[$f];
                }
            }

            // Handle ekstra parametreler
            $ekstra = [
                'temel' => $_POST['ekstra_temel'] ?? [],
                'guncel' => $_POST['ekstra_guncel'] ?? []
            ];

            $jsonEkstra = !empty($ekstra['temel']) || !empty($ekstra['guncel']) ? json_encode($ekstra, JSON_UNESCAPED_UNICODE) : null;
            $set[] = "ekstra_parametreler = ?ekstra_parametreler";
            $params["?ekstra_parametreler"] = $jsonEkstra;

            // replace variables to bindings manually
            $setStr = "";
            $vals = [];
            foreach ($params as $key => $val) {
                $n = substr($key, 1);
                $setStr .= "$n = ?, ";
                $vals[] = $val;
            }
            $setStr = rtrim($setStr, ", ");
            $vals[] = $id;

            if ($setStr) {
                $sql = "UPDATE hakedis_donemleri SET $setStr WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($vals);
            }

            echo json_encode(['status' => 'success']);
            break;

        case 'saveKalem':
            $model = new HakedisKalemModel();
            $db = $model->getDb();

            $sozlesme_id = $_POST['sozlesme_id'] ?? 0;

            $data = [
                'sozlesme_id' => $sozlesme_id,
                'poz_no' => $_POST['poz_no'] ?? '',
                'kalem_adi' => $_POST['kalem_adi'] ?? '',
                'birim' => $_POST['birim'] ?? '',
                'teklif_edilen_birim_fiyat' => floatval($_POST['teklif_edilen_birim_fiyat'] ?? 0),
                'miktari' => floatval($_POST['miktari'] ?? 0)
            ];

            $cols = implode(", ", array_keys($data));
            $vals = ":" . implode(", :", array_keys($data));
            $sql = "INSERT INTO hakedis_kalemleri ($cols) VALUES ($vals)";
            $stmt = $db->prepare($sql);
            $stmt->execute($data);

            echo json_encode(['status' => 'success']);
            break;

        case 'updateKalem':
            $model = new HakedisKalemModel();
            $db = $model->getDb();

            $id = $_POST['kalem_id'] ?? 0;
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Kalem ID bulunamadı']);
                exit;
            }

            $data = [
                'poz_no' => $_POST['poz_no'] ?? '',
                'kalem_adi' => $_POST['kalem_adi'] ?? '',
                'birim' => $_POST['birim'] ?? '',
                'miktari' => floatval($_POST['miktari'] ?? 0),
                'teklif_edilen_birim_fiyat' => floatval($_POST['teklif_edilen_birim_fiyat'] ?? 0)
            ];

            $set = [];
            foreach ($data as $k => $v) {
                $set[] = "$k = :$k";
            }
            $data['id'] = $id;

            $sql = "UPDATE hakedis_kalemleri SET " . implode(", ", $set) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($data);

            echo json_encode(['status' => 'success']);
            break;

        case 'updateMiktar':
            $db = (new HakedisMiktarModel())->getDb();
            $hakedis_id = $_POST['hakedis_id'] ?? 0;
            $kalem_id = $_POST['kalem_id'] ?? 0;
            $miktar = isset($_POST['miktar']) ? floatval($_POST['miktar']) : null;
            $onceki_miktar = isset($_POST['onceki_miktar']) ? floatval($_POST['onceki_miktar']) : null;
            $bolge = $_POST['bolge'] ?? 'Genel';

            $stmt = $db->prepare("SELECT id, miktar, onceki_miktar FROM hakedis_miktarlari WHERE hakedis_donem_id = ? AND kalem_id = ? AND bolge_adi = ?");
            $stmt->execute([$hakedis_id, $kalem_id, $bolge]);

            if ($row = $stmt->fetch()) {
                $m = $miktar !== null ? $miktar : $row['miktar'];
                $om = $onceki_miktar !== null ? $onceki_miktar : $row['onceki_miktar'];

                $stmt2 = $db->prepare("UPDATE hakedis_miktarlari SET miktar = ?, onceki_miktar = ? WHERE id = ?");
                $stmt2->execute([$m, $om, $row['id']]);
            } else {
                $stmt2 = $db->prepare("INSERT INTO hakedis_miktarlari (hakedis_donem_id, kalem_id, bolge_adi, miktar, onceki_miktar) VALUES (?, ?, ?, ?, ?)");
                $stmt2->execute([$hakedis_id, $kalem_id, $bolge, $miktar ?? 0, $onceki_miktar ?? 0]);
            }
            echo json_encode(['status' => 'success']);
            break;

        case 'getHakedisKalemler':
            $db = (new HakedisKalemModel())->getDb();
            $sozlesme_id = $_POST['sozlesme_id'] ?? 0;
            $hakedis_id = $_POST['hakedis_id'] ?? 0;

            // 1. Mevcut hakediş bilgisini al
            $stmtHakedis = $db->prepare("SELECT hakedis_no FROM hakedis_donemleri WHERE id = ?");
            $stmtHakedis->execute([$hakedis_id]);
            $hNo = $stmtHakedis->fetchColumn();

            if (!$hNo) {
                echo json_encode(['status' => 'error', 'message' => 'Hakediş Dönemi hatalı.']);
                exit;
            }

            // 2. Bir önceki hakedişi bul (Varsa)
            $stmtPrev = $db->prepare("SELECT id FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no < ? AND silinme_tarihi IS NULL ORDER BY hakedis_no DESC LIMIT 1");
            $stmtPrev->execute([$sozlesme_id, $hNo]);
            $prevHakedisId = $stmtPrev->fetchColumn();

            // 3. Sözleşmeye ait tüm kalemleri çek
            $stmtKalem = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = :sid");
            $stmtKalem->execute([':sid' => $sozlesme_id]);
            $kalemler = $stmtKalem->fetchAll(PDO::FETCH_ASSOC);

            // 4. Miktarları çek (Mevcut dönem için)
            $miktarlarMap = [];
            $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id = ?");
            $stmtMiktar->execute([$hakedis_id]);
            while ($m = $stmtMiktar->fetch(PDO::FETCH_ASSOC)) {
                $miktarlarMap[$m['kalem_id']] = $m;
            }

            // 5. Tüm önceki miktarları kalem bazlı topla (Kümülatif doğruluk için)
            $prevMiktarlarSum = [];
            $stmtPrevSum = $db->prepare("
                SELECT m.kalem_id, SUM(m.miktar) as toplam_prev
                FROM hakedis_miktarlari m
                JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
                WHERE d.sozlesme_id = ? AND d.hakedis_no < ? AND d.silinme_tarihi IS NULL
                GROUP BY m.kalem_id
            ");
            $stmtPrevSum->execute([$sozlesme_id, $hNo]);
            while ($row = $stmtPrevSum->fetch(PDO::FETCH_ASSOC)) {
                $prevMiktarlarSum[$row['kalem_id']] = floatval($row['toplam_prev']);
            }

            // 6. İlk hakedişteki (hno=1) başlangıç 'onceki_miktar' değerlerini al
            $baslangicMiktarlari = [];
            $stmtBaslangic = $db->prepare("
                SELECT m.kalem_id, m.onceki_miktar
                FROM hakedis_miktarlari m
                JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
                WHERE d.sozlesme_id = ? AND d.hakedis_no = 1 AND d.silinme_tarihi IS NULL
            ");
            $stmtBaslangic->execute([$sozlesme_id]);
            while ($row = $stmtBaslangic->fetch(PDO::FETCH_ASSOC)) {
                $baslangicMiktarlari[$row['kalem_id']] = floatval($row['onceki_miktar']);
            }

            // 7. Sonuçları oluştur
            $sonuc = [];
            foreach ($kalemler as $k) {
                $kalem_id = $k['id'];

                // Bu ayki miktar
                $curMiktarRow = $miktarlarMap[$kalem_id] ?? null;
                $buay_toplam = floatval($curMiktarRow['miktar'] ?? 0);

                // Önceki toplam miktar
                $onceki_toplam = 0;
                if ($curMiktarRow && isset($curMiktarRow['onceki_miktar']) && $curMiktarRow['onceki_miktar'] != 0) {
                    $onceki_toplam = floatval($curMiktarRow['onceki_miktar']);
                } else {
                    $prevSum = $prevMiktarlarSum[$kalem_id] ?? 0;
                    $baslangic = $baslangicMiktarlari[$kalem_id] ?? 0;
                    $onceki_toplam = $prevSum + $baslangic;
                }

                $k['onceki_miktar'] = $onceki_toplam;
                $k['bu_ay_miktar'] = $buay_toplam;
                $sonuc[] = $k;
            }

            // --- Fiyat Farkı ve Toplam Hesapları (Merkezi Metod) ---
            $donemModel = new HakedisDonemModel();
            $totals = $donemModel->calculateTotals($hakedis_id);

            echo json_encode([
                'status' => 'success', 
                'data' => $sonuc, 
                'fiyat_farki' => $totals['fiyat_farki'] ?? 0,
                'imalat_kumulatif' => $totals['imalat_kumulatif'] ?? 0,
                'imalat_donem' => $totals['imalat_donem'] ?? 0,
                'kdv_dahil_toplam' => $totals['kdv_dahil_toplam'] ?? 0
            ]);
            break;

        case 'deleteKalem':
            $db = (new HakedisKalemModel())->getDb();
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM hakedis_miktarlari WHERE kalem_id = ?");
            $stmt->execute([$id]);
            $stmt = $db->prepare("DELETE FROM hakedis_kalemleri WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
            break;

        case 'fetchEndeksForHakedis':
            $hakedisAy = intval($_POST['hakedis_tarihi_ay'] ?? date('n'));
            $hakedisYil = intval($_POST['hakedis_tarihi_yil'] ?? date('Y'));

            require_once __DIR__ . '/endeks_api/akaryakit.php';
            require_once __DIR__ . '/endeks_api/hizmet_endeks.php';

            $sonuc = [
                'asgari_ucret_guncel' => null,
                'motorin_guncel' => null,
                'ufe_genel_guncel' => null,
                'makine_ekipman_guncel' => null,
                'message' => ''
            ];

            // Motorin Güncel (EPDK Akaryakıt)
            $motorinFiyat = getEpdkMotorinFiyati($hakedisYil, $hakedisAy);
            if ($motorinFiyat !== null) {
                $sonuc['motorin_guncel'] = $motorinFiyat;
            }

            // Asgari Ücret Güncel, Yİ-ÜFE Güncel, Makine-Ekipman Güncel (Hizmet İşleri Endeksleri)
            $endeksler = getHizmetEndeksleri($hakedisYil, $hakedisAy);
            if ($endeksler['asgari_ucret'] !== null) {
                $sonuc['asgari_ucret_guncel'] = $endeksler['asgari_ucret'];
            }
            if ($endeksler['ufe'] !== null) {
                $sonuc['ufe_genel_guncel'] = $endeksler['ufe'];
            }
            if ($endeksler['makine'] !== null) {
                $sonuc['makine_ekipman_guncel'] = $endeksler['makine'];
            }

            $eksikVar = false;
            foreach ($sonuc as $k => $v) {
                if ($k !== 'message' && $v === null) {
                    $eksikVar = true;
                }
            }

            if ($eksikVar) {
                $sonuc['message'] = 'Bazı endeks verileri bu ay için henüz açıklanmamış olabilir.';
            }

            echo json_encode(['status' => 'success', 'data' => $sonuc]);
            break;
        
        case 'uploadHakedisTemplate':
            if (isset($_FILES['templateFile']) && $_FILES['templateFile']['error'] === UPLOAD_ERR_OK) {
                $tempPath = $_FILES['templateFile']['tmp_name'];
                $targetPath = __DIR__ . '/Hakedis.xlsx';
                
                // Backup existing? User didn't ask for it but it's safer.
                // However, they specifically said they want to replace it.
                if (move_uploaded_file($tempPath, $targetPath)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Dosya şablon klasörüne kopyalanamadı.']);
                }
            } else {
                $errorMsg = 'Dosya yüklenemedi.';
                if (isset($_FILES['templateFile'])) {
                    $errorMsg .= ' Hata Kodu: ' . $_FILES['templateFile']['error'];
                }
                echo json_encode(['status' => 'error', 'message' => $errorMsg]);
            }
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem tipi.']);
            break;
    }
} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
