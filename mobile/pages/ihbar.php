<?php

use App\Helper\Security;
use App\Model\IhbarModel;
use App\Service\Gate;

Gate::authorizeOrDie('ihbar/list');

$tarihDogrula = static function ($deger, string $varsayilan): string {
    $deger = (string) $deger;
    $tarih = DateTime::createFromFormat('Y-m-d', $deger);
    return $tarih && $tarih->format('Y-m-d') === $deger ? $deger : $varsayilan;
};
$bitisTarihi = $tarihDogrula($_GET['bitis'] ?? '', date('Y-m-d'));
$baslangicTarihi = $tarihDogrula($_GET['baslangic'] ?? '', date('Y-m-d', strtotime('-1 month')));
if ($baslangicTarihi > $bitisTarihi) {
    [$baslangicTarihi, $bitisTarihi] = [$bitisTarihi, $baslangicTarihi];
}

$ihbarModel = new IhbarModel();
$ihbarlar = $ihbarModel->getAllForDashboard($baslangicTarihi, $bitisTarihi);
$durumlar = [
    'yeni' => ['Yeni', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
    'yonlendirildi' => ['Yönlendirildi', 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'],
    'islemde' => ['İşlemde', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
    'olumlu' => ['Olumlu', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
    'olumsuz' => ['Olumsuz', 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'],
];
$ozet = ['toplam' => count($ihbarlar), 'yeni' => 0, 'devam' => 0, 'sonuclanan' => 0];
$detaylar = [];
$sifreliIhbarIdleri = [];
foreach ($ihbarlar as $ihbar) {
    $ozet['yeni'] += $ihbar->durum === 'yeni' ? 1 : 0;
    $ozet['devam'] += in_array($ihbar->durum, ['yonlendirildi', 'islemde'], true) ? 1 : 0;
    $ozet['sonuclanan'] += in_array($ihbar->durum, ['olumlu', 'olumsuz'], true) ? 1 : 0;
    $anahtar = Security::encrypt($ihbar->id);
    $sifreliIhbarIdleri[(int) $ihbar->id] = $anahtar;
    $detaylar[$anahtar] = [
        'durum' => $durumlar[$ihbar->durum][0] ?? $ihbar->durum,
        'konum' => trim(($ihbar->ilce ?: '') . ' / ' . ($ihbar->mahalle ?: ''), ' /'),
        'telefon' => $ihbar->telefon ?: '-',
        'abone' => $ihbar->komsu_abone_no ?: '-',
        'aciklama' => $ihbar->aciklama ?: '-',
        'ekip' => $ihbar->atanan_ekip_adi ?: 'Henüz yönlendirilmedi',
        'tarih' => !empty($ihbar->created_at) ? date('d.m.Y H:i', strtotime($ihbar->created_at)) : '-',
        'tutanak' => $ihbar->tutanak_no ?: '-',
        'sebep' => $ihbar->olumsuz_sebep ?: '-',
        'foto' => (int) ($ihbar->foto_sayisi ?? 0),
        'token' => $anahtar,
        'lat' => is_numeric($ihbar->konum_lat) ? (float) $ihbar->konum_lat : null,
        'lng' => is_numeric($ihbar->konum_lng) ? (float) $ihbar->konum_lng : null,
    ];
}
function miH($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>

<section class="min-h-screen bg-slate-50 dark:bg-background-dark pb-24">
    <header class="bg-gradient-to-br from-orange-600 to-rose-600 text-white px-4 pt-5 pb-8 rounded-b-[2rem] shadow-lg">
        <div class="flex items-center justify-between">
            <div><p class="text-white/70 text-[10px] font-bold uppercase tracking-[.2em]">İş Takip</p><h1 class="text-xl font-bold">İhbar Yönetimi</h1></div>
            <span class="material-symbols-outlined text-3xl">campaign</span>
        </div>
        <div class="grid grid-cols-4 gap-2 mt-5">
            <?php foreach ([['Toplam',$ozet['toplam']],['Yeni',$ozet['yeni']],['Devam',$ozet['devam']],['Biten',$ozet['sonuclanan']]] as $item): ?>
            <div class="bg-white/15 rounded-xl p-2 text-center backdrop-blur"><b class="block text-lg"><?= (int)$item[1] ?></b><span class="text-[9px] uppercase font-bold text-white/75"><?= miH($item[0]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="px-4 -mt-3 space-y-3">
        <form method="get" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-3">
            <input type="hidden" name="p" value="ihbar">
            <div class="grid grid-cols-2 gap-2">
                <label class="text-[10px] font-bold text-slate-500">BAŞLANGIÇ<input type="date" name="baslangic" value="<?= miH($baslangicTarihi) ?>" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm"></label>
                <label class="text-[10px] font-bold text-slate-500">BİTİŞ<input type="date" name="bitis" value="<?= miH($bitisTarihi) ?>" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm"></label>
            </div>
            <button class="mt-2 w-full rounded-xl bg-orange-600 py-2.5 text-sm font-bold text-white">Dönemi Uygula</button>
        </form>
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-3 space-y-2">
            <div class="relative"><span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-xl">search</span><input id="ihbarSearch" type="search" placeholder="İlçe, mahalle, açıklama ara..." class="w-full rounded-xl border-0 bg-slate-100 dark:bg-slate-800 py-2.5 pl-10 pr-3 text-sm dark:text-white"></div>
            <div class="flex gap-2 overflow-x-auto no-scrollbar" id="ihbarFilters">
                <?php foreach (['' => 'Tümü', 'yeni' => 'Yeni', 'yonlendirildi' => 'Yönlendirildi', 'islemde' => 'İşlemde', 'olumlu' => 'Olumlu', 'olumsuz' => 'Olumsuz'] as $key => $label): ?>
                <button data-status="<?= miH($key) ?>" class="ihbar-filter shrink-0 px-3 py-1.5 rounded-full text-xs font-bold <?= $key === '' ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' ?>"><?= miH($label) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="ihbarList" class="space-y-3">
            <?php foreach ($ihbarlar as $ihbar): $d = $durumlar[$ihbar->durum] ?? [$ihbar->durum, 'bg-slate-100 text-slate-600']; $enc = $sifreliIhbarIdleri[(int) $ihbar->id]; ?>
            <button type="button" data-status="<?= miH($ihbar->durum) ?>" data-search="<?= miH(mb_strtolower(($ihbar->ilce ?? '').' '.($ihbar->mahalle ?? '').' '.($ihbar->aciklama ?? '').' '.($ihbar->atanan_ekip_adi ?? ''))) ?>" data-id="<?= miH($enc) ?>" class="ihbar-card w-full text-left bg-white dark:bg-card-dark rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm p-4 active:scale-[.99] transition-transform">
                <div class="flex justify-between gap-3"><div class="min-w-0"><h2 class="font-bold text-slate-800 dark:text-white truncate"><?= miH(($ihbar->ilce ?: 'İlçe belirtilmedi') . ($ihbar->mahalle ? ' · '.$ihbar->mahalle : '')) ?></h2><p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= miH($ihbar->aciklama ?: '-') ?></p></div><span class="shrink-0 h-fit px-2.5 py-1 rounded-full text-[10px] font-bold <?= miH($d[1]) ?>"><?= miH($d[0]) ?></span></div>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400"><span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">groups</span><?= miH($ihbar->atanan_ekip_adi ?: 'Atama bekliyor') ?></span><span><?= miH(date('d.m.Y H:i', strtotime($ihbar->created_at))) ?></span></div>
            </button>
            <?php endforeach; ?>
            <div id="ihbarEmpty" class="hidden text-center py-12 text-slate-400"><span class="material-symbols-outlined text-5xl">search_off</span><p class="mt-2 text-sm font-bold">Eşleşen ihbar bulunamadı</p></div>
        </div>
    </div>
</section>

<div id="ihbarOverlay" class="fixed inset-0 bg-black/50 z-[70] hidden" onclick="closeIhbarDetail()"></div>
<div id="ihbarSheet" class="fixed bottom-0 left-0 right-0 z-[71] translate-y-full transition-transform bg-white dark:bg-card-dark rounded-t-[2rem] max-h-[82vh] overflow-y-auto safe-area-bottom">
    <div class="sticky top-0 bg-white dark:bg-card-dark px-5 pt-3 pb-3 border-b border-slate-100 dark:border-slate-800"><div class="w-10 h-1 bg-slate-300 rounded-full mx-auto mb-3"></div><div class="flex justify-between"><h3 class="font-bold text-lg">İhbar Detayı</h3><button onclick="closeIhbarDetail()"><span class="material-symbols-outlined">close</span></button></div></div>
    <div id="ihbarDetailBody" class="p-5 space-y-3 text-sm"></div>
</div>
<div id="ihbarGallery" class="fixed inset-0 z-[90] hidden bg-slate-950/95 text-white overflow-y-auto"><div class="sticky top-0 z-10 flex justify-between items-center p-4 bg-slate-950/90"><div><h3 class="font-bold">İhbar Fotoğrafları</h3><p id="ihbarGalleryCount" class="text-[10px] text-slate-400"></p></div><button onclick="closeIhbarGallery()" class="w-10 h-10 rounded-full bg-white/10"><span class="material-symbols-outlined">close</span></button></div><div id="ihbarGalleryBody" class="grid grid-cols-2 gap-3 p-4 pb-24"></div></div>
<div id="ihbarLightbox" class="fixed inset-0 z-[110] hidden bg-black/95 text-white" onclick="if(event.target===this) closeIhbarLightbox()"><div class="absolute top-0 inset-x-0 z-10 flex justify-between p-4"><span id="ihbarLightboxCounter" class="text-xs font-bold"></span><button onclick="closeIhbarLightbox()" class="w-10 h-10 rounded-full bg-white/10"><span class="material-symbols-outlined">close</span></button></div><div class="w-full h-full flex items-center justify-center p-3 pt-16 pb-20"><img id="ihbarLightboxImage" class="max-w-full max-h-full object-contain rounded-lg" alt="İhbar fotoğrafı"></div><button id="ihbarLightboxPrev" onclick="stepIhbarLightbox(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50"><span class="material-symbols-outlined">chevron_left</span></button><button id="ihbarLightboxNext" onclick="stepIhbarLightbox(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50"><span class="material-symbols-outlined">chevron_right</span></button></div>
<script>
(() => {
    const details = <?= json_encode($detaylar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>;
    const esc = value => $('<div>').text(value ?? '-').html();
    let status = '', ihbarImages = [], ihbarImageIndex = 0;
    const apply = () => { let shown = 0; const q = ($('#ihbarSearch').val() || '').toLocaleLowerCase('tr-TR'); $('.ihbar-card').each(function(){ const ok = (!status || this.dataset.status === status) && (!q || this.dataset.search.includes(q)); $(this).toggle(ok); if(ok) shown++; }); $('#ihbarEmpty').toggleClass('hidden', shown > 0); };
    $('#ihbarSearch').on('input', apply);
    $('.ihbar-filter').on('click', function(){ status = this.dataset.status; $('.ihbar-filter').removeClass('bg-orange-600 text-white').addClass('bg-slate-100 text-slate-500 dark:bg-slate-800'); $(this).removeClass('bg-slate-100 text-slate-500 dark:bg-slate-800').addClass('bg-orange-600 text-white'); apply(); });
    $('.ihbar-card').on('click', function(){ const d = details[this.dataset.id]; if(!d) return; const map = d.lat !== null && d.lng !== null ? `<a class="flex items-center justify-center gap-2 bg-orange-600 text-white rounded-xl py-3 font-bold" target="_blank" rel="noopener" href="https://www.google.com/maps?q=${encodeURIComponent(d.lat+','+d.lng)}"><span class="material-symbols-outlined">map</span>Konumu Aç</a>` : ''; const gallery=d.foto>0?`<button id="ihbarGalleryButton" class="w-full flex items-center justify-center gap-2 bg-rose-600 text-white rounded-xl py-3 font-bold"><span class="material-symbols-outlined">photo_library</span>Fotoğrafları Görüntüle (${d.foto})</button>`:''; $('#ihbarDetailBody').html(`<div class="grid grid-cols-2 gap-3"><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3"><small class="text-slate-400">Durum</small><b class="block">${esc(d.durum)}</b></div><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3"><small class="text-slate-400">Tarih</small><b class="block">${esc(d.tarih)}</b></div></div><div><small class="text-slate-400">Konum</small><p class="font-semibold">${esc(d.konum || '-')}</p></div><div><small class="text-slate-400">Açıklama</small><p>${esc(d.aciklama)}</p></div><div><small class="text-slate-400">Atanan ekip</small><p>${esc(d.ekip)}</p></div><div class="grid grid-cols-2 gap-3"><div><small class="text-slate-400">Telefon</small><p>${esc(d.telefon)}</p></div><div><small class="text-slate-400">Komşu abone no</small><p>${esc(d.abone)}</p></div></div>${d.tutanak !== '-' ? `<div><small class="text-slate-400">Tutanak</small><p>${esc(d.tutanak)}</p></div>` : ''}${d.sebep !== '-' ? `<div><small class="text-slate-400">Sonuç sebebi</small><p>${esc(d.sebep)}</p></div>` : ''}${gallery}${map}`); $('#ihbarGalleryButton').on('click',()=>openIhbarGallery(d.token)); $('#ihbarOverlay').removeClass('hidden'); requestAnimationFrame(() => $('#ihbarSheet').removeClass('translate-y-full')); });
    window.closeIhbarDetail = () => { $('#ihbarSheet').addClass('translate-y-full'); setTimeout(() => $('#ihbarOverlay').addClass('hidden'), 250); };
    window.openIhbarGallery=async token=>{const body=$('#ihbarGalleryBody');ihbarImages=[];body.html('<div class="col-span-2 py-20 text-center">Fotoğraflar yükleniyor...</div>');$('#ihbarGallery').removeClass('hidden');document.body.style.overflow='hidden';try{const response=await fetch('api/ihbar-fotograflar.php?token='+encodeURIComponent(token),{credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Fotoğraflar yüklenemedi.');ihbarImages=result.data.map(f=>'../views/ihbar/api.php?action=foto&token='+encodeURIComponent(f.token));$('#ihbarGalleryCount').text(ihbarImages.length+' fotoğraf');body.html(ihbarImages.length?ihbarImages.map((url,i)=>`<button onclick="showIhbarPhoto(${i})" class="aspect-square overflow-hidden rounded-2xl bg-white/10"><img src="${url}" class="w-full h-full object-cover" loading="lazy" alt="İhbar fotoğrafı ${i+1}"></button>`).join(''):'<div class="col-span-2 py-20 text-center text-slate-400">Fotoğraf bulunamadı.</div>')}catch(e){body.html(`<div class="col-span-2 py-20 text-center text-red-300">${esc(e.message)}</div>`)}};
    const renderIhbarLightbox=()=>{$('#ihbarLightboxImage').attr('src',ihbarImages[ihbarImageIndex]);$('#ihbarLightboxCounter').text(`${ihbarImageIndex+1} / ${ihbarImages.length}`);$('#ihbarLightboxPrev,#ihbarLightboxNext').toggleClass('hidden',ihbarImages.length<2)};
    window.showIhbarPhoto=i=>{ihbarImageIndex=i;renderIhbarLightbox();$('#ihbarLightbox').removeClass('hidden')};window.stepIhbarLightbox=s=>{ihbarImageIndex=(ihbarImageIndex+s+ihbarImages.length)%ihbarImages.length;renderIhbarLightbox()};window.closeIhbarLightbox=()=>{$('#ihbarLightbox').addClass('hidden');$('#ihbarLightboxImage').attr('src','')};window.closeIhbarGallery=()=>{$('#ihbarGallery').addClass('hidden');document.body.style.overflow=''};
})();
</script>
