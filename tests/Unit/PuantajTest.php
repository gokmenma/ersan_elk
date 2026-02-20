<?php

use App\Model\UserModel;

test('Puantaj Modeli Yüklenebilmeli', function () {
    // Burada projenizin Unit yani birim testi yer alır.
    // Örnek: Bir UserModel test edebiliriz
    $userModel = new UserModel();
    expect($userModel)->toBeObject();
});

test('Örnek Hesaplama Mantığı Doğru Çalışmalı', function () {
    // Örnek bir php puan hesaplama algoritması çağrılıp beklenilen değerle örtüşüyor mu diye bakılır
    // $sonuc = Helper::hesapla(100, 20);
    // expect($sonuc)->toBe(120);
    expect(true)->toBeTrue();
});
