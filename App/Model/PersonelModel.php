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

    /**
     * Personelin silinmeden önce bağlı kayıtları olup olmadığını kontrol eder
     * @param int $id
     * @return string|false
     */
    public function checkDependencies($id)
    {
        $tables = [
            'yapilan_isler' => 'Personelin Yapılan İşler tablosunda verisi bulunmaktadır.',
            'endeks_okuma' => 'Personelin Endeks Okuma tablosunda verisi bulunmaktadır.',
            'personel_izinleri' => 'Personelin İzinler tablosunda verisi bulunmaktadır.',
            'personel_kesintileri' => 'Personelin Kesinti tablosunda verisi bulunmaktadır.'
        ];

        foreach ($tables as $table => $message) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM $table WHERE personel_id = ? LIMIT 1");
                $stmt->execute([$id]);
                if ($stmt->fetch()) {
                    return $message;
                }
            } catch (\Exception $e) {
                // Table might not exist or other DB error, skip check for this table
            }
        }
        return false;
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

    /**Tüm aktif personelleri getirir 
     * @param bool $activeOnly Sadece aktif personelleri getir
     * @param string|null $modul Modül kodu (dışarıdan sigortalı filtresi için: bordro,puantaj,nobet,demirbas,arac,evrak,mail,takip,personel,dashboard)
     */
    public function all($activeOnly = false, $modul = null)
    {
        $sql = "SELECT p.*, t.tur_adi as ekip_adi, f.firma_adi,
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                LEFT JOIN firmalar f ON p.firma_id = f.id
                WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL";

        $params = ['firma_id' => $_SESSION['firma_id']];

        // Dışarıdan sigortalı filtresi
        if ($modul) {
            $sql .= " AND (p.disardan_sigortali = 0 OR FIND_IN_SET('" . addslashes($modul) . "', p.gorunum_modulleri))";
        } else {
            $sql .= " AND p.disardan_sigortali = 0";
        }

        if ($activeOnly) {
            $sql .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')";
        }

        $sql .= " GROUP BY p.id";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function searchForZimmet($term, $type = 'all')
    {
        $term = "%$term%";
        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'term' => $term
        ];

        $sql = "SELECT p.id, p.adi_soyadi, p.cep_telefonu, p.gorev
                FROM {$this->table} p 
                WHERE p.firma_id = :firma_id 
                AND p.silinme_tarihi IS NULL
                AND (p.disardan_sigortali = 0 OR FIND_IN_SET('demirbas', p.gorunum_modulleri) OR FIND_IN_SET('arac', p.gorunum_modulleri))
                AND (p.adi_soyadi LIKE :term OR p.tc_kimlik_no LIKE :term OR p.cep_telefonu LIKE :term)";

        if ($type === 'kesme_acma') {
            $sql = "SELECT p.id, p.adi_soyadi, p.cep_telefonu, p.gorev
                    FROM {$this->table} p 
                    WHERE p.firma_id = :firma_id 
                    AND p.silinme_tarihi IS NULL
                    AND (p.disardan_sigortali = 0 OR FIND_IN_SET('demirbas', p.gorunum_modulleri) OR FIND_IN_SET('arac', p.gorunum_modulleri))
                    AND (p.departman LIKE '%Kesme%' OR p.departman LIKE '%Açma%')
                    AND (p.adi_soyadi LIKE :term OR p.tc_kimlik_no LIKE :term OR p.cep_telefonu LIKE :term)";
        }

        $sql .= " GROUP BY p.id ORDER BY p.adi_soyadi ASC LIMIT 50";

        $query = $this->db->prepare($sql);
        $query->execute($params);

        $results = [];
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $text = $row->adi_soyadi;
            if ($row->cep_telefonu) {
                $text .= ' (' . $row->cep_telefonu . ')';
            }
            if ($row->gorev) {
                $text .= ' - ' . $row->gorev;
            }

            $results[] = [
                'id' => $row->id,
                'text' => $text
            ];
        }

        return $results;
    }

    public function search($term)
    {
        $term = "%$term%";
        $sql = "SELECT p.*, 
                CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as bildirim_abonesi
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                WHERE p.firma_id = :firma_id
                AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))
                AND (
                    p.tc_kimlik_no LIKE :term OR
                    p.adi_soyadi LIKE :term OR
                    p.cep_telefonu LIKE :term OR
                    p.email_adresi LIKE :term OR
                    p.gorev LIKE :term OR
                    (CASE WHEN (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00') THEN 'Aktif' ELSE 'Pasif' END) LIKE :term
                )";

        $params = [
            'firma_id' => $_SESSION['firma_id'],
            'term' => $term
        ];

        $sql .= " GROUP BY p.id";

        $query = $this->db->prepare($sql);
        $query->execute($params);
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
                WHERE p.firma_id = :firma_id AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))";

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
                (CASE WHEN (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00') THEN 'Aktif' ELSE 'Pasif' END) LIKE :term
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
                12 => 'p.isten_cikis_tarihi',
                23 => 'p.sgk_yapilan_firma'
            ];

            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;

                    if ($idx == 12) { // Durum (Aktif/Pasif)
                        if (stripos('Aktif', $val) !== false) {
                            $sql .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
                        } elseif (stripos('Pasif', $val) !== false) {
                            $sql .= " AND (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
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
        $sql = "SELECT * FROM $this->table 
                WHERE $column $operant ? AND firma_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }


    public function personelSayilari($modul = 'dashboard')
    {
        $restricted_dept = $this->getRestrictedDept();
        $is_restricted = ($restricted_dept !== null);

        $extra_where = $is_restricted ? " AND FIND_IN_SET(departman, :restricted_dept)" : "";
        
        $where = "WHERE firma_id = :firma_id AND silinme_tarihi IS NULL AND (disardan_sigortali = 0 OR FIND_IN_SET(:modul, gorunum_modulleri)) $extra_where";
        $params = ['firma_id' => $_SESSION['firma_id'], 'modul' => $modul];
        if ($is_restricted) {
            $params['restricted_dept'] = $restricted_dept;
        }

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
        $where
    ");

        $sql->execute($params);
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

        // Önce ekibin çoklu kullanıma uygun olup olmadığını kontrol et
        $stmtCheck = $this->db->prepare("SELECT birden_fazla_personel_kullanabilir FROM tanimlamalar WHERE id = ?");
        $stmtCheck->execute([$ekip_no]);
        $ekip = $stmtCheck->fetch(PDO::FETCH_OBJ);

        if ($ekip && $ekip->birden_fazla_personel_kullanabilir == 1) {
            return null; // Çoklu kullanıma uygunsa her zaman müsait
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
        $extra_where_p = "";

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
                    AND pg.firma_id = :firma_id_sub
                ) t_all ON p.id = t_all.personel_id
                WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL 
                AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))
                $extra_where_p";

        $params['firma_id_sub'] = $_SESSION['firma_id'];

        // Toplam kayıt sayısı (filtresiz)
        $totalSql = "SELECT COUNT(*) FROM {$this->table} p WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri)) $extra_where_p";
        $totalQuery = $this->db->prepare($totalSql);
        
        $totalParams = ['firma_id' => $_SESSION['firma_id']];
        
        $totalQuery->execute($totalParams);
        $recordsTotal = $totalQuery->fetchColumn();

        // Filtreleme
        $filterSql = "";

        // Status Filtresi (Hızlı Butonlar)
        if (!empty($request['status'])) {
            $status = $request['status'];
            if ($status === 'Aktif') {
                $filterSql .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
            } elseif ($status === 'Pasif') {
                $filterSql .= " AND (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
            }
        }

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
            11 => 'bildirim_abonesi',
            12 => 'p.isten_cikis_tarihi',
            23 => 'p.sgk_yapilan_firma'
        ];

        if (isset($request['columns'])) {
            foreach ($request['columns'] as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $searchValue = $column['search']['value'];
                    $paramName = "col_" . $i;

                    // Gelişmiş Filtre Ayrıştırıcı (mode:value)
                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);

                        // Değerleri ayır
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {
                            // Tarih sütunları için d.m.Y -> Y-m-d dönüşümü
                            if ($i == 4 || $i == 5) {
                                if ($val && strpos($val, '.') !== false)
                                    $val = \App\Helper\Date::Ymd($val, 'Y-m-d');
                                if ($val2 && strpos($val2, '.') !== false)
                                    $val2 = \App\Helper\Date::Ymd($val2, 'Y-m-d');
                            }

                            // Özel alanlar için değer eşleme (Durum: isten_cikis_tarihi bazlı)

                            // Computed (Hesaplanan) alanlar için WHERE düzenlemesi
                            if ($field == 'bildirim_abonesi') {
                                $field = "(CASE WHEN EXISTS (SELECT 1 FROM push_subscriptions WHERE personel_id = p.id) THEN 1 ELSE 0 END)";
                                if (stripos('Açık', $val) !== false)
                                    $val = 1;
                                elseif (stripos('Kapalı', $val) !== false)
                                    $val = 0;
                            }

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramName . "_" . $vIdx;

                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } elseif ($field == 'p.isten_cikis_tarihi' && $i == 12) {
                                                if (stripos($v, 'Aktif') !== false) {
                                                    $orConditions[] = "(p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
                                                } else {
                                                    $orConditions[] = "(p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
                                                }
                                            } elseif (strpos($field, 'push_subscriptions') !== false) { // bildirim_abonesi
                                                $mappedVal = (stripos($v, 'Açık') !== false) ? 1 : 0;
                                                $orConditions[] = "$field = :$vParam";
                                                $params[$vParam] = $mappedVal;
                                            } else {
                                                // Eğer tarih sütunuysa ve değer nokta içeriyorsa dönüştür
                                                if (($i == 4 || $i == 5) && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::Ymd($v, 'Y-m-d');
                                                    $orConditions[] = "$field = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        $filterSql .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $filterSql .= " AND $field NOT LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "$val%";
                                    break;
                                case 'ends_with':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val";
                                    break;
                                case 'equals':
                                    $filterSql .= " AND $field = :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'not_equals':
                                    $filterSql .= " AND $field != :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'gt':
                                case 'greater_than':
                                    $filterSql .= " AND $field > :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'lt':
                                case 'less_than':
                                    $filterSql .= " AND $field < :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'gte':
                                case 'greater_equal':
                                    $filterSql .= " AND $field >= :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'lte':
                                case 'less_equal':
                                    $filterSql .= " AND $field <= :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'before':
                                    $filterSql .= " AND $field < :$paramName";
                                    $params[$paramName] = $val; // Zaten yukarıda Y-m-d yapıldı
                                    break;
                                case 'after':
                                    $filterSql .= " AND $field > :$paramName";
                                    $params[$paramName] = $val; // Zaten yukarıda Y-m-d yapıldı
                                    break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramName . "_1";
                                        $p2 = $paramName . "_2";
                                        $filterSql .= " AND $field BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val; // Zaten yukarıda Y-m-d yapıldı
                                        $params[$p2] = $val2; // Zaten yukarıda Y-m-d yapıldı
                                    }
                                    break;
                                case 'null':
                                    $filterSql .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                    break;
                                case 'not_null':
                                    $filterSql .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'";
                                    break;
                            }
                        }
                    } else {
                        // Normal (Eski) Filtre Mantığı (Sadece LIKE)
                        if ($i == 12) { // Durum
                            if (stripos('Aktif', $searchValue) !== false) {
                                $filterSql .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
                            } elseif (stripos('Pasif', $searchValue) !== false) {
                                $filterSql .= " AND (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
                            }
                        } elseif ($i == 10) { // Ekip / Bölge
                            $filterSql .= " AND (t_all.tur_adi LIKE :$paramName OR p.ekip_bolge LIKE :$paramName)";
                            $params[$paramName] = "%$searchValue%";
                        } elseif ($i == 4 || $i == 5) { // Tarih (DataTables sütun indexine göre)
                            $filterSql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                            $params[$paramName] = "%$searchValue%";
                        } else {
                            $filterSql .= " AND $field LIKE :$paramName";
                            $params[$paramName] = "%$searchValue%";
                        }
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
                                 AND pg.firma_id = :firma_id_sub
                             ) t_all ON p.id = t_all.personel_id
                             WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri)) $filterSql GROUP BY p.id) as temp";

        $filteredQuery = $this->db->prepare($filteredQuerySql);

        // Sadece SQL içinde geçen parametreleri bind et
        foreach ($params as $key => $val) {
            if (strpos($filteredQuerySql, ":" . $key) !== false) {
                $filteredQuery->bindValue(":$key", $val);
            }
        }

        $filteredQuery->execute();
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

        // Sadece SQL içinde geçen parametreleri bind et
        foreach ($params as $key => $val) {
            if (strpos($sql, ":" . $key) !== false) {
                if ($key === 'start' || $key === 'length') {
                    $query->bindValue(":$key", $val, PDO::PARAM_INT);
                } else {
                    $query->bindValue(":$key", $val);
                }
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


        $sql = "INSERT INTO personel_ekip_gecmisi (personel_id, ekip_kodu_id, baslangic_tarihi, bitis_tarihi, firma_id, ekip_sefi_mi) 
                VALUES (:personel_id, :ekip_kodu_id, :baslangic_tarihi, :bitis_tarihi, :firma_id, :ekip_sefi_mi)";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'personel_id' => $data['personel_id'],
            'ekip_kodu_id' => $data['ekip_kodu_id'],
            'baslangic_tarihi' => $data['baslangic_tarihi'],
            'bitis_tarihi' => !empty($data['bitis_tarihi']) ? $data['bitis_tarihi'] : null,
            'firma_id' => $_SESSION['firma_id'],
            'ekip_sefi_mi' => $data['ekip_sefi_mi'] ?? 0
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
                bitis_tarihi = :bitis_tarihi,
                ekip_sefi_mi = :ekip_sefi_mi
                WHERE id = :id AND firma_id = :firma_id";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'id' => $data['id'],
            'ekip_kodu_id' => $data['ekip_kodu_id'],
            'baslangic_tarihi' => $data['baslangic_tarihi'],
            'bitis_tarihi' => !empty($data['bitis_tarihi']) ? $data['bitis_tarihi'] : null,
            'firma_id' => $_SESSION['firma_id'],
            'ekip_sefi_mi' => $data['ekip_sefi_mi'] ?? 0
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

    /**
     * Ekip geçmişi silinirken bağlı görevlerin olup olmadığını kontrol eder
     */
    public function hasRelatedTasks($personel_id, $ekip_kodu_id)
    {
        $tables = ['yapilan_isler', 'endeks_okuma', 'sayac_degisim'];
        foreach ($tables as $table) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM $table WHERE personel_id = ? AND ekip_kodu_id = ? AND silinme_tarihi IS NULL LIMIT 1");
                $stmt->execute([$personel_id, $ekip_kodu_id]);
                if ($stmt->fetch()) {
                    return true;
                }
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
        return false;
    }

    /**
     * Bağlı görevleri soft delete yapar
     */
    public function softDeleteRelatedTasks($personel_id, $ekip_kodu_id)
    {
        $tables = ['yapilan_isler', 'endeks_okuma', 'sayac_degisim'];
        $now = date('Y-m-d H:i:s');
        foreach ($tables as $table) {
            try {
                $stmt = $this->db->prepare("UPDATE $table SET silinme_tarihi = ? WHERE personel_id = ? AND ekip_kodu_id = ? AND silinme_tarihi IS NULL");
                $stmt->execute([$now, $personel_id, $ekip_kodu_id]);
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }

    /**
     * Personelin açıkta kalan (bitiş tarihi olmayan) ekip atamalarını kapatır
     */
    public function closeActiveEkipAssignments($personel_id, $endDate)
    {
        if (empty($endDate))
            return false;

        $sql = "UPDATE personel_ekip_gecmisi SET bitis_tarihi = :bitis_tarihi 
                WHERE personel_id = :personel_id AND bitis_tarihi IS NULL AND firma_id = :firma_id";
        $query = $this->db->prepare($sql);
        return $query->execute([
            'bitis_tarihi' => $endDate,
            'personel_id' => $personel_id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Personelin açıkta kalan (bitiş tarihi olmayan veya işten çıkış tarihinden büyük olan) görev (maaş) atamalarını kapatır
     */
    public function closeActiveGorevGecmisi($personel_id, $endDate)
    {
        if (empty($endDate))
            return false;

        $sql = "UPDATE personel_gorev_gecmisi SET bitis_tarihi = ? 
                WHERE personel_id = ? AND (bitis_tarihi IS NULL OR bitis_tarihi > ?)";
        $query = $this->db->prepare($sql);
        return $query->execute([$endDate, $personel_id, $endDate]);
    }

    /**
     * Personelin belirli bir ekip kodu için tarih çakışması olup olmadığını kontrol eder
     */
    public function hasEkipOverlap($personel_id, $ekip_kodu_id, $startDate, $endDate, $exclude_id = null)
    {
        $sql = "SELECT COUNT(*) FROM personel_ekip_gecmisi 
                WHERE personel_id = :personel_id 
                AND ekip_kodu_id = :ekip_kodu_id 
                AND (
                    (:start_date <= bitis_tarihi OR bitis_tarihi IS NULL)
                    AND 
                    (baslangic_tarihi <= :end_date OR :end_date_null IS NULL)
                )
                AND firma_id = :firma_id";

        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = [
            'personel_id' => $personel_id,
            'ekip_kodu_id' => $ekip_kodu_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'end_date_null' => $endDate,
            'firma_id' => $_SESSION['firma_id']
        ];
        if ($exclude_id) {
            $params['exclude_id'] = $exclude_id;
        }

        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function getActivePersonnelByTeamAndDate($ekip_kodu_id, $date)
    {
        $sql = "SELECT p.id, p.adi_soyadi 
                FROM personel_ekip_gecmisi pg
                JOIN personel p ON pg.personel_id = p.id
                WHERE pg.ekip_kodu_id = :ekip_kodu_id 
                AND pg.baslangic_tarihi <= :date
                AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= :date)
                AND p.silinme_tarihi IS NULL
                AND pg.firma_id = :firma_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ekip_kodu_id' => $ekip_kodu_id,
            'date' => $date,
            'firma_id' => $_SESSION['firma_id']
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir ekip kodunun başka bir personel tarafından o tarihlerde kullanılıp kullanılmadığını kontrol eder
     */
    public function isEkipKoduAvailable($ekip_kodu_id, $startDate, $endDate, $exclude_gecmis_id = null)
    {
        // Önce ekibin çoklu kullanıma uygun olup olmadığını kontrol et
        $stmtCheck = $this->db->prepare("SELECT birden_fazla_personel_kullanabilir FROM tanimlamalar WHERE id = ?");
        $stmtCheck->execute([$ekip_kodu_id]);
        $ekip = $stmtCheck->fetch(PDO::FETCH_OBJ);

        if ($ekip && $ekip->birden_fazla_personel_kullanabilir == 1) {
            return null; // Çoklu kullanıma uygunsa her zaman müsait
        }

        $sql = "SELECT p.adi_soyadi FROM personel_ekip_gecmisi pg
                JOIN personel p ON pg.personel_id = p.id
                WHERE pg.ekip_kodu_id = :ekip_kodu_id 
                AND (
                    (:start_date <= pg.bitis_tarihi OR pg.bitis_tarihi IS NULL)
                    AND 
                    (pg.baslangic_tarihi <= :end_date OR :end_date_null IS NULL)
                )
                AND pg.firma_id = :firma_id";

        if ($exclude_gecmis_id) {
            $sql .= " AND pg.id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = [
            'ekip_kodu_id' => $ekip_kodu_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'end_date_null' => $endDate,
            'firma_id' => $_SESSION['firma_id']
        ];
        if ($exclude_gecmis_id) {
            $params['exclude_id'] = $exclude_gecmis_id;
        }

        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Personelin en eski ekip atama tarihini getirir
     */
    public function getEarliestEkipAssignmentDate($personel_id)
    {
        $sql = "SELECT MIN(baslangic_tarihi) as earliest_date 
                FROM personel_ekip_gecmisi 
                WHERE personel_id = ? AND firma_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personel_id, $_SESSION['firma_id']]);
        $res = $stmt->fetch(PDO::FETCH_OBJ);
        return $res ? $res->earliest_date : null;
    }

    /**
     * Personelin görev (maaş) geçmişini getirir
     */
    public function getGorevGecmisi($personel_id)
    {
        $sql = "SELECT * 
                FROM personel_gorev_gecmisi 
                WHERE personel_id = ? 
                ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personel_id]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Görev (Maaş) geçmişi ekler
     */
    public function addGorevGecmisi($data)
    {
        $sql = "INSERT INTO personel_gorev_gecmisi 
                (personel_id, departman, gorev, maas_durumu, maas_tutari, baslangic_tarihi, bitis_tarihi, aciklama, olusturan_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['personel_id'],
            $data['departman'] ?? null,
            $data['gorev'] ?? null,
            $data['maas_durumu'],
            $data['maas_tutari'] ?? 0,
            $data['baslangic_tarihi'],
            $data['bitis_tarihi'] ?? null,
            $data['aciklama'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Tek bir görev (Maaş) geçmişi kaydını getirir
     */
    public function getSingleGorevGecmisi($id)
    {
        $sql = "SELECT * 
                FROM personel_gorev_gecmisi 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Görev (Maaş) geçmişini günceller
     */
    public function updateGorevGecmisi($data)
    {
        $sql = "UPDATE personel_gorev_gecmisi SET 
                departman = ?,
                gorev = ?,
                maas_durumu = ?, 
                maas_tutari = ?, 
                baslangic_tarihi = ?, 
                bitis_tarihi = ?, 
                aciklama = ? 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['departman'] ?? null,
            $data['gorev'] ?? null,
            $data['maas_durumu'],
            $data['maas_tutari'] ?? 0,
            $data['baslangic_tarihi'],
            $data['bitis_tarihi'] ?? null,
            $data['aciklama'] ?? null,
            $data['id']
        ]);
    }

    /**
     * Görev (Maaş) geçmişini siler
     */
    public function deleteGorevGecmisi($id)
    {
        $sql = "DELETE FROM personel_gorev_gecmisi WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Personelin aktif görev geçmişi kaydını getirir (bugün geçerli olan)
     */
    public function getAktifGorevGecmisi($personel_id)
    {
        $bugun = date('Y-m-d');
        $sql = "SELECT * FROM personel_gorev_gecmisi 
                WHERE personel_id = ? 
                AND baslangic_tarihi <= ?
                AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
                ORDER BY baslangic_tarihi DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personel_id, $bugun, $bugun]);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Personel tablosunu aktif görev geçmişi kaydına göre senkronize eder
     * Maaş Tipi Geçmişi'ndeki aktif kayıt, personel tablosundaki departman/gorev/maas alanlarını günceller
     */
    public function syncPersonelFromGorevGecmisi($personel_id)
    {
        $aktifKayit = $this->getAktifGorevGecmisi($personel_id);

        if ($aktifKayit) {
            $sql = "UPDATE {$this->table} SET 
                    departman = ?, 
                    gorev = ?, 
                    maas_durumu = ?, 
                    maas_tutari = ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $aktifKayit->departman,
                $aktifKayit->gorev,
                $aktifKayit->maas_durumu,
                $aktifKayit->maas_tutari,
                $personel_id
            ]);
        }

        return $aktifKayit;
    }

    public function getAdvancedDashboardStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $bugun = date('Y-m-d');

        $restricted_dept = $this->getRestrictedDept();
        $is_restricted = ($restricted_dept !== null);
        $extra_where_p = $is_restricted ? " AND FIND_IN_SET(p.departman, :restricted_dept)" : "";

        // Sahadaki Personel Sayısı (Bugün iş yapmış olanlar)
        $sqlSahadaki = "SELECT COUNT(DISTINCT p_id) as sahadaki FROM (
            SELECT p.id as p_id FROM yapilan_isler y 
            JOIN personel p ON y.personel_id = p.id
            WHERE y.tarih = :bugun AND y.firma_id = :firma_id AND y.silinme_tarihi IS NULL $extra_where_p
            UNION ALL
            SELECT p.id as p_id FROM endeks_okuma e
            JOIN personel p ON e.personel_id = p.id
            WHERE e.tarih = :bugun AND e.firma_id = :firma_id AND e.silinme_tarihi IS NULL $extra_where_p
        ) as sahadakiler";
        $stmtS = $this->db->prepare($sqlSahadaki);
        $paramsS = ['bugun' => $bugun, 'firma_id' => $firmaId];
        if ($is_restricted) $paramsS['restricted_dept'] = $restricted_dept;
        $stmtS->execute($paramsS);
        $sahadakiCount = $stmtS->fetch(PDO::FETCH_OBJ)->sahadaki ?? 0;

        // İzinli Personel Sayısı
        $sqlIzinli = "SELECT COUNT(*) as izinli FROM personel_izinleri pi
                      JOIN personel p ON pi.personel_id = p.id
                      LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                      WHERE pi.baslangic_tarihi <= :bugun AND pi.bitis_tarihi >= :bugun 
                      AND pi.onay_durumu = 'Onaylandı' AND p.firma_id = :firma_id AND pi.silinme_tarihi IS NULL
                      $extra_where_p
                      AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))";
        $stmtI = $this->db->prepare($sqlIzinli);
        $paramsI = ['bugun' => $bugun, 'firma_id' => $firmaId];
        if ($is_restricted) $paramsI['restricted_dept'] = $restricted_dept;
        $stmtI->execute($paramsI);
        $izinliRecord = $stmtI->fetch(PDO::FETCH_OBJ);
        $izinliCount = $izinliRecord ? $izinliRecord->izinli : 0;

        // Sahadaki Araç Sayısı (Aktif olanlar)
        $sqlAracSaha = "SELECT COUNT(*) as sahadaki_arac FROM araclar 
                        WHERE aktif_mi = 1 AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmtA = $this->db->prepare($sqlAracSaha);
        $stmtA->execute(['firma_id' => $firmaId]);
        $sahadakiAracRecord = $stmtA->fetch(PDO::FETCH_OBJ);
        $sahadakiAracCount = $sahadakiAracRecord ? $sahadakiAracRecord->sahadaki_arac : 0;

        // Servisteki Araç Sayısı (Giriş yapılmış ama henüz iade edilmemiş olanlar)
        $sqlAracServis = "SELECT COUNT(DISTINCT arac_id) as servisteki_arac FROM arac_servis_kayitlari 
                          WHERE iade_tarihi IS NULL AND firma_id = :firma_id AND silinme_tarihi IS NULL";
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

    public function getMonthlyAdvancedDashboardStats()
    {
        $firmaId = $_SESSION['firma_id'] ?? 0;
        $buAy = date('Y-m-01');
        $sonGun = date('Y-m-t');

        $restricted_dept = $this->getRestrictedDept();
        $is_restricted = ($restricted_dept !== null);
        $extra_where_p = $is_restricted ? " AND FIND_IN_SET(p.departman, :restricted_dept)" : "";

        // Sahadaki Personel Sayısı (Bu ay iş yapmış olan benzersiz personeller)
        $sqlSahadaki = "SELECT COUNT(DISTINCT p_id) as sahadaki FROM (
            SELECT p.id as p_id FROM yapilan_isler y 
            JOIN personel p ON y.personel_id = p.id
            WHERE y.tarih >= :buAy AND y.tarih <= :sonGun AND y.firma_id = :firma_id AND y.silinme_tarihi IS NULL $extra_where_p
            UNION ALL
            SELECT p.id as p_id FROM endeks_okuma e
            JOIN personel p ON e.personel_id = p.id
            WHERE e.tarih >= :buAy AND e.tarih <= :sonGun AND e.firma_id = :firma_id AND e.silinme_tarihi IS NULL $extra_where_p
        ) as sahadakiler";
        $stmtS = $this->db->prepare($sqlSahadaki);
        $paramsS = ['buAy' => $buAy, 'sonGun' => $sonGun, 'firma_id' => $firmaId];
        if ($is_restricted) $paramsS['restricted_dept'] = $restricted_dept;
        $stmtS->execute($paramsS);
        $sahadakiCount = $stmtS->fetch(PDO::FETCH_OBJ)->sahadaki ?? 0;

        // İzinli Personel Sayısı (Bu ay içinde en az bir gün izin kullanan benzersiz personeller)
        $sqlIzinli = "SELECT COUNT(DISTINCT personel_id) as izinli FROM personel_izinleri pi
                      JOIN personel p ON pi.personel_id = p.id
                      LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                      WHERE ((pi.baslangic_tarihi <= :sonGun AND pi.bitis_tarihi >= :buAy))
                      AND pi.onay_durumu = 'Onaylandı' AND p.firma_id = :firma_id AND pi.silinme_tarihi IS NULL
                      $extra_where_p
                      AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))";
        $stmtI = $this->db->prepare($sqlIzinli);
        $paramsI = ['buAy' => $buAy, 'sonGun' => $sonGun, 'firma_id' => $firmaId];
        if ($is_restricted) $paramsI['restricted_dept'] = $restricted_dept;
        $stmtI->execute($paramsI);
        $izinliCount = $stmtI->fetch(PDO::FETCH_OBJ)->izinli ?? 0;

        // Sahadaki Araç Sayısı (Bu ay aktif olanlar)
        $sqlAracSaha = "SELECT COUNT(*) as sahadaki_arac FROM araclar 
                        WHERE aktif_mi = 1 AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmtA = $this->db->prepare($sqlAracSaha);
        $stmtA->execute(['firma_id' => $firmaId]);
        $sahadakiAracCount = $stmtA->fetch(PDO::FETCH_OBJ)->sahadaki_arac ?? 0;

        // Servisteki Araç Sayısı (Giriş yapılmış ama henüz iade edilmemiş olanlar)
        $sqlAracServis = "SELECT COUNT(DISTINCT arac_id) as servisteki_arac FROM arac_servis_kayitlari 
                          WHERE iade_tarihi IS NULL AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmtAS = $this->db->prepare($sqlAracServis);
        $stmtAS->execute(['firma_id' => $firmaId]);
        $servistekiAracCount = $stmtAS->fetch(PDO::FETCH_OBJ)->servisteki_arac ?? 0;

        return (object) [
            'sahadaki_personel' => $sahadakiCount,
            'izinli_personel' => $izinliCount,
            'sahadaki_arac' => $sahadakiAracCount,
            'servisteki_arac' => $servistekiAracCount
        ];
    }

    /**
     * Sütun için benzersiz değerleri getirir (Filtreleme için)
     */
    public function getUniqueValues($column, $request = [])
    {
        $restricted_users = [
            69 => 'Endeks Okuma',
            68 => 'Kesme Açma',
            67 => 'Sayaç Sökme Takma',
            70 => 'Kaçak Kontrol'
        ];
        $current_user_id = $_SESSION['user_id'] ?? 0;
        $is_restricted = isset($restricted_users[$current_user_id]);
        $restricted_dept = $is_restricted ? $restricted_users[$current_user_id] : null;

        if ($is_restricted && \App\Service\Gate::isSuperAdmin()) {
            $is_restricted = false;
        }

        $params = ['firma_id' => $_SESSION['firma_id']];
        $params['firma_id_sub'] = $_SESSION['firma_id'];
        
        $extra_where_p = "";
        if ($is_restricted) {
            $extra_where_p = " AND p.departman = :restricted_dept";
            $params['restricted_dept'] = $restricted_dept;
        }

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
            11 => 'bildirim_abonesi',
            12 => 'p.isten_cikis_tarihi',
            23 => 'p.sgk_yapilan_firma'
        ];

        $targetField = '';
        $skipIdx = -1;

        if ($column === 'ekip_adi' || $column === 'tur_adi' || $column === 't_all.tur_adi') {
            $targetField = 't_all.tur_adi';
            $skipIdx = 10;
        } elseif ($column === 'bildirim_abonesi') {
            return ['Açık', 'Kapalı'];
        } elseif ($column === 'aktif_mi' || $column === 'p.aktif_mi' || $column === 'Durum') {
            return ['Aktif', 'Pasif'];
        } else {
            $targetField = strpos($column, 'p.') === false ? "p." . $column : $column;
            foreach ($colMap as $idx => $f) {
                if ($f === $targetField || $f === $column) {
                    $skipIdx = $idx;
                    break;
                }
            }
        }

        // Temel Sorgu (DataTables filtrelemesiyle aynı JOIN yapısı)
        $sql = "SELECT DISTINCT $targetField as val
                FROM {$this->table} p 
                LEFT JOIN push_subscriptions ps ON p.id = ps.personel_id
                LEFT JOIN (
                    SELECT pg.personel_id, t.tur_adi, t.ekip_bolge
                    FROM personel_ekip_gecmisi pg
                    JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                    WHERE pg.baslangic_tarihi <= CURDATE() 
                    AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                    AND pg.firma_id = :firma_id_sub
                ) t_all ON p.id = t_all.personel_id
                WHERE p.firma_id = :firma_id AND p.silinme_tarihi IS NULL $extra_where_p AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))";

        // Diğer sütunlardaki aktif filtreleri uygula (Cascading)
        $filterSql = "";
        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $i => $columnData) {
                if ($i == $skipIdx)
                    continue; // Mevcut sütun filtresini dahil etme

                if (!empty($columnData['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $searchValue = $columnData['search']['value'];
                    $paramName = "u_col_" . $i;

                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {
                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramName . "_" . $vIdx;
                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } elseif ($field == 'p.isten_cikis_tarihi' && $i == 12) {
                                                if (stripos($v, 'Aktif') !== false) {
                                                    $orConditions[] = "(p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
                                                } else {
                                                    $orConditions[] = "(p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
                                                }
                                            } elseif ($field == 'bildirim_abonesi') {
                                                $mappedVal = (stripos($v, 'Açık') !== false) ? 1 : 0;
                                                $orConditions[] = "(CASE WHEN EXISTS (SELECT 1 FROM push_subscriptions WHERE personel_id = p.id) THEN 1 ELSE 0 END) = :$vParam";
                                                $params[$vParam] = $mappedVal;
                                            } else {
                                                $orConditions[] = "$field LIKE :$vParam";
                                                $params[$vParam] = "%$v%";
                                            }
                                        }
                                        $filterSql .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $filterSql .= " AND $field NOT LIKE :$paramName";
                                    $params[$paramName] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "$val%";
                                    break;
                                case 'ends_with':
                                    $filterSql .= " AND $field LIKE :$paramName";
                                    $params[$paramName] = "%$val";
                                    break;
                                case 'equals':
                                    $filterSql .= " AND $field = :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'gt':
                                case 'greater_than':
                                    $filterSql .= " AND $field > :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'lt':
                                case 'less_than':
                                    $filterSql .= " AND $field < :$paramName";
                                    $params[$paramName] = $val;
                                    break;
                                case 'null':
                                    $filterSql .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                    break;
                                case 'not_null':
                                    $filterSql .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'";
                                    break;
                            }
                        }
                    } else {
                        if ($i == 12) {
                            if (stripos('Aktif', $searchValue) !== false)
                                $filterSql .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')";
                            elseif (stripos('Pasif', $searchValue) !== false)
                                $filterSql .= " AND (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00')";
                        } elseif ($i == 10) {
                            $filterSql .= " AND (t_all.tur_adi LIKE :$paramName OR p.ekip_bolge LIKE :$paramName)";
                            $params[$paramName] = "%$searchValue%";
                        } else {
                            $filterSql .= " AND $field LIKE :$paramName";
                            $params[$paramName] = "%$searchValue%";
                        }
                    }
                }
            }
        }

        $sql .= $filterSql;
        $sql .= " ORDER BY $targetField ASC";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        $results = $query->fetchAll(PDO::FETCH_COLUMN);

        // Boş/Null olanları temizle ve (Boş) olarak ekle (eğer varsa)
        $cleanResults = [];
        $hasEmpty = false;
        foreach ($results as $r) {
            if ($r === null || $r === '' || $r === '0000-00-00') {
                $hasEmpty = true;
            } else {
                $cleanResults[] = $r;
            }
        }

        if ($hasEmpty) {
            $cleanResults[] = "(Boş)";
        }

        return array_unique($cleanResults);
    }
}
