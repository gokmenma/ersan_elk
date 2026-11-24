$(document).ready(function () {
    const API_URL = 'views/maliyet-raporu/api.php';
    const kalemler = window.maliyetKalemler || {};

    const esc = function (v) {
        return $('<div>').text(v == null ? '' : v).html();
    };
    const tl = function (n) {
        return Number(n || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };
    const post = function (data) {
        return $.ajax({ url: API_URL, type: 'POST', data: data, dataType: 'json' });
    };
    const hata = function (msg) {
        Swal.fire({ icon: 'error', title: 'Hata', text: msg || 'Sunucu hatası oluştu.' });
    };
    const basari = function (msg, reload) {
        Swal.fire({ icon: 'success', title: 'Başarılı', text: msg, timer: 1300, showConfirmButton: false })
            .then(function () { if (reload) { location.reload(); } });
    };

    const $toggle = $('#btnToggleSummaryCards');
    const toggleDurum = function () {
        const gizli = $('html').hasClass('maliyet-summary-hidden');
        const metin = gizli ? 'Özet kartları göster' : 'Özet kartları gizle';
        $toggle.attr({ 'aria-expanded': !gizli, title: metin, 'aria-label': metin });
        $toggle.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    toggleDurum();
    $toggle.on('click', function () {
        const gizle = !$('html').hasClass('maliyet-summary-hidden');
        $('html').toggleClass('maliyet-summary-hidden', gizle);
        try { localStorage.setItem('maliyet_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        toggleDurum();
    });

    if ($.fn.select2) {
        $('#yilSelect, #aySelect').select2({ minimumResultsForSearch: Infinity, width: '100%' });
        $('#mg_kategori').select2({ dropdownParent: $('#manuelGiderModal'), width: '100%' });
        $('#es_kaynak, #es_kategori, #es_kalem').select2({ dropdownParent: $('#eslestirmeModal'), width: '100%' });
        $('#fk_kalem').select2({ dropdownParent: $('#faturaKalemModal'), width: '100%' });
    }

    const filtreUygula = function (kalem) {
        const params = new URLSearchParams();
        params.set('p', 'maliyet-raporu/list');
        params.set('yil', $('#yilSelect').val());
        const ay = $('#aySelect').val();
        if (ay && ay !== '0') { params.set('ay', ay); }
        if (kalem) { params.set('kalem', kalem); }
        window.location.href = 'index?' + params.toString();
    };
    $('#yilSelect, #aySelect').on('change', function () { filtreUygula($('#maliyetDetayTable').data('kalem')); });

    const $tablo = $('#maliyetDetayTable');
    let detayDt = null;
    if ($tablo.length) {
        const atayabilir = String($tablo.data('atayabilir')) === '1';
        detayDt = $tablo.DataTable(applyLengthStateSave({
            ...getDatatableOptions(),
            processing: true,
            serverSide: true,
            responsive: false,
            ajax: {
                url: API_URL,
                type: 'POST',
                data: function (d) {
                    d.action = 'maliyet-ajax-list';
                    d.yil = $tablo.data('yil');
                    d.ay = $tablo.data('ay');
                    d.kalem = $tablo.data('kalem');
                },
                dataSrc: function (json) {
                    $('#maliyetKayitSayisi').text(json.recordsFiltered || 0);
                    $('#maliyetFiltreliToplam').text(tl(json.toplam));
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: null, className: 'text-center', orderable: false, searchable: false,
                    render: function (d, t, r, meta) { return '<span class="row-index-badge">' + (meta.row + meta.settings._iDisplayStart + 1) + '</span>'; }
                },
                { data: 'tarih', className: 'text-nowrap' },
                {
                    data: 'kalem_adi',
                    render: function (d, t, r) {
                        const renk = (kalemler[r.kalem] || {}).renk || '#64748b';
                        return '<span class="badge rounded-pill px-2 py-1 font-size-11 fw-semibold" style="background:' + renk + '1a;color:' + renk + ';">' + esc(d) + '</span>';
                    }
                },
                { data: 'alt_kategori', render: function (d) { return esc(d); } },
                { data: 'tutar', className: 'text-end text-nowrap fw-medium', render: function (d) { return tl(d); } },
                { data: 'kaynak', render: function (d) { return '<span class="badge-group">' + esc(d) + '</span>'; } },
                {
                    data: 'aciklama', className: 'text-truncate',
                    render: function (d) { return '<span title="' + esc(d) + '" style="display:inline-block;max-width:320px;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;">' + esc(d) + '</span>'; }
                },
                {
                    data: null, className: 'text-center', orderable: false, searchable: false,
                    render: function (d, t, r) {
                        if (!atayabilir || !r.fatura_id) { return '<span class="text-muted">-</span>'; }
                        return '<div class="action-btn-group"><button type="button" class="btn btn-subtle-warning table-action-btn fatura-kalem-ata" title="Kalem Değiştir"'
                            + ' data-id="' + esc(r.fatura_id) + '" data-kalem="' + esc(r.kalem) + '" data-aciklama="' + esc(r.aciklama) + '"><i class="bx bx-transfer-alt"></i></button></div>';
                    }
                }
            ],
            order: [[1, 'desc']]
        }));
    }

    $('#kalemOzetTable').on('click', '.maliyet-kalem-row', function () {
        const kalem = $(this).data('kalem');
        const aktif = $(this).hasClass('active');
        $('.maliyet-kalem-row').removeClass('active');
        const yeni = aktif ? '' : kalem;
        if (!aktif) { $(this).addClass('active'); }
        $tablo.data('kalem', yeni);
        $('#btnKalemFiltreTemizle').toggleClass('d-none', !yeni);
        $('#maliyetListAltBaslik').html((yeni ? esc((kalemler[yeni] || {}).ad) + ' kalemi' : 'Tüm kalemler') + ' · <span id="maliyetKayitSayisi">0</span> kayıt');
        if (detayDt) { detayDt.ajax.reload(); }
        if (yeni) { document.getElementById('maliyetListCard').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    const ozelKalemSecici = '#es_kalem, #mg_kategori, #fk_kalem';
    const kalemRenkleri = ['#ec4899', '#84cc16', '#f97316', '#06b6d4', '#a855f7', '#eab308', '#ef4444', '#3b82f6', '#22c55e', '#78716c'];
    const oncekiKalem = {};
    $(ozelKalemSecici).on('select2:opening', function () { oncekiKalem[this.id] = $(this).val(); });
    $(ozelKalemSecici).on('change', function () {
        if ($(this).val() !== '__yeni__') { return; }
        const $sel = $(this);
        const geri = oncekiKalem[this.id] || '';
        $sel.val(geri).trigger('change.select2');
        const $modal = $sel.closest('.modal');
        let secili = kalemRenkleri[0];
        Swal.fire({
            title: 'Yeni Maliyet Kalemi',
            html: '<input id="yk_ad" class="swal2-input" maxlength="60" placeholder="Kalem adı" autocomplete="off">'
                + '<div id="yk_renkler" class="d-flex justify-content-center flex-wrap gap-2 mt-2">'
                + kalemRenkleri.map(function (c, i) {
                    return '<button type="button" class="yk-renk border-0 rounded-circle" data-renk="' + c + '" style="width:26px;height:26px;background:' + c + ';outline:' + (i === 0 ? '2px solid #0f172a' : 'none') + ';outline-offset:2px;"></button>';
                }).join('') + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Ekle',
            cancelButtonText: 'Vazgeç',
            focusConfirm: false,
            didOpen: function () {
                $('#yk_ad').trigger('focus');
                $('#yk_renkler').on('click', '.yk-renk', function () {
                    secili = $(this).data('renk');
                    $('.yk-renk').css('outline', 'none');
                    $(this).css('outline', '2px solid #0f172a');
                });
            },
            preConfirm: function () {
                const ad = $.trim($('#yk_ad').val());
                if (ad.length < 2) { Swal.showValidationMessage('Kalem adı en az 2 karakter olmalıdır.'); return false; }
                return post({ action: 'kalem-ekle', ad: ad, renk: secili }).then(function (res) {
                    if (res.status !== 'success') { Swal.showValidationMessage(res.message || 'Kalem eklenemedi.'); return false; }
                    return res.kalem;
                }, function () { Swal.showValidationMessage('Sunucu hatası oluştu.'); return false; });
            }
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) { return; }
            const k = r.value;
            kalemler[k.kod] = { ad: k.ad, renk: k.renk };
            $(ozelKalemSecici).each(function () {
                const $o = $(this);
                const yeni = new Option(k.ad, k.kod);
                const $ayrac = $o.find('option[value="__yeni__"]');
                const $haric = $o.find('option[value="haric"]');
                if ($haric.length) { $(yeni).insertBefore($haric); } else if ($ayrac.length) { $(yeni).insertBefore($ayrac); } else { $o.append(yeni); }
                $o.trigger('change.select2');
            });
            $sel.val(k.kod).trigger('change.select2');
            if ($modal.length) { $modal.css('overflow', 'auto'); }
        });
    });

    const kalemGrafikCiz = function () {
        const el = document.getElementById('kalemDagilimChart');
        const anahtarlar = window.maliyetKalemAnahtar || [];
        const degerler = window.maliyetKalemDeger || [];
        if (!el || typeof ApexCharts === 'undefined' || !anahtarlar.length) { return; }
        const toplam = degerler.reduce(function (a, b) { return a + b; }, 0);
        const para = function (v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20BA'; };
        new ApexCharts(el, {
            chart: {
                type: 'donut', height: 300, fontFamily: 'inherit',
                events: {
                    dataPointSelection: function (e, ctx, cfg) {
                        $('.maliyet-kalem-row[data-kalem="' + anahtarlar[cfg.dataPointIndex] + '"]').trigger('click');
                    }
                }
            },
            series: degerler,
            labels: anahtarlar.map(function (k) { return (kalemler[k] || {}).ad || k; }),
            colors: anahtarlar.map(function (k) { return (kalemler[k] || {}).renk || '#94a3b8'; }),
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: ['#fff'] },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true,
                name: { fontSize: '12px', color: '#64748b' },
                value: { fontSize: '18px', fontWeight: 700, color: '#0f172a', formatter: function (v) { return para(v); } },
                total: { show: true, label: 'Toplam', color: '#64748b', formatter: function () { return para(toplam); } }
            } } } },
            tooltip: { y: { formatter: function (v) { return para(v) + (toplam > 0 ? ' \u00B7 %' + (v * 100 / toplam).toFixed(1).replace('.', ',') : ''); } } }
        }).render();
    };
    $(kalemGrafikCiz);

    $('#btnKalemFiltreTemizle').on('click', function () {
        $('.maliyet-kalem-row.active').trigger('click');
    });
    $('#btnHeaderPrint').on('click', function () { window.print(); });

    $('#manuelGiderEkle').on('click', function () {
        $('#manuelGiderForm')[0].reset();
        $('#manuel_gider_id').val('0');
        $('#mg_kategori').val('malzeme_demirbas').trigger('change');
        $('#manuelGiderModalLabel').text('Yeni Manuel Gider');
    });

    $('#manuelGiderForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'manuel-gider-kaydet');
        $.ajax({ url: API_URL, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (res.status === 'success') {
                    $('#manuelGiderModal').modal('hide');
                    basari(res.message, true);
                } else {
                    hata(res.message);
                }
            })
            .fail(function () { hata(); });
    });

    $(document).on('click', '.manuel-gider-duzenle', function (e) {
        e.preventDefault();
        post({ action: 'manuel-gider-detay', id: $(this).data('id') }).done(function (res) {
            if (res.status !== 'success' || !res.data) { hata('Kayıt bulunamadı.'); return; }
            const d = res.data;
            $('#manuel_gider_id').val(d.enc_id);
            $('#mg_kategori').val(kalemler[d.kategori] ? d.kategori : 'ofis_diger').trigger('change');
            $('[name="alt_kategori"]').val(d.alt_kategori || '');
            $('[name="tutar"]').val(d.tutar);
            const tarihEl = $('[name="tarih"]')[0];
            if (tarihEl && tarihEl._flatpickr) { tarihEl._flatpickr.setDate(d.tarih, true); } else { $('[name="tarih"]').val(d.tarih); }
            $('[name="aciklama"]').val(d.aciklama || '');
            $('[name="belge_no"]').val(d.belge_no || '');
            $('#manuelGiderModalLabel').text('Gider Düzenle');
            $('#manuelGiderModal').modal('show');
        }).fail(function () { hata(); });
    });

    $(document).on('click', '.manuel-gider-sil', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Silmek istediğinize emin misiniz?',
            text: 'Kayıt rapordan kaldırılacak.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'manuel-gider-sil', id: id }).done(function (res) {
                if (res.status === 'success') { basari(res.message, true); } else { hata(res.message); }
            }).fail(function () { hata(); });
        });
    });

    const $esModal = $('#eslestirmeModal');
    if (!$esModal.length) { return; }

    let degisiklikVar = false;
    let kategorilerYuklendi = false;

    const kaynakAlanlari = function () {
        const fatura = $('#es_kaynak').val() === 'fatura';
        $('.es-fatura-alan').toggleClass('d-none', !fatura);
        $('.es-gelir-alan').toggleClass('d-none', fatura);
    };
    $('#es_kaynak').on('change', function () {
        kaynakAlanlari();
        $('#es_etiket').val('');
        $('#es_etiket_bilgi').text('');
    });

    const kurallariYukle = function () {
        post({ action: 'eslestirme-listesi' }).done(function (res) {
            if (res.status !== 'success') { hata(res.message); return; }
            const $tb = $('#kuralTable tbody').empty();
            $('#kuralSayi').text(res.data.length);
            if (!res.data.length) { $tb.append('<tr><td colspan="5" class="text-center text-muted">Tanımlı eşleştirme yok.</td></tr>'); }
            res.data.forEach(function (r) {
                const renk = (kalemler[r.kalem] || {}).renk || '#94a3b8';
                $tb.append('<tr>'
                    + '<td><span class="badge-group">' + esc(r.kaynak_adi) + '</span></td>'
                    + '<td>' + esc(r.etiket) + '</td>'
                    + '<td class="text-secondary">' + esc(r.deger) + '</td>'
                    + '<td><span class="badge rounded-pill px-2 py-1 font-size-11 fw-semibold" style="background:' + renk + '1a;color:' + renk + ';">' + esc(r.kalem_adi) + '</span></td>'
                    + '<td class="text-center"><div class="action-btn-group">'
                    + '<button type="button" class="btn btn-subtle-warning table-action-btn kural-duzenle" title="Düzenle" data-kaynak="' + esc(r.kaynak) + '" data-deger="' + esc(r.deger) + '" data-etiket="' + esc(r.etiket) + '" data-kalem="' + esc(r.kalem) + '"><i class="bx bx-edit"></i></button>'
                    + '<button type="button" class="btn btn-subtle-danger table-action-btn kural-sil" title="Sil" data-id="' + esc(r.id) + '"><i class="bx bx-trash"></i></button>'
                    + '</div></td></tr>');
            });
            if (!kategorilerYuklendi) {
                const $sel = $('#es_kategori').empty().append(new Option('Kategori seçiniz', ''));
                (res.gelir_gider_kategorileri || []).forEach(function (k) { $sel.append(new Option(k, k)); });
                kategorilerYuklendi = true;
            }
        }).fail(function () { hata(); });
    };

    const eslesmemisYukle = function () {
        post({ action: 'eslesmemis-faturalar', yil: $tablo.data('yil'), ay: $tablo.data('ay') }).done(function (res) {
            if (res.status !== 'success') { hata(res.message); return; }
            const $tb = $('#eslesmemisTable tbody').empty();
            $('#eslesmemisSayi').text(res.data.length);
            if (!res.data.length) { $tb.append('<tr><td colspan="6" class="text-center text-muted">Bu dönemde eşleşmemiş fatura yok.</td></tr>'); }
            res.data.forEach(function (r) {
                $tb.append('<tr>'
                    + '<td>' + esc(r.unvan) + '</td>'
                    + '<td class="text-secondary">' + esc(r.vkn || '-') + '</td>'
                    + '<td class="text-end"><span class="badge-stat-tag">' + esc(r.adet) + '</span></td>'
                    + '<td class="text-end text-nowrap fw-medium">' + tl(r.toplam) + '</td>'
                    + '<td class="text-nowrap">' + esc(r.son_tarih) + '</td>'
                    + '<td class="text-center">' + (r.vkn ? '<button type="button" class="btn btn-sm btn-subtle-primary py-0 px-2 eslesmemis-sec" data-vkn="' + esc(r.vkn) + '" data-unvan="' + esc(r.unvan) + '">Seç</button>' : '-') + '</td>'
                    + '</tr>');
            });
        }).fail(function () { hata(); });
    };

    const formuDoldur = function (kaynak, deger, etiket, kalem) {
        $('#es_kaynak').val(kaynak).trigger('change');
        if (kaynak === 'fatura') {
            $('#deger_vkn').val(deger);
        } else {
            if (!$('#es_kategori option').filter(function () { return this.value === deger; }).length) {
                $('#es_kategori').append(new Option(deger, deger));
            }
            $('#es_kategori').val(deger).trigger('change');
        }
        $('#es_etiket').val(etiket || '');
        $('#es_etiket_bilgi').text(etiket ? 'Seçili: ' + etiket : '');
        if (kalem) { $('#es_kalem').val(kalem).trigger('change'); }
        $esModal.find('.modal-body').scrollTop(0);
    };

    const modalAc = function (sekme) {
        degisiklikVar = false;
        kaynakAlanlari();
        kurallariYukle();
        eslesmemisYukle();
        if (sekme) { $('[data-bs-target="' + sekme + '"]').tab('show'); }
        $esModal.modal('show');
    };
    $('#btnEslestirme').on('click', function () { modalAc('#tabEslesmemis'); });
    $('#btnEslesmemisAc').on('click', function () { modalAc('#tabEslesmemis'); });

    $esModal.on('click', '.eslesmemis-sec', function () {
        formuDoldur('fatura', String($(this).data('vkn')), $(this).data('unvan'), null);
    });
    $esModal.on('click', '.kural-duzenle', function () {
        const b = $(this);
        formuDoldur(b.data('kaynak'), String(b.data('deger')), b.data('etiket'), b.data('kalem'));
    });
    $esModal.on('click', '.kural-sil', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Eşleştirme kaldırılsın mı?',
            text: 'Bu kurala bağlı kayıtlar varsayılan kaleme döner.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Kaldır',
            cancelButtonText: 'Vazgeç'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'eslestirme-sil', id: id }).done(function (res) {
                if (res.status === 'success') { degisiklikVar = true; kurallariYukle(); eslesmemisYukle(); } else { hata(res.message); }
            }).fail(function () { hata(); });
        });
    });

    $('#eslestirmeForm').on('submit', function (e) {
        e.preventDefault();
        const kaynak = $('#es_kaynak').val();
        const deger = kaynak === 'fatura' ? $.trim($('#deger_vkn').val()) : $('#es_kategori').val();
        if (!deger) { hata(kaynak === 'fatura' ? 'VKN / TCKN giriniz.' : 'Kategori seçiniz.'); return; }
        post({
            action: 'eslestirme-kaydet',
            kaynak: kaynak,
            deger: deger,
            etiket: $('#es_etiket').val() || (kaynak === 'gelir_gider' ? deger : ''),
            kalem: $('#es_kalem').val()
        }).done(function (res) {
            if (res.status !== 'success') { hata(res.message); return; }
            degisiklikVar = true;
            $('#deger_vkn').val('');
            $('#es_etiket').val('');
            $('#es_etiket_bilgi').text('');
            kurallariYukle();
            eslesmemisYukle();
            Swal.fire({ icon: 'success', title: res.message, toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
        }).fail(function () { hata(); });
    });

    $esModal.on('hidden.bs.modal', function () {
        if (degisiklikVar) { location.reload(); }
    });

    $(document).on('click', '.fatura-kalem-ata', function () {
        const b = $(this);
        $('#fk_id').val(b.data('id'));
        $('#fk_bilgi').text(b.data('aciklama'));
        $('#fk_kalem').val(b.data('kalem')).trigger('change');
        $('#faturaKalemModal').modal('show');
    });
    $('#faturaKalemForm').on('submit', function (e) {
        e.preventDefault();
        post({ action: 'fatura-kalem-ata', id: $('#fk_id').val(), kalem: $('#fk_kalem').val() }).done(function (res) {
            if (res.status === 'success') {
                $('#faturaKalemModal').modal('hide');
                basari(res.message, true);
            } else {
                hata(res.message);
            }
        }).fail(function () { hata(); });
    });
});
