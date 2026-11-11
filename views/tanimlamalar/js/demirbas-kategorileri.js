const url = "views/tanimlamalar/api.php";
const SUMMARY_KEY = "dmbkat_summary_cards_state";

$(document).ready(function () {
  let statusFilter = "all";

  const dtTable = $("#kategoriTable").DataTable(
    applyLengthStateSave({
      ...getDatatableOptions(),
      processing: true,
      serverSide: true,
      ajax: {
        url: url,
        type: "POST",
        data: function (d) {
          d.action = "demirbas-kategorisi-liste";
          d.status_filter = statusFilter;
        },
      },
      columns: [
        { data: "sira", orderable: false, searchable: false },
        { data: "tur_adi" },
        { data: "aciklama" },
        { data: "adet", searchable: false },
        { data: "kayit_tarihi", searchable: false },
        { data: "actions", orderable: false, searchable: false },
      ],
      order: [[1, "asc"]],
      pageLength: 25,
    }),
  );

  const $toggle = $("#btnToggleSummaryCards");

  function setSummaryVisible(visible) {
    document.documentElement.classList.toggle("dmbkat-summary-hidden", !visible);
    try {
      localStorage.setItem(SUMMARY_KEY, visible ? "visible" : "hidden");
    } catch (e) {}
    $toggle
      .attr("aria-expanded", visible ? "true" : "false")
      .attr("aria-label", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .attr("title", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
      .html(visible ? '<i class="bx bx-chevron-up"></i>' : '<i class="bx bx-chevron-down"></i>');
  }

  if ($toggle.length) {
    const hidden = document.documentElement.classList.contains("dmbkat-summary-hidden");
    $toggle.attr("aria-expanded", hidden ? "false" : "true");
    $toggle.html(hidden ? '<i class="bx bx-chevron-down"></i>' : '<i class="bx bx-chevron-up"></i>');
    $toggle.on("click", function () {
      setSummaryVisible(document.documentElement.classList.contains("dmbkat-summary-hidden"));
    });
  }

  function refreshSummary() {
    $.post(url, { action: "demirbas-kategorisi-ozet" }, function (res) {
      if (res && res.status === "success" && res.summary) {
        const s = res.summary;
        $("#kpi_toplam").text(s.toplam);
        $("#kpi_sub_toplam").text("Kullanımda: " + s.kullanimda + " | Boş: " + s.bos);
        $("#kpi_kullanimda").text(s.kullanimda);
        $("#kpi_bos").text(s.bos);
        $("#kpi_demirbas").text(s.toplam_demirbas);
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
    $("#kategori_id").val(0);
    $("#actionForm").find(".is-invalid").removeClass("is-invalid");
  }

  $(document).on("click", "#actionEkle", function () {
    resetForm();
    $("#actionModalLabel").html('<i class="bx bx-list-ul me-2"></i>Kategori Ekle');
  });

  function openEdit(id) {
    const formData = new FormData();
    formData.append("action", "demirbas-kategorisi-getir");
    formData.append("id", id);

    fetch(url, { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.status !== "success") {
          Swal.fire("Hata", data.message, "error");
          return;
        }
        resetForm();
        $("#kategori_id").val(id);
        $("#kategori_adi").val(data.data.tur_adi);
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").html('<i class="bx bx-edit me-2"></i>Kategori Düzenle');
        $("#actionModal").modal("show");
      });
  }

  $(document).on("click", ".duzenle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    openEdit($(this).data("id"));
  });

  $("#kategoriTable tbody").on("click", "tr", function (e) {
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
      rules: { kategori_adi: { required: true } },
      messages: { kategori_adi: { required: "Kategori adı boş bırakılamaz" } },
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
    formData.append("action", "demirbas-kategorisi-kaydet");
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
    const name = $(this).data("name") || "Bu kategori";

    Swal.fire({
      title: "Emin misiniz?",
      text: '"' + name + '" kategorisini silmek istediğinize emin misiniz?',
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
      formData.append("action", "demirbas-kategorisi-sil");
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
