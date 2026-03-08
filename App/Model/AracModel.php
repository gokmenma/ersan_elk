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
                   a.ikame_mi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Serviste olan araçları getirir
     */
    public function getServistekiAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   1 as serviste_mi
            FROM {$this->table} a
            INNER JOIN arac_servis_kayitlari s ON a.id = s.arac_id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Servisteki araç sayısı
     */
    public function getServistekiAracSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id) as servisteki_arac
            FROM {$this->table} a
            INNER JOIN arac_servis_kayitlari s ON a.id = s.arac_id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        $result = $sql->fetch(PDO::FETCH_OBJ);
        return $result->servisteki_arac ?? 0;
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
                SUM(CASE WHEN ikame_mi = 0 THEN 1 ELSE 0 END) as toplam_arac,
                SUM(CASE WHEN aktif_mi = 1 AND ikame_mi = 0 THEN 1 ELSE 0 END) as aktif_arac,
                SUM(CASE WHEN aktif_mi = 0 AND ikame_mi = 0 THEN 1 ELSE 0 END) as pasif_arac,
                (SELECT COUNT(*) FROM araclar a2 
                 LEFT JOIN arac_zimmetleri az ON a2.id = az.arac_id AND az.durum = 'aktif'
                 WHERE a2.firma_id = :firma_id1 
                 AND a2.silinme_tarihi IS NULL 
                 AND a2.aktif_mi = 1
                 AND a2.ikame_mi = 0
                 AND az.id IS NULL
                 AND NOT EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a2.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL)) as bosta_arac,
                SUM(CASE WHEN ikame_mi = 1 AND aktif_mi = 1 THEN 1 ELSE 0 END) as ikame_arac
            FROM {$this->table}
            WHERE firma_id = :firma_id2
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'firma_id1' => $_SESSION['firma_id'],
            'firma_id2' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Zimmetli olan araçları getirir
     */
    public function getZimmetliAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            INNER JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İkame araçları getirir
     */
    public function getIkameAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   a.ikame_mi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 1
            AND a.aktif_mi = 1
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İkame araç sayısını getirir
     */
    public function getIkameAracSayisi()
    {
        $sql = $this->db->prepare("SELECT COUNT(*) FROM araclar WHERE firma_id = :firma_id AND silinme_tarihi IS NULL AND ikame_mi = 1 AND aktif_mi = 1");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchColumn();
    }

    /**
     * Boşta olan araçları getirir
     */
    public function getBostaAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   NULL as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            WHERE a.firma_id = :firma_id 
            AND a.silinme_tarihi IS NULL
            AND a.aktif_mi = 1
            AND az.id IS NULL
            AND NOT EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL)
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
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
    public function plakaKontrol($plaka, $excludeId = null, $includeDeleted = false)
    {
        // Plakayı tamamen temizle: boşluklar, tireler, noktalar gitsin
        $plakaTemiz = strtoupper(preg_replace('/[^A-Z0-9]/', '', $plaka));

        $sql = "SELECT id FROM {$this->table} 
                WHERE UPPER(REPLACE(REPLACE(REPLACE(plaka, ' ', ''), '-', ''), '.', '')) = :plaka 
                AND firma_id = :firma_id";

        if (!$includeDeleted) {
            $sql .= " AND silinme_tarihi IS NULL";
        }

        $params = [
            'plaka' => $plakaTemiz,
            'firma_id' => $_SESSION['firma_id']
        ];

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

    /**
     * Evrak istatistiklerini getirir (Biten ve Yaklaşan)
     */
    public function getAracEvrakStats($gunRange = 30)
    {
        $sql = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN muayene_bitis_tarihi IS NOT NULL AND muayene_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as muayene_biten,
                SUM(CASE WHEN muayene_bitis_tarihi IS NOT NULL AND DATEDIFF(muayene_bitis_tarihi, CURDATE()) <= :g1 AND DATEDIFF(muayene_bitis_tarihi, CURDATE()) >= 0 THEN 1 ELSE 0 END) as muayene_yaklasan,
                
                SUM(CASE WHEN sigorta_bitis_tarihi IS NOT NULL AND sigorta_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as sigorta_biten,
                SUM(CASE WHEN sigorta_bitis_tarihi IS NOT NULL AND DATEDIFF(sigorta_bitis_tarihi, CURDATE()) <= :g2 AND DATEDIFF(sigorta_bitis_tarihi, CURDATE()) >= 0 THEN 1 ELSE 0 END) as sigorta_yaklasan,
                
                SUM(CASE WHEN kasko_bitis_tarihi IS NOT NULL AND kasko_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as kasko_biten,
                SUM(CASE WHEN kasko_bitis_tarihi IS NOT NULL AND DATEDIFF(kasko_bitis_tarihi, CURDATE()) <= :g3 AND DATEDIFF(kasko_bitis_tarihi, CURDATE()) >= 0 THEN 1 ELSE 0 END) as kasko_yaklasan
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND aktif_mi = 1
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'g1' => $gunRange,
            'g2' => $gunRange,
            'g3' => $gunRange
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Muayenesi yaklaşan araçları getirir
     */
    public function getMuayeneYaklasanlar($gunRange = 30)
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.muayene_bitis_tarihi >= CURDATE()
            AND DATEDIFF(a.muayene_bitis_tarihi, CURDATE()) <= :g
            ORDER BY a.muayene_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id'], 'g' => $gunRange]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Sigortası yaklaşan araçları getirir
     */
    public function getSigortaYaklasanlar($gunRange = 30)
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.sigorta_bitis_tarihi >= CURDATE()
            AND DATEDIFF(a.sigorta_bitis_tarihi, CURDATE()) <= :g
            ORDER BY a.sigorta_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id'], 'g' => $gunRange]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kaskosu yaklaşan araçları getirir
     */
    public function getKaskoYaklasanlar($gunRange = 30)
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.kasko_bitis_tarihi >= CURDATE()
            AND DATEDIFF(a.kasko_bitis_tarihi, CURDATE()) <= :g
            ORDER BY a.kasko_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id'], 'g' => $gunRange]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Muayenesi biten araçları getirir
     */
    public function getMuayeneBitenler()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.muayene_bitis_tarihi < CURDATE()
            ORDER BY a.muayene_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Sigortası biten araçları getirir
     */
    public function getSigortaBitenler()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.sigorta_bitis_tarihi < CURDATE()
            ORDER BY a.sigorta_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kaskosu biten araçları getirir
     */
    public function getKaskoBitenler()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   p.adi_soyadi as zimmetli_personel_adi,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.kasko_bitis_tarihi < CURDATE()
            ORDER BY a.kasko_bitis_tarihi ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
