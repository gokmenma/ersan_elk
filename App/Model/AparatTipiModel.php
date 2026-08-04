<?php

namespace App\Model;

use PDO;

class AparatTipiModel extends Model
{
    protected $table = 'aparat_tipleri';

    const RENKLER = ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function listele(bool $sadeceAktif = true): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE firma_id = ? AND silinme_tarihi IS NULL";
        if ($sadeceAktif) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY sira ASC, ad ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getir(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function kodVarMi(string $kod, int $haricId = 0): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}
                                    WHERE firma_id = ? AND kod = ? AND id <> ? AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $kod, $haricId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function ekle(array $veri, int $kullaniciId): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, ad, kod, renk, sira, aciklama, is_active, olusturan_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $this->firmaId(),
            $veri['ad'],
            $veri['kod'],
            $veri['renk'] ?? 'primary',
            (int) ($veri['sira'] ?? 1),
            $veri['aciklama'] ?? null,
            isset($veri['is_active']) ? (int) $veri['is_active'] : 1,
            $kullaniciId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function guncelle(int $id, array $veri): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET ad = ?, kod = ?, renk = ?, sira = ?, aciklama = ?, is_active = ?
            WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([
            $veri['ad'],
            $veri['kod'],
            $veri['renk'] ?? 'primary',
            (int) ($veri['sira'] ?? 1),
            $veri['aciklama'] ?? null,
            isset($veri['is_active']) ? (int) $veri['is_active'] : 1,
            $id,
            $this->firmaId(),
        ]);
    }

    /**
     * Stoğu ya da hareketi olan tip silinmez; yalnızca pasife alınır.
     */
    public function kullanimdaMi(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM aparat_hareket WHERE aparat_tip_id = ? AND firma_id = ?) +
            (SELECT COUNT(*) FROM aparat_stok WHERE aparat_tip_id = ? AND firma_id = ? AND adet <> 0)");
        $stmt->execute([$id, $this->firmaId(), $id, $this->firmaId()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function sil(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET silinme_tarihi = NOW(), is_active = 0
                                    WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$id, $this->firmaId()]);
    }

    public function durumDegistir(int $id, int $aktif): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET is_active = ?
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$aktif, $id, $this->firmaId()]);
    }
}
