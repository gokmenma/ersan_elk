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

    // Log Seviyeleri
    const LEVEL_INFO = 0; // Rutin bilgilendirici (nöbet tipi değişimi, vb.)
    const LEVEL_IMPORTANT = 1; // Önemli (giriş/çıkış, silme, excel yükleme, personel ekleme)
    const LEVEL_CRITICAL = 2; // Kritik (toplu silme, güvenlik olayları)
    const LEVEL_PAGE_VIEW = 3; // Sayfa görüntüleme logları

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Log a critical action
     * @param int $userId Kullanıcı ID
     * @param string $actionType İşlem tipi
     * @param string $description Açıklama
     * @param int $level Log seviyesi (0=Info, 1=Önemli, 2=Kritik)
     */
    /**
     * Log a page view action
     * @param int $userId Kullanıcı ID
     * @param string $pageName Sayfa adı
     * @param string $platform Platform (Desktop, Mobile, PWA)
     */
    public function logPageView($userId, $pageName, $platform = 'Desktop')
    {
        return $this->saveWithAttr([
            'user_id' => $userId,
            'firma_id' => $_SESSION['firma_id'] ?? 0,
            'action_type' => 'Sayfa Görüntüleme',
            'description' => "[$platform] $pageName sayfası görüntülendi.",
            'level' => self::LEVEL_PAGE_VIEW
        ]);
    }

    public function logAction($userId, $actionType, $description, $level = self::LEVEL_INFO)
    {
        return $this->saveWithAttr([
            'user_id' => $userId,
            'firma_id' => $_SESSION['firma_id'] ?? 0,
            'action_type' => $actionType,
            'description' => $description,
            'level' => $level
        ]);
    }

    /**
     * Get recent logs with user details
     * @param int $limit Limit
     * @param int|null $minLevel Minimum log seviyesi (null = tüm loglar)
     */
    public function getRecentLogs($limit = 10, $minLevel = self::LEVEL_IMPORTANT)
    {
        $levelCondition = '';
        if ($minLevel !== null) {
            $levelCondition = 'AND COALESCE(l.level, 0) >= ' . intval($minLevel);
        }

        $sql = "SELECT l.*, u.adi_soyadi 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.firma_id = :firma_id {$levelCondition}
                ORDER BY l.created_at DESC 
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':firma_id', $_SESSION["firma_id"], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

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

        if (isset($filters['level']) && $filters['level'] !== '') {
            $conditions[] = 'l.level = ?';
            $params[] = intval($filters['level']);
        }

        if (isset($filters['min_level']) && $filters['min_level'] !== '') {
            $conditions[] = 'l.level >= ?';
            $params[] = intval($filters['min_level']);
        }

        if (isset($filters['max_level']) && $filters['max_level'] !== '') {
            $conditions[] = 'l.level <= ?';
            $params[] = intval($filters['max_level']);
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

        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= " LIMIT " . intval($filters['limit']) . " OFFSET " . intval($filters['offset']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get count of filtered logs
     */
    public function getLogsCount($filters = [])
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

        if (isset($filters['level']) && $filters['level'] !== '') {
            $conditions[] = 'l.level = ?';
            $params[] = intval($filters['level']);
        }

        if (isset($filters['min_level']) && $filters['min_level'] !== '') {
            $conditions[] = 'l.level >= ?';
            $params[] = intval($filters['min_level']);
        }

        if (isset($filters['max_level']) && $filters['max_level'] !== '') {
            $conditions[] = 'l.level <= ?';
            $params[] = intval($filters['max_level']);
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(l.description LIKE ? OR l.action_type LIKE ? OR u.adi_soyadi LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ)->total;
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

    /**
     * Get recent personnel login logs
     */
    public function getPersonelLoginLogs($limit = 1000, $offset = 0, $search = '')
    {
        $where = "p.firma_id = :firma_id";
        $params = [':firma_id' => $_SESSION['firma_id']];

        if (!empty($search)) {
            $where .= " AND (p.adi_soyadi LIKE :search OR pg.ip_adresi LIKE :search OR pg.tarayici LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT p.adi_soyadi, pg.id, pg.giris_tarihi as tarih, pg.ip_adresi, pg.tarayici
                FROM personel_giris_loglari pg 
                JOIN personel p ON p.id = pg.personel_id
                WHERE {$where}
                ORDER BY pg.giris_tarihi DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getPersonelLoginLogsCount($search = '')
    {
        $where = "p.firma_id = :firma_id";
        $params = [':firma_id' => $_SESSION['firma_id']];

        if (!empty($search)) {
            $where .= " AND (p.adi_soyadi LIKE :search OR pg.ip_adresi LIKE :search OR pg.tarayici LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total
                FROM personel_giris_loglari pg 
                JOIN personel p ON p.id = pg.personel_id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ)->total;
    }

    /**
     * Get user login logs
     */
    public function getUserLoginLogs($limit = 1000, $offset = 0, $search = '')
    {
        $where = "sl.action_type = 'Başarılı Giriş' AND FIND_IN_SET(:firma_id, u.firma_ids)";
        $params = [':firma_id' => $_SESSION['firma_id']];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR sl.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT u.adi_soyadi, sl.id, sl.created_at as tarih, SUBSTR(sl.description, LOCATE('IP:', sl.description) + 4) as ip_adresi, 'Sistem' as tarayici
                FROM system_logs sl 
                JOIN users u ON u.id = sl.user_id
                WHERE {$where}
                ORDER BY sl.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getUserLoginLogsCount($search = '')
    {
        $where = "sl.action_type = 'Başarılı Giriş' AND FIND_IN_SET(:firma_id, u.firma_ids)";
        $params = [':firma_id' => $_SESSION['firma_id']];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR sl.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total
                FROM system_logs sl 
                JOIN users u ON u.id = sl.user_id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ)->total;
    }

    /**
     * Get page view logs
     */
    public function getPageViewLogs($limit = 1000, $offset = 0, $search = '')
    {
        $where = "l.firma_id = :firma_id AND l.level = :level";
        $params = [':firma_id' => $_SESSION['firma_id'], ':level' => self::LEVEL_PAGE_VIEW];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR l.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT l.*, u.adi_soyadi 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE {$where}
                ORDER BY l.created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getPageViewLogsCount($search = '')
    {
        $where = "l.firma_id = :firma_id AND l.level = :level";
        $params = [':firma_id' => $_SESSION['firma_id'], ':level' => self::LEVEL_PAGE_VIEW];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR l.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ)->total;
    }
}
