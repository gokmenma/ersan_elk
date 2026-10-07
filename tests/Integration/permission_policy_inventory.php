<?php

/**
 * Salt-okunur politika envanter testi.
 * Çalıştırma: /opt/lampp/bin/php tests/integration/permission_policy_inventory.php
 */

$root = dirname(__DIR__, 2);
require_once $root . '/Autoloader.php';
$policySql = file_get_contents($root . '/sql/2026_10_06_permission_system_phase_3.sql');
if ($policySql === false) {
    throw new RuntimeException('Aşama 3 SQL dosyası okunamadı.');
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views'));
$apiFiles = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getFilename() !== 'api.php') {
        continue;
    }
    $relative = str_replace($root . '/', '', $file->getPathname());
    $apiFiles[] = $relative;
}
sort($apiFiles);

$missingGuard = [];
$missingSqlResource = [];
foreach ($apiFiles as $relative) {
    $source = file_get_contents($root . '/' . $relative) ?: '';
    if (!str_contains($source, 'authorizeApiPolicy') && !str_contains($source, "allowsPolicy('api'")) {
        $missingGuard[] = $relative;
    }

    $resource = preg_replace('#^views/|\.php$#', '', $relative);
    if (!str_contains($policySql, "'{$resource}'")) {
        $missingSqlResource[] = $resource;
    }
}

if ($missingGuard) {
    throw new RuntimeException('Merkezi politika çağrısı olmayan API: ' . implode(', ', $missingGuard));
}
if ($missingSqlResource) {
    throw new RuntimeException('SQL politika kaynağı olmayan API: ' . implode(', ', $missingSqlResource));
}

$phase4Sql = file_get_contents($root . '/sql/2026_10_06_permission_system_phase_4.sql') ?: '';
foreach (['user_role_assignments', 'assigned_by', 'starts_at', 'ends_at', 'deleted_at'] as $requiredToken) {
    if (!str_contains($phase4Sql, $requiredToken)) {
        throw new RuntimeException("Aşama 4 SQL alanı eksik: {$requiredToken}");
    }
}

$postValidationSql = file_get_contents($root . '/sql/2026_10_06_permission_system_post_validation_fixes.sql');
if ($postValidationSql === false) {
    throw new RuntimeException('Geçiş sonrası düzeltme SQL dosyası okunamadı.');
}
$routeSources = [];
foreach ([$root . '/views', $root . '/App', $root . '/cron'] as $sourceRoot) {
    $sourceIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
    foreach ($sourceIterator as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
            continue;
        }
        $source = file_get_contents($file->getPathname()) ?: '';
        preg_match_all('#index(?:\.php)?\?p=([A-Za-z0-9_./%+-]+)#i', $source, $matches);
        foreach ($matches[1] ?? [] as $route) {
            $route = rawurldecode($route);
            if (is_file($root . '/views/' . $route . '.php')) {
                $routeSources[$route] = true;
            }
        }
    }
}

$missingPagePolicy = [];
$db = (new App\Core\Db())->db;
$pagePolicyStmt = $db->prepare(
    "SELECT COUNT(*) FROM permission_policies
     WHERE scope = ? AND resource = ? AND action = ? AND is_active = 1"
);
foreach (array_keys($routeSources) as $route) {
    $pagePolicyStmt->execute(['page', $route, '']);
    if ((int) $pagePolicyStmt->fetchColumn() === 0) {
        $missingPagePolicy[] = $route;
    }
}
sort($missingPagePolicy);
if ($missingPagePolicy) {
    throw new RuntimeException('SQL sayfa politikası olmayan mevcut rota: ' . implode(', ', $missingPagePolicy));
}

$invoiceRoutes = [
    'efatura/dashboard', 'efatura/giden-list', 'efatura/gelen-list', 'efatura/taslak-list',
    'efatura/olustur', 'efatura/cari-list', 'efatura/mal-hizmet-list', 'efatura/ayarlar',
];
foreach ($invoiceRoutes as $route) {
    $pagePolicyStmt->execute(['page', $route, '']);
    if ((int) $pagePolicyStmt->fetchColumn() === 0) {
        throw new RuntimeException("E-Fatura sayfa politikası eksik: {$route}");
    }
}

foreach (['efatura-api.php', 'efatura-cari-api.php', 'efatura-mal-hizmet-api.php'] as $apiFile) {
    $source = file_get_contents($root . '/api/' . $apiFile) ?: '';
    if (!str_contains($source, 'EInvoiceSecurity::checkPermission') && !str_contains($source, 'Gate::allows')) {
        throw new RuntimeException("E-Fatura API yetki kontrolü eksik: api/{$apiFile}");
    }
}

$invoiceSecuritySource = file_get_contents($root . '/App/Helper/EInvoiceSecurity.php') ?: '';
foreach (['efatura/gonder', 'efatura/iptal', 'efatura/senkronize', 'efatura/yanit'] as $operationPermission) {
    if (!str_contains($invoiceSecuritySource, "'{$operationPermission}'")) {
        throw new RuntimeException("E-Fatura işlem yetkisi kullanılmıyor: {$operationPermission}");
    }
}

$routeLessParentStmt = $db->prepare(
    "SELECT COUNT(*) FROM menus
     WHERE parent_id = ? AND (menu_link IS NULL OR TRIM(menu_link) = ?) AND permission_id IS NOT NULL"
);
$routeLessParentStmt->execute([0, '']);
if ((int) $routeLessParentStmt->fetchColumn() > 0) {
    throw new RuntimeException('Rota taşımayan üst menüde doğrudan yetki bağlantısı bulundu.');
}

$auditModel = new App\Model\PermissionAuditModel();
$ownerStmt = $db->prepare("SELECT DISTINCT owner_id FROM user_roles WHERE owner_id IS NOT NULL");
$ownerStmt->execute();
$auditedSubjects = 0;
foreach ($ownerStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $ownerId) {
    $subjects = $auditModel->getSubjects((int) $ownerId);
    foreach ([['role', $subjects['roles']], ['user', $subjects['users']]] as [$type, $list]) {
        foreach ($list as $subject) {
            $audit = $auditModel->audit($type, (int) $subject->id, (int) $ownerId);
            $auditedSubjects++;
            if ((int) $audit['stats']['critical'] > 0) {
                throw new RuntimeException("Sidebar/rota tutarsızlığı: {$type} #{$subject->id}");
            }
        }
    }
}

echo 'OK: ' . count($apiFiles) . ' API girişi ve ' . count($routeSources)
    . " mevcut statik sayfa rotası; {$auditedSubjects} kullanıcı/rol sidebar–rota tutarlılığı doğrulandı.\n";
