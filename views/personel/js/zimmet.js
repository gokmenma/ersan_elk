$(document).ready(function () {
  // =============================================
  // PERSONEL ZİMMET İŞLEMLERİ (Event Delegation)
  // =============================================

  // Yeni Zimmet Modal Aç
  $(document).on("click", "#btnOpenZimmetModal", function () {
    console.log("Zimmet modal açılıyor...");
    $("#modalPersonelZimmetEkle").modal("show");
  });

  // Demirbaş seçildiğinde kalan miktarı göster
  $(document).on("change", "#personel_demirbas_id", function () {
    var kalan = $(this).find(":selected").data("kalan") || 0;
    console.log("Seçilen demirbaş kalan:", kalan);
    $("#personelKalanMiktar").text(kalan);
    $("#personel_teslim_miktar").attr("max", kalan).val(1);
  });

  // Yeni Zimmet Kaydet
  $(document).on("click", "#btnPersonelZimmetKaydet", function () {
    console.log("Zimmet kaydet tıklandı");

    var btn = $(this);
    var originalText = btn.html();
    var form = $("#formPersonelZimmetEkle");

    if (form.length === 0) {
      console.error("Form bulunamadı!");
      return;
    }

    var demirbasId = $("#personel_demirbas_id").val();
    var teslimMiktar = parseInt($("#personel_teslim_miktar").val()) || 1;
    var kalan =
      parseInt($("#personel_demirbas_id").find(":selected").data("kalan")) || 0;

    console.log(
      "Demirbaş ID:",
      demirbasId,
      "Miktar:",
      teslimMiktar,
      "Kalan:",
      kalan
    );

    if (!demirbasId) {
      Swal.fire("Uyarı", "Lütfen demirbaş seçiniz.", "warning");
      return;
    }

    if (teslimMiktar > kalan) {
      Swal.fire(
        "Uyarı",
        "Stokta sadece " + kalan + " adet bulunmaktadır.",
        "warning"
      );
      return;
    }

    btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader bx-spin"></i> Kaydediliyor...');

    var formData = new FormData(form[0]);
    formData.append("action", "zimmet-kaydet");

    $.ajax({
      url: "views/demirbas/api.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        console.log("API Response:", response);
        if (response.status === "success") {
          $("#modalPersonelZimmetEkle").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          });

          // Tab içeriğini yenile
          var $targetPane = $("#zimmetler");
          var url = $targetPane.data("url");

          if ($targetPane.length && url) {
            $targetPane.load(url, function () {
              if (typeof feather !== "undefined") {
                feather.replace();
              }
            });
          } else {
            location.reload();
          }
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Hatası:", status, error, xhr.responseText);
        Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // İade Modal Aç
  $(document).on("click", ".btn-personel-zimmet-iade", function () {
    var id = $(this).data("id");
    var demirbas = $(this).data("demirbas");
    var miktar = $(this).data("miktar");

    console.log("İade modal açılıyor:", id, demirbas, miktar);

    $("#personel_iade_zimmet_id").val(id);
    $("#personel_iade_demirbas_adi").text(demirbas);
    $("#personel_iade_miktar_goster").text(miktar);
    $("#personel_iade_miktar").val(miktar).attr("max", miktar);

    $("#modalPersonelIade").modal("show");
  });

  // İade Kaydet
  $(document).on("click", "#btnPersonelIadeKaydet", function () {
    console.log("İade kaydet tıklandı");

    var btn = $(this);
    var originalText = btn.html();
    var form = $("#formPersonelIade");

    btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader bx-spin"></i> Kaydediliyor...');

    var formData = new FormData(form[0]);
    formData.append("action", "zimmet-iade");

    $.ajax({
      url: "views/demirbas/api.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        console.log("İade API Response:", response);
        if (response.status === "success") {
          $("#modalPersonelIade").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          });

          // Tab içeriğini yenile
          var $targetPane = $("#zimmetler");
          var url = $targetPane.data("url");

          if ($targetPane.length && url) {
            $targetPane.load(url, function () {
              if (typeof feather !== "undefined") {
                feather.replace();
              }
            });
          } else {
            location.reload();
          }
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Hatası:", status, error, xhr.responseText);
        Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // Zimmet Sil
  $(document).on("click", ".btn-personel-zimmet-sil", function () {
    var btn = $(this);
    var id = btn.data("id");
    var row = btn.closest("tr");

    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu zimmet kaydını silmek istediğinizden emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Evet, Sil!",
      cancelButtonText: "İptal",
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/demirbas/api.php",
          type: "POST",
          data: { action: "zimmet-sil", id: id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              row.fadeOut(300, function () {
                $(this).remove();
              });
              Swal.fire("Silindi!", response.message, "success");
            } else {
              Swal.fire("Hata!", response.message, "error");
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX Hatası:", status, error);
            Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
          },
        });
      }
    });
  });

  console.log("Zimmet.js yüklendi.");
});
