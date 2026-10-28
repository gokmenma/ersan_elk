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
    ];

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
        if (!in_array($notificationType, self::TYPES, true)) {
            throw new \InvalidArgumentException('Geçersiz bildirim türü.');
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
            "SELECT notification_type FROM {$this->table} WHERE user_id = :user_id AND is_enabled = 0"
        );
        $stmt->execute(['user_id' => $userId]);

        return array_values(array_intersect(self::TYPES, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
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

        return $preferences;
    }
}
