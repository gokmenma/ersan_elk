<script>
    (function () {
        const htmlAttributes = [
            { name: 'data-theme-mode', target: 'html' },
            { name: 'data-font-family', target: 'html' },
            { name: 'data-bs-theme', target: 'html' },
            { name: 'data-orientation', target: 'html' },
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
            let value = localStorage.getItem(attr.name);
            if (!value && attr.name === 'data-font-family') value = 'Geist';
            if (value) applyAttribute(attr, value);
        });

        // Synchronously apply custom primary color CSS variables
        const customPrimary = localStorage.getItem('custom-primary-color');
        if (customPrimary) {
            document.documentElement.style.setProperty('--bs-primary', customPrimary);
            const r = parseInt(customPrimary.slice(1, 3), 16),
                  g = parseInt(customPrimary.slice(3, 5), 16),
                  b = parseInt(customPrimary.slice(5, 7), 16);
            if (!isNaN(r) && !isNaN(g) && !isNaN(b)) {
                document.documentElement.style.setProperty('--bs-primary-rgb', `${r}, ${g}, ${b}`);
            }
        }

        // Synchronously apply custom topbar color style tag
        const customTopbar = localStorage.getItem('custom-topbar-color');
        if (customTopbar) {
            const style = document.createElement('style');
            style.id = 'custom-topbar-style';
            style.innerHTML = `body #page-topbar, body .navbar-brand-box { background-color: ${customTopbar} !important; border-color: ${customTopbar} !important; } body #page-topbar .header-item, body #page-topbar .logo-txt { color: #fff !important; } body #page-topbar .logo-dark { display: none !important; } body #page-topbar .logo-light { display: block !important; }`;
            document.head.appendChild(style);
        }

        // Synchronously apply custom sidebar color style tag
        const customSidebar = localStorage.getItem('custom-sidebar-color');
        if (customSidebar) {
            const style = document.createElement('style');
            style.id = 'custom-sidebar-style';
            style.innerHTML = `body .vertical-menu, [data-bs-theme="dark"] .main-content, [data-bs-theme="dark"] .page-content { background-color: ${customSidebar} !important; } body .sidebar-sticky-top { background-color: ${customSidebar} !important; } body .sidebar-search { background-color: rgba(255, 255, 255, 0.12) !important; border-color: rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; } body .sidebar-search::placeholder { color: rgba(255, 255, 255, 0.5) !important; } body #sidebar-menu ul li a { color: rgba(255, 255, 255, 0.7) !important; } body #sidebar-menu ul li a i { color: rgba(255, 255, 255, 0.7) !important; } body #sidebar-menu ul li a:hover, body #sidebar-menu ul li a.active, body #sidebar-menu ul li.mm-active > a { color: #fff !important; } body #sidebar-menu .menu-title { color: rgba(255, 255, 255, 0.4) !important; } body .vertical-menu .logo-dark { display: none !important; } body .vertical-menu .logo-light { display: block !important; }`;
            document.head.appendChild(style);
        // Synchronously apply critical layout width/left position styles
        const layoutStyle = document.createElement('style');
        layoutStyle.id = 'layout-initial-position-style';
        layoutStyle.innerHTML = `@media (min-width: 992px) { body:not([data-sidebar-size="sm"]) #page-topbar, body:not([data-sidebar-size="sm"]) .quick-favorites-bar { left: 250px !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .main-content { margin-left: 250px !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .vertical-menu { width: 250px !important; } }`;
        document.head.appendChild(layoutStyle);
    })();
</script>

<style>
@media (min-width: 992px) {
    body:not([data-sidebar-size="sm"]) #page-topbar,
    body:not([data-sidebar-size="sm"]) .quick-favorites-bar {
        left: 250px !important;
        width: calc(100% - 250px) !important;
    }
    body:not([data-sidebar-size="sm"]) .main-content {
        margin-left: 250px !important;
        width: calc(100% - 250px) !important;
    }
    body:not([data-sidebar-size="sm"]) .vertical-menu {
        width: 250px !important;
    }

    body[data-sidebar-size="sm"] #page-topbar,
    body[data-sidebar-size="sm"] .quick-favorites-bar {
        left: 75px !important;
        width: calc(100% - 75px) !important;
    }
    body[data-sidebar-size="sm"] .main-content {
        margin-left: 75px !important;
        width: calc(100% - 75px) !important;
    }
    body[data-sidebar-size="sm"] .vertical-menu {
        width: 75px !important;
    }
}
</style>

<?php

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Helper\Helper;


?>

<!-- Google Fonts: Geist, Inter, Outfit, Poppins, Plus Jakarta Sans, Lexend -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lexend:wght@400;500;600;700&display=swap"
    rel="stylesheet">

<!-- preloader css -->
<link href="<?php echo Helper::base_url('assets/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="<?php echo Helper::base_url("assets/css/preloader.min.css"); ?>" type="text/css" />

<!-- Bootstrap Css -->
<link href="<?php echo Helper::base_url('assets/css/bootstrap.min.css'); ?>" id="bootstrap-style" rel="stylesheet"
    type="text/css" />
<!-- Icons Css -->
<!-- App Css-->
<link href="<?php echo Helper::base_url('assets/css/app.min.css'); ?>" id="app-style" rel="stylesheet"
    type="text/css" />

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="<?php echo Helper::base_url('assets/css/style.css?v=' . filemtime("assets/css/style.css")); ?>"
    id="custom-style" rel="stylesheet" type="text/css" />
<!-- jQuery -->
<script src="<?php echo Helper::base_url('assets/libs/jquery/jquery.3.7.1.min.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="<?php echo Helper::base_url('assets/libs/flatpickr/flatpickr.min.css'); ?>">

<link href="assets/libs//summernote/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
    integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
    crossorigin="anonymous"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">



