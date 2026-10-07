# EDM Gelen E-Fatura Senkronizasyonu

Cron, aktif EDM ayarı bulunan firmaların yeni gelen faturalarını her saat başında sorgular.
Yeni faturalar ETTN ile tekilleştirilir ve ilgili firmaya erişimi olan `efatura/gelen-list`
yetkili kullanıcılara uygulama içi bildirim gönderilir.

## Elle çalıştırma

```bash
/opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/efatura_incoming_sync.php
```

## Crontab

`crontab -e` ile aşağıdaki satırı ekleyin:

```cron
0 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/efatura_incoming_sync.php >> /opt/lampp/htdocs/ersan_elk/cron/logs/efatura_incoming_sync.log 2>&1
```

Görev her saatin başında çalışır. Bir önceki çalışma sürüyorsa dosya kilidi nedeniyle ikinci
işlem başlamaz. EDM oluşturulma tarihine göre bugün ve bir önceki gün örtüşmeli sorgulanır;
yerel `(firm_id, ettn)` tekilliği tekrar kayıt ve tekrar bildirimi engeller.
