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

// Oturum kontrolü
if (!isset($_SESSION['personel_id'])) {
    header("Location: login.php");
    exit();
}

$personel_id = $_SESSION['personel_id'];
$PersonelModel = new PersonelModel();
$personel = $PersonelModel->find($personel_id);

// Personel bulunamazsa varsayılan değerler kullan
if (!$personel) {
    $personel = (object) [
        'adi_soyadi' => $_SESSION['personel_adi'] ?? 'Personel',
        'foto' => '',
        'pozisyon' => '',
        'departman' => '',
        'tc_no' => '',
        'dogum_tarihi' => '',
        'ise_baslama_tarihi' => '',
        'telefon' => '',
        'email' => '',
        'adres' => ''
    ];
}

// Sayfa yönlendirmesi
$page = $_GET['page'] ?? 'ana-sayfa';
$allowed_pages = ['ana-sayfa', 'bordro', 'izin', 'talep', 'profil'];

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

    <title>Ersan Elektrik</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "primary-dark": "#0d47c1",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                        "card-dark": "#1a2130",
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
    <link rel="stylesheet" href="assets/css/pwa-style.css">
<link rel="canonical" href="https:/www.personel.softran.online/index" />

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
        <a href="?page=profil"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'profil' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'profil' ? 'filled' : ''; ?>">person</span>
            <span class="text-[10px] font-semibold">Profil</span>
        </a>
    </nav>

    <!-- Loading Overlay -->
    <div id="loading-overlay"
        class="fixed inset-0 bg-white dark:bg-background-dark z-[100] flex items-center justify-center transition-all duration-500">
        <div class="loader-container">
            <div class="premium-loader">
                <div class="premium-loader-inner">
                    <span class="material-symbols-outlined"></span>
                </div>
            </div>
            <div class="flex flex-col items-center gap-1">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Ersan <span
                        class="text-primary">Elektrik</span></h2>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Yükleniyor...</p>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 left-4 right-4 z-[110] flex flex-col gap-2"></div>

    <!-- Scripts -->
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/pwa-app.js"></script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration);
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            });
        }
    </script>
</body>

</html>