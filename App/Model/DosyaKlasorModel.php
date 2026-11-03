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

    private ?bool $siraVar = null;

    private function siraKolonuVar(): bool
    {
        if ($this->siraVar === null) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dosya_klasorleri' AND COLUMN_NAME = 'sira'"
            );
            $stmt->execute();
            $this->siraVar = (int) $stmt->fetchColumn() > 0;
        }
        return $this->siraVar;
    }

    public function tumKlasorler(int $firmaId): array
    {
        $sira = $this->siraKolonuVar() ? 'k.sira' : '0';
        $stmt = $this->db->prepare(
            "SELECT k.id, k.parent_id, k.ad, {$sira} AS sira, k.alan, k.olusturan_id, k.created_at,
                    (SELECT COUNT(*) FROM dosyalar d WHERE d.klasor_id = k.id AND d.is_active = 1 AND d.deleted_at IS NULL) AS dosya_sayisi
             FROM dosya_klasorleri k
             WHERE k.firma_id = ? AND k.is_active = 1 AND k.deleted_at IS NULL
             ORDER BY {$sira} ASC, k.ad ASC"
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

    public const SEVIYE_GORUNTULE = 1;
    public const SEVIYE_DUZENLE = 2;
    public const SEVIYE_SAHIP = 3;

    /**
     * Her klasör için kullanıcının erişim seviyesini hesaplar (0 = erişim yok).
     * Süper admin ve klasör zincirinde sahibi olduğu klasörler: sahip. Aksi halde zincirdeki
     * en yüksek kullanıcı yetkisi; ortak alanda yetki yoksa rol kısıtları geçerse görüntüleme.
     *
     * @param array{super: bool, user: int, roles: int[], klasor_yetkileri?: array<int,int>} $baglam
     * @return array<int, array{seviye: int, bolum: string, kok: bool, ad: string, parent_id: ?int, alan: string, dosya_sayisi: int}>
     */
    public function seviyeHaritasi(int $firmaId, array $baglam): array
    {
        $klasorler = [];
        foreach ($this->tumKlasorler($firmaId) as $k) {
            $klasorler[(int) $k->id] = $k;
        }
        $roller = array_map('intval', $baglam['roles'] ?? []);
        $rolHaritasi = $this->rolHaritasi($firmaId);
        $yetkiler = $baglam['klasor_yetkileri'] ?? [];
        $kullanici = (int) ($baglam['user'] ?? 0);

        $sonuc = [];
        foreach ($klasorler as $id => $k) {
            $sahip = false;
            $yetki = 0;
            $rolGecer = true;
            $suan = $id;
            $gezilen = [];
            while ($suan !== null && isset($klasorler[$suan]) && !isset($gezilen[$suan])) {
                $gezilen[$suan] = true;
                if ((int) $klasorler[$suan]->olusturan_id === $kullanici) {
                    $sahip = true;
                }
                $yetki = max($yetki, (int) ($yetkiler[$suan] ?? 0));
                if (!empty($rolHaritasi[$suan]) && !array_intersect($rolHaritasi[$suan], $roller)) {
                    $rolGecer = false;
                }
                $suan = $klasorler[$suan]->parent_id !== null ? (int) $klasorler[$suan]->parent_id : null;
            }

            if ($sahip) {
                $seviye = self::SEVIYE_SAHIP;
            } elseif ($yetki > 0) {
                $seviye = $yetki;
            } elseif ($k->alan === 'ortak' && $rolGecer) {
                $seviye = self::SEVIYE_GORUNTULE;
            } else {
                $seviye = 0;
            }

            $sonuc[$id] = [
                'seviye' => $seviye,
                'sahip_zincir' => $sahip,
                'ad' => $k->ad,
                'parent_id' => $k->parent_id !== null ? (int) $k->parent_id : null,
                'alan' => $k->alan,
                'dosya_sayisi' => (int) $k->dosya_sayisi,
            ];
        }

        foreach ($sonuc as $id => &$satir) {
            $ust = $satir['parent_id'];
            $satir['kok'] = $ust === null || empty($sonuc[$ust]['seviye']);
            if ($satir['alan'] === 'ortak') {
                $satir['bolum'] = 'ortak';
            } elseif ($satir['sahip_zincir']) {
                $satir['bolum'] = 'dosyalarim';
            } else {
                $satir['bolum'] = 'paylasilan';
            }
        }
        unset($satir);

        return $sonuc;
    }

    /**
     * @return int[] Seviye >= $asgari olan klasör ID'leri
     */
    public function erisilebilirKlasorIdleri(int $firmaId, array $baglam, int $asgari = self::SEVIYE_GORUNTULE): array
    {
        $ids = [];
        foreach ($this->seviyeHaritasi($firmaId, $baglam) as $id => $satir) {
            if ($satir['seviye'] >= $asgari) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function seviye(?int $klasorId, int $firmaId, array $baglam): int
    {
        if ($klasorId === null || $klasorId <= 0) {
            return 0;
        }
        return $this->seviyeHaritasi($firmaId, $baglam)[$klasorId]['seviye'] ?? 0;
    }

    public function olustur(int $firmaId, ?int $parentId, string $ad, int $kullaniciId, string $alan = 'kisisel'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dosya_klasorleri (firma_id, parent_id, ad, alan, olusturan_id, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$firmaId, $parentId, $ad, $alan === 'ortak' ? 'ortak' : 'kisisel', $kullaniciId]);
        return (int) $this->db->lastInsertId();
    }

    public function ayniAdVarMi(int $firmaId, ?int $parentId, string $ad, int $haricId = 0, ?int $kokSahipId = null, string $alan = 'kisisel'): bool
    {
        $sql = "SELECT COUNT(*) FROM dosya_klasorleri
                WHERE firma_id = ? AND ((parent_id IS NULL AND ? IS NULL) OR parent_id = ?)
                  AND ad = ? AND id <> ? AND is_active = 1 AND deleted_at IS NULL";
        $bind = [$firmaId, $parentId, $parentId, $ad, $haricId];
        if ($parentId === null) {
            $sql .= " AND alan = ?";
            $bind[] = $alan;
            if ($alan !== 'ortak') {
                $sql .= " AND olusturan_id = ?";
                $bind[] = (int) $kokSahipId;
            }
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function siraKaydet(int $firmaId, array $idler): void
    {
        if (!$this->siraKolonuVar()) {
            throw new \DomainException('Sıralama için 2026_11_03_dosya_klasor_sira.sql çalıştırılmalı.');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE dosya_klasorleri SET sira = ? WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
            );
            foreach (array_values($idler) as $i => $id) {
                $stmt->execute([($i + 1) * 10, (int) $id, $firmaId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
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
     * Klasörü, alt klasörlerini ve içindeki dosyaları (disk dosyaları dahil) kalıcı siler.
     *
     * @return array{klasorler: int[], dosyalar: object[]}
     */
    public function sil(int $id, int $firmaId): array
    {
        $idler = array_merge([$id], $this->altKlasorIdleri($id, $firmaId));
        $yer = implode(',', array_fill(0, count($idler), '?'));

        $stmt = $this->db->prepare("SELECT id, firma_id, dosya_adi FROM dosyalar WHERE firma_id = ? AND klasor_id IN ($yer)");
        $stmt->execute(array_merge([$firmaId], $idler));
        $dosyalar = $stmt->fetchAll(PDO::FETCH_OBJ);

        $this->db->beginTransaction();
        try {
            $dosyaIdleri = array_map(static fn($d) => (int) $d->id, $dosyalar);
            if ($dosyaIdleri) {
                $dyer = implode(',', array_fill(0, count($dosyaIdleri), '?'));
                foreach ([
                    "DELETE FROM dosya_paylasimlari WHERE firma_id = ? AND dosya_id IN ($dyer)",
                    "DELETE FROM dosya_erisimleri WHERE firma_id = ? AND nesne_tur = 'dosya' AND nesne_id IN ($dyer)",
                    "DELETE FROM dosyalar WHERE firma_id = ? AND id IN ($dyer)",
                ] as $sql) {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(array_merge([$firmaId], $dosyaIdleri));
                }
            }

            foreach ([
                "DELETE FROM dosya_erisimleri WHERE firma_id = ? AND nesne_tur = 'klasor' AND nesne_id IN ($yer)",
                "DELETE FROM dosya_klasor_rolleri WHERE klasor_id IN ($yer)",
                "DELETE FROM dosya_klasorleri WHERE firma_id = ? AND id IN ($yer)",
            ] as $sql) {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(str_contains($sql, 'firma_id') ? array_merge([$firmaId], $idler) : $idler);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        (new DosyaModel())->diskDosyalariniSil($dosyalar);
        return ['klasorler' => $idler, 'dosyalar' => $dosyalar];
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
