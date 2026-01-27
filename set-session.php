<?php
session_start();
if (isset($_SESSION["id"]) && !empty($_SESSION["id"])) {
    $_SESSION["firma_id"] = $_GET["firma_id"];
    $page = $_GET["p"] ?? "home";


    // Varsayılan firma olarak kaydet (30 gün geçerli)
    if (isset($_GET["varsayilan"]) && $_GET["varsayilan"] == "1") {
        // Veritabanını güncelle
        require_once "vendor/autoload.php";
        $FirmaModel = new \App\Model\FirmaModel();
        $FirmaModel->setDefault($_GET["firma_id"]);

        setcookie(
            'varsayilan_firma_id',
            $_GET["firma_id"],
            time() + (30 * 24 * 60 * 60), // 30 gün
            '/',
            '',
            false, // Secure - HTTPS için true yapılabilir
            true   // HttpOnly
        );
    }

    header("location:index?p=$page");
} else {
    header("location:login.php");
}

