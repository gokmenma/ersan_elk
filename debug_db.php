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
echo "ARACLAR COLUMNS:\n";
echo json_encode($checker->getColumns('araclar'), JSON_PRETTY_PRINT);
echo "\n\nYAPILAN_ISLER COLUMNS:\n";
echo json_encode($checker->getColumns('yapilan_isler'), JSON_PRETTY_PRINT);
echo "\n\nENDEKS_OKUMA COLUMNS:\n";
echo json_encode($checker->getColumns('endeks_okuma'), JSON_PRETTY_PRINT);
