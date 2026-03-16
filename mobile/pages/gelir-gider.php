<?php
use App\Model\GelirGiderModel;
use App\Helper\Security;
use App\Helper\Helper;

$GelirGider = new GelirGiderModel();

// Filtreler
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedAy  = $_GET['ay'] ?? '';
$selectedTip = $_GET['tip'] ?? '';

// Özeti al
$summary = $GelirGider->summary(['yil' => $selectedYil, 'ay' => $selectedAy, 'tip' => $selectedTip]);
$toplam_gelir = $summary->toplam_gelir ?? 0;
$toplam_gider = $summary->toplam_gider ?? 0;
$bakiye = $summary->bakiye ?? 0;

// Listeyi al
$islemListesi = $GelirGider->all($selectedYil, $selectedAy, $selectedTip);

// Kategorileri ve Hesap Adlarını getir (Modal için)
$kategoriler = $GelirGider->getUniqueValues('kategori');
$hesapAdlari = $GelirGider->getUniqueValues('hesap_adi');

// Format helper
if (!function_exists('formatMoneyGG')) {
    function formatMoneyGG($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' ₺';
    }
}
?>

<div class="px-3 py-4 space-y-4 pb-20">

    <!-- Özet Kartları -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-emerald-500 text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-emerald-500 text-[22px] bg-emerald-50 dark:bg-emerald-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">trending_up</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Gelir</p>
            <p class="font-bold text-emerald-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyGG($toplam_gelir) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-rose-500 text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-rose-500 text-[22px] bg-rose-50 dark:bg-rose-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">trending_down</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Gider</p>
            <p class="font-bold text-rose-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyGG($toplam_gider) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-primary text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-primary text-[22px] bg-blue-50 dark:bg-blue-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">account_balance_wallet</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Bakiye</p>
            <p class="font-bold <?= $bakiye < 0 ? 'text-rose-600' : 'text-emerald-600' ?> text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyGG($bakiye) ?></p>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 flex gap-2">
        <select id="filterYil" class="flex-1 bg-slate-50 dark:bg-slate-800 border-0 rounded-lg text-xs font-semibold py-2">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $selectedYil == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select id="filterAy" class="flex-1 bg-slate-50 dark:bg-slate-800 border-0 rounded-lg text-xs font-semibold py-2">
            <option value="">Tüm Yıl</option>
            <?php 
            $aylar = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
            foreach($aylar as $num => $ad): ?>
                <option value="<?= $num ?>" <?= $selectedAy == $num ? 'selected' : '' ?>><?= $ad ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterTip" class="flex-1 bg-slate-50 dark:bg-slate-800 border-0 rounded-lg text-xs font-semibold py-2">
            <option value="">Tümü</option>
            <option value="1" <?= $selectedTip == '1' ? 'selected' : '' ?>>Gelir</option>
            <option value="2" <?= $selectedTip == '2' ? 'selected' : '' ?>>Gider</option>
        </select>
    </div>

    <!-- Arama Kutusu -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <span class="material-symbols-outlined text-slate-400">search</span>
        </div>
        <input type="text" id="ggSearch" placeholder="İşlem veya Açıklama Ara..." autocomplete="off"
               class="w-full pl-10 pr-4 py-3 bg-white dark:bg-card-dark border-transparent focus:border-primary focus:ring-0 rounded-xl shadow-sm text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
    </div>

    <!-- İşlem Listesi -->
    <div class="space-y-3" id="ggList">
        <?php foreach ($islemListesi as $row): 
            $typeColor = $row->type == 1 ? 'text-emerald-600' : 'text-rose-600';
            $typeBg = $row->type == 1 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20';
            $typeIcon = $row->type == 1 ? 'add_circle' : 'do_not_disturb_on';
            $encId = Security::encrypt($row->id);
            $searchString = mb_strtolower($row->hesap_adi . ' ' . $row->kategori_adi . ' ' . $row->aciklama, 'UTF-8');
            $tarih = date('d.m.Y H:i', strtotime($row->tarih));
        ?>
        <div class="relative gg-item-container overflow-hidden rounded-xl shadow-sm">
            <!-- Delete Action -->
            <div class="absolute left-0 top-0 bottom-0 w-[70px] bg-rose-500 flex items-center justify-center text-white cursor-pointer swipe-action-right opacity-0" 
                 onclick="event.stopPropagation(); window.deleteGG('<?= $encId ?>', '<?= addslashes($row->aciklama) ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                    <span class="text-[9px] font-bold uppercase">Sil</span>
                </div>
            </div>

            <!-- Edit Action -->
            <div class="absolute right-0 top-0 bottom-0 w-[70px] bg-amber-500 flex items-center justify-center text-white cursor-pointer swipe-action-left opacity-0" 
                 onclick="event.stopPropagation(); window.editGG('<?= $encId ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                    <span class="text-[9px] font-bold uppercase">Düzenle</span>
                </div>
            </div>

            <div class="bg-white dark:bg-card-dark p-3 border border-slate-100 dark:border-slate-700/50 flex items-center transition-transform duration-200 swipe-content gg-item" 
                 data-search="<?= htmlspecialchars($searchString) ?>">
                <!-- Icon -->
                <div class="w-10 h-10 rounded-[10px] <?= $typeBg ?> <?= $typeColor ?> flex items-center justify-center shrink-0 border border-current/10">
                    <span class="material-symbols-outlined text-xl"><?= $typeIcon ?></span>
                </div>
                
                <!-- Info -->
                <div class="ml-3 flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                        <h4 class="font-semibold text-[13px] text-slate-900 dark:text-white truncate"><?= htmlspecialchars($row->kategori_adi ?: 'Kategorisiz') ?></h4>
                        <span class="font-bold text-[13px] <?= $typeColor ?>"><?= ($row->type == 2 ? '-' : '+') . formatMoneyGG($row->tutar) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium truncate max-w-[140px]"><?= htmlspecialchars($row->hesap_adi ?: '-') ?></span>
                            <span class="text-[9px] text-slate-400 dark:text-slate-500"><?= $tarih ?></span>
                        </div>
                        <span class="text-[10px] text-slate-400 italic truncate max-w-[100px]"><?= htmlspecialchars($row->aciklama) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($islemListesi) == 0): ?>
            <div class="text-center py-8 text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
                <p class="text-sm">İşlem kaydı bulunmuyor.</p>
            </div>
        <?php endif; ?>
        <div id="noResult" class="hidden text-center py-8 text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
            <p class="text-sm">Sonuç bulunamadı.</p>
        </div>
    </div>

    <!-- FAB -->
    <button onclick="window.openGGModal()" class="fixed bottom-[80px] right-4 w-14 h-14 bg-primary text-white rounded-full shadow-lg shadow-primary/30 flex items-center justify-center z-40 active:scale-95 transition-transform border-0 focus:outline-none">
        <span class="material-symbols-outlined text-3xl">add</span>
    </button>
</div>
<!-- Modal Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/50 dark:bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300" onclick="window.closeModals()"></div>

<style>
    /* Premium Input Styling */
    .form-group-premium { position: relative; margin-bottom: 1rem; }
    .form-label-premium { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 2px; }
    .input-wrapper-premium { position: relative; display: flex; align-items: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; transition: all 0.2s; overflow: hidden; }
    .input-wrapper-premium:focus-within { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1); }
    .input-wrapper-premium .material-symbols-outlined { color: #94a3b8; margin-left: 12px; font-size: 20px; flex-shrink: 0; }
    .input-premium { width: 100%; border: none !important; background: transparent !important; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #1e293b; outline: none !important; box-shadow: none !important; }
    .input-premium::placeholder { color: #cbd5e1; }
    
    /* Dark Mode Improvements */
    .dark .input-wrapper-premium { background: #1e1e1e; border-color: #334155; }
    .dark .input-wrapper-premium:focus-within { background: #1a1a1a; border-color: var(--primary); }
    .dark .input-premium { color: white; }
    .dark .form-label-premium { color: #94a3b8; }

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        border: 1.5px solid #e2e8f0 !important;
        background: #f8fafc !important;
        border-radius: 14px !important;
        height: 50px !important;
        display: flex !important;
        align-items: center !important;
        transition: all 0.2s !important;
    }
    .dark .select2-container--default .select2-selection--single {
        border-color: #334155 !important;
        background: #1e1e1e !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 45px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #1e293b !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: white !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 12px !important;
        right: 12px !important;
    }
    .select2-container--open .select2-dropdown {
        border-radius: 14px !important;
        border: 1.5px solid #e2e8f0 !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        overflow: hidden !important;
    }
    .dark .select2-container--open .select2-dropdown {
        border-color: #334155 !important;
        background: #1e1e1e !important;
        color: white !important;
    }
    .select2-search--dropdown .select2-search__field {
        border-radius: 8px !important;
        padding: 8px 12px !important;
    }
    
    /* Icon overlay for Select2 */
    .select2-icon-wrapper { position: relative; }
    .select2-icon-wrapper .material-symbols-outlined {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: #94a3b8;
        pointer-events: none;
        font-size: 20px;
    }
</style>

<!-- Yeni İşlem Modal -->
<div id="ggModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-card-dark z-10 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">account_balance</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-base">Gelir Gider İşlemi</h3>
                <p class="text-[11px] text-slate-400 font-medium">Lütfen tüm alanları eksiksiz doldurun</p>
            </div>
        </div>
        <button onclick="window.closeModals()" class="w-10 h-10 flex items-center justify-center text-slate-400 rounded-2xl bg-slate-50 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>
    
    <div class="p-5 overflow-y-auto">
        <form id="ggForm" onsubmit="window.submitGGForm(event)">
            <input type="hidden" name="action" value="gelir-gider-kaydet">
            <input type="hidden" name="gelir_gider_id" value="0">
            
            <div class="flex p-1.5 bg-slate-100 dark:bg-slate-800 rounded-2xl mb-6 gap-1.5">
                <label class="flex-1 text-center py-3 rounded-xl text-sm font-bold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-emerald-600 has-[:checked]:shadow-sm transition-all flex items-center justify-center gap-2">
                    <input type="radio" name="type" value="1" class="hidden peer">
                    <span class="material-symbols-outlined text-lg">add_circle</span> Gelir
                </label>
                <label class="flex-1 text-center py-3 rounded-xl text-sm font-bold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-rose-600 has-[:checked]:shadow-sm transition-all flex items-center justify-center gap-2">
                    <input type="radio" name="type" value="2" class="hidden peer" checked>
                    <span class="material-symbols-outlined text-lg">do_not_disturb_on</span> Gider
                </label>
            </div>
            
            <div class="space-y-5">
                <div class="form-group-premium">
                    <label class="form-label-premium">Hesap Adı</label>
                    <div class="select2-icon-wrapper">
                        <span class="material-symbols-outlined">person</span>
                        <select name="hesap_adi" id="hesap_adi_select" class="w-full">
                            <option value="">Seçiniz veya Yazınız</option>
                            <?php foreach($hesapAdlari as $h): if($h) echo "<option value='$h'>$h</option>"; endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group-premium">
                    <label class="form-label-premium">Kategori</label>
                    <div class="select2-icon-wrapper">
                        <span class="material-symbols-outlined">category</span>
                        <select name="islem_turu" id="islem_turu_select" class="w-full">
                            <option value="">Seçiniz veya Yazınız</option>
                            <?php foreach($kategoriler as $k): if($k) echo "<option value='$k'>$k</option>"; endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group-premium">
                        <label class="form-label-premium">İşlem Tarihi*</label>
                        <div class="input-wrapper-premium">
                            <span class="material-symbols-outlined">calendar_today</span>
                            <input type="datetime-local" name="islem_tarihi" value="<?= date('Y-m-d\TH:i') ?>" required class="input-premium">
                        </div>
                    </div>
                    <div class="form-group-premium">
                        <label class="form-label-premium">Tutar (TL)*</label>
                        <div class="input-wrapper-premium">
                            <span class="material-symbols-outlined">payments</span>
                            <input type="number" step="0.01" min="0.01" name="tutar" placeholder="0.00" required class="input-premium text-right font-bold">
                        </div>
                    </div>
                </div>
                
                <div class="form-group-premium">
                    <label class="form-label-premium">Açıklama</label>
                    <div class="input-wrapper-premium items-start pt-1">
                        <span class="material-symbols-outlined mt-2.5">notes</span>
                        <textarea name="aciklama" rows="3" class="input-premium placeholder-slate-400" placeholder="İşlem detaylarını buraya yazın..."></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="ggSubmitBtn" class="w-full py-4 mt-8 bg-primary dark:bg-primary text-white rounded-2xl font-bold flex items-center justify-center gap-3 active:scale-95 transition-all mb-4 shadow-xl shadow-primary/20 border-0">
                <span class="material-symbols-outlined">check_circle</span> 
                <span class="text-base">Kaydet</span>
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2 integration (Optional but good for tags)
    $('#hesap_adi_select, #islem_turu_select').select2({
        tags: true,
        width: '100%',
        dropdownParent: $('#ggModal')
    });

    // Filtre Değişimi
    $('#filterYil, #filterAy, #filterTip').on('change', function() {
        const yil = $('#filterYil').val();
        const ay = $('#filterAy').val();
        const tip = $('#filterTip').val();
        location.href = `?p=gelir-gider&yil=${yil}&ay=${ay}&tip=${tip}`;
    });

    // Swipe Functionality
    (function() {
        let touchStartX = 0;
        let touchStartY = 0;
        let isMoving = false;

        window.closeAllSwipes = function() {
            document.querySelectorAll('.swipe-content').forEach(el => {
                el.style.transform = 'translateX(0)';
            });
            document.querySelectorAll('.swipe-action-right, .swipe-action-left').forEach(el => {
                el.style.opacity = '0';
            });
        };

        document.addEventListener('touchstart', e => {
            const container = e.target.closest('.gg-item-container');
            if (!container) {
                window.closeAllSwipes();
                return;
            }
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isMoving = false;
        }, { passive: true });

        document.addEventListener('touchmove', e => {
            const container = e.target.closest('.gg-item-container');
            if (!container) return;
            const currentX = e.touches[0].clientX;
            const currentY = e.touches[0].clientY;
            const diffX = currentX - touchStartX;
            const diffY = currentY - touchStartY;
            
            if (Math.abs(diffX) > Math.abs(diffY)) {
                isMoving = true;
            }
        }, { passive: true });

        document.addEventListener('touchend', e => {
            const container = e.target.closest('.gg-item-container');
            if (!container) return;
            const touchEndX = e.changedTouches[0].clientX;
            const diffX = touchEndX - touchStartX;
            
            const swipeContent = container.querySelector('.swipe-content');
            const actionRight = container.querySelector('.swipe-action-right');
            const actionLeft = container.querySelector('.swipe-action-left');

            if (isMoving && diffX > 50) {
                window.closeAllSwipes();
                swipeContent.style.transform = 'translateX(70px)';
                if (actionRight) actionRight.style.opacity = '1';
            } else if (isMoving && diffX < -50) {
                window.closeAllSwipes();
                swipeContent.style.transform = 'translateX(-70px)';
                if (actionLeft) actionLeft.style.opacity = '1';
            }
        }, { passive: true });
    })();

    // Search
    document.getElementById('ggSearch').addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        const items = document.querySelectorAll('.gg-item');
        let hasVisible = false;
        
        items.forEach(item => {
            const text = item.getAttribute('data-search');
            if (text.includes(term)) {
                item.closest('.gg-item-container').style.display = 'block';
                hasVisible = true;
            } else {
                item.closest('.gg-item-container').style.display = 'none';
            }
        });
        
        document.getElementById('noResult').style.display = hasVisible || items.length === 0 ? 'none' : 'block';
    });
});

window.openGGModal = function() {
    const form = document.getElementById('ggForm');
    form.reset();
    form.querySelector('input[name="gelir_gider_id"]').value = '0';
    $('#hesap_adi_select').val('').trigger('change');
    $('#islem_turu_select').val('').trigger('change');
    
    document.querySelector('#ggModal h3').innerText = 'Yeni İşlem Ekle';
    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('ggModal').classList.remove('translate-y-full');
};

window.editGG = function(id) {
    const formData = new FormData();
    formData.append('action', 'gelir-gider-getir');
    formData.append('gelir_gider_id', id);

    fetch('../views/gelir-gider/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data) {
            const form = document.getElementById('ggForm');
            form.querySelector('input[name="gelir_gider_id"]').value = id;
            form.querySelector(`input[name="type"][value="${data.type}"]`).checked = true;
            
            // Handle Select2
            if ($('#hesap_adi_select').find("option[value='" + data.hesap_adi + "']").length) {
                $('#hesap_adi_select').val(data.hesap_adi).trigger('change');
            } else {
                var newOption = new Option(data.hesap_adi, data.hesap_adi, true, true);
                $('#hesap_adi_select').append(newOption).trigger('change');
            }

            if ($('#islem_turu_select').find("option[value='" + data.kategori + "']").length) {
                $('#islem_turu_select').val(data.kategori).trigger('change');
            } else {
                var newOption = new Option(data.kategori, data.kategori, true, true);
                $('#islem_turu_select').append(newOption).trigger('change');
            }
            
            const islemTarihi = data.tarih ? data.tarih.replace(' ', 'T').substring(0, 16) : '';
            form.querySelector('input[name="islem_tarihi"]').value = islemTarihi;
            form.querySelector('input[name="tutar"]').value = data.tutar;
            form.querySelector('textarea[name="aciklama"]').value = data.aciklama || '';
            
            document.querySelector('#ggModal h3').innerText = 'İşlemi Düzenle';
            document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
            document.getElementById('ggModal').classList.remove('translate-y-full');
            window.closeAllSwipes();
        }
    });
};

window.deleteGG = async function(id, desc) {
    const confirmed = await Alert.confirmDelete("İşlem Silinecek", `"${desc}" açıklamasını içeren işlemi silmek istediğinize emin misiniz?`);
    if (confirmed) {
        const formData = new FormData();
        formData.append('action', 'gelir-gider-sil');
        formData.append('gelir_gider_id', id);

        fetch('../views/gelir-gider/api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                Alert.error("Hata", data.message);
            }
        });
    }
};

window.closeModals = function() {
    document.getElementById('modalOverlay').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('ggModal').classList.add('translate-y-full');
};

window.submitGGForm = function(e) {
    e.preventDefault();
    const btn = document.getElementById('ggSubmitBtn');
    const formData = new FormData(e.target);
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/gelir-gider/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            location.reload();
        } else {
            Alert.error("Hata", data.message);
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg">save</span> Kaydet';
        }
    })
    .catch(() => {
        Alert.error("Hata", "Sunucu hatası.");
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-lg">save</span> Kaydet';
    });
};
</script>
