$(document).ready(function () {
  function syncSummaryToggle() {
    var hidden = document.documentElement.classList.contains("permission-catalog-summary-hidden");
    $("#btnToggleSummaryCards")
      .attr("title", hidden ? "Özet kartlarını göster" : "Özet kartlarını gizle")
      .attr("aria-label", hidden ? "Özet kartlarını göster" : "Özet kartlarını gizle")
      .attr("aria-expanded", hidden ? "false" : "true")
      .find("i").attr("class", hidden ? "bx bx-chevron-down" : "bx bx-chevron-up");
  }

  syncSummaryToggle();
  $("#btnToggleSummaryCards").on("click", function () {
    var hidden = !document.documentElement.classList.contains("permission-catalog-summary-hidden");
    document.documentElement.classList.toggle("permission-catalog-summary-hidden", hidden);
    try { localStorage.setItem("permission_catalog_summary_cards_state", hidden ? "hidden" : "visible"); } catch (e) {}
    syncSummaryToggle();
  });

  if (!$.fn.DataTable || typeof getDatatableOptions !== "function" || typeof applyLengthStateSave !== "function") return;
  var table = $("#permissionCatalogTable").DataTable(applyLengthStateSave({
    ...getDatatableOptions(),
    pageLength: 25,
    order: [[1, "asc"], [2, "asc"]]
  }));

  $(".catalog-filter").on("click", function () {
    $(".catalog-filter").removeClass("active");
    $(this).addClass("active");
    table.column(5).search($(this).data("status") || "").draw();
    document.getElementById("permissionCatalogListCard")?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});
