<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Helper;
use App\Helper\Security;
use PDO;

use App\Model\TanimlamalarModel;



class GelirGiderModel extends Model
{
    protected $table = 'gelir_gider';

    protected $sql_table = "sql_gelir_gider";

    public function __construct()
    {
        parent::__construct($this->table);
    }





    public function all($yil = null, $ay = null, $kategori = null)
    {
        $where = "1=1";
        $params = [];
        
        if ($yil) {
            $where .= " AND YEAR(tarih) = :yil";
            $params['yil'] = $yil;
        }
        if ($ay) {
            $where .= " AND MONTH(tarih) = :ay";
            $params['ay'] = $ay;
        }
        if ($kategori) {
            $where .= " AND type = :kategori";
            $params['kategori'] = $kategori;
        }

        $sql = $this->db->prepare("SELECT g.*, t.tur_adi as kategori_adi 
                                    FROM $this->table g
                                    LEFT JOIN tanimlamalar t ON g.kategori = t.id
                                    WHERE $where
                                    ORDER BY g.tarih DESC, g.id DESC");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function find($id)
    {
        $sql = $this->db->prepare("SELECT g.*, t.tur_adi as kategori_adi 
                                    FROM $this->table g
                                    LEFT JOIN tanimlamalar t ON g.kategori = t.id
                                    WHERE g.id = :id");
        $sql->execute(['id' => $id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getGelirGiderTableRow($id)
    {
        $Tanimlama = new TanimlamalarModel();
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);
        
        // Bu metod artık DataTables server-side ile uyumlu formatta veri döndürmeli
        // Ancak eski kodun çalışması için bir miktar uyumluluk bırakıyorum
        $kayit_sayisi = 0; // Bu artık server-side'da farklı hesaplanıyor

        //Eğer bakiye 0'dan küçükse danger, büyükse success
        $color = ($data->type == 2) ? 'danger' : 'success'; // Basit bakiye mantığı

          return '<tr id="gelir_gider_' . $data->id . '" data-id="' . $enc_id . '">
            <td class="text-center">' . $kayit_sayisi . '</td>
            <td class="text-center">' . date('d.m.Y H:i', strtotime($data->kayit_tarihi)) . '</td>
            <td class="text-center">' . Helper::getBadge($data->type) . '</td>
            <td class="text-center">' . ($data->kategori_adi ?: '-') . '</td>
            <td>' . ($data->tarih ?: '-') . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->tutar) . '</td>
            <td class="text-end text-' . $color . '">' . Helper::formattedMoney($data->tutar) . '</td>
            <td>' . ($data->aciklama ?: '-') . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                            </a>
                            <a class="dropdown-item gelir-gider-sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    //Toplam gelir, gider ve bakiye getir
    public function summary($params = [])
    {
        $where = "1=1";
        $bindParams = [];
        
        $this->prepareFilters($params, $where, $bindParams);
        
        $sql = $this->db->prepare("
            SELECT 
                ROUND(SUM(CASE WHEN type = 1 THEN CAST(tutar AS DECIMAL(15,2)) ELSE 0 END), 2) AS toplam_gelir,
                ROUND(SUM(CASE WHEN type = 2 THEN CAST(tutar AS DECIMAL(15,2)) ELSE 0 END),2) AS toplam_gider,
                ROUND(SUM(CASE WHEN type = 1 THEN CAST(tutar AS DECIMAL(15,2)) ELSE 0 END) - SUM(CASE WHEN type = 2 THEN CAST(tutar AS DECIMAL(15,2)) ELSE 0 END),2) AS bakiye
            FROM sql_gelir_gider g
            WHERE $where
        ");
        $sql->execute($bindParams);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    //Kasa Özetini  getir
    public function getGelirGiderStatics()
    {
        $sql = $this->db->prepare("
            SELECT 
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END), 2) AS toplam_gelir,
                ROUND(SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS toplam_gider,
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END) - SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS bakiye
            FROM $this->table
           
        ");
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $search = $params['search']['value'];
        $orders = $params['order'];
        $columns = $params['columns'];

        // Filtreler (Yıl, Ay, Tip)
        $yil = $params['yil'] ?? null;
        $ay = $params['ay'] ?? null;
        $tip = $params['tip'] ?? null;

        $where = "1=1";
        $bindParams = [];

        $this->prepareFilters($params, $where, $bindParams);

        // Toplam Kayıt Sayısı
        $totalSql = "SELECT COUNT(*) FROM $this->table";
        $totalCount = $this->db->query($totalSql)->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $filteredSql = "SELECT COUNT(*) FROM sql_gelir_gider g WHERE $where";
        $stmtFiltered = $this->db->prepare($filteredSql);
        $stmtFiltered->execute($bindParams);
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY g.tarih DESC, g.id DESC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?: $columns[$colIdx]['name'];
                if ($colName == "kategori_adi") $colName = "g.kategori";
                else if ($colName && $colName != "actions") $colName = "g." . $colName;
                
                if ($colName && $colName != "actions") {
                    $orderArr[] = "$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        // Ana Sorgu
        $sql = "SELECT g.*, g.kategori as kategori_adi 
                FROM sql_gelir_gider g 
                WHERE $where $orderQuery LIMIT :start, :length";
        $stmt = $this->db->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
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

    /**
     * Gelişmiş filtreleri işleyip SQL sorgusuna ekler
     */
    private function processAdvancedFilters($column, $searchVal, &$query, &$params, $idx)
    {
        if (empty($searchVal)) return;

        // Mode ve değeri ayır (e.g., "contains:test", "multi:1|2")
        $parts = explode(':', $searchVal, 2);
        if (count($parts) < 2) {
            // Standart arama
            $query .= " AND {$column} LIKE :col_search_{$idx}";
            $params[":col_search_{$idx}"] = "%{$searchVal}%";
            return;
        }

        $mode = $parts[0];
        $value = $parts[1];

        // Tutar gibi varchar kolonlar için sayısal karşılaştırma gerekebilir
        $isNumericCol = strpos($column, 'tutar') !== false || strpos($column, 'bakiye') !== false;
        $compareCol = $isNumericCol ? "CAST({$column} AS DECIMAL(15,2))" : $column;

        switch ($mode) {
            case 'contains':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}%";
                break;
            case 'not_contains':
                $query .= " AND {$column} NOT LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}%";
                break;
            case 'starts_with':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "{$value}%";
                break;
            case 'ends_with':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}";
                break;
            case 'equals':
                $query .= " AND {$compareCol} = :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'not_equals':
                $query .= " AND {$compareCol} != :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'greater_than':
                $query .= " AND {$compareCol} > :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'less_than':
                $query .= " AND {$compareCol} < :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'greater_equal':
                $query .= " AND {$compareCol} >= :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'less_equal':
                $query .= " AND {$compareCol} <= :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'between':
                $range = explode('|', $value);
                if (count($range) == 2) {
                    $query .= " AND {$compareCol} BETWEEN :col_search_{$idx}_1 AND :col_search_{$idx}_2";
                    $params[":col_search_{$idx}_1"] = $range[0];
                    $params[":col_search_{$idx}_2"] = $range[1];
                }
                break;
            case 'multi':
                $values = explode('|', $value);
                if (!empty($values)) {
                    $placeholders = [];
                    foreach ($values as $vIdx => $v) {
                        $pName = ":col_search_{$idx}_{$vIdx}";
                        $placeholders[] = $pName;
                        $params[$pName] = $v;
                    }
                    $query .= " AND {$column} IN (" . implode(',', $placeholders) . ")";
                }
                break;
            case 'null':
                $query .= " AND ({$column} IS NULL OR {$column} = '')";
                break;
            case 'not_null':
                $query .= " AND ({$column} IS NOT NULL AND {$column} != '')";
                break;
            case 'before':
                $query .= " AND {$compareCol} < :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'after':
                $query .= " AND {$compareCol} > :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
        }
    }

    /**
     * Ortak filtre hazırlama mantığı
     */
    private function prepareFilters($params, &$where, &$bindParams)
    {
        $yil = $params['yil'] ?? null;
        $ay = $params['ay'] ?? null;
        $tip = $params['tip'] ?? ($params['kategori'] ?? null);
        $search = $params['search']['value'] ?? null;
        $columns = $params['columns'] ?? [];

        $prefix = (strpos($where, 'g.') !== false || isset($params['draw']) || isset($params['action'])) ? 'g.' : '';

        if ($yil) {
            $where .= " AND YEAR({$prefix}tarih) = :yil";
            $bindParams['yil'] = $yil;
        }
        if ($ay) {
            $where .= " AND MONTH({$prefix}tarih) = :ay";
            $bindParams['ay'] = $ay;
        }
        if ($tip) {
            $where .= " AND {$prefix}type = :tip";
            $bindParams['tip'] = $tip;
        }

        // Global Arama
        if (!empty($search)) {
            $where .= " AND ({$prefix}aciklama LIKE :search OR {$prefix}kategori LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Sütun Bazlı Arama (Gelişmiş Filtreler)
        foreach ($columns as $i => $column) {
            if (!empty($column['search']['value'])) {
                $searchVal = $column['search']['value'];
                $colName = $column['data'] ?: $column['name'];
                
                // SQL view field mapping
                if ($colName == "kategori_adi" || $colName == "kategori") {
                    $dbField = "{$prefix}kategori"; 
                } else if ($colName != "actions") {
                    $dbField = "{$prefix}" . $colName;
                } else {
                    continue;
                }

                $this->processAdvancedFilters($dbField, $searchVal, $where, $bindParams, $i);
            }
        }
    }

    /**
     * DataTable'daki select filtreleri için benzersiz değerleri döner
     */
    public function getUniqueValues($column, $params = [])
    {
        // View alanlarını eşle
        if ($column == "kategori_adi" || $column == "kategori") {
            // Kategoriler için sql_gelir_gider view'ındaki 'kategori' alanını (Isim) getirelim
            $sql = "SELECT DISTINCT kategori FROM sql_gelir_gider WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC";
        } else {
            $sql = "SELECT DISTINCT {$column} FROM sql_gelir_gider WHERE {$column} IS NOT NULL AND {$column} != '' ORDER BY {$column} ASC";
        }

        return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }
}