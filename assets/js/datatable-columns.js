(function ($) {
  "use strict";

  if (!$ || !$.fn || !$.fn.dataTable) return;

  const STORE_PREFIX = "dtcm:v1:";
  const MIN_WIDTH = 50;
  const NEW_COLUMN_WIDTH = 140;
  const registry = new WeakMap();
  const defaultsStash = new WeakMap();

  function pageKey() {
    let p = "";
    try {
      p = new URLSearchParams(window.location.search).get("p") || "";
    } catch (e) {}
    return window.location.pathname + "?" + p;
  }

  function loadState(key) {
    try {
      const raw = window.localStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function writeState(key, state) {
    try {
      if (state) {
        window.localStorage.setItem(key, JSON.stringify(state));
      } else {
        window.localStorage.removeItem(key);
      }
    } catch (e) {}
  }

  function headerTitle(th) {
    const explicit = $(th).attr("data-colman-title");
    if (explicit) return explicit.trim();
    return $(th).clone().children().remove().end().text().replace(/\s+/g, " ").trim();
  }

  function isLockedTitle(title, th) {
    return (
      !title ||
      title === "#" ||
      $(th).is('[data-colman="fixed"], .no-colvis, .dt-control, .control')
    );
  }

  function isEligible(settings) {
    const init = settings.oInit || {};
    if (init.colReorder !== undefined) return false;
    if (init.scrollX || init.scrollY || init.sScrollX || init.sScrollY) return false;
    if (!$.fn.dataTable.ColReorder) return false;

    const $table = $(settings.nTable);
    if ($table.is('[data-column-manager="off"]')) return false;
    if (document.getElementById("columnList")) return false;
    if (settings.aoColumns.length < 3) return false;
    if ($table.find("> thead > tr").length !== 1) return false;

    let complex = false;
    $table.find("> thead th, > tfoot th, > tfoot td").each(function () {
      if ((parseInt(this.getAttribute("colspan"), 10) || 1) > 1 || (parseInt(this.getAttribute("rowspan"), 10) || 1) > 1) {
        complex = true;
      }
    });
    if (complex) return false;

    const remote = settings.ajax || (settings.oInit && (settings.oInit.ajax || settings.oInit.sAjaxSource));
    const noReorder = !!(remote && settings.aoColumns.some((c) => typeof c.mData === "number"));

    const $rows = $table.find("> tbody > tr");
    if ($rows.length > 1) {
      $rows.find("td[colspan], td[rowspan]").each(function () {
        if ((parseInt(this.getAttribute("colspan"), 10) || 1) > 1 || (parseInt(this.getAttribute("rowspan"), 10) || 1) > 1) {
          complex = true;
        }
      });
    }
    return complex ? false : noReorder ? "resize" : "full";
  }

  function indexedGetter(i) {
    return function (row, type, set) {
      if (type === "set") {
        row[i] = set;
        return undefined;
      }
      return row == null ? null : row[i];
    };
  }

  function convertNumericSources(settings) {
    const internal = $.fn.dataTable.ext.internal;
    settings.aoColumns.forEach(function (col, i) {
      if (typeof col.mData !== "number") return;
      const source = indexedGetter(col.mData);
      const getter = internal._fnGetObjectDataFn(source);
      const setter = internal._fnSetObjectDataFn(source);
      const render = col.mRender != null && col.mRender !== "" ? internal._fnGetObjectDataFn(col.mRender) : null;
      col.mData = source;
      col.fnGetData = function (row, type, meta) {
        const inner = getter(row, type, undefined, meta);
        return render && type ? render(inner, type, row, meta) : inner;
      };
      col.fnSetData = function (row, val, meta) {
        return setter(row, val, meta);
      };
    });
  }

  function createContext(settings) {
    const columns = settings.aoColumns;
    const titles = columns.map((c) => headerTitle(c.nTh));
    const locked = columns.map((c, i) => isLockedTitle(titles[i], c.nTh));

    let left = 0;
    while (left < locked.length && locked[left]) left++;
    let right = 0;
    while (right < locked.length - left && locked[locked.length - 1 - right]) right++;

    const tableId = settings.sTableId || "dt" + settings.nTable.id;
    const stashed = defaultsStash.get(settings);
    const ctx = {
      settings: settings,
      tableId: tableId,
      key: STORE_PREFIX + pageKey() + "#" + tableId,
      sig: columns.length + ":" + titles.join("|"),
      titles: titles,
      locked: locked,
      fixedLeft: left,
      fixedRight: right,
      defaultVisible: stashed || columns.map((c) => c.bVisible !== false),
      widths: {},
      autoWidth: settings.oFeatures.bAutoWidth,
      silent: false,
      api: null,
      sortable: null,
    };

    const saved = loadState(ctx.key);
    ctx.saved = saved && saved.sig === ctx.sig ? saved : null;
    if (ctx.saved && ctx.saved.widths) {
      ctx.widths = $.extend({}, ctx.saved.widths);
    }
    return ctx;
  }

  function origOf(ctx, cur) {
    const col = ctx.settings.aoColumns[cur];
    return col && col._ColReorder_iOrigCol !== undefined ? col._ColReorder_iOrigCol : cur;
  }

  function curOf(ctx, orig) {
    const cols = ctx.settings.aoColumns;
    for (let i = 0; i < cols.length; i++) {
      if (origOf(ctx, i) === orig) return i;
    }
    return orig;
  }

  function currentOrder(ctx) {
    return ctx.settings.aoColumns.map((c, i) => origOf(ctx, i));
  }

  function hasFixedLayout(ctx) {
    return Object.keys(ctx.widths).length > 0;
  }

  function widthOfOrig(ctx, orig) {
    const w = ctx.widths[orig];
    return typeof w === "number" && w >= MIN_WIDTH ? w : null;
  }

  let measureCanvas = null;

  function minWidthOf(th) {
    const title = headerTitle(th);
    if (!title) return 0;
    if (!measureCanvas) measureCanvas = document.createElement("canvas");
    const g = measureCanvas.getContext("2d");
    const cs = window.getComputedStyle(th);
    g.font = cs.fontStyle + " " + cs.fontWeight + " " + cs.fontSize + " " + cs.fontFamily;
    const spacing = parseFloat(cs.letterSpacing) || 0;
    const text = cs.textTransform === "uppercase" ? title.toLocaleUpperCase("tr-TR") : title;
    let longest = 0;
    text.split(/\s+/).forEach(function (word) {
      const w = g.measureText(word).width + spacing * word.length;
      if (w > longest) longest = w;
    });
    return Math.ceil(longest + (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0) + 4);
  }

  function minOfCol(ctx, cur) {
    const th = ctx.settings.aoColumns[cur].nTh;
    return Math.max(MIN_WIDTH, th ? minWidthOf(th) : 0);
  }

  function applyMinWidths(ctx) {
    ctx.settings.aoColumns.forEach(function (col, cur) {
      const th = col.nTh;
      if (!th || col.bVisible === false) return;
      if (widthOfOrig(ctx, origOf(ctx, cur))) return;
      const m = minWidthOf(th);
      if (m > 0) th.style.setProperty("min-width", m + "px", "important");
    });
  }

  function paintWidth(th, w) {
    th.style.setProperty("width", w + "px", "important");
    th.style.setProperty("min-width", w + "px", "important");
    th.style.setProperty("max-width", w + "px", "important");
  }

  function syncTableWidth(ctx) {
    const table = ctx.settings.nTable;
    ctx.settings.oFeatures.bAutoWidth = hasFixedLayout(ctx) ? false : ctx.autoWidth;
    if (!hasFixedLayout(ctx)) {
      $(table).removeClass("dt-colman-fixed");
      table.style.removeProperty("width");
      return;
    }
    let total = 0;
    ctx.settings.aoColumns.forEach(function (col, cur) {
      if (col.bVisible === false) return;
      const w = Math.max(widthOfOrig(ctx, origOf(ctx, cur)) || NEW_COLUMN_WIDTH, minOfCol(ctx, cur));
      total += w;
    });
    $(table).addClass("dt-colman-fixed");
    table.style.setProperty("width", total + "px", "important");
  }

  function paintAllWidths(ctx) {
    ctx.settings.aoColumns.forEach(function (col, cur) {
      const w = widthOfOrig(ctx, origOf(ctx, cur));
      if (w && col.nTh) {
        const fit = Math.max(w, minOfCol(ctx, cur));
        ctx.widths[origOf(ctx, cur)] = fit;
        paintWidth(col.nTh, fit);
      }
    });
    syncTableWidth(ctx);
  }

  function clearWidths(ctx) {
    ctx.widths = {};
    ctx.settings.aoColumns.forEach(function (col) {
      if (!col.nTh) return;
      col.nTh.style.removeProperty("width");
      col.nTh.style.removeProperty("min-width");
      col.nTh.style.removeProperty("max-width");
    });
    syncTableWidth(ctx);
  }

  function freezeWidths(ctx) {
    ctx.settings.aoColumns.forEach(function (col, cur) {
      if (col.bVisible === false || !col.nTh) return;
      const orig = origOf(ctx, cur);
      if (widthOfOrig(ctx, orig)) return;
      ctx.widths[orig] = Math.max(minOfCol(ctx, cur), Math.round(col.nTh.getBoundingClientRect().width));
    });
    paintAllWidths(ctx);
  }

  function persist(ctx) {
    if (ctx.silent) return;
    const order = currentOrder(ctx);
    const identity = order.every((v, i) => v === i);
    const visible = {};
    let visibleChanged = false;
    ctx.settings.aoColumns.forEach(function (col, cur) {
      const orig = origOf(ctx, cur);
      const isVisible = col.bVisible !== false;
      if (isVisible !== ctx.defaultVisible[orig]) {
        visible[orig] = isVisible;
        visibleChanged = true;
      }
    });
    const hasWidths = hasFixedLayout(ctx);

    if (identity && !visibleChanged && !hasWidths) {
      writeState(ctx.key, null);
      return;
    }
    writeState(ctx.key, {
      sig: ctx.sig,
      order: identity ? null : order,
      visible: visible,
      widths: ctx.widths,
    });
  }

  function toOriginalLayout(ctx, data) {
    if (!data || !Array.isArray(data.columns)) return;
    if (data.columns.length !== ctx.settings.aoColumns.length) return;
    const columns = new Array(data.columns.length);
    data.columns.forEach(function (col, cur) {
      columns[origOf(ctx, cur)] = col;
    });
    data.columns = columns;
    if (Array.isArray(data.order)) {
      data.order.forEach(function (o) {
        o.column = origOf(ctx, o.column);
      });
    }
  }

  function ensureScrollWrapper(settings) {
    const $table = $(settings.nTable);
    if ($table.closest(".table-responsive").length) return;
    $table.wrap('<div class="table-responsive dt-colman-scroll"></div>');
  }

  $(document).on("stateLoadParams.dt", function (e, settings, data) {
    if (e.namespace !== "dt" || !data || !isEligible(settings)) return;
    if (!defaultsStash.has(settings)) {
      defaultsStash.set(settings, settings.aoColumns.map((c) => c.bVisible !== false));
    }
    const defaults = defaultsStash.get(settings);
    if (Array.isArray(data.columns)) {
      data.columns.forEach(function (col, i) {
        if (defaults[i] !== undefined) col.visible = defaults[i];
      });
    }
    delete data.ColReorder;
  });

  $(document).on("preInit.dt", function (e, settings) {
    if (e.namespace !== "dt") return;
    if (!isEligible(settings)) return;

    const ctx = createContext(settings);
    const mode = isEligible(settings);
    ctx.reorder = mode === "full";
    if (mode === "resize") {
      convertNumericSources(settings);
      ctx.reorder = true;
      ctx.converted = true;
    }
    registry.set(settings, ctx);

    settings.oInit = $.extend({}, settings.oInit, { responsive: false });
    if (ctx.reorder) {
      settings.oInit.colReorder = {
        fixedColumnsLeft: ctx.fixedLeft,
        fixedColumnsRight: ctx.fixedRight,
        realtime: false,
      };
    }

    if (ctx.reorder && settings.oFeatures.bServerSide) {
      $.fn.dataTable.ext.internal._fnCallbackReg(
        settings,
        "aoServerParams",
        function (data) {
          toOriginalLayout(ctx, data);
        },
        "dtcm"
      );
    }

    ensureScrollWrapper(settings);
    if (hasFixedLayout(ctx)) paintAllWidths(ctx);
  });

  function startResize(ctx, th, ev) {
    const point = ev.type === "touchstart" ? ev.originalEvent.touches[0] : ev;
    const cur = ctx.settings.aoColumns.findIndex((c) => c.nTh === th);
    if (cur < 0) return;

    freezeWidths(ctx);
    const orig = origOf(ctx, cur);
    const startX = point.pageX;
    const startW = widthOfOrig(ctx, orig) || th.getBoundingClientRect().width;
    let moved = false;

    $("body").addClass("dt-colman-resizing");

    function onMove(mv) {
      const p = mv.type === "touchmove" ? mv.originalEvent.touches[0] : mv;
      const w = Math.max(minOfCol(ctx, cur), Math.round(startW + (p.pageX - startX)));
      if (Math.abs(p.pageX - startX) > 2) moved = true;
      ctx.widths[orig] = w;
      paintWidth(th, w);
      syncTableWidth(ctx);
    }

    function onUp() {
      $(document).off(".dtcmDrag");
      $("body").removeClass("dt-colman-resizing");
      if (moved) {
        const swallow = function (c) {
          c.stopPropagation();
          c.preventDefault();
          document.removeEventListener("click", swallow, true);
        };
        document.addEventListener("click", swallow, true);
        setTimeout(function () {
          document.removeEventListener("click", swallow, true);
        }, 0);
      }
      persist(ctx);
    }

    $(document)
      .on("mousemove.dtcmDrag touchmove.dtcmDrag", onMove)
      .on("mouseup.dtcmDrag touchend.dtcmDrag", onUp);
  }

  function resetColumnWidth(ctx, th) {
    const cur = ctx.settings.aoColumns.findIndex((c) => c.nTh === th);
    if (cur < 0) return;
    const orig = origOf(ctx, cur);
    delete ctx.widths[orig];
    th.style.removeProperty("width");
    th.style.removeProperty("min-width");
    th.style.removeProperty("max-width");
    if (hasFixedLayout(ctx)) {
      ctx.widths[orig] = NEW_COLUMN_WIDTH;
      paintWidth(th, NEW_COLUMN_WIDTH);
    }
    syncTableWidth(ctx);
    persist(ctx);
  }

  function createResizer(ctx) {
    return $('<span class="dt-colman-resizer" role="separator" aria-orientation="vertical" title="Genişliği ayarlamak için sürükleyin"></span>')
      .on("mousedown touchstart", function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        startResize(ctx, this.parentNode, ev);
      })
      .on("click", function (ev) {
        ev.stopPropagation();
      })
      .on("dblclick", function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        resetColumnWidth(ctx, this.parentNode);
      });
  }

  function ensureResizers(ctx) {
    ctx.settings.aoColumns.forEach(function (col) {
      const th = col.nTh;
      if (!th || col.bVisible === false) return;
      const $th = $(th).addClass("dt-colman-th");
      if (!$th.children(".dt-colman-resizer").length) {
        $th.append(createResizer(ctx));
      }
    });
    if (!hasFixedLayout(ctx)) applyMinWidths(ctx);
  }

  function renderList(ctx) {
    const $list = ctx.$list;
    if (!$list) return;
    $list.empty();

    ctx.settings.aoColumns.forEach(function (col, cur) {
      const orig = origOf(ctx, cur);
      if (ctx.locked[orig]) return;
      const checked = col.bVisible !== false;
      const $item = $(
        '<div class="dropdown-item py-1 d-flex align-items-center dt-colman-item" data-orig="' + orig + '">' +
          (ctx.reorder ? '<div class="dt-colman-handle" title="Sıralamak için sürükleyin"><i class="bx bx-menu font-size-16"></i></div>' : "") +
          '<div class="form-check flex-grow-1 mb-0">' +
          '<input class="form-check-input dt-colman-check" type="checkbox" id="dtcm-' + ctx.tableId + "-" + orig + '" data-orig="' + orig + '">' +
          '<label class="form-check-label w-100 fw-medium font-size-13 mb-0" for="dtcm-' + ctx.tableId + "-" + orig + '"></label>' +
          "</div></div>"
      );
      $item.find("label").text(ctx.titles[orig]);
      $item.find("input").prop("checked", checked);
      $list.append($item);
    });

    if (!ctx.reorder || typeof Sortable === "undefined") return;
    if (ctx.sortable) ctx.sortable.destroy();
    ctx.sortable = new Sortable($list[0], {
      animation: 150,
      handle: ".dt-colman-handle",
      draggable: ".dt-colman-item",
      forceFallback: true,
      fallbackOnBody: true,
      ghostClass: "bg-primary-subtle",
      onStart: function () {
        $list.closest(".dropdown-menu").addClass("dt-colman-sorting");
      },
      onEnd: function () {
        $list.closest(".dropdown-menu").removeClass("dt-colman-sorting");
        const listed = [];
        $list.find(".dt-colman-item").each(function () {
          listed.push(parseInt($(this).attr("data-orig"), 10));
        });
        applyListOrder(ctx, listed);
      },
    });
  }

  function applyListOrder(ctx, listed) {
    const full = currentOrder(ctx);
    const set = new Set(listed);
    const slots = [];
    full.forEach(function (orig, pos) {
      if (set.has(orig)) slots.push(pos);
    });
    if (slots.length !== listed.length) return;

    const next = full.slice();
    listed.forEach(function (orig, i) {
      next[slots[i]] = orig;
    });

    ctx.silent = true;
    ctx.api.colReorder.order(next, true);
    ctx.silent = false;
    refreshAfterLayout(ctx, true);
    persist(ctx);
  }

  function refreshAfterLayout(ctx, redraw) {
    ctx.api.columns.adjust();
    if (redraw) ctx.api.draw(false);
    ensureResizers(ctx);
    if (hasFixedLayout(ctx)) paintAllWidths(ctx);
  }

  function resetAll(ctx) {
    ctx.silent = true;
    if (ctx.reorder) ctx.api.colReorder.reset();
    ctx.settings.aoColumns.forEach(function (col, cur) {
      const want = ctx.defaultVisible[origOf(ctx, cur)];
      if ((col.bVisible !== false) !== want) ctx.api.column(cur).visible(want, false);
    });
    clearWidths(ctx);
    ctx.silent = false;
    writeState(ctx.key, null);
    renderList(ctx);
    refreshAfterLayout(ctx, true);
  }

  function bindDragToHide(ctx) {
    const $table = $(ctx.settings.nTable);
    const $thead = $table.find("> thead");
    const $scroll = $table.closest(".table-responsive");
    if (!$scroll.length) return;
    if ($scroll.css("position") === "static") $scroll.css("position", "relative");

    const $zone = $('<div class="dt-colman-dropzone"><i class="bx bx-hide"></i><span>Sütunu gizlemek için buraya bırakın</span></div>').appendTo($scroll);
    ctx.$zone = $zone;

    $thead.on("mousedown.dtcm", "tr:first-child th", function (ev) {
      if (ev.which !== 1) return;
      if ($(ev.target).closest(".dt-colman-resizer, .dt-filter-mode-trigger, input, select, button, a, .select2").length) return;

      const th = this;
      const cur = ctx.settings.aoColumns.findIndex((c) => c.nTh === th);
      if (cur < 0) return;
      const orig = origOf(ctx, cur);
      if (ctx.locked[orig]) return;

      const startX = ev.pageX;
      const startY = ev.pageY;
      const orderBefore = ctx.reorder ? currentOrder(ctx) : null;
      let dragging = false;
      let below = false;

      function limit() {
        const rect = $thead[0].getBoundingClientRect();
        return rect.bottom + window.pageYOffset + 24;
      }

      function onMove(mv) {
        if (!dragging && Math.abs(mv.pageX - startX) + Math.abs(mv.pageY - startY) > 6) dragging = true;
        if (!dragging) return;
        below = mv.pageY > limit();
        if (below) {
          const top = $thead[0].getBoundingClientRect().bottom - $scroll[0].getBoundingClientRect().top + $scroll[0].scrollTop;
          $zone.css("top", top + "px");
        }
        $zone.toggleClass("active", below);
      }

      function onUp() {
        $(document).off(".dtcmHide");
        $zone.removeClass("active");
        if (!dragging || !below) return;

        const swallow = function (c) {
          c.stopPropagation();
          c.preventDefault();
          document.removeEventListener("click", swallow, true);
        };
        document.addEventListener("click", swallow, true);
        setTimeout(function () {
          document.removeEventListener("click", swallow, true);
        }, 0);

        setTimeout(function () {
          $(".DTCR_clonedTable, .DTCR_pointer").remove();
          if (orderBefore) {
            const now = currentOrder(ctx);
            if (now.some((v, i) => v !== orderBefore[i])) {
              ctx.silent = true;
              ctx.api.colReorder.order(orderBefore, true);
              ctx.silent = false;
            }
          }
          ctx.api.column(curOf(ctx, orig)).visible(false, false);
          refreshAfterLayout(ctx, !!orderBefore);
          if (window.Swal) {
            window.Swal.fire({
              toast: true,
              position: "top-end",
              icon: "info",
              title: '"' + ctx.titles[orig] + '" gizlendi',
              text: "Geri getirmek için Sütunlar menüsünü kullanabilirsiniz.",
              showConfirmButton: false,
              timer: 2600,
              timerProgressBar: true,
            });
          }
        }, 30);
      }

      $(document).on("mousemove.dtcmHide", onMove).on("mouseup.dtcmHide", onUp);
    });
  }

  function buildUi(ctx) {
    const toolbarSlot = $('[data-dt-colman-for="' + ctx.tableId + '"]').first();
    const inToolbar = toolbarSlot.length > 0;
    const btnClass = inToolbar
      ? toolbarSlot.attr("data-dt-colman-style") === "outline"
        ? "btn btn-sm btn-outline-secondary bg-white px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold"
        : "btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center"
      : "btn btn-sm btn-outline-secondary bg-white shadow-sm d-flex align-items-center";

    const $ui = $(
      '<div class="dropdown d-inline-block dt-colman">' +
        '<button type="button" class="' + btnClass + '" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Sütunları Düzenle">' +
        '<i class="mdi mdi-view-column fs-5 me-1"></i> Sütunlar</button>' +
        '<div class="dropdown-menu dropdown-menu-end p-0 shadow-lg border-0 dt-colman-menu">' +
        '<div class="p-2 border-bottom d-flex justify-content-between align-items-center dt-colman-head">' +
        '<span class="fw-semibold font-size-12 text-muted">SÜTUN GÖRÜNÜMÜ</span>' +
        '<button type="button" class="btn btn-sm btn-link text-primary text-decoration-none p-0 font-size-12 fw-bold dt-colman-reset"><i class="mdi mdi-refresh me-1"></i>VARSAYILAN</button>' +
        "</div>" +
        '<div class="p-2 dt-colman-list"></div>' +
        '<div class="px-3 py-2 border-top font-size-11 text-muted dt-colman-hint"><i class="bx bx-move-horizontal me-1"></i>Genişlik için başlık kenarını sürükleyin; sıfırlamak için çift tıklayın.</div>' +
        "</div></div>"
    );

    if (inToolbar) {
      toolbarSlot.empty().append($ui);
    } else {
      const $bar = $('<div class="dt-colman-bar d-flex justify-content-end mb-2"></div>').append($ui);
      $(ctx.settings.nTableWrapper).before($bar);
      ctx.$bar = $bar;
    }

    ctx.$ui = $ui;
    ctx.$list = $ui.find(".dt-colman-list");

    const btn = $ui.find("[data-bs-toggle]")[0];
    if (window.bootstrap && window.bootstrap.Dropdown) {
      new window.bootstrap.Dropdown(btn, { autoClose: "outside" });
    }

    $ui.on("show.bs.dropdown", function () {
      renderList(ctx);
    });

    $ui.on("click", ".dt-colman-list", function (ev) {
      ev.stopPropagation();
    });

    $ui.on("change", ".dt-colman-check", function () {
      const orig = parseInt($(this).attr("data-orig"), 10);
      const show = this.checked;
      const cur = curOf(ctx, orig);
      ctx.api.column(cur).visible(show, false);
      refreshAfterLayout(ctx);
    });

    $ui.on("click", ".dt-colman-reset", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      resetAll(ctx);
    });

    renderList(ctx);
  }

  function applySaved(ctx) {
    const saved = ctx.saved;
    if (!saved) return;

    let reordered = false;
    ctx.silent = true;
    if (ctx.reorder && Array.isArray(saved.order) && saved.order.length === ctx.settings.aoColumns.length) {
      const now = currentOrder(ctx);
      if (now.some((v, i) => v !== saved.order[i])) {
        ctx.api.colReorder.order(saved.order, true);
        reordered = true;
      }
    }
    if (saved.visible) {
      Object.keys(saved.visible).forEach(function (key) {
        const orig = parseInt(key, 10);
        const cur = curOf(ctx, orig);
        if (ctx.settings.aoColumns[cur] && (ctx.settings.aoColumns[cur].bVisible !== false) !== !!saved.visible[key]) {
          ctx.api.column(cur).visible(!!saved.visible[key], false);
        }
      });
    }
    ctx.silent = false;
    if (hasFixedLayout(ctx)) paintAllWidths(ctx);
    ctx.api.columns.adjust();
    if (reordered) ctx.api.draw(false);
  }

  function attach(settings) {
    const ctx = registry.get(settings);
    if (!ctx || ctx.api) return;
    if (ctx.reorder && !settings._colReorder) {
      registry.delete(settings);
      return;
    }

    ctx.api = new $.fn.dataTable.Api(settings);
    const $table = $(settings.nTable);

    if (ctx.converted && settings.aoData.length && Array.isArray(settings.aoData[0]._aData)) {
      ctx.api.colReorder.disable();
      ctx.reorder = false;
    }

    applySaved(ctx);
    applyMinWidths(ctx);
    ensureResizers(ctx);
    buildUi(ctx);
    bindDragToHide(ctx);

    $table.on("column-reorder.dt.dtcm", function (e, s) {
      if (s !== settings || ctx.silent) return;
      setTimeout(function () {
        ctx.api.draw(false);
      }, 0);
      ensureResizers(ctx);
      renderList(ctx);
      persist(ctx);
    });

    $table.on("column-visibility.dt.dtcm", function (e, s, column, state) {
      if (s !== settings || ctx.silent) return;
      if (state && hasFixedLayout(ctx)) {
        const orig = origOf(ctx, column);
        if (!widthOfOrig(ctx, orig)) ctx.widths[orig] = NEW_COLUMN_WIDTH;
        paintAllWidths(ctx);
      } else if (hasFixedLayout(ctx)) {
        syncTableWidth(ctx);
      }
      ensureResizers(ctx);
      renderList(ctx);
      persist(ctx);
    });

    $table.on("draw.dt.dtcm column-sizing.dt.dtcm", function () {
      ensureResizers(ctx);
      if (hasFixedLayout(ctx) && !$("body").hasClass("dt-colman-resizing")) paintAllWidths(ctx);
      else applyMinWidths(ctx);
    });

    $table.on("destroy.dt.dtcm", function () {
      if (ctx.sortable) ctx.sortable.destroy();
      if (ctx.$zone) ctx.$zone.remove();
      $table.find("> thead").off(".dtcm");
      if (ctx.$bar) ctx.$bar.remove();
      else if (ctx.$ui) ctx.$ui.remove();
      $table.find(".dt-colman-resizer").remove();
      $table.removeClass("dt-colman-fixed");
      $table.off(".dtcm");
      registry.delete(settings);
    });
  }

  $(document).on("init.dt", function (e, settings) {
    if (e.namespace !== "dt") return;
    attach(settings);
  });
})(jQuery);
