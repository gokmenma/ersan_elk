<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// .env değerleri (ENCRYPTION_KEY, DB bilgileri vb.) Model oluşturulmadan da erişilebilir olmalı.
if (!isset($_ENV['ENCRYPTION_KEY']) && file_exists(__DIR__ . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

spl_autoload_register(function ($class) {
    // Namespace prefix'ini kaldır
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/App/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
