<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelFazlaMesaileriModel extends Model
{
    protected $table = 'personel_fazla_mesaileri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirtilen tarih aralığında personellerin fazla mesailerini getirir.
     * Dönen dizi formatı: [personel_id => ['YYYY-MM-DD' => saat, ...]]
     *
     * @param array $personelIds
     * @param string $startDate (Y-m-d)
     * @param string $endDate (Y-m-d)
     * @param int $firmaId
     * @return array
     */
    public function getPersonellerAyMesaileri(array $personelIds, string $startDate, string $endDate, int $firmaId = 0): array
    {
        if (empty($personelIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($personelIds), '?'));
        $sql = "
            SELECT personel_id, tarih, saat, aciklama, onay_durumu
            FROM {$this->table}
            WHERE personel_id IN ($placeholders)
              AND tarih >= ? AND tarih <= ?
              AND silinme_tarihi IS NULL
              AND saat > 0
            ORDER BY tarih ASC
        ";

        $params = array_merge($personelIds, [$startDate, $endDate]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $pid = (int) $row->personel_id;
            $date = $row->tarih;
            $result[$pid][$date] = [
                'saat' => (float) $row->saat,
                'aciklama' => (string) ($row->aciklama ?? ''),
                'onay_durumu' => (string) ($row->onay_durumu ?? 'Onaylandı'),
            ];
        }

        return $result;
    }

    /**
     * Tek bir personelin ay içi fazla mesailerini döner: ['YYYY-MM-DD' => float saat]
     *
     * @param int $personelId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getPersonelAyMesaileri(int $personelId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT tarih, saat, aciklama, onay_durumu
            FROM {$this->table}
            WHERE personel_id = ?
              AND tarih >= ? AND tarih <= ?
              AND silinme_tarihi IS NULL
              AND saat > 0
            ORDER BY tarih ASC
        ");
        $stmt->execute([$personelId, $startDate, $endDate]);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $result[$row->tarih] = (float) $row->saat;
        }

        return $result;
    }

    /**
     * Fazla mesai kaydeder veya günceller.
     * Saat <= 0 ise kaydı soft-delete yapar.
     *
     * @param int $personelId
     * @param string $tarih (Y-m-d)
     * @param float $saat
     * @param int $firmaId
     * @param int $userId
     * @param string|null $aciklama
     * @return bool
     */
    public function saveMesai(int $personelId, string $tarih, float $saat, int $firmaId = 0, int $userId = 0, ?string $aciklama = null): bool
    {
        if ($saat <= 0) {
            return $this->deleteMesai($personelId, $tarih, $userId);
        }

        // Varsa ve silinmişse veya mevcutsa güncelle / ekle
        $checkStmt = $this->db->prepare("
            SELECT id FROM {$this->table} 
            WHERE personel_id = ? AND tarih = ?
            LIMIT 1
        ");
        $checkStmt->execute([$personelId, $tarih]);
        $existingId = $checkStmt->fetchColumn();

        $sourceReset = $this->hasSourceColumns() ? ", kaynak = 'manuel', otomatik_kural = NULL" : '';
        if ($existingId) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET saat = ?, aciklama = ?, onay_durumu = 'Onaylandı',
                    silinme_tarihi = NULL, updated_at = NOW(){$sourceReset}
                WHERE id = ?
            ");
            return $stmt->execute([$saat, $aciklama, $existingId]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (firma_id, personel_id, tarih, saat, aciklama, onay_durumu, kayit_yapan, created_at)
            VALUES (?, ?, ?, ?, ?, 'Onaylandı', ?, NOW())
        ");
        return $stmt->execute([$firmaId, $personelId, $tarih, $saat, $aciklama, $userId]);
    }

    private function hasSourceColumns(): bool
    {
        static $ready = null;
        if ($ready === null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ('kaynak', 'otomatik_kural')");
            $stmt->execute([$this->table]);
            $ready = (int) $stmt->fetchColumn() === 2;
        }
        return $ready;
    }

    /**
     * Belirtilen tarihteki fazla mesai kaydını soft delete yapar.
     *
     * @param int $personelId
     * @param string $tarih
     * @param int $userId
     * @return bool
     */
    public function deleteMesai(int $personelId, string $tarih, int $userId = 0): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET silinme_tarihi = NOW()
            WHERE personel_id = ? AND tarih = ? AND silinme_tarihi IS NULL
        ");
        return $stmt->execute([$personelId, $tarih]);
    }

    /**
     * Bordro için: Personelin dönem içi onaylı toplam fazla mesai saatini hesaplar.
     *
     * @param int $personelId
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getDonemToplamMesaiSaati(int $personelId, string $startDate, string $endDate): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(saat), 0)
            FROM {$this->table}
            WHERE personel_id = ?
              AND tarih >= ? AND tarih <= ?
              AND silinme_tarihi IS NULL
              AND onay_durumu = 'Onaylandı'
        ");
        $stmt->execute([$personelId, $startDate, $endDate]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Bordro için toplu önbellek: Tüm personellerin dönem içi toplam fazla mesai saatini çeker.
     * Format: [personel_id => float $saat]
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $firmaId
     * @return array
     */
    public function getDonemTumPersonellerToplamMesai(string $startDate, string $endDate, int $firmaId = 0): array
    {
        $sql = "
            SELECT personel_id, COALESCE(SUM(saat), 0) as toplam_saat
            FROM {$this->table}
            WHERE tarih >= ? AND tarih <= ?
              AND silinme_tarihi IS NULL
              AND onay_durumu = 'Onaylandı'
        ";
        $params = [$startDate, $endDate];

        if ($firmaId > 0) {
            $sql .= " AND (firma_id = ? OR firma_id = 0)";
            $params[] = $firmaId;
        }

        $sql .= " GROUP BY personel_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $result[(int) $row->personel_id] = (float) $row->toplam_saat;
        }

        return $result;
    }
}
