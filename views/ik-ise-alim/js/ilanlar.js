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
            { data: 'goruntuleme_sayisi', className: 'text-center', render: (d) => `<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11"><i class="bx bx-show"></i> ${esc(d)}</span>` },
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
        order: [[7, 'desc']]
    }));

    $('.status-quick-filter').on('click', function () {
        durumFiltre = $(this).data('status');
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        tablo.ajax.reload();
    });

    let sorular = [];
    let adim = 1;
    let duzenlenen = -1;

    const soruCiz = () => {
        const html = sorular.map((q, i) => {
            if (i === duzenlenen) {
                return `<div class="soru-satir duzenleniyor"><span class="no">${i + 1}</span><input type="text" class="form-control form-control-sm soru-duzenle-input" maxlength="300" value="${esc(q)}" aria-label="Soruyu düzenle">
                    <button type="button" class="btn btn-subtle-success btn-ikon btn-soru-kaydet" data-i="${i}" title="Kaydet" aria-label="Kaydet"><i class="bx bx-check"></i></button>
                    <button type="button" class="btn btn-subtle-secondary btn-ikon btn-soru-iptal" title="Vazgeç" aria-label="Vazgeç"><i class="bx bx-undo"></i></button></div>`;
            }
            return `<div class="soru-satir"><span class="no">${i + 1}</span><span class="metin">${esc(q)}</span>
                <button type="button" class="btn btn-subtle-secondary btn-ikon btn-soru-yukari" data-i="${i}" title="Yukarı taşı" aria-label="Yukarı taşı"${i === 0 ? ' disabled' : ''}><i class="bx bx-chevron-up"></i></button>
                <button type="button" class="btn btn-subtle-secondary btn-ikon btn-soru-asagi" data-i="${i}" title="Aşağı taşı" aria-label="Aşağı taşı"${i === sorular.length - 1 ? ' disabled' : ''}><i class="bx bx-chevron-down"></i></button>
                <button type="button" class="btn btn-subtle-warning btn-ikon btn-soru-duzenle" data-i="${i}" title="Düzenle" aria-label="Soruyu düzenle"><i class="bx bx-edit"></i></button>
                <button type="button" class="btn btn-subtle-danger btn-ikon btn-soru-sil" data-i="${i}" title="Sil" aria-label="Soruyu sil"><i class="bx bx-trash"></i></button></div>`;
        }).join('');
        $('#soruListe').html(html || '<div class="soru-bos"><i class="bx bx-help-circle"></i> Henüz soru eklenmedi. Aşağıdan yazın veya hazır öneriyi seçin.</div>');
        $('#sorular').val(sorular.join('\n'));
        $('#soruSayac').text(`${sorular.length} / 8`);
        const dolu = sorular.length >= 8;
        $('#btnSoruEkle, #yeniSoru').prop('disabled', dolu);
        $('.soru-oneri').prop('disabled', dolu).each(function () { $(this).toggleClass('d-none', sorular.includes($(this).data('soru'))); });
        if (duzenlenen >= 0) $('#soruListe .soru-duzenle-input').trigger('focus');
    };
    const soruEkle = (metin) => {
        const v = (metin !== undefined ? metin : $('#yeniSoru').val()).trim();
        if (!v || sorular.length >= 8) return;
        sorular.push(v);
        if (metin === undefined) $('#yeniSoru').val('').trigger('focus');
        soruCiz();
    };
    const duzenlemeKaydet = () => {
        if (duzenlenen < 0) return;
        const v = $('#soruListe .soru-duzenle-input').val().trim();
        if (v) sorular[duzenlenen] = v; else sorular.splice(duzenlenen, 1);
        duzenlenen = -1;
        soruCiz();
    };
    $('#btnSoruEkle').on('click', () => soruEkle());
    $('#yeniSoru').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); soruEkle(); } });
    $('.soru-oneri').on('click', function () { soruEkle($(this).data('soru')); });
    $('#soruListe').on('click', '.btn-soru-sil', function () { duzenlenen = -1; sorular.splice(parseInt($(this).data('i'), 10), 1); soruCiz(); });
    $('#soruListe').on('click', '.btn-soru-duzenle', function () { duzenlenen = parseInt($(this).data('i'), 10); soruCiz(); });
    $('#soruListe').on('click', '.btn-soru-kaydet', duzenlemeKaydet);
    $('#soruListe').on('click', '.btn-soru-iptal', function () { duzenlenen = -1; soruCiz(); });
    $('#soruListe').on('keydown', '.soru-duzenle-input', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); duzenlemeKaydet(); }
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); duzenlenen = -1; soruCiz(); }
    });
    const soruTasi = (i, yon) => {
        const j = i + yon;
        if (j < 0 || j >= sorular.length) return;
        [sorular[i], sorular[j]] = [sorular[j], sorular[i]];
        soruCiz();
    };
    $('#soruListe').on('click', '.btn-soru-yukari', function () { soruTasi(parseInt($(this).data('i'), 10), -1); });
    $('#soruListe').on('click', '.btn-soru-asagi', function () { soruTasi(parseInt($(this).data('i'), 10), 1); });
    $('.kriter-chip[data-ekle]').on('click', function () {
        const alan = $('#ai_kriterleri');
        const mevcut = alan.val();
        alan.val(mevcut + (mevcut && !mevcut.endsWith('\n') ? '\n' : '') + $(this).data('ekle')).trigger('focus');
    });

    const adimGoster = (n) => {
        adim = Math.min(3, Math.max(1, n));
        $('.ikm-panel').addClass('d-none').filter(`[data-adim="${adim}"]`).removeClass('d-none');
        $('.ikm-step').removeClass('active').filter(`[data-adim="${adim}"]`).addClass('active');
        $('#btnGeri').css('visibility', adim === 1 ? 'hidden' : 'visible');
        $('#btnIleri').toggle(adim < 3);
    };
    $('.ikm-step').on('click', function () { adimGoster(parseInt($(this).data('adim'), 10)); });
    $('#btnIleri').on('click', function () { adimGoster(adim + 1); });
    $('#btnGeri').on('click', function () { adimGoster(adim - 1); });

    const tarihAlan = document.getElementById('gecerlilik_tarihi');
    const tarihFp = window.flatpickr ? window.flatpickr(tarihAlan, { locale: 'tr', dateFormat: 'd.m.Y', allowInput: true, minDate: null, disableMobile: true, appendTo: document.getElementById('ikilanModal') }) : null;
    const trTarih = (iso) => (iso ? iso.split('-').reverse().join('.') : '');
    const isoTarih = (tr) => { const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec((tr || '').trim()); return m ? `${m[3]}-${m[2]}-${m[1]}` : ''; };

    const formuSifirla = () => {
        $('#ikilanForm')[0].reset();
        duzenlenen = -1;
        if (tarihFp) tarihFp.clear();
        $('#ilan_id').val('');
        $('#durum').val('taslak').trigger('change.select2');
        sorular = [];
        soruCiz();
        adimGoster(1);
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
            ['baslik', 'departman', 'gorev', 'calisma_yeri', 'aciklama', 'aranan_nitelikler', 'ai_kriterleri'].forEach((k) => $('#' + k).val(d[k] || ''));
            if (tarihFp) tarihFp.setDate(trTarih(d.gecerlilik_tarihi), false, 'd.m.Y'); else $('#gecerlilik_tarihi').val(trTarih(d.gecerlilik_tarihi));
            sorular = (d.sorular || '').split('\n').map((x) => x.trim()).filter(Boolean);
            soruCiz();
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
        if (!$('#baslik').val().trim()) { adimGoster(1); $('#baslik').addClass('is-invalid').trigger('focus'); return; }
        $('#baslik').removeClass('is-invalid');
        duzenlemeKaydet();
        const tr = $('#gecerlilik_tarihi').val().trim();
        if (tr !== '' && !isoTarih(tr)) { adimGoster(1); Swal.fire('Uyarı', 'Son başvuru tarihi gg.aa.yyyy biçiminde olmalıdır.', 'warning'); return; }
        const veriDizi = $(this).serializeArray().map((o) => (o.name === 'gecerlilik_tarihi' ? { name: o.name, value: isoTarih(tr) } : o));
        const btn = $('#btnIlanKaydet').prop('disabled', true);
        $.post(API, $.param(veriDizi), function (res) {
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
        const baslik = tablo.row($(this).closest('tr')).data().baslik;
        $.post(API, { action: 'ilan-link', id: $(this).data('id') }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Bağlantı alınamadı.', 'error'); return; }
            aktifLink = res.data;
            $('#linkIlanAd').text(baslik);
            $('#linkBalon').text(res.data.mesaj);
            $('#linkUrl').val(res.data.link);
            $('#linkTelefon').val('');
            $('#linkYayindaDegil').toggleClass('d-none', !!res.data.yayinda);
            const img = $('#linkQr');
            img.addClass('d-none').off('load error');
            $('#linkQrYok').removeClass('d-none').text('QR oluşturuluyor...');
            const istemciQr = () => qrIstemciUret(res.data.link, function (url) {
                if (url) { img.attr('src', url).removeClass('d-none'); $('#linkQrYok').addClass('d-none'); }
                else { $('#linkQrYok').removeClass('d-none').text('QR oluşturulamadı. Bağlantıyı kopyalayın.'); }
            });
            if (res.data.qr) {
                img.on('load', function () { img.removeClass('d-none'); $('#linkQrYok').addClass('d-none'); })
                   .on('error', istemciQr)
                   .attr('src', res.data.qr);
            } else {
                istemciQr();
            }
            $('#ikilanLinkModal').modal('show');
        }, 'json').fail(() => Swal.fire('Hata', 'Bağlantı alınamadı.', 'error'));
    });

    const qrIstemciUret = (metin, cb) => {
        const uret = () => {
            try {
                const qr = window.qrcode(0, 'M');
                qr.addData(metin);
                qr.make();
                cb(qr.createDataURL(6, 8));
            } catch (e) { cb(null); }
        };
        if (window.qrcode) { uret(); return; }
        const sc = document.createElement('script');
        sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js';
        sc.onload = uret;
        sc.onerror = () => cb(null);
        document.head.appendChild(sc);
    };

    const panoyaKopyala = (metin, bildirim) => {
        const bitir = () => showToast(bildirim, 'success');
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(metin).then(bitir);
            return;
        }
        const t = $('<textarea>').val(metin).css({ position: 'fixed', opacity: 0 }).appendTo('body');
        t[0].select();
        document.execCommand('copy');
        t.remove();
        bitir();
    };
    $('#btnLinkKopyala').on('click', () => panoyaKopyala($('#linkUrl').val(), 'Bağlantı kopyalandı.'));
    $('#btnMesajKopyala').on('click', () => panoyaKopyala($('#linkBalon').text(), 'Mesaj kopyalandı.'));

    $('#btnWhatsappPaylas').on('click', function () {
        const mesaj = $('#linkBalon').text();
        let no = ($('#linkTelefon').val() || '').replace(/\D/g, '');
        if (no.length === 11 && no[0] === '0') no = '9' + no;
        else if (no.length === 10 && no[0] === '5') no = '90' + no;
        const hedef = no.length >= 11 && no.length <= 15 ? no : '';
        window.open('https://wa.me/' + hedef + '?text=' + encodeURIComponent(mesaj), '_blank', 'noopener');
    });
});
