<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DemirbasKategoriModel extends Model
{
    protected $table = 'demirbas_kategorileri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm aktif kategorileri getirir
     */
    public function getActiveCategories()
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table} WHERE aktif = 1 ORDER BY kategori_adi ASC");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kategoriye göre demirbaş sayısını getirir
     */
    public function getCategoryStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                k.id,
                k.kategori_adi,
                COUNT(d.id) as demirbas_sayisi,
                COALESCE(SUM(d.miktar), 0) as toplam_miktar,
                COALESCE(SUM(d.kalan_miktar), 0) as kalan_miktar
            FROM {$this->table} k
            LEFT JOIN demirbas d ON k.id = d.kategori_id
            WHERE k.aktif = 1
            GROUP BY k.id, k.kategori_adi
            ORDER BY k.kategori_adi ASC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
