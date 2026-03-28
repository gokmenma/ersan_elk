<?php
require_once 'vendor/autoload.php';
// Use any model to get DB connection
use App\Model\UserModel;
$model = new UserModel();
$db = $model->getDb();

function checkTable($db, $tableName) {
    echo "--- $tableName ---\n";
    $stmt = $db->query("DESC $tableName");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']}: {$row['Type']}\n";
    }
}

checkTable($db, 'users');
exit;
