<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AvansModel extends Model
{
    protected $table = 'personel_avanslari';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin avans taleplerini getirir
     */
    public function getPersonelAvanslari($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND silinme_tarihi IS NULL 
            ORDER BY talep_tarihi DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bekleyen avans taleplerini getirir
     */
    public function getBekleyenAvanslar($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Avans limitini hesaplar (Örnek mantık: Maaşın %50'si - Bekleyen/Onaylanan Avanslar)
     */
    public function getAvansLimiti($personel_id)
    {
        // Personel maaşını al
        $personelModel = new PersonelModel();
        $personel = $personelModel->find($personel_id);

        if (!$personel)
            return 0;

        $maas = $personel->maas_tutari ?? 0;
        $limit = $maas * 0.5; // Maaşın yarısı

        // Kullanılan avansları düş
        $sql = $this->db->prepare("
            SELECT SUM(tutar) as toplam FROM {$this->table} 
            WHERE personel_id = ? 
            AND durum IN ('beklemede', 'onaylandi') 
            AND silinme_tarihi IS NULL
            AND MONTH(talep_tarihi) = MONTH(CURRENT_DATE())
            AND YEAR(talep_tarihi) = YEAR(CURRENT_DATE())
        ");
        $sql->execute([$personel_id]);
        $kullanilan = $sql->fetch(PDO::FETCH_OBJ)->toplam ?? 0;

        return max(0, $limit - $kullanilan);
    }

    /**
     * Firma bazında bekleyen avans sayısını getirir
     */
    public function getBekleyenAvansSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.durum = 'beklemede' AND pa.silinme_tarihi IS NULL AND p.firma_id = ?
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ)->count ?? 0;
    }

    /**
     * Firma bazında bekleyen avans listesini getirir (dashboard için)
     */
    public function getBekleyenAvanslarForDashboard($limit = 5)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT 'Avans' as tip, pa.id, pa.personel_id, pa.talep_tarihi as tarih, pa.durum, pa.tutar as detay 
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.durum = 'beklemede' AND pa.silinme_tarihi IS NULL AND p.firma_id = ? 
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tüm bekleyen avans taleplerini personel bilgileriyle getirir
     */
    public function getButunBekleyenAvanslar()
    {
        $sql = $this->db->prepare("
            SELECT pa.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev, p.maas_tutari
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.durum = 'beklemede' AND pa.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pa.talep_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İşlem yapılmış (onaylanmış veya reddedilmiş) avans taleplerini getirir
     */
    public function getIslenmisAvanslar($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pa.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev, p.maas_tutari
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.durum IN ('onaylandi', 'reddedildi') AND pa.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pa.onay_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Reddedilmiş avans taleplerini personel bilgileriyle getirir
     */
    public function getReddedilmisAvanslar($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pa.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev, p.maas_tutari
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.durum = 'reddedildi' AND pa.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pa.onay_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }


    /**
     * Avans durumunu günceller (onay/ret)
     */
    public function updateDurum($id, $durum, $aciklama = null)
    {
        $onay_tarihi = in_array($durum, ['onaylandi', 'reddedildi']) ? date('Y-m-d H:i:s') : null;
        $onaylayan_id = $_SESSION['user_id'] ?? null;

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET durum = ?, onay_aciklama = ?, onay_tarihi = ?, onaylayan_id = ?
            WHERE id = ?
        ");
        return $sql->execute([$durum, $aciklama, $onay_tarihi, $onaylayan_id, $id]);
    }

    /**
     * Avans detayını getirir
     */
    public function getAvansDetay($id)
    {
        $sql = $this->db->prepare("
            SELECT pa.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev, p.maas_tutari
            FROM {$this->table} pa 
            JOIN personel p ON pa.personel_id = p.id 
            WHERE pa.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Onaylanan avansı personel hesabına işler (kesinti olarak ekler)
     */
    public function avansHesabaIsle($avans_id, $donem_id = null)
    {
        $avans = $this->find($avans_id);
        if (!$avans) {
            return false;
        }

        $kayitYapan = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;

        // Eğer dönem ID verilmediyse talep tarihini kapsayan dönemi bul
        if (!$donem_id) {
            $talepTarihi = !empty($avans->talep_tarihi)
                ? date('Y-m-d', strtotime($avans->talep_tarihi))
                : date('Y-m-d');

            $donemSql = $this->db->prepare("
                SELECT id FROM bordro_donemi 
                WHERE baslangic_tarihi <= ? AND bitis_tarihi >= ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $donemSql->execute([$talepTarihi, $talepTarihi]);
            $donem = $donemSql->fetch(PDO::FETCH_OBJ);
            $donem_id = $donem ? (int) $donem->id : 0;
        }

        // Dönem bulunamazsa 0 olarak kaydet
        $donem_id = (int) ($donem_id ?: 0);

        $sql = $this->db->prepare("
            INSERT INTO personel_kesintileri (personel_id, donem_id, tur, aciklama, tutar, kayit_yapan, olusturma_tarihi, durum)
            VALUES (?, ?, 'avans', ?, ?, ?, NOW(), 'onaylandi')
        ");
        $aciklama = 'Avans - ' . date('d.m.Y', strtotime($avans->talep_tarihi));
        return $sql->execute([$avans->personel_id, $donem_id, $aciklama, $avans->tutar, $kayitYapan]);
    }
}
