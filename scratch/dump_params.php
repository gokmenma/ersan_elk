<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$model = new App\Model\BordroParametreModel();
$params = $model->getAllParametreler();

echo "KOD | TIP | TUTAR | OTOMATIK | VARS_GUN\n";
echo "------------------------------------------\n";
foreach ($params as $p) {
    printf("%s | %s | %s | %s | %s\n", 
        $p->kod, 
        $p->hesaplama_tipi, 
        $p->varsayilan_tutar, 
        $p->gun_sayisi_otomatik, 
        $p->varsayilan_gun_sayisi
    );
}
