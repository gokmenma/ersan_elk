<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class TalepModel extends Model
{
    protected $table = 'personel_talepleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin taleplerini getirir
     *
     * @param int $personel_id
     * @return array
     */
    public function getPersonelTalepleri($personel_id)
    {
        $sql = "SELECT * FROM $this->table 
                WHERE personel_id = ? AND silinme_tarihi IS NULL 
                ORDER BY id DESC";

        $query = $this->db->prepare($sql);
        $query->execute([$personel_id]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İstatistikleri getirir
     *
     * @param int $personel_id
     * @return object
     */
    public function getStats($personel_id)
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN durum != 'cozuldu' THEN 1 END) as acik,
                    COUNT(CASE WHEN durum = 'cozuldu' AND MONTH(cozum_tarihi) = MONTH(CURRENT_DATE()) THEN 1 END) as cozulen,
                    AVG(CASE WHEN durum = 'cozuldu' THEN TIMESTAMPDIFF(HOUR, olusturma_tarihi, cozum_tarihi) END) as ort_sure
                FROM $this->table 
                WHERE personel_id = ? AND silinme_tarihi IS NULL";

        $query = $this->db->prepare($sql);
        $query->execute([$personel_id]);
        $result = $query->fetch(PDO::FETCH_OBJ);

        // Format average duration
        $result->ort_sure = $result->ort_sure ? round($result->ort_sure, 1) : 0;

        return $result;
    }

    /**
     * Yeni referans numarası üretir
     */
    public function generateRefNo()
    {
        $prefix = 'TLP-' . date('Ymd') . '-';

        $sql = "SELECT ref_no FROM $this->table WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1";
        $query = $this->db->prepare($sql);
        $query->execute([$prefix . '%']);
        $last = $query->fetch(PDO::FETCH_OBJ);

        if ($last) {
            $num = intval(substr($last->ref_no, -3)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Firma bazında bekleyen talep sayısını getirir
     */
    public function getBekleyenTalepSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM {$this->table} pt 
            JOIN personel p ON pt.personel_id = p.id 
            WHERE pt.durum NOT IN ('cozuldu', 'reddedildi', 'iptal_edildi') AND pt.silinme_tarihi IS NULL AND p.firma_id = ? AND (pt.kategori IS NULL OR pt.kategori != 'nobet_talebi')
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ)->count ?? 0;
    }

    /**
     * Firma bazında bekleyen talep listesini getirir (dashboard için)
     */
    public function getBekleyenTaleplerForDashboard($limit = 5)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT 'Talep' as tip, pt.id, pt.personel_id, pt.olusturma_tarihi as tarih, pt.durum, pt.baslik as detay 
            FROM {$this->table} pt 
            JOIN personel p ON pt.personel_id = p.id 
            WHERE pt.durum NOT IN ('cozuldu', 'reddedildi', 'iptal_edildi') AND pt.silinme_tarihi IS NULL AND p.firma_id = ? AND (pt.kategori IS NULL OR pt.kategori != 'nobet_talebi')
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tüm bekleyen genel talepleri personel bilgileriyle getirir
     */
    public function getButunBekleyenTalepler()
    {
        $sql = $this->db->prepare("
            SELECT pt.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev
            FROM {$this->table} pt 
            JOIN personel p ON pt.personel_id = p.id 
            WHERE pt.durum NOT IN ('cozuldu', 'reddedildi', 'iptal_edildi') AND pt.silinme_tarihi IS NULL AND p.firma_id = ? AND (pt.kategori IS NULL OR pt.kategori != 'nobet_talebi')
            ORDER BY pt.olusturma_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Talebin durumunu günceller
     */
    public function updateDurum($id, $durum, $cozum_aciklama = null)
    {
        $cozum_tarihi = ($durum == 'cozuldu' || $durum == 'iptal_edildi' || $durum == 'reddedildi') ? date('Y-m-d H:i:s') : null;
        $islem_yapan_id = $_SESSION['user_id'] ?? null;

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET durum = ?, cozum_aciklama = ?, cozum_tarihi = ?, islem_yapan_id = ?
            WHERE id = ?
        ");
        return $sql->execute([$durum, $cozum_aciklama, $cozum_tarihi, $islem_yapan_id, $id]);
    }

    /**
     * Talep detayını getirir
     */
    public function getTalepDetay($id)
    {
        $sql = $this->db->prepare("
            SELECT pt.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev, u.adi_soyadi as solver_name
            FROM {$this->table} pt 
            JOIN personel p ON pt.personel_id = p.id 
            LEFT JOIN users u ON pt.islem_yapan_id = u.id
            WHERE pt.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Çözülmüş talepleri personel bilgileriyle getirir
     */
    public function getCozulmusTalepler($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pt.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev,
                   u.adi_soyadi as solver_name
            FROM {$this->table} pt 
            JOIN personel p ON pt.personel_id = p.id 
            LEFT JOIN users u ON pt.islem_yapan_id = u.id
            WHERE pt.durum IN ('cozuldu', 'reddedildi', 'iptal_edildi') AND pt.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pt.islem_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
