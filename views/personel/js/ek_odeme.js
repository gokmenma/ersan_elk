$(document).ready(function () {
  // Select2 başlat (event delegation ile)
  function initEkOdemeSelect2() {
    if ($.fn.select2) {
      $("#ek_odeme_parametre_id").select2({
        dropdownParent: $("#modalPersonelEkOdemeEkle"),
        placeholder: "Ek ödeme türü seçiniz...",
        allowClear: true,
      });
    }
  }

  // Ek Ödeme Modal Aç
  $(document).on("click", "#btnOpenEkOdemeModal", function () {
    resetEkOdemeModal();
    initEkOdemeSelect2();
    $("#modalPersonelEkOdemeEkle").modal("show");
    setTimeout(function() {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, "0");
        var mm = String(today.getMonth() + 1).padStart(2, "0");
        var yyyy = today.getFullYear();
        var dateStr = dd + "." + mm + "." + yyyy;
        var dateInput = $("#ek_odeme_tarih");
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }, 100);
  });

  // Modal açıldığında bugün tarihini zorla (Flatpickr desteğiyle)
  $(document).on("shown.bs.modal", "#modalPersonelEkOdemeEkle", function () {
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;
    var dateInput = $("#ek_odeme_tarih");
    
    // Sadece eğer alan boşsa veya add modundaysak setle
    if (!dateInput.val()) {
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }
  });

  // Modal kapatılınca formu sıfırla
  $(document).on("hidden.bs.modal", "#modalPersonelEkOdemeEkle", function () {
    resetEkOdemeModal();
  });

  function resetEkOdemeModal() {
    var form = $("#formPersonelEkOdemeEkle");
    if (form.length && form[0]) {
      form[0].reset();
    }
    
    // Hidden ID inputunu temizle
    form.find('input[name="id"]').remove();
    
    // Modal başlığını ve buton metnini sıfırla
    $("#modalPersonelEkOdemeEkle .modal-title").html('<i class="bx bx-plus-circle me-2"></i>Yeni Ek Ödeme Ekle');
    $("#btnPersonelEkOdemeKaydet").html('<i class="bx bx-save me-1"></i>Kaydet');
    
    $("#ek_odeme_parametre_id").val("").trigger("change");
    $("#param_info_bar").addClass("d-none").hide();
    $("#ek_tekrar_tek_sefer").prop("checked", true);
    $("#ek_hesaplama_sabit").prop("checked", true);
    updateEkTekrarTipiUI();
    updateEkHesaplamaTipiUI();

    // Set today's date
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;

    var dateInput = $("#ek_odeme_tarih");
    dateInput.val(dateStr);
    if (dateInput[0] && dateInput[0]._flatpickr) {
      dateInput[0]._flatpickr.setDate(dateStr);
    }

    // Set default start date (1st of current month)
    var dMonth = String(today.getMonth() + 1).padStart(2, "0");
    var dYear = today.getFullYear();
    var defaultStartStr = "01." + dMonth + "." + dYear;

    var startInput = $("#ek_odeme_baslangic_donemi");
    startInput.val(defaultStartStr);
    if (startInput[0] && startInput[0]._flatpickr) {
      startInput[0]._flatpickr.setDate(defaultStartStr);
    }
    
    $("#ek_odeme_bitis_donemi").val("");
    if ($("#ek_odeme_bitis_donemi")[0] && $("#ek_odeme_bitis_donemi")[0]._flatpickr) {
      $("#ek_odeme_bitis_donemi")[0]._flatpickr.clear();
    }
  }

  // Ek Ödeme Düzenle
  $(document).on("click", ".btn-personel-ek-odeme-duzenle", function () {
    var id = $(this).data("id");
    
    $.ajax({
      url: "views/personel/ajax/ek-odeme-islemleri.php",
      type: "POST",
      data: {
        action: "get_ek_odeme",
        id: id,
        personel_id: $('#formPersonelEkOdemeEkle input[name="personel_id"]').val()
      },
      dataType: "json",
      success: function (response) {
        if (response && !response.error) {
          resetEkOdemeModal();
          initEkOdemeSelect2();
          
          var form = $("#formPersonelEkOdemeEkle");
          
          // ID ekle
          form.append('<input type="hidden" name="id" value="' + response.id + '">');
          
          // Modal başlığını güncelle
          $("#modalPersonelEkOdemeEkle .modal-title").html('<i class="bx bx-edit me-2"></i>Ek Ödeme Düzenle');
          $("#btnPersonelEkOdemeKaydet").html('<i class="bx bx-save me-1"></i>Güncelle');
          
          // Alanları doldur
          if (response.parametre_id) {
              $("#ek_odeme_parametre_id").val(response.parametre_id).trigger("change");
          } else if (response.tur) {
              // Parametre ID yoksa tür kodundan bulmaya çalış
              var option = $("#ek_odeme_parametre_id option").filter(function() {
                  return $(this).data("kod") == response.tur;
              });
              if (option.length > 0) {
                  $("#ek_odeme_parametre_id").val(option.val()).trigger("change");
              }
          }
          
          // Tekrar tipi
          if (response.tekrar_tipi === 'surekli') {
            $("#ek_tekrar_surekli").prop("checked", true);
            if (response.baslangic_donemi) {
                var bparts = response.baslangic_donemi.split("-");
                var bstr = "";
                if (bparts.length === 3) {
                    bstr = bparts[2] + "." + bparts[1] + "." + bparts[0];
                } else if (bparts.length === 2) {
                    bstr = "01." + bparts[1] + "." + bparts[0];
                }
                
                if (bstr) {
                    $("#ek_odeme_baslangic_donemi").val(bstr);
                    if ($("#ek_odeme_baslangic_donemi")[0]._flatpickr) {
                        $("#ek_odeme_baslangic_donemi")[0]._flatpickr.setDate(bstr);
                    }
                }
            }
            if (response.bitis_donemi) {
                var eparts = response.bitis_donemi.split("-");
                var estr = "";
                if (eparts.length === 3) {
                    estr = eparts[2] + "." + eparts[1] + "." + eparts[0];
                } else if (eparts.length === 2) {
                    estr = "01." + eparts[1] + "." + eparts[0];
                }

                if (estr) {
                    $("#ek_odeme_bitis_donemi").val(estr);
                    if ($("#ek_odeme_bitis_donemi")[0]._flatpickr) {
                        $("#ek_odeme_bitis_donemi")[0]._flatpickr.setDate(estr);
                    }
                }
            }
          } else {
            $("#ek_tekrar_tek_sefer").prop("checked", true);
            $("select[name='ek_odeme_donem']").val(response.donem_id).trigger('change');
          }
          updateEkTekrarTipiUI();
          
          // Hesaplama tipi (Trigger change varsayılanları getirdiği için tekrar set ediyoruz)
          setTimeout(() => {
              var h_tipi = response.hesaplama_tipi || 'sabit';
              if (h_tipi === 'sabit') {
                $("#ek_hesaplama_sabit").prop("checked", true);
                $("#formPersonelEkOdemeEkle input[name='ek_odeme_tutar']").val(response.tutar);
              } else if (h_tipi === 'oran_net') {
                $("#ek_hesaplama_oran_net").prop("checked", true);
                $("#formPersonelEkOdemeEkle input[name='oran']").val(response.oran);
              } else if (h_tipi === 'oran_brut') {
                $("#ek_hesaplama_oran_brut").prop("checked", true);
                $("#formPersonelEkOdemeEkle input[name='oran']").val(response.oran);
              }
              updateEkHesaplamaTipiUI();
          }, 100);
          
          // Tarih
          if (response.tarih) {
            var dateParts = response.tarih.split("-");
            var dateStr = dateParts[2] + "." + dateParts[1] + "." + dateParts[0];
            $("#ek_odeme_tarih").val(dateStr);
            if ($("#ek_odeme_tarih")[0]._flatpickr) {
                $("#ek_odeme_tarih")[0]._flatpickr.setDate(dateStr);
            }
          }
          
          // Açıklama
          $("#formPersonelEkOdemeEkle input[name='aciklama']").val(response.aciklama);
          
          // Modalı göster
          $("#modalPersonelEkOdemeEkle").modal("show");
        } else {
          Swal.fire("Hata", response.error || "Kayıt bulunamadı", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Veri çekilemedi", "error");
      }
    });
  });

  // Tekrar tipi değişince - EVENT DELEGATION
  $(document).on("change", 'input[name="ek_tekrar_tipi"]', function () {
    updateEkTekrarTipiUI();
  });

  function updateEkTekrarTipiUI() {
    var tekrarTipi = $('input[name="ek_tekrar_tipi"]:checked').val();
    console.log("Ek ödeme tekrar tipi değişti:", tekrarTipi); // Debug

    if (tekrarTipi === "surekli") {
      $("#ek_div_tek_sefer_donem").addClass("d-none").hide();
      $("#ek_div_surekli_baslangic, #ek_div_surekli_bitis").removeClass("d-none").show();
      $("select[name='ek_odeme_donem']").prop("required", false);
      $("#ek_odeme_baslangic_donemi").prop("required", true);
    } else {
      $("#ek_div_tek_sefer_donem").removeClass("d-none").show();
      $("#ek_div_surekli_baslangic, #ek_div_surekli_bitis").addClass("d-none").hide();
      $("select[name='ek_odeme_donem']").prop("required", true);
      $("#ek_odeme_baslangic_donemi").prop("required", false);
    }
  }

  // Hesaplama tipi değişince - EVENT DELEGATION
  $(document).on("change", 'input[name="ek_hesaplama_tipi"]', function () {
    updateEkHesaplamaTipiUI();
  });

  function updateEkHesaplamaTipiUI() {
    var hesaplamaTipi = $('input[name="ek_hesaplama_tipi"]:checked').val();
    console.log("Ek ödeme hesaplama tipi değişti:", hesaplamaTipi); // Debug

    if (hesaplamaTipi === "sabit") {
      $("#ek_div_tutar").removeClass("d-none").show();
      $("#ek_div_oran").addClass("d-none").hide();
      $("#ek_odeme_tutar").prop("required", true);
      $("#ek_odeme_oran").prop("required", false);
    } else {
      $("#ek_div_tutar").addClass("d-none").hide();
      $("#ek_div_oran").removeClass("d-none").show();
      $("#ek_odeme_tutar").prop("required", false);
      $("#ek_odeme_oran").prop("required", true);
    }
  }

  // Parametre seçilince - EVENT DELEGATION
  $(document).on("change", "#ek_odeme_parametre_id", function () {
    var selected = $(this).find("option:selected");
    var hes_key = selected.data("hesaplama") || "";
    var hes_etiket = selected.data("hesaplama-etiket") || "Sabit Tutar";
    var oran = selected.data("oran") || 0;
    var tutar = selected.data("tutar") || 0;
    var sgk = selected.data("sgk");
    var gv = selected.data("gv");
    var dv = selected.data("dv");

    console.log("Ek ödeme parametre seçildi:", hes_key, hes_etiket, oran, tutar, sgk, gv, dv); // Debug

    if (selected.val() != "") {
        $("#param_info_bar").removeClass("d-none").fadeIn();
        
        var h_text = hes_etiket;
        var v_text = tutar + " ₺";
        
        if (hes_key.includes("oran_bazli_net") || hes_key === "oran_net") {
            v_text = "%" + oran;
            $("#ek_hesaplama_oran_net").prop("checked", true);
            $("#ek_odeme_oran").val(oran);
        } else if (hes_key.includes("oran_bazli_brut") || hes_key === "oran_brut") {
            v_text = "%" + oran;
            $("#ek_hesaplama_oran_brut").prop("checked", true);
            $("#ek_odeme_oran").val(oran);
        } else {
            $("#ek_hesaplama_sabit").prop("checked", true);
            $("#ek_odeme_tutar").val(tutar);
        }
        
        $("#info_hesaplama").text(h_text);
        $("#info_deger").text(v_text);

        // Vergi / SGK Ayarları
        updateInfoBadge("#info_sgk", sgk, "SGK");
        updateInfoBadge("#info_gv", gv, "GV");
        updateInfoBadge("#info_dv", dv, "DV");

    } else {
        $("#param_info_bar").addClass("d-none").hide();
    }
    
    updateEkHesaplamaTipiUI();
  });

  function updateInfoBadge(selector, value, label) {
    var el = $(selector);
    if (value == 1) {
        el.removeClass("bg-light text-dark").addClass("bg-success text-white border-success");
        el.html('<i class="bx bx-check me-1"></i>' + label);
        el.attr("title", label + " Matrahına Dahil");
    } else {
        el.removeClass("bg-success text-white border-success").addClass("bg-light text-muted border");
        el.html('<i class="bx bx-x me-1"></i>' + label);
        el.attr("title", label + " Matrahına Dahil Değil");
    }
  }

  // Ek Ödeme Kaydet
  $(document).on("click", "#btnPersonelEkOdemeKaydet", function () {
    var form = $("#formPersonelEkOdemeEkle");

    // Manuel validasyon
    var parametreId = $("#ek_odeme_parametre_id").val();
    if (!parametreId) {
      Swal.fire("Hata", "Lütfen ek ödeme türü seçiniz.", "error");
      return;
    }

    var tekrarTipi = $('input[name="ek_tekrar_tipi"]:checked').val();
    var hesaplamaTipi = $('input[name="ek_hesaplama_tipi"]:checked').val();

    // Tek seferlik ise dönem zorunlu
    if (tekrarTipi === "tek_sefer") {
      var donem = $("select[name='ek_odeme_donem']").val();
      if (!donem) {
        Swal.fire("Hata", "Lütfen dönem seçiniz.", "error");
        return;
      }
    } else {
      // Sürekli ise başlangıç dönemi zorunlu
      var baslangicDonemi = $("#ek_odeme_baslangic_donemi").val();
      if (!baslangicDonemi) {
        Swal.fire("Hata", "Lütfen başlangıç dönemini giriniz.", "error");
        return;
      }
    }

    // Sabit tutarda tutar zorunlu
    if (hesaplamaTipi === "sabit") {
      var tutar = $("#formPersonelEkOdemeEkle input[name='ek_odeme_tutar']").val();
      if (!tutar || parseFloat(tutar) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir tutar giriniz.", "error");
        return;
      }
    } else {
      // Oran bazlı ise oran zorunlu
      var oran = $("#formPersonelEkOdemeEkle input[name='oran']").val();
      if (!oran || parseFloat(oran) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir oran giriniz.", "error");
        return;
      }
    }

    // Tür kodunu al
    var turKod =
      $("#ek_odeme_parametre_id").find("option:selected").data("kod") ||
      "diger";

    // Güncelleme kontrolü
    var idInput = form.find('input[name="id"]');
    var action = idInput.length > 0 ? "update_ek_odeme" : "save_ek_odeme";

    var data = {
      action: action,
      personel_id: $('input[name="personel_id"]').val(),
      parametre_id: parametreId,
      tur: turKod,
      tekrar_tipi: tekrarTipi,
      hesaplama_tipi: hesaplamaTipi,
      tutar:
        hesaplamaTipi === "sabit"
          ? $("#formPersonelEkOdemeEkle input[name='ek_odeme_tutar']").val()
          : 0,
      oran:
        hesaplamaTipi !== "sabit"
          ? $("#formPersonelEkOdemeEkle input[name='oran']").val()
          : 0,
      tarih: $("#ek_odeme_tarih").val(),
      aciklama: $("#formPersonelEkOdemeEkle input[name='aciklama']").val(),
    };

    // Update ise ID ekle
    if (action === "update_ek_odeme") {
      data.id = idInput.val();
    }

    // Tarih kontrolü
    if (!data.tarih) {
      Swal.fire("Hata", "Lütfen tarih seçiniz.", "error");
      return;
    }

    // Dönem bilgisi
    if (tekrarTipi === "tek_sefer") {
      data.donem_id = $("select[name='ek_odeme_donem']").val();
    } else {
      data.baslangic_donemi = $("#ek_odeme_baslangic_donemi").val();
      data.bitis_donemi = $("#ek_odeme_bitis_donemi").val() || null;
    }

    $.ajax({
      url: "views/personel/ajax/ek-odeme-islemleri.php",
      type: "POST",
      data: data,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $("#modalPersonelEkOdemeEkle").modal("hide");
          refreshEkOdemeTab();
          Swal.fire("Başarılı", "Ek ödeme kaydedildi.", "success");
        } else {
          Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
    });
  });

  // Sürekli Ödemeyi Sonlandır
  $(document).on("click", ".btn-personel-ek-odeme-sonlandir", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Sürekli Ödemeyi Sonlandır",
      html:
        "<p>Bu sürekli ödeme bu dönemden itibaren sonlandırılacak.</p>" +
        '<div class="mb-3"><label class="form-label">Bitiş Dönemi</label>' +
        '<input type="month" class="form-control" id="swal_bitis_donemi_ek" value="' +
        new Date().toISOString().slice(0, 7) +
        '"></div>',
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sonlandır",
      cancelButtonText: "İptal",
      preConfirm: () => {
        return document.getElementById("swal_bitis_donemi_ek").value;
      },
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        $.ajax({
          url: "views/personel/ajax/ek-odeme-islemleri.php",
          type: "POST",
          data: {
            action: "sonlandir_ek_odeme",
            id: id,
            bitis_donemi: result.value,
            personel_id: $('input[name="personel_id"]').val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshEkOdemeTab();
              Swal.fire("Başarılı!", "Ek ödeme sonlandırıldı.", "success");
            } else {
              Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
            }
          },
          error: function () {
            Swal.fire("Hata", "İşlem başarısız.", "error");
          },
        });
      }
    });
  });

  // Silme İşlemleri
  $(document).on("click", ".btn-personel-ek-odeme-sil", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu ek ödeme kaydı silinecek!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, sil",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/personel/ajax/ek-odeme-islemleri.php",
          type: "POST",
          data: {
            action: "delete_ek_odeme",
            id: id,
            personel_id: $('input[name="personel_id"]').val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshEkOdemeTab();
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

  function refreshEkOdemeTab() {
    var targetPane = $("#ek_odemeler");
    var url = targetPane.attr("data-url");
    if (url) {
      $.get(url, function (html) {
        targetPane.html(html);
        if (typeof initPlugins === "function") {
          initPlugins(targetPane[0]);
        }
      });
    } else {
      // Fallback - sayfayı yenile
      location.reload();
    }
  }
});
