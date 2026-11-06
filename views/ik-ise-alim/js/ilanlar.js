$(document).ready(function () {
    const API = 'views/ik-ise-alim/api.php';
    let durumFiltre = 'all';
    let aktifLink = null;

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const durumRozet = {
        taslak: '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Taslak</span>',
        yayinda: '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Yayında</span>',
        kapali: '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kapalı</span>'
    };

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('ikilan-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('ikilan-summary-hidden');
        $('html').toggleClass('ikilan-summary-hidden', gizle);
        try { localStorage.setItem('ikilan_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    $('#ikilanModal .select2').select2({ dropdownParent: $('#ikilanModal'), width: '100%' });

    const tablo = $('#ikilanTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'ilan-ajax-list';
                d.durum_filtre = durumFiltre;
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                $('#stat_toplam').text(s.toplam || 0);
                $('#stat_sub_toplam').text(`Taslak: ${s.taslak || 0} | Kapalı: ${s.kapali || 0}`);
                $('#stat_yayinda').text(s.yayinda || 0);
                $('#stat_taslak').text(s.taslak || 0);
                $('#stat_basvuru').text(s.basvuru || 0);
                return json.data;
            }
        },
        columns: [
            { data: null, className: 'text-center', width: '50px', orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
            { data: 'baslik', render: (d) => `<span class="fw-semibold">${esc(d)}</span>` },
            { data: 'departman', render: (d) => esc(d || '-') },
            { data: 'durum', render: (d) => durumRozet[d] || esc(d) },
            { data: 'basvuru_sayisi', className: 'text-center', orderable: true },
            { data: 'olumlu_sayisi', className: 'text-center', render: (d) => `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11">${esc(d)}</span>` },
            { data: 'created_at', render: (d) => esc((d || '').substring(0, 10).split('-').reverse().join('.')) },
            {
                data: null, className: 'text-center', width: '150px', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <a href="index.php?p=ik-ise-alim/basvurular&ilan=${encodeURIComponent(r.enc_id)}" class="btn btn-subtle-primary table-action-btn" title="Başvurular"><i class="bx bx-user-check"></i></a>
                    <button type="button" class="btn btn-subtle-success table-action-btn btn-ilan-paylas" data-id="${esc(r.enc_id)}" title="WhatsApp ile Paylaş"><i class="bx bxl-whatsapp"></i></button>
                    <button type="button" class="btn btn-subtle-warning table-action-btn btn-ilan-duzenle" data-id="${esc(r.enc_id)}" title="Düzenle"><i class="bx bx-edit"></i></button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-ilan-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
                </div>`
            }
        ],
        createdRow: function (row) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[6, 'desc']]
    }));

    $('.status-quick-filter').on('click', function () {
        durumFiltre = $(this).data('status');
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        tablo.ajax.reload();
    });

    const formuSifirla = () => {
        $('#ikilanForm')[0].reset();
        $('#ilan_id').val('');
        $('#durum').val('taslak').trigger('change.select2');
    };

    $('#btnYeniIlan').on('click', function () {
        formuSifirla();
        $('#ikilanModalLabel').text('Yeni İlan');
        $('#ikilanModal').modal('show');
    });

    const duzenleAc = (id) => {
        $.post(API, { action: 'ilan-getir', id: id }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'İlan alınamadı.', 'error'); return; }
            formuSifirla();
            const d = res.data;
            $('#ilan_id').val(d.id);
            ['baslik', 'departman', 'gorev', 'calisma_yeri', 'gecerlilik_tarihi', 'aciklama', 'aranan_nitelikler', 'ai_kriterleri', 'sorular'].forEach((k) => $('#' + k).val(d[k] || ''));
            $('#durum').val(d.durum).trigger('change.select2');
            $('#ikilanModalLabel').text('İlanı Düzenle');
            $('#ikilanModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'İlan alınamadı.', 'error'));
    };

    $('#ikilanTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) duzenleAc(veri.enc_id);
    });
    $('#ikilanTable tbody').on('click', '.btn-ilan-duzenle', function () { duzenleAc($(this).data('id')); });

    $('#ikilanForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#btnIlanKaydet').prop('disabled', true);
        $.post(API, $(this).serialize(), function (res) {
            if (res.status === 'success') {
                $('#ikilanModal').modal('hide');
                tablo.ajax.reload(null, false);
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'İlan kaydedilemedi.', 'error');
            }
        }, 'json').fail(() => Swal.fire('Hata', 'İlan kaydedilemedi.', 'error')).always(() => btn.prop('disabled', false));
    });

    $('#ikilanTable tbody').on('click', '.btn-ilan-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'İlan silinsin mi?', text: 'İlan kapatılır ve başvuru bağlantısı çalışmaz; başvurular korunur.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'ilan-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'İlan silinemedi.', 'error');
            }, 'json');
        });
    });

    $('#ikilanTable tbody').on('click', '.btn-ilan-paylas', function () {
        $.post(API, { action: 'ilan-link', id: $(this).data('id') }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Bağlantı alınamadı.', 'error'); return; }
            aktifLink = res.data;
            $('#linkMesaj').val(res.data.mesaj);
            $('#linkUrl').val(res.data.link);
            $('#linkQr').attr('src', res.data.qr || '');
            $('#linkYayindaDegil').toggleClass('d-none', !!res.data.yayinda);
            $('#ikilanLinkModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'Bağlantı alınamadı.', 'error'));
    });

    $('#btnLinkKopyala').on('click', function () {
        const url = $('#linkUrl').val();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => showToast('Bağlantı kopyalandı.', 'success'));
        } else {
            $('#linkUrl').trigger('select');
            document.execCommand('copy');
            showToast('Bağlantı kopyalandı.', 'success');
        }
    });

    $('#btnWhatsappPaylas').on('click', function () {
        const mesaj = $('#linkMesaj').val();
        window.open('https://wa.me/?text=' + encodeURIComponent(mesaj), '_blank', 'noopener');
    });
});
