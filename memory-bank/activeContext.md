# Active Context

## Mobil SPA Başlık Hizalama ve Talepler Modülü Navbar/Yerleşim Optimizasyonu (2026-10-15)
- **Kullanıcı Talebi**:
  1. Tüm sayfalarda sayfa başlık ve açıklamasının üstte, geri butonu yanında yer alması.
  2. Talepler sayfasında "Süreç Yönetimi" yazısının kaldırılması.
  3. Talepler sayfasına basılınca talep türleri kartının navbar içinde yana doğru transition ile açılması, Talepler menüsünün X kapatma şeklinde olması ve transition ile kapanması.
  4. Bekleyenler, İşlem Görenler, Silinenler durum filtrelerinin en üstte (başlık altında) olması.
  5. Arama kutusu ve sıralama butonunun yan yana konumlandırılması.
  6. Sayfanın en verimli şekilde talep içerikleri için kullanılması.
- **Mimari & Geliştirmeler**:
  1. **TopBar & Başlık Standardı (`mobile-spa/src/components/TopBar.vue`, `src/design-system.css`)**:
     - `showBack` aktif olan tüm sayfalarda (`route.path !== '/'` veya `:back="true"`), sayfa başlığı (`h1.clay-topbar-title`) ve alt açıklama (`p.clay-topbar-subtitle`), geri butonunun hemen yanındaki `.clay-topbar-title-block` içine taşındı.
     - Ayrık ve fazla dikey alan kaplayan alt `.clay-page-header` alanı yalnızca `showBack` olmayan (ana sayfa vb.) durumlara sınırlandı.
     - `.clay-topbar.has-inline-header` ile dikey boşluklar kompakt hale getirilerek ~80-100px net dikey alan kazanıldı.
  2. **Talepler Durum Filtresi ve Arama/Sıralama Yerleşimi (`mobile-spa/src/views/TaleplerView.vue`)**:
     - "SÜREÇ YÖNETİMİ" eyebrow etiketi kaldırıldı.
     - Sayfa gövdesindeki kaba `hero-card--talepler` kaldırıldı (~115px dikey alan tasarrufu).
     - `Bekleyenler`, `İşlem Görenler` ve `Silinenler` butonları başlığın hemen altına en üste alındı.
     - Arama kutusu ve sıralama butonu `.talepler-search-sort-row` ile yan yana yerleştirildi (`flex: 1` arama kutusu ve 44x44px dairesel sıralama butonu).
     - Sayfa içeriği (~195px yukarı kayarak) doğrudan görünür hale getirildi.
  3. **Navbar İçinde Yana Doğru Açılan Talep Türleri Nav Menüsü (`mobile-spa/src/components/BottomNav.vue`, `src/services/requests.js`, `src/design-system.css`)**:
     - `requests.js` servisinde reaktif `requestsState` (`activeTab`, `counts`, `permissions`, `drawerOpen`) ve aksiyon yardımcıları tanımlandı; varsayılan tab `all` (Tümü) olarak ayarlandı.
     - Kullanıcının "buradaki biraz daha nav menü gibi olsun, ikon da olsun" geri bildirimi üzerine panel, standart 5 sütunlu `.bottom-nav__tabs` ve `.nav-link` bileşen yapısına dönüştürüldü:
       - 1. Sekme: `Sparkles` ikonu (21px), `nav-bubble-badge` sayaç rozeti ve `Tümü` etiketi.
       - 2. Sekme: `Wallet` ikonu (21px), `nav-bubble-badge` sayaç rozeti ve `Avans` etiketi.
       - 3. Sekme: `CalendarDays` ikonu (21px), `nav-bubble-badge` sayaç rozeti ve `İzin` etiketi.
       - 4. Sekme: `Layers` ikonu (21px), `nav-bubble-badge` sayaç rozeti ve `Genel` etiketi.
       - 5. Sekme: `X` ikonu (22px) ve `Kapat` etiketi (`nav-link--close`).
     - Tıklanan aktif kategori tıpkı ana nav menüdeki gibi saf beyaz hap (`.nav-link.is-active`) zeminine ve koyu metne kavuştu.
     - İkonların sağ üst köşesine bildirim sayısını gösteren `.nav-bubble-badge` dinamik rozetleri yerleştirildi.
     - `X` / Kapat butonuna basıldığında talep türleri sağa doğru akıcı biçimde kayarak kapanıyor ve standart navbar menüsü geri geliyor.
     - `TaleplerView` üzerinde `all` (Tümü) desteği tam entegre edildi; çekmece kapalıyken o anki kategoriyi gösteren `.pill-category-badge` çipi de dinamik olarak senkronize edildi.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile Vite üretim derlemesi 0 hata ile başarıyla tamamlandı (`dist/assets/index-a32JNAJ_.css`, `dist/assets/index-C3OXLq7Q.js`).

## Mobil SPA Araç Takip Modülü ve Gelişmiş Bento UI Geliştirmesi (2026-10-15)
- **Kullanıcı Talebi**:
  1. `mobile-spa` altında Araç Takip modülünün yapılması, `mobile` altındaki eski modülden işlevlerin alınması ve UI/UX Pro Max tasarım standartlarında Bento / Claymorphic UI ile çok daha gelişmiş ve estetik bir SPA modülü olarak inşa edilmesi.
  2. KM ve Araç kartlarının kullanıcı dostu, okunabilir ve modern Bento standartlarına yükseltilmesi (kaba görünüm ve metin sıkışmalarının giderilmesi).
- **Mimari & Geliştirmeler**:
  1. **Backend API (`mobile-spa/public/api/arac.php`)**:
     - `checkAracAuth()` ile Gate yetkisi (`arac_takip_yonetim`, `arac-takip/list`, `vehicles`) ve oturum doğrulaması.
     - `verifyCsrfToken()` ile CSRF koruması.
     - `Security::encrypt` ve `Security::decrypt` ile hassas ID koruması.
     - AES-256-GCM dosya şifreleme ve güvenli stream deşifre indirme entegrasyonu (ruhsat belgesi).
     - `araclar`, `zimmet`, `yakit`, `km`, `servis` sekmeleri için zenginleştirilmiş veri listeleme ve özet KPI hesabı.
     - Araç kaydetme/güncelleme, soft-delete, ruhsat yükleme/silme, zimmet verme/iade alma, yakıt fişi ekleme/silme, KM kaydı, servis bakım kaydı ve tamamlama aksiyonları.
     - Tüm veri işlemlerinde parametrik PDO ve `SystemLogModel` ile denetim loglaması.
  2. **Frontend API Servisi (`mobile-spa/src/services/arac.js`)**:
     - Modern fetch istemcisi, multipart FormData desteği ve CSRF token yönetimi.
  3. **Frontend Görünümü (`mobile-spa/src/views/AracView.vue`) & UI/UX Pro Max Kart Yenilemesi**:
     - **KM Yolculuk Kartı (`.km-journey-card`)**:
       - Üst Kısım: Türk plaka rozeti (`.tr-plate-badge`), canlı mor gradient `+KM` fark hapı (`.km-diff-pill`) ve takvim ikonlu tarih rozeti.
       - Orta Yolculuk Çizelgesi (`.km-journey-box`): Başlangıç KM ve Bitiş KM için iki ayrı kutucuk ve aralarında dikey kesikli yön göstergesi.
       - Alt Bilgi: Dairesel avatarlı sürücü künyesi ve görev notu çipi (`.journey-task-chip`).
     - **Ana Araç Kartı (`.vehicle-master-card`)**:
       - Başlık: Mavi TR şeritli plaka, marka/model/yıl alt başlığı, durum renk noktasıyla canlı rozet (`Zimmetli`, `Boşta`, `Serviste`, `İkame`).
       - Metrik Kutuları (`.vehicle-bento-grid`): 2'li Bento kutusu (Güncel KM sayacı & Zimmetli Personel).
       - Kritik Evrak Hapları: Muayene, Sigorta ve Kasko için 3'lü akıllı renk kodlu hap rozetleri (geçen kırmızı, yaklaşan sarı, geçerli yeşil).
       - Aksiyon Çubuğu: 44px dokunmatik butonlar (Detay & Ruhsat, Düzenle, Servis/KM/Zimmet eylemleri).
     - **Araç Detay Kartı (Modal / Bottom Sheet)**:
       - Üst Kısım: Sürükleme tutamacı (`.sheet-drag-handle`), 38px dairesel araç ikonu, başlık ve plaka alt başlığı.
       - Kimlik Hero Kutusu (`.detail-hero-box`): Mavi TR şeritli plaka rozeti, canlı renkli durum hapı (`Zimmetli`, `Boşta`, `Serviste`), marka/model ve 4'lü özellik çipleri (model yılı, renk, araç tipi, ikame durumu).
       - 2x2 Bento Metrik Izgarası (`.detail-metrics-grid`): Sayaç (indigo), Yakıt tipi (amber), Şase no (monospace, blue), Mülkiyet/Departman (purple) için ayrık Bento kutuları.
       - Güncel Zimmet Sahibi Paneli (`.detail-person-row`): İki harfli gradyan dairesel personel avatarı, personel adı ve departman künyesi; zimmet yoksa yeşil boşta havuz çipi.
       - 3'lü Evrak Takip Izgarası (`.detail-docs-grid`): Muayene, Sigorta ve Kasko için ayrık takvim kutuları ve akıllı renk kodlu (kırmızı süresi doldu, sarı yaklaşan, yeşil geçerli) gün sayaç rozetleri.
       - Şifreli Ruhsat Belgesi Kartı (`.ruhsat-file-card`): ISO 27001 yeşil kalkanlı "AES-256 Şifreli" çipi, dosya adı, doğrudan şifresiz stream indiren/açan "Görüntüle" ve "Sil" aksiyonları; ruhsat yoksa kesikli modern yükleme kutusu.
       - Alt Aksiyon Çubuğu: 48px dokunmatik "Aracı Düzenle" (koyu obsidian bento buton) ve "Sil" (danger buton) çubuğu.
     - **Merkezi Layout Micro-Utilities (`src/design-system.css`)**:
       - SPA genelinde grid, flex, gap, padding, margin, text ve font sınıfları eklenerek form ve modalların hizalama/boşluk kararlılığı sağlandı.
     - **Dönem & Ay Seçici Düzeltmesi (Mobil "Ayı Seçin" Tap Hijacking Önlemi)**:
       - Önceden ekranda görünmeyen, `.relative` kapsayıcısı olmadığı için sayfa geneline yayılan ve kullanıcının alakasız yerlere dokunmasında yerel tarayıcının "Ayı seçin" tekerleğini açan şeffaf `<input type="month">` overlay yapısı tamamen kaldırıldı.
       - Yerine ergonomik, dokunmatik **Dönem & Ay Seçici Bottom Sheet (`isMonthModalOpen`)** geliştirildi: Yıl navigasyonu (`[ < ] 2026 [ > ]`), 12 ay Bento ızgarası (`Ocak` ... `Aralık`), güncel ay gösterge noktası, seçili ay vurgusu ve "Bu Ay", "Tüm Zamanlar" kısayolları eklendi.
     - **Yakıt, Zimmet ve Servis Kartları**:
       - 2'li Bento kutuları (Toplam Tutar, Litre, Birim Fiyat, Sayaç), personel avatarları ve servis durumu rozetleri.
     - **Bento Hero & Uyarı Kartları**:
       - Koyu Obsidian Canlı Filo Bento Hero Kartı (`.bento-card--dark`), Segmented Progress Track, 4'lü metrik ızgarası.
       - Kritik Evrak Uyarı Kartı (`.announcement-card`) ile tek tıkla filtreleme.
     - **Bottom Sheet & Modallar**: 10 adet tam uyumlu ergonomik modal/sheet penceresi.
  4. **Entegrasyon & Build**:
     - `mobile-spa/src/router.js`: `/arac-takip` rotası eklendi (`permission: 'vehicles'`).
     - `mobile-spa/src/components/BottomNav.vue`: "Daha Fazla" menüsündeki Araç Takip butonu SPA rotasına bağlandı (`to: '/arac-takip'`).
     - `mobile-spa/src/views/HomeView.vue`: Ana sayfadaki araç hızlı eylemleri ve filo kartı SPA rotasına bağlandı.
     - `npm run build` ile üretim derlemesi 0 hata ile tamamlandı.

## Zimmet sayfası performans analizi (2026-10-15)
- `demirbas/zimmet` ilk yükü ayrıştırıldı: personel seçenekleri yaklaşık 34 ms, kategoriler yaklaşık 5 ms; DataTables AJAX sorgusu ise 5,8–8,5 saniye sürdü.
- SQL profiling sonucunda toplam sayım 136 ms, filtreli sayım 151 ms, ilk 10 satırın veri sorgusu 7,84 saniye ölçüldü. Ana darboğaz veri sorgusudur.
- EXPLAIN çıktısında `demirbas_zimmet` için 27 bin satır tam tarama, personel tablosunda block nested loop, geçici tablo ve filesort görüldü. Aynı temel sorguda join sırası zimmet tablosundan başlayacak şekilde zorlandığında yaklaşık 1 ms ölçüldü.
- İkincil iyileştirme alanları: hareket toplamlarının üç korelasyonlu alt sorgu yerine sayfa ID'leri için toplu alınması, mükerrer seri kontrolünün sayfa satırlarına toplu uygulanması, uygun bileşik indekslerin ayrı SQL migration ile eklenmesi, 169 KB ortak `demirbas.js` dosyasından zimmet kodunun ayrılması ve kapalı grafik/modalların ihtiyaç anında yüklenmesi.
- Liste sorgusuna `STRAIGHT_JOIN` uygulanarak MySQL'in zimmet tablosundan ve ID indeksinden başlaması sağlandı. Filtresiz ilk 10 satır isteği 5,8–8,5 saniyeden yaklaşık 225 ms'ye indi.
- Gelişmiş sayı filtrelerinin API'de eksik olan `greater_than`, `less_than`, `greater_equal` ve `less_equal` operatörleri eklendi. Mevcut ID filtresi 148 ms, sonuçsuz ID filtresi 145 ms, filtreler temizlendikten sonraki liste 225 ms ölçüldü.
- Görselde filtrelenen `28052` ID'sinin önceki başarısız toplu zimmet transaction'ında üretilip rollback edildiği için veritabanında bulunmadığı doğrulandı; bu ID için boş sonuç doğrudur.

## Zimmet seçim listesinde mükerrer seri görünürlüğü (2026-10-15)
- `DemirbasModel::getForSelect()` aynı firmada birden fazla aktif demirbaş kartında bulunan kullanılabilir seri numaralarını tespit edecek şekilde güncellendi.
- Stoku tükenmiş olsa bile mükerrer seri kartları zimmet modalındaki Select2 sonucuna dahil ediliyor; aktif zimmette olan seçenek uyarılı ve devre dışı, stokta olan mükerrer seçenekler ise görünür uyarı metniyle sunuluyor.
- Geçersiz/temsili seri değerleri (`-`, `0`, `yok`, `n/a` vb.) mükerrer olarak değerlendirilmedi. Mevcut stok uygunluk hesabı ve sunucu tarafındaki zimmet mükerrer kontrolü korundu.
- Gerçek veride `25930517` seri numarasıyla üç kartın birlikte döndüğü, aktif zimmetteki kartın `disabled: true` ve uyarılı olduğu doğrulandı. PHP sözdizimi kontrolleri geçti.
- Zimmet listesi ID başlık filtresinin sunucu eşlemesinde eksik olan `1 => z.id` alanı eklendi; gerçek bir zimmet ID'siyle filtre sonucunun tek satıra düştüğü doğrulandı.
- Toplu sayaç zimmetinde aynı seriye ait tüm demirbaş kartlarının döngüye alınarak transaction içinde yapay mükerrer zimmet oluşturduğu tespit edildi. `26117971–26117980` aralığında 10 seri için 20 kart bulunduğu doğrulandı.
- Koli kontrolü mükerrer kart sayısını uyarı olarak gösteriyor. Kayıt aşaması her seri için stoktaki tek kartı deterministik seçiyor; 10 seriyle yapılan kayıt denemesi hatasız tamamlanıp test transaction'ı geri alındı.

## Sistem Logları Menü ve Yetkilendirme Çalışması (2026-10-15)
- **Kullanıcı Talebi**:
  `views/logs/list.php` sayfası için menü yapılması ve Süper Admin ile Firma Sahibi için yetki verilmesi.
- **Yapılan İnceleme ve Analiz**:
  - `views/logs/list.php` sayfasının `Gate::allows("log_kayitlari")` kontrolü ile korunduğu tespit edildi.
  - Sayfanın kullandığı `views/logs/api.php` dosyasında `Gate::allows("log_kayitlari")` ve `Gate::authorizeApiPolicy('logs/api', $action)` kontrollerinin bulunduğu görüldü.
  - Veritabanında `permission_key = 'log_kayitlari'` (ID: 923) yetkisinin ve `permission_policies` tablosunda `logs/list` (GET) ile `logs/api` (POST) aksiyonlarının tanımlı olduğu, ancak `menus` tablosunda bu sayfa için menü kaydı bulunmadığı tespit edildi.
- **Uygulanan Geliştirmeler ve Standartlar**:
  - **SQL Migration**: `sql/2026_10_15_add_sistem_loglari_menu_and_permissions.sql` oluşturuldu ve PDO üzerinden çalıştırıldı.
  - **Permissions**: `log_kayitlari` yetkisi `name = 'Sistem Logları'`, `group_name = 'Yönetim'` olarak güncellendi.
  - **User Role Permissions**: Süper Admin (`role_type = 'superadmin' OR superadmin = 1`) ve Firma Sahibi rollerine `NOT EXISTS` kontrollü yetki tanımlaması sağlandı.
  - **Menus**: `menus` tablosuna `menu_name = 'Sistem Logları'`, `menu_link = 'logs/list'`, `parent_id = 0`, `group_name = 'Yönetim'`, `group_order = 5`, `menu_order = 84`, `menu_icon = 'shield'`, `permission_id = @log_kayitlari_permission_id` kaydı eklendi.
  - **Permission Policies**: `logs/list` (page GET) ve `logs/api` (api POST/all actions) kayıtları `@log_kayitlari_permission_id` ile senkronize edildi.
  - **Cache Temizliği**: Menü cache'leri (`cache/tenant_*`) temizlendi.
  - **Doğrulama & Test**:
    - `MenuModel::getHierarchicalMenuForRole`: Süper Admin (user 1) ve Firma Sahibi (user 62) için `Yönetim` grubu altında `Sistem Logları` menüsü doğrulandı.
    - `PermissionsModel::getGroupedPermissions`: Yetki grubu düzenleme ekranında `Yönetim` altında `Sistem Logları` eksiksiz listelendi.
    - `PermissionCatalogModel::getCatalog`: Yetki Kataloğu'nda `mapping_type: "explicit"` ve `protected: true` olarak eşleşti.
    - `Gate::allowsPolicy` & `Gate::allows`: Süper Admin ve Firma Sahibi için `true`, yetkisiz roller için `false` döndüğü test edilerek onaylandı.

## Bordro — Elden Kesinti Dağılımı ve Muhasebe Exceli İyileştirmesi (2026-10-14)
- **Kullanıcı Talebi**:
  `views/bordro/list.php` sayfasında kesintilerde:
  1. Eğer kesinti türü "elden" ise, personelin elden hakedişinin maksimum tutarı kadar elden tutar kesilecek, kalan tutar bankadan kesilecek.
  2. Muhasebe Excel'inde (`views/bordro/excel-yemek-export.php` ve Mutabakat sayfası): Bankadan kesilen bu bakiye tutar kesinti tarafında (Q sütunu: Diğer Kesintiler) gösterilecek; yeşil resmî hakediş sütunlarından (asgari ücret / yemek) düşmeyecek.
- **Yapılan Geliştirmeler**:
  - `App/Model/BordroPersonelModel.php`:
    - `kayitliGosterimDegerleri` ve `normalizeKayitliBankaGosterimi`: Bankadan karşılanan kesinti miktarı banka kanallı kesintilerden fazla olduğunda, elden kanallı kesintilerden bankaya aktarılan pay tespit edilerek `$bankaKesintiKalemleri` içine eklendi.
    - `hesaplaOrtakGosterimDegerleri`: Canlı hesaplamada da elden kesintiden bankaya taşan kısım (`bankayaTasanElden`) `$bankaKesintiKalemleri`'ne eklendi.
    - `getMuhasebeOdemeOzeti`: `$bankaKesintisi` hesaplanırken `bankaKesintiKalemleri` toplamı fallback olarak desteklendi; böylece elden kesintiden bankaya aktarılan tutar `muhasebe_banka_diger_kesinti` ve `muhasebe_bankadan_dusulecek` olarak tam yansıtıldı.
    - `hesaplaMaas`: `$isEldenKesinti` tespiti parametre `odeme_yontemi`, `hesaplama_tipi` ve `banka_matrahina_ekle` ile tam senkronize edildi.
  - `views/bordro/excel-yemek-export.php`:
    - 1. sayfadaki Diğer Kesintiler sütunu (M kolonu) ve Mutabakat sayfasındaki Diğer Kesintiler sütunu (Q kolonu) `$veri['muhasebe_banka_diger_kesinti']` üzerinden bankadan düşülen kesintiyi tam yansıtacak şekilde doğrulandı.
  - `views/bordro/list.php` & `views/bordro/js/bordro.js`:
    - Modal formuna kesinti kanalı (Banka / Elden) radio seçimi eklendi (`banka_matrahina_ekle`).
    - Sayfaya `window.kesintiKanallari` eklendi; kesinti türü seçildiğinde parametre tanımına göre radyo butonu otomatik seçiliyor.
    - Kesinti listesinde her satıra "Banka" veya "Elden" rozeti (`kanalBadge`) ve düzenleme butonuna `data-banka-matrahi` niteliği eklendi. Form resetlendiğinde radyo varsayılana dönüyor.
  - Test & Doğrulama:
    - Ömer Faruk Dağ (Personel ID: 236, Dönem ID: 26, Kesinti ID: 7851) üzerinde doğrulandı:
      - Sözleşme: 30.800 TL, Prim: 3.100 TL -> Toplam: 33.900 TL
      - Resmî Asgari: 26.203,80 TL, Yemek: 7.200 TL -> Resmî Matrah: 33.403,80 TL (düşmedi, tam kaldı)
      - Kesinti: 4.624,00 TL -> Eldenden kesilen: 496,20 TL (Elden Net: 0 TL), Bankadan kesilen: 4.127,80 TL
      - Muhasebe Excelinde: Yeşil kalemler düşmedi, Kırmızı Diğer Kesintiler: 4.127,80 TL, Bankaya Yatacak: 29.276,00 TL.
    - `tests/Unit/BordroBankaDagilimiTest.php` içine yeni birim test eklendi (`testEldenKesintiEldenHakedisiAstigindaBankadanKesilirVeMuhasebedeKesintiOlarakGosterilir`). Tüm 94 bordro birim testi başarıyla geçti.
  - SQL & Dokümantasyon:
    - `sql/2026_10_14_elden_kesinti_ve_omer_faruk_dag_guncelleme.sql` scripti oluşturuldu.
    - `docs/BORDRO_HESAPLAMA_KURALLARI.md` ve `AGENTS.md` Kural 5 güncellendi.
- Kullanıcının `@mobile-spa sayfasında bordro sayfasını yapalım @[mobile] burada bordro modülünden alacağız` talebi üzerine:
  - `@mobile` içindeki `mobile/pages/bordro.php` ve `mobile/api/bordro.php` mimarisi incelendi; Soft Bento & Claymorphic UI standartlarına tam uyumlu olarak SPA modülüne dönüştürüldü.
  - Backend API: `mobile-spa/public/api/bordro.php` (ve derleme kopyası `mobile-spa/dist/api/bordro.php`) yazıldı. PDO, Model katmanı (`BordroDonemModel`, `BordroPersonelModel`, `BordroParametreModel`), firma izolasyonu, `Security::encrypt`/`decrypt`, CSRF token denetimi ve merkezi `views/bordro/api.php` entegrasyonu sağlandı.
  - Frontend Servisi: `mobile-spa/src/services/bordro.js` oluşturuldu (`init`, `getDetail`, `calculate`, `togglePeriod`, `distribute`, `resetAllPayments`, `resetSinglePayment`, `addAdjustment`).
  - Görünüm Bileşeni: `mobile-spa/src/views/PayrollView.vue` geliştirildi:
    - Merkezi `TopBar` entegrasyonu (başlık, dönem alt başlığı, yenileme, diğer işlemler ve maaşları hesaplama aksiyonları).
    - Bento dönem seçici ve açık/kapalı durum rozetleri (`clay-pill-tag--emerald` / `clay-pill-tag--coral`).
    - Obsidian koyu zeminli Hero Net Ödeme kartı (`bento-card--dark`) ve 3'lü Bento KPI ızgarası (Banka, Elden, Sodexo).
    - Canlı personel arama çubuğu ve 5 sekmeli hızlı durum filtreleme (`clay-tabs-wrap`).
    - 2x3 hakediş/kesinti ve ödeme kanalları kırılım ızgarasına sahip beyaz Bento personel kartları.
    - Alttan açılan detay paneli (Obsidian hero kart, ödeme kanalları, banka tavanı matrah tooltip'i ve resmî hesaplama kalemleri).
    - Ödeme dağılımı düzenleme modalı (canlı elden bakiye hesaplamalı).
    - Ek gelir (prim, mesai, ikramiye) ve kesinti (avans, icra, nafaka) ekleme form modalları.
    - Dönem kapatma/açma, dağılım sıfırlama işlemlerini barındıran "Diğer İşlemler" paneli ve yerleşik Bento onay modalı.
  - Router & Navigasyon: `router.js` içine `/bordro` ve `/maas` rotaları eklendi; `BottomNav.vue` içindeki eski PHP linki SPA rotası `to: '/bordro'` olarak bağlandı.
  - Görünüm & Tipografi Düzeltmeleri:
    - Kullanıcının "toplam net dağıtım çok büyük, ayrıca dönem adı görünmüyor" geri bildirimi çözüldü.
    - Dönem Adı: API'de `Security::encrypt` çağrısı rastgele IV nedeniyle iki kez çağrıldığında farklı token üretiyordu; `donemOptions` içindeki token ile `selectedDonemData.id` tokenı birebir senkronize edildi. `<select>` elemanına `v-model="selectedDonemId"` bağlandı ve yazı rengi netleştirildi (`#0f172a`), böylece seçili dönem adı ("EYLÜL 2026") select kutusunda eksiksiz göründü.
    - Toplam Net Dağıtım: `.bento-overview-value strong` varsayılan 60px'lik boyutu nedeniyle milyonluk tutarlarda oluşan taşma ve satır kırılması giderildi. `.payroll-hero-amount` için `font-size: clamp(22px, 6.2vw, 28px)`, `white-space: nowrap`, `overflow: hidden`, `text-overflow: ellipsis` uygulandı; tutar kart içine dengeli biçimde oturtuldu.
    - Bordro Detay Modalı (Sheet) Şıklaştırması, İki Sekme ve Kompakt Özet İyileştirmesi:
      - Masaüstündeki (`views/bordro/list.php`) gibi iki ayrı gösterim eklendi:
        1. **Karşılaştırmalı Hesap** (`get-detail-old`): Sözleşme Alacağı Hesabı, Resmî Alacak Hesabı, Kesintiler.
        2. **Resmî Bordro Dökümü** (`get-detail`): Normal kazançlar, fazla çalışmalar, yasal kesintiler.
      - **UI/UX Pro Max Standartları Uygulaması**:
        - **Apple iOS Segmented Control**: Sekmeler kaba butonlar yerine modern gri ray üzerinde saf beyaz kabartmalı aktif hap kontrolüne dönüştürüldü.
        - **Ultra-Kompakt Bento Özet Bandı (`.detail-summary-strip`)**: Ekranın %60'ını kaplayan hantal kutular yerine ~58px'lik şık bir bant yerleştirildi; Net Maaş (`₺53.405,00`), Banka (`₺35.875,50`), Elden (`₺17.529,50`) tek satırda sunuldu, chevron ile detaylı kırılım açılabilir hale getirildi. Modal yüksekliği `94vh` yapılarak döküm alanına ~210px net alan kazandırıldı.
        - **Başlık Rozeti Kırılması Giderildi**: `53.405,00 \n ₺` olarak iki satıra bölünen yeşil rozet `white-space: nowrap !important; font-size: 12.5px; border-radius: 9999px;` ile tek satırda kusursuz hap rozet haline getirildi.
        - **Masaüstü Tablosu → Fintech Kart Listesi Dönüşümü**: Masaüstünden gelen `<table>` ve `<tr><td>` yapısı CSS flexbox ve fintech kart listesi standartlarına dönüştürüldü:
          - Masaüstü `.pe-4` ve `.ps-5` padding'leri sıfırlandı; alt satırlar sol kenarında ince zarif bir gösterge çizgisi (`border-left indicator`), sağ kenarda ise milimetrik hizalı tabular tutarlarla sunuldu.
          - Satırlar arası ince ayırıcılar, koyu okunabilir tipografi (`#0f172a`), zarif birim açıklamaları ve belirgin toplam şeritleri uygulandı.
  - Smoke Test & Derleme: `design-smoke.cjs` içine detay sekmeleri geçişi, puantaj alt döküm kalemleri doğrulamaları dahil edildi. 320/390/480px ekran boyutlarında 27 kontrol başarıyla (`PASS`) tamamlandı. `npm run build` ile hatasız üretim çıktısı alındı.

## Mobil SPA — mobil uygulama tasarımı (2026-10-14)
- Pinterest `558939003771833803` referansının sade beyaz/koyu yüzeyleri ve alt panel yaklaşımı mevcut Vue SPA'ya uygulandı. Ana sayfa gerçek API verisinden saha özeti, hızlı erişim, ekip/filo dağılımı ve operasyon kartları çiziyor; önceki sabit personel avatarları ve işlevsiz bildirim düğmesi kaldırıldı.
- `src/design-system.css` ortak tasarım kaynağı: mat gri canvas, beyaz kartlar, koyu dock, sistem fontu, durum tokenları, 44px ana kontroller, 16px form değerleri, safe-area ve reduced-motion. Mevcut scoped CSS paletleri merkezi tokenlara bağlandı. Giriş/firma seçimi aynı tasarıma uyarlandı; 320px giriş formundaki iç taşma giderildi.
- Ortak TopBar, talepler bağlantısını oturum yetkisine göre çiziyor. Alt dock sütunları görünen menü sayısına uyuyor; Daha Fazla panelinde Escape, odak döngüsü, odak geri dönüşü ve kaydırma kilidi var. API/hesaplama ve kayıt işlemleri değiştirilmedi.
- `mobile-spa/tests/design-smoke.cjs` API mock'larıyla 320/390/480px'de dokuz rota (27 kontrol), izinsiz menü ve rota yönlendirmesi, panel odağı/kapanması ve giriş/şifre görünürlüğünü doğruladı. Canlı API/veritabanı işlemleri test edilmedi. Görev/Rapor ekranlarının mevcut prototip verileri canlıya dönüştürülmedi.
- Üretim derlemesi yenilendi; PWA cache sürümü v18. Tasarım belgesi `mobile-spa/DESIGN.md` güncellendi.

- Personel > Kesintiler sekmesindeki sürekli kesinti düzenleme akışında başlangıç/bitiş tarihi düzeltildi. Arayüzün `GG.AA.YYYY` değerleri Flatpickr `altInput` üzerinden güvenilir biçimde okunup yazılıyor; kesinti API'si işlem, başlangıç ve bitiş tarihlerini Model'e göndermeden önce `Y-m-d` biçimine dönüştürüyor. Böylece başlangıç tarihinin MySQL'e `0000-00-00` yazılması engellendi.

- Bordro hukuki kayıt bütünlüğü güçlendirildi: yeni hesaplama snapshot'ları kullanılan dönemsel görev/maaş geçmişi kaynaklarını ve ödeme mutabakatını saklıyor. Dönem kapatmada hesaplanmamış/geçersiz snapshot, dönemsel sözleşme maaşı–nominal maaş farkı, snapshot–kolon ödeme farkı ve net ödeme–kanal toplamı farkı bloklayıcı kontrol oldu; `force_close` bu mali hataları geçemiyor. İlk uygulamada kontrol yanlışlıkla `donem-guncelle` aksiyonuna yerleştiği için kapanışı engellemedi; kontrol `donem-kapat` bloğuna taşındı ve konum regresyon testi eklendi. Manuel ve Excel ödeme dağıtımları snapshot ödeme/mutabakat alanlarını da atomik güncelliyor.
- Dönem kapatma mutabakat hataları artık tek parça teknik hata metni yerine personel bazlı yapılandırılmış API yanıtıyla dönüyor; arayüz bunları hata sayacı, ayrı personel kartları, açıklama ve kaydırılabilir liste içeren geniş bir SweetAlert penceresinde gösteriyor.
- Bordro kapanışları için değişmez sürüm arşivi eklendi ve migration uygulandı. Her kapanış kişi bazlı tam bordro snapshot'larını SHA-256 ile, dönem manifestini ayrı SHA-256 ile mühürlüyor; yeni sürüm önceki manifest hash'ine bağlanıyor. Arşivleme ve kapatma tek transaction içinde; yeniden açma/hesaplama eski sürümü değiştirmiyor. Arşiv bütünlüğünü yeniden hash ederek doğrulayan Model metodu eklendi.
- Bordro İşlemler menüsüne `Geçmiş Bordro Arşivi (ZIP)` eklendi. Çoklu dönem seçim penceresinden seçilen kapalı dönemleri 7 sayfalı Excel ve `SHA256SUMS.txt` olarak indiriyor; mühürlü sürümler ile eski/mühürsüz kayıtları ayırıyor, kişi/ek ödeme/kesinti/mutabakat/maaş kaynağı/hash ayrıntılarını sunuyor ve formül enjeksiyonuna karşı hücreleri güvenli yazıyor.
- Arşiv ZIP'i olası veri kaybında geri yükleme kaynağı olacak şekilde genişletildi: `Maaş Bordroları` sayfasında tüm bordro alanları, ZIP içinde kişi bazlı kayıpsız snapshot JSON'ları, geri yükleme manifesti, README ve tüm dosyalar için SHA-256 özeti bulunuyor. Otomatik geri yükleme aksiyonu eklenmedi; geri yükleme ayrıca yetki/hash/yedek/transaction denetimi gerektiriyor.
- Bordro listesinde hesaplanmış/geçmiş kayıtların ödeme dağılımı artık gösterim sırasında güncel kurallarla yeniden dengelenmiyor; `bordro_personel` üzerindeki kayıtlı banka, elden, Sodexo ve diğer ödeme değerleri ödeme/kapanış anındaki haliyle korunuyor. Bu davranış `AGENTS.md` Madde 5.1 ve `docs/BORDRO_HESAPLAMA_KURALLARI.md` içinde değiştirilemez snapshot kuralı olarak belgelendi.

## Şu An Ne Üzerinde Çalışılıyor?
- Personel PWA İzin Modülü (`page=izin`):
  - "3 günden uzun süreli izinlerin en az 2 hafta önceden bildirilmesi ve en fazla 20 gün olması" kuralı kesin olarak yalnızca yıllık izin türüne (`isAnnualLeaveType`) bağlandı. Mazeret izni ve diğer sosyal/acil izin türleri bu kısıtlamalardan tamamen muaf tutuldu.
  - Form içerisindeki "3 günden uzun süreli yıllık izin talepleri en az 2 hafta önceden bildirilmelidir" Hatırlatma kutusu statik yapıdan çıkarılarak dinamik hale getirildi; kutu yalnızca "Yıllık İzin" seçildiğinde görünür, "Mazeret İzni" veya diğer izinler seçildiğinde ise otomatik olarak gizlenir.
  - İstemci JS (`views/personel-pwa/pages/izin.php`), PWA API (`views/personel-pwa/api.php`) ve mobil API (`api/mobile/v1/leaves.php`) katmanlarında kural mutabakatı sağlandı.
- Giden Evrak şablon yönetiminde oluşturma ve düzenleme kipleri ayrıştırıldı. Yeni kayıt sonrasında gizli şablon ID'sinin korunup sonraki kaydın önceki şablonun üzerine yazması engellendi; sunucu `kayit_modu=create|update` değerini doğruluyor, arayüz hangi şablonun güncelleneceğini açıkça gösteriyor ve şablon yükleme onayında seçilen ad yer alıyor.
- Evrak Takip PDF önizleme hatası giderildi: UETS/EYP desteği eklenirken `views/evrak-takip/pdf.php` içinden yanlışlıkla kaldırılan ortak `EvrakTakipModel` örneği geri eklendi. Böylece hem `evrak-takip/list` kayıt önizlemesi (GET) hem de `giden-evrak.php` form önizlemesi (POST) yeniden aynı yetki kontrollü PDF üretim akışını kullanıyor.
- Mobile SPA — Tüm Sayfaların Merkezi Soft Bento & Claymorphic UI Standartlarına Tam Harmonizasyonu:
  - Kullanıcının "fatura ve kasa sayfalarını da yap" talebi doğrultusunda:
    - `TopBar.vue`: `#actions` slot desteği eklendi; böylece tüm sayfalar kendi özel aksiyon butonlarını (Yenile, Geçmiş, Fatura Kes vb.) merkezi üst navigasyon çubuğu içinde tutarlı biçimde sunabiliyor.
    - `KasaView.vue`: Eski cam/glacier blur stilleri tamamen kaldırılarak Soft Bento & Claymorphic UI mimarisine geçirildi:
      - Merkezi `TopBar` entegrasyonu (başlık, firma adı, dairesel geçmiş ve yenileme butonları).
      - Saf beyaz `#ffffff` Bento ana bakiye kartı (28px kavis, 3D yumuşak gölge, segmentli doluluk barı, durum rozeti).
      - İkili beyaz Bento gelir/gider kartları ve aktif obsidian seçim vurgusu.
      - `.clay-select-pill` tarih seçicileri, `.clay-tabs-wrap` segmentli tür filtreleri ve `.clay-search-bar` arama çubuğu.
      - 22px kavisli beyaz `.kasa-row-card` hareket kartları, yürüyen bakiye çipleri ve detay aksiyonları.
      - Sağ altta yüzen obsidian koyu dairesel FAB (+ butonu).
      - Beyaz zeminli, 32px kavisli bottom sheet form, fiş ve geçmiş modalları.
    - `FaturaView.vue`: Eski cam/blur efektleri tamamen temizlenip Soft Bento standartlarına dönüştürüldü:
      - Merkezi `TopBar` entegrasyonu (başlık, açıklama, dairesel yenile ve fatura kes butonları).
      - Saf beyaz 999px hap dönem filtreleri (`.fatura-period-pill`, aktif durumda obsidian zemin).
      - `.clay-tabs-wrap` 4'lü modül sekmeleri (Özet & KDV, Giden, Gelen, Taslaklar).
      - Saf beyaz Bento KPI kartları ve belirgin üst kenar vurgulu Net KDV kartı.
      - 3 katmanlı temiz Bento `.fatura-row-card` ve `.fatura-card` satırları.
      - Beyaz zeminli arama çubuğu ve filtre hapları.
    - `npm run build`: Vite üretim derlemesi hatasız olarak tamamlandı (`dist/assets/index-OsLLZqTY.css`, `dist/assets/index-CxM35ZQT.js`).



- Mobile SPA Fatura Modülü (@mobile-spa & @mobile referansı):
  - Kullanıcının talebi ve "Daha Fazla" menüsündeki "E-Fatura & Finans" butonu yönlendirmesi doğrultusunda, `@mobile`'daki `efatura.php`, `efatura-giden.php`, `efatura-gelen.php`, `efatura-taslak.php` ve `efatura-olustur.php` yetenekleri `mobile-spa` mimarisine (Glacier Alpine Design System) tam uyumlu olarak aktarıldı.
  - Backend API: `mobile-spa/public/api/fatura.php` ve `mobile-spa/dist/api/fatura.php` oluşturuldu. Session, Gate yetkileri (`efatura/dashboard`, `efatura/giden-list`, `efatura/gelen-list`, `efatura/taslak-list`, `efatura/olustur`, `efatura/yanit`), CSRF token doğrulaması, Model katmanı (`EInvoiceModel`, `EFaturaMalHizmetModel`, `EInvoiceService`) üzerinden parametrik PDO sorguları ile güvenli hale getirildi.
  - Frontend Servisi: `mobile-spa/src/services/fatura.js` oluşturuldu (Dashboard, listeleme, önizleme, mükellef kontrolü, cariler, kalemler, hesaplama, taslak kaydetme/silme, GİB'e gönderme, ticari yanıt verme).
  - Görünüm Bileşeni: `mobile-spa/src/views/FaturaView.vue` oluşturuldu (Dönem seçici, 4 sekmeli mimari, KDV/KPI kartları, önizleme modalı, yanıt sheet'i, 4 adımlı fatura sihirbazı).
  - Fatura Satırları & Kart Modernizasyonu:
    - Kullanıcının ekran görüntülü geri bildirimi ("fatura satırları biraz karışık görünüyor, daha düzenli ve güzel yapalım") üzerine Son Faturalar listesindeki sıkışık `.fatura-recent-item` yapısı Glacier Alpine standartlarında 3 katmanlı `.fatura-row-card` mimarisiyle baştan tasarlandı.
    - Header: Mono fatura no rozeti (`.fatura-no-tag--sky` / `.fatura-no-tag--emerald`), takvim ikonlu tarih hapı (`.fatura-date-pill`) ve canlı durum noktalı rozet (`.fatura-badge` + `.fatura-badge-dot`).
    - Body: Cari/müşteri unvanı için 2 satır sınırlaması (`-webkit-line-clamp: 2`), `word-break: break-word` ve dengeli tipografi.
    - Footer: İnce ayırıcı çizgi, "Ödenecek Tutar" alt etiketi ve 16px bold sembollü tutar (`formatInvoiceMoney`: ₺, $, €), sağda "Fatura Gör" aksiyon hapı.
    - Kart Görünürlük & Taşma Sorunlarının Giderilmesi ("kartların bir kısmı görünmüyor"):
      1. Taşma (Overflow) Düzeltmesi: `.fatura-card__items-preview` içindeki uzun ürün içeriklerinin grid/flex sınırını delip kartı ekranın sağından dışarı taşırması (böylece durum rozeti ve sağdaki butonların kaybolması) engellendi; flex-column, `min-width: 0`, `max-width: 100%`, `overflow: hidden`, `text-overflow: ellipsis` uygulandı.
      2. Tutar Sıfırlanma Çözümü: Model'in `odenecek_tutar` alanını string (`"8.226,00 TRY"`) dönmesi nedeniyle oluşan `0,00 ₺` sorunu `parseMoneyAmount()` ile çözüldü; ham float ve string formatları tam desteklendi.
      3. Alt Navigasyon Örtüşmesi: Sayfa altına `padding-bottom: calc(140px + var(--ds-safe-bottom, 24px))` ve kart listesi altına `50px` verilerek en alttaki kartların BottomNav barının arkasında kalması engellendi.
  - Router & BottomNav: `/fatura` ve `/efatura` rotaları tanımlandı; `BottomNav.vue` menüsünde "E-Fatura & Finans" butonu SPA rotası `to: '/fatura'` olarak güncellendi.
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı ve `mobile-spa/dist/` klasörüne aktarıldı.

- Bordro Detay modalında (eski/kompakt görünüm) kullanıcı geri bildirimleri doğrultusunda 4 kritik geliştirme tamamlandı ve doğrulandı:
  1. Sözleşme Farkı & Gayriresmî Prim Dağıtımı (Kural 5): Maaşa dahil sosyal yardım alan personellerde primlerin (kaçak ihbar primi vb.) yemek tavanını (300 TL x 24 gün = 7.200 TL) dolduran kısmı yemek yardımına aktarıldı (2.603,80 TL). Yemek tavanını aşan artık bakiye (496,20 TL) gayriresmî prim niteliğinde olduğundan muhasebeye bildirilen banka matrahına ve resmî alacak listesine dahil edilmedi; doğrudan Elden Ödemeye aktarıldı (Banka: 28.779,80 TL, Elden: 496,20 TL). Resmî Alacak listesinde yapay "Ek Kazanç / Prim Payı" veya "Sözleşme Farkı" satırı kaldırıldı; yemek yardımının açılır detayında "Sözleşme İçindeki Yemek Payı: 4.596,20 TL" ve "Olumlu Kaçak İhbar Primi (Yemek yardımına aktarılan): 2.603,80 TL" tam şeffaflıkla gösterildi.
  2. Kart Borderları: Modal kartlarının sınırları (`.main-card`, `.header-glass`, `.card-header-tint`, `.footer-row td`) silik `#eef0f2` yerine belirgin, koyu ve şık slate gri (`border: 1.5px solid #cbd5e1`, macOS Dark'ta `border: 1px solid rgba(255, 255, 255, 0.16)`) yapıldı.
  3. Kesintiler Detayı: Kesintiler kartında (3. kart) generic "Diğer banka kesintileri" yerine gerçek kesinti adı (örn. "Malzeme Zarar - Ziyan") ve altında açıklaması ("Ömer Faruk Dağ tablet ekranı kırık bize verilen güncel fiyat 95 dolar 4.624 tl") net şekilde gösterildi.
  4. Ödenecek Net Maaş Kartı Toggle Düzeni: Kartın sağ üst köşesine aşağı doğru çapraz ok (`↘`) butonu eklendi. Tıklandığında 3 kart eşit kolon olarak yan yana kalırken, Net Maaş kartı altta tam satır (`full-width`) genişliyor ve buton yukarı çapraz oka (`↖`) dönüyor; tercih `localStorage` (`bordro_old_detail_net_fullwidth`) ile saklanıyor.
- Eski bordro detayındaki Kesintiler kartı ve banka tooltip'i, kesinti kaydının açıklamasını kalem adının altında gösteriyor; özellikle Diğer Kesinti'nin gerekçesi artık muhasebe kullanıcısına görünür.
- Kayıtlı bordro snapshot'ındaki `banka_matrahina_ekle` ödeme tercihi artık yanlışlıkla `resmi_alacagina_dahil` kabul edilmiyor. Furkan Akşeker Eylül 2026 örneğinde resmî tutarı sıfır olan hafta sonu nöbeti ile teknik `yemek_yardimi_dengeleme` kaydı resmî ek kalem adaylarından çıkarıldı; yemek yalnız ana Yemek Yardımı satırında bir kez gösteriliyor ve gerçek HTÇ gibi resmî kalemlerin dağılım sırası korunuyor.
- Prim usulü ve sabit maaşı olmayan personelin eski bordro detayındaki yanıltıcı sıfırlar temizlendi: günlük ücret `0,00` yerine `-`, dönem tabanı `Sözleşme Hakedişi / 0,00 × gün` yerine `Puantaj / Prim Hakedişi`, sıfır tutarlı sözleşme HTÇ satırı gizli ve sıfır kesinti `-0,00` yerine `0,00` gösteriliyor. Furkan Akseker örneğindeki 17.480,00 TL puantaj/prim tabanı ile 1.500,00 TL nöbet ek kazancı korunuyor.
- Eski bordro detayının Resmî Alacak kartındaki Yemek Yardımı satırı açılır kırılıma dönüştürüldü. Toplam yemek yardımının sözleşme içindeki payı ile Aylık Araç Kirası ve Olumlu Kaçak İhbar Primi gibi yemek havuzuna aktarılan kazanç payları etiketleriyle ayrı alt satırlarda gösteriliyor; böylece Sözleşme Alacağı kartındaki kazanç ile resmî yemek kalemi arasındaki ilişki izlenebiliyor.
- Eski bordro detayındaki sözleşme kazanç satırları artık teknik tür kodu yerine dönemde geçerli bordro parametresi etiketini gösteriyor (`Aylık Araç Kirası`, `Olumlu Kaçak İhbar Primi`). Sözleşme/Resmî ana akordeon tetikleyicileri açık `show/hide` kontrolünde; yan yana eşit yükseklik CSS'i artık yalnız normal ve `.collapse.show` satırlarını tablo satırı yapıyor, kapalı araç/prim/yemek alt kırılımlarının `display:none` durumunu ezmiyor.
- Banka ödeme detayında maaşa dahil yemek havuzunu büyüten birden fazla ek kazanç olduğunda doğrudan banka bakiyesinin kayıt sırasına göre yanlış kaleme yazılması düzeltildi. Resmî kalemler doğrudan banka payında öncelikli uzlaştırılıyor; Mehmet Akif Kocataş Eylül 2026 örneğinde araç kirası 2.100,00 TL tamamen yemek havuzunda, resmî kaçak ihbar priminin 303,75 TL'si ayrı banka eki ve 96,25 TL'si yemek havuzunda açıklanıyor. Toplam banka/elden sonucu değişmiyor.
- Eski/kompakt bordro detay penceresindeki yerleşim Alt Alta / Yan Yana düğmesiyle değişiyor. Yan yana görünümde ilk üç hesap kartı eşit ve geniş, dördüncü Net Ödeme kolonu minimum 220 px korunarak yaklaşık `%38` daha dar gösteriliyor. Net ödeme kartı diğer beyaz kartlardan çok açık yeşil yüzey, yumuşak yeşil sınır ve düşük yoğunluklu gölgeyle ayrılıyor; koyu temada karşılık gelen yeşil ton kullanılıyor.
- Maaşa dahil sosyal yardım önceliği düzeltildi: sözleşme bakiyesi önce günlük tavanına kadar yemek yardımına, yalnız kalan tutar eş yardımına ayrılıyor. Puantaj yemek günü hesabı birden fazla çalışma geçmişi aralığının birleşimindeki yalnız `X` günlerini sayıyor; HTÇ/RTÇ yemek gününe eklenmiyor. Fahri Ayaz Eylül 2026 verisinde fiilî yemek günü 10 yerine 20 olarak doğrulandı; mevcut kuralla yemek 6.000,00 TL ve kalan eş yardımı 339,60 TL hesaplanıyor.
- Bordro detay modalındaki banka ödeme açılımı düzeltildi: avans/icra sonrası kalan banka tutarı artık yanlışlıkla `Asgari Ücret (Net)` diye gösterilmiyor; asgari taban günlük net asgari ücret × banka günü formülüyle açıkça gösteriliyor. Snapshot içindeki gerçek avans/icra kalemleri okunuyor; banka artırıcıları yeşil `Eklenenler`, kesintiler kalem bazında kırmızı `Çıkarılanlar` altında ayrılıyor ve `Kesinti Öncesi Banka Toplamı` ara sonucu gösteriliyor. Fazla Çalışmalar kartında ana tutarlar net, hover detayları hedef net + yasal kesintiler + brüt olarak sunuluyor. Net maaşlı personelin Normal Kazançlar kartı görev geçmişi parçalı olsa da sözleşme günlüğü yerine asgari net günlüğü kullanıyor; tamamlayıcı tutar Sözleşme Farkı olarak ayrılıyor. Footer'daki Brüt Toplamı kutusuna sözleşme hakedişi, tam HTÇ hakedişi, diğer kazançlar ve toplamı mutabık gösteren hover açılımı eklendi; Fazla Çalışmalar kartı değiştirilmedi.
- Demirbaş/Zimmet listesinde aynı firma, geçerli seri numarası, personel ve teslim tarihindeki aktif mükerrer zimmetler artık gizlenmeden açık kırmızı satır zemini ve `Mükerrer seri numarası` rozetiyle gösteriliyor. Zimmet sayfasına mevcut global/sütun/kategori/durum/personel filtrelerini sunucu tarafı liste sorgusuna taşıyan yetki kontrollü Excel dışa aktarma butonu eklendi; Excel'de seri numarası ve mükerrer uyarısı ayrı sütunlarda bulunuyor.
- Personel PWA izin taleplerindeki “3 günden uzun izinler en az 2 hafta önce bildirilmelidir” kuralı yalnızca yıllık izin türüne sınırlandırıldı. Evlilik ve diğer yıllık olmayan izinler bu ön bildirim kontrolünden muaf; istemci ve API doğrulamaları aynı yıllık izin tespitiyle çalışıyor.
- Demirbaş/Zimmet sayaç kayıtlarında hızlı çift tıklama ve eşzamanlı isteklerin ürettiği mükerrer kayıt engellendi. Standart zimmet düğümü istek süresince kilitleniyor; sayaç API akışı doğrudan tablo yazmak yerine `DemirbasZimmetModel::zimmetVer()` kullanıyor ve demirbaş satırı `FOR UPDATE` ile kilitlenerek stok/mükerrer kontrolü atomik yürütülüyor.
- Geçmiş sayaç zimmet temizliği, ortak/tekrarlı seri değerlerinin geniş sonuç üretmesini önlemek için zorunlu `@target_serial_no` ile tek seri bazında çalışacak şekilde daraltıldı. Script aynı seri farklı demirbaş kartlarında bulunsa da en küçük zimmet ID'sini koruyup diğer zimmetleri ve bağlı hareketleri soft delete ediyor; varsayılan önizleme modunda ve veritabanına uygulanmadı. Model kontrolünde boş, `0`, `-`, `yok`, `n/a` benzeri seri değerleri seri bazlı eşleştirmeden çıkarıldı.
- Demirbaş/Zimmet tablosundaki gerçek çift gönderimleri genel seri tekrarlarından ayırmak için aynı firma, seri, personel ve teslim tarihinde 120 saniye içinde oluşmuş aktif sayaç zimmetlerini listeleyen salt-okunur `sql/2026_10_13_find_duplicate_sayac_zimmet.sql` sorgusu hazırlandı.
- Personel PWA Kaçak sayfasında onaylanmış ve iptal edilmemiş tutanaklara ekip üyelerinin sonradan fotoğraf, video veya açıklama ekleyebilmesi tamamlandı. Açıklama `appendKacakMedia` güvenilir gönderim işlemiyle mevcut açıklamanın sonuna ekleniyor; sahiplik, Kaçak departmanı, onay ve aktiflik kontrolleri sunucuda korunuyor.
- Araç silme güvenliği sıkılaştırıldı: silinmemiş yakıt veya KM kaydı bulunan araçlar sunucu tarafında silinmiyor; engellenen denemeler adet, kullanıcı, IP ve gerekçeyle loglanıyor. Bağımlı kaydı olmayan araçların başarılı silme logu araç anık görüntüsü ve bağlı kayıt adetleriyle aynı transaction içinde yazılıyor.
- `06 DFD 805` (ID 159) ve `46 ALA 432` (ID 164) ile aynı anda silinen bağlı kayıtları zaman damgası kontrollü geri getiren tekrarlanabilir script `sql/2026_10_13_restore_deleted_vehicles_06_dfd_805_46_ala_432.sql` olarak hazırlandı; veritabanına uygulanmadı.
- Personel başına tek aktif araç zimmeti engeli kaldırıldı; araç başına tek aktif zimmet kuralı korundu. Personel PWA KM akışı aktif araç listesini kullanıyor, birden fazla araçta seçim istiyor ve seçilen aracın aktif zimmetini API tarafında doğruluyor.
- Araç Takip > Yakıt Kayıtları sekmesindeki araç kaynağı ana araç listesi filtresinden ayrıldı. Yakıt plaka filtresi ve yakıt kayıt modalı artık aktif normal araçlarla birlikte silinmemiş ikame araçları da listeliyor.
- İhbar Yönetimi listesindeki DataTables kolon filtreleri düzeltildi. `IhbarModel::ajaxList()` artık merkezi filtre bileşeninin gönderdiği metin, tarih, boş/dolu ve çoklu seçim kiplerini ayrıştırıp parametrik SQL koşullarına dönüştürüyor. Durum select filtresi seçenekleri, Model katmanından firmanın tüm aktif ihbarlarında gerçekten kullanılan `DISTINCT` durumlar alınarak oluşturuluyor.
- Kaçak İşlemleri > Ekip Özeti ekranındaki 403 hatası giderildi. Kaçak ekranı artık genel `get-report-table` yerine `get-kacak-report-table` aksiyonunu çağırıyor; yeni aksiyon mevcut `kacak_islemleri` yetkisine bağlı bağımsız API politikasıyla korunuyor.
- Veritabanı politika değişikliği `sql/2026_10_13_add_kacak_report_table_policy.sql` içinde tekrarlanabilir script olarak hazırlandı.
- Evrak Takip ekranındaki UETS senkronizasyonu iki aşamalı hale getirildi: `UETS / KEP Senkronize Et` önce canlı gelen evrakları seçim modalında listeliyor, ardından yalnızca kullanıcının seçtiği evrakları sisteme aktarıyor.
- Önizlemede daha önce UETS kimliğiyle aktarılmış kayıtlar seçilemez; aynı tarih + konu + kurum bilgileriyle eşleşen manuel evraklar olası mükerrer olarak sarı uyarıyla gösterilir.
- UETS indirme işleminden sonra durumun `read` olacağı API dokümanına göre önizleme sorgusuna `read` kayıtları da eklendi; HTTP/API hataları artık boş liste gibi gizlenmek yerine kullanıcıya gösteriliyor.
- UETS mesaj kimlikleri istemciye açık gönderilmiyor; seçim anahtarları `Security::encrypt()` ile korunuyor ve aktarım sırasında sunucuda yeniden çözülüp canlı UETS listesinden doğrulanıyor.
- UETS / KEP Entegrasyon Ayarları (`#modalUetsAyarlar`) ve Giden Evrak E-Tebligat Gönderim modalı (`#modalUetsGonder`) form elemanları `App\Helper\Form` (`Form::FormFloatInput()` ve `Form::FormSelect2()`) standartlarına dönüştürüldü.
- `uets_adresi`: `Form::FormFloatInput()` (floating label, `bx bx-id-card` ikon).
- `api_environment`: `Form::FormSelect2()` (floating label, `bx bx-globe` ikon, `dropdownParent: $('#modalUetsAyarlar')`).
- `api_key`: `Form::FormFloatInput()` (`password-field-wrapper`, `btn-toggle-password` ile göster/gizle ikonu, `bx bx-key` sol ikon).
- Modal içerisindeki Select2 elemanları z-index ve katman taşmalarını önlemek için `dropdownParent` ile modal container'a bağlandı.
- CSS tarafında `.password-field-wrapper` ve `.btn-toggle-password` kuralları `assets/css/style.css`'e eklenerek sistem genelinde standartlaştırıldı.

- Mobile SPA (`mobile-spa/src/views/TaleplerView.vue`): Talepler sayfasında mükerrer kategori sekme barı kaldırıldı; üst KPI bento kartı doğrudan kategori geçiş butonları olarak optimize edildi.
- Aktif kategori kartındaki yazı rengi ve kontrastı düzeltildi (`color: #ffffff !important`); başlık ("Avans", "İzin", "Genel") ve sayaçlar koyu zemin üzerinde net ve okunabilir hale getirildi.
- Üst KPI kartı kompakt/minimal hale getirildi: Global `styles.css` içindeki `.hero-card { min-height: 210px; }` kuralı nedeniyle kartın altında oluşan ~150px'lik boşluk giderildi; `hero-card` mirası kaldırılarak `min-height: 0 !important; height: auto !important;` ile kartın sadece içeriği kadar yer kaplaması sağlandı.
- Sıralama butonu metinsiz, kompakt dairesel ikon butonuna (`ArrowUpDown`) dönüştürüldü; üzerine tıklanınca mobil yerel sıralama seçicisi şeffaf katman üzerinden açılmaya devam ediyor.
- **Mobil SPA Ana Sayfa (`HomeView.vue`) — Claymorphism / Soft Bento Yenilenmesi**:
  - Kullanıcının ilettiği referans tasarıma (SugarCRM / Soft Bento App UI) birebir uyumlu olarak Ana Sayfa yeniden tasarlandı:
  - Zemin yumuşak, mat ve sakin taş/açık gri (`#e6eaef`) renge çekildi.
  - Üstte marka rozeti (`⚡ ersan`) ve sağda dairesel eylem butonları (Mail, canlı bildirim noktalı Zil ve kullanıcı avatarı) konumlandırıldı.
  - Büyük, cesur başlık alanı (`GÜNLÜK ÖZET` ve `Operasyon & Saha`) eklendi.
  - **1. Bento Kart (Personel Durumu - Saf Beyaz)**: 28px yuvarlatılmış köşeler, yumuşak 3D gölge, 5 kişilik avatar yığını (`stack-avatar` ve renkli sayı rozetleri), segmentli yuvarlatılmış çok renkli hap ilerleme çubuğu (mavi, sarı, mercan) ve 2x2 metrik ızgarası ile dairesel yönlendirme butonu.
  - **2. Bento Kart (Filo Durumu - Kömür/Obsidian Koyu Kart)**: Referans görseldeki koyu kart kontrastı uygulanarak `#191d24` zeminli, zümrüt/mercan/kehribar segmentli ve beyaz dairesel detay butonlu filo yönetim kartı eklendi.
  - **Hızlı İşlemler & Operasyon Sayaçları**: Yuvarlatılmış beyaz bento kutuları, renkli simgeler ve temiz tipografi ile güncellendi.
- `npm run build` ile üretim derlemesi başarıyla tamamlandı.
- Bordro ek çalışma dağılımı netleştirildi: fazla mesai/hafta içi nöbet, RTÇ ve HTÇ maaşa dahil yemek havuzunu artık artırmıyor veya azaltmıyor. Muhasebe hedef neti ayrı banka kalemi, hedefi aşan gerçek hakediş elden bakiye olarak dağıtılıyor.
- Eski bordro snapshot'larının kayıtlı banka/elden tutarları ve kalem dağılımı aynen gösteriliyor. Yeni muhasebe hedefi eski kaydın satırına gösterim sırasında uygulanmıyor; teknik snapshot farkı satırı kaldırıldı.
- Maaşa dahil yardım dağımında hafta içi nöbet/fazla mesainin resmî banka neti ek ödeme snapshot'ına ayrı yazılıyor. Nihai banka matrahı gerçek hakediş yerine bu resmî net alanını kullanıyor; böylece HTÇ kalan banka boşluğuna göre kırpılmıyor, elden bakiye bankaya aktarılıyor.
- Nihai resmî ek çalışma filtresine `hafta_ici_nobet`/yemekten bağımsız ek çalışma türleri de eklendi. Snapshot detayı resmî kalemlerde `net_etki` yerine öncelikle `resmi_banka_net` okuyor; yeniden hesaplama sonrasında gerçek nöbet hakedişi resmî banka hedefi gibi kullanılmıyor.
- Kompakt Bordro Detayında Yemek Yardımı alt kaynak menüsü kaldırıldı. Fazla Mesai/Hafta İçi Nöbet satırı toplam saat ve saatlik neti; HTÇ satırı çalışılan gün ile günlük resmî neti açılır alt satırda gösteriyor.
- Bordro detayında tam Sözleşme Hakedişi zaten ana satırda yer aldığından, bunun teknik alt bileşeni olan `Sözleşme Farkı` satırları ve popover'ı kaldırıldı; parasal hesap değiştirilmedi.
- Kesinti açıklamaları tür güvenli hale getirildi: avans satırına taşınmış `[İcra]` metni gösterilmiyor, yeniden oluşturulan avansın açıklaması avans kaynağından yenileniyor.
- Hesaplanmış bordro listesindeki personel kesintisi, eski `kesinti_tutar` kolonundan değil detay modalıyla aynı snapshot kesinti kalemlerinden okunuyor; brüte tamamlama/yasal kesinti personel kesintisi gibi gösterilmiyor.
- Maaşa dahil yemekte banka matrahına dahil kazançların resmî net üstü payı önce günlük yemek tavanını dolduruyor; yalnız tavanı aşan bakiye elden kalıyor. HTÇ resmî neti ayrı banka kalemi olarak korunuyor.
- HTÇ resmî hedef neti diğer fazla çalışma satırından bağımsız garanti altına alındı. `asgari net / 30 × HTÇ günü` banka matrahına ayrı ekleniyor ve snapshot'a `hafta_tatili_calisma` koduyla kaydediliyor.
- Detay arayüzünün açıklaması boş kesintiye listedeki ilk kesintinin açıklamasını kopyalayan fallback'i kaldırıldı; avans satırına icra açıklamasının yeniden taşınması engellendi.
- `mesai` kısa kodlu Fazla Mesai de yemek yardımından bağımsız resmî ek çalışma olarak sınıflandırıldı; HTÇ ile aynı tutarda olduğunda iki kalemin birbirinin yerine geçmesi engellendi.
- Otomatik bordro dağıtımına ödeme mutabakatı eklendi. Net hakedişten yalnız gerçek personel kesintileri düşülüyor; banka/Sodexo/diğer sonrası açık kalan tutar elden ödemeye tamamlanıyor.

## Bordro aşamalı geçiş — 2026-10-14
- Kompakt detay Kesintiler kartı yalnız banka kırılımını okuyordu; elden_tutardan malzeme kesintisi gizleniyordu. Kart tüm snapshot personel kesintilerini okuyan Model özetine bağlandı; ödeme kanalları değiştirilmedi.
- Prim usulü dahil yemek nihai tavanı yalnız puantajla sınırlıydı. Tavan asgari taban ve banka işaretli uygun ek kazançları kapsayacak şekilde düzeltildi; prim tabanına yemek ikinci kez eklenmiyor. Yol yardımı vakası hedef sunucuda yeniden hesaplama ile doğrulanmalı; kişinin dahil yemek profili ayrıca kontrol edilmeli.
- Yeni hesaplar snapshot'ta `ortak_sonuc.surum=1` saklıyor; liste/detay/Excel ortak okuyucu üzerinden bu sonucu tüketiyor. Kayıt öncesi ve yeni sürüm kapanışında kuruş mutabakatı zorunlu. Manuel dağıtım aynı transaction içinde ortak sonucu da güncelliyor.
- RTÇ/HTÇ günleri yeni snapshot'ta saklanıyor. Karma maaş testindeki kaçak ihbar priminin ikinci kez banka beklentisine eklenmesi düzeltildi. 88 bordro testi, 652 doğrulama geçti. Canlı dönemde yeniden hesaplama/veri göçü yapılmadı.
- Aşama 2 ilk parça: sürüm 1 karşılaştırma sonucu eklendi. Tam sayı kuruş, kalem kaynakları, kayıtlı kanallar ve yemek kırılımı taşınıyor; belirsiz hakediş ve eksik resmî net tahmin edilmiyor. Hesaplama motoru/ekran geçişi henüz yapılmadı.
- Aşama 1: salt okunur `auditKayitliBordro` kontrolü ve geçiş belgesi eklendi. Ödeme açığı, bir kuruş fark ve snapshot/kolon kanal farkları test edildi (3 test, 7 doğrulama).
- Kapanışta yalnız icra düşülmesi ve sürümsüz net_maas anlamı farkı risk olarak kaydedildi; üretim hesap davranışı bu aşamada değiştirilmedi.
- Sonraki aşama: sürümlü tek sonuç ve eski/yeni hesap karşılaştırması; mevcut karma maaş 600 TL regresyon farkı çözülmeden yeni motor aktive edilmeyecek.

## Sıradaki Adımlar & Odak Noktaları
- Kullanıcıdan Ana Sayfa'nın yeni Clay/Bento tasarımı hakkında geri bildirim alınması.
- İhtiyaç duyulursa aynı tasarım dilinin diğer ekranlara (Talepler, Personel, Raporlar) yaygınlaştırılması.




## 2026-10-14 - Muhasebe ve Bordro Kontrol Excel'i
- `bordro/list` muhasebe aktarımı tek `Mutabakat` sayfasına indirildi. TC sonrasına Fiilî Gün ve Rapor Günü eklendi; yalnızca muhasebenin gireceği resmî kalemler, uygulayacağı kesintiler ve banka tutarı bırakıldı.
- Seçili personeller için ortak bordro hesabını kullanan, kişi bazında yazdırılabilir bloklar üreten `Bordro Kontrol (Excel)` aksiyonu eklendi.
- Sunucudaki PhpSpreadsheet sürümüyle uyumsuz `SheetView::setShowGridLines()` çağrısı, desteklenen `Worksheet::setShowGridlines(false)` çağrısıyla düzeltildi.
- Bordro Kontrol Excel'indeki yüzlerce puantaj/sayaç hareketi tek `Puantaj Hakedişi`, kaçak kontrol hareketleri tek `Kaçak Kontrol Hakedişi` satırında toplandı; teknik yuvarlama detayı tekrarı kaldırıldı.
- Bordro Kontrol sınıflandırması detay modalıyla eşlendi: yemek/eş yardımı Sözleşme Alacağında tekrar edilmiyor, Yuvarlama Farkı ortak hesap değerinden sözleşme tarafında tek satır gösteriliyor.
- Bordro Kontrol dosyasındaki tekrarlı `Bordro Kontrol | Dönem` başlığı kaldırıldı; ad soyad, TC ve fiilî gün tek koyu satırda beyaz-kalın metinle sunuluyor.
- Bordro Kontrol içindeki dört hesap kartının dış çerçevesi orta kalınlıkta koyu gri yapıldı; iç hücre ızgaraları ince bırakıldı.
- Hafta içi nöbetin muhasebe gösterimi ortak Model metoduna taşındı. Personelin asıl nöbet alacağı sözleşme tarafında korunurken, Bordro Kontrol ve Muhasebe Mutabakat resmî tarafında snapshot asgari netiyle hesaplanan `Fazla Mesai Ücreti (Net)` hedefi kullanılıyor; kayıtlı ödeme kanalları değiştirilmiyor.
- Bordro detay modalındaki Fazla Çalışmalar kartı ve Banka Ödemesi Detayı hafta içi nöbet için aynı resmî hedef neti gösteriyor. Diğer Kazançlar altındaki gerçek Nöbet Ödemesi ve snapshot banka/elden toplamları korunuyor.
- Prim usulü/nominal maaşı olmayan personelde HTÇ maaş farkının hedef neti sıfırlaması engellendi; HTÇ hedef neti gösteriliyor. Banka detay popover'ı maaşa dahil yardımı olmayan personelde de tam kırılımla açılıyor.
# Son düzeltme: elden kesintisinin resmî alacağa eklenmesi

- Bordro detay snapshot okuyucusunda `elden_tutardan` kesintiler banka kırılımından çıkarılırken kesinti detayının varlığı artık korunuyor.
- Böylece eski kayıt fallback'i elden kesintisini yeniden banka kesintisi sayıp `banka + kesinti` ile kesinti öncesi resmî alacağı şişirmiyor.
- Halit Yıldız örneğinde kayıtlı 36.811,35 TL banka, 888,65 TL elden ve 3.000 TL elden trafik kesintisi korunur; resmî alacak 39.811,35 TL yerine 36.811,35 TL gösterilir.

# Hafta içi nöbet kararı

- Genel `prim` kayıtlarını açıklamadan otomatik hafta içi nöbete/fazla mesaiye dönüştüren çalışma kullanıcı talebiyle tamamen geri alındı; yalnız gerçek `hafta_ici_nobet` türü işlenir.
- Gerçek hafta içi nöbet normal/prim hakedişinin üstüne eklenir ve uygulanacak net `max(kayıtlı nöbet neti, asgari net / 225 × 1,5 × saat)` olarak belirlenir.
- Ümit Kara örneğinde 5 günlük 4.679,25 TL tabanın üstüne 4 saatlik 748,68 TL hedef eklenerek 5.427,93 TL hakediş amaçlanır; 500 TL hedefin altında kaldığı için kullanılmaz.
- İlk uygulamada snapshot ek ödeme satırı 748,68 TL'ye yükselmiş olsa da maaşa dahil nihai toplam kurulurken ham 500 TL tekrar okunuyordu. Dahil-yardım havuzu artık `net_etki` değerini öncelikli okuyarak bu geri dönüşü engelliyor.
- Bordro detayındaki Nöbet Ödemeleri satırı da canlı ek ödeme tablosundaki ham tutar yerine hesaplanmış snapshot `net_etki` tutarını gösteriyor.

## Muhasebe Mutabakat gün kolonları — 2026-10-14
- Muhasebe listesinin `Mutabakat` sayfasına TC sonrasında `Toplam Gün` ve `İzin Günü` eklendi; mevcut `Fiilî Gün` ve `Rapor Günü` korundu.
- İzin günü ücretli ve ücretsiz izinlerin toplamıdır. Yeni bordro snapshot'ları ücretli izin gününü de saklar; eski snapshot'lardaki eksik açıklayıcı kırılım dönem kaynağından tamamlanır ve kayıtlı parasal/ödeme kanalı değerleri değiştirilmez.
- Mutabakat kesinti kolonları yalnız banka kanallı snapshot kesintilerinden üretiliyor. Elden kesintisi kırmızı alana eklenmiyor; resmî kalem dağılımı gösterilen banka kesintileriyle birlikte kayıtlı banka tutarına mutabık kalıyor.

## 2026-10-14 — Sözleşme nöbeti / resmî banka regresyonu
- Sözleşme hafta içi nöbeti kayıtlı netiyle korunurken resmî hedefin toplam hakediş tavanına kırpılması düzeltildi. Yeni hesaplamada resmî banka tabanının karşılanamayan bakiyesi `ozet.resmi_taban_tamamlama` olarak saklanıyor; nöbet `net_etki` ve yuvarlama farkı değişmiyor. Elden hakediş yeterliyse ek tamamlama yok.
- Ortak snapshot sonucu `sozlesmeToplamAlacagi` ile sözleşme toplamını ödeme toplamından ayırıyor. Kompakt detay ve Bordro Kontrol sözleşme footer'ı bu tutarı kullanıyor; diğer detayda farkın sözleşme kalemine eklenmesi engellendi. Eski snapshot ödeme kanalları korunuyor.
- Gerçek `hesaplaMaas` akışı için dahil yardımlı ve yardımsız prim usulü nöbet testleri eklendi; kaydedilen snapshot, detay banka kırılımı, muhasebe ve ödeme mutabakatı doğrulandı. Artık kullanılmayan nöbeti sözleşmede yükselten helper ve eski beklenti testi kaldırıldı.
- `vendor/bin/pest tests/Unit --filter 'Bordro|MaasaDahil' --compact`: 90 test, 741 doğrulama geçti. Mevcut canlı legacy gösterimde tanımsız ücretli izin günü için 7 uyarı bulunuyor. PHP sözdizimi ve diff kontrolleri geçti. Veritabanında kayıt değiştirilmedi; mevcut bordro yetkili yeniden hesaplamayla yeni hesabı alır.

## 2026-10-14 — Liste / detay / Excel snapshot mutabakatı
- Bordro Kontrol Excel'i canlı ek ödeme ve kesinti tablolarından ayrıldı; sözleşme ve kesinti kırılımları Model katmanında kayıtlı bordro snapshot'ından üretiliyor.
- Prim usulü sözleşme kartındaki `Puantaj Hakedişi`, tüm personellerde snapshot'a kaydedilmiş gerçek `[Puantaj]`/`[Sayaç]` kaynaklarından üretiliyor; resmî asgari taban bu satıra eklenmiyor. Resmî taraftaki `Asgari Ücret (Net)` ve kayıtlı ödeme kanalları değişmiyor. Aynı puantajın prim usulü kartta ikinci kez ek ödeme satırı olarak çizilmesi engellendi; detay ve Bordro Kontrol Excel'i aynı model özetini kullanıyor.
- Hesaplanmış 164 prim usulü bordro salt okunur tarandı; sözleşme kaynak satırları ile kart/Excel toplamı arasında fark ve negatif satır bulunmadı. Mehmet Durdu Eylül 2026 sözleşme tarafı `0,00 TL Puantaj Hakedişi + 3.500,00 TL Nöbet = 3.500,00 TL`, resmî banka ve ödeme `32.380,41 TL` olarak doğrulandı.
- Prim usulü bordro detayındaki `Puantaj Hakedişi` ana akordeonuna toplam işlem adedi rozeti taşındı. Akordeon açıldığında puantaj işlem adı, adet × birim fiyat ve tutar kırılımları doğrudan gösteriliyor; önceki ikinci puantaj toplam satırı geri getirilmedi ve parasal hesaplara dokunulmadı.
- Resmî Alacak detay dağılımı Muhasebe Excel'iyle aynı önceliğe getirildi: asgari/eş yardımından sonra RTÇ, HTÇ, fazla mesai ve resmî prim ayrılıyor; yemek kalan resmî matrahla sınırlandırılıyor. Böylece Halil İbrahim Karataşlı Eylül 2026 örneğindeki 935,85 TL HTÇ yemek satırında erimeden ayrı gösteriliyor; 14.850,05 TL resmî toplam/banka ödemesi korunuyor. Hesaplanmış 86 HTÇ'li snapshot taramasında detay–Excel HTÇ farkı bulunmadı.
- Resmî kalem öncelik hesabı `BordroPersonelModel::getMuhasebeResmiKalemDagilimi()` altında tekilleştirildi. Detay modalı ve Muhasebe Excel mutabakat sayfası bu metodu kullanıyor; Excel içinde ikinci dağıtım closure'ı kaldırıldı ve geri eklenmesini engelleyen kaynak testi yazıldı. 743 hesaplanmış snapshot/86 HTÇ kaydı taramasında merkezî toplam ve detay–Excel HTÇ farkı `0` kaldı.
- Eski snapshot'larda eksik veya fazla açıklayıcı kalemler negatif teknik satır üretmeden kayıtlı footer toplamına bağlandı.
- 817 hesaplanmış snapshot taramasında sözleşme satır/footer farkı, kesinti satır/footer farkı ve liste–Muhasebe Excel banka farkı sıfır çıktı. 93 bordro testi ve 749 doğrulama geçti; mevcut 7 legacy uyarı devam ediyor.


## 2026-10-15 — Mobil PHP bordro detay modalı
- `mobile/index.php?p=bordro` detay paneli animasyonlu sayfa kapsayıcısından `body` altına taşındı; açılış/kapanış görünürlüğü ve transform durumu bağımsız CSS ile yönetiliyor. Ekran yüksekliği, iç kaydırma ve safe-area uygulandı.
- Mobil API mevcut `get-detail-old` aksiyonunu firma/kayıt kontrolleri ve masaüstü Gate politikası üzerinden sunuyor; kapalı dönemlerde salt okunur detay açılabiliyor.
- Masaüstünün sözleşme alacağı, resmî alacak, kesintiler ve net ödeme/kanal görünümü aynı HTML/snapshot kaynağından mobilde alt alta sunuluyor. Hesap bölümleri başlangıçta açık; satır detayları ve resmî/elden kesinti kırılımları açılıp kapanıyor. Hesaplama ve ödeme tutarları değiştirilmedi.
- Escape, odak geri dönüşü, odak döngüsü, güvenli hata metni ve geciken isteklerin başka paneli değiştirmemesi sağlandı.
- Doğrulama: 390×844 Chromium kontrolü (gerçek salt okunur API yanıtı), 3 Playwright regresyon testi geçti; bordro birim testleri 94 geçti / 751 doğrulama, önceden mevcut 7 legacy uyarı. PHP sözdizimi kontrolü geçti.
- Personel bilgisi kapalı kalırken sözleşme ve resmî alacak accordionları ilk açılışta açık olacak şekilde güncellendi. Resmî alacak badge'i sabit mavi zemin ve beyaz metinle okunaklı hale getirildi; mobil Playwright testi başlangıç durumunu ve renkleri doğruluyor.
- Personel kimlik, dönem ve maaş özet kartı da `Personel Bilgileri` başlıklı bir accordion içine alındı ve ilk açılışta kapalı hale getirildi. Dokunma ve klavye ile açma/kapama davranışı mobil testte doğrulandı.


## 2026-10-15 — Bordro personel sıralaması ve net ödeme kartı
- Personel adı hücresine HTML-escape edilmiş yalnız ad/soyad içeren `data-order` eklendi; fotoğraf HTML ve boşluklarının sıralamayı fotoğraflı/fotoğrafsız gruplara ayırması giderildi.
- Masaüstü kompakt detayın net ödeme kartı koyu lacivert (`#14243e`) yapıldı; yan yana/tam satır görünümünde kanal tutarları açık, açıklamalar açık mavi tonlarla sunuluyor. macOS koyu tema zemini de aynı renge bağlandı.
- Doğrulama: PHP sözdizimi kontrolleri geçti. Chromium üzerinde gerçek DataTables kütüphanesiyle karışık fotoğraf durumlarında artan/azalan ad sıralaması ve 1000/1440px açık/koyu tema kart zemini doğrulandı. Bordro hesapları ve kayıtlı ödeme tutarları değiştirilmedi.


## 2026-10-15 — Bordro fotoğrafsız personel avatarları
- Fotoğrafı olmayan personelde ilk ad ve soyadın baş harfleri 32×32px yuvarlak mavi avatarla gösteriliyor. Türkçe i/ı dönüşümü, tek/çoklu ad ve boş ad destekleniyor. Yüklenemeyen fotoğraf da aynı avatara dönüyor.
- Ad sıralamasındaki `data-order` korundu. PHP sözdizimi, Türkçe baş harf örnekleri ve Chromium üzerinde fotoğraf hata fallback/boyut kontrolü geçti.

## Resmî ücret hesap pusulası / toplu PDF (2026-10-15)
- Muhasebenin `files/ucret_hesap_pusulasi_ersan_elkt İHALE 3.pdf` dosyası incelendi. A4 düzenindeki kimlik/işyeri, çalışma günleri, yasal kesintiler, ek kazançlar ve özet bölümleri yeni ortak şablona alındı.
- `views/bordro/raporlar/bordro.php` dönem seçimi, hazır/hatalı durumları, kayıtlı resmî banka neti, tekil PDF, tümünü yazdır ve toplu PDF indirme olarak düzenlendi. Üst Raporlar kartının doğrudan yazdırma bağlantısı da düzeltildi.
- Eski PDF'deki oturum/yetki, ham ID ve canlı hesap yolları kaldırıldı. `BordroPusulaModel` firma sınırında kayıtlı dönem satırlarını okur; pasif/ayrılmış personeli dışlamaz. Sayfa ve PDF mevcut `bordro/raporlar` Gate yetkisini kullanır. PDF URL'lerinde dönem ve kayıt ID'leri şifrelenir.
- `BordroPusulaService` ortak kayıtlı sonuç ve `BordroYayinIcerikService` ile aynı resmî kalemleri sunar; elden/kart/diğer kanalları PDF'ye taşımaz, neti kayıtlı bankadan alır. Geçersiz/eksik snapshot canlı hesaplanmaz; toplu çıktı eksik personeli sessizce atlamaz, engellenir.
- Kaynak farkı: Muhasebe PDF'si 103 sayfa / 102 tekil kişidir (Fahri Ayaz iki sayfa). Sistem Eylül döneminde 102 kayıt içerir. Bazı yasal kesintiler, yemek ve işyeri bilgileri muhasebe dosyasıyla farklı/eksiktir; bunları muhasebe dosyasına uydurmak için geçmiş bordro değiştirilmedi. Eksik açıklayıcı alanlar `—` ile gösterilir. Muhasebeyle tutar eşitliği için ayrı mutabakat gerekir.
- Kontroller: 103 bordro birim testi / 805 doğrulama geçti (önceden mevcut 7 uyarı); 102 kaydın liste/yayın/PDF kalem eşitliği, tekil/toplu eşitliği, firma izolasyonu ve snapshot değişmezliği salt okunur entegrasyonla doğrulandı. Toplu PDF 102 A4 sayfa ve taşmasız. Chromium'da 102 satır, Select2 ve indirme bağlantıları hatasız; oturumsuz HTTP isteği 403.
- PDF geçici dosyaları web kökü dışında işletim sistemi kullanıcısına özel dizinde tutulur. Yeni SQL/yetki veya bordro hesap kuralı değişikliği yoktur.
- Son bağlantı taramasında Raporlar hızlı indirme ve personel finansal işlemlerindeki eski ham ID bağlantıları da yeni şifreli dönem/kayıt URL'sine geçirildi; finansal sekmedeki yazdır butonu rapor Gate yetkisine bağlandı. Tekil PDF endpointi gerçek servis yoluyla bir A4 olarak doğrulandı.

## Muhasebe bordro Excel karşılaştırması (2026-10-15)
- Resmî Ücret Hesap Pusulaları ekranına geçici `.xlsx` yükleme ve kayıtlı dönem snapshot'ıyla kişi/alan bazında karşılaştırma alanı eklendi. Dosya diske kalıcı kaydedilmez ve sistem bordroları değiştirilmez.
- Eşleştirme önce T.C. kimlik numarasıyla, bulunamazsa benzersiz normalize ad-soyadla yapılır. API yanıtında kimlik numarası maskelenir. Mükerrer Excel satırları atlanmaz; ilk eşleşmeden sonraki satır `Yalnız Muhasebede` olarak görünür.
- Karşılaştırılan alanlar: toplam gün, SGK matrahı, SGK işçi primi, işsizlik işçi primi, gelir vergisi matrahı, gelir vergisi, özel kesinti, damga vergisi, net ödenen/resmî banka, normal gün, yemek, ücretli izin günü ve rapor günü. Para alanları kuruş olarak karşılaştırılır.
- Ekranda toplam/eşleşen/farklı/yalnız muhasebede/yalnız sistemde sayaçları, durum filtreleri ve kişi satırından açılan muhasebe–sistem–fark tablosu bulunur.
- Yüklenen örnekte 103 Excel satırına karşı 102 sistem bordrosu analiz edildi: 102 kayıt farklı, Fahri Ayaz'ın mükerrer ikinci satırı yalnız muhasebede, yalnız sistemde kayıt yok. Farklar özellikle SGK/vergi, yemek ve net ödenen alanlarında yoğunlaşıyor.
- Güvenlik: mevcut `bordro/raporlar` Gate yetkisi, firma/dönem izolasyonu, şifreli dönem ID, CSRF, yalnız `.xlsx`, MIME doğrulama ve 10 MB sınırı uygulandı. Yetkisiz/CSRF/geçersiz dosya senaryoları sırasıyla 403/419/422 döndü.
- Doğrulama: gerçek dosya gerçek HTTP upload akışıyla analiz edildi; 103 sonuç, açılır 6 alan farkı, durum filtresinde tek mükerrer satır ve maskeli T.C. tarayıcıda doğrulandı. Bordro testleri 105 geçti / 817 doğrulama; önceden mevcut 7 legacy uyarı sürüyor.

### Düzeltme — karşılaştırma kaynağı ve ekran konumu
- Karşılaştırma alanı Resmî Ücret Hesap Pusulaları ekranından kaldırılıp `bordro/list` sayfasında özet kartların altına taşındı; işlem mevcut `bordro/list` Gate politikasıyla korunuyor.
- Muhasebenin brüt bordro kolonlarıyla snapshot pusulasını karşılaştıran önceki eşleme kaldırıldı. Yüklenen çalışma kitabında A–R başlıklarını taşıyan `Mutabakat` sayfası bulunuyor ve bizim Muhasebe Excel'inde kullandığımız aynı 16 C–R alanı karşılaştırılıyor.
- Sistem değerleri listeyle aynı ortak hesap, `getMuhasebeOdemeOzeti()`, `getMuhasebeFazlaMesaiNetOzeti()` ve `getMuhasebeResmiKalemDagilimi()` yollarından üretiliyor. Böylece gönderilen Asgari/Yemek/Eş/RTÇ/HTÇ/Fazla Mesai/Prim/Diğer, banka kesintileri ve Bankaya Yatacak tutarı esas alınıyor.
- Çok sayfalı çalışma kitabında Mutabakat başlıkları sayfa sırasından bağımsız aranıyor; farklı şablona anlaşılır hata veriliyor. T.C. öncelikli eşleştirme, maskeleme, CSRF, şifreli dönem ve geçici yükleme kuralları korundu.
- Mutabakat ayrıştırma ve kuruş farkı testleri 2 test / 10 doğrulamayla geçti; ilgili PHP dosyalarının sözdizimi ve diff kontrolü temiz.
- Karşılaştırma bileşeni sürekli görünen liste kartından çıkarıldı. Seçili dönem varken `Dönem İşlemleri > Muhasebe Excel Karşılaştır` menüsüyle açılan geniş, kaydırılabilir modal içinde çalışıyor; sonuçlar ve kişi fark detayları modalda gösteriliyor.
- Kullanıcının gerçek `bordro_ersan_elkt İHALE 3.xlsx` dosyası Mutabakat sayfası içermeyen Logo/muhasebe maaş bordrosudur. Okuyucu bu şablonu da tanıyor ve brüt/net anlamlarını karıştırmadan ortak alanları eşliyor: `T.Gün → Toplam Gün`, `Normal Gün → Fiilî Gün`, `Ücretli İzin`, `Rapor`, yemek tutarı ve `N.Ödenen → Bankaya Yatacak`.
- Muhasebe bordrosundaki brüt fazla mesai/tatil/aile tutarları bizim net Mutabakat kolonlarıyla doğrudan kıyaslanmıyor. Gerçek dosya 103 personel olarak okundu; toplam satırı kayıt sayılmıyor. İlk satır 30 toplam gün, 26 fiilî gün, 7.859,65 TL yemek ve 35.875,50 TL banka olarak doğrulandı.
