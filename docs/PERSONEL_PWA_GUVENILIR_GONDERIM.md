# Personel PWA: zayıf bağlantıda güvenilir gönderim

Kaçak ve ihbar oluşturma/düzenleme işlemleri, fotoğraf ve videolarıyla önce IndexedDB'ye kaydedilir. Form yalnızca cihazdaki yazma tamamlandıktan sonra kapanır. Depolama hatasında form açık kalır. Kuyruk, personel ve firma hesabına bağlıdır; çıkışta silinmez ve başka hesapla gönderilmez.

## Gönderim davranışı

- Yeni kaydın zorunlu ilk fotoğrafı ana istekle, kalan fotoğraflar ayrı isteklerle gönderilir. Mevcut kaydı düzenlerken ilk fotoğraf zaten varsa yeni fotoğraflar ayrı gönderilir. Kaçak tutanak fotoğrafı düzenlemede ana isteğe dahildir.
- Videolar 256 KB parçalarla gönderilir. Her parçanın ve birleşmiş dosyanın SHA-256 özeti ve boyutu doğrulanır. Yeniden açılışta sunucunun aldığı parçalar sorgulanır; yalnızca eksikler gönderilir. Tamamlama yanıtı kaybolursa kalıcı sonuç okunur ve video yeniden gönderilmez.
- Sunucudaki işlem sonucu ile kayıt/dosya veritabanı değişikliği aynı PDO transaction içinde saklanır. İşlem anahtarları personel ve firma bazında benzersizdir. Yeniden gönderim kayıt, fotoğraf, video veya ihbar ataması oluşturmaz.
- Sayfa ve service worker aynı IndexedDB kilidini kullanır. Kilit 150 saniye geçerlidir, 30 saniyede bir yenilenir. Kilidini kaybeden gönderici ilerlemeyi değiştiremez.
- Gönderim istekleri en fazla 120 saniye bekler. Geçici hatalarda yeniden deneme aralığı 30 saniyeden başlayıp 5 dakikaya kadar çıkar. Liste/referans istekleri 15 saniyede sonlanır; saha sayfası navigasyonları 8 saniye sonunda önbelleğe dönebilir. Zaman aşımı isteğin sunucu tarafından işlenmediği anlamına gelmez; sabit işlem anahtarı bu belirsizliği karşılar.
- Oturum ve yetki hatalarında kayıtlar korunur; kullanıcı girişini doğrulayıp “Şimdi tekrar dene” seçeneğini kullanır. Doğrulama hataları otomatik olarak sürekli gönderilmez.
- Önceden yetkilendirilip ana kaydı ulaşmış gönderimin kalan ekleri, kayıt sonradan onaylansa/sonuçlandırılsa da tamamlanabilir. Her ek, aynı hesap ve ana işlem sonucu üzerinden kayıtla eşleştirilir; yeni düzenlemeler mevcut durum/yetki kontrollerinden geçer.
- “Tamamlandı” yalnızca tüm dosyalar sunucu tarafından onaylandığında görünür. Tamamlanan gönderim özetleri cihazda bir gün tutulur; tamamlanmamış medya otomatik silinmez.

## Eski kuyruklar

IndexedDB v1 kayıtları v2 yükseltmesinde korunur. Eski kayıtlarda güvenilir hesap bilgisi bulunmadığından otomatik olarak yeni hesaba bağlanmaz. Kullanıcı kendi eski kaydını “Eski kaydı hesabımla eşleştir” seçeneğiyle kurtarır. Ana kaydı daha önce ulaşmış kayıtlar sunucuda UUID ve bildiren personel kontrolüyle eşleştirilir; eski fotoğraf sıra bilgileri de mükerrer yüklemeyi önlemek için kontrol edilir.

Ana isteğinin sonucu belirsiz olan kuyruk kaydı değiştirilmez; önce aynı işlemle yeniden gönderilip sonuç doğrulanır. Sunucunun açıkça reddettiği, henüz oluşmamış kaçak kayıtlar kuyruk formundan düzeltilebilir.

## Sunucuya alma

1. Yeni dosyalar etkinleştirilmeden önce `database/migrations/2026_10_01_pwa_reliable_transfers.sql` çalıştırılmalıdır. Script yalnızca işlem sonuçlarını saklayan yeni tabloyu oluşturur. Sonuç kayıtları yeniden denemeler için saklanmalı, rutin temizlikte silinmemelidir.
2. PHP kullanıcısı sistem geçici dizinine ve mevcut fotoğraf/video yükleme dizinlerine yazabilmelidir. Parçalar web kökü dışında `sys_get_temp_dir()/ersan-pwa-<uygulama-özeti>` altında, 0700 izinli dizinlerde tutulur. Çok sunuculu kurulumda aynı gönderimin istekleri aynı sunucuya yönlenmeli veya özel geçici alan ortak olmalıdır.
3. `views/cron/pwa_transfer_cleanup.php` aynı PHP kullanıcısıyla günlük CLI görevi olarak çalıştırılmalıdır. Yedi gün hareketsiz kalan geçici parçalar temizlenir. İstemcideki asıl video korunur ve sonraki denemede yeniden parçalara gönderilir. Temizlik ayrıca video isteklerinde çalışır.
4. Service worker kuyruk sürümü 21'dir. Yeni worker etkinleşince eski statik önbellek kaldırılır; kalıcı kuyruk korunur. Sürüm parametreli statik dosyalar çevrimdışıyken hazırlanan önbellekten de açılabilir. Uyarı/onay pencereleri için mevcut yerel SweetAlert2 dosyası kullanılır ve önbelleğe alınır; harici CDN bağlantısı gerekmez.

SQL scripti uygulama veritabanına bu geliştirme sırasında otomatik uygulanmadı; MySQL testleri ayrı, geçici test şemasında çalışır.

HTTPS ve IndexedDB destekleyen bir tarayıcı gerekir. Kalıcı depolama talep edilir; telefonun depolamasının kullanıcı veya işletim sistemi tarafından temizlenmesi kayıtları kaldırabilir. Uygulama açıkken ve yeniden açıldığında gönderim devam eder. Uygulama kapalıyken devam etme, tarayıcının Background Sync desteğine bağlıdır.

## Yeni API alanları

Ana oluşturma/düzenleme aksiyonları `reliable_transfer=1`, `operation_key`, `account_key` alanlarıyla mevcut doğrulama yollarını kullanır. Yanıtta şifreli `target_token` döner. Eklerde `transfer_key`, `main_action`, `kind`, `account_key`, `target_token` ile ana işlem ve kayıt eşleştirilir.

- `pwaTransferIdentity`: aktif hesabın kuyruk anahtarı.
- `pwaTransferResolve`: sahipliği doğrulanan eski kaçak UUID'sinin şifreli hedefi.
- `pwaTransferPhoto`: sabit `operation_key` ile bir fotoğraf.
- `pwaVideoStart` / `pwaVideoStatus`: sabit `video_key` için sunucunun aldığı parça listesi veya tamamlanmış sonuç.
- `pwaVideoChunk`: `index`, `chunk_hash`, `chunk` ile bir parça.
- `pwaVideoComplete`: `operation_key=video_key + '_complete'` ile birleştirme, mevcut video doğrulamaları ve atomik kayıt.

## Doğrulama

```bash
node tests/Integration/pwa-transfer-browser.cjs
node tests/Integration/pwa-chunk-http.cjs
PWA_TRANSFER_MYSQL_TEST=1 /opt/lampp/bin/php tests/Integration/pwa-transfer-mysql.php
```

Tarayıcı testleri gerçek IndexedDB ve ağ istekleriyle çevrimdışı kayıt, yeniden açılış, yanıt kaybı, video devamı, iki sekme, service worker, kilit devri, hesap değişimi, yetki beklemesi, yeniden deneme aralığı, eski kuyruk yükseltmesi ve depolama hatasını kapsar. PHP HTTP testleri gerçek multipart yüklemesi, bozuk parça, SHA-256, süre/format ve yedi günlük temizliği doğrular. MySQL testleri atomik sonuç kaydı, rollback, hesap ayrımı ve eşzamanlı yeniden denemeleri izole şemada çalıştırır.

Gerçek saha bağlantısında ve iOS/Android cihazlarında kullanıcı kabul testi ayrıca yapılmalıdır.
