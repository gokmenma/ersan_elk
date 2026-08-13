(function ($) {
    'use strict';

    const API = 'views/kesme-acma/api.php';
    const yetki = window.kaYetki || {};
    const BUGUN = window.kaBugun;

    const ILCE_ADI = { dulkadiroglu: 'Dulkadiroğlu', onikisubat: 'Onikişubat' };
    const DURUM_ADI = {
        atanabilir: ['Atanabilir', 'success'],
        bekliyor: ['Bekliyor', 'warning'],
        mesajsiz: ['Mesaj atılmadı', 'secondary'],
        sahada: ['Ekip sahada', 'info'],
        girilmiyor: ['Girilmiyor', 'danger']
    };
    const GUN_KISA = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

    let mahalleVerisi = [];
    let mahalleFiltre = 'hepsi';
    let nobetAyi = BUGUN.substring(0, 7) + '-01';
    let nobetVerisi = null;
    let matrisVerisi = null;
    let matrisSonuc = 'TUMU';
    let matrisSecim = null;
    let ozetRaporYuklendi = false;
    let detayRaporTablo = null;

    function kacir(deger) {
        return $('<div>').text(deger === null || deger === undefined ? '' : deger).html();
    }

    function tarihTR(deger) {
        if (!deger) return '';
        const p = String(deger).substring(0, 10).split('-');
        return p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : String(deger);
    }

    function kisaTarih(deger) {
        if (!deger) return '';
        const p = String(deger).substring(0, 10).split('-');
        return p.length === 3 ? `${p[2]}.${p[1]}` : String(deger);
    }

    function tarihGonder(secici) {
        const deger = $(secici).val();
        if (!deger) return '';
        const p = String(deger).split('.');
        return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : deger;
    }

    function sayi(deger) {
        return Number(deger || 0).toLocaleString('tr-TR');
    }

    function gunFarki(a, b) {
        return Math.round((new Date(b) - new Date(a)) / 86400000);
    }

    function hata(mesaj) {
        Swal.fire('Hata', mesaj || 'İşlem tamamlanamadı.', 'error');
    }

    function basari(mesaj) {
        Swal.fire({ icon: 'success', title: 'Tamam', text: mesaj || 'İşlem tamamlandı.', timer: 2200, showConfirmButton: false });
    }

    function istek(veri, tip) {
        return $.ajax({ url: API, type: tip || 'GET', data: veri, dataType: 'json' });
    }

    function select2Kur(secici, kapsayici) {
        const alan = $(secici);
        if (!alan.length || !$.fn.select2) return alan;
        if (alan.hasClass('select2-hidden-accessible')) alan.select2('destroy');
        alan.select2({
            width: '100%',
            dropdownParent: kapsayici ? $(kapsayici) : undefined,
            language: { noResults: () => 'Kayıt bulunamadı' }
        });
        return alan;
    }

    function select2Deger(secici, deger) {
        const alan = $(secici);
        alan.val(deger || '');
        if (alan.hasClass('select2-hidden-accessible')) alan.trigger('change.select2');
        return alan;
    }

    /** <template> içeriği klonlanıp DOM'a eklendiğinde data-feather ikonları henüz SVG'ye çevrilmemiştir. */
    function featherYenile() {
        if (typeof feather !== 'undefined') {
            try { feather.replace(); } catch (e) { /* yoksay */ }
        }
    }

    function ekipKisa(ad) {
        if (!ad) return '';
        const m = String(ad).match(/(EK[İI]P[\s-]?\d+.*|KOORD[İI]NAT[ÖO]R[\s-]?\d+.*)$/i);
        return m ? m[1].trim() : String(ad);
    }

    function ilceRozet(ilce) {
        if (ilce === 'dulkadiroglu') return '<span class="ka-rozet ka-dul">Dulkadiroğlu</span>';
        if (ilce === 'onikisubat') return '<span class="ka-rozet ka-oni">Onikişubat</span>';
        return `<span class="ka-rozet ka-ilce">${kacir(ilce || '-')}</span>`;
    }

    function ilceHarf(ilce) {
        if (ilce === 'dulkadiroglu') return '<span class="ka-rozet ka-dul">D</span>';
        if (ilce === 'onikisubat') return '<span class="ka-rozet ka-oni">O</span>';
        return '<span class="ka-rozet ka-ilce">İ</span>';
    }

    // ---------- Dashboard ----------

    function ozetYukle() {
        istek({ action: 'ozet' }).done(function (c) {
            if (c.status !== 'success') return hata(c.message);

            $('#kaNobetci').text(c.nobetci ? (c.nobetci.personel || ekipKisa(c.nobetci.ekip_adi)) : 'Plan yok');
            $('#kaNobetciAlt').text(c.nobetci ? ekipKisa(c.nobetci.ekip_adi) : 'nöbet planı henüz üretilmedi');
            $('#kaTelefon').text(c.telefon || 'Atanmadı');
            $('#kaAtanabilir').text(sayi(c.sayilar.atanabilir));
            $('#kaKalanIs').text(sayi(c.kalan_is));
            $('#kaSahipsiz').html(c.sahipsiz > 0
                ? `<span class="text-danger fw-semibold">${sayi(c.sahipsiz)} iş sahipsiz</span> — personeli ayrıldı`
                : 'ekiplerin elle girdiği toplam');

            $('#kaSonAktarim').text(c.son_aktarim ? 'Son veri: ' + tarihTR(c.son_aktarim) + ' ' + String(c.son_aktarim).substring(11, 16) : '');

            const p = c.projeksiyon;
            $('#kaAyOzet').html(`Ay başından bugüne <b>${sayi(p.yapilan)}</b> işlem · iş günü ortalaması <b>${sayi(p.is_gunu_ortalamasi)}</b> · ay sonu tahmini <b>${sayi(p.projeksiyon)}</b>`);
            grafikCiz(p.gunluk);
            yapilacakCiz(c);
        }).fail(function () {
            hata('Özet verisi alınamadı.');
        });
    }

    function grafikCiz(gunluk) {
        const gunler = Object.keys(gunluk || {}).sort();
        if (!gunler.length) {
            $('#kaGrafik').html('<div class="text-muted small">Bu ay için işlem kaydı bulunamadı.</div>');
            return;
        }

        const G = 700, ustBosluk = 22, altBosluk = 26, ic = 150, Y = ustBosluk + ic + altBosluk;
        const degerler = gunler.map(g => gunluk[g]);
        const enBuyuk = Math.max.apply(null, degerler) || 1;
        const isGunleri = gunler.filter(g => new Date(g).getDay() !== 0).map(g => gunluk[g]);
        const ortalama = isGunleri.length ? Math.round(isGunleri.reduce((a, b) => a + b, 0) / isGunleri.length) : 0;
        const genislik = G / gunler.length;
        const y = v => ustBosluk + ic - (v / (enBuyuk * 1.15)) * ic;

        let svg = '';
        gunler.forEach(function (g, i) {
            const pazar = new Date(g).getDay() === 0;
            const x = i * genislik + genislik * 0.18;
            const w = genislik * 0.64;
            const merkez = i * genislik + genislik / 2;
            svg += `<rect x="${x}" y="${y(gunluk[g])}" width="${w}" height="${ustBosluk + ic - y(gunluk[g])}" rx="2" fill="${pazar ? '#f8b6b6' : '#556ee6'}"/>`;
            svg += `<text x="${merkez}" y="${y(gunluk[g]) - 4}" font-size="9" text-anchor="middle" fill="#74788d">${gunluk[g]}</text>`;
            svg += `<text x="${merkez}" y="${ustBosluk + ic + 14}" font-size="9" text-anchor="middle" fill="${pazar ? '#f46a6a' : '#74788d'}">${Number(g.substring(8, 10))}</text>`;
        });
        svg += `<line x1="0" x2="${G}" y1="${ustBosluk + ic}" y2="${ustBosluk + ic}" stroke="#eff2f7"/>`;
        if (ortalama > 0) {
            svg += `<line x1="0" x2="${G}" y1="${y(ortalama)}" y2="${y(ortalama)}" stroke="#f1b44c" stroke-dasharray="5 4"/>`;
            svg += `<text x="${G}" y="${y(ortalama) - 5}" font-size="10" text-anchor="end" fill="#f1b44c">ortalama ${ortalama}</text>`;
        }

        $('#kaGrafik').html(`<svg viewBox="0 0 ${G} ${Y}" width="100%">${svg}</svg>`);
    }

    function yapilacakCiz(c) {
        let html = '';

        (c.oneriler || []).forEach(function (o) {
            if (o.onerilen) {
                html += `<div class="ka-yapilacak-satir">
                    <div class="ka-yapilacak-sayi text-warning">${o.kalan_gun <= 1 ? 'bugün' : o.kalan_gun + ' gün'}</div>
                    <div class="flex-grow-1 small">
                        ${kacir(o.personel || ekipKisa(o.ekip_adi))} bitiriyor → <b>${kacir(o.onerilen.ad)}</b> ata
                        <div class="text-muted">${ILCE_ADI[o.siradaki_ilce]} sırası · şu an ${kacir(o.mahalle_adi || '-')}</div>
                    </div>
                    ${yetki.atama ? `<button class="btn btn-sm btn-primary ka-hizli-ata" data-ekip="${o.ekip_id}" data-mahalle="${o.onerilen.id}">Ata</button>` : ''}
                </div>`;
            } else {
                html += `<div class="ka-yapilacak-satir">
                    <div class="ka-yapilacak-sayi text-danger">!</div>
                    <div class="flex-grow-1 small">
                        ${kacir(o.personel || ekipKisa(o.ekip_adi))} için hazır <b>${ILCE_ADI[o.siradaki_ilce]}</b> mahallesi yok
                        <div class="text-muted">${o.ilk_hazir
                        ? 'ilk hazır: ' + kacir(o.ilk_hazir.ad) + ' · ' + tarihTR(o.ilk_hazir.hazir_tarihi)
                        : 'bugün mesaj atılırsa 5 gün sonra hazır olur'}</div>
                    </div>
                </div>`;
            }
        });

        (c.sahipsiz_liste || []).forEach(function (s) {
            html += `<div class="ka-yapilacak-satir">
                <div class="ka-yapilacak-sayi text-danger">${sayi(s.acik)}</div>
                <div class="flex-grow-1 small">${kacir(s.adi_soyadi || 'Bilinmeyen personel')} üzerindeki işler sahipsiz
                    <div class="text-muted">ekipten ayrılmış — devredilmeli</div>
                </div>
            </div>`;
        });

        $('#kaYapilacaklar').html(html || '<div class="text-muted small">Bugün için bekleyen bir iş yok.</div>');
    }

    // ---------- Mahalleler ----------

    function mahalleYukle() {
        istek({ action: 'mahalle-listesi' }).done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            mahalleVerisi = c.mahalleler || [];
            mahalleFiltreCiz();
            mahalleCiz();
        });
    }

    function mahalleFiltreCiz() {
        const say = f => f === 'hepsi'
            ? mahalleVerisi.length
            : mahalleVerisi.filter(m => m.durum === f || m.ilce === f).length;

        const secenekler = ['hepsi', 'atanabilir', 'bekliyor', 'mesajsiz', 'sahada', 'girilmiyor', 'dulkadiroglu', 'onikisubat'];
        $('#kaMahalleFiltre').html(secenekler.map(function (f) {
            const ad = f === 'hepsi' ? 'Hepsi' : (DURUM_ADI[f] ? DURUM_ADI[f][0] : ILCE_ADI[f]);
            return `<button type="button" class="btn btn-sm ${f === mahalleFiltre ? 'btn-primary' : 'btn-outline-secondary'} ka-mahalle-chip" data-f="${f}">
                ${ad} <span class="badge bg-light text-dark ms-1">${say(f)}</span></button>`;
        }).join(''));
    }

    function mahalleCiz() {
        const sira = { atanabilir: 0, bekliyor: 1, sahada: 2, mesajsiz: 3, girilmiyor: 4 };
        const satirlar = mahalleVerisi
            .filter(m => mahalleFiltre === 'hepsi' || m.durum === mahalleFiltre || m.ilce === mahalleFiltre)
            .sort(function (a, b) {
                if (sira[a.durum] !== sira[b.durum]) return sira[a.durum] - sira[b.durum];
                return (a.mesaj_tarihi || '9999') < (b.mesaj_tarihi || '9999') ? -1 : 1;
            });

        $('#kaTabloMahalle tbody').html(satirlar.map(function (m) {
            const [durumAd, durumRenk] = DURUM_ADI[m.durum];
            let ek = '';
            if (m.durum === 'atanabilir') ek = `<small class="text-success ms-2">${gunFarki(m.hazir_tarihi, BUGUN)} gündür bekliyor</small>`;
            if (m.durum === 'bekliyor') ek = `<small class="text-warning ms-2">${gunFarki(BUGUN, m.hazir_tarihi)} gün sonra hazır</small>`;
            if (m.durum === 'sahada') ek = `<small class="text-muted ms-2">${kacir(ekipKisa(m.aktif_ekip_adi || ''))}</small>`;

            const sz = m.son_ziyaret;
            const ziyaret = sz
                ? (sz.durum === 'aktif'
                    ? `<span class="badge bg-info-subtle text-info">şu an sahada</span>`
                    : `${tarihTR(sz.ziyaret_tarihi)} <small class="text-muted d-block">${kacir(ekipKisa(sz.ekip_adi || ''))} · ${gunFarki(sz.ziyaret_tarihi, BUGUN)} gün önce</small>`)
                : '<span class="text-muted">kayıt yok</span>';

            let butonlar = '';
            if (yetki.mesaj && m.durum !== 'girilmiyor' && m.durum !== 'sahada') {
                butonlar += `<button class="btn btn-sm btn-outline-primary ka-mesaj-at" data-id="${m.id}" data-ad="${kacir(m.ad)}" title="Mesaj at"><i class="bx bx-message-dots"></i></button> `;
            }
            if (yetki.atama && m.durum === 'atanabilir') {
                butonlar += `<button class="btn btn-sm btn-primary ka-mahalle-ata" data-id="${m.id}" title="Ekibe ata"><i class="bx bx-user-plus"></i></button> `;
            }
            if (yetki.tanim) {
                butonlar += `<button class="btn btn-sm btn-outline-secondary ka-mahalle-duzenle" data-id="${m.id}" title="Düzenle"><i class="bx bx-edit"></i></button>`;
            }

            return `<tr>
                <td><b>${kacir(m.ad)}</b><small class="text-muted d-block">kod ${kacir(m.kod_araligi || '-')}</small></td>
                <td>${ilceRozet(m.ilce)}</td>
                <td>${m.mesaj_tarihi ? tarihTR(m.mesaj_tarihi) : '<span class="text-muted">-</span>'}</td>
                <td><span class="badge bg-${durumRenk}-subtle text-${durumRenk}">${durumAd}</span>${ek}</td>
                <td>${ziyaret}</td>
                <td class="text-end">${butonlar}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Kayıt yok. Mahalle havuzunu doldurmak için sql/kesme_acma_mahalle_seed.sql çalıştırılabilir.</td></tr>');
    }

    // ---------- Ekipler ----------

    function ekipYukle() {
        istek({ action: 'ekip-listesi' }).done(function (c) {
            if (c.status !== 'success') return hata(c.message);

            const tumu = c.ekipler || [];
            const hareketli = tumu.filter(e => Number(e.hareketli) === 1);
            const gosterilecek = ($('#kaTumEkipler').is(':checked') || !hareketli.length) ? tumu : hareketli;
            $('#kaTumEkipler').closest('.form-check').find('label')
                .text(`Tüm ekipleri göster (${tumu.length - hareketli.length} pasif ekip gizli)`);

            $('#kaTabloEkip tbody').html(gosterilecek.map(function (e) {
                const oneri = e.oneri;
                const acil = e.kalan_gun !== null && e.kalan_gun <= 2;

                let bitis = '<span class="text-muted">-</span>';
                if (e.kalan_gun !== null) {
                    bitis = `<span class="fw-semibold ${acil ? 'text-warning' : 'text-success'}">${e.kalan_gun <= 1 ? 'bugün' : e.kalan_gun + ' iş günü'}</span>
                        <small class="d-block text-muted">${tarihTR(e.bitis_tarihi)}</small>`;
                }

                const son3 = (e.son_atamalar || []).map(a =>
                    `<span title="${kacir(a.mahalle_adi)} · ${tarihTR(a.baslangic)}">${ilceHarf(a.ilce)}</span>`).join('') ||
                    '<span class="text-muted">-</span>';

                let oneriHtml = `<span class="text-muted small">daha var — sırası gelince ${ILCE_ADI[e.siradaki_ilce]} önerilecek</span>`;
                let ataBtn = '';
                if (oneri && oneri.onerilen) {
                    oneriHtml = `<b>${kacir(oneri.onerilen.ad)}</b> ${ilceRozet(e.siradaki_ilce)}`;
                    if (yetki.atama) {
                        ataBtn = `<button class="btn btn-sm btn-primary ka-hizli-ata" data-ekip="${e.ekip_id}" data-mahalle="${oneri.onerilen.id}">Ata</button>`;
                    }
                } else if (oneri) {
                    oneriHtml = `<span class="text-danger small">hazır ${ILCE_ADI[e.siradaki_ilce]} mahallesi yok</span>`;
                    if (oneri.ilk_hazir) {
                        oneriHtml += `<small class="d-block text-muted">ilk hazır: ${kacir(oneri.ilk_hazir.ad)} · ${tarihTR(oneri.ilk_hazir.hazir_tarihi)}</small>`;
                    }
                }

                const kalanGiris = yetki.kalanIs
                    ? `<input type="number" min="0" class="form-control form-control-sm text-center ka-kalan-is" style="width:90px;margin:0 auto"
                            value="${e.kalan_is === null ? '' : e.kalan_is}" data-ekip="${e.ekip_id}" placeholder="-">`
                    : (e.kalan_is === null ? '-' : sayi(e.kalan_is));

                return `<tr>
                    <td><b>${kacir(e.personel || ekipKisa(e.ekip_adi))}</b><small class="text-muted d-block">${kacir(ekipKisa(e.ekip_adi))}</small></td>
                    <td>${e.mahalle_adi ? ilceRozet(e.ilce) + ' ' + kacir(e.mahalle_adi) : '<span class="text-muted">atama yok</span>'}</td>
                    <td class="text-center">${kalanGiris}
                        ${e.kalan_tarihi && e.kalan_tarihi !== BUGUN ? `<small class="d-block text-warning">${tarihTR(e.kalan_tarihi)} girişi</small>` : ''}</td>
                    <td class="text-center">${e.net_eritme}<small class="d-block text-muted">hız ${e.gunluk_hiz} − vade ${e.yeni_vade}</small></td>
                    <td class="text-center">${bitis}</td>
                    <td class="text-center">${son3}</td>
                    <td>${oneriHtml}</td>
                    <td class="text-end">
                        ${ataBtn}
                        <button class="btn btn-sm btn-outline-secondary ka-gecmis-ac" data-ekip="${e.ekip_id}" title="Geçmişi aç"><i class="bx bx-history"></i></button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">Kesme/açma ekibi bulunamadı.</td></tr>');

            const secenekler = (c.atanabilir || []).map(m =>
                `<option value="${m.id}" data-ilce="${m.ilce}">${kacir(m.ad)} — ${ILCE_ADI[m.ilce]} (${tarihTR(m.mesaj_tarihi)} mesaj)</option>`).join('');
            $('#kaAtamaMahalle').html('<option value="">Mahalle Seçiniz</option>' + secenekler);
        });
    }

    // ---------- Geçmiş ----------

    function gecmisYukle() {
        const veri = { action: 'gecmis-listesi' };
        const ekip = $('#kaGecmisEkip').val();
        const ilce = $('#kaGecmisIlce').val();
        if (ekip) veri.ekip_id = ekip;
        if (ilce) veri.ilce = ilce;

        istek(veri).done(function (c) {
            if (c.status !== 'success') return hata(c.message);

            $('#kaGecmisTamamlanan').text(sayi(c.tamamlanan));
            $('#kaGecmisSure').text(c.ortalama_gun ? c.ortalama_gun + ' gün' : '-');
            $('#kaGecmisGidilmeyen').text(sayi(c.hic_gidilmeyen));
            $('#kaGecmisHavuz').text(`havuzdaki ${c.havuz_sayisi} mahalleden`);

            const kapali = (c.ziyaretler || []).filter(z => !z.aktif);
            if (kapali.length) {
                $('#kaGecmisEnEski').text(kapali[0].mahalle_adi);
                $('#kaGecmisEnEskiAlt').text(`${kapali[0].gun_once} gün önce · ${ekipKisa(kapali[0].ekip_adi || '')}`);
            } else {
                $('#kaGecmisEnEski').text('-');
                $('#kaGecmisEnEskiAlt').text('');
            }

            cizelgeCiz(c.zaman_cizelgesi || {}, c.ekipler || []);
            gecmisTabloCiz(c.kayitlar || []);
            ziyaretTabloCiz(c.ziyaretler || [], c.hic_gidilmeyen, c.havuz_sayisi);
        });
    }

    function cizelgeCiz(cizelge, ekipler) {
        let enErken = BUGUN;
        Object.keys(cizelge).forEach(function (ekipId) {
            cizelge[ekipId].forEach(function (a) {
                if (a.baslangic < enErken) enErken = a.baslangic;
            });
        });

        const aralik = Math.max(gunFarki(enErken, BUGUN), 1);
        const konum = t => (gunFarki(enErken, t) / aralik) * 100;

        const satirlar = ekipler.map(function (ekip) {
            const liste = (cizelge[ekip.id] || []).slice().sort((a, b) => a.baslangic < b.baslangic ? -1 : 1);
            if (!liste.length) return '';

            const parcalar = liste.map(function (a) {
                const sol = Math.max(konum(a.baslangic), 0);
                const gen = Math.max(konum(a.bitis || BUGUN) - sol, 1.5);
                const sinif = a.ilce === 'dulkadiroglu' ? 'dul' : (a.ilce === 'onikisubat' ? 'oni' : 'ilce');
                const baslik = `${a.mahalle_adi} — ${ILCE_ADI[a.ilce] || a.ilce}\n${tarihTR(a.baslangic)} – ${a.bitis ? tarihTR(a.bitis) : 'devam ediyor'}`;
                return `<div class="ka-cizelge-parca ${sinif} ${a.bitis ? '' : 'aktif'}"
                    style="left:${sol.toFixed(2)}%;width:${gen.toFixed(2)}%" title="${kacir(baslik)}">${kacir(a.mahalle_adi)}</div>`;
            }).join('');

            const d = liste.filter(a => a.ilce === 'dulkadiroglu').length;
            const o = liste.filter(a => a.ilce === 'onikisubat').length;

            return `<div class="ka-cizelge-satir">
                <div><b class="small">${kacir(ekip.personel || ekipKisa(ekip.tur_adi))}</b>
                    <small class="text-muted d-block">${kacir(ekipKisa(ekip.tur_adi))} · ${liste.length} mahalle · ${d} D / ${o} O</small></div>
                <div class="ka-cizelge-yol">${parcalar}</div>
            </div>`;
        }).join('');

        $('#kaCizelge').html(satirlar || '<div class="text-muted small">Henüz atama kaydı yok.</div>');

        let eksen = '';
        if (satirlar) {
            let ay = new Date(enErken.substring(0, 8) + '01');
            const son = new Date(BUGUN);
            while (ay <= son) {
                const ayBasi = new Date(Math.max(ay, new Date(enErken)));
                const ayBitisHam = new Date(ay.getFullYear(), ay.getMonth() + 1, 0);
                const ayBitis = ayBitisHam > son ? son : ayBitisHam;
                const orta = (konum(ayBasi.toISOString().substring(0, 10)) + konum(ayBitis.toISOString().substring(0, 10))) / 2;
                eksen += `<span style="left:${orta.toFixed(2)}%">${ay.toLocaleDateString('tr-TR', { month: 'long' })}</span>`;
                ay = new Date(ay.getFullYear(), ay.getMonth() + 1, 1);
            }
        }
        $('#kaCizelgeEksen').html(eksen);
    }

    function gecmisTabloCiz(kayitlar) {
        $('#kaTabloGecmis tbody').html(kayitlar.map(function (k) {
            return `<tr>
                <td><b>${kacir(k.mahalle_adi)}</b><small class="text-muted d-block">${kacir(ekipKisa(k.ekip_adi || ''))}</small></td>
                <td>${ilceRozet(k.ilce)}</td>
                <td class="text-center">${tarihTR(k.baslangic)}</td>
                <td class="text-center">${k.bitis ? tarihTR(k.bitis) : '<span class="badge bg-success-subtle text-success">sahada</span>'}</td>
                <td class="text-center">${k.is_gunu}</td>
                <td class="text-end">
                    ${yetki.atama && k.aktif ? `<button class="btn btn-sm btn-outline-primary ka-atama-kapat" data-id="${k.id}" title="Atamayı kapat"><i class="bx bx-check"></i></button> ` : ''}
                    ${yetki.atama ? `<button class="btn btn-sm btn-outline-danger ka-atama-sil" data-id="${k.id}" title="Kaydı sil"><i class="bx bx-trash"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Atama kaydı yok.</td></tr>');
    }

    function ziyaretTabloCiz(ziyaretler, gidilmeyen, havuz) {
        $('#kaZiyaretNot').html(`Havuzdaki ${havuz} mahalleden <b>${havuz - gidilmeyen}</b> tanesine gidildi;
            <b class="text-warning">${gidilmeyen} mahalleye hiç gidilmedi</b>. En eski ziyaret üstte.`);

        $('#kaTabloZiyaret tbody').html(ziyaretler.map(function (z) {
            return `<tr>
                <td><b>${kacir(z.mahalle_adi)}</b></td>
                <td class="text-muted">${kacir(ekipKisa(z.ekip_adi || ''))}</td>
                <td class="text-center">${z.aktif ? '<span class="badge bg-success-subtle text-success">sahada</span>' : tarihTR(z.tarih)}</td>
                <td class="text-center">${z.aktif ? '-' : z.gun_once}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="4" class="text-center text-muted py-4">Ziyaret kaydı yok.</td></tr>');
    }

    // ---------- Nöbet ----------

    function nobetYukle() {
        istek({ action: 'nobet-plani', ay: nobetAyi }).done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            nobetVerisi = c;
            $('#kaTelefonUyari').toggleClass('d-none', (c.personeller || []).length > 0);
            takvimCiz();
            ilceTabloCiz();
        });
    }

    function takvimCiz() {
        const c = nobetVerisi;
        const ayBasi = new Date(c.ay_basi);
        const gunSayisi = new Date(c.ay_sonu).getDate();
        const bosluk = (ayBasi.getDay() + 6) % 7;

        $('#kaNobetBaslik').text(ayBasi.toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' }) + ' — merkez nöbeti');
        $('#kaTakvimBaslik').html(GUN_KISA.map((g, i) =>
            `<div class="text-center small fw-semibold ${i > 4 ? 'text-danger' : 'text-muted'}">${g}</div>`).join(''));

        let html = '';
        for (let i = 0; i < bosluk; i++) html += '<div class="ka-gun bos"></div>';

        for (let g = 1; g <= gunSayisi; g++) {
            const tarih = c.ay_basi.substring(0, 8) + String(g).padStart(2, '0');
            const haftaGun = new Date(tarih).getDay();
            const haftaSonu = haftaGun === 0 || haftaGun === 6;
            const saha = c.saha[tarih];
            const telefon = c.telefon[tarih];
            const gecmisGun = tarih < BUGUN;
            const taslak = saha && saha.kaynak === 'taslak';

            const ekipKutu = saha
                ? `<div class="ka-nobet-kutu ${saha.elle ? 'elle' : ''} ${taslak ? 'ka-saha-hucre' : 'canli-kayit'}" data-tarih="${tarih}">
                       ${kacir(saha.personel || '—')}
                       <div style="font-size:.62rem;font-weight:500;opacity:.9">${kacir(ekipKisa(saha.ekip_adi || ''))}</div>
                       <div class="ka-kaynak-etiket">${taslak ? 'Taslak' : 'Mevcut kayıt'}</div>
                   </div>`
                : `<div class="ka-nobet-kutu bos-kutu ${gecmisGun ? '' : 'ka-saha-hucre'}" data-tarih="${tarih}">${gecmisGun ? '—' : '— ata —'}</div>`;

            const telefonKutu = `<div class="ka-telefon-kutu ${telefon && telefon.elle ? 'elle' : ''} ka-telefon-hucre" data-tarih="${tarih}">
                    <i class="bx bx-phone"></i> ${telefon ? kacir(telefon.adi_soyadi || '') : '—'}
                </div>`;

            html += `<div class="ka-gun ${haftaSonu ? 'hafta-sonu' : ''} ${tarih === BUGUN ? 'bugun' : ''}">
                <div class="gun-no">${g}</div>${ekipKutu}${telefonKutu}</div>`;
        }

        $('#kaTakvim').html(html);

        nobetGrafikCiz();
    }

    function ekipPersoneli(ekipId) {
        const ekip = (nobetVerisi.ekipler || []).find(e => Number(e.id) === Number(ekipId));
        return ekip ? (ekip.personel || '') : '';
    }

    function ilceTabloCiz() {
        const c = nobetVerisi;
        const araclilar = c.aracli_ekipler || [];
        const uygun = (c.ekipler || []).map(e => Number(e.id)).filter(id => araclilar.indexOf(id) === -1);
        const sablon = document.getElementById('kaIlceSablon');

        const haftalar = [];
        let hafta = haftaBasi(c.ay_basi);
        while (hafta <= c.ay_sonu) {
            haftalar.push(hafta);
            const d = new Date(hafta);
            d.setDate(d.getDate() + 7);
            hafta = d.toISOString().substring(0, 10);
        }

        $('#kaTabloIlce tbody').html(haftalar.map(function (h) {
            const kayit = c.ilce[h] || {};
            const hucre = function (ilce) {
                if (!yetki.nobet || !sablon) {
                    return kayit[ilce] ? kacir(ekipPersoneli(kayit[ilce].ekip_id) || ekipKisa(kayit[ilce].ekip_adi)) : '<span class="text-muted">-</span>';
                }
                const kutu = $('<div>').html(sablon.innerHTML);
                const alan = kutu.find('select');
                const secili = kayit[ilce] ? String(kayit[ilce].ekip_id) : '';
                alan.attr({ 'data-hafta': h, 'data-ilce': ilce, id: 'kaIlce_' + ilce + '_' + h, name: 'kaIlce_' + ilce + '_' + h })
                    .addClass('ka-ilce-sec');
                alan.find('option').each(function () {
                    const id = Number(this.value);
                    if (this.value && uygun.indexOf(id) === -1) $(this).attr('disabled', 'disabled');
                    if (this.value === secili) $(this).attr('selected', 'selected');
                });
                if (kayit[ilce] && kayit[ilce].elle) alan.addClass('border-warning');
                kutu.find('label').remove();
                return kutu.html();
            };

            return `<tr>
                <td class="text-muted small">${kisaTarih(h)}</td>
                <td>${hucre('turkoglu')}</td>
                <td>${hucre('pazarcik')}</td>
            </tr>`;
        }).join(''));

        $('#kaTabloIlce select.ka-ilce-sec').each(function () {
            select2Kur(this);
        });
        featherYenile();
    }

    function nobetGrafikCiz() {
        const c = nobetVerisi;
        const sayac = {};

        Object.keys(c.saha).forEach(function (t) {
            const id = Number(c.saha[t].personel_id);
            const haftaGun = new Date(t).getDay();
            sayac[id] = sayac[id] || { n: 0, hs: 0, ad: c.saha[t].personel || '—' };
            sayac[id].n++;
            if (haftaGun === 0 || haftaGun === 6) sayac[id].hs++;
        });

        const satirlar = Object.keys(sayac).map(k => sayac[k]).sort((a, b) => b.n - a.n || a.ad.localeCompare(b.ad, 'tr'));
        if (!satirlar.length) {
            $('#kaNobetGrafik').html('<div class="text-muted small">Bu ay için nöbet planı üretilmedi.</div>');
            $('#kaNobetDagilimNot').text('Ay içinde personel başına düşen nöbet günü; koyu bölüm hafta sonudur.');
            return;
        }

        const enBuyuk = Math.max.apply(null, satirlar.map(s => s.n));
        const toplamHs = satirlar.reduce((t, s) => t + s.hs, 0);
        $('#kaNobetDagilimNot').html(`${satirlar.length} personel · ${Object.keys(c.saha).length} gün · <b>${toplamHs}</b> hafta sonu nöbeti`);

        $('#kaNobetGrafik').html(satirlar.map(function (s) {
            const oran = (s.n / enBuyuk) * 100;
            const hsOran = s.n ? (s.hs / s.n) * 100 : 0;
            return `<div class="ka-dagilim-satir">
                <div class="ka-dagilim-ad" title="${kacir(s.ad)}">${kacir(s.ad)}</div>
                <div class="ka-dagilim-yol">
                    <div class="ka-dagilim-bar" style="width:${oran.toFixed(1)}%">
                        <div class="ka-dagilim-hs" style="width:${hsOran.toFixed(1)}%"></div>
                    </div>
                </div>
                <div class="ka-dagilim-sayi">${s.n}${s.hs ? `<small class="text-muted"> · ${s.hs} hs</small>` : ''}</div>
            </div>`;
        }).join(''));
    }

    function haftaBasi(tarih) {
        const d = new Date(tarih);
        const gun = (d.getDay() + 6) % 7;
        d.setDate(d.getDate() - gun);
        return d.toISOString().substring(0, 10);
    }

    // ---------- Taşınan Kesme/Açma raporları ----------

    function ozetRaporYukle() {
        const icerik = $('#kaOzetRaporIcerik');
        const filtreTipi = $('input[name="kaRaporFiltreTipi"]:checked').val() || 'period';
        icerik.html('<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Rapor hazırlanıyor...</p></div>');
        $.ajax({
            url: 'views/puantaj/api.php',
            type: 'GET',
            data: {
                action: 'get-report-table',
                tab: 'kesme',
                year: $('#kaRaporYil').val(),
                month: $('#kaRaporAy').val(),
                personel_id: $('#kaRaporPersonel').val(),
                region: $('#kaRaporBolge').val(),
                defter: $('#kaRaporDefter').val(),
                start_date: tarihGonder('#kaRaporBaslangic'),
                end_date: tarihGonder('#kaRaporBitis'),
                filter_type: filtreTipi
            }
        }).done(function (html) {
            icerik.html(html);
            ozetRaporYuklendi = true;
            const aksiyonlar = icerik.find('.report-legend .ms-auto').first();
            if (aksiyonlar.length && !aksiyonlar.find('#kaBtnOzetTamEkran').length) {
                aksiyonlar.prepend('<button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" id="kaBtnOzetTamEkran"><i class="bx bx-fullscreen"></i> Tam Ekran</button>');
            }
            window.setTimeout(ozetRaporYukseklikAyarla, 50);
            const donem = filtreTipi === 'period'
                ? ($('#kaRaporAy option:selected').text() + ' ' + $('#kaRaporYil').val())
                : ($('#kaRaporBaslangic').val() + ' – ' + $('#kaRaporBitis').val());
            const badge = [`<span class="ka-rapor-filtre-badge"><i class="bx bx-calendar me-1"></i>${kacir(donem)}</span>`];
            if ($('#kaRaporPersonel').val()) badge.push(`<span class="ka-rapor-filtre-badge"><i class="bx bx-user"></i>${kacir($('#kaRaporPersonel option:selected').text())}</span>`);
            if ($('#kaRaporBolge').val()) badge.push(`<span class="ka-rapor-filtre-badge"><i class="bx bx-map-pin"></i>${kacir($('#kaRaporBolge').val())}</span>`);
            if ($('#kaRaporDefter').val()) badge.push(`<span class="ka-rapor-filtre-badge"><i class="bx bx-book"></i>${kacir($('#kaRaporDefter').val())}</span>`);
            $('#kaOzetRaporBadge').html(badge.join(''));
        }).fail(function () {
            icerik.html('<div class="alert alert-danger mb-0">Özet rapor yüklenirken bir hata oluştu.</div>');
        });
    }

    function ozetRaporYukseklikAyarla() {
        const pane = $('#pane-ka-ozet-rapor');
        const tabloAlani = $('#kaOzetRaporIcerik .table-responsive').first();
        const aktif = pane.hasClass('active') && pane.hasClass('show');

        if (document.fullscreenElement) {
            if (tabloAlani.length) {
                const ust = tabloAlani[0].getBoundingClientRect().top;
                const yukseklik = Math.max(300, Math.floor(window.innerHeight - ust - 15));
                tabloAlani.css({ height: yukseklik + 'px', maxHeight: yukseklik + 'px' });
            }
            return;
        }

        $('html, body').css('overflow-y', aktif ? 'hidden' : '');
        if (!aktif || !tabloAlani.length) return;

        const ust = tabloAlani[0].getBoundingClientRect().top;
        const yukseklik = Math.max(280, Math.floor(window.innerHeight - ust - 10));
        tabloAlani.css({ height: yukseklik + 'px', maxHeight: yukseklik + 'px' });
    }

    function getSonucGorsel(sonuc, index, isTotal) {
        if (isTotal) {
            return {
                renk: '#556ee6',
                ikon: 'bx bx-layer',
                baslik: 'Toplam İşlem'
            };
        }
        const s = (sonuc || '').toUpperCase();
        if (s.includes('ÖDEME') || s.includes('ODEME')) {
            return { renk: '#34c38f', ikon: 'bx bx-credit-card', baslik: sonuc };
        }
        if (s.includes('AÇILDI') || s.includes('ACILDI') || s.includes('AÇMA') || s.includes('ACMA')) {
            return { renk: '#0ea5e9', ikon: 'bx bx-lock-open-alt', baslik: sonuc };
        }
        if (s.includes('KESİM') || s.includes('KESIM') || s.includes('KAPATILDI')) {
            return { renk: '#f59e0b', ikon: 'bx bx-cut', baslik: sonuc };
        }
        if (s.includes('KAPALI') || s.includes('KAPI')) {
            return { renk: '#ef4444', ikon: 'bx bx-door-open', baslik: sonuc };
        }
        if (s.includes('KIRMA') || s.includes('ÜCRET') || s.includes('UCRET') || s.includes('CEZA')) {
            return { renk: '#8b5cf6', ikon: 'bx bx-wrench', baslik: sonuc };
        }
        if (s.includes('TAPA') || s.includes('MÜHÜR') || s.includes('MUHUR')) {
            return { renk: '#06b6d4', ikon: 'bx bx-shield-quarter', baslik: sonuc };
        }

        const palet = [
            { renk: '#556ee6', ikon: 'bx bx-check-circle' },
            { renk: '#34c38f', ikon: 'bx bx-task' },
            { renk: '#0ea5e9', ikon: 'bx bx-radio-circle-marked' },
            { renk: '#f59e0b', ikon: 'bx bx-time' },
            { renk: '#8b5cf6', ikon: 'bx bx-detail' },
            { renk: '#ec4899', ikon: 'bx bx-notepad' }
        ];
        const secilen = palet[index % palet.length];
        return { renk: secilen.renk, ikon: secilen.ikon, baslik: sonuc };
    }

    let seciliDetayFiltresi = '';
    let sonDetayOzet = [];

    function detayRaporOzetCiz(ozet) {
        if (ozet && ozet.length && !seciliDetayFiltresi) {
            sonDetayOzet = ozet;
        }
        const satirlar = (sonDetayOzet && sonDetayOzet.length ? sonDetayOzet : ozet) || [];
        const toplam = satirlar.reduce((t, s) => t + Number(s.toplam_abone || s.toplam || 0), 0);
        const kartlar = [{ sonuc: 'Toplam İşlem', toplam_abone: toplam, isTotal: true }].concat(
            satirlar.slice(0, 5).map(s => ({ ...s, isTotal: false }))
        );

        $('#kaDetayRaporOzet').html(kartlar.map(function (s, i) {
            const isTotal = !!s.isTotal;
            const adet = Number(s.toplam_abone || s.toplam || 0);
            const yuzde = toplam > 0 ? (isTotal ? 100 : Math.round((adet / toplam) * 100)) : 0;
            const gorsel = getSonucGorsel(s.sonuc, i, isTotal);
            const baslik = s.sonuc || (isTotal ? 'Toplam İşlem' : 'İşlem');
            const isActive = isTotal ? (!seciliDetayFiltresi) : (seciliDetayFiltresi === s.sonuc);

            return `<div class="col-6 col-md-4 col-xl-2">
                <div class="card shadow-none ka-stat-card h-100 ${isActive ? 'active' : ''}" 
                     role="button" 
                     data-sonuc="${kacir(s.sonuc || '')}" 
                     data-is-total="${isTotal ? '1' : '0'}"
                     style="--ka-stat-renk: ${gorsel.renk};">
                    <div class="d-flex align-items-center justify-content-between mb-1 gap-1">
                        <div class="d-flex align-items-center gap-1 min-w-0">
                            <span class="ka-stat-dot"></span>
                            <span class="ka-stat-label" title="${kacir(baslik)}">${kacir(baslik)}</span>
                        </div>
                        <i class="${gorsel.ikon} ka-stat-icon"></i>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mt-auto">
                        <span class="ka-stat-value">${sayi(adet)}</span>
                        ${!isTotal && toplam > 0 ? `<span class="ka-stat-ratio" title="Toplam içindeki payı">%${yuzde}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }).join(''));
    }

    function detayRaporYukle() {
        if (detayRaporTablo) {
            detayRaporTablo.ajax.reload();
            return;
        }

        const secenekler = {
            ...(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {}),
            processing: true,
            serverSide: true,
            ajax: {
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: function (d) {
                    d.action = 'puantaj-datatable';
                    d.sorgu_turu = 'KESME_ACMA';
                    d.start_date = tarihGonder('#kaDetayBaslangic');
                    d.end_date = tarihGonder('#kaDetayBitis');
                    if (seciliDetayFiltresi) {
                        d.work_result = seciliDetayFiltresi;
                    }
                },
                dataSrc: function (json) {
                    if (json.summary && json.summary.length && !seciliDetayFiltresi) {
                        sonDetayOzet = json.summary;
                    }
                    detayRaporOzetCiz(sonDetayOzet.length ? sonDetayOzet : json.summary);
                    return json.data || [];
                },
                error: function () {
                    hata('Detaylı rapor yüklenirken bir hata oluştu.');
                }
            },
            columns: [
                { data: 'tarih' },
                { data: 'ekip_kodu', defaultContent: '-' },
                { data: 'personel_adi', defaultContent: '-' },
                { data: 'is_emri_tipi', defaultContent: '-' },
                { data: 'is_emri_sonucu', defaultContent: '-' },
                { data: 'abone_no', defaultContent: '-' },
                { data: 'is_emri_no', defaultContent: '-' },
                { data: 'sonuclanmis', className: 'text-center' },
                { data: 'acik_olanlar', className: 'text-center' }
            ],
            order: [[0, 'desc']]
        };
        const ayar = typeof applyLengthStateSave === 'function'
            ? applyLengthStateSave(secenekler)
            : secenekler;
        try {
            if (!$.fn.DataTable) throw new Error('DataTables yüklenemedi.');
            detayRaporTablo = $('#kaTabloDetayRapor').DataTable(ayar);
        } catch (err) {
            $('#kaTabloDetayRapor tbody').html(`<tr><td colspan="9" class="text-center text-danger py-4">${kacir(err.message || 'Tablo başlatılamadı.')}</td></tr>`);
        }
    }

    // ---------- Günlük işlem matrisi ----------

    function matrisYukle() {
        istek({
            action: 'matris',
            baslangic: tarihGonder('#kaMatrisBaslangic'),
            bitis: tarihGonder('#kaMatrisBitis')
        }).done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            matrisVerisi = c;
            matrisSecim = null;
            matrisFiltreCiz();
            matrisCiz();
        });
    }

    function matrisGunleri() {
        const gunler = [];
        let gun = matrisVerisi.baslangic;
        while (gun <= matrisVerisi.bitis) {
            gunler.push(gun);
            const d = new Date(gun);
            d.setDate(d.getDate() + 1);
            gun = d.toISOString().substring(0, 10);
        }
        return gunler;
    }

    function matrisDeger(ekipId, tarih) {
        const gun = (matrisVerisi.matris[ekipId] || {})[tarih] || {};
        if (matrisSonuc === 'TUMU') {
            return Object.keys(gun).reduce((t, k) => t + gun[k], 0);
        }
        return gun[matrisSonuc] || 0;
    }

    function matrisFiltreCiz() {
        const secenekler = ['TUMU'].concat(matrisVerisi.sonuclar || []);
        $('#kaMatrisFiltre').html(secenekler.map(s =>
            `<button type="button" class="btn btn-sm ${s === matrisSonuc ? 'btn-primary' : 'btn-outline-secondary'} ka-matris-chip" data-s="${kacir(s)}">
                ${s === 'TUMU' ? 'Tümü' : kacir(s)}</button>`).join(''));
    }

    function matrisCiz() {
        const gunler = matrisGunleri();
        const ekipler = matrisVerisi.ekipler || [];

        if (!ekipler.length) {
            $('#kaMatris').html('<div class="text-muted small">Ekip bulunamadı.</div>');
            $('#kaMatrisOzet').html('');
            return;
        }

        let enBuyuk = 1;
        ekipler.forEach(e => gunler.forEach(g => { enBuyuk = Math.max(enBuyuk, matrisDeger(e.id, g)); }));

        const gunToplam = {};
        gunler.forEach(function (g) {
            gunToplam[g] = ekipler.reduce((t, e) => t + matrisDeger(e.id, g), 0);
        });

        let html = '<table class="table table-sm mb-0"><thead><tr><th class="sabit">Ekip</th>';
        gunler.forEach(function (g) {
            const pazar = new Date(g).getDay() === 0;
            const secili = matrisSecim && matrisSecim.tip === 'gun' && matrisSecim.deger === g;
            html += `<th class="${pazar ? 'pazar' : ''} ${secili ? 'secili' : ''} ka-matris-gun" data-g="${g}">
                ${Number(g.substring(8, 10))}<small class="d-block text-muted">${GUN_KISA[(new Date(g).getDay() + 6) % 7]}</small></th>`;
        });
        html += '<th>Toplam</th></tr></thead><tbody>';

        ekipler.forEach(function (e) {
            const seciliSatir = matrisSecim && matrisSecim.tip === 'ekip' && Number(matrisSecim.deger) === Number(e.id);
            html += `<tr class="${seciliSatir ? 'secili' : ''}"><td class="sabit ka-matris-ekip" data-e="${e.id}">
                <b>${kacir(e.personel || ekipKisa(e.tur_adi))}</b><small class="d-block text-muted">${kacir(ekipKisa(e.tur_adi))}</small></td>`;
            let satirToplam = 0;
            gunler.forEach(function (g) {
                const deger = matrisDeger(e.id, g);
                satirToplam += deger;
                const pazar = new Date(g).getDay() === 0;
                const yogunluk = deger ? (0.08 + 0.5 * (deger / enBuyuk)).toFixed(2) : 0;
                const seciliSutun = matrisSecim && matrisSecim.tip === 'gun' && matrisSecim.deger === g;
                html += `<td class="deger ${pazar ? 'pazar' : ''} ${seciliSutun ? 'secili' : ''}"
                    style="${deger ? 'background:rgba(85,110,230,' + yogunluk + ')' : ''}">${deger || '<span class="text-muted">·</span>'}</td>`;
            });
            html += `<td class="deger fw-bold">${sayi(satirToplam)}</td></tr>`;
        });

        html += '<tr class="table-light"><td class="sabit fw-bold">Toplam</td>';
        let genelToplam = 0;
        gunler.forEach(function (g) {
            genelToplam += gunToplam[g];
            html += `<td class="deger fw-bold ${new Date(g).getDay() === 0 ? 'pazar' : ''}">${gunToplam[g] || '-'}</td>`;
        });
        html += `<td class="deger fw-bold">${sayi(genelToplam)}</td></tr></tbody></table>`;

        $('#kaMatris').html(html);
        matrisOzetCiz();
    }

    function matrisOzetCiz() {
        const sonuclar = matrisVerisi.sonuclar || [];
        const gunler = matrisGunleri();
        const ekipler = matrisVerisi.ekipler || [];
        const toplamlar = {};
        sonuclar.forEach(s => { toplamlar[s] = 0; });

        let baslik = 'Seçili aralığın tamamı';
        ekipler.forEach(function (e) {
            if (matrisSecim && matrisSecim.tip === 'ekip' && Number(matrisSecim.deger) !== Number(e.id)) return;
            gunler.forEach(function (g) {
                if (matrisSecim && matrisSecim.tip === 'gun' && matrisSecim.deger !== g) return;
                const gunVerisi = (matrisVerisi.matris[e.id] || {})[g] || {};
                Object.keys(gunVerisi).forEach(function (s) {
                    toplamlar[s] = (toplamlar[s] || 0) + gunVerisi[s];
                });
            });
        });

        if (matrisSecim && matrisSecim.tip === 'gun') {
            baslik = tarihTR(matrisSecim.deger) + ' — tüm ekipler';
        } else if (matrisSecim && matrisSecim.tip === 'ekip') {
            const ekip = ekipler.find(e => Number(e.id) === Number(matrisSecim.deger));
            baslik = (ekip ? (ekip.personel || ekip.tur_adi) : '') + ' — seçili aralık';
        }

        const genel = Object.keys(toplamlar).reduce((t, k) => t + toplamlar[k], 0);
        let html = `<div class="col-12"><div class="text-muted small mb-1">${kacir(baslik)} · <b>${sayi(genel)}</b> işlem</div></div>`;
        Object.keys(toplamlar).sort((a, b) => toplamlar[b] - toplamlar[a]).forEach(function (s, idx) {
            const val = toplamlar[s];
            const gorsel = getSonucGorsel(s, idx, false);
            const yuzde = genel > 0 ? Math.round((val / genel) * 100) : 0;
            html += `<div class="col-6 col-md-3 col-xl-2">
                <div class="card shadow-none ka-stat-card h-100" style="--ka-stat-renk: ${gorsel.renk};">
                    <div class="d-flex align-items-center justify-content-between mb-1 gap-1">
                        <div class="d-flex align-items-center gap-1 min-w-0">
                            <span class="ka-stat-dot"></span>
                            <span class="ka-stat-label" title="${kacir(s)}">${kacir(s)}</span>
                        </div>
                        <i class="${gorsel.ikon} ka-stat-icon"></i>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mt-auto">
                        <span class="ka-stat-value">${sayi(val)}</span>
                        ${genel > 0 ? `<span class="ka-stat-ratio" title="Toplam içindeki payı">%${yuzde}</span>` : ''}
                    </div>
                </div>
            </div>`;
        });
        $('#kaMatrisOzet').html(html);
    }

    // ---------- Olaylar ----------

    $(document).on('click', '.ka-mahalle-chip', function () {
        mahalleFiltre = $(this).data('f');
        mahalleFiltreCiz();
        mahalleCiz();
    });

    $('#kaBtnMahalleYenile').on('click', mahalleYukle);

    $('#kaBtnMahalleEkle').on('click', function () {
        $('#kaMahalleId').val('');
        $('#kaMahalleAd').val('');
        $('#kaMahalleKod').val('');
        $('#kaMahalleIlce').val('').trigger('change');
        $('#kaMahalleHavuz').val(1).trigger('change');
        $('#kaModalMahalle .modal-title').text('Mahalle Ekle');
        new bootstrap.Modal(document.getElementById('kaModalMahalle')).show();
    });

    $(document).on('click', '.ka-mahalle-duzenle', function () {
        const mahalle = mahalleVerisi.find(m => Number(m.id) === Number($(this).data('id')));
        if (!mahalle) return;
        $('#kaMahalleId').val(mahalle.id);
        $('#kaMahalleAd').val(mahalle.ad);
        $('#kaMahalleKod').val(mahalle.kod_araligi || '');
        $('#kaMahalleIlce').val(mahalle.ilce).trigger('change');
        $('#kaMahalleHavuz').val(mahalle.havuzda).trigger('change');
        $('#kaModalMahalle .modal-title').text('Mahalle Düzenle');
        new bootstrap.Modal(document.getElementById('kaModalMahalle')).show();
    });

    $('#kaFormMahalle').on('submit', function (e) {
        e.preventDefault();
        istek({
            action: 'mahalle-kaydet',
            id: $('#kaMahalleId').val(),
            ad: $('#kaMahalleAd').val(),
            ilce: $('#kaMahalleIlce').val(),
            kod_araligi: $('#kaMahalleKod').val(),
            havuzda: $('#kaMahalleHavuz').val()
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            bootstrap.Modal.getInstance(document.getElementById('kaModalMahalle')).hide();
            basari(c.message);
            mahalleYukle();
        });
    });

    $(document).on('click', '.ka-mesaj-at', function () {
        $('#kaMesajMahalleId').val($(this).data('id'));
        $('#kaMesajMahalleAd').text($(this).data('ad'));
        new bootstrap.Modal(document.getElementById('kaModalMesaj')).show();
    });

    $('#kaFormMesaj').on('submit', function (e) {
        e.preventDefault();
        istek({
            action: 'mesaj-kaydet',
            mahalle_id: $('#kaMesajMahalleId').val(),
            mesaj_tarihi: tarihGonder('#kaMesajTarih')
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            bootstrap.Modal.getInstance(document.getElementById('kaModalMesaj')).hide();
            basari(c.message);
            mahalleYukle();
            ozetYukle();
        });
    });

    $(document).on('click', '.ka-mahalle-ata', function () {
        $('#kaAtamaEkip').val('').trigger('change');
        $('#kaAtamaMahalle').val($(this).data('id'));
        new bootstrap.Modal(document.getElementById('kaModalAtama')).show();
    });

    $('#kaFormAtama').on('submit', function (e) {
        e.preventDefault();
        atamaYap($('#kaAtamaEkip').val(), $('#kaAtamaMahalle').val(), tarihGonder('#kaAtamaBaslangic'), function () {
            bootstrap.Modal.getInstance(document.getElementById('kaModalAtama')).hide();
        });
    });

    $(document).on('click', '.ka-hizli-ata', function () {
        atamaYap($(this).data('ekip'), $(this).data('mahalle'), BUGUN);
    });

    function atamaYap(ekipId, mahalleId, baslangic, sonra) {
        if (!ekipId || !mahalleId) return hata('Ekip ve mahalle seçilmelidir.');

        istek({ action: 'atama-yap', ekip_id: ekipId, mahalle_id: mahalleId, baslangic: baslangic }, 'POST')
            .done(function (c) {
                if (c.status !== 'success') return hata(c.message);
                if (typeof sonra === 'function') sonra();
                basari(c.message);
                mahalleYukle();
                ekipYukle();
                ozetYukle();
                gecmisYukle();
            });
    }

    $(document).on('change', '.ka-kalan-is', function () {
        const alan = $(this);
        istek({
            action: 'kalan-is-kaydet',
            ekip_id: alan.data('ekip'),
            tarih: BUGUN,
            kalan_is: alan.val()
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            ekipYukle();
            ozetYukle();
        });
    });

    $('#kaTumEkipler').on('change', ekipYukle);

    $(document).on('click', '.ka-gecmis-ac', function () {
        $('#kaGecmisEkip').val($(this).data('ekip')).trigger('change');
        $('a[href="#pane-ka-gecmis"]').tab('show');
    });

    $('#kaGecmisEkip, #kaGecmisIlce').on('change', gecmisYukle);

    $(document).on('click', '.ka-atama-kapat', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Atama kapatılsın mı?',
            input: 'text',
            inputLabel: 'Bitiş tarihi (gg.aa.yyyy)',
            inputValue: tarihTR(BUGUN),
            showCancelButton: true,
            confirmButtonText: 'Kapat',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'atama-kapat', id: id, bitis: sonuc.value }, 'POST').done(function (c) {
                if (c.status !== 'success') return hata(c.message);
                basari(c.message);
                gecmisYukle();
                ekipYukle();
                mahalleYukle();
            });
        });
    });

    $(document).on('click', '.ka-atama-sil', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Kayıt silinsin mi?',
            text: 'Atama geçmişinden kalıcı olarak silinir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sil',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'atama-sil', id: id }, 'POST').done(function (c) {
                if (c.status !== 'success') return hata(c.message);
                basari(c.message);
                gecmisYukle();
                ekipYukle();
                mahalleYukle();
            });
        });
    });

    $('#kaAyGeri, #kaAyIleri').on('click', function () {
        const d = new Date(nobetAyi);
        d.setMonth(d.getMonth() + (this.id === 'kaAyIleri' ? 1 : -1));
        nobetAyi = d.toISOString().substring(0, 10);
        nobetYukle();
    });

    $('#kaBtnNobetUret').on('click', function () {
        if (!nobetVerisi) return;

        const buHafta = haftaBasi(BUGUN);
        const secenekler = [];
        let hafta = haftaBasi(nobetVerisi.ay_basi);
        while (hafta <= nobetVerisi.ay_sonu) {
            if (hafta >= buHafta) secenekler.push(hafta);
            const d = new Date(hafta);
            d.setDate(d.getDate() + 7);
            hafta = d.toISOString().substring(0, 10);
        }
        if (!secenekler.length) {
            const sonraki = new Date(buHafta);
            secenekler.push(buHafta, new Date(sonraki.setDate(sonraki.getDate() + 7)).toISOString().substring(0, 10));
        }

        $('#kaPlanHafta').html(secenekler.map(function (h) {
            const bitis = new Date(h);
            bitis.setDate(bitis.getDate() + 6);
            const etiket = tarihTR(h) + ' – ' + tarihTR(bitis.toISOString().substring(0, 10))
                + (h === buHafta ? ' (bu hafta)' : '');
            return `<option value="${h}">${etiket}</option>`;
        }).join(''));

        select2Kur('#kaPlanHafta', '#kaModalPlanUret');
        select2Deger('#kaPlanHafta', secenekler[0]);
        new bootstrap.Modal(document.getElementById('kaModalPlanUret')).show();
    });

    $('#kaFormPlanUret').on('submit', function (e) {
        e.preventDefault();
        const dugme = $(this).find('button[type="submit"]').prop('disabled', true);

        istek({ action: 'nobet-uret', hafta_basi: $('#kaPlanHafta').val() }, 'POST')
            .done(function (c) {
                dugme.prop('disabled', false);
                if (c.status !== 'success') return hata(c.message);
                bootstrap.Modal.getInstance(document.getElementById('kaModalPlanUret')).hide();
                basari(c.message);
                nobetYukle();
                ozetYukle();
            })
            .fail(function () {
                dugme.prop('disabled', false);
                hata('Plan üretilemedi.');
            });
    });

    $(document).on('click', '.ka-saha-hucre', function () {
        if (!yetki.nobet || !nobetVerisi) return;

        const tarih = $(this).data('tarih');
        const hafta = haftaBasi(tarih);
        const ilcedekiler = [];
        Object.keys(nobetVerisi.ilce[hafta] || {}).forEach(function (i) {
            ilcedekiler.push(Number(nobetVerisi.ilce[hafta][i].ekip_id));
        });

        const alan = $('#kaSahaNobetEkip');
        alan.find('option').each(function () {
            const id = Number(this.value);
            const personel = (nobetVerisi.saha_personelleri || []).find(p => Number(p.id) === id);
            const ilcede = this.value && personel && ilcedekiler.indexOf(Number(personel.ekip_id)) !== -1;
            $(this).prop('disabled', ilcede)
                .text($(this).text().replace(/ \(ilçede\)$/, '') + (ilcede ? ' (ilçede)' : ''));
        });
        select2Kur(alan, '#kaModalSahaNobet');
        select2Deger(alan, nobetVerisi.saha[tarih] ? String(nobetVerisi.saha[tarih].personel_id) : '');

        const ilceAdlari = ilcedekiler.map(id => ekipPersoneli(id) || ('#' + id)).filter(Boolean);
        $('#kaSahaNobetUyari').toggleClass('d-none', !ilceAdlari.length)
            .text('Bu hafta ilçede olduğu için nöbete yazılamayanlar: ' + ilceAdlari.join(', '));

        $('#kaSahaNobetGun').val(tarih);
        $('#kaSahaNobetTarih').text(tarihTR(tarih));
        new bootstrap.Modal(document.getElementById('kaModalSahaNobet')).show();
    });

    $('#kaFormSahaNobet').on('submit', function (e) {
        e.preventDefault();
        istek({
            action: 'nobet-saha-yaz',
            tarih: $('#kaSahaNobetGun').val(),
            personel_id: $('#kaSahaNobetEkip').val() || 0
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            bootstrap.Modal.getInstance(document.getElementById('kaModalSahaNobet')).hide();
            nobetYukle();
            ozetYukle();
        });
    });

    $(document).on('click', '.ka-telefon-hucre', function () {
        if (!yetki.nobet || !nobetVerisi) return;

        const tarih = $(this).data('tarih');
        $('#kaTelefonNobetGun').val(tarih);
        $('#kaTelefonNobetTarih').text(tarihTR(tarih));
        select2Kur('#kaTelefonNobetPersonel', '#kaModalTelefonNobet');
        select2Deger('#kaTelefonNobetPersonel', nobetVerisi.telefon[tarih] ? String(nobetVerisi.telefon[tarih].personel_id) : '');
        new bootstrap.Modal(document.getElementById('kaModalTelefonNobet')).show();
    });

    $('#kaFormTelefonNobet').on('submit', function (e) {
        e.preventDefault();
        const personelId = $('#kaTelefonNobetPersonel').val();
        if (!personelId) return hata('Personel seçilmelidir.');

        istek({
            action: 'nobet-telefon-yaz',
            tarih: $('#kaTelefonNobetGun').val(),
            personel_id: personelId
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            bootstrap.Modal.getInstance(document.getElementById('kaModalTelefonNobet')).hide();
            nobetYukle();
            ozetYukle();
        });
    });

    $(document).on('change', '.ka-ilce-sec', function () {
        const alan = $(this);
        if (!alan.val()) return;
        istek({
            action: 'nobet-ilce-yaz',
            hafta_basi: alan.data('hafta'),
            ilce: alan.data('ilce'),
            ekip_id: alan.val()
        }, 'POST').done(function (c) {
            if (c.status !== 'success') return hata(c.message);
            nobetYukle();
        });
    });

    $(document).on('click', '.ka-matris-chip', function () {
        matrisSonuc = $(this).data('s');
        matrisFiltreCiz();
        matrisCiz();
    });

    $(document).on('click', '.ka-matris-gun', function () {
        const gun = $(this).data('g');
        matrisSecim = (matrisSecim && matrisSecim.tip === 'gun' && matrisSecim.deger === gun) ? null : { tip: 'gun', deger: gun };
        matrisCiz();
    });

    $(document).on('click', '.ka-matris-ekip', function () {
        const ekip = $(this).data('e');
        matrisSecim = (matrisSecim && matrisSecim.tip === 'ekip' && matrisSecim.deger === ekip) ? null : { tip: 'ekip', deger: ekip };
        matrisCiz();
    });

    $(document).on('click', '#kaDetayRaporOzet .ka-stat-card', function () {
        const isTotal = $(this).data('is-total') === 1 || $(this).data('is-total') === '1';
        const sonuc = $(this).data('sonuc') || '';

        if (isTotal || seciliDetayFiltresi === sonuc) {
            seciliDetayFiltresi = '';
        } else {
            seciliDetayFiltresi = sonuc;
        }

        detayRaporOzetCiz(sonDetayOzet);
        if (detayRaporTablo) {
            detayRaporTablo.ajax.reload();
        }
    });

    $('#kaMatrisBaslangic, #kaMatrisBitis').on('change', matrisYukle);
    $('#kaBtnOzetRapor').on('click', ozetRaporYukle);
    $('#kaBtnDetayRapor').on('click', function () {
        seciliDetayFiltresi = '';
        sonDetayOzet = [];
        detayRaporYukle();
    });
    $('input[name="kaRaporFiltreTipi"]').on('change', function () {
        const aralik = this.value === 'range';
        $('.ka-rapor-donem').toggleClass('d-none', aralik);
        $('.ka-rapor-aralik').toggleClass('d-none', !aralik);
    });
    $(document).on('click', '#kaBtnOzetTamEkran', function () {
        const elem = document.getElementById('kaOzetRaporIcerik');
        if (!document.fullscreenElement) elem.requestFullscreen();
        else document.exitFullscreen();
    });

    $(window).on('resize', ozetRaporYukseklikAyarla);
    document.addEventListener('fullscreenchange', function () {
        window.setTimeout(ozetRaporYukseklikAyarla, 30);
    });
    $('#kaOzetRaporFiltreCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
        window.setTimeout(ozetRaporYukseklikAyarla, 30);
    });

    $('a[href="#pane-ka-detay-rapor"]').on('click', function () {
        window.setTimeout(detayRaporYukle, 180);
    });

    // ---------- Özet kartlarını gizle/göster ----------
    const OZET_SAKLI_ANAHTAR = 'kaOzetKapali';

    function ozetGorunumUygula(kapali, animasyonlu) {
        const satir = $('#kaOzetSatir');
        const dugme = $('#kaOzetToggle');
        if (!animasyonlu) satir.css('transition', 'none');
        satir.toggleClass('ka-kapali', kapali);
        if (!animasyonlu) satir[0] && satir[0].offsetHeight && satir.css('transition', '');
        dugme.toggleClass('ka-donuk', kapali)
            .attr('title', kapali ? 'Özet kartlarını göster' : 'Özet kartlarını gizle')
            .find('i').attr('class', kapali ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    }

    $('#kaOzetToggle').on('click', function () {
        const kapaliOlacak = !$('#kaOzetSatir').hasClass('ka-kapali');
        ozetGorunumUygula(kapaliOlacak, true);
        try { localStorage.setItem(OZET_SAKLI_ANAHTAR, kapaliOlacak ? '1' : '0'); } catch (e) { /* yoksay */ }
    });

    (function ozetBaslangicDurumu() {
        let kapali = false;
        try { kapali = localStorage.getItem(OZET_SAKLI_ANAHTAR) === '1'; } catch (e) { /* yoksay */ }
        if (kapali) ozetGorunumUygula(true, false);
    })();

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const hedef = $(e.target).attr('href');
        if (hedef === '#pane-ka-mahalle' && !mahalleVerisi.length) mahalleYukle();
        if (hedef === '#pane-ka-nobet' && !nobetVerisi) nobetYukle();
        if (hedef === '#pane-ka-ozet-rapor' && !ozetRaporYuklendi) {
            ['#kaRaporYil', '#kaRaporAy', '#kaRaporPersonel', '#kaRaporBolge', '#kaRaporDefter']
                .forEach(function (alan) { select2Kur(alan); });
            ozetRaporYukle();
        }
        if (hedef === '#pane-ka-ozet-rapor') window.setTimeout(ozetRaporYukseklikAyarla, 30);
        else $('html, body').css('overflow-y', '');
        if (hedef === '#pane-ka-detay-rapor') detayRaporYukle();
        if (hedef === '#pane-ka-matris' && !matrisVerisi) matrisYukle();
    });

    $(function () {
        ozetYukle();
        mahalleYukle();
        ekipYukle();
        gecmisYukle();
    });

})(jQuery);
