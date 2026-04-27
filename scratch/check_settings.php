<?php
require_once 'Autoloader.php';
$settings = new \App\Model\SettingsModel();
$all = $settings->getAllSettingsAsKeyValue();
print_r($all);
