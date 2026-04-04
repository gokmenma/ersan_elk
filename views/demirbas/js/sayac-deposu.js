$(function () {
  const apiUrl = "views/demirbas/api.php";
  let selectedPersonelId = 0;

  function setText(id, value) {
    $(id).text(parseInt(value || 0, 10));
  }

  function loadGlobalSummary() {
    $.post(apiUrl, { action: "sayac-global-summary" }, function (res) {
      if (res && res.status === "success") {
        setText("#sayacCardYeniDepo", res.yeni_depoda);
        setText("#sayacCardHurdaDepo", res.hurda_depoda);
        setText("#sayacCardYeniPersonel", res.yeni_personelde);
        setText("#sayacCardHurdaPersonel", res.hurda_personelde);
      }
    }, "json");
  }

  const personelTable = $("#sayacPersonelTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "sayac-personel-list";
      },
    },
    columns: [
      { data: "sira", className: "text-center" },
      { data: "personel_adi" },
      { data: "bizden_toplam_aldigi", className: "text-center" },
      { data: "toplam_taktigi", className: "text-center" },
      { data: "elinde_kalan_yeni", className: "text-center fw-bold text-success" },
      { data: "toplam_hurda", className: "text-center" },
      { data: "teslim_edilen_hurda", className: "text-center" },
      { data: "elinde_kalan_hurda", className: "text-center fw-bold text-warning" },
    ],
    order: [[1, "asc"]],
    createdRow: function (row, data) {
      $(row).attr("data-personel-id", data.personel_id);
      $(row).css("cursor", "pointer");
    },
  });

  const hareketTable = $("#sayacHareketTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "sayac-hareketler-list";
      },
    },
    columns: [
      { data: "tarih" },
      { data: "personel" },
      { data: "demirbas" },
      { data: "tip", orderable: false, searchable: false },
      { data: "miktar", className: "text-center" },
      { data: "aciklama" },
    ],
    order: [[0, "desc"]],
  });

  function loadPersonelSummary(personelId, personelAdi) {
    $.post(
      apiUrl,
      { action: "sayac-personel-summary", personel_id: personelId },
      function (res) {
        if (!res || res.status !== "success") return;
        const s = res.summary || {};
        $("#sayacSeciliPersonel").text(personelAdi + " - Detay");
        setText("#sp_aldigi", s.bizden_toplam_aldigi);
        setText("#sp_taktigi", s.toplam_taktigi);
        setText("#sp_kalan_yeni", s.elinde_kalan_yeni);
        setText("#sp_toplam_hurda", s.toplam_hurda);
        setText("#sp_teslim_hurda", s.teslim_edilen_hurda);
        setText("#sp_kalan_hurda", s.elinde_kalan_hurda);
        $("#sayacPersonelDetailCard").show();
      },
      "json",
    );
  }

  function loadPersonelHistory(personelId) {
    $.post(
      apiUrl,
      { action: "sayac-personel-history", personel_id: personelId },
      function (res) {
        const $tbody = $("#sayacPersonelHistoryTable tbody");
        $tbody.empty();
        if (!res || res.status !== "success") return;
        (res.rows || []).forEach(function (r) {
          $tbody.append(`
            <tr>
              <td>${r.gun_format || "-"}</td>
              <td class="text-center">${parseInt(r.alinan || 0, 10)}</td>
              <td class="text-center">${parseInt(r.taktigi || 0, 10)}</td>
              <td class="text-center">${parseInt(r.hurda_alinan || 0, 10)}</td>
              <td class="text-center">${parseInt(r.hurda_teslim || 0, 10)}</td>
              <td class="text-center">${parseInt(r.kayip || 0, 10)}</td>
              <td class="text-center fw-bold">${parseInt(r.net || 0, 10)}</td>
            </tr>
          `);
        });
      },
      "json",
    );
  }

  $(document).on("click", "#sayacPersonelTable tbody tr", function () {
    const row = personelTable.row(this).data();
    if (!row) return;
    selectedPersonelId = parseInt(row.personel_id || 0, 10);
    if (selectedPersonelId <= 0) return;
    loadPersonelSummary(selectedPersonelId, row.personel_adi || "Personel");
    loadPersonelHistory(selectedPersonelId);
  });

  $('button[data-bs-target="#sayacHareketPane"]').on("shown.bs.tab", function () {
    hareketTable.columns.adjust().draw(false);
  });

  loadGlobalSummary();
});
