<?php
// Quick CLI test: ensure the online-api proxy returns JSON for GET.
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../online-api.php';
