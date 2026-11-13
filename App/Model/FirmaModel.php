<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class FirmaModel extends Model
{
    protected $table = 'firmalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**Tüm aktif firmaları getirir */
    public function all()
    {
        $query = $this->db->prepare("Select * from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);

    }

    /** Silinmemiş firmaları getirir */
    public function listActive()
    {
        $query = $this->db->prepare("SELECT * FROM $this->table WHERE silinme_tarihi IS NULL");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Cookie veya parametrelerden varsayılan firmayı çözer.
     * @return object|null aktif firma objesi veya null
     */
    public function resolveDefaultFirmaFromCookies(array $cookies, array $allFirms)
    {
        $varsayilan_firma_id = 0;
        $varsayilan_firma_kodu = null;

        if (isset($cookies['varsayilan_firma_id']) && !empty($cookies['varsayilan_firma_id'])) {
            $varsayilan_firma_id = (int) $cookies['varsayilan_firma_id'];
        } elseif (isset($cookies['varsayilan_firma_kodu']) && !empty($cookies['varsayilan_firma_kodu'])) {
            $varsayilan_firma_kodu = (string) $cookies['varsayilan_firma_kodu'];
        }

        if ($varsayilan_firma_id <= 0 && empty($varsayilan_firma_kodu)) {
            return null;
        }

        foreach ($allFirms as $firm) {
            $matchesId = ($varsayilan_firma_id > 0 && isset($firm->id) && (int) $firm->id === $varsayilan_firma_id);
            $matchesCode = (!empty($varsayilan_firma_kodu) && isset($firm->firma_kodu) && $firm->firma_kodu == $varsayilan_firma_kodu);
            if (($matchesId || $matchesCode) && (!isset($firm->silinme_tarihi) || is_null($firm->silinme_tarihi))) {
                return $firm;
            }
        }

        return null;
    }

    /**
     * Firma kaydetme için minimal normalize/temizleme.
     * Not: Bu metod DB unique/constraint kontrollerini değiştirmez.
     */
    public function normalizeSaveData(array $data)
    {
        $out = [];
        $out['firma_adi'] = trim((string) ($data['firma_adi'] ?? ''));
        $out['firma_kodu'] = isset($data['firma_kodu']) ? trim((string) $data['firma_kodu']) : null;
        if ($out['firma_kodu'] === '') {
            $out['firma_kodu'] = null;
        }
        $out['vergi_no'] = (string) ($data['vergi_no'] ?? '');
        $out['vergi_dairesi'] = (string) ($data['vergi_dairesi'] ?? '');
        $out['telefon'] = (string) ($data['telefon'] ?? '');
        $out['web_sitesi'] = (string) ($data['web_sitesi'] ?? '');
        $out['kep_adresi'] = (string) ($data['kep_adresi'] ?? '');
        $out['adres'] = (string) ($data['adres'] ?? '');
        $out['vergi_dairesi_no'] = (string) ($data['vergi_dairesi_no'] ?? '');
        $out['mersis_no'] = (string) ($data['mersis_no'] ?? '');
        $out['ticaret_sicil_no'] = (string) ($data['ticaret_sicil_no'] ?? '');
        $out['firma_unvan'] = (string) ($data['firma_unvan'] ?? '');
        if (isset($data['logo_yolu'])) {
            $out['logo_yolu'] = (string) $data['logo_yolu'];
        }
        $out['firma_iban'] = (string) ($data['firma_iban'] ?? '');
        foreach (['il', 'ilce', 'ulke', 'email', 'fax'] as $ekAlan) {
            if (array_key_exists($ekAlan, $data)) {
                $out[$ekAlan] = trim((string) $data[$ekAlan]);
            }
        }

        if (isset($data['id']) && (int) $data['id'] > 0) {
            $out['id'] = (int) $data['id'];
        }
        if (isset($data['kayit_yapan'])) {
            $out['kayit_yapan'] = (int) $data['kayit_yapan'];
        }

        return $out;
    }

    /** Firma kaydeder (create/update) */
    public function saveFirma(array $data)
    {
        $normalized = $this->normalizeSaveData($data);
        if (empty($normalized['firma_adi'])) {
            throw new \Exception('Firma adı boş bırakılamaz.');
        }

        return $this->saveWithAttr($normalized);
    }

    /** Kullanıcının erişebileceği firma kimlikleri; null tüm firmalar anlamına gelir */
    public function izinliFirmaIdleri(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT firma_ids FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $raw = trim((string) $stmt->fetchColumn());
        if ($raw === '') {
            return null;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0)));
        return $ids === [] ? null : $ids;
    }

    private function yonetimKapsami(?array $izinliIds, string $alias = 'f'): array
    {
        $where = "{$alias}.silinme_tarihi IS NULL";
        $bind = [];
        if ($izinliIds !== null) {
            $ph = [];
            foreach ($izinliIds as $i => $id) {
                $ph[] = ":izin_{$i}";
                $bind["izin_{$i}"] = (int) $id;
            }
            $where .= $ph === [] ? " AND 1 = 0" : " AND {$alias}.id IN (" . implode(',', $ph) . ")";
        }
        return [$where, $bind];
    }

    private function eksikBilgiSql(): string
    {
        $bos = fn(string $c) => "(f.{$c} IS NULL OR TRIM(f.{$c}) = '' OR TRIM(f.{$c}) = '0')";
        return implode(' OR ', array_map($bos, ['vergi_no', 'vergi_dairesi', 'telefon', 'adres', 'email', 'il', 'ilce']));
    }

    public function getForYonetim(int $id, ?array $izinliIds): ?array
    {
        [$where, $bind] = $this->yonetimKapsami($izinliIds);
        $stmt = $this->db->prepare("SELECT f.* FROM {$this->table} f WHERE {$where} AND f.id = :id LIMIT 1");
        $stmt->execute($bind + ['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ajaxList(array $params, ?array $izinliIds): array
    {
        $draw = (int) ($params['draw'] ?? 1);
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $length = $length < 1 ? 25 : min($length, 500);
        $search = trim((string) ($params['search']['value'] ?? ''));

        [$scope, $scopeBind] = $this->yonetimKapsami($izinliIds);
        $where = $scope;
        $bind = $scopeBind;

        $durum = (string) ($params['status_filter'] ?? 'all');
        if ($durum === 'varsayilan') {
            $where .= ' AND f.varsayilan_mi = 1';
        } elseif ($durum === 'eksik') {
            $where .= ' AND (' . $this->eksikBilgiSql() . ')';
        } elseif ($durum === 'tam') {
            $where .= ' AND NOT (' . $this->eksikBilgiSql() . ')';
        }

        if ($search !== '') {
            $where .= ' AND (f.firma_adi LIKE :search OR f.firma_unvan LIKE :search OR f.vergi_no LIKE :search OR f.telefon LIKE :search OR f.email LIKE :search OR f.firma_kodu LIKE :search)';
            $bind['search'] = "%{$search}%";
        }

        $colMap = [
            0 => 'f.firma_adi',
            1 => 'f.firma_unvan',
            2 => 'f.vergi_no',
            3 => 'f.vergi_dairesi',
            4 => 'f.telefon',
            5 => 'f.email',
            6 => 'f.il',
        ];
        foreach (($params['columns'] ?? []) as $i => $column) {
            $val = trim((string) ($column['search']['value'] ?? ''));
            if ($val === '' || !isset($colMap[(int) $i])) {
                continue;
            }
            if (strpos($val, ':') !== false) {
                [, $val] = explode(':', $val, 2);
                $val = trim(explode('|', $val)[0]);
                if ($val === '') {
                    continue;
                }
            }
            $name = 'col_' . (int) $i;
            $where .= " AND {$colMap[(int) $i]} LIKE :{$name}";
            $bind[$name] = "%{$val}%";
        }

        $total = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} f WHERE {$scope}");
        $total->execute($scopeBind);
        $recordsTotal = (int) $total->fetchColumn();

        $filtered = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} f WHERE {$where}");
        $filtered->execute($bind);
        $recordsFiltered = (int) $filtered->fetchColumn();

        $orderIdx = (int) ($params['order'][0]['column'] ?? 0);
        $orderDir = strtoupper((string) ($params['order'][0]['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = $colMap[$orderIdx] ?? 'f.firma_adi';

        $stmt = $this->db->prepare(
            "SELECT f.id, f.firma_adi, f.firma_kodu, f.firma_unvan, f.vergi_no, f.vergi_dairesi, f.telefon,
                    f.email, f.il, f.ilce, f.adres, f.varsayilan_mi, f.logo_yolu, f.firma_iban
             FROM {$this->table} f WHERE {$where} ORDER BY {$orderBy} {$orderDir}, f.id ASC LIMIT :start, :length"
        );
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('start', $start, PDO::PARAM_INT);
        $stmt->bindValue('length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $data = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['enc_id'] = Security::encrypt((string) $row['id']);
            unset($row['id']);
            $data[] = $row;
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    public function summary(?array $izinliIds): object
    {
        [$scope, $bind] = $this->yonetimKapsami($izinliIds);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS toplam,
                    SUM(CASE WHEN f.varsayilan_mi = 1 THEN 1 ELSE 0 END) AS varsayilan,
                    SUM(CASE WHEN (" . $this->eksikBilgiSql() . ") THEN 1 ELSE 0 END) AS eksik,
                    SUM(CASE WHEN f.logo_yolu IS NOT NULL AND f.logo_yolu <> '' THEN 1 ELSE 0 END) AS logolu
             FROM {$this->table} f WHERE {$scope}"
        );
        $stmt->execute($bind);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $toplam = (int) ($row['toplam'] ?? 0);
        $eksik = (int) ($row['eksik'] ?? 0);

        return (object) [
            'toplam' => $toplam,
            'varsayilan' => (int) ($row['varsayilan'] ?? 0),
            'eksik' => $eksik,
            'tam' => $toplam - $eksik,
            'logolu' => (int) ($row['logolu'] ?? 0),
        ];
    }

    /** Firma get (id) */
    public function getFirma($id)
    {
        if ($id === 'null' || empty($id)) return null;
        return $this->find((int)$id);
    }

    /** Firma sil (soft delete) - ilişkileri kontrol eder */
    public function deleteFirma($id)
    {
        if ($id === 'null' || empty($id)) return false;
        $id = (int)$id;
        $error = $this->hasRelations($id);
        if ($error) {
            throw new \Exception($error);
        }

        return $this->softDelete($id);
    }

    /**firmaları id ve firma adı olarak option döndürür */
    public function option()
    {
        $query = $this->db->prepare("Select id, firma_adi from $this->table 
                                where silinme_tarihi is null");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
    /**firmaları id ve firma adı olarak option döndürür */
    public function optionByUserPermission()
    {
        $userId = $_SESSION['user']->id ?? 0;
        if (!$userId) {
            return [];
        }

        $userQuery = $this->db->prepare("SELECT firma_ids FROM users WHERE id = :user_id");
        $userQuery->execute(['user_id' => $userId]);
        $userRow = $userQuery->fetch(PDO::FETCH_OBJ);

        $rawFirmaIds = trim((string) ($userRow->firma_ids ?? ''));
        if ($rawFirmaIds !== '') {
            $allowedFirmaIds = array_map('intval', array_filter(array_map('trim', explode(',', $rawFirmaIds)), 'strlen'));
            if (!empty($allowedFirmaIds)) {
                $placeholders = implode(',', array_fill(0, count($allowedFirmaIds), '?'));
                $query = $this->db->prepare("SELECT id, firma_adi FROM $this->table WHERE id IN ($placeholders) AND silinme_tarihi IS NULL");
                $query->execute($allowedFirmaIds);
                return $query->fetchAll(PDO::FETCH_OBJ);
            }
        }

        return $this->getFirmaList();
    }

    /**Firma Listesi */
    public function getFirmaList()
    {
        $query = $this->db->prepare("Select id, firma_adi from $this->table where silinme_tarihi is null");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**Seçilen firmayı varsayılan yapar, diğerlerini sıfırlar */
    public function setDefault($id)
    {
        $query = $this->db->prepare("UPDATE $this->table SET varsayilan_mi = 0");
        $query->execute();

        $query = $this->db->prepare("UPDATE $this->table SET varsayilan_mi = 1 WHERE id = :id");
        return $query->execute(['id' => $id]);
    }

    /** Firmanın personeli veya yetkili kullanıcısı var mı kontrol eder */
    public function hasRelations($id)
    {
        // Personel kontrolü
        $query = $this->db->prepare("SELECT COUNT(*) FROM personel WHERE firma_id = :id AND silinme_tarihi IS NULL");
        $query->execute(['id' => $id]);
        if ($query->fetchColumn() > 0) {
            return "Bu firmaya kayıtlı personel bulunmaktadır. Silme işlemi yapılamaz.";
        }

        // Kullanıcı yetki kontrolü
        $query = $this->db->prepare("SELECT COUNT(*) FROM users WHERE FIND_IN_SET(:id, firma_ids)");
        $query->execute(['id' => $id]);
        if ($query->fetchColumn() > 0) {
            return "Bu firmada yetkili kullanıcılar bulunmaktadır. Silme işlemi yapılamaz.";
        }

        return false;
    }

}
