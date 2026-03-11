<?php
$db = new PDO('mysql:host=localhost;dbname=ersan_personel', 'root', '');
$stmt = $db->query('DESC hakedis_donemleri');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
