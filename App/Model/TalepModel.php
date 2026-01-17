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
                WHERE personel_id = ? AND deleted_at IS NULL 
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
                WHERE personel_id = ? AND deleted_at IS NULL";

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
}
