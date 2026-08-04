(function ($) {
    'use strict';

    const API = 'views/aparat-takip/api.php';
    const yetki = window.aparatYetki || {};
    const tipler = window.aparatTipleri || [];

    let aktifSayimId = 0;
    let aktifIslemId = 0;

    // ---------- Yardımcılar ----------

    function kacir(deger) {
        return $('<div>').text(deger === null || deger === undefined ? '' : deger).html();
    }

    function tarihGoster(deger) {
        if (!deger) return '-';
        const parca = String(deger).split(' ');
        const gun = parca[0].split('-');
        if (gun.length !== 3) return kacir(deger);
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
        Swal.fire('Hata', mesaj || 'İşlem tamamlanamadı.', 'error');
    }

    function basari(mesaj) {
        Swal.fire('Başarılı', mesaj || 'İşlem tamamlandı.', 'success');
    }

    function istek(veri, tip) {
        return $.ajax({
            url: API,
            type: tip || 'GET',
            data: veri,
            dataType: 'json'
        });
    }

    function bosSatir(kolon, mesaj) {
        return `<tr><td colspan="${kolon}" class="text-center text-muted py-4">${kacir(mesaj)}</td></tr>`;
    }

    function durumRozeti(durum) {
        const harita = {
            aktif: 'success', iptal: 'secondary',
            beklemede: 'warning', onaylandi: 'success', reddedildi: 'danger'
        };
        const metin = {
            aktif: 'Aktif', iptal: 'İptal',
            beklemede: 'Beklemede', onaylandi: 'Onaylandı', reddedildi: 'Reddedildi'
        };
        return `<span class="badge bg-${harita[durum] || 'secondary'}-subtle text-${harita[durum] || 'secondary'}">${metin[durum] || kacir(durum)}</span>`;
    }

    // ---------- Stok matrisi ----------

    function stokYukle() {
        istek({ action: 'stok-matris' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const veri = res.data;
            const govde = $('#tabloStokMatris tbody').empty();
            const altlik = $('#tabloStokMatris tfoot').empty();
            const kolonSayisi = veri.tipler.length + 2;

            if (!veri.tipler.length) {
                govde.html(bosSatir(kolonSayisi, 'Aparat tipi tanımlanmamış.'));
                return;
            }

            let sahaToplam = 0;

            veri.satirlar.forEach(function (satir) {
                const havuzMu = satir.sahip_tipi !== 'ekip';
                let hucreler = '';

                veri.tipler.forEach(function (tip) {
                    const adet = satir.adetler[tip.id] || 0;
                    let sinif = 'adet-hucre';
                    if (adet === 0) sinif += ' adet-sifir';
                    if (adet < 0) sinif += ' adet-negatif';
                    hucreler += `<td class="${sinif}">${adet}</td>`;
                });

                if (satir.sahip_tipi === 'saha') sahaToplam = satir.toplam;

                const etiket = havuzMu
                    ? `<i class="bx bx-box me-1 text-muted"></i><b>${kacir(satir.baslik)}</b>`
                    : `${kacir(satir.baslik)}${satir.bolge ? ` <small class="text-muted">${kacir(satir.bolge)}</small>` : ''}`;

                govde.append(`<tr class="${havuzMu ? 'havuz-satiri' : ''}">
                    <td>${etiket}</td>
                    ${hucreler}
                    <td class="adet-hucre">${satir.toplam}</td>
                </tr>`);
            });

            let altHucreler = '';
            veri.tipler.forEach(function (tip) {
                altHucreler += `<td class="adet-hucre">${veri.sutun_toplam[tip.id] || 0}</td>`;
            });
            altlik.html(`<tr><td>TOPLAM</td>${altHucreler}<td class="adet-hucre">${veri.genel_toplam}</td></tr>`);

            $('#ozetToplam').text(veri.genel_toplam);
            $('#ozetSaha').text(sahaToplam);
            $('#ozetTransfer').text(res.bekleyen_transfer || 0);
            $('#ozetNegatif').text(res.negatif_islem || 0);

            if (res.tutarsiz_satir > 0) {
                $('#tutarsizSayi').text(res.tutarsiz_satir);
                $('#aparatTutarsizlikUyari').removeClass('d-none').addClass('d-flex');
            } else {
                $('#aparatTutarsizlikUyari').addClass('d-none').removeClass('d-flex');
            }
        }).fail(() => hata('Stok tablosu yüklenemedi.'));
    }

    $('#btnStokYenile').on('click', stokYukle);

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

    function havuzEkipGorunurluk() {
        const tur = $('#havuz_tur').val();
        const gerekli = ['depo_cikis', 'depo_iade', 'hurda', 'kayip', 'acilis'].indexOf(tur) !== -1;
        $('#havuzEkipAlan').toggle(gerekli);
    }

    $('#btnHavuzHareketi').on('click', function () {
        $('#formHavuz')[0].reset();
        $('#havuz_tur').val('depo_giris').trigger('change');
        $('#havuz_ekip').val('').trigger('change');
        $('#havuz_aparat').val('').trigger('change');
        $('#havuz_adet').val(1);
        havuzEkipGorunurluk();
        new bootstrap.Modal('#modalHavuz').show();
    });

    $('#havuz_tur').on('change', havuzEkipGorunurluk);

    $('#formHavuz').on('submit', function (e) {
        e.preventDefault();

        istek({
            action: 'havuz-hareketi',
            tur: $('#havuz_tur').val(),
            ekip_id: $('#havuz_ekip').val() || 0,
            aparat_tip_id: $('#havuz_aparat').val() || 0,
            adet: $('#havuz_adet').val(),
            aciklama: $('#havuz_aciklama').val()
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalHavuz')).hide();
            basari(res.message);
            stokYukle();
        }).fail(() => hata('Hareket kaydedilemedi.'));
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

    function islemFiltresi() {
        return {
            action: 'islem-listesi',
            start_date: tarihGonder('#islem_bas'),
            end_date: tarihGonder('#islem_bit'),
            ekip_id: $('#islem_ekip').val() || 0,
            islem_tipi: $('#islem_tip_filtre').val() || '',
            aparat_tip_id: $('#islem_aparat').val() || 0,
            durum: $('#islemIptalGoster').is(':checked') ? '' : 'aktif',
            sadece_negatif: $('#islemSadeceNegatif').is(':checked') ? 1 : 0
        };
    }

    function islemleriYukle() {
        const govde = $('#tabloIslemler tbody').html(bosSatir(11, 'Yükleniyor...'));

        istek(islemFiltresi()).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(11, 'Kayıt bulunamadı.'));
                return;
            }

            res.data.forEach(function (k) {
                const aparat = k.aparatsiz == 1
                    ? '<span class="text-muted">Aparatsız</span>'
                    : kacir(k.aparat_adi || '-');

                const uyarilar = (k.negatif_stok == 1 ? '<i class="bx bx-error text-danger ms-1" title="Negatif stok"></i>' : '')
                    + (k.mukerrer_uyari == 1 ? '<i class="bx bx-copy text-warning ms-1" title="Mükerrer kayıt uyarısı"></i>' : '');

                govde.append(`<tr>
                    <td>${tarihGoster(k.tarih)}</td>
                    <td><span class="badge bg-${k.islem_tipi === 'kesme' ? 'danger' : 'success'}-subtle text-${k.islem_tipi === 'kesme' ? 'danger' : 'success'}">${k.islem_tipi === 'kesme' ? 'Kesme' : 'Açma'}</span></td>
                    <td>${kacir(k.ekip_kodu || k.ekip_adi || '-')}</td>
                    <td>${kacir(k.personel_adi || '-')}</td>
                    <td>${kacir(k.abone_no || '-')}${uyarilar}</td>
                    <td>${kacir(k.sayac_no || '-')}</td>
                    <td>${aparat}</td>
                    <td class="text-center">${k.adet}</td>
                    <td>${durumRozeti(k.durum)}</td>
                    <td class="text-center">${k.foto_sayisi > 0 ? `<i class="bx bx-image text-primary"></i> ${k.foto_sayisi}` : '<span class="text-muted">-</span>'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary btn-islem-detay" data-id="${k.id}">
                            <i class="bx bx-show"></i>
                        </button>
                    </td>
                </tr>`);
            });
        }).fail(() => hata('İşlemler yüklenemedi.'));
    }

    $('#btnIslemListele').on('click', islemleriYukle);
    $('#islemSadeceNegatif, #islemIptalGoster').on('change', islemleriYukle);

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
                            <tr><th style="width:40%">İşlem</th><td>${k.islem_tipi === 'kesme' ? 'Kesme' : 'Açma'} ${durumRozeti(k.durum)}</td></tr>
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
            new bootstrap.Modal('#modalIslemDetay').show();
        }).fail(() => hata('Detay yüklenemedi.'));
    });

    $('#btnIslemIptal').on('click', function () {
        Swal.fire({
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

    function hareketleriYukle() {
        const govde = $('#tabloHareketler tbody').html(bosSatir(8, 'Yükleniyor...'));

        istek(hareketFiltresi()).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(8, 'Hareket bulunamadı.'));
                return;
            }

            res.data.forEach(function (h) {
                govde.append(`<tr class="${h.iptal_mi == 1 ? 'text-muted' : ''}">
                    <td>${tarihGoster(h.tarih)}</td>
                    <td>${kacir(h.hareket_tipi)}</td>
                    <td>${kacir(h.sahip_tipi)}</td>
                    <td>${kacir(h.ekip_adi || '-')}</td>
                    <td>${kacir(h.aparat_adi || '-')}</td>
                    <td class="text-center fw-bold ${h.adet < 0 ? 'text-danger' : 'text-success'}">${h.adet > 0 ? '+' : ''}${h.adet}</td>
                    <td>${kacir(h.personel_adi || h.kullanici_adi || '-')}</td>
                    <td class="small">${kacir(h.aciklama || '')}${h.iptal_mi == 1 ? ' <span class="badge bg-secondary-subtle text-secondary">iptal</span>' : ''}</td>
                </tr>`);
            });
        }).fail(() => hata('Hareketler yüklenemedi.'));
    }

    $('#btnHareketListele').on('click', hareketleriYukle);

    $('#btnHareketExcel').on('click', function (e) {
        e.preventDefault();
        const p = hareketFiltresi();
        delete p.action;
        p.tip = 'hareket';
        window.location = 'views/aparat-takip/export-excel.php?' + $.param(p);
    });

    // ---------- Transferler ----------

    let transferDurum = '';

    function transferleriYukle() {
        const govde = $('#tabloTransferler tbody').html(bosSatir(9, 'Yükleniyor...'));

        istek({ action: 'transfer-listesi', durum: transferDurum }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(9, 'Transfer kaydı bulunamadı.'));
                return;
            }

            res.data.forEach(function (t) {
                const adet = t.durum === 'onaylandi' && t.onaylanan_adet !== null && t.onaylanan_adet != t.adet
                    ? `${t.onaylanan_adet} <s class="text-muted">${t.adet}</s>`
                    : t.adet;

                const aksiyon = (yetki.transfer && t.durum === 'beklemede')
                    ? `<button class="btn btn-sm btn-outline-danger btn-transfer-iptal" data-id="${t.id}"><i class="bx bx-x"></i> İptal</button>`
                    : '';

                govde.append(`<tr>
                    <td>${tarihGoster(t.tarih)}</td>
                    <td>${kacir(t.veren_ekip_adi || '-')}</td>
                    <td>${kacir(t.alan_ekip_adi || '-')}</td>
                    <td>${kacir(t.aparat_adi || '-')}</td>
                    <td class="text-center">${adet}</td>
                    <td>${durumRozeti(t.durum)}${t.red_nedeni ? `<div class="small text-muted">${kacir(t.red_nedeni)}</div>` : ''}</td>
                    <td>${kacir(t.olusturan_adi || '-')}</td>
                    <td>${kacir(t.onaylayan_adi || '-')}</td>
                    <td class="text-end">${aksiyon}</td>
                </tr>`);
            });
        }).fail(() => hata('Transferler yüklenemedi.'));
    }

    $('#transferDurumFiltre button').on('click', function () {
        $('#transferDurumFiltre button').each(function () {
            const durum = $(this).data('durum');
            const renk = durum === 'beklemede' ? 'warning' : (durum === 'onaylandi' ? 'success' : (durum === 'reddedildi' ? 'danger' : 'primary'));
            $(this).removeClass(`btn-${renk}`).addClass(`btn-outline-${renk}`);
        });

        const durum = $(this).data('durum');
        const renk = durum === 'beklemede' ? 'warning' : (durum === 'onaylandi' ? 'success' : (durum === 'reddedildi' ? 'danger' : 'primary'));
        $(this).removeClass(`btn-outline-${renk}`).addClass(`btn-${renk}`);

        transferDurum = durum;
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

    function sayimlariYukle() {
        istek({ action: 'sayim-listesi' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const liste = $('#sayimListesi').empty();

            if (!res.data.length) {
                liste.html('<div class="list-group-item text-muted small">Henüz sayım yapılmamış.</div>');
                return;
            }

            res.data.forEach(function (s) {
                const ilerleme = s.satir_sayisi > 0 ? Math.round((s.girilen_sayisi / s.satir_sayisi) * 100) : 0;
                liste.append(`<a href="#" class="list-group-item list-group-item-action sayim-secim" data-id="${s.id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <b>${kacir(s.baslik)}</b>
                        ${s.durum === 'acik' ? '<span class="badge bg-warning-subtle text-warning">Açık</span>' : (s.durum === 'iptal' ? '<span class="badge bg-secondary-subtle text-secondary">İptal</span>' : '<span class="badge bg-success-subtle text-success">Tamamlandı</span>')}
                    </div>
                    <div class="small text-muted">${tarihGoster(s.baslangic_tarihi)} · ${s.ekip_sayisi} ekip</div>
                    <div class="progress mt-2" style="height:4px">
                        <div class="progress-bar" style="width:${ilerleme}%"></div>
                    </div>
                    <div class="small text-muted mt-1">${s.girilen_sayisi}/${s.satir_sayisi} satır girildi</div>
                </a>`);
            });
        }).fail(() => hata('Sayımlar yüklenemedi.'));
    }

    $(document).on('click', '.sayim-secim', function (e) {
        e.preventDefault();
        aktifSayimId = parseInt($(this).data('id'), 10);
        $('.sayim-secim').removeClass('active');
        $(this).addClass('active');
        sayimDetayYukle();
    });

    function sayimDetayYukle() {
        if (!aktifSayimId) return;

        istek({ action: 'sayim-detay', id: aktifSayimId }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);

            const sayim = res.data;
            const acik = sayim.durum === 'acik';
            $('#sayimBaslik').text(sayim.baslik);

            const aksiyonlar = $('#sayimAksiyonlar').empty();
            if (acik && yetki.sayim) {
                aksiyonlar.append('<button class="btn btn-sm btn-outline-primary" id="btnFarklariIsle"><i class="bx bx-check-double me-1"></i>Farkları İşle</button>');
                aksiyonlar.append('<button class="btn btn-sm btn-success" id="btnSayimKapat"><i class="bx bx-lock me-1"></i>Tamamla</button>');
                aksiyonlar.append('<button class="btn btn-sm btn-outline-danger" id="btnSayimIptal"><i class="bx bx-x"></i></button>');
            }

            let govde = '';
            let sonEkip = null;

            res.detaylar.forEach(function (d) {
                if (sonEkip !== d.ekip_adi) {
                    sonEkip = d.ekip_adi;
                    govde += `<tr class="table-light"><td colspan="6"><b>${kacir(d.ekip_adi)}</b></td></tr>`;
                }

                const fark = d.sayilan_adet === null ? null : parseInt(d.fark, 10);
                const farkHtml = fark === null ? '-' :
                    `<span class="${fark === 0 ? 'text-muted' : (fark < 0 ? 'text-danger' : 'text-success')}">${fark > 0 ? '+' : ''}${fark}</span>`;

                const girisHtml = (acik && yetki.sayim && d.islendi == 0)
                    ? `<input type="number" class="form-control form-control-sm sayim-adet-input sayim-giris"
                             data-ekip="${d.ekip_id}" data-tip="${d.aparat_tip_id}"
                             value="${d.sayilan_adet === null ? '' : d.sayilan_adet}" min="0">`
                    : (d.sayilan_adet === null ? '-' : d.sayilan_adet);

                const aciklamaHtml = (acik && yetki.sayim && d.islendi == 0)
                    ? `<input type="text" class="form-control form-control-sm sayim-aciklama"
                             data-ekip="${d.ekip_id}" data-tip="${d.aparat_tip_id}"
                             value="${kacir(d.aciklama || '')}" placeholder="Fark gerekçesi">`
                    : kacir(d.aciklama || '');

                govde += `<tr>
                    <td class="ps-4">${kacir(d.aparat_adi)}</td>
                    <td class="text-center">${d.sistem_adet}</td>
                    <td class="text-center">${girisHtml}</td>
                    <td class="text-center">${farkHtml}</td>
                    <td>${aciklamaHtml}</td>
                    <td class="text-center">${d.islendi == 1 ? '<i class="bx bx-check text-success"></i>' : ''}</td>
                </tr>`;
            });

            $('#sayimDetayIcerik').html(`
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Aparat</th>
                                <th class="text-center">Sistem</th>
                                <th class="text-center">Sayılan</th>
                                <th class="text-center">Fark</th>
                                <th>Açıklama</th>
                                <th class="text-center">İşlendi</th>
                            </tr>
                        </thead>
                        <tbody>${govde || '<tr><td colspan="6" class="text-muted">Satır yok.</td></tr>'}</tbody>
                    </table>
                </div>`);
        }).fail(() => hata('Sayım detayı yüklenemedi.'));
    }

    $(document).on('change', '.sayim-giris', function () {
        const ekip = $(this).data('ekip');
        const tip = $(this).data('tip');
        const deger = $(this).val();

        if (deger === '') return;

        const aciklama = $(`.sayim-aciklama[data-ekip="${ekip}"][data-tip="${tip}"]`).val() || '';

        istek({
            action: 'sayim-gir',
            sayim_id: aktifSayimId,
            ekip_id: ekip,
            aparat_tip_id: tip,
            sayilan_adet: deger,
            aciklama: aciklama
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            sayimDetayYukle();
        }).fail(() => hata('Sayım kaydedilemedi.'));
    });

    $(document).on('click', '#btnFarklariIsle', function () {
        Swal.fire({
            title: 'Farklar stoğa işlensin mi?',
            text: 'Eksik çıkan aparatlar kayıp havuzuna, fazla çıkanlar kayıp havuzundan düşülerek işlenir.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'İşle',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'sayim-farklari-isle', sayim_id: aktifSayimId }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimDetayYukle();
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

    $(document).on('click', '#btnSayimIptal', function () {
        Swal.fire({
            title: 'Sayım iptal edilsin mi?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'İptal Et',
            cancelButtonText: 'Vazgeç'
        }).then(function (sonuc) {
            if (!sonuc.isConfirmed) return;
            istek({ action: 'sayim-iptal', sayim_id: aktifSayimId }, 'POST').done(function (res) {
                if (res.status !== 'success') return hata(res.message);
                basari(res.message);
                sayimlariYukle();
                sayimDetayYukle();
            }).fail(() => hata('Sayım iptal edilemedi.'));
        });
    });

    $('#btnSayimBaslat').on('click', function () {
        $('#sayim_ekipler').val(null).trigger('change');
        new bootstrap.Modal('#modalSayim').show();
    });

    $('#formSayim').on('submit', function (e) {
        e.preventDefault();

        istek({
            action: 'sayim-baslat',
            baslik: $('#sayim_baslik').val(),
            aciklama: $('#sayim_aciklama').val(),
            ekipler: $('#sayim_ekipler').val() || []
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalSayim')).hide();
            basari(res.message);
            aktifSayimId = res.id;
            sayimlariYukle();
            sayimDetayYukle();
        }).fail(() => hata('Sayım başlatılamadı.'));
    });

    // ---------- Raporlar ----------

    $('#btnSahadaTakili').on('click', function () {
        const govde = $('#tabloSahada tbody').html(bosSatir(8, 'Yükleniyor...'));

        istek({
            action: 'sahada-takili',
            aparat_tip_id: $('#rapor_aparat').val() || 0,
            min_gun: $('#rapor_min_gun').val() || 0
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(8, 'Sahada takılı aparat bulunamadı.'));
                return;
            }

            res.data.forEach(function (s) {
                const gun = parseInt(s.gun_sayisi, 10);
                govde.append(`<tr>
                    <td>${tarihGoster(s.tarih)}</td>
                    <td class="text-center"><span class="badge bg-${gun > 90 ? 'danger' : (gun > 30 ? 'warning' : 'secondary')}-subtle text-${gun > 90 ? 'danger' : (gun > 30 ? 'warning' : 'secondary')}">${gun}</span></td>
                    <td>${kacir(s.abone_no || '-')}</td>
                    <td>${kacir(s.sayac_no || '-')}</td>
                    <td>${kacir([s.ilce, s.mahalle].filter(Boolean).join(' / ') || '-')}</td>
                    <td>${kacir(s.aparat_adi || '-')}</td>
                    <td class="text-center">${s.adet}</td>
                    <td>${kacir(s.ekip_adi || '-')}</td>
                </tr>`);
            });
        }).fail(() => hata('Rapor yüklenemedi.'));
    });

    $('#btnDonemselOzet').on('click', function () {
        const govde = $('#tabloDonemsel tbody').html(bosSatir(6, 'Yükleniyor...'));

        istek({
            action: 'donemsel-ozet',
            start_date: tarihGonder('#ozet_bas'),
            end_date: tarihGonder('#ozet_bit')
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(6, 'Kayıt bulunamadı.'));
                return;
            }

            res.data.forEach(function (o) {
                govde.append(`<tr>
                    <td>${o.islem_tipi === 'kesme' ? 'Kesme' : 'Açma'}</td>
                    <td>${kacir(o.aparat_adi || 'Aparatsız')}</td>
                    <td class="text-center">${o.kayit_sayisi}</td>
                    <td class="text-center fw-bold">${o.aparat_adedi}</td>
                    <td class="text-center">${o.hasarli}</td>
                    <td class="text-center">${o.kayip}</td>
                </tr>`);
            });
        }).fail(() => hata('Özet yüklenemedi.'));
    });

    $('#btnApiKarsilastir').on('click', function () {
        const govde = $('#tabloApiKarsilastirma tbody').html(bosSatir(5, 'Yükleniyor...'));

        istek({
            action: 'api-karsilastirma',
            start_date: tarihGonder('#ozet_bas'),
            end_date: tarihGonder('#ozet_bit')
        }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(5, 'Karşılaştırılacak kayıt bulunamadı.'));
                return;
            }

            res.data.forEach(function (k) {
                const fark = parseInt(k.fark, 10);
                govde.append(`<tr>
                    <td>${tarihGoster(k.tarih)}</td>
                    <td>${kacir(k.ekip_adi || '-')}</td>
                    <td class="text-center">${k.api_adet}</td>
                    <td class="text-center">${k.panel_adet}</td>
                    <td class="text-center fw-bold ${fark === 0 ? 'text-success' : 'text-danger'}">${fark > 0 ? '+' : ''}${fark}</td>
                </tr>`);
            });
        }).fail(() => hata('Karşılaştırma yüklenemedi.'));
    });

    // ---------- Tanımlar ----------

    function tipleriYukle() {
        const govde = $('#tabloTipler tbody');
        if (!govde.length) return;

        govde.html(bosSatir(7, 'Yükleniyor...'));

        istek({ action: 'tip-listesi' }).done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            govde.empty();

            if (!res.data.length) {
                govde.html(bosSatir(7, 'Aparat tipi tanımlanmamış.'));
                return;
            }

            res.data.forEach(function (t) {
                govde.append(`<tr>
                    <td class="text-center">${t.sira}</td>
                    <td><b>${kacir(t.ad)}</b></td>
                    <td><span class="badge bg-${kacir(t.renk)}-subtle text-${kacir(t.renk)}">${kacir(t.kod)}</span></td>
                    <td>${kacir(t.renk)}</td>
                    <td class="small text-muted">${kacir(t.aciklama || '')}</td>
                    <td>${t.is_active == 1 ? '<span class="badge bg-success-subtle text-success">Aktif</span>' : '<span class="badge bg-secondary-subtle text-secondary">Pasif</span>'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary btn-tip-duzenle" data-tip='${JSON.stringify(t).replace(/'/g, "&#39;")}'><i class="bx bx-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-tip-sil" data-id="${t.id}"><i class="bx bx-trash"></i></button>
                    </td>
                </tr>`);
            });
        }).fail(() => hata('Tipler yüklenemedi.'));
    }

    $('#btnTipEkle').on('click', function () {
        $('#formTip')[0].reset();
        $('#tip_id').val(0);
        $('#tipModalBaslik').text('Yeni Aparat Tipi');
        $('#tip_renk').val('primary').trigger('change');
        $('#tip_aktif').prop('checked', true);
        new bootstrap.Modal('#modalTip').show();
    });

    $(document).on('click', '.btn-tip-duzenle', function () {
        const t = $(this).data('tip');
        $('#tip_id').val(t.id);
        $('#tip_ad').val(t.ad);
        $('#tip_kod').val(t.kod);
        $('#tip_sira').val(t.sira);
        $('#tip_aciklama').val(t.aciklama || '');
        $('#tip_renk').val(t.renk).trigger('change');
        $('#tip_aktif').prop('checked', t.is_active == 1);
        $('#tipModalBaslik').text('Aparat Tipini Düzenle');
        new bootstrap.Modal('#modalTip').show();
    });

    $('#formTip').on('submit', function (e) {
        e.preventDefault();

        istek({
            action: 'tip-kaydet',
            id: $('#tip_id').val(),
            ad: $('#tip_ad').val(),
            kod: $('#tip_kod').val(),
            renk: $('#tip_renk').val(),
            sira: $('#tip_sira').val(),
            aciklama: $('#tip_aciklama').val(),
            is_active: $('#tip_aktif').is(':checked') ? 1 : 0
        }, 'POST').done(function (res) {
            if (res.status !== 'success') return hata(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modalTip')).hide();
            basari(res.message);
            tipleriYukle();
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

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const hedef = $(e.target).attr('href');

        if (hedef === '#pane-islemler' && !$('#tabloIslemler tbody tr').length) islemleriYukle();
        if (hedef === '#pane-hareketler' && !$('#tabloHareketler tbody tr').length) hareketleriYukle();
        if (hedef === '#pane-transferler' && !$('#tabloTransferler tbody tr').length) transferleriYukle();
        if (hedef === '#pane-sayim') sayimlariYukle();
        if (hedef === '#pane-tanimlar' && !$('#tabloTipler tbody tr').length) tipleriYukle();
    });

    $(function () {
        $('.select2').select2({ width: '100%' });
        $('#modalHavuz, #modalIslem, #modalTip, #modalSayim').each(function () {
            const modal = this;
            $(modal).find('.select2').select2({ width: '100%', dropdownParent: $(modal) });
        });
        stokYukle();
    });

})(jQuery);
