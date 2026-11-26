<?php

namespace App\Model;

use PDO;

class TalepAiOnAnalizModel extends Model
{
    protected $table = 'talep_ai_on_analiz';

    public const TIPLER = ['genel', 'avans', 'izin'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function bul(string $tip, int $talepId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE talep_tipi = ? AND talep_id = ? AND deleted_at IS NULL AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$tip, $talepId]);
        $satir = $stmt->fetch(PDO::FETCH_OBJ);
        return $satir ?: null;
    }

    public function kaydet(array $veri): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table}
                (firma_id, talep_tipi, talep_id, personel_id, durum, oneri, aciliyet, kategori, ozet, bulgular, model, hata_mesaji)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                firma_id = VALUES(firma_id),
                personel_id = VALUES(personel_id),
                durum = VALUES(durum),
                oneri = VALUES(oneri),
                aciliyet = VALUES(aciliyet),
                kategori = VALUES(kategori),
                ozet = VALUES(ozet),
                bulgular = VALUES(bulgular),
                model = VALUES(model),
                hata_mesaji = VALUES(hata_mesaji),
                is_active = 1,
                deleted_at = NULL"
        );
        $stmt->execute([
            (int) $veri['firma_id'],
            (string) $veri['talep_tipi'],
            (int) $veri['talep_id'],
            (int) $veri['personel_id'],
            (string) ($veri['durum'] ?? 'tamamlandi'),
            $veri['oneri'] ?? null,
            $veri['aciliyet'] ?? null,
            $veri['kategori'] ?? null,
            $veri['ozet'] ?? null,
            $veri['bulgular'] ?? null,
            $veri['model'] ?? null,
            $veri['hata_mesaji'] ?? null,
        ]);
    }

    public function beklemedeMi(string $tip, int $talepId): bool
    {
        $sorgular = [
            'genel' => "SELECT COUNT(*) FROM personel_talepleri WHERE id = ? AND silinme_tarihi IS NULL AND durum IN ('beklemede', 'islemde')",
            'avans' => "SELECT COUNT(*) FROM personel_avanslari WHERE id = ? AND silinme_tarihi IS NULL AND durum = 'beklemede'",
            'izin' => "SELECT COUNT(*) FROM personel_izinleri WHERE id = ? AND silinme_tarihi IS NULL AND LOWER(onay_durumu) = 'beklemede'",
        ];
        if (!isset($sorgular[$tip])) {
            return false;
        }
        $stmt = $this->db->prepare($sorgular[$tip]);
        $stmt->execute([$talepId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function personelOzet(int $personelId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT id, firma_id, departman, gorev, ise_giris_tarihi
             FROM personel WHERE id = ? AND silinme_tarihi IS NULL LIMIT 1"
        );
        $stmt->execute([$personelId]);
        $satir = $stmt->fetch(PDO::FETCH_OBJ);
        return $satir ?: null;
    }

    public function genelTalep(int $id): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT id, personel_id, konum, kategori, oncelik, baslik, aciklama, foto, durum, olusturma_tarihi
             FROM personel_talepleri WHERE id = ? AND silinme_tarihi IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $satir = $stmt->fetch(PDO::FETCH_OBJ);
        return $satir ?: null;
    }

    public function genelTalepGecmisi(int $personelId, int $haricId): array
    {
        $stmt = $this->db->prepare(
            "SELECT kategori, COUNT(*) AS adet
             FROM personel_talepleri
             WHERE personel_id = ? AND id <> ? AND silinme_tarihi IS NULL
               AND olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY kategori"
        );
        $stmt->execute([$personelId, $haricId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ayniKonumAcikTalepSayisi(string $konum, int $haricId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM personel_talepleri
             WHERE konum = ? AND id <> ? AND silinme_tarihi IS NULL
               AND durum IN ('beklemede', 'islemde')
               AND olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$konum, $haricId]);
        return (int) $stmt->fetchColumn();
    }

    public function avans(int $id): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT id, personel_id, tutar, odeme_sekli, aciklama, durum, talep_tarihi
             FROM personel_avanslari WHERE id = ? AND silinme_tarihi IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $satir = $stmt->fetch(PDO::FETCH_OBJ);
        return $satir ?: null;
    }

    public function avansGecmisi(int $personelId, int $haricId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(talep_tarihi, '%Y-%m') AS ay, durum, COUNT(*) AS adet, SUM(tutar) AS toplam
             FROM personel_avanslari
             WHERE personel_id = ? AND id <> ? AND silinme_tarihi IS NULL
               AND talep_tarihi >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY ay, durum
             ORDER BY ay DESC"
        );
        $stmt->execute([$personelId, $haricId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function izin(int $id): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT pi.id, pi.personel_id, pi.izin_tipi_id, pi.baslangic_tarihi, pi.bitis_tarihi,
                    pi.toplam_gun, pi.aciklama, pi.onay_durumu, t.tur_adi
             FROM personel_izinleri pi
             LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
             WHERE pi.id = ? AND pi.silinme_tarihi IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $satir = $stmt->fetch(PDO::FETCH_OBJ);
        return $satir ?: null;
    }

    public function izinGecmisi(int $personelId, int $haricId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.tur_adi, COUNT(*) AS adet, SUM(pi.toplam_gun) AS gun
             FROM personel_izinleri pi
             LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
             WHERE pi.personel_id = ? AND pi.id <> ? AND pi.silinme_tarihi IS NULL
               AND LOWER(pi.onay_durumu) IN ('onaylandi', 'onaylandı')
               AND pi.baslangic_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY t.tur_adi"
        );
        $stmt->execute([$personelId, $haricId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ayniDepartmandaIzinliSayisi(int $firmaId, string $departman, string $baslangic, string $bitis, int $haricPersonelId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM personel
                  WHERE firma_id = ? AND departman = ? AND silinme_tarihi IS NULL
                    AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')) AS toplam,
                (SELECT COUNT(DISTINCT pi.personel_id)
                   FROM personel_izinleri pi
                   INNER JOIN personel p ON p.id = pi.personel_id
                  WHERE p.firma_id = ? AND p.departman = ? AND p.silinme_tarihi IS NULL
                    AND pi.personel_id <> ? AND pi.silinme_tarihi IS NULL
                    AND LOWER(pi.onay_durumu) IN ('onaylandi', 'onaylandı', 'beklemede')
                    AND pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?) AS izinli"
        );
        $stmt->execute([$firmaId, $departman, $firmaId, $departman, $haricPersonelId, $bitis, $baslangic]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'toplam' => (int) ($satir['toplam'] ?? 0),
            'izinli' => (int) ($satir['izinli'] ?? 0),
        ];
    }
}
