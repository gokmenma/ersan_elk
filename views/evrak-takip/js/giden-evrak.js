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
    badge.text(count + "/3 Seçildi");
    if (count === 3) {
      badge.removeClass("bg-primary-subtle text-primary bg-secondary-subtle text-secondary").addClass("bg-success-subtle text-success");
    } else if (count === 0) {
      badge.removeClass("bg-success-subtle text-success bg-primary-subtle text-primary").addClass("bg-secondary-subtle text-secondary");
    } else {
      badge.removeClass("bg-success-subtle text-success bg-secondary-subtle text-secondary").addClass("bg-primary-subtle text-primary");
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
  }

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
    const order = attachments.map(item => item.type === "existing" ? { type: "existing", id: item.id } : { type: "new", key: item.key });
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

  function toggleUstYaziDurumu() {
    const isChecked = $("#ust_yazi_gerekli_degil").is(":checked");
    if (isChecked) {
      $("#ustYaziMetinBilgisi").removeClass("d-none");
    } else {
      $("#ustYaziMetinBilgisi").addClass("d-none");
    }
  }

  $(document).on("change", "#ust_yazi_gerekli_degil", toggleUstYaziDurumu);
  toggleUstYaziDurumu();

  function validateForm() {
    if (!$("#evrak_no").val().trim() || !$("#konu").val().trim() || !$("#kurum_adi").val().trim()) {
      Swal.fire("Eksik Bilgi", "Sayı, konu ve muhatap alanları zorunludur.", "warning");
      return false;
    }
    if (!$("#imza_kullanici_ids").val()?.length) {
      Swal.fire("Eksik Bilgi", "En az bir imza atacak kişi seçiniz.", "warning");
      return false;
    }
    const ustYaziGerekliDegil = $("#ust_yazi_gerekli_degil").is(":checked");
    if (!ustYaziGerekliDegil && $("#giden_evrak_icerik").summernote("isEmpty")) {
      Swal.fire("Eksik Bilgi", "Yazı içeriği boş bırakılamaz.", "warning");
      return false;
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
    const tabContentPadding = 32;
    const availableTabContentHeight = cardBodyHeight - tabsHeight - tabContentPadding;

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

  $("#btnEImzaOnayla").on("click", function () {
    eImzaIstek("evrak-e-imza-onayla", {
      title: "E-İmza ile Onayla",
      html: "Evrakı elektronik imza ile onaylıyorsunuz.<br><b>Tüm imzacılar onayladığında evrak elektronik imzalı hâle gelir ve içeriği bir daha değiştirilemez.</b>",
      icon: "question",
      color: "#22c55e",
      confirmText: "Evet, Onayla",
      buttonSelector: "#btnEImzaOnayla"
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
    Swal.fire({
      title: "E-İmza ile Onaya Sun",
      html: "Evrak önce kaydedilecek, ardından imzacıların onayına sunulacak.<br>İmza sırasında ilk sırada siz varsanız imzanız otomatik atılır.<br><b>Onaya sunulan evrakın içeriği, imza süreci tamamlanana kadar değiştirilemez.</b>",
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
                .then(() => location.href = "index?p=evrak-takip/giden-evrak&id=" + encodeURIComponent(kayit.id));
            } else {
              serbestBirak();
              Swal.fire("Evrak Kaydedildi, Onaya Sunulamadı", response.message, "warning")
                .then(() => location.href = "index?p=evrak-takip/giden-evrak&id=" + encodeURIComponent(kayit.id));
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

  if (window.gidenEvrakKilitli) {
    form.addClass("evrak-kilitli");
    form.find("input, select, textarea").not("input[type=hidden]").prop("disabled", true);
    $("#giden_evrak_icerik").summernote("disable");
    $("#btnIcraUstYaziAc, #btnAiTaslakAc").addClass("d-none");
    $("#ek_dosyalari").closest(".border.rounded-3").addClass("d-none");
    $("#imzaSiraContainer").find("button").prop("disabled", true);
  }

  $("#gidenPdfModal").on("hidden.bs.modal", clearPdf);
  renderAttachments();
  if (typeof feather !== "undefined") feather.replace();

  adjustGidenEvrakLayout();
  window.setTimeout(adjustGidenEvrakLayout, 100);
  window.setTimeout(adjustGidenEvrakLayout, 500);
  $(window).on("resize orientationchange", adjustGidenEvrakLayout);
  $("#gidenEvrakTabs button").on("shown.bs.tab", adjustGidenEvrakLayout);
});
