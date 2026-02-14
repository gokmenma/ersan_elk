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

    /**
     * Get all logs with filtering
     */
    public function getAllLogs($filters = [])
    {
        $conditions = ['l.firma_id = ?'];
        $params = [$_SESSION['firma_id']];

        if (!empty($filters['action_type'])) {
            $conditions[] = 'l.action_type = ?';
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'l.user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_start'])) {
            $conditions[] = 'DATE(l.created_at) >= ?';
            $params[] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $conditions[] = 'DATE(l.created_at) <= ?';
            $params[] = $filters['date_end'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(l.description LIKE ? OR l.action_type LIKE ? OR u.adi_soyadi LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        $sql = "SELECT l.*, u.adi_soyadi 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE {$where}
                ORDER BY l.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get distinct action types for filter dropdown
     */
    public function getDistinctActionTypes()
    {
        $sql = "SELECT DISTINCT action_type 
                FROM {$this->table} 
                WHERE firma_id = ? 
                ORDER BY action_type ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $_SESSION['firma_id'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get distinct users who have logs
     */
    public function getDistinctLogUsers()
    {
        $sql = "SELECT DISTINCT l.user_id, u.adi_soyadi 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.firma_id = ? AND u.adi_soyadi IS NOT NULL
                ORDER BY u.adi_soyadi ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $_SESSION['firma_id'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
