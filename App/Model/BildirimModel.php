<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class BildirimModel extends Model
{
    protected $table = 'bildirimler';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Kullanıcıya bildirim oluştur
     */
    public function createNotification($userId, $title, $message, $link = null, $icon = 'bell', $color = 'primary')
    {
        $userModel = new \App\Model\UserModel();
        $user = $userModel->find($userId);

        if (!$user || $user->durum != 'Aktif') {
            return false;
        }

        return $this->saveWithAttr([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'icon' => $icon,
            'color' => $color,
            'is_read' => 0
        ]);
    }

    /**
     * Kullanıcının okunmamış bildirimlerini getir
     */
    public function getUnreadNotifications($userId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kullanıcının son bildirimlerini getir (okunmuş/okunmamış)
     */
    public function getRecentNotifications($userId, $limit = 5)
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bildirimi okundu olarak işaretle
     */
    public function markAsRead($id, $userId)
    {
        $sql = "UPDATE {$this->table} SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Tümünü okundu işaretle
     */
    public function markAllAsRead($userId)
    {
        $sql = "UPDATE {$this->table} SET is_read = 1 WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Talep/avans/izin bildiriminin ilgili kaydını okundu işaretle
     */
    public function markRequestNotificationAsRead($userId, $tab, $requestId)
    {
        $userId = (int) $userId;
        $requestId = (int) $requestId;
        $tab = trim((string) $tab);

        if ($userId <= 0 || $requestId <= 0 || !in_array($tab, ['avans', 'izin', 'talep'], true)) {
            return false;
        }

        $exactLink = "index.php?p=talepler/list&tab={$tab}&id={$requestId}";
        $pattern = "%p=talepler/list%tab={$tab}%id={$requestId}%";

        $sql = "UPDATE {$this->table}
                SET is_read = 1
                WHERE user_id = ?
                  AND is_read = 0
                  AND (
                    link = ?
                    OR REPLACE(link, '&amp;', '&') = ?
                    OR link LIKE ?
                    OR REPLACE(link, '&amp;', '&') LIKE ?
                  )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $exactLink, $exactLink, $pattern, $pattern]);
    }

    /**
     * Okunmamış bildirim sayısını getir
     */
    public function getUnreadCount($userId)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? $result->count : 0;
    }

    /**
     * Kullanıcının son bildirim ID'sini getir
     */
    public function getLastNotificationId($userId)
    {
        $sql = "SELECT MAX(id) as last_id FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? ($result->last_id ?? 0) : 0;
    }
}
