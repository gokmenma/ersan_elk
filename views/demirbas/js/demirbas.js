var zimmetUrl = "views/demirbas/api.php";
var demirbasTable,
  zimmetTable,
  depoPersonelTable,
  hurdaDemirbasTable,
  sayacTable;

// ============== SAYFA YÜKLENDİĞİNDE ==============
$(document).ready(function () {
  // DataTable başlat
  let demirbasOptions = getDatatableOptions();
  demirbasOptions.columnDefs = [{ orderable: false, targets: -1 }];
  demirbasOptions.order = [[0, "asc"]];
  // Özelleştirilmiş boş tablo mesajı
  demirbasOptions.language.emptyTable =
    '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz demirbaş eklenmemiş.<br><small>"Yeni Demirbaş" butonuna tıklayarak ekleyebilirsiniz.</small></div>';

  demirbasTable = $("#demirbasTable").DataTable(demirbasOptions);

  // Zimmet tablosu DataTable
  zimmetTable = $("#zimmetTable").DataTable({
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: zimmetUrl,
      type: "POST",
      data: function (d) {
        d.action = "zimmet-listesi";
      },
    },
    columns: [
      { data: "id", className: "text-center" },
      { data: "kategori_adi" },
      { data: "demirbas_adi" },
      { data: "marka_model" },
      { data: "personel_adi" },
      { data: "teslim_miktar", className: "text-center" },
      { data: "teslim_tarihi" },
      { data: "durum", className: "text-center" },
      { data: "islemler", className: "text-center", orderable: false },
    ],
    order: [[0, "desc"]],
    createdRow: function (row, data, dataIndex) {
      $(row).attr("data-id", data.enc_id);
    },
    language: {
      ...getDatatableOptions().language,
      emptyTable:
        '<div class="text-center text-muted py-4"><i class="bx bx-transfer display-4 d-block mb-2"></i>Henüz zimmet kaydı bulunmamaktadır.</div>',
    },
  });

  // Depo Personel Tablosu
  depoPersonelTable = $("#depoPersonelTable").DataTable({
    ...getDatatableOptions(),
    pageLength: 25,
    order: [[1, "asc"]],
    columnDefs: [{ orderable: false, targets: [0] }],
  });

  // Sayaç Tablosu
  if ($("#sayacTable").length) {
    let sayacOptions = getDatatableOptions();
    sayacOptions.columnDefs = [{ orderable: false, targets: -1 }];
    sayacOptions.order = [[0, "asc"]];
    sayacOptions.language.emptyTable =
      '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz sayaç eklenmemiş.<br><small>"Yeni Sayaç" butonuna tıklayarak ekleyebilirsiniz.</small></div>';
    sayacTable = $("#sayacTable").DataTable(sayacOptions);
  }

  // Select2 başlat
  initSelect2();

  // Feather icons
  if (typeof feather !== "undefined") {
    feather.replace();
  }

  // Başlangıçta buton görünürlüğünü ayarla
  updateButtonVisibility();

  // Eğer sayfa yüklendiğinde zimmet tabı aktifse listeyi yükle
  if ($("#zimmet-tab").hasClass("active")) {
    loadZimmetList();
  }
});

function updateButtonVisibility() {
  let activeTabBtn = $("#demirbasTab button.active");
  if (activeTabBtn.length === 0) return;

  let activeTab = activeTabBtn.attr("id");
  // Tüm ana aksiyon butonlarını gizle
  $("#btnYeniDemirbas, #btnZimmetVer, #btnYeniSayac")
    .addClass("d-none")
    .removeClass("d-flex");
  $("#importExcelLi").addClass("d-none");

  if (activeTab === "demirbas-tab") {
    $("#btnYeniDemirbas").removeClass("d-none").addClass("d-flex");
    $("#importExcelLi").removeClass("d-none");
  } else if (activeTab === "zimmet-tab") {
    $("#btnZimmetVer").removeClass("d-none").addClass("d-flex");
    if (typeof zimmetTable !== "undefined") {
      zimmetTable.ajax.reload(null, false);
    }
  } else if (activeTab === "depo-tab") {
    $("#btnYeniSayac").removeClass("d-none").addClass("d-flex");
  }
}

// ============== GENEL BUTON TIKLAMA OLAYLARI ==============
$(document).on("click", "#btnZimmetVer", function () {
  resetZimmetForm();
});

$(document).on("click", "#btnYeniDemirbas", function () {
  resetDemirbasForm();
});

// Export Excel Butonu - Aktif tabloyu yakalar
$(document).on("click", "#exportExcel", function (e) {
  e.preventDefault();
  let activeTab = $("#demirbasTab button.active").attr("id");
  let targetTable;

  if (activeTab === "demirbas-tab") targetTable = demirbasTable;
  else if (activeTab === "zimmet-tab") targetTable = zimmetTable;
  else if (activeTab === "depo-tab")
    targetTable = sayacTable || depoPersonelTable;

  if (targetTable) {
    targetTable.button(".buttons-excel").trigger();
  } else {
    if (typeof table !== "undefined" && table) {
      table.button(".buttons-excel").trigger();
    }
  }
});

// ============== SELECT2 BAŞLAT ==============
function initSelect2() {
  // Zimmet Modalı Select2'leri
  if ($("#demirbas_id_zimmet").length) {
    $("#demirbas_id_zimmet").select2({
      dropdownParent: $("#zimmetModal"),
      placeholder: "Demirbaş arayın...",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#personel_id").length) {
    $("#personel_id").select2({
      dropdownParent: $("#zimmetModal"),
      placeholder: "Personel arayın...",
      allowClear: true,
      width: "100%",
    });
  }

  // Genel Demirbaş Modalı Select2'leri
  if ($("#kategori_id").length) {
    $("#kategori_id").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Kategori seçin...",
      width: "100%",
    });
  }

  if ($("#durum").length) {
    $("#durum").select2({
      dropdownParent: $("#demirbasModal"),
      minimumResultsForSearch: Infinity,
      width: "100%",
    });
  }

  // Otomatik Zimmet Ayarları Select2'leri
  if ($("#otomatik_zimmet_is_emri").length) {
    $("#otomatik_zimmet_is_emri").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#otomatik_iade_is_emri").length) {
    $("#otomatik_iade_is_emri").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  // ============== KATEGORİ FİLTRELEME (TAB'A GÖRE) ==============
  $("#demirbasModal").on("show.bs.modal", function () {
    // Aktif tab'ı bul (Daha güvenli yöntem)
    let activeTab = $("#demirbasTab button.active").attr("id");
    if (!activeTab) {
      activeTab = $(".nav-link.active[data-bs-toggle='tab']").attr("id");
    }

    const $kategoriSelect = $("#kategori_id");
    const demirbasId = $("#demirbas_id").val();

    // sayacKatIds tanımlı değilse veya boşsa işlem yapma
    if (typeof sayacKatIds === "undefined") return;

    // Önce hepsini temizle ve göster
    $kategoriSelect.find("option").prop("disabled", false).show();

    if (activeTab === "depo-tab") {
      // SADECE SAYAÇLAR (SAYAC KAT ID İÇİNDE OLANLAR)
      $kategoriSelect.find("option").each(function () {
        const val = $(this).val();
        if (val !== "" && !sayacKatIds.includes(val.toString())) {
          $(this).prop("disabled", true).hide();
        }
      });

      // Yeni kayıt ise: Eğer şu anki seçim uygun değilse ilk uygun olanı seç
      if (demirbasId == "0") {
        const currentVal = $kategoriSelect.val();
        if (!currentVal || !sayacKatIds.includes(currentVal.toString())) {
          const firstSayac = $kategoriSelect
            .find("option")
            .filter(function () {
              return sayacKatIds.includes($(this).val().toString());
            })
            .first()
            .val();
          $kategoriSelect.val(firstSayac).trigger("change");
        }
      }
    } else {
      // SAYAÇLAR HARİÇ HER ŞEY
      $kategoriSelect.find("option").each(function () {
        const val = $(this).val();
        if (val !== "" && sayacKatIds.includes(val.toString())) {
          $(this).prop("disabled", true).hide();
        }
      });

      // Yeni kayıt ise: Eğer şu anki seçim bir sayaç ise ilk uygun olanı seç
      if (demirbasId == "0") {
        const currentVal = $kategoriSelect.val();
        if (!currentVal || sayacKatIds.includes(currentVal.toString())) {
          const firstDemirbas = $kategoriSelect
            .find("option")
            .filter(function () {
              const v = $(this).val();
              return v !== "" && !sayacKatIds.includes(v.toString());
            })
            .first()
            .val();
          $kategoriSelect.val(firstDemirbas).trigger("change");
        }
      }
    }

    // Select2'yi tamamen sıfırla ve yeniden başlat (DOM değişikliklerini görmesi için)
    if ($kategoriSelect.data("select2")) {
      $kategoriSelect.select2("destroy");
    }

    $kategoriSelect.select2({
      dropdownParent: $("#demirbasModal"),
      width: "100%",
      templateResult: function (option) {
        if (!option.id) return option.text;
        const target = $kategoriSelect.find(
          'option[value="' + option.id + '"]',
        );
        if (target.css("display") === "none" || target.prop("disabled")) {
          return null;
        }
        return option.text;
      },
    });
  });
}

// ============== İŞ EMRİ SONUÇLARINI GETİR ==============
function fetchIsEmriSonuclari(callback) {
  fetch(zimmetUrl + "?action=is-emri-sonuclari")
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        const options = data.data;
        const selects = ["#otomatik_zimmet_is_emri", "#otomatik_iade_is_emri"];

        selects.forEach((selector) => {
          const $select = $(selector);
          if ($select.length) {
            // Mevcut seçili değeri sakla
            const currentVal = $select.val();

            // Seçenekleri temizle ve yeniden doldur
            $select.empty();
            options.forEach((opt) => {
              const selected = opt.id === currentVal ? "selected" : "";
              $select.append(
                new Option(opt.text, opt.id, false, opt.id === currentVal),
              );
            });

            // Tekrar tetikle
            if (currentVal) {
              $select.val(currentVal).trigger("change.select2");
            }
          }
        });

        if (typeof callback === "function") callback();
      }
    })
    .catch((err) => console.error("İş emri sonuçları yüklenemedi:", err));
}

// ============== TAB DEĞİŞİKLİĞİNDE ==============
$(document).on("click", "#demirbas-tab, #zimmet-tab, #depo-tab", function () {
  let tabMap = {
    "demirbas-tab": "demirbas",
    "zimmet-tab": "zimmet",
    "depo-tab": "depo",
  };
  const tabName = tabMap[this.id] || "demirbas";

  // URL'i güncelle
  const url = new URL(window.location);
  url.searchParams.set("tab", tabName);
  window.history.replaceState({}, "", url);

  // Buton görünürlüğünü güncelle
  updateButtonVisibility();

  if (this.id === "zimmet-tab") {
    loadZimmetList();
  }
});

// ============== ZİMMET LİSTESİ YÜKLE ==============
function loadZimmetList() {
  zimmetTable.ajax.reload(null, false);
}

// ============== DEMİRBAŞ İŞLEMLERİ ==============

// ============== TOPLU SERİ GİRİŞİ ==============

// Seri modu radio toggle
$(document).on("change", 'input[name="seri_mod"]', function () {
  let mod = $(this).val();
  if (mod === "toplu") {
    $("#seriTekliAlani").hide();
    $("#seriTopluAlani").slideDown(200);
    $("#seri_no").val(""); // Tekli seri alanını temizle
    // Toplu modda miktar alanını gizle (otomatik hesaplanacak)
    $("#miktar").closest(".col-md-3").hide();
  } else {
    $("#seriTekliAlani").show();
    $("#seriTopluAlani").slideUp(200);
    $("#seri_baslangic, #seri_bitis, #seri_adet").val("");
    $("#seriOnizlemeContainer").hide();
    $("#seriOnizlemeList").empty();
    // Miktar alanını tekrar göster
    $("#miktar").closest(".col-md-3").show();
  }
});

// Seri numarasından sayısal kısmı ve ön eki ayır
function parseSeriNo(seri) {
  seri = seri.toString().trim();
  // Sondaki rakam bloğunu bul
  let match = seri.match(/^(.*?)(\d+)$/);
  if (match) {
    return {
      prefix: match[1],
      number: parseInt(match[2], 10),
      digits: match[2].length,
    };
  }
  return null;
}

// Seri numarası oluştur (ön ek + sıfır padli numara)
function buildSeriNo(prefix, number, digits) {
  return prefix + number.toString().padStart(digits, "0");
}

// Serileri hesapla ve önizle
function hesaplaVeOnizle() {
  let baslangic = $("#seri_baslangic").val().trim();
  let bitis = $("#seri_bitis").val().trim();
  let adet = parseInt($("#seri_adet").val()) || 0;

  if (!baslangic) {
    $("#seriOnizlemeContainer").hide();
    return [];
  }

  let parsed = parseSeriNo(baslangic);
  if (!parsed) {
    $("#seriOnizlemeContainer").hide();
    return [];
  }

  let seriler = [];

  if (bitis && !adet) {
    // Bitiş girilmiş, adeti hesapla
    let parsedBitis = parseSeriNo(bitis);
    if (parsedBitis && parsedBitis.prefix === parsed.prefix) {
      adet = parsedBitis.number - parsed.number + 1;
      if (adet > 0 && adet <= 500) {
        $("#seri_adet").val(adet);
      } else {
        $("#seriOnizlemeContainer").hide();
        return [];
      }
    }
  } else if (adet > 0 && !bitis) {
    // Adet girilmiş, bitişi hesapla
    let bitisNo = parsed.number + adet - 1;
    $("#seri_bitis").val(buildSeriNo(parsed.prefix, bitisNo, parsed.digits));
  } else if (adet > 0 && bitis) {
    // İkisi de girilmiş → adet'e öncelik ver, bitişi güncelle
    let bitisNo = parsed.number + adet - 1;
    $("#seri_bitis").val(buildSeriNo(parsed.prefix, bitisNo, parsed.digits));
  }

  // Adeti son kez al
  adet = parseInt($("#seri_adet").val()) || 0;
  if (adet <= 0 || adet > 500) {
    $("#seriOnizlemeContainer").hide();
    return [];
  }

  // Seri listesini oluştur
  for (let i = 0; i < adet; i++) {
    seriler.push(buildSeriNo(parsed.prefix, parsed.number + i, parsed.digits));
  }

  // Önizleme göster
  $("#seriOnizlemeContainer").show();
  $("#seriToplamBadge").text(seriler.length + " adet");
  let html = seriler
    .map(
      (s, i) =>
        `<span class="badge bg-light text-dark border">${i + 1}. ${s}</span>`,
    )
    .join("");
  $("#seriOnizlemeList").html(html);

  return seriler;
}

// Input event'leri
$(document).on("input", "#seri_baslangic", function () {
  // Başlangıç değişince bitiş/adet temizle ve yeniden hesapla
  hesaplaVeOnizle();
});

$(document).on("input", "#seri_adet", function () {
  // Adet girildiğinde bitişi hesapla
  $("#seri_bitis").val(""); // Bitiş alanını temizle, adet baz alınacak
  hesaplaVeOnizle();
});

$(document).on("input", "#seri_bitis", function () {
  // Bitiş girildiğinde adeti hesapla
  $("#seri_adet").val(""); // Adet alanını temizle, bitiş baz alınacak
  hesaplaVeOnizle();
});

// Demirbaş Kaydet
$(document).on("click", "#demirbasKaydet", function () {
  var form = $("#demirbasForm");
  var demirbas_id = $("#demirbas_id").val();
  var seriMod = $('input[name="seri_mod"]:checked').val();

  form.validate({
    rules: {
      demirbas_adi: { required: true },
      kategori_id: { required: true },
      miktar: { required: seriMod !== "toplu", min: 1 },
    },
    messages: {
      demirbas_adi: { required: "Demirbaş adı zorunludur" },
      kategori_id: { required: "Kategori seçimi zorunludur" },
      miktar: {
        required: "Miktar zorunludur",
        min: "Miktar en az 1 olmalıdır",
      },
    },
  });

  if (!form.valid()) return;

  // Toplu seri modunda özel kontrol
  if (seriMod === "toplu" && demirbas_id == 0) {
    let seriler = hesaplaVeOnizle();
    if (seriler.length === 0) {
      Swal.fire({
        icon: "warning",
        title: "Eksik Bilgi!",
        html: "Lütfen <strong>başlangıç seri no</strong> ve <strong>adet</strong> veya <strong>bitiş seri no</strong> giriniz.",
        confirmButtonText: "Tamam",
      });
      return;
    }

    // Kullanıcıya onay iste
    Swal.fire({
      title: "Toplu Kayıt Onayı",
      html: `<strong>${seriler.length}</strong> adet demirbaş kaydı oluşturulacak.<br><small class="text-muted">${seriler[0]} → ${seriler[seriler.length - 1]}</small>`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#34c38f",
      cancelButtonColor: "#74788d",
      confirmButtonText: "Evet, Oluştur!",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        // Toplu kaydet
        var formData = new FormData(form[0]);
        formData.append("action", "demirbas-toplu-kaydet");
        formData.append("seri_listesi", JSON.stringify(seriler));

        // Loading göster
        Swal.fire({
          title: "Kaydediliyor...",
          html: `<strong>${seriler.length}</strong> adet demirbaş oluşturuluyor...`,
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading(),
        });

        fetch(zimmetUrl, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status === "success") {
              $("#demirbasModal").modal("hide");
              resetDemirbasForm();
              Swal.fire({
                icon: "success",
                title: "Başarılı!",
                text: data.message,
                confirmButtonText: "Tamam",
              }).then(() => window.location.reload());
            } else {
              Swal.fire({
                icon: "error",
                title: "Hata!",
                text: data.message,
                confirmButtonText: "Tamam",
              });
            }
          })
          .catch((err) => {
            console.error(err);
            Swal.fire({
              icon: "error",
              title: "Hata!",
              text: "İşlem sırasında bir hata oluştu.",
              confirmButtonText: "Tamam",
            });
          });
      }
    });
    return; // Toplu kayıt onay beklediği için burada dur
  }

  // Tekli kaydet (mevcut mantık)
  var formData = new FormData(form[0]);
  formData.append("action", "demirbas-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        if (demirbas_id == 0) {
          demirbasTable.row.add($(data.son_kayit)).draw(false);
        } else {
          let rowNode = demirbasTable.$(`tr[data-id="${demirbas_id}"]`)[0];
          if (rowNode) {
            demirbasTable.row(rowNode).remove().draw();
            demirbasTable.row.add($(data.son_kayit)).draw(false);
          }
        }
        $("#demirbasModal").modal("hide");
        resetDemirbasForm();

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.reload();
          }
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Hata!",
        text: "İşlem sırasında bir hata oluştu.",
        confirmButtonText: "Tamam",
      });
    });
});

// Demirbaş Düzenle
$(document).on("click", ".duzenle", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  $("#demirbas_id").val(id);

  // Modal içindeki tab'ı ilk sekmeye sıfırla
  $("#demirbasModalTabs a:first").tab("show");

  var formData = new FormData();
  formData.append("action", "demirbas-getir");
  formData.append("demirbas_id", id);

  // Seçenekleri önce yükle, sonra verileri bas
  fetchIsEmriSonuclari(() => {
    fetch(zimmetUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          var d = data.data;
          for (var key in d) {
            if ($("#" + key).length) {
              if (key === "edinme_tutari" && d[key]) {
                // Para formatı
                $("#" + key).val(
                  parseFloat(d[key]).toLocaleString("tr-TR", {
                    minimumFractionDigits: 2,
                  }),
                );
              } else if (
                key === "kategori_id" ||
                key === "durum" ||
                key === "otomatik_zimmet_is_emri" ||
                key === "otomatik_iade_is_emri"
              ) {
                // Select2 alanları için
                $("#" + key)
                  .val(d[key])
                  .trigger("change");
              } else {
                $("#" + key).val(d[key]);
              }
            }
          }
          $("#demirbasModal").modal("show");
        }
      });
  });
});

// Demirbaş Sil
$(document).on("click", ".demirbas-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let name = $(this).data("name");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    html: `<strong>${name}</strong> adlı demirbaşı silmek istediğinizden emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=demirbas-sil&id=${id}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            demirbasTable.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success").then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetDemirbasForm() {
  $("#demirbasForm")[0].reset();
  $("#demirbas_id").val(0);
  $("#kategori_id").val("").trigger("change");
  $("#durum").val("aktif").trigger("change");
  $("#miktar").val(1);
  // Otomatik zimmet ayarları
  $("#otomatik_zimmet_is_emri").val("").trigger("change");
  $("#otomatik_iade_is_emri").val("").trigger("change");
  // Toplu seri alanlarını sıfırla
  $("#seriModTekli").prop("checked", true).trigger("change");
  $("#seriTekliAlani").show();
  $("#seriTopluAlani").hide();
  $("#seri_baslangic, #seri_bitis, #seri_adet").val("");
  $("#seriOnizlemeContainer").hide();
  $("#seriOnizlemeList").empty();
  $("#miktar").closest(".col-md-3").show();
  // Modal içindeki tab'ı ilk sekmeye sıfırla
  $("#demirbasModalTabs a:first").tab("show");
}

// Yeni Sayaç butonuna tıklandığında kategori "Sayaç" olarak pre-select edilsin
$(document).on("click", "#btnYeniSayac", function () {
  resetDemirbasForm();
  setTimeout(() => {
    let sayacOpt = $("#kategori_id option")
      .filter(function () {
        let txt = $(this).text().toLowerCase();
        return txt.includes("sayaç") || txt.includes("sayac");
      })
      .first();
    if (sayacOpt.length > 0) {
      $("#kategori_id").val(sayacOpt.val()).trigger("change");
    }
  }, 100);
});

// Modal kapatıldığında formu sıfırla
$("#demirbasModal").on("hidden.bs.modal", function () {
  resetDemirbasForm();
});

// Modallar açıldığında Feather ikonlarını yenile
$(".modal").on("shown.bs.modal", function () {
  if (typeof feather !== "undefined") {
    feather.replace();
  }
  initSelect2();

  // Eğer demirbasModal ise seçenekleri bir kez daha tazele (opsiyonel ama garanti)
  if ($(this).attr("id") === "demirbasModal") {
    fetchIsEmriSonuclari();
  }
});

// ============== ZİMMET İŞLEMLERİ ==============

// Demirbaş listesinden zimmet ver
$(document).on("click", ".zimmet-ver", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let rawId = $(this).data("raw-id");
  let name = $(this).data("name");
  let kalan = $(this).data("kalan");

  // Formu sıfırla ama select'i kapatacağız
  resetZimmetForm();

  $("#zimmetModal").modal("show");

  // Demirbaş seçimini yap ve kilitle
  if (rawId) {
    $("#demirbas_id_zimmet").val(rawId).trigger("change");
    $("#demirbas_id_zimmet").prop("disabled", true);
  }

  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan);
});

// Demirbaş seçildiğinde kalan miktarı göster
$(document).on("change", "#demirbas_id_zimmet", function () {
  let kalan = $(this).find(":selected").data("kalan") || 0;
  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan).val(1);
});

// Zimmet Kaydet
$(document).on("click", "#zimmetKaydet", function () {
  var form = $("#zimmetForm");

  form.validate({
    rules: {
      demirbas_id: { required: true },
      personel_id: { required: true },
      teslim_miktar: { required: true, min: 1 },
      teslim_tarihi: { required: true },
    },
    messages: {
      demirbas_id: { required: "Demirbaş seçimi zorunludur" },
      personel_id: { required: "Personel seçimi zorunludur" },
      teslim_miktar: { required: "Miktar zorunludur" },
      teslim_tarihi: { required: "Teslim tarihi zorunludur" },
    },
  });

  if (!form.valid()) return;

  // Miktar kontrolü
  let kalan = parseInt(
    $("#demirbas_id_zimmet").find(":selected").data("kalan") || 0,
  );
  let teslimMiktar = parseInt($("#teslim_miktar").val());

  if (teslimMiktar > kalan) {
    Swal.fire({
      icon: "error",
      title: "Yetersiz Stok!",
      text: `Stokta sadece ${kalan} adet bulunmaktadır.`,
      confirmButtonText: "Tamam",
    });
    return;
  }

  // Eğer select disabled ise FormData'ya dahil olmaz, geçici olarak açalım
  let isDisabled = $("#demirbas_id_zimmet").prop("disabled");
  if (isDisabled) $("#demirbas_id_zimmet").prop("disabled", false);

  var formData = new FormData(form[0]);

  // UI tutarlılığı için tekrar kapatalım (modal hala açıksa)
  if (isDisabled) $("#demirbas_id_zimmet").prop("disabled", true);

  formData.append("action", "zimmet-kaydet");

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#zimmetModal").modal("hide");
        resetZimmetForm();

        // Zimmet tablosunu yenile
        loadZimmetList();

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            location.reload();
          }
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    });
});

// Zimmet İade Modal Aç
$(document).on("click", ".zimmet-iade", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let demirbas = $(this).data("demirbas");
  let personel = $(this).data("personel");
  let miktar = $(this).data("miktar");

  $("#iade_zimmet_id").val(id);
  $("#iade_demirbas_adi").text(demirbas);
  $("#iade_personel_adi").text(personel);
  $("#iade_teslim_miktar").text(miktar);
  $("#iade_miktar").val(miktar).attr("max", miktar);

  $("#iadeModal").modal("show");
});

// İade Kaydet
$(document).on("click", "#iadeKaydet", function () {
  var form = $("#iadeForm");

  let iadeMiktar = parseInt($("#iade_miktar").val());
  let teslimMiktar = parseInt($("#iade_teslim_miktar").text());

  if (iadeMiktar > teslimMiktar) {
    Swal.fire({
      icon: "error",
      title: "Hata!",
      text: `İade miktarı teslim edilen miktardan (${teslimMiktar}) fazla olamaz.`,
      confirmButtonText: "Tamam",
    });
    return;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "zimmet-iade");
  formData.append("zimmet_id", $("#iade_zimmet_id").val());

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#iadeModal").modal("hide");

        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then(() => {
          location.reload();
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata!",
          text: data.message,
          confirmButtonText: "Tamam",
        });
      }
    });
});

// Zimmet Sil
$(document).on("click", ".zimmet-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let row = $(this).closest("tr");

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu zimmet kaydını silmek istediğinizden emin misiniz? Stok miktarı güncellenecektir.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch(zimmetUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=zimmet-sil&id=${id}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            zimmetTable.row(row).remove().draw();
            Swal.fire("Silindi!", data.message, "success").then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        });
    }
  });
});

// Form Reset
function resetZimmetForm() {
  $("#zimmetForm")[0].reset();
  $("#zimmet_id").val(0);
  $("#demirbas_id_zimmet").prop("disabled", false).val("").trigger("change");
  $("#personel_id").val("").trigger("change");
  $("#kalanMiktarText").text("-");
  $(".flatpickr").flatpickr({
    dateFormat: "d.m.Y",
    locale: "tr",
    defaultDate: new Date(),
  });
}

// Modal kapatıldığında formu sıfırla
$("#zimmetModal").on("hidden.bs.modal", function () {
  resetZimmetForm();
});

// Zimmet Detay
$(document).on("click", ".zimmet-detay", function (e) {
  e.preventDefault();
  let id = $(this).data("id");

  // Loading göster
  Pace.start();

  var formData = new FormData();
  formData.append("action", "zimmet-detay");
  formData.append("id", id);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        let d = data.data;
        let gecmis = data.gecmis;
        let hareketler = data.hareketler;

        // Üst bilgi kartını doldur
        $("#detay_demirbas_adi").text(d.demirbas_detay.demirbas_adi || "-");
        $("#detay_marka_model").text(
          (d.demirbas_detay.marka || "") + " " + (d.demirbas_detay.model || ""),
        );
        $("#detay_seri_no").text(d.demirbas_detay.seri_no || "-");
        $("#detay_durum_badge").html(d.durum_badge);
        $("#detay_personel").text(d.personel_detay.adi_soyadi || "-");

        // 1. HAREKET DETAYLARI TABLOSUNU DOLDUR
        let hBody = $("#zimmetHareketBody");
        hBody.empty();
        if (hareketler && hareketler.length > 0) {
          hareketler.forEach((h) => {
            let row = `
              <tr>
                <td>${h.hareket_badge}</td>
                <td class="text-center fw-bold">${h.miktar}</td>
                <td>${h.tarih_format}</td>
                <td class="small">${h.aciklama || ""}</td>
                <td>${h.kaynak_badge}</td>
              </tr>
            `;
            hBody.append(row);
          });
        } else {
          hBody.append(
            '<tr><td colspan="5" class="text-center text-muted">Hareket kaydı bulunamadı.</td></tr>',
          );
        }

        // 2. GEÇMİŞ TABLOSUNU DOLDUR
        let tbody = $("#zimmetGecmisBody");
        tbody.empty();
        if (gecmis && gecmis.length > 0) {
          gecmis.forEach((item) => {
            let row = `
              <tr>
                <td>
                  <div class="fw-medium">${item.personel_adi || "-"}</div>
                  <div class="small text-muted">${item.personel_telefon || ""}</div>
                </td>
                <td class="text-center">${item.teslim_miktar}</td>
                <td>${item.teslim_tarihi_format}</td>
                <td>${item.iade_tarihi_format}</td>
                <td class="text-center">${item.durum_badge}</td>
                <td class="small text-muted">${item.aciklama || "-"}</td>
              </tr>
            `;
            tbody.append(row);
          });
        } else {
          tbody.append(
            '<tr><td colspan="6" class="text-center text-muted py-3">Geçmiş kaydı bulunamadı.</td></tr>',
          );
        }

        $("#zimmetDetayModal").modal("show");
      } else {
        Swal.fire("Hata!", data.message, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.close();
      Swal.fire("Hata!", "Bir hata oluştu.", "error");
    });
});

// ============== EXCEL İŞLEMLERİ ==============

// Excel Import Modal Aç
$(document).on("click", "#importExcel", function () {
  $("#importExcelModal").modal("show");
});

// Excel Upload
$(document).on("click", "#btnUploadExcel", function () {
  var formData = new FormData($("#importExcelForm")[0]);
  formData.append("action", "excel-upload");

  if ($("#excelFile").val() == "") {
    Swal.fire("Uyarı", "Lütfen bir dosya seçiniz.", "warning");
    return;
  }

  Swal.fire({
    title: "Yükleniyor...",
    text: "Demirbaş listesi işleniyor, lütfen bekleyin.",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: zimmetUrl,
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (response) {
      try {
        let res = JSON.parse(response);
        if (res.status === "success") {
          Swal.fire({
            title: "Başarılı",
            text: res.message,
            icon: "success",
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      } catch (e) {
        Swal.fire("Hata", "Sunucudan geçersiz yanıt alındı.", "error");
      }
    },
    error: function () {
      Swal.fire("Hata", "İşlem sırasında bir hata oluştu.", "error");
    },
  });
});

// Excel Export
$(document).on("click", "#exportExcel", function () {
  let activeTab = $("#demirbasTab button.active").attr("id");
  let tabName = activeTab === "zimmet-tab" ? "zimmet" : "demirbas";
  let currentTable = tabName === "zimmet" ? zimmetTable : demirbasTable;

  let searchTerm = currentTable.search();
  let url = "views/demirbas/export-excel.php";
  let params = new URLSearchParams();

  params.append("tab", tabName);
  if (searchTerm) {
    params.append("search", searchTerm);
  }

  // Sütun bazlı aramaları ekle (eğer varsa)
  let colSearches = {};
  $(
    `#${tabName === "zimmet" ? "zimmetTable" : "demirbasTable"} .search-input-row input`,
  ).each(function () {
    let val = $(this).val();
    let colIdx = $(this).attr("data-col-idx");
    if (val && colIdx) {
      colSearches[colIdx] = val;
    }
  });

  if (Object.keys(colSearches).length > 0) {
    params.append("col_search", JSON.stringify(colSearches));
  }

  window.location.href = url + "?" + params.toString();
});

// ============== KAŞİYE TESLİM İŞLEMLERİ ==============
$(document).on("click", ".sayac-kasiye-teslim", function (e) {
  e.preventDefault();
  let demirbasId = $(this).data("id");
  let name = $(this).data("name");
  let tr = $(this).closest("tr");
  let seriNo = tr.find("td:eq(4)").text().trim();

  // Alert kutusunu sıfırla
  $("#kasiyeAlert")
    .removeClass("alert-success alert-danger d-block")
    .addClass("d-none")
    .text("");

  // Modal'ı aç ve bilgileri doldur
  $("#kasiye_demirbas_id").val(demirbasId);
  $("#kasiyeSayacAdi").text(name);
  $("#kasiyeSeriNo").text(seriNo ? seriNo : "-");

  $("#kasiyeTeslimModal").modal("show");
});

// Form Gönderimi (Kaskiye Teslim Kaydet)
$(document).on("submit", "#kasiyeTeslimForm", function (e) {
  e.preventDefault();

  let demirbasId = $("#kasiye_demirbas_id").val();
  let tarih = $("#tarih").val();
  let aciklama = $("#aciklama").val();
  let submitBtn = $("#btnKasiyeKaydet");

  if (!demirbasId || !tarih) {
    Swal.fire("Uyarı", "Lütfen gerekli alanları doldurunuz.", "warning");
    return;
  }

  // Submit butonunu yükleniyor yap
  let originalBtnHtml = submitBtn.html();
  submitBtn
    .prop("disabled", true)
    .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

  let formData = new FormData();
  formData.append("action", "kasiye-teslim");
  formData.append("demirbas_id", demirbasId);
  formData.append("tarih", tarih);
  formData.append("aciklama", aciklama);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#kasiyeTeslimModal").modal("hide");
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          confirmButtonText: "Tamam",
        }).then(() => location.reload());
      } else {
        Swal.fire("Hata!", data.message, "error");
        submitBtn.prop("disabled", false).html(originalBtnHtml);
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire("Hata!", "İşlem sırasında bir hata oluştu.", "error");
      submitBtn.prop("disabled", false).html(originalBtnHtml);
    });
});

// Kaskiye Teslim Modal kapandığında formu sıfırla
$("#kasiyeTeslimModal").on("hidden.bs.modal", function () {
  $("#kasiyeTeslimForm")[0].reset();
  $("#kasiyeSayacAdi").text("Sayaç Adı");
  $("#kasiyeSeriNo").text("-");
  $("#btnKasiyeKaydet")
    .prop("disabled", false)
    .html('<i class="bx bx-check-circle me-1"></i> Teslimi Kaydet');
  $("#kasiyeAlert")
    .removeClass("alert-success alert-danger d-block")
    .addClass("d-none")
    .text("");
});

// ============== KASKİYE TESLİM DETAY (DURUM TIKLAMA) ==============
$(document).on("click", ".kaskiye-detay-btn", function (e) {
  e.preventDefault();
  let demirbasId = $(this).data("id");
  let sayacAdi = $(this).data("name") || "Sayaç";
  let seriNo = $(this).data("seri") || "-";

  Swal.fire({
    title: "Lütfen Bekleyin...",
    didOpen: () => {
      Swal.showLoading();
    },
  });

  let formData = new FormData();
  formData.append("action", "demirbas-getir");
  formData.append("demirbas_id", demirbasId);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.status === "success" && data.data) {
        let item = data.data;
        let teslimEden = item.kaskiye_teslim_eden || "Sistem";
        let teslimTarihi = item.kaskiye_teslim_tarihi || "-";
        let aciklama = item.aciklama || "Açıklama belirtilmemiş.";

        if (teslimTarihi !== "-") {
          let parts = teslimTarihi.split("-");
          if (parts.length === 3) {
            teslimTarihi = parts[2] + "." + parts[1] + "." + parts[0];
          }
        }

        Swal.fire({
          title: `<div class="d-flex align-items-center justify-content-center mb-1">
                    <div class="avatar-xs me-2 rounded bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bx bx-info-circle text-dark fs-5"></i>
                    </div>
                    <span class="fw-bold" style="font-size: 1.1rem;">Kaskiye Teslim Detayı</span>
                  </div>`,
          html: `
            <div class="text-center mb-3">
                <h5 class="fw-bold mb-1 text-primary">${sayacAdi}</h5>
                <p class="text-muted small mb-0"><i class="bx bx-barcode"></i> SN: ${seriNo}</p>
            </div>
            <div class="p-3 bg-light rounded-3 text-start border shadow-sm">
                <div class="row g-2">
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.6rem;">Teslim Eden</small>
                        <span class="fw-bold text-dark small">${teslimEden}</span>
                    </div>
                    <div class="col-6 border-start ps-3">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.6rem;">Teslim Tarihi</small>
                        <span class="fw-bold text-dark small">${teslimTarihi}</span>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Açıklama / Not</small>
                        <p class="mb-0 text-dark small font-italic opacity-75">${aciklama}</p>
                    </div>
                </div>
            </div>
          `,
          confirmButtonText: "Kapat",
          confirmButtonColor: "#343a40",
          customClass: {
            container: "my-swal-z-index",
            popup: "rounded-4 shadow-lg border-0",
          },
        });
      } else {
        Swal.fire("Hata!", data.message || "Detaylar getirilemedi.", "error");
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire("Hata!", "Veri çekilirken bir hata oluştu.", "error");
    });
});
// ============== DEMİRBAŞ İŞLEM GEÇMİŞİ ==============
$(document).on("click", ".demirbas-gecmis", function (e) {
  e.preventDefault();
  console.log("Demirbaş geçmiş butonu tıklandı");
  let demirbasId = $(this).data("raw-id");
  let name = $(this).data("name");

  $("#gecmisDemirbasAdi").text(name);
  $("#demirbasGecmisBody").html(
    '<tr><td colspan="6" class="text-center py-3"><i class="bx bx-loader-alt bx-spin fs-4"></i> Yükleniyor...</td></tr>',
  );

  // Modal'ı body'ye taşı (eğer değilse) ve aç
  let modalEl = $("#demirbasGecmisModal");
  if (modalEl.length) {
    // Modal trapped ise body'ye taşı
    if (modalEl.parent().prop("tagName") !== "BODY") {
      modalEl.appendTo("body");
    }

    try {
      modalEl.modal("show");
    } catch (err) {
      console.warn("jQuery modal fail, constructor denenecek", err);
      if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        let bModal =
          bootstrap.Modal.getInstance(modalEl[0]) ||
          new bootstrap.Modal(modalEl[0]);
        bModal.show();
      }
    }
  } else {
    console.error("Hata: demirbasGecmisModal bulunamadı!");
  }

  let formData = new FormData();
  formData.append("action", "hareket-gecmisi");
  formData.append("demirbas_id", demirbasId);

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      console.log("Hareket geçmişi verisi alındı:", data);
      if (data.status === "success") {
        let hBody = $("#demirbasGecmisBody");
        hBody.empty();

        if (data.hareketler && data.hareketler.length > 0) {
          data.hareketler.forEach((h) => {
            let row = `
                        <tr>
                            <td>${h.hareket_badge}</td>
                            <td class="text-center fw-bold">${h.miktar}</td>
                            <td>${h.tarih_format}</td>
                            <td>${h.personel_adi || "-"}</td>
                            <td class="small">${h.aciklama || ""}</td>
                            <td class="text-end small">${h.islem_yapan_adi || h.kaynak_badge || "-"}</td>
                        </tr>
                    `;
            hBody.append(row);
          });
        } else {
          hBody.html(
            '<tr><td colspan="6" class="text-center text-muted py-3">Bu demirbaşa ait işlem kaydı bulunamadı.</td></tr>',
          );
        }
      } else {
        Swal.fire(
          "Hata!",
          data.message || "Geçmiş verileri alınamadı.",
          "error",
        );
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire("Hata!", "Veri çekilirken bir hata oluştu.", "error");
    });
});
