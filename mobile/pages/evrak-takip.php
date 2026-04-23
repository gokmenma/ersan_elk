<?php
use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;

$Evrak = new EvrakTakipModel();
$Personel = new PersonelModel();

$stats = $Evrak->getStats();
$evraklar = $Evrak->all();

// Format helper
if (!function_exists('formatDateEvrak')) {
    function formatDateEvrak($dateStr) {
        if (!$dateStr) return '-';
        return date('d.m.Y', strtotime($dateStr));
    }
}
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-sky-600 to-sky-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10 pointer-events-none">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    
    <div class="relative z-10 flex flex-col gap-4">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                Evrak Takip
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium">Toplam <?= $stats->toplam_evrak ?? 0 ?> evrak</p>
        </div>

        <div class="flex gap-1.5 overflow-x-auto no-scrollbar pb-1 -mr-4 pr-4">
            <button onclick="filterType('all')" class="flex-1 min-w-[65px] text-center filter-btn active" data-type="all">
                <div class="bg-white rounded-xl px-2 py-2 backdrop-blur-sm border border-white/20 shadow-sm transition-all text-sky-600">
                    <span class="block text-lg font-black leading-tight"><?= $stats->toplam_evrak ?? 0 ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider opacity-90 truncate">Tümü</span>
                </div>
            </button>
            <button onclick="filterType('gelen')" class="flex-1 min-w-[65px] text-center filter-btn" data-type="gelen">
                <div class="bg-white/20 rounded-xl px-2 py-2 backdrop-blur-sm border border-white/20 shadow-sm transition-all text-white">
                    <span class="block text-lg font-black leading-tight"><?= $stats->gelen_evrak ?? 0 ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider opacity-90 truncate">Gelen</span>
                </div>
            </button>
            <button onclick="filterType('giden')" class="flex-1 min-w-[65px] text-center filter-btn" data-type="giden">
                <div class="bg-white/20 rounded-xl px-2 py-2 backdrop-blur-sm border border-white/20 shadow-sm transition-all text-white">
                    <span class="block text-lg font-black leading-tight"><?= $stats->giden_evrak ?? 0 ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider opacity-90 truncate">Giden</span>
                </div>
            </button>
            <button onclick="filterType('bekleyen')" class="flex-1 min-w-[65px] text-center filter-btn" data-type="bekleyen">
                <div class="bg-white/20 rounded-xl px-2 py-2 backdrop-blur-sm border border-white/20 shadow-sm transition-all text-white">
                    <span class="block text-lg font-black leading-tight"><?= $stats->cevap_bekleyen ?? 0 ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider opacity-90 truncate">Bekleyen</span>
                </div>
            </button>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-4 pb-6">
    <!-- Search -->
    <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
        <input type="text" id="evrakSearch" placeholder="Evrak no, konu, kurum ara..." class="w-full bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl py-3 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/50 transition-shadow shadow-sm text-slate-700 dark:text-slate-200">
    </div>

    <!-- List -->
    <div id="evrakList" class="space-y-3">
        <?php foreach ($evraklar as $evrak): 
            $isGelen = $evrak->evrak_tipi == 'gelen';
            $isBekleyen = $isGelen && (!$evrak->cevap_verildi_mi);
            $typeClass = $isGelen ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400';
            $typeIcon = $isGelen ? 'download' : 'upload';
            $typeLabel = $isGelen ? 'Gelen' : 'Giden';
            
            $searchTags = mb_strtolower($evrak->evrak_no . ' ' . $evrak->konu . ' ' . $evrak->kurum_adi . ' ' . $evrak->personel_adi . ' ' . $evrak->ilgili_personel_adi, 'UTF-8');
            $filterTags = $evrak->evrak_tipi . ($isBekleyen ? ' bekleyen' : '');
        ?>
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 relative overflow-hidden evrak-card" 
             data-search="<?= htmlspecialchars($searchTags) ?>"
             data-filter="<?= $filterTags ?>">
            
            <div class="flex items-start justify-between mb-3 border-b border-slate-100 dark:border-slate-800/60 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl <?= $typeClass ?> flex flex-col items-center justify-center border border-current opacity-80 shrink-0">
                        <span class="material-symbols-outlined text-lg"><?= $typeIcon ?></span>
                        <span class="text-[8px] uppercase font-bold leading-none mt-0.5"><?= $typeLabel ?></span>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm truncate"><?= htmlspecialchars($evrak->konu) ?></h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[11px] text-slate-500 font-medium">No: <?= htmlspecialchars($evrak->evrak_no) ?></span>
                            <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                            <span class="text-[11px] text-slate-500 font-medium"><?= formatDateEvrak($evrak->tarih) ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($isBekleyen): ?>
                    <span class="bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 text-[9px] px-2 py-1 rounded-lg font-black uppercase shrink-0">Bekliyor</span>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 gap-2.5">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-slate-400">corporate_fare</span>
                    <span class="text-[12px] text-slate-700 dark:text-slate-300 font-semibold truncate"><?= htmlspecialchars($evrak->kurum_adi ?: '-') ?></span>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">person</span>
                        <span class="text-[11px] text-slate-600 dark:text-slate-400 truncate"><?= htmlspecialchars($evrak->personel_adi ?: '-') ?></span>
                    </div>
                    <?php if ($evrak->ilgili_personel_adi): ?>
                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                        <span class="material-symbols-outlined text-[16px] text-sky-500">person_search</span>
                        <span class="text-[11px] text-sky-600 dark:text-sky-400 truncate"><?= htmlspecialchars($evrak->ilgili_personel_adi) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3 pt-3 border-t border-slate-50 dark:border-slate-800/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <?php if ($evrak->dosya_yolu): ?>
                        <a href="../<?= $evrak->dosya_yolu ?>" target="_blank" class="flex items-center gap-1 text-[11px] font-bold text-sky-600 bg-sky-50 dark:bg-sky-900/20 px-2 py-1 rounded-lg">
                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                            Dosya
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2">
                    <button onclick="event.stopPropagation(); editEvrak(<?= $evrak->id ?>)" class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-500 flex items-center justify-center active:scale-90 transition-transform">
                        <span class="material-symbols-outlined text-base">edit</span>
                    </button>
                    <button onclick="event.stopPropagation(); deleteEvrak(<?= $evrak->id ?>)" class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center active:scale-90 transition-transform">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div id="noResults" class="hidden py-10 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-2">search_off</span>
            <p class="text-slate-500 text-sm">Kayıt bulunamadı.</p>
        </div>
    </div>
</div>

<!-- FAB -->
<button onclick="openNewEvrak()" class="fixed bottom-24 right-4 w-14 h-14 bg-sky-500 text-white rounded-full shadow-lg shadow-sky-500/30 flex items-center justify-center active:scale-95 transition-transform z-50">
    <span class="material-symbols-outlined text-3xl">add</span>
</button>

<!-- Bottom Sheet Container -->
<div id="bs-container" class="fixed inset-0 z-[100] hidden flex-col justify-end">
    <div id="bs-backdrop" class="absolute inset-0 bg-slate-900/60 transition-opacity duration-300 opacity-0" onclick="closeSheet()"></div>
    <div id="bs-sheet" class="relative w-full bg-slate-50 dark:bg-slate-900 rounded-t-3xl min-h-[50vh] max-h-[90vh] flex flex-col translate-y-full transition-transform duration-300 shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 flex flex-col items-center sticky top-0 z-10">
             <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full mb-3 cursor-pointer" onclick="closeSheet()"></div>
             <div class="w-full flex justify-between items-center">
                 <h3 id="bs-title" class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2">
                     <span class="material-symbols-outlined text-sky-500">description</span>
                     <span>Evrak İşlemi</span>
                 </h3>
                 <button onclick="closeSheet()" class="w-8 h-8 flex items-center justify-center bg-slate-100 dark:bg-slate-800 rounded-full text-slate-500 hover:bg-slate-200 transition-colors focus:outline-none">
                     <span class="material-symbols-outlined text-sm">close</span>
                 </button>
             </div>
        </div>
        <div id="bs-body" class="p-4 overflow-y-auto w-full grow flex flex-col space-y-4 pb-28 relative">
            <?php include 'sheets/evrak-sheet.php'; ?>
        </div>
    </div>
</div>

<script>
function filterType(type) {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        const div = btn.querySelector('div');
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('active');
            div.classList.replace('bg-white/20', 'bg-white');
            div.classList.replace('text-white', 'text-sky-600');
        } else {
            btn.classList.remove('active');
            div.classList.replace('bg-white', 'bg-white/20');
            div.classList.replace('text-sky-600', 'text-white');
        }
    });

    applyFilters();
}

function applyFilters() {
    const searchTerm = document.getElementById('evrakSearch').value.toLowerCase();
    const activeType = document.querySelector('.filter-btn.active').getAttribute('data-type');
    const cards = document.querySelectorAll('.evrak-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const searchTags = card.getAttribute('data-search');
        const filterTags = card.getAttribute('data-filter');
        
        const matchesSearch = searchTags.includes(searchTerm);
        let matchesFilter = true;
        
        if (activeType !== 'all') {
            matchesFilter = filterTags.includes(activeType);
        }

        if (matchesSearch && matchesFilter) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);
}

document.getElementById('evrakSearch').addEventListener('input', applyFilters);

const MobileSwal = Swal.mixin({
    customClass: {
        container: 'z-[9999]',
        popup: 'rounded-[2rem] shadow-2xl bg-white dark:bg-slate-800 dark:text-white border border-slate-100 dark:border-slate-700',
        title: 'text-lg font-extrabold text-slate-800 dark:text-white tracking-tight',
        htmlContainer: 'text-sm text-slate-500 dark:text-slate-400',
        actions: 'flex gap-3 w-full px-6 mb-4',
        confirmButton: 'flex-1 py-3 bg-sky-500 hover:bg-sky-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md focus:outline-none',
        cancelButton: 'flex-1 py-3 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 font-bold rounded-xl transition-all focus:outline-none'
    },
    buttonsStyling: false
});

function openSheet(id = 'evrak') {
    const container = document.getElementById('bs-container');
    const backdrop  = document.getElementById('bs-backdrop');
    const sheet     = document.getElementById('bs-sheet');
    
    container.classList.remove('hidden');
    container.classList.add('flex');
    
    setTimeout(() => {
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
    }, 10);
    
    document.querySelectorAll('.app-sheet-content').forEach(el => el.classList.add('hidden'));
    document.getElementById('sheet-content-' + id).classList.remove('hidden');
}

function closeSheet() {
    const backdrop = document.getElementById('bs-backdrop');
    const sheet    = document.getElementById('bs-sheet');
    const container = document.getElementById('bs-container');
    
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    
    setTimeout(() => {
        container.classList.add('hidden');
        container.classList.remove('flex');
    }, 300);
}

function openNewEvrak() {
    const form = document.getElementById('evrakForm');
    form.reset();
    form.id.value = '';
    document.getElementById('bs-title').innerText = 'Yeni Evrak Kaydı';
    openSheet('evrak');
}

function editEvrak(id) {
    MobileSwal.fire({
        title: 'Yükleniyor...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    $.post('../views/evrak-takip/api.php', { action: 'evrak-detay', id: id }, function(response) {
        MobileSwal.close();
        const res = (typeof response === 'object') ? response : JSON.parse(response);
        if (res.status === 'success') {
            const data = res.data;
            const form = document.getElementById('evrakForm');
            form.reset();
            
            form.id.value = data.id;
            form.tarih.value = data.tarih;
            form.evrak_no.value = data.evrak_no;
            form.konu.value = data.konu;
            form.kurum_adi.value = data.kurum_adi;
            form.personel_id.value = data.personel_id;
            form.ilgili_personel_id.value = data.ilgili_personel_id;
            form.aciklama.value = data.aciklama;
            
            const tipRadio = form.querySelector(`input[name="evrak_tipi"][value="${data.evrak_tipi}"]`);
            if (tipRadio) {
                tipRadio.checked = true;
                toggleTip(data.evrak_tipi);
            }
            
            if (data.evrak_tipi === 'gelen') {
                form.cevap_verildi_mi.checked = data.cevap_verildi_mi == 1;
                toggleCevap(data.cevap_verildi_mi == 1);
                form.cevap_tarihi.value = data.cevap_tarihi;
            } else {
                form.ilgili_evrak_id.value = data.ilgili_evrak_id;
            }
            
            document.getElementById('bs-title').innerText = 'Evrak Düzenle';
            setTimeout(() => {
                openSheet('evrak');
            }, 300);
        } else {
            MobileSwal.fire('Hata', 'Bilgiler alınamadı.', 'error');
        }
    });
}

function deleteEvrak(id) {
    MobileSwal.fire({
        title: 'Emin misiniz?',
        text: 'Bu evrak kaydı kalıcı olarak silinecektir.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, Sil',
        cancelButtonText: 'Vazgeç'
    }).then(res => {
        if (res.isConfirmed) {
            $.post('../views/evrak-takip/api.php', { action: 'evrak-sil', id: id }, function(response) {
                const res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status === 'success') {
                    window.location.reload();
                } else {
                    MobileSwal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                }
            });
        }
    });
}
</script>

<style>
.filter-btn div { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.filter-btn.active div {
    transform: translateY(-4px);
    box-shadow: 0 8px 15px -3px rgba(0,0,0,0.2);
}
.filter-btn.active span { color: inherit; }
</style>
