# Tech Context

## Kullanılan Teknolojiler, Kütüphaneler ve Sürümler

### 1. Çalışma Ortamı (Runtime)
- **Sunucu & Stack**: Linux (XAMPP / LAMPP)
- **PHP CLI**: `/opt/lampp/bin/php`
- **Veritabanı**: MariaDB / MySQL (PDO bağlantısı)

### 2. Backend Bağımlılıkları (Composer)
- `phpoffice/phpspreadsheet`: Excel içe/dışa aktarma ve bordro raporlaması
- `phpoffice/phpword`: Word şablonları ve belge üretimi
- `dompdf/dompdf` & `mpdf/mpdf`: PDF oluşturma ve rapor dökümleri
- `smalot/pdfparser`: PDF ayrıştırma işlemleri
- `vlucas/phpdotenv`: `.env` ortam değişkenleri yönetimi
- `phpmailer/phpmailer`: E-posta gönderimi
- `minishlink/web-push`: Web push bildirimleri
- `sentry/sentry`: Hata ve performans izleme
- `chillerlan/php-qrcode`: QR kod üretimi
- `pestphp/pest`: Test altyapısı (Test ortamı)

### 3. Frontend & Kütüphaneler
- **DataTables**: Sunucu taraflı (serverSide) ve istemci taraflı gelişmiş tablolar, header filtre eklentisi (`datatable-filters.js`, `datatables.init.js`).
- **Select2**: Dinamik formatlanmış, çift satırlı arama ve seçim bileşenleri.
- **Tasarım / CSS**: Bootstrap tabanlı altyapı, özel macOS Dark tema (`style.css`), mobil tasarım tokenları (`src/design-system.css`).
- **İkonlar**: Boxicons (`bx bx-*`).
