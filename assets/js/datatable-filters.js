/**
 * DataTable Advanced Column Filters
 * ==================================
 * Gelişmiş kolon filtreleme sistemi.
 */

(function ($) {
  "use strict";

  const FILTER_MODES = {
    string: [
      { key: "contains", label: "İçerir", icon: "bx bx-search" },
      { key: "not_contains", label: "İçermez", icon: "bx bx-block" },
      { key: "starts_with", label: "İle Başlar", icon: "bx bx-chevron-right" },
      { key: "ends_with", label: "İle Biter", icon: "bx bx-chevron-left" },
      { key: "equals", label: "Eşittir", icon: "bx bx-check" },
      { key: "not_equals", label: "Eşit Değil", icon: "bx bx-x" },
    ],
    number: [
      { key: "equals", label: "Eşittir", icon: "bx bx-check" },
      { key: "not_equals", label: "Eşit Değil", icon: "bx bx-x" },
      { key: "greater_than", label: "Büyüktür", icon: "bx bx-chevron-up" },
      { key: "less_than", label: "Küçüktür", icon: "bx bx-chevron-down" },
      { key: "greater_equal", label: "Büyük Eşit", icon: "bx bx-chevrons-up" },
      { key: "less_equal", label: "Küçük Eşit", icon: "bx bx-chevrons-down" },
    ],
    date: [
      { key: "equals", label: "Eşittir", icon: "bx bx-calendar-check" },
      { key: "before", label: "Öncesi", icon: "bx bx-chevron-left" },
      { key: "after", label: "Sonrası", icon: "bx bx-chevron-right" },
      { key: "between", label: "Arasında", icon: "bx bx-calendar-event" },
    ],
  };

  const DEFAULT_MODES = {
    string: "contains",
    number: "equals",
    date: "equals",
  };

  function getStorageKey(tableId) {
    return "dt_adv_filters_" + tableId;
  }
  function saveFiltersToStorage(tableId, state) {
    localStorage.setItem(getStorageKey(tableId), JSON.stringify(state));
  }
  function loadFiltersFromStorage(tableId) {
    try {
      return JSON.parse(localStorage.getItem(getStorageKey(tableId))) || {};
    } catch (e) {
      return {};
    }
  }
  function clearFiltersFromStorage(tableId) {
    localStorage.removeItem(getStorageKey(tableId));
  }
  function formatDateDMY(d) {
    if (!d) return "";
    return (
      d.getDate().toString().padStart(2, "0") +
      "." +
      (d.getMonth() + 1).toString().padStart(2, "0") +
      "." +
      d.getFullYear()
    );
  }

  window.initAdvancedFilters = function (api, settings) {
    const tableId = settings.sTableId;
    const $thead = $("#" + tableId + " thead");

    // Check if any column has advanced filter enabled
    const hasAnyFilter = $thead.find("th[data-filter]").length > 0;
    if (!hasAnyFilter) return;

    if ($thead.find(".dt-filter-row").length > 0) return;

    const savedState = loadFiltersFromStorage(tableId);
    const $filterRow = $('<tr class="dt-filter-row"></tr>');
    $thead.append($filterRow);

    const filterCells = [];

    api.columns().every(function () {
      const column = this;
      const colIdx = column.index();
      const $header = $(column.header());
      const filterType = $header.attr("data-filter");
      const title = $header.text().trim();

      const $th = $("<th></th>");
      if (!column.visible()) $th.hide();
      $filterRow.append($th);

      const isActionCol =
        ["İŞLEM", "SEC", "SEÇ", "#", "SIRA", "BİLDİRİM", "BILDIRIM"].includes(
          title.toUpperCase().replace(/\s/g, ""),
        ) || $header.find('input[type="checkbox"]').length > 0;

      if (!filterType && isActionCol) return;

      let $modeTrigger = null;
      let $dropdown = null;

      if (filterType && filterType !== "select") {
        $header.addClass("dt-header-with-filter");
        $modeTrigger = $(
          '<button type="button" class="dt-filter-mode-trigger"><i class="bx bx-filter-alt"></i></button>',
        );
        $header.append($modeTrigger);

        $dropdown = $('<div class="dt-filter-mode-dropdown"></div>');
        (FILTER_MODES[filterType] || []).forEach((m) => {
          $dropdown.append(
            $('<button type="button" class="mode-opt"></button>')
              .attr("data-mode", m.key)
              .html(`<i class="${m.icon}"></i> ${m.label}`),
          );
        });
        $("body").append($dropdown);

        $modeTrigger.on("click", function (e) {
          e.stopPropagation();
          $(".dt-filter-mode-dropdown").not($dropdown).removeClass("show");
          const offset = $(this).offset();
          $dropdown
            .css({
              top: offset.top + $(this).outerHeight() + 2,
              left: Math.min(offset.left, $(window).width() - 160),
            })
            .toggleClass("show");
        });
      }

      const $cell = $('<div class="dt-filter-cell"></div>');
      $th.append($cell);

      const cellState = savedState[colIdx] || {};
      const cellInfo = {
        colIdx: colIdx,
        type: filterType || "basic",
        mode: cellState.mode || DEFAULT_MODES[filterType] || "contains",
        value: cellState.value || "",
        value2: cellState.value2 || "",
        title: title,
        $trigger: $modeTrigger,
        $dropdown: $dropdown,
      };

      if (filterType === "select") {
        const $select = $(
          '<select class="dt-filter-control select-control"></select>',
        );
        $select.append('<option value="">Tümü</option>');
        const uniqueVals = {};
        column.data().each(function (v) {
          const t = $("<div>").html(v).text().trim();
          if (t) uniqueVals[t] = true;
        });
        Object.keys(uniqueVals)
          .sort()
          .forEach((v) => {
            $select.append(
              new Option(v, v, v === cellInfo.value, v === cellInfo.value),
            );
          });
        $cell.append($select);
        cellInfo.select = $select[0];
        $select.on("change", function () {
          cellInfo.value = $(this).val();
          applyFilters();
        });
      } else if (filterType === "date") {
        const $input = $(
          '<input type="text" class="dt-filter-control date-control" placeholder="Tarih...">',
        );
        if (cellInfo.value) $input.val(cellInfo.value);
        $cell.append($input);
        cellInfo.input = $input[0];

        const initFp = () => {
          if (cellInfo._fp) cellInfo._fp.destroy();
          cellInfo._fp = $($input).flatpickr({
            locale: "tr",
            dateFormat: "d.m.Y",
            allowInput: true,
            mode: cellInfo.mode === "between" ? "range" : "single",
            defaultDate: cellInfo.value || null,
            onChange: (sel, str) => {
              if (cellInfo.mode === "between") {
                if (sel.length === 2) {
                  cellInfo.value = formatDateDMY(sel[0]);
                  cellInfo.value2 = formatDateDMY(sel[1]);
                  applyFilters();
                }
              } else {
                cellInfo.value = str;
                applyFilters();
              }
            },
          });
        };
        initFp();
        cellInfo.reinitDate = initFp;
      } else {
        const $input = $(
          '<input type="text" class="dt-filter-control text-control" autocomplete="off">',
        );
        $input.attr("placeholder", title);
        if (cellInfo.value) $input.val(cellInfo.value);
        $cell.append($input);
        cellInfo.input = $input[0];

        let timeout;
        $input.on("input", function () {
          const val = $(this).val().trim();
          clearTimeout(timeout);
          timeout = setTimeout(() => {
            cellInfo.value = val;
            applyFilters();
          }, 300);
        });
      }

      if ($dropdown) {
        $dropdown.on("click", ".mode-opt", function (e) {
          e.stopPropagation();
          cellInfo.mode = $(this).attr("data-mode");
          $dropdown.removeClass("show");
          if (cellInfo.reinitDate) cellInfo.reinitDate();
          applyFilters();
        });
      }

      filterCells.push(cellInfo);
    });

    function applyFilters() {
      const state = {};
      filterCells.forEach((cell) => {
        const hasVal =
          (cell.value && cell.value.trim() !== "") ||
          (cell.value2 && cell.value2.trim() !== "");
        if (cell.$trigger) {
          cell.$trigger.toggleClass("active", !!hasVal);
        }

        if (hasVal || cell.mode !== (DEFAULT_MODES[cell.type] || "contains")) {
          state[cell.colIdx] = {
            mode: cell.mode,
            value: cell.value,
            value2: cell.value2,
          };
        }

        if (settings.oFeatures.bServerSide) {
          if (hasVal) {
            let s = cell.mode + ":" + cell.value;
            if (cell.value2) s += "|" + cell.value2;
            api.column(cell.colIdx).search(s);
          } else {
            api.column(cell.colIdx).search("");
          }
        }
      });
      saveFiltersToStorage(tableId, state);
      api.draw();
      updateFilterBar();
    }

    let $filterBar = null;
    function updateFilterBar() {
      const active = filterCells.filter(
        (c) => c.value && c.value.trim() !== "",
      );
      if (active.length === 0) {
        if ($filterBar) $filterBar.remove();
        $filterBar = null;
        return;
      }
      if (!$filterBar) {
        $filterBar = $('<div class="dt-active-filters-bar"></div>');
        $("#" + tableId)
          .closest(".dataTables_wrapper")
          .prepend($filterBar);
      }
      let html = '<span class="label">Filtreler:</span>';
      active.forEach((cell) => {
        const mLabel =
          (FILTER_MODES[cell.type] || []).find((m) => m.key === cell.mode)
            ?.label || "";
        const val = cell.value + (cell.value2 ? " - " + cell.value2 : "");
        html += `<span class="badge">${cell.title}${mLabel ? " (" + mLabel + ")" : ""}: ${val}<i class="bx bx-x remove" data-col="${cell.colIdx}"></i></span>`;
      });
      html += '<button type="button" class="clear-all">Tümünü Temizle</button>';
      $filterBar.html(html);
    }

    // Event Delegation for Body level clicks
    const ns = ".dtf_" + tableId;
    $(document)
      .off("click" + ns)
      .on("click" + ns, (e) => {
        const $t = $(e.target);
        if ($t.closest(".dt-active-filters-bar .remove").length) {
          const col = $t.closest(".remove").data("col");
          const cell = filterCells.find((c) => c.colIdx === col);
          if (cell) {
            cell.value = "";
            cell.value2 = "";
            cell.mode = DEFAULT_MODES[cell.type] || "contains";
            if (cell.input) $(cell.input).val("");
            if (cell.select) $(cell.select).val("");
            if (cell._fp) cell._fp.clear();
            applyFilters();
          }
        } else if ($t.closest(".dt-active-filters-bar .clear-all").length) {
          filterCells.forEach((cell) => {
            cell.value = "";
            cell.value2 = "";
            cell.mode = DEFAULT_MODES[cell.type] || "contains";
            if (cell.input) $(cell.input).val("");
            if (cell.select) $(cell.select).val("");
            if (cell._fp) cell._fp.clear();
            api.column(cell.colIdx).search("");
          });
          clearFiltersFromStorage(tableId);
          api.search("").draw();
          updateFilterBar();
        } else {
          $(".dt-filter-mode-dropdown").removeClass("show");
        }
      });

    // Initial persistence sync
    filterCells.forEach((cell) => {
      const hasVal = cell.value && cell.value.trim() !== "";
      if (cell.$trigger) cell.$trigger.toggleClass("active", !!hasVal);
      if (hasVal) {
        if (settings.oFeatures.bServerSide) {
          let s = cell.mode + ":" + cell.value;
          if (cell.value2) s += "|" + cell.value2;
          api.column(cell.colIdx).search(s);
        }
      }
    });
    if (filterCells.some((c) => c.value && c.value.trim() !== "")) {
      api.draw();
      updateFilterBar();
    }
  };
})(jQuery);
