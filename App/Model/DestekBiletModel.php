<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class DestekBiletModel extends Model
{
    protected $table = 'destek_biletleri';
    protected $messageTable = 'destek_bilet_mesajlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Yeni bilet oluşturur
     */
    public function createTicket($userId, $personelId, $konu, $kategori, $oncelik, $ilkMesaj, $dosyaYolu = null, $onayDurumu = 'onaylandi')
    {
        try {
            $this->db->beginTransaction();

            if ($this->countActiveTickets((int) $userId) >= 10) {
                throw new \Exception('Aynı anda en fazla 10 açık destek talebiniz olabilir. Önce mevcut taleplerinizden birini kapatın.');
            }

            $refNo = $this->generateRefNo();

            // Bileti ekle
            $sql = "INSERT INTO {$this->table} (user_id, personel_id, ref_no, konu, kategori, oncelik, durum, onay_durumu) 
                    VALUES (?, ?, ?, ?, ?, ?, 'acik', ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $userId, (int) $personelId, $refNo, $konu, $kategori, $oncelik, $onayDurumu]);
            
            $biletId = $this->db->lastInsertId();

            // İlk mesajı ekle (gonderenId her zaman user_id olarak saklayalım ya da personel varsa personel_id?)
            // Aslında mesajlardaki gonderen_id'yi de user_id'ye çevirsek mi?
            // Mevcut sistemde gonderen_tip='personel' ise gonderen_id=personel_id kullanılıyor.
            
            $this->addMessage($biletId, 'personel', $personelId > 0 ? $personelId : $userId, $ilkMesaj, $dosyaYolu);

            $this->db->commit();
            return $biletId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Bilete mesaj ekler
     */
    public function addMessage($biletId, $gonderenTip, $gonderenId, $mesaj, $dosyaYolu = null)
    {
        $sql = "INSERT INTO {$this->messageTable} (bilet_id, gonderen_tip, gonderen_id, mesaj, dosya_yolu) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$biletId, $gonderenTip, $gonderenId, $mesaj, $dosyaYolu]);

        // Bilet durumunu ve güncelleme tarihini güncelle
        $durum = ($gonderenTip === 'yonetici') ? 'yanitlandi' : 'personel_yaniti';
        $sqlStatus = "UPDATE {$this->table} SET durum = ?, guncelleme_tarihi = CURRENT_TIMESTAMP WHERE id = ?";
        $stmtStatus = $this->db->prepare($sqlStatus);
        $stmtStatus->execute([$durum, $biletId]);

        return $this->db->lastInsertId();
    }

    /**
     * Bilet referans numarası üretir (Örn: DST-20240328-001)
     */
    public function generateRefNo()
    {
        $prefix = 'DST-' . date('Ymd') . '-';
        $sql = "SELECT ref_no FROM {$this->table} WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch(PDO::FETCH_OBJ);

        $num = $last ? (intval(substr($last->ref_no, -3)) + 1) : 1;
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Personelin veya kullanıcının biletlerini getirir
     */
    public function getPersonelTickets($userId, $personelId = 0, $status = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($userId > 0 && $personelId > 0) {
            $sql .= " AND (user_id = ? OR personel_id = ?)";
            $params[] = (int)$userId;
            $params[] = (int)$personelId;
        } elseif ($userId > 0) {
            $sql .= " AND (user_id = ? OR personel_id = ?)";
            $params[] = (int)$userId;
            $params[] = (int)$userId;
        } elseif ($personelId > 0) {
            $sql .= " AND (personel_id = ? OR user_id = ?)";
            $params[] = (int)$personelId;
            $params[] = (int)$personelId;
        } else {
            return []; // No valid ID
        }

        if ($status) {
            $sql .= " AND durum = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY guncelleme_tarihi DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        return array_map([$this, 'appendEncryptedId'], $tickets);
    }

    /**
     * Tüm biletleri getirir (Yönetici için)
     */
    public function getAllTickets($status = null, $approvalStatus = null)
    {
        $sql = "SELECT db.*, 
                COALESCE(p.adi_soyadi, u.adi_soyadi) as personel_adi, 
                p.departman 
                FROM {$this->table} db
                LEFT JOIN personel p ON db.personel_id = p.id
                LEFT JOIN users u ON db.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        if ($status) {
            $sql .= " AND db.durum = ?";
            $params[] = $status;
        }

        if ($approvalStatus) {
            $sql .= " AND db.onay_durumu = ?";
            $params[] = $approvalStatus;
        }
        
        $sql .= " ORDER BY db.guncelleme_tarihi DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        return array_map([$this, 'appendEncryptedId'], $tickets);
    }

    /**
     * Bilet detayını ve mesajlarını getirir
     */
    public function getTicketDetails($biletId)
    {
        // Bilet bilgisi
        $sqlTicket = "SELECT db.*, 
                      COALESCE(p.adi_soyadi, u.adi_soyadi) as personel_adi, 
                      p.departman, p.resim_yolu,
                      uk.adi_soyadi as kapatan_adi
                      FROM {$this->table} db
                      LEFT JOIN personel p ON db.personel_id = p.id
                      LEFT JOIN users u ON db.user_id = u.id
                      LEFT JOIN users uk ON db.kapatan_user_id = uk.id
                      WHERE db.id = ?";
        $stmtTicket = $this->db->prepare($sqlTicket);
        $stmtTicket->execute([$biletId]);
        $ticket = $stmtTicket->fetch(PDO::FETCH_OBJ);

        if (!$ticket) return null;

        $ticket = $this->appendEncryptedId($ticket);

        // Mesajlar
        $sqlMessages = "SELECT dm.*, 
                        CASE 
                            WHEN dm.gonderen_tip = 'personel' THEN p.adi_soyadi
                            WHEN dm.gonderen_tip = 'yonetici' THEN u.adi_soyadi
                            ELSE 'Sistem'
                        END as gonderen_adi
                        FROM {$this->messageTable} dm
                        LEFT JOIN personel p ON dm.gonderen_tip = 'personel' AND p.id = dm.gonderen_id
                        LEFT JOIN users u ON (dm.gonderen_tip = 'yonetici' AND u.id = dm.gonderen_id) OR (dm.gonderen_tip = 'personel' AND u.id = dm.gonderen_id AND p.id IS NULL)
                        WHERE dm.bilet_id = ?
                        ORDER BY dm.olusturma_tarihi ASC";
        $stmtMessages = $this->db->prepare($sqlMessages);
        $stmtMessages->execute([$biletId]);
        $ticket->messages = $stmtMessages->fetchAll(PDO::FETCH_OBJ);

        return $ticket;
    }

    /**
     * Bilet durumunu manuel günceller (Kapatma vb.)
     */
    public function updateStatus($biletId, $durum, $userId = null)
    {
        $params = [$durum];
        $sql = "UPDATE {$this->table} SET durum = ?, guncelleme_tarihi = CURRENT_TIMESTAMP";
        
        if ($durum === 'kapali') {
            $sql .= ", kapatan_user_id = ?, kapatma_tarihi = CURRENT_TIMESTAMP";
            $params[] = $userId;
        } elseif ($durum === 'acik') {
            // Reopening clears closure info
            $sql .= ", kapatan_user_id = NULL, kapatma_tarihi = NULL";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $biletId;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Biletin son mesajını getirir.
     */
    public function getLastMessage(int $biletId)
    {
        $sql = "SELECT * FROM {$this->messageTable} WHERE bilet_id = ? ORDER BY olusturma_tarihi DESC, id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$biletId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Talep sahibi yeni mesaj gönderebilir mi kontrol eder.
     * Son mesaj talep sahibinden geldiyse yönetici yanıtı beklenir.
     */
    public function canRequesterReply(int $biletId): bool
    {
        $ticket = $this->find($biletId);
        if (!$ticket || ($ticket->durum ?? '') === 'kapali' || (($ticket->onay_durumu ?? 'onaylandi') !== 'onaylandi')) {
            return false;
        }

        $lastMessage = $this->getLastMessage($biletId);
        if (!$lastMessage) {
            return true;
        }

        return ($lastMessage->gonderen_tip ?? '') !== 'personel';
    }

    /**
     * İstatistikleri getirir
     */
    public function getStats($userId = null, $personelId = null, $approvalStatus = null)
    {
        // Handle old call pattern: getStats($personelId, $approvalStatus)
        // If the second param is a string, it's likely an approval status (from an old call)
        if (is_string($personelId) && $approvalStatus === null) {
            $approvalStatus = $personelId;
            $pId = $userId;
            $uId = $userId;
        } else {
            $uId = $userId;
            $pId = $personelId;
        }

        $sql = "SELECT 
                    COUNT(*) as toplam,
                    SUM(CASE WHEN (durum = 'acik' OR durum = 'personel_yaniti') AND onay_durumu = 'onaylandi' THEN 1 ELSE 0 END) as bekleyen,
                    SUM(CASE WHEN durum = 'yanitlandi' AND onay_durumu = 'onaylandi' THEN 1 ELSE 0 END) as yanitlanan,
                    SUM(CASE WHEN durum = 'kapali' AND onay_durumu = 'onaylandi' THEN 1 ELSE 0 END) as kapali
                FROM {$this->table}";
        
        $params = [];
        $where = [];

        if ($uId > 0 || $pId > 0) {
            $where[] = "(user_id = ? OR personel_id = ?)";
            $params[] = $uId > 0 ? (int)$uId : (int)$pId;
            $params[] = $pId > 0 ? (int)$pId : (int)$uId;
        }

        if ($approvalStatus) {
            $where[] = "onay_durumu = ?";
            $params[] = $approvalStatus;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Kapalı olmayan destek taleplerini sayar.
     */
    public function countActiveTickets(int $userId, int $personelId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE (user_id = ? OR personel_id = ?) AND durum != 'kapali'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$userId, (int)($personelId ?: $userId)]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Kullanıcının yeni destek talebi açıp açamayacağını döndürür.
     */
    public function canCreateNewTicket(int $userId): bool
    {
        return $this->countActiveTickets($userId) < 10;
    }

    protected function appendEncryptedId($ticket)
    {
        if ($ticket && isset($ticket->id)) {
            $ticket->encrypted_id = Security::encrypt((int) $ticket->id);
        }

        return $ticket;
    }

    /**
     * Destek talebi onay durumunu günceller.
     */
    public function updateApprovalStatus(int $biletId, string $status, ?int $onaylayanUserId = null, ?string $onayNotu = null): bool
    {
        $allowed = ['beklemede', 'onaylandi', 'reddedildi'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $sql = "UPDATE {$this->table}
                SET onay_durumu = ?,
                    onaylayan_user_id = ?,
                    onay_tarihi = CASE WHEN ? IN ('onaylandi','reddedildi') THEN NOW() ELSE NULL END,
                    onay_notu = ?,
                    guncelleme_tarihi = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $onaylayanUserId, $status, $onayNotu, $biletId]);
    }

    /**
     * Kullanıcı ID'sinden Personel ID'sini bulur.
     */
    public function getPersonelIdByUserId($userId)
    {
        $sql = "SELECT personel_id FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $res = $stmt->fetch(PDO::FETCH_OBJ);
        return $res ? (int)$res->personel_id : 0;
    }
}
