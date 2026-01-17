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
                // Ana durum varsayılanı (eğer tabloda yoksa veya onaylardan gelecekse)
                // Eğer izin_onaylari tablosunda kayıt varsa son durumu alacağız
                $result[$row->id] = $row;
            }
            
            // Onay kaydı varsa detaylara ekle
            if ($row->onay_kayit_id) {
                $onay = new \stdClass();
                $onay->adi = $row->onaylayan_adi_soyadi;
                $onay->tarih = $row->onay_tarihi;
                $onay->durum = $row->onay_durumu_text;
                $onay->aciklama = $row->onay_aciklama;
                
                $result[$row->id]->onaylar[] = $onay;
                
                // Son onay durumunu ana durum olarak set edelim (basit mantık)
                $result[$row->id]->son_durum = $row->onay_durumu_text;
            }
        }

        return array_values($result);
    }
}