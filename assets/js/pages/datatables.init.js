
$(document).ready(function () {
  $(".datatable").DataTable({
    language: {
      url: "assets/js/tr.json"
    },
    layout: {
      // bottomStart: "pageLength",
      // bottom2Start: "info",
      topStart: null,
      topEnd: null
    },
     buttons: [
      {
        extend: "excelHtml5",
        className: "d-none", // Butonu gizliyoruz
        exportOptions: {
          columns: ":visible:not(.no-export)" // .no-export sınıfına sahip sütunları dışa aktarma
        }
      }
    ],
    initComplete: function (settings, json) {
      var api = this.api();
      var tableId = settings.sTableId;
      $("#" + tableId + " thead").append('<tr class="search-input-row"></tr>');

      api.columns().every(function () {
        let column = this;
        let title = column.header().textContent;

        if (
          title != "İşlem" &&
          title != "Seç" &&
          $(column.header()).find('input[type="checkbox"]').length === 0
        ) {
          // Create input element
          let input = document.createElement("input");
          input.placeholder = title;
          input.classList.add("form-control");
          input.classList.add("form-control-sm");
          input.setAttribute("autocomplete", "off");

          // Append input element to the new row
          $("#" + tableId + " .search-input-row").append(
            $('<th class="search">').append(input)
          );

          // Event listener for user input
          $(input).on("keyup change", function () {
            if (column.search() !== this.value) {
              column.search(this.value).draw();
            }
          });
        } else {
          // Eğer "İşlem" sütunuysa, boş bir th ekleyin
          $("#" + tableId + " .search-input-row").append("<th></th>");
        }
      });
    }
  });
});
