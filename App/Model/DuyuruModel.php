<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DuyuruModel extends Model
{
    protected $table = 'duyurular';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAll($activeOnly = false, $adminOnly = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE firma_id = :firma_id AND silinme_tarihi IS NULL";
        $params = [
            'firma_id' => $_SESSION['firma_id'],
        ];

        if ($activeOnly) {
            $sql .= " AND durum = 'Yayında' AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= :today)";
            $params['today'] = date('Y-m-d');
        }

        if ($adminOnly) {
            $sql .= " AND ana_sayfada_goster = 1";
        }

        $sql .= " ORDER BY tarih DESC";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function createDuyuru($data)
    {
        return $this->saveWithAttr($data);
    }

    public function updateDuyuru($id, $data)
    {
        $data['id'] = $id;
        return $this->saveWithAttr($data);
    }

    public function getStats()
    {
        $sql = "SELECT 
                COUNT(*) as toplam,
                SUM(CASE WHEN durum = 'Yayında' AND (DATE(etkinlik_tarihi) >= ? OR etkinlik_tarihi IS NULL) AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN durum = 'Yayında' AND DATE(etkinlik_tarihi) < ? AND etkinlik_tarihi IS NOT NULL AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as suresi_dolmus
                FROM {$this->table} 
                WHERE firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $today = date('Y-m-d');
        $stmt->execute([$today, $today, $_SESSION['firma_id']]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getPwaSlider(int $firmaId, int $personelId): array
    {
        $stmt = $this->db->prepare("SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi
            FROM {$this->table}
            WHERE silinme_tarihi IS NULL
            AND firma_id = ?
            AND pwa_goster = 1
            AND durum = 'Yayında'
            AND (alici_tipi = 'toplu' OR FIND_IN_SET(?, alici_ids))
            AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= ?)
            ORDER BY id DESC
            LIMIT 5");
        $stmt->execute([$firmaId, $personelId, date('Y-m-d')]);

        $bugun = new \DateTime('today');
        return array_map(function ($d) use ($bugun) {
            $tarih = date('d.m.Y H:i', strtotime($d['tarih']));
            $kalanGun = null;
            if ($d['etkinlik_tarihi']) {
                $tarih = 'Son: ' . date('d.m.Y', strtotime($d['etkinlik_tarihi']));
                $etkinlik = (new \DateTime($d['etkinlik_tarihi']))->setTime(0, 0, 0);
                if ($etkinlik >= $bugun) {
                    $kalanGun = sprintf('%02d', $bugun->diff($etkinlik)->days);
                }
            }
            return [
                'id' => $d['id'],
                'baslik' => $d['baslik'],
                'icerik' => $d['icerik'],
                'resim' => $d['resim'],
                'tarih' => $tarih,
                'kalan_gun' => $kalanGun,
                'hedef_sayfa' => $d['hedef_sayfa'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
