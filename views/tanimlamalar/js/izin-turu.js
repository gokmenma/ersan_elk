const url = "views/tanimlamalar/api.php";
const SUMMARY_KEY = "izinturu_summary_cards_state";
const DEFAULT_RENK = "bg-primary/10 text-primary";
const DEFAULT_IKON = "event";

$(document).ready(function () {
  let statusFilter = "all";

  $("#renk, #ikon").select2({
    width: "100%",
    dropdownParent: $("#actionModal"),
  });

  const dtTable = $("#izinTuruTable").DataTable(
    applyLengthStateSave({
      ...getDatatableOptions(),
      processing: true,
      serverSide: true,
      ajax: {
        url: url,
        type: "POST",
        data: function (d) {
          d.action = "izin-turu-liste";
          d.status_filter = statusFilter;
        },
      },
      columns: [
        { data: "sira", orderable: false, searchable: false },
        { data: "tur_adi" },
        { data: "kisa_kod" },
        { data: "gorunum", orderable: false, searchable: false },
        { data: "ucretli_mi" },
        { data: "normal_mesai_sayilir" },
        { data: "personel_gorebilir" },
        { data: "yetkili_onayina_tabi" },
        { data: "aciklama" },
        { data: "actions", orderable: false, searchable: false },
      ],
      order: [[1, "asc"]],
      pageLength: 25,
    }),
  );

  const $container = $("#summaryCardsContainer");
  const $toggle = $("#btnToggleSummaryCards");

  function setSummaryVisible(visible) {
    document.documentElement.classList.toggle("izinturu-summary-hidden", !visible);
    try {
      localStorage.setItem(SUMMARY_KEY, visible ? "visible" : "hidden");
    } catch (e) {}
    $toggle
      .attr("aria-expanded", visible ? "true" : "false")
      .attr("aria-label", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .attr("title", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .html(visible ? '<i class="bx bx-chevron-up"></i>' : '<i class="bx bx-chevron-down"></i>');
  }

  if ($container.length && $toggle.length) {
    const hidden = document.documentElement.classList.contains("izinturu-summary-hidden");
    $toggle.attr("aria-expanded", hidden ? "false" : "true");
    $toggle.html(hidden ? '<i class="bx bx-chevron-down"></i>' : '<i class="bx bx-chevron-up"></i>');
    $toggle.on("click", function () {
      const isHidden = document.documentElement.classList.contains("izinturu-summary-hidden");
      setSummaryVisible(isHidden);
    });
  }

  function refreshSummary() {
    $.post(url, { action: "izin-turu-ozet" }, function (res) {
      if (res && res.status === "success" && res.summary) {
        const s = res.summary;
        $("#kpi_toplam").text(s.toplam);
        $("#kpi_sub_toplam").text("Ücretli: " + s.ucretli + " | Ücretsiz: " + s.ucretsiz);
        $("#kpi_ucretli").text(s.ucretli);
        $("#kpi_ucretsiz").text(s.ucretsiz);
        $("#kpi_onayli").text(s.onayli);
        $("#kpi_sub_personel").text("Personele açık: " + s.personel_gorebilir);
      }
    }, "json");
  }

  $(".status-quick-filter").on("click", function () {
    $(".status-quick-filter").removeClass("active");
    $(this).addClass("active");
    statusFilter = $(this).data("status");
    dtTable.ajax.reload();
  });

  $("#btnHeaderPrint").on("click", function () {
    window.print();
  });

  function resetForm() {
    $("#actionForm")[0].reset();
    $("#izin_turu_id").val(0);
    $("#ucretli_mi").prop("checked", true);
    $("#normal_mesai_sayilir").prop("checked", false);
    $("#resmi_tatil_calismasi").prop("checked", false);
    $("#hafta_tatili_calismasi").prop("checked", false);
    $("#personel_gorebilir").prop("checked", true);
    $("#yetkili_onayina_tabi").prop("checked", false);
    $("#renk").val(DEFAULT_RENK).trigger("change");
    $("#ikon").val(DEFAULT_IKON).trigger("change");
    $("#actionForm").find(".is-invalid").removeClass("is-invalid");
  }

  $(document).on("click", "#actionEkle", function () {
    resetForm();
    $("#actionModalLabel").html('<i class="bx bx-calendar-check me-2"></i>İzin Türü Ekle');
  });

  function openEdit(id) {
    const formData = new FormData();
    formData.append("action", "izin-turu-getir");
    formData.append("id", id);

    fetch(url, { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.status !== "success") {
          Swal.fire("Hata", data.message, "error");
          return;
        }
        const d = data.data;
        resetForm();
        $("#izin_turu_id").val(id);
        $("#izin_turu").val(d.tur_adi);
        $("#kisa_kod").val(d.kisa_kod);
        $("#aciklama").val(d.aciklama);
        $("#ucretli_mi").prop("checked", d.ucretli_mi == 1);
        $("#normal_mesai_sayilir").prop("checked", d.normal_mesai_sayilir == 1);
        $("#resmi_tatil_calismasi").prop("checked", d.resmi_tatil_calismasi == 1);
        $("#hafta_tatili_calismasi").prop("checked", d.hafta_tatili_calismasi == 1);
        $("#personel_gorebilir").prop("checked", d.personel_gorebilir == 1);
        $("#yetkili_onayina_tabi").prop("checked", d.yetkili_onayina_tabi == 1);
        $("#renk").val(d.renk || DEFAULT_RENK).trigger("change");
        $("#ikon").val(d.ikon || DEFAULT_IKON).trigger("change");
        $("#actionModalLabel").html('<i class="bx bx-edit me-2"></i>İzin Türü Düzenle');
        $("#actionModal").modal("show");
      });
  }

  $(document).on("click", ".duzenle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    openEdit($(this).data("id"));
  });

  $("#izinTuruTable tbody").on("click", "tr", function (e) {
    if ($(e.target).closest("td:last-child, a, button").length) {
      return;
    }
    const id = $(this).find(".duzenle").data("id");
    if (id) {
      openEdit(id);
    }
  });

  $(document).on("click", "#actionKaydet", function () {
    const form = $("#actionForm");

    form.validate({
      rules: { izin_turu: { required: true }, kisa_kod: { required: true } },
      messages: {
        izin_turu: { required: "İzin Türü boş bırakılamaz" },
        kisa_kod: { required: "Kısa Kod boş bırakılamaz" },
      },
      errorElement: "span",
      highlight: function (element) {
        $(element).addClass("is-invalid");
      },
      unhighlight: function (element) {
        $(element).removeClass("is-invalid");
      },
    });

    if (!form.valid()) {
      return;
    }

    const formData = new FormData(form[0]);
    formData.append("action", "izin-turu-kaydet");
    ["ucretli_mi", "normal_mesai_sayilir", "resmi_tatil_calismasi", "hafta_tatili_calismasi", "personel_gorebilir", "yetkili_onayina_tabi"].forEach(function (name) {
      formData.set(name, $("#" + name).is(":checked") ? 1 : 0);
    });

    const $btn = $(this).prop("disabled", true);

    fetch(url, { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        $btn.prop("disabled", false);
        if (data.status === "success") {
          $("#actionModal").modal("hide");
          Swal.fire({ icon: "success", title: "Başarılı", text: data.message, timer: 1500, showConfirmButton: false });
          dtTable.ajax.reload(null, false);
          refreshSummary();
        } else {
          Swal.fire({ icon: "error", title: "Hata", text: data.message });
        }
      })
      .catch(function () {
        $btn.prop("disabled", false);
        Swal.fire({ icon: "error", title: "Hata", text: "Sunucu ile iletişim kurulamadı." });
      });
  });

  $(document).on("click", ".sil", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const id = $(this).data("id");
    const name = $(this).data("name") || "Bu izin türü";

    Swal.fire({
      title: "Emin misiniz?",
      text: '"' + name + '" izin türünü silmek istediğinize emin misiniz?',
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#ea5455",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      const formData = new FormData();
      formData.append("action", "izin-turu-sil");
      formData.append("id", id);

      fetch(url, { method: "POST", body: formData })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            Swal.fire({ icon: "success", title: "Silindi", text: data.message, timer: 1500, showConfirmButton: false });
            dtTable.ajax.reload(null, false);
            refreshSummary();
          } else {
            Swal.fire("Hata", data.message, "error");
          }
        });
    });
  });
});
