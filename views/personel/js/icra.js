$(document).ready(function () {
  // İcra Modal Aç
  $(document).on("click", "#btnOpenIcraModal", function () {
    $("#modalPersonelIcraEkle").modal("show");
  });

  // İcra Kaydet
  $(document).on("click", "#btnPersonelIcraKaydet", function () {
    var form = $("#formPersonelIcraEkle");
    if (form[0].checkValidity() === false) {
      form[0].reportValidity();
      return;
    }

    var data = form.serialize() + "&action=save_icra";

    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: data,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $("#modalPersonelIcraEkle").modal("hide");
          form[0].reset();
          refreshIcraTab();
          // Eğer Kesintiler tabı açıksa oradaki icra listesini de güncellemek gerekebilir ama şu anlık gerek yok
          Swal.fire("Başarılı", "İcra dosyası kaydedildi.", "success");
        } else {
          Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
    });
  });

  // İcra Silme
  $(document).on("click", ".btn-personel-icra-sil", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu icra dosyası silinecek!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/personel/ajax/kesinti-islemleri.php",
          type: "POST",
          data: {
            action: "delete_icra",
            id: id,
            personel_id: $('input[name="personel_id"]').val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshIcraTab();
              Swal.fire("Silindi!", "Kayıt silindi.", "success");
            } else {
              Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
            }
          },
          error: function () {
            Swal.fire("Hata", "Silme işlemi başarısız.", "error");
          },
        });
      }
    });
  });

  function refreshIcraTab() {
    var targetPane = $("#icralar");
    var url = targetPane.attr("data-url");
    if (url) {
      $.get(url, function (html) {
        targetPane.html(html);
        if (typeof initPlugins === "function") {
          initPlugins(targetPane[0]);
        }
      });
    }
  }
});
