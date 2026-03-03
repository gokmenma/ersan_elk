$(document).ready(function () {
  // =============================================
  // PERSONEL ZİMMET İŞLEMLERİ (Event Delegation)
  // =============================================

  // Yeni Zimmet Modal Aç
  $(document).on("click", "#btnOpenZimmetModal", function () {
    console.log("Zimmet modal açılıyor...");
    $("#modalPersonelZimmetEkle").modal("show");

    // Select2'yi modal içinde başlat
    $("#demirbas_id").select2({
      dropdownParent: $("#modalPersonelZimmetEkle"),
      width: "100%",
    });
  });

  // Demirbaş seçildiğinde kalan miktarı göster
  $(document).on("change", "#demirbas_id", function () {
    var id = $(this).val();
    var stokMap = $(this).data("stok") || {};
    var kalan = stokMap[id] || 0;
    console.log("Seçilen demirbaş kalan:", kalan);
    $("#personelKalanMiktar").text(kalan);
    $("#teslim_miktar").attr("max", kalan).val(1);
  });

  // Yeni Zimmet Kaydet
  $(document).on("click", "#btnPersonelZimmetKaydet", function () {
    console.log("Zimmet kaydet tıklandı");

    var btn = $(this);
    var originalText = btn.html();
    var form = $("#formPersonelZimmetEkle");

    if (form.length === 0) {
      console.error("Form bulunamadı!");
      return;
    }

    var demirbasId = $("#demirbas_id").val();
    var teslimMiktar = parseInt($("#teslim_miktar").val()) || 1;
    var stokMap = $("#demirbas_id").data("stok") || {};
    var kalan = stokMap[demirbasId] || 0;

    console.log(
      "Demirbaş ID:",
      demirbasId,
      "Miktar:",
      teslimMiktar,
      "Kalan:",
      kalan,
    );

    if (!demirbasId) {
      Swal.fire("Uyarı", "Lütfen demirbaş seçiniz.", "warning");
      return;
    }

    if (teslimMiktar > kalan) {
      Swal.fire(
        "Uyarı",
        "Stokta sadece " + kalan + " adet bulunmaktadır.",
        "warning",
      );
      return;
    }

    btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader bx-spin"></i> Kaydediliyor...');

    var formData = new FormData(form[0]);
    formData.append("action", "zimmet-kaydet");

    $.ajax({
      url: "views/demirbas/api.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        console.log("API Response:", response);
        if (response.status === "success") {
          $("#modalPersonelZimmetEkle").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          });

          // Tab içeriğini yenile
          var $targetPane = $("#zimmetler");
          var url = $targetPane.data("url");

          if ($targetPane.length && url) {
            $targetPane.load(url, function () {
              if (typeof initPlugins === "function") {
                initPlugins($targetPane[0]);
              }
            });
          } else {
            location.reload();
          }
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Hatası:", status, error, xhr.responseText);
        Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // İade Modal Aç
  $(document).on("click", ".btn-personel-zimmet-iade", function () {
    var id = $(this).data("id");
    var demirbas = $(this).data("demirbas");
    var miktar = $(this).data("miktar");

    console.log("İade modal açılıyor:", id, demirbas, miktar);

    $("#personel_iade_zimmet_id").val(id);
    $("#personel_iade_demirbas_adi").text(demirbas);
    $("#personel_iade_miktar_goster").text(miktar);
    $("#iade_miktar").val(miktar).attr("max", miktar);

    $("#modalPersonelIade").modal("show");
  });

  // İade Kaydet
  $(document).on("click", "#btnPersonelIadeKaydet", function () {
    console.log("İade kaydet tıklandı");

    var btn = $(this);
    var originalText = btn.html();
    var form = $("#formPersonelIade");

    btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader bx-spin"></i> Kaydediliyor...');

    var formData = new FormData(form[0]);
    formData.append("action", "zimmet-iade");

    $.ajax({
      url: "views/demirbas/api.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        console.log("İade API Response:", response);
        if (response.status === "success") {
          $("#modalPersonelIade").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: response.message,
            timer: 1500,
            showConfirmButton: false,
          });

          // Tab içeriğini yenile
          var $targetPane = $("#zimmetler");
          var url = $targetPane.data("url");

          if ($targetPane.length && url) {
            $targetPane.load(url, function () {
              if (typeof initPlugins === "function") {
                initPlugins($targetPane[0]);
              }
            });
          } else {
            location.reload();
          }
        } else {
          Swal.fire("Hata", response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Hatası:", status, error, xhr.responseText);
        Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
      },
      complete: function () {
        btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // Zimmet Detay
  $(document).on("click", ".btn-personel-zimmet-detay", function (e) {
    e.preventDefault();
    let id = $(this).data("id");

    var formData = new FormData();
    formData.append("action", "zimmet-detay");
    formData.append("id", id);

    $.ajax({
      url: "views/demirbas/api.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (data) {
        if (data.status === "success") {
          let d = data.data;
          let gecmis = data.gecmis;
          let hareketler = data.hareketler;

          // Üst bilgi kartını ve özet kartlarını doldur
          $("#detay_demirbas_adi").text(d.demirbas_detay.demirbas_adi || "-");
          $("#detay_marka_model").text(
            (d.demirbas_detay.marka || "") +
              " " +
              (d.demirbas_detay.model || ""),
          );
          $("#detay_seri_no").text(d.demirbas_detay.seri_no || "-");
          $("#detay_durum_badge").html(d.durum_badge);
          $("#detay_personel_adi").text(d.personel_detay.adi_soyadi || "-");

          let toplamZimmet = parseInt(d.teslim_miktar || 0);
          let tuketilen = parseInt(d.iade_miktar || 0);
          let kalan = toplamZimmet - tuketilen;

          $("#ozet_toplam").text(toplamZimmet);
          $("#ozet_tuketilen").text(tuketilen);
          $("#ozet_kalan").text(kalan);

          // 1. HAREKET DETAYLARI TABLOSUNU DOLDUR
          let hBody = $("#zimmetHareketBody");
          hBody.empty();
          if (hareketler && hareketler.length > 0) {
            let ilkZimmetAtlandi = false;

            hareketler.forEach((h) => {
              if (
                !ilkZimmetAtlandi &&
                (h.hareket_tipi === "zimmet" || h.hareket_tipi === "Zimmet")
              ) {
                ilkZimmetAtlandi = true;
                return;
              }

              let deleteBtn = "";
              if (h.hareket_tipi === "iade" || h.hareket_tipi === "sarf") {
                deleteBtn = `<button class="btn btn-sm btn-outline-danger zimmet-hareket-sil" data-id="${h.id}" data-type="${h.hareket_tipi}" title="Geri Al / Sil"><i class="bx bx-trash"></i></button>`;
              }

              let row = `
                <tr>
                  <td>${h.hareket_badge}</td>
                  <td class="text-center fw-bold">${h.miktar}</td>
                  <td>${h.tarih_format}</td>
                  <td class="small">${h.aciklama || ""}</td>
                  <td class="text-center">${deleteBtn}</td>
                </tr>
              `;
              hBody.append(row);
            });

            if (hBody.children().length === 0) {
              hBody.append(
                '<tr><td colspan="5" class="text-center text-muted border-0 py-3 italic">Başka bir hareket bulunmuyor.</td></tr>',
              );
            }
          } else {
            hBody.append(
              '<tr><td colspan="5" class="text-center text-muted py-3">Hareket kaydı bulunamadı.</td></tr>',
            );
          }

          

          if (typeof feather !== "undefined") {
            setTimeout(() => feather.replace(), 10);
          }

          $("#zimmetDetayModal").modal("show");
        } else {
          Swal.fire("Hata!", data.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Hatası:", status, error);
        Swal.fire("Hata!", "Bir hata oluştu.", "error");
      },
    });
  });

  // Zimmet Sil
  $(document).on("click", ".btn-personel-zimmet-sil", function () {
    var btn = $(this);
    var id = btn.data("id");
    var row = btn.closest("tr");

    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu zimmet kaydını silmek istediğinizden emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Evet, Sil!",
      cancelButtonText: "İptal",
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/demirbas/api.php",
          type: "POST",
          data: { action: "zimmet-sil", id: id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              row.fadeOut(300, function () {
                $(this).remove();
              });
              Swal.fire("Silindi!", response.message, "success");
            } else {
              Swal.fire("Hata!", response.message, "error");
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX Hatası:", status, error);
            Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
          },
        });
      }
    });
  });

  //console.log("Zimmet.js yüklendi.");
});
