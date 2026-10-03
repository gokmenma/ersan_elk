# e-Fatura kurulum ve doğrulama

## Kurulum

PHP 8.2, PDO MySQL, SOAP, DOM/libxml ve BCMath gerekir. MariaDB 10.4 veya üzeri kullanılır. `storage/invoices` uygulama kullanıcısınca yazılabilir olmalıdır; doğrudan HTTP erişimi `.htaccess` ile engellenir. Apache dışında aynı erişim engeli sunucu yapılandırmasına eklenmelidir.

Veritabanı yedeğini aldıktan sonra aşağıdaki ayrı scriptleri sırayla uygulayın:

1. `sql/efatura_tamamlama.sql`
2. `sql/efatura_log_hassas_veri_temizleme.sql`

İlk script mevcut e-Fatura şeması üzerine ekleme yapar ve tekrar çalıştırılabilir. Mükerrer firma/ETTN ve giden fatura numarası raporunu inceleyin. Mükerrer varsa benzersizlik kurulumu durur; hiçbir kayıt otomatik silinmez. İkinci script eski SOAP loglarındaki hassas içerikleri maskeler. Scriptleri SQL hatasında devam eden istemci seçeneğiyle çalıştırmayın. Yeni izinler mevcut yönetici/muhasebe rol atamalarına eklenir; izin önbelleği/oturum varsa yenilenmelidir.

Bu geliştirmede uygulamanın gerçek veritabanına migration uygulanmadı. Mevcut LIVE/TEST ayarı değiştirilmedi, canlı gönderim, kabul/ret ve iptal yapılmadı.

## İşleyiş

Oluşturma desteği TEMELFATURA, TICARIFATURA, EARSIVFATURA ile SATIS, IADE, TEVKIFAT, ISTISNA tipleriyle sınırlıdır. İade TEMEL/e-Arşiv profiline ve orijinal fatura numarası/tarihine ihtiyaç duyar. Desteklenmeyen oluşturma kombinasyonları gönderilmez. İçe aktarılan XML'in tarafları, gerçek kalemleri ve mali tutarları kaynak değerleriyle korunur.

Taslak, önizleme ve UBL aynı BCMath hesaplama servisini kullanır. Para iki, miktar ve fiyat dört basamakla işlenir; yarım yukarı yuvarlama uygulanır. Gönderim öncesi resmî XSD, kod listeleri ve desteklenen imza öncesi Schematron kuralları kontrol edilir. EDM'nin dolduracağı boş imza uzantısı yalnızca XSD kontrol kopyasında geçici elemanla temsil edilir; gönderilen XML'e bu eleman eklenmez. Tam elektronik imza/zarf doğrulaması bu yerel kontrolün kapsamına girmez.

Numara tahsisi transaction/kilit kullanır; yıl fatura tarihinden alınır, EDM ve yerel son numarayla sayaç yalnızca ileri uzlaştırılır. Aynı fatura için eşzamanlı işlem kilitlidir. Timeout veya doğrulanamayan servis sonucu BELIRSIZ olarak tutulur; yeniden gönderimden önce ETTN üzerinden durum sorgulanmalıdır. Kabul/ret ve iptal ancak doğrulanmış EDM sonucundan sonra yerelde başarılı olur. Başarıyla gönderilmiş e-Fatura EDM üzerinden iptal edilmez.

Senkronizasyon gün aralıkları ve OFFSET sayfalarıyla ilerler. Kısmi sonuç/hata açıkça bildirilir ve geçmişe kaydedilir; ilerlemeyen sayfalama başarı sayılmaz. Senkronizasyon mevcut arayüz/API üzerinden başlatılır; yeni zamanlanmış görev kurulmaz. PDF yalnızca EDM'deki gerçek belge için indirilir; yerel taslak yazdırılabilir önizlemeyi kullanır.

Ayarlardaki bağlantı/firma ve kontör düğmeleri kayıtlı bağlantı bilgilerini kullanır. Kontör bulunamaması sıfır olarak gösterilmez. Firma bazlı düşük kontör eşiği varsayılan 100'dür. Yeni kurulum önce TEST hesabıyla doğrulanmalıdır; LIVE ortamda işlem kullanıcı yetkisi ve açık iş akışıyla yapılmalıdır.

## Otomatik testler

Proje kökünde:

```bash
/opt/lampp/bin/php vendor/bin/phpunit --do-not-cache-result tests/Unit/EInvoiceWorkflowTest.php tests/Unit/EInvoicePersistenceTest.php
/opt/lampp/bin/php tests/Integration/efatura-mysql.php
node tests/Integration/efatura-browser.cjs
```

Birim testleri mock SOAP ve SQLite kullanır; EDM bağlantısı kurmaz. MySQL testi yalnızca rastgele `ersan_efatura_test_*` veritabanı oluşturur ve sonunda kaldırır; sunucuda CREATE/DROP DATABASE izni gerekir. Varsayılan bağlantı yerel XAMPP soketidir. Başka sunucu için `EFATURA_TEST_SERVER_DSN` (dbname olmadan), `EFATURA_TEST_USER`, `EFATURA_TEST_PASSWORD` kullanılabilir. Test 8 eşzamanlı işçiyle 120 numara, yıl değişimi, sayaç uzlaştırması, kilit ve migration tekrarını doğrular.

Tarayıcı testi Playwright ve Chromium gerektirir. Gerçek PHP formunu yerel fixture üzerinden açar; Select2, kaçışlama, sıfır KDV, döviz, CSRF, dinamik satırlar, PDF indirme ve özet kartı tercihini doğrular. SOAP testleri iş hatası/timeout/belirsiz sonuç, tek/çok kayıt, 205 aynı zaman damgalı kayıt, uzun tarih aralığı, kısmi hata ve tekrar işlemlerini kapsar.

Canlıya geçişte TEST ortamında gerçek firma/alias/seri bilgileri, PDF, ticari yanıt ve e-Arşiv rapor durumları entegratör hesabıyla ayrıca doğrulanmalıdır. Otomatik testler gerçek EDM sözleşmesinin sunucu davranışını veya gerçek GİB kabulünü kanıtlamaz.
