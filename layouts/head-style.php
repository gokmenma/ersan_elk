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

        const getAdaptiveColors = color => {
            const channels = [1, 3, 5].map(start => {
                const value = parseInt(color.slice(start, start + 2), 16) / 255;
                return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
            });
            const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
            const dark = luminance < 0.42;
            return {
                text: dark ? '#f8fafc' : '#1f2937',
                muted: dark ? '#cbd5e1' : '#64748b',
                subtle: dark ? '#94a3b8' : '#64748b',
                surface: dark ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.07)',
                border: dark ? 'rgba(255,255,255,.16)' : 'rgba(15,23,42,.14)',
                dark
            };
        };

        // Synchronously apply custom topbar color style tag
        const customTopbar = localStorage.getItem('custom-topbar-color');
        if (customTopbar) {
            const contrast = getAdaptiveColors(customTopbar);
            const style = document.createElement('style');
            style.id = 'custom-topbar-style';
            style.innerHTML = `body #page-topbar, body .navbar-brand-box { background-color: ${customTopbar} !important; border-color: ${customTopbar} !important; } body #page-topbar .header-item, body #page-topbar .logo-txt, body #page-topbar #topbar-page-title { color: ${contrast.text} !important; } body #page-topbar #topbar-page-desc { color: ${contrast.muted} !important; } body #page-topbar .header-item svg { color: ${contrast.text} !important; stroke: currentColor !important; } body #page-topbar .logo-dark { display: ${contrast.dark ? 'none' : 'block'} !important; } body #page-topbar .logo-light { display: ${contrast.dark ? 'block' : 'none'} !important; }`;
            document.head.appendChild(style);
        }

        // Synchronously apply custom sidebar color style tag
        const customSidebar = localStorage.getItem('custom-sidebar-color');
        if (customSidebar) {
            const contrast = getAdaptiveColors(customSidebar);
            const style = document.createElement('style');
            style.id = 'custom-sidebar-style';
            style.innerHTML = `body { --sidebar-bg: ${customSidebar}; --sidebar-border: ${contrast.border}; --sidebar-item-hover: ${contrast.surface}; --sidebar-item-active: ${contrast.surface}; --sidebar-foreground: ${contrast.text}; --sidebar-muted: ${contrast.muted}; } body .vertical-menu, body .sidebar-sticky-top { background-color: ${customSidebar} !important; border-color: ${contrast.border} !important; } body .sidebar-search { background-color: ${contrast.surface} !important; border-color: ${contrast.border} !important; color: ${contrast.text} !important; } body .sidebar-search::placeholder { color: ${contrast.subtle} !important; } body .sidebar-search-container .search-icon, body #sidebar-menu ul li a i, body #sidebar-menu ul li a svg { color: ${contrast.muted} !important; stroke: currentColor !important; } body #sidebar-menu ul li a, body #sidebar-menu ul li ul.sub-menu li a, body .brand-name { color: ${contrast.text} !important; } body #sidebar-menu .menu-title, body .brand-sub { color: ${contrast.muted} !important; } body #sidebar-menu ul li a:hover, body #sidebar-menu ul li a.active, body #sidebar-menu ul li.mm-active > a { background-color: ${contrast.surface} !important; color: ${contrast.text} !important; } body .vertical-menu .logo-dark { display: ${contrast.dark ? 'none' : 'block'} !important; } body .vertical-menu .logo-light { display: ${contrast.dark ? 'block' : 'none'} !important; }`;
            document.head.appendChild(style);
        }
        // Synchronously apply critical layout width/left position styles
        const layoutStyle = document.createElement('style');
        layoutStyle.id = 'layout-initial-position-style';
        layoutStyle.innerHTML = `@media (min-width: 992px) { body:not([data-sidebar-size="sm"]) #page-topbar, body:not([data-sidebar-size="sm"]) .quick-favorites-bar { left: 250px !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .main-content { margin-left: 250px !important; margin-right: 0 !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .vertical-menu { width: 250px !important; } body[data-sidebar-size="sm"] #page-topbar, body[data-sidebar-size="sm"] .quick-favorites-bar { left: 60px !important; width: calc(100% - 60px) !important; } body[data-sidebar-size="sm"] .main-content { margin-left: 60px !important; margin-right: 0 !important; width: calc(100% - 60px) !important; } body[data-sidebar-size="sm"] .vertical-menu { width: 60px !important; } }`;
        document.head.appendChild(layoutStyle);
    })();
</script>

<style>
/* Layout Symmetry & Boxed Mode Reset */
html, body, #layout-wrapper {
    max-width: 100% !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

body[data-layout-size="boxed"] #layout-wrapper,
body[data-layout-size="boxed"] #page-topbar,
body[data-layout-size="boxed"] .main-content,
body[data-layout-size="boxed"] .container-fluid,
body[data-layout-size="boxed"] .footer,
body[data-layout="horizontal"] .container-fluid {
    max-width: 100% !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

#page-topbar .navbar-header {
    padding-left: 12px !important;
}

@media (min-width: 992px) {
    body:not([data-sidebar-size="sm"]) #page-topbar,
    body:not([data-sidebar-size="sm"]) .quick-favorites-bar {
        left: 250px !important;
        width: calc(100% - 250px) !important;
    }
    body:not([data-sidebar-size="sm"]) .main-content {
        margin-left: 250px !important;
        margin-right: 0 !important;
        width: calc(100% - 250px) !important;
    }
    body:not([data-sidebar-size="sm"]) .vertical-menu {
        width: 250px !important;
    }

    body[data-sidebar-size="sm"] #page-topbar,
    body[data-sidebar-size="sm"] .quick-favorites-bar {
        left: 60px !important;
        width: calc(100% - 60px) !important;
    }
    body[data-sidebar-size="sm"] .main-content {
        margin-left: 60px !important;
        margin-right: 0 !important;
        width: calc(100% - 60px) !important;
    }
    body[data-sidebar-size="sm"] .vertical-menu {
        width: 60px !important;
    }
}

/* Dar (daraltılmış) menüde ikonları tam ortalama */
body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu > ul > li > a {
    padding: 12px 0 !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu > ul > li > a i,
body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu > ul > li > a svg,
body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu > ul > li > a [data-feather] {
    margin: 0 auto !important;
    display: block !important;
}

/* Dar menüde favori yıldız ikonlarını gizleme */
body[data-sidebar-size="sm"] .star-btn,
body[data-sidebar-size="sm"] .vertical-menu .star-btn,
body[data-sidebar-size="sm"] #side-menu .star-btn,
body[data-sidebar-size="sm"] #sidebar-menu .star-btn {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
}

.page-content {
    padding-top: 128px !important;
    padding-bottom: 60px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

body:not(:has(#quick-favorites-bar)) .main-content .page-content {
    padding-top: 86px !important;
}

.main-content .container-fluid,
.page-content .container-fluid {
    width: 100% !important;
    max-width: 100% !important;
    padding-left: 16px !important;
    padding-right: 16px !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    box-sizing: border-box !important;
}

.main-content .card {
    margin-bottom: 20px !important;
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



