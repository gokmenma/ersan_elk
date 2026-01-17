<?php
use App\Helper\Helper;
echo "url". $_SERVER['SCRIPT_FILENAME'];
include Helper::base_url('layouts/topbar.php');
include Helper::base_url('layouts/sidebar.php');

//include '../../layouts/horizontal-menu.php';
