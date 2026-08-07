# Kaçak İşlemleri — "Sicil Oluşmayanlar" Modülü Uygulama Planı

**Durum:** Uygulandı (Adım 1–6 tamam) — 07.08.2026
**SQL scripti veritabanında çalıştırıldı.**

| Adım | Dosya | Durum |
|---|---|---|
| 1. SQL | [`sql/kacak_sicil_olusmayanlar.sql`](../sql/kacak_sicil_olusmayanlar.sql) | ✅ çalıştırıldı |
| 2. Model | [`App/Model/KacakSicilEksikModel.php`](../App/Model/KacakSicilEksikModel.php) | ✅ |
| 3. Masaüstü API | [`views/kacak/api.php`](../views/kacak/api.php) | ✅ 7 action |
| 4. Masaüstü sekme | [`views/kacak/list.php`](../views/kacak/list.php) | ✅ 3 modal |
| 5. Bildirimler | `views/kacak/api.php` | ✅ push + bildirimler tablosu |
| 6. PWA | [`views/personel-pwa/pages/kacak.php`](../views/personel-pwa/pages/kacak.php) | ✅ |

---

## 1. İş Problemi

Firma, sahada kaçak/abonesiz tutanağı düzenliyor ve kuruma (KASKİ) iletiyor. Kurum bu
tutanakları ceza kesmek için kendi sistemine işliyor. Mükellefin **TC kimlik numarası
veya doğum tarihi hatalıysa sicil oluşmuyor** ve tutanak işlenemiyor.

Bugün bu geri dönüş sistemde hiçbir yerde kayıtlı değil; telefonla/WhatsApp'tan
konuşuluyor. Sonuç:

- Hangi tutanağın neden takıldığı takip edilemiyor.
- Düzeltme talebi tutanağı tutan ekibe ulaşmıyor.
- Ekip doğru bilgiyi bulduğunda kuruma nasıl geri döneceği belirsiz.
- Aynı tutanak birden fazla kez geri gelirse geçmiş kayboluyor.

### Cevaplanması gereken sorular

| Soru | Cevap |
|---|---|
| Kurum geri gönderdi, kayıt nerede duracak? | Yeni `kacak_sicil_eksik` tablosu — her gidiş-geliş ayrı satır |
| Kullanıcıya nasıl düşecek? | Tutanağı tutan ekibe PWA push + bildirim; ofis için masaüstü sekmesi |
| Bilgi güncellendi, nereye yazılacak? | Hem eksik kaydına (geçmiş) hem `kacak_kontrol`'e (kaynak veri) |
| Tutanağın **durumu** güncellenmeli mi? | **Hayır.** Ayrı `sicil_durumu` alanı — gerekçe §2.1 |
| Kurum düzeltmeyi tekrar nasıl görecek? | Aynı sekmenin "Yanıtlandı" alt sekmesi + rozet sayacı |

---

## 2. Veri Modeli

### 2.1 `kacak_kontrol.durum` alanına DOKUNULMAYACAK

Bu kararın gerekçesi kritik:

- `durum` (`aktif`/`iptal`) ve `onay_durumu` alanları **hakediş ve prim hesabına**
  giriyor — bkz. [`KacakKontrolModel::hakedisKosulu()`](../App/Model/KacakKontrolModel.php#L63-L64)
  ve [`getOzet()`](../App/Model/KacakKontrolModel.php#L867-L873).
- Sicilin oluşmaması tutanağı geçersiz kılmıyor. Ekip işi yapmış, tutanağı tutmuş,
  hakedişi hak etmiştir. Sadece kurum tarafındaki idari işlem takılmıştır.
- Bu iki kavram aynı kolona sıkıştırılırsa prim hesabı bozulur ve geri alınması zor
  bir veri hatası oluşur.

Bunun yerine **ayrı bir eksen** açılıyor: `sicil_durumu`.

### 2.2 `kacak_kontrol` — yeni kolonlar

```sql
ALTER TABLE `kacak_kontrol`
  ADD COLUMN IF NOT EXISTS `abone_tc` VARCHAR(11) DEFAULT NULL AFTER `abone_adi`,
  ADD COLUMN IF NOT EXISTS `abone_dogum_tarihi` DATE DEFAULT NULL AFTER `abone_tc`,
  ADD COLUMN IF NOT EXISTS `sicil_durumu`
      ENUM('normal','eksik','yanitlandi','cozuldu') NOT NULL DEFAULT 'normal'
      AFTER `hakedisten_dus`,
  ADD INDEX IF NOT EXISTS `idx_sicil_durumu` (`firma_id`, `sicil_durumu`);
```

- `abone_tc` / `abone_dogum_tarihi`: **şu an tabloda yok.** Sorunun kaynağı olan iki
  alan sistemde hiç tutulmuyor, bu yüzden düzeltilen bilgi yazılacak yer yok.
  Doğrulanmış bilgi buraya işlenince bir sonraki teslim listesi/export doğru çıkar.
- `sicil_durumu`: yalnızca **rozet ve filtre performansı** için denormalize bayrak.
  Tek doğru kaynak `kacak_sicil_eksik` tablosudur.

### 2.3 Yeni tablo: `kacak_sicil_eksik`

Neden ayrı tablo: **aynı tutanak birden fazla kez geri dönebilir.** Kurum düzeltmeyi
de kabul etmezse ikinci tur başlar. Tek kolonla tutulursa geçmiş kaybolur ve
"kaç kez geri geldi" sorusu cevaplanamaz.

```sql
CREATE TABLE IF NOT EXISTS `kacak_sicil_eksik` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firma_id` INT(11) NOT NULL,
  `kacak_id` INT(11) DEFAULT NULL,          -- sistemde eşleşen tutanak (NULL olabilir)
  `tutanak_no` VARCHAR(100) NOT NULL,       -- eşleşmese de kurumun verdiği numara
  `tutanak_tarihi` DATE DEFAULT NULL,
  `tur_sira` TINYINT(3) NOT NULL DEFAULT 1, -- kaçıncı gidiş-geliş turu

  `neden` ENUM('tc_hatali','dogum_tarihi_hatali','ad_soyad_hatali','adres_hatali',
               'sayac_no_hatali','abone_bulunamadi','tutanak_okunmuyor','diger')
          NOT NULL,
  `aciklama` TEXT DEFAULT NULL,

  `durum` ENUM('beklemede','yanitlandi','cozuldu','iptal')
          NOT NULL DEFAULT 'beklemede',

  `bildiren_user_id` INT(11) NOT NULL,      -- kurum kullanıcısı
  `bildirim_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
  `atanan_personel_ids` VARCHAR(255) DEFAULT NULL, -- tutanağı tutan ekip (kopya)

  `yanit_veren_personel_id` INT(11) DEFAULT NULL,
  `yanit_veren_user_id` INT(11) DEFAULT NULL,
  `yanit_tarihi` DATETIME DEFAULT NULL,
  `yanit_aciklama` TEXT DEFAULT NULL,
  `duzeltilen_veri` LONGTEXT DEFAULT NULL,  -- JSON: {tc, dogum_tarihi, ad_soyad, adres}
  `onceki_veri` LONGTEXT DEFAULT NULL,      -- JSON: düzeltmeden önceki hâli

  `kapatan_user_id` INT(11) DEFAULT NULL,
  `kapatma_tarihi` DATETIME DEFAULT NULL,
  `kapatma_aciklama` TEXT DEFAULT NULL,

  `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` DATETIME DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_firma_durum` (`firma_id`, `durum`),
  KEY `idx_kacak` (`kacak_id`),
  KEY `idx_tutanak` (`firma_id`, `tutanak_no`),
  KEY `idx_bildiren` (`bildiren_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`kacak_id` NULL olabilir: kurum sistemde bulunmayan bir tutanak numarası bildirebilir
(numara yanlış okunmuş, kayıt henüz girilmemiş vb.). Bu durum kaybolmamalı, ofis
personeli sonradan eşleştirebilmeli.

Silme işlemi soft delete (`silinme_tarihi`) ile yürür — proje kuralı.

---

## 3. Durum Makinesi

```
        ┌──────────────────────────────────────────────────────────┐
        │                                                          │
        v                                                          │
  ┌───────────┐   ekip düzeltir   ┌────────────┐  kurum reddeder   │
  │ beklemede │ ────────────────> │ yanitlandi │ ──────────────────┘
  └───────────┘                   └────────────┘   (yeni kayıt, tur_sira+1)
        │                               │
        │ kurum iptal eder              │ kurum onaylar (sicil açıldı)
        v                               v
   ┌────────┐                      ┌─────────┐
   │ iptal  │                      │ cozuldu │
   └────────┘                      └─────────┘
```

| # | Aksiyon | Kim | `kacak_sicil_eksik.durum` | `kacak_kontrol.sicil_durumu` | Bildirim |
|---|---|---|---|---|---|
| 1 | Sicil oluşmadı bildirimi | Kurum kullanıcısı | `beklemede` | `eksik` | → Ekip (PWA push + `bildirimler`) |
| 2 | Doğru bilgi girildi | Ekip (PWA) / ofis | `yanitlandi` | `yanitlandi` | → `bildiren_user_id` (masaüstü bildirim) |
| 3a | Sicil açıldı, kapat | Kurum kullanıcısı | `cozuldu` | `cozuldu` | → Ekip (bilgilendirme) |
| 3b | Bilgi yine hatalı | Kurum kullanıcısı | mevcut `cozuldu`+ yeni kayıt `beklemede` | `eksik` | → Ekip |
| 3c | Hatalı bildirim, geri al | Kurum kullanıcısı | `iptal` | `normal` (başka açık kayıt yoksa) | — |

**Adım 2'de veri iki yere yazılır:**
1. `kacak_sicil_eksik.duzeltilen_veri` (JSON) — kimin, ne zaman, neyi düzelttiğinin izi
2. `kacak_kontrol.abone_tc` / `abone_dogum_tarihi` / `abone_adi` — kaynak verinin
   kendisi. Eski değer `onceki_veri` alanına yedeklenir.

`sicil_durumu` her geçişte `kacak_sicil_eksik` üzerinden yeniden hesaplanır
(açık kayıtların en öncelikli durumu), elle set edilmez.

---

## 4. Yetkiler

Mevcut durumda `Kaçak Kontrol Sorumlusu` rolünün tüm yazma yetkileri kaldırılmış —
bkz. [`kacak_kontrol_sorumlusu_sadece_goruntuleme.sql:26`](../sql/kacak_kontrol_sorumlusu_sadece_goruntuleme.sql#L26).
Bu rol kurum tarafını temsil ettiği için **yeni ve dar kapsamlı** bir yetki gerekiyor.

| `auth_name` | Kime | Ne yapar |
|---|---|---|
| `kacak_sicil_bildir` | Kurum kullanıcısı (Kaçak Kontrol Sorumlusu) | Eksik bildirimi açar, çözüldü/iptal olarak kapatır |
| `kacak_sicil_yanitla` | Ofis + ekip yöneticisi | Düzeltilmiş bilgiyi girer |

Bu iki yetki `kacak_duzenle`'den bağımsızdır — kurum kullanıcısı tutanağa
dokunamaz, sadece sicil eksik akışını yönetir.

**Sekme görünürlüğü:**
```php
$yetkiSicilBildir  = Gate::allows('kacak_sicil_bildir')  || Gate::isSuperAdmin();
$yetkiSicilYanitla = Gate::allows('kacak_sicil_yanitla') || Gate::isSuperAdmin();
$yetkiSicil        = $yetkiSicilBildir || $yetkiSicilYanitla;
```

PWA tarafında ekip için ayrı yetki aranmaz; personel yalnızca **kendi ekibine atanmış**
kayıtları görür ve yanıtlar (`FIND_IN_SET(personel_id, atanan_personel_ids)`).

---

## 5. Dosya Dosya Uygulama Adımları

### Adım 1 — SQL scripti
**Yeni:** `sql/kacak_sicil_olusmayanlar.sql`

1. `kacak_kontrol` yeni kolonlar (§2.2)
2. `kacak_sicil_eksik` tablosu (§2.3)
3. `permissions` kayıtları: `kacak_sicil_bildir`, `kacak_sicil_yanitla`
   (grup: `İş Takip`, mevcut `kacak_islemleri.sql` §4 deseniyle aynı, `NOT EXISTS` korumalı)
4. `user_role_permissions`:
   - `Kaçak Kontrol Sorumlusu` rolüne → `kacak_sicil_bildir`
   - `Süper Admin` + `Firma Sahibi` rollerine → her ikisi
   - `kacak_duzenle` yetkisi olan rollere → `kacak_sicil_yanitla`

> Script idempotent olacak (`IF NOT EXISTS` / `NOT EXISTS` korumalı), tekrar
> çalıştırılabilmeli.

---

### Adım 2 — Model
**Yeni:** `App/Model/KacakSicilEksikModel.php`

`Model` sınıfından türetilir, tüm sorgular `prepare()/execute()` ile. Metotlar:

| Metot | Açıklama |
|---|---|
| `list(array $filters)` | durum, tarih aralığı, neden, ekip, arama; DataTables besler |
| `find(int $id)` | Tek kayıt + `kacak_kontrol` join (abone bilgileri, ekip, foto sayısı) |
| `tutanakAra(string $q)` | Tutanak no autocomplete — kurum kullanıcısı için |
| `create(array $data)` | Yeni eksik bildirimi. `kacak_id` çözümlemesi, `tur_sira` hesabı, `atanan_personel_ids` kopyası |
| `yanitla(int $id, array $veri, int $personelId)` | Düzeltme kaydı + `kacak_kontrol` güncellemesi — **tek transaction** |
| `kapat(int $id, string $sonuc, string $aciklama)` | `cozuldu` / `iptal` |
| `personelListesi(int $personelId, array $filters)` | PWA: ekibe atanmış kayıtlar |
| `sayaclar()` | Sekme rozetleri: bekleyen / yanitlandi adetleri |
| `sicilDurumuTazele(int $kacakId)` | `kacak_kontrol.sicil_durumu` yeniden hesaplama (private) |

**Dikkat:** `yanitla()` içinde `kacak_sicil_eksik` güncellemesi ve `kacak_kontrol`
güncellemesi **aynı transaction'da** olmalı; yarım kalırsa veri tutarsızlaşır.

---

### Adım 3 — Masaüstü API
**Değişecek:** [`views/kacak/api.php`](../views/kacak/api.php)

Mevcut `switch ($action)` bloğuna ([satır 113](../views/kacak/api.php#L113)) yeni case'ler:

| Action | Yetki | Açıklama |
|---|---|---|
| `sicil-list` | `kacak_sicil_bildir` \|\| `kacak_sicil_yanitla` | Liste (durum filtreli) |
| `sicil-counts` | aynı | Rozet sayaçları |
| `sicil-tutanak-ara` | `kacak_sicil_bildir` | Tutanak no autocomplete |
| `sicil-create` | `kacak_sicil_bildir` | Yeni eksik bildirimi |
| `sicil-yanitla` | `kacak_sicil_yanitla` | Düzeltme girişi |
| `sicil-kapat` | `kacak_sicil_bildir` | Çözüldü / iptal |
| `sicil-detay` | her ikisi | Geçmiş turlar dahil detay |

Uyulacak mevcut desenler:
- Yetki: `kacakYetkiKontrol('kacak_sicil_bildir')` — [`api.php:91`](../views/kacak/api.php#L91) yardımcısı `kacakIzin` ile genişletilecek
- Salt okunur action'lar `$saltOkunurActionlar` dizisine eklenecek (session kilidi
  bırakma optimizasyonu — [`api.php:44-56`](../views/kacak/api.php#L44-L56))
- Log: `$Log->logAction($userId, 'Sicil Eksik Bildirimi', "...", SystemLogModel::LEVEL_IMPORTANT)`
- Hata: kullanıcıya genel mesaj, detay `error_log()` ile

**Yeni yardımcı fonksiyon:** `sicilBildirimGonder(array $kayit, string $tip)` —
mevcut [`kacakBildirimGonder()`](../views/kacak/api.php#L635) deseniyle aynı,
`PushNotificationService::sendToPersonel()` + `BildirimModel::createNotification()`.

---

### Adım 4 — Masaüstü Sekme
**Değişecek:** [`views/kacak/list.php`](../views/kacak/list.php)

**4a. Yetki değişkenleri** — [satır 51-54](../views/kacak/list.php#L51-L54) yanına §4'teki üç değişken.

**4b. Sekme butonu** — [satır 269](../views/kacak/list.php#L269) `#pane-teslim`'den sonra:

```php
<?php if ($yetkiSicil): ?>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-sicil" type="button">
    <i class="bx bx-user-x me-1"></i> Sicil Oluşmayanlar
    <span class="badge bg-danger ms-1" id="sicilBadge" style="display:none">0</span>
  </button></li>
<?php endif; ?>
```

Rozet mantığı mevcut `bekleyenBadge` ([satır 259](../views/kacak/list.php#L259)) ile aynı,
ancak **rol duyarlı**:
- Kurum kullanıcısı → `yanitlandi` sayısı (onun aksiyon bekleyeni)
- Ofis/ekip → `beklemede` sayısı

Bu, "kurum düzeltmeyi tekrar nasıl görecek" sorusunun doğrudan cevabı.

**4c. `#pane-sicil` içeriği** — 3 alt sekme (nav-pills):

| Alt sekme | İçerik |
|---|---|
| **Bekleyen** | Ekipten yanıt bekleyenler. Yaşlandırma: 3+ gün sarı, 7+ gün kırmızı satır |
| **Yanıtlandı** | Ekip düzeltmeyi girdi, kurum kontrol edecek |
| **Çözüldü / Arşiv** | `cozuldu` + `iptal`, tarih filtreli |

Tablo kolonları:
`Tutanak No | Tutanak Tarihi | Abone | Ekip | Neden | Açıklama | Bildiren | Bekleme Süresi | Tur | Durum | İşlem`

**4d. İki modal:**

- **Eksik Bildir** (`kacak_sicil_bildir`):
  `Tutanak No` (Select2 AJAX autocomplete → seçilince abone/tarih/ekip otomatik dolar),
  `Neden` (Select2), `Açıklama` (textarea)
- **Düzeltme Gir** (`kacak_sicil_yanitla`):
  Sol tarafta kurumun bildirdiği neden + mevcut bilgiler (salt okunur),
  sağ tarafta `TC Kimlik No`, `Doğum Tarihi`, `Ad Soyad`, `Adres`, `Açıklama`

> **Zorunlu:** Tüm form alanları `App\Helper\Form` metotlarıyla üretilecek
> (`FormFloatInput`, `FormSelect2`, `FormFloatTextarea`, `FormDate`). Ham
> `<input>`/`<select>` yazılmayacak. Modal içindeki Select2'lerde
> `dropdownParent` ilgili modal olarak verilecek — proje standardı.

**4e. DataTables** — mevcut `dtSecenekleri()` yardımcısı
([list.php:898](../views/kacak/list.php#L898) civarı) kullanılacak;
`<th>`lere `data-filter="string|select|date"` nitelikleri eklenecek.

**4f. Kayıtlar sekmesine sicil rozeti** — ana tabloda `sicil_durumu != 'normal'`
olan satırlara küçük ikon; kullanıcı listeye bakarken takılan tutanağı görsün.

**4g. Listeden doğrudan bildirim** — Kayıtlar ve Bekleyen Onaylar sekmelerinde
`kacak_sicil_bildir` yetkisi olanlara işlem butonu **ve satıra sağ tık menüsü**.
Tutanak zaten açık kayıtlıysa buton "Sicil Kaydına Git"e dönüşür.
Bu yol `get-record` ile tutanağı çekip modalı önceden doldurur; kurum kullanıcısı
tutanak numarasını elle aramak zorunda kalmaz.

---

### Adım 5 — PWA (ekip tarafı)
**Değişecek:** [`views/personel-pwa/pages/kacak.php`](../views/personel-pwa/pages/kacak.php),
[`views/personel-pwa/api.php`](../views/personel-pwa/api.php)

Ekipler sahada telefonda çalışıyor; masaüstüne dönmelerini beklemek akışı yavaşlatır.

**5a. Yeni API action'ları** (PWA `api.php` switch bloğuna):
- `getSicilDuzeltmeTalepleri` — `atanan_personel_ids` içinde personel geçen kayıtlar
- `saveSicilDuzeltme` — düzeltilmiş bilgiyi kaydeder

**5b. Sayfa değişikliği:** Başlığın altına, kuyruk şeridinin
([satır 35](../views/personel-pwa/pages/kacak.php#L35)) hemen altına yeni bölüm:

```
┌────────────────────────────────────────────┐
│ ⚠ 2 tutanak için bilgi düzeltmesi bekleniyor│
│                                             │
│ #41625 · 12.07.2026 · Ahmet Yılmaz         │
│ Doğum tarihi hatalı                        │
│ "Nüfus kaydıyla uyuşmuyor"     [Düzelt →]  │
└────────────────────────────────────────────┘
```

Açık talep yoksa bölüm gizli.

**5c. Düzeltme modalı** — TC, doğum tarihi, ad soyad, açıklama alanları.

> **Kritik uyarı:** PWA, Tailwind'i `assets/css/tailwind-build.css` adlı **önceden
> derlenmiş statik dosyadan** alır. Build'de olmayan bir utility sınıfı hata vermeden
> sessizce çalışmaz. Kullanılan her sınıf `tailwind-build.css` içinde
> aranarak doğrulanmalı; yoksa satır içi `style="..."` kullanılmalı.
> Ayrıca `pwa-app.js` sayfa içeriğinden **sonra** yüklenir —
> `API`/`Alert`/`Modal`/`Toast` globallerine ancak `DOMContentLoaded` içinde erişilir.

**Offline kuyruk:** İlk sürümde düzeltme kaydı **çevrimiçi zorunlu** olacak.
Mevcut `OfflineQueue` altyapısına bağlamak ek karmaşıklık getirir ve düzeltme
talepleri acil değildir. Gerekirse ikinci fazda eklenir.

---

### Adım 6 — Bildirim Akışı

| Tetikleyici | Hedef | Kanal |
|---|---|---|
| Eksik bildirimi açıldı | `kacak_kontrol.personel_ids` + `bildiren_personel_id` | `PushNotificationService::sendToPersonel()` |
| Ekip düzeltmeyi girdi | `kacak_sicil_eksik.bildiren_user_id` | `BildirimModel::createNotification()` — link: `index.php?p=kacak/list&tab=sicil&id=...` |
| Kurum çözüldü işaretledi | Ekip | Push (bilgilendirme) |

Örnek metin (resimdeki senaryo birebir):
> **Tutanak Bilgi Düzeltmesi**
> 41625 nolu tutanağın doğum tarihi hatalı olduğu bildirildi. Aboneye ulaşıp
> doğru bilgiyi uygulamadan giriniz.

Bildirim gönderimi `try/catch` içinde olacak — push başarısız olsa bile ana
işlem geri alınmamalı ([`kacakBildirimGonder`](../views/kacak/api.php#L635) deseni).

---

## 6. Sınır Durumlar

| Durum | Davranış |
|---|---|
| Tutanak no sistemde yok | `kacak_id = NULL` ile kayıt açılır, listede "Eşleşmedi" rozeti. Ofis sonradan eşleştirir |
| Aynı tutanak için açık kayıt varken yeni bildirim | Engellenir, mevcut kayda yönlendirilir |
| Tutanak `iptal` durumunda | Bildirim açılabilir ama uyarı gösterilir |
| Ekip personeli işten ayrılmış | Bildirim ekipteki diğer aktif personele düşer; hiçbiri aktif değilse ofis listesinde "atanamadı" olarak görünür |
| Aynı kayda iki kişi aynı anda yanıt | `durum = 'beklemede'` koşullu `UPDATE`; ikinci istek "zaten yanıtlanmış" uyarısı alır |
| Kurum kullanıcısı başka firmanın kaydını görmeye çalışırsa | Tüm sorgular `firma_id` filtreli — Model seviyesinde zorunlu |

---

## 7. Test Sonuçları

Model katmanı gerçek veriyle (tutanak 43611) uçtan uca test edildi, test dosyası sonrasında silindi:

| # | Test | Sonuç |
|---|---|---|
| 1 | Tutanak arama (autocomplete) | ✅ eşleşme bulundu |
| 2 | Eksik bildirimi açıldı, ekip otomatik atandı (`90,138`) | ✅ `sicil_durumu = eksik` |
| 3 | Aynı tutanağa ikinci açık kayıt | ✅ engellendi |
| 4 | Geçersiz TC (`123`) | ✅ engellendi |
| 5 | Ekip düzeltmeyi girdi (TC + `15.06.1978`) | ✅ `kacak_kontrol`'e işlendi |
| 5b | **`durum` = `aktif`, `onay_durumu` = `beklemede`** | ✅ **değişmedi** |
| 6 | Aynı kayda ikinci yanıt (yarış durumu) | ✅ engellendi |
| 7 | Ekipte olmayan personel (999) yetkisi | ✅ reddedildi |
| 8 | PWA talep listesi personel bazlı filtre | ✅ 90→1 kayıt, 999→0 kayıt |
| 9 | Sayaçlar | ✅ doğru |
| 10 | Kurum çözüldü işaretledi | ✅ `sicil_durumu = cozuldu` |
| 11 | İkinci tur açıldı | ✅ `tur_sira = 2`, geçmiş 1 kayıt |
| 12 | Farklı `firma_id` ile erişim | ✅ sızıntı yok |

Tüm PHP dosyaları `php -l` ile doğrulandı. PWA'da kullanılan her Tailwind sınıfı
`tailwind-build.css` içinde aranarak doğrulandı (`text-[10px]` build'de yok, kullanılmadı).

### Arayüzde elle doğrulanması gerekenler

- [ ] Kurum kullanıcısı hesabıyla sekme görünüyor, "Sicil Oluşmadı Bildir" çalışıyor
- [ ] Ekip personelinin telefonunda push bildirimi düşüyor
- [ ] PWA'da düzeltme şeridi ve modal doğru render ediliyor
- [ ] Kurum kullanıcısına bildirim düşüyor, sekme rozeti artıyor
- [ ] Bildirimdeki link doğrudan "Yanıtlandı" alt sekmesini açıyor

---

## 8. Uygulama Sırası ve Tahmin

| # | İş | Bağımlılık |
|---|---|---|
| 1 | SQL scripti | — |
| 2 | `KacakSicilEksikModel` | 1 |
| 3 | Masaüstü API action'ları | 2 |
| 4 | Masaüstü sekme + modallar | 3 |
| 5 | Bildirim entegrasyonu | 3 |
| 6 | PWA bölümü + API | 2, 5 |
| 7 | Test + doğrulama | tümü |

Adım 1–5 tamamlandığında akış masaüstünden uçtan uca çalışır durumda olur;
Adım 6 ekiplerin sahadan yanıt verebilmesini ekler.

---

## 9. Kapsam Dışı (İkinci Faz Önerileri)

- Kurumun kendi sisteminden otomatik bildirim göndermesi için harici API endpoint'i
  (`api/external/kacak-sicil-eksik.php`) — mevcut
  [harici API](kacak-kontrol-harici-api-entegrasyonu.md) deseniyle
- Düzeltme talebi için offline kuyruk desteği
- Belirlenen süre içinde yanıtlanmayan talepler için otomatik hatırlatma (cron)
- Neden bazlı istatistik raporu ("en çok hangi hata tipinden sicil oluşmuyor")
