# Mail Bildirim Sistemi - Kurulum ve Kullanım

## 📧 Yapılan Değişiklikler

### 1. Veritabanı Güncellemeleri

**Konum:** `database/migrations/add_mail_notification_fields_to_users.sql`

Users tablosuna aşağıdaki alanlar eklendi:
- `mail_avans_talep` - Avans talebi bildirimleri
- `mail_izin_talep` - İzin talebi bildirimleri
- `mail_genel_talep` - Genel talep bildirimleri
- `mail_ariza_talep` - Arıza talebi bildirimleri

**Kullanım:**
```bash
# XAMPP MySQL'e giriş yapın ve migration dosyasını çalıştırın
```

### 2. Kullanıcı Modal Güncellemesi

**Konum:** `views/kullanici/modal/user-modal.php`

Kullanıcı düzenleme formuna "Mail Bildirim Ayarları" bölümü eklendi. Her talep türü için ayrı checkbox ile kullanıcı hangi talepler için mail alacağını seçebilir.

### 3. API Güncellemeleri

#### `views/kullanici/api.php`
- Kullanıcı kaydetme işleminde mail bildirim checkbox'larını işleme kodu eklendi
- Checkbox işaretliyse 'Evet', değilse 'Hayır' değeri kaydediliyor

#### `views/personel-pwa/api.php`
Aşağıdaki talep oluşturma case'lerine mail gönderme özelliği eklendi:

**a) createAvansTalebi**
- Avans talebi oluşturulduğunda `mail_avans_talep` = 'Evet' olan kullanıcılara mail gönderilir
- Mail içeriği: Personel adı, tutar, ödeme şekli, açıklama ve tarih

**b) createTalepBildirimi**
- Talep bildirimi oluşturulduğunda kategoriye göre mail gönderilir:
  - Kategori 'ariza' ise → `mail_ariza_talep` = 'Evet' olan kullanıcılara
  - Diğer kategoriler → `mail_genel_talep` = 'Evet' olan kullanıcılara
- Mail içeriği: Referans no, personel adı, kategori, konum, öncelik, açıklama ve tarih

**c) createIzinTalebi** (Önceden varolan, güncellenmedi)
- İzin talebi için mail gönderimi zaten mevcut
- Gelecekte `mail_izin_talep` alanı ile entegre edilebilir

### 4. Model Güncellemesi

**Konum:** `App/Model/UserModel.php`

İki yeni yardımcı metod eklendi:

```php
/**
 * Belirli bir talep türü için mail bildirimi açık olan kullanıcıları getirir
 */
public function getMailBildirimKullanicilari(string $talepTuru): array

/**
 * Belirli bir kullanıcının belirli bir talep türü için mail alıp almadığını kontrol eder
 */
public function checkMailBildirimi(int $userId, string $talepTuru): bool
```

Desteklenen talep türleri: `'avans'`, `'izin'`, `'genel'`, `'ariza'`

## 🚀 Kurulum Adımları

### Adım 1: SQL Migration'ı Çalıştırın

1. XAMPP Control Panel'den MySQL'i başlatın
2. phpMyAdmin'e gidin (http://localhost/phpmyadmin)
3. Proje veritabanınızı seçin
4. `database/migrations/add_mail_notification_fields_to_users.sql` dosyasının içeriğini SQL sekmesine yapıştırın
5. Çalıştır butonuna tıklayın

### Adım 2: Kullanıcı Ayarlarını Yapın

1. Kullanıcı yönetimi sayfasına gidin
2. Bir kullanıcıyı düzenleyin
3. "Mail Bildirim Ayarları" bölümünde hangi talep türleri için bildirim alacağını seçin
4. Kaydedin

### Adım 3: Test Edin

1. Personel PWA uygulamasına giriş yapın
2. Bir avans talebi oluşturun
3. Mail bildirim ayarları açık olan kullanıcıların mail adreslerine bildirim gönderilmelidir

## ✨ Özellikler

### Kullanıcı Bazlı Kontrol
- Her kullanıcı hangi talep türleri için mail alacağını seçebilir
- Varsayılan olarak tüm bildirimler kapalıdır

### Otomatik Mail Gönderimi
- Talep oluşturulduğunda ilgili kullanıcılara otomatik mail gönderilir
- Mail şablonları HTML formatında ve responsive

### Hata Yönetimi
- Mail gönderme hatası olsa bile talep oluşturma işlemi başarılı olur
- Hatalar error_log ile kaydedilir

### Kategori Bazlı Filtreleme
- Arıza talepleri için `mail_ariza_talep`
- Diğer talepler (öneri, şikayet, istek) için `mail_genel_talep`
- Avans talepleri için `mail_avans_talep`
- İzin talepleri için `mail_izin_talep`

## 📝 Mail Şablonları

Mail içerikleri inline CSS ile stilize edilmiştir:
- Modern ve professional görünüm
- Responsive tasarım
- Renkli öncelik göstergeleri (arıza/genel taleplerde)
- Tablo formatında detaylar

## 🔧 Gelecek Geliştirmeler

1. Mail şablonlarını ayrı dosyalara taşıma (`views/mail-template/`)
2. İzin onayı mail sistemi ile entegrasyon
3. Toplu mail gönderimi için queue sistemi (performance optimization)
4. Mail gönderim logları için veritabanı tablosu
5. Kullanıcı arayüzünde mail geçmişini görüntüleme

## 📚 Dokümantasyon

Detaylı kullanım örnekleri için: `docs/mail-bildirim-sistemi.md`

## ⚠️ Önemli Notlar

- Mail gönderimi için `App\Service\MailGonderService` kullanılıyor
- Sadece email adresi olan kullanıcılara mail gönderilir
- Mail gönderimi asenkron değildir (future improvement needed)
- Test amaçlı kullanımlarda mail sunucu ayarlarının doğru yapılandırıldığından emin olun

## 🎯 Kullanım Senaryoları

### Senaryo 1: Muhasebe Müdürü
- `mail_avans_talep` = Evet
- Tüm avans taleplerinden haberdar olur

### Senaryo 2: İnsan Kaynakları
- `mail_izin_talep` = Evet
- Tüm izin taleplerinden haberdar olur

### Senaryo 3: Teknik Müdür
- `mail_ariza_talep` = Evet
- Tüm arıza bildirimlerinden haberdar olur

### Senaryo 4: Genel Müdür
- Tüm ayarlar = Evet
- Her türlü talepten haberdar olur

---

**Oluşturulma Tarihi:** 19 Ocak 2026
**Versiyon:** 1.0.0
