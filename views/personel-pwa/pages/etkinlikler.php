<div class="header-main bg-primary relative px-4 pt-12 pb-6 flex items-center shadow-lg rounded-b-[2rem] safe-area-top">
    <!-- Header Background Pattern -->
    <div class="absolute inset-0 opacity-10 overflow-hidden rounded-b-[2rem]">
        <svg class="absolute -right-4 top-0 w-32 h-32" viewBox="0 0 100 100" fill="currentColor"
            xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="50" />
        </svg>
        <svg class="absolute -left-12 -bottom-12 w-48 h-48" viewBox="0 0 100 100" fill="currentColor"
            xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="50" />
        </svg>
    </div>

    <!-- Header Content -->
    <div class="relative z-10 flex items-center gap-3 w-full">
        <!-- Back Button Option -->
        <a href="?page=ana-sayfa"
            class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur border border-white/20 flex items-center justify-center text-white cursor-pointer active:scale-95 transition-transform">
            <span class="material-symbols-outlined font-light">arrow_back</span>
        </a>
        <div class="flex-1">
            <h1 class="text-white text-xl font-bold tracking-tight">Tüm Etkinlikler</h1>
            <p class="text-white/80 text-xs">Geçmiş ve yaklaşan etkinlik/duyurular</p>
        </div>
    </div>
</div>

<div class="px-4 mt-6">
    <div id="etkinlikler-loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div id="etkinlikler-container" class="grid gap-4 hidden pb-10">
        <!-- Events will be populated here via JS -->
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadAllEtkinlikler();
    });

    async function loadAllEtkinlikler() {
        var container = document.getElementById('etkinlikler-container');
        var loading = document.getElementById('etkinlikler-loading');

        try {
            var response = await API.request('getAllEtkinlikler');
            loading.style.display = 'none';
            container.classList.remove('hidden');

            if (response.success && response.data && response.data.length > 0) {
                container.innerHTML = response.data.map(function (duyuru) {
                    var onClick = duyuru.hedef_sayfa ? 'window.location.href=\'' + escapeHtml(duyuru.hedef_sayfa) + '\';' : '';
                    var cursorClass = duyuru.hedef_sayfa ? 'cursor-pointer' : '';

                    var opacityClass = duyuru.gecmis ? 'opacity-60 saturate-50' : '';

                    var bgImg = duyuru.resim ? 'background-image: linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.3)), url(\'' + escapeHtml(duyuru.resim) + '\'); background-size: cover; background-position: center;'
                        : 'background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);';

                    var kalan_gun_html = '';
                    if (duyuru.kalan_gun !== null && duyuru.kalan_gun !== undefined && !duyuru.gecmis) {
                        kalan_gun_html = '<div class="absolute top-4 right-4 bg-black/40 backdrop-blur-md border border-white/20 rounded-xl px-3 py-1 text-center shadow-[0_0_10px_rgba(255,255,255,0.1)] z-20 flex flex-col items-center justify-center">' +
                            '<span class="block text-2xl font-black text-white leading-none tracking-tighter" style="text-shadow: 0 1px 3px rgba(0,0,0,0.5);">-' + escapeHtml(duyuru.kalan_gun) + '</span>' +
                            '<span class="block text-[8px] font-bold text-white/90 uppercase tracking-widest mt-1">GÜN KALDI</span>' +
                            '</div>';
                    }

                    return '<div class="rounded-2xl p-4 text-white shadow-lg relative overflow-hidden transition-transform active:scale-[0.98] ' + cursorClass + ' ' + opacityClass + '" ' +
                        'style="' + bgImg + '" onclick="' + onClick + '">' +
                        kalan_gun_html +
                        '<div class="relative z-10 pr-12">' +
                        '<span class="badge badge-primary bg-white/20 text-white border-none mb-2 text-[10px]">' + escapeHtml(duyuru.tarih) + '</span>' +
                        (duyuru.gecmis ? '<span class="badge badge-danger bg-red-500/80 text-white border-none mb-2 ml-2 text-[10px]">Geçmiş Etkinlik</span>' : '') +
                        '<h3 class="font-bold text-lg leading-tight mb-2 text-white">' + escapeHtml(duyuru.baslik) + '</h3>' +
                        '<p class="text-xs text-white/80 line-clamp-3 leading-relaxed">' + escapeHtml(duyuru.icerik) + '</p>' +
                        '</div>' +
                        '</div>';
                }).join('');
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