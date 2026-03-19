<?php
/**
 * Mobil Admin - Profil Sayfası
 * Yönetici bilgileri ve ayarlar
 */
use App\Helper\Helper;
use App\Helper\Date;
use App\Model\UserModel;

$userModel = new UserModel();
$user = $_SESSION['user'] ?? null;
if (!$user) {
    $user = $userModel->find($_SESSION['user_id'] ?? 0);
}

// Fallbacks
$displayName = $user->adi_soyadi ?? $_SESSION['user_full_name'] ?? 'Yönetici';
$email = $user->email_adresi ?? 'email@example.com';
$telefon = $user->telefon ?? 'Belirtilmemiş';
$gorevi = $user->gorevi ?? 'Yönetici';
?>

<div class="flex flex-col min-h-[calc(100vh-85px)] pb-8 mt-4">
    <!-- Profile Card -->
    <section class="px-4 relative z-20">
        <div class="card p-6 text-center shadow-sm border border-slate-100 dark:border-slate-800">
            <div class="relative w-24 h-24 mx-auto mb-4">
                <div class="w-24 h-24 rounded-full bg-primary/10 border-4 border-white dark:border-card-dark overflow-hidden shadow-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-4xl">admin_panel_settings</span>
                </div>
            </div>

            <h2 class="text-xl font-bold text-slate-900 dark:text-white mt-2">
                <?= htmlspecialchars($displayName) ?>
            </h2>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($gorevi) ?></p>

            <div class="flex items-center justify-center gap-2 mt-3">
                <span class="badge badge-primary">Yönetim</span>
                <span class="badge badge-success">Aktif</span>
            </div>
        </div>
    </section>

    <!-- Contact Info -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">İletişim Bilgileri</h3>
        <div class="card overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600">phone</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">Telefon</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?= htmlspecialchars($telefon) ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600">mail</span>
                </div>
                <div class="flex-1 truncate">
                    <p class="text-xs text-slate-500">E-posta</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                        <?= htmlspecialchars($email) ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Settings -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Hızlı Ayarlar</h3>
        <div class="card overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800" id="notification-setting">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600">notifications</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Bildirimler</p>
                    <p class="text-xs text-slate-500" id="notification-status">Durum kontrol ediliyor...</p>
                </div>
                <button type="button" id="notification-toggle-btn" onclick="toggleNotifications()"
                    data-subscribed="false" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold">
                    ...
                </button>
            </div>

            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">dark_mode</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Karanlık Mod</p>
                    <p class="text-xs text-slate-500">Tema değiştir</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="dark-mode-toggle-profile" class="sr-only peer" onchange="toggleDarkMode()">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
                    </div>
                </label>
            </div>

            <div class="flex items-start gap-4 p-4 border-b border-slate-100 dark:border-slate-800" id="theme-color-setting">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-indigo-600">palette</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Tema Rengi</p>
                    <p class="text-xs text-slate-500 mb-3">Uygulama rengini değiştir</p>
                    <div class="flex items-center gap-2.5 flex-wrap" id="theme-color-swatches-profile"></div>
                </div>
            </div>

            <button onclick="Alert.show({title:'Bilgi', content:'Şifre değiştirme işlemi masaüstü panelden yapılmaktadır.', icon:'info'})" class="flex items-center gap-4 p-4 w-full text-left">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600">lock</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Şifre Değiştir</p>
                    <p class="text-xs text-slate-500">Hesap güvenliği</p>
                </div>
                <span class="material-symbols-outlined text-slate-400">chevron_right</span>
            </button>
        </div>
    </section>

    <!-- Mobile Menu Ordering -->
    <section class="px-4 mt-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Menü Sıralaması</h3>
            <button onclick="saveMenuOrder()" class="text-xs font-bold text-primary flex items-center gap-1 bg-primary/10 px-3 py-1.5 rounded-lg active:scale-95 transition-all">
                <span class="material-symbols-outlined text-sm">save</span>
                Kaydet
            </button>
        </div>
        <div class="card overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800">
            <!-- Sabit Ana Sayfa -->
            <?php if (isset($final_sorted_menus['home'])): 
                $homeData = $final_sorted_menus['home']; ?>
            <div class="flex items-center gap-4 p-4 bg-slate-50/50 dark:bg-slate-800/20 border-b border-slate-100 dark:border-slate-800 opacity-80">
                <div class="w-10 h-10 rounded-xl <?= $homeData['color_bg'] ?> flex items-center justify-center">
                    <span class="material-symbols-outlined <?= $homeData['color_icon'] ?>"><?= $homeData['icon'] ?></span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= $homeData['label'] ?></p>
                    <p class="text-[10px] text-slate-400">Sabitlenmiş</p>
                </div>
                <span class="material-symbols-outlined text-slate-300 dark:text-slate-600">lock</span>
            </div>
            <?php endif; ?>

            <div id="sortable-menu" class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php 
                // Index.php'de hazırlanan sıralı menü listesini kullanıyoruz (Ana sayfa hariç)
                foreach ($final_sorted_menus as $mKey => $mData): 
                    if ($mKey === 'home') continue; ?>
                    <div class="flex items-center gap-4 p-4 bg-white dark:bg-card-dark cursor-move" data-id="<?= $mKey ?>">
                        <div class="w-10 h-10 rounded-xl <?= $mData['color_bg'] ?> flex items-center justify-center">
                            <span class="material-symbols-outlined <?= $mData['color_icon'] ?>"><?= $mData['icon'] ?></span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= $mData['label'] ?></p>
                        </div>
                        <span class="material-symbols-outlined text-slate-400">drag_indicator</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-900/50 flex flex-col gap-2">
                <p class="text-[10px] text-slate-400 text-center italic">Sürükleyip bırakarak diğer menülerin yerlerini değiştirebilirsiniz.</p>
                <button onclick="resetMenuOrder()" class="text-xs font-bold text-slate-500 flex items-center justify-center gap-1.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg active:scale-95 transition-all w-full bg-white dark:bg-card-dark">
                    <span class="material-symbols-outlined text-sm">settings_backup_restore</span>
                    Varsayılan Sıralamaya Dön
                </button>
            </div>
        </div>
    </section>

    <!-- Logout Button -->
    <section class="px-4 mt-6">
        <a href="../logout.php" class="w-full card p-4 flex items-center justify-center gap-3 text-red-500 shadow-sm border border-red-100 dark:border-red-900/30 bg-red-50 dark:bg-red-900/10">
            <span class="material-symbols-outlined">logout</span>
            <span class="font-semibold">Çıkış Yap</span>
        </a>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialize dark mode toggle
    const darkModeToggle = document.getElementById('dark-mode-toggle-profile');
    if (darkModeToggle) {
        darkModeToggle.checked = document.documentElement.classList.contains('dark');
    }

    // Initialize notification status
    updateNotificationStatus();

    // Render theme color swatches if Theme module exists, otherwise minimal generic implementation
    if (typeof Theme !== 'undefined' && Theme.renderSwatches) {
        Theme.renderSwatches('theme-color-swatches-profile');
    } else {
        renderThemeSwatchesGen();
    }
});

/** Notification Helpers */
async function updateNotificationStatus() {
    const statusEl = document.getElementById('notification-status');
    const toggleBtn = document.getElementById('notification-toggle-btn');
    if (!statusEl || !toggleBtn) return;

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        statusEl.textContent = 'Desteklenmiyor';
        toggleBtn.textContent = 'Desteklenmiyor';
        toggleBtn.disabled = true;
        toggleBtn.classList.replace('bg-primary', 'bg-slate-300');
        return;
    }

    const registration = await navigator.serviceWorker.getRegistration('../sw.js');
    const subscription = registration ? await registration.pushManager.getSubscription() : null;

    if (Notification.permission === 'denied') {
        statusEl.textContent = 'Engellendi';
        toggleBtn.textContent = 'Ayarı Aç';
        toggleBtn.dataset.subscribed = 'false';
        statusEl.className = 'text-xs text-red-500';
    } else if (subscription) {
        statusEl.textContent = 'Aktif';
        toggleBtn.textContent = 'Kapat';
        toggleBtn.dataset.subscribed = 'true';
        toggleBtn.classList.replace('bg-primary', 'bg-red-500');
        statusEl.className = 'text-xs text-green-500';
    } else {
        statusEl.textContent = 'Kapalı';
        toggleBtn.textContent = 'Aç';
        toggleBtn.dataset.subscribed = 'false';
        toggleBtn.classList.replace('bg-red-500', 'bg-primary');
        statusEl.className = 'text-xs text-slate-500';
    }
}

async function toggleNotifications() {
    const toggleBtn = document.getElementById('notification-toggle-btn');
    const isSubscribed = toggleBtn.dataset.subscribed === 'true';

    if (isSubscribed) {
        // Unsubscribe logic
        try {
            const registration = await navigator.serviceWorker.getRegistration('../sw.js');
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
                // Optionally call API to remove from DB
                await $.ajax({
                    url: '../views/gorevler/api.php',
                    type: 'POST',
                    data: { action: 'remove-subscription', endpoint: subscription.endpoint }
                });
            }
            updateNotificationStatus();
        } catch (e) {
            console.error('Unsubscribe error:', e);
        }
    } else {
        // Subscribe logic using existing PushConfig if available, or manual
        if (typeof PushConfig !== 'undefined') {
            await PushConfig.askPermission();
            // Wait a bit for subscription to happen
            setTimeout(updateNotificationStatus, 1000);
        } else {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                // If PushConfig is missing, we'd need its logic here, 
                // but index.php includes push-config.js
                location.reload(); 
            }
        }
    }
}

function renderThemeSwatchesGen() {
    const container = document.getElementById('theme-color-swatches-profile');
    if (!container) return;
    
    const themes = window.__themeColors || {
        blue:   { primary: '#135bec' },
        purple: { primary: '#5156be' },
        green:  { primary: '#059669' },
        red:    { primary: '#f46a6a' },
        orange: { primary: '#f97316' },
        teal:   { primary: '#0d9488' },
        ersan:  { primary: '#e2bd61' },
        slate:  { primary: '#252526' }
    };
    
    let html = '';
    const activeColor = window.__activeThemeName || 'blue';
    
    for (const [name, colors] of Object.entries(themes)) {
        const isActive = name === activeColor;
        html += `
            <button class="w-8 h-8 rounded-full flex items-center justify-center transition-transform active:scale-90 ${isActive ? 'ring-2 ring-offset-2 ring-slate-400 dark:ring-slate-500' : ''}" 
                style="background-color: ${colors.primary};" 
                onclick="changeThemeColorGen('${name}')">
                ${isActive ? '<span class="material-symbols-outlined text-white text-sm">check</span>' : ''}
            </button>
        `;
    }
    container.innerHTML = html;
}

function changeThemeColorGen(themeName) {
    if (!window.__themeColors || !window.__themeColors[themeName]) return;
    
    const t = window.__themeColors[themeName];
    const r = document.documentElement;
    
    r.style.setProperty('--primary', t.primary);
    if(t.dark) r.style.setProperty('--primary-dark', t.dark);
    if(t.light) r.style.setProperty('--primary-light', t.light);
    
    const hex = t.primary.replace('#', '');
    r.style.setProperty('--primary-rgb', parseInt(hex.substring(0,2),16) + ', ' + parseInt(hex.substring(2,4),16) + ', ' + parseInt(hex.substring(4,6),16));
    
    localStorage.setItem('themeColor', themeName);
    window.__activeThemeName = themeName;
    
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', t.primary);
    
    renderThemeSwatchesGen();
}

/** Menu Sorting */
function initMenuSortable() {
    const el = document.getElementById('sortable-menu');
    if (!el) return;
    
    // Load SortableJS dynamically if not present
    if (typeof Sortable === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
        script.onload = () => {
            new Sortable(el, {
                animation: 150,
                ghostClass: 'bg-primary/5',
                handle: '.cursor-move'
            });
        };
        document.head.appendChild(script);
    } else {
        new Sortable(el, {
            animation: 150,
            ghostClass: 'bg-primary/5',
            handle: '.cursor-move'
        });
    }
}

async function saveMenuOrder() {
    const el = document.getElementById('sortable-menu');
    if (!el) return;

    const items = el.querySelectorAll('[data-id]');
    const order = Array.from(items).map(item => item.dataset.id).join(',');

    Loading.show();
    try {
        const response = await $.ajax({
            url: '../views/profil/api.php',
            type: 'POST',
            data: { 
                action: 'save-mobile-menu-order', 
                order: order 
            },
            dataType: 'json'
        });

        if (response.status === 'success') {
            Alert.success('Başarılı', 'Menü sıralaması kaydedildi. Uygulama yenileniyor...');
            setTimeout(() => location.reload(), 1500);
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    } catch (e) {
        console.error('Save menu order error:', e);
        Alert.error('Hata', 'Sunucu ile iletişim kurulamadı.');
    } finally {
        Loading.hide();
    }
}

async function resetMenuOrder() {
    const isConfirmed = await Alert.confirm('Emin misiniz?', 'Menü sıralaması varsayılana döndürülecektir.', 'Evet, Sıfırla');
    if (!isConfirmed) return;

    Loading.show();
    try {
        const response = await $.ajax({
            url: '../views/profil/api.php',
            type: 'POST',
            data: { 
                action: 'reset-mobile-menu-order' 
            },
            dataType: 'json'
        });

        if (response.status === 'success') {
            Alert.success('Başarılı', response.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    } catch (e) {
        console.error('Reset menu order error:', e);
        Alert.error('Hata', 'Sunucu ile iletişim kurulamadı.');
    } finally {
        Loading.hide();
    }
}

// Call init on load
document.addEventListener('DOMContentLoaded', initMenuSortable);
</script>
