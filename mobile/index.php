<?php
/**
 * Mobil Admin Arayüzü
 * Admin kullanıcıları için mobil öncelikli yönetim paneli
 * CSS/tasarım sistemi views/personel-pwa'dan alınmıştır.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Proje kök dizinine geç (view include'larının relative PHP path'leri için)
chdir(dirname(__DIR__));

session_start();

// Auth check
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($currentUserId <= 0 || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['firma_id'])) {
    header("Location: ../firma-secim.php");
    exit();
}

// Masaüstü görünümüne geç
if (isset($_GET['force_desktop'])) {
    $_SESSION['force_desktop'] = true;
    $p = urlencode($_GET['p'] ?? 'home');
    header("Location: ../index.php?p=$p");
    exit();
}

// Sayfa yönlendirmesi — yalnızca güvenli karakter kümesine izin ver
$raw_page = $_GET['p'] ?? 'home';
$page     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($raw_page));

$allowed_pages = ['home', 'personel', 'arac', 'gorevler', 'talepler', 'raporlar'];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

$page_file = __DIR__ . '/pages/' . $page . '.php';

$page_titles = [
    'home'     => 'Ana Sayfa',
    'personel' => 'Personel',
    'arac'     => 'Araç Takip',
    'gorevler' => 'Görevler',
    'talepler' => 'Talepler',
    'raporlar' => 'Raporlar',
];

$currentTitle = $page_titles[$page] ?? 'Ana Sayfa';

$nav_items = [
    ['page' => 'home',     'label' => 'Ana Sayfa', 'icon' => 'home'],
    ['page' => 'personel', 'label' => 'Personel',  'icon' => 'group'],
    ['page' => 'gorevler', 'label' => 'Görevler',  'icon' => 'task_alt'],
    ['page' => 'arac',     'label' => 'Araç',      'icon' => 'directions_car'],
];

$more_pages   = ['talepler', 'raporlar'];
$isMoreActive = in_array($page, $more_pages);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#135bec">
    <title>Ersan Elektrik | Yönetim</title>

    <!-- Google Fonts & Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Tema & Dark Mode ön yüklemesi (personel-pwa ile aynı) -->
    <script>
        (function () {
            const themes = {
                blue:   { primary: '#135bec', dark: '#0d47c1', light: '#4a87f5' },
                purple: { primary: '#5156be', dark: '#414598', light: '#868ae0' },
                green:  { primary: '#059669', dark: '#047857', light: '#34d399' },
                red:    { primary: '#f46a6a', dark: '#c35555', light: '#f89a9a' },
                orange: { primary: '#f97316', dark: '#c75c12', light: '#fb9a5c' },
                teal:   { primary: '#0d9488', dark: '#0a766d', light: '#3dbbb1' },
                ersan:  { primary: '#e2bd61', dark: '#b5974d', light: '#ebcc85' },
                slate:  { primary: '#252526', dark: '#1e1e1f', light: '#4d4d4f' },
            };
            const saved = localStorage.getItem('themeColor') || 'blue';
            const t     = themes[saved] || themes.blue;
            window.__themeColors    = themes;
            window.__activeTheme    = t;
            window.__activeThemeName = saved;
            const r = document.documentElement;
            r.style.setProperty('--primary',       t.primary);
            r.style.setProperty('--primary-dark',  t.dark);
            r.style.setProperty('--primary-light', t.light);
            const hex = t.primary.replace('#', '');
            r.style.setProperty('--primary-rgb', `${parseInt(hex.substring(0,2),16)}, ${parseInt(hex.substring(2,4),16)}, ${parseInt(hex.substring(4,6),16)}`);
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', t.primary);
            if (localStorage.getItem('darkMode') === 'true') r.classList.add('dark');
        })();
    </script>

    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary":          "var(--primary)",
                        "primary-dark":     "var(--primary-dark)",
                        "background-light": "#f6f6f8",
                        "background-dark":  "#121212",
                        "card-dark":        "#1e1e1e",
                    },
                    fontFamily: {
                        "display": ["Roboto Condensed", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem",
                        "2xl": "1rem", "3xl": "1.5rem", "full": "9999px"
                    },
                }
            }
        }
    </script>

    <!-- PWA stillerini personel-pwa'dan doğrudan yeniden kullan -->
    <link rel="stylesheet" href="../views/personel-pwa/assets/css/pwa-style.css?v=<?= time() ?>">

    <style>
        :root {
            --primary:       #135bec;
            --primary-dark:  #0d47c1;
            --primary-light: #4a87f5;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }
        .safe-area-bottom {
            padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem);
        }
        .h-safe-top {
            height: env(safe-area-inset-top, 0px);
        }
        .pb-nav {
            padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
        }
        /* Dolu ikonlar için Material Symbols */
        .filled { font-variation-settings: 'FILL' 1; }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-white min-h-screen pb-nav">

    <!-- iOS Güvenli Alan Boşluğu -->
    <div class="h-safe-top bg-primary dark:bg-primary-dark"></div>

    <!-- Üst Başlık -->
    <header class="sticky top-0 z-40 bg-white dark:bg-card-dark border-b border-slate-100 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3">
            <h1 class="text-base font-bold text-slate-900 dark:text-white">
                <?= htmlspecialchars($currentTitle) ?>
            </h1>
            <div class="flex items-center gap-1">
                <button onclick="toggleDarkMode()"
                    class="w-9 h-9 rounded-full flex items-center justify-center text-slate-500 dark:text-slate-300 active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-xl dark:hidden">dark_mode</span>
                    <span class="material-symbols-outlined text-xl hidden dark:block">light_mode</span>
                </button>
                <a href="../logout.php"
                    class="w-9 h-9 rounded-full flex items-center justify-center text-slate-500 dark:text-slate-300 active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-xl">logout</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Ana İçerik -->
    <main id="main-content" class="min-h-screen">
        <?php if (file_exists($page_file)): ?>
            <?php include $page_file; ?>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center min-h-[60vh] px-6 text-center">
                <span class="material-symbols-outlined text-6xl text-slate-300 dark:text-slate-600 mb-4">construction</span>
                <h2 class="text-xl font-bold text-slate-700 dark:text-slate-300 mb-2">Bu Sayfa Hazırlanıyor</h2>
                <p class="text-slate-500 dark:text-slate-400 mb-6 text-sm">Masaüstü görünümünden erişebilirsiniz.</p>
                <div class="flex gap-3">
                    <a href="?p=home"
                        class="px-5 py-2.5 bg-primary text-white rounded-full font-semibold text-sm active:scale-95 transition-transform">
                        Ana Sayfaya Dön
                    </a>
                    <a href="?force_desktop=1&p=<?= urlencode($page) ?>"
                        class="px-5 py-2.5 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-white rounded-full font-semibold text-sm active:scale-95 transition-transform">
                        Masaüstü Görünümü
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Alt Navigasyon -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark border-t border-slate-200 dark:border-slate-700 safe-area-bottom z-50 shadow-lg">
        <div class="flex items-center justify-around px-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="?p=<?= $item['page'] ?>"
                    class="nav-item flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all <?= $page === $item['page'] ? 'text-primary' : 'text-slate-400 dark:text-slate-500' ?>">
                    <span class="material-symbols-outlined text-[26px] <?= $page === $item['page'] ? 'filled' : '' ?>"><?= $item['icon'] ?></span>
                    <span class="text-[10px] font-semibold"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
            <button type="button" onclick="toggleMoreMenu()"
                class="nav-item flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all <?= $isMoreActive ? 'text-primary' : 'text-slate-400 dark:text-slate-500' ?>">
                <span class="material-symbols-outlined text-[26px] <?= $isMoreActive ? 'filled' : '' ?>">more_horiz</span>
                <span class="text-[10px] font-semibold">Daha Fazla</span>
            </button>
        </div>
    </nav>

    <!-- Daha Fazla Overlay -->
    <div id="more-menu-overlay"
        class="fixed inset-0 bg-black/50 z-[60] opacity-0 pointer-events-none transition-opacity duration-300"
        onclick="closeMoreMenu()"></div>

    <!-- Daha Fazla Bottom Sheet -->
    <div id="more-menu-sheet"
        class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-2xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom">
        <div class="flex justify-center pt-2 pb-1">
            <div class="w-8 h-1 bg-slate-300 dark:bg-slate-600 rounded-full"></div>
        </div>
        <div class="px-4 pb-4">

            <!-- Masaüstü Görünümü -->
            <a href="?force_desktop=1&p=<?= urlencode($page) ?>"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors mb-1">
                <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-lg">desktop_windows</span>
                </div>
                <div>
                    <span class="font-medium text-slate-900 dark:text-white text-sm block">Masaüstü Görünümü</span>
                    <span class="text-xs text-slate-400">Tam sürüme geç</span>
                </div>
                <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
            </a>

            <div class="h-px bg-slate-100 dark:bg-slate-700 my-2"></div>

            <a href="?p=talepler"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?= $page === 'talepler' ? 'bg-primary/10' : '' ?>">
                <div class="w-9 h-9 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-orange-600 text-lg">assignment</span>
                </div>
                <span class="font-medium text-slate-900 dark:text-white text-sm">Talepler</span>
                <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
            </a>

            <a href="?p=raporlar"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors <?= $page === 'raporlar' ? 'bg-primary/10' : '' ?>">
                <div class="w-9 h-9 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600 text-lg">bar_chart</span>
                </div>
                <span class="font-medium text-slate-900 dark:text-white text-sm">Raporlar</span>
                <span class="material-symbols-outlined text-slate-400 ml-auto text-lg">chevron_right</span>
            </a>

            <div class="h-px bg-slate-100 dark:bg-slate-700 my-2"></div>

            <a href="../logout.php"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600 text-lg">logout</span>
                </div>
                <span class="font-medium text-red-600 text-sm">Çıkış Yap</span>
            </a>

        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleMoreMenu() {
            const overlay = document.getElementById('more-menu-overlay');
            const sheet   = document.getElementById('more-menu-sheet');
            const isOpen  = !overlay.classList.contains('pointer-events-none');
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
            const sheet   = document.getElementById('more-menu-sheet');
            overlay.classList.add('pointer-events-none', 'opacity-0');
            overlay.classList.remove('opacity-100');
            sheet.classList.add('translate-y-full');
        }

        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? 'true' : 'false');
        }
    </script>

</body>
</html>
