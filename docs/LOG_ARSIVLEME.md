# Log arşivleme

Önce `sql/2026_10_04_log_archive.sql` bir kez uygulanır, ardından
`/opt/lampp/bin/php cron/archive_logs.php` çalıştırılır.

Günlük görev (sunucu saat diliminde 02:30):

```cron
30 2 * * * cd /opt/lampp/htdocs/ersan_elk && /opt/lampp/bin/php cron/archive_logs.php
```

- Sayfa ziyaretleri: 30 gün.
- Rutin sistem işlemleri ve başarılı AI sorguları: 90 gün.
- Önemli/kritik işlemler, silmeler, giriş/çıkışlar, AUTH_FAIL kayıtları,
  personel girişleri ve AI hataları: 1 yıl.
- Eşik tarihinden kesin olarak eski kayıtlar arşivlenir; eşikteki kayıt korunur.
- Her kaynakta 1000 kayıtlık partiler, çalıştırmada en fazla 100 parti.
  Sonraki çalıştırma kalan kayıtlarla devam eder; tekrar çalıştırma güvenlidir.
- `archived_at` ile mantıksal arşiv uygulanır. Kayıtlar taşınmaz/silinmez ve
  ID'ler değişmez. Disk alanı küçülmez; indeksli aktif sorgular daralır.
- Varsayılan son 30 gün ve son 90 gün yalnızca aktif kayıtları gösterir.
  Tüm kayıtlar arşivi de içerir; Arşiv yalnızca arşivlenmiş kayıtları gösterir.
  Firma ve mevcut log/AI yetkileri bütün kapsamlarda korunur.
- KPI ve 14 günlük grafik kapsam seçiminden bağımsızdır; toplam tüm zamanlardır.
- Eski `data_retention.php` sistem loglarını ve personel girişlerini artık silmez.
  Diğer veri türlerinin mevcut saklama davranışına dokunulmamıştır.
