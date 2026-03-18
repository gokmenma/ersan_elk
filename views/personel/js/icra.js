$(document).ready(function () {
  // Feather ikonlarını kesin olarak render eden fonksiyon
  function syncFeather() {
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  // Personel ID alırken daha spesifik olalım (Çakışmaları önlemek için)
  function getPersonelId() {
    return (
      $("#personelForm #personel_id").val() ||
      $('input[name="personel_id"]').first().val()
    );
  }

  // İcra Modal Aç (Yeni Ekle)
  $(document).on("click", "#btnOpenIcraModal", function () {
    var nextSira = $(this).data("next-sira") || 1;
    $("#icraModalTitle").html(
      '<i data-feather="plus-circle" class="icon-sm me-2"></i>Yeni İcra Dosyası Ekle',
    );
    $("#formPersonelIcraEkle")[0].reset();
    $("#icra_id_hidden").val("");
    $("#icra_sira").val(nextSira);
    $("#icra_durum").val("devam_ediyor");
    $("#icra_kesinti_tipi").val("tutar");
    $("#icra_kesinti_orani").val("25");
    $("#icra_iban").val("");
    $("#icra_hesap_bilgileri").val("");
    if ($("#icra_baslangic")[0] && $("#icra_baslangic")[0]._flatpickr) {
        $("#icra_baslangic")[0]._flatpickr.clear();
    } else {
        $("#icra_baslangic").val("");
    }
    
    if ($("#icra_bitis")[0] && $("#icra_bitis")[0]._flatpickr) {
        $("#icra_bitis")[0]._flatpickr.clear();
    } else {
        $("#icra_bitis").val("");
    }

    $("#modalPersonelIcraEkle").modal("show");
    setTimeout(function() {
        syncFeather();
    }, 50);
  });

  // Kesinti tipi değiştiğinde alanları göster/gizle (Sadece bir kez bağla)
  $(document).on("change", "#icra_kesinti_tipi", function () {
    var tip = $(this).val();
    if (tip === "tutar") {
      $("#div_icra_aylik_kesinti").show();
      $("#div_icra_kesinti_orani").hide();
      $("#icra_aylik_kesinti").attr("required", true);
      $("#icra_kesinti_orani").removeAttr("required");
    } else {
      $("#div_icra_aylik_kesinti").hide();
      $("#div_icra_kesinti_orani").show();
      $("#icra_aylik_kesinti").removeAttr("required");
      $("#icra_kesinti_orani").attr("required", true);
    }
  });
  
  // Bitiş tarihi değiştiğinde uyarı ver
  $(document).on("change", "#icra_bitis", function () {
    var val = $(this).val();
    var durum = $("#icra_durum").val();
    if (val && val !== "" && val !== "0000-00-00" && durum === "devam_ediyor") {
        Swal.fire({
            title: "Durum Güncelleme Hatırlatması",
            text: "Bitiş tarihi girdiğinizde, dosya kapandığında icra durumunu da 'Tamamlandı' veya 'Kesinti Bitti' şeklinde güncellemeyi unutmayınız.",
            icon: "info",
            confirmButtonText: "Anladım",
            customClass: {
                confirmButton: "btn btn-info px-4"
            },
            buttonsStyling: false
        });
    }
  });

  // İcra Düzenle Modal Aç
  $(document).on("click", ".btn-icra-duzenle", function () {
    var id = $(this).data("id");
    $("#icraModalTitle").html(
      '<i data-feather="edit-3" class="icon-sm me-2"></i>İcra Dosyasını Düzenle',
    );

    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "GET",
      data: {
        action: "get_icra",
        id: id,
        personel_id: getPersonelId(),
      },
      dataType: "json",
      success: function (response) {
        if (response) {
          $("#icra_id_hidden").val(response.id);
          // Helper'ın oluşturduğu id'leri (input name ile aynı) kullanıyoruz
          $("#icra_sira").val(response.sira);
          $("#icra_dairesi").val(response.icra_dairesi);
          $("#icra_dosya_no").val(response.dosya_no);
          $("#icra_toplam_borc").val(response.toplam_borc);
          $("#icra_kesinti_tipi")
            .val(response.kesinti_tipi || "tutar")
            .trigger("change");
          $("#icra_aylik_kesinti").val(response.aylik_kesinti_tutari);
          $("#icra_kesinti_orani").val(response.kesinti_orani || "25");
          $("#icra_iban").val(response.iban || "");
          $("#icra_hesap_bilgileri").val(response.hesap_bilgileri || "");
          $("#icra_durum").val(response.durum).trigger("change");
          
          // Flatpickr değerlerini set et (altInput kullanıldığı için setDate gereklidir)
          if ($("#icra_baslangic")[0] && $("#icra_baslangic")[0]._flatpickr) {
              $("#icra_baslangic")[0]._flatpickr.setDate(response.baslangic_tarihi || "", true);
          } else {
              $("#icra_baslangic").val(response.baslangic_tarihi);
          }
          
          if ($("#icra_bitis")[0] && $("#icra_bitis")[0]._flatpickr) {
              $("#icra_bitis")[0]._flatpickr.setDate(response.bitis_tarihi || "", true);
          } else {
              $("#icra_bitis").val(response.bitis_tarihi);
          }

          $("#icra_aciklama").val(response.aciklama);

          $("#modalPersonelIcraEkle").modal("show");
          setTimeout(function () {
            syncFeather();
          }, 50);
        } else {
          Swal.fire("Hata", "İcra bilgileri alınamadı", "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Veri çekme sırasında bir hata oluştu.", "error");
      },
    });
  });

  // İcra Kaydet/Güncelle
  $(document).on("click", "#btnPersonelIcraKaydet", function () {
    var form = $("#formPersonelIcraEkle");
    if (form[0].checkValidity() === false) {
      form[0].reportValidity();
      return;
    }

    var icraId = $("#icra_id_hidden").val();
    var action = icraId ? "update_icra" : "save_icra";
    // Serialize ederken personel_id'nin doğru olduğundan emin olalım
    var data = form.serialize() + "&action=" + action;

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
          showToast(
            icraId ? "Kayıt güncellendi." : "Kayıt oluşturuldu.",
            "success",
          );
        } else {
          showToast(response.error || "İşlem başarısız", "error");
        }
      },
      error: function () {
        showToast("Sunucu hatası oluştu.", "error");
      },
    });
  });

  // İcra Silme
  $(document).on("click", ".btn-personel-icra-sil", function () {
    var id = $(this).data("id");
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu icra dosyası ve tüm geçmişi silinecektir!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "Vazgeç",
      customClass: {
        confirmButton: "btn btn-danger px-4 me-2",
        cancelButton: "btn btn-light px-4",
      },
      buttonsStyling: false,
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/personel/ajax/kesinti-islemleri.php",
          type: "POST",
          data: {
            action: "delete_icra",
            id: id,
            personel_id: getPersonelId(),
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              refreshIcraTab();
              showToast("Dosya silinmiştir.", "success");
            } else {
              showToast(response.error || "Silme başarısız", "error");
            }
          },
          error: function () {
            showToast("Sistem hatası.", "error");
          },
        });
      }
    });
  });

  // İcra Kesinti Detay Modal
  var currentIcraId = null;

  $(document).on("click", ".btn-icra-kesinti-detay", function () {
    var icraId = $(this).data("id");
    var icraDairesi = $(this).data("icra-dairesi");
    var dosyaNo = $(this).data("dosya-no");
    var toplamBorc = parseFloat($(this).data("toplam-borc")) || 0;

    currentIcraId = icraId;

    $("#icraDetayDairesi").text(icraDairesi);
    $("#icraDetayDosyaNo").text(dosyaNo);
    $("#icraDetayToplamBorc").text(
      toplamBorc.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " TL",
    );

    $("#icraKesintileriBody").html(
      '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-warning me-2" role="status"></div>Yükleniyor...</td></tr>',
    );

    $("#modalIcraKesintileri").modal("show");
    setTimeout(syncFeather, 100);

    $.ajax({
      url: "views/personel/ajax/kesinti-islemleri.php",
      type: "GET",
      data: {
        action: "get_icra_kesintileri",
        icra_id: icraId,
        personel_id: getPersonelId(),
      },
      dataType: "json",
      success: function (response) {
        if (response.error) {
          $("#icraKesintileriBody").html(
            '<tr><td colspan="6" class="text-center py-3 text-danger">' +
              response.error +
              "</td></tr>",
          );
          return;
        }

        var kesintiler = response.kesintiler || [];
        var html = "";
        var tKesilen = 0;

        if (kesintiler.length === 0) {
          html =
            '<tr><td colspan="6" class="text-center py-5 text-muted">Bu dosyaya ait kesinti kaydı bulunamadı.</td></tr>';
        } else {
          for (var idx = 0; idx < kesintiler.length; idx++) {
            var k = kesintiler[idx];
            var tutar = parseFloat(k.tutar) || 0;
            tKesilen += tutar;

            var dBadge =
              k.durum === "onaylandi"
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Onaylı</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Beklemede</span>';

            html += "<tr>";
            html += '<td class="text-center">' + (idx + 1) + "</td>";
            html += "<td>" + (k.donem_adi || "-") + "</td>";
            html += "<td>" + (k.icra_detay || "-") + "</td>";
            html += "<td>" + (k.aciklama || "-") + "</td>";
            html +=
              '<td class="text-end fw-bold text-dark">' +
              tutar.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
              " TL</td>";
            html += '<td class="text-center">' + dBadge + "</td>";
            html +=
              "<td>" +
              (k.olusturma_tarihi
                ? new Date(k.olusturma_tarihi).toLocaleDateString("tr-TR")
                : "-") +
              "</td>";
            html += "</tr>";
          }
        }

        $("#icraKesintileriBody").html(html);
        setTimeout(syncFeather, 50);

        $("#icraDetayToplamKesilen").text(
          tKesilen.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
            " TL",
        );
        var kalan = toplamBorc - tKesilen;
        $("#icraDetayKalanTutar")
          .text(
            kalan.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " TL",
          )
          .removeClass("text-danger text-success")
          .addClass(kalan > 0 ? "text-danger" : "text-success");
      },
      error: function () {
        $("#icraKesintileriBody").html(
          '<tr><td colspan="6" class="text-center text-danger py-3">Bağlantı hatası!</td></tr>',
        );
      },
    });
  });

  $(document).on("click", "#btnIcraListYazdir", function () {
    $("#modalIcraListeYazdir").modal("show");
  });

  $(document).on("click", "#btnIcraKesintileriExcel", function () {
    if (!currentIcraId) return;
    window.location.href =
      "views/personel/ajax/kesinti-islemleri.php?action=export_icra_kesintileri&icra_id=" +
      currentIcraId +
      "&personel_id=" +
      getPersonelId();
  });

  function refreshIcraTab() {
    var target = $("#icralar");
    var url = target.attr("data-url");
    if (url) {
      $.get(url, function (html) {
        target.html(html);
        if (typeof initPlugins === "function") {
          initPlugins(target[0]);
        } else {
          setTimeout(syncFeather, 50);
        }
      });
    }
  }

  setTimeout(syncFeather, 100);
});
