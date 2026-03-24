<?php
require_once __DIR__ . '/Autoloader.php';

$host = 'localhost';
$dbName = 'mbeyazil_ersanelektrik';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sourceDir = __DIR__ . '/views/formlar/files';
$files = glob($sourceDir . '/*.{doc,docx,xls,xlsx,pdf}', GLOB_BRACE);

if (empty($files)) {
    echo "Örnek dosya bulunamadı.\n";
    exit;
}

$targetDir = __DIR__ . '/uploads/formlar';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

foreach ($files as $source) {
    if (file_exists($source)) {
        $originalName = basename($source);
        $ext = pathinfo($source, PATHINFO_EXTENSION);
        $newName = time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        $target = $targetDir . '/' . $newName;

        if (copy($source, $target)) {
            $stmt = $pdo->prepare("INSERT INTO `formlar` (`firma_id`, `baslik`, `dosya_adi`, `dosya_yolu`, `ekleyen_id`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([1, pathinfo($originalName, PATHINFO_FILENAME) . ' (Örnek)', $originalName, 'uploads/formlar/' . $newName, 1]);
            echo "Eklendi: $originalName\n";
        }
    }
}
