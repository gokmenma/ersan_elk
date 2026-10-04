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
     * Belirli bir IP'den, verilen action_type için son X dakikada kaydedilmiş
     * başarısız kimlik doğrulama denemesi sayısını döndürür (basit rate-limit kontrolü).
     * Başarısız girişler description alanına "AUTH_FAIL | IP: x.x.x.x | ..." formatında yazılmalıdır.
     */
    public function countRecentFailedApiAttempts(string $ip, string $actionType, int $minutes = 15): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}
                WHERE action_type = ? AND description LIKE 'AUTH_FAIL%' AND description LIKE ?
                AND created_at >= (NOW() - INTERVAL ? MINUTE)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionType, '%IP: ' . $ip . '%', $minutes]);
        return (int) $stmt->fetch(PDO::FETCH_OBJ)->total;
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
        $stmt->bindValue(':firma_id', $_SESSION["firma_id"] ?? 0, PDO::PARAM_INT);
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

        if (!empty($filters['column_user'])) {
            $conditions[] = 'u.adi_soyadi LIKE ?';
            $params[] = '%' . $filters['column_user'] . '%';
        }
        if (!empty($filters['column_action'])) {
            $conditions[] = 'l.action_type LIKE ?';
            $params[] = '%' . $filters['column_action'] . '%';
        }
        if (!empty($filters['column_description'])) {
            $conditions[] = 'l.description LIKE ?';
            $params[] = '%' . $filters['column_description'] . '%';
        }
        if (!empty($filters['column_date'])) {
            $conditions[] = 'DATE(l.created_at) = ?';
            $params[] = $filters['column_date'];
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

        if (!empty($filters['column_user'])) {
            $conditions[] = 'u.adi_soyadi LIKE ?';
            $params[] = '%' . $filters['column_user'] . '%';
        }
        if (!empty($filters['column_action'])) {
            $conditions[] = 'l.action_type LIKE ?';
            $params[] = '%' . $filters['column_action'] . '%';
        }
        if (!empty($filters['column_description'])) {
            $conditions[] = 'l.description LIKE ?';
            $params[] = '%' . $filters['column_description'] . '%';
        }
        if (!empty($filters['column_date'])) {
            $conditions[] = 'DATE(l.created_at) = ?';
            $params[] = $filters['column_date'];
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
     * Aktivite denetim ekranında kullanılan firma bazlı KPI ve trend verileri.
     */
    public function getActivityDashboardData(): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);

        $summarySql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() AND COALESCE(level, 0) <> :page_level_1 THEN 1 ELSE 0 END) AS today_operations,
                SUM(CASE WHEN DATE(created_at) = CURDATE() AND action_type = 'Başarılı Giriş' THEN 1 ELSE 0 END) AS today_logins,
                SUM(CASE WHEN DATE(created_at) = CURDATE() AND COALESCE(level, 0) = :critical_level THEN 1 ELSE 0 END) AS today_critical
            FROM {$this->table}
            WHERE firma_id = :firma_id";
        $summaryStmt = $this->db->prepare($summarySql);
        $summaryStmt->execute([
            ':page_level_1' => self::LEVEL_PAGE_VIEW,
            ':critical_level' => self::LEVEL_CRITICAL,
            ':firma_id' => $firmaId,
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $trendSql = "SELECT DATE(created_at) AS log_date,
                SUM(CASE WHEN COALESCE(level, 0) <> :page_level_1 THEN 1 ELSE 0 END) AS operation_count,
                SUM(CASE WHEN COALESCE(level, 0) = :page_level_2 THEN 1 ELSE 0 END) AS page_count,
                SUM(CASE WHEN action_type = 'Başarılı Giriş' THEN 1 ELSE 0 END) AS login_count
            FROM {$this->table}
            WHERE firma_id = :firma_id
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
            GROUP BY DATE(created_at)
            ORDER BY log_date";
        $trendStmt = $this->db->prepare($trendSql);
        $trendStmt->execute([
            ':page_level_1' => self::LEVEL_PAGE_VIEW,
            ':page_level_2' => self::LEVEL_PAGE_VIEW,
            ':firma_id' => $firmaId,
        ]);
        $trendMap = [];
        foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $trendMap[$row['log_date']] = $row;
        }

        $trend = ['labels' => [], 'operations' => [], 'views' => [], 'logins' => []];
        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $date = date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
            $row = $trendMap[$date] ?? [];
            $trend['labels'][] = date('d.m', strtotime($date));
            $trend['operations'][] = (int) ($row['operation_count'] ?? 0);
            $trend['views'][] = (int) ($row['page_count'] ?? 0);
            $trend['logins'][] = (int) ($row['login_count'] ?? 0);
        }

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'today_operations' => (int) ($summary['today_operations'] ?? 0),
            'today_logins' => (int) ($summary['today_logins'] ?? 0),
            'today_critical' => (int) ($summary['today_critical'] ?? 0),
            'trend' => $trend,
        ];
    }

    /**
     * Farklı aktivite kaynaklarını denetim günlüğü için ortak bir yapıda listeler.
     */
    public function getUnifiedActivities(array $filters = []): array
    {
        [$sql, $params] = $this->buildUnifiedActivityQuery($filters, false);
        $sql .= ' ORDER BY activity_date DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, (int) ($filters['limit'] ?? 25)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, (int) ($filters['offset'] ?? 0)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getUnifiedActivitiesCount(array $filters = []): int
    {
        $hasTextFilter = false;
        foreach (['search', 'date', 'user', 'type', 'module', 'detail', 'related'] as $filterKey) {
            if (!empty($filters[$filterKey])) {
                $hasTextFilter = true;
                break;
            }
        }
        if (!$hasTextFilter) {
            return $this->getUnifiedActivitiesFastCount($filters);
        }
        [$sql, $params] = $this->buildUnifiedActivityQuery($filters, true);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Arşiv ve zaman kapsamı tüm kaynaklarda sayım/liste için aynıdır. */
    private function activityScope(array $filters, string $alias, string $dateColumn, string $key, array &$params): string
    {
        $scope = $filters['scope'] ?? '30';
        $prefix = $alias === '' ? '' : $alias . '.';
        if ($scope === 'archive') {
            return " AND {$prefix}archived_at IS NOT NULL";
        }
        if ($scope === 'all') {
            return '';
        }
        $days = $scope === '90' ? 90 : 30;
        $params[':since_' . $key] = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        return " AND {$prefix}archived_at IS NULL AND {$prefix}{$dateColumn} >= :since_{$key}";
    }

    /** CLI görevi: kalıcı silme yapmadan, sınırlı partilerle arşivler. */
    public function archiveExpiredActivities(int $batchSize = 1000): array
    {
        $batchSize = max(1, min(5000, $batchSize));
        $policies = [
            'system_logs' => ['created_at', "CASE
                WHEN level IN (1, 2) OR action_type LIKE '%Sil%' OR action_type LIKE '%Giriş%' OR action_type LIKE '%Çıkış%' OR description LIKE 'AUTH_FAIL%' THEN :important
                WHEN level = 3 THEN :views ELSE :routine END"],
            'personel_giris_loglari' => ['giris_tarihi', ':important'],
            'ai_agent_logs' => ['created_at', "CASE WHEN status = 'error' THEN :important ELSE :routine END"],
        ];
        $result = [];
        foreach ($policies as $table => [$date, $cutoff]) {
            $params = [];
            foreach (['important' => '-1 year', 'views' => '-30 days', 'routine' => '-90 days'] as $key => $interval) {
                if (strpos($cutoff, ':' . $key) !== false) {
                    $params[':' . $key] = date('Y-m-d H:i:s', strtotime($interval));
                }
            }
            $stmt = $this->db->prepare("UPDATE {$table} SET archived_at = NOW()
                WHERE archived_at IS NULL AND {$date} < ({$cutoff}) ORDER BY {$date}, id LIMIT :batch");
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':batch', $batchSize, PDO::PARAM_INT);
            $stmt->execute();
            $result[$table] = $stmt->rowCount();
        }
        return $result;
    }

    private function getUnifiedActivitiesFastCount(array $filters): int
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $category = (string) ($filters['category'] ?? '');
        $queries = [];
        $params = [];

        if ($category !== 'ai') {
            $systemWhere = 'firma_id = :firma_system_count';
            if ($category === 'view') $systemWhere .= ' AND level = 3';
            elseif ($category === 'login') $systemWhere .= " AND action_type = 'Başarılı Giriş'";
            elseif ($category === 'critical') $systemWhere .= ' AND level = 2';
            elseif ($category === 'delete') $systemWhere .= " AND action_type LIKE '%Sil%'";
            elseif ($category === 'operation') $systemWhere .= " AND COALESCE(level, 0) <> 3 AND action_type <> 'Başarılı Giriş' AND level <> 2 AND action_type NOT LIKE '%Sil%'";
            $systemWhere .= $this->activityScope($filters, '', 'created_at', 'system_count', $params);
            $queries[] = "SELECT COUNT(*) AS cnt FROM system_logs WHERE {$systemWhere}";
            $params[':firma_system_count'] = $firmaId;
        }
        if (in_array($category, ['', 'login'], true)) {
            $queries[] = 'SELECT COUNT(*) AS cnt FROM personel_giris_loglari pg INNER JOIN personel p ON p.id = pg.personel_id WHERE p.firma_id = :firma_personel_count' . $this->activityScope($filters, 'pg', 'giris_tarihi', 'personel_count', $params);
            $params[':firma_personel_count'] = $firmaId;
        }
        if (!empty($filters['include_ai']) && in_array($category, ['', 'ai'], true)) {
            $queries[] = 'SELECT COUNT(*) AS cnt FROM ai_agent_logs WHERE firma_id = :firma_ai_count' . $this->activityScope($filters, '', 'created_at', 'ai_count', $params);
            $params[':firma_ai_count'] = $firmaId;
        }

        $stmt = $this->db->prepare('SELECT COALESCE(SUM(counts.cnt), 0) FROM (' . implode(' UNION ALL ', $queries) . ') counts');
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildUnifiedActivityQuery(array $filters, bool $countOnly): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $category = (string) ($filters['category'] ?? '');
        $includeSystem = !in_array($category, ['ai'], true);
        $includePersonnel = in_array($category, ['', 'login'], true);
        $includeAi = !empty($filters['include_ai']) && in_array($category, ['', 'ai'], true);
        $systemCategorySql = '';
        if ($category === 'view') {
            $systemCategorySql = ' AND l.level = 3';
        } elseif ($category === 'login') {
            $systemCategorySql = " AND l.action_type = 'Başarılı Giriş'";
        } elseif ($category === 'critical') {
            $systemCategorySql = ' AND l.level = 2';
        } elseif ($category === 'delete') {
            $systemCategorySql = " AND l.action_type LIKE '%Sil%'";
        } elseif ($category === 'operation') {
            $systemCategorySql = " AND COALESCE(l.level, 0) <> 3 AND l.action_type <> 'Başarılı Giriş' AND l.level <> 2 AND l.action_type NOT LIKE '%Sil%'";
        }

        $parts = [];
        $params = [];
        $systemScope = $includeSystem ? $this->activityScope($filters, 'l', 'created_at', 'system', $params) : '';
        $personnelScope = $includePersonnel ? $this->activityScope($filters, 'pg', 'giris_tarihi', 'personel', $params) : '';
        $aiScope = $includeAi ? $this->activityScope($filters, 'a', 'created_at', 'ai', $params) : '';
        if ($includeSystem) {
            $parts[] = "
            SELECT l.id, l.created_at AS activity_date,
                   COALESCE(NULLIF(u.adi_soyadi, ''), 'Sistem') AS user_name,
                   CASE
                       WHEN l.level = 3 THEN 'Sayfa Ziyareti'
                       WHEN l.action_type = 'Başarılı Giriş' THEN 'Yönetici Girişi'
                       WHEN l.level = 2 THEN 'Kritik Olay'
                       ELSE COALESCE(NULLIF(l.action_type, ''), 'Sistem İşlemi')
                   END AS event_type,
                   CASE WHEN l.level = 3 THEN 'Navigasyon' WHEN l.action_type = 'Başarılı Giriş' THEN 'Oturum' ELSE 'Sistem' END AS module_name,
                   l.description AS detail,
                   CASE WHEN l.level = 3 THEN 'Sayfa' WHEN l.action_type = 'Başarılı Giriş' THEN 'Yönetici Oturumu' ELSE CONCAT('Log #', l.id) END AS related_record,
                   CASE WHEN l.level = 3 THEN 'view' WHEN l.action_type = 'Başarılı Giriş' THEN 'login' WHEN l.level = 2 THEN 'critical' WHEN l.action_type LIKE '%Sil%' THEN 'delete' ELSE 'operation' END AS category,
                   COALESCE(l.level, 0) AS severity
            FROM system_logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE l.firma_id = :firma_system{$systemCategorySql}{$systemScope}";
        }
        if ($includePersonnel) {
            $parts[] = "
            SELECT pg.id, pg.giris_tarihi, p.adi_soyadi, 'Personel Girişi', 'Personel PWA',
                   CONCAT('Personel uygulamasına giriş yapıldı', CASE WHEN pg.tarayici IS NOT NULL AND pg.tarayici <> '' THEN CONCAT(' · ', pg.tarayici) ELSE '' END),
                   COALESCE(NULLIF(pg.ip_adresi, ''), 'Personel Oturumu'), 'login', 0
            FROM personel_giris_loglari pg
            INNER JOIN personel p ON p.id = pg.personel_id
            WHERE p.firma_id = :firma_personel{$personnelScope}";
        }

        if ($includeAi) {
            $parts[] = "
            SELECT a.id, a.created_at, COALESCE(NULLIF(u.adi_soyadi, ''), NULLIF(u.user_name, ''), CONCAT('Kullanıcı #', a.user_id)),
                   'Yapay Zeka Sorgusu', 'Yapay Zeka',
                   CONCAT(COALESCE(a.prompt, ''), CASE WHEN a.status IS NOT NULL THEN CONCAT(' · Durum: ', a.status) ELSE '' END),
                   COALESCE(NULLIF(a.model_used, ''), 'AI Agent'), 'ai', CASE WHEN a.status = 'error' THEN 2 ELSE 0 END
            FROM ai_agent_logs a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.firma_id = :firma_ai{$aiScope}";
        }
        $hasOuterFilter = false;
        foreach (['search', 'date', 'user', 'type', 'module', 'detail', 'related'] as $outerFilterKey) {
            if (!empty($filters[$outerFilterKey])) {
                $hasOuterFilter = true;
                break;
            }
        }
        if (!$countOnly && !$hasOuterFilter) {
            $candidateLimit = max(1, (int) ($filters['offset'] ?? 0) + (int) ($filters['limit'] ?? 25));
            $parts = array_map(
                static fn(string $part): string => '(' . $part . ' ORDER BY 2 DESC LIMIT ' . $candidateLimit . ')',
                $parts
            );
        }
        $union = implode(' UNION ALL ', $parts);

        $conditions = [];
                if ($includeSystem) {
            $params[':firma_system'] = $firmaId;
        }
        if ($includePersonnel) {
            $params[':firma_personel'] = $firmaId;
        }
        if ($includeAi) {
            $params[':firma_ai'] = $firmaId;
        }
        $searchColumns = ['date' => 'activity_date', 'user' => 'user_name', 'type' => 'event_type', 'module' => 'module_name', 'detail' => 'detail', 'related' => 'related_record'];
        foreach ($searchColumns as $key => $column) {
            if (!empty($filters[$key])) {
                if ($key === 'date') {
                    $conditions[] = 'DATE(activity.activity_date) = :filter_date';
                    $params[':filter_date'] = $filters[$key];
                } else {
                    $conditions[] = "activity.{$column} LIKE :filter_{$key}";
                    $params[":filter_{$key}"] = '%' . $filters[$key] . '%';
                }
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(activity.user_name LIKE :global_search OR activity.event_type LIKE :global_search OR activity.module_name LIKE :global_search OR activity.detail LIKE :global_search OR activity.related_record LIKE :global_search)';
            $params[':global_search'] = '%' . $filters['search'] . '%';
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $sql = $countOnly
            ? "SELECT COUNT(*) FROM ({$union}) activity{$where}"
            : "SELECT * FROM ({$union}) activity{$where}";
        return [$sql, $params];
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
     * Get AI Agent query logs for dashboard and log lists
     */
    public function getAiAgentLogs($limit = 10, $offset = 0, $search = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 1;
        $where = "l.firma_id = :firma_id";
        $params = [':firma_id' => $firmaId];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR u.user_name LIKE :search OR l.prompt LIKE :search OR l.response LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT l.id, l.user_id, COALESCE(u.adi_soyadi, u.user_name, CONCAT('Kullanıcı #', l.user_id)) as adi_soyadi, 
                       l.prompt, l.response, l.model_used, l.status, l.execution_time_ms, l.created_at
                FROM ai_agent_logs l 
                LEFT JOIN users u ON u.id = l.user_id
                WHERE {$where}
                ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAiAgentLogsCount($search = '')
    {
        $firmaId = $_SESSION['firma_id'] ?? 1;
        $where = "l.firma_id = :firma_id";
        $params = [':firma_id' => $firmaId];

        if (!empty($search)) {
            $where .= " AND (u.adi_soyadi LIKE :search OR u.user_name LIKE :search OR l.prompt LIKE :search OR l.response LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total
                FROM ai_agent_logs l 
                LEFT JOIN users u ON u.id = l.user_id
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
