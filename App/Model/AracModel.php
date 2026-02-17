<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class AracModel extends Model
{
    protected $table = 'araclar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma bazlı tüm araçları getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif araçları getirir
     */
    public function getAktifAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tek araç detayı
     */
    public function getById($id)
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.personel_id as zimmetli_personel_id,
                   az.id as aktif_zimmet_id,
                   p.adi_soyadi as zimmetli_personel_adi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.id = :id 
            AND a.firma_id = :firma_id
        ");
        $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Select2 için araç listesi
     */
    public function getForSelect($search = '')
    {
        $searchTerm = '%' . $search . '%';
        $sql = $this->db->prepare("
            SELECT id, 
                   CONCAT(plaka, ' - ', COALESCE(marka, ''), ' ', COALESCE(model, '')) as text
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND aktif_mi = 1
            AND silinme_tarihi IS NULL
            AND (plaka LIKE :search OR marka LIKE :search OR model LIKE :search)
            ORDER BY plaka
            LIMIT 20
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'search' => $searchTerm
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Araç istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_arac,
                SUM(CASE WHEN aktif_mi = 1 THEN 1 ELSE 0 END) as aktif_arac,
                SUM(CASE WHEN aktif_mi = 0 THEN 1 ELSE 0 END) as pasif_arac
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Zimmetli araç sayısı
     */
    public function getZimmetliAracSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id) as zimmetli_arac
            FROM {$this->table} a
            INNER JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        $result = $sql->fetch(PDO::FETCH_OBJ);
        return $result->zimmetli_arac ?? 0;
    }

    /**
     * Plaka kontrolü
     */
    public function plakaKontrol($plaka, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE plaka = :plaka AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $params = ['plaka' => $plaka, 'firma_id' => $_SESSION['firma_id']];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Araç KM güncelle
     */
    public function updateKm($aracId, $yeniKm)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET guncel_km = :km, guncelleme_tarihi = NOW()
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
            'km' => $yeniKm,
            'id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Yaklaşan muayene/sigorta tarihleri
     */
    public function getYaklasanTarihler($gunSayisi = 30)
    {
        $sql = $this->db->prepare("
            SELECT id, plaka, marka, model,
                   muayene_bitis_tarihi,
                   sigorta_bitis_tarihi,
                   kasko_bitis_tarihi,
                   DATEDIFF(muayene_bitis_tarihi, CURDATE()) as muayene_kalan_gun,
                   DATEDIFF(sigorta_bitis_tarihi, CURDATE()) as sigorta_kalan_gun,
                   DATEDIFF(kasko_bitis_tarihi, CURDATE()) as kasko_kalan_gun
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND aktif_mi = 1
            AND silinme_tarihi IS NULL
            AND (
                (muayene_bitis_tarihi IS NOT NULL AND DATEDIFF(muayene_bitis_tarihi, CURDATE()) <= :gun1 AND DATEDIFF(muayene_bitis_tarihi, CURDATE()) >= 0)
                OR (sigorta_bitis_tarihi IS NOT NULL AND DATEDIFF(sigorta_bitis_tarihi, CURDATE()) <= :gun2 AND DATEDIFF(sigorta_bitis_tarihi, CURDATE()) >= 0)
                OR (kasko_bitis_tarihi IS NOT NULL AND DATEDIFF(kasko_bitis_tarihi, CURDATE()) <= :gun3 AND DATEDIFF(kasko_bitis_tarihi, CURDATE()) >= 0)
            )
            ORDER BY LEAST(
                COALESCE(DATEDIFF(muayene_bitis_tarihi, CURDATE()), 9999),
                COALESCE(DATEDIFF(sigorta_bitis_tarihi, CURDATE()), 9999),
                COALESCE(DATEDIFF(kasko_bitis_tarihi, CURDATE()), 9999)
            ) ASC
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'gun1' => $gunSayisi,
            'gun2' => $gunSayisi,
            'gun3' => $gunSayisi
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
