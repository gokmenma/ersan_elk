<?php
use App\Model\PersonelModel;

$PersonelModel = new PersonelModel();

$istatistik = $PersonelModel->personelSayilari('personel');
$toplam_p   = (int) ($istatistik->toplam_personel ?? 0);
$aktif_p    = (int) ($istatistik->aktif_personel  ?? 0);
$pasif_p    = (int) ($istatistik->pasif_personel  ?? 0);

$db = $PersonelModel->getDb();
$stmt = $db->prepare("SELECT * FROM personel WHERE firma_id = ? AND silinme_tarihi IS NULL AND (disardan_sigortali = 0 OR FIND_IN_SET('personel', gorunum_modulleri)) ORDER BY adi_soyadi ASC");
$stmt->execute([$_SESSION['firma_id'] ?? 0]);
$personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

// Helper function for user initials
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $name = trim($name);
        if (empty($name)) return '?';
        $words = explode(' ', $name);
        $initials = '';
        if (count($words) >= 2) {
            $initials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[count($words) - 1], 0, 1, 'UTF-8');
        } else {
            $initials = mb_substr($name, 0, 2, 'UTF-8');
        }
        return mb_strtoupper($initials, 'UTF-8');
    }
}
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-indigo-600 to-indigo-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg shrink-0">
    <div class="absolute inset-0 opacity-10 pointer-events-none">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                Personel
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium">Toplam <?= $toplam_p ?> personel</p>
        </div>
        <div class="flex gap-2">
            <button onclick="filterBadge('Aktif')" class="text-center badge-btn transition-transform active:scale-95 focus:outline-none">
                <div class="bg-white/20 rounded-xl px-3 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-xl font-black"><?= $aktif_p ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90">Aktif</span>
                </div>
            </button>
            <button onclick="filterBadge('Pasif')" class="text-center badge-btn transition-transform active:scale-95 focus:outline-none">
                <div class="bg-white/20 rounded-xl px-3 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm opacity-70">
                    <span class="block text-xl font-black"><?= $pasif_p ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90">Pasif</span>
                </div>
            </button>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-4 pb-24">
    
    <!-- Arama Alanı -->
    <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
        <input type="text" id="personelSearchInput" onkeyup="filterCards()" placeholder="Ad, soyad, görev, telefon ara..." class="w-full bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl py-3 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-shadow text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 shadow-sm">
        <button id="clearSearchBtn" onclick="clearSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hidden">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>

    <!-- PERSONEL LİSTESİ -->
    <div class="space-y-3" id="personel-list">
        <?php if (empty($personeller)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-8 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-indigo-400 text-3xl">group_off</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white mb-1">Kayıtlı Personel Yok</h3>
                <p class="text-sm text-slate-500">Sistemde henüz personel bulunmuyor.</p>
            </div>
        <?php else: ?>
            <?php foreach ($personeller as $kisi): 
                $tel = $kisi->cep_telefonu ?? '';
                $gorev = $kisi->gorev_gosterim ?? $kisi->gorev ?? '';
                $departman = $kisi->departman_gosterim ?? $kisi->departman ?? '-';
                
                $dtAyrilis = $kisi->isten_cikis_tarihi ?? '';
                $isAktif = (empty($dtAyrilis) || $dtAyrilis == '0000-00-00') ? 1 : 0;
            ?>
                <!-- Sadece aktif personeli varsayılan gösterelim, pasifler filtreyle açılsın diye class ekliyoruz -->
                <div class="personel-card rounded-2xl shadow-sm p-4 transition-transform active:scale-[0.98] <?= $isAktif ? 'bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 is-aktif' : 'bg-rose-50/50 dark:bg-rose-900/10 border border-rose-100/60 dark:border-rose-900/30 is-pasif hidden' ?>" onclick="openMenu('<?= urlencode(\App\Helper\Security::encrypt($kisi->id)) ?>')">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <?php 
                            $paResim = !empty($kisi->personel_resim_yolu) ? $kisi->personel_resim_yolu : ($kisi->resim_yolu ?? '');
                            if (!empty($paResim) && file_exists($paResim)): ?>
                                <img src="../<?= htmlspecialchars($paResim) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-50 dark:from-indigo-900/40 dark:to-indigo-800/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-lg border-2 border-indigo-50 dark:border-slate-700">
                                    <?= getInitials($kisi->adi_soyadi) ?>
                                </div>
                            <?php endif; ?>
                            <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-card-dark <?= $isAktif ? 'bg-green-500' : 'bg-slate-400' ?>"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-900 dark:text-white text-[15px] truncate">
                                <?= htmlspecialchars($kisi->adi_soyadi ?? 'İsimsiz') ?>
                            </h3>
                            <p class="text-[11px] text-slate-500 font-medium truncate mb-1">
                                <?= htmlspecialchars($gorev ?: $departman) ?>
                            </p>
                            
                            <div class="flex items-center gap-3 mt-1 text-[11px]">
                                <?php if (!empty($tel)): ?>
                                <a href="tel:<?= htmlspecialchars($tel) ?>" class="flex items-center gap-1 text-slate-600 dark:text-slate-400 font-semibold bg-slate-50 dark:bg-slate-800/50 px-2 py-0.5 rounded-md hover:text-indigo-600 transition-colors" onclick="event.stopPropagation()">
                                    <span class="material-symbols-outlined text-[13px]">call</span>
                                    <?= htmlspecialchars($tel) ?>
                                </a>
                                <?php endif; ?>
                                
                                <span class="flex items-center gap-1 text-slate-600 dark:text-slate-400 font-semibold bg-slate-50 dark:bg-slate-800/50 px-2 py-0.5 rounded-md">
                                    <span class="material-symbols-outlined text-[13px]">location_on</span>
                                    <span class="max-w-[80px] truncate"><?= htmlspecialchars($kisi->ekip_bolge ?? 'Bölge Yok') ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="noResultMsg" class="hidden bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-slate-400 text-2xl">search_off</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Sonuç Bulunamadı</h3>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Yüzen Personel Ekle Butonu -->
<button onclick="window.location.href='?p=personel-duzenle'" class="fixed bottom-24 right-4 w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg shadow-indigo-600/30 flex items-center justify-center transition-transform active:scale-95 z-40">
    <span class="material-symbols-outlined text-3xl">add</span>
</button>


<script>
    let activeFilter = 'Aktif';

    function filterCards() {
        const input = document.getElementById('personelSearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const searchVal = input.value.toLocaleLowerCase('tr-TR');
        const cards = document.querySelectorAll('.personel-card');
        const noResultMsg = document.getElementById('noResultMsg');
        
        let visibleCount = 0;
        
        if (searchVal.length > 0) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }

        cards.forEach(card => {
            const textContent = card.innerText.toLocaleLowerCase('tr-TR');
            const matchesSearch = searchVal === '' || textContent.includes(searchVal);
            
            // Badge statüsüne göre kontrol
            let matchesBadge = false;
            if (activeFilter === 'Aktif' && card.classList.contains('is-aktif')) {
                matchesBadge = true;
            } else if (activeFilter === 'Pasif' && card.classList.contains('is-pasif')) {
                matchesBadge = true;
            } else if (activeFilter === 'Tümü') {
                matchesBadge = true;
            }

            if (matchesSearch && matchesBadge) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        if (visibleCount === 0 && cards.length > 0) {
            noResultMsg.classList.remove('hidden');
        } else if (cards.length > 0) {
            noResultMsg.classList.add('hidden');
        }
    }

    function filterBadge(tag) {
        // Toggle opacity effect
        const badgeBtns = document.querySelectorAll('.badge-btn');
        activeFilter = activeFilter === tag ? 'Tümü' : tag; // Aynı butona tıklanırsa 'Tümü' olsun
        
        badgeBtns.forEach(btn => {
            const btnTag = btn.querySelector('.text-\\[9px\\]').innerText;
            const innerDiv = btn.querySelector('div');
            if (btnTag === activeFilter) {
                innerDiv.classList.remove('opacity-70', 'border-white/20');
                innerDiv.classList.add('bg-white/40', 'border-white/50');
            } else {
                innerDiv.classList.add('opacity-70', 'border-white/20');
                innerDiv.classList.remove('bg-white/40', 'border-white/50');
            }
        });
        
        filterCards();
    }

    function clearSearch() {
        const input = document.getElementById('personelSearchInput');
        input.value = '';
        input.focus();
        filterCards();
    }
    
    function openMenu(id) {
        window.location.href = `?p=personel-duzenle&id=` + id;
    }
    
    // Uygulama yüklendiğinde aktif personel butonunu vurguluyoruz (default Aktif filtre)
    document.addEventListener('DOMContentLoaded', () => {
        const activeBtnDiv = document.querySelector('.badge-btn:first-child div');
        if(activeBtnDiv) {
            activeBtnDiv.classList.remove('opacity-70', 'border-white/20');
            activeBtnDiv.classList.add('bg-white/40', 'border-white/50');
        }
        filterCards();
    });
</script>
