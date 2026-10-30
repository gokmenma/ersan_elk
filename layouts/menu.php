<?php
use App\Helper\Helper;
ob_start();
include Helper::base_url('layouts/topbar.php');
include Helper::base_url('layouts/sidebar.php');
include Helper::base_url('layouts/favorites-bar.php');
?>
<script>if (typeof feather !== 'undefined') { feather.replace(); }</script>
<span id="app-shell-ready" hidden></span>
<?php
echo ob_get_clean();
