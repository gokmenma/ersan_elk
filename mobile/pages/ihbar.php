<?php

use App\Helper\Security;
use App\Model\IhbarModel;
use App\Model\KacakKontrolModel;
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
$kacakModel = new KacakKontrolModel();
$yonlendirilecekPersoneller = array_map(static function (array $personel): array {
    return [
        'token' => Security::encrypt($personel['id']),
        'ad' => $personel['adi_soyadi'],
        'gorev' => $personel['gorev'] ?: ($personel['departman'] ?: 'Kaçak Kontrol'),
    ];
}, $kacakModel->getEkipAdaylari());
$mobileCsrf = Security::csrf();
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
$personelOzet = [];
foreach ($ihbarlar as $ihbar) {
    $ekipUyeleri = array_values(array_filter(array_map('trim', explode(',', (string) ($ihbar->atanan_ekip_adi ?? '')))));
    if (!$ekipUyeleri) continue;
    $ekipAdi = implode(' & ', $ekipUyeleri);
    $personelOzet[$ekipAdi] = ($personelOzet[$ekipAdi] ?? 0) + 1;
}
arsort($personelOzet);
$personelOzet = array_slice($personelOzet, 0, 10, true);
$personelOzetMax = max([1, ...array_values($personelOzet)]);
function miH($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>

<script>
(() => {
    const pageColor = '#ea580c';
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta) themeMeta.setAttribute('content', pageColor);
    document.querySelectorAll('.h-safe-top').forEach(el => {
        el.style.backgroundColor = pageColor;
    });
})();
</script>

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

<button type="button" onclick="openIhbarSummary()" class="fixed right-4 bottom-24 z-40 w-14 h-14 rounded-full bg-orange-600 text-white shadow-xl shadow-orange-600/30 flex items-center justify-center active:scale-95"><span class="material-symbols-outlined text-2xl">leaderboard</span></button>
<div id="ihbarSummaryOverlay" class="fixed inset-0 z-[72] hidden bg-black/50" onclick="closeIhbarSummary()"></div>
<div id="ihbarSummarySheet" class="fixed bottom-0 inset-x-0 z-[73] translate-y-full transition-transform rounded-t-[2rem] bg-white dark:bg-card-dark max-h-[78vh] overflow-y-auto safe-area-bottom">
 <div class="sticky top-0 bg-white dark:bg-card-dark p-4 border-b dark:border-slate-800"><div class="w-10 h-1 bg-slate-300 rounded-full mx-auto mb-3"></div><div class="flex justify-between items-center"><div><h2 class="font-bold">Ekip Bazında İhbar Özeti</h2><p class="text-[10px] text-slate-400"><?= miH(date('d.m.Y',strtotime($baslangicTarihi)).' – '.date('d.m.Y',strtotime($bitisTarihi))) ?></p></div><button onclick="closeIhbarSummary()"><span class="material-symbols-outlined">close</span></button></div></div>
 <div class="p-5"><?php if(!$personelOzet): ?><p class="text-sm text-center text-slate-400 py-10">Atanmış ihbar bulunamadı.</p><?php else: ?><div class="space-y-4"><?php $renkler=['from-violet-600 to-fuchsia-400','from-orange-500 to-amber-300','from-sky-600 to-cyan-300','from-emerald-600 to-lime-300','from-rose-600 to-pink-300'];$ri=0;foreach($personelOzet as $ad=>$sayi): ?><div><div class="flex justify-between text-xs mb-1.5"><b class="truncate"><?= miH($ad) ?></b><span class="font-black"><?= (int)$sayi ?></span></div><div class="h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r <?= $renkler[$ri++%count($renkler)] ?>" style="width:<?= (int)round($sayi/$personelOzetMax*100) ?>%"></div></div></div><?php endforeach; ?></div><?php endif; ?></div>
</div>

<div id="ihbarOverlay" class="fixed inset-0 bg-black/50 z-[70] hidden" onclick="closeIhbarDetail()"></div>
<div id="ihbarSheet" class="fixed bottom-0 left-0 right-0 z-[71] translate-y-full transition-transform bg-white dark:bg-card-dark rounded-t-[2rem] max-h-[82vh] overflow-y-auto safe-area-bottom">
    <div class="sticky top-0 bg-white dark:bg-card-dark px-5 pt-3 pb-3 border-b border-slate-100 dark:border-slate-800"><div class="w-10 h-1 bg-slate-300 rounded-full mx-auto mb-3"></div><div class="flex justify-between"><h3 class="font-bold text-lg">İhbar Detayı</h3><button onclick="closeIhbarDetail()"><span class="material-symbols-outlined">close</span></button></div></div>
    <div id="ihbarDetailBody" class="p-5 space-y-3 text-sm"></div>
</div>
<div id="ihbarGallery" class="fixed inset-0 z-[90] hidden bg-slate-950/95 text-white overflow-y-auto"><div class="sticky top-0 z-10 flex justify-between items-center p-4 bg-slate-950/90"><div><h3 class="font-bold">İhbar Fotoğrafları</h3><p id="ihbarGalleryCount" class="text-[10px] text-slate-400"></p></div><button onclick="closeIhbarGallery()" class="w-10 h-10 rounded-full bg-white/10"><span class="material-symbols-outlined">close</span></button></div><div id="ihbarGalleryBody" class="grid grid-cols-2 gap-3 p-4 pb-24"></div></div>
<div id="ihbarLightbox" class="fixed inset-0 z-[110] hidden bg-black/95 text-white" onclick="if(event.target===this) closeIhbarLightbox()"><div class="absolute top-0 inset-x-0 z-10 flex justify-between p-4"><span id="ihbarLightboxCounter" class="text-xs font-bold"></span><button onclick="closeIhbarLightbox()" class="w-10 h-10 rounded-full bg-white/10"><span class="material-symbols-outlined">close</span></button></div><div class="w-full h-full flex items-center justify-center p-3 pt-16 pb-20"><img id="ihbarLightboxImage" class="max-w-full max-h-full object-contain rounded-lg" alt="İhbar fotoğrafı"></div><button id="ihbarLightboxPrev" onclick="stepIhbarLightbox(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50"><span class="material-symbols-outlined">chevron_left</span></button><button id="ihbarLightboxNext" onclick="stepIhbarLightbox(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/50"><span class="material-symbols-outlined">chevron_right</span></button></div>
<div id="ihbarAssignOverlay" class="fixed inset-0 z-[80] hidden bg-black/50" onclick="closeAssignSheet()"></div>
<div id="ihbarAssignSheet" class="fixed bottom-0 inset-x-0 z-[81] translate-y-full transition-transform rounded-t-[2rem] bg-white dark:bg-card-dark max-h-[85vh] flex flex-col safe-area-bottom">
 <div class="p-4 border-b dark:border-slate-800"><div class="w-10 h-1 bg-slate-300 rounded-full mx-auto mb-3"></div><div class="flex items-center justify-between"><div><h2 class="font-bold text-lg">Kaçak Ekibini Seçin</h2><p class="text-xs text-slate-400">En fazla iki personel seçebilirsiniz</p></div><button onclick="closeAssignSheet()" class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800"><span class="material-symbols-outlined">close</span></button></div><div class="relative mt-3"><span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400">search</span><input id="assignPersonSearch" type="search" placeholder="Personel ara..." class="w-full rounded-xl border-0 bg-slate-100 dark:bg-slate-800 pl-10 text-sm"></div></div>
 <div id="assignPersonList" class="overflow-y-auto flex-1 p-4 space-y-2"></div>
 <div class="p-4 border-t dark:border-slate-800 bg-white dark:bg-card-dark"><button id="assignPersonSubmit" class="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-500 py-3.5 text-white font-bold shadow-lg">Seçilen Ekibe Yönlendir <span id="assignSelectedCount"></span></button></div>
</div>
<script>
(() => {
 const details=<?= json_encode($detaylar, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>;
 const people=<?= json_encode($yonlendirilecekPersoneller,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' ?>;
 const csrf=<?= json_encode($mobileCsrf) ?>, esc=v=>$('<div>').text(v??'-').html();let status='',images=[],imageIndex=0,assignToken='';
 const apply=()=>{let n=0,q=($('#ihbarSearch').val()||'').toLocaleLowerCase('tr-TR');$('.ihbar-card').each(function(){let ok=(!status||this.dataset.status===status)&&(!q||this.dataset.search.includes(q));$(this).toggle(ok);if(ok)n++});$('#ihbarEmpty').toggleClass('hidden',n>0)};
 $('#ihbarSearch').on('input',apply);$('.ihbar-filter').on('click',function(){status=this.dataset.status;$('.ihbar-filter').removeClass('bg-orange-600 text-white').addClass('bg-slate-100 text-slate-500 dark:bg-slate-800');$(this).removeClass('bg-slate-100 text-slate-500 dark:bg-slate-800').addClass('bg-orange-600 text-white');apply()});
 $('.ihbar-card').on('click',function(){let d=details[this.dataset.id];if(!d)return;let row=(l,v)=>`<div><small class="text-slate-400">${esc(l)}</small><p class="font-semibold">${esc(v)}</p></div>`,gallery=d.foto>0?`<button id="ihbarGalleryButton" class="w-full rounded-xl bg-rose-600 py-3 text-white font-bold">Fotoğrafları Görüntüle (${d.foto})</button>`:'',map=d.lat!==null&&d.lng!==null?`<a target="_blank" rel="noopener" href="https://www.google.com/maps?q=${encodeURIComponent(d.lat+','+d.lng)}" class="block text-center rounded-xl bg-orange-600 py-3 text-white font-bold">Konumu Aç</a>`:'';$('#ihbarDetailBody').html(`<div class="grid grid-cols-2 gap-3">${row('Durum',d.durum)}${row('Tarih',d.tarih)}${row('Konum',d.konum)}${row('Telefon',d.telefon)}</div>${row('Açıklama',d.aciklama)}${row('Atanan ekip',d.ekip)}${gallery}${map}<div class="grid gap-2 pt-2"><button id="assignIhbar" class="rounded-xl bg-indigo-600 py-3 text-white font-bold">Ekibe Yönlendir</button><button id="noteIhbar" class="rounded-xl bg-slate-700 py-3 text-white font-bold">İşlem Notu Ekle</button><button id="closeIhbar" class="rounded-xl bg-emerald-600 py-3 text-white font-bold">İhbarı Sonuçlandır</button></div>`);$('#ihbarGalleryButton').on('click',()=>openGallery(d.token));$('#assignIhbar').on('click',()=>assign(d.token));$('#noteIhbar').on('click',()=>note(d.token));$('#closeIhbar').on('click',()=>finish(d.token));$('#ihbarOverlay').removeClass('hidden');requestAnimationFrame(()=>$('#ihbarSheet').removeClass('translate-y-full'))});
 const post=async data=>{data._mobile_csrf=csrf;let response=await fetch('../views/ihbar/api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams(data),credentials:'same-origin'}),result=await response.json();if(!result.success)throw new Error(result.message||'İşlem başarısız.');await Alert.success('İşlem Tamamlandı',result.message);location.reload()};
 const renderPeople=(query='')=>{let q=query.toLocaleLowerCase('tr-TR'),filtered=people.filter(p=>!q||p.ad.toLocaleLowerCase('tr-TR').includes(q));$('#assignPersonList').html(filtered.map((p,i)=>`<label class="assign-person flex items-center gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 p-3 active:scale-[.99]" data-search="${esc(p.ad.toLocaleLowerCase('tr-TR'))}"><input type="checkbox" value="${p.token}" class="assign-check rounded text-indigo-600"><div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/30 text-indigo-600 flex items-center justify-center font-black">${esc(p.ad.charAt(0))}</div><div class="min-w-0"><b class="block text-sm truncate">${esc(p.ad)}</b><span class="text-[10px] text-slate-400">${esc(p.gorev)}</span></div></label>`).join('')||'<p class="text-center text-sm text-slate-400 py-10">Eşleşen personel bulunamadı.</p>');$('.assign-check').on('change',function(){if($('.assign-check:checked').length>2){this.checked=false;Toast.show('En fazla iki personel seçebilirsiniz.','warning')}updateAssignCount()})};
 const updateAssignCount=()=>{let n=$('.assign-check:checked').length;$('#assignSelectedCount').text(n?`(${n})`:'')};
 const assign=token=>{assignToken=token;$('#assignPersonSearch').val('');renderPeople();$('#ihbarAssignOverlay').removeClass('hidden');requestAnimationFrame(()=>$('#ihbarAssignSheet').removeClass('translate-y-full'))};
 window.closeAssignSheet=()=>{$('#ihbarAssignSheet').addClass('translate-y-full');setTimeout(()=>$('#ihbarAssignOverlay').addClass('hidden'),250)};$('#assignPersonSearch').on('input',function(){renderPeople(this.value)});$('#assignPersonSubmit').on('click',async()=>{let selected=$('.assign-check:checked').map((_,e)=>e.value).get();if(!selected.length){Toast.show('En az bir personel seçmelisiniz.','warning');return}if(selected.length>2){Toast.show('En fazla iki personel seçebilirsiniz.','warning');return}let data={action:'assign',mobile_token:assignToken};selected.forEach((token,i)=>data[`personel_tokens[${i}]`]=token);try{await post(data)}catch(e){Alert.error('Hata',e.message)}});
 const note=async token=>{let value=await Alert.prompt('İşlem Notu','Yapılan işlemi yazın.','Notu Ekle');if(!value)return;try{await post({action:'addNote',mobile_token:token,aciklama:value})}catch(e){Alert.error('Hata',e.message)}};
 const finish=async token=>{let r=await Swal.fire({title:'İhbarı Sonuçlandır',html:'<select id="ihbarResult" class="w-full rounded-xl mb-3"><option value="olumlu">Olumlu</option><option value="olumsuz">Olumsuz</option></select><input id="ihbarReport" class="w-full rounded-xl mb-3" placeholder="Tutanak no"><textarea id="ihbarReason" class="w-full rounded-xl" placeholder="Olumsuz sonuç sebebi"></textarea>',showCancelButton:true,confirmButtonText:'Kaydet',cancelButtonText:'Vazgeç',preConfirm:()=>({durum:$('#ihbarResult').val(),tutanak_no:$('#ihbarReport').val(),sebep:$('#ihbarReason').val()})});if(!r.isConfirmed)return;try{await post({action:'close',mobile_token:token,...r.value})}catch(e){Alert.error('Hata',e.message)}};
 window.closeIhbarDetail=()=>{$('#ihbarSheet').addClass('translate-y-full');setTimeout(()=>$('#ihbarOverlay').addClass('hidden'),250)};
 window.openGallery=async token=>{let body=$('#ihbarGalleryBody');images=[];body.html('<div class="col-span-2 py-20 text-center">Yükleniyor...</div>');$('#ihbarGallery').removeClass('hidden');try{let response=await fetch('api/ihbar-fotograflar.php?token='+encodeURIComponent(token)),result=await response.json();if(!result.success)throw new Error(result.message);images=result.data.map(f=>'../views/ihbar/api.php?action=foto&token='+encodeURIComponent(f.token));$('#ihbarGalleryCount').text(images.length+' fotoğraf');body.html(images.map((u,i)=>`<button onclick="showIhbarPhoto(${i})" class="aspect-square overflow-hidden rounded-2xl"><img src="${u}" class="w-full h-full object-cover"></button>`).join(''))}catch(e){body.html(`<div class="col-span-2 text-red-300">${esc(e.message)}</div>`)}};
 const render=()=>{$('#ihbarLightboxImage').attr('src',images[imageIndex]);$('#ihbarLightboxCounter').text(`${imageIndex+1} / ${images.length}`)};window.showIhbarPhoto=i=>{imageIndex=i;render();$('#ihbarLightbox').removeClass('hidden')};window.stepIhbarLightbox=s=>{imageIndex=(imageIndex+s+images.length)%images.length;render()};window.closeIhbarLightbox=()=>$('#ihbarLightbox').addClass('hidden');window.closeIhbarGallery=()=>$('#ihbarGallery').addClass('hidden');
 window.openIhbarSummary=()=>{$('#ihbarSummaryOverlay').removeClass('hidden');requestAnimationFrame(()=>$('#ihbarSummarySheet').removeClass('translate-y-full'))};window.closeIhbarSummary=()=>{$('#ihbarSummarySheet').addClass('translate-y-full');setTimeout(()=>$('#ihbarSummaryOverlay').addClass('hidden'),250)};
})();
</script>
