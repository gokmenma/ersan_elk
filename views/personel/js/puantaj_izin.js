$(document).ready(function () {
  const API_URL = "views/personel/api/puantaj_izin.php";
  let tableRows = []; // Row cache for performance
  let selectedType = null;
  let unsavedChanges = {}; // { 'personelId-date': { typeId, name, color, shortCode } }
  let definitionsMap = {}; // { shortCode: { id, name, color, shortCode } }
  let personnelMap = {}; // { nameOrTc: personObj }
  let ucretsizIzinIds = new Set();
  let excelReplacedPersonnel = new Set(); // Excel'den yüklenen personellerin ID'leri
  let ucretliDefinitions = [];
  let ucretsizDefinitions = [];

  // Initial Load from LocalStorage or default
  const savedAy = localStorage.getItem("puantaj_ay");
  const savedYil = localStorage.getItem("puantaj_yil");
  const savedFilter = localStorage.getItem("puantaj_filter") || "";

  if (savedAy) $("#select-ay").val(savedAy);
  if (savedYil) $("#select-yil").val(savedYil);
  if (savedFilter) $("#personel-filter").val(savedFilter);

  // Initialize Bootstrap Tooltips and Popovers ONCE using delegation on the container
  // This is MUCH faster for large tables (delegation vs thousands of instances)
  const tooltipInstance = new bootstrap.Tooltip(document.getElementById('puantaj-full-container'), {
    selector: '[data-bs-toggle="tooltip"]',
    trigger: 'hover',
    container: 'body'
  });

  const popoverInstance = new bootstrap.Popover(document.getElementById('puantaj-full-container'), {
    selector: '[data-bs-toggle="popover"]',
    trigger: 'hover focus',
    html: true,
    container: 'body'
  });

  loadDefinitions(function() {
    renderTable();
  });

  // Events
  $("#select-ay").on("change", function () {
    localStorage.setItem("puantaj_ay", $(this).val());
    renderTable();
  });

  $("#select-yil").on("change", function () {
    localStorage.setItem("puantaj_yil", $(this).val());
    renderTable();
  });

  // Türkçe karakter duyarlı küçük harfe çevirme fonksiyonu
  function turkceKucukHarf(text) {
    if (!text) return "";
    return text.replace(/İ/g, "i").replace(/I/g, "ı").toLowerCase();
  }

  // Personel Filtreleme
  function applyFilter() {
    const filterValue = turkceKucukHarf($("#personel-filter").val());
    localStorage.setItem("puantaj_filter", filterValue);

    const rows = tableRows;
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const name = turkceKucukHarf(row.getAttribute("data-personel-adi") || "");
      if (name.indexOf(filterValue) > -1) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    }

    calculateTotals();
  }

  let filterTimer = null;
  $("#personel-filter").on("input", function () {
    localStorage.setItem("puantaj_filter", $(this).val());
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      applyFilter();
      calculateTotals();
    }, 200);
  });

  // Tam Ekran Modu
  $("#btn-fullscreen").on("click", function () {
    $("body").toggleClass("puantaj-fullscreen");
    const isFull = $("body").hasClass("puantaj-fullscreen");

    $(this).html(
      isFull
        ? '<i class="mdi mdi-fullscreen-exit me-1"></i> Küçült'
        : '<i class="mdi mdi-fullscreen me-1"></i> Tam Ekran',
    );

    $(this).toggleClass("btn-soft-secondary btn-soft-danger");
  });

  // ESC ile Tam Ekrandan Çık
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $("body").hasClass("puantaj-fullscreen")) {
      $("#btn-fullscreen").click();
    }
  });

  $(document).on("click", ".draggable-izin", function () {
    selectedType = {
      id: $(this).data("id"),
      name: $(this).data("name"),
      color: $(this).data("color"),
      shortCode: $(this).data("shortcode"),
    };
  });

  function getShortCode(item) {
    if (item.kisa_kod) return item.kisa_kod;
    if (!item.tur_adi) return "??";
    return item.tur_adi
      .split(" ")
      .map((w) => w[0])
      .join("")
      .toUpperCase()
      .substring(0, 2);
  }

  // Helper: Get days in month
  function getDaysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
  }

  // Helper: Get style from tailwind class (WITH CACHE)
  const styleCache = {};
  function getStyleFromTailwind(tailwindClass) {
    if (!tailwindClass) return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };
    if (styleCache[tailwindClass]) return styleCache[tailwindClass];

    let result;
    // Check if it's already a hex
    if (tailwindClass.startsWith("#")) {
      result = {
        bg: tailwindClass + "26", // 15% opacity hex
        color: tailwindClass,
      };
    } else if (tailwindClass.includes("blue")) {
      result = { bg: "#dbeafe", color: "#2563eb" };
    } else if (tailwindClass.includes("amber")) {
      result = { bg: "#fef3c7", color: "#d97706" };
    } else if (tailwindClass.includes("red")) {
      result = { bg: "#fee2e2", color: "#dc2626" };
    } else if (tailwindClass.includes("pink")) {
      result = { bg: "#fce7f3", color: "#db2777" };
    } else if (tailwindClass.includes("gray")) {
      result = { bg: "#f3f4f6", color: "#4b5563" };
    } else if (tailwindClass.includes("green")) {
      result = { bg: "#dcfce7", color: "#16a34a" };
    } else if (tailwindClass.includes("purple")) {
      result = { bg: "#f3e8ff", color: "#9333ea" };
    } else {
      // Default to primary theme color (light style)
      result = { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };
    }

    styleCache[tailwindClass] = result;
    return result;
  }

  // Load Definitions
  function loadDefinitions(callback) {
    $.post(API_URL, { action: "get-definitions" }, function (res) {
      if (res.status === "success") {
        ucretsizIzinIds.clear();
        const render = (list, containerId, isUcretsiz = false) => {
          let html = "";
          list.forEach((item) => {
            if (isUcretsiz) ucretsizIzinIds.add(item.id.toString());
            const style = getStyleFromTailwind(item.renk);
            const shortCode = getShortCode(item);
            html += `
                            <div class="izin-item-container draggable-izin" draggable="true"
                                 data-id="${item.id}" 
                                 data-name="${item.tur_adi}"
                                 data-color="${item.renk}"
                                 data-shortcode="${shortCode}"
                                 data-bs-toggle="tooltip" 
                                 title="${item.tur_adi}">
                                <div class="izin-box" style="background-color: ${style.bg} !important; color: ${style.color} !important; border: 1px solid ${style.color}33;">
                                    ${shortCode}
                                </div>
                                <span class="d-none">${item.tur_adi}</span>
                            </div>`;

            // Map definitions for Excel import
            if (shortCode) {
              definitionsMap[shortCode.toUpperCase()] = {
                id: item.id,
                name: item.tur_adi,
                color: item.renk,
                shortCode: shortCode,
              };
            }
          });
          $(`#${containerId}`).html(html);
        };
        if (res.data.ucretli) {
          ucretliDefinitions = res.data.ucretli;
          render(res.data.ucretli, "ucretli-list", false);
        }
        if (res.data.ucretsiz) {
          ucretsizDefinitions = res.data.ucretsiz;
          render(res.data.ucretsiz, "ucretsiz-list", true);
        }

        // İzin türleri yüklendikten sonra sticky yüksekliği güncelle
        setTimeout(updateStickyHeights, 100);
        if (callback) callback();
      }
    });
  }

  // Render Table
  function renderTable() {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();
    const daysCount = getDaysInMonth(ay, yil);

    // Tabloyu temizle ve yükleniyor göster
    $("#table-body").empty();
    $("#puantaj-loader").fadeIn(100);

    // Header
    let headerHtml = '<th class="sticky-col">Personel</th>';
    const dayNames = ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"];
    for (let d = 1; d <= daysCount; d++) {
      const date = new Date(yil, ay - 1, d);
      const isSunday = date.getDay() === 0;
      const dayName = dayNames[date.getDay()];
      const sundayClass = isSunday ? "is-sunday" : "";

      headerHtml += `<th class="${sundayClass}">
         <div class="day-header-pill">
             <div class="day-name">${dayName}</div>
             <div class="day-number">${d}</div>
         </div>
      </th>`;
    }
    headerHtml += '<th class="sticky-col-right-1">Toplam Gün</th>';
    $("#table-header").html(headerHtml);

    // Body with data
    $.post(
      API_URL,
      { action: "get-calendar-data", ay, yil },
      function (res) {
        if (res.status === "success") {
          try {
            let bodyHtml = "";
            personnelMap = {}; // Clear previous personnel map
            if (res.data && Array.isArray(res.data)) {
              if (res.data.length === 0) {
                $("#table-body").html(
                  '<tr><td colspan="40" class="text-center py-5 text-muted">Bu kriterlere uygun personel bulunamadı.</td></tr>',
                );
              } else {
                res.data.forEach((p) => {
                  // Map personnel for Excel import
                  if (p.adi_soyadi)
                    personnelMap[turkceKucukHarf(p.adi_soyadi)] = p;
                  if (p.tc_kimlik_no) personnelMap[p.tc_kimlik_no] = p;
                  bodyHtml += `<tr data-personel-adi="${turkceKucukHarf(p.adi_soyadi)}">
                        <td class="personel-info sticky-col" title="${p.adi_soyadi}" data-personel-id="${p.id}" data-gg-toplam-gun="${p.gg_toplam_gun || 0}" data-gorev-gecmisi="${p.gorev_gecmisi_var || 0}">
                            <div class="d-flex align-items-center">
                                <a href="?p=personel/manage&id=${p.encrypt_id}" class="text-truncate-name text-primary fw-bold" style="text-decoration: none;">${p.adi_soyadi}</a>
                            </div>
                        </td>`;

                  let disabledDaysCount = 0;
                  let unpaidCount = 0;

                  const pGiris = p.ise_giris_tarihi && p.ise_giris_tarihi !== "0000-00-00" ? new Date(p.ise_giris_tarihi) : 0;
                  const pCikis = p.isten_cikis_tarihi && p.isten_cikis_tarihi !== "0000-00-00" ? new Date(p.isten_cikis_tarihi) : new Date(yil + 10, 1, 1); // Far future date

                  for (let d = 1; d <= daysCount; d++) {
                    const dateStr = `${yil}-${ay}-${d.toString().padStart(2, "0")}`;
                    const dateObj = new Date(yil, ay - 1, d);
                    const isSunday = dateObj.getDay() === 0;
                    const sundayClass = isSunday ? "is-sunday" : "";

                    const isDisabled = (pGiris && dateObj < pGiris) || (pCikis && dateObj > pCikis);
                    const disabledClass = isDisabled ? "disabled" : "";
                    if (isDisabled) disabledDaysCount++;

                    let cellContent = "";
                    let hasEntryClass = "";

                    const key = `${p.id}-${dateStr}`;
                    const unsaved = unsavedChanges[key];

                    // Excel'den yüklenen personel için mevcut kayıtları gizle, sadece unsaved göster
                    const isExcelReplaced = excelReplacedPersonnel.has(
                      p.id.toString(),
                    );
                    const entries = isExcelReplaced
                      ? []
                      : p.entries[dateStr] || [];

                    // Önce unsavedChanges kontrol et (Excel'den yüklenen dahil)
                    if (unsaved) {
                      const unsavedStyle = getStyleFromTailwind(unsaved.color);
                      const typeId = (
                        unsaved.type_id || unsaved.typeId
                      )?.toString();
                      // Sadece manuel giriş yapılan ücretsiz izinleri say (default HT/X değil)
                      if (ucretsizIzinIds.has(typeId)) unpaidCount++;

                      hasEntryClass = "has-entry unsaved";
                      cellContent = `
                                <div class="cell-content draggable-izin" draggable="true"
                                     data-bs-toggle="tooltip" 
                                     title="${unsaved.name}" 
                                     data-id="${unsaved.type_id || unsaved.typeId}" 
                                     data-shortcode="${unsaved.shortCode}"
                                     data-name="${unsaved.name}"
                                     data-color="${unsaved.color}"
                                     data-is-default="false"
                                     style="background-color: ${unsavedStyle.bg} !important; color: ${unsavedStyle.color} !important; border: 1px solid ${unsavedStyle.color}33;">
                                    ${unsaved.shortCode}
                                    <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
                                </div>`;
                    } else if (entries.length > 0) {
                      const entry = entries[0];
                      const styleObj = getStyleFromTailwind(entry.color);
                      const typeId = entry.tip_id?.toString();
                      // Sadece manuel giriş yapılan ücretsiz izinleri say (default HT/X değil)
                      if (entry.type !== "default" && ucretsizIzinIds.has(typeId)) unpaidCount++;
                      
                      hasEntryClass = "has-entry";
                      const shortCode = getShortCode(entry);

                      let deleteBtn = "";
                      if (entry.type !== "default") {
                        deleteBtn = `<span class="btn-delete-cell" onclick="deleteEntry(${entry.id}, event)">×</span>`;
                      }

                      cellContent = `
                                <div class="cell-content draggable-izin" draggable="true"
                                     data-bs-toggle="tooltip" 
                                     title="${entry.name}" 
                                     data-id="${entry.tip_id}" 
                                     data-shortcode="${shortCode}"
                                     data-name="${entry.name}"
                                     data-color="${entry.color}"
                                     data-is-default="${entry.type === "default"}"
                                     style="background-color: ${styleObj.bg} !important; color: ${styleObj.color} !important; border: 1px solid ${styleObj.color}33;">
                                    ${shortCode}
                                    ${deleteBtn}
                                </div>`;
                    }

                    bodyHtml += `<td class="day-cell ${hasEntryClass} ${sundayClass} ${disabledClass}" 
                                         data-personel-id="${p.id}" 
                                         data-date="${dateStr}">
                                        ${cellContent}
                                    </td>`;
                  }

                   const calisilmasiGerekenGun = daysCount - disabledDaysCount;
                  let activeDaysLimit = calisilmasiGerekenGun;
                  
                  // Eğer görev geçmişi varsa, toplam gün limitine göre adjust et
                  if (p.gorev_gecmisi_var && p.gg_toplam_gun > 0) {
                    activeDaysLimit = Math.min(calisilmasiGerekenGun, parseInt(p.gg_toplam_gun));
                  }
                  
                  const toplamCalisma =
                    activeDaysLimit > 0
                      ? activeDaysLimit - unpaidCount
                      : 0;
                  bodyHtml += `<td class="sticky-col-right-1 toplam-calisma-gunu">${Math.max(0, toplamCalisma)}</td>`;
                  bodyHtml += "</tr>";
                });
                $("#table-body").html(bodyHtml);
                tableRows = document.querySelectorAll("#table-body tr");
                initTableEvents();
                applyFilter();
              }
            }
          } catch (e) {
            console.error("Tablo oluşturulurken hata:", e);
            showToast("Veriler işlenirken hata oluştu.", "error");
          }
        } else {
          showToast(res.message || "Veriler yüklenemedi.", "error");
        }
      },
      "json",
    )
      .fail(function (xhr) {
        console.error("Puantaj verisi yükleme hatası:", xhr.responseText);
        showToast("Sunucu hatası oluştu.", "error");
      })
      .always(function () {
        $("#puantaj-loader").fadeOut(200);
        calculateTotals();
      });
  }

  function calculateTotals() {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();
    const daysCount = parseInt(getDaysInMonth(ay, yil));

    let secilenPersonelSayisi = 0;
    let totalGenelCalisma = 0;
    
    // Day-based data storage for footer
    let dayStats = Array.from({ length: daysCount + 1 }, () => ({
      totalEntries: 0,
      typeCounts: {}
    }));

    // Single pass over visible rows (Vanilla JS for speed)
    const rows = tableRows;
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.style.display === "none") continue;

      secilenPersonelSayisi++;

      // Toplam çalışma gününü al (satır sonundaki hücre)
      const totalCell = row.querySelector(".toplam-calisma-gunu");
      if (totalCell) {
        totalGenelCalisma += parseInt(totalCell.textContent) || 0;
      }

      // Sadece has-entry olan hücreleri tara
      const cells = row.querySelectorAll(".day-cell.has-entry");
      for (let j = 0; j < cells.length; j++) {
        const cell = cells[j];
        const dateStr = cell.getAttribute("data-date");
        if (!dateStr) continue;

        const d = parseInt(dateStr.split("-")[2]);
        if (d < 1 || d > daysCount) continue;

        const content = cell.querySelector(".cell-content");
        if (content) {
          const shortcode = content.getAttribute("data-shortcode");
          const name = content.getAttribute("data-name");
          const idStr = content.getAttribute("data-id");

          if (!dayStats[d].typeCounts[shortcode]) {
            dayStats[d].typeCounts[shortcode] = {
              count: 0,
              name: name,
              typeId: idStr,
            };
          }
          dayStats[d].typeCounts[shortcode].count++;
          dayStats[d].totalEntries++;
        }
      }
    }

    let footerHtml = `<tr>
            <td class="sticky-col px-3" style="display: table-cell; vertical-align: middle;">
                <div class="d-flex w-100 justify-content-between align-items-center">
                <span>Toplam Personel:</span>
                <span class="fw-bold">${secilenPersonelSayisi}</span>
                </div>
            </td>`;

    for (let d = 1; d <= daysCount; d++) {
      const stats = dayStats[d];
      let tooltipText = "";
      Object.keys(stats.typeCounts).forEach(function (code) {
        tooltipText += `${stats.typeCounts[code].name}(${code}) : ${stats.typeCounts[code].count}<br>`;
      });

      const dateObj = new Date(yil, ay - 1, d);
      const isSunday = dateObj.getDay() === 0;
      const sundayClass = isSunday ? "is-sunday" : "";

      if (stats.totalEntries > 0) {
        footerHtml += `<td class="text-center ${sundayClass} position-relative">
                <span tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="top" data-bs-container="body" title="İzin Dağılımı" data-bs-content="${tooltipText}" style="cursor:help;">${stats.totalEntries}</span>
           </td>`;
      } else {
        footerHtml += `<td class="text-center ${sundayClass}">0</td>`;
      }
    }

    footerHtml += `<td class="sticky-col-right-1 text-center">${totalGenelCalisma}</td>`;
    footerHtml += `</tr>`;

    $("#table-footer").html(footerHtml);
  }

  function initPopovers() {
    // Already handled by delegated global initializer
  }

  // Table Interaction Events
  function initTableEvents() {
    // Selection and range extension disabled as per user request
    $(".day-cell").off("mousedown mouseenter mouseup mousemove");

    // Table cell click event for single day addition
    $(".day-cell")
      .off("click")
      .on("click", function (e) {
        if (!selectedType || $(this).hasClass("disabled")) return;

        applyLeaveToCell(this, selectedType);
      });

    // Native Drag and Drop Implementation
    let draggedType = null;
    let draggedSourceKey = null;

    $(document)
      .off("dragstart", ".draggable-izin")
      .on("dragstart", ".draggable-izin", function (e) {
        const $el = $(this);
        draggedType = {
          id: $el.data("id"),
          shortCode: $el.data("shortcode"),
          name: $el.data("name"),
          color: $el.data("color"),
        };

        const $cell = $el.closest(".day-cell");
        if ($cell.length) {
          draggedSourceKey = `${$cell.data("personel-id")}-${$cell.data("date")}`;
        } else {
          draggedSourceKey = null;
        }

        e.originalEvent.dataTransfer.effectAllowed = "copyMove";
        // Hide tooltip
        $(".tooltip").remove();

        // Let the ghost image look normal
        setTimeout(() => {
          $el.css("opacity", "0.5");
        }, 0);
      });

    $(document)
      .off("dragend", ".draggable-izin")
      .on("dragend", ".draggable-izin", function (e) {
        $(this).css("opacity", "1");
        draggedType = null;
        draggedSourceKey = null;
        $(".day-cell").removeClass("drag-over");
      });

    $(document)
      .off("dragover", ".day-cell")
      .on("dragover", ".day-cell", function (e) {
        e.preventDefault();
        if (!draggedType) return;
        if (!$(this).hasClass("disabled")) {
          e.originalEvent.dataTransfer.dropEffect = "copy";
          $(this).addClass("drag-over");
        }
      });

    $(document)
      .off("dragenter", ".day-cell")
      .on("dragenter", ".day-cell", function (e) {
        e.preventDefault();
        if (!draggedType || $(this).hasClass("disabled")) return;
        $(this).addClass("drag-over");
      });

    $(document)
      .off("dragleave", ".day-cell")
      .on("dragleave", ".day-cell", function (e) {
        $(this).removeClass("drag-over");
      });

    $(document)
      .off("drop", ".day-cell")
      .on("drop", ".day-cell", function (e) {
        e.preventDefault();
        $(this).removeClass("drag-over");
        if (!draggedType || $(this).hasClass("disabled")) return;

        const pId = $(this).data("personel-id");
        const date = $(this).data("date");
        const targetKey = `${pId}-${date}`;

        if (draggedSourceKey === targetKey) return;
        applyLeaveToCell(this, draggedType);
      });

    // Right-click Context Menu Logic
    $(".day-cell").on("contextmenu", function (e) {
      e.preventDefault();
      if ($(this).hasClass("disabled")) return;

      const cell = $(this);
      const x = e.clientX;
      const y = e.clientY;

      const contextMenu = $("#custom-context-menu");
      const menuItemsContainer = $("#context-menu-items");
      menuItemsContainer.empty();

      // Determine active list (Paid/Unpaid)
      const isUcretliActive =
        $("#ucretli-tab").hasClass("active") ||
        $("#ucretli-list").is(":visible");

      const definitions = isUcretliActive
        ? ucretliDefinitions
        : ucretsizDefinitions;

      const pId = cell.data("personel-id");
      const date = cell.data("date");
      const key = `${pId}-${date}`;

      // Check current cell state
      const unsaved = unsavedChanges[key];
      const hasContent = cell.find(".cell-content").length > 0;
      const currentTypeId = unsaved
        ? (unsaved.type_id || unsaved.typeId)?.toString()
        : cell.find(".cell-content").data("id")?.toString();

      // Header text
      const personnelName = cell
        .closest("tr")
        .find(".text-truncate-name")
        .text();
      $("#menu-header-text").text(personnelName + " - " + date);

      // Add leave types to menu
      definitions.forEach((item) => {
        const shortCode = getShortCode(item);
        const isActive = currentTypeId === item.id.toString();
        const activeClass = isActive ? "active" : "";

        const style = getStyleFromTailwind(item.renk);
        const itemHtml = `
          <div class="menu-item ${activeClass}" data-id="${item.id}" data-shortcode="${shortCode}" data-name="${item.tur_adi}" data-color="${item.renk}">
            <div class="menu-item-code" style="background-color: ${style.bg} !important; color: ${style.color} !important; border: 1px solid ${style.color}33;">
              ${shortCode}
            </div>
            <span>${item.tur_adi}</span>
          </div>
        `;
        menuItemsContainer.append(itemHtml);
      });

      // Position and show menu
      contextMenu.css({
        display: "block",
        left: x + "px",
        top: y + "px",
      });

      // Handle window boundaries
      const menuWidth = contextMenu.outerWidth();
      const menuHeight = contextMenu.outerHeight();
      const windowWidth = $(window).width();
      const windowHeight = $(window).height();

      if (x + menuWidth > windowWidth)
        contextMenu.css("left", windowWidth - menuWidth - 10 + "px");
      if (y + menuHeight > windowHeight)
        contextMenu.css("top", windowHeight - menuHeight - 10 + "px");

      // Store cell reference
      contextMenu.data("target-cell", cell);

      // Disable delete button if no entry
      if (!hasContent) {
        $("#menu-item-delete").addClass("d-none");
      } else {
        $("#menu-item-delete").removeClass("d-none");
      }
    });

    // Menu Item Clicks
    $(document)
      .off("click", "#context-menu-items .menu-item")
      .on("click", "#context-menu-items .menu-item", function () {
        const contextMenu = $("#custom-context-menu");
        const cell = contextMenu.data("target-cell");
        const item = $(this).data();

        // Apply selected leave
        applyLeaveToCell(cell[0], {
          id: item.id,
          name: item.name,
          color: item.color,
          shortCode: item.shortcode,
        });

        contextMenu.hide();
      });

    $("#menu-item-delete")
      .off("click")
      .on("click", function () {
        const contextMenu = $("#custom-context-menu");
        const cell = contextMenu.data("target-cell");
        const pId = cell.data("personel-id");
        const date = cell.data("date");
        const key = `${pId}-${date}`;

        const content = cell.find(".cell-content");
        if (content.data("is-default") === true) {
          contextMenu.hide();
          return;
        }

        const isUnsaved = cell.hasClass("unsaved");

        if (isUnsaved) {
          removeUnsaved(key, {
            target: cell.find(".btn-delete-cell")[0],
            stopPropagation: () => {},
          });
        } else {
          const delBtn = cell.find(".btn-delete-cell")[0];
          if (delBtn) {
            delBtn.click();
          }
        }

        contextMenu.hide();
      });

    // Close menu on click outside
    $(document).on("click mousedown", function (e) {
      if (!$(e.target).closest("#custom-context-menu").length) {
        $("#custom-context-menu").hide();
      }
    });
  }

  function applyLeaveToCell(cell, type) {
    const row = cell.closest("tr");
    const pId = cell.dataset.personelId;
    const date = cell.dataset.date;
    const key = `${pId}-${date}`;

    // Add to unsaved changes
    unsavedChanges[key] = {
      personel_id: pId,
      date: date,
      type_id: type.id,
      name: type.name,
      color: type.color,
      shortCode: type.shortCode,
    };
    // Update UI instantly using Vanilla JS (Much faster than jQuery)
    const styleObj = getStyleFromTailwind(type.color);
    cell.style.cssText = "";
    cell.className = `day-cell has-entry unsaved ${cell.classList.contains("is-sunday") ? "is-sunday" : ""}`;
    cell.innerHTML = `
      <div class="cell-content draggable-izin" draggable="true"
           data-bs-toggle="tooltip" 
           title="${type.name}" 
           data-id="${type.id}" 
           data-name="${type.name}" 
           data-color="${type.color}" 
           data-shortcode="${type.shortCode}"
           style="background-color: ${styleObj.bg}; color: ${styleObj.color}; border: 1px solid ${styleObj.color}4D;">
          ${type.shortCode}
          <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
      </div>`;

    updateRowTotals(row);
  }

  let calculateTotalsTimer = null;
  function updateRowTotals(rowOrId) {
    const row = typeof rowOrId === "object" ? rowOrId : document.querySelector(`.personel-info[data-personel-id="${rowOrId}"]`)?.closest("tr");
    if (!row) return;

    let unpaidCount = 0;
    const dayCells = row.querySelectorAll(".day-cell");
    let disabledCount = 0;

    for (let i = 0; i < dayCells.length; i++) {
      const cell = dayCells[i];
      if (cell.classList.contains("disabled")) {
        disabledCount++;
        continue;
      }

      const content = cell.querySelector(".cell-content");
      if (content) {
        const typeId = content.getAttribute("data-id");
        const isDefault = content.getAttribute("data-is-default") === "true";
        if (!isDefault && typeId && ucretsizIzinIds.has(typeId)) {
          unpaidCount++;
        }
      }
    }

    const totalCells = dayCells.length;
    const activeDays = Math.max(0, totalCells - disabledCount);
    
    // Görev geçmişi limitini uygula
    const personelCell = row.querySelector(".personel-info");
    const gorevGecmisiVar = parseInt(personelCell.getAttribute("data-gorev-gecmisi")) || 0;
    const ggToplamGun = parseInt(personelCell.getAttribute("data-gg-toplam-gun")) || 0;
    
    let activeDaysLimit = activeDays;
    if (gorevGecmisiVar && ggToplamGun > 0) {
      activeDaysLimit = Math.min(activeDays, ggToplamGun);
    }
    
    const toplamCalisma = activeDaysLimit > 0 ? activeDaysLimit - unpaidCount : 0;
    const totalDisplayCell = row.querySelector(".toplam-calisma-gunu");
    if (totalDisplayCell) {
        totalDisplayCell.textContent = Math.max(0, toplamCalisma);
    }

    // Satır toplamı değiştiyse genel toplamı da güncelle
    if (calculateTotalsTimer) clearTimeout(calculateTotalsTimer);
    calculateTotalsTimer = setTimeout(() => {
      calculateTotals();
    }, 150);
  }

  window.removeUnsaved = function (key, e) {
    const $cell = $(e.target).closest(".day-cell");
    const pId = $cell.data("personel-id");

    if (e) e.stopPropagation();
    delete unsavedChanges[key];

    // UI'yı anlık temizle (Tabloyu yenilemeden)
    $cell.empty().removeClass("has-entry unsaved").attr("style", "");

    // Tooltip varsa temizle
    $(".tooltip").remove();

    updateRowTotals(pId);
  };

  function clearSelection() {
    $(".day-cell").removeClass("selected");
  }

  /**
   * Date objesini YYYY-MM-DD formatına çevirir
   */
  function formatDateISO(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  $("#btn-save-selected")
    .off("click")
    .on("click", function () {
      const changesCount = Object.keys(unsavedChanges).length;
      if (changesCount === 0) {
        showToast("Kaydedilecek yeni bir değişiklik yok.", "info");
        return;
      }

      // Değişiklikleri kaydet (Grup yapmadan, tekil gün olarak gönderiyoruz)
      const rawData = Object.values(unsavedChanges);
      const groupedData = rawData;

      // Excel'den yüklenen personel ID'lerini de gönder (mevcut kayıtları silmek için)
      const excelPersonnelIds = Array.from(excelReplacedPersonnel);

      console.log("Kaydedilecek veriler:", groupedData);
      console.log("Excel personelleri:", excelPersonnelIds);
      saveBulkEntries(groupedData, excelPersonnelIds);
    });

  // API Calls
  function saveBulkEntries(data, excelPersonnelIds = []) {
    // Optimistic Update: Önce UI'ı güncelle
    $(".day-cell.unsaved").each(function () {
      $(this).removeClass("unsaved");
      // Silme butonunu güncelle (artık veritabanından silecek)
      const $deleteBtn = $(this).find(".btn-delete-cell");
      if ($deleteBtn.length) {
        // Entry id henüz yok, ama kayıt olduktan sonra olacak
        // Şimdilik butonu kaldır, tam yenilemede gelecek
        $deleteBtn.remove();
      }
    });

    // Kayıtları hemen temizle
    const savedChanges = { ...unsavedChanges };
    const savedExcelPersonnel = new Set(excelReplacedPersonnel);
    unsavedChanges = {};
    excelReplacedPersonnel.clear();

    let savingToast = showToast("Kaydediliyor...", "info");
    if (typeof Pace !== "undefined") Pace.restart();

    // Ay ve yıl bilgisini de gönder (Excel personellerinin eski kayıtlarını silmek için)
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    $.post(
      API_URL,
      {
        action: "save-bulk-entries",
        data: JSON.stringify(data),
        excelPersonnelIds: JSON.stringify(excelPersonnelIds),
        ay: ay,
        yil: yil,
      },
      function (res) {
        if (res.status === "success") {
          if (savingToast && typeof savingToast.hideToast === "function") {
            savingToast.hideToast();
          }
          showToast("İşlem Başarılı!!", "success");
          // Sadece silme butonlarını güncellemek için sessiz yenileme
          setTimeout(() => renderTable(), 500);
        } else {
          if (savingToast && typeof savingToast.hideToast === "function") {
            savingToast.hideToast();
          }
          // Hata olursa kayıtları geri getir
          unsavedChanges = savedChanges;
          excelReplacedPersonnel = savedExcelPersonnel;
          showToast(res.message, "error");
          renderTable();
        }
      },
    ).fail(function () {
      if (savingToast && typeof savingToast.hideToast === "function") {
        savingToast.hideToast();
      }
      // Bağlantı hatası durumunda kayıtları geri getir
      unsavedChanges = savedChanges;
      excelReplacedPersonnel = savedExcelPersonnel;
      showToast("Bağlantı hatası oluştu.", "error");
      renderTable();
    });
  }

  function saveEntry(personel_ids, dates, type_id) {
    $.post(
      API_URL,
      {
        action: "save-entry",
        personel_ids,
        dates,
        type_id,
      },
      function (res) {
        if (res.status === "success") {
          renderTable();
          clearSelection();
          showToast("İşlem başarıyla kaydedildi.", "success");
        } else {
          showToast(res.message, "error");
        }
      },
    );
  }

  function showToast(message, icon = "success") {
    if (typeof Toastify !== "undefined") {
      let bgColor = "#34c38f"; // Success
      if (icon === "error") bgColor = "#f46a6a"; // Error
      if (icon === "warning") bgColor = "#f1b44c"; // Warning
      if (icon === "info") bgColor = "#50a5f1"; // Info

      const safeMsg = typeof message === 'string' ? message : String(message || '');

      return Toastify({
        text: safeMsg,
        duration: 3000,
        gravity: "top",
        position: "center",
        style: {
          background: bgColor,
          borderRadius: "6px",
        },
      }).showToast();
    } else if (typeof toastr !== "undefined") {
      toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: "3000",
      };
      const type =
        icon === "success"
          ? "success"
          : icon === "warning"
            ? "warning"
            : "error";
      toastr[type](message);
    } else {
      Swal.fire({
        icon: icon,
        title: message,
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
  }

  function initTooltips() {
    // Already handled by delegated global initializer
  }

  // Global delete
  window.deleteEntry = function (id, e) {
    if (e) e.stopPropagation();

    const $btn = $(e.target);
    const $cell = $btn.closest(".day-cell");
    const originalHtml = $cell.html();
    const originalStyle = $cell.attr("style");
    const originalClass = $cell.attr("class");

    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu kayıt veritabanından tamamen silinecektir!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#34c38f",
      cancelButtonColor: "#f46a6a",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        // UI'yı anlık temizle (Optimistic Update)
        const pId = $cell.data("personel-id");
        $cell.empty().removeClass("has-entry unsaved").attr("style", "");
        $(".tooltip").remove();
        updateRowTotals(pId);

        // Pace başlasın ama loader'ı tüm sayfaya koyma
        if (typeof Pace !== "undefined") Pace.restart();

        $.post(API_URL, { action: "delete-entry", id }, function (res) {
          if (res.status === "success") {
            showToast("Kayıt veritabanından silindi.", "success");
            // renderTable() çağırmıyoruz, çünkü veriyi zaten temizledik
          } else {
            // Hata olursa UI'yı geri getir
            $cell
              .html(originalHtml)
              .attr("style", originalStyle)
              .attr("class", originalClass);
            showToast(res.message, "error");
          }
        }).fail(function () {
          // Bağlantı hatası durumunda geri getir
          $cell
            .html(originalHtml)
            .attr("style", originalStyle)
            .attr("class", originalClass);
          showToast("Bağlantı hatası oluştu.", "error");
        });
      }
    });
  };

  /**
   * Dinamik olarak sticky (sabit) başlıkların yüksekliklerini hesaplar.
   * İzin türleri alanı genişlediğinde tablonun üstte kalan kısmını ayarlar.
   */
  function updateStickyHeights() {
    const headerHeight = $(".puantaj-table-header").outerHeight() || 0;
    const izinTurleriHeight = $(".card-izin-turleri").outerHeight() || 0;

    // Normal modda sticky offsetleri
    if (!$("body").hasClass("puantaj-fullscreen")) {
      $(".card-izin-turleri").css("top", headerHeight + 70 + "px"); // 70px ana navbar tahmini
    } else {
      $(".card-izin-turleri").css("top", headerHeight + "px");
    }
  }

  // Pencere boyutu değiştiğinde yükseklikleri güncelle
  $(window).on("resize", updateStickyHeights);

  // =====================================================
  // KAYDEDİLMEMİŞ DEĞİŞİKLİK UYARI SİSTEMİ
  // =====================================================

  /**
   * Kaydedilmemiş değişiklik olup olmadığını kontrol eder
   */
  function hasUnsavedChanges() {
    return Object.keys(unsavedChanges).length > 0;
  }

  /**
   * Tarayıcı sekme kapatma / sayfa yenileme uyarısı
   * Not: Modern tarayıcılarda özel mesaj gösterilmez, standart uyarı görünür
   */
  $(window).on("beforeunload", function (e) {
    if (hasUnsavedChanges()) {
      const message =
        "Kaydedilmemiş değişiklikler var! Sayfadan ayrılmak istediğinize emin misiniz?";
      e.preventDefault();
      e.returnValue = message; // Chrome için gerekli
      return message;
    }
  });

  /**
   * Menü linkleri ve sayfa içi navigasyon kontrolü
   * Kullanıcı başka bir sayfaya gitmek istediğinde uyarı gösterir
   */
  $(document).on(
    "click",
    'a[href]:not([href="#"]):not([href^="#"]):not([data-bs-toggle]):not(.no-unsaved-check)',
    function (e) {
      // Kaydedilmemiş değişiklik var mı kontrol et
      if (!hasUnsavedChanges()) {
        return; // Değişiklik yoksa devam et
      }

      const targetUrl = $(this).attr("href");

      // Aynı sayfa içi link mi kontrol et
      if (
        targetUrl.startsWith("#") ||
        targetUrl.startsWith("javascript:") ||
        $(this).attr("target") === "_blank"
      ) {
        return; // Bu tür linkleri kontrol etme
      }

      // Sayfadan ayrılmayı engelle ve uyarı göster
      e.preventDefault();

      Swal.fire({
        title: "Kaydetmediğiniz Değişiklikler Var!",
        html: `
          <div class="mb-2">Tabloda yaptığınız değişiklikleri henüz sisteme kaydetmediniz.</div>
          <div class="text-muted small">Eğer sayfadan ayrılırsanız bu değişiklikler <b>silinecektir!</b>.</div>
        `,
        icon: "warning",
        customClass: {
          popup: "swal-unsaved-popup",
        },
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonColor: "#34c38f",
        denyButtonColor: "#f46a6a",
        cancelButtonColor: "#f8f9fa", // Vazgeç için açık renk
        confirmButtonText:
          '<i class="mdi mdi-content-save me-1"></i> Kaydet ve Git',
        denyButtonText:
          '<i class="mdi mdi-delete-outline me-1"></i> Kaydetmeden Ayrıl',
        cancelButtonText: '<i class="mdi mdi-close me-1"></i> Vazgeç',
      }).then((result) => {
        if (result.isConfirmed) {
          // Kaydet ve sonra sayfaya git
          const rawData = Object.values(unsavedChanges);
          const groupedData = rawData;

          showToast("Kayıtlar kaydediliyor...", "info");

          $.post(
            API_URL,
            {
              action: "save-bulk-entries",
              data: JSON.stringify(groupedData),
            },
            function (res) {
              if (res.status === "success") {
                unsavedChanges = {}; // Temizle
                showToast("Kayıtlar başarıyla kaydedildi!", "success");
                // Kısa bir gecikme ile sayfaya yönlendir
                setTimeout(function () {
                  window.location.href = targetUrl;
                }, 500);
              } else {
                showToast(
                  res.message || "Kayıt sırasında hata oluştu.",
                  "error",
                );
              }
            },
          ).fail(function () {
            showToast("Bağlantı hatası oluştu.", "error");
          });
        } else if (result.isDenied) {
          // Kaydetmeden ayrıl
          unsavedChanges = {}; // beforeunload'u tetiklememesi için temizle
          window.location.href = targetUrl;
        }
        // Cancel: Sayfada kal - hiçbir şey yapma
      });
    },
  );

  /**
   * Sidebar menü linkleri için özel kontrol
   * (ID bazlı menü sistemi için)
   */
  $(document).on("click", "#sidebar-menu a", function (e) {
    if (!hasUnsavedChanges()) {
      return;
    }

    const $link = $(this);
    const href = $link.attr("href");

    // Submenu toggle linkleri için atla
    if (!href || href === "#" || $link.hasClass("has-arrow")) {
      return;
    }

    // Ana event handler'ın çalışmasına izin ver
    // (Yukarıdaki genel handler çalışacak)
  });

  // =====================================================
  // SGK İŞLEMLERİ
  // =====================================================

  // Onaylanmış Raporları Getir
  $("#btn-sgk-onaylanmis-raporlar").on("click", function () {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    Swal.fire({
      title: "SGK Raporları Getiriliyor...",
      html: "Onaylanmış raporlar SGK'dan sorgulanıyor...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.post(
      API_URL,
      {
        action: "get-sgk-onaylanmis-raporlar",
        ay: ay,
        yil: yil,
      },
      function (res) {
        Swal.close();

        if (res.status === "success") {
          if (res.data.length === 0) {
            Swal.fire({
              icon: "info",
              title: "Rapor Bulunamadı",
              text: "Seçilen dönemde onaylanmış SGK raporu bulunamadı.",
            });
            return;
          }

          showSgkRaporModal(
            res.data,
            "Onaylanmış SGK Raporları",
            res.toplam,
            res.eslesen,
          );
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: res.message || "SGK raporları getirilemedi.",
          });
        }
      },
    ).fail(function () {
      Swal.fire({
        icon: "error",
        title: "Bağlantı Hatası",
        text: "SGK sunucusuna bağlanılamadı.",
      });
    });
  });

  // Onay Bekleyen Raporları Getir
  $("#btn-sgk-onay-bekleyen-raporlar").on("click", function () {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    Swal.fire({
      title: "SGK Raporları Getiriliyor...",
      html: "Onay bekleyen raporlar SGK'dan sorgulanıyor...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.post(
      API_URL,
      {
        action: "get-sgk-onay-bekleyen-raporlar",
        ay: ay,
        yil: yil,
      },
      function (res) {
        Swal.close();

        if (res.status === "success") {
          if (res.data.length === 0) {
            Swal.fire({
              icon: "info",
              title: "Rapor Bulunamadı",
              text: "Seçilen dönemde onay bekleyen SGK raporu bulunamadı.",
            });
            return;
          }

          showSgkRaporModal(
            res.data,
            "Onay Bekleyen SGK Raporları",
            res.toplam,
            res.eslesen,
          );
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: res.message || "SGK raporları getirilemedi.",
          });
        }
      },
    ).fail(function () {
      Swal.fire({
        icon: "error",
        title: "Bağlantı Hatası",
        text: "SGK sunucusuna bağlanılamadı.",
      });
    });
  });

  /**
   * SGK Rapor Modal - Raporları göster ve işleme al
   */
  function showSgkRaporModal(raporlar, title, toplam, eslesen) {
    $("#sgkRaporModalLabel").text(title);

    let tableHtml = `
      <div class="px-4 py-3 bg-soft-primary border-bottom border-light">
          <div class="row align-items-center">
              <div class="col-md-8">
                  <div class="d-flex align-items-center gap-3">
                      <div class="d-flex flex-column">
                          <span class="text-muted small fw-medium">Sorgulama Sonucu</span>
                          <div class="d-flex align-items-center gap-2">
                              <span class="badge rounded-pill bg-primary px-3">Toplam: ${toplam} Rapor</span>
                              <span class="badge rounded-pill bg-success px-3">Eşleşen: ${eslesen} Personel</span>
                          </div>
                      </div>
                      <div class="vr mx-2" style="height: 30px; opacity: 0.1;"></div>
                      <div class="text-muted small">
                          <i class="mdi mdi-information-outline text-info me-1"></i>
                          Seçilen raporlar puantaja <b>RP</b> olarak işlenecektir.
                      </div>
                  </div>
              </div>
              <div class="col-md-4 text-end">
                  <div class="form-check form-switch d-inline-flex align-items-center gap-2 bg-white px-3 py-2 rounded-pill shadow-sm">
                      <label class="form-check-label small fw-bold text-dark cursor-pointer" for="sgk-select-all">Tümünü Seç</label>
                      <input class="form-check-input ms-0 cursor-pointer" type="checkbox" id="sgk-select-all" checked>
                  </div>
              </div>
          </div>
      </div>
      <div class="table-responsive" style="max-height: calc(100vh - 350px);">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-primary text-white sticky-top">
            <tr>
              <th class="px-4 text-center text-white" style="width: 60px;">#</th>
              <th class="text-white">Personel</th>
              <th class="text-white">SGK Sistemindeki Ad</th>
              <th class="text-white">Vaka Türü</th>
              <th class="text-center text-white">Başlangıç</th>
              <th class="text-center text-white">İş başı tarihi</th>
              <th class="text-center text-white">Gün</th>
              <th class="text-end px-4 text-white">Durum</th>
            </tr>
          </thead>
          <tbody>
    `;

    raporlar.forEach((rapor, index) => {
      const isEslesti = !!rapor.eslesti;
      const rowClass = isEslesti ? "" : "table-light opacity-75";
      const badgeClass = isEslesti ? "bg-success" : "bg-warning";
      const statusIcon = isEslesti
        ? "mdi-check-decagram"
        : "mdi-alert-decagram-outline";
      const statusText = isEslesti ? "Eşleşti" : "Eşleşmedi";

      const baslangicDisplay = rapor.baslangic_raw || rapor.baslangic || "-";
      const bitisDisplay = rapor.bitis_raw || rapor.bitis || "-";

      tableHtml += `
        <tr class="${rowClass} ${!isEslesti ? "text-muted" : ""}">
          <td class="text-center px-4">
            <div class="form-check d-flex justify-content-center">
                <input type="checkbox" class="form-check-input sgk-rapor-check cursor-pointer" 
                       data-index="${index}" 
                       ${isEslesti ? "checked" : "disabled"} 
                       style="width: 1.2rem; height: 1.2rem;">
            </div>
          </td>
          <td>
            ${
              isEslesti
                ? `<div>
                    <div class="fw-bold text-dark">${rapor.personel_adi}</div>
                    <div class="text-muted small">${rapor.tc_kimlik || ""}</div>
                   </div>`
                : `<span class="badge badge-danger text-white px-2 py-1">Eşleşen Kayıt Yok</span>`
            }
          </td>
          <td>
            <div class="fw-medium">${rapor.ad_soyad}</div>
          </td>
          <td>
            <span class="badge badge-info text-white px-2 py-1">
                <i class="mdi mdi-medical-bag me-1"></i>${rapor.vaka_adi}
            </span>
          </td>
          <td class="text-center fw-medium text-dark">${baslangicDisplay}</td>
          <td class="text-center fw-medium text-dark">${bitisDisplay}</td>
          <td class="text-center fw-bold text-primary">${rapor.toplam_gun || 0}</td>
          <td class="text-end px-4">
            <span class="badge ${badgeClass} bg-opacity-10 text-${badgeClass.replace("bg-", "")} px-2 py-1">
                <i class="mdi ${statusIcon} me-1"></i>${statusText}
            </span>
          </td>
        </tr>
      `;
    });

    tableHtml += `
          </tbody>
        </table>
      </div>
    `;

    $("#sgkRaporModalBody").html(tableHtml);

    // Bootstrap Modal'ı göster
    const modalEl = document.getElementById("sgkRaporModal");
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Event Listeners
    const updateButtonState = () => {
      const checkedCount = $(".sgk-rapor-check:checked").length;
      $("#btn-sgk-rapor-onayla").prop("disabled", checkedCount === 0);
    };

    // İlk açılışta buton durumunu ayarla
    updateButtonState();

    $("#sgk-select-all")
      .off("change")
      .on("change", function () {
        $(".sgk-rapor-check:not(:disabled)").prop(
          "checked",
          $(this).is(":checked"),
        );
        updateButtonState();
      });

    $(document)
      .off("change", ".sgk-rapor-check")
      .on("change", ".sgk-rapor-check", function () {
        const total = $(".sgk-rapor-check:not(:disabled)").length;
        const checked = $(".sgk-rapor-check:not(:disabled):checked").length;
        $("#sgk-select-all").prop("checked", total === checked && total > 0);
        updateButtonState();
      });

    $("#btn-sgk-rapor-onayla")
      .off("click")
      .on("click", function () {
        const secilenler = [];
        $(".sgk-rapor-check:checked").each(function () {
          const index = $(this).data("index");
          const rapor = raporlar[index];

          // Güvenlik Kontrolü: Sadece eşleşmiş kayıtları al (Hacker-proof)
          if (rapor && rapor.eslesti) {
            secilenler.push(rapor);
          }
        });

        if (secilenler.length === 0) {
          showToast(
            "İşlem yapılabilecek geçerli bir rapor seçilmedi.",
            "error",
          );
          updateButtonState();
          return;
        }

        modal.hide();
        isleSgkRaporlari(secilenler);
      });
  }

  /**
   * Seçilen SGK raporlarını puantaja işle
   */
  function isleSgkRaporlari(raporlar) {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    Swal.fire({
      title: "İşleniyor...",
      html: `<b>${raporlar.length}</b> rapor seçilen dönem (<b>${ay}/${yil}</b>) için puantaja işleniyor...`,
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.post(
      API_URL,
      {
        action: "sgk-raporlari-isle",
        raporlar: JSON.stringify(raporlar),
        ay: ay,
        yil: yil,
      },
      function (res) {
        if (res.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: res.message,
          }).then(() => {
            renderTable(); // Tabloyu yenile
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: res.message || "Raporlar işlenirken bir hata oluştu.",
          });
        }
      },
    ).fail(function () {
      Swal.fire({
        icon: "error",
        title: "Bağlantı Hatası",
        text: "Raporlar işlenirken bağlantı hatası oluştu.",
      });
    });
  }

  // =====================================================
  // EXCEL IMPORT & TEMPLATE (MODAL VERSION)
  // =====================================================

  // Modal Açma
  $("#btn-open-excel-modal").on("click", function () {
    const modal = new bootstrap.Modal(
      document.getElementById("excelImportModal"),
    );
    modal.show();
  });

  // Şablon İndir / Excele Aktar - Mevcut verileri export eder
  $("#btn-download-template-modal, #btn-export-excel").on("click", function () {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    // Sunucu tarafında PHPSpreadsheet ile oluşturup indir
    window.location.href = `${API_URL}?action=export-excel&ay=${ay}&yil=${yil}`;
  });

  // Modal içindeki Yükle Butonu (Dosya seçimi ve okuma aynı kalabilir veya o da taşınabilir ama şimdilik export isteniyor)

  // Modal içindeki Yükle Butonu
  $("#btn-import-excel-submit").on("click", function () {
    const fileInput = document.getElementById("import-excel-file-modal");
    const file = fileInput.files[0];

    if (!file) {
      showToast("Lütfen bir Excel dosyası seçin.", "error");
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      try {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: "array" });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const jsonData = XLSX.utils.sheet_to_json(worksheet);

        processExcelData(jsonData);

        // Modal'ı kapat ve temizle
        const modalEl = document.getElementById("excelImportModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        fileInput.value = "";
      } catch (err) {
        console.error("Excel okuma hatası:", err);
        showToast("Excel dosyası okunamadı.", "error");
      }
    };
    reader.onerror = function () {
      showToast("Dosya okuma hatası oluştu.", "error");
    };
    reader.readAsArrayBuffer(file);
  });

  function processExcelData(data) {
    if (!data || data.length === 0) {
      showToast("Excel dosyası boş veya geçersiz.", "error");
      return;
    }

    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    let successCount = 0;
    let failCount = 0;

    // Puantaj formatı: TC Kimlik No veya Personel sütunu, sonraki sütunlar gün numaraları (1, 2, 3, ...)
    data.forEach((row) => {
      // TC Kimlik No veya Personel sütununu al
      const tcKimlik = (row["TC Kimlik No"] || row["TC"] || "")
        .toString()
        .trim();
      const personelAdi = (row["Personel"] || row["Ad Soyad"] || "")
        .toString()
        .trim();

      // Önce TC ile eşleştir, yoksa isimle
      let person = null;
      if (tcKimlik) {
        person = personnelMap[tcKimlik];
      }
      if (!person && personelAdi) {
        person = personnelMap[turkceKucukHarf(personelAdi)];
      }

      if (!person) {
        failCount++;
        return;
      }

      // Bu personeli Excel'den yüklenen olarak işaretle (mevcut kayıtları gizlemek için)
      excelReplacedPersonnel.add(person.id.toString());

      // Gün sütunlarını döngüyle oku
      Object.keys(row).forEach((key) => {
        // Sadece sayısal gün sütunlarını işle
        const dayNum = parseInt(key, 10);
        if (isNaN(dayNum) || dayNum < 1 || dayNum > 31) return;

        const code = (row[key] || "").toString().trim().toUpperCase();
        if (!code) return; // Boş hücreleri atla

        const definition = definitionsMap[code];
        if (!definition) {
          failCount++;
          return;
        }

        // Tarihi oluştur
        const dateStr = `${yil}-${ay}-${dayNum.toString().padStart(2, "0")}`;
        const currentDate = new Date(yil, ay - 1, dayNum);

        // İşe giriş kontrolü - Giriş tarihinden önceki günleri engelle
        if (
          person.ise_giris_tarihi &&
          person.ise_giris_tarihi !== "0000-00-00" &&
          person.ise_giris_tarihi !== null
        ) {
          const girisParts = person.ise_giris_tarihi.split("-");
          const girisDate = new Date(
            parseInt(girisParts[0]),
            parseInt(girisParts[1]) - 1,
            parseInt(girisParts[2]),
          );
          if (currentDate < girisDate) return;
        }

        // İşten çıkış kontrolü - Çıkış tarihinden sonraki günleri engelle
        if (
          person.isten_cikis_tarihi &&
          person.isten_cikis_tarihi !== "0000-00-00" &&
          person.isten_cikis_tarihi !== null
        ) {
          const cikisParts = person.isten_cikis_tarihi.split("-");
          const cikisDate = new Date(
            parseInt(cikisParts[0]),
            parseInt(cikisParts[1]) - 1,
            parseInt(cikisParts[2]),
          );
          if (currentDate > cikisDate) return;
        }

        const dateKey = `${person.id}-${dateStr}`;

        unsavedChanges[dateKey] = {
          personel_id: person.id,
          date: dateStr,
          type_id: definition.id,
          name: definition.name,
          color: definition.color,
          shortCode: definition.shortCode,
        };
        successCount++;
      });
    });

    if (successCount > 0) {
      showToast(`${successCount} adet kayıt yüklendi.`, "success");
      renderTable();
    } else {
      Swal.fire({
        icon: "warning",
        title: "Kayıt Yüklenemedi",
        text: "Eşleşen personel veya izin kodu bulunamadı.",
        footer:
          "<b>İpucu:</b> Personel adlarının ve İzin Kodlarının (MI, RP vb.) doğruluğundan emin olun.",
      });
    }
  }
});
