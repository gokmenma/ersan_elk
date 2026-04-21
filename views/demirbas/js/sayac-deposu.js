$(function () {
    var apiUrl = "views/demirbas/api.php";
    var $pageLoader = $("#personel-loader");
    var activeLoaderRequests = 0;
    var loaderHideTimer = null;

    function showPageLoader() {
        if (loaderHideTimer) {
            clearTimeout(loaderHideTimer);
            loaderHideTimer = null;
        }
        activeLoaderRequests += 1;
        $pageLoader.stop(true, true).show();
    }

    function hidePageLoader(force) {
        if (force === true) {
            activeLoaderRequests = 0;
        } else {
            activeLoaderRequests = Math.max(0, activeLoaderRequests - 1);
        }

        if (activeLoaderRequests > 0) return;

        loaderHideTimer = setTimeout(function () {
            $pageLoader.stop(true, true).fadeOut(200);
        }, 120);
    }

    // Güvenli DataTable başlatma (Otomatik Preloader Desteği ile)
    function safeInitTable(selector, customOptions) {
        if ($.fn.DataTable.isDataTable(selector)) {
            return $(selector).DataTable();
        }
        var defaults = getDatatableOptions();

        // Loader'ı çizim yerine gerçek ajax lifecycle'ına bağla.
        var $table = $(selector);
        $table.off('.sayacLoader');
        $table.on('preXhr.dt.sayacLoader', function () {
            showPageLoader();
        });
        $table.on('xhr.dt.sayacLoader', function () {
            hidePageLoader();
        });
        $table.on('error.dt.sayacLoader', function () {
            hidePageLoader();
        });

        var merged = $.extend(true, {}, defaults, customOptions);
        return $(selector).DataTable(merged);
    }

    // =============================================
    // 1. KASKİ TARİH BAZLI TABLO
    // =============================================
    var kaskiTarihTable = safeInitTable("#kaskiTarihTable", {
        serverSide: true,
        ajax: {
            url: apiUrl,
            type: "POST",
            data: function (d) { d.action = "sayac-kaski-tarih-list"; },
        },
        columns: [
            {
                className: 'details-control text-center',
                orderable: false,
                data: null,
                defaultContent: '<i class="bx bx-chevron-right fs-4 cursor-pointer text-primary"></i>',
                width: '5%'
            },
            { data: "tarih", width: '20%' },
            { data: "islem_tipi", width: '40%' },
            { data: "yon", className: "text-center", width: '15%' },
            { data: "adet", className: "text-center", width: '15%' },
        ],
        order: [[1, "desc"]],
        pageLength: 25,
    });

    $('#kaskiTarihTable tbody').on('click', 'tr', function (e) {
        // Eğer bir butona veya etkileşimli elemana basıldıysa accordion'u tetikleme
        if ($(e.target).closest('button, a, input, .dropdown').length) return;
        
        e.stopImmediatePropagation();
        
        var tr = $(this);
        var row = kaskiTarihTable.row(tr);
        var icon = tr.find('td.details-control i');

        if (tr.hasClass('shown')) {
            // Açıksa kapat
            row.child.hide();
            tr.removeClass('shown');
            icon.removeClass('bx-chevron-down').addClass('bx-chevron-right');
        } else {
            // Kapalıysa aç
            var dateRaw = row.data().islem_tarih_raw;
            var islemTipiRaw = row.data().islem_tipi_raw || row.data().islem_tipi;
            if (!dateRaw) return;

            tr.addClass('shown');
            icon.removeClass('bx-chevron-right').addClass('bx-chevron-down');
            
            // Geçici yükleme göster
            row.child('<div class="text-center p-3"><div class="spinner-border text-primary spinner-border-sm me-2"></div> Detaylar Yükleniyor...</div>').show();
            
            $.post(apiUrl, { action: 'sayac-kaski-date-details', tarih: dateRaw, islem_tipi: islemTipiRaw }, function(res) {
                if (res.status === 'success') {
                    row.child(res.html).show();
                } else {
                    row.child('<div class="alert alert-danger m-2">' + res.message + '</div>').show();
                    tr.removeClass('shown'); // Açılmadıysa class'ı temizle
                    icon.removeClass('bx-chevron-down').addClass('bx-chevron-right');
                }
            }, 'json').fail(function() {
                row.child('<div class="alert alert-danger m-2">Sunucu hatası oluştu.</div>').show();
                tr.removeClass('shown');
                icon.removeClass('bx-chevron-down').addClass('bx-chevron-right');
            });
        }
    });

    // =============================================
    // 2. BİZİM DEPO SAYAÇ TABLOSU
    // =============================================
    var depoSayacTable = safeInitTable("#depoSayacTable", {
        serverSide: true,
        ajax: {
            url: apiUrl,
            type: "POST",
            data: function (d) {
                d.action = "demirbas-listesi";
                d.tab = "sayac";
                d.lokasyon = "bizim_depo";
                d.status_filter = $('input[name="sayac-status-filter"]:checked').val() || "";
            },
        },
        columns: [
            { data: "checkbox", className: "text-center", orderable: false, searchable: false },
            { data: "demirbas_adi" },
            { data: "marka_model" },
            { data: "seri_no" },
            { data: "stok", className: "text-center" },
            { data: "durum", className: "text-center" },
            { data: "aciklama", defaultContent: "" },
            { data: "tarih" },
            { data: "islemler", className: "text-center", orderable: false },
        ],
        order: [[1, "desc"]],
        pageLength: 25,
    });

    // =============================================
    // 3. PERSONEL BAZLI TABLO
    // =============================================
    var personelTable = safeInitTable("#sayacPersonelTable", {
        serverSide: true,
        ajax: {
            url: apiUrl,
            type: "POST",
            data: function (d) { d.action = "sayac-personel-list"; },
        },
        columns: [
            { data: "sira", className: "text-center", orderable: false, searchable: false },
            { data: "personel_adi" },
            { data: "aldigi_yeni", className: "text-center" },
            { data: "taktigi", className: "text-center" },
            { data: "elinde_yeni", className: "text-center" },
            { data: "aldigi_hurda", className: "text-center" },
            { data: "teslim_hurda", className: "text-center" },
            { data: "elinde_hurda", className: "text-center" },
        ],
        order: [[1, "asc"]],
        pageLength: 25,
    });

    // =============================================
    // 4. HAREKETLER TABLOSU
    // =============================================
    // Görünüm Modu Restore Et
    var savedHareketView = localStorage.getItem('sayac_hareket_view_mode') || 'grouped';
    $('input[name="hareket-view-mode"][value="' + savedHareketView + '"]').prop('checked', true);

    var hareketTable = safeInitTable("#hareketTable", {
        serverSide: true,
        ajax: {
            url: apiUrl,
            type: "POST",
            data: function (d) {
                var viewMode = $('input[name="hareket-view-mode"]:checked').val() || "grouped";
                d.action = viewMode === "grouped" ? "sayac-depo-hareketleri-grouped" : "sayac-depo-hareketleri";
                d.status_filter = $('input[name="hareket-status-filter"]:checked').val() || "";
            },
        },
        columns: [
            { data: "checkbox", className: "text-center", orderable: false, searchable: false },
            { data: "id", className: "text-center" },
            { data: "hareket_tipi" },
            { data: "demirbas_adi" },
            { data: "seri_no" },
            { data: "miktar", className: "text-center" },
            { data: "lokasyon_personel" },
            { data: "aciklama" },
            { data: "tarih", className: "text-center" },
            { data: "islem", className: "text-center", orderable: false, searchable: false },
        ],
        order: [[8, "desc"]],
        pageLength: 25,
    });

    // =============================================
    // ÖZET KARTLARINI YÜKLE
    // =============================================
    function loadDepoSummary() {
        $.post(apiUrl, { action: "sayac-global-summary" }, function (res) {
            if (res.status === "success") {
                let d = res;
                let yeniDepo = parseInt(d.yeni_depoda) || 0;
                let hurdaDepo = parseInt(d.hurda_depoda) || 0;
                let yeniPersonel = parseInt(d.yeni_personelde) || 0;
                let hurdaPersonel = parseInt(d.hurda_personelde) || 0;
                let takilan = parseInt(d.takilan) || 0;
                let hurdaKaski = parseInt(d.hurda_kaskiye) || 0;
                let yeniToplam = parseInt(d.toplam_alinan) || 0;
                let yeniKayip = parseInt(d.kayip_yeni) || 0;
                let hurdaToplam = parseInt(d.toplam_hurda) || 0;

                // UI Güncelleme (Bizim Depo Tabı)
                $("#sayacCardToplamGiren").text(yeniToplam);
                $("#sayacCardDepoKalan").text(yeniDepo);
                $("#sayacCardTakilan").text(takilan);
                $("#sayacCardPersonelZimmetli").text(yeniPersonel);
                $("#sayacCardKayipYeni").text(yeniKayip);

                $("#sayacCardToplamHurda").text(hurdaToplam);
                $("#sayacCardKaskiyeTeslim").text(hurdaKaski);
                $("#sayacCardHurda").text(hurdaDepo);
                $("#sayacCardPersonelHurda").text(hurdaPersonel);
                $("#sayacCardKayipHurda").text(0);

            }
        }, "json");

        $.post(apiUrl, { action: "sayac-kaski-ozet" }, function (res) {
            if (res.status === "success") {
                let toplamAlinanYeni = parseInt(res.toplam_alinan_yeni) || 0;
                let toplamTeslimHurda = parseInt(res.toplam_teslim_hurda) || 0;

                $("#kaskiSummaryToplamAlinan").text(toplamAlinanYeni);
                $("#kaskiSummaryIadeEdilen").text(toplamTeslimHurda);
                $("#kaskiSummaryFark").text(toplamAlinanYeni - toplamTeslimHurda);
            }
        }, "json");
    }
    loadDepoSummary();

    function reloadAllTables() {
        try { kaskiTarihTable.ajax.reload(null, false); } catch(e) {}
        try { depoSayacTable.ajax.reload(null, false); } catch(e) {}
        try { personelTable.ajax.reload(null, false); } catch(e) {}
        try { hareketTable.ajax.reload(null, false); } catch(e) {}
        loadDepoSummary();
        loadPersonelAllSummary();
    }

    // =============================================
    // PERSONEL KPI KARTLARI
    // =============================================
    function loadPersonelAllSummary() {
        $.post(apiUrl, { action: "sayac-personel-all-summary" }, function (res) {
            if (res.status === "success") {
                $("#persKpiVerilen").text(res.toplam_verilen ?? 0);
                $("#persKpiTakilan").text(res.toplam_takilan ?? 0);
                $("#persKpiEldeYeni").text(res.elde_kalan_yeni ?? 0);
                $("#persKpiHurda").text(res.toplam_hurda ?? 0);
                $("#persKpiTeslimHurda").text(res.toplam_teslim_hurda ?? 0);
                $("#persKpiEldeHurda").text(res.elde_kalan_hurda ?? 0);
            }
        }, "json");
    }
    loadPersonelAllSummary();

    // =============================================
    // TAB BUTON GÖRÜNÜRLÜKLERİ & URL PERSISTENCE
    // =============================================
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("id");
        updateButtonVisibility(target);
        
        // URL Hash güncelle
        var tabPaneId = $(e.target).data("bs-target").replace("#", "");
        if (history.replaceState) {
            history.replaceState(null, null, "#" + tabPaneId);
        } else {
            window.location.hash = tabPaneId;
        }

        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 150);
    });

    // Sayfa yüklendiğinde hashe göre sekmeyi aç
    function loadTabFromHash() {
        var hash = window.location.hash;
        if (hash) {
            var $tab = $('button[data-bs-target="' + hash + '"]');
            if ($tab.length) {
                bootstrap.Tab.getOrCreateInstance($tab[0]).show();
            }
        } else {
            updateButtonVisibility("kaski-tab"); // Varsayılan
        }
    }
    loadTabFromHash();

    function updateButtonVisibility(activeTabId) {
        // Dropdown öğelerini (li) gizle/göster
        $("#btnSayacEkleDrop, #btnPersoneleZimmetleDrop, #btnSayacKaskiyeTeslimDrop").closest('li').addClass("d-none");
        // Divider'ı da gizle
        $("#btnSayacKaskiyeTeslimDrop").closest('li').next('li').find('hr').closest('li').addClass("d-none");

        if (activeTabId === "depo-tab") {
            $("#btnSayacEkleDrop, #btnPersoneleZimmetleDrop, #btnSayacKaskiyeTeslimDrop").closest('li').removeClass("d-none");
             $("#btnSayacKaskiyeTeslimDrop").closest('li').next('li').find('hr').closest('li').removeClass("d-none");
        }
    }

    // Dropdown öğelerine basınca gizli asıl butonları tetikle
    $(document).on("click", "#btnSayacEkleDrop", function() { $("#btnSayacEkle").trigger("click"); });
    $(document).on("click", "#btnPersoneleZimmetleDrop", function() { $("#btnPersoneleZimmetle").trigger("click"); });
    $(document).on("click", "#btnSayacKaskiyeTeslimDrop", function() { $("#btnSayacKaskiyeTeslim").trigger("click"); });

    // Tab-level seçim butonları tetiklemeleri
    $(document).on("click", "#btnTopluSilSayacTab", function() { $("#btnTopluSilSayac").trigger("click"); });

    // =============================================
    // FİLTRELER
    // =============================================
    $('input[name="sayac-status-filter"]').on('change', function () {
        depoSayacTable.ajax.reload();
        
        // Görsel efekt: Aktif butonu işaretle
        $(this).closest('.status-filter-group').find('label.btn').removeClass('active');
        $('label[for="' + $(this).attr('id') + '"]').addClass('active');

        var filter = $(this).val();
        if (filter === 'yeni') {
            $("#depoSayacTable thead th").eq(3).text("Seri No");
            $("#yeniSayacCardCol").fadeIn(300).removeClass('col-xl-6 col-md-6').addClass('col-xl-12 col-md-12');
            $("#hurdaSayacCardCol").hide();
        } else if (filter === 'hurda') {
            $("#depoSayacTable thead th").eq(3).text("Abone No");
            $("#hurdaSayacCardCol").fadeIn(300).removeClass('col-xl-6 col-md-6').addClass('col-xl-12 col-md-12');
            $("#yeniSayacCardCol").hide();
        } else {
            $("#depoSayacTable thead th").eq(3).text("Abone No");
            $("#yeniSayacCardCol").fadeIn(300).removeClass('col-xl-12 col-md-12').addClass('col-xl-6 col-md-6');
            $("#hurdaSayacCardCol").fadeIn(300).removeClass('col-xl-12 col-md-12').addClass('col-xl-6 col-md-6');
        }

        // Buton durumlarını güncelle
        if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
    }).filter(':checked').trigger('change');
    $('input[name="hareket-status-filter"]').on('change', function () {
        hareketTable.ajax.reload();
        
        // Görsel efekt: Aktif butonu işaretle
        $(this).closest('.status-filter-group').find('label.btn').removeClass('active');
        $('label[for="' + $(this).attr('id') + '"]').addClass('active');
    });

    $('input[name="hareket-view-mode"]').on('change', function () {
        var val = $(this).val();
        localStorage.setItem('sayac_hareket_view_mode', val);
        hareketTable.ajax.reload();
    });

    // Hareket Grubu Detaylarını Göster (Accordion)
    $(document).on("click", ".view-details-group, tr.group-row", function (e) {
        // Eğer bir butona veya seçme kutusuna tıklandıysa işlemi durdur
        if ($(e.target).closest('input, .hareket-select, .hareket-sil-btn, button:not(.view-details-group)').length) return;
        
        var tr = $(this).closest('tr');
        var row = hareketTable.row(tr);
        
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.view-details-group i').removeClass('bx-chevron-up').addClass('bx-chevron-down');
        } else {
            var data = row.data();
            var gun = data.gun;
            var personelId = data.personel_id;
            var statusFilter = $('input[name="hareket-status-filter"]:checked').val() || "";
            
            // Yükleniyor göster
            row.child('<div class="text-center p-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Hareketler getiriliyor...</div>').show();
            tr.addClass('shown');
            tr.find('.view-details-group i').removeClass('bx-chevron-down').addClass('bx-chevron-up');
            
            $.post(apiUrl, { action: "sayac-depo-hareketleri-detay", gun: gun, personel_id: personelId, status_filter: statusFilter }, function (res) {
                if (res.status === 'success') {
                    row.child(res.html).show();
                    // Yeni açılan tablodaki checkbox vb. olaylarını tetiklemek gerekirse burada yapılabilir
                } else {
                    row.child('<div class="alert alert-danger m-2">' + res.message + '</div>').show();
                }
            }, 'json');
        }
    });

    var globalSeciliSayacIds = [];
    var isTumuSecildi = false;
    var globalSeciliHareketIds = [];
    var isHareketTumuSecildi = false;

    // =============================================
    // SATIRA TIKLA = SEÇ (Genel Seçim Desteği)
    // =============================================
    // mousedown kullanıyoruz çünkü DataTable responsive eklentisi 
    // click olayını yakalayıp engelleyebiliyor.
    // document üzerinden delegasyon yapıyoruz çünkü DataTable tbody'yi dinamik oluşturuyor.
    $(document).on("mousedown", "#depoSayacTable tbody td, #zimmetListesiBody td, #personelZimmetTable tbody td, #aparatZimmetTable tbody td, #hareketTable tbody td", function (e) {
        // Sadece sol tıklama
        if (e.which !== 1) return;
        
        var target = e.target;
        var tagName = target.tagName.toUpperCase();
        
        // Checkbox veya label ise tarayıcı kendi halletsin
        if (tagName === 'INPUT' || tagName === 'LABEL') return;
        // Checkbox container içine tıklandıysa (label'ın üstü)
        if ($(target).closest('.custom-checkbox-container').length) return;
        // Buton, link, dropdown, dtr-control
        if ($(target).closest('button, a, .dropdown, .details-control, .btn, .dtr-control').length) return;
        
        var $tr = $(this).closest('tr');
        var $cb = $tr.find('input.sayac-select, input.zimmet-select, input.hareket-select').first();
        
        if ($cb.length > 0 && !$cb.prop('disabled')) {
            e.preventDefault(); // Metnin seçilmesini engelle
            var newVal = !$cb.prop("checked");
            $cb.prop("checked", newVal);
            $cb.trigger('change'); // Change olayını tetikle ki butonlar güncellensin
        }
    });

    // Checkbox doğrudan tıklandığında (label üzerinden) satırı renklendir
    $(document).on("change", ".sayac-select, .zimmet-select, .hareket-select", function () {
        var isChecked = $(this).prop("checked");
        $(this).closest("tr").toggleClass("table-active", isChecked);
        if (typeof updateSelectionInfo === "function") updateSelectionInfo();
        if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
        
        // Hareket seçim bilgisini güncelle
        if ($(this).hasClass("hareket-select")) {
            updateHareketSelectionInfo();
        }
        
        // Hareket silme butonunu göster/gizle
        if ($(this).hasClass("hareket-select")) {
            var hasHarekSecim = $(".hareket-select:checked").length > 0;
            $("#btnTopluSilHareket").toggleClass("d-none", !hasHarekSecim);
        }
    });

    // Sayfa değiştiğinde veya tablo yenilendiğinde seçili sınıflarını temizle
    $(document).on("draw.dt", function () {
        $("tr.selected").removeClass("selected");
    });

    // =============================================
    // TOPLU SEÇİM BİLGİ KARTI
    // =============================================
    $(document).on("change", "#selectAllSayac", function () {
        var checked = $(this).prop("checked");
        if (checked) {
            // Tüm filtrelenmiş kayıtların ID'lerini sunucudan al
            var params = depoSayacTable.ajax.params();
            params.action = "get-filtered-sayac-ids";
            params.tab = "sayac_bizim_depo";
            $.post(apiUrl, params, function(res) {
                if (res.status === 'success') {
                    globalSeciliSayacIds = res.ids;
                    isTumuSecildi = true;
                    $(".sayac-select").prop("checked", true);
                    updateSelectionInfo();
                }
            }, 'json');
        } else {
            isTumuSecildi = false;
            globalSeciliSayacIds = [];
            $(".sayac-select").prop("checked", false);
            updateSelectionInfo();
            if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
        }
    });

    $(document).on("change", ".sayac-select", function () {
        if (isTumuSecildi && !$(this).prop("checked")) {
            isTumuSecildi = false;
            globalSeciliSayacIds = [];
            $("#selectAllSayac").prop("checked", false);
        }
        updateSelectionInfo();
    });

    function getAktifSeciliIdler() {
        if (isTumuSecildi && globalSeciliSayacIds.length > 0) {
            return globalSeciliSayacIds;
        }
        return getSelectedIds(".sayac-select");
    }

    function updateSelectionInfo() {
        var count = getSelectedIds(".sayac-select").length;
        var total = isTumuSecildi ? globalSeciliSayacIds.length : count;
        var $info = $("#sayacSecimInfo");

        if (count > 0 || isTumuSecildi) {
            if ($info.length === 0) {
                $("body").append('<div id="sayacSecimInfo" class="selection-info-bar"></div>');
                $info = $("#sayacSecimInfo");
            }

            var statusText = isTumuSecildi ? 'Filtrelenen tüm ' : 'Seçilen: ';
            
            $info.html(
                '<div class="selection-info-status">' +
                    '<span>' + statusText + '</span>' +
                    '<span class="count-badge">' + total + '</span>' +
                    '<span> kayıt</span>' +
                '</div>' +
                '<div class="selection-info-actions">' +
                    (!isTumuSecildi ? '<button type="button" id="secimTumuFiltre" class="selection-action-btn selection-action-btn-primary"><i class="bx bx-select-multiple"></i> Tümünü Seç</button>' : '') +
                    '<button type="button" id="secimTemizle" class="selection-action-btn selection-action-btn-danger"><i class="bx bx-trash"></i> Temizle</button>' +
                '</div>'
            );
            
            // Reflow trigger for transition
            if ($info[0]) {
                $info[0].offsetHeight;
                $info.addClass("show");
            }
        } else {
            $info.removeClass("show");
            setTimeout(function() {
                if ($("#sayacSecimInfo").length && !$("#sayacSecimInfo").hasClass("show")) {
                    $("#sayacSecimInfo").remove();
                }
            }, 500);
        }
        
        if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
    }

    $(document).on("click", "#secimTumuFiltre", function () {
        var params = depoSayacTable.ajax.params();
        params.action = "get-filtered-sayac-ids";
        params.tab = "sayac_bizim_depo";
        
        var $btn = $(this);
        var oldHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span> Bekleyiniz...').css("pointer-events", "none");
        
        $.post(apiUrl, params, function(res) {
            if (res.status === 'success') {
                globalSeciliSayacIds = res.ids;
                isTumuSecildi = true;
                $(".sayac-select").prop("checked", true);
                $("#selectAllSayac").prop("checked", true);
                updateSelectionInfo();
                if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
            } else {
                Swal.fire("Hata", res.message, "error");
                $btn.html(oldHtml).css("pointer-events", "auto");
            }
        }, 'json');
    });

    $(document).on("click", "#secimTemizle", function () {
        isTumuSecildi = false;
        globalSeciliSayacIds = [];
        $(".sayac-select").prop("checked", false);
        $("#selectAllSayac").prop("checked", false);
        updateSelectionInfo();
    });

    function updateBulkActionButtons() {
        var filter = $('input[name="sayac-status-filter"]:checked').val() || "";
        var seciliIdler = getAktifSeciliIdler();
        var hasSecim = seciliIdler.length > 0;

        // Varsayılan State
        var canZimmet = true;
        var canKaski = true;

        if (filter === 'yeni') {
            canZimmet = true;
            canKaski = false;
        } else if (filter === 'hurda') {
            canZimmet = false;
            canKaski = true;
        } else {
            // Tümü modundaysa seçilen satırların içeriğine bak
            if (hasSecim && !isTumuSecildi) {
                var hasHurda = false;
                var hasYeni = false;
                $(".sayac-select:checked").each(function() {
                    var rowData = depoSayacTable.row($(this).closest('tr')).data();
                    if (rowData && rowData.DT_RowData) {
                        if (rowData.DT_RowData.durum === 'hurda' || rowData.durum === 'hurda') hasHurda = true;
                        else hasYeni = true;
                    }
                });

                if (hasHurda && !hasYeni) { canZimmet = false; canKaski = true; }
                else if (hasYeni && !hasHurda) { canZimmet = true; canKaski = false; }
            }
        }

        // Uygula
        // Functional Buttons (Hidden & FAB)
        $("#btnPersoneleZimmetle, #fabPersoneleZimmetle").prop("disabled", !canZimmet).css("opacity", canZimmet ? "1" : "0.5");
        $("#btnSayacKaskiyeTeslim, #fabKaskiyeTeslim").prop("disabled", !canKaski).css("opacity", canKaski ? "1" : "0.5");

        // Header Trash icon
        $("#btnTopluSilSayac").prop("disabled", !hasSecim).css("opacity", hasSecim ? "1" : "0.5");

        // Dropdown Items (Zimmetle, İade Et)
        $("#btnPersoneleZimmetleDrop").toggleClass("disabled", !canZimmet).css("pointer-events", canZimmet ? "auto" : "none").css("opacity", canZimmet ? "1" : "0.5");
        $("#btnSayacKaskiyeTeslimDrop").toggleClass("disabled", !canKaski).css("pointer-events", canKaski ? "auto" : "none").css("opacity", canKaski ? "1" : "0.5");

        // Tab-level seçim butonları durumu
        $("#btnTopluSilSayacTab").prop("disabled", !hasSecim).css("opacity", hasSecim ? "1" : "0.5");
    }

    // =============================================
    // SAYAÇ GİR BUTONI → Modal aç
    // =============================================
    $(document).on("click", "#btnSayacEkle", function () {
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("sayacGirModal"));
        modal.show();
    });

    // Sayaç gir modal - kayıt modu toggle
    $('input[name="seri_mod"]').on('change', function() {
        var v = $(this).val();
        if (v === 'toplu') {
            $("#sayacSeriTekli").hide();
            $("#sayacSeriToplu").show();
        } else {
            $("#sayacSeriTekli").show();
            $("#sayacSeriToplu").hide();
        }
    });

    // Sayaç Gir - Toplu seri önizleme
    $(document).on("input", "#sayacGirForm #seri_baslangic, #sayacGirForm #seri_adet, #sayacGirForm #seri_bitis", function() {
        var start = $("#sayacGirForm #seri_baslangic").val();
        var end = $("#sayacGirForm #seri_bitis").val();
        var count = parseInt($("#sayacGirForm #seri_adet").val()) || 0;

        if (!start) { $("#sayacSeriOnizleme").hide(); return; }

        var numPart = start.replace(/\D/g, '');
        var prefix = start.replace(/\d+$/, '');
        var startNum = parseInt(numPart) || 0;

        if (end) {
            var endNum = parseInt(end.replace(/\D/g, '')) || 0;
            count = endNum - startNum + 1;
            if (count > 0) {
                $("#sayacGirForm #seri_adet").val(count);
            }
        }

        if (count <= 0) { $("#sayacSeriOnizleme").hide(); return; }
        if (count > 500) count = 500;

        var seriler = [];
        for (var i = 0; i < count; i++) {
            seriler.push(prefix + (startNum + i));
        }

        var listHtml = seriler.slice(0, 50).map(function(s) {
            return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">' + s + '</span>';
        }).join('');
        if (count > 50) listHtml += '<span class="badge bg-secondary">+' + (count - 50) + ' daha...</span>';

        $("#sayacSeriOnizlemeList").html(listHtml);
        $("#sayacSeriToplamBadge").text(count + " adet");
        $("#sayacSeriOnizleme").show();
        // Miktar alanını güncelle
        $("#sayacGirForm #miktar").val(count);
    });

    // Sayaç gir kaydet
    $(document).on("click", "#sayacGirKaydet", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var $form = $("#sayacGirForm");
        var formData = new FormData($form[0]);
        var seriMod = $form.find('input[name="seri_mod"]:checked').val();

        if (seriMod === 'toplu') {
            formData.set("action", "demirbas-toplu-kaydet");
            
            // Seri listesini oluştur
            var start = $("#sayacGirForm #seri_baslangic").val();
            var count = parseInt($("#sayacGirForm #seri_adet").val()) || 0;
            
            if (!start || count <= 0) {
                Swal.fire("Uyarı", "Başlangıç seri ve adet giriniz.", "warning");
                return;
            }

            var numPart = start.replace(/\D/g, '');
            var prefix = start.replace(/\d+$/, '');
            var startNum = parseInt(numPart) || 0;
            var seriler = [];
            for (var i = 0; i < count; i++) {
                seriler.push(prefix + (startNum + i));
            }
            
            formData.append("seri_listesi", JSON.stringify(seriler));
            formData.set("miktar", 1); // Her kayıt için miktar 1 olacak
        } else {
            formData.set("action", "demirbas-kaydet");
        }

        // Durum her zaman "aktif" (yeni sayaç)
        formData.set("durum", "aktif");

        var $btn = $(this);
        if ($btn.prop("disabled")) return;

        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Kaydediliyor...');

        $.ajax({
            url: apiUrl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (res) {
                if (res.status === "success") {
                    bootstrap.Modal.getInstance(document.getElementById('sayacGirModal')).hide();
                    Swal.fire("Başarılı", res.message || "Sayaçlar kaydedildi.", "success");
                    reloadAllTables();
                    $form[0].reset();
                    $("#sayacSeriOnizleme").hide();
                    $("#sayacSeriTekli").show();
                    $("#sayacSeriToplu").hide();
                } else {
                    Swal.fire("Hata", res.message || "Kayıt sırasında hata oluştu.", "error");
                }
            },
            error: function () {
                $btn.prop("disabled", false).html('<i class="bx bx-check me-1"></i>Kaydet');
                Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
            }
        });
    });

    // =============================================
    // PERSONELE ZİMMETLE BUTON → Modal aç
    // =============================================
    $(document).on("click", "#btnPersoneleZimmetle", function () {
        var seciliIdler = getAktifSeciliIdler();
        
        if (seciliIdler.length > 0) {
            // Tablodan seçim yapılmışsa
            $("#sayacTopluTabloSecim").removeClass("d-none");
            $("#sayacTekliSecim").addClass("d-none");
            $("#sayacKoliModuToggle").prop("checked", false).closest('.form-switch').hide();
            $("#sayacKoliTipiSecimi, #sayacKoliSecim").addClass("d-none");
            
            $("#sayac_is_toplu_secim").val("1");
            $("#sayac_secilen_ids").val(JSON.stringify(seciliIdler));
            $("#sayacTopluTabloAdetText").text(seciliIdler.length);
            
            // Miktar alanını gizle ama tarih alanını göster
            $("#sayacTeslimMiktarRow").show();
            $("#sayacTeslimMiktarRow .col-md-6:first-child").hide(); // Miktar gizle
            $("#sayacTeslimMiktarRow .col-md-6:last-child").show(); // Tarih göster
        } else {
            // Standart açılış (seçim yok)
            $("#sayacTopluTabloSecim").addClass("d-none");
            $("#sayacTekliSecim").removeClass("d-none");
            $("#sayacKoliModuToggle").prop("checked", false).closest('.form-switch').show();
            $("#sayac_is_toplu_secim").val("0");
            $("#sayacTeslimMiktarRow").show();
            $("#sayacTeslimMiktarRow .col-md-6").show(); // Tüm alanları göster
        }
        
        var modalEl = document.getElementById("sayacZimmetModal");
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    });

    // Zimmet modal - koli modu toggle
    $(document).on("change", "#sayacKoliModuToggle", function () {
        if ($("#sayac_is_toplu_secim").val() === "1") return; // Tablodan seçili ise karışma
        
        if ($(this).prop("checked")) {
            $("#sayacKoliTipiSecimi").removeClass("d-none");
            $("#sayacTekliSecim").addClass("d-none");
            $("#sayacKoliSecim").removeClass("d-none");
        } else {
            $("#sayacKoliTipiSecimi").addClass("d-none");
            $("#sayacTekliSecim").removeClass("d-none");
            $("#sayacKoliSecim").addClass("d-none");
        }
    });

    // Zimmet modal - Ekle butonu (çoklu seri)
    var sayacZimmetEklenenSeriler = [];
    $(document).on("click", "#btnSayacKoliEkle", function () {
        var input = $("#sayac_koli_baslangic_seri").val().trim();
        if (!input) return;
        var koliTipi = parseInt($('input[name="sayac_koli_tipi"]:checked').val()) || 10;
        var parts = input.split(",");

        parts.forEach(function (part) {
            part = part.trim();
            if (!part) return;
            var numPart = part.replace(/\D/g, '');
            var prefix = part.replace(/\d+$/, '');
            var startNum = parseInt(numPart) || 0;

            for (var i = 0; i < koliTipi; i++) {
                var seri = prefix + (startNum + i);
                if (sayacZimmetEklenenSeriler.indexOf(seri) === -1) {
                    sayacZimmetEklenenSeriler.push(seri);
                }
            }
        });

        renderSayacKoliList();
        $("#sayac_koli_baslangic_seri").val("").focus();
    });

    // Excel'den yükle
    $(document).on("change", "#sayacKoliExcelFile", function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var wb = XLSX.read(e.target.result, { type: "binary" });
                var ws = wb.Sheets[wb.SheetNames[0]];
                var data = XLSX.utils.sheet_to_json(ws, { header: 1 });
                data.forEach(function (row) {
                    if (row[0]) {
                        var seri = String(row[0]).trim();
                        if (seri && sayacZimmetEklenenSeriler.indexOf(seri) === -1) {
                            sayacZimmetEklenenSeriler.push(seri);
                        }
                    }
                });
                renderSayacKoliList();
            } catch (ex) {
                Swal.fire("Hata", "Excel dosyası okunamadı.", "error");
            }
        };
        reader.readAsBinaryString(file);
        $(this).val("");
    });

    function renderSayacKoliList() {
        if (sayacZimmetEklenenSeriler.length === 0) {
            $("#sayacEklenenKoliler").addClass("d-none").html("");
            $("#sayacToplamKoliBilgisi").addClass("d-none");
            return;
        }

        var koliTipi = parseInt($('input[name="sayac_koli_tipi"]:checked').val()) || 10;
        var html = "";
        sayacZimmetEklenenSeriler.forEach(function (seri, idx) {
            html += '<div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">';
            html += '<span class="small"><code>' + seri + '</code></span>';
            html += '<button type="button" class="btn btn-link btn-sm text-danger p-0 sayac-koli-sil" data-idx="' + idx + '"><i class="bx bx-x"></i></button>';
            html += '</div>';
        });
        $("#sayacEklenenKoliler").html(html).removeClass("d-none");
        var koliCount = Math.ceil(sayacZimmetEklenenSeriler.length / koliTipi);
        $("#sayacLblKoli").text(koliCount);
        $("#sayacLblSayac").text(sayacZimmetEklenenSeriler.length);
        $("#sayacToplamKoliBilgisi").removeClass("d-none");
    }

    $(document).on("click", ".sayac-koli-sil", function () {
        var idx = $(this).data("idx");
        sayacZimmetEklenenSeriler.splice(idx, 1);
        renderSayacKoliList();
    });

    // Zimmet kaydet
    $(document).on("click", "#sayacZimmetKaydet", function () {
        var isKoli = $("#sayacKoliModuToggle").prop("checked");
        var isTopluTablo = $("#sayac_is_toplu_secim").val() === "1";
        
        var formData = {
            action: "zimmet-ver",
            personel_id: $("#sayacZimmetForm #personel_id").val(),
            teslim_tarihi: $("#sayacZimmetForm #teslim_tarihi").val(),
            aciklama: $("#sayacZimmetForm #aciklama").val(),
            zimmet_turu: "sayac"
        };
        
        if (!isTopluTablo) {
            formData.teslim_miktar = $("#sayacZimmetForm #teslim_miktar").val();
        }

        if (isTopluTablo) {
            formData.is_toplu_secim = "1";
            formData.secilen_ids = $("#sayac_secilen_ids").val();
        } else if (isKoli && sayacZimmetEklenenSeriler.length > 0) {
            formData.koli_seriler = sayacZimmetEklenenSeriler;
            formData.koli_mod = "1";
        } else {
            formData.demirbas_id = $("#sayacZimmetForm #demirbas_id").val();
        }

        if (!formData.personel_id) {
            Swal.fire("Uyarı", "Personel seçimi zorunludur.", "warning");
            return;
        }

        var $btn = $(this);
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>İşleniyor...');

        $.post(apiUrl, formData, function (res) {
            $btn.prop("disabled", false).html('<i class="bx bx-check-square me-1"></i>Zimmet Ver');
            if (res.status === "success") {
                bootstrap.Modal.getInstance(document.getElementById("sayacZimmetModal")).hide();
                Swal.fire("Başarılı", res.message, "success");
                reloadAllTables();
                sayacZimmetEklenenSeriler = [];
                $("#sayacZimmetForm")[0].reset();
            } else {
                Swal.fire("Hata", res.message, "error");
            }
        }, "json").fail(function() {
            $btn.prop("disabled", false).html('<i class="bx bx-check-square me-1"></i>Zimmet Ver');
            Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
        });
    });

    // Personel Select2 AJAX
    function initPersonelSelect(selector) {
        var $el = $(selector);
        if ($el.hasClass("select2-hidden-accessible")) {
            $el.select2('destroy');
        }
        $el.empty().select2({
            ajax: {
                url: apiUrl,
                type: "POST",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return { action: "personel-ara", q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: "Personel arayın...",
            allowClear: true,
            dropdownParent: $el.closest('.modal')
        });
    }

    // Sayaç Select2 AJAX (zimmet modal için)
    function initSayacSelect(selector) {
        var $el = $(selector);
        if ($el.hasClass("select2-hidden-accessible")) {
            $el.select2('destroy');
        }
        $el.empty().select2({
            ajax: {
                url: apiUrl,
                type: "POST",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return { action: "demirbas-ara", q: params.term, tab: "sayac", lokasyon: "bizim_depo" };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                },
                cache: true
            },
            minimumInputLength: 1,
            placeholder: "Sayaç arayın...",
            allowClear: true,
            dropdownParent: $el.closest('.modal')
        });
    }

    // Modal açıldığında Select2 init
    $(document).on("shown.bs.modal", "#sayacZimmetModal", function () {
        initPersonelSelect("#sayacZimmetModal #personel_id");
        initSayacSelect("#sayacZimmetModal #demirbas_id");
    });

    $(document).on("shown.bs.modal", "#kasiyeTeslimModal", function () {
        // Flatpickr init
        if ($.fn.flatpickr || window.flatpickr) {
            $("#kasiyeTeslimModal .flatpickr").flatpickr({ dateFormat: "d.m.Y", locale: "tr" });
        }
    });

    // =============================================
    // KASKİYE TESLİM (Modal ile)
    // =============================================
    $(document).on("click", "#btnSayacKaskiyeTeslim", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var selected = getAktifSeciliIdler();
        if (selected.length === 0) {
            Swal.fire("Uyarı", "Lütfen Kaski'ye iade edilecek sayaçları seçin.", "warning");
            return;
        }
        // Şifrelenmiş ID'ler zaten elimizde, modalda toplayalım.
        // Ancak IDler dizi olarak geliyor. kaskiyeTeslimForm submission js array olarak alıyor.
        $("#kasiye_toplu_ids").val(selected.join(','));
        $("#kasiye_is_toplu").val("1");
        $("#kasiyeTopluAdetV2").text(selected.length);
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("kasiyeTeslimModal"));
        modal.show();
    });

    // Tekli kaskiye teslim (satırdaki butondan)
    $(document).on("click", ".kaskiye-teslim-btn", function () {
        var id = $(this).data("id");
        $("#kasiye_demirbas_id").val(id);
        $("#kasiye_is_toplu").val("0");
        $("#kasiye_toplu_ids").val("");
        $("#kasiyeTopluAdetV2").text("1");
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("kasiyeTeslimModal"));
        modal.show();
    });

    // Kaskiye teslim form submit (double-submit koruması ile)
    var isKaskiyeSubmitting = false;
    $(document).on("submit", "#kasiyeTeslimForm", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        // Double-submit guard
        if (isKaskiyeSubmitting) return;
        isKaskiyeSubmitting = true;

        var $form = $(this);
        var isToplu = $("#kasiye_is_toplu").val() === "1";
        
        var postData = {
            action: isToplu ? "toplu-kasiye-teslim" : "kasiye-teslim",
            tarih: $form.find('[name="tarih"]').val(),
            aciklama: $form.find('[name="aciklama"]').val(),
            teslim_eden: $form.find('[name="teslim_eden"]').val()
        };

        if (isToplu) {
            postData.ids = JSON.stringify($("#kasiye_toplu_ids").val().split(','));
        } else {
            postData.demirbas_id = $("#kasiye_demirbas_id").val();
        }

        var $btn = $("#btnKasiyeKaydet");
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor...');

        $.post(apiUrl, postData, function (res) {
            if (res.status === "success") {
                var modalEl = document.getElementById("kasiyeTeslimModal");
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                Swal.fire("Başarılı", res.message, "success");
                reloadAllTables();
                // Seçimi ve bilgi barını temizle
                isTumuSecildi = false;
                globalSeciliSayacIds = [];
                $(".sayac-select").prop("checked", false);
                $("#selectAllSayac").prop("checked", false);
                $("#sayacSecimInfo").remove();
            } else {
                Swal.fire("Hata", res.message, "error");
            }
        }, "json").fail(function() {
            Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
        }).always(function() {
            isKaskiyeSubmitting = false;
            $btn.prop("disabled", false).html('<i class="bx bx-check me-1"></i>Evet, Teslim Et');
        });
    });

    // =============================================
    // PERSONEL DETAY MODALI
    // =============================================
    $(document).on("click", ".personel-detay-link", function (e) {
        e.preventDefault();
        var personelId = $(this).data("personel-id");
        var personelAdi = $(this).data("personel-adi");
        $("#personelDetayBaslik").text(personelAdi);
        $("#personelDetayTarihBody").html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Yükleniyor...</td></tr>');
        $("#pdKpiAldigi, #pdKpiTaktigi, #pdKpiEldeYeni, #pdKpiHurda, #pdKpiTeslimHurda, #pdKpiEldeHurda").text("-");

        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("personelDetayModal"));
        modal.show();

        // KPI
        $.post(apiUrl, { action: "sayac-personel-summary", personel_id: personelId }, function (res) {
            if (res.status === "success" && res.summary) {
                var s = res.summary;
                $("#pdKpiAldigi").text(s.bizden_toplam_aldigi ?? 0);
                $("#pdKpiTaktigi").text(s.toplam_taktigi ?? 0);
                $("#pdKpiEldeYeni").text(s.elinde_kalan_yeni ?? 0);
                $("#pdKpiHurda").text(s.toplam_hurda ?? 0);
                $("#pdKpiTeslimHurda").text(s.teslim_edilen_hurda ?? 0);
                $("#pdKpiEldeHurda").text(s.elinde_kalan_hurda ?? 0);
            }
        }, "json");

        // Tarih bazlı döküm
        $.post(apiUrl, { action: "sayac-personel-history", personel_id: personelId }, function (res) {
            if (res.status === "success" && res.rows) {
                var html = "";
                if (res.rows.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-muted py-3">Kayıt bulunamadı</td></tr>';
                } else {
                    res.rows.forEach(function (r) {
                        html += '<tr class="personel-tarih-row" style="cursor:pointer" data-personel-id="' + personelId + '" data-date="' + r.gun + '">';
                        html += '<td class="text-center"><i class="bx bx-chevron-right fs-5 text-muted expand-chevron"></i></td>';
                        html += '<td class="fw-semibold">' + r.gun_format + '</td>';
                        html += '<td class="text-center"><span class="fw-bold text-info">' + r.alinan + '</span></td>';
                        html += '<td class="text-center"><span class="fw-bold text-success">' + r.taktigi + '</span></td>';
                        html += '<td class="text-center"><span class="text-danger">' + r.hurda_alinan + '</span></td>';
                        html += '<td class="text-center"><span class="text-muted">' + r.hurda_teslim + '</span></td>';
                        html += '<td class="text-center"><span class="text-secondary">' + r.kayip + '</span></td>';
                        html += '</tr>';
                    });
                }
                $("#personelDetayTarihBody").html(html);
            }
        }, "json");
    });

    // Personel tablosundaki "Elinde Hurda" badge'ine basınca iade modalını aç
    $(document).on("click", ".hurda-iade-trigger", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var personelId = $(this).data("personel-id");
        var badgeAdet = parseInt($(this).text().replace(/[^0-9]/g, ''), 10) || 0;
        if (!personelId) return;

        // Modal açıldığında demirbas.js tarafında öncelikli olarak bu adet kullanılacak
        window.prefillHurdaIadeAdet = badgeAdet > 0 ? badgeAdet : null;

        $("#btnHurdaSayacIade").trigger("click");

        // Süreç: önce personelden depoya iade alınır, fiziksel teslimde ayrıca KASKI yapılır.
        // Bu yüzden modal badge ile açılsa da "Doğrudan KASKI" varsayılanı kapalı tutulur.
        setTimeout(function () {
            $("#direct_kaski").prop("checked", false).trigger("change");

            var $personelSelect = $("#hurda_personel_id");
            if ($personelSelect.length) {
                var exists = $personelSelect.find("option[value='" + personelId + "']").length > 0;
                if (!exists) {
                    $("#hurdaPersonelTum").prop("checked", true).trigger("change");
                }
                $personelSelect.val(personelId).trigger("change");

                if (window.prefillHurdaIadeAdet && window.prefillHurdaIadeAdet > 0) {
                    setTimeout(function () {
                        $("#hurda_iade_adet").val(window.prefillHurdaIadeAdet);
                    }, 350);
                }
            }
        }, 250);
    });

    // Personel tarih satırına tıklayınca accordion
    $(document).on("click", ".personel-tarih-row", function () {
        var $row = $(this);
        var personelId = $row.data("personel-id");
        var date = $row.data("date");

        if ($row.hasClass("expanded")) {
            $row.removeClass("expanded");
            $row.find(".expand-chevron").css("transform", "rotate(0deg)");
            $row.next(".personel-detail-row").fadeOut(150, function() { $(this).remove(); });
            return;
        }

        // Diğerlerini kapat
        $(".personel-tarih-row.expanded").each(function() {
            $(this).removeClass("expanded");
            $(this).find(".expand-chevron").css("transform", "rotate(0deg)");
            $(this).next(".personel-detail-row").remove();
        });

        $row.addClass("expanded");
        $row.find(".expand-chevron").css("transform", "rotate(90deg)");

        var detailRow = $('<tr class="personel-detail-row"><td colspan="7" class="p-0 border-0"><div class="p-3 bg-light text-center"><div class="spinner-border spinner-border-sm text-primary"></div> Detaylar yükleniyor...</div></td></tr>');
        $row.after(detailRow);

        $.post(apiUrl, { action: "sayac-personel-daily-details", personel_id: personelId, date: date }, function (res) {
            if (res.status === "success" && res.data) {
                var html = '<div class="p-3 bg-light" style="display:none;">';
                if (res.data.length === 0) {
                    html += '<p class="text-muted text-center mb-0">Bu tarihte detay bulunamadı.</p>';
                } else {
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 bg-white shadow-sm" style="border-radius:8px; overflow:hidden;">';
                    html += '<thead class="table-light"><tr><th class="ps-3">Saat</th><th class="text-center">İşlem</th><th class="text-center">Sayaç</th><th class="text-center">Seri No</th><th class="text-center">Durum</th></tr></thead><tbody>';
                    res.data.forEach(function (d) {
                        html += '<tr>';
                        html += '<td class="ps-3">' + d.tarih + '</td>';
                        html += '<td class="text-center">' + d.tip + '</td>';
                        html += '<td class="text-center">' + d.demirbas + '</td>';
                        html += '<td class="text-center"><span class="badge bg-light text-dark border">' + d.seri_no + '</span></td>';
                        html += '<td class="text-center">' + d.durum_badge + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                }
                html += '</div>';
                var $html = $(html);
                detailRow.find("td").html($html);
                $html.slideDown(200);
            } else {
                detailRow.find("td").html('<div class="p-3 bg-light text-center text-danger">Veri yüklenemedi.</div>');
            }
        }, "json").fail(function() {
            detailRow.find("td").html('<div class="p-3 bg-light text-center text-danger">Sunucu hatası.</div>');
        });
    });

    // =============================================
    // HAREKET SİLME
    // =============================================
    $(document).on("click", ".hareket-sil-btn", function () {
        var hareketId = $(this).data("id");
        Swal.fire({
            title: "Emin misiniz?",
            text: "Bu hareket kaydı silinecek!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            confirmButtonText: "Evet, Sil",
            cancelButtonText: "İptal"
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post(apiUrl, { action: "hareket-sil", hareket_id: hareketId }, function (res) {
                    if (res.status === "success") {
                        Swal.fire("Silindi", res.message, "success");
                        reloadAllTables();
                    } else {
                        Swal.fire("Hata", res.message, "error");
                    }
                }, "json");
            }
        });
    });

    // =============================================
    // TOPLU SİLME
    // =============================================
    $(document).on("click", "#btnTopluSilSayac", function () {
        var selected = getAktifSeciliIdler();
        if (selected.length === 0) {
            Swal.fire("Uyarı", "Lütfen silinecek kayıtları seçin.", "warning");
            return;
        }
        Swal.fire({
            title: "Emin misiniz?",
            text: "Seçili " + selected.length + " kayıt silinecek!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            confirmButtonText: "Evet, Sil",
            cancelButtonText: "İptal"
        }).then(function(result) {
            if (result.isConfirmed) {
                // İşlem sürerken kullanıcıyı bilgilendirelim (Loading)
                Swal.fire({
                    title: 'Lütfen Bekleyiniz...',
                    text: 'Seçili ' + selected.length + ' adet kayıt siliniyor, bu işlem biraz zaman alabilir...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                var postData = { 
                    action: "bulk-demirbas-sil", 
                    ids: JSON.stringify(selected),
                    all_filtered: (typeof isTumuSecildi !== 'undefined' && isTumuSecildi) ? 1 : 0
                };

                // Eğer "Tümünü Seç" aktifse filtreleri ve tab bilgisini de gönderelim
                if (typeof isTumuSecildi !== 'undefined' && isTumuSecildi) {
                    var params = $('#depoSayacTable').DataTable().ajax.params();
                    // params içindeki 'action' ve diğer çakışabilecekleri ayıklayalım veya postData'yı en son ezelim
                    $.extend(params, postData); 
                    postData = params;
                    // Tab bilgisini ve filtreleri koruyalım
                    postData.tab = $('#sayacTabControl .nav-link.active').data('bs-target')?.replace('#', '') || 'sayac';
                }

                $.post(apiUrl, postData, function (res) {
                    Swal.close(); // Loading kapat
                    if (res.status === "success") {
                        Swal.fire("Başarılı", res.message, "success");
                        
                        // Durumu sıfırlayalım
                        isTumuSecildi = false;
                        globalSeciliSayacIds = [];
                        
                        reloadAllTables();
                        // Seçim durumunu temizle
                        $(".sayac-select").prop("checked", false);
                        $("#selectAllSayac").prop("checked", false);
                        $("#sayacSecimInfo").remove();
                        if (typeof updateBulkActionButtons === "function") updateBulkActionButtons();
                    } else {
                        Swal.fire("Hata", res.message, "error");
                    }
                }, "json").fail(function() {
                    Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
                });
            }
        });
    });

    // =============================================
    // HAREKET TOPLU SİLME
    // =============================================
    // =============================================
    // HAREKET TOPLU SEÇİM
    // =============================================
    $(document).on("change", "#selectAllHareket", function () {
        var checked = $(this).prop("checked");
        if (checked) {
            $(".hareket-select").prop("checked", true);
            updateHareketSelectionInfo();
        } else {
            isHareketTumuSecildi = false;
            globalSeciliHareketIds = [];
            $(".hareket-select").prop("checked", false);
            updateHareketSelectionInfo();
        }
    });

    $(document).on("change", ".hareket-select", function () {
        if (isHareketTumuSecildi && !$(this).prop("checked")) {
            isHareketTumuSecildi = false;
            globalSeciliHareketIds = [];
            $("#selectAllHareket").prop("checked", false);
        }
        updateHareketSelectionInfo();
    });

    $(document).on("click", "#hareketSecimTumuFiltre", function () {
        var params = hareketTable.ajax.params();
        params.action = "get-filtered-hareket-ids";
        params.status_filter = $('input[name="hareket-status-filter"]:checked').val() || "";
        
        var $btn = $(this);
        var oldHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span> Bekleyiniz...').css("pointer-events", "none");
        
        $.post(apiUrl, params, function (res) {
            $btn.html(oldHtml).css("pointer-events", "auto");
            if (res.status === 'success') {
                globalSeciliHareketIds = res.ids;
                isHareketTumuSecildi = true;
                $(".hareket-select").prop("checked", true);
                updateHareketSelectionInfo();
            }
        }, 'json');
    });

    $(document).on("click", "#btnTopluSilHareket", function () {
        var selected = getAktifSeciliHareketIdleri();
        if (selected.length === 0) {
            Swal.fire("Uyarı", "Lütfen silinecek hareketleri seçin.", "warning");
            return;
        }
        Swal.fire({
            title: "Emin misiniz?",
            text: "Seçili " + selected.length + " hareket kaydı silinecek!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            confirmButtonText: "Evet, Sil",
            cancelButtonText: "İptal"
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Lütfen Bekleyiniz...',
                    text: 'İşlem yapılıyor...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                var postData = { 
                    action: "bulk-hareket-sil", 
                    ids: JSON.stringify(selected),
                    all_filtered: (typeof isHareketTumuSecildi !== 'undefined' && isHareketTumuSecildi) ? 1 : 0
                };

                // Eğer "Tümünü Seç" aktifse filtreleri de gönderelim ki backend tekrar bulabilsin
                if (typeof isHareketTumuSecildi !== 'undefined' && isHareketTumuSecildi) {
                    var params = hareketTable.ajax.params();
                    $.extend(params, postData);
                    postData = params;
                    postData.status_filter = $('input[name="hareket-status-filter"]:checked').val() || "";
                }

                $.post(apiUrl, postData, function (res) {
                    Swal.close();
                    if (res.status === "success") {
                        Swal.fire("Başarılı", res.message, "success");
                        
                        // ÖNEMLİ: Durumu sıfırlayalım
                        isHareketTumuSecildi = false;
                        globalSeciliHareketIds = [];
                        
                        reloadAllTables();
                        $("#btnTopluSilHareket").addClass("d-none");
                        $("#selectAllHareket").prop("checked", false);
                        updateHareketSelectionInfo(); 
                    } else {
                        Swal.fire("Hata", res.message, "error");
                    }
                }, "json");
            }
        });
    });

    // =============================================
    // YARDIMCI FONKSİYONLAR
    // =============================================
    function updateHareketSelectionInfo() {
        var $info = $("#hareketSecimInfo");
        var selectedOnPage = $(".hareket-select:checked").length;
        var totalOnPage = $(".hareket-select").length;
        
        if (isHareketTumuSecildi) {
            if ($info.length === 0) {
                $("#hareketTable_wrapper").prepend('<div id="hareketSecimInfo"></div>');
                $info = $("#hareketSecimInfo");
            }
            $info.attr("class", "selection-info-bar selection-info-bar-success")
                  .html(
                      '<div class="selection-info-actions">' +
                          '<button type="button" id="hareketSecimTemizle" class="selection-action-btn selection-action-btn-danger"><i class="bx bx-x me-1"></i> Temizle</button>' +
                      '</div>' +
                      '<div class="selection-info-status">' +
                          '<i class="bx bx-check-circle me-1"></i> Filtrelenen tüm <strong class="mx-1">' + globalSeciliHareketIds.length + '</strong> hareket kaydı seçildi' +
                      '</div>'
                  );
            return;
        }

        if (selectedOnPage > 0) {
            if ($info.length === 0) {
                $("#hareketTable_wrapper").prepend('<div id="hareketSecimInfo"></div>');
                $info = $("#hareketSecimInfo");
            } else {
                $info = $("#hareketSecimInfo");
            }

            $info.attr("class", "selection-info-bar").html(
                '<div class="selection-info-actions">' +
                    '<button type="button" id="hareketSecimTumuFiltre" class="selection-action-btn selection-action-btn-primary"><i class="bx bx-check-square me-1"></i> Tüm Filtrelenenleri Seç</button>' +
                    '<button type="button" id="hareketSecimTemizle" class="selection-action-btn selection-action-btn-danger"><i class="bx bx-x me-1"></i> Temizle</button>' +
                '</div>' +
                '<div class="selection-info-status">' +
                    '<i class="bx bx-info-circle me-1"></i> Sayfadan <strong class="mx-1">' + selectedOnPage + '</strong> / ' + totalOnPage + ' hareket kaydı seçildi' +
                '</div>'
            );
        } else {
            if ($info.length > 0) $info.remove();
        }

        if (selectedOnPage > 0 && selectedOnPage === totalOnPage) {
            $("#selectAllHareket").prop("checked", true);
        } else {
            $("#selectAllHareket").prop("checked", false);
        }

        // Hareket silme butonunu göster/gizle
        var hasHarekSecim = $(".hareket-select:checked").length > 0 || (typeof isHareketTumuSecildi !== 'undefined' && isHareketTumuSecildi);
        if (hasHarekSecim) {
            $("#btnTopluSilHareket").removeClass("d-none").fadeIn(200);
        } else {
            $("#btnTopluSilHareket").addClass("d-none").hide();
        }
    }

    $(document).on("click", "#hareketSecimTemizle", function() {
        isHareketTumuSecildi = false;
        globalSeciliHareketIds = [];
        $(".hareket-select").prop("checked", false).trigger("change");
        $("#selectAllHareket").prop("checked", false);
        updateHareketSelectionInfo();
    });

    $(document).on("draw.dt", "#hareketTable", function() {
        if (isHareketTumuSecildi) {
             $(".hareket-select").prop("checked", true);
             $("#selectAllHareket").prop("checked", true);
        }
        updateHareketSelectionInfo();
    });

    function getAktifSeciliHareketIdleri() {
        if (isHareketTumuSecildi && globalSeciliHareketIds.length > 0) {
            return globalSeciliHareketIds;
        }
        return getSelectedIds(".hareket-select");
    }

    function getSelectedIds(selector) {
        var arr = [];
        $(selector + ":checked").each(function () { arr.push($(this).val()); });
        return arr;
    }

    // reloadAllTables ve loadDepoSummary zaten yukarıda tanımlı (satır 191-244)
    // Buraya tekrar yazmıyoruz, sadece global erişim atıyoruz
    window.reloadSayacTables = reloadAllTables;


    // =============================================
    // EXCEL'E AKTAR
    // =============================================
    $(document).on("click", "#exportSayacExcel", function() {
        var activeTab = $('#sayacDepoTab .nav-link.active').attr('id');
        var exportTab = '';
        var table = null;
        var statusFilter = '';
        var viewMode = '';

        if (activeTab === 'kaski-tab') {
            exportTab = 'sayac_kaski';
            table = kaskiTarihTable;
        } else if (activeTab === 'depo-tab') {
            exportTab = 'sayac_bizim_depo';
            table = depoSayacTable;
            statusFilter = $('input[name="sayac-status-filter"]:checked').val() || "";
        } else if (activeTab === 'personel-tab') {
            exportTab = 'sayac_personel';
            table = personelTable;
        } else if (activeTab === 'hareket-tab') {
            exportTab = 'sayac_hareket';
            table = hareketTable;
            statusFilter = $('input[name="hareket-status-filter"]:checked').val() || "";
            viewMode = $('input[name="hareket-view-mode"]:checked').val() || "list";
        }

        if (!exportTab || !table) {
            Swal.fire("Uyarı", "Geçersiz sekme seçimi. Lütfen sayfayı yenileyip tekrar deneyiniz.", "warning");
            return;
        }

        var searchValue = table.search() || '';
        var colSearches = [];
        table.columns().every(function() {
            var val = this.search();
            if (val) {
                colSearches.push({ field: this.index(), value: val });
            }
        });

        var exportUrl = 'views/demirbas/export-excel.php?tab=' + exportTab;
        exportUrl += '&search=' + encodeURIComponent(searchValue);
        exportUrl += '&col_search=' + encodeURIComponent(JSON.stringify(colSearches));
        if (statusFilter) exportUrl += '&status_filter=' + encodeURIComponent(statusFilter);
        if (viewMode) exportUrl += '&view_mode=' + encodeURIComponent(viewMode);

        // UI Feedback
        Swal.fire({
            title: 'Excel Hazırlanıyor',
            text: 'Veriler işleniyor, lütfen bekleyiniz...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        var $btn = $(this);
        var oldHtml = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);

        // Trigger Download
        window.location.href = exportUrl;

        // Reset state after a short delay
        setTimeout(function() {
            $btn.html(oldHtml).prop('disabled', false);
            Swal.close();
        }, 3000);
    });

// Hurda Sayaç İade İşlemleri demirbas.js'den yönetiliyor. Duplike olmaması için silindi.
});

