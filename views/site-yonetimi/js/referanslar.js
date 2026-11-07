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
        const gizli = $('html').hasClass('sitereferans-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('sitereferans-summary-hidden');
        $('html').toggleClass('sitereferans-summary-hidden', gizle);
        try { localStorage.setItem('sitereferans_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    $('#sitereferansModal .select2').select2({ dropdownParent: $('#sitereferansModal'), width: '100%' });

    const tablo = $('#sitereferansTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'referans-ajax-list';
                d.durum_filtre = durumFiltre;
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                $('#stat_toplam').text(s.toplam || 0);
                $('#stat_sub_toplam').text(`Taslak: ${s.taslak || 0}`);
                $('#stat_yayinda').text(s.yayinda || 0);
                $('#stat_taslak').text(s.taslak || 0);
                $('#stat_ekstra').text(s.ekstra || 0);
                return json.data;
            }
        },
        columns: [
            { data: null, className: 'text-center', width: '50px', orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
            {
                data: 'kurum',
                render: (d, t, r) => `<div class="d-flex align-items-center gap-2">${r.logo ? `<img class="spm-thumb" src="${esc(r.logo)}" alt="">` : ''}<div><span class="fw-semibold">${esc(d)}</span><div class="text-muted font-size-12">${esc([r.kisi, r.unvan].filter(Boolean).join(' · '))}</div></div></div>`
            },
            { data: 'ozet', render: (d) => `<span class="text-muted">${esc(d)}</span>` },
            { data: 'sira', className: 'text-center' },
            { data: 'durum', render: (d) => durumRozet[d] || esc(d) },
            { data: 'guncelleme', render: (d) => esc((d || '').substring(0, 10).split('-').reverse().join('.')) },
            {
                data: null, className: 'text-center', width: '130px', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <button type="button" class="btn btn-subtle-${r.durum === 'yayinda' ? 'secondary' : 'success'} table-action-btn btn-durum" data-id="${esc(r.enc_id)}" data-durum="${r.durum === 'yayinda' ? 'taslak' : 'yayinda'}" title="${r.durum === 'yayinda' ? 'Yayından Kaldır' : 'Yayınla'}"><i class="bx ${r.durum === 'yayinda' ? 'bx-hide' : 'bx-broadcast'}"></i></button>
                    <button type="button" class="btn btn-subtle-warning table-action-btn btn-duzenle" data-id="${esc(r.enc_id)}" title="Düzenle"><i class="bx bx-edit"></i></button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
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

    const formuSifirla = () => {
        $('#sitereferansForm')[0].reset();
        $('#kayit_id').val('');
        $('#gorsel_kaldir').val('0');
        $('#durum').val('taslak').trigger('change.select2');
        onizlemeGoster(null);
    };

    $('#btnYeni').on('click', function () {
        formuSifirla();
        $('#sitereferansModalLabel').text('Yeni Referans');
        $('#sitereferansModal').modal('show');
    });

    const duzenleAc = (id) => {
        $.post(API, { action: 'referans-getir', id: id }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Kayıt alınamadı.', 'error'); return; }
            formuSifirla();
            const d = res.data;
            $('#kayit_id').val(d.id);
            ['kurum', 'kisi', 'unvan', 'yorum', 'sira'].forEach((k) => $('#' + k).val(d[k] == null ? '' : d[k]));
            $('#durum').val(d.durum).trigger('change.select2');
            onizlemeGoster(d.logo || null);
            $('#sitereferansModalLabel').text('Referans Düzenle');
            $('#sitereferansModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'Kayıt alınamadı.', 'error'));
    };

    $('#sitereferansTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) duzenleAc(veri.enc_id);
    });
    $('#sitereferansTable tbody').on('click', '.btn-duzenle', function () { duzenleAc($(this).data('id')); });

    $('#sitereferansForm').on('submit', function (e) {
        e.preventDefault();
        if (!$('#kurum').val().trim()) { $('#kurum').addClass('is-invalid').trigger('focus'); return; }
        $('#kurum').removeClass('is-invalid');
        const btn = $('#btnKaydet').prop('disabled', true);
        $.ajax({
            url: API, type: 'POST', data: new FormData(this), processData: false, contentType: false, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                $('#sitereferansModal').modal('hide');
                tablo.ajax.reload(null, false);
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'Kayıt yapılamadı.', 'error');
            }
        }).fail(() => Swal.fire('Hata', 'Kayıt yapılamadı.', 'error')).always(() => btn.prop('disabled', false));
    });

    $('#sitereferansTable tbody').on('click', '.btn-durum', function () {
        $.post(API, { action: 'referans-durum', id: $(this).data('id'), durum: $(this).data('durum') }, function (res) {
            if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
            else Swal.fire('Hata', res.message || 'Durum güncellenemedi.', 'error');
        }, 'json');
    });

    $('#sitereferansTable tbody').on('click', '.btn-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Kayıt silinsin mi?', text: 'Kayıt sitede artık görünmez.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'referans-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'Kayıt silinemedi.', 'error');
            }, 'json');
        });
    });
});
