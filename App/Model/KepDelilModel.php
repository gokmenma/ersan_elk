<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class KepDelilModel extends Model
{
    protected $table = 'kep_deliller';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * KEP yasal delil kaydını ekler veya günceller
     */
    public function upsertDelil(int $kepMesajId, array $data): int
    {
        $delilId = trim((string) ($data['delil_id'] ?? ''));
        if ($delilId === '') {
            return 0;
        }

        $existing = $this->getByDelilId($delilId);
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET 
                delil_tur_id = :delil_tur_id,
                delil_aciklama = :delil_aciklama,
                olay_zamani = :olay_zamani,
                kephs = :kephs
                WHERE delil_id = :delil_id");

            $stmt->execute([
                'delil_id' => $delilId,
                'delil_tur_id' => (int) ($data['delil_tur_id'] ?? 0),
                'delil_aciklama' => (string) ($data['delil_aciklama'] ?? ''),
                'olay_zamani' => !empty($data['tarih']) ? date('Y-m-d H:i:s', strtotime($data['tarih'])) : date('Y-m-d H:i:s'),
                'kephs' => (string) ($data['kephs'] ?? ''),
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO {$this->table} (
            kep_mesaj_id, delil_id, delil_tur_id, delil_aciklama, olay_zamani, kephs, created_at
        ) VALUES (
            :kep_mesaj_id, :delil_id, :delil_tur_id, :delil_aciklama, :olay_zamani, :kephs, NOW()
        )");

        $stmt->execute([
            'kep_mesaj_id' => $kepMesajId,
            'delil_id' => $delilId,
            'delil_tur_id' => (int) ($data['delil_tur_id'] ?? 0),
            'delil_aciklama' => (string) ($data['delil_aciklama'] ?? ''),
            'olay_zamani' => !empty($data['tarih']) ? date('Y-m-d H:i:s', strtotime($data['tarih'])) : date('Y-m-d H:i:s'),
            'kephs' => (string) ($data['kephs'] ?? ''),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * DelilId üzerinden delil kaydını getirir
     */
    public function getByDelilId(string $delilId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE delil_id = :delil_id LIMIT 1");
        $stmt->execute(['delil_id' => trim($delilId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Kep mesajına ait tüm delilleri tarih sırasıyla döner
     */
    public function getDelillerByMesajId(int $kepMesajId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE kep_mesaj_id = :kep_mesaj_id ORDER BY olay_zamani ASC, id ASC");
        $stmt->execute(['kep_mesaj_id' => $kepMesajId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * İndirilen XML dosya yolunu günceller
     */
    public function updateDelilXmlPath(string $delilId, string $filePath): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET delil_xml_yolu = :path WHERE delil_id = :delil_id");
        return $stmt->execute(['delil_id' => trim($delilId), 'path' => $filePath]);
    }
}
