/**
 * Görevler Modülü - Google Tasks Tarzı
 * Tüm CRUD, sürükle-bırak, tarih/yineleme işlemleri
 */
(function () {
  "use strict";

  const API_URL = "views/gorevler/api.php";
  let allData = []; // [{liste, gorevler, tamamlananlar}]
  let activeListeId = null; // sidebar seçili liste (null = tüm görevler)

  // =====================================================
  // SAYFA YÜKLEME
  // =====================================================
  $(document).ready(function () {
    loadAll();
    initGlobalEvents();
  });

  function loadAll() {
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

    const dataToShow = activeListeId
      ? allData.filter((d) => d.liste.id === activeListeId)
      : allData;

    if (dataToShow.length === 0) {
      container.html(`
                <div class="gorevler-empty">
                    <i class="bx bx-task"></i>
                    <h4>Henüz liste yok</h4>
                    <p>Sol panelden yeni bir liste oluşturarak başlayın</p>
                </div>
            `);
      return;
    }

    dataToShow.forEach(function (item) {
      const kolon = buildListeKolon(item);
      container.append(kolon);
    });
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
            <div class="gorev-liste-kolon" data-liste-id="${liste.id}" style="border-top: 3px solid ${listeRenk}">
                <div class="gorev-liste-header">
                    <h3>${escHtml(liste.baslik)}</h3>
                    <div style="position: relative;">
                        <button class="liste-menu-btn" data-liste-id="${liste.id}">
                            <i class="bx bx-dots-vertical"></i>
                        </button>
                        <div class="gorev-dropdown liste-dropdown" data-liste-id="${liste.id}">
                            <button class="dropdown-item btn-liste-yeniden-adlandir" data-liste-id="${liste.id}">
                                <i class="bx bx-edit"></i> Yeniden adlandır
                            </button>
                            <button class="dropdown-item danger btn-liste-sil" data-liste-id="${liste.id}">
                                <i class="bx bx-trash"></i> Listeyi sil
                            </button>
                        </div>
                    </div>
                </div>

                <button class="gorev-ekle-btn" data-liste-id="${liste.id}">
                    <i class="bx bx-edit"></i> Görev ekle
                </button>

                <div class="gorev-ekleme-form" data-liste-id="${liste.id}">
                    <div class="gorev-baslik-container">
                        <div class="gorev-checkbox-placeholder"></div>
                        <input type="text" class="gorev-baslik-input" placeholder="Başlık" data-liste-id="${liste.id}">
                    </div>
                    <textarea class="gorev-aciklama-input" placeholder="Ayrıntılar" rows="1" data-liste-id="${liste.id}"></textarea>
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

      tarihBadge = `<span class="gorev-tarih-badge ${cls}"><i class="bx bx-calendar"></i> ${label}</span>`;
    }

    const yinelemeIcon = g.yineleme_sikligi
      ? '<i class="bx bx-repeat gorev-yineleme-icon" title="Yinelenen görev"></i>'
      : "";

    return `
            <div class="gorev-item" data-gorev-id="${g.id}" data-liste-id="${g.liste_id}">
                <div class="gorev-checkbox" data-gorev-id="${g.id}" data-tamamlandi="${tamamlandi ? 1 : 0}" title="${tamamlandi ? "Geri al" : "Tamamla"}"></div>
                <div class="gorev-info">
                    <div class="gorev-baslik">${escHtml(g.baslik)}</div>
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
      $(".nav-item").removeClass("active");
      $(this).addClass("active");
      renderContent();
      initSortable();
    });

    $(document).on("click", ".liste-nav-item", function () {
      activeListeId = $(this).data("liste-id");
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

    // Görev ekle butonu toggle
    $(document).on("click", ".gorev-ekle-btn", function () {
      const listeId = $(this).data("liste-id");
      const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
      form.toggleClass("active");
      if (form.hasClass("active")) {
        form.find(".gorev-baslik-input").focus();
      }
    });

    // Görev ekleme - Enter ile
    $(document).on("keydown", ".gorev-baslik-input", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        const listeId = $(this).data("liste-id");
        submitGorev(listeId);
      }
      if (e.key === "Escape") {
        const listeId = $(this).data("liste-id");
        $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`).removeClass(
          "active",
        );
      }
    });

    // Tarih kısayolları
    $(document).on("click", ".btn-tarih-sec", function () {
      const listeId = $(this).data("liste-id");
      const today = formatDate(new Date());
      $(this).data("tarih", today).addClass("has-value").text("Bugün");
    });

    $(document).on("click", ".btn-yarin-sec", function () {
      const listeId = $(this).data("liste-id");
      const yarin = new Date();
      yarin.setDate(yarin.getDate() + 1);
      const formatted = formatDate(yarin);
      const btn = $(
        `.gorev-form-actions[data-liste-id="${listeId}"] .btn-tarih-sec, .btn-tarih-sec[data-liste-id="${listeId}"]`,
      ).first();

      // btn-yarin-sec'e tarih verisi ekle
      $(this).data("tarih", formatted).addClass("has-value");
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
      const listeId = $(this).data("liste-id");
      $(`.liste-dropdown[data-liste-id="${listeId}"]`).toggleClass("show");
    });

    // Görev menüsü
    $(document).on("click", ".btn-gorev-menu", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");
      const item = $(this).closest(".gorev-item");

      // Mevcut menüyü kaldır
      $(".gorev-item-dropdown").remove();

      const dropdown = $(`
                <div class="gorev-dropdown gorev-item-dropdown show" style="top: 0; right: 40px;">
                    <button class="dropdown-item btn-gorev-sil" data-gorev-id="${gorevId}">
                        <i class="bx bx-trash"></i> Sil
                    </button>
                </div>
            `);

      item.css("position", "relative").append(dropdown);
    });

    // Görev sil
    $(document).on("click", ".btn-gorev-sil", function (e) {
      e.stopPropagation();
      const gorevId = $(this).data("gorev-id");

      Swal.fire({
        title: "Görev Silinsin mi?",
        text: "Bu görev kalıcı olarak silinecektir.",
        icon: "warning",
        showCancelButton: true,
        cancelButtonText: "İptal",
        confirmButtonText: "Sil",
        confirmButtonColor: "#c5221f",
      }).then((result) => {
        if (result.isConfirmed) {
          $.post(
            API_URL,
            { action: "delete-gorev", gorev_id: gorevId },
            function (res) {
              if (res.success) {
                showToast("Görev silindi", "success");
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
      if ($item.hasClass("editing")) return;

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
                <input type="text" class="gorev-baslik-input edit-gorev-baslik" value="${escHtml(g.baslik)}" placeholder="Başlık">
            </div>
            <textarea class="gorev-aciklama-input edit-gorev-aciklama" rows="1" placeholder="Ayrıntılar">${escHtml(g.aciklama || "")}</textarea>
            
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

            <div class="d-flex justify-content-end gap-2 mt-3 pe-2 pb-2">
                <button type="button" class="btn btn-sm btn-edit-iptal" style="border:none; background:#f1f3f4; color:#5f6368; font-weight: 500;">İptal</button>
                <button type="button" class="btn btn-sm btn-primary btn-edit-kaydet" style="background:#202124; border:none; padding: 4px 16px; border-radius: 4px; font-weight: 500;">Kaydet</button>
            </div>
        </div>
      `;

      $item.find(".gorev-info, .gorev-checkbox, .gorev-actions").hide();
      $item.append(editFormHtml);
      $item.find(".edit-gorev-baslik").focus();
    });

    function closeInlineEdit($item) {
      $item.removeClass("editing");
      $item.find(".inline-edit-form").remove();
      $item.find(".gorev-info, .gorev-checkbox, .gorev-actions").show();
    }

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

    $(document).on("click", ".btn-edit-kaydet", function (e) {
      e.stopPropagation();
      const $form = $(this).closest(".inline-edit-form");

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
        showToast("Başlık boş olamaz!", "error");
        return;
      }

      const postData = {
        action: "update-gorev",
        gorev_id: gorevId,
        baslik: baslik,
        aciklama: aciklama || null,
        tarih: tarih || null,
        saat: saat || null,
        yineleme_sikligi: yineleme.sikligi || null,
        yineleme_birimi: yineleme.birimi || null,
        yineleme_baslangic: yineleme.baslangic || null,
        yineleme_bitis_tipi: yineleme.bitis_tipi || null,
        yineleme_bitis_tarihi: yineleme.bitis_tarihi || null,
        yineleme_bitis_adet: yineleme.bitis_adet || null,
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
    });

    // Enter/Escape handlers for Edit Form
    $(document).on("keydown", ".edit-gorev-baslik", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        $(this).closest(".inline-edit-form").find(".btn-edit-kaydet").click();
      }
      if (e.key === "Escape") {
        $(this).closest(".inline-edit-form").find(".btn-edit-iptal").click();
      }
    });
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
  // GÖREV EKLEME
  // =====================================================
  function submitGorev(listeId) {
    const form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
    const baslik = form.find(".gorev-baslik-input").val().trim();
    const aciklama = form.find(".gorev-aciklama-input").val().trim();

    if (!baslik) return;

    // Tarih
    let tarih = form.data("selected-tarih") || null;
    let saat = form.data("selected-saat") || null;

    // Bugün/Yarın butonları
    const bugunBtn = form.find(".btn-tarih-sec");
    const yarinBtn = form.find(".btn-yarin-sec");
    if (bugunBtn.hasClass("has-value") && !tarih) {
      tarih = bugunBtn.data("tarih");
    }
    if (yarinBtn.hasClass("has-value") && !tarih) {
      tarih = yarinBtn.data("tarih");
    }

    // Yineleme
    let yinelemeData = form.data("yineleme") || {};

    const postData = {
      action: "add-gorev",
      liste_id: listeId,
      baslik: baslik,
      aciklama: aciklama || null,
      tarih: tarih,
      saat: saat,
      yineleme_sikligi: yinelemeData.sikligi || null,
      yineleme_birimi: yinelemeData.birimi || null,
      yineleme_baslangic: yinelemeData.baslangic || null,
      yineleme_bitis_tipi: yinelemeData.bitis_tipi || null,
      yineleme_bitis_tarihi: yinelemeData.bitis_tarihi || null,
      yineleme_bitis_adet: yinelemeData.bitis_adet || null,
    };

    $.post(
      API_URL,
      postData,
      function (res) {
        if (res.success) {
          // Formu temizle
          form.find(".gorev-baslik-input").val("");
          form.find(".gorev-aciklama-input").val("");
          form.removeData("selected-tarih");
          form.removeData("selected-saat");
          form.removeData("yineleme");
          form.find(".form-action-btn").removeClass("has-value");
          form.find(".btn-tarih-sec").text("Bugün");
          form.find(".btn-yarin-sec").text("Yarın");
          form.find(".btn-yineleme-ac").find("i").css("color", "");

          // Focus tekrar başlığa
          form.find(".gorev-baslik-input").focus();

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
      form = $(`.gorev-ekleme-form[data-liste-id="${listeId}"]`);
      existingDate = form.data("selected-tarih");
      existingSaat = form.data("selected-saat") || "";
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

    if (selectedDate) {
      const formatted = formatDate(selectedDate);
      const label = formatDateDisplay(formatted);

      if (isEdit) {
        form.find(".edit-tarih-val").val(formatted);
        form.find(".edit-tarih-label").text(label);
      } else {
        form.data("selected-tarih", formatted);
        form
          .find(".btn-takvim-ac")
          .addClass("has-value")
          .html(`<i class="bx bx-calendar"></i> ${label}`);
      }
    }

    const saat = $("#tarihSaatInput").val();
    if (isEdit) {
      form.find(".edit-saat-val").val(saat || "");
    } else if (saat) {
      form.data("selected-saat", saat);
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
      yineleme = form.data("yineleme") || {};
    }

    $("#yinelemeSikligi").val(yineleme.sikligi || 1);
    $("#yinelemeBirimi").val(yineleme.birimi || "gun");
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
    const yinelemeData = {
      sikligi: $("#yinelemeSikligi").val(),
      birimi: $("#yinelemeBirimi").val(),
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
      form.data("yineleme", yinelemeData);
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
        filter: ".tamamlandi-section, .gorev-actions, .gorev-checkbox",
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
      gravity: "top",
      position: "right",
      style: { background: bg },
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

  $(document).ready(function () {
    if (
      "Notification" in window &&
      Notification.permission !== "granted" &&
      Notification.permission !== "denied"
    ) {
      Notification.requestPermission();
    }

    // İlk açılışta ve her 1 dakikada bir yaklaşan görevleri kontrol et
    checkUpcomingAlarms();
    setInterval(checkUpcomingAlarms, 60000);
  });
})();
