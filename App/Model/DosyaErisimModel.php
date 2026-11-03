<?php

namespace App\Model;

use PDO;

class DosyaErisimModel extends Model
{
    protected $table = 'dosya_erisimleri';

    private const SEVIYELER = ['goruntule' => 1, 'duzenle' => 2];

    public function __construct()
    {
        parent::__construct('dosya_erisimleri');
    }

    /**
     * @return array{klasor: array<int,int>, dosya: array<int,int>}
     */
    public function kullaniciHaritasi(int $firmaId, int $kullaniciId): array
    {
        $stmt = $this->db->prepare(
            "SELECT nesne_tur, nesne_id, seviye FROM dosya_erisimleri WHERE firma_id = ? AND user_id = ?"
        );
        $stmt->execute([$firmaId, $kullaniciId]);
        $harita = ['klasor' => [], 'dosya' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $satir) {
            $harita[$satir->nesne_tur][(int) $satir->nesne_id] = self::SEVIYELER[$satir->seviye] ?? 1;
        }
        return $harita;
    }

    public function listele(int $firmaId, string $tur, int $nesneId): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.user_id, e.seviye, e.created_at, u.adi_soyadi AS kullanici_adi, v.adi_soyadi AS veren_adi
             FROM dosya_erisimleri e
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN users v ON v.id = e.veren_id
             WHERE e.firma_id = ? AND e.nesne_tur = ? AND e.nesne_id = ?
             ORDER BY u.adi_soyadi ASC"
        );
        $stmt->execute([$firmaId, $tur, $nesneId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function ver(int $firmaId, string $tur, int $nesneId, int $kullaniciId, string $seviye, int $verenId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dosya_erisimleri (firma_id, nesne_tur, nesne_id, user_id, seviye, veren_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE seviye = VALUES(seviye), veren_id = VALUES(veren_id)"
        );
        $stmt->execute([$firmaId, $tur, $nesneId, $kullaniciId, $seviye === 'duzenle' ? 'duzenle' : 'goruntule', $verenId]);
    }

    public function bul(int $id, int $firmaId): ?object
    {
        $stmt = $this->db->prepare("SELECT * FROM dosya_erisimleri WHERE id = ? AND firma_id = ? LIMIT 1");
        $stmt->execute([$id, $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function kaldir(int $id, int $firmaId): void
    {
        $stmt = $this->db->prepare("DELETE FROM dosya_erisimleri WHERE id = ? AND firma_id = ?");
        $stmt->execute([$id, $firmaId]);
    }

    public function nesneErisimleriniTemizle(int $firmaId, string $tur, array $nesneIdleri): void
    {
        $nesneIdleri = array_values(array_unique(array_map('intval', $nesneIdleri)));
        if (!$nesneIdleri) {
            return;
        }
        $yer = implode(',', array_fill(0, count($nesneIdleri), '?'));
        $stmt = $this->db->prepare("DELETE FROM dosya_erisimleri WHERE firma_id = ? AND nesne_tur = ? AND nesne_id IN ($yer)");
        $stmt->execute(array_merge([$firmaId, $tur], $nesneIdleri));
    }

    public function firmaKullanicilari(int $firmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, adi_soyadi FROM users
             WHERE silinme_tarihi IS NULL AND durum = 'Aktif'
               AND FIND_IN_SET(:firma, REPLACE(COALESCE(firma_ids, ''), ' ', '')) > 0
             ORDER BY adi_soyadi ASC"
        );
        $stmt->execute(['firma' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
