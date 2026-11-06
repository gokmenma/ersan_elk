/**
 * Maliyet Raporu - Manuel Gider CRUD İşlemleri
 */
$(document).ready(function () {

    const API_URL = 'views/maliyet-raporu/api.php';

    /* ------------------------------------------------------------ */
    /*  TÜM GİDERLER (SERVER-SIDE)                                   */
    /* ------------------------------------------------------------ */
    const esc = function (v) {
        return $('<div>').text(v == null ? '' : v).html();
    };
    const tl = function (n) {
        return Number(n || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };

    const $maliyetTable = $('#maliyetDetayTable');
    if ($maliyetTable.length) {
        const renk = window.maliyetKategoriRenk || {};
        const maliyetDt = $maliyetTable.DataTable(applyLengthStateSave({
            ...getDatatableOptions(),
            processing: true,
            serverSide: true,
            responsive: false,
            ajax: {
                url: API_URL,
                type: 'POST',
                data: function (d) {
                    d.action = 'maliyet-ajax-list';
                    d.yil = $maliyetTable.data('yil');
                    d.ay = $maliyetTable.data('ay');
                    d.kategori = $maliyetTable.data('kategori');
                },
                dataSrc: function (json) {
                    $('#maliyetKayitSayisi').text(json.recordsTotal || 0);
                    $('#maliyetFiltreliToplam').text(tl(json.toplam));
                    return json.data;
                }
            },
            columns: [
                {
                    data: null, className: 'text-center fw-medium', orderable: false, searchable: false,
                    render: function (d, t, r, meta) { return meta.row + meta.settings._iDisplayStart + 1; }
                },
                { data: 'tarih' },
                {
                    data: 'kategori',
                    render: function (d) {
                        const c = renk[d] || renk['Diğer'] || { renk: '#64748b' };
                        return '<span class="badge" style="background:' + c.renk + '1a;color:' + c.renk + ';">' + esc(d) + '</span>';
                    }
                },
                { data: 'alt_kategori', render: function (d) { return esc(d); } },
                { data: 'tutar', className: 'text-end fw-medium', render: function (d) { return tl(d); } },
                {
                    data: 'kaynak_tablo',
                    render: function (d) { return '<span class="badge bg-light text-dark border" style="font-size:11px;">' + esc(d) + '</span>'; }
                },
                {
                    data: 'aciklama', className: 'text-truncate', render: function (d) {
                        return '<span title="' + esc(d) + '" style="display:inline-block;max-width:300px;overflow:hidden;text-overflow:ellipsis;">' + esc(d) + '</span>';
                    }
                }
            ],
            order: [[1, 'desc']]
        }));
    }

    /* ------------------------------------------------------------ */
    /*  YENİ KAYIT BUTONU                                            */
    /* ------------------------------------------------------------ */
    $('#manuelGiderEkle').on('click', function () {
        $('#manuelGiderForm')[0].reset();
        $('#manuel_gider_id').val('0');
        $('#manuelGiderModalLabel').text('Yeni Manuel Gider');
    });

    /* ------------------------------------------------------------ */
    /*  KAYDET                                                       */
    /* ------------------------------------------------------------ */
    $('#manuelGiderForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'manuel-gider-kaydet');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                const res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function () {
                        location.reload();
                    });
                    $('#manuelGiderModal').modal('hide');
                } else {
                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Hata', text: 'Sunucu hatası oluştu.' });
            }
        });
    });

    /* ------------------------------------------------------------ */
    /*  DÜZENLE                                                      */
    /* ------------------------------------------------------------ */
    $(document).on('click', '.manuel-gider-duzenle', function (e) {
        e.preventDefault();
        const id = $(this).data('id');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: { action: 'manuel-gider-detay', id: id },
            success: function (response) {
                const res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    $('#manuel_gider_id').val(d.enc_id);
                    $('[name="kategori"]').val(d.kategori).trigger('change');
                    $('[name="alt_kategori"]').val(d.alt_kategori);
                    $('[name="tutar"]').val(d.tutar);
                    $('[name="tarih"]').val(d.tarih);
                    if (typeof $('[name="tarih"]')[0]._flatpickr !== 'undefined') {
                        $('[name="tarih"]')[0]._flatpickr.setDate(d.tarih, true);
                    }
                    $('[name="aciklama"]').val(d.aciklama);
                    $('[name="belge_no"]').val(d.belge_no);
                    $('#manuelGiderModalLabel').text('Gider Düzenle');
                    $('#manuelGiderModal').modal('show');
                }
            }
        });
    });

    /* ------------------------------------------------------------ */
    /*  SİL                                                          */
    /* ------------------------------------------------------------ */
    $(document).on('click', '.manuel-gider-sil', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        const row = $(this).closest('tr');

        Swal.fire({
            title: 'Silmek istediğinize emin misiniz?',
            text: 'Bu işlem geri alınamaz!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    data: { action: 'manuel-gider-sil', id: id },
                    success: function (response) {
                        const res = typeof response === 'string' ? JSON.parse(response) : response;
                        if (res.status === 'success') {
                            row.fadeOut(400, function () { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                        }
                    }
                });
            }
        });
    });
});
