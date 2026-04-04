<?php
// Geçiş aşaması: mevcut list.php içindeki zimmet sekmesini bağımsız sayfa URL'sinde aç.
$_GET['tab'] = 'zimmet';
require __DIR__ . '/list.php';
?>

<style>
	#demirbasTab {
		display: none !important;
	}
</style>
