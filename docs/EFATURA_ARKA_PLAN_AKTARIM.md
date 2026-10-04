# EDM taslak ve giden fatura arka plan aktarımı

`efatura/taslak-list` ve `efatura/giden-list` üzerindeki EDM Faturalarını Çek işlemi kalıcı bir aktarım işi oluşturur. API isteği kısa sürede döner; ayrı PHP CLI işçisi sayfa kapansa da aktarımı sürdürür. Sayfa yeniden açıldığında kullanıcıya ait son aktarım sunucudan okunur.

## Kurulum

1. `sql/efatura_sync_jobs.sql` dosyasını uygulamanın veritabanında çalıştırın. Script tekrar çalıştırılabilir; mevcut kayıtları değiştirmez. Eski kurulumlarda `fatura_profili` / `fatura_tipi` alanları ENUM olarak kaldıysa `sql/efatura_profil_tip_uyumlulugu.sql` dosyasını da uygulayın; mevcut değerler korunarak VARCHAR(40) yapısına geçilir.
2. Linux sunucusunda PHP CLI, `exec` ve `nohup` kullanılabilir olmalıdır. Varsayılan PHP yolu `PHP_BINDIR/php` değeridir. Gerekirse `.env` içine `EFATURA_PHP_BINARY=/opt/lampp/bin/php` ekleyin. CLI'da PDO MySQL ve SOAP eklentileri ile uygulamanın aynı HTTPS sertifika ayarları bulunmalıdır.
3. Web sunucusu kullanıcısı `.env` dosyasını okuyabilmeli, mevcut `storage/invoices` dizinine yazabilmelidir. Ayrı cron kurulumu gerekmez.

İşçi yalnız CLI üzerinden çalışır: `php cron/efatura_sync_worker.php <dahili-is-kimligi>`. Kimlik kullanıcı arayüzüne şifrelenerek iletilir. Başlatma, durum, devam ve kapatma API aksiyonları mevcut e-fatura yetkileri, firma/kullanıcı kapsamı, POST ve CSRF kontrolü ile korunur. Windows için otomatik başlatma desteklenmez; bu durumda işçi başlatılamadığı açıkça gösterilir.

## İşleyiş

- Taslak sorgusunda `CONNECTORSTATUSDESCRIPTION=LOAD - SUCCEED` EDM'ye gönderilir; yalnızca taslakların XML içeriği alınır. Durum filtresi TEST servisine salt okuma isteğiyle doğrulandı. `DRAFTCHOICE` alanındaki sayı/bool/metin denemeleri filtre uygulamadığından kullanılmaz.
- Giden sorgusunda önce `HEADER_ONLY=Y` ile başlıklar alınır; taslakların XML'i indirilmez ve taslaklar aktarılmaz. Taslak dışındaki durumları birlikte hariç tutan doğrulanmış servis filtresi bulunmadığı için başlık sayfaları tüm OUT kayıtlarını kapsar; bilinmeyen/yeni giden durumları da korunur.
- 50 kayıt istenir; OFFSET filtrelenen/işlenen sayıdan bağımsız olarak EDM'nin döndürdüğü ham sayfa uzunluğu kadar ilerler. Aktarım türü iş oluşturulurken saklanır; devamda değiştirilmez. Her sayfa yalnız kendi aktarım türünün geçmişini gösterir.
- Tarih üst sınırı işin başlama anında sabitlenir. Aktarım başladıktan sonra oluşturulan faturalar bir sonraki aktarımda alınır; aynı işin devamında sorgu sınırı değiştirilmez.
- Sayfanın ETTN imzası ve sayfa içindeki ilk işlenmemiş kayıt konumu saklanır. Her başarılı fatura sonrası ilerleme kaydedilir. Yarım kalan sayfa tekrar okunur ve işlenmemiş kayıttan devam edilir.
- Geçici EDM bağlantı hatası aynı OFFSET ile 2 ve 4 saniye beklenerek iki kez yeniden denenir. Kayıt bazlı XML/alan doğrulama hataları ayrı sayılır ve sonraki faturaya geçilir; böyle faturalar kaydedilmez. Sonuç `partial` olarak gösterilir, hata sayısı ve ilk neden ekranda sunulur. Veritabanı/sistem hatalarında veya üç başarısız bağlantı denemesi sonunda iş duraklatılır; aktarılan faturalar korunur.
- Devam et aynı işin konumunu kullanır. Aktarımı kapat iş kaydını kapatır ve yeni tarih aralığı seçilmesine izin verir; faturaları silmez.
- EDM durumu `LOAD - SUCCEED` / `LOAD-SUCCEED` olan giden taslakların boş fatura numarası NULL olarak saklanabilir. Gönderilmiş/gelen faturaların numara kontrolü ve dolu ama geçersiz numaraların reddi korunur.
- ETTN üzerinden mevcut Model içe aktarma yolu kullanılır. Bir işlem kaydedildikten hemen sonra işçi kesilirse son kayıt yeniden işlenebilir; mükerrer fatura oluşturulmaz. İşlem sayaçlarında bu yeniden işlem bir güncelleme olarak görünebilir.
- MySQL adlandırılmış kilidi aynı işi iki işçinin eşzamanlı işlemesini engeller. Aynı firmada aynı anda bir açık aktarım bulunur. Farklı türde açık iş varsa yeni iş başlatılmaz; aynı türdeki açık iş yeniden kullanılır.
- İşçi beklenmedik biçimde kapanırsa, sayfadaki durum kontrolü 180 saniyedir ilerlemeyen işi yeniden başlatmayı dener. Üç başlatma girişimine rağmen ilerlemeyen iş duraklatılır. Sayfa tamamen kapalıyken sunucu/işçi yeniden başlatılması sonrası otomatik kurtarma yapılmaz; sayfa yeniden açılınca kurtarma devreye girer.
- Devam edilecek sayfanın içeriği değişmişse veya EDM aynı sayfayı tekrar döndürüyorsa kayıt atlamak yerine iş duraklatılır. Aktarımı kapatıp aynı tarih aralığını tekrar başlatın; mevcut faturalar ETTN üzerinden güncellenir.

## Doğrulama

- `/opt/lampp/bin/php vendor/bin/phpunit tests/Unit/EInvoiceSyncWorkerTest.php tests/Unit/EInvoiceWorkflowTest.php tests/Unit/EInvoicePersistenceTest.php`
- `/opt/lampp/bin/php tests/Integration/efatura-mysql.php` — geçici test veritabanında migration, kilitler, kalıcı konum, firma/kullanıcı kapsamı ve bağımsız arka plan işçisi.
- `node tests/Integration/efatura-sync-browser.cjs` — gerçek taslak veya `EFATURA_SYNC_MODE=giden-list` ile giden sayfası üzerinden sahte API: başlatma, ilerleme, sayfa yenileme, devam, tamamlanma ve CSRF. Gerekirse `EFATURA_CHROME` ile mevcut Chromium/Chrome yolu verilebilir.

Testler EDM'ye fatura göndermez veya gerçek fatura verilerini değiştirmez.
