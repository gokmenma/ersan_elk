$(document).ready(function () {
    const API = 'views/site-yonetimi/api.php';
    let durumFiltre = 'all';

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const tarihSaat = (d) => {
        if (!d) return '';
        const [t, s] = String(d).split(' ');
        return `${t.split('-').reverse().join('.')} ${(s || '').substring(0, 5)}`;
    };
    const rozet = {
        yeni: '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Yeni</span>',
        okundu: '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Okundu</span>',
        cevaplandi: '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Cevaplandı</span>'
    };

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('sitemesaj-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('sitemesaj-summary-hidden');
        $('html').toggleClass('sitemesaj-summary-hidden', gizle);
        try { localStorage.setItem('sitemesaj_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    $('#sitemesajModal .select2').select2({ dropdownParent: $('#sitemesajModal'), width: '100%' });

    const tablo = $('#sitemesajTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'mesaj-ajax-list';
                d.durum_filtre = durumFiltre;
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                $('#stat_toplam').text(s.toplam || 0);
                $('#stat_sub_toplam').text(`Okundu: ${s.okundu || 0} | Cevaplandı: ${s.cevaplandi || 0}`);
                $('#stat_yeni').text(s.yeni || 0);
                $('#stat_okundu').text(s.okundu || 0);
                $('#stat_cevaplandi').text(s.cevaplandi || 0);
                return json.data;
            }
        },
        columns: [
            { data: null, className: 'text-center', width: '50px', orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
            {
                data: 'ad_soyad',
                render: (d, t, r) => `<span class="${r.durum === 'yeni' ? 'fw-bold' : 'fw-semibold'}">${esc(d)}</span><div class="text-muted font-size-12">${esc(r.telefon || r.email || '')}</div>`
            },
            { data: 'konu', render: (d) => esc(d || '-') },
            { data: 'ozet', render: (d) => `<span class="text-muted">${esc(d)}</span>` },
            { data: 'durum', render: (d) => rozet[d] || esc(d) },
            { data: 'created_at', render: (d) => esc(tarihSaat(d)) },
            {
                data: null, className: 'text-center', width: '100px', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <button type="button" class="btn btn-subtle-primary table-action-btn btn-mesaj-ac" data-id="${esc(r.enc_id)}" title="Görüntüle"><i class="bx bx-show"></i></button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-mesaj-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
                </div>`
            }
        ],
        createdRow: function (row) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[5, 'desc']]
    }));

    $('.status-quick-filter').on('click', function () {
        durumFiltre = $(this).data('status');
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        tablo.ajax.reload();
    });

    const ac = (id) => {
        $.post(API, { action: 'mesaj-getir', id: id }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Mesaj alınamadı.', 'error'); return; }
            const d = res.data;
            $('#mesaj_id').val(d.id);
            $('#mesajAd').text(d.ad_soyad);
            const iletisim = [];
            if (d.telefon) iletisim.push(`<a href="tel:${esc(String(d.telefon).replace(/[^0-9+]/g, ''))}"><i class="bx bx-phone"></i> ${esc(d.telefon)}</a>`);
            if (d.email) iletisim.push(`<a href="mailto:${esc(d.email)}"><i class="bx bx-envelope"></i> ${esc(d.email)}</a>`);
            $('#mesajIletisim').html(iletisim.join('<br>'));
            $('#mesajKonu').text(d.konu || '-');
            $('#mesajMetin').text(d.mesaj);
            $('#mesajTarih').text(tarihSaat(d.created_at));
            $('#yonetici_notu').val(d.yonetici_notu || '');
            $('#durum').val(d.durum === 'yeni' ? 'okundu' : d.durum).trigger('change.select2');
            $('#sitemesajModal').modal('show');
            if (d.durum === 'yeni') {
                $.post(API, { action: 'mesaj-durum', id: d.id, durum: 'okundu', yonetici_notu: d.yonetici_notu || '' }, function () {
                    tablo.ajax.reload(null, false);
                }, 'json');
            }
        }, 'json').fail(() => Swal.fire('Hata', 'Mesaj alınamadı.', 'error'));
    };

    $('#sitemesajTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) ac(veri.enc_id);
    });
    $('#sitemesajTable tbody').on('click', '.btn-mesaj-ac', function () { ac($(this).data('id')); });

    $('#sitemesajForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#btnMesajKaydet').prop('disabled', true);
        $.post(API, { action: 'mesaj-durum', id: $('#mesaj_id').val(), durum: $('#durum').val(), yonetici_notu: $('#yonetici_notu').val() }, function (res) {
            if (res.status === 'success') {
                $('#sitemesajModal').modal('hide');
                tablo.ajax.reload(null, false);
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'Mesaj güncellenemedi.', 'error');
            }
        }, 'json').fail(() => Swal.fire('Hata', 'Mesaj güncellenemedi.', 'error')).always(() => btn.prop('disabled', false));
    });

    $('#sitemesajTable tbody').on('click', '.btn-mesaj-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Mesaj silinsin mi?', text: 'Mesaj listeden kaldırılır.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'mesaj-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'Mesaj silinemedi.', 'error');
            }, 'json');
        });
    });
});
