<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class CariHareketleriModel extends Model
{
    protected $table = 'cari_hareketleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getHareketler($cari_id)
    {
        $sql = "SELECT *, 
                @bakiye := @bakiye + (alacak - borc) AS yuruyen_bakiye
                FROM $this->table, (SELECT @bakiye := 0) as vars
                WHERE cari_id = :cari_id AND silinme_tarihi IS NULL
                ORDER BY islem_tarihi ASC, id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cari_id' => $cari_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $cari_id = $params['cari_id'];
        $search = $params['search']['value'] ?? "";
        $orders = $params['order'] ?? [];
        $columns = $params['columns'] ?? [];
        $filter_type = $params['filter_type'] ?? 'all';

        $where = "h.cari_id = :cari_id AND h.silinme_tarihi IS NULL";
        $bindParams = ['cari_id' => $cari_id];

        // Tümü, Girişler, Çıkışlar Filtresi
        if ($filter_type === 'verdim') {
            $where .= " AND h.alacak > 0";
        } elseif ($filter_type === 'aldim') {
            $where .= " AND h.borc > 0";
        }

        // Global Arama
        if (!empty($search)) {
            $where .= " AND (h.belge_no LIKE :search OR h.aciklama LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Sütun Bazlı Arama (Advanced Filters)
        if (!empty($columns)) {
            $colMap = [
                0 => 'h.islem_tarihi',
                1 => 'h.belge_no',
                2 => 'h.aciklama',
                3 => 'h.borc',
                4 => 'h.alacak'
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

                        // Sayısal alanlar için temizlik yap
                        if ($field === 'h.borc' || $field === 'h.alacak') {
                            if (!in_array($mode, ['null', 'not_null'])) {
                                $filterVal = \App\Helper\Helper::formattedMoneyToNumber($filterVal);
                            }
                        }

                        switch ($mode) {
                            case 'contains': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal%"; break;
                            case 'not_contains': $where .= " AND $field NOT LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal%"; break;
                            case 'equals': $where .= " AND $field = :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'not_equals': $where .= " AND $field != :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'starts_with': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "$filterVal%"; break;
                            case 'ends_with': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal"; break;
                            case 'greater_than': $where .= " AND $field > :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'less_than': $where .= " AND $field < :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'greater_equal': $where .= " AND $field >= :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'less_equal': $where .= " AND $field <= :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'before': $where .= " AND DATE($field) <= :$paramName"; $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal)); break;
                            case 'after': $where .= " AND DATE($field) >= :$paramName"; $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal)); break;
                            case 'between': 
                                if (count($vals) === 2) {
                                    $v1 = $paramName . "_1";
                                    $v2 = $paramName . "_2";
                                    $where .= " AND DATE($field) BETWEEN :$v1 AND :$v2";
                                    $bindParams[$v1] = date('Y-m-d', strtotime($vals[0]));
                                    $bindParams[$v2] = date('Y-m-d', strtotime($vals[1]));
                                }
                                break;
                            case 'null': $where .= " AND ($field IS NULL OR $field = '')"; break;
                            case 'not_null': $where .= " AND ($field IS NOT NULL AND $field != '')"; break;
                        }
                    } else {
                        $where .= " AND $field LIKE :$paramName";
                        $bindParams[$paramName] = "%$val%";
                    }
                }
            }
        }

        // Toplam Kayıt Sayısı
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $cari_id]);
        $totalCount = $stmt->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) FROM $this->table h WHERE $where");
        foreach($bindParams as $k => $v) {
            $stmtFiltered->bindValue($k, $v);
        }
        $stmtFiltered->execute();
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY h.islem_tarihi DESC, h.id DESC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?? null;
                
                if ($colName && $colName != "actions" && $colName != "yuruyen_bakiye") {
                    $orderArr[] = "h.$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        $sql = "SELECT h.*, 
                (SELECT SUM(alacak - borc) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
                FROM $this->table h
                WHERE $where
                $orderQuery
                LIMIT :start, :length";

        $stmt = $this->db->prepare($sql);
        foreach($bindParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue('length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalCount),
            "recordsFiltered" => intval($filteredCount),
            "data" => $data
        ];
    }
}
