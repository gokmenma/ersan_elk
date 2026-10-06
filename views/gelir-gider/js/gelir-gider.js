/**
 * Gelir-Gider Yönetimi Scripti (Gelişmiş Filtreler + Sıralama + Sütun Yönetimi + Toplu Silme)
 */
var url = window.gelirGiderApiUrl || "views/gelir-gider/api.php";
var gelirGiderTable = window.gelirGiderTable || null;
var currentTipFilter = window.currentTipFilter || '';

$(document).ready(function() {
    // 1. Özet Kartları Açma/Kapama Standardı (AGENTS.md)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('gelir-gider-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('gelir-gider-summary-hidden');
        $('html').toggleClass('gelir-gider-summary-hidden', willHide);
        localStorage.setItem('gelir_gider_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    // 2. DataTables Başlatma
    initGelirGiderTable();

    // 3. Flatpickr Başlatma
    initFlatpickr();

    // 4. Modal Select2 Alanlarını Başlatma
    initModalSelect2Fields();

    // 5. Yıl ve Ay filtreleri değiştiğinde tabloyu yenile
    $('#filterYil, #filterAy').on('change', function() {
        reloadGelirGiderTable();
    });

    // 6. Özet Kartlara Tıklayınca İlgili Türü Filtreleme (AGENTS.md)
    $('.summary-kpi-card[data-tip], .status-quick-filter').on('click', function(e) {
        const tipVal = $(this).attr('data-tip') !== undefined ? $(this).attr('data-tip') : $(this).data('tip');
        if (tipVal === undefined) return;

        currentTipFilter = tipVal;

        // Kart ve buton aktifliklerini güncelle
        $('.summary-kpi-card').removeClass('active');
        $('.status-quick-filter').removeClass('active');

        $(`.summary-kpi-card[data-tip="${tipVal}"]`).addClass('active');
        $(`.status-quick-filter[data-tip="${tipVal}"]`).addClass('active');

        reloadGelirGiderTable();
    });

    // 7. Checkbox ve Toplu Silme Yönetimi
    $(document).on('change', '#checkAll', function() {
        const isChecked = $(this).is(':checked');
        $('.row-check').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    $(document).on('change', '.row-check', function() {
        const total = $('.row-check').length;
        const checked = $('.row-check:checked').length;
        $('#checkAll').prop('checked', total > 0 && total === checked);
        updateBulkDeleteButton();
    });

    $('#btnBulkDelete').on('click', function() {
        const selectedIds = [];
        $('.row-check:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen silinecek en az bir kayıt seçin.' });
            return;
        }

        Swal.fire({
            title: 'Toplu Silme Onayı',
            text: `Seçilen ${selectedIds.length} adet işlem silinecektir. Bu işlem geri alınamaz!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil!',
            cancelButtonText: 'İptal',
            customClass: {
                confirmButton: 'btn btn-danger me-2',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        action: 'gelir-gider-toplu-sil',
                        ids: selectedIds
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: res.message,
                                customClass: { confirmButton: 'btn btn-primary' },
                                buttonsStyling: false
                            });
                            $('#checkAll').prop('checked', false);
                            updateBulkDeleteButton();
                            reloadGelirGiderTable();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: res.message,
                                customClass: { confirmButton: 'btn btn-primary' },
                                buttonsStyling: false
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: 'Sunucu ile iletişim kurulurken bir hata oluştu.',
                            customClass: { confirmButton: 'btn btn-primary' },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    });

    // 8. Excel Dışa Aktarma Butonları
    $('#btnHeaderExportExcel, #btnDropdownExportExcel, #exportExcel').on('click', function() {
        const yil = $('#filterYil').val() || '';
        const ay = $('#filterAy').val() || '';
        const search = (gelirGiderTable) ? gelirGiderTable.search() : '';
        const exportUrl = `views/gelir-gider/export-excel.php?yil=${encodeURIComponent(yil)}&ay=${encodeURIComponent(ay)}&tip=${encodeURIComponent(currentTipFilter)}&search=${encodeURIComponent(search)}`;
        window.open(exportUrl, '_blank');
    });

    // 9. Tabloyu Yazdır Butonu
    $('#btnHeaderPrint').on('click', function() {
        window.print();
    });

    // 10. Satır Tıklama (Checkbox ve İşlem sütunu hariç düzenleme modalını aç)
    $('#gelirGiderTable tbody').on('click', 'tr td:not(:first-child):not(:last-child)', function (e) {
        if ($(e.target).closest('a, button, .dropdown, .table-action-btn, input, .form-check').length > 0) return;
        const $row = $(this).closest('tr');
        const encId = $row.data('id') || $row.find('.duzenle').data('id');
        if (encId) {
            editGelirGider(encId);
        }
    });
});

function updateBulkDeleteButton() {
    const checkedCount = $('.row-check:checked').length;
    const $btn = $('#btnBulkDelete');
    if (checkedCount > 0) {
        $('#bulkDeleteText').text(`Seçilenleri Sil (${checkedCount})`);
        $btn.removeClass('d-none');
    } else {
        $btn.addClass('d-none');
    }
}

function initFlatpickr() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".flatpickr", {
            dateFormat: "d.m.Y H:i",
            enableTime: true,
            time_24hr: true,
            allowInput: true,
            minuteIncrement: 1
        });
    }
}

function initModalSelect2Fields() {
    ['#hesap_adi', '#islem_turu', '#plaka', '#odeme_sekli', '#banka_adi'].forEach(function(sel) {
        const $el = $(sel);
        if (!$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.select2({
            tags: sel !== '#odeme_sekli',
            placeholder: $el.data('placeholder') || 'Seçiniz',
            allowClear: true,
            dropdownParent: $('#gelirGiderModal'),
            language: 'tr',
            width: '100%'
        });
    });
}

function initGelirGiderTable() {
    const tableId = "#gelirGiderTable";
    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    const defaultOpts = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};

    gelirGiderTable = $(tableId).DataTable(applyLengthStateSave({
        ...defaultOpts,
        ordering: true,
        colReorder: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: url,
            type: "POST",
            data: function(d) {
                d.action = "gelir-gider-ajax-list";
                d.yil = $('#filterYil').val() || '';
                d.ay = $('#filterAy').val() || '';
                d.tip = currentTipFilter;
            },
            dataSrc: function(json) {
                if (json && json.summary) {
                    updateSummaryCards(json.summary);
                }
                setTimeout(() => {
                    updateBulkDeleteButton();
                    $('#checkAll').prop('checked', false);
                }, 50);
                return json.data || [];
            }
        },
        columns: [
            { data: 'check', className: "text-center", width: "35px", orderable: false, searchable: false },
            { data: 'id', className: "text-center", width: "50px" },
            { data: 'kayit_tarihi', className: "text-center", width: "125px" },
            { data: 'type', className: "text-center", width: "90px" },
            { data: 'hesap_adi' },
            { data: 'kategori_adi' },
            { data: 'plaka', className: "text-center", width: "110px" },
            { data: 'odeme_sekli', className: "text-center", width: "120px" },
            { data: 'banka_adi', className: "text-center", width: "120px" },
            { data: 'tarih', className: "text-center", width: "125px" },
            { data: 'tutar', className: "text-end", width: "120px" },
            { data: 'bakiye', className: "text-end", width: "120px" },
            { data: 'aciklama' },
            { data: 'actions', className: "text-center", width: "85px", orderable: false, searchable: false }
        ],
        createdRow: function(row, data, dataIndex) {
            $(row).find('td:not(:first-child):not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[2, "desc"]],
        initComplete: function(settings, json) {
            const api = this.api();
            
            // Gelişmiş filtreleri ve sıralama ikonlarını başlat
            if (typeof initAdvancedFilters === 'function') {
                initAdvancedFilters(api, settings);
            }
            
            setupColumnManager();
            setupDraggableHeaders();
        }
    }));
}

function reloadGelirGiderTable() {
    if (gelirGiderTable) {
        gelirGiderTable.ajax.reload(null, false);
    } else {
        initGelirGiderTable();
    }
}

// -------------------------------------------------------------
// Sütun Yönetimi (Column Manager)
// -------------------------------------------------------------
function setupColumnManager() {
    const $container = $('#columnListContainer');
    if (!$container.length || !gelirGiderTable) return;
    $container.empty();

    gelirGiderTable.columns().every(function(index) {
        const col = this;
        const headerText = $(col.header()).text().trim();

        // Checkbox (index 0) ve İşlem (index 13) sütunları gizlenemez
        if (!headerText || headerText === '#' || headerText === '' || index === 0 || index === 13) return;

        const isVisible = col.visible();
        const itemId = `col_toggle_${index}`;

        const $item = $(`
            <div class="dropdown-item py-1.5 px-2 d-flex align-items-center justify-content-between rounded-2 font-size-12">
                <span class="text-dark fw-medium">${headerText}</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input col-toggle-switch cursor-pointer" type="checkbox" id="${itemId}" data-column="${index}" ${isVisible ? 'checked' : ''}>
                </div>
            </div>
        `);
        $container.append($item);
    });

    $('#columnListDropdown').off('click').on('click', function(e) {
        e.stopPropagation();
    });

    $('.col-toggle-switch').off('change').on('change', function() {
        const colIdx = parseInt($(this).data('column'), 10);
        const isChecked = $(this).is(':checked');
        gelirGiderTable.column(colIdx).visible(isChecked, true);
        gelirGiderTable.columns.adjust().draw(false);
        setupDraggableHeaders();
    });

    $('#btnResetColumns').off('click').on('click', function() {
        gelirGiderTable.columns().every(function() {
            this.visible(true, false);
        });
        if (gelirGiderTable.colReorder) {
            gelirGiderTable.colReorder.reset();
        }
        gelirGiderTable.columns.adjust().draw(false);
        setupColumnManager();
        setupDraggableHeaders();
    });
}

// -------------------------------------------------------------
// Drag to Hide Dropzone
// -------------------------------------------------------------
function setupDraggableHeaders() {
    const $table = $('#gelirGiderTable');
    const $thead = $table.find('thead');
    const $dropzone = $('#dtDropzoneOverlay');

    $thead.find('tr:first th').each(function() {
        const $th = $(this);
        const title = $th.clone().find('.dt-filter-mode-trigger, .dt-filter-input, svg, i, .form-check, input').remove().end().text().trim();

        // Sistem sütunları (Checkbox, Sıra, İşlemler) sürüklenebilir değildir
        if (!title || title === '#' || title === 'SIRA' || title === 'İŞLEMLER' || $th.find('#checkAll').length) {
            $th.removeClass('dt-draggable-header');
            $th.off('.dtColHide');
            return;
        }

        $th.addClass('dt-draggable-header');

        $th.off('mousedown.dtColHide').on('mousedown.dtColHide', function(e) {
            if ($(e.target).closest('.dt-filter-mode-trigger, .dt-filter-control, .dt-filter-input, .select2, input, button, a').length) {
                return;
            }

            const thElement = this;
            const thTitle = title;
            const theadOffset = $thead.offset();
            const theadBottom = theadOffset.top + $thead.outerHeight();
            let isBelowHeader = false;

            function onDocMouseMove(moveEvent) {
                if (moveEvent.pageY > theadBottom + 25) {
                    isBelowHeader = true;
                    $dropzone.addClass('active');
                } else {
                    isBelowHeader = false;
                    $dropzone.removeClass('active');
                }
            }

            function onDocMouseUp() {
                $(document).off('mousemove.dtColHide', onDocMouseMove);
                $(document).off('mouseup.dtColHide', onDocMouseUp);

                $dropzone.removeClass('active');

                if (isBelowHeader) {
                    setTimeout(function() {
                        $('.dt-colreorder-drag, .dt-colreorder-moving, .dt-colreorder-floating, .dt-colreorder-insert, .DTCR_clonedTable').remove();
                    }, 10);

                    const targetCol = gelirGiderTable.column(thElement);
                    if (targetCol && targetCol.length) {
                        targetCol.visible(false, true);
                        gelirGiderTable.columns.adjust().draw(false);
                        setupColumnManager();
                        setupDraggableHeaders();

                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'info',
                                title: `"${thTitle || 'Sütun'}" gizlendi`,
                                html: '<span class="text-secondary small">Geri getirmek için Sütunlar menüsünü kullanabilirsiniz.</span>',
                                showConfirmButton: false,
                                timer: 2800,
                                timerProgressBar: true
                            });
                        }
                    }
                }
            }

            $(document).on('mousemove.dtColHide', onDocMouseMove);
            $(document).on('mouseup.dtColHide', onDocMouseUp);
        });
    });
}

function updateSummaryCards(summary) {
    if (!summary) return;
    
    const format = (val) => {
        return '₺' + parseFloat(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const toplamIslem = parseInt(summary.toplam_islem || 0, 10);
    const gelirAdet = parseInt(summary.gelir_adet || 0, 10);
    const giderAdet = parseInt(summary.gider_adet || 0, 10);
    const toplamGelir = parseFloat(summary.toplam_gelir || 0);
    const toplamGider = parseFloat(summary.toplam_gider || 0);
    const bakiye = parseFloat(summary.bakiye || 0);

    // 1. Kart: Toplam İşlem
    $("#stat_toplam_islem").text(toplamIslem);
    $("#stat_sub_gelir_gider").text(`Gelir: ${gelirAdet} | Gider: ${giderAdet}`);

    // 2. Kart: Toplam Gelir
    $("#card_toplam_gelir").text(format(toplamGelir));
    $("#stat_sub_gelir_adet").text(`${gelirAdet} Gelir Kaydı`);

    // 3. Kart: Toplam Gider
    $("#card_toplam_gider").text(format(toplamGider));
    $("#stat_sub_gider_adet").text(`${giderAdet} Gider Kaydı`);

    // 4. Kart: Net Bakiye
    const $bakiyeEl = $("#card_net_bakiye");
    $bakiyeEl.removeClass('text-danger text-success text-dark');
    if (bakiye < 0) {
        $bakiyeEl.addClass('text-danger');
    } else if (bakiye > 0) {
        $bakiyeEl.addClass('text-success');
    }
    $bakiyeEl.text(format(bakiye));

    let bakiyeBadgeText = 'Dengede';
    let bakiyeBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    if (bakiye < 0) {
        bakiyeBadgeText = 'Borç / Açık';
        bakiyeBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
    } else if (bakiye > 0) {
        bakiyeBadgeText = 'Kasa Fazlası';
        bakiyeBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
    }

    $('#bakiye_bilgi')
        .attr('class', `badge ${bakiyeBadgeClass} rounded-pill px-2 py-1 font-size-11 fw-semibold`)
        .text(bakiyeBadgeText);
}

// Modal dinamik seçeneklerini yükle
function loadModalOptions() {
    // Hesap Adları
    $.ajax({
        url: url,
        type: 'POST',
        data: { action: 'hesap-adlari-getir' },
        dataType: 'json',
        success: function(response) {
            let currentVal = $("#hesap_adi").val();
            $("#hesap_adi").empty().append('<option value=""></option>');
            if (Array.isArray(response)) {
                response.forEach(function(item) {
                    if (item && item !== '0' && item !== '') {
                        $("#hesap_adi").append(new Option(item, item));
                    }
                });
            }
            if (currentVal) $("#hesap_adi").val(currentVal).trigger('change.select2');
        }
    });

    // Plakalar
    $.ajax({
        url: url,
        type: 'POST',
        data: { action: 'plakalari-getir' },
        dataType: 'json',
        success: function(response) {
            let currentVal = $("#plaka").val();
            $("#plaka").empty().append('<option value=""></option>');
            if (Array.isArray(response)) {
                response.forEach(function(item) {
                    if (item) {
                        $("#plaka").append(new Option(item, item));
                    }
                });
            }
            if (currentVal) $("#plaka").val(currentVal).trigger('change.select2');
        }
    });

    // Bankalar
    $.ajax({
        url: url,
        type: 'POST',
        data: { action: 'bankalari-getir' },
        dataType: 'json',
        success: function(response) {
            let currentVal = $("#banka_adi").val();
            $("#banka_adi").empty().append('<option value=""></option>');
            if (Array.isArray(response)) {
                response.forEach(function(item) {
                    if (item) {
                        $("#banka_adi").append(new Option(item, item));
                    }
                });
            }
            if (currentVal) $("#banka_adi").val(currentVal).trigger('change.select2');
        }
    });
}

// İşlem türüne göre kategorileri getir
$(document).on('change', '.form-selectgroup-input', function() {
    const type = $(this).val();
    fetchCategories(type);
});

function fetchCategories(type, selectedValue = null) {
    const formData = new FormData();
    formData.append("action", "gelir-gider-turu-getir");
    formData.append("type", type);

    return fetch(url, {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        let options = '<option value=""></option>';
        if (Array.isArray(data)) {
            data.forEach(item => {
                options += `<option value="${item.id}">${item.tur_adi}</option>`;
            });
        } else {
            options = data;
        }
        $("#islem_turu").html(options).trigger("change.select2");
        
        if (selectedValue) {
            $("#islem_turu").val(selectedValue).trigger("change.select2");
        }
    });
}

// Yeni İşlem Butonu Modal Temizleme
$(document).on('click', '#gelirGiderEkle', function () {
    resetGelirGiderForm();
    initModalSelect2Fields();
    loadModalOptions();
    // Varsayılan olarak Gider (2) seçili gelsin
    $('.form-selectgroup-input[value="2"]').prop('checked', true);
    fetchCategories(2);
});

// Modaldaki Temizle Butonu
$(document).on("click", "#yeniIslemModal", function () {
    resetGelirGiderForm();
    const currentType = $('.form-selectgroup-input:checked').val() || 2;
    fetchCategories(currentType);
});

function resetGelirGiderForm() {
    $("#gelir_gider_id").val(0);
    $("#gelirGiderForm")[0].reset();
    $("#islem_turu").val("").trigger("change.select2");
    $("#hesap_adi").val("").trigger("change.select2");
    $("#plaka").val("").trigger("change.select2");
    $("#odeme_sekli").val("").trigger("change.select2");
    $("#banka_adi").val("").trigger("change.select2");
    $("#tutar").val("");
    $("#aciklama").val("");
}

// Form Kaydet
$(document).on("click", "#gelirGiderKaydet", function () {
    const form = $("#gelirGiderForm");
    const gelir_gider_id = $("#gelir_gider_id").val() || 0;
    
    form.validate({
        rules: {
            islem_turu: { required: true },
            tutar: { required: true },
            islem_tarihi: { required: true }
        },
        messages: {
            islem_turu: { required: "Lütfen bir kategori seçiniz" },
            tutar: { required: "Tutar alanı boş bırakılamaz" },
            islem_tarihi: { required: "İşlem tarihi boş bırakılamaz" }
        },
        errorElement: "span",
        highlight: function (element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function (element) {
            $(element).removeClass("is-invalid");
        }
    });

    if (!form.valid()) {
        return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...');

    const formData = new FormData(form[0]);
    formData.append("action", "gelir-gider-kaydet");
    formData.append("gelir_gider_id", gelir_gider_id);

    fetch(url, {
        method: "POST",
        body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
        $btn.prop("disabled", false).html('<i class="bx bx-save font-size-16"></i> Kaydet');
        const isSuccess = data.status === "success";

        Swal.fire({
            title: isSuccess ? "Başarılı" : "Hata",
            text: data.message,
            icon: isSuccess ? "success" : "error",
            confirmButtonText: "Tamam",
            customClass: { confirmButton: 'btn btn-primary' },
            buttonsStyling: false
        }).then(() => {
            if (isSuccess) {
                $("#gelirGiderModal").modal("hide");
                reloadGelirGiderTable();
            }
        });
    })
    .catch(() => {
        $btn.prop("disabled", false).html('<i class="bx bx-save font-size-16"></i> Kaydet');
        Swal.fire({
            title: "Hata",
            text: "Sunucu ile iletişim kurulurken bir hata oluştu.",
            icon: "error"
        });
    });
});

function formatDateDMYHI(dateStr) {
    if (!dateStr) return "";
    const dateObj = new Date(dateStr.replace(/-/g, '/'));
    const pad = (n) => n < 10 ? '0' + n : n;
    return (
        pad(dateObj.getDate()) + '.' +
        pad(dateObj.getMonth() + 1) + '.' +
        dateObj.getFullYear() + ' ' +
        pad(dateObj.getHours()) + ':' +
        pad(dateObj.getMinutes())
    );
}

// Gelir-Gider Düzenleme Fonksiyonu
function editGelirGider(gelir_gider_id) {
    $("#gelir_gider_id").val(gelir_gider_id);
    initModalSelect2Fields();
    loadModalOptions();

    const formData = new FormData();
    formData.append("action", "gelir-gider-getir");
    formData.append("gelir_gider_id", gelir_gider_id);

    fetch(url, {
        method: "POST",
        body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
        if (!data) return;

        $(`.form-selectgroup-input[value="${data.type}"]`).prop("checked", true);
        
        fetchCategories(data.type, data.kategori).then(() => {
            $("#gelirGiderModal").modal("show");

            $("#tutar").val(data.tutar);
            $("#aciklama").val(data.aciklama);
            
            if (data.hesap_adi) {
                if ($("#hesap_adi option[value='" + data.hesap_adi + "']").length === 0) {
                    $("#hesap_adi").append(new Option(data.hesap_adi, data.hesap_adi, true, true));
                }
                $("#hesap_adi").val(data.hesap_adi).trigger("change.select2");
            }
            
            if (data.plaka) {
                if ($("#plaka option[value='" + data.plaka + "']").length === 0) {
                    $("#plaka").append(new Option(data.plaka, data.plaka, true, true));
                }
                $("#plaka").val(data.plaka).trigger("change.select2");
            }

            if (data.odeme_sekli) {
                $("#odeme_sekli").val(data.odeme_sekli).trigger("change.select2");
            }

            if (data.banka_adi) {
                if ($("#banka_adi option[value='" + data.banka_adi + "']").length === 0) {
                    $("#banka_adi").append(new Option(data.banka_adi, data.banka_adi, true, true));
                }
                $("#banka_adi").val(data.banka_adi).trigger("change.select2");
            }

            if (data.tarih) {
                $("#islem_tarihi").val(formatDateDMYHI(data.tarih));
            }
        });
    });
}

// Tablodaki Düzenle Butonu
$(document).on("click", ".duzenle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const gelir_gider_id = $(this).data("id");
    editGelirGider(gelir_gider_id);
});

// Gelir-gider Sil
$(document).on("click", ".gelir-gider-sil", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const gelir_gider_id = $(this).data("id");
    const buttonElement = $(this);

    const formData = new FormData();
    formData.append("action", "gelir-gider-sil");
    formData.append("gelir_gider_id", gelir_gider_id);

    confirmAndDelete(url, formData, buttonElement, "gelirGiderTable");
});

// Excel'den Yükle
$(document).on('click', '#btnUploadExcel', function() {
    const fileInput = $('#excelFile');
    if (!fileInput.val()) {
        Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen bir Excel dosyası seçin.' });
        return;
    }

    const formData = new FormData($('#importExcelForm')[0]);
    formData.append("action", "gelir-gider-excel-kaydet");

    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Yükleniyor...');

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const data = typeof response === 'string' ? JSON.parse(response) : response;
            if (data.status === 'success') {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Başarılı', 
                    text: data.message,
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                $('#importExcelModal').modal('hide');
                $('#importExcelForm').trigger('reset');
                reloadGelirGiderTable();
            } else {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Hata', 
                    text: data.message,
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false 
                });
            }
        },
        error: function() {
            Swal.fire({ 
                icon: 'error', 
                title: 'Hata', 
                text: 'Sunucu ile iletişim kurulamadı.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="bx bx-upload font-size-16"></i> Yükle');
        }
    });
});
