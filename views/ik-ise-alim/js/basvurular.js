$(document).ready(function () {
    const API = 'views/ik-ise-alim/api.php';
    const urlParams = new URLSearchParams(window.location.search);
    const ilanFiltre = urlParams.get('ilan') || '';
    let durumFiltre = 'all';
    let etiketFiltre = '';

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const tarih = (v) => esc((v || '').substring(0, 10).split('-').reverse().join('.'));

    const durumAd = { yeni: 'Yeni', degerlendirildi: 'Değerlendirildi', gorusme: 'Görüşme', olumlu: 'Olumlu', olumsuz: 'Olumsuz', personele_donustu: 'Personele Dönüştü' };
    const durumRenk = { yeni: 'info', degerlendirildi: 'primary', gorusme: 'warning', olumlu: 'success', olumsuz: 'danger', personele_donustu: 'success' };
    const etiketRenk = { olumlu: 'success', belirsiz: 'warning', olumsuz: 'danger' };
    const etiketAd = { olumlu: 'Olumlu', belirsiz: 'Belirsiz', olumsuz: 'Olumsuz' };

    const rozet = (renk, metin) => `<span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${esc(metin)}</span>`;

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('ikbasvuru-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('ikbasvuru-summary-hidden');
        $('html').toggleClass('ikbasvuru-summary-hidden', gizle);
        try { localStorage.setItem('ikbasvuru_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    const tablo = $('#ikbasvuruTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'basvuru-ajax-list';
                d.durum_filtre = durumFiltre;
                d.etiket_filtre = etiketFiltre;
                d.ilan_filtre = ilanFiltre;
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                $('#stat_toplam').text(s.toplam || 0);
                $('#stat_sub_toplam').text(`Son 7 gün: ${s.son_7_gun || 0}`);
                $('#stat_bekleyen').text(s.bekleyen || 0);
                $('#stat_sub_bekleyen').text(`AI hata/bekleyen: ${(s.ai_hata || 0) + (s.ai_bekleyen || 0)}`);
                $('#stat_ai_olumlu').text(s.ai_olumlu || 0);
                $('#stat_sub_ai').text(`Belirsiz: ${s.ai_belirsiz || 0} | Olumsuz: ${s.ai_olumsuz || 0}`);
                $('#stat_donusen').text(s.donusen || 0);
                $('#stat_sub_puan').text(`Ort. AI puanı: ${s.ortalama_puan ? s.ortalama_puan : '-'}`);
                return json.data;
            }
        },
        columns: [
            { data: null, className: 'text-center', orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
            { data: 'ad_soyad', render: (d) => `<span class="fw-semibold">${esc(d)}</span>` },
            { data: 'ilan_baslik', render: (d) => esc(d) },
            { data: 'telefon', render: (d) => esc(d) },
            { data: 'sehir', render: (d) => esc(d || '-') },
            {
                data: 'ai_puan', className: 'text-center',
                render: (d, t, r) => r.ai_durum === 'hata' ? rozet('danger', 'AI hata') : (d === null ? rozet('secondary', 'Bekliyor') : `<strong>${esc(d)}</strong>`)
            },
            { data: 'ai_etiket', className: 'text-center', render: (d) => d ? rozet(etiketRenk[d] || 'secondary', etiketAd[d] || d) : '-' },
            { data: 'durum', render: (d) => rozet(durumRenk[d] || 'secondary', durumAd[d] || d) },
            { data: 'created_at', render: (d) => tarih(d) },
            {
                data: null, className: 'text-center', orderable: false, searchable: false,
                render: (d, t, r) => `<div class="action-btn-group">
                    <a href="index.php?p=ik-ise-alim/basvuru-detay&id=${encodeURIComponent(r.enc_id)}" class="btn btn-subtle-primary table-action-btn" title="Detay"><i class="bx bx-show"></i></a>
                    <button type="button" class="btn btn-subtle-danger table-action-btn btn-basvuru-sil" data-id="${esc(r.enc_id)}" title="Sil"><i class="bx bx-trash"></i></button>
                </div>`
            }
        ],
        createdRow: function (row) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[8, 'desc']]
    }));

    $('#ikbasvuruTable tbody').on('click', 'tr td:not(:last-child)', function () {
        const veri = tablo.row($(this).closest('tr')).data();
        if (veri) window.location.href = 'index.php?p=ik-ise-alim/basvuru-detay&id=' + encodeURIComponent(veri.enc_id);
    });

    $('.status-quick-filter').on('click', function () {
        durumFiltre = $(this).data('status');
        etiketFiltre = '';
        $('.status-quick-filter, .etiket-quick-filter').removeClass('active');
        $(this).addClass('active');
        tablo.ajax.reload();
    });
    $('.etiket-quick-filter').on('click', function () {
        const aktif = $(this).hasClass('active');
        durumFiltre = 'all';
        etiketFiltre = aktif ? '' : $(this).data('etiket');
        $('.status-quick-filter, .etiket-quick-filter').removeClass('active');
        if (aktif) $('.status-quick-filter[data-status="all"]').addClass('active');
        else $(this).addClass('active');
        tablo.ajax.reload();
    });

    $('#ikbasvuruTable tbody').on('click', '.btn-basvuru-sil', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Başvuru silinsin mi?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(API, { action: 'basvuru-sil', id: id }, function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); showToast(res.message, 'success'); }
                else Swal.fire('Hata', res.message || 'Başvuru silinemedi.', 'error');
            }, 'json');
        });
    });
});
