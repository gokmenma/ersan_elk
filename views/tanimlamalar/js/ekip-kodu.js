let url = "views/tanimlamalar/api.php";

// Bölge kuralları cache
let bolgeKurallari = {};

// Sayfa yüklendiğinde kuralları al
$(document).ready(function () {
  $("#ekip_bolge").select2({
    dropdownParent: $("#actionModal"),
    tags: true,
    width: "100%",
  });

  // yeniBolge select2 initialize (eğer varsa)
  if ($("#yeniBolge").length) {
    $("#yeniBolge").select2({
      dropdownParent: $("#actionModal"),
      width: "100%",
      placeholder: "Bölge Seçin",
      allowClear: true,
    });
  }

  // Bölge kurallarını yükle
  loadBolgeKurallari();

  // Sekme değişikliğinde butonları güncelle
  $('button[data-bs-toggle="tab"]').on("shown.bs.tab", function (e) {
    const targetTab = $(e.target).attr("data-bs-target");
    if (targetTab === "#bolgeKurallariContent") {
      $("#actionKaydet").hide();
      $("#kuralKaydet").show();
      // Feather icons'ları yeniden render et
      if (typeof feather !== "undefined") {
        feather.replace();
      }
    } else {
      $("#actionKaydet").show();
      $("#kuralKaydet").hide();
    }
  });

  // Modal açıldığında feather icons'ları render et
  $("#actionModal").on("shown.bs.modal", function () {
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  });

  // Bölge değiştiğinde kural bilgisini göster
  $("#ekip_bolge").on("change", function () {
    updateEkipKoduInfo();
  });
});

// Bölge kurallarını yükle
function loadBolgeKurallari() {
  var formData = new FormData();
  formData.append("action", "bolge-kurallari-getir");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        bolgeKurallari = data.kurallar || {};
      }
    })
    .catch((err) => console.error("Kurallar yüklenemedi:", err));
}

// Ekip kodu info güncelleme
function updateEkipKoduInfo() {
  const bolge = $("#ekip_bolge").val();
  const infoEl = $("#ekipKoduInfo");

  if (bolge && bolgeKurallari[bolge]) {
    const kural = bolgeKurallari[bolge];
    infoEl
      .html(
        `<i class="bx bx-info-circle"></i> Bu bölge için ekip numarası <strong>${kural.min}</strong> ile <strong>${kural.max}</strong> arasında olmalıdır.`,
      )
      .removeClass("text-muted")
      .addClass("text-warning");
  } else {
    infoEl.html("").removeClass("text-warning").addClass("text-muted");
  }
}

// Ekip kodundan sayıyı çıkar (örn: "ER-SAN ELEKTRİK EKİP-10" -> 10)
function extractEkipNumber(ekipKodu) {
  const match = ekipKodu.match(/(\d+)$/);
  return match ? parseInt(match[1], 10) : null;
}

// Ekip kodu validasyonu
function validateEkipKodu(bolge, ekipKodu) {
  if (!bolge || !bolgeKurallari[bolge]) {
    return { valid: true };
  }

  const ekipNo = extractEkipNumber(ekipKodu);
  if (ekipNo === null) {
    return {
      valid: false,
      message:
        "Ekip kodunun sonunda bir sayı bulunmalıdır (örn: ER-SAN ELEKTRİK EKİP-10)",
    };
  }

  const kural = bolgeKurallari[bolge];
  if (ekipNo < kural.min || ekipNo > kural.max) {
    return {
      valid: false,
      message: `${bolge} bölgesi için ekip numarası ${kural.min} ile ${kural.max} arasında olmalıdır. Girilen: ${ekipNo}`,
    };
  }

  return { valid: true };
}

$(document).on("click", "#actionEkle", function () {
  $("#actionForm")[0].reset();
  $("#ekip_bolge").val("").trigger("change");
  $("#ekip_id").val(0);
  $("#ekipKoduInfo").html("");
  $("#actionModalLabel").text("Ekip Kodu Ekle");

  // İlk sekmeye dön
  $("#ekipKodu-tab").tab("show");
  $("#actionKaydet").show();
  $("#kuralKaydet").hide();
});

$(document).on("click", "#actionKaydet", function () {
  var form = $("#actionForm");

  form.validate({
    rules: {
      ekip_kodu: {
        required: true,
      },
    },
    messages: {
      ekip_kodu: {
        required: "Ekip Kodu boş bırakılamaz",
      },
    },
    errorElement: "span",
    highlight: function (element) {
      $(element).addClass("is-invalid");
    },
  });

  if (!form.valid()) {
    return;
  }

  // Bölge kuralı validasyonu
  const bolge = $("#ekip_bolge").val();
  const ekipKodu = $("#ekip_kodu").val();

  const validation = validateEkipKodu(bolge, ekipKodu);
  if (!validation.valid) {
    swal.fire({
      title: "Geçersiz Ekip Kodu",
      text: validation.message,
      icon: "error",
      confirmButtonText: "Tamam",
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "ekip-kodu-kaydet");

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      title = data.status == "success" ? "Başarılı" : "Hata";

      swal
        .fire({
          title: title,
          text: data.message,
          icon: data.status,
          confirmButtonText: "Tamam",
        })
        .then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
    });
});

$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  var id = $(this).data("id");

  var formData = new FormData();
  formData.append("action", "ekip-kodu-getir");
  formData.append("id", id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        // We need to set the hidden input to the ENCRYPTED id so save works
        $("#ekip_id").val(id);
        $("#ekip_bolge").val(data.data.ekip_bolge).trigger("change");

        $("#ekip_kodu").val(data.data.ekip_kodu);
        $("#aciklama").val(data.data.aciklama);
        $("#actionModalLabel").text("Ekip Kodu Düzenle");

        // İlk sekmeye dön
        $("#ekipKodu-tab").tab("show");
        $("#actionKaydet").show();
        $("#kuralKaydet").hide();

        updateEkipKoduInfo();

        $("#actionModal").modal("show");
      } else {
        swal.fire("Hata", data.message, "error");
      }
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
        formData.append("action", "ekip-kodu-sil");
        formData.append("id", id);

        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status == "success") {
              var table = $("#actionTable").DataTable();
              table
                .row($("#row_" + data.deleted_id))
                .remove()
                .draw(false);

              swal.fire("Silindi!", "Kayıt başarıyla silindi.", "success");
            } else {
              swal.fire("Hata", data.message, "error");
            }
          });
      }
    });
});

// ========== BÖLGE KURALLARI İŞLEMLERİ ==========

// Yeni kural ekle
$(document).on("click", "#kuralEkle", function () {
  const bolge = $("#yeniBolge").val();
  const min = parseInt($("#yeniMin").val(), 10);
  const max = parseInt($("#yeniMax").val(), 10);

  if (!bolge) {
    swal.fire("Uyarı", "Lütfen bir bölge seçin.", "warning");
    return;
  }

  if (isNaN(min) || isNaN(max)) {
    swal.fire("Uyarı", "Min ve Maks değerleri sayı olmalıdır.", "warning");
    return;
  }

  if (min > max) {
    swal.fire("Uyarı", "Min değeri Maks değerinden büyük olamaz.", "warning");
    return;
  }

  // Tabloya ekle
  const newRow = `
    <tr data-bolge="${bolge}">
      <td>
        <input type="text" class="form-control form-control-sm bolge-input" value="${bolge}" readonly>
      </td>
      <td>
        <input type="number" class="form-control form-control-sm min-input" value="${min}" min="0">
      </td>
      <td>
        <input type="number" class="form-control form-control-sm max-input" value="${max}" min="0">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-danger kural-sil d-inline-flex align-items-center justify-content-center">
          <i data-feather="trash-2" style="width:14px;height:14px;"></i>
        </button>
      </td>
    </tr>
  `;

  $("#bolgeKurallariBody").append(newRow);

  // Feather icons'ları yeniden render et
  if (typeof feather !== "undefined") {
    feather.replace();
  }

  // Dropdown'dan seçeneği kaldır
  $(`#yeniBolge option[value="${bolge}"]`).remove();

  // Inputları temizle
  $("#yeniBolge").val("").trigger("change");
  $("#yeniMin").val("");
  $("#yeniMax").val("");
});

// Kural sil
$(document).on("click", ".kural-sil", function () {
  const row = $(this).closest("tr");
  const bolge = row.data("bolge");

  // Dropdown'a geri ekle
  $("#yeniBolge").append(`<option value="${bolge}">${bolge}</option>`);

  row.remove();
});

// Kuralları kaydet
$(document).on("click", "#kuralKaydet", function () {
  const kurallar = {};

  $("#bolgeKurallariBody tr").each(function () {
    const bolge = $(this).find(".bolge-input").val();
    const min = parseInt($(this).find(".min-input").val(), 10);
    const max = parseInt($(this).find(".max-input").val(), 10);

    if (bolge && !isNaN(min) && !isNaN(max)) {
      kurallar[bolge] = { min, max };
    }
  });

  var formData = new FormData();
  formData.append("action", "bolge-kurallari-kaydet");
  formData.append("kurallar", JSON.stringify(kurallar));

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        bolgeKurallari = kurallar; // Cache'i güncelle
        swal.fire({
          title: "Başarılı",
          text: data.message,
          icon: "success",
          confirmButtonText: "Tamam",
        });
      } else {
        swal.fire("Hata", data.message, "error");
      }
    });
});
