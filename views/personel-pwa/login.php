<?php
/**
 * Personel PWA - Giriş Sayfası
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Clear any stale permission cache to ensure fresh permissions load
unset($_SESSION['permission_cache']);

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\PersonelModel;
use App\Model\SettingsModel;
use App\Model\MesajLogModel;
use App\Model\PersonelGirisLogModel;

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['personel_id'])) {
    header("Location: index.php");
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
        $stmt = $db->prepare("SELECT * FROM personel WHERE cep_telefonu = :phone OR cep_telefonu = :phone_with_zero");
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
        $response = ['status' => 'error', 'message' => 'Sistem hatası: ' . $e->getMessage()];
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
        $stmt = $db->prepare("SELECT * FROM personel WHERE tc_kimlik_no = :input OR cep_telefonu = :telefon OR cep_telefonu = :telefon_with_zero");
        $stmt->execute(['input' => $login_input, 'telefon' => $phone, 'telefon_with_zero' => '0' . $phone]);
        $personel = $stmt->fetch(PDO::FETCH_OBJ);

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
                        $token = base64_encode($personel->id . ':' . hash_hmac('sha256', $personel->id . $personel->sifre, 'ErsanElektrikPWASecretKey'));
                        setcookie('remember_token', $token, time() + (86400 * 30), "/");
                    }

                    header("Location: index.php");
                    exit();
                } else {
                    $error = 'Şifre hatalı.';
                }
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
                <a href="javascript:void(0)" onclick="openForgotModal()"
                    class="text-sm text-primary font-semibold hover:underline">Şifremi Unuttum</a>
            </div>

            <button type="submit"
                class="w-full py-3.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-dark transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2 mt-2">
                <span>Giriş Yap</span>
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
        </form>
    </div>

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