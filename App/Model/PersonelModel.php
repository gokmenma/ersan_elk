<?php

namespace App\Model;

use App\Model\Model;
use PDO;


class PersonelModel extends Model
{
    protected $table = 'personel';

    public function __construct()
    {
        parent::__construct($this->table);
    }
    /**Personeli Ekip Kodu ile beraber getirir */
    public function findByEkipNo($id)
    {
        $sql = "SELECT p.*, t.tur_adi as ekip_adi FROM {$this->table} p
                    LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                    WHERE p.id = :id";
        $query = $this->db->prepare($sql);
        $query->execute([
            'id' => $id,
        ]);
        return $query->fetch(PDO::FETCH_OBJ);
    }

    /**Tüm aktif personelleri getirir */
    public function all()
    {
        $sql = "SELECT p.*, t.tur_adi as ekip_adi, f.firma_adi,
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                LEFT JOIN firmalar f ON p.firma_id = f.id
                WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL
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
        $sql = "SELECT p.*, t.tur_adi as ekip_adi, f.firma_adi,
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                LEFT JOIN firmalar f ON p.firma_id = f.id
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
                t.tur_adi LIKE :term OR
                p.ekip_bolge LIKE :term OR
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
                5 => 'p.isten_cikis_tarihi',
                6 => 'p.cep_telefonu',
                7 => 'p.email_adresi',
                8 => 'p.gorev',
                9 => 'p.departman',
                10 => 't.tur_adi',
                12 => 'p.aktif_mi'
            ];

            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;

                    if ($idx == 12) { // Durum (Aktif/Pasif)
                        if (stripos('Aktif', $val) !== false) {
                            $sql .= " AND p.aktif_mi = 1";
                        } elseif (stripos('Pasif', $val) !== false) {
                            $sql .= " AND p.aktif_mi = 0";
                        }
                    } elseif ($idx == 10) { // Ekip / Bölge
                        $val = "%$val%";
                        $sql .= " AND (t.tur_adi LIKE :$paramName OR p.ekip_bolge LIKE :$paramName)";
                        $params[$paramName] = $val;
                    } elseif ($idx == 5 || $idx == 6) { // Tarih
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

    public function where($column, $value = null, $operant = '=')
    {
        if ($value === null && in_array($operant, ['IS', 'IS NOT'])) {
            $sql = $this->db->prepare(
                "SELECT * FROM $this->table 
             WHERE $column $operant NULL AND firma_id = ?"
            );
            $sql->execute([$_SESSION['firma_id']]);
        } else {
            $sql = $this->db->prepare(
                "SELECT * FROM $this->table 
             WHERE $column $operant ? AND firma_id = ?"
            );
            $sql->execute([$value, $_SESSION['firma_id']]);
        }

        return $sql->fetchAll(PDO::FETCH_OBJ);
    }


    public function personelSayilari()
    {
        $sql = $this->db->prepare("
        SELECT
            COUNT(*) AS toplam_personel,
            SUM(
                CASE 
                    WHEN isten_cikis_tarihi IS NULL 
                         OR isten_cikis_tarihi = '0000-00-00'
                    THEN 1 ELSE 0 
                END
            ) AS aktif_personel,
            SUM(
                CASE 
                    WHEN isten_cikis_tarihi IS NOT NULL 
                         AND isten_cikis_tarihi <> '0000-00-00'
                    THEN 1 ELSE 0 
                END
            ) AS pasif_personel
        FROM $this->table
        WHERE firma_id = ?
        AND aktif_mi != 2
    ");

        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
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

        // Temel sorgu - Birden fazla ekip için GROUP_CONCAT kullanıldı
        $sql = "SELECT p.*, 
                GROUP_CONCAT(DISTINCT t_all.tur_adi SEPARATOR ', ') as ekip_adi,
                GROUP_CONCAT(DISTINCT t_all.ekip_bolge SEPARATOR ', ') as ekip_bolge,
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                LEFT JOIN (
                    SELECT pg.personel_id, t.tur_adi, t.ekip_bolge
                    FROM personel_ekip_gecmisi pg
                    JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                    WHERE pg.baslangic_tarihi <= CURDATE() 
                    AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                    AND pg.firma_id = :firma_id_internal
                ) t_all ON p.id = t_all.personel_id
                WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL";

        $params['firma_id_internal'] = $_SESSION['firma_id'];

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
                p.gorev LIKE :search OR
                p.ekip_bolge LIKE :search OR
                t_all.tur_adi LIKE :search
            )";
            $params['search'] = $searchValue;
        }

        // Sütun Bazlı Arama
        $colMap = [
            2 => 'p.tc_kimlik_no',
            3 => 'p.adi_soyadi',
            4 => 'p.ise_giris_tarihi',
            5 => 'p.isten_cikis_tarihi',
            6 => 'p.cep_telefonu',
            7 => 'p.email_adresi',
            8 => 'p.gorev',
            9 => 'p.departman',
            10 => 't_all.tur_adi',
            12 => 'p.aktif_mi'
        ];

        if (isset($request['columns'])) {
            foreach ($request['columns'] as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = "%" . $column['search']['value'] . "%";
                    $paramName = "col_" . $i;

                    if ($i == 12) { // Durum
                        if (stripos('Aktif', $column['search']['value']) !== false) {
                            $filterSql .= " AND p.aktif_mi = 1";
                        } elseif (stripos('Pasif', $column['search']['value']) !== false) {
                            $filterSql .= " AND p.aktif_mi = 0";
                        }
                    } elseif ($i == 10) { // Ekip / Bölge
                        $val = "%" . $column['search']['value'] . "%";
                        $filterSql .= " AND (t_all.tur_adi LIKE :$paramName OR p.ekip_bolge LIKE :$paramName)";
                        $params[$paramName] = $val;
                    } elseif ($i == 5 || $i == 6) { // Tarih
                        $filterSql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                        $params[$paramName] = $val;
                    } else {
                        $val = "%" . $column['search']['value'] . "%";
                        $filterSql .= " AND $field LIKE :$paramName";
                        $params[$paramName] = $val;
                    }
                }
            }
        }

        $sql .= $filterSql;
        $sql .= " GROUP BY p.id";

        // Filtrelenmiş kayıt sayısı
        $filteredQuerySql = "SELECT COUNT(*) FROM (SELECT p.id FROM {$this->table} p 
                             LEFT JOIN (
                                 SELECT pg.personel_id, t.tur_adi, t.ekip_bolge
                                 FROM personel_ekip_gecmisi pg
                                 JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                                 WHERE pg.baslangic_tarihi <= CURDATE() 
                                 AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                                 AND pg.firma_id = :firma_id_internal_v2
                             ) t_all ON p.id = t_all.personel_id
                             WHERE p.firma_id = :firma_id $filterSql GROUP BY p.id) as temp";
        $filteredQuery = $this->db->prepare($filteredQuerySql);
        // Filtrelenmiş sayı için parametreleri temizle (sadece gerekli olanları bırak)
        $filteredParams = ['firma_id' => $_SESSION['firma_id'], 'firma_id_internal_v2' => $_SESSION['firma_id']];
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

    /**
     * Personelin ekip geçmişini getirir
     */
    public function getEkipGecmisi($personel_id)
    {
        $sql = "SELECT pg.*, t.tur_adi as ekip_adi 
                FROM personel_ekip_gecmisi pg
                LEFT JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                WHERE pg.personel_id = :personel_id AND pg.firma_id = :firma_id
                ORDER BY pg.baslangic_tarihi DESC";
        $query = $this->db->prepare($sql);
        $query->execute([
            'personel_id' => $personel_id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir tarihteki aktif ekip kodunu getirir
     */
    public function getEkipByDate($personel_id, $date)
    {
        $sql = "SELECT pg.*, t.tur_adi as ekip_adi 
                FROM personel_ekip_gecmisi pg
                LEFT JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                WHERE pg.personel_id = :personel_id 
                AND pg.firma_id = :firma_id
                AND pg.baslangic_tarihi <= :date
                AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= :date)
                ORDER BY pg.baslangic_tarihi DESC
                LIMIT 1";
        $query = $this->db->prepare($sql);
        $query->execute([
            'personel_id' => $personel_id,
            'firma_id' => $_SESSION['firma_id'],
            'date' => $date
        ]);
        return $query->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Ekip geçmişi ekler
     */
    public function addEkipGecmisi($data)
    {


        $sql = "INSERT INTO personel_ekip_gecmisi (personel_id, ekip_kodu_id, baslangic_tarihi, bitis_tarihi, firma_id) 
                VALUES (:personel_id, :ekip_kodu_id, :baslangic_tarihi, :bitis_tarihi, :firma_id)";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'personel_id' => $data['personel_id'],
            'ekip_kodu_id' => $data['ekip_kodu_id'],
            'baslangic_tarihi' => $data['baslangic_tarihi'],
            'bitis_tarihi' => !empty($data['bitis_tarihi']) ? $data['bitis_tarihi'] : null,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Tek bir ekip geçmişi kaydını getirir
     */
    public function getSingleEkipGecmisi($id)
    {
        $sql = "SELECT pg.*, t.tur_adi as ekip_adi 
                FROM personel_ekip_gecmisi pg
                LEFT JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                WHERE pg.id = :id AND pg.firma_id = :firma_id";
        $query = $this->db->prepare($sql);
        $query->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $query->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Ekip geçmişi günceller
     */
    public function updateEkipGecmisi($data)
    {
        $sql = "UPDATE personel_ekip_gecmisi SET 
                ekip_kodu_id = :ekip_kodu_id, 
                baslangic_tarihi = :baslangic_tarihi, 
                bitis_tarihi = :bitis_tarihi 
                WHERE id = :id AND firma_id = :firma_id";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'id' => $data['id'],
            'ekip_kodu_id' => $data['ekip_kodu_id'],
            'baslangic_tarihi' => $data['baslangic_tarihi'],
            'bitis_tarihi' => !empty($data['bitis_tarihi']) ? $data['bitis_tarihi'] : null,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Belirli bir tarih aralığındaki tüm aktif ekip atamalarını getirir
     */
    public function getAllActiveAssignmentsInRange($startDate, $endDate)
    {
        $sql = "SELECT pg.personel_id, pg.ekip_kodu_id, p.adi_soyadi, p.gorev, p.departman
                FROM personel_ekip_gecmisi pg
                JOIN personel p ON pg.personel_id = p.id
                WHERE pg.firma_id = :firma_id 
                AND p.silinme_tarihi IS NULL
                AND pg.baslangic_tarihi <= :end_date
                AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= :start_date)
                GROUP BY pg.personel_id, pg.ekip_kodu_id";
        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Ekip geçmişi siler
     */
    public function deleteEkipGecmisi($id)
    {
        $sql = "DELETE FROM personel_ekip_gecmisi WHERE id = :id AND firma_id = :firma_id";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    public function getAdvancedDashboardStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $bugun = date('Y-m-d');

        // Sahadaki Personel Sayısı (Bugün iş yapmış olanlar)
        $sqlSahadaki = "SELECT COUNT(DISTINCT p_id) as sahadaki FROM (
            SELECT personel_id as p_id FROM yapilan_isler WHERE tarih = :bugun AND firma_id = :firma_id AND silinme_tarihi IS NULL
            UNION
            SELECT personel_id as p_id FROM endeks_okuma WHERE tarih = :bugun AND firma_id = :firma_id AND silinme_tarihi IS NULL
        ) as sahadakiler";
        $stmtS = $this->db->prepare($sqlSahadaki);
        $stmtS->execute(['bugun' => $bugun, 'firma_id' => $firmaId]);
        $sahadakiCount = $stmtS->fetch(PDO::FETCH_OBJ)->sahadaki ?? 0;

        // İzinli Personel Sayısı
        $sqlIzinli = "SELECT COUNT(*) as izinli FROM personel_izinleri pi
                      JOIN personel p ON pi.personel_id = p.id
                      WHERE pi.baslangic_tarihi <= :bugun AND pi.bitis_tarihi >= :bugun 
                      AND pi.onay_durumu = 'Onaylandı' AND p.firma_id = :firma_id AND pi.silinme_tarihi IS NULL";
        $stmtI = $this->db->prepare($sqlIzinli);
        $stmtI->execute(['bugun' => $bugun, 'firma_id' => $firmaId]);
        $izinliRecord = $stmtI->fetch(PDO::FETCH_OBJ);
        $izinliCount = $izinliRecord ? $izinliRecord->izinli : 0;

        // Sahadaki Araç Sayısı (Aktif olanlar)
        $sqlAracSaha = "SELECT COUNT(*) as sahadaki_arac FROM araclar 
                        WHERE aktif_mi = 1 AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmtA = $this->db->prepare($sqlAracSaha);
        $stmtA->execute(['firma_id' => $firmaId]);
        $sahadakiAracRecord = $stmtA->fetch(PDO::FETCH_OBJ);
        $sahadakiAracCount = $sahadakiAracRecord ? $sahadakiAracRecord->sahadaki_arac : 0;

        // Servisteki Araç Sayısı (Pasif olanlar)
        $sqlAracServis = "SELECT COUNT(*) as servisteki_arac FROM araclar 
                          WHERE aktif_mi = 0 AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmtAS = $this->db->prepare($sqlAracServis);
        $stmtAS->execute(['firma_id' => $firmaId]);
        $servistekiAracRecord = $stmtAS->fetch(PDO::FETCH_OBJ);
        $servistekiAracCount = $servistekiAracRecord ? $servistekiAracRecord->servisteki_arac : 0;

        return (object) [
            'sahadaki_personel' => $sahadakiCount,
            'izinli_personel' => $izinliCount,
            'sahadaki_arac' => $sahadakiAracCount,
            'servisteki_arac' => $servistekiAracCount
        ];
    }
}