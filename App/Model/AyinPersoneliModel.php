<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AyinPersoneliModel extends Model
{
    protected $table = 'ayin_personeli';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getWinnerForMonth($donem, $firma_id)
    {
        $sql = "SELECT ap.*, p.adi_soyadi, p.resim_yolu, p.departman, h.baslik as hediye_adi, h.icon as hediye_icon, h.renk as hediye_renk
                FROM {$this->table} ap
                JOIN personel p ON ap.personel_id = p.id
                LEFT JOIN ayin_personeli_hediyeler h ON ap.hediye_id = h.id
                WHERE ap.donem = :donem AND ap.firma_id = :firma_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['donem' => $donem, 'firma_id' => $firma_id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getTopCandidates($donem, $firma_id, $limit = 5)
    {
        // This is a placeholder for actual scoring logic
        // In a real app, you would join with activity/performance tables
        $sql = "SELECT p.id, p.adi_soyadi, p.resim_yolu, p.departman,
                (SELECT COUNT(*) FROM destek_biletleri WHERE ekleyen_id = p.id AND durum = 'Çözüldü' AND MONTH(olusturma_tarihi) = :month AND YEAR(olusturma_tarihi) = :year) as bilet_skoru,
                (SELECT COUNT(*) FROM gorevler WHERE sorumlu_id = p.id AND durum = 'Tamamlandı' AND MONTH(olusturma_tarihi) = :month AND YEAR(olusturma_tarihi) = :year) as gorev_skoru,
                (SELECT COUNT(*) FROM puantaj WHERE personel_id = p.id AND MONTH(tarih) = :month AND YEAR(tarih) = :year) as devam_skoru
                FROM personel p
                WHERE p.firma_id = :firma_id AND p.aktif_mi = 1
                ORDER BY (bilet_skoru + gorev_skoru + devam_skoru) DESC
                LIMIT :limit";
        
        $yearMonth = explode('-', $donem);
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':month', (int)$yearMonth[1], PDO::PARAM_INT);
        $stmt->bindValue(':year', (int)$yearMonth[0], PDO::PARAM_INT);
        $stmt->bindValue(':firma_id', (int)$firma_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveWinner($data)
    {
        return $this->saveWithAttr($data);
    }

    public function getHediyeler()
    {
        $stmt = $this->db->prepare("SELECT * FROM ayin_personeli_hediyeler WHERE durum = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getHallOfFame($firma_id, $limit = 12)
    {
        $sql = "SELECT ap.*, p.adi_soyadi, p.resim_yolu, p.departman, h.baslik as hediye_adi
                FROM {$this->table} ap
                JOIN personel p ON ap.personel_id = p.id
                LEFT JOIN ayin_personeli_hediyeler h ON ap.hediye_id = h.id
                WHERE ap.firma_id = :firma_id
                ORDER BY ap.donem DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['firma_id' => $firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
