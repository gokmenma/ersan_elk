# Progress

## Neler Tamamlandı?
- [x] Yakıt/KM kaydı bulunan araçların silinmesi engellendi; başarılı ve engellenen silme denemeleri ayrıntılı audit verisiyle kaydedilir hale getirildi.
- [x] `06 DFD 805` ve `46 ALA 432` araçları için araç silme anıyla eşleşen bağlı verileri geri yükleyen kontrollü SQL scripti hazırlandı.
- [x] Bir personele birden fazla aktif araç zimmetlenebilmesi sağlandı; mobil KM bildiriminde çoklu araç seçimi ve sunucu tarafı zimmet doğrulaması eklendi.
- [x] Araç Takip yakıt filtresi ve yakıt kayıt modalına ikame araçlar eklendi; ana araç sekmesinin mevcut aktif/ikame filtre davranışı korunarak yakıt kayıtlarının ikame plakasıyla seçilip görüntülenebilmesi sağlandı.
- [x] İhbar Yönetimi server-side DataTables kolon filtrelerinde merkezi `mode:value` protokolü desteklenerek metin, tarih ve durum filtreleri çalışır hale getirildi; Durum kolonu API’den bağımsız sabit seçenekli select filtre olarak düzenlendi.
- [x] Kaçak İşlemleri Ekip Özeti için `kacak_islemleri` yetkisine bağlı ayrı `get-kacak-report-table` API aksiyonu tanımlandı; genel puantaj raporu yetkisi bulunmayan kullanıcılardaki 403 ayrıştırıldı.
- [x] Kaçak Ekip Özeti API politikası tekrarlanabilir ayrı SQL scripti olarak eklendi.
- [x] UETS gelen evrak senkronizasyonu doğrudan toplu kayıt yerine önizleme + seçili aktarım akışına dönüştürüldü.
- [x] Daha önce aktarılmış UETS evrakları ve olası manuel mükerrerler seçim modalında ayırt edilir hale getirildi.
- [x] UETS `read` durumundaki eski/indirilmiş gelen evraklar önizlemeye dahil edildi ve listeleme API hataları görünür hale getirildi.
- [x] Seçili aktarım API aksiyonu mevcut `evrak-takip/uets` yetkisine bağlanan tekrarlanabilir SQL politika scriptiyle korundu.
- [x] `memory-bank/` klasör yapısı kuruldu ve `AGENTS.md` sistem kurallarına entegre edildi.
- [x] PTT UETS REST API v1/5.1 entegrasyonu (Model, Servis, Cron, DB Tabloları, Yetkilendirme) tamamlandı.
- [x] UETS / KEP modal form elemanları (`uets_adresi`, `api_environment`, `api_key`, `recipient_uets`, `barcode`, `recipient_ref`) `App\Helper\Form` (`Form::FormFloatInput` & `Form::FormSelect2`) standartlarına refactor edildi.
- [x] Modal Select2 başlatımı `dropdownParent` standardıyla ve dinamik şifre göster/gizle desteğiyle uyarlandı.
- [x] Mobile SPA TaleplerView ekranındaki mükerrer sekme barı kaldırıldı; üst KPI bento kartı üzerinden tekil ve net kategori geçişi sağlandı.
- [x] Mobile SPA TaleplerView aktif kategori kartındaki yazı görünürlüğü ve yüksek kontrastı (`color: #ffffff !important`) sağlandı; üretim derlemesi alındı.

## Neler Kaldı / Devam Edenler?
- [ ] `sql/2026_10_13_restore_deleted_vehicles_06_dfd_805_46_ala_432.sql` kontrollü araç geri yükleme scripti hedef veritabanına uygulanacak.
- [ ] `sql/2026_10_13_add_kacak_report_table_policy.sql` veritabanına uygulanacak.
- [ ] `sql/2026_10_13_uets_selected_import_policy.sql` veritabanına uygulanacak.
- [ ] Kullanıcı ortamında gerçek UETS API canlı/test tebligat işlemleri takibi.
