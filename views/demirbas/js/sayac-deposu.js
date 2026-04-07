$(function () {
    var apiUrl = "views/demirbas/api.php";

    // Güvenli DataTable başlatma
    function safeInitTable(selector, customOptions) {
        if ($.fn.DataTable.isDataTable(selector)) {
            return $(selector).DataTable();
        }
        var defaults = getDatatableOptions();
        var defInit = defaults.initComplete;
        var custInit = customOptions.initComplete;
        customOptions.initComplete = function(settings, json) {
            if (typeof defInit === "function") defInit.call(this, settings, json);
            if (typeof custInit === "function") custInit.call(this, settings, json);
        };
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
            { data: "tarih" },
            { data: "islem_tipi" },
            { data: "yon", className: "text-center" },
            { data: "adet", className: "text-center" },
        ],
        order: [[0, "desc"]],
        pageLength: 25,
        initComplete: function () { $("#personel-loader").fadeOut(300); }
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
    var hareketTable = safeInitTable("#hareketTable", {
        serverSide: true,
        ajax: {
            url: apiUrl,
            type: "POST",
            data: function (d) {
                d.action = "sayac-depo-hareketleri";
                d.status_filter = $('input[name="hareket-status-filter"]:checked').val() || "";
            },
        },
        columns: [
            { data: "id", className: "text-center" },
            { data: "hareket_tipi" },
            { data: "demirbas_adi" },
            { data: "seri_no" },
            { data: "lokasyon_personel" },
            { data: "tarih", className: "text-center" },
            { data: "islem", className: "text-center", orderable: false, searchable: false },
        ],
        order: [[0, "desc"]],
        pageLength: 25,
    });

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
    // TAB BUTON GÖRÜNÜRLÜKLERİ
    // =============================================
    // =============================================
    // TAB BUTON GÖRÜNÜRLÜKLERİ & URL PERSISTENCE
    // =============================================
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("id");
        updateButtonVisibility(target);
        
        // URL Hash güncelle
        var tabPaneId = $(e.target).data("bs-target").replace("#", "");
        window.location.hash = tabPaneId;

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
        // Tüm butonları gizle
        $("#btnSayacEkle, #btnPersoneleZimmetle, #btnSayacKaskiyeTeslim").addClass("d-none");
        // Sadece Depo sekmesinde tüm butonlar görünsün
        if (activeTabId === "depo-tab") {
            $("#btnSayacEkle, #btnPersoneleZimmetle, #btnSayacKaskiyeTeslim").removeClass("d-none");
        }
    }

    // =============================================
    // FİLTRELER
    // =============================================
    $('input[name="sayac-status-filter"]').on('change', function () {
        depoSayacTable.ajax.reload();
    });
    $('input[name="hareket-status-filter"]').on('change', function () {
        hareketTable.ajax.reload();
    });

    var globalSeciliSayacIds = [];
    var isTumuSecildi = false;

    // =============================================
    // SATIRA TIKLA = SEÇ (Bizim Depo tablosu)
    // =============================================
    $(document).on("click", "#depoSayacTable tbody tr", function (e) {
        // label ve checkbox-container tıklamasını hariç tut (çift toggle önleme)
        if ($(e.target).closest("input, button, a, label, .dropdown-menu, .custom-checkbox-container").length) return;
        var $cb = $(this).find(".sayac-select");
        if ($cb.length) {
            $cb.prop("checked", !$cb.prop("checked")).trigger("change");
        }
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
        var total = $(".sayac-select").length;

        if (isTumuSecildi) {
             if (!$("#sayacSecimInfo").length) {
                 $("#depoSayacTable_wrapper").prepend('<div id="sayacSecimInfo"></div>');
             }
             $("#sayacSecimInfo").attr("class", "alert alert-success py-2 px-3 mb-2 d-flex align-items-center justify-content-between shadow-sm border-0").css({"border-radius":"8px"})
                 .html('<span><i class="bx bx-check-double me-1"></i> Filtrelenen tüm <strong class="text-success">' + globalSeciliSayacIds.length + '</strong> kayıt seçildi.</span><span><a href="javascript:void(0);" id="secimTemizle" class="text-danger fw-bold text-decoration-none">Temizle</a></span>');
             return;
        }

        if (count > 0) {
            if (!$("#sayacSecimInfo").length) {
                var html = '<div id="sayacSecimInfo" class="alert alert-info py-2 px-3 mb-2 d-flex align-items-center justify-content-between shadow-sm border-0" style="background: #e0f2fe; color: #0369a1; border-radius: 8px;">';
                html += '<span><i class="bx bx-check-circle me-1"></i> Sayfadan <strong id="secimAdet">' + count + '</strong> / ' + total + ' kayıt seçildi</span>';
                html += '<span>';
                html += '<a href="javascript:void(0);" id="secimTumuFiltre" class="text-primary fw-bold me-3 text-decoration-none">Tüm Filtrelenenleri Seç</a>';
                html += '<a href="javascript:void(0);" id="secimTemizle" class="text-danger fw-bold text-decoration-none">Temizle</a>';
                html += '</span></div>';
                $("#depoSayacTable_wrapper").prepend(html);
            } else {
                $("#secimAdet").text(count);
                if ($("#secimTumuFiltre").length === 0 && !isTumuSecildi) {
                    $("#sayacSecimInfo").remove();
                    updateSelectionInfo();
                }
            }
        } else {
            $("#sayacSecimInfo").remove();
        }
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

    // Kaskiye teslim form submit
    $(document).on("submit", "#kasiyeTeslimForm", function (e) {
        e.preventDefault();
        var isToplu = $("#kasiye_is_toplu").val() === "1";
        
        var postData = {
            action: isToplu ? "toplu-kasiye-teslim" : "kasiye-teslim",
            tarih: $("#kasiye_tarih").val(),
            aciklama: $("#kasiye_aciklama").val(),
            teslim_eden: $("#kasiye_teslim_eden").val()
        };

        if (isToplu) {
            postData.ids = JSON.stringify($("#kasiye_toplu_ids").val().split(','));
        } else {
            postData.demirbas_id = $("#kasiye_demirbas_id").val();
        }

        var $btn = $(this).find('button[type="submit"]');
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor...');

        $.post(apiUrl, postData, function (res) {
            $btn.prop("disabled", false).html('<i class="bx bx-check me-1"></i>Evet, Teslim Et');
            if (res.status === "success") {
                var modalEl = document.getElementById("kasiyeTeslimModal");
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                Swal.fire("Başarılı", res.message, "success");
                reloadAllTables();
            } else {
                Swal.fire("Hata", res.message, "error");
            }
        }, "json").fail(function() {
            $btn.prop("disabled", false).html('<i class="bx bx-check me-1"></i>Evet, Teslim Et');
            Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
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
        var selected = getSelectedIds(".sayac-select");
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
                $.post(apiUrl, { action: "bulk-demirbas-sil", ids: selected }, function (res) {
                    if (res.status === "success") {
                        Swal.fire("Başarılı", res.message, "success");
                        reloadAllTables();
                        $("#sayacSecimInfo").remove();
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
    function getSelectedIds(selector) {
        var arr = [];
        $(selector + ":checked").each(function () { arr.push($(this).val()); });
        return arr;
    }

    function loadDepoSummary() {
        $.post(apiUrl, { action: "sayac-global-summary" }, function (res) {
            if (res.status === "success") {
                $("#sayacCardToplamGiren, #kaskiCardToplamGiren").text(res.toplam_alinan ?? 0);
                $("#sayacCardDepoKalan, #kaskiCardDepoKalan").text(res.yeni_depoda ?? 0);
                $("#sayacCardPersonelZimmetli, #kaskiCardPersonelZimmetli").text(res.yeni_personelde ?? 0);
                $("#sayacCardKaskiyeTeslim, #kaskiCardHurdaKaskiye").text(res.hurda_kaskiye ?? 0);
                $("#sayacCardHurda, #kaskiCardHurda").text(res.hurda_depoda ?? 0);
                $("#sayacCardPersonelHurda, #kaskiCardPersonelHurda").text(res.hurda_personelde ?? 0);
            }
        }, "json");
    }

    function reloadAllTables() {
        try { kaskiTarihTable.ajax.reload(null, false); } catch(e) {}
        try { depoSayacTable.ajax.reload(null, false); } catch(e) {}
        try { personelTable.ajax.reload(null, false); } catch(e) {}
        try { hareketTable.ajax.reload(null, false); } catch(e) {}
        loadPersonelAllSummary();
        loadDepoSummary();
    }
    window.reloadSayacTables = reloadAllTables;

    // Tüm kaydedme olaylarından sonra tabloları yenile
    $(document).on("demirbas-saved zimmet-saved iade-saved kaskiye-teslim-saved hurda-iade-saved", function() {
        reloadAllTables();
    });
});
