<?php
// Bu scripti projenizin ana dizinine koyup çalıştırın
// veya MenuModel.php içindekiyle aynı dizin yapısına göre ayarlayın.
$targetDir = __DIR__ . '/../cache/tenant_1'; // Projenizin gerçek yoluna göre ayarlayın
$pattern = $targetDir . '/menu_role_*.cache';

var_dump("Target Directory: " . $targetDir);
var_dump("Is Target Directory a dir? " . (is_dir($targetDir) ? 'Yes' : 'No'));
var_dump("Pattern: " . $pattern);

$files = glob($pattern);
var_dump($files);
?>