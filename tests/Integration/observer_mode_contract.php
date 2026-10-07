<?php

/**
 * Gözlem modu yapısal güvenlik testi.
 * Çalıştırma: /opt/lampp/bin/php tests/integration/observer_mode_contract.php
 */

$root = dirname(__DIR__, 2);
$requiredFiles = [
    'App/Service/ObserverMode.php',
    'App/Model/ObserverAuditModel.php',
    'observer-mode.php',
    'layouts/observer-banner.php',
    'sql/2026_10_07_add_read_only_observer_mode.sql',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        throw new RuntimeException("Gözlem modu dosyası eksik: {$file}");
    }
}

$sql = file_get_contents($root . '/sql/2026_10_07_add_read_only_observer_mode.sql') ?: '';
foreach (['access_mode', "ENUM('read', 'write')", 'observer_session_logs', 'actor_user_id', 'target_user_id'] as $token) {
    if (!str_contains($sql, $token)) {
        throw new RuntimeException("Gözlem SQL sözleşmesi eksik: {$token}");
    }
}

$gate = file_get_contents($root . '/App/Service/Gate.php') ?: '';
foreach (['ObserverMode::isActive()', 'ObserverMode::allowsPolicy', '$policyModel->resolve'] as $token) {
    if (!str_contains($gate, $token)) {
        throw new RuntimeException("API gözlem koruması eksik: {$token}");
    }
}

$db = file_get_contents($root . '/App/Core/Db.php') ?: '';
if (!str_contains($db, 'START TRANSACTION READ ONLY')
    || !str_contains($db, 'assertObserverRequestIsReadOnly')
    || !str_contains($db, 'rollBack()')) {
    throw new RuntimeException('Veritabanı salt-okuma savunması eksik.');
}

$auditModel = file_get_contents($root . '/App/Model/ObserverAuditModel.php') ?: '';
if (!str_contains($auditModel, 'Db::createIsolatedConnection()') || str_contains($auditModel, 'parent::__construct()')) {
    throw new RuntimeException('Audit modeli salt-okunur uygulama bağlantısından ayrı değil.');
}

$endpoint = file_get_contents($root . '/observer-mode.php') ?: '';
foreach (['Autoloader.php', 'Gate::isSuperAdmin()', 'Security::decrypt', 'verifyCsrf', "REQUEST_METHOD"] as $token) {
    if (!str_contains($endpoint, $token)) {
        throw new RuntimeException("Gözlem endpoint güvenliği eksik: {$token}");
    }
}

echo "OK: gözlem modu oturum, API, DB ve audit korumaları mevcut.\n";
