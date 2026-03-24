<?php
$p=new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik;charset=utf8mb4','root','');
print_r($p->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN));
print_r($p->query('SHOW COLUMNS FROM personel')->fetchAll(PDO::FETCH_COLUMN));
