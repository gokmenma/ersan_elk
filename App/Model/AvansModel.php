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
}
