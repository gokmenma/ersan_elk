<?php
/**
 * Personel PWA - Ana Giriş Noktası
 * Mobil uygulama benzeri personel portalı
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\PersonelModel;

// Oturum kontrolü öncesi beni hatırla kontrolü
if (!isset($_SESSION['personel_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $parts = explode(':', base64_decode($token));

    if (count($parts) === 2) {
        $p_id = $parts[0];
        $hash = $parts[1];

        $PersonelModel = new PersonelModel();
        $personel = $PersonelModel->find($p_id);

        if ($personel) {
            $checkHash = hash_hmac('sha256', $personel->id . $personel->sifre, 'ErsanElektrikPWASecretKey');
            if ($hash === $checkHash) {
                $_SESSION['personel_id'] = $personel->id;
                $_SESSION['personel_tc'] = $personel->tc_kimlik_no;
                $_SESSION['personel_adi'] = $personel->adi_soyadi;
                // Cookie süresini uzat
                setcookie('remember_token', $token, time() + (86400 * 30), "/");
            }
        }
    }
}

// Oturum kontrolü
if (!isset($_SESSION['personel_id'])) {
    header("Location: login.php");
    exit();
}

$personel_id = $_SESSION['personel_id'];
$PersonelModel = new PersonelModel();
$personel = $PersonelModel->find($personel_id);

// Personel bulunamazsa oturumu kapat ve login'e yönlendir
if (!$personel) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Sayfa yönlendirmesi
$page = $_GET['page'] ?? 'ana-sayfa';
$allowed_pages = ['ana-sayfa', 'bordro', 'izin', 'talep', 'profil', 'puantaj', 'nobet', 'etkinlikler'];

if (!in_array($page, $allowed_pages)) {
    $page = 'ana-sayfa';
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#135bec">
    <meta name="description" content="Ersan Elektrik - Mobil Uygulama">

    <title>Ersan Elektrik | Personel Yönetimi</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192-new.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-192-new.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Theme & Dark Mode Pre-init -->
    <script>
        (function () {
            const themes = {
                blue: { primary: '#135bec', dark: '#0d47c1', light: '#4a87f5' },
                purple: { primary: '#7c3aed', dark: '#6d28d9', light: '#a78bfa' },
                green: { primary: '#059669', dark: '#047857', light: '#34d399' },
                red: { primary: '#dc2626', dark: '#b91c1c', light: '#f87171' },
                orange: { primary: '#ea580c', dark: '#c2410c', light: '#fb923c' },
                pink: { primary: '#db2777', dark: '#be185d', light: '#f472b6' },
                teal: { primary: '#0d9488', dark: '#0f766e', light: '#2dd4bf' },
                slate: { primary: '#475569', dark: '#334155', light: '#94a3b8' },
            };
            const saved = localStorage.getItem('themeColor') || 'blue';
            const t = themes[saved] || themes.blue;
            window.__themeColors = themes;
            window.__activeTheme = t;
            window.__activeThemeName = saved;

            // Apply CSS variables
            const r = document.documentElement;
            r.style.setProperty('--primary', t.primary);
            r.style.setProperty('--primary-dark', t.dark);
            r.style.setProperty('--primary-light', t.light);

            // Compute RGB triplet for rgba() usage (chat widget etc.)
            const hex = t.primary.replace('#', '');
            const pr = parseInt(hex.substring(0, 2), 16);
            const pg = parseInt(hex.substring(2, 4), 16);
            const pb = parseInt(hex.substring(4, 6), 16);
            r.style.setProperty('--primary-rgb', `${pr}, ${pg}, ${pb}`);

            // Update meta theme-color
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', t.primary);

            // Dark mode
            if (localStorage.getItem('darkMode') === 'true') {
                r.classList.add('dark');
            }
        })();
    </script>

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": window.__activeTheme.primary,
                        "primary-dark": window.__activeTheme.dark,
                        "background-light": "#f6f6f8",
                        "background-dark": "#121212",
                        "card-dark": "#1e1e1e",
                    },
                    fontFamily: {
                        "display": ["Roboto Condensed", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "3xl": "1.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/pwa-style.css?v=<?= time() ?>">
    <?php
    // Canlı destek ayar kontrolü
    $_pwaSettingsModel = new \App\Model\SettingsModel();
    $_pwaDestekModel = new \App\Model\DestekModel();
    $_pwaCanliDestekAktif = $_pwaSettingsModel->getSettings('canli_destek_aktif') === '1';

    // Mesai saatleri dışında chati gizle (Eğer aktif/açık bir konuşması yoksa)
    if ($_pwaCanliDestekAktif && !$_pwaDestekModel->isWorkingHours()) {
        $__aktifKonusma = $_pwaDestekModel->getActiveConversation($personel_id);
        if (!$__aktifKonusma) {
            $_pwaCanliDestekAktif = false;
        }
    }

    if ($_pwaCanliDestekAktif): ?>
        <link rel="stylesheet" href="assets/css/pwa-chat.css?v=<?= time() ?>">
    <?php endif; ?>
    <link rel="canonical" href="https://www.personel.softran.online/index.php" />

</head>

<body
    class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-white min-h-screen pb-20">

    <!-- Status Bar Spacer (iOS) -->
    <div class="h-safe-top bg-primary"></div>

    <!-- Main Content -->
    <main id="main-content" class="min-h-screen">
        <?php include "pages/{$page}.php"; ?>
    </main>

    <!-- Bottom Navigation -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark border-t border-slate-200 dark:border-slate-800 flex justify-around items-center py-2 px-4 z-50 safe-area-bottom">
        <a href="?page=ana-sayfa"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'ana-sayfa' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'ana-sayfa' ? 'filled' : ''; ?>">home</span>
            <span class="text-[10px] font-semibold">Ana Sayfa</span>
        </a>
        <a href="?page=bordro"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'bordro' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'bordro' ? 'filled' : ''; ?>">payments</span>
            <span class="text-[10px] font-semibold">Avans</span>
        </a>
        <a href="?page=izin"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'izin' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span
                class="material-symbols-outlined <?php echo $page === 'izin' ? 'filled' : ''; ?>">calendar_today</span>
            <span class="text-[10px] font-semibold">İzinler</span>
        </a>
        <a href="?page=talep"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'talep' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'talep' ? 'filled' : ''; ?>">assignment</span>
            <span class="text-[10px] font-semibold">Talepler</span>
        </a>
        <button type="button" onclick="toggleMoreMenu()"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo in_array($page, ['profil', 'puantaj']) ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span
                class="material-symbols-outlined <?php echo in_array($page, ['profil', 'puantaj']) ? 'filled' : ''; ?>">more_horiz</span>
            <span class="text-[10px] font-semibold">Diğer</span>
        </button>
    </nav>

    <!-- Loading Overlay -->
    <div id="loading-overlay"
        class="fixed inset-0 bg-white dark:bg-background-dark z-[100] flex items-center justify-center transition-all duration-500">
        <div class="loader-container">
            <div class="ekg-loader">
                <svg viewBox="0 0 300 100" class="ekg-svg">
                    <path class="ekg-line-bg"
                        d="M 0 50 L 30 50 L 35 45 L 40 55 L 45 50 L 55 50 L 60 10 L 65 90 L 70 50 L 80 45 L 85 55 L 90 45 L 95 55 L 100 50 L 110 10 L 115 90 L 120 50 L 130 45 L 135 55 L 140 50 L 300 50" />
                    <path class="ekg-line"
                        d="M 0 50 L 30 50 L 35 45 L 40 55 L 45 50 L 55 50 L 60 10 L 65 90 L 70 50 L 80 45 L 85 55 L 90 45 L 95 55 L 100 50 L 110 10 L 115 90 L 120 50 L 130 45 L 135 55 L 140 50 L 300 50" />
                </svg>
            </div>
            <div class="flex flex-col items-center gap-1">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Ersan <span
                        class="text-primary">Elektrik</span></h2>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Yükleniyor...</p>
            </div>
        </div>
    </div>

    <!-- More Menu Bottom Sheet -->
    <div id="more-menu-overlay"
        class="fixed inset-0 bg-black/50 z-[60] opacity-0 pointer-events-none transition-opacity duration-300"
        onclick="closeMoreMenu()"></div>
    <div id="more-menu-sheet"
        class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-2xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom">
        <div class="flex justify-center pt-2 pb-1">
            <div class="w-8 h-1 bg-slate-300 dark:bg-slate-600 rounded-full"></div>
        </div>
        <div class="px-4 pb-4">
            <div class="flex flex-col gap-1">
                <a href="javascript:void(0)" onclick="logout()"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'logout' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-red-600 text-lg">logout</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Çıkış
                        Yap</span>
                </a>
                <a href="?page=etkinlikler"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'etkinlikler' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-600 text-lg">event</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Etkinlikler</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <a href="?page=profil"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'profil' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-lg">person</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Profil</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <a href="?page=puantaj"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'puantaj' ? 'bg-primary/10' : ''; ?>">
                    <div
                        class="w-9 h-9 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-purple-600 text-lg">checklist</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">İş Takip</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <a href="?page=nobet"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'nobet' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-amber-600 text-lg">nights_stay</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Nöbet Takibi</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($_pwaCanliDestekAktif): ?>
        <!-- Floating Chat Button -->
        <button id="chat-fab" onclick="LiveChat.toggle()" class="chat-fab" aria-label="Canlı Destek">
            <span class="chat-fab-icon material-symbols-outlined">chat</span>
            <span class="chat-fab-close material-symbols-outlined">close</span>
            <span id="chat-fab-badge" class="chat-fab-badge" style="display:none;">0</span>
            <span class="chat-fab-pulse"></span>
        </button>

        <!-- Chat Overlay Widget -->
        <div id="chat-overlay" class="chat-overlay">
            <div class="chat-widget">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-header-info">
                        <div class="chat-header-avatar">
                            <span class="material-symbols-outlined">support_agent</span>
                            <span class="chat-online-dot"></span>
                        </div>
                        <div>
                            <h3 class="chat-header-title" id="chat-header-title">Canlı Destek</h3>
                            <p class="chat-header-status" id="chat-status">Çevrimiçi</p>
                        </div>
                    </div>
                    <div class="chat-header-actions" style="display:flex; flex-direction:row; align-items:center; gap:4px;">
                        <button onclick="LiveChat.toggleHistory()" class="chat-header-btn" id="chat-history-btn"
                            aria-label="Geçmiş" title="Konuşma Geçmişi">
                            <span class="material-symbols-outlined">history</span>
                        </button>
                        <button onclick="LiveChat.close()" class="chat-header-btn" aria-label="Kapat">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                <!-- Conversation Action Bar (çözüldü/kapat) -->
                <div id="chat-action-bar"
                    style="display:none; padding:6px 12px; background:#f0f4ff; border-bottom:1px solid #e2e8f0; flex-shrink:0;">
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:6px;">
                        <button onclick="LiveChat.resolveConversation()" id="chat-resolve-btn"
                            style="display:flex; align-items:center; gap:4px; padding:5px 12px; border:none; border-radius:8px; background:#16a34a; color:#fff; font-size:12px; font-weight:600; cursor:pointer; transition:opacity .2s;"
                            title="Konuşmayı çözüldü olarak işaretle">
                            <span class="material-symbols-outlined" style="font-size:16px;">check_circle</span>
                            Çözüldü
                        </button>
                        <button onclick="LiveChat.closeConversation()" id="chat-close-btn"
                            style="display:flex; align-items:center; gap:4px; padding:5px 12px; border:none; border-radius:8px; background:#ef4444; color:#fff; font-size:12px; font-weight:600; cursor:pointer; transition:opacity .2s;"
                            title="Konuşmayı kapat">
                            <span class="material-symbols-outlined" style="font-size:16px;">cancel</span>
                            Kapat
                        </button>
                    </div>
                </div>

                <!-- Conversation History Panel (hidden by default) -->
                <div id="chat-history-panel" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
                    <div
                        style="padding:10px 14px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                        <button onclick="LiveChat.toggleHistory()"
                            style="border:none; background:none; cursor:pointer; color:#64748b; padding:2px;">
                            <span class="material-symbols-outlined" style="font-size:20px;">arrow_back</span>
                        </button>
                        <span style="font-weight:600; font-size:14px; color:#1e293b;">Konuşma Geçmişi</span>
                    </div>
                    <div id="chat-history-list" style="flex:1; overflow-y:auto; padding:8px;"></div>
                </div>

                <!-- Chat Messages (active chat view) -->
                <div id="chat-active-view" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
                    <div class="chat-messages" id="chat-messages">
                        <div class="chat-welcome" id="chat-welcome">
                            <div class="chat-welcome-icon">
                                <span class="material-symbols-outlined">waving_hand</span>
                            </div>
                            <h4>Merhaba!</h4>
                            <p>Size nasıl yardımcı olabiliriz?</p>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="chat-input-area" id="chat-input-area">
                        <div class="chat-input-wrapper">
                            <button onclick="LiveChat.openImagePicker()" class="chat-attach-btn" aria-label="Resim Ekle">
                                <span class="material-symbols-outlined">image</span>
                            </button>
                            <input type="text" id="chat-input" class="chat-input" placeholder="Mesajınızı yazın..."
                                onkeypress="if(event.key==='Enter')LiveChat.send()" autocomplete="off">
                            <button onclick="LiveChat.send()" class="chat-send-btn" id="chat-send-btn" aria-label="Gönder">
                                <span class="material-symbols-outlined">send</span>
                            </button>
                        </div>
                        <input type="file" id="chat-image-input" accept="image/*" style="display:none"
                            onchange="LiveChat.sendImage(this)">
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php endif; /* canli destek */ ?>

    <?php if ($_pwaCanliDestekAktif): ?>
        <!-- Image Preview Modal -->
        <div id="chat-image-preview" class="chat-image-preview" onclick="LiveChat.closeImagePreview()">
            <img id="chat-image-preview-img" src="" alt="Preview">
        </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 left-4 right-4 z-[110] flex flex-col gap-2"></div>

    <!-- Scripts -->
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/pwa-app.js"></script>
    <script src="assets/js/notification-helper.js"></script>
    <?php if ($_pwaCanliDestekAktif): ?>
        <script src="assets/js/pwa-chat.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <!-- More Menu Scripts -->
    <script>
        function toggleMoreMenu() {
            const overlay = document.getElementById('more-menu-overlay');
            const sheet = document.getElementById('more-menu-sheet');

            const isOpen = !overlay.classList.contains('pointer-events-none');

            if (isOpen) {
                closeMoreMenu();
            } else {
                overlay.classList.remove('pointer-events-none', 'opacity-0');
                overlay.classList.add('opacity-100');
                sheet.classList.remove('translate-y-full');
            }
        }

        function closeMoreMenu() {
            const overlay = document.getElementById('more-menu-overlay');
            const sheet = document.getElementById('more-menu-sheet');

            overlay.classList.add('pointer-events-none', 'opacity-0');
            overlay.classList.remove('opacity-100');
            sheet.classList.add('translate-y-full');
        }

        // Swipe down to close
        let startY = 0;
        const moreSheet = document.getElementById('more-menu-sheet');
        if (moreSheet) {
            moreSheet.addEventListener('touchstart', (e) => {
                startY = e.touches[0].clientY;
            });

            moreSheet.addEventListener('touchmove', (e) => {
                const currentY = e.touches[0].clientY;
                const diff = currentY - startY;

                if (diff > 50) {
                    closeMoreMenu();
                }
            });
        }
    </script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration);
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            });
        }
    </script>
    <script>
        async function logout() {
            const isConfirmed = await Alert.confirm(
                'Çıkış Yap',
                'Çıkış yapmak istediğinize emin misiniz?',
                'Çıkış Yap',
                'Vazgeç'
            );

            if (!isConfirmed) return;

            try {
                const response = await API.request('logout');
                window.location.href = 'login.php';
            } catch (error) {
                window.location.href = 'login.php';
            }
        }
    </script>
</body>

</html>