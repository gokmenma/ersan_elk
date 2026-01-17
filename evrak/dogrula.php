<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Can Sağlık Sendikası | Evrak Doğrulama Sistemi</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (İkonlar için) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <!-- Özel CSS Stilleri -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="verification-container">
        <div class="verification-card">
            <!-- Logo veya Başlık -->
            <div class="card-header text-center">
                <img src="../assets/images/Logo2025.png" alt="Can Sağlık Sendikası" class="mb-3" style="max-width: 100px;">
                <h2>Evrak Doğrulama</h2>
                <p class="text-muted">Lütfen evrak üzerinde bulunan doğrulama kodunu girin.</p>
            </div>

            <div class="card-body p-4 p-md-5">
                <!-- 1. DOĞRULAMA FORMU (Varsayılan olarak görünür) -->
                <form id="verificationForm">
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" class="form-control" id="verificationCode" placeholder="Doğrulama Kodunu Girin..." aria-label="Doğrulama Kodu" required>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary btn-lg" type="submit">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span class="btn-text">Doğrula</span>
                        </button>
                    </div>
                </form>

                <!-- 2. BAŞARILI SONUÇ (Varsayılan olarak gizli) -->
                <div id="successResult" class="text-center d-none">
                    <div class="result-icon success-icon mx-auto mb-4">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-3">Evrak Başarıyla Doğrulandı</h3>
                    
                    <ul class="list-group list-group-flush text-start mb-4">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Evrak Türü:</strong>
                            <span id="docType"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Oluşturma Tarihi:</strong>
                            <span id="docDate"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Kime:</strong>
                            <span id="docRecipient"></span>
                        </li>
                    </ul>

                    <div class="d-grid">
                        <a id="downloadLink" href="#" class="btn btn-success btn-lg" download>
                            <i class="fas fa-download me-2"></i> Evrakı İndir
                        </a>
                    </div>
                    <button id="resetButton" class="btn btn-link mt-3">Yeni Sorgulama Yap</button>
                </div>

                <!-- 3. HATA SONUCU (Varsayılan olarak gizli) -->
                <div id="errorResult" class="text-center d-none">
                    <div class="result-icon error-icon mx-auto mb-4">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="mb-3">Doğrulama Başarısız</h3>
                    <p class="text-danger" id="errorMessage">Girdiğiniz kod geçersiz veya böyle bir evrak bulunamadı. Lütfen kontrol ederek tekrar deneyin.</p>
                    <button id="tryAgainButton" class="btn btn-secondary mt-3">Tekrar Dene</button>
                </div>
            </div>
        </div>
        <footer class="text-center mt-4 text-muted small">
            © <?php echo date("Y") ?> Can Sağlık Sendikası | Tüm hakları saklıdır.
        </footer>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Özel JavaScript Kodu -->
    <script src="script.js"></script>
</body>
</html> 