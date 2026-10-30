<?php
/**
 * Personel PWA - Giriş Sayfası
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Clear any stale permission cache to ensure fresh permissions load
unset($_SESSION['permission_cache']);

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\PersonelModel;
use App\Model\SettingsModel;
use App\Model\MesajLogModel;
use App\Model\PersonelGirisLogModel;

function pwaGirisSonrasiAdres(): string
{
    $token = $_SESSION['bordro_yayin_donus'] ?? null;
    unset($_SESSION['bordro_yayin_donus']);
    return is_string($token) && strlen($token) <= 2048
        ? 'index.php?page=bordro&dokum=' . rawurlencode($token) : 'index.php';
}

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['personel_id'])) {
    header("Location: " . pwaGirisSonrasiAdres());
    exit();
}

$error = '';

// Şifre sıfırlama isteği (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Bir hata oluştu.'];

    $phone = $_POST['phone'] ?? '';




    if (empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Lütfen telefon numaranızı girin.']);
        exit;
    }

    try {
        $PersonelModel = new PersonelModel();
        $db = $PersonelModel->getDb();

        function normalizePhone($phone)
        {
            $phone = preg_replace('/\D/', '', $phone); // sadece rakamlar
            return substr($phone, -10);                 // son 10 hane
        }


        // Telefon numarasını temizle (boşlukları vs sil)
        $phone = normalizePhone($phone);

        // Başında 0 varsa veya yoksa diye kontrol et
        $stmt = $db->prepare(
            "SELECT * FROM personel
             WHERE (cep_telefonu = :phone OR cep_telefonu = :phone_with_zero)
               AND silinme_tarihi IS NULL
               AND aktif_mi <> 0"
        );
        $stmt->execute(['phone' => $phone, 'phone_with_zero' => '0' . $phone]); // Son 10 hanesine ve sıfırlı haline bak
        $personel = $stmt->fetch(PDO::FETCH_OBJ);


        if ($personel) {
            // Yeni şifre oluştur
            $newPass = rand(100000, 999999);
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);

            // DB güncelle
            $updateStmt = $db->prepare("UPDATE personel SET sifre = :sifre WHERE id = :id");
            $update = $updateStmt->execute(['sifre' => $newHash, 'id' => $personel->id]);

            if ($update) {
                // SMS Gönderimi
                $Settings = new SettingsModel();
                $allSettings = $Settings->getAllSettingsAsKeyValue();
                $username = $allSettings['sms_api_kullanici'] ?? '';
                $password = $allSettings['sms_api_sifre_yeni'] ?? '';
                $msgheader = $allSettings['sms_baslik'] ?? '';

                //Helper::dd($allSettings);

                if ($username && $password && $msgheader) {
                    $messageText = "Sayın {$personel->adi_soyadi}, yeni şifreniz: {$newPass}";
                    $recipients = [$personel->cep_telefonu];

                    $data = [
                        "msgheader" => $msgheader,
                        "messages" => [
                            ['msg' => $messageText, 'no' => $personel->cep_telefonu]
                        ],
                        "encoding" => "TR"
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://api.netgsm.com.tr/sms/rest/v2/send");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Basic ' . base64_encode($username . ':' . $password)
                    ]);

                    $smsResponse = curl_exec($ch);
                    curl_close($ch);

                    // Loglama
                    try {
                        $MesajLogModel = new MesajLogModel();
                        $firmaId = 1; // Varsayılan firma ID
                        $MesajLogModel->logSms($firmaId, $msgheader, $recipients, $messageText, 'success');
                    } catch (Exception $e) {
                    }

                    $response = ['status' => 'success', 'message' => 'Yeni şifreniz telefonunuza SMS olarak gönderildi.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'SMS servisi yapılandırılmamış. Lütfen yönetici ile iletişime geçin.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Şifre güncellenemedi.'];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Bu telefon numarası ile kayıtlı personel bulunamadı.'];
        }
    } catch (Exception $e) {
        error_log('PWA reset_password hatası: ' . $e->getMessage());
        $response = ['status' => 'error', 'message' => 'Bir sistem hatası oluştu. Lütfen tekrar deneyin.'];
    }

    echo json_encode($response);
    exit;
}

$error = '';

// Check if user was kicked out for being passive
if (isset($_GET['status']) && $_GET['status'] === 'inactive') {
    $error = 'Hesabınız pasif duruma getirildiği için oturumunuz sonlandırıldı.';
}

// Form gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwaIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pwaLockKey = 'pwa_locked_until_' . md5($pwaIp);
    $pwaAttemptsKey = 'pwa_attempts_' . md5($pwaIp);

    if (isset($_SESSION[$pwaLockKey]) && $_SESSION[$pwaLockKey] > time()) {
        $wait = ceil(($_SESSION[$pwaLockKey] - time()) / 60);
        $error = "Çok fazla başarısız deneme. $wait dakika sonra tekrar deneyin.";
    } else {
    $login_input = $_POST['login_input'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $PersonelModel = new PersonelModel();


        // TC Kimlik No veya Telefon ile personeli bul
        // Model üzerinden DB bağlantısını alıp özel sorgu atıyoruz
        $phone = preg_replace('/\D/', '', $login_input); // sadece rakamlar

        $phone = preg_replace('/^0/', '', $phone);


        $db = $PersonelModel->getDb();
        $tcHash = hash('sha256', $login_input);
        $stmt = $db->prepare(
            "SELECT * FROM personel
             WHERE (tc_hash = :tc_hash OR cep_telefonu = :telefon OR cep_telefonu = :telefon_with_zero)
               AND silinme_tarihi IS NULL
               AND aktif_mi <> 0"
        );
        $stmt->execute(['tc_hash' => $tcHash, 'telefon' => $phone, 'telefon_with_zero' => '0' . $phone]);
        $personel = $stmt->fetch(PDO::FETCH_OBJ);
        if ($personel) {
            $personel = $PersonelModel->decryptFields($personel);
        }

        if ($personel) {
            // Durum Kontrolü (İşten çıkış tarihi varsa pasiftir)
            $isPassive = (!empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00');
            
            if ($isPassive) {
                $error = 'Hesabınız pasif durumdadır. Lütfen yönetici ile iletişime geçiniz.';
            } else {
                // Şifre kontrolü - hash'lenmiş şifre ile doğrula
                $dbPassword = $personel->sifre ?? '';
                
                // Şifre boşsa veya null ise giriş yapılamaz
                if (empty($dbPassword)) {
                    $error = 'Bu hesap için henüz şifre belirlenmemiş. Lütfen yöneticinizle iletişime geçin.';
                } elseif (password_verify($password, $dbPassword)) {
                    unset($_SESSION[$pwaAttemptsKey], $_SESSION[$pwaLockKey]);
                    $_SESSION['personel_id'] = $personel->id;
                    $_SESSION['personel_tc'] = $personel->tc_kimlik_no;
                    $_SESSION['personel_adi'] = $personel->adi_soyadi;

                    // Log the successful login
                    try {
                        $girisLogModel = new PersonelGirisLogModel();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $girisLogModel->logLogin($personel->id, $ip, $userAgent);
                    } catch (\Exception $e) {
                        error_log("Login log error: " . $e->getMessage());
                    }

                    // Beni Hatırla
                    if (isset($_POST['remember'])) {
                        $pwaSecret = $_ENV['PWA_HMAC_SECRET'] ?? '';
                        if (empty($pwaSecret)) {
                            error_log('PWA_HMAC_SECRET .env dosyasında tanımlı değil, Beni Hatırla devre dışı.');
                        }
                        $token = base64_encode($personel->id . ':' . hash_hmac('sha256', $personel->id . $personel->sifre, $pwaSecret));
                        setcookie('remember_token', $token, ['expires' => time() + (86400 * 30), 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                    }

                    header("Location: " . pwaGirisSonrasiAdres());
                    exit();
                } else {
                    $_SESSION[$pwaAttemptsKey] = ($_SESSION[$pwaAttemptsKey] ?? 0) + 1;
                    if ($_SESSION[$pwaAttemptsKey] >= 5) {
                        $_SESSION[$pwaLockKey] = time() + 900;
                        unset($_SESSION[$pwaAttemptsKey]);
                    }
                    $error = 'Şifre hatalı.';
                }
            }
        } else {
            $_SESSION[$pwaAttemptsKey] = ($_SESSION[$pwaAttemptsKey] ?? 0) + 1;
            if ($_SESSION[$pwaAttemptsKey] >= 5) {
                $_SESSION[$pwaLockKey] = time() + 900;
                unset($_SESSION[$pwaAttemptsKey]);
            }
            $error = 'Bu bilgilerle kayıtlı personel bulunamadı.';
        }
    }
    }
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
    <link rel="icon" type="image/x-icon" href="assets/icons/app-favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/app-192.png?v=2">
    <link rel="apple-touch-icon" href="assets/icons/app-apple-touch-180.png?v=2">

    <title>Giriş - Ersan Elektrik</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Theme Pre-init -->
    <script>
        (function () {
            const themes = {
                blue: { primary: '#135bec', dark: '#0d47c1' },
                purple: { primary: '#7c3aed', dark: '#6d28d9' },
                green: { primary: '#059669', dark: '#047857' },
                red: { primary: '#dc2626', dark: '#b91c1c' },
                orange: { primary: '#ea580c', dark: '#c2410c' },
                pink: { primary: '#db2777', dark: '#be185d' },
                teal: { primary: '#0d9488', dark: '#0f766e' },
                slate: { primary: '#475569', dark: '#334155' },
            };
            const saved = localStorage.getItem('themeColor') || 'blue';
            const t = themes[saved] || themes.blue;
            window.__activeTheme = t;

            const r = document.documentElement;
            r.style.setProperty('--primary', t.primary);
            r.style.setProperty('--primary-dark', t.dark);

            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', t.primary);
        })();
    </script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": window.__activeTheme.primary,
                        "primary-dark": window.__activeTheme.dark,
                    },
                    fontFamily: {
                        "display": ["Roboto Condensed", "sans-serif"]
                    }
                },
            },
        }
    </script>

    <style>
        body {
            font-family: 'Roboto Condensed', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            margin: 0;
            min-height: 100svh;
            background: #e6eaef;
        }

        .auth-screen {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            max-width: 480px;
            margin: 0 auto;
            background: #e6eaef;
        }

        .auth-visual {
            position: relative;
            min-height: 44svh;
            background: #191d24;
            color: #f5f7fa;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: calc(16px + env(safe-area-inset-top)) 20px 36px;
        }

        .auth-top-bar { display: flex; justify-content: flex-end; }

        .auth-status {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(15, 23, 42, 0.62); border: 1px solid rgba(255, 255, 255, 0.16);
            color: #f8fafc; font-size: 11px; font-weight: 600;
        }
        .auth-status i { width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.65); }

        .auth-brand-content { display: flex; flex-direction: column; align-items: flex-start; margin: auto 0; padding: 8px 0 16px; }

        .auth-splash-stage {
            position: relative; width: 156px; height: 156px; display: grid; place-items: center;
            perspective: 900px; margin-left: -12px; cursor: pointer; -webkit-tap-highlight-color: transparent;
        }
        .splash-glow {
            position: absolute; inset: -20px; border-radius: 50%;
            background: radial-gradient(circle, rgba(201, 151, 56, 0.38) 0%, rgba(255, 255, 255, 0.12) 48%, transparent 72%);
            filter: blur(14px); animation: splash-glow-pulse 2.6s ease-in-out infinite alternate; pointer-events: none;
        }
        .splash-orbit {
            position: absolute; inset: 0; border-radius: 50%;
            border: 1.5px solid rgba(255, 255, 255, 0.14); border-top-color: #c99738; border-right-color: rgba(255, 255, 255, 0.85);
            box-shadow: 0 0 20px rgba(201, 151, 56, 0.22); animation: splash-orbit-spin 3.2s linear infinite; pointer-events: none;
        }
        .splash-orbit--inner {
            inset: 12px; border: 1px dashed rgba(201, 151, 56, 0.35); border-bottom-color: #ffffff;
            animation: splash-orbit-spin-rev 4.4s linear infinite;
        }
        .splash-logo-card-3d {
            position: relative; width: 104px; height: 104px; border-radius: 24px; transform-style: preserve-3d; overflow: hidden;
            box-shadow: 0 22px 44px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.18);
            animation: splash-logo-3d-motion 3.4s ease-in-out infinite alternate;
        }
        .splash-logo-card-3d img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .splash-logo-card-3d::after {
            content: ''; position: absolute; top: -40%; left: -120%; width: 75%; height: 180%;
            background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.58) 50%, transparent 100%);
            transform: rotate(28deg); animation: splash-logo-sheen 2.8s cubic-bezier(0.4, 0, 0.2, 1) infinite; pointer-events: none;
        }
        .auth-splash-stage.is-spinning-3d .splash-logo-card-3d { animation: splash-logo-3d-click-spin 0.85s cubic-bezier(0.22, 1, 0.36, 1) forwards !important; }

        .auth-brand-titles { margin-top: 14px; }
        .auth-brand-name { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #c99738; text-shadow: 0 2px 14px rgba(0, 0, 0, 0.65); }
        .auth-brand-sub { margin: 4px 0 0; font-size: 13.5px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #d4b478; opacity: 0.94; }
        .auth-brand-slogan { margin: 10px 0 0; font-size: 14.5px; font-weight: 600; letter-spacing: 0.03em; color: rgba(245, 248, 252, 0.92); display: flex; align-items: center; gap: 10px; }
        .auth-brand-slogan::before { content: ''; width: 20px; height: 2px; border-radius: 2px; background: #c99738; }

        .auth-form {
            position: relative; z-index: 4; flex: 1; margin-top: -24px;
            display: grid; align-content: start; gap: 14px;
            padding: 12px 20px calc(24px + env(safe-area-inset-bottom));
            border-radius: 26px 26px 0 0; background: #ffffff;
            box-shadow: 0 -12px 40px rgba(5, 22, 48, 0.12);
        }
        .sheet-handle { width: 38px; height: 4px; margin: 0 auto 4px; border-radius: 99px; background: #e4e6e9; }
        .section-kicker { color: #135bec; font-size: 10.5px; font-weight: 800; letter-spacing: 0.14em; }
        .auth-form h2 { margin: 4px 0 0; font-size: 24px; font-weight: 800; letter-spacing: -0.04em; color: #0f172a; }
        .auth-form .auth-lead { margin: 4px 0 0; font-size: 12px; color: #64748b; }

        .form-alert { display: flex; align-items: center; gap: 8px; padding: 11px 13px; border: 1px solid #ffd4cc; border-radius: 13px; color: #b83e2c; background: #fff2ef; font-size: 12px; line-height: 1.45; }

        .auth-field { display: grid; gap: 6px; color: #4f5968; font-size: 11px; font-weight: 700; }
        .auth-field > div {
            position: relative; display: flex; height: 52px; align-items: center; gap: 11px; padding: 0 14px;
            border: 1px solid #e4e7eb; border-radius: 17px; color: #9aa0aa; background: #f7f8f9;
            transition: border-color .2s, background .2s;
        }
        .auth-field > div:focus-within { border-color: #78a6f7; background: #ffffff; }
        .auth-field input[type="text"], .auth-field input[type="password"], .auth-field input[type="tel"] {
            flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; color: #0f172a; font-size: 14px; font-weight: 600; font-family: inherit;
        }
        .auth-field input::placeholder { color: #b2b6bd; font-weight: 500; }
        .auth-field button { display: grid; place-items: center; min-width: 44px; min-height: 44px; margin-right: -10px; color: #8e949d; background: transparent; border: 0; }

        .auth-options { display: flex; align-items: center; justify-content: space-between; font-size: 12px; padding: 0 2px; }
        .auth-options label { display: inline-flex; min-height: 44px; align-items: center; gap: 7px; color: #6f7784; cursor: pointer; }
        .auth-options input { width: 16px; height: 16px; accent-color: #191d24; }
        .auth-options a { display: inline-flex; min-height: 44px; align-items: center; color: #0f172a; font-weight: 700; text-decoration: none; }

        .login-button {
            display: flex; height: 52px; align-items: center; justify-content: space-between; width: 100%;
            padding: 0 12px 0 24px; border: 0; border-radius: 999px; color: #ffffff; background: #191d24;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.22); font-family: inherit; font-size: 14px; font-weight: 800; cursor: pointer;
        }
        .login-button:active { transform: scale(0.985); }
        .login-button .arrow { width: 34px; height: 34px; border-radius: 50%; background: #ffffff; color: #191d24; display: grid; place-items: center; }
        .login-button .arrow .material-symbols-outlined { font-size: 20px; }

        .auth-help { display: flex; align-items: center; justify-content: center; gap: 5px; margin: 0; color: #a2a7af; font-size: 11px; }
        .auth-help .material-symbols-outlined { font-size: 14px; }

        @keyframes splash-orbit-spin { to { transform: rotate(360deg); } }
        @keyframes splash-orbit-spin-rev { to { transform: rotate(-360deg); } }
        @keyframes splash-glow-pulse { 0% { transform: scale(0.92); opacity: 0.6; } 100% { transform: scale(1.08); opacity: 0.95; } }
        @keyframes splash-logo-3d-motion {
            0% { transform: translateY(-3px) rotateY(-12deg) rotateX(9deg) scale(1); }
            50% { transform: translateY(3px) rotateY(12deg) rotateX(-7deg) scale(1.03); }
            100% { transform: translateY(-2px) rotateY(-6deg) rotateX(11deg) scale(1.01); }
        }
        @keyframes splash-logo-sheen { 0% { left: -120%; } 55%, 100% { left: 165%; } }
        @keyframes splash-logo-3d-click-spin {
            0% { transform: scale(1) rotateY(0deg) rotateX(0deg); }
            50% { transform: scale(1.15) rotateY(180deg) rotateX(12deg); }
            100% { transform: scale(1) rotateY(360deg) rotateX(0deg); }
        }

        /* Toast Styles */
        :root {
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        .toast {
            padding: 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            animation: slideInDown 0.3s ease-out;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            pointer-events: auto;
        }

        .toast-success {
            background: var(--success);
            color: white;
        }

        .toast-error {
            background: var(--danger);
            color: white;
        }

        .toast-warning {
            background: var(--warning);
            color: white;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    </style>
</head>

<body class="font-display">
    <section class="auth-screen">
        <div class="auth-visual">
            <div class="auth-top-bar">
                <span class="auth-status"><i></i> Sistem aktif</span>
            </div>

            <div class="auth-brand-content">
                <div class="auth-splash-stage" id="splash-stage" title="Ersan Elektrik 3D" role="button" tabindex="0">
                    <div class="splash-glow"></div>
                    <div class="splash-orbit"></div>
                    <div class="splash-orbit splash-orbit--inner"></div>
                    <div class="splash-logo-card-3d">
                        <img src="assets/images/logo-3d-splash.png" alt="Er-San Elektrik" width="104" height="104">
                    </div>
                </div>

                <div class="auth-brand-titles">
                    <h1 class="auth-brand-name">ER-SAN ELEKTRİK</h1>
                    <p class="auth-brand-sub">Personel Portalı</p>
                    <p class="auth-brand-slogan">İşin Sahada, Kontrol Sende</p>
                </div>
            </div>
        </div>

        <form method="POST" class="auth-form">
            <span class="sheet-handle" aria-hidden="true"></span>
            <div>
                <span class="section-kicker">GÜVENLİ GİRİŞ</span>
                <h2>Tekrar hoş geldiniz</h2>
                <p class="auth-lead">Hesabınıza kaldığınız yerden devam edin.</p>
            </div>

            <?php if ($error): ?>
                <div class="form-alert" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <label class="auth-field">
                <span>TC Kimlik No veya Telefon</span>
                <div>
                    <span class="material-symbols-outlined">badge</span>
                    <input type="text" name="login_input" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="TC No veya Telefon" required>
                </div>
            </label>

            <label class="auth-field">
                <span>Şifre</span>
                <div>
                    <span class="material-symbols-outlined">lock</span>
                    <input type="password" name="password" id="password" autocomplete="current-password" placeholder="Şifreniz" required>
                    <button type="button" onclick="togglePassword()" aria-label="Şifreyi göster/gizle">
                        <span class="material-symbols-outlined" id="password-icon">visibility</span>
                    </button>
                </div>
            </label>

            <div class="auth-options">
                <label><input type="checkbox" name="remember"> <span>Beni hatırla</span></label>
                <a href="javascript:void(0)" onclick="openForgotModal()">Şifremi unuttum</a>
            </div>

            <button type="submit" class="login-button">
                <span>Giriş yap</span>
                <span class="arrow"><span class="material-symbols-outlined">arrow_forward</span></span>
            </button>

            <p class="auth-help"><span class="material-symbols-outlined">verified_user</span> Bilgileriniz güvenli bağlantıyla korunur.</p>
        </form>
    </section>

    <!-- Forgot Password Modal -->
    <div id="forgot-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeForgotModal()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl p-6 transform transition-transform duration-300 translate-y-full"
            id="forgot-modal-content">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-6"></div>

            <h3 class="text-xl font-bold text-slate-900 mb-2">Şifremi Unuttum</h3>
            <p class="text-slate-500 text-sm mb-6">Kayıtlı telefon numaranızı girin, yeni şifrenizi SMS olarak
                gönderelim.</p>

            <form onsubmit="handleForgotSubmit(event)" class="flex flex-col gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Telefon Numarası</label>
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">phone_iphone</span>
                        <input type="tel" name="phone" id="forgot-phone"
                            class="w-full pl-12 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="05XX XXX XX XX" required>
                    </div>
                </div>

                <button type="submit" id="forgot-submit-btn"
                    class="w-full py-3.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-dark transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                    <span>Şifre Gönder</span>
                    <span class="material-symbols-outlined text-lg">send</span>
                </button>
            </form>

            <button onclick="closeForgotModal()"
                class="w-full py-3.5 mt-2 text-slate-500 font-semibold rounded-xl hover:bg-slate-50 transition-all">
                Vazgeç
            </button>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center">
        <p class="text-white/40 text-xs">© 2024 Ersan Elektrik. Tüm hakları saklıdır.</p>
    </div>

    <div id="toast-container" class="fixed top-4 left-4 right-4 z-[110] flex flex-col gap-2"></div>

    <script>
        // Toast Notifications
        const Toast = {
            container: null,

            init() {
                this.container = document.getElementById("toast-container");
            },

            show(message, type = "success", duration = 3000) {
                if (!this.container) this.init();
                if (!this.container) return;

                const toast = document.createElement("div");
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <span class="material-symbols-outlined text-xl">
                        ${type === "success" ? "check_circle" : type === "error" ? "error" : type === "warning" ? "warning" : "info"}
                    </span>
                    <span>${message}</span>
                `;

                this.container.appendChild(toast);

                setTimeout(() => {
                    toast.style.animation = "slideOutUp 0.3s ease-out forwards";
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            },
        };

        (function () {
            const stage = document.getElementById('splash-stage');
            if (!stage) return;
            const spin = function () {
                if (stage.classList.contains('is-spinning-3d')) return;
                stage.classList.add('is-spinning-3d');
                setTimeout(function () { stage.classList.remove('is-spinning-3d'); }, 850);
            };
            stage.addEventListener('click', spin);
            stage.addEventListener('keydown', function (e) { if (e.key === 'Enter') spin(); });
        })();

        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('password-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // İOS için input focus scroll fix
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', () => {
                setTimeout(() => {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });

        // Forgot Password Modal Logic
        function openForgotModal() {
            const modal = document.getElementById('forgot-modal');
            const content = document.getElementById('forgot-modal-content');
            modal.classList.remove('hidden');
            // Trigger reflow
            void modal.offsetWidth;
            content.classList.remove('translate-y-full');
        }

        function closeForgotModal() {
            const modal = document.getElementById('forgot-modal');
            const content = document.getElementById('forgot-modal-content');
            content.classList.add('translate-y-full');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        async function handleForgotSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('forgot-submit-btn');
            const originalText = btn.innerHTML;
            const phone = document.getElementById('forgot-phone').value;

            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">refresh</span> Gönderiliyor...';

            try {
                const formData = new FormData();
                formData.append('action', 'reset_password');
                formData.append('phone', phone);

                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    Toast.show(result.message, 'success');
                    closeForgotModal();
                } else {
                    Toast.show(result.message, 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>

</html>
