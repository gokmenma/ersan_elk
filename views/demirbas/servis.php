<?php
// Geçiş aşaması: mevcut list.php içindeki servis sekmesini bağımsız sayfa URL'sinde aç.
$_GET['tab'] = 'servis';
require __DIR__ . '/list.php';
?>

<style>
	#demirbasTab {
		display: none !important;
	}
</style>
