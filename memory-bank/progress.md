# Progress
- [x] Mobil SPA (`mobile-spa`): Ana navbar "Diğer" sekmesi standardizasyonu ve Talepler sayfası üst çubuk düzenlemesi (2026-10-15):
  - **Ana Navbarda "Diğer" İkonu Standardizasyonu**: Eski `styles.css` içindeki FAB yükselen yuvarlak buton kuralları temizlendi (`.nav-link--center`). `BottomNav.vue` içinde "Diğer" butonu diğer 4 sekme (Ana Sayfa, Personel, Raporlar, Talepler) ile aynı `nav-link` sınıfına, 21px `LayoutGrid` ikonuna ve 54px yüksekliğe getirildi. `design-system.css` içinde `.bottom-nav .nav-icon` için `position: static !important; top: 0 !important;` ile 5 sekmenin de aynı taban çizgisine (baseline) tam oturması sağlandı.
  - **Talepler Sayfasında Üst "Tümü" Butonunun Kaldırılması**: `TaleplerView.vue` sayfasındaki durum pilleri satırından gereksiz `.pill-category-badge` ("Tümü ▾") kaldırıldı; `Bekleyenler`, `İşlem Görenler` ve `Silinenler` butonları satıra tam simetrik dağıtıldı (`flex: 1`).
  - **Derleme**: `npm run build` ile Vite derlemesi 0 hata ile tamamlandı.
- [x] Mobil SPA (`mobile-spa`): Tüm sayfalarda başlık & açıklamanın geri butonu yanına taşınması ve Talepler modülünün alan verimliliği optimizasyonu (2026-10-15):
  - **Tüm Sayfalar İçin Üst Başlık Standardı (`TopBar.vue`, `design-system.css`)**: Geri butonu bulunan tüm sayfalarda sayfa başlığı (`h1`) ve açıklaması (`p`), geri butonunun hemen yanında tek satır/kompakt blok haline getirildi; ayrık büyük `.clay-page-header` alanı kaldırılarak dikeyde ~80-100px ferahlık sağlandı.
  - **Talepler "SÜREÇ YÖNETİMİ" Kaldırıldı**: `eyebrow="SÜREÇ YÖNETİMİ"` kaldırıldı.
  - **Durum Filtreleri En Üstte (`TaleplerView.vue`)**: Bekleyenler, İşlem Görenler, Silinenler butonları başlığın hemen altına konumlandırıldı.
  - **Arama ve Sıralama Yan Yana**: Arama kutusu ile dairesel sıralama butonu yan yana hizalandı (`.talepler-search-sort-row`).
  - **Navbar İçi Talep Türleri Nav Menüsü (`BottomNav.vue`, `requests.js`, `design-system.css`)**: Kaba KPI kartı yerine alt navigasyon çubuğu içine tam 5 sütunlu nav menü formatı uygulandı: `Tümü` (`Sparkles`), `Avans` (`Wallet`), `İzin` (`CalendarDays`), `Genel` (`Layers`) ve `Kapat` (`X`). Her sekmede dikey ikon ve metin hiyerarşisi, ikon köşelerinde dinamik sayaç rozetleri (`.nav-bubble-badge`) ve ana navbar ile birebir aynı aktif beyaz hap seçimi (`.nav-link.is-active`) sağlandı.
  - **Derleme**: `npm run build` ile hatasız üretim çıktısı alındı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): Araç Takip modülü (`AracView.vue`, `arac.js`, `public/api/arac.php`) UI/UX Pro Max ve Soft Bento & Claymorphic UI standartlarında sıfırdan geliştirildi (2026-10-15):
  - **Backend API**: `mobile-spa/public/api/arac.php` ve `dist/api/arac.php` oluşturuldu. Gate yetkilendirmesi (`arac_takip_yonetim`, `arac-takip/list`, `vehicles`), CSRF token denetimi, PDO parametrik binding, Security::encrypt / Security::decrypt ve AES-256-GCM şifreli ruhsat yükleme/stream indirme entegrasyonu sağlandı.
  - **Frontend Servis**: `mobile-spa/src/services/arac.js` API istemcisi oluşturuldu.
  - **Bento & Claymorphic UI Kart Yenilemesi**:
    - **KM Yolculuk Kartı (`.km-journey-card`)**: Üstte TR plaka rozeti (`.tr-plate-badge`), canlı mor gradient `+KM` fark hapı (`.km-diff-pill`) ve tarih rozeti; ortada Başlangıç ve Bitiş sayaçlarını yön okuyla bağlayan ferah `Journey Track` çizelgesi (`.km-journey-box`); altta dairesel avatarlı sürücü künyesi ve görev notu çipi (`.journey-task-chip`).
    - **Ana Araç Kartı (`.vehicle-master-card`)**: Üstte mavi TR şeritli plaka ve durum renk noktasıyla canlı durum hapı; ortada güncel sayaç ve zimmetli personel 2'li Bento ızgarası; altta muayene, sigorta, kasko için akıllı renk kodlu (kırmızı/sarı/yeşil) 3'lü hap rozetleri ve 44px dokunmatik aksiyon çubuğu.
    - **Araç Detay Kartı (Modal / Bottom Sheet)**: Üstte drag tutamacı, kimlik hero kutusu (TR plaka rozeti, canlı durum hapı, marka/model ve 4'lü model/renk/tip/ikame özellikleri), 2x2 Bento metrik ızgarası (sayaç, yakıt tipi, şase no, mülkiyet), zimmetli personel için iki harfli gradyan avatar ve departman künyesi, 3'lü evrak takip takvimi (kalan gün rozetleri), AES-256 şifreli güvenli ruhsat kartı (ISO 27001) ve 48px dokunmatik aksiyon butonları.
    - **Merkezi Layout Micro-Utilities (`src/design-system.css`)**: SPA genelinde grid, flex, gap, padding, margin, text ve font yardımcı sınıfları eklenerek tüm modalların ve formların hizalama sorunları giderildi.
    - **Dönem & Ay Seçici Düzeltmesi (Mobil "Ayı Seçin" Tap Hijacking Önlemi)**: Sayfada gizli/şeffaf duran ve `.relative` eksikliği nedeniyle ekranın farklı yerlerine taşarak dokunmalarda yerel tarayıcının "Ayı seçin" tekerleğini açan şeffaf `<input type="month">` overlay yapısı tamamen temizlendi. Yerine dokunmatik Bento Dönem & Ay Seçici Bottom Sheet (`isMonthModalOpen`, 12 ay ızgarası, yıl navigasyonu, hızlı kısayollar) getirildi.
    - **Yakıt, Zimmet ve Servis Kartları**: 2'li Bento blokları, personel avatarları ve durum rozetleriyle aynı yüksek okunabilirlik standartlarına kavuşturuldu.
    - **Hero & Filtreler**: Koyu Obsidian Canlı Filo Bento Hero kartı (`.bento-card--dark`), Segmented Progress Track, 4'lü metrik ızgarası, kritik evrak uyarı banner'ı (`.announcement-card`), 5 sekmeli yapı, dinamik periyot seçici ve 10 adet ergonomik Bottom Sheet / modal penceresi.
  - **Entegrasyon & Routing**: `router.js` içerisine `/arac-takip` rotası tanımlandı, `BottomNav.vue` "Daha Fazla" menüsü ve `HomeView.vue` hızlı aksiyonları SPA rotasına bağlandı.
  - **Derleme**: `npm run build` ile hatasız üretim çıktısı alındı.
- [x] Sistem Logları (`views/logs/list.php`) Menü ve Yetkilendirme Çalışması (2026-10-15):
  - `views/logs/list.php` sayfası için `menus` tablosuna `menu_name = 'Sistem Logları'`, `menu_link = 'logs/list'`, `parent_id = 0`, `group_name = 'Yönetim'`, `menu_icon = 'shield'`, `menu_order = 84` menü kaydı eklendi.
  - `permissions` tablosunda `permission_key = 'log_kayitlari'` kaydı `group_name = 'Yönetim'` ve `name = 'Sistem Logları'` olarak güncellendi; `permission_id` menüye bağlandı.
  - `user_role_permissions` tablosunda Süper Admin ve Firma Sahibi rollerine `NOT EXISTS` kontrolüyle yetki tanımlaması sağlandı.
  - `permission_policies` tablosunda sayfa (`logs/list` GET) ve API (`logs/api` tüm aksiyonlar) kuralları eksiksiz bağlandı.
  - Menü önbellekleri temizlendi; Süper Admin, Firma Sahibi ve yetkisiz roller ile Yetki Kataloğu ve Yetki Grubu Düzenleme ekranlarındaki görünürlük test edilerek doğrulandı.
  - SQL scripti: `sql/2026_10_15_add_sistem_loglari_menu_and_permissions.sql`.
- [x] Bordro — Elden Kesintilerin Elden Hakedişi Aşan Kısmının Bankadan Kesilmesi ve Muhasebe Exceli İyileştirmesi (2026-10-14):
  - Elden kanallı kesintilerde (`banka_matrahina_ekle = 0`) kesinti öncelikle personelin elden hakedişinin maksimum tutarı kadar elden tutardan düşülüyor; elden hakedişin yetmediği bakiye kesinti ise bankadan mahsup ediliyor.
  - Muhasebe Excel çıktısında (`excel-yemek-export.php` ve Mutabakat sayfası): Bankadan kesilen bu bakiye yeşil resmî hakediş sütunlarından (asgari ücret / yemek) düşürülmeyip kırmızı Diğer Kesintiler sütununda (`muhasebe_banka_diger_kesinti`) gösteriliyor.
  - `views/bordro/list.php` modal formuna "Kesinti Kanalı" (Banka / Elden) radyo buton grubu eklendi; `bordro.js` üzerinden kesinti türüne göre parametrik otomatik eşleştirme ve liste rozetleri entegre edildi.
  - Ömer Faruk Dağ (Personel ID: 236, Dönem: 26) ve genel senaryolar doğrulandı; 94 bordro testinin tamamı başarıyla geçti.
  - `sql/2026_10_14_elden_kesinti_ve_omer_faruk_dag_guncelleme.sql`, `docs/BORDRO_HESAPLAMA_KURALLARI.md` ve `AGENTS.md` Kural 5 güncellendi.
- [x] Mobile SPA (`mobile-spa`): `@mobile` içerisindeki Bordro modülü (`mobile/pages/bordro.php` & `mobile/api/bordro.php`) Soft Bento & Claymorphic UI mimarisine uyarlandı.
  - Backend API: `mobile-spa/public/api/bordro.php` ve `dist/api/bordro.php` oluşturuldu. Session, Gate (`bordro/list`, `bordro/parametreler`), CSRF token denetimi, PDO ve Model katmanı (`BordroDonemModel`, `BordroPersonelModel`, `BordroParametreModel`) ile merkezi `views/bordro/api.php` entegrasyonu sağlandı.
  - Frontend Servisi: `mobile-spa/src/services/bordro.js` oluşturuldu (`init`, `getDetail`, `calculate`, `togglePeriod`, `distribute`, `resetAllPayments`, `resetSinglePayment`, `addAdjustment`).
  - Görünüm Bileşeni: `mobile-spa/src/views/PayrollView.vue` geliştirildi (TopBar aksiyonları, Bento dönem seçici ve rozeti, Obsidian hero net tutar kartı, 3'lü Bento KPI ızgarası, canlı personel arama ve 5 sekmeli durum filtreleme, 2x3 kırılım ızgaralı personel kartları, bordro detay sheet'i, ödeme dağıtımı modalı, ek gelir/kesinti modalları, diğer işlemler sheet'i ve yerleşik Bento onay penceresi).
  - Router & Navigasyon: `/bordro` ve `/maas` rotaları tanımlandı; `BottomNav.vue` menüsündeki "Bordro" öğesi `to: '/bordro'` olarak bağlandı.
  - Görünüm & Tipografi Düzeltmeleri: Seçili dönem adının `<select>` kutusunda görünmesi (`v-model`, API token senkronizasyonu), hero net tutar font boyutu ve taşma düzeltmesi (`clamp(22px, 6.2vw, 28px)`), bordro detay modalında (sheet) net tutar taşmasının giderilmesi, kaba personel künye kartının temizlenmesi ve resmî hesaplama kalemlerinin Soft Bento tasarım sistemine tam uyumlu hale getirilmesi tamamlandı.
  - Bordro Detay Modalı İki Sekme & Ultra-Kompakt Özet Bandı (Kullanılabilirlik ve Alan Tasarrufu):
    - Masaüstündeki (`views/bordro/list.php`) gibi iki görünüm eklendi: "Karşılaştırmalı Hesap" (`get-detail-old`: Sözleşme Alacağı, Resmî Alacak, Kesintiler) ve "Resmî Bordro Dökümü" (`get-detail`: Kazançlar, Fazla Çalışmalar, Yasal Kesintiler).
    - `detailCache` desteğiyle sekmeler arası anında (0ms) geçiş sağlandı.
    - Ekranın %60'ını işgal eden hantal Obsidian hero ve kanal kartları yerine tek satırlık **Ultra-Kompakt Bento Özet Bandı** geliştirildi; Net Maaş (`₺53.405,00`), Banka (`₺35.875,50`), Elden (`₺17.529,50`) tek bir zarif bantta sunuldu, istendiğinde chevron ile detaylı kırılım açılabilir hale getirildi.
    - Modal yüksekliği `height: 94vh; max-height: 94vh;` yapılarak ekranın %80'i doğrudan bordro döküm tablosuna ayrıldı (~210px dikey alan kazanıldı).
  - Test & Derleme: `design-smoke.cjs` 320/390/480px ekran boyutlarında 27 kontrol başarıyla (`PASS`) tamamlandı; `npm run build` ile hatasız üretim çıktısı alındı.
- [x] Bordro detayındaki elden kanallı malzeme kesintisinin gizlenmesi giderildi; kart tüm personel kesintilerini gösteriyor.
- [x] Prim usulünde banka işaretli yol/ek kazancın son yemek tavanından düşmesi düzeltildi; yemek dağılımının toplam hakedişi ikinci kez artırması önlendi. İlgili 43 test geçti.
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
- [x] Seçilen bir veya birden fazla kapalı dönemi mühürlü/mühürsüz ayrımıyla Excel + SHA256SUMS içeren ZIP olarak dışa aktaran Geçmiş Bordro Arşivi aksiyonu eklendi.
- [x] Maaş Bordroları Excel sayfası tam mali/yasal alanlarla genişletildi; ZIP'e kayıpsız kişi snapshot JSON'ları ve `bordro-export-recovery-v1` manifesti eklendi.
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

## 2026-10-14 — Mobil SPA tasarım yenilemesi
- [x] Referans görsel incelendi; ana sayfa sade mobil uygulama düzenine taşındı.
- [x] Ortak üst bar, yetkiye göre sıkışan koyu dock ve erişilebilir Daha Fazla alt paneli yenilendi.
- [x] Giriş/firma seçimi ortak tasarıma uyarlandı; 320px giriş formu iç taşması düzeltildi.
- [x] Mevcut modüllerin temel/durum paletleri merkezi `--ds-*` tokenlarına bağlandı; dokunma alanları, 16px form değerleri ve safe-area payları güncellendi.
- [x] API mock'larıyla 27 rota/genişlik kontrolü, izinli/izinsiz gezinme, alt panel odağı ve giriş kontrolleri geçti; üretim build alındı. PWA cache v18.
- [x] Tekrar çalıştırılabilir tarayıcı kontrolü `mobile-spa/tests/design-smoke.cjs` ve güncel tasarım belgesi eklendi.
- Canlı API/veritabanı doğrulaması veya sunucu deploy işlemi yapılmadı; mevcut Görev/Rapor prototip verileri aynı kapsamda kaldı.
# Elden kesintisi / resmî alacak regresyonu

- [x] Snapshot'taki elden kesintisinin banka kesintisi fallback'ine girmesi engellendi.
- [x] Kesinti kartının tüm kesintileri göstermesi ile banka detayının yalnız banka kanalını göstermesi ayrıştırıldı.
- [x] 3.000 TL elden kesintinin 36.811,35 TL kayıtlı resmî alacağı 39.811,35 TL'ye yükseltmediğini doğrulayan regresyon testi eklendi.

# Hafta içi nöbet geri alma

- [x] `prim` açıklamasından otomatik fazla mesai sınıflandırması yapan son değişiklikler geri alındı.
- [x] Önceki elden kesintisi/resmî alacak düzeltmesi korundu ve ilgili bordro testleri yeniden çalıştırıldı.
- [x] Gerçek `hafta_ici_nobet` hakedişi fazla mesai hedef netinin altında kalmayacak ve tabanın üstüne eklenecek şekilde düzenlendi.
- [x] Maaşa dahil nihai toplamın yükseltilmiş nöbet `net_etki` değeri yerine ham tutarı tekrar kullanması düzeltildi.
- [x] Bordro detayındaki nöbet satırı yükseltilmiş snapshot tutarıyla eşlendi.
- [x] Muhasebe Mutabakat Excel'ine Toplam Gün ve İzin Günü kolonları eklendi; ücretli/ücretsiz izin toplamı ortak ödeme özetinden aktarılır hale getirildi.
- [x] Muhasebe Mutabakat Excel'inde elden kanallı kesintilerin banka kesinti kolonlarına taşınması engellendi; resmî eklenenler eksi gösterilen banka kesintileri kayıtlı banka tutarıyla eşlendi.

## 2026-10-14 — Sözleşme nöbeti / resmî banka regresyonu
- Sözleşme hafta içi nöbeti kayıtlı netiyle korunurken resmî hedefin toplam hakediş tavanına kırpılması düzeltildi. Yeni hesaplamada resmî banka tabanının karşılanamayan bakiyesi `ozet.resmi_taban_tamamlama` olarak saklanıyor; nöbet `net_etki` ve yuvarlama farkı değişmiyor. Elden hakediş yeterliyse ek tamamlama yok.
- Ortak snapshot sonucu `sozlesmeToplamAlacagi` ile sözleşme toplamını ödeme toplamından ayırıyor. Kompakt detay ve Bordro Kontrol sözleşme footer'ı bu tutarı kullanıyor; diğer detayda farkın sözleşme kalemine eklenmesi engellendi. Eski snapshot ödeme kanalları korunuyor.
- Gerçek `hesaplaMaas` akışı için dahil yardımlı ve yardımsız prim usulü nöbet testleri eklendi; kaydedilen snapshot, detay banka kırılımı, muhasebe ve ödeme mutabakatı doğrulandı. Artık kullanılmayan nöbeti sözleşmede yükselten helper ve eski beklenti testi kaldırıldı.
- `vendor/bin/pest tests/Unit --filter 'Bordro|MaasaDahil' --compact`: 90 test, 741 doğrulama geçti. Mevcut canlı legacy gösterimde tanımsız ücretli izin günü için 7 uyarı bulunuyor. PHP sözdizimi ve diff kontrolleri geçti. Veritabanında kayıt değiştirilmedi; mevcut bordro yetkili yeniden hesaplamayla yeni hesabı alır.

## 2026-10-14 — Liste / detay / Excel snapshot mutabakatı
- [x] Bordro Kontrol sözleşme kalemleri snapshot kaynağına taşındı ve footer toplamına kuruş bazında bağlandı.
- [x] Bordro Kontrol kesinti kalemleri snapshot kaynağına taşındı; eski eksik kırılımlar kayıtlı toplamı değiştirmeden tamamlandı.
- [x] Prim usulü tüm personellerde sözleşme ana satırı snapshot'taki gerçek puantaj kaynaklarına bağlandı; asgari taban yalnız resmî `Asgari Ücret (Net)` satırında bırakıldı ve puantajın ikinci kez çizilmesi engellendi.
- [x] Hesaplanmış 164 prim usulü bordroda sözleşme kaynak satırları/toplamları tarandı; fark veya negatif satır bulunmadı. Mehmet Durdu Eylül 2026 için sözleşme kaynak toplamı 3.500,00 TL, kayıtlı resmî banka ve net ödeme 32.380,41 TL doğrulandı.
- [x] Prim usulü `Puantaj Hakedişi` akordeon başlığında toplam işlem adedi ve açılan bölümde işlem/adet/birim fiyat/tutar kırılımları gösterildi; hakediş ve ödeme hesapları değiştirilmedi.
- [x] Resmî Alacak kartındaki HTÇ ayrımı Muhasebe Excel önceliğiyle eşitlendi; Halil İbrahim Karataşlı örneğinde 935,85 TL HTÇ ayrı gösterilirken toplam korundu. 86 HTÇ'li hesaplanmış bordroda detay–Excel farkı `0` doğrulandı.
- [x] Detay ve Muhasebe Excel resmî dağıtımı tek Model metodunda birleştirildi; Excel'deki kopya hesap kaldırıldı ve tek kaynağı zorunlu tutan regresyon testi eklendi. 743 snapshot taramasında merkezî toplam farkı ve 86 HTÇ kaydında ekran–Excel farkı `0` doğrulandı.
- [x] 817 snapshot taraması sıfır sözleşme/kesinti/footer ve liste–Excel banka farkıyla tamamlandı.
- [x] Bordro testleri: 93 test, 749 doğrulama geçti; sözdizimi ve diff kontrolleri geçti.


## 2026-10-15 — Mobil PHP bordro detay modalı
- `mobile/index.php?p=bordro` detay paneli animasyonlu sayfa kapsayıcısından `body` altına taşındı; açılış/kapanış görünürlüğü ve transform durumu bağımsız CSS ile yönetiliyor. Ekran yüksekliği, iç kaydırma ve safe-area uygulandı.
- Mobil API mevcut `get-detail-old` aksiyonunu firma/kayıt kontrolleri ve masaüstü Gate politikası üzerinden sunuyor; kapalı dönemlerde salt okunur detay açılabiliyor.
- Masaüstünün sözleşme alacağı, resmî alacak, kesintiler ve net ödeme/kanal görünümü aynı HTML/snapshot kaynağından mobilde alt alta sunuluyor. Hesap bölümleri başlangıçta açık; satır detayları ve resmî/elden kesinti kırılımları açılıp kapanıyor. Hesaplama ve ödeme tutarları değiştirilmedi.
- Escape, odak geri dönüşü, odak döngüsü, güvenli hata metni ve geciken isteklerin başka paneli değiştirmemesi sağlandı.
- Doğrulama: 390×844 Chromium kontrolü (gerçek salt okunur API yanıtı), 3 Playwright regresyon testi geçti; bordro birim testleri 94 geçti / 751 doğrulama, önceden mevcut 7 legacy uyarı. PHP sözdizimi kontrolü geçti.
- [x] Personel bilgisi kapalı tutulurken sözleşme ve resmî alacak accordionları başlangıçta açık yapıldı; resmî alacak badge kontrastı beyaz metin/mavi zemin olarak düzeltildi ve tarayıcı testiyle doğrulandı.
- [x] Personel bilgi kartı başlangıçta kapalı gelen accordion yapısına dönüştürüldü; fare/dokunma ve klavye etkileşimi doğrulandı.


## 2026-10-15 — Bordro personel sıralaması ve net ödeme kartı
- Personel adı hücresine HTML-escape edilmiş yalnız ad/soyad içeren `data-order` eklendi; fotoğraf HTML ve boşluklarının sıralamayı fotoğraflı/fotoğrafsız gruplara ayırması giderildi.
- Masaüstü kompakt detayın net ödeme kartı koyu lacivert (`#14243e`) yapıldı; yan yana/tam satır görünümünde kanal tutarları açık, açıklamalar açık mavi tonlarla sunuluyor. macOS koyu tema zemini de aynı renge bağlandı.
- Doğrulama: PHP sözdizimi kontrolleri geçti. Chromium üzerinde gerçek DataTables kütüphanesiyle karışık fotoğraf durumlarında artan/azalan ad sıralaması ve 1000/1440px açık/koyu tema kart zemini doğrulandı. Bordro hesapları ve kayıtlı ödeme tutarları değiştirilmedi.


## 2026-10-15 — Bordro fotoğrafsız personel avatarları
- Fotoğrafı olmayan personelde ilk ad ve soyadın baş harfleri 32×32px yuvarlak mavi avatarla gösteriliyor. Türkçe i/ı dönüşümü, tek/çoklu ad ve boş ad destekleniyor. Yüklenemeyen fotoğraf da aynı avatara dönüyor.
- Ad sıralamasındaki `data-order` korundu. PHP sözdizimi, Türkçe baş harf örnekleri ve Chromium üzerinde fotoğraf hata fallback/boyut kontrolü geçti.
# Zimmet sayfası performans analizi

- [x] PHP hazırlığı ve DataTables AJAX süresi ayrı ölçüldü; gecikmenin 5,8–8,5 saniyelik liste veri sorgusundan kaynaklandığı belirlendi.
- [x] SQL profiling ve EXPLAIN ile tam tarama, block nested loop, geçici tablo ve filesort doğrulandı.
- [x] Join sırası karşılaştırmasında temel sorgunun yaklaşık 1 ms seviyesine inebildiği görüldü; uygulanacak optimizasyon sırası belirlendi.
- [x] Liste sorgusunun join sırası zimmet tablosundan başlayacak şekilde düzeltildi; filtresiz DataTables yanıtı yaklaşık 225 ms'ye indirildi.
- [x] Sayı filtrelerinin büyüktür/küçüktür ve eşitli karşılıkları sunucu tarafına eklendi; mevcut, sonuçsuz ve temizlenmiş filtre senaryoları doğrulandı.

# Zimmet seçim listesinde mükerrer seri uyarısı

- [x] Aynı firmadaki gerçek mükerrer seri numaraları model katmanında tespit edildi.
- [x] Stoku tükenen mükerrer kartlar seçim sonuçlarına geri eklendi ve aktif zimmette oldukları belirtilerek seçilemez yapıldı.
- [x] Stokta olan mükerrer kartlara görünür uyarı metni eklendi; geçersiz seri değerleri kapsam dışında bırakıldı.
- [x] Gerçek mükerrer sayaç verisiyle sonuç kümesi ve `disabled` davranışı doğrulandı; PHP sözdizimi kontrolleri geçti.
- [x] Zimmet tablosunun ID kolon filtresi sunucu tarafında `z.id` alanına bağlandı ve tek kayıt sonucu doğrulandı.
- [x] Toplu sayaç zimmetinde aynı seri için birden fazla demirbaş kartının tekrar tekrar işlenmesi engellendi; mükerrer kart sayısı koli satırında uyarı olarak gösterildi.
- [x] `26117971–26117980` aralığındaki 10 serinin 20 mükerrer karttan 10 tekil karta indirildiği ve kayıt akışının transaction içinde hatasız çalıştığı doğrulandı; test verisi rollback edildi.

## 2026-10-15 — Resmî ücret hesap pusulası ve toplu PDF
- [x] Bordro → Raporlar → Resmî Ücret Hesap Pusulaları ekranı ve doğrudan yazdırma bağlantısı onarıldı.
- [x] Muhasebe PDF düzeninde kişi başına A4, tümünü yazdır, toplu indirme ve tekil PDF eklendi.
- [x] Model üzerinden firma izolasyonu, mevcut rapor Gate yetkisi, şifreli ID, HTML escape ve kimlik alanlarının çözülmesi sağlandı.
- [x] Kayıtlı snapshot ve banka neti korundu; eksik snapshotta canlı hesap/eksik kişiyi sessiz atlama engellendi.
- [x] 103 birim testi / 805 doğrulama; 102 kayıt liste/yayın/PDF karşılaştırması; tekil/toplu, firma izolasyonu, 102 taşmasız A4 ve Chromium kontrolleri geçti. Önceden mevcut 7 legacy uyarı sürüyor.
- [ ] Muhasebe ile kaynak mutabakatı: Bazı SGK/vergi/yemek ve işyeri alanları mevcut snapshotta PDF'den farklı/eksik. Geçmiş hesaplar değiştirilmedi. Referans 103 sayfa, 102 tekil kişi (Fahri Ayaz iki pusula); sistem 102 personeli tekil gösterir.
- [x] Raporlar hızlı indirme ve personel finansal sekmesindeki eski PDF bağlantıları da güncellendi; tekil endpoint bir A4 üretiyor.

## 2026-10-15 — Muhasebe bordro Excel karşılaştırması
- [x] Resmî pusula ekranına `.xlsx` yükleme, analiz ve karşılaştırma alanı eklendi.
- [x] T.C. öncelikli/ad-soyad yedek eşleştirme, kuruş hassasiyetli 13 alan karşılaştırması ve yalnız bir tarafta bulunan kayıtlar uygulandı.
- [x] Özet sayaçları, durum filtreleri ve kişi bazlı açılır fark tablosu eklendi; T.C. değerleri yanıtta maskelendi.
- [x] Gate, firma izolasyonu, şifreli dönem, CSRF, MIME/uzantı ve 10 MB dosya sınırı doğrulandı; yüklenen dosya kalıcı saklanmıyor.
- [x] Gerçek örnek Excel ile 103 sonuç üretildi: 102 farklı, 1 yalnız muhasebede (mükerrer Fahri Ayaz), 0 yalnız sistemde. Tarayıcı ve gerçek HTTP upload kontrolleri geçti.
- [x] Bordro testleri 105 geçti / 817 doğrulama; önceden mevcut 7 legacy uyarı sürüyor.
- [x] Kullanıcı düzeltmesiyle Excel karşılaştırması `bordro/list` sayfasına taşındı; pusula rapor ekranındaki karşılaştırma bileşeni kaldırıldı.
- [x] SGK/vergi brüt bordro karşılaştırması kaldırıldı; muhasebeye gönderilen `Mutabakat` sayfasının C–R kolonları aynı model dağılımıyla karşılaştırılır hale getirildi.
- [x] Çok sayfalı dosyada Mutabakat başlığı arama, 16 alanın gün/kuruş ayrıştırması ve banka kesintisi/ödeme kolonları için birim testi geçti (2 test, 10 doğrulama).
- [x] Muhasebe Excel karşılaştırması sayfada yer kaplamayan modal yapısına taşındı ve seçili dönemin `Dönem İşlemleri` menüsüne eklendi.
- [x] Gerçek muhasebe maaş bordrosu şablonu (`T.Gün`, `Normal Gün`, `N.Ödenen` ve ikili gün/tutar kolonları) desteklenerek gönderilen Mutabakat dosyasındaki altı ortak alanla güvenli biçimde eşlendi.
- [x] Eklenen şablon testiyle birlikte 3 test / 16 doğrulama geçti; gerçek dosyada 103 personel ve örnek gün/yemek/banka değerleri doğrulandı.
- [x] Modalda her farklı alan için benzersiz personel sayısını gösteren yatay grafik eklendi.
- [x] Durum ve alan özetli, tüm farkları açık/filtrelenebilir satırlarda sunan XLSX indirme çıktısı eklendi; test paketi 4 test / 20 doğrulamaya çıktı.
- [x] Excel detay çıktısı her personel tek satır ve her kalem üç kolon (`Muhasebe / Gönderilen / Fark`) olacak yatay matrise dönüştürüldü; fark hücreleri görünür biçimde vurgulandı.
