<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Giriş Paneli | Ersan Elektrik</title>

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tabler Icons or similar if needed, but I'll use simple CSS for now -->
    
    <style>
        :root {
            --primary: #135bec;
            --primary-hover: #0d4bc7;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 10px 40px -10px rgba(19, 91, 236, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        /* Abstract background shapes */
        .shape {
            position: absolute;
            z-index: -1;
            filter: blur(80px);
            opacity: 0.4;
            border-radius: 50%;
        }
        .shape-1 {
            width: 300px;
            height: 300px;
            background: #135bec;
            top: -100px;
            right: -50px;
        }
        .shape-2 {
            width: 250px;
            height: 250px;
            background: #38bdf8;
            bottom: -50px;
            left: -50px;
        }

        .container {
            width: 100%;
            max-width: 440px;
            animation: fadeIn 0.8s ease-out forwards;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 48px 40px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.6);
            text-align: center;
        }

        .logo-wrapper {
            margin-bottom: 32px;
        }

        .logo-wrapper img {
            height: 48px;
            width: auto;
        }

        .content h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .content p {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn-portal {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 16px 24px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            border-radius: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(19, 91, 236, 0.2);
        }

        .btn-portal:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(19, 91, 236, 0.3);
        }

        .btn-portal:active {
            transform: translateY(0);
        }

        .footer-info {
            margin-top: 32px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .footer-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Mobile */
        @media (max-width: 480px) {
            .card {
                padding: 40px 24px;
            }
            .content h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="container">
        <div class="card">
            <div class="logo-wrapper">
                <img src="assets/images/logo.png" alt="Ersan Elektrik Logo">
            </div>

            <div class="content">
                <h1>Giriş Bilgileri Değişti</h1>
                <p>Güvenli ve hızlı bir deneyim için yeni giriş adresimize erişebilirsiniz.</p>

                <a href="https://personel.ersantr.com" class="btn-portal">
                    Sisteme Giriş Yap
                </a>
            </div>

            <div class="footer-info">
                © <script>document.write(new Date().getFullYear())</script> 
                <strong>Ersan Elektrik</strong> 
                — <span style="opacity: 0.7">Personel Yönetimi</span>
            </div>
        </div>
    </div>

</body>

</html>