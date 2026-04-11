<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;
$sql = "SELECT kod, hesaplama_tipi FROM bordro_parametreleri WHERE kod = 'yemek_yardimi_tum'";
$p = $db->query($sql)->fetch(PDO::FETCH_OBJ);

if ($p) {
    echo "KOD: [" . $p->kod . "]\n";
    echo "TIP: [" . $p->hesaplama_tipi . "]\n";
    echo "HEX: " . bin2hex($p->hesaplama_tipi) . "\n";
} else {
    echo "Parametre bulunamadı.\n";
}
