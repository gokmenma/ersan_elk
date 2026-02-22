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
                'sozlesme_tarihi' => !empty($_POST['sozlesme_tarihi']) ? $_POST['sozlesme_tarihi'] : null,
                'isin_bitecegi_tarih' => !empty($_POST['isin_bitecegi_tarih']) ? $_POST['isin_bitecegi_tarih'] : null,
                'ihale_tarihi' => !empty($_POST['ihale_tarihi']) ? $_POST['ihale_tarihi'] : null,
                'yer_teslim_tarihi' => !empty($_POST['yer_teslim_tarihi']) ? $_POST['yer_teslim_tarihi'] : null,
                'isin_suresi' => !empty($_POST['isin_suresi']) ? intval($_POST['isin_suresi']) : null,
                'kontrol_teskilati' => $_POST['kontrol_teskilati'] ?? '',
                'idare_onaylayan' => $_POST['idare_onaylayan'] ?? '',
                'idare_onaylayan_unvan' => $_POST['idare_onaylayan_unvan'] ?? '',
                'durum' => $_POST['durum'] ?? 'aktif'
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
            }

            echo json_encode(['status' => 'success']);
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
                'is_yapilan_ayin_son_gunu' => !empty($_POST['is_yapilan_ayin_son_gunu']) ? $_POST['is_yapilan_ayin_son_gunu'] : null,
                'temel_endeks_ayi' => $_POST['temel_endeks_ayi'] ?? '',
                'guncel_endeks_ayi' => $_POST['guncel_endeks_ayi'] ?? '',
                'olusturan_personel_id' => $_SESSION['id'],
                'durum' => 'taslak'
            ];

            $data['olusturma_tarihi'] = date('Y-m-d H:i:s');
            $cols = implode(", ", array_keys($data));
            $vals = ":" . implode(", :", array_keys($data));
            $sql = "INSERT INTO hakedis_donemleri ($cols) VALUES ($vals)";
            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            $hakedisId = $db->lastInsertId();

            echo json_encode(['status' => 'success', 'hakedis_id' => $hakedisId]);
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
                'miktari' => floatval($_POST['hedef_miktari'] ?? 0)
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
            $miktar = floatval($_POST['miktar'] ?? 0);
            $bolge = $_POST['bolge'] ?? 'Genel'; // Excel'deki gibi KASKİ1 vs olabilir

            // Eğer daha evvel bu dönemde bu kalem için girildiyse update, yoksa insert
            $stmt = $db->prepare("SELECT id FROM hakedis_miktarlari WHERE hakedis_donem_id = ? AND kalem_id = ? AND bolge_adi = ?");
            $stmt->execute([$hakedis_id, $kalem_id, $bolge]);
            if ($row = $stmt->fetch()) {
                $stmt2 = $db->prepare("UPDATE hakedis_miktarlari SET miktar = ? WHERE id = ?");
                $stmt2->execute([$miktar, $row['id']]);
            } else {
                $stmt2 = $db->prepare("INSERT INTO hakedis_miktarlari (hakedis_donem_id, kalem_id, bolge_adi, miktar) VALUES (?, ?, ?, ?)");
                $stmt2->execute([$hakedis_id, $kalem_id, $bolge, $miktar]);
            }
            echo json_encode(['status' => 'success']);
            break;

        case 'getHakedisKalemler':
            $db = (new HakedisKalemModel())->getDb();
            $sozlesme_id = $_POST['sozlesme_id'] ?? 0;
            $hakedis_id = $_POST['hakedis_id'] ?? 0;

            // Sözleşmeye ait tüm kalemleri çek
            // Her kalem için İki bilgi lazım:
            // 1) Aynı sözleşmede, bu hakedişten (tarih/sıra olarak) "önceki" hakedişlerde gerçekleşmiş olan toplam miktar (Önceki İcmal)
            // 2) Bu hakedişte gerçekleşen miktar (Bu Ayki)

            // Hakediş bilgisini bul, özellikle no'ya göre önceki dönemi filtreleyeceğz
            $stmtHakedis = $db->prepare("SELECT hakedis_no FROM hakedis_donemleri WHERE id = ?");
            $stmtHakedis->execute([$hakedis_id]);
            $hNo = $stmtHakedis->fetchColumn();

            if (!$hNo) {
                echo json_encode(['status' => 'error', 'message' => 'Hakediş Dönemi hatalı.']);
                exit;
            }

            // Önce kalemleri alalım
            $stmtKalem = $db->prepare("SELECT * FROM hakedis_kalemleri WHERE sozlesme_id = :sid");
            $stmtKalem->execute([':sid' => $sozlesme_id]);
            $kalemler = $stmtKalem->fetchAll(PDO::FETCH_ASSOC);

            // Tüm Miktarları tek seferde çekip map yapalım (Sözleşmeye ait tüm hakedişleri bul)
            $sqlMiktar = "
               SELECT 
                    m.kalem_id, 
                    d.hakedis_no, 
                    m.miktar 
               FROM hakedis_miktarlari m
               JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
               WHERE d.sozlesme_id = ? AND d.silinme_tarihi IS NULL
            ";
            $stmtMiktar = $db->prepare($sqlMiktar);
            $stmtMiktar->execute([$sozlesme_id]);
            $allMiktar = $stmtMiktar->fetchAll(PDO::FETCH_ASSOC);

            // Calculate sums
            $sonuc = [];
            foreach ($kalemler as $k) {
                $onceki_toplam = 0;
                $buay_toplam = 0;

                foreach ($allMiktar as $m) {
                    if ($m['kalem_id'] == $k['id']) {
                        if ($m['hakedis_no'] < $hNo) {
                            $onceki_toplam += $m['miktar'];
                        } elseif ($m['hakedis_no'] == $hNo) {
                            $buay_toplam += $m['miktar'];
                        }
                    }
                }

                $k['onceki_miktar'] = $onceki_toplam;
                $k['bu_ay_miktar'] = $buay_toplam;
                $sonuc[] = $k;
            }

            // --- Fiyat Farkı Hesabı (Basit Simülasyon / Mockup) ---
            // Genelde KASKİ Excelinde (Pn - 1) * Tutar formülü bulunur
            // Pn = a1*(G/Go) + b1*(M/Mo) + c*(ÜFE/ÜFEo)
            $fiyat_farki = 0;

            // Önce hakediş paramlarını okuyalım
            $stmtParam = $db->prepare("SELECT * FROM hakedis_donemleri WHERE id = ?");
            $stmtParam->execute([$hakedis_id]);
            $params = $stmtParam->fetch(PDO::FETCH_ASSOC);

            if ($params && floatval($params['asgari_ucret_temel']) > 0 && floatval($params['asgari_ucret_guncel']) > 0) {
                $a1 = floatval($params['a1_katsayisi'] ?? 0.28);

                $I = floatval($params['asgari_ucret_guncel']);
                $Io = floatval($params['asgari_ucret_temel']);

                $pn = $a1 * ($I / $Io);

                // Calculate bu ay imalat total
                $imalatBuAy = 0;
                foreach ($sonuc as $k) {
                    $imalatBuAy += floatval($k['bu_ay_miktar']) * floatval($k['teklif_edilen_birim_fiyat']);
                }

                if ($pn > 1) {
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
