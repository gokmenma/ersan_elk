$(document).ready(function () {
    const API = 'views/ik-ise-alim/api.php';
    const basvuruId = new URLSearchParams(window.location.search).get('id') || '';
    let veri = null;

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const tarihSaat = (v) => {
        if (!v) return '-';
        const [t, s] = String(v).split(' ');
        return esc(t.split('-').reverse().join('.') + (s ? ' ' + s.substring(0, 5) : ''));
    };
    const durumAd = { yeni: 'Yeni', degerlendirildi: 'Değerlendirildi', gorusme: 'Görüşme', olumlu: 'Olumlu', olumsuz: 'Olumsuz', personele_donustu: 'Personele Dönüştü' };
    const etiketAd = { olumlu: 'Olumlu', belirsiz: 'Belirsiz', olumsuz: 'Olumsuz' };
    const durumRenk = { yeni: 'secondary', degerlendirildi: 'info', gorusme: 'warning', olumlu: 'success', olumsuz: 'danger', personele_donustu: 'primary' };
    const liste = (dizi) => dizi && dizi.length ? `<ul>${dizi.map((m) => `<li>${esc(m)}</li>`).join('')}</ul>` : '<span class="text-muted font-size-12">Belirtilmedi.</span>';
    const kutu = (sinif, ikon, baslik, dizi) => `<div class="col-md-6"><div class="ik-ai-kutu ${sinif}"><h6><i class="bx ${ikon}"></i> ${baslik}</h6>${liste(dizi)}</div></div>`;
    const bilgi = (etiket, deger, ikon, tam, ek) => `<div class="ik-bilgi-oge${tam ? ' ik-tam' : ''}"><div class="et">${ikon ? `<i class="bx ${ikon}"></i>` : ''}${esc(etiket)}</div><div class="dg">${deger === null || deger === undefined || deger === '' ? '<span class="text-muted">-</span>' : esc(deger)}</div>${ek || ''}</div>`;
    const telSade = (t) => String(t || '').replace(/\D/g, '');

    const alanAd = { egitim: 'Eğitim', deneyim_yili: 'Deneyim (yıl)', ehliyet: 'Ehliyet', maas_beklentisi: 'Maaş beklentisi', ise_baslama: 'İşe başlama', sehir: 'Şehir', deneyim_ozet: 'Deneyim özeti' };
    const sayiGoster = (v) => (v === null || v === undefined || v === '' ? '' : Number(v).toLocaleString('tr-TR'));
    const alanGoster = (alan, v) => (alan === 'maas_beklentisi' ? (sayiGoster(v) ? sayiGoster(v) + ' ₺' : '') : alan === 'deneyim_yili' ? sayiGoster(v) : v);
    const turAd = { telefon: 'Telefon', yuz_yuze: 'Yüz yüze', online: 'Online' };
    const sonucRenk = { olumlu: 'success', olumsuz: 'danger', beklemede: 'secondary' };
    const sonucAd = { olumlu: 'Olumlu', olumsuz: 'Olumsuz', beklemede: 'Beklemede' };

    const ciz = () => {
        const orj = veri;
        const d = Object.assign({}, orj, orj.guncel || {});
        const duzeltmeNotu = (alan) => (orj.guncel && Object.prototype.hasOwnProperty.call(orj.guncel, alan)
            ? `<div class="ik-degisti"><i class="bx bx-edit-alt"></i> Mülakatta güncellendi • başvuruda: ${esc(alanGoster(alan, orj[alan]) || '-')}</div>` : '');
        $('#dAd').text(d.ad_soyad);
        if (d.foto_var) {
            $('#dFoto').addClass('p-0').css({ width: '56px', height: '72px' }).html(`<img src="${API}?action=basvuru-foto&id=${encodeURIComponent(d.id)}" alt="Vesikalık fotoğraf" style="width:100%;height:100%;object-fit:cover" onerror="this.remove()">`);
        }
        $('#dAlt').text(`${d.ilan_baslik} • Başvuru: ${(d.created_at || '').substring(0, 16)}`);

        const renk = durumRenk[d.durum] || 'secondary';
        $('#durumRozet').html(`<span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${esc(durumAd[d.durum] || d.durum)}</span>`);
        $('.ik-durum').removeClass('aktif').filter(`[data-durum="${d.durum}"]`).addClass('aktif');

        const tel = telSade(d.telefon);
        let chip = '';
        if (d.telefon) chip += `<a class="ik-chip" href="tel:+${esc(tel)}"><i class="bx bx-phone"></i> ${esc(d.telefon)}</a><a class="ik-chip" target="_blank" rel="noopener" href="https://wa.me/${esc(tel)}"><i class="bx bxl-whatsapp text-success"></i> WhatsApp</a>`;
        if (d.email) chip += `<a class="ik-chip" href="mailto:${esc(d.email)}"><i class="bx bx-envelope"></i> ${esc(d.email)}</a>`;
        if (d.sehir) chip += `<span class="ik-chip"><i class="bx bx-map"></i> ${esc(d.sehir)}</span>`;
        if (d.ehliyet) chip += `<span class="ik-chip"><i class="bx bx-id-card"></i> Ehliyet: ${esc(d.ehliyet)}</span>`;
        if (d.deneyim_yili) chip += `<span class="ik-chip"><i class="bx bx-briefcase"></i> ${esc(d.deneyim_yili)} yıl deneyim</span>`;
        if (d.maas_beklentisi) chip += `<span class="ik-chip"><i class="bx bx-lira"></i> ${esc(Number(d.maas_beklentisi).toLocaleString('tr-TR'))} ₺</span>`;
        $('#dChipler').html(chip);

        let ai = '';
        if (d.ai_durum === 'tamam') {
            const puan = Math.max(0, Math.min(100, Number(d.ai_puan) || 0));
            ai = `<div class="d-flex gap-3 align-items-center mb-3 flex-wrap">
                    <div class="ik-puan-halka ${esc(d.ai_etiket)}" style="--p:${puan}"><div><span>${esc(puan)}</span><small>/ 100</small></div></div>
                    <div class="flex-grow-1" style="min-width:220px">
                        <div class="fw-bold font-size-15">${esc(etiketAd[d.ai_etiket] || '')} aday önerisi</div>
                        <div class="text-muted font-size-12 mb-2">${tarihSaat(d.ai_tarihi)} • Yalnızca öneridir, karar İK'ya aittir</div>
                        <div class="ik-ozet">${esc(d.ai_ozet)}</div>
                    </div>
                  </div>
                  <div class="row g-3">
                    ${kutu('guclu', 'bx-plus-circle', 'Güçlü yönler', d.ai_guclu)}
                    ${kutu('zayif', 'bx-minus-circle', 'Zayıf yönler', d.ai_zayif)}
                    ${kutu('risk', 'bx-error', 'Riskler / eksik bilgi', d.ai_riskler)}
                    ${kutu('soru', 'bx-help-circle', 'Önerilen mülakat soruları', d.ai_mulakat_sorulari)}
                  </div>`;
        } else if (d.ai_durum === 'hata') {
            ai = '<div class="alert alert-danger mb-0">Yapay zekâ değerlendirmesi tamamlanamadı.</div>';
        } else {
            ai = '<div class="alert alert-info mb-0">Değerlendirme henüz tamamlanmadı.</div>';
        }
        $('#aiAlani').html(ai);

        let b = '';
        b += bilgi('Eğitim', d.egitim, 'bx-book-reader', false, duzeltmeNotu('egitim')) + bilgi('Deneyim (yıl)', d.deneyim_yili, 'bx-briefcase', false, duzeltmeNotu('deneyim_yili'));
        b += bilgi('Ehliyet', d.ehliyet, 'bx-id-card', false, duzeltmeNotu('ehliyet')) + bilgi('İşe başlama', d.ise_baslama, 'bx-calendar-check', false, duzeltmeNotu('ise_baslama'));
        b += bilgi('Maaş beklentisi', alanGoster('maas_beklentisi', d.maas_beklentisi), 'bx-lira', true, duzeltmeNotu('maas_beklentisi'));
        b += bilgi('Deneyim özeti', d.deneyim_ozet, 'bx-notepad', true, duzeltmeNotu('deneyim_ozet'));
        Object.keys(d.cevaplar || {}).forEach((soru) => { b += bilgi(soru, d.cevaplar[soru], 'bx-message-square-detail', true); });
        $('#adayBilgi').html(b);

        $('#cvAlani').html(d.cv_var
            ? `<a class="btn btn-outline-secondary w-100" target="_blank" rel="noopener" href="${API}?action=basvuru-cv&id=${encodeURIComponent(d.id)}"><i class="bx bx-file"></i> CV'yi Görüntüle (PDF)</a>${d.cv_metin_var ? '' : '<div class="text-warning font-size-12 mt-1"><i class="bx bx-error"></i> CV metni okunamadı (taranmış olabilir).</div>'}`
            : '<span class="text-muted font-size-12">CV yüklenmedi.</span>');

        $('#ik_notu').val(d.ik_notu || '');
        $('#olaylar').html((d.olaylar || []).length
            ? `<div class="ik-zaman">${d.olaylar.map((o) => `<div class="ik-zaman-oge"><div><span class="fw-semibold">${esc(o.olay)}</span>${o.aciklama ? ' – ' + esc(o.aciklama) : ''}</div><div class="text-muted font-size-12">${tarihSaat(o.created_at)}${o.kullanici ? ' • ' + esc(o.kullanici) : ''}</div></div>`).join('')}</div>`
            : '<span class="text-muted">Kayıt yok.</span>');

        if (orj.mulakat_yetki) {
            const ml = orj.mulakatlar || [];
            $('#mulakatSayisi').text(ml.length);
            $('#mulakatListe').html(ml.length ? ml.map((m, i) => {
                const r = sonucRenk[m.sonuc] || 'secondary';
                const qa = (m.sorular || []).map((q) => `<div class="ik-qa"><div class="q">${esc(q.soru)}${q.puan ? ` <span class="badge bg-warning-subtle text-warning rounded-pill ms-1">${esc(q.puan)}/5</span>` : ''}</div><div class="a">${q.cevap ? esc(q.cevap) : '<span class="text-muted">Cevap notu girilmedi.</span>'}</div></div>`).join('');
                return `<div class="ik-mulakat"><div class="ik-mulakat-baslik" data-bs-toggle="collapse" data-bs-target="#mlg${i}">
                    <i class="bx bx-chevron-down"></i><span class="fw-semibold">${tarihSaat(m.mulakat_tarihi)}</span>
                    <span class="badge bg-light text-muted border rounded-pill">${esc(turAd[m.tur] || m.tur)}</span>
                    <span class="badge bg-${r}-subtle text-${r} border border-${r}-subtle rounded-pill">${esc(sonucAd[m.sonuc] || m.sonuc)}</span>
                    ${m.genel_puan ? `<span class="badge bg-warning-subtle text-warning rounded-pill"><i class="bx bxs-star"></i> ${esc(m.genel_puan)}/5</span>` : ''}
                    <span class="text-muted font-size-12 ms-auto">${m.yapan ? esc(m.yapan) : ''}</span></div>
                    <div class="collapse${i === 0 ? ' show' : ''}" id="mlg${i}"><div class="ik-mulakat-govde">
                        ${m.genel_not ? `<div class="mt-2" style="white-space:pre-line">${esc(m.genel_not)}</div>` : ''}${qa}
                        ${d.durum === 'personele_donustu' ? '' : `<div class="mt-3 d-flex gap-2"><button type="button" class="btn btn-sm btn-subtle-warning btn-mulakat-duzenle" data-i="${i}"><i class="bx bx-edit"></i> Düzenle</button><button type="button" class="btn btn-sm btn-subtle-danger btn-mulakat-sil" data-i="${i}"><i class="bx bx-trash"></i> Sil</button></div>`}
                    </div></div></div>`;
            }).join('') : '<span class="text-muted">Henüz mülakat kaydı yok.</span>');
            $('#btnMulakatEkle').toggleClass('d-none', d.durum === 'personele_donustu');
        }

        const donustu = d.durum === 'personele_donustu';
        $('#durumButonlari').toggleClass('d-none', donustu);
        $('#btnPersoneleDonustur').toggleClass('d-none', donustu);
        $('#btnPersonelGit').toggleClass('d-none', !donustu || !d.personel_id);
        if (d.personel_id) $('#btnPersonelGit').attr('href', 'index?p=personel/manage&id=' + encodeURIComponent(d.personel_id));
    };

    const yukle = () => {
        $.post(API, { action: 'basvuru-getir', id: basvuruId }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Başvuru alınamadı.', 'error'); return; }
            veri = res.data;
            ciz();
        }, 'json').fail(() => Swal.fire('Hata', 'Başvuru alınamadı.', 'error'));
    };

    const soruSatiri = (soru, cevap, puan) => `<div class="ik-soru-satir">
        <input type="text" class="form-control form-control-sm fw-semibold mb-2 ml-soru" maxlength="300" placeholder="Soru" value="${esc(soru)}">
        <div class="row g-2">
            <div class="col-md-9"><textarea class="form-control form-control-sm ml-cevap" rows="2" maxlength="2000" placeholder="Adayın cevabı / gözleminiz">${esc(cevap)}</textarea></div>
            <div class="col-md-3 d-flex gap-2"><select class="form-select form-select-sm ml-puan"><option value="">Puan</option>${[1, 2, 3, 4, 5].map((n) => `<option value="${n}"${Number(puan) === n ? ' selected' : ''}>${n}</option>`).join('')}</select>
            <button type="button" class="btn btn-sm btn-subtle-danger ml-soru-sil" title="Soruyu kaldır"><i class="bx bx-x"></i></button></div>
        </div></div>`;

    const bilgiAlanlari = () => {
        const e = Object.assign({}, veri, veri.guncel || {});
        const kutu = (alan, tip, tam) => `<div class="${tam ? 'col-12' : 'col-md-6'}"><label class="form-label font-size-12 text-muted mb-0">${esc(alanAd[alan])} <span class="text-muted">(başvuruda: ${esc(alanGoster(alan, veri[alan]) || '-')})</span></label>
            ${tip === 'textarea' ? `<textarea class="form-control form-control-sm ml-bilgi" data-alan="${alan}" rows="2" maxlength="2000">${esc(e[alan] || '')}</textarea>` : `<input type="${tip}" ${tip === 'number' ? 'step="0.5" min="0"' : ''} class="form-control form-control-sm ml-bilgi" data-alan="${alan}" maxlength="150" value="${esc(e[alan] || '')}">`}</div>`;
        $('#mulakatBilgi').html(kutu('egitim', 'text') + kutu('deneyim_yili', 'number') + kutu('ehliyet', 'text') + kutu('maas_beklentisi', 'number') + kutu('ise_baslama', 'text') + kutu('sehir', 'text') + kutu('deneyim_ozet', 'textarea', true));
    };

    const simdiYerel = () => { const t = new Date(); t.setMinutes(t.getMinutes() - t.getTimezoneOffset()); return t.toISOString().substring(0, 16); };

    const mulakatAc = (m) => {
        $('#mulakat_id').val(m ? m.id : '');
        $('#mulakat_tarihi').val(m ? String(m.mulakat_tarihi).substring(0, 16).replace(' ', 'T') : simdiYerel());
        $('#mulakat_tur').val(m ? m.tur : 'yuz_yuze').trigger('change.select2');
        $('#mulakat_sonuc').val(m ? m.sonuc : 'beklemede').trigger('change.select2');
        $('#mulakat_puan').val(m && m.genel_puan ? String(m.genel_puan) : '').trigger('change.select2');
        $('#mulakat_not').val(m ? (m.genel_not || '') : '');
        const sorular = m ? (m.sorular || []) : (veri.ai_mulakat_sorulari || []).map((q) => ({ soru: q, cevap: '', puan: '' }));
        $('#mulakatSorular').html(sorular.map((q) => soruSatiri(q.soru, q.cevap, q.puan)).join(''));
        bilgiAlanlari();
        $('#mulakatModal').modal('show');
    };

    if (window.IK_MULAKAT_YETKI) {
        $('#mulakatModal .select2').select2({ dropdownParent: $('#mulakatModal'), width: '100%', minimumResultsForSearch: Infinity });

        $('#btnMulakatEkle').on('click', () => mulakatAc(null));
        $(document).on('click', '.btn-mulakat-duzenle', function () { mulakatAc(veri.mulakatlar[$(this).data('i')]); });
        $('#btnSoruEkle').on('click', () => $('#mulakatSorular').append(soruSatiri('', '', '')));
        $(document).on('click', '.ml-soru-sil', function () { $(this).closest('.ik-soru-satir').remove(); });

        $(document).on('click', '.btn-mulakat-sil', function () {
            const m = veri.mulakatlar[$(this).data('i')];
            Swal.fire({ title: 'Mülakat kaydı silinsin mi?', text: 'Kayıt listeden kaldırılır; işlem geçmişte izlenir.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç' }).then((r) => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'mulakat-sil', mulakat_id: m.id }, function (res) {
                    if (res.status === 'success') { showToast(res.message, 'success'); yukle(); }
                    else Swal.fire('Hata', res.message || 'Silinemedi.', 'error');
                }, 'json').fail(() => Swal.fire('Hata', 'Silinemedi.', 'error'));
            });
        });

        $('#mulakatForm').on('submit', function (e) {
            e.preventDefault();
            const sorular = $('#mulakatSorular .ik-soru-satir').map(function () {
                return { soru: $(this).find('.ml-soru').val(), cevap: $(this).find('.ml-cevap').val(), puan: $(this).find('.ml-puan').val() };
            }).get();
            const bilgiVeri = {};
            $('.ml-bilgi').each(function () { bilgiVeri[$(this).data('alan')] = $(this).val(); });
            const btn = $('#btnMulakatKaydet').prop('disabled', true);
            $.post(API, {
                action: 'mulakat-kaydet', id: basvuruId, mulakat_id: $('#mulakat_id').val(),
                mulakat_tarihi: $('#mulakat_tarihi').val(), tur: $('#mulakat_tur').val(), sonuc: $('#mulakat_sonuc').val(),
                genel_puan: $('#mulakat_puan').val(), genel_not: $('#mulakat_not').val(),
                sorular: JSON.stringify(sorular), bilgi: JSON.stringify(bilgiVeri)
            }, function (res) {
                if (res.status === 'success') { $('#mulakatModal').modal('hide'); showToast(res.message, 'success'); yukle(); }
                else Swal.fire('Hata', res.message || 'Mülakat kaydedilemedi.', 'error');
            }, 'json').fail(() => Swal.fire('Hata', 'Mülakat kaydedilemedi.', 'error')).always(() => btn.prop('disabled', false));
        });
    }

    if (window.IK_BILDIRIM_YETKI) {
        $('#bildirimModal .select2').select2({ dropdownParent: $('#bildirimModal'), width: '100%', minimumResultsForSearch: Infinity });

        const waNo = (t) => {
            let n = String(t || '').replace(/\D/g, '');
            if (n.startsWith('00')) n = n.substring(2);
            if (n.startsWith('0')) n = '90' + n.substring(1);
            else if (n.length === 10) n = '90' + n;
            return n;
        };

        const sablonYukle = (sablon) => {
            $.post(API, { action: 'aday-bildirim-hazirla', id: basvuruId, sablon: sablon }, function (res) {
                if (res.status === 'success') { $('#bildirim_konu').val(res.konu); $('#bildirim_mesaj').val(res.mesaj); }
                else Swal.fire('Hata', res.message || 'Şablon alınamadı.', 'error');
            }, 'json').fail(() => Swal.fire('Hata', 'Şablon alınamadı.', 'error'));
        };

        const bildirimAc = (sablon) => {
            $('#bildirim_sablon').val(sablon).trigger('change.select2');
            const eposta = veri.email, tel = waNo(veri.telefon);
            $('#btnBildirimEposta').prop('disabled', !eposta);
            $('#btnBildirimWhatsapp').prop('disabled', !tel);
            $('#bildirimAlici').html(`<span><i class="bx bx-envelope"></i> ${eposta ? esc(eposta) : 'E-posta yok'}</span><span><i class="bx bx-phone"></i> ${tel ? esc(veri.telefon) : 'Telefon yok'}</span>`);
            sablonYukle(sablon);
            $('#bildirimModal').modal('show');
        };

        $('#btnAdayaBildir').on('click', () => {
            const varsayilan = { gorusme: 'gorusme', olumlu: 'olumlu', olumsuz: 'olumsuz' }[veri.durum] || 'serbest';
            bildirimAc(varsayilan);
        });
        $('#bildirim_sablon').on('change', function () { if ($('#bildirimModal').hasClass('show')) sablonYukle($(this).val()); });

        const gonder = (kanal) => {
            const mesaj = $('#bildirim_mesaj').val(), konu = $('#bildirim_konu').val();
            const gonderIslem = () => {
                const waPencere = kanal === 'whatsapp' ? window.open('about:blank', '_blank') : null;
                $('#btnBildirimEposta, #btnBildirimWhatsapp').prop('disabled', true);
                $.post(API, { action: 'aday-bildirim-gonder', id: basvuruId, kanal: kanal, sablon: $('#bildirim_sablon').val(), konu: konu, mesaj: mesaj }, function (res) {
                    if (res.status === 'success') {
                        if (waPencere) waPencere.location.href = 'https://wa.me/' + waNo(veri.telefon) + '?text=' + encodeURIComponent(mesaj);
                        $('#bildirimModal').modal('hide');
                        showToast(res.message, 'success');
                        yukle();
                    } else {
                        if (waPencere) waPencere.close();
                        Swal.fire('Hata', res.message || 'Bildirim gönderilemedi.', 'error');
                    }
                }, 'json').fail(() => { if (waPencere) waPencere.close(); Swal.fire('Hata', 'Bildirim gönderilemedi.', 'error'); })
                    .always(() => { $('#btnBildirimEposta').prop('disabled', !veri.email); $('#btnBildirimWhatsapp').prop('disabled', !waNo(veri.telefon)); });
            };
            if (/\[[^\]]+\]/.test(mesaj)) {
                Swal.fire({ title: 'Doldurulmamış alan var', text: 'Mesajda köşeli parantezli bir alan kaldı (örn. [tarih ve saat]). Yine de gönderilsin mi?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yine de gönder', cancelButtonText: 'Düzenle' }).then((r) => { if (r.isConfirmed) gonderIslem(); });
            } else gonderIslem();
        };
        $('#btnBildirimEposta').on('click', () => gonder('eposta'));
        $('#btnBildirimWhatsapp').on('click', () => gonder('whatsapp'));

        window.ikBildirimAc = bildirimAc;
    }

    yukle();

    $(document).on('click', '#btnAiYeniden', function () {
        const btn = $(this).prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> Değerlendiriliyor...');
        $('#aiAlani').css('opacity', .5);
        $.post(API, { action: 'basvuru-ai-yeniden', id: basvuruId }, function (res) {
            if (res.status === 'success') showToast(res.message, 'success'); else Swal.fire('Hata', res.message || 'Değerlendirme yapılamadı.', 'error');
        }, 'json').fail(() => Swal.fire('Hata', 'Değerlendirme yapılamadı.', 'error')).always(() => { btn.prop('disabled', false).html('<i class="bx bx-refresh"></i> Yeniden değerlendir'); $('#aiAlani').css('opacity', 1); yukle(); });
    });

    $('.ik-durum').on('click', function () {
        const btnDurum = $(this).data('durum');
        $.post(API, { action: 'basvuru-durum', id: basvuruId, durum: btnDurum }, function (res) {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                const durum = btnDurum;
                yukle();
                if (res.degisti && window.IK_BILDIRIM_YETKI && window.ikBildirimAc && ['gorusme', 'olumlu', 'olumsuz'].includes(durum)) setTimeout(() => window.ikBildirimAc(durum), 400);
            } else Swal.fire('Hata', res.message || 'Durum güncellenemedi.', 'error');
        }, 'json');
    });

    $('#btnNotKaydet').on('click', function () {
        $.post(API, { action: 'basvuru-not', id: basvuruId, ik_notu: $('#ik_notu').val() }, function (res) {
            if (res.status === 'success') { showToast(res.message, 'success'); yukle(); }
            else Swal.fire('Hata', res.message || 'Not kaydedilemedi.', 'error');
        }, 'json');
    });

    $('#btnPersoneleDonustur').on('click', function () { $('#donusturModal').modal('show'); });

    $('#donusturForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#btnDonusturKaydet').prop('disabled', true);
        $.post(API, { action: 'personele-donustur', id: basvuruId, tc_kimlik_no: $('#tc_kimlik_no').val(), ise_giris_tarihi: $('#ise_giris_tarihi').val() }, function (res) {
            if (res.status === 'success') {
                $('#donusturModal').modal('hide');
                Swal.fire({ title: 'Personel oluşturuldu', text: 'Personel kartına yönlendiriliyorsunuz. Karşılama bağlantısını oradan gönderebilirsiniz.', icon: 'success', confirmButtonText: 'Personel Kartı' })
                    .then(() => { window.location.href = 'index?p=personel/manage&id=' + encodeURIComponent(res.personel_id); });
            } else {
                Swal.fire('Hata', res.message || 'Personel oluşturulamadı.', 'error');
            }
        }, 'json').fail(() => Swal.fire('Hata', 'Personel oluşturulamadı.', 'error')).always(() => btn.prop('disabled', false));
    });
});
