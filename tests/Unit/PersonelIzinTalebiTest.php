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

test('personel PWA yıllık izin akışında 14 günlük ön bildirim kısıtı bulunmaz', function () {
    $istemci = file_get_contents(__DIR__ . '/../../views/personel-pwa/pages/izin.php');
    $api = file_get_contents(__DIR__ . '/../../views/personel-pwa/api.php');

    expect($istemci)
        ->not->toContain('gunFarki < 14')
        ->not->toContain('en az iki hafta önceden bildirilmesi gerekir')
        ->toContain("toplamGun > 20");

    expect($api)
        ->not->toContain('$gun_farki < 14')
        ->not->toContain('en az iki hafta önceden bildirilmesi gerekir')
        ->toContain('$toplam_gun > 20');
});
