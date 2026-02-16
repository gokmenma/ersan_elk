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
        $out['adres'] = (string) ($data['adres'] ?? '');
        $out['firma_unvan'] = (string) ($data['firma_unvan'] ?? '');
        $out['firma_iban'] = (string) ($data['firma_iban'] ?? '');

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

    /** Firma get (id) */
    public function getFirma(int $id)
    {
        return $this->find($id);
    }

    /** Firma sil (soft delete) - ilişkileri kontrol eder */
    public function deleteFirma(int $id)
    {
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
        $query = $this->db->prepare("SELECT id, firma_adi 
                                     FROM $this->table
                                     WHERE FIND_IN_SET(id, (
                                        SELECT firma_ids FROM users WHERE id = :user_id
                                     ))
                                     AND silinme_tarihi IS NULL");
        $query->execute([
            'user_id' => $_SESSION['user']->id
        ]);
        return $query->fetchAll(PDO::FETCH_OBJ);
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