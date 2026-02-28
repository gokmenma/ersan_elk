<?php
require 'Autoloader.php';
$db = new App\Model\DemirbasModel();
$stmt = $db->getDb()->query('DESCRIBE demirbas');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
