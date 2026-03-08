/**
 * Görevler Modülü - Google Tasks Tarzı
 * Tüm CRUD, sürükle-bırak, tarih/yineleme işlemleri
 */
(function () {
  "use strict";

  const API_URL = "views/gorevler/api.php";
  let allData = []; // [{liste, gorevler, tamamlananlar}]
  let activeListeId = null; // sidebar seçili liste (null = tüm görevler)
  let filterYildizli = false; // yıldızlı filtresi aktif mi
  let lastFocusedForm = null; // Hangi formun aktif olduğunu takip et (Enter için)

  // =====================================================
  // SAYFA YÜKLEME
  // =====================================================
  $(document).ready(function () {
    loadAll();
    initGlobalEvents();
  });

  function loadAll() {
    $("#pagePreloader").removeClass("fade-out"); // Show preloader
    $.post(
      API_URL,
      { action: "get-tum-gorevler" },
      function (res) {
        if (res.success) {
          allData = res.data;
          renderSidebar();
          renderContent();
          initSortable();
        }
      },
      "json",
    );
  }

  // =====================================================
  // SIDEBAR RENDER
  // =====================================================
  function renderSidebar() {
    const nav = $("#sidebarNav");
    const listSection = nav.find(".liste-items");
    listSection.empty();

    // Toplam görev sayısı
    let totalAktif = 0;
    allData.forEach(
      (d) => (totalAktif += parseInt(d.liste.aktif_gorev_sayisi || 0)),
    );
    nav.find(".nav-tum-gorevler .badge").text(totalAktif || "");

    allData.forEach(function (item) {
      const renk = item.liste.renk || "var(--gt-primary)";
      const li = $(`
                <div class="nav-item liste-nav-item ${activeListeId === item.liste.id ? "active" : ""}" 
                     data-liste-id="${item.liste.id}">
                    <span class="liste-nav-renk" style="background:${renk}"></span>
                    <span class="liste-nav-baslik">${escHtml(item.liste.baslik)}</span>
                    <span class="badge">${item.liste.aktif_gorev_sayisi || ""}</span>
                </div>
            `);
      listSection.append(li);
    });
  }

  // =====================================================
  // İÇERİK RENDER (KOLON BAZLI)
  // =====================================================
  function renderContent() {
    const container = $("#gorevlerContent");
    container.empty();

    let dataToShow = activeListeId
      ? allData.filter((d) => d.liste.id === activeListeId)
      : allData;

    // Yıldızlı filtresi aktifse, sadece yıldızlı görevleri göster
    if (filterYildizli) {
      dataToShow = dataToShow
        .map(function (item) {
          return {
            liste: item.liste,
            gorevler: (item.gorevler || []).filter(function (g) {
              return g.yildizli == 1;
            }),
            tamamlananlar: (item.tamamlananlar || []).filter(function (g) {
              return g.yildizli == 1;
            }),
          };
        })
        .filter(function (item) {
          return item.gorevler.length > 0 || item.tamamlananlar.length > 0;
        });
    }

    if (dataToShow.length === 0) {
      container.html(`
                <div class="gorevler-empty">
                    <i class="bx ${filterYildizli ? "bx-star" : "bx-task"}"></i>
                    <h4>${filterYildizli ? "Yıldızlı görev yok" : "Henüz liste yok"}</h4>
                    <p>${filterYildizli ? "Yıldızladığınız görevler burada görünecek" : "Sol panelden yeni bir liste oluşturarak başlayın"}</p>
                </div>
            `);
      return;
    }

    dataToShow.forEach(function (item) {
      if (item.gorevler) {
        item.gorevler.sort((a, b) => b.id - a.id);
      }
      if (item.tamamlananlar) {
        item.tamamlananlar.sort((a, b) => b.id - a.id);
      }
      const kolon = buildListeKolon(item);
      container.append(kolon);
    });

    // Hide preloader after render
    setTimeout(() => {
      $("#pagePreloader").addClass("fade-out");
    }, 300);
  }

  function buildListeKolon(item) {
    const liste = item.liste;
    const gorevler = item.gorevler || [];
    const tamamlananlar = item.tamamlananlar || [];

    let gorevlerHtml = "";
    gorevler.forEach(function (g) {
      gorevlerHtml += buildGorevItem(g, false);
    });

    let tamamlananlarHtml = "";
    tamamlananlar.forEach(function (g) {
      tamamlananlarHtml += buildGorevItem(g, true);
    });

    const tamamSayi = tamamlananlar.length;

    const listeRenk = liste.renk || "var(--gt-primary)";

    return $(`
            <div class="gorev-liste-kolon" data-liste-id="${liste.id}" style="border-top: 3px solid ${listeRenk}; --card-color: ${listeRenk}">
                <div class="gorev-liste-header">
                    <h3>${escHtml(liste.baslik)}</h3>
                    <div style="position: relative;">
                        <button class="liste-menu-btn" data-liste-id="${liste.id}">
                            <i class="bx bx-dots-vertical"></i>
                        </button>
                    </div>
                </div>

                <button class="gorev-ekle-btn" data-liste-id="${liste.id}">
                    <i class="bx bx-edit"></i> Görev ekle
                </button>

                <div class="gorev-ekleme-form" data-liste-id="${liste.id}">
                    <input type="hidden" class="new-gorev-tarih" value="">
                    <input type="hidden" class="new-gorev-saat" value="">
                    <input type="hidden" class="new-gorev-yineleme" value="{}">
                    
                    <div class="gorev-baslik-container">
                        <div class="gorev-checkbox-placeholder"></div>
                        <textarea class="gorev-baslik-input auto-resize" placeholder="Başlık" data-liste-id="${liste.id}" rows="1"></textarea>
                    </div>
                    <textarea class="gorev-aciklama-input auto-resize" placeholder="Ayrıntılar" rows="1" data-liste-id="${liste.id}"></textarea>
                    <div class="gorev-form-actions">
                        <button class="form-action-btn btn-tarih-sec" data-liste-id="${liste.id}">
                            Bugün
                        </button>
                        <button class="form-action-btn btn-yarin-sec" data-liste-id="${liste.id}">
                            Yarın
                        </button>
                        <button class="form-action-btn btn-takvim-ac" data-liste-id="${liste.id}" title="Tarih seç">
                            <i class="bx bx-calendar"></i>
                        </button>
                        <button class="form-action-btn btn-yineleme-ac" data-liste-id="${liste.id}" title="Tekrarla">
                            <i class="bx bx-repeat"></i>
                        </button>
                    </div>
                </div>

                <div class="gorev-liste-body sortable-liste" data-liste-id="${liste.id}">
                    ${gorevlerHtml}
                </div>

                ${
                  tamamSayi > 0
                    ? `
                <div class="tamamlandi-section">
                    <button class="tamamlandi-toggle" data-liste-id="${liste.id}">
                        <i class="bx bx-chevron-down"></i>
                        Tamamlandı (${tamamSayi})
                    </button>
                    <div class="tamamlandi-list collapsed" data-liste-id="${liste.id}">
                        ${tamamlananlarHtml}
                    </div>
                </div>
                `
                    : ""
                }
            </div>
        `);
  }

  function buildGorevItem(g, tamamlandi) {
    let tarihBadge = "";
    if (g.tarih) {
      const tarihObj = new Date(g.tarih + "T00:00:00");
      const bugun = new Date();
      bugun.setHours(0, 0, 0, 0);
      const yarin = new Date(bugun);
      yarin.setDate(yarin.getDate() + 1);

      let cls = "gelecek";
      let label = "";

      if (tarihObj < bugun) {
        cls = "gecmis";
        // Hafta farkı hesapla
        const diffMs = bugun - tarihObj;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        if (diffDays < 7) {
          label = diffDays + " gün önce";
        } else {
          const diffWeeks = Math.floor(diffDays / 7);
          label = diffWeeks + " hafta önce";
        }
      } else if (tarihObj.getTime() === bugun.getTime()) {
        cls = "bugun";
        label = "Bugün";
      } else if (tarihObj.getTime() === yarin.getTime()) {
        cls = "gelecek";
        label = "Yarın";
      } else {
        // Format: "5 Mar Çar"
        const gunler = ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"];
        const aylar = [
          "Oca",
          "Şub",
          "Mar",
          "Nis",
          "May",
          "Haz",
          "Tem",
          "Ağu",
          "Eyl",
          "Eki",
          "Kas",
          "Ara",
        ];
        label =
          tarihObj.getDate() +
          " " +
          aylar[tarihObj.getMonth()] +
          " " +
          gunler[tarihObj.getDay()];
      }

      if (tamamlandi && g.tamamlanma_tarihi) {
        const tamamTarih = new Date(g.tamamlanma_tarihi);
        const aylar = [
          "Oca",
          "Şub",
          "Mar",
          "Nis",
          "May",
          "Haz",
          "Tem",
          "Ağu",
          "Eyl",
          "Eki",
          "Kas",
          "Ara",
        ];
        const gunler = ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"];
        label =
          "Tamamlandı: " +
          tamamTarih.getDate() +
          " " +
          aylar[tamamTarih.getMonth()] +
          " " +
          gunler[tamamTarih.getDay()];
        cls = "gelecek";
      }

      let displayLabel = label;
      if (g.saat) {
        displayLabel += ", " + g.saat.substring(0, 5);
      }

      tarihBadge = `<span class="gorev-tarih-badge ${cls}"><i class="bx bx-calendar"></i> ${displayLabel} <span class="btn-clear-date" data-gorev-id="${g.id}" title="Temizle"><i class="bx bx-x"></i></span></span>`;
    }

    const yinelemeIcon = g.yineleme_sikligi
      ? '<i class="bx bx-repeat gorev-yineleme-icon" title="Yinelenen görev"></i>'
      : "";

    const aciklamaHtml = g.aciklama
      ? `<div class="gorev-aciklama-text">${escHtml(g.aciklama)}</div>`
      : "";

    return `
            <div class="gorev-item" data-gorev-id="${g.id}" data-liste-id="${g.liste_id}">
                <div class="gorev-checkbox" data-gorev-id="${g.id}" data-tamamlandi="${tamamlandi ? 1 : 0}" title="${tamamlandi ? "Geri al" : "Tamamla"}"></div>
                <div class="gorev-info">
                    <div class="gorev-baslik">${escHtml(g.baslik)}</div>
                    ${aciklamaHtml}
                    <div class="gorev-meta">
                        ${tarihBadge}
                        ${yinelemeIcon}
                    </div>
                </div>
                <div class="gorev-actions">
                    <button class="gorev-action-btn btn-gorev-menu" data-gorev-id="${g.id}">
                        <i class="bx bx-dots-vertical"></i>
                    </button>
                    <button class="gorev-action-btn btn-gorev-yildiz ${g.yildizli == 1 ? "active" : ""}" data-gorev-id="${g.id}">
                        <i class="bx ${g.yildizli == 1 ? "bxs-star" : "bx-star"}" style="${g.yildizli == 1 ? "color:#f4b400" : ""}"></i>
                    </button>
                </div>
            </div>
        `;
  }

  // =====================================================
  // GLOBAL EVENT HANDLERS
  // =====================================================
  function initGlobalEvents() {
    // Sidebar navigasyon
    $(document).on("click", ".nav-tum-gorevler", function () {
      activeListeId = null;
      filterYildizli = false;
      $(".nav-item").removeClass("active");
      $(this).addClass("active");
      renderContent();
      initSortable();
    });

    // Yıldızlı görevler filtresi
    $(document).on("click", ".nav-yildizli", function () {
      activeListeId = null;
      filterYildizli = true;
      $(".nav-item").removeClass("active");
      $(this).addClass("active");
      renderContent();
      initSortable();
    });

    $(document).on("click", ".liste-nav-item", function () {
      activeListeId = $(this).data("liste-id");
      filterYildizli = false;
      $(".nav-item").removeClass("active");
      $(this).addClass("active");
      renderContent();
      initSortable();
    });

    // Yeni liste oluştur - modal aç
    $(document).on("click", "#btnYeniListe, #btnYeniListe2", function () {
      $("#yeniListeBaslik").val("");
      $("#yeniListeRenk").val("");
      $(".renk-secici-item").removeClass("active");
      $("#yeniListeModal").addClass("show");
      setTimeout(() => $("#yeniListeBaslik").focus(), 100);
    });

    // Yeni liste modal iptal
    $(document).on("click", "#yeniListeIptal", function () {
      $("#yeniListeModal").removeClass("show");
    });

    // Renk seçici (hem yeni liste hem isim değiştirme için)
    $(document).on("click", ".renk-secici-item", function () {
      const parent = $(this).closest(".renk-secici");
      parent.find(".renk-secici-item").removeClass("active");
      $(this).addClass("active");

      if (parent.hasClass("clr-rename")) {
        $("#listeRenameRenk").val($(this).data("renk"));
      } else {
        $("#yeniListeRenk").val($(this).data("renk"));
      }
    });

    // Yeni liste oluştur - kaydet
    $(document).on("click", "#yeniListeOlustur", function () {
      const baslik = $("#yeniListeBaslik").val().trim();
      if (!baslik) {
        showToast("Liste adı boş olamaz!", "error");
        return;
      }
      const renk = $("#yeniListeRenk").val() || null;

      $.post(
        API_URL,
        { action: "add-liste", baslik: baslik, renk: renk },
        function (res) {
          if (res.success) {
            $("#yeniListeModal").removeClass("show");
            showToast("Liste oluşturuldu", "success");
            loadAll();
          } else {
            showToast(res.message, "error");
          }
        },
        "json",
      );
    });

    // Yeni liste - Enter ile kaydet
    $(document).on("keydown", "#yeniListeBaslik", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $("#yeniListeOlustur").click();
      }
      if (e.key === "Escape") {
        $("#yeniListeModal").removeClass("show");
      }
    });

    // Hangi formun aktif olduğunu takip et (Enter için)
    $(document).on(
      "focusin",
      ".gorev-ekleme-form, .inline-edit-form, .gorev-baslik-input, .gorev-aciklama-input, .edit-gorev-baslik, .edit-gorev-aciklama",
      function () {
        lastFocusedForm = $(this).closest(
          ".gorev-ekleme-form, .inline-edit-form",
        );
      },
    );

    // Görev ekle butonu toggle
    $(document).on("click", ".gorev-ekle-btn", function () {
      const listeId = $(this).data("liste-id");
      const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);

      // Diğer aktif "yeni görev" formlarını kapat (kalabalık yapmasın)
      $(".gorev-ekleme-form.active").not(form).removeClass("active");

      form.toggleClass("active");
      if (form.hasClass("active")) {
        form.find(".gorev-baslik-input").focus();
        lastFocusedForm = form;
      }
    });

    // Bu kısım silindi, global keydown handler (aşağıda) tarafından yönetilecek.

    // Form İptal butonu
    $(document).on("click", ".btn-gorev-iptal", function () {
      const listeId = $(this).data("liste-id");
      $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`).removeClass("active");
    });

    // Form Kaydet butonu
    $(document).on("click", ".btn-gorev-kaydet", function () {
      const listeId = $(this).data("liste-id");
      submitGorev(listeId);
    });

    // Tarih kısayolları
    $(document).on("click", ".btn-tarih-sec", function () {
      const today = formatDate(new Date());
      const $form = $(this).closest(".gorev-ekleme-form");
      $form.find(".new-gorev-tarih").val(today);
      $form.find(".btn-tarih-sec").addClass("has-value");
      $form.find(".btn-yarin-sec").removeClass("has-value");
      $form
        .find(".btn-takvim-ac")
        .removeClass("has-value")
        .html('<i class="bx bx-calendar"></i>');
    });

    $(document).on("click", ".btn-yarin-sec", function () {
      const yarin = new Date();
      yarin.setDate(yarin.getDate() + 1);
      const formatted = formatDate(yarin);
      const $form = $(this).closest(".gorev-ekleme-form");
      $form.find(".new-gorev-tarih").val(formatted);
      $form.find(".btn-yarin-sec").addClass("has-value");
      $form.find(".btn-tarih-sec").removeClass("has-value");
      $form
        .find(".btn-takvim-ac")
        .removeClass("has-value")
        .html('<i class="bx bx-calendar"></i>');
    });

    // Takvim aç
    $(document).on("click", ".btn-takvim-ac", function () {
      const listeId = $(this).data("liste-id");
      openTarihPicker(listeId);
    });

    // Yineleme aç
    $(document).on("click", ".btn-yineleme-ac", function () {
      const listeId = $(this).data("liste-id");
      openYinelemeModal(listeId);
    });

    // Görev tamamla/geri al
    $(document).on("click", ".gorev-checkbox", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");
      const tamamlandi = $(this).data("tamamlandi");
      const action = tamamlandi == 1 ? "geri-al" : "tamamla";

      // Animasyon
      const item = $(this).closest(".gorev-item");
      if (action === "tamamla") {
        $(this).css({
          background: "var(--gt-primary)",
          borderColor: "var(--gt-primary)",
        });
        $(this).html(
          '<span style="color:#fff;font-size:11px;font-weight:700">✓</span>',
        );
        item
          .find(".gorev-baslik")
          .css({ textDecoration: "line-through", color: "#80868b" });
      }

      setTimeout(() => {
        $.post(
          API_URL,
          { action: action, gorev_id: gorevId },
          function (res) {
            if (res.success) {
              loadAll();
            } else {
              showToast(res.message, "error");
            }
          },
          "json",
        );
      }, 300);
    });

    // Tamamlandı toggle
    $(document).on("click", ".tamamlandi-toggle", function () {
      const listeId = $(this).data("liste-id");
      const list = $(`.tamamlandi-list[data-liste-id="${listeId}"]`);
      $(this).toggleClass("collapsed");
      list.toggleClass("collapsed");

      if (!list.hasClass("collapsed")) {
        list.css("max-height", list[0].scrollHeight + "px");
      }
    });

    // Liste menü toggle
    $(document).on("click", ".liste-menu-btn", function (e) {
      e.stopPropagation();
      const $btn = $(this);
      const listeId = $btn.data("liste-id");

      // Mevcut menüleri kaldır
      $(".gorev-dropdown").remove();

      const offset = $btn.offset();
      const dropdownHeight = 90; // Yaklaşık yükseklik
      const windowHeight = $(window).height();
      const spaceBelow = windowHeight - offset.top - $btn.outerHeight();

      // Eğer altta yer yoksa yukarı aç
      let topPos = offset.top + 35;
      if (spaceBelow < dropdownHeight) {
        topPos = offset.top - dropdownHeight + 5;
      }

      const dropdown = $(`
        <div class="gorev-dropdown liste-dropdown show" style="top: ${topPos}px; left: ${offset.left - 130}px;">
            <button class="dropdown-item btn-liste-yeniden-adlandir" data-liste-id="${listeId}">
                <i class="bx bx-edit"></i> Yeniden adlandır
            </button>
            <button class="dropdown-item danger btn-liste-sil" data-liste-id="${listeId}">
                <i class="bx bx-trash"></i> Listeyi sil
            </button>
        </div>
      `);

      $("body").append(dropdown);
    });

    // Görev menüsü
    $(document).on("click", ".btn-gorev-menu", function (e) {
      e.stopPropagation();
      const $btn = $(this);
      const gorevId = $btn.data("gorev-id");

      // Mevcut menüleri kaldır
      $(".gorev-item-dropdown").remove();

      const offset = $btn.offset();
      const dropdownHeight = 90; // Yaklaşık yükseklik
      const windowHeight = $(window).height();
      const spaceBelow = windowHeight - offset.top - $btn.outerHeight();

      // Eğer altta yer yoksa yukarı aç
      let topPos = offset.top + 35;
      if (spaceBelow < dropdownHeight) {
        topPos = offset.top - dropdownHeight + 5;
      }

      const dropdown = $(`
        <div class="gorev-dropdown gorev-item-dropdown show" style="top: ${topPos}px; left: ${offset.left - 180}px;">
            <button class="dropdown-item btn-gorev-kullanici-sec" data-gorev-id="${gorevId}">
                <i class="bx bx-user-plus"></i> Kullanıcılar
            </button>
            <button class="dropdown-item btn-gorev-sil" data-gorev-id="${gorevId}">
                <i class="bx bx-trash"></i> Sil
            </button>
        </div>
      `);

      $("body").append(dropdown);
    });

    // Görev sil (Doğrudan silme + Geri al)
    let deleteTimers = {};
    $(document).on("click", ".btn-gorev-sil", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");
      const $item = $(`.gorev-item[data-gorev-id="${gorevId}"]`);

      // UI'dan anında gizle
      $item.fadeOut(300);
      $(".gorev-item-dropdown").remove();

      // Undo Toast Göster
      const toast = Toastify({
        text: `Görev silindi. <a href="#" class="undo-delete" data-id="${gorevId}" style="color:#fff;text-decoration:underline;margin-left:10px;font-weight:700">Geri Al</a>`,
        duration: 5000,
        escapeMarkup: false,
        gravity: "bottom",
        position: "center",
        style: {
          background: "linear-gradient(135deg, #c5221f, #ea4335)",
          borderRadius: "6px",
        },
      }).showToast();

      // 5 saniye sonra gerçekten sil
      deleteTimers[gorevId] = setTimeout(() => {
        $.post(
          API_URL,
          { action: "delete-gorev", gorev_id: gorevId },
          function (res) {
            if (!res.success) showToast(res.message, "error");
            delete deleteTimers[gorevId];
          },
          "json",
        );
      }, 5000);
    });

    // Silmeyi Geri Al
    $(document).on("click", ".undo-delete", function (e) {
      e.preventDefault();
      const id = $(this).data("id");
      if (deleteTimers[id]) {
        clearTimeout(deleteTimers[id]);
        delete deleteTimers[id];
        $(`.gorev-item[data-gorev-id="${id}"]`).stop().fadeIn(300);
        $(this).closest(".toastify").remove();
        showToast("İşlem geri alındı", "success");
      }
    });

    // Görev Kullanıcı Seç (Modal Aç)
    $(document).on("click", ".btn-gorev-kullanici-sec", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");
      $(".gorev-dropdown").removeClass("show");
      openGorevKullaniciSecModal(gorevId);
    });

    // Görev Kullanıcı Seç İptal
    $(document).on("click", "#gorevKullaniciSecIptal", function () {
      $("#gorevKullaniciSecModal").removeClass("show");
    });

    // Görev Kullanıcı Seç Kaydet
    $(document).on("click", "#gorevKullaniciSecKaydet", function () {
      saveGorevKullaniciSec();
    });

    // Yıldız toggle
    $(document).on("click", ".btn-gorev-yildiz", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");
      const isActive = $(this).hasClass("active");
      const newVal = isActive ? 0 : 1;

      $.post(
        API_URL,
        { action: "update-gorev", gorev_id: gorevId, yildizli: newVal },
        function (res) {
          if (res.success) {
            loadAll();
          }
        },
        "json",
      );
    });

    // Liste yeniden adlandır - modal aç
    $(document).on("click", ".btn-liste-yeniden-adlandir", function (e) {
      e.stopPropagation();
      const listeId = $(this).data("liste-id");
      const currentName = $(
        `.gorev-liste-kolon[data-liste-id="${listeId}"] .gorev-liste-header h3`,
      ).text();

      // Listeyi bulup rengini al
      const listeData = allData.find((d) => d.liste.id === listeId);
      const currentRenk =
        listeData && listeData.liste.renk ? listeData.liste.renk : "";

      $(".gorev-dropdown").removeClass("show");

      $("#listeRenameId").val(listeId);
      $("#listeRenameBaslik").val(currentName);
      $("#listeRenameRenk").val(currentRenk);

      // Renk seçiciyi güncelle
      $(".clr-rename .renk-secici-item").removeClass("active");
      if (currentRenk) {
        $(`.clr-rename .renk-secici-item[data-renk="${currentRenk}"]`).addClass(
          "active",
        );
      }

      $("#listeRenameModal").addClass("show");
      setTimeout(() => $("#listeRenameBaslik").focus(), 100);
    });

    // Liste yeniden adlandır modal iptal
    $(document).on("click", "#listeRenameIptal", function () {
      $("#listeRenameModal").removeClass("show");
    });

    // Liste yeniden adlandır - kaydet
    $(document).on("click", "#listeRenameKaydet", function () {
      const listeId = $("#listeRenameId").val();
      const baslik = $("#listeRenameBaslik").val().trim();
      const renk = $("#listeRenameRenk").val() || null;

      if (!baslik) {
        showToast("Liste adı boş olamaz!", "error");
        return;
      }

      $.post(
        API_URL,
        {
          action: "update-liste",
          liste_id: listeId,
          baslik: baslik,
          renk: renk,
        },
        function (res) {
          if (res.success) {
            $("#listeRenameModal").removeClass("show");
            showToast("Liste adı güncellendi", "success");
            loadAll();
          } else {
            showToast(res.message, "error");
          }
        },
        "json",
      );
    });

    // Liste yeniden adlandır - Enter ile kaydet
    $(document).on("keydown", "#listeRenameBaslik", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $("#listeRenameKaydet").click();
      }
      if (e.key === "Escape") {
        $("#listeRenameModal").removeClass("show");
      }
    });

    // Liste sil
    $(document).on("click", ".btn-liste-sil", function (e) {
      e.stopPropagation();
      const listeId = $(this).data("liste-id");
      $(".gorev-dropdown").removeClass("show");

      Swal.fire({
        title: "Liste Silinsin mi?",
        text: "Bu liste ve içindeki tüm görevler kalıcı olarak silinecektir.",
        icon: "warning",
        showCancelButton: true,
        cancelButtonText: "İptal",
        confirmButtonText: "Sil",
        confirmButtonColor: "#c5221f",
      }).then((result) => {
        if (result.isConfirmed) {
          $.post(
            API_URL,
            { action: "delete-liste", liste_id: listeId },
            function (res) {
              if (res.success) {
                if (activeListeId === listeId) activeListeId = null;
                showToast("Liste silindi", "success");
                loadAll();
              } else {
                showToast(res.message, "error");
              }
            },
            "json",
          );
        }
      });
    });

    // Görev kartına tıklama - Düzenle (Inline)
    $(document).on("click", ".gorev-item", function (e) {
      if (
        $(e.target).closest(
          ".gorev-checkbox, .gorev-actions, .inline-edit-form",
        ).length
      )
        return;

      const $item = $(this);

      // Eğer bir butonun veya inputun içine tıkladıysa iptal et
      if ($(e.target).closest("button, input, textarea").length) return;

      if ($item.hasClass("editing")) return;

      // Diğerlerini kapat
      $(".gorev-item.editing").each(function () {
        closeInlineEdit($(this));
      });

      const gorevId = $item.data("gorev-id");
      const listeId = $item.data("liste-id");

      const listeData = allData.find((d) => d.liste.id === listeId);
      if (!listeData) return;

      const g =
        listeData.gorevler.find((v) => v.id === gorevId) ||
        listeData.tamamlananlar.find((v) => v.id === gorevId);
      if (!g) return;

      $item.addClass("editing");

      const yinelemeData = {
        sikligi: g.yineleme_sikligi,
        birimi: g.yineleme_birimi,
        gunleri: g.yineleme_gunleri,
        baslangic: g.yineleme_baslangic,
        bitis_tipi: g.yineleme_bitis_tipi,
        bitis_tarihi: g.yineleme_bitis_tarihi,
        bitis_adet: g.yineleme_bitis_adet,
      };

      const hasYineleme = !!g.yineleme_sikligi;
      const yinelemeColor = hasYineleme
        ? 'style="color: var(--gt-primary)"'
        : "";

      const editFormHtml = `
        <div class="inline-edit-form gorev-ekleme-form active edit-mode">
            <input type="hidden" class="edit-gorev-id" value="${g.id}">
            <div class="gorev-baslik-container">
                <div class="gorev-checkbox-placeholder"></div>
                <textarea class="gorev-baslik-input edit-gorev-baslik auto-resize" rows="1" placeholder="Başlık">${escHtml(g.baslik)}</textarea>
            </div>
            <textarea class="gorev-aciklama-input edit-gorev-aciklama auto-resize" rows="1" placeholder="Ayrıntılar">${escHtml(g.aciklama || "")}</textarea>
            
            <div class="gorev-form-actions">
                <button type="button" class="form-action-btn edit-btn-tarih-bugun ${g.tarih ? "" : ""}" data-gorev-id="${g.id}">Bugün</button>
                <button type="button" class="form-action-btn edit-btn-tarih-yarin" data-gorev-id="${g.id}">Yarın</button>
                <button type="button" class="form-action-btn edit-btn-takvim" data-gorev-id="EDIT_${g.id}" title="Tarih seç">
                    <i class="bx bx-calendar"></i>
                </button>
                <button type="button" class="form-action-btn edit-btn-yineleme ${hasYineleme ? "has-value" : ""}" data-gorev-id="EDIT_${g.id}" title="Tekrarla">
                    <i class="bx bx-repeat" ${yinelemeColor}></i>
                </button>
            </div>

            <input type="hidden" class="edit-tarih-val" value="${g.tarih || ""}">
            <input type="hidden" class="edit-saat-val" value="${g.saat || ""}">
            <input type="hidden" class="edit-yineleme-val" value='${JSON.stringify(yinelemeData)}'>
        </div>
      `;

      // CSS halledecek (display: none), JS ile müdahale etmiyoruz
      // $item.find(".gorev-info, .gorev-checkbox, .gorev-actions").hide();
      $item.append(editFormHtml);
      const $titleInput = $item.find(".edit-gorev-baslik");
      $titleInput.focus();

      // Boyutu içeriğe göre ilkle
      $item.find(".auto-resize").trigger("input");
    });

    // closeInlineEdit artık modül seviyesinde tanımlı (aşağıda)

    $(document).on("click", ".btn-edit-iptal", function (e) {
      e.stopPropagation();
      closeInlineEdit($(this).closest(".gorev-item"));
    });

    // Inline form: Bugün
    $(document).on("click", ".edit-btn-tarih-bugun", function (e) {
      e.stopPropagation();
      const $form = $(this).closest(".inline-edit-form");
      const today = formatDate(new Date());
      $form.find(".edit-tarih-val").val(today);
      $form.find(".edit-btn-tarih-bugun").addClass("has-value");
      $form.find(".edit-btn-tarih-yarin").removeClass("has-value");
    });

    // Inline form: Yarın
    $(document).on("click", ".edit-btn-tarih-yarin", function (e) {
      e.stopPropagation();
      const $form = $(this).closest(".inline-edit-form");
      const yarin = new Date();
      yarin.setDate(yarin.getDate() + 1);
      const formatted = formatDate(yarin);
      $form.find(".edit-tarih-val").val(formatted);
      $form.find(".edit-btn-tarih-yarin").addClass("has-value");
      $form.find(".edit-btn-tarih-bugun").removeClass("has-value");
    });

    // Inline form: Tarih aç
    $(document).on("click", ".edit-btn-takvim", function (e) {
      e.stopPropagation();
      const editId = $(this).data("gorev-id");
      openTarihPicker(editId);
    });

    // Inline form: Yineleme aç
    $(document).on("click", ".edit-btn-yineleme", function (e) {
      e.stopPropagation();
      const editId = $(this).data("gorev-id");
      openYinelemeModal(editId);
    });

    // Global Enter/Escape handler
    $(document).on("keydown", function (e) {
      // Flatpickr veya modal açıksa müdahale etme
      if (
        $(".flatpickr-calendar.open").length ||
        $(".tarih-picker-modal.show").length ||
        $(".yineleme-modal.show").length ||
        $(".swal2-container").length ||
        $(".modal.show").length
      )
        return;

      // Escape — iptal
      if (e.which === 27) {
        // 1. Önce aktif inline editi kapatmayı dene
        const $editingItem = $(".gorev-item.editing");
        if ($editingItem.length) {
          e.preventDefault();
          closeInlineEdit($editingItem);
          return;
        }
        // 2. Yeni görev formunu kapatmayı dene
        const $activeForm = $(".gorev-ekleme-form.active:not(.edit-mode)");
        if ($activeForm.length) {
          e.preventDefault();
          $activeForm.removeClass("active");
          return;
        }
      }

      // Enter — kaydet (Shift yoksa)
      if (e.which === 13 && !e.shiftKey) {
        // Textarea içindeysen ve Shift yoksa kaydetmek istiyoruz

        // 1. Odaklanmış bir form varsa onu kaydet
        if (
          lastFocusedForm &&
          lastFocusedForm.is(":visible") &&
          (lastFocusedForm.hasClass("active") ||
            lastFocusedForm.closest(".gorev-item").hasClass("editing"))
        ) {
          if (lastFocusedForm.hasClass("inline-edit-form")) {
            e.preventDefault();
            saveInlineEdit(lastFocusedForm);
            return;
          } else if (lastFocusedForm.hasClass("gorev-ekleme-form")) {
            e.preventDefault();
            const lId = lastFocusedForm.attr("data-liste-id");
            submitGorev(lId);
            return;
          }
        }

        // 2. Odak yoksa ama tek bir açık form varsa onu kaydet
        const $editingItem = $(".gorev-item.editing");
        if ($editingItem.length === 1) {
          const $form = $editingItem.find(".inline-edit-form");
          if ($form.length) {
            e.preventDefault();
            saveInlineEdit($form);
            return;
          }
        }

        const $activeForms = $(".gorev-ekleme-form.active:not(.edit-mode)");
        if ($activeForms.length === 1) {
          e.preventDefault();
          const listeId = $activeForms.attr("data-liste-id");
          submitGorev(listeId);
          return;
        }
      }
    });

    function saveInlineEdit($form) {
      const gorevId = $form.find(".edit-gorev-id").val();
      const baslik = $form.find(".edit-gorev-baslik").val().trim();
      const aciklama = $form.find(".edit-gorev-aciklama").val().trim();
      const tarih = $form.find(".edit-tarih-val").val();
      const saat = $form.find(".edit-saat-val").val();
      let yineleme = {};

      try {
        const yData = $form.find(".edit-yineleme-val").val();
        if (yData && yData !== "{}") {
          yineleme = JSON.parse(yData);
        }
      } catch (err) {}

      if (!baslik) {
        closeInlineEdit($form.closest(".gorev-item"));
        return;
      }

      const postData = {
        action: "update-gorev",
        gorev_id: gorevId,
        baslik: baslik,
        aciklama: aciklama || "",
        tarih: tarih || "",
        saat: saat || "",
        yineleme_sikligi: yineleme.sikligi || "",
        yineleme_birimi: yineleme.birimi || "",
        yineleme_gunleri: yineleme.gunleri || "",
        yineleme_baslangic: yineleme.baslangic || "",
        yineleme_bitis_tipi: yineleme.bitis_tipi || "",
        yineleme_bitis_tarihi: yineleme.bitis_tarihi || "",
        yineleme_bitis_adet: yineleme.bitis_adet || "",
      };

      $.post(
        API_URL,
        postData,
        function (res) {
          if (res.success) {
            showToast("Görev güncellendi", "success");
            loadAll();
          } else {
            showToast(res.message, "error");
          }
        },
        "json",
      );
    }
    // Tıklama ile menü kapat
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".liste-menu-btn, .gorev-dropdown").length) {
        $(".gorev-dropdown").removeClass("show");
      }
      if (
        !$(e.target).closest(".gorev-action-btn, .gorev-item-dropdown").length
      ) {
        $(".gorev-item-dropdown").remove();
      }
    });
  }

  // =====================================================
  // INLINE EDIT KAPAT (Modül seviyesi — her yerden erişilebilir)
  // =====================================================
  function closeInlineEdit($item) {
    if (!$item || !$item.length) return;
    $item.each(function () {
      const $el = $(this);
      if (!$el.hasClass("editing")) return;
      $el.removeClass("editing");
      $el.find(".inline-edit-form").remove();
      // Yönlendirmeler CSS tarafından yönetiliyor, .show() kaldırıldı
    });
    // Zorla focusu başka yere at ki aksiyon ikonları takılı kalmasın
    if (document.activeElement) {
      document.activeElement.blur();
    }
  }

  // =====================================================
  // GÖREV EKLEME
  // =====================================================
  function submitGorev(listeId) {
    const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
    const baslik = form.find(".gorev-baslik-input").val().trim();
    const aciklama = form.find(".gorev-aciklama-input").val().trim();

    if (!baslik) return;

    // Tarih
    let tarih = form.find(".new-gorev-tarih").val() || "";
    let saat = form.find(".new-gorev-saat").val() || "";

    // Debug amaçlı gizli alanların doluluğunu kontrol edelim
    // console.log("Submitting task:", { baslik, tarih, saat });

    // Yineleme
    let yinelemeData = {};
    try {
      const yStr = form.find(".new-gorev-yineleme").val();
      if (yStr && yStr !== "{}") {
        yinelemeData = JSON.parse(yStr);
      }
    } catch (e) {}

    const postData = {
      action: "add-gorev",
      liste_id: listeId,
      baslik: baslik,
      aciklama: aciklama || "",
      tarih: tarih,
      saat: saat,
      yineleme_sikligi: yinelemeData.sikligi || "",
      yineleme_birimi: yinelemeData.birimi || "",
      yineleme_gunleri: yinelemeData.gunleri || "",
      yineleme_baslangic: yinelemeData.baslangic || "",
      yineleme_bitis_tipi: yinelemeData.bitis_tipi || "",
      yineleme_bitis_tarihi: yinelemeData.bitis_tarihi || "",
      yineleme_bitis_adet: yinelemeData.bitis_adet || "",
    };

    $.post(
      API_URL,
      postData,
      function (res) {
        if (res.success) {
          // Formu temizle
          form.find(".gorev-baslik-input").val("");
          form.find(".gorev-aciklama-input").val("");
          form.find(".new-gorev-tarih").val("");
          form.find(".new-gorev-saat").val("");
          form.find(".new-gorev-yineleme").val("{}");
          form.find(".form-action-btn").removeClass("has-value");
          form.find(".btn-tarih-sec").text("Bugün");
          form.find(".btn-yarin-sec").text("Yarın");
          form.find(".btn-yineleme-ac").find("i").css("color", "");

          // Focus tekrar başlığa
          form.find(".gorev-baslik-input").focus();
          lastFocusedForm = form;

          showToast("Görev başarıyla eklendi", "success");
          loadAll();
        } else {
          showToast(res.message, "error");
        }
      },
      "json",
    );
  }

  // =====================================================
  // TARİH PICKER MODAL
  // =====================================================
  let tarihPickerInstance = null;
  let currentTarihListeId = null;

  function openTarihPicker(listeId) {
    currentTarihListeId = listeId;
    const modal = $("#tarihPickerModal");
    modal.addClass("show");

    // Mevcut tarih
    let existingDate, existingSaat;
    const isEdit = String(listeId).startsWith("EDIT_");

    if (isEdit) {
      const id = listeId.replace("EDIT_", "");
      const form = $(`.gorev-item[data-gorev-id="${id}"] .inline-edit-form`);
      existingDate = form.find(".edit-tarih-val").val();
      existingSaat = form.find(".edit-saat-val").val() || "";
    } else {
      const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
      existingDate = form.find(".new-gorev-tarih").val();
      existingSaat = form.find(".new-gorev-saat").val() || "";
    }

    if (tarihPickerInstance) {
      tarihPickerInstance.destroy();
    }

    tarihPickerInstance = flatpickr("#tarihPickerCalendar", {
      inline: true,
      locale: "tr",
      defaultDate: existingDate || new Date(),
      dateFormat: "Y-m-d",
    });

    // Saat input doldur
    $("#tarihSaatInput").val(existingSaat);
  }

  $(document).on("click", "#tarihPickerIptal", function () {
    $("#tarihPickerModal").removeClass("show");
  });

  $(document).on("click", "#tarihPickerBitti", function () {
    if (!currentTarihListeId) return;

    const selectedDate = tarihPickerInstance.selectedDates[0];
    const isEdit = String(currentTarihListeId).startsWith("EDIT_");

    let form;
    if (isEdit) {
      const id = currentTarihListeId.replace("EDIT_", "");
      form = $(`.gorev-item[data-gorev-id="${id}"] .inline-edit-form`);
    } else {
      form = $(`.gorev-ekleme-form[data-liste-id="${currentTarihListeId}"]`);
    }

    const saat = $("#tarihSaatInput").val();

    if (selectedDate) {
      const formatted = formatDate(selectedDate);
      let label = formatDateDisplay(formatted);

      if (saat) {
        label += ", " + saat;
      }

      if (isEdit) {
        form.find(".edit-tarih-val").val(formatted);
        form
          .find(".edit-btn-takvim")
          .addClass("has-value")
          .html(
            `<i class="bx bx-calendar"></i> ${label} <span class="btn-clear-date" title="Temizle"><i class="bx bx-x"></i></span>`,
          );
      } else {
        form.find(".new-gorev-tarih").val(formatted);
        form.find(".btn-tarih-sec, .btn-yarin-sec").removeClass("has-value");
        form
          .find(".btn-takvim-ac")
          .addClass("has-value")
          .html(
            `<i class="bx bx-calendar"></i> ${label} <span class="btn-clear-date" title="Temizle"><i class="bx bx-x"></i></span>`,
          );
      }
    }

    if (isEdit) {
      form.find(".edit-saat-val").val(saat || "");
    } else if (saat) {
      form.find(".new-gorev-saat").val(saat);
    }

    $("#tarihPickerModal").removeClass("show");
  });

  // Tekrarla butonu: tarih picker'dan yineleme modal'ı aç
  $(document).on("click", "#tarihPickerTekrarla", function () {
    $("#tarihPickerModal").removeClass("show");
    openYinelemeModal(currentTarihListeId);
  });

  // =====================================================
  // YİNELEME MODAL
  // =====================================================
  let currentYinelemeListeId = null;

  function openYinelemeModal(listeId) {
    currentYinelemeListeId = listeId;
    const modal = $("#yinelemeModal");
    modal.addClass("show");

    let yineleme = {};
    const isEdit = String(listeId).startsWith("EDIT_");

    if (isEdit) {
      const id = listeId.replace("EDIT_", "");
      const form = $(`.gorev-item[data-gorev-id="${id}"] .inline-edit-form`);
      try {
        yineleme = JSON.parse(form.find(".edit-yineleme-val").val());
      } catch (e) {}
    } else {
      const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
      try {
        const yStr = form.find(".new-gorev-yineleme").val();
        if (yStr && yStr !== "{}") {
          yineleme = JSON.parse(yStr);
        }
      } catch (e) {}
    }

    $("#yinelemeSikligi").val(yineleme.sikligi || 1);
    const birim = yineleme.birimi || "gun";
    $("#yinelemeBirimi").val(birim);

    // Hafta Günlerini sıfırla ve doldur
    $(".gun-daire").removeClass("active");
    if (birim === "hafta") {
      $("#yinelemeHaftaGunleri").show();
      const gunler = yineleme.gunleri
        ? String(yineleme.gunleri).split(",")
        : [];
      gunler.forEach((g) => {
        $(`.gun-daire[data-gun="${g}"]`).addClass("active");
      });
    } else {
      $("#yinelemeHaftaGunleri").hide();
    }

    $("#yinelemeBaslangic").val(yineleme.baslangic || formatDate(new Date()));
    $(
      'input[name="yinelemeBitisTipi"][value="' +
        (yineleme.bitis_tipi || "asla") +
        '"]',
    ).prop("checked", true);
    $("#yinelemeBitisTarihi").val(yineleme.bitis_tarihi || "");
    $("#yinelemeBitisAdet").val(yineleme.bitis_adet || 30);
  }

  $(document).on("click", "#yinelemeIptal", function () {
    $("#yinelemeModal").removeClass("show");
  });

  $(document).on("click", "#yinelemeBitti", function () {
    if (!currentYinelemeListeId) return;

    const bitisTipi = $('input[name="yinelemeBitisTipi"]:checked').val();
    const birim = $("#yinelemeBirimi").val();

    // Seçili günleri topla
    const gunler = [];
    if (birim === "hafta") {
      $(".gun-daire.active").each(function () {
        gunler.push($(this).data("gun"));
      });
    }

    const yinelemeData = {
      sikligi: $("#yinelemeSikligi").val(),
      birimi: birim,
      gunleri: gunler.join(","),
      baslangic: $("#yinelemeBaslangic").val(),
      bitis_tipi: bitisTipi,
      bitis_tarihi:
        bitisTipi === "tarih" ? $("#yinelemeBitisTarihi").val() : null,
      bitis_adet: bitisTipi === "adet" ? $("#yinelemeBitisAdet").val() : null,
    };

    const isEdit = String(currentYinelemeListeId).startsWith("EDIT_");

    if (isEdit) {
      const id = currentYinelemeListeId.replace("EDIT_", "");
      const form = $(`.gorev-item[data-gorev-id="${id}"] .inline-edit-form`);
      form.find(".edit-yineleme-val").val(JSON.stringify(yinelemeData));
      form
        .find(".edit-btn-yineleme")
        .addClass("has-value")
        .find("i")
        .css("color", "var(--gt-primary)");
    } else {
      const form = $(
        `.gorev-ekleme-form[data-liste-id="${currentYinelemeListeId}"]`,
      );
      form.find(".new-gorev-yineleme").val(JSON.stringify(yinelemeData));
      form
        .find(".btn-yineleme-ac")
        .addClass("has-value")
        .find("i")
        .css("color", "var(--gt-primary)");
    }

    $("#yinelemeModal").removeClass("show");
  });

  // =====================================================
  // SÜRÜKLE BIRAK (SortableJS)
  // =====================================================
  function initSortable() {
    // Liste kolonları sürükle-bırak
    const contentEl = document.getElementById("gorevlerContent");
    if (contentEl) {
      if (contentEl._sortable) contentEl._sortable.destroy();
      contentEl._sortable = new Sortable(contentEl, {
        animation: 200,
        ghostClass: "kolon-sortable-ghost",
        chosenClass: "kolon-sortable-chosen",
        dragClass: "kolon-sortable-drag",
        handle: ".gorev-liste-header",
        filter: ".liste-menu-btn",
        preventOnFilter: false,
        direction: "horizontal",
        onEnd: function () {
          saveListeSira();
        },
      });
    }

    // Liste body'leri için sortable (görev sıralaması + listeler arası)
    document.querySelectorAll(".sortable-liste").forEach(function (el) {
      if (el._sortable) el._sortable.destroy();

      el._sortable = new Sortable(el, {
        group: "gorevler",
        animation: 150,
        ghostClass: "sortable-ghost",
        chosenClass: "sortable-chosen",
        dragClass: "sortable-drag",
        handle: ".gorev-item",
        filter:
          ".tamamlandi-section, .gorev-actions, .gorev-checkbox, .inline-edit-form, input, textarea",
        preventOnFilter: false,
        onEnd: function (evt) {
          saveGorevSira();
        },
      });
    });
  }

  function saveGorevSira() {
    const gorevler = [];

    document.querySelectorAll(".sortable-liste").forEach(function (liste) {
      const listeId = liste.dataset.listeId;
      const items = liste.querySelectorAll(".gorev-item");

      items.forEach(function (item, index) {
        gorevler.push({
          id: item.dataset.gorevId,
          liste_id: listeId,
          sira: index,
        });
      });
    });

    if (gorevler.length > 0) {
      $.post(
        API_URL,
        {
          action: "update-sira",
          gorevler: JSON.stringify(gorevler),
        },
        function (res) {
          if (!res.success) {
            console.error("Sıra güncelleme hatası:", res.message);
          }
        },
        "json",
      );
    }
  }

  function saveListeSira() {
    const kolonlar = document.querySelectorAll(".gorev-liste-kolon");
    const siralar = [];
    kolonlar.forEach(function (kolon, index) {
      siralar.push({
        id: kolon.dataset.listeId,
        sira: index,
      });
    });

    if (siralar.length > 0) {
      // Sidebar senkronizasyonu için allData'yı da güncellemeliyiz (Optimistik)
      const dataMap = {};
      allData.forEach((d) => (dataMap[d.liste.id] = d));

      const newAllData = [];
      siralar.forEach((s) => {
        if (dataMap[s.id]) {
          newAllData.push(dataMap[s.id]);
        }
      });
      allData = newAllData;
      renderSidebar();

      $.post(
        API_URL,
        {
          action: "update-liste-sira",
          siralar: JSON.stringify(siralar),
        },
        function (res) {
          if (!res.success) {
            console.error("Liste sıra güncelleme hatası:", res.message);
          }
        },
        "json",
      );
    }
  }

  // =====================================================
  // YARDIMCI FONKSİYONLAR
  // =====================================================
  function escHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function formatDateDisplay(dateStr) {
    if (!dateStr) return "Tarih / Saat";
    const tarihObj = new Date(dateStr + "T00:00:00");
    const aylar = [
      "Oca",
      "Şub",
      "Mar",
      "Nis",
      "May",
      "Haz",
      "Tem",
      "Ağu",
      "Eyl",
      "Eki",
      "Kas",
      "Ara",
    ];
    return tarihObj.getDate() + " " + aylar[tarihObj.getMonth()];
  }

  function showToast(msg, type) {
    const bg =
      type === "success"
        ? "linear-gradient(135deg, #1a73e8, #4285f4)"
        : type === "error"
          ? "linear-gradient(135deg, #c5221f, #ea4335)"
          : "linear-gradient(135deg, #5f6368, #80868b)";

    Toastify({
      text: msg,
      duration: 3000,
      gravity: "bottom",
      position: "center",
      style: { background: bg, borderRadius: "6px" },
      stopOnFocus: true,
    }).showToast();
  }

  // =====================================================
  // BİLDİRİM (ALARM) SİSTEMİ - İSTEMCİ TARAFI
  // =====================================================
  let knownAlarms = {}; // Görev ID'sine göre kurulan setTimeout ID'lerini tutar

  function checkUpcomingAlarms() {
    $.post(
      API_URL,
      { action: "get-upcoming-alarms" },
      function (res) {
        if (res.success && res.data && res.data.length > 0) {
          const now = new Date().getTime();

          res.data.forEach((task) => {
            if (!task.saat) return;

            const taskDateTimeStr = `${task.tarih}T${task.saat.length === 5 ? task.saat + ":00" : task.saat}`;
            const taskTime = new Date(taskDateTimeStr).getTime();
            const timeDiff = taskTime - now;

            // Eğer görevin saati geçmiş ama sunucu henüz işaretlememişse veya
            // görev 5 dakika içindeyse alarm kur/tetikle
            if (timeDiff <= 5 * 60 * 1000 && timeDiff > -60000) {
              // Daha önce alarm kurulmamışsa
              if (!knownAlarms[task.id]) {
                const delay = timeDiff > 0 ? timeDiff : 0; // Geçmişse hemen çal

                knownAlarms[task.id] = setTimeout(() => {
                  fireTaskNotification(task);
                  delete knownAlarms[task.id];
                }, delay);
              }
            }
          });
        }
      },
      "json",
    ).fail(function () {
      // API fail ignore for interval silently
    });
  }

  function fireTaskNotification(task) {
    // 1. Ekrana bildirim düşür
    const toastHtml = `
      <div style="display:flex; align-items:center; gap:12px;">
         <i class="bx bx-bell" style="font-size:24px; color:#fbbc04;"></i>
         <div>
            <div style="font-weight:600; font-size:14px; margin-bottom:2px;">Görev Zamanı Geldi</div>
            <div style="font-size:13px; color:rgba(255,255,255,0.9);">${escHtml(task.baslik)}</div>
            <div style="font-size:11px; margin-top:4px; opacity:0.8;">[${escHtml(task.liste_adi || "Tüm Görevler")}] - ${task.saat ? task.saat.substring(0, 5) : ""}</div>
         </div>
      </div>
    `;

    Toastify({
      text: toastHtml,
      duration: 10000, // 10 saniye ekranda kalsın
      close: true,
      gravity: "top",
      position: "right",
      escapeMarkup: false,
      style: {
        background: "#202124",
        color: "#fff",
        borderRadius: "8px",
        boxShadow: "0 4px 12px rgba(0,0,0,0.15)",
        minWidth: "300px",
        padding: "16px",
      },
    }).showToast();

    // Browser Notification API
    if (Notification.permission === "granted") {
      new Notification("Görev Zamanı: " + task.baslik, {
        body: task.liste_adi
          ? `Liste: ${task.liste_adi} - Saat: ${task.saat.substring(0, 5)}`
          : `Saat: ${task.saat.substring(0, 5)}`,
        icon: "/assets/images/logo-sm.png",
      });
    }

    // 2. Sunucuya "bildirim gönderildi" bilgisini geç
    $.post(
      API_URL,
      { action: "mark-notified", gorev_id: task.id },
      function (res) {},
    );
  }

  // =====================================================
  // AUTO RESIZE TEXTAREA
  // =====================================================
  $(document).on("input", ".auto-resize", function () {
    this.style.height = "auto";
    this.style.height = this.scrollHeight + "px";
  });

  // Yeni bir textarea eklendiğinde (render) boyutunu ilkle
  function initAutoResize() {
    $(".auto-resize").each(function () {
      this.style.height = "auto";
      this.style.height = this.scrollHeight + "px";
    });
  }

  $(document).on("focus", ".auto-resize", function () {
    this.style.height = "auto";
    this.style.height = this.scrollHeight + "px";
  });

  $(document).ready(function () {
    if (
      "Notification" in window &&
      Notification.permission !== "granted" &&
      Notification.permission !== "denied"
    ) {
      Notification.requestPermission();
    }

    $("#gorevAyarlarKaydet").on("click", function () {
      saveGorevSettings();
    });

    // İlk açılışta ve her 1 dakikada bir yaklaşan görevleri kontrol et
    checkUpcomingAlarms();
    setInterval(checkUpcomingAlarms, 60000);

    // Ayarlar Butonu
    $(document).on("click", "#btnGorevAyarlar", function () {
      openSettingsModal();
    });

    $("#gorevAyarlarIptal").on("click", function () {
      $("#gorevAyarlarModal").removeClass("show");
    });

    // Sidebar Toggle
    const sidebarState = localStorage.getItem("gorev_sidebar_collapsed");
    if (sidebarState === "true") {
      $(".gorevler-sidebar").addClass("collapsed");
      $("#btnSidebarToggle i")
        .removeClass("bx-chevron-left")
        .addClass("bx-chevron-right");
    }

    $(document).on("click", "#btnSidebarToggle", function () {
      const $sidebar = $(".gorevler-sidebar");
      $sidebar.toggleClass("collapsed");
      const isCollapsed = $sidebar.hasClass("collapsed");
      localStorage.setItem("gorev_sidebar_collapsed", isCollapsed);

      const $icon = $(this).find("i");
      if (isCollapsed) {
        $icon.removeClass("bx-chevron-left").addClass("bx-chevron-right");
      } else {
        $icon.removeClass("bx-chevron-right").addClass("bx-chevron-left");
      }
    });

    // Boşluğa tıklayınca (düzenleme modundan çıkma)
    $(document).on("mousedown", function (e) {
      if ($(".gorev-item.editing").length > 0) {
        // Formun dışına veya özel bir alana tıklanmadığını kontrol et
        if (
          !$(e.target).closest(
            ".gorev-item.editing, .tarih-picker-modal, .yineleme-modal, .flatpickr-calendar, .toastify, .select2-container",
          ).length
        ) {
          closeInlineEdit($(".gorev-item.editing"));
        }
      }
    });
  });

  function openSettingsModal() {
    const modal = $("#gorevAyarlarModal");
    const select = $("#set_gorev_bildirim_kullanicilar");

    // Her açılışta güncel veriyi (ve taze şifreli ID'leri) çek
    $.post(API_URL, { action: "get-settings" }, function (res) {
      if (res.success) {
        // Dakika ayarı
        $("#set_gorev_bildirim_dakika").val(res.data.gorev_bildirim_dakika);

        // Kullanıcı listesini temizle ve yeniden oluştur (Şifreli ID'ler her seferinde değiştiği için tazelenmeli)
        select.empty();
        const selectedValues = [];

        res.data.users.forEach((u) => {
          const option = new Option(u.text, u.id, u.selected, u.selected);
          select.append(option);
          if (u.selected) {
            selectedValues.push(u.id);
          }
        });

        // Select2 tazele
        if ($.fn.select2) {
          if (select.hasClass("select2-hidden-accessible")) {
            select.select2("destroy");
          }
          select.select2({
            dropdownParent: modal.find(".yeni-liste-content"),
            placeholder: "Kullanıcı seçin",
            width: "100%",
          });
        }

        select.val(selectedValues).trigger("change");
        modal.addClass("show");
      }
    });
  }

  function saveGorevSettings() {
    // ... (logic stays the same but now sends IDs that the server will decrypt)
    const dakika = $("#set_gorev_bildirim_dakika").val();
    const kullanicilar = $("#set_gorev_bildirim_kullanicilar").val() || [];

    $.post(
      API_URL,
      {
        action: "save-settings",
        gorev_bildirim_dakika: dakika,
        gorev_bildirim_kullanicilar: Array.isArray(kullanicilar)
          ? kullanicilar.join(",")
          : kullanicilar,
      },
      function (res) {
        if (res.success) {
          showToast(res.message, "success");
          $("#gorevAyarlarModal").removeClass("show");
        } else {
          showToast(res.message, "error");
        }
      },
    );
  }

  function openGorevKullaniciSecModal(gorevId) {
    const modal = $("#gorevKullaniciSecModal");
    const select = $("#set_gorev_ozel_kullanicilar");
    $("#gorevKullaniciSecGorevId").val(gorevId);

    $.post(
      API_URL,
      { action: "get-settings-for-task", gorev_id: gorevId },
      function (res) {
        if (res.success) {
          select.empty();
          const selectedValues = [];

          res.data.users.forEach((u) => {
            const option = new Option(u.text, u.id, u.selected, u.selected);
            select.append(option);
            if (u.selected) {
              selectedValues.push(u.id);
            }
          });

          if ($.fn.select2) {
            if (select.hasClass("select2-hidden-accessible")) {
              select.select2("destroy");
            }
            select.select2({
              dropdownParent: modal.find(".yeni-liste-content"),
              placeholder: "Kullanıcı seçin",
              width: "100%",
            });
          }

          select.val(selectedValues).trigger("change");
          modal.addClass("show");
        }
      },
    );
  }

  function saveGorevKullaniciSec() {
    const gorevId = $("#gorevKullaniciSecGorevId").val();
    const kullanicilar = $("#set_gorev_ozel_kullanicilar").val() || [];

    $.post(
      API_URL,
      {
        action: "update-gorev",
        gorev_id: gorevId,
        gorev_kullanicilari: Array.isArray(kullanicilar)
          ? kullanicilar.join(",")
          : kullanicilar,
      },
      function (res) {
        if (res.success) {
          showToast("Görev kullanıcıları güncellendi", "success");
          $("#gorevKullaniciSecModal").removeClass("show");
          loadAll();
        } else {
          showToast(res.message, "error");
        }
      },
      "json",
    );
  }

  // Tarih Temizle
  $(document).on("click", ".btn-clear-date", function (e) {
    e.stopPropagation();
    const gorevId = $(this).data("gorev-id");
    const isEdit = $(this).closest(".inline-edit-form").length > 0;
    const form = isEdit
      ? $(this).closest(".inline-edit-form")
      : $(this).closest(".gorev-ekleme-form");

    if (gorevId && !isEdit) {
      // Statik görevdeki badge'den temizleme
      $.post(
        API_URL,
        { action: "update-gorev", gorev_id: gorevId, tarih: null, saat: null },
        function (res) {
          if (res.success) {
            loadAll();
          }
        },
        "json",
      );
      return;
    }

    if (isEdit) {
      form.find(".edit-tarih-val").val("");
      form.find(".edit-saat-val").val("");
      form
        .find(".edit-btn-takvim")
        .removeClass("has-value")
        .html('<i class="bx bx-calendar"></i>');
    } else {
      form.find(".new-gorev-tarih").val("");
      form.find(".new-gorev-saat").val("");
      form
        .find(".btn-takvim-ac")
        .removeClass("has-value")
        .html('<i class="bx bx-calendar"></i>');
    }
  });

  // Hafta Günleri Seçimi
  $(document).on("click", ".gun-daire", function () {
    $(this).toggleClass("active");
  });

  $("#yinelemeBirimi").on("change", function () {
    if ($(this).val() === "hafta") {
      $("#yinelemeHaftaGunleri").show();
    } else {
      $("#yinelemeHaftaGunleri").hide();
    }
  });
  // Drag-to-Scroll (Tıklayıp sürükleyerek kaydırma)
  const $content = $(".gorevler-content");
  let isDown = false;
  let startX;
  let scrollLeft;

  // mousedown'ı document seviyesine taşırsak veya delegasyon kullanırsak dinamik içerikte daha iyi çalışır
  $(document).on("mousedown", ".gorevler-content", function (e) {
    // Sadece direkt konteynere veya sürüklenemeyen alanlara tıklanırsa çalışsın
    // Input, buton veya kart içindeysek scroll tetiklenmesin
    if (
      $(e.target).closest(
        ".gorev-item, .gorev-liste-header, button, input, textarea, .gorev-checkbox, .liste-menu-btn",
      ).length
    )
      return;

    isDown = true;
    $content.addClass("dragging-active");
    startX = e.pageX - $content.offset().left;
    scrollLeft = $content.scrollLeft();
  });

  $(document).on("mouseup mouseleave", function () {
    if (!isDown) return;
    isDown = false;
    $content.removeClass("dragging-active");
  });

  $(document).on("mousemove", function (e) {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - $content.offset().left;
    const walk = (x - startX) * 2; // Kaydırma hızı çarpanı
    $content.scrollLeft(scrollLeft - walk);
  });
})();
