<?php

use App\Model\PersonelIzinleriModel;

function izinModeli(): PersonelIzinleriModel
{
    return (new ReflectionClass(PersonelIzinleriModel::class))->newInstanceWithoutConstructor();
}

test('yıllık izin hakediş bakiyesi kısıtına tabi değildir', function () {
    $izinTuru = (object) [
        'tur_adi' => 'Yıllık İzin',
        'kisa_kod' => 'Yİ',
        'ucretli_mi' => 1,
    ];

    expect(izinModeli()->isEntitlementRequiredLeaveType($izinTuru))->toBeFalse();
});

test('diğer ücretli izinlerin hakediş kısıtı korunur', function () {
    $izinTuru = (object) [
        'tur_adi' => 'Ücretli İzin',
        'kisa_kod' => 'UI',
        'ucretli_mi' => 1,
    ];

    expect(izinModeli()->isEntitlementRequiredLeaveType($izinTuru))->toBeTrue();
});
