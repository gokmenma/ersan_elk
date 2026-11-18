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
        'efatura_loglari',
    ],

    'veri_haric_tablolar' => [],

    'mail_alicilar' => ['beyzade83@gmail.com'],

    'mail_gonder' => true,
    'drive_yedekleme' => true,
    'drive_saklama_gun' => 90,
    'drive_artimli_saklama_gun' => 14,

    'dosya_kaynaklari' => [
        'uploads',
        'files',
        'storage/dosya-yonetimi',
        'storage/invoices',
        'storage/mobil-imza',
        'assets/images/users',
        'assets/images/demirbas',
        'assets/belgeler',
    ],

    'dosya_haric' => [
        '*/.DS_Store',
        '*/Thumbs.db',
        '*.tmp',
        '*.part',
    ],

    'dosya_saklama_gun' => 14,
    'dosya_tam_yedek_gunu' => 0,
    'dosya_sikistirma_seviyesi' => 3,
    'dosya_drive_saklama_gun' => 60,
    'dosya_drive_artimli_saklama_gun' => 14,
];
