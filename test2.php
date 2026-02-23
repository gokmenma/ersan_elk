<?php
$a = (object) ['name' => 1];
$b = (object) ['name' => 2];
$c = (object) ['name' => 3];
$d = (object) ['name' => 4];

$arr = [$a, $b, $c, $d];

foreach ($arr as &$v) {
}

foreach ($arr as $v) {
    echo $v->name . "\n";
}
