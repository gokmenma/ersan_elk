$(document).ready(function () {
    const API = 'views/ik-ise-alim/api.php';
    const grafikler = {};

    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    const tarih = (v) => esc((v || '').substring(0, 10).split('-').reverse().join('.'));

    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const gizli = $('html').hasClass('ikdash-summary-hidden');
        toggleBtn.attr('aria-expanded', !gizli);
        toggleBtn.find('i').attr('class', gizli ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    updateToggleState();
    toggleBtn.on('click', function () {
        const gizle = !$('html').hasClass('ikdash-summary-hidden');
        $('html').toggleClass('ikdash-summary-hidden', gizle);
        try { localStorage.setItem('ikdash_summary_cards_state', gizle ? 'hidden' : 'visible'); } catch (e) {}
        updateToggleState();
    });

    const grafikCiz = (anahtar, secici, secenekler) => {
        if (grafikler[anahtar]) grafikler[anahtar].destroy();
        grafikler[anahtar] = new ApexCharts(document.querySelector(secici), secenekler);
        grafikler[anahtar].render();
    };

    const gunlukSeri = (satirlar, gun) => {
        const harita = {};
        satirlar.forEach((s) => { harita[s.tarih] = parseInt(s.adet, 10); });
        const etiketler = [];
        const degerler = [];
        const bugun = new Date();
        for (let i = gun - 1; i >= 0; i--) {
            const d = new Date(bugun.getFullYear(), bugun.getMonth(), bugun.getDate() - i);
            const anahtar = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            etiketler.push(`${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}`);
            degerler.push(harita[anahtar] || 0);
        }
        return { etiketler, degerler };
    };

    const ciz = (r) => {
        const s = r.summary;
        const aiToplam = (s.ai_olumlu || 0) + (s.ai_belirsiz || 0) + (s.ai_olumsuz || 0);
        $('#k_toplam').text(s.toplam);
        $('#k_toplam_alt').text(`Son 7 gün: ${s.son_7_gun}`);
        $('#k_bekleyen').text(s.bekleyen);
        $('#k_bekleyen_alt').text(`AI hata/bekleyen: ${(s.ai_hata || 0) + (s.ai_bekleyen || 0)}`);
        $('#k_olumlu').text(s.ai_olumlu);
        $('#k_olumlu_alt').text(`Ortalama puan: ${s.ortalama_puan ? s.ortalama_puan : '-'}`);
        $('#k_donusen').text(s.donusen);
        $('#k_donusen_alt').text(`Dönüşüm oranı: %${s.toplam ? ((s.donusen / s.toplam) * 100).toFixed(1).replace('.', ',') : 0}`);

        const g = gunlukSeri(r.gunluk, r.gun);
        grafikCiz('gunluk', '#chartGunluk', {
            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
            series: [{ name: 'Başvuru', data: g.degerler }],
            xaxis: { categories: g.etiketler, tickAmount: Math.min(12, g.etiketler.length), labels: { rotate: 0 } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#556ee6'],
            dataLabels: { enabled: false },
            fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
            noData: { text: 'Veri yok' }
        });

        grafikCiz('etiket', '#chartEtiket', {
            chart: { type: 'donut', height: 280, fontFamily: 'inherit', animations: { enabled: false } },
            series: aiToplam ? [s.ai_olumlu, s.ai_belirsiz, s.ai_olumsuz] : [],
            labels: ['Olumlu', 'Belirsiz', 'Olumsuz'],
            colors: ['#34c38f', '#f1b44c', '#f46a6a'],
            legend: { position: 'bottom' },
            noData: { text: 'Henüz değerlendirme yok' }
        });

        grafikCiz('ilan', '#chartIlan', {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
            series: [
                { name: 'Başvuru', data: r.ilanlar.map((i) => parseInt(i.adet, 10)) },
                { name: 'AI olumlu', data: r.ilanlar.map((i) => parseInt(i.olumlu || 0, 10)) }
            ],
            xaxis: { categories: r.ilanlar.map((i) => (i.baslik.length > 22 ? i.baslik.substring(0, 21) + '…' : i.baslik)), labels: { rotate: -30, trim: true } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
            colors: ['#556ee6', '#34c38f'],
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            noData: { text: 'İlan yok' }
        });

        const araliklar = Array.from({ length: 10 }, (_, i) => i * 10);
        const puanHarita = {};
        r.puanlar.forEach((p) => { puanHarita[Math.min(90, parseInt(p.aralik, 10))] = (puanHarita[Math.min(90, parseInt(p.aralik, 10))] || 0) + parseInt(p.adet, 10); });
        grafikCiz('puan', '#chartPuan', {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
            series: [{ name: 'Aday', data: araliklar.map((a) => puanHarita[a] || 0) }],
            xaxis: { categories: araliklar.map((a) => (a === 90 ? '90-100' : `${a}-${a + 9}`)) },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
            colors: ['#50a5f1'],
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } }
        });

        const detay = (id) => `index.php?p=ik-ise-alim/basvuru-detay&id=${encodeURIComponent(id)}`;
        $('#listeEnIyi').html(r.en_iyiler.length
            ? r.en_iyiler.map((a) => `<a href="${detay(a.enc_id)}" class="satir"><div><div class="fw-semibold">${esc(a.ad_soyad)}</div><div class="text-muted font-size-12">${esc(a.ilan_baslik)} • ${tarih(a.created_at)}</div></div><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-12 fw-bold">${esc(a.ai_puan)}</span></a>`).join('')
            : '<span class="text-muted">Bekleyen olumlu aday yok.</span>');
        $('#listeSorunlu').html(r.ai_sorunlu.length
            ? r.ai_sorunlu.map((a) => `<a href="${detay(a.enc_id)}" class="satir"><div><div class="fw-semibold">${esc(a.ad_soyad)}</div><div class="text-muted font-size-12">${esc(a.ilan_baslik)} • ${tarih(a.created_at)}</div></div><span class="badge bg-${a.ai_durum === 'hata' ? 'danger' : 'secondary'}-subtle text-${a.ai_durum === 'hata' ? 'danger' : 'secondary'} border rounded-pill px-2 py-1 font-size-11">${a.ai_durum === 'hata' ? 'AI hata' : 'Bekliyor'}</span></a>`).join('')
            : '<span class="text-muted">Sorun yok.</span>');
    };

    const yukle = () => {
        if (window.ikDashIlk && $('#dashGun').val() === '30') {
            const ilk = window.ikDashIlk;
            window.ikDashIlk = null;
            ilk.then((res) => {
                if (res && res.status === 'success') ciz(res); else yukleAjax();
            });
            return;
        }
        yukleAjax();
    };

    const yukleAjax = () => {
        $.post(API, { action: 'dashboard-data', gun: $('#dashGun').val() }, function (res) {
            if (res.status === 'success') ciz(res);
            else Swal.fire('Hata', res.message || 'Veriler alınamadı.', 'error');
        }, 'json').fail(() => Swal.fire('Hata', 'Veriler alınamadı.', 'error'));
    };
    $('#dashGun').on('change', yukle);
    yukle();
});
