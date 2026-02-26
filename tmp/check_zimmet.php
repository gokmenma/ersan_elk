<?php
require_once __DIR__ . '/../bootstrap.php';
$db = getDbConnection();

$sql = "SELECT DISTINCT durum FROM demirbas_zimmet";

$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
