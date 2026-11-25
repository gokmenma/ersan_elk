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

    const kvAcKapa = function () {
        const $k = $('#kvGiderKart');
        const $govde = $('#kvGiderModalBody');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('kvGiderModal'));
        $govde.html('<div class="text-center text-secondary py-5">Yükleniyor...</div>');
        modal.show();
        post({ action: 'kurumlar-vergisi-detay', yil: $k.data('yil'), ay: $k.data('ay') }).done(function (res) {
            if (res.status !== 'success') { $govde.html('<div class="text-danger text-center py-4">' + esc(res.message || 'Detay yüklenemedi.') + '</div>'); return; }
            const d = res.data;
            const topla = function (liste, alan) { return liste.reduce(function (t, r) { return t + Number(r[alan] || 0); }, 0); };
            const maas = topla(d.bordro, 'maas');
            const sgk = topla(d.bordro, 'sgk');
            const fatura = topla(d.faturalar, 'matrah');
            const faturaKdvli = topla(d.faturalar, 'tutar');
            const genel = maas + sgk + fatura;

            let h = '<div class="row g-3 mb-4">'
                + [['Resmî Maaş', maas, 'text-dark'], ['SGK', sgk, 'text-dark'], ['Fatura (KDV\'siz)', fatura, 'text-dark'], ['Toplam Gider', genel, 'text-danger']].map(function (k) {
                    return '<div class="col-6 col-lg-3"><div class="summary-kpi-label">' + k[0] + '</div><div class="summary-kpi-value ' + k[2] + '">' + tl(k[1]) + '</div></div>';
                }).join('') + '</div>';

            h += '<h6 class="fw-bold font-size-14 mb-2">Bordro (Resmî Maaş + SGK)</h6>';
            h += '<div class="table-responsive mb-4"><table class="table table-bordered align-middle w-100 mb-0 kv-detay-tablo"><thead><tr><th>Dönem</th><th class="text-end">Personel</th><th class="text-end">Resmî Maaş (Banka)</th><th class="text-end">SGK + İşsizlik (İşçi + İşveren)</th></tr></thead><tbody>';
            if (!d.bordro.length) { h += '<tr><td colspan="4" class="text-center text-secondary">Hesaplanmış bordro bulunamadı.</td></tr>'; }
            d.bordro.forEach(function (r) {
                h += '<tr><td>' + esc(r.donem) + '</td><td class="text-end">' + r.personel + '</td><td class="text-end text-nowrap">' + tl(r.maas) + '</td><td class="text-end text-nowrap">' + tl(r.sgk) + '</td></tr>';
            });
            h += '</tbody><tfoot><tr class="fw-bold"><td colspan="2">Toplam</td><td class="text-end text-nowrap">' + tl(maas) + '</td><td class="text-end text-nowrap">' + tl(sgk) + '</td></tr></tfoot></table></div>';

            h += '<h6 class="fw-bold font-size-14 mb-2">Gelen Faturalar · Kalem Dağılımı</h6>';
            h += '<div class="table-responsive mb-4"><table class="table table-bordered align-middle w-100 mb-0 kv-detay-tablo"><thead><tr><th>Kalem</th><th class="text-end">Fatura</th><th class="text-end">KDV\'siz Tutar</th><th class="text-end">KDV Dahil Tutar</th></tr></thead><tbody>';
            if (!d.kalem_grup.length) { h += '<tr><td colspan="4" class="text-center text-secondary">Kaleme bağlı fatura bulunamadı.</td></tr>'; }
            d.kalem_grup.forEach(function (r) {
                h += '<tr><td>' + esc(r.kalem) + '</td><td class="text-end">' + r.adet + '</td><td class="text-end text-nowrap">' + tl(r.matrah) + '</td><td class="text-end text-nowrap">' + tl(r.tutar) + '</td></tr>';
            });
            h += '</tbody><tfoot><tr class="fw-bold"><td>Toplam</td><td class="text-end">' + d.faturalar.length + '</td><td class="text-end text-nowrap">' + tl(fatura) + '</td><td class="text-end text-nowrap">' + tl(faturaKdvli) + '</td></tr></tfoot></table></div>';

            h += '<h6 class="fw-bold font-size-14 mb-2">Fatura Listesi</h6>';
            h += '<div class="kv-detay-fatura-kutu border rounded mb-4"><table class="table table-hover align-middle w-100 mb-0 kv-detay-tablo"><thead><tr><th>Tarih</th><th>Fatura No</th><th>Firma</th><th>Kalem</th><th class="text-end">KDV\'siz</th><th class="text-end">KDV Dahil</th></tr></thead><tbody>';
            d.faturalar.forEach(function (r) {
                h += '<tr><td class="text-nowrap">' + esc(r.tarih) + '</td><td class="text-nowrap">' + esc(r.fatura_no) + '</td><td>' + esc(r.unvan) + '</td><td>' + esc(r.kalem) + '</td><td class="text-end text-nowrap">' + tl(r.matrah) + '</td><td class="text-end text-nowrap">' + tl(r.tutar) + '</td></tr>';
            });
            h += '</tbody></table></div>';

            if (d.baglanmamis.adet > 0) {
                h += '<div class="alert alert-info mb-0 font-size-13"><i class="bx bx-info-circle me-1"></i><strong>' + d.baglanmamis.adet + '</strong> gelen fatura maliyet kalemine bağlı değildir; yine de gider olarak hesaba dahildir (KDV\'siz ' + tl(d.baglanmamis.matrah) + ' · KDV dahil ' + tl(d.baglanmamis.tutar) + ').</div>';
            }
            $govde.html(h);
        }).fail(function () {
            $govde.html('<div class="text-danger text-center py-4">Detay yüklenemedi.</div>');
        });
    };
    $('#kvGiderKart').on('click', kvAcKapa).on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); kvAcKapa(); }
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
        params.set('ay', ay || '0');
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
        if (kalem === 'kurumlar_vergisi') {
            document.getElementById('kurumlarVergisiCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
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
    const oncekiKalem = {};
    let yeniKalemHedef = null;
    const $yeniKalemModal = $('#yeniKalemModal');
    $(ozelKalemSecici).on('select2:opening', function () { oncekiKalem[this.id] = $(this).val(); });
    $(ozelKalemSecici).on('change', function () {
        if ($(this).val() !== '__yeni__') { return; }
        yeniKalemHedef = $(this);
        $(this).val(oncekiKalem[this.id] || '').trigger('change.select2');
        $yeniKalemModal.modal('show');
    });
    $yeniKalemModal.on('shown.bs.modal', function () {
        $('.modal-backdrop').last().css('z-index', 1070);
        $('#yk_ad').trigger('focus');
    });
    $yeniKalemModal.on('hidden.bs.modal', function () {
        $('#yeniKalemForm')[0].reset();
        $('#yk_hata').addClass('d-none');
        if ($('.modal.show').length) { $('body').addClass('modal-open'); }
    });
    $('#yeniKalemForm').on('submit', function (e) {
        e.preventDefault();
        const ad = $.trim($('#yk_ad').val());
        const $hata = $('#yk_hata');
        if (ad.length < 2) { $hata.text('Kalem adı en az 2 karakter olmalıdır.').removeClass('d-none'); return; }
        const $btn = $(this).find('button[type=submit]').prop('disabled', true);
        post({ action: 'kalem-ekle', ad: ad, renk: $('.yk-renk-input:checked').val() }).done(function (res) {
            if (res.status !== 'success') { $hata.text(res.message || 'Kalem eklenemedi.').removeClass('d-none'); return; }
            const k = res.kalem;
            kalemler[k.kod] = { ad: k.ad, renk: k.renk };
            $(ozelKalemSecici).each(function () {
                const $o = $(this);
                const $haric = $o.find('option[value="haric"]');
                const $yeni = $o.find('option[value="__yeni__"]');
                const opt = new Option(k.ad, k.kod);
                if ($haric.length) { $(opt).insertBefore($haric); } else if ($yeni.length) { $(opt).insertBefore($yeni); } else { $o.append(opt); }
                $o.trigger('change.select2');
            });
            if (yeniKalemHedef) { yeniKalemHedef.val(k.kod).trigger('change.select2'); }
            $yeniKalemModal.modal('hide');
        }).fail(function (xhr) { $hata.text((xhr.responseJSON && xhr.responseJSON.message) || 'Sunucu hatası oluştu.').removeClass('d-none'); })
          .always(function () { $btn.prop('disabled', false); });
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

    let kuralDt = null;
    let eslesmemisDt = null;
    const dtKur = function (selector, ekstra) {
        return $(selector).DataTable(applyLengthStateSave({ ...getDatatableOptions(), responsive: false, autoWidth: false, ...ekstra }));
    };
    const dtYenile = function (dt, $satirlar) {
        dt.clear();
        if ($satirlar.length) { dt.rows.add($satirlar.map(function ($tr) { return $tr.get(0); })); }
        dt.draw(false);
    };

    const kurallariYukle = function () {
        post({ action: 'eslestirme-listesi' }).done(function (res) {
            if (res.status !== 'success') { hata(res.message); return; }
            $('#kuralSayi').text(res.data.length);
            if (!kuralDt) { kuralDt = dtKur('#kuralTable', { order: [[0, 'asc']], columnDefs: [{ targets: -1, orderable: false, searchable: false }] }); }
            const satirlar = res.data.map(function (r) {
                const renk = (kalemler[r.kalem] || {}).renk || '#94a3b8';
                return $('<tr>'
                    + '<td><span class="badge-group">' + esc(r.kaynak_adi) + '</span></td>'
                    + '<td>' + esc(r.etiket) + '</td>'
                    + '<td class="text-secondary">' + esc(r.deger) + '</td>'
                    + '<td><span class="badge rounded-pill px-2 py-1 font-size-11 fw-semibold" style="background:' + renk + '1a;color:' + renk + ';">' + esc(r.kalem_adi) + '</span></td>'
                    + '<td class="text-center"><div class="action-btn-group">'
                    + '<button type="button" class="btn btn-subtle-warning table-action-btn kural-duzenle" title="Düzenle" data-kaynak="' + esc(r.kaynak) + '" data-deger="' + esc(r.deger) + '" data-etiket="' + esc(r.etiket) + '" data-kalem="' + esc(r.kalem) + '"><i class="bx bx-edit"></i></button>'
                    + '<button type="button" class="btn btn-subtle-danger table-action-btn kural-sil" title="Sil" data-id="' + esc(r.id) + '"><i class="bx bx-trash"></i></button>'
                    + '</div></td></tr>');
            });
            dtYenile(kuralDt, satirlar);
            if (!kategorilerYuklendi) {
                const $sel = $('#es_kategori').empty().append(new Option('Kategori seçiniz', ''));
                (res.gelir_gider_kategorileri || []).forEach(function (k) { $sel.append(new Option(k, k)); });
                kategorilerYuklendi = true;
            }
        }).fail(function () { hata(); });
    };

    const secilenVkn = {};
    const topluSayiGuncelle = function () {
        const n = Object.keys(secilenVkn).length;
        $('#topluSecilenSayi').text(n);
        $('#btnTopluEslestir').prop('disabled', n === 0);
        const gorunen = $('.eslesmemis-check').length;
        const gorunenSecili = $('.eslesmemis-check:checked').length;
        const $hepsi = $('#eslesmemisHepsi');
        $hepsi.prop('checked', gorunen > 0 && gorunenSecili === gorunen);
        $hepsi.prop('indeterminate', gorunenSecili > 0 && gorunenSecili < gorunen);
    };
    const secimUygula = function ($c, secili) {
        const vkn = String($c.data('vkn'));
        $c.prop('checked', secili).closest('tr').toggleClass('mk-secili', secili);
        if (secili) { secilenVkn[vkn] = $c.data('unvan'); } else { delete secilenVkn[vkn]; }
    };
    $('#eslesmemisHepsi').on('change', function () {
        const secili = this.checked;
        if (eslesmemisDt) {
            eslesmemisDt.rows({ search: 'applied' }).every(function () {
                const $c = $(this.node()).find('.eslesmemis-check');
                if ($c.length) { secimUygula($c, secili); }
            });
        }
        topluSayiGuncelle();
    });
    $('#eslesmemisTable').on('change', '.eslesmemis-check', function () { secimUygula($(this), this.checked); topluSayiGuncelle(); });
    $('#eslesmemisTable').on('click', 'tbody td', function (e) {
        if ($(e.target).closest('input, button, a, label').length) { return; }
        const $c = $(this).closest('tr').find('.eslesmemis-check');
        if ($c.length) { secimUygula($c, !$c.prop('checked')); topluSayiGuncelle(); }
    });
    $('#eslesmemisTable').on('draw.dt', topluSayiGuncelle);
    $('#btnTopluEslestir').on('click', function () {
        const kalem = $('#es_kalem').val();
        const vkn = Object.keys(secilenVkn);
        if (!vkn.length || !kalem || kalem === '__yeni__') { hata('Firma seçin ve üstten geçerli bir maliyet kalemi belirleyin.'); return; }
        const kalemAd = $('#es_kalem option:selected').text();
        Swal.fire({
            icon: 'question', title: 'Toplu eşleştirme',
            text: vkn.length + ' firma "' + kalemAd + '" kalemine eşleştirilecek.',
            showCancelButton: true, confirmButtonText: 'Eşleştir', cancelButtonText: 'Vazgeç'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            const etiket = vkn.map(function (v) { return secilenVkn[v]; });
            post({ action: 'eslestirme-toplu-kaydet', kalem: kalem, vkn: vkn, etiket: etiket }).done(function (res) {
                if (res.status !== 'success') { hata(res.message); return; }
                degisiklikVar = true;
                vkn.forEach(function (v) { delete secilenVkn[v]; });
                kurallariYukle();
                eslesmemisYukle();
                Swal.fire({ icon: 'success', title: res.message, toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
            }).fail(function (xhr) { hata(xhr.responseJSON && xhr.responseJSON.message); });
        });
    });

    const tarihSirala = function (t) {
        const p = String(t || '').split('.');
        return p.length === 3 ? p[2] + p[1] + p[0] : '0';
    };
    const eslesmemisYukle = function () {
        post({ action: 'eslesmemis-faturalar', yil: $tablo.data('yil'), ay: $tablo.data('ay') }).done(function (res) {
            if (res.status !== 'success') { hata(res.message); return; }
            $('#eslesmemisSayi').text(res.data.length);
            if (!eslesmemisDt) {
                eslesmemisDt = dtKur('#eslesmemisTable', { order: [[4, 'desc']], columnDefs: [{ targets: [0, -1], orderable: false, searchable: false }] });
            }
            const gecerli = {};
            const satirlar = res.data.map(function (r) {
                if (r.vkn) { gecerli[String(r.vkn)] = true; }
                const secili = r.vkn && secilenVkn[String(r.vkn)] !== undefined;
                const kutu = r.vkn
                    ? '<label class="mk-check"><input type="checkbox" class="eslesmemis-check" data-vkn="' + esc(r.vkn) + '" data-unvan="' + esc(r.unvan) + '"' + (secili ? ' checked' : '') + '><span class="mk-check-box"></span></label>'
                    : '';
                return $('<tr' + (secili ? ' class="mk-secili"' : '') + '>'
                    + '<td class="text-center">' + kutu + '</td>'
                    + '<td>' + esc(r.unvan) + '</td>'
                    + '<td class="text-secondary">' + esc(r.vkn || '-') + '</td>'
                    + '<td class="text-end" data-order="' + esc(r.adet) + '"><span class="badge-stat-tag">' + esc(r.adet) + '</span></td>'
                    + '<td class="text-end text-nowrap fw-medium" data-order="' + esc(r.toplam) + '">' + tl(r.toplam) + '</td>'
                    + '<td class="text-nowrap" data-order="' + tarihSirala(r.son_tarih) + '">' + esc(r.son_tarih) + '</td>'
                    + '<td class="text-center">' + (r.vkn ? '<button type="button" class="btn btn-sm btn-subtle-primary py-0 px-2 eslesmemis-sec" data-vkn="' + esc(r.vkn) + '" data-unvan="' + esc(r.unvan) + '">Seç</button>' : '-') + '</td>'
                    + '</tr>');
            });
            Object.keys(secilenVkn).forEach(function (v) { if (!gecerli[v]) { delete secilenVkn[v]; } });
            dtYenile(eslesmemisDt, satirlar);
            topluSayiGuncelle();
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

    const dtHizala = function () {
        [kuralDt, eslesmemisDt].forEach(function (dt) { if (dt) { dt.columns.adjust(); } });
    };
    $esModal.on('shown.bs.modal', dtHizala);
    $esModal.on('shown.bs.tab', dtHizala);
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
