let url = "views/tanimlamalar/api.php";
let actionTable;

$(document).ready(function () {
  // Initialize flatpickr for date inputs with Turkish locale
  if ($(".flatpickr").length) {
    flatpickr(".flatpickr", {
      locale: "tr",
      dateFormat: "d.m.Y",
      allowInput: true,
    });
  }

  // Initialize Select2 if any
  if ($(".select2").length) {
    $(".select2").select2({
      dropdownParent: $("#actionModal"),
      width: "100%",
    });
  }

  // Initialize datatable using the globally defined getDatatableOptions()
  let datatableOptions =
    typeof getDatatableOptions === "function" ? getDatatableOptions() : {};

    datatableOptions = {
        ...datatableOptions,
        processing: true,
        serverSide: true,
        ajax: {
            url: url,
            type: "POST",
            data: function (d) {
                d.action = "defter-kodu-liste";
            },
        },
        columns: [
            { data: "id", className: "text-center" },
            { data: "tur_adi", className: "text-center" },
            { data: "defter_bolge", className: "text-center" },
            { data: "defter_mahalle", className: "text-center" },
            { data: "defter_abone_sayisi", className: "text-center" },
            { data: "baslangic_tarihi", className: "text-center" },
            { data: "bitis_tarihi", className: "text-center" },
            { data: "aciklama", className: "text-center" },
            { data: "islem", className: "text-center", orderable: false },
        ],
        order: [[0, "desc"]],
        buttons: [
            {
                extend: "excelHtml5",
                title: "Defter Kodları Listesi",
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7],
                },
            },
        ],
    };

    actionTable = $("#actionTable").DataTable(datatableOptions);
});

// Excel'e Aktar butonuna tıklandığında tüm filtrelenmiş veriyi çek
$(document).on("click", "#btnExcelAktar", function () {
    if (actionTable) {
        let params = actionTable.ajax.params();
        
        // POST ile dosya indirme için geçici form oluştur (URL uzunluk sınırlarını aşmamak için)
        let form = $('<form>', {
            action: url,
            method: 'POST'
        });

        const addInputs = (obj, prefix = "") => {
            for (let key in obj) {
                let name = prefix ? `${prefix}[${key}]` : key;
                if (typeof obj[key] === "object" && obj[key] !== null) {
                    addInputs(obj[key], name);
                } else {
                    $("<input>", {
                        type: "hidden",
                        name: name,
                        value: obj[key],
                    }).appendTo(form);
                }
            }
        };

        addInputs({
            ...params,
            action: "defter-kodu-excel"
        });

        form.appendTo("body").submit().remove();
    }
});

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#id").val(0);
  $("#actionModalLabel").text("Defter Kodu Ekle");
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      tur_adi: {
        required: true,
      },
      defter_bolge: {
        required: true,
      },
    },
    messages: {
      tur_adi: {
        required: "Defter Kodu boş bırakılamaz",
      },
      defter_bolge: {
        required: "Bölge boş bırakılamaz",
      },
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
    },
  });

  if (!form.valid()) {
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "defter-kodu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

      if (data.status == "success") {
        actionTable.ajax.reload(null, false);
        $("#actionModal").modal("hide");
      }

      swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam",
      });
    })
    .catch((error) => {
      swal.fire("Hata", "Bir hata oluştu", "error");
    });
});

$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  var formData = new FormData();
  formData.append("action", "defter-kodu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        $("#id").val(id);
        $("#tur_adi").val(data.data.tur_adi);
        $("#defter_bolge").val(data.data.defter_bolge);
        $("#defter_mahalle").val(data.data.defter_mahalle);
        $("#defter_abone_sayisi").val(data.data.defter_abone_sayisi);

        if (data.data.baslangic_tarihi) {
          document
            .querySelector("#baslangic_tarihi")
            ._flatpickr.setDate(data.data.baslangic_tarihi, true, "d.m.Y");
        } else {
          $("#baslangic_tarihi").val("");
        }

        if (data.data.bitis_tarihi) {
          document
            .querySelector("#bitis_tarihi")
            ._flatpickr.setDate(data.data.bitis_tarihi, true, "d.m.Y");
        } else {
          $("#bitis_tarihi").val("");
        }

        $("#aciklama").val(data.data.aciklama);

        $("#actionModalLabel").text("Defter Kodu Düzenle");
        $("#actionModal").modal("show");
      } else {
        swal.fire("Hata", data.message, "error");
      }
    })
    .catch((error) => {
      swal.fire("Hata", "Bir hata oluştu", "error");
    });
});

$(document).on("click", ".sil", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  swal
    .fire({
      title: "Emin misiniz?",
      text: "Bu kaydı silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal",
    })
    .then((result) => {
      if (result.isConfirmed) {
        var formData = new FormData();
        formData.append("action", "defter-kodu-sil");
        formData.append("id", id);

        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status == "success") {
              actionTable.ajax.reload(null, false);
              swal.fire("Silindi!", data.message, "success");
            } else {
              swal.fire("Hata", data.message, "error");
            }
          })
          .catch((error) => {
            swal.fire("Hata", "Bir hata oluştu", "error");
          });
      }
    });
});

// Excel Yükle Form Submit
$(document).on("submit", "#formExcelYukle", function (e) {
  e.preventDefault();

  var form = $(this);
  var formData = new FormData(form[0]);
  formData.append("action", "defter-kodu-excel-yukle");

  // Yükleniyor göster
  Swal.fire({
    title: "Yükleniyor...",
    text: "Excel dosyası işleniyor, lütfen bekleyin.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      Swal.close();

      if (data.status === "success") {
        Swal.fire({
          title: "Başarılı",
          html: `
                    <div class="text-center">
                        <p>${data.message}</p>
                        ${data.insertCount > 0 ? `<div class="badge bg-success me-1">${data.insertCount} yeni kayıt</div>` : ""}
                        ${data.updateCount > 0 ? `<div class="badge bg-info">${data.updateCount} güncelleme</div>` : ""}
                    </div>
                `,
          icon: "success",
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            actionTable.ajax.reload();
          }
        });
      } else if (data.status === "warning") {
        Swal.fire({
          title: "Uyarı",
          text: data.message,
          icon: "warning",
          confirmButtonText: "Tamam",
        });
      } else {
        Swal.fire({
          title: "Hata",
          text: data.message,
          icon: "error",
          confirmButtonText: "Tamam",
        });
      }

      // Modal'ı kapat ve formu sıfırla
      $("#excelModal").modal("hide");
      form[0].reset();
    })
    .catch((error) => {
      Swal.close();
      Swal.fire({
        title: "Hata",
        text: "Bir hata oluştu: " + error.message,
        icon: "error",
        confirmButtonText: "Tamam",
      });
    });
});

// İcmal Butonu Tıklama
$(document).on("click", "#btnIcmal", function () {
    $("#icmalModal").modal("show");
    $("#icmalContent").html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Yükleniyor...</span>
            </div>
        </div>
    `);

    // DataTable'ın mevcut filtre parametrelerini al
    let dtParams = actionTable ? actionTable.ajax.params() : {};
    
    $.ajax({
        url: url,
        type: "POST",
        data: {
            ...dtParams,
            action: "defter-kodu-icmal"
        },
        dataType: "json",
        success: function (data) {
            if (data.status === "success") {
                $("#icmalContent").html(data.html);
            } else {
                $("#icmalContent").html(
                    '<div class="alert alert-danger m-3">' + data.message + "</div>"
                );
            }
        },
        error: function (xhr, status, error) {
            $("#icmalContent").html(
                '<div class="alert alert-danger m-3">Bir hata oluştu: ' + error + '</div>'
            );
        }
    });
});

// İcmal Tablosundan Bölgeye Göre Filtreleme
$(document).on("click", ".icmal-bolge-filter", function () {
  let bolge = $(this).data("bolge");
  
  // Gelişmiş filtre inputunu bul ve değeri set et (badge görünmesi için)
  // Bölge sütunu indexi 2
  let $filterInput = $('#actionTable thead tr.dt-filter-row th').eq(2).find('input');
  
  if ($filterInput.length) {
      $filterInput.val(bolge).trigger('input');
  } else {
      actionTable.column(2).search(bolge).draw();
  }
  
  $("#icmalModal").modal("hide");

  // Filtre bilgisini kullanıcıya göster
  Swal.fire({
    toast: true,
    position: "top-end",
    icon: "info",
    title: bolge + " bölgesi filtrelendi",
    showConfirmButton: false,
    timer: 2000,
  });
});
