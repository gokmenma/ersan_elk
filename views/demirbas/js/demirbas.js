var zimmetUrl = "views/demirbas/api.php";
var demirbasTable,
  zimmetTable,
  depoPersonelTable,
  hurdaDemirbasTable,
  sayacTable,
  aparatTable,
  servisTable;

// ============== SAYFA YÜKLENDİĞİNDE ==============
$(document).ready(function () {
  let demirbasOptions = {
    ...getDatatableOptions(),
    serverSide: true,
    ajax: {
      url: zimmetUrl,
      type: "POST",
      data: function (d) {
        d.action = "demirbas-listesi";
        d.tab = "demirbas";
        d.inventory_kat_adi = $("#activeFilterBadges").data("katAdi") || null;
        d.inventory_type = $("#activeFilterBadges").data("filterType") || null;
      },
    },
    columns: [
      {
        data: "checkbox",
        className: "text-center",
        orderable: false,
        searchable: false,
      },
      {
        data: "id",
        className: "text-center",
        orderable: true,
        searchable: false,
      },
      { data: "demirbas_no", className: "text-center" },
      { data: "kategori_adi" },
      { data: "demirbas_adi" },
      { data: "marka_model" },
      {
        data: "stok",
        className: "text-center",
        orderable: false,
        searchable: false,
      },
      { data: "durum", className: "text-center" },
      {
        data: "tutar",
        className: "text-end",
        orderable: false,
        searchable: false,
      },
      { data: "tarih", orderable: false, searchable: false },
      {
        data: "islemler",
        className: "text-center",
        orderable: false,
        searchable: false,
      },
    ],
    order: [[1, "desc"]],
    createdRow: function (row, data, dataIndex) {
      if (data.DT_RowData) {
        $(row).attr("data-id", data.DT_RowData.id);
        $(row).attr("data-kat-adi", data.DT_RowData["kat-adi"]);
        $(row).attr("data-durum", data.DT_RowData.durum);
        $(row).attr("data-bosta", data.DT_RowData.bosta);
        $(row).attr("data-zimmetli", data.DT_RowData.zimmetli);
      }
    },
    language: {
      ...getDatatableOptions().language,
      emptyTable:
        '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz demirbaş eklenmemiş.<br><small>"Yeni Demirbaş" butonuna tıklayarak ekleyebilirsiniz.</small></div>',
    },
  };

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
        d.filter_type = $('input[name="zimmetFilter"]:checked').val() || "all";
        d.personel_id = $("#zimmet_personel_filtre").val() || "all";
        d.sayac_kat_ids = typeof sayacKatIds !== "undefined" ? sayacKatIds : [];
        d.aparat_kat_ids =
          typeof aparatKatIds !== "undefined" ? aparatKatIds : [];
      },
    },
    columns: [
      {
        data: "checkbox",
        className: "text-center",
        orderable: false,
        searchable: false,
      },
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
    let sayacOptions = {
      ...getDatatableOptions(),
      serverSide: true,
      ajax: {
        url: zimmetUrl,
        type: "POST",
        data: function (d) {
          d.action = "demirbas-listesi";
          d.tab = "sayac";
        },
      },
      columns: [
        {
          data: "checkbox",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        {
          data: "id",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        { data: "demirbas_no", className: "text-center" },
        { data: "demirbas_adi" },
        { data: "marka_sade" },
        { data: "seri_no" },
        {
          data: "stok",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        { data: "durum", className: "text-center" },
        { data: "tarih", orderable: false, searchable: false },
        {
          data: "islemler",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
      ],
      order: [[1, "desc"]],
      createdRow: function (row, data, dataIndex) {
        if (data.DT_RowData) {
          $(row).attr("data-id", data.DT_RowData.id);
          $(row).attr("data-kat-adi", data.DT_RowData["kat-adi"]);
          $(row).attr("data-durum", data.DT_RowData.durum);
          $(row).attr("data-bosta", data.DT_RowData.bosta);
          $(row).attr("data-zimmetli", data.DT_RowData.zimmetli);
        }
      },
      language: {
        ...getDatatableOptions().language,
        emptyTable:
          '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz sayaç eklenmemiş.<br><small>"Yeni Sayaç" butonuna tıklayarak ekleyebilirsiniz.</small></div>',
      },
    };
    sayacTable = $("#sayacTable").DataTable(sayacOptions);
  }

  // Aparat Tablosu
  if ($("#aparatTable").length) {
    let aparatOptions = {
      ...getDatatableOptions(),
      serverSide: true,
      ajax: {
        url: zimmetUrl,
        type: "POST",
        data: function (d) {
          d.action = "demirbas-listesi";
          d.tab = "aparat";
        },
      },
      columns: [
        {
          data: "checkbox",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        {
          data: "id",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        { data: "demirbas_no", className: "text-center" },
        { data: "demirbas_adi" },
        { data: "marka_sade" },
        { data: "seri_no" },
        {
          data: "stok",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
        { data: "durum", className: "text-center" },
        { data: "tarih", orderable: false, searchable: false },
        {
          data: "islemler",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
      ],
      order: [[1, "desc"]],
      createdRow: function (row, data, dataIndex) {
        if (data.DT_RowData) {
          $(row).attr("data-id", data.DT_RowData.id);
          $(row).attr("data-kat-adi", data.DT_RowData["kat-adi"]);
          $(row).attr("data-durum", data.DT_RowData.durum);
          $(row).attr("data-bosta", data.DT_RowData.bosta);
          $(row).attr("data-zimmetli", data.DT_RowData.zimmetli);
        }
      },
      language: {
        ...getDatatableOptions().language,
        emptyTable:
          '<div class="text-center text-muted py-4"><i class="bx bx-package display-4 d-block mb-2"></i>Henüz aparat eklenmemiş.<br><small>"Yeni Aparat" butonuna tıklayarak ekleyebilirsiniz.</small></div>',
      },
    };
    aparatTable = $("#aparatTable").DataTable(aparatOptions);
  }

  // Servis Tablosu
  if ($("#servisTable").length) {
    servisTable = $("#servisTable").DataTable({
      ...getDatatableOptions(),
      serverSide: true,
      ajax: {
        url: zimmetUrl,
        type: "POST",
        data: function (d) {
          d.action = "servis-listesi";
          d.baslangic = $("#servis_filtre_baslangic").val();
          d.bitis = $("#servis_filtre_bitis").val();
        },
      },
      columns: [
        { data: "sira", className: "text-center" },
        { data: "demirbas_adi" },
        { data: "servis_tarihi", className: "text-center" },
        { data: "iade_tarihi", className: "text-center" },
        { data: "servis_adi" },
        { data: "teslim_eden" },
        { data: "islem_detay" },
        { data: "tutar", className: "text-end" },
        { data: "islemler", className: "text-center", orderable: false },
      ],
      order: [[2, "desc"]],
      language: {
        ...getDatatableOptions().language,
        emptyTable:
          '<div class="text-center text-muted py-4"><i class="bx bx-wrench display-4 d-block mb-2"></i>Herhangi bir servis kaydı bulunamadı.</div>',
      },
    });
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

  if ($("#servis-tab").hasClass("active")) {
    loadServisList();
  }
});

function updateButtonVisibility() {
  let activeTabBtn = $("#demirbasTab button.active");
  if (activeTabBtn.length === 0) return;

  let activeTab = activeTabBtn.attr("id");
  // Tüm ana aksiyon butonlarını gizle
  $(
    "#btnYeniDemirbas, #btnZimmetVer, #btnYeniSayac, #btnTopluKaskiyeTeslim, #btnYeniAparat, #btnAparatPersoneleVer, #btnYeniServis",
  )
    .addClass("d-none")
    .removeClass("d-flex");
  $("#importExcelLi").addClass("d-none");
  $("#topluZimmetSilLi").addClass("d-none");
  $("#topluIadeLi").addClass("d-none");
  $("#topluDemirbasSilLi").addClass("d-none");
  $("#zimmetIslemlerDivider").addClass("d-none");
  $("#hurdaIadeLi, #hurdaIadeButonLi").addClass("d-none");

  if (activeTab === "demirbas-tab") {
    $("#btnYeniDemirbas").removeClass("d-none").addClass("d-flex");
    $("#importExcelLi").removeClass("d-none");
    $("#topluDemirbasSilLi").removeClass("d-none");
  } else if (activeTab === "zimmet-tab") {
    $("#btnZimmetVer").removeClass("d-none").addClass("d-flex");
    $("#topluZimmetSilLi").removeClass("d-none");
    $("#topluIadeLi").removeClass("d-none");
    $("#zimmetIslemlerDivider").removeClass("d-none");
    if (typeof zimmetTable !== "undefined") {
      zimmetTable.ajax.reload(null, false);
    }
  } else if (activeTab === "depo-tab") {
    $("#btnYeniSayac").removeClass("d-none").addClass("d-flex");
    $("#btnTopluKaskiyeTeslim").removeClass("d-none").addClass("d-flex");
    $("#topluDemirbasSilLi").removeClass("d-none");
    $("#hurdaIadeLi, #hurdaIadeButonLi").removeClass("d-none");
  } else if (activeTab === "aparat-tab") {
    $("#btnYeniAparat").removeClass("d-none").addClass("d-flex");
    $("#btnAparatPersoneleVer").removeClass("d-none").addClass("d-flex");
    $("#topluDemirbasSilLi").removeClass("d-none");
  } else if (activeTab === "servis-tab") {
    $("#btnYeniServis").removeClass("d-none").addClass("d-flex");
    if (typeof servisTable !== "undefined") {
      servisTable.ajax.reload(null, false);
    }
  }
}

// ============== GENEL BUTON TIKLAMA OLAYLARI ==============
$(document).on("click", "#btnZimmetVer", function () {
  resetZimmetForm();
});

$(document).on("click", "#btnYeniDemirbas", function () {
  resetDemirbasForm();
});

// Aparat sekmesinden "Personele Ver" butonuna tıklandığında
$(document).on("click", "#btnAparatPersoneleVer", function () {
  // Seçili aparatları kontrol et
  let seciliAparatlar = [];
  $("#aparatTable .sayac-select:checked").each(function () {
    let row = $(this).closest("tr");
    let encId = $(this).val();
    // Checkbox'un id'si "chk_123" formatında, buradan raw id'yi çıkart
    let checkboxId = $(this).attr("id") || "";
    let rawId = checkboxId.replace("chk_", "") || encId;

    // DataTable'dan row data al
    let rowData = null;
    if (typeof aparatTable !== "undefined") {
      try {
        rowData = aparatTable.row(row).data();
      } catch (e) {}
    }

    // Row'dan bilgileri çıkart
    let name = "";
    let marka = "";
    let kalan = 0;

    if (rowData) {
      // DataTable data nesnesinden al
      let nameHtml = rowData.demirbas_adi || "";
      name = $("<div>").html(nameHtml).text().trim();
      let markaHtml = rowData.marka_sade || "";
      marka = $("<div>").html(markaHtml).text().trim();
      // Stok badge'dan kalan miktarı çıkart
      let stokHtml = rowData.stok || "";
      let stokText = $("<div>").html(stokHtml).text().trim();
      // "448/446" veya "Stok Yok" veya "Stok Azaldı (10/8)"
      let stokMatch = stokText.match(/(\d+)\s*\/\s*\d+/);
      if (stokMatch) {
        kalan = parseInt(stokMatch[1]) || 0;
      }
    } else {
      // Fallback: DOM'dan çıkart
      let cells = row.find("td");
      name = cells.eq(3).text().trim();
      marka = cells.eq(4).text().trim();
      let stokText = cells.eq(6).text().trim();
      let stokMatch = stokText.match(/(\d+)\s*\/\s*\d+/);
      if (stokMatch) {
        kalan = parseInt(stokMatch[1]) || 0;
      }
    }

    if (kalan > 0) {
      seciliAparatlar.push({
        enc_id: encId,
        raw_id: rawId,
        name: name,
        marka: marka,
        kalan: kalan,
        miktar: 1,
      });
    }
  });

  if (seciliAparatlar.length === 0) {
    // Hiç seçim yok → eski davranış: zimmet modalını aç, aparat türü seçili gelsin
    resetZimmetForm();
    setTimeout(function () {
      $("#zimmetTurAparat").prop("checked", true).trigger("change");
    }, 150);
    return;
  }

  if (seciliAparatlar.length === 1) {
    // Tek seçim → mevcut zimmet modalını aç, seçili aparat ile
    let aparat = seciliAparatlar[0];
    resetZimmetForm(false);

    $('input[name="zimmet_turu"][value="aparat"]')
      .prop("checked", true)
      .trigger("change");

    $("#zimmetModal").modal("show");

    // Demirbaş seçimini yap
    setTimeout(function () {
      // AJAX select2'ye option ekle
      if (
        $("#demirbas_id_zimmet option[value='" + aparat.raw_id + "']")
          .length === 0
      ) {
        let optText =
          aparat.name + (aparat.marka ? " [" + aparat.marka + "]" : "");
        var newOption = new Option(optText, aparat.raw_id, true, true);
        $("#demirbas_id_zimmet").append(newOption);
      }
      $("#demirbas_id_zimmet").val(aparat.raw_id).trigger("change");
      $("#demirbas_id_zimmet option:selected").data("kalan", aparat.kalan);
      $("#demirbas_id_zimmet").prop("disabled", true);
      $("#kalanMiktarText").text(aparat.kalan);
      $("#teslim_miktar").attr("max", aparat.kalan);
    }, 200);
    return;
  }

  // Çoklu seçim → Toplu Aparat Zimmet Modalını aç
  openTopluAparatZimmetModal(seciliAparatlar);
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
  else if (activeTab === "aparat-tab") targetTable = aparatTable;

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
      ajax: {
        url: zimmetUrl,
        type: "POST",
        dataType: "json",
        delay: 250,
        data: function (params) {
          return {
            action: "demirbas-ara",
            q: params.term,
            type: $('input[name="zimmet_turu"]:checked').val() || "demirbas",
            page: params.page || 1,
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;
          return {
            results: data.results, // Use data.results instead of data.items
          };
        },
        cache: true,
      },
      minimumInputLength: 0,
      templateResult: function (repo) {
        if (repo.loading) return repo.text;
        return repo.text;
      },
      templateSelection: function (repo) {
        return repo.text || repo.id;
      },
    });
  }

  if ($("#personel_id").length) {
    $("#personel_id").select2({
      dropdownParent: $("#zimmetModal"),
      placeholder: "Personel arayın...",
      allowClear: true,
      width: "100%",
      ajax: {
        url: zimmetUrl,
        type: "POST",
        dataType: "json",
        delay: 250,
        data: function (params) {
          return {
            action: "personel-ara",
            q: params.term,
            type: $('input[name="personel_turu"]:checked').val() || "all",
          };
        },
        processResults: function (data) {
          return {
            results: data.results,
          };
        },
        cache: true,
      },
      minimumInputLength: 0,
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

  // Otomatik Zimmet Ayarları Select2'leri (hepsi multiple)
  if ($("#otomatik_zimmet_is_emri_ids").length) {
    $("#otomatik_zimmet_is_emri_ids").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#otomatik_iade_is_emri_ids").length) {
    $("#otomatik_iade_is_emri_ids").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#otomatik_zimmetten_dus_is_emri_ids").length) {
    $("#otomatik_zimmetten_dus_is_emri_ids").select2({
      dropdownParent: $("#demirbasModal"),
      placeholder: "Seçiniz (Yok)",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#servis_demirbas_id").length) {
    $("#servis_demirbas_id").select2({
      dropdownParent: $("#servisModal"),
      placeholder: "Demirbaş Seçin...",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#teslim_eden_personel_id").length) {
    $("#teslim_eden_personel_id").select2({
      dropdownParent: $("#servisModal"),
      placeholder: "Personel Seçin...",
      allowClear: true,
      width: "100%",
    });
  }

  if ($("#zimmet_personel_filtre").length) {
    $("#zimmet_personel_filtre").select2({
      placeholder: "Personel seçin...",
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
    } else if (
      activeTab === "aparat-tab" &&
      typeof aparatKatIds !== "undefined"
    ) {
      // SADECE APARATLAR
      $kategoriSelect.find("option").each(function () {
        const val = $(this).val();
        if (val !== "" && !aparatKatIds.includes(val.toString())) {
          $(this).prop("disabled", true).hide();
        }
      });

      if (demirbasId == "0") {
        const currentVal = $kategoriSelect.val();
        if (!currentVal || !aparatKatIds.includes(currentVal.toString())) {
          const firstAparat = $kategoriSelect
            .find("option")
            .filter(function () {
              return aparatKatIds.includes($(this).val().toString());
            })
            .first()
            .val();
          $kategoriSelect.val(firstAparat).trigger("change");
        }
      }
    } else {
      // SAYAÇLAR VE APARATLAR HARİÇ HER ŞEY
      $kategoriSelect.find("option").each(function () {
        const val = $(this).val();
        const isSayac =
          typeof sayacKatIds !== "undefined" &&
          sayacKatIds.includes(val.toString());
        const isAparat =
          typeof aparatKatIds !== "undefined" &&
          aparatKatIds.includes(val.toString());

        if (val !== "" && (isSayac || isAparat)) {
          $(this).prop("disabled", true).hide();
        }
      });

      // Yeni kayıt ise: Eğer şu anki seçim bir sayaç veya aparat ise ilk uygun olanı seç
      if (demirbasId == "0") {
        const currentVal = $kategoriSelect.val();
        const isSayac =
          typeof sayacKatIds !== "undefined" &&
          sayacKatIds.includes(currentVal?.toString());
        const isAparat =
          typeof aparatKatIds !== "undefined" &&
          aparatKatIds.includes(currentVal?.toString());

        if (!currentVal || isSayac || isAparat) {
          const firstDemirbas = $kategoriSelect
            .find("option")
            .filter(function () {
              const v = $(this).val();
              const vSayac =
                typeof sayacKatIds !== "undefined" &&
                sayacKatIds.includes(v.toString());
              const vAparat =
                typeof aparatKatIds !== "undefined" &&
                aparatKatIds.includes(v.toString());
              return v !== "" && !vSayac && !vAparat;
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
// PHP tarafında yüklendiği için artık JS den çekmeye gerek yok, ancak başka yer kullanıyorsa kalabilir.
function fetchIsEmriSonuclari(callback) {
  if (typeof callback === "function") callback();
}

// ============== TAB DEĞİŞİKLİĞİNDE ==============
$(document).on(
  "click",
  "#demirbas-tab, #zimmet-tab, #depo-tab, #aparat-tab, #servis-tab",
  function () {
    let tabMap = {
      "demirbas-tab": "demirbas",
      "zimmet-tab": "zimmet",
      "depo-tab": "depo",
      "aparat-tab": "aparat",
      "servis-tab": "servis",
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
    } else if (this.id === "servis-tab") {
      loadServisList();
    }
  },
);

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
  if ($("#durum").prop("disabled")) {
    formData.append("durum", $("#durum").val());
  }
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
              } else if (key === "kategori_id" || key === "durum") {
                // Select2 alanları için
                $("#" + key)
                  .val(d[key])
                  .trigger("change");

                if (key === "durum") {
                  if (d[key] === "serviste") {
                    $("#" + key).prop("disabled", true);
                  } else {
                    $("#" + key).prop("disabled", false);
                  }
                }
              } else if (
                key === "otomatik_zimmet_is_emri_ids" ||
                key === "otomatik_iade_is_emri_ids" ||
                key === "otomatik_zimmetten_dus_is_emri_ids"
              ) {
                // Çoklu select2 - virgülle ayrılmış ID'leri diziye çevir
                if (d[key]) {
                  let ids = String(d[key])
                    .split(",")
                    .map((s) => s.trim())
                    .filter((s) => s !== "");
                  $("#" + key)
                    .val(ids)
                    .trigger("change");
                } else {
                  $("#" + key)
                    .val(null)
                    .trigger("change");
                }
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
  $("#durum").prop("disabled", false).val("aktif").trigger("change");
  $("#miktar").val(1);
  $("#minimun_stok_uyari_miktari").val(0);
  // Otomatik zimmet ayarları (hepsi multiple)
  $("#otomatik_zimmet_is_emri_ids").val(null).trigger("change");
  $("#otomatik_iade_is_emri_ids").val(null).trigger("change");
  $("#otomatik_zimmetten_dus_is_emri_ids").val(null).trigger("change");
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

// Personel Türü Radio Button Değişikliği
$(document).on("change", 'input[name="personel_turu"]', function () {
  $("#personel_id").val(null).trigger("change");
});

// Zimmet Türü Radio Button Değişikliği
$(document).on("change", 'input[name="zimmet_turu"]', function () {
  let type = $(this).val();
  filterZimmetOptions(type);
});

function filterZimmetOptions(type) {
  // AJAX kullandığımız için sadece seçimi temizliyoruz.
  // Select2, açıldığında 'type' parametresini (radio buton) okuyarak sunucudan doğru veriyi çekecek.
  $("#demirbas_id_zimmet").val(null).trigger("change");

  // Koli Modu Göster/Gizle
  if (type === "sayac") {
    $("#koliModuWrapper").removeClass("d-none");
    $("#personelTuruWrapper").removeClass("d-none");
  } else {
    $("#koliModuWrapper").addClass("d-none");
    $("#personelTuruWrapper").addClass("d-none");

    // Filtreyi sıfırla (Tüm Personeller)
    $("#personelTuruTum").prop("checked", true).trigger("change");

    if ($("#koliModuToggle").is(":checked")) {
      $("#koliModuToggle").prop("checked", false).trigger("change");
    }
  }
}

// Koli Modu için Global Değişkenler
let koliListesi = [];

// Koli Modu Toggle
$(document).on("change", "#koliModuToggle", function () {
  if ($(this).is(":checked")) {
    $("#tekliSecimAlani").addClass("d-none");
    $("#koliSecimAlani").removeClass("d-none");
    // Miktarı 10'a sabitle ve gizle (veya disable et)
    $("#teslim_miktar").val(10).prop("readonly", true);
    $("#kalanMiktarText").text("-"); // Koli modunda kalan anlamsız
    $("#demirbas_id_zimmet").removeAttr("required"); // HTML5 validation'ı engellemek için
    koliListesi = [];
    renderKoliListesi();
    $("#koli_baslangic_seri").val("").focus();
  } else {
    $("#tekliSecimAlani").removeClass("d-none");
    $("#koliSecimAlani").addClass("d-none");
    $("#teslim_miktar").val(1).prop("readonly", false);
    $("#demirbas_id_zimmet").attr("required", true);
  }
});

// Koli Ekleme Fonksiyonu
function koliEkle(inputVal) {
  if (!inputVal) return;

  // Virgülle ayrılmış girişleri destekle
  let girisler = inputVal.split(/[\s,]+/);
  let eklendi = false;

  girisler.forEach((seri) => {
    seri = seri.trim();
    if (seri.length < 3) return;

    // Zaten ekli mi?
    if (koliListesi.some((k) => k.baslangic === seri)) {
      return;
    }

    let koliObj = {
      id:
        "koli_" + new Date().getTime() + "_" + Math.floor(Math.random() * 1000),
      baslangic: seri,
      durum: "bekliyor",
      mesaj: "Kontrol ediliyor...",
      uygunSayisi: 0,
      seriler: [],
    };

    koliListesi.push(koliObj);
    eklendi = true;
    koliKontrolEt(koliObj);
  });

  if (eklendi) {
    renderKoliListesi();
    $("#koli_baslangic_seri").val("");
  }
}

// Koli Ekle Butonu
$(document).on("click", "#btnKoliEkle", function () {
  koliEkle($("#koli_baslangic_seri").val());
});

// Enter tuşu ile ekleme
$(document).on("keypress", "#koli_baslangic_seri", function (e) {
  if (e.which === 13) {
    e.preventDefault();
    koliEkle($(this).val());
  }
});

// Koli Silme
$(document).on("click", ".koli-sil", function () {
  let id = $(this).data("id");
  koliListesi = koliListesi.filter((k) => k.id !== id);
  renderKoliListesi();
});

// Koli Listesini Render Et
function renderKoliListesi() {
  let $liste = $("#eklenenKolilerListesi");
  let $info = $("#toplamKoliBilgisi");

  $liste.empty();

  if (koliListesi.length === 0) {
    $liste.addClass("d-none");
    $info.addClass("d-none");
    // Eğer koli modundaysak ve liste boşsa butonu pasif yap
    if ($("#koliModuToggle").is(":checked")) {
      $("#zimmetKaydet").prop("disabled", true);
    }
    return;
  }

  $liste.removeClass("d-none");
  $info.removeClass("d-none");

  let toplamKoli = koliListesi.length;
  let toplamSayac = toplamKoli * 10;
  let hepsiUygun = true;

  koliListesi.forEach((koli) => {
    let badgeClass = "bg-secondary";
    let icon = "bx-loader-alt bx-spin";

    if (koli.durum === "uygun") {
      badgeClass = "bg-success";
      icon = "bx-check";
    } else if (koli.durum === "hatali") {
      badgeClass = "bg-danger";
      icon = "bx-x";
      hepsiUygun = false;
    } else {
      hepsiUygun = false;
    }

    let html = `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0"><i class="bx bx-package me-1"></i>${koli.baslangic} <small class="text-muted">(10 Adet)</small></h6>
                    <small class="${koli.durum === "uygun" ? "text-success" : koli.durum === "hatali" ? "text-danger" : "text-muted"}">
                        <i class='bx ${icon}'></i> ${koli.mesaj}
                    </small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger koli-sil" data-id="${koli.id}"><i class="bx bx-trash"></i></button>
            </div>
        `;
    $liste.append(html);
  });

  $("#lblToplamKoli").text(toplamKoli);
  $("#lblToplamSayac").text(toplamSayac);

  // Eğer koli modundaysak ve hepsi uygunsa butonu aç
  if ($("#koliModuToggle").is(":checked")) {
    $("#zimmetKaydet").prop("disabled", !hepsiUygun);
  }
}

// Backend Kontrolü
function koliKontrolEt(koli) {
  let parsed = parseSeriNo(koli.baslangic);
  if (!parsed) {
    koli.durum = "hatali";
    koli.mesaj = "Geçersiz seri no formatı";
    renderKoliListesi();
    return;
  }

  let seriler = [];
  for (let i = 0; i < 10; i++) {
    seriler.push(buildSeriNo(parsed.prefix, parsed.number + i, parsed.digits));
  }
  koli.seriler = seriler;

  $.post(
    zimmetUrl,
    {
      action: "koli-kontrol",
      seriler: JSON.stringify(seriler),
    },
    function (res) {
      let data = typeof res === "string" ? JSON.parse(res) : res;

      if (data.status === "success") {
        let sonuclar = data.data;
        let uygunSayisi = 0;

        seriler.forEach((seri) => {
          let info = sonuclar[seri];
          if (info && info.status === "ok") {
            uygunSayisi++;
          }
        });

        koli.uygunSayisi = uygunSayisi;

        if (uygunSayisi === 10) {
          koli.durum = "uygun";
          koli.mesaj = "10/10 Uygun";
        } else {
          koli.durum = "hatali";
          koli.mesaj = `${uygunSayisi}/10 Uygun - Stok kontrol ediniz`;
        }
      } else {
        koli.durum = "hatali";
        koli.mesaj = "Sunucu hatası";
      }
      renderKoliListesi();
    },
  );
}

// Demirbaş listesinden zimmet ver
$(document).on("click", ".zimmet-ver", function (e) {
  e.preventDefault();
  let id = $(this).data("id");
  let rawId = $(this).data("raw-id");
  let name = $(this).data("name");
  let kalan = $(this).data("kalan");

  // Hangi tablodan tıklandığını bulup türü belirle
  let tableId = $(this).closest("table").attr("id");
  let type = "demirbas";
  if (tableId === "sayacTable") type = "sayac";
  else if (tableId === "aparatTable") type = "aparat";

  // Formu sıfırla (tür seçimini atla)
  resetZimmetForm(false);

  // Türü ayarla
  $('input[name="zimmet_turu"][value="' + type + '"]')
    .prop("checked", true)
    .trigger("change");

  $("#zimmetModal").modal("show");

  // Demirbaş seçimini yap ve kilitle (AJAX olduğu için Option'ı manuel ekle)
  if (rawId) {
    // Eğer seçenek zaten varsa (nadir) onu seç, yoksa oluştur
    if ($("#demirbas_id_zimmet option[value='" + rawId + "']").length === 0) {
      var newOption = new Option(name, rawId, true, true);
      $("#demirbas_id_zimmet").append(newOption);
    }
    $("#demirbas_id_zimmet").val(rawId).trigger("change");

    // Kalan bilgisini seçili elemana ekle (data-kalan)
    $("#demirbas_id_zimmet option:selected").data("kalan", kalan);

    $("#demirbas_id_zimmet").prop("disabled", true);
  }

  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan);
});

// Demirbaş seçildiğinde kalan miktarı göster
$(document).on("change", "#demirbas_id_zimmet", function () {
  let selectedData = $(this).select2("data")[0];
  let kalan = 0;
  if (selectedData) {
    if (selectedData.kalan_miktar !== undefined) {
      kalan = selectedData.kalan_miktar;
    } else {
      kalan = $(this).find(":selected").data("kalan") || 0;
    }
  }

  $("#kalanMiktarText").text(kalan);
  $("#teslim_miktar").attr("max", kalan).val(1);
});

// Zimmet Kaydet
$(document).on("click", "#zimmetKaydet", function () {
  var form = $("#zimmetForm");

  // Koli Modu Kontrolü
  if ($("#koliModuToggle").is(":checked")) {
    let personel_id = $("#personel_id").val();
    let teslim_tarihi = $("#teslim_tarihi").val();

    if (koliListesi.length === 0) {
      Swal.fire("Hata", "Lütfen en az bir koli ekleyin.", "warning");
      return;
    }

    // Hepsinin uygun olduğundan emin ol
    if (koliListesi.some((k) => k.durum !== "uygun")) {
      Swal.fire(
        "Hata",
        "Listede uygun olmayan koliler var. Lütfen kontrol ediniz.",
        "warning",
      );
      return;
    }

    if (!personel_id) {
      Swal.fire("Hata", "Lütfen personel seçiniz.", "warning");
      return;
    }
    if (!teslim_tarihi) {
      Swal.fire("Hata", "Teslim tarihi zorunludur.", "warning");
      return;
    }

    // Backend'e gönderilecek veri: Sadece başlangıç serileri listesi
    let baslangicSerileri = koliListesi.map((k) => k.baslangic);

    var formData = new FormData();
    formData.append("action", "zimmet-koli-kaydet-coklu"); // Yeni action
    formData.append("koli_baslangiclar", JSON.stringify(baslangicSerileri));
    formData.append("personel_id", personel_id);
    formData.append("teslim_tarihi", teslim_tarihi);
    formData.append("aciklama", $("#aciklama").val());

    // Disable button
    let $btn = $(this);
    $btn
      .prop("disabled", true)
      .html('<i class="bx bx-loader-alt bx-spin"></i> Kaydediliyor...');

    fetch(zimmetUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        $btn
          .prop("disabled", false)
          .html('<i data-feather="check-square" class="me-1"></i>Zimmet Ver');
        if (typeof feather !== "undefined") feather.replace();

        if (data.status === "success") {
          $("#zimmetModal").modal("hide");
          resetZimmetForm();
          loadZimmetList();
          Swal.fire({
            icon: "success",
            title: "Başarılı!",
            text: data.message,
            confirmButtonText: "Tamam",
          }).then((result) => {
            if (result.isConfirmed) location.reload();
          });
        } else {
          Swal.fire("Hata!", data.message, "error");
        }
      })
      .catch((err) => {
        console.error(err);
        $btn
          .prop("disabled", false)
          .html('<i data-feather="check-square" class="me-1"></i>Zimmet Ver');
        if (typeof feather !== "undefined") feather.replace();
        Swal.fire("Hata!", "Bir hata oluştu.", "error");
      });

    return;
  }

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
function resetZimmetForm(setDefaultType = true) {
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

  if (setDefaultType) {
    // Default olarak Demirbaş seç
    $("#zimmetTurDemirbas").prop("checked", true);
    // filterZimmetOptions'a gerek yok, zaten change tetiklenince select2 sıfırlanır
    $("#demirbas_id_zimmet").val(null).trigger("change");

    // Koli modunu sıfırla
    $("#koliModuToggle").prop("checked", false).trigger("change");
    $("#koliModuWrapper").addClass("d-none");

    // Personel filtre modunu sıfırla
    $("#personelTuruWrapper").addClass("d-none");
    $("#personelTuruTum").prop("checked", true);
  } else {
    // Eğer setDefaultType false ise (örn: listeden tıklandıysa),
    // yine de koli modunu kapatmamız lazım çünkü listeden tekil seçim yapıldı
    $("#koliModuToggle").prop("checked", false).trigger("change");
  }
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

        // Üst bilgi kartını ve özet kartlarını doldur
        $("#detay_demirbas_adi").text(d.demirbas_detay.demirbas_adi || "-");
        $("#detay_marka_model").text(
          (d.demirbas_detay.marka || "") + " " + (d.demirbas_detay.model || ""),
        );
        $("#detay_seri_no").text(d.demirbas_detay.seri_no || "-");
        $("#detay_durum_badge").html(d.durum_badge);
        $("#detay_personel_adi").text(d.personel_detay.adi_soyadi || "-");

        // Özet Hesabı (Zimmet Detayı)
        let toplamZimmet = parseInt(d.teslim_miktar || 0);
        let tuketilen = parseInt(d.iade_miktar || 0); // Bu iade_miktar tüketilen (sarf) kısımdır
        let kalan = toplamZimmet - tuketilen;

        $("#ozet_toplam").text(toplamZimmet);
        $("#ozet_tuketilen").text(tuketilen);
        $("#ozet_kalan").text(kalan);

        // 1. HAREKET DETAYLARI TABLOSUNU DOLDUR
        let hBody = $("#zimmetHareketBody");
        hBody.empty();
        if (hareketler && hareketler.length > 0) {
          // getZimmetHareketleri ASC (eskiden yeniye) geliyor.
          // İlk kayıt ana zimmet kaydıdır, onu yukarıda gösterdiğimiz için tabloda skip ediyoruz.

          let ilkZimmetAtlandi = false;

          hareketler.forEach((h) => {
            // İlk "zimmet" hareketini atla
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

        // 2. GEÇMİŞ TABLOSUNU DOLDUR
        

        if (typeof feather !== "undefined") {
          setTimeout(() => feather.replace(), 10);
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

// Zimmet Hareket Sil (İadeyi Geri Al)
$(document).on("click", ".zimmet-hareket-sil", function (e) {
  e.preventDefault();
  let id = $(this).data("id");

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu iade işlemini geri almak istediğinizden emin misiniz? Stok ve zimmet durumu güncellenecektir.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#74788d",
    confirmButtonText: "Evet, Geri Al!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      Pace.start();
      $.ajax({
        url: zimmetUrl,
        type: "POST",
        data: {
          action: "zimmet-hareket-sil",
          id: id,
        },
        dataType: "json",
        success: function (data) {
          if (data.status === "success") {
            Swal.fire("Başarılı!", data.message, "success");
            $("#zimmetDetayModal").modal("hide");
            loadZimmetList();
            if (typeof demirbasTable !== "undefined")
              demirbasTable.ajax.reload(null, false);
            if (typeof sayacTable !== "undefined")
              sayacTable.ajax.reload(null, false);
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        },
        error: function () {
          Swal.fire("Hata!", "Sunucu ile iletişim kurulamadı.", "error");
        },
      });
    }
  });
});

// ============== EXCEL İŞLEMLERİ ==============

// Excel Import Modal Aç
$(document).on("click", "#importExcel", function (e) {
  e.preventDefault();

  // Modal elementini seç
  var modalEl = $("#importExcelModal");

  // Eğer modal bulunamadıysa hata ver
  if (modalEl.length === 0) {
    console.error("Import Excel Modal bulunamadı!");
    return;
  }

  // Modalı body'ye taşı (z-index ve overflow sorunlarını önlemek için)
  if (modalEl.parent().prop("tagName") !== "BODY") {
    modalEl.appendTo("body");
  }

  // Bootstrap modal instance'ını al veya oluştur
  var modal =
    bootstrap.Modal.getInstance(modalEl[0]) || new bootstrap.Modal(modalEl[0]);
  modal.show();
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
          let hasSkipped = res.skipped && res.skipped.length > 0;
          let hasErrors = res.errors && res.errors.length > 0;

          if (hasSkipped || hasErrors) {
            // Detaylı sonuç göster
            let htmlContent = `
              <div class="text-start">
                <div class="d-flex gap-2 mb-3 justify-content-center flex-wrap">
                  <span class="badge bg-success px-3 py-2 fs-6">
                    <i class="bx bx-check-circle me-1"></i> ${res.successCount || 0} Başarılı
                  </span>`;

            if (hasSkipped) {
              htmlContent += `
                  <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                    <i class="bx bx-skip-next-circle me-1"></i> ${res.skippedCount || 0} Atlandı
                  </span>`;
            }

            if (hasErrors) {
              htmlContent += `
                  <span class="badge bg-danger px-3 py-2 fs-6">
                    <i class="bx bx-error-circle me-1"></i> ${res.errors.length} Hata
                  </span>`;
            }

            htmlContent += `</div>`;

            // Atlanan satırlar tablosu
            if (hasSkipped) {
              htmlContent += `
                <div class="alert alert-warning py-2 px-3 mb-2">
                  <i class="bx bx-info-circle me-1"></i>
                  <strong>Atlanan Satırlar</strong> - Kategori eşleşmediği için aşağıdaki satırlar yüklenmedi:
                </div>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                  <table class="table table-sm table-bordered table-striped mb-2">
                    <thead class="table-light" style="position: sticky; top: 0;">
                      <tr>
                        <th class="text-center" style="width:15%">Satır</th>
                        <th style="width:25%">Demirbaş Adı</th>
                        <th style="width:20%">Kategori</th>
                        <th style="width:40%">Neden</th>
                      </tr>
                    </thead>
                    <tbody>`;

              res.skipped.forEach(function (s) {
                htmlContent += `
                      <tr>
                        <td class="text-center fw-bold">${s.satir}</td>
                        <td>${s.demirbas_adi || "-"}</td>
                        <td><span class="badge bg-danger">${s.kategori || "-"}</span></td>
                        <td class="small">${s.neden}</td>
                      </tr>`;
              });

              htmlContent += `
                    </tbody>
                  </table>
                </div>`;

              // Mevcut geçerli kategoriler
              if (res.mevcutKategoriler && res.mevcutKategoriler.length > 0) {
                htmlContent += `
                <div class="alert alert-info py-2 px-3 mb-0 mt-2">
                  <i class="bx bx-list-check me-1"></i>
                  <strong>Geçerli Kategoriler:</strong><br>
                  <div class="mt-1 d-flex flex-wrap gap-1">`;

                res.mevcutKategoriler.forEach(function (k) {
                  htmlContent += `<span class="badge bg-primary bg-opacity-75">${k}</span>`;
                });

                htmlContent += `
                  </div>
                </div>`;
              }
            }

            // Hatalar
            if (hasErrors) {
              htmlContent += `
                <div class="alert alert-danger py-2 px-3 mb-0 mt-2">
                  <i class="bx bx-error me-1"></i>
                  <strong>Hatalar:</strong><br>`;
              res.errors.forEach(function (err) {
                htmlContent += `<div class="small">• ${err}</div>`;
              });
              htmlContent += `</div>`;
            }

            htmlContent += `</div>`;

            Swal.fire({
              title:
                res.successCount > 0 ? "İşlem Tamamlandı" : "Yükleme Başarısız",
              html: htmlContent,
              icon: res.successCount > 0 ? "warning" : "error",
              width: "700px",
              confirmButtonText: "Tamam",
            }).then(() => {
              if (res.successCount > 0) {
                location.reload();
              }
            });
          } else {
            // Tüm satırlar başarılı
            Swal.fire({
              title: "Başarılı",
              text: res.message,
              icon: "success",
            }).then(() => {
              location.reload();
            });
          }
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
  let seriNo = tr.find("td:eq(5)").text().trim();

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
          timer: 1500,
          showConfirmButton: false,
        });

        // Tabloyu yenile
        let activeTab = $("#demirbasTab button.active").attr("id");
        if (
          activeTab === "demirbas-tab" &&
          typeof demirbasTable !== "undefined"
        ) {
          demirbasTable.ajax.reload(null, false);
        } else if (
          activeTab === "depo-tab" &&
          typeof sayacTable !== "undefined"
        ) {
          sayacTable.ajax.reload(null, false);
        }
      } else {
        Swal.fire("Hata!", data.message, "error");
      }
    })
    .catch((err) => {
      console.error("Hata:", err);
      Swal.fire("Hata!", "İşlem sırasında bir hata oluştu.", "error");
    })
    .finally(() => {
      submitBtn.prop("disabled", false).html(originalBtnHtml);
    });
});

// Toplu Kaskiye Teslim Checkbox Logic
$(document).on("click", "#checkAllSayac", function () {
  $("#sayacTable .sayac-select")
    .prop("checked", this.checked)
    .trigger("change");
});

$(document).on("click", ".sayac-select", function () {
  let tableId = $(this).closest("table").attr("id");
  let checkAllId = "";
  if (tableId === "demirbasTable") checkAllId = "#checkAllDemirbas";
  else if (tableId === "sayacTable") checkAllId = "#checkAllSayac";
  else if (tableId === "aparatTable") checkAllId = "#checkAllAparat";

  if (checkAllId) {
    let allChecks = $("#" + tableId + " .sayac-select").length;
    let checkedCount = $("#" + tableId + " .sayac-select:checked").length;
    $(checkAllId).prop("checked", checkedCount === allChecks && allChecks > 0);
  }
});

$(document).on("click", "#btnTopluKaskiyeTeslim", function () {
  let selected = [];
  $(".sayac-select:checked").each(function () {
    selected.push($(this).val());
  });

  if (selected.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Uyarı",
      text: "Lütfen teslim edilecek sayaçları seçin.",
      confirmButtonText: "Tamam",
    });
    return;
  }

  Swal.fire({
    title: "Emin misiniz?",
    html: `Seçili <b>${selected.length}</b> adet sayacı Kaskiye teslim etmek istiyor musunuz?<br><br>
          <div class="row text-start mt-3">
            <div class="col-12 mb-2">
                <label class="form-label">Teslim Tarihi:</label>
                <input type="date" id="swal_kaskiye_tarih" class="form-control" value="${new Date().toISOString().split("T")[0]}">
            </div>
            <div class="col-12">
                <label class="form-label">Açıklama (İsteğe bağlı):</label>
                <textarea id="swal_kaskiye_aciklama" class="form-control" rows="2"></textarea>
            </div>
          </div>`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Evet, Teslim Et",
    cancelButtonText: "İptal",
    preConfirm: () => {
      const tarih = document.getElementById("swal_kaskiye_tarih").value;
      const aciklama = document.getElementById("swal_kaskiye_aciklama").value;
      if (!tarih) {
        Swal.showValidationMessage("Lütfen teslim tarihi seçin");
      }

      // format date to dd.mm.yyyy format for PHP
      let dateParts = tarih.split("-");
      let formattedTarih =
        dateParts[2] + "." + dateParts[1] + "." + dateParts[0];

      return { tarih: formattedTarih, aciklama: aciklama };
    },
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: zimmetUrl,
        type: "POST",
        data: {
          action: "bulk-kasiye-teslim",
          ids: selected,
          tarih: result.value.tarih,
          aciklama: result.value.aciklama,
        },
        dataType: "json",
        success: function (res) {
          if (res.status === "success") {
            Swal.fire("Başarılı!", res.message, "success");
            if (typeof sayacTable !== "undefined") {
              sayacTable.ajax.reload(null, false);
            }
            $("#checkAllSayac").prop("checked", false);
          } else {
            Swal.fire("Hata!", res.message, "error");
          }
        },
        error: function () {
          Swal.fire("Hata!", "Sunucu ile iletişim kurulamadı.", "error");
        },
      });
    }
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

  // Eski tabloyu temizle
  if ($.fn.DataTable.isDataTable("#demirbasGecmisTable")) {
    $("#demirbasGecmisTable").DataTable().destroy();
  }
  $("#demirbasGecmisBody").empty();

  // DataTable Initialize Server-Side
  $("#demirbasGecmisTable").DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    autoWidth: false,
    bDestroy: true, // Allow re-initialization
    language: {
      url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json",
    },
    ajax: {
      url: zimmetUrl,
      type: "POST",
      data: function (d) {
        d.action = "hareket-gecmisi";
        d.demirbas_id = demirbasId;
        // DataTables draw değişkenleri otomatik gidiyor, ekstra parametreye gerek yok
      },
      error: function (xhr, error, thrown) {
        console.error("DataTables Hatası: ", error);
        Swal.fire("Hata", "Veriler yüklenirken bir hata oluştu.", "error");
      },
    },
    order: [[2, "desc"]], // Varsayılan tarih sıralaması
    columns: [
      { orderable: true }, // İşlem Tipi
      { orderable: true }, // Miktar
      { orderable: true }, // Tarih
      { orderable: true }, // İlgili Personel
      { orderable: true }, // Açıklama
      { orderable: true }, // İşlem Yapan
    ],
  });
});

// Zimmet filtresi tıklandığında tabloyu yenile
$(document).on("change", ".zimmet-filter", function () {
  zimmetTable.ajax.reload();
});

// ============== ENVANTER RAPORU FİLTRELEME ==============
$(document).on("click", ".inventory-filter", function () {
  let katAdi = $(this).data("kat-adi");
  let type = $(this).data("filter-type");

  if (!katAdi || !type) return;

  // Tabloyu yeniden yükle (Sunucu tarafında filtrelenecek)
  $("#activeFilterBadges").data("katAdi", katAdi);
  $("#activeFilterBadges").data("filterType", type);
  demirbasTable.ajax.reload();

  // Filtre Badge'lerini oluştur (Yeni İstek)
  const filterNames = {
    bosta: "Boşta",
    zimmetli: "Zimmetli",
    arizali: "Serviste/Arızalı",
    hurda: "Hurda",
  };

  const badgeHtml = `
    <div class="filter-badge">
        <span class="filter-label">Kategori:</span>
        <span class="filter-value">${katAdi}</span>
    </div>
    <div class="filter-badge">
        <span class="filter-label">Durum:</span>
        <span class="filter-value">${filterNames[type]}</span>
        <span class="filter-remove" id="clearInventoryFilterBadge" title="Filtreyi Kaldır">
            <i class="bx bx-x"></i>
        </span>
    </div>
  `;

  $("#activeFilterBadges").html(badgeHtml);

  // Tabloya kaydır
  $("html, body").animate(
    {
      scrollTop: $("#demirbasTable").offset().top - 150,
    },
    500,
  );

  // Eski butonu artık eklememize gerek yok (başlıkta badge var)
  if ($("#clearInventoryFilter").length) {
    $("#clearInventoryFilter").remove();
  }
});

// Filtre Badge'inden Kaldırma (x'e basınca)
$(document).on("click", "#clearInventoryFilterBadge", function (e) {
  e.stopPropagation(); // Accordion'un açılıp kapanmasını engelle
  $("#activeFilterBadges").removeData("katAdi");
  $("#activeFilterBadges").removeData("filterType");
  $("#activeFilterBadges").empty();
  demirbasTable.ajax.reload();
});

// Eski Filtreyi Temizle Butonu (Geriye dönük uyumluluk veya yedek)
$(document).on("click", "#clearInventoryFilter", function () {
  $("#activeFilterBadges").removeData("katAdi");
  $("#activeFilterBadges").removeData("filterType");
  $("#activeFilterBadges").empty();
  $(this).remove();
  demirbasTable.ajax.reload();
});

// ============== SERVİS KAYDI İŞLEMLERİ ==============

function loadServisList() {
  if (servisTable) {
    servisTable.ajax.reload(function (json) {
      if (json.stats) {
        $("#servis_toplam_kayit").text(json.stats.toplam_kayit);
        $("#servis_aktif_sayisi").text(json.stats.aktif_sayisi);
        $("#servis_toplam_maliyet").text(json.stats.toplam_maliyet);
        $("#servisStatsRow").removeClass("d-none");
      }
    }, false);
  }
}

$(document).on("click", "#btnServisListele", function () {
  loadServisList();
});

$(document).on("click", "#btnYeniServis", function () {
  $("#servisForm")[0].reset();
  $("#servis_id").val("");
  $("#servis_demirbas_id").val("").trigger("change");
  $("#teslim_eden_personel_id").val("").trigger("change");

  $("#servis_demirbas_select_area").removeClass("d-none");
  $("#servis_demirbas_info_area").addClass("d-none");

  $("#servisModalLabel").html(
    '<i class="bx bx-wrench me-2"></i>Yeni Servis Kaydı',
  );
  $("#servisModal").modal("show");
});

$(document).on("click", ".servis-ekle", function () {
  const rawId = $(this).data("raw-id");
  const name = $(this).data("name");
  const no = $(this).data("no");

  $("#servisForm")[0].reset();
  $("#servis_id").val("");
  $("#servis_demirbas_id").val(rawId).trigger("change");
  $("#teslim_eden_personel_id").val("").trigger("change");

  $("#servis_demirbas_select_area").addClass("d-none");
  $("#servis_demirbas_info_area").removeClass("d-none");

  $("#servis_demirbas_adi_display").text(name);
  $("#servis_demirbas_no_display").text(no);

  $("#servisModalLabel").html(
    '<i class="bx bx-wrench me-2"></i>Yeni Servis Kaydı',
  );

  $("#servisModal").modal("show");
});

$(document).on("click", ".servis-duzenle", function () {
  const encId = $(this).data("id");

  $.post(
    zimmetUrl,
    { action: "servis-detay", id: encId },
    function (response) {
      if (response.status === "success") {
        const data = response.data;
        $("#servisForm")[0].reset();
        $("#servis_id").val(encId);
        $("#servis_demirbas_id").val(data.demirbas_id).trigger("change");
        $("#teslim_eden_personel_id")
          .val(data.teslim_eden_personel_id)
          .trigger("change");

        $("#servis_demirbas_select_area").addClass("d-none");
        $("#servis_demirbas_info_area").removeClass("d-none");

        $("#servis_demirbas_adi_display").text(data.demirbas_adi);
        $("#servis_demirbas_no_display").text(data.demirbas_no);

        // Form alanlarını doldur
        $("#servis_tarihi").val(data.servis_tarihi_formatted);
        $("#iade_tarihi").val(data.iade_tarihi_formatted);
        $("#servis_adi").val(data.servis_adi);
        $("#servis_nedeni").val(data.servis_nedeni);
        $("#yapilan_islemler").val(data.yapilan_islemler);
        $("#tutar").val(data.tutar);
        $("#fatura_no").val(data.fatura_no);

        $("#servisModalLabel").html(
          '<i class="bx bx-wrench me-2"></i>Servis Kaydı Düzenle',
        );
        $("#servisModal").modal("show");
      } else {
        Swal.fire("Hata", response.message || "Veri alınamadı", "error");
      }
    },
    "json",
  );
});

$(document).on("click", "#btnServisKaydet", function () {
  const $btn = $(this);
  const formData = $("#servisForm").serialize();

  $btn
    .prop("disabled", true)
    .html('<i class="bx bx-loader bx-spin me-1"></i> Kaydediliyor...');

  $.post(
    zimmetUrl,
    {
      action: "servis-kaydet",
      ...$("#servisForm")
        .serializeArray()
        .reduce((obj, item) => ({ ...obj, [item.name]: item.value }), {}),
    },
    function (response) {
      $btn
        .prop("disabled", false)
        .html('<i class="bx bx-save me-1"></i> Kaydet');
      if (response.status === "success") {
        Swal.fire("Başarılı", "Servis kaydı başarıyla kaydedildi.", "success");
        $("#servisModal").modal("hide");
        loadServisList();
      } else {
        Swal.fire("Hata", response.message || "Kaydedilemedi", "error");
      }
    },
    "json",
  );
});

$(document).on("click", ".servis-sil", function () {
  const encId = $(this).data("id");

  Swal.fire({
    title: "Emin misiniz?",
    text: "Bu servis kaydı silinecektir!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, sil!",
    cancelButtonText: "Vazgeç",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        zimmetUrl,
        { action: "servis-sil", id: encId },
        function (response) {
          if (response.status === "success") {
            Swal.fire("Silindi", "Servis kaydı silindi.", "success");
            loadServisList();
          } else {
            Swal.fire("Hata", response.message || "Silinemedi", "error");
          }
        },
        "json",
      );
    }
  });
});

// ============== ZİMMET İSTATİSTİKLERİ VE GRAFİKLER ==============

var katChart, durumChart;

function initZimmetCharts() {
  if (typeof ApexCharts === "undefined") return;

  const katOptions = {
    chart: { height: 260, type: "donut" },
    series: [],
    labels: [],
    colors: ["#556ee6", "#34c38f", "#f1b44c", "#50a5f1", "#f46a6a", "#32394e"],
    legend: {
      position: "bottom",
      formatter: function (seriesName, opts) {
        return (
          seriesName + ":  " + opts.w.globals.seriesTotals[opts.seriesIndex]
        );
      },
    },
    dataLabels: {
      enabled: true,
      formatter: function (val, opts) {
        return opts.w.globals.seriesTotals[opts.seriesIndex];
      },
      style: {
        fontSize: "12px",
        fontWeight: "bold",
      },
    },
    stroke: { width: 0 },
    noData: { text: "Veri Yükleniyor..." },
  };

  const durumOptions = {
    chart: { height: 260, type: "pie" },
    series: [],
    labels: [],
    colors: ["#f1b44c", "#34c38f", "#f46a6a", "#50a5f1"],
    legend: {
      position: "bottom",
      formatter: function (seriesName, opts) {
        return (
          seriesName + ":  " + opts.w.globals.seriesTotals[opts.seriesIndex]
        );
      },
    },
    dataLabels: {
      enabled: true,
      formatter: function (val, opts) {
        return opts.w.globals.seriesTotals[opts.seriesIndex];
      },
      style: {
        fontSize: "12px",
        fontWeight: "bold",
      },
    },
    stroke: { width: 0 },
    noData: { text: "Veri Yükleniyor..." },
  };

  katChart = new ApexCharts(
    document.querySelector("#zimmetKategoriChart"),
    katOptions,
  );
  durumChart = new ApexCharts(
    document.querySelector("#zimmetDurumChart"),
    durumOptions,
  );

  katChart.render();
  durumChart.render();
}

function loadZimmetCharts() {
  const pId = $("#zimmet_personel_filtre").val() || "all";

  $.post(
    zimmetUrl,
    { action: "zimmet-stats-chart", personel_id: pId },
    function (res) {
      if (res.status === "success") {
        const katLabels = res.katData.map((d) => d.label);
        const katValues = res.katData.map((d) => parseInt(d.value));

        const durumLabels = res.durumData.map((d) => d.label);
        const durumValues = res.durumData.map((d) => parseInt(d.value));

        if (!katChart || !durumChart) {
          initZimmetCharts();
        }

        if (katChart && durumChart) {
          katChart.updateOptions({
            labels: katLabels || [],
            series: katValues || [],
          });
          durumChart.updateOptions({
            labels: durumLabels || [],
            series: durumValues || [],
          });
        }
      }
    },
    "json",
  );
}

$(document).on("change", "#zimmet_personel_filtre", function () {
  if (typeof zimmetTable !== "undefined") {
    zimmetTable.ajax.reload();
  }
  if ($("#collapseZimmetStats").hasClass("show")) {
    loadZimmetCharts();
  }
});

$(document).on("change", 'input[name="zimmetFilter"]', function () {
  if (typeof zimmetTable !== "undefined") {
    zimmetTable.ajax.reload();
  }
});

$("#collapseZimmetStats").on("shown.bs.collapse", function () {
  if (!katChart) {
    initZimmetCharts();
  }
  loadZimmetCharts();
});

// Zimmet Tablosu Toplu Seçim ve Silme
$(document).on("change", "#checkAllZimmet", function () {
  const isChecked = $(this).prop("checked");
  $(".zimmet-select:not(:disabled)")
    .prop("checked", isChecked)
    .trigger("change");
});

// Update check-all status and row highlighting when single checkbox changes
$(document).on("change", ".zimmet-select", function () {
  const totalSelectable = $(".zimmet-select:not(:disabled)").length;
  const totalChecked = $(".zimmet-select:checked:not(:disabled)").length;

  if (totalSelectable > 0 && totalChecked === totalSelectable) {
    $("#checkAllZimmet").prop("checked", true);
  } else {
    $("#checkAllZimmet").prop("checked", false);
  }

  if ($(this).prop("checked")) {
    $(this).closest("tr").addClass("selected-row");
  } else {
    $(this).closest("tr").removeClass("selected-row");
  }
});

$(document).on("change", "#checkAllDemirbas", function () {
  $("#demirbasTable .sayac-select")
    .prop("checked", this.checked)
    .trigger("change");
});
$(document).on("change", "#checkAllSayac", function () {
  $("#sayacTable .sayac-select")
    .prop("checked", this.checked)
    .trigger("change");
});
$(document).on("change", "#checkAllAparat", function () {
  $("#aparatTable .sayac-select")
    .prop("checked", this.checked)
    .trigger("change");
});

$(document).on("change", ".sayac-select", function () {
  if ($(this).prop("checked")) {
    $(this).closest("tr").addClass("selected-row");
  } else {
    $(this).closest("tr").removeClass("selected-row");
  }
});

// Row Click selection for Demirbas Tables
$(document).on(
  "click",
  "#demirbasTable tbody tr, #sayacTable tbody tr, #aparatTable tbody tr",
  function (e) {
    if (
      $(e.target).closest(
        ".dropdown, .dropdown-menu, .custom-checkbox-input, .sayac-select, a, button",
      ).length
    ) {
      return;
    }
    const checkbox = $(this).find(".sayac-select");
    if (checkbox.length) {
      const isChecked = !checkbox.prop("checked");
      checkbox.prop("checked", isChecked).trigger("change");
    }
  },
);

$(document).on("click", "#btnTopluDemirbasSil", function (e) {
  e.preventDefault();
  let secilenKayıtlar = [];

  let activeTabBtn = $("#demirbasTab button.active");
  if (activeTabBtn.length === 0) return;

  let activeTab = activeTabBtn.attr("id");
  let tableId = "";
  if (activeTab === "demirbas-tab") tableId = "#demirbasTable";
  else if (activeTab === "depo-tab") tableId = "#sayacTable";
  else if (activeTab === "aparat-tab") tableId = "#aparatTable";

  if (!tableId) return;

  $(tableId + " .sayac-select:checked").each(function () {
    secilenKayıtlar.push($(this).val());
  });

  if (secilenKayıtlar.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Hata",
      text: "Lütfen silmek için en az bir kayıt seçin!",
      confirmButtonText: "Tamam",
    });
    return;
  }

  Swal.fire({
    title: "Emin misiniz?",
    text: "Seçilen kayıtlar silinecektir. Zimmet geçmişi olanlar silinemez.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: zimmetUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "bulk-demirbas-sil",
          ids: secilenKayıtlar,
        },
        success: function (res) {
          if (res.status === "success") {
            Swal.fire("Başarılı", res.message, "success");

            if (
              activeTab === "demirbas-tab" &&
              typeof demirbasTable !== "undefined"
            )
              demirbasTable.ajax.reload(null, false);
            else if (
              activeTab === "depo-tab" &&
              typeof sayacTable !== "undefined"
            )
              sayacTable.ajax.reload(null, false);
            else if (
              activeTab === "aparat-tab" &&
              typeof aparatTable !== "undefined"
            )
              aparatTable.ajax.reload(null, false);

            $("#checkAllDemirbas").prop("checked", false);
            $("#checkAllSayac").prop("checked", false);
            $("#checkAllAparat").prop("checked", false);
          } else {
            Swal.fire("Hata", res.message, "error");
          }
        },
        error: function () {
          Swal.fire("Hata", "Bir ağ hatası oluştu.", "error");
        },
      });
    }
  });
});

// Row Click selection for Zimmet Table
$(document).on("click", "#zimmetTable tbody tr", function (e) {
  // If clicked on an action button, dropdown, or checkbox itself, don't trigger row selection
  if (
    $(e.target).closest(
      ".dropdown, .dropdown-menu, .custom-checkbox-container, .zimmet-select, a, button",
    ).length
  ) {
    return;
  }

  const checkbox = $(this).find(".zimmet-select");
  if (checkbox.is(":disabled")) return;

  const isChecked = !checkbox.prop("checked");
  checkbox.prop("checked", isChecked).trigger("change");
});

$(document).on("click", "#btnTopluIadeAl", function (e) {
  e.preventDefault();
  let secilenZimmetler = [];
  $(".zimmet-select:checked:not(:disabled)").each(function () {
    secilenZimmetler.push($(this).val());
  });

  if (secilenZimmetler.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Hata",
      text: "Lütfen iade almak için en az bir aktif zimmet kaydı seçin!",
      confirmButtonText: "Tamam",
    });
    return;
  }

  $("#toplu_iade_sayisi").text(secilenZimmetler.length);
  $("#topluIadeModal").modal("show");
});

$(document).on("click", "#btnTopluIadeKaydet", function () {
  let secilenZimmetler = [];
  $(".zimmet-select:checked:not(:disabled)").each(function () {
    secilenZimmetler.push($(this).val());
  });

  const iadeTarihi = $("#toplu_iade_tarihi").val();
  const aciklama = $("#toplu_iade_aciklama").val();

  if (!iadeTarihi) {
    Swal.fire("Hata", "Lütfen iade tarihini seçin.", "error");
    return;
  }

  Swal.fire({
    title: "Toplu İade Yapılıyor...",
    didOpen: () => {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: zimmetUrl,
    type: "POST",
    dataType: "json",
    data: {
      action: "bulk-zimmet-iade",
      ids: secilenZimmetler,
      iade_tarihi: iadeTarihi,
      aciklama: aciklama,
    },
    success: function (res) {
      if (res.status === "success") {
        $("#topluIadeModal").modal("hide");
        $("#toplu_iade_aciklama").val("");

        Swal.fire({
          icon: "success",
          title: "Başarılı",
          text: res.message,
          timer: 3000,
          showConfirmButton: false,
        });
        $("#checkAllZimmet").prop("checked", false);
        if (typeof zimmetTable !== "undefined") {
          zimmetTable.ajax.reload(null, false);
        }
      } else {
        Swal.fire({
          icon: "error",
          title: "Hata",
          text: res.message,
          confirmButtonText: "Tamam",
        });
      }
    },
    error: function () {
      Swal.fire({
        icon: "error",
        title: "Hata",
        text: "Bir ağ hatası oluştu.",
      });
    },
  });
});

$(document).on("click", "#btnTopluZimmetSil", function (e) {
  e.preventDefault();
  let secilenZimmetler = [];
  $(".zimmet-select:checked:not(:disabled)").each(function () {
    secilenZimmetler.push($(this).val());
  });

  if (secilenZimmetler.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Hata",
      text: "Lütfen silmek için en az bir aktif zimmet kaydı seçin!",
      confirmButtonText: "Tamam",
    });
    return;
  }

  Swal.fire({
    title: "Emin misiniz?",
    text:
      secilenZimmetler.length +
      " adet aktif zimmet kaydını kalıcı olarak silmek istediğinize emin misiniz? Arşivlenmiş (iade edilmiş) kayıtlar silinmeyecektir. Zimmetli durumdaki kayıtların stoğu otomatik olarak demirbaş listesinde artırılacaktır.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Evet, Sil!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: "Siliniyor...",
        didOpen: () => {
          Swal.showLoading();
        },
      });

      $.ajax({
        url: zimmetUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "bulk-zimmet-sil",
          ids: secilenZimmetler,
        },
        success: function (res) {
          if (res.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Başarılı",
              text: res.message,
              timer: 3000,
              showConfirmButton: false,
            });
            $("#checkAllZimmet").prop("checked", false);
            if (typeof zimmetTable !== "undefined") {
              zimmetTable.ajax.reload(null, false);
            }
          } else {
            Swal.fire({
              icon: "error",
              title: "Hata",
              text: res.message,
              confirmButtonText: "Tamam",
            });
          }
        },
        error: function () {
          Swal.fire({
            icon: "error",
            title: "Bağlantı Hatası",
            text: "Lütfen internet bağlantınızı kontrol edip tekrar deneyin.",
            confirmButtonText: "Tamam",
          });
        },
      });
    }
  });
});

// ============== TOPLU APARAT ZİMMET İŞLEMLERİ ==============

let topluAparatVerisi = [];

function openTopluAparatZimmetModal(aparatlar) {
  topluAparatVerisi = aparatlar;

  // Listeyi render et
  renderTopluAparatListesi();

  // Select2 başlat (personel)
  if ($("#toplu_aparat_personel_id").data("select2")) {
    $("#toplu_aparat_personel_id").select2("destroy");
  }

  $("#toplu_aparat_personel_id").select2({
    dropdownParent: $("#topluAparatZimmetModal"),
    placeholder: "Personel arayın...",
    allowClear: true,
    width: "100%",
    ajax: {
      url: zimmetUrl,
      type: "POST",
      dataType: "json",
      delay: 250,
      data: function (params) {
        return {
          action: "personel-ara",
          q: params.term,
          type: "all",
        };
      },
      processResults: function (data) {
        return { results: data.results };
      },
      cache: true,
    },
    minimumInputLength: 0,
  });

  // Flatpickr başlat
  let fpEl = document.getElementById("toplu_aparat_teslim_tarihi");
  if (fpEl && fpEl._flatpickr) {
    fpEl._flatpickr.setDate(new Date());
  } else if (fpEl) {
    flatpickr(fpEl, {
      dateFormat: "d.m.Y",
      locale: "tr",
      defaultDate: new Date(),
    });
  }

  // Feather icons yenile
  if (typeof feather !== "undefined") feather.replace();

  // Modalı aç
  $("#topluAparatZimmetModal").modal("show");
}

function renderTopluAparatListesi() {
  let $liste = $("#topluAparatListesi");
  $liste.empty();

  if (topluAparatVerisi.length === 0) {
    $liste.html(`
      <div class="toplu-aparat-empty">
        <i class="bx bx-package"></i>
        <span>Aparat seçilmemiş</span>
      </div>
    `);
    $("#topluAparatOzet").hide();
    $("#topluAparatZimmetKaydet").prop("disabled", true);
    return;
  }

  topluAparatVerisi.forEach(function (aparat, index) {
    let isValid = aparat.miktar >= 1 && aparat.miktar <= aparat.kalan;
    let stokClass =
      aparat.kalan > 10 ? "bg-success text-white" : "bg-warning text-dark";

    let html = `
      <div class="aparat-zimmet-item" data-index="${index}">
        <div class="aparat-info">
          <div class="aparat-name" title="${aparat.name}">${aparat.name}</div>
          <div class="aparat-meta">
            ${aparat.marka ? '<i class="bx bx-purchase-tag-alt me-1"></i>' + aparat.marka : ""}
          </div>
        </div>
        <div class="aparat-qty-group">
          <span class="stock-badge ${stokClass}">Stok: ${aparat.kalan}</span>
          <input type="number" 
            class="qty-input toplu-aparat-qty ${isValid ? "" : "is-invalid"}" 
            data-index="${index}" 
            value="${aparat.miktar}" 
            min="1" 
            max="${aparat.kalan}"
            title="Maks: ${aparat.kalan}">
        </div>
        <button type="button" class="remove-btn toplu-aparat-sil" data-index="${index}" title="Listeden çıkar">
          <i class="bx bx-x fs-5"></i>
        </button>
      </div>
    `;
    $liste.append(html);
  });

  // Özet güncelle
  updateTopluAparatOzet();
}

function updateTopluAparatOzet() {
  let toplamCesit = topluAparatVerisi.length;
  let toplamAdet = 0;
  let hepsiGecerli = true;

  topluAparatVerisi.forEach(function (aparat) {
    toplamAdet += aparat.miktar || 0;
    if (aparat.miktar < 1 || aparat.miktar > aparat.kalan) {
      hepsiGecerli = false;
    }
  });

  if (toplamCesit > 0) {
    $("#topluAparatOzet").show();
    $("#topluAparatCesit").text(toplamCesit);
    $("#topluAparatToplam").text(toplamAdet);
  } else {
    $("#topluAparatOzet").hide();
  }

  // Validasyon durumunu güncelle
  if (hepsiGecerli && toplamCesit > 0) {
    $("#topluAparatValidasyonText")
      .text("Tüm miktarlar uygun")
      .closest(".toplu-aparat-summary")
      .css({
        background: "linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)",
        "border-color": "#86efac",
      });
    $("#topluAparatValidasyonText")
      .prev("i")
      .attr("class", "bx bx-check-circle text-success");
  } else {
    $("#topluAparatValidasyonText")
      .text("Hatalı miktar var!")
      .closest(".toplu-aparat-summary")
      .css({
        background: "linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)",
        "border-color": "#fca5a5",
      });
    $("#topluAparatValidasyonText")
      .prev("i")
      .attr("class", "bx bx-error-circle text-danger");
  }

  // Kaydet butonu durumu
  let personelSecili = !!$("#toplu_aparat_personel_id").val();
  $("#topluAparatZimmetKaydet").prop(
    "disabled",
    !hepsiGecerli || toplamCesit === 0 || !personelSecili,
  );
}

// Miktar değişikliği
$(document).on("input change", ".toplu-aparat-qty", function () {
  let index = parseInt($(this).data("index"));
  let val = parseInt($(this).val()) || 0;
  let max = parseInt($(this).attr("max")) || 0;

  // Değeri enforce et
  if (val > max) {
    val = max;
    $(this).val(val);
  }
  if (val < 0) {
    val = 0;
    $(this).val(val);
  }

  topluAparatVerisi[index].miktar = val;

  // Validasyon
  if (val >= 1 && val <= max) {
    $(this).removeClass("is-invalid");
  } else {
    $(this).addClass("is-invalid");
  }

  updateTopluAparatOzet();
});

// Aparatı listeden çıkar
$(document).on("click", ".toplu-aparat-sil", function () {
  let index = parseInt($(this).data("index"));
  topluAparatVerisi.splice(index, 1);
  renderTopluAparatListesi();
});

// Personel seçimi değiştiğinde kaydet butonu güncelle
$(document).on("change", "#toplu_aparat_personel_id", function () {
  updateTopluAparatOzet();
});

// Toplu Aparat Zimmet Kaydet
$(document).on("click", "#topluAparatZimmetKaydet", function () {
  let personelId = $("#toplu_aparat_personel_id").val();
  let teslimTarihi = $("#toplu_aparat_teslim_tarihi").val();
  let aciklama = $("#toplu_aparat_aciklama").val();

  if (!personelId) {
    Swal.fire("Hata", "Lütfen personel seçiniz.", "warning");
    return;
  }

  if (!teslimTarihi) {
    Swal.fire("Hata", "Teslim tarihi zorunludur.", "warning");
    return;
  }

  if (topluAparatVerisi.length === 0) {
    Swal.fire("Hata", "Listede aparat bulunmuyor.", "warning");
    return;
  }

  // Son validasyon
  let hatali = topluAparatVerisi.find(
    (a) => a.miktar < 1 || a.miktar > a.kalan,
  );
  if (hatali) {
    Swal.fire(
      "Hata",
      `"${hatali.name}" için girilen miktar geçersiz. Stok: ${hatali.kalan}`,
      "error",
    );
    return;
  }

  // Onay iste
  let toplamAdet = topluAparatVerisi.reduce((s, a) => s + a.miktar, 0);

  Swal.fire({
    title: "Toplu Zimmet Onayı",
    html: `<strong>${topluAparatVerisi.length}</strong> çeşit aparat, toplam <strong>${toplamAdet}</strong> adet zimmetlenecek.<br><small class="text-muted">İşlem geri alınamaz.</small>`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#f1b44c",
    cancelButtonColor: "#74788d",
    confirmButtonText: "Evet, Zimmet Ver!",
    cancelButtonText: "İptal",
  }).then((result) => {
    if (result.isConfirmed) {
      // Loading göster
      let $btn = $("#topluAparatZimmetKaydet");
      $btn
        .prop("disabled", true)
        .html('<i class="bx bx-loader-alt bx-spin me-1"></i>Kaydediliyor...');

      // Zimmetleri sırayla gönder
      let items = topluAparatVerisi.map((a) => ({
        demirbas_id: a.raw_id,
        miktar: a.miktar,
      }));

      let formData = new FormData();
      formData.append("action", "toplu-aparat-zimmet-kaydet");
      formData.append("items", JSON.stringify(items));
      formData.append("personel_id", personelId);
      formData.append("teslim_tarihi", teslimTarihi);
      formData.append("aciklama", aciklama || "");

      fetch(zimmetUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          $btn
            .prop("disabled", false)
            .html('<i class="bx bx-transfer-alt me-1"></i>Toplu Zimmet Ver');

          if (data.status === "success") {
            $("#topluAparatZimmetModal").modal("hide");
            topluAparatVerisi = [];

            Swal.fire({
              icon: "success",
              title: "Başarılı!",
              text: data.message,
              confirmButtonText: "Tamam",
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Hata!", data.message, "error");
          }
        })
        .catch((err) => {
          console.error(err);
          $btn
            .prop("disabled", false)
            .html('<i class="bx bx-transfer-alt me-1"></i>Toplu Zimmet Ver');
          Swal.fire("Hata!", "Bir bağlantı hatası oluştu.", "error");
        });
    }
  });
});

// Modal kapatıldığında sıfırla
$("#topluAparatZimmetModal").on("hidden.bs.modal", function () {
  topluAparatVerisi = [];
  $("#topluAparatListesi").empty();
  $("#toplu_aparat_personel_id").val(null).trigger("change");
  $("#toplu_aparat_aciklama").val("");
  $("#topluAparatOzet").hide();
  $("#topluAparatZimmetKaydet").prop("disabled", true);
});

// Modal açıldığında feather ikonlarını yenile
$("#topluAparatZimmetModal").on("shown.bs.modal", function () {
  if (typeof feather !== "undefined") feather.replace();
});

// ============== HURDA SAYAÇ İADE İŞLEMLERİ ==============

// Hurda Sayaç İade Modal Aç
$(document).on("click", "#btnHurdaSayacIade", function (e) {
  e.preventDefault();

  // Formu sıfırla
  $("#hurdaIadeForm")[0].reset();
  $("#hurda_iade_tarihi").val(
    new Date().toLocaleDateString("tr-TR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }),
  );
  $("#hurdaZimmetListesi").addClass("d-none");
  $("#hurdaZimmetBody").html(
    '<tr><td colspan="4" class="text-center text-muted py-3">Personel seçildiğinde listelenecektir.</td></tr>',
  );

  // Filtreyi Aktif'e çek
  $("#hurdaPersonelAktif").prop("checked", true);

  // Select2 sıfırla ve doldur
  updateHurdaPersonelList("aktif");

  // Modal'ı aç
  let modalEl = $("#hurdaIadeModal");
  if (modalEl.parent().prop("tagName") !== "BODY") {
    modalEl.appendTo("body");
  }
  var modal =
    bootstrap.Modal.getInstance(modalEl[0]) || new bootstrap.Modal(modalEl[0]);
  modal.show();
});

// Personel listesini filtreye göre güncelle fonksiyonu
function updateHurdaPersonelList(filterType) {
  let list =
    filterType === "aktif" ? hurdaAktifPersoneller : hurdaTumPersoneller;
  let $el = $("#hurda_personel_id");

  // Eğer select2 ise temizle
  if ($el.hasClass("select2-hidden-accessible")) {
    $el.empty().append('<option value="">Personel Seçin</option>');
  } else {
    // Select2 değilse init et
    $el.select2({
      dropdownParent: $("#hurdaIadeModal"),
      placeholder: "Personel Seçin",
      allowClear: true,
      width: "100%",
    });
    $el.empty().append('<option value="">Personel Seçin</option>');
  }

  // Yeni datayı yükle
  list.forEach(function (p) {
    $el.append(new Option(p.text, p.id, false, false));
  });

  $el.val(null).trigger("change");
}

// Filtre değiştiğinde
$(document).on("change", 'input[name="hurdaPersonelFilter"]', function () {
  updateHurdaPersonelList($(this).val());
});

// Modal açıldığında Select2 init ve Feather icons
$("#hurdaIadeModal").on("shown.bs.modal", function () {
  if (typeof feather !== "undefined") feather.replace();

  // Flatpickr init
  if (!$("#hurda_iade_tarihi").hasClass("flatpickr-input")) {
    flatpickr("#hurda_iade_tarihi", {
      dateFormat: "d.m.Y",
      locale: "tr",
      defaultDate: "today",
    });
  }
});

// Personel seçildiğinde hurda sayaç zimmetlerini getir
$(document).on("change", "#hurda_personel_id", function () {
  let personelId = $(this).val();

  if (!personelId || personelId <= 0) {
    $("#hurdaZimmetListesi").addClass("d-none");
    $("#hurdaZimmetBody").html(
      '<tr><td colspan="4" class="text-center text-muted py-3">Personel seçildiğinde listelenecektir.</td></tr>',
    );
    return;
  }

  // Yükleniyor göster
  $("#hurdaZimmetListesi").removeClass("d-none");
  $("#hurdaZimmetBody").html(
    '<tr><td colspan="4" class="text-center py-3"><i class="bx bx-loader-alt bx-spin fs-4"></i> Yükleniyor...</td></tr>',
  );

  $.ajax({
    url: zimmetUrl,
    type: "POST",
    data: {
      action: "hurda-zimmet-listesi",
      personel_id: personelId,
    },
    dataType: "json",
    success: function (res) {
      if (res.status === "success") {
        let data = res.data || [];
        let tbody = $("#hurdaZimmetBody");
        tbody.empty();

        if (data.length === 0) {
          tbody.html(
            '<tr><td colspan="4" class="text-center text-muted py-3"><i class="bx bx-info-circle me-1"></i> Bu personelin zimmetinde hurda sayaç bulunmuyor.</td></tr>',
          );
          return;
        }

        data.forEach(function (item) {
          let row = `
            <tr>
              <td class="text-center">
                <input type="checkbox" class="form-check-input hurda-zimmet-check" value="${item.id}">
              </td>
              <td>
                <div class="fw-medium small">${item.demirbas_adi}</div>
                ${item.seri_no !== "-" ? '<small class="text-muted">SN: ' + item.seri_no + "</small>" : ""}
              </td>
              <td class="text-center">
                <span class="badge bg-danger">${item.kalan_miktar}</span>
              </td>
              <td class="small">${item.teslim_tarihi}</td>
            </tr>
          `;
          tbody.append(row);
        });
      } else {
        $("#hurdaZimmetBody").html(
          '<tr><td colspan="4" class="text-center text-danger py-3">' +
            res.message +
            "</td></tr>",
        );
      }
    },
    error: function () {
      $("#hurdaZimmetBody").html(
        '<tr><td colspan="4" class="text-center text-danger py-3">Sunucu ile iletişim kurulamadı.</td></tr>',
      );
    },
  });
});

// Hurda zimmet hepsini seç/kaldır
$(document).on("change", "#hurdaCheckAll", function () {
  $(".hurda-zimmet-check").prop("checked", this.checked);
});

// Hurda Sayaç İade Kaydet
$(document).on("click", "#btnHurdaIadeKaydet", function () {
  let selectedZimmetler = [];
  $(".hurda-zimmet-check:checked").each(function () {
    selectedZimmetler.push($(this).val());
  });

  let personelId = $("#hurda_personel_id").val();
  let iadeTarihi = $("#hurda_iade_tarihi").val();
  let adet = $("#hurda_iade_adet").val();
  let sayacAdi = $("#hurda_sayac_adi").val();
  let aciklama = $("#hurda_aciklama").val();

  // Eğer zimmet listesinden seçim yapıldıysa "select" mode
  if (selectedZimmetler.length > 0) {
    Swal.fire({
      title: "Emin misiniz?",
      html: `<b>${selectedZimmetler.length}</b> adet hurda sayacı personelden alıp depoya iade etmek istediğinize emin misiniz?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#ef4444",
      cancelButtonColor: "#74788d",
      confirmButtonText: "<i class='bx bx-check me-1'></i> Evet, İade Al",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        doHurdaIade("select", {
          selected_ids: JSON.stringify(selectedZimmetler),
          hurda_iade_tarihi: iadeTarihi,
          hurda_aciklama: aciklama,
        });
      }
    });
  } else {
    // Manuel giriş mode
    if (!personelId || personelId <= 0) {
      Swal.fire("Uyarı", "Lütfen bir personel seçin.", "warning");
      return;
    }
    if (!iadeTarihi) {
      Swal.fire("Uyarı", "Lütfen iade tarihini girin.", "warning");
      return;
    }
    if (!adet || adet <= 0) {
      Swal.fire("Uyarı", "Adet en az 1 olmalıdır.", "warning");
      return;
    }

    doHurdaIade("manual", {
      hurda_personel_id: personelId,
      hurda_iade_tarihi: iadeTarihi,
      hurda_iade_adet: adet,
      hurda_sayac_adi: sayacAdi,
      hurda_aciklama: aciklama,
    });
  }
});

function doHurdaIade(mode, extraData) {
  let submitBtn = $("#btnHurdaIadeKaydet");
  let originalHtml = submitBtn.html();
  submitBtn
    .prop("disabled", true)
    .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

  let formData = new FormData();
  formData.append("action", "hurda-sayac-iade");
  formData.append("mode", mode);

  for (let key in extraData) {
    formData.append(key, extraData[key]);
  }

  fetch(zimmetUrl, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        $("#hurdaIadeModal").modal("hide");
        Swal.fire({
          icon: "success",
          title: "Başarılı!",
          text: data.message,
          timer: 2000,
          showConfirmButton: false,
        });
        // Tabloları yenile
        if (typeof sayacTable !== "undefined") {
          sayacTable.ajax.reload(null, false);
        }
        if (typeof demirbasTable !== "undefined") {
          demirbasTable.ajax.reload(null, false);
        }
      } else {
        Swal.fire("Hata!", data.message, "error");
      }
    })
    .catch((err) => {
      console.error("Hurda iade hatası:", err);
      Swal.fire("Hata!", "İşlem sırasında bir hata oluştu.", "error");
    })
    .finally(() => {
      submitBtn.prop("disabled", false).html(originalHtml);
    });
}

// Hurda İade Modal kapandığında sıfırla
$("#hurdaIadeModal").on("hidden.bs.modal", function () {
  $("#hurdaIadeForm")[0].reset();
  $("#hurdaZimmetListesi").addClass("d-none");
  $("#hurdaZimmetBody").empty();
  if ($("#hurda_personel_id").hasClass("select2-hidden-accessible")) {
    $("#hurda_personel_id").val(null).trigger("change");
  }
});
