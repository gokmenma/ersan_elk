(function ($) {
    'use strict';

    const API = 'views/aparat-takip/api.php';
    const yetki = window.aparatYetki || {};

    const dtOrnekleri = {};
    let aktifSayimId = 0;
    let aktifIslemId = 0;
    let transferDurum = '';
    let sonMatris = null;

    const HAVUZ_TURLERI = {
        depo_giris: { baslik: 'Depoya Giriş', aciklama: 'Satın alınan aparatı depoya ekleyin.', ekip: false, kaynak: null },
        depo_cikis: { baslik: 'Ekibe Ver', aciklama: 'Depodan düşer, seçilen ekibin zimmetine geçer.', ekip: true, kaynak: 'depo' },
        depo_iade: { baslik: 'İade Al', aciklama: 'Ekibin zimmetinden düşer, depoya geri girer.', ekip: true, kaynak: 'ekip' },
        hurda: { baslik: 'Hurdaya Ayır', aciklama: 'Ekipten düşer, hurdaya yazılır. Gerekçe zorunlu.', ekip: true, kaynak: 'ekip' },
        kayip: { baslik: 'Kayıp Kaydı', aciklama: 'Ekipten düşer, kayıp olarak yazılır. Gerekçe zorunlu.', ekip: true, kaynak: 'ekip' },
        acilis: { baslik: 'Açılış Stoğu', aciklama: 'Ekibin elinde zaten bulunan aparatı ilk kurulumda bir kez girin.', ekip: true, kaynak: null }
    };

    function esikSinifi(adet, seviye) {
        if (adet < 0 || seviye === 'bitti') return 'adet-negatif';
        if (seviye === 'azaldi') return 'adet-kritik';
        return '';
    }

    // ---------- Yardımcılar ----------

    function kacir(deger) {
        return $('<div>').text(deger === null || deger === undefined ? '' : deger).html();
    }

    function tarihGoster(deger) {
        if (!deger) return '';
        const parca = String(deger).split(' ');
        const gun = parca[0].split('-');
        if (gun.length !== 3) return String(deger);
        const saat = parca[1] ? ' ' + parca[1].substring(0, 5) : '';
        return `${gun[2]}.${gun[1]}.${gun[0]}${saat}`;
    }

    function tarihGonder(secici) {
        const deger = $(secici).val();
        if (!deger) return '';
        const p = deger.split('.');
        return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : deger;
    }

    function hata(mesaj) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: mesaj || 'İşlem tamamlanamadı.',
            showConfirmButton: false,
            showCloseButton: true,
            timer: 4500,
            timerProgressBar: true
        });
    }

    function basari(mesaj) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: mesaj || 'İşlem tamamlandı.',
            showConfirmButton: false,
            showCloseButton: true,
            timer: 3500,
            timerProgressBar: true
        });
    }

    function geriAlBildirimi(mesaj, grupIdleri) {
        if (!grupIdleri || !grupIdleri.length) return basari(mesaj);

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: mesaj,
            showConfirmButton: true,
            confirmButtonText: 'Geri Al',
            showCloseButton: true,
            timer: 15000,
            timerProgressBar: true
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'havuz-geri-al', grup_idleri: JSON.stringify(grupIdleri) }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                stokYukle();
                if (dtOrnekleri['#tabloHareketler']) hareketleriYukle();
            }).fail(() => hata('Geri alınamadı.'));
        });
    }

    function istek(veri, tip) {
        return $.ajax({ url: API, type: tip || 'GET', data: veri, dataType: 'json' });
    }

    /**
     * Projedeki DataTable standardı: applyLengthStateSave({ ...getDatatableOptions(), ... }).
     * Kolon filtreleri getDatatableOptions() içindeki initComplete tarafından
     * `data-filter` niteliğine bakılarak başlatılır; tablo yeniden kurulurken
     * eski filtre satırı temizlenmezse ikinci kez eklenmez.
     */
    function dtKur(secici, ayarlar) {
        if (dtOrnekleri[secici]) {
            dtOrnekleri[secici].destroy();
            $(secici).find('thead .dt-filter-row').remove();
            $(secici).find('tbody').empty();
        }

        const temel = (typeof getDatatableOptions === 'function') ? getDatatableOptions() : {};
        let secenekler = $.extend(true, { autoWidth: false }, temel, ayarlar || {});

        if (typeof applyLengthStateSave === 'function') {
            secenekler = applyLengthStateSave(secenekler);
        }

        dtOrnekleri[secici] = $(secici).DataTable(secenekler);
        return dtOrnekleri[secici];
    }

    /** Metin verisini görünümde HTML'e sarar; filtre ve sıralama düz metinle çalışır. */
    function bicim(fn) {
        return function (veri, tip, satir) {
            return tip === 'display' ? fn(veri, satir) : (veri === null ? '' : veri);
        };
    }

    const ISLEM_ADI = { kesme: 'Kesme', acma: 'Açma' };
    const DURUM_ADI = {
        aktif: 'Aktif', iptal: 'İptal',
        beklemede: 'Beklemede', onaylandi: 'Onaylandı', reddedildi: 'Reddedildi'
    };
    const DURUM_RENK = {
        aktif: 'success', iptal: 'secondary',
        beklemede: 'warning', onaylandi: 'success', reddedildi: 'danger'
    };

    function durumRozeti(metin) {
        const anahtar = Object.keys(DURUM_ADI).find(function (k) { return DURUM_ADI[k] === metin; }) || '';
        const renk = DURUM_RENK[anahtar] || 'secondary';
        return `<span class="badge bg-${renk}-subtle text-${renk}">${kacir(metin)}</span>`;
    }

    // ---------- Stok matrisi ----------

    let stokSifirlariGizle = true;
    try {
        const kayitliFiltre = localStorage.getItem('aparat_stok_sifir_gizle');
        if (kayitliFiltre !== null) stokSifirlariGizle = kayitliFiltre === '1';
    } catch (e) {}
    let sonStokYaniti = null;

    function stokFiltreButonGuncelle() {
        const btn = $('#btnStokSifirlariGizle');
        if (!btn.length) return;
        btn.toggleClass('btn-primary', stokSifirlariGizle)
            .toggleClass('btn-outline-secondary', !stokSifirlariGizle)
            .attr('aria-pressed', stokSifirlariGizle ? 'true' : 'false')
            .html(stokSifirlariGizle
                ? '<i class="bx bx-show me-1"></i> Sıfırları Göster'
                : '<i class="bx bx-hide me-1"></i> Sıfırları Gizle');
    }

    function stokMatrisCiz(res) {
        const veri = res.data;
        sonMatris = veri;
        const kritik = res.kritik || { depo: [], ekipler: [], toplam: 0 };
        const kritikHarita = {};
        kritik.depo.forEach(function (e) { kritikHarita['depo_' + e.aparat_tip_id] = e.seviye; });
        kritik.ekipler.forEach(function (k) {
            k.eksikler.forEach(function (e) { kritikHarita[k.ekip_id + '_' + e.aparat_tip_id] = e.seviye; });
        });
        const altlik = $('#tabloStokMatris tfoot').empty();

        // Ekip satırları tabloda sıralanabilir kalsın; depo/saha/hurda/kayıp
        // havuzları ile toplam satırı tfoot'ta sabitlenir.
        const ekipSatirlari = veri.satirlar.filter(function (s) { return s.sahip_tipi === 'ekip' || s.sahip_tipi === 'personel'; });
        const gorunenEkipSatirlari = stokSifirlariGizle
            ? ekipSatirlari.filter(function (s) {
                return veri.tipler.some(function (tip) { return (s.adetler[tip.id] || 0) !== 0; }) || (s.toplam || 0) !== 0;
            })
            : ekipSatirlari;
        const havuzSatirlari = veri.satirlar.filter(function (s) {
            if (s.sahip_tipi === 'ekip' || s.sahip_tipi === 'personel') return false;
            return true;
        });

            let sahaToplam = 0;
            let depoToplam = 0;
            let ekipToplam = 0;
            veri.satirlar.forEach(function (s) {
                if (s.sahip_tipi === 'saha') sahaToplam = s.toplam;
                if (s.sahip_tipi === 'depo') depoToplam = s.toplam;
                if (s.sahip_tipi === 'ekip' || s.sahip_tipi === 'personel') ekipToplam += s.toplam;
            });

            const kolonlar = [{
                data: 'ekip',
                render: bicim(function (deger, satir) {
                    return `<span class="fw-medium text-dark">${kacir(satir.ekip_kodu)}</span>${satir.bolge ? ` <small class="text-muted">· ${kacir(satir.bolge)}</small>` : ''}`;
                })
            }, {
                data: 'personel',
                render: bicim(function (deger, satir) {
                    return satir.uyeler
                        ? `<span class="fw-semibold text-dark">${kacir(satir.uyeler)}</span>`
                        : '<span class="text-muted">Personel atanmamış</span>';
                })
            }];

            veri.tipler.forEach(function (tip) {
                kolonlar.push({
                    data: 'adet_' + tip.id,
                    className: 'adet-hucre',
                    render: bicim(function (adet) {
                        if (adet === 0) return '<span class="text-muted fw-normal">0</span>';
                        if (adet < 0) return `<span class="text-danger">${adet}</span>`;
                        return String(adet);
                    })
                });
            });

            kolonlar.push({ data: 'toplam', className: 'adet-hucre' });

            const satirlar = gorunenEkipSatirlari.map(function (s) {
                const satir = {
                    sahip_tipi: s.sahip_tipi,
                    sahip_id: s.sahip_id,
                    ekip: (s.ekip_kodu || '') + (s.bolge ? ' · ' + s.bolge : ''),
                    personel: s.uyeler || 'Personel atanmamış',
                    uyeler: s.uyeler,
                    ekip_kodu: s.ekip_kodu,
                    bolge: s.bolge,
                    toplam: s.toplam
                };
                veri.tipler.forEach(function (tip) {
                    satir['adet_' + tip.id] = s.adetler[tip.id] || 0;
                });
                return satir;
            });

            dtKur('#tabloStokMatris', {
                data: satirlar,
                columns: kolonlar,
                order: [[kolonlar.length - 1, 'desc'], [0, 'asc']],
                createdRow: function (row, data) {
                    veri.tipler.forEach(function (tip, i) {
                        const sinif = esikSinifi(data['adet_' + tip.id], kritikHarita[data.sahip_id + '_' + tip.id]);
                        if (sinif) $('td', row).eq(i + 2).addClass(sinif);
                    });
                }
            });

            let personelHucreleri = '';
            veri.tipler.forEach(function (tip) {
                const adet = ekipSatirlari.reduce(function (toplam, s) {
                    return toplam + (s.adetler[tip.id] || 0);
                }, 0);
                const sinif = adet === 0 ? ' adet-sifir' : (adet < 0 ? ' adet-negatif' : '');
                personelHucreleri += `<td class="adet-hucre${sinif}">${adet}</td>`;
            });
            altlik.append(`<tr class="havuz-satiri">
                <td colspan="2"><i class="bx bx-group me-1 text-muted"></i><b>Personelde</b></td>
                ${personelHucreleri}
                <td class="adet-hucre">${ekipToplam}</td>
            </tr>`);

            havuzSatirlari.forEach(function (s) {
                let hucreler = '';
                veri.tipler.forEach(function (tip) {
                    const adet = s.adetler[tip.id] || 0;
                    let sinif = adet === 0 ? ' adet-sifir' : '';
                    if (s.sahip_tipi === 'depo') {
                        const depoSinif = esikSinifi(adet, kritikHarita['depo_' + tip.id]);
                        if (depoSinif) sinif = ' ' + depoSinif;
                    } else if (adet < 0 && s.sahip_tipi !== 'kayip') {
                        sinif = ' adet-negatif';
                    }
                    hucreler += `<td class="adet-hucre${sinif}" data-tip-id="${tip.id}">${adet}</td>`;
                });
                const baslik = s.sahip_tipi === 'saha' ? 'Abonede Takılı' : s.baslik;
                const ikon = { depo: 'bx-buildings', saha: 'bx-map-pin', hurda: 'bx-trash', kayip: 'bx-error' }[s.sahip_tipi] || 'bx-box';
                altlik.append(`<tr class="havuz-satiri havuz-tiklanabilir" data-sahip-tipi="${kacir(s.sahip_tipi)}">
                    <td colspan="2"><i class="bx ${ikon} me-1 text-muted"></i><b>${kacir(baslik)}</b></td>
                    ${hucreler}
                    <td class="adet-hucre">${s.toplam}</td>
                </tr>`);
            });

            let toplamHucreleri = '';
            veri.tipler.forEach(function (tip) {
                toplamHucreleri += `<td class="adet-hucre">${veri.sutun_toplam[tip.id] || 0}</td>`;
            });
            if (yetki.gelismis) {
                altlik.append(`<tr><td colspan="2">TOPLAM</td>${toplamHucreleri}<td class="adet-hucre">${veri.genel_toplam}</td></tr>`);
            }

            $('#ozetDepo').text(depoToplam);
            $('#ozetEkip').text(ekipToplam);
            const personelSayisi = ekipSatirlari.filter((x) => x.sahip_tipi === 'personel').length;
            $('#ozetEkipAlt').text((ekipSatirlari.length - personelSayisi) + ' ekip'
                + (personelSayisi ? ' + ' + personelSayisi + ' personel' : '') + ' zimmetinde');
            $('#ozetSaha').text(sahaToplam);
            const havuzToplam = function (tur) {
                const satir = veri.satirlar.find(function (x) { return x.sahip_tipi === tur; });
                return satir ? satir.toplam : 0;
            };
            $('#ozetHurda').text(havuzToplam('hurda'));
            $('#ozetKayip').text(havuzToplam('kayip'));
            kritikGoster(kritik);

            if (res.kirma_bekleyen > 0) {
                $('#kirmaBekleyenSayi').text(res.kirma_bekleyen);
                $('#aparatKirmaBekleyen').removeClass('d-none').addClass('d-flex');
            } else {
                $('#aparatKirmaBekleyen').addClass('d-none').removeClass('d-flex');
            }

            if (res.tutarsiz_satir > 0) {
                $('#tutarsizSayi').text(res.tutarsiz_satir);
                $('#aparatTutarsizlikUyari').removeClass('d-none').addClass('d-flex');
            } else {
                $('#aparatTutarsizlikUyari').addClass('d-none').removeClass('d-flex');
            }
    }

    function stokYukle() {
        stokFiltreButonGuncelle();
        istek({ action: 'stok-matris' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            sonStokYaniti = res;
            stokMatrisCiz(res);
        }).fail(() => hata('Stok tablosu yüklenemedi.'));
    }

    $(document).on('click', '#btnStokSifirlariGizle', function () {
        stokSifirlariGizle = !stokSifirlariGizle;
        try { localStorage.setItem('aparat_stok_sifir_gizle', stokSifirlariGizle ? '1' : '0'); } catch (e) {}
        stokFiltreButonGuncelle();
        if (sonStokYaniti) stokMatrisCiz(sonStokYaniti);
    });

    function sahipHareketGecmisiAc(satir, aparatTipId) {
        if (!satir || (!satir.sahip_id && !satir.sahip_tipi)) return;
        const tipId = parseInt(aparatTipId, 10) || 0;
        const tipObj = tipId > 0 && sonMatris && Array.isArray(sonMatris.tipler)
            ? sonMatris.tipler.find(function (t) { return parseInt(t.id, 10) === tipId; })
            : null;
        const baslikEk = tipObj ? tipObj.ad + ' Hareketleri' : 'Hareket Geçmişi';
        const mevcutAdet = tipObj ? (satir['adet_' + tipObj.id] ?? 0) : (satir.toplam ?? 0);

        $('#sahipGecmisBaslik').text((satir.personel || 'Personel atanmamış') + ' — ' + baslikEk);
        $('#sahipGecmisAltBaslik').text((satir.ekip ? satir.ekip + ' · ' : '') + 'Mevcut: ' + mevcutAdet + ' adet');
        $('#sahipGecmisIcerik').html('<div class="text-center text-muted py-5"><i class="bx bx-loader-alt bx-spin fs-3 d-block mb-2"></i>Hareket geçmişi yükleniyor...</div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSahipHareketGecmisi')).show();

        istek({
            action: 'hareket-listesi',
            sahip_tipi: satir.sahip_tipi || 'ekip',
            sahip_id: satir.sahip_id || 0,
            aparat_tip_id: tipId,
            tum_tarih: 1
        }).done(function (res) {
            if (res.status !== 'success') {
                $('#sahipGecmisIcerik').html(`<div class="alert alert-danger m-3 mb-0">${kacir(res.message || 'Hareketler alınamadı.')}</div>`);
                return;
            }
            const kayitlar = res.data || [];
            if (!kayitlar.length) {
                $('#sahipGecmisIcerik').html('<div class="text-center text-muted py-5">Bu kayıt için henüz aparat hareketi bulunmuyor.</div>');
                return;
            }
            const hedefTip = satir.sahip_tipi || 'ekip';
            const hedefId = parseInt(satir.sahip_id, 10) || 0;
            const tarafEslesir = function (taraf) {
                if (!taraf || taraf.tip !== hedefTip) return false;
                return hedefId === 0 || parseInt(taraf.id, 10) === hedefId;
            };

            let giren = 0;
            let cikan = 0;
            const satirlarHtml = kayitlar.map(function (h) {
                const adet = parseInt(h.adet, 10) || 0;
                const iptal = h.iptal_mi == 1;
                const girdiMi = tarafEslesir(h.hedef);
                const ciktiMi = tarafEslesir(h.kaynak);
                if (!iptal) {
                    if (girdiMi) giren += adet;
                    if (ciktiMi) cikan += adet;
                }
                const yonSinif = iptal ? 'text-muted' : (girdiMi && !ciktiMi ? 'text-success' : (ciktiMi && !girdiMi ? 'text-danger' : ''));
                const yonIsaret = girdiMi && !ciktiMi ? '+' : (ciktiMi && !girdiMi ? '-' : '');
                const aciklamaHtml = iptal
                    ? `<span class="text-muted text-decoration-line-through">${kacir(h.aciklama || '')}</span> <span class="badge bg-danger-subtle text-danger ms-1">İptal</span>`
                    : kacir(h.aciklama || '');
                return `<tr class="${iptal ? 'opacity-75' : ''}">
                    <td class="text-nowrap">${kacir(tarihGoster(h.tarih))}</td>
                    <td class="fw-semibold">${kacir(h.hareket_adi || '')}</td>
                    <td>${kacir(h.nereden || '—')} <i class="bx bx-right-arrow-alt text-muted mx-1"></i> ${kacir(h.nereye || '—')}</td>
                    <td>${kacir(h.aparat_adi || '')}</td>
                    <td class="text-center fw-bold ${yonSinif}">${yonIsaret}${adet}</td>
                    <td>${kacir(h.yapan || h.kaydeden || '—')}</td>
                    <td class="small">${aciklamaHtml}</td>
                </tr>`;
            }).join('');

            const netSonuc = giren - cikan;
            const sonucRenk = netSonuc > 0 ? 'primary' : (netSonuc < 0 ? 'warning' : 'secondary');
            const kartlarHtml = `
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 px-3 rounded-3 border bg-success-subtle border-success-subtle d-flex align-items-center justify-content-between">
                            <div>
                                <div class="font-size-11 text-success fw-semibold text-uppercase">Giren</div>
                                <div class="fs-5 fw-bold text-success">+${giren}</div>
                            </div>
                            <i class="bx bx-down-arrow-circle fs-3 text-success opacity-75"></i>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 px-3 rounded-3 border bg-danger-subtle border-danger-subtle d-flex align-items-center justify-content-between">
                            <div>
                                <div class="font-size-11 text-danger fw-semibold text-uppercase">Çıkan</div>
                                <div class="fs-5 fw-bold text-danger">-${cikan}</div>
                            </div>
                            <i class="bx bx-up-arrow-circle fs-3 text-danger opacity-75"></i>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 px-3 rounded-3 border bg-${sonucRenk}-subtle border-${sonucRenk}-subtle d-flex align-items-center justify-content-between">
                            <div>
                                <div class="font-size-11 text-${sonucRenk} fw-semibold text-uppercase">Sonuç (Net)</div>
                                <div class="fs-5 fw-bold text-${sonucRenk}">${netSonuc > 0 ? '+' + netSonuc : netSonuc}</div>
                            </div>
                            <i class="bx bx-calculator fs-3 text-${sonucRenk} opacity-75"></i>
                        </div>
                    </div>
                </div>
            `;

            $('#sahipGecmisIcerik').html(`
                ${kartlarHtml}
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>Hareket</th>
                                <th>Nereden → Nereye</th>
                                <th>Aparat</th>
                                <th class="text-center">Adet</th>
                                <th>Yapan / Kaydeden</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody>${satirlarHtml}</tbody>
                    </table>
                </div>
            `);
        }).fail(function () {
            $('#sahipGecmisIcerik').html('<div class="alert alert-danger m-3 mb-0">Hareket geçmişi yüklenemedi.</div>');
        });
    }

    $(document).on('click', '#tabloStokMatris tbody td', function (e) {
        if ($(e.target).closest('a, button, input, select, label').length) return;
        const dt = dtOrnekleri['#tabloStokMatris'];
        if (!dt) return;
        const cellIdx = dt.cell(this).index();
        if (!cellIdx) return;
        const data = dt.row(cellIdx.row).data();
        if (!data) return;
        const dataSrc = String(dt.column(cellIdx.column).dataSrc() || '');
        const aparatTipId = dataSrc.indexOf('adet_') === 0 ? (parseInt(dataSrc.slice(5), 10) || 0) : 0;
        sahipHareketGecmisiAc(data, aparatTipId);
    });

    $(document).on('click', '#tabloStokMatris tfoot tr.havuz-tiklanabilir td', function () {
        const sahipTipi = String($(this).closest('tr').data('sahip-tipi') || '');
        if (!sahipTipi || !sonMatris || !Array.isArray(sonMatris.satirlar)) return;
        const havuz = sonMatris.satirlar.find(function (x) { return x.sahip_tipi === sahipTipi; });
        if (!havuz) return;
        const baslik = sahipTipi === 'saha' ? 'Abonede Takılı' : (havuz.baslik || sahipTipi);
        const satir = {
            sahip_tipi: sahipTipi,
            sahip_id: 0,
            personel: baslik,
            ekip: 'Havuz Hesabı',
            toplam: havuz.toplam
        };
        (sonMatris.tipler || []).forEach(function (tip) {
            satir['adet_' + tip.id] = (havuz.adetler && havuz.adetler[tip.id]) || 0;
        });
        const tipId = parseInt($(this).data('tip-id'), 10) || 0;
        sahipHareketGecmisiAc(satir, tipId);
    });

    function kritikGoster(kritik) {
        $('#ozetKritik').text(kritik.toplam || 0);
        $('#ozetKritikAlt').text('Depo: ' + kritik.depo.length + ' · Ekip: ' + kritik.ekipler.length);

        const liste = $('#aparatKritikListe').empty();
        if (!kritik.toplam) {
            $('#aparatKritikUyari').addClass('d-none');
            return;
        }

        function rozetler(eksikler) {
            return eksikler.map(function (e) {
                const renk = e.seviye === 'bitti' ? 'danger' : 'warning';
                return `<span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold me-1 mb-1">${kacir(e.aparat_adi)}: ${e.adet} / ${e.esik}</span>`;
            }).join('');
        }

        if (kritik.depo.length) {
            liste.append(`<div class="col-12 col-md-6 col-xl-4"><div class="aparat-kritik-kutu">
                <div class="fw-semibold text-dark mb-1"><i class="bx bx-buildings me-1"></i>Depo</div>${rozetler(kritik.depo)}</div></div>`);
        }
        kritik.ekipler.forEach(function (e) {
            liste.append(`<div class="col-12 col-md-6 col-xl-4"><div class="aparat-kritik-kutu">
                <div class="fw-semibold text-dark mb-1"><i class="bx bx-user me-1"></i>${kacir(e.uyeler || 'Personel atanmamış')} <small class="text-muted">(${kacir(e.ekip_kodu)})</small></div>${rozetler(e.eksikler)}</div></div>`);
        });
        $('#aparatKritikUyari').removeClass('d-none');
    }

    (function () {
        const buton = $('#btnToggleSummaryCards');
        function durumGuncelle() {
            const gizli = $('html').hasClass('aparat-summary-hidden');
            const etiket = gizli ? 'Özet Kartları Göster' : 'Özet Kartları Gizle';
            buton.attr({ 'aria-expanded': !gizli, title: etiket, 'aria-label': etiket });
            buton.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
        durumGuncelle();
        buton.on('click', function () {
            const gizle = !$('html').hasClass('aparat-summary-hidden');
            $('html').toggleClass('aparat-summary-hidden', gizle);
            try { localStorage.setItem('aparat_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
            durumGuncelle();
        });
    })();

    $('#btnBakiyeOnar').on('click', function () {
        Swal.fire({
            title: 'Bakiye onarılsın mı?',
            text: 'Bakiye tablosu ana defterden yeniden hesaplanacak. Hareket kayıtları değişmez.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Onar',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'bakiye-yeniden-kur' }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                stokYukle();
            }).fail(() => hata('Bakiye onarılamadı.'));
        });
    });

    // ---------- Havuz hareketi ----------

    function havuzBakiyeGoster() {
        const tur = HAVUZ_TURLERI[$('#havuz_tur').val()] || {};
        const tipId = parseInt($('#havuz_aparat').val(), 10) || 0;
        const ekipId = parseInt($('#havuz_ekip').val(), 10) || 0;
        const kutu = $('#havuzBakiyeBilgi');

        if (!sonMatris || !tur.kaynak || !tipId || (tur.kaynak === 'ekip' && !ekipId)) {
            kutu.addClass('d-none');
            return;
        }

        const satir = sonMatris.satirlar.find(function (s) {
            return tur.kaynak === 'depo' ? s.sahip_tipi === 'depo' : (s.sahip_tipi === 'ekip' && s.sahip_id === ekipId);
        });
        const elde = satir ? (satir.adetler[tipId] || 0) : 0;
        const yer = tur.kaynak === 'depo' ? 'Depoda' : 'Ekipte';
        $('#havuzBakiyeMetin').text(yer + ' şu an: ' + elde + ' adet');
        kutu.removeClass('d-none');
    }

    $(document).on('click', '.btn-depo-hareket', function () {
        const kod = $(this).data('tur');
        const tur = HAVUZ_TURLERI[kod];
        if (!tur) return;

        if (kod === 'depo_giris') {
            depoGirisAc();
            return;
        }
        if (['depo_cikis', 'depo_iade', 'kayip', 'acilis'].indexOf(kod) !== -1) {
            topluAc(kod);
            return;
        }

        $('#formHavuz')[0].reset();
        $('#havuz_tur').val(kod);
        $('#havuzModalBaslik').text(tur.baslik);
        $('#havuzModalAciklama').text(tur.aciklama);
        $('#havuz_ekip').val('').trigger('change');
        $('#havuz_aparat').val('').trigger('change');
        $('#havuz_adet').val(1);
        $('#havuzEkipAlan').toggle(tur.ekip);
        $('#havuz_bozuk').prop('checked', false);
        $('#havuzBozukAlan').toggleClass('d-none', kod !== 'depo_iade');
        havuzBakiyeGoster();
        new bootstrap.Modal('#modalHavuz').show();
    });

    $('#havuz_ekip, #havuz_aparat').on('change', havuzBakiyeGoster);

    $('#formHavuz').on('submit', function (e) {
        e.preventDefault();

        istek({
            action: 'havuz-hareketi',
            tur: $('#havuz_tur').val() === 'depo_iade' && $('#havuz_bozuk').is(':checked') ? 'depo_iade_hurda' : $('#havuz_tur').val(),
            ekip_id: $('#havuz_ekip').val() || 0,
            aparat_tip_id: $('#havuz_aparat').val() || 0,
            adet: $('#havuz_adet').val(),
            aciklama: $('#havuz_aciklama').val()
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalHavuz')).hide();
            geriAlBildirimi(res.message, res.grup_idleri);
            stokYukle();
        }).fail(() => hata('Hareket kaydedilemedi.'));
    });

    // ---------- Toplu havuz hareketi (ekip x aparat) ----------

    let topluDeger = {};

    function topluSahipMevcut(tur, sahipId, tipId) {
        if (!sonMatris) return 0;
        const tip = tur === 'p' ? 'personel' : 'ekip';
        const satir = sonMatris.satirlar.find((s) => s.sahip_tipi === tip && parseInt(s.sahip_id, 10) === sahipId);
        return satir ? (satir.adetler[tipId] || 0) : 0;
    }

    function topluDepoMevcut(tipId) {
        if (!sonMatris) return 0;
        const satir = sonMatris.satirlar.find((s) => s.sahip_tipi === 'depo');
        return satir ? (satir.adetler[tipId] || 0) : 0;
    }

    function topluAc(kod) {
        const tur = HAVUZ_TURLERI[kod];
        topluDeger = {};
        $('#toplu_tur').val(kod);
        $('#topluBaslik').text(tur.baslik);
        $('#topluAciklama').text(kod === 'depo_cikis'
            ? 'Her ekibin satırına verilecek adetleri girin. Depodan düşer, ekiplerin zimmetine geçer. Ekip ataması olmayan Kaçak Kontrol personeli tablonun altındaki ayrı bölümde yer alır.'
            : (kod === 'depo_iade'
                ? 'Her ekibin satırına iade alınacak adetleri girin. Zimmetinden düşer, depoya girer.'
                : (kod === 'kayip'
                    ? 'Her ekibin satırına kayıp adetlerini girin. Zimmetinden düşer, kayıp havuzuna yazılır. Gerekçe zorunludur.'
                    : 'Her ekibin veya personelin elinde zaten bulunan adetleri ilk kurulum için girin.')));
        $('#topluArama').val('');
        $('#toplu_aciklama').val('');
        $('#toplu_bozuk').prop('checked', false);
        $('#topluBozukAlan').toggleClass('d-none', kod !== 'depo_iade');
        $('#topluCezaAlan').toggleClass('d-none', kod !== 'kayip');
        $('#toplu_ceza_tutari').val(kod === 'kayip' ? varsayilanKayipCezaGetir() : '');
        $('#topluFiltre button').removeClass('active').first().addClass('active');
        topluTabloCiz(kod);
        topluOzetGuncelle();
        new bootstrap.Modal('#modalHavuzToplu').show();
    }

    function topluSatirHtml(tur, sahip, tipler) {
        const hucreler = tipler.map(function (t) {
            return `<td class="text-center">
                <div class="toplu-sayac">
                    <button type="button" class="toplu-az" tabindex="-1" aria-label="Azalt">−</button>
                    <input type="number" inputmode="numeric" min="0" class="form-control toplu-giris" placeholder="–"
                           data-tur="${tur}" data-sahip="${sahip.id}" data-tip="${t.id}">
                    <button type="button" class="toplu-art" tabindex="-1" aria-label="Artır">+</button>
                </div>
                <div class="toplu-mevcut">${tur === 'p' ? 'personelde' : 'ekipte'} ${topluSahipMevcut(tur, sahip.id, t.id)}</div>
            </td>`;
        }).join('');
        const ara = (sahip.adi + ' ' + (sahip.uyeler || '')).toLocaleLowerCase('tr');
        const alt = tur === 'p'
            ? `<i class="bx bx-user me-1"></i>${kacir(sahip.gorev || 'Kaçak Kontrol')} · ekipsiz`
            : (sahip.uyeler ? '<i class="bx bx-user me-1"></i>' + kacir(sahip.uyeler) : 'Personel atanmamış');
        return `<tr class="toplu-satir" data-tur="${tur}" data-sahip="${sahip.id}" data-ara="${kacir(ara)}">
            <td><div class="toplu-ekip-ad">${kacir(sahip.adi)}</div><div class="toplu-ekip-uye">${alt}</div></td>
            ${hucreler}
        </tr>`;
    }

    function topluTabloCiz(kod) {
        const tipler = (window.aparatTipleri || []).filter((t) => t.aktif == 1);
        const ekipler = window.aparatEkipler || [];
        const personeller = (window.aparatPersoneller || []).map((p) => ({ id: p.id, adi: p.adi, gorev: p.gorev, uyeler: '' }));

        const baslik = tipler.map(function (t) {
            return `<th class="text-center">${kacir(t.ad)}${kod === 'depo_cikis' ? `<small>Depoda ${topluDepoMevcut(t.id)}</small>` : ''}</th>`;
        }).join('');

        let govde = ekipler.map((e) => topluSatirHtml('e', e, tipler)).join('');
        if (personeller.length) {
            govde += `<tr class="toplu-grup"><td colspan="${tipler.length + 1}"><i class="bx bx-user-pin me-1"></i>Ekipsiz Kaçak Kontrol Personeli</td></tr>`
                + personeller.map((p) => topluSatirHtml('p', p, tipler)).join('');
        }

        const altlik = tipler.map((t) => `<td data-tip="${t.id}" class="toplu-toplam">0</td>`).join('');

        $('#topluTabloKap').html(`<table class="table table-sm align-middle toplu-tablo">
            <thead><tr><th>Ekip / Personel</th>${baslik}</tr></thead>
            <tbody>${govde || `<tr><td colspan="${tipler.length + 1}" class="text-muted p-3">Ekip bulunamadı.</td></tr>`}<tr class="toplu-dolgu" aria-hidden="true"><td colspan="${tipler.length + 1}"></td></tr></tbody>
            <tfoot><tr><td class="text-start">${kod === 'depo_cikis' ? 'Dağıtılan' : 'Toplam'}</td>${altlik}</tr></tfoot>
        </table>`);
    }

    function topluOzetGuncelle() {
        const kod = $('#toplu_tur').val();
        const toplamlar = {};
        let kayit = 0;
        let adet = 0;
        const dolular = {};

        Object.keys(topluDeger).forEach(function (anahtar) {
            const [tur, sahip, tip] = anahtar.split('_');
            const n = topluDeger[anahtar];
            toplamlar[tip] = (toplamlar[tip] || 0) + n;
            dolular[tur + '_' + sahip] = true;
            kayit++;
            adet += n;
        });

        $('#topluTabloKap tfoot td.toplu-toplam').each(function () {
            const tip = String($(this).data('tip'));
            const t = toplamlar[tip] || 0;
            let metin = String(t);
            let eksi = false;
            if (kod === 'depo_cikis') {
                const kalan = topluDepoMevcut(parseInt(tip, 10)) - t;
                metin = `${t} <small class="fw-normal text-muted">(kalan ${kalan})</small>`;
                eksi = kalan < 0;
            }
            $(this).html(metin).toggleClass('eksi', eksi);
        });

        const kisi = Object.keys(dolular).length;
        $('#topluSayGirilen').text(kisi);
        const kayipModu = kod === 'kayip';
        const birimCeza = kayipModu ? (parseFloat(String($('#toplu_ceza_tutari').val() || '0').replace(',', '.')) || 0) : 0;
        const toplamCeza = birimCeza > 0 ? (adet * birimCeza) : 0;
        $('#topluOzetMetin').text(kayit
            ? `${kisi} ekip/personel · ${kayit} kayıt · toplam ${adet} adet`
                + (toplamCeza > 0 ? ` · Toplam ceza: ${toplamCeza.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ₺` : '')
            : 'Henüz adet girilmedi.');
        $('#btnTopluKaydet').prop('disabled', kayit === 0);
        topluFiltreUygula();
    }

    function varsayilanKayipCezaGetir() {
        let kayitli = '';
        try { kayitli = localStorage.getItem('aparat_kayip_ceza_tutari') || ''; } catch (e) {}
        if (kayitli !== '' && parseFloat(kayitli) > 0) return kayitli;
        if (window.aparatSonKayipCeza && parseFloat(window.aparatSonKayipCeza) > 0) {
            return String(window.aparatSonKayipCeza);
        }
        return '';
    }

    $(document).on('input', '#toplu_ceza_tutari', topluOzetGuncelle);

    function topluFiltreUygula() {
        const aranan = ($('#topluArama').val() || '').toLocaleLowerCase('tr').trim();
        const sadeceGirilen = $('#topluFiltre button.active').data('filtre') === 'girilen';

        $('#topluTabloKap tbody tr.toplu-satir').each(function () {
            const onek = $(this).data('tur') + '_' + $(this).data('sahip') + '_';
            const doluMu = Object.keys(topluDeger).some((k) => k.indexOf(onek) === 0);
            let goster = !sadeceGirilen || doluMu;
            if (goster && aranan) goster = ($(this).attr('data-ara') || '').indexOf(aranan) !== -1;
            $(this).toggleClass('d-none', !goster);
        });

        $('#topluTabloKap tr.toplu-grup').toggleClass('d-none', !$('#topluTabloKap tr.toplu-satir[data-tur="p"]:not(.d-none)').length);
    }

    function topluDegerYaz($giris, deger) {
        const anahtar = $giris.data('tur') + '_' + $giris.data('sahip') + '_' + $giris.data('tip');
        const n = parseInt(deger, 10);
        if (isNaN(n) || n <= 0) {
            delete topluDeger[anahtar];
        } else {
            topluDeger[anahtar] = n;
        }
        $giris.toggleClass('dolu', !!topluDeger[anahtar]);
        topluOzetGuncelle();
    }

    $(document).on('input', '.toplu-giris', function () { topluDegerYaz($(this), $(this).val()); });
    $(document).on('focus', '.toplu-giris', function () { this.select(); });

    $(document).on('click', '.toplu-az, .toplu-art', function () {
        const $giris = $(this).siblings('.toplu-giris');
        const mevcut = parseInt($giris.val(), 10);
        const artir = $(this).hasClass('toplu-art');
        const yeni = artir ? (isNaN(mevcut) ? 1 : mevcut + 1) : Math.max(0, (isNaN(mevcut) ? 0 : mevcut) - 1);
        $giris.val(yeni > 0 ? yeni : '');
        topluDegerYaz($giris, yeni);
    });

    $(document).on('keydown', '.toplu-giris', function (e) {
        if (['Enter', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) return;
        e.preventDefault();
        const tip = $(this).data('tip');
        const sutun = $(`.toplu-giris[data-tip="${tip}"]:visible`);
        const hedef = sutun.eq(sutun.index(this) + (e.key === 'ArrowUp' ? -1 : 1));
        if (hedef.length) hedef.trigger('focus');
    });

    $(document).on('input', '#topluArama', topluFiltreUygula);

    $(document).on('click', '#topluFiltre button', function () {
        $('#topluFiltre button').removeClass('active');
        $(this).addClass('active');
        topluFiltreUygula();
    });

    function topluGonder() {
        const kod = $('#toplu_tur').val() === 'depo_iade' && $('#toplu_bozuk').is(':checked') ? 'depo_iade_hurda' : $('#toplu_tur').val();
        const satirlar = Object.keys(topluDeger).map(function (anahtar) {
            const [tur, sahip, tip] = anahtar.split('_');
            const satir = { aparat_tip_id: parseInt(tip, 10), adet: topluDeger[anahtar] };
            if (tur === 'p') satir.personel_id = parseInt(sahip, 10);
            else satir.ekip_id = parseInt(sahip, 10);
            return satir;
        });
        if (!satirlar.length) return;

        const kayipModu = kod === 'kayip';
        const aciklamaMetni = String($('#toplu_aciklama').val() || '').trim();
        if (kayipModu && aciklamaMetni === '') {
            return hata('Kayıp kaydı için açıklama / gerekçe zorunludur.');
        }
        const cezaTutari = kayipModu ? (parseFloat(String($('#toplu_ceza_tutari').val() || '0').replace(',', '.')) || 0) : 0;

        const $btn = $('#btnTopluKaydet').prop('disabled', true).text('Kaydediliyor...');
        istek({
            action: 'havuz-toplu',
            tur: kod,
            aciklama: aciklamaMetni,
            ceza_tutari: cezaTutari,
            satirlar: JSON.stringify(satirlar)
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            if (kayipModu && cezaTutari > 0) {
                window.aparatSonKayipCeza = cezaTutari;
                try { localStorage.setItem('aparat_kayip_ceza_tutari', String(cezaTutari)); } catch (e) {}
            }
            bootstrap.Modal.getInstance(document.getElementById('modalHavuzToplu')).hide();
            geriAlBildirimi(res.message, res.grup_idleri);
            stokYukle();
        }).fail(() => hata('Hareketler kaydedilemedi.'))
            .always(() => $btn.prop('disabled', Object.keys(topluDeger).length === 0).text('Kaydet'));
    }

    $('#btnTopluKaydet').on('click', function () {
        if ($('#toplu_tur').val() !== 'depo_cikis') return topluGonder();

        const asilan = (window.aparatTipleri || []).filter(function (t) {
            const toplam = Object.keys(topluDeger).filter((k) => k.split('_')[2] === String(t.id))
                .reduce((n, k) => n + topluDeger[k], 0);
            return toplam > topluDepoMevcut(t.id);
        });

        if (!asilan.length) return topluGonder();

        Swal.fire({
            icon: 'warning',
            title: 'Depo stoğu yetmiyor',
            html: `<b>${asilan.map((t) => kacir(t.ad)).join(', ')}</b> için girilen toplam adet depodaki miktarı aşıyor. Devam ederseniz depo bakiyesi eksiye düşer.`,
            showCancelButton: true,
            confirmButtonText: 'Yine de kaydet',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (sonuc.isConfirmed) topluGonder();
        });
    });

    // ---------- Toplu depoya giriş ----------

    let depoGirisDeger = {};

    function depoGirisAc() {
        depoGirisDeger = {};
        $('#dg_aciklama').val('');
        const tipler = (window.aparatTipleri || []).filter((t) => t.aktif == 1);

        const govde = tipler.map(function (t) {
            const simdi = topluDepoMevcut(t.id);
            return `<tr class="dg-satir">
                <td><div class="toplu-ekip-ad">${kacir(t.ad)}</div></td>
                <td class="text-center text-muted">${simdi}</td>
                <td class="text-center">
                    <div class="dg-sayac">
                        <button type="button" class="dg-az" tabindex="-1" aria-label="Azalt">−</button>
                        <input type="number" inputmode="numeric" min="0" class="form-control dg-giris" placeholder="–" data-tip="${t.id}" data-simdi="${simdi}">
                        <button type="button" class="dg-art" tabindex="-1" aria-label="Artır">+</button>
                    </div>
                </td>
                <td class="text-center dg-sonra" data-tip="${t.id}">${simdi}</td>
            </tr>`;
        }).join('');

        $('#dgTabloKap').html(`<table class="table table-sm align-middle toplu-tablo dg-tablo">
            <thead><tr><th>Aparat</th><th class="text-center">Depoda şu an</th><th class="text-center">Gelen adet</th><th class="text-center">Giriş sonrası</th></tr></thead>
            <tbody>${govde}</tbody>
        </table>`);
        depoGirisOzet();
        new bootstrap.Modal('#modalDepoGiris').show();
    }

    function depoGirisOzet() {
        let kayit = 0;
        let adet = 0;
        $('#dgTabloKap .dg-giris').each(function () {
            const tip = $(this).data('tip');
            const n = depoGirisDeger[tip] || 0;
            const simdi = parseInt($(this).data('simdi'), 10) || 0;
            $('#dgTabloKap .dg-sonra[data-tip="' + tip + '"]').text(simdi + n).toggleClass('artti', n > 0);
            if (n > 0) { kayit++; adet += n; }
        });
        $('#dgOzetMetin').text(kayit ? `${kayit} aparat çeşidi · toplam ${adet} adet depoya girecek` : 'Henüz adet girilmedi.');
        $('#btnDgKaydet').prop('disabled', kayit === 0);
    }

    function depoGirisYaz($giris, deger) {
        const n = parseInt(deger, 10);
        if (isNaN(n) || n <= 0) delete depoGirisDeger[$giris.data('tip')];
        else depoGirisDeger[$giris.data('tip')] = n;
        $giris.toggleClass('dolu', !!depoGirisDeger[$giris.data('tip')]);
        depoGirisOzet();
    }

    $(document).on('input', '.dg-giris', function () { depoGirisYaz($(this), $(this).val()); });
    $(document).on('focus', '.dg-giris', function () { this.select(); });

    $(document).on('click', '.dg-az, .dg-art', function () {
        const $giris = $(this).siblings('.dg-giris');
        const mevcut = parseInt($giris.val(), 10);
        const yeni = $(this).hasClass('dg-art') ? (isNaN(mevcut) ? 1 : mevcut + 1) : Math.max(0, (isNaN(mevcut) ? 0 : mevcut) - 1);
        $giris.val(yeni > 0 ? yeni : '');
        depoGirisYaz($giris, yeni);
    });

    $(document).on('keydown', '.dg-giris', function (e) {
        if (['Enter', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) return;
        e.preventDefault();
        const hepsi = $('.dg-giris');
        const hedef = hepsi.eq(hepsi.index(this) + (e.key === 'ArrowUp' ? -1 : 1));
        if (hedef.length) hedef.trigger('focus');
    });

    $('#btnDgKaydet').on('click', function () {
        const satirlar = Object.keys(depoGirisDeger).map((tip) => ({ aparat_tip_id: parseInt(tip, 10), adet: depoGirisDeger[tip] }));
        if (!satirlar.length) return;

        const $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
        istek({
            action: 'havuz-toplu',
            tur: 'depo_giris',
            aciklama: $('#dg_aciklama').val(),
            satirlar: JSON.stringify(satirlar)
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalDepoGiris')).hide();
            geriAlBildirimi(res.message, res.grup_idleri);
            stokYukle();
        }).fail(() => hata('Depoya giriş kaydedilemedi.'))
            .always(() => $btn.prop('disabled', Object.keys(depoGirisDeger).length === 0).text('Kaydet'));
    });

    // ---------- Manuel saha kaydı ----------

    function manuelDurumGorunurluk() {
        const acma = $('#mi_islem_tipi').val() === 'acma';
        const aparatsiz = $('#mi_aparatsiz').is(':checked');
        $('#miDurumAlan').toggle(acma && !aparatsiz);
        $('#mi_aparat').prop('disabled', aparatsiz).trigger('change');
    }

    $('#btnManuelIslem').on('click', function () {
        $('#formIslem')[0].reset();
        $('#mi_islem_tipi').val('kesme').trigger('change');
        $('#mi_ekip, #mi_aparat').val('').trigger('change');
        $('#mi_adet').val(1);
        $('#mi_aparatsiz').prop('checked', false);
        manuelDurumGorunurluk();
        new bootstrap.Modal('#modalIslem').show();
    });

    $('#mi_islem_tipi, #mi_aparatsiz').on('change', manuelDurumGorunurluk);

    $('#formIslem').on('submit', function (e) {
        e.preventDefault();

        istek({
            action: 'islem-kaydet',
            islem_tipi: $('#mi_islem_tipi').val(),
            ekip_id: $('#mi_ekip').val() || 0,
            aparat_tip_id: $('#mi_aparat').val() || 0,
            adet: $('#mi_adet').val() || 1,
            aparatsiz: $('#mi_aparatsiz').is(':checked') ? 1 : 0,
            aparat_durumu: $('#mi_aparat_durumu').val(),
            abone_no: $('#mi_abone_no').val(),
            sayac_no: $('#mi_sayac_no').val(),
            ilce: $('#mi_ilce').val(),
            aciklama: $('#mi_aciklama').val(),
            tarih: tarihGonder('#mi_tarih')
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalIslem')).hide();
            basari(res.message);
            stokYukle();
            islemleriYukle();
        }).fail(() => hata('Kayıt eklenemedi.'));
    });

    // ---------- Saha işlemleri ----------

    let islemDurumu = 'aktif';

    function islemFiltresi() {
        return {
            action: 'islem-listesi',
            start_date: tarihGonder('#islem_bas'),
            end_date: tarihGonder('#islem_bit'),
            ekip_id: $('#islem_ekip').val() || 0,
            islem_tipi: $('#islem_tip_filtre').val() || '',
            aparat_tip_id: $('#islem_aparat').val() || 0,
            durum: islemDurumu === 'negatif' ? 'aktif' : islemDurumu,
            sadece_negatif: islemDurumu === 'negatif' ? 1 : 0
        };
    }

    function islemleriYukle() {
        istek(islemFiltresi()).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (k) {
                return {
                    id: k.id,
                    tarih: tarihGoster(k.tarih),
                    islem: ISLEM_ADI[k.islem_tipi] || k.islem_tipi,
                    islem_tipi: k.islem_tipi,
                    ekip: k.ekip_kodu || '',
                    personel: k.personel_adi || '',
                    abone_no: k.abone_no || '',
                    sayac_no: k.sayac_no || '',
                    aparat: k.aparatsiz == 1 ? 'Aparatsız' : (k.aparat_adi || ''),
                    adet: parseInt(k.adet, 10),
                    durum: DURUM_ADI[k.durum] || k.durum,
                    durum_kodu: k.durum,
                    foto_sayisi: parseInt(k.foto_sayisi, 10),
                    negatif_stok: k.negatif_stok,
                    mukerrer_uyari: k.mukerrer_uyari
                };
            });

            dtKur('#tabloIslemler', {
                data: satirlar,
                order: [[0, 'desc']],
                columns: [
                    { data: 'tarih' },
                    {
                        data: 'islem',
                        render: bicim(function (deger, satir) {
                            const renk = satir.islem_tipi === 'kesme' ? 'danger' : 'success';
                            return `<span class="badge bg-${renk}-subtle text-${renk}">${kacir(deger)}</span>`;
                        })
                    },
                    { data: 'ekip' },
                    { data: 'personel' },
                    {
                        data: 'abone_no',
                        render: bicim(function (deger, satir) {
                            const uyari = (satir.negatif_stok == 1 ? '<i class="bx bx-error text-danger ms-1" title="Negatif stok"></i>' : '')
                                + (satir.mukerrer_uyari == 1 ? '<i class="bx bx-copy text-warning ms-1" title="Mükerrer kayıt uyarısı"></i>' : '');
                            return kacir(deger) + uyari;
                        })
                    },
                    { data: 'sayac_no' },
                    { data: 'aparat' },
                    { data: 'adet', className: 'text-center' },
                    { data: 'durum', render: bicim(durumRozeti) },
                    {
                        data: 'foto_sayisi',
                        className: 'text-center',
                        orderable: false,
                        render: bicim(function (adet) {
                            return adet > 0
                                ? `<i class="bx bx-image text-primary"></i> ${adet}`
                                : '<span class="text-muted">-</span>';
                        })
                    },
                    {
                        data: 'id',
                        className: 'text-end',
                        orderable: false,
                        searchable: false,
                        render: function (id, tip, satir) {
                            return '<div class="d-flex justify-content-end flex-nowrap gap-1">'
                                + `<button class="btn btn-sm btn-outline-primary btn-islem-detay" data-id="${id}"><i class="bx bx-show"></i></button>`
                                + (yetki.sil && satir.durum_kodu === 'iptal' ? `<button class="btn btn-sm btn-outline-danger btn-islem-sil" data-id="${id}" title="Sil"><i class="bx bx-trash"></i></button>` : '')
                                + '</div>';
                        }
                    }
                ]
            });
        }).fail(() => hata('İşlemler yüklenemedi.'));
    }

    $('#btnIslemListele').on('click', islemleriYukle);

    $('#islemDurumFiltre input[name="islem-durum"]').on('change', function () {
        islemDurumu = $(this).val();
        islemleriYukle();
    });

    $('#btnIslemExcel').on('click', function (e) {
        e.preventDefault();
        const p = islemFiltresi();
        delete p.action;
        p.tip = 'islem';
        window.location = 'views/aparat-takip/export-excel.php?' + $.param(p);
    });

    $(document).on('click', '.btn-islem-detay', function () {
        aktifIslemId = parseInt($(this).data('id'), 10);

        istek({ action: 'islem-detay', id: aktifIslemId }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const k = res.data;
            let fotoHtml = '';

            (res.fotograflar || []).forEach(function (f) {
                fotoHtml += `<div class="col-6 aparat-foto-kutu">
                    <div class="small text-muted mb-1">${f.tur === 'sayac' ? 'Sayaç' : (f.tur === 'aparat' ? 'Aparat' : 'İptal')} fotoğrafı</div>
                    <a href="views/aparat-takip/foto-goruntule.php?id=${f.id}" target="_blank">
                        <img src="views/aparat-takip/foto-goruntule.php?id=${f.id}" alt="fotoğraf">
                    </a>
                </div>`;
            });

            if (!fotoHtml) {
                fotoHtml = '<div class="col-12 text-muted small">Bu kayda ait fotoğraf bulunmuyor.</div>';
            }

            let hareketHtml = '';
            (res.hareketler || []).forEach(function (h) {
                hareketHtml += `<tr>
                    <td>${tarihGoster(h.tarih)}</td>
                    <td>${kacir(h.sahip_tipi)}</td>
                    <td>${kacir(h.aparat_adi || '-')}</td>
                    <td class="text-center ${h.adet < 0 ? 'text-danger' : 'text-success'}">${h.adet > 0 ? '+' : ''}${h.adet}</td>
                    <td>${h.iptal_mi == 1 ? '<span class="badge bg-secondary-subtle text-secondary">iptal</span>' : ''}</td>
                </tr>`;
            });

            $('#islemDetayIcerik').html(`
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr><th style="width:40%">İşlem</th><td>${ISLEM_ADI[k.islem_tipi] || ''} ${durumRozeti(DURUM_ADI[k.durum] || k.durum)}</td></tr>
                            <tr><th>Tarih</th><td>${tarihGoster(k.tarih)}</td></tr>
                            <tr><th>Ekip</th><td>${kacir(k.ekip_kodu || k.ekip_adi || '-')}</td></tr>
                            <tr><th>Personel</th><td>${kacir(k.personel_adi || '-')}</td></tr>
                            <tr><th>Abone / Sayaç</th><td>${kacir(k.abone_no || '-')} / ${kacir(k.sayac_no || '-')}</td></tr>
                            <tr><th>Adres</th><td>${kacir([k.ilce, k.mahalle, k.adres].filter(Boolean).join(' / ') || '-')}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr><th style="width:40%">Aparat</th><td>${k.aparatsiz == 1 ? 'Kullanılmadı' : kacir(k.aparat_adi || '-')}</td></tr>
                            <tr><th>Adet</th><td>${k.adet}</td></tr>
                            <tr><th>Aparat Durumu</th><td>${kacir(k.aparat_durumu || '-')}</td></tr>
                            <tr><th>Kaynak</th><td>${k.kaynak === 'pwa' ? 'Telefon' : 'Panel'}</td></tr>
                            <tr><th>Cihaz Zamanı</th><td>${tarihGoster(k.cihaz_zamani)}</td></tr>
                            <tr><th>Konum</th><td>${k.enlem && k.boylam ? `<a href="https://maps.google.com/?q=${k.enlem},${k.boylam}" target="_blank">Haritada gör</a>` : '-'}</td></tr>
                        </table>
                    </div>
                    ${k.durum === 'iptal' ? `<div class="col-12"><div class="alert alert-secondary mb-0 py-2 small">İptal gerekçesi: ${kacir(k.iptal_aciklama || '-')}</div></div>` : ''}
                    ${k.negatif_stok == 1 ? '<div class="col-12"><div class="alert alert-danger mb-0 py-2 small">Bu kayıt girildiğinde ekip stoğu eksiye düştü.</div></div>' : ''}
                    ${fotoHtml}
                    <div class="col-12">
                        <h6 class="mt-2">Stok Hareketleri</h6>
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Tarih</th><th>Havuz</th><th>Aparat</th><th class="text-center">Adet</th><th></th></tr></thead>
                            <tbody>${hareketHtml || '<tr><td colspan="5" class="text-muted small">Hareket yok (aparatsız kayıt).</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>`);

            $('#btnIslemIptal').toggle(k.durum === 'aktif');
            $('#btnIslemSil').toggle(k.durum === 'iptal');
            new bootstrap.Modal('#modalIslemDetay').show();
        }).fail(() => hata('Detay yüklenemedi.'));
    });

    $('#btnIslemIptal').on('click', function () {
        Swal.fire({
            target: document.querySelector('.modal.show') || document.body,
            title: 'Kayıt iptal edilsin mi?',
            input: 'textarea',
            inputLabel: 'İptal gerekçesi (zorunlu)',
            inputValidator: (v) => (!v || !v.trim()) && 'Gerekçe girmelisiniz.',
            showCancelButton: true,
            confirmButtonText: 'İptal Et',
            cancelButtonText: 'Vazgeç',
            icon: 'warning'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;

            istek({ action: 'islem-iptal', id: aktifIslemId, aciklama: sonuc.value }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                bootstrap.Modal.getInstance(document.getElementById('modalIslemDetay')).hide();
                basari(res.message);
                islemleriYukle();
                stokYukle();
            }).fail(() => hata('Kayıt iptal edilemedi.'));
        });
    });

    function islemSil(id) {
        Swal.fire({
            target: document.querySelector('.modal.show') || document.body,
            title: 'Kayıt silinsin mi?',
            html: 'İptal edilmiş kayıt listelerden kalıcı olarak kaldırılır.',
            input: 'textarea',
            inputLabel: 'Silme gerekçesi (zorunlu)',
            inputValidator: (v) => (!v || !v.trim()) && 'Gerekçe girmelisiniz.',
            showCancelButton: true,
            confirmButtonText: 'Sil',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#dc3545',
            icon: 'warning'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;

            istek({ action: 'islem-sil', id: id, aciklama: sonuc.value }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalIslemDetay'));
                if (modal) modal.hide();
                basari(res.message);
                islemleriYukle();
                stokYukle();
            }).fail(() => hata('Kayıt silinemedi.'));
        });
    }

    $(document).on('click', '.btn-islem-sil', function (e) {
        e.stopPropagation();
        islemSil($(this).data('id'));
    });

    $('#btnIslemSil').on('click', function () {
        islemSil(aktifIslemId);
    });

    // ---------- Hareket dökümü ----------

    function hareketFiltresi() {
        return {
            action: 'hareket-listesi',
            start_date: tarihGonder('#hrk_bas'),
            end_date: tarihGonder('#hrk_bit'),
            ekip_id: $('#hrk_ekip').val() || 0,
            hareket_tipi: $('#hrk_tip').val() || '',
            sahip_tipi: $('#hrk_havuz').val() || ''
        };
    }

    const HAREKET_ADI = {
        kesme: 'Kesme', acma: 'Açma', transfer: 'Transfer',
        depo_giris: 'Depo Girişi', depo_cikis: 'Depodan Ekibe Çıkış', depo_iade: 'Depoya İade',
        hurda: 'Hurda', kayip: 'Kayıp', sayim_duzeltme: 'Sayım Düzeltmesi', acilis: 'Açılış Stoğu'
    };
    const HAVUZ_ADI = { ekip: 'Ekip', depo: 'Depo', saha: 'Sahada Takılı', hurda: 'Hurda', kayip: 'Kayıp' };

    function hareketleriYukle() {
        istek(hareketFiltresi()).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (h) {
                return {
                    tarih: tarihGoster(h.tarih),
                    hareket: h.hareket_adi,
                    hareket_tipi: h.hareket_tipi,
                    nereden: h.nereden,
                    nereye: h.nereye,
                    aparat: h.aparat_adi || '',
                    adet: parseInt(h.adet, 10),
                    yapan: h.yapan || '',
                    kaydeden: h.kaydeden || '',
                    aciklama: h.aciklama || '',
                    iptal_mi: h.iptal_mi,
                    id: h.id,
                    referans_tipi: h.referans_tipi || ''
                };
            });

            const YER_RENK = { 'Depo': 'primary', 'Abonede takılı': 'warning', 'Hurda': 'danger', 'Kayıp': 'danger', 'Satın alma': 'secondary', 'Açılış stoğu': 'secondary' };
            const yerRozeti = function (deger) {
                const renk = YER_RENK[deger];
                if (!renk) return `<span class="fw-medium">${kacir(deger)}</span>`;
                return `<span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${kacir(deger)}</span>`;
            };

            dtKur('#tabloHareketler', {
                data: satirlar,
                order: [],
                columns: [
                    { data: 'tarih', className: 'text-nowrap' },
                    { data: 'hareket', className: 'fw-semibold' },
                    { data: 'nereden', render: bicim(yerRozeti) },
                    {
                        data: 'nereye',
                        render: bicim(function (deger) {
                            return `<i class="bx bx-right-arrow-alt text-muted me-1"></i>${yerRozeti(deger)}`;
                        })
                    },
                    { data: 'aparat' },
                    { data: 'adet', className: 'text-center fw-bold' },
                    { data: 'yapan' },
                    { data: 'kaydeden', className: 'text-secondary' },
                    {
                        data: 'aciklama',
                        render: bicim(function (deger, satir) {
                            if (satir.iptal_mi != 1) return kacir(deger);
                            return `<span class="text-muted text-decoration-line-through">${kacir(deger)}</span>
                                    <span class="badge bg-secondary ms-1">İptal</span>`;
                        })
                    },
                    {
                        data: 'id',
                        orderable: false,
                        className: 'text-center',
                        render: bicim(function (id, satir) {
                            if (satir.iptal_mi == 1) {
                                return '<span class="text-muted">-</span>';
                            }
                            if (String(satir.aciklama || '').indexOf('İptal: ') === 0) {
                                return '<span class="text-muted">-</span>';
                            }
                            const geriAlinabilir = ['manuel', 'bozuk_iade', 'aparat_sayim_detay'].indexOf(satir.referans_tipi) !== -1;
                            if (!geriAlinabilir) {
                                const nerede = satir.referans_tipi === 'aparat_transfer' ? 'Transferler' : 'Saha İşlemleri';
                                return `<span class="text-muted" title="Bu kaydı ${nerede} sekmesinden geri alabilirsiniz"><i class="bx bx-lock-alt"></i></span>`;
                            }
                            return `<button class="btn btn-sm btn-soft-danger btn-hareket-iptal text-nowrap px-2 py-0" style="font-size:11.5px;line-height:1.55" data-id="${id}"
                                        title="Geri al"><i class="bx bx-undo me-1"></i>Geri Al</button>`;
                        })
                    }
                ]
            });
        }).fail(() => hata('Hareketler yüklenemedi.'));
    }

    $(document).on('click', '.btn-hareket-iptal', function () {
        const id = $(this).data('id');
        const satir = $('#tabloHareketler').DataTable().row($(this).closest('tr')).data() || {};
        const ozet = `<b>${kacir(satir.adet || '')} ${kacir(satir.aparat || '')}</b> (${kacir(satir.hareket || '')}): ${kacir(satir.nereden || '')} → ${kacir(satir.nereye || '')}`;

        Swal.fire({
            title: 'Bu işlem geri alınsın mı?',
            html: `<div class="text-start">
                <div class="border rounded p-2 mb-2 bg-light">${ozet}</div>
                <p class="mb-2">Geri alırsanız depo ve ekibin elindeki stok <b>işlemden önceki haline</b> döner. Sonra doğrusunu yeniden girebilirsiniz.</p>
                <input type="text" id="swGeriNeden" class="form-control" placeholder="Neden geri alıyorsunuz? (isteğe bağlı)">
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, geri al',
            cancelButtonText: 'Vazgeç',
            preConfirm: () => $('#swGeriNeden').val()
        }).then(function (r) {
            if (!r.isConfirmed) return;

            $.post(API, { action: 'hareket-iptal', id: id, aciklama: r.value || '' }, null, 'json')
                .done(function (res) {
                    if (res.status !== 'success') return hata(res.message);
                    basari(res.message);
                    hareketleriYukle();
                    stokYukle();
                    if (aktifSayimId) sayimDetayYukle();
                })
                .fail(() => hata('Geri alınamadı.'));
        });
    });

    $('#btnHareketListele').on('click', hareketleriYukle);

    $('#btnHareketExcel').on('click', function (e) {
        e.preventDefault();
        const p = hareketFiltresi();
        delete p.action;
        p.tip = 'hareket';
        window.location = 'views/aparat-takip/export-excel.php?' + $.param(p);
    });

    // ---------- Transferler ----------

    function transferleriYukle() {
        istek({ action: 'transfer-listesi', durum: transferDurum }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (t) {
                return {
                    id: t.id,
                    tarih: tarihGoster(t.tarih),
                    veren: t.veren_ekip_adi || '',
                    alan: t.alan_ekip_adi || '',
                    aparat: t.aparat_adi || '',
                    adet: parseInt(t.adet, 10),
                    onaylanan_adet: t.onaylanan_adet,
                    durum: DURUM_ADI[t.durum] || t.durum,
                    durum_kodu: t.durum,
                    red_nedeni: t.red_nedeni || '',
                    olusturan: t.olusturan_adi || '',
                    onaylayan: t.onaylayan_adi || ''
                };
            });

            dtKur('#tabloTransferler', {
                data: satirlar,
                order: [[0, 'desc']],
                columns: [
                    { data: 'tarih' },
                    { data: 'veren' },
                    { data: 'alan' },
                    { data: 'aparat' },
                    {
                        data: 'adet',
                        className: 'text-center',
                        render: bicim(function (adet, satir) {
                            return (satir.durum_kodu === 'onaylandi' && satir.onaylanan_adet !== null && satir.onaylanan_adet != adet)
                                ? `${satir.onaylanan_adet} <s class="text-muted">${adet}</s>`
                                : String(adet);
                        })
                    },
                    {
                        data: 'durum',
                        render: bicim(function (deger, satir) {
                            return durumRozeti(deger)
                                + (satir.red_nedeni ? `<div class="small text-muted">${kacir(satir.red_nedeni)}</div>` : '');
                        })
                    },
                    { data: 'olusturan' },
                    { data: 'onaylayan' },
                    {
                        data: 'id',
                        className: 'text-end',
                        orderable: false,
                        searchable: false,
                        render: function (id, tip, satir) {
                            return (yetki.transfer && satir.durum_kodu === 'beklemede')
                                ? `<button class="btn btn-sm btn-outline-danger btn-transfer-iptal" data-id="${id}"><i class="bx bx-x"></i> İptal</button>`
                                : '';
                        }
                    }
                ]
            });
        }).fail(() => hata('Transferler yüklenemedi.'));
    }

    $('#transferDurumFiltre input[name="transfer-durum"]').on('change', function () {
        transferDurum = $(this).val();
        transferleriYukle();
    });

    $(document).on('click', '.btn-transfer-iptal', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Transfer iptal edilsin mi?',
            text: 'Bekleyen transfer iptal edilir, stok etkilenmez.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'İptal Et',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'transfer-iptal', id: id }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                transferleriYukle();
                stokYukle();
            }).fail(() => hata('Transfer iptal edilemedi.'));
        });
    });

    // ---------- Sayım ----------

    let sayimListe = [];
    let sayimListeDurum = '';

    function sayimZamanMetni(deger) {
        if (!deger) return '';
        const d = new Date(String(deger).replace(' ', 'T'));
        if (isNaN(d.getTime())) return tarihGoster(deger);
        const gun = (t) => new Date(t.getFullYear(), t.getMonth(), t.getDate()).getTime();
        const fark = Math.round((gun(new Date()) - gun(d)) / 86400000);
        if (fark === 0) return d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        if (fark === 1) return 'Dün';
        return tarihGoster(deger);
    }

    function sayimListeCiz() {
        const liste = $('#sayimListesi').empty();
        const aranan = ($('#sayimListeArama').val() || '').toLocaleLowerCase('tr').trim();

        const gorunen = sayimListe.filter(function (s) {
            if (sayimListeDurum && s.durum !== sayimListeDurum) return false;
            if (!aranan) return true;
            return [s.baslik, tarihGoster(s.baslangic_tarihi), s.baslatan_adi || '', s.durum === 'acik' ? 'açık' : (s.durum === 'iptal' ? 'iptal' : 'tamamlandı')]
                .join(' ').toLocaleLowerCase('tr').indexOf(aranan) !== -1;
        });

        if (!gorunen.length) {
            liste.html(`<div class="text-center text-muted small py-5 px-3">${sayimListe.length ? 'Aramaya uyan sayım yok.' : 'Henüz sayım yapılmamış.'}</div>`);
            return;
        }

        gorunen.forEach(function (s) {
            const satir = parseInt(s.satir_sayisi, 10) || 0;
            const girilen = parseInt(s.girilen_sayisi, 10) || 0;
            const yuzde = s.durum === 'tamamlandi' ? 100 : (satir > 0 ? Math.round((girilen / satir) * 100) : 0);
            const renk = s.durum === 'iptal' ? '#ef4444' : '#22c55e';
            const avatarIkon = s.durum === 'iptal' ? 'bx-block' : 'bx-list-check';
            const durumSimge = s.durum === 'acik'
                ? '<i class="bx bx-time-five text-warning"></i> Açık'
                : (s.durum === 'iptal' ? '<span class="text-danger fw-medium"><i class="bx bx-block"></i> İptal</span>'
                    : '<i class="bx bx-check-double" style="color:#53bdeb"></i> Tamamlandı');
            const rozet = s.durum === 'acik'
                ? `<span class="sayim-wa-sag"><span class="sayim-wa-rozet">%${yuzde}</span>${yetki.sayim ? `<button type="button" class="sayim-wa-iptal" data-id="${s.id}" title="Sayımı iptal et" aria-label="Sayımı iptal et"><i class="bx bx-x"></i></button>` : ''}</span>`
                : (s.durum === 'iptal' ? '<span class="sayim-wa-rozet iptal">İptal</span>' : '');

            liste.append(`<a href="#" class="sayim-wa-oge sayim-secim ${s.durum}${s.id == aktifSayimId ? ' active' : ''}" data-id="${s.id}">
                <div class="sayim-wa-avatar" style="--yuzde:${yuzde};--renk:${renk}"><span><i class="bx ${avatarIkon}"></i></span></div>
                <div class="sayim-wa-govde">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="sayim-wa-baslik">${kacir(s.baslik)}</span>
                        <span class="sayim-wa-zaman">${sayimZamanMetni(s.baslangic_tarihi)}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="sayim-wa-alt">${durumSimge} · ${s.ekip_sayisi} ekip · ${girilen}/${satir}</span>
                        ${rozet}
                    </div>
                </div>
            </a>`);
        });
    }

    function sayimlariYukle() {
        istek({ action: 'sayim-listesi' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            sayimListe = res.data;
            sayimListeCiz();
        }).fail(() => hata('Sayımlar yüklenemedi.'));
    }

    $(document).on('input', '#sayimListeArama', sayimListeCiz);

    $(document).on('click', '#sayimListeFiltre button', function () {
        sayimListeDurum = String($(this).data('durum') || '');
        $('#sayimListeFiltre button').removeClass('active');
        $(this).addClass('active');
        sayimListeCiz();
    });

    $(document).on('click', '.sayim-secim', function (e) {
        e.preventDefault();
        aktifSayimId = parseInt($(this).data('id'), 10);
        $('.sayim-secim').removeClass('active');
        $(this).addClass('active');
        sayimDetayYukle();
    });

    let sayimVerisi = null;
    let sayimFiltre = 'tum';

    function sayimAciklamaAktif() {
        return sayimVerisi && sayimVerisi.sayim.durum === 'acik' && yetki.sayim;
    }

    function sayimOzet() {
        const d = sayimVerisi.detaylar;
        const girilen = d.filter((x) => x.sayilan_adet !== null).length;
        const farkli = d.filter((x) => x.sayilan_adet !== null && parseInt(x.fark, 10) !== 0);
        const eksik = farkli.reduce((t, x) => t + Math.min(0, parseInt(x.fark, 10)), 0);
        const fazla = farkli.reduce((t, x) => t + Math.max(0, parseInt(x.fark, 10)), 0);
        return { toplam: d.length, girilen: girilen, farkliSayi: farkli.length, eksik: eksik, fazla: fazla };
    }

    function sayimOzetCiz() {
        const o = sayimOzet();
        $('#sayimSayGirilmemis').text(o.toplam - o.girilen);
        $('#sayimSayFarkli').text(o.farkliSayi);
        const yuzde = o.toplam > 0 ? Math.round((o.girilen / o.toplam) * 100) : 0;
        $('#sayimOzet').html(`
            <div class="sayim-ozet-kutu">
                <div class="sayim-ozet-etiket">Girilen</div>
                <div class="sayim-ozet-deger">${o.girilen}<small>/${o.toplam}</small></div>
                <div class="progress mt-1" style="height:4px"><div class="progress-bar bg-success" style="width:${yuzde}%"></div></div>
            </div>
            <div class="sayim-ozet-kutu">
                <div class="sayim-ozet-etiket">Farklı satır</div>
                <div class="sayim-ozet-deger ${o.farkliSayi ? 'text-warning' : ''}">${o.farkliSayi}</div>
            </div>
            <div class="sayim-ozet-kutu">
                <div class="sayim-ozet-etiket">Eksik</div>
                <div class="sayim-ozet-deger ${o.eksik ? 'text-danger' : ''}">${o.eksik}</div>
            </div>
            <div class="sayim-ozet-kutu">
                <div class="sayim-ozet-etiket">Fazla</div>
                <div class="sayim-ozet-deger ${o.fazla ? 'text-success' : ''}">${o.fazla ? '+' + o.fazla : 0}</div>
            </div>`);
    }

    function sayimFarkHtml(d) {
        if (d.sayilan_adet === null) return '<span class="text-muted">-</span>';
        const fark = parseInt(d.fark, 10);
        if (fark === 0) return '<span class="badge bg-success-subtle text-success rounded-pill">✓</span>';
        return `<span class="badge ${fark < 0 ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info'} rounded-pill">${fark > 0 ? '+' : ''}${fark}</span>`;
    }

    function sayimFiltreUygula() {
        const aranan = ($('#sayimArama').val() || '').toLocaleLowerCase('tr').trim();
        const gruplar = {};

        $('#sayimGovde tr.sayim-satir').each(function () {
            const $tr = $(this);
            const girilmis = $tr.attr('data-girilmis') === '1';
            const farkli = $tr.attr('data-farkli') === '1';
            let goster = true;
            if (sayimFiltre === 'girilmemis') goster = !girilmis;
            if (sayimFiltre === 'farkli') goster = farkli;
            if (goster && aranan) goster = ($tr.attr('data-ara') || '').indexOf(aranan) !== -1;
            $tr.toggleClass('d-none', !goster);
            const ekip = $tr.attr('data-ekip');
            gruplar[ekip] = gruplar[ekip] || goster;
        });

        $('#sayimGovde tr.sayim-ekip-satir').each(function () {
            $(this).toggleClass('d-none', !gruplar[$(this).attr('data-ekip')]);
        });

        const gorunen = $('#sayimGovde tr.sayim-satir:not(.d-none)').length;
        $('#sayimBosMesaj').toggleClass('d-none', gorunen > 0);
    }

    function sayimEkipSayaciGuncelle(ekipId) {
        const d = sayimVerisi.detaylar.filter((x) => String(x.ekip_id) === String(ekipId));
        const girilen = d.filter((x) => x.sayilan_adet !== null).length;
        const farkli = d.some((x) => x.sayilan_adet !== null && parseInt(x.fark, 10) !== 0);
        $(`.sayim-ekip-sayac[data-ekip="${ekipId}"]`)
            .text(`${girilen}/${d.length}`)
            .toggleClass('tamam', girilen === d.length)
            .toggleClass('farkli', farkli);
    }

    function sayimTabloCiz() {
        const duzenlenebilir = sayimAciklamaAktif();
        const duzenlenebilirSayim = duzenlenebilir;
        let govde = '';
        let sonEkip = null;

        sayimVerisi.detaylar.forEach(function (d) {
            if (sonEkip !== d.ekip_id) {
                sonEkip = d.ekip_id;
                const uyeler = d.ekip_uyeleri
                    ? `<span class="sayim-uyeler"><i class="bx bx-user me-1"></i>${kacir(d.ekip_uyeleri)}</span>`
                    : '<span class="sayim-uyeler text-muted">Personel atanmamış</span>';
                govde += `<tr class="sayim-ekip-satir" data-ekip="${d.ekip_id}"><td colspan="6">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <b>${kacir(d.ekip_adi)}</b>${uyeler}
                        <span class="sayim-ekip-sayac ms-auto" data-ekip="${d.ekip_id}"></span>
                        ${duzenlenebilir && !sayimVerisi.detaylar.some((x) => x.ekip_id == d.ekip_id && x.islendi == 1)
                            ? `<button type="button" class="sayim-ekip-cikar" data-ekip="${d.ekip_id}" title="Bu ekibi sayımdan çıkar" aria-label="Sayımdan çıkar"><i class="bx bx-x"></i></button>`
                            : ''}
                    </div></td></tr>`;
            }

            const kilitli = !duzenlenebilir || d.islendi == 1;
            const girisHtml = kilitli
                ? (d.sayilan_adet === null ? '-' : d.sayilan_adet)
                : `<div class="sayim-sayac">
                        <button type="button" class="sayim-az" tabindex="-1" aria-label="Azalt">−</button>
                        <input type="number" inputmode="numeric" class="form-control sayim-giris"
                               data-ekip="${d.ekip_id}" data-tip="${d.aparat_tip_id}"
                               value="${d.sayilan_adet === null ? '' : d.sayilan_adet}" min="0" placeholder="–">
                        <button type="button" class="sayim-art" tabindex="-1" aria-label="Artır">+</button>
                   </div>`;
            const aciklamaHtml = kilitli
                ? kacir(d.aciklama || '')
                : `<input type="text" class="form-control sayim-aciklama"
                         data-ekip="${d.ekip_id}" data-tip="${d.aparat_tip_id}"
                         value="${kacir(d.aciklama || '')}"
                         placeholder="${d.sayilan_adet !== null && parseInt(d.fark, 10) !== 0 ? (parseInt(d.fark, 10) < 0 ? 'Kullanıldı' : 'Elinde mevcut') : 'Not / fark gerekçesi'}">`;

            const ara = [d.ekip_adi, d.ekip_uyeleri, d.aparat_adi].join(' ').toLocaleLowerCase('tr');
            govde += `<tr class="sayim-satir" data-ekip="${d.ekip_id}" data-tip="${d.aparat_tip_id}" data-ara="${kacir(ara)}"
                          data-girilmis="${d.sayilan_adet === null ? 0 : 1}"
                          data-farkli="${d.sayilan_adet !== null && parseInt(d.fark, 10) !== 0 ? 1 : 0}">
                <td class="ps-4">${kacir(d.aparat_adi)}</td>
                <td class="text-center text-muted sayim-sistem">${d.sistem_adet}</td>
                <td class="text-center">${girisHtml}</td>
                <td class="text-center sayim-fark">${sayimFarkHtml(d)}</td>
                <td>${aciklamaHtml}</td>
                <td class="text-center">${d.islendi == 1
                    ? '<i class="bx bx-check-double text-success fs-5"></i>'
                        + (duzenlenebilirSayim && parseInt(d.fark, 10) !== 0
                            ? ` <button type="button" class="btn btn-sm btn-soft-danger sayim-geri-al ms-1" data-id="${d.id}" title="Bu satırı geri al"><i class="bx bx-undo"></i></button>`
                            : '')
                    : ''}</td>
            </tr>`;
        });

        $('#sayimDetayIcerik').attr('class', '').html(`
            <div class="sayim-ozet" id="sayimOzet"></div>
            <div class="sayim-arac d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="sayim-liste-arama flex-grow-1">
                    <i class="bx bx-search"></i>
                    <input type="search" id="sayimArama" placeholder="Ekip, personel veya aparat ara" autocomplete="off">
                </div>
                <div class="sayim-chip-grup" id="sayimFiltreGrup">
                    <button type="button" class="${sayimFiltre === 'tum' ? 'active' : ''}" data-filtre="tum">Tümü</button>
                    <button type="button" class="${sayimFiltre === 'girilmemis' ? 'active' : ''}" data-filtre="girilmemis">Girilmemiş <span class="sayim-chip-say" id="sayimSayGirilmemis"></span></button>
                    <button type="button" class="${sayimFiltre === 'farkli' ? 'active' : ''}" data-filtre="farkli">Farklı <span class="sayim-chip-say" id="sayimSayFarkli"></span></button>
                </div>
            </div>
            <div class="sayim-tablo-kap">
                <table class="table table-sm align-middle mb-0 sayim-tablo">
                    <thead>
                        <tr>
                            <th>Aparat</th>
                            <th class="text-center">Sistem</th>
                            <th class="text-center">Sayılan</th>
                            <th class="text-center">Fark</th>
                            <th>Açıklama</th>
                            <th class="text-center">İşlendi</th>
                        </tr>
                    </thead>
                    <tbody id="sayimGovde">${govde || '<tr><td colspan="6" class="text-muted">Satır yok.</td></tr>'}</tbody>
                </table>
                <div class="text-center text-muted small py-4 d-none" id="sayimBosMesaj">Filtreye uyan satır yok.</div>
            </div>`);

        sayimVerisi.detaylar.map((d) => d.ekip_id)
            .filter((v, i, a) => a.indexOf(v) === i)
            .forEach(sayimEkipSayaciGuncelle);
        sayimOzetCiz();
        sayimFiltreUygula();
        sayimEkipKaydirmaBagla($('.sayim-tablo-kap')[0]);
        const baslikYuk = $('.sayim-tablo thead').outerHeight();
        if (baslikYuk) $('.sayim-tablo-kap')[0].style.setProperty('--sayim-baslik-yuk', (baslikYuk - 1) + 'px');
    }

    function sayimEkipKaydirmaBagla(kap) {
        if (!kap) return;
        let kilit = false;

        kap.addEventListener('wheel', function (e) {
            if (e.ctrlKey || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
            e.preventDefault();
            if (kilit) return;

            const baslikYuk = kap.querySelector('thead').offsetHeight;
            const konumlar = Array.prototype.slice.call(kap.querySelectorAll('tr.sayim-ekip-satir'))
                .filter((tr) => tr.offsetParent !== null)
                .map((tr) => Math.max(0, tr.offsetTop - baslikYuk));
            const enAlt = kap.scrollHeight - kap.clientHeight;
            const simdi = kap.scrollTop;
            let hedef;

            if (e.deltaY > 0) {
                hedef = konumlar.find((k) => k > simdi + 2);
                if (hedef === undefined || hedef > enAlt) hedef = enAlt;
            } else {
                hedef = konumlar.slice().reverse().find((k) => k < simdi - 2);
                if (hedef === undefined) hedef = 0;
            }

            if (Math.abs(hedef - simdi) < 2) return;
            kilit = true;
            kap.scrollTo({ top: hedef, behavior: 'smooth' });
            setTimeout(function () { kilit = false; }, 320);
        }, { passive: false });
    }

    function sayimDetayYukle() {
        if (!aktifSayimId) return;

        istek({ action: 'sayim-detay', id: aktifSayimId }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const sayim = res.data;
            const acik = sayim.durum === 'acik';
            sayimVerisi = { sayim: sayim, detaylar: res.detaylar };

            $('#sayimBaslik').text(sayim.baslik);
            $('#sayimAltBaslik').html(
                (acik ? '<span class="badge bg-warning-subtle text-warning rounded-pill me-1">Açık</span>'
                    : (sayim.durum === 'iptal' ? '<span class="badge bg-danger-subtle text-danger rounded-pill me-1">İptal</span>'
                        : '<span class="badge bg-success-subtle text-success rounded-pill me-1">Tamamlandı</span>'))
                + tarihGoster(sayim.baslangic_tarihi));

            const aksiyonlar = $('#sayimAksiyonlar').empty();
            aksiyonlar.append('<button class="btn btn-sm btn-outline-secondary" id="btnSayimExcel"><i class="bx bx-spreadsheet me-1"></i>Excel</button>');
            if (acik && yetki.sayim) {
                aksiyonlar.append('<button class="btn btn-sm btn-outline-secondary" id="btnSayimEkipEkle" title="Sayıma ekip veya personel ekle"><i class="bx bx-user-plus me-1"></i>Ekip / Personel Ekle</button>');
                aksiyonlar.append('<button class="btn btn-sm btn-outline-primary" id="btnFarklariIsle"><i class="bx bx-check-double me-1"></i>Farkları İşle</button>');
                aksiyonlar.append('<button class="btn btn-sm btn-success" id="btnSayimKapat"><i class="bx bx-lock me-1"></i>Tamamla</button>');
            }

            sayimTabloCiz();
        }).fail(() => hata('Sayım detayı yüklenemedi.'));
    }

    $(document).on('input', '#sayimArama', sayimFiltreUygula);

    $(document).on('click', '#sayimFiltreGrup button', function () {
        sayimFiltre = $(this).data('filtre');
        $('#sayimFiltreGrup button').removeClass('active');
        $(this).addClass('active');
        sayimFiltreUygula();
    });

    $(document).on('keydown', '.sayim-giris', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const girisler = $('.sayim-giris:visible');
        const sonraki = girisler.eq(girisler.index(this) + 1);
        $(this).trigger('change');
        if (sonraki.length) sonraki.trigger('focus').trigger('select');
    });

    $(document).on('focus', '.sayim-giris', function () { this.select(); });

    $(document).on('keydown', '.sayim-giris', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        e.preventDefault();
        const girisler = $('.sayim-giris:visible');
        const hedef = girisler.eq(girisler.index(this) + (e.key === 'ArrowDown' ? 1 : -1));
        $(this).trigger('change');
        if (hedef.length) hedef.trigger('focus');
    });

    $(document).on('click', '.sayim-az, .sayim-art', function () {
        const $giris = $(this).siblings('.sayim-giris');
        const mevcut = parseInt($giris.val(), 10);
        const artir = $(this).hasClass('sayim-art');
        const yeni = artir ? (isNaN(mevcut) ? 1 : mevcut + 1) : Math.max(0, (isNaN(mevcut) ? 1 : mevcut) - 1);
        $giris.val(yeni);
        clearTimeout($giris.data('kayit'));
        $giris.data('kayit', setTimeout(() => $giris.trigger('change'), 450));
    });

    $(document).on('change', '.sayim-giris, .sayim-aciklama', function () {
        const ekip = $(this).data('ekip');
        const tip = $(this).data('tip');
        const $tr = $(this).closest('tr');
        const deger = $tr.find('.sayim-giris').val();

        if (deger === '') return;

        const aciklama = $tr.find('.sayim-aciklama').val() || '';

        istek({
            action: 'sayim-gir',
            sayim_id: aktifSayimId,
            ekip_id: ekip,
            aparat_tip_id: tip,
            sayilan_adet: deger,
            aciklama: aciklama
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const d = sayimVerisi.detaylar.find((x) => String(x.ekip_id) === String(ekip) && String(x.aparat_tip_id) === String(tip));
            if (d) {
                d.sayilan_adet = parseInt(deger, 10);
                d.sistem_adet = res.sistem_adet;
                d.fark = res.fark;
                d.aciklama = res.aciklama !== undefined ? res.aciklama : aciklama;
                $tr.find('.sayim-aciklama').val(d.aciklama || '');
                $tr.find('.sayim-sistem').text(d.sistem_adet);
                $tr.find('.sayim-fark').html(sayimFarkHtml(d));
                $tr.attr('data-girilmis', 1).attr('data-farkli', d.fark != 0 ? 1 : 0);
                $tr.find('.sayim-aciklama').attr('placeholder', d.fark != 0 ? (d.fark < 0 ? 'Kullanıldı' : 'Elinde mevcut') : 'Not / fark gerekçesi');
                sayimEkipSayaciGuncelle(ekip);
                sayimOzetCiz();
            }
            $tr.addClass('sayim-kaydedildi');
            setTimeout(() => $tr.removeClass('sayim-kaydedildi'), 900);
        }).fail(() => hata('Sayım kaydedilemedi.'));
    });

    $(document).on('click', '.sayim-geri-al', function () {
        const detayId = $(this).data('id');
        const d = sayimVerisi.detaylar.find((x) => String(x.id) === String(detayId)) || {};

        Swal.fire({
            title: 'Bu satır geri alınsın mı?',
            html: `<div class="text-start">
                <div class="border rounded p-2 mb-2 bg-light"><b>${kacir(d.ekip_adi || '')}</b> · ${kacir(d.aparat_adi || '')}<br>
                    Sayımda <b>${d.sayilan_adet}</b> girilmişti, stok ${d.sistem_adet} → ${d.sayilan_adet} yapılmıştı.</div>
                <p class="mb-0">Geri alırsanız stok <b>işlemden önceki haline</b> döner ve bu satırı yeniden düzenleyip tekrar işleyebilirsiniz.</p>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, geri al',
            cancelButtonText: 'Vazgeç'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            istek({ action: 'sayim-satir-geri-al', detay_id: detayId }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimDetayYukle();
                sayimlariYukle();
                stokYukle();
            }).fail(() => hata('Geri alınamadı.'));
        });
    });

    $(document).on('click', '#btnSayimExcel', function () {
        $('#btnSayimFormIndir').attr('href', 'views/aparat-takip/export-excel.php?tip=sayim&id=' + aktifSayimId);
        $('#sayimExcelDosya').val('');
        $('#btnSayimExcelGonder').prop('disabled', true);
        $('#sayimExcelYukleBlok, #btnSayimExcelGonder').toggle(sayimAciklamaAktif());
        new bootstrap.Modal('#modalSayimExcel').show();
    });

    $(document).on('change', '#sayimExcelDosya', function () {
        $('#btnSayimExcelGonder').prop('disabled', !this.files.length);
    });

    $(document).on('click', '#btnSayimExcelGonder', function () {
        const dosya = $('#sayimExcelDosya')[0].files[0];
        if (!dosya) return;

        const form = new FormData();
        form.append('action', 'sayim-excel-yukle');
        form.append('sayim_id', aktifSayimId);
        form.append('dosya', dosya);

        const $btn = $(this).prop('disabled', true).text('Yükleniyor...');

        $.ajax({ url: API, type: 'POST', data: form, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                bootstrap.Modal.getInstance(document.getElementById('modalSayimExcel')).hide();
                const hatalar = (res.hatalar || []).map((h) => `<li>${kacir(h)}</li>`).join('');
                if (hatalar) {
                    Swal.fire({ icon: 'warning', title: res.message, html: `<ul class="text-start small mb-0">${hatalar}</ul>` });
                } else {
                    basari(res.message);
                }
                sayimlariYukle();
                sayimDetayYukle();
            })
            .fail(() => hata('Excel yüklenemedi.'))
            .always(() => $btn.prop('disabled', false).text('Yükle'));
    });

    $(document).on('click', '#btnFarklariIsle', function () {
        if (!sayimVerisi) return;

        const detaylar = sayimVerisi.detaylar;
        const bekleyen = detaylar.filter((d) =>
            d.islendi == 0 && d.sayilan_adet !== null && parseInt(d.fark, 10) !== 0);

        const sayilanEkipler = {};
        detaylar.forEach(function (d) { if (d.sayilan_adet !== null) sayilanEkipler[d.ekip_id] = true; });
        const sifirAdaylari = detaylar.filter((d) =>
            d.islendi == 0 && d.sayilan_adet === null && sayilanEkipler[d.ekip_id] && parseInt(d.sistem_adet, 10) !== 0);

        if (!bekleyen.length && !sifirAdaylari.length) {
            Swal.fire({
                icon: 'info',
                title: 'İşlenecek fark yok',
                text: 'Sayılan adedi sistemden farklı olan ve henüz işlenmemiş satır bulunmuyor. Hiç sayım girilmemiş ekiplerin satırlarına işlem yapılmaz.'
            });
            return;
        }

        const satirlar = bekleyen.map((d) => ({ d: d, sifir: false, sayilan: parseInt(d.sayilan_adet, 10), fark: parseInt(d.fark, 10) }))
            .concat(sifirAdaylari.map((d) => ({ d: d, sifir: true, sayilan: 0, fark: -parseInt(d.sistem_adet, 10) })));

        const ekipSirasi = [];
        const ekipGruplari = {};
        satirlar.forEach(function (x) {
            if (!ekipGruplari[x.d.ekip_id]) { ekipGruplari[x.d.ekip_id] = []; ekipSirasi.push(x.d.ekip_id); }
            ekipGruplari[x.d.ekip_id].push(x);
        });

        const listeHtml = ekipSirasi.map(function (ekipId) {
            const grup = ekipGruplari[ekipId];
            const ilk = grup[0].d;
            const chipler = grup.map(function (x) {
                const eksik = x.fark < 0;
                return `<span class="isle-chip ${eksik ? 'eksi' : 'fazla'}${x.sifir ? ' sifir' : ''}">
                    ${kacir(x.d.aparat_adi)} <span class="isle-chip-adet">${x.d.sistem_adet} → ${x.sayilan}</span>
                    <b>${x.fark > 0 ? '+' : ''}${x.fark}</b>${x.sifir ? ' <i class="bx bx-question-mark" title="Sayılmadı, 0 kabul edilecek"></i>' : ''}</span>`;
            }).join('');
            return `<div class="isle-ekip" data-ekip="${ekipId}">
                <div class="isle-ekip-ad">${kacir(ilk.ekip_adi)}${ilk.ekip_uyeleri ? ` <small><i class="bx bx-user"></i> ${kacir(ilk.ekip_uyeleri)}</small>` : ''}</div>
                <div class="isle-chipler">${chipler}</div>
            </div>`;
        }).join('');

        const sifirKutu = sifirAdaylari.length ? `
            <label class="isle-sifir">
                <input class="form-check-input" type="checkbox" id="swSifirla" checked>
                <span><b>Sayılmayan satırlar da 0 olsun</b><br>
                <small>Sayım yapılan ${new Set(sifirAdaylari.map((d) => d.ekip_id)).size} ekipte sayılmamış ${sifirAdaylari.length} satır var. İşaretliyse bunlar 0 sayılır, sistemdeki adet kadar eksik çıkar (listede "?" ile gösterilir). İşaretlemezseniz bu satırlara dokunulmaz.</small></span>
            </label>` : '';

        const ozetCiz = function () {
            const sifirAktif = !$('#swSifirla').length || $('#swSifirla').is(':checked');
            const aktif = satirlar.filter((x) => !x.sifir || sifirAktif);
            const eksik = aktif.filter((x) => x.fark < 0);
            const fazla = aktif.filter((x) => x.fark > 0);
            const topla = (l) => l.reduce((t, x) => t + Math.abs(x.fark), 0);
            $('#isleEksikAdet').text(topla(eksik)); $('#isleEksikSatir').text(eksik.length);
            $('#isleFazlaAdet').text(topla(fazla)); $('#isleFazlaSatir').text(fazla.length);
            $('.isle-chip.sifir').toggle(sifirAktif);
            $('.isle-ekip').each(function () { $(this).toggle($(this).find('.isle-chip:visible').length > 0); });
        };

        Swal.fire({
            title: 'Sayım farkları stoğa işlensin mi?',
            width: '46rem',
            customClass: { popup: 'isle-pencere' },
            html: `<div class="text-start">
                <div class="isle-ozet">
                    <div class="isle-kutu eksik"><div class="isle-sayi"><span id="isleEksikAdet">0</span> <small>adet</small></div>
                        <div class="isle-etiket">Eksik çıkan · <span id="isleEksikSatir">0</span> satır</div>
                        <div class="isle-aciklama">Ekibin elinden düşer, kayıp sayılır.</div></div>
                    <div class="isle-kutu fazla"><div class="isle-sayi"><span id="isleFazlaAdet">0</span> <small>adet</small></div>
                        <div class="isle-etiket">Fazla çıkan · <span id="isleFazlaSatir">0</span> satır</div>
                        <div class="isle-aciklama">Ekibin eline eklenir, kayıptan düşer.</div></div>
                </div>
                ${sifirKutu}
                <div class="isle-liste">${listeHtml}</div>
                <div class="isle-not"><i class="bx bx-info-circle"></i>
                    <div>İşlenen her satır Hareket Dökümü'nde "Sayım Düzeltmesi" olarak görünür. Yanlış işlerseniz sayım açıkken satırın yanındaki <b>↶</b> butonuyla geri alabilirsiniz. Hiç sayım girilmemiş ekiplere dokunulmaz.</div></div>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Evet, stoğa işle',
            cancelButtonText: 'Vazgeç',
            didOpen: function () {
                ozetCiz();
                $('#swSifirla').on('change', ozetCiz);
            },
            preConfirm: () => ({ sifirla: $('#swSifirla').length ? $('#swSifirla').is(':checked') : false })
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'sayim-farklari-isle', sayim_id: aktifSayimId, bos_sifir: sonuc.value.sifirla ? 1 : 0 }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimDetayYukle();
                sayimlariYukle();
                stokYukle();
            }).fail(() => hata('Farklar işlenemedi.'));
        });
    });

    $(document).on('click', '#btnSayimKapat', function () {
        istek({ action: 'sayim-kapat', sayim_id: aktifSayimId }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            basari(res.message);
            sayimlariYukle();
            sayimDetayYukle();
        }).fail(() => hata('Sayım kapatılamadı.'));
    });

    function sayimIptalEt(id) {
        Swal.fire({
            title: 'Sayım iptal edilsin mi?',
            text: 'İptal edilen sayım düzenlenemez. Farkları stoğa işlenmiş sayımlar iptal edilemez.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'İptal Et',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'sayim-iptal', sayim_id: id }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimlariYukle();
                if (parseInt(id, 10) === parseInt(aktifSayimId, 10)) sayimDetayYukle();
            }).fail(() => hata('Sayım iptal edilemedi.'));
        });
    }

    $(document).on('click', '.sayim-wa-iptal', function (e) {
        e.preventDefault();
        e.stopPropagation();
        sayimIptalEt($(this).data('id'));
    });

    // ---------- Ekip / personel seçici (Sayım Başlat ve Sayıma Ekle) ----------

    const SY_GRUPLAR = {
        kesme: 'Kesme / Açma',
        kacak: 'Kaçak Kontrol',
        personel: 'Ekipsiz Personel',
        diger: 'Diğer'
    };

    function syZimmet(tur, id) {
        if (!sonMatris) return null;
        const satir = sonMatris.satirlar.find((s) => s.sahip_tipi === tur && parseInt(s.sahip_id, 10) === id);
        return satir ? satir.toplam : 0;
    }

    function syTumSatirlar() {
        return (window.aparatEkipler || []).map(function (e) {
            return { id: e.id, ad: e.adi, alt: e.uyeler || 'Personel atanmamış', grup: e.grup || 'diger', zimmet: syZimmet('ekip', e.id) };
        }).concat((window.aparatPersoneller || []).map(function (p) {
            return { id: -p.id, ad: p.adi, alt: (p.gorev || 'Kaçak Kontrol') + ' · ekipsiz', grup: 'personel', zimmet: syZimmet('personel', p.id) };
        }));
    }

    function ekipSecici(onek) {
        const durum = { secim: new Set(), grup: '', satirlar: [] };
        const el = (ek) => $('#' + onek + ek);

        function gorunenler() {
            const aranan = (el('Arama').val() || '').toLocaleLowerCase('tr').trim();
            return durum.satirlar.filter(function (x) {
                if (durum.grup === 'secili' && !durum.secim.has(x.id)) return false;
                if (durum.grup && durum.grup !== 'secili' && x.grup !== durum.grup) return false;
                return !aranan || (x.ad + ' ' + x.alt).toLocaleLowerCase('tr').indexOf(aranan) !== -1;
            });
        }

        function ozetCiz() {
            const sayac = { '': durum.satirlar.length, secili: durum.secim.size };
            durum.satirlar.forEach(function (x) { sayac[x.grup] = (sayac[x.grup] || 0) + 1; });

            const cipler = [['', 'Tümü'], ['kesme', SY_GRUPLAR.kesme], ['kacak', SY_GRUPLAR.kacak], ['personel', SY_GRUPLAR.personel], ['diger', SY_GRUPLAR.diger], ['secili', 'Seçililer']]
                .filter(([k]) => k === '' || k === 'secili' || sayac[k]);
            el('GrupFiltre').html(cipler.map(([k, ad]) =>
                `<button type="button" class="${durum.grup === k ? 'active' : ''}" data-grup="${k}">${ad} <span class="sayim-chip-say">${sayac[k] || 0}</span></button>`).join(''));

            const ekipSayisi = durum.satirlar.filter((x) => x.id > 0 && durum.secim.has(x.id)).length;
            const personelSayisi = durum.satirlar.filter((x) => x.id < 0 && durum.secim.has(x.id)).length;
            el('SecimOzet').text(`${ekipSayisi} ekip` + (personelSayisi ? ` + ${personelSayisi} personel` : '') + ' seçili');
            el('Kap').trigger('secim-degisti');
        }

        function listeCiz() {
            const gorunen = gorunenler();
            el('Liste').html(gorunen.length ? gorunen.map(function (x) {
                const secili = durum.secim.has(x.id);
                return `<label class="sy-satir${secili ? ' secili' : ''}">
                    <input type="checkbox" class="form-check-input sy-cb" value="${x.id}"${secili ? ' checked' : ''}>
                    <div><div class="sy-ad">${kacir(x.ad)}</div><div class="sy-alt">${kacir(x.alt)}</div></div>
                    <span class="sy-rozet">${SY_GRUPLAR[x.grup]}</span>
                    <span class="sy-stok">${x.zimmet === null ? '' : 'Zimmette ' + x.zimmet}</span>
                </label>`;
            }).join('') : '<div class="text-center text-muted small py-4">Eşleşen kayıt yok.</div>');
            ozetCiz();
        }

        $(document).on('change', `#${onek}Liste .sy-cb`, function () {
            const id = parseInt(this.value, 10);
            if (this.checked) durum.secim.add(id); else durum.secim.delete(id);
            $(this).closest('.sy-satir').toggleClass('secili', this.checked);
            ozetCiz();
        });
        $(document).on('click', `#${onek}GrupFiltre button`, function () { durum.grup = String($(this).data('grup')); listeCiz(); });
        $(document).on('input', `#${onek}Arama`, listeCiz);
        $(document).on('click', `#${onek}GorunenleriSec`, function () { gorunenler().forEach((x) => durum.secim.add(x.id)); listeCiz(); });
        $(document).on('click', `#${onek}GorunenleriKaldir`, function () { gorunenler().forEach((x) => durum.secim.delete(x.id)); listeCiz(); });

        return {
            ac: function (satirlar, varsayilanHepsi) {
                durum.satirlar = satirlar;
                durum.secim = new Set(varsayilanHepsi ? satirlar.map((x) => x.id) : []);
                durum.grup = '';
                el('Arama').val('');
                listeCiz();
            },
            secim: () => Array.from(durum.secim)
        };
    }

    const syBaslatSecici = ekipSecici('sy');
    const syEkleSecici = ekipSecici('se');

    $('#btnSayimBaslat').on('click', function () {
        syBaslatSecici.ac(syTumSatirlar(), true);
        new bootstrap.Modal('#modalSayim').show();
    });

    $('#formSayim').on('submit', function (e) {
        e.preventDefault();

        const secim = syBaslatSecici.secim();
        if (!secim.length) return hata('Sayıma en az bir ekip veya personel seçmelisiniz.');

        istek({
            action: 'sayim-baslat',
            baslik: $('#sayim_baslik').val(),
            aciklama: $('#sayim_aciklama').val(),
            ekipler: secim
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalSayim')).hide();
            basari(res.message);
            aktifSayimId = res.id;
            sayimlariYukle();
            sayimDetayYukle();
        }).fail(() => hata('Sayım başlatılamadı.'));
    });

    // Açık sayıma ekip / personel ekle
    $(document).on('click', '#btnSayimEkipEkle', function () {
        const sayimdakiler = new Set(sayimVerisi.detaylar.map((d) => parseInt(d.ekip_id, 10)));
        const adaylar = syTumSatirlar().filter((x) => !sayimdakiler.has(x.id));
        syEkleSecici.ac(adaylar, false);
        $('#seBosMesaj').toggleClass('d-none', adaylar.length > 0);
        $('#btnSayimEkleKaydet').prop('disabled', true);
        new bootstrap.Modal('#modalSayimEkle').show();
    });

    $('#seKap').on('secim-degisti', function () {
        $('#btnSayimEkleKaydet').prop('disabled', syEkleSecici.secim().length === 0);
    });

    $('#btnSayimEkleKaydet').on('click', function () {
        const secim = syEkleSecici.secim();
        if (!secim.length) return;

        const $btn = $(this).prop('disabled', true).text('Ekleniyor...');
        istek({ action: 'sayim-ekip-ekle', sayim_id: aktifSayimId, ekipler: secim }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalSayimEkle')).hide();
            basari(res.message);
            sayimDetayYukle();
            sayimlariYukle();
        }).fail(() => hata('Sayıma eklenemedi.'))
            .always(() => $btn.prop('disabled', false).text('Sayıma Ekle'));
    });

    // Sayımdan ekip / personel çıkar
    $(document).on('click', '.sayim-ekip-cikar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const ekipId = parseInt($(this).data('ekip'), 10);
        const satirlar = sayimVerisi.detaylar.filter((d) => parseInt(d.ekip_id, 10) === ekipId);
        const girilen = satirlar.filter((d) => d.sayilan_adet !== null).length;
        const ilk = satirlar[0] || {};

        Swal.fire({
            title: 'Sayımdan çıkarılsın mı?',
            html: `<div class="text-start">
                <div class="border rounded p-2 mb-2 bg-light"><b>${kacir(ilk.ekip_adi || '')}</b>${ilk.ekip_uyeleri ? `<br><span class="text-muted">${kacir(ilk.ekip_uyeleri)}</span>` : ''}</div>
                <p class="mb-2">Bu sayımda sayılmayacak ve listeden kalkacak. <b>Stoğa hiçbir etkisi olmaz.</b></p>
                ${girilen ? `<p class="mb-2 text-danger">Girilmiş <b>${girilen}</b> satırlık sayım adedi de silinir.</p>` : ''}
                <p class="mb-0 text-muted small">Sonradan fikrinizi değiştirirseniz "Ekip / Personel Ekle" ile geri ekleyebilirsiniz.</p>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, çıkar',
            cancelButtonText: 'Vazgeç'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            istek({ action: 'sayim-ekip-cikar', sayim_id: aktifSayimId, ekip_id: ekipId }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimDetayYukle();
                sayimlariYukle();
            }).fail(() => hata('Sayımdan çıkarılamadı.'));
        });
    });

    // ---------- Raporlar ----------

    $('#btnSahadaTakili').on('click', function () {
        istek({
            action: 'sahada-takili',
            aparat_tip_id: $('#rapor_aparat').val() || 0,
            min_gun: $('#rapor_min_gun').val() || 0
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (s) {
                return {
                    tarih: tarihGoster(s.tarih),
                    gun: parseInt(s.gun_sayisi, 10),
                    abone_no: s.abone_no || '',
                    sayac_no: s.sayac_no || '',
                    bolge: [s.ilce, s.mahalle].filter(Boolean).join(' / '),
                    aparat: s.aparat_adi || '',
                    adet: parseInt(s.adet, 10),
                    ekip: s.ekip_adi || ''
                };
            });

            dtKur('#tabloSahada', {
                data: satirlar,
                order: [[1, 'desc']],
                columns: [
                    { data: 'tarih' },
                    {
                        data: 'gun',
                        className: 'text-center',
                        render: bicim(function (gun) {
                            const renk = gun > 90 ? 'danger' : (gun > 30 ? 'warning' : 'secondary');
                            return `<span class="badge bg-${renk}-subtle text-${renk}">${gun}</span>`;
                        })
                    },
                    { data: 'abone_no' },
                    { data: 'sayac_no' },
                    { data: 'bolge' },
                    { data: 'aparat' },
                    { data: 'adet', className: 'text-center' },
                    { data: 'ekip' }
                ]
            });
        }).fail(() => hata('Rapor yüklenemedi.'));
    });

    $('#btnDonemselOzet').on('click', function () {
        istek({
            action: 'donemsel-ozet',
            start_date: tarihGonder('#ozet_bas'),
            end_date: tarihGonder('#ozet_bit')
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (o) {
                return {
                    islem: ISLEM_ADI[o.islem_tipi] || o.islem_tipi,
                    aparat: o.aparat_adi || 'Aparatsız',
                    kayit_sayisi: parseInt(o.kayit_sayisi, 10),
                    aparat_adedi: parseInt(o.aparat_adedi, 10),
                    hasarli: parseInt(o.hasarli, 10),
                    kayip: parseInt(o.kayip, 10)
                };
            });

            dtKur('#tabloDonemsel', {
                data: satirlar,
                order: [[0, 'asc']],
                columns: [
                    { data: 'islem' },
                    { data: 'aparat' },
                    { data: 'kayit_sayisi', className: 'text-center' },
                    { data: 'aparat_adedi', className: 'text-center fw-bold' },
                    { data: 'hasarli', className: 'text-center' },
                    { data: 'kayip', className: 'text-center' }
                ]
            });
        }).fail(() => hata('Özet yüklenemedi.'));
    });

    $('#btnApiKarsilastir').on('click', function () {
        istek({
            action: 'api-karsilastirma',
            start_date: tarihGonder('#ozet_bas'),
            end_date: tarihGonder('#ozet_bit')
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (k) {
                return {
                    tarih: tarihGoster(k.tarih),
                    ekip: k.ekip_adi || '',
                    api_adet: parseInt(k.api_adet, 10),
                    panel_adet: parseInt(k.panel_adet, 10),
                    fark: parseInt(k.fark, 10)
                };
            });

            dtKur('#tabloApiKarsilastirma', {
                data: satirlar,
                order: [[0, 'desc']],
                columns: [
                    { data: 'tarih' },
                    { data: 'ekip' },
                    { data: 'api_adet', className: 'text-center' },
                    { data: 'panel_adet', className: 'text-center' },
                    {
                        data: 'fark',
                        className: 'text-center fw-bold',
                        render: bicim(function (fark) {
                            const renk = fark === 0 ? 'text-success' : 'text-danger';
                            return `<span class="${renk}">${fark > 0 ? '+' : ''}${fark}</span>`;
                        })
                    }
                ]
            });
        }).fail(() => hata('Karşılaştırma yüklenemedi.'));
    });

    // ---------- Tanımlar ----------

    const tipVerileri = {};

    function tipleriYukle() {
        if (!$('#tabloTipler').length) return;

        istek({ action: 'tip-listesi' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const satirlar = res.data.map(function (t) {
                tipVerileri[t.id] = t;
                return {
                    id: t.id,
                    sira: parseInt(t.sira, 10),
                    resim: t.resim || '',
                    ad: t.ad,
                    kod: t.kod,
                    renk: t.renk,
                    depo_kritik: parseInt(t.depo_kritik_stok, 10) || 0,
                    ekip_kritik: parseInt(t.ekip_kritik_stok, 10) || 0,
                    varsayilan_kirma: parseInt(t.varsayilan_kirma, 10) === 1,
                    aciklama: t.aciklama || '',
                    durum: t.is_active == 1 ? 'Aktif' : 'Pasif'
                };
            });

            dtKur('#tabloTipler', {
                data: satirlar,
                order: [[0, 'asc']],
                columns: [
                    { data: 'sira', className: 'text-center' },
                    {
                        data: 'resim',
                        className: 'text-center',
                        orderable: false,
                        searchable: false,
                        render: function (resim, type, satir) {
                            if (resim) {
                                return `<img src="views/aparat-takip/tip-resim-goruntule.php?id=${satir.id}&v=${Date.now()}" 
                                             class="img-thumbnail btn-tip-resim-onizle" 
                                             data-id="${satir.id}" 
                                             data-ad="${kacir(satir.ad)}" 
                                             style="width: 38px; height: 38px; object-fit: cover; border-radius: 6px; cursor: pointer;" 
                                             title="Görseli büyüt">`;
                            }
                            return `<span class="badge bg-light text-muted p-2" title="Görsel yok"><i class="bx bx-image-alt fs-5"></i></span>`;
                        }
                    },
                    {
                        data: 'ad',
                        render: bicim(function (deger, satir) {
                            const rozet = satir.varsayilan_kirma
                                ? ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="Tipi bilinmeyen online kesme, açma ve kırmalar bu tipten işlenir">Online varsayılan tip</span>'
                                : '';
                            return `<b>${kacir(deger)}</b>${rozet}`;
                        })
                    },
                    {
                        data: 'kod',
                        render: bicim(function (deger, satir) {
                            return `<span class="badge bg-${kacir(satir.renk)}-subtle text-${kacir(satir.renk)}">${kacir(deger)}</span>`;
                        })
                    },
                    { data: 'renk' },
                    { data: 'depo_kritik', className: 'text-center' },
                    { data: 'ekip_kritik', className: 'text-center' },
                    { data: 'aciklama', className: 'small text-muted' },
                    {
                        data: 'durum',
                        render: bicim(function (deger) {
                            const renk = deger === 'Aktif' ? 'success' : 'secondary';
                            return `<span class="badge bg-${renk}-subtle text-${renk}">${deger}</span>`;
                        })
                    },
                    {
                        data: 'id',
                        className: 'text-center text-nowrap',
                        orderable: false,
                        searchable: false,
                        render: function (id) {
                            return `<div class="action-btn-group d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-subtle-warning table-action-btn btn-tip-duzenle" data-id="${id}" title="Düzenle"><i class="bx bx-edit-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-tip-sil" data-id="${id}" title="Sil"><i class="bx bx-trash"></i></button>
                            </div>`;
                        }
                    }
                ]
            });
        }).fail(() => hata('Tipler yüklenemedi.'));
    }

    $('#btnTipEkle').on('click', function () {
        $('#formTip')[0].reset();
        $('#tip_id').val(0);
        $('#tip_resim_sil').val(0);
        $('#tip_resim').val('');
        $('#tip_resim_onizleme_kutu').addClass('d-none');
        $('#tip_resim_img').attr('src', '');
        $('#tipModalBaslik').text('Yeni Aparat Tipi');
        $('#tip_renk').val('primary').trigger('change');
        $('#tip_aktif').prop('checked', true);
        $('#tip_depo_kritik, #tip_ekip_kritik').val(0);
        $('#tip_varsayilan_kirma').prop('checked', false);
        new bootstrap.Modal('#modalTip').show();
    });

    $(document).on('click', '.btn-tip-duzenle', function () {
        const t = tipVerileri[$(this).data('id')];
        if (!t) return;
        $('#tip_id').val(t.id);
        $('#tip_ad').val(t.ad);
        $('#tip_kod').val(t.kod);
        $('#tip_sira').val(t.sira);
        $('#tip_depo_kritik').val(parseInt(t.depo_kritik_stok, 10) || 0);
        $('#tip_ekip_kritik').val(parseInt(t.ekip_kritik_stok, 10) || 0);
        $('#tip_varsayilan_kirma').prop('checked', parseInt(t.varsayilan_kirma, 10) === 1);
        $('#tip_aciklama').val(t.aciklama || '');
        $('#tip_renk').val(t.renk).trigger('change');
        $('#tip_aktif').prop('checked', t.is_active == 1);
        $('#tip_resim_sil').val(0);
        $('#tip_resim').val('');

        if (t.resim) {
            $('#tip_resim_img').attr('src', `views/aparat-takip/tip-resim-goruntule.php?id=${t.id}&v=${Date.now()}`);
            $('#tip_resim_onizleme_kutu').removeClass('d-none');
        } else {
            $('#tip_resim_onizleme_kutu').addClass('d-none');
            $('#tip_resim_img').attr('src', '');
        }

        $('#tipModalBaslik').text('Aparat Tipini Düzenle');
        new bootstrap.Modal('#modalTip').show();
    });

    $('#tip_resim').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#tip_resim_img').attr('src', e.target.result);
                $('#tip_resim_onizleme_kutu').removeClass('d-none');
            };
            reader.readAsDataURL(file);
            $('#tip_resim_sil').val(0);
        }
    });

    $('#btnTipResimSil').on('click', function () {
        $('#tip_resim').val('');
        $('#tip_resim_sil').val(1);
        $('#tip_resim_onizleme_kutu').addClass('d-none');
        $('#tip_resim_img').attr('src', '');
    });

    $(document).on('click', '.btn-tip-resim-onizle', function () {
        const id = $(this).data('id');
        const ad = $(this).data('ad');
        $('#modalTipResimBaslik').text(ad ? `${ad} - Görsel` : 'Aparat Görseli');
        $('#modalTipResimImg').attr('src', `views/aparat-takip/tip-resim-goruntule.php?id=${id}&v=${Date.now()}`);
        new bootstrap.Modal('#modalTipResimOnizleme').show();
    });

    $('#formTip').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'tip-kaydet');
        formData.append('id', $('#tip_id').val());
        formData.append('ad', $('#tip_ad').val());
        formData.append('kod', $('#tip_kod').val());
        formData.append('renk', $('#tip_renk').val());
        formData.append('sira', $('#tip_sira').val());
        formData.append('aciklama', $('#tip_aciklama').val());
        formData.append('depo_kritik_stok', $('#tip_depo_kritik').val() || 0);
        formData.append('varsayilan_kirma', $('#tip_varsayilan_kirma').is(':checked') ? 1 : 0);
        formData.append('ekip_kritik_stok', $('#tip_ekip_kritik').val() || 0);
        formData.append('is_active', $('#tip_aktif').is(':checked') ? 1 : 0);
        formData.append('resim_sil', $('#tip_resim_sil').val());

        const fileInput = $('#tip_resim')[0];
        if (fileInput && fileInput.files[0]) {
            formData.append('resim', fileInput.files[0]);
        }

        $.ajax({
            url: API,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            const modalEl = document.getElementById('modalTip');
            if (modalEl) {
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();
            }
            basari(res.message);
            tipleriYukle();
            stokYukle();
        }).fail(() => hata('Aparat tipi kaydedilemedi.'));
    });

    $(document).on('click', '.btn-tip-sil', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Aparat tipi silinsin mi?',
            text: 'Stok hareketi olan tipler silinmez, pasife alınır.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sil',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'tip-sil', id: id }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                tipleriYukle();
            }).fail(() => hata('Silme işlemi başarısız.'));
        });
    });

    // ---------- Sekme tetikleyicileri ----------

    function sekmeAksiyonlari(hedef) {
        $('.tab-aksiyon').each(function () {
            const goster = $(this).data('tab') === hedef;
            $(this).toggleClass('d-none', !goster).toggleClass('d-flex', goster);
        });
    }

    sekmeAksiyonlari($('a[data-bs-toggle="tab"].active').attr('href') || '#pane-stok');

    function sekmeAcildi(hedef) {
        sekmeAksiyonlari(hedef);

        if (hedef === '#pane-islemler' && !dtOrnekleri['#tabloIslemler']) islemleriYukle();
        if (hedef === '#pane-hareketler' && !dtOrnekleri['#tabloHareketler']) hareketleriYukle();
        if (hedef === '#pane-transferler' && !dtOrnekleri['#tabloTransferler']) transferleriYukle();
        if (hedef === '#pane-sayim') sayimlariYukle();
        if (hedef === '#pane-tanimlar' && !dtOrnekleri['#tabloTipler']) tipleriYukle();

        // Gizliyken kurulan tablolarda kolon genişlikleri şaşar; sekme açılınca düzeltilir.
        Object.keys(dtOrnekleri).forEach(function (secici) {
            if ($(secici).is(':visible')) dtOrnekleri[secici].columns.adjust();
        });
    }

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        sekmeAcildi($(e.target).attr('href'));
    });

    const aparatAkis = (function () {
        const kok = document.getElementById('aparatAkis');
        if (!kok) return null;

        const SURE = 3600;
        const hareketAzalt = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const BASLANGIC = { depo: 3, ekipA: 0, ekipB: 0, abone: 0 };
        const ADIMLAR = [
            { kim: 'siz', baslik: 'Depoya Giriş', metin: 'Aparat satın alınınca depoya eklenir. Depo artar.', yol: 'tedarik-depo', kaynak: 'tedarik', hedef: 'depo', renk: '#2563eb' },
            { kim: 'siz', baslik: 'Ekibe Ver', metin: 'Depodan Ahmet\'in ekibine aparat verilir. Depo azalır, Ahmet\'in zimmeti artar.', yol: 'depo-ekipA', kaynak: 'depo', hedef: 'ekipA', renk: '#d97706' },
            { kim: 'sistem', baslik: 'Kesme', metin: 'Ahmet sayacı aparatla keser. İş online sorgulamaya düşünce aparat Ahmet\'in zimmetinden kendiliğinden düşer, abonede takılı olur.', yol: 'ekipA-abone', kaynak: 'ekipA', hedef: 'abone', renk: '#dc2626' },
            { kim: 'sistem', baslik: 'Açma', metin: 'Günler sonra Mehmet aynı aboneyi açar ve aparatı söker. Açma online sorgulamaya düşünce aparat kendiliğinden Mehmet\'in zimmetine geçer.', yol: 'abone-ekipB', kaynak: 'abone', hedef: 'ekipB', renk: '#059669' },
            { kim: 'sistem', baslik: 'Ekipler Arası Değişim', metin: 'Mehmet aparatı telefondan Ahmet\'e gönderir. Ahmet onaylayınca Mehmet\'ten düşer, Ahmet\'e geçer. Elinde olmayanı gönderemez.', yol: 'ekipB-ekipA', kaynak: 'ekipB', hedef: 'ekipA', renk: '#7c3aed' },
            { kim: 'siz', baslik: 'İade Al', metin: 'Ahmet kullanmadığı ya da bozulan aparatı depoya getirir. Ahmet\'in zimmeti azalır, depo artar. Aparat bozuksa İade Al\'da “bozuk” işaretlenir, depoya değil hurdaya geçer.', yol: 'ekipA-depo', kaynak: 'ekipA', hedef: 'depo', renk: '#0891b2' }
        ];

        const aparat = document.getElementById('apfAparat');
        const adimKutu = document.getElementById('apfAdimlar');
        const oynatBtn = document.getElementById('apfOynat');
        let sira = 0;
        let calisiyor = false;
        let zamanlayici = null;
        let sayilar = {};

        ADIMLAR.forEach(function (adim, i) {
            const b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-label', (i + 1) + '. adım: ' + adim.baslik);
            b.title = adim.baslik;
            b.addEventListener('click', function () { git(i); });
            adimKutu.appendChild(b);
        });

        function konum(ad) {
            const d = kok.querySelector('[data-dugum="' + ad + '"]');
            return { left: d.style.left, top: d.style.top };
        }

        function sayilariYaz(degisen) {
            Object.keys(sayilar).forEach(function (k) {
                const el = kok.querySelector('[data-sayi="' + k + '"]');
                if (!el) return;
                el.textContent = sayilar[k];
                el.classList.remove('artti', 'azaldi');
                if (degisen && degisen[k]) {
                    void el.offsetWidth;
                    el.classList.add(degisen[k]);
                }
            });
        }

        function durumuHesapla(n) {
            sayilar = Object.assign({}, BASLANGIC);
            for (let i = 0; i < n; i++) {
                const a = ADIMLAR[i];
                if (a.kaynak in sayilar) sayilar[a.kaynak]--;
                if (a.hedef in sayilar) sayilar[a.hedef]++;
            }
        }

        function git(n) {
            clearTimeout(zamanlayici);
            sira = (n + ADIMLAR.length) % ADIMLAR.length;
            const adim = ADIMLAR[sira];
            kok.style.setProperty('--apf-renk', adim.renk);
            kok.style.setProperty('--apf-sure', SURE + 'ms');

            durumuHesapla(sira);
            sayilariYaz();

            const kim = document.getElementById('apfKim');
            kim.textContent = adim.kim === 'siz' ? 'Siz yaparsınız · sayfadaki buton' : 'Sistem yapar · otomatik';
            kim.className = 'apf-kim ' + adim.kim;
            document.getElementById('apfBaslik').textContent = (sira + 1) + '. ' + adim.baslik;
            document.getElementById('apfMetin').textContent = adim.metin;

            kok.querySelectorAll('.apf-yollar line').forEach(function (l) {
                l.classList.toggle('aktif', l.getAttribute('data-yol') === adim.yol);
            });
            kok.querySelectorAll('.apf-dugum').forEach(function (d) {
                const ad = d.getAttribute('data-dugum');
                d.classList.toggle('vurgu', ad === adim.kaynak || ad === adim.hedef);
            });
            Array.prototype.forEach.call(adimKutu.children, function (b, i) {
                b.classList.remove('aktif', 'bitti');
                if (i < sira) b.classList.add('bitti');
                b.setAttribute('aria-selected', i === sira ? 'true' : 'false');
            });
            const aktifCubuk = adimKutu.children[sira];
            void aktifCubuk.offsetWidth;
            aktifCubuk.classList.add('aktif');

            const baslangic = konum(adim.kaynak);
            const bitis = konum(adim.hedef);
            aparat.style.transition = 'none';
            aparat.style.left = baslangic.left;
            aparat.style.top = baslangic.top;
            aparat.classList.remove('gizli');
            void aparat.offsetWidth;
            aparat.style.transition = '';

            const hedefeVar = function () {
                const degisen = {};
                if (adim.kaynak in sayilar) { sayilar[adim.kaynak]--; degisen[adim.kaynak] = 'azaldi'; }
                if (adim.hedef in sayilar) { sayilar[adim.hedef]++; degisen[adim.hedef] = 'artti'; }
                sayilariYaz(degisen);
            };

            if (hareketAzalt) {
                aparat.style.left = bitis.left;
                aparat.style.top = bitis.top;
                hedefeVar();
            } else {
                zamanlayici = setTimeout(function () {
                    aparat.style.left = bitis.left;
                    aparat.style.top = bitis.top;
                    zamanlayici = setTimeout(function () {
                        hedefeVar();
                        if (calisiyor) zamanlayici = setTimeout(function () { git(sira + 1); }, SURE - 1550);
                    }, 1250);
                }, 300);
            }

        }

        function oynatDurumu(acik) {
            calisiyor = acik && !hareketAzalt;
            kok.classList.toggle('durdu', !calisiyor);
            oynatBtn.innerHTML = calisiyor ? '<i class="bx bx-pause"></i>' : '<i class="bx bx-play"></i>';
            oynatBtn.setAttribute('aria-label', calisiyor ? 'Duraklat' : 'Oynat');
            oynatBtn.title = calisiyor ? 'Duraklat' : 'Oynat';
        }

        oynatBtn.addEventListener('click', function () {
            oynatDurumu(!calisiyor);
            git(calisiyor ? sira + 1 : sira);
        });
        document.getElementById('apfIleri').addEventListener('click', function () { oynatDurumu(false); git(sira + 1); });
        document.getElementById('apfGeri').addEventListener('click', function () { oynatDurumu(false); git(sira - 1); });

        return {
            baslat: function () { oynatDurumu(true); git(0); },
            durdur: function () { clearTimeout(zamanlayici); oynatDurumu(false); }
        };
    })();

    $('#modalNasilCalisir')
        .on('shown.bs.modal', function () { if (aparatAkis) aparatAkis.baslat(); })
        .on('hidden.bs.modal', function () { if (aparatAkis) aparatAkis.durdur(); });

    $(function () {
        $('.select2').select2({ width: '100%' });
        $('#modalHavuz, #modalIslem, #modalTip, #modalSayim').each(function () {
            const modal = this;
            $(modal).find('.select2').select2({ width: '100%', dropdownParent: $(modal) });
        });
        stokYukle();

        const aktifSekmeBul = function () {
            return $('.nav-tabs-custom a[data-bs-toggle="tab"].active').attr('href')
                || $('a[data-bs-toggle="tab"].active').attr('href')
                || '#pane-stok';
        };
        const baslangicSekmesi = aktifSekmeBul();
        if (baslangicSekmesi && baslangicSekmesi !== '#pane-stok') sekmeAcildi(baslangicSekmesi);

        setTimeout(function () {
            const guncelSekme = aktifSekmeBul();
            if (guncelSekme && guncelSekme !== '#pane-stok') {
                const tabloMap = {
                    '#pane-islemler': '#tabloIslemler',
                    '#pane-hareketler': '#tabloHareketler',
                    '#pane-transferler': '#tabloTransferler',
                    '#pane-tanimlar': '#tabloTipler'
                };
                const tablo = tabloMap[guncelSekme];
                if (!tablo || !dtOrnekleri[tablo]) sekmeAcildi(guncelSekme);
            }
        }, 80);
    });

})(jQuery);
