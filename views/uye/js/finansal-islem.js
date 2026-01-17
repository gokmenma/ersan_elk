let url = "views/uye/api.php";
//Gelir Gider Türlerini getir
$(document).on("click", ".form-selectgroup-input", function () {
  let type = $(this).val();

  var formData = new FormData();
  formData.append("action", "gelir-gider-turu-getir");
  formData.append("type", type);

  fetch("views/gelir-gider/api.php", {
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

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//********************Yeni Finansal İşlem Ekle***************
$(document).on("click", "#finansalIslemEkle", function () {
  //Temsilcilik kaydedildi mi kontrol et.
  var uye_id = $("#uye_id").val();
  if (uye_id == 0) {
    swal.fire({
      title: "Hata",
      text: "Önce Üye Bilgilerini kaydetmeniz gerekir",
      icon: "error",
    });
    return;
  }

  $("#finansalIslemForm").trigger("reset");
  $("#finansal_islem_id").val(0);
  //Modali aç
  $("#finansalIslemModal").modal("show");
});
//********************Yeni Finansal İşlem Ekle***************

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//*********************FİNANSAL İŞLEMLER KAYDET*************
$(document).on("click", "#finansalIslemKaydet", function () {
  let form = $("#finansalIslemForm");
  let uye_id = $("#uye_id").val();
  let finansal_islem_id = $("#finansal_islem_id").val();

  //Üye bilgileri kaydedilmemişse hata ver
  if (uye_id == "" || uye_id == 0) {
    Swal.fire({
      title: "Hata",
      text: "Üye bilgileri kaydedilmemiş",
      icon: "error",
      confirmButtonText: "Tamam",
    });
    return false;
  }

  //Form Doğrulaması yap
  form.validate({
    rules: {
      tutar: "required",
      islem_tarihi: "required",
    },
    messages: {
      tutar: "Tutar alanı boş bırakılamaz",
      islem_tarihi: "İşlem tarihi alanı boş bırakılamaz",
    },
  });

  //Form doğrulaması başarısız ise işlemi durdur
  if (!form.valid()) {
    return false;
  }

  let formData = new FormData(form[0]);
  let action = "finansal-islem-kaydet";

  formData.append("action", action);
  formData.append("uye_id", uye_id);
  formData.append("finansal_islem_id", finansal_islem_id);

  // for (var pair of formData.entries()) {
  //   console.log(pair[0] + ", " + pair[1]);
  // }

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      console.log(data);
      // Tabloya yeni satır eklemek için
      if (data.status == "success") {
        let table = $("#finansalIslemTable").DataTable();
        if (finansal_islem_id != 0) {
          //Eğer işlem başarılı ve güncelleme ise tablodaki veriyi güncelle
          let rowNode = table.$(`tr[data-id="${finansal_islem_id}"]`)[0];
          if (rowNode) {
            table.row(rowNode).remove().draw();
          }
        }
        table.row.add($(data.son_kayit)).draw(false);
      }
      title = data.status == "success" ? "Başarılı" : "Hata";
      Swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam",
      });
    });
});
//*********************FİNANSAL İŞLEMLER KAYDET*************

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//*********************FİNANSAL İŞLEMLER DÜZENLE*************
$(document).on("click", ".finansal-islem-duzenle", function () {
  let finansal_islem_id = $(this).data("id");
  //bilgileri veritabanından çek
  var formData = new FormData();
  formData.append("action", "finansal-islem-bilgi");
  formData.append("finansal_islem_id", finansal_islem_id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      console.log(data);
      if (data.status == "success") {
        $("#finansal_islem_id").val(finansal_islem_id);
        $("#tutar").val(data.tutar);
        $("#islem_tarihi").val(data.islem_tarihi);
        $("#finansal_aciklama").val(data.aciklama);
        //console.log(data.aciklama);

        $("#finansalIslemModal").modal("show");
      }
    });
});
//*********************FİNANSAL İŞLEMLER DÜZENLE*************

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//*********************FİNANSAL İŞLEMLER SİL*************
$(document).on("click", ".finansal-islem-sil", function () {
  let finansal_islem_id = $(this).data("id");
  let buttonElement = $(this); // Store reference to the clicked button

  Swal.fire({
    title: "Emin misiniz?",
    html: `Finansal işlemi silmek istediğinize emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Evet",
    cancelButtonText: "Hayır",
  }).then((result) => {
    if (result.isConfirmed) {
      var formData = new FormData();
      formData.append("action", "finansal-islem-sil");
      formData.append("finansal_islem_id", finansal_islem_id);

      fetch("views/uye/api.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status == "success") {
            let table = $("#finansalIslemTable").DataTable();
            table.row(buttonElement.closest("tr")).remove().draw(false);
            Swal.fire(
              "Başarılı!",
              `Finansal işlem başarıyla silindi.`,
              "success"
            );
          }
        });
    }
  });
});
//*********************FİNANSAL İŞLEMLER SİL*************

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//finansal işlemi ödendi yap butonuna basınca
$(document).on("click", ".finansal-islem-odendi-yap", function () {
  let finansal_islem_id = $(this).data("id");
  $("#finansal_islem_odeme_id").val(finansal_islem_id);
  $("#finansalIslemOdemeModal").modal("show");
});


//::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

//*********************FİNANSAL İŞLEMLER ÖDENDİ YAP*************
$(document).on("click", "#finansalIslemOdemeKaydet", function () {
  Swal.fire({
    title: "Emin misiniz?",
    html: `Finansal işlemi ödendi olarak işaretlemek istediğinize emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Evet",
    cancelButtonText: "Hayır",
  }).then((result) => {
    if (result.isConfirmed) {
      var formData = new FormData();
      formData.append("action", "finansal-islem-odendi-yap");
      formData.append("finansal_islem_id", $("#finansal_islem_odeme_id").val());

      // for(var pair of formData.entries()) {
      //   console.log(pair[0]+ ', ' + pair[1]);
      // }

      fetch(url, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status == "success") {
             //console.log(data);
            $("#finansalIslemOdemeModal").modal("hide");

            let table = $("#finansalIslemTable").DataTable();
            let rowNode = table.$(`tr[data-id="${finansal_islem_id}"]`)[0];
            if (rowNode) {
              table.row(rowNode).remove().draw();
              table.row.add($(data.son_kayit)).draw(false);
            }
            Swal.fire(
              "Başarılı!",
              `Finansal işlem ödendi olarak işaretlendi.`,
              "success"
            );
          }
        });
    }
  });
});
//*********************FİNANSAL İŞLEMLER ÖDENDİ YAP*************
