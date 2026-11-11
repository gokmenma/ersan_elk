$(function () {
  const api = window.SOZLESME_SABLON_API;

  function hata(mesaj) {
    Swal.fire("Hata", mesaj || "İşlem başarısız.", "error");
  }

  $(document).on("click", ".sablon-kod", function () {
    const kod = $(this).data("kod");
    const bitti = function () {
      Swal.fire({ toast: true, position: "top-end", icon: "success", title: kod + " kopyalandı", showConfirmButton: false, timer: 1500 });
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(kod).then(bitti);
    }
  });

  $("#btnSablonYukle").on("click", function () {
    const form = $("#sablonYukleForm")[0];
    if (!form.sablon_dosyasi.files.length) {
      Swal.fire("Uyarı", "Lütfen bir .docx dosyası seçin.", "warning");
      return;
    }
    const $btn = $(this).prop("disabled", true);
    $.ajax({ url: api, type: "POST", data: new FormData(form), processData: false, contentType: false, dataType: "json" })
      .done(function (res) {
        $btn.prop("disabled", false);
        if (res && res.status === "success") {
          Swal.fire("Başarılı", res.message, "success").then(() => location.reload());
        } else {
          hata(res && res.message);
        }
      })
      .fail(function () {
        $btn.prop("disabled", false);
        hata("Sunucu ile iletişim kurulamadı.");
      });
  });

  function onayla(metin, veri) {
    Swal.fire({ title: "Emin misiniz?", text: metin, icon: "question", showCancelButton: true, confirmButtonText: "Evet", cancelButtonText: "Vazgeç" }).then((r) => {
      if (!r.isConfirmed) return;
      $.post(api, veri, null, "json")
        .done(function (res) {
          if (res && res.status === "success") {
            Swal.fire("Başarılı", res.message, "success").then(() => location.reload());
          } else {
            hata(res && res.message);
          }
        })
        .fail(function () {
          hata("Sunucu ile iletişim kurulamadı.");
        });
    });
  }

  $(document).on("click", ".sablon-aktif-et", function () {
    onayla("Bu sürüm sözleşme indirmelerinde kullanılacak.", { action: "sablon-aktif-et", id: $(this).data("id") });
  });

  $(document).on("click", ".sablon-varsayilana-don", function () {
    onayla("Sistem varsayılan şablonu sözleşme indirmelerinde kullanılacak.", { action: "sablon-varsayilana-don" });
  });
});
