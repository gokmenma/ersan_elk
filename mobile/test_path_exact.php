<?php
// Exact logic from mobile/index.php
chdir(dirname(__DIR__)); 
$path = 'assets/images/users/personel_69b2b4e0488a2.png';
echo "Path: $path\n";
echo "File Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
echo "Current Dir: " . getcwd() . "\n";
$pwa_path = 'uploads/personel/personel_69b2b4e0488a2.png'; // Example
echo "PWA Path: $pwa_path\n";
echo "PWA File Exists: " . (file_exists($pwa_path) ? 'YES' : 'NO') . "\n";
