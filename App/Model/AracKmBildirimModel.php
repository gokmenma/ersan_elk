<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class AracKmBildirimModel extends Model
{
    protected $table = 'arac_km_bildirimleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPendingReports()
    {
        $sql = $this->db->prepare("
            SELECT akb.*, 
                   a.plaka, a.marka, a.model,
                   p.adi_soyadi as personel_adi
            FROM {$this->table} akb
            INNER JOIN araclar a ON akb.arac_id = a.id
            INNER JOIN personel p ON akb.personel_id = p.id
            WHERE akb.firma_id = :firma_id
            AND akb.durum = 'beklemede'
            ORDER BY akb.olusturma_tarihi DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getReportsByStatus($status)
    {
        $sql = $this->db->prepare("
            SELECT akb.*, 
                   a.plaka, a.marka, a.model,
                   p.adi_soyadi as personel_adi,
                   u.adi_soyadi as onaylayan_adi
            FROM {$this->table} akb
            INNER JOIN araclar a ON akb.arac_id = a.id
            INNER JOIN personel p ON akb.personel_id = p.id
            LEFT JOIN users u ON akb.onaylayan_id = u.id
            WHERE akb.firma_id = :firma_id
            AND akb.durum = :status
            ORDER BY akb.onay_tarihi DESC, akb.olusturma_tarihi DESC
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'status' => $status
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getPersonelHistory($personelId, $limit = 20)
    {
        $sql = $this->db->prepare("
            SELECT akb.*, a.plaka
            FROM {$this->table} akb
            LEFT JOIN araclar a ON akb.arac_id = a.id
            WHERE akb.personel_id = :pid
            ORDER BY akb.tarih DESC, akb.olusturma_tarihi DESC
            LIMIT " . (int)$limit
        );
        $sql->execute(['pid' => $personelId]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir tarih ve türden (sabah/akşam) önceki en son KM bilgisini getirir
     */
    public function getLastKm($aracId, $tarih, $tur, $excludeId = 0)
    {
        // $tarih ve $tur mevcut bildirimimiz.
        // Biz bundan önceki en son kaydedilmiş (reddedilmemiş) KM değerini istiyoruz.
        
        $sqlStr = "
            SELECT bitis_km 
            FROM {$this->table} 
            WHERE arac_id = :aid 
            AND durum != 'reddedildi'
            AND (
                tarih < :tarih 
                OR (tarih = :tarih AND :tur = 'aksam' AND tur = 'sabah')
            )
        ";

        if ($excludeId > 0) {
            $sqlStr .= " AND id != :exid ";
        }

        $sqlStr .= " ORDER BY tarih DESC, (CASE WHEN tur = 'aksam' THEN 2 ELSE 1 END) DESC, bitis_km DESC, id DESC LIMIT 1 ";
        
        $sql = $this->db->prepare($sqlStr);
        
        $params = [
            'aid' => $aracId,
            'tarih' => $tarih,
            'tur' => $tur
        ];

        if ($excludeId > 0) {
            $params['exid'] = $excludeId;
        }
        
        $sql->execute($params);
        
        $res = $sql->fetch(PDO::FETCH_OBJ);
        return $res ? (int)$res->bitis_km : 0;
    }
}
