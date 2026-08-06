<?php

namespace App\Model;

use App\Model\Model;
use PDO;
use App\Helper\Date;

class PuantajModel extends Model
{
    /**
     * İŞ TAKİP RAPOR SEKME KURALLARI
     *
     * Okuma, Sayaç Sökme Takma ve Kaçak Kontrol kendi tablolarını kullanır.
     * Kesme/Açma ile Mühürleme ise yapilan_isler tablosunu paylaşır. Bu metot,
     * ortak tablodaki kayıtların iki farklı rapor sekmesine birden düşmesini önleyen
     * tek yetkili filtre noktasıdır. Sekme sorgularına ayrıca dağınık koşul eklemeyin.
     */
    private function getReportTabCondition(string $reportType): string
    {
        switch ($reportType) {
            case 'ENDEKS_OKUMA':
                return " AND COALESCE(t.is_emri_tipi, '') = 'Endeks Okuma'";

            case 'SAYAC_DEGISIM':
                return " AND tn.rapor_sekmesi = 'sokme_takma'";

            case 'MUHURLEME':
                return " AND tn.rapor_sekmesi = 'muhurleme'";

            case 'KESME_ACMA':
                return " AND tn.rapor_sekmesi = 'kesme'";

            case 'ESLESMEYEN':
                // Rapor sekmesi tanımlanmayan kayıtlar hiçbir operasyon raporuna
                // dahil edilmez; yönetici incelemesi için yalnızca bu alanda görünür.
                return " AND (tn.id IS NULL OR COALESCE(tn.rapor_sekmesi, '') IN ('', '0'))
                        AND COALESCE(t.is_emri_tipi, '') != 'Manuel Düşüm'";
        }

        return '';
    }

    public function __construct($tableName = 'yapilan_isler')
    {
        $this->table = $tableName;
        parent::__construct($this->table);
    }

    public function getFiltered($startDate = null, $endDate = null, $ekipKodu = null, $workType = null, $workResult = null, $raporSekmesi = null, $limit = null, $offset = null, $onlyCount = false, $region = '', $defter = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        
        if ($onlyCount) {
            $select = "COUNT(*) as total";
        } else {
            $select = "t.*, 
                    p.adi_soyadi as personel_adi,
                    f.firma_adi as firma,
                    COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                    COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu,
                    tn.is_turu_ucret,
                    tn.rapor_sekmesi";
        }

        $sql = "SELECT $select
                FROM $this->table t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN firmalar f ON f.id = t.firma_id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";
        $params = [$firmaId];

        if ($region) {
            $sql .= " AND (t.bolge = ? OR ek.ekip_bolge = ?)";
            $params[] = $region;
            $params[] = $region;
        }

        if ($defter) {
            $sql .= " AND (t.defter = ? OR tn.defter_kodlari = ?)"; // Some records store defter in tn or t
            $params[] = $defter;
            $params[] = $defter;
        }

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

        if ($raporSekmesi) {
            $sql .= " AND tn.rapor_sekmesi = ?";
            $params[] = $raporSekmesi;
        }

        if ($workResult === 'sonuclanan') {
            $sql .= " AND (t.sonuclanmis > 0)";
        } elseif ($workResult === 'acik') {
            $sql .= " AND (t.acik_olanlar > 0)";
        } elseif ($workResult) {
            $sql .= " AND (tn.is_emri_sonucu = ? OR t.is_emri_sonucu = ?)";
            $params[] = $workResult;
            $params[] = $workResult;
        } else {
            // Hiçbir filtre yoksa, en azından bir değeri olanları getir
            $sql .= " AND (t.sonuclanmis > 0 OR t.acik_olanlar > 0)";
        }

        if ($onlyCount) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }

        $sql .= " ORDER BY t.tarih DESC";

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private static $cachedWorkTypes = [];
    private static $cachedWorkResults = [];

    public function getWorkTypes($personelId = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $cacheKey = $firmaId . '_' . ($personelId ?? 'all');
        if (isset(self::$cachedWorkTypes[$cacheKey])) {
            return self::$cachedWorkTypes[$cacheKey];
        }

        $params = [$firmaId];

        $sql = "SELECT DISTINCT TRIM(COALESCE(NULLIF(tn.tur_adi, ''), NULLIF(t.is_emri_tipi, ''))) as tur_adi
                FROM $this->table t 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";
        
        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }
        
        $sql .= " HAVING tur_adi IS NOT NULL AND tur_adi != '' ORDER BY tur_adi ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        self::$cachedWorkTypes[$cacheKey] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return self::$cachedWorkTypes[$cacheKey];
    }

    public function getWorkResults($personelId = null, $workType = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $cacheKey = $firmaId . '_' . ($personelId ?? 'all') . '_' . ($workType ?? 'all');
        if (isset(self::$cachedWorkResults[$cacheKey])) {
            return self::$cachedWorkResults[$cacheKey];
        }

        $params = [$firmaId];
        
        $sql = "SELECT DISTINCT TRIM(COALESCE(NULLIF(tn.is_emri_sonucu, ''), NULLIF(t.is_emri_sonucu, ''))) as is_emri_sonucu
                FROM $this->table t 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL";

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }

        if ($workType) {
            $sql .= " AND (TRIM(tn.tur_adi) = ? OR TRIM(t.is_emri_tipi) = ?)";
            $params[] = trim($workType);
            $params[] = trim($workType);
        }

        $sql .= " HAVING is_emri_sonucu IS NOT NULL AND is_emri_sonucu != '' ORDER BY is_emri_sonucu ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        self::$cachedWorkResults[$cacheKey] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return self::$cachedWorkResults[$cacheKey];
    }

    /**
     * İş Takip DataTable filtre seçeneklerini aktif rapor sekmesiyle aynı kapsamda getirir.
     * Liste sorgusu ile filtre seçenekleri mutlaka aynı getReportTabCondition() kuralını
     * kullanmalıdır; aksi halde başka sekmelere ait seçenekler açılır listede görünür.
     */
    public function getReportFilterValues(
        string $column,
        string $reportType,
        ?string $startDate = null,
        ?string $endDate = null,
        $personelId = null,
        string $region = ''
    ): array {
        $expressions = [
            'is_emri_tipi' => "TRIM(COALESCE(NULLIF(tn.tur_adi, ''), NULLIF(t.is_emri_tipi, '')))",
            'is_emri_sonucu' => "TRIM(COALESCE(NULLIF(tn.is_emri_sonucu, ''), NULLIF(t.is_emri_sonucu, '')))"
        ];

        if (!isset($expressions[$column])) {
            return [];
        }

        $firmaId = $_SESSION['firma_id'] ?? 0;
        $expression = $expressions[$column];
        $sql = "SELECT DISTINCT $expression AS value
                FROM {$this->table} t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE t.firma_id = ?
                AND t.silinme_tarihi IS NULL";
        $params = [$firmaId];

        $sql .= $this->getReportTabCondition($reportType);

        if ($startDate) {
            $sql .= " AND t.tarih >= ?";
            $params[] = Date::Ymd($startDate) ?: $startDate;
        }
        if ($endDate) {
            $sql .= " AND t.tarih < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = Date::Ymd($endDate) ?: $endDate;
        }
        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }
        if ($region !== '') {
            $sql .= " AND ek.ekip_bolge = ?";
            $params[] = $region;
        }

        $sql .= " HAVING value IS NOT NULL AND value != '' ORDER BY value ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public function getSummaryByRange($startDate, $endDate, $personelId = '', $region = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.personel_id, t.ekip_kodu_id, t.ekip_kodu, t.tarih, SUM(t.sonuclanmis) as toplam 
                FROM $this->table t
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL";
        $params = [$firmaId, $startDate, $endDate];

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }
        if ($region) {
            $sql .= " AND ek.ekip_bolge = ?";
            $params[] = $region;
        }

        $sql .= " GROUP BY t.personel_id, t.ekip_kodu_id, t.ekip_kodu, t.tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $key = $row->ekip_kodu_id . '|' . $row->ekip_kodu;
            $summary[$row->personel_id][$key][$row->tarih] = $row->toplam;
        }
        return $summary;
    }

    public function getSummaryDetailedByRange($startDate, $endDate, $personelId = '', $region = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.personel_id, t.ekip_kodu_id, t.ekip_kodu, t.tarih, 
                    TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) as is_emri_sonucu, 
                    SUM(t.sonuclanmis) as toplam 
                FROM $this->table t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL";
        $params = [$firmaId, $startDate, $endDate];

        if ($personelId) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $personelId;
        }
        if ($region) {
            $sql .= " AND ek.ekip_bolge = ?";
            $params[] = $region;
        }

        $sql .= " GROUP BY t.personel_id, t.ekip_kodu_id, t.ekip_kodu, t.tarih, TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $key = $row->ekip_kodu_id . '|' . $row->ekip_kodu;
            $summary[$row->personel_id][$key][$row->tarih][$row->is_emri_sonucu] = $row->toplam;
        }
        return $summary;
    }

    public function getKacakSummaryByRange($startDate, $endDate, $region = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT k.ekip_adi, k.tarih, SUM(k.sayi) as toplam 
                FROM kacak_kontrol k
                LEFT JOIN tanimlamalar ek ON k.ekip_adi = ek.tur_adi AND ek.grup = 'ekip_kodu' AND ek.firma_id = k.firma_id
                WHERE k.firma_id = ? AND k.tarih BETWEEN ? AND ? AND k.silinme_tarihi IS NULL
                AND (k.aciklama != 'Manuel Düşüm' OR k.aciklama IS NULL)
                AND " . \App\Model\KacakKontrolModel::hakedisKosulu('k');
        $params = [$firmaId, $startDate, $endDate];

        if ($region) {
            $sql .= " AND (k.ilce = ? OR ek.ekip_bolge = ?)";
            $params[] = $region;
            $params[] = $region;
        }

        $sql .= " GROUP BY k.ekip_adi, k.tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $normEkip = \App\Model\KacakKontrolModel::normalizeEkipAdi($row->ekip_adi);
            if (!isset($summary[$normEkip][$row->tarih])) {
                $summary[$normEkip][$row->tarih] = 0;
            }
            $summary[$normEkip][$row->tarih] += (int) $row->toplam;
        }
        return $summary;
    }

    public function getKacakTeams()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $stmt = $this->db->prepare("SELECT DISTINCT ekip_adi FROM kacak_kontrol WHERE firma_id = ? AND ekip_adi IS NOT NULL AND ekip_adi != '' AND silinme_tarihi IS NULL ORDER BY ekip_adi ASC");
        $stmt->execute([$firmaId]);
        $rawTeams = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $normalizedTeams = [];
        foreach ($rawTeams as $team) {
            $norm = \App\Model\KacakKontrolModel::normalizeEkipAdi($team);
            if (!empty($norm) && !in_array($norm, $normalizedTeams, true)) {
                $normalizedTeams[] = $norm;
            }
        }
        sort($normalizedTeams, SORT_STRING | SORT_FLAG_CASE);
        return $normalizedTeams;
    }

    /**
     * Harici entegrasyon API'sinden gelen bir kaçak kontrol tutanağını kaydeder.
     * tutanak_no + tarih üzerinden aynı kaydın tekrar gönderilmesini engeller (idempotent).
     */
    public function insertKacakKontrolExternal(array $data): array
    {
        $firmaId = (int) $data['firma_id'];
        $tarih = $data['tarih'];
        $tutanakNo = trim((string) $data['tutanak_no']);

        $islemId = md5('harici_api|' . $firmaId . '|' . $tarih . '|' . $tutanakNo);

        $stmtVarMi = $this->db->prepare("SELECT id FROM kacak_kontrol WHERE islem_id = ? AND silinme_tarihi IS NULL LIMIT 1");
        $stmtVarMi->execute([$islemId]);
        $mevcutId = $stmtVarMi->fetchColumn();
        if ($mevcutId) {
            return ['success' => false, 'duplicate' => true, 'id' => (int) $mevcutId];
        }

        $stmt = $this->db->prepare("INSERT INTO kacak_kontrol
            (firma_id, personel_ids, tarih, ekip_adi, ilce, tur, tutanak_no, abone_adi, sayac_no, endeks, sayi, aciklama, islem_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                $firmaId,
                $data['personel_ids'],
                $tarih,
                $data['ekip_adi'],
                $data['ilce'],
                $data['tur'],
                $tutanakNo,
                $data['abone_adi'],
                $data['sayac_no'],
                $data['endeks'],
                $data['sayi'],
                $data['aciklama'],
                $islemId
            ]);
        } catch (\PDOException $e) {
            // islem_id üzerinde UNIQUE KEY var; yarış durumunda (aynı anda iki istek) burada yakalanır.
            if ($e->getCode() === '23000') {
                return ['success' => false, 'duplicate' => true, 'id' => 0];
            }
            throw $e;
        }

        return ['success' => true, 'duplicate' => false, 'id' => (int) $this->db->lastInsertId()];
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
    public function getDataTable($request, $startDate, $endDate, $ekipKodu = '', $workType = '', $workResult = '', $sorguTuru = '', $region = '', $defter = '')
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
            $baseWhere .= " AND t.tarih < DATE_ADD(:end_date, INTERVAL 1 DAY)";
            $params['end_date'] = Date::Ymd($endDate) ?: $endDate;
        }
        if ($ekipKodu) {
            $baseWhere .= " AND t.personel_id = :ekip_kodu";
            $params['ekip_kodu'] = $ekipKodu;
        }
        if ($workType) {
            $baseWhere .= " AND (TRIM(tn.tur_adi) = :work_type OR TRIM(t.is_emri_tipi) = :work_type)";
            $params['work_type'] = trim($workType);
        }
        if ($workResult === 'sonuclanan') {
            $baseWhere .= " AND (t.sonuclanmis > 0)";
        } elseif ($workResult === 'acik') {
            $baseWhere .= " AND (t.acik_olanlar > 0)";
        } elseif ($workResult) {
            $baseWhere .= " AND (TRIM(tn.is_emri_sonucu) = :work_result OR TRIM(t.is_emri_sonucu) = :work_result)";
            $params['work_result'] = trim($workResult);
        }

        $baseWhere .= $this->getReportTabCondition($sorguTuru);

        if ($region) {
            $baseWhere .= " AND ek.ekip_bolge = :region";
            $params['region'] = $region;
        }

        if ($defter && $sorguTuru === 'ENDEKS_OKUMA') {
             // Puantaj endeks okuma tab verisi defter bilgisi içerebilir
             $baseWhere .= " AND t.ekip_kodu LIKE :defter_search";
             $params['defter_search'] = "%$defter%";
        }

        // Toplam kayıt sayısı (filtresiz)
        $totalQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} t LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id LEFT JOIN firmalar f ON t.firma_id = f.id LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id WHERE $baseWhere");
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
                (CASE WHEN tn.is_turu_ucret > 0 THEN 'Ücretli' ELSE 'Ücretsiz' END) LIKE :search OR
                t.abone_no LIKE :search OR
                t.is_emri_no LIKE :search OR
                p.adi_soyadi LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama (yeni sıralama: [0:Checkbox], 1:Tarih, 2:Ekip Kodu, 3:Personel, 4:İş Emri Tipi, 5:İş Emri Sonucu, 6:Abone No/Ücret Durumu, 7:İş Emri No/Sonuçlanmış, 8:Açık Olanlar)
        if (in_array($sorguTuru, ['KESME_ACMA', 'ESLESMEYEN'], true)) {
            $colSearchMap = [
                1 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
                2 => 'ek.tur_adi',
                3 => 'p.adi_soyadi',
                4 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
                5 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
                6 => 't.abone_no',
                7 => 't.is_emri_no',
                8 => 't.sonuclanmis',
                9 => 't.acik_olanlar'
            ];
        } else {
            $colSearchMap = [
                1 => 'DATE_FORMAT(t.tarih, "%d.%m.%Y")',
                2 => 'ek.tur_adi',
                3 => 'p.adi_soyadi',
                4 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
                5 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
                6 => 'CASE WHEN tn.is_turu_ucret > 0 THEN \'Ücretli\' ELSE \'Ücretsiz\' END',
                7 => 't.sonuclanmis',
                8 => 't.acik_olanlar'
            ];
        }


        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $colIdx => $col) {
                if (!empty($col['search']['value']) && isset($colSearchMap[$colIdx])) {
                    $field = $colSearchMap[$colIdx];
                    $searchValue = $col['search']['value'];
                    $paramName = "col_search_" . $colIdx;

                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {
                            // Tarih sütunu dönüşümü
                            if ($colIdx == 0) {
                                if ($val && strpos($val, '.') !== false) $val = \App\Helper\Date::Ymd($val, 'Y-m-d');
                                if ($val2 && strpos($val2, '.') !== false) $val2 = \App\Helper\Date::Ymd($val2, 'Y-m-d');
                                // Eğer formatlıysa, orijinal alanı kullan (örn t.tarih)
                                if (strpos($field, 'DATE_FORMAT') !== false) {
                                    if (preg_match('/DATE_FORMAT\(([^,]+),/', $field, $m)) {
                                        $field = trim($m[1]);
                                    }
                                }
                            }

                            $dateCompareField = ($colIdx == 0) ? "DATE($field)" : $field;

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramName . "_" . $vIdx;
                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '')";
                                            } else {
                                                if ($colIdx == 0 && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::Ymd($v, 'Y-m-d');
                                                    $orConditions[] = "$dateCompareField = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        if (!empty($orConditions)) {
                                            $searchWhere .= " AND (" . implode(" OR ", $orConditions) . ")";
                                        }
                                    }
                                    break;
                                case 'contains':
                                    $searchWhere .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $searchWhere .= " AND $field NOT LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $searchWhere .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "$val%";
                                    break;
                                case 'ends_with':
                                    $searchWhere .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val";
                                    break;
                                case 'equals':
                                    $searchWhere .= " AND $dateCompareField = :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'not_equals':
                                    $searchWhere .= " AND $dateCompareField != :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'gt': case 'greater_than':
                                    $searchWhere .= " AND $field > :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'lt': case 'less_than':
                                    $searchWhere .= " AND $field < :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'gte': case 'greater_equal':
                                    $searchWhere .= " AND $field >= :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'lte': case 'less_equal':
                                    $searchWhere .= " AND $field <= :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'before':
                                    $searchWhere .= " AND $dateCompareField < :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'after':
                                    $searchWhere .= " AND $dateCompareField > :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramName . "_1";
                                        $p2 = $paramName . "_2";
                                        $searchWhere .= " AND $dateCompareField BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val;
                                        $params[$p2] = $val2;
                                    }
                                    break;
                                case 'null':
                                    $searchWhere .= " AND ($field IS NULL OR $field = '')";
                                    break;
                                case 'not_null':
                                    $searchWhere .= " AND $field IS NOT NULL AND $field != ''";
                                    break;
                            }
                        }
                    } else {
                        // Normal arama fallback
                        $searchVal = "%" . $searchValue . "%";
                        $searchWhere .= " AND $field LIKE :$paramName";
                        $params[$paramName] = $searchVal;
                    }
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

        // Sıralama
        $orderColumn = 't.tarih';
        $orderDir = 'DESC';
        if ($sorguTuru === 'KESME_ACMA') {
            $colMap = [
                1 => 't.tarih',
                2 => 'ek.tur_adi',
                3 => 'p.adi_soyadi',
                4 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
                5 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
                6 => 't.abone_no',
                7 => 't.is_emri_no',
                8 => 't.sonuclanmis',
                9 => 't.acik_olanlar'
            ];
        } else {
            $colMap = [
                1 => 't.tarih',
                2 => 'ek.tur_adi',
                3 => 'p.adi_soyadi',
                4 => 'COALESCE(tn.tur_adi, t.is_emri_tipi)',
                5 => 'COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)',
                6 => 'tn.is_turu_ucret',
                7 => 't.sonuclanmis',
                8 => 't.acik_olanlar'
            ];
        }
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
                    TRIM(COALESCE(NULLIF(tn.tur_adi, ''), NULLIF(t.is_emri_tipi, ''))) as is_emri_tipi,
                    TRIM(COALESCE(NULLIF(tn.is_emri_sonucu, ''), NULLIF(t.is_emri_sonucu, ''))) as is_emri_sonucu,
                    ek.tur_adi as ekip_kodu_adi,
                    tn.is_turu_ucret as ucret
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                LEFT JOIN firmalar f ON t.firma_id = f.id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE $baseWhere $searchWhere
                ORDER BY $orderColumn $orderDir";

        if (isset($request['length']) && (int)$request['length'] !== -1) {
            $sql .= " LIMIT :start, :length";
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(":$key", $val);
        }

        if (isset($request['length']) && (int)$request['length'] !== -1) {
            $stmt->bindValue(':start', (int) ($request['start'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':length', (int) ($request['length'] ?? 10), PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data,
            "summary" => $this->getSummaryByFilters($baseWhere, $searchWhere, $params)
        ];
    }

    /**
     * Filtrelere göre özet toplamları getirir
     */
    public function getSummaryByFilters($baseWhere, $searchWhere, $params)
    {
        $sql = "SELECT TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) as sonuc,
                       COUNT(*) as adet,
                       SUM(t.sonuclanmis) as toplam_abone,
                       MAX(CASE WHEN tn.is_turu_ucret > 0 THEN 1 ELSE 0 END) as is_olumlu
                FROM {$this->table} t
                LEFT JOIN personel p ON t.personel_id = p.id 
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
                LEFT JOIN firmalar f ON t.firma_id = f.id
                LEFT JOIN tanimlamalar ek ON t.ekip_kodu_id = ek.id
                WHERE $baseWhere $searchWhere
                GROUP BY sonuc
                ORDER BY adet DESC";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            if ($key === 'start' || $key === 'length') continue;
            $stmt->bindValue(":$key", $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
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
                    SUM(t.sonuclanmis) as toplam
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
                    TRIM(COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu)) as sonuc,
                    SUM(t.sonuclanmis) as toplam
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
        $bugun = date('Y-m-d');

        $sql = "SELECT 
                    COALESCE(SUM(sayi), 0) as toplam,
                    COALESCE(SUM(CASE WHEN onay_durumu = 'onaylandi' THEN sayi ELSE 0 END), 0) as onaylanan,
                    COALESCE(SUM(CASE WHEN onay_durumu = 'beklemede' THEN sayi ELSE 0 END), 0) as bekleyen
                FROM kacak_kontrol 
                WHERE firma_id = ? 
                AND tarih = ? 
                AND silinme_tarihi IS NULL
                AND durum != 'iptal'
                AND (aciklama != 'Manuel Düşüm' OR aciklama IS NULL)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $bugun]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getKacakMonthlyStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $buAy = date('Y-m-01');
        $sonGun = date('Y-m-t');

        $sql = "SELECT 
                    COALESCE(SUM(sayi), 0) as toplam,
                    COALESCE(SUM(CASE WHEN onay_durumu = 'onaylandi' THEN sayi ELSE 0 END), 0) as onaylanan,
                    COALESCE(SUM(CASE WHEN onay_durumu = 'beklemede' THEN sayi ELSE 0 END), 0) as bekleyen
                FROM kacak_kontrol 
                WHERE firma_id = ? 
                AND tarih >= ? AND tarih <= ?
                AND silinme_tarihi IS NULL
                AND durum != 'iptal'
                AND (aciklama != 'Manuel Düşüm' OR aciklama IS NULL)";

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
    public function getComparisonByPeriods(array $periods, string $raporTuru = '', string $region = ''): array
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
                    $wtClause";

            if ($region) {
                $sql .= " AND (t.bolge = ? OR def.ekip_bolge = ?)";
                $params[] = $region;
                $params[] = $region;
            }

            $sql .= " GROUP BY t.personel_id, t.ekip_kodu_id, p.adi_soyadi, def.tur_adi, def.ekip_bolge";

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
    public function getKacakComparisonByPeriods(array $periods, $region = ''): array
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $result = ['personel' => [], 'bolge' => [], 'firma' => []];

        foreach ($periods as $period) {
            $sql = "SELECT k.ekip_adi, SUM(k.sayi) as toplam, COUNT(DISTINCT k.tarih) as gun_sayisi
                    FROM kacak_kontrol k
                    LEFT JOIN tanimlamalar ek ON k.ekip_adi = ek.tur_adi AND ek.grup = 'ekip_kodu' AND ek.firma_id = k.firma_id
                    WHERE k.firma_id = ? AND k.tarih BETWEEN ? AND ? AND k.silinme_tarihi IS NULL
                    AND (k.aciklama != 'Manuel Düşüm' OR k.aciklama IS NULL)
                AND " . \App\Model\KacakKontrolModel::hakedisKosulu('k');
            $params = [$firmaId, $period['start'], $period['end']];

            if ($region) {
                $sql .= " AND ek.ekip_bolge = ?";
                $params[] = $region;
            }

            $sql .= " GROUP BY k.ekip_adi";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
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
