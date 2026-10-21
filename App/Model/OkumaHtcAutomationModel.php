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

    /** Otomasyon migration'ının hedef veritabanında eksiksiz olduğunu doğrular. */
    public function automationSchemaStatus(): array
    {
        $requiredColumns = ['kaynak', 'manuel_kilit', 'otomatik_kural', 'otomatik_okuma_sayisi'];
        $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_izinleri'
              AND COLUMN_NAME IN ('kaynak', 'manuel_kilit', 'otomatik_kural', 'otomatik_okuma_sayisi')");
        $stmt->execute();
        $presentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $tableStmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'puantaj_otomasyon_kilitleri'");
        $tableStmt->execute();

        return [
            'ready' => count(array_diff($requiredColumns, $presentColumns)) === 0
                && (int) $tableStmt->fetchColumn() === 1,
            'missing_columns' => array_values(array_diff($requiredColumns, $presentColumns)),
            'missing_lock_table' => (int) $this->tableExists('puantaj_otomasyon_kilitleri') === 0,
        ];
    }

    /** @return int[] */
    public function activeFirmIds(?int $firmaId = null): array
    {
        $sql = "SELECT id FROM firmalar WHERE silinme_tarihi IS NULL";
        $params = [];
        if ($firmaId !== null) {
            $sql .= " AND id = ?";
            $params[] = $firmaId;
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

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

    public function operationTotals(int $firmaId, string $date): array
    {
        $totals = [];

        $kacak = $this->db->prepare("SELECT p.id AS personel_id, SUM(k.sayi) AS toplam
            FROM kacak_kontrol k INNER JOIN personel p ON p.firma_id = k.firma_id
              AND (p.id = k.bildiren_personel_id OR FIND_IN_SET(p.id, k.personel_ids))
            WHERE k.firma_id = ? AND k.tarih = ? AND k.silinme_tarihi IS NULL
              AND k.onay_durumu IN ('onaylandi', 'beklemede')
              AND NOT (k.durum = 'iptal' AND k.hakedisten_dus = 1)
              AND p.silinme_tarihi IS NULL
            GROUP BY p.id");
        $kacak->execute([$firmaId, $date]);
        foreach ($kacak->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['personel_id']]['kacak'] = (float) $row['toplam'];
        }

        $sokme = $this->db->prepare("SELECT t.personel_id,
              ROUND(SUM(CASE WHEN pay.personel_sayisi > 0 THEN 1.0 / pay.personel_sayisi ELSE 0 END), 4) AS toplam
            FROM sayac_degisim t
            JOIN (SELECT tarih, SUBSTRING_INDEX(islem_id, '_', 1) ortak_islem_id, COUNT(*) personel_sayisi
                  FROM sayac_degisim WHERE firma_id = ? AND tarih = ? AND silinme_tarihi IS NULL
                  GROUP BY tarih, SUBSTRING_INDEX(islem_id, '_', 1)) pay
              ON pay.tarih = t.tarih AND pay.ortak_islem_id = SUBSTRING_INDEX(t.islem_id, '_', 1)
            WHERE t.firma_id = ? AND t.tarih = ? AND t.silinme_tarihi IS NULL AND t.personel_id > 0
              AND EXISTS (SELECT 1 FROM tanimlamalar d WHERE d.firma_id = t.firma_id
                  AND d.grup = 'is_turu' AND d.rapor_sekmesi = 'sokme_takma'
                  AND TRIM(d.is_emri_sonucu) = TRIM(t.isemri_sonucu) AND d.silinme_tarihi IS NULL)
            GROUP BY t.personel_id");
        $sokme->execute([$firmaId, $date, $firmaId, $date]);
        foreach ($sokme->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['personel_id']]['sokme_takma'] = (float) $row['toplam'];
        }

        $muhur = $this->db->prepare("SELECT y.personel_id, SUM(y.sonuclanmis) AS toplam
            FROM yapilan_isler y INNER JOIN tanimlamalar d ON d.id = y.is_emri_sonucu_id
            WHERE y.firma_id = ? AND y.tarih = ? AND y.silinme_tarihi IS NULL AND y.personel_id > 0
              AND d.rapor_sekmesi = 'muhurleme' AND d.silinme_tarihi IS NULL
            GROUP BY y.personel_id");
        $muhur->execute([$firmaId, $date]);
        foreach ($muhur->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['personel_id']]['muhurleme'] = (float) $row['toplam'];
        }

        return $totals;
    }

    public function personnelSummaries(int $firmaId, array $personelIds): array
    {
        $personelIds = array_values(array_unique(array_filter(array_map('intval', $personelIds))));
        if (!$personelIds) return [];
        $placeholders = implode(',', array_fill(0, count($personelIds), '?'));
        $stmt = $this->db->prepare("SELECT id, adi_soyadi, departman, ekip_no, ekip_bolge
            FROM personel WHERE firma_id = ? AND silinme_tarihi IS NULL AND id IN ($placeholders)
            ORDER BY adi_soyadi ASC");
        $stmt->execute(array_merge([$firmaId], $personelIds));
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int) $row['id']] = $row;
        return $rows;
    }

    public function activeRecords(int $personelId, string $date): array
    {
        $stmt = $this->db->prepare("SELECT pi.id, pi.izin_tipi_id, pi.baslangic_tarihi, pi.bitis_tarihi,
                pi.kaynak, pi.manuel_kilit, pi.otomatik_kural, t.kisa_kod
            FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL
              AND pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ? ORDER BY pi.id ASC");
        $stmt->execute([$personelId, $date, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function softDeleteAutomatic(int $id, string $reason): void
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
