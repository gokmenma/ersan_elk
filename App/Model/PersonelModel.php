<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class PersonelModel extends Model
{
    protected $table = 'personel';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**Tüm aktif personelleri getirir */
    public function all()
    {
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id
                GROUP BY p.id";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function search($term)
    {
        $term = "%$term%";
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id
                AND (
                    p.tc_kimlik_no LIKE :term OR
                    p.adi_soyadi LIKE :term OR
                    p.cep_telefonu LIKE :term OR
                    p.email_adresi LIKE :term OR
                    p.gorev LIKE :term OR
                    (CASE WHEN p.aktif_mi = 1 THEN 'Aktif' ELSE 'Pasif' END) LIKE :term
                )
                GROUP BY p.id";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'term' => $term
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id";

        $params = ['firma_id' => $_SESSION['firma_id']];

        // Global Search
        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (
                p.tc_kimlik_no LIKE :term OR
                p.adi_soyadi LIKE :term OR
                p.cep_telefonu LIKE :term OR
                p.email_adresi LIKE :term OR
                p.gorev LIKE :term OR
                (CASE WHEN p.aktif_mi = 1 THEN 'Aktif' ELSE 'Pasif' END) LIKE :term
            )";
            $params['term'] = $term;
        }

        // Column Searches
        if (!empty($colSearches)) {
            $colMap = [
                2 => 'p.tc_kimlik_no',
                3 => 'p.adi_soyadi',
                4 => 'p.ise_giris_tarihi',
                5 => 'p.cep_telefonu',
                6 => 'p.email_adresi',
                7 => 'p.gorev',
                9 => 'p.aktif_mi'
            ];

            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;

                    if ($idx == 9) { // Durum (Aktif/Pasif)
                        if (stripos('Aktif', $val) !== false) {
                            $sql .= " AND p.aktif_mi = 1";
                        } elseif (stripos('Pasif', $val) !== false) {
                            $sql .= " AND p.aktif_mi = 0";
                        }
                    } elseif ($idx == 4) { // Tarih
                        $val = "%$val%";
                        $sql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                        $params[$paramName] = $val;
                    } else {
                        $val = "%$val%";
                        $sql .= " AND $field LIKE :$paramName";
                        $params[$paramName] = $val;
                    }
                }
            }
        }

        $sql .= " GROUP BY p.id";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

    public function where($column, $value)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE $column = ? AND firma_id = ?");
        $sql->execute(array($value, $_SESSION['firma_id']));
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aynı ekip kodunda aktif personel var mı kontrol eder
     * @param string $ekip_no Ekip kodu
     * @param int|null $exclude_id Hariç tutulacak personel ID'si (güncelleme işlemlerinde)
     * @return object|null Aktif personel varsa personel bilgisi, yoksa null
     */
    public function getAktifPersonelByEkipNo($ekip_no, $exclude_id = null)
    {
        if (empty($ekip_no)) {
            return null;
        }

        $sql = "SELECT id, adi_soyadi, ekip_no FROM $this->table 
                WHERE ekip_no = ? 
                AND aktif_mi = 1 
                AND firma_id = ?";

        $params = [$ekip_no, $_SESSION['firma_id']];

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * DataTables için sunucu taraflı veri çekme
     */
    public function getDataTable($request)
    {
        $params = ['firma_id' => $_SESSION['firma_id']];

        // Temel sorgu
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id";

        // Toplam kayıt sayısı (filtresiz)
        $totalQuery = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = :firma_id");
        $totalQuery->execute(['firma_id' => $_SESSION['firma_id']]);
        $recordsTotal = $totalQuery->fetchColumn();

        // Filtreleme
        $filterSql = "";

        // Global Arama
        if (!empty($request['search']['value'])) {
            $searchValue = "%" . $request['search']['value'] . "%";
            $filterSql .= " AND (
                p.tc_kimlik_no LIKE :search OR
                p.adi_soyadi LIKE :search OR
                p.cep_telefonu LIKE :search OR
                p.email_adresi LIKE :search OR
                p.gorev LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun Bazlı Arama
        $colMap = [
            2 => 'p.tc_kimlik_no',
            3 => 'p.adi_soyadi',
            4 => 'p.ise_giris_tarihi',
            5 => 'p.cep_telefonu',
            6 => 'p.email_adresi',
            7 => 'p.gorev',
            9 => 'p.aktif_mi'
        ];

        if (isset($request['columns'])) {
            foreach ($request['columns'] as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = "%" . $column['search']['value'] . "%";
                    $paramName = "col_" . $i;

                    if ($i == 9) { // Durum
                        if (stripos('Aktif', $column['search']['value']) !== false) {
                            $filterSql .= " AND p.aktif_mi = 1";
                        } elseif (stripos('Pasif', $column['search']['value']) !== false) {
                            $filterSql .= " AND p.aktif_mi = 0";
                        }
                    } else {
                        $filterSql .= " AND $field LIKE :$paramName";
                        $params[$paramName] = $val;
                    }
                }
            }
        }

        $sql .= $filterSql;
        $sql .= " GROUP BY p.id";

        // Filtrelenmiş kayıt sayısı
        $filteredQuerySql = "SELECT COUNT(*) FROM (SELECT p.id FROM {$this->table} p WHERE p.firma_id = :firma_id $filterSql GROUP BY p.id) as temp";
        $filteredQuery = $this->db->prepare($filteredQuerySql);
        // Filtrelenmiş sayı için parametreleri temizle (sadece gerekli olanları bırak)
        $filteredParams = ['firma_id' => $_SESSION['firma_id']];
        if (isset($params['search']))
            $filteredParams['search'] = $params['search'];
        foreach ($params as $key => $val) {
            if (strpos($key, 'col_') === 0)
                $filteredParams[$key] = $val;
        }
        $filteredQuery->execute($filteredParams);
        $recordsFiltered = $filteredQuery->fetchColumn();

        // Sıralama
        if (isset($request['order'][0])) {
            $orderColIdx = $request['order'][0]['column'];
            $orderDir = $request['order'][0]['dir'];
            if (isset($colMap[$orderColIdx])) {
                $sql .= " ORDER BY " . $colMap[$orderColIdx] . " " . $orderDir;
            } else {
                $sql .= " ORDER BY p.adi_soyadi ASC";
            }
        } else {
            $sql .= " ORDER BY p.adi_soyadi ASC";
        }

        // Sayfalama
        if (isset($request['start']) && $request['length'] != -1) {
            $sql .= " LIMIT :start, :length";
            $params['start'] = (int) $request['start'];
            $params['length'] = (int) $request['length'];
        }

        $query = $this->db->prepare($sql);

        // Bind values for LIMIT
        foreach ($params as $key => $val) {
            if ($key === 'start' || $key === 'length') {
                $query->bindValue(":$key", $val, PDO::PARAM_INT);
            } else {
                $query->bindValue(":$key", $val);
            }
        }

        $query->execute();
        $data = $query->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ];
    }
}