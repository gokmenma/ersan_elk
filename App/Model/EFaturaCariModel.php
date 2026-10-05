<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class EFaturaCariModel extends Model
{
    protected $table = 'efatura_cariler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * DataTables Server-side listeleme
     */
    public function ajaxList(array $params, int $firmId): array
    {
        $draw = (int)($params['draw'] ?? 1);
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 25);
        $search = trim($params['search']['value'] ?? '');
        $order = $params['order'] ?? [];

        $where = "c.firm_id = :firm_id AND c.deleted_at IS NULL";
        $bindParams = ['firm_id' => $firmId];

        // Hızlı Durum Filtresi
        $statusFilter = $params['status_filter'] ?? 'all';
        if ($statusFilter === 'kurumsal') {
            $where .= " AND c.alici_turu = 'KURUMSAL'";
        } elseif ($statusFilter === 'bireysel') {
            $where .= " AND c.alici_turu = 'BIREYSEL'";
        } elseif ($statusFilter === 'efatura') {
            $where .= " AND c.belge_turu = 'EFATURA'";
        } elseif ($statusFilter === 'earsiv') {
            $where .= " AND c.belge_turu = 'EARSIV'";
        }

        // Genel Arama
        if ($search !== '') {
            $where .= " AND (c.unvan LIKE :search OR c.kisa_ad LIKE :search OR c.cari_kodu LIKE :search OR c.vkn_tckn LIKE :search OR c.vergi_dairesi LIKE :search OR c.telefon LIKE :search OR c.eposta LIKE :search OR c.il LIKE :search OR c.ilce LIKE :search OR c.posta_kutusu LIKE :search)";
            $bindParams['search'] = "%{$search}%";
        }

        // Sütun Bazlı Filtreleme
        if (!empty($params['columns']) && is_array($params['columns'])) {
            $colMap = [
                0 => 'c.cari_kodu',
                1 => 'c.unvan',
                2 => 'c.vkn_tckn',
                3 => 'c.alici_turu',
                4 => 'c.belge_turu',
                5 => 'c.telefon',
                6 => "CONCAT_WS(' / ', c.il, c.ilce)",
                7 => 'c.eposta'
            ];

            foreach ($params['columns'] as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = trim($column['search']['value']);
                    $paramName = "col_" . $i;

                    if (strpos($val, ':') !== false) {
                        list($mode, $filterVal) = explode(':', $val, 2);
                        $vals = explode('|', $filterVal);

                        switch ($mode) {
                            case 'multi':
                                if (!empty($vals)) {
                                    $multiConditions = [];
                                    foreach ($vals as $vIdx => $v) {
                                        $vParam = $paramName . "_m_" . $vIdx;
                                        $multiConditions[] = "$field LIKE :$vParam";
                                        $bindParams[$vParam] = "%" . trim($v) . "%";
                                    }
                                    $where .= " AND (" . implode(" OR ", $multiConditions) . ")";
                                }
                                break;
                            case 'equals':
                                $where .= " AND $field = :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            case 'not_equals':
                                $where .= " AND $field != :$paramName";
                                $bindParams[$paramName] = $filterVal;
                                break;
                            default:
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "%{$filterVal}%";
                                break;
                        }
                    } else {
                        $where .= " AND $field LIKE :$paramName";
                        $bindParams[$paramName] = "%{$val}%";
                    }
                }
            }
        }

        // Toplam Kayıt Sayısı
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firm_id = :firm_id AND deleted_at IS NULL");
        $totalStmt->execute(['firm_id' => $firmId]);
        $totalRecords = (int)$totalStmt->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $filteredStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} c WHERE {$where}");
        $filteredStmt->execute($bindParams);
        $filteredRecords = (int)$filteredStmt->fetchColumn();

        // Sıralama
        $orderColIndex = (int)($order[0]['column'] ?? 1);
        $orderDir = strtoupper($order[0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $orderColMap = [
            0 => 'c.cari_kodu',
            1 => 'c.unvan',
            2 => 'c.vkn_tckn',
            3 => 'c.alici_turu',
            4 => 'c.belge_turu',
            5 => 'c.telefon',
            6 => 'c.il',
            7 => 'c.eposta'
        ];
        $orderBy = $orderColMap[$orderColIndex] ?? 'c.unvan';

        // Verileri Çek
        $sql = "
            SELECT 
                c.id,
                c.firm_id,
                c.cari_kodu,
                c.unvan,
                c.kisa_ad,
                c.vkn_tckn,
                c.vergi_dairesi,
                c.alici_turu,
                c.belge_turu,
                c.posta_kutusu,
                c.telefon,
                c.eposta,
                c.web_sitesi,
                c.ticaret_sicil_no,
                c.mersis_no,
                c.ulke,
                c.il,
                c.ilce,
                c.posta_kodu,
                c.adres,
                c.notlar,
                c.is_active,
                c.created_at,
                c.updated_at
            FROM {$this->table} c
            WHERE {$where}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT :start, :length
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('start', $start, PDO::PARAM_INT);
        $stmt->bindValue('length', $length, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $encryptedId = Security::encrypt((string)$row['id']);
            $row['enc_id'] = $encryptedId;
            $data[] = $row;
        }

        return [
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data
        ];
    }

    /**
     * KPI / Özet Kartları
     */
    public function summary(int $firmId): object
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) AS toplam_cari,
                SUM(CASE WHEN alici_turu = 'KURUMSAL' THEN 1 ELSE 0 END) AS kurumsal_sayisi,
                SUM(CASE WHEN alici_turu = 'BIREYSEL' THEN 1 ELSE 0 END) AS bireysel_sayisi,
                SUM(CASE WHEN belge_turu = 'EFATURA' THEN 1 ELSE 0 END) AS efatura_mukellefi,
                SUM(CASE WHEN belge_turu = 'EARSIV' THEN 1 ELSE 0 END) AS earsiv_mukellefi,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS aktif_cari
            FROM {$this->table}
            WHERE firm_id = :firm_id AND deleted_at IS NULL
        ");
        $stmt->execute(['firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return (object)[
            'toplam_cari'       => (int)($row['toplam_cari'] ?? 0),
            'kurumsal_sayisi'   => (int)($row['kurumsal_sayisi'] ?? 0),
            'bireysel_sayisi'   => (int)($row['bireysel_sayisi'] ?? 0),
            'efatura_mukellefi' => (int)($row['efatura_mukellefi'] ?? 0),
            'earsiv_mukellefi'  => (int)($row['earsiv_mukellefi'] ?? 0),
            'aktif_cari'        => (int)($row['aktif_cari'] ?? 0)
        ];
    }

    /**
     * ID ile tekil kayıt getirme
     */
    public function getById(int $id, int $firmId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * VKN/TCKN ile kayıt arama
     */
    public function getByVkn(string $vkn, int $firmId): ?array
    {
        $vkn = preg_replace('/\D/', '', $vkn);
        if ($vkn === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE vkn_tckn = :vkn AND firm_id = :firm_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['vkn' => $vkn, 'firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Kayıt ekleme veya güncelleme
     */
    public function saveCari(array $data, int $firmId, int $userId): array
    {
        $unvan = trim($data['unvan'] ?? '');
        $vkn = preg_replace('/\D/', '', $data['vkn_tckn'] ?? '');

        if ($unvan === '') {
            return ['success' => false, 'message' => 'Cari Ünvan / Ad Soyad alanı zorunludur.'];
        }
        if ($vkn === '') {
            return ['success' => false, 'message' => 'VKN veya TCKN alanı zorunludur.'];
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;

        // VKN mükerrerlik kontrolü
        $checkSql = "SELECT id FROM {$this->table} WHERE firm_id = :firm_id AND vkn_tckn = :vkn AND deleted_at IS NULL" . ($id ? " AND id != :id" : "") . " LIMIT 1";
        $checkParams = ['firm_id' => $firmId, 'vkn' => $vkn];
        if ($id) {
            $checkParams['id'] = $id;
        }
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute($checkParams);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Bu VKN/TCKN numarasına sahip başka bir cari kaydı zaten mevcuttur.'];
        }

        $fields = [
            'cari_kodu'        => trim($data['cari_kodu'] ?? ''),
            'unvan'            => $unvan,
            'kisa_ad'          => trim($data['kisa_ad'] ?? ''),
            'vkn_tckn'         => $vkn,
            'vergi_dairesi'    => trim($data['vergi_dairesi'] ?? ''),
            'alici_turu'       => in_array($data['alici_turu'] ?? '', ['KURUMSAL', 'BIREYSEL'], true) ? $data['alici_turu'] : (strlen($vkn) === 11 ? 'BIREYSEL' : 'KURUMSAL'),
            'belge_turu'       => in_array($data['belge_turu'] ?? '', ['OTOMATIK', 'EFATURA', 'EARSIV'], true) ? $data['belge_turu'] : 'OTOMATIK',
            'posta_kutusu'     => trim($data['posta_kutusu'] ?? ''),
            'telefon'          => trim($data['telefon'] ?? ''),
            'eposta'           => trim($data['eposta'] ?? ''),
            'web_sitesi'       => trim($data['web_sitesi'] ?? ''),
            'ticaret_sicil_no' => trim($data['ticaret_sicil_no'] ?? ''),
            'mersis_no'        => trim($data['mersis_no'] ?? ''),
            'ulke'             => trim($data['ulke'] ?? '') ?: 'Türkiye',
            'il'               => trim($data['il'] ?? ''),
            'ilce'             => trim($data['ilce'] ?? ''),
            'posta_kodu'       => trim($data['posta_kodu'] ?? ''),
            'adres'            => trim($data['adres'] ?? ''),
            'notlar'           => trim($data['notlar'] ?? ''),
            'is_active'        => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ];

        if ($id && $id > 0) {
            // Güncelleme
            $sets = [];
            $updateParams = ['id' => $id, 'firm_id' => $firmId];
            foreach ($fields as $key => $val) {
                $sets[] = "{$key} = :{$key}";
                $updateParams[$key] = $val;
            }
            $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $res = $stmt->execute($updateParams);

            if ($res) {
                return ['success' => true, 'message' => 'Cari kaydı başarıyla güncellendi.', 'id' => $id];
            }
            return ['success' => false, 'message' => 'Cari güncellenirken bir hata oluştu.'];
        } else {
            // Yeni Ekleme
            $fields['firm_id'] = $firmId;
            $fields['created_by'] = $userId;
            $columns = implode(', ', array_keys($fields));
            $placeholders = ':' . implode(', :', array_keys($fields));

            $sql = "INSERT INTO {$this->table} ({$columns}, created_at) VALUES ({$placeholders}, NOW())";
            $stmt = $this->db->prepare($sql);
            $res = $stmt->execute($fields);

            if ($res) {
                $newId = (int)$this->db->lastInsertId();
                return ['success' => true, 'message' => 'Cari kaydı başarıyla oluşturuldu.', 'id' => $newId];
            }
            return ['success' => false, 'message' => 'Cari kaydedilirken bir hata oluştu.'];
        }
    }

    /**
     * Soft delete
     */
    public function deleteCari(int $id, int $firmId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), is_active = 0
            WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
    }

    /**
     * Tüm aktif cariler (Select / Autocomplete için)
     */
    public function getAllActive(int $firmId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, cari_kodu, unvan AS CariAdi, unvan, kisa_ad, vkn_tckn, vergi_dairesi, alici_turu, belge_turu, posta_kutusu, telefon AS Telefon, eposta AS Email, web_sitesi, ticaret_sicil_no, mersis_no, ulke, il, ilce, posta_kodu, adres AS Adres, notlar
            FROM {$this->table}
            WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY unvan ASC
        ");
        $stmt->execute(['firm_id' => $firmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Arama (Select2 AJAX)
     */
    public function search(string $term, int $firmId, int $limit = 20): array
    {
        $term = trim($term);
        $stmt = $this->db->prepare("
            SELECT id, cari_kodu, unvan, kisa_ad, vkn_tckn, vergi_dairesi, alici_turu, belge_turu, posta_kutusu, telefon, eposta, ulke, il, ilce, adres
            FROM {$this->table}
            WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
              AND (unvan LIKE :term OR vkn_tckn LIKE :term OR cari_kodu LIKE :term OR kisa_ad LIKE :term)
            ORDER BY unvan ASC
            LIMIT :limit
        ");
        $stmt->bindValue('firm_id', $firmId, PDO::PARAM_INT);
        $stmt->bindValue('term', "%{$term}%", PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
