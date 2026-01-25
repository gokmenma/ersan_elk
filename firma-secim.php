<?php
require_once "vendor/autoload.php";

use App\Model\FirmaModel;
use App\Model\UserModel;
use App\Helper\Helper;

session_start();

$Firma = new FirmaModel();
$User = new UserModel();

$branchs = $Firma->all();

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
    
    <!-- Geist Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    
    <style>
        :root {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --slate-950: #020817;
            
            --primary: #0f172a;
            --primary-foreground: #f8fafc;
            
            --radius: 0.5rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Geist', sans-serif;
            background-color: var(--slate-50);
            color: var(--slate-900);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 800px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            background: #fff;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius);
            padding: 3rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .logo i {
            font-size: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.025em;
            color: var(--slate-900);
        }

        .header p {
            font-size: 1rem;
            color: var(--slate-500);
            margin-top: 0.5rem;
        }

        .branch-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .branch-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 2.5rem 1.5rem;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background-color: #fff;
        }

        .branch-item:hover {
            background-color: var(--slate-50);
            border-color: var(--slate-300);
            transform: translateY(-2px);
        }

        .branch-item.selected {
            border-color: var(--slate-900);
            background-color: var(--slate-50);
            box-shadow: 0 0 0 1px var(--slate-900);
        }

        .branch-icon {
            width: 3.5rem;
            height: 3.5rem;
            background-color: var(--slate-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
            color: var(--slate-600);
            transition: all 0.2s ease;
        }

        .branch-item.selected .branch-icon {
            background-color: var(--slate-900);
            color: #fff;
        }

        .branch-info {
            width: 100%;
        }

        .branch-name {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--slate-900);
            margin-bottom: 0.5rem;
        }

        .branch-address {
            font-size: 0.8125rem;
            color: var(--slate-500);
            line-height: 1.4;
        }

        .check-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: var(--slate-900);
            font-size: 1.25rem;
            opacity: 0;
            transition: all 0.2s ease;
            transform: scale(0.5);
        }

        .branch-item.selected .check-icon {
            opacity: 1;
            transform: scale(1);
        }

        .footer-actions {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input {
            display: none;
        }

        .custom-checkbox {
            width: 1.125rem;
            height: 1.125rem;
            border: 1px solid var(--slate-300);
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .checkbox-container:hover .custom-checkbox {
            border-color: var(--slate-400);
        }

        .checkbox-container input:checked + .custom-checkbox {
            background-color: var(--slate-900);
            border-color: var(--slate-900);
        }

        .custom-checkbox i {
            color: #fff;
            font-size: 0.75rem;
            display: none;
        }

        .checkbox-container input:checked + .custom-checkbox i {
            display: block;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: var(--slate-600);
        }

        .btn-continue {
            width: 100%;
            background-color: var(--slate-900);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            padding: 1rem;
            font-family: inherit;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-continue:hover:not(:disabled) {
            opacity: 0.9;
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .spinner {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: var(--slate-950);
            }
            .card {
                background-color: var(--slate-950);
                border-color: var(--slate-800);
            }
            .header h1, .branch-name, .logo {
                color: var(--slate-50);
            }
            .branch-item {
                background-color: var(--slate-950);
                border-color: var(--slate-800);
            }
            .branch-item:hover {
                background-color: var(--slate-900);
            }
            .branch-item.selected {
                border-color: var(--slate-50);
                background-color: var(--slate-900);
                box-shadow: 0 0 0 1px var(--slate-50);
            }
            .branch-icon {
                background-color: var(--slate-900);
                color: var(--slate-400);
            }
            .branch-item.selected .branch-icon {
                background-color: var(--slate-50);
                color: var(--slate-950);
            }
            .check-icon {
                color: var(--slate-50);
            }
            .btn-continue {
                background-color: var(--slate-50);
                color: var(--slate-950);
            }
            .custom-checkbox {
                border-color: var(--slate-700);
            }
            .checkbox-container input:checked + .custom-checkbox {
                background-color: var(--slate-50);
                border-color: var(--slate-50);
            }
            .custom-checkbox i {
                color: var(--slate-950);
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <i class="fa-solid fa-bolt"></i>
                    <span>ER-SAN</span>
                </div>
                <h1>Hoş Geldiniz</h1>
                <p>Devam etmek için bir şube seçin</p>
            </div>

            <div class="branch-list">
                <?php foreach ($branchs as $branch) { ?>
                    <div class="branch-item" data-id="<?php echo $branch->id ?>">
                        <div class="branch-icon">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="branch-info">
                            <div class="branch-name"><?php echo $branch->firma_adi ?></div>
                            <div class="branch-address"><?php echo $branch->adres ?></div>
                        </div>
                        <i class="fa-solid fa-circle-check check-icon"></i>
                    </div>
                <?php } ?>
            </div>

            <div class="footer-actions">
                <label class="checkbox-container">
                    <input type="checkbox" id="varsayilan-firma">
                    <span class="custom-checkbox"><i class="fa-solid fa-check"></i></span>
                    <span class="checkbox-label">Varsayılan olarak ayarla</span>
                </label>

                <button id="continue-btn" class="btn-continue" disabled>
                    <span>Devam Et</span>
                    <div class="spinner"></div>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchItems = document.querySelectorAll('.branch-item');
            const continueBtn = document.getElementById('continue-btn');
            const btnText = continueBtn.querySelector('span');
            const spinner = continueBtn.querySelector('.spinner');
            const defaultCheck = document.getElementById('varsayilan-firma');

            let selectedId = null;

            branchItems.forEach(item => {
                item.addEventListener('click', () => {
                    branchItems.forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    selectedId = item.dataset.id;
                    continueBtn.disabled = false;
                });
            });

            continueBtn.addEventListener('click', () => {
                if (!selectedId) return;

                btnText.style.display = 'none';
                spinner.style.display = 'block';
                continueBtn.disabled = true;

                const isDefault = defaultCheck.checked;
                let url = `/set-session.php?firma_id=${selectedId}`;
                if (isDefault) url += '&varsayilan=1';

                setTimeout(() => {
                    window.location.href = url;
                }, 400);
            });
        });
    </script>

</body>

</html>