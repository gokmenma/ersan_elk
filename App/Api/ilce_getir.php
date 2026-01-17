<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Helper;
use App\Helper\City;


if(isset($_GET['il_id'])) {
    $il_id = ($_GET['il_id']);
    $City = new City();
    $districts = $City->getDistricts($il_id);
   

    header('Content-Type: application/json');
    echo json_encode($districts);
    exit;
}