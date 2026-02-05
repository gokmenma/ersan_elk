$(document).ready(function () {
  // Mevcut DataTable instance'ına eriş
  var table = $("#membersTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    order: [[3, "asc"]],
    ajax: {
      url: "views/personel/api.php",
      type: "POST",
      data: function (d) {
        d.action = "personel-list";
      },
    },
    columns: [
      {
        data: null,
        orderable: false,
        render: function (data, type, row, meta) {
          return `
            <div class="form-check font-size-16">
                <input class="form-check-input" type="checkbox" id="orderidcheck${meta.row}">
                <label class="form-check-label" for="orderidcheck${meta.row}"></label>
            </div>`;
        },
      },
      {
        data: null,
        className: "text-center",
        render: function (data, type, row, meta) {
          return meta.row + meta.settings._iDisplayStart + 1;
        },
      },
      
      { data: "tc_kimlik_no" },
      {
        data: "adi_soyadi",
        render: function (data, type, row) {
          return `
            <div class="personel-name-container">
                <a class="fw-bold" target="_blank" href="index?p=personel/manage&id=${row.id}">${data}</a>
                <img src="${row.resim_yolu ? row.resim_yolu : "assets/images/users/user-dummy-img.jpg"}"
                    alt="${data}" class="personel-hover-image">
            </div>`;
        },
      },
      { data: "ise_giris_tarihi" },
      { data: "isten_cikis_tarihi" },
      {
        data: "cep_telefonu",
        render: function (data) {
          return `<i class="feather feather-smartphone"></i> ${data}`;
        },
      },
      { data: "email_adresi" },
      { data: "gorev" },
      { data: "departman" },
      {
        data: null,
        render: function (data, type, row) {
          if (
            !row.ekip_adi ||
            row.ekip_adi === "YOK" ||
            row.ekip_adi.trim() === ""
          ) {
            return "";
          }

          // Badge renk paleti
          const badgeColors = [
            "bg-primary-subtle text-primary border-primary-subtle",
            "bg-success-subtle text-success border-success-subtle",
            "bg-info-subtle text-info border-info-subtle",
            "bg-warning-subtle text-warning border-warning-subtle",
            "bg-danger-subtle text-danger border-danger-subtle",
            "bg-secondary-subtle text-secondary border-secondary-subtle",
            "bg-dark-subtle text-dark border-dark-subtle",
          ];

          // İsimden renk seçen basit fonksiyon (aynı ekip hep aynı renk kalır)
          const getColor = (str) => {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
              hash = str.charCodeAt(i) + ((hash << 5) - hash);
            }
            return badgeColors[Math.abs(hash) % badgeColors.length];
          };

          // Birden fazla ekip olabilir, virgülle ayrılmışları temizle
          let ekipler = row.ekip_adi.split(",");
          let badges = ekipler.map((ekip) => {
            let cleanEkip = ekip.trim();
            // ERSAN ELEKTRİK, ER-SAN ELEKTRİK, vb. ibareleri kaldır
            cleanEkip = cleanEkip
              .replace(/ER-SAN ELEKTRİK/gi, "")
              .replace(/ERSAN ELEKTRİK/gi, "")
              .replace(/ER SAN ELEKTRİK/gi, "")
              .trim();

            const colorClass = getColor(cleanEkip);

            return `<span class="badge ${colorClass} font-size-12 px-2 py-1 mb-1 me-1 border">${cleanEkip}</span>`;
          });

          let bolgeler = "";
          if (row.ekip_bolge && row.ekip_bolge !== "---") {
            bolgeler = `<div class="text-muted small mt-1"><i class="bx bx-map-pin"></i> ${row.ekip_bolge}</div>`;
          }

          return `
            <div class="d-flex flex-wrap">${badges.join("")}</div>
            ${bolgeler}
          `;
        },
      },
      {
        data: "bildirim_abonesi",
        className: "text-center",
        render: function (data) {
          if (data == 1) {
            return '<span class="badge bg-success"><i class="bx bx-check-double font-size-13 align-middle me-1"></i>Açık</span>';
          } else {
            return '<span class="badge bg-danger"><i class="bx bx-x font-size-13 align-middle me-1"></i>Kapalı</span>';
          }
        },
      },
      {
        data: "aktif_mi",
        render: function (data) {
          return data == 1 ? "Aktif" : "Pasif";
        },
      },
    ],
    createdRow: function (row, data, dataIndex) {
      $(row).attr("data-id", data.id);
    },
  });

  // Satır seçimi
  table.on("click", "tbody tr", (e) => {
    // Eğer tıklanan element bir link ise veya bir linkin içindeyse, seçme işlemini yapma
    if ($(e.target).closest("a").length > 0) {
      return;
    }

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

  // // Seçili ID'yi alma fonksiyonu
  // function getSelectedId() {
  //   var selectedRow = table.row(".selected");
  //   if (selectedRow.any()) {
  //     var data = selectedRow.data();
  //     // Index 1: ID sütunu (0: Checkbox)
  //     return data.id;
  //   }
  //   return null;
  // }

  function getSelectedId() {
    var selectedRow = table.row(".selected");

    if (selectedRow.any()) {
      var tr = selectedRow.node(); // <tr>
      return $(tr).data("id"); // data-id
    }

    return null;
  }

  // Düzenle Butonu
  $("#btnEditSelected").click(function () {
    var id = getSelectedId();
    console.log("ID: " + id);
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
            },
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
                : "assets/images/users/user-dummy-img.jpg",
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
              (data.dogum_yeri_il || "") + " / " + (data.dogum_yeri_ilce || ""),
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
              document.getElementById("personelDetailModal"),
            );
            myModal.show();
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
      );
    }
  });

  // Excel Import Modal Aç
  $("#btnImportExcel").click(function () {
    var myModal = new bootstrap.Modal(
      document.getElementById("importExcelModal"),
    );
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
      title: "Yükleniyor...",
      text: "Personel listesi işleniyor, lütfen bekleyin.",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
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
              message +=
                "<br><br><strong>Hata Detayları:</strong><br><div style='text-align:left; max-height: 200px; overflow-y: auto; font-size: 0.9em; border: 1px solid #eee; padding: 10px; background: #f9f9f9;'><ul>";
              res.errors.forEach((err) => {
                message += `<li>${err}</li>`;
              });
              message += "</ul></div>";
            }

            Swal.fire({
              title: "İşlem Tamamlandı",
              html: message,
              icon: icon,
              width: "600px",
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
      },
    });
  });
  // Excel Export Button Click
  $("#exportExcel").click(function () {
    var btn = $(this);
    var originalText = btn.html();

    // Buton metnini ve durumunu güncelle
    btn.html('<i class="bx bx-loader bx-spin label-icon"></i> Aktarılıyor...');
    btn.prop("disabled", true);

    // Get DataTables search term
    var table = $("#membersTable").DataTable();
    var searchTerm = table.search();

    var colSearches = {};

    // Custom search inputs from datatables.init.js
    $("#membersTable .search-input-row input").each(function () {
      var val = $(this).val();
      var colIdx = $(this).attr("data-col-idx");
      if (val && colIdx) {
        colSearches[colIdx] = val;
      }
    });

    var url = "views/personel/export-excel.php";
    var params = new URLSearchParams();

    if (searchTerm) {
      params.append("search", searchTerm);
    }

    if (Object.keys(colSearches).length > 0) {
      params.append("col_search", JSON.stringify(colSearches));
    }

    // Add timestamp to prevent caching
    params.append("t", Date.now());

    if (params.toString()) {
      url += "?" + params.toString();
    }

    fetch(url)
      .then((resp) => {
        if (resp.status !== 200) {
          return resp.text().then((text) => {
            throw new Error(text || "Export failed");
          });
        }
        return resp.blob();
      })
      .then((blob) => {
        // Dosyayı indir
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        // Dosya ismini tarihli yap
        var date = new Date().toISOString().slice(0, 10);
        a.download = "personel_listesi_" + date + ".xlsx";
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch((err) => {
        console.error(err);
        Swal.fire(
          "Hata",
          "Excel aktarımı sırasında bir hata oluştu: " + err.message,
          "error",
        );
      })
      .finally(() => {
        // Butonu eski haline getir
        btn.html(originalText);
        btn.prop("disabled", false);
      });
  });
});
