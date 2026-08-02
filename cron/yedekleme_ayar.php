<?php

$kokDizin = dirname(__DIR__);

return [
    'yedek_dizini' => $_ENV['BACKUP_DIR'] ?? ($kokDizin . '/backups'),

    'saklama_gun' => 14,

    'tam_yedek_gunu' => 0,

    'paket_limiti' => 512 * 1024,

    'satir_limiti' => 2000,

    'sikistirma_seviyesi' => 6,

    'artimli_tablolar' => [
        'endeks_okuma',
        'yapilan_isler',
        'sayac_degisim',
        'system_logs',
        'personel_giris_loglari',
        'personel_hareketleri',
        'demirbas_hareketler',
        'mesaj_log',
        'bildirimler',
    ],

    'veri_haric_tablolar' => [],

    'mail_alicilar' => ['beyzade83@gmail.com'],

    'mail_gonder' => true,
];
