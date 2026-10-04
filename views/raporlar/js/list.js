$(document).ready(function() {
    
    // Değişkenler
    var reportTables = {};
    var currentTableId = 1;
    var SUMMARY_STORAGE_KEY = 'raporlar_summary_cards_state';

    // Select2 Başlatma
    if ($.fn.select2) {
        $('#rapor_turu').select2({ width: '100%' });
    }

    // Flatpickr Başlatma (varsa)
    if (typeof flatpickr !== 'undefined') {
        $('.flatpickr').flatpickr({
            dateFormat: 'd.m.Y',
            allowInput: true,
            locale: 'tr'
        });
    }

    // Özet Kartları Açma/Kapama Standardı
    function updateToggleIcon(isHidden) {
        var $btn = $('#btnToggleSummaryCards');
        var $icon = $btn.find('i');
        if (isHidden) {
            $icon.removeClass('bx-chevron-up').addClass('bx-chevron-down');
            $btn.attr('title', 'Özet Kartları Göster').attr('aria-expanded', 'false');
        } else {
            $icon.removeClass('bx-chevron-down').addClass('bx-chevron-up');
            $btn.attr('title', 'Özet Kartları Gizle').attr('aria-expanded', 'true');
        }
    }
    updateToggleIcon(document.documentElement.classList.contains('raporlar-summary-hidden'));

    $('#btnToggleSummaryCards').on('click', function() {
        var isHidden = document.documentElement.classList.toggle('raporlar-summary-hidden');
        try {
            localStorage.setItem(SUMMARY_STORAGE_KEY, isHidden ? 'hidden' : 'visible');
        } catch (e) {}
        updateToggleIcon(isHidden);
    });

    // Sayı ve Para Formatlama Fonksiyonları
    function formatNumber(num) {
        return new Intl.NumberFormat('tr-TR').format(num || 0);
    }

    function formatMoney(num) {
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0) + ' ₺';
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null || value === '' ? '-' : value).html();
    }

    function personRender(data, type) {
        if (type !== 'display') return data;
        var name = data || '-';
        var initials = name === '-' ? '?' : name.trim().split(/\s+/).slice(0, 2).map(function(part) {
            return part.charAt(0);
        }).join('').toLocaleUpperCase('tr-TR');
        return '<div class="report-person"><span class="report-person-avatar">' + escapeHtml(initials) + '</span><span class="report-person-name">' + escapeHtml(name) + '</span></div>';
    }

    function dateRender(data, type) {
        if (type !== 'display') return data;
        return '<span class="report-date"><i class="bx bx-calendar"></i>' + escapeHtml(data) + '</span>';
    }

    function descriptionRender(data, type) {
        if (type !== 'display') return data;
        return '<span class="report-description" title="' + escapeHtml(data) + '">' + escapeHtml(data) + '</span>';
    }

    function moneyRender(data, type) {
        if (type !== 'display') return data;
        return '<span class="report-money">' + escapeHtml(data) + '</span>';
    }

    function labelRender(data, type) {
        if (type !== 'display') return data;
        return '<span class="report-badge report-badge-primary">' + escapeHtml(data) + '</span>';
    }

    function badgeRender(data, type, forcedClass, forcedIcon) {
        if (type !== 'display') return data;
        var normalized = String(data || '').toLocaleLowerCase('tr-TR');
        var badgeClass = typeof forcedClass === 'string' ? forcedClass : 'secondary';
        var icon = typeof forcedIcon === 'string' ? forcedIcon : 'bx-info-circle';
        if (/onay|tamam|bitti|aktif/.test(normalized)) { badgeClass = 'success'; icon = 'bx-check-circle'; }
        else if (/bekliyor|beklemede|devam/.test(normalized)) { badgeClass = 'warning'; icon = 'bx-time-five'; }
        else if (/red|iptal|durdur|kesinti/.test(normalized)) { badgeClass = 'danger'; icon = 'bx-x-circle'; }
        else if (/fek|talep/.test(normalized)) { badgeClass = 'info'; icon = 'bx-info-circle'; }
        return '<span class="report-badge report-badge-' + badgeClass + '"><i class="bx ' + icon + '"></i>' + escapeHtml(data) + '</span>';
    }

    // Başlık ve Açıklama Güncelleme
    var reportMetadata = {
        1: { title: 'İzin / Rapor Listesi', subtitle: 'Filtrelenen tarih aralığındaki personel izin ve istirahat kayıtları' },
        2: { title: 'Personel Kesinti / Ek Ödeme Listesi', subtitle: 'Filtrelenen tarih aralığındaki tek seferlik kesinti ve ek ödeme kayıtları' },
        3: { title: 'Personel Talepleri Listesi', subtitle: 'Filtrelenen tarih aralığındaki personel avans, izin, destek talepleri' },
        4: { title: 'Personel İcra Listesi', subtitle: 'Filtrelenen tarih aralığındaki aktif icra ve nafaka dosyaları' }
    };

    function updateReportHeader(rapor_turu) {
        var meta = reportMetadata[rapor_turu] || reportMetadata[1];
        $('#reportCardTitle').text(meta.title);
        $('#reportCardSubtitle').text(meta.subtitle);

        // Hızlı filtre butonlarında aktifliği güncelle
        $('.status-quick-filter[data-report]').removeClass('active');
        $('.status-quick-filter[data-report="' + rapor_turu + '"]').addClass('active');
    }

    // Özet KPI Kartlarını Yükleme
    function loadSummary(start_date, end_date) {
        $.ajax({
            url: 'views/raporlar/api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get-summary',
                start_date: start_date,
                end_date: end_date
            },
            success: function(res) {
                if (res.status === 'success' && res.data) {
                    var d = res.data;
                    // 1. İzinler
                    $('#stat_toplam_izin').text(formatNumber(d.izin.toplam));
                    $('#stat_sub_izin').text('Onaylı: ' + formatNumber(d.izin.onayli) + ' | Bekleyen: ' + formatNumber(d.izin.bekleyen));

                    // 2. Kesinti / Ek Ödemeler
                    $('#stat_toplam_kesinti_ek').text(formatNumber(d.kesinti_ek.toplam));
                    $('#stat_sub_kesinti_ek').text('Ek: ' + formatNumber(d.kesinti_ek.ek_adet) + ' | Kesinti: ' + formatNumber(d.kesinti_ek.kesinti_adet));

                    // 3. Talepler
                    $('#stat_toplam_talep').text(formatNumber(d.talep.toplam));
                    $('#stat_sub_talep').text('Bekleyen: ' + formatNumber(d.talep.bekleyen) + ' | Çözülen: ' + formatNumber(d.talep.cozuldu));

                    // 4. İcralar
                    $('#stat_toplam_icra').text(formatNumber(d.icra.toplam));
                    $('#stat_sub_icra').text(formatMoney(d.icra.toplam_borc) + ' Borç');
                }
            }
        });
    }

    // DataTable Yükleme Fonksiyonu
    function loadTable(rapor_turu, start_date, end_date) {
        // Preloader'ı göster
        $('#rapor-loader').fadeIn('fast');
        
        // Önce tüm tabloları gizle
        $('.table-container').hide();
        var containerId = '#tableContainer' + rapor_turu;
        var tableId = '#table' + rapor_turu;
        
        // Seçilen tablo container'ını göster
        $(containerId).show();
        updateReportHeader(rapor_turu);

        // Merkezi ayarlardan başlangıç yap
        var options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        
        // Raporlara özel ayarları ekle/ez
        $.extend(true, options, {
            serverSide: true,
            processing: true,
            destroy: true,
            language: {
                emptyTable: "Seçilen filtre kriterlerine uygun rapor verisi bulunamadı."
            },
            ajax: {
                url: 'views/raporlar/api.php',
                type: 'POST',
                data: function (d) {
                    if (d.draw === 1) $('#rapor-loader').fadeIn('fast');
                    d.action = 'get-rapor';
                    d.rapor_turu = rapor_turu;
                    d.start_date = start_date;
                    d.end_date = end_date;
                },
                dataSrc: function (json) {
                    $('#rapor-loader').fadeOut('fast');
                    if (json.status !== 'success') {
                        Swal.fire('Hata!', json.message || 'Veri getirilirken hata oluştu.', 'error');
                        return [];
                    }
                    return json.data;
                },
                error: function () {
                    $('#rapor-loader').fadeOut('fast');
                    Swal.fire('Hata!', 'Sunucuyla iletişim kurulurken bir sorun oluştu.', 'error');
                }
            }
        });

        var islemColumnRender = function(data, type, row) {
            if (typeof canDeleteTableRow !== 'undefined' && canDeleteTableRow) {
                var delType = rapor_turu;
                if (rapor_turu == 2) delType = row.islem_tipi;
                return '<div class="action-btn-group d-flex align-items-center justify-content-center gap-1">' +
                       '<button type="button" class="btn btn-subtle-danger table-action-btn btn-delete-row" data-id="' + row.id + '" data-type="' + delType + '" data-bs-toggle="tooltip" title="Kaydı Sil">' +
                       '<i class="bx bx-trash font-size-14"></i>' +
                       '</button>' +
                       '</div>';
            }
            return '-';
        };

        if (rapor_turu == 1) {
            options.columns = [
                { data: 'personel', defaultContent: '-', render: personRender },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'izin_turu', defaultContent: '-', render: labelRender },
                { data: 'baslangic_tarihi', defaultContent: '-', render: dateRender },
                { data: 'bitis_tarihi', defaultContent: '-', render: dateRender },
                { data: 'gun_sayisi', defaultContent: '-' },
                { data: 'durum', defaultContent: '-', render: badgeRender },
                { data: 'onaylayan', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-', render: descriptionRender },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 2) {
            options.columns = [
                { data: 'personel', defaultContent: '-', render: personRender },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'islem_tipi', render: function(data) {
                    return badgeRender(data, 'display', data === 'Kesinti' ? 'danger' : 'success', data === 'Kesinti' ? 'bx-minus-circle' : 'bx-plus-circle');
                }},
                { data: 'tur', defaultContent: '-', render: labelRender },
                { data: 'detay', defaultContent: '-' },
                { data: 'tutar', defaultContent: '-', render: moneyRender },
                { data: 'tarih', defaultContent: '-', render: dateRender },
                { data: 'durum', defaultContent: '-', render: badgeRender },
                { data: 'aciklama', defaultContent: '-', render: descriptionRender },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 3) {
            options.columns = [
                { data: 'personel', defaultContent: '-', render: personRender },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'kategori', defaultContent: '-', render: labelRender },
                { data: 'baslik', defaultContent: '-' },
                { data: 'tarih', defaultContent: '-', render: dateRender },
                { data: 'durum', defaultContent: '-', render: badgeRender },
                { data: 'cozum_tarihi', defaultContent: '-', render: dateRender },
                { data: 'cozum_aciklama', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-', render: descriptionRender },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 4) {
            options.columns = [
                { data: 'personel', defaultContent: '-', render: personRender },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'icra_dairesi', defaultContent: '-' },
                { data: 'dosya_no', defaultContent: '-' },
                { data: 'toplam_borc', defaultContent: '-', render: moneyRender },
                { data: 'kesilen_tutar', defaultContent: '-', render: moneyRender },
                { data: 'kalan_tutar', defaultContent: '-', render: moneyRender },
                { data: 'durum', render: function(data) {
                    var badges = {
                        'bekliyor': 'warning',
                        'devam_ediyor': 'primary',
                        'fekki_geldi': 'info',
                        'kesinti_bitti': 'success',
                        'bitti': 'success',
                        'durduruldu': 'danger'
                    };
                    var labels = {
                        'bekliyor': 'Bekliyor',
                        'devam_ediyor': 'Devam Ediyor',
                        'fekki_geldi': 'Fekki Geldi',
                        'kesinti_bitti': 'Kesinti Bitti',
                        'bitti': 'Tamamlandı',
                        'durduruldu': 'Durduruldu'
                    };
                    var badgeClass = badges[data] || 'secondary';
                    var label = labels[data] || data;
                    return badgeRender(label, 'display', badgeClass);
                }},
                { data: 'tarih', defaultContent: '-', render: dateRender },
                { data: 'aciklama', defaultContent: '-', render: descriptionRender },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: islemColumnRender }
            ];
        }

        // Merkezi başlatma fonksiyonunu çağır
        if (typeof applyLengthStateSave === 'function') {
            options = applyLengthStateSave(options);
        }
        
        if (typeof destroyAndInitDataTable === 'function') {
            reportTables[rapor_turu] = destroyAndInitDataTable(tableId, options);
        } else {
            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().destroy();
            }
            reportTables[rapor_turu] = $(tableId).DataTable(options);
        }
        currentTableId = rapor_turu;
    }

    // Buton Tıklanması: Raporu Getir
    $('#btnRaporGetir').on('click', function(e) {
        e.preventDefault();
        
        var rapor_turu = parseInt($('#rapor_turu').val(), 10) || 1;
        var start_date = $('#baslangic_tarihi').val();
        var end_date = $('#bitis_tarihi').val();

        if (!start_date || !end_date) {
            Swal.fire('Uyarı!', 'Lütfen tarih aralığı seçiniz.', 'warning');
            return;
        }

        loadSummary(start_date, end_date);
        loadTable(rapor_turu, start_date, end_date);
    });

    // KPI Kartlarındaki Hızlı Filtre Butonları Tıklaması
    $(document).on('click', '.status-quick-filter[data-report]', function(e) {
        e.preventDefault();
        var reportType = parseInt($(this).data('report'), 10) || 1;
        
        if ($('#rapor_turu').length) {
            $('#rapor_turu').val(reportType).trigger('change');
        }
        
        var start_date = $('#baslangic_tarihi').val();
        var end_date = $('#bitis_tarihi').val();
        loadTable(reportType, start_date, end_date);
    });

    // Rapor Türü Select değiştiğinde başlığı güncelle
    $('#rapor_turu').on('change', function() {
        var val = parseInt($(this).val(), 10) || 1;
        updateReportHeader(val);
    });

    // Excel Dışa Aktarma Butonu (Sunucu Taraflı - Tüm Filtreli Veriler)
    $('#exportExcelBtn').on('click', function(e) {
        e.preventDefault();
        var tableId = '#table' + currentTableId;
        var dt = $(tableId).DataTable();
        
        if (!dt || !dt.data().any()) {
            Swal.fire('Uyarı!', 'Dışa aktarılacak tablo verisi bulunamadı.', 'warning');
            return;
        }

        // DataTables'ın o anki AJAX parametrelerini alalım (Arama, Filtre, Sıralama dahildir)
        var params = dt.ajax.params();
        params.action = 'export-rapor';
        
        // Form oluşturalım ve POST olarak gönderelim
        var form = $('<form>', {
            action: 'views/raporlar/api.php',
            method: 'POST',
            target: '_blank'
        });
        
        function appendInputs(obj, prefix) {
            $.each(obj, function(k, v) {
                var name = prefix ? prefix + '[' + k + ']' : k;
                if (typeof v === 'object' && v !== null) {
                    appendInputs(v, name);
                } else {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: name,
                        value: v
                    }));
                }
            });
        }
        
        appendInputs(params);
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Yazdır Butonu
    $('#btnHeaderPrint').on('click', function(e) {
        e.preventDefault();
        var currentTable = document.querySelector('#tableContainer' + currentTableId);
        if (!currentTable) {
            window.print();
            return;
        }

        var printWindow = window.open('', '_blank');
        var reportTitle = $('#reportCardTitle').text() + ' (' + $('#baslangic_tarihi').val() + ' - ' + $('#bitis_tarihi').val() + ')';
        var tableHtml = currentTable.innerHTML;

        printWindow.document.write('<!DOCTYPE html><html><head><title>' + reportTitle + '</title>');
        printWindow.document.write('<link rel="stylesheet" href="assets/css/bootstrap.min.css">');
        printWindow.document.write('<style>body { font-family: sans-serif; padding: 20px; } table { width: 100%; border-collapse: collapse; margin-top: 15px; } th, td { border: 1px solid #dee2e6; padding: 6px 8px; font-size: 11px; text-align: left; } th { background-color: #f8f9fa !important; font-weight: bold; } th:last-child, td:last-child { display: none; } .report-person-avatar { display: none; } .report-badge { border: 1px solid #ccc; padding: 2px 4px; border-radius: 4px; font-size: 10px; }</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h4 style="margin-bottom: 5px;">' + reportTitle + '</h4>');
        printWindow.document.write('<p style="font-size: 12px; color: #666; margin-bottom: 15px;">Oluşturulma Tarihi: ' + new Date().toLocaleString('tr-TR') + '</p>');
        printWindow.document.write(tableHtml);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
        }, 500);
    });

    // Satır Silme İşlemleri
    $(document).on('click', '.btn-delete-row', function() {
        var id = $(this).data('id');
        var type = $(this).data('type');
        
        $('#deleteRowId').val(id);
        $('#deleteRowType').val(type);
        $('#deleteRowAciklama').val('');
        $('#deleteRowModal').modal('show');
    });

    $('#btnConfirmDelete').on('click', function() {
        var id = $('#deleteRowId').val();
        var type = $('#deleteRowType').val();
        var aciklama = $('#deleteRowAciklama').val();

        if (!aciklama || aciklama.trim() === '') {
            Swal.fire('Uyarı!', 'Lütfen silme nedeni giriniz.', 'warning');
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> İşleniyor...');

        $.ajax({
            url: 'views/raporlar/api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete-rapor-satir',
                id: id,
                type: type,
                aciklama: aciklama
            },
            success: function(res) {
                if (res.status === 'success') {
                    $('#deleteRowModal').modal('hide');
                    Swal.fire('Başarılı!', res.message, 'success');
                    $('#btnRaporGetir').click();
                } else {
                    Swal.fire('Hata!', res.message || 'Silme işlemi başarısız', 'error');
                }
            },
            error: function() {
                Swal.fire('Hata!', 'Sunucu bağlantı hatası.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Sayfa ilk açıldığında özetleri ve ilk rapor tablosunu yükle
    var initialStart = $('#baslangic_tarihi').val();
    var initialEnd = $('#bitis_tarihi').val();
    var initialType = parseInt($('#rapor_turu').val(), 10) || 1;

    loadSummary(initialStart, initialEnd);
    loadTable(initialType, initialStart, initialEnd);

});
