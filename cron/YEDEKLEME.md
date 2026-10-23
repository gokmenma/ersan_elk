# Veritabanı Yedekleme ve Geri Yükleme

Tek veritabanı (`.env` içindeki `DB_NAME`) için akışlı, sıkıştırılmış, artımlı yedekleme sistemi.
`mysqldump`/`exec()` gerektirmez, paylaşımlı hostingde çalışır.

## Dosyalar

| Dosya | Görev |
|---|---|
| `App/Model/YedeklemeModel.php` | Tüm veritabanı erişimi (akışlı okuma, şema keşfi, geri yükleme, test VT'ye ayrı bağlantı) |
| `App/Service/VeritabaniYedekService.php` | Yedek alma, rotasyon, kilit, Drive senkronu, rapor maili (CLI ve arayüz ortak) |
| `App/Service/VeritabaniGeriYuklemeService.php` | Yedek tarama, zincir kurma, doğrulama, uygulama, geri yükleme testi |
| `App/Service/YedekYonetimService.php` | Arayüz: birleşik liste, özet, arka plan işleri |
| `cron/yedekleme_ayar.php` | Ayarlar (dizin, saklama süresi, artımlı tablo listesi) |
| `cron/db_backup.php` | Yedek alma (CLI) |
| `cron/db_restore.php` | Geri yükleme (CLI) |
| `views/yedekleme/` | Veritabanı Yedekleri sayfası (yalnız Süper Admin) |
| `<yedek_dizini>/durum.json` | Artımlı yedek işaretleri, şema özeti, son Drive senkronu |
| `<yedek_dizini>/is_durumu.json` | Arayüzden başlatılan son işin durumu ve logu |
| `<yedek_dizini>/geri_yukleme_testleri.json` | Son 30 geri yükleme testinin sonucu |
| `<yedek_dizini>/.islem.lock` | Aynı anda tek yedek/geri yükleme işlemi çalışmasını sağlayan kilit |

## Yedek alma

```bash
php cron/db_backup.php              # Pazar günü ilk çalışmada tam, diğerlerinde artımlı
php cron/db_backup.php --tam        # Tam yedek zorla
php cron/db_backup.php --artimli    # Artımlı yedek zorla
php cron/db_backup.php --tablo=endeks_okuma,personel
php cron/db_backup.php --sessiz --mailsiz
```

Çıktı: `<yedek_dizini>/tam_<vt>_<tarih>.sql.gz` veya `artimli_<vt>_<tarih>.sql.gz` (0600 izinli).

### Tam yedek

Tüm tabloların `DROP` + `CREATE` + veri kaydı, ardından view / trigger / procedure tanımları.
`DEFINER` ifadeleri temizlenir, böylece farklı sunucuya da yüklenebilir.

### Artımlı yedek

- `yedekleme_ayar.php` içindeki `artimli_tablolar` listesindeki büyük tablolar: yalnızca son
  yedekten sonra eklenen/değişen satırlar, `REPLACE INTO` ile yazılır.
  İşaret sütunu otomatik bulunur: `AUTO_INCREMENT` sütunu + varsa `updated_at`,
  `guncelleme_tarihi`, `silinme_tarihi`, `deleted_at`.
- Listedeki olmayan (küçük) tablolar: `DELETE FROM` + tam veri, yani her çalışmada tam anlık görüntü.

Parametresiz çalıştırmada tam yedek, `tam_yedek_gunu` gününün **ilk** çalışmasında alınır; aynı gün
sonraki çalışmalar artımlıdır. Bu sayede cron 2 saatte bir çalışsa da haftada bir tam yedek oluşur.

Başka bir yedekleme/geri yükleme (cron veya arayüz) sürerken yeni çalışma kilit nedeniyle atlanır
ve uyarı loglanır.

Şu üç durumda artımlı istense bile otomatik olarak tam yedek alınır:

1. `durum.json` yok veya ilk çalıştırma
2. Tablo yapısı değişmiş (şema md5 özeti tutmuyor)
3. Referans tam yedek dosyası silinmiş

### Tutarlılık

Yedek `START TRANSACTION WITH CONSISTENT SNAPSHOT` içinde alınır; tablolar kilitlenmez,
uygulama yedek sırasında çalışmaya devam eder ve dosya tek bir ana ait tutarlı görüntü içerir.

## Geri yükleme

```bash
php cron/db_restore.php --liste
php cron/db_restore.php --zaman="2026-08-02 03:00:00"            # kuru çalışma (plan)
php cron/db_restore.php --zaman="2026-08-02 03:00:00" --onayla   # gerçek geri yükleme
php cron/db_restore.php --dosya=tam_vt_2026-08-02_03-00-00.sql.gz --onayla
php cron/db_restore.php --zaman="..." --tablo=endeks_okuma --onayla
php cron/db_restore.php --zaman="..." --hedef-vt=yedek_test --onayla
```

`--onayla` verilmezse hiçbir yazma işlemi yapılmaz; yalnızca uygulanacak zincir, dosya
bütünlüğü ve ifade sayısı raporlanır.

`--zaman` verildiğinde o ana kadarki en son tam yedek bulunur, ardından ona bağlı artımlı
yedekler sırayla uygulanır. Her dosya uygulanmadan önce bütünlük açısından doğrulanır
(yarım kalmış ifade veya eksik sonlandırma işareti varsa işlem başlamaz).

### Yedek doğrulama tatbikatı (geri yükleme testi)

Yedeğin gerçekten geri yüklenebildiği düzenli olarak sınanmalıdır. En kolay yol
**Yönetim → Veritabanı Yedekleri** sayfasındaki satır işlemi **Geri Yükleme Testi**'dir:

1. Seçilen yedek (artımlıysa bağlı tam yedek + aradaki artımlılar) doğrulanır.
2. `.env` içindeki `YEDEK_TEST_VT` veritabanına **ayrı bir PDO bağlantısıyla** yüklenir; canlı
   bağlantı hiç değişmez. `YEDEK_TEST_VT` canlı VT ile aynıysa test başlamaz.
3. Her tablonun satır sayısı, yedek anında dosyaya yazılan `@SAYIM` değerleriyle karşılaştırılır.
4. Test veritabanı sonunda boşaltılır (disk kotası için).

cPanel kurulumu: **MySQL Databases** ekranından `ersantrc_yedektest` gibi bir veritabanı oluşturun,
uygulamanın VT kullanıcısını **ALL PRIVILEGES** ile ekleyin ve `.env`'e yazın:

```
YEDEK_TEST_VT=ersantrc_yedektest
```

Komut satırından aynı işlem:

```bash
php cron/db_restore.php --zaman="$(date '+%Y-%m-%d %H:%M:%S')" --hedef-vt=ersantrc_yedektest --onayla
```

## Dosya biçimi

Her ifade işaretli bir blok içindedir; satır bazlı ayrıştırma yapılır, SQL parse edilmez.

```
-- @META {"surum":1,"tip":"tam","vt":"...","zaman":"...","sema":"...","temel":null}
-- @STMT t=system_logs k=veri
INSERT INTO `system_logs` (`id`,...) VALUES (...),(...);
-- @END
-- @SAYIM {"system_logs":104600,"personel":84,...}
-- @SON
```

`@SAYIM`, yedeğin alındığı tutarlı anlık görüntüdeki tablo satır sayılarıdır (artımlı yedekte de
tablonun o andaki toplamı). Geri yükleme testi bu değerlerle karşılaştırma yapar. Bu satırdan önce
alınmış eski yedekler yine yüklenebilir; yalnızca satır sayısı karşılaştırması yapılamaz.

Değerler `PDO::quote()` ile kaçırıldığı için satır sonu karakteri içermez; bu yüzden her veri
ifadesi tek satırdır. `k` alanı: `oturum`, `ddl`, `veri`, `temizle`, `view`, `trigger`, `rutin`.

Dosya `gunzip` sonrası `mysql < dosya.sql` ile de yüklenebilir; tek istisna trigger tanımlarıdır
(`DELIMITER` satırı içermez, `db_restore.php` bunları tek ifade olarak gönderir).

## Ayarlar

```php
'yedek_dizini'   => $_ENV['BACKUP_DIR'] ?? kök . '/backups',
'saklama_gun'    => 14,
'tam_yedek_gunu' => 0,          // 0 = Pazar
'paket_limiti'   => 512 * 1024, // tek INSERT ifadesinin üst sınırı
'satir_limiti'   => 2000,       // tek INSERT ifadesindeki en fazla satır
```

`paket_limiti` çalışma anında sunucunun `max_allowed_packet` değeriyle karşılaştırılır ve
gerekirse otomatik düşürülür.

Yedek dizini web kökü içindeyse `.htaccess` + `index.html` otomatik oluşturulur. Mümkünse
`.env` içine `BACKUP_DIR` tanımlayıp dizini web kökü dışına alın:

```
BACKUP_DIR=/home/kullanici/db_yedekleri
```

## Crontab

cPanel → **Cron Jobs** → *Common Settings: Once Per Two Hours* (`0 */2 * * *`):

```
0 */2 * * *  /usr/local/bin/php /home/KULLANICI/public_html/ersan_elk/cron/db_backup.php --sessiz >> /home/KULLANICI/public_html/ersan_elk/cron/logs/backup_cron.log 2>&1
```

PHP yolu sunucuya göre değişebilir (`which php` veya cPanel'deki örnek komut). Pazar günü ilk
çalışmada tam, diğer tüm çalışmalarda artımlı yedek alınır; olası veri kaybı en fazla 2 saattir.
Başarılı çalışmalar için günde **bir** rapor maili gider, hata maili her seferinde gönderilir.
Log: `cron/logs/backup.log`, `cron/logs/restore.log` ve `system_logs` tablosu.

Boyut tahmini: yerel test verisinde artımlı yedek ≈ 3,7 MB (çoğu, `artimli_tablolar` listesinde
olmayan küçük tabloların her seferki tam anlık görüntüsü). 12 çalışma/gün ile yerelde ~45 MB/gün;
Drive'da artımlılar 14 gün (`drive_artimli_saklama_gun`), tam yedekler 90 gün tutulur.

## Google Drive uzak kopya

Her başarılı yedekten sonra `db_backup.php`, yedek dizininde olup Drive'da olmayan tüm
`tam_*` / `artimli_*` dosyalarını **AES-256-GCM ile şifreleyip** Drive klasörüne yükler,
yüklenen dosyanın MD5 özetini doğrular ve Drive'da `drive_saklama_gun` (varsayılan 90)
gününden eski zincirleri, `drive_artimli_saklama_gun` (varsayılan 14) gününden eski artımlı
yedekleri siler. Daha eski bir ana dönüş haftalık tam yedeklerle yapılır. Yükleme başarısız olursa yerel yedek etkilenmez, rapor
`[HATA]` başlıklı gider ve bir sonraki çalışmada eksik dosyalar yeniden denenir.
`.env` değerleri tanımlı değilse adım yalnızca uyarı verip atlanır. `--drivesiz` ile kapatılır.

| Dosya | Görev |
|---|---|
| `App/Service/GoogleDriveService.php` | OAuth2 + Drive REST (parçalı, devam ettirilebilir yükleme) |
| `App/Service/YedekSifrelemeService.php` | Akışlı AES-256-GCM şifreleme / çözme |
| `App/Service/DriveYedekSenkronService.php` | Yükleme, doğrulama, uzak rotasyon, indirme |
| `cron/drive_yetkilendir.php` | Anahtar üretme, Google yetkilendirme, bağlantı testi |
| `cron/drive_geri_al.php` | Drive'dan indirip çözme (felaket kurtarma) |

### Kurulum (bir kez)

1. <https://console.cloud.google.com> → yeni proje → **APIs & Services → Library → Google Drive API → Enable**.
2. **OAuth consent screen**: User type *External*, uygulama adı ve e-posta; scope olarak
   `.../auth/drive.file`. Kaydettikten sonra **Publish app** ile *In production* yapın.
   *Testing* durumunda bırakılırsa refresh token 7 gün sonra geçersiz olur.
3. **Credentials → Create credentials → OAuth client ID → Application type: Desktop app**.
   Client ID ve Client secret değerlerini `.env`'e yazın.
4. Şifreleme anahtarı üretin ve `.env`'e yazın:
   ```bash
   php cron/drive_yetkilendir.php --anahtar-uret
   ```
   **Bu anahtarı sunucu dışında da (şifre yöneticisi) saklayın.** Sunucu kaybolup anahtar da
   kaybolursa Drive'daki yedekler açılamaz.
5. Yetkilendirin; çıkan adresi tarayıcıda açıp izin verin, yönlendirilen `http://localhost/?code=...`
   adresini kopyalayın:
   ```bash
   php cron/drive_yetkilendir.php
   php cron/drive_yetkilendir.php --kod="http://localhost/?code=..."
   ```
   Çıktıdaki `GDRIVE_REFRESH_TOKEN` ve `GDRIVE_KLASOR_ID` satırlarını `.env`'e ekleyin.
6. Doğrulayın ve ilk tam yedeği gönderin:
   ```bash
   php cron/drive_yetkilendir.php --test
   php cron/db_backup.php --tam
   ```

```
GDRIVE_CLIENT_ID=...
GDRIVE_CLIENT_SECRET=...
GDRIVE_REFRESH_TOKEN=...
GDRIVE_KLASOR_ID=...
YEDEK_SIFRELEME_ANAHTARI=...
```

`drive.file` kapsamı uygulamaya yalnızca kendi oluşturduğu dosyalara erişim verir; Drive'daki
diğer dosyalarınız okunamaz.

### Felaket kurtarma (Drive'dan geri dönüş)

Yeni sunucuya kod ve `.env` (anahtar dahil) yerleştirildikten sonra:

```bash
php cron/drive_geri_al.php --liste
php cron/drive_geri_al.php --zaman="2026-08-02 03:00:00"
php cron/db_restore.php --zaman="2026-08-02 03:00:00"
php cron/db_restore.php --zaman="2026-08-02 03:00:00" --onayla
```

`drive_geri_al.php` veritabanına yazmaz; o ana ait tam yedeği ve sonrasındaki artımlı
yedekleri indirir, MD5 ve şifre bütünlüğünü doğrulayıp yedek dizinine açar.

## Yönetim arayüzü

**Yönetim → Veritabanı Yedekleri** (`index.php?p=yedekleme/list`). Erişim yalnız
`Gate::isSuperAdmin()` rollerine açıktır (Süper Admin ve Firma Sahibi); yetki kaydı
`sql/2026_10_22_add_yedekleme_yonetimi_menu_and_permissions.sql` ile oluşturulur.

- Özet kartları: toplam yedek, son yedek, Google Drive durumu, son geri yükleme testi.
- Liste: yerel dizin ve Drive birlikte; konum, son test sonucu ve satır işlemleri
  (Detay, Geri Yükleme Testi, İndir, Drive'dan Yerele Al).
- Elle **Tam / Artımlı Yedek Al** ve **Google Drive'a Gönder**.
- Uzun işler yanıt döndükten sonra arka planda sürer; sayfa 2 saniyede bir durumu ve logu çeker.
  Sunucu süreci öldürürse iş "Yarıda kaldı" olarak işaretlenir ve kilit serbest kalır.
- İndirme POST + CSRF ile yapılır ve `system_logs` tablosuna kritik seviyede yazılır; kayıt
  yazılamazsa indirme başlamaz.
- Canlı veritabanına geri yükleme arayüzde **yoktur**; yalnız `cron/db_restore.php` ile yapılır.

## Sınırlar

- Artımlı yedek, listedeki tablolarda **kalıcı olarak silinen** (hard delete) satırları takip
  edemez. Bu satırlar bir sonraki tam yedekte düzelir; bu yüzden tam yedek aralığı en fazla
  bir hafta tutulmalıdır. Yumuşak silme (`silinme_tarihi`) takip edilir.
- Yedek boyunca açık kalan tutarlı okuma işlemi, çok yoğun sunucularda undo log büyümesine yol
  açabilir. Yedek saati düşük trafikli bir zamana ayarlanmalıdır.
- Toplu Excel/API veri yüklemesi sonrasında elle bir tam yedek almak güvenlidir.
