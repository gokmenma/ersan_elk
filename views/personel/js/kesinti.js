$(document).ready(function () {
  // Select2 başlat (event delegation ile)
  function initKesintiSelect2() {
    if ($.fn.select2) {
      var modal = $("#modalPersonelKesintiEkle");
      var paramSelect = modal.length ? modal.find("#kesinti_parametre_id") : $("#kesinti_parametre_id");
      if (paramSelect.length) {
        if (paramSelect.hasClass("select2-hidden-accessible")) {
          try { paramSelect.select2('destroy'); } catch(e){}
        }
        paramSelect.select2({
          dropdownParent: modal.length ? modal : $(document.body),
          placeholder: "Kesinti türü seçiniz...",
          allowClear: true,
        });

        paramSelect.off("select2:select.sync").on("select2:select.sync", function (e) {
          if (e.params && e.params.data && e.params.data.id) {
            $(this).val(e.params.data.id);
          }
        });
      }

      var icraSelect = modal.length ? modal.find("#kesinti_icra_id") : $("#kesinti_icra_id");
      if (icraSelect.length) {
        if (icraSelect.hasClass("select2-hidden-accessible")) {
          try { icraSelect.select2('destroy'); } catch(e){}
        }
        icraSelect.select2({
          dropdownParent: modal.length ? modal : $(document.body),
          placeholder: "İcra dosyası seçiniz...",
          allowClear: true,
        });
      }
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
        var dateInput = $("#modalPersonelKesintiEkle").find("#kesinti_tarih");
        if (!dateInput.length) dateInput = $("#kesinti_tarih");
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }, 100);
  });
  
  // Modal açıldığında bugün tarihini ve UI durumlarını zorla
  $(document).on("shown.bs.modal", "#modalPersonelKesintiEkle", function () {
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;
    var modal = $(this);
    var dateInput = modal.find("#kesinti_tarih");
    
    // Sadece eğer alan boşsa veya add modundaysak setle
    if (!dateInput.val()) {
        dateInput.val(dateStr);
        if (dateInput[0] && dateInput[0]._flatpickr) {
            dateInput[0]._flatpickr.setDate(dateStr);
        }
    }

    updateTekrarTipiUI();
    updateHesaplamaTipiUI();
    updateKesintiKanalUI();
    updateKesintiTipUI();
  });

  // Modal kapatılınca formu sıfırla
  $(document).on("hidden.bs.modal", "#modalPersonelKesintiEkle", function () {
    resetKesintiModal();
  });

  function resetKesintiModal() {
    var modal = $("#modalPersonelKesintiEkle");
    var form = modal.find("#formPersonelKesintiEkle");
    if (!form.length) form = $("#formPersonelKesintiEkle");
    if (form.length && form[0]) {
      form[0].reset();
    }
    
    // Hidden ID inputunu temizle
    form.find('input[name="id"]').remove();
    
    // Modal başlığını ve buton metnini sıfırla
    modal.find(".modal-title").text("Yeni Kesinti Ekle");
    $("#btnPersonelKesintiKaydet span").text("Kaydet");
    
    var paramSelect = modal.find("#kesinti_parametre_id");
    if (!paramSelect.length) paramSelect = $("#kesinti_parametre_id");
    paramSelect.val("").trigger("change");
    
    form.find('input[name="tekrar_tipi"]').prop("checked", false).removeAttr("checked");
    form.find("#tekrar_tek_sefer").prop("checked", true).attr("checked", "checked");
    
    form.find('input[name="hesaplama_tipi"]').prop("checked", false).removeAttr("checked");
    form.find("#hesaplama_sabit").prop("checked", true).attr("checked", "checked");
    
    form.find('input[name="banka_matrahina_ekle"]').prop("checked", false).removeAttr("checked");
    form.find("#kesinti_banka_matrah_evet").prop("checked", true).attr("checked", "checked");
    
    form.find('input[name="rad_kesinti_tip"]').prop("checked", false).removeAttr("checked");
    form.find("#kesinti_tip_tutar").prop("checked", true).attr("checked", "checked");
    
    // UI Sıfırla
    updateTekrarTipiUI();
    updateHesaplamaTipiUI();
    updateKesintiKanalUI();
    updateKesintiTipUI();
    modal.find("#param_info_bar").addClass("d-none");
    modal.find("#div_icra_secimi").addClass("d-none");
    modal.find("#div_ucretsiz_izin_secenek").addClass("d-none");
    modal.find("#div_kesinti_gun").addClass("d-none");
    modal.find("#div_taksit_sayisi").addClass("d-none");
    modal.find("#kesinti_taksit_sayisi").val(1);
    modal.find("#div_tutar").removeClass("d-none");

    // Set today's date
    var today = new Date();
    var dd = String(today.getDate()).padStart(2, "0");
    var mm = String(today.getMonth() + 1).padStart(2, "0");
    var yyyy = today.getFullYear();
    var dateStr = dd + "." + mm + "." + yyyy;

    var dateInput = modal.find("#kesinti_tarih");
    dateInput.val(dateStr);
    if (dateInput[0] && dateInput[0]._flatpickr) {
      dateInput[0]._flatpickr.setDate(dateStr);
    }
  }

  // Kesinti Düzenle
  $(document).on("click", ".btn-personel-kesinti-duzenle", function () {
    var id = $(this).data("id");
    var personelId = $('input[name="personel_id"]').val() || $('#formPersonelKesintiEkle input[name="personel_id"]').val() || '';
    
    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: {
        action: "get_kesinti",
        id: id,
        personel_id: personelId
      },
      dataType: "json",
      success: function (response) {
        if (response && !response.error) {
          resetKesintiModal();
          initKesintiSelect2();
          
          var modal = $("#modalPersonelKesintiEkle");
          var form = modal.find("#formPersonelKesintiEkle");
          if (!form.length) form = $("#formPersonelKesintiEkle");
          
          // ID ekle
          form.find('input[name="id"]').remove();
          form.append('<input type="hidden" name="id" value="' + response.id + '">');
          
          // Modal başlığını güncelle
          modal.find(".modal-title").html('<i class="bx bx-edit me-2"></i>Kesinti Düzenle');
          $("#btnPersonelKesintiKaydet span").text("Güncelle");
          
          // Parametre seç
          var paramSelect = modal.find("#kesinti_parametre_id");
          if (!paramSelect.length) paramSelect = $("#kesinti_parametre_id");

          var targetParamId = response.parametre_id;
          if (!targetParamId && response.tur) {
              var opt = paramSelect.find("option").filter(function() {
                  return String($(this).data("kod")).toLowerCase() === String(response.tur).toLowerCase()
                      || $(this).text().trim().toLowerCase() === String(response.tur).toLowerCase()
                      || String($(this).val()) === String(response.tur);
              });
              if (opt.length > 0) {
                  targetParamId = opt.val();
              }
          }
          if (targetParamId) {
              paramSelect.val(String(targetParamId)).trigger("change");
              paramSelect.trigger({
                  type: 'select2:select',
                  params: {
                      data: { id: String(targetParamId) }
                  }
              });
          }
          
          // İcra ise
          if (response.icra_id) {
             setTimeout(function() {
                 modal.find("#kesinti_icra_id").val(response.icra_id).trigger("change");
             }, 300);
          }
          
          // Tekrar tipi
          form.find('input[name="tekrar_tipi"]').prop("checked", false).removeAttr("checked");
          if (response.tekrar_tipi === 'surekli') {
            form.find("#tekrar_surekli").prop("checked", true).attr("checked", "checked");
            modal.find("#baslangic_donemi").val(response.baslangic_donemi || '');
            modal.find("#bitis_donemi").val(response.bitis_donemi || '');
          } else if (response.tekrar_tipi === 'taksitli') {
            form.find("#tekrar_taksitli").prop("checked", true).attr("checked", "checked");
            modal.find("select[name='kesinti_donem']").val(response.donem_id).trigger('change');
            modal.find("#kesinti_taksit_sayisi").val(response.taksit_sayisi || 1);
          } else {
            form.find("#tekrar_tek_sefer").prop("checked", true).attr("checked", "checked");
            modal.find("select[name='kesinti_donem']").val(response.donem_id).trigger('change');
          }
          updateTekrarTipiUI();
          
          // Hesaplama tipi ve Değerler
          form.find('input[name="hesaplama_tipi"]').prop("checked", false).removeAttr("checked");
          var h_tipi = response.hesaplama_tipi || 'sabit';
          if (h_tipi === 'sabit') {
            form.find("#hesaplama_sabit").prop("checked", true).attr("checked", "checked");
            form.find("input[name='kesinti_tutar']").val(response.tutar || '');
          } else if (h_tipi === 'oran_net') {
            form.find("#hesaplama_oran_net").prop("checked", true).attr("checked", "checked");
            form.find("input[name='oran']").val(response.oran || '');
          } else if (h_tipi === 'oran_brut') {
            form.find("#hesaplama_oran_brut").prop("checked", true).attr("checked", "checked");
            form.find("input[name='oran']").val(response.oran || '');
          }
          updateHesaplamaTipiUI();
          
          // Tarih
          if (response.tarih) {
            var dateStr = response.tarih;
            if (dateStr.indexOf("-") !== -1) {
              var dateParts = dateStr.split("-");
              dateStr = dateParts[2] + "." + dateParts[1] + "." + dateParts[0];
            }
            var dateInp = modal.find("#kesinti_tarih");
            dateInp.val(dateStr);
            if (dateInp[0] && dateInp[0]._flatpickr) {
                dateInp[0]._flatpickr.setDate(dateStr);
            }
          }
          
          // Açıklama
          form.find("input[name='aciklama']").val(response.aciklama || '');

          // Banka Matrahı / Kanalı
          form.find('input[name="banka_matrahina_ekle"]').prop("checked", false).removeAttr("checked");
          if (response.banka_matrahina_ekle !== undefined && parseInt(response.banka_matrahina_ekle) === 0) {
            form.find("#kesinti_banka_matrah_hayir").prop("checked", true).attr("checked", "checked");
          } else {
            form.find("#kesinti_banka_matrah_evet").prop("checked", true).attr("checked", "checked");
          }
          updateKesintiKanalUI();
          
          // Modalı göster
          modal.modal("show");
        } else {
          Swal.fire("Hata", response.error || "Kayıt bulunamadı", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Veri çekilemedi", "error");
      }
    });
  });

  // Kesinti Kanalı Değişince Bilgilendirme Metnini Güncelle
  $(document).on("change", 'input[name="banka_matrahina_ekle"]', function () {
    updateKesintiKanalUI();
  });

  function updateKesintiKanalUI() {
    var modal = $("#modalPersonelKesintiEkle");
    var container = modal.length 
      ? modal.find('input[name="banka_matrahina_ekle"]').closest('.segmented-control-container')
      : $('input[name="banka_matrahina_ekle"]').closest('.segmented-control-container');
    
    if (!container.length) return;

    var checkedInput = container.find('input[name="banka_matrahina_ekle"]:checked');
    if (!checkedInput.length) {
      checkedInput = container.find('#kesinti_banka_matrah_evet');
      checkedInput.prop('checked', true).attr('checked', 'checked');
    }
    var val = checkedInput.val() !== undefined ? checkedInput.val() : "1";

    container.find('.segmented-control-input').not(checkedInput).prop('checked', false).removeAttr('checked');
    container.find('.segmented-control-label').removeClass('active');
    if (checkedInput.attr('id')) {
      container.find('label[for="' + checkedInput.attr('id') + '"]').addClass('active');
    }

    if (val === "0") {
      $("#kesinti_kanal_bilgi_metin").html('<strong>Elden Seçilirse:</strong> Kesinti tutarı öncelikle elden ödeme tutarından düşülür. Elden tutarın yetmediği durumda kalan bakiye banka ödemesinden mahsup edilir.');
    } else {
      $("#kesinti_kanal_bilgi_metin").html('<strong>Banka Seçilirse:</strong> Kesinti tutarı öncelikle resmî banka ödemesinden düşülür. Banka tutarını aşarsa kalan kısım elden ödemeden mahsup edilir.');
    }
  }

  // Tekrar tipi değişince - EVENT DELEGATION
  $(document).on("change", 'input[name="tekrar_tipi"]', function () {
    updateTekrarTipiUI();
  });

  function updateTekrarTipiUI() {
    var modal = $("#modalPersonelKesintiEkle");
    var container = modal.length 
      ? modal.find('input[name="tekrar_tipi"]').closest('.segmented-control-container')
      : $('input[name="tekrar_tipi"]').closest('.segmented-control-container');
    
    if (!container.length) return;

    var checkedInput = container.find('input[name="tekrar_tipi"]:checked');
    if (!checkedInput.length) {
      checkedInput = container.find('#tekrar_tek_sefer');
      checkedInput.prop('checked', true).attr('checked', 'checked');
    }
    var tekrarTipi = checkedInput.val() || 'tek_sefer';

    container.find('.segmented-control-input').not(checkedInput).prop('checked', false).removeAttr('checked');
    container.find('.segmented-control-label').removeClass('active');
    if (checkedInput.attr('id')) {
      container.find('label[for="' + checkedInput.attr('id') + '"]').addClass('active');
    }

    if (tekrarTipi === "surekli") {
      modal.find("#div_tek_sefer_donem").addClass("d-none").css("display", "");
      modal.find("#div_surekli_donem_baslangic, #div_surekli_donem_bitis").removeClass("d-none").css("display", "");
      modal.find("#div_taksit_sayisi").addClass("d-none").css("display", "");
      modal.find("select[name='kesinti_donem']").prop("required", false);
      modal.find("#baslangic_donemi").prop("required", true);
    } else if (tekrarTipi === "taksitli") {
      modal.find("#div_tek_sefer_donem").removeClass("d-none").css("display", "");
      modal.find("#div_surekli_donem_baslangic, #div_surekli_donem_bitis").addClass("d-none").css("display", "");
      modal.find("#div_taksit_sayisi").removeClass("d-none").css("display", "");
      modal.find("select[name='kesinti_donem']").prop("required", true);
      modal.find("#baslangic_donemi").prop("required", false);
    } else {
      modal.find("#div_tek_sefer_donem").removeClass("d-none").css("display", "");
      modal.find("#div_surekli_donem_baslangic, #div_surekli_donem_bitis").addClass("d-none").css("display", "");
      modal.find("#div_taksit_sayisi").addClass("d-none").css("display", "");
      modal.find("select[name='kesinti_donem']").prop("required", true);
      modal.find("#baslangic_donemi").prop("required", false);
    }
  }

  // Hesaplama tipi değişince - EVENT DELEGATION
  $(document).on("change", 'input[name="hesaplama_tipi"]', function () {
    updateHesaplamaTipiUI();
  });

  function updateHesaplamaTipiUI() {
    var modal = $("#modalPersonelKesintiEkle");
    var container = modal.length 
      ? modal.find('input[name="hesaplama_tipi"]').closest('.segmented-control-container')
      : $('input[name="hesaplama_tipi"]').closest('.segmented-control-container');
    
    if (!container.length) return;

    var checkedInput = container.find('input[name="hesaplama_tipi"]:checked');
    if (!checkedInput.length) {
      checkedInput = container.find('#hesaplama_sabit');
      checkedInput.prop('checked', true).attr('checked', 'checked');
    }
    var hesaplamaTipi = checkedInput.val() || 'sabit';

    container.find('.segmented-control-input').not(checkedInput).prop('checked', false).removeAttr('checked');
    container.find('.segmented-control-label').removeClass('active');
    if (checkedInput.attr('id')) {
      container.find('label[for="' + checkedInput.attr('id') + '"]').addClass('active');
    }

    if (hesaplamaTipi === "sabit") {
      modal.find("#div_tutar").removeClass("d-none").css("display", "");
      modal.find("#div_oran").addClass("d-none").css("display", "");
      modal.find("input[name='kesinti_tutar']").prop("required", true);
      modal.find("input[name='oran']").prop("required", false);
    } else {
      modal.find("#div_tutar").addClass("d-none").css("display", "");
      modal.find("#div_oran").removeClass("d-none").css("display", "");
      modal.find("input[name='kesinti_tutar']").prop("required", false);
      modal.find("input[name='oran']").prop("required", true);
    }
  }

  function updateKesintiTipUI() {
    var modal = $("#modalPersonelKesintiEkle");
    var container = modal.length 
      ? modal.find('input[name="rad_kesinti_tip"]').closest('.segmented-control-container')
      : $('input[name="rad_kesinti_tip"]').closest('.segmented-control-container');
    
    if (!container.length) return;

    var checkedInput = container.find('input[name="rad_kesinti_tip"]:checked');
    if (!checkedInput.length) {
      checkedInput = container.find('#kesinti_tip_tutar');
      checkedInput.prop('checked', true).attr('checked', 'checked');
    }
    var tip = checkedInput.val() || 'tutar';

    container.find('.segmented-control-input').not(checkedInput).prop('checked', false).removeAttr('checked');
    container.find('.segmented-control-label').removeClass('active');
    if (checkedInput.attr('id')) {
      container.find('label[for="' + checkedInput.attr('id') + '"]').addClass('active');
    }

    if (tip === "gun") {
      modal.find("#div_kesinti_gun").removeClass("d-none");
      modal.find("#div_tutar").addClass("d-none");
      modal.find("input[name='kesinti_tutar']").prop("required", false);
      modal.find("#kesinti_gun_sayisi").prop("required", true).focus();
    } else {
      modal.find("#div_kesinti_gun").addClass("d-none");
      modal.find("#div_tutar").removeClass("d-none");
      modal.find("input[name='kesinti_tutar']").prop("required", true);
      modal.find("#kesinti_gun_sayisi").prop("required", false);
    }
  }

  // Parametre seçilince - EVENT DELEGATION
  $(document).on("change", "#kesinti_parametre_id", function () {
    var selected = $(this).find("option:selected");
    if (!selected.val()) {
        $("#param_info_bar").addClass("d-none");
        return;
    }

    var kod = selected.data("kod");
    var hesaplama = selected.data("hesaplama") || "";
    var oran = selected.data("oran") || 0;
    var tutar = selected.data("tutar") || 0;
    var personel_id = $('input[name="personel_id"]').val() || $('#formPersonelKesintiEkle input[name="personel_id"]').val() || '';

    // Bilgi barını güncelle
    $("#param_info_bar").removeClass("d-none");
    var hLabel = "Sabit Tutar";
    if (hesaplama.includes("oran_bazli_net") || hesaplama === "oran_net") hLabel = "Net Maaş %";
    else if (hesaplama.includes("oran_bazli_brut") || hesaplama === "oran_brut") hLabel = "Brüt Maaş %";
    
    $("#info_hesaplama").text(hLabel);
    $("#info_deger").text(hesaplama.includes("oran") ? "%" + oran : tutar + " TL");

    // İcra seçildiyse icra dosyalarını getir
    if (kod === "icra") {
      $("#div_icra_secimi").removeClass("d-none");
      loadIcraDosyalari(personel_id);
    } else {
      $("#div_icra_secimi").addClass("d-none");
      $("#kesinti_icra_id").val("");
    }

    // Sadece yeni kayıt modundaysak (ID yoksa) parametre varsayılanını yükle
    var isEditMode = $("#formPersonelKesintiEkle input[name='id']").length > 0;
    if (!isEditMode) {
      if (hesaplama.includes("oran_bazli_net") || hesaplama === "oran_net") {
        $("#hesaplama_oran_net").prop("checked", true).trigger("change");
        if (oran > 0) {
          $("#formPersonelKesintiEkle input[name='oran']").val(oran);
        }
      } else if (
        hesaplama.includes("oran_bazli_brut") ||
        hesaplama === "oran_brut"
      ) {
        $("#hesaplama_oran_brut").prop("checked", true).trigger("change");
        if (oran > 0) {
          $("#formPersonelKesintiEkle input[name='oran']").val(oran);
        }
      } else {
        $("#hesaplama_sabit").prop("checked", true).trigger("change");
        if (tutar > 0) {
          $("#formPersonelKesintiEkle input[name='kesinti_tutar']").val(tutar);
        }
      }
      updateHesaplamaTipiUI();
    }

    // Ücretsiz İzin özel mantığı
    if (kod === "izin_kesinti") {
      $("#div_ucretsiz_izin_secenek").removeClass("d-none");
      const maasDurumu = window.personelData ? window.personelData.maas_durumu : "";

      if (maasDurumu === "Prim Usulü") {
        $("#kesinti_tip_gun").prop("disabled", true);
        $("label[for='kesinti_tip_gun']").addClass("opacity-50");
        $("#kesinti_tip_tutar").prop("checked", true).trigger("change");
      } else {
        $("#kesinti_tip_gun").prop("disabled", false);
        $("label[for='kesinti_tip_gun']").removeClass("opacity-50");
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
      $("input[name='kesinti_tutar']").prop("required", false);
      $("#kesinti_gun_sayisi").prop("required", true).focus();
    } else {
      $("#div_kesinti_gun").addClass("d-none");
      $("#div_tutar").removeClass("d-none");
      $("input[name='kesinti_tutar']").prop("required", true);
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
      $("input[name='kesinti_tutar']").val(toplam);
    } else {
      $("input[name='kesinti_tutar']").val(0);
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
              '" data-dosya-no="' +
              item.dosya_no +
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

  $(document).on("change select2:select", "#kesinti_icra_id", function () {
    var selected = $(this).find("option:selected");
    var tutar = selected.data("tutar");
    var dosyaNo = selected.data("dosya-no");
    var form = $("#formPersonelKesintiEkle");
    
    if (tutar) {
      form.find("input[name='kesinti_tutar']").val(tutar);
      // İcra her zaman sabit tutar
      $("#hesaplama_sabit").prop("checked", true);
      updateHesaplamaTipiUI();
    }

    if (dosyaNo && window.personelData) {
        var tc = window.personelData.tc_kimlik_no || "";
        var ad = window.personelData.adi_soyadi || "";
        var defaultAciklama = dosyaNo + " ESAS Numaralı " + tc + " T.C Kimlik numaralı " + ad + " İcra ödemesi";
        form.find("input[name='aciklama']").val(defaultAciklama);
    }
  });

  // Kesinti Kaydet
  $(document).on("click", "#btnPersonelKesintiKaydet", function () {
    var submitBtn = $(this);
    var modal = submitBtn.closest(".modal");
    if (!modal.length) modal = $("#modalPersonelKesintiEkle");
    var form = submitBtn.closest("form");
    if (!form.length) form = modal.find("#formPersonelKesintiEkle");
    if (!form.length) form = $("#formPersonelKesintiEkle");
    var originalHtml = submitBtn.html();

    // Manuel validasyon - Kesinti türü tespiti
    var paramSelect = modal.find("#kesinti_parametre_id");
    if (!paramSelect.length) paramSelect = form.find("#kesinti_parametre_id");
    if (!paramSelect.length) paramSelect = $("#kesinti_parametre_id");

    var parametreId = paramSelect.val();
    if (!parametreId) {
      parametreId = paramSelect.find("option:selected").val();
    }
    if (!parametreId && paramSelect.data("select2")) {
      var s2Data = paramSelect.select2("data");
      if (s2Data && s2Data.length && s2Data[0].id) {
        parametreId = s2Data[0].id;
      }
    }
    if (!parametreId) {
      parametreId = form.find("select[name='parametre_id']").val();
    }

    // Güçlü Fallback: Görünür Select2 etiketinden option eşleme
    if (!parametreId) {
      var renderedEl = modal.find(".select2-selection__rendered");
      var renderedText = (renderedEl.attr("title") || renderedEl.text() || "").trim();
      renderedText = renderedText.replace(/^[×x]\s*/, '').replace(/\s*[×x]$/, '').trim();
      if (renderedText && renderedText !== "Kesinti türü seçiniz..." && renderedText !== "Dosya seçiniz...") {
        paramSelect.find("option").each(function () {
          var val = $(this).val();
          var txt = $(this).text().trim();
          var kod = $(this).data("kod");
          if (val && (txt === renderedText || txt.indexOf(renderedText) !== -1 || kod === renderedText)) {
            parametreId = val;
            paramSelect.val(val);
            return false;
          }
        });
      }
    }

    if (!parametreId) {
      Swal.fire("Hata", "Lütfen kesinti türü seçiniz.", "error");
      return;
    }

    // Underlying select sync
    paramSelect.val(parametreId);

    var selectedOpt = paramSelect.find("option[value='" + parametreId + "']");
    if (!selectedOpt.length) selectedOpt = paramSelect.find("option:selected");
    var turKod = selectedOpt.data("kod") || selectedOpt.text().trim() || "diger";

    var tekrarTipi = form.find('input[name="tekrar_tipi"]:checked').val() || "tek_sefer";
    var hesaplamaTipi = form.find('input[name="hesaplama_tipi"]:checked').val() || "sabit";
    var bankaMatrah = form.find('input[name="banka_matrahina_ekle"]:checked').val() !== undefined 
      ? form.find('input[name="banka_matrahina_ekle"]:checked').val() 
      : 1;

    // Tek seferlik veya Taksitli ise dönem zorunlu
    if (tekrarTipi === "tek_sefer" || tekrarTipi === "taksitli") {
      var donem = form.find("select[name='kesinti_donem']").val();
      if (!donem) {
        Swal.fire("Hata", "Lütfen dönem seçiniz.", "error");
        return;
      }
    } else {
      // Sürekli ise başlangıç dönemi zorunlu (Y-m formatında date inputu)
      var baslangicDonemi = form.find("#baslangic_donemi").val();
      if (!baslangicDonemi) {
        Swal.fire("Hata", "Lütfen başlangıç dönemini giriniz.", "error");
        return;
      }
    }

    // Taksitli ise taksit sayısı kontrolü
    if (tekrarTipi === "taksitli") {
      var ts = form.find("#kesinti_taksit_sayisi").val();
      if (!ts || parseInt(ts) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir taksit sayısı giriniz.", "error");
        return;
      }
    }

    // Sabit tutarda tutar zorunlu
    if (hesaplamaTipi === "sabit") {
      var tutar = form.find("input[name='kesinti_tutar']").val();
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

    // Güncelleme kontrolü
    var idInput = form.find('input[name="id"]');
    var action = idInput.length > 0 ? "update_kesinti" : "save_kesinti";

    var data = {
      action: action,
      personel_id: $('input[name="personel_id"]').val() || form.find('input[name="personel_id"]').val(),
      parametre_id: parametreId,
      tur: turKod,
      tekrar_tipi: tekrarTipi,
      hesaplama_tipi: hesaplamaTipi,
      tutar: hesaplamaTipi === "sabit" ? form.find("input[name='kesinti_tutar']").val() : 0,
      oran: hesaplamaTipi !== "sabit" ? form.find("input[name='oran']").val() : 0,
      tarih: form.find("#kesinti_tarih").val() || $("#kesinti_tarih").val(),
      banka_matrahina_ekle: bankaMatrah,
      aciklama: form.find("input[name='aciklama']").val(),
      icra_id: form.find("#kesinti_icra_id").val() || null,
      taksit_sayisi: tekrarTipi === "taksitli" ? form.find("#kesinti_taksit_sayisi").val() : null,
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
    if (tekrarTipi === "tek_sefer" || tekrarTipi === "taksitli") {
      data.donem_id = form.find("select[name='kesinti_donem']").val();
    } else {
      data.baslangic_donemi = form.find("#baslangic_donemi").val();
      data.bitis_donemi = form.find("#bitis_donemi").val() || null;
    }

    // Disable button and show spinner to prevent multiple submissions
    submitBtn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Kaydediliyor...');

    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "POST",
      data: data,
      dataType: "json",
      success: function (response) {
        submitBtn.prop("disabled", false).html(originalHtml);
        if (response.success) {
          refreshKesintiTab(function () {
            Swal.fire("Başarılı", "Kesinti kaydedildi.", "success");
          });
        } else {
          Swal.fire("Hata", response.error || "Bir hata oluştu", "error");
        }
      },
      error: function () {
        submitBtn.prop("disabled", false).html(originalHtml);
        Swal.fire("Hata", "Bir hata oluştu.", "error");
      },
    });
  });

  // Kesinti Onayla
  $(document).on("click", ".btn-personel-kesinti-onayla", function () {
    var id = $(this).data("id");

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
        $.ajax({
          url: "views/personel/api.php",
          type: "POST",
          data: {
            action: "kesinti-onayla",
            kesinti_id: id,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              refreshKesintiTab(function () {
                Swal.fire("Onaylandı!", "Kesinti onaylandı.", "success");
              });
            } else {
              Swal.fire("Hata", response.message || "Bir hata oluştu", "error");
            }
          },
          error: function (xhr, status, error) {
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
              refreshKesintiTab(function () {
                Swal.fire("Reddedildi!", "Kesinti reddedildi.", "success");
              });
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
              refreshKesintiTab(function () {
                Swal.fire("Başarılı!", "Kesinti sonlandırıldı.", "success");
              });
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
              refreshKesintiTab(function () {
                Swal.fire("Silindi!", "Kayıt silindi.", "success");
              });
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

  function refreshKesintiTab(callback) {
    var modalEl = $("#modalPersonelKesintiEkle");
    if (modalEl.length) {
      if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        var modalObj = bootstrap.Modal.getInstance(modalEl[0]);
        if (modalObj) {
          try { modalObj.hide(); } catch(e){}
          try { modalObj.dispose(); } catch(e){}
        } else {
          modalEl.modal("hide");
        }
      } else {
        modalEl.modal("hide");
      }
    }
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css({ overflow: "", "padding-right": "" });

    var targetPane = $("#kesintiler");
    if (!targetPane.length) {
      if (typeof callback === "function") callback();
      return;
    }

    var url = targetPane.attr("data-url");
    if (url) {
      $.get(url, function (html) {
        targetPane.html(html);
        targetPane.attr("data-loaded", "true");

        if (typeof window.initPlugins === "function") {
          window.initPlugins(targetPane[0]);
        } else if (typeof initPlugins === "function") {
          initPlugins(targetPane[0]);
        }

        initKesintiSelect2();

        if (typeof feather !== "undefined") {
          feather.replace();
        }

        if (typeof toggleKesintiView === "function") {
          toggleKesintiView(localStorage.getItem("kesintiViewMode") || "gruplu");
        }

        if (typeof callback === "function") {
          callback();
        }
      }).fail(function () {
        targetPane.html('<div class="alert alert-danger">İçerik yüklenirken bir hata oluştu.</div>');
      });
    } else {
      location.reload();
    }
  }

  // Global erişim için
  window.refreshKesintiTab = refreshKesintiTab;
});
