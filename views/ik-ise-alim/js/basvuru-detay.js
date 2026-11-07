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
    const bilgi = (etiket, deger, ikon, tam) => `<div class="ik-bilgi-oge${tam ? ' ik-tam' : ''}"><div class="et">${ikon ? `<i class="bx ${ikon}"></i>` : ''}${esc(etiket)}</div><div class="dg">${deger === null || deger === undefined || deger === '' ? '<span class="text-muted">-</span>' : esc(deger)}</div></div>`;
    const telSade = (t) => String(t || '').replace(/\D/g, '');

    const ciz = () => {
        const d = veri;
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
        b += bilgi('Eğitim', d.egitim, 'bx-book-reader') + bilgi('Deneyim (yıl)', d.deneyim_yili, 'bx-briefcase');
        b += bilgi('Ehliyet', d.ehliyet, 'bx-id-card') + bilgi('İşe başlama', d.ise_baslama, 'bx-calendar-check');
        b += bilgi('Maaş beklentisi', d.maas_beklentisi ? Number(d.maas_beklentisi).toLocaleString('tr-TR') + ' ₺' : '', 'bx-lira', true);
        b += bilgi('Deneyim özeti', d.deneyim_ozet, 'bx-notepad', true);
        Object.keys(d.cevaplar || {}).forEach((soru) => { b += bilgi(soru, d.cevaplar[soru], 'bx-message-square-detail', true); });
        $('#adayBilgi').html(b);

        $('#cvAlani').html(d.cv_var
            ? `<a class="btn btn-outline-secondary w-100" target="_blank" rel="noopener" href="${API}?action=basvuru-cv&id=${encodeURIComponent(d.id)}"><i class="bx bx-file"></i> CV'yi Görüntüle (PDF)</a>${d.cv_metin_var ? '' : '<div class="text-warning font-size-12 mt-1"><i class="bx bx-error"></i> CV metni okunamadı (taranmış olabilir).</div>'}`
            : '<span class="text-muted font-size-12">CV yüklenmedi.</span>');

        $('#ik_notu').val(d.ik_notu || '');
        $('#olaylar').html((d.olaylar || []).length
            ? `<div class="ik-zaman">${d.olaylar.map((o) => `<div class="ik-zaman-oge"><div><span class="fw-semibold">${esc(o.olay)}</span>${o.aciklama ? ' – ' + esc(o.aciklama) : ''}</div><div class="text-muted font-size-12">${tarihSaat(o.created_at)}${o.kullanici ? ' • ' + esc(o.kullanici) : ''}</div></div>`).join('')}</div>`
            : '<span class="text-muted">Kayıt yok.</span>');

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
    yukle();

    $(document).on('click', '#btnAiYeniden', function () {
        const btn = $(this).prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> Değerlendiriliyor...');
        $('#aiAlani').css('opacity', .5);
        $.post(API, { action: 'basvuru-ai-yeniden', id: basvuruId }, function (res) {
            if (res.status === 'success') showToast(res.message, 'success'); else Swal.fire('Hata', res.message || 'Değerlendirme yapılamadı.', 'error');
        }, 'json').fail(() => Swal.fire('Hata', 'Değerlendirme yapılamadı.', 'error')).always(() => { btn.prop('disabled', false).html('<i class="bx bx-refresh"></i> Yeniden değerlendir'); $('#aiAlani').css('opacity', 1); yukle(); });
    });

    $('.ik-durum').on('click', function () {
        $.post(API, { action: 'basvuru-durum', id: basvuruId, durum: $(this).data('durum') }, function (res) {
            if (res.status === 'success') { showToast(res.message, 'success'); yukle(); }
            else Swal.fire('Hata', res.message || 'Durum güncellenemedi.', 'error');
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
