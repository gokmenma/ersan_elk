<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class EFaturaMalHizmetModel extends Model
{
    protected $table = 'efatura_mal_hizmet';

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

        $where = "m.firm_id = :firm_id AND m.deleted_at IS NULL";
        $bindParams = ['firm_id' => $firmId];

        // Hızlı Durum/Tür Filtresi
        $statusFilter = $params['status_filter'] ?? 'all';
        if ($statusFilter === 'mal') {
            $where .= " AND m.tur = 'MAL'";
        } elseif ($statusFilter === 'hizmet') {
            $where .= " AND m.tur = 'HIZMET'";
        } elseif ($statusFilter === 'aktif') {
            $where .= " AND m.is_active = 1";
        } elseif ($statusFilter === 'pasif') {
            $where .= " AND m.is_active = 0";
        }

        // Genel Arama
        if ($search !== '') {
            $where .= " AND (m.urun_adi LIKE :search OR m.stok_kodu LIKE :search OR m.barkod LIKE :search OR m.gtip_no LIKE :search OR m.aciklama LIKE :search)";
            $bindParams['search'] = "%{$search}%";
        }

        // Sütun Bazlı Filtreleme
        if (!empty($params['columns']) && is_array($params['columns'])) {
            $colMap = [
                0 => 'm.tur',
                1 => 'm.stok_kodu',
                2 => 'm.urun_adi',
                3 => 'm.birim',
                4 => 'm.para_birimi',
                5 => 'm.kdv_orani',
                6 => 'm.alis_fiyati',
                7 => 'm.satis_fiyati',
                8 => 'm.is_active'
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
                            case 'greater_than':
                                $where .= " AND $field > :$paramName";
                                $bindParams[$paramName] = (float)str_replace(['.', ','], ['', '.'], $filterVal);
                                break;
                            case 'less_than':
                                $where .= " AND $field < :$paramName";
                                $bindParams[$paramName] = (float)str_replace(['.', ','], ['', '.'], $filterVal);
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
        $filteredStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} m WHERE {$where}");
        $filteredStmt->execute($bindParams);
        $filteredRecords = (int)$filteredStmt->fetchColumn();

        // Sıralama
        $orderColIndex = (int)($order[0]['column'] ?? 2);
        $orderDir = strtoupper($order[0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $orderColMap = [
            0 => 'm.tur',
            1 => 'm.stok_kodu',
            2 => 'm.urun_adi',
            3 => 'm.birim',
            4 => 'm.para_birimi',
            5 => 'm.kdv_orani',
            6 => 'm.alis_fiyati',
            7 => 'm.satis_fiyati',
            8 => 'm.is_active'
        ];
        $orderBy = $orderColMap[$orderColIndex] ?? 'm.urun_adi';

        // Verileri Çek
        $sql = "
            SELECT 
                m.id,
                m.firm_id,
                m.tur,
                m.stok_kodu,
                m.urun_adi,
                m.barkod,
                m.birim,
                m.para_birimi,
                m.kdv_orani,
                m.alis_fiyati,
                m.satis_fiyati,
                m.kdv_dahil_mi,
                m.tevkifat_kodu,
                m.tevkifat_orani,
                m.gtip_no,
                m.aciklama,
                m.is_active,
                m.created_at,
                m.updated_at
            FROM {$this->table} m
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
                COUNT(*) AS toplam_kalem,
                SUM(CASE WHEN tur = 'MAL' THEN 1 ELSE 0 END) AS mal_sayisi,
                SUM(CASE WHEN tur = 'HIZMET' THEN 1 ELSE 0 END) AS hizmet_sayisi,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS aktif_kalem,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS pasif_kalem
            FROM {$this->table}
            WHERE firm_id = :firm_id AND deleted_at IS NULL
        ");
        $stmt->execute(['firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return (object)[
            'toplam_kalem'  => (int)($row['toplam_kalem'] ?? 0),
            'mal_sayisi'    => (int)($row['mal_sayisi'] ?? 0),
            'hizmet_sayisi' => (int)($row['hizmet_sayisi'] ?? 0),
            'aktif_kalem'   => (int)($row['aktif_kalem'] ?? 0),
            'pasif_kalem'   => (int)($row['pasif_kalem'] ?? 0)
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
     * Kayıt ekleme veya güncelleme
     */
    public function saveItem(array $data, int $firmId, int $userId): array
    {
        $urunAdi = trim($data['urun_adi'] ?? '');
        if ($urunAdi === '') {
            return ['success' => false, 'message' => 'Mal / Hizmet adı boş bırakılamaz.'];
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $stokKodu = trim($data['stok_kodu'] ?? '');

        // Stok Kodu mükerrerlik kontrolü (varsa)
        if ($stokKodu !== '') {
            $checkSql = "SELECT id FROM {$this->table} WHERE firm_id = :firm_id AND stok_kodu = :stok_kodu AND deleted_at IS NULL" . ($id ? " AND id != :id" : "") . " LIMIT 1";
            $checkParams = ['firm_id' => $firmId, 'stok_kodu' => $stokKodu];
            if ($id) {
                $checkParams['id'] = $id;
            }
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute($checkParams);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Bu stok koduna sahip başka bir ürün/hizmet kaydı zaten mevcuttur.'];
            }
        }

        $cleanNumber = function ($val, $default = 0.0) {
            if ($val === null || $val === '') return $default;
            if (is_numeric($val)) return (float)$val;
            $val = trim((string)$val);
            if (strpos($val, ',') !== false) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            }
            return (float)$val;
        };

        $alisFiyati = $cleanNumber($data['alis_fiyati'] ?? 0);
        $satisFiyati = $cleanNumber($data['satis_fiyati'] ?? 0);
        $kdvOrani = $cleanNumber($data['kdv_orani'] ?? 20, 20.0);

        $fields = [
            'tur'            => in_array($data['tur'] ?? '', ['MAL', 'HIZMET'], true) ? $data['tur'] : 'MAL',
            'stok_kodu'      => $stokKodu,
            'urun_adi'       => $urunAdi,
            'barkod'         => trim($data['barkod'] ?? ''),
            'birim'          => trim($data['birim'] ?? 'C62') ?: 'C62',
            'para_birimi'    => in_array($data['para_birimi'] ?? '', ['TRY', 'USD', 'EUR', 'GBP'], true) ? $data['para_birimi'] : 'TRY',
            'kdv_orani'      => $kdvOrani,
            'alis_fiyati'    => $alisFiyati,
            'satis_fiyati'   => $satisFiyati,
            'kdv_dahil_mi'   => !empty($data['kdv_dahil_mi']) ? 1 : 0,
            'tevkifat_kodu'  => trim($data['tevkifat_kodu'] ?? ''),
            'tevkifat_orani' => trim($data['tevkifat_orani'] ?? ''),
            'gtip_no'        => trim($data['gtip_no'] ?? ''),
            'aciklama'       => trim($data['aciklama'] ?? ''),
            'is_active'      => isset($data['is_active']) ? (int)$data['is_active'] : 1,
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
                return ['success' => true, 'message' => 'Mal / Hizmet kaydı başarıyla güncellendi.', 'id' => $id];
            }
            return ['success' => false, 'message' => 'Kayıt güncellenirken bir hata oluştu.'];
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
                return ['success' => true, 'message' => 'Mal / Hizmet kaydı başarıyla oluşturuldu.', 'id' => $newId];
            }
            return ['success' => false, 'message' => 'Kayıt kaydedilirken bir hata oluştu.'];
        }
    }

    /**
     * Soft delete
     */
    public function deleteItem(int $id, int $firmId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), is_active = 0
            WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
    }

    /**
     * Tüm aktif ürün/hizmetler (Select2 / Autocomplete için)
     */
    public function getAllActive(int $firmId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, tur, stok_kodu, urun_adi, barkod, birim, para_birimi, kdv_orani, alis_fiyati, satis_fiyati, kdv_dahil_mi, tevkifat_kodu, tevkifat_orani, gtip_no, aciklama
            FROM {$this->table}
            WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY urun_adi ASC
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
            SELECT id, tur, stok_kodu, urun_adi, barkod, birim, para_birimi, kdv_orani, alis_fiyati, satis_fiyati, kdv_dahil_mi, tevkifat_kodu, tevkifat_orani, gtip_no, aciklama
            FROM {$this->table}
            WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
              AND (urun_adi LIKE :term OR stok_kodu LIKE :term OR barkod LIKE :term)
            ORDER BY urun_adi ASC
            LIMIT :limit
        ");
        $stmt->bindValue('firm_id', $firmId, PDO::PARAM_INT);
        $stmt->bindValue('term', "%{$term}%", PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
