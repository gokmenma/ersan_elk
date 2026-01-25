<script>
    (function () {
        const htmlAttributes = [
            { name: 'data-theme-mode', target: 'html' },
            { name: 'data-bs-theme', target: 'html' },
            { name: 'dir', target: 'html' }
        ];

        const applyAttribute = (attr, value) => {
            const targetEl = document.documentElement;
            if (attr.name === 'dir') {
                targetEl.setAttribute('dir', value);
            } else {
                targetEl.setAttribute(attr.name, value);
            }
        };

        htmlAttributes.forEach(attr => {
            const value = localStorage.getItem(attr.name);
            if (value) applyAttribute(attr, value);
        });
    })();
</script>

<?php

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Helper\Helper;


?>

<!-- preloader css -->
<link href="<?php echo Helper::base_url('assets/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="<?php echo Helper::base_url("assets/css/preloader.min.css"); ?>" type="text/css" />

<!-- Bootstrap Css -->
<link href="<?php echo Helper::base_url('assets/css/bootstrap.min.css'); ?>" id="bootstrap-style" rel="stylesheet"
    type="text/css" />
<!-- Icons Css -->
<!-- App Css-->
<link href="<?php echo Helper::base_url('assets/css/app.min.css'); ?>" id="app-style" rel="stylesheet" type="text/css" />

<link href="<?php echo Helper::base_url('assets/css/style.css'); ?>" id="app-style" rel="stylesheet" type="text/css" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- sweet-alert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/libs/jquery/jquery.3.7.1.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="<?php echo Helper::base_url('assets/libs/flatpickr/flatpickr.min.css'); ?>">

<link href="assets/libs//summernote/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
    integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
    crossorigin="anonymous"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">