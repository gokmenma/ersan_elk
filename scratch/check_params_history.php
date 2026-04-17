<?php
require_once 'App/Model/Model.php';
class MockDB extends App\Model\Model {
    public function getAllHistory() {
        $sql = $this->db->prepare("SELECT * FROM bordro_genel_ayarlar WHERE parametre_kodu = 'asgari_ucret_net' ORDER BY gecerlilik_baslangic DESC");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
$m = new MockDB('bordro_genel_ayarlar');
print_r($m->getAllHistory());
?>
