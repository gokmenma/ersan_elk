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
                   p.adi_soyadi as personel_adi, p.resim_yolu as personel_resim,
                   (SELECT bitis_km FROM {$this->table} b2 
                    WHERE b2.arac_id = akb.arac_id 
                    AND b2.tarih = akb.tarih 
                    AND b2.tur = 'sabah' 
                    AND b2.durum != 'reddedildi' 
                    LIMIT 1) as sabah_km
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
                   p.adi_soyadi as personel_adi, p.resim_yolu as personel_resim,
                   u.adi_soyadi as onaylayan_adi,
                   (SELECT bitis_km FROM {$this->table} b2 
                    WHERE b2.arac_id = akb.arac_id 
                    AND b2.tarih = akb.tarih 
                    AND b2.tur = 'sabah' 
                    AND b2.durum != 'reddedildi' 
                    LIMIT 1) as sabah_km
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

    /**
     * Aynı gün, aynı araç ve aynı tür (sabah/akşam) için mükerrer kayıt kontrolü yapar.
     * Reddedilen kayıtlar mükerrer sayılmaz (tekrar denenebilir).
     */
    public function checkDuplicate($aracId, $tarih, $tur, $excludeId = 0)
    {
        $sqlStr = "SELECT id FROM {$this->table} 
                WHERE arac_id = :aid AND tarih = :tarih AND tur = :tur AND durum != 'reddedildi'";
        
        $params = [
            'aid' => $aracId,
            'tarih' => $tarih,
            'tur' => $tur
        ];

        if ($excludeId > 0) {
            $sqlStr .= " AND id != :exid";
            $params['exid'] = $excludeId;
        }

        $sql = $this->db->prepare($sqlStr);
        $sql->execute($params);
        return $sql->fetchColumn() !== false;
    }

    /**
     * Belirli bir tarih ve tür için KM bildirimi yapmayan personelleri getirir
     */
    public function getUnreported($tarih, $tur)
    {
        $sql = "SELECT z.personel_id, z.arac_id, p.adi_soyadi as personel_adi, p.cep_telefonu as telefon, a.plaka
                FROM arac_zimmetleri z
                INNER JOIN personel p ON z.personel_id = p.id
                INNER JOIN araclar a ON z.arac_id = a.id
                WHERE z.durum = 'aktif'
                AND z.firma_id = :firma_id
                AND z.silinme_tarihi IS NULL
                AND z.zimmet_tarihi <= :tarih
                AND NOT EXISTS (
                    SELECT 1 FROM arac_km_bildirimleri b
                    WHERE b.arac_id = z.arac_id
                    AND b.personel_id = z.personel_id
                    AND b.tarih = :tarih
                    AND b.tur = :tur
                    AND b.silinme_tarihi IS NULL
                    AND b.durum != 'reddedildi'
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'firma_id' => $_SESSION['firma_id'],
            'tarih' => $tarih,
            'tur' => $tur
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    /**
     * Belirli bir tarih ve tür için bildirim yapmayan tüm personellere hatirlatma gönderir
     */
    public function sendBatchKmHatirlatma($tarih, $tur)
    {
        $unreported = $this->getUnreported($tarih, $tur);
        if (empty($unreported)) return 0;

        $Bildirim = new \App\Model\BildirimModel();
        
        $count = 0;
        $turFmt = $tur === 'sabah' ? 'Sabah' : 'Akşam';
        $tarihFmt = date('d.m.Y', strtotime($tarih));
        $message = "{$tarihFmt} tarihli {$turFmt} bildirimini yapmadınız, Lütfen yapınız.";

        foreach ($unreported as $r) {
            // Personelin bağlı olduğu kullanıcıyı bul
            $stmt = $this->db->prepare("SELECT id FROM users WHERE personel_id = ? AND durum = 'Aktif' AND silinme_tarihi IS NULL");
            $stmt->execute([$r->personel_id]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) continue;

            foreach ($userIds as $uid) {
                // 1. Sistem Bildirimi
                $Bildirim->createNotification(
                    $uid,
                    'KM Bildirim Hatırlatması',
                    $message,
                    'index.php?p=personel-pwa/pages/ana-sayfa',
                    'bell',
                    'warning'
                );

                // 2. Push Bildirimi
                try {
                    $push = new \App\Service\PushNotificationService();
                    $push->sendToUser($uid, [
                        'title' => 'KM Bildirim Hatırlatması',
                        'body' => $message,
                        'url' => 'index_pwa.php' 
                    ], true);
                } catch (\Exception $e) {
                    // Log but ignore push errors
                }
                
                $count++;
            }
        }
        return $count;
    }
}
