<?php
try {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare('SELECT * FROM sqlite_master WHERE type = ? OR name = ?');
    $stmt->execute([0 => 't', 2 => 't2']);
    echo "Executed Successfully";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
