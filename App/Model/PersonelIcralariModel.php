<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelIcralariModel extends Model
{
    protected $table = 'personel_icralari';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPersonelIcralari($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND silinme_tarihi IS NULL 
            ORDER BY created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getDevamEdenIcralar($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE personel_id = ? AND durum = 'devam_ediyor' AND silinme_tarihi IS NULL 
            ORDER BY sira ASC, created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin icra dosyalarını toplam kesilen ve kalan tutar ile birlikte getirir
     */
    public function getPersonelIcralariWithKesintiler($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT pi.*, 
                   COALESCE(SUM(CASE WHEN pk.silinme_tarihi IS NULL AND pk.durum = 'onaylandi' THEN pk.tutar ELSE 0 END), 0) as toplam_kesilen,
                   (pi.toplam_borc - COALESCE(SUM(CASE WHEN pk.silinme_tarihi IS NULL AND pk.durum = 'onaylandi' THEN pk.tutar ELSE 0 END), 0)) as kalan_tutar
            FROM {$this->table} pi
            LEFT JOIN personel_kesintileri pk ON pk.icra_id = pi.id
            WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL 
            GROUP BY pi.id
            ORDER BY pi.sira ASC, pi.created_at DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir icra dosyasına yapılan kesintilerin detayını getirir
     */
    public function getIcraKesintileri($icra_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.id, pk.tutar, pk.aciklama, pk.durum, pk.olusturma_tarihi,
                   bd.donem_adi, bd.baslangic_tarihi as donem_baslangic
            FROM personel_kesintileri pk
            LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.icra_id = ? AND pk.silinme_tarihi IS NULL
            ORDER BY bd.baslangic_tarihi ASC, pk.olusturma_tarihi ASC
        ");
        $sql->execute([$icra_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
