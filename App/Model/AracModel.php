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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
     * Serviste olan ve aktif zimmeti bulunan araç sayısı
     */
    public function getZimmetliServistekiAracSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id) as sayi
            FROM {$this->table} a
            INNER JOIN arac_servis_kayitlari s ON a.id = s.arac_id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL
            INNER JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            WHERE a.firma_id = :firma_id
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return (int) ($sql->fetch(PDO::FETCH_OBJ)->sayi ?? 0);
    }

    /**
     * Aktif araçları getirir
     */
    public function getAktifAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 1
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
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
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
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
                SUM(CASE WHEN ikame_mi = 1 AND EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.ikame_arac_id = araclar.id AND s.ikame_iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) THEN 1 ELSE 0 END) as ikame_arac
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
        $sql = $this->db->prepare("SELECT COUNT(*) FROM araclar a WHERE a.firma_id = :firma_id AND a.silinme_tarihi IS NULL AND a.ikame_mi = 1");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchColumn();
    }

    /**
     * Araç silme kararı ve audit kaydı için aktif bağlı kayıt adetleri.
     */
    public function getDeleteDependencyCounts(int $aracId): object
    {
        $sql = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM arac_yakit_kayitlari WHERE arac_id = :yakit_arac_id AND firma_id = :yakit_firma_id AND silinme_tarihi IS NULL) AS yakit_sayisi,
                (SELECT COUNT(*) FROM arac_km_kayitlari WHERE arac_id = :km_arac_id AND firma_id = :km_firma_id AND silinme_tarihi IS NULL) AS km_sayisi,
                (SELECT COUNT(*) FROM arac_zimmetleri WHERE arac_id = :zimmet_arac_id AND firma_id = :zimmet_firma_id AND silinme_tarihi IS NULL) AS zimmet_sayisi,
                (SELECT COUNT(*) FROM arac_servis_kayitlari WHERE arac_id = :servis_arac_id AND firma_id = :servis_firma_id AND silinme_tarihi IS NULL) AS servis_sayisi,
                (SELECT COUNT(*) FROM arac_sigorta_kayitlari WHERE arac_id = :sigorta_arac_id AND firma_id = :sigorta_firma_id AND silinme_tarihi IS NULL) AS sigorta_sayisi,
                (SELECT COUNT(*) FROM arac_bakim_kayitlari WHERE arac_id = :bakim_arac_id AND firma_id = :bakim_firma_id AND silinme_tarihi IS NULL) AS bakim_sayisi
        ");
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $sql->execute([
            'yakit_arac_id' => $aracId,
            'yakit_firma_id' => $firmaId,
            'km_arac_id' => $aracId,
            'km_firma_id' => $firmaId,
            'zimmet_arac_id' => $aracId,
            'zimmet_firma_id' => $firmaId,
            'servis_arac_id' => $aracId,
            'servis_firma_id' => $firmaId,
            'sigorta_arac_id' => $aracId,
            'sigorta_firma_id' => $firmaId,
            'bakim_arac_id' => $aracId,
            'bakim_firma_id' => $firmaId,
        ]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: (object) [];
    }

    public function getKayitBilgileri(array $aracIdleri): array
    {
        $aracIdleri = array_values(array_unique(array_filter(array_map('intval', $aracIdleri))));
        if (!$aracIdleri) {
            return [];
        }

        $yerTutucular = implode(',', array_fill(0, count($aracIdleri), '?'));
        $sql = $this->db->prepare("
            SELECT a.id, a.olusturma_tarihi, a.guncelleme_tarihi,
                   uo.adi_soyadi AS olusturan_kullanici_adi,
                   ug.adi_soyadi AS guncelleyen_kullanici_adi
            FROM {$this->table} a
            LEFT JOIN users uo ON uo.id = a.olusturan_kullanici_id
            LEFT JOIN users ug ON ug.id = a.guncelleyen_kullanici_id
            WHERE a.firma_id = ? AND a.id IN ({$yerTutucular})
        ");
        $sql->execute(array_merge([(int) ($_SESSION['firma_id'] ?? 0)], $aracIdleri));

        $sonuc = [];
        foreach ($sql->fetchAll(PDO::FETCH_OBJ) as $satir) {
            $sonuc[(int) $satir->id] = $satir;
        }
        return $sonuc;
    }

    /**
     * Boşta olan araçları getirir
     */
    public function getBostaAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   NULL as zimmetli_personel_id,
                   NULL as zimmetli_personel_adi,
                   NULL as zimmetli_personel_departman,
                   NULL as zimmetli_personel_resim,
                   NULL as zimmetli_personel_resim_yolu,
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
        $normalize = function ($p) {
            $p = mb_strtoupper((string) $p, 'UTF-8');
            $p = str_replace(
                ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
                ['I', 'I', 'G', 'U', 'S', 'O', 'C'],
                $p
            );
            $p = preg_replace('/[^A-Z0-9]/', '', $p);
            return ltrim($p, '0');
        };

        $plakaTemiz = $normalize($plaka);

        // Fetch all possible plates for this firm
        $sql = "SELECT id, plaka, aktif_mi, silinme_tarihi FROM {$this->table} WHERE firma_id = :firma_id";
        $params = ['firma_id' => $_SESSION['firma_id']];

        if (!$includeDeleted) {
            $sql .= " AND silinme_tarihi IS NULL";
        }

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $araclar = $stmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($araclar as $arac) {
            if ($normalize($arac->plaka) === $plakaTemiz) {
                return $arac;
            }
        }

        return false;
    }

    /**
     * Araç KM güncelle
     */
    public function updateKm($aracId, $yeniKm)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET guncel_km = GREATEST(COALESCE(guncel_km, 0), :km), guncelleme_tarihi = NOW()
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
            'km' => intval($yeniKm),
            'id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Ruhsat dosyası meta bilgisiyle birlikte aracı getirir (firma bazlı yetki kontrolü dahil)
     */
    public function getRuhsatSahibiArac($aracId)
    {
        $sql = $this->db->prepare("
            SELECT id, firma_id, plaka, ruhsat_dosya_adi, ruhsat_orijinal_ad, ruhsat_mime_tipi, ruhsat_boyutu, ruhsat_yukleme_tarihi
            FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Ruhsat dosyası meta bilgilerini kaydeder
     */
    public function updateRuhsatBilgisi($aracId, $dosyaAdi, $orijinalAd, $mimeTipi, $boyut, $yukleyenId)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET ruhsat_dosya_adi = :dosya_adi,
                ruhsat_orijinal_ad = :orijinal_ad,
                ruhsat_mime_tipi = :mime_tipi,
                ruhsat_boyutu = :boyut,
                ruhsat_yukleme_tarihi = NOW(),
                ruhsat_yukleyen_id = :yukleyen_id
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
            'dosya_adi' => $dosyaAdi,
            'orijinal_ad' => $orijinalAd,
            'mime_tipi' => $mimeTipi,
            'boyut' => $boyut,
            'yukleyen_id' => $yukleyenId,
            'id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    public function updateEvrakBitis($aracId, string $tur, string $tarih)
    {
        $kolonlar = ['sigorta' => 'sigorta_bitis_tarihi', 'kasko' => 'kasko_bitis_tarihi'];
        if (!isset($kolonlar[$tur])) {
            return false;
        }
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET {$kolonlar[$tur]} = :tarih
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
            'tarih' => $tarih,
            'id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }

    /**
     * Ruhsat dosyası meta bilgilerini temizler (dosyanın kendisi çağıran tarafından silinir)
     */
    public function clearRuhsatBilgisi($aracId)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET ruhsat_dosya_adi = NULL,
                ruhsat_orijinal_ad = NULL,
                ruhsat_mime_tipi = NULL,
                ruhsat_boyutu = NULL,
                ruhsat_yukleme_tarihi = NULL,
                ruhsat_yukleyen_id = NULL
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
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
    /**
     * Pasif araçları getirir
     */
    public function getPasifAraclar()
    {
        $sql = $this->db->prepare("
            SELECT a.*, 
                   az.id as zimmet_id,
                   az.zimmet_tarihi as zimmet_tarihi,
                   az.personel_id as zimmetli_personel_id,
                   p.adi_soyadi as zimmetli_personel_adi,
                   p.departman as zimmetli_personel_departman,
                   p.resim_yolu as zimmetli_personel_resim,
                   p.personel_resim_yolu as zimmetli_personel_resim_yolu,
                   (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
            FROM {$this->table} a
            LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id 
            AND a.aktif_mi = 0
            AND a.silinme_tarihi IS NULL
            AND a.ikame_mi = 0
            ORDER BY a.plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Benzersiz departman listesini getirir
     */
    public function getDepartmanlar()
    {
        $sql = $this->db->prepare("
            SELECT DISTINCT departmani 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND departmani IS NOT NULL 
            AND departmani != '' 
            AND silinme_tarihi IS NULL 
            ORDER BY departmani ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_COLUMN);
    }

    private function listeKolonIfadeleri(): array
    {
        return [
            1 => ['tip', "CASE a.arac_tipi WHEN 'binek' THEN 'Binek' WHEN 'kamyonet' THEN 'Kamyonet' WHEN 'kamyon' THEN 'Kamyon' WHEN 'minibus' THEN 'Minibüs' WHEN 'otobus' THEN 'Otobüs' WHEN 'motosiklet' THEN 'Motosiklet' WHEN 'diger' THEN 'Diğer' WHEN 'ikame' THEN 'İkame' ELSE '-' END", 'text'],
            2 => ['plaka', "CONCAT_WS(' ', a.plaka, a.marka, a.model)", 'text'],
            3 => ['departmani', 'a.departmani', 'text'],
            4 => ['mulkiyet', 'a.mulkiyet', 'text'],
            5 => ['zimmetli', 'p.adi_soyadi', 'text'],
            6 => ['zimmet_tarihi', 'az.zimmet_tarihi', 'date'],
            7 => ['durum', "CASE WHEN EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) THEN 'Serviste' WHEN a.aktif_mi = 1 THEN 'Aktif' ELSE 'Pasif' END", 'text'],
            8 => ['yakit', "CASE a.yakit_tipi WHEN 'benzin' THEN 'Benzin' WHEN 'dizel' THEN 'Dizel' WHEN 'lpg' THEN 'LPG' WHEN 'elektrik' THEN 'Elektrik' WHEN 'hibrit' THEN 'Hibrit' ELSE '-' END", 'text'],
            9 => ['guncel_km', 'a.guncel_km', 'number'],
            10 => ['muayene', 'a.muayene_bitis_tarihi', 'date'],
            11 => ['sigorta', 'a.sigorta_bitis_tarihi', 'date'],
            12 => ['kasko', 'a.kasko_bitis_tarihi', 'date'],
        ];
    }

    private function listeFiltreKosulu(?string $filter): string
    {
        $aktifIkameDegil = "a.aktif_mi = 1 AND a.ikame_mi = 0";
        $aktif = "a.aktif_mi = 1";
        switch ($filter) {
            case 'muayene':
                return "$aktif AND a.muayene_bitis_tarihi < CURDATE()";
            case 'muayene_yaklasan':
                return "$aktif AND a.muayene_bitis_tarihi >= CURDATE() AND DATEDIFF(a.muayene_bitis_tarihi, CURDATE()) <= 30";
            case 'sigorta':
                return "$aktif AND a.sigorta_bitis_tarihi < CURDATE()";
            case 'sigorta_yaklasan':
                return "$aktif AND a.sigorta_bitis_tarihi >= CURDATE() AND DATEDIFF(a.sigorta_bitis_tarihi, CURDATE()) <= 30";
            case 'kasko':
                return "$aktif AND a.kasko_bitis_tarihi < CURDATE()";
            case 'kasko_yaklasan':
                return "$aktif AND a.kasko_bitis_tarihi >= CURDATE() AND DATEDIFF(a.kasko_bitis_tarihi, CURDATE()) <= 30";
            case 'zimmetli':
                return "az.id IS NOT NULL";
            case 'bosta':
                return "$aktif AND az.id IS NULL AND NOT EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL)";
            case 'serviste':
                return "a.ikame_mi = 0 AND EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL)";
            case 'ikame':
                return "a.ikame_mi = 1";
            case 'tumu':
                return "a.ikame_mi = 0";
            case 'pasif':
                return "a.aktif_mi = 0 AND a.ikame_mi = 0";
            default:
                return $aktifIkameDegil;
        }
    }

    private function listeFromSql(): string
    {
        return "FROM {$this->table} a
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) AS max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            LEFT JOIN users uo ON uo.id = a.olusturan_kullanici_id";
    }

    private function listeSutunKosulu(string $ifade, string $tip, string $deger, string $prm, array &$bind): string
    {
        $deger = trim($deger);
        if ($deger === '') {
            return '';
        }

        if (strpos($deger, ':') === false) {
            $bind[$prm] = "%$deger%";
            return $tip === 'date'
                ? " AND DATE_FORMAT($ifade, '%d.%m.%Y') LIKE :$prm"
                : " AND $ifade LIKE :$prm";
        }

        [$mod, $icerik] = explode(':', $deger, 2);
        $parcalar = explode('|', $icerik);
        $ilk = $parcalar[0];

        if ($mod === 'null') {
            return " AND ($ifade IS NULL OR $ifade = '' OR $ifade = 0)";
        }
        if ($mod === 'not_null') {
            return " AND ($ifade IS NOT NULL AND $ifade != '' AND $ifade != 0)";
        }

        if ($tip === 'date') {
            $tarih = static function ($v) {
                $d = \DateTime::createFromFormat('d.m.Y', trim((string) $v));
                return $d ? $d->format('Y-m-d') : null;
            };
            $t1 = $tarih($ilk);
            if ($t1 === null) {
                return '';
            }
            switch ($mod) {
                case 'before':
                    $bind[$prm] = $t1;
                    return " AND $ifade <= :$prm";
                case 'after':
                    $bind[$prm] = $t1;
                    return " AND $ifade >= :$prm";
                case 'between':
                    $t2 = $tarih($parcalar[1] ?? '');
                    if ($t2 === null) {
                        return '';
                    }
                    $bind[$prm . '_a'] = $t1;
                    $bind[$prm . '_b'] = $t2;
                    return " AND $ifade BETWEEN :{$prm}_a AND :{$prm}_b";
                default:
                    $bind[$prm] = $t1;
                    return " AND $ifade = :$prm";
            }
        }

        if ($mod === 'multi') {
            $kosullar = [];
            foreach ($parcalar as $i => $v) {
                $vp = $prm . '_m' . $i;
                if ($tip === 'text') {
                    $kosullar[] = "$ifade = :$vp";
                    $bind[$vp] = $v;
                } else {
                    $kosullar[] = "$ifade LIKE :$vp";
                    $bind[$vp] = "%$v%";
                }
            }
            return $kosullar ? " AND (" . implode(' OR ', $kosullar) . ")" : '';
        }

        $sayi = str_replace(['.', ','], ['', '.'], $ilk);
        $sayisal = is_numeric($sayi) ? (float) $sayi : null;

        switch ($mod) {
            case 'greater_than':
            case 'less_than':
            case 'greater_equal':
            case 'less_equal':
                $op = ['greater_than' => '>', 'less_than' => '<', 'greater_equal' => '>=', 'less_equal' => '<='][$mod];
                $bind[$prm] = $sayisal ?? 0;
                return " AND $ifade $op :$prm";
            case 'equals':
            case 'not_equals':
                $op = $mod === 'equals' ? '=' : '!=';
                $bind[$prm] = ($tip === 'number' && $sayisal !== null) ? $sayisal : $ilk;
                return " AND $ifade $op :$prm";
            case 'starts_with':
                $bind[$prm] = "$ilk%";
                return " AND $ifade LIKE :$prm";
            case 'ends_with':
                $bind[$prm] = "%$ilk";
                return " AND $ifade LIKE :$prm";
            case 'not_contains':
                $bind[$prm] = "%$ilk%";
                return " AND $ifade NOT LIKE :$prm";
            default:
                $bind[$prm] = "%$ilk%";
                return " AND $ifade LIKE :$prm";
        }
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $kolonlar = $this->listeKolonIfadeleri();
        $bind = ['firma_id' => $firma_id];
        $where = "a.firma_id = :firma_id AND a.silinme_tarihi IS NULL AND (" . $this->listeFiltreKosulu($params['filter'] ?? null) . ")";
        $from = $this->listeFromSql();

        $toplamStmt = $this->db->prepare("SELECT COUNT(*) $from WHERE $where");
        $toplamStmt->execute($bind);
        $toplam = (int) $toplamStmt->fetchColumn();

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (a.plaka LIKE :arama OR a.marka LIKE :arama OR a.model LIKE :arama OR a.departmani LIKE :arama OR a.mulkiyet LIKE :arama OR p.adi_soyadi LIKE :arama)";
            $bind['arama'] = "%$arama%";
        }

        foreach (($params['columns'] ?? []) as $i => $kolon) {
            $i = (int) $i;
            $deger = (string) ($kolon['search']['value'] ?? '');
            if ($deger === '' || !isset($kolonlar[$i])) {
                continue;
            }
            $where .= $this->listeSutunKosulu($kolonlar[$i][1], $kolonlar[$i][2], $deger, 'col_' . $i, $bind);
        }

        $filtreliStmt = $this->db->prepare("SELECT COUNT(*) $from WHERE $where");
        $filtreliStmt->execute($bind);
        $filtreli = (int) $filtreliStmt->fetchColumn();

        $siralama = [];
        foreach (($params['order'] ?? []) as $o) {
            $idx = (int) ($o['column'] ?? -1);
            if (isset($kolonlar[$idx])) {
                $yon = strtolower($o['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                $siralama[] = $kolonlar[$idx][1] . " $yon";
            }
        }
        $siralama[] = 'a.plaka ASC';

        $baslangic = max(0, (int) ($params['start'] ?? 0));
        $uzunluk = (int) ($params['length'] ?? 25);
        $limit = $uzunluk > 0 ? " LIMIT :baslangic, :uzunluk" : '';

        $stmt = $this->db->prepare("
            SELECT a.*,
                   az.id AS zimmet_id,
                   az.zimmet_tarihi AS zimmet_tarihi,
                   az.personel_id AS zimmetli_personel_id,
                   p.adi_soyadi AS zimmetli_personel_adi,
                   p.departman AS zimmetli_personel_departman,
                   p.resim_yolu AS zimmetli_personel_resim,
                   uo.adi_soyadi AS olusturan_kullanici_adi,
                   EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) AS serviste_mi
            $from
            WHERE $where
            ORDER BY " . implode(', ', $siralama) . $limit);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($limit) {
            $stmt->bindValue('baslangic', $baslangic, PDO::PARAM_INT);
            $stmt->bindValue('uzunluk', $uzunluk, PDO::PARAM_INT);
        }
        $stmt->execute();

        return [
            'draw' => (int) ($params['draw'] ?? 0),
            'recordsTotal' => $toplam,
            'recordsFiltered' => $filtreli,
            'data' => $stmt->fetchAll(PDO::FETCH_OBJ),
            'baslangic' => $baslangic,
        ];
    }

    public function listeBenzersizDegerler(string $kolonAdi, ?string $filter, int $firma_id): array
    {
        foreach ($this->listeKolonIfadeleri() as $kolon) {
            if ($kolon[0] !== $kolonAdi || $kolon[2] === 'date') {
                continue;
            }
            $ifade = $kolon[1];
            $stmt = $this->db->prepare("
                SELECT DISTINCT $ifade AS val
                " . $this->listeFromSql() . "
                WHERE a.firma_id = :firma_id AND a.silinme_tarihi IS NULL AND (" . $this->listeFiltreKosulu($filter) . ")
                AND $ifade IS NOT NULL AND $ifade != '' AND $ifade != '-'
                ORDER BY val ASC");
            $stmt->execute(['firma_id' => $firma_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        return [];
    }
}
