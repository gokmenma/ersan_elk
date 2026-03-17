<?php
chdir('..'); // Simulate mobile/index.php behavior
$path = 'assets/images/users/personel_69b2b4e0488a2.png';
echo "Path: $path\n";
echo "File Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
echo "Current Dir: " . getcwd() . "\n";
