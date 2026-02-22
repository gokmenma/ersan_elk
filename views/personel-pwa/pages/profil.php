<?php
/**
 * Personel PWA - Profil Sayfası
 * Personel bilgileri ve ayarlar
 */
use App\Helper\Helper;
use App\Helper\Date;

?>

<div class="flex flex-col min-h-screen pb-8">
    <!-- Header with Profile Photo -->
    <header class="bg-gradient-primary text-white px-4 pt-6 pb-16 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 mb-10"></div>
        </div>

        <div class="relative z-10 flex items-center justify-between">
            <h1 class="text-xl font-bold">Profilim</h1>
            <button onclick="toggleDarkMode()"
                class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
            </button>
        </div>
    </header>

    <!-- Profile Card -->
    <section class="px-4 -mt-12 relative z-20">
        <div class="card p-6 text-center">
            <div class="relative w-24 h-24 mx-auto -mt-16">
                <div
                    class="w-24 h-24 rounded-full bg-slate-200 dark:bg-slate-700 border-4 border-white dark:border-card-dark overflow-hidden shadow-lg">
                    <?php if (!empty($personel->resim_yolu)): ?>
                        <img id="profile-image" src="<?php echo Helper::base_url($personel->resim_yolu); ?>" alt="Profil"
                            class="w-full h-full object-cover">
                    <?php else: ?>
                        <div id="profile-placeholder" class="w-full h-full flex items-center justify-center bg-primary/10">
                            <span class="material-symbols-outlined text-primary text-4xl">person</span>
                        </div>
                        <img id="profile-image" src="" alt="Profil" class="w-full h-full object-cover hidden">
                    <?php endif; ?>
                </div>
                <button onclick="document.getElementById('profile-upload').click()"
                    class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center shadow-lg border-2 border-white dark:border-card-dark">
                    <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <input type="file" id="profile-upload" class="hidden" accept="image/*"
                    onchange="uploadProfileImage(this)">
            </div>

            <h2 class="text-xl font-bold text-slate-900 dark:text-white mt-4">
                <?php echo $personel->adi_soyadi ?? 'Personel'; ?>
            </h2>
            <p class="text-sm text-slate-500"><?php echo $personel->pozisyon ?? 'Çalışan'; ?></p>

            <div class="flex items-center justify-center gap-2 mt-3">
                <span class="badge badge-primary"><?php echo $personel->departman ?? 'Departman'; ?></span>
                <span class="badge badge-success">Aktif</span>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100 dark:border-slate-800">
                <div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white" id="kalan-izin-profil">15</p>
                    <p class="text-xs text-slate-500">Kalan İzin</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white" id="calismaCcalisma-yili">3</p>
                    <p class="text-xs text-slate-500">Yıl Kıdem</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-primary" id="puan">4.8</p>
                    <p class="text-xs text-slate-500">Performans</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Personal Info -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Kişisel Bilgiler</h3>
        <div class="card overflow-hidden">
            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">badge</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">TC Kimlik No</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?php echo $personel->tc_no ?? '***********'; ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">cake</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">Doğum Tarihi</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?php echo Date::dmY($personel->dogum_tarihi ?? null); ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">calendar_today</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">İşe Başlama Tarihi</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?php echo Date::dmY($personel->ise_giris_tarihi ?? null) ?? ''; ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Info -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">İletişim Bilgileri</h3>
        <div class="card overflow-hidden">
            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600">phone</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">Telefon</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?php echo $personel->telefon ?? '+90 5** *** ** **'; ?>
                    </p>
                </div>
                <a href="tel:<?php echo $personel->telefon ?? ''; ?>"
                    class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">call</span>
                </a>
            </div>

            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600">mail</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">E-posta</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                        <?php echo $personel->email_adresi ?? 'email@example.com'; ?>
                    </p>
                </div>
                <a href="mailto:<?php echo $personel->email_adresi ?? ''; ?>"
                    class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">send</span>
                </a>
            </div>

            <div class="flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-orange-600">location_on</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">Adres</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <?php echo $personel->adres ?? 'Adres bilgisi yok'; ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Settings -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Hızlı Ayarlar</h3>
        <div class="card overflow-hidden">
            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800"
                id="notification-setting">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600">notifications</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Bildirimler</p>
                    <p class="text-xs text-slate-500" id="notification-status">Durum kontrol ediliyor...</p>
                </div>
                <button type="button" id="notification-toggle-btn" onclick="toggleNotifications()"
                    data-subscribed="false" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold">
                    ...
                </button>
            </div>

            <div class="flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">dark_mode</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Karanlık Mod</p>
                    <p class="text-xs text-slate-500">Tema değiştir</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="dark-mode-toggle" class="sr-only peer" onchange="toggleDarkMode()">
                    <div
                        class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
                    </div>
                </label>
            </div>

            <div class="flex items-start gap-4 p-4 border-b border-slate-100 dark:border-slate-800"
                id="theme-color-setting">
                <div
                    class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-indigo-600">palette</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Tema Rengi</p>
                    <p class="text-xs text-slate-500 mb-3">Uygulama rengini değiştir</p>
                    <div class="flex items-center gap-2.5 flex-wrap" id="theme-color-swatches"></div>
                </div>
            </div>

            <button onclick="Modal.open('password-modal')" class="flex items-center gap-4 p-4 w-full text-left">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600">lock</span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Şifre Değiştir</p>
                    <p class="text-xs text-slate-500">Hesap güvenliği</p>
                </div>
                <span class="material-symbols-outlined text-slate-400">chevron_right</span>
            </button>
        </div>
    </section>

    <!-- Logout Button -->
    <section class="px-4 mt-6">
        <button onclick="logout()" class="w-full card p-4 flex items-center justify-center gap-3 text-red-500">
            <span class="material-symbols-outlined">logout</span>
            <span class="font-semibold">Çıkış Yap</span>
        </button>
    </section>

    <!-- App Version -->
    <div class="px-4 mt-6 text-center">
        <p class="text-xs text-slate-400">Ersan Elektrik v1.0.0</p>
        <p class="text-[10px] text-slate-400 mt-1">© 2024 Tüm hakları saklıdır.</p>
    </div>
</div>

<!-- Şifre Değiştir Modal -->
<div id="password-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">lock</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Şifre Değiştir</h3>
            </div>
            <button onclick="Modal.close('password-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="password-form" class="flex flex-col gap-4">
            <div>
                <label class="form-label">Mevcut Şifre</label>
                <div class="relative">
                    <input type="password" name="current_password" class="form-input pr-12" required>
                    <button type="button" onclick="togglePasswordVisibility(this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2">
                        <span class="material-symbols-outlined text-slate-400">visibility</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="form-label">Yeni Şifre</label>
                <div class="relative">
                    <input type="password" name="new_password" id="new_password" class="form-input pr-12" required
                        minlength="6">
                    <button type="button" onclick="togglePasswordVisibility(this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2">
                        <span class="material-symbols-outlined text-slate-400">visibility</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="form-label">Yeni Şifre (Tekrar)</label>
                <div class="relative">
                    <input type="password" name="confirm_password" class="form-input pr-12" required>
                    <button type="button" onclick="togglePasswordVisibility(this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2">
                        <span class="material-symbols-outlined text-slate-400">visibility</span>
                    </button>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-xl">
                <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">Şifre gereksinimleri:</p>
                <ul class="text-xs text-slate-500 space-y-1">
                    <li class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm" id="req-length">close</span>
                        En az 6 karakter
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm" id="req-match">close</span>
                        Şifreler eşleşmeli
                    </li>
                </ul>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('password-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    İptal
                </button>
                <button type="submit" class="flex-1 btn-primary py-3">
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        darkModeToggle.checked = App.darkMode;
        updateThemeIcon();

        // Render theme color swatches
        Theme.renderSwatches('theme-color-swatches');

        // Password form
        document.getElementById('password-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await changePassword(this);
        });

        // Password validation
        document.getElementById('new_password').addEventListener('input', validatePassword);
        document.querySelector('[name="confirm_password"]').addEventListener('input', validatePassword);
    });

    function togglePasswordVisibility(btn) {
        const input = btn.previousElementSibling;
        const icon = btn.querySelector('.material-symbols-outlined');

        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }

    function validatePassword() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.querySelector('[name="confirm_password"]').value;

        // Length check
        const lengthReq = document.getElementById('req-length');
        if (newPassword.length >= 6) {
            lengthReq.textContent = 'check';
            lengthReq.classList.add('text-green-500');
            lengthReq.classList.remove('text-red-500');
        } else {
            lengthReq.textContent = 'close';
            lengthReq.classList.add('text-red-500');
            lengthReq.classList.remove('text-green-500');
        }

        // Match check
        const matchReq = document.getElementById('req-match');
        if (newPassword === confirmPassword && confirmPassword.length > 0) {
            matchReq.textContent = 'check';
            matchReq.classList.add('text-green-500');
            matchReq.classList.remove('text-red-500');
        } else {
            matchReq.textContent = 'close';
            matchReq.classList.add('text-red-500');
            matchReq.classList.remove('text-green-500');
        }
    }

    function toggleDarkMode() {
        App.toggleDarkMode();
        document.getElementById('dark-mode-toggle').checked = App.darkMode;
        updateThemeIcon();
    }

    function updateThemeIcon() {
        const icon = document.getElementById('theme-icon');
        icon.textContent = App.darkMode ? 'light_mode' : 'dark_mode';
    }

    async function changePassword(form) {
        const formData = Form.serialize(form);

        if (formData.new_password !== formData.confirm_password) {
            Toast.show('Şifreler eşleşmiyor', 'error');
            return;
        }

        if (formData.new_password.length < 6) {
            Toast.show('Şifre en az 6 karakter olmalı', 'error');
            return;
        }

        try {
            const response = await API.request('changePassword', formData);

            if (response.success) {
                Toast.show('Şifreniz başarıyla değiştirildi', 'success');
                Modal.close('password-modal');
                form.reset();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    async function logout() {
        const isConfirmed = await Alert.confirm(
            'Çıkış Yap',
            'Çıkış yapmak istediğinize emin misiniz?',
            'Çıkış Yap',
            'Vazgeç'
        );

        if (!isConfirmed) return;

        try {
            const response = await API.request('logout');
            window.location.href = 'login.php';
        } catch (error) {
            window.location.href = 'login.php';
        }
    }
    async function uploadProfileImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const formData = new FormData();
            formData.append('action', 'updateProfileImage');
            formData.append('image', file);

            try {
                Loading.show();
                // API.request yerine fetch kullanıyoruz çünkü FormData gönderiyoruz
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Update image source
                    const img = document.getElementById('profile-image');
                    const placeholder = document.getElementById('profile-placeholder');

                    // Add timestamp to bypass cache
                    img.src = '<?php echo Helper::base_url(); ?>' + result.data.image_url + '?t=' + new Date().getTime();
                    img.classList.remove('hidden');

                    if (placeholder) {
                        placeholder.classList.add('hidden');
                    }

                    Toast.show('Profil resmi güncellendi', 'success');
                } else {
                    Toast.show(result.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                console.error(error);
                Toast.show('Bir hata oluştu', 'error');
            } finally {
                Loading.hide();
            }
        }
    }
</script>