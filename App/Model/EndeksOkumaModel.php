<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class EndeksOkumaModel extends Model
{
    protected $table = 'endeks_okuma';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getMonthlySummary($year, $month)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT personel_id, DAY(tarih) as gun, SUM(okunan_abone_sayisi) as toplam 
                FROM $this->table 
                WHERE firma_id = ? AND YEAR(tarih) = ? AND MONTH(tarih) = ?
                GROUP BY personel_id, DAY(tarih)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $year, $month]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->gun] = $row->toplam;
        }
        return $summary;
    }

    public function getFiltered($startDate, $endDate, $personelId = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.*, p.adi_soyadi as personel_adi 
                FROM $this->table t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";
        $params = [$firmaId];

        if ($startDate) {
            $sql .= " AND t.tarih >= ?";
            $params[] = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
        }
        if ($endDate) {
            $sql .= " AND t.tarih <= ?";
            $params[] = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;
        }
        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }

        $sql .= " ORDER BY t.tarih DESC, t.id ASC";

        // DEBUG
        file_put_contents(dirname(__DIR__, 2) . '/debug_sql.txt', "SQL: $sql\nParams: " . print_r($params, true) . "\n----------------\n", FILE_APPEND);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Server-side DataTable için veri çekme
     */
    public function getDataTable($request, $startDate, $endDate, $personelId = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $params = ['firma_id' => $firmaId];

        // Temel sorgu
        $baseWhere = "t.firma_id = :firma_id AND t.silinme_tarihi IS NULL";

        // Tarih filtreleri
        if ($startDate) {
            $baseWhere .= " AND t.tarih >= :start_date";
            $params['start_date'] = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
        }
        if ($endDate) {
            $baseWhere .= " AND t.tarih <= :end_date";
            $params['end_date'] = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;
        }
        if ($personelId) {
            $baseWhere .= " AND t.personel_id = :personel_id";
            $params['personel_id'] = $personelId;
        }

        // Toplam kayıt sayısı (filtresiz)
        $totalQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t WHERE $baseWhere");
        foreach ($params as $key => $val) {
            $totalQuery->bindValue(":$key", $val);
        }
        $totalQuery->execute();
        $recordsTotal = $totalQuery->fetchColumn();

        // Arama filtresi
        $searchWhere = "";
        if (!empty($request['search']['value'])) {
            $searchValue = "%" . $request['search']['value'] . "%";
            $searchWhere = " AND (
                t.bolge LIKE :search OR
                t.kullanici_adi LIKE :search OR
                p.adi_soyadi LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama
        $colSearchMap = [
            0 => 't.bolge',
            1 => 'p.adi_soyadi',
            2 => 't.sarfiyat',
            3 => 't.ort_sarfiyat_gunluk',
            4 => 't.tahakkuk',
            5 => 't.ort_tahakkuk_gunluk',
            6 => 't.okunan_gun_sayisi',
            7 => 't.okunan_abone_sayisi',
            8 => 't.ort_okunan_abone_sayisi_gunluk',
            9 => 't.okuma_performansi',
            10 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")'
        ];

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

        // Filtrelenmiş kayıt sayısı
        $filteredQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN personel p ON t.personel_id = p.id WHERE $baseWhere $searchWhere");
        foreach ($params as $key => $val) {
            $filteredQuery->bindValue(":$key", $val);
        }
        $filteredQuery->execute();
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama
        $orderColumn = 't.tarih';
        $orderDir = 'DESC';
        $colMap = [
            0 => 't.bolge',
            1 => 'p.adi_soyadi',
            2 => 't.sarfiyat',
            3 => 't.ort_sarfiyat_gunluk',
            4 => 't.tahakkuk',
            5 => 't.ort_tahakkuk_gunluk',
            6 => 't.okunan_gun_sayisi',
            7 => 't.okunan_abone_sayisi',
            8 => 't.ort_okunan_abone_sayisi_gunluk',
            9 => 't.okuma_performansi',
            10 => 't.tarih'
        ];
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = strtoupper($request['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderColumn = $colMap[$orderColIdx];
            }
        }

        // Veri çekme
        $sql = "SELECT t.*, p.adi_soyadi as personel_adi 
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                WHERE $baseWhere $searchWhere 
                ORDER BY $orderColumn $orderDir 
                LIMIT :start, :length";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(":$key", $val);
        }
        $stmt->bindValue(':start', (int) ($request['start'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) ($request['length'] ?? 10), PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ];
    }
}
