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
      { key: "null", label: "Boş Olanlar", icon: "bx bx-checkbox" },
      {
        key: "not_null",
        label: "Dolu Olanlar",
        icon: "bx bx-checkbox-checked",
      },
    ],
    number: [
      { key: "contains", label: "İçerir", icon: "bx bx-search" },
      { key: "equals", label: "Eşittir", icon: "bx bx-check" },
      { key: "not_equals", label: "Eşit Değil", icon: "bx bx-x" },
      { key: "greater_than", label: "Büyüktür", icon: "bx bx-chevron-up" },
      { key: "less_than", label: "Küçüktür", icon: "bx bx-chevron-down" },
      { key: "greater_equal", label: "Büyük Eşit", icon: "bx bx-chevrons-up" },
      { key: "less_equal", label: "Küçük Eşit", icon: "bx bx-chevrons-down" },
      { key: "null", label: "Boş Olanlar", icon: "bx bx-checkbox" },
      {
        key: "not_null",
        label: "Dolu Olanlar",
        icon: "bx bx-checkbox-checked",
      },
    ],
    date: [
      { key: "equals", label: "Eşittir", icon: "bx bx-calendar-check" },
      { key: "before", label: "Öncesi", icon: "bx bx-chevron-left" },
      { key: "after", label: "Sonrası", icon: "bx bx-chevron-right" },
      { key: "between", label: "Arasında", icon: "bx bx-calendar-event" },
      { key: "null", label: "Boş Olanlar", icon: "bx bx-checkbox" },
      {
        key: "not_null",
        label: "Dolu Olanlar",
        icon: "bx bx-checkbox-checked",
      },
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
      .replace(/ç/g, "c")
      .replace(/â/g, "a")
      .replace(/î/g, "i")
      .replace(/û/g, "u")
      .replace(/\s+/g, " ")
      .trim();
  }

  function extractTextWithSpaces(html) {
    if (!html) return "";
    if (typeof html !== "string") return html.toString();
    // Wrap to parse easily, replace tag junctions with space
    const cleanedHtml = html.toString().replace(/>\s*</g, "> <");
    const $tmp = $("<div>").html(cleanedHtml);
    return $tmp.text().replace(/\s+/g, " ").trim();
  }

  // --- Filter Engine ---
  function matchFilter(cell, cellValueRaw) {
    const mode = cell.mode;
    const val = cell.value;

    // Determine if applied
    const isApplied = Array.isArray(val)
      ? true
      : val && val.toString().trim() !== "";
    if (!isApplied) return true;

    // Handle empty array as "Select None"
    if (Array.isArray(val) && val.length === 0) return false;

    const cellText = extractTextWithSpaces(cellValueRaw);
    const isSelect = cell.type === "select";

    if (isSelect) {
      const nCell = normalizeTR(cellText);
      const valArr = Array.isArray(val) ? val : [val];
      const match = valArr.some((v) => {
        const nv = normalizeTR(v);
        const hit = nCell === nv || nCell.indexOf(nv) !== -1;
        return hit;
      });
      console.log(`[DT-Filter] Col:${cell.colIdx} Select Match:${match}`, {
        nCell,
        valArr,
      });
      return match;
    }

    if (cell.type === "string") {
      const nCell = normalizeTR(cellText);
      const nFilter = normalizeTR(val);
      let match = false;
      switch (mode) {
        case "contains":
          match = nCell.indexOf(nFilter) !== -1;
          break;
        case "not_contains":
          match = nCell.indexOf(nFilter) === -1;
          break;
        case "starts_with":
          match = nCell.indexOf(nFilter) === 0;
          break;
        case "ends_with":
          match =
            nCell.length >= nFilter.length &&
            nCell.substr(nCell.length - nFilter.length) === nFilter;
          break;
        case "equals":
          match = nCell === nFilter;
          break;
        case "not_equals":
          match = nCell !== nFilter;
          break;
        default:
          match = true;
      }
      return match;
    }

    if (cell.type === "number") {
      const nCellStr = cellText.replace(/[^\d]/g, "");
      const nFilterStr = val.toString().replace(/[^\d]/g, "");
      if (mode === "contains") return nCellStr.indexOf(nFilterStr) !== -1;

      const numCell = parseNumTR(cellText);
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

      // FIX: Stop propagation to prevent sorting when clicking the filter cell
      $th.on("click mousedown", function (e) {
        e.stopPropagation();
      });

      const isActionCol = [
        "SEC",
        "SEÇ",
        "NO",
        "NÖBET",
        "NOBET",
        "BİLDİRİM",
        "BILDIRIM",
      ].includes(title.toUpperCase().replace(/\s/g, ""));

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
        $th: $th,
      };

      if (filterType === "select") {
        let uniqueVals = [];
        const isServerSide = settings.oFeatures.bServerSide;

        const populateOptions = (vals) => {
          $list.find(".option-item:not(.select-all)").remove();
          vals.sort().forEach((v) => {
            const isChecked =
              !cellInfo.value ||
              (Array.isArray(cellInfo.value) && cellInfo.value.includes(v));
            $list.append(
              `<label class="option-item"><input type="checkbox" value="${v}" ${isChecked ? "checked" : ""}> <span>${v}</span></label>`,
            );
          });
          updateSelectAllState();
        };

        column
          .data()
          .unique()
          .each(function (v) {
            const t = extractTextWithSpaces(v);
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

        // Server-side lazy load
        let hasLoadedFullList = !isServerSide;
        const loadFullList = () => {
          if (hasLoadedFullList) return;
          const ajaxUrl =
            typeof settings.ajax === "string"
              ? settings.ajax
              : settings.ajax.url;
          if (!ajaxUrl) return;

          const colData = column.dataSrc();
          if (!colData) return;

          $list.append(
            '<div class="loading-info p-2 text-center text-muted"><i class="bx bx-loader-alt bx-spin"></i> Yükleniyor...</div>',
          );

          const filterData = {
            action: "get-unique-values",
            column: colData,
            columns: [],
          };

          if (settings.ajax && typeof settings.ajax.data === "function") {
            const d = {};
            settings.ajax.data(d);
            Object.assign(filterData, d);
            filterData.action = "get-unique-values"; // Keep our action
          } else if (settings.ajax && typeof settings.ajax.data === "object") {
            Object.assign(filterData, settings.ajax.data);
            filterData.action = "get-unique-values";
          }

          api.columns().every(function () {
            filterData.columns.push({
              search: { value: this.search() },
            });
          });

          $.ajax({
            url: ajaxUrl,
            type: "POST",
            data: filterData,
            dataType: "json",
            success: function (res) {
              $list.find(".loading-info").remove();
              if (res.status === "success" && Array.isArray(res.data)) {
                hasLoadedFullList = true;
                populateOptions(res.data);
              }
            },
            error: function () {
              $list.find(".loading-info").remove();
            },
          });
        };

        // Sync "Select All" initial state
        const updateSelectAllState = () => {
          const $searchInp = $excelDpy.find(".search-box input");
          const searchTerm = normalizeTR($searchInp.val());
          const $items = $list.find(".option-item:not(.select-all)");

          if (searchTerm !== "") {
            let visibleCount = 0;
            let visibleChecked = 0;
            $items.each(function () {
              if ($(this).css("display") !== "none") {
                visibleCount++;
                if ($(this).find("input").prop("checked")) visibleChecked++;
              }
            });
            $excelDpy
              .find(".select-all input")
              .prop(
                "checked",
                visibleCount > 0 && visibleCount === visibleChecked,
              );
          } else {
            const checkedCount = $items.find("input:checked").length;
            $excelDpy
              .find(".select-all input")
              .prop(
                "checked",
                $items.length > 0 && $items.length === checkedCount,
              );
          }
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

          if ($excelDpy.hasClass("show")) {
            loadFullList();
          }
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
          updateSelectAllState();
        });

        $excelDpy.on(
          "change",
          ".option-item:not(.select-all) input",
          updateSelectAllState,
        );

        $excelDpy.find(".select-all input").on("change", function () {
          const isChecked = $(this).prop("checked");
          const term = normalizeTR($excelDpy.find(".search-box input").val());
          const hasSearch = term !== "";

          $excelDpy.find(".option-item:not(.select-all)").each(function () {
            const isVisible = $(this).css("display") !== "none";
            if (isVisible) {
              $(this).find("input").prop("checked", isChecked);
            } else if (hasSearch && isChecked) {
              // Eğer arama yaparken "Hepsini Seç" deniliyorsa, genellikle SADECE arama sonuçları istenir.
              // Bu yüzden gizli olanları kaldırıyoruz.
              $(this).find("input").prop("checked", false);
            }
          });
          updateSelectAllState();
        });

        $excelDpy
          .find(".btn-cancel")
          .on("click", () => $excelDpy.removeClass("show"));

        $excelDpy.find(".btn-apply").on("click", function () {
          const selected = [];
          const $searchInp = $excelDpy.find(".search-box input");
          const term = normalizeTR($searchInp.val());
          const hasSearch = term !== "";

          const $allItems = $list.find(".option-item:not(.select-all) input");

          if (hasSearch) {
            // Eğer arama aktifse, sadece GÖRÜNÜR ve SEÇİLİ olanları alıyoruz
            $list.find(".option-item:not(.select-all)").each(function () {
              if ($(this).css("display") !== "none") {
                const $chk = $(this).find("input");
                if ($chk.prop("checked")) {
                  selected.push($chk.val());
                }
              }
            });

            // Arama varken "Hepsini Seç" deniliyorsa ve her şey görünür/seçiliyse bile,
            // bunu bir dizi olarak gönderelim ki filtreleme sadece bunlarla yapılsın.
            cellInfo.value = selected;
            $displayInput.val(
              selected.length > 0 ? selected.length + " seçildi" : "Hiçbiri",
            );
          } else {
            // Normal mod: Standart mantık
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
        const isNullMode = ["null", "not_null"].includes(cell.mode);
        const hasVal =
          (val &&
            (Array.isArray(val)
              ? val.length > 0
              : val.toString().trim() !== "")) ||
          isNullMode;

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
            let s = cell.mode + ":";
            if (!isNullMode) {
              s += Array.isArray(cell.value)
                ? cell.value.join("|")
                : cell.value;
              if (cell.value2) s += "|" + cell.value2;
            }
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

    $.fn.dataTable.ext.search.push(
      function (s, searchData, dataIndex, rowData) {
        if (s.sTableId !== tableId) return true;
        // Use original rowData or searchData if not available
        const dataToMatch = rowData || searchData;
        for (let i = 0; i < filterCells.length; i++) {
          const cell = filterCells[i];
          if (!matchFilter(cell, dataToMatch[cell.colIdx])) return false;
        }
        return true;
      },
    );

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

    // Girişte kaydedilmiş filtreleri uygula
    const hasInitialFilters = filterCells.some((cell) => {
      const isNullMode = ["null", "not_null"].includes(cell.mode);
      const hasVal =
        (cell.value &&
          (Array.isArray(cell.value)
            ? cell.value.length > 0
            : cell.value.toString().trim() !== "")) ||
        isNullMode;
      const isNonDefaultMode =
        cell.mode !== (DEFAULT_MODES[cell.type] || "contains");
      return hasVal || isNonDefaultMode;
    });

    if (hasInitialFilters) {
      applyFilters();
    } else {
      updateFilterBar();
    }

    // --- Column Visibility Listener ---
    api.on("column-visibility.dt", function (e, settings, column, state) {
      if (settings.sTableId !== tableId) return;
      const cell = filterCells.find((c) => c.colIdx === column);
      if (cell && cell.$th) {
        state ? cell.$th.show() : cell.$th.hide();
      }
    });

    // --- Column Reorder Listener ---
    api.on("column-reorder.dt", function (e, settings, details) {
      if (settings.sTableId !== tableId) return;
      if (!api.colReorder) return;

      // Re-order filter cells to match current column order
      const currentOrder = api.colReorder.order();
      const $cells = $filterRow.children("th");

      // Detach all cells
      $cells.detach();

      // Re-append in new order
      currentOrder.forEach((originalIdx) => {
        const cell = filterCells.find((c) => c.colIdx === originalIdx);
        if (cell && cell.$th) {
          $filterRow.append(cell.$th);
        }
      });
    });
  };
})(jQuery);
