<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class UetsEvidenceModel extends Model
{
    protected $table = 'uets_deliller';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Delil kaydeder veya günceller
     */
    public function upsertEvidence(int $uetsMesajId, array $data): int
    {
        try {
            $existing = $this->getByEvidenceId($data['id']);
            $olayZamani = !empty($data['time']) ? date('Y-m-d H:i:s', strtotime($data['time'])) : date('Y-m-d H:i:s');

            if ($existing) {
                return (int) $existing['id'];
            }

            $sql = "INSERT INTO uets_deliller 
                        (uets_mesaj_id, evidence_id, delil_tipi, olay, olay_zamani, raw_json, created_at)
                    VALUES 
                        (:uets_mesaj_id, :evidence_id, :delil_tipi, :olay, :olay_zamani, :raw_json, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'uets_mesaj_id' => $uetsMesajId,
                'evidence_id' => (string) $data['id'],
                'delil_tipi' => (string) ($data['type'] ?? ''),
                'olay' => (string) ($data['event'] ?? ''),
                'olay_zamani' => $olayZamani,
                'raw_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("UetsEvidenceModel::upsertEvidence Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getByEvidenceId(string $evidenceId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM uets_deliller WHERE evidence_id = :evidence_id LIMIT 1");
            $stmt->execute(['evidence_id' => $evidenceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log("UetsEvidenceModel::getByEvidenceId Error: " . $e->getMessage());
            return null;
        }
    }

    public function getByUetsMesajId(int $uetsMesajId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM uets_deliller WHERE uets_mesaj_id = :uets_mesaj_id ORDER BY olay_zamani ASC");
            $stmt->execute(['uets_mesaj_id' => $uetsMesajId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("UetsEvidenceModel::getByUetsMesajId Error: " . $e->getMessage());
            return [];
        }
    }

    public function updatePdfPath(string $evidenceId, string $pdfPath): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE uets_deliller SET delil_pdf_yolu = :pdf_path WHERE evidence_id = :evidence_id");
            return $stmt->execute(['pdf_path' => $pdfPath, 'evidence_id' => $evidenceId]);
        } catch (\PDOException $e) {
            error_log("UetsEvidenceModel::updatePdfPath Error: " . $e->getMessage());
            return false;
        }
    }
}
