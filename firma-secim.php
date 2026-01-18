<?php
require_once "vendor/autoload.php";

use App\Model\FirmaModel;
use App\Model\UserModel;
use App\Helper\Helper;

session_start();

$Firma = new FirmaModel();
$User = new UserModel();

$branchs = $Firma->all();
//Helper::dd($branchs);

// Varsayılan firma cookie kontrolü - otomatik yönlendirme
if (isset($_COOKIE['varsayilan_firma_id']) && !empty($_COOKIE['varsayilan_firma_id'])) {
    $varsayilan_firma_id = (int) $_COOKIE['varsayilan_firma_id'];
    // Firma hala aktif mi kontrol et
    $firma_aktif = false;
    foreach ($branchs as $branch) {
        if ($branch->id == $varsayilan_firma_id) {
            $firma_aktif = true;
            break;
        }
    }
    if ($firma_aktif) {
        $_SESSION['firma_id'] = $varsayilan_firma_id;
        header("Location: /set-session.php?firma_id=" . $varsayilan_firma_id);
        exit;
    }
}

//**Eğer 1 adet sube varsa direkt yönlendir */
if (count($branchs) == 1) {
    $only_branch = $branchs[0];
    $_SESSION['sube_id'] = $only_branch->id;
    header("Location: /set-session.php?firma_id=" . $only_branch->id);
    exit;
}

?>


<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Seçimi | Ersan Elektrik</title>
    <!-- Google Fonts: Modern bir yazı tipi -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome: İkonlar için -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Animate.css for entrance animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />



    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap');

        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-main: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.7);
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-hover: rgba(255, 255, 255, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto Condensed', sans-serif;
            background: #0f172a;
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background Shapes */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            filter: blur(80px);
            border-radius: 50%;
            opacity: 0.4;
            animation: float 20s infinite alternate;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            background: #4f46e5;
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            background: #ec4899;
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 250px;
            height: 250px;
            background: #06b6d4;
            top: 40%;
            left: 60%;
            animation-delay: -10s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(50px, 50px) scale(1.1);
            }
        }

        .branch-selector-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 50px 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 900px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .header {
            margin-bottom: 40px;
        }

        .header .logo-wrapper {
            width: 80px;
            height: 80px;
            background: var(--primary);
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            transform: rotate(-10deg);
            transition: transform 0.3s ease;
        }

        .header .logo-wrapper:hover {
            transform: rotate(0deg) scale(1.1);
        }

        .header .logo-icon {
            font-size: 2.5rem;
            color: #fff;
        }

        .header h2 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 300;
        }

        .branch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .branch-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px 20px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .branch-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .branch-card:hover {
            transform: translateY(-10px);
            background: var(--card-hover);
            border-color: var(--primary);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .branch-card:hover::before {
            opacity: 1;
        }

        .branch-card .card-icon-wrapper {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .branch-card:hover .card-icon-wrapper {
            background: var(--primary);
            transform: scale(1.1);
        }

        .branch-card .card-icon {
            font-size: 1.8rem;
            color: var(--primary);
            transition: color 0.3s ease;
        }

        .branch-card:hover .card-icon {
            color: #fff;
        }

        .branch-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
        }

        .branch-card .location {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .branch-card .checkmark {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.4rem;
            color: var(--primary);
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 2;
        }

        /* Seçili Kart Stili */
        .branch-card.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.15);
            box-shadow: 0 0 0 2px var(--primary);
        }

        .branch-card.selected .checkmark {
            opacity: 1;
            transform: scale(1);
        }

        .branch-card.selected .card-icon-wrapper {
            background: var(--primary);
        }

        .branch-card.selected .card-icon {
            color: #fff;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Varsayılan Firma Checkbox Stili */
        .default-firm-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .default-firm-option:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
        }

        .default-firm-option input[type="checkbox"] {
            display: none;
        }

        .default-firm-option .checkbox-custom {
            width: 24px;
            height: 24px;
            border: 2px solid var(--glass-border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .default-firm-option .checkbox-custom i {
            font-size: 14px;
            color: #fff;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s ease;
        }

        .default-firm-option input[type="checkbox"]:checked+.checkbox-custom {
            background: var(--primary);
            border-color: var(--primary);
        }

        .default-firm-option input[type="checkbox"]:checked+.checkbox-custom i {
            opacity: 1;
            transform: scale(1);
        }

        .default-firm-option .checkbox-label {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 400;
            transition: color 0.3s ease;
        }

        .default-firm-option:hover .checkbox-label,
        .default-firm-option input[type="checkbox"]:checked~.checkbox-label {
            color: var(--text-main);
        }

        .default-firm-option .skip-info {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.4);
            margin-left: 5px;
        }

        #continue-btn {
            width: 100%;
            padding: 18px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        #continue-btn:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.5);
        }

        #continue-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        #continue-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.3);
            box-shadow: none;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Mobil Cihazlar için Ayarlamalar */
        @media (max-width: 640px) {
            .branch-selector-container {
                padding: 30px 20px;
                margin: 20px;
                border-radius: 20px;
            }

            .header h2 {
                font-size: 1.8rem;
            }

            .branch-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="branch-selector-container animate__animated animate__fadeInUp">

        <div class="header">
            <div class="logo-wrapper">
                <i class="fa-solid fa-paper-plane logo-icon"></i>
            </div>
            <h2>Hoş Geldiniz!</h2>
            <p>Lütfen devam etmek için işlem yapacağınız şubeyi seçin.</p>
        </div>

        <div class="branch-grid">
            <?php foreach ($branchs as $index => $branch) { ?>
                <div class="branch-card animate__animated animate__fadeInUp"
                    style="animation-delay: <?php echo ($index * 0.1) + 0.2 ?>s" data-branch-id="<?php echo $branch->id ?>">
                    <div class="checkmark"><i class="fas fa-check-circle"></i></div>
                    <div class="card-icon-wrapper">
                        <i class="card-icon fa-solid fa-store"></i>
                    </div>
                    <h3><?php echo $branch->firma_adi ?></h3>
                    <p class="location"><?php echo $branch->adres ?></p>
                </div>
            <?php } ?>
        </div>

        <div class="actions">
            <label class="default-firm-option" for="varsayilan-firma">
                <input type="checkbox" id="varsayilan-firma" name="varsayilan_firma">
                <span class="checkbox-custom"><i class="fas fa-check"></i></span>
                <span class="checkbox-label">
                    Varsayılan olarak bu firmayı seç
                    <span class="skip-info">(Bu adımı atla)</span>
                </span>
            </label>
            <button id="continue-btn" disabled>
                <span>Devam Et</span>
                <div class="loading-spinner"></div>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchCards = document.querySelectorAll('.branch-card');
            const continueBtn = document.getElementById('continue-btn');
            const btnText = continueBtn.querySelector('span');
            const btnIcon = continueBtn.querySelector('.fa-arrow-right');
            const btnSpinner = continueBtn.querySelector('.loading-spinner');

            branchCards.forEach(card => {
                card.addEventListener('click', () => {
                    branchCards.forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    continueBtn.disabled = false;

                    // Subtle haptic-like feedback
                    if (window.navigator.vibrate) {
                        window.navigator.vibrate(5);
                    }
                });
            });

            continueBtn.addEventListener('click', () => {
                const selectedCard = document.querySelector('.branch-card.selected');

                if (selectedCard) {
                    const branchId = selectedCard.dataset.branchId;

                    // UI Feedback
                    btnText.textContent = 'Yönlendiriliyor...';
                    btnIcon.style.display = 'none';
                    btnSpinner.style.display = 'block';
                    continueBtn.disabled = true;

                    // Varsayılan firma checkbox kontrolü
                    const isDefault = document.getElementById('varsayilan-firma').checked;

                    // Redirect with default firm parameter
                    setTimeout(() => {
                        let url = `/set-session.php?firma_id=${branchId}`;
                        if (isDefault) {
                            url += '&varsayilan=1';
                        }
                        window.location.href = url;
                    }, 600);
                }
            });
        });
    </script>

</body>

</html>