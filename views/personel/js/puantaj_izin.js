$(document).ready(function () {
  const API_URL = "views/personel/api/puantaj_izin.php";
  let tableRows = []; // Row cache for performance
  let selectedType = null;
  let unsavedChanges = {}; // { 'personelId-date': { typeId, name, color, shortCode } }
  let unsavedOvertimes = {}; // { 'personelId-date': { personel_id, date, saat } }
  let isFmModeActive = false;
  let definitionsMap = {}; // { shortCode: { id, name, color, shortCode } }
  let personnelMap = {}; // { nameOrTc: personObj }
  let ucretsizIzinIds = new Set();
  let ucretliIzinIds = new Set();
  let excelReplacedPersonnel = new Set(); // Excel'den yüklenen personellerin ID'leri
  let ucretliDefinitions = [];
  let ucretsizDefinitions = [];

  // Initial Load from LocalStorage or default
  const savedAy = localStorage.getItem("puantaj_ay");
  const savedYil = localStorage.getItem("puantaj_yil");
  const savedFilter = localStorage.getItem("puantaj_filter") || "";
  const savedDepartman = localStorage.getItem("puantaj_departman") || "";
  const savedBolge = localStorage.getItem("puantaj_bolge") || "";

  if (savedAy) $("#select-ay").val(savedAy).trigger("change");

  if (savedYil) $("#select-yil").val(savedYil);
  if (savedFilter) $("#personel-filter").val(savedFilter);

  const savedIskurDahil = localStorage.getItem("puantaj_iskur_dahil") !== "0";
  $("#check-iskur-dahil").prop("checked", savedIskurDahil);

  // Tooltip desteği performans için iptal edildi (Binlerce hücrede hover gecikmesini önler)
  $(".tooltip").remove();

  $(document).on("mouseenter focus", '[data-bs-toggle="popover"]', function () {
    if (typeof bootstrap !== "undefined" && bootstrap.Popover) {
      bootstrap.Popover.getOrCreateInstance(this, {
        container: "body",
        trigger: "hover focus",
        html: true,
      });
    }
  });

  loadDefinitions(function () {
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

  $("#select-departman").on("change", function () {
    localStorage.setItem("puantaj_departman", $(this).val());
    renderTable();
  });

  $("#select-bolge").on("change", function () {
    localStorage.setItem("puantaj_bolge", $(this).val());
    renderTable();
  });

  $("#check-iskur-dahil").on("change", function () {
    localStorage.setItem("puantaj_iskur_dahil", $(this).is(":checked") ? "1" : "0");
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
    updateWorkspaceMeta();
  }

  function updateWorkspaceMeta() {
    const visibleCount = Array.from(tableRows).filter((row) => row.style.display !== "none").length;
    const changeCount = Object.keys(unsavedChanges).length + Object.keys(unsavedOvertimes).length;
    const ayLabel = $("#select-ay option:selected").text() || "";
    const yilLabel = $("#select-yil").val() || "";

    $("#puantaj-visible-count").text(`${visibleCount} personel`);
    $("#puantaj-period-label").text(`${ayLabel} ${yilLabel} dönemi · Hücreye tıklayarak hızlı giriş yapabilirsiniz`);

    const $changePill = $("#puantaj-change-pill");
    if (changeCount > 0) {
      $changePill.addClass("is-unsaved");
      $changePill.find("i").attr("class", "mdi mdi-content-save-alert-outline");
      $("#puantaj-change-count").text(`${changeCount} bekleyen değişiklik`);
    } else {
      $changePill.removeClass("is-unsaved");
      $changePill.find("i").attr("class", "mdi mdi-check-circle-outline");
      $("#puantaj-change-count").text("Tüm değişiklikler kayıtlı");
    }
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

    // Tam genişlikte personel sütunundan başlanır; yatay kaydırma konumu şaşırtmaz.
    if (isFull) {
      $(".puantaj-table-wrapper").scrollLeft(0);
    }

    $(document).trigger("puantaj:fullscreen", [isFull]);

    // Destekleyen tarayıcılarda adres çubuğu dahil tüm ekranı çalışma alanına bırak.
    if (isFull && document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen().catch(() => {
        // Tarayıcı izin vermezse CSS tam ekran düzeni yine kullanılabilir.
      });
    } else if (!isFull && document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen();
    }
  });

  // Tarayıcının kendi ESC davranışıyla çıkılırsa arayüz durumunu da geri al.
  $(document).on("fullscreenchange", function () {
    if (!document.fullscreenElement && $("body").hasClass("puantaj-fullscreen")) {
      $("body").removeClass("puantaj-fullscreen");
      $("#btn-fullscreen")
        .html('<i class="mdi mdi-fullscreen me-1"></i> Tam Ekran')
        .removeClass("btn-soft-secondary btn-soft-danger");
    }
  });

  // Tam ekran hızlı kaydet: mevcut kaydetme mantığını tetikler, ayrı işlem akışı oluşturmaz.
  $("#btn-save-floating").on("click", function () {
    $("#btn-save-selected").trigger("click");
  });

  // Odak modu: filtre ve puantaj paletini kapatıp dikey alanı tabloya bırakır.
  let focusTransitionTimer = null;
  function setPuantajFocusMode(isActive, animate = true) {
    const $body = $("body");
    if (focusTransitionTimer) clearTimeout(focusTransitionTimer);
    if (animate) $body.addClass("puantaj-focus-transition");
    $body.toggleClass("puantaj-focus-mode", isActive);
    const $button = $("#btn-puantaj-focus");
    $button
      .attr("title", isActive ? "Üst alanları göster" : "Üst alanları gizle")
      .attr("aria-label", isActive ? "Üst alanları göster" : "Üst alanları gizle")
      .attr("aria-expanded", isActive ? "false" : "true")
      .find("i")
      .attr("class", isActive ? "mdi mdi-chevron-down" : "mdi mdi-chevron-up");
    localStorage.setItem("puantaj_focus_mode_v2", isActive ? "1" : "0");
    updateStickyHeights();
    if (animate) {
      focusTransitionTimer = setTimeout(() => {
        $body.removeClass("puantaj-focus-transition");
      }, 380);
    }
  }

  $(document).on("click", "#btn-puantaj-focus", function (e) {
    e.preventDefault();
    setPuantajFocusMode(!$("body").hasClass("puantaj-focus-mode"));
  });

  // Eski hatalı odak modu kaydını temizle ve varsayılan olarak açık başlat
  localStorage.removeItem("puantaj_focus_mode");
  if (localStorage.getItem("puantaj_focus_mode_v2") === "1") {
    setPuantajFocusMode(true, false);
  } else {
    setPuantajFocusMode(false, false);
  }

  // ESC ile Tam Ekrandan Çık
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $("body").hasClass("puantaj-fullscreen")) {
      $("#btn-fullscreen").click();
    }
  });

  // Palet her zaman tablonun üstünde sabit kalır (Tüm div'in sürüklenmesi engellendi)
  localStorage.removeItem("puantaj_izin_paleti_konum");
  $("body").removeClass("puantaj-palette-floating puantaj-palette-dragging");
  $("#izin-turleri-palette").css({ left: "", top: "", width: "", transform: "" });

  $(document).on("click", ".draggable-izin", function () {
    if ($(this).closest("#izin-turleri-palette").length) {
      $("#izin-turleri-palette .draggable-izin").removeClass("is-selected");
      $(this).addClass("is-selected");
    }
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

  // Helper: Get style from tailwind class (WITH CACHE & Soft Palette)
  const styleCache = {};
  function getStyleFromTailwind(tailwindClass, shortCode) {
    const code = (shortCode || "").toUpperCase();
    const isMacTheme = document.documentElement.getAttribute("data-theme-preset") === "macos-dark";
    const cacheKey = `${isMacTheme ? "mac" : "default"}_${tailwindClass || ""}_${code}`;
    if (styleCache[cacheKey]) return styleCache[cacheKey];

    let result;

    // Finder benzeri koyu cam paleti. Renkler anlamı korur, yüzeyler parlak beyaz olmaz.
    if (isMacTheme) {
      const colorToken = (tailwindClass || "").toLowerCase();

      if (code === "X" || colorToken.includes("blue") || colorToken.includes("primary")) {
        result = { bg: "rgba(67, 91, 123, .34)", color: "rgba(166, 195, 231, .9)", border: "rgba(112, 145, 185, .32)" };
      } else if (code === "HT" || colorToken.includes("amber") || colorToken.includes("orange") || colorToken.includes("warning")) {
        result = { bg: "rgba(119, 82, 40, .34)", color: "rgba(229, 179, 110, .9)", border: "rgba(181, 130, 68, .32)" };
      } else if (colorToken.includes("red") || colorToken.includes("rose") || colorToken.includes("danger") || colorToken.includes("pink")) {
        result = { bg: "rgba(116, 55, 65, .34)", color: "rgba(222, 141, 153, .9)", border: "rgba(174, 86, 101, .32)" };
      } else if (colorToken.includes("green") || colorToken.includes("emerald") || colorToken.includes("success") || colorToken.includes("teal")) {
        result = { bg: "rgba(48, 103, 79, .34)", color: "rgba(137, 207, 172, .9)", border: "rgba(73, 145, 109, .32)" };
      } else if (colorToken.includes("purple") || colorToken.includes("violet") || colorToken.includes("indigo")) {
        result = { bg: "rgba(91, 67, 116, .34)", color: "rgba(198, 165, 226, .9)", border: "rgba(137, 101, 169, .32)" };
      } else if (colorToken.includes("cyan") || colorToken.includes("info")) {
        result = { bg: "rgba(47, 100, 108, .34)", color: "rgba(137, 205, 211, .9)", border: "rgba(72, 143, 151, .32)" };
      } else if (tailwindClass && tailwindClass.startsWith("#")) {
        result = {
          bg: `color-mix(in srgb, ${tailwindClass} 22%, rgba(34, 35, 40, .96))`,
          color: `color-mix(in srgb, ${tailwindClass} 58%, white)`,
          border: `color-mix(in srgb, ${tailwindClass} 34%, transparent)`
        };
      } else {
        result = { bg: "rgba(255, 255, 255, .075)", color: "rgba(226, 227, 230, .78)", border: "rgba(255, 255, 255, .12)" };
      }

      styleCache[cacheKey] = result;
      return result;
    }

    // Çalışılan Gün (X) için göz yormayan soft Ice Blue
    if (code === "X") {
      result = { bg: "#eff6ff", color: "#2563eb", border: "#dbeafe" };
    }
    // Hafta Tatili (HT) için soft Warm Amber / Gold
    else if (code === "HT") {
      result = { bg: "#fffbeb", color: "#d97706", border: "#fef3c7" };
    }
    // Hex rengi kontrolü
    else if (tailwindClass && tailwindClass.startsWith("#")) {
      result = {
        bg: tailwindClass + "1a", // %10 yumuşak pastel opaklık
        color: tailwindClass,
        border: tailwindClass + "33"
      };
    } else if (tailwindClass && (tailwindClass.includes("blue") || tailwindClass.includes("primary"))) {
      result = { bg: "#eff6ff", color: "#2563eb", border: "#dbeafe" };
    } else if (tailwindClass && tailwindClass.includes("amber")) {
      result = { bg: "#fffbeb", color: "#d97706", border: "#fef3c7" };
    } else if (tailwindClass && (tailwindClass.includes("red") || tailwindClass.includes("rose") || tailwindClass.includes("danger"))) {
      result = { bg: "#fff1f2", color: "#e11d48", border: "#ffe4e6" };
    } else if (tailwindClass && tailwindClass.includes("pink")) {
      result = { bg: "#fdf2f8", color: "#db2777", border: "#fce7f3" };
    } else if (tailwindClass && (tailwindClass.includes("gray") || tailwindClass.includes("slate") || tailwindClass.includes("secondary"))) {
      result = { bg: "#f8fafc", color: "#475569", border: "#e2e8f0" };
    } else if (tailwindClass && (tailwindClass.includes("green") || tailwindClass.includes("emerald") || tailwindClass.includes("success"))) {
      result = { bg: "#f0fdf4", color: "#16a34a", border: "#dcfce7" };
    } else if (tailwindClass && (tailwindClass.includes("purple") || tailwindClass.includes("violet") || tailwindClass.includes("indigo"))) {
      result = { bg: "#f5f3ff", color: "#7c3aed", border: "#ede9fe" };
    } else if (tailwindClass && (tailwindClass.includes("orange") || tailwindClass.includes("warning"))) {
      result = { bg: "#fff7ed", color: "#ea580c", border: "#ffedd5" };
    } else if (tailwindClass && (tailwindClass.includes("cyan") || tailwindClass.includes("teal") || tailwindClass.includes("info"))) {
      result = { bg: "#f0fdfa", color: "#0d9488", border: "#ccfbf1" };
    } else {
      result = { bg: "#eff6ff", color: "#3b82f6", border: "#dbeafe" };
    }

    styleCache[cacheKey] = result;
    return result;
  }

  // Load Definitions
  function loadDefinitions(callback) {
    $.post(API_URL, { action: "get-definitions" }, function (res) {
      if (res.status === "success") {
        ucretsizIzinIds.clear();
        ucretliIzinIds.clear();
        const render = (list, containerId, isUcretsiz = false) => {
          let html = "";
          list.forEach((item) => {
            if (isUcretsiz) {
              ucretsizIzinIds.add(item.id.toString());
            } else {
              ucretliIzinIds.add(item.id.toString());
            }
            const shortCode = getShortCode(item);
            const style = getStyleFromTailwind(item.renk, shortCode);
            html += `
                            <div class="izin-item-container draggable-izin" draggable="true"
                                 data-id="${item.id}" 
                                 data-name="${item.tur_adi}"
                                 data-color="${item.renk}"
                                 data-shortcode="${shortCode}"
                                 title="${item.tur_adi}">
                                <div class="izin-box" style="background-color: ${style.bg} !important; color: ${style.color} !important; border: 1px solid ${style.border};">
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

        // Departmanlar
        let depHtml = '<option value="">Tüm Departmanlar</option>';
        if (res.data.departmanlar && Array.isArray(res.data.departmanlar)) {
          res.data.departmanlar.forEach(dep => {
            if (dep) {
              depHtml += `<option value="${dep}">${dep}</option>`;
            }
          });
        }
        $("#select-departman").html(depHtml);
        if (savedDepartman) {
          $("#select-departman").val(savedDepartman).trigger("change.select2");
        }

        // Bölgeler
        let bolgeHtml = '<option value="">Tüm Bölgeler</option>';
        if (res.data.bolgeler && Array.isArray(res.data.bolgeler)) {
          res.data.bolgeler.forEach(b => {
            if (b) {
              bolgeHtml += `<option value="${b}">${b}</option>`;
            }
          });
        }
        $("#select-bolge").html(bolgeHtml);
        if (savedBolge) {
          $("#select-bolge").val(savedBolge).trigger("change.select2");
        }

        // İzin türleri yüklendikten sonra sticky yüksekliği güncelle
        setTimeout(updateStickyHeights, 100);
        if (callback) callback();
      }
    });
  }

  function getPersonInitials(fullName) {
    const parts = (fullName || "").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "—";
    const first = parts[0].charAt(0);
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : "";
    return `${first}${last}`.toLocaleUpperCase("tr-TR");
  }

  function getPersonAvatarTone(fullName) {
    let hash = 0;
    for (const character of fullName || "") hash = (hash + character.charCodeAt(0)) % 5;
    return `personel-avatar-tone-${hash}`;
  }

  // Render Table
  function renderTable() {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();
    const departman = $("#select-departman").val() || "";
    const bolge = $("#select-bolge").val() || "";
    const iskur_dahil = $("#check-iskur-dahil").is(":checked") ? 1 : 0;
    const daysCount = getDaysInMonth(ay, yil);
    updateWorkspaceMeta();

    // Tabloyu temizle ve yükleniyor göster
    $("#table-body").empty();
    $("#puantaj-loader").fadeIn(100);

    // Header
    let headerHtml = '<th class="sticky-col">Personel</th>';
    const dayNames = ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"];
    for (let d = 1; d <= daysCount; d++) {
      const date = new Date(yil, ay - 1, d);
      const isSunday = date.getDay() === 0;
      const now = new Date();
      const isToday = date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && d === now.getDate();
      const dayName = dayNames[date.getDay()];
      const sundayClass = isSunday ? "is-sunday" : "";
      const todayClass = isToday ? "is-today" : "";

      headerHtml += `<th class="${sundayClass} ${todayClass}">
         <div class="day-header-pill">
             <div class="day-name">${dayName}</div>
             <div class="day-number">${d}</div>
         </div>
      </th>`;
    }
    headerHtml += '<th class="sticky-col-right-1">Toplam Gün</th>';
    headerHtml += '<th class="sticky-col-right-2" title="Aylık Toplam Fazla Mesai Saati">Toplam FM</th>';
    $("#table-header").html(headerHtml);

    // Body with data
    $.post(
      API_URL,
      { action: "get-calendar-data", ay, yil, departman, bolge, iskur_dahil },
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
                  const safeDepartman = (p.departman || "").replace(/"/g, "&quot;");
                  const safeAdi = (p.adi_soyadi || "").replace(/"/g, "&quot;");
                  bodyHtml += `<tr data-personel-adi="${turkceKucukHarf(p.adi_soyadi)}" data-personel-adi-raw="${safeAdi}" data-departman="${safeDepartman}">
                        <td class="personel-info sticky-col" title="${p.adi_soyadi}" data-personel-id="${p.id}" data-gg-toplam-gun="${p.gg_toplam_gun || 0}" data-gorev-gecmisi="${p.gorev_gecmisi_var || 0}">
                            <div class="d-flex align-items-center">
                                <span class="personel-avatar-mini ${getPersonAvatarTone(p.adi_soyadi)}">${getPersonInitials(p.adi_soyadi)}</span>
                                <a href="?p=personel/manage&id=${p.encrypt_id}" class="text-truncate-name text-primary fw-bold" style="text-decoration: none;">${p.adi_soyadi}</a>
                            </div>
                        </td>`;

                  let disabledDaysCount = 0;
                  let paidCount = 0;
                  let totalFmHours = 0;

                  for (let d = 1; d <= daysCount; d++) {
                    const dateStr = `${yil}-${ay.toString().padStart(2, "0")}-${d.toString().padStart(2, "0")}`;
                    const dateObj = new Date(yil, ay - 1, d);
                    const isSunday = dateObj.getDay() === 0;
                    const sundayClass = isSunday ? "is-sunday" : "";
                    const now = new Date();
                    const todayClass = dateObj.getFullYear() === now.getFullYear() && dateObj.getMonth() === now.getMonth() && d === now.getDate() ? "is-today" : "";

                    const pGirisStr = p.ise_giris_tarihi && p.ise_giris_tarihi !== "0000-00-00" ? p.ise_giris_tarihi : "";
                    const pCikisStr = p.isten_cikis_tarihi && p.isten_cikis_tarihi !== "0000-00-00" ? p.isten_cikis_tarihi : "";

                    const calismaDonemleri = Array.isArray(p.calisma_donemleri) ? p.calisma_donemleri : [];
                    const isAktifCalismaGunu = calismaDonemleri.length > 0
                      ? calismaDonemleri.some((donem) => dateStr >= donem.baslangic && (!donem.bitis || dateStr <= donem.bitis))
                      : !((pGirisStr && dateStr < pGirisStr) || (pCikisStr && dateStr > pCikisStr));
                    const isDisabled = !isAktifCalismaGunu;
                    const disabledClass = isDisabled ? "disabled" : "";
                    if (isDisabled) disabledDaysCount++;

                    let cellContent = "";
                    let hasEntryClass = "";

                    const key = `${p.id}-${dateStr}`;
                    const unsaved = unsavedChanges[key];

                    // Fazla Mesai (Overtime) kontrolü
                    let otSaat = 0;
                    const nobetOtSaat = parseFloat(p.shift_overtimes && p.shift_overtimes[dateStr]) || 0;
                    let manuelOtSaat = 0;
                    let isUnsavedOt = false;
                    if (unsavedOvertimes[key] !== undefined) {
                      manuelOtSaat = parseFloat(unsavedOvertimes[key].saat) || 0;
                      isUnsavedOt = true;
                    } else if (p.overtimes && p.overtimes[dateStr]) {
                      manuelOtSaat = parseFloat(p.overtimes[dateStr].saat || p.overtimes[dateStr]) || 0;
                    }
                    otSaat = manuelOtSaat + nobetOtSaat;
                    if (!isDisabled && otSaat > 0) {
                      totalFmHours += otSaat;
                    }
                    let overtimeBadgeHtml = "";
                    if (otSaat > 0) {
                      const unsavedOtClass = isUnsavedOt ? "unsaved-ot" : "";
                      const otTitle = nobetOtSaat > 0 ? `Fazla Mesai: ${otSaat} Saat (Nöbet: ${nobetOtSaat} Saat)` : `Fazla Mesai: ${otSaat} Saat`;
                      overtimeBadgeHtml = `<span class="overtime-badge ${unsavedOtClass}" data-key="${key}" data-hours="${otSaat}" data-manual-hours="${manuelOtSaat}" data-shift-hours="${nobetOtSaat}" title="${otTitle}">+${otSaat % 1 === 0 ? otSaat.toFixed(0) : otSaat.toFixed(1)}S</span>`;
                    }

                    // Excel'den yüklenen personel için mevcut kayıtları gizle, sadece unsaved göster
                    const isExcelReplaced = excelReplacedPersonnel.has(
                      p.id.toString(),
                    );
                    const entries = isExcelReplaced
                      ? []
                      : p.entries[dateStr] || [];

                    // Önce unsavedChanges kontrol et (Excel'den yüklenen dahil)
                    if (unsaved) {
                      const unsavedStyle = getStyleFromTailwind(unsaved.color, unsaved.shortCode);
                      const typeId = (
                        unsaved.type_id || unsaved.typeId
                      )?.toString();
                      // Sadece manuel giriş yapılan ücretsiz izinleri say (default HT/X değil)
                      if (!isDisabled && typeId && ucretliIzinIds.has(typeId)) paidCount++;

                      hasEntryClass = "has-entry unsaved";
                      cellContent = `
                                <div class="cell-content draggable-izin" draggable="true"
                                     title="${unsaved.name}" 
                                     data-id="${unsaved.type_id || unsaved.typeId}" 
                                     data-shortcode="${unsaved.shortCode}"
                                     data-name="${unsaved.name}"
                                     data-color="${unsaved.color}"
                                     data-is-default="false"
                                     style="background-color: ${unsavedStyle.bg} !important; color: ${unsavedStyle.color} !important; border: 1px solid ${unsavedStyle.border};">
                                    ${unsaved.shortCode}
                                    <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
                                </div>`;
                    } else if (entries.length > 0) {
                      const entry = entries[0];
                      const shortCode = getShortCode(entry);
                      const styleObj = getStyleFromTailwind(entry.color, shortCode);
                      const typeId = entry.tip_id?.toString();
                      // Sadece manuel giriş yapılan ücretsiz izinleri say (default HT/X değil)
                      if (!isDisabled && typeId && ucretliIzinIds.has(typeId)) paidCount++;
                      
                      hasEntryClass = "has-entry";
                      if (entry.type === "nobet_htc" || entry.locked) hasEntryClass += " nobet-htc-locked";

                      let deleteBtn = "";
                      if (entry.type !== "default" && entry.type !== "nobet_htc" && !entry.locked) {
                        deleteBtn = `<span class="btn-delete-cell" onclick="deleteEntry(${entry.id}, event)">×</span>`;
                      }

                      cellContent = `
                                <div class="cell-content draggable-izin" draggable="true"
                                     title="${entry.name}" 
                                     data-id="${entry.tip_id}" 
                                     data-shortcode="${shortCode}"
                                     data-name="${entry.name}"
                                     data-color="${entry.color}"
                                     data-is-default="${entry.type === "default"}"
                                     style="background-color: ${styleObj.bg} !important; color: ${styleObj.color} !important; border: 1px solid ${styleObj.border};">
                                    ${shortCode}
                                    ${deleteBtn}
                                </div>`;
                    }

                    bodyHtml += `<td class="day-cell ${hasEntryClass} ${sundayClass} ${todayClass} ${disabledClass}"
                                         data-personel-id="${p.id}" 
                                         data-date="${dateStr}">
                                        ${cellContent}
                                        ${overtimeBadgeHtml}
                                    </td>`;
                  }
                  bodyHtml += `<td class="sticky-col-right-1 toplam-calisma-gunu">${Math.max(0, paidCount)}</td>`;
                  bodyHtml += `<td class="sticky-col-right-2 toplam-fm-saati" data-personel-id="${p.id}">${totalFmHours > 0 ? (totalFmHours % 1 === 0 ? totalFmHours.toFixed(0) : totalFmHours.toFixed(1)) + "s" : "-"}</td>`;
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
    let totalGenelFm = 0;
    
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

      // Toplam fazla mesai saatini al
      const fmCell = row.querySelector(".toplam-fm-saati");
      if (fmCell && fmCell.textContent !== "-") {
        totalGenelFm += parseFloat(fmCell.textContent) || 0;
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
    footerHtml += `<td class="sticky-col-right-2 text-center text-purple fw-bold">${totalGenelFm > 0 ? (totalGenelFm % 1 === 0 ? totalGenelFm.toFixed(0) : totalGenelFm.toFixed(1)) + 's' : '-'}</td>`;
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
        if ($(this).hasClass("disabled")) return;
        if ($(this).hasClass("nobet-htc-locked")) {
          showToast("Bu HTÇ onaylı hafta sonu nöbetinden otomatik gelir ve puantajdan değiştirilemez.", "info");
          return;
        }

        // Fazla Mesai Modu Aktifse:
        if (isFmModeActive) {
          const currentBadge = this.querySelector(".overtime-badge");
          const defaultHours = parseFloat($("#fm-default-hours").val()) || 4.0;

          if (currentBadge) {
            const existingHours = parseFloat(currentBadge.getAttribute("data-manual-hours")) || 0;
            if (existingHours === defaultHours) {
              // Aynı saat zaten varsa tıklandığında kaldır
              applyOvertimeToCell(this, 0);
              return;
            }
          }
          // Eğer personel Kesme departmanında ise modal açarak bilinçli saat girmesini sağla
          const row = this.closest("tr");
          const departman = (row ? (row.getAttribute("data-departman") || "") : "").trim();
          const isKesme = turkceKucukHarf(departman).indexOf("kesme") > -1;
          if (isKesme) {
            openOvertimeModal(this);
            return;
          }

          applyOvertimeToCell(this, defaultHours);
          return;
        }

        if (!selectedType) return;
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
        
        // Sadece küçük tür kutucuğunu sürükle (Geniş div sürüklenmesini engeller)
        const dragVisual = $el.find(".izin-box")[0] || $el.find(".cell-content")[0] || this;
        if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.setDragImage) {
          const offsetX = dragVisual.offsetWidth ? Math.floor(dragVisual.offsetWidth / 2) : 14;
          const offsetY = dragVisual.offsetHeight ? Math.floor(dragVisual.offsetHeight / 2) : 14;
          e.originalEvent.dataTransfer.setDragImage(dragVisual, offsetX, offsetY);
        }

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

        const style = getStyleFromTailwind(item.renk, shortCode);
        const itemHtml = `
          <div class="menu-item ${activeClass}" data-id="${item.id}" data-shortcode="${shortCode}" data-name="${item.tur_adi}" data-color="${item.renk}">
            <div class="menu-item-code" style="background-color: ${style.bg} !important; color: ${style.color} !important; border: 1px solid ${style.border};">
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

      // Check overtime state for context menu
      const hasOvertime = cell.find(".overtime-badge").length > 0;
      if (hasOvertime) {
        $("#menu-item-remove-overtime").removeClass("d-none");
      } else {
        $("#menu-item-remove-overtime").addClass("d-none");
      }
    });

    // Context Menu: Fazla Mesai Saati Gir / Değiştir
    $("#menu-item-set-overtime")
      .off("click")
      .on("click", function () {
        const contextMenu = $("#custom-context-menu");
        const cell = contextMenu.data("target-cell");
        if (!cell || !cell.length) return;
        contextMenu.hide();
        openOvertimeModal(cell[0]);
      });

    // Context Menu: Fazla Mesaiyi Kaldır
    $("#menu-item-remove-overtime")
      .off("click")
      .on("click", function () {
        const contextMenu = $("#custom-context-menu");
        const cell = contextMenu.data("target-cell");
        if (!cell || !cell.length) return;

        applyOvertimeToCell(cell[0], 0);
        contextMenu.hide();
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

    // Eğer hücrede önceden fazla mesai rozeti varsa koru
    const existingOtBadge = cell.querySelector(".overtime-badge");
    const otBadgeOuterHtml = existingOtBadge ? existingOtBadge.outerHTML : "";

    // Update UI instantly using Vanilla JS (Much faster than jQuery)
    const styleObj = getStyleFromTailwind(type.color, type.shortCode);
    cell.style.cssText = "";
    cell.className = `day-cell has-entry unsaved ${cell.classList.contains("is-sunday") ? "is-sunday" : ""} ${cell.classList.contains("is-today") ? "is-today" : ""}`;
    cell.innerHTML = `
      <div class="cell-content draggable-izin" draggable="true"
           title="${type.name}" 
           data-id="${type.id}" 
           data-name="${type.name}" 
           data-color="${type.color}" 
           data-shortcode="${type.shortCode}"
           style="background-color: ${styleObj.bg} !important; color: ${styleObj.color} !important; border: 1px solid ${styleObj.border};">
          ${type.shortCode}
          <span class="btn-delete-cell" onclick="removeUnsaved('${key}', event)">×</span>
      </div>${otBadgeOuterHtml}`;

    updateRowTotals(row);
  }

  function setOvertimeBadgeAndState(cell, saat) {
    const row = cell.closest("tr");
    const pId = cell.dataset.personelId;
    const date = cell.dataset.date;
    const key = `${pId}-${date}`;

    const oldBadge = cell.querySelector(".overtime-badge");
    const shiftHours = oldBadge ? (parseFloat(oldBadge.getAttribute("data-shift-hours")) || 0) : 0;

    // Varsa eski rozeti kaldır
    if (oldBadge) oldBadge.remove();

    if (saat > 0 || shiftHours > 0) {
      unsavedOvertimes[key] = {
        personel_id: pId,
        date: date,
        saat: saat
      };
      const badge = document.createElement("span");
      badge.className = "overtime-badge unsaved-ot";
      badge.setAttribute("data-key", key);
      const totalHours = saat + shiftHours;
      badge.setAttribute("data-hours", totalHours);
      badge.setAttribute("data-manual-hours", saat);
      badge.setAttribute("data-shift-hours", shiftHours);
      badge.setAttribute("title", shiftHours > 0 ? `Fazla Mesai: ${totalHours} Saat (Nöbet: ${shiftHours} Saat)` : `Fazla Mesai: ${totalHours} Saat`);
      badge.textContent = `+${totalHours % 1 === 0 ? totalHours.toFixed(0) : totalHours.toFixed(1)}S`;
      cell.appendChild(badge);
    } else {
      unsavedOvertimes[key] = {
        personel_id: pId,
        date: date,
        saat: 0
      };
    }

    updateRowTotals(row);
    updateWorkspaceMeta();
  }

  let activeOvertimeCell = null;

  function openOvertimeModal(cell) {
    if (!cell) return;
    activeOvertimeCell = cell;

    const row = cell.closest("tr");
    const dateStr = cell.dataset.date || "";
    const personelAdi = row ? (row.getAttribute("data-personel-adi-raw") || row.querySelector(".text-truncate-name")?.textContent?.trim() || "Personel") : "Personel";
    const departman = (row ? (row.getAttribute("data-departman") || "") : "").trim();
    const isKesme = turkceKucukHarf(departman).indexOf("kesme") > -1;

    let formattedDate = dateStr;
    try {
      const parts = dateStr.split("-");
      if (parts.length === 3) {
        const dObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        formattedDate = dObj.toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric" });
      }
    } catch(e) {}

    $("#modal-fm-personel-tarih").text(`${personelAdi} · ${formattedDate}`);

    if (isKesme) {
      $("#modal-fm-kesme-alert").show();
    } else {
      $("#modal-fm-kesme-alert").hide();
    }

    const currentBadge = cell.querySelector(".overtime-badge");
    const existingHours = currentBadge ? parseFloat(currentBadge.getAttribute("data-manual-hours")) || 0 : 0;
    const shiftHours = currentBadge ? parseFloat(currentBadge.getAttribute("data-shift-hours")) || 0 : 0;
    const initialHours = existingHours > 0 ? existingHours : (shiftHours > 0 ? 0 : (parseFloat($("#fm-default-hours").val()) || 4.0));

    if (shiftHours > 0) {
      $("#modal-fm-nobet-alert").text(`Bu güne Nöbet Listesi'nden otomatik ${shiftHours} saat eklendi. Aşağıdaki alan yalnız ilave manuel mesai içindir.`).show();
    } else {
      $("#modal-fm-nobet-alert").hide().text("");
    }

    $("#modal-fm-hours-input").val(initialHours);

    $(".btn-fm-quick").removeClass("btn-outline-purple active").addClass("btn-outline-secondary");
    $(`.btn-fm-quick[data-hours="${initialHours}"]`).removeClass("btn-outline-secondary").addClass("btn-outline-purple active");

    if (existingHours > 0) {
      $("#btn-modal-fm-remove").show();
    } else {
      $("#btn-modal-fm-remove").hide();
    }

    const modalEl = document.getElementById("fazlaMesaiModal");
    if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      setTimeout(() => {
        $("#modal-fm-hours-input").focus().select();
      }, 350);
    }
  }

  // Fazla Mesai Modalı Buton Olayları
  $(document).on("click", ".btn-fm-quick", function () {
    const hrs = $(this).data("hours");
    $("#modal-fm-hours-input").val(hrs);
    $(".btn-fm-quick").removeClass("btn-outline-purple active").addClass("btn-outline-secondary");
    $(this).removeClass("btn-outline-secondary").addClass("btn-outline-purple active");
    $("#modal-fm-hours-input").focus();
  });

  $("#btn-modal-fm-save").off("click").on("click", function () {
    if (!activeOvertimeCell) return;
    const val = parseFloat($("#modal-fm-hours-input").val()) || 0;
    if (val <= 0) {
      showToast("Lütfen 0'dan büyük geçerli bir saat giriniz.", "warning");
      return;
    }
    const modalEl = document.getElementById("fazlaMesaiModal");
    if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }
    setOvertimeBadgeAndState(activeOvertimeCell, val);
    persistSingleOvertime(activeOvertimeCell, val);
  });

  $("#modal-fm-hours-input").off("keydown").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      $("#btn-modal-fm-save").click();
    }
  });

  $("#btn-modal-fm-remove").off("click").on("click", function () {
    if (!activeOvertimeCell) return;
    const modalEl = document.getElementById("fazlaMesaiModal");
    if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }
    setOvertimeBadgeAndState(activeOvertimeCell, 0);
    persistSingleOvertime(activeOvertimeCell, 0);
  });

  function persistSingleOvertime(cell, saat) {
    if (!cell) return;
    const pId = cell.dataset.personelId;
    const date = cell.dataset.date;
    const key = `${pId}-${date}`;
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();

    $.post(API_URL, {
      action: "save-bulk-entries",
      data: "[]",
      overtimes: JSON.stringify([{ personel_id: pId, date: date, saat: saat }]),
      excelPersonnelIds: "[]",
      ay: ay,
      yil: yil,
    }, function (res) {
      if (res.status !== "success") {
        showToast(res.message || "Fazla mesai kaydedilemedi.", "error");
        return;
      }
      delete unsavedOvertimes[key];
      const sonucMesaji = saat > 0 ? `${saat} saat fazla mesai kaydedildi.` : "Fazla mesai kaldırıldı.";
      showToast(`${sonucMesaji} Açık dönem bordrosu varsa yeniden hesaplayın.`, "success");
      renderTable();
    }).fail(function () {
      showToast("Fazla mesai kaydedilirken bağlantı hatası oluştu.", "error");
    });
  }

  function applyOvertimeToCell(cell, saat, onComplete) {
    saat = parseFloat(saat) || 0;

    // Mesai kaldırılıyorsa doğrudan kaldır
    if (saat <= 0) {
      setOvertimeBadgeAndState(cell, 0);
      persistSingleOvertime(cell, 0);
      if (typeof onComplete === "function") onComplete(0);
      return;
    }

    setOvertimeBadgeAndState(cell, saat);
    persistSingleOvertime(cell, saat);
    if (typeof onComplete === "function") onComplete(saat);
  }

  let calculateTotalsTimer = null;
  function getRowPaidDayCount(row) {
    const dayCells = row.querySelectorAll(".day-cell");
    let paidCount = 0;

    for (let i = 0; i < dayCells.length; i++) {
      const cell = dayCells[i];
      if (cell.classList.contains("disabled")) continue;

      const content = cell.querySelector(".cell-content");
      if (!content) continue;

      const typeId = content.getAttribute("data-id");
      if (typeId && ucretliIzinIds.has(typeId.toString())) {
        paidCount++;
      }
    }

    return Math.max(0, paidCount);
  }

  function updateRowTotals(rowOrId) {
    const row = typeof rowOrId === "object" ? rowOrId : document.querySelector(`.personel-info[data-personel-id="${rowOrId}"]`)?.closest("tr");
    if (!row) return;

    const toplamCalisma = getRowPaidDayCount(row);
    const totalDisplayCell = row.querySelector(".toplam-calisma-gunu");
    if (totalDisplayCell) {
        totalDisplayCell.textContent = toplamCalisma;
    }

    // Toplam FM saatini güncelle
    const otBadges = row.querySelectorAll(".overtime-badge");
    let rowFmTotal = 0;
    for (let b = 0; b < otBadges.length; b++) {
      rowFmTotal += parseFloat(otBadges[b].getAttribute("data-hours")) || 0;
    }
    const fmDisplayCell = row.querySelector(".toplam-fm-saati");
    if (fmDisplayCell) {
      fmDisplayCell.textContent = rowFmTotal > 0 ? (rowFmTotal % 1 === 0 ? rowFmTotal.toFixed(0) : rowFmTotal.toFixed(1)) + "s" : "-";
    }

    // Satır toplamı değiştiyse genel toplamı da güncelle
    if (calculateTotalsTimer) clearTimeout(calculateTotalsTimer);
    calculateTotalsTimer = setTimeout(() => {
      calculateTotals();
      updateWorkspaceMeta();
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

  // Fazla Mesai Modu Buton Olayı
  $("#btn-toggle-fm-mode")
    .off("click")
    .on("click", function () {
      isFmModeActive = !isFmModeActive;
      $(this).toggleClass("active", isFmModeActive);
      $("body").toggleClass("fm-mode-active", isFmModeActive);

      if (isFmModeActive) {
        selectedType = null;
        $(".izin-box.active").removeClass("active");
        $("#fm-hours-input-wrapper").css("display", "inline-flex").hide().fadeIn(150);
        showToast("Fazla Mesai Modu Aktif. Günlere tıklayarak saat bazlı mesai ekleyebilirsiniz.", "info");
      } else {
        $("#fm-hours-input-wrapper").fadeOut(150);
        showToast("Fazla Mesai Modu Kapatıldı.", "info");
      }
    });

  // Paletteki herhangi bir izin türü seçilirse FM modunu kapat
  $(document).on("click", ".izin-box", function () {
    if (isFmModeActive) {
      isFmModeActive = false;
      $("#btn-toggle-fm-mode").removeClass("active");
      $("body").removeClass("fm-mode-active");
      $("#fm-hours-input-wrapper").fadeOut(150);
    }
  });

  $("#btn-save-selected")
    .off("click")
    .on("click", function () {
      const changesCount = Object.keys(unsavedChanges).length;
      const overtimesCount = Object.keys(unsavedOvertimes).length;
      if (changesCount === 0 && overtimesCount === 0) {
        showToast("Kaydedilecek yeni bir değişiklik yok.", "info");
        return;
      }

      // Değişiklikleri kaydet (Grup yapmadan, tekil gün olarak gönderiyoruz)
      const rawData = Object.values(unsavedChanges);
      const rawOvertimes = Object.values(unsavedOvertimes);
      const excelPersonnelIds = Array.from(excelReplacedPersonnel);

      saveBulkEntries(rawData, excelPersonnelIds, rawOvertimes);
    });

  // API Calls
  function saveBulkEntries(data, excelPersonnelIds = [], overtimes = []) {
    // Optimistic Update: Önce UI'ı güncelle
    $(".day-cell.unsaved").each(function () {
      $(this).removeClass("unsaved");
      // Silme butonunu güncelle (artık veritabanından silecek)
      const $deleteBtn = $(this).find(".btn-delete-cell");
      if ($deleteBtn.length) {
        $deleteBtn.remove();
      }
    });
    $(".overtime-badge.unsaved-ot").removeClass("unsaved-ot");

    // Kayıtları hemen temizle
    const savedChanges = { ...unsavedChanges };
    const savedOvertimes = { ...unsavedOvertimes };
    const savedExcelPersonnel = new Set(excelReplacedPersonnel);
    unsavedChanges = {};
    unsavedOvertimes = {};
    excelReplacedPersonnel.clear();
    updateWorkspaceMeta();

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
        overtimes: JSON.stringify(overtimes),
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
          unsavedOvertimes = savedOvertimes;
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
      unsavedOvertimes = savedOvertimes;
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
   */
  function updateStickyHeights() {
    // Normal modda kartlar doğal akışta yer alır, top offset'i uygulanmaz
    if (!$("body").hasClass("puantaj-palette-floating")) {
      $(".card-izin-turleri").css("top", "");
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
      html: "Onaylı ve arşivlenmiş raporlar SGK'dan sorgulanıyor...",
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
              text: "Seçilen dönemde puantaja işlenecek SGK raporu bulunamadı.",
            });
            return;
          }

          showSgkRaporModal(
            res.data,
            "Puantaja İşlenecek SGK Raporları",
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

  function showSgkRaporModal(raporlar, title, toplam, eslesen) {
    $("#sgkRaporModalLabel").text(title);
    $("#sgkRaporModal .modal-title").text(title);

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

      // Onaylı ve arşivlenmiş raporlar aynı listede geldiği için kaynağı belirtilir
      const kaynakEtiketi =
        rapor.kaynak === "arsiv"
          ? `<span class="badge bg-secondary bg-opacity-25 text-secondary mt-1"><i class="mdi mdi-archive-outline me-1"></i>Arşiv (kısa süreli)</span>`
          : rapor.kaynak === "onayli"
            ? `<span class="badge bg-success bg-opacity-25 text-success mt-1"><i class="mdi mdi-check-circle-outline me-1"></i>Onaylı</span>`
            : "";

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
            ${kaynakEtiketi}
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
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
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
    const modal = bootstrap.Modal.getOrCreateInstance(
      document.getElementById("excelImportModal"),
    );
    modal.show();
  });

  // Şablon İndir / Excele Aktar - Mevcut verileri export eder
  $("#btn-download-template-modal, #btn-export-excel").on("click", function () {
    const ay = $("#select-ay").val();
    const yil = $("#select-yil").val();
    const departman = $("#select-departman").val() || "";
    const bolge = $("#select-bolge").val() || "";
    const iskur_dahil = $("#check-iskur-dahil").is(":checked") ? 1 : 0;

    // Sunucu tarafında PHPSpreadsheet ile oluşturup indir
    window.location.href = `${API_URL}?action=export-excel&ay=${ay}&yil=${yil}&departman=${encodeURIComponent(departman)}&bolge=${encodeURIComponent(bolge)}&iskur_dahil=${iskur_dahil}`;
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
  function sundayDatesForSelectedMonth() {
    const year = parseInt($("#select-yil").val(), 10);
    const month = parseInt($("#select-ay").val(), 10);
    const dates = [];
    for (let day = 1; day <= getDaysInMonth(month, year); day++) {
      const date = new Date(year, month - 1, day);
      if (date.getDay() === 0) dates.push(`${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`);
    }
    return dates;
  }

  function escapeHtcHtml(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
  }

  function loadSundayHtcPreview() {
    const date = $("#sunday-htc-date").val();
    if (!date) return;
    const $body = $("#sunday-htc-table tbody").html('<tr><td colspan="9" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Çalışmalar kontrol ediliyor...</td></tr>');
    $("#btn-assign-sunday-htc").prop("disabled", true);
    $.post(API_URL, { action: "get-sunday-work-preview", date }, function (res) {
      if (res.status !== "success") throw new Error(res.message || "Veriler alınamadı.");
      const rows = res.data.rows || [];
      if (!rows.length) {
        $body.html('<tr><td colspan="9" class="text-center text-muted py-5">Bu Pazar için operasyon kaydı bulunamadı.</td></tr>');
        $("#sunday-htc-summary").text("0 personel"); return;
      }
      $body.html(rows.map((row) => {
        const t = row.totals || {};
        const selectable = !row.blocked && !row.already_htc;
        const status = row.already_htc
          ? '<span class="badge bg-success-subtle text-success">HTÇ mevcut</span>'
          : row.blocked ? `<span class="badge bg-warning-subtle text-warning">${escapeHtcHtml(row.block_reason)}</span>`
          : row.qualified ? '<span class="badge bg-info-subtle text-info">Eşiği geçti</span>' : '<span class="badge bg-secondary-subtle text-secondary">Manuel seçim</span>';
        return `<tr>
          <td class="text-center"><input type="checkbox" class="htc-check sunday-htc-person" aria-label="${escapeHtcHtml(row.adi_soyadi)} için HTÇ seç" value="${row.id}" ${selectable && row.qualified ? "checked" : ""} ${selectable ? "" : "disabled"}></td>
          <td><div class="fw-semibold">${escapeHtcHtml(row.adi_soyadi)}</div><small class="text-muted">Ekip ${escapeHtcHtml(row.ekip_no || "-")}</small></td>
          <td>${escapeHtcHtml(row.departman || "-")}<br><small class="text-muted">${escapeHtcHtml(row.ekip_bolge || "-")}</small></td>
          <td class="text-center fw-semibold">${Number(t.okuma || 0).toLocaleString("tr-TR")}</td>
          <td class="text-center">${Number(t.kacak || 0).toLocaleString("tr-TR")}</td>
          <td class="text-center">${Number(t.sokme_takma || 0).toLocaleString("tr-TR")}</td>
          <td class="text-center">${Number(t.muhurleme || 0).toLocaleString("tr-TR")}</td>
          <td class="text-center"><span class="badge bg-light text-dark border">${escapeHtcHtml(row.current_code)}</span></td><td>${status}</td></tr>`;
      }).join(""));
      $("#sunday-htc-summary").text(`${rows.length} çalışan · ${rows.filter(r => r.qualified).length} eşik üstü`);
      const selectableCount = $(".sunday-htc-person:not(:disabled)").length;
      const checkedCount = $(".sunday-htc-person:checked").length;
      $("#sunday-htc-select-all").prop("checked", selectableCount > 0 && checkedCount === selectableCount).prop("indeterminate", checkedCount > 0 && checkedCount < selectableCount);
      $("#btn-assign-sunday-htc").prop("disabled", !$(".sunday-htc-person:checked").length);
    }, "json").fail((xhr) => {
      $body.html(`<tr><td colspan="9" class="text-center text-danger py-5">${escapeHtcHtml(xhr.responseJSON?.message || "Kontrol sırasında hata oluştu.")}</td></tr>`);
    });
  }

  $("#btn-open-sunday-htc-modal").on("click", function () {
    const dates = sundayDatesForSelectedMonth();
    const $select = $("#sunday-htc-date").empty();
    dates.forEach((date) => $select.append(new Option(new Date(date + "T12:00:00").toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric", weekday: "long" }), date)));
    $select.select2({ dropdownParent: $("#sundayHtcModal"), width: "100%" });
    bootstrap.Modal.getOrCreateInstance(document.getElementById("sundayHtcModal")).show();
    loadSundayHtcPreview();
  });
  $("#sunday-htc-date").on("change", loadSundayHtcPreview);
  $(document).on("change", ".sunday-htc-person", function () {
    const total = $(".sunday-htc-person:not(:disabled)").length;
    const checked = $(".sunday-htc-person:checked").length;
    $("#sunday-htc-select-all").prop("checked", total > 0 && checked === total).prop("indeterminate", checked > 0 && checked < total);
    $("#btn-assign-sunday-htc").prop("disabled", checked === 0);
  });
  $("#sunday-htc-select-all").on("change", function () { $(".sunday-htc-person:not(:disabled)").prop("checked", this.checked).trigger("change"); });
  $("#btn-assign-sunday-htc").on("click", function () {
    const ids = $(".sunday-htc-person:checked").map((_, el) => parseInt(el.value, 10)).get();
    const date = $("#sunday-htc-date").val();
    const $btn = $(this).prop("disabled", true);
    $.post(API_URL, { action: "assign-selected-sunday-htc", date, personel_ids: JSON.stringify(ids) }, function (res) {
      if (res.status !== "success") return Swal.fire("Hata", res.message || "HTÇ eklenemedi.", "error");
      Swal.fire("Tamamlandı", res.message, "success"); loadSundayHtcPreview(); renderTable();
    }, "json").fail((xhr) => Swal.fire("Hata", xhr.responseJSON?.message || "HTÇ eklenemedi.", "error")).always(() => $btn.prop("disabled", false));
  });
});
