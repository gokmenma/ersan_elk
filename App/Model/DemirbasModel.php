<?php
namespace App\Model;

use App\Helper\Helper;
use App\Model\Model;
use App\Helper\Security;
use PDO;

class DemirbasModel extends Model
{
    protected $table = 'demirbas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm demirbaşları kategori bilgisiyle beraber getirir
     */
    public function getAllWithCategory()
    {
        $sql = $this->db->prepare("
            SELECT 
                d.*,
                k.tur_adi as kategori_adi,
                COALESCE(d.miktar, 1) as miktar,
                COALESCE(d.kalan_miktar, 1) as kalan_miktar,
                (SELECT id FROM demirbas_servis_kayitlari WHERE demirbas_id = d.id AND iade_tarihi IS NULL AND silinme_tarihi IS NULL LIMIT 1) as active_servis_id
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.firma_id = ?
            ORDER BY d.kayit_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Stokta kalan demirbaşları getirir
     */
    public function getInStock()
    {
        $sql = $this->db->prepare("
            SELECT 
                d.*,
                k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kalan_miktar > 0 AND d.firma_id = ?
            ORDER BY k.tur_adi, d.demirbas_adi
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kategoriye göre demirbaşları getirir
     */
    public function getByCategory($kategori_id)
    {
        $sql = $this->db->prepare("
            SELECT d.*, k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kategori_id = ? AND d.firma_id = ?
            ORDER BY d.demirbas_adi
        ");
        $sql->execute([$kategori_id, $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Demirbaş stok özeti
     */
    public function getStockSummary()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_cesit,
                COALESCE(SUM(miktar), 0) as toplam_adet,
                COALESCE(SUM(kalan_miktar), 0) as stokta_kalan,
                (COALESCE(SUM(miktar), 0) - COALESCE(SUM(kalan_miktar), 0)) as zimmetli_adet
            FROM {$this->table}
            WHERE firma_id = ?
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Select2 için demirbaş listesi
     */
    public function getForSelect($search = '', $type = 'demirbas')
    {
        $searchTerm = '%' . $search . '%';
        // search terms count: 4 (demirbas_no, demirbas_adi, marka, seri_no)
        $params = [$_SESSION['firma_id'], $searchTerm, $searchTerm, $searchTerm, $searchTerm];

        $whereType = "";
        
        // Kategori filtresi
        $sayacCondition = "(LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%')";
        $aparatCondition = "(LOWER(k.tur_adi) LIKE '%aparat%' OR k.id = 645)";

        if ($type === 'sayac') {
            $whereType = " AND " . $sayacCondition;
        } elseif ($type === 'aparat') {
            $whereType = " AND " . $aparatCondition;
        } else {
            // Demirbaş: Sayaç ve Aparat hariç
            $whereType = " AND NOT " . $sayacCondition . " AND NOT " . $aparatCondition;
        }

        // Sayaç ise seri numarasını da ekle
        $textSelect = "CONCAT(d.demirbas_no, ' - ', d.demirbas_adi, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ')')";
        if ($type === 'sayac') {
            $textSelect = "CONCAT(d.demirbas_no, ' - ', d.demirbas_adi, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ') - SN: ', COALESCE(d.seri_no, '-'))";
        } else {
             // Diğer demirbaşlarda da varsa seri numarasını göster (Tablet, Simkart vb.)
             $textSelect = "CONCAT(d.demirbas_no, ' - ', d.demirbas_adi, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ')', CASE WHEN d.seri_no IS NOT NULL AND d.seri_no != '' THEN CONCAT(' - SN: ', d.seri_no) ELSE '' END)";
        }

        $sql = $this->db->prepare("
            SELECT 
                d.id,
                $textSelect as text,
                d.kalan_miktar,
                d.seri_no
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kalan_miktar > 0 AND d.firma_id = ?
                AND (d.demirbas_no LIKE ? OR d.demirbas_adi LIKE ? OR d.marka LIKE ? OR d.seri_no LIKE ?)
                $whereType
            ORDER BY d.demirbas_adi
            LIMIT 50
        ");
        
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
     */
    public function getTableRow($id)
    {
        $sql = $this->db->prepare("
            SELECT d.*, k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.id = ?
        ");
        $sql->execute([$id]);
        $data = $sql->fetch(PDO::FETCH_OBJ);

        if (!$data) {
            return '';
        }

        $enc_id = Security::encrypt($data->id);
        $miktar = $data->miktar ?? 1;
        $kalan = $data->kalan_miktar ?? 1;
        $minStok = $data->minimun_stok_uyari_miktari ?? 0;

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
        $durumText = $data->durum ?? 'aktif';
        $durumMap = [
            'aktif' => '<span class="badge bg-soft-success text-success">Aktif</span>',
            'pasif' => '<span class="badge bg-soft-secondary text-secondary">Pasif</span>',
            'arizali' => '<span class="badge bg-soft-warning text-warning">Arızalı</span>',
            'hurda' => '<span class="badge bg-soft-danger text-danger">Hurda</span>',
        ];
        $durumBadge = $durumMap[strtolower($durumText)] ?? '<span class="badge bg-soft-secondary text-secondary">' . $durumText . '</span>';

        return '<tr data-id="' . $enc_id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->demirbas_no . '</td>
            <td><span class="badge bg-soft-primary text-primary">' . ($data->kategori_adi ?? 'Kategorisiz') . '</span></td>
            <td>
                <a href="#" data-id="' . $enc_id . '" class="text-dark duzenle fw-medium">
                    ' . $data->demirbas_adi . '</a>
            </td>
            <td>
                <div>' . ($data->marka ?? '-') . ' ' . ($data->model ?? '') . '</div>
                <small class="text-muted">' . ($data->seri_no ? 'SN: ' . $data->seri_no : '') . '</small>
            </td>
            <td class="text-center">' . $stokBadge . '</td>
            <td class="text-center">' . $durumBadge . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->edinme_tutari ?? 0) . '</td>
            <td>' . ($data->edinme_tarihi ?? '-') . '</td>
            <td class="text-center text-nowrap">
                ' . ($kalan > 0 ? '<button type="button" class="btn btn-sm btn-soft-warning waves-effect waves-light zimmet-ver" data-id="' . $enc_id . '" data-raw-id="' . $data->id . '" data-name="' . $data->demirbas_adi . '" data-kalan="' . $kalan . '" title="Zimmet Ver"><i class="bx bx-transfer"></i></button>' : '') . '
                <button type="button" class="btn btn-sm btn-soft-primary waves-effect waves-light duzenle" data-id="' . $enc_id . '" title="Düzenle"><i class="bx bx-edit"></i></button>
                <button type="button" class="btn btn-sm btn-soft-danger waves-effect waves-light demirbas-sil" data-id="' . $enc_id . '" data-name="' . $data->demirbas_adi . '" title="Sil"><i class="bx bx-trash"></i></button>
            </td>
        </tr>';
    }

    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT d.*, k.tur_adi as kategori_adi
                FROM {$this->table} d
                LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                WHERE d.firma_id = :firma_id";

        $params = ['firma_id' => $_SESSION['firma_id']];

        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (d.demirbas_no LIKE :term OR d.demirbas_adi LIKE :term OR d.marka LIKE :term OR d.model LIKE :term OR k.tur_adi LIKE :term)";
            $params['term'] = $term;
        }

        if (!empty($colSearches)) {
            $colMap = [1 => 'd.demirbas_no', 2 => 'k.tur_adi', 3 => 'd.demirbas_adi', 4 => 'd.marka', 6 => 'd.durum', 7 => 'd.edinme_tutari', 8 => 'd.edinme_tarihi'];
            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;
                    if ($idx == 8) {
                        $sql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                    } else {
                        $sql .= " AND $field LIKE :$paramName";
                    }
                    $params[$paramName] = "%$val%";
                }
            }
        }

        $sql .= " ORDER BY d.kayit_tarihi DESC";
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * DataTables server-side listesi için verileri getirir
     */
    public function getDatatableList($request, $tab = 'demirbas')
    {
        $start = $request['start'] ?? 0;
        $length = $request['length'] ?? 10;
        $search = $request['search']['value'] ?? null;
        $orderCol = $request['order'][0]['column'] ?? null;
        $orderDir = $request['order'][0]['dir'] ?? 'DESC';

        $selectCols = "SELECT 
                        d.*,
                        k.tur_adi as kategori_adi,
                        COALESCE(d.miktar, 1) as miktar_val,
                        COALESCE(d.kalan_miktar, 1) as kalan_miktar_val,
                        (SELECT id FROM demirbas_servis_kayitlari WHERE demirbas_id = d.id AND iade_tarihi IS NULL AND silinme_tarihi IS NULL LIMIT 1) as active_servis_id";

        $fromSql = " FROM {$this->table} d
                    LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'";

        $whereSql = " WHERE d.firma_id = :firma_id";

        $params = ['firma_id' => $_SESSION['firma_id']];

        // Tab filtremesi
        $sayacCondition = "(LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%')";
        $aparatCondition = "(LOWER(k.tur_adi) LIKE '%aparat%' OR k.id = 645)";

        if ($tab === 'sayac') {
            $whereSql .= " AND " . $sayacCondition;
        } elseif ($tab === 'aparat') {
            $whereSql .= " AND " . $aparatCondition;
        } else {
            $whereSql .= " AND NOT " . $sayacCondition . " AND NOT " . $aparatCondition;
        }

        $searchWhere = "";

        // Global Arama
        if (!empty($search)) {
            $searchWhere .= " AND (d.demirbas_no LIKE :search 
                            OR d.demirbas_adi LIKE :search 
                            OR k.tur_adi LIKE :search
                            OR d.marka LIKE :search
                            OR d.model LIKE :search
                            OR d.seri_no LIKE :search)";
            $params['search'] = "%$search%";
        }

        // Sütun Bazlı Arama
        if ($tab === 'demirbas') {
            // indices: 0=>sira, 1=>no, 2=>kat, 3=>adi, 4=>marka, 5=>stok, 6=>durum, 7=>tutar, 8=>tarih
            $colSearchMap = [
                1 => 'd.demirbas_no',
                2 => 'k.tur_adi',
                3 => 'd.demirbas_adi',
                4 => 'CONCAT_WS(" ", d.marka, d.model, d.seri_no)',
                6 => 'd.durum',
                7 => 'd.edinme_tutari',
                8 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        } elseif ($tab === 'sayac') {
            // indices: 0=>checkbox, 1=>sira, 2=>no, 3=>adi, 4=>marka, 5=>seri, 6=>stok, 7=>durum, 8=>tarih
            $colSearchMap = [
                2 => 'd.demirbas_no',
                3 => 'd.demirbas_adi',
                4 => 'CONCAT_WS(" ", d.marka, d.model)',
                5 => 'd.seri_no',
                7 => 'd.durum',
                8 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        } else {
            // aparat (no checkbox): 0=>sira, 1=>no, 2=>adi, 3=>marka, 4=>seri, 5=>stok, 6=>durum, 7=>tarih
            $colSearchMap = [
                1 => 'd.demirbas_no',
                2 => 'd.demirbas_adi',
                3 => 'CONCAT_WS(" ", d.marka, d.model)',
                4 => 'd.seri_no',
                6 => 'd.durum',
                7 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        }

        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $colIdx => $col) {
                if (!empty($col['search']['value']) && isset($colSearchMap[$colIdx])) {
                    $searchVal = "%" . $col['search']['value'] . "%";
                    $paramKey = "col_search_" . $colIdx;
                    $searchWhere .= " AND {$colSearchMap[$colIdx]} LIKE :$paramKey";
                    $params[$paramKey] = $searchVal;
                }
            }
        }

        // Envanter Raporu Filtreleri (Sadece Demirbaş tabında kullanılır)
        $inventoryKatAdi = $request['inventory_kat_adi'] ?? null;
        $inventoryType = $request['inventory_type'] ?? null;

        if (!empty($inventoryKatAdi) && !empty($inventoryType)) {
            $searchWhere .= " AND k.tur_adi = :inv_kat";
            $params['inv_kat'] = $inventoryKatAdi;

            if ($inventoryType === 'bosta') {
                $searchWhere .= " AND d.kalan_miktar > 0";
            } elseif ($inventoryType === 'zimmetli') {
                $searchWhere .= " AND d.kalan_miktar < COALESCE(d.miktar, 1) AND d.durum NOT IN ('arizali', 'hurda')";
            } elseif ($inventoryType === 'arizali') {
                $searchWhere .= " AND LOWER(d.durum) = 'arizali'";
            } elseif ($inventoryType === 'hurda') {
                $searchWhere .= " AND LOWER(d.durum) LIKE '%hurda%'";
            }
        }

        // Toplam kayıt sayısı (sadece tab filtresi ile)
        $totalSql = "SELECT COUNT(d.id)" . $fromSql . $whereSql;
        $stmtTotal = $this->db->prepare($totalSql);
        $stmtTotal->execute(['firma_id' => $_SESSION['firma_id']]);
        $totalRecords = $stmtTotal->fetchColumn();

        // Filtrelenmiş kayıt sayısı
        $filterSql = "SELECT COUNT(d.id)" . $fromSql . $whereSql . $searchWhere;
        $stmtFilter = $this->db->prepare($filterSql);
        foreach ($params as $key => $val) {
            $stmtFilter->bindValue($key, $val);
        }
        $stmtFilter->execute();
        $recordsFiltered = $stmtFilter->fetchColumn();



        // Sıralama
        if ($tab === 'demirbas') {
            $colMapOrder = [
                0 => 'd.id',
                1 => 'd.demirbas_no',
                2 => 'k.tur_adi',
                3 => 'd.demirbas_adi',
                4 => 'd.marka',
                5 => 'd.kalan_miktar',
                6 => 'd.durum',
                7 => 'd.edinme_tutari',
                8 => 'd.edinme_tarihi'
            ];
        } elseif ($tab === 'sayac') {
            $colMapOrder = [
                1 => 'd.id',
                2 => 'd.demirbas_no',
                3 => 'd.demirbas_adi',
                4 => 'd.marka',
                5 => 'd.seri_no',
                6 => 'd.kalan_miktar',
                7 => 'd.durum',
                8 => 'd.edinme_tarihi'
            ];
        } else {
            // aparat
            $colMapOrder = [
                0 => 'd.id',
                1 => 'd.demirbas_no',
                2 => 'd.demirbas_adi',
                3 => 'd.marka',
                4 => 'd.seri_no',
                5 => 'd.kalan_miktar',
                6 => 'd.durum',
                7 => 'd.edinme_tarihi'
            ];
        }

        $orderSql = "";
        if ($orderCol !== null && isset($colMapOrder[$orderCol])) {
            $orderSql = " ORDER BY " . $colMapOrder[$orderCol] . " " . ($orderDir === 'asc' ? 'ASC' : 'DESC');
        } else {
            $orderSql = " ORDER BY d.kayit_tarihi DESC";
        }

        // Limit
        $limitSql = " LIMIT :start, :length";

        $finalSql = $selectCols . $fromSql . $whereSql . $searchWhere . $orderSql . $limitSql;
        $stmt = $this->db->prepare($finalSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue('length', (int) $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ];
    }
}