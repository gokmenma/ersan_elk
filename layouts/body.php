<body <?php echo (isset($bodyClass) ? 'class="' . $bodyClass . '"' : ''); ?>>
    <script>
        (function () {
            const bodyAttrs = [
                'data-layout',
                'data-layout-size',
                'data-layout-scrollable',
                'data-topbar',
                'data-sidebar-size',
                'data-sidebar'
            ];
            bodyAttrs.forEach(name => {
                const value = localStorage.getItem(name);
                if (value) {
                    document.body.setAttribute(name, value);
                }
            });
        })();
    </script>