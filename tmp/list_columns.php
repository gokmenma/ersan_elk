<?php
require 'bootstrap.php';
$db = App\Helper\Database::getInstance();
$stmt = $db->query('DESCRIBE bordro_personel');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . PHP_EOL;
}
