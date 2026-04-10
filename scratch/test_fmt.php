<?php
$d = '3/04/2026';
$fmt = 'j/n/Y';
$dt = DateTime::createFromFormat($fmt, $d);
$errors = DateTime::getLastErrors();
echo "Input: $d | Fmt: $fmt | Result: " . ($dt ? $dt->format('Y-m-d') : 'Fail') . "\n";
echo "Errors: " . print_r($errors, true) . "\n";
