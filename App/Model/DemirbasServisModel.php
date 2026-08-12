<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DemirbasServisModel extends Model
{
    protected $table = 'demirbas_servis_kayitlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function findForCompany($id)
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL");
        $sql->execute([
            'id' => (int) $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function updateFaturaDosyasi($id, $dosyaAdi, $orijinalAd, $mimeTipi, $boyut)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET fatura_dosya_adi = :dosya_adi,
                fatura_orijinal_adi = :orijinal_adi,
                fatura_mime_tipi = :mime_tipi,
                fatura_boyutu = :boyut
            WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL
        ");
        return $sql->execute([
            'dosya_adi' => $dosyaAdi,
            'orijinal_adi' => $orijinalAd,
            'mime_tipi' => $mimeTipi,
            'boyut' => $boyut,
            'id' => (int) $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Tüm servis kayıtlarını getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT s.*, d.demirbas_adi, d.demirbas_no, d.seri_no, d.marka, d.model, p.adi_soyadi as teslim_eden_adi
            FROM {$this->table} s
            INNER JOIN demirbas d ON s.demirbas_id = d.id
            LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
            WHERE s.firma_id = :firma_id 
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir demirbaşın servis kayıtlarını getirir
     */
    public function getByDemirbas($demirbasId)
    {
        $sql = $this->db->prepare("
            SELECT s.*, d.demirbas_adi, d.demirbas_no, d.seri_no, d.marka, d.model, p.adi_soyadi as teslim_eden_adi
            FROM {$this->table} s
            INNER JOIN demirbas d ON s.demirbas_id = d.id
            LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
            WHERE s.demirbas_id = :demirbas_id 
            AND s.firma_id = :firma_id
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $sql->execute([
            'demirbas_id' => $demirbasId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığına göre servis kayıtlarını getirir
     */
    public function getByDateRange($baslangic, $bitis, $demirbasId = null, $status = 'all')
    {
        $sqlStr = "SELECT s.*, d.demirbas_adi, d.demirbas_no, d.seri_no, d.marka, d.model, p.adi_soyadi as teslim_eden_adi
                  FROM {$this->table} s
                  LEFT JOIN demirbas d ON s.demirbas_id = d.id
                  LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
                  WHERE s.firma_id = :firma_id 
                  AND s.silinme_tarihi IS NULL";

        $params = [
            'firma_id' => $_SESSION['firma_id']
        ];

        if ($baslangic) {
            $sqlStr .= " AND s.servis_tarihi >= :baslangic";
            $params['baslangic'] = $baslangic;
        }

        if ($bitis) {
            $sqlStr .= " AND s.servis_tarihi <= :bitis";
            $params['bitis'] = $bitis;
        }

        if ($status === 'active') {
            $sqlStr .= " AND s.iade_tarihi IS NULL";
        } elseif ($status === 'completed') {
            $sqlStr .= " AND s.iade_tarihi IS NOT NULL";
        }

        if ($demirbasId) {
            $sqlStr .= " AND s.demirbas_id = :demirbas_id";
            $params['demirbas_id'] = $demirbasId;
        }

        $sqlStr .= " ORDER BY s.servis_tarihi DESC";

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * DataTables server-side listesi için verileri getirir
     */
    public function getDatatableList(array $request)
    {
        $start = isset($request['start']) ? (int) $request['start'] : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 10;
        $search = isset($request['search']['value']) ? trim($request['search']['value']) : null;
        $orderCol = isset($request['order'][0]['column']) ? (int) $request['order'][0]['column'] : 2;
        $orderDir = strtoupper($request['order'][0]['dir'] ?? 'DESC');
        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        $baslangic = !empty($request['baslangic']) ? \App\Helper\Date::dttoeng($request['baslangic']) : null;
        $bitis = !empty($request['bitis']) ? \App\Helper\Date::dttoeng($request['bitis']) : null;
        $statusFilter = $request['status_filter'] ?? 'all';

        $baseSql = "SELECT s.*, 
                           d.demirbas_adi, 
                           d.demirbas_no, 
                           d.seri_no, 
                           d.marka, 
                           d.model, 
                           p.adi_soyadi as teslim_eden_adi
                    FROM {$this->table} s
                    LEFT JOIN demirbas d ON s.demirbas_id = d.id
                    LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
                    WHERE s.firma_id = :firma_id 
                      AND s.silinme_tarihi IS NULL";

        $params = ['firma_id' => $_SESSION['firma_id']];

        if ($baslangic) {
            $baseSql .= " AND s.servis_tarihi >= :baslangic";
            $params['baslangic'] = $baslangic;
        }

        if ($bitis) {
            $baseSql .= " AND s.servis_tarihi <= :bitis";
            $params['bitis'] = $bitis;
        }

        if ($statusFilter === 'active') {
            $baseSql .= " AND s.iade_tarihi IS NULL";
        } elseif ($statusFilter === 'completed') {
            $baseSql .= " AND s.iade_tarihi IS NOT NULL";
        }

        $whereSearch = "";

        // 1) Global Arama (Demirbaş Adı, No, Seri No, Marka, Model, Servis Adı, Teslim Eden, Nedeni, İşlemler, Tutar)
        if (!empty($search)) {
            $whereSearch .= " AND (
                d.demirbas_adi LIKE :search 
                OR d.demirbas_no LIKE :search 
                OR d.seri_no LIKE :search 
                OR d.marka LIKE :search 
                OR d.model LIKE :search 
                OR s.servis_adi LIKE :search 
                OR p.adi_soyadi LIKE :search 
                OR s.servis_nedeni LIKE :search 
                OR s.yapilan_islemler LIKE :search
                OR CAST(s.tutar AS CHAR) LIKE :search
            )";
            $params['search'] = "%$search%";
        }

        // 2) Sütun Bazlı Arama (Header Filtreleri)
        $colSearchMap = [
            1 => "CONCAT_WS(' ', d.demirbas_adi, d.demirbas_no, d.seri_no, d.marka, d.model)",
            2 => 's.servis_tarihi',
            3 => 's.iade_tarihi',
            4 => 's.servis_adi',
            5 => 'p.adi_soyadi',
            6 => "CONCAT_WS(' ', s.servis_nedeni, s.yapilan_islemler)",
            7 => 's.tutar'
        ];

        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $colIdx => $col) {
                if (!empty($col['search']['value']) && isset($colSearchMap[$colIdx])) {
                    $field = $colSearchMap[$colIdx];
                    $searchValue = trim($col['search']['value']);
                    $paramKey = "col_search_" . $colIdx;

                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = $vals[1] ?? null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {
                            $isDateColumn = in_array($colIdx, [2, 3]);

                            if ($isDateColumn) {
                                if ($val) $val = \App\Helper\Date::dttoeng($val);
                                if ($val2) $val2 = \App\Helper\Date::dttoeng($val2);
                            }

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramKey . "_" . $vIdx;
                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } else {
                                                if ($isDateColumn && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::dttoeng($v);
                                                    $orConditions[] = "DATE($field) = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        $whereSearch .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains':
                                    $whereSearch .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $whereSearch .= " AND $field NOT LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $whereSearch .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "$val%";
                                    break;
                                case 'ends_with':
                                    $whereSearch .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val";
                                    break;
                                case 'equals':
                                    if ($isDateColumn) {
                                        $whereSearch .= " AND DATE($field) = :$paramKey";
                                    } else {
                                        $whereSearch .= " AND $field = :$paramKey";
                                    }
                                    $params[$paramKey] = $val;
                                    break;
                                case 'not_equals':
                                    $whereSearch .= " AND $field != :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'before':
                                    $whereSearch .= " AND $field < :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'after':
                                    $whereSearch .= " AND $field > :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramKey . "_1";
                                        $p2 = $paramKey . "_2";
                                        $whereSearch .= " AND DATE($field) BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val;
                                        $params[$p2] = $val2;
                                    }
                                    break;
                                case 'null':
                                    $whereSearch .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                    break;
                                case 'not_null':
                                    $whereSearch .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'";
                                    break;
                            }
                        }
                    } else {
                        $whereSearch .= " AND $field LIKE :$paramKey";
                        $params[$paramKey] = "%$searchValue%";
                    }
                }
            }
        }

        // Records Total
        $countTotalSql = "SELECT COUNT(*) FROM {$this->table} s WHERE s.firma_id = :firma_id AND s.silinme_tarihi IS NULL";
        $countTotalParams = ['firma_id' => $_SESSION['firma_id']];
        if ($baslangic) {
            $countTotalSql .= " AND s.servis_tarihi >= :baslangic";
            $countTotalParams['baslangic'] = $baslangic;
        }
        if ($bitis) {
            $countTotalSql .= " AND s.servis_tarihi <= :bitis";
            $countTotalParams['bitis'] = $bitis;
        }
        if ($statusFilter === 'active') {
            $countTotalSql .= " AND s.iade_tarihi IS NULL";
        } elseif ($statusFilter === 'completed') {
            $countTotalSql .= " AND s.iade_tarihi IS NOT NULL";
        }

        $stmtTotal = $this->db->prepare($countTotalSql);
        $stmtTotal->execute($countTotalParams);
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        // Records Filtered
        $countFilteredSql = "SELECT COUNT(*) FROM {$this->table} s
                             LEFT JOIN demirbas d ON s.demirbas_id = d.id
                             LEFT JOIN personel p ON s.teslim_eden_personel_id = p.id
                             WHERE s.firma_id = :firma_id 
                               AND s.silinme_tarihi IS NULL";
        if ($baslangic) {
            $countFilteredSql .= " AND s.servis_tarihi >= :baslangic";
        }
        if ($bitis) {
            $countFilteredSql .= " AND s.servis_tarihi <= :bitis";
        }
        if ($statusFilter === 'active') {
            $countFilteredSql .= " AND s.iade_tarihi IS NULL";
        } elseif ($statusFilter === 'completed') {
            $countFilteredSql .= " AND s.iade_tarihi IS NOT NULL";
        }
        $countFilteredSql .= $whereSearch;

        $stmtFiltered = $this->db->prepare($countFilteredSql);
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // Ordering
        $orderMap = [
            0 => 's.id',
            1 => 'd.demirbas_adi',
            2 => 's.servis_tarihi',
            3 => 's.iade_tarihi',
            4 => 's.servis_adi',
            5 => 'p.adi_soyadi',
            6 => 's.servis_nedeni',
            7 => 's.tutar'
        ];

        $orderByField = $orderMap[$orderCol] ?? 's.servis_tarihi';
        $finalSql = $baseSql . $whereSearch . " ORDER BY {$orderByField} {$orderDir}";

        if ($length != -1) {
            $finalSql .= " LIMIT " . (int) $start . ", " . (int) $length;
        }

        $stmt = $this->db->prepare($finalSql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            'draw' => intval($request['draw'] ?? 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ];
    }

    /**
     * Servis istatistikleri
     */
    public function getStats($baslangic = null, $bitis = null)
    {
        $sqlStr = "SELECT 
                    COUNT(*) as toplam_kayit,
                    SUM(tutar) as toplam_maliyet,
                    (SELECT COUNT(DISTINCT demirbas_id) FROM {$this->table} WHERE iade_tarihi IS NULL AND silinme_tarihi IS NULL AND firma_id = :firma_id_inner) as servisteki_sayisi
                  FROM {$this->table}
                  WHERE firma_id = :firma_id
                  AND silinme_tarihi IS NULL";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'firma_id_inner' => $_SESSION['firma_id']
        ];

        if ($baslangic) {
            $sqlStr .= " AND servis_tarihi >= :baslangic";
            $params['baslangic'] = $baslangic;
        }
        if ($bitis) {
            $sqlStr .= " AND servis_tarihi <= :bitis";
            $params['bitis'] = $bitis;
        }

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
