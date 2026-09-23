<?php

require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
use App\Model\UserModel;
use App\Model\SystemLogModel;
use App\Helper\Helper;
use App\Helper\Security;

$User = new UserModel();

// Initialize the session
session_start();

// Clear any stale permission cache to ensure fresh permissions load
unset($_SESSION['permission_cache']);
unset($_SESSION['topbar_firma_option_cache']);

// Check if the user is already logged in, if yes then redirect him to index page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (($_SESSION['portal_scope'] ?? '') === 'kaski') {
        header('location: kaski/index.php');
        exit;
    }
    header("location: index?p=home");
    exit;
}

// Check for remember me cookie
if (isset($_COOKIE["remember_me"])) {
    $decrypted_id = Security::decrypt($_COOKIE["remember_me"]);
    if ($decrypted_id) {
        $user = $User->find($decrypted_id);
        if ($user && ($user->durum ?? 'Aktif') !== 'Pasif') {
            session_regenerate_id(true);
            $_SESSION["loggedin"] = true;
            $_SESSION["user"] = $user;
            $_SESSION["user_id"] = $user->id;
            $_SESSION["id"] = $user->id;
            $_SESSION["owner_id"] = $user->owner_id;
            $_SESSION["username"] = $user->user_name;
            $_SESSION["user_full_name"] = $user->adi_soyadi;
            $_SESSION["sube_id"] = $user->sube_id;
            $_SESSION["personel_id"] = $user->personel_id ?? 0;

            // Mobil cihazdan giriş kontrolü
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
                $_SESSION['mobile_login_alert'] = true;
            }

            // Log the automatic login
            try {
                $SystemLog = new SystemLogModel();
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $SystemLog->logAction(
                    $user->id,
                    'Başarılı Giriş (Beni Hatırla)',
                    "{$user->adi_soyadi} ({$user->user_name}) sisteme otomatik giriş yaptı (Beni Hatırla). IP: {$ip}",
                    SystemLogModel::LEVEL_IMPORTANT
                );
            } catch (\Exception $e) { /* Loglama hatası sessiz geçilir */
            }

            header("location: firma-secim.php");
            exit;
        }
    }
}

$username = $password = "";
$username_err = $password_err = "";

if (isset($_GET["status"]) && $_GET["status"] === "inactive") {
    $username_err = "Hesabınız pasif duruma getirildiği için oturumunuz sonlandırıldı.";
}

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$lockKey = 'login_attempts_' . md5($ip);

if (!isset($_SESSION[$lockKey])) {
    $_SESSION[$lockKey] = ['count' => 0, 'first_attempt' => time()];
}

$attemptData = &$_SESSION[$lockKey];

if ($attemptData['count'] >= LOGIN_MAX_ATTEMPTS) {
    $elapsed = time() - $attemptData['first_attempt'];
    if ($elapsed < LOGIN_LOCKOUT_SECONDS) {
        $remaining = ceil((LOGIN_LOCKOUT_SECONDS - $elapsed) / 60);
        $username_err = "Çok fazla başarısız giriş denemesi. {$remaining} dakika sonra tekrar deneyin.";
    } else {
        $attemptData = ['count' => 0, 'first_attempt' => time()];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($username_err)) {

    if (empty(trim($_POST["username"]))) {
        $username_err = "Kullanıcı adı giriniz.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Şifrenizi giriniz.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        $user = $User->checkUser($username);

        // Check if username exists, if yes then verify password
        if ($user) {
            // Durum Kontrolü
            if (($user->durum ?? 'Aktif') === 'Pasif') {
                $username_err = "Hesabınız pasif durumdadır. Lütfen yönetici ile iletişime geçiniz.";
            } else {
                $hashed_password = $user->password;

                if (password_verify($password, $hashed_password)) {
                    unset($_SESSION[$lockKey]);
                    session_regenerate_id(true);
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user"] = $user;
                    $_SESSION["user_id"] = $user->id;
                    $_SESSION["id"] = $user->id;
                    $_SESSION["owner_id"] = $user->owner_id;
                    $_SESSION["username"] = $username;
                    $_SESSION["user_full_name"] = $user->adi_soyadi;
                    $_SESSION["sube_id"] = $user->sube_id;
                    $_SESSION["personel_id"] = $user->personel_id ?? 0;
                    unset($_SESSION['topbar_firma_option_cache']);
                    unset($_SESSION['permission_cache']);

                    // Remember Me
                    if (isset($_POST["remember"])) {
                        $encrypted_user_id = Security::encrypt($user->id);
                        setcookie("remember_me", $encrypted_user_id, [
                            'expires'  => time() + (30 * 24 * 60 * 60),
                            'path'     => '/',
                            'secure'   => true,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }

                    // Redirect user to welcome page
                    try {
                        $SystemLog = new SystemLogModel();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                        $SystemLog->logAction(
                            $user->id,
                            'Başarılı Giriş',
                            "{$user->adi_soyadi} ({$username}) sisteme giriş yaptı. IP: {$ip}",
                            SystemLogModel::LEVEL_IMPORTANT
                        );
                    } catch (\Exception $e) { /* Loglama hatası sessiz geçilir */
                    }

                    header("location: firma-secim.php");
                    exit;
                } else {
                    $password_err = "Hatalı şifre girdiniz.";
                    $attemptData['count']++;
                    if ($attemptData['count'] === 1) {
                        $attemptData['first_attempt'] = time();
                    }

                    // Başarısız giriş denemesini logla
                    try {
                        $SystemLog = new SystemLogModel();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                        $SystemLog->logAction(
                            $user->id,
                            'Başarısız Giriş Denemesi',
                            "{$user->adi_soyadi} ({$username}) için hatalı şifre denemesi. IP: {$ip}",
                            SystemLogModel::LEVEL_IMPORTANT
                        );
                    } catch (\Exception $e) { /* Loglama hatası sessiz geçilir */
                    }
                }
            }
        } else {
            $username_err = "Kullanıcı bulunamadı";
            $attemptData['count']++;
            if ($attemptData['count'] === 1) {
                $attemptData['first_attempt'] = time();
            }

            // Bulunamayan kullanıcı denemesini logla
            try {
                $SystemLog = new SystemLogModel();
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $SystemLog->logAction(
                    0,
                    'Bilinmeyen Giriş Denemesi',
                    "'{$username}' kullanıcı adıyla giriş denemesi yapıldı (kullanıcı bulunamadı). IP: {$ip}",
                    SystemLogModel::LEVEL_IMPORTANT
                );
            } catch (\Exception $e) { /* Loglama hatası sessiz geçilir */
            }
        }
    }
}
?>
<?php include 'layouts/main.php'; ?>

<head>
    <title>Giriş Yap | Ersan Elektrik - Personel Yönetim Sistemi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Ersan Elektrik Personel Yönetim Sistemi Kurumsal Giriş Portalı" name="description" />
    <meta content="mbeyazilim" name="author" />
    <link rel="shortcut icon" href="assets/images/favicon.ico">

    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

    <style>
        :root {
            --portal-primary: #e2bd61;
            --portal-primary-hover: #cfab4f;
            --portal-primary-light: rgba(226, 189, 97, 0.12);
            --portal-primary-glow: rgba(226, 189, 97, 0.35);
            --portal-dark: #0b0f19;
            --portal-dark-card: #111827;
            --portal-slate: #1e293b;
            --portal-text-main: #0f172a;
            --portal-text-muted: #64748b;
            --portal-border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background-color: #f8fafc;
            color: var(--portal-text-main);
            overflow-x: hidden;
            min-height: 100vh;
        }

        .premium-login-wrapper {
            min-height: 100vh;
            display: flex;
            width: 100%;
        }

        /* Left / Form Section */
        .auth-form-column {
            flex: 0 0 100%;
            max-width: 100%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 2rem;
            position: relative;
            z-index: 2;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.03);
        }

        @media (min-width: 992px) {
            .auth-form-column {
                flex: 0 0 42%;
                max-width: 42%;
                padding: 3.5rem 3.5rem;
            }
        }

        @media (min-width: 1400px) {
            .auth-form-column {
                flex: 0 0 36%;
                max-width: 36%;
                padding: 4rem 4.5rem;
            }
        }

        .auth-card-inner {
            width: 100%;
            max-width: 420px;
            margin: auto;
        }

        /* Logo and Header Styling */
        .brand-header {
            margin-bottom: 2rem;
        }

        .brand-logo-img {
            max-height: 48px;
            width: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .brand-logo-img:hover {
            transform: scale(1.02);
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border: 1px solid #e2e8f0;
        }

        .portal-badge .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--portal-primary);
            box-shadow: 0 0 6px var(--portal-primary);
        }

        .auth-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }

        .auth-subtitle {
            font-size: 0.925rem;
            color: #64748b;
            margin-bottom: 1.75rem;
        }

        /* Modern Input Groups */
        .premium-input-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .premium-input-label {
            display: block;
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .input-box-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box-wrapper .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 1.15rem;
            pointer-events: none;
            transition: color 0.2s ease, transform 0.2s ease;
            z-index: 3;
        }

        .premium-input {
            width: 100%;
            height: 48px;
            padding: 0.65rem 1rem 0.65rem 42px;
            font-size: 0.925rem;
            font-weight: 500;
            color: #0f172a;
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .premium-input.has-toggle {
            padding-right: 44px;
        }

        .premium-input:hover {
            background-color: #ffffff;
            border-color: #cbd5e1;
        }

        .premium-input:focus {
            background-color: #ffffff;
            border-color: var(--portal-primary);
            box-shadow: 0 0 0 4px var(--portal-primary-light);
            outline: none;
        }

        .premium-input:focus + .input-icon,
        .input-box-wrapper:focus-within .input-icon {
            color: #d97706;
            transform: scale(1.05);
        }

        .password-toggle-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 8px;
            cursor: pointer;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 4;
        }

        .password-toggle-btn:hover {
            color: #334155;
            background-color: #f1f5f9;
        }

        /* Error States */
        .is-invalid-field {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .is-invalid-field:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15) !important;
        }

        .field-error-msg {
            display: block;
            font-size: 0.775rem;
            color: #ef4444;
            font-weight: 500;
            margin-top: 0.35rem;
            padding-left: 2px;
        }

        .alert-premium-error {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Custom Checkbox */
        .custom-remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            user-select: none;
            margin-bottom: 0;
        }

        .custom-remember-label input[type="checkbox"] {
            width: 17px;
            height: 17px;
            border-radius: 5px;
            border: 1.5px solid #cbd5e1;
            accent-color: #d97706;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .custom-remember-label input[type="checkbox"]:active {
            transform: scale(0.92);
        }

        .forgot-link {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #b45309;
            text-decoration: underline;
        }

        /* Primary CTA Button */
        .btn-portal-submit {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #e2bd61 0%, #d4a942 100%);
            color: #1a1a1a;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(226, 189, 97, 0.35);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-portal-submit:hover {
            background: linear-gradient(135deg, #e8c772 0%, #dbb24c 100%);
            transform: translateY(-1.5px);
            box-shadow: 0 6px 20px rgba(226, 189, 97, 0.45);
            color: #000000;
        }

        .btn-portal-submit:active {
            transform: translateY(0.5px);
            box-shadow: 0 2px 8px rgba(226, 189, 97, 0.3);
        }

        .btn-portal-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer Info & Security */
        .auth-footer-info {
            font-size: 0.775rem;
            color: #94a3b8;
            text-align: center;
            margin-top: 1.25rem;
            line-height: 1.5;
        }

        .auth-footer-info a {
            color: #475569;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
            transition: color 0.2s ease;
        }

        .auth-footer-info a:hover {
            color: #b45309;
        }

        .security-badges-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f1f5f9;
        }

        .sec-badge-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.725rem;
            font-weight: 600;
            color: #64748b;
        }

        .sec-badge-item i, .sec-badge-item svg {
            color: #10b981;
            font-size: 0.9rem;
        }

        /* Right / Branding Showcase Column */
        .auth-showcase-column {
            flex: 1;
            background-color: var(--portal-dark);
            position: relative;
            overflow: hidden;
            display: none;
            flex-direction: column;
            justify-content: space-between;
            padding: 3.5rem 4rem;
            color: #ffffff;
        }

        @media (min-width: 992px) {
            .auth-showcase-column {
                display: flex;
            }
        }

        /* Dynamic Mesh Glow & Geometric Layer */
        .showcase-bg-layer {
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(at 15% 15%, rgba(226, 189, 97, 0.18) 0px, transparent 55%),
                radial-gradient(at 85% 80%, rgba(59, 130, 246, 0.16) 0px, transparent 60%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.85) 0px, #0b0f19 100%);
            z-index: 1;
        }

        .showcase-grid-overlay {
            position: absolute;
            inset: 0;
            background-size: 36px 36px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            z-index: 1;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }

        .showcase-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Top Bar on Showcase */
        .showcase-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            font-size: 0.775rem;
            font-weight: 500;
            color: #cbd5e1;
        }

        .status-dot-pulse {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Middle Showcase Feature Cards */
        .showcase-hero-body {
            margin: auto 0;
            max-width: 680px;
        }

        .hero-tag {
            display: inline-block;
            color: var(--portal-primary);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.75rem;
        }

        .hero-heading {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.22;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 1.25rem;
        }

        .hero-heading span.gradient-text {
            background: linear-gradient(135deg, #ffffff 30%, #e2bd61 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            font-size: 1rem;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* Glassmorphism Feature Grid */
        .feature-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 2.5rem;
        }

        .glass-feature-card {
            background: rgba(255, 255, 255, 0.035);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.25rem;
            transition: all 0.3s ease;
        }

        .glass-feature-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(226, 189, 97, 0.35);
            transform: translateY(-3px);
        }

        .feature-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(226, 189, 97, 0.12);
            color: var(--portal-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.85rem;
            border: 1px solid rgba(226, 189, 97, 0.2);
        }

        .feature-card-title {
            font-size: 0.925rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 0.3rem;
        }

        .feature-card-desc {
            font-size: 0.775rem;
            color: #94a3b8;
            line-height: 1.45;
            margin-bottom: 0;
        }

        /* Testimonial / Quote Carousel in Bottom */
        .showcase-quote-slider {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .quote-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid var(--portal-primary);
            object-fit: cover;
            flex-shrink: 0;
        }

        .quote-text {
            font-size: 0.825rem;
            color: #e2e8f0;
            font-style: italic;
            margin-bottom: 0.15rem;
        }

        .quote-author {
            font-size: 0.725rem;
            color: var(--portal-primary);
            font-weight: 600;
        }

        /* Modern Modal Polish */
        .modal-content.premium-modal {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .modal-content.premium-modal .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.75rem;
        }

        .modal-content.premium-modal .modal-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #0f172a;
        }

        .modal-content.premium-modal .modal-body {
            padding: 1.75rem;
        }

        .modal-content.premium-modal .modal-footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1rem 1.75rem;
        }
    </style>
</head>

<?php include 'layouts/body.php'; ?>

<div class="premium-login-wrapper">
    <!-- LEFT: Form Column -->
    <div class="auth-form-column">
        <!-- Top branding -->
        <div class="d-flex align-items-center justify-content-between brand-header">
            <div>
                <a href="/" class="d-inline-block">
                    <img src="assets/images/logo.png" alt="Ersan Elektrik" class="brand-logo-img">
                </a>
            </div>
            <div>
                <span class="portal-badge">
                    <span class="badge-dot"></span>
                    Personel Portalı
                </span>
            </div>
        </div>

        <!-- Form Card Center -->
        <div class="auth-card-inner">
            <div class="mb-4">
                <h1 class="auth-title">Hoş Geldiniz</h1>
                <p class="auth-subtitle">Devam etmek için kurumsal hesabınızla oturum açın.</p>
            </div>

            <?php if (!empty($username_err) && empty($password_err) && !empty($_POST)): ?>
                <div class="alert-premium-error" role="alert">
                    <i class="mdi mdi-alert-circle-outline font-size-18"></i>
                    <span><?php echo htmlspecialchars($username_err, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php elseif (isset($_GET["status"]) && $_GET["status"] === "inactive"): ?>
                <div class="alert-premium-error" role="alert">
                    <i class="mdi mdi-account-lock-outline font-size-18"></i>
                    <span><?php echo htmlspecialchars($username_err, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm" novalidate>
                <!-- Username Input -->
                <div class="premium-input-group">
                    <label class="premium-input-label" for="username">Kullanıcı Adı / T.C. / E-Posta</label>
                    <div class="input-box-wrapper">
                        <input type="text"
                               class="premium-input <?php echo (!empty($username_err)) ? 'is-invalid-field' : ''; ?>"
                               id="username"
                               name="username"
                               value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="kullanici_adi veya ornek@ersanelektrik.com"
                               autocomplete="username"
                               required>
                        <i class="mdi mdi-account-outline input-icon"></i>
                    </div>
                    <?php if (!empty($username_err) && empty($_POST)): ?>
                        <span class="field-error-msg"><?php echo htmlspecialchars($username_err, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Password Input -->
                <div class="premium-input-group">
                    <label class="premium-input-label" for="password">Şifre</label>
                    <div class="input-box-wrapper">
                        <input type="password"
                               class="premium-input has-toggle <?php echo (!empty($password_err)) ? 'is-invalid-field' : ''; ?>"
                               id="password"
                               name="password"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required>
                        <i class="mdi mdi-lock-outline input-icon"></i>
                        <button type="button" class="password-toggle-btn" id="passwordToggle" title="Şifreyi Göster/Gizle" aria-label="Şifreyi Göster">
                            <i class="mdi mdi-eye-outline font-size-18" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    <?php if (!empty($password_err)): ?>
                        <span class="field-error-msg"><?php echo htmlspecialchars($password_err, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <label class="custom-remember-label">
                        <input type="checkbox" name="remember" id="remember-check" value="1">
                        <span>Beni Hatırla</span>
                    </label>
                    <a href="javascript:void(0)" class="forgot-link" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                        Şifremi Unuttum
                    </a>
                </div>

                <!-- Submit Button -->
                <button class="btn-portal-submit" type="submit" id="btnLogin">
                    <span class="spinner-border spinner-border-sm d-none" id="loginSpinner" role="status" aria-hidden="true"></span>
                    <span id="loginBtnText">Oturum Aç</span>
                    <i class="mdi mdi-arrow-right font-size-16" id="loginBtnIcon"></i>
                </button>

                <!-- KVKK info -->
                <div class="auth-footer-info">
                    Giriş yaparak <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#kvkkModal">KVKK Aydınlatma Metni</a>'ni ve kurumsal güvenlik politikasını okuduğunuzu kabul etmiş olursunuz.
                </div>
            </form>

            <!-- Trust / Security badges -->
            <div class="security-badges-bar">
                <div class="sec-badge-item">
                    <i class="mdi mdi-shield-check-outline"></i>
                    <span>256-Bit SSL Şifreleme</span>
                </div>
                <div class="sec-badge-item">
                    <i class="mdi mdi-lock-check-outline"></i>
                    <span>ISO 27001 Güvenlik</span>
                </div>
            </div>
        </div>

        <!-- Footer Copyright -->
        <div class="text-center mt-3 pt-2">
            <p class="mb-0 text-muted font-size-12">
                © <?php echo date('Y'); ?> Ersan Elektrik A.Ş. Tüm hakları saklıdır.
            </p>
        </div>
    </div>

    <!-- RIGHT: Showcase / Prestige Column -->
    <div class="auth-showcase-column">
        <div class="showcase-bg-layer"></div>
        <div class="showcase-grid-overlay"></div>

        <div class="showcase-content">
            <!-- Showcase Topbar -->
            <div class="showcase-topbar">
                <div class="d-flex align-items-center gap-2">
                    <img src="assets/images/logo-sm.svg" alt="Ersan Logo" height="24" style="opacity: 0.9;">
                    <span style="font-weight: 700; font-size: 0.95rem; letter-spacing: 0.05em; color: #f8fafc;">ERSAN ELEKTRİK</span>
                </div>
                <div class="live-status-pill">
                    <span class="status-dot-pulse"></span>
                    <span>Kurumsal Sunucu Aktif</span>
                </div>
            </div>

            <!-- Hero Body & Key Highlights -->
            <div class="showcase-hero-body">
                <span class="hero-tag">Yönetim & Operasyon Portalı</span>
                <h2 class="hero-heading">
                    Güçlü Altyapı, <br>
                    <span class="gradient-text">Eksiksiz Personel Yönetimi.</span>
                </h2>
                <p class="hero-description">
                    Ersan Elektrik bünyesindeki tüm personel özlük, puantaj, bordro, SGK ve idari süreçleri tek çatı altında güvenle yönetin.
                </p>

                <!-- Feature Grid -->
                <div class="feature-cards-grid">
                    <div class="glass-feature-card">
                        <div class="feature-card-icon">
                            <i class="mdi mdi-account-group-outline"></i>
                        </div>
                        <h4 class="feature-card-title">Personel & Özlük</h4>
                        <p class="feature-card-desc">Bordro, izin, avans ve zimmet süreçlerinin anlık dijital takibi.</p>
                    </div>

                    <div class="glass-feature-card">
                        <div class="feature-card-icon">
                            <i class="mdi mdi-shield-lock-outline"></i>
                        </div>
                        <h4 class="feature-card-title">AES-256 Koruması</h4>
                        <p class="feature-card-desc">ISO 27001 standartlarında şifrelenmiş veri ve evrak güvenliği.</p>
                    </div>

                    <div class="glass-feature-card">
                        <div class="feature-card-icon">
                            <i class="mdi mdi-calculator-variant-outline"></i>
                        </div>
                        <h4 class="feature-card-title">Akıllı Bordro</h4>
                        <p class="feature-card-desc">Yasal mevzuat ve firma kurallarına tam uyumlu otomatik hesaplama.</p>
                    </div>

                    <div class="glass-feature-card">
                        <div class="feature-card-icon">
                            <i class="mdi mdi-chart-timeline-variant"></i>
                        </div>
                        <h4 class="feature-card-title">Merkezi Denetim</h4>
                        <p class="feature-card-desc">Detaylı aktivite logları ve yetki kademeleriyle tam kontrol.</p>
                    </div>
                </div>
            </div>

            <!-- Bottom Quote Widget -->
            <div class="showcase-quote-slider">
                <img src="assets/images/img-2.jpg" class="quote-avatar" alt="Ersan Elektrik">
                <div>
                    <div class="quote-text">"Güvenilir enerji, disiplinli yönetim ve sürdürülebilir başarı."</div>
                    <div class="quote-author">Ersan Elektrik Yönetim Kurulu</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KVKK Aydınlatma Metni Modal -->
<div class="modal fade" id="kvkkModal" tabindex="-1" aria-labelledby="kvkkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content premium-modal">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-shield-account text-warning font-size-22"></i>
                    <h5 class="modal-title mb-0" id="kvkkModalLabel">KVKK Aydınlatma Metni</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body font-size-14 text-muted" style="line-height: 1.65;">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <strong class="text-dark">Veri Sorumlusu:</strong> Ersan Elektrik A.Ş.
                </div>

                <h6 class="fw-bold text-dark mt-3">1. Kişisel Verilerinizin İşlenme Amacı</h6>
                <p>Bu sistem yalnızca yetkili şirket çalışanlarının kullanımına açık kurumsal bir yönetim uygulamasıdır. Sisteme giriş yaparak aşağıdaki amaçlarla kişisel verileriniz işlenecektir:</p>
                <ul class="mb-3">
                    <li>Personel özlük, puantaj ve bordro işlemlerinin yürütülmesi</li>
                    <li>İzin, avans, zimmet ve talep yönetiminin sağlanması</li>
                    <li>Güvenlik, oturum ve erişim kontrollerinin gerçekleştirilmesi</li>
                    <li>Yasal yükümlülüklerin (SGK, Gelir İdaresi, İş Kanunu) eksiksiz yerine getirilmesi</li>
                </ul>

                <h6 class="fw-bold text-dark mt-3">2. İşlenen Kişisel Veriler</h6>
                <p>Kimlik (ad-soyad, TC kimlik no), iletişim (telefon, e-posta, adres), finansal (IBAN, maaş bilgileri), SGK ve özlük bilgileri işlenmektedir.</p>

                <h6 class="fw-bold text-dark mt-3">3. Hukuki Dayanak</h6>
                <p>Verileriniz; iş sözleşmesinin ifası (KVKK Md.5/2-c), kanuni yükümlülüklerin yerine getirilmesi (Md.5/2-ç) ve meşru menfaat (Md.5/2-f) kapsamında işlenmektedir.</p>

                <h6 class="fw-bold text-dark mt-3">4. Veri Güvenliği Tedbirleri</h6>
                <p>Verileriniz 256-bit şifreli bağlantı (HTTPS), rol tabanlı yetkilendirme, güvenli oturum yönetimi ve AES-256 disk şifreleme yöntemleriyle en üst düzeyde korunmaktadır. Tüm giriş denemeleri ve kritik işlemler kayıt altına alınmaktadır.</p>

                <h6 class="fw-bold text-dark mt-3">5. Haklarınız (KVKK Madde 11)</h6>
                <p>Veri sorumlusuna başvurarak; verilerinize erişim, düzeltme veya silinmesini talep edebilirsiniz. Başvurularınız için sistem yöneticiniz ile iletişime geçebilirsiniz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Anladım, Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content premium-modal">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-lock-reset text-warning font-size-22"></i>
                    <h5 class="modal-title mb-0" id="forgotPasswordModalLabel">Şifremi Unuttum</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted font-size-13 mb-3">
                    Sistemde kayıtlı kullanıcı adınızı, telefon numaranızı veya e-posta adresinizi giriniz. Şifre sıfırlama bağlantınız e-posta adresinize iletilecektir.
                </p>
                <form id="forgotPasswordForm">
                    <div class="premium-input-group mb-3">
                        <label class="premium-input-label" for="forgot-input">Kullanıcı Adı, Telefon veya E-posta</label>
                        <div class="input-box-wrapper">
                            <input type="text" class="premium-input" id="forgot-input" name="identifier" placeholder="ornek@ersanelektrik.com" required>
                            <i class="mdi mdi-email-outline input-icon"></i>
                        </div>
                    </div>
                    <button type="button" class="btn-portal-submit mt-3" id="btnForgotPassword">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Sıfırlama Bağlantısı Gönder</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>

<script>
    $(document).ready(function() {
        // Password Visibility Toggle
        $('#passwordToggle').on('click', function() {
            const passwordInput = $('#password');
            const icon = $('#passwordToggleIcon');
            
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
                $(this).attr('aria-label', 'Şifreyi Gizle');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
                $(this).attr('aria-label', 'Şifreyi Göster');
            }
        });

        // Form Submit Loading Feedback
        $('#loginForm').on('submit', function() {
            const btn = $('#btnLogin');
            const username = $('#username').val().trim();
            const password = $('#password').val().trim();

            if (username && password) {
                $('#loginSpinner').removeClass('d-none');
                $('#loginBtnIcon').addClass('d-none');
                $('#loginBtnText').text('Doğrulanıyor...');
                btn.prop('disabled', true);
            }
        });

        // Forgot Password AJAX Handler
        const handleForgotPassword = function(e) {
            if (e) e.preventDefault();
            const btn = $('#btnForgotPassword');
            const spinner = btn.find('.spinner-border');
            const btnText = btn.find('.btn-text');
            const identifier = $('#forgot-input').val().trim();

            if (!identifier) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uyarı',
                    text: 'Lütfen kullanıcı adı, telefon veya e-posta giriniz.',
                    confirmButtonColor: '#e2bd61'
                });
                return;
            }

            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Gönderiliyor...');

            $.ajax({
                url: 'auth-api.php',
                type: 'POST',
                data: {
                    action: 'forgot-password',
                    identifier: identifier
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı!',
                            text: response.message || 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.',
                            confirmButtonColor: '#10b981'
                        }).then(() => {
                            $('#forgotPasswordModal').modal('hide');
                            $('#forgot-input').val('');
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata!',
                            text: response.message || 'Bir hata oluştu.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr) {
                    let msg = 'Sistem hatası oluştu. Lütfen tekrar deneyiniz.';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res && res.message) msg = res.message;
                    } catch(e) {}
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata!',
                        text: msg,
                        confirmButtonColor: '#ef4444'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Sıfırlama Bağlantısı Gönder');
                }
            });
        };

        $('#btnForgotPassword').on('click', handleForgotPassword);
        $('#forgotPasswordForm').on('submit', handleForgotPassword);
    });
</script>

</body>
</html>
