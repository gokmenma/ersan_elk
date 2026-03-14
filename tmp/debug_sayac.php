<?php
require_once __DIR__ . '/App/Config/Config.php';
require_once __DIR__ . '/App/Model/Model.php';
require_once __DIR__ . '/App/Model/SayacDegisimModel.php';

$SayacDegisim = new \App\Model\SayacDegisimModel();
$personelId = 170; // Cuma Canlı

// Get some records to see structure and dates
$sql = "SELECT * FROM sayac_degisim WHERE personel_id = ? ORDER BY tarih DESC LIMIT 10";
$stmt = $SayacDegisim->db->prepare($sql);
$stmt->execute([$personelId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_PRETTY_PRINT);
