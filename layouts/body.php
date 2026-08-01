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
                const value = localStorage.getItem(name);
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