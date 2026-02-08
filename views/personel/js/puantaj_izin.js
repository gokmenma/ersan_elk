$(document).ready(function () {
  const API_URL = "views/personel/api/puantaj_izin.php";
  let selectedType = null;
  let unsavedChanges = {}; // { 'personelId-date': { typeId, name, color, shortCode } }
  let definitionsMap = {}; // { shortCode: { id, name, color, shortCode } }
  let personnelMap = {}; // { nameOrTc: personObj }
  let excelReplacedPersonnel = new Set(); // Excel'den yüklenen personellerin ID'leri

  // Initial Load from LocalStorage or default
  const savedAy = localStorage.getItem("puantaj_ay");
  const savedYil = localStorage.getItem("puantaj_yil");
  const savedFilter = localStorage.getItem("puantaj_filter") || "";

  if (savedAy) $("#select-ay").val(savedAy);
  if (savedYil) $("#select-yil").val(savedYil);
  if (savedFilter) $("#personel-filter").val(savedFilter);

  // Initialize Select2 if not already initialized
  if ($.fn.select2) {
    $(".select2").select2();
  }

  loadDefinitions();
  renderTable();

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
    const value = turkceKucukHarf($("#personel-filter").val());
    $("#table-body tr").each(function () {
      const rowText = turkceKucukHarf($(this).text());
      $(this).toggle(rowText.indexOf(value) > -1);
    });
  }

  $("#personel-filter").on("keyup", function () {
    localStorage.setItem("puantaj_filter", $(this).val());
    applyFilter();
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

  // Helper: Get style from tailwind class
  function getStyleFromTailwind(tailwindClass) {
    if (!tailwindClass)
      return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };

    // Check if it's already a hex
    if (tailwindClass.startsWith("#")) {
      // Return light version of hex for background, original for text
      return {
        bg: tailwindClass + "26", // 15% opacity hex
        color: tailwindClass,
      };
    }

    if (tailwindClass.includes("blue"))
      return { bg: "#dbeafe", color: "#2563eb" };
    if (tailwindClass.includes("amber"))
      return { bg: "#fef3c7", color: "#d97706" };
    if (tailwindClass.includes("red"))
      return { bg: "#fee2e2", color: "#dc2626" };
    if (tailwindClass.includes("pink"))
      return { bg: "#fce7f3", color: "#db2777" };
    if (tailwindClass.includes("gray"))
      return { bg: "#f3f4f6", color: "#4b5563" };
    if (tailwindClass.includes("green"))
      return { bg: "#dcfce7", color: "#16a34a" };
    if (tailwindClass.includes("purple"))
      return { bg: "#f3e8ff", color: "#9333ea" };

    // Default to primary theme color (light style)
    return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };
  }

  // Load Definitions
  function loadDefinitions() {
    $.post(API_URL, { action: "get-definitions" }, function (res) {
      if (res.status === "success") {
        const render = (list, containerId) => {
          let html = "";
          list.forEach((item) => {
            const style = getStyleFromTailwind(item.renk);
            const shortCode = getShortCode(item);
            html += `
                            <div class="izin-item-container draggable-izin" 
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
          initTooltips();

          // Sortable for Drag and Drop
          new Sortable(document.getElementById(containerId), {
            group: {
              name: "izinSharing",
              pull: "clone",
              put: false,
            },
            sort: false,
            animation: 150,
            ghostClass: "sortable-ghost",
            onStart: function () {
              $(".tooltip").remove();
            },
          });
        };
        if (res.data.ucretli) render(res.data.ucretli, "ucretli-list");
        if (res.data.ucretsiz) render(res.data.ucretsiz, "ucretsiz-list");

        // İzin türleri yüklendikten sonra sticky yüksekliği güncelle
        setTimeout(updateStickyHeights, 100);
      }
    });
  }

  // Render Table
  function renderTable() {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();
    const daysCount = getDaysInMonth(ay, yil);

    $("#puantaj-loader").fadeIn(200);

    // Header
    let headerHtml = '<th class="sticky-col">Personel</th>';
    for (let d = 1; d <= daysCount; d++) {
      const date = new Date(yil, ay - 1, d);
      const isSunday = date.getDay() === 0;
      const sundayClass = isSunday ? "is-sunday" : "";
      headerHtml += `<th class="${sundayClass}">${d}</th>`;
    }
    $("#table-header").html(headerHtml);

    // Body with data
    $.post(
      API_URL,
      { action: "get-calendar-data", ay, yil },
      function (res) {
        if (res.status === "success") {
          let bodyHtml = "";
          personnelMap = {}; // Clear previous personnel map
          if (res.data && Array.isArray(res.data)) {
            res.data.forEach((p) => {
              // Map personnel for Excel import
              if (p.adi_soyadi) personnelMap[turkceKucukHarf(p.adi_soyadi)] = p;
              if (p.tc_kimlik_no) personnelMap[p.tc_kimlik_no] = p;

              bodyHtml += `<tr>
                        <td class="personel-info sticky-col" title="${p.adi_soyadi}">
                            <div class="d-flex align-items-center">
                                <a href="?p=personel/manage&id=${p.encrypt_id}" class="text-truncate-name text-primary fw-bold" style="text-decoration: none;">${p.adi_soyadi}</a>
                            </div>
                        </td>`;

              for (let d = 1; d <= daysCount; d++) {
                const dateObj = new Date(yil, ay - 1, d);
                const isSunday = dateObj.getDay() === 0;
                const sundayClass = isSunday ? "is-sunday" : "";

                const dateStr = `${yil}-${ay}-${d.toString().padStart(2, "0")}`;
                const key = `${p.id}-${dateStr}`;
                const unsaved = unsavedChanges[key];

                // Excel'den yüklenen personel için mevcut kayıtları gizle, sadece unsaved göster
                const isExcelReplaced = excelReplacedPersonnel.has(
                  p.id.toString(),
                );
                const entries = isExcelReplaced ? [] : p.entries[dateStr] || [];

                let cellContent = "";
                let cellStyle = "";
                let hasEntryClass = "";

                // Önce unsavedChanges kontrol et (Excel'den yüklenen dahil)
                if (unsaved) {
                  const unsavedStyle = getStyleFromTailwind(unsaved.color);
                  cellStyle = `background-color: ${unsavedStyle.bg} !important; color: ${unsavedStyle.color} !important; border: 1px solid ${unsavedStyle.color}33 !important;`;
                  hasEntryClass = "has-entry unsaved";
                  cellContent = `
                                <div class="cell-content draggable-izin" 
                                     data-bs-toggle="tooltip" 
                                     title="${unsaved.name}" 
                                     data-id="${unsaved.type_id || unsaved.typeId}" 
                                     data-shortcode="${unsaved.shortCode}"
                                     data-name="${unsaved.name}"
                                     data-color="${unsaved.color}"
                                     style="font-weight: 700;">
                                    ${unsaved.shortCode}
                                    <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
                                </div>`;
                } else if (entries.length > 0) {
                  const entry = entries[0];
                  const styleObj = getStyleFromTailwind(entry.color);
                  cellStyle = `background-color: ${styleObj.bg} !important; color: ${styleObj.color} !important; border: 1px solid ${styleObj.color}33 !important;`;
                  hasEntryClass = "has-entry";
                  const shortCode = getShortCode(entry);

                  cellContent = `
                                <div class="cell-content draggable-izin" 
                                     data-bs-toggle="tooltip" 
                                     title="${entry.name}" 
                                     data-id="${entry.tip_id}" 
                                     data-shortcode="${shortCode}"
                                     data-name="${entry.name}"
                                     data-color="${entry.color}"
                                     style="font-weight: 700;">
                                    ${shortCode}
                                    <span class="btn-delete-cell" onclick="deleteEntry(${entry.id}, event)">×</span>
                                </div>`;
                }

                bodyHtml += `<td class="day-cell ${hasEntryClass} ${sundayClass}" 
                                         style="${cellStyle}"
                                         data-personel-id="${p.id}" 
                                         data-date="${dateStr}">
                                        ${cellContent}
                                    </td>`;
              }
              bodyHtml += "</tr>";
            });
            $("#table-body").html(bodyHtml);
            initTableEvents();
            initTooltips();
            applyFilter();
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
      });
  }

  // Table Interaction Events
  function initTableEvents() {
    // Selection disabled as per user request
    $(".day-cell").off("mousedown mouseenter mouseup");

    // Drop Logic for cells (Drag & Drop now just selects the cell)
    $(".day-cell").each(function () {
      const cell = this;
      new Sortable(cell, {
        group: {
          name: "izinSharing",
          pull: "clone",
          put: true,
        },
        sort: false,
        ghostClass: "sortable-ghost",
        dragClass: "sortable-drag",
        fallbackOnBody: true,
        onStart: function () {
          $(".tooltip").remove();
        },
        onAdd: function (evt) {
          const typeId = evt.item.dataset.id;
          const typeName = evt.item.dataset.name;
          const typeColor = evt.item.dataset.color;
          const typeShortCode = evt.item.dataset.shortcode;

          if (!typeId) {
            evt.item.remove();
            return;
          }

          const pId = cell.dataset.personelId;
          const date = cell.dataset.date;
          const key = `${pId}-${date}`;

          // Add to unsaved changes
          unsavedChanges[key] = {
            personel_id: pId,
            date: date,
            type_id: typeId,
            name: typeName,
            color: typeColor,
            shortCode: typeShortCode,
          };

          // Render locally
          const style = getStyleFromTailwind(typeColor);
          $(cell)
            .attr(
              "style",
              `background-color: ${style.bg} !important; color: ${style.color} !important; border: 1px solid ${style.color}33 !important;`,
            )
            .addClass("has-entry unsaved");
          $(cell).html(`
                        <div class="cell-content draggable-izin" 
                             data-bs-toggle="tooltip" 
                             title="${typeName}" 
                             data-id="${typeId}" 
                             data-shortcode="${typeShortCode}"
                             data-name="${typeName}"
                             data-color="${typeColor}"
                             style="font-weight: 700;">
                            ${typeShortCode}
                            <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
                        </div>`);
          initTooltips();

          evt.item.remove();
        },
      });
    });
  }

  window.removeUnsaved = function (key, e) {
    if (e) e.stopPropagation();
    delete unsavedChanges[key];

    // UI'yı anlık temizle (Tabloyu yenilemeden)
    const $cell = $(e.target).closest(".day-cell");
    $cell.empty().removeClass("has-entry unsaved").attr("style", "");

    // Tooltip varsa temizle
    $(".tooltip").remove();
  };

  function clearSelection() {
    $(".day-cell").removeClass("selected");
    selectedCells.clear();
  }

  /**
   * Ardışık aynı türden günleri gruplandırır
   * Örnek: 5,6,7 Ocak aynı izin türü -> tek kayıt (baslangic: 5, bitis: 7)
   */
  function groupConsecutiveEntries(entries) {
    // Personel ve tür bazında grupla
    const grouped = {};

    entries.forEach((entry) => {
      const key = `${entry.personel_id}-${entry.type_id}`;
      if (!grouped[key]) {
        grouped[key] = {
          personel_id: entry.personel_id,
          type_id: entry.type_id,
          name: entry.name,
          color: entry.color,
          dates: [],
        };
      }
      grouped[key].dates.push(entry.date);
    });

    // Her grup için tarihleri sırala ve ardışık aralıklar oluştur
    const result = [];

    Object.values(grouped).forEach((group) => {
      // Tarihleri sırala
      const sortedDates = group.dates
        .map((d) => new Date(d))
        .sort((a, b) => a - b);

      if (sortedDates.length === 0) return;

      let rangeStart = sortedDates[0];
      let rangeEnd = sortedDates[0];

      for (let i = 1; i < sortedDates.length; i++) {
        const current = sortedDates[i];
        const prev = sortedDates[i - 1];

        // Bir günlük fark var mı kontrol et
        const diffDays = Math.round((current - prev) / (1000 * 60 * 60 * 24));

        if (diffDays === 1) {
          // Ardışık, aralığı genişlet
          rangeEnd = current;
        } else {
          // Ardışık değil, mevcut aralığı kaydet ve yenisini başlat
          result.push({
            personel_id: group.personel_id,
            type_id: group.type_id,
            name: group.name,
            color: group.color,
            baslangic_tarihi: formatDateISO(rangeStart),
            bitis_tarihi: formatDateISO(rangeEnd),
          });
          rangeStart = current;
          rangeEnd = current;
        }
      }

      // Son aralığı kaydet
      result.push({
        personel_id: group.personel_id,
        type_id: group.type_id,
        name: group.name,
        color: group.color,
        baslangic_tarihi: formatDateISO(rangeStart),
        bitis_tarihi: formatDateISO(rangeEnd),
      });
    });

    return result;
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

      // Ardışık günleri grupla
      const rawData = Object.values(unsavedChanges);
      const groupedData = groupConsecutiveEntries(rawData);

      // Excel'den yüklenen personel ID'lerini de gönder (mevcut kayıtları silmek için)
      const excelPersonnelIds = Array.from(excelReplacedPersonnel);

      console.log("Gruplanmış veriler:", groupedData);
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

      return Toastify({
        text: message,
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
    var tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]'),
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      if (bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
        bootstrap.Tooltip.getInstance(tooltipTriggerEl).dispose();
      }
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
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
        $cell.empty().removeClass("has-entry unsaved").attr("style", "");
        $(".tooltip").remove();

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
          const groupedData = groupConsecutiveEntries(rawData);

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
          <thead class="table-light sticky-top">
            <tr>
              <th class="px-4 text-center" style="width: 60px;">#</th>
              <th>Personel</th>
              <th>SGK Sistemindeki Ad</th>
              <th>Vaka Türü</th>
              <th class="text-center">Başlangıç</th>
              <th class="text-center">Bitiş</th>
              <th class="text-end px-4">Durum</th>
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
    $("#sgk-select-all")
      .off("change")
      .on("change", function () {
        $(".sgk-rapor-check:not(:disabled)").prop(
          "checked",
          $(this).is(":checked"),
        );
      });

    $(".sgk-rapor-check")
      .off("change")
      .on("change", function () {
        const total = $(".sgk-rapor-check:not(:disabled)").length;
        const checked = $(".sgk-rapor-check:not(:disabled):checked").length;
        $("#sgk-select-all").prop("checked", total === checked);
      });

    $("#btn-sgk-rapor-onayla")
      .off("click")
      .on("click", function () {
        const secilenler = [];
        $(".sgk-rapor-check:checked").each(function () {
          const index = $(this).data("index");
          secilenler.push(raporlar[index]);
        });

        if (secilenler.length === 0) {
          showToast("Lütfen en az bir rapor seçin.", "error");
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
    Swal.fire({
      title: "İşleniyor...",
      html: `${raporlar.length} rapor puantaja işleniyor...`,
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
