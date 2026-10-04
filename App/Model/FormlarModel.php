<?php

namespace App\Model;

use PDO;

class FormlarModel extends Model
{
    protected $table = 'formlar';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAll(int $firma_id): array
    {
        $stmt = $this->db->prepare("
            SELECT f.*, u.adi_soyadi as ekleyen_adi 
            FROM {$this->table} f
            LEFT JOIN users u ON f.ekleyen_id = u.id
            WHERE f.firma_id = ?
            ORDER BY f.id DESC
        ");
        $stmt->execute([$firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getById(int $id, int $firma_id): ?object
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ?");
        $stmt->execute([$id, $firma_id]);
        $res = $stmt->fetch(PDO::FETCH_OBJ);
        return $res ? $res : null;
    }

    public function getStats(int $firma_id): object
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam,
                SUM(CASE WHEN LOWER(dosya_adi) LIKE '%.docx' OR LOWER(dosya_adi) LIKE '%.doc' THEN 1 ELSE 0 END) as word_sayisi,
                SUM(CASE WHEN LOWER(dosya_adi) LIKE '%.xlsx' OR LOWER(dosya_adi) LIKE '%.xls' THEN 1 ELSE 0 END) as excel_sayisi,
                SUM(CASE WHEN LOWER(dosya_adi) LIKE '%.pdf' THEN 1 ELSE 0 END) as pdf_sayisi
            FROM {$this->table}
            WHERE firma_id = ?
        ");
        $stmt->execute([$firma_id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return (object) [
            'toplam' => (int) ($row->toplam ?? 0),
            'word' => (int) ($row->word_sayisi ?? 0),
            'excel' => (int) ($row->excel_sayisi ?? 0),
            'pdf' => (int) ($row->pdf_sayisi ?? 0)
        ];
    }
}
