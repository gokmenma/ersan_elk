<?php

namespace App\Model;

use PDO;

class SozlesmeSablonModel extends Model
{
    protected $table = 'personel_sozlesme_sablonlari';

    public const TUR_HIZMET = 'hizmet_sozlesmesi';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function varsayilanYol(): string
    {
        return dirname(__DIR__, 2) . '/resources/sablonlar/hizmet_sozlesmesi.docx';
    }

    public static function klasor(int $firmaId): string
    {
        return dirname(__DIR__, 2) . '/uploads/sozlesme-sablonlari/' . $firmaId;
    }

    public function hazirMi(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listele(int $firmaId, string $tur = self::TUR_HIZMET): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.adi_soyadi AS yukleyen_adi
             FROM {$this->table} s
             LEFT JOIN users u ON u.id = s.yukleyen_id
             WHERE s.firma_id = ? AND s.sablon_turu = ? AND s.deleted_at IS NULL AND s.is_active = 1
             ORDER BY s.versiyon DESC"
        );
        $stmt->execute([$firmaId, $tur]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function bul(int $id, int $firmaId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ? AND deleted_at IS NULL AND is_active = 1"
        );
        $stmt->execute([$id, $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function aktif(int $firmaId, string $tur = self::TUR_HIZMET): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE firma_id = ? AND sablon_turu = ? AND kullanimda = 1 AND deleted_at IS NULL AND is_active = 1
             ORDER BY versiyon DESC LIMIT 1"
        );
        $stmt->execute([$firmaId, $tur]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function dosyaYolu(object $kayit): string
    {
        return self::klasor((int) $kayit->firma_id) . '/' . $kayit->dosya_adi;
    }

    public function aktifDosyaYolu(int $firmaId, string $tur = self::TUR_HIZMET): string
    {
        if ($this->hazirMi()) {
            $kayit = $this->aktif($firmaId, $tur);
            if ($kayit) {
                $yol = $this->dosyaYolu($kayit);
                if (is_file($yol)) {
                    return $yol;
                }
                error_log('Sözleşme şablon dosyası bulunamadı, varsayılan kullanılıyor: ' . $kayit->id);
            }
        }
        return self::varsayilanYol();
    }

    public function sonrakiVersiyon(int $firmaId, string $tur): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(versiyon), 0) + 1 FROM {$this->table} WHERE firma_id = ? AND sablon_turu = ?");
        $stmt->execute([$firmaId, $tur]);
        return (int) $stmt->fetchColumn();
    }

    public function ekle(int $firmaId, string $tur, string $dosyaAdi, string $orijinalAd, int $boyut, string $sha256, string $aciklama, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE {$this->table} SET kullanimda = 0 WHERE firma_id = ? AND sablon_turu = ?")->execute([$firmaId, $tur]);
            $this->db->prepare(
                "INSERT INTO {$this->table}
                 (firma_id, sablon_turu, versiyon, dosya_adi, orijinal_dosya_adi, dosya_boyutu, sha256, aciklama, kullanimda, yukleyen_id, olusturma_tarihi)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())"
            )->execute([$firmaId, $tur, $this->sonrakiVersiyon($firmaId, $tur), $dosyaAdi, $orijinalAd, $boyut, $sha256, $aciklama, $userId]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function aktifEt(int $id, int $firmaId): ?object
    {
        $kayit = $this->bul($id, $firmaId);
        if (!$kayit) {
            return null;
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE {$this->table} SET kullanimda = 0 WHERE firma_id = ? AND sablon_turu = ?")->execute([$firmaId, $kayit->sablon_turu]);
            $this->db->prepare("UPDATE {$this->table} SET kullanimda = 1 WHERE id = ? AND firma_id = ?")->execute([$id, $firmaId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $kayit;
    }

    public function varsayilanaDon(int $firmaId, string $tur = self::TUR_HIZMET): void
    {
        $this->db->prepare("UPDATE {$this->table} SET kullanimda = 0 WHERE firma_id = ? AND sablon_turu = ?")->execute([$firmaId, $tur]);
    }
}
