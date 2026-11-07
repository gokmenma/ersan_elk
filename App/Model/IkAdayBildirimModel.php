<?php

namespace App\Model;

use PDO;

class IkAdayBildirimModel extends Model
{
    protected $table = 'ik_basvuru_bildirimleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ekle(array $v): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, basvuru_id, kanal, sablon, alici, konu, mesaj, durum, kullanici_id)
            VALUES (:firma_id, :basvuru_id, :kanal, :sablon, :alici, :konu, :mesaj, :durum, :kullanici_id)");
        $stmt->execute([
            'firma_id' => $v['firma_id'],
            'basvuru_id' => $v['basvuru_id'],
            'kanal' => $v['kanal'],
            'sablon' => $v['sablon'],
            'alici' => $v['alici'],
            'konu' => $v['konu'],
            'mesaj' => $v['mesaj'],
            'durum' => $v['durum'],
            'kullanici_id' => $v['kullanici_id'] > 0 ? $v['kullanici_id'] : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listele(int $basvuruId, int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT b.kanal, b.sablon, b.konu, b.mesaj, b.durum, b.created_at, u.adi_soyadi AS kullanici
            FROM {$this->table} b
            LEFT JOIN users u ON u.id = b.kullanici_id
            WHERE b.basvuru_id = :basvuru_id AND b.firma_id = :firma_id
            ORDER BY b.id DESC");
        $stmt->execute(['basvuru_id' => $basvuruId, 'firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
