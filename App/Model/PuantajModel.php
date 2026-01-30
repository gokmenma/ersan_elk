<?php

namespace App\Model;

use App\Model\Model;
use PDO;

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
        $sql = "SELECT t.*, p.adi_soyadi as personel_adi 
                FROM $this->table t 
                LEFT JOIN personel p ON t.personel_id = p.id 
                WHERE t.firma_id = ?";
        $params = [$firmaId];

        if ($startDate) {
            $sql .= " AND t.tarih >= ?";
            $params[] = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
        }
        if ($endDate) {
            $sql .= " AND t.tarih <= ?";
            $params[] = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;
        }
        if ($ekipKodu) {
            $sql .= " AND t.personel_id = ?";
            $params[] = $ekipKodu;
        }
        if ($workType) {
            $sql .= " AND t.is_emri_tipi = ?";
            $params[] = $workType;
        }
        if ($workResult) {
            $sql .= " AND t.is_emri_sonucu = ?";
            $params[] = $workResult;
        }

        $sql .= " ORDER BY t.tarih DESC";

        // DEBUG
        file_put_contents(dirname(__DIR__, 2) . '/debug_sql.txt', "SQL: $sql\nParams: " . print_r($params, true) . "\n----------------\n", FILE_APPEND);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getWorkTypes()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $stmt = $this->db->prepare("SELECT DISTINCT is_emri_tipi FROM $this->table WHERE firma_id = ? AND is_emri_tipi IS NOT NULL AND is_emri_tipi != ''");
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getWorkResults($personelId = null)
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $sql = "SELECT DISTINCT is_emri_sonucu FROM $this->table WHERE firma_id = ? AND is_emri_sonucu IS NOT NULL AND is_emri_sonucu != ''";
        $params = [$firmaId];

        if ($personelId) {
            $sql .= " AND personel_id = ?";
            $params[] = $personelId;
        }

        $sql .= " ORDER BY is_emri_sonucu ASC";

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
        $sql = "SELECT personel_id, DAY(tarih) as gun, is_emri_sonucu, SUM(sonuclanmis) as toplam 
                FROM $this->table 
                WHERE firma_id = ? AND YEAR(tarih) = ? AND MONTH(tarih) = ?
                GROUP BY personel_id, DAY(tarih), is_emri_sonucu";

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
            $notInClause = " AND t.is_emri_sonucu NOT IN ($placeholders)";
            $params = array_merge($params, $matchedResults);
        }

        $sql = "SELECT t.*, p.adi_soyadi as personel_adi, ek.tur_adi as ekip_kodu
                FROM yapilan_isler t
                LEFT JOIN personel p ON t.personel_id = p.id
                LEFT JOIN tanimlamalar ek ON p.ekip_no = ek.id
                WHERE t.firma_id = ? AND YEAR(t.tarih) = ? AND MONTH(t.tarih) = ? 
                $notInClause
                AND t.is_emri_sonucu IS NOT NULL AND t.is_emri_sonucu != ''
                ORDER BY t.tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
