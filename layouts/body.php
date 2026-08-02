<body class="preload <?php echo (isset($bodyClass) ? $bodyClass : ''); ?>">
    <script>
        (function () {
            const htmlAttrs = [
                'data-theme-mode',
                'data-font-family',
                'data-bs-theme',
                'data-orientation',
                'dir'
            ];
            htmlAttrs.forEach(name => {
                const value = localStorage.getItem(name);
                if (value) {
                    document.documentElement.setAttribute(name, value);
                }
            });

            // Reset any legacy boxed layout preference to fluid for full-width layout symmetry
            if (localStorage.getItem('data-layout-size') === 'boxed') {
                localStorage.setItem('data-layout-size', 'fluid');
            }

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