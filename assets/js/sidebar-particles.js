/**
 * Sidebar Constellation / Parçacık Ağı (Plexus) Arka Plan Animasyonu
 * Ultra-lightweight 60 FPS Canvas tabanlı interaktif arka plan efekti
 */
(function(window, document) {
    'use strict';

    var canvas = null;
    var ctx = null;
    var sidebar = null;
    var animationFrameId = null;
    var particles = [];
    var width = 0, height = 0, dpr = 1;
    var isRunning = false;
    var mouse = { x: -1000, y: -1000, active: false, radius: 95 };

    function getThemeColors() {
        var isLight = document.body.getAttribute('data-sidebar') === 'light' || 
                     (document.documentElement.getAttribute('data-bs-theme') !== 'dark' && 
                      document.body.getAttribute('data-sidebar') !== 'dark' &&
                      document.body.getAttribute('data-sidebar') !== 'brand' &&
                      document.body.getAttribute('data-sidebar') !== 'purple' &&
                      document.body.getAttribute('data-sidebar') !== 'slate' &&
                      document.body.getAttribute('data-sidebar') !== 'red' &&
                      document.body.getAttribute('data-sidebar') !== 'emerald' &&
                      document.body.getAttribute('data-sidebar') !== 'teal' &&
                      document.body.getAttribute('data-sidebar') !== 'cyan' &&
                      document.body.getAttribute('data-sidebar') !== 'ersan' &&
                      document.body.getAttribute('data-sidebar') !== 'rose');

        if (isLight) {
            return {
                node: 'rgba(71, 85, 105, ',
                line: 'rgba(148, 163, 184, ',
                accent: 'rgba(14, 165, 233, '
            };
        }

        return {
            node: 'rgba(255, 255, 255, ',
            line: 'rgba(180, 225, 255, ',
            accent: 'rgba(56, 189, 248, '
        };
    }

    var colors = getThemeColors();

    function resize() {
        if (!sidebar || !canvas) return;
        var rect = sidebar.getBoundingClientRect();
        width = rect.width;
        height = rect.height;
        dpr = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        if (ctx) {
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);
        }
        initParticles();
    }

    function initParticles() {
        particles = [];
        if (!width || !height) return;
        var isCollapsed = width < 120;
        var count = isCollapsed ? 12 : Math.min(Math.max(Math.floor((width * height) / 14000), 20), 38);

        for (var i = 0; i < count; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.4,
                vy: (Math.random() - 0.5) * 0.4,
                radius: Math.random() * 1.5 + 1.2,
                baseAlpha: Math.random() * 0.4 + 0.25,
                alpha: 0.3,
                pulseAngle: Math.random() * Math.PI * 2,
                pulseSpeed: Math.random() * 0.02 + 0.01,
                isSpecial: Math.random() > 0.75
            });
        }
    }

    function render() {
        if (!isRunning || !ctx) return;
        ctx.clearRect(0, 0, width, height);

        var maxDistance = width < 120 ? 60 : 85;
        var maxDistSq = maxDistance * maxDistance;

        // Bağlantı Çizgileri
        for (var i = 0; i < particles.length; i++) {
            var p1 = particles[i];
            for (var j = i + 1; j < particles.length; j++) {
                var p2 = particles[j];
                var dx = p1.x - p2.x, dy = p1.y - p2.y;
                var distSq = dx * dx + dy * dy;

                if (distSq < maxDistSq) {
                    var dist = Math.sqrt(distSq);
                    var lineAlpha = (1 - dist / maxDistance) * 0.22;
                    ctx.beginPath();
                    ctx.strokeStyle = colors.line + lineAlpha + ')';
                    ctx.lineWidth = 0.8;
                    ctx.moveTo(p1.x, p1.y);
                    ctx.lineTo(p2.x, p2.y);
                    ctx.stroke();
                }
            }

            // Fare ile Bağlantı
            if (mouse.active) {
                var mdx = p1.x - mouse.x, mdy = p1.y - mouse.y;
                var mDistSq = mdx * mdx + mdy * mdy;
                if (mDistSq < mouse.radius * mouse.radius) {
                    var mDist = Math.sqrt(mDistSq);
                    var mAlpha = (1 - mDist / mouse.radius) * 0.4;
                    ctx.beginPath();
                    ctx.strokeStyle = colors.accent + mAlpha + ')';
                    ctx.lineWidth = 1;
                    ctx.moveTo(p1.x, p1.y);
                    ctx.lineTo(mouse.x, mouse.y);
                    ctx.stroke();
                    p1.x += (mdx / mDist) * 0.3;
                    p1.y += (mdy / mDist) * 0.3;
                }
            }
        }

        // Parçacık Düğümleri
        for (var k = 0; k < particles.length; k++) {
            var p = particles[k];
            p.pulseAngle += p.pulseSpeed;
            p.alpha = Math.max(0.1, p.baseAlpha + Math.sin(p.pulseAngle) * 0.2);

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            if (p.isSpecial) {
                ctx.fillStyle = colors.accent + (p.alpha * 1.2) + ')';
                ctx.shadowBlur = 6;
                ctx.shadowColor = colors.accent + '0.6)';
            } else {
                ctx.fillStyle = colors.node + p.alpha + ')';
                ctx.shadowBlur = 0;
            }
            ctx.fill();
            ctx.shadowBlur = 0;

            p.x += p.vx;
            p.y += p.vy;

            if (p.x < 0) { p.x = 0; p.vx = -p.vx; }
            if (p.x > width) { p.x = width; p.vx = -p.vx; }
            if (p.y < 0) { p.y = 0; p.vy = -p.vy; }
            if (p.y > height) { p.y = height; p.vy = -p.vy; }
        }

        animationFrameId = requestAnimationFrame(render);
    }

    function init() {
        sidebar = document.getElementById('navbar') || document.querySelector('.vertical-menu') || document.querySelector('.sidebar');
        if (!sidebar) return;

        canvas = document.getElementById('sidebar-particles-canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = 'sidebar-particles-canvas';
            canvas.className = 'sidebar-particles-canvas';
            sidebar.insertBefore(canvas, sidebar.firstChild);
        }

        ctx = canvas.getContext('2d', { alpha: true });
        colors = getThemeColors();

        sidebar.addEventListener('mousemove', function(e) {
            var rect = sidebar.getBoundingClientRect();
            mouse.x = e.clientX - rect.left;
            mouse.y = e.clientY - rect.top;
            mouse.active = true;
        }, { passive: true });

        sidebar.addEventListener('mouseleave', function() {
            mouse.active = false;
            mouse.x = -1000;
            mouse.y = -1000;
        }, { passive: true });

        if (window.ResizeObserver) {
            new ResizeObserver(function() {
                colors = getThemeColors();
                resize();
            }).observe(sidebar);
        } else {
            window.addEventListener('resize', function() {
                colors = getThemeColors();
                resize();
            });
        }

        var observer = new MutationObserver(function() {
            colors = getThemeColors();
        });
        if (document.documentElement) {
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        }
        if (document.body) {
            observer.observe(document.body, { attributes: true, attributeFilter: ['data-sidebar', 'data-sidebar-size'] });
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (animationFrameId) {
                    cancelAnimationFrame(animationFrameId);
                    animationFrameId = null;
                }
            } else if (isRunning && !animationFrameId) {
                animationFrameId = requestAnimationFrame(render);
            }
        });

        isRunning = true;
        resize();
        animationFrameId = requestAnimationFrame(render);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
