$(document).ready(function () {
  // Onaylayan Personel Arama (Autocomplete)
  // Dinamik içerik olduğu için event delegation kullanıyoruz
  $(document).on("input", "#onaylayan_ara", function () {
    var searchInput = $(this);
    var hiddenInput = $("#onaylayan_id");

    var container = searchInput.closest(".input-group");
    if (container.length === 0) {
      container = searchInput.parent();
    }

    // Sonuç listesi div'i yoksa oluştur
    var resultsDiv = container.find(".onaylayan-autocomplete-results");
    if (resultsDiv.length === 0) {
      resultsDiv = $(
        "<div class='list-group position-absolute w-100 onaylayan-autocomplete-results' style='z-index: 9999; max-height: 200px; overflow-y: auto; top: 100%; left: 0; right: 0;'></div>"
      );
      container.append(resultsDiv);
      container.css("position", "relative");
    }

    var term = (searchInput.val() || "").trim();
    hiddenInput.val(""); // Yazı değişirse ID'yi temizle

    if (term.length < 2) {
      resultsDiv.empty().hide();
      return;
    }

    // Debug
    console.log("Arama başlatılıyor: " + term);

    var debounceTimer = searchInput.data("debounceTimer");
    if (debounceTimer) clearTimeout(debounceTimer);

    debounceTimer = setTimeout(function () {
      var requestToken = Date.now() + ":" + term;
      searchInput.data("requestToken", requestToken);

      // Loading göster
      resultsDiv
          .empty()
          .append($("<div class='list-group-item disabled'>Aranıyor...</div>"))
          .show();

      $.ajax({
        url: "views/personel/api/APIizinler.php",
        type: "POST",
        dataType: "json",
        data: { action: "search_user", term: term },
        success: function (data) {

            console.log(data);
          if (searchInput.data("requestToken") !== requestToken) return;

          resultsDiv.empty();

          if (Array.isArray(data) && data.length > 0) {
            $.each(data, function (index, user) {
              var item = $(
                "<a href='javascript:void(0);' class='list-group-item list-group-item-action'></a>"
              )
                .html(
                  "<strong>" +
                    (user.adi_soyadi || "") +
                    "</strong> <br><small>" +
                    (user.email_adresi || "") +
                    "</small>"
                )
                .data("id", user.id)
                .data("name", user.adi_soyadi);

              item.on("click", function (e) {
                e.preventDefault();
                searchInput.val($(this).data("name"));
                hiddenInput.val($(this).data("id"));
                resultsDiv.empty().hide();
              });

              resultsDiv.append(item);
            });
            resultsDiv.show();
            return;
          }

          if (data && data.status === "error" && data.message) {
            resultsDiv
              .append(
                $("<div class='list-group-item disabled text-danger'></div>").text(
                  data.message
                )
              )
              .show();
            return;
          }

          // Sonuç yok
          resultsDiv
            .append(
                $("<div class='list-group-item disabled'>Sonuç bulunamadı</div>")
            )
            .show();
        },
        error: function (xhr, status, error) {
          if (searchInput.data("requestToken") !== requestToken) return;
          
          console.error("AJAX Hatası:", status, error, xhr.responseText);
          var msg = "Hata oluştu.";
          if(xhr.status === 404) msg = "API adresi bulunamadı (404).";
          else if(xhr.status === 500) msg = "Sunucu hatası (500).";
          
          resultsDiv
            .empty()
            .append(
              $("<div class='list-group-item disabled text-danger'></div>").text(msg)
            )
            .show();
        },
      });
    }, 300);

    searchInput.data("debounceTimer", debounceTimer);
  });

  // Dışarı tıklandığında sonuçları gizle
  $(document).on("click", function (e) {
    if (
      !$(e.target).closest("#onaylayan_ara").length &&
      !$(e.target).closest(".onaylayan-autocomplete-results").length
    ) {
      $(".onaylayan-autocomplete-results").hide();
    }
  });

  // Kaydet Butonu İşlemleri - Event Delegation
  $(document).on("click", "#btnIzinKaydet", function () {
    var btn = $(this);
    var originalText = btn.html();
    var form = $("#formIzinEkle");

    if (form.length === 0) {
      alert("Form bulunamadı!");
      return;
    }

    if ($.fn.validate) {
      form.validate({
        rules: {
          personel_id: { required: true },
          izin_tipi: { required: true },
          baslangic_tarihi: { required: true },
          bitis_tarihi: { required: true },
          onaylayan_id: { required: true },
        },
        messages: {
          personel_id: { required: "Lütfen personel seçin" },
          izin_tipi: { required: "Lütfen izin türü seçin" },
          baslangic_tarihi: { required: "Lütfen başlangıç tarihi girin" },
          bitis_tarihi: { required: "Lütfen bitiş tarihi girin" },
          onaylayan_id: { required: "Lütfen onaylayan personel seçin" },
        },
        errorElement: "span",
        highlight: function (element) {
          $(element).addClass("is-invalid");
        },
      });

      if (!form.valid()) return;
    } else {
      if (
        !$("#personel_id").val() ||
        !$("[name='izin_tipi']").val() ||
        !$("[name='baslangic_tarihi']").val() ||
        !$("[name='bitis_tarihi']").val() ||
        !$("#onaylayan_id").val()
      ) {
        alert("Lütfen zorunlu alanları doldurun.");
        return;
      }
    }

    btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader bx-spin"></i> Kaydediliyor...');

    var formData = new FormData(form[0]);
    formData.append("action", "izin_kaydet");

    $.ajax({
      url: "views/personel/api/APIizinler.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "success",
              title: "Başarılı",
              text: response.message,
              timer: 1500,
              showConfirmButton: false,
            });
          } else {
            alert(response.message);
          }

          // Modalı kapat ve formu temizle
          $("#modalIzinEkle").modal("hide");

          // Tab içeriğini yenile
          var $targetPane = $("#izinler");
          var url = $targetPane.data("url");

          if ($targetPane.length && url) {
            $targetPane.load(url, function () {
              // Select2
              if ($(".select2").length > 0 && $.fn.select2) {
                $(".modal>.select2").select2({
                  dropdownParent: $(".modal"),
                });
              }
              // Flatpickr

              $(".flatpickr").flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "tr",
              });

              // Feather
              if (typeof feather !== "undefined") {
                feather.replace();
              }
            });
          } else {
            // Tab yapısı yoksa sayfayı yenile
            location.reload();
          }
        } else {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "error",
              title: "Hata",
              text: response.message,
            });
          } else {
            alert("Hata: " + response.message);
          }
        }
      },
      error: function (xhr, status, error) {
        var errorMsg = "Bir hata oluştu.";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: errorMsg,
          });
        } else {
          alert(errorMsg);
        }
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // İzin Silme İşlemi
  $(document).on("click", ".btn-izin-sil", function () {
    var btn = $(this);
    var id = btn.data("id");
    var durum = btn.data("durum");

    if (durum !== "Beklemede") {
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "warning",
          title: "Uyarı",
          text: "Sadece bekleyen izinler silinebilir.",
        });
      } else {
        alert("Sadece bekleyen izinler silinebilir.");
      }
      return;
    }

    var confirmAction = function () {
      $.post(
        "views/personel/api/APIizinler.php",
        { action: "izin_sil", id: id },
        function (response) {
          if (response.status === "success") {
            if (typeof Swal !== "undefined") {
              Swal.fire({
                icon: "success",
                title: "Başarılı",
                text: response.message,
                timer: 1500,
                showConfirmButton: false,
              });
            } else {
              alert(response.message);
            }

            // Tab içeriğini yenile
            var $targetPane = $("#izinler");
            var url = $targetPane.data("url");

            if ($targetPane.length && url) {
              $targetPane.load(url, function () {
                if ($(".select2").length > 0 && $.fn.select2) {
                  $(".select2").select2({ dropdownParent: $(".modal") });
                }
                if (typeof flatpickr !== "undefined") {
                  $(".flatpickr-date").flatpickr({
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    time_24hr: true,
                    locale: "tr",
                  });
                }
                if (typeof feather !== "undefined") {
                  feather.replace();
                }
              });
            } else {
              location.reload();
            }
          } else {
            if (typeof Swal !== "undefined") {
              Swal.fire({
                icon: "error",
                title: "Hata",
                text: response.message,
              });
            } else {
              alert("Hata: " + response.message);
            }
          }
        },
        "json"
      ).fail(function (xhr) {
        var errorMsg = "Sunucu hatası oluştu.";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }
        if (typeof Swal !== "undefined") {
          Swal.fire({ icon: "error", title: "Hata", text: errorMsg });
        } else {
          alert(errorMsg);
        }
      });
    };

    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: "Emin misiniz?",
        text: "Bu izin kaydını silmek istediğinize emin misiniz?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Evet, Sil",
        cancelButtonText: "Vazgeç",
      }).then((result) => {
        if (result.isConfirmed) {
          confirmAction();
        }
      });
    } else {
      if (confirm("Bu izin kaydını silmek istediğinize emin misiniz?")) {
        confirmAction();
      }
    }
  });
});
