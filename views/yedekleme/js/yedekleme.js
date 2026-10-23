$(function () {
    const API = 'views/yedekleme/api.php';
    const csrf = $('#yedeklemeSayfasi').data('csrf');
    const toggleBtn = $('#btnToggleSummaryCards');
    let durumFiltresi = 'all';
    let ozet = null;
    let isZamanlayici = null;
    let sonIsDurumu = null;
    let driveYenile = false;

    const esc = (deger) => $('<div>').text(deger == null ? '' : String(deger)).html();

    const boyutYaz = (bayt) => {
        if (bayt == null) return '-';
        const birimler = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        let n = Number(bayt);
        while (n >= 1024 && i < birimler.length - 1) {
            n /= 1024;
            i++;
        }
        return n.toLocaleString('tr-TR', { maximumFractionDigits: i > 1 ? 2 : 0 }) + ' ' + birimler[i];
    };

    const tarihYaz = (zaman) => {
        if (!zaman) return '-';
        const [g, s] = String(zaman).split(' ');
        const [y, a, d] = g.split('-');
        return `${d}.${a}.${y} ${(s || '').substring(0, 5)}`;
    };

    const onceYaz = (zaman) => {
        if (!zaman) return '-';
        const fark = (Date.now() - new Date(String(zaman).replace(' ', 'T')).getTime()) / 1000;
        if (fark < 60) return 'az önce';
        if (fark < 3600) return Math.floor(fark / 60) + ' dk önce';
        if (fark < 86400) return Math.floor(fark / 3600) + ' saat önce';
        return Math.floor(fark / 86400) + ' gün önce';
    };

    const rozet = (renk, metin, ikon) =>
        `<span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${ikon ? `<i class="bx ${ikon} me-1"></i>` : ''}${esc(metin)}</span>`;

    const testRozeti = (test) => {
        if (!test) return rozet('secondary', 'Test edilmedi');
        switch (test.sonuc) {
            case 'basarili': return rozet('success', 'Başarılı', 'bx-check');
            case 'sayimsiz': return rozet('info', 'Yüklendi', 'bx-info-circle');
            case 'farkli': return rozet('warning', 'Fark var', 'bx-error');
            default: return rozet('danger', 'Hatalı', 'bx-x');
        }
    };

    const testMetni = (test) => ({ basarili: 'Başarılı', sayimsiz: 'Yüklendi', farkli: 'Fark var', hata: 'Hatalı' }[test?.sonuc] || 'Test edilmedi');

    const konumMetni = (satir) => satir.yerel && satir.drive ? 'Yerel + Drive' : (satir.yerel ? 'Yalnız Yerel' : 'Yalnız Drive');

    const ozetGorunumunuGuncelle = () => {
        const gizli = $('html').hasClass('yedekleme-summary-hidden');
        toggleBtn.attr('aria-expanded', String(!gizli))
            .attr('title', gizli ? 'Özet Kartları Göster' : 'Özet Kartları Gizle')
            .attr('aria-label', gizli ? 'Özet kartları göster' : 'Özet kartları gizle');
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    ozetGorunumunuGuncelle();

    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('yedekleme-summary-hidden');
        $('html').toggleClass('yedekleme-summary-hidden', gizle);
        try { localStorage.setItem('yedekleme_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        ozetGorunumunuGuncelle();
    });

    $.fn.dataTable.ext.search.push(function (settings, data, index) {
        if (settings.nTable.id !== 'yedeklemeTable' || durumFiltresi === 'all') return true;
        const satir = settings.aoData[index]._aData;
        if (durumFiltresi === 'tam') return satir.tip === 'tam';
        if (durumFiltresi === 'drive_eksik') return satir.yerel && !satir.drive;
        return true;
    });

    const table = $('#yedeklemeTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: false,
        order: [[1, 'desc']],
        ajax: {
            url: API,
            type: 'POST',
            data: function (d) {
                d.action = 'liste';
                d.csrf_token = csrf;
                d.drive_yenile = driveYenile ? '1' : '0';
            },
            dataSrc: function (json) {
                driveYenile = false;
                if (json.status !== 'success') {
                    Swal.fire('Hata', json.message || 'Yedek listesi alınamadı.', 'error');
                    return [];
                }
                ozet = json.ozet;
                ozetiCiz(json);
                if (ozet.is) isDurumunuCiz(ozet.is, true);
                return json.data;
            },
            error: function (xhr) {
                Swal.fire('Hata', xhr.responseJSON?.message || 'Yedek listesi alınamadı.', 'error');
            }
        },
        columns: [
            {
                data: 'tip',
                render: (v, type) => type === 'display'
                    ? (v === 'tam' ? rozet('primary', 'Tam', 'bx-data') : rozet('secondary', 'Artımlı', 'bx-git-commit'))
                    : (v === 'tam' ? 'Tam' : 'Artımlı')
            },
            {
                data: 'zaman',
                render: (v, type) => type === 'display' || type === 'filter' ? tarihYaz(v) : v
            },
            {
                data: 'ad',
                render: (v, type) => type === 'display' ? `<span class="font-size-12 text-muted">${esc(v)}</span>` : v
            },
            {
                data: 'boyut',
                className: 'text-end',
                render: (v, type) => type === 'display' ? boyutYaz(v) : v
            },
            {
                data: null,
                render: (v, type, satir) => {
                    if (type !== 'display') return konumMetni(satir);
                    let html = '';
                    if (satir.yerel) html += `<span class="yedek-konum-badge">${rozet('primary', 'Yerel', 'bx-server')}</span>`;
                    if (satir.drive) html += `<span class="yedek-konum-badge">${rozet('success', 'Drive', 'bxl-google')}</span>`;
                    return html;
                }
            },
            {
                data: 'test',
                render: (v, type) => type === 'display' ? testRozeti(v) : testMetni(v)
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                width: '130px',
                render: (v, type, satir) => {
                    const ad = esc(satir.ad);
                    let html = `<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">
                        <button type="button" class="btn btn-subtle-primary table-action-btn yedek-detay" data-dosya="${ad}" title="Detay"><i class="bx bx-file-find font-size-14"></i></button>`;
                    if (satir.yerel) {
                        html += `<button type="button" class="btn btn-subtle-warning table-action-btn yedek-test" data-dosya="${ad}" title="Geri Yükleme Testi"><i class="bx bx-test-tube font-size-14"></i></button>
                            <button type="button" class="btn btn-subtle-info table-action-btn yedek-indir" data-dosya="${ad}" title="İndir"><i class="bx bx-download font-size-14"></i></button>`;
                    } else {
                        html += `<button type="button" class="btn btn-subtle-success table-action-btn yedek-drive-al" data-dosya="${ad}" title="Drive'dan Yerele Al"><i class="bx bx-cloud-download font-size-14"></i></button>`;
                    }
                    return html + '</div>';
                }
            }
        ]
    }));

    function ozetiCiz(json) {
        const o = json.ozet;
        $('#kpiToplam').text(o.toplam.toLocaleString('tr-TR'));
        $('#kpiToplamAlt').text(`Tam: ${o.tam_adet} | Artımlı: ${o.artimli_adet} | Yerel: ${boyutYaz(o.yerel_boyut)}`);

        if (o.son_yedek) {
            $('#kpiSonYedek').text(onceYaz(o.son_yedek.zaman));
            $('#kpiSonYedekAlt').text(`${o.son_yedek.tip === 'tam' ? 'Tam' : 'Artımlı'} · ${tarihYaz(o.son_yedek.zaman)}`);
            const saat = (Date.now() - new Date(o.son_yedek.zaman.replace(' ', 'T')).getTime()) / 3600000;
            $('#kpiSonYedek').toggleClass('text-success', saat <= 26).toggleClass('text-danger', saat > 26);
        } else {
            $('#kpiSonYedek').text('Yok').removeClass('text-success').addClass('text-danger');
            $('#kpiSonYedekAlt').text('Henüz yedek alınmadı');
        }

        if (!o.drive_yapilandirildi) {
            $('#kpiDrive').text('Kapalı').attr('class', 'summary-kpi-value my-1 text-secondary');
            $('#kpiDriveAlt').text('.env yapılandırması bekleniyor');
        } else if (json.drive_durum === 'hata') {
            $('#kpiDrive').text('Erişilemedi').attr('class', 'summary-kpi-value my-1 text-danger');
            $('#kpiDriveAlt').text(json.drive_hata || 'Google Drive listesi alınamadı');
        } else {
            $('#kpiDrive').text(o.drive_adet.toLocaleString('tr-TR')).attr('class', 'summary-kpi-value my-1 ' + (o.drive_eksik > 0 ? 'text-warning' : 'text-success'));
            $('#kpiDriveAlt').text(o.drive_eksik > 0 ? `${o.drive_eksik} yerel yedek Drive'da yok` : `Senkron · ${o.son_drive ? onceYaz(o.son_drive.zaman) : '-'}`);
        }

        if (o.son_test) {
            $('#kpiTest').text(onceYaz(o.son_test.zaman));
            $('#kpiTestAlt').text(o.son_test.tablo_sayisi ? `${o.son_test.tablo_sayisi} tablo · ${o.son_test.sure} sn` : tarihYaz(o.son_test.zaman));
            $('#kpiTestRozet').replaceWith($(testRozeti(o.son_test)).attr('id', 'kpiTestRozet'));
        }

        const uyarilar = [];
        if (!o.drive_yapilandirildi) uyarilar.push("Google Drive uzak kopyası kapalı; yedekler yalnızca bu sunucuda duruyor. Kurulum: <code>cron/YEDEKLEME.md</code>");
        if (!o.test_vt_tanimli) uyarilar.push('Geri yükleme testi için <code>.env</code> içinde <code>YEDEK_TEST_VT</code> tanımlanmalı.');
        if (o.son_yedek && (Date.now() - new Date(o.son_yedek.zaman.replace(' ', 'T')).getTime()) / 3600000 > 26) uyarilar.push('Son 26 saattir yedek alınmamış; cron görevini kontrol edin.');
        if (o.bos_alan !== null && o.bos_alan < 2 * 1024 * 1024 * 1024) uyarilar.push(`Yedek dizininde boş alan az: ${boyutYaz(o.bos_alan)}`);
        $('#yedekUyari').toggleClass('d-none', uyarilar.length === 0).toggleClass('d-flex', uyarilar.length > 0)
            .html(uyarilar.length ? `<i class="bx bx-error font-size-18"></i><div>${uyarilar.join('<br>')}</div>` : '');

        $('#yedekListeAlt').text(json.drive_zaman ? `Drive listesi: ${tarihYaz(json.drive_zaman)}` : 'Yerel yedek dizini ve Google Drive birlikte listelenir');

        testGecmisiniCiz(o.test_gecmisi || []);
    }

    function testGecmisiniCiz(testler) {
        if (!testler.length) {
            $('#testGecmisi').html('<div class="text-muted font-size-12 py-2">Henüz test yapılmadı.</div>');
            return;
        }
        $('#testGecmisi').html(testler.map((t) => {
            const parcalar = [tarihYaz(t.zaman)];
            if (t.tablo_sayisi) parcalar.push(`${t.tablo_sayisi} tablo, ${Number(t.toplam_satir || 0).toLocaleString('tr-TR')} satır`);
            if (t.sure != null) parcalar.push(`${t.sure} sn`);
            return `
            <div class="yedek-test-satir">
                <div class="yedek-test-bilgi">
                    <div class="fw-semibold">${esc(parcalar.join(' · '))}</div>
                    <div class="dosya" title="${esc(t.dosya)}">${esc(t.dosya)}</div>
                    ${t.hata ? `<div class="hata">${esc(t.hata)}</div>` : ''}
                </div>
                <div class="flex-shrink-0">${testRozeti(t)}</div>
            </div>`;
        }).join(''));
    }

    function isDurumunuCiz(is, sayfaYukleme) {
        if (!is) return;
        const calisiyor = is.durum === 'calisiyor';
        const sonuclandi = sonIsDurumu === 'calisiyor' && !calisiyor;
        if (sayfaYukleme && !calisiyor && !$('#yedekIsKarti').hasClass('aktif')) return;

        $('#yedekIsKarti').addClass('aktif');
        $('#yedekIsBaslik').text(is.baslik + (is.parametre?.dosya ? ' — ' + is.parametre.dosya : ''));
        $('#yedekIsAlt').text(`Başladı: ${tarihYaz(is.basladi)}${is.bitti ? ' · Bitti: ' + tarihYaz(is.bitti) : ''}`);

        const durumlar = {
            calisiyor: ['info', 'Çalışıyor', 'bx-loader-alt bx-spin'],
            tamam: ['success', 'Tamamlandı', 'bx-check-circle'],
            hata: ['danger', 'Hata', 'bx-x-circle'],
            yarida_kaldi: ['danger', 'Yarıda kaldı', 'bx-error-circle']
        };
        const [renk, metin, ikon] = durumlar[is.durum] || durumlar.hata;
        $('#yedekIsRozet').attr('class', `badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold`).text(metin);
        $('#yedekIsIkon').attr('class', `p-2 bg-${renk}-subtle text-${renk} rounded-3 border border-${renk}-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0`)
            .html(`<i class="bx ${ikon} font-size-20"></i>`);
        $('#btnIsKartiKapat').toggleClass('d-none', calisiyor);

        const log = $('#yedekIsLog');
        const enAltta = log[0].scrollHeight - log.scrollTop() - log.outerHeight() < 30;
        log.html((is.log || []).map((s) => `<span class="log-zaman">${esc(s.z)}</span> <span class="log-${esc(s.s)}">${esc(s.m)}</span>`).join('\n'));
        if (enAltta) log.scrollTop(log[0].scrollHeight);

        $('.yedek-is-btn, .yedek-test, .yedek-drive-al').prop('disabled', calisiyor);

        if (calisiyor && !isZamanlayici) {
            isZamanlayici = setInterval(isDurumunuSorgula, 2000);
        }
        if (!calisiyor && isZamanlayici) {
            clearInterval(isZamanlayici);
            isZamanlayici = null;
        }
        if (sonuclandi) {
            showToast(`${is.baslik}: ${metin}`, is.durum === 'tamam' ? 'success' : 'error');
            table.ajax.reload(null, false);
        }
        sonIsDurumu = is.durum;
    }

    function isDurumunuSorgula() {
        $.post(API, { action: 'is-durumu', csrf_token: csrf }, function (res) {
            if (res.status === 'success' && res.is) isDurumunuCiz(res.is, false);
        }, 'json');
    }

    function isBaslat(is, dosya, onayMetni) {
        Swal.fire({
            title: 'Emin misiniz?',
            html: onayMetni,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Başlat',
            cancelButtonText: 'Vazgeç'
        }).then((secim) => {
            if (!secim.isConfirmed) return;
            $.post(API, { action: 'is-baslat', is: is, dosya: dosya || '', csrf_token: csrf }, function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Uyarı', res.message || 'İşlem başlatılamadı.', 'warning');
                    return;
                }
                showToast(res.message, 'success');
                sonIsDurumu = null;
                isDurumunuCiz(res.is, false);
                setTimeout(isDurumunuSorgula, 800);
            }, 'json').fail(function (xhr) {
                Swal.fire('Uyarı', xhr.responseJSON?.message || 'İşlem başlatılamadı.', 'warning');
            });
        });
    }

    const isOnaylari = {
        yedek_tam: 'Veritabanının <b>tam yedeği</b> alınacak. Büyük tablolarda birkaç dakika sürebilir; uygulama bu sırada çalışmaya devam eder.',
        yedek_artimli: 'Son yedekten bu yana değişen kayıtların <b>artımlı yedeği</b> alınacak.',
        drive_senkron: "Drive'da olmayan yerel yedekler şifrelenip <b>Google Drive</b>'a yüklenecek."
    };

    $('.yedek-is-btn').on('click', function () {
        const is = $(this).data('is');
        isBaslat(is, '', isOnaylari[is]);
    });

    $('#yedeklemeTable').on('click', '.yedek-test', function (e) {
        e.stopPropagation();
        const dosya = $(this).data('dosya');
        isBaslat('geri_yukleme_testi', dosya,
            `<div class="text-start font-size-13"><p class="mb-2"><code>${esc(dosya)}</code> yedeği (artımlıysa bağlı olduğu tam yedekle birlikte) <b>ayrı test veritabanına</b> yüklenecek ve tablo satır sayıları doğrulanacak.</p><p class="mb-0 text-muted">Canlı veritabanına dokunulmaz. Test sonunda test veritabanı boşaltılır.</p></div>`);
    });

    $('#yedeklemeTable').on('click', '.yedek-drive-al', function (e) {
        e.stopPropagation();
        const dosya = $(this).data('dosya');
        isBaslat('drive_indir', dosya, `<code>${esc(dosya)}</code> Google Drive'dan indirilip şifresi çözülerek yerel yedek dizinine alınacak.`);
    });

    $('#yedeklemeTable').on('click', '.yedek-indir', function (e) {
        e.stopPropagation();
        const dosya = $(this).data('dosya');
        Swal.fire({
            title: 'Yedek indirilsin mi?',
            html: '<div class="text-start font-size-13">Bu dosya <b>tüm firmaların</b> personel, maaş ve kimlik verilerini içerir. İndirme işlemi kritik seviyede loglanır.<br><br>Dosyayı yalnızca güvenli bir cihazda saklayın.</div>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'İndir',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#f46a6a'
        }).then((secim) => {
            if (!secim.isConfirmed) return;
            const form = $('#yedekIndirForm');
            form.find('[name=dosya]').val(dosya);
            form[0].submit();
        });
    });

    $('#yedeklemeTable').on('click', '.yedek-detay', function (e) {
        e.stopPropagation();
        const satir = table.row($(this).closest('tr')).data();
        if (satir) detayGoster(satir);
    });

    $('#yedeklemeTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('td:last-child, a, button').length) return;
        const satir = table.row(this).data();
        if (satir) detayGoster(satir);
    });

    function detayGoster(satir) {
        $('#yedekDetayDosya').text(satir.ad);
        const bilgi = [
            ['Tip', satir.tip === 'tam' ? 'Tam yedek' : 'Artımlı yedek'],
            ['Yedek zamanı', tarihYaz(satir.zaman)],
            ['Boyut', boyutYaz(satir.boyut) + (satir.drive_boyut ? ` (Drive şifreli: ${boyutYaz(satir.drive_boyut)})` : '')],
            ['Konum', konumMetni(satir)],
            ['Bağlı tam yedek', satir.temel || (satir.tip === 'tam' ? '—' : 'Bilinmiyor')]
        ];
        let html = `<table class="table table-sm table-borderless font-size-13 mb-3"><tbody>${bilgi.map(([k, v]) => `<tr><td class="text-muted" style="width:160px">${esc(k)}</td><td class="text-break">${esc(v)}</td></tr>`).join('')}</tbody></table>`;
        html += '<h6 class="fw-bold font-size-13 mb-2">Son Geri Yükleme Testi</h6><div id="yedekDetayTest" class="font-size-12 text-muted">Yükleniyor...</div>';
        $('#yedekDetayIcerik').html(html);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('yedekDetayModal')).show();

        $.post(API, { action: 'test-detay', dosya: satir.ad, csrf_token: csrf }, function (res) {
            const t = res.test;
            if (!t) {
                $('#yedekDetayTest').text('Bu yedek henüz test edilmedi.');
                return;
            }
            let ic = `<div class="d-flex align-items-center gap-2 mb-2">${testRozeti(t)}<span>${esc(tarihYaz(t.zaman))} · ${esc(t.sure)} sn${t.tablo_sayisi ? ' · ' + esc(t.tablo_sayisi) + ' tablo · ' + Number(t.toplam_satir || 0).toLocaleString('tr-TR') + ' satır' : ''}</span></div>`;
            if (t.hata) ic += `<div class="alert alert-danger py-2 font-size-12 mb-0">${esc(t.hata)}</div>`;
            if (t.farklar && t.farklar.length) {
                ic += `<table class="table table-sm table-bordered font-size-12 mb-0"><thead><tr><th>Tablo</th><th class="text-end">Yedekteki</th><th class="text-end">Yüklenen</th></tr></thead><tbody>${t.farklar.map((f) => `<tr><td>${esc(f.tablo)}</td><td class="text-end">${f.beklenen == null ? '-' : Number(f.beklenen).toLocaleString('tr-TR')}</td><td class="text-end">${f.bulunan == null ? 'tablo yok' : Number(f.bulunan).toLocaleString('tr-TR')}</td></tr>`).join('')}</tbody></table>`;
            } else if (t.sonuc === 'basarili') {
                ic += '<div class="text-success">Tüm tabloların satır sayıları yedekle birebir eşleşti.</div>';
            } else if (t.sonuc === 'sayimsiz') {
                ic += '<div>Eski biçimli yedek: yükleme başarılı, satır sayıları karşılaştırılamadı.</div>';
            }
            $('#yedekDetayTest').removeClass('text-muted').html(ic);
        }, 'json');
    }

    $('.status-quick-filter').on('click', function () {
        durumFiltresi = $(this).data('status') || 'all';
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        table.draw();
    });

    $('#btnDriveListeYenile').on('click', function () {
        driveYenile = true;
        table.ajax.reload(null, false);
    });

    $('#btnIsKartiKapat').on('click', function () {
        $('#yedekIsKarti').removeClass('aktif');
    });

    $('#btnHeaderPrint').on('click', function () {
        window.print();
    });
});
