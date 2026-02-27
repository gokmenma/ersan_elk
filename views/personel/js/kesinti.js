$(document).ready(function () {
  // Select2 başlat (event delegation ile)
  function initKesintiSelect2() {
    if ($.fn.select2) {
      $("#kesinti_parametre_id").select2({
        dropdownParent: $("#modalPersonelKesintiEkle"),
        placeholder: "Kesinti türü seçiniz...",
        allowClear: true,
      });

      $("#kesinti_icra_id").select2({
        dropdownParent: $("#modalPersonelKesintiEkle"),
        placeholder: "İcra dosyası seçiniz...",
        allowClear: true,
      });
    }
  }

  // Kesinti Modal Aç
  $(document).on("click", "#btnOpenKesintiModal", function () {
    resetKesintiModal();
    initKesintiSelect2();
    $("#modalPersonelKesintiEkle").modal("show");
    setTimeout(function() {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, "0");
        var mm = String(today.getMonth() + 1).padStart(2, "0");
        var yyyy = today.getFullYear();
        var dateStr = dd + "." + mm + "." + yyyy;
        var dateInput = $("#kesinti_tarih");
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }, 100);
  });
  
  // Modal açıldığında bugün tarihini zorla (Flatpickr desteğiyle)
  $(document).on("shown.bs.modal", "#modalPersonelKesintiEkle", function () {
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;
    var dateInput = $("#kesinti_tarih");
    
    // Sadece eğer alan boşsa veya add modundaysak setle
    if (!dateInput.val()) {
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }
  });

  // Modal kapatılınca formu sıfırla
  $(document).on("hidden.bs.modal", "#modalPersonelKesintiEkle", function () {
    resetKesintiModal();
  });

  function resetKesintiModal() {
    var form = $("#formPersonelKesintiEkle");
    if (form.length && form[0]) {
      form[0].reset();
    }
    
    // Hidden ID inputunu temizle
    form.find('input[name="id"]').remove();
    
    // Modal başlığını ve buton metnini sıfırla
    $("#modalPersonelKesintiEkle .modal-title").html('<i class="bx bx-minus-circle me-2"></i>Yeni Kesinti Ekle');
    $("#btnPersonelKesintiKaydet").html('<i class="bx bx-save me-1"></i>Kaydet');
    
    $("#kesinti_parametre_id").val("").trigger("change");
    $("#tekrar_tek_sefer").prop("checked", true);
    $("#hesaplama_sabit").prop("checked", true);
    updateTekrarTipiUI();
    updateHesaplamaTipiUI();
    $("#div_icra_secimi").addClass("d-none");
    $("#div_ucretsiz_izin_secenek").addClass("d-none");
    $("#div_kesinti_gun").addClass("d-none");
    $("#div_tutar").removeClass("d-none");
    $("#kesinti_tip_tutar").prop("checked", true);

    // Set today's date
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;

    var dateInput = $("#kesinti_tarih");
    dateInput.val(dateStr);
    if (dateInput[0] && dateInput[0]._flatpickr) {
      dateInput[0]._flatpickr.setDate(dateStr);
    }
  }

  // Kesinti Düzenle
  $(document).on("click", ".btn-personel-kesinti-duzenle", function () {
    var id = $(this).data("id");
    
    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: {
        action: "get_kesinti",
        id: id,
        personel_id: $('#formPersonelKesintiEkle input[name="personel_id"]').val()
      },
      dataType: "json",
      success: function (response) {
        if (response && !response.error) {
          resetKesintiModal();
          initKesintiSelect2();
          
          var form = $("#formPersonelKesintiEkle");
          
          // ID ekle
          form.append('<input type="hidden" name="id" value="' + response.id + '">');
          
          // Modal başlığını güncelle
          $("#modalPersonelKesintiEkle .modal-title").html('<i class="bx bx-edit me-2"></i>Kesinti Düzenle');
          $("#btnPersonelKesintiKaydet").html('<i class="bx bx-save me-1"></i>Güncelle');
          
          // Alanları doldur
          if (response.parametre_id) {
              $("#kesinti_parametre_id").val(response.parametre_id).trigger("change");
          } else if (response.tur) {
              // Parametre ID yoksa tür kodundan bulmaya çalış
              var option = $("#kesinti_parametre_id option").filter(function() {
                  return $(this).data("kod") == response.tur;
              });
              if (option.length > 0) {
                  $("#kesinti_parametre_id").val(option.val()).trigger("change");
              }
          }
          
          // İcra ise
          if (response.icra_id) {
             // İcra dosyalarını bekle ve seç
             setTimeout(function() {
                 $("#kesinti_icra_id").val(response.icra_id).trigger("change");
             }, 500);
          }
          
          // Tekrar tipi
          if (response.tekrar_tipi === 'surekli') {
            $("#tekrar_surekli").prop("checked", true);
            $("#kesinti_baslangic_donemi").val(response.baslangic_donemi);
            $("#kesinti_bitis_donemi").val(response.bitis_donemi);
          } else {
            $("#tekrar_tek_sefer").prop("checked", true);
            $("select[name='kesinti_donem']").val(response.donem_id).trigger('change');
          }
          updateTekrarTipiUI();
          
          // Hesaplama tipi (Trigger change varsayılanları getirdiği için tekrar set ediyoruz)
          setTimeout(() => {
              if (response.hesaplama_tipi === 'sabit') {
                $("#hesaplama_sabit").prop("checked", true);
                $("#kesinti_tutar").val(response.tutar);
              } else if (response.hesaplama_tipi === 'oran_net') {
                $("#hesaplama_oran_net").prop("checked", true);
                $("#formPersonelKesintiEkle input[name='oran']").val(response.oran);
              } else if (response.hesaplama_tipi === 'oran_brut') {
                $("#hesaplama_oran_brut").prop("checked", true);
                $("#formPersonelKesintiEkle input[name='oran']").val(response.oran);
              }
              updateHesaplamaTipiUI();
          }, 100);
          
          // Tarih
          if (response.tarih) {
            var dateParts = response.tarih.split("-");
            var dateStr = dateParts[2] + "." + dateParts[1] + "." + dateParts[0];
            $("#kesinti_tarih").val(dateStr);
            if ($("#kesinti_tarih")[0]._flatpickr) {
                $("#kesinti_tarih")[0]._flatpickr.setDate(dateStr);
            }
          }
          
          // Açıklama
          $("#formPersonelKesintiEkle input[name='aciklama']").val(response.aciklama);
          
          // Modalı göster
          $("#modalPersonelKesintiEkle").modal("show");
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
  $(document).on("change", 'input[name="tekrar_tipi"]', function () {
    updateTekrarTipiUI();
  });

  function updateTekrarTipiUI() {
    var tekrarTipi = $('input[name="tekrar_tipi"]:checked').val();
    console.log("Tekrar tipi değişti:", tekrarTipi); // Debug

    if (tekrarTipi === "surekli") {
      $("#div_tek_sefer_donem").addClass("d-none").hide();
      $("#div_surekli_donem").removeClass("d-none").show();
      $("select[name='kesinti_donem']").prop("required", false);
      $("#kesinti_baslangic_donemi").prop("required", true);
    } else {
      $("#div_tek_sefer_donem").removeClass("d-none").show();
      $("#div_surekli_donem").addClass("d-none").hide();
      $("select[name='kesinti_donem']").prop("required", true);
      $("#kesinti_baslangic_donemi").prop("required", false);
    }
  }

  // Hesaplama tipi değişince - EVENT DELEGATION
  $(document).on("change", 'input[name="hesaplama_tipi"]', function () {
    updateHesaplamaTipiUI();
  });

  function updateHesaplamaTipiUI() {
    var hesaplamaTipi = $('input[name="hesaplama_tipi"]:checked').val();
    console.log("Hesaplama tipi değişti:", hesaplamaTipi); // Debug

    if (hesaplamaTipi === "sabit") {
      $("#div_tutar").removeClass("d-none").show();
      $("#div_oran").addClass("d-none").hide();
      $("#kesinti_tutar").prop("required", true);
      $("#formPersonelKesintiEkle input[name='oran']").prop("required", false);
    } else {
      $("#div_tutar").addClass("d-none").hide();
      $("#div_oran").removeClass("d-none").show();
      $("#kesinti_tutar").prop("required", false);
      $("#formPersonelKesintiEkle input[name='oran']").prop("required", true);
    }
  }

  // Parametre seçilince - EVENT DELEGATION
  $(document).on("change", "#kesinti_parametre_id", function () {
    var selected = $(this).find("option:selected");
    var kod = selected.data("kod");
    var hesaplama = selected.data("hesaplama") || "";
    var oran = selected.data("oran") || 0;
    var tutar = selected.data("tutar") || 0;
    var personel_id = $('input[name="personel_id"]').val();

    console.log("Parametre seçildi:", kod, hesaplama, oran, tutar); // Debug

    // İcra seçildiyse icra dosyalarını getir
    if (kod === "icra") {
      $("#div_icra_secimi").removeClass("d-none").show();
      loadIcraDosyalari(personel_id);
    } else {
      $("#div_icra_secimi").addClass("d-none").hide();
      $("#kesinti_icra_id").val("");
    }

    // Hesaplama tipini otomatik ayarla
    if (hesaplama.includes("oran_bazli_net") || hesaplama === "oran_net") {
      $("#hesaplama_oran_net").prop("checked", true);
      if (oran > 0) {
        $("#formPersonelKesintiEkle input[name='oran']").val(oran);
      }
    } else if (
      hesaplama.includes("oran_bazli_brut") ||
      hesaplama === "oran_brut"
    ) {
      $("#hesaplama_oran_brut").prop("checked", true);
      if (oran > 0) {
        $("#formPersonelKesintiEkle input[name='oran']").val(oran);
      }
    } else {
      $("#hesaplama_sabit").prop("checked", true);
      if (tutar > 0) {
        $("#kesinti_tutar").val(tutar);
      }
    }
    updateHesaplamaTipiUI();

    // Ücretsiz İzin özel mantığı
    if (kod === "izin_kesinti") {
      $("#div_ucretsiz_izin_secenek").removeClass("d-none");
      const maasDurumu = window.personelData ? window.personelData.maas_durumu : "";

      if (maasDurumu === "Prim Usulü") {
        $("#kesinti_tip_gun").prop("disabled", true);
        $("label[for='kesinti_tip_gun']").addClass("d-none");
        $("#kesinti_tip_tutar").prop("checked", true).trigger("change");
      } else {
        $("#kesinti_tip_gun").prop("disabled", false);
        $("label[for='kesinti_tip_gun']").removeClass("d-none");
      }
    } else {
      $("#div_ucretsiz_izin_secenek").addClass("d-none");
      $("#div_kesinti_gun").addClass("d-none");
      $("#div_tutar").removeClass("d-none");
    }
  });

  // Kesinti Tipi (Tutar/Gün) Değişince
  $(document).on("change", "input[name='rad_kesinti_tip']", function () {
    const tip = $(this).val();
    if (tip === "gun") {
      $("#div_kesinti_gun").removeClass("d-none");
      $("#div_tutar").addClass("d-none");
      $("input[name='tutar']").prop("required", false);
      $("#kesinti_gun_sayisi").prop("required", true).focus();
    } else {
      $("#div_kesinti_gun").addClass("d-none");
      $("#div_tutar").removeClass("d-none");
      $("input[name='tutar']").prop("required", true);
      $("#kesinti_gun_sayisi").prop("required", false);
    }
  });

  // Gün Sayısı Değişince Tutar Hesapla
  $(document).on("input", "#kesinti_gun_sayisi", function () {
    const gun = parseFloat($(this).val()) || 0;
    const maas = window.personelData ? window.personelData.maas_tutari : 0;

    if (gun > 0 && maas > 0) {
      const gunluk = maas / 30;
      const toplam = (gunluk * gun).toFixed(2);
      $("input[name='tutar']").val(toplam);
    } else {
      $("input[name='tutar']").val(0);
    }
  });

  // İcra dosyalarını yükle
  function loadIcraDosyalari(personel_id) {
    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: {
        action: "get_icralar",
        personel_id: personel_id,
      },
      dataType: "json",
      success: function (response) {
        var options = '<option value="">Dosya seçiniz...</option>';
        if (response && response.length > 0) {
          $("#no_icra_warning").hide();
          $.each(response, function (i, item) {
            options +=
              '<option value="' +
              item.id +
              '" data-tutar="' +
              item.aylik_kesinti_tutari +
              '">' +
              item.icra_dairesi +
              " - " +
              item.dosya_no +
              " (Aylık: " +
              item.aylik_kesinti_tutari +
              " TL)</option>";
          });
        } else {
          $("#no_icra_warning").show();
        }
        $("#kesinti_icra_id").html(options);
      },
    });
  }

  $(document).on("change", "#kesinti_icra_id", function () {
    var selected = $(this).find("option:selected");
    var tutar = selected.data("tutar");
    if (tutar) {
      $("#kesinti_tutar").val(tutar);
      // İcra her zaman sabit tutar
      $("#hesaplama_sabit").prop("checked", true);
      updateHesaplamaTipiUI();
    }
  });

  // Kesinti Kaydet
  $(document).on("click", "#btnPersonelKesintiKaydet", function () {
    var form = $("#formPersonelKesintiEkle");

    // Manuel validasyon
    var parametreId = $("#kesinti_parametre_id").val();
    if (!parametreId) {
      Swal.fire("Hata", "Lütfen kesinti türü seçiniz.", "error");
      return;
    }

    var tekrarTipi = $('input[name="tekrar_tipi"]:checked').val();
    var hesaplamaTipi = $('input[name="hesaplama_tipi"]:checked').val();

    // Tek seferlik ise dönem zorunlu
    if (tekrarTipi === "tek_sefer") {
      var donem = $("select[name='kesinti_donem']").val();
      if (!donem) {
        Swal.fire("Hata", "Lütfen dönem seçiniz.", "error");
        return;
      }
    } else {
      // Sürekli ise başlangıç dönemi zorunlu
      var baslangicDonemi = $("#kesinti_baslangic_donemi").val();
      if (!baslangicDonemi) {
        Swal.fire("Hata", "Lütfen başlangıç dönemini giriniz.", "error");
        return;
      }
    }

    // Sabit tutarda tutar zorunlu
    if (hesaplamaTipi === "sabit") {
      var tutar = $("#kesinti_tutar").val();
      if (!tutar || parseFloat(tutar) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir tutar giriniz.", "error");
        return;
      }
    } else {
      // Oran bazlı ise oran zorunlu
      var oran = form.find("input[name='oran']").val();
      if (!oran || parseFloat(oran) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir oran giriniz.", "error");
        return;
      }
    }

    // Tür kodunu al
    var turKod =
      $("#kesinti_parametre_id").find("option:selected").data("kod") || "diger";

    // Güncelleme kontrolü
    var idInput = form.find('input[name="id"]');
    var action = idInput.length > 0 ? "update_kesinti" : "save_kesinti";

    var data = {
      action: action,
      personel_id: $('input[name="personel_id"]').val(),
      parametre_id: parametreId,
      tur: turKod,
      tekrar_tipi: tekrarTipi,
      hesaplama_tipi: hesaplamaTipi,
      tutar: hesaplamaTipi === "sabit" ? $("#kesinti_tutar").val() : 0,
      oran: hesaplamaTipi !== "sabit" ? form.find("input[name='oran']").val() : 0,
      tarih: $("#kesinti_tarih").val(),
      aciklama: form.find("input[name='aciklama']").val(),
      icra_id: $("#kesinti_icra_id").val() || null,
    };

    // Update ise ID ekle
    if (action === "update_kesinti") {
      data.id = idInput.val();
    }

    // Tarih kontrolü
    if (!data.tarih) {
      Swal.fire("Hata", "Lütfen tarih seçiniz.", "error");
      return;
    }

    // Dönem bilgisi
    if (tekrarTipi === "tek_sefer") {
      data.donem_id = $("select[name='kesinti_donem']").val();
    } else {
      data.baslangic_donemi = $("#kesinti_baslangic_donemi").val();
      data.bitis_donemi = $("#kesinti_bitis_donemi").val() || null;
    }

    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: data,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $("#modalPersonelKesintiEkle").modal("hide");
          refreshKesintiTab();
          Swal.fire("Başarılı", "Kesinti kaydedildi.", "success");
        } else {
          Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
    });
  });

  // Kesinti Onayla
  $(document).on("click", ".btn-personel-kesinti-onayla", function () {
    var id = $(this).data("id");
    console.log("Kesinti Onayla - ID:", id); // Debug

    Swal.fire({
      title: "Kesintiyi Onayla",
      text: "Bu kesinti onaylanacak ve maaş hesaplamasına dahil edilecek.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Onayla",
      cancelButtonText: "İptal",
      confirmButtonColor: "#28a745",
    }).then((result) => {
      if (result.isConfirmed) {
        console.log("Gönderilen data:", {
          action: "kesinti-onayla",
          kesinti_id: id,
        }); // Debug

        $.ajax({
          url: "views/personel/api.php",
          type: "POST",
          data: {
            action: "kesinti-onayla",
            kesinti_id: id,
          },
          dataType: "json",
          success: function (response) {
            console.log("API Response:", response); // Debug

            if (response.status === "success") {
              refreshKesintiTab();
              Swal.fire("Onaylandı!", "Kesinti onaylandı.", "success");
            } else {
              Swal.fire("Hata", response.message || "Bir hata oluştu", "error");
            }
          },
          error: function (xhr, status, error) {
            console.log("AJAX Error:", xhr.responseText, status, error); // Debug
            Swal.fire("Hata", "İşlem başarısız.", "error");
          },
        });
      }
    });
  });

  // Kesinti Reddet
  $(document).on("click", ".btn-personel-kesinti-reddet", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Kesintiyi Reddet",
      text: "Bu kesinti reddedilecek ve maaş hesaplamasına dahil edilmeyecek.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Reddet",
      cancelButtonText: "İptal",
      confirmButtonColor: "#dc3545",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/personel/api.php",
          type: "POST",
          data: {
            action: "kesinti-reddet",
            kesinti_id: id,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              refreshKesintiTab();
              Swal.fire("Reddedildi!", "Kesinti reddedildi.", "success");
            } else {
              Swal.fire("Hata", response.message || "Bir hata oluştu", "error");
            }
          },
          error: function () {
            Swal.fire("Hata", "İşlem başarısız.", "error");
          },
        });
      }
    });
  });

  // Sürekli Kesintiyi Sonlandır
  $(document).on("click", ".btn-personel-kesinti-sonlandir", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Sürekli Kesintiyi Sonlandır",
      html:
        "<p>Bu sürekli kesinti bu dönemden itibaren sonlandırılacak.</p>" +
        '<div class="mb-3"><label class="form-label">Bitiş Dönemi</label>' +
        '<input type="month" class="form-control" id="swal_bitis_donemi" value="' +
        new Date().toISOString().slice(0, 7) +
        '"></div>',
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sonlandır",
      cancelButtonText: "İptal",
      preConfirm: () => {
        return document.getElementById("swal_bitis_donemi").value;
      },
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        $.ajax({
          url: "views/personel/ajax/kesinti-islemleri.php",
          type: "POST",
          data: {
            action: "sonlandir_kesinti",
            id: id,
            bitis_donemi: result.value,
            personel_id: $('input[name="personel_id"]').val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshKesintiTab();
              Swal.fire("Başarılı!", "Kesinti sonlandırıldı.", "success");
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
  $(document).on("click", ".btn-personel-kesinti-sil", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu kesinti kaydı silinecek!",
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
            action: "delete_kesinti",
            id: id,
            personel_id: $('input[name="personel_id"]').val(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshKesintiTab();
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

  function refreshKesintiTab() {
    var targetPane = $("#kesintiler");
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
