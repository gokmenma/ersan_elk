<?php
$lines = file('C:/xampp/htdocs/ersan_elk/views/bordro/api.php');
for ($i = 840; $i <= 890; $i++) {
    echo $i . ': ' . trim($lines[$i - 1]) . "\n";
}
