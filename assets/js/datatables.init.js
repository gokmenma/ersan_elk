let table;
$(document).ready(function () {
  table = $(".datatable").DataTable(getDatatableOptions());
});

function getDatatableOptions() {
  return {
    stateSave: false,
    responsive: false,
    pageLength: 10,
    dom: 't<"row"<"col-sm-12 col-md-6 d-flex align-items-center justify-content-start"i<"ms-3 text-nowrap"l>><"col-sm-12 col-md-6 d-flex justify-content-end"p>>',
    language: {
      url: "assets/js/tr.json",
    },
    buttons: ["excel"],

    ...getTableSpecificOptions(),

    initComplete: function (settings, json) {
      var api = this.api();
      var tableId = settings.sTableId;
      var tableId = settings.sTableId;
      $("#" + tableId + " thead").append('<tr class="search-input-row"></tr>');

      // PageLength select kutusunun düzgün görünmesi için
      $(settings.nTableWrapper)
        .find(".dataTables_length label")
        .addClass("d-flex align-items-center");
      $(settings.nTableWrapper)
        .find(".dataTables_length select")
        .addClass("mx-2");

      api.columns().every(function () {
        let column = this;
        let title = column.header().textContent;

        if (
          title != "İşlem" &&
          title != "Seç" &&
          title != "#" &&
          $(column.header()).find('input[type="checkbox"]').length === 0
        ) {
          // Create input element
          let input = document.createElement("input");
          input.placeholder = title;
          input.classList.add("form-control");
          input.classList.add("form-control-sm");
          input.setAttribute("autocomplete", "off");

          // // Append input element to the new row
          // $("#" + tableId + " .search-input-row").append(
          //   $('<th class="search">').append(input)
          // );

          // Append input element to the new row
          const th = $('<th class="search">').append(input);
          $("#" + tableId + " .search-input-row").append(th);

          // Event listener for user input
          $(input).on("keyup change", function () {
            if (column.search() !== this.value) {
              column.search(this.value).draw();
            }
          });

          // Sütunun gerçekten görünür olup olmadığını kontrol et
          const isColumnVisible =
            column.visible() && !$(column.header()).hasClass("dtr-hidden");

          //  const isColumnVisible =
          //  column.visible() && $(column.header()).css("display") !== "none";

          if (!isColumnVisible) {
            th.hide(); // Sütun gerçekten görünmüyorsa input'u da gizle
          }
        } else {
          // Eğer "İşlem" sütunuysa, boş bir th ekleyin

          // Sütun görünürse <th> elemanını ekle
          $("#" + tableId + " .search-input-row").append("<th></th>");
        }
      });

      // Responsive olayını dinle
      table.on("responsive-resize", function (e, datatable, columns) {
        // Sütun görünürlüğünü kontrol et ve inputları gizle/göster
        $("#" + tableId + " .search-input-row th").each(function (index) {
          if (columns[index]) {
            $(this).show(); // Sütun görünüyorsa inputu göster
          } else {
            $(this).hide(); // Sütun gizliyse inputu gizle
          }
        });
      });

      var state = table.state.loaded();
      if (state) {
        $("input", table.table().header()).each(function (index) {
          var searchValue = state.columns[index].search.search;
          if (searchValue) {
            $(this).val(searchValue);
          }
        });
      }
    },
  };
}



$("#exportExcel").on("click", function () {
  table.button(".buttons-excel").trigger();
});

function getTableSpecificOptions() {
  return {
    ordering: document.getElementById("gelirGiderTable") ? false : true,
  };
}
