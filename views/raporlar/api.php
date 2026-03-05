<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;

// header('Content-Type: application/json; charset=utf-8'); // Set conditionally later

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Güvenlik: Firma ID kontrolü
    $firmaId = $_SESSION['firma_id'] ?? 0;
    if ($firmaId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Firma bilgisi bulunamadı.']);
        exit;
    }

    $izinModel = new PersonelIzinleriModel();
    $db = $izinModel->getDb();

    try {
        if ($action === 'delete-rapor-satir') {
            if (!\App\Service\Gate::allows("toplu_raporlar_satir_silme")) {
                echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz bulunmamaktadır.']);
                exit;
            }

            $id = intval($_POST['id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $aciklama = $_POST['aciklama'] ?? '';

            if (!$id || !$type) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz parametre.']);
                exit;
            }

            if (empty(trim($aciklama))) {
                echo json_encode(['status' => 'error', 'message' => 'Silme nedeni girmek zorunludur.']);
                exit;
            }

            $table = '';
            if ($type == '1') $table = 'personel_izinleri';
            else if ($type == 'Kesinti') $table = 'personel_kesintileri';
            else if ($type == 'Ek Ödeme') $table = 'personel_ek_odemeler';
            else if ($type == '3') $table = 'personel_talepleri';
            else if ($type == '4') $table = 'personel_icralari';

            if ($table) {
                $sql = "UPDATE {$table} SET silinme_tarihi = NOW(), aciklama = CONCAT(COALESCE(aciklama, ''), ' [Silinme Nedeni: ', ?, ']') WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$aciklama, $id]);
                echo json_encode(['status' => 'success', 'message' => 'Kayıt başarıyla silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt türü geçersiz.']);
            }
            exit;
        }

        if ($action === 'get-rapor' || $action === 'export-rapor') {
            $isExport = ($action === 'export-rapor');
            if (!$isExport) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $rapor_turu = intval($_POST['rapor_turu'] ?? 0);
            $start_date_raw = $_POST['start_date'] ?? date('01.m.Y');
            $end_date_raw = $_POST['end_date'] ?? date('d.m.Y');
            
            // JS'den gelen d.m.Y formatını MySQL için Y-m-d'ye çevir
            $s_time = strtotime(str_replace('.', '-', $start_date_raw));
            $e_time = strtotime(str_replace('.', '-', $end_date_raw));
            
            $start_date = $s_time ? date('Y-m-d', $s_time) : date('Y-m-01');
            $end_date = $e_time ? date('Y-m-d', $e_time) : date('Y-m-d');

            // Tarih sonuna saat ekleyelim ki o günün sonuna kadar alsın
            $end_date_full = $end_date . ' 23:59:59';
            $start_date_full = $start_date . ' 00:00:00';

            // DataTables Server-Side Parametreleri
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            if ($isExport) $length = -1; // Export tüm veriyi çekmeli
            
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            
            $orderColumnIdx = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
            $orderDir = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';

            $data = [];
            $recordsTotal = 0;
            $recordsFiltered = 0;

            switch ($rapor_turu) {
                case 1: // İzin/Rapor Listesi
                    $columnsMap = [
                        0 => 'pi.baslangic_tarihi', // Personel adı genelde yazılır ama JS tablosunda personel ilk sırada
                        1 => 'p.tc_kimlik_no',
                        2 => 'p.departman',
                        3 => 't.tur_adi',
                        4 => 'pi.baslangic_tarihi',
                        5 => 'pi.bitis_tarihi',
                        6 => 'gun_sayisi', // hesaba dayalı
                        7 => 'pi.onay_durumu',
                        8 => 'onaylayan_kisi',
                        9 => 'pi.aciklama',
                        10 => 'pi.baslangic_tarihi' // İslem
                    ];
                    
                    if ($orderColumnIdx == 0) $orderColumn = 'p.adi_soyadi';
                    else $orderColumn = $columnsMap[$orderColumnIdx] ?? 'pi.baslangic_tarihi';

                    $whereClause = "pi.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)";
                    $params = [$firmaId, $end_date, $start_date];

                    if (!empty($searchValue)) {
                        $whereClause .= " AND (p.adi_soyadi LIKE ? OR p.tc_kimlik_no LIKE ? OR p.departman LIKE ? OR t.tur_adi LIKE ? OR pi.onay_durumu LIKE ?)";
                        $params = array_merge($params, ["%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%"]);
                    }

                    // Sütun Bazlı Arama
                    if (isset($_POST['columns'])) {
                        foreach ($_POST['columns'] as $i => $col) {
                            if (!empty($col['search']['value']) && isset($columnsMap[$i]) && $columnsMap[$i] != 'gun_sayisi') {
                                $whereClause .= " AND " . $columnsMap[$i] . " LIKE ?";
                                $params[] = "%" . $col['search']['value'] . "%";
                            }
                        }
                    }

                    // Toplam Kayıt Sayısı (Filtresiz)
                    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM personel_izinleri pi JOIN personel p ON pi.personel_id = p.id WHERE pi.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)");
                    $stmtTotal->execute([$firmaId, $end_date, $start_date]);
                    $recordsTotal = $stmtTotal->fetchColumn();

                    // Toplam Kayıt Sayısı (Filtreli)
                    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM personel_izinleri pi JOIN personel p ON pi.personel_id = p.id LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id WHERE $whereClause");
                    $stmtFiltered->execute($params);
                    $recordsFiltered = $stmtFiltered->fetchColumn();

                    $sql = "SELECT pi.*, p.adi_soyadi, p.tc_kimlik_no, p.departman, t.tur_adi as izin_turu,
                                   (SELECT u.adi_soyadi FROM izin_onaylari io JOIN users u ON io.onaylayan_id = u.id WHERE io.izin_id = pi.id ORDER BY io.id DESC LIMIT 1) as onaylayan_kisi
                            FROM personel_izinleri pi
                            JOIN personel p ON pi.personel_id = p.id
                            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                            WHERE $whereClause
                            ORDER BY $orderColumn $orderDir ";
                            
                    if ($length != -1) {
                        $sql .= " LIMIT $start, $length";
                    }
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);
                    
                    foreach ($rawData as $row) {
                        $gun_sayisi = $izinModel->hesaplaIzinGunu($row->baslangic_tarihi, $row->bitis_tarihi);
                        
                        $data[] = [
                            'id' => $row->id,
                            'personel' => $row->adi_soyadi ?? '-',
                            'tc_no' => $row->tc_kimlik_no ?? '-',
                            'departman' => $row->departman ?? '-',
                            'izin_turu' => $row->izin_turu ?? '-',
                            'baslangic_tarihi' => date('d.m.Y', strtotime($row->baslangic_tarihi)),
                            'bitis_tarihi' => date('d.m.Y', strtotime($row->bitis_tarihi)),
                            'gun_sayisi' => $gun_sayisi,
                            'durum' => $row->onay_durumu ?? 'Bekliyor',
                            'onaylayan' => $row->onaylayan_kisi ?? '-',
                            'aciklama' => $row->aciklama ?? '-'
                        ];
                    }
                    break;

                case 2: // Personel Kesinti/Ek Ödemeleri Listesi
                    $columnsMap = [
                        0 => 'tarih',
                        1 => 'tc_kimlik_no',
                        2 => 'departman',
                        3 => 'islem_tipi',
                        4 => 'detay_turu',
                        5 => 'tutar',
                        6 => 'tarih',
                        7 => 'durum',
                        8 => 'aciklama',
                        9 => 'tarih' // Islem
                    ];
                    
                    if ($orderColumnIdx == 0) $orderColumn = 'adi_soyadi';
                    else $orderColumn = $columnsMap[$orderColumnIdx] ?? 'tarih';

                    $unionBase = "
                        SELECT pk.id, 
                               CONVERT(p.adi_soyadi USING utf8mb4) as adi_soyadi, 
                               CONVERT(p.tc_kimlik_no USING utf8mb4) as tc_kimlik_no, 
                               CONVERT(p.departman USING utf8mb4) as departman, 
                               CONVERT('Kesinti' USING utf8mb4) as islem_tipi, 
                               CONVERT(pk.tur USING utf8mb4) as detay_turu, 
                               pk.tutar, 
                               pk.olusturma_tarihi as tarih, 
                               CONVERT(pk.durum USING utf8mb4) as durum, 
                               CONVERT(pk.aciklama USING utf8mb4) as aciklama
                        FROM personel_kesintileri pk
                        JOIN personel p ON pk.personel_id = p.id
                        WHERE pk.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = :firmaId AND pk.olusturma_tarihi BETWEEN :startDate AND :endDate
                        UNION ALL
                        SELECT pe.id, 
                               CONVERT(p.adi_soyadi USING utf8mb4) as adi_soyadi, 
                               CONVERT(p.tc_kimlik_no USING utf8mb4) as tc_kimlik_no, 
                               CONVERT(p.departman USING utf8mb4) as departman, 
                               CONVERT('Ek Ödeme' USING utf8mb4) as islem_tipi, 
                               CONVERT(pe.tur USING utf8mb4) as detay_turu, 
                               pe.tutar, 
                               pe.created_at as tarih, 
                               CONVERT(pe.durum USING utf8mb4) as durum, 
                               CONVERT(pe.aciklama USING utf8mb4) as aciklama
                        FROM personel_ek_odemeler pe
                        JOIN personel p ON pe.personel_id = p.id
                        WHERE pe.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = :firmaId AND pe.created_at BETWEEN :startDate AND :endDate
                    ";

                    $whereClause = "1=1";
                    $paramsFiltered = [':firmaId' => $firmaId, ':startDate' => $start_date_full, ':endDate' => $end_date_full];

                    if (!empty($searchValue)) {
                        $whereClause .= " AND (adi_soyadi LIKE :search OR tc_kimlik_no LIKE :search OR departman LIKE :search OR islem_tipi LIKE :search OR detay_turu LIKE :search OR durum LIKE :search)";
                        $paramsFiltered[':search'] = "%$searchValue%";
                    }

                    // Sütun Bazlı Arama
                    if (isset($_POST['columns'])) {
                        foreach ($_POST['columns'] as $i => $col) {
                            if (!empty($col['search']['value']) && isset($columnsMap[$i])) {
                                $colKey = ":colsearch" . $i;
                                $whereClause .= " AND " . $columnsMap[$i] . " LIKE " . $colKey;
                                $paramsFiltered[$colKey] = "%" . $col['search']['value'] . "%";
                            }
                        }
                    }

                    // Toplam Kayıt Sayısı (Filtresiz)
                    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM ($unionBase) as t");
                    $stmtTotal->execute([':firmaId' => $firmaId, ':startDate' => $start_date_full, ':endDate' => $end_date_full]);
                    $recordsTotal = $stmtTotal->fetchColumn();

                    // Toplam Kayıt Sayısı (Filtreli)
                    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM ($unionBase) as t WHERE $whereClause");
                    $stmtFiltered->execute($paramsFiltered);
                    $recordsFiltered = $stmtFiltered->fetchColumn();

                    $sql = "SELECT * FROM ($unionBase) as t WHERE $whereClause ORDER BY $orderColumn $orderDir";
                    if ($length != -1) {
                        $sql .= " LIMIT :start, :length";
                    }

                    $stmt = $db->prepare($sql);
                    
                    // Tüm parametreleri bağla
                    foreach ($paramsFiltered as $key => $val) {
                        $stmt->bindValue($key, $val, PDO::PARAM_STR);
                    }
                    if ($length != -1) {
                        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
                        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
                    }
                    
                    $stmt->execute();
                    $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

                    foreach ($rawData as $row) {
                        $data[] = [
                            'id' => $row->id,
                            'personel' => $row->adi_soyadi,
                            'tc_no' => $row->tc_kimlik_no,
                            'departman' => $row->departman,
                            'islem_tipi' => $row->islem_tipi,
                            'tur' => $row->detay_turu,
                            'tutar' => number_format((float)$row->tutar, 2, ',', '.') . ' TL',
                            'tarih' => date('d.m.Y H:i', strtotime($row->tarih)),
                            'durum' => $row->durum ?? 'Bekliyor',
                            'aciklama' => $row->aciklama ?? '-'
                        ];
                    }
                    break;

                case 3: // Personel Talepleri Listesi
                    $columnsMap = [
                        0 => 'pt.olusturma_tarihi',
                        1 => 'p.tc_kimlik_no',
                        2 => 'p.departman',
                        3 => 'pt.kategori',
                        4 => 'pt.baslik',
                        5 => 'pt.olusturma_tarihi',
                        6 => 'pt.durum',
                        7 => 'pt.cozum_tarihi',
                        8 => 'pt.cozum_aciklama',
                        9 => 'pt.aciklama',
                        10 => 'pt.olusturma_tarihi' // Islem
                    ];
                    
                    if ($orderColumnIdx == 0) $orderColumn = 'p.adi_soyadi';
                    else $orderColumn = $columnsMap[$orderColumnIdx] ?? 'pt.olusturma_tarihi';

                    $whereClause = "pt.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND pt.olusturma_tarihi BETWEEN ? AND ?";
                    $params = [$firmaId, $start_date_full, $end_date_full];

                    if (!empty($searchValue)) {
                        $whereClause .= " AND (p.adi_soyadi LIKE ? OR p.tc_kimlik_no LIKE ? OR p.departman LIKE ? OR pt.kategori LIKE ? OR pt.baslik LIKE ? OR pt.durum LIKE ?)";
                        $params = array_merge($params, ["%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%"]);
                    }

                    // Sütun Bazlı Arama
                    if (isset($_POST['columns'])) {
                        foreach ($_POST['columns'] as $i => $col) {
                            if (!empty($col['search']['value']) && isset($columnsMap[$i]) && $columnsMap[$i] != 'pt.olusturma_tarihi') {
                                $whereClause .= " AND " . $columnsMap[$i] . " LIKE ?";
                                $params[] = "%" . $col['search']['value'] . "%";
                            }
                        }
                    }

                    // Toplam Kayıt (Filtresiz)
                    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM personel_talepleri pt JOIN personel p ON pt.personel_id = p.id WHERE pt.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND pt.olusturma_tarihi BETWEEN ? AND ?");
                    $stmtTotal->execute([$firmaId, $start_date_full, $end_date_full]);
                    $recordsTotal = $stmtTotal->fetchColumn();

                    // Toplam Kayıt (Filtreli)
                    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM personel_talepleri pt JOIN personel p ON pt.personel_id = p.id WHERE $whereClause");
                    $stmtFiltered->execute($params);
                    $recordsFiltered = $stmtFiltered->fetchColumn();

                    $sql = "SELECT pt.id, pt.baslik, pt.kategori, pt.olusturma_tarihi, pt.durum, pt.cozum_tarihi, pt.cozum_aciklama, pt.aciklama,
                                   p.adi_soyadi, p.tc_kimlik_no, p.departman
                            FROM personel_talepleri pt
                            JOIN personel p ON pt.personel_id = p.id
                            WHERE $whereClause
                            ORDER BY $orderColumn $orderDir";
                            
                    if ($length != -1) {
                        $sql .= " LIMIT $start, $length";
                    }

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

                    foreach ($rawData as $row) {
                        $data[] = [
                            'id' => $row->id,
                            'personel' => $row->adi_soyadi,
                            'tc_no' => $row->tc_kimlik_no,
                            'departman' => $row->departman,
                            'baslik' => $row->baslik ?? '-',
                            'kategori' => $row->kategori ?? '-',
                            'aciklama' => $row->aciklama ?? '-',
                            'tarih' => date('d.m.Y H:i', strtotime($row->olusturma_tarihi)),
                            'durum' => $row->durum ?? 'Beklemede',
                            'cozum_tarihi' => $row->cozum_tarihi ? date('d.m.Y H:i', strtotime($row->cozum_tarihi)) : '-',
                            'cozum_aciklama' => $row->cozum_aciklama ?? '-'
                        ];
                    }
                    break;

                case 4: // Personel İcra Listesi
                    $columnsMap = [
                        0 => 'pi.created_at',
                        1 => 'p.tc_kimlik_no',
                        2 => 'p.departman',
                        3 => 'pi.icra_dairesi',
                        4 => 'pi.dosya_no',
                        5 => 'pi.toplam_borc',
                        6 => 'kesilen_tutar',
                        7 => 'kalan_tutar',
                        8 => 'pi.durum',
                        9 => 'pi.created_at',
                        10 => 'pi.created_at' // Islem
                    ];
                    
                    if ($orderColumnIdx == 0) $orderColumn = 'p.adi_soyadi';
                    else $orderColumn = $columnsMap[$orderColumnIdx] ?? 'pi.created_at';

                    $whereClause = "pi.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND pi.created_at BETWEEN ? AND ?";
                    $params = [$firmaId, $start_date_full, $end_date_full];

                    if (!empty($searchValue)) {
                        $whereClause .= " AND (p.adi_soyadi LIKE ? OR p.tc_kimlik_no LIKE ? OR p.departman LIKE ? OR pi.icra_dairesi LIKE ? OR pi.dosya_no LIKE ? OR pi.durum LIKE ?)";
                        $params = array_merge($params, ["%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%", "%$searchValue%"]);
                    }

                    // Sütun Bazlı Arama
                    if (isset($_POST['columns'])) {
                        foreach ($_POST['columns'] as $i => $col) {
                            if (!empty($col['search']['value']) && isset($columnsMap[$i]) && !in_array($columnsMap[$i], ['kesilen_tutar', 'kalan_tutar', 'pi.created_at'])) {
                                $whereClause .= " AND " . $columnsMap[$i] . " LIKE ?";
                                $params[] = "%" . $col['search']['value'] . "%";
                            }
                        }
                    }

                    // Toplam Kayıt (Filtresiz)
                    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM personel_icralari pi JOIN personel p ON pi.personel_id = p.id WHERE pi.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL AND p.firma_id = ? AND pi.created_at BETWEEN ? AND ?");
                    $stmtTotal->execute([$firmaId, $start_date_full, $end_date_full]);
                    $recordsTotal = $stmtTotal->fetchColumn();

                    // Toplam Kayıt (Filtreli)
                    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM personel_icralari pi JOIN personel p ON pi.personel_id = p.id WHERE $whereClause");
                    $stmtFiltered->execute($params);
                    $recordsFiltered = $stmtFiltered->fetchColumn();

                    $sql = "SELECT pi.*, p.adi_soyadi, p.tc_kimlik_no, p.departman,
                                  (SELECT COALESCE(SUM(tutar), 0) FROM personel_kesintileri pk WHERE pk.icra_id = pi.id AND pk.silinme_tarihi IS NULL AND pk.durum = 'onaylandi') as kesilen_tutar
                            FROM personel_icralari pi
                            JOIN personel p ON pi.personel_id = p.id
                            WHERE $whereClause
                            ORDER BY $orderColumn $orderDir";
                            
                    if ($length != -1) {
                        $sql .= " LIMIT $start, $length";
                    }

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $rawData = $stmt->fetchAll(PDO::FETCH_OBJ);

                    foreach ($rawData as $row) {
                        $kesilen_tutar = $row->kesilen_tutar;
                        $kalan_tutar = max(0, floatval($row->toplam_borc) - floatval($kesilen_tutar));
                        
                        $data[] = [
                            'id' => $row->id,
                            'personel' => $row->adi_soyadi,
                            'tc_no' => $row->tc_kimlik_no,
                            'departman' => $row->departman,
                            'icra_dairesi' => $row->icra_dairesi ?? '-',
                            'dosya_no' => $row->dosya_no ?? '-',
                            'toplam_borc' => number_format((float)$row->toplam_borc, 2, ',', '.') . ' TL',
                            'kesilen_tutar' => number_format((float)$kesilen_tutar, 2, ',', '.') . ' TL',
                            'kalan_tutar' => number_format((float)$kalan_tutar, 2, ',', '.') . ' TL',
                            'durum' => $row->durum ?? '-',
                            'tarih' => date('d.m.Y', strtotime($row->created_at))
                        ];
                    }
                    break;

                default:
                    throw new Exception('Geçersiz rapor türü.');
            }

            if ($isExport) {
                $headers = [];
                $excelData = [];
                
                if ($rapor_turu == 1) {
                    $headers = ['Personel', 'TC No', 'Departman', 'İzin Türü', 'Başlangıç', 'Bitiş', 'Gün', 'Durum', 'Onaylayan', 'Açıklama'];
                    foreach ($data as $r) {
                        $excelData[] = [$r['personel'], $r['tc_no'], $r['departman'], $r['izin_turu'], $r['baslangic_tarihi'], $r['bitis_tarihi'], $r['gun_sayisi'], $r['durum'], $r['onaylayan'], $r['aciklama']];
                    }
                } else if ($rapor_turu == 2) {
                    $headers = ['Personel', 'TC No', 'Departman', 'İşlem Tipi', 'Tür', 'Tutar', 'Tarih', 'Durum', 'Açıklama'];
                    foreach ($data as $r) {
                        $excelData[] = [$r['personel'], $r['tc_no'], $r['departman'], $r['islem_tipi'], $r['tur'], $r['tutar'], $r['tarih'], $r['durum'], $r['aciklama']];
                    }
                } else if ($rapor_turu == 3) {
                    $headers = ['Personel', 'TC No', 'Departman', 'Kategori', 'Başlık', 'Tarih', 'Durum', 'Çözüm Tarihi', 'Çözüm Açıklama', 'Açıklama'];
                    foreach ($data as $r) {
                        $excelData[] = [$r['personel'], $r['tc_no'], $r['departman'], $r['kategori'], $r['baslik'], $r['tarih'], $r['durum'], $r['cozum_tarihi'], $r['cozum_aciklama'], $r['aciklama']];
                    }
                } else if ($rapor_turu == 4) {
                    $headers = ['Personel', 'TC No', 'Departman', 'İcra Dairesi', 'Dosya No', 'Toplam Borç', 'Kesilen', 'Kalan', 'Durum', 'Tarih'];
                    foreach ($data as $r) {
                        $excelData[] = [$r['personel'], $r['tc_no'], $r['departman'], $r['icra_dairesi'], $r['dosya_no'], $r['toplam_borc'], $r['kesilen_tutar'], $r['kalan_tutar'], $r['durum'], $r['tarih']];
                    }
                }
                
                renderExcel($excelData, $headers, "Rapor_" . date('d_m_Y') . ".xls");
            } else {
                echo json_encode([
                    'status' => 'success',
                    'draw' => $draw,
                    'recordsTotal' => intval($recordsTotal),
                    'recordsFiltered' => intval($recordsFiltered),
                    'data' => $data
                ]);
            }
        } else {
            throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        if (!($isExport ?? false)) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        } else {
            echo "Hata: " . $e->getMessage();
        }
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}

/**
 * HTML tablosu formatında Excel çıktısı üretir (Excel bu formatı açabilir).
 */
function renderExcel($data, $headers, $filename = "rapor.xls") {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo '<table border="1">';
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color: #f2f2f2;">' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}
