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
    $("#kesinti_parametre_id").val("").trigger("change");
    $("#tekrar_tek_sefer").prop("checked", true);
    $("#hesaplama_sabit").prop("checked", true);
    updateTekrarTipiUI();
    updateHesaplamaTipiUI();
    $("#div_icra_secimi").addClass("d-none");
  }

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
      $("input[name='tutar']").prop("required", true);
      $("input[name='oran']").prop("required", false);
    } else {
      $("#div_tutar").addClass("d-none").hide();
      $("#div_oran").removeClass("d-none").show();
      $("input[name='tutar']").prop("required", false);
      $("input[name='oran']").prop("required", true);
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
        $("input[name='oran']").val(oran);
      }
    } else if (
      hesaplama.includes("oran_bazli_brut") ||
      hesaplama === "oran_brut"
    ) {
      $("#hesaplama_oran_brut").prop("checked", true);
      if (oran > 0) {
        $("input[name='oran']").val(oran);
      }
    } else {
      $("#hesaplama_sabit").prop("checked", true);
      if (tutar > 0) {
        $("input[name='tutar']").val(tutar);
      }
    }
    updateHesaplamaTipiUI();
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

  // İcra Seçilince Tutarı Otomatik Doldur
  $(document).on("change", "#kesinti_icra_id", function () {
    var selected = $(this).find("option:selected");
    var tutar = selected.data("tutar");
    if (tutar) {
      $("input[name='tutar']").val(tutar);
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
      var tutar = $("input[name='tutar']").val();
      if (!tutar || parseFloat(tutar) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir tutar giriniz.", "error");
        return;
      }
    } else {
      // Oran bazlı ise oran zorunlu
      var oran = $("input[name='oran']").val();
      if (!oran || parseFloat(oran) <= 0) {
        Swal.fire("Hata", "Lütfen geçerli bir oran giriniz.", "error");
        return;
      }
    }

    // Tür kodunu al
    var turKod =
      $("#kesinti_parametre_id").find("option:selected").data("kod") || "diger";

    var data = {
      action: "save_kesinti",
      personel_id: $('input[name="personel_id"]').val(),
      parametre_id: parametreId,
      tur: turKod,
      tekrar_tipi: tekrarTipi,
      hesaplama_tipi: hesaplamaTipi,
      tutar: hesaplamaTipi === "sabit" ? $("input[name='tutar']").val() : 0,
      oran: hesaplamaTipi !== "sabit" ? $("input[name='oran']").val() : 0,
      aciklama: $("input[name='aciklama']").val(),
      icra_id: $("#kesinti_icra_id").val() || null,
    };

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
      });
    } else {
      // Fallback - sayfayı yenile
      location.reload();
    }
  }
});
