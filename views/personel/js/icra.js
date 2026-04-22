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
          $("#icra_dairesi").val(response.icra_dairesi).trigger("change", true);
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
  var currentToplamBorc = 0;

  function loadIcraKesintileri(icraId, toplamBorc) {
    currentIcraId = icraId;
    currentToplamBorc = toplamBorc;

    $("#icraKesintileriBody").html(
      '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-warning me-2" role="status"></div>Yükleniyor...</td></tr>',
    );

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
            '<tr><td colspan="8" class="text-center py-3 text-danger">' +
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
            '<tr><td colspan="8" class="text-center py-5 text-muted">Bu dosyaya ait kesinti kaydı bulunamadı.</td></tr>';
        } else {
          for (var idx = 0; idx < kesintiler.length; idx++) {
            var k = kesintiler[idx];
            var tutar = parseFloat(k.tutar) || 0;
            tKesilen += tutar;

            var dBadge =
              k.durum === "onaylandi"
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Onaylı</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Beklemede</span>';

            var oBadge = 
              k.odeme_durumu === "odendi"
                ? '<span class="badge bg-success pointer-cursor update-odeme-modal" data-id="'+k.id+'" data-durum="odenmedi" style="cursor:pointer" title="Ödenmedi Olarak İşaretle">Ödendi</span>'
                : '<span class="badge bg-danger pointer-cursor update-odeme-modal" data-id="'+k.id+'" data-durum="odendi" style="cursor:pointer" title="Ödendi Olarak İşaretle">Ödenmedi</span>';

            var dekontHtml = "";
            if (k.dekont_dosyasi) {
                dekontHtml = '<div class="d-flex flex-column align-items-center gap-1">' +
                             '<div class="btn-group">' +
                             '<a href="uploads/kesintiler/'+k.dekont_dosyasi+'" target="_blank" class="btn btn-xs btn-outline-info" title="Görüntüle"><i class="bx bx-show"></i></a>' +
                             '<button class="btn btn-xs btn-outline-danger btn-dekont-sil-modal" data-id="'+k.id+'" title="Sil"><i class="bx bx-trash"></i></button>' +
                             '</div>' +
                             '<small class="text-muted text-truncate" style="max-width: 80px;" title="'+k.dekont_dosyasi+'">'+k.dekont_dosyasi+'</small>' +
                             '</div>';
            } else {
                dekontHtml = '<button class="btn btn-xs btn-outline-primary btn-upload-modal" data-id="'+k.id+'" title="Yükle"><i class="bx bx-upload"></i></button>';
            }

            html += "<tr>";
            html += '<td class="text-center">' + (idx + 1) + "</td>";
            html += "<td>" + (k.donem_adi || "-") + "</td>";
            html += "<td>" + (k.icra_detay || "-") + "</td>";
            html += "<td>" + (k.aciklama || "-") + "</td>";
            html +=
              '<td class="text-end fw-bold text-dark">' +
              tutar.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
              " TL</td>";
            html += '<td class="text-center">' + oBadge + "</td>";
            html += '<td class="text-center">' + dekontHtml + "</td>";
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
        var kalan = currentToplamBorc - tKesilen;
        $("#icraDetayKalanTutar")
          .text(
            kalan.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " TL",
          )
          .removeClass("text-danger text-success")
          .addClass(kalan > 0 ? "text-danger" : "text-success");
      },
      error: function () {
        $("#icraKesintileriBody").html(
          '<tr><td colspan="8" class="text-center text-danger py-3">Bağlantı hatası!</td></tr>',
        );
      },
    });
  }

  $(document).on("click", ".btn-icra-kesinti-detay", function () {
    var icraId = $(this).data("id");
    var icraDairesi = $(this).data("icra-dairesi");
    var dosyaNo = $(this).data("dosya-no");
    var toplamBorc = parseFloat($(this).data("toplam-borc")) || 0;

    $("#icraDetayDairesi").text(icraDairesi);
    $("#icraDetayDosyaNo").text(dosyaNo);
    $("#icraDetayToplamBorc").text(
      toplamBorc.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " TL",
    );

    $("#modalIcraKesintileri").modal("show");
    setTimeout(syncFeather, 100);

    loadIcraKesintileri(icraId, toplamBorc);
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
  
  // Ödeme Durumu Güncelle (Modal içinden)
  $(document).on("click", ".update-odeme-modal", function() {
      const id = $(this).data("id");
      const durum = $(this).data("durum");
      const pId = getPersonelId();
      
      $.post("views/personel/ajax/kesinti-islemleri.php", {
          action: "update_odeme_durumu",
          id: id,
          odeme_durumu: durum,
          personel_id: pId
      }, function(response) {
          if (response.success) {
              loadIcraKesintileri(currentIcraId, currentToplamBorc);
              showToast("Ödeme durumu güncellendi.", "success");
          } else {
              showToast(response.error || "Hata oluştu", "error");
          }
      }, "json");
  });
  
  $(document).on("click", ".btn-upload-modal", function() {
      const id = $(this).data("id");
      const pId = getPersonelId();
      
      Swal.fire({
          title: 'Dekont Yükleme',
          html: `
              <div class="p-3">
                  <div class="upload-container border-2 border-dashed border-info rounded-3 p-4 text-center mb-3" 
                       style="background-color: #f8f9fa; cursor: pointer;"
                       onclick="document.getElementById('dekontFileInputModal').click()">
                      <i class="bx bx-cloud-upload display-4 text-info mb-2"></i>
                      <h6 class="fw-bold">Ödeme Dekontu Seçin</h6>
                      <p class="text-muted small mb-0">PDF, JPG, JPEG veya PNG (Maks 5MB)</p>
                      <p class="text-primary small mt-2 mb-0"><i class="bx bx-info-circle me-1"></i> Belge yüklendiğinde durum otomatik olarak <b>"Ödendi"</b> yapılacaktır.</p>
                      <div id="file-info-modal" class="mt-3 d-none">
                          <span class="badge bg-info px-3 py-2 rounded-pill">
                              <i class="bx bx-file me-1"></i> <span id="file-name-modal">dosya.pdf</span>
                          </span>
                      </div>
                  </div>
                  <input type="file" id="dekontFileInputModal" class="d-none" accept=".pdf,.jpg,.jpeg,.png" onchange="
                      if(this.files[0]) {
                          document.getElementById('file-info-modal').classList.remove('d-none');
                          document.getElementById('file-name-modal').innerText = this.files[0].name;
                      }
                  ">
              </div>
          `,
          showCancelButton: true,
          confirmButtonText: '<i class="bx bx-upload me-1"></i> Yükle',
          cancelButtonText: 'Vazgeç',
          confirmButtonColor: '#50a5f1',
          cancelButtonColor: '#f46a6a',
          customClass: {
              confirmButton: 'btn btn-info text-white px-4 rounded-pill',
              cancelButton: 'btn btn-light px-4 rounded-pill'
          },
          buttonsStyling: false,
          preConfirm: () => {
              const file = document.getElementById('dekontFileInputModal').files[0];
              if (!file) {
                  Swal.showValidationMessage('Lütfen bir dosya seçin');
                  return false;
              }
              return file;
          }
      }).then((result) => {
          if (result.isConfirmed) {
              const formData = new FormData();
              formData.append('action', 'upload_dekont');
              formData.append('id', id);
              formData.append('dekont', result.value);
              formData.append('personel_id', pId);

              // Yükleme bildirimi
              Swal.fire({
                  title: 'Yükleniyor...',
                  text: 'Dekont sisteme kaydediliyor.',
                  allowOutsideClick: false,
                  didOpen: () => {
                      Swal.showLoading();
                  }
              });

              $.ajax({
                  url: 'views/personel/ajax/kesinti-islemleri.php',
                  type: 'POST',
                  data: formData,
                  processData: false,
                  contentType: false,
                  dataType: 'json',
                  success: function(response) {
                      if (response.success) {
                          loadIcraKesintileri(currentIcraId, currentToplamBorc);
                          Swal.fire({
                              icon: 'success',
                              title: 'Başarılı',
                              text: 'Dekont yüklendi ve ödeme durumu "Ödendi" olarak güncellendi.',
                              timer: 2000
                          });
                      } else {
                          showToast(response.error || "Yükleme başarısız", "error");
                      }
                  }
              });
          }
      });
  });

  // Dekont Sil (Modal içinden)
  $(document).on("click", ".btn-dekont-sil-modal", function() {
      const id = $(this).data("id");
      
      Swal.fire({
          title: 'Emin misiniz?',
          text: "Bu dekont dosyası kalıcı olarak silinecektir!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#f46a6a',
          cancelButtonColor: '#74788d',
          confirmButtonText: 'Evet, Sil!',
          cancelButtonText: 'Vazgeç',
          customClass: {
              confirmButton: 'btn btn-danger text-white px-4 rounded-pill me-2',
              cancelButton: 'btn btn-light px-4 rounded-pill'
          },
          buttonsStyling: false
      }).then((result) => {
          if (result.isConfirmed) {
              $.post("views/personel/ajax/kesinti-islemleri.php", {
                  action: "delete_dekont",
                  id: id,
                  personel_id: getPersonelId()
              }, function(response) {
                  if (response.success) {
                      loadIcraKesintileri(currentIcraId, currentToplamBorc);
                      showToast("Dekont dosyası silindi.", "success");
                  } else {
                      showToast(response.error || "Hata oluştu", "error");
                  }
              }, "json");
          }
      });
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
