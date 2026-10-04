<body class="preload <?php echo (isset($bodyClass) ? $bodyClass : ''); ?>">
    <script>
        (function () {
            const htmlAttrs = [
                'data-theme-mode',
                'data-font-family',
                'data-bs-theme',
                'data-orientation',
                'data-theme-preset',
                'dir'
            ];
            htmlAttrs.forEach(name => {
                let value = localStorage.getItem(name);
                if (!value && name === 'data-theme-preset') value = 'ersan';
                if (!value && name === 'data-font-family') value = 'Outfit';
                if (!value && name === 'data-theme-mode') value = 'ersan';
                if (!value && name === 'data-bs-theme') value = 'light';
                if (value) {
                    document.documentElement.setAttribute(name, value);
                }
            });

            // Reset any legacy boxed layout preference to fluid for full-width layout symmetry
            if (localStorage.getItem('data-layout-size') === 'boxed') {
                localStorage.setItem('data-layout-size', 'fluid');
            }

            const customTopbar = localStorage.getItem('custom-topbar-color');
            const customSidebar = localStorage.getItem('custom-sidebar-color');
            const savedPreset = localStorage.getItem('data-theme-preset');

            const bodyAttrs = [
                'data-layout',
                'data-layout-size',
                'data-layout-scrollable',
                'data-topbar',
                'data-sidebar-size',
                'data-sidebar',
                'data-theme-mode'
            ];
            bodyAttrs.forEach(name => {
                let value = localStorage.getItem(name);
                if (!value && name === 'data-topbar' && !customTopbar && (!savedPreset || savedPreset === 'ersan' || savedPreset === 'minimalist')) value = 'light';
                if (!value && name === 'data-sidebar' && !customSidebar && (!savedPreset || savedPreset === 'ersan' || savedPreset === 'minimalist' || savedPreset === 'dark-pro')) value = 'dark';
                if (!value && name === 'data-theme-mode') value = 'ersan';
                if (name === 'data-layout-size' && value === 'boxed') {
                    value = 'fluid';
                }
                if (value) {
                    document.body.setAttribute(name, value);
                }
            });

            window.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    document.body.classList.remove('preload');
                }, 150);
            });
        })();
    </script>