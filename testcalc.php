<?php
require 'bootstrap.php';
$bp = new \App\Model\BordroPersonelModel();
$bp->hesaplaMaas(1177);
echo "OK\n";
