<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
session_start();

use App\Model\HakedisSozlesmeModel;
use App\Model\HakedisDonemModel;
use App\Model\HakedisKalemModel;
use App\Model\HakedisMiktarModel;

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
                'isin_adi' => $_POST['isin_adi'] ?? '',
                'isin_yuklenicisi' => $_POST['isin_yuklenicisi'] ?? '',
                'ihale_kayit_no' => $_POST['ihale_kayit_no'] ?? '',
                'kesif_bedeli' => !empty($_POST['kesif_bedeli']) ? floatval($_POST['kesif_bedeli']) : null,
                'ihale_tenzilati' => !empty($_POST['ihale_tenzilati']) ? floatval($_POST['ihale_tenzilati']) : null,
                'sozlesme_bedeli' => !empty($_POST['sozlesme_bedeli']) ? floatval($_POST['sozlesme_bedeli']) : null,
                'sozlesme_tarihi' => convertDateToDb($_POST['sozlesme_tarihi'] ?? null),
                'isin_bitecegi_tarih' => convertDateToDb($_POST['isin_bitecegi_tarih'] ?? null),
                'ihale_tarihi' => convertDateToDb($_POST['ihale_tarihi'] ?? null),
                'yer_teslim_tarihi' => convertDateToDb($_POST['yer_teslim_tarihi'] ?? null),
                'isin_suresi' => !empty($_POST['isin_suresi']) ? intval($_POST['isin_suresi']) : null,
                'kontrol_teskilati' => $_POST['kontrol_teskilati'] ?? '',
                'idare_onaylayan' => $_POST['idare_onaylayan'] ?? '',
                'idare_onaylayan_unvan' => $_POST['idare_onaylayan_unvan'] ?? '',
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
                    $islenen_idleri = [];
                    foreach ($kalemler as $k) {
                        $k_id = isset($k['id']) && $k['id'] > 0 ? (int) $k['id'] : 0;

                        if ($k_id > 0) {
                            // Güncelle
                            $stmtExt = $db->prepare("UPDATE hakedis_kalemleri SET kalem_adi = ?, birim = ?, miktari = ?, teklif_edilen_birim_fiyat = ? WHERE id = ? AND sozlesme_id = ?");
                            $stmtExt->execute([$k['kalem_adi'], $k['birim'], floatval($k['miktari']), floatval($k['teklif_edilen_birim_fiyat']), $k_id, $sid]);
                            $islenen_idleri[] = $k_id;
                        } else {
                            // Yeni ekle
                            $stmtExt = $db->prepare("INSERT INTO hakedis_kalemleri (sozlesme_id, kalem_adi, birim, miktari, teklif_edilen_birim_fiyat) VALUES (?, ?, ?, ?, ?)");
                            $stmtExt->execute([$sid, $k['kalem_adi'], $k['birim'], floatval($k['miktari']), floatval($k['teklif_edilen_birim_fiyat'])]);
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
                $stmt = $db->prepare("SELECT * FROM hakedis_sozlesmeler WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
                $stmt->execute([$id, $firma_id]);
                $sozlesme = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sozlesme) {
                    $stmtK = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = ? ORDER BY id ASC");
                    $stmtK->execute([$id]);
                    $kalemler = $stmtK->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['status' => 'success', 'data' => $sozlesme, 'kalemler' => $kalemler]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Sözleşme bulunamadı veya yetkiniz yok.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID eksik']);
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

            $sql = "SELECT * FROM hakedis_donemleri WHERE $where ORDER BY hakedis_tarihi_yil $orderDir, hakedis_tarihi_ay $orderDir LIMIT :start, :length";
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
                'is_yapilan_ayin_son_gunu' => convertDateToDb($_POST['is_yapilan_ayin_son_gunu'] ?? null),
                'temel_endeks_ayi' => $_POST['temel_endeks_ayi'] ?? '',
                'guncel_endeks_ayi' => $_POST['guncel_endeks_ayi'] ?? '',
                'olusturan_personel_id' => $_SESSION['id'],
                'durum' => $_POST['durum'] ?? 'taslak'
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
            }

            if ($hakedis_id) {
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
                // Determine if hakedis belongs to a sozlesme of this firm
                $model = new HakedisDonemModel();
                $db = $model->getDb();
                $stmt = $db->prepare("
                    SELECT hd.id FROM hakedis_donemleri hd
                    JOIN hakedis_sozlesmeler hs ON hd.sozlesme_id = hs.id
                    WHERE hd.id = ? AND hs.firma_id = ?
                ");
                $stmt->execute([$id, $firma_id]);
                if ($stmt->fetch()) {
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
                'kdv_orani'
            ];

            $set = [];
            $params = [];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
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
                'kalem_adi' => $_POST['kalem_adi'] ?? '',
                'birim' => $_POST['birim'] ?? '',
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

            // 4. Miktarları çek (Mevcut ve bir önceki dönem için)
            $relevantDonemIds = array_filter([$hakedis_id, $prevHakedisId]);
            $miktarlarMap = [];
            if (!empty($relevantDonemIds)) {
                $placeholders = implode(',', array_fill(0, count($relevantDonemIds), '?'));
                $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id IN ($placeholders)");
                $stmtMiktar->execute(array_values($relevantDonemIds));
                $allMiktar = $stmtMiktar->fetchAll(PDO::FETCH_ASSOC);

                foreach ($allMiktar as $m) {
                    $miktarlarMap[$m['hakedis_donem_id']][$m['kalem_id']] = $m;
                }
            }

            // 5. Sonuçları oluştur
            $sonuc = [];
            foreach ($kalemler as $k) {
                $kalem_id = $k['id'];

                // Bu ayki miktar
                $curMiktarRow = $miktarlarMap[$hakedis_id][$kalem_id] ?? null;
                $buay_toplam = floatval($curMiktarRow['miktar'] ?? 0);

                // Önceki toplam miktar
                // Eğer bu ay için bir manuel override girildiyse (onceki_miktar) onu kullan
                // Yoksa bir önceki hakedişin (onceki + mevcut) toplamını kullan
                $onceki_toplam = 0;
                if ($curMiktarRow && isset($curMiktarRow['onceki_miktar']) && $curMiktarRow['onceki_miktar'] != 0) {
                    $onceki_toplam = floatval($curMiktarRow['onceki_miktar']);
                } else if ($prevHakedisId) {
                    $prevMiktarRow = $miktarlarMap[$prevHakedisId][$kalem_id] ?? null;
                    if ($prevMiktarRow) {
                        $onceki_toplam = floatval($prevMiktarRow['onceki_miktar'] ?? 0) + floatval($prevMiktarRow['miktar'] ?? 0);
                    }
                }

                $k['onceki_miktar'] = $onceki_toplam;
                $k['bu_ay_miktar'] = $buay_toplam;
                $sonuc[] = $k;
            }

            // --- Fiyat Farkı Hesabı (Pn Katsayısı) ---
            // Pn = a1*(In/Io) + b1*(Mn/Mo) + b2*(ÜFEn/ÜFEo) + c*(En/Eo)
            $fiyat_farki = 0;
            $pn = 1;

            // Önce hakediş paramlarını okuyalım
            $stmtParam = $db->prepare("SELECT * FROM hakedis_donemleri WHERE id = ?");
            $stmtParam->execute([$hakedis_id]);
            $params = $stmtParam->fetch(PDO::FETCH_ASSOC);

            if ($params) {
                // Her bir çarpanın pay/payda kontrolünü yaparak Pn hesapla
                $p1 = 0;

                // a1 (İşçilik)
                if (floatval($params['asgari_ucret_temel']) > 0) {
                    $p1 += floatval($params['a1_katsayisi'] ?? 0.28) * (floatval($params['asgari_ucret_guncel']) / floatval($params['asgari_ucret_temel']));
                }

                // b1 (Motorin)
                if (floatval($params['motorin_temel']) > 0) {
                    $p1 += floatval($params['b1_katsayisi'] ?? 0.22) * (floatval($params['motorin_guncel']) / floatval($params['motorin_temel']));
                }

                // b2 (Yİ-ÜFE)
                if (floatval($params['ufe_genel_temel']) > 0) {
                    $p1 += floatval($params['b2_katsayisi'] ?? 0.25) * (floatval($params['ufe_genel_guncel']) / floatval($params['ufe_genel_temel']));
                }

                // c (Makine-Ekipman)
                if (floatval($params['makine_ekipman_temel']) > 0) {
                    $p1 += floatval($params['c_katsayisi'] ?? 0.25) * (floatval($params['makine_ekipman_guncel']) / floatval($params['makine_ekipman_temel']));
                }

                $pn = $p1;

                // Calculate bu ay imalat total
                $imalatBuAy = 0;
                foreach ($sonuc as $k) {
                    $imalatBuAy += floatval($k['bu_ay_miktar']) * floatval($k['teklif_edilen_birim_fiyat']);
                }

                if ($pn > 1) {
                    // Fiyat Farkı = Tutar * 0.90 * (Pn - 1)
                    $fiyat_farki = $imalatBuAy * 0.90 * ($pn - 1);
                }
            }

            echo json_encode(['status' => 'success', 'data' => $sonuc, 'fiyat_farki' => $fiyat_farki]);
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

        default:
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem tipi.']);
            break;
    }
} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
