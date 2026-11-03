$(function () {
    const API = 'views/dosya-yonetimi/api.php';
    const INDIR = 'views/dosya-yonetimi/indir.php';
    const Y = window.DOSYA_YETKI || {};
    const CSRF = { 'X-CSRF-Token': window.DOSYA_CSRF || '' };

    let secili = 'tum';
    try { secili = sessionStorage.getItem('dosya_secili') || 'tum'; } catch (e) {}
    let uzantiGoster = true;
    try { uzantiGoster = localStorage.getItem('dosya_uzanti_goster') !== '0'; } catch (e) {}
    const gorunenAd = (ad, uzanti) => {
        const metin = String(ad == null ? '' : ad);
        const son = '.' + String(uzanti || '').toLowerCase();
        return (!uzantiGoster && son.length > 1 && metin.toLowerCase().endsWith(son)) ? metin.slice(0, -son.length) : metin;
    };
    const ozelSecim = () => secili === 'tum' || secili.startsWith('bolum:');
    const anahtarKlasor = (a) => agacListe.find((k) => k.anahtar === a);
    const seciliId = () => (ozelSecim() ? null : ((anahtarKlasor(secili) || {}).id || null));
    let agacListe = [];
    let bolumler = ['dosyalarim'];
    let ortakYetki = false;
    let icerikBaglami = { seviye: 0, alan: 'kisisel' };
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

    const ikonTon = (u) => ({ pdf: 'danger', doc: 'primary', docx: 'primary', xls: 'success', xlsx: 'success', csv: 'success', jpg: 'info', jpeg: 'info', png: 'info' }[u] || 'secondary');
    const ikonKutu = (u) => '<span class="fi fi-' + ikonTon(u) + '">' + ikon(u) + '</span>';
    const klasorKutu = '<span class="fi fi-warning"><i class="bx bxs-folder"></i></span>';

    const hata = (mesaj) => Swal.fire({ icon: 'error', title: 'Hata', text: mesaj || 'İşlem tamamlanamadı.' });
    const basari = (mesaj) => Swal.fire({ icon: 'success', title: mesaj, timer: 1400, showConfirmButton: false });

    const api = (action, data) => $.ajax({
        url: API, type: 'POST', dataType: 'json', headers: CSRF, data: Object.assign({ action }, data || {})
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

    const aksiyonlar = (tur, id, ad, nesne) => {
        const sv = Number((nesne && nesne.seviye) || 0);
        const alan = (nesne && nesne.alan) || '';
        let h = '<div class="action-btn-group d-inline-flex gap-1" data-tur="' + tur + '" data-id="' + esc(id) + '" data-key="' + esc((nesne && nesne.anahtar) || '') + '" data-ad="' + esc(ad) + '" data-seviye="' + sv + '">';
        if (tur === 'dosya') {
            h += '<button type="button" class="btn btn-subtle-primary table-action-btn act-indir" title="İndir"><i class="bx bx-download"></i></button>';
            if (Y.paylas && sv >= 2) h += '<button type="button" class="btn btn-subtle-success table-action-btn act-paylas" title="Bağlantı ile Paylaş"><i class="bx bx-share-alt"></i></button>';
        } else if (Y.klasor && sv >= 3 && alan === 'ortak') {
            h += '<button type="button" class="btn btn-subtle-success table-action-btn act-rol" title="Rol Erişimi"><i class="bx bx-shield-quarter"></i></button>';
        }
        if (Y.paylas && sv >= 2) h += '<button type="button" class="btn btn-subtle-info table-action-btn act-erisim" title="Kullanıcılarla Paylaş"><i class="bx bx-user-plus"></i></button>';
        if (Y.duzenle && sv >= 2) h += '<button type="button" class="btn btn-subtle-warning table-action-btn act-ad" title="Yeniden Adlandır"><i class="bx bx-edit"></i></button>';
        if (Y.duzenle && sv >= (tur === 'klasor' ? 3 : 2)) h += '<button type="button" class="btn btn-subtle-secondary table-action-btn act-tasi" title="Taşı"><i class="bx bx-move"></i></button>';
        if (Y.sil && sv >= 3) h += '<button type="button" class="btn btn-subtle-danger table-action-btn act-sil" title="Sil"><i class="bx bx-trash"></i></button>';
        return h + '</div>';
    };

    const ozetYukle = () => api('ozet').then((o) => {
        $('#stat_toplam_dosya').text(o.toplam_dosya);
        $('#stat_toplam_klasor').text(o.toplam_klasor);
        $('#stat_bu_ay').text(o.bu_ay);
        $('#stat_toplam_boyut').text(boyutYaz(o.toplam_boyut));
    });

    const BOLUM_ADLARI = {
        dosyalarim: ['bx-user', 'text-primary', 'Dosyalarım'],
        paylasilan: ['bx-share-alt', 'text-info', 'Benimle Paylaşılanlar'],
        ortak: ['bx-buildings', 'text-success', 'Ortak Alan']
    };

    const siraKaydet = (kap, secici) => {
        const idler = $(kap).children(secici).map(function () { return $(this).attr('data-fid'); }).get();
        if (idler.length < 2) return;
        api('klasor-sirala', { idler }).then(() => agacYukle().then(() => { if (gorunum !== 'liste') icerikYukle(); }), (m) => { hata(m); hepsiniYenile(); });
    };

    const siralamaBaslat = (kap, secici, tutamac) => {
        if (!Y.duzenle || typeof Sortable === 'undefined') return;
        Sortable.create(kap, {
            animation: 150,
            draggable: secici,
            handle: tutamac || undefined,
            filter: '.tg, .action-btn-group',
            preventOnFilter: false,
            ghostClass: 'surukleniyor',
            onEnd: (e) => { if (e.oldIndex !== e.newIndex) siraKaydet(kap, secici); }
        });
    };

    const VARSAYILAN_BOLUMLER = ['dosyalarim', 'paylasilan', 'ortak'];
    const bolumSirasi = () => {
        let kayitli = [];
        try { kayitli = JSON.parse(localStorage.getItem('dosya_bolum_sirasi') || '[]'); } catch (e) {}
        const sira = (Array.isArray(kayitli) ? kayitli : []).filter((b) => VARSAYILAN_BOLUMLER.includes(b));
        VARSAYILAN_BOLUMLER.forEach((b) => { if (!sira.includes(b)) sira.push(b); });
        return sira;
    };

    const acik = new Set();
    const ustleriAc = (id) => {
        let k = anahtarKlasor(id);
        while (k && k.ust_anahtar) { acik.add(k.ust_anahtar); k = anahtarKlasor(k.ust_anahtar); }
    };

    const agacCiz = () => {
        try { sessionStorage.setItem('dosya_secili', secili); } catch (e) {}
        const cocuklar = {};
        agacListe.forEach((k) => { if (k.ust_anahtar) (cocuklar[k.ust_anahtar] = cocuklar[k.ust_anahtar] || []).push(k); });
        const dal = (liste) => {
            if (!liste.length) return '';
            return '<ul class="sirali">' + liste.map((k) => {
                const alt = cocuklar[k.anahtar] || [];
                const a = acik.has(k.anahtar);
                return '<li' + (Y.duzenle && k.seviye >= 2 ? ' data-fid="' + esc(k.id) + '"' : '') + '><div class="node' + (secili === k.anahtar ? ' active' : '') + '" data-id="' + esc(k.anahtar) + '">' +
                    (alt.length ? '<span class="tg" data-tg="' + esc(k.anahtar) + '"><i class="bx bx-chevron-' + (a ? 'down' : 'right') + '"></i></span>' : '<span class="tg"></span>') +
                    '<i class="bx ' + (secili === k.anahtar || a ? 'bxs-folder-open' : 'bxs-folder') + ' text-warning"></i><span class="ad">' + esc(k.ad) + '</span><span class="cnt">' + k.dosya_sayisi + '</span></div>' +
                    (a ? dal(alt) : '') + '</li>';
            }).join('') + '</ul>';
        };
        let h = '<ul><li><div class="node' + (secili === 'tum' ? ' active' : '') + '" data-id="tum"><span class="tg"></span><i class="bx bx-layer text-primary"></i><span class="ad">Tüm Dosyalar</span></div></li></ul><div class="agac-ayrac"></div>';
        h += '<div id="bolumSira">';
        bolumSirasi().filter((b) => bolumler.includes(b)).forEach((b) => {
            const kokler = agacListe.filter((k) => k.kok && k.bolum === b);
            const m = BOLUM_ADLARI[b];
            h += '<ul class="bolum-ul" data-bolum="' + b + '"><li><div class="node bolum' + (secili === 'bolum:' + b ? ' active' : '') + '" data-id="bolum:' + b + '"><span class="tg"></span><i class="bx ' + m[0] + ' ' + m[1] + '"></i><span class="ad">' + m[2] + '</span><span class="cnt">' + kokler.length + '</span></div>' + dal(kokler) + '</li></ul>';
        });
        $('#dosyaAgac').html(h + '</div>');
        const bolumKap = document.getElementById('bolumSira');
        if (bolumKap && typeof Sortable !== 'undefined') {
            Sortable.create(bolumKap, {
                animation: 150,
                draggable: '.bolum-ul',
                handle: '.node.bolum',
                filter: '.tg',
                preventOnFilter: false,
                ghostClass: 'surukleniyor',
                onEnd: () => {
                    const yeni = $(bolumKap).children('.bolum-ul').map(function () { return $(this).attr('data-bolum'); }).get();
                    try { localStorage.setItem('dosya_bolum_sirasi', JSON.stringify(yeni)); } catch (e) {}
                }
            });
        }
        $('#dosyaAgac ul.sirali').each(function () { siralamaBaslat(this, 'li[data-fid]', null); });
    };

    const agacYukle = () => api('agac').then((r) => {
        agacListe = r.klasorler;
        bolumler = r.bolumler || ['dosyalarim'];
        ortakYetki = Boolean(r.ortak_alan_yetkisi);
        if (secili !== 'tum' && !secili.startsWith('bolum:') && !anahtarKlasor(secili)) secili = 'tum';
        if (secili.startsWith('bolum:') && !bolumler.includes(secili.slice(6))) secili = 'tum';
        if (!ozelSecim()) { acik.add(secili); ustleriAc(secili); }
        agacCiz();
    });

    $('#dosyaAgac').on('click', '.tg[data-tg]', function (e) {
        e.stopPropagation();
        const id = String($(this).data('tg'));
        if (acik.has(id)) acik.delete(id); else acik.add(id);
        agacCiz();
    });

    $('#dosyaAgac').on('click', '.node', function () {
        secili = String($(this).data('id'));
        acik.add(secili);
        ustleriAc(secili);
        agacCiz();
        yenile();
    });

    const klasorAdi = (a) => (anahtarKlasor(a) || {}).ad;
    const secimBolumu = () => {
        if (secili.startsWith('bolum:')) return secili.slice(6);
        const k = anahtarKlasor(secili);
        return k ? k.bolum : 'dosyalarim';
    };
    const konumAdi = () => (secili.startsWith('bolum:') ? BOLUM_ADLARI[secili.slice(6)][2] : (klasorAdi(secili) || ''));
    const konumGuncelle = () => {
        let ad = 'Tüm dosyalar';
        if (secili.startsWith('bolum:')) ad = BOLUM_ADLARI[secili.slice(6)][2];
        else if (secili !== 'tum') ad = klasorAdi(secili) || 'Klasör';
        $('#dosyaKonumBilgi').text(ad);
    };

    const dosyaAttr = (d) => 'data-act="ac" data-id="' + esc(d.id) + '" data-ad="' + esc(d.ad) + '" data-onizle="' + esc(d.onizleme || '') + '"';

    const dosyaSatirHtml = (d) =>
        '<div class="dosya-row" ' + dosyaAttr(d) + '>' + ikonKutu(d.uzanti) +
        '<span class="ad" title="' + esc(d.ad) + '">' + esc(gorunenAd(d.ad, d.uzanti)) + '<small class="tur-etiket">' + esc(String(d.uzanti).toUpperCase()) + '</small></span>' +
        '<span class="kol kol-boyut">' + boyutYaz(d.boyut) + '</span><span class="kol kol-kim">' + esc(d.yukleyen || '-') + '</span>' +
        '<span class="kol kol-tarih">' + tarihYaz(d.tarih) + '</span><span class="kol-act">' + aksiyonlar('dosya', d.id, d.ad, d) + '</span></div>';

    const fidAttr = (k) => (Y.duzenle && k.seviye >= 2 ? ' data-fid="' + esc(k.id) + '"' : '');
    const grip = (k) => (Y.duzenle && k.seviye >= 2 ? '<span class="surukle" title="Sıralamak için sürükleyin"><i class="bx bx-grid-vertical"></i></span>' : '');

    const klasorSatirHtml = (k) =>
        '<div class="dosya-row" data-act="klasor-ac" data-key="' + esc(k.anahtar) + '"' + fidAttr(k) + '>' + grip(k) + klasorKutu +
        '<span class="ad">' + esc(k.ad) + '<small>Klasör</small></span><span class="kol kol-boyut">' + k.dosya_sayisi + ' dosya</span>' +
        '<span class="kol kol-kim"></span><span class="kol kol-tarih"></span><span class="kol-act">' + aksiyonlar('klasor', k.id, k.ad, k) + '</span></div>';

    const dosyaKartHtml = (d) =>
        '<div class="col-6 col-md-4 col-xl-3"><div class="dosya-tile" ' + dosyaAttr(d) + '>' +
        '<div class="d-flex justify-content-between align-items-start">' + ikonKutu(d.uzanti) + '<span class="tur-etiket badge bg-' + ikonTon(d.uzanti) + '-subtle text-' + ikonTon(d.uzanti) + ' border border-' + ikonTon(d.uzanti) + '-subtle rounded-pill font-size-11">' + esc(String(d.uzanti).toUpperCase()) + '</span></div>' +
        '<span class="ad">' + esc(gorunenAd(d.ad, d.uzanti)) + '</span>' +
        '<span class="text-muted font-size-12">' + boyutYaz(d.boyut) + ' · ' + tarihYaz(d.tarih).split(' ')[0] + '</span>' + aksiyonlar('dosya', d.id, d.ad, d) + '</div></div>';

    const klasorKartHtml = (k) =>
        '<div class="col-6 col-md-4 col-xl-3"' + fidAttr(k) + '><div class="dosya-tile" data-act="klasor-ac" data-key="' + esc(k.anahtar) + '"><div class="d-flex justify-content-between align-items-start">' + klasorKutu + grip(k) + '</div>' +
        '<span class="ad">' + esc(k.ad) + '</span><span class="text-muted font-size-12">' + k.dosya_sayisi + ' dosya</span>' + aksiyonlar('klasor', k.id, k.ad, k) + '</div></div>';

    const BOS_MESAJ = {
        dosyalarim: 'Kişisel alanınız boş. Dosya sürükleyip bırakın ya da “Dosya Yükle” ile ekleyin.',
        paylasilan: 'Henüz sizinle paylaşılan bir klasör veya dosya yok.',
        ortak: 'Ortak alanda klasör yok. Yetkiniz varsa “Yeni Klasör” ile başlayın.'
    };

    const icerikYukle = () => {
        const arama = $('#dosyaArama').val().trim();
        const bolum = secimBolumu();
        const hedef = ozelSecim() ? 'kok' : (seciliId() || 'kok');
        return api('klasor-icerik', { klasor_id: hedef, arama, bolum }).then((r) => {
            icerikBaglami = { seviye: r.seviye, alan: r.alan };
            const yol = ['<a href="#" class="yol-link" data-id="bolum:' + esc(bolum) + '"><i class="bx ' + BOLUM_ADLARI[bolum][0] + '"></i> ' + esc(BOLUM_ADLARI[bolum][2]) + '</a>'];
            (r.yol || []).forEach((p) => yol.push('<a href="#" class="yol-link" data-id="' + esc(p.anahtar) + '">' + esc(p.ad) + '</a>'));
            $('#dosyaYol').html(r.aranan ? '<span class="text-muted"><i class="bx bx-search"></i> Arama sonuçları</span>' : yol.join('<i class="bx bx-chevron-right"></i>'));
            $('#dosyaKonumBilgi').text((r.klasorler || []).length + ' klasör · ' + (r.dosyalar || []).length + ' dosya');

            let h = '';
            if (gorunum === 'izgara') {
                h = ((r.klasorler || []).length ? '<div class="row g-3 mb-3 klasor-sira">' + r.klasorler.map(klasorKartHtml).join('') + '</div>' : '') +
                    ((r.dosyalar || []).length ? '<div class="row g-3">' + r.dosyalar.map(dosyaKartHtml).join('') + '</div>' : '');
            } else {
                h = '<div class="dosya-row dosya-row-head"><span class="fi-bos"></span><span class="ad">Ad</span><span class="kol kol-boyut">Boyut</span><span class="kol kol-kim">Yükleyen</span><span class="kol kol-tarih">Tarih</span><span class="kol-act"></span></div>' +
                    ((r.klasorler || []).length ? '<div class="klasor-sira">' + r.klasorler.map(klasorSatirHtml).join('') + '</div>' : '') + (r.dosyalar || []).map(dosyaSatirHtml).join('');
            }
            if (!(r.klasorler || []).length && !(r.dosyalar || []).length) {
                const yukleyebilir = Y.yukle && r.seviye >= 2;
                h = '<div class="dosya-bos"><span class="dosya-bos-ikon"><i class="bx bx-' + (r.aranan ? 'search-alt' : 'cloud-upload') + '"></i></span><strong>' + (r.aranan ? 'Sonuç bulunamadı' : 'Burada henüz bir şey yok') + '</strong><p>' + esc(r.aranan ? 'Aramanızla eşleşen dosya yok.' : (BOS_MESAJ[bolum] || '')) + '</p>' +
                    (yukleyebilir && !r.aranan ? '<button type="button" class="btn btn-primary btn-sm" id="btnBosYukle"><i class="bx bx-upload"></i> Dosya Seç</button>' : '') + '</div>';
            }
            $('#gorunumIcerik').html(h);
            $('#gorunumIcerik .klasor-sira').each(function () { siralamaBaslat(this, '[data-fid]', '.surukle'); });
        }, hata);
    };

    const table = $('#dosyaTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'POST',
            headers: CSRF,
            data: function (d) {
                d.action = 'liste';
                d.klasor_id = ozelSecim() ? secili : (seciliId() || 'tum');
            },
            error: function (xhr) { hata((xhr.responseJSON && xhr.responseJSON.message) || 'Liste yüklenemedi.'); }
        },
        columns: [
            { data: 'ad', render: (v, t, r) => '<span class="d-inline-flex align-items-center gap-2">' + ikonKutu(r.uzanti) + '<span class="fw-semibold">' + esc(gorunenAd(v, r.uzanti)) + '</span></span>' },
            { data: 'klasor_adi', render: (v) => esc(v || 'Ana Dizin') },
            { data: 'uzanti', render: (v) => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">' + esc(String(v).toUpperCase()) + '</span>' },
            { data: 'boyut', className: 'text-end', render: (v, t) => t === 'display' ? boyutYaz(v) : v },
            { data: 'yukleyen', render: (v) => esc(v || '-') },
            { data: 'tarih', render: (v, t) => t === 'display' ? tarihYaz(v) : v },
            { data: null, className: 'text-center', orderable: false, searchable: false, render: (v, t, r) => aksiyonlar('dosya', r.id, r.ad, r) }
        ],
        createdRow: function (row, data) {
            $(row).attr({ 'data-act': 'ac', 'data-id': data.id, 'data-ad': data.ad, 'data-onizle': data.onizleme || '' });
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[5, 'desc']]
    }));

    const gorunumUygula = () => {
        $('.dosya-gorunum').removeClass('active').attr('aria-pressed', 'false').filter('[data-gorunum="' + gorunum + '"]').addClass('active').attr('aria-pressed', 'true');
        $('#gorunumListe').toggleClass('d-none', gorunum !== 'liste');
        $('#gorunumIcerik').toggleClass('d-none', gorunum === 'liste');
    };

    const yenile = () => {
        konumGuncelle();
        gorunumUygula();
        if (gorunum === 'liste') {
            $('#dosyaYol').html(secili === 'tum' ? '' : '<a href="#" class="yol-link" data-id="tum">Tümü</a> <i class="bx bx-chevron-right"></i> ' + esc(konumAdi()));
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

    const LIB = 'assets/libs/dosya-onizleme/';
    const betikler = {};
    const betikYukle = (dosya) => betikler[dosya] || (betikler[dosya] = new Promise((coz, red) => {
        const el = document.createElement('script');
        el.src = LIB + dosya;
        el.onload = coz;
        el.onerror = () => { delete betikler[dosya]; red(new Error('Önizleme bileşeni yüklenemedi.')); };
        document.head.appendChild(el);
    }));

    const veriAl = (id) => fetch(INDIR + '?id=' + encodeURIComponent(id) + '&onizleme=1', { credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error('Dosya okunamadı.'); return r.arrayBuffer(); });

    const metneCevir = (buf) => {
        try { return new TextDecoder('utf-8', { fatal: true }).decode(buf); } catch (e) { return new TextDecoder('windows-1254').decode(buf); }
    };

    const temizle = (kap) => {
        kap.querySelectorAll('script, iframe, object, embed, link, meta, base, form').forEach((n) => n.remove());
        kap.querySelectorAll('*').forEach((n) => {
            Array.from(n.attributes).forEach((a) => { if (/^on/i.test(a.name)) n.removeAttribute(a.name); });
        });
        kap.querySelectorAll('a[href]').forEach((a) => {
            if (!/^(https?:|mailto:|#)/i.test(a.getAttribute('href') || '')) a.removeAttribute('href');
            else { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); }
        });
    };

    const tabloCiz = (kap, kitap) => {
        const sekmeler = document.createElement('div');
        sekmeler.className = 'onizleme-sekmeler';
        const alan = document.createElement('div');
        alan.className = 'onizleme-tablo';
        const goster = (ad) => {
            const ws = kitap.Sheets[ad];
            const satirlar = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' }).slice(0, 2000);
            const tablo = document.createElement('table');
            tablo.className = 'table table-sm table-bordered mb-0';
            const govde = document.createElement('tbody');
            satirlar.forEach((satir, i) => {
                const tr = document.createElement('tr');
                satir.slice(0, 60).forEach((hucre) => {
                    const td = document.createElement(i === 0 ? 'th' : 'td');
                    td.textContent = hucre == null ? '' : String(hucre);
                    tr.appendChild(td);
                });
                govde.appendChild(tr);
            });
            tablo.appendChild(govde);
            alan.replaceChildren(tablo);
            sekmeler.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b.dataset.sayfa === ad));
        };
        kitap.SheetNames.forEach((ad) => {
            const dugme = document.createElement('button');
            dugme.type = 'button';
            dugme.dataset.sayfa = ad;
            dugme.textContent = ad;
            dugme.addEventListener('click', () => goster(ad));
            sekmeler.appendChild(dugme);
        });
        kap.replaceChildren(sekmeler, alan);
        if (kitap.SheetNames.length) goster(kitap.SheetNames[0]);
        else alan.textContent = 'Bu dosyada görüntülenecek sayfa yok.';
    };

    let onizlemeSayac = 0;
    const onizlemeAc = (id, ad, tur) => {
        const sira = ++onizlemeSayac;
        const kap = document.getElementById('onizlemeIcerik');
        $('#onizlemeBaslik').text(ad);
        $('#btnOnizlemeIndir').off('click').on('click', () => { window.location.href = INDIR + '?id=' + encodeURIComponent(id); });
        kap.innerHTML = '<div class="text-center text-muted p-5"><span class="spinner-border spinner-border-sm me-2"></span>Önizleme hazırlanıyor...</div>';
        modal('onizlemeModal').show();
        const aktif = () => sira === onizlemeSayac;
        const hataGoster = (m) => { if (aktif()) kap.innerHTML = '<div class="dosya-bos"><span class="dosya-bos-ikon"><i class="bx bx-error"></i></span><strong>Önizleme açılamadı</strong><p>' + esc(m || 'Dosya indirerek açabilirsiniz.') + '</p></div>'; };

        if (tur === 'docx') {
            Promise.all([betikYukle('jszip.min.js'), betikYukle('docx-preview.min.js')])
                .then(() => veriAl(id))
                .then((buf) => {
                    if (!aktif()) return null;
                    const sayfa = document.createElement('div');
                    sayfa.className = 'onizleme-docx';
                    kap.replaceChildren(sayfa);
                    return window.docx.renderAsync(buf, sayfa, null, { className: 'docx', inWrapper: true, breakPages: true, ignoreLastRenderedPageBreak: true }).then(() => temizle(sayfa));
                })
                .catch((e) => hataGoster(e.message));
        } else if (tur === 'tablo') {
            Promise.all([betikYukle('xlsx.full.min.js'), veriAl(id)])
                .then(([, buf]) => {
                    if (!aktif()) return;
                    const csv = /\.csv$/i.test(ad);
                    const kitap = csv ? XLSX.read(metneCevir(buf), { type: 'string', sheetRows: 2001 }) : XLSX.read(buf, { type: 'array', cellDates: true, sheetRows: 2001 });
                    const sayfa = document.createElement('div');
                    kap.replaceChildren(sayfa);
                    tabloCiz(sayfa, kitap);
                })
                .catch((e) => hataGoster(e.message));
        } else if (tur === 'metin') {
            veriAl(id).then((buf) => {
                if (!aktif()) return;
                const pre = document.createElement('pre');
                pre.className = 'onizleme-metin';
                pre.textContent = metneCevir(buf);
                kap.replaceChildren(pre);
            }).catch((e) => hataGoster(e.message));
        }
    };

    const ac = (id, ad, tur) => {
        tur = String(tur || '');
        if (tur === 'pdf' || tur === 'goruntu') {
            const url = INDIR + '?id=' + encodeURIComponent(id) + '&inline=1';
            const goster = tur === 'pdf'
                ? '<iframe src="' + esc(url) + '" style="width:100%;height:75vh;border:0" title="' + esc(ad) + '"></iframe>'
                : '<div class="text-center p-3"><img src="' + esc(url) + '" alt="' + esc(ad) + '" style="max-width:100%;max-height:75vh"></div>';
            $('#onizlemeBaslik').text(ad);
            $('#btnOnizlemeIndir').off('click').on('click', () => { window.location.href = INDIR + '?id=' + encodeURIComponent(id); });
            $('#onizlemeIcerik').html(goster);
            modal('onizlemeModal').show();
        } else if (tur === 'docx' || tur === 'tablo' || tur === 'metin') {
            onizlemeAc(id, ad, tur);
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
        secili = String($(this).data('key'));
        acik.add(secili);
        ustleriAc(secili);
        agacCiz();
        yenile();
    });
    $('#onizlemeModal').on('hidden.bs.modal', () => { onizlemeSayac++; $('#onizlemeIcerik').empty(); });

    const aksiyon = (el) => $(el).closest('.action-btn-group');

    $(document).on('click', '.act-indir', function () {
        window.location.href = INDIR + '?id=' + encodeURIComponent(aksiyon(this).data('id'));
    });

    $(document).on('click', '.act-ad', function () {
        const g = aksiyon(this);
        duzenlenen = { tur: g.data('tur'), id: String(g.data('id')), mod: 'yeniden' };
        $('#klasorAdBaslik').text(g.data('tur') === 'klasor' ? 'Klasörü Yeniden Adlandır' : 'Dosyayı Yeniden Adlandır');
        const ilkAd = String(g.data('ad'));
        $('#ad_girdi').val(g.data('tur') === 'dosya' ? gorunenAd(ilkAd, ilkAd.split('.').pop()) : ilkAd);
        modal('klasorAdModal').show();
    });

    const yeniKlasor = () => {
        const bolum = secimBolumu();
        let parent = null;
        let alan = 'kisisel';
        if (secili !== 'tum' && !secili.startsWith('bolum:')) {
            parent = seciliId();
        } else if (secili === 'bolum:ortak') {
            if (!ortakYetki) return hata('Ortak alanda klasör oluşturma yetkiniz yok.');
            alan = 'ortak';
        } else if (secili === 'bolum:paylasilan') {
            return hata('Bu bölümde klasör oluşturulamaz. Önce Dosyalarım veya bir klasör seçin.');
        }
        duzenlenen = { tur: 'klasor', id: parent, alan, mod: 'yeni' };
        $('#klasorAdBaslik').text(alan === 'ortak' ? 'Yeni Ortak Klasör' : 'Yeni Klasör');
        $('#ad_girdi').val('');
        modal('klasorAdModal').show();
    };
    $('#btnYeniKlasor, #btnAgacYeni').on('click', yeniKlasor);
    $('#klasorAdModal').on('shown.bs.modal', () => $('#ad_girdi').trigger('focus'));
    $('#ad_girdi').on('keydown', (e) => { if (e.key === 'Enter') $('#btnAdKaydet').trigger('click'); });

    $('#btnAdKaydet').on('click', function () {
        if (!duzenlenen) return;
        const ad = $('#ad_girdi').val().trim();
        if (!ad) return hata('Ad boş olamaz.');
        let istek;
        if (duzenlenen.mod === 'yeni') istek = api('klasor-olustur', { parent_id: duzenlenen.id || '', ad, alan: duzenlenen.alan || 'kisisel' });
        else if (duzenlenen.tur === 'klasor') istek = api('klasor-yeniden-adlandir', { id: duzenlenen.id, ad });
        else istek = api('dosya-yeniden-adlandir', { id: duzenlenen.id, ad });
        istek.then(() => { modal('klasorAdModal').hide(); basari('Kaydedildi'); hepsiniYenile(); }, hata);
    });

    $(document).on('click', '.act-tasi', function () {
        const g = aksiyon(this);
        duzenlenen = { tur: g.data('tur'), id: String(g.data('id')), key: String(g.data('key') || '') };
        const secenekler = ['<option value="">Ana Dizin</option>'].concat(
            agacListe.filter((k) => k.seviye >= 2 && !(g.data('tur') === 'klasor' && k.anahtar === duzenlenen.key)).map((k) => '<option value="' + esc(k.id) + '">' + esc(k.ad) + '</option>')
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
            if (klasor && secili === String(g.data('key'))) secili = 'tum';
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

    const erisimYukle = () => api('erisim-listele', { tur: duzenlenen.tur, id: duzenlenen.id }).then((r) => {
        duzenlenen.tamYetki = r.tam_yetki;
        $('#erisim_seviye option[value="duzenle"]').prop('disabled', !r.tam_yetki);
        if (!r.tam_yetki) $('#erisim_seviye').val('goruntule');
        $('#erisim_seviye').trigger('change');
        $('#erisimListe').html(r.erisimler.length ? r.erisimler.map((e) =>
            '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><span class="fw-semibold">' + esc(e.kullanici) + '</span> <span class="badge bg-' + (e.seviye === 'duzenle' ? 'warning' : 'info') + '-subtle text-' + (e.seviye === 'duzenle' ? 'warning' : 'info') + ' border border-' + (e.seviye === 'duzenle' ? 'warning' : 'info') + '-subtle rounded-pill ms-1">' + (e.seviye === 'duzenle' ? 'Düzenleyebilir' : 'Görüntüleyebilir') + '</span></div>' +
            '<button type="button" class="btn btn-sm btn-subtle-danger act-erisim-kaldir" data-id="' + esc(e.id) + '">Kaldır</button></div>'
        ).join('') : '<span class="text-muted">Henüz kimseyle paylaşılmadı.</span>');
    }, hata);

    $(document).on('click', '.act-erisim', function () {
        const g = aksiyon(this);
        duzenlenen = { tur: g.data('tur'), id: String(g.data('id')) };
        $('#erisimNesneAdi').text(g.data('ad'));
        api('erisim-kullanicilar').then((liste) => {
            $('#erisim_kullanici').html('<option value="">Kullanıcı seçin</option>' + liste.map((u) => '<option value="' + u.id + '">' + esc(u.ad) + '</option>').join('')).val('').trigger('change');
            erisimYukle();
            modal('erisimModal').show();
        }, hata);
    });
    $('#btnErisimEkle').on('click', function () {
        const kullanici = $('#erisim_kullanici').val();
        if (!kullanici) return hata('Kullanıcı seçin.');
        api('erisim-ver', { tur: duzenlenen.tur, id: duzenlenen.id, user_id: kullanici, seviye: $('#erisim_seviye').val() }).then(() => {
            $('#erisim_kullanici').val('').trigger('change');
            erisimYukle();
            hepsiniYenile();
        }, hata);
    });
    $(document).on('click', '.act-erisim-kaldir', function () {
        api('erisim-kaldir', { id: $(this).data('id') }).then(() => { erisimYukle(); hepsiniYenile(); }, hata);
    });

    const yukle = (dosyalar) => {
        if (!dosyalar || !dosyalar.length) return;
        if (!Y.yukle) return hata('Dosya yükleme yetkiniz yok.');
        const fd = new FormData();
        fd.append('action', 'yukle');
        if (secili === 'bolum:paylasilan') return hata('Bu bölüme doğrudan dosya yüklenemez. Paylaşılan bir klasörü açıp içine yükleyin.');
        if (secili === 'bolum:ortak') {
            const klasorVar = agacListe.some((k) => k.kok && k.bolum === 'ortak');
            if (!klasorVar && !ortakYetki) return hata('Ortak alanda henüz klasör yok. Önce yetkili bir kullanıcının ortak klasör oluşturması gerekir.');
            return Swal.fire({
                icon: 'info',
                title: klasorVar ? 'Bir ortak klasör seçin' : 'Önce ortak klasör oluşturun',
                text: klasorVar ? 'Ortak alanda dosyalar klasörlerin içinde tutulur. Soldaki ağaçtan bir klasör seçip tekrar yükleyin.' : 'Ortak alanda dosyalar klasörlerin içinde tutulur. Şimdi bir ortak klasör oluşturabilirsiniz.',
                showCancelButton: ortakYetki && Y.klasor,
                showConfirmButton: ortakYetki && Y.klasor,
                confirmButtonText: 'Klasör Oluştur',
                cancelButtonText: 'Vazgeç'
            }).then((s) => { if (s.isConfirmed) yeniKlasor(); });
        }
        fd.append('klasor_id', seciliId() || '');
        Array.from(dosyalar).forEach((f) => fd.append('dosyalar[]', f));

        Swal.fire({ title: 'Yükleniyor...', html: '<div class="progress"><div class="progress-bar" id="yuklemeCubuk" style="width:0%"></div></div>', allowOutsideClick: false, showConfirmButton: false });
        $.ajax({
            url: API, type: 'POST', headers: CSRF, data: fd, processData: false, contentType: false, dataType: 'json',
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

    $(document).on('click', '#btnBosYukle', () => $('#dosyaInput').trigger('click'));
    $('#btnDosyaYukle').on('click', () => $('#dosyaInput').trigger('click'));
    $('#dosyaInput').on('change', function () { yukle(this.files); this.value = ''; });

    const bolge = document.getElementById('dosyaDropZone');
    ['dragenter', 'dragover'].forEach((ad) => bolge.addEventListener(ad, (e) => { e.preventDefault(); bolge.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach((ad) => bolge.addEventListener(ad, (e) => { e.preventDefault(); bolge.classList.remove('dragover'); }));
    bolge.addEventListener('drop', (e) => yukle(e.dataTransfer.files));

    const uzantiUygula = () => {
        $('#dosyaListCard').toggleClass('uzanti-gizli', !uzantiGoster);
        $('#dosyaUzantiAnahtar').prop('checked', uzantiGoster);
        table.column(2).visible(uzantiGoster, false);
    };
    uzantiUygula();
    $('#dosyaUzantiAnahtar').on('change', function () {
        uzantiGoster = this.checked;
        try { localStorage.setItem('dosya_uzanti_goster', uzantiGoster ? '1' : '0'); } catch (e) {}
        uzantiUygula();
        yenile();
    });

    hepsiniYenile();
});
