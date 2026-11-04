<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['personel_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['pwa_sifre_degistir_zorunlu'])) {
    header('Location: index.php');
    exit();
}

$adi = htmlspecialchars((string) ($_SESSION['personel_adi'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#e6eaef">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/app-192.png?v=2">
    <title>Şifre Değiştir - Ersan Elektrik</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #e6eaef; font-family: 'Roboto Condensed', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 16px; }
        .card { width: 100%; max-width: 400px; background: #fff; border-radius: 28px; padding: 28px 24px; box-shadow: 0 10px 30px rgba(25, 29, 36, .12); }
        h1 { margin: 0 0 6px; font-size: 22px; color: #191d24; }
        p { margin: 0 0 16px; color: #5b6472; font-size: 14px; line-height: 1.45; }
        label { display: block; font-size: 13px; color: #5b6472; margin: 12px 0 6px; }
        input { width: 100%; min-height: 48px; border: 1px solid #d5dae2; border-radius: 16px; padding: 0 16px; font-size: 18px; letter-spacing: 4px; font-family: inherit; }
        input:focus { outline: 2px solid #191d24; border-color: transparent; }
        ul { margin: 14px 0 0; padding-left: 18px; color: #5b6472; font-size: 13px; line-height: 1.6; }
        button { width: 100%; min-height: 48px; margin-top: 18px; border: 0; border-radius: 999px; background: #191d24; color: #fff; font-size: 16px; font-weight: 600; font-family: inherit; cursor: pointer; }
        button:disabled { opacity: .6; }
        .msg { margin-top: 12px; font-size: 14px; min-height: 20px; color: #c0392b; }
        .out { display: block; text-align: center; margin-top: 14px; font-size: 14px; color: #5b6472; }
    </style>
</head>

<body>
    <div class="card">
        <h1>Şifrenizi Değiştirin</h1>
        <p><?= $adi ?>, mevcut şifreniz kolay tahmin edilebilir olduğu için devam etmeden önce yeni bir şifre belirlemeniz gerekiyor.</p>
        <form id="f" autocomplete="off">
            <label for="current_password">Mevcut Şifre</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            <label for="new_password">Yeni Şifre (6 haneli rakam)</label>
            <input type="password" id="new_password" name="new_password" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password">
            <label for="confirm_password">Yeni Şifre (Tekrar)</label>
            <input type="password" id="confirm_password" name="confirm_password" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password">
            <ul>
                <li>Tam 6 haneli rakam olmalı</li>
                <li>Ardışık olmamalı (123456, 654321, 345... gibi)</li>
                <li>Aynı rakam tekrarı olmamalı (111111)</li>
                <li>Doğum tarihinizle ilişkili olmamalı</li>
            </ul>
            <div class="msg" id="msg" role="alert"></div>
            <button type="submit" id="btn">Şifreyi Değiştir</button>
        </form>
        <a class="out" href="login.php" id="logout">Çıkış yap</a>
    </div>
    <script>
        const form = document.getElementById('f');
        const msg = document.getElementById('msg');
        const btn = document.getElementById('btn');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            msg.style.color = '#c0392b';
            const neu = form.new_password.value;
            if (!/^\d{6}$/.test(neu)) { msg.textContent = 'Şifre 6 haneli rakamlardan oluşmalı.'; return; }
            if (neu !== form.confirm_password.value) { msg.textContent = 'Şifreler eşleşmiyor.'; return; }
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('action', 'changePassword');
                fd.append('current_password', form.current_password.value);
                fd.append('new_password', neu);
                const r = await fetch('api.php', { method: 'POST', body: fd });
                const j = await r.json();
                if (j.success) {
                    msg.style.color = '#1e8449';
                    msg.textContent = 'Şifreniz değiştirildi, yönlendiriliyorsunuz...';
                    window.location.replace('index.php');
                    return;
                }
                msg.textContent = j.message || 'Bir hata oluştu.';
            } catch (err) {
                msg.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
            }
            btn.disabled = false;
        });

        document.getElementById('logout').addEventListener('click', async function (e) {
            e.preventDefault();
            try {
                const fd = new FormData();
                fd.append('action', 'logout');
                await fetch('api.php', { method: 'POST', body: fd });
            } catch (err) { }
            window.location.replace('login.php');
        });
    </script>
</body>

</html>
