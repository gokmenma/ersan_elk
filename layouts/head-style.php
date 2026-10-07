<script>
    (function () {
        const htmlAttributes = [
            { name: 'data-theme-mode', target: 'html' },
            { name: 'data-font-family', target: 'html' },
            { name: 'data-bs-theme', target: 'html' },
            { name: 'data-orientation', target: 'html' },
            { name: 'data-theme-preset', target: 'html' },
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

        // Comprehensive Theme Presets Definitions for Instant Zero-Flicker Apply
        const THEME_PRESETS_CONFIG = {
            'kode': { primary: '#399bff', topbar: '#399bff', sidebar: '#282e38', themeMode: 'default', layoutMode: 'light', font: 'Inter' },
            'ersan': { primary: '#e2bd61', topbar: 'light', sidebar: 'dark', themeMode: 'ersan', layoutMode: 'light', font: 'Outfit' },
            'midnight-emerald': { primary: '#10b981', topbar: '#10b981', sidebar: '#15241f', themeMode: 'emerald', layoutMode: 'light', font: 'Plus Jakarta Sans' },
            'royal-purple': { primary: '#5156be', topbar: '#5156be', sidebar: '#1e1b2e', themeMode: 'purple', layoutMode: 'light', font: 'Outfit' },
            'crimson-rose': { primary: '#ec003f', topbar: '#ec003f', sidebar: '#232125', themeMode: 'rose', layoutMode: 'light', font: 'Poppins' },
            'minimalist': { primary: '#18181b', topbar: 'light', sidebar: 'light', themeMode: 'slate', layoutMode: 'light', font: 'Geist' },
            'dark-pro': { primary: '#06b6d4', topbar: 'dark', sidebar: 'dark', themeMode: 'cyan', layoutMode: 'dark', font: 'Inter' },
            'ocean-deep': { primary: '#0284c7', topbar: '#0284c7', sidebar: '#0f172a', themeMode: 'cyan', layoutMode: 'light', font: 'Plus Jakarta Sans' },
            'sunset-amber': { primary: '#f97316', topbar: '#f59e0b', sidebar: '#1c1917', themeMode: 'orange', layoutMode: 'light', font: 'Montserrat' },
            'forest-moss': { primary: '#10b981', topbar: '#059669', sidebar: '#064e3b', themeMode: 'emerald', layoutMode: 'light', font: 'Manrope' },
            'cyber-violet': { primary: '#8b5cf6', topbar: '#7c3aed', sidebar: '#180d38', themeMode: 'purple', layoutMode: 'light', font: 'Space Grotesk' },
            'nordic-slate': { primary: '#64748b', topbar: '#475569', sidebar: '#1e293b', themeMode: 'slate', layoutMode: 'light', font: 'DM Sans' },
            'ruby-dark': { primary: '#e11d48', topbar: '#9f1239', sidebar: '#1f0a10', themeMode: 'rose', layoutMode: 'light', font: 'Roboto' },
            'mint-fresh': { primary: '#0d9488', topbar: '#0d9488', sidebar: '#132a26', themeMode: 'teal', layoutMode: 'light', font: 'Lexend' },
            'mocha-gold': { primary: '#d97706', topbar: '#78350f', sidebar: '#271406', themeMode: 'ersan', layoutMode: 'light', font: 'Nunito' }
        };

        const savedPresetKey = localStorage.getItem('data-theme-preset') || 'ersan';
        const activePreset = THEME_PRESETS_CONFIG[savedPresetKey] || THEME_PRESETS_CONFIG['ersan'];

        htmlAttributes.forEach(attr => {
            let value = localStorage.getItem(attr.name);
            if (!value && attr.name === 'data-font-family') value = activePreset.font || 'Outfit';
            if (!value && attr.name === 'data-theme-preset') value = savedPresetKey;
            if (!value && attr.name === 'data-theme-mode') value = activePreset.themeMode || 'ersan';
            if (!value && attr.name === 'data-bs-theme') value = activePreset.layoutMode || 'light';
            if (value) applyAttribute(attr, value);
        });

        // Synchronously read evrak summary cards state before render
        try {
            if (localStorage.getItem('evrak_ozet_kapali') === '1') {
                document.documentElement.classList.add('evrak-ozet-baslangic-kapali');
            }
        } catch (e) {}

        let customPrimary = localStorage.getItem('custom-primary-color');
        if (!customPrimary && activePreset && activePreset.primary) {
            customPrimary = activePreset.primary;
        }

        // Synchronously apply custom primary color CSS variables
        if (customPrimary) {
            document.documentElement.style.setProperty('--bs-primary', customPrimary);
            document.documentElement.style.setProperty('--bs-link-color', customPrimary);
            document.documentElement.style.setProperty('--bs-link-hover-color', customPrimary);
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
                text: dark ? '#ffffff' : '#1f2937',
                muted: dark ? 'rgba(255,255,255,0.75)' : '#64748b',
                subtle: dark ? 'rgba(255,255,255,0.6)' : '#64748b',
                surface: dark ? 'rgba(255,255,255,.15)' : 'rgba(15,23,42,.07)',
                border: dark ? 'rgba(255,255,255,.2)' : 'rgba(15,23,42,.14)',
                dark
            };
        };

        // Synchronously apply custom topbar color style tag
        let customTopbar = localStorage.getItem('custom-topbar-color');
        if (!customTopbar && activePreset && activePreset.topbar && activePreset.topbar.startsWith('#')) {
            customTopbar = activePreset.topbar;
        }
        if (customTopbar && customTopbar.startsWith('#')) {
            const contrast = getAdaptiveColors(customTopbar);
            const style = document.createElement('style');
            style.id = 'custom-topbar-style';
            style.innerHTML = `html body #page-topbar, html body .navbar-brand-box, body[data-topbar] #page-topbar, body[data-topbar] .navbar-brand-box { background-color: ${customTopbar} !important; background-image: none !important; border-color: ${customTopbar} !important; } html body #page-topbar .header-item, html body #page-topbar .logo-txt, html body #page-topbar #topbar-page-title, html body #page-topbar .topbar-page-title, body[data-topbar] #page-topbar .header-item, body[data-topbar] #page-topbar .logo-txt, body[data-topbar] #page-topbar #topbar-page-title, body[data-topbar] #page-topbar .topbar-page-title { color: ${contrast.text} !important; } html body #page-topbar #topbar-page-desc, html body #page-topbar .topbar-page-desc, body[data-topbar] #page-topbar #topbar-page-desc, body[data-topbar] #page-topbar .topbar-page-desc { color: ${contrast.muted} !important; } html body #page-topbar .header-item svg, html body #page-topbar .header-item i, body[data-topbar] #page-topbar .header-item svg, body[data-topbar] #page-topbar .header-item i { color: ${contrast.text} !important; stroke: currentColor !important; } html body #page-topbar .logo-dark, body[data-topbar] #page-topbar .logo-dark { display: ${contrast.dark ? 'none' : 'block'} !important; } html body #page-topbar .logo-light, body[data-topbar] #page-topbar .logo-light { display: ${contrast.dark ? 'block' : 'none'} !important; } html body #page-topbar .global-search-input-box, body[data-topbar] #page-topbar .global-search-input-box { background-color: ${contrast.surface} !important; border-color: ${contrast.border} !important; box-shadow: none !important; } html body #page-topbar .global-search-input-box:focus-within, body[data-topbar] #page-topbar .global-search-input-box:focus-within { background-color: ${contrast.dark ? 'rgba(255,255,255,0.2)' : '#ffffff'} !important; border-color: ${contrast.dark ? 'rgba(255,255,255,0.4)' : 'var(--bs-primary, #3b82f6)'} !important; box-shadow: 0 0 0 3px ${contrast.dark ? 'rgba(255,255,255,0.15)' : 'rgba(59,130,246,0.15)'} !important; } html body #page-topbar .global-search-input, body[data-topbar] #page-topbar .global-search-input { color: ${contrast.text} !important; } html body #page-topbar .global-search-input::placeholder, body[data-topbar] #page-topbar .global-search-input::placeholder { color: ${contrast.subtle} !important; } html body #page-topbar .global-search-icon, body[data-topbar] #page-topbar .global-search-icon { color: ${contrast.muted} !important; } html body #page-topbar .global-search-kbd-badge kbd, body[data-topbar] #page-topbar .global-search-kbd-badge kbd { background-color: ${contrast.surface} !important; border-color: ${contrast.border} !important; color: ${contrast.muted} !important; box-shadow: none !important; } html body #page-topbar .global-search-clear-btn, body[data-topbar] #page-topbar .global-search-clear-btn { color: ${contrast.muted} !important; }`;
            document.head.appendChild(style);
        }

        // Synchronously apply custom sidebar color style tag
        let customSidebar = localStorage.getItem('custom-sidebar-color');
        if (!customSidebar && activePreset && activePreset.sidebar && activePreset.sidebar.startsWith('#')) {
            customSidebar = activePreset.sidebar;
        }
        if (customSidebar && customSidebar.startsWith('#')) {
            const contrast = getAdaptiveColors(customSidebar);
            const style = document.createElement('style');
            style.id = 'custom-sidebar-style';
            style.innerHTML = `body { --sidebar-bg: ${customSidebar}; --sidebar-border: ${contrast.border}; --sidebar-item-hover: ${contrast.surface}; --sidebar-item-active: ${contrast.surface}; --sidebar-foreground: ${contrast.text}; --sidebar-muted: ${contrast.muted}; } html body .vertical-menu, html body .sidebar-sticky-top, body[data-sidebar] .vertical-menu, body[data-sidebar] .sidebar-sticky-top { background-color: ${customSidebar} !important; border-color: ${contrast.border} !important; } html body .sidebar-search, body[data-sidebar] .sidebar-search { background-color: ${contrast.surface} !important; border-color: ${contrast.border} !important; color: ${contrast.text} !important; } html body .sidebar-search:focus, body[data-sidebar] .sidebar-search:focus { background-color: ${contrast.surface} !important; border-color: ${contrast.border} !important; color: ${contrast.text} !important; } html body .sidebar-search::placeholder, body[data-sidebar] .sidebar-search::placeholder { color: ${contrast.subtle} !important; } html body .sidebar-search-container .search-icon, html body #sidebar-menu ul li a i, html body #sidebar-menu ul li a svg, body[data-sidebar] .sidebar-search-container .search-icon, body[data-sidebar] #sidebar-menu ul li a i, body[data-sidebar] #sidebar-menu ul li a svg { color: ${contrast.muted} !important; stroke: currentColor !important; } html body #sidebar-menu ul li a, html body #sidebar-menu ul li ul.sub-menu li a, html body .brand-name, body[data-sidebar] #sidebar-menu ul li a, body[data-sidebar] #sidebar-menu ul li ul.sub-menu li a, body[data-sidebar] .brand-name { color: ${contrast.text} !important; } html body #sidebar-menu .menu-title, html body .brand-sub, body[data-sidebar] #sidebar-menu .menu-title, body[data-sidebar] .brand-sub { color: ${contrast.muted} !important; } html body #sidebar-menu ul li a:hover, html body #sidebar-menu ul li a.active, html body #sidebar-menu ul li.mm-active > a, body[data-sidebar] #sidebar-menu ul li a:hover, body[data-sidebar] #sidebar-menu ul li a.active, body[data-sidebar] #sidebar-menu ul li.mm-active > a { background-color: ${contrast.surface} !important; color: ${contrast.text} !important; } html body .vertical-menu .logo-dark, body[data-sidebar] .vertical-menu .logo-dark { display: ${contrast.dark ? 'none' : 'block'} !important; } html body .vertical-menu .logo-light, body[data-sidebar] .vertical-menu .logo-light { display: ${contrast.dark ? 'block' : 'none'} !important; }`;
            document.head.appendChild(style);
        }
        // Synchronously apply critical layout width/left position styles
        const layoutStyle = document.createElement('style');
        layoutStyle.id = 'layout-initial-position-style';
        layoutStyle.innerHTML = `@media (min-width: 992px) { body:not([data-sidebar-size="sm"]) #page-topbar, body:not([data-sidebar-size="sm"]) .quick-favorites-bar { left: 250px !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .main-content { margin-left: 250px !important; margin-right: 0 !important; width: calc(100% - 250px) !important; } body:not([data-sidebar-size="sm"]) .vertical-menu { width: 250px !important; } body[data-sidebar-size="sm"] #page-topbar, body[data-sidebar-size="sm"] .quick-favorites-bar { left: 60px !important; width: calc(100% - 60px) !important; } body[data-sidebar-size="sm"] .main-content { margin-left: 60px !important; margin-right: 0 !important; width: calc(100% - 60px) !important; } body[data-sidebar-size="sm"] .vertical-menu { width: 60px !important; } }`;
        document.head.appendChild(layoutStyle);

        // Synchronously prevent scroll jump (Zero-Flicker Sidebar Scroll Lock)
        const savedSidebarScroll = localStorage.getItem('sidebar_scroll_top');
        if (savedSidebarScroll !== null) {
            const scrollPos = parseInt(savedSidebarScroll, 10) || 0;
            if (scrollPos > 0) {
                // Pozisyon ayarlanana kadar 0 konumunun görünmesini engelle
                const lockStyle = document.createElement('style');
                lockStyle.id = 'sidebar-scroll-lock';
                lockStyle.innerHTML = '.vertical-menu .sidebar-menu-scroll { opacity: 0 !important; visibility: hidden !important; }';
                document.head.appendChild(lockStyle);

                const unlockSidebar = () => {
                    const lock = document.getElementById('sidebar-scroll-lock');
                    if (lock) lock.remove();
                };

                const applySidebarScroll = () => {
                    const wrapper = document.querySelector('.vertical-menu .simplebar-content-wrapper');
                    if (wrapper) {
                        wrapper.scrollTop = scrollPos;
                        unlockSidebar();
                        return true;
                    }
                    const rawScroll = document.getElementById('sidebar-menu-scroll') || document.querySelector('.sidebar-menu-scroll');
                    if (rawScroll) {
                        rawScroll.scrollTop = scrollPos;
                    }
                    return false;
                };

                const observer = new MutationObserver(() => {
                    if (applySidebarScroll()) {
                        // SimpleBar wrapper oluştu ve scroll uygulandı
                    }
                });
                observer.observe(document.documentElement, { childList: true, subtree: true });

                // DOM ve load anlarında teyit et ve kilidi kesinlikle kaldır
                window.addEventListener('DOMContentLoaded', () => {
                    applySidebarScroll();
                    setTimeout(applySidebarScroll, 20);
                    setTimeout(() => {
                        applySidebarScroll();
                        unlockSidebar();
                        observer.disconnect();
                    }, 100);
                });

                // Fail-safe: En geç 150ms sonra kilidi mutlaka kaldır
                setTimeout(unlockSidebar, 150);
            }
        }
    })();
</script>

<style>
/* Sidebar Scroll Flicker & Animation Reset */
.vertical-menu,
.sidebar-menu-scroll,
.vertical-menu .simplebar-content-wrapper {
    scroll-behavior: auto !important;
}

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
    padding-top: 74px !important;
    padding-bottom: 16px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

body:has(#quick-favorites-bar) .main-content .page-content {
    padding-top: 116px !important;
}

body:not(:has(#quick-favorites-bar)) .main-content .page-content {
    padding-top: 74px !important;
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
    margin-top: 0 !important;
    margin-bottom: 16px !important;
}
</style>

<?php

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Helper\Helper;


?>

<?php $isBordroListPage = ($_GET['p'] ?? '') === 'bordro/list'; ?>

<!-- Google Fonts: Modern UI Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Geist:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Lexend:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&family=Nunito:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet"<?= $isBordroListPage ? ' media="print" onload="this.media=\'all\'"' : '' ?>>
<?php if ($isBordroListPage): ?>
<noscript>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Geist:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Lexend:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&family=Nunito:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</noscript>
<?php endif; ?>

<!-- preloader css -->
<link href="<?php echo Helper::base_url('assets/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="<?php echo Helper::base_url("assets/css/preloader.min.css"); ?>" type="text/css" />
<?php if ($isBordroListPage): ?>
<script>
    // Bordroda Pace yalnız ilk sayfa/tablo hazırlığını gösterir; AJAX istekleri
    // çubuğu ikinci kez başlatmaz. Tablo hazır olduğunda bordro.js durdurur.
    window.paceOptions = {
        ajax: false,
        elements: false,
        eventLag: false,
        restartOnPushState: false,
        restartOnRequestAfter: false
    };
</script>
<script src="assets/libs/pace-js/pace.min.js"></script>
<?php endif; ?>

<!-- Bootstrap Css -->
<link href="<?php echo Helper::base_url('assets/css/bootstrap.min.css'); ?>" id="bootstrap-style" rel="stylesheet"
    type="text/css" />
<!-- Icons Css -->
<!-- App Css-->
<link href="<?php echo Helper::base_url('assets/css/app.min.css'); ?>" id="app-style" rel="stylesheet"
    type="text/css" />

<link href="<?php echo Helper::base_url('assets/libs/select2/css/select2.min.css'); ?>" rel="stylesheet" />
<link href="<?php echo Helper::base_url('assets/css/style.css?v=' . filemtime("assets/css/style.css")); ?>"
    id="custom-style" rel="stylesheet" type="text/css" />
<!-- jQuery -->
<script src="<?php echo Helper::base_url('assets/libs/jquery/jquery.3.7.1.min.js'); ?>"></script>
<script src="<?php echo Helper::base_url('assets/libs/select2/js/select2.min.js'); ?>"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="<?php echo Helper::base_url('assets/libs/flatpickr/flatpickr.min.css'); ?>">

<link href="assets/libs//summernote/summernote-lite.min.css" rel="stylesheet">
<?php if (!$isBordroListPage): ?>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
    integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
    crossorigin="anonymous"></script>
<?php endif; ?>
<link rel="stylesheet" type="text/css" href="<?php echo Helper::base_url('assets/libs/toastify/toastify.min.css'); ?>">
<!-- Feather Icons (Immediate load for early render) -->
<script src="<?php echo Helper::base_url('assets/libs/feather-icons/feather.min.js'); ?>"></script>
