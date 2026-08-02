# Veritabanı Yedekleme ve Geri Yükleme

Tek veritabanı (`.env` içindeki `DB_NAME`) için akışlı, sıkıştırılmış, artımlı yedekleme sistemi.
`mysqldump`/`exec()` gerektirmez, paylaşımlı hostingde çalışır.

## Dosyalar

| Dosya | Görev |
|---|---|
| `App/Model/YedeklemeModel.php` | Tüm veritabanı erişimi (akışlı okuma, şema keşfi, geri yükleme) |
| `cron/yedekleme_ayar.php` | Ayarlar (dizin, saklama süresi, artımlı tablo listesi) |
| `cron/db_backup.php` | Yedek alma |
| `cron/db_restore.php` | Geri yükleme |
| `<yedek_dizini>/durum.json` | Artımlı yedek işaretleri ve şema özeti |

## Yedek alma

```bash
php cron/db_backup.php              # Pazar günü tam, diğer günler artımlı
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
- Listedeki olmayan (küçük) tablolar: `DELETE FROM` + tam veri, yani her gece tam anlık görüntü.

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

### Yedek doğrulama tatbikatı

Yedeğin gerçekten geri yüklenebildiği düzenli olarak sınanmalıdır:

```bash
mysql -u root -e "CREATE DATABASE yedek_test CHARACTER SET utf8mb4"
php cron/db_restore.php --zaman="$(date '+%Y-%m-%d %H:%M:%S')" --hedef-vt=yedek_test --onayla
mysql -u root -e "CHECKSUM TABLE ersantrc_personel.endeks_okuma EXTENDED"
mysql -u root -e "CHECKSUM TABLE yedek_test.endeks_okuma EXTENDED"
```

## Dosya biçimi

Her ifade işaretli bir blok içindedir; satır bazlı ayrıştırma yapılır, SQL parse edilmez.

```
-- @META {"surum":1,"tip":"tam","vt":"...","zaman":"...","sema":"...","temel":null}
-- @STMT t=system_logs k=veri
INSERT INTO `system_logs` (`id`,...) VALUES (...),(...);
-- @END
-- @SON
```

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

```
0 3 * * *  /usr/bin/php /path/to/ersan_elk/cron/db_backup.php --sessiz >> /path/to/ersan_elk/cron/logs/backup_cron.log 2>&1
```

Pazar günleri tam, diğer günler artımlı yedek alınır. Log: `cron/logs/backup.log`,
`cron/logs/restore.log` ve `system_logs` tablosu.

## Sınırlar

- Artımlı yedek, listedeki tablolarda **kalıcı olarak silinen** (hard delete) satırları takip
  edemez. Bu satırlar bir sonraki tam yedekte düzelir; bu yüzden tam yedek aralığı en fazla
  bir hafta tutulmalıdır. Yumuşak silme (`silinme_tarihi`) takip edilir.
- Yedek boyunca açık kalan tutarlı okuma işlemi, çok yoğun sunucularda undo log büyümesine yol
  açabilir. Yedek saati düşük trafikli bir zamana ayarlanmalıdır.
- Toplu Excel/API veri yüklemesi sonrasında elle bir tam yedek almak güvenlidir.
