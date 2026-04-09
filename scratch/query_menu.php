<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=ersantrc_personel;charset=utf8', 'root', '');
    $stmt = $db->query("SELECT * FROM menus WHERE menu_name LIKE '%Araç Takip%'");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
