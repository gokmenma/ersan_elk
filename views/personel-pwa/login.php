<?php
/**
 * Personel PWA - Giriş Sayfası
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\PersonelModel;

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['personel_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

// Form gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = $_POST['login_input'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $PersonelModel = new PersonelModel();

        // TC Kimlik No veya Telefon ile personeli bul
        // Model üzerinden DB bağlantısını alıp özel sorgu atıyoruz
        $db = $PersonelModel->getDb();
        $stmt = $db->prepare("SELECT * FROM personel WHERE tc_kimlik_no = :input OR cep_telefonu = :input");
        $stmt->execute(['input' => $login_input]);
        $personel = $stmt->fetch(PDO::FETCH_OBJ);

        if ($personel) {

            // Şifre kontrolü
            // Not: Gerçek sistemde password_verify kullanılmalı
            // Geliştirme ortamı için '123456' şifresini veya veritabanındaki şifreyi kabul ediyoruz
            $dbPassword = $personel->sifre ?? '';

            if ($password === '123456' || $password === $dbPassword) {
                $_SESSION['personel_id'] = $personel->id;
                $_SESSION['personel_tc'] = $personel->tc_kimlik_no;
                $_SESSION['personel_adi'] = $personel->adi_soyadi;

                header("Location: index.php");
                exit();
            } else {
                $error = 'Şifre hatalı.';
            }
        } else {
            $error = 'Bu bilgilerle kayıtlı personel bulunamadı.';
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

    <title>Giriş - Ersan Elektrik</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "primary-dark": "#0d47c1",
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

        .pattern-bg {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>

<body
    class="min-h-screen bg-gradient-to-br from-primary via-primary-dark to-blue-900 pattern-bg flex flex-col items-center justify-center p-4">

    <!-- Logo & Brand -->
    <div class="text-center mb-8 float-animation">
        <div
            class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-2xl">
            <span class="material-symbols-outlined text-white text-4xl">shield_person</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Ersan Elektrik</h1>
        <p class="text-white/60 text-sm mt-1">Kurumsal Erişim</p>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-slate-900">Hoş Geldiniz</h2>
            <p class="text-slate-500 text-sm mt-1">Devam etmek için giriş yapın</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
                <span class="material-symbols-outlined text-red-500">error</span>
                <p class="text-sm text-red-600"><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-2">TC Kimlik No veya Telefon</label>
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">badge</span>
                    <input type="text" name="login_input"
                        class="w-full pl-12 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                        placeholder="TC No veya Telefon" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-600 mb-2">Şifre</label>
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                    <input type="password" name="password" id="password"
                        class="w-full pl-12 pr-12 py-3.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                        placeholder="••••••" required>
                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2">
                        <span class="material-symbols-outlined text-slate-400" id="password-icon">visibility</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember"
                        class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span class="text-sm text-slate-600">Beni hatırla</span>
                </label>
                <a href="#" class="text-sm text-primary font-semibold hover:underline">Şifremi Unuttum</a>
            </div>

            <button type="submit"
                class="w-full py-3.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-dark transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2 mt-2">
                <span>Giriş Yap</span>
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
        </form>

        <!-- Demo Info -->
        <div class="mt-6 p-4 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500 text-center mb-2">Demo Hesap Bilgileri:</p>
            <div class="flex justify-center gap-4 text-xs">
                <span class="bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                    <strong class="text-slate-600">TC:</strong> 12345678901
                </span>
                <span class="bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                    <strong class="text-slate-600">Şifre:</strong> 123456
                </span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center">
        <p class="text-white/40 text-xs">© 2024 Ersan Elektrik. Tüm hakları saklıdır.</p>
    </div>

    <script>
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
    </script>
</body>

</html>