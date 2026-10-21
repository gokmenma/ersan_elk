$(document).ready(function () {
  const form = $("#gidenEvrakForm");
  let pdfObjectUrl = null;
  let nextFileKey = 1;
  let attachments = (window.gidenExistingAttachments || []).map(item => ({ ...item, type: "existing" }));
  const removedAttachmentIds = [];

  $(".giden-select2").select2({ width: "100%", placeholder: "Seçiniz..." });
  $(".giden-select2-tags").select2({ width: "100%", tags: true, placeholder: "Seçiniz veya Yazınız..." });

  // --- İmza Yetkilileri Yönetimi (Modern & Kullanıcı Dostu) ---
  const allSigningUsers = window.gidenSigningUsersList || [];
  let selectedSigners = (window.gidenInitialSelectedSigners || []).slice(0, 3);
  const signerAddSelect = $("#imza_kullanici_ekle_select");

  function getSignerInitials(name) {
    if (!name) return "İ";
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function escapeHtml(text) {
    if (!text) return "";
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function syncSignerState() {
    // 1. Gizli select'i senkronize et (Form submit ve PDF preview için)
    const hiddenSelect = $("#imza_kullanici_ids").empty();
    selectedSigners.forEach(s => {
      hiddenSelect.append(new Option(s.name, s.id, true, true));
    });
    hiddenSelect.val(selectedSigners.map(s => s.id));

    // 2. Gizli kimin_adina_1, kimin_adina_2, kimin_adina_3 alanlarını güncelle
    $("#kimin_adina_1").val(selectedSigners[0]?.kimin_adina || "");
    $("#kimin_adina_2").val(selectedSigners[1]?.kimin_adina || "");
    $("#kimin_adina_3").val(selectedSigners[2]?.kimin_adina || "");

    // 3. Sayaç rozetini güncelle
    const count = selectedSigners.length;
    const badge = $("#imzaSecimSayac");
    badge.text(count + "/3 SEÇİLDİ");
    if (count === 3) {
      badge.removeClass("bg-light text-muted bg-primary-subtle text-primary").addClass("bg-success-subtle text-success");
    } else if (count === 0) {
      badge.removeClass("bg-success-subtle text-success bg-primary-subtle text-primary").addClass("bg-light text-muted");
    } else {
      badge.removeClass("bg-success-subtle text-success bg-light text-muted").addClass("bg-primary-subtle text-primary");
    }

    // 4. Ekleme Dropdown'ını güncelle (seçilenleri seçeneklerden gizle)
    signerAddSelect.empty();
    signerAddSelect.append(new Option(count >= 3 ? "Maksimum 3 yetkili seçildi" : "+ İmza Yetkilisi Seç ve Ekle...", "", true, true));

    const selectedIds = selectedSigners.map(s => s.id);
    allSigningUsers.forEach(user => {
      if (!selectedIds.includes(user.id)) {
        const titleText = user.title ? " — " + user.title : "";
        signerAddSelect.append(new Option(user.name + titleText, user.id));
      }
    });

    if (count >= 3 || window.gidenEvrakKilitli) {
      signerAddSelect.prop("disabled", true);
      $("#imzaSeciciContainer").addClass("opacity-50");
    } else {
      signerAddSelect.prop("disabled", false);
      $("#imzaSeciciContainer").removeClass("opacity-50");
    }

    if (signerAddSelect.data("select2")) {
      signerAddSelect.trigger("change.select2");
    }

    // 5. Kart listesini çiz
    renderSignerOrderList();
  }

  function renderSignerOrderList() {
    const container = $("#imzaSiraListesi").empty();
    if (!selectedSigners || selectedSigners.length === 0) {
      container.html(`
        <div class="p-3 text-muted small text-center bg-light-subtle border border-dashed rounded-3">
          <i class="bx bx-user-plus font-size-20 d-block mb-1 text-primary"></i>
          Henüz imza yetkilisi seçilmedi.<br>
          <span class="text-muted font-size-11">Yukarıdaki listeden en fazla 3 yetkili ekleyebilirsiniz.</span>
        </div>
      `);
      return;
    }

    const total = selectedSigners.length;

    selectedSigners.forEach((signer, index) => {
      let posText = "Sol";
      if (total === 2) {
        posText = index === 0 ? "Sol" : "Sağ";
      } else if (total === 3) {
        posText = index === 0 ? "Sol" : (index === 1 ? "Orta" : "Sağ");
      }

      const initials = getSignerInitials(signer.name);
      const isLocked = window.gidenEvrakKilitli;

      const card = $(`
        <div class="card border shadow-none mb-0 signer-card" style="background:#fff; border-radius:8px; border-color:#e2e8f0!important;" data-id="${escapeHtml(signer.id)}">
          <div class="card-body p-2.5">
            <div class="d-flex align-items-center justify-content-between mb-1.5">
              <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-primary text-white fw-bold px-2 py-1" style="font-size:10px;">
                  ${index + 1}. İmza (${posText})
                </span>
              </div>
              ${!isLocked ? `
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary btn-sm px-1.5 py-0 btn-signer-move" data-index="${index}" data-dir="-1" title="Yukarı / Sola Taşı" ${index === 0 ? 'disabled' : ''} style="font-size:11px">
                    <i class="bx bx-chevron-up"></i>
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm px-1.5 py-0 btn-signer-move" data-index="${index}" data-dir="1" title="Aşağı / Sağa Taşı" ${index === total - 1 ? 'disabled' : ''} style="font-size:11px">
                    <i class="bx bx-chevron-down"></i>
                  </button>
                  <button type="button" class="btn btn-outline-danger btn-sm px-1.5 py-0 btn-signer-remove" data-index="${index}" title="Kaldır" style="font-size:11px">
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              ` : ''}
            </div>

            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:30px;height:30px;font-size:11px;">
                ${initials}
              </div>
              <div class="min-w-0 flex-grow-1">
                <div class="fw-bold text-dark text-truncate" style="font-size:12.5px;">${escapeHtml(signer.name)}</div>
                <div class="text-muted small text-truncate" style="font-size:11px;">${escapeHtml(signer.title || "Ünvan belirtilmedi")}</div>
              </div>
            </div>

            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light text-muted px-2 py-0" style="font-size:10.5px;" title="İmza bloğunda adın üzerinde yer alacak temsil ibaresi">
                <i class="bx bx-user-check me-1 text-primary"></i>Kimin Adına:
              </span>
              <input type="text" class="form-control form-control-sm signer-kimin-adina-input py-0" data-index="${index}" style="font-size:11px; height:28px;" placeholder="Örn: Firma Yetkilisi a." value="${escapeHtml(signer.kimin_adina || '')}" ${isLocked ? 'disabled' : ''}>
              ${!isLocked ? `
                <button type="button" class="btn btn-light btn-sm px-2 py-0 btn-quick-kimin-adina" data-index="${index}" data-text="Firma Yetkilisi a." title="'Firma Yetkilisi a.' olarak ayarla" style="font-size:10.5px;">
                  Firma Yetkilisi a.
                </button>
              ` : ''}
            </div>
          </div>
        </div>
      `);

      container.append(card);
    });
  }

  // İmza Yetkilisi Seçildiğinde Ekle
  signerAddSelect.on("change", function () {
    const selectedId = $(this).val();
    if (!selectedId) return;

    if (selectedSigners.length >= 3) {
      Swal.fire("Sınır Aşıldı", "En fazla 3 imza yetkilisi seçebilirsiniz.", "warning");
      $(this).val("").trigger("change.select2");
      return;
    }

    const foundUser = allSigningUsers.find(u => u.id === selectedId);
    if (foundUser && !selectedSigners.some(s => s.id === selectedId)) {
      selectedSigners.push({
        id: foundUser.id,
        raw_id: foundUser.raw_id,
        name: foundUser.name,
        title: foundUser.title,
        kimin_adina: ""
      });
      syncSignerState();
    }
  });

  // İmza Sıralama / Taşıma Butonları
  $(document).on("click", ".btn-signer-move", function () {
    const index = parseInt($(this).data("index"), 10);
    const dir = parseInt($(this).data("dir"), 10);
    const target = index + dir;
    if (target < 0 || target >= selectedSigners.length) return;

    const temp = selectedSigners[index];
    selectedSigners[index] = selectedSigners[target];
    selectedSigners[target] = temp;

    syncSignerState();
  });

  // İmza Yetkilisi Kaldırma
  $(document).on("click", ".btn-signer-remove", function () {
    const index = parseInt($(this).data("index"), 10);
    selectedSigners.splice(index, 1);
    syncSignerState();
  });

  // Kimin Adına Değiştiğinde Senkronize Et
  $(document).on("input change", ".signer-kimin-adina-input", function () {
    const index = parseInt($(this).data("index"), 10);
    if (selectedSigners[index]) {
      selectedSigners[index].kimin_adina = $(this).val().trim();
      $("#kimin_adina_" + (index + 1)).val(selectedSigners[index].kimin_adina);
    }
  });

  // Hızlı "Firma Yetkilisi a." Butonu
  $(document).on("click", ".btn-quick-kimin-adina", function () {
    const index = parseInt($(this).data("index"), 10);
    const text = $(this).data("text") || "";
    const input = $(this).closest(".input-group").find(".signer-kimin-adina-input");
    const currentVal = input.val().trim();
    const newVal = (currentVal === text) ? "" : text;
    input.val(newVal).trigger("change");
  });

  // İlk Yükleme Senkronizasyonu
  syncSignerState();

  // --- Gelen Evraktan İlgi Ekleme Seçicisi ---
  const ilgiGelenSelect = $("#ilgiGelenEvrakSelect");
  const btnIlgiyeEkle = $("#btnIlgiyeEkle");

  ilgiGelenSelect.on("change", function () {
    const val = $(this).val();
    btnIlgiyeEkle.prop("disabled", !val);
  });

  btnIlgiyeEkle.on("click", function () {
    const selectedOpt = ilgiGelenSelect.find("option:selected");
    const val = ilgiGelenSelect.val();
    if (!val || !selectedOpt.length) return;

    const evrakNo = (selectedOpt.data("no") || "").toString().trim();
    const tarih = (selectedOpt.data("tarih") || "").toString().trim();
    const kurum = (selectedOpt.data("kurum") || "").toString().trim();

    let formattedText = "";
    if (tarih && evrakNo) {
      if (kurum) {
        formattedText = `${tarih} tarihli ve ${kurum}'nın ${evrakNo} sayılı yazısı.`;
      } else {
        formattedText = `${tarih} tarihli ve ${evrakNo} sayılı yazınız.`;
      }
    } else if (tarih) {
      formattedText = `${tarih} tarihli yazınız.`;
    } else if (evrakNo) {
      formattedText = `${evrakNo} sayılı yazınız.`;
    } else {
      formattedText = selectedOpt.text().trim();
    }

    const currentIlgiler = $("#ilgiler").val().trim();
    let updatedIlgiler = "";

    if (currentIlgiler === "") {
      updatedIlgiler = formattedText;
    } else {
      const lines = currentIlgiler.split("\n").map(l => l.trim()).filter(l => l !== "");
      if (!lines.includes(formattedText)) {
        lines.push(formattedText);
        updatedIlgiler = lines.join("\n");
      } else {
        updatedIlgiler = currentIlgiler;
      }
    }

    $("#ilgiler").val(updatedIlgiler).trigger("input").trigger("change");

    // Dropdown'ı kapat ve sıfırla
    ilgiGelenSelect.val("").trigger("change");
    const dropdownBtn = document.getElementById("btnIlgiGelenEvrakSec");
    if (dropdownBtn && typeof bootstrap !== "undefined" && bootstrap.Dropdown) {
      const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn) || new bootstrap.Dropdown(dropdownBtn);
      bsDropdown.hide();
    }

    // Toast bildirimi
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: "İlgi alanına eklendi",
      text: formattedText,
      showConfirmButton: false,
      timer: 2000,
      timerProgressBar: true
    });
  });

  // --- İlişkili Gelen Evrak Seçildiğinde İlgi Alanını Otomatik Doldur ---
  $("#ilgili_evrak_id").on("change select2:select", function () {
    const val = $(this).val();
    if (!val || !window.gidenGelenEvraklarMap || !window.gidenGelenEvraklarMap[val]) {
      return;
    }

    const item = window.gidenGelenEvraklarMap[val];
    const dateStr = (item.tarih || "").trim();
    const noStr = (item.evrak_no || "").trim();

    let text = "";
    if (dateStr && noStr) {
      text = dateStr + " tarih ve " + noStr + " sayılı yazınız.";
    } else if (dateStr) {
      text = dateStr + " tarihli yazınız.";
    } else if (noStr) {
      text = noStr + " sayılı yazınız.";
    }

    if (!text) return;

    const currentIlgiler = $("#ilgiler").val().trim();
    if (currentIlgiler === "") {
      $("#ilgiler").val(text).trigger("input").trigger("change");
    } else {
      const lines = currentIlgiler.split("\n").map(l => l.trim()).filter(l => l !== "");
      if (!lines.includes(text)) {
        lines.push(text);
        $("#ilgiler").val(lines.join("\n")).trigger("input").trigger("change");
      }
    }
  });

  if (typeof flatpickr !== "undefined") {
    flatpickr("#tarih", { dateFormat: "d.m.Y", locale: "tr" });
  }

  $("#giden_evrak_icerik").summernote({
    height: 450,
    lang: "tr-TR",
    placeholder: "Resmî yazı içeriğini yazınız...",
    fontNames: ["Times New Roman", "Arial"],
    fontNamesIgnoreCheck: ["Times New Roman", "Arial"],
    toolbar: [
      ["style", ["style"]],
      ["font", ["fontname", "fontsize", "bold", "italic", "underline", "clear"]],
      ["color", ["color"]],
      ["para", ["ul", "ol", "paragraph"]],
      ["insert", ["link", "table", "hr"]],
      ["view", ["fullscreen", "codeview"]]
    ],
    callbacks: {
      onInit: function () {
        $("#gidenEvrakForm .note-editable").css({ fontFamily: '"Times New Roman", Times, serif', fontSize: "12pt" });
        if ($("#giden_evrak_icerik").summernote("isEmpty")) {
          $("#giden_evrak_icerik").summernote("fontName", "Times New Roman");
          $("#giden_evrak_icerik").summernote("fontSize", "12");
        }
        adjustGidenEvrakLayout();
      }
    }
  });

  function syncContent() {
    $("#giden_evrak_icerik").val($("#giden_evrak_icerik").summernote("code"));
  }

  let aiSelectionRange = null;
  let aiSelectedText = "";
  const aiContextMenu = $("#evrakAiContextMenu");

  $(document).on("contextmenu", "#gidenEvrakForm .note-editable", function (event) {
    if (window.gidenEvrakKilitli) {
      aiContextMenu.hide();
      return;
    }
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed || !selection.toString().trim()) {
      aiContextMenu.hide();
      return;
    }
    const range = selection.getRangeAt(0);
    if (!this.contains(range.commonAncestorContainer)) {
      aiContextMenu.hide();
      return;
    }
    event.preventDefault();
    aiSelectionRange = range.cloneRange();
    aiSelectedText = selection.toString().trim();
    const menuWidth = 230;
    const menuHeight = 48;
    aiContextMenu.css({
      left: Math.min(event.clientX, window.innerWidth - menuWidth - 8) + "px",
      top: Math.min(event.clientY, window.innerHeight - menuHeight - 8) + "px"
    }).show();
  });

  $(document).on("click scroll", function (event) {
    if (!$(event.target).closest("#evrakAiContextMenu").length) aiContextMenu.hide();
  });
  $(document).on("keydown", function (event) {
    if (event.key === "Escape") aiContextMenu.hide();
  });

  $("#btnAiSecimDuzenleAc").on("click", function () {
    aiContextMenu.hide();
    if (!aiSelectionRange || !aiSelectedText) {
      Swal.fire("Metin Seçilmedi", "Önce düzenlemek istediğiniz metni seçiniz.", "warning");
      return;
    }
    $("#aiSeciliMetinOnizleme").text(aiSelectedText);
    $("#aiSecimTalimat").val("");
    $("#evrakAiSecimModal").modal("show");
    $("#evrakAiSecimModal").one("shown.bs.modal", function () { $("#aiSecimTalimat").trigger("focus"); });
  });

  $(".ai-hizli-talimat").on("click", function () {
    $("#aiSecimTalimat").val($(this).data("talimat")).trigger("focus");
  });

  function replaceAiSelection(html) {
    const editable = $("#gidenEvrakForm .note-editable")[0];
    if (!editable || !aiSelectionRange || !editable.contains(aiSelectionRange.commonAncestorContainer)) {
      throw new Error("Seçili metnin konumu artık geçerli değil.");
    }
    const holder = document.createElement("div");
    holder.innerHTML = html;
    const startElement = aiSelectionRange.startContainer.nodeType === Node.ELEMENT_NODE ? aiSelectionRange.startContainer : aiSelectionRange.startContainer.parentElement;
    const endElement = aiSelectionRange.endContainer.nodeType === Node.ELEMENT_NODE ? aiSelectionRange.endContainer : aiSelectionRange.endContainer.parentElement;
    const startBlock = $(startElement).closest("p,li")[0];
    const endBlock = $(endElement).closest("p,li")[0];
    if (startBlock && startBlock === endBlock && holder.children.length === 1 && holder.firstElementChild.tagName === "P") {
      holder.innerHTML = holder.firstElementChild.innerHTML;
    }
    const fragment = document.createDocumentFragment();
    let lastNode = null;
    while (holder.firstChild) {
      lastNode = fragment.appendChild(holder.firstChild);
    }
    aiSelectionRange.deleteContents();
    aiSelectionRange.insertNode(fragment);
    if (lastNode) {
      aiSelectionRange.setStartAfter(lastNode);
      aiSelectionRange.collapse(true);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(aiSelectionRange);
    }
    $(editable).trigger("input");
    syncContent();
  }

  $("#btnAiSecimUygula").on("click", function () {
    const instruction = $("#aiSecimTalimat").val().trim();
    if (!instruction) {
      Swal.fire("Eksik Bilgi", "Seçili metnin nasıl düzenleneceğini yazınız.", "warning");
      return;
    }
    const button = $(this);
    const originalHtml = button.html();
    button.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Düzenleniyor...');
    $.ajax({
      url: "views/evrak-takip/ai-metin-duzenle.php",
      type: "POST",
      dataType: "json",
      data: {
        selected_text: aiSelectedText,
        instruction: instruction,
        document_context: $("#gidenEvrakForm .note-editable").text()
      },
      success: function (response) {
        if (response.status !== "success" || !response.data || !response.data.html) {
          Swal.fire("Hata", response.message || "Metin düzenlenemedi.", "error");
          return;
        }
        try {
          replaceAiSelection(response.data.html);
          $("#evrakAiSecimModal").modal("hide");
          Swal.fire("Metin Düzenlendi", "Yapay zekâ sonucu seçili bölümün yerine uygulandı.", "success");
        } catch (error) {
          Swal.fire("Seçim Geçersiz", error.message, "warning");
        }
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Yapay zekâ servisine ulaşılamadı.";
        Swal.fire("Hata", message, "error");
      },
      complete: function () {
        button.prop("disabled", false).html(originalHtml);
      }
    });
  });

  $("#btnAiTaslakAc").on("click", function () {
    $("#evrakAiModal").modal("show");
  });

  // --- İcra Üst Yazısı ---
  const icraModal = $("#icraUstYaziModal");
  const icraPersonelSelect = $("#icraUstYaziPersonel");
  let seciliIcraId = null;

  icraPersonelSelect.select2({ width: "100%", placeholder: "Personel seçiniz...", dropdownParent: icraModal });

  function icraListesiMesaji(text, tone) {
    seciliIcraId = null;
    $("#btnIcraUstYaziOlustur").prop("disabled", true);
    $("#icraUstYaziListe").html('<div class="p-3 small text-center ' + (tone || "text-muted bg-light") + '">' + text + "</div>");
  }

  function renderIcraListesi(items) {
    const container = $("#icraUstYaziListe").empty();
    seciliIcraId = null;
    $("#btnIcraUstYaziOlustur").prop("disabled", true);

    if (!items.length) {
      icraListesiMesaji("Bu personele ait icra dosyası bulunmuyor.", "text-warning bg-warning-subtle");
      return;
    }

    items.forEach(item => {
      const row = $("<label>").addClass("d-flex align-items-center gap-3 p-3 border-bottom bg-white mb-0").css("cursor", "pointer");
      $("<input type='radio' name='icraUstYaziSecim' class='form-check-input mt-0 flex-shrink-0'>")
        .val(item.id)
        .on("change", function () {
          seciliIcraId = $(this).val();
          $("#btnIcraUstYaziOlustur").prop("disabled", false);
        })
        .appendTo(row);

      const info = $("<div>").addClass("min-w-0 flex-grow-1");
      $("<div>").addClass("fw-bold text-dark text-truncate").text((item.icra_dairesi || "-") + " — " + (item.dosya_no || "-")).appendTo(info);
      const detay = [item.durum_etiketi, "Borç: " + item.toplam_borc + " TL", "Kalan: " + item.kalan_tutar + " TL"];
      if (item.alacakli) detay.unshift(item.alacakli);
      $("<div>").addClass("small text-muted text-truncate").text(detay.join(" | ")).appendTo(info);

      row.append(info).appendTo(container);
    });
    $("#icraUstYaziListe > label:last-child").removeClass("border-bottom");
  }

  $("#btnIcraUstYaziAc").on("click", function () {
    icraModal.modal("show");
  });

  const requestedTool = new URLSearchParams(window.location.search).get("arac");
  if (requestedTool === "ai") {
    $("#btnAiTaslakAc").trigger("click");
  } else if (requestedTool === "icra") {
    $("#btnIcraUstYaziAc").trigger("click");
  }

  icraPersonelSelect.on("change", function () {
    const personelId = $(this).val();
    if (!personelId) {
      icraListesiMesaji("Önce personel seçiniz.");
      return;
    }
    icraListesiMesaji('<span class="spinner-border spinner-border-sm me-2"></span>İcra dosyaları yükleniyor...');
    $.post("views/evrak-takip/icra-ust-yazi.php", { action: "icra-listesi", personel_id: personelId })
      .done(response => {
        if (response.status !== "success") {
          icraListesiMesaji(response.message || "İcra dosyaları alınamadı.", "text-danger bg-danger-subtle");
          return;
        }
        renderIcraListesi(response.data || []);
      })
      .fail(xhr => {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "İcra dosyaları alınamadı.";
        icraListesiMesaji(message, "text-danger bg-danger-subtle");
      });
  });

  $("#btnIcraUstYaziOlustur").on("click", function () {
    if (!seciliIcraId) return;
    const button = $(this);
    const originalHtml = button.html();
    button.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Metin hazırlanıyor...');

    $.post("views/evrak-takip/icra-ust-yazi.php", { action: "taslak", icra_id: seciliIcraId })
      .done(response => {
        if (response.status !== "success" || !response.data) {
          Swal.fire("Hata", response.message || "Üst yazı oluşturulamadı.", "error");
          return;
        }
        const draft = response.data;
        ["konu", "kurum_adi", "ilgiler"].forEach(function (field) {
          if (typeof draft[field] === "string" && draft[field] !== "") {
            $("#" + field).val(draft[field]).trigger("input").trigger("change");
          }
        });
        if (draft.aciklama_html) {
          $("#giden_evrak_icerik").summernote("code", draft.aciklama_html);
        }
        if (draft.personel_id) {
          $("#ilgili_personel_id").val(String(draft.personel_id)).trigger("change");
        }
        icraModal.modal("hide");
        $("#gidenEvrakTabs button[data-bs-target='#gidenIcerikTab']").tab("show");
        Swal.fire("Metin Hazır", "Üst yazı içeriği oluşturuldu. Kaydetmeden önce tutarları ve muhatabı kontrol ediniz.", "success");
      })
      .fail(xhr => {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Üst yazı oluşturulamadı.";
        Swal.fire("Hata", message, "error");
      })
      .always(() => {
        button.prop("disabled", false).html(originalHtml);
        if (typeof feather !== "undefined") feather.replace();
      });
  });

  $("#btnAiTemizle").on("click", function () {
    $("#aiGelenEvrak").val("");
    $("#aiTalimat").val("");
  });

  $("#btnFormuTemizle").on("click", function () {
    Swal.fire({
      title: "Formu Temizle",
      text: "Girilen tüm alanlar ve yazılan içerik temizlenecektir. Devam etmek istiyor musunuz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, Temizle",
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#d33",
    }).then((result) => {
      if (result.isConfirmed) {
        $("#evrak_no, #konu, #kurum_adi, #muhatap_alt_birim, #muhatap_adres, #ilgiler, #ekler").val("").trigger("input").trigger("change");
        $("#ilgili_evrak_id, #personel_id, #ilgili_personel_id").val(null).trigger("change");
        $("#giden_evrak_icerik").summernote("code", "<p><br></p>");
        attachments = [];
        removedAttachmentIds = [];
        renderAttachments();
        selectedSigners = [];
        syncSignerState();
        Swal.fire({ icon: "success", title: "Temizlendi", text: "Form başarıyla sıfırlandı.", timer: 1500, showConfirmButton: false });
      }
    });
  });

  $("#btnAiTaslakOlustur").on("click", function () {
    const file = $("#aiGelenEvrak")[0].files[0];
    const instruction = $("#aiTalimat").val().trim();
    if (!file || !instruction) {
      Swal.fire("Eksik Bilgi", "Gelen evrakı seçin ve ne yapmak istediğinizi yazın.", "warning");
      return;
    }
    if (file.size > 12 * 1024 * 1024) {
      Swal.fire("Dosya Çok Büyük", "Gelen evrak en fazla 12 MB olabilir.", "warning");
      return;
    }

    const button = $(this);
    const originalHtml = button.html();
    const data = new FormData();
    data.append("gelen_evrak", file, file.name);
    data.append("talimat", instruction);
    button.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Evrak hazırlanıyor...');

    $.ajax({
      url: "views/evrak-takip/ai-taslak.php",
      type: "POST",
      data: data,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.status !== "success" || !response.data) {
          Swal.fire("Hata", response.message || "Taslak oluşturulamadı.", "error");
          return;
        }
        const draft = response.data;
        ["konu", "kurum_adi", "muhatap_alt_birim", "muhatap_adres", "ilgiler"].forEach(function (field) {
          if (typeof draft[field] === "string" && draft[field] !== "") {
            $("#" + field).val(draft[field]).trigger("input").trigger("change");
          }
        });
        if (draft.aciklama_html) {
          $("#giden_evrak_icerik").summernote("code", draft.aciklama_html);
        }
        $("#evrakAiModal").modal("hide");
        $("#gidenEvrakTabs button[data-bs-target='#gidenIcerikTab']").tab("show");
        Swal.fire("Taslak Hazır", "Alanlar yapay zekâ taslağıyla dolduruldu. Kaydetmeden önce bilgileri kontrol ediniz.", "success");
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Yapay zekâ servisine ulaşılamadı.";
        Swal.fire("Hata", message, "error");
      },
      complete: function () {
        button.prop("disabled", false).html(originalHtml);
        if (typeof feather !== "undefined") feather.replace();
      }
    });
  });

  function formatBytes(bytes) {
    if (!bytes) return "0 KB";
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    return (bytes / 1024).toFixed(2) + " KB";
  }

  function renderAttachments() {
    const list = $("#ekDosyaListesi").empty();
    $("#ekBosUyari").toggleClass("d-none", attachments.length > 0);
    attachments.forEach((item, index) => {
      const row = $("<div>").addClass("ek-dosya-satiri d-flex align-items-center justify-content-between gap-3");
      const info = $("<div>").addClass("min-w-0");
      $("<div>").addClass("fw-bold text-dark text-truncate").text(item.name).appendTo(info);
      const details = ["Boyut: " + formatBytes(item.size)];
      if (item.date) details.push("Tarih: " + item.date);
      $("<div>").addClass("small text-muted").text(details.join(" | ")).appendTo(info);
      const actions = $("<div>").addClass("btn-group btn-group-sm flex-shrink-0");
      $("<button type='button' class='btn btn-outline-secondary' title='Yukarı'><i class='bx bx-up-arrow-alt'></i></button>").prop("disabled", index === 0).on("click", () => moveAttachment(index, -1)).appendTo(actions);
      $("<button type='button' class='btn btn-outline-secondary' title='Aşağı'><i class='bx bx-down-arrow-alt'></i></button>").prop("disabled", index === attachments.length - 1).on("click", () => moveAttachment(index, 1)).appendTo(actions);
      if (item.type === "existing" && item.path) $("<a target='_blank' class='btn btn-outline-info' title='Görüntüle'><i class='bx bx-show'></i></a>").attr("href", item.path).appendTo(actions);
      $("<button type='button' class='btn btn-danger' title='Sil'><i class='bx bx-trash'></i></button>").on("click", () => removeAttachment(index)).appendTo(actions);
      if (window.gidenEvrakKilitli) actions.find("button").prop("disabled", true);
      row.append(info, actions).appendTo(list);
    });
    syncAttachmentFields();
    updateKepPackageSummary();
  }

  function updateKepPackageSummary() {
    const totalBytes = attachments.reduce((sum, item) => sum + (Number(item.size) || 0), 0);
    const hasCoverLetter = !$("#ust_yazi_gerekli_degil").is(":checked");
    $("#kepAttachmentCount").text(attachments.length + (hasCoverLetter ? 1 : 0));
    $("#kepAttachmentSize").text(formatBytes(totalBytes));
    $("#kepPackageSummary").text(hasCoverLetter ? "Resmî üst yazı PDF’i ve ekler gönderilecek." : "Yalnızca eklenen belgeler gönderilecek.");
  }

  function applyDeliveryChannel(channel) {
    channel = channel === "kep" ? "kep" : "normal";
    $("#gonderim_kanali").val(channel);
    $(".gonderim-channel-card").removeClass("active").filter(`[data-channel="${channel}"]`).addClass("active");
    $("#kepComposerPanel").toggleClass("d-none", channel !== "kep");
    $("#islakImzaOnayaSunItem").toggleClass("d-none", channel !== "normal");
    $("#mobilImzaOnayaSunItem").toggleClass("d-none", channel !== "normal");
    $("#btnGidenKepHizliGonder").toggleClass("d-none", channel !== "kep" && !window.gidenKepCanSendApproved);
    updateKepPackageSummary();
    adjustGidenEvrakLayout();
  }

  $(document).on("click", ".gonderim-channel-card:not(:disabled)", function () {
    applyDeliveryChannel($(this).data("channel"));
  });
  $(document).on("change", "#gonderim_turu_secimi", function () {
    const channel = $(this).val() === "kep" ? "kep" : "normal";
    applyDeliveryChannel(channel);
    $("#gidenKepAliciPanel").toggleClass("d-none", channel !== "kep");
  });
  applyDeliveryChannel($("#gonderim_kanali").val());
  $("#gidenKepAliciPanel").toggleClass("d-none", $("#gonderim_kanali").val() !== "kep");

  function moveAttachment(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= attachments.length) return;
    [attachments[index], attachments[target]] = [attachments[target], attachments[index]];
    renderAttachments();
  }

  function removeAttachment(index) {
    const item = attachments[index];
    if (item.type === "existing") removedAttachmentIds.push(item.id);
    attachments.splice(index, 1);
    renderAttachments();
  }

  function syncAttachmentFields() {
    const order = attachments.map(item => item.type === "existing" ? { type: "existing", id: item.id } : (item.type === "template" ? { type: "template", id: item.id } : { type: "new", key: item.key }));
    $("#ek_duzen_json").val(JSON.stringify(order));
    $("#silinen_ek_ids_json").val(JSON.stringify(removedAttachmentIds));
  }

  $("#ek_dosyalari").on("change", function () {
    Array.from(this.files || []).forEach(file => {
      if (file.size > 10 * 1024 * 1024) {
        Swal.fire("Dosya Çok Büyük", file.name + " 10 MB sınırını aşıyor.", "warning");
        return;
      }
      attachments.push({ type: "new", key: "n" + nextFileKey++, name: file.name, size: file.size, date: new Date(file.lastModified).toLocaleString("tr-TR"), file });
    });
    this.value = "";
    renderAttachments();
  });

  renderAttachments();

  const templateModal = $("#evrakSablonModal");
  let documentTemplates = [];
  function setTemplateSaveMode(template) {
    const isEdit = Boolean(template && template.id);
    $("#evrakSablonId").val(isEdit ? template.id : "");
    $("#evrakSablonAdi").val(isEdit ? template.adi : "");
    $("#evrakSablonKayitModu")
      .toggleClass("alert-warning", isEdit)
      .toggleClass("alert-primary", !isEdit)
      .text(isEdit ? '"' + template.adi + '" şablonu güncellenecek.' : "Yeni şablon oluşturulacak.");
    $("#btnEvrakSablonKaydet").html(isEdit
      ? '<i class="bx bx-save me-1"></i> Seçili Şablonu Güncelle'
      : '<i class="bx bx-plus me-1"></i> Yeni Şablon Olarak Kaydet');
  }
  function loadDocumentTemplates() {
    $("#evrakSablonListesi").html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Şablonlar yükleniyor...</div>');
    return $.post("views/evrak-takip/api.php", { action: "evrak-sablon-listele" }).done(function (response) {
      if (response.status !== "success") return Swal.fire("Hata", response.message || "Şablonlar yüklenemedi.", "error");
      documentTemplates = response.data || [];
      const list = $("#evrakSablonListesi").empty();
      if (!documentTemplates.length) return list.html('<div class="border rounded-3 text-center text-muted py-4">Henüz kayıtlı şablon yok.</div>');
      documentTemplates.forEach(function (template) {
        const row = $('<div class="list-group-item d-flex align-items-center justify-content-between gap-2"></div>');
        $('<button type="button" class="btn btn-link text-start text-decoration-none flex-grow-1 p-0"></button>').html('<span class="d-block fw-bold text-dark">' + escapeHtml(template.adi) + '</span><span class="small text-muted">' + escapeHtml(template.guncelleme_tarihi) + ' · ' + (template.ek_dosyalar || []).length + ' ek</span>').on("click", () => applyDocumentTemplate(template)).appendTo(row);
        const actions = $('<div class="btn-group btn-group-sm"></div>');
        $('<button type="button" class="btn btn-outline-secondary" title="Şablonu güncelle"><i class="bx bx-edit"></i></button>').on("click", function () { setTemplateSaveMode(template); $("#evrakSablonAdi").focus(); }).appendTo(actions);
        $('<button type="button" class="btn btn-outline-danger"><i class="bx bx-trash"></i></button>').on("click", () => deleteDocumentTemplate(template)).appendTo(actions);
        row.append(actions).appendTo(list);
      });
    });
  }
  function applyDocumentTemplate(template) {
    Swal.fire({ title: '"' + template.adi + '" şablonu yüklensin mi?', text: "Formdaki mevcut bilgiler bu şablonun içeriğiyle değiştirilecek.", icon: "question", showCancelButton: true, confirmButtonText: "Yükle", cancelButtonText: "Vazgeç" }).then(function (result) {
      if (!result.isConfirmed) return;
      const data = template.veri || {};
      ["tarih", "evrak_no", "konu", "kurum_adi", "muhatap_alt_birim", "muhatap_adres", "ilgiler", "ekler"].forEach(field => $("#" + field).val(data[field] || "").trigger("input").trigger("change"));
      ["ilgili_evrak_id", "personel_id", "ilgili_personel_id"].forEach(field => $("#" + field).val(data[field] || null).trigger("change"));
      $("#giden_evrak_icerik").summernote("code", data.aciklama || "<p><br></p>");
      $("#ust_yazi_gerekli_degil").prop("checked", Number(data.ust_yazi_gerekli_degil) === 1).trigger("change");
      selectedSigners = (data.imza_kullanici_ids || []).map((rawId, index) => { const user = allSigningUsers.find(item => Number(item.raw_id) === Number(rawId)); return user ? { ...user, kimin_adina: (data.imza_kimin_adina || [])[index] || "" } : null; }).filter(Boolean).slice(0, 3);
      syncSignerState();
      attachments = (template.ek_dosyalar || []).map(item => ({ ...item, type: "template" }));
      removedAttachmentIds.length = 0; renderAttachments(); templateModal.modal("hide");
      Swal.fire({ icon: "success", title: "Şablon Yüklendi", text: "Tüm evrak alanları ve ekleri forma aktarıldı.", timer: 1700, showConfirmButton: false });
    });
  }
  function deleteDocumentTemplate(template) {
    Swal.fire({ title: "Şablon silinsin mi?", text: template.adi, icon: "warning", showCancelButton: true, confirmButtonText: "Sil", cancelButtonText: "Vazgeç", confirmButtonColor: "#d33" }).then(function (result) {
      if (!result.isConfirmed) return;
      $.post("views/evrak-takip/api.php", { action: "evrak-sablon-sil", sablon_id: template.id }).done(response => response.status === "success" ? loadDocumentTemplates() : Swal.fire("Hata", response.message, "error"));
    });
  }
  $("#btnEvrakSablonlari").on("click", function () { setTemplateSaveMode(null); templateModal.modal("show"); loadDocumentTemplates(); });
  $("#btnEvrakSablonYeni").on("click", function () { setTemplateSaveMode(null); $("#evrakSablonAdi").focus(); });
  $("#btnEvrakSablonKaydet").on("click", function () {
    const name = $("#evrakSablonAdi").val().trim(); if (!name) return Swal.fire("Eksik Bilgi", "Şablon adını giriniz.", "warning");
    const templateId = $("#evrakSablonId").val();
    syncContent();
    const payload = { tarih: $("#tarih").val(), evrak_no: $("#evrak_no").val(), konu: $("#konu").val(), kurum_adi: $("#kurum_adi").val(), muhatap_alt_birim: $("#muhatap_alt_birim").val(), muhatap_adres: $("#muhatap_adres").val(), ilgiler: $("#ilgiler").val(), ekler: $("#ekler").val(), aciklama: $("#giden_evrak_icerik").summernote("code"), ust_yazi_gerekli_degil: $("#ust_yazi_gerekli_degil").is(":checked") ? 1 : 0, ilgili_evrak_id: $("#ilgili_evrak_id").val(), personel_id: $("#personel_id").val(), ilgili_personel_id: $("#ilgili_personel_id").val(), imza_kullanici_ids: selectedSigners.map(item => item.raw_id), imza_kimin_adina: selectedSigners.map(item => item.kimin_adina || "") };
    const data = new FormData(); data.append("action", "evrak-sablon-kaydet"); data.append("kayit_modu", templateId ? "update" : "create"); data.append("sablon_id", templateId); data.append("sablon_adi", name); data.append("sablon_verisi", JSON.stringify(payload));
    data.append("sablon_mevcut_ekler", JSON.stringify(attachments.filter(item => item.type !== "new" && item.id).map(item => ({ type: item.type, id: item.id }))));
    attachments.filter(item => item.type === "new").forEach((item, index) => data.append("sablon_yeni_ekler[" + index + "]", item.file, item.name));
    const button = $(this).prop("disabled", true);
    $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data, contentType: false, processData: false }).done(function (response) { if (response.status === "success") { setTemplateSaveMode(null); Swal.fire("Başarılı", response.message, "success"); loadDocumentTemplates(); } else Swal.fire("Hata", response.message, "error"); }).always(() => button.prop("disabled", false));
  });

  function buildFormData() {
    syncContent();
    syncAttachmentFields();
    const data = new FormData(form[0]);
    data.delete("ek_dosyalari[]");
    attachments.forEach(item => {
      if (item.type === "new") data.append("ek_dosyalari[" + item.key + "]", item.file, item.name);
    });
    return data;
  }

  function clearPdf() {
    if (pdfObjectUrl) URL.revokeObjectURL(pdfObjectUrl);
    pdfObjectUrl = null;
    $("#gidenPdfFrame").off("load.gidenPdf").addClass("giden-pdf-hidden").attr("src", "about:blank").css("height", "75vh");
    $("#gidenPdfLoader").removeClass("giden-pdf-hidden");
    $("#gidenPdfYeniSekme").addClass("d-none").attr("href", "#");
    $("#gidenPdfModal .modal-dialog").removeClass("modal-fullscreen").addClass("modal-xl modal-dialog-centered");
    $("#btnGidenPdfTamEkran").html('<i class="bx bx-fullscreen me-1"></i>Tam Ekran');
  }

  $("#btnGidenPdfTamEkran").on("click", function () {
    const modalDialog = $("#gidenPdfModal .modal-dialog");
    const isFullScreen = modalDialog.hasClass("modal-fullscreen");
    if (!isFullScreen) {
      modalDialog.removeClass("modal-xl modal-dialog-centered").addClass("modal-fullscreen");
      $(this).html('<i class="bx bx-exit-fullscreen me-1"></i>Küçült');
      $("#gidenPdfFrame").css("height", "calc(100vh - 120px)");
    } else {
      modalDialog.removeClass("modal-fullscreen").addClass("modal-xl modal-dialog-centered");
      $(this).html('<i class="bx bx-fullscreen me-1"></i>Tam Ekran');
      $("#gidenPdfFrame").css("height", "75vh");
    }
  });

  function toggleUstYaziDurumu(isUserAction) {
    const isChecked = $("#ust_yazi_gerekli_degil").is(":checked");
    if (isChecked) {
      $("#ustYaziMetinBilgisi").removeClass("d-none");
      $("#editorContainer").addClass("d-none");
      $("#btnIcraUstYaziAc, #btnAiTaslakAc").addClass("d-none");
      // Üst yazı gerekli değil iken akordeonları kapat
      $("#collapseImzaYetkilileri, #collapseIliskiliKayitlar, #collapseIlgiEkMetinleri").collapse("hide");
      if (isUserAction === true) {
        $("#gidenEvrakTabs button[data-bs-target='#gidenEklerTab']").tab("show");
      }
    } else {
      $("#ustYaziMetinBilgisi").addClass("d-none");
      $("#editorContainer").removeClass("d-none");
      if (!window.gidenEvrakKilitli) {
        $("#btnIcraUstYaziAc, #btnAiTaslakAc").removeClass("d-none");
      }
      if (isUserAction === true) {
        $("#collapseImzaYetkilileri").collapse("show");
        $("#gidenEvrakTabs button[data-bs-target='#gidenIcerikTab']").tab("show");
      } else {
        if (selectedSigners && selectedSigners.length > 0) {
          $("#collapseImzaYetkilileri").collapse("show");
        }
      }
    }
    updateKepPackageSummary();
    adjustGidenEvrakLayout();
  }

  $(document).on("change", "#ust_yazi_gerekli_degil", function () {
    toggleUstYaziDurumu(true);
  });
  toggleUstYaziDurumu(false);

  $(document).on("click", "#btnEklereGit", function () {
    $("#gidenEvrakTabs button[data-bs-target='#gidenEklerTab']").tab("show");
  });

  $("#gidenDigerAlanlarAccordion").on("shown.bs.collapse", function () {
    $(this).find(".giden-select2").each(function () {
      if ($(this).data("select2")) {
        $(this).select2({ width: "100%" });
      }
    });
    adjustGidenEvrakLayout();
  });
  $("#gidenDigerAlanlarAccordion").on("hidden.bs.collapse", function () {
    adjustGidenEvrakLayout();
  });

  function validateForm() {
    if (!$("#evrak_no").val().trim() || !$("#konu").val().trim() || !$("#kurum_adi").val().trim()) {
      Swal.fire("Eksik Bilgi", "Sayı, konu ve muhatap alanları zorunludur.", "warning");
      return false;
    }
    const ustYaziGerekliDegil = $("#ust_yazi_gerekli_degil").is(":checked");
    if ($("#gonderim_kanali").val() === "kep") {
      const recipients = parseKepRecipients();
      if (!window.gidenKepSettings || !window.gidenKepSettings.has_settings) {
        Swal.fire("KEP Ayarı Eksik", "Firma KEP ayarları tamamlanmadan KEP evrakı hazırlanamaz.", "warning");
        return false;
      }
      if (!recipients.length || recipients.some(address => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address))) {
        $("#kep_alicilar").focus();
        Swal.fire("Eksik Bilgi", "En az bir geçerli alıcı KEP adresi giriniz.", "warning");
        return false;
      }
    }
    if (ustYaziGerekliDegil && attachments.length === 0) {
      $("#gidenEvrakTabs button[data-bs-target='#gidenEklerTab']").tab("show");
      Swal.fire("Eksik Belge", "Yalnızca eklerle gönderimde en az bir belge eklenmelidir.", "warning");
      return false;
    }
    if (!ustYaziGerekliDegil) {
      if (!$("#imza_kullanici_ids").val()?.length) {
        $("#collapseImzaYetkilileri").collapse("show");
        Swal.fire("Eksik Bilgi", "En az bir imza yetkilisi seçiniz.", "warning");
        return false;
      }
      if ($("#giden_evrak_icerik").summernote("isEmpty")) {
        $("#gidenEvrakTabs button[data-bs-target='#gidenIcerikTab']").tab("show");
        Swal.fire("Eksik Bilgi", "Yazı içeriği boş bırakılamaz.", "warning");
        return false;
      }
    }
    return true;
  }

  function requestPdf(options) {
    clearPdf();
    $("#gidenPdfLoader").removeClass("giden-pdf-hidden");
    $("#gidenPdfModal").modal("show");
    $.ajax($.extend({
      url: "views/evrak-takip/pdf.php", xhrFields: { responseType: "blob" },
      success: function (blob) {
        if (blob.type !== "application/pdf") { blob.text().then(msg => Swal.fire("Hata", msg, "error")); $("#gidenPdfLoader").addClass("giden-pdf-hidden"); $("#gidenPdfModal").modal("hide"); return; }
        pdfObjectUrl = URL.createObjectURL(blob);
        const frame = $("#gidenPdfFrame");
        const revealPdf = function () {
          frame.removeClass("giden-pdf-hidden");
          $("#gidenPdfLoader").addClass("giden-pdf-hidden");
        };
        frame.one("load.gidenPdf", revealPdf).attr("src", pdfObjectUrl);
        window.setTimeout(revealPdf, 1200);
        $("#gidenPdfYeniSekme").attr("href", pdfObjectUrl).removeClass("d-none");
      },
      error: function (xhr) { $("#gidenPdfLoader").addClass("giden-pdf-hidden"); $("#gidenPdfModal").modal("hide"); const b = xhr.response; b instanceof Blob ? b.text().then(msg => Swal.fire("Hata", msg, "error")) : Swal.fire("Hata", "PDF oluşturulamadı.", "error"); }
    }, options));
  }

  $("#btnGidenPdfOnizle").on("click", function () {
    if (window.gidenEvrakKilitli) {
      requestPdf({ url: "views/evrak-takip/pdf.php?id=" + encodeURIComponent(form.find("input[name=id]").val()), type: "GET" });
      return;
    }
    syncContent();
    if (!validateForm()) return;
    requestPdf({ type: "POST", data: buildFormData(), contentType: false, processData: false });
  });

  form.on("submit", function (event) {
    event.preventDefault();
    syncContent();
    if (!validateForm()) return;
    const button = $("#btnGidenKaydet").prop("disabled", true);
    $.ajax({
      url: "views/evrak-takip/api.php", type: "POST", data: buildFormData(), contentType: false, processData: false,
      success: response => response.status === "success" ? Swal.fire("Başarılı", response.message, "success").then(() => location.href = "index?p=evrak-takip/list") : (button.prop("disabled", false), Swal.fire("Hata", response.message, "error")),
      error: () => { button.prop("disabled", false); Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error"); }
    });
  });

  function adjustGidenEvrakLayout() {
    if (window.innerWidth < 992) {
      $(".giden-meta-panel").css({ "height": "", "max-height": "", "overflow-y": "" });
      $("#gidenEvrakForm .note-editable").css({ "height": "450px", "max-height": "", "overflow-y": "" });
      $("#gidenEklerTab").css({ "height": "", "max-height": "", "overflow-y": "" });
      $("#gidenEvrakForm > .card").css({ "height": "", "max-height": "" });
      return;
    }

    const windowHeight = $(window).height();
    const cardEl = $("#gidenEvrakForm > .card");
    if (!cardEl.length) return;

    const cardOffsetTop = cardEl.offset() ? cardEl.offset().top : 120;
    const actionBarHeight = $(".giden-action-bar").outerHeight() || 64;
    const bottomGap = 16;

    const cardHeight = Math.max(380, windowHeight - cardOffsetTop - actionBarHeight - bottomGap);
    cardEl.css({ "height": cardHeight + "px", "max-height": cardHeight + "px" });

    const cardHeaderHeight = cardEl.children(".card-header").outerHeight() || 56;
    const cardBodyHeight = cardHeight - cardHeaderHeight;

    $(".giden-meta-panel").css({
      "height": cardBodyHeight + "px",
      "max-height": cardBodyHeight + "px",
      "overflow-y": "auto"
    });

    const tabsHeight = $("#gidenEvrakTabs").outerHeight() || 42;
    const channelHeight = $("#gonderimKanaliSecimi").outerHeight(true) || 0;
    const kepComposerHeight = $("#kepComposerPanel:not(.d-none)").outerHeight(true) || 0;
    const tabContentPadding = 32;
    const availableTabContentHeight = cardBodyHeight - channelHeight - kepComposerHeight - tabsHeight - tabContentPadding;

    const noteToolbarHeight = $("#gidenIcerikTab .note-toolbar").outerHeight() || 41;
    const noteStatusbarHeight = $("#gidenIcerikTab .note-statusbar").outerHeight() || 0;
    const noteFormTextHeight = $("#gidenIcerikTab .form-text").outerHeight(true) || 28;

    const noteEditableHeight = Math.max(180, availableTabContentHeight - noteToolbarHeight - noteStatusbarHeight - noteFormTextHeight - 16);

    $("#gidenEvrakForm .note-editable").css({
      "height": noteEditableHeight + "px",
      "max-height": noteEditableHeight + "px",
      "overflow-y": "auto"
    });

    $("#gidenEklerTab").css({
      "height": availableTabContentHeight + "px",
      "max-height": availableTabContentHeight + "px",
      "overflow-y": "auto"
    });
  }

  function eImzaIstek(action, options) {
    Swal.fire({
      title: options.title,
      html: options.html,
      icon: options.icon,
      showCancelButton: true,
      confirmButtonColor: options.color,
      cancelButtonColor: "#64748b",
      confirmButtonText: options.confirmText,
      cancelButtonText: "Vazgeç"
    }).then(result => {
      if (!result.isConfirmed) return;
      const button = $(options.buttonSelector).prop("disabled", true);
      $.post("views/evrak-takip/api.php", { action: action, id: button.data("id") }, response => {
        if (response.status === "success") {
          Swal.fire("Başarılı", response.message, "success").then(() => location.reload());
        } else {
          button.prop("disabled", false);
          Swal.fire("Hata", response.message, "error");
        }
      }).fail(() => {
        button.prop("disabled", false);
        Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
      });
    });
  }

  function otomatikKepGonder(completed, evrakId, pin) {
    const autoKep = completed?.data?.auto_kep;
    if (!completed?.data?.completed || !autoKep) return Promise.resolve(null);
    if (!autoKep.enabled) {
      const error = new Error(autoKep.error || "Otomatik KEP gönderim bilgileri eksik.");
      error.code = "PADES_OK_KEP_FAILED";
      throw error;
    }
    Swal.update({ title: "Son imza tamamlandı, KEP paketi hazırlanıyor..." });
    return $.post("views/evrak-takip/api.php", {
      action: "kep-mime-hazirla", evrak_id: evrakId,
      alici_kep: autoKep.alici_kep, paket_turu: autoKep.paket_turu || "Standart"
    }).then(mime => {
      if (mime.status !== "success" || !mime.eHash || !mime.temp_id) {
        const error = new Error(mime.message || "KEP paketi hazırlanamadı.");
        error.code = "PADES_OK_KEP_FAILED";
        throw error;
      }
      Swal.update({ title: "KEP paketi aynı kartla imzalanıyor..." });
      return fetch("http://127.0.0.1:9191/sign", {
        method: "POST", mode: "cors", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ hash: mime.eHash, pin: pin })
      }).then(response => response.json()).then(signature => ({ mime, signature }));
    }).then(({ mime, signature }) => {
      if (!signature || signature.status !== "success" || !signature.signature) {
        const error = new Error(signature?.message || "KEP CAdES imzası üretilemedi.");
        error.code = "PADES_OK_KEP_FAILED";
        throw error;
      }
      Swal.update({ title: "İmzalı evrak PTT KEP sistemine gönderiliyor..." });
      return $.post("views/evrak-takip/api.php", {
        action: "kep-smime-sevk", temp_id: mime.temp_id,
        mesajid: mime.mesajid, signature: signature.signature
      });
    }).then(sent => {
      if (sent.status !== "success") {
        const error = new Error(sent.message || "PTT KEP gönderimi tamamlanamadı.");
        error.code = "PADES_OK_KEP_FAILED";
        throw error;
      }
      return sent;
    });
  }

  $("#btnEImzaOnayla").on("click", function () {
    const button = $(this);
    fetch("http://127.0.0.1:9191/status", { method: "GET", mode: "cors" })
      .then(response => response.json())
      .then(agent => {
        if (!agent || agent.status !== "success") {
          throw new Error((agent && agent.message) || "E-imza cihazı bulunamadı. Güncel Ersan E-İmza uygulamasını çalıştırınız.");
        }
        if (!String(agent.agent_version || "").startsWith("1.2.")) {
          const versionError = new Error("PAdES için Ersan E-İmza v1.2.0 gereklidir.");
          versionError.code = "EIMZA_AGENT_UPDATE_REQUIRED";
          throw versionError;
        }
        return Swal.fire({
          title: "E-İmza ile İmzala",
          html: `<div class="text-start small mb-2"><b>${$('<div>').text(agent.owner_name || 'Akıllı kart').html()}</b><br>İmza, PDF içine doğrulanabilir PAdES olarak işlenecektir.</div>`,
          input: "password",
          inputLabel: "E-İmza PIN kodu",
          inputPlaceholder: "PIN kodunuzu giriniz",
          inputAttributes: { autocomplete: "off", maxlength: 32 },
          showCancelButton: true,
          confirmButtonColor: "#22c55e",
          confirmButtonText: "Kart ile İmzala",
          cancelButtonText: "Vazgeç",
          inputValidator: value => !value ? "E-imza PIN kodu zorunludur." : undefined
        }).then(result => ({ result, agent }));
      })
      .then(({ result, agent }) => {
        if (!result.isConfirmed) return;
        button.prop("disabled", true);
        Swal.fire({ title: "İmzalanacak PDF hazırlanıyor...", allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        return $.post("views/evrak-takip/api.php", {
          action: "evrak-pades-hazirla", id: button.data("id")
        }).then(prepared => {
          if (prepared.status !== "success" || !prepared.data || !prepared.data.pdf || !prepared.data.temp_id) {
            throw new Error(prepared.message || "PDF hazırlanamadı.");
          }
          Swal.update({ title: "Akıllı kart ile PAdES imzası üretiliyor..." });
          return fetch("http://127.0.0.1:9191/sign-pdf", {
            method: "POST", mode: "cors", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              pdf: prepared.data.pdf, pin: result.value,
              signer_name: prepared.data.signer_name,
              reason: prepared.data.reason, location: prepared.data.location
            })
          }).then(response => response.json()).then(signed => ({ signed, tempId: prepared.data.temp_id }));
        }).then(signed => {
          if (!signed || !signed.signed || signed.signed.status !== "success" || !signed.signed.signed_pdf) {
            throw new Error((signed && signed.signed && signed.signed.message) || "Kart PAdES imzası üretemedi.");
          }
          Swal.update({ title: "PAdES imzası sunucuda doğrulanıyor..." });
          return $.post("views/evrak-takip/api.php", {
            action: "evrak-pades-tamamla", id: button.data("id"), temp_id: signed.tempId, signed_pdf: signed.signed.signed_pdf
          });
        }).then(completed => {
          if (completed.status !== "success") throw new Error(completed.message || "PAdES imzası kabul edilmedi.");
          return otomatikKepGonder(completed, button.data("id"), result.value).then(sent => ({ completed, sent }));
        }).then(({ completed, sent }) => {
          const title = sent ? "İmzalandı ve KEP ile Gönderildi" : "İmza Doğrulandı";
          const message = sent ? `${completed.message}<br><br><b>PTT Takip Kodu:</b><br>${$('<div>').text(sent.kep_id || '').html()}` : completed.message;
          return Swal.fire({ icon: "success", title: title, html: message }).then(() => location.reload());
        }).catch(error => {
          button.prop("disabled", false);
          if (error && error.code === "PADES_OK_KEP_FAILED") {
            Swal.fire("İmza Tamamlandı, KEP Gönderilemedi", `${error.message}<br><br>Evrak imzalı olarak kaydedildi. KEP gönderimini evrak listesinden tekrar başlatabilirsiniz.`, "warning")
              .then(() => location.reload());
          } else {
            Swal.fire("PAdES İmza Hatası", error.message || "E-imza işlemi tamamlanamadı.", "error");
          }
        });
      })
      .catch(error => {
        if (error && error.code === "EIMZA_AGENT_UPDATE_REQUIRED") {
          Swal.fire({
            icon: "warning",
            title: "E-İmza Uygulamasını Güncelleyin",
            html: `
              <p class="mb-3">PAdES için <b>Ersan E-İmza v1.2.0</b> gereklidir.</p>
              <a href="downloads/ErsanEImza.jar?v=1.3.1" download="ErsanEImza.jar"
                 class="btn btn-primary px-4 py-2">
                <i class="bx bxs-download me-1"></i> E-İmza v1.3.1 İndir
              </a>
              <div class="small text-muted mt-3">Eski uygulamayı tamamen kapatın, ardından indirdiğiniz dosyayı çalıştırın.</div>`,
            showConfirmButton: true,
            confirmButtonText: "Kapat"
          });
          return;
        }
        Swal.fire("E-İmza Uygulaması", error.message, "error");
      });
  });

  $("#btnEImzaGeriAl").on("click", function () {
    eImzaIstek("evrak-e-imza-geri-al", {
      title: "Evrakı Üzerime Geri Al",
      html: "Elektronik imza süreci iptal edilecek ve evrak <b>taslak</b> durumuna dönecek.<br>Alınmış tüm imzalar sıfırlanır ve işlem kayıt altına alınır.",
      icon: "warning",
      color: "#0ea5e9",
      confirmText: "Evet, Geri Al",
      buttonSelector: "#btnEImzaGeriAl"
    });
  });

  $("#btnEImzaIade").on("click", function () {
    const button = $(this);
    Swal.fire({
      title: "Düzeltilmek Üzere İade Et",
      html: "Evrak imzalanmadan <b>taslak</b> durumuna döndürülecek ve gerekçe evrakı hazırlayan kullanıcıya bildirilecek.",
      input: "textarea",
      inputLabel: "İade gerekçesi",
      inputPlaceholder: "Örnek: İlgi bölümündeki esas numarası hatalı, düzeltilip yeniden gönderilmeli.",
      inputAttributes: { maxlength: 2000, rows: 4 },
      showCancelButton: true,
      confirmButtonColor: "#f43f5e",
      cancelButtonColor: "#64748b",
      confirmButtonText: "İade Et",
      cancelButtonText: "Vazgeç",
      inputValidator: value => (!value || value.trim().length < 5) ? "Gerekçeyi en az 5 karakter olacak şekilde yazınız." : undefined
    }).then(result => {
      if (!result.isConfirmed) return;
      button.prop("disabled", true);
      $.post("views/evrak-takip/api.php", { action: "evrak-e-imza-iade", id: button.data("id"), gerekce: result.value }, function (response) {
        if (response.status === "success") {
          Swal.fire("İade Edildi", response.message, "success").then(() => location.reload());
        } else {
          button.prop("disabled", false);
          Swal.fire("Hata", response.message, "error");
        }
      }).fail(() => {
        button.prop("disabled", false);
        Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
      });
    });
  });

  $("#btnEImzaOnayaSun").on("click", function () {
    syncContent();
    if (!validateForm()) return;
    if (!$("#imza_kullanici_ids").val()?.length) {
      $("#collapseImzaYetkilileri").collapse("show");
      Swal.fire("Eksik Bilgi", "E-İmza ile onaya sunmak için en az bir imza yetkilisi seçmelisiniz.", "warning");
      return;
    }
    Swal.fire({
      title: "E-İmza ile Onaya Sun",
      html: "Evrak önce kaydedilecek, ardından imzacıların onayına sunulacak.<br>Her imzacı akıllı kartı ve PIN koduyla PAdES imzası atacaktır.<br><b>KEP seçildiyse son imzadan sonra evrak aynı kartla otomatik gönderilir.</b><br>Onaya sunulan evrakın içeriği süreç boyunca değiştirilemez.",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#556ee6",
      cancelButtonColor: "#64748b",
      confirmButtonText: "Kaydet ve Onaya Sun",
      cancelButtonText: "Vazgeç"
    }).then(result => {
      if (!result.isConfirmed) return;
      const button = $("#btnEImzaOnayaSun").prop("disabled", true);
      const serbestBirak = () => button.prop("disabled", false);
      $.ajax({
        url: "views/evrak-takip/api.php", type: "POST", data: buildFormData(), contentType: false, processData: false,
        success: function (kayit) {
          if (kayit.status !== "success" || !kayit.id) {
            serbestBirak();
            Swal.fire("Hata", kayit.message || "Evrak kaydedilemedi.", "error");
            return;
          }
          $.post("views/evrak-takip/api.php", { action: "evrak-e-imza-onaya-sun", id: kayit.id }, function (response) {
            if (response.status === "success") {
              Swal.fire("Onaya Sunuldu", response.message, "success")
                .then(() => location.href = "index?p=evrak-takip/list");
            } else {
              serbestBirak();
              Swal.fire("Evrak Kaydedildi, Onaya Sunulamadı", response.message, "warning")
                .then(() => location.href = "index?p=evrak-takip/list");
            }
          }).fail(() => {
            serbestBirak();
            Swal.fire("Hata", "Evrak kaydedildi ancak onaya sunulamadı. Sunucu ile iletişim kurulamadı.", "error");
          });
        },
        error: function () {
          serbestBirak();
          Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
        }
      });
    });
  });

  $("#btnIslakImzaOnayaSun").on("click", function () {
    syncContent();
    if (!validateForm()) return;
    if ($("#gonderim_kanali").val() !== "normal") return Swal.fire("Uygun Değil", "Islak imza akışı yalnız Normal Gönderim türünde kullanılabilir.", "warning");
    if (!$("#imza_kullanici_ids").val()?.length) {
      $("#collapseImzaYetkilileri").collapse("show");
      return Swal.fire("Eksik Bilgi", "Islak imza onayı için en az bir imza yetkilisi seçmelisiniz.", "warning");
    }
    Swal.fire({
      title: "Islak İmza ile Onaya Sun",
      html: "Evrak kaydedilecek ve seçilen yetkililere sırayla gönderilecektir.<br>Yetkililer, belgeyi fiziksel olarak imzaladıklarını sistemde teyit edecektir.<br><b>Bu akış nitelikli elektronik imza oluşturmaz.</b>",
      icon: "question", showCancelButton: true, confirmButtonColor: "#f59e0b", cancelButtonColor: "#64748b",
      confirmButtonText: "Kaydet ve Onaya Sun", cancelButtonText: "Vazgeç"
    }).then(result => {
      if (!result.isConfirmed) return;
      const button = $("#btnIslakImzaOnayaSun").prop("disabled", true);
      $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: buildFormData(), contentType: false, processData: false })
        .then(kayit => {
          if (kayit.status !== "success" || !kayit.id) return $.Deferred().reject({ message: kayit.message || "Evrak kaydedilemedi." }).promise();
          return $.post("views/evrak-takip/api.php", { action: "evrak-islak-imza-onaya-sun", id: kayit.id });
        })
        .done(response => {
          if (response.status !== "success") throw new Error(response.message || "Onaya sunulamadı.");
          Swal.fire("Onaya Sunuldu", response.message, "success").then(() => location.href = "index?p=evrak-takip/list");
        })
        .fail(xhr => {
          button.prop("disabled", false);
          Swal.fire("Hata", xhr.responseJSON?.message || xhr.message || "İşlem tamamlanamadı.", "error");
        });
    });
  });

  $("#btnIslakImzaOnayla").on("click", function () {
    const button = $(this);
    Swal.fire({ title: "Islak İmza Onayı", text: "Belgeyi fiziksel olarak imzaladığınızı ve onayladığınızı teyit ediyor musunuz?", icon: "question", showCancelButton: true, confirmButtonColor: "#f59e0b", confirmButtonText: "İmzaladım, Onayla", cancelButtonText: "Vazgeç" })
      .then(result => {
        if (!result.isConfirmed) return;
        button.prop("disabled", true);
        $.post("views/evrak-takip/api.php", { action: "evrak-islak-imza-onayla", id: button.data("id") })
          .done(response => {
            if (response.status === "success") Swal.fire("Onaylandı", response.message, "success").then(() => location.reload());
            else { button.prop("disabled", false); Swal.fire("Hata", response.message || "Onay kaydedilemedi.", "error"); }
          })
          .fail(xhr => { button.prop("disabled", false); Swal.fire("Hata", xhr.responseJSON?.message || xhr.message || "Onay kaydedilemedi.", "error"); });
      });
  });

  $("#btnMobilImzaOnayaSun").on("click", function () {
    syncContent();
    if (!validateForm()) return;
    if ($("#gonderim_kanali").val() !== "normal") return Swal.fire("Uygun Değil", "Mobil imza akışı yalnız Normal Gönderim türünde kullanılabilir.", "warning");
    if (!$("#imza_kullanici_ids").val()?.length) {
      $("#collapseImzaYetkilileri").collapse("show");
      return Swal.fire("Eksik Bilgi", "Mobil imza onayı için en az bir imza yetkilisi seçmelisiniz.", "warning");
    }
    Swal.fire({
      title: "Mobil İmza ile Onaya Sun",
      html: "Evrak kaydedilecek, kilitlenecek ve seçilen yetkililer için mobil imza onay sırası oluşturulacaktır.<br><b>Mobil operatör üzerinden gerçek imzalama işlemi ileride devreye alınacaktır.</b>",
      icon: "info", showCancelButton: true, confirmButtonColor: "#0ea5e9", cancelButtonColor: "#64748b",
      confirmButtonText: "Kaydet ve Akışı Başlat", cancelButtonText: "Vazgeç"
    }).then(result => {
      if (!result.isConfirmed) return;
      const button = $("#btnMobilImzaOnayaSun").prop("disabled", true);
      $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: buildFormData(), contentType: false, processData: false })
        .then(kayit => {
          if (kayit.status !== "success" || !kayit.id) return $.Deferred().reject({ message: kayit.message || "Evrak kaydedilemedi." }).promise();
          return $.post("views/evrak-takip/api.php", { action: "evrak-mobil-imza-onaya-sun", id: kayit.id });
        })
        .done(response => {
          if (response.status === "success") Swal.fire("Mobil İmza Akışı Başlatıldı", response.message, "success").then(() => location.href = "index?p=evrak-takip/list");
          else { button.prop("disabled", false); Swal.fire("Hata", response.message || "Mobil imza akışı başlatılamadı.", "error"); }
        })
        .fail(xhr => {
          button.prop("disabled", false);
          Swal.fire("Hata", xhr.responseJSON?.message || xhr.message || "İşlem tamamlanamadı.", "error");
        });
    });
  });

  $("#btnMobilImzaOnayla").on("click", function () {
    Swal.fire({
      title: "Mobil İmza Entegrasyonu Hazırlanıyor",
      text: "Evrak mobil imza sırasına alınmıştır. Operatör üzerinden mobil imzalama işlemi ileride devreye alınacaktır.",
      icon: "info",
      confirmButtonText: "Tamam"
    });
  });

  if (window.gidenEvrakKilitli) {
    form.addClass("evrak-kilitli");
    form.find("input, select, textarea").not("input[type=hidden]").prop("disabled", true);
    $("#giden_evrak_icerik").summernote("disable");
    $("#btnIcraUstYaziAc, #btnAiTaslakAc").addClass("d-none");
    $("#ek_dosyalari").closest(".border.rounded-3").addClass("d-none");
    $("#imzaSiraContainer").find("button").prop("disabled", true);
  }

  // ==========================================
  // UETS / KEP İLE GÖNDERİM İŞLEMLERİ
  // ==========================================
  $(document).on("click", "#btnUetsGonderModalAc, #btnUetsHizliGonder", function () {
    $("#uetsAliciDogrulamaSonuc").html("");
    $("#modalUetsGonder").modal("show");
  });

  // Alıcı UETS Doğrulama
  $("#btnUetsGonderDogrula").on("click", function () {
    const query = ($("#recipient_uets").length ? $("#recipient_uets") : $("#uetsGonderAlici")).val().trim();
    if (!query) {
      $("#uetsAliciDogrulamaSonuc").html('<span class="text-danger">Lütfen UETS adresi giriniz.</span>');
      return;
    }
    const $btn = $(this);
    const origHtml = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin"></i>');
    $("#uetsAliciDogrulamaSonuc").html('<span class="text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> UETS Rehberi sorgulanıyor...</span>');

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "uets-alici-ara", query: query },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(origHtml);
        if (res.status === "success" && res.data && res.data.length > 0) {
          const rec = res.data[0];
          const name = rec.name || rec.unvan || "Kurum/Kişi";
          $("#uetsAliciDogrulamaSonuc").html(
            `<span class="text-success"><i class="bx bx-check-circle me-1"></i><strong>Doğrulandı:</strong> ${name} (${rec.address || query})</span>`
          );
        } else {
          $("#uetsAliciDogrulamaSonuc").html(
            `<span class="text-danger"><i class="bx bx-x-circle me-1"></i>Adres doğrulanamadı. Lütfen kontrol ediniz.</span>`
          );
        }
      },
      error: function () {
        $btn.prop("disabled", false).html(origHtml);
        $("#uetsAliciDogrulamaSonuc").html('<span class="text-danger">Doğrulama servisine bağlanılamadı.</span>');
      },
    });
  });

  // UETS Gönder Form Submit
  $("#formUetsGonder").on("submit", function (e) {
    e.preventDefault();
    const recipient = ($("#recipient_uets").length ? $("#recipient_uets") : $("#uetsGonderAlici")).val().trim();
    if (!recipient) {
      Swal.fire("Uyarı", "Alıcı UETS adresi girilmelidir.", "warning");
      return;
    }

    Swal.fire({
      title: "E-Tebligat Gönderilecek",
      text: "Evrak EYP formatında şifrelenip PTT UETS sistemine iletilecektir. Bu işlem geri alınamaz. Onaylıyor musunuz?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Evet, Gönder",
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#556ee6",
    }).then((result) => {
      if (result.isConfirmed) {
        const $btn = $("#btnUetsGonderSubmit");
        $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Gönderiliyor...');
        Swal.fire({
          title: "PTT UETS'e İletiliyor...",
          text: "EYP paketi oluşturuluyor ve UETS kuyruğuna alınıyor.",
          allowOutsideClick: false,
          didOpen: () => { Swal.showLoading(); }
        });

        const formData = $(this).serialize() + "&action=uets-gonder";

        $.ajax({
          url: "views/evrak-takip/api.php",
          type: "POST",
          data: formData,
          dataType: "json",
          success: function (res) {
            $btn.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> Tebligatı Gönder');
            if (res.status === "success") {
              $("#modalUetsGonder").modal("hide");
              Swal.fire({
                icon: "success",
                title: "Tebligat Gönderildi!",
                html: `<strong>UETS Mesaj ID:</strong> ${res.uets_message_id}<br><strong>Barkod:</strong> ${res.barcode || "-"}`,
              }).then(() => {
                window.location.href = "index?p=evrak-takip/list";
              });
            } else {
              Swal.fire("Hata", res.message || "Tebligat gönderilemedi.", "error");
            }
          },
          error: function (xhr) {
            $btn.prop("disabled", false).html('<i class="bx bx-paper-plane me-1"></i> Tebligatı Gönder');
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "UETS servisiyle bağlantı kurulamadı.";
            Swal.fire("Gönderim Hatası", msg, "error");
          }
        });
      }
    });
  });

  // =========================================================================
  // PTT KEP ENTEGRASYONU (SAYFA İÇİ İŞLEMLER)
  // =========================================================================

  // 1. Alıcı KEP Adresi Doğrulama (RehberTopluKontrol)
  function parseKepRecipients() {
    const raw = $("#kep_alicilar").val() || "";
    return [...new Set(String(raw).split(/[;,\n\r]+/).map(value => value.trim()).filter(Boolean))];
  }

  function dogrulaGidenKepAdresi(showSpinner = true) {
    const $input = $("#kep_alicilar");
    const addresses = parseKepRecipients();
    const $res = $("#gidenKepDogrulamaSonuc");
    const $btn = $("#btnGidenKepDogrula");

    if (!addresses.length) {
      $res.html('<span class="text-danger small"><i class="bx bx-error me-1"></i>Lütfen en az bir KEP adresi giriniz.</span>');
      return;
    }

    if (showSpinner) {
      $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin font-size-14"></i>');
      $res.html('<span class="text-muted small"><i class="bx bx-loader-alt bx-spin me-1"></i> PTT Rehberi sorgulanıyor...</span>');
    }

    const requests = addresses.map(address => $.ajax({ url: "views/evrak-takip/api.php", type: "POST", data: { action: "kep-alici-dogrula", alici_kep: address }, dataType: "json" })
      .then(response => ({ address, response }), xhr => ({ address, response: { status: "error", message: xhr.responseJSON?.message || "Doğrulanamadı" } })));
    $.when.apply($, requests).then(function () {
      const results = requests.length === 1 ? [arguments[0]] : Array.from(arguments);
      $btn.prop("disabled", false).html('<i class="bx bx-search fs-5"></i>');
      $res.html(results.map(item => {
        const response = item.response || {};
        const ok = response.status === "success" && response.exist && response.suitable;
        const icon = ok ? "bx-check-circle text-success" : "bx-error text-warning";
        const message = ok ? "Doğrulandı" : (response.message || "Rehberde bulunamadı veya iletiye uygun değil");
        return `<div class="small"><i class="bx ${icon} me-1"></i><span class="font-monospace">${escapeHtml(item.address)}</span> — ${escapeHtml(message)}</div>`;
      }).join(""));
    });
  }

  $("#btnGidenKepDogrula").on("click", function () {
    dogrulaGidenKepAdresi(true);
  });

  $("#kep_alicilar").on("change", function () {
    if ($(this).val().trim().includes("@")) {
      dogrulaGidenKepAdresi(false);
    }
  });

  // 2. Sayfa İçi KEP ile Gönder (Hızlı Buton ve İşlemler Menüsü)
  function gidenKepGonderimiBaslat(aliciOverride, paketOverride) {
    const $aliciKep = $("#kep_alicilar");
    const aliciAddress = String(aliciOverride || parseKepRecipients().join("; ")).trim();
    const evrakIdEnc = $('input[name="id"]').val() || "";

    if (!window.gidenKepSettings || !window.gidenKepSettings.has_settings) {
      Swal.fire("KEP Kullanıcı Bilgileri Eksik", "KEP gönderimi için Evrak Takip → İşlemler → UETS / KEP Ayarları bölümünden kendi TCKN, parola ve web servis şifrenizi kaydediniz.", "warning");
      return;
    }

    if (!aliciAddress) {
      Swal.fire({
        title: "Onaylı Evrakı KEP ile Gönder",
        html: `<div class="text-start">
          <label for="swalGidenKepAlici" class="form-label fw-semibold">Alıcı KEP Adresi</label>
          <input id="swalGidenKepAlici" class="form-control font-monospace" placeholder="kurum@hs01.kep.tr" autocomplete="off">
          <div class="form-text mb-3">Birden fazla adresi noktalı virgülle ayırabilirsiniz.</div>
          <label for="swalGidenKepPaket" class="form-label fw-semibold">Paket Türü</label>
          <select id="swalGidenKepPaket" class="form-select">
            <option value="Standart">Standart KEP İletisi</option>
            <option value="EYazisma">E-Yazışma (EYP Paketi)</option>
          </select>
          <div class="alert alert-info py-2 px-3 mt-3 mb-0 font-size-12"><i class="bx bx-info-circle me-1"></i> Onaylı üst yazı PDF’i ve kayıtlı ekler değiştirilmeden gönderilir.</div>
        </div>`,
        icon: "info",
        showCancelButton: true,
        confirmButtonText: '<i class="bx bx-mail-send me-1"></i> Devam Et',
        cancelButtonText: "Vazgeç",
        preConfirm: () => {
          const address = String($("#swalGidenKepAlici").val() || "").trim();
          const addresses = address.split(/[;,\n\r]+/).map(item => item.trim()).filter(Boolean);
          if (!addresses.length || addresses.some(item => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(item))) {
            Swal.showValidationMessage("Geçerli bir alıcı KEP adresi giriniz.");
            return false;
          }
          return { address: addresses.join("; "), packageType: $("#swalGidenKepPaket").val() || "Standart" };
        }
      }).then(result => {
        if (result.isConfirmed) gidenKepGonderimiBaslat(result.value.address, result.value.packageType);
      });
      return;
    }

    const paketTuru = paketOverride || $("#kep_paket_turu").val() || "Standart";
    const paketTuruAd = paketTuru === "EYazisma" ? "E-Yazışma (EYP Paketi)" : "Standart KEP İletisi";

    Swal.fire({
      title: "Evrak KEP ile Sevk Edilecek",
      html: `
        <div class="text-start font-size-13 p-3 bg-light rounded border mb-3">
          <div class="mb-1"><strong>Alıcı KEP:</strong> <span class="font-monospace text-primary">${aliciAddress}</span></div>
          <div class="mb-2"><strong>İleti Türü:</strong> ${paketTuruAd}</div>
          <div id="swalEimzaCihazDurum" class="alert alert-info py-2 px-3 mb-0 font-size-12">
            <i class="bx bx-loader-alt bx-spin me-1"></i> Takılı e-imza cihazı taranıyor...
          </div>
        </div>
        <div class="text-start mb-2">
          <label class="form-label fw-semibold font-size-13 mb-1" for="swalKepEimzaPin">
            <i class="bx bx-key text-warning me-1"></i> E-İmza Kart PIN Kodu
          </label>
          <input type="password" id="swalKepEimzaPin" class="form-control" placeholder="E-İmza PIN kodunuzu giriniz" maxlength="20" autocomplete="off">
          <div class="form-text font-size-11 text-muted">PIN kodunuz doğrudan akıllı karta iletilir, sunucuda kaydedilmez.</div>
        </div>
      `,
      icon: "info",
      showCancelButton: true,
      confirmButtonText: '<i class="bx bx-paper-plane me-1"></i> İmzala ve Sevk Et',
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#50a5f1",
      didOpen: () => {
        // Canlı e-imza kart tespiti (E-Tuğra, SafeNet, AKİS vb.)
        $.getJSON("views/evrak-takip/api.php?action=eimza-kart-bilgisi", function (card) {
          const $box = $("#swalEimzaCihazDurum");
          if (card && card.status === "success") {
            $box.removeClass("alert-info alert-warning").addClass("alert-success").html(`
              <i class="bx bx-chip me-1"></i> <strong>${escapeHtml(card.device_type || 'Akıllı Kart')} Algılandı:</strong>
              <div>${escapeHtml(card.owner_name || '')} <span class="text-muted font-monospace font-size-11">(TC: ${escapeHtml(card.tcno || '')})</span></div>
            `);
          } else {
            $box.removeClass("alert-info alert-success").addClass("alert-warning").html(`
              <i class="bx bx-error-circle me-1"></i> ${escapeHtml(card?.message || 'E-İmza cihazı bulunamadı. Lütfen USB kartı takınız.')}
            `);
          }
        });

        setTimeout(() => {
          const pinInput = document.getElementById("swalKepEimzaPin");
          if (pinInput) pinInput.focus();
        }, 300);
      },
      preConfirm: () => {
        const pin = document.getElementById("swalKepEimzaPin").value.trim();
        if (!pin) {
          Swal.showValidationMessage("Lütfen E-İmza kart PIN kodunuzu giriniz.");
          return false;
        }
        return pin;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        const pin = result.value;

        const legacySend = (pinCode) => {
          Swal.fire({
            title: "PTT KEP Sistemine İletiliyor...",
            html: `<div class="p-2"><i class="bx bx-loader-alt bx-spin font-size-24 text-primary mb-2 d-block"></i>SafeNet akıllı kart üzerinde CAdES-BES imzası üretiliyor ve PTT KEP ağına sevk ediliyor.<br><small class="text-muted">Lütfen bekleyiniz...</small></div>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
          });

          const postData = {
            action: "kep-gonder",
            evrak_id: evrakIdEnc,
            alici_kep: aliciAddress,
            paket_turu: paketTuru,
            eimza_pin: pinCode
          };

          $.ajax({
            url: "views/evrak-takip/api.php",
            type: "POST",
            data: postData,
            dataType: "json",
            success: function (res) {
              if (res.status === "success") {
                Swal.fire({
                  icon: "success",
                  title: "KEP İletisi Başarıyla Gönderildi!",
                  html: `<strong>PTT Takip Kodu (KepId):</strong><br><span class="font-monospace font-size-14 text-primary">${res.kep_id}</span><div class="mt-2 text-success small"><i class="bx bx-check-double"></i> Resmi e-imza doğrulandı, PTT tarafından kabul edildi.</div>`,
                  confirmButtonText: "Tamam"
                }).then(() => {
                  window.location.reload();
                });
              } else {
                Swal.fire("KEP Gönderim Hatası", res.message || "İleti sevk edilemedi.", "error");
              }
            },
            error: function (xhr) {
              const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "PTT KEP servisine bağlanılamadı.";
              Swal.fire("Gönderim Hatası", msg, "error");
            }
          });
        };

        // Seçenek B: Kullanıcının yerel e-imza ajanını kontrol et
        fetch("http://127.0.0.1:9191/status", { method: "GET", mode: "cors" })
          .then(r => r.json())
          .then(agentStatus => {
            if (agentStatus && agentStatus.status === "success") {
              // 1. Adım: MimeYap
              Swal.fire({
                title: "PTT KEP İletisi Hazırlanıyor...",
                html: `<div class="p-2"><i class="bx bx-loader-alt bx-spin font-size-24 text-primary mb-2 d-block"></i>1/3: PTT KEP MIME paketi oluşturuluyor...<br><small class="text-muted">Lütfen bekleyiniz...</small></div>`,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
              });

              $.ajax({
                url: "views/evrak-takip/api.php",
                type: "POST",
                data: { action: "kep-mime-hazirla", evrak_id: evrakIdEnc, alici_kep: aliciAddress, paket_turu: paketTuru },
                dataType: "json"
              }).done(mimeRes => {
                if (mimeRes.status !== "success" || !mimeRes.eHash || !mimeRes.temp_id) {
                  Swal.fire("MIME Hazırlama Hatası", mimeRes.message || "PTT paketi oluşturulamadı.", "error");
                  return;
                }

                // 2. Adım: Ajan ile imzala
                Swal.fire({
                  title: "E-İmza Üretiliyor...",
                  html: `<div class="p-2"><i class="bx bx-loader-alt bx-spin font-size-24 text-primary mb-2 d-block"></i>2/3: Akıllı kart üzerinden CAdES-BES imzası alınıyor...<br><small class="text-muted">${agentStatus.owner_name || ''} (${agentStatus.device_type || ''})</small></div>`,
                  allowOutsideClick: false,
                  showConfirmButton: false,
                  didOpen: () => Swal.showLoading()
                });

                fetch("http://127.0.0.1:9191/sign", {
                  method: "POST",
                  mode: "cors",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({ hash: mimeRes.eHash, pin: pin })
                })
                .then(r => r.json())
                .then(signRes => {
                  if (signRes.status !== "success" || !signRes.signature) {
                    Swal.fire("E-İmza Hatası", signRes.message || "Kart imzalamayı reddetti.", "error");
                    return;
                  }

                  // 3. Adım: PTT'ye sevk et
                  Swal.fire({
                    title: "PTT KEP Sistemine Sevk Ediliyor...",
                    html: `<div class="p-2"><i class="bx bx-loader-alt bx-spin font-size-24 text-primary mb-2 d-block"></i>3/3: İmzalı paket PTT KEP ağına iletiliyor...<br><small class="text-muted">Lütfen bekleyiniz...</small></div>`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                  });

                  $.ajax({
                    url: "views/evrak-takip/api.php",
                    type: "POST",
                    data: { action: "kep-smime-sevk", temp_id: mimeRes.temp_id, mesajid: mimeRes.mesajid, signature: signRes.signature },
                    dataType: "json"
                  }).done(sendRes => {
                    if (sendRes.status === "success") {
                      Swal.fire({
                        icon: "success",
                        title: "KEP İletisi Başarıyla Gönderildi!",
                        html: `<strong>PTT Takip Kodu (KepId):</strong><br><span class="font-monospace font-size-14 text-primary">${sendRes.kep_id}</span><div class="mt-2 text-success small"><i class="bx bx-check-double"></i> Resmi CAdES-BES imzanız doğrulandı, PTT tarafından kabul edildi.</div>`,
                        confirmButtonText: "Tamam"
                      }).then(() => location.reload());
                    } else {
                      Swal.fire("KEP Gönderim Hatası", sendRes.message || "İleti sevk edilemedi.", "error");
                    }
                  }).fail(xhr => {
                    Swal.fire("Gönderim Hatası", xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı.", "error");
                  });
                }).catch(err => {
                  Swal.fire("Yerel Ajan Hatası", "Yerel E-İmza Ajanı ile iletişim hatası: " + err.message, "error");
                });
              }).fail(xhr => {
                Swal.fire("MIME Hazırlama Hatası", xhr.responseJSON?.message || "PTT KEP servisine bağlanılamadı.", "error");
              });
            } else {
              legacySend(pin);
            }
          })
          .catch(() => {
            legacySend(pin);
          });
      }
    });
  }

  $(document).on("click", "#btnGidenKepHizliGonder, #btnGidenKepIslemlerGonder", function (e) {
    e.preventDefault();
    gidenKepGonderimiBaslat();
  });

  if (window.gidenKepAutoStart && !window.gidenKepMesaj) {
    setTimeout(() => gidenKepGonderimiBaslat(), 350);
  }

  // 3. Delilleri Yenile / Güncelle Butonu
  $(document).on("click", "#btnKepDelilGuncelle", function () {
    const $btn = $(this);
    const kepId = $btn.data("kep-id");
    const origHtml = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Güncelleniyor...');

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "kep-delil-listesi", kep_id: kepId },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(origHtml);
        if (res.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Deliller Güncellendi",
            text: res.message || "Yasal delil kayıtları PTT'den başarıyla güncellendi.",
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire("Bilgi", res.message || "Yeni delil bulunamadı.", "info");
        }
      },
      error: function () {
        $btn.prop("disabled", false).html(origHtml);
        Swal.fire("Hata", "Delil sorgulama servisine erişilemedi.", "error");
      }
    });
  });

  // 4. KEP Ayarları Modalı İşlemleri
  $(document).on("click", "#btnKepAyarlarModalAc", function () {
    $("#modalKepAyarlar").modal("show");
    $("#kepTestResult").addClass("d-none").empty();

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "kep-ayarlari-getir" },
      dataType: "json",
      success: function (res) {
        if (res.status === "success" && res.data) {
          const d = res.data;
          $("input[name='kep_adresi']").val(d.kep_adresi || "");
          $("input[name='yetkili_tcno']").val(d.yetkili_tcno || "");
          $("input[name='wsdl_url']").val(d.wsdl_url || "https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl");
          if (d.ortam) {
            $("#kep_ortam").val(d.ortam).trigger("change");
          }
          if (d.has_secrets) {
            $("#kepParolaHelpText").text("Kayıtlı parola mevcut (Değiştirmeyecekseniz boş bırakınız).");
            $("#kepSifreHelpText").text("Kayıtlı şifre mevcut (Değiştirmeyecekseniz boş bırakınız).");
          }
          if (d.bakiye_kontor !== null) {
            $("#kepBakiyeText").text(`Kayıtlı Bakiye: ${d.bakiye_kontor} ${d.bakiye_cins || "Kontör"}`);
          }
          if (d.son_bakiye_sorgu) {
            $("#kepSonSorguText").text(`(Son: ${d.son_bakiye_sorgu})`);
          }
        }
      }
    });
  });

  // Şifre Göster / Gizle
  $(".btn-toggle-password").on("click", function () {
    const target = $(this).data("target");
    const $inp = $(`input[name='${target}']`);
    if ($inp.length) {
      const isPass = $inp.attr("type") === "password";
      $inp.attr("type", isPass ? "text" : "password");
      $(this).find("i").toggleClass("bx-show bx-hide");
    }
  });

  // KEP Bağlantı Testi
  $("#btnKepTestConnection").on("click", function () {
    const $btn = $(this);
    const origHtml = $btn.html();
    const $res = $("#kepTestResult");
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Test Ediliyor...');
    $res.removeClass("d-none alert-success alert-danger").addClass("alert alert-info").html('<i class="bx bx-loader-alt bx-spin me-1"></i> PTT KEP servisine bağlanılıyor...');

    const formData = $("#formKepAyarlar").serialize() + "&action=kep-baglanti-test";

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(origHtml);
        if (res.status === "success") {
          $res.removeClass("alert-info alert-danger").addClass("alert alert-success").html(
            `<i class="bx bx-check-circle me-1"></i><strong>Bağlantı Başarılı!</strong> ${res.message || "Bakiye başarıyla sorgulandı."}`
          );
        } else {
          $res.removeClass("alert-info alert-success").addClass("alert alert-danger").html(
            `<i class="bx bx-x-circle me-1"></i><strong>Bağlantı Hatası:</strong> ${res.message || "Giriş bilgileri doğrulanamadı."}`
          );
        }
      },
      error: function (xhr) {
        $btn.prop("disabled", false).html(origHtml);
        const err = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "PTT KEP servisine erişilemedi.";
        $res.removeClass("alert-info alert-success").addClass("alert alert-danger").html(
          `<i class="bx bx-x-circle me-1"></i><strong>Hata:</strong> ${err}`
        );
      }
    });
  });

  // KEP Bakiye Sorgula
  $("#btnKepCheckBalance").on("click", function () {
    const $btn = $(this);
    const origHtml = $btn.html();
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>');

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: { action: "kep-bakiye-sorgula" },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html(origHtml);
        if (res.status === "success") {
          $("#kepBakiyeText").text(`Kayıtlı Bakiye: ${res.miktar} ${res.cins || "Kontör"}`);
          Swal.fire("KEP Bakiyesi", `Güncel Bakiye: <strong>${res.miktar} ${res.cins || "Kontör"}</strong>`, "info");
        } else {
          Swal.fire("Bakiye Sorgulama Hatası", res.message || "Bakiye sorgulanamadı.", "warning");
        }
      },
      error: function () {
        $btn.prop("disabled", false).html(origHtml);
        Swal.fire("Hata", "Bakiye servisine erişilemedi.", "error");
      }
    });
  });

  // KEP Ayarları Kaydet
  $("#formKepAyarlar").on("submit", function (e) {
    e.preventDefault();
    const $btn = $("#btnSaveKepSettings");
    $btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

    const formData = $(this).serialize() + "&action=kep-ayarlari-kaydet";

    $.ajax({
      url: "views/evrak-takip/api.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false).html('<i class="bx bx-save me-1"></i> Ayarları Kaydet');
        if (res.status === "success") {
          $("#modalKepAyarlar").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: res.message || "KEP ayarları kaydedildi.",
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire("Hata", res.message || "Ayarlar kaydedilemedi.", "error");
        }
      },
      error: function (xhr) {
        $btn.prop("disabled", false).html('<i class="bx bx-save me-1"></i> Ayarları Kaydet');
        const err = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Ayarlar kaydedilirken hata oluştu.";
        Swal.fire("Hata", err, "error");
      }
    });
  });

  $("#gidenPdfModal").on("hidden.bs.modal", clearPdf);
  renderAttachments();
  if (typeof feather !== "undefined") feather.replace();

  adjustGidenEvrakLayout();
  window.setTimeout(adjustGidenEvrakLayout, 100);
  window.setTimeout(adjustGidenEvrakLayout, 500);
  if (new URLSearchParams(window.location.search).get("islem") === "imzala" && $("#btnEImzaOnayla, #btnIslakImzaOnayla, #btnMobilImzaOnayla").length) {
    window.setTimeout(() => $("#btnEImzaOnayla, #btnIslakImzaOnayla, #btnMobilImzaOnayla").first().trigger("click"), 350);
  }
  $(window).on("resize orientationchange", adjustGidenEvrakLayout);
  $("#gidenEvrakTabs button").on("shown.bs.tab", adjustGidenEvrakLayout);
});
