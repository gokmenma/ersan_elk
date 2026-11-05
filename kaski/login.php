<?php

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\FirmaModel;
use App\Model\SystemLogModel;
use App\Model\UserModel;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$User = new UserModel();
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if (!empty($_SESSION['loggedin'])
    && $currentUserId > 0
    && $User->hasRoleName($currentUserId, 'KASKİ Görüntüleme')) {
    $_SESSION['portal_scope'] = 'kaski';
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
$csrfToken = $_SESSION['kaski_login_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['kaski_login_csrf'] = $csrfToken;

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$attemptKey = 'kaski_login_attempts_' . hash('sha256', $ip);
$attempts = $_SESSION[$attemptKey] ?? ['count' => 0, 'first_attempt' => time()];

if ($attempts['count'] >= 5 && (time() - (int) $attempts['first_attempt']) < 900) {
    $remaining = (int) ceil((900 - (time() - (int) $attempts['first_attempt'])) / 60);
    $error = "Çok fazla başarısız giriş denemesi. {$remaining} dakika sonra tekrar deneyin.";
} elseif ($attempts['count'] >= 5) {
    $attempts = ['count' => 0, 'first_attempt' => time()];
    $_SESSION[$attemptKey] = $attempts;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $error === '') {
    $postedCsrf = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $error = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Kullanıcı adı ve şifre zorunludur.';
        } else {
            $user = $User->checkUser($username);
            $validUser = $user
                && ($user->durum ?? 'Aktif') !== 'Pasif'
                && $User->hasRoleName((int) $user->id, 'KASKİ Görüntüleme')
                && password_verify($password, (string) $user->password);

            if (!$validUser) {
                $error = 'Kullanıcı bilgileri hatalı veya KASKİ portalı erişiminiz bulunmuyor.';
                $attempts['count']++;
                if ($attempts['count'] === 1) {
                    $attempts['first_attempt'] = time();
                }
                $_SESSION[$attemptKey] = $attempts;
            } else {
                $firmaIds = array_values(array_filter(
                    array_map('intval', explode(',', (string) ($user->firma_ids ?? ''))),
                    static fn(int $id): bool => $id > 0
                ));
                $firmaId = $firmaIds[0] ?? 0;
                $Firma = new FirmaModel();
                $firma = $firmaId > 0 ? $Firma->find($firmaId) : null;

                if (!$firma || !empty($firma->silinme_tarihi)) {
                    $error = 'Hesabınıza erişilebilir bir firma tanımlanmamış.';
                } else {
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['loggedin'] = true;
                    $_SESSION['portal_scope'] = 'kaski';
                    $_SESSION['user'] = $user;
                    $_SESSION['user_id'] = (int) $user->id;
                    $_SESSION['id'] = (int) $user->id;
                    $_SESSION['owner_id'] = (int) $user->owner_id;
                    $_SESSION['username'] = (string) $user->user_name;
                    $_SESSION['user_full_name'] = (string) $user->adi_soyadi;
                    $_SESSION['sube_id'] = (int) ($user->sube_id ?? $firmaId);
                    $_SESSION['personel_id'] = 0;
                    $_SESSION['firma_id'] = $firmaId;
                    $_SESSION['firma_kodu'] = (string) ($firma->firma_kodu ?? '');
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    try {
                        (new SystemLogModel())->logAction(
                            (int) $user->id,
                            'KASKİ Portal Girişi',
                            $user->adi_soyadi . ' salt okunur KASKİ portalına giriş yaptı. IP: ' . $ip,
                            SystemLogModel::LEVEL_IMPORTANT
                        );
                    } catch (Throwable $e) {
                        error_log("KASKİ giriş log hatası: " . $e->getMessage());
                    }

                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
}

?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KASKİ Kaçak Kontrol Portalı | Ersan Elektrik</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/icons.min.css" rel="stylesheet">
    <link href="../assets/css/app.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --portal-primary: #e2bd61;
            --portal-primary-light: rgba(226, 189, 97, 0.12);
            --portal-dark: #0b0f19;
        }
        body { font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important; background-color: #f8fafc; color: #0f172a; overflow-x: hidden; min-height: 100vh; }
        .premium-login-wrapper { min-height: 100vh; display: flex; width: 100%; }
        .auth-form-column { flex: 0 0 100%; max-width: 100%; background: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 2.5rem 2rem; position: relative; z-index: 2; box-shadow: 10px 0 30px rgba(0, 0, 0, 0.03); }
        @media (min-width: 992px) { .auth-form-column { flex: 0 0 42%; max-width: 42%; padding: 3.5rem; } }
        @media (min-width: 1400px) { .auth-form-column { flex: 0 0 36%; max-width: 36%; padding: 4rem 4.5rem; } }
        .auth-card-inner { width: 100%; max-width: 420px; margin: auto; }
        .brand-header { margin-bottom: 2rem; }
        .brand-logo-img { max-height: 48px; width: auto; object-fit: contain; transition: transform .3s ease; }
        .brand-logo-img:hover { transform: scale(1.02); }
        .portal-badge { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 9999px; font-size: .75rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .portal-badge .badge-dot { width: 6px; height: 6px; border-radius: 50%; background-color: var(--portal-primary); box-shadow: 0 0 6px var(--portal-primary); }
        .auth-title { font-size: 1.65rem; font-weight: 700; color: #0f172a; letter-spacing: -.02em; margin-bottom: .35rem; }
        .auth-subtitle { font-size: .925rem; color: #64748b; margin-bottom: 1.75rem; }
        .readonly-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 9999px; background: var(--portal-primary-light); color: #92660a; font-size: .775rem; font-weight: 600; margin-bottom: 1.5rem; }
        .premium-input-group { margin-bottom: 1.25rem; position: relative; }
        .premium-input-label { display: block; font-size: .825rem; font-weight: 600; color: #334155; margin-bottom: .4rem; }
        .input-box-wrapper { position: relative; display: flex; align-items: center; }
        .input-box-wrapper .input-icon { position: absolute; left: 14px; color: #94a3b8; font-size: 1.15rem; pointer-events: none; transition: color .2s ease, transform .2s ease; z-index: 3; }
        .premium-input { width: 100%; height: 48px; padding: .65rem 1rem .65rem 42px; font-size: .925rem; font-weight: 500; color: #0f172a; background-color: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; transition: all .25s cubic-bezier(.4, 0, .2, 1); }
        .premium-input.has-toggle { padding-right: 44px; }
        .premium-input:hover { background-color: #fff; border-color: #cbd5e1; }
        .premium-input:focus { background-color: #fff; border-color: var(--portal-primary); box-shadow: 0 0 0 4px var(--portal-primary-light); outline: none; }
        .input-box-wrapper:focus-within .input-icon { color: #d97706; transform: scale(1.05); }
        .password-toggle-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; padding: 8px; cursor: pointer; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all .2s ease; z-index: 4; }
        .password-toggle-btn:hover { color: #334155; background-color: #f1f5f9; }
        .alert-premium-error { background-color: #fef2f2; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: .85rem 1rem; border-radius: 10px; font-size: .85rem; font-weight: 500; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; }
        .btn-portal-submit { width: 100%; height: 48px; background: linear-gradient(135deg, #e2bd61 0%, #d4a942 100%); color: #1a1a1a; border: none; border-radius: 12px; font-size: .95rem; font-weight: 700; letter-spacing: .02em; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; box-shadow: 0 4px 14px rgba(226, 189, 97, .35); transition: all .25s cubic-bezier(.4, 0, .2, 1); }
        .btn-portal-submit:hover { background: linear-gradient(135deg, #e8c772 0%, #dbb24c 100%); transform: translateY(-1.5px); box-shadow: 0 6px 20px rgba(226, 189, 97, .45); color: #000; }
        .btn-portal-submit:active { transform: translateY(.5px); box-shadow: 0 2px 8px rgba(226, 189, 97, .3); }
        .btn-portal-submit:disabled { opacity: .7; cursor: not-allowed; transform: none; }
        .auth-footer-info { font-size: .775rem; color: #94a3b8; text-align: center; margin-top: 1.25rem; line-height: 1.5; }
        .security-badges-bar { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; }
        .sec-badge-item { display: inline-flex; align-items: center; gap: 5px; font-size: .725rem; font-weight: 600; color: #64748b; }
        .sec-badge-item i { color: #10b981; font-size: .9rem; }
        .auth-showcase-column { flex: 1; background-color: var(--portal-dark); position: relative; overflow: hidden; display: none; flex-direction: column; justify-content: space-between; padding: 3.5rem 4rem; color: #fff; }
        @media (min-width: 992px) { .auth-showcase-column { display: flex; } }
        .showcase-bg-layer { position: absolute; inset: 0; background-image: radial-gradient(at 15% 15%, rgba(226, 189, 97, .18) 0, transparent 55%), radial-gradient(at 85% 80%, rgba(59, 130, 246, .16) 0, transparent 60%), radial-gradient(at 50% 50%, rgba(15, 23, 42, .85) 0, #0b0f19 100%); z-index: 1; }
        .showcase-grid-overlay { position: absolute; inset: 0; background-size: 36px 36px; background-image: linear-gradient(to right, rgba(255, 255, 255, .03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, .03) 1px, transparent 1px); z-index: 1; mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%); -webkit-mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%); }
        .showcase-content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .showcase-topbar { display: flex; align-items: center; justify-content: space-between; }
        .live-status-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: rgba(255, 255, 255, .06); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, .1); border-radius: 9999px; font-size: .775rem; font-weight: 500; color: #cbd5e1; }
        .status-dot-pulse { width: 8px; height: 8px; background-color: #10b981; border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, .7); animation: pulse-green 2s infinite; }
        @keyframes pulse-green { 0% { transform: scale(.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, .7); } 70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } 100% { transform: scale(.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        .showcase-hero-body { margin: auto 0; max-width: 680px; }
        .hero-tag { display: inline-block; color: var(--portal-primary); font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; margin-bottom: .75rem; }
        .hero-heading { font-size: 2.25rem; font-weight: 800; line-height: 1.22; letter-spacing: -.03em; color: #fff; margin-bottom: 1.25rem; }
        .hero-heading span.gradient-text { background: linear-gradient(135deg, #fff 30%, #e2bd61 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-description { font-size: 1rem; color: #94a3b8; line-height: 1.6; margin-bottom: 2.5rem; }
        .feature-cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 2.5rem; }
        .glass-feature-card { background: rgba(255, 255, 255, .035); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, .08); border-radius: 16px; padding: 1.25rem; transition: all .3s ease; }
        .glass-feature-card:hover { background: rgba(255, 255, 255, .06); border-color: rgba(226, 189, 97, .35); transform: translateY(-3px); }
        .feature-card-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(226, 189, 97, .12); color: var(--portal-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: .85rem; border: 1px solid rgba(226, 189, 97, .2); }
        .feature-card-title { font-size: .925rem; font-weight: 700; color: #f8fafc; margin-bottom: .3rem; }
        .feature-card-desc { font-size: .775rem; color: #94a3b8; line-height: 1.45; margin-bottom: 0; }
        .showcase-quote-slider { background: rgba(15, 23, 42, .6); border: 1px solid rgba(255, 255, 255, .07); border-radius: 14px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 14px; }
        .quote-avatar { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--portal-primary); object-fit: cover; flex-shrink: 0; }
        .quote-text { font-size: .825rem; color: #e2e8f0; font-style: italic; margin-bottom: .15rem; }
        .quote-author { font-size: .725rem; color: var(--portal-primary); font-weight: 600; }
    </style>
</head>
<body>
<div class="premium-login-wrapper">
    <div class="auth-form-column">
        <div class="d-flex align-items-center justify-content-between brand-header">
            <div>
                <a href="login.php" class="d-inline-block">
                    <img src="../assets/images/logo.png" alt="Ersan Elektrik" class="brand-logo-img">
                </a>
            </div>
            <div>
                <span class="portal-badge"><span class="badge-dot"></span>KASKİ Portalı</span>
            </div>
        </div>

        <div class="auth-card-inner">
            <div class="mb-4">
                <h1 class="auth-title">KASKİ Kaçak Kontrol</h1>
                <p class="auth-subtitle">Devam etmek için kurumsal hesabınızla oturum açın.</p>
            </div>

            <div class="readonly-chip"><i class="mdi mdi-eye-outline"></i> Salt okunur kayıt portalı</div>

            <?php if ($error !== ''): ?>
                <div class="alert-premium-error" role="alert">
                    <i class="mdi mdi-alert-circle-outline font-size-18"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" id="loginForm" autocomplete="on" novalidate>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="premium-input-group">
                    <label class="premium-input-label" for="username">Kullanıcı Adı / T.C. / E-Posta</label>
                    <div class="input-box-wrapper">
                        <input type="text" class="premium-input" id="username" name="username"
                               value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="kullanici_adi veya ornek@ersanelektrik.com"
                               autocomplete="username" required autofocus>
                        <i class="mdi mdi-account-outline input-icon"></i>
                    </div>
                </div>

                <div class="premium-input-group">
                    <label class="premium-input-label" for="password">Şifre</label>
                    <div class="input-box-wrapper">
                        <input type="password" class="premium-input has-toggle" id="password" name="password"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <i class="mdi mdi-lock-outline input-icon"></i>
                        <button type="button" class="password-toggle-btn" id="passwordToggle" title="Şifreyi Göster/Gizle" aria-label="Şifreyi Göster">
                            <i class="mdi mdi-eye-outline font-size-18" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button class="btn-portal-submit mt-4" type="submit" id="btnLogin">
                    <span class="spinner-border spinner-border-sm d-none" id="loginSpinner" role="status" aria-hidden="true"></span>
                    <span id="loginBtnText">Portala Giriş Yap</span>
                    <i class="mdi mdi-arrow-right font-size-16" id="loginBtnIcon"></i>
                </button>

                <div class="auth-footer-info">Bu portal yalnızca yetkili kurum personelinin kullanımına açıktır.</div>
            </form>

            <div class="security-badges-bar">
                <div class="sec-badge-item"><i class="mdi mdi-shield-check-outline"></i><span>256-Bit SSL Şifreleme</span></div>
                <div class="sec-badge-item"><i class="mdi mdi-lock-check-outline"></i><span>Salt Okunur Erişim</span></div>
            </div>
        </div>

        <div class="text-center mt-3 pt-2">
            <p class="mb-0 text-muted font-size-12">© <?= date('Y') ?> Ersan Elektrik A.Ş. Tüm hakları saklıdır.</p>
        </div>
    </div>

    <div class="auth-showcase-column">
        <div class="showcase-bg-layer"></div>
        <div class="showcase-grid-overlay"></div>

        <div class="showcase-content">
            <div class="showcase-topbar">
                <div class="d-flex align-items-center gap-2">
                    <img src="../assets/images/logo-sm.svg" alt="Ersan Logo" height="24" style="opacity: .9;">
                    <span style="font-weight: 700; font-size: .95rem; letter-spacing: .05em; color: #f8fafc;">ERSAN ELEKTRİK</span>
                </div>
                <div class="live-status-pill">
                    <span class="status-dot-pulse"></span>
                    <span>Kurumsal Sunucu Aktif</span>
                </div>
            </div>

            <div class="showcase-hero-body">
                <span class="hero-tag">Kaçak Kontrol Bilgi Sistemi</span>
                <h2 class="hero-heading">
                    Kaçak kayıtlarına <br>
                    <span class="gradient-text">güvenli ve hızlı erişim.</span>
                </h2>
                <p class="hero-description">
                    Kayıtları, tutanakları, saha görsellerini ve raporları tek ekrandan inceleyin.
                </p>

                <div class="feature-cards-grid">
                    <div class="glass-feature-card">
                        <div class="feature-card-icon"><i class="mdi mdi-shield-lock-outline"></i></div>
                        <h4 class="feature-card-title">Yetkili Erişim</h4>
                        <p class="feature-card-desc">Yetki grubu ile sınırlandırılmış güvenli erişim.</p>
                    </div>
                    <div class="glass-feature-card">
                        <div class="feature-card-icon"><i class="mdi mdi-format-list-checks"></i></div>
                        <h4 class="feature-card-title">Güncel Kayıtlar</h4>
                        <p class="feature-card-desc">Güncel kaçak kayıtları ve detayları tek yerde.</p>
                    </div>
                    <div class="glass-feature-card">
                        <div class="feature-card-icon"><i class="mdi mdi-image-multiple-outline"></i></div>
                        <h4 class="feature-card-title">Saha Görselleri</h4>
                        <p class="feature-card-desc">Tutanaklar ve saha görselleri kayıtla birlikte incelenir.</p>
                    </div>
                    <div class="glass-feature-card">
                        <div class="feature-card-icon"><i class="mdi mdi-eye-lock-outline"></i></div>
                        <h4 class="feature-card-title">Salt Okunur</h4>
                        <p class="feature-card-desc">Değişiklik yapılamayan, denetime uygun kullanım.</p>
                    </div>
                </div>
            </div>

            <div class="showcase-quote-slider">
                <img src="../assets/images/img-2.jpg" class="quote-avatar" alt="Ersan Elektrik">
                <div>
                    <div class="quote-text">"Güvenilir enerji, disiplinli yönetim ve sürdürülebilir başarı."</div>
                    <div class="quote-author">Ersan Elektrik Yönetim Kurulu</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/libs/jquery/jquery.3.7.1.min.js"></script>
<script src="../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(function () {
        $('#passwordToggle').on('click', function () {
            var input = $('#password');
            var icon = $('#passwordToggleIcon');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
                $(this).attr('aria-label', 'Şifreyi Gizle');
            } else {
                input.attr('type', 'password');
                icon.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
                $(this).attr('aria-label', 'Şifreyi Göster');
            }
        });

        $('#loginForm').on('submit', function () {
            if ($('#username').val().trim() && $('#password').val().trim()) {
                $('#loginSpinner').removeClass('d-none');
                $('#loginBtnIcon').addClass('d-none');
                $('#loginBtnText').text('Doğrulanıyor...');
                $('#btnLogin').prop('disabled', true);
            }
        });
    });
</script>
</body>
</html>
