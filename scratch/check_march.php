<?php
require_once 'App/Model/Model.php';
class MockDB extends App\Model\Model {
    public function getMarch() {
        $sql = $this->db->prepare("SELECT * FROM bordro_donemi WHERE baslangic_tarihi LIKE '2026-03%'");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
$m = new MockDB('bordro_donemi');
print_r($m->getMarch());
?>
