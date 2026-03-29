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
use App\Model\PersonelGirisLogModel;
use App\Model\PersonelIcralariModel;

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

                // Log the automatic login
                try {
                    $girisLogModel = new PersonelGirisLogModel();
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $girisLogModel->logLogin($personel->id, $ip, $userAgent);

                    // Log zamanını kaydet
                    $_SESSION['last_pwa_login_log_time'] = time();
                } catch (\Exception $e) {
                    error_log("PWA Auto-Login log error: " . $e->getMessage());
                }

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

// Uzun süreli oturumlarda periyodik loglama (4 saatte bir)
// Kullanıcı aktif olduğu sürece "Giriş" logu atarak takip edilebilirliği artırıyoruz.
$lastLogTime = $_SESSION['last_pwa_login_log_time'] ?? 0;
$logInterval = 3600 * 4; // 4 saat

if ((time() - $lastLogTime) > $logInterval) {
    try {
        $girisLogModel = new PersonelGirisLogModel();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $girisLogModel->logLogin($personel->id, $ip, $userAgent);

        $_SESSION['last_pwa_login_log_time'] = time();
    } catch (\Exception $e) {
        // Log hatası akışı bozmamalı
    }
}

// Firma ID'yi oturuma ekle (Model'ler için gerekli)
if (!isset($_SESSION['firma_id']) && isset($personel->firma_id)) {
    $_SESSION['firma_id'] = $personel->firma_id;
}

// Departman & Görev bazlı kontrol
$isEndeksOkuma = (stripos($personel->departman ?? '', 'Endeks Okuma') !== false);
$isSayacSokmeTakma = (stripos($personel->departman ?? '', 'Sayaç Sökme Takma') !== false);
$isBuro = (stripos($personel->departman ?? '', 'BÜRO') !== false || stripos($personel->departman ?? '', 'Büro') !== false);
$isSef = (stripos($personel->gorev ?? '', 'Şef') !== false);
$isKesmeAcma = (stripos($personel->departman ?? '', 'Kesme-Açma') !== false || stripos($personel->departman ?? '', 'Kesme Açma') !== false);
$isEkipSefi = false;

if ($isEndeksOkuma && $isSef) {
    $ekipGecmisiList = $PersonelModel->getEkipGecmisi($personel_id);
    foreach ($ekipGecmisiList as $g) {
        if (($g->ekip_sefi_mi ?? 0) == 1 && (empty($g->bitis_tarihi) || $g->bitis_tarihi >= date('Y-m-d'))) {
            $isEkipSefi = true;
            break;
        }
    }
}

// Personelin icrası var mı kontrol et
$PersonelIcralariModel = new PersonelIcralariModel();
$devamEdenIcralar = $PersonelIcralariModel->getDevamEdenIcralar($personel_id);
$hasIcra = count($devamEdenIcralar) > 0;

// Sayfa yönlendirmesi
$page = $_GET['page'] ?? 'ana-sayfa';
$allowed_pages = ['ana-sayfa', 'bordro', 'izin', 'talep', 'profil', 'puantaj', 'etkinlikler', 'zimmetler', 'icralar', 'yardim', 'yardim-detay'];

if ($isEndeksOkuma && $isEkipSefi) {
    $allowed_pages[] = 'ekip-takibi';
}

if ($isKesmeAcma) {
    $allowed_pages[] = 'nobet';
}

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
                purple: { primary: '#5156be', dark: '#414598', light: '#868ae0' },
                green: { primary: '#059669', dark: '#047857', light: '#34d399' },
                red: { primary: '#f46a6a', dark: '#c35555', light: '#f89a9a' },
                orange: { primary: '#f97316', dark: '#c75c12', light: '#fb9a5c' },
                pink: { primary: '#db2777', dark: '#be185d', light: '#f472b6' },
                teal: { primary: '#0d9488', dark: '#0a766d', light: '#3dbbb1' },
                cyan: { primary: '#06b6d4', dark: '#0592aa', light: '#38d1e9' },
                emerald: { primary: '#10b981', dark: '#0d9467', light: '#40d5a7' },
                rose: { primary: '#ec003f', dark: '#bc0032', light: '#f24d79' },
                ersan: { primary: '#e2bd61', dark: '#b5974d', light: '#ebcc85' },
                slate: { primary: '#252526', dark: '#1e1e1f', light: '#4d4d4f' },
            };
            let saved = localStorage.getItem('themeColor') || 'blue';
            let t = themes[saved] || themes.blue;

            if (saved === 'custom') {
                const customColor = localStorage.getItem('customThemeColor');
                if (customColor) {
                    t = { primary: customColor, dark: customColor, light: customColor };
                }
            }

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
                        "primary": "var(--primary)",
                        "primary-dark": "var(--primary-dark)",
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
        <?php if ($isBuro): ?>
            <a href="?page=izin"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'izin' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span class="material-symbols-outlined <?php echo $page === 'izin' ? 'filled' : ''; ?>">calendar_today</span>
                <span class="text-[10px] font-semibold">İzinler</span>
            </a>
        <?php else: ?>
            <a href="?page=puantaj"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'puantaj' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span class="material-symbols-outlined <?php echo $page === 'puantaj' ? 'filled' : ''; ?>">checklist</span>
                <span class="text-[10px] font-semibold">İş Takibi</span>
            </a>
        <?php endif; ?>
        <?php if ($isEndeksOkuma && $isEkipSefi): ?>
            <a href="?page=ekip-takibi"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'ekip-takibi' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span class="material-symbols-outlined <?php echo $page === 'ekip-takibi' ? 'filled' : ''; ?>">groups</span>
                <span class="text-[10px] font-semibold">Ekip Takibi</span>
            </a>
        <?php elseif ($isKesmeAcma): ?>
            <a href="?page=nobet"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'nobet' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span class="material-symbols-outlined <?php echo $page === 'nobet' ? 'filled' : ''; ?>">nights_stay</span>
                <span class="text-[10px] font-semibold">Nöbet Takibi</span>
            </a>
        <?php else: ?>
            <a href="?page=zimmetler"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'zimmetler' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span
                    class="material-symbols-outlined <?php echo $page === 'zimmetler' ? 'filled' : ''; ?>">inventory_2</span>
                <span class="text-[10px] font-semibold">Zimmetler</span>
            </a>
        <?php endif; ?>
        <a href="?page=talep"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'talep' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'talep' ? 'filled' : ''; ?>">assignment</span>
            <span class="text-[10px] font-semibold">Talepler</span>
        </a>
        <?php
        $moreActivePages = ['profil', 'etkinlikler', 'bordro', 'izin'];
        $isZimmetInBottomNav = !(($isEndeksOkuma && $isEkipSefi) || $isKesmeAcma);
        // Zimmetler zaten bottom nav'da gösteriliyorsa diğer menüde highlight etme
        if (!$isZimmetInBottomNav) {
            $moreActivePages[] = 'zimmetler';
        }
        ?>
        <button type="button" onclick="toggleMoreMenu()"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo in_array($page, $moreActivePages) ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span
                class="material-symbols-outlined <?php echo in_array($page, $moreActivePages) ? 'filled' : ''; ?>">more_horiz</span>
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

                <a href="?page=profil"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'profil' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-lg">person</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Profil</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>

                <a href="?page=yardim"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'yardim' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-indigo-600 text-lg">support_agent</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Yardım & Destek</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>

                <a href="?page=etkinlikler"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'etkinlikler' ? 'bg-primary/10' : ''; ?>">
                    <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-600 text-lg">event</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Etkinlikler</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <a href="?page=bordro"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'bordro' ? 'bg-primary/10' : ''; ?>">
                    <div
                        class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">payments</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">Avans</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <a href="?page=izin"
                    class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'izin' ? 'bg-primary/10' : ''; ?>">
                    <div
                        class="w-9 h-9 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-orange-600 text-lg">calendar_today</span>
                    </div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm">İzinler</span>
                    <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                </a>
                <?php if ($hasIcra): ?>
                    <a href="?page=icralar"
                        class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'icralar' ? 'bg-primary/10' : ''; ?>">
                        <div class="w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-rose-600 text-lg">gavel</span>
                        </div>
                        <span class="font-medium text-slate-900 dark:text-white text-sm">İcralarım</span>
                        <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                    </a>
                <?php endif; ?>
                <?php if (!$isZimmetInBottomNav): ?>
                    <a href="?page=zimmetler"
                        class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?php echo $page === 'zimmetler' ? 'bg-primary/10' : ''; ?>">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600 text-lg">inventory_2</span>
                        </div>
                        <span class="font-medium text-slate-900 dark:text-white text-sm">Zimmetler</span>
                        <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
                    </a>
                <?php endif; ?>
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

    <!-- Etkinlik Detay Tam Ekran Modal (Full Screen Layout) -->
    <div id="etkinlik-detay-fullscreen"
        class="fixed inset-0 z-[120] bg-slate-50 dark:bg-background-dark transform translate-y-full transition-transform duration-300 flex flex-col"
        style="display:none;">
        <!-- Close Button (Fixed at Bottom Right) -->
        <button onclick="closeEtkinlikFullScreen()"
            class="fixed bottom-6 right-6 w-14 h-14 rounded-full bg-primary text-white shadow-2xl flex items-center justify-center z-[130] active:scale-90 transition-transform cursor-pointer border-none outline-none">
            <span class="material-symbols-outlined text-3xl">close</span>
        </button>

        <!-- Container for dynamic content -->
        <div id="etkinlik-fullscreen-content" class="flex-1 overflow-y-auto flex flex-col disable-scrollbar pb-24">
        </div>
    </div>

    <!-- Scripts -->
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/pwa-app.js?v=<?= time() ?>"></script>
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

    <!-- ETKİNLİK DETAY TAM EKRAN MODAL MANTIĞI -->
    <script>
        function showEtkinlikFullScreen(duyuruStr) {
            let duyuru;
            try {
                // Determine if it was passed as JSON string or object directly
                duyuru = typeof duyuruStr === 'string' ? JSON.parse(duyuruStr) : duyuruStr;
            } catch (e) {
                console.error('JSON Parse error', e);
                return;
            }

            const modal = document.getElementById('etkinlik-detay-fullscreen');
            const container = document.getElementById('etkinlik-fullscreen-content');

            // Apply selected UI themes as background for the header
            const bgImg = `background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);`;

            const hasImage = duyuru.resim ? true : false;
            let fileAttachmentHtml = '';
            if (hasImage) {
                fileAttachmentHtml = `
                    <div class="mt-8">
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 pl-1">EKLİ GÖRSEL / DOSYA</p>
                        <div class="rounded-2xl overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800 relative bg-slate-100 dark:bg-slate-800">
                            <img src="${escapeHtml(duyuru.resim)}" class="w-full h-auto object-cover max-h-[400px]" alt="Etkinlik Görseli">
                        </div>
                    </div>
                `;
            }

            let linkHtml = '';
            if (duyuru.hedef_sayfa) {
                linkHtml = `
                <div class="mt-8">
                    <a href="${escapeHtml(duyuru.hedef_sayfa)}" class="btn-primary w-full py-4 text-center rounded-2xl flex justify-center items-center gap-2 font-bold shadow-lg shadow-primary/30 active:scale-95 transition-transform">
                        <span>İlgili Sayfaya Git</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>
                `;
            }

            let kalanGunHtml = '';
            if (duyuru.kalan_gun) {
                kalanGunHtml = `
                    <div class="absolute -top-4 -right-2 pointer-events-none select-none z-0 flex flex-col items-end opacity-20">
                        <span class="text-[12rem] font-black leading-[0.8] tracking-tighter bg-gradient-to-bl from-white to-transparent text-transparent bg-clip-text">${escapeHtml(duyuru.kalan_gun)}</span>
                    </div>
                `;
            } else if (duyuru.gecmis) {
                kalanGunHtml = `<div class="mt-4"><span class="badge badge-danger bg-red-500/80 backdrop-blur-md text-white border-none py-1.5 px-3 shadow-md">Geçmiş Etkinlik</span></div>`;
            }

            container.innerHTML = `
                <div class="header-main relative px-6 pt-10 pb-8 flex flex-col items-start shadow-xl rounded-b-[2.5rem] safe-area-top shrink-0 overflow-hidden" style="${bgImg}">
                    <div class="absolute inset-0 opacity-10 overflow-hidden rounded-b-[2.5rem] pointer-events-none">
                        <svg class="absolute -right-4 top-0 w-32 h-32" viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="50" />
                        </svg>
                        <svg class="absolute -left-12 -bottom-12 w-48 h-48" viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="50" />
                        </svg>
                    </div>
                    
                    ${kalanGunHtml}

                    <div class="relative w-full z-10 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-6">
                            <div class="w-10 h-10"></div> <!-- Placeholder for layout balance -->
                            <span class="bg-white/20 backdrop-blur-md border border-white/10 text-white rounded-lg px-3 py-1 text-[11px] font-semibold tracking-wide shadow-sm">${escapeHtml(duyuru.tarih)}</span>
                        </div>

                        <div class="flex flex-col justify-end mt-2">
                            <h1 class="text-white text-2xl font-black tracking-tight leading-[1.15] break-words" style="text-shadow: 0 4px 8px rgba(0,0,0,0.5);">${escapeHtml(duyuru.baslik)}</h1>
                            ${duyuru.kalan_gun ? `<div class="mt-2 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                <span class="text-[11px] font-bold text-white/90 uppercase tracking-[0.2em]">${escapeHtml(duyuru.kalan_gun)} GÜN KALDI</span>
                            </div>` : ''}
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-8 flex-1 bg-transparent -mt-5 relative z-20">
                    <div class="bg-white dark:bg-card-dark rounded-[2rem] p-6 shadow-xl shadow-black/5 dark:shadow-black/20 border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-700 dark:text-slate-300 text-[15px] leading-relaxed whitespace-pre-wrap">${escapeHtml(duyuru.icerik)}</p>
                        ${fileAttachmentHtml}
                    </div>
                    ${linkHtml}
                </div>
            `;

            modal.style.display = 'flex';
            // Allow DOM state update before kicking off CSS transitions
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    modal.classList.remove('translate-y-full');
                });
            });

            try {
                window.history.pushState({ modal: 'etkinlik-detay-fullscreen' }, '', '');
            } catch (err) { }
        }

        function closeEtkinlikFullScreen() {
            const modal = document.getElementById('etkinlik-detay-fullscreen');
            if (!modal) return;

            modal.classList.add('translate-y-full');
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('etkinlik-fullscreen-content').innerHTML = ''; // memory clean
            }, 300); // match transition duration
        }

        // Catch the native back button
        window.addEventListener('popstate', function (e) {
            const modalFS = document.getElementById('etkinlik-detay-fullscreen');
            if (modalFS && modalFS.style.display !== 'none') {
                closeEtkinlikFullScreen();
            }
        });
    </script>
</body>

</html>