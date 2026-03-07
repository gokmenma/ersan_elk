/**
 * Personel Evrakları JavaScript
 * AJAX ile yüklenen evraklar tabı için event handler'lar
 */

(function ($) {
  "use strict";

  // Evrak modülünü başlat
  function initEvrakModule() {
    //console.log("Evrak modülü başlatılıyor...");
    bindEvrakEvents();
  }

  // Event'leri bağla
  function bindEvrakEvents() {
    // Modal Açma - Event Delegation
    $(document)
      .off("click.evrak", "#btnOpenEvrakModal")
      .on("click.evrak", "#btnOpenEvrakModal", function (e) {
        e.preventDefault();
        console.log("Evrak modal açılıyor...");

        var form = document.getElementById("formEvrakYukle");
        if (form) form.reset();

        var progressEl = document.getElementById("uploadProgress");
        if (progressEl) progressEl.classList.add("d-none");

        var modalEl = document.getElementById("modalEvrakYukle");
        if (modalEl) {
          var modal = new bootstrap.Modal(modalEl);
          modal.show();

          // Select2'yi modal içinde başlat
          $("#evrak_turu").select2({
            dropdownParent: $("#modalEvrakYukle"),
            width: "100%",
            tags: true,
          });
        } else {
          console.error("Modal element bulunamadı: modalEvrakYukle");
          Swal.fire("Hata", "Modal açılamadı.", "error");
        }
      });

    // Evrak Yükle Form Submit
    $(document)
      .off("submit.evrak", "#formEvrakYukle")
      .on("submit.evrak", "#formEvrakYukle", function (e) {
        e.preventDefault();
        console.log("Evrak form submit edildi");

        var form = $(this);
        var formData = new FormData(form[0]);
        var progressBar = $("#uploadProgress");
        var progressBarInner = progressBar.find(".progress-bar");
        var submitBtn = $("#btnEvrakKaydet");

        // Validasyon
        var evrakAdi = $("#evrak_adi").val().trim();
        var evrakTuru = $("#evrak_turu").val();
        var dosyaInput = document.getElementById("evrak_dosyasi");
        var dosya = dosyaInput && dosyaInput.files[0];

        if (!evrakAdi) {
          Swal.fire("Uyarı", "Evrak adı zorunludur.", "warning");
          return;
        }

        if (!evrakTuru) {
          Swal.fire("Uyarı", "Evrak türü seçiniz.", "warning");
          return;
        }

        if (!dosya) {
          Swal.fire("Uyarı", "Lütfen bir dosya seçiniz.", "warning");
          return;
        }

        // Dosya boyutu kontrolü (10MB)
        if (dosya.size > 10 * 1024 * 1024) {
          Swal.fire("Uyarı", "Dosya boyutu 10MB'ı geçemez.", "warning");
          return;
        }

        // Progress bar göster
        progressBar.removeClass("d-none");
        submitBtn
          .prop("disabled", true)
          .html('<i class="bx bx-loader-alt bx-spin me-1"></i>Yükleniyor...');

        // AJAX ile yükle
        $.ajax({
          url: "views/personel/api/APIevraklar.php",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          xhr: function () {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener(
              "progress",
              function (e) {
                if (e.lengthComputable) {
                  var percent = Math.round((e.loaded / e.total) * 100);
                  progressBarInner
                    .css("width", percent + "%")
                    .text(percent + "%");
                }
              },
              false,
            );
            return xhr;
          },
          success: function (response) {
            if (typeof response === "string") {
              try {
                response = JSON.parse(response);
              } catch (e) {
                console.error("JSON parse error:", e);
                Swal.fire("Hata", "Sunucu yanıtı geçersiz.", "error");
                return;
              }
            }

            if (response.status === "success") {
              var modalEl = document.getElementById("modalEvrakYukle");
              var modalInstance = bootstrap.Modal.getInstance(modalEl);
              if (modalInstance) modalInstance.hide();

              Swal.fire("Başarılı", response.message, "success").then(
                function () {
                  // Evraklar tabını yenile
                  if (typeof window.reloadActiveTab === "function") {
                    window.reloadActiveTab();
                  } else {
                    $('a[href="#evraklar"]').trigger("click");
                  }
                },
              );
            } else {
              Swal.fire("Hata", response.message, "error");
            }
          },
          error: function (xhr, status, error) {
            console.error("Upload Error:", error, xhr.responseText);
            Swal.fire("Hata", "Dosya yüklenirken bir hata oluştu.", "error");
          },
          complete: function () {
            submitBtn
              .prop("disabled", false)
              .html('<i class="bx bx-upload me-1"></i>Yükle');
            progressBar.addClass("d-none");
            progressBarInner.css("width", "0%").text("0%");
          },
        });
      });

    // Evrak Görüntüle
    $(document)
      .off("click.evrak", ".btn-evrak-goruntule")
      .on("click.evrak", ".btn-evrak-goruntule", function () {
        var dosya = $(this).data("dosya");
        var tip = $(this).data("tip");
        var ad = $(this).data("ad");

        $("#evrakGoruntuleBaslik").text(ad);
        $("#evrakIndirLink").attr("href", dosya);

        var icerik = $("#evrakIcerik");
        icerik.empty();

        if (tip && tip.indexOf("pdf") !== -1) {
          icerik.html(
            '<embed src="' +
              dosya +
              '" type="application/pdf" width="100%" height="500px">',
          );
        } else if (tip && tip.indexOf("image") !== -1) {
          icerik.html(
            '<img src="' +
              dosya +
              '" class="img-fluid" alt="' +
              ad +
              '" style="max-height: 500px;">',
          );
        } else {
          icerik.html(
            '<div class="p-5"><i class="bx bx-file display-1 text-muted"></i><p class="mt-3">Bu dosya türü önizlenemez.<br>Lütfen dosyayı indirin.</p></div>',
          );
        }

        var modalEl = document.getElementById("modalEvrakGoruntule");
        if (modalEl) {
          var modal = new bootstrap.Modal(modalEl);
          modal.show();
        }
      });

    // Evrak Sil
    $(document)
      .off("click.evrak", ".btn-evrak-sil")
      .on("click.evrak", ".btn-evrak-sil", function () {
        var id = $(this).data("id");
        var ad = $(this).data("ad");
        var row = $(this).closest("tr");

        Swal.fire({
          title: "Emin misiniz?",
          html:
            "<strong>" +
            ad +
            "</strong> adlı evrakı silmek istediğinizden emin misiniz?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#d33",
          cancelButtonColor: "#3085d6",
          confirmButtonText: "Evet, Sil!",
          cancelButtonText: "İptal",
        }).then(function (result) {
          if (result.isConfirmed) {
            $.ajax({
              url: "views/personel/api/APIevraklar.php",
              type: "POST",
              data: {
                action: "evrak_sil",
                id: id,
              },
              dataType: "json",
              success: function (response) {
                if (response.status === "success") {
                  row.fadeOut(300, function () {
                    $(this).remove();
                    if ($("#tblEvraklar tbody tr").length === 0) {
                      $("#tblEvraklar tbody").html(
                        '<tr id="noEvrakRow">' +
                          '<td colspan="6" class="text-center py-4">' +
                          '<i class="bx bx-folder-open display-6 text-muted d-block mb-2"></i>' +
                          '<span class="text-muted">Bu personele henüz evrak yüklenmemiş.</span>' +
                          "</td></tr>",
                      );
                    }
                  });
                  Swal.fire("Silindi!", response.message, "success");
                } else {
                  Swal.fire("Hata!", response.message, "error");
                }
              },
              error: function () {
                Swal.fire("Hata!", "İşlem sırasında bir hata oluştu.", "error");
              },
            });
          }
        });
      });

    // Tooltip'leri etkinleştir
    var tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]'),
    );
    tooltipTriggerList.forEach(function (el) {
      new bootstrap.Tooltip(el);
    });

    //console.log("Evrak modülü event'leri bağlandı.");
  }

  // Document ready'de başlat
  $(document).ready(function () {
    initEvrakModule();
  });

  // Sayfa AJAX ile yüklendiğinde de çalışması için global fonksiyon
  window.initEvrakModule = initEvrakModule;
})(jQuery);
