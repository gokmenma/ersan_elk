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
        const gizli = $('html').hasClass('sitehizmet-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('sitehizmet-summary-hidden');
        $('html').toggleClass('sitehizmet-summary-hidden', gizle);
        try { localStorage.setItem('sitehizmet_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    $('#sitehizmetModal .select2').select2({ dropdownParent: $('#sitehizmetModal'), width: '100%' });

    const tablo = $('#sitehizmetTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'hizmet-ajax-list';
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
                render: (d, t, r) => `<div class="d-flex align-items-center gap-2">${r.gorsel ? `<img class="shm-thumb" src="${esc(r.gorsel)}" alt="">` : '<span class="shm-thumb d-inline-flex align-items-center justify-content-center text-muted"><i class="bx bx-image"></i></span>'}<span class="fw-semibold">${esc(d)}</span></div>`
            },
            { data: 'slug', render: (d) => `<code>${esc(d)}</code>` },
            { data: 'sira', className: 'text-center' },
            { data: 'durum', render: (d) => durumRozet[d] || esc(d) },
            { data: 'guncelleme', render: (d) => esc((d || '').substring(0, 10).split('-').reverse().join('.')) },
            {
                data: null, className: 'text-center', width: '130px', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <button type="button" class="btn btn-subtle-${r.durum === 'yayinda' ? 'secondary' : 'success'} table-action-btn btn-hizmet-durum" data-id="${esc(r.enc_id)}" data-durum="${r.durum === 'yayinda' ? 'taslak' : 'yayinda'}" title="${r.durum === 'yayinda' ? 'Yayından Kaldır' : 'Yayınla'}"><i class="bx ${r.durum === 'yayinda' ? 'bx-hide' : 'bx-broadcast'}"></i></button>
                    <button type="button" class="btn btn-subtle-warning table-action-btn btn-hizmet-duzenle" data-id="${esc(r.enc_id)}" title="Düzenle"><i class="bx bx-edit"></i></button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-hizmet-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
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
        $('#sitehizmetForm')[0].reset();
        $('#hizmet_id').val('');
        $('#gorsel_kaldir').val('0');
        $('#durum').val('taslak').trigger('change.select2');
        onizlemeGoster(null);
    };

    $('#btnYeniHizmet').on('click', function () {
        formuSifirla();
        $('#sitehizmetModalLabel').text('Yeni Hizmet');
        $('#sitehizmetModal').modal('show');
    });

    const duzenleAc = (id) => {
        $.post(API, { action: 'hizmet-getir', id: id }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Hizmet alınamadı.', 'error'); return; }
            formuSifirla();
            const d = res.data;
            $('#hizmet_id').val(d.id);
            ['baslik', 'slug', 'kisa_aciklama', 'aciklama', 'ozellikler', 'adimlar', 'sira'].forEach((k) => $('#' + k).val(d[k] == null ? '' : d[k]));
            $('#durum').val(d.durum).trigger('change.select2');
            onizlemeGoster(d.gorsel || null);
            $('#sitehizmetModalLabel').text('Hizmeti Düzenle');
            $('#sitehizmetModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'Hizmet alınamadı.', 'error'));
    };

    $('#sitehizmetTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) duzenleAc(veri.enc_id);
    });
    $('#sitehizmetTable tbody').on('click', '.btn-hizmet-duzenle', function () { duzenleAc($(this).data('id')); });

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

    $('#sitehizmetForm').on('submit', function (e) {
        e.preventDefault();
        if (!$('#baslik').val().trim()) { $('#baslik').addClass('is-invalid').trigger('focus'); return; }
        $('#baslik').removeClass('is-invalid');
        const btn = $('#btnHizmetKaydet').prop('disabled', true);
        $.ajax({
            url: API, type: 'POST', data: new FormData(this), processData: false, contentType: false, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                $('#sitehizmetModal').modal('hide');
                tablo.ajax.reload(null, false);
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'Hizmet kaydedilemedi.', 'error');
            }
        }).fail(() => Swal.fire('Hata', 'Hizmet kaydedilemedi.', 'error')).always(() => btn.prop('disabled', false));
    });

    $('#sitehizmetTable tbody').on('click', '.btn-hizmet-durum', function () {
        $.post(API, { action: 'hizmet-durum', id: $(this).data('id'), durum: $(this).data('durum') }, function (res) {
            if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
            else Swal.fire('Hata', res.message || 'Durum güncellenemedi.', 'error');
        }, 'json');
    });

    try {
        if (new URLSearchParams(window.location.search).get('yeni') === '1') {
            history.replaceState(null, '', 'index.php?p=site-yonetimi/hizmetler');
            $('#btnYeniHizmet').trigger('click');
        }
    } catch (e) {}

    $('#sitehizmetTable tbody').on('click', '.btn-hizmet-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Hizmet silinsin mi?', text: 'Hizmet sitede artık görünmez.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'hizmet-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'Hizmet silinemedi.', 'error');
            }, 'json');
        });
    });
});
