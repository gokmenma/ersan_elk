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
     * Kullanıcı aboneliği kaydeder veya günceller
     */
    public function saveUserSubscription($userId, $endpoint, $publicKey, $authToken, $contentEncoding = 'aes128gcm')
    {
        // Önce bu endpoint var mı kontrol et
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        $existing = $stmt->fetch(PDO::FETCH_OBJ);

        if ($existing) {
            // Güncelle
            $sql = "UPDATE {$this->table} SET 
                    user_id = ?, 
                    public_key = ?, 
                    auth_token = ?, 
                    content_encoding = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$userId, $publicKey, $authToken, $contentEncoding, $existing->id]);
        } else {
            // Ekle
            $sql = "INSERT INTO {$this->table} (user_id, endpoint, public_key, auth_token, content_encoding) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$userId, $endpoint, $publicKey, $authToken, $contentEncoding]);
        }
    }

    /**
     * Personelin tüm aboneliklerini getirir
     */
    public function getSubscriptionsByPersonel($personelId)
    {
        $stmt = $this->db->prepare("SELECT ps.* FROM {$this->table} ps 
                                   JOIN personel p ON ps.personel_id = p.id 
                                   WHERE ps.personel_id = ? 
                                   AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')
                                   AND p.silinme_tarihi IS NULL");
        $stmt->execute([$personelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // WebPush kütüphanesi array bekler
    }

    /**
     * Kullanıcının tüm aboneliklerini getirir
     */
    public function getSubscriptionsByUser($userId)
    {
        $stmt = $this->db->prepare("SELECT ps.* FROM {$this->table} ps 
                                   JOIN users u ON ps.user_id = u.id 
                                   WHERE ps.user_id = ? 
                                   AND u.durum = 'Aktif'");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Endpoint'e göre abonelik siler (Geçersiz abonelikler için)
     */
    public function deleteByEndpoint($endpoint)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE endpoint = ?");
        return $stmt->execute([$endpoint]);
    }

    /**
     * Kullanıcının tüm aboneliklerini siler
     */
    public function deleteByUser($userId)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Personelin tüm aboneliklerini siler
     */
    public function deleteByPersonel($personelId)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE personel_id = ?");
        return $stmt->execute([$personelId]);
    }

    /**
     * Kullanıcının aboneliği var mı kontrol eder
     */
    public function checkUserSubscription($userId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
    /**
     * Personelin aboneliği var mı kontrol eder
     */
    public function checkPersonelSubscription($personelId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE personel_id = ?");
        $stmt->execute([$personelId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
