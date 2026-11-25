<?php

namespace App\Model;

use PDO;

class AparatOtomatikKirmaModel extends Model
{
    protected $table = 'aparat_otomatik_kirma';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function baslangicTarihi(int $firmaId): ?string
    {
        $deger = (new SettingsModel())->getSettings('aparat_otomatik_kirma_baslangic', $firmaId);
        if ($deger === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($deger))) {
            return null;
        }
        return trim($deger);
    }

    public function varsayilanTipId(int $firmaId): int
    {
        $stmt = $this->db->prepare("SELECT id FROM aparat_tipleri
            WHERE firma_id = ? AND varsayilan_kirma = 1 AND is_active = 1 AND silinme_tarihi IS NULL
            ORDER BY id ASC LIMIT 1");
        $stmt->execute([$firmaId]);
        return (int) $stmt->fetchColumn();
    }

    public function karsilastirma(int $firmaId, string $baslangic): array
    {
        $hedefStmt = $this->db->prepare("SELECT y.personel_id, y.tarih, y.is_emri_sonucu_id,
                   SUM(COALESCE(y.sonuclanmis, 0)) AS hedef
            FROM yapilan_isler y
            INNER JOIN tanimlamalar t ON t.id = y.is_emri_sonucu_id
                AND t.grup = 'is_turu' AND t.aparat_hurda = 1
            WHERE y.firma_id = ? AND y.tarih >= ? AND y.silinme_tarihi IS NULL
              AND y.personel_id IS NOT NULL AND y.personel_id > 0
            GROUP BY y.personel_id, y.tarih, y.is_emri_sonucu_id");
        $hedefStmt->execute([$firmaId, $baslangic]);

        $sonuc = [];
        foreach ($hedefStmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $anahtar = $satir['personel_id'] . '|' . $satir['tarih'] . '|' . $satir['is_emri_sonucu_id'];
            $sonuc[$anahtar] = [
                'personel_id' => (int) $satir['personel_id'],
                'tarih' => (string) $satir['tarih'],
                'is_emri_sonucu_id' => (int) $satir['is_emri_sonucu_id'],
                'hedef' => max(0, (int) $satir['hedef']),
                'kayit' => null,
            ];
        }

        $kayitStmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE firma_id = ? AND tarih >= ?");
        $kayitStmt->execute([$firmaId, $baslangic]);
        foreach ($kayitStmt->fetchAll(PDO::FETCH_ASSOC) as $kayit) {
            $anahtar = $kayit['personel_id'] . '|' . $kayit['tarih'] . '|' . $kayit['is_emri_sonucu_id'];
            if (!isset($sonuc[$anahtar])) {
                $sonuc[$anahtar] = [
                    'personel_id' => (int) $kayit['personel_id'],
                    'tarih' => (string) $kayit['tarih'],
                    'is_emri_sonucu_id' => (int) $kayit['is_emri_sonucu_id'],
                    'hedef' => 0,
                    'kayit' => null,
                ];
            }
            $sonuc[$anahtar]['kayit'] = $kayit;
        }

        return array_values($sonuc);
    }

    public function ekipBul(int $firmaId, int $personelId, string $tarih): int
    {
        $stmt = $this->db->prepare("SELECT ekip_kodu_id FROM personel_ekip_gecmisi
            WHERE personel_id = ? AND firma_id = ?
              AND baslangic_tarihi <= ?
              AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
            ORDER BY baslangic_tarihi DESC LIMIT 1");
        $stmt->execute([$personelId, $firmaId, $tarih, $tarih]);
        return (int) $stmt->fetchColumn();
    }

    public function kaydet(int $firmaId, array $veri): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, personel_id, tarih, is_emri_sonucu_id, ekip_id, aparat_tip_id, islenen_adet)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), ekip_id = VALUES(ekip_id),
                aparat_tip_id = VALUES(aparat_tip_id), islenen_adet = VALUES(islenen_adet)");
        $stmt->execute([
            $firmaId,
            (int) $veri['personel_id'],
            $veri['tarih'],
            (int) $veri['is_emri_sonucu_id'],
            $veri['ekip_id'] ?: null,
            (int) $veri['aparat_tip_id'],
            (int) $veri['islenen_adet'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function kilitAl(int $firmaId): bool
    {
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, 0)");
        $stmt->execute(['aparat_otomatik_kirma_' . $firmaId]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function kilitBirak(int $firmaId): void
    {
        $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
        $stmt->execute(['aparat_otomatik_kirma_' . $firmaId]);
    }
}
