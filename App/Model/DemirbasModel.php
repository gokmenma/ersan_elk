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
                (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) as kalan_miktar,
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
                k.tur_adi as kategori_adi,
                (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) as kalan_miktar
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) > 0 AND d.firma_id = ?
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
                SUM((COALESCE(miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = demirbas.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0))) as stokta_kalan,
                (COALESCE(SUM(miktar), 0) - SUM((COALESCE(miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = demirbas.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)))) as zimmetli_adet
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

        if ($type === 'all') {
            $whereType = "";
        } elseif ($type === 'sayac') {
            $whereType = " AND " . $sayacCondition;
        } elseif ($type === 'aparat') {
            $whereType = " AND " . $aparatCondition;
        } else {
            // Demirbaş: Sayaç ve Aparat hariç
            $whereType = " AND NOT " . $sayacCondition . " AND NOT " . $aparatCondition;
        }

        $markaModelSql = "CASE WHEN d.marka IS NOT NULL AND d.marka != '' AND d.model IS NOT NULL AND d.model != '' THEN CONCAT(' [', d.marka, ' ', d.model, ']') WHEN d.marka IS NOT NULL AND d.marka != '' THEN CONCAT(' [', d.marka, ']') WHEN d.model IS NOT NULL AND d.model != '' THEN CONCAT(' [', d.model, ']') ELSE '' END";
        $noSql = "CASE WHEN d.demirbas_no IS NOT NULL AND d.demirbas_no != '' THEN CONCAT(d.demirbas_no, ' - ') ELSE '' END";

        // Sayaç ise seri numarasını da ekle
        $textSelect = "CONCAT($noSql, d.demirbas_adi, $markaModelSql, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ')')";
        if ($type === 'sayac') {
            $textSelect = "CONCAT($noSql, d.demirbas_adi, $markaModelSql, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ') - SN: ', COALESCE(d.seri_no, '-'))";
        } else {
            // Diğer demirbaşlarda da varsa seri numarasını göster (Tablet, Simkart vb.)
            $textSelect = "CONCAT($noSql, d.demirbas_adi, $markaModelSql, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ')', CASE WHEN d.seri_no IS NOT NULL AND d.seri_no != '' THEN CONCAT(' - SN: ', d.seri_no) ELSE '' END)";
        }

        $stockFilter = "";
        if ($type !== 'all') {
            $stockFilter = " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) > 0";
        }

        $lokasyonFilter = "";
        if ($type === 'sayac') {
            $lokasyonFilter = " AND (d.lokasyon = 'bizim_depo' OR d.lokasyon IS NULL OR d.lokasyon = '')";
        }

        $sql = $this->db->prepare("
            SELECT 
                d.id,
                $textSelect as text,
                (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) as kalan_miktar,
                d.seri_no
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.firma_id = ? AND d.silinme_tarihi IS NULL
                AND (d.demirbas_no LIKE ? OR d.demirbas_adi LIKE ? OR d.marka LIKE ? OR d.seri_no LIKE ?)
                AND d.durum NOT IN ('pasif', 'hurda')
                $whereType
                $stockFilter
                $lokasyonFilter
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
            SELECT d.*, k.tur_adi as kategori_adi,
            (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) as dynamic_kalan
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
        $kalan = $data->dynamic_kalan ?? 1;
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
            <td class="text-center">
                <div class="custom-checkbox-container d-inline-block">
                    <input type="checkbox" class="custom-checkbox-input sayac-select" value="' . $enc_id . '" id="chk_' . $data->id . '">
                    <label class="custom-checkbox-label" for="chk_' . $data->id . '"></label>
                </div>
            </td>
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . ($data->demirbas_no ?? '-') . '</td>
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
            <td class="text-end">' . Helper::formattedMoney($data->edinme_tutari ?? 0) . ' ₺' . '</td>
            <td>' . ($data->edinme_tarihi ? date('d.m.Y', strtotime($data->edinme_tarihi)) : '-') . '</td>
            <td class="text-center text-nowrap">
                <div class="dropdown d-inline-block">
                    <button class="btn btn-soft-secondary btn-sm dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                        ' . ($kalan > 0 ? '<li><a class="dropdown-item py-2 zimmet-ver text-warning" href="javascript:void(0);" data-id="' . $enc_id . '" data-raw-id="' . $data->id . '" data-name="' . htmlspecialchars($data->demirbas_adi) . '" data-kalan="' . $kalan . '"><i class="bx bx-transfer me-2"></i> Zimmet Ver</a></li>' : '') . '
                        <li><a class="dropdown-item py-2 duzenle text-primary" href="javascript:void(0);" data-id="' . $enc_id . '"><i class="bx bx-edit me-2"></i> Düzenle</a></li>
                        <li><a class="dropdown-item py-2 demirbas-sil text-danger" href="javascript:void(0);" data-id="' . $enc_id . '" data-name="' . htmlspecialchars($data->demirbas_adi) . '"><i class="bx bx-trash me-2"></i> Sil</a></li>
                    </ul>
                </div>
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
                        (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) as kalan_miktar,
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
            if (!empty($request['lokasyon'])) {
                if ($request['lokasyon'] === 'bizim_depo') {
                    $whereSql .= " AND (d.lokasyon = :lokasyon OR d.lokasyon IS NULL OR d.lokasyon = '')";
                } else {
                    $whereSql .= " AND d.lokasyon = :lokasyon";
                }
                $params['lokasyon'] = $request['lokasyon'];
            }
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
                2 => 'd.demirbas_no',
                3 => 'k.tur_adi',
                4 => 'd.demirbas_adi',
                5 => 'CONCAT_WS(" ", d.marka, d.model, d.seri_no)',
                7 => 'd.durum',
                8 => 'd.edinme_tutari',
                9 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        } elseif ($tab === 'sayac') {
            // sayac-deposu.js indices: 0=>cb, 1=>adi, 2=>marka_model, 3=>seri, 4=>stok, 5=>durum, 6=>tarih, 7=>islemler
            $colSearchMap = [
                1 => 'd.demirbas_adi',
                2 => 'CONCAT_WS(" ", d.marka, d.model)',
                3 => 'd.seri_no',
                5 => 'd.durum',
                6 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        } else {
            // aparat: 0=>sira, 1=>adi, 2=>marka_model, 3=>seri, 4=>stok, 5=>durum, 6=>tarih, 7=>islemler
            $colSearchMap = [
                1 => 'd.demirbas_adi',
                2 => 'CONCAT_WS(" ", d.marka, d.model)',
                3 => 'd.seri_no',
                5 => 'd.durum',
                6 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        }

        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $colIdx => $col) {
                if (!empty($col['search']['value']) && isset($colSearchMap[$colIdx])) {
                    $field = $colSearchMap[$colIdx];
                    $searchValue = $col['search']['value'];
                    $paramKey = "col_search_" . $colIdx;

                    // Gelişmiş Filtre Ayrıştırıcı (mode:value)
                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);

                        // Değerleri ayır
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {

                            // Tarih sütunu için d.m.Y -> Y-m-d dönüşümü
                            if ($tab === 'demirbas' && $colIdx == 9)
                                $field = 'd.edinme_tarihi';
                            elseif ($tab === 'sayac' && $colIdx == 6)
                                $field = 'd.edinme_tarihi';
                            elseif ($tab === 'aparat' && $colIdx == 6)
                                $field = 'd.edinme_tarihi';

                            $isDateColumn = ($field === 'd.edinme_tarihi');

                            if ($isDateColumn) {
                                if ($val && strpos($val, '.') !== false)
                                    $val = \App\Helper\Date::Ymd($val, 'Y-m-d');
                                if ($val2 && strpos($val2, '.') !== false)
                                    $val2 = \App\Helper\Date::Ymd($val2, 'Y-m-d');
                            }

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramKey . "_" . $vIdx;

                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } else {
                                                if ($isDateColumn && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::Ymd($v, 'Y-m-d');
                                                    $orConditions[] = "$field = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        $searchWhere .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $searchWhere .= " AND $field NOT LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "$val%";
                                    break;
                                case 'ends_with':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val";
                                    break;
                                case 'equals':
                                    $searchWhere .= " AND $field = :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'not_equals':
                                    $searchWhere .= " AND $field != :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'before':
                                    $searchWhere .= " AND $field < :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'after':
                                    $searchWhere .= " AND $field > :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramKey . "_1";
                                        $p2 = $paramKey . "_2";
                                        $searchWhere .= " AND $field BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val;
                                        $params[$p2] = $val2;
                                    }
                                    break;
                                case 'null':
                                    $searchWhere .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                    break;
                                case 'not_null':
                                    $searchWhere .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'";
                                    break;
                            }
                        }
                    } else {
                        // Basit arama (colon yoksa varsayılan: 'contains')
                        $searchWhere .= " AND $field LIKE :$paramKey";
                        $params[$paramKey] = "%$searchValue%";
                    }
                }
            }
        }

        // Durum Filtresi (Üst butonlar)
        $statusFilter = $request['status_filter'] ?? null;
        if (!empty($statusFilter)) {
            if ($statusFilter === 'bosta') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) > 0 AND LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi'";
            } elseif ($statusFilter === 'zimmetli') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) < COALESCE(d.miktar, 1) AND LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi'";
            } elseif ($statusFilter === 'hurda') {
                $searchWhere .= " AND LOWER(d.durum) = 'hurda'";
            } elseif ($statusFilter === 'kaskiye') {
                $searchWhere .= " AND LOWER(d.durum) = 'kaskiye teslim edildi'";
            }
        }

        // Envanter Raporu Filtreleri (Sadece Demirbaş tabında kullanılır)
        $inventoryKatAdi = $request['inventory_kat_adi'] ?? null;
        $inventoryType = $request['inventory_type'] ?? null;

        if (!empty($inventoryKatAdi) && !empty($inventoryType)) {
            $searchWhere .= " AND k.tur_adi = :inv_kat";
            $params['inv_kat'] = $inventoryKatAdi;

            if ($inventoryType === 'bosta') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) > 0";
            } elseif ($inventoryType === 'zimmetli') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) < COALESCE(d.miktar, 1) AND d.durum NOT IN ('arizali', 'hurda')";
            } elseif ($inventoryType === 'arizali') {
                $searchWhere .= " AND LOWER(d.durum) = 'arizali'";
            } elseif ($inventoryType === 'hurda') {
                $searchWhere .= " AND LOWER(d.durum) LIKE '%hurda%'";
            }
        }

        // Toplam kayıt sayısı (tab + lokasyon filtresi ile)
        $totalSql = "SELECT COUNT(d.id)" . $fromSql . $whereSql;
        $stmtTotal = $this->db->prepare($totalSql);
        
        // Sadece SQL'de bulunan parametreleri bind edelim
        if (strpos($totalSql, ':firma_id') !== false) $stmtTotal->bindValue(':firma_id', $params['firma_id']);
        if (strpos($totalSql, ':lokasyon') !== false) $stmtTotal->bindValue(':lokasyon', $params['lokasyon']);
        
        $stmtTotal->execute();
        $totalRecords = $stmtTotal->fetchColumn();

        // Filtrelenmiş kayıt sayısı
        $filterSql = "SELECT COUNT(d.id)" . $fromSql . $whereSql . $searchWhere;
        $stmtFilter = $this->db->prepare($filterSql);
        foreach ($params as $key => $val) {
            if (strpos($filterSql, $key) !== false) {
                $stmtFilter->bindValue($key, $val);
            }
        }
        $stmtFilter->execute();
        $recordsFiltered = $stmtFilter->fetchColumn();

        // Sıralama
        if ($tab === 'demirbas') {
            $colMapOrder = [
                0 => 'd.id',
                1 => 'd.id',
                2 => 'd.demirbas_no',
                3 => 'k.tur_adi',
                4 => 'd.demirbas_adi',
                5 => 'd.marka',
                7 => 'd.durum',
                8 => 'd.edinme_tutari',
                9 => 'd.edinme_tarihi'
            ];
        } elseif ($tab === 'sayac') {
            // sayac-deposu.js: 0=>cb, 1=>adi, 2=>marka_model, 3=>seri, 4=>stok, 5=>durum, 6=>tarih, 7=>islemler
            $colMapOrder = [
                0 => 'd.id',
                1 => 'd.demirbas_adi',
                2 => 'd.marka',
                3 => 'd.seri_no',
                4 => 'kalan_miktar',
                5 => 'd.durum',
                6 => 'd.edinme_tarihi'
            ];
        } else {
            // aparat
            $colMapOrder = [
                0 => 'd.id',
                1 => 'd.demirbas_adi',
                2 => 'd.marka',
                3 => 'd.seri_no',
                4 => 'kalan_miktar',
                5 => 'd.durum',
                6 => 'd.edinme_tarihi'
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
            if (strpos($finalSql, $key) !== false) {
                $stmt->bindValue($key, $val);
            }
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

    /**
     * Seri numarası çakışma kontrolü
     */
    public function checkSeriNo($seri_no, $exclude_id = null)
    {
        if (empty($seri_no)) return false;

        $sql = "SELECT id FROM {$this->table} WHERE seri_no = ? AND silinme_tarihi IS NULL";
        $params = [$seri_no];

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    /**
     * Filtrelenmiş tüm kayıtların ID'lerini getirir
     */
    public function getFilteredIds($request, $tab = 'demirbas')
    {
        $search = $request['search_val'] ?? null;
        
        $fromSql = " FROM {$this->table} d
                    LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'";

        $whereSql = " WHERE d.firma_id = :firma_id";
        $params = ['firma_id' => $_SESSION['firma_id']];

        // Tab filtremesi
        $sayacCondition = "(LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%')";
        $aparatCondition = "(LOWER(k.tur_adi) LIKE '%aparat%' OR k.id = 645)";

        if ($tab === 'sayac' || $tab === 'sayac_bizim_depo') {
            $whereSql .= " AND " . $sayacCondition;
            if ($tab === 'sayac_bizim_depo') {
                $whereSql .= " AND d.lokasyon = 'bizim_depo'";
            }
        } elseif ($tab === 'aparat') {
            $whereSql .= " AND " . $aparatCondition;
        } else {
            $baseWhere = " AND d.silinme_tarihi IS NULL";
            if ($tab === 'demirbas') {
                $whereSql .= " AND NOT " . $sayacCondition . " AND NOT " . $aparatCondition;
            }
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
            $colSearchMap = [
                2 => 'd.demirbas_no',
                3 => 'k.tur_adi',
                4 => 'd.demirbas_adi',
                5 => 'CONCAT_WS(" ", d.marka, d.model, d.seri_no)',
                7 => 'd.durum',
                8 => 'd.edinme_tutari',
                9 => 'DATE_FORMAT(d.edinme_tarihi, "%d.%m.%Y")'
            ];
        } else {
            $colSearchMap = [
                1 => 'd.demirbas_adi',
                2 => 'CONCAT_WS(" ", d.marka, d.model)',
                3 => 'd.seri_no',
                5 => 'd.durum',
                6 => 'd.edinme_tarihi'
            ];
        }

        $column_searches = $request['column_searches'] ?? [];
        if (!empty($column_searches)) {
            foreach ($column_searches as $colIdx => $searchValue) {
                if (!empty($searchValue) && isset($colSearchMap[$colIdx])) {
                    $field = $colSearchMap[$colIdx];
                    $paramKey = "col_search_" . $colIdx;

                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['multi', 'null', 'not_null'])) {
                            
                            if (($tab === 'demirbas' && $colIdx == 9) || ($tab === 'sayac' && $colIdx == 8) || ($tab === 'aparat' && $colIdx == 8)) {
                                $field = 'd.edinme_tarihi';
                            }
                            $isDateColumn = ($field === 'd.edinme_tarihi');

                            if ($isDateColumn) {
                                if ($val && strpos($val, '.') !== false) $val = \App\Helper\Date::Ymd($val, 'Y-m-d');
                                if ($val2 && strpos($val2, '.') !== false) $val2 = \App\Helper\Date::Ymd($val2, 'Y-m-d');
                            }

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramKey . "_" . $vIdx;
                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } else {
                                                if ($isDateColumn && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::Ymd($v, 'Y-m-d');
                                                    $orConditions[] = "$field = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        $searchWhere .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains': $searchWhere .= " AND $field LIKE :$paramKey"; $params[$paramKey] = "%$val%"; break;
                                case 'not_contains': $searchWhere .= " AND $field NOT LIKE :$paramKey"; $params[$paramKey] = "%$val%"; break;
                                case 'starts_with': $searchWhere .= " AND $field LIKE :$paramKey"; $params[$paramKey] = "$val%"; break;
                                case 'ends_with': $searchWhere .= " AND $field LIKE :$paramKey"; $params[$paramKey] = "%$val"; break;
                                case 'equals': $searchWhere .= " AND $field = :$paramKey"; $params[$paramKey] = $val; break;
                                case 'not_equals': $searchWhere .= " AND $field != :$paramKey"; $params[$paramKey] = $val; break;
                                case 'before': $searchWhere .= " AND $field < :$paramKey"; $params[$paramKey] = $val; break;
                                case 'after': $searchWhere .= " AND $field > :$paramKey"; $params[$paramKey] = $val; break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramKey . "_1"; $p2 = $paramKey . "_2";
                                        $searchWhere .= " AND $field BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val; $params[$p2] = $val2;
                                    }
                                    break;
                                case 'null': $searchWhere .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')"; break;
                                case 'not_null': $searchWhere .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'"; break;
                            }
                        }
                    } else {
                        $searchWhere .= " AND $field LIKE :$paramKey";
                        $params[$paramKey] = "%$searchValue%";
                    }
                }
            }
        }

        // Durum Filtresi
        $statusFilter = $request['status_filter'] ?? null;
        if (!empty($statusFilter)) {
            if ($statusFilter === 'bosta') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) > 0 AND LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi'";
            } elseif ($statusFilter === 'zimmetli') {
                $searchWhere .= " AND (COALESCE(d.miktar, 1) - COALESCE((SELECT SUM(z2.teslim_miktar - COALESCE((SELECT SUM(h2.miktar) FROM demirbas_hareketler h2 WHERE h2.zimmet_id = z2.id AND h2.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h2.silinme_tarihi IS NULL), 0)) FROM demirbas_zimmet z2 WHERE z2.demirbas_id = d.id AND z2.durum = 'teslim' AND z2.silinme_tarihi IS NULL), 0)) < COALESCE(d.miktar, 1) AND LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi'";
            } elseif ($statusFilter === 'hurda') {
                $searchWhere .= " AND LOWER(d.durum) = 'hurda'";
            } elseif ($statusFilter === 'kaskiye') {
                $searchWhere .= " AND LOWER(d.durum) = 'kaskiye teslim edildi'";
            }
        }

        $finalSql = "SELECT d.id" . $fromSql . $whereSql . $searchWhere;
        $stmt = $this->db->prepare($finalSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
