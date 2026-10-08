let table;
let datatables = {}; // Çoklu tablo desteği için

/**
 * assets/js/tr.json içeriğinin gömülü kopyası.
 * language.url kullanıldığında DataTables tabloyu çizmeden önce ayrı bir HTTP
 * isteği tamamlanmasını bekler; bu da her tablo başlatmasına bir tur ekler.
 */
const DT_LANG_TR = {
  info: "_START_ - _END_ / _TOTAL_ Toplam Kayıt ",
  infoEmpty: "Kayıt yok",
  infoFiltered: "(_MAX_ kayıt içerisinden bulunan)",
  infoThousands: ".",
  infoPostFix: " ",
  lengthMenu: "Sayfada _MENU_ kayıt göster",
  loadingRecords: "Yükleniyor...",
  processing: "İşleniyor...",
  search: "Ara:",
  searchPlaceholder: "Arayın...",
  zeroRecords: "Eşleşen kayıt bulunamadı",
  emptyTable: "Tabloda veri bulunmuyor",
  thousands: ".",
  decimal: ",",
  paginate: {
    first: "İlk",
    last: "Son",
    next: "Sonraki",
    previous: "Önceki",
  },
  aria: {
    sortAscending: ": artan sütun sıralamasını aktifleştir",
    sortDescending: ": azalan sütun sıralamasını aktifleştir",
  },
  select: {
    rows: { _: "%d kayıt seçildi", 1: "1 kayıt seçildi" },
    cells: { _: "%d hücre seçildi", 1: "1 hücre seçildi" },
    columns: { _: "%d sütun seçildi", 1: "1 sütun seçildi" },
  },
  buttons: {
    collection:
      'Koleksiyon <span class="ui-button-icon-primary ui-icon ui-icon-triangle-1-s"></span>',
    colvis: "Sütun görünürlüğü",
    colvisRestore: "Görünürlüğü eski haline getir",
    copy: "Kopyala",
    copySuccess: {
      1: "1 satır panoya kopyalandı",
      _: "%ds satır panoya kopyalandı",
    },
    copyTitle: "Panoya kopyala",
    copyKeys:
      "Tablodaki veriyi kopyalamak için CTRL veya u2318 + C tuşlarına basınız. İptal etmek için bu mesaja tıklayın veya escape tuşuna basın.",
    csv: "CSV",
    excel: "Excel",
    pdf: "PDF",
    print: "Yazdır",
    pageLength: {
      "-1": "Bütün Kayıtları göster",
      _: "%d kayıt göster",
      1: "1 Kayıt Göster",
    },
  },
  datetime: {
    amPm: ["öö", "ös"],
    hours: "Saat",
    minutes: "Dakika",
    seconds: "Saniye",
    next: "Sonraki",
    previous: "Önceki",
    unknown: "Bilinmeyen",
    weekdays: {
      0: "Pzt",
      1: "Sal",
      2: "Çar",
      3: "Per",
      4: "Cum",
      5: "Cmt",
      6: "Paz",
    },
    months: {
      0: "Ocak",
      1: "Şubat",
      2: "Mart",
      3: "Nisan",
      4: "Mayıs",
      5: "Haziran",
      6: "Temmuz",
      7: "Ağustos",
      8: "Eylül",
      9: "Ekim",
      10: "Kasım",
      11: "Aralık",
    },
  },
};

$(document).ready(function () {
  // Manuel başlatılacakları (.datatable-deferred) hariç tut
  $(".datatable").not(".datatable-deferred").each(function() {
      if (!$.fn.DataTable.isDataTable(this)) {
          $(this).DataTable(getDatatableOptions());
      }
  });

  // URL tab parametresini kontrol et ve ilgili sekmeyi aç
  var urlParams = new URLSearchParams(window.location.search);
  var urlTab = urlParams.get("tab");
  if (urlTab) {
    var tabSelectors = [
      'a[data-bs-toggle="tab"][href="#' + urlTab + '"]',
      'a[data-bs-toggle="tab"][href="#pane-' + urlTab + '"]',
      'a[data-bs-toggle="tab"][href="#' + urlTab + 'Content"]',
      'button[data-bs-toggle="tab"][data-bs-target="#' + urlTab + '"]',
      'button[data-bs-toggle="tab"][data-bs-target="#pane-' + urlTab + '"]'
    ];
    for (var i = 0; i < tabSelectors.length; i++) {
      var $tabEl = $(tabSelectors[i]);
      if ($tabEl.length) {
        $tabEl.tab("show");
        break;
      }
    }
  }

  // ===================================================
  // Global DataTables Sağ Tık (Context Menu) Mekanizması
  // ===================================================
  function dtEscapeHtml(text) {
    if (text == null) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  $(document).on('contextmenu', 'table.dataTable tbody tr, .table-hover tbody tr', function(e) {
    const $tr = $(this);
    
    // Boş satır, yükleniyor uyarısı veya çocuk satır ise engelleme
    if ($tr.hasClass('dataTables_empty') || $tr.find('td').length <= 1) return;
    if ($tr.closest('table').hasClass('no-context-menu')) return;
    if (e.isDefaultPrevented() || e.isPropagationStopped()) return;

    e.preventDefault();

    $('table.dataTable tbody tr, .table-hover tbody tr').removeClass('context-menu-active');
    $tr.addClass('context-menu-active');

    // 1. Satır Başlığı Belirleme (Kayıt No, Ad vb.)
    let rowTitle = '';
    const $firstBadge = $tr.find('.badge, strong, b, h6, .fw-bold').first();
    if ($firstBadge.length && $firstBadge.text().trim()) {
      rowTitle = $firstBadge.text().trim();
    } else {
      const $secondCol = $tr.find('td:nth-child(2)');
      if ($secondCol.length && $secondCol.text().trim()) {
        rowTitle = $secondCol.text().trim();
      } else {
        rowTitle = $tr.find('td:first-child').text().trim() || 'İşlemler';
      }
    }
    if (rowTitle.length > 35) {
      rowTitle = rowTitle.substring(0, 35) + '...';
    }

    // 2. Aksiyon Butonlarını ve Linkleri Tara (Son sütun veya .action-btn-group)
    const $actionTd = $tr.find('td:last-child');
    const $actionButtons = $actionTd.find('a, button, .dropdown-item');

    let menuItemsHtml = '';
    let hasDangerAction = false;
    let dangerItemHtml = '';

    // Varsa Özel PDF/Önizleme Butonları (Satır içi data butonları)
    const $pdfBtn = $tr.find('.btn-offer-pdf, .btn-pdf-preview, [data-pdf-url]');
    if ($pdfBtn.length) {
      menuItemsHtml += `<button type="button" class="cm-action-item" data-target-ref="pdf-preview"><i class="bx bxs-file-pdf text-danger"></i> <span>PDF Önizle</span></button>`;
    }

    $actionButtons.each(function(index) {
      const $btn = $(this);
      
      // Dropdown toggle ana butonunu atla
      if ($btn.hasClass('dropdown-toggle') && $btn.siblings('.dropdown-menu').length) {
        return;
      }

      // Buton başlığı/açıklaması
      let text = $btn.attr('title') || $btn.attr('data-bs-original-title') || $btn.attr('data-original-title') || $btn.attr('aria-label') || $btn.text().trim();
      
      // İkon bulma
      let iconHtml = '';
      const $icon = $btn.find('i, svg').first();
      if ($icon.length) {
        iconHtml = $icon[0].outerHTML;
      } else {
        iconHtml = '<i class="bx bx-chevron-right"></i>';
      }

      // Varsayılan metin yoksa buton sınıfına göre anlamlı başlık ver
      if (!text) {
        if ($btn.hasClass('btn-subtle-primary') || $btn.hasClass('btn-info') || $btn.hasClass('hesap-hareketleri') || $btn.hasClass('sozlesme-detay') || $btn.find('.bx-file-find, .bx-history, .bx-show').length) {
          text = 'Detay / Görüntüle';
        } else if ($btn.hasClass('btn-subtle-warning') || $btn.hasClass('btn-warning') || $btn.hasClass('duzenle') || $btn.hasClass('sozlesme-duzenle') || $btn.find('.bx-edit, .bx-edit-alt').length) {
          text = 'Düzenle';
        } else if ($btn.hasClass('btn-subtle-danger') || $btn.hasClass('btn-danger') || $btn.hasClass('cari-sil') || $btn.hasClass('sozlesme-sil') || $btn.find('.bx-trash').length) {
          text = 'Sil';
        } else if ($btn.hasClass('hareket-ekle') || $btn.find('.bx-plus-circle').length) {
          text = 'Hareket Ekle';
        } else {
          text = 'İşlem ' + (index + 1);
        }
      }

      const isDanger = $btn.hasClass('btn-danger') || $btn.hasClass('btn-subtle-danger') || $btn.hasClass('cari-sil') || $btn.hasClass('sozlesme-sil') || $btn.hasClass('cm-danger') || text.toLowerCase().includes('sil') || text.toLowerCase().includes('delete');

      // Butona benzersiz bir referans ata
      const btnRefId = 'cm-btn-ref-' + Math.random().toString(36).substr(2, 9);
      $btn.attr('data-cm-ref', btnRefId);

      const itemHtml = `<button type="button" class="cm-action-item ${isDanger ? 'cm-danger' : ''}" data-target-ref="${btnRefId}">${iconHtml} <span>${dtEscapeHtml(text)}</span></button>`;

      if (isDanger) {
        hasDangerAction = true;
        dangerItemHtml += itemHtml;
      } else {
        menuItemsHtml += itemHtml;
      }
    });

    if (hasDangerAction) {
      if (menuItemsHtml !== '') {
        menuItemsHtml += '<div class="cm-divider"></div>';
      }
      menuItemsHtml += dangerItemHtml;
    }

    // Eğer hiç buton bulunamadıysa ama satır tıklanabilirse
    if (menuItemsHtml === '') {
      menuItemsHtml = `<button type="button" class="cm-row-click"><i class="bx bx-right-arrow-alt text-primary"></i> <span>Detaya Git</span></button>`;
    }

    const menuHeader = `<div class="cm-header"><i class="bx bx-layer text-primary me-2 font-size-15"></i> <span class="text-truncate">${dtEscapeHtml(rowTitle)}</span></div>`;
    const fullMenuHtml = menuHeader + menuItemsHtml;

    let $contextMenu = $('#customContextMenu');
    if (!$contextMenu.length) {
      $contextMenu = $('<div id="customContextMenu" class="custom-context-menu"></div>').appendTo('body');
    }

    $contextMenu.html(fullMenuHtml);

    let mouseX = e.clientX;
    let mouseY = e.clientY;

    $contextMenu.css({ display: 'block', visibility: 'hidden', opacity: '0' });
    const menuWidth = $contextMenu.outerWidth();
    const menuHeight = $contextMenu.outerHeight();
    const windowWidth = $(window).width();
    const windowHeight = $(window).height();

    if (mouseX + menuWidth > windowWidth) {
      mouseX = windowWidth - menuWidth - 10;
    }
    if (mouseY + menuHeight > windowHeight) {
      mouseY = windowHeight - menuHeight - 10;
    }

    $contextMenu.css({
      top: mouseY + 'px',
      left: mouseX + 'px',
      visibility: 'visible',
      opacity: '1'
    });
  });

  // Context Menu Elemanına Tıklama
  $(document).on('click', '#customContextMenu .cm-action-item', function(e) {
    e.stopPropagation();
    const targetRef = $(this).data('target-ref');
    $('#customContextMenu').hide();
    $('table.dataTable tbody tr, .table-hover tbody tr').removeClass('context-menu-active');

    if (targetRef === 'pdf-preview') {
      const $activeTr = $('tr.context-menu-active');
      $activeTr.find('.btn-offer-pdf, .btn-pdf-preview, [data-pdf-url]').first().trigger('click');
      return;
    }

    if (targetRef) {
      const $targetBtn = $('[data-cm-ref="' + targetRef + '"]');
      if ($targetBtn.length) {
        if ($targetBtn.is('a') && $targetBtn.attr('href') && $targetBtn.attr('href') !== '#') {
          const href = $targetBtn.attr('href');
          const target = $targetBtn.attr('target');
          if (target === '_blank') {
            window.open(href, '_blank');
          } else {
            window.location.href = href;
          }
        } else {
          $targetBtn.trigger('click');
        }
      }
    }
  });

  // Satır Tıklama Menü Elemanı
  $(document).on('click', '#customContextMenu .cm-row-click', function(e) {
    e.stopPropagation();
    $('#customContextMenu').hide();
    const $activeTr = $('tr.context-menu-active');
    $activeTr.removeClass('context-menu-active');
    if ($activeTr.length) {
      $activeTr.find('td:not(:last-child)').first().trigger('click');
    }
  });

  // Menü Kapatma (Dışarı tıklama, scroll veya Escape)
  $(document).on('click scroll', function(e) {
    if (!$(e.target).closest('#customContextMenu').length) {
      $('#customContextMenu').hide();
      $('table.dataTable tbody tr, .table-hover tbody tr').removeClass('context-menu-active');
    }
  });

  $(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
      $('#customContextMenu').hide();
      $('table.dataTable tbody tr, .table-hover tbody tr').removeClass('context-menu-active');
    }
  });
});

/**
 * Mevcut bir tabloyu yok edip yeni ayarlarla başlatır.
 * Merkezi dosyadan başlatma kuralına uymak içindir.
 */
function destroyAndInitDataTable(selector, options = {}) {
  if ($.fn.DataTable.isDataTable(selector)) {
    $(selector).DataTable().destroy();
    // Sadece tbody içeriğini temizle, thead kalmalı
    $(selector).find("tbody").empty();
  }

  let defaultOptions = getDatatableOptions();

  // initComplete fonksiyonlarını birleştir (overwrite etme)
  let defaultInit = defaultOptions.initComplete;
  let customInit = options.initComplete;

  options.initComplete = function (settings, json) {
    if (typeof defaultInit === "function") defaultInit.call(this, settings, json);
    if (typeof customInit === "function") customInit.call(this, settings, json);
  };

  let mergedOptions = $.extend(true, {}, defaultOptions, options);

  return $(selector).DataTable(mergedOptions);
}

function getDatatableOptions() {
  let focusedColIdx = null;
  let focusedCursorPos = null;

  return {
    stateSave: false,
    responsive: true,
    // scrollX: false,
    // fixedHeader: {
    //   header: true,
    //   headerOffset: $("#page-topbar").length
    //     ? $("#page-topbar").outerHeight()
    //     : 70,
    // },
    pageLength: 25,
    orderCellsTop: true,
    dom: 't<"row"<"col-sm-12 col-md-6 d-flex align-items-center justify-content-start"i<"ms-3 text-nowrap"l>><"col-sm-12 col-md-6 d-flex justify-content-end"p>>',
    language: $.extend(true, {}, DT_LANG_TR, {
      emptyTable:
        '<div class="text-center py-5"><div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(85,110,230,0.15) 0%, rgba(85,110,230,0.05) 100%); border: 2px dashed rgba(85,110,230,0.3);"><i class="bx bx-folder-open text-primary" style="font-size: 48px;"></i></div><h5 class="text-dark fw-semibold mb-2">Veri Bulunamadı</h5><p class="text-muted mb-0" style="max-width: 280px; margin: 0 auto;">Bu tabloda henüz gösterilecek kayıt bulunmuyor.</p></div>',
      zeroRecords:
        '<div class="text-center py-5"><div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(241,180,76,0.15) 0%, rgba(241,180,76,0.05) 100%); border: 2px dashed rgba(241,180,76,0.3);"><i class="bx bx-search-alt text-warning" style="font-size: 48px;"></i></div><h5 class="text-dark fw-semibold mb-2">Sonuç Bulunamadı</h5><p class="text-muted mb-0" style="max-width: 280px; margin: 0 auto;">Arama kriterlerinize uygun kayıt bulunamadı.</p></div>',
    }),
    buttons: ["excel"],

    // preDrawCallback: function (settings) {
    //   let activeEl = document.activeElement;
    //   if (
    //     activeEl &&
    //     activeEl.tagName === "INPUT" &&
    //     $(activeEl).closest("thead, .dtfh-floatingparent").length
    //   ) {
    //     focusedColIdx = $(activeEl).closest("th").index();
    //     try {
    //       focusedCursorPos = activeEl.selectionStart;
    //     } catch (e) {
    //       focusedCursorPos = null;
    //     }
    //   } else {
    //     focusedColIdx = null;
    //   }
    // },

    // drawCallback: function (settings) {
    //   if (focusedColIdx !== null) {
    //     let api = this.api();
    //     setTimeout(() => {
    //       let $wrapper = $(settings.nTableWrapper);
    //       // 1. Dtfh plugins (FixedHeader) yüzen başlığını kontrol et
    //       let $th = $(".dtfh-floatingparent th").eq(focusedColIdx);

    //       if (!$th.length) {
    //         // 2. Normal tablonun arama satırlarını kontrol et
    //         let filterRows = $(api.table().header()).find(
    //           "tr.search-input-row, tr.dt-filter-row",
    //         );
    //         if (filterRows.length) {
    //           $th = filterRows.last().find("th").eq(focusedColIdx);
    //         } else {
    //           $th = $(api.table().header())
    //             .find("tr")
    //             .last()
    //             .find("th")
    //             .eq(focusedColIdx);
    //         }
    //       }

    //       let $input = $th.find('input[type="text"]');
    //       if ($input.length) {
    //         $input[0].focus();
    //         if (
    //           focusedCursorPos !== null &&
    //           typeof $input[0].setSelectionRange === "function"
    //         ) {
    //           try {
    //             $input[0].setSelectionRange(focusedCursorPos, focusedCursorPos);
    //           } catch (e) {}
    //         }
    //       }
    //     }, 10);
    //   }
    // },

    ...getTableSpecificOptions(),

    initComplete: function (settings, json) {
      var api = this.api();
      var tableId = settings.sTableId;
      var $nTable = $(settings.nTable);
      var $thead = $nTable.find("thead");

      // Merkezi premium görünüm kabuğu sınıfını uygula
      var $premiumShell = $nTable.closest(".table-responsive, .responsive").first();
      if (!$premiumShell.length) {
        $premiumShell = $nTable.closest(".dataTables_wrapper");
      }
      $premiumShell.addClass("datatable-premium-shell");

      // Gelişmiş filtre var mı kontrol et (Daha sağlam kontrol)
      var hasAnyAdvancedFilter = $thead.find("th[data-filter]").length > 0;

      // PageLength select kutusunun düzgün görünmesi için
      $(settings.nTableWrapper)
        .find(".dataTables_length label")
        .addClass("d-flex align-items-center");
      $(settings.nTableWrapper)
        .find(".dataTables_length select")
        .addClass("mx-2");

      if (hasAnyAdvancedFilter) {
        // Gelişmiş filtre varsa, eski basit filtre satırını HİÇ oluşturma.
        // initAdvancedFilters fonksiyonu aşağıda (satır 145) toplu olarak çağrılıyor.
      } else {
        // Sadece eski tip filtreler varsa eski mantığı çalıştır
        if ($thead.find(".search-input-row").length > 0) return;
        var $searchRow = $('<tr class="search-input-row"></tr>');
        $thead.append($searchRow);

        api.columns().every(function () {
          let column = this;
          let title = column.header().textContent;

          if (
            title != "İşlem" &&
            title != "Seç" &&
            title != "#" &&
            $(column.header()).find('input[type="checkbox"]').length === 0
          ) {
            let input = document.createElement("input");
            input.placeholder = title + "...";
            input.classList.add("form-control", "form-control-sm", "border-light", "bg-light");
            input.setAttribute("autocomplete", "off");
            $(input).css({
                "font-size": "0.75rem",
                "padding": "0.25rem 0.5rem",
                "border-radius": "4px"
            });
            $(input).attr("data-col-idx", column.index());

            const th = $('<th class="search">').append(input);
            $searchRow.append(th);

            // FIX: Stop propagation to prevent sorting when clicking the search box
            th.on("click mousedown", function (e) {
              e.stopPropagation();
            });

            // Eski tip Tarih sütunu desteği
            if (title === "Tarih") {
              $(input).addClass("flatpickr-datatable");
              $(input).flatpickr({
                locale: "tr",
                dateFormat: "d.m.Y",
                allowInput: true,
                onChange: function (selectedDates, dateStr) {
                  let colIdx = $(input).attr("data-col-idx");
                  let table = $(input).closest("table").DataTable();
                  if (table.settings()[0].oFeatures.bServerSide) {
                    table.column(colIdx).search(dateStr).draw();
                  } else {
                    table.draw();
                  }
                },
              });
            }

            let searchTimeout;
            $(input).on("input change", function (event) {
              let val = $(this).val();
              let colIdx = $(this).attr("data-col-idx");
              let table = $(this).closest("table").DataTable();
              if (
                $(this).hasClass("flatpickr-datatable") &&
                event.type === "input"
              )
                return;

              clearTimeout(searchTimeout);
              searchTimeout = setTimeout(function () {
                if (table.settings()[0].oFeatures.bServerSide) {
                  table.column(colIdx).search(val).draw();
                } else {
                  table.draw();
                }
              }, 300);
            });

            if (!column.visible()) th.hide();
          } else {
            $searchRow.append("<th></th>");
          }
        });

        // Responsive olayını dinle
        api.on("responsive-resize", function (e, datatable, columns) {
          $searchRow.find("th").each(function (i) {
            columns[i] ? $(this).show() : $(this).hide();
          });
        });

        // State'den değerleri geri yükle
        var state = api.state.loaded();
        if (state) {
          $searchRow.find("input").each(function () {
            var colIdx = $(this).attr("data-col-idx");
            if (colIdx && state.columns[colIdx]) {
              var val = state.columns[colIdx].search.search;
              if (val) $(this).val(val);
            }
          });
        }
      }

      if (typeof feather !== "undefined") {
        try { feather.replace(); } catch (e) { console.warn("feather.replace error:", e); }
      }

      // Basit filtreli tablolarda URL arama parametresini uygula
      if (!hasAnyAdvancedFilter) {
        var urlParams = new URLSearchParams(window.location.search);
        var urlSearch = (urlParams.get("search") || urlParams.get("q") || urlParams.get("arama") || "").trim();
        if (urlSearch) {
          var $firstSearchInput = $thead.find(".search-input-row input[type='text']:first");
          if ($firstSearchInput.length) {
            $firstSearchInput.val(urlSearch).trigger("input");
          } else {
            api.search(urlSearch).draw();
          }
        }
      }

      // Gelişmiş kolon filtreleri başlat (Sadece bir kez, initComplete sonunda)
      // Büyük, DOM kaynaklı tablolarda filtre arayüzünün hazırlanması ilk görünür
      // çizimi geciktirebilir. İsteyen sayfa filtreleri ilk çizimden sonra başlatır.
      if (
        typeof initAdvancedFilters === "function" &&
        !settings.oInit.deferAdvancedFilters
      ) {
        initAdvancedFilters(api, settings);
      }
    },
  };
}

/**
 * DataTable seçeneklerine sadece sayfa uzunluğunu kaydedecek stateSave ayarlarını uygular.
 * @param {Object} options DataTable seçenekleri
 * @returns {Object} Güncellenmiş seçenekler
 */
function applyLengthStateSave(options) {
  options.stateSave = true;
  options.stateSaveParams = function (settings, data) {
    // Sadece sayfa uzunluğunu (length) sakla, diğer her şeyi sıfırla
    data.start = 0;
    data.search.search = "";
    data.order = [];
    if (data.columns) {
      data.columns.forEach((col) => {
        col.search.search = "";
      });
    }
  };
  return options;
}

$("#exportExcel").on("click", function () {
  if (typeof table !== "undefined" && table) {
    table.button(".buttons-excel").trigger();
  }
});

function getTableSpecificOptions() {
  return {};
}

// DataTables Türkçe karakter arama desteği
(function () {
  // Türkçe karakterleri normalize eden fonksiyon
  // ÖNEMLİ: Önce büyük Türkçe harfler dönüştürülmeli, sonra toLowerCase uygulanmalı
  function normalizeTR(data) {
    if (!data) return "";

    return (
      data
        .toString()
        // Önce büyük Türkçe harfleri küçüğe çevir (toLowerCase'dan önce!)
        .replace(/İ/gi, "i")
        .replace(/I/g, "ı") // Noktasız büyük I -> ı
        .replace(/Ş/gi, "s")
        .replace(/Ğ/gi, "g")
        .replace(/Ü/gi, "u")
        .replace(/Ö/gi, "o")
        .replace(/Ç/gi, "c")
        // Sonra standart toLowerCase
        .toLowerCase()
        // Küçük Türkçe harfleri de ASCII'ye çevir
        .replace(/ı/g, "i")
        .replace(/ş/g, "s")
        .replace(/ğ/g, "g")
        .replace(/ü/g, "u")
        .replace(/ö/g, "o")
        .replace(/ç/g, "c")
        .replace(/â/g, "a")
        .replace(/î/g, "i")
        .replace(/û/g, "u")
    );
  }

  // Global search override
  $.fn.dataTable.ext.type.search.string = function (data) {
    return normalizeTR(data);
  };

  // Sütun bazlı arama için özel filter - Input değerlerini direkt DOM'dan oku
  $.fn.dataTable.ext.search.push(
    function (settings, searchData, dataIndex, rowData, counter) {
      // Eğer tablo server-side ise, client-side filtreleme yapma
      if (settings.oFeatures.bServerSide) return true;

      var tableId = settings.sTableId;
      var dominated = false;

      // Bu tablodaki tüm arama inputlarını bul
      $("#" + tableId + " .search-input-row input").each(function () {
        var searchValue = $(this).val();
        if (searchValue && searchValue.length > 0) {
          var colIdx = parseInt($(this).attr("data-col-idx"));
          if (!isNaN(colIdx)) {
            var cellValue = searchData[colIdx] || "";
            var normalizedCell = normalizeTR(cellValue);
            var normalizedSearch = normalizeTR(searchValue);

            if (normalizedCell.indexOf(normalizedSearch) === -1) {
              dominated = true;
              return false; // break
            }
          }
        }
      });

      return !dominated;
    },
  );
})();
