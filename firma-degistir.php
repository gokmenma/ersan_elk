<?php
/**
 * Firma Değiştir
 * Varsayılan firma cookie'sini sıfırlar ve firma seçim sayfasına yönlendirir
 */
session_start();

// Varsayılan firma cookie'sini sıfırla
if (isset($_COOKIE['varsayilan_firma_id'])) {
    setcookie('varsayilan_firma_id', '', time() - 3600, '/');
}

// Mevcut firma session'ını temizle
if (isset($_SESSION['firma_id'])) {
    unset($_SESSION['firma_id']);
}

// Firma seçim sayfasına yönlendir
header("Location: firma-secim.php");
exit;
