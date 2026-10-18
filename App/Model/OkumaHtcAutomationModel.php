<?php

namespace App\Model;

use PDO;

class OkumaHtcAutomationModel extends Model
{
    public function __construct()
    {
        parent::__construct('personel_izinleri');
    }

    public function begin(): bool { return $this->db->beginTransaction(); }
    public function commit(): bool { return $this->db->commit(); }
    public function rollBack(): bool { return $this->db->rollBack(); }
    public function inTransaction(): bool { return $this->db->inTransaction(); }

    public function definitionIds(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT id, UPPER(TRIM(kisa_kod)) AS code, firma_id FROM tanimlamalar
            WHERE grup = 'izin_turu' AND UPPER(TRIM(kisa_kod)) IN ('HT', 'HTÇ')
              AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL
            ORDER BY (firma_id = ?) DESC, id DESC");
        $stmt->execute([$firmaId, $firmaId]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ids[$row['code']] ??= (int) $row['id'];
        }
        return $ids;
    }

    public function eligibleReadingTotals(int $firmaId, string $date): array
    {
        $stmt = $this->db->prepare("SELECT eo.personel_id, SUM(eo.okunan_abone_sayisi) AS reading_count
            FROM endeks_okuma eo INNER JOIN personel p ON p.id = eo.personel_id
            WHERE eo.firma_id = ? AND eo.tarih = ? AND eo.silinme_tarihi IS NULL
              AND eo.personel_id > 0 AND p.firma_id = ? AND p.silinme_tarihi IS NULL
              AND LOWER(COALESCE((SELECT pgg.departman FROM personel_gorev_gecmisi pgg
                  WHERE pgg.personel_id = p.id AND pgg.baslangic_tarihi <= ?
                    AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
                  ORDER BY pgg.baslangic_tarihi DESC, pgg.id DESC LIMIT 1), p.departman, '')) LIKE '%okuma%'
            GROUP BY eo.personel_id");
        $stmt->execute([$firmaId, $date, $firmaId, $date, $date]);
        $totals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['personel_id']] = (float) $row['reading_count'];
        }
        return $totals;
    }

    public function automaticPersonnelIds(int $firmaId, string $date, string $rule): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT pi.personel_id FROM personel_izinleri pi
            INNER JOIN personel p ON p.id = pi.personel_id
            WHERE p.firma_id = ? AND pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?
              AND pi.kaynak = 'otomatik' AND pi.otomatik_kural = ? AND pi.silinme_tarihi IS NULL");
        $stmt->execute([$firmaId, $date, $date, $rule]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function activeRecordsForUpdate(int $personelId, string $date): array
    {
        $stmt = $this->db->prepare("SELECT pi.id, pi.izin_tipi_id, pi.baslangic_tarihi, pi.bitis_tarihi,
                pi.kaynak, pi.manuel_kilit, t.kisa_kod
            FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL
              AND pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?
            ORDER BY pi.id ASC FOR UPDATE");
        $stmt->execute([$personelId, $date, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id, string $reason): void
    {
        $stmt = $this->db->prepare("UPDATE personel_izinleri SET silinme_tarihi = NOW(),
            silinme_aciklama = ?, silen_kullanici = 0 WHERE id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$reason, $id]);
    }

    public function updateAutomatic(int $id, string $rule, float $readingCount, string $description): void
    {
        $stmt = $this->db->prepare("UPDATE personel_izinleri SET otomatik_okuma_sayisi = ?, aciklama = ?
            WHERE id = ? AND kaynak = 'otomatik' AND otomatik_kural = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$readingCount, $description, $id, $rule]);
    }

    public function insertAutomatic(int $personelId, int $htcId, string $date, string $description, string $rule, float $readingCount): void
    {
        $stmt = $this->db->prepare("INSERT INTO personel_izinleri
            (personel_id, izin_tipi_id, baslangic_tarihi, bitis_tarihi, toplam_gun, yillik_izne_etki,
             aciklama, onay_durumu, onaylayan_id, olusturma_tarihi, kaynak, manuel_kilit,
             otomatik_kural, otomatik_okuma_sayisi)
            VALUES (?, ?, ?, ?, 1, 0, ?, 'Onaylandı', '0', NOW(), 'otomatik', 0, ?, ?)");
        $stmt->execute([$personelId, $htcId, $date, $date, $description, $rule, $readingCount]);
    }

    public function hasManualLock(int $firmaId, int $personelId, string $date, string $rule): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM puantaj_otomasyon_kilitleri
            WHERE firma_id = ? AND personel_id = ? AND tarih = ? AND otomatik_kural = ? LIMIT 1");
        $stmt->execute([$firmaId, $personelId, $date, $rule]);
        return (bool) $stmt->fetchColumn();
    }

    public function lockManualOverride(int $firmaId, int $personelId, string $date, string $rule, int $userId, string $selectedCode): void
    {
        $stmt = $this->db->prepare("INSERT INTO puantaj_otomasyon_kilitleri
            (firma_id, personel_id, tarih, otomatik_kural, secilen_kod, kilitleyen_kullanici_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE secilen_kod = VALUES(secilen_kod),
                kilitleyen_kullanici_id = VALUES(kilitleyen_kullanici_id), guncelleme_tarihi = NOW()");
        $stmt->execute([$firmaId, $personelId, $date, $rule, $selectedCode ?: null, $userId ?: null]);
    }
}
