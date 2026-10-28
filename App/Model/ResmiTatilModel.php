<?php

namespace App\Model;

use PDO;
use RuntimeException;

class ResmiTatilModel extends Model
{
    public const TAM = 'tam';
    public const YARIM = 'yarim';

    public function __construct()
    {
        parent::__construct('resmi_tatiller');
    }

    public function isReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resmi_tatiller'
                  AND COLUMN_NAME IN ('firma_id', 'tur', 'yarim_gun_saati', 'fazla_mesai_saat', 'silinme_tarihi')");
            $stmt->execute();
            $ready = (int) $stmt->fetchColumn() === 5;
        }
        return $ready;
    }

    public function holidayFor(int $firmaId, string $date): ?array
    {
        if (!$this->isReady()) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT id, firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat
            FROM resmi_tatiller
            WHERE tatil_tarihi = ? AND firma_id IN (0, ?) AND silinme_tarihi IS NULL
            ORDER BY (firma_id = ?) DESC, id DESC LIMIT 1");
        $stmt->execute([$date, $firmaId, $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function isFullHoliday(int $firmaId, string $date): bool
    {
        return ($this->holidayFor($firmaId, $date)['tur'] ?? null) === self::TAM;
    }

    public function holidaysBetween(int $firmaId, string $startDate, string $endDate): array
    {
        if (!$this->isReady()) {
            return [];
        }
        $stmt = $this->db->prepare("SELECT id, firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat
            FROM resmi_tatiller
            WHERE tatil_tarihi BETWEEN ? AND ? AND firma_id IN (0, ?) AND silinme_tarihi IS NULL
            ORDER BY tatil_tarihi ASC, (firma_id = ?) ASC, id ASC");
        $stmt->execute([$startDate, $endDate, $firmaId, $firmaId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[$row['tatil_tarihi']] = $row;
        }
        return $rows;
    }

    public function findForFirm(int $id, int $firmaId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat
            FROM resmi_tatiller WHERE id = ? AND firma_id IN (0, ?) AND silinme_tarihi IS NULL");
        $stmt->execute([$id, $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveHoliday(array $data, int $firmaId, int $userId): int
    {
        $id = (int) ($data['id'] ?? 0);
        $date = (string) $data['tatil_tarihi'];
        $duplicate = $this->db->prepare("SELECT id FROM resmi_tatiller
            WHERE tatil_tarihi = ? AND firma_id = ? AND silinme_tarihi IS NULL AND id <> ? LIMIT 1");
        $existing = $id > 0 ? $this->findForFirm($id, $firmaId) : null;
        $targetFirmaId = $existing !== null ? (int) $existing['firma_id'] : (int) ($data['firma_id'] ?? $firmaId);
        $duplicate->execute([$date, $targetFirmaId, $id]);
        if ($duplicate->fetchColumn()) {
            throw new RuntimeException('Bu tarih için zaten bir resmi tatil tanımı bulunuyor.');
        }

        $values = [
            $date,
            mb_substr(trim((string) ($data['aciklama'] ?? '')), 0, 100),
            $data['tur'] === self::YARIM ? self::YARIM : self::TAM,
            (string) ($data['yarim_gun_saati'] ?? '13:00:00'),
            (float) ($data['fazla_mesai_saat'] ?? 4),
        ];

        if ($id > 0) {
            if ($existing === null) {
                throw new RuntimeException('Kayıt bulunamadı.');
            }
            $stmt = $this->db->prepare("UPDATE resmi_tatiller SET tatil_tarihi = ?, aciklama = ?, tur = ?,
                yarim_gun_saati = ?, fazla_mesai_saat = ? WHERE id = ? AND silinme_tarihi IS NULL");
            $stmt->execute(array_merge($values, [$id]));
            return $id;
        }

        $stmt = $this->db->prepare("INSERT INTO resmi_tatiller
            (firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat, olusturan_kullanici)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array_merge([$targetFirmaId], $values, [$userId ?: null]));
        return (int) $this->db->lastInsertId();
    }

    public function importRows(array $rows, int $targetFirmaId, int $userId): array
    {
        $result = ['created' => 0, 'updated' => 0];
        $find = $this->db->prepare("SELECT id FROM resmi_tatiller
            WHERE firma_id = ? AND tatil_tarihi = ? AND silinme_tarihi IS NULL ORDER BY id DESC LIMIT 1");
        $update = $this->db->prepare("UPDATE resmi_tatiller SET aciklama = ?, tur = ?, yarim_gun_saati = ?, fazla_mesai_saat = ?
            WHERE id = ?");
        $insert = $this->db->prepare("INSERT INTO resmi_tatiller
            (firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat, olusturan_kullanici)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $values = [
                    mb_substr((string) $row['aciklama'], 0, 100),
                    $row['tur'] === self::YARIM ? self::YARIM : self::TAM,
                    (string) $row['yarim_gun_saati'],
                    (float) $row['fazla_mesai_saat'],
                ];
                $find->execute([$targetFirmaId, $row['tatil_tarihi']]);
                $existingId = (int) $find->fetchColumn();
                if ($existingId > 0) {
                    $update->execute(array_merge($values, [$existingId]));
                    $result['updated']++;
                } else {
                    $insert->execute(array_merge([$targetFirmaId, $row['tatil_tarihi']], $values, [$userId ?: null]));
                    $result['created']++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $result;
    }

    public function listForYear(int $firmaId, int $year): array
    {
        $stmt = $this->db->prepare("SELECT tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat, firma_id
            FROM resmi_tatiller
            WHERE firma_id IN (0, ?) AND silinme_tarihi IS NULL AND YEAR(tatil_tarihi) = ?
            ORDER BY tatil_tarihi ASC, (firma_id = ?) DESC");
        $stmt->execute([$firmaId, $year, $firmaId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[$row['tatil_tarihi']] ??= $row;
        }
        return array_values($rows);
    }

    public function softDeleteHoliday(int $id, int $firmaId, int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE resmi_tatiller SET silinme_tarihi = NOW(), silen_kullanici = ?
            WHERE id = ? AND firma_id IN (0, ?) AND silinme_tarihi IS NULL");
        $stmt->execute([$userId ?: null, $id, $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $bind = [':firma_id' => $firma_id];
        $base = "FROM resmi_tatiller rt WHERE rt.firma_id IN (0, :firma_id) AND rt.silinme_tarihi IS NULL";

        $stmt = $this->db->prepare("SELECT COUNT(*) $base");
        $stmt->execute($bind);
        $total = (int) $stmt->fetchColumn();

        $where = '';
        $year = (int) ($params['yil'] ?? 0);
        if ($year > 0) {
            $where .= " AND YEAR(rt.tatil_tarihi) = :yil";
            $bind[':yil'] = $year;
        }
        $type = (string) ($params['tur'] ?? '');
        if (in_array($type, [self::TAM, self::YARIM], true)) {
            $where .= " AND rt.tur = :tur";
            $bind[':tur'] = $type;
        } elseif ($type === 'gelecek') {
            $where .= " AND rt.tatil_tarihi >= CURDATE()";
        }

        $search = trim((string) ($params['search']['value'] ?? ''));
        if ($search !== '') {
            $where .= " AND (rt.aciklama LIKE :search OR DATE_FORMAT(rt.tatil_tarihi, '%d.%m.%Y') LIKE :search)";
            $bind[':search'] = '%' . $search . '%';
        }

        $colMap = [
            0 => "DATE_FORMAT(rt.tatil_tarihi, '%d.%m.%Y')",
            2 => 'rt.aciklama',
        ];
        foreach (($params['columns'] ?? []) as $i => $column) {
            $val = trim((string) ($column['search']['value'] ?? ''));
            if ($val === '' || !isset($colMap[(int) $i])) {
                continue;
            }
            if (strpos($val, ':') !== false) {
                [, $val] = explode(':', $val, 2);
            }
            $p = ':col_' . (int) $i;
            $where .= " AND {$colMap[(int) $i]} LIKE $p";
            $bind[$p] = '%' . $val . '%';
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) $base $where");
        $stmt->execute($bind);
        $filtered = (int) $stmt->fetchColumn();

        $orderMap = [0 => 'rt.tatil_tarihi', 1 => 'DAYOFWEEK(rt.tatil_tarihi)', 2 => 'rt.aciklama', 3 => 'rt.tur', 4 => 'rt.fazla_mesai_saat'];
        $orderCol = $orderMap[(int) ($params['order'][0]['column'] ?? 0)] ?? 'rt.tatil_tarihi';
        $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $limit = $length > 0 ? ' LIMIT ' . $start . ', ' . min($length, 500) : '';

        $stmt = $this->db->prepare("SELECT rt.id, rt.firma_id, rt.tatil_tarihi, rt.aciklama, rt.tur,
                rt.yarim_gun_saati, rt.fazla_mesai_saat
            $base $where ORDER BY $orderCol $orderDir, rt.id ASC $limit");
        $stmt->execute($bind);

        return [
            'draw' => (int) ($params['draw'] ?? 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $stmt->fetchAll(PDO::FETCH_OBJ),
        ];
    }

    public function summary(int $firma_id, int $year = 0): object
    {
        $year = $year > 0 ? $year : (int) date('Y');
        $stmt = $this->db->prepare("SELECT
                COUNT(*) AS toplam,
                SUM(tur = 'tam') AS tam_gun,
                SUM(tur = 'yarim') AS yarim_gun,
                SUM(tatil_tarihi >= CURDATE()) AS gelecek,
                MIN(CASE WHEN tatil_tarihi >= CURDATE() THEN tatil_tarihi END) AS siradaki_tarih
            FROM resmi_tatiller
            WHERE firma_id IN (0, ?) AND silinme_tarihi IS NULL AND YEAR(tatil_tarihi) = ?");
        $stmt->execute([$firma_id, $year]);
        $row = $stmt->fetch(PDO::FETCH_OBJ) ?: (object) [];

        $next = null;
        if (!empty($row->siradaki_tarih)) {
            $next = $this->holidayFor($firma_id, $row->siradaki_tarih);
        }

        return (object) [
            'yil' => $year,
            'toplam' => (int) ($row->toplam ?? 0),
            'tam_gun' => (int) ($row->tam_gun ?? 0),
            'yarim_gun' => (int) ($row->yarim_gun ?? 0),
            'gelecek' => (int) ($row->gelecek ?? 0),
            'siradaki' => $next,
        ];
    }
}
