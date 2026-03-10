<?php
require_once 'vendor/autoload.php';
use App\Model\HakedisDonemModel;
$model = new HakedisDonemModel();
$db = $model->getDb();

$sqls = [
    // hakedis_sozlesmeler
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN a1_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN b1_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN b2_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN c_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN asgari_ucret_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN motorin_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN ufe_genel_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_sozlesmeler MODIFY COLUMN makine_ekipman_temel DECIMAL(20,6)",

    // hakedis_donemleri
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN a1_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN b1_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN b2_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN c_katsayisi DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN asgari_ucret_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN asgari_ucret_guncel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN motorin_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN motorin_guncel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN ufe_genel_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN ufe_genel_guncel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN makine_ekipman_temel DECIMAL(20,6)",
    "ALTER TABLE hakedis_donemleri MODIFY COLUMN makine_ekipman_guncel DECIMAL(20,6)"
];

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "Executed: $sql\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . " in $sql\n";
    }
}
