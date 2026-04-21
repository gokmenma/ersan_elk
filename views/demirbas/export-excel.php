<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['firma_id'])) {
    die("Hata: Oturum kapalı veya firma seçilmemiş. Lütfen tekrar giriş yapınız.");
}

$firmaId = (int) $_SESSION['firma_id'];

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

try {
    $autoloaderPath = dirname(__DIR__, 2) . '/Autoloader.php';
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Autoloader bulunamadı.");
    }
    require_once $autoloaderPath;

    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi bulunamadı.");
    }

    $tab = $_GET['tab'] ?? 'demirbas';
    $term = $_GET['search'] ?? null;
    $colSearches = [];
    if (isset($_GET['col_search'])) {
        $colSearches = json_decode($_GET['col_search'], true);
    }
    $statusFilter = $_GET['status_filter'] ?? '';
    $viewMode = $_GET['view_mode'] ?? 'list';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $data = [];
    $columns = [];
    $filename = 'export_' . date('Y-m-d') . '.xlsx';

    $db = (new \App\Model\Model())->db;

    if ($tab === 'demirbas') {
        $model = new \App\Model\DemirbasModel();
        $data = $model->filter($term, $colSearches);
        $columns = [
            'Sıra' => 'id',
            'D.No' => 'demirbas_no',
            'Kategori' => 'kategori_adi',
            'Demirbaş Adı' => 'demirbas_adi',
            'Marka' => 'marka',
            'Model' => 'model',
            'Stok' => 'kalan_miktar',
            'Toplam Miktar' => 'miktar',
            'Edinme Tutarı' => 'edinme_tutari',
            'Edinme Tarihi' => 'edinme_tarihi'
        ];
        $filename = 'demirbas_listesi_' . date('Y-m-d') . '.xlsx';
    } elseif ($tab === 'zimmet') {
        $model = new \App\Model\DemirbasZimmetModel();
        $data = $model->filter($term, $colSearches);
        $columns = [
            'ID' => 'id',
            'Kategori' => 'kategori_adi',
            'Demirbaş' => 'demirbas_adi',
            'Marka' => 'marka',
            'Model' => 'model',
            'Personel' => 'personel_adi',
            'Miktar' => 'teslim_miktar',
            'Teslim Tarihi' => 'teslim_tarihi',
            'Durum' => 'durum'
        ];
        $filename = 'zimmet_kayitlari_' . date('Y-m-d') . '.xlsx';
    } elseif ($tab === 'sayac_kaski') {
        $Demirbas = new \App\Model\DemirbasModel();
        $catIds = getCategoryIdsByKeywords($Demirbas->db, $firmaId, ['sayaç', 'sayac']);
        if (empty($catIds)) {
            $data = [];
        } else {
            $in = buildInClause($catIds);
            $unionSql = "
                SELECT DATE(COALESCE(edinme_tarihi, kayit_tarihi)) as tarih, 'Yeni Sayaç Girişi' as islem_tipi, COUNT(id) as adet, 'gelen' as yon
                FROM demirbas
                WHERE kategori_id IN ($in) AND firma_id = ? AND silinme_tarihi IS NULL
                    AND LOWER(COALESCE(durum,'')) NOT LIKE '%hurda%'
                    AND LOWER(COALESCE(demirbas_adi,'')) NOT LIKE '%hurda%'
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
            if (!empty($term)) {
                $wrapWhere = " WHERE DATE_FORMAT(sub.tarih, '%d.%m.%Y') LIKE ? OR sub.islem_tipi LIKE ?";
                $wrapParams[] = '%' . $term . '%';
                $wrapParams[] = '%' . $term . '%';
            }
            $dataSql = $Demirbas->db->prepare("SELECT * FROM ($unionSql) sub $wrapWhere ORDER BY sub.tarih DESC");
            $dataSql->execute($wrapParams);
            $data = $dataSql->fetchAll(\PDO::FETCH_OBJ);
        }
        $columns = [
            'Tarih' => 'tarih',
            'İşlem' => 'islem_tipi',
            'Yön' => 'yon',
            'Adet' => 'adet'
        ];
        $filename = 'sayac_kaski_listesi_' . date('Y-m-d') . '.xlsx';
    } elseif ($tab === 'sayac_bizim_depo') {
        $model = new \App\Model\DemirbasModel();
        $request = $_GET;
        $request['length'] = 100000;
        $request['start'] = 0;
        $request['lokasyon'] = 'bizim_depo';
        $request['status_filter'] = $statusFilter;
        
        // DataTables search formatına dönüştür
        if (isset($request['search']) && !is_array($request['search'])) {
            $request['search'] = ['value' => $request['search']];
        }

        if (isset($request['col_search'])) {
            $colsArr = json_decode($request['col_search'], true);
            $request['columns'] = [];
            foreach ($colsArr as $cs) {
                $request['columns'][$cs['field']] = ['search' => ['value' => $cs['value']]];
            }
        }

        $res = $model->getDatatableList($request, 'sayac');
        $data = $res['data'] ?? [];
        $columns = [
            'Sayaç Adı' => 'demirbas_adi',
            'Marka/Model' => 'marka_model',
            'Seri/Abone No' => 'seri_no',
            'Stok' => 'stok',
            'Durum' => 'durum',
            'Açıklama' => 'aciklama',
            'Tarih' => 'tarih'
        ];
        foreach ($data as &$row) {
            foreach ($row as $key => $val) { if (is_string($val)) $row->$key = strip_tags($val); }
            if (!isset($row->marka_model)) $row->marka_model = ($row->marka ?? '') . ' ' . ($row->model ?? '');
            if (!isset($row->stok)) $row->stok = $row->kalan_miktar ?? 1;
            if (!isset($row->tarih)) $row->tarih = $row->edinme_tarihi ?? $row->kayit_tarihi ?? '';
        }
        $filename = 'sayac_depo_stok_' . date('Y-m-d') . '.xlsx';
    } elseif ($tab === 'sayac_personel') {
        $model = new \App\Model\DemirbasModel();
        $where = " WHERE h.silinme_tarihi IS NULL AND h.personel_id IS NOT NULL AND d.firma_id = ? 
                     AND (LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%')
                     AND NOT (h.hareket_tipi = 'sarf' AND (LOWER(COALESCE(h.aciklama, '')) LIKE '%kask%' OR LOWER(COALESCE(d.durum, '')) = 'kaskiye teslim edildi')) ";
        $params = [$firmaId];
        if (!empty($term)) {
            $where .= " AND p.adi_soyadi LIKE ? ";
            $params[] = '%' . $term . '%';
        }
        $sql = $model->db->prepare("SELECT
                COALESCE(p.adi_soyadi, CONCAT('Personel #', h.personel_id)) as personel_adi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' THEN h.miktar ELSE 0 END), 0) as aldigi_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(h.aciklama, '')) NOT LIKE '%kask%' THEN h.miktar ELSE 0 END), 0) as taktigi,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(h.aciklama, '')) NOT LIKE '%kask%' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%' THEN h.miktar ELSE 0 END), 0) as elinde_yeni,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(h.aciklama, '')) NOT LIKE '%kask%' THEN h.miktar ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND (LOWER(COALESCE(d.durum, '')) LIKE '%hurda%' OR LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%') THEN h.miktar ELSE 0 END), 0) as aldigi_hurda,
                COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0) as teslim_hurda,
                (COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' AND LOWER(COALESCE(d.durum, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(d.demirbas_adi, '')) NOT LIKE '%hurda%' AND LOWER(COALESCE(h.aciklama, '')) NOT LIKE '%kask%' THEN h.miktar ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' AND (LOWER(COALESCE(d.durum, '')) LIKE '%hurda%' OR LOWER(COALESCE(d.demirbas_adi, '')) LIKE '%hurda%') THEN h.miktar ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND (h.aciklama IS NULL OR h.aciklama NOT LIKE '[DEPO_IADE]%') THEN h.miktar ELSE 0 END), 0)) as elinde_hurda
            FROM demirbas_hareketler h
            INNER JOIN demirbas d ON d.id = h.demirbas_id
            INNER JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON p.id = h.personel_id
            $where
            GROUP BY h.personel_id, p.adi_soyadi
            ORDER BY p.adi_soyadi ASC");
        $sql->execute($params);
        $data = $sql->fetchAll(\PDO::FETCH_OBJ);
        $columns = [
            'Personel' => 'personel_adi',
            'Aldığı Yeni' => 'aldigi_yeni',
            'Taktığı' => 'taktigi',
            'Elinde Yeni' => 'elinde_yeni',
            'Aldığı Hurda' => 'aldigi_hurda',
            'Teslim Hurda' => 'teslim_hurda',
            'Elinde Hurda' => 'elinde_hurda'
        ];
        $filename = 'sayac_personel_ozet_' . date('Y-m-d') . '.xlsx';
    } elseif ($tab === 'sayac_hareket') {
        $model = new \App\Model\DemirbasModel();
        if ($viewMode === 'grouped') {
            $columns = ['Personel / Sorumlu' => 'personel_adi', 'Tarih' => 'gun', 'İşlem Sayısı' => 'adet', 'Toplam Miktar' => 'toplam_miktar'];
            $params = [$firmaId]; $whereSql = " WHERE h.silinme_tarihi IS NULL AND d.firma_id = ?";
            $sayacCatIds = getCategoryIdsByKeywords($model->db, $firmaId, ['sayaç', 'sayac']);
            if (!empty($sayacCatIds)) {
                $in = buildInClause($sayacCatIds); $whereSql .= " AND d.kategori_id IN ($in)";
                foreach ($sayacCatIds as $catId) $params[] = $catId;
            }
            if ($statusFilter === 'kaski') {
                $whereSql .= " AND h.hareket_tipi <> 'iade' AND (LOWER(COALESCE(h.aciklama, '')) LIKE '%kask%' OR (h.hareket_tipi = 'sarf' AND (d.lokasyon = 'kaski' OR LOWER(COALESCE(d.durum, '')) = 'kaskiye teslim edildi')))";
            } elseif ($statusFilter === 'depo') {
                $whereSql .= " AND (d.lokasyon = 'bizim_depo' OR h.hareket_tipi = 'iade')";
            } elseif ($statusFilter === 'zimmet') {
                $whereSql .= " AND h.hareket_tipi = 'zimmet'";
            }
            if (!empty($term)) {
                $whereSql .= " AND (p.adi_soyadi LIKE ? OR DATE_FORMAT(h.tarih, '%d.%m.%Y') LIKE ?)";
                $params[] = "%$term%"; $params[] = "%$term%";
            }
            $sql = $model->db->prepare("SELECT p.adi_soyadi as personel_adi, DATE(h.tarih) as gun, COUNT(*) as adet, SUM(h.miktar) as toplam_miktar FROM demirbas_hareketler h INNER JOIN demirbas d ON h.demirbas_id = d.id LEFT JOIN personel p ON h.personel_id = p.id $whereSql GROUP BY h.personel_id, gun ORDER BY gun DESC, personel_adi ASC");
            $sql->execute($params); $data = $sql->fetchAll(\PDO::FETCH_OBJ);
            foreach ($data as &$r) { if (!$r->personel_adi) $r->personel_adi = 'Genel Depo'; }
        } else {
            $columns = ['ID' => 'id', 'Tarih' => 'tarih', 'İşlem Tipi' => 'hareket_tipi', 'Sayaç' => 'demirbas_adi', 'Seri/Abone' => 'seri_no', 'Miktar' => 'miktar', 'Sorumlu/Yer' => 'lokasyon_personel', 'Açıklama' => 'aciklama'];
            $params = [$firmaId]; $whereSql = " WHERE d.firma_id = ? AND h.silinme_tarihi IS NULL";
            $sayacCatIds = getCategoryIdsByKeywords($model->db, $firmaId, ['sayaç', 'sayac']);
            if (!empty($sayacCatIds)) {
                $in = buildInClause($sayacCatIds); $whereSql .= " AND d.kategori_id IN ($in)";
                foreach ($sayacCatIds as $catId) $params[] = $catId;
            }
            if ($statusFilter === 'kaski') {
                $whereSql .= " AND h.hareket_tipi <> 'iade' AND (LOWER(COALESCE(h.aciklama, '')) LIKE '%kask%' OR (h.hareket_tipi = 'sarf' AND (d.lokasyon = 'kaski' OR LOWER(COALESCE(d.durum, '')) = 'kaskiye teslim edildi')))";
            } elseif ($statusFilter === 'depo') {
                $whereSql .= " AND (d.lokasyon = 'bizim_depo' OR h.hareket_tipi = 'iade')";
            } elseif ($statusFilter === 'zimmet') {
                $whereSql .= " AND h.hareket_tipi = 'zimmet'";
            }
            if (!empty($term)) {
                $whereSql .= " AND (d.demirbas_adi LIKE ? OR d.seri_no LIKE ? OR p.adi_soyadi LIKE ?)";
                $params[] = "%$term%"; $params[] = "%$term%"; $params[] = "%$term%";
            }
            $sql = $model->db->prepare("SELECT h.*, d.demirbas_adi, d.seri_no, p.adi_soyadi as personel_adi, d.lokasyon, CASE WHEN h.personel_id IS NOT NULL THEN p.adi_soyadi ELSE d.lokasyon END as lokasyon_personel FROM demirbas_hareketler h INNER JOIN demirbas d ON h.demirbas_id = d.id LEFT JOIN personel p ON h.personel_id = p.id $whereSql ORDER BY h.tarih DESC, h.id DESC");
            $sql->execute($params); $data = $sql->fetchAll(\PDO::FETCH_OBJ);
            foreach ($data as &$row) {
                if ($row->lokasyon_personel === 'bizim_depo') $row->lokasyon_personel = 'Bizim Depo';
                if ($row->lokasyon_personel === 'kaski') $row->lokasyon_personel = 'Kaski Depo';
                if (preg_match('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', $row->demirbas_adi, $matches)) {
                    if (empty($row->seri_no) || $row->seri_no == '-') $row->seri_no = $matches[1];
                    $row->demirbas_adi = preg_replace('/\s*\/\s*Abone(?:\s*No)?[:\s]+(\d+)/i', '', $row->demirbas_adi);
                }
            }
        }
        $filename = 'sayac_hareketleri_' . date('Y-m-d') . '.xlsx';
    }

    // Başlıkları yaz
    $colIndex = 1;
    foreach ($columns as $header => $field) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);
        $sheet->getStyle($columnLetter . '1')->getFont()->setBold(true);
        $sheet->getStyle($columnLetter . '1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');
        $colIndex++;
    }

    // Verileri yaz
    $rowIndex = 2;
    foreach ($data as $row) {
        $colIndex = 1;
        foreach ($columns as $header => $field) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $row->$field ?? '';

            if (strpos($field, 'tarih') !== false || $field === 'gun') {
                if (!empty($val) && $val !== '0000-00-00' && $val !== '0000-00-00 00:00:00') {
                    $val = date('d.m.Y H:i', strtotime($val));
                    if (strpos($val, ' 00:00') !== false) $val = date('d.m.Y', strtotime($val));
                }
            } elseif ($field === 'hareket_tipi') {
                $tipler = ['zimmet' => 'Zimmet', 'iade' => 'İade', 'sarf' => 'Sarf/Takılan', 'giris' => 'Giriş', 'kayip' => 'Kayıp'];
                $val = $tipler[$val] ?? $val;
            } elseif ($field === 'yon') {
                $val = $val === 'gelen' ? 'Giriş' : 'Çıkış';
            }

            $sheet->setCellValueExplicit($columnLetter . $rowIndex, (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colIndex++;
        }
        $rowIndex++;
    }

    // Sütun genişlikleri
    for ($i = 1; $i < $colIndex; $i++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo "Hata: " . $e->getMessage();
}
