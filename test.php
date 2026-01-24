<?php 


require_once "bootstrap.php";


use App\Model\UserModel;
use App\Helper\Helper;

$UserModel = new UserModel();



Helper::dd($UserModel->isSuperAdmin());
