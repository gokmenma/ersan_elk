<?php
require_once "App/Helper/Form.php";

use App\Helper\Form;

// Dummy options
$personelList = [
    (object)['id' => 1, 'adi_soyadi' => 'Ahmet Kaya'],
    (object)['id' => 2, 'adi_soyadi' => 'Mehmet Yilmaz']
];

echo Form::FormMultipleSelect2(
    'test_select',
    $personelList,
    [],
    'Personel Seçiniz',
    'users',
    'id',
    'adi_soyadi'
);
