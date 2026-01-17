<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Helper\Helper;

$tests = [
    'Düzce Üniversitesi',
    'MANİSA CELAL BAYAR ÜNİVERSİTESİ REKTÖRLÜĞÜNE',
    'KAHRAMANMARAŞ SÜTÇÜ İMAM ÜNİVERSİTESİ REKTÖRLÜĞÜ',
    'İstanbul Üniversitesi Rektörlüğü',
    'Cumhurbaşkanlığı Genel Sekreterliği',
    'Sağlık Bakanlığı',
    'T.C. Çalışma ve Sosyal Güvenlik Bakanlığına',
    'İstanbul Üniversitesi-Cerrahpaşa Rektörlüğü',
    'NAMIK KEMAL ÜNİVERSİTESİNE',
];

foreach ($tests as $t) {
    $out = Helper::gidenEki(Helper::trUpper($t));
    echo $t . ' => ' . $out . PHP_EOL;
}
