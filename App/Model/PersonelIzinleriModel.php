<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class PersonelIzinleriModel extends Model
{
    protected $table = 'personel_izinleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin izinlerini, onay durumu ve onaylayan bilgileriyle birlikte getirir.
     *
     * @param int $personel_id
     * @return array
     */
    public function getPersonelIzinleri($personel_id)
    {
        $sql = "SELECT 
                    pi.*,
                    io.id as onay_kayit_id,
                    io.onay_durumu as onay_durumu_text,
                    io.onay_tarihi,
                    io.aciklama as onay_aciklama,
                    u.adi_soyadi as onaylayan_adi_soyadi
                FROM $this->table as pi
                LEFT JOIN izin_onaylari as io ON io.izin_id = pi.id
                LEFT JOIN users as u ON u.id = io.onaylayan_id
                WHERE pi.personel_id = ?
                ORDER BY pi.id DESC, io.id ASC";

        $query = $this->db->prepare($sql);
        $query->execute([$personel_id]);
        $rows = $query->fetchAll(PDO::FETCH_OBJ);

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->id])) {
                $row->onaylar = [];
                $result[$row->id] = $row;
            }

            if ($row->onay_kayit_id) {
                $onay = new \stdClass();
                $onay->adi = $row->onaylayan_adi_soyadi;
                $onay->tarih = $row->onay_tarihi;
                $onay->durum = $row->onay_durumu_text;
                $onay->aciklama = $row->onay_aciklama;

                $result[$row->id]->onaylar[] = $onay;
                $result[$row->id]->son_durum = $row->onay_durumu_text;
            }
        }

        return array_values($result);
    }

    /**
     * Firma bazında bekleyen izin sayısını getirir
     */
    public function getBekleyenIzinSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.onay_durumu = 'beklemede' AND p.firma_id = ?
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ)->count ?? 0;
    }

    /**
     * Firma bazında bekleyen izin listesini getirir (dashboard için)
     */
    public function getBekleyenIzinlerForDashboard($limit = 5)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT 'İzin' as tip, pi.id, pi.personel_id, pi.talep_tarihi as tarih, pi.onay_durumu as durum, pi.izin_tipi as detay 
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.onay_durumu = 'beklemede' AND p.firma_id = ? 
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Şu anda izindeki personelleri getirir
     */
    public function getAktifIzinler($limit = 10)
    {
        $limit = (int) $limit;
        $today = date('Y-m-d');
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.departman 
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ? AND pi.onay_durumu = 'Onaylandı' AND p.firma_id = ?
            ORDER BY pi.bitis_tarihi ASC
            LIMIT {$limit}
        ");
        $sql->execute([$today, $today, $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tüm bekleyen izin taleplerini personel bilgileriyle getirir
     */
    public function getButunBekleyenIzinler()
    {
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.onay_durumu = 'beklemede' AND p.firma_id = ?
            ORDER BY pi.talep_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Onaylanmış izin taleplerini personel bilgileriyle getirir
     */
    public function getOnaylanmisIzinler($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.onay_durumu = 'Onaylandı' AND p.firma_id = ?
            ORDER BY pi.talep_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İzin durumunu günceller (onay/ret)
     */
    public function updateDurum($id, $durum, $aciklama = null)
    {
        $onay_tarihi = in_array($durum, ['Onaylandı', 'Reddedildi']) ? date('Y-m-d H:i:s') : null;
        $onaylayan_id = $_SESSION['user_id'] ?? null;

        if ($onay_tarihi) {
            $this->addOnayKaydi($id, $onaylayan_id, $durum, $aciklama);
        }

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET onay_durumu = ?
            WHERE id = ?
        ");
        return $sql->execute([$durum, $id]);
    }

    /**
     * İzin onay kaydı ekler
     */
    private function addOnayKaydi($izin_id, $onaylayan_id, $durum, $aciklama)
    {
        $sql = $this->db->prepare("
            INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, aciklama)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        return $sql->execute([$izin_id, $onaylayan_id, $durum, $aciklama]);
    }

    /**
     * İzin detayını getirir
     */
    public function getIzinDetay($id)
    {
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.departman, p.gorev
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * İzin gün sayısını hesaplar
     */
    public function hesaplaIzinGunu($baslangic, $bitis)
    {
        $start = new \DateTime($baslangic);
        $end = new \DateTime($bitis);
        return $start->diff($end)->days + 1;
    }
}