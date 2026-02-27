<?php
session_start();
if (isset($_SESSION["id"]) && !empty($_SESSION["id"])) {
    $_SESSION["firma_id"] = $_GET["firma_id"];
    if (isset($_GET["firma_kodu"])) {
        $_SESSION["firma_kodu"] = $_GET["firma_kodu"];
    }else{
        require_once "vendor/autoload.php";
        $FirmaModel = new \App\Model\FirmaModel();
        $firma = $FirmaModel->find($_GET["firma_id"]);
        $_SESSION["firma_kodu"] = $firma->firma_kodu;
    }
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

        // Firma kodunu da cookie'ye yaz (fallback olarak id cookie'si zaten var)
        if (isset($_GET["firma_kodu"]) && !empty($_GET["firma_kodu"])) {
            setcookie(
                'varsayilan_firma_kodu',
                $_GET["firma_kodu"],
                time() + (30 * 24 * 60 * 60), // 30 gün
                '/',
                '',
                false,
                true
            );
        }
    }

    header("location:index?p=$page");
} else {
    header("location:login.php");
}

