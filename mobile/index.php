<?php
/**
 * Mobil Admin Arayüzü
 * Admin kullanıcıları için mobil öncelikli yönetim paneli
 * CSS/tasarım sistemi views/personel-pwa'dan alınmıştır.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

use App\Model\UserModel;

// Proje kök dizinine geç (view include'larının relative PHP path'leri için)
chdir(dirname(__DIR__));

session_start();

// Auth check
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($currentUserId <= 0 || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// User status check (kicks out passive users)
$StatusUserModel = new UserModel();
$currentUserStatus = $StatusUserModel->find($currentUserId);
if (!$currentUserStatus || ($currentUserStatus->durum ?? 'Aktif') === 'Pasif') {
    session_destroy();
    header("Location: ../login.php?status=inactive");
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

use App\Model\MenuModel;
$Menus = new MenuModel();
$menu_data = $Menus->getHierarchicalMenuForRole($currentUserId);

$permitted_links = [];
foreach ($menu_data as $group => $items) {
    foreach ($items as $item) {
        if (!empty($item->menu_link)) $permitted_links[] = $item->menu_link;
        if (!empty($item->children)) {
            foreach ($item->children as $child) {
                if (!empty($child->menu_link)) $permitted_links[] = $child->menu_link;
            }
        }
    }
}

// Tüm mobil özellikler (Erişim kontrolü için link anahtar kelimeleriyle)
$all_mobile_menus = [
    'home'        => ['label' => 'Ana Sayfa',   'icon' => 'home', 'color_bg' => 'bg-blue-100 dark:bg-blue-900/30', 'color_icon' => 'text-blue-600', 'link_match' => 'home'],
    'cari-takip'  => ['label' => 'Cari',        'icon' => 'account_balance_wallet', 'color_bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'color_icon' => 'text-emerald-600', 'link_match' => 'cari/list'],
    'gelir-gider' => ['label' => 'Gelir Gider', 'icon' => 'account_balance', 'color_bg' => 'bg-amber-100 dark:bg-amber-900/30', 'color_icon' => 'text-amber-600', 'link_match' => 'gelir-gider/list'],
    'raporlar'    => ['label' => 'Raporlar',    'icon' => 'bar_chart', 'color_bg' => 'bg-purple-100 dark:bg-purple-900/30', 'color_icon' => 'text-purple-600', 'link_match' => 'puantaj/raporlar'],
    'arac'        => ['label' => 'Araç',        'icon' => 'directions_car', 'color_bg' => 'bg-teal-100 dark:bg-teal-900/30', 'color_icon' => 'text-teal-600', 'link_match' => 'arac-takip/list'],
    'personel'    => ['label' => 'Personel',    'icon' => 'group', 'color_bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'color_icon' => 'text-indigo-600', 'link_match' => 'personel/list'],
    'gorevler'    => ['label' => 'Görevler',    'icon' => 'task_alt', 'color_bg' => 'bg-green-100 dark:bg-green-900/30', 'color_icon' => 'text-green-600', 'link_match' => 'gorevler/list'],
    'talepler'    => ['label' => 'Talepler',    'icon' => 'assignment', 'color_bg' => 'bg-orange-100 dark:bg-orange-900/30', 'color_icon' => 'text-orange-600', 'link_match' => 'talepler/list'],
    'evrak-takip' => ['label' => 'Evrak Takip', 'icon' => 'drafts', 'color_bg' => 'bg-sky-100 dark:bg-sky-900/30', 'color_icon' => 'text-sky-600', 'link_match' => 'evrak-takip/list'],
    'nobet'       => ['label' => 'Nöbetler',     'icon' => 'event_available', 'color_bg' => 'bg-rose-100 dark:bg-rose-900/30', 'color_icon' => 'text-rose-600', 'link_match' => 'nobet/onay-islemleri'],
    'km-onaylari' => ['label' => 'KM Onayları',  'icon' => 'speed', 'color_bg' => 'bg-cyan-100 dark:bg-cyan-900/30', 'color_icon' => 'text-cyan-600', 'link_match' => 'arac-takip/km-onaylari'],
    'yardim'      => ['label' => 'Destek',      'icon' => 'support_agent', 'color_bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'color_icon' => 'text-indigo-600', 'link_match' => 'yardim/list'],
];

$user_mobile_menus = [];
// Ana sayfa her zaman var
$user_mobile_menus['home'] = $all_mobile_menus['home'];

// Diğer menülerin yetkisi var mı?
foreach ($all_mobile_menus as $pKey => $mData) {
    if ($pKey === 'home') continue;
    
    // Normal link kontrolü
    $hasAccess = in_array($mData['link_match'], $permitted_links);
    
    // Yardım için alternatif link kontrolü
    if ($pKey === 'yardim' && !$hasAccess) {
        $hasAccess = in_array('yardim/user-list', $permitted_links);
        
        // Eğer hala yoksa, içinde 'yardim' geçen herhangi bir link var mı bak (daha esnek kontrol)
        if (!$hasAccess) {
            foreach ($permitted_links as $link) {
                if (strpos($link, 'yardim') !== false) {
                    $hasAccess = true;
                    break;
                }
            }
        }
    }

    if ($hasAccess) {
        $user_mobile_menus[$pKey] = $mData;
    }
}

$allowed_pages = array_keys($user_mobile_menus);

// Menüde görünmeyen gizli mobil alt sayfalar
$sub_pages = ['hesap-hareketleri', 'personel-duzenle', 'profil'];
$allowed_pages = array_merge($allowed_pages, $sub_pages);

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

$page_file = __DIR__ . '/pages/' . $page . '.php';

$page_titles = [
    'home'        => 'Ana Sayfa',
    'personel'    => 'Personel',
    'arac'        => 'Araç Takip',
    'gorevler'    => 'Görevler',
    'gelir-gider' => 'Gelir Gider Takibi',
    'cari-takip'  => 'Cari',    
    'hesap-hareketleri' => 'Hesap Hareketleri',
    'talepler'    => 'Talepler',
    'raporlar'    => 'Raporlar',
    'evrak-takip' => 'Evrak Takip',
    'nobet'       => 'Nöbetler',
    'km-onaylari' => 'KM Onayları',
    'yardim'      => 'Yardım ve Destek',
];

$currentTitle = $page_titles[$page] ?? 'Ana Sayfa';

$nav_items = [];
$more_pages_data = [];
$more_pages = [];

$hasCariPermission = isset($user_mobile_menus['cari-takip']);
$hasGelirGiderPermission = isset($user_mobile_menus['gelir-gider']);

// Kullanıcının özel sıralamasını al
$UserModel = new UserModel();
$userObj = $UserModel->find($currentUserId);
$customOrder = (!empty($userObj->mobile_menu_order)) ? explode(',', $userObj->mobile_menu_order) : [];

// Özel başlık düzeltmeleri
if ($hasCariPermission && $hasGelirGiderPermission) {
    if (isset($user_mobile_menus['gelir-gider'])) {
        $user_mobile_menus['gelir-gider']['label'] = 'Kasa';
    }
}

// Menüleri sırala
$final_sorted_menus = [];

// Ana sayfa her zaman ilk sırada olmalı
if (isset($user_mobile_menus['home'])) {
    $final_sorted_menus['home'] = $user_mobile_menus['home'];
    unset($user_mobile_menus['home']);
}

// Önce özel sıralamadaki mevcut menüleri ekle
foreach ($customOrder as $pKey) {
    if ($pKey === 'home') continue; // Zaten ekledik
    if (isset($user_mobile_menus[$pKey])) {
        $final_sorted_menus[$pKey] = $user_mobile_menus[$pKey];
        unset($user_mobile_menus[$pKey]);
    }
}

// Eğer özel sıralama yoksa varsayılan sıralama (cari ve gelir gider varsa özel bir varsayılan vardı)
if (empty($customOrder) && $hasCariPermission && $hasGelirGiderPermission) {
    $default_order = ['cari-takip', 'raporlar', 'gelir-gider'];
    foreach ($default_order as $pKey) {
        if (isset($user_mobile_menus[$pKey])) {
            $final_sorted_menus[$pKey] = $user_mobile_menus[$pKey];
            unset($user_mobile_menus[$pKey]);
        }
    }
}

// Kalan menüleri sonuna ekle
foreach ($user_mobile_menus as $pKey => $mData) {
    $final_sorted_menus[$pKey] = $mData;
}

// Navigasyon ve "Daha Fazla"yı ayır
$i = 0;
foreach ($final_sorted_menus as $pKey => $mData) {
    if ($i < 4) {
        $nav_items[] = ['page' => $pKey, 'label' => $mData['label'], 'icon' => $mData['icon']];
    } else {
        $more_pages_data[$pKey] = $mData;
        $more_pages[] = $pKey;
    }
    $i++;
}

$isMoreActive = in_array($page, $more_pages);
if ($page === 'personel-duzenle' && in_array('personel', $more_pages)) $isMoreActive = true;
if ($page === 'hesap-hareketleri' && in_array('cari-takip', $more_pages)) $isMoreActive = true;

// Bildirim Sayısı
$unreadNotificationCount = 0;
try {
    $db = (new \App\Model\Model())->getDb();
    $st1 = $db->prepare("SELECT COUNT(*) FROM personel_talepleri WHERE durum != 'cozuldu' AND silinme_tarihi IS NULL AND firma_id = ?");
    $st1->execute([$_SESSION['firma_id']]);
    $st2 = $db->prepare("SELECT COUNT(*) FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
    $st2->execute();
    $st3 = $db->prepare("SELECT COUNT(*) FROM personel_izinleri WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
    $st3->execute();
    $unreadNotificationCount = (int)$st1->fetchColumn() + (int)$st2->fetchColumn() + (int)$st3->fetchColumn();
} catch (\Exception $e) {}
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
    
    <!-- Scripts & Styles -->
    <script src="../assets/libs/jquery/jquery.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    <!-- Desktop Redirect -->
    <script>
        (function () {
            function checkDesktopRedirect() {
                var isMobileView = window.matchMedia('(max-width: 1024px)').matches;
                if (!isMobileView) {
                    var qs = new URLSearchParams(window.location.search);
                    var p = qs.get('p') || 'home';
                    // mobile=1 ekleyerek force_desktop session'ının temizlenmesini sağlarız.
                    window.location.replace('../index.php?p=' + encodeURIComponent(p) + '&mobile=1');
                }
            }
            // Sayfa yüklendiğinde ve ekran boyutu değiştiğinde kontrol et
            checkDesktopRedirect();
            window.addEventListener('resize', checkDesktopRedirect);
        })();

        // ===== PWA UI Helpers (Alert & Loading) =====
        const Loading = {
            show() { document.body.classList.add('loading-active'); },
            hide() { document.body.classList.remove('loading-active'); }
        };

        const Alert = {
            async confirm(title, text, confirmText = "Evet", cancelText = "Vazgeç") {
                const result = await Swal.fire({
                    title: title, text: text, icon: "question", showCancelButton: true,
                    confirmButtonText: confirmText, cancelButtonText: cancelText,
                    buttonsStyling: false, reverseButtons: true, width: 320, padding: 0,
                    customClass: {
                        popup: "swal-custom-popup", title: "swal-custom-title",
                        htmlContainer: "swal-custom-content", actions: "swal-custom-actions swal-actions-two",
                        confirmButton: "swal-custom-confirm swal-confirm-primary",
                        cancelButton: "swal-custom-cancel", icon: "swal-custom-icon swal-icon-question",
                    },
                });
                return result.isConfirmed;
            },
            async confirmDelete(title, text, confirmText = "Evet, Sil", cancelText = "Vazgeç") {
                const result = await Swal.fire({
                    title: title, text: text, icon: "warning", showCancelButton: true,
                    confirmButtonText: confirmText, cancelButtonText: cancelText,
                    buttonsStyling: false, reverseButtons: true, width: 320, padding: 0,
                    customClass: {
                        popup: "swal-custom-popup", title: "swal-custom-title",
                        htmlContainer: "swal-custom-content", actions: "swal-custom-actions swal-actions-two",
                        confirmButton: "swal-custom-confirm swal-confirm-danger",
                        cancelButton: "swal-custom-cancel", icon: "swal-custom-icon swal-icon-warning",
                    },
                });
                return result.isConfirmed;
            },
            success(title, text) {
                return Swal.fire({
                    title: title, text: text, icon: "success",
                    confirmButtonText: "Tamam", showCancelButton: false,
                    buttonsStyling: false, width: 320, padding: 0,
                    customClass: {
                        popup: "swal-custom-popup", title: "swal-custom-title",
                        htmlContainer: "swal-custom-content", actions: "swal-custom-actions",
                        confirmButton: "swal-custom-confirm swal-confirm-primary swal-confirm-full",
                        icon: "swal-custom-icon swal-icon-success",
                    },
                });
            },
            error(title, text) {
                return Swal.fire({
                    title: title, text: text, icon: "error",
                    confirmButtonText: "Tamam", showCancelButton: false,
                    buttonsStyling: false, width: 320, padding: 0,
                    customClass: {
                        popup: "swal-custom-popup", title: "swal-custom-title",
                        htmlContainer: "swal-custom-content", actions: "swal-custom-actions",
                        confirmButton: "swal-custom-confirm swal-confirm-danger swal-confirm-full",
                        icon: "swal-custom-icon swal-icon-error",
                    },
                });
            },
            async prompt(title, text, confirmText = "Gönder", placeholder = "") {
                const result = await Swal.fire({
                    title: title, text: text, icon: "question", showCancelButton: true,
                    confirmButtonText: confirmText, cancelButtonText: "Vazgeç",
                    input: 'text', inputPlaceholder: placeholder,
                    buttonsStyling: false, reverseButtons: true, width: 320, padding: 0,
                    customClass: {
                        popup: "swal-custom-popup", title: "swal-custom-title",
                        htmlContainer: "swal-custom-content", actions: "swal-custom-actions swal-actions-two",
                        confirmButton: "swal-custom-confirm swal-confirm-primary",
                        cancelButton: "swal-custom-cancel", input: "swal-custom-input",
                    },
                });
                return result.isConfirmed ? result.value : false;
            }
        };

        const Toast = {
            container: null,
            init() {
                this.container = document.getElementById("toast-container");
            },
            show(message, type = "success", duration = 3000) {
                if (!this.container) this.init();
                const toast = document.createElement("div");
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-xl">
                            ${type === "success" ? "check_circle" : type === "error" ? "error" : type === "warning" ? "warning" : "info"}
                        </span>
                        <span>${message}</span>
                    </div>
                `;
                this.container.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = "slideOutUp 0.3s ease-out forwards";
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        };
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

        /* ===== Flatpickr Premium Mobile Theme ===== */
        .flatpickr-calendar {
            background: #fff;
            box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.2), 0 0 1px 1px rgba(0,0,0,0.05);
            border-radius: 1.5rem;
            border: none;
            font-family: var(--font-family);
            padding: 8px;
            width: 320px !important;
        }

        .dark .flatpickr-calendar {
            background: #1e1e1e;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6), 0 0 1px 1px rgba(255,255,255,0.05);
            color: #fff;
        }

        .flatpickr-months {
            padding: 10px 0;
        }

        .flatpickr-current-month {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months {
            font-weight: 700;
        }

        .flatpickr-weekday {
            font-weight: 800;
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .flatpickr-day {
            border-radius: 0.75rem;
            font-weight: 600;
            margin: 2px;
            height: 38px;
            line-height: 38px;
            transition: all 0.2s ease;
        }

        .flatpickr-day.today {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(var(--primary-rgb), 0.05);
        }

        .flatpickr-day.selected, 
        .flatpickr-day.startRange, 
        .flatpickr-day.endRange {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.4);
        }

        .flatpickr-day.inRange {
            background: rgba(var(--primary-rgb), 0.1) !important;
            border-color: transparent !important;
            box-shadow: none !important;
            color: var(--primary) !important;
        }

        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.flatpickr-disabled:hover {
            color: #e2e8f0;
        }

        .dark .flatpickr-day {
            color: #cbd5e1;
        }

        .dark .flatpickr-day.flatpickr-disabled {
            color: #475569;
        }

        .dark .flatpickr-current-month,
        .dark .flatpickr-monthDropdown-months,
        .dark .cur-year {
            color: #fff;
        }

        .flatpickr-months .flatpickr-prev-month, 
        .flatpickr-months .flatpickr-next-month {
            padding: 12px;
            color: var(--primary);
            fill: var(--primary);
        }

        /* Month Select Plugin Styling */
        .flatpickr-monthSelect-month {
            border-radius: 1rem;
            padding: 10px 0;
            font-weight: 700;
        }

        .flatpickr-monthSelect-month.selected {
            background: var(--primary) !important;
            color: #fff;
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-white min-h-screen pb-nav">

    <!-- iOS Güvenli Alan Boşluğu -->
    <div class="h-safe-top bg-primary dark:bg-primary-dark"></div>

    <!-- Üst Başlık (Kullanıcı isteğiyle gizlendi) 
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
    -->

    <?php 
    // Kendi özel (gradient vb.) başlık yapısı olan veya üst bar istenmeyen sayfalar
    $no_header_pages = ['home', 'hesap-hareketleri', 'arac', 'gorevler', 'talepler', 'personel', 'personel-duzenle', 'yardim', 'evrak-takip', 'nobet', 'km-onaylari'];
    if (!in_array($page, $no_header_pages)): 
    ?>
    <!-- Sayfa Başlığı (Gradient) -->
    <header class="bg-gradient-primary text-white px-4 pt-4 pb-5 rounded-b-3xl relative overflow-hidden z-40 shadow-sm shrink-0">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white rounded-full -ml-12 -mb-12"></div>
        </div>
        <div class="relative z-10 flex items-center justify-between">
            <div>
                <h1 class="text-[17px] font-bold leading-tight">
                    <?= htmlspecialchars($currentTitle) ?>
                </h1>
                <p class="text-white/70 text-[10px] mt-0.5"><?= date('d.m.Y') ?> – Yönetim Paneli</p>
            </div>
            <div class="flex items-center gap-1.5">
                <button onclick="toggleDarkMode()"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-white active:scale-95 transition-transform bg-white/10 hover:bg-white/20">
                    <span class="material-symbols-outlined text-[18px] dark:hidden">dark_mode</span>
                    <span class="material-symbols-outlined text-[18px] hidden dark:block">light_mode</span>
                </button>
                <a href="?p=talepler" class="relative w-8 h-8 rounded-full flex items-center justify-center text-white active:scale-95 transition-transform bg-white/10 hover:bg-white/20">
                    <span class="material-symbols-outlined text-[18px]">notifications</span>
                    <?php if ($unreadNotificationCount > 0): ?>
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center border border-[#135bec]">
                            <?= $unreadNotificationCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../logout.php"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-white active:scale-95 transition-transform bg-white/10 hover:bg-white/20">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <!-- Ana İçerik -->
    <main id="main-content" class="<?= (!in_array($page, $no_header_pages)) ? 'min-h-[calc(100vh-85px)]' : 'min-h-screen' ?>">
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
                    <a href="?force_desktop=1&p=home"
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
            <?php foreach ($nav_items as $item): 
                $isActive = ($page === $item['page']);
                if ($page === 'personel-duzenle' && $item['page'] === 'personel') $isActive = true;
                if ($page === 'hesap-hareketleri' && $item['page'] === 'cari-takip') $isActive = true;
            ?>
                <a href="?p=<?= $item['page'] ?>"
                    class="nav-item flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all <?= $isActive ? 'text-primary' : 'text-slate-400 dark:text-slate-500' ?>">
                    <span class="material-symbols-outlined text-[26px] <?= $isActive ? 'filled' : '' ?>"><?= $item['icon'] ?></span>
                    <span class="text-[10px] font-semibold"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!empty($more_pages)): ?>
            <button type="button" onclick="toggleMoreMenu()"
                class="nav-item flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all <?= $isMoreActive ? 'text-primary' : 'text-slate-400 dark:text-slate-500' ?>">
                <span class="material-symbols-outlined text-[26px] <?= $isMoreActive ? 'filled' : '' ?>">more_horiz</span>
                <span class="text-[10px] font-semibold">Daha Fazla</span>
            </button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 left-4 right-4 z-[110] flex flex-col gap-2 pointer-events-none"></div>

    <!-- Daha Fazla Overlay -->
    <div id="more-menu-overlay"
        class="fixed inset-0 bg-black/50 z-[60] opacity-0 pointer-events-none transition-opacity duration-300"
        onclick="closeMoreMenu()"></div>

    <!-- Daha Fazla Bottom Sheet -->
    <div id="more-menu-sheet"
        class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[65vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-2 shrink-0 cursor-pointer" onclick="toggleMoreMenu()">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="flex-1 overflow-y-auto px-4 pb-8 no-scrollbar">
            <div class="py-2">
                <!-- Masaüstü Görünümü -->
                <a href="?force_desktop=1&p=home"
                    class="flex items-center gap-3 p-3.5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors mb-2 border border-transparent hover:border-slate-100 dark:hover:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-xl">desktop_windows</span>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-slate-900 dark:text-white text-[13px] block">Masaüstü Görünümü</span>
                        <span class="text-[10px] text-slate-400 font-medium">Tam sürüme geçiş yap</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                </a>

                <!-- Profil Sayfası -->
                <a href="?p=profil"
                    class="flex items-center gap-3 p-3.5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors <?= $page === 'profil' ? 'bg-primary/5 border-primary/10' : 'border-transparent' ?> mb-2 border hover:border-slate-100 dark:hover:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-indigo-600 text-xl">person</span>
                    </div>
                    <div class="flex-1">
                        <span class="font-bold text-slate-900 dark:text-white text-[13px] block">Profil Bilgileri</span>
                        <span class="text-[10px] text-slate-400 font-medium">Hesap ve güvenlik ayarları</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                </a>

                <?php if (!empty($more_pages_data)): ?>
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="h-px bg-slate-100 dark:bg-slate-800 flex-1"></div>
                    <span class="text-[10px] font-black text-slate-300 tracking-widest uppercase">Modüller</span>
                    <div class="h-px bg-slate-100 dark:bg-slate-800 flex-1"></div>
                </div>

                <div class="grid grid-cols-1 gap-1">
                    <?php foreach ($more_pages_data as $pKey => $mItem): ?>
                    <a href="?p=<?= $pKey ?>"
                        class="flex items-center gap-3 p-3.5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors <?= $page === $pKey ? 'bg-primary/5 border-primary/10' : 'border-transparent' ?> border hover:border-slate-100 dark:hover:border-slate-700">
                        <div class="w-10 h-10 rounded-xl <?= $mItem['color_bg'] ?> flex items-center justify-center">
                            <span class="material-symbols-outlined <?= $mItem['color_icon'] ?> text-xl"><?= $mItem['icon'] ?></span>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold text-slate-900 dark:text-white text-[13px] block"><?= $mItem['label'] ?></span>
                        </div>
                        <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="mt-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <a href="../logout.php"
                        class="flex items-center gap-3 p-3.5 rounded-2xl bg-red-50/50 dark:bg-red-900/10 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors border border-red-100/50 dark:border-red-900/20">
                        <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-600 text-xl">logout</span>
                        </div>
                        <span class="font-bold text-red-600 text-[13px]">Güvenli Çıkış</span>
                        <span class="material-symbols-outlined text-red-300 ml-auto text-lg">power_settings_new</span>
                    </a>
                </div>
            </div>
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

    <script src="assets/js/push-config.js?v=<?= time() ?>"></script>
</body>
</html>
