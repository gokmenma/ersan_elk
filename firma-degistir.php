<?php
/**
 * Firma Değiştir
 * Varsayılan firma cookie'sini sıfırlar ve firma seçim sayfasına yönlendirir
 */
session_start();

// Varsayılan firma cookie'sini sıfırla
if (isset($_COOKIE['varsayilan_firma_id'])) {
    setcookie('varsayilan_firma_id', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

if (isset($_COOKIE['varsayilan_firma_kodu'])) {
    setcookie('varsayilan_firma_kodu', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

// Mevcut firma session'ını temizle
if (isset($_SESSION['firma_id'])) {
    unset($_SESSION['firma_id']);
}

// Firma seçim sayfasına yönlendir
header("Location: firma-secim.php");
exit;
