# Progress
- [x] Aşama 2 temel sonuç sözleşmesi ve salt okunur karşılaştırma okuyucusu eklendi; kayıtlı kanallar korunuyor, belirsiz parasal alanlar null taşınıyor.

- [x] Bordro aşamalı geçişinin ilk aşaması: salt okunur snapshot/ödeme denetimi, geçiş riskleri belgesi ve 3 davranış testi eklendi (7 doğrulama başarılı). Hesap kayıtları değiştirilmedi.
- [x] Aşama 2: yeni Model hesaplarının sürümlü ortak gösterim sonucu ve ödeme denetimine bağlanması.
- [x] Aşama 3: liste–detay–Excel ortak okuyucusunun yeni sonuç sözleşmesine bağlanması; eski snapshot uyumluluğu korunuyor.
- [x] Aşama 4: yeni sürümlü kayıt/kapanış denetimi ve manuel dağıtım tutarlılığı; 88 test, 652 doğrulama başarılı.
- [ ] Hedef sunucuda dönem bazlı canlı mutabakat ve yetkili yeniden hesaplama doğrulaması.

- [x] Personel kesinti düzenleme ekranında sürekli kesinti başlangıç/bitiş tarihlerinin kaydedilmemesine yol açan `GG.AA.YYYY` → MySQL `DATE` biçim uyuşmazlığı giderildi; Flatpickr görünür/gerçek input senkronizasyonu ve sunucu tarafı tarih normalizasyonu eklendi.

- [x] Bordro kapanışına hukuki snapshot/mutabakat kontrolü eklendi; dönemsel maaş kaynağı ve ödeme kanalı tutarsızlıkları zorlanamaz biçimde kapanışı engelliyor, yeni snapshot kaynakları kaydediliyor ve manuel/Excel dağıtımları snapshot ile birlikte atomik güncelleniyor.
- [x] Bordro kapanışındaki çoklu mutabakat hataları personel bazlı, numaralı ve kaydırılabilir kullanıcı dostu kontrol penceresine dönüştürüldü.
- [x] `bordro_kapanis_arsiv` ve kişi doküm tabloları oluşturuldu; SHA-256 mühürlü, önceki hash'e bağlı sürüm zinciri ve atomik arşivle-kapat akışı devreye alındı.
- [x] `bordro/list` geçmiş dönem gösteriminde kayıtlı banka/elden dağılımını yeniden hesaplayan gösterim tavanı kaldırıldı; kapanmış/ödenmiş bordronun kayıtlı ödeme kanalları aynen gösterilir hale getirildi ve regresyon testi eklendi.
- [x] Hesaplanmış/kapanmış bordroların kayıtlı hakediş, kesinti ve ödeme kanallarının gösterimde değiştirilemeyeceği snapshot ilkesi `AGENTS.md` ve bordro hesaplama kuralları dokümanında kalıcı proje kuralı yapıldı.

- [x] Mobile SPA (`mobile-spa`): Soft Bento & Claymorphic UI mimarisi tüm mobil uygulama için merkezi varsayılan tasarım standardı yapıldı.
  - Merkezi `src/design-system.css` kütüphanesi oluşturuldu: `--ds-canvas: #e6eaef` (mat gri/arduvaz), `--ds-surface: #ffffff` (saf beyaz bento kart), `--ds-surface-dark: #191d24` (obsidian bento kart), 28px kart kavisleri, 3D yumuşak gölgeler, segmentli barlar, metrik ızgaraları, hap butonlar ve modallar merkezi tokenlara bağlandı.
  - Tek noktadan yönetim (Single Source of Truth) sağlandı: Gelecekte bir tasarım değişikliği yapıldığında sadece `design-system.css` üzerinden tüm sayfaların anında güncellenmesi garanti altına alındı.
  - Ekran Görüntüsü & Uyumsuzluk Temizliği: Eski `.personnel-hero`, `.form-topbar` ve `.workspace-header` lacivert/mavi degrade dalgalı başlıkları `styles.css` içinden tamamen temizlendi.
  - Sayfa Modernizasyonları: `PersonnelView.vue`, `PersonnelDetailView.vue`, `PersonnelSectionView.vue`, `PersonnelFormView.vue`, `HomeView.vue`, `TaleplerView.vue`, `ReportsView.vue`, `TasksView.vue`, `ProfileView.vue`, `KasaView.vue`, `FaturaView.vue` ve `TopBar.vue` merkezi Soft Bento standartlarına dönüştürüldü.
  - `TopBar.vue` `#actions` slotu ile tüm sayfaların özel aksiyon butonları tek tip dairesel clay buton yapısına bağlandı.
  - `AGENTS.md` (Madde 10) tasarım standardı kural olarak tescillendi.
  - `npm run build` ile üretim paketi başarıyla derlendi.

  - Backend API (`mobile-spa/public/api/kasa.php` & `dist/api/kasa.php`) oluşturuldu; session, Gate yetkileri (`gelir_gider_takibi`, `gelir-gider/list`, `kasa/list`), CSRF doğrulaması, Model katmanı (`GelirGiderModel`, `SystemLogModel`) ve `sql_gelir_gider` view entegrasyonu sağlandı.
  - Frontend servisi (`mobile-spa/src/services/kasa.js`) ve görünüm bileşeni (`mobile-spa/src/views/KasaView.vue`) oluşturuldu (3 KPI özet kartı, son 4 yıl & ay ve tip filtreleri, canlı arama, gelir/gider listesi, yeni/düzenle bottom sheet form modalı, şık makbuz/detay modalı, tüm hareketler modalı, güvenli silme diyaloğu, FAB butonu).
  - Router (`/kasa`, `/gelir-gider`, `/kasa-yonetimi`) ve `BottomNav.vue` "Daha Fazla" menüsündeki "Kasa Yönetimi" butonu doğrudan SPA içi `to: '/kasa'` rotasına bağlandı.
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı.


- [x] Mobile SPA (`mobile-spa`): `@mobile` içerisindeki tüm fatura yetenekleri (`efatura.php`, `efatura-giden.php`, `efatura-gelen.php`, `efatura-taslak.php`, `efatura-olustur.php`) Glacier Alpine Design System standartlarında SPA mimarisine aktarıldı.
  - Backend API (`mobile-spa/public/api/fatura.php` & `dist/api/fatura.php`) oluşturuldu; session, Gate yetkileri, CSRF doğrulaması ve Model katmanı ile güvenliği sağlandı.
  - Frontend servisi (`mobile-spa/src/services/fatura.js`) ve görünüm bileşeni (`mobile-spa/src/views/FaturaView.vue`) oluşturuldu (Özet & KDV, Giden, Gelen, Taslaklar, 4 adımlı fatura sihirbazı, tam ekran HTML/PDF önizleme, ticari yanıt verme sheet'i).
  - Fatura kartları & satırları Glacier Alpine Design System standartlarında yeniden tasarlandı: 3 katmanlı (.fatura-row-card), 2 satır sınırlamalı müşteri unvanı, renkli mono no tag, tarih hapı, canlı durum noktalı rozet, 16px bold sembollü tutar ve "Fatura Gör" aksiyon hapı eklendi.
  - Router (`/fatura` & `/efatura`) ve `BottomNav.vue` "Daha Fazla" menüsündeki "E-Fatura & Finans" butonu SPA rotasına bağlandı.
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı.
- [x] Personel PWA Kaçak sayfasında onaylanan aktif tutanaklara ekip üyeleri için fotoğraf/video/açıklama ekleme akışı tamamlandı; açıklama güvenilir kuyruk üzerinden sunucuya taşınıp mevcut tutanak açıklamasına ekleniyor ve API sahiplik/onay/aktiflik kontrollerini uyguluyor.

- [x] Personel PWA İzin Modülü (`page=izin`): 3 günden uzun izinlerin en az 2 hafta önceden bildirilmesi ve 20 gün sınırı kuralı mazeret izninden kaldırılarak yalnızca yıllık izin türüyle sınırlandırıldı. Hatırlatma kutusu arayüzde yalnızca yıllık izin seçildiğinde görünecek şekilde dinamikleştirildi.
- [x] Giden Evrak şablonlarında yeni kayıt sonrası bir sonraki kaydın önceki şablonu güncellemesine yol açan kalıcı gizli ID sorunu giderildi; oluşturma/güncelleme kipleri istemci ve sunucuda ayrıldı, seçim ve düzenleme durumu görünür hale getirildi.
- [x] Evrak Takip listesindeki ve Giden Evrak formundaki ortak PDF önizleme hatası, `pdf.php` içinde eksik kalan `EvrakTakipModel` başlatması geri eklenerek giderildi.
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
- [x] Mobil SPA genelinde Glassmorphism (buzlu cam) deseni uygulandı: zemin ambient mesh gradient küreleri, `backdrop-filter: blur(20px-28px) saturate(180%-200%)`, 1px beyaz ışık kenarlığı, bento KPI kartı, durum pilleri, arama alanı, liste kartları ve alt dock/çekmece cam dokusu tamamlandı; üretim derlemesi alındı.

## Neler Kaldı / Devam Edenler?
- [ ] `sql/2026_10_13_restore_deleted_vehicles_06_dfd_805_46_ala_432.sql` kontrollü araç geri yükleme scripti hedef veritabanına uygulanacak.
- [ ] `sql/2026_10_13_add_kacak_report_table_policy.sql` veritabanına uygulanacak.
- [ ] `sql/2026_10_13_uets_selected_import_policy.sql` veritabanına uygulanacak.
- [ ] Kullanıcı ortamında gerçek UETS API canlı/test tebligat işlemleri takibi.

## 2026-10-14
- [x] Muhasebe Excel'i tek ve sade `Mutabakat` sayfasına dönüştürüldü.
- [x] Fiilî gün ve rapor günü TC kolonundan sonra eklendi.
- [x] Seçili personeller için toplu `Bordro Kontrol` Excel dökümü eklendi.
- [x] Bordro Kontrol indirmesindeki PhpSpreadsheet gridline metot uyumsuzluğu giderildi.
- [x] Bordro Kontrol dosyasındaki puantaj/sayaç ve kaçak kontrol hareketleri özet satırlarda gruplandı.
- [x] Bordro Kontrol yemek/eş yardımı ve yuvarlama farkı yerleşimi detay modalıyla aynı hale getirildi.
- [x] Bordro Kontrol başlığı kaldırılarak personel bilgileri koyu ve okunaklı üst satıra taşındı.
- [x] Bordro Kontrol kartlarının dış sınırları daha belirgin hale getirildi.
- [x] Hafta içi nöbet muhasebe hedef neti Model katmanında ortaklaştırılarak Bordro Kontrol ve Mutabakat Excel'lerinde `Fazla Mesai Ücreti (Net)` olarak kullanıldı.
- [x] Bordro detayındaki Fazla Mesai satırı ve Banka Ödemesi Detayı hafta içi nöbet resmî hedef netiyle eşlendi.
- [x] Prim usulü personelde HTÇ net gösterimi ve yardımsız profilde banka detay kırılımı geri getirildi.
- [x] Fazla mesai/hafta içi nöbet ile RTÇ/HTÇ yemek yardımı havuzundan ayrıştırıldı; resmî hedef banka, hedef üstü hakediş elden dağıtılır hale getirildi.
- [x] Teknik `Eski snapshot dağıtım farkı` satırı kaldırıldı; geçmiş bordrolarda kayıtlı banka kalemleri, yeni hedeflerle ekranda değiştirilmeden mutabık gösteriliyor.
- [x] HTÇ'nin hafta içi nöbet satırından sonra kalan banka boşluğuna kırpılması giderildi; ek çalışmanın resmî banka neti snapshot detayında ayrı saklanıp elden/banka dağımında kullanılıyor.
- [x] Hafta içi nöbetin nihai resmî banka filtresinden düşmesine yol açan eksik tür kontrolü tamamlandı; detay okuyucusu `resmi_banka_net` alanını öncelikli kullanıyor.
- [x] Kompakt bordro detayında Yemek Yardımı açılımı kaldırıldı; Fazla Mesai ve HTÇ satırlarına saat/gün ve birim net formülü içeren açılır detay eklendi.
- [x] Sözleşme hakedişinin iç bileşeni olan teknik `Sözleşme Farkı` satırı kompakt/yeni detay ve banka açılımlarından kaldırıldı.
- [x] Avans satırında yanlış icra açıklamasının gösterilmesi engellendi; otomatik avans açıklaması kaynak kayıttan yenilenir hale getirildi.
- [x] Bordro liste ve detay personel kesintileri aynı snapshot kalemlerine bağlandı; eski kesinti kolonundaki yasal/brüte tamamlama farkı ayıklandı.
- [x] HTÇ'nin aynı tutarlı fazla mesai satırı nedeniyle resmî alacaktan düşmesi engellendi; hedef net ayrı snapshot kalemi ve banka matrahı alt sınırı yapıldı.
- [x] Açıklaması boş avans satırına ilk icra açıklamasını kopyalayan detay ekranı fallback'i kaldırıldı.
- [x] `mesai` kodlu fazla çalışma resmî banka kalemine dahil edilerek HTÇ ile ayrı ayrı gösterilmesi sağlandı.
- [x] Otomatik ödeme dağılımında kaybolan pozitif bakiye elden kanalına tamamlandı; brüte tamamlama vergilerinin personel kesintisi alanına yazılması engellendi.
- [x] Banka matrahına dahil ek çalışmaların resmî net üstü payı yeniden maaşa dahil yemek tavanını dolduracak şekilde hesaplamaya bağlandı; tavan üstü bakiye elden bırakıldı.
