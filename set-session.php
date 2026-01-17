<?php 
session_start();
if (isset($_SESSION["id"]) && !empty($_SESSION["id"])) {
    $_SESSION["firma_id"] = $_GET["firma_id"];
    header("location:index?p=home");
}else{
    header("location:login.php");
}

