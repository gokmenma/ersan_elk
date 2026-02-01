<?php

namespace App\Model;

use App\Model\Model;
use PDO;
use App\Helper\Date;

class PuantajModel extends Model
{
    protected $table = 'yapilan_isler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getFiltered($startDate, $endDate, $ekipKodu, $workType, $workResult = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi,
                    f.firma_adi as firma,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu
                FROM $this->table t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN firmalar f ON f.id = t.firma_id
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";
        $params = [$firmaId];

        if ($startDate) {
            $sql .= " AND t.tarih >= ?";
            $params[] = Date::Ymd($startDate) ?: $startDate;
        }
        if ($endDate) {
            $sql .= " AND t.tarih <= ?";
            $params[] = Date::Ymd($endDate) ?: $endDate;
        }
        if ($ekipKodu) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $ekipKodu;
        }
        if ($workType) {
            $sql .= " AND (tn.tur_adi = ? OR t.is_emri_tipi = ?)";
            $params[] = $workType;
            $params[] = $workType;
        }
        if ($workResult) {
            $sql .= " AND (tn.is_emri_sonucu = ? OR t.is_emri_sonucu = ?)";
            $params[] = $workResult;
            $params[] = $workResult;
        }

        $sql .= " ORDER BY t.tarih DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getWorkTypes()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Hem yeni normalized hem de eski string alanından unique değerleri al
        $stmt = $this->db->prepare("
            SELECT DISTINCT COALESCE(tn.tur_adi, t.is_emri_tipi) as tur_adi
            FROM $this->table t 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
            WHERE t.firma_id = ? 
            AND COALESCE(tn.tur_adi, t.is_emri_tipi) IS NOT NULL 
            AND COALESCE(tn.tur_adi, t.is_emri_tipi) != ''
        ");
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getWorkResults($personelId = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Hem yeni normalized hem de eski string alanından unique değerleri al
        $sql = "
            SELECT DISTINCT COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu
            FROM $this->table t 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
            WHERE t.firma_id = ? 
            AND COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) IS NOT NULL 
            AND COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) != ''";
        $params = [$firmaId];

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }

        $sql .= " ORDER BY COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public function getMonthlySummary($year, $month)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT personel_id, DAY(tarih) as gun, SUM(sonuclanmis) as toplam 
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

    public function getMonthlySummaryDetailed($year, $month)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // COALESCE ile eski ve yeni alanlardan is_emri_sonucu al
        $sql = "SELECT t.personel_id, DAY(t.tarih) as gun, 
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu, 
                    SUM(t.sonuclanmis) as toplam 
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? AND YEAR(t.tarih) = ? AND MONTH(t.tarih) = ?
                GROUP BY t.personel_id, DAY(t.tarih), COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $year, $month]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->gun][$row->is_emri_sonucu] = $row->toplam;
        }
        return $summary;
    }

    public function getKacakSummary($year, $month)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT ekip_adi, DAY(tarih) as gun, SUM(sayi) as toplam 
                FROM kacak_kontrol 
                WHERE firma_id = ? AND YEAR(tarih) = ? AND MONTH(tarih) = ? AND silinme_tarihi IS NULL
                GROUP BY ekip_adi, DAY(tarih)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $year, $month]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->ekip_adi][$row->gun] = $row->toplam;
        }
        return $summary;
    }

    public function getKacakTeams()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $stmt = $this->db->prepare("SELECT DISTINCT ekip_adi FROM kacak_kontrol WHERE firma_id = ? AND ekip_adi IS NOT NULL AND ekip_adi != '' AND silinme_tarihi IS NULL ORDER BY ekip_adi ASC");
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get mapping of ekip_adi to personel_ids for quick entry feature
     */
    public function getKacakPersonelMapping()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT DISTINCT ekip_adi, personel_ids 
                FROM kacak_kontrol 
                WHERE firma_id = ? AND ekip_adi IS NOT NULL AND ekip_adi != '' AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $mapping = [];
        foreach ($results as $row) {
            // Use the first personel_ids found for each ekip_adi
            if (!isset($mapping[$row->ekip_adi]) && $row->personel_ids) {
                $mapping[$row->ekip_adi] = $row->personel_ids;
            }
        }
        return $mapping;
    }

    /**
     * Server-side DataTable için veri çekme
     */
    public function getDataTable($request, $startDate, $endDate, $ekipKodu = '', $workType = '', $workResult = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $params = ['firma_id' => $firmaId];

        // Temel sorgu
        $baseWhere = "t.firma_id = :firma_id AND t.silinme_tarihi IS NULL";

        // Tarih filtreleri
        if ($startDate) {
            $baseWhere .= " AND t.tarih >= :start_date";
            $params['start_date'] = Date::Ymd($startDate) ?: $startDate;
        }
        if ($endDate) {
            $baseWhere .= " AND t.tarih <= :end_date";
            $params['end_date'] = Date::Ymd($endDate) ?: $endDate;
        }
        if ($ekipKodu) {
            $baseWhere .= " AND t.personel_id = :ekip_kodu";
            $params['ekip_kodu'] = $ekipKodu;
        }
        if ($workType) {
            // Hem yeni normalized hem de eski string alanından filtrele
            $baseWhere .= " AND (tn.tur_adi = :work_type OR t.is_emri_tipi = :work_type)";
            $params['work_type'] = $workType;
        }
        if ($workResult) {
            // Hem yeni normalized hem de eski string alanından filtrele
            $baseWhere .= " AND (tn.is_emri_sonucu = :work_result OR t.is_emri_sonucu = :work_result)";
            $params['work_result'] = $workResult;
        }

        // Toplam kayıt sayısı (filtresiz)
        $totalQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id LEFT JOIN firmalar f ON t.firma_id = f.id WHERE $baseWhere");
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
                f.firma_adi LIKE :search OR
                COALESCE(tn.tur_adi, t.is_emri_tipi) LIKE :search OR
                t.ekip_kodu LIKE :search OR
                COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) LIKE :search OR
                p.adi_soyadi LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama
        $colSearchMap = [
            0 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
            1 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
            2 => 'p.adi_soyadi',
            3 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
            4 => 't.sonuclanmis',
            5 => 't.acik_olanlar'
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
        $filteredQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN personel p ON t.personel_id = p.id LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id LEFT JOIN firmalar f ON t.firma_id = f.id WHERE $baseWhere $searchWhere");
        foreach ($params as $key => $val) {
            $filteredQuery->bindValue(":$key", $val);
        }
        $filteredQuery->execute();
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama (Tarih başa, Firma kaldırıldı)
        $orderColumn = 't.tarih';
        $orderDir = 'DESC';
        $colMap = [
            0 => 't.tarih',
            1 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
            2 => 'p.adi_soyadi',
            3 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
            4 => 't.sonuclanmis',
            5 => 't.acik_olanlar'
        ];
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = strtoupper($request['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderColumn = $colMap[$orderColIdx];
            }
        }

        // Veri çekme - COALESCE ile eski ve yeni alanlardan fallback
        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi,
                    f.firma_adi,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN firmalar f ON t.firma_id = f.id
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

    public function getUnmatchedWorkResults($year, $month, $raporTuru)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;

        if ($raporTuru === 'all') {
            // Get results that are NOT in ANY report tab
            $sqlT = "SELECT is_emri_sonucu FROM tanimlamalar WHERE grup = 'is_turu' AND rapor_sekmesi IS NOT NULL AND rapor_sekmesi != '' AND silinme_tarihi IS NULL";
            $stmtT = $this->db->prepare($sqlT);
            $stmtT->execute();
            $matchedResults = $stmtT->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $sqlT = "SELECT is_emri_sonucu FROM tanimlamalar WHERE grup = 'is_turu' AND rapor_sekmesi = ? AND silinme_tarihi IS NULL";
            $stmtT = $this->db->prepare($sqlT);
            $stmtT->execute([$raporTuru]);
            $matchedResults = $stmtT->fetchAll(PDO::FETCH_COLUMN);

            if (empty($matchedResults) && $raporTuru === 'sokme_takma') {
                $stmtT->execute(['sokme']);
                $matchedResults = $stmtT->fetchAll(PDO::FETCH_COLUMN);
            }

            // If sökme fails, try kesme as fallback if it's the only one with ucret
            if (empty($matchedResults) && $raporTuru === 'kesme') {
                $sqlT = "SELECT is_emri_sonucu FROM tanimlamalar WHERE grup = 'is_turu' AND is_turu_ucret > 0 AND silinme_tarihi IS NULL";
                $stmtT = $this->db->prepare($sqlT);
                $stmtT->execute();
                $matchedResults = $stmtT->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        $params = [$firmaId, $year, $month];
        $notInClause = "";
        if (!empty($matchedResults)) {
            $placeholders = implode(',', array_fill(0, count($matchedResults), '?'));
            // COALESCE ile hem yeni hem eski alanı kontrol et
            $notInClause = " AND COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) NOT IN ($placeholders)";
            $params = array_merge($params, $matchedResults);
        }

        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi, 
                    ek.tur_adi as ekip_kodu,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu
                FROM yapilan_isler t
                LEFT JOIN personel p ON t.personel_id = p.id
                LEFT JOIN tanimlamalar ek ON p.ekip_no = ek.id
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? AND YEAR(t.tarih) = ? AND MONTH(t.tarih) = ? 
                $notInClause
                AND (t.is_emri_sonucu_id > 0 OR (t.is_emri_sonucu IS NOT NULL AND t.is_emri_sonucu != ''))
                ORDER BY t.tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getWorkTypeStats($year)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Hem tanımlamalardaki grup is_turu olanları hem de yapılan işlerdeki eşleşmeleri sayalım
        $sql = "SELECT 
                    MONTH(t.tarih) as ay,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as tur,
                    COUNT(*) as toplam
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE (tn.grup = 'is_turu' OR t.is_emri_tipi IS NOT NULL)
                    AND YEAR(t.tarih) = ? 
                    AND t.firma_id = ? 
                    AND t.silinme_tarihi IS NULL
                GROUP BY MONTH(t.tarih), COALESCE(tn.tur_adi, t.is_emri_tipi)
                ORDER BY MONTH(t.tarih) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
