$(document).ready(function () {
  initSozlesmelerTable();

  $("#yeniSozlesmeForm").on("submit", function (e) {
    e.preventDefault();
    saveSozlesme(this);
  });
});

let sozlesmeTable;

function initSozlesmelerTable() {
  let options =
    typeof getDatatableOptions === "function"
      ? getDatatableOptions()
      : {
          language: {
            url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json",
          },
          processing: true,
          serverSide: true,
        };

  options.processing = true;
  options.serverSide = true;
  options.ajax = {
    url: "views/hakedisler/online-api.php?type=getSozlesmeler",
    type: "POST",
  };
  ((options.columns = [
    { data: "idare_adi" },
    {
      data: "isin_adi",
      render: function (data, type, row) {
        return data.length > 50 ? data.substr(0, 50) + "..." : data;
      },
    },
    {
      data: "sozlesme_tarihi",
      render: function (data) {
        return data ? moment(data).format("DD.MM.YYYY") : "-";
      },
    },
    {
      data: "isin_bitecegi_tarih",
      render: function (data) {
        return data ? moment(data).format("DD.MM.YYYY") : "-";
      },
    },
    {
      data: "sozlesme_bedeli",
      render: function (data) {
        return data
          ? parseFloat(data).toLocaleString("tr-TR", {
              style: "currency",
              currency: "TRY",
            })
          : "-";
      },
    },
    {
      data: "durum",
      render: function (data) {
        let badge = "bg-primary";
        if (data == "tamamlandi") badge = "bg-success";
        if (data == "pasif") badge = "bg-danger";
        return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
      },
    },
    {
      data: "id",
      orderable: false,
      render: function (data) {
        return `
                        <div class="d-flex gap-2">
                            <a href="?p=hakedisler/sozlesme-detay&id=${data}" class="btn btn-sm btn-info" title="Detaya Git">
                                <i class="bx bx-file-find"></i> Detay/Hakedişler
                            </a>
                            <button class="btn btn-sm btn-warning" onclick="editSozlesme(${data})" title="Düzenle">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSozlesme(${data})" title="Sil">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    `;
      },
    },
  ]),
    (options.order = [[2, "desc"]]));
  sozlesmeTable = $("#sozlesmeTable").DataTable(options);
}

function saveSozlesme(form) {
  const formData = $(form).serializeArray();
  formData.push({ name: "type", value: "saveSozlesme" });

  Swal.fire({
    title: "Kaydediliyor...",
    allowEscapeKey: false,
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.post(
    "views/hakedisler/online-api.php",
    formData,
    function (response) {
      if (response.status === "success") {
        Swal.fire("Başarılı!", "Sözleşme kaydedildi.", "success");
        $("#yeniSozlesmeModal").modal("hide");
        form.reset();
        sozlesmeTable.ajax.reload();
      } else {
        Swal.fire("Hata!", response.message || "Bir hata oluştu.", "error");
      }
    },
    "json",
  ).fail(function () {
    Swal.fire("Hata!", "Sunucu bağlantısında sorun oluştu.", "error");
  });
}

function deleteSozlesme(id) {
  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu sözleşmeyi ve ilişkili tüm hakediş/kalem verilerini silmek üzeresiniz. Bu işlem geri alınamaz!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "views/hakedisler/online-api.php",
        { type: "deleteSozlesme", id: id },
        function (res) {
          if (res.status == "success") {
            sozlesmeTable.ajax.reload();
            Swal.fire("Silindi!", "Sözleşme başarıyla silindi.", "success");
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
        "json",
      );
    }
  });
}

function editSozlesme(id) {
  // Edit action could open the modal and populate the form via ajax
  Swal.fire("Bilgi", "Düzenleme fonksiyonu hazırlanıyor...", "info");
}
