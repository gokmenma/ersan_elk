# System Patterns

## Mimari Kararlar ve Kod Düzeni

### 1. Mimari Katmanlar
- **Model Katmanı**: Tüm veritabanı işlemleri `App\Models` altındaki sınıflardan geçer. Doğrudan controller/view içinde SQL yazılmaz.
- **Helper Katmanı**: Form üretimi (`App\Helper\Form`), Güvenlik (`App\Helper\Security`), Yetkilendirme (`Gate`) ve yardımcı fonksiyonlar.
- **View / Presentation**: Masaüstü için PHP layout/view şablonları; Mobil için `mobile-spa` (SPA mimarisi).

### 2. Veritabanı & Sorgu Desenleri
- **PDO & Parametrik Binding**: String interpolasyon kesinlikle yasaktır, her zaman `prepare()` ve `execute()` kullanılır.
- **Soft Delete**: Silme işlemlerinde `deleted_at = NOW()`, `is_active = 0` mantığı işletilir.
- **SQL Değişiklikleri**: Kod içinden DDL çalıştırılmaz; her şema değişikliği ayrı SQL scripti olarak saklanır ve uygulanır.

### 3. Güvenlik ve Gizlilik (ISO 27001)
- **Fiziksel Dosya Şifreleme**: Yüklenen tüm belgeler diske yazılmadan önce AES-256-GCM (`openssl_encrypt`) ile şifrelenir. Asla ham dosya saklanmaz. İndirme anında bellek üzerinde çözülerek sunulur.
- **Hassas Veri / ID Maskeleme**: Hassas alanlar ve URL/parametre ID'leri `Security::encrypt()` ile şifrelenir.
- **XSS & Session Koruması**: Tüm HTML çıktıları `htmlspecialchars` ile filtrelenir; başarılı girişte `session_regenerate_id(true)` çağrılır.
- **Yetki Kontrolü**: Tüm sayfa ve API aksiyonları `Gate` veya `Gate::isSuperAdmin()` ile kontrol edilir.

### 4. İşlem Loglama
- Tüm kritik veri ve dosya hareketleri (ekleme, düzenleme, silme, indirme) `ActivityLogModel` veya ilgili log modelleri üzerinden veritabanına kaydedilir.
