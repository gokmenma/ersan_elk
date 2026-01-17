$(document).ready(function () {
  // Mevcut DataTable instance'ına eriş
  var table = $("#membersTable").DataTable(getDatatableOptions());

  // Satır seçimi
  table.on("click", "tbody tr", (e) => {
    let classList = e.currentTarget.classList;

    if (classList.contains("selected")) {
      classList.remove("selected");
      // Checkbox ve butonları güncelle
      $(e.currentTarget).find(".form-check-input").prop("checked", false);
      toggleButtons(false);
    } else {
      table
        .rows(".selected")
        .nodes()
        .each((row) => {
          row.classList.remove("selected");
          $(row).find(".form-check-input").prop("checked", false);
        });
      classList.add("selected");

      // Checkbox ve butonları güncelle
      $(e.currentTarget).find(".form-check-input").prop("checked", true);
      toggleButtons(true);
    }
  });

  // Butonları aktif/pasif yapma fonksiyonu
  function toggleButtons(enable) {
    var buttons = $("#btnEditSelected, #btnDeleteSelected, #btnDetailSelected");
    if (enable) {
      buttons.prop("disabled", false);
    } else {
      buttons.prop("disabled", true);
    }
  }

  // Seçili ID'yi alma fonksiyonu
  function getSelectedId() {
    var selectedRow = table.row(".selected");
    if (selectedRow.any()) {
      var data = selectedRow.data();
      // Index 1: ID sütunu (0: Checkbox)
      return data[1];
    }
    return null;
  }

  // Düzenle Butonu
  $("#btnEditSelected").click(function () {
    var id = getSelectedId();
    if (id) {
      window.location.href = "index?p=personel/manage&id=" + id;
    }
  });

  // Sil Butonu
  $("#btnDeleteSelected").click(function () {
    var id = getSelectedId();
    if (id) {
      Swal.fire({
        title: "Emin misiniz?",
        text: "Bu personeli silmek istediğinize emin misiniz?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Evet, sil!",
        cancelButtonText: "Hayır, iptal et",
      }).then((result) => {
        if (result.isConfirmed) {
          // API üzerinden silme işlemi
          $.post(
            "views/personel/api.php",
            { action: "personel-sil", id: id },
            function (response) {
              let res = JSON.parse(response);
              if (res.status === "success") {
                Swal.fire("Silindi!", res.message, "success").then(() => {
                  location.reload(); // Sayfayı yenile
                });
              } else {
                Swal.fire("Hata!", res.message, "error");
              }
            }
          );
        }
      });
    }
  });

  // Detay Butonu
  $("#btnDetailSelected").click(function () {
    var id = getSelectedId();
    if (id) {
      // AJAX isteği ile verileri çek
      $.post(
        "views/personel/api.php",
        { action: "get-details", id: id },
        function (response) {
          let res = JSON.parse(response);
          if (res.status === "success") {
            let data = res.data;

            // Profil Özeti
            $("#detailResim").attr(
              "src",
              data.resim_yolu
                ? data.resim_yolu
                : "assets/images/users/user-dummy-img.jpg"
            );
            $("#detailAdSoyad").text(data.adi_soyadi);
            $("#detailGorev").text(data.gorev);
            $("#detailDepartman").text(data.departman);

            let durumBadge =
              data.aktif_mi == 1
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-danger">Pasif</span>';
            $("#detailDurum").html(durumBadge);

            // Genel Bilgiler Tab
            $("#detailTc").text(data.tc_kimlik_no);
            $("#detailDogumTarihi").text(data.dogum_tarihi);
            $("#detailCinsiyet").text(data.cinsiyet);
            $("#detailMedeniDurum").text(data.medeni_durum);
            $("#detailKanGrubu").text(data.kan_grubu);
            $("#detailAnneAdi").text(data.anne_adi);
            $("#detailBabaAdi").text(data.baba_adi);
            $("#detailDogumYeri").text(
              (data.dogum_yeri_il || "") + " / " + (data.dogum_yeri_ilce || "")
            );

            // İletişim Bilgileri Tab
            $("#detailTelefon").text(data.cep_telefonu);
            $("#detailTelefon2").text(data.cep_telefonu_2);
            $("#detailEmail").text(data.email_adresi);
            $("#detailAdres").text(data.adres);

            // Çalışma Bilgileri Tab
            $("#detailIseGiris").text(data.ise_giris_tarihi);
            $("#detailIstenCikis").text(data.isten_cikis_tarihi);
            $("#detailTakim").text(data.takim);
            $("#detailSgkNo").text(data.sgk_no);
            $("#detailSgkFirma").text(data.sgk_yapilan_firma);

            // Modalı göster
            var myModal = new bootstrap.Modal(
              document.getElementById("personelDetailModal")
            );
            myModal.show();
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        }
      );
    }
  });

  // Excel Import Modal Aç
  $("#btnImportExcel").click(function () {
    var myModal = new bootstrap.Modal(document.getElementById("importExcelModal"));
    myModal.show();
  });

  // Şablon İndir Butonu
  $("#btnDownloadTemplate").click(function () {
    window.location.href = "views/personel/download-template.php";
  });

  // Excel Upload Button Click
  $("#btnUploadExcel").click(function () {
    var formData = new FormData($("#importExcelForm")[0]);
    formData.append("action", "excel-upload");

    // Dosya seçili mi kontrol et
    if ($("#excelFile").val() == "") {
        Swal.fire("Uyarı", "Lütfen bir dosya seçiniz.", "warning");
        return;
    }

    // Yükleniyor göster
    Swal.fire({
        title: 'Yükleniyor...',
        text: 'Personel listesi işleniyor, lütfen bekleyin.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: "views/personel/api.php",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
            try {
                let res = JSON.parse(response);
                if (res.status === "success") {
                    let message = res.message.replace(/\n/g, "<br>");
                    let icon = "success";
                    
                    if (res.errors && res.errors.length > 0) {
                        icon = "warning";
                        message += "<br><br><strong>Hata Detayları:</strong><br><div style='text-align:left; max-height: 200px; overflow-y: auto; font-size: 0.9em; border: 1px solid #eee; padding: 10px; background: #f9f9f9;'><ul>";
                        res.errors.forEach(err => {
                            message += `<li>${err}</li>`;
                        });
                        message += "</ul></div>";
                    }

                    Swal.fire({
                        title: "İşlem Tamamlandı",
                        html: message,
                        icon: icon,
                        width: '600px'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire("Hata", res.message, "error");
                }
            } catch (e) {
                Swal.fire("Hata", "Sunucudan geçersiz yanıt alındı.", "error");
                console.error(response);
            }
        },
        error: function () {
            Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
        }
    });
  });
});
