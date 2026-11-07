<?php

namespace App\Model;

use PDO;

class UserNotificationPreferenceModel extends Model
{
    public const TYPE_KACAK_CREATED = 'kacak_created';
    public const TYPE_IHBAR_CREATED = 'ihbar_created';
    public const TYPE_ADVANCE_REQUEST = 'advance_request';
    public const TYPE_LEAVE_REQUEST = 'leave_request';
    public const TYPE_GENERAL_REQUEST = 'general_request';
    public const TYPE_FAULT_REQUEST = 'fault_request';
    public const TYPE_SUPPORT = 'support';
    public const TYPE_KM = 'km';
    public const TYPE_TASK = 'task';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_SHIFT = 'shift';
    public const TYPE_EINVOICE = 'einvoice';
    public const TYPE_KEP = 'kep';
    public const TYPE_PAYROLL = 'payroll';
    public const TYPE_KESME_ACMA = 'kesme_acma';
    public const TYPE_IK_APPLICATION = 'ik_application';
    public const TYPE_SITE_MESSAGE = 'site_message';

    public const OPT_IN_TYPES = [self::TYPE_IK_APPLICATION];

    public const TYPES = [
        self::TYPE_KACAK_CREATED,
        self::TYPE_IHBAR_CREATED,
        self::TYPE_ADVANCE_REQUEST,
        self::TYPE_LEAVE_REQUEST,
        self::TYPE_GENERAL_REQUEST,
        self::TYPE_FAULT_REQUEST,
        self::TYPE_SUPPORT,
        self::TYPE_KM,
        self::TYPE_TASK,
        self::TYPE_DOCUMENT,
        self::TYPE_SHIFT,
        self::TYPE_EINVOICE,
        self::TYPE_KEP,
        self::TYPE_PAYROLL,
        self::TYPE_KESME_ACMA,
        self::TYPE_IK_APPLICATION,
        self::TYPE_SITE_MESSAGE,
    ];

    public const LABELS = [
        self::TYPE_KACAK_CREATED => ['Kaçak İşlemleri', 'Yeni kaçak tutanağı bildirildiğinde haber alın.'],
        self::TYPE_IHBAR_CREATED => ['İhbarlar', 'Yeni bir kaçak su ihbarı oluşturulduğunda haber alın.'],
        self::TYPE_ADVANCE_REQUEST => ['Avans Talepleri', 'Yeni avans taleplerinden haberdar olun.'],
        self::TYPE_LEAVE_REQUEST => ['İzin Talepleri', 'Yeni izin taleplerinden haberdar olun.'],
        self::TYPE_GENERAL_REQUEST => ['Genel Talepler', 'Öneri, şikâyet, istek ve diğer talepler için bildirim alın.'],
        self::TYPE_FAULT_REQUEST => ['Arıza Talepleri', 'Yeni arıza taleplerinden haberdar olun.'],
        self::TYPE_SUPPORT => ['Destek Talepleri', 'Destek talepleri, yanıtları ve durum değişiklikleri için bildirim alın.'],
        self::TYPE_KM => ['KM Bildirimleri', 'KM hatırlatmaları ve manuel onay bildirimlerini alın.'],
        self::TYPE_TASK => ['Görev Bildirimleri', 'Görev zamanı ve görev süreçleri için bildirim alın.'],
        self::TYPE_DOCUMENT => ['Evrak Bildirimleri', 'Tarafınıza evrak zimmetlendiğinde bildirim alın.'],
        self::TYPE_SHIFT => ['Nöbet Bildirimleri', 'Nöbet talebi, değişim ve mazeret bildirimlerini alın.'],
        self::TYPE_EINVOICE => ['E-Fatura Bildirimleri', 'Yeni gelen fatura alındığında bildirim alın.'],
        self::TYPE_KEP => ['KEP Bildirimleri', 'Yeni gelen KEP ve otomatik KEP alım hataları için bildirim alın.'],
        self::TYPE_PAYROLL => ['Bordro Bildirimleri', 'Ücret pusulası itirazı gibi bordro bildirimlerini alın.'],
        self::TYPE_KESME_ACMA => ['Kesme/Açma Uyarıları', 'Yönetim eşiğini aşan kesme/açma uyarıları için bildirim alın.'],
        self::TYPE_IK_APPLICATION => ['İşe Alım Başvuruları', 'Yeni aday başvurusu geldiğinde bildirim alın. Varsayılan kapalıdır; yönetici açar.'],
        self::TYPE_SITE_MESSAGE => ['Site Mesajları', 'Kurumsal siteden yeni iletişim mesajı geldiğinde bildirim alın.'],
    ];

    protected $table = 'user_notification_preferences';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private static ?bool $hasAdminColumn = null;

    private function hasAdminColumn(): bool
    {
        if (self::$hasAdminColumn === null) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = 'admin_disabled'"
            );
            $stmt->execute(['t' => $this->table]);
            self::$hasAdminColumn = (int) $stmt->fetchColumn() > 0;
        }

        return self::$hasAdminColumn;
    }

    private static ?bool $hasGrantColumn = null;

    private function hasGrantColumn(): bool
    {
        if (self::$hasGrantColumn === null) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = 'admin_granted'"
            );
            $stmt->execute(['t' => $this->table]);
            self::$hasGrantColumn = (int) $stmt->fetchColumn() > 0;
        }

        return self::$hasGrantColumn;
    }

    private function getGrantedOptInTypes(int $userId): array
    {
        if (!$this->hasGrantColumn()) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT notification_type FROM {$this->table} WHERE user_id = :user_id AND admin_granted = 1"
        );
        $stmt->execute(['user_id' => $userId]);

        return array_values(array_intersect(self::OPT_IN_TYPES, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    private function getUngrantedOptInTypes(int $userId): array
    {
        return array_values(array_diff(self::OPT_IN_TYPES, $this->getGrantedOptInTypes($userId)));
    }

    public function isEnabled(int $userId, string $notificationType): bool
    {
        $adminColumn = $this->hasAdminColumn() ? 'admin_disabled' : '0';
        $stmt = $this->db->prepare(
            "SELECT is_enabled, {$adminColumn} AS admin_disabled
             FROM {$this->table}
             WHERE user_id = :user_id AND notification_type = :notification_type
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'notification_type' => $notificationType,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (in_array($notificationType, self::OPT_IN_TYPES, true)
            && !in_array($notificationType, $this->getGrantedOptInTypes($userId), true)) {
            return false;
        }

        if ($row === false) {
            return true;
        }

        return (int) $row['admin_disabled'] === 0 && (int) $row['is_enabled'] === 1;
    }

    public function setPreference(int $userId, string $notificationType, bool $isEnabled): void
    {
        if (!in_array($notificationType, self::TYPES, true)) {
            throw new \InvalidArgumentException('Geçersiz bildirim türü.');
        }

        if (in_array($notificationType, $this->getAdminDisabledTypes($userId), true)) {
            return;
        }

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

    public function getDisabledTypes(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT notification_type FROM {$this->table} WHERE user_id = :user_id AND (is_enabled = 0"
            . ($this->hasAdminColumn() ? ' OR admin_disabled = 1' : '') . ')'
        );
        $stmt->execute(['user_id' => $userId]);

        $disabled = array_values(array_intersect(self::TYPES, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));

        return array_values(array_unique(array_merge($disabled, $this->getUngrantedOptInTypes($userId))));
    }

    public function getPreferences(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT notification_type, is_enabled FROM {$this->table} WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
        $saved = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $preferences = [];
        foreach (self::TYPES as $type) {
            $preferences[$type] = !array_key_exists($type, $saved) || (int) $saved[$type] === 1;
        }

        foreach ($this->getAdminDisabledTypes($userId) as $type) {
            $preferences[$type] = false;
        }

        return $preferences;
    }

    public function getAdminDisabledTypes(int $userId): array
    {
        $disabled = [];
        if ($this->hasAdminColumn()) {
            $stmt = $this->db->prepare(
                "SELECT notification_type FROM {$this->table} WHERE user_id = :user_id AND admin_disabled = 1"
            );
            $stmt->execute(['user_id' => $userId]);
            $disabled = array_values(array_intersect(self::TYPES, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        }

        return array_values(array_unique(array_merge($disabled, $this->getUngrantedOptInTypes($userId))));
    }

    public function getUserOffTypes(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT notification_type FROM {$this->table} WHERE user_id = :user_id AND is_enabled = 0"
            . ($this->hasAdminColumn() ? ' AND admin_disabled = 0' : '')
        );
        $stmt->execute(['user_id' => $userId]);
        $off = array_values(array_intersect(self::TYPES, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));

        return array_values(array_diff($off, $this->getUngrantedOptInTypes($userId)));
    }

    public function getUserOffMap(int $userId): array
    {
        $off = $this->getUserOffTypes($userId);
        $map = [];
        foreach (self::TYPES as $type) {
            $map[$type] = in_array($type, $off, true);
        }

        return $map;
    }

    public function setAdminDisabledTypes(int $userId, array $disabledTypes): void
    {
        if (!$this->hasAdminColumn()) {
            throw new \RuntimeException('admin_disabled kolonu bulunamadı; SQL scriptini çalıştırın.');
        }
        if (!$this->hasGrantColumn()) {
            throw new \RuntimeException('admin_granted kolonu bulunamadı; SQL scriptini çalıştırın.');
        }

        $userOff = $this->getUserOffTypes($userId);

        $lockStmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, notification_type, is_enabled, admin_disabled, created_at, updated_at)
             VALUES (:user_id, :notification_type, 1, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE admin_disabled = 1, updated_at = NOW()"
        );
        $openStmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, notification_type, is_enabled, admin_disabled, created_at, updated_at)
             VALUES (:user_id, :notification_type, 1, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE admin_disabled = 0, is_enabled = 1, updated_at = NOW()"
        );
        $grantOnStmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, notification_type, is_enabled, admin_disabled, admin_granted, created_at, updated_at)
             VALUES (:user_id, :notification_type, 1, 0, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE admin_disabled = 0, admin_granted = 1, is_enabled = 1, updated_at = NOW()"
        );
        $grantOffStmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, notification_type, is_enabled, admin_disabled, admin_granted, created_at, updated_at)
             VALUES (:user_id, :notification_type, 1, 0, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE admin_disabled = 0, admin_granted = 0, updated_at = NOW()"
        );

        foreach (self::TYPES as $type) {
            $blocked = in_array($type, $disabledTypes, true);
            if ($blocked && in_array($type, $userOff, true)) {
                continue;
            }

            if (in_array($type, self::OPT_IN_TYPES, true)) {
                $stmt = $blocked ? $grantOffStmt : $grantOnStmt;
            } else {
                $stmt = $blocked ? $lockStmt : $openStmt;
            }

            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':notification_type', $type, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    public function getAdminDisabledMap(int $userId): array
    {
        $disabled = $this->getAdminDisabledTypes($userId);
        $map = [];
        foreach (self::TYPES as $type) {
            $map[$type] = in_array($type, $disabled, true);
        }

        return $map;
    }
}
