<?php
require_once 'App/Model/BordroParametreModel.php';
require_once 'App/Model/Model.php';
// Mock enough to get DB
class MockDB extends App\Model\Model {
    public function getVal($date) {
        $sql = $this->db->prepare("SELECT deger FROM bordro_genel_ayarlar WHERE parametre_kodu = 'asgari_ucret_net' AND aktif = 1 AND gecerlilik_baslangic <= ? ORDER BY gecerlilik_baslangic DESC LIMIT 1");
        $sql->execute([$date]);
        return $sql->fetchColumn();
    }
}
$m = new MockDB('bordro_genel_ayarlar');
echo "Value for 2026-03-31: " . $m->getVal('2026-03-31') . "\n";
echo "Value for 2026-04-17: " . $m->getVal('2026-04-17') . "\n";
?>
