<?php
require_once 'Autoloader.php';
use App\Helper\Helper;

$data = [
    'sodexo' => '₺3.000',
    'gunluk_ucret' => '₺1.500',
    'maas_tutari' => '₺15.000',
    'bes_kesintisi_varmi' => 'Evet'
];

foreach ($data as $key => $value) {
    if ($value === '') {
        $data[$key] = null;
    }

    /**Parasal tutarlar için money formatını kaldır */
    if (strpos($key, 'tutar') !== false || $key == 'gunluk_ucret' || $key == 'sodexo') {
        echo "Processing $key with value '$value'\n";
        $data[$key] = Helper::formattedMoneyToNumber($value);
    }
}

print_r($data);
