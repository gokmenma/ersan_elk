<?php

namespace App\Model;

use PDO;

class UserNotificationPreferenceModel extends Model
{
    public const TYPE_IHBAR_CREATED = 'ihbar_created';

    protected $table = 'user_notification_preferences';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function isEnabled(int $userId, string $notificationType): bool
    {
        $stmt = $this->db->prepare(
            "SELECT is_enabled
             FROM {$this->table}
             WHERE user_id = :user_id AND notification_type = :notification_type
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'notification_type' => $notificationType,
        ]);

        $value = $stmt->fetchColumn();

        // Tercihi henüz kaydedilmemiş kullanıcıların mevcut bildirim akışı değişmez.
        return $value === false || (int) $value === 1;
    }

    public function setPreference(int $userId, string $notificationType, bool $isEnabled): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, notification_type, is_enabled, created_at, updated_at)
             VALUES (:user_id, :notification_type, :is_enabled, NOW(), NOW())
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_at = NOW()"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':notification_type', $notificationType, PDO::PARAM_STR);
        $stmt->bindValue(':is_enabled', $isEnabled ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }
}
