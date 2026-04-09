<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=ersantrc_personel;charset=utf8', 'root', '');
    $stmt = $db->query("SELECT role_id FROM user_role_permissions WHERE permission_id = 70");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
