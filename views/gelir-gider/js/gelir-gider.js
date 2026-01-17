//api sayfasının url tanımlamasını yap
let url = "views/gelir-gider/api.php";

//yeni işlem kaydet
$(document).on('click', '#gelirGiderEkle', function () {

  $("#gelir_gider_id").val(0);
});

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
      var table = $("#gelirGiderTable").DataTable();

      if (data.status == "success" && gelir_gider_id == 0) {
        //Eğer işlem başarılı ve yeni kayıt ise tabloya son eklenen kaydın bilgileri ekle ekle
        // table.row.add($(data.son_kayit)).draw(false);
        var addedRow = table.row.add($(data.son_kayit)).draw(false);
      } else if (data.status == "success" && gelir_gider_id != 0) {
        //Eğer işlem başarılı ve güncelleme ise tablodaki veriyi güncelle
        let rowNode = table.$(`tr[data-id="${gelir_gider_id}"]`)[0];
        if (rowNode) {
          //console.log(rowNode);
          table.row(rowNode).remove().draw();
          var addedRow = table.row.add($(data.son_kayit)).draw(false);
        }
      }
      // 2. Eklenen satırın DOM düğümünü al
      var rowNode = addedRow.node();

      // 3. Bu düğümü mevcut konumundan ayır (DOM'dan kaldır ama veriyi ve olayları koru)
      $(rowNode).detach();

      // 4. Ayrılan düğümü tablonun <tbody> elementinin en başına ekle
      $(table.table().body()).prepend(rowNode);

      swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam",
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
        .prop("checked", true)
        .trigger("click"); // Bu trigger ile select options yüklenir

      //Düzenleme modalini aç
      $("#gelirGiderModal").modal("show");
      $("#hesap_adi_text").val(data.hesap_adi);
      $("#islem_turu").val(data.islem_turu).trigger("change.select2");
      $("#finansal_aciklama").val(data.aciklama); // Açıklama alanı varsa
      $("#tutar").val(data.tutar);
      $("#aciklama").val(data.aciklama);
      // islem_tarihi'ni d.m.Y H:i formatında ayarlayan fonksiyon

      if (data.islem_tarihi) {
        $("#islem_tarihi").val(formatDateDMYHI(data.islem_tarihi));
      }
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

//Gelir Gider Türlerini getir
$(document).on("click", ".form-selectgroup-input", function () {
  let type = $(this).val();

  var formData = new FormData();
  formData.append("action", "gelir-gider-turu-getir");
  formData.append("type", type);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      //islem_turu selectine gelen datayı ekle
      const select = $("#islem_turu");

      // İçeriği temizle
      select.empty();

      // Gelen HTML option'ları direkt ekle
      select.html(data).trigger("change.select2");
    });
});

//gelirGiderEkle modalini temizle
$(document).on("click", "#gelirGiderEkle", function () {
  $("#gelirGiderForm").trigger("reset");
  $("#hesap_adi").val("").trigger("change.select2");
  $("#islem_turu").val("").trigger("change.select2");
});

//Üye seçimi yapıldığında hesap adına atar
$(document).on("change", "#hesap_adi", function () {
  let hesap_adi = $(this).val();
  $("#hesap_adi_text").val(hesap_adi);
  $(this).val(null).trigger("change.select2");
  //acordionu kapat

  var accordionCollapseElement = $("#uye-sec");
  var accordionButtonElement = $(".accordion-button");

  accordionCollapseElement.removeClass("show");
  accordionButtonElement.attr("aria-expanded", "false");
  accordionButtonElement.addClass("collapsed");
});


//Modaldaki yeni işlem butonunna basınca modali temizle
$(document).on("click", "#yeniIslemModal", function () {
  //Modalı temizle
  $("#gelir_gider_id").val(0);
  $("#gelirGiderForm").trigger("reset");
  $("#hesap_adi").val("").trigger("change.select2");
  $("#islem_turu").val("").trigger("change.select2");
  $("#hesap_adi_text").val("");
  $("#gelir_gider_id").val(0);
});