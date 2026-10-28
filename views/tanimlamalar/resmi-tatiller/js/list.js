$(document).ready(function () {
  const API_URL = "views/tanimlamalar/resmi-tatiller/api.php";
  const STATE_KEY = "resmitatil_summary_cards_state";
  const HIDDEN_CLASS = "resmitatil-summary-hidden";
  let currentStatus = "all";
  const $modal = $("#holidayModal");
  const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);

  const toggleBtn = $("#btnToggleSummaryCards");
  const updateToggleState = () => {
    const isHidden = $("html").hasClass(HIDDEN_CLASS);
    toggleBtn.attr("aria-expanded", !isHidden);
    const label = isHidden ? "Özet Kartları Göster" : "Özet Kartları Gizle";
    toggleBtn.attr({ title: label, "aria-label": label });
    toggleBtn.find("i").attr("class", isHidden ? "bx bx-chevron-down" : "bx bx-chevron-up");
  };
  updateToggleState();
  toggleBtn.on("click", function () {
    const willHide = !$("html").hasClass(HIDDEN_CLASS);
    $("html").toggleClass(HIDDEN_CLASS, willHide);
    try {
      localStorage.setItem(STATE_KEY, willHide ? "hidden" : "visible");
    } catch (e) {}
    updateToggleState();
  });

  $("#filter_yil").select2({ minimumResultsForSearch: Infinity, width: "100%" });
  $("#tur").select2({ dropdownParent: $modal, minimumResultsForSearch: Infinity, width: "100%" });
  if (typeof flatpickr === "function") {
    flatpickr("#tatil_tarihi", { dateFormat: "d.m.Y", locale: "tr", allowInput: true });
  }

  function renderSummary(s) {
    $("#stat_toplam").text(s.toplam || 0);
    $("#stat_sub_toplam").text(`${s.yil} yılı`);
    $("#stat_tam_gun").text(s.tam_gun || 0);
    $("#stat_sub_tam_gun").text("Çalışana RTÇ yazılır");
    $("#stat_yarim_gun").text(s.yarim_gun || 0);
    $("#stat_sub_yarim_gun").text("13:00 sonrası fazla mesai");
    $("#stat_siradaki").text(s.siradaki_tarih || "-");
    $("#stat_sub_siradaki").text(s.siradaki_ad || `${s.gelecek || 0} gelecek gün`);
  }

  const table = $("#resmiTatilTable").DataTable(
    applyLengthStateSave({
      ...getDatatableOptions(),
      processing: true,
      serverSide: true,
      ajax: {
        url: API_URL,
        type: "POST",
        data: function (d) {
          d.action = "liste";
          d.yil = $("#filter_yil").val();
          d.tur = currentStatus === "all" ? "" : currentStatus;
        },
        dataSrc: function (json) {
          if (json.summary) renderSummary(json.summary);
          return json.data || [];
        },
      },
      columns: [
        { data: "tarih" },
        { data: "gun" },
        { data: "aciklama" },
        { data: "tur", className: "text-center" },
        { data: "islem", className: "text-center" },
        { data: "kapsam", className: "text-center", orderable: false },
        { data: "actions", className: "text-center", orderable: false, searchable: false },
      ],
      order: [[0, "asc"]],
      createdRow: function (row) {
        $(row).find("td:not(:last-child)").attr("style", "cursor: pointer !important");
      },
    }),
  );

  $(".status-quick-filter").on("click", function () {
    currentStatus = $(this).data("status") || "all";
    $(".status-quick-filter").removeClass("active");
    $(this).addClass("active");
    table.ajax.reload();
  });
  $("#filter_yil").on("change", () => table.ajax.reload());
  $("#btnHeaderPrint").on("click", () => window.print());

  function syncTypeFields() {
    $modal.toggleClass("is-yarim", $("#tur").val() === "yarim");
  }
  $("#tur").on("change", syncTypeFields);

  function openForm(data) {
    const d = data || {};
    $("#holidayModalTitle").text(d.id ? "Tatil Gününü Düzenle" : "Yeni Tatil Günü");
    $("#holiday_id").val(d.id || "");
    const fp = document.getElementById("tatil_tarihi")._flatpickr;
    if (fp) fp.setDate(d.tatil_tarihi || "", false, "d.m.Y");
    else $("#tatil_tarihi").val(d.tatil_tarihi || "");
    $("#aciklama").val(d.aciklama || "");
    $("#tur").val(d.tur || "tam").trigger("change");
    $("#yarim_gun_saati").val(d.yarim_gun_saati || "13:00");
    $("#fazla_mesai_saat").val(d.fazla_mesai_saat || 4);
    $("#genel_tanim").prop("checked", !!d.genel).prop("disabled", !!d.id);
    syncTypeFields();
    modal.show();
  }

  function fail(xhr, fallback) {
    Swal.fire("Hata", (xhr.responseJSON && xhr.responseJSON.message) || fallback, "error");
  }

  $("#btnNewHoliday").on("click", () => openForm());

  $(document).on("click", ".btn-edit-holiday", function () {
    $.post(API_URL, { action: "getir", id: $(this).data("id") }, null, "json")
      .done((res) => (res.status === "success" ? openForm(res.data) : Swal.fire("Hata", res.message, "error")))
      .fail((xhr) => fail(xhr, "Kayıt getirilemedi."));
  });

  $("#resmiTatilTable tbody").on("click", "tr td:not(:last-child)", function () {
    $(this).closest("tr").find(".btn-edit-holiday").trigger("click");
  });

  $("#btnSaveHoliday").on("click", function () {
    const $btn = $(this).prop("disabled", true);
    const payload = $("#holidayForm").serializeArray();
    payload.push({ name: "action", value: "kaydet" });
    $.post(API_URL, $.param(payload), null, "json")
      .done((res) => {
        if (res.status !== "success") return Swal.fire("Hata", res.message, "error");
        modal.hide();
        table.ajax.reload(null, false);
        Swal.fire({ icon: "success", title: res.message, timer: 1500, showConfirmButton: false });
      })
      .fail((xhr) => fail(xhr, "Kayıt yapılamadı."))
      .always(() => $btn.prop("disabled", false));
  });

  const importModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("holidayImportModal"));

  $("#btnExcelTemplate, #btnExcelTemplateInline").on("click", function () {
    window.location.href = `${API_URL}?action=excel-sablon&yil=${encodeURIComponent($("#filter_yil").val() || "")}`;
  });

  $("#btnExcelImport").on("click", function () {
    $("#holidayImportForm")[0].reset();
    $("#holidayImportErrors").addClass("d-none").empty();
    importModal.show();
  });

  $("#btnHolidayImport").on("click", function () {
    const fileInput = document.getElementById("holidayExcelFile");
    if (!fileInput.files.length) {
      Swal.fire("Uyarı", "Lütfen bir Excel dosyası seçiniz.", "warning");
      return;
    }
    const formData = new FormData(document.getElementById("holidayImportForm"));
    formData.append("action", "excel-yukle");
    const $btn = $(this).prop("disabled", true);
    const $errors = $("#holidayImportErrors").addClass("d-none").empty();
    const showErrors = (errors) => {
      if (!errors || !errors.length) return;
      $errors.removeClass("d-none").append($("<div>").addClass("fw-semibold mb-1").text("Atlanan satırlar:"));
      errors.forEach((e) => $errors.append($("<div>").text(e)));
    };
    $.ajax({ url: API_URL, type: "POST", data: formData, processData: false, contentType: false, dataType: "json" })
      .done((res) => {
        table.ajax.reload(null, false);
        showErrors(res.errors);
        if (!res.errors || !res.errors.length) importModal.hide();
        Swal.fire({ icon: "success", title: res.message, timer: 2200, showConfirmButton: false });
      })
      .fail((xhr) => {
        showErrors(xhr.responseJSON && xhr.responseJSON.errors);
        fail(xhr, "Excel yüklenemedi.");
      })
      .always(() => $btn.prop("disabled", false));
  });

  $(document).on("click", ".btn-delete-holiday", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "Tatil günü silinsin mi?",
      text: "Otomatik RTÇ / fazla mesai bu gün için artık üretilmez; mevcut puantaj kayıtları değişmez.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sil",
      cancelButtonText: "Vazgeç",
      confirmButtonColor: "#f46a6a",
    }).then((r) => {
      if (!r.isConfirmed) return;
      $.post(API_URL, { action: "sil", id }, null, "json")
        .done((res) => {
          if (res.status !== "success") return Swal.fire("Hata", res.message, "error");
          table.ajax.reload(null, false);
        })
        .fail((xhr) => fail(xhr, "Silme işlemi yapılamadı."));
    });
  });
});
