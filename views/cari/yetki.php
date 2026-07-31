<?php

/**
 * Cari dışa aktarım dosyaları (PDF/Excel) için ortak oturum ve yetki kontrolü.
 * Yetki yoksa isteği sonlandırır.
 */
if (!function_exists('cariExportYetkiKontrol')) {
    function cariExportYetkiKontrol(string $yetkiAdi): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            http_response_code(403);
            die("Bu işlem için oturum açmanız gerekiyor.");
        }

        $kullanici_id = (int) ($_SESSION["id"] ?? $_SESSION["user_id"] ?? ($_SESSION["user"]->id ?? 0));
        if ($kullanici_id <= 0) {
            http_response_code(403);
            die("Bu işlem için oturum açmanız gerekiyor.");
        }

        try {
            $User = new \App\Model\UserModel();
            $yetkili = $User->hasUserPermission($kullanici_id, $yetkiAdi);
        } catch (\Throwable $e) {
            error_log("Cari export yetki kontrolü hatası: " . $e->getMessage());
            http_response_code(500);
            die("İşlem sırasında bir hata oluştu.");
        }

        if (!$yetkili) {
            http_response_code(403);
            die("Bu işlem için yetkiniz bulunmuyor.");
        }

        return $kullanici_id;
    }
}
