$(document).ready(function () {
    const API = 'views/site-yonetimi/api.php';
    let durumFiltre = 'all';

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const durumRozet = {
        taslak: '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Taslak</span>',
        yayinda: '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Yayında</span>'
    };

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('siteproje-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('siteproje-summary-hidden');
        $('html').toggleClass('siteproje-summary-hidden', gizle);
        try { localStorage.setItem('siteproje_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    $('#siteprojeModal .select2').select2({ dropdownParent: $('#siteprojeModal'), width: '100%' });

    const tablo = $('#siteprojeTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'proje-ajax-list';
                d.durum_filtre = durumFiltre;
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                $('#stat_toplam').text(s.toplam || 0);
                $('#stat_sub_toplam').text(`Taslak: ${s.taslak || 0}`);
                $('#stat_yayinda').text(s.yayinda || 0);
                $('#stat_taslak').text(s.taslak || 0);
                $('#stat_gorselsiz').text(s.gorselsiz || 0);
                return json.data;
            }
        },
        columns: [
            { data: null, className: 'text-center', width: '50px', orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
            {
                data: 'baslik',
                render: (d, t, r) => `<div class="d-flex align-items-center gap-2">${r.gorsel ? `<img class="spm-thumb" src="${esc(r.gorsel)}" alt="">` : '<span class="spm-thumb d-inline-flex align-items-center justify-content-center text-muted"><i class="bx bx-image"></i></span>'}<span class="fw-semibold">${esc(d)}</span></div>`
            },
            { data: 'kategori', render: (d) => esc(d || '-') },
            { data: 'sira', className: 'text-center' },
            { data: 'durum', render: (d) => durumRozet[d] || esc(d) },
            { data: 'guncelleme', render: (d) => esc((d || '').substring(0, 10).split('-').reverse().join('.')) },
            {
                data: null, className: 'text-center', width: '130px', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <button type="button" class="btn btn-subtle-${r.durum === 'yayinda' ? 'secondary' : 'success'} table-action-btn btn-proje-durum" data-id="${esc(r.enc_id)}" data-durum="${r.durum === 'yayinda' ? 'taslak' : 'yayinda'}" title="${r.durum === 'yayinda' ? 'Yayından Kaldır' : 'Yayınla'}"><i class="bx ${r.durum === 'yayinda' ? 'bx-hide' : 'bx-broadcast'}"></i></button>
                    <button type="button" class="btn btn-subtle-warning table-action-btn btn-proje-duzenle" data-id="${esc(r.enc_id)}" title="Düzenle"><i class="bx bx-edit"></i></button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-proje-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
                </div>`
            }
        ],
        createdRow: function (row) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[3, 'asc']]
    }));

    $('.status-quick-filter').on('click', function () {
        durumFiltre = $(this).data('status');
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        tablo.ajax.reload();
    });

    const onizlemeGoster = (yol) => {
        $('#gorselOnizlemeKutu').toggleClass('d-none', !yol);
        if (yol) { $('#gorselOnizleme').attr('src', yol); }
    };

    const formuSifirla = () => {
        $('#siteprojeForm')[0].reset();
        $('#proje_id').val('');
        $('#gorsel_kaldir').val('0');
        $('#durum').val('taslak').trigger('change.select2');
        onizlemeGoster(null);
    };

    $('#btnYeniProje').on('click', function () {
        formuSifirla();
        $('#siteprojeModalLabel').text('Yeni Proje');
        $('#siteprojeModal').modal('show');
    });

    const duzenleAc = (id) => {
        $.post(API, { action: 'proje-getir', id: id }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Proje alınamadı.', 'error'); return; }
            formuSifirla();
            const d = res.data;
            $('#proje_id').val(d.id);
            ['baslik', 'slug', 'kategori', 'musteri', 'konum', 'proje_yili', 'kisa_aciklama', 'aciklama', 'sira'].forEach((k) => $('#' + k).val(d[k] == null ? '' : d[k]));
            $('#durum').val(d.durum).trigger('change.select2');
            onizlemeGoster(d.gorsel || null);
            $('#siteprojeModalLabel').text('Projei Düzenle');
            $('#siteprojeModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'Proje alınamadı.', 'error'));
    };

    $('#siteprojeTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) duzenleAc(veri.enc_id);
    });
    $('#siteprojeTable tbody').on('click', '.btn-proje-duzenle', function () { duzenleAc($(this).data('id')); });

    $('#gorsel').on('change', function () {
        const dosya = this.files && this.files[0];
        if (!dosya) { return; }
        $('#gorsel_kaldir').val('0');
        onizlemeGoster(URL.createObjectURL(dosya));
    });
    $('#btnGorselKaldir').on('click', function () {
        $('#gorsel').val('');
        $('#gorsel_kaldir').val('1');
        onizlemeGoster(null);
    });

    $('#siteprojeForm').on('submit', function (e) {
        e.preventDefault();
        if (!$('#baslik').val().trim()) { $('#baslik').addClass('is-invalid').trigger('focus'); return; }
        $('#baslik').removeClass('is-invalid');
        const btn = $('#btnProjeKaydet').prop('disabled', true);
        $.ajax({
            url: API, type: 'POST', data: new FormData(this), processData: false, contentType: false, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                $('#siteprojeModal').modal('hide');
                tablo.ajax.reload(null, false);
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'Proje kaydedilemedi.', 'error');
            }
        }).fail(() => Swal.fire('Hata', 'Proje kaydedilemedi.', 'error')).always(() => btn.prop('disabled', false));
    });

    $('#siteprojeTable tbody').on('click', '.btn-proje-durum', function () {
        $.post(API, { action: 'proje-durum', id: $(this).data('id'), durum: $(this).data('durum') }, function (res) {
            if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
            else Swal.fire('Hata', res.message || 'Durum güncellenemedi.', 'error');
        }, 'json');
    });

    try {
        if (new URLSearchParams(window.location.search).get('yeni') === '1') {
            history.replaceState(null, '', 'index.php?p=site-yonetimi/projeler');
            $('#btnYeniProje').trigger('click');
        }
    } catch (e) {}

    $('#siteprojeTable tbody').on('click', '.btn-proje-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Proje silinsin mi?', text: 'Proje sitede artık görünmez.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'proje-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'Proje silinemedi.', 'error');
            }, 'json');
        });
    });
});
