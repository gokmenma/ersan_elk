$(function () {
    const API = 'views/dosya-yonetimi/api.php';
    const INDIR = 'views/dosya-yonetimi/indir.php';
    const Y = window.DOSYA_YETKI || {};

    let secili = 'tum';
    let agacListe = [];
    let gorunum = 'liste';
    try { gorunum = localStorage.getItem('dosya_gorunum') || 'liste'; } catch (e) {}
    let aramaZamani = null;
    let duzenlenen = null;

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const boyutYaz = (b) => {
        b = Number(b) || 0;
        if (b >= 1073741824) return (b / 1073741824).toFixed(2).replace('.', ',') + ' GB';
        if (b >= 1048576) return (b / 1048576).toFixed(1).replace('.', ',') + ' MB';
        if (b >= 1024) return Math.round(b / 1024) + ' KB';
        return b + ' B';
    };
    const tarihYaz = (t) => {
        if (!t) return '';
        const d = new Date(String(t).replace(' ', 'T'));
        return isNaN(d) ? esc(t) : d.toLocaleDateString('tr-TR') + ' ' + d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    };
    const ikon = (u) => ({
        pdf: '<i class="bx bxs-file-pdf text-danger"></i>',
        doc: '<i class="bx bxs-file-doc text-primary"></i>',
        docx: '<i class="bx bxs-file-doc text-primary"></i>',
        xls: '<i class="bx bxs-file text-success"></i>',
        xlsx: '<i class="bx bxs-file text-success"></i>',
        csv: '<i class="bx bxs-file text-success"></i>',
        jpg: '<i class="bx bxs-file-image text-info"></i>',
        jpeg: '<i class="bx bxs-file-image text-info"></i>',
        png: '<i class="bx bxs-file-image text-info"></i>'
    }[u] || '<i class="bx bxs-file-blank text-secondary"></i>');

    const hata = (mesaj) => Swal.fire({ icon: 'error', title: 'Hata', text: mesaj || 'İşlem tamamlanamadı.' });
    const basari = (mesaj) => Swal.fire({ icon: 'success', title: mesaj, timer: 1400, showConfirmButton: false });

    const api = (action, data) => $.ajax({
        url: API, type: 'POST', dataType: 'json', data: Object.assign({ action }, data || {})
    }).then(
        (r) => r.data,
        (xhr) => $.Deferred().reject((xhr.responseJSON && xhr.responseJSON.message) || 'İşlem tamamlanamadı.')
    );

    const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    $('.modal .select2').each(function () {
        $(this).select2({ dropdownParent: $(this).closest('.modal') });
    });

    const toggleBtn = $('#btnToggleSummaryCards');
    const guncelleToggle = () => {
        const gizli = $('html').hasClass('dosya-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    guncelleToggle();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('dosya-summary-hidden');
        $('html').toggleClass('dosya-summary-hidden', gizle);
        try { localStorage.setItem('dosya_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        guncelleToggle();
    });

    const aksiyonlar = (tur, id, ad, uzanti) => {
        let h = '<div class="action-btn-group d-inline-flex gap-1" data-tur="' + tur + '" data-id="' + esc(id) + '" data-ad="' + esc(ad) + '">';
        if (tur === 'dosya') {
            h += '<button type="button" class="btn btn-subtle-primary table-action-btn act-indir" title="İndir"><i class="bx bx-download"></i></button>';
            if (Y.paylas) h += '<button type="button" class="btn btn-subtle-success table-action-btn act-paylas" title="Paylaş"><i class="bx bx-share-alt"></i></button>';
        } else if (Y.klasor) {
            h += '<button type="button" class="btn btn-subtle-success table-action-btn act-rol" title="Erişim Yetkisi"><i class="bx bx-shield-quarter"></i></button>';
        }
        if (Y.duzenle) {
            h += '<button type="button" class="btn btn-subtle-warning table-action-btn act-ad" title="Yeniden Adlandır"><i class="bx bx-edit"></i></button>';
            h += '<button type="button" class="btn btn-subtle-secondary table-action-btn act-tasi" title="Taşı"><i class="bx bx-move"></i></button>';
        }
        if (Y.sil) h += '<button type="button" class="btn btn-subtle-danger table-action-btn act-sil" title="Sil"><i class="bx bx-trash"></i></button>';
        return h + '</div>';
    };

    const ozetYukle = () => api('ozet').then((o) => {
        $('#stat_toplam_dosya').text(o.toplam_dosya);
        $('#stat_toplam_klasor').text(o.toplam_klasor);
        $('#stat_bu_ay').text(o.bu_ay);
        $('#stat_toplam_boyut').text(boyutYaz(o.toplam_boyut));
    });

    const agacCiz = () => {
        const cocuklar = {};
        agacListe.forEach((k) => { (cocuklar[k.parent_id || 'kok'] = cocuklar[k.parent_id || 'kok'] || []).push(k); });
        const dal = (anahtar) => {
            const liste = cocuklar[anahtar] || [];
            if (!liste.length) return '';
            return '<ul>' + liste.map((k) =>
                '<li><div class="node' + (secili === k.id ? ' active' : '') + '" data-id="' + esc(k.id) + '"><i class="bx bx-folder text-warning"></i><span>' + esc(k.ad) + '</span><span class="cnt">' + k.dosya_sayisi + '</span></div>' + dal(k.id) + '</li>'
            ).join('') + '</ul>';
        };
        $('#dosyaAgac').html('<ul><li><div class="node' + (secili === 'tum' ? ' active' : '') + '" data-id="tum"><i class="bx bx-home text-primary"></i><span>Tüm Dosyalar</span></div>' + dal('kok') + '</li></ul>');
    };

    const agacYukle = () => api('agac').then((r) => { agacListe = r.klasorler; agacCiz(); });

    $('#dosyaAgac').on('click', '.node', function () {
        secili = String($(this).data('id'));
        agacCiz();
        yenile();
    });

    const klasorAdi = (id) => (agacListe.find((k) => k.id === id) || {}).ad;
    const konumGuncelle = () => {
        $('#dosyaKonumBilgi').text(secili === 'tum' ? 'Tüm dosyalar' : (klasorAdi(secili) || 'Klasör'));
    };

    const dosyaSatirHtml = (d) =>
        '<div class="dosya-row" data-act="ac" data-id="' + esc(d.id) + '" data-ad="' + esc(d.ad) + '" data-onizle="' + (d.onizlenebilir ? 1 : 0) + '"><span class="ico">' + ikon(d.uzanti) + '</span>' +
        '<span class="ad" title="' + esc(d.ad) + '">' + esc(d.ad) + '</span><span class="text-muted font-size-12 d-none d-md-inline">' + boyutYaz(d.boyut) + '</span>' +
        '<span class="text-muted font-size-12 d-none d-lg-inline">' + tarihYaz(d.tarih) + '</span>' + aksiyonlar('dosya', d.id, d.ad, d.uzanti) + '</div>';

    const dosyaKartHtml = (d) =>
        '<div class="col-6 col-md-4 col-xl-3"><div class="dosya-tile" data-act="ac" data-id="' + esc(d.id) + '" data-ad="' + esc(d.ad) + '" data-onizle="' + (d.onizlenebilir ? 1 : 0) + '">' +
        '<span class="ico">' + ikon(d.uzanti) + '</span><span class="ad">' + esc(d.ad) + '</span>' +
        '<span class="text-muted font-size-12">' + boyutYaz(d.boyut) + ' · ' + tarihYaz(d.tarih).split(' ')[0] + '</span>' + aksiyonlar('dosya', d.id, d.ad, d.uzanti) + '</div></div>';

    const klasorKartHtml = (k) =>
        '<div class="col-6 col-md-4 col-xl-3"><div class="dosya-tile" data-act="klasor-ac" data-id="' + esc(k.id) + '"><span class="ico"><i class="bx bxs-folder text-warning"></i></span>' +
        '<span class="ad">' + esc(k.ad) + '</span><span class="text-muted font-size-12">' + k.dosya_sayisi + ' dosya</span>' + aksiyonlar('klasor', k.id, k.ad) + '</div></div>';

    const icerikYukle = () => {
        const arama = $('#dosyaArama').val().trim();
        const hedef = secili === 'tum' ? 'kok' : secili;
        return api('klasor-icerik', { klasor_id: hedef, arama }).then((r) => {
            const yol = ['<a href="#" class="yol-link" data-id="tum">Ana Dizin</a>'];
            (r.yol || []).forEach((p) => yol.push('<a href="#" class="yol-link" data-id="' + esc(p.id) + '">' + esc(p.ad) + '</a>'));
            $('#dosyaYol').html(r.aranan ? '<span class="text-muted">Arama sonuçları</span>' : yol.join(' <i class="bx bx-chevron-right"></i> '));

            let h = '';
            if (gorunum === 'izgara') {
                h = '<div class="row g-3">' + (r.klasorler || []).map(klasorKartHtml).join('') + (r.dosyalar || []).map(dosyaKartHtml).join('') + '</div>';
            } else {
                h = (r.klasorler || []).map((k) =>
                    '<div class="dosya-row" data-act="klasor-ac" data-id="' + esc(k.id) + '"><span class="ico"><i class="bx bxs-folder text-warning"></i></span><span class="ad">' + esc(k.ad) + '</span>' +
                    '<span class="text-muted font-size-12">' + k.dosya_sayisi + ' dosya</span>' + aksiyonlar('klasor', k.id, k.ad) + '</div>'
                ).join('') + (r.dosyalar || []).map(dosyaSatirHtml).join('');
            }
            if (!(r.klasorler || []).length && !(r.dosyalar || []).length) {
                h = '<div class="text-center text-muted py-5"><i class="bx bx-folder-open fs-1 d-block mb-2"></i>' + (r.aranan ? 'Aramanızla eşleşen dosya yok.' : 'Bu klasör boş. Dosya sürükleyip bırakabilirsiniz.') + '</div>';
            }
            $('#gorunumIcerik').html(h);
        }, hata);
    };

    const table = $('#dosyaTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'liste';
                d.klasor_id = secili;
            },
            error: function (xhr) { hata((xhr.responseJSON && xhr.responseJSON.message) || 'Liste yüklenemedi.'); }
        },
        columns: [
            { data: 'ad', render: (v, t, r) => '<span class="me-2 fs-5 align-middle">' + ikon(r.uzanti) + '</span>' + esc(v) },
            { data: 'klasor_adi', render: (v) => esc(v || 'Ana Dizin') },
            { data: 'uzanti', render: (v) => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">' + esc(String(v).toUpperCase()) + '</span>' },
            { data: 'boyut', className: 'text-end', render: (v, t) => t === 'display' ? boyutYaz(v) : v },
            { data: 'yukleyen', render: (v) => esc(v || '-') },
            { data: 'tarih', render: (v, t) => t === 'display' ? tarihYaz(v) : v },
            { data: null, className: 'text-center', orderable: false, searchable: false, render: (v, t, r) => aksiyonlar('dosya', r.id, r.ad, r.uzanti) }
        ],
        createdRow: function (row, data) {
            $(row).attr({ 'data-act': 'ac', 'data-id': data.id, 'data-ad': data.ad, 'data-onizle': data.onizlenebilir ? 1 : 0 });
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[5, 'desc']]
    }));

    const gorunumUygula = () => {
        $('.dosya-gorunum').removeClass('active').filter('[data-gorunum="' + gorunum + '"]').addClass('active');
        $('#gorunumListe').toggleClass('d-none', gorunum !== 'liste');
        $('#gorunumIcerik').toggleClass('d-none', gorunum === 'liste');
    };

    const yenile = () => {
        konumGuncelle();
        gorunumUygula();
        if (gorunum === 'liste') {
            $('#dosyaYol').html(secili === 'tum' ? '' : '<a href="#" class="yol-link" data-id="tum">Tüm Dosyalar</a> <i class="bx bx-chevron-right"></i> ' + esc(klasorAdi(secili) || ''));
            table.ajax.reload(null, false);
        } else {
            icerikYukle();
        }
    };
    const hepsiniYenile = () => Promise.all([agacYukle(), ozetYukle()]).then(yenile);

    $('.dosya-gorunum').on('click', function () {
        gorunum = $(this).data('gorunum');
        try { localStorage.setItem('dosya_gorunum', gorunum); } catch (e) {}
        yenile();
    });

    $('#dosyaArama').on('input', function () {
        clearTimeout(aramaZamani);
        const v = this.value;
        aramaZamani = setTimeout(() => {
            if (gorunum === 'liste') table.search(v).draw();
            else icerikYukle();
        }, 300);
    });

    $('#btnTumDosyalar').on('click', function () { secili = 'tum'; agacCiz(); yenile(); });

    $(document).on('click', '.yol-link', function (e) {
        e.preventDefault();
        secili = String($(this).data('id'));
        agacCiz();
        yenile();
    });

    const ac = (id, ad, onizle) => {
        if (String(onizle) === '1') {
            const url = INDIR + '?id=' + encodeURIComponent(id) + '&inline=1';
            const goster = /\.pdf$/i.test(ad)
                ? '<iframe src="' + esc(url) + '" style="width:100%;height:75vh;border:0" title="' + esc(ad) + '"></iframe>'
                : '<div class="text-center p-3"><img src="' + esc(url) + '" alt="' + esc(ad) + '" style="max-width:100%;max-height:75vh"></div>';
            $('#onizlemeBaslik').text(ad);
            $('#onizlemeIcerik').html(goster);
            modal('onizlemeModal').show();
        } else {
            window.location.href = INDIR + '?id=' + encodeURIComponent(id);
        }
    };

    $(document).on('click', '#dosyaListCard [data-act="ac"] td:not(:last-child), #gorunumIcerik [data-act="ac"]', function (e) {
        if ($(e.target).closest('.action-btn-group').length) return;
        const el = $(this).closest('[data-act="ac"]');
        ac(el.data('id'), String(el.data('ad')), el.data('onizle'));
    });
    $(document).on('click', '#gorunumIcerik [data-act="klasor-ac"]', function (e) {
        if ($(e.target).closest('.action-btn-group').length) return;
        secili = String($(this).data('id'));
        agacCiz();
        yenile();
    });
    $('#onizlemeModal').on('hidden.bs.modal', () => $('#onizlemeIcerik').empty());

    const aksiyon = (el) => $(el).closest('.action-btn-group');

    $(document).on('click', '.act-indir', function () {
        window.location.href = INDIR + '?id=' + encodeURIComponent(aksiyon(this).data('id'));
    });

    $(document).on('click', '.act-ad', function () {
        const g = aksiyon(this);
        duzenlenen = { tur: g.data('tur'), id: String(g.data('id')), mod: 'yeniden' };
        $('#klasorAdBaslik').text(g.data('tur') === 'klasor' ? 'Klasörü Yeniden Adlandır' : 'Dosyayı Yeniden Adlandır');
        $('#ad_girdi').val(g.data('ad'));
        modal('klasorAdModal').show();
    });

    const yeniKlasor = () => {
        duzenlenen = { tur: 'klasor', id: secili === 'tum' ? null : secili, mod: 'yeni' };
        $('#klasorAdBaslik').text('Yeni Klasör');
        $('#ad_girdi').val('');
        modal('klasorAdModal').show();
    };
    $('#btnYeniKlasor').on('click', yeniKlasor);
    $('#klasorAdModal').on('shown.bs.modal', () => $('#ad_girdi').trigger('focus'));
    $('#ad_girdi').on('keydown', (e) => { if (e.key === 'Enter') $('#btnAdKaydet').trigger('click'); });

    $('#btnAdKaydet').on('click', function () {
        if (!duzenlenen) return;
        const ad = $('#ad_girdi').val().trim();
        if (!ad) return hata('Ad boş olamaz.');
        let istek;
        if (duzenlenen.mod === 'yeni') istek = api('klasor-olustur', { parent_id: duzenlenen.id || '', ad });
        else if (duzenlenen.tur === 'klasor') istek = api('klasor-yeniden-adlandir', { id: duzenlenen.id, ad });
        else istek = api('dosya-yeniden-adlandir', { id: duzenlenen.id, ad });
        istek.then(() => { modal('klasorAdModal').hide(); basari('Kaydedildi'); hepsiniYenile(); }, hata);
    });

    $(document).on('click', '.act-tasi', function () {
        const g = aksiyon(this);
        duzenlenen = { tur: g.data('tur'), id: String(g.data('id')) };
        const secenekler = ['<option value="">Ana Dizin</option>'].concat(
            agacListe.filter((k) => !(g.data('tur') === 'klasor' && k.id === duzenlenen.id)).map((k) => '<option value="' + esc(k.id) + '">' + esc(k.ad) + '</option>')
        );
        $('#tasi_hedef').html(secenekler.join('')).val('').trigger('change');
        modal('tasiModal').show();
    });
    $('#btnTasiKaydet').on('click', function () {
        const hedef = $('#tasi_hedef').val() || '';
        const istek = duzenlenen.tur === 'klasor' ? api('klasor-tasi', { id: duzenlenen.id, hedef_id: hedef }) : api('dosya-tasi', { id: duzenlenen.id, hedef_id: hedef });
        istek.then(() => { modal('tasiModal').hide(); basari('Taşındı'); hepsiniYenile(); }, hata);
    });

    $(document).on('click', '.act-sil', function () {
        const g = aksiyon(this);
        const klasor = g.data('tur') === 'klasor';
        Swal.fire({
            icon: 'warning',
            title: klasor ? 'Klasör silinsin mi?' : 'Dosya silinsin mi?',
            text: klasor ? 'Klasör, alt klasörleri ve içindeki tüm dosyalar silinecek.' : String(g.data('ad')),
            showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#dc3545'
        }).then((s) => {
            if (!s.isConfirmed) return;
            if (klasor && secili === String(g.data('id'))) secili = 'tum';
            api(klasor ? 'klasor-sil' : 'dosya-sil', { id: g.data('id') }).then(() => { basari('Silindi'); hepsiniYenile(); }, hata);
        });
    });

    $(document).on('click', '.act-rol', function () {
        const g = aksiyon(this);
        duzenlenen = { id: String(g.data('id')) };
        api('klasor-rolleri', { id: duzenlenen.id }).then((r) => {
            $('#klasor_roller').val(r.secili.map(String)).trigger('change');
            modal('rolModal').show();
        }, hata);
    });
    $('#btnRolKaydet').on('click', function () {
        api('klasor-rolleri-kaydet', { id: duzenlenen.id, roller: $('#klasor_roller').val() || [] })
            .then(() => { modal('rolModal').hide(); basari('Erişim güncellendi'); hepsiniYenile(); }, hata);
    });

    const paylasimlariYukle = () => api('paylasim-listele', { id: duzenlenen.id }).then((liste) => {
        $('#paylasListe').html(liste.length ? liste.map((p) =>
            '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div>' +
            (p.aktif ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Aktif</span>' : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">' + (p.iptal ? 'İptal' : 'Süresi doldu') + '</span>') +
            ' <span class="ms-2">Bitiş: ' + tarihYaz(p.bitis) + ' · İndirme: ' + p.indirme + (p.limit ? '/' + p.limit : '') + (p.sifreli ? ' · Şifreli' : '') + '</span></div>' +
            (p.aktif ? '<button type="button" class="btn btn-sm btn-subtle-danger act-paylasim-iptal" data-id="' + esc(p.id) + '">İptal Et</button>' : '') + '</div>'
        ).join('') : '<span class="text-muted">Henüz bağlantı oluşturulmadı.</span>');
    }, hata);

    $(document).on('click', '.act-paylas', function () {
        const g = aksiyon(this);
        duzenlenen = { id: String(g.data('id')) };
        $('#paylasDosyaAdi').text(g.data('ad'));
        $('#paylasSonuc').addClass('d-none');
        $('#paylas_sifre, #paylas_limit').val('');
        $('#paylas_gun').val('7').trigger('change');
        paylasimlariYukle();
        modal('paylasModal').show();
    });
    $('#btnPaylasOlustur').on('click', function () {
        api('paylasim-olustur', { id: duzenlenen.id, gun: $('#paylas_gun').val(), sifre: $('#paylas_sifre').val(), limit: $('#paylas_limit').val() }).then((r) => {
            $('#paylasUrl').val(r.url);
            $('#paylasSonuc').removeClass('d-none');
            paylasimlariYukle();
        }, hata);
    });
    $('#btnUrlKopyala').on('click', function () {
        const alan = document.getElementById('paylasUrl');
        alan.select();
        (navigator.clipboard ? navigator.clipboard.writeText(alan.value) : Promise.resolve(document.execCommand('copy'))).then(() => basari('Bağlantı kopyalandı'));
    });
    $(document).on('click', '.act-paylasim-iptal', function () {
        api('paylasim-iptal', { id: $(this).data('id') }).then(paylasimlariYukle, hata);
    });

    const yukle = (dosyalar) => {
        if (!dosyalar || !dosyalar.length) return;
        if (!Y.yukle) return hata('Dosya yükleme yetkiniz yok.');
        const fd = new FormData();
        fd.append('action', 'yukle');
        fd.append('klasor_id', secili === 'tum' ? '' : secili);
        Array.from(dosyalar).forEach((f) => fd.append('dosyalar[]', f));

        Swal.fire({ title: 'Yükleniyor...', html: '<div class="progress"><div class="progress-bar" id="yuklemeCubuk" style="width:0%"></div></div>', allowOutsideClick: false, showConfirmButton: false });
        $.ajax({
            url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            xhr: function () {
                const x = $.ajaxSettings.xhr();
                if (x.upload) x.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) $('#yuklemeCubuk').css('width', Math.round(e.loaded / e.total * 100) + '%');
                });
                return x;
            }
        }).then((r) => {
            const hatalar = (r.data && r.data.hatalar) || [];
            if (hatalar.length) Swal.fire({ icon: 'warning', title: r.data.yuklenen + ' dosya yüklendi', text: hatalar.join('\n') });
            else basari(r.data.yuklenen + ' dosya yüklendi');
            hepsiniYenile();
        }, (xhr) => hata((xhr.responseJSON && xhr.responseJSON.message) || 'Yükleme başarısız.'));
    };

    $('#btnDosyaYukle').on('click', () => $('#dosyaInput').trigger('click'));
    $('#dosyaInput').on('change', function () { yukle(this.files); this.value = ''; });

    const bolge = document.getElementById('dosyaDropZone');
    ['dragenter', 'dragover'].forEach((ad) => bolge.addEventListener(ad, (e) => { e.preventDefault(); bolge.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach((ad) => bolge.addEventListener(ad, (e) => { e.preventDefault(); bolge.classList.remove('dragover'); }));
    bolge.addEventListener('drop', (e) => yukle(e.dataTransfer.files));

    hepsiniYenile();
});
