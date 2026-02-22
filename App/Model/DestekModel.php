<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DestekModel extends Model
{
    protected $table = 'destek_konusmalar';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    // ============================================================
    // KONUŞMA İŞLEMLERİ
    // ============================================================

    /**
     * Yeni konuşma başlat
     */
    public function startConversation($personelId, $konu = null)
    {
        // Açık konuşma var mı kontrol et
        $existing = $this->getActiveConversation($personelId);
        if ($existing) {
            return $existing->id;
        }

        $sql = "INSERT INTO destek_konusmalar (personel_id, konu, durum, son_mesaj_zamani) 
                VALUES (?, ?, 'acik', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personelId, $konu ?? 'Destek Talebi']);
        return $this->db->lastInsertId();
    }

    /**
     * Personelin aktif konuşmasını getir
     */
    public function getActiveConversation($personelId)
    {
        $sql = "SELECT * FROM destek_konusmalar 
                WHERE personel_id = ? AND durum IN ('acik', 'beklemede') 
                ORDER BY updated_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personelId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Konuşma detayını getir
     */
    public function getConversation($id)
    {
        $sql = "SELECT dk.*, p.adi_soyadi as personel_adi, p.resim_yolu, p.departman, p.cep_telefonu
                FROM destek_konusmalar dk
                LEFT JOIN personel p ON p.id = dk.personel_id
                WHERE dk.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Tüm konuşmaları listele (yönetici için)
     */
    public function getAllConversations($durum = null, $limit = 50)
    {
        $sql = "SELECT dk.*, p.adi_soyadi as personel_adi, p.resim_yolu, p.departman
                FROM destek_konusmalar dk
                LEFT JOIN personel p ON p.id = dk.personel_id
                WHERE dk.deleted_at IS NULL";

        $params = [];
        if ($durum) {
            $sql .= " AND dk.durum = ?";
            $params[] = $durum;
        }

        $sql .= " ORDER BY dk.son_mesaj_zamani DESC LIMIT ?";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif konuşmaları getir (yönetici için - okunmamış dahil)
     */
    public function getActiveConversations()
    {
        $sql = "SELECT dk.*, p.adi_soyadi as personel_adi, p.resim_yolu, p.departman
                FROM destek_konusmalar dk
                LEFT JOIN personel p ON p.id = dk.personel_id
                WHERE dk.durum IN ('acik', 'beklemede') AND dk.deleted_at IS NULL
                ORDER BY dk.okunmamis_yonetici DESC, dk.son_mesaj_zamani DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Konuşmayı soft delete yap
     */
    public function softDeleteConversation($id)
    {
        $sql = "UPDATE destek_konusmalar SET deleted_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Konuşma durumunu güncelle
     */
    public function updateStatus($id, $durum)
    {
        $sql = "UPDATE destek_konusmalar SET durum = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$durum, $id]);
    }

    /**
     * Konuşmayı yöneticiye ata
     */
    public function assignToUser($konusmaId, $userId)
    {
        $sql = "UPDATE destek_konusmalar SET atanan_user_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $konusmaId]);
    }

    /**
     * Toplam okunmamış mesaj sayısı (yönetici için)
     */
    public function getTotalUnreadForAdmin()
    {
        $sql = "SELECT SUM(okunmamis_yonetici) as toplam FROM destek_konusmalar WHERE durum IN ('acik', 'beklemede')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? (int) $result->toplam : 0;
    }

    /**
     * Toplam okunmamış mesaj sayısı (personel için)
     */
    public function getUnreadForPersonel($personelId)
    {
        $sql = "SELECT SUM(okunmamis_personel) as toplam FROM destek_konusmalar 
                WHERE personel_id = ? AND durum IN ('acik', 'beklemede')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personelId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? (int) $result->toplam : 0;
    }

    // ============================================================
    // MESAJ İŞLEMLERİ
    // ============================================================

    /**
     * Mesaj gönder
     */
    public function sendMessage($konusmaId, $gondericiBilgi, $mesaj, $dosyaUrl = null, $dosyaTip = null, $reopenIfClosed = true)
    {
        // Mesajı kaydet
        $sql = "INSERT INTO destek_mesajlar (konusma_id, gonderen_tip, gonderen_id, mesaj, dosya_url, dosya_tip) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $konusmaId,
            $gondericiBilgi['tip'],
            $gondericiBilgi['id'],
            $mesaj,
            $dosyaUrl,
            $dosyaTip
        ]);

        $mesajId = $this->db->lastInsertId();

        // Konuşmayı güncelle
        $onizleme = mb_substr(strip_tags($mesaj), 0, 100);
        $unreadField = $gondericiBilgi['tip'] === 'personel' ? 'okunmamis_yonetici' : 'okunmamis_personel';

        if ($reopenIfClosed) {
            // Personel mesaj gönderirse kapalı konuşmayı tekrar aç ve soft delete'i kaldır
            $sql = "UPDATE destek_konusmalar 
                    SET son_mesaj_zamani = NOW(), 
                        son_mesaj_onizleme = ?,
                        {$unreadField} = {$unreadField} + 1,
                        durum = CASE WHEN durum = 'kapali' THEN 'acik' ELSE durum END,
                        deleted_at = NULL
                    WHERE id = ?";
        } else {
            // Sistem/yönetici kapatma mesajlarında durumu değiştirme
            $sql = "UPDATE destek_konusmalar 
                    SET son_mesaj_zamani = NOW(), 
                        son_mesaj_onizleme = ?
                    WHERE id = ?";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$onizleme, $konusmaId]);

        return $mesajId;
    }

    /**
     * Sistem mesajı gönder (otomatik yanıt) - konuşmayı tekrar açmaz
     */
    public function sendSystemMessage($konusmaId, $mesaj)
    {
        return $this->sendMessage($konusmaId, [
            'tip' => 'sistem',
            'id' => 0
        ], $mesaj, null, null, false);
    }

    /**
     * Konuşmanın mesajlarını getir
     */
    public function getMessages($konusmaId, $afterId = 0, $limit = 50)
    {
        $sql = "SELECT dm.*, 
                CASE 
                    WHEN dm.gonderen_tip = 'personel' THEN p.adi_soyadi
                    WHEN dm.gonderen_tip = 'yonetici' THEN u.adi_soyadi
                    ELSE 'Sistem'
                END as gonderen_adi
                FROM destek_mesajlar dm
                LEFT JOIN personel p ON dm.gonderen_tip = 'personel' AND p.id = dm.gonderen_id
                LEFT JOIN users u ON dm.gonderen_tip = 'yonetici' AND u.id = dm.gonderen_id
                WHERE dm.konusma_id = ?";

        $params = [$konusmaId];

        if ($afterId > 0) {
            $sql .= " AND dm.id > ?";
            $params[] = $afterId;
        }

        $sql .= " ORDER BY dm.created_at ASC LIMIT ?";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Mesajları okundu işaretle
     */
    public function markMessagesAsRead($konusmaId, $okuyanTip)
    {
        // Mesajları okundu yap
        $gondericiBit = ($okuyanTip === 'personel') ? 'yonetici' : 'personel';
        $sql = "UPDATE destek_mesajlar SET okundu = 1 
                WHERE konusma_id = ? AND gonderen_tip IN (?, 'sistem') AND okundu = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$konusmaId, $gondericiBit]);

        // Konuşma okunmamış sayısını sıfırla
        $unreadField = ($okuyanTip === 'personel') ? 'okunmamis_personel' : 'okunmamis_yonetici';
        $sql = "UPDATE destek_konusmalar SET {$unreadField} = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$konusmaId]);

        return true;
    }

    /**
     * Son mesaj ID'sini getir (SSE için)
     */
    public function getLastMessageId($konusmaId)
    {
        $sql = "SELECT MAX(id) as last_id FROM destek_mesajlar WHERE konusma_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$konusmaId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? ($result->last_id ?? 0) : 0;
    }

    /**
     * Yönetici için yeni mesajları kontrol et (SSE polling)
     */
    public function checkNewMessagesForAdmin($lastCheckTime)
    {
        $sql = "SELECT dk.id as konusma_id, dk.personel_id, dk.okunmamis_yonetici,
                       p.adi_soyadi as personel_adi, p.resim_yolu,
                       dm.mesaj, dm.created_at, dm.gonderen_tip
                FROM destek_mesajlar dm
                JOIN destek_konusmalar dk ON dk.id = dm.konusma_id
                LEFT JOIN personel p ON p.id = dk.personel_id
                WHERE dm.created_at > ? AND dm.gonderen_tip = 'personel'
                ORDER BY dm.created_at DESC
                LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lastCheckTime]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personel için yeni mesajları kontrol et (SSE polling)
     */
    public function checkNewMessagesForPersonel($personelId, $lastCheckTime)
    {
        $sql = "SELECT dm.*, dk.personel_id
                FROM destek_mesajlar dm
                JOIN destek_konusmalar dk ON dk.id = dm.konusma_id
                WHERE dk.personel_id = ? 
                AND dm.created_at > ? 
                AND dm.gonderen_tip IN ('yonetici', 'sistem')
                ORDER BY dm.created_at DESC
                LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personelId, $lastCheckTime]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Çalışma saatleri kontrolü (Dinamik Ayarlardan)
     */
    public function isWorkingHours()
    {
        $Settings = new \App\Model\SettingsModel();
        $gunlerStr = $Settings->getSettings('canli_destek_gunler') ?? '1,2,3,4,5,6';
        $gunler = explode(',', $gunlerStr);
        $baslama = $Settings->getSettings('canli_destek_baslama_saati') ?? '08:00';
        $bitis = $Settings->getSettings('canli_destek_bitis_saati') ?? '18:00';

        $dayOfWeek = (int) date('N'); // 1=Pazartesi, 7=Pazar
        if (!in_array((string) $dayOfWeek, $gunler)) {
            return false;
        }

        $currentTime = date('H:i');
        return ($currentTime >= $baslama && $currentTime <= $bitis);
    }

    /**
     * Çalışma saatleri dışı otomatik mesaj (Dinamik Ayarlardan)
     */
    public function getOutOfHoursMessage()
    {
        $Settings = new \App\Model\SettingsModel();
        $gunlerStr = $Settings->getSettings('canli_destek_gunler') ?? '1,2,3,4,5,6';
        $gunler = explode(',', $gunlerStr);
        $baslama = $Settings->getSettings('canli_destek_baslama_saati') ?? '08:00';
        $bitis = $Settings->getSettings('canli_destek_bitis_saati') ?? '18:00';

        $dayOfWeek = (int) date('N');

        if (!in_array((string) $dayOfWeek, $gunler)) {
            return "🕐 Bugün mesai saatleri dışındayız. İlk mesai gününde saat {$baslama}'da sizinle iletişime geçeceğiz. Acil durumlar için lütfen telefon ile ulaşınız.";
        }

        $currentTime = date('H:i');
        if ($currentTime < $baslama) {
            return "🕐 Mesai saatleri dışındasınız. Saat {$baslama}'da sizinle iletişime geçeceğiz. Mesajınız kaydedildi.";
        }

        return "🕐 Mesai saatlerimiz {$baslama}-{$bitis} arasıdır. Mesajınız kaydedildi, en kısa sürede dönüş yapacağız.";
    }

    /**
     * Konuşma geçmişini getir (personel için)
     */
    public function getConversationHistory($personelId, $limit = 10)
    {
        $sql = "SELECT dk.*, 
                (SELECT COUNT(*) FROM destek_mesajlar WHERE konusma_id = dk.id) as mesaj_sayisi
                FROM destek_konusmalar dk
                WHERE dk.personel_id = ?
                ORDER BY dk.son_mesaj_zamani DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $personelId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
