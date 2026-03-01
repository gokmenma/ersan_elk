<?php

namespace App\Model;

use App\Model\Model;
use PDO;
use App\Helper\Date;

class SayacDegisimModel extends Model
{
    protected $table = 'sayac_degisim';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Server-side DataTable için sayaç değişim verilerini çeker
     */
    public function getDataTable($request, $startDate, $endDate, $ekipKodu = '')
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
                t.ekip LIKE :search OR
                t.memur LIKE :search OR
                p.adi_soyadi LIKE :search OR
                t.bolge LIKE :search OR
                t.isemri_sebep LIKE :search OR
                t.isemri_sonucu LIKE :search OR
                t.isemri_no LIKE :search OR
                t.abone_no LIKE :search OR
                t.takilan_sayacno LIKE :search OR
                t.sonuc_aciklama LIKE :search OR
                DATE_FORMAT(t.tarih, '%d.%m.%Y') LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun bazlı arama
        $colSearchMap = [
            0 => 'DATE_FORMAT(t.kayit_tarihi, "%d.%m.%Y %H:%i")',
            1 => 't.ekip',
            2 => 'p.adi_soyadi',
            3 => 't.bolge',
            4 => 't.isemri_sebep',
            5 => 't.isemri_sonucu',
            6 => 't.abone_no',
            7 => 't.takilan_sayacno'
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
        $filteredQuery = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} t 
            LEFT JOIN personel p ON t.personel_id = p.id 
            WHERE $baseWhere $searchWhere
        ");
        foreach ($params as $key => $val) {
            $filteredQuery->bindValue(":$key", $val);
        }
        $filteredQuery->execute();
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama
        $orderColumn = 't.kayit_tarihi';
        $orderDir = 'DESC';
        $colMap = [
            0 => 't.kayit_tarihi',
            1 => 't.ekip',
            2 => 'p.adi_soyadi',
            3 => 't.bolge',
            4 => 't.isemri_sebep',
            5 => 't.isemri_sonucu',
            6 => 't.abone_no',
            7 => 't.takilan_sayacno'
        ];
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = strtoupper($request['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderColumn = $colMap[$orderColIdx];
            }
        }

        // Veri çekme
        $sql = "SELECT t.*, 
                    p.adi_soyadi as personel_adi,
                    ek.tur_adi as ekip_kodu_adi
                FROM {$this->table} t 
                LEFT JOIN personel p ON t.personel_id = p.id 
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

    /**
     * Tarih aralığındaki sayaç değişim sayısını çeker (özet)
     */
    public function getSummaryByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT personel_id, ekip_kodu_id, tarih, COUNT(*) as toplam 
                FROM {$this->table} 
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

    /**
     * Tarih aralığındaki sayaç değişim sayısını çeker (detaylı, isemri_sonucu'na göre gruplu)
     */
    public function getSummaryDetailedByRange($startDate, $endDate)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT personel_id, ekip_kodu_id, tarih, isemri_sonucu as is_emri_sonucu, COUNT(*) as toplam 
                FROM {$this->table} 
                WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
                GROUP BY personel_id, ekip_kodu_id, tarih, isemri_sonucu";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $summary = [];
        foreach ($results as $row) {
            $summary[$row->personel_id][$row->ekip_kodu_id][$row->tarih][$row->is_emri_sonucu] = $row->toplam;
        }
        return $summary;
    }

    /**
     * Veritabanında kayıtlı benzersiz iş emri sonuçlarını getirir (Rapor kolon başlıkları için)
     */
    public function getDistinctWorkTypes()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT t.isemri_sonucu, MAX(def.id) as def_id 
                FROM {$this->table} t
                JOIN tanimlamalar def ON TRIM(t.isemri_sonucu) = TRIM(def.is_emri_sonucu)
                WHERE t.firma_id = ? 
                AND t.silinme_tarihi IS NULL 
                AND t.isemri_sonucu IS NOT NULL 
                AND t.isemri_sonucu != ''
                AND def.grup = 'is_turu'
                AND def.is_turu_ucret > 0
                AND def.silinme_tarihi IS NULL
                GROUP BY TRIM(t.isemri_sonucu)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId]);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $workTypes = [];
        foreach ($results as $row) {
            $workTypes[] = (object) [
                'id' => $row->def_id,
                'is_emri_sonucu' => $row->isemri_sonucu
            ];
        }
        return $workTypes;
    }

    /**
     * Karşılaştırma raporu için çoklu dönem verileri (Sökme Takma)
     * @param array $periods [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'label' => 'Ocak 2026'], ...]
     * @return array ['personel' => [...], 'bolge' => [...], 'firma' => [...]]
     */
    public function getComparisonByPeriods(array $periods): array
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $result = ['personel' => [], 'bolge' => [], 'firma' => []];

        foreach ($periods as $period) {
            $periodLabel = $period['label'];

            $sql = "SELECT t.personel_id, t.ekip_kodu_id,
                        p.adi_soyadi as personel_adi,
                        def.tur_adi as ekip_adi,
                        def.ekip_bolge as bolge,
                        COUNT(*) as toplam,
                        COUNT(DISTINCT t.tarih) as gun_sayisi
                    FROM {$this->table} t
                    LEFT JOIN personel p ON t.personel_id = p.id
                    LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                    WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? AND t.silinme_tarihi IS NULL
                    GROUP BY t.personel_id, t.ekip_kodu_id, p.adi_soyadi, def.tur_adi, def.ekip_bolge";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$firmaId, $period['start'], $period['end']]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

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
}
