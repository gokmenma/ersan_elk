<?php
require_once 'vendor/autoload.php';
use App\Model\HakedisDonemModel;
$model = new HakedisDonemModel();
$db = $model->getDb();

function checkTable($db, $tableName) {
    echo "--- $tableName ---\n";
    $stmt = $db->query("DESC $tableName");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $field = $row['Field'];
        if(stripos($field, 'temel') !== false || stripos($field, 'guncel') !== false || stripos($field, 'katsayisi') !== false) {
            echo "{$row['Field']}: {$row['Type']}\n";
        }
    }
}

checkTable($db, 'hakedis_sozlesmeler');
echo "\n";
checkTable($db, 'hakedis_donemleri');
