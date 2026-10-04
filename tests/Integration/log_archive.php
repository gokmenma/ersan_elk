<?php
/** Çalışan test DB'si: php tests/integration/log_archive.php (salt okunur). */
require_once dirname(__DIR__, 2) . '/Autoloader.php';
class LogArchiveIntegrationModel extends App\Model\SystemLogModel {
    public function firms(): array {
        $stmt = $this->db->prepare('SELECT DISTINCT firma_id FROM system_logs');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function expectedSystemCount(string $scope): int {
        $where = 'firma_id = :firma';
        $params = [':firma' => $_SESSION['firma_id']];
        if ($scope === 'archive') $where .= ' AND archived_at IS NOT NULL';
        elseif ($scope !== 'all') {
            $where .= ' AND archived_at IS NULL AND created_at >= :since';
            $params[':since'] = date('Y-m-d H:i:s', strtotime('-' . ($scope === '90' ? 90 : 30) . ' days'));
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM system_logs WHERE ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

}
$model = new LogArchiveIntegrationModel();
$checks = 0;
foreach ($model->firms() as $firm) {
    $_SESSION['firma_id'] = (int) $firm;
    foreach (['30', '90', 'all', 'archive'] as $scope) {
        foreach (['', 'view', 'login', 'critical', 'delete', 'operation', 'ai'] as $category) {
            $filters = ['scope' => $scope, 'category' => $category, 'include_ai' => true];
            $fast = $model->getUnifiedActivitiesCount($filters);
            // '%' LIKE filtresi tüm dolu olay tipleriyle eşleşir; UNION sayım yolunu zorlar.
            $filtered = $model->getUnifiedActivitiesCount($filters + ['type' => '%']);
            if ($fast !== $filtered) throw new RuntimeException("Sayım uyuşmazlığı: {$firm}/{$scope}/{$category}");
            $rows = $model->getUnifiedActivities($filters + ['limit' => 25]);
            if (count($rows) !== min(25, $fast)) throw new RuntimeException('Liste/sayım uyuşmazlığı');
            for ($i = 1; $i < count($rows); $i++) {
                if ($rows[$i-1]->activity_date < $rows[$i]->activity_date) throw new RuntimeException('Tarih sıralaması hatası');
            }
            $checks++;
        }
        if ($model->getUnifiedActivitiesCount(['scope' => $scope, 'category' => 'system']) !== $model->expectedSystemCount($scope)) throw new RuntimeException('Arşiv/tarih kapsamı uyuşmazlığı');
        $method = new ReflectionMethod(App\Model\SystemLogModel::class, 'buildUnifiedActivityQuery');
        [$sql, $params] = $method->invoke($model, ['scope' => $scope, 'category' => 'view'], true);
        if (!str_contains($sql, 'firma_id = :firma_system')) throw new RuntimeException('Firma kapsamı kayıp');
        $checks++;
    }
}
echo "OK: {$checks} kapsam/liste/sayım kontrolü\n";
