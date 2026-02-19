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

    public function getWorkTypes($personelId = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Hem yeni normalized hem de eski string alanından unique değerleri al
        $sql = "
            SELECT DISTINCT COALESCE(tn.tur_adi, t.is_emri_tipi) as tur_adi
            FROM $this->table t 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
            WHERE t.firma_id = ? 
            AND t.silinme_tarihi IS NULL
            AND COALESCE(tn.tur_adi, t.is_emri_tipi) IS NOT NULL 
            AND COALESCE(tn.tur_adi, t.is_emri_tipi) != ''";
        $params = [$firmaId];

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getWorkResults($personelId = null, $workType = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        // Hem yeni normalized hem de eski string alanından unique değerleri al
        $sql = "
            SELECT DISTINCT COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu
            FROM $this->table t 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
            WHERE t.firma_id = ? 
            AND t.silinme_tarihi IS NULL
            AND COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) IS NOT NULL 
            AND COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) != ''";
        $params = [$firmaId];

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }

        if ($workType) {
            $sql .= " AND (tn.tur_adi = ? OR t.is_emri_tipi = ?)";
            $params[] = $workType;
            $params[] = $workType;
        }

        $sql .= " ORDER BY COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public function getSummaryByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT personel_id, ekip_kodu_id, tarih, SUM(sonuclanmis) as toplam 
                FROM $this->table 
                WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
                GROUP BY personel_id, ekip_kodu_id, tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->ekip_kodu_id][$row->tarih] = $row->toplam;
        }
        return $summary;
    }

    public function getSummaryDetailedByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.personel_id, t.ekip_kodu_id, t.tarih, 
                    TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) as is_emri_sonucu, 
                    SUM(t.sonuclanmis) as toplam 
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL
                GROUP BY t.personel_id, t.ekip_kodu_id, t.tarih, TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->ekip_kodu_id][$row->tarih][$row->is_emri_sonucu] = $row->toplam;
        }
        return $summary;
    }

    public function getKacakSummaryByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT ekip_adi, tarih, SUM(sayi) as toplam 
                FROM kacak_kontrol 
                WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
                GROUP BY ekip_adi, tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->ekip_adi][$row->tarih] = $row->toplam;
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
            if ($row->ekip_adi && $row->personel_ids) {
                if (!isset($mapping[$row->ekip_adi])) {
                    $mapping[$row->ekip_adi] = [];
                }
                $recordIds = explode(',', $row->personel_ids);
                foreach ($recordIds as $rid) {
                    $rid = trim($rid);
                    if ($rid)
                        $mapping[$row->ekip_adi][] = $rid;
                }
            }
        }

        foreach ($mapping as $key => $ids) {
            $mapping[$key] = implode(',', array_unique($ids));
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
                ek.tur_adi LIKE :search OR
                COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) LIKE :search OR
                p.adi_soyadi LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama (yeni sıralama: Tarih, Ekip Kodu, Personel, İş Emri Tipi, İş Emri Sonucu, Sonuçlanmış, Açık Olanlar)
        $colSearchMap = [
            0 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
            1 => 'ek.tur_adi',
            2 => 'p.adi_soyadi',
            3 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
            4 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
            5 => 't.sonuclanmis',
            6 => 't.acik_olanlar'
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

        // Filtrelenmiş kayıt sayısı (yapilan_isler.ekip_kodu_id üzerinden join)
        $filteredQuery = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} t 
            LEFT JOIN personel p ON t.personel_id = p.id 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
            LEFT JOIN firmalar f ON t.firma_id = f.id
            LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
            WHERE $baseWhere $searchWhere
        ");
        foreach ($params as $key => $val) {
            $filteredQuery->bindValue(":$key", $val);
        }
        $filteredQuery->execute();
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama (yeni sıralama: Tarih, Ekip Kodu, Personel, İş Emri Tipi, İş Emri Sonucu, Sonuçlanmış, Açık Olanlar)
        $orderColumn = 't.tarih';
        $orderDir = 'DESC';
        $colMap = [
            0 => 't.tarih',
            1 => 'ek.tur_adi',
            2 => 'p.adi_soyadi',
            3 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
            4 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
            5 => 't.sonuclanmis',
            6 => 't.acik_olanlar'
        ];
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = strtoupper($request['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderColumn = $colMap[$orderColIdx];
            }
        }

        // Veri çekme - COALESCE ile eski ve yeni alanlardan fallback
        // ekip_kodu_adi: yapilan_isler tablosundaki ekip_kodu_id üzerinden getiriliyor
        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi,
                    f.firma_adi,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu,
                    ek.tur_adi as ekip_kodu_adi
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN firmalar f ON t.firma_id = f.id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
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

    public function getUnmatchedWorkResults($startDate, $endDate, $raporTuru)
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

        $params = [$firmaId, $startDate, $endDate];
        $notInClause = "";
        if (!empty($matchedResults)) {
            $placeholders = implode(',', array_fill(0, count($matchedResults), '?'));
            // COALESCE ile hem yeni hem eski alanı kontrol et
            $notInClause = " AND TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) NOT IN ($placeholders)";
            $params = array_merge($params, $matchedResults);
        }

        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi, 
                    ek.tur_adi as ekip_kodu,
                    TRIM(COALESCE(tn.tur_adi, t.is_emri_tipi)) as is_emri_tipi,
                    TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) as is_emri_sonucu
                FROM yapilan_isler t
                LEFT JOIN personel p ON t.personel_id = p.id
                LEFT JOIN personel_ekip_gecmisi pg ON t.personel_id = pg.personel_id 
                    AND pg.baslangic_tarihi <= t.tarih 
                    AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= t.tarih)
                LEFT JOIN tanimlamalar ek ON pg.ekip_kodu_id = ek.id
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? 
                AND t.silinme_tarihi IS NULL
                $notInClause
                AND (t.is_emri_sonucu_id > 0 OR (t.is_emri_sonucu IS NOT NULL AND t.is_emri_sonucu != ''))
                ORDER BY t.tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getWorkTypeStats($year, $month = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT 
                    MONTH(t.tarih) as ay,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as tur,
                    COUNT(*) as toplam
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE (tn.grup = 'is_turu' OR t.is_emri_tipi IS NOT NULL)
                    AND YEAR(t.tarih) = ? 
                    AND t.firma_id = ? 
                    AND t.silinme_tarihi IS NULL";

        $params = [$year, $firmaId];
        if ($month) {
            $sql .= " AND MONTH(t.tarih) = ?";
            $params[] = $month;
        }

        $sql .= " GROUP BY MONTH(t.tarih), COALESCE(tn.tur_adi, t.is_emri_tipi)
                ORDER BY MONTH(t.tarih) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getWorkResultStats($year, $month = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT 
                    MONTH(t.tarih) as ay,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as sonuc,
                    COUNT(*) as toplam
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE (tn.grup = 'is_turu' OR t.is_emri_sonucu IS NOT NULL)
                    AND YEAR(t.tarih) = ? 
                    AND t.firma_id = ? 
                    AND t.silinme_tarihi IS NULL";

        $params = [$year, $firmaId];
        if ($month) {
            $sql .= " AND MONTH(t.tarih) = ?";
            $params[] = $month;
        }

        $sql .= " GROUP BY MONTH(t.tarih), COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)
                ORDER BY MONTH(t.tarih) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getDailyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $bugun = date('Y-m-d');

        $sql = "SELECT 
                    SUM(CASE WHEN tn.rapor_sekmesi = 'kesme' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as kesme_acma,
                    SUM(CASE WHEN tn.rapor_sekmesi = 'sokme_takma' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as sayac_degisimi,
                    SUM(CASE WHEN tn.rapor_sekmesi = 'muhurleme' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as muhurleme
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? 
                AND t.tarih = ? 
                AND t.silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $bugun]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getMonthlyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $buAy = date('Y-m-01');
        $sonGun = date('Y-m-t');

        $sql = "SELECT 
                    SUM(CASE WHEN tn.rapor_sekmesi = 'kesme' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as kesme_acma,
                    SUM(CASE WHEN tn.rapor_sekmesi = 'sokme_takma' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as sayac_degisimi,
                    SUM(CASE WHEN tn.rapor_sekmesi = 'muhurleme' AND tn.is_turu_ucret > 0 THEN t.sonuclanmis ELSE 0 END) as muhurleme
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? 
                AND t.tarih >= ? AND t.tarih <= ?
                AND t.silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $buAy, $sonGun]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    public function getKacakDailyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $dun = date('Y-m-d', strtotime('-1 day'));

        $sql = "SELECT SUM(sayi) as toplam 
                FROM kacak_kontrol 
                WHERE firma_id = ? 
                AND tarih = ? 
                AND silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $dun]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getKacakMonthlyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $buAy = date('Y-m-01');
        $sonGun = date('Y-m-t');

        $sql = "SELECT SUM(sayi) as toplam 
                FROM kacak_kontrol 
                WHERE firma_id = ? 
                AND tarih >= ? AND tarih <= ?
                AND silinme_tarihi IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $buAy, $sonGun]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Karşılaştırma raporu için çoklu dönem verileri (Kesme/Açma, Sökme Takma, Mühürleme)
     * @param array $periods [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'label' => 'Ocak 2026'], ...]
     * @param string $raporTuru 'kesme', 'sokme_takma', 'muhurleme'
     * @return array ['personel' => [...], 'bolge' => [...], 'firma' => [...]]
     */
    public function getComparisonByPeriods(array $periods, string $raporTuru = ''): array
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $result = ['personel' => [], 'bolge' => [], 'firma' => []];

        // İlgili iş türlerini bul
        $workTypeFilter = [];
        if (!empty($raporTuru)) {
            $sqlWT = "SELECT TRIM(is_emri_sonucu) as is_emri_sonucu FROM tanimlamalar WHERE grup = 'is_turu' AND rapor_sekmesi = ? AND silinme_tarihi IS NULL";
            $stmtWT = $this->db->prepare($sqlWT);
            $stmtWT->execute([$raporTuru]);
            $workTypeFilter = $stmtWT->fetchAll(PDO::FETCH_COLUMN);

            if (empty($workTypeFilter) && $raporTuru === 'sokme_takma') {
                $stmtWT->execute(['sokme']);
                $workTypeFilter = $stmtWT->fetchAll(PDO::FETCH_COLUMN);
            }
            if (empty($workTypeFilter) && $raporTuru === 'kesme') {
                $sqlWT2 = "SELECT TRIM(is_emri_sonucu) as is_emri_sonucu FROM tanimlamalar WHERE grup = 'is_turu' AND is_turu_ucret > 0 AND silinme_tarihi IS NULL";
                $stmtWT2 = $this->db->prepare($sqlWT2);
                $stmtWT2->execute();
                $workTypeFilter = $stmtWT2->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        foreach ($periods as $idx => $period) {
            $params = [$firmaId, $period['start'], $period['end']];
            $wtClause = '';
            if (!empty($workTypeFilter)) {
                $placeholders = implode(',', array_fill(0, count($workTypeFilter), '?'));
                $wtClause = " AND TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) IN ($placeholders)";
                $params = array_merge($params, $workTypeFilter);
            }

            $sql = "SELECT t.personel_id, t.ekip_kodu_id,
                        p.adi_soyadi as personel_adi,
                        def.tur_adi as ekip_adi,
                        def.ekip_bolge as bolge,
                        SUM(t.sonuclanmis) as toplam,
                        COUNT(DISTINCT t.tarih) as gun_sayisi
                    FROM {$this->table} t
                    LEFT JOIN personel p ON t.personel_id = p.id
                    LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                    LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                    WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL
                    $wtClause
                    GROUP BY t.personel_id, t.ekip_kodu_id, p.adi_soyadi, def.tur_adi, def.ekip_bolge";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $periodLabel = $period['label'];
            $periodTotal = 0;
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
                $bolgeName = $row->bolge ?: 'TANIMSIZ';
                if (!isset($result['bolge'][$bolgeName])) {
                    $result['bolge'][$bolgeName] = ['periods' => []];
                }
                if (!isset($result['bolge'][$bolgeName]['periods'][$periodLabel])) {
                    $result['bolge'][$bolgeName]['periods'][$periodLabel] = ['toplam' => 0, 'personel_sayisi' => 0];
                }
                $result['bolge'][$bolgeName]['periods'][$periodLabel]['toplam'] += (int) $row->toplam;
                $result['bolge'][$bolgeName]['periods'][$periodLabel]['personel_sayisi']++;

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

    /**
     * Kaçak kontrol karşılaştırma raporu (ekip bazlı)
     */
    public function getKacakComparisonByPeriods(array $periods): array
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $result = ['personel' => [], 'bolge' => [], 'firma' => []];

        foreach ($periods as $period) {
            $sql = "SELECT k.ekip_adi, SUM(k.sayi) as toplam, COUNT(DISTINCT k.tarih) as gun_sayisi
                    FROM kacak_kontrol k
                    WHERE k.firma_id = ? AND k.tarih BETWEEN ? AND ? AND k.silinme_tarihi IS NULL
                    GROUP BY k.ekip_adi";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$firmaId, $period['start'], $period['end']]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $periodLabel = $period['label'];
            $periodTotal = 0;

            foreach ($rows as $row) {
                $teamName = $row->ekip_adi ?: 'TANIMSIZ';

                if (!isset($result['personel'][$teamName])) {
                    $result['personel'][$teamName] = [
                        'personel_adi' => $teamName,
                        'ekip_adi' => $teamName,
                        'bolge' => '-',
                        'periods' => []
                    ];
                }
                $result['personel'][$teamName]['periods'][$periodLabel] = [
                    'toplam' => (int) $row->toplam,
                    'gun_sayisi' => (int) $row->gun_sayisi
                ];

                $periodTotal += (int) $row->toplam;
            }

            $result['firma'][$periodLabel] = [
                'toplam' => $periodTotal,
                'personel_sayisi' => count($rows)
            ];
        }

        return $result;
    }
}
