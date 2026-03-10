<?php
require_once 'vendor/autoload.php';
use App\Model\HakedisDonemModel;
$model = new HakedisDonemModel();
$db = $model->getDb();
$stmt = $db->query('SELECT h.*, s.a1_katsayisi, s.b1_katsayisi, s.b2_katsayisi, s.c_katsayisi FROM hakedis_donemleri h JOIN hakedis_sozlesmeler s ON h.sozlesme_id = s.id ORDER BY h.id DESC LIMIT 1');
$h = $stmt->fetch(PDO::FETCH_ASSOC);
print_r([
  'a1' => $h['a1_katsayisi'], 'au_temel' => $h['asgari_ucret_temel'], 'au_guncel' => $h['asgari_ucret_guncel'],
  'b1' => $h['b1_katsayisi'], 'mot_temel' => $h['motorin_temel'], 'mot_guncel' => $h['motorin_guncel'],
  'b2' => $h['b2_katsayisi'], 'ufe_temel' => $h['ufe_genel_temel'], 'ufe_guncel' => $h['ufe_genel_guncel'],
  'c'  => $h['c_katsayisi'], 'mak_temel' => $h['makine_ekipman_temel'], 'mak_guncel' => $h['makine_ekipman_guncel']
]);
