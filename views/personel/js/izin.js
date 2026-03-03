$(document).ready(function () {
  // Onaylayan Personel Arama (Autocomplete)
  // Dinamik içerik olduğu için event delegation kullanıyoruz
  $(document)
    .off("input.izinler", "#onaylayan_ara")
    .on("input.izinler", "#onaylayan_ara", function () {
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
          "<div class='list-group position-absolute w-100 onaylayan-autocomplete-results' style='z-index: 9999; max-height: 200px; overflow-y: auto; top: 100%; left: 0; right: 0;'></div>",
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
                  "<a href='javascript:void(0);' class='list-group-item list-group-item-action'></a>",
                )
                  .html(
                    "<strong>" +
                      (user.adi_soyadi || "") +
                      "</strong> <br><small>" +
                      (user.email_adresi || "") +
                      "</small>",
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
                  $(
                    "<div class='list-group-item disabled text-danger'></div>",
                  ).text(data.message),
                )
                .show();
              return;
            }

            // Sonuç yok
            resultsDiv
              .append(
                $(
                  "<div class='list-group-item disabled'>Sonuç bulunamadı</div>",
                ),
              )
              .show();
          },
          error: function (xhr, status, error) {
            if (searchInput.data("requestToken") !== requestToken) return;

            console.error("AJAX Hatası:", status, error, xhr.responseText);
            var msg = "Hata oluştu.";
            if (xhr.status === 404) msg = "API adresi bulunamadı (404).";
            else if (xhr.status === 500) msg = "Sunucu hatası (500).";

            resultsDiv
              .empty()
              .append(
                $(
                  "<div class='list-group-item disabled text-danger'></div>",
                ).text(msg),
              )
              .show();
          },
        });
      }, 300);

      searchInput.data("debounceTimer", debounceTimer);
    });

  // Dışarı tıklandığında sonuçları gizle
  // Dışarı tıklandığında sonuçları gizle
  $(document)
    .off("click.izinler_hide")
    .on("click.izinler_hide", function (e) {
      if (
        !$(e.target).closest("#onaylayan_ara").length &&
        !$(e.target).closest(".onaylayan-autocomplete-results").length
      ) {
        $(".onaylayan-autocomplete-results").hide();
      }
    });

  // Kaydet Butonu İşlemleri - Event Delegation
  // Kaydet Butonu İşlemleri - Event Delegation
  $(document)
    .off("click.izinler", "#btnIzinKaydet")
    .on("click.izinler", "#btnIzinKaydet", function () {
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
            onaylayan_id: {
              required: function () {
                return $('[name="onay_durumu"]').val() !== "Beklemede";
              },
            },
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
      // id alanının formda olduğundan emin olalım, eğer yoksa veya 0 ise insert, varsa update
      if (!formData.has("id")) {
        formData.append("id", $("#izin_id").val() || 0);
      }

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

            if (typeof reloadActiveTab === "function") {
              reloadActiveTab();
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
  // İzin Silme İşlemi
  $(document)
    .off("click.izinler", ".btn-izin-sil")
    .on("click.izinler", ".btn-izin-sil", function () {
      var btn = $(this);
      var id = btn.data("id");
      var durum = btn.data("durum");

      var onayaTabi = btn.data("onaya-tabi") == 1;

      if (durum !== "Beklemede" && onayaTabi) {
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "warning",
            title: "Uyarı",
            text: "Yetkili onayından geçmiş kayıtlar silinemez.",
          });
        } else {
          alert("Yetkili onayından geçmiş kayıtlar silinemez.");
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

              if (typeof reloadActiveTab === "function") {
                reloadActiveTab();
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
          "json",
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

  // İzin Onayla/Reddet/Detay İşlemleri - Event Delegation
  const TALEPLER_API = "views/talepler/api.php";

  // İzin Onayla Butonu
  // İzin Onayla Butonu
  $(document)
    .off("click.izinler", ".btn-izin-onayla")
    .on("click.izinler", ".btn-izin-onayla", function () {
      const btn = $(this);
      const id = btn.data("id");
      const personel = btn.data("personel");
      const tur = btn.data("tur");
      const gun = btn.data("gun");

      $("#izin_onay_id").val(id);
      $("#izin_onay_personel").text(personel);
      $("#izin_onay_tur").text(tur);
      $("#izin_onay_gun").text(gun);

      $("#modalIzinOnayPersonel").modal("show");
    });

  // İzin Reddet Butonu
  // İzin Reddet Butonu
  $(document)
    .off("click.izinler", ".btn-izin-reddet")
    .on("click.izinler", ".btn-izin-reddet", function () {
      const btn = $(this);
      const id = btn.data("id");
      const personel = btn.data("personel");

      $("#izin_red_id").val(id);
      $("#izin_red_personel").text(personel);

      $("#modalIzinRedPersonel").modal("show");
    });

  // İzin Detay Butonu
  $(document)
    .off("click.izinler", ".btn-izin-detay")
    .on("click.izinler", ".btn-izin-detay", function () {
      const id = $(this).data("id");
      loadIzinDetay(id);
    });

  // İzin Düzenle Butonu
  $(document)
    .off("click.izinler", ".btn-izin-duzenle")
    .on("click.izinler", ".btn-izin-duzenle", function () {
      const btn = $(this);
      const data = btn.data("json");

      if (!data) return;

      $("#formIzinEkle")[0].reset();
      $("#izin_id").val(data.id);

      // Ücret durumu
      const ucretliMi = data.ucretli_mi !== undefined ? data.ucretli_mi : 1;
      $('[name="izin_ucret_durumu"][value="' + ucretliMi + '"]')
        .prop("checked", true)
        .trigger("change");

      // İzin tipi (Select dolmuş olmalı)
      setTimeout(function () {
        $('[name="izin_tipi"]').val(data.izin_tipi_id).trigger("change");
      }, 200);

      // Tarihleri doldur
      function setFpDate(name, dateStr) {
        if (!dateStr || dateStr.startsWith("0000")) return;
        var form = $("#formIzinEkle");
        var el = form.find('[name="' + name + '"]')[0];

        var d = null;
        if (dateStr.includes(".")) {
          var parts = dateStr.split(" ")[0].split(".");
          d = new Date(parts[2], parts[1] - 1, parts[0]);
          if (dateStr.includes(":")) {
            var t = dateStr.split(" ")[1].split(":");
            d.setHours(t[0] || 0, t[1] || 0);
          }
        } else {
          d = new Date(dateStr.replace(/-/g, "/"));
        }

        if (d && !isNaN(d.getTime())) {
          if (el && el._flatpickr) {
            el._flatpickr.setDate(d, true);
          } else {
            var day = String(d.getDate()).padStart(2, "0");
            var month = String(d.getMonth() + 1).padStart(2, "0");
            var year = d.getFullYear();
            var formatted = day + "." + month + "." + year;
            if (name == "onay_tarihi") {
              var h = String(d.getHours()).padStart(2, "0");
              var m = String(d.getMinutes()).padStart(2, "0");
              formatted += " " + h + ":" + m;
            }
            $(el).val(formatted);
          }
        }
      }

      setFpDate("baslangic_tarihi", data.baslangic_tarihi);
      setFpDate("bitis_tarihi", data.bitis_tarihi);
      setFpDate("onay_tarihi", data.onay_tarihi);

      // Diğer alanlar
      $('[name="sure"]').val(data.toplam_gun || data.sure || "");
      $('[name="aciklama"]').val(data.aciklama || "");

      if (data.izin_durumu) {
        $('[name="izin_durumu"]').val(data.izin_durumu).trigger("change");
      }
      if (data.yillik_izne_etki !== undefined) {
        $('[name="yillik_izne_etki"]')
          .val(data.yillik_izne_etki)
          .trigger("change");
      }
      if (data.bordroya_aktar) {
        $('[name="bordroya_aktar"]').val(data.bordroya_aktar).trigger("change");
      }

      // Onay bilgileri
      $('[name="onay_durumu"]')
        .val(data.son_durum || data.onay_durumu || "Beklemede")
        .trigger("change");
      $("#onaylayan_id").val(data.onaylayan_id || "");

      // Onaylayan arama alanına isim yaz
      if (data.onaylayan_adi_soyadi) {
        $("#onaylayan_ara").val(data.onaylayan_adi_soyadi);
      } else if (data.onaylar && data.onaylar.length > 0) {
        $("#onaylayan_ara").val(data.onaylar[data.onaylar.length - 1].adi);
      }

      $("#onay_aciklama").val(data.onay_aciklama || "");

      $("#modalIzinEkleLabel").text("İzin Düzenle");
      $("#modalIzinEkle").modal("show");
    });

  // Yeni İzin Ekle modalı açıldığında label'ı düzelt ve id'yi sıfırla
  $(document).on("click", '[data-bs-target="#modalIzinEkle"]', function () {
    if (!$(this).hasClass("btn-izin-duzenle")) {
      $("#formIzinEkle")[0].reset();
      $("#izin_id").val(0);
      $("#modalIzinEkleLabel").text("Yeni Ekle");
      // Varsayılanları ayarla
      $('[name="izin_ucret_durumu"][value="1"]')
        .prop("checked", true)
        .trigger("change");
      $('[name="onay_durumu"]').val("Beklemede").trigger("change");
    }
  });

  // İzin Onay Form Submit
  // İzin Onay Form Submit
  $(document)
    .off("submit.izinler", "#formIzinOnayPersonel")
    .on("submit.izinler", "#formIzinOnayPersonel", function (e) {
      e.preventDefault();
      const form = $(this);
      const formData = new FormData(form[0]);
      formData.append("action", "izin-onayla");

      $.ajax({
        url: TALEPLER_API,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Başarılı",
              text: response.message || "İzin onaylandı.",
            }).then((result) => {
              $("#modalIzinOnayPersonel").modal("hide");
              if (typeof reloadActiveTab === "function") {
                reloadActiveTab();
              } else {
                location.reload();
              }
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Hata",
              text: response.message || "Bir hata oluştu.",
            });
          }
        },
        error: function () {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: "Sunucu hatası oluştu.",
          });
        },
      });
    });

  // İzin Red Form Submit
  // İzin Red Form Submit
  $(document)
    .off("submit.izinler", "#formIzinRedPersonel")
    .on("submit.izinler", "#formIzinRedPersonel", function (e) {
      e.preventDefault();
      const form = $(this);
      const formData = new FormData(form[0]);
      formData.append("action", "izin-reddet");

      $.ajax({
        url: TALEPLER_API,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Başarılı",
              text: response.message || "İzin reddedildi.",
            }).then(() => {
              $("#modalIzinRedPersonel").modal("hide");
              if (typeof reloadActiveTab === "function") {
                reloadActiveTab();
              } else {
                location.reload();
              }
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Hata",
              text: response.message || "Bir hata oluştu.",
            });
          }
        },
        error: function () {
          Swal.fire({
            icon: "error",
            title: "Hata",
            text: "Sunucu hatası oluştu.",
          });
        },
      });
    });

  // İzin Detay Yükle Fonksiyonu
  function loadIzinDetay(id) {
    const detayContent = $("#izinDetayContent");
    detayContent.html(
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>',
    );

    $("#modalIzinDetayPersonel").modal("show");

    $.post(
      TALEPLER_API,
      { action: "get-izin-detay", id: id },
      function (response) {
        if (response.status === "success") {
          const izin = response.data;
          let html = `
          <div class="row">
              <div class="col-md-6">
                  <table class="table table-sm table-borderless">
                      <tr>
                          <th class="text-muted" style="width: 40%;">İzin Türü:</th>
                          <td><span class="badge bg-info">${izin.izin_tipi_adi || izin.izin_tipi || "-"}</span></td>
                      </tr>
                      <tr>
                          <th class="text-muted">Başlangıç Tarihi:</th>
                          <td>${formatDate(izin.baslangic_tarihi)}</td>
                      </tr>
                      <tr>
                          <th class="text-muted">Bitiş Tarihi:</th>
                          <td>${formatDate(izin.bitis_tarihi)}</td>
                      </tr>
                      <tr>
                          <th class="text-muted">Süre:</th>
                          <td><span class="badge bg-secondary">${izin.sure || "-"} Gün</span></td>
                      </tr>
                  </table>
              </div>
              <div class="col-md-6">
                  <table class="table table-sm table-borderless">
                      <tr>
                          <th class="text-muted" style="width: 40%;">Durum:</th>
                          <td>${getDurumBadge(izin.son_durum || izin.onay_durumu || "Beklemede")}</td>
                      </tr>
                      <tr>
                          <th class="text-muted">İzin Durumu:</th>
                          <td>${izin.izin_durumu || "-"}</td>
                      </tr>
                      <tr>
                          <th class="text-muted">Yıllık İzne Etki:</th>
                          <td>${izin.yillik_izne_etki || "-"}</td>
                      </tr>
                      <tr>
                          <th class="text-muted">Bordroya Aktar:</th>
                          <td>${izin.bordroya_aktar || "-"}</td>
                      </tr>
                  </table>
              </div>
          </div>
          ${
            izin.aciklama
              ? `
          <div class="mt-3">
              <h6 class="text-muted"><i class="bx bx-message-detail me-1"></i> Açıklama</h6>
              <p class="mb-0 p-2 bg-light rounded">${izin.aciklama}</p>
          </div>
          `
              : ""
          }
          ${
            izin.onay_aciklama
              ? `
          <div class="mt-3">
              <h6 class="text-muted"><i class="bx bx-check-circle me-1"></i> Onay Açıklaması</h6>
              <p class="mb-0 p-2 bg-light rounded">${izin.onay_aciklama}</p>
          </div>
          `
              : ""
          }
        `;
          detayContent.html(html);
        } else {
          detayContent.html(
            `<div class="alert alert-danger">${response.message || "Detay yüklenemedi."}</div>`,
          );
        }
      },
      "json",
    ).fail(function () {
      detayContent.html(
        '<div class="alert alert-danger">Sunucu hatası oluştu.</div>',
      );
    });
  }

  // Yardımcı Fonksiyonlar
  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleDateString("tr-TR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  }

  function getDurumBadge(durum) {
    const badges = {
      Onaylandı: '<span class="badge bg-success">Onaylandı</span>',
      Reddedildi: '<span class="badge bg-danger">Reddedildi</span>',
      Beklemede: '<span class="badge bg-warning">Beklemede</span>',
      Bekliyor: '<span class="badge bg-warning">Bekliyor</span>',
    };
    return badges[durum] || `<span class="badge bg-secondary">${durum}</span>`;
  }
  // Tarih Hesaplama ve Kontrol
  function calculateDuration() {
    var modal = $("#modalIzinEkle");
    var startDateVal = modal.find('[name="baslangic_tarihi"]').val();
    var endDateVal = modal.find('[name="bitis_tarihi"]').val();

    if (!startDateVal || !endDateVal) return;

    // Tarihleri parse et (d.m.Y veya Y-m-d formatını destekle)
    function parseDate(str) {
      if (!str) return null;
      if (str.includes(".")) {
        var parts = str.split(".");
        if (parts.length === 3) {
          // d.m.Y -> Y, m-1, d
          return new Date(parts[2], parts[1] - 1, parts[0]);
        }
      } else if (str.includes("-")) {
        return new Date(str);
      }
      return new Date(str);
    }

    var startDate = parseDate(startDateVal);
    var endDate = parseDate(endDateVal);

    if (startDate && endDate && !isNaN(startDate) && !isNaN(endDate)) {
      // Farkı hesapla
      var timeDiff = endDate.getTime() - startDate.getTime();
      var dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;

      if (dayDiff < 0) dayDiff = 0;

      modal.find('[name="sure"]').val(dayDiff);

      // Yıllık İzin Kontrolü
      var izinTuruSelect = modal.find('[name="izin_tipi"]');
      var izinTuru = izinTuruSelect.find("option:selected").text();
      // Eğer select2 ise ve text gelmiyorsa container'dan almayı dene
      if (!izinTuru && izinTuruSelect.data("select2")) {
        izinTuru = izinTuruSelect.select2("data")[0].text;
      }

      if (izinTuru && izinTuru.includes("Yıllık İzin")) {
        var kalanIzinStr = $("#kalan_izin_gun").text().trim();
        // "14 Gün" veya "14,5 Gün" gibi olabilir. Sadece sayıları alalım.
        var kalanIzin = parseFloat(
          kalanIzinStr.replace(",", ".").replace(/[^\d.-]/g, ""),
        );

        if (!isNaN(kalanIzin) && dayDiff > kalanIzin) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "warning",
              title: "Yetersiz Hakediş",
              text:
                "Talep edilen izin süresi (" +
                dayDiff +
                " gün), kalan hakedişinizden (" +
                kalanIzin +
                " gün) fazladır!",
              confirmButtonText: "Anladım",
            });
          } else {
            alert(
              "Talep edilen izin süresi (" +
                dayDiff +
                " gün), kalan hakedişinizden (" +
                kalanIzin +
                " gün) fazladır!",
            );
          }
        }
      }
    }
  }

  // İzin Ücret Durumu Değiştiğinde İzin Türlerini Filtrele
  $(document).on("change", 'input[name="izin_ucret_durumu"]', function () {
    var ucretliMi = $(this).val();
    var select = $('select[name="izin_tipi"]');
    var currentVal = select.val();

    select.empty();

    if (typeof allIzinTurleri !== "undefined") {
      var filtered = allIzinTurleri.filter(function (item) {
        return item.ucretli_mi == ucretliMi;
      });

      $.each(filtered, function (index, item) {
        var option = new Option(item.tur_adi, item.id, false, false);
        select.append(option);
      });

      // Eğer select2 ise tetikle
      if (select.hasClass("select2-hidden-accessible")) {
        select.trigger("change");
      }
    }
  });

  // Tarih senkronizasyonu (Başlangıç değişince bitişi aynı yap)
  $(document)
    .off("change.izin_sync input.izin_sync", "#modalIzinEkle #baslangic_tarihi")
    .on(
      "change.izin_sync input.izin_sync",
      "#modalIzinEkle #baslangic_tarihi",
      function () {
        var startVal = $(this).val();

        if (startVal) {
          var endInput = $("#bitis_tarihi");
          if (endInput.length > 0) {
            if (endInput[0]._flatpickr) {
              endInput[0]._flatpickr.set("minDate", startVal);

              var currentEnd = endInput.val();
              // Eğer bitiş tarihi boşsa VEYA başlangıçtan küçükse güncelle
              if (!currentEnd || parseDate(currentEnd) < parseDate(startVal)) {
                endInput[0]._flatpickr.setDate(startVal, true);
              }
            } else {
              var currentEnd = endInput.val();
              if (!currentEnd || parseDate(currentEnd) < parseDate(startVal)) {
                endInput.val(startVal).trigger("change");
              }
            }
          }
        }
        calculateDuration();
      },
    );

  // Bitiş tarihi veya izin tipi değiştiğinde süreyi hesapla
  $(document)
    .off(
      "change.izin_calc",
      "#modalIzinEkle #bitis_tarihi, #modalIzinEkle [name='izin_tipi']",
    )
    .on(
      "change.izin_calc",
      "#modalIzinEkle #bitis_tarihi, #modalIzinEkle [name='izin_tipi']",
      function () {
        calculateDuration();
      },
    );

  function parseDate(str) {
    if (!str) return null;
    if (str.includes(".")) {
      var parts = str.split(".");
      if (parts.length === 3) {
        return new Date(parts[2], parts[1] - 1, parts[0]);
      }
    } else if (str.includes("-")) {
      var parts = str.split("-");
      if (parts.length === 3) {
        return new Date(parts[0], parts[1] - 1, parts[2]);
      }
    }
    return new Date(str);
  }
});
