<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/App/Config/Database.php';
require_once __DIR__ . '/App/Model/DefinesModel.php';
require_once __DIR__ . '/App/Model/BordroPersonelModel.php';
require_once __DIR__ . '/App/Model/BordroParametreModel.php';

use App\Model\BordroPersonelModel;

$BordroPersonel = new BordroPersonelModel();

// Get personnel with kesinti etc. We need the view `sqlmaas_personel_listesi` or table `personel`
$sql = "SELECT * FROM sqlmaas_personel_listesi WHERE adi_soyadi LIKE '%KAAN AKÇADAĞ%'";
$p = $BordroPersonel->db->query($sql)->fetch(PDO::FETCH_OBJ);
if (!$p) die("Personel bulunamadı");

$donem = $BordroPersonel->db->query("SELECT * FROM bordro_donemleri ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);

$hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($p, $donem, 28075.50);

echo json_encode($hesap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
