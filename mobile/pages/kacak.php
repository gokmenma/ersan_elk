<?php

use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Service\Gate;

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Bu sayfaya erişim yetkiniz bulunmuyor.');
}

$kacakModel = new KacakKontrolModel();
$tarihDogrula = static function ($deger, string $varsayilan): string { $deger=(string)$deger; $tarih=DateTime::createFromFormat('Y-m-d',$deger); return $tarih&&$tarih->format('Y-m-d')===$deger?$deger:$varsayilan; };
$bitisTarihi=$tarihDogrula($_GET['bitis']??'',date('Y-m-d'));
$baslangicTarihi=$tarihDogrula($_GET['baslangic']??'',date('Y-m-d',strtotime('-1 month')));
if($baslangicTarihi>$bitisTarihi){[$baslangicTarihi,$bitisTarihi]=[$bitisTarihi,$baslangicTarihi];}
$kayitlar = $kacakModel->getRecords(['tarih_baslangic'=>$baslangicTarihi,'tarih_bitis'=>$bitisTarihi]);
$ozet = ['toplam' => count($kayitlar), 'beklemede' => 0, 'onaylandi' => 0, 'iptal' => 0];
$detaylar = [];
$sifreliKacakIdleri = [];
foreach ($kayitlar as $kayit) {
    $ozet['beklemede'] += $kayit['onay_durumu'] === 'beklemede' ? 1 : 0;
    $ozet['onaylandi'] += $kayit['onay_durumu'] === 'onaylandi' ? 1 : 0;
    $ozet['iptal'] += $kayit['durum'] === 'iptal' ? 1 : 0;
    $anahtar = Security::encrypt($kayit['id']);
    $sifreliKacakIdleri[(int) $kayit['id']] = $anahtar;
    $detaylar[$anahtar] = [
        'tarih' => !empty($kayit['tarih']) ? date('d.m.Y', strtotime($kayit['tarih'])) : '-',
        'ilce' => $kayit['ilce'] ?: '-', 'tur' => $kayit['tur'] ?: '-',
        'tutanak' => $kayit['tutanak_no'] ?: '-', 'abone' => $kayit['abone_adi'] ?: '-',
        'sayac' => $kayit['sayac_no'] ?: '-', 'endeks' => $kayit['endeks'] ?: '-',
        'ekip' => $kayit['ekip_adi'] ?: ($kayit['bildiren_adi'] ?: '-'),
        'aciklama' => $kayit['aciklama'] ?: '-', 'onay' => $kayit['onay_durumu'] ?: '-',
        'durum' => $kayit['durum'] ?: '-', 'foto' => (int)$kayit['foto_sayisi'],
        'token' => $anahtar,
    ];
}
function mkH($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function mkOnay(string $durum): array {
    return match ($durum) {
        'onaylandi' => ['Onaylandı','bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
        'reddedildi' => ['Reddedildi','bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'],
        default => ['Beklemede','bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
    };
}
?>
<section class="min-h-screen bg-slate-50 dark:bg-background-dark pb-24">
    <header class="bg-gradient-to-br from-red-700 to-rose-500 text-white px-4 pt-5 pb-8 rounded-b-[2rem] shadow-lg">
        <div class="flex items-center justify-between"><div><p class="text-white/70 text-[10px] font-bold uppercase tracking-[.2em]">Saha İşlemleri</p><h1 class="text-xl font-bold">Kaçak Kontrol</h1></div><span class="material-symbols-outlined text-3xl">shield</span></div>
        <div class="grid grid-cols-4 gap-2 mt-5">
            <?php foreach ([['Toplam',$ozet['toplam']],['Bekleyen',$ozet['beklemede']],['Onaylı',$ozet['onaylandi']],['İptal',$ozet['iptal']]] as $item): ?><div class="bg-white/15 rounded-xl p-2 text-center backdrop-blur"><b class="block text-lg"><?= (int)$item[1] ?></b><span class="text-[9px] uppercase font-bold text-white/75"><?= mkH($item[0]) ?></span></div><?php endforeach; ?>
        </div>
    </header>
    <div class="px-4 -mt-3 space-y-3">
        <form method="get" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-3"><input type="hidden" name="p" value="kacak"><div class="grid grid-cols-2 gap-2"><label class="text-[10px] font-bold text-slate-500">BAŞLANGIÇ<input type="date" name="baslangic" value="<?= mkH($baslangicTarihi) ?>" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm"></label><label class="text-[10px] font-bold text-slate-500">BİTİŞ<input type="date" name="bitis" value="<?= mkH($bitisTarihi) ?>" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm"></label></div><button class="mt-2 w-full rounded-xl bg-red-600 py-2.5 text-sm font-bold text-white">Dönemi Uygula</button></form>
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-3 space-y-2">
            <div class="relative"><span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-xl">search</span><input id="kacakSearch" type="search" placeholder="Tutanak, sayaç, abone veya ekip ara..." class="w-full rounded-xl border-0 bg-slate-100 dark:bg-slate-800 py-2.5 pl-10 pr-3 text-sm dark:text-white"></div>
            <div class="flex gap-2 overflow-x-auto no-scrollbar"><?php foreach ([''=>'Tümü','beklemede'=>'Bekleyen','onaylandi'=>'Onaylı','reddedildi'=>'Reddedilen','iptal'=>'İptal'] as $key=>$label): ?><button data-status="<?= mkH($key) ?>" class="kacak-filter shrink-0 px-3 py-1.5 rounded-full text-xs font-bold <?= $key===''?'bg-red-600 text-white':'bg-slate-100 text-slate-500 dark:bg-slate-800' ?>"><?= mkH($label) ?></button><?php endforeach; ?></div>
        </div>
        <div class="space-y-3">
            <?php foreach ($kayitlar as $kayit): $badge=mkOnay($kayit['onay_durumu']); $enc=$sifreliKacakIdleri[(int) $kayit['id']]; $filterStatus=$kayit['durum']==='iptal'?'iptal':$kayit['onay_durumu']; ?>
            <button type="button" data-id="<?= mkH($enc) ?>" data-status="<?= mkH($filterStatus) ?>" data-search="<?= mkH(mb_strtolower(implode(' ', [$kayit['tutanak_no'],$kayit['sayac_no'],$kayit['abone_adi'],$kayit['ekip_adi'],$kayit['ilce']]))) ?>" class="kacak-card w-full text-left bg-white dark:bg-card-dark rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm p-4 active:scale-[.99] transition-transform">
                <div class="flex justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><span class="text-[10px] font-black uppercase text-red-600"><?= mkH($kayit['tur'] ?: 'Kaçak') ?></span><span class="text-[10px] text-slate-400"><?= mkH(!empty($kayit['tarih'])?date('d.m.Y',strtotime($kayit['tarih'])):'-') ?></span></div><h2 class="font-bold text-slate-800 dark:text-white truncate mt-1"><?= mkH($kayit['tutanak_no'] ?: ($kayit['abone_adi'] ?: 'Tutanak numarası yok')) ?></h2><p class="text-xs text-slate-500 mt-1 truncate"><?= mkH(($kayit['ilce'] ?: '-') . ' · ' . ($kayit['ekip_adi'] ?: ($kayit['bildiren_adi'] ?: 'Ekip yok'))) ?></p></div><span class="shrink-0 h-fit px-2.5 py-1 rounded-full text-[10px] font-bold <?= mkH($badge[1]) ?>"><?= mkH($kayit['durum']==='iptal'?'İptal':$badge[0]) ?></span></div>
                <div class="flex gap-4 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400"><span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">speed</span><?= mkH($kayit['sayac_no'] ?: '-') ?></span><span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">photo_camera</span><?= (int)$kayit['foto_sayisi'] ?></span></div>
            </button>
            <?php endforeach; ?><div id="kacakEmpty" class="hidden text-center py-12 text-slate-400"><span class="material-symbols-outlined text-5xl">search_off</span><p class="mt-2 text-sm font-bold">Eşleşen kayıt bulunamadı</p></div>
        </div>
    </div>
</section>
<div id="kacakOverlay" class="fixed inset-0 bg-black/50 z-[70] hidden" onclick="closeKacakDetail()"></div>
<div id="kacakSheet" class="fixed bottom-0 left-0 right-0 z-[71] translate-y-full transition-transform bg-white dark:bg-card-dark rounded-t-[2rem] max-h-[82vh] overflow-y-auto safe-area-bottom"><div class="sticky top-0 bg-white dark:bg-card-dark px-5 pt-3 pb-3 border-b border-slate-100 dark:border-slate-800"><div class="w-10 h-1 bg-slate-300 rounded-full mx-auto mb-3"></div><div class="flex justify-between"><h3 class="font-bold text-lg">Kaçak Kontrol Detayı</h3><button onclick="closeKacakDetail()"><span class="material-symbols-outlined">close</span></button></div></div><div id="kacakDetailBody" class="p-5 space-y-3 text-sm"></div></div>
<div id="kacakGallery" class="fixed inset-0 z-[90] hidden bg-slate-950/95 text-white overflow-y-auto">
 <div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-slate-950/90 backdrop-blur"><div><h3 class="font-bold">Kayıt Fotoğrafları</h3><p id="kacakGalleryCount" class="text-[10px] text-slate-400"></p></div><button type="button" onclick="closeKacakGallery()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined">close</span></button></div>
 <div id="kacakGalleryBody" class="grid grid-cols-2 gap-3 p-4 pb-24"></div>
</div>
<div id="kacakLightbox" class="fixed inset-0 z-[110] hidden bg-black/95 text-white select-none" onclick="if(event.target===this) closeKacakLightbox()">
 <div class="absolute top-0 left-0 right-0 z-10 flex items-center justify-between p-4 bg-gradient-to-b from-black/80 to-transparent">
  <span id="kacakLightboxCounter" class="text-xs font-bold"></span>
  <button type="button" onclick="closeKacakLightbox()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined">close</span></button>
 </div>
 <div class="w-full h-full flex items-center justify-center p-3 pt-16 pb-20"><img id="kacakLightboxImage" src="" alt="Kaçak kontrol fotoğrafı" class="max-w-full max-h-full object-contain rounded-lg"></div>
 <button id="kacakLightboxPrev" type="button" onclick="stepKacakLightbox(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50 border border-white/20 flex items-center justify-center"><span class="material-symbols-outlined">chevron_left</span></button>
 <button id="kacakLightboxNext" type="button" onclick="stepKacakLightbox(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50 border border-white/20 flex items-center justify-center"><span class="material-symbols-outlined">chevron_right</span></button>
</div>
<script>
(() => {
 const details=<?= json_encode($detaylar, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>, esc=v=>$('<div>').text(v??'-').html(); let status='', lightboxImages=[], lightboxIndex=0;
 const apply=()=>{let n=0,q=($('#kacakSearch').val()||'').toLocaleLowerCase('tr-TR');$('.kacak-card').each(function(){let ok=(!status||this.dataset.status===status)&&(!q||this.dataset.search.includes(q));$(this).toggle(ok);if(ok)n++});$('#kacakEmpty').toggleClass('hidden',n>0)};
 $('#kacakSearch').on('input',apply);$('.kacak-filter').on('click',function(){status=this.dataset.status;$('.kacak-filter').removeClass('bg-red-600 text-white').addClass('bg-slate-100 text-slate-500 dark:bg-slate-800');$(this).removeClass('bg-slate-100 text-slate-500 dark:bg-slate-800').addClass('bg-red-600 text-white');apply()});
 $('.kacak-card').on('click',function(){let d=details[this.dataset.id];if(!d)return;let row=(l,v)=>`<div><small class="text-slate-400">${esc(l)}</small><p class="font-semibold break-words">${esc(v)}</p></div>`;let gallery=d.foto>0?`<button id="kacakGalleryButton" type="button" class="w-full mt-2 flex items-center justify-center gap-2 rounded-xl bg-red-600 py-3 text-white font-bold"><span class="material-symbols-outlined">photo_library</span>Fotoğrafları Görüntüle (${d.foto})</button>`:'';$('#kacakDetailBody').html(`<div class="grid grid-cols-2 gap-3"><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3"><small class="text-slate-400">İşlem durumu</small><b class="block capitalize">${esc(d.durum)}</b></div><div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3"><small class="text-slate-400">Onay durumu</small><b class="block capitalize">${esc(d.onay)}</b></div></div><div class="grid grid-cols-2 gap-3">${row('Tarih',d.tarih)}${row('İlçe',d.ilce)}${row('Tür',d.tur)}${row('Tutanak no',d.tutanak)}${row('Abone',d.abone)}${row('Sayaç no',d.sayac)}${row('Endeks',d.endeks)}${row('Fotoğraf',d.foto)}</div>${row('Ekip / Bildiren',d.ekip)}${row('Açıklama',d.aciklama)}${gallery}`);$('#kacakGalleryButton').on('click',()=>openKacakGallery(d.token));$('#kacakOverlay').removeClass('hidden');requestAnimationFrame(()=>$('#kacakSheet').removeClass('translate-y-full'))});
 window.closeKacakDetail=()=>{$('#kacakSheet').addClass('translate-y-full');setTimeout(()=>$('#kacakOverlay').addClass('hidden'),250)};
 window.openKacakGallery=async token=>{let body=$('#kacakGalleryBody');lightboxImages=[];body.html('<div class="col-span-2 py-20 text-center"><span class="material-symbols-outlined animate-spin text-4xl">progress_activity</span><p class="mt-2 text-sm">Fotoğraflar yükleniyor...</p></div>');$('#kacakGallery').removeClass('hidden');document.body.style.overflow='hidden';try{let response=await fetch('api/kacak-fotograflar.php?token='+encodeURIComponent(token),{credentials:'same-origin'}),result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Fotoğraflar yüklenemedi.');$('#kacakGalleryCount').text(result.data.length+' dosya');if(!result.data.length){body.html('<div class="col-span-2 py-20 text-center text-slate-400">Görüntülenebilir fotoğraf bulunamadı.</div>');return}result.data.forEach(f=>{if(!f.pdf)lightboxImages.push('../views/kacak/foto-goruntule.php?token='+encodeURIComponent(f.token))});let imageIndex=0;body.html(result.data.map((f,i)=>{let url='../views/kacak/foto-goruntule.php?token='+encodeURIComponent(f.token),label=f.tur==='tutanak'?'Tutanak':'Saha';if(f.pdf)return `<a href="${url}" target="_blank" rel="noopener" class="aspect-square rounded-2xl bg-white/10 flex flex-col items-center justify-center p-3"><span class="material-symbols-outlined text-5xl text-red-400">picture_as_pdf</span><b class="mt-2 text-xs">${esc(f.ad)}</b></a>`;let current=imageIndex++;return `<button type="button" onclick="showKacakPhoto(${current})" class="relative aspect-square overflow-hidden rounded-2xl bg-white/10"><img src="${url}" alt="${esc(f.ad)}" class="w-full h-full object-cover" loading="lazy"><span class="absolute bottom-2 left-2 rounded-full bg-black/60 px-2 py-1 text-[10px] font-bold">${label} ${i+1}</span></button>`}).join(''))}catch(e){body.html(`<div class="col-span-2 py-20 text-center text-red-300"><span class="material-symbols-outlined text-4xl">error</span><p class="mt-2">${esc(e.message)}</p></div>`);}};
 const renderLightbox=()=>{if(!lightboxImages.length)return;$('#kacakLightboxImage').attr('src',lightboxImages[lightboxIndex]);$('#kacakLightboxCounter').text(`${lightboxIndex+1} / ${lightboxImages.length}`);$('#kacakLightboxPrev,#kacakLightboxNext').toggleClass('hidden',lightboxImages.length<2)};
 window.showKacakPhoto=index=>{lightboxIndex=index;renderLightbox();$('#kacakLightbox').removeClass('hidden')};
 window.stepKacakLightbox=step=>{lightboxIndex=(lightboxIndex+step+lightboxImages.length)%lightboxImages.length;renderLightbox()};
 window.closeKacakLightbox=()=>{$('#kacakLightbox').addClass('hidden');$('#kacakLightboxImage').attr('src','')};
 window.closeKacakGallery=()=>{$('#kacakGallery').addClass('hidden');document.body.style.overflow=''};
 $(document).on('keydown',e=>{if($('#kacakLightbox').hasClass('hidden'))return;if(e.key==='Escape')closeKacakLightbox();if(e.key==='ArrowLeft')stepKacakLightbox(-1);if(e.key==='ArrowRight')stepKacakLightbox(1)});
})();
</script>
