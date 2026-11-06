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
    const liste = (dizi) => dizi && dizi.length ? `<ul class="ik-liste">${dizi.map((m) => `<li>${esc(m)}</li>`).join('')}</ul>` : '<span class="text-muted">-</span>';

    const bilgi = (etiket, deger) => `<dt>${esc(etiket)}</dt><dd>${deger === null || deger === undefined || deger === '' ? '<span class="text-muted">-</span>' : esc(deger)}</dd>`;

    const ciz = () => {
        const d = veri;
        $('#dAd').text(d.ad_soyad);
        $('#dAlt').text(`${d.ilan_baslik} • Başvuru: ${(d.created_at || '').substring(0, 16)} • Durum: ${durumAd[d.durum] || d.durum}`);

        let ai = '';
        if (d.ai_durum === 'tamam') {
            ai = `<div class="d-flex gap-3 align-items-center mb-3">
                    <div class="ik-puan-halka ${esc(d.ai_etiket)}">${esc(d.ai_puan)}</div>
                    <div>
                        <div class="fw-bold">${esc(etiketAd[d.ai_etiket] || '')} aday önerisi</div>
                        <div class="text-muted font-size-12">${tarihSaat(d.ai_tarihi)} • Yalnızca öneridir, karar İK'ya aittir</div>
                        <p class="mb-0 mt-2">${esc(d.ai_ozet)}</p>
                    </div>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6"><div class="fw-semibold text-success mb-1"><i class="bx bx-plus-circle"></i> Güçlü yönler</div>${liste(d.ai_guclu)}</div>
                    <div class="col-md-6"><div class="fw-semibold text-danger mb-1"><i class="bx bx-minus-circle"></i> Zayıf yönler</div>${liste(d.ai_zayif)}</div>
                    <div class="col-md-6"><div class="fw-semibold text-warning mb-1"><i class="bx bx-error"></i> Riskler / eksik bilgi</div>${liste(d.ai_riskler)}</div>
                    <div class="col-md-6"><div class="fw-semibold text-primary mb-1"><i class="bx bx-help-circle"></i> Önerilen mülakat soruları</div>${liste(d.ai_mulakat_sorulari)}</div>
                  </div>`;
        } else if (d.ai_durum === 'hata') {
            ai = '<div class="alert alert-danger mb-2">Yapay zekâ değerlendirmesi tamamlanamadı.</div>';
        } else {
            ai = '<div class="alert alert-info mb-2">Değerlendirme henüz tamamlanmadı.</div>';
        }
        ai += '<div class="mt-3"><button type="button" class="btn btn-sm btn-outline-primary" id="btnAiYeniden"><i class="bx bx-refresh"></i> Yapay zekâyla yeniden değerlendir</button></div>';
        $('#aiAlani').html(ai);

        let b = '';
        b += bilgi('Telefon', d.telefon) + bilgi('E-posta', d.email) + bilgi('Şehir', d.sehir) + bilgi('Eğitim', d.egitim);
        b += bilgi('Deneyim (yıl)', d.deneyim_yili) + bilgi('Ehliyet', d.ehliyet);
        b += bilgi('Maaş beklentisi', d.maas_beklentisi ? Number(d.maas_beklentisi).toLocaleString('tr-TR') + ' ₺' : '');
        b += bilgi('İşe başlama', d.ise_baslama) + bilgi('Deneyim özeti', d.deneyim_ozet);
        Object.keys(d.cevaplar || {}).forEach((soru) => { b += bilgi(soru, d.cevaplar[soru]); });
        $('#adayBilgi').html(b);

        $('#cvAlani').html(d.cv_var
            ? `<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="${API}?action=basvuru-cv&id=${encodeURIComponent(d.id)}"><i class="bx bx-file"></i> CV'yi Görüntüle (PDF)</a>${d.cv_metin_var ? '' : '<div class="text-warning font-size-12 mt-1">CV metni okunamadı (taranmış olabilir).</div>'}`
            : '<span class="text-muted font-size-12">CV yüklenmedi.</span>');

        $('#ik_notu').val(d.ik_notu || '');
        $('#olaylar').html((d.olaylar || []).length
            ? d.olaylar.map((o) => `<div class="border-bottom py-2"><span class="fw-semibold">${esc(o.olay)}</span>${o.aciklama ? ' – ' + esc(o.aciklama) : ''}<div class="text-muted font-size-12">${tarihSaat(o.created_at)}${o.kullanici ? ' • ' + esc(o.kullanici) : ''}</div></div>`).join('')
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
        $.post(API, { action: 'basvuru-ai-yeniden', id: basvuruId }, function (res) {
            if (res.status === 'success') showToast(res.message, 'success'); else Swal.fire('Hata', res.message || 'Değerlendirme yapılamadı.', 'error');
        }, 'json').fail(() => Swal.fire('Hata', 'Değerlendirme yapılamadı.', 'error')).always(yukle);
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
