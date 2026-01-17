let zimmetUrl = "views/demirbas/api.php";
let table, zimmetTable;

// ============== SAYFA YÜKLENDİĞİNDE ==============
$(document).ready(function () {
  // DataTable başlat
  table = $("#demirbasTable").DataTable({
    responsive: true,
    language: {
      url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json"
    },
    columnDefs: [
      { orderable: false, targets: -1 }
    ],
    order: [[0, "asc"]]
  });

  // Zimmet tablosu DataTable
  zimmetTable = $("#zimmetTable").DataTable({
    responsive: true,
    language: {
      url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json"
    },
    columnDefs: [
      { orderable: false, targets: -1 }
    ],
    order: [[0, "desc"]]
  });

  // Select2 başlat
  initSelect2();

  // Flatpickr başlat
  $(".flatpickr").flatpickr({
    dateFormat: "d.m.Y",
    locale: "tr"
  });

  // Money mask
  $(".money").mask("#.##0,00", { reverse: true });
});

// ============== SELECT2 BAŞLAT ==============
function initSelect2() {
  $("#demirbas_id_zimmet").select2({
    dropdownParent: $("#zimmetModal"),
    placeholder: "Demirbaş arayın...",
    allowClear: true,
    width: "100%"
  });

  $("#personel_id").select2({
    dropdownParent: $("#zimmetModal"),
    placeholder: "Personel arayın...",
    allowClear: true,
    width: "100%"
  });
}

// ============== TAB DEĞİŞİKLİĞİNDE ==============
$('button[data-bs-toggle="tab"]').on("shown.bs.tab", function (e) {
  if (e.target.id === "zimmet-tab") {
    loadZimmetList();
  }
  
  // DataTable responsive yeniden hesapla
  setTimeout(() => {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
  }, 100);
});

// ============== ZİMMET LİSTESİ YÜKLE ==============
function loadZimmetList() {
  fetch(zimmetUrl, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "action=zimmet-listesi"
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        zimmetTable.clear();
        data.rows.forEach((row) => {
          zimmetTable.row.add($(row)).draw(false);
        });
      }
    })
    .catch((err) => console.error("Zimmet listesi yüklenemedi:", err));
}

// ============== DEMİRBAŞ İŞLEMLERİ ==============

// Demirbaş Kaydet
$(document).on("click", "#demirbasKaydet", function () {
  var form = $("#demirbasForm");
  var demirbas_id = $("#demirbas_id").val();

  form.validate({
    rules: {
      demirbas_adi: { required: true },
      kategori_id: { required: true },
      miktar: { required: true, min: 1 }
    },
    messages: {
      demirbas_adi: { required: "Demirbaş adı zorunludur" },
      kategori_id: { required: "Kategori seçimi zorunludur" },
      miktar: { required: "Miktar zorunludur", min: "Miktar en az 1 olmalıdır" }
    },
    errorElement: "span",
    errorClass: "text-danger small",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
    }
  });

  if (!form.valid()) return;

  var formData = new FormData(form[0]);
  formData.append("action", "demirbas-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        if (demirbas_id == 0) {
          table.row.add($(data.son_kayit)).draw(false);
        } else {
          let rowNode = table.$(`tr[data-id="${demirbas_id}"]`)[0];
          if (rowNode) {
            table.row(rowNode).remove().draw();
            table.row.add($(data.son_kayit)).draw(false);
          }
        }
        $("#demirbasModal").modal("hide");
        resetDemirbasForm();
        
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam"
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam"
        });
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Hata!",
        text: "İşlem sırasında bir hata oluştu.",
        confirmButtonText: "Tamam"
      });
    });
});

// Demirbaş Düzenle
$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  $("#demirbas_id").val(id);

  var formData = new FormData();
  formData.append("action", "demirbas-getir");
  formData.append("demirbas_id", id);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        var d = data.data;
        for (var key in d) {
          if ($("#" + key).length) {
            if (key === "edinme_tutari" && d[key]) {
              // Para formatı
              $("#" + key).val(parseFloat(d[key]).toLocaleString("tr-TR", { minimumFractionDigits: 2 }));
            } else {
              $("#" + key).val(d[key]);
            }
          }
        }
        $("#demirbasModal").modal("show");
      }
    });
});

// Demirbaş Sil
$(document).on("click", ".demirbas-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let name = $(this).data("name");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    html: `<strong>${name}</strong> adlı demirbaşı silmek istediğinizden emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal"
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=demirbas-sil&id=${id}`
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            table.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success");
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetDemirbasForm() {
  $("#demirbasForm")[0].reset();
  $("#demirbas_id").val(0);
  $("#kategori_id").val("");
  $("#durum").val("aktif");
  $("#miktar").val(1);
}

// Modal kapatıldığında formu sıfırla
$("#demirbasModal").on("hidden.bs.modal", function () {
  resetDemirbasForm();
});

// ============== ZİMMET İŞLEMLERİ ==============

// Demirbaş listesinden zimmet ver
$(document).on("click", ".zimmet-ver", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let name = $(this).data("name");
  let kalan = $(this).data("kalan");

  // Demirbaş seçimini yap
  // Not: Select2'de değer seçmek için ID'yi decrypt etmemiz gerekiyor
  // Şimdilik modal'ı aç, kullanıcı manuel seçim yapsın
  
  $("#zimmetModal").modal("show");
  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan);
});

// Demirbaş seçildiğinde kalan miktarı göster
$(document).on("change", "#demirbas_id_zimmet", function () {
  let kalan = $(this).find(":selected").data("kalan") || 0;
  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan).val(1);
});

// Zimmet Kaydet
$(document).on("click", "#zimmetKaydet", function () {
  var form = $("#zimmetForm");

  form.validate({
    rules: {
      demirbas_id: { required: true },
      personel_id: { required: true },
      teslim_miktar: { required: true, min: 1 },
      teslim_tarihi: { required: true }
    },
    messages: {
      demirbas_id: { required: "Demirbaş seçimi zorunludur" },
      personel_id: { required: "Personel seçimi zorunludur" },
      teslim_miktar: { required: "Miktar zorunludur" },
      teslim_tarihi: { required: "Teslim tarihi zorunludur" }
    },
    errorElement: "span",
    errorClass: "text-danger small",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element) {
      $(element).removeClass("is-invalid");
    }
  });

  if (!form.valid()) return;

  // Miktar kontrolü
  let kalan = parseInt($("#demirbas_id_zimmet").find(":selected").data("kalan") || 0);
  let teslimMiktar = parseInt($("#teslim_miktar").val());
  
  if (teslimMiktar > kalan) {
    Swal.fire({
      icon: "error",
      title: "Yetersiz Stok!",
      text: `Stokta sadece ${kalan} adet bulunmaktadır.`,
      confirmButtonText: "Tamam"
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "zimmet-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#zimmetModal").modal("hide");
        resetZimmetForm();
        
        // Zimmet tablosunu yenile
        loadZimmetList();
        
        // Demirbaş tablosunu yenile (stok güncellendi)
        location.reload();
        
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam"
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam"
        });
      }
    });
});

// Zimmet İade Modal Aç
$(document).on("click", ".zimmet-iade", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let demirbas = $(this).data("demirbas");
  let personel = $(this).data("personel");
  let miktar = $(this).data("miktar");

  $("#iade_zimmet_id").val(id);
  $("#iade_demirbas_adi").text(demirbas);
  $("#iade_personel_adi").text(personel);
  $("#iade_teslim_miktar").text(miktar);
  $("#iade_miktar").val(miktar).attr("max", miktar);
  
  $("#iadeModal").modal("show");
});

// İade Kaydet
$(document).on("click", "#iadeKaydet", function () {
  var form = $("#iadeForm");

  let iadeMiktar = parseInt($("#iade_miktar").val());
  let teslimMiktar = parseInt($("#iade_teslim_miktar").text());
  
  if (iadeMiktar > teslimMiktar) {
    Swal.fire({
      icon: "error",
      title: "Hata!",
      text: `İade miktarı teslim edilen miktardan (${teslimMiktar}) fazla olamaz.`,
      confirmButtonText: "Tamam"
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "zimmet-iade");
  formData.append("zimmet_id", $("#iade_zimmet_id").val());

  fetch(zimmetUrl, {
    method: "POST",
    body: formData
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#iadeModal").modal("hide");
        
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam"
        }).then(() => {
          location.reload();
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam"
        });
      }
    });
});

// Zimmet Sil
$(document).on("click", ".zimmet-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu zimmet kaydını silmek istediğinizden emin misiniz? Stok miktarı güncellenecektir.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal"
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=zimmet-sil&id=${id}`
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            zimmetTable.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success").then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetZimmetForm() {
  $("#zimmetForm")[0].reset();
  $("#zimmet_id").val(0);
  $("#demirbas_id_zimmet").val("").trigger("change");
  $("#personel_id").val("").trigger("change");
  $("#kalanMiktarText").text("-");
  $(".flatpickr").flatpickr({
    dateFormat: "d.m.Y",
    locale: "tr",
    defaultDate: new Date()
  });
}

// Modal kapatıldığında formu sıfırla
$("#zimmetModal").on("hidden.bs.modal", function () {
  resetZimmetForm();
});
