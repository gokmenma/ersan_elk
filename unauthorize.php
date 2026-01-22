<?php
require_once 'Autoloader.php';
use App\Helper\Helper;


?>
<!doctype html>
<html lang="tr">

<head>
    <meta charset="utf-8" />
    <title>Yetkisiz Erişim | Ersan Elektrik</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

    <style>
        .auth-error-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7fe;
            padding: 20px;
        }

        .auth-error-card {
            max-width: 600px;
            width: 100%;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: none;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-error-header {
            background: linear-gradient(135deg, #f46a6a 0%, #d93025 100%);
            padding: 40px 20px;
            text-align: center;
            color: #fff;
        }

        .auth-error-header i {
            font-size: 60px;
            margin-bottom: 15px;
            display: block;
            animation: shake 2s infinite;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .auth-error-body {
            padding: 40px;
        }

        .reason-list {
            background: #fff5f5;
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #f46a6a;
        }

        .reason-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .reason-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            color: #495057;
            font-size: 14px;
        }

        .reason-list li i {
            color: #f46a6a;
            margin-right: 10px;
            font-size: 18px;
        }

        .btn-rounded {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-rounded:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body data-topbar="dark">
    <div class="auth-error-wrapper">
        <div class="auth-error-card">
            <div class="auth-error-header">
                <i class="bx bx-shield-x"></i>
                <h2 class="text-white mb-0">Erişim Engellendi</h2>
            </div>
            <div class="auth-error-body text-center">
                <h4 class="text-dark mb-3">Bu Sayfaya Yetkiniz Bulunmamaktadır</h4>
                <p class="text-muted font-size-15">
                    Üzgünüz, talep ettiğiniz sayfayı görüntülemek için gerekli yetki seviyesine sahip değilsiniz. Bu
                    durum hesap ayarlarınız veya kısıtlı bir alanla ilgili olabilir.
                </p>

                <div class="reason-list text-start">
                    <h6 class="text-danger mb-3"><i class="bx bx-search-alt"></i> Olası Nedenler:</h6>
                    <ul>
                        <li><i class="bx bx-x-circle"></i> Bu modül için yetki tanımlamanız yapılmamış olabilir.</li>
                        <li><i class="bx bx-x-circle"></i> Hesabınız bu sayfaya erişim için kısıtlanmış olabilir.</li>
                        <li><i class="bx bx-x-circle"></i> Oturum süreniz dolmuş veya geçersiz bir bağlantı olabilir.
                        </li>
                    </ul>
                </div>

                <div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
                    <a href="index" class="btn btn-primary btn-rounded">
                        <i class="bx bx-home-alt"></i> Ana Sayfaya Dön
                    </a>
                    <button onclick="history.back()" class="btn btn-outline-secondary btn-rounded">
                        <i class="bx bx-arrow-back"></i> Geri Git
                    </button>
                </div>
            </div>
            <div class="p-4 bg-light border-top text-center">
                <p class="mb-0 text-muted font-size-13">
                    Erişim sorununuz devam ederse lütfen <strong>Sistem Yöneticisi</strong> ile iletişime geçin.
                </p>
                <p class="mb-0 mt-1 text-muted font-size-12">©
                    <?php echo date('Y'); ?> Ersan Elektrik. Tüm Hakları Saklıdır.
                </p>
            </div>
        </div>
    </div>

    <?php include 'layouts/vendor-scripts.php'; ?>
</body>

</html>