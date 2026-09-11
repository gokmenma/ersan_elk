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
                        // Loglama sorunu giriş akışını engellemez.
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
    <title>KASKİ Kaçak Kontrol Portalı</title>
    <link rel="shortcut icon" href="../assets/images/fav.png">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/icons.min.css" rel="stylesheet">
    <link href="../assets/css/app.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --kaski-primary: #2563eb; --kaski-dark: #102a56; }
        body { font-family: 'Geist', sans-serif; background: #fff; }
        .kaski-auth-page, .kaski-auth-page > .container-fluid, .kaski-auth-page .row { min-height: 100vh; }
        .kaski-form-panel { background: var(--bs-body-bg, #fff); position: relative; z-index: 2; }
        .kaski-form-wrap { width: 100%; max-width: 430px; }
        .kaski-logo { height: 34px; width: auto; }
        .kaski-label { font-size: .78rem; font-weight: 700; letter-spacing: .08em; color: var(--kaski-primary); }
        .portal-mark { width: 54px; height: 54px; border-radius: 15px; display: grid; place-items: center; background: rgba(37, 99, 235, .1); color: var(--kaski-primary); font-size: 27px; }
        .kaski-form-panel .form-floating-custom .form-control { min-height: 58px; border-radius: 10px; }
        .kaski-form-panel .form-floating-custom .form-floating-icon { color: #64748b; }
        .kaski-login-btn { min-height: 48px; border-radius: 9px; background: var(--kaski-primary); border-color: var(--kaski-primary); font-weight: 600; }
        .kaski-login-btn:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .kaski-side {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(145deg, rgba(10, 36, 80, .94), rgba(13, 94, 139, .86)),
                url('../assets/images/auth-bg.jpg') center/cover no-repeat;
        }
        .kaski-side::before { content: ''; position: absolute; width: 460px; height: 460px; right: -120px; top: -130px; border: 1px solid rgba(255,255,255,.14); border-radius: 50%; }
        .kaski-side::after { content: ''; position: absolute; width: 300px; height: 300px; left: -100px; bottom: -100px; background: rgba(45, 212, 191, .1); border-radius: 50%; }
        .kaski-side-content { position: relative; z-index: 2; max-width: 660px; }
        .kaski-side-icon { width: 78px; height: 78px; border-radius: 22px; display: grid; place-items: center; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); font-size: 38px; backdrop-filter: blur(10px); }
        .kaski-feature { display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,.86); }
        .kaski-feature i { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: rgba(255,255,255,.1); color: #7dd3fc; font-size: 18px; }
        .readonly-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 999px; background: rgba(37, 99, 235, .08); color: #1d4ed8; font-size: .78rem; font-weight: 600; }
        .kaski-footer { color: #94a3b8; font-size: .78rem; }
        @media (max-width: 991.98px) {
            .kaski-side { min-height: 235px !important; order: -1; }
            .kaski-side-content { padding: 36px 28px !important; }
            .kaski-side-content h2 { font-size: 1.55rem; }
            .kaski-side-content .lead, .kaski-features { display: none; }
            .kaski-form-panel { min-height: calc(100vh - 235px) !important; }
        }
    </style>
</head>
<body>
<main class="kaski-auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <section class="col-lg-5 col-xl-4 kaski-form-panel d-flex p-4 p-sm-5" aria-label="KASKİ portal girişi">
                <div class="kaski-form-wrap d-flex flex-column mx-auto">
                    <div class="mb-5">
                        <a href="login.php" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                            <img src="../assets/images/logo.png" alt="Ersan Elektrik" class="kaski-logo">
                            <span class="fw-semibold text-dark">Personel Yönetimi</span>
                        </a>
                    </div>

                    <div class="my-auto">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="portal-mark"><i class="bx bx-shield-quarter"></i></div>
                            <div>
                                <div class="kaski-label mb-1">KURUMSAL ERİŞİM</div>
                                <h1 class="h3 fw-bold mb-1">KASKİ Kaçak Kontrol</h1>
                                <p class="text-muted mb-0">Devam etmek için oturum açın</p>
                            </div>
                        </div>

                        <div class="readonly-chip mb-4"><i class="bx bx-show"></i> Salt okunur kayıt portalı</div>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                                <i class="bx bx-error-circle fs-5 mt-1"></i>
                                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="login.php" autocomplete="on">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-floating form-floating-custom mb-4">
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Kullanıcı adı" required autofocus autocomplete="username">
                                <label for="username">Kullanıcı Adı, Telefon veya E-posta</label>
                                <div class="form-floating-icon"><i data-feather="user"></i></div>
                            </div>
                            <div class="form-floating form-floating-custom mb-4 auth-pass-inputgroup">
                                <input type="password" class="form-control pe-5" id="password" name="password"
                                       placeholder="Şifre" required autocomplete="current-password">
                                <label for="password">Şifre</label>
                                <div class="form-floating-icon"><i data-feather="lock"></i></div>
                                <button type="button" class="btn btn-link position-absolute h-100 end-0 top-0 text-muted" id="password-addon" aria-label="Şifreyi göster">
                                    <i class="mdi mdi-eye-outline font-size-18"></i>
                                </button>
                            </div>
                            <button type="submit" class="btn btn-primary kaski-login-btn w-100 waves-effect waves-light">
                                <i class="bx bx-log-in-circle me-1"></i> Portala Giriş Yap
                            </button>
                        </form>
                    </div>

                    <div class="kaski-footer mt-5">© <?= date('Y') ?> Ersan Elektrik · Yetkili kurum personeli kullanımı içindir.</div>
                </div>
            </section>

            <aside class="col-lg-7 col-xl-8 kaski-side d-flex align-items-center justify-content-center text-white">
                <div class="kaski-side-content p-5">
                    <div class="kaski-side-icon mb-4"><i class="bx bx-search-alt"></i></div>
                    <div class="text-uppercase fw-semibold mb-3" style="letter-spacing:.14em;color:#7dd3fc;font-size:.8rem;">Kaçak Kontrol Bilgi Sistemi</div>
                    <h2 class="display-5 fw-bold text-white mb-3">Kaçak kayıtlarına<br>güvenli ve hızlı erişim.</h2>
                    <p class="lead text-white-50 mb-5">Kayıtları, tutanakları, saha görsellerini ve raporları tek ekrandan inceleyin.</p>
                    <div class="kaski-features d-flex flex-column gap-3">
                        <div class="kaski-feature"><i class="bx bx-lock-alt"></i><span>Yetki grubu ile sınırlandırılmış güvenli erişim</span></div>
                        <div class="kaski-feature"><i class="bx bx-list-check"></i><span>Güncel kaçak kayıtları ve detayları</span></div>
                        <div class="kaski-feature"><i class="bx bx-shield-quarter"></i><span>Değişiklik yapılamayan salt okunur kullanım</span></div>
                    </div>
                </div>
                <ul class="bg-bubbles" aria-hidden="true">
                    <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
                </ul>
            </aside>
        </div>
    </div>
</main>

<script src="../assets/libs/jquery/jquery.3.7.1.min.js"></script>
<script src="../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/libs/feather-icons/feather.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.feather) feather.replace();
        var toggle = document.getElementById('password-addon');
        var password = document.getElementById('password');
        if (toggle && password) {
            toggle.addEventListener('click', function () {
                var visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                toggle.querySelector('i').className = visible
                    ? 'mdi mdi-eye-outline font-size-18'
                    : 'mdi mdi-eye-off-outline font-size-18';
            });
        }
    });
</script>
</body>
</html>
