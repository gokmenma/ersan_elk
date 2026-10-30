<?php pwaPageHeader(['eyebrow' => 'KURUM', 'title' => 'Tüm Etkinlikler', 'subtitle' => 'Geçmiş ve yaklaşan etkinlik/duyurular', 'back' => '?page=ana-sayfa']); ?>

<div class="px-4">
    <div class="pwa-filter-tabs" role="tablist" aria-label="Etkinlik filtresi">
        <button type="button" role="tab" class="pwa-filter-pill is-active" data-etkinlik-sekme="aktif">Aktif <span data-etkinlik-sayi="aktif">0</span></button>
        <button type="button" role="tab" class="pwa-filter-pill" data-etkinlik-sekme="tumu">Tümü <span data-etkinlik-sayi="tumu">0</span></button>
        <button type="button" role="tab" class="pwa-filter-pill" data-etkinlik-sekme="gecmis">Geçmiş <span data-etkinlik-sayi="gecmis">0</span></button>
    </div>
</div>

<div class="px-4 mt-4">
    <div id="etkinlikler-loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div id="etkinlikler-container" class="grid gap-4 hidden pb-10">
        <!-- Events will be populated here via JS -->
    </div>
</div>

<script>
    var etkinlikVerisi = [];
    var etkinlikSekme = 'aktif';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-etkinlik-sekme]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                etkinlikSekme = btn.getAttribute('data-etkinlik-sekme');
                renderEtkinlikler();
            });
        });
        loadAllEtkinlikler();
    });

    function renderEtkinlikler() {
        var container = document.getElementById('etkinlikler-container');
        var sayilar = {
            aktif: etkinlikVerisi.filter(function (d) { return !d.gecmis; }).length,
            gecmis: etkinlikVerisi.filter(function (d) { return d.gecmis; }).length,
            tumu: etkinlikVerisi.length
        };
        document.querySelectorAll('[data-etkinlik-sekme]').forEach(function (btn) {
            var aktif = btn.getAttribute('data-etkinlik-sekme') === etkinlikSekme;
            btn.classList.toggle('is-active', aktif);
            btn.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });
        document.querySelectorAll('[data-etkinlik-sayi]').forEach(function (el) {
            el.textContent = sayilar[el.getAttribute('data-etkinlik-sayi')];
        });

        var liste = etkinlikVerisi.filter(function (d) {
            if (etkinlikSekme === 'aktif') return !d.gecmis;
            if (etkinlikSekme === 'gecmis') return !!d.gecmis;
            return true;
        });

        if (!liste.length) {
            container.innerHTML = '<div class="flex flex-col items-center justify-center py-10 bg-white dark:bg-card-dark rounded-2xl shadow-sm">' +
                '<span class="material-symbols-outlined text-4xl text-slate-300 mb-2">event_busy</span>' +
                '<p class="text-sm font-medium text-slate-500">Bu filtrede etkinlik bulunmuyor.</p>' +
                '</div>';
            return;
        }

        container.innerHTML = liste.map(etkinlikKartiHtml).join('');
    }

    function etkinlikKartiHtml(duyuru) {
        var duyuruJson = JSON.stringify(duyuru).replace(/\\/g, "\\\\").replace(/"/g, "&quot;").replace(/'/g, "\\'");
        var onClick = "showEtkinlikFullScreen('" + duyuruJson + "');";
        var cursorClass = 'cursor-pointer';

        var opacityClass = duyuru.gecmis ? 'opacity-60 saturate-50' : '';

        var bgImg = 'background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);';

        var kalan_gun_html = '';
        if (duyuru.kalan_gun !== null && duyuru.kalan_gun !== undefined && !duyuru.gecmis) {
            kalan_gun_html = '<div class="absolute -top-6 -right-2 pointer-events-none select-none z-0 flex flex-col items-end opacity-80">' +
                '<span class="text-[9rem] font-black leading-[0.8] tracking-tighter bg-gradient-to-bl from-white/70 to-white/0 text-transparent bg-clip-text">' + escapeHtml(duyuru.kalan_gun) + '</span>' +
                '<span class="text-[10px] font-bold text-white/40 uppercase tracking-[0.2em] relative -top-6 pr-6">GÜN KALDI</span>' +
                '</div>';
        }

        return '<div class="rounded-2xl p-4 text-white shadow-lg relative overflow-hidden transition-transform active:scale-[0.98] ' + cursorClass + ' ' + opacityClass + '" ' +
            'style="' + bgImg + '" onclick="' + onClick + '">' +
            kalan_gun_html +
            '<div class="relative z-10 pr-2">' +
            '<span class="badge badge-primary bg-white/20 text-white border-none mb-2 text-[10px]">' + escapeHtml(duyuru.tarih) + '</span>' +
            (duyuru.gecmis ? '<span class="badge badge-danger bg-red-500/80 text-white border-none mb-2 ml-2 text-[10px]">Geçmiş Etkinlik</span>' : '') +
            '<h3 class="font-bold text-lg leading-tight mb-2 text-white max-w-[85%]">' + escapeHtml(duyuru.baslik) + '</h3>' +
            '<p class="text-xs text-white/80 line-clamp-3 leading-relaxed max-w-[85%]">' + escapeHtml(duyuru.icerik ? duyuru.icerik.replace(/<[^>]*>?/gm, '') : '') + '</p>' +
            '</div>' +
            '</div>';
    }

    async function loadAllEtkinlikler() {
        var container = document.getElementById('etkinlikler-container');
        var loading = document.getElementById('etkinlikler-loading');

        try {
            var response = await API.request('getAllEtkinlikler');
            loading.style.display = 'none';
            container.classList.remove('hidden');

            if (response.success && response.data && response.data.length > 0) {
                etkinlikVerisi = response.data;
                renderEtkinlikler();
            } else {
                container.innerHTML = '<div class="flex flex-col items-center justify-center py-10 bg-white dark:bg-card-dark rounded-2xl shadow-sm">' +
                    '<span class="material-symbols-outlined text-4xl text-slate-300 mb-2">event_busy</span>' +
                    '<p class="text-sm font-medium text-slate-500">Henüz etkinlik bulunmuyor.</p>' +
                    '</div>';
            }
        } catch (error) {
            console.error('Etkinlikler load error:', error);
            loading.style.display = 'none';
            container.classList.remove('hidden');
            container.innerHTML = '<div class="flex flex-col items-center justify-center py-10 bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-red-100 dark:border-red-900/30">' +
                '<span class="material-symbols-outlined text-4xl text-red-400 mb-2">error</span>' +
                '<p class="text-sm font-medium text-slate-500">Etkinlikler yüklenirken hata oluştu.</p>' +
                '</div>';
        }
    }
</script>