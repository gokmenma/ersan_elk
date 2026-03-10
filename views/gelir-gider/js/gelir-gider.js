let url = "views/gelir-gider/api.php";
let gelirGiderTable;

$(document).ready(function() {
    initGelirGiderTable();
});

function initGelirGiderTable() {
    const tableId = "#gelirGiderTable";
    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    var opts = getDatatableOptions();
    var originalInitComplete = opts.initComplete;

    opts.serverSide = true;
    opts.processing = true;
    opts.ajax = {
        url: url,
        type: "POST",
        data: function(d) {
            d.action = "gelir-gider-ajax-list";
            d.yil = $('select[name="yil"]').val();
            d.ay = $('select[name="ay"]').val();
            d.tip = $('select[name="tip"]').val();
        }
    };
    opts.columns = [
        { data: 'id' },
        { data: 'kayit_tarihi' },
        { data: 'type' },
        { data: 'kategori_adi' },
        { data: 'tarih' },
        { data: 'tutar' },
        { data: 'bakiye' },
        { data: 'aciklama' },
        { data: 'actions' }
    ];
    opts.columnDefs = [
        { targets: [0, 1, 2, 3, 4, 8], className: "text-center" },
        { targets: [5, 6], className: "text-end" }
    ];
    opts.order = [[1, "desc"]]; // Kayıt tarihine göre azalan
    opts.initComplete = function(settings, json) {
        // Orijinal (global) initComplete'i çağır ki gelişmiş filtreler (data-filter) yüklenebilsin
        if (typeof originalInitComplete === "function") {
            originalInitComplete.call(this, settings, json);
        }
    };

    gelirGiderTable = $(tableId).DataTable(opts);

    gelirGiderTable.on('xhr.dt', function ( e, settings, json, xhr ) {
        if (json && json.summary) {
            updateSummaryCards(json.summary);
        }
    });
}

function reloadGelirGiderTable() {
    if (gelirGiderTable) {
        gelirGiderTable.ajax.reload(null, false);
    } else {
        initGelirGiderTable();
    }
}

function updateSummaryCards(summary) {
    if (!summary) return;
    
    // Rakamları formatlayan basit bir fonksiyon
    const format = (val) => {
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);
    };

    $("#card_toplam_gelir").html(format(summary.toplam_gelir) + ' <span style="font-size:0.85rem;font-weight:600;">₺</span>');
    $("#card_toplam_gider").html(format(summary.toplam_gider) + ' <span style="font-size:0.85rem;font-weight:600;">₺</span>');
    $("#card_net_bakiye").html(format(summary.bakiye) + ' <span style="font-size:0.85rem;font-weight:600;">₺</span>');
    
    // Bakiye rengini güncelle
    const bakiyeColor = summary.bakiye < 0 ? '#f43f5e' : '#0ea5e9';
    $("#card_net_bakiye").closest('.card').css('border-bottom-color', bakiyeColor + ' !important');
    $("#card_net_bakiye").closest('.card').find('i').css('color', bakiyeColor);
}


//yeni işlem kaydet
$(document).on('click', '#gelirGiderEkle', function () {

  $("#gelir_gider_id").val(0);
});

// İşlem türüne göre kategorileri getir
$(document).on('change', '.form-selectgroup-input', function() {
    const type = $(this).val();
    fetchCategories(type);
});

// Kategorileri getir (Filtreleme ile)
function fetchCategories(type, selectedValue = null) {
    const formData = new FormData();
    formData.append("action", "gelir-gider-turu-getir");
    formData.append("type", type);

    return fetch(url, {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        let options = '<option value="">Seçiniz</option>';
        // Veri dizi değilse (HTML dönmüşse) hata vermemesi için kontrol
        if (Array.isArray(data)) {
            data.forEach(item => {
                options += `<option value="${item.id}">${item.tur_adi}</option>`;
            });
        } else {
            // Eski API <option> formatında HTML dönüyor olabilir
            options = data;
        }
        $("#islem_turu").html(options).trigger("change.select2");
        
        if (selectedValue) {
            $("#islem_turu").val(selectedValue).trigger("change.select2");
        }
    });
}

$(document).on("click", "#gelirGiderKaydet", function () {
  var form = $("#gelirGiderForm");
  var gelir_gider_id = $("#gelir_gider_id").val();
  form.validate({
    rules: {
      islem_turu: {
        required: true,
      },
      tutar: {
        required: true,
      },
      islem_tarihi: {
        required: true,
      },
    },
    messages: {
      islem_turu: {
        required: "İşlem türü alanı boş bırakılamaz",
      },
      tutar: {
        required: "Tutar alanı boş bırakılamaz",
      },
      islem_tarihi: {
        required: "Ödeme tarihi alanı boş bırakılamaz",
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

  //butonu disable yap
  $(this).prop("disabled", true);

  //İşlemin id'sini al,eğer yoksa 0 ata

  var formData = new FormData(form[0]);
  formData.append("action", "gelir-gider-kaydet");
  formData.append("gelir_gider_id", gelir_gider_id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";
      console.log(data);
      

      swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam",
      }).then(() => {
          if (data.status == "success") {
              $("#gelirGiderModal").modal("hide");
              reloadGelirGiderTable();
          }
      });

      $("#gelir_gider_id").val(data.id);
      // var row = table.row(table.rows().count()-1).node();
      // $(row).detach();
      // $(table.table().body()).prepend(row);
    });

  //butonu tekrar enable yap
  $(this).prop("disabled", false);
});



function formatDateDMYHI(dateStr) {
  if (!dateStr) return "";
  const dateObj = new Date(dateStr.replace(/-/g, '/'));
  const pad = (n) => n < 10 ? '0' + n : n;
  return (
    pad(dateObj.getDate()) + '.' +
    pad(dateObj.getMonth() + 1) + '.' +
    dateObj.getFullYear() + ' ' +
    pad(dateObj.getHours()) + ':' +
    pad(dateObj.getMinutes())
  );
}
//Gelir-gider düzenle
$(document).on("click", ".duzenle", function () {
  var gelir_gider_id = $(this).data("id");
  $("#gelir_gider_id").val(gelir_gider_id);
  var formData = new FormData();
  formData.append("action", "gelir-gider-getir");
  formData.append("gelir_gider_id", gelir_gider_id);
  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      console.log(data);

      $(`.form-selectgroup-input[value="${data.type}"]`)
        .prop("checked", true);
        
      // Kategorileri seçilen türe göre yükle ve sonra seçili olanı ayarla
      fetchCategories(data.type, data.kategori).then(() => {
          //Düzenleme modalini aç
          $("#gelirGiderModal").modal("show");

          $("#finansal_aciklama").val(data.aciklama);
          $("#tutar").val(data.tutar);
          $("#aciklama").val(data.aciklama);

          if (data.tarih) {
              $("#islem_tarihi").val(formatDateDMYHI(data.tarih));
          }
      });
    });
});

//Gelir-gider-sil
$(document).on("click", ".gelir-gider-sil", function () {
  var gelir_gider_id = $(this).data("id");
  let buttonElement = $(this); // Store reference to the clicked button

  var formData = new FormData();
  formData.append("action", "gelir-gider-sil");
  formData.append("gelir_gider_id", gelir_gider_id);

  confirmAndDelete(url, formData, buttonElement, "gelirGiderTable");
});


//gelirGiderEkle modalini temizle
$(document).on("click", "#gelirGiderEkle", function () {
  $("#gelirGiderForm").trigger("reset");
  // Varsayılan olarak Gider (2) seçili gelsin
  $('.form-selectgroup-input[value="2"]').prop('checked', true);
  // Kategorileri Gider (2) için yükle
  fetchCategories(2);
});


//Modaldaki yeni işlem butonunna basınca modali temizle
$(document).on("click", "#yeniIslemModal", function () {
  //Modalı temizle
  $("#gelir_gider_id").val(0);
  $("#gelirGiderForm").trigger("reset");

  $("#islem_turu").val("").trigger("change.select2");

  $("#gelir_gider_id").val(0);
});