$(function () {
  // Servis listesini yenile
  $(document).on("click", "#btnServisListele", function () {
    if (typeof servisTable !== "undefined") {
      servisTable.draw();
    }
  });

  // Excel'e aktar
  $(document).on("click", "#btnExportExcelServis", function () {
    if (typeof servisTable !== "undefined") {
      servisTable.button(".buttons-excel").trigger();
    }
  });
});
