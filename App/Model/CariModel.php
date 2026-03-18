<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class CariModel extends Model
{
    protected $table = 'cari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $search = $params['search']['value'];
        $orders = $params['order'];
        $columns = $params['columns'];

        $where = "c.silinme_tarihi IS NULL";
        $bindParams = [];

        if (!empty($search)) {
            $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR c.Telefon LIKE :search OR c.Email LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Toplam Kayıt Sayısı
        $totalCount = $this->db->query("SELECT COUNT(*) FROM $this->table WHERE silinme_tarihi IS NULL")->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $filteredSql = "SELECT COUNT(*) FROM $this->table c WHERE $where";
        $stmtFiltered = $this->db->prepare($filteredSql);
        $stmtFiltered->execute($bindParams);
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY c.CariAdi ASC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?: $columns[$colIdx]['name'];
                
                if ($colName && $colName != "actions" && $colName != "bakiye") {
                    $orderArr[] = "c.$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        // Ana Sorgu (Bakiye ile)
        $sql = "SELECT c.*, 
                (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
                FROM $this->table c 
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

    public function summary()
    {
        $sql = "SELECT 
                ROUND(SUM(ch.borc), 2) as toplam_borc,
                ROUND(SUM(ch.alacak), 2) as toplam_alacak,
                ROUND(SUM(ch.alacak) - SUM(ch.borc), 2) as genel_bakiye
                FROM cari_hareketleri ch
                INNER JOIN cari c ON ch.cari_id = c.id
                WHERE ch.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL";
        return $this->db->query($sql)->fetch(PDO::FETCH_OBJ);
    }
}
