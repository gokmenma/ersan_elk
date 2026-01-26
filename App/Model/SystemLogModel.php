<?php

namespace App\Model;

use App\Model\Model;
use PDO;

/**
 * SystemLogModel Class
 * Handles system logging operations
 */
class SystemLogModel extends Model
{
    protected $table = 'system_logs';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Log a critical action
     */
    public function logAction($userId, $actionType, $description)
    {
        return $this->saveWithAttr([
            'user_id' => $userId,
            'firma_id' => $_SESSION['firma_id'] ?? 0,
            'action_type' => $actionType,
            'description' => $description
        ]);
    }

    /**
     * Get recent logs with user details
     */
    public function getRecentLogs($limit = 10)
    {
        $sql = "SELECT l.*, u.adi_soyadi 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.firma_id = ?
                ORDER BY l.created_at DESC 
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $_SESSION["firma_id"], PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
