<?php

namespace App\Model;

use App\Core\Db;
use PDO;

class MaliyetOzelKalemModel extends Db
{
    public const ONEK = 'ozel_';

    public const RENKLER = ['#ec4899', '#84cc16', '#f97316', '#06b6d4', '#a855f7', '#eab308', '#ef4444', '#3b82f6', '#22c55e', '#78716c'];

    private int $firmaId;

    public function __construct()
    {
        parent::__construct();
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function liste(): array
    {
        $stmt = $this->db->prepare("SELECT id, ad, renk FROM maliyet_ozel_kalem
                                    WHERE firma_id = :firma_id AND deleted_at IS NULL AND is_active = 1
                                    ORDER BY id ASC");
        $stmt->execute(['firma_id' => $this->firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function adVarMi(string $ad): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM maliyet_ozel_kalem
                                    WHERE firma_id = :firma_id AND deleted_at IS NULL AND is_active = 1 AND LOWER(ad) = LOWER(:ad)");
        $stmt->execute(['firma_id' => $this->firmaId, 'ad' => $ad]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function ekle(string $ad, string $renk, int $kullaniciId): string
    {
        $stmt = $this->db->prepare("INSERT INTO maliyet_ozel_kalem (firma_id, ad, renk, olusturan_kullanici_id)
                                    VALUES (:firma_id, :ad, :renk, :kullanici)");
        $stmt->execute(['firma_id' => $this->firmaId, 'ad' => $ad, 'renk' => $renk, 'kullanici' => $kullaniciId ?: null]);
        return self::ONEK . $this->db->lastInsertId();
    }
}
