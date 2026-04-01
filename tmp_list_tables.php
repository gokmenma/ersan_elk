<?php
$host = 'localhost';
$dbname = 'mbeyazil_ersanelektrik';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $stmt = $pdo->query("SHOW TABLES");
    while($row = $stmt->fetchColumn()) {
        echo "$row\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
