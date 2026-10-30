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
use App\Model\AracZimmetModel;

// Personel sayfaları HTTP önbelleğinde saklanmaz; service worker'ın saha sayfası
// önbelleği ayrıca kendi allowlist/istisnalarını uygular.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

// Oturum kontrolü öncesi beni hatırla kontrolü
if (!isset($_SESSION['personel_id']) && isset($_COOKIE['remember_token'])) {
    $pwaSecret = $_ENV['PWA_HMAC_SECRET'] ?? '';
    if (empty($pwaSecret)) {
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        error_log('PWA_HMAC_SECRET .env dosyasında tanımlı değil, remember_token cookie temizlendi.');
    } else {
    $token = $_COOKIE['remember_token'];
    $parts = explode(':', base64_decode($token));

    if (count($parts) === 2) {
        $p_id = $parts[0];
        $hash = $parts[1];

        $PersonelModel = new PersonelModel();
        $personel = $PersonelModel->find($p_id);

        $isActivePersonel = $personel
            && empty($personel->silinme_tarihi)
            && (int) ($personel->aktif_mi ?? 0) !== 0
            && (empty($personel->isten_cikis_tarihi) || $personel->isten_cikis_tarihi === '0000-00-00');

        if ($isActivePersonel) {
            $checkHash = hash_hmac('sha256', $personel->id . $personel->sifre, $pwaSecret);
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
                setcookie('remember_token', $token, ['expires' => time() + (86400 * 30), 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            }
        }
    }

    if (!isset($_SESSION['personel_id'])) {
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }
    }
}

// Oturum kontrolü
if (!isset($_SESSION['personel_id'])) {
    if (($_GET['page'] ?? '') === 'bordro' && is_string($_GET['dokum'] ?? null) && strlen($_GET['dokum']) <= 2048) {
        $_SESSION['bordro_yayin_donus'] = $_GET['dokum'];
    }
    header("Location: login.php");
    exit();
}

$personel_id = $_SESSION['personel_id'];
$PersonelModel = new PersonelModel();
$personel = $PersonelModel->find($personel_id);

// Personel bulunamazsa veya pasifse oturumu kapat ve login'e yönlendir
$isPassive = $personel && (
    !empty($personel->silinme_tarihi)
    || (int) ($personel->aktif_mi ?? 0) === 0
    || (!empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00')
);
if (!$personel || $isPassive) {
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    $statusParam = $isPassive ? '?status=inactive' : '';
    header("Location: login.php" . $statusParam);
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

// Araç zimmet kontrolü
$AracZimmetModel = new AracZimmetModel();
$aktifAracZimmetleri = $AracZimmetModel->getAktifZimmetlerByPersonel($personel_id);
$aktifAracZimmeti = $aktifAracZimmetleri[0] ?? null;

// Departman & Görev bazlı kontrol
$isEndeksOkuma = (stripos($personel->departman ?? '', 'Endeks Okuma') !== false);
$isSayacSokmeTakma = (stripos($personel->departman ?? '', 'Sayaç Sökme Takma') !== false);
$isBuro = (stripos($personel->departman ?? '', 'BÜRO') !== false || stripos($personel->departman ?? '', 'Büro') !== false);
$isSef = (stripos($personel->gorev ?? '', 'Şef') !== false);
$isKesmeAcma = (stripos($personel->departman ?? '', 'Kesme-Açma') !== false || stripos($personel->departman ?? '', 'Kesme Açma') !== false);
$isKacakKontrol = (stripos($personel->departman ?? '', 'Kaçak') !== false);
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

$isKaskiKacak = ($personel->personel_tipi ?? 'standart') === 'kaski_kacak';

if ($isKaskiKacak) {
    $allowed_pages = ['kacak', 'profil', 'bordro'];
    $page = isset($_GET['page']) && in_array($_GET['page'], $allowed_pages, true) ? $_GET['page'] : 'kacak';
} else {
    // Sayfa yönlendirmesi
    $page = $_GET['page'] ?? 'ana-sayfa';
    $allowed_pages = ['ana-sayfa', 'bordro', 'izin', 'talep', 'profil', 'puantaj', 'etkinlikler', 'zimmetler', 'icralar', 'yardim', 'yardim-detay', 'km-bildirimleri', 'ihbar'];

    if ($isEndeksOkuma && $isEkipSefi) {
        $allowed_pages[] = 'ekip-takibi';
    }

    if ($isKesmeAcma) {
        $allowed_pages[] = 'nobet';
        $allowed_pages[] = 'aparat';
    }

    if ($isKacakKontrol) {
        $allowed_pages[] = 'kacak';
    }

    if (!in_array($page, $allowed_pages)) {
        $page = 'ana-sayfa';
    }
}

// Yatay kaydırma sırası, sabit bir istemci listesinden değil personelin gerçekten
// görebildiği menü öğelerinden üretilir. Detay sayfaları bu sıraya dahil edilmez.
if ($isKaskiKacak) {
    $pwaNavigationPages = ['kacak', 'bordro', 'profil'];
} else {
    $pwaNavigationPages = [
        'ana-sayfa',
        $isBuro ? 'izin' : 'puantaj',
        ($isEndeksOkuma && $isEkipSefi) ? 'ekip-takibi' : ($isKesmeAcma ? 'nobet' : 'zimmetler'),
        'talep',
        'profil',
        'yardim',
        'ihbar',
    ];

    if ($isKacakKontrol) {
        $pwaNavigationPages[] = 'kacak';
    }
    if ($isKesmeAcma) {
        $pwaNavigationPages[] = 'aparat';
    }

    $pwaNavigationPages[] = 'km-bildirimleri';
    $pwaNavigationPages[] = 'etkinlikler';
    $pwaNavigationPages[] = 'bordro';

    // Büro dışındaki personelde İzinler alt menüde değil, Diğer menüsündedir.
    if (!$isBuro) {
        $pwaNavigationPages[] = 'izin';
    }
    if ($hasIcra) {
        $pwaNavigationPages[] = 'icralar';
    }
    if (($isEndeksOkuma && $isEkipSefi) || $isKesmeAcma) {
        $pwaNavigationPages[] = 'zimmetler';
    }

    $pwaNavigationPages = array_values(array_unique(array_filter(
        $pwaNavigationPages,
        static fn ($navigationPage) => in_array($navigationPage, $allowed_pages, true)
    )));
}

// Service worker'ın çevrimdışı kullanım için önden çektiği sayfa gerçekten
// görüntülenmiş sayılmaz; log kirlenmesin.
$onbellekIcin = isset($_GET['onbellek']);

// Log page view
if (!$onbellekIcin) {
    try {
        $LogModel = new \App\Model\SystemLogModel();
        // Personel olduğu için name parametresi ile ismini saklayacağız (user_id 0 ancak açıklama içinde geçecek)
        $LogModel->logAction(0, 'Sayfa Görüntüleme', "[Personel PWA] " . ($personel->adi_soyadi ?? 'Bilinmeyen') . " tarafından $page sayfası görüntülendi.", \App\Model\SystemLogModel::LEVEL_PAGE_VIEW);
    } catch (\Exception $e) {}
}

// Bağlantı yokken açılabilmesi için önden önbelleğe alınacak sayfalar.
// Personelin yetkisi olmayan sayfa listeye girmez.
if ($isKaskiKacak) {
    $cevrimdisiSayfalar = ['index.php', 'index.php?page=kacak', 'index.php?page=profil'];
} else {
    $cevrimdisiSayfalar = ['index.php', 'index.php?page=ana-sayfa', 'index.php?page=ihbar'];
    if ($isKacakKontrol) {
        $cevrimdisiSayfalar[] = 'index.php?page=kacak';
    }
    if ($isKesmeAcma) {
        $cevrimdisiSayfalar[] = 'index.php?page=aparat';
    }
}

// Durum çubuğu tüm sayfalarda gövde zemin rengiyle aynıdır (açık: #e6eaef, koyu: #121212).
$pageStatusLight = '#e6eaef';
$pageStatusDark = '#121212';
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?= htmlspecialchars($pageStatusLight, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="description" content="Ersan Elektrik - Mobil Uygulama">

    <!-- Harici CSS önbellekte kalsa bile uygulama içi geçişte loader görünmez. -->
    <style>html.pwa-internal-navigation #loading-overlay{display:none!important}</style>

    <title>Ersan Elektrik | Personel Yönetimi</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/x-icon" href="assets/icons/app-favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/app-192.png?v=2">
    <link rel="apple-touch-icon" href="assets/icons/app-apple-touch-180.png?v=2">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block"
        rel="stylesheet">

    <!-- Theme & Dark Mode Pre-init -->
    <script>
        (function () {
            // Uygulama içindeki sayfa geçişlerinde tam ekran yükleyici, View
            // Transition animasyonunun üzerini kapatmasın. İlk açılışta gösterilir.
            try {
                const navigationMarker = JSON.parse(sessionStorage.getItem('pwaInternalNavigation') || 'null');
                const currentUrl = new URL(window.location.href);
                const markedInternalNavigation =
                    navigationMarker &&
                    Date.now() - Number(navigationMarker.time || 0) < 10000 &&
                    navigationMarker.path === currentUrl.pathname + currentUrl.search;
                const appAlreadyStarted = sessionStorage.getItem('pwaSessionStarted') === '1';

                if (markedInternalNavigation || appAlreadyStarted) {
                    document.documentElement.classList.add('pwa-internal-navigation');
                }

                sessionStorage.setItem('pwaSessionStarted', '1');
                sessionStorage.removeItem('pwaInternalNavigation');
            } catch (e) {}

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
            const pageStatusLight = <?= json_encode($pageStatusLight) ?>;
            const pageStatusDark = <?= json_encode($pageStatusDark) ?>;
            window.syncPageStatusBar = function () {
                const isDark = document.documentElement.classList.contains('dark');
                const color = isDark ? pageStatusDark : pageStatusLight;
                document.documentElement.style.setProperty('--page-status-color', color);
                if (meta) meta.setAttribute('content', color);
            };
            window.syncPageStatusBar();

            // Dark mode
            if (localStorage.getItem('darkMode') === 'true') {
                r.classList.add('dark');
            }

            if (localStorage.getItem('chatFabHidden') === 'true') {
                r.classList.add('chat-fab-hidden');
            }
            window.syncPageStatusBar();
            new MutationObserver(window.syncPageStatusBar).observe(r, {
                attributes: true,
                attributeFilter: ['class']
            });
        })();
    </script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/pwa-style.css?v=<?= filemtime(__DIR__ . '/assets/css/pwa-style.css') ?>">
    <?php
    // Canlı destek ayar kontrolü
    $_pwaSettingsModel = new \App\Model\SettingsModel();
    $_pwaFirmaId = $personel->firma_id ?? ($_SESSION['firma_id'] ?? null);
    $_pwaCanliDestekAktif = $_pwaSettingsModel->getSettings('canli_destek_aktif', $_pwaFirmaId) === '1';

    if ($_pwaCanliDestekAktif): ?>
        <link rel="stylesheet" href="assets/css/pwa-chat.css?v=<?= filemtime(__DIR__ . '/assets/css/pwa-chat.css') ?>">
    <?php endif; ?>

    <!-- Tailwind CSS (derlenmiş statik build - rebuild-tailwind.sh) -->
    <!-- pwa-style.css/pwa-chat.css'ten SONRA yüklenmeli: aynı özgüllükteki .quick-action, .neon-* vb.
         özel class'ları Tailwind utility'lerinin ezmemesi için (CDN'in eski davranışıyla aynı sıra). -->
    <link rel="stylesheet" href="assets/css/tailwind-build.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind-build.css') ?>">
    <link rel="canonical" href="https://www.personel.ersantr.com/index.php" />

</head>

<body
    class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-white min-h-screen pb-20">

    <!-- Status Bar Spacer (iOS) -->
    <div class="h-safe-top" style="background-color: var(--page-status-color, #e6eaef);"></div>

    <!-- Main Content -->
    <main id="main-content" class="min-h-screen">
        <?php require_once __DIR__ . '/partials/page-header.php'; ?>
        <?php include "pages/{$page}.php"; ?>
    </main>

    <?php
    $navMaxSlots = 3;
    $navCatalog = [
        'puantaj' => ['label' => 'İş Takibi', 'eyebrow' => 'SAHA', 'icon' => 'checklist', 'tone' => 'blue', 'show' => !$isBuro],
        'ekip-takibi' => ['label' => 'Ekip Takibi', 'eyebrow' => 'EKİP', 'icon' => 'groups', 'tone' => 'blue', 'show' => (bool) ($isEndeksOkuma && $isEkipSefi)],
        'nobet' => ['label' => 'Nöbet Takibi', 'eyebrow' => 'VARDİYA', 'icon' => 'nights_stay', 'tone' => 'purple', 'show' => (bool) $isKesmeAcma],
        'zimmetler' => ['label' => 'Zimmetler', 'eyebrow' => 'ENVANTER', 'icon' => 'inventory_2', 'tone' => 'amber', 'show' => true],
        'talep' => ['label' => 'Talepler', 'eyebrow' => 'TALEP YÖNETİMİ', 'icon' => 'assignment', 'tone' => 'emerald', 'show' => true],
        'izin' => ['label' => 'İzinler', 'eyebrow' => 'İNSAN KAYNAKLARI', 'icon' => 'calendar_today', 'tone' => 'amber', 'show' => true],
        'bordro' => ['label' => 'Bordrolar ve Avans', 'navLabel' => 'Bordro', 'eyebrow' => 'FİNANS', 'icon' => 'payments', 'tone' => 'emerald', 'show' => true],
        'profil' => ['label' => 'Profil', 'eyebrow' => 'HESABIM', 'icon' => 'person', 'tone' => 'blue', 'show' => true],
        'yardim' => ['label' => 'Yardım & Destek', 'navLabel' => 'Destek', 'eyebrow' => 'DESTEK', 'icon' => 'support_agent', 'tone' => 'purple', 'show' => true],
        'ihbar' => ['label' => 'İhbar Yönetimi', 'navLabel' => 'İhbar', 'eyebrow' => 'KAÇAK KONTROL', 'icon' => 'campaign', 'tone' => 'coral', 'show' => true],
        'kacak' => ['label' => 'Kaçak İşlemleri', 'navLabel' => 'Kaçak', 'eyebrow' => 'KAÇAK KONTROL', 'icon' => 'gpp_maybe', 'tone' => 'amber', 'show' => (bool) $isKacakKontrol],
        'aparat' => ['label' => 'Aparat Takip', 'navLabel' => 'Aparat', 'eyebrow' => 'SAHA', 'icon' => 'build', 'tone' => 'purple', 'show' => (bool) $isKesmeAcma],
        'km-bildirimleri' => ['label' => 'KM Bildirimleri', 'navLabel' => 'KM', 'eyebrow' => 'ARAÇ', 'icon' => 'speed', 'tone' => 'purple', 'show' => true],
        'etkinlikler' => ['label' => 'Etkinlikler', 'eyebrow' => 'KURUM', 'icon' => 'event', 'tone' => 'emerald', 'show' => true],
        'icralar' => ['label' => 'İcralarım', 'navLabel' => 'İcra', 'eyebrow' => 'DOSYALARIM', 'icon' => 'gavel', 'tone' => 'coral', 'show' => (bool) $hasIcra],
    ];
    $navCatalog = array_filter($navCatalog, static fn(array $m): bool => $m['show']);

    $navVarsayilan = [
        $isBuro ? 'izin' : 'puantaj',
        ($isEndeksOkuma && $isEkipSefi) ? 'ekip-takibi' : ($isKesmeAcma ? 'nobet' : 'zimmetler'),
        'talep',
    ];
    $navKeys = $navVarsayilan;
    if (isset($_COOKIE['pwa_nav']) && is_string($_COOKIE['pwa_nav'])) {
        $cerezAnahtarlar = $_COOKIE['pwa_nav'] === '-' ? [] : explode(',', $_COOKIE['pwa_nav']);
        $gecerli = [];
        foreach ($cerezAnahtarlar as $anahtar) {
            if (isset($navCatalog[$anahtar]) && !in_array($anahtar, $gecerli, true)) {
                $gecerli[] = $anahtar;
            }
        }
        $navKeys = array_slice($gecerli, 0, $navMaxSlots);
    }
    $navKeys = array_values(array_filter($navKeys, static fn(string $k): bool => isset($navCatalog[$k])));
    $moreActive = isset($navCatalog[$page]) && !in_array($page, $navKeys, true);

    $menuPayload = [
        'items' => [],
        'nav' => $navKeys,
        'default' => array_values(array_filter($navVarsayilan, static fn(string $k): bool => isset($navCatalog[$k]))),
        'max' => $navMaxSlots,
        'page' => $page,
        'cookiePath' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/',
    ];
    foreach ($navCatalog as $anahtar => $m) {
        $menuPayload['items'][] = [
            'key' => $anahtar,
            'label' => $m['label'],
            'navLabel' => $m['navLabel'] ?? $m['label'],
            'eyebrow' => $m['eyebrow'],
            'icon' => $m['icon'],
            'tone' => $m['tone'],
        ];
    }
    ?>
    <!-- Bottom Navigation -->
    <?php if ($isKaskiKacak): ?>
    <nav
        aria-label="Ana gezinme"
        class="pwa-bottom-navigation fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark border-t border-slate-200 dark:border-slate-800 flex justify-around items-center py-2 px-4 z-50 safe-area-bottom">
        <a href="?page=kacak"
            class="nav-item flex flex-col items-center gap-1 py-2 px-6 rounded-xl transition-all <?php echo $page === 'kacak' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'kacak' ? 'filled' : ''; ?>">gpp_maybe</span>
            <span class="text-[11px] font-semibold">Kaçak İşlemleri</span>
        </a>
        <a href="?page=bordro" class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl <?php echo $page === 'bordro' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined">receipt_long</span><span class="text-[11px] font-semibold">Bordrom</span>
        </a>
        <a href="?page=profil"
            class="nav-item flex flex-col items-center gap-1 py-2 px-6 rounded-xl transition-all <?php echo $page === 'profil' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'profil' ? 'filled' : ''; ?>">person</span>
            <span class="text-[11px] font-semibold">Profilim</span>
        </a>
        <a href="javascript:void(0)" onclick="logout()"
            class="nav-item flex flex-col items-center gap-1 py-2 px-6 rounded-xl transition-all text-red-500">
            <span class="material-symbols-outlined">logout</span>
            <span class="text-[11px] font-semibold">Çıkış Yap</span>
        </a>
    </nav>
    <?php else: ?>
    <nav
        aria-label="Ana gezinme"
        class="pwa-bottom-navigation fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark border-t border-slate-200 dark:border-slate-800 flex justify-around items-center py-2 px-4 z-50 safe-area-bottom">
        <a href="?page=ana-sayfa"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $page === 'ana-sayfa' ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span class="material-symbols-outlined <?php echo $page === 'ana-sayfa' ? 'filled' : ''; ?>">home</span>
            <span class="text-[10px] font-semibold">Ana Sayfa</span>
        </a>
        <?php foreach ($navKeys as $_navKey): $_navMod = $navCatalog[$_navKey]; $_navAktif = $page === $_navKey; ?>
            <a href="?page=<?= htmlspecialchars($_navKey, ENT_QUOTES, 'UTF-8') ?>"
                class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $_navAktif ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
                <span class="material-symbols-outlined <?php echo $_navAktif ? 'filled' : ''; ?>"><?= htmlspecialchars($_navMod['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-[10px] font-semibold"><?= htmlspecialchars($_navMod['navLabel'] ?? $_navMod['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" onclick="toggleMoreMenu()"
            class="nav-item flex flex-col items-center gap-1 py-2 px-4 rounded-xl transition-all <?php echo $moreActive ? 'text-primary bg-primary/10' : 'text-slate-500'; ?>">
            <span
                class="material-symbols-outlined <?php echo $moreActive ? 'filled' : ''; ?>">more_horiz</span>
            <span class="text-[10px] font-semibold">Diğer</span>
        </button>
    </nav>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 z-[100] flex items-center justify-center" role="status" aria-live="polite">
        <div class="loader-container">
            <div class="splash-stage-3d">
                <div class="splash-glow"></div>
                <div class="splash-orbit"></div>
                <div class="splash-orbit splash-orbit--inner"></div>
                <div class="splash-logo-card-3d">
                    <img src="assets/images/logo-3d-splash.png?v=1" width="104" height="104" alt="Ersan Elektrik">
                </div>
            </div>
            <div class="splash-meta">
                <span class="splash-kicker">ERSAN ELEKTRİK</span>
                <strong class="splash-title">Personel Portalı</strong>
                <div class="splash-loader"><i></i></div>
            </div>
        </div>
    </div>

    <!-- More Menu Bottom Sheet -->
    <div id="more-menu-overlay"
        class="fixed inset-0 bg-black/50 z-[60] opacity-0 pointer-events-none transition-opacity duration-300"
        onclick="closeMoreMenu()"></div>
    <div id="more-menu-sheet"
        class="pwa-nav-sheet translate-y-full" role="dialog" aria-modal="true" aria-label="Daha Fazla">
        <header class="pwa-nav-sheet__header">
            <div class="pwa-nav-sheet__titles">
                <span class="pwa-nav-sheet__eyebrow" id="more-menu-eyebrow">HIZLI ERİŞİM &amp; MENÜ</span>
                <h2 class="pwa-nav-sheet__title" id="more-menu-title">Daha Fazla</h2>
                <p class="pwa-nav-sheet__subtitle" id="more-menu-subtitle">Modüller ve araçlar</p>
            </div>
            <div class="pwa-topbar__actions">
                <button type="button" id="more-menu-edit-btn" class="pwa-circle-btn" aria-label="Menü düzenini özelleştir" title="Menüyü Düzenle" onclick="MoreMenu.toggleEdit()">
                    <span class="material-symbols-outlined">tune</span>
                </button>
                <button type="button" class="pwa-circle-btn" aria-label="Kapat" onclick="closeMoreMenu()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </header>
        <div class="pwa-nav-sheet__content" id="more-menu-content"></div>
        <div class="pwa-nav-sheet-bar" id="more-menu-search-wrap">
            <label class="pwa-nav-sheet-search">
                <span class="material-symbols-outlined pwa-nav-sheet-search__icon">search</span>
                <input type="text" id="more-menu-search" class="pwa-nav-sheet-search__input" placeholder="Menülerde ara…"
                    aria-label="Menülerde ara" autocomplete="off" enterkeyhint="search">
                <button type="button" id="more-menu-search-clear" class="pwa-nav-sheet-search__clear" aria-label="Aramayı temizle" hidden>
                    <span class="material-symbols-outlined">close</span>
                </button>
            </label>
            <a href="?page=profil" class="pwa-circle-btn pwa-nav-sheet-bar__btn" aria-label="Profil" title="Profil">
                <span class="material-symbols-outlined">person</span>
            </a>
            <button type="button" class="pwa-circle-btn pwa-nav-sheet-bar__btn pwa-nav-sheet-bar__btn--danger" aria-label="Çıkış Yap" title="Çıkış Yap" onclick="logout()">
                <span class="material-symbols-outlined">logout</span>
            </button>
        </div>
        <div class="pwa-nav-sheet-footer" id="more-menu-footer" hidden>
            <button type="button" class="pwa-nav-sheet-footer__btn" onclick="MoreMenu.resetDefault()">Varsayılana Sıfırla</button>
            <button type="button" class="pwa-nav-sheet-footer__btn pwa-nav-sheet-footer__btn--primary" onclick="MoreMenu.save()">Düzeni Kaydet</button>
        </div>
    </div>

    <script>window.PWA_MENU = <?= json_encode($menuPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>

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

    <!-- PWA Tam Ekran Modal (Generic Full Screen Layout) -->
    <div id="pwa-full-modal"
        class="fixed inset-0 z-[120] bg-slate-50 dark:bg-background-dark transform translate-y-full transition-transform duration-300 flex flex-col"
        style="display:none;">
        
        <!-- Header Actions (Top) -->
        <div class="fixed top-0 left-0 right-0 z-[140] p-4 flex justify-between items-center pointer-events-none">
            <button onclick="closePwaFullModal()" 
                    class="w-10 h-10 rounded-full bg-black/20 backdrop-blur-md text-white flex items-center justify-center pointer-events-auto active:scale-90 transition-transform shadow-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div id="pwa-full-modal-actions" class="flex items-center gap-2 pointer-events-auto">
                <!-- Additional actions inyected here -->
            </div>
        </div>

        <!-- Float Close Button (Fixed at Bottom Right) -->
        <button onclick="closePwaFullModal()"
            class="fixed bottom-6 right-6 w-14 h-14 rounded-full bg-primary text-white shadow-2xl flex items-center justify-center z-[130] active:scale-90 transition-transform cursor-pointer border-none outline-none">
            <span class="material-symbols-outlined text-3xl">close</span>
        </button>

        <!-- Container for dynamic content -->
        <div id="pwa-full-modal-content" class="flex-1 overflow-y-auto flex flex-col disable-scrollbar pb-24">
        </div>
    </div>

    <!-- Scripts -->
    <!-- SweetAlert2 -->
    <script src="assets/libs/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="assets/js/exif-cekim.js?v=<?= filemtime(__DIR__ . '/assets/js/exif-cekim.js') ?>"></script>
    <script>
        window.PWA_ACCOUNT_KEY = <?= json_encode(hash('sha256', 'pwa:' . (int) $_SESSION['firma_id'] . ':' . (int) $personel_id)) ?>;
        window.PWA_NAVIGATION_PAGES = <?= json_encode($pwaNavigationPages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script>
        // Eski PWA JavaScript'i sabit IndexedDB sürümüyle açılırsa Safari mevcut
        // daha yüksek sürümü reddeder. Kuyruk veritabanında sürüm seçimini daima
        // tarayıcıya bırakarak önbellekte kalmış eski istemciyi de kurtar.
        try {
            if (window.indexedDB && !window.__pwaIndexedDbVersionGuard) {
                const indexedDbOpen = window.indexedDB.open.bind(window.indexedDB);
                window.indexedDB.open = function (name, version) {
                    if (name === 'ersan-pwa-offline') return indexedDbOpen(name);
                    return arguments.length > 1 ? indexedDbOpen(name, version) : indexedDbOpen(name);
                };
                window.__pwaIndexedDbVersionGuard = true;
            }
        } catch (e) {
            console.warn('IndexedDB sürüm koruması kurulamadı:', e);
        }
    </script>
    <script src="assets/js/pwa-offline-queue.js?v=<?= filemtime(__DIR__ . '/assets/js/pwa-offline-queue.js') ?>"></script>
    <script src="assets/js/pwa-app.js?v=<?= filemtime(__DIR__ . '/assets/js/pwa-app.js') ?>"></script>
    <script src="assets/js/notification-helper.js"></script>
    <?php if ($_pwaCanliDestekAktif): ?>
        <script src="assets/js/pwa-chat.js?v=<?= filemtime(__DIR__ . '/assets/js/pwa-chat.js') ?>"></script>
    <?php endif; ?>

    <!-- More Menu Scripts -->
    <script>
        const MoreMenu = {
            data: window.PWA_MENU || { items: [], nav: [], default: [], max: 3, page: '', cookiePath: '/' },
            editMode: false,
            draft: [],
            query: '',
            feedback: '',

            norm(t) { return String(t || '').toLocaleLowerCase('tr-TR').trim(); },
            item(key) { return this.data.items.find((m) => m.key === key); },

            el(tag, cls, text) {
                const n = document.createElement(tag);
                if (cls) n.className = cls;
                if (text !== undefined) n.textContent = text;
                return n;
            },
            icon(name, cls) { return this.el('span', 'material-symbols-outlined' + (cls ? ' ' + cls : ''), name); },

            init() {
                const input = document.getElementById('more-menu-search');
                const clear = document.getElementById('more-menu-search-clear');
                if (!input) return;
                input.addEventListener('input', () => {
                    this.query = input.value;
                    clear.hidden = !input.value;
                    this.render();
                });
                clear.addEventListener('click', (e) => {
                    e.preventDefault();
                    input.value = '';
                    this.query = '';
                    clear.hidden = true;
                    input.focus();
                    this.render();
                });
                this.render();
            },

            reset() {
                this.editMode = false;
                this.feedback = '';
                this.query = '';
                const input = document.getElementById('more-menu-search');
                if (input) input.value = '';
                const clear = document.getElementById('more-menu-search-clear');
                if (clear) clear.hidden = true;
                this.render();
            },

            toggleEdit() {
                this.editMode = !this.editMode;
                this.draft = this.data.nav.slice();
                this.feedback = '';
                this.query = '';
                const input = document.getElementById('more-menu-search');
                if (input) input.value = '';
                this.render();
            },

            togglePin(key) {
                const i = this.draft.indexOf(key);
                if (i >= 0) {
                    this.draft.splice(i, 1);
                    this.feedback = '';
                } else if (this.draft.length >= this.data.max) {
                    this.feedback = 'Alt bar dolu. Önce bir kısayolu çıkarın.';
                } else {
                    this.draft.push(key);
                    this.feedback = '';
                }
                this.render();
            },

            move(key, dir) {
                const i = this.draft.indexOf(key);
                const j = i + dir;
                if (i < 0 || j < 0 || j >= this.draft.length) return;
                [this.draft[i], this.draft[j]] = [this.draft[j], this.draft[i]];
                this.render();
            },

            resetDefault() {
                this.draft = this.data.default.slice();
                this.feedback = '';
                this.render();
            },

            save() {
                const value = this.draft.length ? this.draft.join(',') : '-';
                const secure = location.protocol === 'https:' ? '; Secure' : '';
                document.cookie = 'pwa_nav=' + encodeURIComponent(value) + '; path=' + this.data.cookiePath + '; max-age=31536000; SameSite=Lax' + secure;
                location.reload();
            },

            row(m, opts) {
                const tag = opts.edit ? 'button' : 'a';
                const a = this.el(tag, 'pwa-nav-sheet-item pwa-nav-sheet-item--' + m.tone + (opts.active ? ' is-active' : '') + (opts.pinned ? ' is-pinned' : ''));
                if (opts.edit) {
                    a.type = 'button';
                    a.addEventListener('click', () => this.togglePin(m.key));
                } else if (opts.onclick) {
                    a.href = 'javascript:void(0)';
                    a.addEventListener('click', opts.onclick);
                } else {
                    a.href = '?page=' + encodeURIComponent(m.key);
                }
                const ic = this.el('span', 'pwa-nav-sheet-item__icon');
                ic.appendChild(this.icon(m.icon));
                const body = this.el('span', 'pwa-nav-sheet-item__body');
                body.appendChild(this.el('span', 'pwa-nav-sheet-item__eyebrow', m.eyebrow));
                body.appendChild(this.el('span', 'pwa-nav-sheet-item__label', m.label));
                a.append(ic, body);
                if (opts.edit) {
                    a.appendChild(this.el('span', 'pwa-nav-sheet-item__pin' + (opts.pinned ? ' is-on' : ''), opts.pinned ? 'ALT BARDA' : 'SABİTLE'));
                } else {
                    a.appendChild(this.icon('chevron_right', 'pwa-nav-sheet-item__arrow'));
                }
                return a;
            },

            render() {
                const content = document.getElementById('more-menu-content');
                if (!content) return;
                content.replaceChildren();

                document.getElementById('more-menu-eyebrow').textContent = this.editMode ? 'KİŞİSELLEŞTİRME' : 'HIZLI ERİŞİM & MENÜ';
                document.getElementById('more-menu-title').textContent = this.editMode ? 'Alt Bar Düzeni' : 'Daha Fazla';
                document.getElementById('more-menu-subtitle').textContent = this.editMode ? 'Alt bardaki kısayolları seçin ve sıralayın' : 'Modüller ve araçlar';
                document.getElementById('more-menu-edit-btn').classList.toggle('pwa-circle-btn--primary', this.editMode);
                document.getElementById('more-menu-search-wrap').hidden = this.editMode;
                document.getElementById('more-menu-footer').hidden = !this.editMode;

                if (this.editMode) {
                    this.renderEdit(content);
                    return;
                }

                const term = this.norm(this.query);
                const list = this.el('div', 'pwa-nav-sheet__list');
                let count = 0;
                this.data.items.forEach((m) => {
                    if (m.key === 'profil' || this.data.nav.includes(m.key)) return;
                    if (term && !this.norm(m.label + ' ' + m.eyebrow).includes(term)) return;
                    list.appendChild(this.row(m, { active: this.data.page === m.key }));
                    count++;
                });
                if (!count) content.appendChild(this.el('div', 'pwa-nav-sheet-empty', 'Aramanızla eşleşen menü bulunamadı.'));
                content.appendChild(list);
            },

            renderEdit(content) {
                if (this.feedback) content.appendChild(this.el('div', 'pwa-nav-sheet-feedback', this.feedback));

                const dock = this.el('div', 'pwa-nav-dock');
                dock.appendChild(this.el('span', 'pwa-nav-dock__title', 'ALT BAR KISAYOLLARI (' + this.draft.length + '/' + this.data.max + ')'));
                const slots = this.el('div', 'pwa-nav-dock__slots');
                const fixed = (iconName, label) => {
                    const s = this.el('div', 'pwa-nav-slot is-fixed');
                    s.append(this.icon(iconName), this.el('span', 'pwa-nav-slot__label', label), this.el('span', 'pwa-nav-slot__tag', 'Sabit'));
                    return s;
                };
                slots.appendChild(fixed('home', 'Ana Sayfa'));
                this.draft.forEach((key, i) => {
                    const m = this.item(key);
                    if (!m) return;
                    const s = this.el('div', 'pwa-nav-slot');
                    const rm = this.el('button', 'pwa-nav-slot__remove');
                    rm.type = 'button';
                    rm.setAttribute('aria-label', m.label + ' kısayolunu çıkar');
                    rm.appendChild(this.icon('close'));
                    rm.addEventListener('click', () => this.togglePin(key));
                    const ctrl = this.el('div', 'pwa-nav-slot__move');
                    const left = this.el('button', '', '');
                    left.type = 'button';
                    left.disabled = i === 0;
                    left.setAttribute('aria-label', 'Sola taşı');
                    left.appendChild(this.icon('chevron_left'));
                    left.addEventListener('click', () => this.move(key, -1));
                    const right = this.el('button', '', '');
                    right.type = 'button';
                    right.disabled = i === this.draft.length - 1;
                    right.setAttribute('aria-label', 'Sağa taşı');
                    right.appendChild(this.icon('chevron_right'));
                    right.addEventListener('click', () => this.move(key, 1));
                    ctrl.append(left, right);
                    s.append(rm, this.icon(m.icon), this.el('span', 'pwa-nav-slot__label', m.navLabel), ctrl);
                    slots.appendChild(s);
                });
                for (let i = this.draft.length; i < this.data.max; i++) {
                    const s = this.el('div', 'pwa-nav-slot is-empty');
                    s.append(this.el('span', 'pwa-nav-slot__plus', '+'), this.el('span', 'pwa-nav-slot__label', 'Boş Slot'));
                    slots.appendChild(s);
                }
                slots.appendChild(fixed('more_horiz', 'Diğer'));
                dock.appendChild(slots);
                content.appendChild(dock);

                const list = this.el('div', 'pwa-nav-sheet__list');
                this.data.items.forEach((m) => {
                    list.appendChild(this.row(m, { edit: true, pinned: this.draft.includes(m.key) }));
                });
                content.appendChild(list);
            },
        };

        document.addEventListener('DOMContentLoaded', () => MoreMenu.init());

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
                MoreMenu.reset();
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
                navigator.serviceWorker.register('./sw.js?v=<?= filemtime(__DIR__ . '/sw.js') ?>', { updateViaCache: 'none' })
                    .then(registration => {
                        console.log('SW registered:', registration);
                        // Tarayıcının periyodik kontrolünü beklemeden yeni worker'ı denetle.
                        // updateViaCache=none, sw.js ve importScripts bağımlılıklarının
                        // eski HTTP önbelleğinden gelmesini engeller.
                        registration.update().catch(error => {
                            console.log('SW update check failed:', error);
                        });
                        cevrimdisiSayfalariHazirla();
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            });
        }

        /**
         * Saha personeli kaçak/ihbar sayfasını çevrimdışıyken de açabilsin diye
         * sayfalar arka planda önden indirilir. Mobil veriyi tüketmemek için
         * altı saatte birden sık tekrarlanmaz.
         */
        async function cevrimdisiSayfalariHazirla() {
            const SAYFALAR = <?= json_encode($cevrimdisiSayfalar, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const ARALIK = 6 * 60 * 60 * 1000;

            if (!navigator.onLine) return;

            try {
                const son = parseInt(localStorage.getItem('cevrimdisiSayfaZamani') || '0', 10);
                if (Date.now() - son < ARALIK) return;

                const reg = await navigator.serviceWorker.ready;
                const sw = reg.active || navigator.serviceWorker.controller;
                if (!sw) return;

                sw.postMessage({ tip: 'sayfalari-onbellekle', sayfalar: SAYFALAR });
                localStorage.setItem('cevrimdisiSayfaZamani', String(Date.now()));
            } catch (e) {
                console.log('Çevrimdışı sayfa hazırlığı atlandı:', e);
            }
        }
    </script>
    <script>
        async function logout() {
            // Gönderilmemiş çevrimdışı kayıt varsa personel çıkmadan önce uyarılır;
            // kuyruk cihazda kalır ama tekrar giriş yapılmadan gönderilemez.
            let bekleyenUyarisi = '';
            try {
                if (window.OfflineQueue) {
                    const bekleyen = (await OfflineQueue.listele()).length;
                    if (bekleyen > 0) {
                        bekleyenUyarisi = `\n\nGönderilmeyi bekleyen ${bekleyen} kaydınız var. `
                            + 'Bu kayıtlar cihazda kalır, tekrar giriş yaptığınızda gönderilir.';
                    }
                }
            } catch (e) { }

            const isConfirmed = await Alert.confirm(
                'Çıkış Yap',
                'Çıkış yapmak istediğinize emin misiniz?' + bekleyenUyarisi,
                'Çıkış Yap',
                'Vazgeç'
            );

            if (!isConfirmed) return;

            try {
                sessionStorage.removeItem('pwaSessionStarted');
                sessionStorage.removeItem('pwaInternalNavigation');
            } catch (e) { }

            try {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ tip: 'oturum-temizle' });
                }
            } catch (e) { }

            try {
                const response = await API.request('logout');
                window.location.href = 'login.php';
            } catch (error) {
                window.location.href = 'login.php';
            }
        }
    </script>

    <!-- ETKİNLİK VE BİLDİRİM TAM EKRAN MODAL MANTIĞI -->
    <script>
        /**
         * Generic Full Screen Modal Loader
         */
        function showPwaFullModal(options) {
            const modal = document.getElementById('pwa-full-modal');
            const container = document.getElementById('pwa-full-modal-content');
            const actionsContainer = document.getElementById('pwa-full-modal-actions');
            
            if (!modal || !container) return;

            // Reset
            actionsContainer.innerHTML = '';
            
            // Render content based on options
            container.innerHTML = options.html || '';
            
            // Add custom actions if any
            if (options.actionsHtml) {
                actionsContainer.innerHTML = options.actionsHtml;
            }

            // Show modal
            modal.style.display = 'flex';
            
            // Allow DOM state update before kicking off CSS transitions
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    modal.classList.remove('translate-y-full');
                });
            });

            try {
                window.history.pushState({ modal: 'pwa-full-modal' }, '', '');
            } catch (err) { }

            // Trigger onOpen callback if provided
            if (typeof options.onOpen === 'function') {
                setTimeout(options.onOpen, 100);
            }
        }

        function closePwaFullModal() {
            const modal = document.getElementById('pwa-full-modal');
            if (!modal) return;

            modal.classList.add('translate-y-full');
            setTimeout(() => {
                modal.style.display = 'none';
                modal.classList.remove('ihbar-form-modal');
                modal.classList.remove('pwa-km-modal');
                document.getElementById('pwa-full-modal-content').innerHTML = ''; // memory clean
            }, 300);
        }

        function showEtkinlikFullScreen(duyuruStr) {
            let duyuru;
            try {
                duyuru = typeof duyuruStr === 'string' ? JSON.parse(duyuruStr) : duyuruStr;
            } catch (e) {
                console.error('JSON Parse error', e);
                return;
            }

            const bgImg = `background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);`;
            const hasImage = duyuru.resim ? true : false;
            
            let fileAttachmentHtml = '';
            if (hasImage) {
                fileAttachmentHtml = `
                    <div class="duyuru-attach">
                        <p class="duyuru-attach-label">EKLİ GÖRSEL / DOSYA</p>
                        <img src="${escapeHtml(duyuru.resim)}" class="duyuru-attach-img" alt="Etkinlik Görseli" onerror="this.closest('.duyuru-attach').remove()">
                    </div>
                `;
            }

            let linkHtml = '';
            if (duyuru.hedef_sayfa) {
                linkHtml += `
                <a href="${escapeHtml(duyuru.hedef_sayfa)}" class="duyuru-btn duyuru-btn--primary">
                    <span>Detayı İncele</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
                `;
            }

            linkHtml += `
            <a href="?page=etkinlikler" class="duyuru-btn duyuru-btn--ghost">
                <span class="material-symbols-outlined">format_list_bulleted</span>
                <span>Tüm Duyuruları Gör</span>
            </a>
            `;

            let kalanGunHtml = '';
            if (duyuru.kalan_gun) {
                kalanGunHtml = `<span class="duyuru-hero-num" aria-hidden="true">${escapeHtml(duyuru.kalan_gun)}</span>`;
            } else if (duyuru.gecmis) {
                kalanGunHtml = `<span class="duyuru-hero-pill duyuru-hero-pill--past">Geçmiş Etkinlik</span>`;
            }

            const html = `
                <div class="duyuru-detay">
                    <div class="duyuru-hero" style="${bgImg}">
                        ${kalanGunHtml}
                        <div class="duyuru-hero-body">
                            <span class="duyuru-hero-pill">${escapeHtml(duyuru.tarih)}</span>
                            <h1 class="duyuru-hero-title">${escapeHtml(duyuru.baslik)}</h1>
                            ${duyuru.kalan_gun ? `<div class="duyuru-hero-meta"><span class="duyuru-hero-dot"></span><span>${escapeHtml(duyuru.kalan_gun)} GÜN KALDI</span></div>` : ''}
                        </div>
                    </div>

                    <div class="duyuru-body-card">
                        <div class="duyuru-content">${duyuru.icerik || ''}</div>
                        ${fileAttachmentHtml}
                    </div>
                    ${linkHtml}
                </div>
            `;

            showPwaFullModal({ html: html });
        }

        function closeEtkinlikFullScreen() { closePwaFullModal(); }
    </script>

    <?php include "includes/km-modal.php"; ?>
    <script>
        // Catch the native back button
        window.addEventListener('popstate', function (e) {
            const modalFS = document.getElementById('pwa-full-modal');
            if (modalFS && modalFS.style.display !== 'none') {
                closePwaFullModal();
            }
        });
    </script>
</body>

</html>
