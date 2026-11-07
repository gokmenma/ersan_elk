$(document).ready(function () {
    const API = 'views/site-yonetimi/api.php';
    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const tarihSaat = (d) => {
        if (!d) return '';
        const [t, s] = String(d).split(' ');
        return `${t.split('-').reverse().join('.')} ${(s || '').substring(0, 5)}`;
    };
    const rozet = {
        taslak: '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Taslak</span>',
        yayinda: '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Yayında</span>'
    };

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('sitedash-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('sitedash-summary-hidden');
        $('html').toggleClass('sitedash-summary-hidden', gizle);
        try { localStorage.setItem('sitedash_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    const ciz = function (res) {
        if (res.status !== 'success') {
            Swal.fire('Hata', res.message || 'Veriler alınamadı.', 'error');
            return;
        }
        const izin = res.izin || {};
        $('[data-izin="hizmet"]').toggleClass('d-none', !izin.hizmet);
        $('[data-izin="ilan"]').toggleClass('d-none', !izin.ilan);
        $('#btnYeniHizmetLink').toggleClass('d-none', !izin.hizmet);
        $('[data-izin="mesaj"]').toggleClass('d-none', !izin.mesaj);
        $('[data-izin="proje"]').toggleClass('d-none', !izin.proje);
        $('[data-izin="ayar"]').toggleClass('d-none', !izin.ayar);
        ['referans', 'sss', 'istatistik'].forEach((k) => {
            $(`[data-izin="${k}"]`).toggleClass('d-none', !izin[k]);
            const o = (res.icerik || {})[k];
            if (o) { $(`#${k}Ozet`).text(`${o.yayinda} yayında, ${o.taslak} taslak`); }
        });
        $('#hizliBos').toggleClass('d-none', !!(izin.hizmet || izin.ilan || izin.mesaj || izin.proje || izin.referans || izin.sss || izin.istatistik || izin.ayar));
        if (res.proje) {
            $('#projeOzet').text(`${res.proje.yayinda} yayında, ${res.proje.taslak} taslak proje`);
        }
        if (res.mesaj) {
            $('#mesajOzet').text(`${res.mesaj.toplam} mesaj, ${res.mesaj.yeni} okunmamış`);
            $('#mesajYeniRozet').text(res.mesaj.yeni).toggleClass('d-none', !res.mesaj.yeni);
        }

        const h = res.hizmet;
        ['toplam', 'yayinda', 'taslak', 'gorselsiz'].forEach((k) => $('#stat_' + k).text(h ? h[k] : '-'));

        if (res.kariyer) {
            $('#kariyerOzet').text(`${res.kariyer.yayinda} yayında, ${res.kariyer.taslak} taslak ilan`);
        }

        const liste = res.son_hizmetler || [];
        if (!izin.hizmet) {
            $('#sonHizmetler').html('<div class="sd-bos">Hizmetleri görüntüleme yetkiniz bulunmuyor.</div>');
        } else if (!liste.length) {
            $('#sonHizmetler').html('<div class="sd-bos">Henüz hizmet eklenmemiş.</div>');
        } else {
            $('#sonHizmetler').html(liste.map((x) => `<div class="sd-satir">
                ${x.gorsel ? `<img class="sd-thumb" src="${esc(x.gorsel)}" alt="">` : '<span class="sd-thumb d-inline-flex align-items-center justify-content-center text-muted"><i class="bx bx-image"></i></span>'}
                <div class="flex-grow-1"><a href="index.php?p=site-yonetimi/hizmetler" class="fw-semibold">${esc(x.baslik)}</a><div class="text-muted font-size-12">${esc(tarihSaat(x.guncelleme))}</div></div>
                ${rozet[x.durum] || ''}
            </div>`).join(''));
        }

        const islemler = res.son_islemler || [];
        if (izin.hizmet) {
            $('#sonIslemlerKart').removeClass('d-none');
            $('#sonIslemler').html(islemler.length ? islemler.map((x) => `<div class="sd-satir">
                <span class="ico bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;"><i class="bx bx-history"></i></span>
                <div class="flex-grow-1"><div class="fw-semibold font-size-13">${esc(x.aciklama)}</div><div class="text-muted font-size-12">${esc(x.kullanici || 'Sistem')} · ${esc(tarihSaat(x.tarih))}</div></div>
            </div>`).join('') : '<div class="sd-bos">Henüz kayıtlı işlem yok.</div>');
        }
    };

    const ilk = window.siteDashIlk || Promise.resolve(null);
    ilk.then(function (res) {
        if (res) { ciz(res); return; }
        $.post(API, { action: 'dashboard-data' }, ciz, 'json').fail(() => Swal.fire('Hata', 'Veriler alınamadı.', 'error'));
    });
});
