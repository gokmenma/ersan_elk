# Mail Bildirim Sistemi Kullanım Kılavuzu

## Genel Bakış
Users tablosuna eklenen mail bildirim ayarları sayesinde, personel talep oluşturduğunda hangi kullanıcılara mail gönderileceğini kontrol edebilirsiniz.

## Veritabanı Değişiklikleri

Aşağıdaki SQL sorgusunu çalıştırarak users tablosuna gerekli alanları ekleyin:

```sql
ALTER TABLE `users` 
ADD COLUMN `mail_avans_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Avans talebi bildirimlerini al',
ADD COLUMN `mail_izin_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'İzin talebi bildirimlerini al',
ADD COLUMN `mail_genel_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Genel talep bildirimlerini al',
ADD COLUMN `mail_ariza_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Arıza talebi bildirimlerini al';
```

## Arayüz Değişiklikleri

### Kullanıcı Modal
`views/kullanici/modal/user-modal.php` dosyasında kullanıcı düzenleme formuna mail bildirim ayarları eklendi:
- Avans Talebi Bildirimleri
- İzin Talebi Bildirimleri
- Genel Talep Bildirimleri
- Arıza Talebi Bildirimleri

### API Güncellemeleri
`views/kullanici/api.php` dosyası güncellenerek checkbox değerlerini doğru şekilde işler.

## UserModel Metodları

### 1. getMailBildirimKullanicilari($talepTuru)
Belirli bir talep türü için mail bildirimi açık olan tüm kullanıcıları getirir.

**Parametreler:**
- `$talepTuru`: 'avans', 'izin', 'genel', 'ariza'

**Dönüş:**
- Array of user objects

**Örnek Kullanım:**
```php
$userModel = new UserModel();
$kullanicilar = $userModel->getMailBildirimKullanicilari('avans');

foreach($kullanicilar as $kullanici) {
    // Mail gönder
    echo $kullanici->email_adresi;
}
```

### 2. checkMailBildirimi($userId, $talepTuru)
Belirli bir kullanıcının belirli bir talep türü için mail alıp almadığını kontrol eder.

**Parametreler:**
- `$userId`: Kullanıcı ID'si (integer)
- `$talepTuru`: 'avans', 'izin', 'genel', 'ariza'

**Dönüş:**
- Boolean (true/false)

**Örnek Kullanım:**
```php
$userModel = new UserModel();
$userId = 1;

if($userModel->checkMailBildirimi($userId, 'avans')) {
    // Bu kullanıcıya avans talebi maili gönder
}
```

## Talep API'lerinde Kullanım Örnekleri

### Avans Talebi (views/personel-pwa/api.php veya ilgili api dosyası)

```php
use App\Model\UserModel;
use App\Service\MailGonderService;

// Avans talebi oluşturulduğunda
if ($_POST["action"] == "createAvansTalebi") {
    // ... talep kaydetme işlemleri ...
    
    // Mail gönderme
    $userModel = new UserModel();
    $mailService = new MailGonderService();
    
    // Avans talebi bildirimi açık olan kullanıcıları getir
    $bildirimKullanicilari = $userModel->getMailBildirimKullanicilari('avans');
    
    foreach($bildirimKullanicilari as $kullanici) {
        // Mail şablonunu hazırla
        $mailIcerik = "Yeni bir avans talebi oluşturuldu...";
        
        // Mail gönder
        $mailService->sendMail(
            $kullanici->email_adresi,
            $kullanici->adi_soyadi,
            "Yeni Avans Talebi",
            $mailIcerik
        );
    }
}
```

### İzin Talebi (views/personel-pwa/api.php)

```php
if ($_POST["action"] == "createIzinTalebi") {
    // ... talep kaydetme işlemleri ...
    
    // Mail gönderme
    $userModel = new UserModel();
    $mailService = new MailGonderService();
    
    // İzin talebi bildirimi açık olan kullanıcıları getir
    $bildirimKullanicilari = $userModel->getMailBildirimKullanicilari('izin');
    
    foreach($bildirimKullanicilari as $kullanici) {
        // Mail şablonunu kullan (örn: views/mail-template/izin_onay.php)
        ob_start();
        include __DIR__ . '/../mail-template/izin_onay.php';
        $mailIcerik = ob_get_clean();
        
        // Mail gönder
        $mailService->sendMail(
            $kullanici->email_adresi,
            $kullanici->adi_soyadi,
            "Yeni İzin Talebi",
            $mailIcerik
        );
    }
}
```

### Genel Talep

```php
if ($_POST["action"] == "createGenelTalep") {
    // ... talep kaydetme işlemleri ...
    
    $userModel = new UserModel();
    $bildirimKullanicilari = $userModel->getMailBildirimKullanicilari('genel');
    
    // Mail gönderme işlemleri...
}
```

### Arıza Talebi

```php
if ($_POST["action"] == "createArizaTalebi") {
    // ... talep kaydetme işlemleri ...
    
    $userModel = new UserModel();
    $bildirimKullanicilari = $userModel->getMailBildirimKullanicilari('ariza');
    
    // Mail gönderme işlemleri...
}
```

## Önemli Notlar

1. **Email Kontrolü**: `getMailBildirimKullanicilari()` metodu sadece email adresi olan kullanıcıları döndürür.

2. **Varsayılan Değer**: Tüm mail bildirim alanları varsayılan olarak 'Hayır' değerine sahiptir.

3. **Kullanıcı Ayarları**: Her kullanıcı, kullanıcı düzenleme formundan hangi talep türleri için bildirim alacağını seçebilir.

4. **Performans**: Toplu mail gönderimi için arka plan işleme (queue) kullanmayı düşünün.

## Sonraki Adımlar

1. SQL migration dosyasını çalıştırın: `database/migrations/add_mail_notification_fields_to_users.sql`
2. İlgili talep API dosyalarında mail gönderme kodlarını ekleyin
3. Mail şablonlarını kontrol edin ve gerekirse güncelleyin
4. Test kullanıcıları oluşturun ve mail bildirimlerini test edin
