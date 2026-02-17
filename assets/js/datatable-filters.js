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
      { key: "contains", label: "İçerir", icon: "bx bx-search" },
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
    number: "contains",
    date: "equals",
    select: "multi",
  };

  // --- Helpers ---
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

  function parseDateDMY(str) {
    if (!str) return null;
    const parts = str.trim().split(/[\s]+/)[0].split(".");
    if (parts.length === 3) {
      return new Date(
        parseInt(parts[2], 10),
        parseInt(parts[1], 10) - 1,
        parseInt(parts[0], 10),
      );
    }
    const d = new Date(str);
    return isNaN(d.getTime()) ? null : d;
  }

  function parseNumTR(val) {
    if (!val) return NaN;
    const cleaned = val
      .toString()
      .replace(/[^\d.,-]/g, "")
      .replace(/\./g, "")
      .replace(",", ".");
    return parseFloat(cleaned);
  }

  function normalizeTR(data) {
    if (!data) return "";
    return data
      .toString()
      .replace(/[\n\r\t]/g, " ")
      .replace(/\s\s+/g, " ")
      .trim()
      .replace(/İ/gi, "i")
      .replace(/I/g, "ı")
      .replace(/Ş/gi, "s")
      .replace(/Ğ/gi, "g")
      .replace(/Ü/gi, "u")
      .replace(/Ö/gi, "o")
      .replace(/Ç/gi, "c")
      .toLowerCase()
      .replace(/ı/g, "i")
      .replace(/ş/g, "s")
      .replace(/ğ/g, "g")
      .replace(/ü/g, "u")
      .replace(/ö/g, "o")
      .replace(/ç/g, "c");
  }

  // --- Filter Engine ---
  function matchFilter(cell, cellValueRaw) {
    const mode = cell.mode;
    const val = cell.value;
    const hasVal =
      val &&
      (Array.isArray(val) ? val.length > 0 : val.toString().trim() !== "");
    if (!hasVal) return true;

    const cellValue = $("<div>")
      .html(cellValueRaw)
      .text()
      .replace(/\s\s+/g, " ")
      .trim();

    if (cell.type === "select") {
      const normalizedCell = normalizeTR(cellValue);
      if (Array.isArray(val)) {
        return val.some((v) => normalizeTR(v) === normalizedCell);
      }
      return normalizedCell === normalizeTR(val);
    }

    if (cell.type === "string") {
      const nCell = normalizeTR(cellValue);
      const nFilter = normalizeTR(val);
      switch (mode) {
        case "contains":
          return nCell.indexOf(nFilter) !== -1;
        case "not_contains":
          return nCell.indexOf(nFilter) === -1;
        case "starts_with":
          return nCell.indexOf(nFilter) === 0;
        case "ends_with":
          return (
            nCell.length >= nFilter.length &&
            nCell.substr(nCell.length - nFilter.length) === nFilter
          );
        case "equals":
          return nCell === nFilter;
        case "not_equals":
          return nCell !== nFilter;
        default:
          return true;
      }
    }

    if (cell.type === "number") {
      const nCellStr = cellValue.toString().replace(/[^\d]/g, "");
      const nFilterStr = val.toString().replace(/[^\d]/g, "");
      if (mode === "contains") return nCellStr.indexOf(nFilterStr) !== -1;

      const numCell = parseNumTR(cellValue);
      const numFilter = parseNumTR(val);
      if (isNaN(numFilter)) return true;
      if (isNaN(numCell)) return false;
      switch (mode) {
        case "equals":
          return Math.abs(numCell - numFilter) < 0.001;
        case "not_equals":
          return Math.abs(numCell - numFilter) >= 0.001;
        case "greater_than":
          return numCell > numFilter;
        case "less_than":
          return numCell < numFilter;
        case "greater_equal":
          return numCell >= numFilter;
        case "less_equal":
          return numCell <= numFilter;
        default:
          return true;
      }
    }
    return true;
  }

  // --- Main Init ---
  window.initAdvancedFilters = function (api, settings) {
    const tableId = settings.sTableId;
    const $thead = $("#" + tableId + " thead");

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

      if (filterType) {
        $header.addClass("dt-header-with-filter");
        $modeTrigger = $(
          '<button type="button" class="dt-filter-mode-trigger"><i class="bx bx-filter-alt"></i></button>',
        );
        $header.append($modeTrigger);

        if (filterType !== "select") {
          $dropdown = $('<div class="dt-filter-mode-dropdown"></div>');
          (FILTER_MODES[filterType] || FILTER_MODES["string"]).forEach((m) => {
            $dropdown.append(
              $('<button type="button" class="mode-opt"></button>')
                .attr("data-mode", m.key)
                .html(`<i class="${m.icon}"></i> ${m.label}`),
            );
          });
          $("body").append($dropdown);

          $modeTrigger.on("click", function (e) {
            e.stopPropagation();
            $(".dt-filter-mode-dropdown, .dt-filter-excel-dropdown")
              .not($dropdown)
              .removeClass("show");

            // Mark active mode
            $dropdown.find(".mode-opt").removeClass("active");
            $dropdown
              .find(`.mode-opt[data-mode="${cellInfo.mode}"]`)
              .addClass("active");

            const offset = $(this).offset();
            $dropdown
              .css({
                top: offset.top + $(this).outerHeight() + 2,
                left: Math.min(offset.left, $(window).width() - 160),
              })
              .toggleClass("show");
          });
        }
      }

      const $cell = $('<div class="dt-filter-cell"></div>');
      $th.append($cell);

      const cellState = savedState[colIdx] || {};
      const cellInfo = {
        colIdx: colIdx,
        type: filterType || "string",
        mode: cellState.mode || DEFAULT_MODES[filterType] || "contains",
        value: cellState.value || "",
        value2: cellState.value2 || "",
        title: title,
        $trigger: $modeTrigger,
        $dropdown: $dropdown,
      };

      if (filterType === "select") {
        const uniqueVals = [];
        column
          .data()
          .unique()
          .each(function (v) {
            const t = $("<div>").html(v).text().replace(/\s\s+/g, " ").trim();
            if (t && !uniqueVals.includes(t)) uniqueVals.push(t);
          });
        uniqueVals.sort();

        const $excelDpy = $('<div class="dt-filter-excel-dropdown"></div>');
        $excelDpy.append(
          '<div class="search-box"><input type="text" placeholder="Ara..."></div>',
        );
        const $list = $('<div class="options-list"></div>');

        $list.append(
          `<label class="option-item select-all"><input type="checkbox"> <span>(Hepsini Seç)</span></label>`,
        );
        uniqueVals.forEach((v) => {
          // Sync logic: If value is an array, check if it contains v. If value is empty, all are checked by default in Excel.
          // BUT: If user explicitly unchecked some, value is an array of what remains.
          // If value is empty string, it means "All Selected".
          const isChecked =
            !cellInfo.value ||
            (Array.isArray(cellInfo.value) && cellInfo.value.includes(v));
          $list.append(
            `<label class="option-item"><input type="checkbox" value="${v}" ${isChecked ? "checked" : ""}> <span>${v}</span></label>`,
          );
        });
        $excelDpy.append($list);

        const $footer = $(
          '<div class="dt-filter-footer"><button type="button" class="btn btn-primary btn-sm btn-apply">Tamam</button><button type="button" class="btn btn-light btn-sm btn-cancel">İptal</button></div>',
        );
        $excelDpy.append($footer);
        $("body").append($excelDpy);

        cellInfo.$excelDropdown = $excelDpy;

        // Sync "Select All" initial state
        const updateSelectAllState = () => {
          const $items = $list.find(".option-item:not(.select-all) input");
          const $checked = $list.find(
            ".option-item:not(.select-all) input:checked",
          );
          $excelDpy
            .find(".select-all input")
            .prop("checked", $items.length === $checked.length);
        };
        updateSelectAllState();

        $modeTrigger.on("click", function (e) {
          e.stopPropagation();
          $(".dt-filter-mode-dropdown, .dt-filter-excel-dropdown")
            .not($excelDpy)
            .removeClass("show");
          const offset = $(this).offset();
          let leftPos = offset.left;
          if (leftPos + 240 > $(window).width())
            leftPos = $(window).width() - 250;
          $excelDpy
            .css({
              top: offset.top + $(this).outerHeight() + 2,
              left: Math.max(10, leftPos),
            })
            .toggleClass("show");
        });

        const $displayInput = $(
          '<input type="text" class="dt-filter-control text-control" readonly placeholder="Tümü">',
        );
        if (Array.isArray(cellInfo.value) && cellInfo.value.length > 0) {
          $displayInput.val(cellInfo.value.length + " seçildi");
        }
        $cell.append($displayInput);
        cellInfo.$displayInput = $displayInput;

        $excelDpy.find(".search-box input").on("input", function () {
          const term = normalizeTR($(this).val());
          $excelDpy.find(".option-item:not(.select-all)").each(function () {
            const text = normalizeTR($(this).find("span").text());
            $(this).toggle(text.indexOf(term) !== -1);
          });
        });

        $excelDpy
          .find(".option-item:not(.select-all) input")
          .on("change", updateSelectAllState);

        $excelDpy.find(".select-all input").on("change", function () {
          const isChecked = $(this).prop("checked");
          $excelDpy
            .find(".option-item:not(.select-all):visible input")
            .prop("checked", isChecked);
        });

        $excelDpy
          .find(".btn-cancel")
          .on("click", () => $excelDpy.removeClass("show"));

        $excelDpy.find(".btn-apply").on("click", function () {
          const selected = [];
          const $allItems = $list.find(".option-item:not(.select-all) input");
          const $checkedItems = $list.find(
            ".option-item:not(.select-all) input:checked",
          );

          if ($checkedItems.length < $allItems.length) {
            $checkedItems.each(function () {
              selected.push($(this).val());
            });
            cellInfo.value = selected;
            $displayInput.val(
              selected.length > 0 ? selected.length + " seçildi" : "Hiçbiri",
            );
          } else {
            cellInfo.value = "";
            $displayInput.val("");
          }
          $excelDpy.removeClass("show");
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
        ).attr("placeholder", title);
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

          // Focus the input after mode selection
          if (cellInfo.input) {
            setTimeout(() => {
              $(cellInfo.input).focus();
            }, 50);
          }

          applyFilters();
        });
      }
      filterCells.push(cellInfo);
    });

    function applyFilters() {
      const state = {};
      filterCells.forEach((cell) => {
        const val = cell.value;
        const hasVal =
          val &&
          (Array.isArray(val) ? val.length > 0 : val.toString().trim() !== "");
        if (cell.$trigger) cell.$trigger.toggleClass("active", !!hasVal);
        if (
          hasVal ||
          (cell.type !== "select" &&
            cell.mode !== (DEFAULT_MODES[cell.type] || "contains"))
        ) {
          state[cell.colIdx] = {
            mode: cell.mode,
            value: cell.value,
            value2: cell.value2,
          };
        }
        if (settings.oFeatures.bServerSide) {
          if (hasVal) {
            let s =
              cell.mode +
              ":" +
              (Array.isArray(cell.value) ? cell.value.join("|") : cell.value);
            if (cell.value2) s += "|" + cell.value2;
            api.column(cell.colIdx).search(s);
          } else api.column(cell.colIdx).search("");
        }
      });
      saveFiltersToStorage(tableId, state);
      api.draw();
      updateFilterBar();
    }

    let $filterBar = null;
    function updateFilterBar() {
      const active = filterCells.filter((c) => {
        const val = c.value;
        return (
          val &&
          (Array.isArray(val) ? val.length > 0 : val.toString().trim() !== "")
        );
      });
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
        let valDisplay = Array.isArray(cell.value)
          ? cell.value.length > 2
            ? cell.value.length + " öğe"
            : cell.value.join(", ")
          : cell.value;
        if (cell.value2) valDisplay += " - " + cell.value2;
        html += `<span class="badge">${cell.title}${mLabel && cell.type !== "select" ? " (" + mLabel + ")" : ""}: ${valDisplay}<i class="bx bx-x remove" data-col="${cell.colIdx}"></i></span>`;
      });
      html +=
        '<button type="button" class="btn btn-danger btn-sm clear-all">Tümünü Temizle</button>';
      $filterBar.html(html);
    }

    $.fn.dataTable.ext.search.push(function (s, searchData, dataIndex) {
      if (s.sTableId !== tableId) return true;
      for (let i = 0; i < filterCells.length; i++) {
        if (!matchFilter(filterCells[i], searchData[filterCells[i].colIdx]))
          return false;
      }
      return true;
    });

    const ns = ".dtf_" + tableId;
    $(document)
      .off("click" + ns)
      .on("click" + ns, (e) => {
        const $t = $(e.target);
        if ($t.closest(".dt-active-filters-bar .remove").length) {
          const col = $t.closest(".remove").data("col");
          const cell = filterCells.find((c) => c.colIdx === col);
          if (cell) {
            clearSingleFilter(cell);
            applyFilters();
          }
        } else if ($t.closest(".dt-active-filters-bar .clear-all").length) {
          filterCells.forEach((cell) => clearSingleFilter(cell));
          clearFiltersFromStorage(tableId);
          api.search("").draw();
          updateFilterBar();
        } else if (
          !$t.closest(
            ".dt-filter-mode-dropdown, .dt-filter-excel-dropdown, .dt-filter-mode-trigger",
          ).length
        ) {
          $(".dt-filter-mode-dropdown, .dt-filter-excel-dropdown").removeClass(
            "show",
          );
        }
      });

    function clearSingleFilter(cell) {
      cell.value = "";
      cell.value2 = "";
      cell.mode = DEFAULT_MODES[cell.type] || "contains";
      if (cell.input) $(cell.input).val("");
      if (cell.$displayInput) cell.$displayInput.val("");
      if (cell.$excelDropdown) {
        cell.$excelDropdown
          .find('input[type="checkbox"]')
          .prop("checked", true);
        cell.$excelDropdown.find(".search-box input").val("").trigger("input");
      }
      if (cell._fp) cell._fp.clear();
      if (cell.$trigger) cell.$trigger.removeClass("active");
      api.column(cell.colIdx).search("");
    }

    filterCells.forEach((cell) => {
      const val = cell.value;
      const hasVal =
        val &&
        (Array.isArray(val) ? val.length > 0 : val.toString().trim() !== "");
      if (cell.$trigger) cell.$trigger.toggleClass("active", !!hasVal);
    });
    if (
      filterCells.some((c) => {
        const v = c.value;
        return (
          v && (Array.isArray(v) ? v.length > 0 : v.toString().trim() !== "")
        );
      })
    ) {
      api.draw();
      updateFilterBar();
    }
  };
})(jQuery);
