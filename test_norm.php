<?php
function normalizeDeptName($name)
{
    if (!$name)
        return '';
    $name = trim($name);
    $search = array('ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü', '-', '_', ' ', '/', '.', ',');
    $replace = array('C', 'G', 'I', 'I', 'O', 'S', 'U', 'C', 'G', 'I', 'I', 'O', 'S', 'U', '', '', '', '', '', '');
    $name = str_replace($search, $replace, $name);
    return strtoupper($name);
}

echo "Test 1 (Kesme Açma): " . normalizeDeptName('Kesme Açma') . "\n";
echo "Test 2 (Kesme-Açma): " . normalizeDeptName('Kesme-Açma') . "\n";
echo "Test 3 (Büro): " . normalizeDeptName('Büro') . "\n";
echo "Test 4 (BÜRO): " . normalizeDeptName('BÜRO') . "\n";
