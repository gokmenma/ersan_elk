<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class HakedisSozlesmeModel extends Model
{
    protected $table = 'hakedis_sozlesmeler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $draw = $params['draw'] ?? 0;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $search = $params['search']['value'] ?? '';
        $orders = $params['order'] ?? [];
        $columns = $params['columns'] ?? [];

        $where = "s.firma_id = :firma_id AND s.silinme_tarihi IS NULL";
        $bindParams = [':firma_id' => $firma_id];

        // Durum Filtresi (Quick status filter)
        $status_filter = $params['status_filter'] ?? 'all';
        if ($status_filter === 'aktif') {
            $where .= " AND s.durum = 'aktif'";
        } elseif ($status_filter === 'tamamlandi') {
            $where .= " AND s.durum = 'tamamlandi'";
        } elseif ($status_filter === 'pasif') {
            $where .= " AND s.durum = 'pasif'";
        }

        // Genel Arama
        if (!empty($search)) {
            $where .= " AND (s.idare_adi LIKE :search OR s.isin_adi LIKE :search OR s.isin_yuklenicisi LIKE :search OR s.ihale_kayit_no LIKE :search)";
            $bindParams[':search'] = "%$search%";
        }

        // Sütun Bazlı Canlı Arama (Datatables Header Filters)
        if (!empty($columns)) {
            $colMap = [
                0 => 's.id',
                1 => 's.idare_adi',
                2 => 's.isin_adi',
                3 => 's.sozlesme_tarihi',
                4 => 's.isin_bitecegi_tarih',
                5 => 's.sozlesme_bedeli',
                6 => 's.durum'
            ];

            foreach ($columns as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = $column['search']['value'];
                    $paramName = "col_" . $i;

                    if (strpos($val, ':') !== false) {
                        list($mode, $filterVal) = explode(':', $val, 2);
                        $vals = explode('|', $filterVal);
                        $filterVal = $vals[0];

                        // Sayısal alan kontrolü
                        if ($field === 's.sozlesme_bedeli' || $field === 's.id') {
                            if (!in_array($mode, ['null', 'not_null'])) {
                                $filterVal = Helper::formattedMoneyToNumber($filterVal);
                            }
                        }

                        switch ($mode) {
                            case 'multi':
                                if (!empty($vals)) {
                                    $multiConditions = [];
                                    foreach ($vals as $vIdx => $v) {
                                        $vParam = $paramName . "_m_" . $vIdx;
                                        $multiConditions[] = "$field LIKE :$vParam";
                                        $bindParams[$vParam] = "%$v%";
                                    }
                                    $where .= " AND (" . implode(" OR ", $multiConditions) . ")";
                                }
                                break;
                            case 'contains':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal%";
                                break;
                            case 'not_contains':
                                $where .= " AND $field NOT LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal%";
                                break;
                            case 'equals':
                                $where .= " AND $field = :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'not_equals':
                                $where .= " AND $field != :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'starts_with':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "$filterVal%";
                                break;
                            case 'ends_with':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal";
                                break;
                            case 'greater_than':
                                $where .= " AND $field > :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'less_than':
                                $where .= " AND $field < :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'greater_equal':
                                $where .= " AND $field >= :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'less_equal':
                                $where .= " AND $field <= :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'before':
                            case 'date_before':
                                $where .= " AND DATE($field) <= :$paramName";
                                $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal));
                                break;
                            case 'after':
                            case 'date_after':
                                $where .= " AND DATE($field) >= :$paramName";
                                $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal));
                                break;
                            case 'between':
                            case 'date_range':
                                if (count($vals) === 2) {
                                    $v1 = $paramName . "_1";
                                    $v2 = $paramName . "_2";
                                    $where .= " AND DATE($field) BETWEEN :$v1 AND :$v2";
                                    $bindParams[$v1] = date('Y-m-d', strtotime($vals[0]));
                                    $bindParams[$v2] = date('Y-m-d', strtotime($vals[1]));
                                }
                                break;
                            case 'null':
                                $where .= " AND ($field IS NULL OR $field = '')";
                                break;
                            case 'not_null':
                                $where .= " AND ($field IS NOT NULL AND $field != '')";
                                break;
                        }
                    } else {
                        $where .= " AND $field LIKE :$paramName";
                        $bindParams[$paramName] = "%$val%";
                    }
                }
            }
        }

        // Toplam Kayıt Sayısı
        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = :firma_id AND silinme_tarihi IS NULL");
        $stmtTotal->execute([':firma_id' => $firma_id]);
        $totalCount = (int) $stmtTotal->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} s WHERE $where");
        foreach ($bindParams as $k => $v) {
            $stmtFiltered->bindValue($k, $v);
        }
        $stmtFiltered->execute();
        $filteredCount = (int) $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY s.id DESC";
        if (!empty($orders)) {
            $orderArr = [];
            $colSortMap = [
                0 => 's.id',
                1 => 's.idare_adi',
                2 => 's.isin_adi',
                3 => 's.sozlesme_tarihi',
                4 => 's.isin_bitecegi_tarih',
                5 => 's.sozlesme_bedeli',
                6 => 's.durum'
            ];
            foreach ($orders as $order) {
                $colIdx = (int) ($order['column'] ?? 0);
                $colDir = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                if (isset($colSortMap[$colIdx])) {
                    $orderArr[] = "{$colSortMap[$colIdx]} $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        // Ana Sorgu
        $sql = "SELECT s.* FROM {$this->table} s WHERE $where $orderQuery LIMIT :start, :length";
        $stmt = $this->db->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($draw),
            "recordsTotal" => $totalCount,
            "recordsFiltered" => $filteredCount,
            "data" => $data,
            "summary" => $this->summary($firma_id)
        ];
    }

    public function summary(int $firma_id): object
    {
        $sql = "SELECT 
                COUNT(id) as toplam_sozlesme,
                SUM(CASE WHEN durum = 'aktif' THEN 1 ELSE 0 END) as aktif_sayisi,
                SUM(CASE WHEN durum = 'tamamlandi' THEN 1 ELSE 0 END) as tamamlanan_sayisi,
                SUM(CASE WHEN durum = 'pasif' THEN 1 ELSE 0 END) as pasif_sayisi,
                COALESCE(SUM(sozlesme_bedeli), 0) as toplam_bedel,
                COALESCE(SUM(CASE WHEN durum = 'aktif' THEN sozlesme_bedeli ELSE 0 END), 0) as aktif_bedel,
                COALESCE(SUM(CASE WHEN durum = 'tamamlandi' THEN sozlesme_bedeli ELSE 0 END), 0) as tamamlanan_bedel,
                COALESCE(SUM(CASE WHEN durum = 'pasif' THEN sozlesme_bedeli ELSE 0 END), 0) as pasif_bedel
                FROM {$this->table}
                WHERE firma_id = :firma_id AND silinme_tarihi IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':firma_id' => $firma_id]);
        $summary = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$summary) {
            $summary = (object) [
                'toplam_sozlesme' => 0,
                'aktif_sayisi' => 0,
                'tamamlanan_sayisi' => 0,
                'pasif_sayisi' => 0,
                'toplam_bedel' => 0,
                'aktif_bedel' => 0,
                'tamamlanan_bedel' => 0,
                'pasif_bedel' => 0
            ];
        }

        return $summary;
    }
}
