# WhatsApp Kaçak Bildirimi → PWA Paylaşım Otomasyonu (Uygulama Planı)

Durum: Onay bekliyor (kod yazılmadı)
Tarih: 07.08.2026

## 1. Amaç

WhatsApp gruplarına düşen kaçak bildirimlerinin (fotoğraf, metin, konum) personel tarafından
elle sisteme girilmesi yerine, mesajın PWA'ya paylaşılmasıyla otomatik olarak ön doldurulmuş
bir ihbar kaydına dönüşmesi.

Karar verilen davranış:
- Gelen içerik doğrudan ihbar açmaz, önce **onay kuyruğuna** düşer.
- Konum bilgisi yoksa ekran personelden **konum pini** ister.
- Saha cihazları **karışık (Android + iPhone)**, ikisi de desteklenir.

## 2. Neden WhatsApp API değil

| Yol | Grup okuyabilir | Sorun |
|---|---|---|
| Meta Cloud API (resmi) | Hayır | Grup mesajlarına erişim yok, sadece birebir mesaj |
| Baileys / whatsapp-web.js | Evet | Numara ban riski, sunucuda sürekli Node süreci + QR oturum bakımı |
| **PWA paylaşım hedefi** | — | WhatsApp'a hiç dokunmaz; personel mesajı uygulamaya paylaşır |

Paylaşım yaklaşımı seçildi: API maliyeti, ban riski ve ek sunucu süreci yok. Kuyruk tablosu
kanal bağımsız tasarlanacağı için ileride Telegram veya WhatsApp webhook adaptörü aynı
kuyruğa yazacak şekilde eklenebilir.

## 3. Akış

```
WhatsApp mesajı (foto + metin)
        │
        ├── Android: Paylaş → Ersan  ────┐
        └── iPhone:  Uygulamada "Paylaşımdan Ekle" ekranı (galeri + metin yapıştır)
                                          │
                                          ▼
                        index.php?page=ihbar-paylasim  (POST)
                                          │
                    ihbar_paylasim_kuyrugu + ihbar_paylasim_medya
                                          │
                        AI ayrıştırma (ilçe/mahalle/telefon/açıklama)
                                          │
                        Konum yoksa → personelden konum pini istenir
                                          │
                          Panel: Kaçak İhbar Kuyruğu ekranı
                                          │
                                     Onay (Gate)
                                          │
                  IhbarModel::create() → autoAssignNearest() → push
```

## 4. Cihaz desteği

| Platform | Yöntem | Not |
|---|---|---|
| Android (Chrome/Edge) | Web Share Target | PWA kurulu olmalı, HTTPS zorunlu. Paylaş menüsünde uygulama görünür |
| iOS (Safari) | Uygulama içi "Paylaşımdan Ekle" ekranı | iOS Web Share Target desteklemiyor; galeriden foto seçme + metin yapıştırma |
| iOS (opsiyonel) | Kısayollar (Shortcuts) uygulaması | Paylaş menüsüne kısayol eklenip aynı uç noktaya POST edilebilir; ikinci aşama |

Her iki yol da **aynı kuyruk tablosuna** yazar, panelde tek ekranda toplanır.

## 5. Veritabanı değişiklikleri

Kod ile birlikte iki ayrı SQL scripti verilecek, mevcut tablolara dokunulmayacak.

### 5.1 `database/migrations/2026_08_07_create_ihbar_paylasim_kuyrugu.sql`

**`ihbar_paylasim_kuyrugu`**

| Kolon | Tip | Açıklama |
|---|---|---|
| id | int PK | |
| firma_id | int | Çok firmalı yapı için |
| kanal | enum('pwa_paylasim','pwa_manuel','telegram','whatsapp') | Girdi adaptörü |
| gonderen_personel_id | int NULL | Paylaşımı yapan personel |
| ham_baslik / ham_metin / ham_link | varchar/text | Paylaşımdan gelen ham veri |
| konum_lat / konum_lng / konum_dogruluk | decimal NULL | Personelin verdiği pin |
| ai_durum | enum('bekliyor','tamam','hata') | Ayrıştırma durumu |
| ai_ilce / ai_mahalle / ai_telefon / ai_aciklama | varchar/text NULL | AI çıkarımı |
| ai_guven | decimal(3,2) NULL | Düşük skor panelde işaretlenir |
| ai_ham_yanit | text NULL | Hata ayıklama için model yanıtı |
| durum | enum('yeni','incelemede','onaylandi','reddedildi','mukerrer') | |
| ihbar_id | int NULL | Onaylanınca oluşan ihbar |
| degerlendiren_user_id / degerlendirme_tarihi / red_sebebi | | Onay izi |
| icerik_hash | char(64) | Mükerrer paylaşım tespiti |
| created_at / updated_at / silinme_tarihi | datetime | Soft delete |

İndeksler: `idx_durum`, `idx_firma`, `idx_icerik_hash`, `idx_gonderen`.

**`ihbar_paylasim_medya`**

`id`, `kuyruk_id` (FK cascade), `tur` enum('foto','video'), `dosya_yolu`, `kucuk_yol`,
`orijinal_ad`, `boyut`, `created_at`.

`kucuk_yol` kolonu mevcut `2026_08_05_add_kucuk_yol_to_foto_tablolari.sql` deseniyle uyumlu.

### 5.2 `sql/add_ihbar_kuyruk_permission_menu_ve_rol.sql`

`sql/add_ihbar_yonetimi_permission_menu_ve_rol.sql` birebir örnek alınarak:
- `permissions` → `auth_name = 'ihbar/kuyruk'`, grup "İş Takip Yönetim"
- `menus` → parent_id 5, link `ihbar/kuyruk`, ikon `inbox`
- `user_role_permissions` → "Kaçak Kontrol Sorumlusu", "Süper Admin", "Firma Sahibi"

Hepsi `WHERE NOT EXISTS` ile idempotent yazılacak.

## 6. Dosya değişiklikleri

### Yeni dosyalar

| Dosya | İçerik |
|---|---|
| `App/Model/IhbarPaylasimKuyruguModel.php` | Kuyruk CRUD, medya ekleme, mükerrer kontrolü, onay/red, listeleme + sayım |
| `App/Service/IhbarPaylasimAnalizService.php` | Ham metin/görselden ilçe, mahalle, telefon, açıklama çıkarımı |
| `views/personel-pwa/pages/ihbar-paylasim.php` | Paylaşım alıcı + konum pini ekranı (Android POST hedefi, iOS manuel giriş) |
| `views/ihbar/kuyruk.php` | Panelde onay kuyruğu listesi ve detay modalı |
| `database/migrations/2026_08_07_create_ihbar_paylasim_kuyrugu.sql` | Tablolar |
| `sql/add_ihbar_kuyruk_permission_menu_ve_rol.sql` | Yetki, menü, rol |

### Değişecek dosyalar

| Dosya | Değişiklik |
|---|---|
| `views/personel-pwa/manifest.json` | `share_target` bloğu eklenecek (POST, multipart/form-data, `files` → image/video) |
| `views/personel-pwa/index.php:150` | `$allowed_pages` listesine `ihbar-paylasim` eklenecek; sayfa POST ile de açılabilmeli |
| `views/personel-pwa/api.php` | `ihbarPaylasimKaydet`, `ihbarPaylasimKonum`, `ihbarPaylasimListe` aksiyonları |
| `views/ihbar/api.php` | `kuyrukListe`, `kuyrukDetay`, `kuyrukOnayla`, `kuyrukReddet` aksiyonları (`Gate::authorizeOrDie('ihbar/kuyruk')`) |
| `views/ihbar/list.php` | Kuyrukta bekleyen sayısını gösteren rozet/sekme |
| `views/personel-pwa/sw.js` | Paylaşım POST'unun önbelleğe girmemesi zaten sağlanıyor (satır 144-148); yalnızca `ihbar-paylasim` çevrimdışı sayfa listesine eklenecek |

`views/personel-pwa/index.php:150` kontrolü önemli: `$page` beyaz listede olmadığı sürece
`ana-sayfa`ya düşüyor, paylaşım POST'u bu yüzden veri kaybeder.

### manifest.json'a eklenecek blok

```json
"share_target": {
  "action": "index.php?page=ihbar-paylasim",
  "method": "POST",
  "enctype": "multipart/form-data",
  "params": {
    "title": "paylasim_baslik",
    "text": "paylasim_metin",
    "url": "paylasim_link",
    "files": [
      { "name": "paylasim_medya", "accept": ["image/*", "video/*"] }
    ]
  }
}
```

## 7. AI ayrıştırma

`App/Service/KacakTutanakAnalizService.php` deseni birebir izlenecek:
- Anahtar `SettingsModel::getAllSettingsAsKeyValue()` üzerinden firma bazlı okunacak, kod içinde
  hardcoded fallback olmayacak.
- Model yanıtı JSON olarak istenecek: `ilce`, `mahalle`, `telefon`, `aciklama`, `guven`.
- Ayrıştırma başarısızsa kayıt kaybolmaz; `ai_durum = 'hata'` yazılır, operatör alanları elle doldurur.
- İlçe/mahalle çıktısı serbest metin olarak değil, mevcut ihbar formundaki listeyle eşleştirilerek
  doğrulanacak; eşleşmezse boş bırakılıp panelde uyarı gösterilecek.

Maliyet notu: paylaşım başına 1 çağrı. Sadece metin varsa ucuz model yeterli; fotoğraftaki
adres/tutanak okunacaksa görsel destekli model gerekir.

## 8. Onay ve ihbara dönüştürme

Panelde onay verildiğinde tek transaction içinde:
1. `IhbarModel::create()` — ilçe, mahalle, telefon, açıklama, konum, `olusturan_user_id`
2. Kuyruktaki medya `uploads/ihbar/YYYY/MM` altına taşınıp `IhbarModel::addFotograf()` / `addVideo()`
3. `IhbarModel::addTarihce()` — "İhbar, WhatsApp paylaşımından onaylanarak oluşturuldu"
4. Konum varsa `IhbarModel::autoAssignNearest()` → en yakın müsait personele atama
5. `PushNotificationService::sendToPersonel()` ile bildirim
6. Kuyruk kaydı `durum = 'onaylandi'`, `ihbar_id` yazılır

Red durumunda `red_sebebi` zorunlu, kayıt silinmez (soft delete mantığı korunur).

## 9. Mükerrer kontrolü

Aynı bildirim birden fazla personel tarafından paylaşılabilir. `icerik_hash` alanı
(normalize edilmiş metin + medya dosya hash'i) ile son 24 saat içinde eşleşme aranır;
eşleşme varsa kayıt `durum = 'mukerrer'` olarak açılır ve panelde asıl kayda bağlanır.
`KacakKontrolModel::findDuplicateRecord()` mantığıyla aynı çizgide çalışır.

## 10. Güvenlik ve loglama

- Paylaşım ekranı `$_SESSION['personel_id']` kontrolünden geçer; oturumsuz POST reddedilir.
- Panel aksiyonları `Gate::authorizeOrDie('ihbar/kuyruk')` ile korunur.
- Tüm sorgular `prepare()`/`execute()`, HTML çıktıları `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Dosya doğrulaması `ImageUploadService` / `VideoUploadService` üzerinden; MIME whitelist zaten mevcut.
- Kuyruğa giriş, onay ve red işlemleri `SystemLogModel::logAction()` ile loglanır.
- Exception'lar kullanıcıya yansıtılmaz, `error_log()` ile kaydedilir.
- Personel başına dakikalık paylaşım limiti (spam koruması) uygulanır.

## 11. Uygulama sırası

1. SQL scriptleri (tablolar + yetki/menü/rol)
2. `IhbarPaylasimKuyruguModel`
3. PWA paylaşım ekranı + `manifest.json` + `index.php` beyaz liste — uçtan uca kayıt çalışsın
4. Panel kuyruk ekranı + onay/red + ihbara dönüştürme
5. AI ayrıştırma servisi (bu adıma kadar alanlar elle doldurulabilir durumda)
6. iOS manuel giriş ekranının ayrıştırılması ve rozet/sayaç iyileştirmeleri

3. adımın sonunda sistem AI olmadan da kullanılabilir durumda olur.

## 12. Test planı

- Android'de PWA kurulu iken WhatsApp'tan foto + metin paylaşımı → kuyruk kaydı ve medya oluşuyor mu
- Metin paylaşımı (medyasız), sadece foto paylaşımı, çoklu foto paylaşımı
- Konumsuz paylaşımda pin isteme akışı ve pin sonrası kaydın tamamlanması
- iPhone'da manuel ekrandan aynı kaydın oluşması
- Aynı içeriğin iki personel tarafından paylaşılması → mükerrer işaretlemesi
- Onay → ihbar oluşumu, en yakın personele atama, push bildirimi
- Red → ihbar oluşmaması, red sebebinin kaydedilmesi
- Yetkisiz kullanıcının kuyruk ekranına ve API aksiyonlarına erişememesi
- Çevrimdışı paylaşım denemesi (beklenen davranış aşağıda)

## 13. Riskler ve açık noktalar

| Konu | Durum |
|---|---|
| **WebP medya** | WhatsApp çıkartmaları WebP gelir; sunucuda GD WebP desteği yok, `ImageUploadService` bu dosyaları reddeder. Paylaşım ekranında anlaşılır hata gösterilecek. Fotoğraflar JPEG geldiği için normal akış etkilenmez |
| **Çevrimdışı paylaşım** | Paylaşım POST'u ağ yokken kaybolur. `assets/js/pwa-offline-queue.js` ile kuyruklama ikinci aşamada değerlendirilebilir |
| **PWA kurulumu** | Share Target yalnızca ana ekrana eklenmiş PWA'da görünür; kurulu olmayan personelde menüde çıkmaz. Sahaya kısa bir kurulum yönergesi gerekir |
| **iOS kapsamı** | iPhone'da akış iki dokunuş yerine galeriden seçme ile ilerler; Shortcuts entegrasyonu istenirse ayrıca planlanır |
| **AI maliyeti/isabet** | Serbest metin ihbarlarında ilçe/mahalle her zaman çıkmayabilir; operatör onayı bu riski karşılıyor |
| **Kişisel veri** | Paylaşılan mesajlarda vatandaş telefon numarası bulunabilir; kuyruk kayıtları için saklama süresi `cron/data_retention.php` kapsamına alınmalı |
