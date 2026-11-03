<?php

namespace App\Model;

use PDO;

class DosyaKlasorModel extends Model
{
    protected $table = 'dosya_klasorleri';

    public function __construct()
    {
        parent::__construct('dosya_klasorleri');
    }

    public function bul(int $id, int $firmaId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM dosya_klasorleri WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id, $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function tumKlasorler(int $firmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT k.id, k.parent_id, k.ad, k.created_at,
                    (SELECT COUNT(*) FROM dosyalar d WHERE d.klasor_id = k.id AND d.is_active = 1 AND d.deleted_at IS NULL) AS dosya_sayisi
             FROM dosya_klasorleri k
             WHERE k.firma_id = ? AND k.is_active = 1 AND k.deleted_at IS NULL
             ORDER BY k.ad ASC"
        );
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function rolHaritasi(int $firmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.klasor_id, r.role_id
             FROM dosya_klasor_rolleri r
             INNER JOIN dosya_klasorleri k ON k.id = r.klasor_id
             WHERE k.firma_id = ? AND k.is_active = 1 AND k.deleted_at IS NULL"
        );
        $stmt->execute([$firmaId]);
        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $satir) {
            $harita[(int) $satir->klasor_id][] = (int) $satir->role_id;
        }
        return $harita;
    }

    /**
     * Kullanıcının erişebildiği klasör ID listesini döndürür. Bir klasör ve tüm üst klasörleri
     * rol kısıtı taşıyorsa kullanıcının rollerinden en az biri her kısıtta bulunmalıdır.
     *
     * @param array{super: bool, roles: int[]} $erisim
     * @return int[]
     */
    public function erisilebilirKlasorIdleri(int $firmaId, array $erisim): array
    {
        $klasorler = $this->tumKlasorler($firmaId);
        if (!empty($erisim['super'])) {
            return array_map(static fn($k) => (int) $k->id, $klasorler);
        }

        $roller = array_map('intval', $erisim['roles'] ?? []);
        $rolHaritasi = $this->rolHaritasi($firmaId);
        $parentHaritasi = [];
        foreach ($klasorler as $k) {
            $parentHaritasi[(int) $k->id] = $k->parent_id !== null ? (int) $k->parent_id : null;
        }

        $sonuc = [];
        foreach ($parentHaritasi as $id => $parent) {
            $izinli = true;
            $gezilen = [];
            $suan = $id;
            while ($suan !== null && !isset($gezilen[$suan])) {
                $gezilen[$suan] = true;
                if (!empty($rolHaritasi[$suan]) && !array_intersect($rolHaritasi[$suan], $roller)) {
                    $izinli = false;
                    break;
                }
                $suan = $parentHaritasi[$suan] ?? null;
            }
            if ($izinli) {
                $sonuc[] = $id;
            }
        }
        return $sonuc;
    }

    public function klasorErisebilir(?int $klasorId, int $firmaId, array $erisim): bool
    {
        if ($klasorId === null || $klasorId <= 0) {
            return true;
        }
        return in_array($klasorId, $this->erisilebilirKlasorIdleri($firmaId, $erisim), true);
    }

    public function agac(int $firmaId, array $erisim): array
    {
        $izinli = array_flip($this->erisilebilirKlasorIdleri($firmaId, $erisim));
        $liste = [];
        foreach ($this->tumKlasorler($firmaId) as $k) {
            if (isset($izinli[(int) $k->id])) {
                $liste[] = $k;
            }
        }
        return $liste;
    }

    public function olustur(int $firmaId, ?int $parentId, string $ad, int $kullaniciId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dosya_klasorleri (firma_id, parent_id, ad, olusturan_id, is_active, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$firmaId, $parentId, $ad, $kullaniciId]);
        return (int) $this->db->lastInsertId();
    }

    public function ayniAdVarMi(int $firmaId, ?int $parentId, string $ad, int $haricId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM dosya_klasorleri
             WHERE firma_id = ? AND ((parent_id IS NULL AND ? IS NULL) OR parent_id = ?)
               AND ad = ? AND id <> ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$firmaId, $parentId, $parentId, $ad, $haricId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function yenidenAdlandir(int $id, int $firmaId, string $ad): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE dosya_klasorleri SET ad = ?, updated_at = NOW()
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$ad, $id, $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function altKlasorIdleri(int $id, int $firmaId): array
    {
        $parentHaritasi = [];
        foreach ($this->tumKlasorler($firmaId) as $k) {
            $parentHaritasi[(int) $k->id] = $k->parent_id !== null ? (int) $k->parent_id : null;
        }
        $sonuc = [];
        $kuyruk = [$id];
        while ($kuyruk) {
            $suan = array_shift($kuyruk);
            foreach ($parentHaritasi as $cocukId => $parent) {
                if ($parent === $suan && !in_array($cocukId, $sonuc, true)) {
                    $sonuc[] = $cocukId;
                    $kuyruk[] = $cocukId;
                }
            }
        }
        return $sonuc;
    }

    public function tasi(int $id, int $firmaId, ?int $yeniParentId): bool
    {
        if ($yeniParentId !== null) {
            if ($yeniParentId === $id || in_array($yeniParentId, $this->altKlasorIdleri($id, $firmaId), true)) {
                return false;
            }
            if (!$this->bul($yeniParentId, $firmaId)) {
                return false;
            }
        }
        $stmt = $this->db->prepare(
            "UPDATE dosya_klasorleri SET parent_id = ?, updated_at = NOW()
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$yeniParentId, $id, $firmaId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Klasörü, alt klasörlerini ve içindeki dosyaları soft delete ile siler.
     *
     * @return int[] Silinen klasör ID'leri
     */
    public function sil(int $id, int $firmaId): array
    {
        $idler = array_merge([$id], $this->altKlasorIdleri($id, $firmaId));
        $yer = implode(',', array_fill(0, count($idler), '?'));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE dosyalar SET deleted_at = NOW(), is_active = 0
                 WHERE firma_id = ? AND klasor_id IN ($yer) AND deleted_at IS NULL"
            );
            $stmt->execute(array_merge([$firmaId], $idler));

            $stmt = $this->db->prepare(
                "UPDATE dosya_klasorleri SET deleted_at = NOW(), is_active = 0
                 WHERE firma_id = ? AND id IN ($yer) AND deleted_at IS NULL"
            );
            $stmt->execute(array_merge([$firmaId], $idler));

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $idler;
    }

    public function roller(int $klasorId): array
    {
        $stmt = $this->db->prepare("SELECT role_id FROM dosya_klasor_rolleri WHERE klasor_id = ?");
        $stmt->execute([$klasorId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function rolleriKaydet(int $klasorId, array $roleIdleri): void
    {
        $roleIdleri = array_values(array_unique(array_filter(array_map('intval', $roleIdleri))));
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM dosya_klasor_rolleri WHERE klasor_id = ?");
            $stmt->execute([$klasorId]);
            if ($roleIdleri) {
                $ekle = $this->db->prepare("INSERT INTO dosya_klasor_rolleri (klasor_id, role_id) VALUES (?, ?)");
                foreach ($roleIdleri as $rolId) {
                    $ekle->execute([$klasorId, $rolId]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rolSecenekleri(): array
    {
        $stmt = $this->db->prepare("SELECT id, role_name FROM user_roles ORDER BY role_name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function yol(int $klasorId, int $firmaId): array
    {
        $parentHaritasi = [];
        foreach ($this->tumKlasorler($firmaId) as $k) {
            $parentHaritasi[(int) $k->id] = $k;
        }
        $yol = [];
        $suan = $klasorId;
        $gezilen = [];
        while ($suan && isset($parentHaritasi[$suan]) && !isset($gezilen[$suan])) {
            $gezilen[$suan] = true;
            array_unshift($yol, $parentHaritasi[$suan]);
            $suan = $parentHaritasi[$suan]->parent_id !== null ? (int) $parentHaritasi[$suan]->parent_id : 0;
        }
        return $yol;
    }
}
