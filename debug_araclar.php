<?php
require_once __DIR__ . '/Autoloader.php';
use App\Model\Model;
@session_start();

class DBCheck extends Model
{
    public function getColumns($table)
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM $table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$checker = new DBCheck('araclar');
$cols = $checker->getColumns('araclar');
foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
