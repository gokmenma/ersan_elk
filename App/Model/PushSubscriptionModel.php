<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PushSubscriptionModel extends Model
{
    protected $table = 'push_subscriptions';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Abonelik kaydeder veya günceller
     */
    public function saveSubscription($personelId, $endpoint, $publicKey, $authToken, $contentEncoding = 'aes128gcm')
    {
        // Önce bu endpoint var mı kontrol et
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        $existing = $stmt->fetch(PDO::FETCH_OBJ);

        if ($existing) {
            // Güncelle
            $sql = "UPDATE {$this->table} SET 
                    personel_id = ?, 
                    public_key = ?, 
                    auth_token = ?, 
                    content_encoding = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$personelId, $publicKey, $authToken, $contentEncoding, $existing->id]);
        } else {
            // Ekle
            $sql = "INSERT INTO {$this->table} (personel_id, endpoint, public_key, auth_token, content_encoding) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$personelId, $endpoint, $publicKey, $authToken, $contentEncoding]);
        }
    }

    /**
     * Personelin tüm aboneliklerini getirir
     */
    public function getSubscriptionsByPersonel($personelId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE personel_id = ?");
        $stmt->execute([$personelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // WebPush kütüphanesi array bekler
    }

    /**
     * Endpoint'e göre abonelik siler (Geçersiz abonelikler için)
     */
    public function deleteByEndpoint($endpoint)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE endpoint = ?");
        return $stmt->execute([$endpoint]);
    }
}
