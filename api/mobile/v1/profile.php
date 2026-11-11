<?php

require_once __DIR__ . '/_bootstrap.php';

$personel = mobile_require_auth();
$tc = preg_replace('/\D/', '', (string) ($personel->tc_kimlik_no ?? ''));
$maskedTc = strlen($tc) === 11 ? substr($tc, 0, 3) . '*****' . substr($tc, -3) : '***********';

mobile_ok([
    'profile' => mobile_personel_ozet($personel) + [
        'tc_masked' => $maskedTc,
        'dogum_tarihi' => $personel->dogum_tarihi ?? null,
        'ise_giris_tarihi' => $personel->ise_giris_tarihi ?? null,
        'telefon' => $personel->cep_telefonu ?? ($personel->telefon ?? ''),
        'email' => $personel->email_adresi ?? '',
        'adres' => $personel->adres ?? '',
        'pozisyon' => $personel->pozisyon ?? ($personel->gorev ?? ''),
        'aktif' => true,
    ],
]);
