<?php

require_once __DIR__ . '/Autoloader.php';
use App\Model\SettingsModel;

// Check fallback token from DB
$apiKey = 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';
try {
    $settings = new SettingsModel();
    $all = $settings->getAllSettingsAsKeyValue();
    $apiKey = $all['api_sayac_degisim_sifre'] ?? $all['api_endeks_sifre'] ?? $apiKey;
} catch (\Throwable $e) {
    // Fallback stays the same
}

// Handle AJAX Request
if (isset($_POST['action']) && $_POST['action'] === 'fetch_stats') {
    header('Content-Type: application/json; charset=utf-8');

    $apiUrl = $_POST['api_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_donem_istatistik_secure.php?action=getDonemStats';
    $token = $_POST['api_token'] ?? $apiKey;
    $baslangic = $_POST['baslangic_donem'] ?? '202604';
    $bitis = $_POST['bitis_donem'] ?? '202604';

    $data = [
        'baslangic_donem' => $baslangic,
        'bitis_donem' => $bitis
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($token)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode([
            'status' => 'error',
            'message' => 'cURL Hatası: ' . $curlError
        ]);
        exit;
    }

    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'status' => 'error',
            'message' => 'API yanıtı JSON formatında değil veya boş.',
            'raw' => $response,
            'http_code' => $httpCode
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'http_code' => $httpCode,
        'data' => $decodedResponse
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dönem İstatistikleri API Test Paneli</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --card-border: rgba(0, 0, 0, 0.06);
            --accent-glow: rgba(79, 70, 229, 0.12);
            --accent-primary: #4f46e5;
            --accent-secondary: #7c3aed;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-color);
            background-image: radial-gradient(ellipse at 50% -20%, rgba(79, 70, 229, 0.06), rgba(255, 255, 255, 0));
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding-top: 3.5rem;
            padding-bottom: 3.5rem;
        }

        h1, h2, h3, h4, .outfit {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }

        .premium-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03),
                        0 8px 10px -6px rgba(0, 0, 0, 0.02);
            transition: all 0.35s ease;
            margin-bottom: 2rem;
        }

        .premium-card:hover {
            box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.05),
                        0 0 25px -2px var(--accent-glow);
            border-color: rgba(79, 70, 229, 0.2);
        }

        .form-label {
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--accent-primary);
        }

        .form-control, .form-select {
            background-color: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            color: #1e293b;
            padding: 0.85rem 1.1rem;
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px var(--accent-glow);
            color: #0f172a;
        }

        .btn-premium {
            background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 1rem 2.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.25);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -2px rgba(79, 70, 229, 0.35);
            filter: brightness(1.05);
        }

        .btn-premium:active {
            transform: translateY(1px);
        }

        .test-title {
            background: linear-gradient(135deg, #0f172a 40%, #4338ca 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.6rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
            text-align: center;
        }

        .test-subtitle {
            color: var(--text-muted);
            text-align: center;
            font-size: 1.1rem;
            margin-bottom: 3.5rem;
            font-weight: 400;
        }

        pre {
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 14px;
            padding: 1.5rem;
            color: #1e293b;
            max-height: 500px;
            overflow-y: auto;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            font-family: 'Fira Code', monospace;
        }

        .badge-status {
            font-size: 0.85rem;
            padding: 0.45rem 1rem;
            border-radius: 20px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            letter-spacing: 0.3px;
        }

        .badge-status-success {
            background-color: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-status-error {
            background-color: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge-status-info {
            background-color: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        /* Loading Spinner */
        .spinner-glow {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #ffffff;
            animation: spinGlow 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }

        @keyframes spinGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .response-container {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <h1 class="test-title">MARAŞKASKİ API Test Paneli</h1>
                <p class="test-subtitle outfit">Dönem İstatistikleri API Sorgulama & Test Sistemi</p>

                <div class="premium-card">
                    <form id="apiTestForm">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label for="api_url" class="form-label">
                                    <i class="fa-solid fa-link"></i> API Endpoint URL
                                </label>
                                <input type="url" class="form-control" id="api_url" name="api_url" value="https://yonetim.maraskaski.gov.tr/api/api_donem_istatistik_secure.php?action=getDonemStats" required>
                            </div>

                            <div class="col-md-12">
                                <label for="api_token" class="form-label">
                                    <i class="fa-solid fa-key"></i> Yetkilendirme Tokenı (Authorization Token)
                                </label>
                                <input type="text" class="form-control" id="api_token" name="api_token" value="<?php echo htmlspecialchars($apiKey); ?>" required>
                                <div class="form-text text-muted mt-2" style="font-size: 0.85rem;">
                                    <i class="fa-solid fa-circle-info me-1"></i> Mevcut tanımlı genel yetkilendirme şifreniz sistem tarafından otomatik çekilmiştir.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="baslangic_donem" class="form-label">
                                    <i class="fa-solid fa-calendar-days"></i> Başlangıç Dönemi
                                </label>
                                <input type="text" class="form-control" id="baslangic_donem" name="baslangic_donem" value="202604" placeholder="Örn: 202604" required>
                            </div>

                            <div class="col-md-6">
                                <label for="bitis_donem" class="form-label">
                                    <i class="fa-solid fa-calendar-days"></i> Bitiş Dönemi
                                </label>
                                <input type="text" class="form-control" id="bitis_donem" name="bitis_donem" value="202604" placeholder="Örn: 202604" required>
                            </div>

                            <div class="col-md-12 text-end mt-4">
                                <button type="submit" id="submitBtn" class="btn btn-premium w-100 py-3">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Verileri Sorgula ve Test Et
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- API Response Output -->
                <div class="premium-card response-container" id="responseCard">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0 outfit" style="color: #0f172a;">API Yanıtı ve İstatistik Verileri</h4>
                        <span id="httpStatusBadge" class="badge-status">
                            <i class="fa-solid fa-info-circle"></i> HTTP -
                        </span>
                    </div>

                    <pre id="apiOutput" style="color: #1e293b;">API isteği bekleniyor...</pre>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS + Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('apiTestForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const submitBtn = document.getElementById('submitBtn');
            const responseCard = document.getElementById('responseCard');
            const apiOutput = document.getElementById('apiOutput');
            const httpStatusBadge = document.getElementById('httpStatusBadge');

            // Set loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-glow me-2"></span> Sorgulanıyor...';
            responseCard.style.display = 'block';
            apiOutput.innerText = 'Sorgu sunucuya gönderiliyor, lütfen bekleyin...';
            apiOutput.style.color = '#64748b';

            const formData = new FormData(form);
            formData.append('action', 'fetch_stats');

            fetch('test_api_donem_istatistik.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    httpStatusBadge.className = 'badge-status badge-status-success';
                    httpStatusBadge.innerHTML = `<i class="fa-solid fa-circle-check"></i> HTTP ${res.http_code} OK`;
                    apiOutput.style.color = '#0f172a';
                    apiOutput.innerText = JSON.stringify(res.data, null, 4);
                } else {
                    httpStatusBadge.className = 'badge-status badge-status-error';
                    httpStatusBadge.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> Hata`;
                    apiOutput.style.color = '#b91c1c';
                    
                    if(res.raw) {
                        apiOutput.innerText = `${res.message}\n\n[Raw Response]:\n${res.raw}`;
                    } else {
                        apiOutput.innerText = JSON.stringify(res, null, 4);
                    }
                }
            })
            .catch(err => {
                httpStatusBadge.className = 'badge-status badge-status-error';
                httpStatusBadge.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> İstek Hatası`;
                apiOutput.style.color = '#b91c1c';
                apiOutput.innerText = 'Bir ağ veya istek hatası oluştu:\n' + err.message;
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i> Verileri Yeniden Sorgula';
            });
        });
    </script>
</body>
</html>
