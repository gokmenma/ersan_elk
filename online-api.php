<?php
// Public JSON proxy for İşBank Posmatik Online API.
//
// Neden var?
// - /views/... dizini production'da çoğu zaman public değildir.
// - admin/index.php router'ı ise HTML layout basabilir / auth yönlendirmesi yapabilir.
//
// Bu dosya doğrudan çağrılabilir bir endpoint sağlar ve view içindeki JSON mantığını aynen çalıştırır.

// Not: view dosyası kendi Autoloader/session/header ayarlarını yapıyor.

require_once __DIR__ . '/views/gelir-gider/online-api.php';
