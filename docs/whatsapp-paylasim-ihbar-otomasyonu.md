# WhatsApp Bot ile Kaçak İhbar Otomasyonu (Uygulama Planı)

Durum: Onay bekliyor (kod yazılmadı)
Tarih: 07.08.2026

## 1. Amaç

WhatsApp gruplarına düşen kaçak bildirimlerini personel **bot numarasına iletir**, bot mesajı
okur, ayrıştırır ve onay kuyruğuna düşürür. Operatör panelden onaylayınca ihbar oluşur ve
en yakın personele otomatik atanır.

Karar verilen davranış:
- Gelen içerik doğrudan ihbar açmaz, önce **onay kuyruğuna** düşer.
- Konum bilgisi yoksa bot **konum pini** ister.
- Saha cihazları karışık (Android + iPhone); ileti akışı ikisinde de aynı çalışır.

## 2. Neden "ilet" yöntemi

WhatsApp'ta metin mesajında paylaş menüsü çıkmaz, yalnızca **İlet (Forward)** vardır. İletilen
mesaj bot numarasına birebir mesaj olarak ulaşır — bu, resmi Meta Cloud API'nin okuyabildiği
tek mesaj türüdür.

| Yol | Grup okur | Durum |
|---|---|---|
| Cloud API + grup okuma | Hayır | Meta grup mesajlarına erişim vermiyor |
| **Cloud API + ileti (seçilen yol)** | Gerekmiyor | Personel mesajı bota iletir, resmi ve ban riski yok |
| Baileys / whatsapp-web.js | Evet | Numara ban riski, sunucuda sürekli Node süreci + QR bakımı |
| PWA paylaşım hedefi | — | Metin mesajlarında paylaş menüsü çıkmadığı için elendi |

Personelin tek yeni alışkanlığı: ihbar mesajını (metin + foto + konum) seçip bot numarasına iletmek.
WhatsApp'ta çoklu seçim ile hepsi tek seferde iletilebilir.

## 3. Akış

```
WhatsApp grubu: ihbar mesajı (metin / foto / konum)
        │
        │  personel mesajları seçip "İlet" → Kaçak İhbar Botu
        ▼
api/external/whatsapp-webhook.php   (imza doğrulama, ham kayıt, hızlı 200)
        │
whatsapp_gelen_mesajlar  ──►  cron/whatsapp_isleyici.php (dakikada bir)
        │                            │
        │                            ├── medya indirme (Graph API)
        │                            ├── AI ayrıştırma (ilçe/mahalle/telefon/açıklama)
        │                            └── bot yanıtı: özet + eksik bilgi talebi
        ▼
ihbar_paylasim_kuyrugu + ihbar_paylasim_medya
        │
        │  konum yoksa bot konum pini ister
        ▼
Panel: Kaçak İhbar Kuyruğu ekranı  →  Onay (Gate)
        │
IhbarModel::create() → autoAssignNearest() → PushNotificationService
```

## 4. Ön koşullar (Meta tarafı — kod dışı)

Bunlar tamamlanmadan geliştirme test edilemez:

1. **Ayrı bir telefon numarası** — WhatsApp uygulamasında kayıtlı olmayan, sadece bota ayrılmış bir numara.
2. **Meta Business hesabı** ve iş doğrulaması (birkaç iş günü sürebilir).
3. Meta uygulaması → WhatsApp ürünü eklenip numaranın kaydedilmesi.
4. Kalıcı erişim jetonu (system user token), `phone_number_id`, `app_secret`.
5. Webhook için **geçerli sertifikalı HTTPS** adresi (mevcut alan adı uygun).

### Maliyet

Bot her zaman personelin ilettiği mesajdan sonra yanıt verdiği için 24 saatlik **servis penceresi**
içinde kalır; bu penceredeki yanıtlar ücretlendirilmez. Pencere dışına çıkan bir yanıt için onaylı
şablon gerekir ve ücretlidir. Planlanan akışta buna ihtiyaç yok. Güncel fiyatlandırma Meta tarafından
değiştirilebildiği için devreye almadan önce teyit edilmeli.

## 5. Veritabanı değişiklikleri

Kod ile birlikte ayrı SQL scriptleri verilecek, mevcut tablolara dokunulmayacak.

### 5.1 `database/migrations/2026_08_07_create_whatsapp_ihbar_tablolari.sql`

**`whatsapp_gelen_mesajlar`** — ham webhook kaydı, tekrar işlemeyi engeller

| Kolon | Tip | Açıklama |
|---|---|---|
| id | int PK | |
| firma_id | int | |
| wa_message_id | varchar(128) **UNIQUE** | Meta aynı webhook'u tekrar gönderebilir; idempotency anahtarı |
| gonderen_no | varchar(20) | E.164, baştaki + olmadan |
| gonderen_personel_id | int NULL | Eşleştirme sonucu |
| tip | enum('text','image','video','document','location','other') | |
| metin | text NULL | |
| medya_id / medya_mime | varchar NULL | Graph API medya kimliği |
| konum_lat / konum_lng | decimal NULL | |
| iletilmis_mi | tinyint | Meta `context.forwarded` bayrağı |
| ham_json | text | Hata ayıklama |
| islendi | tinyint | İşleyici cron tarafından set edilir |
| kuyruk_id | int NULL | Bağlandığı kuyruk kaydı |
| created_at | datetime | |

**`ihbar_paylasim_kuyrugu`** — kanal bağımsız onay kuyruğu

| Kolon | Tip | Açıklama |
|---|---|---|
| id | int PK | |
| firma_id | int | |
| kanal | enum('whatsapp','pwa','telegram') | İleride başka adaptörler aynı kuyruğa yazar |
| gonderen_personel_id | int NULL | İleten personel |
| gonderen_no | varchar(20) | |
| ham_metin | text | Birleştirilmiş mesaj gövdesi |
| konum_lat / konum_lng / konum_dogruluk | decimal NULL | |
| ai_durum | enum('bekliyor','tamam','hata') | |
| ai_ilce / ai_mahalle / ai_telefon / ai_aciklama | varchar/text NULL | |
| ai_guven | decimal(3,2) NULL | Düşük skor panelde işaretlenir |
| ai_ham_yanit | text NULL | |
| durum | enum('toplaniyor','yeni','incelemede','onaylandi','reddedildi','mukerrer') | `toplaniyor` = mesajlar hâlâ geliyor |
| ihbar_id | int NULL | Onayda oluşan ihbar |
| degerlendiren_user_id / degerlendirme_tarihi / red_sebebi | | Onay izi |
| icerik_hash | char(64) | Mükerrer tespiti |
| son_mesaj_tarihi | datetime | Oturum penceresi hesabı |
| created_at / updated_at / silinme_tarihi | datetime | Soft delete |

İndeksler: `idx_durum`, `idx_firma`, `idx_icerik_hash`, `idx_gonderen_no`, `idx_son_mesaj`.

**`ihbar_paylasim_medya`** — `id`, `kuyruk_id` (FK cascade), `tur` enum('foto','video'),
`dosya_yolu`, `kucuk_yol`, `orijinal_ad`, `boyut`, `created_at`.
`kucuk_yol` mevcut `2026_08_05_add_kucuk_yol_to_foto_tablolari.sql` deseniyle uyumlu.

**`whatsapp_yetkili_numaralar`** — `id`, `firma_id`, `telefon`, `personel_id` NULL,
`user_id` NULL, `aciklama`, `aktif_mi`, `created_at`. `personel.cep_telefonu` ile eşleşmeyen
ya da farklı hattan yazan personel için elle tanımlama.

### 5.2 `sql/add_ihbar_kuyruk_permission_menu_ve_rol.sql`

`sql/add_ihbar_yonetimi_permission_menu_ve_rol.sql` birebir örnek alınarak, hepsi
`WHERE NOT EXISTS` ile idempotent:
- `permissions` → `auth_name = 'ihbar/kuyruk'`, grup "İş Takip Yönetim"
- `menus` → parent_id 5, link `ihbar/kuyruk`, ikon `inbox`
- `user_role_permissions` → "Kaçak Kontrol Sorumlusu", "Süper Admin", "Firma Sahibi"

## 6. Webhook uç noktası

`api/external/whatsapp-webhook.php` — mevcut `api/external/kacak-kontrol.php` deseniyle aynı
iskelet (session yok, `.env` yapılandırması, `SystemLogModel` ile loglama).

**GET** — Meta doğrulaması: `hub.mode`, `hub.verify_token`, `hub.challenge`.
`hash_equals()` ile `.env`'deki `WHATSAPP_VERIFY_TOKEN` karşılaştırılır, eşleşirse challenge döner.

**POST** — mesaj teslimi:
1. Ham gövde okunur, `X-Hub-Signature-256` başlığı `hash_hmac('sha256', $raw, $appSecret)` ile
   `hash_equals()` üzerinden doğrulanır. Eşleşmezse 403 ve log.
2. Gönderen numarası beyaz listede mi (`personel.cep_telefonu` normalize + `whatsapp_yetkili_numaralar`)
   kontrol edilir. Değilse kayıt açılmaz, sadece loglanır.
3. Her mesaj `whatsapp_gelen_mesajlar` tablosuna yazılır (`wa_message_id` UNIQUE sayesinde
   tekrarlanan teslimatlar sessizce yutulur).
4. **Hemen 200 döner.** Medya indirme ve AI ayrıştırma burada yapılmaz.

Meta, 200 alamazsa webhook'u tekrar gönderir. Ağır işi uç noktada yapmak tekrar teslimata ve
mükerrer kayda yol açar; bu yüzden işlem cron'a devredilir.

## 7. Mesaj gruplama (oturum mantığı)

Bir ihbar genelde 2-4 ayrı mesaj olarak iletilir: fotoğraf, açıklama metni, konum pini.
Bunların tek kuyruk kaydında toplanması gerekir.

Kural: aynı numaradan gelen mesaj, o numaranın `durum = 'toplaniyor'` olan açık kaydına eklenir.
Açık kayıt yoksa yeni kayıt açılır.

Kayıt şu durumlarda kapanır (`durum = 'yeni'`, onay kuyruğuna düşer):
- Personel **TAMAM** / **BİTTİ** yazarsa
- Son mesajın üzerinden **10 dakika** geçerse (cron kapatır)

Personel **YENİ** yazarsa açık kayıt kapatılıp yeni kayıt başlar.

### Bot yanıtları

| Durum | Yanıt |
|---|---|
| İlk mesaj | "İhbar kaydı açıldı (#123). Fotoğraf ve konum ekleyebilirsiniz. Bitince TAMAM yazın." |
| Konum yoksa (kapanışta) | "Konum pini paylaşır mısınız? Ekli/Konum → Mevcut konum." |
| Kapanış | "#123 onay kuyruğuna alındı. İlçe: X, Mahalle: Y. Onaylanınca ekibe yönlendirilecek." |
| Yetkisiz numara | Yanıt verilmez, yalnızca loglanır |

## 8. İşleyici cron

`cron/whatsapp_isleyici.php` — dakikada bir çalışır:
1. `islendi = 0` mesajları sırayla alır, kuyruk kaydına bağlar.
2. Medyayı indirir: `GET graph.facebook.com/v21.0/{media_id}` → dönen URL'ye Bearer jetonu ile
   ikinci istek. **Medya URL'leri ~5 dakika geçerlidir**, gecikme olursa medya kaybolur; bu yüzden
   cron sıklığı 1 dakikadan seyrek olmamalı.
3. Dosyalar `ImageUploadService` / `VideoUploadService` üzerinden doğrulanıp
   `uploads/ihbar/YYYY/MM` altına yazılır.
4. Penceresi dolan kayıtları kapatır, AI ayrıştırmayı çalıştırır, bot özet yanıtını gönderir.
5. Tüm adımlar `SystemLogModel` ile loglanır; hata akışı durdurmaz, kayıt `ai_durum = 'hata'` kalır.

## 9. AI ayrıştırma

`App/Service/KacakTutanakAnalizService.php` deseni izlenecek:
- Anahtar `SettingsModel::getAllSettingsAsKeyValue()` ile firma bazlı okunur, kod içinde
  hardcoded fallback olmaz.
- Model yanıtı JSON: `ilce`, `mahalle`, `telefon`, `aciklama`, `guven`.
- İlçe/mahalle serbest metin olarak kabul edilmez, mevcut ihbar formundaki listeyle eşleştirilir;
  eşleşmezse boş bırakılıp panelde uyarı gösterilir.
- Ayrıştırma başarısızsa kayıt kaybolmaz, operatör alanları elle doldurur.

Maliyet: kuyruk kaydı başına 1 çağrı. Sadece metinse ucuz model yeterli; fotoğraftaki adres/tutanak
okunacaksa görsel destekli model gerekir.

## 10. Onay ve ihbara dönüştürme

Panelde onay verildiğinde tek transaction içinde:
1. `IhbarModel::create()` — ilçe, mahalle, telefon, açıklama, konum, `olusturan_user_id`
2. Kuyruktaki medya `IhbarModel::addFotograf()` / `addVideo()` ile ihbara bağlanır
3. `IhbarModel::addTarihce()` — "İhbar, WhatsApp botundan onaylanarak oluşturuldu"
4. Konum varsa `IhbarModel::autoAssignNearest()` → en yakın müsait personele atama
5. `PushNotificationService::sendToPersonel()` ile bildirim
6. Kuyruk kaydı `durum = 'onaylandi'`, `ihbar_id` yazılır
7. Bota bilgi yanıtı gönderilir (servis penceresi açıksa)

Red durumunda `red_sebebi` zorunlu, kayıt silinmez (soft delete korunur).

## 11. Mükerrer kontrolü

Aynı ihbar birden fazla personel tarafından iletilebilir. `icerik_hash` (normalize metin + medya
dosya hash'i) ile son 24 saat içinde eşleşme aranır; varsa kayıt `durum = 'mukerrer'` açılır ve
panelde asıl kayda bağlanır. `KacakKontrolModel::findDuplicateRecord()` mantığıyla aynı çizgide.

## 12. Dosya değişiklikleri

### Yeni dosyalar

| Dosya | İçerik |
|---|---|
| `api/external/whatsapp-webhook.php` | GET doğrulama, POST imza kontrolü, ham kayıt, hızlı 200 |
| `App/Service/WhatsAppCloudService.php` | `sendText()`, `downloadMedia()`, `verifySignature()`, numara normalizasyonu |
| `App/Model/WhatsappMesajModel.php` | Ham mesaj CRUD, işlenmemiş kayıt sorgusu, yetkili numara eşleştirme |
| `App/Model/IhbarPaylasimKuyruguModel.php` | Kuyruk CRUD, oturum penceresi, mükerrer, onay/red |
| `App/Service/IhbarPaylasimAnalizService.php` | AI ayrıştırma |
| `cron/whatsapp_isleyici.php` | Medya indirme, kuyruk kapatma, AI, bot yanıtı |
| `views/ihbar/kuyruk.php` | Panelde onay kuyruğu listesi ve detay modalı |
| `database/migrations/2026_08_07_create_whatsapp_ihbar_tablolari.sql` | Tablolar |
| `sql/add_ihbar_kuyruk_permission_menu_ve_rol.sql` | Yetki, menü, rol |

### Değişecek dosyalar

| Dosya | Değişiklik |
|---|---|
| `views/ihbar/api.php` | `kuyrukListe`, `kuyrukDetay`, `kuyrukOnayla`, `kuyrukReddet` aksiyonları (`Gate::authorizeOrDie('ihbar/kuyruk')`) |
| `views/ihbar/list.php` | Kuyrukta bekleyen sayısını gösteren rozet/sekme |
| `.env` | Aşağıdaki anahtarlar |

### `.env` anahtarları

```
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_FIRMA_ID=
```

`KACAK_API_*` deseniyle aynı; kod içinde hardcoded fallback olmayacak.

## 13. Güvenlik ve loglama

- Webhook imzası `X-Hub-Signature-256` ile HMAC-SHA256 doğrulanır, `hash_equals()` kullanılır.
- Yalnızca beyaz listedeki numaralar kayıt açabilir; bilinmeyen numara yanıt almaz, loglanır.
- IP ya da numara bazlı oran sınırı — `SystemLogModel::countRecentFailedApiAttempts()` mevcut deseni kullanılır.
- Panel aksiyonları `Gate::authorizeOrDie('ihbar/kuyruk')` ile korunur.
- Tüm sorgular `prepare()`/`execute()`, HTML çıktıları `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Erişim jetonu loglara yazılmaz; `ham_json` içinde jeton bulunmaz.
- Exception'lar kullanıcıya yansıtılmaz, `error_log()` ile kaydedilir.

## 14. Uygulama sırası

1. SQL scriptleri (tablolar + yetki/menü/rol)
2. `WhatsAppCloudService` + `WhatsappMesajModel` + webhook uç noktası — Meta test numarasıyla
   mesaj alındığının doğrulanması
3. `IhbarPaylasimKuyruguModel` + oturum mantığı + işleyici cron + bot yanıtları
4. Panel kuyruk ekranı, onay/red, ihbara dönüştürme — **bu adımın sonunda sistem AI olmadan çalışır**
5. AI ayrıştırma servisi
6. Mükerrer tespiti, rozet/sayaç, raporlama iyileştirmeleri

## 15. Test planı

- Webhook GET doğrulaması ve yanlış `verify_token` ile reddedilme
- Geçersiz imzalı POST'un 403 alması
- Aynı `wa_message_id` iki kez gelince tek kayıt oluşması
- Tek metin mesajının iletilmesi → kuyruk kaydı
- Foto + metin + konumun ayrı ayrı iletilmesi → tek kuyruk kaydında toplanması
- TAMAM komutuyla kapanış, 10 dakika sessizlikle otomatik kapanış
- YENİ komutuyla ikinci kaydın açılması
- Konumsuz kayıtta botun konum pini istemesi ve gelen pinin kayda işlenmesi
- Yetkisiz numaradan mesaj → kayıt açılmaması, yanıt gitmemesi
- Onay → ihbar oluşumu, en yakın personele atama, push bildirimi
- Red → ihbar oluşmaması, red sebebinin kaydedilmesi
- Yetkisiz kullanıcının kuyruk ekranına ve API aksiyonlarına erişememesi

## 16. Riskler ve açık noktalar

| Konu | Durum |
|---|---|
| **İletilen mesajda kaynak kaybı** | WhatsApp ileti yaparken orijinal göndereni ve saatini taşımaz. Vatandaşın numarası yalnızca metin içinde geçiyorsa yakalanabilir; kuyrukta "ileten personel" kayıtlıdır |
| **Meta onay süresi** | İş doğrulaması ve numara kaydı birkaç gün sürebilir; geliştirme test numarasıyla ilerleyebilir |
| **Medya URL ömrü** | İndirme bağlantısı ~5 dakika geçerli. İşleyici cron duraklarsa medya kalıcı olarak kaybolur; cron izleme ve hata alarmı gerekir |
| **Numara gereksinimi** | Bot numarası normal WhatsApp uygulamasında kullanılamaz, ayrı hat gerekir |
| **WebP medya** | WhatsApp çıkartmaları WebP gelir; sunucuda GD WebP desteği yok, `ImageUploadService` reddeder. Fotoğraflar JPEG geldiği için normal akış etkilenmez |
| **Servis penceresi** | Bot yanıtı 24 saatlik pencere dışına taşarsa şablon gerekir. Onay bildirimi geç kalırsa gönderilmez, panelde görünür |
| **Kişisel veri** | İletilen mesajlarda vatandaş telefonu bulunabilir; kuyruk ve ham mesaj kayıtları `cron/data_retention.php` kapsamına alınmalı |
| **Grup okuma beklentisi** | Bu çözüm grupları okumaz. Tam otomatik grup okuma isteniyorsa tek yol gayri resmi istemci ve numara ban riskidir |
