<?php
require_once 'Autoloader.php';
use App\Model\PersonelModel;

$Personel = new PersonelModel();
$data = $Personel->all();

foreach ($data as $p) {
    echo "ID: $p->id | Name: $p->adi_soyadi | Sodexo: $p->sodexo\n";
}
