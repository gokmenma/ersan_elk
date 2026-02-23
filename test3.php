<?php
$a = (object) ['name' => 'APARATLA KESİM YAPILDI.', 'adet' => 242];
$b = (object) ['name' => 'Sayaç Kullanıma açıldı', 'adet' => 242];
$c = (object) ['name' => 'Ödeme Yaptırıldı', 'adet' => 115];
$d = (object) ['name' => 'APARAT KIRMA ÜCRETİ', 'adet' => 60];

$gecerliIsler = [$a, $b, $c, $d];

$manuelDusumTotal = 10;
usort($gecerliIsler, function ($x, $y) {
    return $y->adet <=> $x->adet; });

foreach ($gecerliIsler as &$is) {
    if ($manuelDusumTotal <= 0)
        break;
    $mevcutAdet = floatval($is->adet);
    if ($mevcutAdet >= $manuelDusumTotal) {
        $is->adet = $mevcutAdet - $manuelDusumTotal;
        $manuelDusumTotal = 0;
    }
}

foreach ($gecerliIsler as $is) {
    echo $is->name . " => " . $is->adet . "\n";
}
