<?php
require 'Autoloader.php';
$db = new App\Core\Db();
foreach($db->query('SELECT id, role_name FROM user_roles')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ':' . $row['role_name'] . '|';
}
