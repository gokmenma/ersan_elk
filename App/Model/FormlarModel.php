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
}
