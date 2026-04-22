<?php
require 'bootstrap.php';
$db = (new \App\Model\Model('personel_gorev_gecmisi'))->getDb();
$stmt = $db->prepare('SELECT * FROM personel_gorev_gecmisi WHERE personel_id = 178');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_OBJ);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
