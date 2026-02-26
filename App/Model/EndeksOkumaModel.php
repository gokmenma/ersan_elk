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

    public function getSummaryByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.personel_id, t.ekip_kodu_id, t.tarih, SUM(t.okunan_abone_sayisi) as toplam 
                FROM $this->table t
                LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL
                AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'
                GROUP BY t.personel_id, t.ekip_kodu_id, t.tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->ekip_kodu_id][$row->tarih] = $row->toplam;
        }
        return $summary;
    }

    public function getFiltered($startDate, $endDate, $personelId = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.*, p.adi_soyadi as personel_adi, def.ekip_bolge 
                FROM $this->table t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL
                AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";
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
        // file_put_contents(dirname(__DIR__, 2) . '/debug_sql.txt', "SQL: $sql\nParams: " . print_r($params, true) . "\n----------------\n", FILE_APPEND);

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
        $baseWhere = "t.firma_id = :firma_id AND t.silinme_tarihi IS NULL AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";

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
        $totalQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id WHERE $baseWhere");
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
                t.defter LIKE :search OR
                t.sayac_durum LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama (Yeni tablo yapısına göre güncellendi)
        $colSearchMap = [
            0 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
            1 => 't.defter',
            2 => 't.bolge',
            3 => 'def.tur_adi', // Ekip No
            4 => 'p.adi_soyadi',
            5 => 't.okunan_abone_sayisi',
            6 => 't.sayac_durum'
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
        $filteredQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN personel p ON t.personel_id = p.id LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id WHERE $baseWhere $searchWhere");
        foreach ($params as $key => $val) {
            $filteredQuery->bindValue(":$key", $val);
        }
        $filteredQuery->execute();
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama (Yeni tablo yapısına göre güncellendi)
        $orderColumn = 't.tarih';
        $orderDir = 'DESC';
        $colMap = [
            0 => 't.tarih',
            1 => 't.defter',
            2 => 't.bolge',
            3 => 'def.tur_adi',
            4 => 'p.adi_soyadi',
            5 => 't.okunan_abone_sayisi',
            6 => 't.sayac_durum'
        ];
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = strtoupper($request['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderColumn = $colMap[$orderColIdx];
            }
        }

        // Veri çekme (Ekip adı için tanimlamalar joinlendi)
        $sql = "SELECT t.*, p.adi_soyadi as personel_adi, def.tur_adi as ekip_kodu_adi 
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
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

    public function getDailyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $bugun = date('Y-m-d');

        $sql = "SELECT SUM(okunan_abone_sayisi) as toplam 
                FROM $this->table 
                WHERE firma_id = ? 
                AND tarih = ? 
                AND silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $bugun]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->toplam ?? 0;
    }

    public function getMonthlyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $buAy = date('Y-m-01');
        $sonGun = date('Y-m-t');

        $sql = "SELECT SUM(okunan_abone_sayisi) as toplam 
                FROM $this->table 
                WHERE firma_id = ? 
                AND tarih >= ? AND tarih <= ?
                AND silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $buAy, $sonGun]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->toplam ?? 0;
    }

    /**
     * Karşılaştırma raporu için çoklu dönem verileri
     * @param array $periods [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'label' => 'Ocak 2026'], ...]
     * @return array ['personel' => [...], 'bolge' => [...], 'firma' => [...]]
     */
    public function getComparisonByPeriods(array $periods): array
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $result = ['personel' => [], 'bolge' => [], 'firma' => []];

        foreach ($periods as $idx => $period) {
            $sql = "SELECT t.personel_id, t.ekip_kodu_id, 
                        p.adi_soyadi as personel_adi,
                        def.tur_adi as ekip_adi,
                        def.ekip_bolge as bolge,
                        SUM(t.okunan_abone_sayisi) as toplam,
                        COUNT(DISTINCT t.tarih) as gun_sayisi
                    FROM {$this->table} t
                    LEFT JOIN personel p ON t.personel_id = p.id
                    LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                    WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL
                    AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'
                    GROUP BY t.personel_id, t.ekip_kodu_id, p.adi_soyadi, def.tur_adi, def.ekip_bolge";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$firmaId, $period['start'], $period['end']]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $periodLabel = $period['label'];
            $periodTotal = 0;
            $periodGun = 0;
            $periodPersonelSayisi = 0;

            foreach ($rows as $row) {
                $pKey = $row->personel_id . '_' . $row->ekip_kodu_id;

                // Personel bazlı
                if (!isset($result['personel'][$pKey])) {
                    $result['personel'][$pKey] = [
                        'personel_adi' => $row->personel_adi ?: '-',
                        'ekip_adi' => $row->ekip_adi ?: '-',
                        'bolge' => $row->bolge ?: '-',
                        'periods' => []
                    ];
                }
                $result['personel'][$pKey]['periods'][$periodLabel] = [
                    'toplam' => (int) $row->toplam,
                    'gun_sayisi' => (int) $row->gun_sayisi
                ];

                // Bölge bazlı
                $bolge = $row->bolge ?: 'TANIMSIZ';
                if (!isset($result['bolge'][$bolge])) {
                    $result['bolge'][$bolge] = ['periods' => []];
                }
                if (!isset($result['bolge'][$bolge]['periods'][$periodLabel])) {
                    $result['bolge'][$bolge]['periods'][$periodLabel] = ['toplam' => 0, 'personel_sayisi' => 0];
                }
                $result['bolge'][$bolge]['periods'][$periodLabel]['toplam'] += (int) $row->toplam;
                $result['bolge'][$bolge]['periods'][$periodLabel]['personel_sayisi']++;

                $periodTotal += (int) $row->toplam;
                $periodPersonelSayisi++;
            }

            // Firma toplam
            $result['firma'][$periodLabel] = [
                'toplam' => $periodTotal,
                'personel_sayisi' => $periodPersonelSayisi
            ];
        }

        return $result;
    }
}
