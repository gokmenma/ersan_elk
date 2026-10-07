# Yetki Sistemi Geçiş Uygulama Sırası

## Ön koşullar

- Veritabanının güncel yedeği alınmalıdır.
- Uygulama kodu SQL geçişlerinden önce yayınlanmalıdır; kod yeni tablolar yokken legacy fallback ile çalışır.
- Geçiş bakım penceresinde ve Superadmin oturumu açıkken uygulanmalıdır.

## SQL sırası

1. `sql/2026_10_06_permission_system_phase_1.sql`
2. `sql/2026_10_06_permission_system_phase_3.sql`
3. `sql/2026_10_06_permission_system_phase_4.sql`
4. `sql/2026_10_06_permission_system_post_validation_fixes.sql`

SQL dosyaları bu sırayla çalıştırılmalıdır. Dört script de tekrar çalıştırılabilir yapıdadır.

## Otomatik doğrulama sonucu

Scriptler, mevcut veritabanı şeması ve ilgili tabloların gerçek verileri ayrı bir geçici veritabanına kopyalanarak iki kez çalıştırıldı.

- Oluşan politika: `713`
- Oluşan aktif kullanıcı–rol ataması: `50`
- Yetkiye bağlanmamış aktif korumalı menü: `0`
- Geçersiz permission foreign key: `0`
- Mükerrer aktif kullanıcı–rol ataması: `0`
- İkinci çalıştırmada politika sayısı: `713`
- İkinci çalıştırmada atama sayısı: `50`

## 6 Ekim 2026 canlı uygulama sonucu

- Geçiş öncesi tablolar `perm_mig_bak_20261006_205757_*` adıyla aynı veritabanında yedeklendi.
- Aşama 1, 3, 4 ve geçiş sonrası düzeltme SQL'leri gerçek veritabanına uygulandı.
- Denetlenen kullanıcı ve rol öznesi: `30`
- Kritik bulgu: `0`
- Uyarı: `0`
- Eski/yeni rol kaynağı uyuşmazlığı: `0`
- Kaynak kodda kullanılan mevcut statik sayfa rotası: `53`
- Politikası eksik mevcut statik sayfa rotası: `0`
- Aktif kullanıcı–rol ataması: `50`

Yedek tablolar gözlem süresi tamamlanana kadar silinmeyecektir.

## Uygulama sonrası kontrol

1. Superadmin ile `kullanici-gruplari/yetki-denetimi` açılır.
2. Firma Sahibi, Normal Kullanıcı ve dar kapsamlı bir rol ayrı ayrı denetlenir.
3. Politika sütununda `Eksik` kaydı kalmadığı doğrulanır.
4. Kullanıcı denetiminde `Rol kaynakları: uyumlu` sonucu aranır.
5. Sidebar görünürlüğü, doğrudan sayfa URL'si ve ilgili API işlemi aynı kullanıcıyla karşılaştırılır.
6. En az bir rol ekleme ve kaldırma işlemi yapılarak hem rol matrisi hem kullanıcı denetimi tekrar kontrol edilir.

## Legacy kaldırma koşulu

`users.roles`, eski menü–yetki tahminleri ve `FIND_IN_SET` fallback'leri şu koşulların tamamı sağlanmadan kaldırılmayacaktır:

- Tüm kullanıcıların eski ve yeni rol kaynakları eşleşiyor.
- Aktif korumalı menülerde eksik `permission_id` yok.
- Aktif sayfa ve API politikalarında geçersiz `permission_id` yok.
- Örnek kullanıcı regresyonları tamamlandı.
- En az bir üretim döngüsünde yeni atama tablosundan sorunsuz okuma yapıldığı gözlendi.
