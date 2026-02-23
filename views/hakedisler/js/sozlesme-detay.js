$(document).ready(function () {
  initHakedisTable();

  $("#yeniHakedisForm").on("submit", function (e) {
    e.preventDefault();
    saveHakedis(this);
  });
});

let hakedisTable;

function initHakedisTable() {
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
    url: "views/hakedisler/online-api.php?type=getHakedisler",
    type: "POST",
    data: function (d) {
      d.sozlesme_id = currentSozlesmeId;
    },
  };
  ((options.columns = [
    {
      data: "hakedis_no",
      render: function (data) {
        return `<strong>#${data}</strong>`;
      },
    },
    {
      data: null,
      render: function (data, type, row) {
        const aylar = {
          1: "Ocak",
          2: "Şubat",
          3: "Mart",
          4: "Nisan",
          5: "Mayıs",
          6: "Haziran",
          7: "Temmuz",
          8: "Ağustos",
          9: "Eylül",
          10: "Ekim",
          11: "Kasım",
          12: "Aralık",
        };
        return `${aylar[row.hakedis_tarihi_ay]} ${row.hakedis_tarihi_yil}`;
      },
    },
    {
      data: null,
      render: function (data, type, row) {
        return `<span class="badge bg-info">${row.temel_endeks_ayi}</span> <i class="bx bx-right-arrow-alt"></i> <span class="badge bg-warning">${row.guncel_endeks_ayi}</span>`;
      },
    },
    {
      data: "durum",
      render: function (data) {
        let badge = "bg-secondary";
        if (data == "onaylandi") badge = "bg-success";
        return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
      },
    },
    {
      data: "id",
      orderable: false,
      render: function (data) {
        return `
                        <div class="d-flex gap-2">
                            <a href="?p=hakedisler/hakedis-detay&id=${data}" class="btn btn-sm btn-primary" title="Miktarlar ve Fiyat Farkı">
                                <i class="bx bx-list-ol"></i> İçerik
                            </a>
                            <button class="btn btn-sm btn-warning" onclick="editHakedis(${data})" title="Düzenle">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteHakedis(${data})" title="Sil">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    `;
      },
    },
  ]),
    (options.order = [[0, "desc"]]));
  hakedisTable = $("#hakedisTable").DataTable(options);
}

function saveHakedis(form) {
  const formData = $(form).serializeArray();
  formData.push({ name: "type", value: "saveHakedis" });

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
        Swal.fire("Başarılı!", "Hakediş kaydedildi.", "success").then(() => {
          $("#yeniHakedisModal").modal("hide");
          hakedisTable.ajax.reload();
          // window.location.href = "?p=hakedisler/hakedis-detay&id=" + response.hakedis_id;
        });
      } else {
        Swal.fire("Hata!", response.message || "Bir hata oluştu.", "error");
      }
    },
    "json",
  ).fail(function () {
    Swal.fire("Hata!", "Sunucu bağlantısında sorun oluştu.", "error");
  });
}

function editHakedis(id) {
  Swal.fire({
    title: "Yükleniyor...",
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.post(
    "views/hakedisler/online-api.php",
    { type: "getHakedis", id: id },
    function (res) {
      if (res.status === "success") {
        Swal.close();
        const data = res.data;
        $("#hakedis_id").val(data.id);
        $("#yeniHakedisForm").find('[name="hakedis_no"]').val(data.hakedis_no);
        $("#yeniHakedisForm")
          .find('[name="hakedis_tarihi_ay"]')
          .val(data.hakedis_tarihi_ay);
        $("#yeniHakedisForm")
          .find('[name="hakedis_tarihi_yil"]')
          .val(data.hakedis_tarihi_yil);
        $("#yeniHakedisForm")
          .find('[name="temel_endeks_ayi"]')
          .val(data.temel_endeks_ayi);
        $("#yeniHakedisForm")
          .find('[name="guncel_endeks_ayi"]')
          .val(data.guncel_endeks_ayi);
        $("#yeniHakedisForm")
          .find('[name="is_yapilan_ayin_son_gunu"]')
          .val(data.is_yapilan_ayin_son_gunu);

        $("#yeniHakedisModal").modal("show");
        if (typeof feather !== "undefined") {
          setTimeout(() => {
            feather.replace();
          }, 100);
        }
      } else {
        Swal.fire("Hata", res.message, "error");
      }
    },
    "json",
  );
}

$(document).on("click", '[data-bs-target="#yeniHakedisModal"]', function () {
  $("#yeniHakedisForm")[0].reset();
  $("#hakedis_id").val("");
  if (typeof feather !== "undefined") {
    setTimeout(() => {
      feather.replace();
    }, 100);
  }
});

function deleteHakedis(id) {
  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu hakedişin tüm detay verileri ve miktar girişleri silinecektir. Geri alınamaz!",
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
        { type: "deleteHakedis", id: id },
        function (res) {
          if (res.status == "success") {
            hakedisTable.ajax.reload();
            Swal.fire("Silindi!", "Hakediş başarıyla silindi.", "success");
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
        "json",
      );
    }
  });
}
