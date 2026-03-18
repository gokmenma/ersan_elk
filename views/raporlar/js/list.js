$(document).ready(function() {
    
    // Değişkenler
    var reportTables = {};
    var currentTableId = 1;

    // DataTable yükleme fonksiyonu
    function loadTable(rapor_turu, start_date, end_date) {
        // Preloader'ı göster
        $('#rapor-loader').fadeIn('fast');
        
        // Önce tüm tabloları gizle
        $('.table-container').hide();
        var containerId = '#tableContainer' + rapor_turu;
        var tableId = '#table' + rapor_turu;
        
        // Seçilen tablo container'ını göster
        $(containerId).show();
        
        // Merkezi ayarlardan başlangıç yap
        var options = getDatatableOptions();
        
        // Raporlara özel ayarları ekle/ez
        $.extend(true, options, {
            serverSide: true,
            processing: true,
            destroy: true,
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="mdi mdi-file-excel fs-5 me-1"></i> Excele Aktar',
                    className: 'btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center',
                    title: "Rapor_" + start_date + "_" + end_date,
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
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
            if(typeof canDeleteTableRow !== 'undefined' && canDeleteTableRow) {
                var delType = rapor_turu;
                if (rapor_turu == 2) delType = row.islem_tipi;
                return '<button type="button" class="btn btn-sm btn-soft-danger btn-delete-row" data-id="' + row.id + '" data-type="' + delType + '" data-bs-toggle="tooltip" title="Kaydı Sil"><i class="mdi mdi-delete"></i></button>';
            }
            return '-';
        };

        if (rapor_turu == 1) {
            options.columns = [
                { data: 'personel', defaultContent: '-' },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'izin_turu', defaultContent: '-' },
                { data: 'baslangic_tarihi', defaultContent: '-' },
                { data: 'bitis_tarihi', defaultContent: '-' },
                { data: 'gun_sayisi', defaultContent: '-' },
                { data: 'durum', defaultContent: '-' },
                { data: 'onaylayan', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-' },
                { data: null, orderable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 2) {
            options.columns = [
                { data: 'personel', defaultContent: '-' },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'islem_tipi', render: function(data) {
                    var color = data === 'Kesinti' ? 'danger' : 'success';
                    return '<span class="badge bg-' + color + '">' + data + '</span>';
                }},
                { data: 'tur', defaultContent: '-' },
                { data: 'detay', defaultContent: '-' },
                { data: 'tutar', defaultContent: '-' },
                { data: 'tarih', defaultContent: '-' },
                { data: 'durum', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-' },
                { data: null, orderable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 3) {
            options.columns = [
                { data: 'personel', defaultContent: '-' },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'kategori', defaultContent: '-' },
                { data: 'baslik', defaultContent: '-' },
                { data: 'tarih', defaultContent: '-' },
                { data: 'durum', defaultContent: '-' },
                { data: 'cozum_tarihi', defaultContent: '-' },
                { data: 'cozum_aciklama', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-' },
                { data: null, orderable: false, className: 'text-center', render: islemColumnRender }
            ];
        } else if (rapor_turu == 4) {
            options.columns = [
                { data: 'personel', defaultContent: '-' },
                { data: 'tc_no', defaultContent: '-' },
                { data: 'departman', defaultContent: '-' },
                { data: 'icra_dairesi', defaultContent: '-' },
                { data: 'dosya_no', defaultContent: '-' },
                { data: 'toplam_borc', defaultContent: '-' },
                { data: 'kesilen_tutar', defaultContent: '-' },
                { data: 'kalan_tutar', defaultContent: '-' },
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
                    return '<span class="badge bg-' + badgeClass + '">' + label + '</span>';
                }},
                { data: 'tarih', defaultContent: '-' },
                { data: 'aciklama', defaultContent: '-' },
                { data: null, orderable: false, className: 'text-center', render: islemColumnRender }
            ];
        }

        // Merkezi başlatma fonksiyonunu çağır
        reportTables[rapor_turu] = destroyAndInitDataTable('#table' + rapor_turu, options);
        currentTableId = rapor_turu;
    }

    // Buton tıklanmasını dinle
    $('#btnRaporGetir').on('click', function(e) {
        e.preventDefault();
        
        var rapor_turu = $('#rapor_turu').val();
        var start_date = $('#baslangic_tarihi').val();
        var end_date = $('#bitis_tarihi').val();

        if(!start_date || !end_date) {
            Swal.fire('Uyarı!', 'Lütfen tarih aralığı seçiniz.', 'warning');
            return;
        }

        loadTable(rapor_turu, start_date, end_date);
    });

    // Sayfa ilk açıldığında tabloyu yükle (isteğe bağlı)
    // loadTable(1, $('#baslangic_tarihi').val(), $('#bitis_tarihi').val());

    // Excel Dışa Aktarma Butonu (Sunucu Taraflı - Tüm Filtreli Veriler)
    $('#exportExcelBtn').on('click', function(e) {
        e.preventDefault();
        var dt = $('#table' + currentTableId).DataTable();
        
        if (!dt || !dt.data().any()) {
            Swal.fire('Uyarı!', 'Dışa aktarılacak tablo verisi bulunamadı.', 'warning');
            return;
        }

        // DataTables'ın o anki AJAX parametrelerini alalım (Arama, Filtre, Sıralama dahildir)
        var params = dt.ajax.params();
        params.action = 'export-rapor';
        
        // Bir form oluşturalım ve POST olarak gönderelim (Büyük parametre setleri için GET yerine POST güvenlidir)
        var form = $('<form>', {
            action: 'views/raporlar/api.php',
            method: 'POST',
            target: '_blank' // Yeni sekmede indir
        });
        
        // İç içe geçmiş objeleri form inputuna çeviren yardımcı fonksiyon
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

        // Butonu disabled yapalım ki çift tıklanmasın
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> İşleniyor...');

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
                    // Yeniden yükle
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

});
