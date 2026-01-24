// Türkçe karakter normalizasyonu için yardımcı fonksiyon
function turkishNormalize(str) {
  if (!str) return "";

  // Türkçe karakterleri dönüştür (büyük -> küçük)
  var turkishMap = {
    İ: "i",
    I: "ı",
    Ş: "ş",
    Ğ: "ğ",
    Ü: "ü",
    Ö: "ö",
    Ç: "ç",
  };

  // Önce Türkçe karakterleri dönüştür
  str = str.toString();
  for (var key in turkishMap) {
    str = str.replace(new RegExp(key, "g"), turkishMap[key]);
  }

  // Sonra standart toLowerCase uygula
  return str.toLowerCase();
}

// DataTables için özel Türkçe arama fonksiyonu
// $.fn.dataTable.ext.search.push(
//   function (settings, data, dataIndex, rowData, counter) {
//     var table = $(settings.nTable);
//     var api = table.DataTable();

//     // Her sütun için filtreleri kontrol et
//     var allColumnsPass = true;

//     api.columns().every(function (colIdx) {
//       var column = this;
//       var searchValue = column.search();

//       if (searchValue && searchValue.length > 0) {
//         var cellData = data[colIdx] || "";
//         var normalizedCellData = turkishNormalize(cellData);
//         var normalizedSearchValue = turkishNormalize(searchValue);

//         if (normalizedCellData.indexOf(normalizedSearchValue) === -1) {
//           allColumnsPass = false;
//           return false; // break
//         }
//       }
//     });

//     return allColumnsPass;
//   },
// );

$(document).ready(function () {
  var table = $(".datatable").DataTable({
    stateSave: true,
    responsive: false,
    pageLength: 25,
    language: {
      url: "assets/js/tr.json",
    },
    dom: "ltp",
    buttons: ["excel"],

    ...getTableSpecificOptions(),

    initComplete: function (settings, json) {
      var api = this.api();
      var tableId = settings.sTableId;
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
  });
});
$("#exportExcel").on("click", function () {
  alert("Excel'e Aktarılıyor...");
  table.button(".buttons-excel").trigger();
});

function getTableSpecificOptions() {
  return {
    ordering: document.getElementById("gelirGiderTable") ? false : true,
  };
}
