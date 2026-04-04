$(function () {
  const apiUrl = "views/demirbas/api.php";

  const sayacTable = $("#sayacTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "demirbas-listesi";
        d.tab = "sayac";
      },
    },
    columns: [
      { data: "checkbox", className: "text-center", orderable: false, searchable: false },
      { data: "id", className: "text-center", orderable: false, searchable: false },
      { data: "demirbas_no", className: "text-center" },
      { data: "demirbas_adi" },
      { data: "marka_sade" },
      { data: "seri_no" },
      { data: "stok", className: "text-center", orderable: false, searchable: false },
      { data: "durum", className: "text-center" },
      { data: "tarih", orderable: false, searchable: false },
      { data: "islemler", className: "text-center", orderable: false, searchable: false },
    ],
    order: [[1, "desc"]],
    initComplete: function () {
      $("#personel-loader").fadeOut(300);
    },
  });

  const sayacZimmetTable = $("#sayacZimmetTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "zimmet-listesi";
        d.filter_type = "sayac";
        d.personel_id = "all";
        d.sayac_kat_ids = typeof sayacKatIds !== "undefined" ? sayacKatIds : [];
        d.aparat_kat_ids = [];
      },
    },
    columns: [
      { data: "checkbox", className: "text-center", orderable: false, searchable: false },
      { data: "id", className: "text-center" },
      { data: "kategori_adi" },
      { data: "demirbas_adi" },
      { data: "marka_model" },
      { data: "personel_adi" },
      { data: "teslim_miktar", className: "text-center" },
      { data: "teslim_tarihi" },
      { data: "durum", className: "text-center" },
      { data: "islemler", className: "text-center", orderable: false },
    ],
    order: [[1, "desc"]],
    initComplete: function () {
      // Hareketler tabı yüklendiğinde de preloader'dan emin olalım
      $("#personel-loader").fadeOut(300);
    },
  });

  $('button[data-bs-target="#sayacHareketPane"]').on("shown.bs.tab", function () {
    sayacZimmetTable.columns.adjust().draw(false);
  });

  $('button[data-bs-target="#sayaclarPane"]').on("shown.bs.tab", function () {
    sayacTable.columns.adjust().draw(false);
  });
});
