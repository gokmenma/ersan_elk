<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\UserModel;
use App\Helper\Security;
use App\Model\SystemLogModel;

$User = new UserModel();

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) {
    header("location: login.php");
    exit;
}

$user = $User->getUserByResetToken($token);

if (!$user) {
    $error = "Geçersiz veya süresi dolmuş bir bağlantı kullandınız. Lütfen tekrar şifre sıfırlama talebinde bulununuz.";
}

// Form işleme
if ($_SERVER["REQUEST_METHOD"] == "POST" && $user) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 4) {
        $error = "Şifreniz en az 4 karakter olmalıdır.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Şifreler uyuşmuyor.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        if ($User->resetPassword($user->id, $hashed_password)) {
            $success = true;
            
            // Log the password reset
            try {
                $SystemLog = new SystemLogModel();
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $SystemLog->logAction(
                    $user->id,
                    'Şifre Sıfırlama',
                    "{$user->adi_soyadi} ({$user->user_name}) şifresini başarıyla sıfırladı. IP: {$ip}",
                    SystemLogModel::LEVEL_IMPORTANT
                );
            } catch (\Exception $e) {}

        } else {
            $error = "Şifre güncellenirken bir hata oluştu.";
        }
    }
}
?>

<?php include 'layouts/main.php'; ?>

<head>
    <title>Şifre Sıfırla | Ersan Elektrik</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>
</head>

<?php include 'layouts/body.php'; ?>

<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-xxl-3 col-lg-4 col-md-5">
                <div class="auth-full-page-content d-flex p-sm-5 p-4">
                    <div class="w-100">
                        <div class="d-flex flex-column h-100">
                            <div class="mb-4 mb-md-5 text-center">
                                <a href="login.php" class="d-block auth-logo">
                                    <img src="assets/images/logo.png" alt="" height="28">
                                    <p class="logo-txt">Personel Yönetimi</p>
                                </a>
                            </div>
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-0">Yeni Şifre Belirleyin</h5>
                                    <p class="text-muted mt-2">Hesabınız için güvenli bir şifre giriniz.</p>
                                </div>

                                <?php if ($success): ?>
                                    <div class="alert alert-success text-center mb-4 mt-4" role="alert">
                                        Şifreniz başarıyla güncellendi. Artık yeni şifrenizle giriş yapabilirsiniz.
                                    </div>
                                    <div class="mt-4 text-center">
                                        <a href="login.php" class="btn btn-primary w-100 waves-effect waves-light">Giriş Sayfasına Dön</a>
                                    </div>
                                <?php elseif ($error): ?>
                                    <div class="alert alert-danger text-center mb-4 mt-4" role="alert">
                                        <?php echo $error; ?>
                                    </div>
                                    <?php if (strpos($error, 'Geçersiz') !== false): ?>
                                        <div class="mt-4 text-center">
                                            <a href="login.php" class="btn btn-primary w-100 waves-effect waves-light">Giriş Sayfasına Dön</a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!$success && $user): ?>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?token=" . $token; ?>" class="custom-form mt-4">
                                        
                                        <div class="form-floating form-floating-custom mb-3 auth-pass-inputgroup">
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Yeni Şifre" required>
                                            <label for="password">Yeni Şifre</label>
                                            <div class="form-floating-icon">
                                                <i data-feather="lock"></i>
                                            </div>
                                            <button type="button" class="btn btn-link position-absolute h-100 end-0 top-0" id="password-addon">
                                                <i class="mdi mdi-eye-outline font-size-18 text-muted"></i>
                                            </button>
                                        </div>

                                        <div class="form-floating form-floating-custom mb-3 auth-pass-inputgroup">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Şifre Tekrar" required>
                                            <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                                            <div class="form-floating-icon">
                                                <i data-feather="check-circle"></i>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <button class="btn btn-primary w-100 waves-effect waves-light" type="submit">Şifreyi Güncelle</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-4 mt-md-5 text-center">
                                <p class="mb-0">© <script>document.write(new Date().getFullYear())</script> Ersan Elektrik</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bg content same as login.php -->
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay"></div>
                    <ul class="bg-bubbles">
                        <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>

</body>
</html>
