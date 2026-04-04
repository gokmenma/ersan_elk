$(function () {
  const apiUrl = "views/demirbas/api.php";

  function setText(id, value) {
    $(id).text(parseInt(value || 0, 10));
  }

  function loadGlobalSummary() {
    $.post(apiUrl, { action: "aparat-global-summary" }, function (res) {
      if (res && res.status === "success") {
        setText("#apCardDepo", res.depoda);
        setText("#apCardPersonel", res.personelde);
        setText("#apCardTuketilen", res.tuketilen);
        setText("#apCardCesit", res.toplam_cesit);
      }
    }, "json");
  }

  const personelTable = $("#aparatPersonelTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "aparat-personel-list";
      },
    },
    columns: [
      { data: "sira", className: "text-center" },
      { data: "personel_adi" },
      { data: "toplam_verilen", className: "text-center" },
      { data: "toplam_tuketilen", className: "text-center" },
      { data: "toplam_depo_iade", className: "text-center" },
      { data: "toplam_kayip", className: "text-center" },
      { data: "kalan_miktar", className: "text-center fw-bold text-success" },
    ],
    order: [[1, "asc"]],
    createdRow: function (row, data) {
      $(row).attr("data-personel-id", data.personel_id);
      $(row).css("cursor", "pointer");
    },
  });

  const hareketTable = $("#aparatHareketTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: apiUrl,
      type: "POST",
      data: function (d) {
        d.action = "aparat-hareketler-list";
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
      { action: "aparat-personel-summary", personel_id: personelId },
      function (res) {
        if (!res || res.status !== "success") return;
        const s = res.summary || {};
        $("#aparatSeciliPersonel").text(personelAdi + " - Detay");
        setText("#ap_verilen", s.toplam_verilen);
        setText("#ap_tuketilen", s.toplam_tuketilen);
        setText("#ap_depo_iade", s.toplam_depo_iade);
        setText("#ap_kayip", s.toplam_kayip);
        setText("#ap_kalan", s.kalan_miktar);
        $("#aparatPersonelDetailCard").show();
      },
      "json",
    );
  }

  function loadPersonelHistory(personelId) {
    $.post(
      apiUrl,
      { action: "aparat-personel-history", personel_id: personelId },
      function (res) {
        const $tbody = $("#aparatPersonelHistoryTable tbody");
        $tbody.empty();
        if (!res || res.status !== "success") return;
        (res.rows || []).forEach(function (r) {
          $tbody.append(`
            <tr>
              <td>${r.gun_format || "-"}</td>
              <td class="text-center">${parseInt(r.verilen || 0, 10)}</td>
              <td class="text-center">${parseInt(r.tuketilen || 0, 10)}</td>
              <td class="text-center">${parseInt(r.depo_iade || 0, 10)}</td>
              <td class="text-center">${parseInt(r.kayip || 0, 10)}</td>
              <td class="text-center fw-bold">${parseInt(r.net || 0, 10)}</td>
            </tr>
          `);
        });
      },
      "json",
    );
  }

  $(document).on("click", "#aparatPersonelTable tbody tr", function () {
    const row = personelTable.row(this).data();
    if (!row) return;
    const personelId = parseInt(row.personel_id || 0, 10);
    if (personelId <= 0) return;
    loadPersonelSummary(personelId, row.personel_adi || "Personel");
    loadPersonelHistory(personelId);
  });

  $('button[data-bs-target="#aparatHareketPane"]').on("shown.bs.tab", function () {
    hareketTable.columns.adjust().draw(false);
  });

  loadGlobalSummary();
});
