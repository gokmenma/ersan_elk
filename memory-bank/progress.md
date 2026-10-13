# Progress

- [x] Mobile SPA (`mobile-spa`): `@mobile` içerisindeki tüm fatura yetenekleri (`efatura.php`, `efatura-giden.php`, `efatura-gelen.php`, `efatura-taslak.php`, `efatura-olustur.php`) Glacier Alpine Design System standartlarında SPA mimarisine aktarıldı.
  - Backend API (`mobile-spa/public/api/fatura.php`) oluşturuldu; session, Gate yetkileri, CSRF doğrulaması ve Model katmanı ile güvenliği sağlandı.
  - Frontend servisi (`mobile-spa/src/services/fatura.js`) ve görünüm bileşeni (`mobile-spa/src/views/FaturaView.vue`) oluşturuldu (Özet & KDV, Giden, Gelen, Taslaklar, 4 adımlı fatura sihirbazı, tam ekran HTML/PDF önizleme, ticari yanıt verme sheet'i).
  - Router (`/fatura` & `/efatura`) ve `BottomNav.vue` "Daha Fazla" menüsündeki "E-Fatura & Finans" butonu SPA rotasına bağlandı.
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı.
- [x] Personel PWA Kaçak sayfasında onaylanan aktif tutanaklara ekip üyeleri için fotoğraf/video/açıklama ekleme akışı tamamlandı; açıklama güvenilir kuyruk üzerinden sunucuya taşınıp mevcut tutanak açıklamasına ekleniyor ve API sahiplik/onay/aktiflik kontrollerini uyguluyor.

## Neler Tamamlandı?
- [x] Bordro Detay modalında sözleşme farkı ve maaşa dahil ek kazanç dağıtım mantığı düzeltildi; resmî olmayan primlerin yemek tavanı dışındaki kısmı elden ödemeye aktarıldı, yapay sözleşme farkı kaldırıldı.
- [x] Bordro Detay modalındaki tüm kartların sınırları (border) belirginleştirildi (`1.5px solid #cbd5e1`, macOS Dark uyumlu).
- [x] Kesintiler kartında (3. kart) "Diğer banka kesintileri" yerine gerçek kesinti adı (örn. "Malzeme Zarar - Ziyan") ve detay açıklaması gösterildi.
- [x] Ödenecek Net Maaş kartının sağ üst köşesine aşağı çapraz ok butonu (`↘`) eklendi; tıklandığında 3 kolon üstte, Net Maaş kartı altta tam satır (`full-width`) genişletilerek kalıcı `localStorage` durum yönetimi sağlandı.
- [x] Bordro Kesintiler kartı ve banka ödeme tooltip'inde kesinti açıklaması güvenli HTML çıktısıyla kalem adının altında gösterilir hale getirildi.
- [x] Kayıtlı bordro detayında banka kanal seçiminin resmî alacak üyeliği sayılması engellendi; resmî olmayan hafta sonu nöbeti ve zaten ana yemek satırında bulunan teknik yemek dengeleme kaydı ek kalemlerden çıkarıldı ve regresyon testleri eklendi.
- [x] Sabit maaşı olmayan prim usulü personelin eski detayında sıfır günlük/sıfır HTÇ ve negatif sıfır kesinti gösterimleri kaldırıldı; dönem tabanı Puantaj / Prim Hakedişi olarak doğru etiketlendi.
- [x] Eski bordro detayındaki Resmî Alacak > Yemek Yardımı satırına sözleşme payı ve yemek havuzuna aktarılan ek kazançları gösteren mutabık açılır kaynak kırılımı eklendi.
- [x] Eski bordro detayında ek ödeme teknik kodları parametre etiketleriyle değiştirildi; ana ve satır içi akordeonların kapanmama sorunu açık Bootstrap kontrolü ve kapalı collapse satırlarını ezmeyen eşit-yükseklik CSS seçicisiyle giderildi.
- [x] Maaşa dahil yemekli bordrolarda araç kirası gibi resmî olmayan kalemlerin kayıt sırası nedeniyle parçalanıp doğrudan resmî banka eki gibi gösterilmesi giderildi; doğrudan banka payı resmî kalemlere öncelikli yazılır hale getirildi.
- [x] Eski bordro detay penceresinde kalıcı Alt Alta / Yan Yana görünüm ve bağımsız akordeon durumu eklendi; yan yana görünümde Net Ödeme kolonu daraltıldı ve açık/koyu temada hafif yeşil yüzeyle diğer kartlardan ayrıldı.
- [x] Maaşa dahil yardım sırası yemek → kalan bakiyeden eş yardımı olarak düzeltildi; çoklu çalışma aralıklarında yalnız son aralığı sayan puantaj X günü hatası giderildi ve HTÇ/RTÇ yemek gününden çıkarıldı.
- [x] Bordro detay modalında asgari ücret satırının kesinti sonrası banka bakiyesine kırpılması giderildi; snapshot kesintileri kalem bazında açıldı, kesinti öncesi ara toplam eklendi, net maaşlı Normal Kazançlar asgari tabana çekildi ve net fazla çalışma/brüt hover ayrımı tamamlandı.
- [x] Bordro detay footer'ındaki Brüt Toplamı kutusuna toplam hakedişe nasıl ulaşıldığını sözleşme günü, sözleşme günlüğü, tam HTÇ hakedişi ve diğer kazançlarla açıklayan mutabakat tooltip'i eklendi.
- [x] Zimmet listesinde aktif mükerrer seri numaraları satır uyarısıyla görünür hale getirildi; ekrandaki filtreleri koruyan, seri ve mükerrer uyarı sütunlarını içeren yetki kontrollü Excel dışa aktarma eklendi.
- [x] Personel PWA’da 3 günden uzun izinler için 14 günlük ön bildirim zorunluluğu yalnızca yıllık izne uygulandı; evlilik ve diğer yıllık olmayan izinler istemci ve API tarafında bu kısıttan çıkarıldı.
- [x] Demirbaş/Zimmet sayaç zimmetinde istemci tarafı çift gönderim kilidi ve Model katmanında satır kilitli atomik stok/mükerrer kontrolü eklendi; sayaç API kayıtları ortak Model akışına taşındı.
- [x] Geçmiş mükerrer sayaç zimmet temizliği tek bir hedef seri numarasıyla sınırlandırıldı; ortak/geçersiz seri değerlerinin toplu yanlış eşleşmesi önlendi ve Model kontrolünde placeholder seri değerleri kapsam dışına alındı.
- [x] Aynı seri numarasının meşru geçmiş tekrarlarını çift gönderimlerden ayıran, 120 saniyelik pencereye sahip salt-okunur mükerrer sayaç zimmet tespit sorgusu eklendi.
- [x] Yakıt/KM kaydı bulunan araçların silinmesi engellendi; başarılı ve engellenen silme denemeleri ayrıntılı audit verisiyle kaydedilir hale getirildi.
- [x] `06 DFD 805` ve `46 ALA 432` araçları için araç silme anıyla eşleşen bağlı verileri geri yükleyen kontrollü SQL scripti hazırlandı.
- [x] Bir personele birden fazla aktif araç zimmetlenebilmesi sağlandı; mobil KM bildiriminde çoklu araç seçimi ve sunucu tarafı zimmet doğrulaması eklendi.
- [x] Araç Takip yakıt filtresi ve yakıt kayıt modalına ikame araçlar eklendi; ana araç sekmesinin mevcut aktif/ikame filtre davranışı korunarak yakıt kayıtlarının ikame plakasıyla seçilip görüntülenebilmesi sağlandı.
- [x] İhbar Yönetimi server-side DataTables kolon filtrelerinde merkezi `mode:value` protokolü desteklenerek metin, tarih ve durum filtreleri çalışır hale getirildi; Durum select seçenekleri tüm aktif kayıtlarda bulunan gerçek durumlarla dinamik hale getirildi.
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
- [x] Mobile SPA TaleplerView üst KPI kartındaki global `min-height: 210px` zorlaması kaldırılarak kart yüksekliği içeriğe tam uyumlu, kompakt ve minimal hale getirildi.
- [x] Mobile SPA TaleplerView sıralama butonu metinsiz, kompakt dairesel ikon butonuna (`ArrowUpDown`) dönüştürüldü; üretim derlemesi alındı.

## Neler Kaldı / Devam Edenler?
- [ ] `sql/2026_10_13_restore_deleted_vehicles_06_dfd_805_46_ala_432.sql` kontrollü araç geri yükleme scripti hedef veritabanına uygulanacak.
- [ ] `sql/2026_10_13_add_kacak_report_table_policy.sql` veritabanına uygulanacak.
- [ ] `sql/2026_10_13_uets_selected_import_policy.sql` veritabanına uygulanacak.
- [ ] Kullanıcı ortamında gerçek UETS API canlı/test tebligat işlemleri takibi.
