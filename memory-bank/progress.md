# Progress

## 2026-10-31 — Mobil SPA KM Onayları Varsayılan Tarih Filtresi
- [x] Bekleyen sekmesi `Tümü`, diğer sekmeler `Bugün` ile açılıyor; önceki günden kalan bekleyen kayıtlar gizlenmiyor.

## 2026-10-31 — Personel PWA KM Fotoğraf Servisi
- [x] `uploads/km_bildirim` doğrudan erişime kapalı (403) olduğundan PWA KM geçmişi `views/personel-pwa/km-foto.php` üzerinden sunuluyor; personel yalnız kendi fotoğrafını görebilir.
- [x] Masaüstü (`views/arac-takip/km-foto.php`, `arac_km_onaylari`), eski `mobile/pages/km-onaylari.php` ve RN (`km.php?action=foto`, JWT + `AuthImage`) da yetkili servise geçirildi.

## 2026-10-31 — Personel PWA Ana Sayfa Endeks Karşılaştırması
- [x] `getEndeksKarsilastirma` API aksiyonu: memur kendi verisi, şef kendi bölge ekibi; kapsam sunucuda belirlenir.
- [x] Şef toplamı yönetici mobil kartıyla birebir eşleşti; şef/memur oturumlarıyla API ve 390px Chrome ekran görüntüsü (açık/koyu) doğrulandı.
- [ ] Sunucuya deploy.

## 2026-10-30 — Personel PWA Ana Sayfa / KM Güncellemesi
- [x] Saha Görevi ve Araç KM kartları durum bazlı animasyonlu sahnelere çevrildi (bekleyen iş / devam eden iş); Chrome 390px ile üç KM ve iki görev durumu doğrulandı.
- [x] KM bildirimi: Sabah/Akşam butonları, fotoğraftan yapay zekâ ile KM okuma, yapışkan gönder butonu, hızlı yanıt (AI arka planda).
- [x] Durum çubuğu rengi tüm sayfalarda gövde rengiyle eşitlendi; servis çalışanı önbellek revizyonu eklendi.
- [ ] Gerçek bir gösterge fotoğrafıyla `read-km-photo` doğruluğu ve arka plan AI onayı canlıda denenmeli.
- [ ] iPhone'da durum çubuğu yazı rengi (açık/koyu sistem görünümü) cihazda doğrulanmalı.
- [ ] Sunucuya deploy; `van.png`, `van-wheel.png` yeni dosyaları ile birlikte commit edilmeli.

## 2026-10-28 — Mobil SPA Personel Takip Modülü
- [x] `PersonelTakipModel` + `api/personel-takip.php` (gün durumu, personel detayı, analiz, mesai ayarı, konum isteği, gecikme açıklaması).
- [x] Yetki: `personnel_tracking` (`personel-takip/list`); sunucu tarafı 403 ve CSRF 419 doğrulandı, yetkisiz kullanıcıda menü/rota gizli.
- [x] Canlı Durum, Harita (Leaflet), Analiz sekmeleri ve personel detay çekmecesi; açık/koyu tema 390px Chrome ekran görüntüleriyle kontrol edildi.
- [x] Açıklama kaydı ve konum isteği uçtan uca test edildi, test kayıtları silindi; `npm run build` alındı.
- [ ] Sunucuya deploy (`git pull` + `mobile-spa/bin/deploy-production.sh`).

## 2026-10-28 — Puantaj Özel Gün Otomasyonu (HTÇ / RTÇ / Arife FM)
- [x] `resmi_tatiller` firma/tür/yarım gün alanları, 2026–2027 tatil takvimi, `personel_fazla_mesaileri.kaynak/otomatik_kural`, `resmi_tatil_tanimlari` yetkisi (SQL, tekrar çalıştırılabilir).
- [x] HTÇ'de göreve başlama kaydı şartı; iş departmanında görev + eşik, diğerlerinde yalnız görev kaydı.
- [x] Resmi tatilde otomatik RTÇ (Pazar'a denk gelirse HTÇ yerine), arifede 13:00 sonrası +4 saat FM.
- [x] 23:00 / 08:00 cron'u, dry-run, firma bazlı sistem logu.
- [x] Resmi Tatil Tanımları sayfası, puantaj başlık işaretleri, Pazar modalında Görev sütunu, ayarlar.
- [x] Pest: 13 test geçti; transaction içinde gerçek yazma ve ikinci çalıştırmada değişmeme doğrulandı; sayfa/modal Chrome ile kontrol edildi.
- [ ] Sunucuda SQL'in çalıştırılması ve crontab satırlarının eklenmesi.

## 2026-10-25 — Mobil SPA Ana Sayfa Kartları Sürükle-Bırak (Drag & Drop) Düzeltmesi
- [x] Tutamaç Dokunmatik & Pointer Desteği: 7 ana sayfa kartının tutamaçlarına (`.card-inner-handle`) `@pointerdown.stop` ve `@touchstart.stop` eklenerek `startHandleDrag()` fonksiyonu ile mobil dokunmatik ekranlar için sürükle-bırak desteği sağlandı.
- [x] Akıllı Hedef Belirleme & Auto-scroll: Kartların dikey sınır koordinatları (`findCardUnderPoint`) anlık hesaplanarak parmak altındaki kart ve konum (`top`/`bottom`) tespit edildi; ekran kenarlarında otomatik sayfa kaydırma eklendi.
- [x] İndeks Kayması Hatası Giderildi: `reorderCards()` metodu yazılarak eleman indeks kayması olmadan sıralama garantilendi, hem dokunmatik hem de masaüstü HTML5 drop bu metoda bağlandı.
- [x] Görsel Geri Bildirim ve Titreşim: Sürükleme sırasında saydamlık/küçülme efekti, hedef konuma parlak 4px kapsül çizgisi, haptic titreşim (`navigator.vibrate`) ve toast bildirimi eklendi.
- [x] Vite üretim paketi (`npm run build`) derlendi ve doğrulandı.

## 2026-10-24 — Mobil SPA 3 Boyutlu Logo, 3D Açılış Görseli ve Animasyonlu 3D Açılış Ekranı
- [x] Kurumsal `ER` logosunun 3 boyutlu altın/titanyum versiyonu (`images/logo-3d.png`) ve tüm PWA/uygulama ikonları (`icon-512.png`, `icon-192.png`, `icon-maskable-512.png`, `apple-touch-icon.png`, `favicon.svg`, `favicon.ico`) üretildi.
- [x] Giriş ekranı logosu köşeleri yuvarlatılmış kare (squircle `124x124px`) yapıldı, dönen halka kaldırılarak yalnızca shine efekti bırakıldı; app-splash arka planı Obsidian (`#191d24`) yapıldı ve dönen çizgiler hardal-beyaz olarak güncellendi.
- [x] `index.html` ve `main.js` içerisine 3D perspektif süzülme, enerji halkaları ve metalik ışık yansımalı tam ekran animasyonlu açılış ekranı (`#app-splash`) eklendi.
- [x] `LoginView.vue`, `CompanySelectView.vue` ve `design-system.css` içerisine sürekli 3D perspektif hareketli ve dokunulduğunda 360° 3D dönen etkileşimli logo animasyonu eklendi.
- [x] `npm run build` ile üretim derlemesi (`dist/`) tamamlandı ve doğrulandı.

## 2026-10-24 — Yönetici Mobil Profil Sayfası
- [x] Şifre değiştirme (API + sheet), bağlı cihaz listeleme/kaldırma, görünüm tercihleri, yardım merkezi tamamlandı.
- [x] PHP sözdizimi, API hata yolları (curl) ve 390px Chrome ekran görüntüleri doğrulandı; `npm run build` başarılı.
- [x] Koyu tema (Açık / Koyu / Sistem) eklendi; açık tema piksel karşılaştırmasıyla korunmuş olarak doğrulandı.

## 2026-10-24 — Mobil SPA Hakediş Silme SweetAlert2 ve Tamamlandı Koruması
- [x] Sistem SweetAlert2 Teması: `mobile-spa` içerisine `sweetalert2` entegre edildi ve masaüstündeki Shadcn/Bento CSS kuralları `design-system.css` içine taşındı.
- [x] Hakediş Silme Onayı: `SozlesmeDetailView.vue` içerisindeki native `confirm()` ve `alert()` pencereleri SweetAlert2 (`Swal.fire`) ile değiştirildi.
- [x] Tamamlandı Durum Koruması (Frontend): "Tamamlandı" durumundaki hakedişlerde silme butonu pasif/soluk (`.action-icon-btn--disabled`, `:disabled="true"`) yapıldı; tıklandığında SweetAlert ile bilgilendirici uyarı verildi.
- [x] Tamamlandı Durum Koruması (Backend): `mobile-spa/public/api/sozlesme-hakedis.php` dosyasında `delete_hakedis` aksiyonunda `tamamlandi` durumundaki hakedişlerin silinmesi bloklanarak 422 HTTP koduyla hata döndürüldü.
- [x] Sözleşme Silme Onayı: `SozlesmeHakedisView.vue` içindeki sözleşme silme onayı da SweetAlert2'ye geçirildi.
- [x] Derleme ve Test: Vite üretim derlemesi (`npm run build`) ve PHP CLI testleri hatasız tamamlandı.

## 2026-10-24 — Mobil SPA Özelleştirilebilir Alt Bar ve Sürükle-Bırak Menü Sıralaması
- [x] `App\Model\MenuModel` içerisine masaüstü sıralamasını koruyan `getMobileMenuOrder`, `saveMobileMenuOrder`, `resetMobileMenuOrder` metotları ve `SystemLogModel` kaydı eklendi.
- [x] `mobile-spa/public/api/auth.php` içerisine `menu_order` oturum çıktısı ile `save-menu-order` ve `reset-menu-order` aksiyonları (CSRF ve whitelist doğrulamalı) eklendi.
- [x] `BottomNav.vue` ve `design-system.css` üzerinde Alt Bar kısayol seçici/sıralayıcı ve "Daha Fazla" menüsünde grup + grup içi sürükle-bırak düzenleme modu tamamlandı.
- [x] PHP sözdizimi, `MenuModel` birim testi ve `npm run build` üretim derlemesi doğrulandı.

## 2026-10-24 — Masaüstü Canlı Destek Rozet/Durum Düzeltmesi
- [x] Soft-delete edilmiş konuşmalar okunmamış rozet toplamından çıkarıldı ve silme anında rozet güncellendi.
- [x] Yönetici müsaitlik durumu firma bazlı kalıcı ayara bağlandı.
- [x] Widget API hata yanıtlarını yakalayıp durumu geri yükleyecek şekilde düzeltildi.
- [x] Masaüstü canlı destek işlemlerine firma kapsamı ve konuşma sahipliği kontrolleri eklendi.
- [x] Durum menüsünde seçili seçenek görünür hale getirildi ve çevrimdışı gösterimi kırmızıya çevrildi.

## 2026-10-24 — Mobil SPA Canlı Destek Modülü
- [x] Mevcut canlı sohbet verileriyle çalışan Soft Bento yönetici ekranı, rota, servis ve yetkili menü girişi eklendi.
- [x] Firma izolasyonu, şifreli konuşma kimlikleri, CSRF, Gate/politika kontrolleri ve merkezi personel fotoğraf servisi uygulandı.
- [x] Liste/arama/filtre/KPI, polling tabanlı sohbet, görsel gönderimi, durum ve müsaitlik yönetimi tamamlandı.
- [x] Mobil API politikaları mevcut `canli_sohbet_ayarlari_sekmesi` yetkisine bağlandı ve yerel veritabanında doğrulandı.
- [x] Üst özet kartı minimal hale getirildi; canlı destek ayarları için mobil bottom-sheet eklendi.
- [x] Masaüstündeki aktiflik, yetkili kullanıcı, çalışma günü ve mesai saati ayarları mobil API üzerinden ortak firma kayıtlarına bağlandı.
- [x] İlk açılış konuşma filtresi `Aktif` olarak ayarlandı.

## 2026-10-24 — Araç İşlem Geçmişi
- [x] SQL yerelde iki kez çalıştırıldı; API ekleme → güncelleme → geçmiş akışı CLI oturumuyla test edildi, test verileri silindi.
- [ ] Sunucuda `database/migrations/2026_10_24_arac_islem_loglari.sql` çalıştırılmalı (çalıştırılmadan araç kaydetme hata verir).
- [x] Yakıt Excel yükleme: araç oluşturma + yakıt kaydı satır bazında tek transaction; tutarsız satır ve litre/tutar sütunu olmayan dosya reddediliyor; başka firmaya ait `external_id` atlanıyor (önceden kaydı diğer firmadan bu araca taşıyordu). Otomatik eklenen araçlar mesajda ve araç işlem geçmişinde görünüyor.
- [ ] Sunucuda `database/migrations/2026_10_24_yakit_excel_sahipsiz_arac_temizligi.sql` önce önizleme SELECT'i ile kontrol edilip çalıştırılmalı.

## 2026-10-23 — Turkcell Mobil İmza Entegrasyonu
- [x] SQL `sql/2026_10_23_add_turkcell_mobil_imza.sql` yerelde iki kez çalıştırıldı (tekrarlanabilir); yetki Yetki Kataloğu ve Yetki Grubu Düzenleme verisinde görünüyor, Süper Admin ve Firma Sahibi rollerine atandı.
- [x] Incremental PAdES imzalayıcı: mPDF (klasik xref) ve Ghostscript (xref stream + object stream) PDF’lerinde art arda iki imza `pdfsig` ile “Signature is Valid”.
- [x] Sahte MSSP sunucusu + test CA ile uçtan uca: başarılı imza (mobil_imza ve eimza türü), telefonda iptal (401) ve yanlış özet senaryoları doğrulandı; test verileri geri alındı.
- [x] Ayarlar sekmesi ve API aksiyonları HTTP üzerinden test edildi (doğrulama, Gate, oturum, hata mesajları).
- [ ] Sunucuda: SQL scripti, `.env` `TURKCELL_MIMZA_AP_ID/AP_PWD`, sunucu çıkış IP’sinin Turkcell’e beyaz listeye eklenmesi, Ayarlar > Mobil İmza’dan hatların girilip “Profil Sorgula” ile denenmesi, gerçek telefonla imza testi.
## 2026-10-23 — Menü Yönetimi 2 Seviye Grup Hiyerarşisi ve Sürükle/Bırak Düzeltmesi
- [x] `views/menu-yonetimi/js/list.js` içerisinde `findGroupParentCard()`, `applyChildStateToCard()`, `updateParentCardUI()` ve `syncCardGroup()` yardımcıları eklendi.
- [x] Ardışık menüler alt menü yapıldığında (`.btn-indent`) veya sürüklendiğinde (`initSortable`) bir üstteki alt menünün değil, bulunduğu grubun `Ana Menü` (`parent_id = 0`) kaydının alt menüsü olması sağlandı.
- [x] `.btn-outdent`, `.btn-move-up`, `.btn-move-down` ve `.inline-menu-form` işlemlerinde alt menü bloklarının bölünmemesi ve `parent_id` bilgisinin korunması sağlandı.
- [x] `App/Model/MenuManagementModel.php` (`updateMenuHierarchy`, `saveMenuData`) ve `views/menu-yonetimi/api.php` üzerinde 3. seviye alt menü oluşumunu kök ana menüye bağlayan sunucu doğrulaması ve grup senkronizasyonu eklendi.
- [x] PHP (`php -l`) ve JS (`node --check`) sözdizimi kontrolleri doğrulandı.

## 2026-10-22 — Veritabanı Yedekleri Yönetim Arayüzü
- [x] Yedek/geri yükleme servisleri ayrıştırıldı, CLI davranışı korundu (tam+artımlı+liste+test VT’ye yükleme doğrulandı).
- [x] 2 saatlik cron uyumu: günde bir tam yedek kontrolü, günlük başarı maili, işlem kilidi.
- [x] Geri yükleme testi: 188 tablo `@SAYIM` ile birebir eşleşti; canlı VT adıyla test engellendi; test VT sonunda boşaltıldı.
- [x] `views/yedekleme` sayfası (§8 standardı): KPI kartları, birleşik liste, arka plan işleri, indirme, detay modalı; açık/koyu tema ve sağ tık menüsü doğrulandı.
- [x] Yetki SQL’i yerelde iki kez çalıştırıldı (tekrarlanabilir); Yetki Kataloğu ve Yetki Grubu Düzenleme ekranında göründüğü doğrulandı; normal kullanıcı sayfa/API’de reddedildi.
- [ ] Sunucuda: SQL scripti, `YEDEK_TEST_VT` veritabanı, cPanel cron `0 */2 * * *`, Google Drive `.env` değerleri.

## 2026-10-22 — Bildirim Tercihleri ve Mobil Push Düzeltmesi (yeniden uygulandı)
- [x] Talep bildirimi alıcıları sayfa yetkisine göre seçiliyor; `ihbar` türü eklendi.
- [x] `BildirimModel::createNotificationWithPush()` ve broadcast `$sendPush` seçeneği eklendi.
- [x] Nöbet, destek, kaçak, KM, evrak ve SPA/mobil ihbar bildirimleri push gönderiyor; `mobile-spa/dist/api/ihbar.php` eşitlendi.
- [x] PHP lint ve alıcı listesi doğrulaması geçti.

## 2026-10-22 — Veritabanı Yedeklerinin Google Drive’a Şifreli Kopyalanması
- [x] Google Drive REST servisi, AES-256-GCM yedek şifreleme servisi ve senkron servisi yazıldı.
- [x] `db_backup.php` yedek sonrası Drive senkronuna bağlandı; yapılandırma yoksa mevcut davranış korunuyor.
- [x] Yetkilendirme/test ve Drive’dan geri alma CLI scriptleri eklendi; `cron/YEDEKLEME.md` güncellendi.
- [x] Yerel testler: 0 B–5 MB gidiş-dönüş, bozulma/kesilme/yanlış anahtar tespiti, sahte Drive ile senkron-rotasyon-geri alma, gerçek 13 MB yedekle bütünlük.
- [ ] Sunucuda Google Cloud OAuth kurulumu ve `php cron/drive_yetkilendir.php --test` doğrulaması.

## 2026-10-21 — Evrak Takip Modülü Koyu Mod (Dark Theme) Düzeltmeleri
- [x] `views/evrak-takip/list.php` sayfasında koyu modda tablo thead'inin açık/beyaz kalmasına neden olan inline CSS ve `table-light` sınıfı kaldırıldı.
- [x] `#evrakTable thead th` ve `#evrakTable thead .dt-filter-row th` hücreleri koyu tema zemin ve açık yazı renkleriyle entegre edildi.
- [x] Tablo renkleri kartın koyu lacivert-antrasit tonuyla tam uyumlu hale getirildi (`#1e222d`, `#171922`, `#15171f`, `#12141a`); zebra satır ve mavi hover yansıması eklendi.
- [x] Tablo kenarlıkları (borders) `border: 1px solid rgba(255, 255, 255, 0.11)` ve hücre kenarlıkları `rgba(255, 255, 255, 0.1)` olarak belirginleştirildi; başlık ve filtre satırları altına ayrım çizgileri çekildi.
- [x] Filtre arama inputlarının kenarlıkları ve focus halkaları iyileştirildi; `assets/css/datatable-filters.css`'e `html[data-theme-preset="macos-dark"]` sıralama SVG kuralları eklendi.
- [x] Tablo gövdesinde metin rengini siyah yapan `#evrakTable tbody td { color: #0f172a !important; }` kaldırıldı ve koyu mod paleti sağlandı.
- [x] Tablodaki ve başlıklardaki `.text-dark` sınıflı elemanlar (`h4`, `h5`, evrak konuları, kurum ve personel adları) koyu modda açık renge uyarlandı.
- [x] Üst araç çubuğu ve işlem butonlarındaki `.bg-white` butonları koyu tema zeminine çekildi.
- [x] Özet KPI kartları (`.summary-kpi-card`), sarı bildirim bandı (`#imzaBekleyenUyari`), dropdownlar ve popover stilleri koyu moda uyarlandı.
- [x] `modal/evrak-modal.php` ve `giden-evrak.php` sayfalarındaki koyu mod kontrast ve bileşen uyumsuzlukları giderildi.
- [x] `/opt/lampp/bin/php -l` kontrolleri başarıyla geçti.

## 2026-10-21 — Personel Detaya Geçiş Lazy-load Tamamlama
- [x] Hızlı personel seçici listesi ilk profil isteğinden çıkarıldı.
- [x] Liste yalnız personel seçici açıldığında ayrı API aksiyonundan yüklenir hale getirildi.
- [x] İlk profil çekirdeği ~1 ms ve ~2,2 KB olarak ölçüldü.
- [x] Sekmelerin yalnız tıklanınca yüklendiği doğrulandı; production build ve API dağıtım kontrolleri geçti.

## 2026-10-21 — Personel Zimmetler ve İş Takip Performans Düzeltmesi
- [x] Binlerce zimmet kaydının tek yanıtta ve DOM'da yüklenmesi engellendi; tam özet + son 100 kayıt modeli uygulandı.
- [x] Zimmet sorgusuna firma izolasyonu ve birleşik performans indeksi eklendi.
- [x] Demirbaş seçenekleri ve personel hızlı listesi kullanıcı aksiyonuna kadar lazy-load edildi.
- [x] İş takip ilk yükü son 50 kayıtla sınırlandı.
- [x] Yoğun veri, PHP lint, production build, public/dist eşitliği ve tekrarlanabilir SQL testleri tamamlandı.

## 2026-10-21 — Mobil SPA Ana Sayfa Performans Kontrolü
- [x] Ana sayfa istemci yükleme akışı, dashboard API ve tüm model kart metotları incelendi.
- [x] Gerçek veride tüm kartlar toplam ~82,87 ms ve ~1,4 KB olarak ölçüldü.
- [x] Personel detayındaki düzeyde ağır eager-load problemi bulunmadı; üç düşük öncelikli optimizasyon fırsatı kaydedildi.

## 2026-10-21 — Mobil SPA Personel Detay Performans Analizi
- Personel detay ve bölüm ekranlarının Vue yükleme akışı, ortak API endpoint'i ve `MobilePersonelModel` sorguları incelendi.
- Gerçek veritabanı üzerinde metot bazlı süre ve yaklaşık JSON boyutu ölçüldü; ana darboğazın kullanılmayan bölüm verilerinin eager-load edilmesi ve özellikle puantaj sorguları olduğu doğrulandı.
- Kod değişikliği yapılmadı; düzeltme öncelikleri ve gerekli indeks/mimari ayrıştırma adımları belirlendi.

## 2026-10-21 — Mobil SPA Personel Detay Performans Düzeltmesi
- [x] Ortak personel detay endpoint'i profil/bölüm kapsamlarına ayrıldı.
- [x] Alt bölüm ekranı yalnız aktif bölümün verisini isteyecek şekilde güncellendi.
- [x] Üç performans indeksi tekrarlanabilir ayrı SQL scriptiyle oluşturuldu ve uygulandı.
- [x] Profil model süresi yaklaşık 505 ms'den 4,79 ms'ye indirildi; puantaj sorgularının yeni indeksleri kullandığı doğrulandı.
- [x] PHP lint, Vite production build, public/dist eşitliği, SQL tekrar çalıştırma ve diff kontrolleri geçti.

## 2026-10-21: Mobil SPA — Personel Detay Sayfasında İş ve Puantaj (İş Takip / Saha Operasyonları) Modülü
- [x] Model Katmanı (`App/Model/MobilePersonelModel.php`):
  - `getPuantajIsTakipDetails`: `yapilan_isler`, `endeks_okuma` ve `kacak_kontrol` kayıtlarını ortak standart veri şemasında birleştiren, özet metrikleri (`toplam_kayit`, `toplam_is`, `sonuclanan_is`, `acik_is`, `endeks_sayisi`, `kacak_sayisi`, `tamamlanma_orani`) ve dinamik iş tipi/sonuç seçeneklerini üreten metot yazıldı.
  - `savePuantajJob`: Personel için manuel iş emri/görev kaydını `yapilan_isler` tablosuna güvenli şekilde oluşturan metot yazıldı.
  - `deletePuantajJob`: İlgili iş emri, endeks veya kaçak kaydını soft delete mantığıyla silen metot yazıldı.
- [x] Backend API (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`):
  - `detail` aksiyonuna `puantaj_items`, `puantaj_ozet`, `puantaj_work_types` ve `puantaj_work_results` verileri bağlandı.
  - `puantaj_is_takip` (GET), `save_puantaj_job` (POST) ve `delete_puantaj_job` (POST) API uçları CSRF ve Gate (`personel_duzenle` / `puantaj_yonetim`) denetimiyle eklendi.
- [x] Mobil İstemci Servisi (`mobile-spa/src/services/personnel.js`):
  - `personnelApi` servisine `getPuantajIsTakip`, `savePuantajJob` ve `deletePuantajJob` fonksiyonları eklendi.
- [x] Vue Arayüzü (`mobile-spa/src/views/PersonnelSectionView.vue`):
  - Hero Obsidian Bento Kartı (Tamamlanan/Açık işler ilerleme çubuğu, 4'lü tıklanabilir KPI legend kartları).
  - Canlı arama, tarih filtresi (Başlangıç/Bitiş) ve dinamik filtre sekmeleri (`clay-tabs-wrap`).
  - Saha İşleri, Endeks Okuma ve Kaçak Kontrol bento kartları (Abone no, İş emri no, Sayaç no, Tüketim m³, Ekip, Açıklama, Detay ve Sil butonları).
  - 3 Adet Teleport Modalı: "Yeni İş Ekle (Manuel)", "İş / Görev Detayı" ve "Silme Onayı".
- [x] Derleme ve Test:
  - PHP syntax kontrolleri (`/opt/lampp/bin/php -l`) hatasız geçti.
  - Vite derlemesi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-21: Mobil SPA — Personel Detay Sayfasında Zimmetler Modülü
- [x] Model Katmanı (`App/Model/MobilePersonelModel.php`):
  - `getZimmetDetails`: Personelin demirbaş ve araç zimmet kayıtlarını ortak şemada birleştirip özet metriklerle birlikte getiren metot yazıldı.
  - `getDemirbasOptions`: Stoktaki teslim edilebilir demirbaşları kalan stok bilgileriyle listeleyen metot yazıldı.
  - `saveZimmet`: Stok kontrolü, mükerrer sayaç kontrolü, işlem logları ve stoktan düşme mantığıyla zimmet kaydı oluşturan metot yazıldı.
  - `iadeZimmet`: Araç zimmetleri için `AracZimmetModel::iadeEt` ve demirbaşlar için `DemirbasZimmetModel::iadeYap` entegrasyonuyla iade ve stok yenileme metodu yazıldı.
  - `deleteZimmet`: Yetki ve durum doğrulamasıyla aktif zimmet kaydını silip stoku geri yükleyen metot yazıldı.
  - `getZimmetHareketleri`: Zimmet kaydına ait tüm teslim/iade hareketlerini ve işlem yapan personeli getiren metot yazıldı.
- [x] Backend API (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`):
  - `detail` aksiyonuna `zimmetler`, `zimmet_ozet` ve `demirbas_options` verileri bağlandı.
  - `save_zimmet`, `iade_zimmet`, `delete_zimmet`, `zimmet_hareketleri` ve `search_demirbas` API uçları CSRF ve Gate denetimiyle eklendi.
- [x] Mobil İstemci Servisi (`mobile-spa/src/services/personnel.js`):
  - `personnelApi` servisine tüm zimmet CRUD fonksiyonları eklendi.
- [x] Vue Arayüzü (`mobile-spa/src/views/PersonnelSectionView.vue`):
  - Hero Obsidian Bento Kartı (Aktif zimmet sayısı, renkli segmentli dağılım barı, 4'lü tıklanabilir KPI legend kartları).
  - Canlı arama ve dinamik kategori filtre hapları (`clay-tabs-wrap`).
  - Zimmet Bento Kartları (Demirbaş ve araç rozetleri, durum badge'i, tarih/miktar/açıklama bilgileri, Detay, İade Al ve Sil aksiyon butonları).
  - 4 Adet Bottom-Sheet Modalı: "Yeni Zimmet Ver", "Zimmet İade Al", "Zimmet Detayı & Hareket Geçmişi" ve "Silme Onayı".
- [x] Derleme ve Test:
  - PHP syntax ve CLI simülasyon testleri (`/opt/lampp/bin/php -l`) başarıyla geçti.
  - Vite derlemesi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-21: Mobil SPA — Sözleşme & Hakediş Modülü Entegrasyonu ve Düzeltmeler
- [x] Backend API: `mobile-spa/public/api/sozlesme-hakedis.php` geliştirildi (Sözleşmeler listesi, KPI özetleri, detay, yeni sözleşme/düzenleme, soft delete, iş artış/azalış revizyonları, süre uzatımları, hakediş dönemleri, kalem imalat girişleri, kümülatif hesaplamalar, EPDK/TÜİK canlı endeks servisi entegrasyonu).
- [x] Dönem İmalat Tutarı Düzeltmesi: Hakediş dönemleri listesinde kümülatif tutar yerine sadece dönemin kendi imalat tutarı (`imalat_donem`) gösterildi; altına kümülatif imalat bilgisi eklendi.
- [x] KDV Dahil Dönem Tutarı Düzeltmesi: Hakediş detayındaki koyu renkli özet bento kartında "KDV DAHİL TOPLAM ÖDEME" kısmında kümülatif tutar (98.733.787,02 ₺) yerine o dönemin KDV dahil tutarı (`(imalat_donem + fiyat_farki) * (1 + kdv / 100)` = 10.575.019,02 ₺) gösterildi; kümülatif KDV dahil tutar altına yerleştirildi.
- [x] İmalat Miktarı Girişi Modalı Düzeltmesi: `HakedisDetailView.vue` içerisindeki bottom sheet modalında `.form-grid`, `.form-field`, `.calc-preview-box` ve `.preview-item` CSS stilleri tamamlanarak 2 sütunlu, modern, etiket-input çakışması olmayan mobil bento form düzeni sağlandı.
- [x] Yetkilendirme & Güvenlik: `auth.php` içerisine `contracts` Gate/menü izni eklendi. Tüm API uçlarında Gate denetimi, session firma kontrolü, CSRF koruması ve `Security::encrypt/decrypt` ID şifrelemesi uygulandı.
- [x] Servis Katmanı: `mobile-spa/src/services/sozlesme-hakedis.js` oluşturuldu.
- [x] Vue Arayüzleri: `SozlesmeHakedisView.vue`, `SozlesmeDetailView.vue`, `HakedisDetailView.vue`.
- [x] Rota ve Menü Entegrasyonu: `router.js` içerisine `/sozlesme-hakedis` rotaları, `BottomNav.vue` içine "FİNANS & MUHASEBE" kategorisinde "Sözleşme & Hakediş", `HomeView.vue` içine hızlı erişim seçeneği eklendi.
- [x] Derleme & Test: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı, PHP sözdizimi ve CLI simülasyon testleri başarıyla geçti.


## 2026-10-21: Ayarlar — Avans Ayarları Kaydetme Yetki Hatası
- [x] API reddinin form içi kontrolden değil, eksik merkezi `permission_policies` kaydından kaynaklandığı doğrulandı.
- [x] `avans_ayarlari_sekmesi` aktif ve benzersiz yetkisi için tekrarlanabilir SQL oluşturuldu.
- [x] `save_avans_ayarlari` aksiyonu aynı yetkiye bağlı `write` politikası olarak tanımlandı.
- [x] Yeni yetki yalnızca `role_type = 'superadmin'` veya `superadmin = 1` rollerine `NOT EXISTS` kontrolüyle atandı.

## 2026-10-21: Dashboard — Görev & Evrak Takvimi Kartı Yükseklik Eşitlemesi (260px)
- [x] Kart Yüksekliği Birebir Eşitlendi: `widget-takvim-alarmlar` kartı (`.cal-alarm-card`), yanındaki operasyonel kartların (Endeks Okuma, Sayaç Değişimi, Kesme Açma) `.stat-card` yüksekliği olan tam **260px** (`height: 260px !important; min-height: 260px !important; max-height: 260px !important;`) ile pikseli pikseline eşitlendi.
- [x] İç Düzen ve Dikey Oranlama: Takvim ızgarası satır yüksekliği `grid-auto-rows: 23px; gap: 2px;`, gün hücreleri `22x22px` ve sağ alarm listesi kaydırma alanı `max-height: 168px;` olarak güncellenerek 260px içine kusursuz oturtuldu.
- [x] Önbellek ve Skeleton Güncellendi: `views/home.php` dosyasında skeleton yüksekliği `260px` yapıldı ve istemci önbellek anahtarı `dashboard_cache_v6_` olarak artırılarak tarayıcıdaki eski önbellek otomatik yenilendi.

## 2026-10-21: Ersan E-İmza Java Ajanı — Modern FlatLaf Arayüzü
- [x] FlatLaf 3.7.2 projeye eklendi ve Java 8 hedefiyle derlendi.
- [x] Sekmeli eski arayüz, modern sol navigasyonlu ana sayfa/ayarlar yerleşimine dönüştürüldü.
- [x] Servis ve kart durumları, imza isteği özeti, kart tarama, sürücü seçme, tanılama, arka plana alma ve kapatma aksiyonları yeni tasarıma taşındı.
- [x] `downloads/ErsanEImza.jar` ve `scripts/ErsanEImza.jar` yeniden üretildi; gerekli FlatLaf ve native kaynakların paket içinde bulunduğu doğrulandı.

## 2026-10-21: Dashboard — Görev & Evrak Takvimi Kartı Yükseklik Standardizasyonu
- [x] Kart Yüksekliği Sabitlendi: `widget-takvim-alarmlar` kartı, yanındaki operasyonel kartların (Endeks Okuma, Sayaç Değişimi vb.) 235px - 240px yüksekliğiyle birebir eşit olacak şekilde `height: 238px !important; max-height: 238px !important; min-height: 238px !important;` olarak yeniden boyutlandırıldı.
- [x] Kompakt Takvim Tasarımı: Dikey padding `py-2`, takvim başlığı, hafta isimleri ve 7x6 gün hücreleri `20x20px` (font-size: 10px) kompakt ölçülere getirildi.
- [x] Kompakt Alarm Paneli: Sağ alarmlar listesi ince scrollbar (`scrollbar-width: thin;`) ve 140px max-height sınırı ile optimize edildi.
- [x] Önbellek Yenilendi: `views/home.php` dosyasında `dashboard_cache_v4_` tanımlandı ve sunucudaki statik widget cache'i güncellendi.

## 2026-10-21: KM Onayları AI Analizi ve Ret Gerekçesi Görünümü
- [x] GPT-5 görsel OCR çağrılarında analiz/çıktı bütçesi yükseltildi ve orta düzey muhakeme etkinleştirildi; güvenlik eşikleri korunarak doğruluk odaklı analiz sıkılaştırıldı.
- [x] Geçici OpenAI bağlantı/kapasite/oran sınırı hatalarına bir kontrollü tekrar eklendi.
- [x] Genel `Yapay zekâ servisi analizi kabul etmedi` mesajı yerine HTTP hata ayrıntısı, model reddi, çıktı sınırı, içerik filtresi ve bozuk çıktı gerekçeleri güvenli biçimde ayrıştırıldı.
- [x] Masaüstünde `YZ Onaylamadı` mesajına tıklanınca tam gerekçeyi gösteren SweetAlert ayrıntısı eklendi.
- [x] Mobil SPA'da AI mesajına dokununca plaka ve tam gerekçeyi gösteren Bento modalı eklendi.
- [x] Mobil SPA üretim derlemesi, ilgili PHP lint kontrolleri ve hata ayrıştırma testi geçti.

## 2026-10-21: Araç Takip KM Kayıtları — Zimmetli Personel Kolonu ve Normal Excel Export
- [x] Model Katmanı (`App/Model/AracKmModel.php`):
  - `all()`, `getByArac()` ve `getByDateRange()` metotlarına `a.departmani` ve KM tarihindeki veya güncel aktif zimmetli personeli getiren `zimmetli_personel` alanı eklendi.
  - `getByArac()` sorgusundaki eksik kullanıcı tablosu (`LEFT JOIN users u`) ve `olusturan_kullanici_adi` tamamlandı.
- [x] Liste Arayüzü (`views/arac-takip/list.php`):
  - KM Kayıtları sekmesindeki `#kmTable` başlığına Plaka'dan hemen sonra "Zimmetli Personel" sütunu (`data-filter="string"`) eklendi.
  - Sütun genişlikleri dengelendi, canlı filtreleme özellikleri korunarak `colspan="8"` yapıldı.
- [x] İstemci Mantığı (`views/arac-takip/js/arac-takip.js`):
  - `kmListesiYukle()` fonksiyonunda tablo satırına personelin adı veya "Boşta" rozeti içeren sütun eklendi.
  - `#btnExceleAktar` butonuna tıklandığında KM sekmesinde DataTables HTML5 export yerine doğrudan aktif filtrelerle birlikte `action=km-excel-aktar` sunucu çağrısı yapılması sağlandı.
- [x] Sunucu Taraflı Excel Export (`views/arac-takip/api.php`):
  - `action === 'km-excel-aktar'` aksiyonu oluşturuldu.
  - PhpSpreadsheet ile profesyonel kurumsal tablo şablonu (Sıra, Plaka, Araç Marka/Model, Departman, Zimmetli Personel, Tarih, Başlangıç KM, Sabah Bildiren, Bitiş KM, Akşam Bildiren, Yapılan KM, Kaydeden, Kayıt Tarihi) üretildi.
  - Toplam yapılan KM için alt toplam satırı (`=SUM(...)`) ve `SystemLogModel` log kaydı oluşturuldu.
- [x] Güvenlik ve Yetkilendirme (`sql/2026_10_21_km_excel_aktar_permission.sql`):
  - `permission_policies` tablosuna `km-excel-aktar` için `arac_takip_yonetim` yetkisi tanımlandı ve doğrulandı.


## 2026-10-21: Araç Takip KM Onayları — Yapay Zekâ 1.000 KM Üst Sınır Kontrolü
- [x] `App/Service/KmBildirimAiKontrolService.php` içerisine `calculateYapilanKm(object $bildirim, int $kmCandidate): ?int` metodu eklendi.
- [x] Akşam bildirimlerinde günün başlangıç KM'si veya önceki KM baz alınarak yapılan KM (`bitis_km - baslangic_km`) hesaplandı.
- [x] Sabah bildirimlerinde kayıtlı bitiş KM varsa yapılan KM, yoksa önceki sistem KM'si ile fark hesaplandı.
- [x] `evaluate()` metodunda toplam yapılan KM veya atlama farkı 1.000 KM'yi aştığında yapay zekânın otomatik onayı engellendi (`$karar['uygun'] = false`); net açıklayıcı red nedeni (`Toplam yapılan KM (X KM) 1.000 KM'den fazla olduğu için otomatik onaylanamaz.`) üretilerek `ai_onaylanmama_nedeni` alanına kaydedildi.
- [x] Fotoğraftan okunan alternatif KM değeri ve `adjudicateCloseOdometerReading` aday hakemliği adımı da 1.000 KM sınırına bağlanarak gereksiz API çağrıları engellendi.
- [x] PHP CLI üzerinden tüm uç senaryolar (aşırı KM, normal KM, sabah/akşam) test edilerek doğrulandı.

## 2026-10-21: Mobil SPA Raporlar — Toplam İşlemler Kartı Detaylı Dağılım Dökümü
- [x] Backend API (`mobile-spa/public/api/reports.php` ve `mobile-spa/dist/api/reports.php`) üzerinde `action === 'distribution'` aksiyonu oluşturuldu.
- [x] Tüm 5 operasyon sekmesi (`okuma`, `kesme`, `sokme_takma`, `muhurleme`, `kacakkontrol`) için operasyonel durum dağılımı (`status_section`) ve bölge katkısı (`region_section`) analiz sorguları uygulandı.
- [x] Kesme/Açma sekmesinde masaüstü formatında `Tümü / Olumlu / Olumsuz` filtre hapları ve dinamik kırılım desteği sağlandı.
- [x] Servis katmanında (`mobile-spa/src/services/reports.js`) `distributionDrawerOpen`, `distributionLoading`, `distributionData`, `openDistributionModal()`, `closeDistributionModal()` ve `loadDistribution()` fonksiyonları tanımlandı.
- [x] Görsel arayüzde (`mobile-spa/src/views/ReportsView.vue`):
  - Sol üstteki Toplam İşlem KPI kartı tıklanabilir yapıldı (`.kpi-card--clickable`, grafik rozeti, `Döküm >` eylem linki).
  - Masaüstündeki zengin gradyan hero başlığına (`.dist-hero-banner`) sahip şık Mobil Dağılım Çekmecesi (`distribution-sheet`) tasarlandı.
  - 4'lü Hero KPI cam kapsülleri, `Tümü / Durumlar / Bölge Katkısı` görünüm geçişleri, her durum için yüzde, kayıt ve renkli ilerleme çubukları ve sıralı bölge katkı çubukları uygulandı.
- [x] `npm run build` ile prodüksiyon derlemesi hatasız alındı ve testler başarıyla doğrulandı.

## 2026-10-20: Ana Sayfa — Görev & Evrak Takvimi & Alarmlar Mini Kartı
- [x] Veritabanı Değişikliği: `sql/2026_10_20_add_son_tarih_to_evrak_takip.sql` oluşturuldu ve `evrak_takip` tablosuna `son_tarih DATE NULL` sütunu eklendi.
- [x] Model Katmanı:
  - `EvrakTakipModel.php`: `checkSchema()` güncellendi; `getTakvimEvraklar()` metodu eklendi.
  - `GorevModel.php`: `getTakvimGorevler()` metodu eklendi.
- [x] Evrak Takip Modülü:
  - `views/evrak-takip/modal/evrak-modal.php`: Gelen evrak ekleme/düzenleme modalına "Son Tarih / Termin (Opsiyonel)" alanı eklendi.
  - `views/evrak-takip/api.php`: `evrak-kaydet` aksiyonunda `son_tarih` parametrik formatlaması ve kaydı uygulandı.
  - `views/evrak-takip/js/evrak-takip.js`: Evrak düzenleme formunda `son_tarih` alanının yüklenmesi sağlandı.
  - `views/evrak-takip/list.php`: Tabloda son tarihi olan evraklara kırmızı termin rozeti gösterimi eklendi.
- [x] Ana Sayfa (Dashboard) Widget Entegrasyonu:
  - `views/home/render_widgets.php`: Referans tasarıma (Görsel 1) uygun takvim (koyu başlık, `P S Ç P C C P`, alarmlı günlerde terrakota yuvarlak rozet, bugünün gününde turkuaz halka, gün filtreleme) ve sağ alarm listesi (çan ikonu, `X ALARM` sayacı, terrakota kutucuklar, doğrudan linkler) `widget-takvim-alarmlar` olarak geliştirildi.
  - `views/home/api.php`: `batch-load-all` içerisine görev ve evrak alarmlarının birleştirilip tarihe göre sıralanması eklendi; dinamik ay gezinmesi için `get-calendar-alarms` aksiyonu ve policy'si (`sql/2026_10_20_add_calendar_alarms_policy.sql`) uygulandı.
  - `views/home.php`: Personel kartı (`col-md-4 col-xl-4`) boyutunda lazy-load widget tanımı, macOS pencere başlığı (`Görev & Evrak Takvimi`), Dashboard Ayarları menü switch'i ve dinamik gün/ay etkileşim JavaScript fonksiyonları (`selectHomeCalendarDay`, `resetHomeCalendarDayFilter`, `navigateHomeCalendar`) eklendi.
- [x] PHP sözdizimi kontrolleri ve simülasyon testleri başarıyla doğrulandı.

## 2026-10-20: Mobil SPA Evrak Takip — KEP Gönderisi Belirteçleri ve Giden KEP Önizlemesi
- [x] Mobil API (`mobile-spa/public/api/evrak.php`) üzerinde `is_kep`, `is_uets`, `kep_mesaj_id`, `alici_kep` alanları ve `kep-detail`, `kep-sync-delil`, `kep-delil-indir` aksiyonları uygulandı.
- [x] Evrak kartlarında belirgin `KEP` rozeti, sol kenarlık vurgusu ve KEP alıcı adresi için `Send` simgeli monospaced formatlama sağlandı.
- [x] KEP evrakları için `Önizle` butonu `KEP Önizle` olarak uyarlandı; tıklandığında resmi evrak PDF'i yerine zengin **Giden KEP Önizleme Çekmecesi** açılması sağlandı.
- [x] Giden KEP Önizleme Çekmecesi; gönderen/alıcı adresleri (kopyalama özellikli), PTT KepId kodu, teslimat durum rozeti, ileti metni, ekler ve PTT Resmi Yasal Delil Çizelgesi (10, 40, 60 delil akışı + canlı güncelleme) ile tamamlandı.
- [x] `mobile-spa` üzerinde `npm run build` başarıyla tamamlandı.

## 2026-10-20: Mobil SPA Ana Sayfa — Personel & Araç Metrik Kutularının Çerçevelenmesi ve Hizalanması
- [x] Personel Durumu (Koyu Bento Kartı) altındaki Sahada, İzinli ve Geç Kalan metrikleri için saydam koyu cam zeminli (`rgba(255, 255, 255, 0.05)`), `1px solid rgba(255, 255, 255, 0.1)` çerçeveli ve `16px` kavisli bağımsız kutular oluşturuldu.
- [x] Araç & Filo (Beyaz Bento Kartı) altındaki Sahada, Serviste ve Boşta metrikleri için açık zeminli (`var(--ds-surface-subtle)`), `1px solid var(--ds-border-subtle)` çerçeveli ve `16px` kavisli bağımsız kutular oluşturuldu; sağ kenarlık sütun ayırıcıları kaldırıldı.
- [x] Her iki karttaki tüm metrik kutularında durum noktaları, etiketler ve sayısal değerler `align-items: center; justify-content: center; text-align: center;` ile yatay ve dikey eksende tam ortalandı.
- [x] Araç & Filo metriklerine segment çubuğu renkleriyle eşleşen durum noktaları (`ds-dot--emerald`, `ds-dot--coral`, `ds-dot--amber`) eklenerek tasarım bütünlüğü sağlandı.
- [x] `mobile-spa` üzerinde `npm run build` çalıştırılarak üretim paketi (`dist`) güncellendi.

## 2026-10-20: Fatura Listelerinde Filtrelenmiş Özet Kartları
- [x] `EInvoiceModel::ajaxList()` gelen, giden ve taslak listelerinde aynı parametrik filtre sorgusundan özet üretecek şekilde ortaklaştırıldı.
- [x] Gelen ve taslak sayfalarındaki bağımsız/filtrelerden habersiz özet istekleri kaldırıldı; kartlar DataTables yanıtındaki `summary` verisine bağlandı.
- [x] Kolon, genel, ürün, tarih ve hızlı filtrelerin kart adet/tutarlarına yansıması sağlandı.
- [x] Gelen fatura hızlı yanıt filtresi `ticari_yanit` alanına düzeltildi; PHP ve JavaScript sözdizimi kontrolleri geçti.

## 2026-10-19: PTT KEP Entegrasyonu (Donanımsal E-İmza, Özel KEP Modalı ve Ek Dosya Adı / Uzantı Çözümü)
- [x] PTT KEP WSDL ve Geliştirici Kılavuzu (v1.3.2) analiz edildi; canlı MTOM/XOP yanıt formatı tespit edildi.
- [x] `App/Service/MtomSoapClient.php` geliştirilerek MTOM multipart yanıtlarından SOAP XML zarfının ayıklanması ve `<xop:Include>` binary parçalarının otomatik olarak base64Binary inline edilmesi sağlandı.
- [x] `App/Service/KepClientService.php` ile KontorSorgula, KotaSorgula, RehberTopluKontrol, MimeYap, SmimeGonder, Yukle, PaketDelilSorgula ve PaketDelilIndir metotları sarmalandı.
- [x] `sql/kep_integration.sql` ve `sql/add_alici_kep_to_evrak_takip.sql` çalıştırıldı; `kep_ayarlar`, `kep_mesajlar`, `kep_deliller` tabloları ve `evrak_takip.alici_kep`, `kep_paket_turu`, `kep_mesaj_id` sütunları eklendi.
- [x] Akıllı PTT Alıcı Doğrulama: `RehberTopluKontrol`'ün izole test ortamındaki `exist=0` davranışı `MimeYap` dry-run geçerlilik testi ile aşıldı; test adresleri doğrudan doğrulanıyor.
- [x] Donanımsal CAdES-BES (PKCS#7) İmzalama: Bilgisayarda takılı olan SafeNet eToken 5110 donanımı üzerinden `/usr/lib/libeToken.so` ve `scripts/cades_token_signer.py` ile E-GÜVEN NES sertifikası (MEHMET ALİ GÖKMEN, TCKN: `32450401908`) kullanılarak ETSI EN 319 122 uyumlu resmi CAdES-BES imzası üretildi.
- [x] PTT KEP Uçtan Uca Teslimatı Doğrulandı: İleti sevk edildi (`durum = 0`), PTT Takip Kodu alındı, `delilTurId = 10` (Kabul), `delilTurId = 40` (Alıcıya Teslim Edildi) ve `delilTurId = 60` (Alıcı Tarafından Açıldı) resmi delilleri çekildi.
- [x] Yasal ETSI Delil XML'i (`rem:DeliveryNonDeliveryToRecipient`) PTT sunucusundan indirildi ve ISO 27001 standardı gereği AES-256-GCM ile şifrelenerek arşive alındı.
- [x] Özel KEP Önizleme ve Delil Detayı Modalı Geliştirildi:
  - `views/evrak-takip/modal/kep-preview-modal.php` oluşturuldu; Gönderen, Alıcılar, KepId, Tarih, İleti Metni, Ekler ve PTT Resmi Yasal Delil Çizelgesi (10, 40, 60 delilleri + XML İndir butonları) eklendi.
  - `views/evrak-takip/api.php`: KEP evraklarının `giden-evrak.php` formuna gitmesi engellendi; hem konu linki hem işlem sütununa KEP önizleme butonu (`.btn-kep-onizle`) bağlandı; `kep-detay-getir` ve `kep-delil-indir` API aksiyonları eklendi.
  - `views/evrak-takip/js/evrak-takip.js`: KEP modalı açma, veri render, PTT delil yenileme ve tablo satır tıklaması (tr click) KEP koruması tamamlandı.
  - `sql/add_kep_preview_permission_policies.sql`: Yetki politikaları parametrik olarak uygulandı.
- [x] KEP Ek Dosya Adı ve Uzantı Sorunu Giderildi:
  - W3C XMLMIME (`http://www.w3.org/2005/05/xmlmime`) standardı incelendi; Axis2 şemasındaki `xmime:fileName` niteliği zorunluluğu keşfedildi.
  - `App/Service/KepService.php` içinde `xmime:fileName` ve `xmime:contentType` eklendi; PTT sunucusunun ürettiği MIME paketinden indirilen dosyaların orijinal adıyla ve `.pdf`, `.xlsx` uzantısıyla geldiği doğrulanıp kanıtlandı.
- [x] Evrak Görüntüleme Modalı ve Ek Eksiklikleri Çözüldü:
  - Eksik fiziksel dosya kopyası tamamlandı (`generateTempPdf(54)` ve `generateTempPdf(55)` başarıyla bordro PDF'ini birleştiriyor).
  - `views/evrak-takip/api.php` içinde şifreli arşivden otomatik ek kurtarma (self-healing) sağlandı.
- [x] Sunucu Python Bağımsızlığı ve Kesintisiz OpenSSL Fallback Sağlandı:
  - `getPythonBinary()` ile Python dinamik tespiti yapıldı (`python3`, `python`, `/usr/bin/python3`, `/usr/local/bin/python3` vb.).
  - Sunucuda Python bulunmadığında veya donanımsal token takılı olmadığında, test ortamında sistemin `sh: python3: command not found` hatası verip durması engellendi; yerleşik PHP + OpenSSL CAdES-BES motoruna otomatik ve kesintisiz geçiş yapması sağlandı. PTT KEP test ortamının bu imzayı eksiksiz kabul ettiği kanıtlandı.

## 2026-10-19: Bekleyen Onaylar Boş Liste Hatası
- [x] Sonuç filtresi ile bekleyen onay durumu arasındaki çelişen Model koşulu tespit edildi.
- [x] Boş sonuç parametresi ile açık “Tüm Sonuçlar” seçimi ayrıştırıldı.
- [x] Bekleyen onay sorgusunun sayaçla aynı kayıtları döndürmesi sağlandı.

## 2026-10-19: Personel PWA İhbar Türü
- [x] İhbar türü sütunu, geriye dönük veri doldurma ve indeks için tekrarlanabilir SQL scripti oluşturuldu.
- [x] PWA ihbar oluşturma/düzenleme formuna zorunlu tür seçimi eklendi.
- [x] Offline kuyruk ve PWA API oluşturma/güncelleme akışları tür alanına bağlandı.
- [x] PWA listesine tür rozeti ve gelişmiş tür filtresi eklendi.
- [x] Masaüstü oluşturma/düzenleme formu ile Gelen İhbarlar filtresi gerçek tür sütununa geçirildi.

## 2026-10-19: Kaçak Kontrol Olumlu/Olumsuz Filtresi
- [x] Kayıtlar üst araç çubuğuna Tümü / Olumlu / Olumsuz sonuç filtresi eklendi.
- [x] Olumlu ve olumsuz sonuç kuralları Model katmanında uygulandı.
- [x] Server-side DataTables API ve Excel dışa aktarımı sonuç filtresine bağlandı.

## 2026-10-19: Nöbet Personel Havuzu Varsayılan Departmanı
- [x] Personel havuzu her sayfa açılışında Kesme Açma departmanıyla başlatıldı.
- [x] Önceki tarayıcı departman seçiminin varsayılan filtreyi ezmesi kaldırıldı; sayfa içindeki departman değiştirme işlevi korundu.

## 2026-10-19: Gelen İhbarlar Tür Filtresi
- [x] Gelen İhbarlar üst filtre grubuna Abonesiz / Kaçak / Pasif / Usülsüz seçimi eklendi.
- [x] Tür filtresi server-side DataTables parametrelerine bağlandı.
- [x] Açıklama tabanlı tür eşlemesi `IhbarModel::ajaxList()` içinde parametrik PDO koşullarıyla uygulandı.
- [x] Tür filtresi Select2 başlatma, canlı yenileme ve filtre sıfırlama akışlarına dahil edildi.

## 2026-10-19: Mobil/Masaüstü Sayaç Değişimi Dashboard Senkronizasyonu
- [x] Mobil ana sayfa sayaç kartı `sayac_degisim` tablosunu kullanan `SayacDegisimModel` istatistiklerine bağlandı.
- [x] Masaüstü ana sayfa sayaç kartı aynı kanonik istatistik kaynağına bağlandı.
- [x] Mobil toplu online sorguda alt endpoint hatalarının başarı gibi gösterilmesi engellendi.
- [x] Başarılı toplu sorgudan sonra mobil dashboard verisinin yeniden çekilmesi korundu.
- [x] `App/Model/MobileDashboardModel.php` ve `views/home.php` PHP lint kontrollerinden geçti.
- [x] `npm --prefix mobile-spa run build` başarıyla tamamlandı; yeni production bundle üretildi.

## 2026-10-19: Tüm Operasyon Kartlarında Mobil→Masaüstü Cache Senkronizasyonu
- [x] Masaüstü operasyon kartlarının 5 dakikalık sunucu cache'i mobil sorgu sonrası eski veri gösterme nedeni olarak tespit edildi.
- [x] Başarılı Endeks Okuma sorgusu sonrası firma operasyon cache'i geçersiz kılındı.
- [x] Başarılı Kesme/Açma ve Mühürleme sorguları sonrası firma operasyon cache'i geçersiz kılındı.
- [x] Başarılı Sayaç Değişimi sorgusu sonrası firma operasyon cache'i geçersiz kılındı.
- [x] `views/puantaj/api.php` PHP lint kontrolünden geçti.

## 2026-10-19 — Mobil SPA: E-Fatura "Cariye İşle" (Sync to Cari) Entegrasyonu & UI İyileştirmeleri
- [x] **Backend Model & API ([`EInvoiceModel.php`](file:///opt/lampp/htdocs/ersan_elk/App/Model/EInvoiceModel.php) & [`fatura.php`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/public/api/fatura.php))**:
  - `EInvoiceModel::getDashboardData` içindeki son gelen ve son giden fatura sorgularına `cariye_islendi` alt sorgusu eklendi.
  - `check_invoice_cari` GET ve `sync_to_cari` POST aksiyonları hazırlandı, CSRF ve Gate yetkileriyle korundu.
  - `mobile-spa/dist/api/fatura.php` ve `public/api/fatura.php` eşitlendi; PHP lint kontrolleri hatasız geçti.
- [x] **İkon Buton Dönüşümü (Sayfaya Sığma & Taşma Çözümü)**:
  - Fatura kartlarındaki `Cariye İşle` ve `Fatura Gör` butonları 32x32px dairesel ikon butonlara (`.fatura-icon-action-btn`) dönüştürüldü.
  - Kart aksiyon alanındaki genişlik daraltıldı; butonların sağ kenardan taşması veya kesilmesi tamamen engellendi.
- [x] **Cariye İşle Modalı Alt Boşluk & Padding Düzeltmesi**:
  - Detay kartlarının altına `margin-bottom: 24px`, aksiyon butonlarının üstüne `margin-top: 24px` eklendi.
  - Modal gövdesine safe-area uyumlu geniş alt dolgu (`padding-bottom: max(40px, ...)`) verilerek butonların modal tabanına yapışması önlendi.
- [x] **Özet (Dashboard) Sekmesine Cariye İşle Entegrasyonu**:
  - Özet sekmesindeki `Son Faturalar` kartlarına hem `Cariye İşle` (`ArrowLeftRight` / `CheckCheck`) hem de `Fatura Gör` (`Eye`) ikon butonları yerleştirildi.
  - Özet ekranından yapılan işlem sonrası reaktif güncelleme sağlandı.
- [x] **Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi.
- [x] **Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi.

## 2026-10-18 — Puantaj Snapshot ve Ayın 3'üne Kadar Mutabakat
- [x] Okuma/kesme ay sonu snapshot, manifest hash, çalışma checkpoint, staging ve fark tabloları için tekrarlanabilir SQL hazırlandı.
- [x] Ayın 1–3'ü 03:00 worker'ı, günlük sayfalama, DB kilidi ve hata izolasyonu eklendi; kapsam Ekim 2026 ve sonrasıyla sınırlandı, geçmiş backfill kaldırıldı.
- [x] Fark sınıflandırma, yetkili güncel veriyi uygulama, audit bilgileri ve bordro yeniden hesaplama işareti tamamlandı.
- [x] Yeni yetki/menü/API politikaları ve Superadmin/Firma Sahibi rol atamaları SQL'e bağlandı.
- [x] Yönetim DataTable sayfası, özet kartları, e-posta ayarı, CSV bildirim eki ve bordro hesaplama/kapanış blokajı eklendi.
- [x] Mutabakat ve bordro kapanış doğrulamaları 10 test / 55 assertion ile geçti; ilgili PHP dosyalarının syntax kontrolleri başarılı.
- [x] Sayaç Sökme-Takma ve Mühürleme ayrı mutabakat kaynakları olarak eklendi; rapor sayfasının üstüne sürekli görünür mutabakat kısayolu yerleştirildi.
- [x] Açık ayda henüz üretilemeyen final mutabakatın maaş hesaplamasını engellemesi kaldırıldı; hesaplama taslak olarak serbest, dönem kapatma final mutabakata bağlı bırakıldı.

## 2026-10-18 — Mobil SPA: Puantaj Fazla Mesai (FM) Girişi ve 3'lü Sekme Mimarisi
- [x] **3'lü Kompakt Sekme Yapısı**: Gün seçim modalı dikeyde gereksiz alan işgal etmemesi için `[ Ücretli İzin ]`, `[ Ücretsiz İzin ]`, `[ ⚡ Fazla Mesai ]` 3'lü sekmesine dönüştürüldü. Varsayılan olarak Ücretli İzin sekmesinde kompakt açılır.
- [x] **Saat Kesilme Sorunu Giderildi**: Eski akordeon yapısı kaldırıldı; yerine 4 sütunlu esnek ve tam görünür `.picker-fm-grid` ızgarası yerleştirildi (`1s`, `1.5s`, `2s`, `3s`, `4s (Standart)`, `5.5s`, `7.5s`).
- [x] **Manuel Saat Girişi & Stepper Eklendi**: `[-]` (0.5s azalt), `[+]` (0.5s artır) step butonları ve doğrudan klavyeden sayı yazılabilen büyük numara kutusu ile mor `[ Kaydet / Güncelle ]` butonu eklendi.
- [x] **Mevcut Mesai Rozeti ve Kaldır Butonu**: Seçili günde mesai varsa `+4s Mesai` mor hapı ve kırmızı `[ Kaldır ]` butonu ile anında silme desteği sağlandı.
- [x] **Kesme Departmanı Uyarısı**: Kesme personelleri için kart içinde sarı uyarı kutusu gösterildi.
- [x] **Ay Detay Takviminde "+FM" Damgalama**: `⚡ Fazla Mesai (+FM)` sekmesiyle saat seçip takvimdeki günlere dokunarak ardışık günlere tık tık mesai damgalama özelliği eklendi.
- [x] **API Parametre Sırası Düzeltildi**: `PersonelFazlaMesaileriModel::saveMesai` ve `deleteMesai` çağrılarındaki parametre sırası düzeltildi.
- [x] **Derleme & PWA Cache**: `npm run build` ile 0 hatayla derlendi, PWA service worker cache `ersan-mobile-shell-v24` seviyesine yükseltildi.

## 2026-10-18 — Mobil SPA: Cari Hareketlerini Düzenleme Desteği (Hareketi Düzenle Butonu ve Güncelleme Akışı)
- [x] **1. Cari Detay Ekranı ([`CariDetailView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariDetailView.vue))**:
  - Hareket satırına basıldığında açılan detay penceresine birincil eylem butonu olarak **"Hareketi Düzenle"** (`.primary-edit-btn`) eklendi.
  - Tıklandığında mevcut hareket verileri (yön, tutar, yerel tarih, belge no, açıklama, ekli dosya) okunarak düzenleme formuna yüklendi.
  - Hızlı işlem modalı `İşlemi Düzenle` başlığı ve `Değişiklikleri Güncelle` kaydet butonuyla edit moduna geçirildi.
  - Mevcut ekli belgeyi gösteren `existing-doc-banner` kartı eklendi.
  - Kayıt esnasında `hareket_id` gönderilerek `CariHareketleriModel->saveWithAttr()` üzerinden UPDATE sağlandı ve bakiye özetleri yenilendi.
- [x] **2. Cari Genel Listesi & İşlem Geçmişi ([`CariView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariView.vue))**:
  - Tüm Hareketler (İşlem Geçmişi) modalındaki detay popup'ına da **"Hareketi Düzenle"** butonu entegre edildi.
  - Düzenleme modalı `z-index: 120` ile geçmiş modalının üstünde açılıp kaydedildiğinde hem ana liste hem de geçmiş anlık yenilenecek şekilde bağlandı.
- [x] **3. Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi; dist dizini oluşturuldu.


## 2026-10-18 — Fatura Cari Hareket Yönleri SQL Sunucu Hatası Teşhis Edildi
- [x] `sql/2026_10_18_fix_fatura_cari_hareket_yonleri.sql` içeriği ve sorgu sonları kontrol edildi.
- [x] MariaDB 1064 hatasının dosyadan değil, sunucu editöründe sorgu sonuna eklenen `;ersantrc_personel` metninden kaynaklandığı belirlendi.
- [x] Çalıştırılacak doğru son satırın `AND ch.silinme_tarihi IS NULL;` olduğu doğrulandı.

## 2026-10-18 — Masaüstü ve Mobil: Cari Hareketleri Renk Standardı (Aldıklarım Kırmızı, Verdiklerim Yeşil)
- [x] **1. Masaüstü Renk Eşitlemesi ([`hesap-hareketleri.php`](file:///opt/lampp/htdocs/ersan_elk/views/cari/hesap-hareketleri.php) & [`api.php`](file:///opt/lampp/htdocs/ersan_elk/views/cari/api.php))**:
  - `TOPLAM GİRİŞ (ALD.")` özet kartı kırmızı (`bg-danger-subtle text-danger`, `Girişler (-)`) yapıldı.
  - `TOPLAM ÇIKIŞ (VERD.")` özet kartı yeşil (`bg-success-subtle text-success`, `Çıkışlar (+)`) yapıldı.
  - Üst aksiyon araç çubuğunda `Aldım (-)` butonu kırmızı (`btn-danger`), `Verdim (+)` butonu yeşil (`btn-success`) yapıldı.
  - DataTables tablosunda GİRİŞ (ALD.) sütunundaki tutarlar kırmızı (`text-danger`), ÇIKIŞ (VERD.) sütunundaki tutarlar yeşil (`text-success`) yapıldı.
  - Excel ihracı ve Hızlı İşlem modalı renkleri uyarlandı.
- [x] **2. Mobil SPA Renk Eşitlemesi ([`CariDetailView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariDetailView.vue) & [`CariView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariView.vue))**:
  - `TOP. ALDIM` mini özet kartı nokta ve tutarı **KIRMIZI** (`summary-dot--aldim`, `text-aldim`) yapıldı.
  - `TOP. VERDİM` mini özet kartı nokta ve tutarı **YEŞİL** (`summary-dot--verdim`, `text-verdim`) yapıldı.
  - Hareket listesinde `aldim` satırları kırmızı `-`, `verdim` satırları yeşil `+` olarak uyarlandı.
  - Hızlı işlem modalında `Aldım (-)` kırmızı, `Verdim (+)` yeşil aktif stili uygulandı.
  - Vite ile `npm run build` 0 hata ile tamamlandı.

## 2026-10-18 — Mobil SPA: Cari Hesap Hareketleri Masaüstü Uyumluluğu (Giriş/Aldım Yeşil, Çıkış/Verdim Kırmızı)
- [x] **1. Özet Kartları Renkleri ve Etiketleri**:
  - `TOP. ALDIM` (GİRİŞ) kartı masaüstü standardına getirilerek **YEŞİL** yapıldı.
  - `TOP. VERDİM` (ÇIKIŞ) kartı masaüstü standardına getirilerek **KIRMIZI** yapıldı.
  - 3. Net Bakiye kartında `cari.bakiye < 0` için `BORÇLUYUM (NET)` (Kırmızı), `> 0` için `ALACAKLIYIM (NET)` (Yeşil) gösterildi.
- [x] **2. Hareket Listesi Satırları ve Yürüyen Bakiye**:
  - `aldim` (Giriş) hareketleri: Masaüstündeki gibi **YEŞİL (+)** tutar, yeşil giriş ikonu kutusu (`type-aldim`).
  - `verdim` (Çıkış) hareketleri: Masaüstündeki gibi **KIRMIZI (-)** tutar, kırmızı çıkış ikonu kutusu (`type-verdim`).
  - Yürüyen bakiye: Masaüstü standardı `Bakiye: 81.400,00 ₺ (B)` formatında gösterildi.
- [x] **3. Hızlı İşlem Ekle ve Hareket Detay Modalları**:
  - `Aldım (+)` seçeneği yeşil ve `Verdim (-)` seçeneği kırmızı buton stilleriyle masaüstündeki "Aldım (+)" ve "Verdim (-)" butonlarıyla birebir eşitlendi.
- [x] **4. Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi.

## 2026-10-18 — Mobil SPA KM Onayları: Yapay Zeka (AI) ve Eylemlerde CSRF Hatası Düzeltildi
- [x] **CSRF Hatası Giderildi (`Güvenlik doğrulaması CSRF geçersiz`)**:
  - `auth.js` reaktif durumuna `csrfToken` yanında `csrf_token` uyumluluğu eklendi.
  - `km-onaylari.js` API istemcisine `auth` entegre edilerek tüm POST isteklerinde (`aiCheck`, `approve`, `reject`, vb.) CSRF token'ın header (`X-CSRF-Token`) ve body'ye otomatik eklenmesi sağlandı.
  - `KmOnaylariView.vue` içindeki tüm aksiyon çağrıları `auth.state.csrfToken || auth.state.csrf_token` güvencesine alındı.
  - Backend API (`public/api/km-onaylari.php` & `dist/api/km-onaylari.php`) `verifyCsrfToken` fonksiyonu güçlendirildi.
  - Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-18 — Araç Takip Yönetimi: Filo Operasyon ve Analitik Dashboard (Masaüstü & Mobil SPA) Tamamlandı
- [x] **1. SQL & Yetkilendirme**:
  - `sql/2026_10_18_add_arac_dashboard_menu_and_permissions.sql` çalıştırıldı. `arac-takip/dashboard` yetkisi, menü bağlantısı (ID: 986) ve superadmin/firma sahibi rolleri oluşturuldu.
- [x] **2. Model Katmanı (`AracDashboardModel.php`)**:
  - PDO ve parametrik binding ile MariaDB uyumlu KPI, 12 aylık trendler, departman/mülkiyet/yakıt tipi dağılımı, lider araçlar ve kritik evrak fonksiyonları yazıldı.
- [x] **3. Masaüstü Sayfa & API**:
  - `views/arac-takip/dashboard.php`, `views/arac-takip/js/dashboard.js`, `views/arac-takip/api.php` ve `index.php` rotaları tamamlandı.
- [x] **4. Mobil Backend API**:
  - `mobile-spa/public/api/arac.php` ve `mobile-spa/dist/api/arac.php` içine `action === 'dashboard'` endpoint'i ve dinamik dönem filtreleri eklendi.
- [x] **5. Mobil Gezinme & Router**:
  - `BottomNav.vue` içine Araç sekmelerinin ilk elemanı olarak `Dashboard` sekmesi eklendi; `arac.js` ve `router.js` rotaları tanımlandı.
- [x] **6. Mobil SPA Tasarım & Arayüz (`AracView.vue`)**:
  - Soft Bento & Claymorphic UI standartlarına tam uyumlu:
    - Koyu Hero Bento Kartı (Segmentli filo doluluk ve 4 metrik hücresi)
    - Dönem & Departman filtre barı (Bu Ay, Geçen Ay, Son 3 Ay, Bu Yıl, Tümü, Ay Seç ve Departman Seçici Modal)
    - 4 Detaylı Bento KPI Kartı (Filo, KM, Yakıt, Bakım/Evrak) ve ilgili sekmelere yönlendirme
    - 12 Aylık Trend CSS Bar Grafiği (KM vs Yakıt)
    - Departman Dağılım Kartı (KM / Yakıt / Araç geçişli dinamik doluluk barları)
    - Mülkiyet ve Yakıt Türü Dağılım Kartları
    - Liderler Tablosu (Top 5 KM ve Top 5 Yakıt)
    - Kritik & Yaklaşan Evraklar (Muayene, Sigorta, Kasko gecikme ve kalan gün rozetleri)
    - Son Bakım & Servis Kayıtları
    - Departman Seçim Bottom Sheet Modalı
- [x] **7. Üretim Derlemesi & Test**:
  - `npm run build` ile Vite üretim paketi 0 hata ile derlendi; PHP CLI üzerinden JSON çıktısı doğrulandı.

## 2026-10-18 — Mobil SPA Talepler Kartı: Talep Durumu ve Talep Türü Gösterimi Tamamlandı
- [x] **1. Talep Durum Rozeti Entegrasyonu (`getStatusMeta`)**:
  - `status-chip` rozeti her talep kartına entegre edildi.
  - Avans ve izinlerde tutar/gün kutusunun altına yerleştirilerek ekran görüntüsünde işaretlenen ilk kırmızı alanda `Onaylandı` (yeşil), `Reddedildi` (kırmızı), `Kısmi Onay` (açık mavi), `İşlemde` (kehribar/dönen ikon), `Beklemede` (gri) ve `Silindi` durumlarının görünür olması sağlandı.
  - Detay modalındaki `DURUM` alanına da aynı `status-chip` bileşeni eklendi.
- [x] **2. Talep Türü Ayrımı (`getTypeMeta` & `type-pill`)**:
  - Ekran görüntüsünde işaretlenen ikinci kırmızı alana (tarih satırının sağı) `type-pill` yerleştirildi:
    - **Avans Talebi**: Zümrüt yeşili zemin + `Wallet` ikonu
    - **İzin Talebi**: Açık mavi zemin + `CalendarDays` ikonu
    - **Genel Talep**: Mor zemin + `Layers` ikonu
  - Personel adı altındaki departman bilgisi yanına da talep türü eklendi (örn: `Kesme Açma · Avans`).
- [x] **3. Derleme & Doğrulama**:
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı, `postbuild` symlink'leri korundu.

## 2026-10-18 — Mobil SPA: Hızlı Erişim (Quick Actions) Operasyon Tarzı Renklendirme ve Tam Özelleştirme
- [x] **1. Operasyonlar Tarzı Renk & Çerçeve Standartları (`design-system.css`)**:
  - Hızlı erişim kutuları (`.quick-action-tile`) 1.5px renkli kenarlık, hafif gölge ve operasyonlar kutusundaki gibi renkli zeminli ikon balonu (`.quick-action-icon`) ile tasarlandı.
  - 10 farklı renk teması tanımlandı (`blue`, `emerald`, `amber`, `indigo`, `teal`, `red`, `purple`, `yellow`, `slate`, `pink`).
  - Hızlı Erişim kartı başlığına "Özelleştir" (`.qa-customize-trigger-btn`) butonu eklendi.
- [x] **2. Derin Bağlantı (Deep Linking) Desteği**:
  - Son incelenen personel ID'si yerel depolamada saklanarak `personnel_section:ek_odemeler` gibi kısayollardan doğrudan personelin ilgili alt sekmesine (Ek Ödemeler, Kesintiler, İzinler, Evraklar, Zimmetler vb.) geçiş sağlandı.
- [x] **3. 3 Sekmeli Kısayol Düzenleme Modalı (`HomeView.vue`) & Bento UI İyileştirmeleri**:
  - **Çift Scrollbar Giderildi**: Nested scroll çakışması kaldırıldı, kaydırma tek ve akıcı hale getirildi.
  - **Kapsül Segmented Bar**: Sekmeler pürüzsüz yuvarlak hap formuna kavuşturuldu, tarayıcı odak çerçeveleri temizlendi ve sekmeler arası geçişte en üste kaydırma (`scrollTop = 0`) sağlandı.
  - **Canlı Önizleme Bento Kartı**: Özel bağlantı oluşturulurken kısayolun ana sayfadaki gerçek görünümü dinamik olarak canlı yansıtıldı.
  - **Hızlı Şablon Chipleri (Presets)**: Ek Ödemeler, Maaş Kesintisi, İzinler, Avans vb. için tek dokunuşla tüm formu ve renk/ikonları dolduran hazır şablonlar eklendi.
  - **İkon ve Renk Seçimi**: 16 kavisli buton ve çift halkalı dairesel renk seçiciler tasarlandı, form alanlarına sol ikonlar eklendi.
  - **Sistem Kataloğu Gruplandı**: "Hızlı Bölümler & Derin Sekmeler" ile "Ana Sistem Modülleri" ayrıştırıldı.
- [x] **4. Kalıcılık & Sunucu Senkronizasyonu**:
  - Ayarlar hem tarayıcıda (`localStorage`), hem de veritabanında (`mobile_dashboard_settings`) kaydedildi.
- [x] **5. Derleme & Doğrulama**:
  - `npm run build` ile üretim paketi 0 hata ile derlendi.

## 2026-10-18 — Mobil SPA: Bordro Detayında Ödeme Türleri Akordiyon Yapısı
- [x] **1. HTML Başlangıç Durumu & Sanitize Mantığı (`sanitizeDetailHtml`)**:
  - Ana hesap kartları (`.bordro-main-calculation-collapse`) mobilde varsayılan olarak açık (`show`) ve `aria-expanded="true"` tutuldu.
  - Ödeme türleri alt kırılımları (`.child-row.collapse`) varsayılan olarak kapalı hale getirildi, `aria-expanded="false"` atandı.
- [x] **2. Vue Olay Yöneticisi (`handleDetailTableClick`)**:
  - Konteynera `@click="handleDetailTableClick"` bağlandı; `.parent-row[data-bs-target]` tıklamalarında ilgili `.child-row` elemanlarının `.show` sınıfı animasyonlu olarak açılıp kapanması sağlandı.
  - Ana hesap kartı başlıkları (`.calculation-toggle`) için de panel katlama desteği eklendi.
- [x] **3. CSS Vektörel Chevron & Akordiyon Animasyonları (`PayrollView.vue`)**:
  - Saf CSS vektörel oklar (`.rotate-icon::after`, `.panel-chevron::after`) eklendi.
  - `.child-row.collapse:not(.show)` için `display: none !important`, `.show` için flex ve `childRowExpand` animasyonu uygulandı.
  - Açık satırlarda chevron 180° dönerek elektrik mavisi renge büründü, dokunmatik aktif durumlar (`:active`) tasarlandı.
- [x] **4. Derleme & Doğrulama**:
  - `npm run build` ile üretim paketi 0 hata ile derlendi.
  - Playwright otomatik testi ile kapalı ve açık durumlar doğrulandı, ekran görüntüleri kaydedildi (`bordro-detail-accordion-collapsed.png`, `bordro-detail-accordion-expanded.png`).

## 2026-10-18 — Masaüstü: Araç Takip & Filo Yönetimi Altında Araç Dashboard Modülü
- [x] **1. SQL Scripti & Yetkilendirme (`sql/2026_10_18_add_arac_dashboard_menu_and_permissions.sql`)**:
  - `permissions` tablosuna `arac-takip/dashboard` yetkisi tanımlandı.
  - `user_role_permissions` üzerinden Süper Admin ve Firma Sahibi rollerine otomatik yetki verildi.
  - `menus` tablosunda Parent ID 56 ('Araç Takip/Yönetim') altına `menu_order = 49` ile ilk sıraya "Araç Dashboard" eklendi.
  - `user_menu_orders` tablosundaki özel kullanıcı menü sıralamasında `56` numaralı ana menü altına ilk sıraya yerleştirildi.
  - `permission_policies` tablosuna `arac-takip/dashboard` sayfa ve `arac-takip/api` `get-dashboard-stats` API politikaları bağlandı.
- [x] **2. Model Katmanı (`App/Model/AracDashboardModel.php`)**:
  - PDO parametrik sorgularla Filo durumu (toplam, aktif, zimmetli, boşta, servisteki, ikame), KM kullanımı (toplam km, günlük ortalama, araç başı ortalama, lider araç), Yakıt sarfiyatı (toplam tutar, toplam litre, 100km tüketim, km başı maliyet) ve Servis & Evrak göstergeleri oluşturuldu.
  - Yıllık 12 ay Yakıt & KM trendleri, Departman bazlı dağılım, Mülkiyet ve yakıt türü dağılımları yazıldı.
  - Top 5 KM, Top 5 Yakıt, Yaklaşan ve geciken kritik evraklar (Muayene, Sigorta, Kasko) ve son servis hareketleri metodları eklendi.
- [x] **3. Backend API Entegrasyonu (`views/arac-takip/api.php`)**:
  - `get-dashboard-stats` eylemi eklendi; Gate politikası ve dinamik JSON yanıt formatı sağlandı.
- [x] **4. Görünüm & Dinamik JavaScript (`views/arac-takip/dashboard.php` & `js/dashboard.js`)**:
  - `views/efatura/dashboard.php` tasarım standardına birebir uyumlu sayfa şablonu oluşturuldu.
  - Segmented dönem butonları (`Bu Ay`, `Geçen Ay`, `Son 3 Ay`, `Bu Yıl`, `Tümü`), Flatpickr tarih seçici, departman filtresi ve preloader animasyonu.
  - 4 Adet degrade kenarlıklı `.dashboard-stat-card`.
  - ApexCharts Combo Bar+Line (Yakıt & KM), Donut (Departman), Pie (Mülkiyet) ve Donut (Yakıt Tipi) grafikleri.
  - Top 5 KM, Top 5 Yakıt, Evrak Uyarıları ve Son Servisler tabloları.
  - macOS Dark tema tam uyumluluğu.
- [x] **5. Rota & Testler**:
  - `index.php` routeAliases dizisine `arac-dashboard` eklendi.
  - Model, API ve sayfa render testleri hatasız tamamlandı.

## 2026-10-18 — Mobil SPA: Cari Takip Modülü Bakiye ve Borçlu/Alacaklı Mantığı Düzeltmesi
- [x] **1. Model Katmanı (`App/Model/CariModel.php`)**:
  - `summary()` metodunda `bakiye > 0` şartı `borclu_cari_sayisi`na, `bakiye < 0` şartı `alacakli_cari_sayisi`na bağlandı.
  - `ajaxList()` metodunda `$balance_filter === 'borclu'` için `> 0`, `$balance_filter === 'alacakli'` için `< 0` yapılarak veritabanı filtreleri ters durumdan kurtarıldı.
- [x] **2. API Katmanı (`mobile-spa/public/api/cari.php` & `dist/api/cari.php`)**:
  - `balanceFilter` filtresinde `borclu` için `> 0`, `alacakli` için `< 0` uygulandı.
  - Liste kayıtlarında `bakiye_status` ve `bakiye_label`: `bakiye > 0 ? 'Borçlu' : ($bakiye < 0 ? 'Alacaklı' : 'Bakiye Yok')` olarak düzeltildi.
- [x] **3. Vue Arayüzü (`mobile-spa/src/views/CariView.vue`)**:
  - Obsidian Hero kartı lejantında:
    - "Top. Verdim (Alacak)" altında borçlu cari sayısı (`{{ summary.borclu_sayisi || 0 }} borçlu cari`) gösterildi ve tıklama `activeFilter === 'borclu'`ya bağlandı.
    - "Top. Aldım (Borç)" altında alacaklı cari sayısı (`{{ summary.alacakli_sayisi || 0 }} alacaklı cari`) gösterildi ve tıklama `activeFilter === 'alacakli'`ya bağlandı.
  - Filtre sekmelerinde `Borçlular` (7) ve `Alacaklılar` (5) sayaçları ve butonları doğru filtreye bağlandı.
  - Cari kartlarında pozitif bakiye (`+`) olan cariler için `Borçlu` rozeti (`badge-borclu`), negatif bakiye (`-`) olan cariler için `Alacaklı` rozeti (`badge-alacakli`) gösterildi.
- [x] **4. Derleme ve Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi; test scripti ile veritabanı hesapları ve API çıktıları doğrulandı.

## 2026-10-18 — Mobil SPA: E-Fatura Görüntüleme Ekrana Sığdırma (Fit-to-Screen), Zoom/Pinch ve PDF Dosya Paylaşımı
- [x] **1. Fatura Önizleme Modalı Ekrana Sığdırma (Fit-to-Screen)**:
  - Mobil ekranlarda masaüstü/A4 genişliğindeki (~820px) faturaların sağ yarısının kesilmesi sorunu çözüldü.
  - `onIframeLoaded` ile faturanın doğal boyutları dinamik okunup `fitScale` hesaplanarak fatura açılır açılmaz tam ekran genişliğine sığdırıldı.
- [x] **2. Etkileşimli Zoom ve İki Parmakla Kıstırma (Pinch-to-Zoom)**:
  - İki parmakla kıstırma (pinch-to-zoom) dokunmatik hareketleri (`onTouchStart`, `onTouchMove`, `onTouchEnd`) entegre edildi.
  - Altta yüzen modern frosted-glass kontrol çubuğu (`.fatura-zoom-dock`) ile `[-]`, `[+%.. / Sığdırıldı]`, `[+]` ve `[Sığdır]` butonları sunuldu.
  - Yakınlaştırıldığında dikey ve yatay kaydırma/panning akıcı hale getirildi.
- [x] **3. PDF Belgesi Olarak Paylaşma (Web Share API Files)**:
  - URL linki paylaşımı kaldırıldı; `/api/fatura.php?action=download_pdf&id=...` üzerinden PDF ikili dosyası indirilip `new File([blob], ...)` ile doğrudan PDF dosyası olarak paylaşıldı.
  - Hem liste kartlarındaki paylaş butonu hem de önizleme modalı başlığındaki yeni Paylaş butonu (`Share2`) bu mantığa bağlandı.
  - Dosya paylaşımını desteklemeyen tarayıcılar için otomatik PDF indirme fallback'i sağlandı.
- [x] **4. Backend PDF Dayanıklılığı (`EInvoiceService::downloadPdf`)**:
  - EDM'den PDF çekilemeyen durumlarda ve yerel taslaklarda `Dompdf` ile HTML önizlemeden anlık PDF üretip döndüren çift katmanlı fallback eklendi.
- [x] **5. Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi; `php -l` ile PHP sözdizimleri doğrulandı.

- [x] **1. Profil Fotoğrafları / Avatar 404 Çözümü**:
  - `requests.php` içindeki doğrudan disk yolu kullanımı kaldırıldı; `resolvePersonelAvatar()` fonksiyonu ile çoklu sütun adayı (`personel_resim_yolu`, `resim_yolu`) diskte `file_exists()` ile kontrol edildi.
  - Merkezi güvenli servis `/api/personnel-photo.php?id={Security::encrypt(personel_id)}` üzerinden sunulması sağlandı.
  - `personnel-photo.php` Gate yetki listesine `talepler`, `avans_talepleri`, `izin_talepleri`, `ariza_talepleri` eklendi.
  - `TaleplerView.vue` bileşeninde `<img>` etiketine `@error="item.avatar = null"` ve `loading="lazy"` eklenerek kırık görsel oluşması engellendi; resmi olmayan veya yüklenemeyen personeller için `getInitials(name)` baş harf avatarı gösterildi.
  - `package.json`'a `postbuild` hook'u eklenerek `npm run build` sonrasında `dist/uploads` ve `dist/assets/images` symlink'lerinin otomatik oluşturulması sağlandı.
- [x] **2. İşleme Al Butonu ve Durum Aksiyonları**:
  - Genel arıza/talepler sekmesinde "İşleme Al" butonu açık yeşil renkli, kompakt boyutta (`clay-btn--subtle-success`), Çözüldü butonunun hemen solunda konumlandırıldı.
  - Buton tıklandığında durumu "İşlemde"ye alacak şekilde entegrasyonu tamamlandı.
- [x] **3. Derleme ve Canlıya Alma**:
  - `npm run build` 0 hata ile çalıştırıldı, üretim çıktıları `dist/` dizinine aktarıldı.

## 2026-10-17 — Kesme/Açma Dinamik Olumlu/Olumsuz Filtreleme ve İş Türü Tanımlama Entegrasyonu Tamamlandı
- [x] **1. Veritabanı Mimarisi (`sql/add_sonuc_tipi_to_tanimlamalar.sql`)**:
  - `tanimlamalar` tablosuna `sonuc_tipi VARCHAR(20) NOT NULL DEFAULT 'olumlu'` kolonu eklendi.
  - Temel olumsuz kesme türleri ('Kapalı', 'ADREŞ YANLIŞ ABONE BULUNAMADI', 'SAYAÇ KASKİYE ALINDI', 'ABONE MÜSADE ETMEDİ.', 'TAPA SAĞLAM', 'MÜHÜR VE CONTA' vb.) başlangıç olarak 'olumsuz' işaretlendi.
- [x] **2. İş Türü Tanımlama Yönetimi (`views/tanimlamalar/`)**:
  - `is-turu.php`: Tabloya `SONUÇ TİPİ` kolonu, `data-filter="select"` filtresi, yeşil `Olumlu` ve kırmızı `Olumsuz` rozetleri eklendi.
  - Özet KPI kartına `Olumlu` ve `Olumsuz` sayaçları entegre edildi.
  - Ekleme/düzenleme modalına ve Excel yükleme şablonuna `sonuc_tipi` Select2 / Excel sütunu eklendi (`views/tanimlamalar/api.php`, `is-turu.js`, `is-turu-excel-sablon.php`).
- [x] **3. Model Katmanı (`App/Model/PuantajModel.php`)**:
  - `getComparisonByPeriods()` metoduna `$sonucTipi` parametresi ve `tn.sonuc_tipi` PDO prepare filtresi eklendi.
  - `getKesmeMonthlyComparisonByDay()` tek seferde `all`, `olumlu` ve `olumsuz` dataset'lerini üretecek şekilde optimize edildi.
- [x] **4. Ana Sayfa & Dağılım Modalı Entegrasyonu (`views/home.php`, `views/home/api.php`)**:
  - Kart başlığına ve `#kesmeDistributionModal` başlık çubuğuna (kapatma butonunun hemen soluna) `Tümü / Olumlu / Olumsuz` toggle butonları yerleştirildi.
  - Modal içindeki tüm KPI kartları (`Toplam iş`, `İşlem türü`, `Toplam kayıt`, `Aktif bölge`), `İş Sonucu Dağılımı` ve `Bölge Katkısı` ile ana karttaki tüm metrikler ve grafik verileri anında senkronize filtrelenecek şekilde bağlandı.
  - Kart ve modal açılışında varsayılan olarak **Olumlu** seçeneği aktif (`active`) hale getirildi.

## 2026-10-17 — Mobil SPA: Personel İcra Dosyaları ve Yasal Kesintiler Modülü Tamamlandı
- [x] **1. Bento Obsidian Hero Kartı (`.cari-hero-volume-card`)**:
  - Toplam borç, kesilen bakiye ve kalan borç hacmi özeti.
  - İki renkli segmentli ilerleme çubuğu (% kesilen / % kalan).
  - 4'lü tıklanabilir filtre lejantı (`Tüm Dosyalar`, `Devam Eden`, `Sırada / Bekleyen`, `Tamamlanan`).
- [x] **2. Canlı Arama ve Kategori Filtre Hapları (`.clay-filter-pill`)**:
  - Dosya numarası, icra dairesi veya alacaklı adı ile dinamik arama.
  - Durum bazlı filtreleme sekmeleri (`Tümü`, `Devam Edenler`, `Sıradakiler`, `Tamamlananlar`).
- [x] **3. Zengin İcra Bento Kartları (`.icra-item-card`)**:
  - Sıra rozeti (#1. Sıra), dosya no, icra dairesi adı, durum rozeti (`clay-badge`).
  - Alacaklı bilgisi, 3 sütunlu bakiye özeti (Toplam, Kesilen %, Kalan).
  - Kart içi mini ilerleme çubuğu.
  - Kesinti planı (`Sabit Tutar ₺` veya `Maaş Net %`), tarih aralığı.
  - Tek tıkla kopyalanabilen IBAN çipi ve not alanı.
  - Kesinti geçmişi görüntüleme, düzenleme ve silme aksiyon butonları.
- [x] **4. Yeni Dosya Ekleme & Düzenleme Modalı (`showIcraModal`)**:
  - Daire otomatik tamamlama (`icra_daireleri` tablosundan dinamik datalist).
  - Parametrik doğrulama ve veri kaydı.
- [x] **5. Kesinti Geçmişi Zaman Çizelgesi Modalı (`showIcraKesintiGecmisiModal`)**:
  - Bordrodan onaylanan tüm kesintilerin dönem bazında listesi (`personel_kesintileri`).
- [x] **6. Backend & Veritabanı Entegrasyonu**:
  - `MobilePersonelModel.php`: `getIcraDetails`, `saveIcra`, `deleteIcra`, `getIcraKesintileri` metotları.
  - `public/api/personnel.php` & `dist/api/personnel.php`: `detail`, `icra_kesintileri`, `save_icra`, `delete_icra` API aksiyonları.
  - Gate yetki kontrolleri (`Gate::allows('personel_duzenle')`), CSRF denetimi ve `SystemLogModel` loglama.
- [x] **7. Derleme & Doğrulama**:
  - `npm run build` ile 0 hata ile derlendi (Vite v6.4.4).
  - PHP sözdizimleri `/opt/lampp/bin/php -l` ile doğrulandı.

## 2026-10-17 — Mobil SPA Ana Sayfa: Bugünün Nöbetçileri Personel Fotoğrafı ve Fallback İyileştirmesi
- [x] **1. Model Katmanı Standardı (`MobileDashboardModel.php`)**:
  - `shifts()` metodunda ham dosya yolu (`/assets/images/...`) yerine Kural 12 standardına uygun olarak `/api/personnel-photo.php?id={Security::encrypt(personel_id)}` merkezi servis URL'si tanımlandı.
- [x] **2. Güvenli Fotoğraf Servisi Gate Yetkisi (`personnel-photo.php`)**:
  - `personnel-photo.php` erişim yetkilerine `nobet_goruntuleme`, `nobet_onaylama`, `nobet_yonetim` ve `nobetler` eklendi.
- [x] **3. Vue Hata Fallback Koruması (`HomeView.vue`)**:
  - Nöbetçi kartındaki avatar `<img>` etiketine `@error="shift.photo = null"` eklendi. Böylece görsel bulunamasa bile kırık görsel ve baş harflerin üst üste binmesi engellenerek doğrudan şık gradyanlı avatar devreye girdi.
- [x] **4. Derleme**:
  - `npm run build` ile Vite üretimi 0 hata ile tamamlandı.

- [x] **1. API Köprüsü Oluşturuldu (`/api/puantaj-sync.php`)**:
  - `mobile.ersantr.com` document root'u (`mobile-spa/dist`) nedeniyle masaüstünün `views/puantaj/api.php` dosyasına yapılan doğrudan isteklerdeki `404 Not Found` hatası giderildi.
  - SPA standartlarına uygun `mobile-spa/public/api/puantaj-sync.php` köprüsü oluşturuldu ve `dist/api/` dizinine aktarıldı.
- [x] **2. Sıralı (Sequential) Sorgulama & Lock Koruması (`dashboard.js`)**:
  - `syncDailyOperations()` fonksiyonu yeni köprüye yönlendirildi.
  - Endeks Okuma, Kesme/Açma, Mühürleme ve Sayaç Değişimi işlemleri lock çakışmasını engellemek için sıralı `for ... of` döngüsü ile çalıştırıldı.
  - Sorgulama bittiğinde `getDashboard()` ile veriler anında arayüzde tazelendi.
- [x] **3. Derleme**:
  - `npm run build` ile Vite üretimi 0 hata ile tamamlandı.

- [x] **1. Güvenli Resim Servisi (`personnel-photo.php`) Entegrasyonu**:
  - `nobet.php` API'sinde ham disk yolu (`/assets/images/...`) yerine SPA standardı olan `/api/personnel-photo.php?id=` formatına geçildi.
  - `personnel-photo.php` erişim yetkilerine `nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list`, `shifts` eklendi.
  - Fiziksel dosya kontrolünde `personel_resim_yolu` ve `resim_yolu` sütunları sıralı taranarak ilk geçerli fiziksel resmin sunulması sağlandı (böylece eski/eksik resim yolları olan personellerin mevcut vesikalıkları da kurtarıldı).
- [x] **2. Vue Görünümü Fallback Koruması (`NobetOnayView.vue`)**:
  - Havuz, Onay Listesi, Takvim ve Nöbet Planlama modalındaki tüm avatar `<img>` etiketlerine `@error="p.avatar = null"` / `@error="shift.personel_avatar = null"` eklendi.
  - Görseli bulunmayan veya yüklenemeyen personellerde kırık resim ikonu engellenerek anında şık gradyanlı baş harf rozeti (`initials`) gösterildi.
- [x] **3. Derleme**:
  - `npm run build` ile Vite bundle'ı 0 hata ile derlendi.
- [x] **1. Bordro Standardında Personel Seçici Kartı (`.person-selector-card`)**:
  - `design-system.css`: `.person-selector-card`, `.person-selector-avatar-box`, `.person-selector-card__meta`, `.person-selector-sub`, `.person-selector-name`, `.person-mini-status`, `.person-selector-card__right` ve `.person-change-btn` sınıfları eklendi.
  - `PersonnelSectionView.vue`: Üst profil kartı Bordro tasarımına kavuşturuldu; sol tarafta büyütülebilir avatar, `SEÇİLİ PERSONEL` etiketi, personel adı, aktiflik rozeti (`● Aktif` / `● Ayrıldı`); sağ tarafta sekme `Kaydet` butonu ve `Değiştir ⌵` butonu konumlandırıldı.
- [x] **2. Arama Kutulu Personel Seçim Modalı (`.payroll-modal-sheet--period`)**:
  - `PersonnelSectionView.vue` & `PersonnelDetailView.vue`: `Değiştir ⌵` tıklandığında alttan açılan bottom-sheet modal (`isPersonSheetOpen`).
  - Modal başlığı (`PERSONEL SEÇİMİ`), kapatma ikonu (`clay-circle-btn`).
  - Anlık arama kutusu (`.person-sheet-search-wrap`): ad, görev ve departmana göre filtreleme.
  - Personel listesi (`.period-sheet-list`): avatar/baş harf ikonu (`.period-sheet-icon`), isim, görev/departman, aktif/ayrıldı hap rozeti ve seçili personel için mavi onay imi (`.period-sheet-check`).
  - Tıklandığında personelin seçilmesi, modalın kapanması ve ilgili ekrana yönlendirilmesi sağlandı.
- [x] **3. Evraklar Bölümü Bento Obsidian Hacim Kartı (`PersonnelSectionView.vue` - `/personel/:id/evraklar`)**:
  - Unstyled dikey metin yığını yerine Cari Takip tarzı Bento Obsidian kartı ve 4 segmentli ilerleme çubuğu uygulandı.
  - 2x2 tıklanabilir interaktif lejant ızgarası (`cari-chart-legend--2x2`):
    - **Özlük & Kimlik** (Sky Blue)
    - **Sözleşme & Yasal** (Emerald)
    - **Sağlık & Eğitim** (Purple)
    - **Diğer Belgeler** (Coral)
  - Lejant kutularına tıklandığında ilgili belge türü kümesi anında filtreleniyor.
  - Altında canlı arama kutusu, kategori filtreleme hapları (`clay-filter-pill`) ve belge kartları düzenlendi.
- [x] **4. Backend Desteği (`MobilePersonelModel.php`)**:
  - `mobileDirectoryQuickList()` içine `avatar` alanı entegre edildi.
- [x] **5. Derleme & Doğrulama**:
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı (0 hata).
  - `/opt/lampp/bin/php -l` ile PHP sözdizimleri doğrulandı.

## 2026-10-17 — Mobil SPA: Personel Fotoğrafı Lightbox, Hızlı Personel Değiştirme ve Evrak Yönetimi
- [x] **1. Personel Fotoğrafı Büyütme (Lightbox Modalı)**:
  - `design-system.css` içine `.clay-lightbox-*` merkezi bileşen stilleri eklendi (koyu bulanık arka plan, 28px kavisli kart, büyük görsel/initials fallback, küçültme/büyütme animasyonu).
  - `PersonnelView.vue`: Personel kartı üzerindeki avatara tıklandığında doğrudan büyük fotoğraf modalı açılıyor.
  - `PersonnelDetailView.vue` & `PersonnelSectionView.vue`: Profil kartındaki avatar tıklandığında lightbox açılıyor.
- [x] **2. Hızlı Personel Değiştirme (Personnel Switcher)**:
  - `MobilePersonelModel.php` içine `mobileDirectoryQuickList(int $firmaId): array` eklendi.
  - `personnel.php` API detay yanıtına firma personelleri listesi dahil edildi.
  - `PersonnelDetailView.vue` ve `PersonnelSectionView.vue`: Personel adının olduğu alana şık mavi hap ve `ChevronDown` ikonuyla yerel mobil sistem seçicisi (native select picker) entegre edildi.
  - Seçim yapıldığında `router.push` ile personelin profiline ve `PersonnelSectionView` üzerinde bulunulan mevcut sekmeye (çalışma, izinler, evraklar vb.) anında yönlendirme sağlanıyor.
- [x] **3. Personel Evrakları Yönetim Sayfası (`PersonnelSectionView.vue` - `/personel/:id/evraklar`)**:
  - `App/Model/MobilePersonelModel.php`:
    - `getEvrakDetails`: Aktif evrakları ve kategori istatistiklerini getirir.
    - `uploadEvrak`: ISO 27001 gereğince dosyaları **AES-256-GCM** (`Security::encryptFile`) ile şifreleyerek `uploads/personel_evraklar/{id}/{hash}.enc` yoluna kaydeder, veritabanına ve sistem loguna işler.
    - `deleteEvrak`: Soft delete (`aktif = 0`) uygular ve loglar.
  - `views/personel/evrak-indir.php`: `personel_listesi` ve `personel_goruntule` izinlerine sahip kullanıcıların da şifreli evrakları anlık deşifre ederek (`Security::decryptFile`) görüntülemesine/indirmesine izin verildi.
  - `mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`: `upload_evrak` ve `delete_evrak` aksiyonları eklendi.
  - `mobile-spa/src/services/personnel.js`: `uploadEvrak` ve `deleteEvrak` servis metodları eklendi.
  - `PersonnelSectionView.vue`:
    - Obsidian bento hacim kartı (Toplam evrak adedi ve kategori dağılım hapları).
    - Canlı arama kutusu ve kategori hap filtreleri (`clay-filter-pill`).
    - Evrak kartları: Belge türü rozetleri (PDF, IMG, DOC, XLS), evrak adı, kategori rozeti, dosya boyutu, yüklenme tarihi ve yükleyen kişi.
    - Görüntüle (güvenli anlık deşifre), İndir ve Sil butonları.
    - Yeni Evrak Yükleme Bottom-Sheet Modalı: Evrak türü, başlık, açıklama, Dropzone dosya seçici ve ISO 27001 güvenlik güvencesi.
- [x] **Derleme & Doğrulama**:
  - `npm run build` ile Vite üretimi 0 hata ile derlendi.
  - PHP CLI (`/opt/lampp/bin/php -l`) ile tüm backend dosyalarının sözdizimi doğrulandı.

## 2026-10-17 — Mobil SPA Raporlar: Varsayılan Ay Aralığı ve Kapalı Akordiyon İyileştirmesi
- [x] **Varsayılan Dönem: İçinde Bulunulan Ay (`01.10.2026 - 31.10.2026`)**:
  - `reports.js` içinde `new Date().toISOString()` kaynaklı UTC+3 saat dilimi kayması (`30.09.2026`) giderildi; `formatLocalYmd()` ve `getCurrentMonthRange()` fonksiyonlarıyla ayın 1'inden ayın son takvim gününe kadar olan aralık yerel olarak üretildi.
  - `reports.php` API'sinde `buay` filtresi varsayılan bitiş tarihi `date('Y-m-t')` (ayın son günü) olarak ayarlandı ve `date_range_label` `01.10.2026 – 31.10.2026` olarak sunuldu.
  - `ReportsView.vue` içindeki `selectMonth` fonksiyonunda bugüne sınırlandırma kaldırılarak seçilen ayın tam takvim ayı olarak uygulanması sağlandı.
- [x] **Bölge Akordiyon Detaylarının Kapalı Başlaması**:
  - Sayfa açıldığında ve veriler yüklendiğinde tüm bölgelerin detay kısımları varsayılan olarak kapalı (`expandedRegions[r.name] = false`) gelecek şekilde ayarlandı.
  - Sekme değiştirildiğinde (`activeTab`) durum sıfırlanarak yeni sekmede de bölgelerin kapalı başlaması sağlandı.
  - Arama yapıldığında eşleşen bölgeler otomatik genişletilip arama temizlendiğinde tekrar kapatılıyor.
- [x] **Derleme & Doğrulama**:
  - `npm run build` ile üretim derlemesi alındı (`dist/`).
  - PHP CLI ile test edilerek JSON çıktısı ve dönem formatı doğrulandı.

## 2026-10-17 — Mobil SPA Ana Sayfa: Kart Gizleme Butonları Temizliği, Yalnızca İkon Sorgula ve Bildirim İkonu Entegrasyonu
- [x] Kullanıcının talepleri doğrultusunda ana sayfadaki kontroller ve üst bar güncellendi:
  - **Kart İçi Gizleme Butonları Temizliği**:
    - Kart başlıklarındaki kırmızı `EyeOff` (`card-inner-btn--danger`) gizleme butonları kaldırıldı (Nöbetçiler, Araç & Filo, Operasyonlar, Hızlı Erişim ve Kurumsal Duyurular).
    - Kart gizleme/gösterme işlemleri "Kartları Düzenle" modalindeki switch kontrollerine ve alttaki gizlenen kartlar çubuğuna devredildi.
  - **Operasyonlar Kartı — Yalnızca İkon Olan Sorgula Butonu**:
    - Günlük operasyonlar başlığındaki `.op-sync-all-btn` içindeki metin (`Sorgula` / `Sorgulanıyor…`) kaldırıldı; yerine 26x26px dairesel, kompakt `RefreshCw` ikon butonu uygulandı.
  - **TopBar Bildirim İkonu & Sayaç Rozeti Entegrasyonu**:
    - `design-system.css` içine `.topbar-bell-btn` ve `.topbar-bell-badge` global stilleri tanımlandı.
    - `HomeView.vue` TopBar slotunda sağ üst kısma "Kartları Düzenle", bildirim butonu (Bell + okunmamış rozeti) ve profil avatar butonu entegre edildi.
  - **Derleme**: Vite v6.4.4 (`npm run build`) ile 0 hata ile derlendi.

- [x] Kullanıcının bildirdiği "sayfadaki tablo hala gelmiyor" ve "yukarı oka basınca tablo kayboldu" sorunu analiz edilip giderildi:
  - **Kök Neden**: `views/personel/puantaj.php` dosyasındaki `.izin-palette-header` etiketinin eksik kapatılması nedeniyle tablonun `.puantaj-focus-panel` içine dahil olması ve odak moduna girildiğinde tablonun da `max-height: 0; opacity: 0; visibility: hidden;` ile gizlenmesiydi.
  - **Div Hiyerarşisi**: Eksik kapatılan div eklendi, tablo focus panelden ayrıldı ve dosya sonundaki fazlalık `</div>` kaldırılarak div stack'i 92/92 tam dengeye kavuşturuldu.
  - **Odak Modu State Sıfırlama**: `views/personel/js/puantaj_izin.js` içinde eski bozuk `localStorage` kaydı temizlendi, varsayılan olarak açık başlayacak şekilde güncellendi. Artık yukarı oka basıldığında sadece üst filtreler gizlenip tablo genişler, tablo asla kaybolmaz.
  - **Doğrulama**: PHP CLI ve cURL ile render testleri başarıyla doğrulandı.

## 2026-10-17 — Mobil SPA Ana Sayfa: Operasyon Kutularında Eşit Renkli Kenarlık ve Saf Beyaz Zemin
- [x] Kullanıcının talebi doğrultusunda operasyon kutularındaki sol kalın vurgu çizgisi (`border-left: 3.5px solid`) ve zeminlerdeki `linear-gradient` renk geçişi kaldırıldı.
- [x] Tüm kutular saf beyaz (`background: var(--ds-surface, #ffffff);`) zemin üzerine tüm kenarları eşit `1.5px solid` renkli çerçeve ile net, minimal ve sade hale getirildi.
- [x] İkon balonları ve Git butonları ilgili operasyon renginde canlılığını korudu.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Puantaj: FM Akordeon Tasarımı ve Aylık Takvim Sığdırma İyileştirmesi
- [x] Kullanıcının talepleri doğrultusunda mobil puantaj arayüzünde iki önemli UX iyileştirmesi tamamlandı:
  - **Hızlı Gün Statüsü Bottom Sheet (`status-picker-sheet`) FM Akordeonu**:
    - Fazla Mesai (FM) alanı akordeon bileşeni haline getirildi ve varsayılan olarak kapalı (`showPickerFmAccordion = false`) başlaması sağlandı.
    - Akordeon kapalıyken tek satırda başlık, mor saat ikonu, mevcut mesai saatini gösteren rozet (`+Xs Mesai` / `(Mesai Yok)`) ve `ChevronDown` ikonu gösterilerek modal yüksekliği sadeleştirildi.
    - Akordeon açıldığında yumuşak geçişle hızlı saat çipleri (1s, 1.5s, 2s, 3s, 4s, 5.5s, 7.5s, [Kaldır]) ve serbest saat giriş inputu erişilebilir hale geldi.
  - **Personel Ay Detay Modalı ("Ay İçi Günlük Takvim") Dikey Taşma ve Kesilme Sorunu Çözümü**:
    - Takvim kartının (`clean-calendar-box`) alt kenarından 26-31 satırının kesilmesi sorunu giderildi.
    - `.clean-calendar-box` üzerindeki `overflow: hidden;` kaldırıldı, `overflow: visible;` ve `flex-shrink: 0;` verilerek kartın alt satırları içine alacak şekilde dikeyde ezilmeden esnemesi sağlandı.
    - `.calendar-tile` için aşırı uzun dikey boy oluşturan `aspect-ratio: 1 / 1.15;` değeri `aspect-ratio: 1 / 0.95;` oranına çekildi.
    - Grid boşlukları (`gap: 3px;`), gün başlıkları (`padding: 2px 0 4px;`) ve hücre iç boşlukları (`padding: 2.5px 1px 2px;`) dengelenerek tüm 31 gün ve 6 haftalık aylarda hiçbir gün kesilmeden tek bakışta sığması sağlandı.
    - Hızlı Damga Modu kartı (`stamp-card-box`) da dikeyde kompaktlaştırılarak (`padding: 10px 12px; gap: 8px;`) takvime ferah görüntü alanı kazandırıldı.
  - **Derleme & Doğrulama**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-17 — Masaüstü Puantaj: Değişken Saatli Fazla Mesai (FM) Çift Katmanlı (Overlay) Girişi ve Bordro Entegrasyonu
- [x] Kullanıcının talebi ("puantaj kısmında HTÇ gibi değişken saatli fazla mesai girme ve mevcut puantaj türünü koruma") doğrultusunda çift katmanlı mimari eksiksiz geliştirildi:
  - **Veritabanı Şeması**: `sql/create_personel_fazla_mesaileri.sql` oluşturuldu ve `personel_fazla_mesaileri` tablosu çalıştırıldı.
  - **Model Katmanı**: `App/Model/PersonelFazlaMesaileriModel.php` geliştirildi (PDO parametrik binding, soft delete, aylık toplu ve tekil mesai sorguları).
  - **Puantaj API Katmanı**: `views/personel/api/puantaj_izin.php` içinde takvim verilerine `overtimes` eklendi, `save-bulk-entries` aksiyonu hem izinleri hem fazla mesaileri transaction içinde atomik kaydeder hale getirildi.
  - **Puantaj Frontend & JS**: `views/personel/puantaj.php` ve `views/personel/js/puantaj_izin.js` güncellendi:
    - **İzin Paletine Fazla Mesai Butonu & Toggle Saat Kutusu**: `#btn-toggle-fm-mode` (mor buton) eklendi. Saat kutusu (`#fm-hours-input-wrapper`) varsayılan olarak gizlendi ve yalnızca kullanıcı butona tıklayıp modu aktif ettiğinde akıcı biçimde görünür hale getirildi (kapatıldığında veya başka izin seçildiğinde otomatik gizlenir).
    - **Fazla Mesai Standart Bootstrap Modalı (`#fazlaMesaiModal`)**: SweetAlert prompt yerine kullanıcı dostu standart modal geliştirildi; personel adı ve tarihi, Kesme departmanı uyarısı, saat kutusu, hızlı saat butonları (1s, 1.5s, 2s, 3s, 4s, 5.5s, 7.5s), [Mesaiyi Kaldır], [Vazgeç] ve [Kaydet] butonları entegre edildi.
    - **Kesme Departmanı Fazla Mesai Uyarısı**: Kesme departmanındaki personellerin mesaileri normalde Nöbet modülü üzerinden geldiği için; bu personellere puantajdan fazla mesai girilmek istendiğinde modal içinde sarı uyarı kutusu ile bilinçli onay alınması sağlandı.
    - **Puantaj Sürükleme ve Palet Düzeltmesi**: Paletin yüzen bir pencereye dönüşüp tüm div'in tablonun üstüne kayması (`initIzinPalette`) tamamen kaldırıldı, palet yerine sabitlendi; tür sürüklenirken `setDragImage` ile fare ucunda yalnızca küçük tür kutusu (`.izin-box`) görünecek şekilde optimize edildi.
    - **Performans İçin Hover Tooltip İptali**: Binlerce hücrede fare gezinirken performansı düşüren Bootstrap Tooltip dinleyicisi iptal edildi, native title desteğine geçilerek sayfa akıcılığı maksimize edildi.
    - Hücre üzerine binen `.overtime-badge` (+Xs rozeti) ile gün bazlı çalışma türü (X, RTÇ, HTÇ) bozulmadan üzerine saat rozeti işlendi.
    - Tablonun sağına `Toplam FM` sütunu eklenerek personelin ay içindeki toplam mesai saati canlı hesaplandı.
  - **Bordro Hesaplama Motoru Entegrasyonu**:
    - `BordroPersonelModel::olusturFazlaMesaiOdemeleri()` ile dönemdeki puantaj mesai saatleri otomatik çekilip kanuni `(Asgari Net / 225) * 1.5 * Saat` formülüyle net hakediş (asgari net saat ücreti 124,78 ₺ × 1,5 = 187,17 ₺ / saat) ve gross-up brüt hesaplanarak `[Puantaj Fazla Mesai]` resmi ek ödemesi olarak kaydedildi.
    - Bordro detay modalında kullanıcıların %50 artırımı doğrudan görmesi için açıklama formatı `187,17 ₺ × 1,00 saat (124,78 ₺ × 1,5)` şeklinde güncellendi.
    - `getMuhasebeFazlaMesaiNetOzeti()` ve `views/bordro/api.php` içinde hem nöbet mesaisi hem de puantaj mesaisi toplam saat ve resmi brüt olarak birleştirildi; muhasebe Excel'i ve bordro detay modalıyla %100 uyum sağlandı.
  - **Doğrulama**: PHP CLI (`/opt/lampp/bin/php -l`) ve matematiksel hakediş simülasyonları hatasız doğrulandı.

## 2026-10-17 — Mobil SPA: Puantaj Modal Başlıkları ve Handle Hizalama İyileştirmesi
- [x] Kullanıcının talebi ve paylaştığı ekran görüntüsü (`media_1792236447288_97036ca8.png`) doğrultusunda modal başlıkları düzeltildi:
  - **Handle Sola Kayma Sorunu Giderildi**: `status-picker-header` bir yatay flex container iken içine konulan handle (`.ds-sheet-handle`) dışarı çıkarılarak doğrudan `.ds-sheet`'in ilk elemanı yapıldı ve `margin: 10px auto 4px;` ile tam ortalandı.
  - **Başlık ve Kapat Butonu Simetrisi**: `status-picker-header` ve `detail-sheet-header` tek bir satırda sol tarafta avatar + kullanıcı adı + meta bilgi (tarih / departman) ve sağ tarafta 34x34px dairesel kapatma butonu (`clay-circle-btn`) olacak şekilde tek bir flex row olarak hizalandı.
  - **Bütünleşik Beyaz Başlık (Ayrı Köşeli Div Yok)**: `.ds-sheet` tepeden itibaren bembeyaz (`#ffffff`) ve 28px kavisle başlayacak şekilde ayarlandı; arkadaki köşeli basamak hissi tamamen ortadan kaldırıldı.
  - **Derleme & Doğrulama**: `npm run build` ile 0 hata ile derlendi (Vite v6.4.4) ve `dist/api/puantaj.php` senkronize edildi.

## 2026-10-17 — Mobil SPA Sistem Logları Modülü (Son 2 Günlük Varsayılan Filtre, Arama & Detay)
- [x] Kullanıcının talebi doğrultusunda mobil SPA için Sistem Logları sayfası ve altyapısı eksiksiz geliştirildi:
  - **Varsayılan Son 2 Günlük Filtreleme**: Kullanıcı talebi gereği sayfa ilk açıldığında doğrudan son iki günün (dün ve bugün) logları listelenir.
  - **Soft Bento & Claymorphic UI Tasarımı**: `design-system.css` tokenları, `--ds-canvas: #e6eaef`, 4 Bento KPI kartı (Toplam, Kritik, Önemli, Girişler) ve claymorphic liste kartları.
  - **Filtreleme & Arama İşlemleri**: Anlık metin araması (açıklama, işlem tipi, kullanıcı ve IP), hızlı zaman hapları (Son 2 Gün, Bugün, Son 7 Gün, Son 30 Gün, Tümü), hızlı seviye hapları (Kritik, Önemli, Bilgi, Sayfa Ziyareti) ve detaylı filtre çekmecesi.
  - **Log Detay Çekmecesi (Sheet)**: Tıklanan log kaydının tüm detaylarını (ID, seviye rozeti, formatlı tarih, göreceli zaman, kullanıcı, IP ve açıklama) gösteren modal.
  - **Model & API Katmanı**: `SystemLogModel` içinde PDO parametrik binding ile `getMobileLogs`, `getMobileLogsCount`, `getMobileLogStats` ve `getMobileFilterOptions` metotları oluşturuldu. `public/api/logs.php` API uç noktası hazırlandı ve `dist/api/logs.php` ile senkronize edildi.
  - **Yetkilendirme**: `Gate::allows('log_kayitlari') || Gate::isSuperAdmin()` ile korundu, `auth.php` permissions haritasına eklendi.
  - **Navigasyon & Menü**: `router.js` (`/loglar`, `/log-kayitlari`), `BottomNav.vue` (SİSTEM & DESTEK altında "Sistem Logları") ve `HomeView.vue` hızlı erişim butonlarına eklendi.
  - **Derleme**: `npm run build` ile başarıyla derlendi.

- [x] Kullanıcının talepleri doğrultusunda takvim taşması ve sheet başlığı kusurları giderildi:
  - **Aylık Takvim Günlerinin Dışarı Taşması Engellendi**: Grid yapısı `repeat(7, minmax(0, 1fr))` mimarisine geçirildi, kart içi yatay padding optimize edildi ve hücrelere `min-width: 0; aspect-ratio: 1 / 1.15; overflow: hidden;` verilerek 7 sütunun en dar mobil ekranlarda dahi kutu sınırları içinde kalması sağlandı.
  - **Sheet Başlığı Bütünleştirildi (Köşeli Ayrı Div Kaldırıldı)**: Ayrı köşeli basamak oluşturan div yapısı kaldırılarak; handle, personel avatarı, adı ve kapat butonu doğrudan sheet'in 32px kavisli üst başlığıyla (`border-radius: 32px 32px 0 0`) tek parça olarak birleştirildi.
  - **Derleme**: Vite üretimi (`npm run build`) başarıyla tamamlandı.

## 2026-10-17 — Mobil SPA Kasa Takibi: Cari Takip Tasarım Standardı & Obsidian Bento Dönüşümü
- [x] **Hero Obsidian Bento Kartı & Çift Yönlü Segmentli Bar (`KasaView.vue`)**:
  - Cari Takip (`CariView.vue`) ile birebir uyumlu koyu zeminli (`#191d24`, 28px kavisli köşe, `var(--ds-shadow-bento-dark)`) Kasa İşlem Hacmi kartı geliştirildi.
  - Kart başlığında `BarChart3` ikon kutusu, `KASA İŞLEM HACMİ`, `Tahsilat & Ödeme Dağılım Analizi` ve sağda `Pozitif Denge / Negatif Denge` hap rozeti.
  - Ortada büyük 26px beyaz Kasa İşlem Hacmi tutarı ve altında renkli Net Kasa Bakiye satırı.
  - İki renkli segmentli doluluk çubuğu: Zümrüt yeşili Gelir (Tahsilat) ve Gökyüzü mavisi Gider (Ödeme) yüzdeleri.
  - Alt Lejant Izgarası: Toplam Gelir ve Toplam Gider için ayrı interaktif kutucuklar, tutarlar ve adetler.
- [x] **Cari Takip Standartlarında Arama ve Filtre Hapları**:
  - Yıl ve Ay seçicileri kompakt dönem hapları olarak üst satıra alındı.
  - 999px oval beyaz arama çubuğu (`Search` ikonu, temizleme butonu).
  - Siyah aktif haplı ve renkli noktalı filtre tabları: `Tümü (Toplam)`, `• Gelirler`, `• Giderler`.
- [x] **3 Kolonlu Kart Listesi (`.kasa-card` / `.cari-card`)**:
  - 46x46px koyu zeminli avatar kutusu içinde kategorinin 2 harfli baş harfleri (`AK`, `EF`, `MA`, vb.).
  - Başlıkta 13.5px bold kategori adı, alt satırda hesap/muhatap, en altta saat ve açıklama/plaka meta satırı.
  - Sağ tarafta büyük renkli tutar (`+` / `-`), `GELİR` / `GİDER` rozeti, hızlı detay butonu (`Eye`) ve `ChevronRight`.
- [x] **Sağ Altta Çift Kayan Dairesel Butonlar (Floating Buttons)**:
  - Cari Takipteki gibi üst üste `History` geçmiş butonu ve `+` Yeni işlem FAB butonu.
- [x] **Cari Takip Bottom Sheet Form ve Detay Modalları**:
  - Segmented Gelir/Gider seçici, ₺ para kutusu, hızlı tutar çipleri (`+250 ₺`, `+500 ₺`, `+1.000 ₺`, `+5.000 ₺`), parametreli detay fişi.
- [x] **Vite Üretim Derlemesi & Önbellek İptali**: 
  - `npm run build` ile üretim paketi temizlendi ve yeniden derlendi (`dist/assets/index-DMCy-om9.js` ve `index-BTm_GUkl.css`).
  - Paket içinde `kasa-hero-volume-card` ve Cari Takip bileşenlerinin bulunduğu, eski `GÜNCEL NET BAKİYE` metninin bulunmadığı doğrulandı.
  - Service Worker sürümü `ersan-mobile-shell-v20` seviyesine yükseltildi (`skipWaiting` ve `clients.claim` ile eski önbellek silinmesi tetiklendi).
  - `main.js` içine `reg.update()` eklenerek tarayıcının her ziyarette yeni paketi doğrudan çekmesi sağlandı.

## 2026-10-17 — Mobil SPA Puantaj — Hızlı Gün Statüsü Seçici Modalı ve SGK Vizite Servisi Çözümü
- [x] Kullanıcının talepleri doğrultusunda gün seçici ve SGK servisi tamamlandı:
  - **Hızlı Gün Statüsü Seçici Bottom Sheet (`status-picker-sheet`)**: Haftalık gün kapsülüne basıldığında tüm puantaj türlerini (Ücretli & Ücretsiz sekmeleriyle X, HT, Yİ, SGK, Bİ, Eİ vb.) içeren şık bir seçim menüsü açıldı. Kullanıcı istediği türe bastığı anda doğrudan gün hücresine atanır ve sheet kapanır.
  - **SGK Vizite Servisi Çözüldü**: `App/Service/SgkViziteService.php` dosyasının global namespace yapısı nedeniyle oluşan `Class "App\Service\SgkViziteService" not found` hatası, dosyanın doğrudan require edilip `\SgkViziteService` olarak çağrılmasıyla giderildi.
  - **Derleme**: Vite üretimi (`npm run build`) başarıyla tamamlandı.

## 2026-10-17 — Mobil SPA "Daha Fazla" (Diğer) Menüsü: Yatay Kart & Sayfa Başlığı Standardı Dönüşümü
- [x] **Yatay Liste & Sayfa Başlığı Hiyerarşisi (`BottomNav.vue`)**:
  - Eski 3 sütunlu mikro ızgara yapısı kaldırılarak; sayfa başlıklarındaki (`TopBar.vue`) görsel diliyle %100 uyumlu yatay kart listesi mimarisine geçildi.
  - Her kart için: Sol rozet (42px dairesel beyaz `clay-circle-btn` formunda pastel renkli modül ikonu), sağ blok (üstte 9px uppercase kategori/eyebrow etiketi, ortada 14px kalın başlık, altta 11px açıklayıcı alt metin) ve sağ uçta minimal `ChevronRight` oku eklendi.
- [x] **Mantıksal Kategorik Gruplandırma**:
  - 13 modül 4 ana operasyonel gruba ayrıldı:
    1. *FİNANS & MUHASEBE*: E-Fatura & Finans, Kasa Yönetimi, Cari Takip, Bordro.
    2. *OPERASYON & SAHA*: Araç Takip, KM Onayları, Evrak Takip, İhbar Yönetimi, Kaçak Kontrol.
    3. *PLANLAMA & İK*: Görev Takibi, Nöbet Onay, Puantaj.
    4. *SİSTEM & DESTEK*: Destek & Yardım.
  - `visibleOperationGroups` computed özelliği ile kullanıcının yetkisi olmayan hiçbir modül veya grup ekrana basılmaz (Kural 2).
- [x] **Kapat Butonu Standardizasyonu (`design-system.css`)**:
  - `nav-sheet` kapat butonu kalın mavi daire yerine sistemin nötr dairesel `clay-circle-btn` stiline kavuşturuldu.
- [x] **Doğrulama ve Derleme**:
  - Otomatik Playwright testi ile hiyerarşi, öğe sayısı, kategori başlıkları, kapat butonu ve odak döngüsü doğrulandı.
  - `npm run build` ile 0 hata derleme alındı (`dist` güncellendi).

## 2026-10-17 — Mobil SPA Giden Evrak: Zengin Resmî Editör (WYSIWYG), Tipografi, Punto, Araç Çubuğu & Şablon Kütüphanesi
- [x] **Tipografi ve Punto Seçimleri (`GidenEvrakView.vue`)**:
  - Resmî Yazışma Yönetmeliğine tam uyumlu *Times New Roman* ve *Arial* font anahtarı.
  - Punto seçimi için hap buton grubu: `10pt`, `11pt`, `12pt (Standart)`, `14pt (Başlık)`.
- [x] **Zengin Metin Biçimlendirme Araç Çubuğu (Formatting Toolbar)**:
  - Kalın (`B`), İtalik (`I`), Altı Çizili (`U`), Üstü Çizili (`S`).
  - Sola Yasla, Ortala, Sağa Yasla ve resmî yazılarda zorunlu olan **İki Yana Yasla (Justify)** hizalama butonları.
  - Madde işaretli ve numaralı listeler, girinti artırma/azaltma (`Indent` / `Outdent`).
  - Biçimlendirmeyi temizleme (`Eraser`), Geri Al / İleri Al (`Undo` / `Redo`).
  - Görsel editör ile HTML kaynak kodu arasında geçiş butonu (`Code`).
  - Dokunmatik ekranlarda imleç/seçim kaybını önlemek amacıyla tüm araçlarda `@mousedown.prevent` koruması.
- [x] **Hazır Resmî İfadeler (Hızlı Ekleme Çipleri)**:
  - İmleç konumuna tek dokunuşla eklenen standart nezaket ve bildirim kalıpları ("Bilgilerinize arz ederim.", "Gereğini rica ederim.", "İlgide kayıtlı müzekkerenize istinaden;", vb.).
- [x] **Hazır Resmî Şablonlar Kütüphanesi (`showTemplateModal`)**:
  - Sekme başlığında `[ BookOpen ] Şablonlar` butonu.
  - 5 standart resmî şablon: Maaş Haczi Kesintisi Başlatma, Borçlu İşten Ayrıldı Bildirimi, Öncelikli Haciz Var, Haciz Kaldırma Teyidi, Genel Bilgilendirme.
- [x] **WYSIWYG Editör & Reaktif Senkronizasyon**:
  - `contenteditable` container ile `form.aciklama` arasında çift yönlü senkronizasyon (`syncEditor()`).
  - İcra sihirbazı, AI taslakları ve şablon yüklemelerinde anında editör güncellemesi.
- [x] `npm run build` ile 0 hata derlendi (`dist` güncellendi).

## 2026-10-17 — Mobil SPA Cari Detay & İşlem Ekle Tasarım Standartlaştırması
- [x] **Alttaki Yüzen Butonun Kaldırılması (`CariDetailView.vue`)**:
  - Alt dock menüyü örten ve ekranı daraltan yüzen `<aside class="detail-floating-fab">` kaldırıldı.
  - Sayfa alt boşluğu sistem standardı `--ds-nav-clearance` seviyesine çekildi.
- [x] **Üst Çubuk & Kolay Erişim**:
  - Uzun cari unvanlarında butonların taşmasını önlemek için başlığa `min-width: 0` ve `text-overflow: ellipsis` uygulandı.
  - Üst başlık çubuğuna (Topbar) `+` (Yeni İşlem Ekle) butonu entegre edildi.
  - Hareketler başlığı yanındaki `+ İşlem Ekle` butonu korundu.
- [x] **Hero Kartı & Bento Özet**:
  - Telefon numarası `formatPhone` ile `0536 997 83 81` formatına kavuşturuldu.
  - 3'lü Soft Bento rozetleri (Top. Aldım, Top. Verdim, Net Bakiye) renkli durum noktalarıyla dengelendi.
  - Hızlı aksiyon butonları (Cari Notu, Ara, Paylaş, Ekstre PDF) pastel kil ikonlarıyla zenginleştirildi.
- [x] **"Yeni İşlem Ekle" Modalı (Bottom Sheet)**:
  - Belirgin geri bildirimli 2'li Segmented Toggle: Aldım (Kırmızı) ve Verdim (Yeşil).
  - Tutar alanı: Belirgin ₺ rozeti, `inputmode="decimal"`, hızlı tutar çipleri (`+500 ₺`, `+1.000 ₺`, `+5.000 ₺`, `Tam Bakiye`).
  - Açıklama alanı: Hızlı şablon çipleri (`Nakit`, `Havale/EFT`, `Fatura Ödemesi`, `Mal/Hizmet`).
  - Belge yükleme: Ham tarayıcı inputu yerine özel Soft Bento Dropzone ve seçilen dosya kartı (`Kaldır` butonlu).
- [x] **`CariView.vue` Eşitlemesi**:
  - Ana cari listesindeki işlem ekleme modalı da aynı modern bileşen ve dropzone ile güncellendi.
- [x] `npm run build` ile 0 hata derlendi.

## 2026-10-17 — Mobil SPA Personel Detayı: "Ek Ödemeler" ve "Kesintiler" Dağılım Kartı (Obsidian Minimal), Dönem Filtresi & Puantaj Kart Gruplaması
- [x] **Cari Takip Tarzı Minimal Obsidian Dağılım Kartı**:
  - `ek_odemeler` ve `kesintiler` sayfalarındaki özet kartları `CariView.vue` tarzı şık ve koyu obsidian (`#191d24`) bento kartı yapısına kavuşturuldu.
  - Kompakt dolgular (16px), 24px kalın toplam tutar, banka/elden döküm satırı, yüzdeli segmentli ilerleme çubuğu ve tıklanabilir filtre kutuları (Ek Ödemeler için 3'lü grid, Kesintiler için 2x2 grid) uygulandı.
- [x] **Dönem Bazlı Filtre Çubuğu (`.period-filter-strip`)**:
  - Hem Ek Ödemeler hem de Kesintiler sayfalarına açık/kapalı tüm bordro dönemlerini içeren native açılır liste ve tek tıkla temizleme butonu eklendi.
  - Tek seferlik ve taksitli hareketler döneme göre, sürekli hareketler ise dönem geçerlilik aralığı kesişimine göre dinamik olarak filtrelenir.
- [x] **Puantaj Primlerinin Dönem Bazlı Tek Kart Halinde Gruplanması**:
  - Aynı bordro dönemine ait olan ve `[Puantaj]` kaynaklı üretilen tüm prim hareketleri, alt alta ayrı kartlar yerine **o döneme ait tek bir "Puantaj Primleri" kartı** (`.financial-card--puantaj`) altında toplandı.
  - Kart üzerinde toplam tutar, kalem sayısı rozeti (`X Kalem`) ve dönem bilgisi sunuldu; "Kalem Dökümünü İncele" butonuyla açılan akordiyon çekmecesi ile alt kalemlerin detaylı dökümü listelendi.
  - `App/Model/MobilePersonelModel.php` içinde `is_puantaj` bayrağı eklendi.
- [x] **Vite Derleme & Doğrulama**:
  - `npm run build` ile 0 hata derlendi (`dist` başarıyla güncellendi).

## 2026-10-17 — Mobil SPA Personel Detayı: "Ek Ödemeler" ve "Kesintiler" Olarak Ayrılması & Tam Yönetimi
- [x] **Menü ve Modül Ayrımı (`PersonnelDetailView.vue`)**:
  - `finansal` modülü kaldırılarak yerine **Ek ödemeler** (`ek_odemeler` - Zümrüt Yeşili) ve **Kesintiler** (`kesintiler` - Mercan Kırmızısı) modülleri oluşturuldu.
  - Modül sayısı rozeti `9 Bölüm` olarak güncellendi.
- [x] **Model & API Katmanı (`MobilePersonelModel.php`, `personnel.php`, `personnel.js`)**:
  - `MobilePersonelModel`: `getEkOdemelerDetails`, `saveEkOdeme`, `deleteEkOdeme`, `sonlandirEkOdeme`, `getKesintilerDetails`, `saveKesinti`, `deleteKesinti`, `sonlandirKesinti` metotları uygulandı.
  - `public/api/personnel.php` & `dist/api/personnel.php`: `detail` verisine ek ödeme ve kesinti listeleri/özetleri/parametreleri eklendi; 6 adet korumalı POST aksiyonu tanımlandı.
  - `personnel.js`: İlgili tüm API fonksiyonları eklendi.
- [x] **Ek Ödemeler Arayüzü (`PersonnelSectionView.vue`)**:
  - 3'lü Soft Bento Özet Kartı: Toplam Ek Kazanç, Tek Seferlik, Düzenli (Aylık).
  - Sağa tam yaslı `+ Yeni Ek Ödeme` butonu, filtre hapları (Tümü, Tek Seferlik, Sürekli).
  - Claymorphic hareket kartları: Tutar, resmi tutar, hesaplama tipi, dönem/tarih, banka/elden rozetleri, açıklama.
  - Aksiyonlar: Düzenleme, Sürekli ödemeyi sonlandırma, Silme.
  - Yeni Ek Ödeme Ekle / Düzenle modal sheet'i ve Sonlandırma modal sheet'i.
- [x] **Kesintiler Arayüzü (`PersonnelSectionView.vue`)**:
  - 3'lü Soft Bento Özet Kartı: Toplam Kesinti, Tek Seferlik, Taksitli & Sürekli.
  - Sağa tam yaslı `+ Yeni Kesinti Ekle` butonu, filtre hapları (Tümü, Tek Seferlik, Taksitli, Sürekli).
  - Claymorphic hareket kartları: Tutar, taksit tutarı, icra bağlantısı, dönem/tarih, banka/elden rozetleri, açıklama.
  - Aksiyonlar: Düzenleme, Sürekli kesintiyi sonlandırma, Silme.
  - Yeni Kesinti Ekle / Düzenle modal sheet'i (İcra ve taksit entegrasyonlu) ve Sonlandırma modal sheet'i.
- [x] `npm run build` ile 0 hata ile derlendi.

## 2026-10-17 — Mobil SPA Personel Detayı: İzin ve Raporlar Sekmesi (`/personel/:id/izinler`)
- [x] **Yıllık İzin Bakiyesi Bento Kartı**:
  - `entitlement` entegrasyonu ile Toplam Hakediş, Kullanılan İzin ve Kalan İzin günleri 3'lü Bento kutu ızgarasında sunuldu.
  - Sağa yaslı `Hakediş Detayı` butonu ile hizmet yıllarına göre hakediş günleri ve tarihlerini listeleyen modal eklendi.
- [x] **İzin ve Rapor Hareketleri Listesi**:
  - Başlıkta sağa tam yaslı `+ Yeni İzin Ekle` butonu.
  - İzin kartları: İzin türü, Ücretli/Ücretsiz etiketi, Onay durumu rozeti (`Onaylandı`, `Beklemede`, `Reddedildi`), tarih aralığı, gün sayısı, yıllık izne etki rozeti, açıklama ve onaylayan yetkili bilgisi.
  - Hızlı Aksiyonlar: Bekleyen izinler için tek tıkla Onaylama ve Ret gerekçesi girme; Düzenleme ve Silme butonları.
- [x] **Yeni İzin Ekle & Düzenle Modalı (`clay-modal-sheet`)**:
  - İzin türü seçimi, başlangıç/bitiş tarihleri (değiştikçe gün sayısı otomatik hesaplanır), süre, yıllık izne etki, onay durumu ve açıklama alanları.
- [x] **Model & API Katmanı**:
  - `MobilePersonelModel`: `getLeaveDetails`, `getLeaveTypes`, `saveLeave`, `deleteLeave`, `approveOrRejectLeave` metotları; `PersonelIzinleriModel` ve `SystemLogModel` entegrasyonu.
  - `public/api/personnel.php` & `dist/api/personnel.php`: `detail` (leaves verisiyle), `save_leave`, `delete_leave`, `approve_or_reject_leave` uç noktaları.
  - `personnel.js`: API çağrıları eklendi.
- [x] `npm run build` ile hatasız üretim derlemesi alındı, PHP CLI testleri başarıyla geçti.


## 2026-10-17 — Mobil SPA Evrak Takip & Ayrı Sekmeli Giden Evrak Sayfası (`/giden-evrak`)
- [x] **Ayrı Giden Evrak Sayfası Tasarım Bütünlüğü (`/giden-evrak` & `GidenEvrakView.vue`)**:
  - `EvrakView.vue` liste sayfası ile görsel kopukluk oluşturan izole kutulu yapı ve eksik alt gezinme (`chrome: false`) giderildi.
  - Sayfa, uygulamanın ana kabuğuna (`app-stage`, `app-shell`) ve standart alt navigasyon çubuğuna (`BottomNav`) bağlandı.
  - Standart `<TopBar>` bileşeni (`eyebrow="RESMÎ YAZIŞMA & E-İMZA"`, dairesel geri butonu, başlık, alt başlık ve sağ üst köşede PDF Önizleme + Hızlı Kaydet) uygulandı.
  - Üst kısma `EvrakView` üzerindeki 4'lü Bento KPI kartlarıyla aynı estetiğe (28px kavis, pastel renkli ikon kareleri) sahip 2'li Sihirbaz Kartı (İcra Sihirbazı & AI Asistanı) yerleştirildi.
  - Sekmeler doğrudan slate canvas üzerinde yüzen `.clay-tabs-wrap` ve `.clay-filter-pill` yapısına (`Evrak Bilgileri`, `Resmî İçerik`, `Ekler & E-İmza`) geçirildi.
  - Form kutuları saf beyaz zeminli, ince çerçeveli (`.evrak-input`, `.evrak-select`, `.evrak-textarea`) standart form elemanlarına dönüştürüldü; Hızlı Konu Çipleri (`.evrak-preset-chips`) eklendi.
  - Kart içlerine akıcı adım ilerleme butonları eklendi.
  - `npm run build` ile üretim paketi 0 hata ile derlendi.

## 2026-10-17 — Mobil SPA Kaçak Kontrol: Varsayılan 30 Gün Tarih Aralığı & Yatay Sekme Kaydırma (Wheel + Drag)
- [x] **Varsayılan Tarih Aralığı (Son 30 Gün)**:
  - `KacakView.vue` ve `services/kacak.js` içinde varsayılan tarih aralığı `today - 30 days` olarak ayarlandı (`datePreset = '30days'`).
  - Sayfa ilk açılışta gereksiz tüm geçmişi yüklemek yerine doğrudan son 30 günün kayıtlarını getirerek anında hızlı açılacak şekilde optimize edildi.
- [x] **Yatay Sekme Kaydırma (Mouse Wheel & Drag-to-Scroll)**:
  - `.status-pills-scroll` ve `.date-preset-group` konteynerlerine mouse tekerleğiyle yatay kaydırma (`@wheel.passive="handleWheelScroll"`) ve masaüstünde fareyle tutup çekerek kaydırma (`@mousedown="startDragScroll"`) yetenekleri eklendi.
  - `flex-shrink: 0 !important; white-space: nowrap !important;` ve `touch-action: pan-x; cursor: grab;` kurallarıyla sekmelerin mobilde parmakla, masaüstünde fareyle takılmadan kayması sağlandı.
- [x] `npm run build` ile 0 hata ile derlendi.

## 2026-10-17 — Mobil SPA Personel Detayı: Doğrudan Düzenleme Modu, Üst Kartta Tek Kaydet Butonu ve Sağa Yaslı Aksiyon Butonları
- [x] **Doğrudan Düzenleme Modu (Düzenle/Vazgeç Butonlarının Kaldırılması)**:
  - `PersonnelSectionView.vue`: "Genel Bilgiler" ve "Çalışma Bilgileri" sekmelerinde ayrı görüntüleme modu ve `[ ✎ Düzenle ]` / `[ ✕ Vazgeç ]` butonları tamamen kaldırıldı. Sayfaya girildiğinde formlar doğrudan düzenlenebilir olarak açık gelir.
  - Çalışma Yaşam Döngüsü kartı üstte durum rozetleri (Aktif Çalışan, Kıdem, SGK) ile birlikte doğrudan düzenlenebilir `.form-grid` / `.form-field` bento kartı olarak yapılandırıldı.
- [x] **Üst Profil Kartında Tekil Sağ `[ 💾 Kaydet ]` Butonu**:
  - Profil kartının (`.personnel-profile-head`) sağ tarafına `.profile-save-btn` yerleştirildi.
  - Aktif sekmenin durumuna göre (`genel` veya `calisma`) tek tıkla kaydeder; sayfa içi gereksiz barlar kaldırıldı.
- [x] **Sağa Yaslı Başlık Butonları**:
  - `.group-header` flex yapısı `justify-content: space-between; width: 100%;` ve `.header-action-btn { margin-left: auto !important; }` yapılarak "Ekip Atama Geçmişi" (`+ Yeni Ekip`) ve "Çalışma Dönemleri Geçmişi" (`+ Dönem Ekle`) butonları kart başlığının en sağına tam yaslandı.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA: Sistem Geneli ve Personel Detayı Kartlar Arası Boşluk Standardı (16px)
- [x] **`PersonnelSectionView.vue` Kartlar Arası Boşluk Düzeltmesi**:
  - Görüntüleme modundaki `.quick-contact-bar` ve `.info-group-card` kartlarının `14px` olan marjinleri `var(--ds-card-gap, 16px) !important;` olarak güncellendi.
  - `.genel-view-stack` ve `.calisma-view-stack` flex container'ları `gap: var(--ds-card-gap, 16px)` ile yapılandırıldı; içindeki kartların `margin-bottom: 0 !important;` alması sağlanarak çift boşluk engellendi.
  - Düzenleme modundaki `.genel-edit-form` ve `.calisma-edit-form` formlarında `gap: 16px` uygulandı; içindeki `.form-bento-section` ve `.edit-sticky-top-bar` elemanlarının `margin-bottom` değeri sıfırlanarak 30px'lik aşırı açılma kesin olarak giderildi.
- [x] **`PersonnelDetailView.vue` Aralık Düzeltmesi**:
  - `.detail-modules-section` üzerindeki `margin-top: 20px;` kaldırılarak hero bento kartı ile modül listesi arasındaki mesafe tam 16px standardına getirildi.
- [x] **`design-system.css` Evrensel Kural**:
  - `[class*="-edit-form"] > .bento-card, [class*="-view-stack"] > .bento-card, .home-sections-stack > .bento-card, .genel-edit-form > .bento-card, .calisma-edit-form > .bento-card { margin-bottom: 0 !important; }` kuralı eklenerek tüm sayfalarda flex gap ve bento margin çakışması önlendi.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Bordro: Raporlar Formatında Dönem Seçimi, Gömülü Kanal Hero Kartı ve Filtre Sekmelerinin Kaldırılması
- [x] **Raporlar Standardında Dönem Seçim Kartı & Bottom Sheet (`PayrollView.vue`)**:
  - Hantal HTML select kutusu kaldırıldı; Raporlar sayfası ile 1:1 uyumlu `.date-range-badge` bento kartı eklendi (takvim ikonu, dönem adı, tarih aralığı, kilit rozeti ve `Değiştir ⌵` butonu).
  - Karta tıklandığında açılan dokunmatik ve modern **Dönem Seçici Bottom Sheet (`payroll-modal-sheet--period`)** geliştirildi; dönem listesi, tarih aralıkları ve aktif seçim doğrulaması sağlandı.
- [x] **Hero Kartı İçi Kompakt Kanal Dağıtımı**:
  - Alttaki 3 ayrı beyaz kart (`BANKA`, `ELDEN`, `SODEXO`) kaldırılarak doğrudan `.payroll-kpi-hero` siyah kartının içine 3'lü koyu cam mikro kartlar olarak yerleştirildi.
  - Kart iç düzeni ve padding'leri sıkılaştırılarak kartın büyümesi engellendi; toplam kart yüksekliği **~132px** ile önceki tasarıma kıyasla dikeyde ~150px yer tasarrufu sağlandı.
- [x] **Filtre Sekmelerinin Kaldırılması**:
  - Kullanıcının talebi doğrultusunda `.clay-tabs-wrap.payroll-tabs` ('Tümü', 'Hesaplanan', 'Bekleyen'...) kaldırıldı; arama alanı tekil, sade ve ferah biçimde konumlandırıldı.
- [x] `design-smoke.cjs` 27 viewport kontrolünden `PASS` aldı, `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Personel Detayı: Sticky Barın Scroll Esnasında Sabit Kalması (Floating Sticky Düzeltmesi)
- [x] **`.app-shell` Overflow Düzeltmesi**: `overflow: hidden` kaldırılarak `overflow-x: clip; overflow-y: visible;` uygulandı ve W3C standardında sticky elemanların window scroll ile kilitlenmesi sağlandı.
- [x] **Garantili Floating Sentinel & Scroll Mekanizması (`PersonnelSectionView.vue`)**:
  - `stickySentinel` ve `window` scroll dinleyicisi ile sayfa kaydırıldığı anda `.edit-sticky-top-bar` otomatik olarak `.is-floating` moduna geçerek ekranın üst kenarında (`position: fixed; top: 10px; width: calc(100% - 32px); max-width: 448px; left: 50%; transform: translateX(-50%); z-index: 100;`) sabitlenir.
  - Form ne kadar aşağı kaydırılırsa kaydırılsın bar **kesinlikle yukarı kaçmaz**, ekranda sabit kalarak her an tek dokunuşla kaydetme/vazgeçme imkanı sunar.
  - Floating moddayken form içeriğinin sıçramaması için `sticky-bar-placeholder` boşluk koruyucu eklendi.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Personel Detayı: Düzenleme Input Tasarımlarının Görüntüleme Kartı Standardına Uyarlanması
- [x] **Hücre İçi Bento Kapsayıcı Tasarımı (`.form-field`)**:
  - Gönderilen referans görsel doğrultusunda form alanları dışta etiketli klasik input formundan çıkarıldı; her alan tek parça açık gri (`#f1f4f9`), 14px kavisli ve iç boşluklu bir bento hücresi haline getirildi.
  - Hücre içi üstte küçük, gri ve büyük harfli etiket; altta kalın, siyah ve kenarlıksız/şeffaf `input`, `select` ve `textarea` bileşenleri yerleştirildi.
  - `:focus-within` ile hücreye tıklandığında yumuşak beyaz zemin ve macOS mavisi odak çizgisi/yayılması eklendi.
- [x] **Başlıkların Eşitlenmesi**: Düzenleme formlarındaki küçük `01`, `02` rozetleri kaldırılarak görüntüleme modundaki gibi ikon kutulu, h3 başlıklı ve alt açıklamalı `.group-header` standartlaştırıldı.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Personel Detayı: Minimal Hero Kartı ve Çalışma Bilgileri Sekmesi
- [x] **Hero Profil Kartının Minimalize Edilmesi (`PersonnelDetailView.vue`)**:
  - Dikey yüksekliği fazla olan grid kutuları kaldırıldı; tek parça, dikeyde yalnızca ~30px alan kaplayan `.hero-mini-meta-card` ve `.meta-row` satır formatına geçildi.
  - Avatar boyutu 60x60px'den 50x50px'e dengelendi; Departman, Ekip ve İşe Giriş Tarihi tek bir satırda ikonlu ve sıkılaştırılmış biçimde yerleştirilerek dikeyde ~120px net alan kazanıldı.
- [x] **Çalışma Bilgileri Sekmesinin Masaüstü Standartlarında Yeniden İnşası (`PersonnelSectionView.vue`)**:
  - **Çalışma Yaşam Döngüsü Kartı**: Aktif/Ayrılmış durum rozeti, dinamik kıdem hesaplama (`calculateTenure`), işe giriş-çıkış tarihleri, SGK firması, sınıfı, departman, görev, saha takibi ve araç kullanım rozetleri.
  - **Çalışma Bilgilerini Düzenleme Formu**: Masaüstündeki tüm alanları içeren, `Gate::allows('personel_duzenle')` yetki kontrollü tam düzenleme yeteneği.
  - **Ekip Atama Geçmişi**: Personelin görev aldığı ekipler, ekip şefliği rozeti, tarih aralıkları; "Yeni Ekibe Ata" modalı, düzenleme ve çakışma kontrollü silme desteği.
  - **Çalışma Dönemleri Geçmişi**: Personelin geçmiş çalışma dönemleri; yeni dönem ekleme ve silme modalları.
- [x] **Model & API Altyapısı (`MobilePersonelModel.php`, `personnel.php`, `personnel.js`)**:
  - `getWorkDetails`, `getWorkOptions`, `saveWorkInfo`, `saveEkipGecmisi`, `deleteEkipGecmisi`, `saveCalismaGecmisi`, `deleteCalismaGecmisi` metotları, CSRF ve yetki korumalı POST uç noktalarıyla bağlandı.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Personel Detayı ve Genel Bilgiler Sekmesi
- [x] **Hero Bento Kartı Entegrasyonu**:
  - `PersonnelDetailView.vue`: Kullanıcının ilettiği görsel doğrultusunda, altta ayrı duran "Departman, Ekip/Bölge ve İşe Giriş Tarihi" kartı kaldırılarak doğrudan üstteki Hero Bento profil kartının içine (`.hero-facts-grid` & `.hero-facts-divider`) entegre edildi.
- [x] **Genel Bilgiler Sekmesinin Masaüstü Standartlarında Yenilenmesi**:
  - `PersonnelSectionView.vue`: Genel bilgiler sekmesi masaüstü modülündeki tüm kabiliyetlerle yerel SPA olarak baştan inşa edildi:
    - *Görüntüleme Modu*: 5 ayrı Bento kartı (Kimlik Bilgileri, İletişim & Adres, Kişisel & Aile Detayları, Fiziksel & Acil Durum, Program & APK Giriş Bilgileri), tek tıkla TC ve telefon kopyalama, Google Haritalarda adresi açma, hızlı WhatsApp ve Arama kısayolları, KASKİ şifresi gizle/göster.
    - *Düzenleme Modu*: Yetkili kullanıcılar için tam düzenleme formu, yerel OCR ile belge/fotoğraftan veri doldurma yeteneği, program şifresi güncelleme, KASKİ kullanıcı adı/şifresi güncelleme, sticky kaydet/iptal çubuğu, anında Toast bildirimi.
- [x] **Backend Model ve API Altyapısı**:
  - `MobilePersonelModel.php`: `mobileDirectoryDetail` ile tüm alanlar çekildi, şifreli alanlar `Security::decrypt()` ile çözümlendi; `saveMobileGeneralInfo` ile masaüstü parametrik binding, güvenlik doğrulamaları ve `SystemLogModel` loglama desteği sağlandı.
  - `public/api/personnel.php` & `dist/api/personnel.php`: `personnelPublicRow` genel bilgiler nesnesiyle zenginleştirildi; `action === 'save_general'` POST uç noktası eklendi.
  - `services/personnel.js`: `saveGeneral` metodu eklendi.
- [x] `npm run build` ile hatasız üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA Profil Sayfası: Çıkış Butonunun Ayarlar Listesine Entegre Edilmesi
- [x] Profil sayfasında ayrı bir alt buton olarak duran "Güvenli Çıkış Yap" butonu kaldırılarak doğrudan ayarlar bento kartının son liste elemanı haline getirildi:
  - `ProfileView.vue`: Harici `.logout-btn` kaldırıldı; `settings-bento-card` içerisine son satır olarak `settings-row--danger` sınıfında ikonlu, kırmızı vurgulu ve sağ oklu "Güvenli Çıkış Yap" satırı eklendi.
  - `npm run build` ile hatasız üretim derlemesi sağlandı.

## 2026-10-17 — Mobil SPA Ana Sayfa: Masaüstü Standardında Özelleştirilebilir, Sürükle-Bırak Sıralanabilir ve Kapsamlı Bento Kartları
- [x] Kullanıcının talepleri doğrultusunda klasik `mobile` (`mobile/pages/home.php`) ve masaüstü `views/home` standartları birleştirilerek Mobil SPA ana sayfası tamamen yenilendi:
  - **Sürükle-Bırak Sıralama**: HTML5 Drag & Drop ve mobil dokunmatik tutamağı (`.home-drag-handle`, `GripVertical`) ile kartların sırası dinamik olarak değiştirilebilir hale getirildi.
  - **Kalıcı Kullanıcı Ayarları**: Kart sırası ve görünürlük tercihleri (`order`, `settings`) `SettingsModel` (`mobile_dashboard_order`, `mobile_dashboard_settings`) ve yerel `localStorage` ile kullanıcı bazında kalıcı olarak saklandı. Sayfa yenilendiğinde kullanıcıya özel sıralama aynen korunur.
  - **Kart Kapatıp/Açma (Görünürlük)**: Her kartta hızlı "Gizle" (`EyeOff`) butonu, altta "Gizlenen Kartlar" hızlı geri açma çubuğu ve kapsamlı "Ana Sayfayı Özelleştir" bottom sheet modalı eklendi. Modal içinde aç/kapa switch'leri, yukarı/aşağı butonları ve "Varsayılan Düzene Sıfırla" butonu sunuldu.
  - **Klasik Mobil Verilerinin Tamamı & Bento Tasarımı**:
    - *Kurumsal Duyurular*: Gradient bento kartı + tıklandığında açılan tam metin ve görsel bottom sheet modalı.
    - *Bekleyen Onaylar*: Amber uyarı bento kartı (talep, avans ve izin kırılımı, `/talepler` bağlantısı).
    - *Bugünün Nöbetçileri*: Avatar/resim, ad soyad, görev saatleri, tek dokunuşla arama butonu (`tel:`) ve `/nobet-onay` takvim bağlantısı.
    - *Personel Durumu*: Koyu bento kartı + 3 segmentli renkli bar (Sahada, İzinli ve Geç Kalan personeller).
    - *Araç & Filo*: Sahada, serviste, boşta dağılımı ve filo bağlantısı.
    - *Günün Operasyonları*: Günlük / Aylık toggle geçişi, son güncelleme zamanı ve operasyon detayları.
    - *Hızlı Erişim*: Yetkili modüllere doğrudan tile kısayolları.
  - **Backend Model & API (`MobileDashboardModel.php`, `dashboard.php`, `dashboard.js`)**: `shifts()`, `pendingApprovals()`, `late` personeller, operasyonel son güncelleme saatleri ve `save_card_settings` POST aksiyonu eklendi.
  - **Derleme**: `npm run build` ile 0 hata ile üretim derlemesi alındı.

## 2026-10-17 — Mobil SPA 'Daha Fazla' Menü Footer: Profil Menü Butonu Entegrasyonu
- [x] Diğer ("Daha Fazla") menüsünün alt aksiyon çubuğuna (Güvenli Çıkış butonunun solundaki alana) Profil butonu eklendi:
  - `BottomNav.vue`: Sol tarafa `<UserRound :size="18" /> <span>Profil</span>` butonu eklendi, tıklandığında `/profil` rotasına yönlendirildi.
  - `styles.css` & `design-system.css`: `.nav-sheet-footer` 2 sütunlu grid (`grid-template-columns: 1fr 1fr;`) yapılarak Profil ve Güvenli Çıkış butonları tam genişlikte ve simetrik olarak yerleştirildi.
  - `npm run build` ile hatasız üretim derlemesi sağlandı.

## 2026-10-16 — Mobil SPA E-Fatura & Finans: Akıllı Tekil Arama & UI/UX Pro Max Taslak / Wizard Yenilemesi
- [x] E-fatura modülü tekil akıllı arama ve modern UI/UX Pro Max tasarım diliyle yenilendi:
  - **Tekil Akıllı Arama Çubuğu**: Çift input (fatura no ve ürün arama) kaldırıldı; Fatura No, Müşteri Ünvanı, VKN ve Ürün/Kalem satırlarını tek çubukta tarayan `.fatura-search-box--unified` ve tek dokunuşla sıfırlama butonu (`X`) getirildi.
  - **Modern Bento Boş Durum (`.fatura-empty-bento`)**: Çift halkalı claymorphic ikon, açıklayıcı başlık/açıklama, belirgin 46px primer CTA (`+ Yeni Taslak Fatura Oluştur`) ve güven rozetleri eklendi.
  - **Yeni Fatura Kes Sihirbazı (Wizard)**:
    - Gradient ilerleme çubuğu ve checkmark'lı 4 adımlı interaktif stepper.
    - Adım 1: `<Building2>` ikonu, baş harf avatarlı müşteri listesi ve input içi entegre `[Sorgula]` mikro butonuyla canlı GİB mükellef sorgulama.
    - Adım 2: Belge türü için 2'li Segmented Pill (`E-Fatura` / `E-Arşiv`), Fatura Tipi için 4'lü chip butonlar, Senaryo çipleri, seri ve para birimi grid'i.
    - Adım 3: `#1 Kalem` rozetli bento kartları, hızlı ürün seçici çipleri, 3 sütunlu miktar-birim-fiyat, matrah ve KDV dahil tutar özeti, kesikli yeni kalem butonu.
    - Adım 4: Termal/bento makbuz kartı, müşteri/VKN/tarih künyesi, finansal döküm, büyük genel toplam ve EDM Bilişim GİB güvenlik onay rozeti.
    - Dikey gri scrollbar gizlendi (`scrollbar-width: none`), akıcı mobil dokunmatik kaydırma sağlandı.
  - **Derleme**: `npm run build` ile 0 hata ile derlendi.

- [x] Klasik mobil (`mobile/pages/talepler.php`) talepler sayfasıyla tam parite ve gelişmiş onay özellikleri sağlandı:
  - **Avans Kısmi Onayı**: Kartlarda `[Kısmi Onay]` butonu, modalda "Aynı Tutar" / "Farklı Tutar" segmentli seçimi, personele otomatik onay notu oluşturma ("Avans talebiniz X TL olarak uygun görülmüştür.") ve dinamik buton etiketi.
  - **İzin Kısmi Onayı**: Kartlarda `[Kısmi Onay]` butonu, modalda "Aynı Tarih" / "Farklı Tarihler" segmentli seçimi, tarih değişiminde anlık canlı gün hesaplama rozeti ("Yeni Onaylanan Süre: X Gün"), personele otomatik onay notu oluşturma ve dinamik buton etiketi.
  - **Genel Taleplerde İşleme Al**: Kartlarda sağ üstteki gereksiz "İşlemde" rozeti kaldırıldı; işlev doğrudan sol altta "Çözüldü" butonunun yanına eklenen daha küçük/kompakt açık yeşil `[ ▶ İşleme Al ]` butonuna aktarıldı. Buton her zaman "İşleme Al" yazar, işlemdeyken aktif yeşil ikon/kenarlıkla durumunu korur ve tıklandığında beklemede/işlemde arasında çift yönlü geçiş sağlar.
  - **Detay Bottom Sheet Zenginleştirmesi**:
    - **Resmî İzin Formu (Word DOCX) İndirme**: İzin detayında doğrudan Word belgesini indiren `[Resmî İzin Formunu İndir (Word DOCX)]` butonu eklendi.
    - **Fotoğraf Büyütme**: Ekli fotoğraflara dokunulduğunda tam boy yeni sekmede açılma (`openPhoto`) ve "Büyütmek için dokunun" ipucu eklendi.
    - **Alt Hızlı Aksiyon Butonları**: Bekleyen veya işlemdeki kayıtlarda altta hızlı Reddet, Kısmi Onay, Onayla / İşleme Al, Çözüldü, Sil butonları eklendi.
  - **Backend API (`public/api/requests.php` & `dist/api/requests.php`)**: `IzinFormuService` entegrasyonu ile `action=izin-formu-docx-indir` GET uç noktası eklendi; silinmiş kayıtlarda not ve tarih alanları standardize edildi.
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-16 — Mobil SPA Raporlar (İşlem Özetleri & Saha Performansı) Modülü
- [x] Klasik mobil (`mobile/index.php?p=raporlar`) raporlar modülü, `@mobile-spa` mimarisine Soft Bento & Claymorphic UI ve tüm operasyonel özellikleriyle taşındı:
  - **Backend JSON API (`public/api/reports.php` & `dist/api/reports.php`)**:
    - Strict types, session ve firma izolasyonu, permission 61 (`puantaj/raporlar`), `puantaj_raporlama`, `puantaj_yonetim`, `puantaj/list` ve Superadmin yetkilendirmesi.
    - `action=cards`: 5 sekmeye (Okuma, Kesme, Sayaç Sökme/Takma, Mühürleme, Kaçak) ait tüm modelleri (`EndeksOkumaModel`, `PuantajModel`, `SayacDegisimService`, `TanimlamalarModel`, `PersonelModel`, `EkipHelper`) dinamik olarak işleyip bölge ve personel performanslarını JSON döner.
    - `action=detail`: Tıklanan personelin seçili dönemdeki tüm çalışma günlerini, gün adlarını, işlem türlerini ve toplamlarını döken icmal servisi.
  - **Frontend Servis Katmanı (`services/reports.js`)**:
    - Reaktif `reportsState`, hızlı tarih hesaplama (Bugün, Dün, Bu Hafta, Bu Ay, Geçen Ay, Özel) ve `reportsApi` istemci metotları.
  - **Modern Görünüm (`views/ReportsView.vue`)**:
    - **TopBar**: Geri butonu, `OPERASYONEL ANALİZ` eyebrow, `İşlem Özetleri` başlığı ve yenileme butonu.
    - **Hızlı Tarih Hapları & Tarih Aralığı Kartı**: Tek dokunuşla hazır dönem geçişi ve aktif aralık göstergesi.
    - **5 Rapor Sekmesi**: Endeks Okuma (`Gauge`), Kesme / Açma (`ZapOff`), Sayaç Sökme/Takma (`Wrench`), Mühürleme (`ShieldCheck`), Kaçak Kontrol (`ShieldAlert`).
    - **4 Bento KPI Kartı**: Toplam İşlem/Okuma, Aktif / Toplam Ekip Kadrosu, En Yüksek Bölge (% ve adet), Kişi Başı Ortalama.
    - **Bölgesel İş Dağılımı Bento Kartı**: Renkli segment çubukları, bölge adetleri, personelleri ve dokunulduğunda o bölgeye göre anında filtreleme.
    - **Canlı Arama ve Sıralama**: Personel/ekip/bölge araması, 4 farklı ölçüte göre sıralama (En Çok, En Az, İsim A-Z, Ekip No).
    - **Personel Performans Kartları**: 1., 2., 3. sıralamada Altın/Gümüş/Bronz kupa rozetleri (`Trophy`), avatar/baş harfler, isim, ekip rozeti (`EKİP-102`), bölge pini, büyük net sayaç ve iş türü alt etiketleri.
    - **Personel Günlük İcmal Bottom Sheet**: Dönem toplamı, çalışılan gün, günlük ortalama KPI bandı, iş türü dağılımı ve gün gün kronolojik döküm listesi.
    - **Gelişmiş Tarih Seçici Sheet**: 12 aylık buton matrisi ile tek dokunuşla geçmiş ayları seçebilme, yıl stepper'ı ve özel tarih aralığı formu.
  - **Derleme & Canlı Doğrulama**: `npm run build` ile 0 hata ile derlendi; `mobile.ersanelektrik.com/api/reports.php` gerçek Apache oturumu ile test edildi.

## 2026-10-16 — Mobil SPA Bildirim Altyapısı ve Web Push Entegrasyonu
- [x] Mobil SPA (`mobile-spa`) bünyesinde eksik olan bildirim merkezi ve Web Push altyapısı uçtan uca tamamlandı:
  - **Backend API (`public/api/notifications.php` & `dist/api/notifications.php`)**: `unread-count`, `list`, `vapid-key`, `push-status`, `subscribe`, `unsubscribe`, `mark-read`, `mark-all-read` aksiyonları `BildirimModel`, `PushSubscriptionModel` ve `SystemLogModel` üzerinden yazıldı.
  - **Service Worker (`sw.js`)**: `push` ve `notificationclick` dinleyicileri eklendi, SPA rotalarına akıllı yönlendirme sağlandı, cache sürümü `ersan-mobile-shell-v19` yapıldı.
  - **Frontend Servis Katmanı (`services/notifications.js`)**: Reaktif `notificationsState`, VAPID anahtarı yönetimi, push izin ve abonelik fonksiyonları, otomatik periyodik sayaç sorgulaması (polling) uygulandı.
  - **TopBar Zil İkonu (`TopBar.vue`)**: Sağ üst köşeye dinamik okunmamış sayısı rozetine (`topbar-bell-badge`) sahip `Bell` butonu eklendi.
  - **Bento Bildirim Çekmecesi (`NotificationsDrawer.vue`)**: Alttan açılan mobil bildirim merkezi, okunmuş/okunmamış filtreleme, tek tıkla okundu işaretleme ve hızlı push aktifleştirme banner'ı entegre edildi.
  - **Profil Sayfası (`ProfileView.vue`)**: "Bildirim tercihleri" satırı anlık durum rozetine kavuşturuldu, tıklandığında açılan ayar modalı ile push bildirimlerini açıp kapatma işlevi sağlandı.
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-16 — Mobil SPA Kaçak Kontrol — İhbar Yönetimi Tasarım Diline Tam Uyumlaştırma
- [x] Kaçak Kontrol sayfası İhbar Yönetimi sayfasının tasarım şablonu, bento hero kartı ve yerleşim yapısıyla birebir eşleştirildi:
  - **Obsidian Bento Hero Kartı**: Toplam tutanak, %Onay Oranı yeşil rozeti, 4 renkli segmentli progress barı ve 4'lü interaktif durum hücreleri (`Bekleyen`, `Onaylı`, `Reddedilen`, `İptal`).
  - **Dönem Gezinme**: "Son 1 Ay", "30 Gün", "Bu Yıl", "Tümü" hap butonları ve tarih göstergesi.
  - **Arama & Filtre Kartı**: `bento-card--white` içinde tam genişlik arama kutusu, durum hapları, tür hapları ve ilçe dropdown'ı.
  - **Tutanak Kartları**: `ihbar-card` stiline tam geçirildi (Sol üstte kırmızı ikonu, tutanak no ve ilçe, sağda durum rozeti, abone ve açıklama özeti, etiketler: Tür, Sayaç No, Endeks, Fotoğraf sayısı, ZIP İndir butonu, ekip ve tarih bilgisi).
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-16 — Mobil SPA Puantaj — Personel Kartı İçi Haftalık Kapsül Takvimi ve Ay Görünümü Entegrasyonu
- [x] Kullanıcının yüklediği görseldeki "Weekly Schedule" haftalık kapsül takvimi her personel kartına entegre edildi:
  - **Haftalık 7 Sütunlu Kapsüller (`.weekly-day-capsule`)**: Pazartesi'den Pazar'a haftanın günleri dikey hap formatında, gün numarası ve yuvarlak durum rozeti ile listelendi.
  - **Bugün ve Pazar Vurgusu**: Bugün için 2px mavi border ve mavi metin; Pazar günü için turuncu/kırmızı tema.
  - **Doğrudan Dokunarak Değiştirme**: Herhangi bir gün kapsülüne dokunulduğunda o personelin o günkü puantajı döngüsel olarak değişir ve yüzen kaydetme barı açılır.
  - **Ayrı Ay Görünümü**: Haftalık kutunun sağ üstündeki `[Calendar] Ekim 2026` butonuna veya personel kartına tıklandığında tüm ayı içeren detay bottom sheet'i açılır.
  - **Derleme**: Vite üretimi (`npm run build`) başarıyla tamamlandı.

## 2026-10-16 — Mobil SPA Nöbet Onay & Planlama Modülü (Soft Bento & Claymorphic UI, Mini Takvim, Onay Listesi, Dağılım Havuzu & Planlama Sheet'i)
- [x] Mobil SPA (`mobile-spa`) bünyesinde nöbet onay, planlama ve dağıtım yönetim sayfası sıfırdan geliştirildi:
  - **Backend API (`public/api/nobet.php` & `dist/api/nobet.php`)**: Gate yetkilendirmesi (`nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list`, `Gate::isSuperAdmin`), CSRF doğrulama (`verifyCsrfToken`), firma ID ve oturum güvenliği; `list`, `calendar`, `personnel_summary`, `personnel_list`, `approve`, `unapprove`, `delete`, `bulk_approve`, `bulk_unapprove`, `bulk_delete`, `add`, `send_reminder` uç noktaları eksiksiz yazıldı.
  - **Frontend Servisi (`services/nobet.js`)**: Reaktif `nobetState` ve `nobetApi` metotları oluşturuldu.
  - **Mobil Görünüm (`views/NobetOnayView.vue`)**:
    - **TopBar**: Sayfa başlığı, seçili yıl bilgisi ve alt açıklama.
    - **Ay ve Yıl Gezgini**: Önceki/Sonraki ay butonları, seçili cari dönem göstergesi ve "+ Nöbet Planla" aksiyon butonu.
    - **4 Minimal Bento KPI Kartı**: Bekleyenler, Hafta Sonu, Resmi Tatil, Hafta İçi. Tıklandığında doğrudan liste filtrelenir.
    - **3 Segmentli Tab Gezinmesi**:
      1. *Onay Listesi*: Canlı arama kutusu, durum filtre hapları (Bekleyenler, Onaylananlar, Tümü), çoklu seçim modu (Tümünü Seç, Toplu Onayla, Toplu Sil), detaylı nöbet kartları (avatar, ad-soyad, departman, tarih, gün adı, saatler, tip rozeti, onay durumu rozeti, not kutusu, Onayla/Onayı Kaldır, Hatırlatma Gönder, Sil butonları).
      2. *Takvim & Planlama*: Mobil 7 sütunlu aylık mini takvim, gün bazında nöbetçi noktaları, güne tıklandığında altta açılan o günün nöbetçi listesi ve "+ Bu Güne Nöbet Ekle" kısayolu.
      3. *Havuz & Dağılım*: Personellerin o ayki nöbet dağılımı ve doluluk çubukları, arama filtresi, karta tıklandığında o personele doğrudan nöbet planlama modalı açılması.
    - **Nöbet Planlama Bottom Sheet (Modal)**: Yukarı kayan mobil modal, arama özellikli personel listesi, tarih seçici, nöbet tipi butonları, hazır saat butonları (18:00 - 08:00, 08:00 - 18:00, 24 saat), açıklama alanı, doğrudan onaylı kaydetme toggle'ı.
    - **Güvenli Eylem ve Onay Modalı**: Silme ve toplu işlemler için onay diyalogu.
    - **Canlı Toast Bildirimleri**: Başarılı/hata durum geri bildirimleri.
  - **SPA Rota ve Menü Entegrasyonu**:
    - `router.js`: `/nobet-onay`, `/nobet`, `/nobetler` rotaları eklendi (`permission: 'shifts'`).
    - `BottomNav.vue`: "Daha Fazla" menüsündeki Nöbet Onay bağlantısı doğrudan SPA rotasına bağlandı (`to: '/nobet-onay'`).
    - `HomeView.vue`: Hızlı erişim butonlarına Nöbetler eklendi.
    - `public/api/auth.php`: `shifts` izni `nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list` anahtarlarıyla güçlendirildi.
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.
- [x] Mobil SPA (`mobile-spa`) bünyesinde modern, ergonomik ve zengin Kaçak Kontrol yönetim sayfası sıfırdan geliştirildi:
  - **Backend API (`public/api/kacak.php` & `dist/api/kacak.php`)**: Gate yetkilendirmesi (`kacak_islemleri`, `kacak/list`, `kacak_onay`, `kacak_iptal_ekle`, `kacak_iptal`), CSRF doğrulaması (`verifyCsrfToken`), firma ID ve oturum güvenliği; `list`, `detail`, `photos`, `approve`, `reject`, `cancel`, `revert_cancel` aksiyonları yazıldı.
  - **Frontend Servisi (`services/kacak.js`)**: Reaktif `kacakState` ve `kacakApi` metotları oluşturuldu.
  - **Mobil Görünüm (`views/KacakView.vue`)**:
    - **TopBar**: Sayfa başlığı, Kupa ikonu (`Trophy`) ile Ekip Başarı Sıralaması (Leaderboard) açılışı ve yenileme butonu.
    - **Bento KPI Kartları**: 4 interaktif metrik kartı (Toplam, Bekleyen, Onaylanan, İptal). Dokunulduğunda ilgili durum filtresini doğrudan süzme özelliği.
    - **Hızlı Tarih Barı**: "Son 30 Gün", "Bu Yıl", "Son 1 Yıl", "Tümü" ve açılır özel tarih seçici paneli.
    - **Canlı Arama ve Çift Katmanlı Filtre**: Tutanak no, abone adı, sayaç no, ekip ve ilçe canlı araması; Clay sekmeleri (Tümü, Bekleyen, Onaylı, Reddedilen, İptal) ve Tür çipleri (Kaçak, Abonesiz, Usülsüz, Pasif Abone).
    - **Tutanak Kartları**: Tür rozetleri, tarih, onay/işlem durumu, Tutanak No ve Abone adı, İlçe ve Ekip bilgileri, Sayaç No, Fotoğraf Sayısı ve kart üstünde doğrudan ZIP İndirme kısayolu.
    - **Ekip Başarı Sıralaması (Leaderboard) Drawer**: 1., 2., 3. ekiplere özel madalya rozetleri, ekip isimleri, tutanak sayıları ve renkli ilerleme çubukları.
    - **Detay Bottom Sheet**: 2 sütunlu Bento bilgi ızgarası, Varsa Red Nedeni / İptal Açıklaması kutuları, "Fotoğrafları İncele" ve "ZIP İndir" butonları, Yetki bazlı "Onayla", "Reddet", "İptal Et", "İptali Geri Al" aksiyonları.
    - **Fotoğraf Galerisi & Lightbox**: Tutanak ve Saha fotoğrafları, PDF dokümanları desteği, Tam ekran dokunmatik / butonlu Lightbox ve fotoğraf indirme özelliği.
    - **Aksiyon Onay Modalı**: Red gerekçesi ve İptal açıklaması + Hakedişten düş kutucuğu içeren güvenli eylem modalı.
  - **SPA Rota ve Menü Entegrasyonu**:
    - `router.js`: `/kacak-kontrol` ve `/kacak` rotaları eklendi (`permission: 'illegal'`).
    - `BottomNav.vue`: "Daha Fazla" menüsündeki Kaçak Kontrol bağlantısı doğrudan SPA rotasına (`to: '/kacak-kontrol'`) bağlandı.
    - `HomeView.vue`: Hızlı erişim butonlarına Kaçak Kontrol eklendi.
    - `public/api/auth.php`: `illegal_approve`, `illegal_cancel`, `illegal_revert_cancel` izinleri oturum haritasına eklendi.
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-16 — Mobil SPA İhbar Yönetimi Modülü (Soft Bento & Claymorphic UI, Galeri & Lightbox, Ekip Atama, Performans Liderlik & Yeni İhbar)
- [x] Mobil SPA (`mobile-spa`) bünyesinde modern, ergonomik ve zengin İhbar Yönetimi sayfası sıfırdan geliştirildi:
  - **Backend API (`public/api/ihbar.php` & `dist/api/ihbar.php`)**: Gate yetkilendirmesi (`ihbar/list`, `ihbar_duzenle`), CSRF doğrulama, JSON/POST veri ayrıştırma, `list`, `detail`, `foto`, `stats`, `assign`, `addNote`, `close`, `cancelResult`, `create` uç noktaları eksiksiz yazıldı.
  - **Frontend Servisi (`services/ihbar.js`)**: Reaktif `ihbarState` ve `ihbarApi` metotları oluşturuldu.
  - **Mobil Görünüm (`views/IhbarView.vue`)**:
    - **TopBar**: Dinamik başlık ve alt açıklama, Liderlik/Performans butonu (`Trophy`), Yeni İhbar butonu (`Plus`), anlık yenileme.
    - **Obsidian Bento Hero Kartı**: Toplam ihbar sayısı, % olumlu başarı oranı, 4 renkli segmentli dağılım çubuğu, 4 adet interaktif durum hücresi (`Yeni`, `Devam`, `Olumlu`, `Olumsuz`) ile tek dokunuşla filtreleme.
    - **Dönem Gezinme ve Filtreler**: "Son 1 Ay", "30 Gün", "Tümü" hızlı hapları, başlangıç/bitiş tarih aralığı, canlı arama çubuğu (ilçe, mahalle, açıklama, personel, tutanak), yatay kaydırılabilir durum hap filtreleri (`Yeni`, `Yönlendirildi`, `İşlemde`, `Olumlu`, `Olumsuz`) ve Kahramanmaraş ilçe seçici dropdown'u.
    - **İhbar Bento Kartları**: Durum rozeti (renkli pill), İlçe ve Mahalle başlığı (lokasyon pin ikonu), 2 satırlık açıklama önizlemesi, Fotoğraf sayısı/GPS/Tutanak etiketleri, Bildiren personel, Atanan ekip rozeti ve oluşturulma tarihi.
    - **İhbar Detay Bottom Sheet (Drawer)**: Durum rozeti, bildiren personel, telefon (tıkla-ara `tel:` bağlantısı), komşu abone no, atanan ekip üyeleri, açık adres ve Google Haritalar'da aç butonu, küçük resimli fotoğraf galerisi, tam ekran lightbox (önceki/sonraki kontrolleri), işlem adımları zaman çizelgesi (timeline).
    - **Yetkili Aksiyonlar**: Kaçak Ekibine Yönlendir modalı (arama destekli personel seçimi, en fazla 2 kişi), İşlem Notu Ekle modalı, İhbarı Sonuçlandır modalı (Olumlu/Olumsuz toggle, tutanak no, gerekçe), Sonucu Geri Al / İptal Et butonu.
    - **Personel Performansı (Liderlik) Sheet**: 4'lü üst KPI (Bildirim yapan personel sayısı, En çok gönderen personel, En yüksek olumlu oranı, Saha ihbar toplamı), personel bazlı başarı oranı çubukları, "İhbarları Gör" ile tek dokunuşla o personelin ihbarlarını süzme, Excel'e (XLS) indirme.
    - **Yeni İhbar Ekleme Sheet**: Cihaz GPS konumunu tek tıkla alma (`navigator.geolocation`), fotoğraf çekme/galeriden çoklu ekleme (önizleme ve silme destekli), ilçe/mahalle/açıklama/telefon/abone alanları.
  - **SPA Rota ve Menü Entegrasyonu**:
    - `router.js`: `/ihbar`, `/ihbar-yonetimi`, `/ihbarlar` rotaları eklendi (`permission: 'notices'`).
    - `BottomNav.vue`: "Daha Fazla" menüsündeki legacy PHP linki doğrudan Vue rotasına (`to: '/ihbar'`) bağlandı.
    - `HomeView.vue`: Hızlı erişim aksiyonlarına `notices` yetkisi varsa İhbarlar butonu eklendi.
  - **Derleme**: Vite üretimi (`npm run build`) 0 hata ile tamamlandı.

## 2026-10-16 — Mobil SPA Puantaj — Arama/Filtre Hizalaması, Tab Sadeleştirmesi ve Takvim Bottom Sheet İyileştirmesi
- [x] Kullanıcının talepleri doğrultusunda Puantaj sayfası sadeleştirildi ve takvim modalı iyileştirildi:
  - **Sekmeler Kaldırıldı ve Bento KPI'lara Bağlandı**: "Tüm Personel", "İzinliler", "Raporlular" buton sırası kaldırılarak sayfa alanı ferahlatıldı. Üstteki 2x2 Bento KPI kartları üzerinden filtreleme aynen korunuyor.
  - **Arama Kutusu ve Filtre Butonu Aynı Satıra Alındı**: Arama kutusu (`flex: 1`) ve sağında 44x44px yuvarlak clay filtre butonu (`.puantaj-filter-btn`) tek satırda (`.puantaj-search-filter-row`) yan yana getirildi. Aktif filtre olduğunda mavi rozet noktası gösterildi.
  - **Hızlı Damga ve Modal Scrollbar'ı Gizlendi**: Yatay çirkin scrollbar `scrollbar-width: none` ve `::-webkit-scrollbar { display: none }` ile kaldırıldı.
  - **Takvim Hücreleri Pastel Temaya Kavuşturuldu**: Eski gri kutular yerine Görsel 2 sağ ekrandaki gibi zarif pastel hücreler (`.calendar-tile`), gün numarası, durum rozeti ve kaydedilmemiş değişiklik glow noktası entegre edildi.
  - **Derleme**: Vite üretimi (`npm run build`) başarıyla tamamlandı.

## 2026-10-16 — Mobil SPA 'Daha Fazla' Menü Sadeleştirmesi ve Kart Gölge Dağılımı Optimizasyonu
- [x] Diğer ("Daha Fazla") menüsünden gereksiz öğeler kaldırıldı ve kart gölgeleri optimize edildi:
  - **Menü Öğeleri Kaldırıldı**: `operations` dizisindeki `Ana Sayfa` ve `Profil & Ayarlar` kartları ile üst kısımdaki `nav-sheet-profile` kartı tamamen kaldırıldı.
  - **Masaüstü Modu Butonu Kaldırıldı**: Footer alanından `Masaüstü Modu` butonu kaldırıldı, footer tek sütun (`grid-template-columns: 1fr;`) olarak sadece tam genişlikte `Güvenli Çıkış` butonunu içerecek şekilde düzenlendi.
  - **Kart Gölgeleri Dağılımı Azaltıldı**: `src/design-system.css` içindeki `--ds-shadow-tile` (`0 6px 16px ...` -> `0 2px 6px ...`), `--ds-shadow-bento` (`0 8px 24px ...` -> `0 4px 14px ...`) ve diğer yardımcı gölge tokenlarının yayılma ve blur değerleri derli toplu ve hafif hale getirildi.
  - **Derleme**: Vite üretimi (`npm run build`) başarıyla tamamlandı.

## 2026-10-16 — Mobil SPA Puantaj Modülü (Bento UI & Hızlı Damga & SGK Vizite Entegrasyonu)
- [x] Mobil SPA (`mobile-spa`) bünyesinde modern, kullanıcı dostu ve zengin Puantaj sayfası sıfırdan geliştirildi:
  - **Backend API (`public/api/puantaj.php` & `dist/api/puantaj.php`)**: Gate yetkilendirmesi (`puantaj_raporlama`, `puantaj_yonetim`, `puantaj/raporlar`, `puantaj/list`), CSRF doğrulama, JSON/POST veri ayrıştırma, `definitions`, `calendar-data`, `save-bulk`, `sgk-reports` uç noktaları eksiksiz yazıldı.
  - **Frontend Servisi (`services/puantaj.js`)**: Reaktif `puantajApi` metotları oluşturuldu.
  - **Mobil Görünüm (`views/PuantajView.vue`)**:
    - **TopBar**: Dinamik başlık ve alt açıklama, SGK Vizite butonu ve anlık yenileme.
    - **Dönem Gezinme Bento Kartı**: Önceki/Sonraki ay butonları, ortada tıklanabilir büyük ay/yıl başlığı (dokunulduğunda açılan modern yıl/ay seçici sheet) ve "BUGÜN" hızlı sıçrama butonu.
    - **4'lü Bento KPI İstatistik Kartları**: Toplam Personel, İzinli (kişi & gün sayısı - dokunulduğunda listeyi filtreler), Raporlu (kişi & gün sayısı - dokunulduğunda listeyi filtreler), Toplam Çalışılan gün sayısı. Aktif filtrelere tıklanıp açma/kapama ve temizleme chip'i desteği.
    - **Canlı Arama & Detaylı Filtre Çekmecesi**: İsim/TC/departman araması, açılır filtre sheet'inde Departman, Bölge ve İŞKUR Dahil toggle seçicisi.
    - **Personel Bento Kartları**: Avatar (fotoğraf/baş harf), isim, dinamik renkli departman rozeti, TC kimlik no, çalışılan gün / ay gün oranı ve ilerleme çubuğu, varsa izin/rapor gün rozetleri. Dokunulduğunda Personel Ay Detayı Bottom Sheet'i açılır.
    - **Personel Detay & 7 Sütunlu Takvim Bottom Sheet**:
      - *Hızlı Damga Paleti (Stamp Mode)*: Ücretli/Ücretsiz sekmesi, renkli izin türleri chip'leri. Seçildiğinde "Damga Modu Aktif" banner'ı çıkar ve takvimde dokunulan her güne seçili statüyü damgalar.
      - *7 Sütunlu Günlük Takvim Izgarası*: Pazartesi'den Pazar'a hizalı, Pazar günleri özel vurgulu, her günde gün numarası ve statü rozeti (X, HT, Yİ, SGK vb.), değiştirilen günlerde amber ring/parlama efekti. Dokunulduğunda hızlı döngüsel geçiş (X -> HT -> Yİ -> SGK).
      - *Tarih Aralığı ile Statü Tanımla*: Katlanabilir akordeon kartı, başlangıç günü, bitiş günü ve statü seçimi ile toplu aralık uygulama.
    - **Yüzen Kaydetme Çubuğu (Floating Clay Action Bar)**: Ekranın alt kısmında yüzen koyu obsidian çubuk, kaydedilmemiş değişiklik sayacı, "Vazgeç" ve "Kaydet" butonları.
    - **SGK Vizite Raporları Bottom Sheet**: Seçili aya ait onaylı vizite raporları, personel eşleşmesi, tarih aralığı, vaka türü ve "Puantaja İşle (SGK)" butonu.
    - **Kaydedilmemiş Değişiklik Güvenliği**: Sayfadan veya aydan ayrılmaya çalışıldığında kullanıcıyı uyaran ve onay isteyen dialog.
  - **SPA Rota ve Menü Entegrasyonu**:
    - `router.js`: `/puantaj` ve `/puantaj-takip` rotaları eklendi.
    - `BottomNav.vue`: "Daha Fazla" menüsündeki legacy link Vue rotasına (`to: '/puantaj'`) bağlandı.
    - `HomeView.vue`: Hızlı işlemler listesindeki Puantaj linki Vue rotasına (`to: '/puantaj'`) bağlandı.
  - **Derleme**: `npm run build` ile 0 hata ile derlendi.

## 2026-10-16 — Mobil SPA KM Onayları Modülü (Bento UI, AI Entegrasyonu & Day/Night Toggle)
- [x] Mobil SPA (`mobile-spa`) bünyesinde modern, kullanıcı dostu KM Onayları sayfası sıfırdan geliştirildi:
  - **Backend API (`public/api/km-onaylari.php` & `dist/api/km-onaylari.php`)**: Gate yetkilendirmesi (`arac_takip_yonetim`, `onaylikm_duzenle`), CSRF doğrulama, JSON/POST veri ayrıştırma, `list`, `approve`, `edit_approve`, `reject`, `bulk_approve`, `ai_check`, `send_reminder`, `bulk_reminder` uç noktaları eksiksiz yazıldı.
  - **Frontend Servisi (`services/km-onaylari.js`)**: Reaktif `kmState` ve `kmApi` metotları oluşturuldu.
  - **Mobil Görünüm (`views/KmOnaylariView.vue`)**:
    - **TopBar**: Dinamik başlık ve sayaç, yenileme ve çoklu seçim modu açma/kapatma.
    - **Minimal Bento Özet Strip**: Sayfa içi kaba sekme çubukları kaldırıldı; sol tarafta aktif sekme başlığı, kayıt sayacı ve 4 renkli mini segment oran çizgisi (`.km-mini-track`) yerleştirildi.
    - **Day/Night Toggle Switch (Güneş & Ay/Yıldızlar)**: Arama kutusu altındaki chip'ler yerine özet kart içine entegre pürüzsüz animasyonlu Sabah (Güneş ☀️) / Akşam (Hilal & Yıldızlar 🌙) toggle switch'i ve "Tümü" seçeneği eklendi.
    - **Alt Navigasyon Çekmecesi (BottomNav Drawer)**: Durum filtreleri (`Bekleyen`, `Yapmayanlar`, `Onaylanan`, `Reddedilen`) Talepler sayfasındaki gibi alt gezinme çubuğunda (`BottomNav.vue`) rozet sayaçlı çekmece ve `Kapat (X)` butonu ile entegre edildi. Çekmece kapalıyken açan `floating-km-dock-btn` sağlandı.
    - **Çoklu Seçim Modu**: Seçilen adet rozeti, "Yapay Zeka ile Doğrula" ve "Toplu Onayla" eylem çubuğu.
    - **Hero Bildirim Kartları**: TR plaka rozeti, araç marka/model, tür rozeti, yüksek kaliteli gösterge fotoğrafı (saat, tarih, net KM sayacı, sabah KM farkı), personel avatarı, açıklama notu, varsa AI analiz uyarısı, onaylayan/reddeden detayları.
    - **Yapmayanlar Listesi**: Personel avatarı, plaka, telefon, son 30 gün akşam eksik sayısı ve tek tıkla personele Push/sistem bildirimi gönderme.
    - **Modallar**: Tam ekran zoom destekli Görsel Lightbox, Düzelt ve Onayla Bottom Sheet (+10/+50/+100 hızlı butonlar), Hazır şablonlu Reddetme Modalı ve Yapay Zeka OCR Analiz Rapor Modalı.
  - **SPA Entegrasyonu**: `router.js` (`/km-onaylari`), `BottomNav.vue` ("Daha Fazla" menüsü ve çekmece) ve `AracView.vue` (KM sekmesi yönlendirme banner'ı) tam entegre edildi.
  - **Derleme**: `npm run build` ile 0 hata ile derlendi.

## 2026-10-16 — Mobil SPA Tipografi Standardizasyonu
- [x] Mobil SPA (`mobile-spa`) genel yazı tipi, Personel PWA standardı olan **Roboto Condensed** ile eşitlendi:
  - `mobile-spa/index.html` ve `mobile-spa/dist/index.html` içine Google Fonts `Roboto Condensed` (`ital,wght@0,100..900;1,100..900`) preconnect ve link etiketleri eklendi.
  - `mobile-spa/src/styles.css` içindeki `@import`, `:root` ve `body` font-family tanımları `Roboto Condensed` olarak güncellendi.
  - `mobile-spa/src/design-system.css` merkezi `--ds-font-main` değişkeni `'Roboto Condensed', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif` olarak ayarlandı; tüm Bento kartları, butonlar, inputlar ve formlar merkezi değişkenden miras aldı.
  - Münferit sayfalardaki hardcoded font tanımları (`CariView.vue`, `CariDetailView.vue`, `PersonnelSectionView.vue`, `KasaView.vue`, `FaturaView.vue`) `var(--ds-font-main)` standardına çekildi.
  - `npm run build` ile Vite derlemesi başarıyla tamamlandı.

## 2026-10-16 — Resmî Ücret Pusulası Dijital Yayın Entegrasyonu / Adım 1
- [x] Bordro raporu ile personel yayın önizleme/yayın akışı aynı `BordroPusulaService` kaynağına bağlandı.
- [x] Resmî kazanç, yasal/özel kesinti, matrah, gün ve vergi istisnası alanları değişmez yayın içeriğine eklendi.
- [x] Personel PWA bordro detayına resmî toplamlar ve yasal kesinti kırılımı eklendi.
- [x] İç hesap detayları ile resmî olmayan ödeme kanallarının yayın içeriğine taşınmadığı regresyon testiyle doğrulandı.
- [x] İlgili bordro pusula/yayın testleri: 35 test, 120 doğrulama geçti.
- [x] Yeni yayınların personel PDF'i yönetim tarafındaki resmî ücret pusulası şablonuyla ortaklaştırıldı.
- [x] PDF yalnız mühürlü snapshot'tan üretiliyor; T.C. maskeli, teknik/iç ödeme verileri dışarıda ve eski yayınlar geriye uyumlu.
- [x] İkinci adım sonrası ilgili testler: 35 test, 128 doğrulama geçti.
- [ ] Personel pusula durumları ve “Okudum ve Onaylıyorum” beyan metni standartlaştırılacak.
- [x] Personel pusula durumları ve “Okudum ve Onaylıyorum” beyan metni standartlaştırıldı.
- [x] Pusula numarası, oluşturma/gönderim zamanı ve onay kanıt alanları ayrı migration ile eklendi.
- [x] Kategorili itiraz, süreç durumları, şifreli dosya eki, yönetici/personel bildirimleri ve revizyon bildirimi tamamlandı.
- [x] Genişletilmiş bordro pusula/yayın regresyonu: 38 test, 138 doğrulama geçti.
- [x] `database/migrations/2026_10_16_expand_bordro_yayin_onay.sql` yerel hedef veritabanına uygulandı.
- [x] Personel PWA'da PDF indirmeden resmî pusulanın ayrıntılı personel, işyeri, gün, matrah, kesinti, kazanç, istisna, özet ve doğrulama bilgileri incelenebilir hale getirildi.
- [x] Bordro modalı `Pusula Detayı` ve `İtiraz / İnceleme` sekmelerine ayrıldı; açık talep rozeti ve işlem sonrası sekme devamlılığı eklendi.
- [x] Pusula detayında gelir/kesinti kartları üste taşındı ve yeşil/kırmızı tonlarla ayrıldı; bilgi kartları alta alındı, işyeri kartı kaldırıldı.
- [x] Yemek günlük yuvarlama farkı görünümde yemek yardımına birleştirildi; ayrı yuvarlama satırı kaldırılırken kayıtlı hesap ve toplamlar korundu.
- [x] Personel ve belge doğrulama kartları personel pusula ayrıntısından kaldırıldı; bekleyen onay kartına erişilebilir pulse border efekti eklendi.
- [x] Mobil SPA (`mobile-spa`): Evrak Takip modülü sıfırdan Soft Bento & Claymorphic UI standartlarında, ISO 27001 şifreli dosya desteği ve E-İmza akışıyla geliştirildi (2026-10-15):
  - **Güvenli Backend API (`public/api/evrak.php`)**: Gate yetkilendirmesi, CSRF doğrulaması, PDO parametrik bağlama ile `EvrakTakipModel` üzerinden tüm işlemler yürütüldü. `list`, `meta`, `detail`, `save`, `delete`, `e-imza-onaya-sun`, `e-imza-onayla`, `e-imza-iade`, `e-imza-geri-al`, `arac-zimmet`, `stream-file` ve `pdf` aksiyonları tamamlandı.
  - **ISO 27001 Belge Şifreleme**: Yüklenen belgeler `Security::encryptFile()` ile diske şifreli kaydediliyor; `stream-file` ile kullanıcı yetkisiyle anlık deşifre edilip MIME türüyle sunuluyor.
  - **Frontend Servisi (`services/evrak.js`)**: Reaktif veri yönetimi, filtreleme, e-imza onay, iade ve zimmet sorgulama uçları tanımlandı.
  - **Mobil Görünüm (`views/EvrakView.vue`)**:
    - `TopBar` kompakt başlık ve hızlı `+ Yeni Evrak` butonu.
    - Acil durum kartı (İmzanızı bekleyen evraklar için vurgulu kehribar banner).
    - 4 Minimal Bento KPI kartı (Toplam, Gelen, Giden, Cevap Bekleyen).
    - Canlı metin araması ve durum hapları (Tümü, Gelen, Giden, Cevap Bekleyen, İmzamda).
    - Dokunmatik evrak Bento kartları (Yön rozeti, evrak no, tarih, durum rozetleri, kurum, zimmetli & ilgili personel, trafik cezası plaka/tutar, ekler, doğrudan imzala/iade/onaya sun/düzenle/sil aksiyonları).
    - Detay Bottom Sheet (Giden evrak için E-İmza Stepper onay akışı, Resmî Yazı PDF önizleme, ekli dosyaların in-app lightbox/iframe önizlemesi ve indirilmesi).
    - Yeni/Düzenleme Form Sheet (Gelen/Giden geçişi, hazır konu çipleri, kurum adı, ofis/ilgili personel seçimi, trafik cezalarında araç seçildiğinde otomatik sürücü bulma ve %25 erken ödeme indirimini anında hesaplama, 1-3 imza yetkilisi seçimi, çoklu dosya yükleme).
  - **Yönlendirme & Navigasyon**: `router.js` (`/evrak-takip`), `BottomNav.vue` ("Daha Fazla" menüsü) ve `HomeView.vue` (hızlı işlem) SPA içi rotaya bağlandı.
  - **Derleme**: `npm run build` ile 0 hata ile derlendi.
- [x] Mobil SPA (`mobile-spa`): E-Fatura Gelen & Giden sekmeleri düzenlemesi ve Minimal Bento Özet Kartı (2026-10-15):
  - **Minimal Bento Özet Kartı (`.fatura-mini-bento-strip`)**: Kaba 3 kutulu eski şerit yerine tek satırlık kompakt Bento kartı; sekmeye özel renkli mini ikon (`Send`, `Inbox`), net toplam tutar, durum rozetleri (`Onaylı`, `Bekliyor` / `Kabul`, `Yanıt Bekliyor`, `Red`) ve kart altında 3px zarif oran dağılım çizgisi eklendi.
  - **Dinamik Sayaçlı Durum Filtreleri (`.fatura-filter-pills`)**: `Tümü`, `Onaylı`, `Bekleyen` / `Yanıt Bekleyen`, `Kabul`, `Red` filtre haplarına dinamik adet sayaçları (`.fatura-pill-num`) entegre edildi.
  - **Fatura Liste Kartları (`.fatura-card`)**: Kart başlığı, cari ünvanı, VKN/tarih ayrımı, kalem önizleme şeridi ve alt aksiyon butonları (`Yanıt Ver`, `Fatura Gör`, `Paylaş`) ferah ve dokunmatik uyumlu hale getirildi.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
- [x] Fatura sihirbazı: merkezi tokenlarla okunaklı form, belirgin ileri/geri/kaydet/gönder butonları, iki satırlı son adım aksiyonları ve uzun alıcı adı yerleşimi uygulandı (2026-10-15).
- [x] Mobil SPA (`mobile-spa`): E-Fatura taslak sihirbazı genel okunabilirlik ve yönlendirme açısından geliştirildi (2026-10-15): aktif adım metni, erişilebilir adım durumu, taşmayan fatura özeti ve bağlama uygun ileri aksiyon metni eklendi.
- [x] Mobil SPA (`mobile-spa`): E-Fatura Özet sekmesi grafik & Bento zenginleştirmesi, yüzen buton konumu ve "Diğer" menüsü düzeltmesi (2026-10-15):
  - **"Diğer" Butonu Düzeltmesi (`BottomNav.vue`)**: Ortadaki `LayoutGrid` "Diğer" butonuna basıldığında sayfa çekmecesini zorla açan koşul kaldırıldı; doğrudan standart "Daha Fazla" menüsü (`menuOpen`) tetikleniyor.
  - **Yüzen Menü Butonu Konumu (`design-system.css`)**: `.floating-fatura-dock-btn` ve `.floating-arac-dock-btn` butonları `bottom: calc(var(--ds-safe-bottom, 16px) + 96px);` yapılarak alt dock ile çakışması önlendi, ergonomik yüksekliğe çekildi.
  - **Özet Sekmesi Görsel Grafik & Bento Zenginleştirmesi (`FaturaView.vue`)**:
    - **Obsidian Black Hero Bento Kartı**: Toplam Ticari İşlem Hacmi (`totalTradeVolume`), Net Ticari Bakiye (`netTradeBalance`) ve duruma göre pozitif bakiye / alış ağırlıklı rozeti eklendi.
    - **Çift Yönlü Segmentli Dağılım Çubuğu**: Satış % (Zümrüt Yeşili) ve Alış % (Gökyüzü Mavisi) segmentli progress barı (`.fatura-segmented-track`) + interaktif detay lejantı (fatura adetleri ve ciro/maliyet tutarları).
    - **Gelişmiş KDV & Vergi Sağlığı Kartı**: Net Ödenecek / Devreden KDV tutarı, Alış KDV'sinin Satış KDV'sini karşılama yüzdesi ve renkli görsel ilerleme skalası (`.fatura-kdv-gauge-track`), Hesaplanan KDV ve İndirilecek KDV karşılaştırma kutuları.
    - **3'lü Finansal Bento Metrikler Şeridi**: Ortalama Satış Faturası, Ortalama Alış Faturası ve Efektif KDV Yükü Oranı (`%`).
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): E-Fatura mal/hizmet adımı ve ileri/geri navigasyonu iyileştirildi (2026-10-15): kalem kartları sadeleştirildi, toplam satırı güçlendirildi, footer butonları dengeli ve dokunmatik uyumlu hale getirildi.
- [x] Mobil SPA (`mobile-spa`): E-Fatura taslak oluşturma sheet’i kullanıcı dostu hale getirildi (2026-10-15): 4 adıma bağlamsal açıklama blokları eklendi; form alanları, içerik zemini, sheet yüksekliği ve alt aksiyon alanı mobil dokunma ergonomisine göre iyileştirildi.
- [x] Mobil SPA (`mobile-spa`): “Daha Fazla” menüsü Soft Bento tasarımından ayrılmadan renklendirildi (2026-10-15): başlık/kapatma aksanları, profil kartı gradienti, 4’lü ikon renk paleti ve kart dokunma geri bildirimi merkezi `design-system.css` üzerinden güncellendi; yapı ve yetki filtrelemesi korunuyor.
- [x] Mobil SPA (`mobile-spa`): E-Fatura alt menü rozet (badge) mesafe düzeltmesi ve Giden/Gelen ikonlarının yenilenmesi (2026-10-15):
  - **Rozetlerin İkona Çıpalenmesi (`design-system.css`)**: `.bottom-nav .nav-icon` elemanındaki `position: static !important;` kuralı `position: relative !important;` yapıldı. Böylece `.nav-bubble-badge` rozeti butonun en sağına kaçmak yerine 24x24px ikona çıpalandı (`top: -4px; right: -7px;`) ve sayılar tam ikonun sağ üst omuzuna oturdu.
  - **Giden ve Gelen İkonları (`BottomNav.vue`, `FaturaView.vue`)**: Çapraz oklar yerine e-fatura/muhasebeye uygun ikonlar getirildi: Giden için `Send` (Gönderim / Kağıt uçak), Gelen için `Inbox` (Gelen kutusu tepsisi). Hem alt çekmecede hem de sayfa içi KPI/hızlı işlem kartlarında uygulandı.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): Evrak Takip (E-Fatura & Finans) sekmelerinin Talepler gibi alt menüye (obsidian floating dock drawer) taşınması (2026-10-15):
  - **Kullanıcı Talebi**: E-Fatura & Finans (Evrak Takip) sayfasında ekranın üstünde yer alan sekmeli menünün (Özet, Giden, Gelen, Taslaklar), Talepler sayfasındaki alt menü gibi yapılması.
  - **Servis & Reaktif Durum Yönetimi (`services/fatura.js`)**: `faturaState` reaktif nesnesi oluşturuldu (`activeTab: 'ozet'`, `drawerOpen: true`, `counts: { giden: 0, gelen: 0, taslak: 0 }`). `setFaturaTab`, `openFaturaDrawer`, `closeFaturaDrawer` ve `toggleFaturaDrawer` yardımcı fonksiyonları tanımlandı.
  - **Navbar Çekmecesi (`BottomNav.vue`)**: Sayfaya özel `showFaturaDrawer` çekmecesi eklendi. Tam 5 sütunlu nav ızgarası (`fatura-nav-tabs`, `--nav-count: 5`) oluşturuldu: `Özet` (`ReceiptText`), `Giden` (`ArrowUpRight`, sayaç rozetli), `Gelen` (`ArrowDownLeft`, sayaç rozetli), `Taslak` (`Edit3`, sayaç rozetli) ve `Kapat` (`X`). Çekmece kapatıldığında standart BottomNav'a geçiş yapılması; kapalıyken alt bardaki orta "Diğer" butonuna tıklandığında çekmecenin geri açılması sağlandı.
  - **Sayfa Ferahlatması (`FaturaView.vue`)**: Sayfanın üst kısmında sıkışıklık yaratan eski `.clay-tabs-wrap.fatura-tabs` bloğu tamamen kaldırıldı. Çekmece kapalıyken tek dokunuşla menüyü geri açabilen yüzen tetikleyici buton (`.floating-fatura-dock-btn`) eklendi. `activeTab` ile `faturaState.activeTab` iki yönlü senkronize edildi. `dashData` ve fatura listesi yüklendikçe `faturaState.counts` değerleri dinamik güncellenerek alt menüdeki ikon rozetlerine anlık yansıtıldı.
  - **Tasarım Sistemi (`design-system.css`)**: `.fatura-nav-tabs` ve `.floating-fatura-dock-btn` stilleri merkezi tasarım sistemine eklendi.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): Araç Takip üst özet kartının minimalleştirilmesi ve TR plaka mavi alanına border-radius verilmesi (2026-10-15):
  - **Minimal Hero Bento Kartı (`AracView.vue`)**: Üstteki koyu Bento özet kartına `.arac-hero-bento` sınıfı eklenerek dikey yüksekliği ~%40 oranında küçültüldü. İç dolgular `13px 15px 14px`, köşe kavisi `20px`, başlık fontu `15px`, etiket `8px`, segmentli track yüksekliği `5px`, 4'lü metrik ızgara aralığı `7px`, metrik hücre iç boşluğu `7px 10px`, hücre değerleri `14.5px` ve etiketleri `9.5px` olarak ayarlandı.
  - **TR Plaka Mavi Alanının Yuvarlatılması**: `.tr-plate-badge` içine `padding: 1.5px; border-radius: 7px;` eklendi; `.tr-band` mavi alanı için `border-radius: 4px; padding: 3px 5px;` tanımlanarak plaka görseli içindeki mavi TR alanının tüm köşelerine estetik bir kavis verildi.
  - **KM Gösterim Güvenliği**: `formatKm` ve `km-diff-pill` içindeki değerler negatif KM riskine karşı `Math.max(0, ...)` ve `Math.abs(...)` ile güvenceye alındı.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi hatasız alındı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): Araç Takip üst koyu Bento kartının dinamikleştirilmesi ve alttaki mükerrer özetlerin temizlenmesi (2026-10-15):
  - **Dinamik Koyu Bento Kartı (`AracView.vue`)**: En üstteki koyu Bento kart aktif sekmeye göre dinamik hale getirildi: `araclar` (Filo Operasyon Özeti), `zimmet` (Zimmet Operasyon Özeti), `yakit` (Yakıt Operasyon Özeti), `km` (KM Operasyon Özeti) ve `servis` (Servis Operasyon Özeti). Her sekmeye özel segmentli ilerleme çubuğu ve 4'lü metrik hücresi eklendi.
  - **Evrak Uyarısı Sınırlandırması**: Evrak Yenileme Uyarısı kartı yalnızca Araçlar sekmesinde (`activeTab === 'araclar' && summary.evrak_uyari_sayisi > 0`) görünecek şekilde izole edildi.
  - **Alttaki Özet Kartları Kaldırıldı**: Sayfa ortasında mükerrer duran beyaz `.yakit-summary-bento` ve `.km-summary-bento` kartları tamamen kaldırıldı.
  - **Backend API Metrikleri (`public/api/arac.php` & `dist/api/arac.php`)**: Zimmet aktif/iade, yakıt tutar/litre/fiş/ortalama, km net mesafe/bildirim/ortalama ve servis aktif/tamamlanan/maliyet metrikleri dinamik agregasyonla sunuldu.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
- [x] Mobil SPA (`mobile-spa`): Araç Takip sekmelerinin navbar alt menüsüne taşınması (Scrollable sekmeler & Sabit Kapat butonu) (2026-10-15):
  - **Kullanıcı Talebi**: Araç takip sayfasındaki üstte bulunan sekme menüsünün (Araçlar, Zimmet, Yakıt, KM, Servis...), Talepler sayfasındaki gibi navbar alt çekmecesine taşınması; sekmelerin yatayda scrollable yapılması ve Kapat butonunun en sağda sabit tutulması.
  - **Servis Durumu**: `src/services/arac.js` içerisinde `aracState` (`activeTab`, `drawerOpen`), `setAracTab`, `openAracDrawer`, `closeAracDrawer` ve `toggleAracDrawer` yardımcıları tanımlandı.
  - **Navbar Çekmecesi & Sabit Kapat Butonu (`BottomNav.vue`)**: Araç takip sayfası için `showAracDrawer` çekmecesi entegre edildi. Sekmeler `.arac-nav-tabs__scroll` içinde yatay kaydırılabilir hale getirildi; aktif sekmeye `scrollIntoView` ile yumuşak geçiş sağlandı. En sağdaki `Kapat` (`X`) butonu `sticky`/sabit konumlandırılarak derinlik gölgesiyle kayan sekmelerin arkasında kalması sağlandı. Kapatıldığında ana sekmelere dönülmesi, Araç sayfasındayken ana bardaki butona basıldığında çekmecenin geri açılması sağlandı.
  - **Sayfa Ferahlatması (`AracView.vue`)**: Sayfa üstünde yer alan ve sağa taşan eski `clay-tabs-wrap` menüsü kaldırıldı. Sayfa açılışında çekmecenin otomatik açılması, kapanışta temizlenmesi ve alt çekmece kapalıyken kullanıcının tek tıkla geri açabilmesi için yüzen tetikleyici buton (`.floating-arac-dock-btn`) eklendi.
  - **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/`).
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

- [x] Mobil SPA Kasa Takibi yıl filtresine `Tüm Zamanlar` eklendi; bu seçimde ay filtresi temizleniyor ve tüm dönem liste/özet API sorgusu kullanılıyor. (2026-10-20)

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
- [x] Ücret hesap pusulası ve PDF sunumunda yemek günlük yuvarlama farkı ayrı satırdan kaldırılıp yemek yardımına dahil edildi; kayıtlı hesap ve toplamlar değiştirilmedi (6 test / 45 doğrulama).
- [x] Ücret hesap pusulası/PDF içinde hafta içi nöbet kalemleri `Fazla Mesai Ücreti (Net)` etiketi altında birleştirildi; tutar ve hesap kaynağı korundu (6 test / 47 doğrulama).
- [x] Büyük Türkçe `İ` kaynaklı Unicode eşleme hatası giderildi; gerçek `Hafta İçi Nöbet` etiketi pusulada artık `Fazla Mesai Ücreti (Net)` olarak doğrulandı.
- [x] Pusula kesintilerindeki tekrarlı `kesintisi` eki ve eksi işaretli sunum giderildi; başlık `KESİNTİLER`, etiketler kaynak adıyla ve tutarlar pozitif büyüklükle gösteriliyor (14 test / 73 doğrulama).

# 2026-10-16 — Personel test yayını şema hatası

- [x] Genel yayın hatasının günlük kaydı incelendi; eksik `pusula_no` sütunu kesin neden olarak belirlendi.
- [x] Ücret pusulası ve dijital onay migrasyonu yerel veritabanına uygulandı.
- [x] Yeni döküm alanları, itiraz eki tablosu ve eski aktif kayıtlardaki pusula numarası doluluğu doğrulandı.
- [x] İlgili birim testleri geçti: 30 test, 117 doğrulama.
- [x] İkinci personel testinin birinci personel testini silmesi/pasifleştirmesi giderildi; testler personel bazında bağımsız tutuluyor.
- [x] Sayfa yenilemesi sonrası yayın modalı açıldığında takip ve döküm önizlemesinin sunucudan otomatik yeniden kurulması sağlandı.
- [x] Bildirimsiz test kayıtları yönetim takip KPI toplamına dahil edildi.
- [x] Çoklu personel test regresyonu eklendi; ilgili paket 31 test / 120 doğrulama geçti.
- [x] Muhasebe bordrosundaki toplam, normal, hafta tatili, ücretli izin ve rapor günleri bağımsız karşılaştırma alanlarına dönüştürüldü.
- [x] Toplam gün eşitken alt gün türlerindeki farkların kaybolmasını engelleyen regresyon testi eklendi.
- [x] Modal kişi detayına açılır Gün Karşılaştırması tablosu eklendi; parasal farklar ayrı bölümde korundu.
- [x] Gerçek 103 satırlık muhasebe dosyasında gün kolonları ve 55 adet “toplam eşit / alt kırılım farklı” sonucu doğrulandı.
- [x] Bordro regresyon paketi: 113 test, 867 doğrulama geçti; önceden mevcut 7 uyarı sürüyor.
- [x] Mobil SPA Evrak Takip detayındaki `ee.yukleyen_kullanici_id` bilinmeyen kolon hatası giderildi; çoklu dosya/yükleyen migration'ı idempotent yapılıp aktif veritabanına uygulandı ve Model ek sorgusu doğrulandı. (2026-10-18)
- [x] Mobil SPA Evrak formunda ofis/ilgili personel seçenekleri yalnız aktif kayıtlarla sınırlandı; plaka seçiminde tarih bazlı araç zimmetlisi otomatik olarak İlgili Personel alanına getirildi ve üretim SPA paketi yeniden oluşturuldu. (2026-10-18)
- [x] Kullanıcı kararıyla mobil SPA cari hareket tutarlarında Aldım için `-`, Verdim için `+` işaretleri liste ve detay görünümüne geri eklendi. (2026-10-18)
- [x] Cari hareket özetlerindeki Aldım/Verdim kartları mobil SPA ve masaüstü hesap hareketleri sayfasında ilgili hareketleri filtreleyecek şekilde etkinleştirildi; tekrar seçimle filtre temizleniyor. (2026-10-18)
- [x] Mobil tekil cari Ekstre PDF bağlantısı alt dizin uyumlu API endpoint'iyle onarıldı; masaüstü ve mobil cari listelerine tüm aktif carilerin ayrı PDF'lerini ve CSV özetini tek ZIP içinde indiren yetki kontrollü toplu yedek eylemi eklendi. Gerçek 13 cariyle çıktı doğrulandı. (2026-10-18)
- [x] Toplu cari ZIP çıktısının web sunucusunda mPDF geçici dizin yetkisi nedeniyle başarısız olması giderildi; web/CLI kullanıcılarına ayrılmış yazılabilir sistem geçici dizini uygulandı. (2026-10-18)
- [x] Pazar günü kişi bazında varsayılan 10 okumayı geçen Okuma/Endeks Okuma personeline 23:45 endeks cron'u sonrasında otomatik HTÇ üretimi eklendi; pazartesi sabah telafisi, firma ayarları, legacy HT dönüşümü, soft delete ve manuel kod koruması uygulandı. (2026-10-18)
- [x] Otomatik HTÇ'nin mobil/masaüstü puantajda başka koda veya kayıtsız `X` gününe çevrilmesinin sonraki cron tarafından ezilmesini önleyen kaynak ve otomasyon kilidi takibi eklendi. (2026-10-18)
- [x] Otomatik HTÇ karar testleri ile ilgili bordro regresyonları geçti: 57 test / 601 doğrulama; önceden mevcut 7 uyarı devam ediyor. (2026-10-18)
- [x] Otomatik pazar HTÇ kapsamı kaçak personeli için 2, sökme-takma ve mühürleme personeli için 10 işlem eşiğiyle genişletildi; eşikler firma ayarına ve ilgili operasyon cronlarına bağlandı. (2026-10-18)
# 2026-10-19 — Puantajda nöbet mesaisi gösterimi

- [x] Onaylı hafta içi nöbetlerin 4 saatlik fazla mesaisi masaüstü ve mobil puantaj verisine eklendi.
- [x] Manuel ve nöbet kaynaklı saatler günlük/aylık toplamda birleştirildi; manuel düzenleme nöbet saatini değiştirmiyor.
- [x] Excel hücreleri `X+4S` biçiminde dışa aktarılıyor.
- [x] PHP/JavaScript sözdizimi, gerçek Ekim 2026 nöbet verisi ve mobil üretim derlemesi doğrulandı.
- [x] Modal `Kaydet` düğmesinin yalnız tarayıcıdaki puantaja uygulama yaptığı netleştirildi; kalıcı kayıt için üst `Kaydet` uyarısı eklendi. Eksik manuel kayıt tamamlanıp ilgili açık bordro yeniden hesaplandı ve snapshot okuyucusunda 12 saat / 2.246,04 TL doğrulandı.
- [x] Bordro detayında hafta içi nöbet ve manuel fazla mesai sözleşme alacağı tarafında ayrıldı; resmî alacak dağılımı iki fazla çalışma kaynağını `Fazla Mesai Ücreti (Net)` etiketi altında birleştirecek şekilde düzeltildi.
- [x] Masaüstü fazla mesai ekleme/kaldırma işlemleri doğrudan kalıcı API kaydına bağlandı. Test personelindeki manuel 4 saat soft-delete edilip bordro yeniden hesaplandı; 8 saat / 1.497,36 TL snapshot doğrulandı.
- [x] Puantaj `Kaydet` API'sini fatal hatayla durduran HTÇ otomasyon model metot imzası çakışması giderildi; gerçek API ekle/sil regresyonu başarıyla doğrulandı.
- [x] Sözleşme alacağındaki hafta içi nöbet detayı `gün × sözleşme nöbet tutarı` formatına çevrildi; teknik `4 Saat` ibaresi kaldırıldı.
- [x] Onaylı hafta sonu nöbetleri HTÇ gün kaynağına eklendi; aynı tarihli puantaj HTÇ ile çift sayım engellendi. Doğrudan/cache gün testi ve yeniden hesaplanan snapshot'ta 1 gün / 935,85 TL doğrulandı.
- [x] Nöbet kaynaklı HTÇ masaüstü/mobil puantaj ve Excel'de kilitli/türetilmiş statü olarak gösterildi; mükerrer manuel HTÇ hem UI hem API katmanında engellendi. Gerçek Ekim API yanıtı ve reddetme regresyonu doğrulandı.
- [x] Sözleşme alacağındaki hafta içi ve hafta sonu nöbetleri tek açılır `Nöbet Ödemeleri` başlığında toplandı; detaylarda sırasıyla `gün × tutar` ve `adet × tutar` gösterimi doğrulandı.
- [x] Evrak Takip'te ikinci belge eklenince eski ana belgenin kaybolmuş görünmesine yol açan ana dosya/ek tablosu ayrımı giderildi; liste ve düzenleme modalı iki kaynağı birlikte ve tekilleştirerek gösteriyor. PHP/JavaScript sözdizimi doğrulandı. (2026-10-19)
- [x] Evrak Takip listesi server-side DataTables yapısına taşındı; `ajaxList()` ve sayfa-kapsamlı toplu ek/onay sorguları eklendi, tüm kayıtların ilk istekte render edilmesi kaldırıldı. Hızlı filtreler ve imza filtresi SQL tarafına alındı. Birleşik liste/onay indeksleri `2026_10_19_optimize_evrak_takip_list_indexes.sql` ile oluşturulup aktif veritabanına uygulandı. 32 kayıt üzerinde 10 satırlık AJAX yanıtı, filtre sonuçları, PHP/JavaScript sözdizimi ve indeksler doğrulandı. (2026-10-19)
- [x] Evrak server-side DataTables sonrasında verilerin görünmemesine neden olan çift istemci/sunucu filtreleme çakışması giderildi. Merkezi filtre modülü server-side tabloda yalnız API araması üretiyor; Model gelişmiş filtre öneklerini ve çoklu seçimleri yorumluyor. `06.08.2026` filtresiyle 2/2 satır döndüğü doğrulandı. (2026-10-19)
- [x] Ana sayfa Kesme/Açma Karşılaştırma bölge/personel detay tablolarına aktif filtreyle uyumlu dönem toplamları ve toplam değişim satırı eklendi; 9.860 / 9.857 farkının tüm sonuçlar ile varsayılan Olumlu filtresi arasındaki üç kayıttan doğduğu doğrulandı. (2026-10-20)

## 2026-10-19: Giden Evrakta Dinamik Normal / KEP Kanalı
- [x] Tek sayfada kart tabanlı Normal/Harici ve KEP gönderim kanalı seçimi eklendi.
- [x] KEP oluşturma alanları dar sağ akordeondan geniş ana alana taşındı; çoklu alıcı doğrulaması, ileti seçenekleri, mesaj ve ek özeti eklendi.
- [x] Resmî üst yazı ile yalnızca ek gönderimi bağımsızlaştırıldı; yalnızca ekte belge zorunluluğu ve EYP için üst yazı zorunluluğu istemci/sunucuda uygulandı.
- [x] KEP sevki kayıtlı taslağı kullanacak şekilde güvenli hale getirildi; yetki, firma ayarı, kanal, tekrar sevk ve boyut kontrolleri eklendi.
- [x] Tekrarlanabilir migration aktif veritabanına uygulandı; PHP/JavaScript sözdizimi ve şema sütunları doğrulandı.

## 2026-10-19: KEP Gönderisinin Bağımsız Modal Akışına Ayrılması
- [x] Evrak Takip üst araç çubuğundaki aksiyonlar tek `Yeni` menüsünde Gelen Evrak, Giden Evrak ve yalnız `evrak-takip/kep` yetkisi bulunan kullanıcılar için KEP Gönderisi olarak birleştirildi.
- [x] PTT’ye aşina kullanıcılar için alıcı, konu, ileti tipi/detayı, metin, ekler, doğrulama ve `İmzala ve Gönder` aksiyonlu modal geliştirildi.
- [x] Bağımsız KEP sevk servisi, arşivleme, yasal delil başlangıç kaydı ve Evrak Takip listesine KEP rozetli kayıt üretimi eklendi.
- [x] `kep-dogrudan-gonder` API politikası ayrı SQL scriptiyle oluşturulup aktif veritabanına uygulandı.
- [x] Giden Evrak üst yazı ekranından yeni KEP kanal seçimi ve oluşturma alanları kaldırıldı; eski sevk kayıtlarının takip görünümü korundu.
- [x] KEP modalındaki `Form` sınıfı namespace hatasının sayfa render'ını keserek tabloyu boş bırakması giderildi; modalın tam HTML üretimi doğrulandı.
- [x] KEP modal içeriği viewport içinde kaydırılabilir hale getirildi; Ekler alanı ve sabit modal footer tüm ekran yüksekliklerinde erişilebilir yapıldı.
- [x] PTT bakiye yanıtındaki `Şifre veya Parola Yanlış` metninin bakiye birimi sanılarak başarılı kabul edilmesi giderildi; modalda eski bakiye ile canlı bağlantı durumu ayrıldı ve geçersiz kimlikte gönderim engellendi.
- [x] `UETS / KEP Ayarları` modalına yetki kontrollü `PTT KEP` sekmesi eklendi; KEP kimlik/servis ayarları, bağlantı testi ve bakiye sorgusu tek ayar penceresinde toplandı. Gizli alanların boş bırakıldığında korunması ve maskeli TCKN'nin tekrar gönderilmemesi sağlandı; PHP/JavaScript sözdizimi doğrulandı.
- [x] Bağımsız KEP modalındaki alıcı rehber sorgusunun tekli/çoklu AJAX sonuç işleme akışı düzeltildi; gerçek API hatası kullanıcıya gösteriliyor. Test alıcısı canlı servis çağrısında `exist=true`, `suitable=true` sonucu verdi; JavaScript kontrolü geçti.
- [x] `kep-alici-dogrula` switch bloğundaki eksik `break` eklendi; başarılı rehber yanıtına e-imza kart JSON'u eklenerek tarayıcıda parse hatası oluşması engellendi.
- [x] KEP gönderi modalındaki e-imza/PIN bölümü isteğe bağlı ikinci adıma dönüştürüldü; kart taraması modal açılışında değil `İmzala ve Gönder` tıklamasında başlıyor, PIN boşken sevk engelleniyor.
- [x] Doğrulanmış KEP alıcılarını otomatik saklayan ve Select2 üzerinden yeniden seçtiren firma bazlı adres defteri geliştirildi; migration aktif veritabanına uygulandı ve test adresi listede doğrulandı.
- [x] KEP PIN girişi ana formdan ayrı, kart durumu ve gönderim onayını birleştiren SweetAlert modalına taşındı; e-imza kart bilgisi aksiyonu KEP yetkisine bağlandı. PHP/JavaScript ve SQL kontrolleri geçti.
- [x] KEP PIN modalındaki `escapeHtml is not defined` JavaScript hatası yerel güvenli kaçış yardımcısı eklenerek giderildi.
- [x] KEP ana modalının odak kilidi nedeniyle PIN alanına yazılamaması, SweetAlert hedefi ana modal içine alınarak giderildi.
- [x] Açılı ayraçlı PTT KepId'nin başarı penceresinde HTML etiketi sanılıp boş görünmesi giderildi; takip kodu güvenli kod bloğunda gösteriliyor ve API uyumluluk alanı eklendi.
- [x] KEP ileti oluşturma modalı bölüm kartları, dinamik dört adımlı akış, kompakt editör ve sabit aksiyon alanıyla görsel/ergonomik olarak yenilendi; masaüstü ve mobil kaydırma davranışı korundu.
- [x] KEP modalı minimal tasarıma revize edildi: stepper kaldırıldı, gönderen/bakiye başlığa taşındı, alıcı ve adres defteri tek satırda birleştirildi, ileti tipi segmentli radio seçimine çevrildi ve ağır kart stili azaltıldı.

# 2026-10-20 — KEP yerel e-imza politika tamamlama

- [x] Yerel e-imzalı KEP gönderiminde 403 üreten eksik `kep-mime-hazirla` ve `kep-smime-sevk` API politikaları mevcut KEP yetkisine bağlanan tekrarlanabilir SQL migration'ıyla tamamlandı.

# 2026-10-20 — Taşınabilir OpenSC içeren e-imza ajanı

- [x] OpenSC 0.27.1 x64 çalışma dosyaları lisans ve SHA-256 doğrulamasıyla JAR içine alındı; kullanıcı tarafında ek OpenSC kurulumu ve yönetici yetkisi ihtiyacı kaldırıldı.
- [x] Java 8 uyumlu ajan yeniden derlendi; JAR kaynak içeriği, otomatik çıkarma, dosya hash'leri ve Windows executable çalışması doğrulandı.
- [x] Windows'ta imza anında native PKCS#11 sürücüsünün Java ajanını düşürmesini önlemek için imzalama gömülü OpenSC alt sürecine izole edildi; 9191 servisinin sürücü hatasından etkilenmemesi sağlandı.
- [x] Çok nesneli e-imza kartlarında özel anahtar–sertifika CKA_ID eşleştirmesi eklendi; sertifika okuma ve imza komutları aynı nesne ID'sine sabitlendi, araç hata çıktıları kullanıcıya güvenli ve sınırlı biçimde taşındı.
- [x] PTT tarafından bozuk S/MIME olarak reddedilen CMS çıktısındaki primitive sertifika kümesi etiketi doğru constructed `[0]` etiketiyle değiştirildi; `1.1.3` ajanı derlendi ve detached içerik üzerinde OpenSSL ile yapısal ve kriptografik doğrulama başarılı oldu.
- [x] Sağlanan PTT WSDL dokümanına göre `MimeYap eHash → detached CAdES-BES → SmimeGonder` akışı teyit edildi; SHA-256 algoritma parametresi strict CMS uyumu için absent kodlandı ve donanımsal RSA çıktısına ajan içi doğrulama eklendi. `1.1.4` paketi OpenSSL detached CMS doğrulamasından geçti.
- [x] Linux/masaüstü kullanımında terminal gereksinimi kaldırıldı: ana pencere ve tepsi menüsüne yeniden başlatma/tam kapatma eylemleri, tepsi ikonunu temizleme ve ikinci JAR açılışında mevcut pencereyi gösterme davranışı eklendi; `1.1.5` JAR derlemesi doğrulandı.
- [x] Linux/OpenSC kartlarında farklı CKA_ID kullanan özel anahtar ve sertifika, sabit deneme verisi üzerinde gerçek RSA imza doğrulamasıyla eşleştirilecek şekilde ajan geliştirildi; `1.1.6` Java 8 uyumlu paket derlendi.
- [x] Kurumsal Windows ortamında ajanı yeniden başlatma sırasında 9191 portunun askıda kalma riski kaldırıldı; eylem `Bağlantıyı Yenile` olarak kart önbelleği yenilemesine çevrildi ve HTTP servisinde dört daemon worker kullanıldı. `1.2.1` paketi derlenip sürüm içeriği doğrulandı.
- [x] Linux `akia-bin` AKİS sürücüsü `/opt/Akia/libakisp11.so` adaylarına eklendi; gerçek ATR19 kart/sertifika algılaması başarıyla doğrulandı. Aynı kartın EC anahtarı için PKCS#11 ECDSA imzalama, DER kodlama/doğrulama ve CMS algoritma desteği eklenerek `1.2.2` JAR üretildi.
- [x] AKİS ikinci sorgusunda görülen `wsm.sessionDeactivated` için kart algılama tek oturumlu hale getirildi; ayrıntılı kimlik sonucu ajan/tarayıcı tarafında önbelleklendi ve yalnız modül yükleyerek üretilen T.C.'siz yalancı başarı kaldırıldı. `1.2.3` paketi derlendi.
- [x] Windows AKİS algılamasına alternatif `akia_pkcs11.dll`/Program Files yolları ve 32/64-bit Java uyumlu sertifika doğrulamalı fallback eklendi; tarayıcı–ajan durum çelişkisine yol açan istemci önbelleği kaldırıldı. `1.2.4` JAR derlendi ve sürüm içeriği doğrulandı.
- [x] Windows'ta kart sürücüsü standart dizinde değilse kullanıcıya terminal gerektirmeden PKCS#11 DLL seçtiren ve seçimi kalıcı saklayan arayüz eklendi; yaygın AKİA dizinleri otomatik taranıyor. `1.2.5` Java 8 uyumlu JAR üretildi.
- [x] Windows'ta PIN öncesi sertifika listesini kapatan AKİS sürümleri için slot/token tabanlı kart algılama ve kullanıcıya kopyalanabilir sürücü tanılaması eklendi; `1.2.6` JAR derlendi.
- [x] PIN öncesinde gizlenen Windows AKİS sertifikası için doğru UI açıklaması ve PIN sonrası X.509 kimliğini önbelleğe alma eklendi; `1.2.7` paketi derlendi, JavaScript/PHP kontrolleri geçti.
- [x] KEP PIN modalında PIN sonrası yerel sertifika listeleme endpoint'i ve sahip/T.C./geçerlilik/algoritma kartları eklendi; `1.2.8` JAR ile JS/PHP kontrolleri geçti.
- [x] Java yerel ajan arayüzü durum kartları ve ayrı `Durum` / `Ayarlar ve Tanılama` sekmeleriyle yenilendi; ayar butonları ana ekrandan ayrıldı ve `1.2.9` JAR üretildi.
- [x] `CKR_MECHANISM_INVALID` veren RSA kartlar için `SHA256-RSA-PKCS` → ajan tarafında SHA-256 DigestInfo + `RSA-PKCS` otomatik fallback'i eklendi; ana imza ve anahtar eşleştirme yolları kapsandı, `1.3.0` JAR üretildi.
- [x] RSA kart mekanizmaları imza öncesinde sorgulanarak desteklenen yol doğrudan seçilir hale getirildi; `0x70` metin tabanlı fallback, bağlı ajan sürümünün web UI'da gösterimi ve yeni `1.3.x` sürümlerinin PAdES kontrolünde kabulü eklendi. `1.3.1` JAR üretildi.

# 2026-10-20 — Cari hesap hareketleri Excel dışa aktarımı

- [x] Excel aksiyonları popup/JavaScript bağımlılığından çıkarılıp doğrudan indirme bağlantısına dönüştürüldü.
- [x] Aktif Aldım/Verdim filtresi ve DataTables genel araması Excel isteğine aktarılmaya devam edecek şekilde korundu.
- [x] Excel veri sorgusu Model katmanına taşındı ve tüm dinamik değerler PDO parametrik binding ile bağlandı.
- [x] İlgili PHP dosyaları, JavaScript ve diff kontrolleri geçti; örnek cari için Model sorgusu 45 satırla başarıyla çalıştı.

# 2026-10-21 — KEP ileti modalı ve adres defteri akışı

- [x] KEP ileti oluşturma modalı numaralı, kart tabanlı bölümler ve belirgin ek yükleme alanıyla yenilendi.
- [x] Yazılan KEP adresleri için kullanıcı kontrollü `Doğrula ve Kaydet` seçeneği eklendi; yalnız PTT rehberinde geçerli ve uygun bulunan adresler firma bazlı adres defterine kaydediliyor.
- [x] Kayıt sonucu satır bazında gösteriliyor ve yeni adresler modal içindeki Select2 adres defteri listesine anında alınıyor.
- [x] PHP, JavaScript ve diff sözdizimi kontrolleri geçti.
- [x] Modal alt işlem alanı kompakt e-imza durum kartı ve sade gönderim aksiyonlarıyla düzenlendi.
- [x] Evrak Takip İşlemler menüsünden AI taslak/icra üst yazısı kısayolları kaldırıldı; Java E-İmza indirme bağlantısı menünün en altına taşındı.

# 2026-10-21 — Giden Evrak ıslak imza onay akışı

- [x] Normal gönderim türüne `Islak İmza ile Onaya Sun` işlemi eklendi.
- [x] Islak imza ve PAdES akışları veritabanındaki `onay_turu` alanıyla ayrıştırıldı.
- [x] Sıralı yetkili onayı, fiziksel imza teyidi, bildirim, işlem kaydı, iade/geri alma ve son onayda kilitleme akışları bağlandı.
- [x] KEP evraklarında ıslak imza; ıslak imza evraklarında PAdES işlemi sunucu tarafında engellendi.
- [x] Migration aktif geliştirme veritabanına uygulandı; yeni API politikaları doğrulandı.

# 2026-10-21 — Kullanıcı bazlı evrak imza yetkisi

- [x] Kullanıcı düzenleme modalına `Evrak İmza Yetkisi` seçimi eklendi ve kullanıcı kayıt API'sine bağlandı.
- [x] `users.evrak_imza_yetkisi` alanı ile sorgu indeksini oluşturan tekrarlanabilir migration hazırlandı ve aktif geliştirme veritabanına uygulandı.
- [x] Giden Evrak imzacı listesi aktif firma üyeliği ve evrak imza yetkisiyle filtrelenir hale getirildi.
- [x] İstemciden gönderilen imzacı seçimi onaya sunma sırasında aynı yetki koşullarıyla sunucu tarafında doğrulanıyor.

# 2026-10-21 — Giden Evrak mobil imza onay taslağı

- [x] Normal gönderim işlemlerine `Mobil İmza ile Onaya Sun` seçeneği eklendi.
- [x] Mobil imza akışı e-imza ve ıslak imzadan ayrı `mobil_imza` türüyle veritabanında saklanıyor.
- [x] Kayıt, evrak kilitleme, sıralı imzacı ve bildirim başlangıç akışı bağlandı; KEP gönderiminde sunucu tarafında engellendi.
- [x] Gerçek mobil imzalama entegrasyonu yapılana kadar sıradaki kullanıcının onay aksiyonu açıklayıcı `hazırlanıyor` mesajı gösteriyor ve sahte onay üretmiyor.

# 2026-10-20 — PTT KEP çoklu kullanıcı kimlikleri

- [x] Aynı kurumsal KEP adresi için her uygulama kullanıcısının kendi PTT TCKN, parola ve web servis şifresini şifreli saklayacağı kullanıcı hesap tablosu eklendi.
- [x] KEP ayar görüntüleme, kayıt, bağlantı, bakiye, rehber ve gönderim akışları oturum kullanıcısının kimliğine izole edildi.
- [x] Eski tekil KEP kimliği ilk yetkili kullanıcıya tek seferlik taşınırken diğer kullanıcıların bu kimliği kullanması engellendi.
- [x] Migration aktif veritabanına uygulandı; sözdizimi, şema ve çoklu kullanıcı izolasyon testi geçti.

# 2026-10-20 — Evrak işlem yapan personel görünümü

- [x] Evrak Takip listesine gelen kaydını oluşturan veya giden evrak işlemini yapan kullanıcıyı gösteren kolon eklendi.
- [x] İşlem yapan kişi alanı global arama, kolon filtresi ve sıralamaya bağlandı; otomatik UETS kayıtlarına sistem etiketi eklendi.
- [x] Server-side DataTables kolon eşleşmeleri ile PHP/JavaScript sözdizimi ve gerçek Model yanıtı doğrulandı.

# 2026-10-20 — Onaylı normal giden evraktan KEP sevki

- [x] Onaylanmış ve henüz KEP ile gönderilmemiş normal giden evraklara liste ve detay sayfasında yetki kontrollü KEP gönderim aksiyonu eklendi; liste aksiyonu sayfa yönlendirmeden mevcut KEP modalını açıyor.
- [x] Alıcı/paket seçimi, onaylı PDF ve eklerin hazırlanması, yerel e-imza ajanı ve PTT KEP sevki mevcut güvenli akışa bağlandı.
- [x] Sunucuda onay durumu, mükerrer gönderim, kullanıcıya bağlı geçici taslak ve 30 dakikalık taslak süresi kontrolleri eklendi.
- [x] Sözdizimi ile onaysız/mükerrer gönderim regresyon kontrolleri geçti.
- [x] Onaylı evrak KEP gönderimindeki `SoapVar`/`strlen()` MIME boyut hesabı hatası iki gönderim yolunda da gerçek dosya byte toplamı kullanılarak giderildi.
- [x] Onaylı evrak KEP gövdesinde üst yazı içeriğinin tekrarlanması kaldırıldı; ileti metni iki gönderim yolunda da `İlgili yazı ektedir.` olarak standartlaştırıldı.

# 2026-10-20 — Gerçek PAdES e-imza

- [x] Sanal onay endpoint'i devre dışı bırakıldı; kart/PIN gerektiren PAdES hazırlama ve tamamlama API'leri mevcut Evrak Takip yetkisine bağlandı.
- [x] Çoklu imzalarda PDFBox incremental signing ile önceki imzaları koruyan yerel ajan v1.2.0 derlendi ve indirilebilir JAR güncellendi.
- [x] Sunucu tarafında PDF kaynak eşleştirmesi, `ByteRange`, CMS kriptografik imzası, sertifika süresi ve kullanıcı-sertifika bağı doğrulamaları eklendi.
- [x] İmza ve sertifika meta verileri için ayrı migration hazırlandı ve aktif veritabanına uygulandı; imzalı PDF şifreli ve SHA-256 kontrollü saklanıyor.
- [x] PDF önizleme ve KEP üst yazı üretimi gerçek PAdES dosyasını kullanacak şekilde güncellendi; PHP/JavaScript sentaksı, JAR bütünlüğü ve SQL politika kayıtları doğrulandı.
- [ ] Fiziksel kart ve güven hizmet sağlayıcısının sertifika zinciriyle uçtan uca PAdES imza denemesi kullanıcı ortamında yapılacak; mevcut çalışan v1.1.6 ajan kapatılıp v1.2.0 ajan başlatılmalı.
- [x] Eski ajan sürümü uyarısına güncel `ErsanEImza.jar v1.2.0` dosyasını doğrudan indiren buton eklendi.
- [x] İlk imzada mPDF'in iki ayrı üretimi arasındaki byte farkından doğan `İmzalı PDF ... değişmez evrak sürümüne ait değil` hatası, hazırlanan PDF'i 15 dakikalık şifreli ve kullanıcıya bağlı oturumda sabitleyerek giderildi.
- [x] PDFBox `ByteRange` boşluğunda `/Contents` anahtarının değil yalnız `<CMS hex>` değerinin bulunmasından doğan `PAdES CMS imza paketi bulunamadı` hatası giderildi; iki PDF yerleşimi de destekleniyor.
- [x] İmzalı evrak PDF'ine örnekteki gibi görünür e-imza bandı; güvenli e-imza ibaresi, belge doğrulama kodu, takip adresi ve imzacı adları eklendi.
- [x] Giden evrak kayıt formuna gönderim türü seçimi eklendi; KEP seçildiğinde alıcı adresi görünür ve zorunlu hale getirildi, sunucu doğrulaması mevcut KEP yetkisine bağlandı.
- [x] Son PAdES imzacısından sonra aynı kart/PIN oturumuyla MIME hazırlama → CAdES imzalama → PTT KEP sevki otomatik zinciri eklendi; sevk hatasında imzalı evrak korunuyor ve manuel tekrar gönderim yolu açık kalıyor.
- [x] `evrak_eimza_goster=0` firma ayarının yeni PAdES PDF'lerinde görünür imza bandını kaldırması engellendi; gerçek PAdES imzalama kopyasında doğrulama bilgileri zorunlu yapıldı.
- [x] Onaya sunma sonrası yönlendirme Evrak Takip listesine çevrildi; satır aksiyonları `İşlemler` dropdown menüsünde birleştirildi ve sağ tık menüsündeki `İşlem 2` etiketi `PAdES ile İmzala` olarak düzeltildi.
- [x] E-imza durum rozetine tıklanınca solda gerçek evrak PDF'i, sağda imzacı sırası ve kronolojik imza aşamaları bulunan iki panelli önizleme açılması sağlandı.
- [x] Oluşturulan üst yazı PDF'i Dosya sütununa sanal dosya olarak eklendi; onaylı giden evrakta fiziksel ek olmasa da `Üst Yazı.pdf` görünür ve önizlenebilir hale getirildi.
- [x] E-imza doğrulama alanının mevcut kurumsal alt bilgi tasarımını değiştirmemesi sağlandı; adres/iletişim ve `Bilgi için` yerleşimi korunarak yalnız e-imza kutusu ve güvenli imza notu eklendi.
- [x] KEP satırındaki e-imza rozetinin tıklama olayı satır tıklamasından ayrıştırıldı; iki modalın birlikte açılması engellendi.
- [x] Giden KEP evrakları için tip sütunundaki iki ayrı rozet tek mor `GİDEN / KEP` rozetine dönüştürüldü.
- [x] Giden Evrak sayfasındaki alt `KEP / Gönderim Bilgileri` akordeonu kaldırıldı; üstteki gönderim türü ve alıcı KEP alanları korundu.
- [x] İmza önizleme modalına yalnız imza sırası ilgili kullanıcıdaysa görünen gerçek PAdES `E-İmza ile Onayla` aksiyonu eklendi; kart/PIN ve son imza sonrası otomatik KEP akışı bağlandı.
- [x] İmza modalına sağ altta sabit `İade Et` ve `E-İmza ile İmzala` işlem butonları eklendi; İşlemler menüsündeki PAdES etiketi E-İmza olarak değiştirildi.
- [x] PIN alanı imza modalına taşındı; işlem sırasında alanlar disable edilerek PAdES/KEP aşamaları ve yüzdelik ilerleme aynı panelde canlı gösterilir hale getirildi.
- [x] E-imza doğrulama alanındaki tam çerçeve kaldırılarak yalnız alt kalın kırmızı çizgi bırakıldı.
- [x] E-imza alt bilgisinde doğrulama kodu/PIN sol tarafa, belge takip adresi sağ tarafa yerleştirildi; elektronik imzalayan bilgisi kaldırıldı.
- [x] E-imza alt bilgisi özgün referansa yaklaştırıldı; çift kırmızı ayraç tek çizgiye indirildi ve alt bilgi tipografi/boşluk oranları sıkılaştırıldı.
- [x] Giden KEP evraklarının en son PTT delil durumu, Evrak Takip listesinde Tip alanından ayrılarak bağımsız `KEP Durumu` kolonunda gösterildi; firma izolasyonlu toplu Model sorgusu kullanıldı.
- [x] `tests/integration` / `tests/Integration` yol çakışması giderildi ve Linux için Git büyük/küçük harf duyarlılığı etkinleştirildi.
# 2026-10-21 — Mobil SPA yetki erişimi denetimi

- [x] Tüm Vue rotalarının `requiresAuth`, `requiresCompany` ve modül `permission` meta kontrolleri incelendi.
- [x] Oturum API'sinin sunucu kaynaklı yetki haritası ile navbar/“Diğer” filtreleri karşılaştırıldı.
- [x] Mobil modül API'lerinin başlangıç Gate/403 kontrolleri tarandı.
- [x] Doğrulanmış doğrudan yetkisiz sayfa erişimi bulunmadı; üç savunma derinliği/tutarlılık riski raporlandı, kod değişikliği yapılmadı.
- [x] `burakkafadar` Sistem Logları görünürlük hatası canlı rol/yetki kayıtlarıyla doğrulandı: yetki ataması yok, eski/tanımsız `views/logs/list.php` linkinin fail-open menü kontrolü yanlış `logs=true` üretiyor.
- [x] Tanımsız menü/yetki eşleşmeleri fail-closed hale getirildi; mobil sayfa, menü, dashboard ve API erişimleri masaüstü permission anahtarlarıyla eşitlendi.
- [x] `burakkafadar` doğrulamasında Araç Takip, KM Onayları, Puantaj ve Kaçak Kontrol izinli; Sistem Logları ve tanımsız bağlantılar reddedildi. PHP lint, üretim build’i ve public/dist eşitliği geçti.
- [x] Mobil alt navigasyonda `Diğer` butonu daima en sağda olacak şekilde sıralama düzeltildi.
- [x] Personel Puantaj mobil erişimi masaüstündeki `personel/puantaj` yetkisine bağlandı; `puantaj/raporlar` yetkisinin bu sayfayı açması engellendi.
- [x] Mobil SPA'nın tüm Vue rotaları, menü/hızlı işlem öğeleri ve modül API girişleri masaüstü yetkileriyle yeniden denetlendi.
- [x] Giden/Gelen Evrak, Nöbet Onay, Personel düzenleme/alt bölümler ve E-Fatura alt sekmelerindeki geniş yetki eşlemeleri ayrıştırıldı.
- [x] Açık oturumda kaldırılan yetkinin sonraki rota geçişinde etkili olması için oturum yetki haritası ve Gate önbelleği tazelenir hale getirildi.
- [x] Personel detay ve fatura detay API yanıtlarındaki yetkisiz veri alanları sunucu tarafında kapatıldı.
- [x] Tüm mobil PHP API dosyaları lint kontrolünden, Vue production build’i, `git diff --check` ve public/dist eşitlik kontrolünden geçti.
- [x] Puantaj/Raporlar dağılım detayındaki 500 hatası giderildi; `reports.php` ortak PDO bağlantısı geri eklendi ve Ekim 2026 `okuma` dağılım çağrısı gerçek kullanıcı oturumuyla doğrulandı.
# 2026-10-21 — Bağımsız Pazar HTÇ telafisi

- [x] `cron/okuma_htc_telafi.php` tarih/firma parametreli, yalnız CLI erişimli ve tekrar çalıştırılabilir telafi komutu olarak eklendi.
- [x] Migration ön koşulu ve aktif firma seçimi `OkumaHtcAutomationModel` içindeki parametrik PDO metotlarıyla doğrulandı.
- [x] Geçersiz gün doğrulaması, yardım çıktısı, PHP lint, `git diff --check` ve 18 Ekim 2026 idempotent çalışma testi geçti.

# 2026-10-21 — Puantaj Pazar HTÇ kontrolü

- [x] İşlemler menüsüne Pazar HTÇ Kontrolü ve Select2 tarih seçicili detay modalı eklendi.
- [x] Personel bazında dört operasyon sayısı, eşik durumu, mevcut puantaj ve seçim engeli gösterildi.
- [x] Seçili personellere HTÇ atama; firma kapsamı, Gate yetkisi, manuel kayıt koruması, transaction, mükerrer kayıt önleme ve sistem loguyla tamamlandı.
- [x] PHP/JavaScript sözdizimi, `git diff --check`, salt okunur 18 Ekim önizlemesi ve 5 test/13 doğrulamalı HTÇ servis testleri geçti.
- [x] HTÇ atamasındaki eksik `personel_izinleri.onaylayan_id` sütunu hatası giderildi ve INSERT işlemi rollback testiyle doğrulandı.
- [x] Modal checkbox UI'ı yenilendi; toplu seçim ara durumu, sabit tablo başlığı ve bağımsız masaüstü/mobil scroll düzeltildi.
- [x] HTÇ kontrol modalının varsayılan tarihi seçili dönemdeki son tamamlanmış Pazar olarak ayarlandı.
- [x] Otomatik HTÇ kayıtlarının modalda mevcut olup takvimde görünmemesi düzeltildi; onay varyantları, otomatik kayıt kilidi ve atama sonrası takvim yenilemesi tamamlandı.
- [x] Pazar HTÇ modalını açarken oluşan `formatLocalDate is not defined` JavaScript hatası giderildi.

# 2026-10-21 — Menü yönetimi sürükle/bırak hiyerarşi düzeltmesi

- [x] Sıralama kaydında mevcut `parent_id` bağlarının yanlışlıkla yeniden hesaplanması kaldırıldı.
- [x] Ana menü sürüklenirken bağlı alt menülerin aynı blokta kalması sağlandı.
- [x] İçeri alma işlemi açık hedef ebeveynle güncellendi ve üçüncü seviye hiyerarşi engellendi.
- [x] JavaScript sözdizimi ve diff kontrolleri tamamlandı.
# 2026-10-21 — Mobil SPA log kaynak ayrımı

- [x] Mobil SPA API isteklerine merkezi istemci kaynak başlığı eklendi.
- [x] Mobil API işlem logları `SystemLogModel` katmanında `[Mobil SPA]` etiketiyle standartlaştırıldı.
- [x] Yetkili Vue rota ziyaretleri CSRF korumalı ve sunucu whitelist'li sayfa ziyaret loguna bağlandı.
- [x] PHP lint, production build ve public/dist eşleşmesi doğrulandı.

# 2026-10-21 — Personel PWA log aktör adı

- [x] `user_id=0` Personel PWA loglarındaki personel adı Kullanıcı sütununa geriye dönük yansıtıldı.
- [x] Birleşik masaüstü log listesi, Mobil SPA log listesi ve sayfa ziyareti API fallback'i aynı davranışa getirildi.
- [x] PHP lint, diff kontrolü ve gerçek veride iki liste sorgusu başarıyla doğrulandı.

# 2026-10-21 — Sistem Logları kolon sıralaması

- [x] DataTables `order` parametresi API'de güvenli kolon/yön whitelist'iyle işlenir hale getirildi.
- [x] Birleşik Model sorgusu altı görünür kolonda gerçek server-side `ASC/DESC` sıralama yapacak şekilde güncellendi.
- [x] Varsayılan tarih sıralaması optimizasyonu korundu; diğer sıralamalarda eksik aday kümesi sorunu engellendi.
- [x] On iki kolon/yön kombinasyonu canlı sorguyla, dosyalar PHP lint ve diff kontrolüyle doğrulandı.

# 2026-10-21 — Aktivite Dashboard detaylandırması

- [x] Dört yeni trafik ve ziyaretçi KPI kartı eklendi.
- [x] Personel/kullanıcı bazlı ziyaret edilen sayfalar, ziyaret sayıları ve son hareket bilgisi eklendi.
- [x] Popüler sayfalar, benzersiz ziyaretçiler ve platform dağılımı panelleri eklendi.
- [x] Son kritik olaylar paneli ve tüm kritik kayıtlara geçiş eklendi.
- [x] Firma kapsamı, parametrik Model sorguları, HTML escaping, responsive/dark tema stilleri ve boş durumlar uygulandı.
- [x] Gerçek veride dashboard sözleşmesi, PHP lint ve diff kontrolü başarıyla doğrulandı.

# 2026-10-24 — Puantaj SGK raporu pasif personel eşleştirmesi

- [x] Masaüstü SGK onaylanmış ve onay bekleyen rapor sorgularındaki aktif personel kısıtı kaldırıldı.
- [x] Yönetici mobil SGK raporu sorgusunda silinmemiş pasif personeller T.C. hash eşleştirmesine dahil edildi.
- [x] Soft-delete filtresi ve aktif kayıt önceliği korundu.
- [x] Masaüstü ve yönetici mobil puantaj fotoğrafları merkezi personel fotoğraf servisine geçirildi; servis yalnız yönetici fotoğrafını sunuyor.
- [x] Fotoğraf endpoint'ine puantaj yetkileri ve Vue görsellerine reaktif baş harf fallback'i eklendi.

# 2026-10-24 — Mobil bildirim merkezi başlık yerleşimi

- [x] “Tümü Okundu” butonunun dar ekranlarda kapatma düğmesinin altında kalması engellendi.
- [x] 430px ve 350px kırılımlarında kompakt, tek satırlı ve erişilebilir aksiyon görünümü eklendi.

# 2026-10-24 — Personel avatarlarında yönetici fotoğrafı önceliği

- [x] Merkezi personel fotoğraf servisi yalnız `resim_yolu` sunacak şekilde güncellendi.
- [x] Mobil ve masaüstünde doğrudan fotoğraf seçen avatar akışlarından `personel_resim_yolu` fallback'i kaldırıldı.
- [x] Yönetici fotoğrafı bulunmadığında personelin yüklediği fotoğraf yerine baş harf/varsayılan avatar davranışı korundu.
# 2026-10-22 — Personel PWA yıllık izin talep kısıtı

- [x] Hakedişi olmayan personel için yıllık izin seçeneğinin pasifleşmesi kaldırıldı.
- [x] Yıllık izin talebinin kalan bakiyeyi aşmasını engelleyen istemci kontrolü kaldırıldı.
- [x] API tarafındaki yıllık izin hakediş reddi kaldırılırken diğer ücretli izin kısıtı korundu ve birim testle güvenceye alındı.
- [x] Üç günü aşan yıllık izinlerdeki 14 günlük ön bildirim zorunluluğu ve arayüz hatırlatması kaldırıldı; 20 günlük azami talep sınırı korundu.
# 2026-10-24 — Koyu tema DataTables/sidebar yüzey bütünlüğü

- [x] Koyu temada DataTables satır/hücre zemini sidebar yüzey tokenına bağlandı.
- [x] macOS koyu temada tablo ve sidebar ana tonu ortak tema değişkeni üzerinden eşitlendi.
- [x] Slate koyu temadaki gerçek sidebar rengi (`#282f36`) DataTables yüzeyine birebir uygulandı.
- [x] Evrak Takip listesindeki sayfaya özel koyu başlık, filtre ve zebra satır renkleri merkezi sidebar yüzeyiyle eşitlendi.
- [x] Cari liste ve diğer standart DataTables sayfaları için Slate/Dark sidebar seçimine göre gerçek sidebar rengi merkezi tablo yüzeyine aktarıldı.
- [x] Gelir-Gider ve Gelen Faturalar tablolarının başlık, filtre ve gövde yüzeyleri seçili koyu sidebar rengiyle eşitlendi.
- [x] Gelir-Gider ve Gelen Faturalar sayfalarının kart, araç çubuğu ve filtre yüzeyleri de aynı koyu sidebar paletiyle bütünleştirildi.
- [x] Giden Faturalar sayfasındaki genel koyu mod yüzeyleri merkezi sidebar paletiyle uyumlu hale getirildi.
- [x] Standart koyu tema, macOS temasına benzer merkezi ve katmanlı yüzey tokenlarıyla sayfa geneline yayıldı; kartlar, modallar, menüler ve form kontrolleri ortak palete alındı.
- [x] Standart koyu tema katmanları görsel ayrımı belirgin sabit tonlarla güncellendi; canvas, sidebar/kart/tablo, yükseltilmiş menü ve kontrol yüzeyleri net biçimde ayrıştırıldı.
- [x] Sidebar rengi yükseltilmiş alanların `#2b2e34` yüzeyiyle eşitlendi.

- [x] Koyu tema uyum turu: sidebar, topbar, kart, tablo, modal ve satır içi açık renkler tek yüzey sistemine bağlandı (Playwright ile giden-list, gelen-list, bordro, personel, cari, home taraması temiz).

- Hakediş (sozlesme-detay): iş artışı, tamamlanan ve taslak hakediş özet kartları eklendi (HakedisDonemModel::ozet, online-api getHakedisler summary).
- Hakediş sözleşme listesi (hakedisler/index): iş artışı, tamamlanan/taslak hakediş KPI kartları, tablo kolonları ve ilerleme çubuğu eklendi (HakedisDonemModel::ozetToplu).
- Mobil SPA (SozlesmeHakedisView + public/api/sozlesme-hakedis.php contracts_list): 4 KPI kart (güncel hacim/iş artışı, tamamlanan, taslak hakediş) ve sözleşme kartlarında segmentli ilerleme çubuğu eklendi.
# 2026-10-24 — Mobil Kaçak Kontrol fotoğraf galerisi

- [x] Kart fotoğraf badge'i galeri açma işlemine bağlandı.
- [x] Galeri görsellerinin tam ekran büyütülmesi güvenilir indeks tabanlı akışa geçirildi.
- [x] Dokunmatik kaydırma, klavye gezinmesi, sayaç, indirme ve kapatma davranışları tamamlandı.
- [x] Büyük görselin viewport dışına taşması giderildi ve sabit, yüksek kontrastlı kapatma kontrolü eklendi.
- [x] Çok fotoğraflı galeri sheet'inde dokunmatik dikey scroll etkinleştirildi.
# 2026-10-24 — Mobil Nöbet Onay takvim hizalaması

- [x] Aylık takvime Pazartesi başlangıçlı gerçek gün ofseti eklendi.
- [x] Gün numaralarının hafta başlıklarıyla kaymasına neden olan sıfır ofsetli grid düzeltildi.
# 2026-10-24 — Mobil nöbet planlama kayıt düzeltmesi

- [x] `hafta_ici` istemci değeri veritabanındaki `standart` nöbet tipine güvenli biçimde eşlendi.
- [x] Geçersiz nöbet tipi değerleri API katmanında reddedilir hale getirildi.
- [x] Planlama kaydetme aksiyonu 44px dokunma alanlı ana buton ve yükleniyor göstergesi kazandı.

# 2026-10-24 — Personel PWA iPhone kaçak fotoğraf gönderimi

- [x] API POST/dosya yükleme istekleri iOS'ta kopmaya neden olan service worker ağ vekilinden çıkarıldı.
- [x] Güvenilir aktarımda geçici ağ hatası için tek kontrollü yeniden deneme eklendi.
- [x] Service worker sürümü artırılarak güncel kuyruk kodunun cihazlara ulaşması sağlandı.
- [x] JavaScript sözdizimi ve `git diff --check` doğrulamaları geçti.
- [ ] Playwright tarayıcı paketi ortamda kurulu olmadığı için mevcut browser entegrasyon testi çalıştırılamadı; gerçek iPhone doğrulaması gerekli.
- [x] Yönetici mobil ana sayfa: rota tembel yükleme, dashboard stale-while-revalidate önbelleği, zaman damgası sorgusu tekilleştirme ve `personel_hareketleri` indeksi eklendi.
- [ ] Canlı sunucuda indeks SQL'i çalıştırılmalı; `auth.php?action=session` her geçişte zorla yenilenmesi (madde 1) henüz yapılmadı.

## Mobil SPA navbar rozetleri
- badges.php (canlı destek okunmamış + e-fatura gelen/giden "son görülen ID" mantığı), MobilRozetModel, services/badges.js, BottomNav rozeti. SQL: sql/mobil_rozet_durumlari.sql (çalıştırılmalı).
- Rozetler genişletildi: km_approvals, kacak (onay bekleyen), ihbar (yeni), evrak (imzamda bekleyen), requests (avans+izin+talep bekleyen).
- Rozet mantığı "görülmemiş"e çevrildi: KM, kaçak, ihbar, evrak, talepler de son görülen ID ile çalışır; sayfa açılınca düşer (canlı destek hâlâ gerçek okunmamış).

- 2026-10-24: Araç takip Zimmetler ve Araçlar sekmelerine "Zimmet Formu Yazdır" eklendi (api action zimmet-form-yazdir, sql/2026_10_24_arac_zimmet_form_yazdir_permission.sql).

- 2026-10-24: Araç takip Araçlar sekmesi server-side DataTables yapıldı (AracModel::ajaxList/listeBenzersizDegerler, api actions arac-ajax-list + get-unique-values, satır HTML views/arac-takip/arac-satir-render.php, sql/2026_10_24_arac_liste_serverside_policy.sql canlıda çalıştırılmalı). Eski filtre getter metotları excel export için duruyor.

- 2026-10-26: Evrak takip sayfasına PTT KEP gelen kutusu eklendi (KepClientService::paketSorgula/indir/alindiOnay, KepService::previewIncoming/syncIncoming, KepMimeParserService, api actions kep-gelen-onizleme + kep-gelen-aktar, İşlemler menüsünde "KEP Gelen Kutusunu Al"). sql/2026_10_26_kep_gelen_kutusu.sql çalıştırılmalı; gerçek KEP hesabıyla uçtan uca test edilmedi, cron yok (kimlik bilgileri kullanıcı bazlı).

- 2026-10-26: KEP gelen kutusu için PaketSorgula3 yerine PaketSorgula2 kullanıldı (PTT PaketSorgula3 "Geliştirme Aşamasında" dönüyor). Otomatik alım: cron/kep_gelen_sync.php (15 dk), kep_ayarlar.otomatik_senkron_user_id (anahtarı açan kullanıcının kimlik bilgileri kullanılır), api action kep-gelen-otomatik-ayarla; SQL aynı dosyaya eklendi.

- 2026-10-26: Otomatik KEP alım/okundu ayarları KEP ayarları sekmesine taşındı (kep_ayarlar.gelen_alindi_onay_otomatik); okundu önce gönderilir, başarılıysa aktarılır; cron bu ayarları okur.

- 2026-10-26: KEP ayarlarına "otomatik aktarım hesabı" göstergesi ve "Hesabı ben olarak ayarla" eklendi; cron hata verince firma KEP yetkililerine bildirim (6 saatte bir). BildirimModel::sendToFirmAdmins yok, broadcastByPermissionForFirm kullanıldı.

- [x] Gelir-Gider: EFT ücreti + BSMV tek kayıt gösterimi (masaüstü + mobil SPA)

- [x] 2026-10-27 Evrak Takip: işlemler sütunu sticky, KEP iletisi için mobil imza seçeneği (SQL policy scripti çalıştırılmalı, gerçek cihazla test bekliyor).

- [x] 2026-10-27 Mobil imza ayarlari Evrak Takip Islemler modalina tasindi; KEP varsayilan mobil imza kullanicisi eklendi (test bekliyor)
- [x] 2026-10-27 KEP adres dogrulamada test ortami + canli adres uyarisi eklendi (KepClientService::rehberTopluKontrol)
- [x] 2026-10-27 KEP delil XML indirme (GET) yetki hatasi duzeltildi: api.php action GET fallback yalniz kep-delil-indir icin
- [x] 2026-10-27 KEP SmimeGonder icin 45 sn socket timeout, sure logu ve istemci 90 sn timeout mesaji eklendi
- [x] 2026-10-27 KEP delil XML indirme dayanikli hale getirildi (bos onbellek yok sayilir, gzip/zip/BOM, bos icerikte acik hata)
- [x] 2026-10-27 KEP mobil imza reddi (InvalidUserSignature: issuer serial yok) giderildi: ESSCertIDv2 yayinci+seri eklendi, sertifika settings tablosunda onbelleklenir; ilk KEP imzasinda sertifika taninir, bir kez daha imzalama istenir (test bekliyor)
- [x] 2026-10-27 Mobil imza sertifika onbellek anahtari settings.set_name varchar(50) siniri icin kisaltildi (mimza_sert_<32 hex>)
- [x] 2026-10-27 Mobil imza sertifika onbellek karsilastirmasi duzeltildi (hash ham ikili, hash_equals ikinci arguman true)
- [x] 2026-10-27 Mobil SPA: evrak onayi mobil imza ile (mobil-imza-baslat/durum/iptal), sanal e-imza-onayla kapatildi, KEP iletisi mobil imzayla gonderme (kep-hazirla/kep-mobil-imza-baslat/durum), EvrakView.vue arayuzu; dist yeniden derlenmedi, gercek hatla test bekliyor
- [x] 2026-10-27 Mobil SPA KEP formu bottom sheet oldu, adres defterinden secim (kep-adres-defteri) ve Vazgec solda
- [x] 2026-10-27 Mobil SPA: KEP sheet imza istegi sirasinda acik kalir, gonderim sonrasi liste yenilenir (loadList(false) yuklenen-daha-fazla hatasi islem sonrasi cagrilarda loadList(true) yapildi)
- [x] 2026-10-27 Mobil SPA evrak detayinda KEP (gelen/giden) icin Evrak Bilgisi / PTT Delilleri sekmeleri
- [x] 2026-10-27 returnurl: oturum dusunce/beni hatirla ile giris sonrasi kullanici bulundugu sayfaya doner (App/Helper/ReturnUrl.php, layouts/session.php, login.php, set-session.php); oturumsuz AJAX/API 401 + session_expired, assets/js/session-guard.js ile login'e yonlenir; logout.php status beyaz listesi. Tarayicida tam giris akisi (DB kullanicisiyla) test bekliyor
- [x] 2026-10-27 Sistem Aktiviteleri: Cron İşlemleri ayrı hızlı filtre/rozet; kep_gelen_sync.php ve efatura_incoming_sync.php Cron - önekli log yazıyor (tarayıcıda test bekliyor)
- [x] 2026-10-27 Personel listesi doğum tarihi filtre/sıralama düzeltildi; doğum günü push bildirimi (cron + log tablosu SQL) ve ana sayfa Doğum Günleri kartı (personel_listesi yetkisi) eklendi; SQL uygulama, cron kurulumu ve tarayıcı testi bekliyor
- [x] 2026-10-27 Mobil SPA ana sayfa Doğum Günleri takvim kartı (personel yetkisi); dist derlendi, cihazda test bekliyor
- [x] 2026-10-27 Mail & SMS gönderim geçmişi sayfası server-side DataTables + yeni sayfa standardına çevrildi (api/log.php, MesajLogModel::ajaxList/summary/getDetail, policy SQL); tarayıcıda test bekliyor
- [x] 2026-10-27 Mobil SPA Genel Arama (search.php + MobileGlobalSearchModel + MobileSearchAccess + GlobalSearchSheet); sunucu tarafı yetki doğrulandı, dist derlendi; cihazda test ve Pest/Playwright testi bekliyor
- [x] 2026-10-28 Mobil SPA ana sayfa Endeks Karşılaştırma kartı (lazy API, reports yetkisi); dist derlendi, cihazda test bekliyor
- [x] Bildirim tercihleri masaüstü + mobil SPA'da ortak uygulanıyor; rozet ilk yükleme düzeltildi (migration çalıştırılmayı bekliyor).
- [x] 2026-10-28 UETS gelen tebligat çekme sorgu hakkına (2 saatte 1) uyarlandı: tek liste sorgusu + DB önbelleği + 429 engeli, HTTP hata ayrıştırma (UetsApiException), cron yalnız delivered/readtimeout; `sql/2026_10_28_uets_liste_onbellek.sql` çalıştırılmayı ve sunucuda (IP 104.247.167.2) denenmeyi bekliyor
- [x] 2026-10-28 UETS gelen evrak görüntüleme yetkisi (`evrak-takip/uets-goruntule`) + personele sevk zinciri + sevk/erişim tarihçesi (`evrak_sevk`, `evrak_tarihce`); `sql/2026_10_28_uets_evrak_sevk_tarihce.sql` çalıştırılmayı, rollere yetki atanmasını ve tarayıcıda test edilmeyi bekliyor; mobil SPA'da sevk arayüzü henüz yok (sunucu tarafı filtre var)
- [x] 2026-10-28 Okunmamış UETS tebligatları: masaüstü ana sayfa uyarısı, Evrak Takip başlık rozeti + satır "OKUNMAMIŞ" rozeti, mobil SPA ana sayfa kartı (`uets_unread`), mobil alt menü Evrak rozeti, mobil Evrak sevk/tarihçe arayüzü; dist derlendi, tarayıcıda/cihazda test bekliyor

- [x] Araç poliçe (sigorta/kasko) ekleme alanı — kod tamam, SQL uygulanacak, tarayıcı testi bekliyor.
- [x] 2026-10-28 Masaüstü Evrak Takip dosya bağlantıları şifreli UETS/KEP dosyalarını açamıyordu: `pdf.php?type=dosya` (deşifre + firma/yetki kontrollü) eklendi, liste/detay bağlantıları `EvrakTakipModel::fileUrl()` kullanıyor; EYP paket meta dosyaları (.rels, core.xml, .imz vb.) artık ek olarak kaydedilmiyor; tarayıcıda test bekliyor
- [x] 2026-10-28 UETS ile gelen evrak koruması: içerik/dosya/tarih/evrak no değiştirilemez, silinemez (masaüstü `evrak-kaydet` sunucu tarafı alan kilidi + `evrak-sil` engeli, mobil save/delete engeli, arayüzde kilit uyarısı); yalnızca ilgili personel, cevap, termin güncellenebilir ve tarihçeye `duzenleme` yazılır; tarayıcıda test bekliyor
- [x] 2026-10-28 Sevk edilmemiş UETS evrakı hatırlatması: `EvrakSevkModel::remindUnforwarded()` cron/uets_sync.php sonunda çalışır (min 2 saat yaşlı, cevapsız, aktarım başlangıcından sonra; kullanıcı başına 6 saatte bir bildirim + push); sunucuda cron ile test bekliyor
- [x] 2026-10-28 Giden evrak onay tarihçesi: onaya sunma, imza, tamamlanma, imzacı iadesi ve geri alma `evrak_tarihce` tablosuna yazılır (`EvrakTakipModel::recordApprovalHistory`); listede giden evrak İşlemler menüsünde "Onay Tarihçesi" modalı (`evrak-onay-tarihcesi` aksiyonu); `sql/2026_10_28_evrak_onay_tarihcesi.sql` (policy) ve `sql/2026_10_28_uets_evrak_sevk_tarihce.sql` (tablo) çalıştırılmalı; tarayıcıda test bekliyor
- [x] 2026-10-28 KEP gönderimi: kullanıcının kendi KEP kimliği yoksa firma varsayılan hesabıyla gönderim (model fallback parametresi).
- [x] 2026-10-28 Mobil SPA KM Onayları: tarih filtresi (Bugün varsayılan, 7 gün, 1 ay, Tümü, özel aralık) ve AI butonu analiz göstergesi; build alındı, cihazda test bekliyor
- [x] 2026-10-28 Evrak belge doğrulama: kalıcı kod/PIN tablosu, alt bilgide link + QR, herkese açık `belge-dogrula` sayfası (özet + imzalı PDF indirme, rate limit); yerelde curl ile doğrulama/yanlış PIN/429/oturumsuz indirme testleri geçti, canlıda SQL + eski kayıt doldurma bekliyor.

## Mobil Hakediş KPI: Tamamlanan kartı
- Tamamlanan Hakediş kartında Hakediş toplamı, Fiyat farkı toplamı ve Toplam gerçekleşen ayrı gösterilir (sozlesme-hakedis.php summary.tamamlanan_gerceklesen_formatted, SozlesmeHakedisView.vue).

## 2026-10-29 — Mobil SPA ana sayfa izinli personel bottom sheet
- Personel Durumu kartında İzinli kutusu artık bottom sheet açar (ad, departman, izin tipi, başlangıç–bitiş).
- MobileDashboardModel::onLeaveList() (departman kısıtı ve personel yetkisi dashboard.php ile aynı), HomeView.vue sheet, design-system.css .leave-sheet-*.

## 2026-10-29 — Mobil SPA bordro: maaş hesaplama preloader ve seçmeli hesaplama
- PayrollView.vue: "Maaşları Hesapla" artık tam ekran preloader (spinner, kayan ilerleme çubuğu, adım listesi) gösterir; işlem bitene kadar tekrar tetiklenemez.
- Hesaplama kapsamı: Tümü (üst bar), Personel Seç modu ile çoklu seçim ("Seçilenleri Hesapla" dock'u, Görünenleri Seç) ve kart üzerindeki tek personel hesapla butonu. Sunucu (mobile-spa/public/api/bordro.php maas-hesapla) zaten personel_tokens destekliyordu; backend değişmedi.

## 2026-10-29 — Mobil SPA bordro: kart tıklama, İşlemler menüsü, puantaj sheet
- PayrollView.vue, services/bordro.js, public/api/bordro.php güncellendi (kart→detay, Hesapla/İşlemler, puantaj bottom sheet, permissions haritası).

## 2026-10-30 — Mobil SPA Kesme/Açma Karşılaştırması
- Tamamlandı: model metodu, API section, servis, kart tanımı/sıralaması, Vue kartı, build. Tarayıcıda oturumlu görsel doğrulama yapılmadı.

## 2026-10-30 — Merkezi DataTables sütun yöneticisi
- Tamamlandı: modül, CSS, personel geçişi. Chrome ile doğrulandı: personel (gizle, sürükle, genişlik, yenileme sonrası kalıcılık, sıfırlama), cari, kullanıcı, demirbaş, araç takip. gelir-gider/efatura/bordro kendi yapısıyla devam ediyor.

## 2026-10-30 — Mobil SPA renk paletleri
- Tamamlandı: palet tokenları, tercih servisi, profil seçici, build alındı. Cihaz/tarayıcıda görsel doğrulama bekliyor.

- [x] Personel PWA navbar yönetici mobil dock görünümüne getirildi (2026-10-30).

- [~] Personel PWA header standardı: İcralarım örnek sayfa yapıldı; kalan sayfalar bekliyor.

- [x] Personel PWA header standardı tüm sayfalara uygulandı; Roboto Condensed geçişi tamamlandı (2026-10-30).

- [x] Personel PWA ikon + preloader + koyu özet kartı (2026-10-30). Kalan: Performans Özeti (JS ile çizilen) kartları hâlâ açık tema.

- [x] Personel PWA Diğer menüsü yönetici mobil stili (2026-10-30).

- [x] Personel PWA alt bar özelleştirme + menü araması (2026-10-30). Tercih cihaz/tarayıcı bazlı (cookie).

- [~] Personel PWA sayfa tasarımı: Talepler, İzin, Nöbet, Yardım, KM, İhbar, Bordro sekmeleri tamam; Zimmetler/Kaçak/Aparat detay kartları kaldı.

- [x] Personel PWA ana sayfa kartları sürükleyerek sıralanabilir (migration uygulanmalı).
