<?php
require_once __DIR__ . '/Autoloader.php';
$model = new App\Model\TanimlamalarModel();
$model->db->exec('ALTER TABLE tanimlamalar ADD COLUMN normal_mesai_sayilir TINYINT(1) DEFAULT 0');
echo 'Column added';
