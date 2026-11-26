(function (window, document) {
    'use strict';

    var COLS = 12;
    var CELL = 40;
    var MARGIN = 6;
    var MIN_W = 2;
    var MIN_H = 2;
    var COLLAPSED_H = 2;
    var COL_CLASS = /^col(-[a-z]+)?-\d+$/;
    var SKIP_IDS = { 'widget-row-break': true };

    var DashGrid = {
        active: false,
        grid: null,
        container: null,
        known: {},
        saved: {},
        onSave: null
    };

    var silent = 0;
    var interacting = false;
    var saveTimer = null;
    var refitTimer = null;
    var observer = null;
    var mini = null;
    var MINI_CELL = 8;

    function toInt(value, fallback) {
        var n = parseInt(value, 10);
        return isFinite(n) ? n : fallback;
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function isManaged(el) {
        return !!el && el.nodeType === 1 && el.classList.contains('widget-item') && !SKIP_IDS[el.id];
    }

    function isHiddenEl(el) {
        return el.classList.contains('widget-hidden') || el.classList.contains('d-none') || el.style.display === 'none';
    }

    function stripColClasses(el) {
        var own = [];
        Array.prototype.slice.call(el.classList).forEach(function (cls) {
            if (COL_CLASS.test(cls)) {
                own.push(cls);
                el.classList.remove(cls);
            }
        });
        if (own.length && !el.hasAttribute('data-default-width')) {
            el.setAttribute('data-default-width', own.join(' '));
        }
    }

    function widthFromClasses(el) {
        var source = (el.getAttribute('data-default-width') || '') + ' ' + Array.prototype.join.call(el.classList, ' ');
        var tokens = source.split(/\s+/).filter(function (cls) { return COL_CLASS.test(cls); });
        var order = ['xxl', 'xl', 'lg', 'md', 'sm', ''];
        for (var i = 0; i < order.length; i++) {
            for (var j = 0; j < tokens.length; j++) {
                var m = tokens[j].match(/^col(?:-([a-z]+))?-(\d+)$/);
                if (m && (m[1] || '') === order[i]) return clamp(toInt(m[2], COLS), MIN_W, COLS);
            }
        }
        return COLS;
    }

    function cardOf(el) {
        return el.querySelector(':scope > .card, :scope > .carousel, :scope > section, :scope > div');
    }

    function layoutFromSaved(id, el) {
        var s = DashGrid.saved[id] || {};
        var gs = s.gs && typeof s.gs === 'object' ? s.gs : null;
        if (gs && toInt(gs.w, 0) >= 1) {
            return {
                x: toInt(gs.x, 0),
                y: toInt(gs.y, 0),
                w: clamp(toInt(gs.w, COLS), MIN_W, COLS),
                h: Math.max(MIN_H, toInt(gs.h, 6)),
                auto: gs.a === 1 || gs.a === true,
                placed: true
            };
        }
        var legacyCols = toInt(s.gridCols, 0);
        var legacyHeight = toInt(s.gridHeight, 0);
        return {
            w: legacyCols >= MIN_W && legacyCols <= COLS ? legacyCols : widthFromClasses(el),
            h: legacyHeight > 0 ? Math.max(MIN_H, Math.round(legacyHeight / CELL)) : 6,
            auto: !(legacyHeight > 0),
            placed: false
        };
    }

    function snapshotNode(el) {
        var node = el.gridstackNode;
        var id = el.id;
        if (node) {
            return {
                x: toInt(node.x, 0),
                y: toInt(node.y, 0),
                w: toInt(node.w, COLS),
                h: toInt(node.h, 6),
                a: el.getAttribute('data-gs-auto') === '1' ? 1 : 0
            };
        }
        var known = DashGrid.known[id];
        if (known) {
            return { x: known.x, y: known.y, w: known.w, h: known.h, a: known.auto ? 1 : 0 };
        }
        return null;
    }

    function remember(el) {
        var snap = snapshotNode(el);
        if (snap && el.id) {
            DashGrid.known[el.id] = { x: snap.x, y: snap.y, w: snap.w, h: snap.h, auto: snap.a === 1, placed: true };
        }
    }

    function syncAttributes(el) {
        var node = el.gridstackNode;
        if (!node) return;
        el.setAttribute('data-grid-cols', node.w);
        if (el.getAttribute('data-gs-auto') === '1') {
            el.removeAttribute('data-grid-height');
        } else {
            el.setAttribute('data-grid-height', node.h * CELL);
        }
    }

    function prepare(el, layout) {
        stripColClasses(el);
        el.style.removeProperty('width');
        el.style.removeProperty('height');
        el.style.removeProperty('position');
        el.style.removeProperty('left');
        el.style.removeProperty('top');
        el.classList.add('grid-stack-item');
        el.setAttribute('gs-id', el.id);
        el.setAttribute('gs-w', layout.w);
        el.setAttribute('gs-h', layout.h);
        el.setAttribute('gs-min-w', MIN_W);
        el.setAttribute('gs-min-h', MIN_H);
        el.setAttribute('data-gs-auto', layout.auto ? '1' : '0');
        if (layout.placed) {
            el.setAttribute('gs-x', layout.x);
            el.setAttribute('gs-y', layout.y);
            el.removeAttribute('gs-auto-position');
        } else {
            el.removeAttribute('gs-x');
            el.removeAttribute('gs-y');
            el.setAttribute('gs-auto-position', 'true');
        }
    }

    function resolveLayout(el) {
        return DashGrid.known[el.id] || layoutFromSaved(el.id, el);
    }

    function measureNaturalHeight(el) {
        var card = cardOf(el);
        if (!card) return 0;
        el.classList.add('dash-measuring');
        var height = card.getBoundingClientRect().height;
        el.classList.remove('dash-measuring');
        return height;
    }

    function markEmpty(el, empty) {
        var node = el.gridstackNode;
        if (!node) return;
        var was = el.classList.contains('dash-gs-empty');
        if (empty && !was) {
            el.classList.add('dash-gs-empty');
            el.setAttribute('data-restore-empty-h', node.h);
            DashGrid.grid.update(el, { h: 1, minH: 1 });
        } else if (!empty && was) {
            el.classList.remove('dash-gs-empty');
            DashGrid.grid.update(el, { h: Math.max(MIN_H, toInt(el.getAttribute('data-restore-empty-h'), 6)), minH: MIN_H });
        }
    }

    function fitItem(el) {
        if (!DashGrid.grid || !el.gridstackNode) return;
        if (el.classList.contains('lazy-widget')) return;
        var hiddenByCss = window.getComputedStyle(el).display === 'none';
        if (hiddenByCss) {
            markEmpty(el, true);
            return;
        }
        if (el.offsetParent === null) return;
        var card = cardOf(el);
        var natural = card ? measureNaturalHeight(el) : 0;
        if (!card || natural < 20) {
            markEmpty(el, true);
            return;
        }
        markEmpty(el, false);
        if (el.getAttribute('data-gs-auto') !== '1') return;
        if (el.classList.contains('widget-collapsed') || el.classList.contains('widget-maximized')) return;
        var rows = Math.max(MIN_H, Math.ceil((natural + MARGIN * 2) / CELL));
        if (rows !== el.gridstackNode.h) {
            DashGrid.grid.update(el, { h: rows });
        }
    }

    function refit() {
        if (!DashGrid.active || interacting) return;
        silent++;
        try {
            DashGrid.grid.batchUpdate();
            DashGrid.container.querySelectorAll(':scope > .grid-stack-item').forEach(fitItem);
            DashGrid.grid.batchUpdate(false);
            DashGrid.grid.compact();
        } catch (e) {
            console.warn('Dashboard grid refit hatası', e);
        } finally {
            silent--;
        }
        DashGrid.container.querySelectorAll(':scope > .grid-stack-item').forEach(syncAttributes);
    }

    function scheduleRefit(delay) {
        clearTimeout(refitTimer);
        refitTimer = setTimeout(refit, typeof delay === 'number' ? delay : 250);
    }

    function scheduleSave() {
        if (silent) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            DashGrid.container.querySelectorAll(':scope > .grid-stack-item').forEach(function (el) {
                remember(el);
                syncAttributes(el);
            });
            if (typeof DashGrid.onSave === 'function') DashGrid.onSave();
        }, 400);
    }

    function adopt(el) {
        if (!DashGrid.active || !isManaged(el) || el.gridstackNode) return;
        if (isHiddenEl(el) && !el.getAttribute('data-gs-reveal')) {
            stripColClasses(el);
            return;
        }
        var layout = resolveLayout(el);
        prepare(el, layout);
        silent++;
        try {
            var opts = { w: layout.w, h: layout.h, minW: MIN_W, minH: MIN_H, id: el.id };
            if (layout.placed) {
                opts.x = layout.x;
                opts.y = layout.y;
            } else {
                opts.autoPosition = true;
            }
            DashGrid.grid.makeWidget(el, opts);
        } catch (e) {
            console.warn('Widget gride eklenemedi', el.id, e);
        } finally {
            silent--;
        }
        syncAttributes(el);
        scheduleRefit(200);
    }

    function release(el) {
        if (!el || !el.gridstackNode) return;
        remember(el);
        silent++;
        try {
            DashGrid.grid.removeWidget(el, false, false);
        } catch (e) {
            console.warn('Widget gridden çıkarılamadı', el.id, e);
        } finally {
            silent--;
        }
    }

    function onMutations(mutations) {
        var removed = [];
        var added = [];
        mutations.forEach(function (m) {
            Array.prototype.forEach.call(m.removedNodes, function (n) {
                if (isManaged(n) && n.gridstackNode && !n.parentNode && n.gridstackNode.el === n) removed.push(n);
            });
            Array.prototype.forEach.call(m.addedNodes, function (n) {
                if (isManaged(n)) added.push(n);
            });
        });
        if (!removed.length && !added.length) return;
        removed.forEach(release);
        added.forEach(adopt);
    }

    function updateInteractionMode() {
        if (!DashGrid.active) return;
        var narrow = window.innerWidth < 768;
        DashGrid.grid.enableMove(!narrow);
        DashGrid.grid.enableResize(!narrow);
        DashGrid.container.classList.toggle('dash-gs-locked', narrow);
    }

    DashGrid.init = function (options) {
        options = options || {};
        var container = document.getElementById(options.containerId || 'dashboard-widgets');
        if (!container || typeof window.GridStack === 'undefined') return false;
        if (DashGrid.active) return true;

        DashGrid.container = container;
        DashGrid.saved = options.saved && typeof options.saved === 'object' ? options.saved : {};
        DashGrid.onSave = options.onSave || null;

        container.classList.remove('row');
        container.classList.add('dash-gs', 'grid-stack');
        container.style.minHeight = '';

        Array.prototype.forEach.call(container.children, function (el) {
            if (!isManaged(el)) return;
            if (isHiddenEl(el)) {
                stripColClasses(el);
                return;
            }
            prepare(el, resolveLayout(el));
        });

        var grid = window.GridStack.init({
            column: COLS,
            cellHeight: CELL + 'px',
            minRow: 1,
            margin: 0,
            handle: '.dash-gs-whole-item',
            draggable: { cancel: 'a, label, .mac-control, .dropdown-menu, .nav-link, .page-link, .finder-tabs-shell, .form-check, .carousel-control-prev, .carousel-control-next, .carousel-indicators, .table-responsive, [data-bs-toggle], [role="button"], [onclick]' },
            resizable: { handles: 'e, s, se' },
            animate: false,
            disableOneColumnMode: false,
            float: false
        }, container);

        DashGrid.grid = grid;
        DashGrid.active = true;

        grid.on('dragstart resizestart', function () { interacting = true; });
        grid.on('dragstop', function () {
            interacting = false;
            scheduleSave();
        });
        grid.on('resizestop', function (event, el) {
            interacting = false;
            if (el) {
                el.setAttribute('data-gs-auto', '0');
                el.classList.remove('widget-collapsed');
            }
            scheduleSave();
            setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 120);
        });

        observer = new MutationObserver(onMutations);
        observer.observe(container, { childList: true });

        var contentObserver = new MutationObserver(function () {
            if (!interacting) scheduleRefit(450);
        });
        contentObserver.observe(container, { childList: true, subtree: true, characterData: true });

        window.addEventListener('resize', function () {
            updateInteractionMode();
            scheduleRefit(300);
        });
        window.addEventListener('load', function () {
            [100, 900, 2500, 5000].forEach(function (delay) { setTimeout(refit, delay); });
        });

        updateInteractionMode();
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                refit();
                grid.setAnimation(true);
            });
        });
        return true;
    };

    DashGrid.refit = function (delay) {
        scheduleRefit(delay === undefined ? 0 : delay);
    };

    DashGrid.snapshot = function (el) {
        return snapshotNode(el);
    };

    DashGrid.sortIds = function (ids) {
        var pos = {};
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            var snap = el ? snapshotNode(el) : null;
            pos[id] = snap ? snap.y * 100 + snap.x : Number.MAX_SAFE_INTEGER;
        });
        return ids.slice().sort(function (a, b) { return pos[a] - pos[b]; });
    };

    function miniTitle(el) {
        var t = el.querySelector('.mac-title-text');
        return (t && t.textContent.trim()) || el.id;
    }

    function applyMini() {
        if (!mini || !DashGrid.active) return;
        silent++;
        try {
            DashGrid.grid.batchUpdate();
            mini.engine.nodes.forEach(function (n) {
                var el = document.getElementById(n.id);
                if (el && el.gridstackNode) DashGrid.grid.update(el, { x: n.x, y: n.y });
            });
            DashGrid.grid.batchUpdate(false);
        } finally {
            silent--;
        }
        scheduleSave();
    }

    DashGrid.renderMini = function (host) {
        if (!DashGrid.active || !host) return;
        DashGrid.miniHost = host;
        if (mini) {
            try { mini.destroy(false); } catch (e) {}
            mini = null;
        }
        host.innerHTML = '';
        [['#widget-ana-slider', 'Haberler ve Duyurular'], ['.dash-hero', 'Karşılama ve Takvim']].forEach(function (def) {
            var src = document.querySelector(def[0]);
            if (!src || src.offsetParent === null) return;
            var fixed = document.createElement('div');
            fixed.className = 'dash-mini-fixed';
            fixed.style.height = Math.max(16, Math.round(src.getBoundingClientRect().height * (MINI_CELL / CELL))) + 'px';
            var label = document.createElement('span');
            label.textContent = def[1];
            var lock = document.createElement('i');
            lock.className = 'bx bx-lock-alt';
            fixed.appendChild(lock);
            fixed.appendChild(label);
            host.appendChild(fixed);
        });
        var holder = document.createElement('div');
        holder.className = 'grid-stack dash-mini-grid';
        host.appendChild(holder);
        DashGrid.container.querySelectorAll(':scope > .grid-stack-item').forEach(function (el) {
            var n = el.gridstackNode;
            if (!n) return;
            var d = document.createElement('div');
            d.className = 'grid-stack-item';
            d.setAttribute('gs-id', el.id);
            d.setAttribute('gs-x', n.x);
            d.setAttribute('gs-y', n.y);
            d.setAttribute('gs-w', n.w);
            d.setAttribute('gs-h', n.h);
            var c = document.createElement('div');
            c.className = 'grid-stack-item-content';
            var label = document.createElement('span');
            label.textContent = miniTitle(el);
            c.appendChild(label);
            d.appendChild(c);
            holder.appendChild(d);
        });
        mini = window.GridStack.init({
            column: COLS,
            cellHeight: MINI_CELL + 'px',
            margin: 2,
            disableResize: true,
            float: false,
            animate: true
        }, holder);
        mini.on('dragstop', applyMini);
    };

    DashGrid.setVisible = function (el, visible) {
        if (!DashGrid.active || !isManaged(el)) return;
        if (visible) {
            if (!el.gridstackNode) {
                el.setAttribute('data-gs-reveal', '1');
                adopt(el);
                el.removeAttribute('data-gs-reveal');
            }
        } else {
            release(el);
        }
        scheduleSave();
        if (DashGrid.miniHost && DashGrid.miniHost.offsetParent) setTimeout(function () { DashGrid.renderMini(DashGrid.miniHost); }, 150);
    };

    DashGrid.setSize = function (el, cols, heightPx) {
        if (!DashGrid.active || !el || !el.gridstackNode) return;
        var update = {};
        var w = cols ? clamp(toInt(cols, COLS), MIN_W, COLS) : widthFromClasses(el);
        update.w = w;
        if (heightPx) {
            update.h = Math.max(MIN_H, Math.round(toInt(heightPx, 0) / CELL));
            el.setAttribute('data-gs-auto', '0');
        } else {
            el.setAttribute('data-gs-auto', '1');
        }
        DashGrid.grid.update(el, update);
        syncAttributes(el);
        if (!heightPx) scheduleRefit(60);
        scheduleSave();
    };

    DashGrid.toggleCollapse = function (el) {
        if (!DashGrid.active || !el || !el.gridstackNode) return false;
        var collapsed = el.classList.toggle('widget-collapsed');
        if (collapsed) {
            el.setAttribute('data-restore-h', el.gridstackNode.h);
            el.setAttribute('data-restore-auto', el.getAttribute('data-gs-auto') || '0');
            DashGrid.grid.update(el, { h: COLLAPSED_H, minH: COLLAPSED_H });
        } else {
            var restore = Math.max(MIN_H, toInt(el.getAttribute('data-restore-h'), 6));
            el.setAttribute('data-gs-auto', el.getAttribute('data-restore-auto') || '0');
            DashGrid.grid.update(el, { h: restore, minH: MIN_H });
            scheduleRefit(60);
        }
        return collapsed;
    };

    window.DashGrid = DashGrid;
})(window, document);
