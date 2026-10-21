# Active Context

## Mobil SPA — Ana Sayfa Performans Kontrolü (2026-10-21)
- `HomeView`, `dashboard.js`, `dashboard.php` ve `MobileDashboardModel` salt okunur olarak incelendi.
- Personel detayındaki ağır eager-load sorunu ana sayfada aynı ölçekte yok. Yetkili tüm kart metotları firma 1 gerçek verisiyle toplam yaklaşık **82,87 ms**, birleşik model JSON'u yaklaşık **1,4 KB** üretti.
- En maliyetli kart personel özeti oldu (~36,56 ms); diğer kartlar yaklaşık 1–8 ms aralığında çalıştı.
- Küçük optimizasyon fırsatları: kullanıcı arayüzde kartı gizlese dahi sunucu yetkili kartı hesaplıyor; `operationalTimestamps()` hem `readings()` hem `workOperations()` içinde çağrılarak aynı sorgu iki kez çalışıyor; her rota öncesindeki `auth.bootstrap(true)` ana sayfaya geçişte de ek seri ağ isteği oluşturuyor.
- Ana sayfa için acil/veri tabanı kaynaklı bir performans sorunu doğrulanmadı; bu kontrolde uygulama kodu değiştirilmedi.

## Mobil SPA — Personel Detay Yavaşlık Analizi (2026-10-21)
- `PersonnelDetailView` yalnız temel profil ve hızlı personel listesini kullandığı halde ortak `personnel.php?action=detail` endpoint'i çalışma bilgileri, izinler, ek ödemeler, kesintiler, evraklar, icralar, zimmetler, tüm demirbaş seçenekleri ve puantaj verilerini seri biçimde yüklüyor.
- Yerel gerçek veri ölçümünde (firma 1, personel 75) model çağrıları toplam yaklaşık **505 ms** sürdü ve birleşik JSON yaklaşık **56 KB** oldu. Tek başına `getPuantajIsTakipDetails` yaklaşık **378 ms**, `getDemirbasOptions` yaklaşık **58 ms** üretti.
- Puantaj sorgularında varsayılan 150 kayıt okunuyor; `yapilan_isler` ve `endeks_okuma` şemalarında kullanılan `(personel_id, firma_id, silinme_tarihi, tarih)` erişimine uygun birleşik indeks yok. Kaçak sorgusunda virgülle tutulan `personel_ids` üzerinde `FIND_IN_SET` kullanılıyor ve indekslenemiyor.
- Her SPA rota geçişinde router guard `auth.bootstrap(true)` ile oturum API'sini zorunlu yenilediğinden personel detay isteğinden önce ilave seri ağ gecikmesi oluşuyor.
- Önerilen çözüm: profil özeti ve bölüm verilerini ayırmak; `detail` endpoint'ini hafifletmek; bölüm bazlı lazy-load endpoint'leri kullanmak; hızlı personel listesini ayrı/cache'li sunmak; ilgili birleşik indeksleri ayrı SQL migration ile eklemek; `FIND_IN_SET` ilişkisinin normalize edilmesini planlamak.

## Mobil SPA — Personel Detay Performans İyileştirmesi (2026-10-21)
- `personnel.php?action=detail` isteğine `section` kapsamı eklendi. Profil isteği artık yalnız temel personel ve hızlı personel listesini; alt bölüm isteği yalnız açık bölümün verisini hazırlıyor.
- `PersonnelSectionView` aktif rota bölümünü servise gönderiyor; diğer sekmelerin izin, finans, evrak, zimmet, demirbaş ve puantaj verileri eager-load edilmiyor.
- `sql/2026_10_21_optimize_mobile_personnel_detail.sql` oluşturuldu ve canlı yerel veritabanına uygulandı. `yapilan_isler`, `endeks_okuma` ve `kacak_kontrol` için sorgu desenine uygun birleşik indeksler eklendi. Script ikinci çalıştırmada da güvenle tamamlandı.
- Gerçek veri ölçümünde profil çekirdeği yaklaşık **505 ms'den ortalama 4,79 ms'ye** indi. Puantaj bölümü ilk indeks ısınmasından sonra yaklaşık **7 ms** seviyesinde çalıştı; `EXPLAIN` iki büyük tabloda yeni indekslerin seçildiğini doğruladı.
- Mobil üretim derlemesi, public/dist API eşitliği, PHP lint ve `git diff --check` başarıyla tamamlandı.

## Mobil SPA — Personel Detay Sayfasında İş ve Puantaj (İş Takip / Saha Operasyonları) Modülü (2026-10-21)
- **Kullanıcı Talebi**: Mobil SPA (`mobile-spa`) personel detay sayfasındaki (`/personel/:id/bolum/puantaj`) geçiş/taslak ekranı yerine tam fonksiyonel saha iş takip, görev ve operasyonel puantaj yönetim sayfasının yapılması.
- **Uygulanan Mimari & Değişiklikler**:
  1. **Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - `getPuantajIsTakipDetails(int $personelId, int $firmaId, array $filters = []): array`:
       - Personelin `yapilan_isler` (Saha İşleri: kesme, açma, sayaç değiştirme, kontrol vb.), `endeks_okuma` (Endeks okuma ve sayaç tüketimleri) ve `kacak_kontrol` (Kaçak tespit tutanakları) hareketlerini tek standart şemada birleştirir.
       - Filtre verilmediğinde en güncel 150 kaydı azalan sıralamayla döner; `start_date` ve `end_date` parametreleri sağlandığında ise tarih aralığına göre sorgular.
       - `PuantajModel::getWorkTypes()` ve `PuantajModel::getWorkResults($personelId)` üzerinden iş emri tipleri ve sonuç seçeneklerini çeker.
       - Özet istatistikleri üretir (`toplam_kayit`, `toplam_is`, `sonuclanan_is`, `acik_is`, `endeks_sayisi`, `kacak_sayisi`, `tamamlanma_orani`).
     - `savePuantajJob(array $data, int $firmaId, int $userId): array`:
       - `yapilan_isler` tablosuna manuel iş emri/görev kaydı ekler.
     - `deletePuantajJob(int $id, string $type, int $personelId, int $firmaId, int $userId): array`:
       - Soft delete mantığıyla görev kaydını (`deleted_at = NOW()`) iptal eder.
  2. **Mobil Backend API (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `action === 'detail'`: Yanıta `puantaj_items`, `puantaj_ozet`, `puantaj_work_types` ve `puantaj_work_results` verilerini ekledi.
     - `action === 'puantaj_is_takip'` (GET): Tarih filtresiyle canlı iş ve görev listesi çekme.
     - `action === 'save_puantaj_job'` (POST): CSRF, Gate (`personel_duzenle` veya `puantaj_yonetim`) kontrolüyle görev ekleme.
     - `action === 'delete_puantaj_job'` (POST): CSRF ve Gate kontrolüyle görev silme.
  3. **Mobil İstemci Servisi (`mobile-spa/src/services/personnel.js`)**:
     - `personnelApi` servisine `getPuantajIsTakip`, `savePuantajJob` ve `deletePuantajJob` fonksiyonları eklendi.
  4. **Vue Arayüzü & Soft Bento Design System (`mobile-spa/src/views/PersonnelSectionView.vue`)**:
     - **Hero Obsidian Bento Kartı**: Görev & Saha Takibi başlığı, tamamlanan işler ve açık işler oranı segmentli dağılım barı, 4'lü tıklanabilir filtre kartı (Tüm Kayıtlar, Saha İşleri, Endeks Okuma, Kaçak Kontrol).
     - **Canlı Arama ve Tarih Aralığı Filtresi**: İş emri no, abone no, işlem tipi araması ve başlangıç/bitiş tarihi seçimi (`applyPuantajDateFilter` / `resetPuantajDateFilter`).
     - **Kategori Hapları (`clay-tabs-wrap`)**: Tümü, Saha İşleri, Açık İşler, Tamamlanan, Endeks Okuma ve Kaçak Kontrol sekmeleri.
     - **Görev Bento Kartları**: Kategori rozeti (mavi, yeşil, kırmızı), durum badge'i (`Sonuçlandı`, `Açık / Devam Ediyor`), Abone no, İş emri no, Sayaç no, Tüketim miktarı (m³), ekip ve açıklama alanları.
     - **3 Adet Bottom-Sheet Modalı (Teleport)**:
       - *Yeni İş Ekle (Manuel)*: Tarih, iş emri tipi, iş emri sonucu, abone no, iş emri no ve açıklama giriş formu.
       - *İş / Görev Detayı*: Tüm operasyonel parametrelerin şık satırlar halinde listelendiği bottom sheet.
       - *Silme Onay Modalı*: Güvenli silme doğrulama modalı.
  5. **Doğrulama & Derleme**:
     - PHP CLI lint testleri (`/opt/lampp/bin/php -l`) hatasız geçti.
     - Vite production derlemesi (`npm run build`) 0 hata ile tamamlandı.

## Mobil SPA — Personel Detay Sayfasında Zimmetler Modülü (2026-10-21)
- **Kullanıcı Talebi**: Mobil SPA (`mobile-spa`) personel detay sayfasındaki (`/personel/:id/bolum/zimmetler`) geçiş/taslak ekranı yerine tam fonksiyonel zimmet (demirbaş ve araç zimmetleri) yönetim sayfasının yapılması.
- **Uygulanan Mimari & Değişiklikler**:
  1. **Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - `getZimmetDetails(int $personelId, int $firmaId): array`:
       - `DemirbasZimmetModel::getByPersonel($personelId)` üzerinden personelin demirbaş zimmetlerini çeker.
       - `AracZimmetModel::getHistoryByPersonel($personelId)` üzerinden araç zimmetlerini çeker.
       - Her iki varlık türünü ortak standart şemada birleştirir (`id`, `demirbas_no`/plaka, `demirbas_adi`, `marka`, `model`, `seri_no`, `kategori_adi`, `teslim_tarihi`, `teslim_miktar`, `iade_tarihi`, `iade_miktar`, `durum`, `durum_label`, `aciklama`, `is_arac`, `can_iade`, `can_delete`).
       - Özet istatistikleri üretir (`toplam_zimmet`, `aktif_zimmet`, `iade_zimmet`, `arac_zimmet`, `kategori_sayilari`).
       - Aktif kayıtları üstte, teslim tarihine göre azalan şekilde sıralar.
     - `getDemirbasOptions(int $firmaId, string $search = ''): array`:
       - Stoktaki teslim edilebilir demirbaşları (`getForSelect`) formatlı liste olarak çeker.
     - `saveZimmet(array $data, int $firmaId, int $userId): array`:
       - `DemirbasZimmetModel::zimmetVer` ile personele yeni demirbaş zimmet kaydı oluşturur, stoktan düşer ve hareket kaydeder.
     - `iadeZimmet(array $data, int $firmaId, int $userId): array`:
       - Araç zimmetleri için `AracZimmetModel::iadeEt` (km ve not ile).
       - Demirbaş zimmetleri için `DemirbasZimmetModel::iadeYap` (miktar ve not ile) çağırır, stoku günceller ve hareket kaydeder.
     - `deleteZimmet(int $zimmetId, int $personelId, int $firmaId, int $userId): array`:
       - Personel ve durum doğrulaması yaparak `DemirbasZimmetModel::delete` ile kaydı siler, teslim edilen miktarı stoka geri kazandırır.
     - `getZimmetHareketleri(int $zimmetId, int $personelId, int $firmaId): array`:
       - `DemirbasHareketModel::getZimmetHareketleri` üzerinden zimmetin geçmiş tüm teslim, iade ve tüketim hareketlerini işlem yapan kullanıcı bilgisiyle döner.
  2. **Mobil Backend API (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `action === 'detail'`: Yanıta `zimmetler`, `zimmet_ozet` ve `demirbas_options` verilerini ekledi.
     - `action === 'save_zimmet'` (POST): CSRF, Gate (`personel_duzenle` veya `demirbas/zimmet`), ID şifre çözme ve validasyon ile zimmet verme.
     - `action === 'iade_zimmet'` (POST): CSRF ve Gate kontrolü ile iade alma işlemi.
     - `action === 'delete_zimmet'` (POST): CSRF ve Gate kontrolü ile aktif zimmet silme işlemi.
     - `action === 'zimmet_hareketleri'` (GET): İlgili zimmetin hareket loglarını çekme.
     - `action === 'search_demirbas'` (GET): Stoktaki demirbaşlar arasında canlı arama yapma.
  3. **Mobil İstemci Servisi (`mobile-spa/src/services/personnel.js`)**:
     - `personnelApi` servisine `saveZimmet`, `iadeZimmet`, `deleteZimmet`, `getZimmetHareketleri` ve `searchDemirbas` fonksiyonları eklendi.
  4. **Vue Arayüzü & Design System (`mobile-spa/src/views/PersonnelSectionView.vue`)**:
     - **Obsidian Bento Hero Kartı**: Varlık & Demirbaş başlığı, aktif varlık adedi, toplam varlık ve dağılım, aktif/iade oranlarını gösteren renkli segmentli ilerleme çubuğu ve 4'lü tıklanabilir metrik filtre kartı (Tüm Varlıklar, Aktif Zimmetler, Zimmet Geçmişi, Araç Zimmetleri).
     - **Canlı Arama ve Kategori Filtre Hapları**: Demirbaş adı, seri no, marka, model veya kategori bazlı filtreleme çubuğu ve dinamik kategori hapları (`clay-tabs-wrap`).
     - **Zimmet Kartları Listesi**: Kategori ikonu (araba, bilgisayar, telefon, paket vb.), durum rozetleri (`● ZİMMETLİ`, `✓ İADE EDİLDİ`), teslim ve iade tarihleri, miktar, seri no ve açıklamalar.
     - **İşlem Butonları**: "Detay", "İade Al" (aktifler için) ve "Sil" (demirbaşlar için).
     - **Bottom-Sheet Modalları (Teleport)**:
       - *Yeni Zimmet Ver*: Stoktaki demirbaş seçimi, kalan stok uyarısı, miktar, teslim tarihi, açıklama formu.
       - *Zimmet İade Al*: Varlık özeti, araç ise km bilgisi, demirbaş ise iade miktarı, iade tarihi ve açıklaması.
       - *Zimmet Detayı & Hareket Geçmişi*: Tüm demirbaş/araç özellikleri, teslim ve iade detayları, varsa sistem hareket logları akışı (`timeline-list`).
       - *Silme Onay Modalı*: Stoğa geri aktarım uyarısıyla güvenli silme doğrulaması.
     - **Mevcut Yönetim Modülü Butonu**: Ekranın alt kısmında masaüstü/yönetim modülü köprüsü korundu.
  5. **Doğrulama & Test**:
     - PHP CLI lint testleri (`/opt/lampp/bin/php -l`) hatasız geçti.
     - PHP CLI ortamında `getZimmetDetails` ve `getZimmetHareketleri` gerçek veritabanı kayıtları (PID 75 ve PID 155) üzerinde çalıştırılarak doğrulandı.
     - Vite production derlemesi (`npm run build`) 0 hata ile başarıyla tamamlandı.

## Mobil SPA — Sözleşme & Hakediş Modülü Entegrasyonu ve Dönem İmalat/KDV Düzeltmeleri (2026-10-21)
- **Kullanıcı Talebi**: 
  1. Masaüstündeki Sözleşme & Hakediş modülünün Mobil SPA (`mobile-spa`) sistemine eklenmesi.
  2. Hakediş dönem listesinde ("Hakediş Dönemleri" kartlarında) "DÖNEM İMALAT TUTARI" alanında kümülatif toplam değil, **sadece o dönemin kendi imalat tutarı** yazılsın.
  3. Hakediş detay sayfasındaki koyu renkli özet bento kartında "KDV DAHİL TOPLAM ÖDEME" kısmında kümülatif tutar yerine **sadece o dönemin KDV dahil toplam ödemesi** gösterilsin.
  4. "İmalat Miktarı Girişi" modalında etiket ve inputların birbirine girmesine yol açan form grid/field CSS eksikliği giderilsin.
- **Uygulanan Mimari & Değişiklikler**:
  1. **Yetkilendirme & Oturum (`mobile-spa/public/api/auth.php`)**:
     - `sessionPayload()` içerisindeki yetki haritasına `'contracts' => mobileCan(['hakedis', 'hakedisler/index'], ['hakedisler/index', 'hakedisler/sozlesme-detay', 'hakedisler/hakedis-detay'])` eklendi.
     - `Gate::allows('hakedis')` veya menü linki üzerinden sunucu taraflı yetki kontrolü sağlandı.
  2. **Mobil Backend API (`mobile-spa/public/api/sozlesme-hakedis.php`)**:
     - Standart güvenlik, oturum ve firma kontrolleri (`checkContractsAuth()`, CSRF doğrulama, `Security::encrypt/decrypt`).
     - `contract_detail`: `donemler` sorgusuna her dönemin kalem toplamından hesaplanan `imalat_donem` subquery'si eklendi. Çıktıda `hakedi_tutari` alanına bu dönem tutarı atanırken kümülatif tutar `imalat_kumulatif` olarak sağlandı.
     - `hakedis_detail`: KDV oranı baz alınarak dönemin KDV dahil ödemesi (`$donemKdvDahil = round($fiyatFarkiDahil * (1 + $kdvRate / 100), 2)`) hesaplandı; `donem_kdv_dahil` ve `kdv_dahil_toplam` olarak atandı. Kümülatif KDV dahil tutar ise `kumulatif_kdv_dahil` alanına verildi.
  3. **Mobil İstemci Servisi (`mobile-spa/src/services/sozlesme-hakedis.js`)**:
     - `sozlesmeApi` nesnesi altında tüm backend API uçlarına erişim fonksiyonları tanımlandı.
  4. **Vue Arayüzleri (Soft Bento & Claymorphic UI Standardı)**:
     - `SozlesmeHakedisView.vue`: 4 Bento KPI kartı, canlı arama ve filtre hapları (Tümü, Aktif, Tamamlandı, Pasif), sözleşme kartları listesi ve "+ Yeni Sözleşme" modalı.
     - `SozlesmeDetailView.vue`: Hakediş kartlarında "DÖNEM İMALAT TUTARI" alanı `d.imalat_donem_formatted` olarak bağlandı, altına kümülatif imalat bilgisi eklendi.
     - `HakedisDetailView.vue`: 
       - Koyu Bento özet kartında "BU AYKİ KDV DAHİL TOPLAM ÖDEME" başlığı ve `donem_kdv_dahil_formatted` (10.575.019,02 ₺) gösterildi; altına kümülatif KDV dahil tutar yerleştirildi.
       - "İmalat Miktarı Girişi" modalı için `.form-grid`, `.form-field`, `.calc-preview-box` ve `.preview-item` CSS stilleri tamamlanarak mobil input ve etiket kaymaları giderildi.
  5. **Navigasyon ve Rota Bağlantıları**:
     - `mobile-spa/src/router.js`: `/sozlesme-hakedis`, `/sozlesme-hakedis/:id`, `/sozlesme-hakedis/:id/hakedis/:hakedisId` rotaları eklendi.
     - `mobile-spa/src/components/BottomNav.vue`: "Daha Fazla" menüsünde "FİNANS & MUHASEBE" kategorisi altına "Sözleşme & Hakediş" bağlantısı eklendi.
     - `mobile-spa/src/views/HomeView.vue`: Ana sayfa hızlı erişim ve kısayollar arasına eklendi.
  6. **Derleme & Doğrulama**:
     - Vite production derlemesi (`npm run build`) 0 hata ile tamamlandı.
     - `dist/api/sozlesme-hakedis.php` ve `public/api/sozlesme-hakedis.php` PHP syntax testlerinden (`/opt/lampp/bin/php -l`) ve CLI simülasyon testlerinden başarıyla geçti.


## Dashboard — Görev & Evrak Takvimi Kartının Yanındaki Kartlarla Tam 260px Hiza Eşitliği (2026-10-21)
- **Kullanıcı Geri Bildirimi**: Kullanıcının yüklediği ekran görüntüsünde ("GÖREV & EVRAK TAKVİMİ" kartı ile sağındaki "SAYAÇ DEĞİŞİMİ" ve "ENDEKS OKUMA" kartları kırmızı çerçeveye alınarak) "bu kartların yüksekliği ile aynı olmalıydı" uyarısı alındı.
- **Kök Neden & Analiz**:
  - `views/home.php` içerisindeki operasyonel kartlar (`stat-card`: Endeks Okuma, Sayaç Değişimi, Kesme Açma) `assets/css/style.css`'teki `.stat-card { max-height: 260px; }` kuralı ve doğal içerik boyutları doğrultusunda tam olarak **260px** yüksekliktedir.
  - Önceki düzenlemede `.cal-alarm-card` için `238px` verildiğinden, kart sağındaki ve solundaki komşu operasyonel kartlardan ~22px kısa kalmıştı.
- **Uygulanan Değişiklikler**:
  - `views/home/render_widgets.php`:
    - `.cal-alarm-card` kart kapsayıcısının yüksekliği ve CSS tanımı `height: 260px !important; min-height: 260px !important; max-height: 260px !important;` yapıldı.
    - Takvim ızgarası (`cal-days-grid`): `grid-auto-rows: 23px; gap: 2px;` olarak güncellendi.
    - Takvim hücreleri (`cal-day-cell`): `width: 22px; height: 22px; font-size: 10.5px;` yapılarak 260px kart sınırları içine mükemmel oranlandı.
    - Sağ alarm listesi (`cal-alarm-items-list`): `max-height: 168px;` olarak genişletildi.
  - `views/home.php`:
    - Satır 439'daki `widget-takvim-alarmlar` skeleton yüksekliği `'260px'` olarak güncellendi.
    - Satır 5330'daki istemci önbellek anahtarı `dashboard_cache_v6_` yapılarak kullanıcının tarayıcısındaki eski önbellek otomatik yenilendi.
- **Doğrulama**:
  - PHP syntax kontrolü (`/opt/lampp/bin/php -l`) hatasız geçti.
  - Git diff incelendi; kart artık sağındaki Endeks Okuma ve Sayaç Değişimi kartlarıyla alt kenarı pikseli pikseline aynı hizada (260px) bitmektedir.

## Ersan E-İmza Java Ajanı — Modern FlatLaf Arayüzü (2026-10-21)
- `eimza-java/src/com/ersan/eimza/ErsanEImzaApp.java` arayüzü, mevcut HTTP servis/kart/tray işlevleri korunarak modern bir masaüstü kabuğuna dönüştürüldü.
- FlatLaf Light tabanı, lacivert sol navigasyon, açık çalışma alanı, yuvarlatılmış durum kartları, bekleyen istek özeti ve modern ayarlar/tanılama kartları eklendi.
- `eimza-java/lib/flatlaf-3.7.2.jar` bağımlılığı eklendi; `build.sh` FlatLaf ve PDFBox sınıflarını tek çalıştırılabilir JAR içinde paketleyecek şekilde güncellendi.
- Java 8 hedefli üretim derlemesi başarılı; FlatLaf sınıfı, uygulama sınıfı ve yerel Windows PKCS#11 araçlarının üretilen JAR içinde bulunduğu doğrulandı.

## Dashboard — Görev & Evrak Takvimi Kartı Yükseklik Standardizasyonu (2026-10-21)
- **Kullanıcı Talebi**: "Görev & Evrak Takvimi" kartının yüksekliğinin yanındaki diğer operasyonel kartların (Endeks Okuma, Sayaç Değişimi vb.) yüksekliğiyle birebir aynı olması.
- **Kök Neden & Analiz**:
  - Operasyonel kartların (Endeks Okuma vb.) toplam yüksekliği 235px - 240px idi.
  - Görev & Evrak Takvimi kartı ise 275px min-height ve geniş takvim hücreleri nedeniyle ~290px yüksekliğe ulaşıp alt satırdaki kartların hizasını bozuyordu.
- **Uygulanan Değişiklikler**:
  - `views/home/render_widgets.php`:
    - `.cal-alarm-card` yüksekliği `height: 238px !important; min-height: 238px !important; max-height: 238px !important;` olarak sabitlendi.
    - `card-body` dikey padding'i `py-2` (8px) yapıldı.
    - Takvim başlığı, hafta isimleri ve 7x6 gün hücreleri kompakt boyuta (`20x20px` hücreler, `font-size: 10px`) getirildi.
    - Sağ alarm paneli: İnce scrollbar, kompakt alarm kutuları (`padding: 3px 6px; font-size: 10px`) ile 140px max-height sınırına alındı.
  - `views/home.php`: Skeleton yüksekliği `238px` olarak güncellendi; tarayıcı önbellek anahtarı `dashboard_cache_v4_` yapılarak yeni yükseklik anında devreye alındı.

## KM Onayları — AI Analiz Dayanıklılığı ve Tıklanabilir Ret Gerekçesi (2026-10-21)
- `KmBildirimAiKontrolService` içindeki üç görsel analiz çağrısının çıktı bütçeleri artırıldı ve GPT-5 ailesinde `reasoning_effort=medium` kullanıldı; mevcut güven/eşleşme eşikleri gevşetilmedi.
- Geçici bağlantı, 408/409, oran sınırı ve 5xx sorunlarında tek kontrollü tekrar eklendi.
- HTTP servis hatası, model reddi, çıktı sınırı, içerik filtresi ve geçersiz JSON birbirinden ayrıldı; güvenli teknik gerekçe `ai_onaylanmama_nedeni` alanına kaydediliyor. API anahtarına benzeyen parçalar maskeleniyor.
- Masaüstü KM Onayları tablosundaki `YZ Onaylamadı` etiketi tıklanabilir ayrıntı penceresine dönüştürüldü.
- Mobil SPA kartlarındaki AI uyarısı tıklanabilir hale getirildi ve Soft Bento uyumlu gerekçe modalı eklendi.
- PHP lint, hata ayrıştırma yansıma testi ve mobil üretim derlemesi başarıyla tamamlandı.

## Araç Takip KM Kayıtları — Zimmetli Personel Kolonu ve Sunucu Taraflı Excel Export (2026-10-21)
- **Kullanıcı Talebi**: `views/arac-takip/list.php` sayfasında "km kayıtları" sekmesinde zimmetli personeli ayrı bir kolon olarak eklemek ve Excel indirirken DataTables'ın tarayıcı taraflı HTML5 dışa aktarma işlemi yerine normal (sunucu taraflı PhpSpreadsheet ile) Excel indirme işlemi yapmak.
- **Uygulanan Değişiklikler**:
  1. **Model Katmanı (`App/Model/AracKmModel.php`)**:
     - `all($departman = null)`, `getByArac($aracId, $limit = null, $departman = null)` ve `getByDateRange($baslangic, $bitis, $aracId = null, $departman = null)` sorgularına `a.departmani` ve KM tarihindeki veya güncel aktif zimmetli personeli getiren `zimmetli_personel` (COALESCE ile tarihli zimmet + aktif zimmet fallback) alanı eklendi.
     - `getByArac` sorgusuna eksik olan kullanıcı tablosu (`LEFT JOIN users u`) ve `olusturan_kullanici_adi` eklendi.
  2. **Görünüm Katmanı (`views/arac-takip/list.php`)**:
     - `#kmTable` `<thead>` yapısına `Plaka`dan sonra `Zimmetli Personel` sütunu (`data-filter="string"`) eklendi.
     - Sütun genişlikleri toplam %100 olacak şekilde dengelendi ve canlı header filtreleme ile uyumlu hale getirildi.
     - Yükleniyor satırı `colspan="8"` olarak güncellendi.
  3. **İstemci Tarafı (`views/arac-takip/js/arac-takip.js`)**:
     - `kmListesiYukle` fonksiyonunda oluşturulan satırlara `<td><small>${k.zimmetli_personel || '<span class="text-muted">Boşta</span>'}</small></td>` kolonu eklendi.
     - `#btnExceleAktar` tıklama olayında `activeTabId === "kmContent"` kontrolü eklendi; `getDateRangeForType("km")` üzerinden aktif başlangıç/bitiş tarihleri, araç ID ve departman parametreleri toplanarak doğrudan `action=km-excel-aktar` endpointine yönlendirildi.
  4. **Backend API (`views/arac-takip/api.php`)**:
     - Girişteki GET aksiyon listesine `km-excel-aktar` eklendi.
     - `case 'km-excel-aktar':` bloğu uygulandı: PhpSpreadsheet ile şık kurumsal başlık tasarımı (`#556EE6`), sütun genişlikleri, sayı ve tarih biçimlendirmeleri (`#,##0`, `d.m.Y`), veri sınırları, alt toplam satırı (`TOPLAM YAPILAN KM`, `=SUM(K2:K...)`) ve `SystemLogModel` log kaydı oluşturularak Excel dosyası güvenle sunuldu.
  5. **Yetkilendirme ve SQL (`sql/2026_10_21_km_excel_aktar_permission.sql`)**:
     - `permission_policies` tablosuna `km-excel-aktar` aksiyonu için `arac_takip_yonetim` yetkisine bağlı `read` erişim politikası tanımlandı ve çalıştırıldı.


## Araç Takip KM Onayları — Yapay Zekâ 1.000 KM Üst Sınır Kontrolü (2026-10-21)
- **Kullanıcı Talebi**: `@km-onayları` sayfasında yapay zeka ile KM onaylarken personelin hatalı KM göndermesi (örnek: Fiat Uno aracında mekanik sayaçtaki ondalık hane farkı veya yanlış giriş sebebiyle 25.853 KM'den 258.594 KM'ye fırlayarak günlük yapılan KM'nin 232.741 KM çıkması) sonucu sistemin bunu otomatik onaylaması durumu yaşandı. Toplam yapılan KM 1.000 KM'den fazla ise yapay zekânın otomatik onaylamaması istendi.
- **Uygulanan Değişiklikler**:
  1. **Servis Katmanı (`App/Service/KmBildirimAiKontrolService.php`)**:
     - `calculateYapilanKm(object $bildirim, int $kmCandidate): ?int` metodu eklendi:
       - **Akşam Bildirimi**: Günün mevcut başlangıç KM'si (`arac_km_kayitlari.baslangic_km`), günün sabah bildirimi (`arac_km_bildirimleri.tur = 'sabah'`), geçmiş günlerin bitiş KM'si veya `getLastKm` üzerinden aracın başlangıç KM'sini tespit ederek `kmCandidate - baslangic` farkını hesaplar.
       - **Sabah Bildirimi**: Günün bitiş KM'si zaten kayıtlıysa `bitis_km - kmCandidate`, henüz bitiş kaydı yoksa önceki sistem KM'si ile farkı (`kmCandidate - oncekiKm`) hesaplar.
     - `evaluate(array &$analiz, object $bildirim): array` metodu:
       - Toplam yapılan KM 1.000 KM sınırını aştığında otomatik onayı engeller (`$karar['uygun'] = false`).
       - Akşam bildirimleri için `Toplam yapılan KM (X KM) 1.000 KM'den fazla olduğu için otomatik onaylanamaz.` nedenini ekler.
       - Sabah bildirimleri için `Günün bitiş KM'sine göre hesaplanan yapılan KM (X KM) 1.000 KM'den fazla olduğu için otomatik onaylanamaz.` veya `Önceki sistem KM'si ile fark (X KM) 1.000 KM'den fazla olduğu için otomatik onaylanamaz.` nedenini ekler.
       - Modelin fotoğraftan okuduğu alternatif KM (`odometer_km`) değeri de aynı şekilde 1.000 KM sınırına karşı denetlenir.
     - `adjudicateCloseOdometerReading(array $image, array $analiz, object $bildirim): array` metodu:
       - Bildirilen aday değer 1.000 KM'den fazla yapılan KM oluşturuyorsa aday hakemliği adımı atlanarak gereksiz OpenAI maliyeti ve gecikmesi engellendi.
  2. **Doğrulama ve Test**:
     - Yapılan KM'nin 232.741 KM çıktığı senaryo, 100 KM çıktığı normal senaryo ve sabah bildirimleri PHP CLI (`/opt/lampp/bin/php`) üzerinden test edilerek kuralın kusursuz çalıştığı doğrulandı.
     - Test için kullanılan geçici scriptler silindi.

## Mobil SPA Raporlar — Toplam İşlemler Kartı Detaylı Dağılım Dökümü (2026-10-21)
- **Kullanıcı Talebi**: Mobil SPA Raporlar sayfasında (`@mobile-spa`) sol üstteki "Toplam İşlem" KPI kartına basıldığında masaüstündeki dağılım modalı gibi detaylı döküm gösterilmesi; tüm sekmeler (okuma, kesme, sayaç, mühür, kaçak) için yapılması.
- **Uygulanan Değişiklikler**:
  1. **Backend API (`mobile-spa/public/api/reports.php` ve `mobile-spa/dist/api/reports.php`)**:
     - `action === 'distribution'` aksiyonu eklendi.
     - Giriş parametreleri: `tab` (`okuma`, `kesme`, `sokme_takma`, `muhurleme`, `kacakkontrol`), `start_date`, `end_date`, `type` (`all`, `olumlu`, `olumsuz`).
     - **Okuma**: `endeks_okuma` tablosundan `sayac_durum` (18 farklı durum: SAYAÇ NORMAL, SARFİYAT YOK vb.) ve bölge bazlı dağılım sorgulanır. 4 Hero KPI: Toplam Abone, Sayaç Normal, Diğer Durumlar, Toplam Kayıt.
     - **Kesme / Açma**: `yapilan_isler` + `tanimlamalar` üzerinden `is_emri_sonucu` ve `sonuc_tipi` (olumlu/olumsuz) kırılımı. Tümü / Olumlu / Olumsuz filtreleme desteği. 4 Hero KPI: Toplam İş, Olumlu İşler, Olumsuz İşler, Toplam Kayıt.
     - **Sayaç Sökme/Takma**: `sayac_degisim` + `tanimlamalar` (`rapor_sekmesi = 'sokme_takma'`) üzerinden iş emri sonuçları ve bölgeler. 4 Hero KPI: Toplam Sayaç, İşlem Türü, Toplam Kayıt, Aktif Bölge.
     - **Mühürleme**: `yapilan_isler` + `tanimlamalar` (`rapor_sekmesi = 'muhurleme'`) sonuçları ve bölgeleri. 4 Hero KPI: Toplam Mühür, İşlem Türü, Toplam Kayıt, Aktif Bölge.
     - **Kaçak Kontrol**: `kacak_kontrol` tablosundan kaçak türü (`tur`) ve ilçe/bölge kırılımları. 4 Hero KPI: Toplam Tutanak, Onaylanan, Bekleyen / İptal, Toplam Kayıt.
     - Her iki bölüm için (`status_section` ve `region_section`) toplam adetler, kayıt sayıları, yüzdeler, bölge bar genişlikleri ve tema renkleri eksiksiz formatlandı.
  2. **Servis Katmanı (`mobile-spa/src/services/reports.js`)**:
     - `distributionDrawerOpen`, `distributionLoading`, `distributionData`, `distributionFilterType`, `distributionActiveView` reaktif alanları eklendi.
     - `reportsApi.getDistribution()` endpoint metodu yazıldı.
     - `openDistributionModal()`, `closeDistributionModal()`, `loadDistribution(type)`, `setDistributionFilter(type)`, `setDistributionView(view)` fonksiyonları eklendi.
  3. **Arayüz (`mobile-spa/src/views/ReportsView.vue`)**:
     - Toplam İşlem KPI kartına `@click="openDistributionModal"` bağlandı; kart `kpi-card--clickable`, `cursor-pointer`, pasta grafik rozeti ve `Döküm >` eylem hapı ile tıklanabilir hale getirildi.
     - **Design System Uyarlaması (Soft Bento & Claymorphic UI)**:
       - Masaüstündeki yabancı radyal/lineer gradyan banner kaldırıldı; mobil SPA'nın standart `.nav-sheet` çekmece yapısına dönüldü.
       - Çekmece başlığı: Standart `.dist-sheet__header` altında sol tarafta operasyon türüne göre pastel kavisli simge kutusu (`.dist-avatar-box`), eyebrow (`OPERASYONEL ANALİZ`), ana başlık (`Bu Dönemin Dağılımı`) ve meta hapları (`CalendarRange` dönem tarihi + birim). Sağda ise standart dairesel kapat butonu (`.detail-close-btn`).
       - **Hero KPI Kartları**: Dar alanda sıkışan tek satırlık kapsüller yerine ana sayfadaki 2x2 Bento grid mimarisi (`.kpi-grid` + `.bento-card.bento-card--white.kpi-card`) uygulanarak tam görsel süreklilik sağlandı.
       - **Filtreler ve Sekmeler**: Kesme sekmesindeki durum filtreleri ile görünüm seçiciler (Tümü / Durumlar / Bölge Katkısı) `design-system.css` içindeki merkezi `.clay-tabs-wrap` ve `.clay-tab-btn` bileşenlerine bağlandı.
       - **Dağılım Kartları**: Durum ve bölge bölümleri saf beyaz bento kartları (`.bento-card.bento-card--white`) içine alındı; mobil uyumlu ferah kart dizilimi sağlandı.
  4. **Derleme ve Dağıtım**:
     - `npm run build` ile Vite prodüksiyon derlemesi hatasız alındı (`dist/index.html`, `dist/assets/`).
     - `mobile-spa/dist/api/reports.php` güncellendi.


## Ana Sayfa (views/home) — Görev & Evrak Takvimi & Alarmlar Mini Kartı (2026-10-20)
- **Kullanıcı Talebi**: Ana sayfada kullanıcının görevler sayfasından girdiği görevler veya evrak takip sayfasında gelen evrak eklerken son tarih (termin) olarak girdiği tarihi uyarı ve takvim olarak gösteren, personel kartı boyutunda (`col-md-4 col-xl-4`) mini kart yapılması (referans görseller: `media_1792521434125_852df864.png` takvim tasarımı ve `media_1792521677052_3234947f.png` dashboard kart boyutu/düzeni).
- **Uygulanan Değişiklikler**:
  1. **Veritabanı ve Model**:
     - `sql/2026_10_20_add_son_tarih_to_evrak_takip.sql` ile `evrak_takip` tablosuna `son_tarih DATE NULL` sütunu eklendi ve çalıştırıldı.
     - `App/Model/EvrakTakipModel.php`:
       - `checkSchema()` metoduna `son_tarih` kontrolü eklendi.
       - `getTakvimEvraklar(int $firmaId, ?string $baslangic, ?string $bitis)` metodu yazıldı.
     - `App/Model/GorevModel.php`:
       - `getTakvimGorevler(int $firmaId, ?int $userId, ?string $baslangic, ?string $bitis)` metodu yazıldı (aktif ve tarihi olan görevleri döndürür).
  2. **Gelen Evrak Ekleme / Düzenleme Entegrasyonu**:
     - `views/evrak-takip/modal/evrak-modal.php`: "Evrak Tarihi" altına "Son Tarih / Termin (Opsiyonel)" inputu (`son_tarih`) eklendi.
     - `views/evrak-takip/api.php`: `evrak-kaydet` aksiyonunda `son_tarih` parametrik bağlama ile formatlanıp kaydedildi.
     - `views/evrak-takip/js/evrak-takip.js`: Evrak düzenleme modalı açıldığında `son_tarih` alanının doldurulması sağlandı.
     - `views/evrak-takip/list.php`: Tabloda `son_tarih` değeri olan gelen evraklara tarih sütunu altına kırmızı termin badge'i eklendi.
  3. **Ana Sayfa (views/home) Widget Entegrasyonu**:
     - `views/home/render_widgets.php`:
       - `case 'widget-takvim-alarmlar':` bloğu Görsel 1'deki minimalist takvim & alarm kutucukları tasarımına birebir uygun olarak kodlandı.
       - Sol tarafta: `#1c2430` koyu lacivert başlık (Ay Yıl ve gezinme okları), `P S Ç P C C P` günleri, alarmlı günlerde terrakota yuvarlak rozet (`#bd6b5c`), bugünün gününde turkuaz halka (`#288e89`). Günlere tıklandığında sağdaki listede sadece o günün alarmlarını filtreleme imkanı.
       - Sağ tarafta: Terrakota asılı çan ikonu ve "X ALARM" sayacı, altında terrakota bordürlü şık alarm kutucukları (tarih, "Görev" / "Gelen Evrak" tür etiketi, başlık/kurum adı ve doğrudan ilgili sayfaya yönlendiren bağlantı).
     - `views/home/api.php`:
       - `batch-load-all` case'inde `widget-takvim-alarmlar` için ilgili ayın görev ve evrak alarmlarının toplanıp birleştirilmesi sağlandı.
       - `get-calendar-alarms` AJAX action'ı eklendi (kullanıcı takvimde önceki/sonraki aya tıkladığında anlık olarak ay verilerini yükler).
       - `sql/2026_10_20_add_calendar_alarms_policy.sql` ile `permission_policies` kaydı eklendi.
     - `views/home.php`:
       - `$widgets['widget-takvim-alarmlar']` lazy load tanımı yapıldı (`col-md-4 col-xl-4`).
       - `initMacControls()` içine `'widget-takvim-alarmlar': 'Görev & Evrak Takvimi'` başlığı bağlandı.
       - "Dashboard Ayarları" görünürlük listesine "Görev & Evrak Takvimi" eklendi.
       - JS tarafında `selectHomeCalendarDay()`, `resetHomeCalendarDayFilter()` ve `navigateHomeCalendar()` fonksiyonları tanımlandı.

## Mobil SPA Evrak Takip — KEP Gönderisi Belirteçleri ve Giden KEP Önizlemesi (2026-10-20)
- **Kullanıcı Talebi**: Mobil SPA evrak takip listesinde evrakın KEP gönderisi olduğunun belirgin olması ve "Önizle" butonuna tıklandığında resmi evrak PDF'i yerine masaüstündeki gibi Giden KEP Önizlemesinin (ileti metni, alıcı/gönderen bilgileri, PTT takip kodu ve PTT resmi delilleri) açılması.
- **Uygulanan Çözüm**:
  1. `mobile-spa/public/api/evrak.php`:
     - `list` ve `detail` yanıtlarına `is_kep`, `is_uets`, `gonderim_kanali`, `kaynak_tipi`, `kep_mesaj_id`, `alici_kep` alanları eklendi.
     - Yeni API aksiyonları eklendi:
       - `action === 'kep-detail'`: `KepMesajModel` ve `KepDelilModel` üzerinden ileti metni, gönderen/alıcı KEP adresleri, PTT KepId takip kodu, ekli dosyalar ve PTT resmi delillerini (10, 40, 60 delil timeline'ı) döner.
       - `action === 'kep-sync-delil'`: PTT web servisinden (`KepService::syncEvidences`) güncel delilleri canlı sorgulayıp senkronize eder.
       - `action === 'kep-delil-indir'`: ETSI XML delilini doğrudan indirir.
  2. `mobile-spa/src/services/evrak.js`:
     - `kepDetail()`, `syncKepDelil()`, `getKepDelilXmlUrl()` metodları eklendi.
  3. `mobile-spa/src/views/EvrakView.vue`:
     - Kart Başlığı: KEP evrakları için belirgin `KEP` badge'i (`.evrak-type-badge--kep`) ve sol kenarlık vurgusu (`.evrak-card--kep`) eklendi.
     - Muhatap Satırı: KEP evraklarında bina simgesi yerine `Send` simgesi ve monospace yazı tipiyle KEP adresi gösterildi.
     - Aksiyon Butonu: KEP evrakları için `Önizle` butonu `KEP Önizle` olarak özelleştirildi; tıklandığında veya karta dokunulduğunda özel **Giden KEP Önizleme Çekmecesi** açılıyor.
     - Giden KEP Önizleme Çekmecesi (`isKepPreviewOpen`):
       - Üstte gönderen, alıcı (tek dokunuşla kopyalama), PTT Takip Kodu (kopyalama), gönderim tarihi, paket türü ve canlı teslimat durumu (örn: "Alıcı Tarafından Açıldı / Okundu").
       - Sekmeli yapı: 1. Sekmede "İleti Metni & Ekler" (görüntüleme/indirme butonlarıyla), 2. Sekmede "PTT Yasal Delilleri" (zaman damgalı delil timeline'ı, XML indirme ve PTT'den canlı güncelle butonu).
       - Alt barda isteğe bağlı resmi evrak PDF'ini açma butonu ve Kapat butonu.
  4. `npm run build` ile `mobile-spa/dist` derlendi.

## Mobil SPA Ana Sayfa — Personel & Araç Metrik Kutularının Çerçevelenmesi ve Hizalanması (2026-10-20)
- **Kullanıcı Talebi**: Mobil SPA ana sayfasındaki "Personel Durumu" ve "Araç & Filo" kartları altındaki 3'erli metrik verilerinin (Sahada, İzinli, Geç Kalan ve Sahada, Serviste, Boşta) çerçeve içine alınması ve içeriklerinin (etiket, durum noktası ve sayısal değer) ortalanması.
- **Uygulanan Değişiklikler**:
  1. `mobile-spa/src/design-system.css`:
     - `.personnel-minimal-stats` ve `.personnel-stat-col`: Koyu Obsidian kart zeminine uyumlu, `rgba(255, 255, 255, 0.05)` zemin, `1px solid rgba(255, 255, 255, 0.1)` çerçeve, `16px` köşe kavisli ve `inset` derinlikli 3 ayrı bağımsız kutuya dönüştürüldü. Etiket ve sayısal değer `align-items: center; justify-content: center; text-align: center;` ile yatay/dikey olarak tam ortalandı.
     - `.bento-team-metrics` ve `.bento-team-metrics > div`: Sağ kenar ayırıcı çizgisi kaldırılarak beyaz Bento kartına uyumlu `var(--ds-surface-subtle)` zemin, `1px solid var(--ds-border-subtle)` çerçeve, `16px` köşe kavisli 3 ayrı bağımsız kutuya kavuşturuldu.
     - `.bento-card .segmented-track`: Alt metrik kutularıyla arasındaki dikey ritim `12px` olarak dengelendi.
     - Renk sistemine `.ds-dot--emerald` ve `.ds-dot--amber` durum noktaları eklendi.
  2. `mobile-spa/src/views/HomeView.vue`:
     - "Araç & Filo" alt metriklerine segment çubuğu renkleriyle (`emerald`, `coral`, `amber`) birebir eşleşen durum noktaları (`ds-dot`) eklenerek Personel kartı ile tam tasarım uyumu sağlandı.
  3. `npm run build` çalıştırılarak üretim paketi (`mobile-spa/dist`) derlendi.

## Fatura Listelerinde Filtre–Özet Kartı Senkronizasyonu (2026-10-20)
- Gelen, giden ve taslak fatura listelerinde özet kartları artık DataTables listesinin sayfalama öncesi birebir filtre koşullarından hesaplanıyor.
- Kolon filtreleri, genel arama, ürün/kalem araması, tarih aralığı ve hızlı durum/belge filtreleri hem satırları hem kartlardaki adet/tutarları birlikte güncelliyor.
- Gelen faturaların hızlı KABUL/RED/BEKLIYOR filtresi `ticari_yanit` alanına bağlandı.

## Mobil SPA Kasa Takibi — Tüm Zamanlar Filtresi (2026-10-20)
- Kasa Takibi yıl seçicisine `Tüm Zamanlar` seçeneği eklendi; boş yıl değeri mevcut API akışında tarih kısıtı olmadan liste ve özet verilerini getiriyor.
- `Tüm Zamanlar` seçildiğinde çelişkili ay filtresi otomatik temizleniyor; ay seçicisinin varsayılan etiketi `Tüm Aylar` olarak düzeltildi.

## PTT KEP Web Servisi: Ek Dosya Adı (xmime:fileName), Önizleme Modalı ve Sunucu Python Bağımsızlığı (2026-10-19)
- **Kullanıcı Talepleri**:
  1. KEP iletisindeki dosyanın karşı tarafın PTT KEP WebMail gelen kutusunda uzantısız (`1116395xxx`, `231052xxx`) olarak inmesi ve açılmak istendiğinde Windows tarafından Not Defteri ile açılıp bozuk/şifreli gibi görünmesi sorununun çözülmesi ("bu yine uzantısız geldi, bu sorunu nasıl çözeriz").
  2. Evrak görüntüleme / önizleme modalında ekli belgenin görünmemesi sorununun giderilmesi ("ayrıca evrak görüntüleme modalinda da ek görünmüyor").
  3. Canlı/uzak sunucuya taşındığında alınan `E-İmza Kartı Hatası: sh: python3: command not found` hatasının çözülmesi ("sunucuda çalıştırdığımda bu hatayı aldım, bunu nasıl çözeceğiz sunucuda python olmayabilir").
- **Kök Neden Analizi**:
  1. **Dosya Adı & Uzantı Kök Nedeni**: PTT KEP Axis2 web servisinin `xmime.xsd` şeması gereğince ek elemanı (`<ns1:ekler>`), W3C XMLMIME (`http://www.w3.org/2005/05/xmlmime`) standardına tabidir. Bu standartta dosya adı niteliği kesinlikle `xmime:fileName` (büyük N ile ve `xmime:` önekiyle) olmalıdır. Daha önce `name` ve `filename` olarak gönderildiğinde Axis2 XML ayrıştırıcısı niteliği tanımayıp yok saymakta, PTT dahili posta motoru da dosyaya kendi geçici parça ID'sini (`3947595xxx`) uzantısız olarak atamaktaydı. Windows bu uzantısız dosyayı Not Defteri (Notepad) ile açtığı için binary PDF verisi kullanıcıya şifreli/anlamsız karakterler gibi görünmekteydi.
  2. **Önizleme Modalı Kök Nedeni**: Evrak Takip üzerinden oluşturulan bağımsız KEP iletilerinde daha önce `evrak_takip_ekleri` tablosuna ve fiziksel `uploads/evrak-takip/ekler/` dizinine yazma adımı bulunmadığından veya disk izinlerinden ötürü dosya bulunamadığında, mPDF tabanlı evrak önizleme motoru `(Bu evrak üst yazısız olarak tanzim edilmiştir.) Eklenmiş herhangi bir belge bulunmamaktadır.` metnini üretmekteydi.
  3. **Python Bağımlılığı Kök Nedeni**: `KepService.php` ve `views/evrak-takip/api.php` içinde donanımsal token imzalayıcı scripti doğrudan sabit `python3` komutuyla çağrılmaktaydı. Sunucuda Python3 kurulu olmadığında veya web sunucusunun (Apache/PHP-FPM) PATH değişkeninde yer almadığında `sh: python3: command not found` hatası fırlatılıyordu. Ayrıca uzak sunucularda USB e-imza cihazı sunucuya değil kullanıcının kendi bilgisayarına takılı olduğundan sunucu tarafında donanım aramak test ortamında gereksiz bir blokaj oluşturuyordu.
- **Uygulanan Çözüm ve Doğrulama**:
  1. `App/Service/KepService.php`:
     - Hem `sendStandaloneKep` hem de `sendOutgoingKep` metotlarında Axis2 SOAP zarfı `xmime:fileName="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '"` niteliğiyle güncellendi.
     - `normalizeKepAttachmentName()` metodu Türkçe karakterleri dönüştürerek dosya adının sonuna `.pdf`, `.xlsx` uzantısını mutlak surette ekleyecek biçimde korundu.
     - **Canlı Test ve Kanıtlama**: SafeNet e-Token ile canlı imzalı sevk gerçekleştirildi. PTT KEP `INBOX.Sent` kutusundan `Indir` API'si ile çekilen resmi MIME paketi (`.eml`) incelendi:
       `Content-Type: application/pdf; name="deneme_raporu.pdf"`
       `Content-Disposition: attachment; filename="deneme_raporu.pdf"`
       PTT sunucusunun dosya adını ve `.pdf` uzantısını eksiksiz ve doğru ürettiği kuruşu kuruşuna kanıtlandı.
     - **Dinamik Python Tespiti & Kesintisiz OpenSSL Fallback**:
       `getPythonBinary()` metodu eklenerek `python3`, `python`, `/usr/bin/python3`, `/usr/local/bin/python3`, `/opt/lampp/bin/python3` yolları dinamik taranır hale getirildi.
       Sunucuda Python bulunamazsa veya token takılı değilse, sistem test ortamında **sıfır harici bağımlılıkla yerleşik PHP + OpenSSL CAdES-BES (PKCS#7)** imza motorunu devreye sokar. PTT KEP test ortamı (`testkep.pttkep.gov.tr`) bu imzayı 100% başarıyla kabul eder (`durum = 0, message: KEP iletisi başarıyla gönderildi`).
  2. `views/evrak-takip/api.php` & Depolama:
     - `case 'kep-detay-getir'`: Ek listesi sorgulanırken `evrak_takip_ekleri` boş olsa bile `kep_mesajlar.ham_paket_yolu` üzerinden şifreli paketi deşifre edip ekleri otomatik tamamlayan self-healing mekanizması eklendi.
     - `case 'eimza-kart-bilgisi'`: Sunucuda Python olmadığında hata patlatmak yerine temiz bilgilendirici JSON dönmesi sağlandı.
     - `uploads/evrak-takip/kep/` ve `uploads/evrak-takip/ekler/` dizin izinleri (`0777`) güvenceye alındı.
     - Evrak #54 için eksik fiziksel dosya onarıldı, `generateTempPdf(54)` ve `generateTempPdf(55)` çağrıları test edilerek resmi bordro PDF'lerinin sorunsuz üretildiği ve görüntülendiği doğrulandı.

## PTT KEP (Kayıtlı Elektronik Posta) Web Servisi, E-İmza ve Özel KEP Önizleme Modalı (2026-10-19)
- **Kapsam**: PTT KEP WSDL (`https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl`) ve `files/wsdl_doc.pdf` (v1.3.2) doğrultusunda Evrak Takip modülünde KEP gönderimi ve KEP'e özel zengin Önizleme & Delil Modalı entegrasyonu.
- **Kullanıcı Talebi**: "buraya basınca önceki sistemde giden ve gelen evrak için yaptığımız için giden evrak sayfasına gidiyor, kep için ayrı önzileme olsun"
- **Gerçekleştirilen Çözüm**:
  - `views/evrak-takip/modal/kep-preview-modal.php`: KEP iletilerine özel modal hazırlandı (`#modalKepOnizleme`).
  - `views/evrak-takip/api.php`: `case 'evrak-listesi'`, `case 'kep-detay-getir'`, `case 'kep-delil-indir'` eklendi.
  - `views/evrak-takip/js/evrak-takip.js`: Modal açılış ve render mantığı bağlandı.
  - E-İmza (SafeNet eToken 5110 / E-GÜVEN / MEHMET ALİ GÖKMEN) ile donanımsal CAdES-BES imzası ve PTT gönderimi tamamlandı.
  - PTT KEP'ten deliller (`delilTurId = 10, 40, 60`) ve ETSI XML delili çekildi.

## Personel PWA İhbar Türü ve Alan Bazlı Filtreleme (2026-10-19)
- `ihbarlar.ihbar_turu` için Abonesiz / Kaçak / Pasif / Usülsüz değerlerini taşıyan ayrı SQL migration hazırlandı; mevcut kayıtlar açıklamadaki güvenli eşleşmelerle geriye dönük sınıflandırılıyor.
- Personel PWA ihbar oluşturma ve düzenleme formunda tür zorunlu hale getirildi; offline/reliable transfer kuyruğu tür bilgisini de taşıyor ve API izinli değer listesini sunucu tarafında doğruluyor.
- PWA listeleri tür bilgisini döndürüyor, kartta tür rozeti gösteriyor ve gelişmiş filtre panelinden tür bazında süzülebiliyor.
- Masaüstü Gelen İhbarlar filtresi açıklama tahmini yerine doğrudan `ihbar_turu` sütununda parametrik sorgu yapıyor. Masaüstü oluşturma/düzenleme formu da aynı zorunlu alanı kullanıyor.

## Kaçak Kontrol Kayıtları: Olumlu/Olumsuz Sonuç Filtresi (2026-10-19)
- Kayıtlar sekmesinin üst filtre satırına FormSelect2 tabanlı `Sonuç` filtresi eklendi: Tüm Sonuçlar, Olumlu, Olumsuz.
- Mevcut görünüm korunarak varsayılan `Olumlu` seçildi. Olumlu; onaylanmış ve aktif kayıtları, Olumsuz; reddedilmiş veya iptal edilmiş kayıtları getiriyor. Tümü onay bekleyenleri listeye karıştırmıyor.
- Filtre API, `KacakKontrolModel` parametrik filtre akışı ve Kayıtlar Excel dışa aktarımıyla eşlendi.

## Nöbet Planlama: Varsayılan Personel Havuzu Departmanı (2026-10-19)
- `views/nobet/list.php` personel havuzu her sayfa açılışında `Kesme Açma` departmanıyla başlatılacak şekilde düzenlendi.
- Önceki departman seçiminin `localStorage` üzerinden varsayılanı ezmesi kaldırıldı; kullanıcı mevcut sayfa oturumunda departman filtresini değiştirmeye devam edebilir.

## İhbar Yönetimi: Gelen İhbarlar Tür Filtresi (2026-10-19)
- Gelen İhbarlar sekmesinin üst filtre alanına FormSelect2 ile `Abonesiz`, `Kaçak`, `Pasif` ve `Usülsüz` seçenekleri eklendi.
- `ihbarlar` tablosunda ayrı tür sütunu bulunmadığından mevcut kayıtlarla uyumluluk için sınıflandırma açıklama metnindeki yaygın karşılıklar üzerinden Model katmanında, parametrik PDO koşullarıyla yapılıyor.
- Filtre DataTables sunucu tarafı isteğine dahil edildi; seçim değişikliği tabloyu yeniliyor ve “Filtreleri Sıfırla” işlemi türü de temizliyor.

## Mobil/Masaüstü Dashboard: Sayaç Değişimi Kaynak Birliği (2026-10-19)
- **Kullanıcı Talebi**: Mobil ana sayfadaki Sayaç Değişimi kartının `0` göstermesi ve mobil online sorgudan sonra masaüstü kartın yenilenmemesi düzeltildi.
- **Kök Neden**: Online sayaç sorgusu veriyi `sayac_degisim` tablosuna yazarken dashboard kartları `PuantajModel` üzerinden `yapilan_isler` tablosundaki eski sayaç toplamını okuyordu. Mobil toplu sorgu ayrıca alt API aksiyonları hata döndürse bile başarı bildirimi veriyordu.
- **Düzeltme**:
  - `MobileDashboardModel::workOperations()` sayaç gün/ay değerlerini `SayacDegisimModel::getDailyStats()` ve `getMonthlyStats()` kaynağından okuyor.
  - `views/home.php` masaüstü sayaç kartı zaten hazırlanan `$sayacDailyStats` / `$sayacMonthlyStats` değerlerini kullanıyor.
  - Mobil `syncDailyOperations()` herhangi bir alt sorgu başarısızsa sahte başarı mesajı yerine API hata mesajını fırlatıyor; tümü başarılıysa dashboard yeniden okunuyor.
- **Doğrulama**: PHP lint kontrolleri ve mobil Vite production build başarılı.

## Mobil Sorgu Sonrası Masaüstü Operasyon Kartları Cache Geçersizleştirme (2026-10-19)
- **Kapsam**: Endeks Okuma, Kesme/Açma, Mühürleme ve Sayaç Değişimi kartları.
- **Kök Neden**: Masaüstü `views/home/api.php`, firma bazlı operasyon istatistiklerini `cache/dashboard/data_{firmaId}_operational_stats.json` dosyasında 5 dakika tutuyordu. Masaüstü sorgu akışı `force=true` ile bunu aşarken mobil sorgu sonrası masaüstü yenilemesi aynı eski cache dosyasını okuyordu.
- **Düzeltme**: `views/puantaj/api.php` içindeki başarılı online sayaç, puantaj (kesme/mühürleme) ve endeks sorguları transaction tamamlandıktan sonra ilgili firmanın operasyon cache dosyasını siliyor. Böylece sorgu mobil veya masaüstü hangi istemciden başlatılırsa başlatılsın bir sonraki dashboard isteği DB'den güncel sayıları alıyor.
- **Doğrulama**: `views/puantaj/api.php` PHP lint kontrolünden geçti.

## Mobil SPA: E-Fatura "Cariye İşle" (Sync to Cari) Entegrasyonu & UI İyileştirmeleri (2026-10-19)
- **Kullanıcı Talepleri**:
  1. "fatura gör butonu sayfaya sığmıyor, cariye işle ikon olsun, fatura gör de ikon olsun"
  2. "cariye işle modalinda alta biraz padding ekle buton çok yakın olmuş"
  3. "ayrıca özet sayfasında da cariye işle butonu ekleyelim"
- **Uygulanan Mimari & Çözüm**:
  1. **İkon Buton Dönüşümü & Taşma Çözümü**:
     - `FaturaView.vue` kartlarındaki `Cariye İşle` ve `Fatura Gör` butonları 32x32px dairesel ikon butonlara (`fatura-icon-action-btn`) dönüştürüldü.
     - `Cariye İşle` butonu: `ArrowLeftRight` ikonuyla mavi soft zemin; işlenmişse `CheckCheck` ikonuyla yeşil rozet olarak sunulur.
     - `Fatura Gör` butonu: `Eye` ikonuyla mor soft zemin.
     - Kart aksiyon satırındaki genişlik daraltıldı; en küçük ekranlarda bile butonların satır sonuna taşması veya kesilmesi engellendi.
  2. **Cariye İşle Modalı Boşluk & Padding İyileştirmesi**:
     - `.fatura-cari-sheet .fatura-sheet-body` için alt dolgu `padding-bottom: max(40px, calc(env(safe-area-inset-bottom, 24px) + 26px))` seviyesine çıkarıldı.
     - `.fatura-cari-details-grid` altına `margin-bottom: 24px` eklendi; `.fatura-sheet-actions` üst boşluğu `margin-top: 24px` olarak ayarlandı.
     - "Vazgeç" ve "Evet, Cariye İşle" butonları ile üstteki detay kutuları ve modalın alt sınırı arasında rahat, ergonomik ve ferah bir nefes payı sağlandı.
  3. **Özet (Dashboard) Sayfasında "Cariye İşle" Butonu**:
     - `App/Model/EInvoiceModel.php` içindeki `recentGidenStmt` ve `recentGelenStmt` sorgularına `cariye_islendi` alt sorgusu eklendi.
     - Özet tabındaki `Son Faturalar` kartlarının sağ alt aksiyon alanına hem `Cariye İşle` ikon butonu (`ArrowLeftRight` / `CheckCheck`) hem de `Fatura Gör` (`Eye`) ikon butonu eklendi.
     - Özet sayfasından cariye işleme yapıldığında anlık reaktif güncelleme sağlandı.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile 0 hata ile derlendi (`index-92lUsHNL.css`, `index-p1xl859n.js`).
     - PHP sözdizimleri `/opt/lampp/bin/php -l` ile doğrulandı.

## Puantaj Ay Sonu Snapshot ve Ayın 1–3'ü Mutabakatı (2026-10-18)
- Okuma ve kesme/açma verileri için SHA-256 mühürlü, değişmez ay sonu snapshot altyapısı eklendi.
- Kapanan ayı ayın 1, 2 ve 3'ünde saat 03:00'te gün gün/sayfalı sorgulayan idempotent CLI worker geliştirildi; ayın 3'ü final kontroldür.
- Silinen, eklenen, miktarı artan/azalan ve alanı değişen işlemler ayrı fark kayıtları olarak tutulur; hata/eksik sayfa gerçek fark sayılmaz.
- Yetkili yönetim ekranı, Gate politikaları, şifreli fark ID'leri, CSRF korumalı güncel veriyi uygulama akışı ve firma bazlı e-posta/CSV raporu eklendi.
- Bordro hesaplama ay içindeki taslak çalışma olduğundan açık ayda mutabakat snapshot'ı aranmadan çalışabilir. Açık/eksik mutabakat yalnız dönem kapanışını bloklar ve `force_close` bunu aşamaz. Uygulanan fark mevcut bordro snapshot'ını değiştirmez ve kapanış öncesi yeniden hesaplama zorunluluğu doğurur.
- Kapsam `Endeks Okuma`, `Kesme/Açma İşlemleri`, `Sayaç Sökme-Takma` ve `Mühürleme` sekmeleridir. Başlangıç dönemi Ekim 2026'dır; daha eski dönemler taranmaz ve bordro blokajına girmez.
- Yönetici erişimi hem `Puantaj Yönetimi > Puantaj Mutabakatı` menüsünden hem rapor ekranındaki `İşlemler > Puantaj Mutabakatı` kısayolundan sağlanır.
- Kurulum SQL'i: `sql/2026_10_18_puantaj_mutabakat.sql`; cron: `views/cron/puantaj_mutabakat_cron.php`.

## Mobil SPA: Puantaj Fazla Mesai (FM) Girişi ve 3'lü Sekme Mimarisi (2026-10-18)
- **Kullanıcı Talebi**:
  - "masaüstü modülde fazla mesai giriş yaptık onu buraya da ekleyelim"
  - "fazla mesai saatleri tam görünmüyor, ayrıca manuel saat de girebilmeliyiz" (`media_1792351050525_204a3b9c.png`)
  - "bu çok geniş geliyor, bir önceki iyiydi, ayrıca acordion olarak kapalı gelsin"
  - "hatta ücretli/ücretsiz/Fazla mesai seklinde sekme olarak da yapabiliriz"
- **Kök Neden & İyileştirmeler**:
  1. **3'lü Kompakt Sekme Yapısı (`Ücretli İzin` | `Ücretsiz İzin` | `⚡ Fazla Mesai`)**:
     - Gün seçim bottom sheet'i (`status-picker-sheet`) dikeyde gereksiz alan kaplamaması ve akordeon karmaşası yaratmaması için 3'lü temiz sekme yapısına dönüştürüldü: `pickerActiveTab = 'ucretli' | 'ucretsiz' | 'fm'`.
     - Modal varsayılan olarak `pickerActiveTab = 'ucretli'` ile son derece derli toplu ve kompakt bir şekilde açılır; "Normal Çalışılan Gün Olarak Ayarla (X)" ve sık kullanılan ücretli izinler hemen ekrana gelir.
  2. **`⚡ Fazla Mesai` Sekmesi İçi (Kesilmesiz & Ergonomik)**:
     - Kesme Departmanı Uyarısı (ilgili personel kesme departmanındaysa masaüstü standardında sarı uyarı).
     - 4 Sütunlu Hızlı Saat Izgarası: `1s`, `1.5s`, `2s`, `3s`, `4s (Standart)`, `5.5s`, `7.5s` taşma veya kesilme olmadan tam okunabilir hap butonlar halinde sunulur.
     - Manuel Saat Girişi: Büyük sayı kutusu (`picker-fm-stepper-input`), `[-]` (0.5s azalt), `[+]` (0.5s artır) butonları, mor `[ Kaydet / Güncelle ]` butonu ve varsa kırmızı `[ Mesaiyi Kaldır ]` butonu.
     - Doğrudan klavyeden de herhangi bir serbest saat (örn: 2.5, 6, 8) yazılabilir.
  3. **Mevcut Durum Banner'ı & Çift Rozet**:
     - Üstteki mevcut durum kartında hem günün statüsü (`X Çalışılan Gün`, `Yİ Yıllık İzin` vb.) hem de varsa `+4s Mesai` mor hapı yan yana kompakt rozet grubu (`.banner-badges-group`) olarak gösterilir.
     - Sekme butonunun başlığında da mesai varsa dinamik olarak `⚡ Fazla Mesai (+4s)` rozeti yer alır.
  4. **Ay Detay Takviminde "+FM (Fazla Mesai)" Damgalama Desteği**:
     - Ay detay takviminde `Ücretli`, `Ücretsiz` sekmelerinin yanına `⚡ Fazla Mesai (+FM)` sekmesi eklendi. Kullanıcı saat seçip takvimdeki günlere dokunarak ardışık günlere tık tık mesai damgalayabilir.
  5. **API Backend Parametre Sırası Düzeltmesi**:
     - `mobile-spa/public/api/puantaj.php` & `dist/api/puantaj.php` içinde `PersonelFazlaMesaileriModel::saveMesai` ve `deleteMesai` çağrılarındaki parametre sırası (`$personelId`, `$tarih`, `$saat`, `$firmaId`, `$userId`) düzeltildi.
  6. **Derleme & PWA Cache**:
     - `npm run build` ile 0 hata ile derlendi (`index-BScQ_uQp.js`, `index-r5mRu-Vl.css`). Service worker cache adı `ersan-mobile-shell-v24` seviyesine yükseltildi.

## Mobil SPA: Cari Hareketlerini Düzenleme Desteği (Hareketi Düzenle Butonu ve Güncelleme Akışı) (2026-10-18)
- **Kullanıcı Talebi**: "mobil-spa'da cari hareketine basınca düzenleyebilmek istiyorum, basınca sadece sil var şu anda"
- **Uygulanan Mimari & Tasarım**:
  1. **`CariDetailView.vue`**:
     - Hareket detay popup'ında (`isMovementDetailOpen`) tekli silme aksiyonu yerine birincil "Hareketi Düzenle" (`.primary-edit-btn`) butonu eklendi.
     - Butona basıldığında `openEditMovement(h)` metodu çalışır; hareketin ID'si, yönü (`aldim` / `verdim`), tutarı, yerel saat dilimine dönüştürülmüş tarihi (`YYYY-MM-DDTHH:mm`), belge numarası, açıklaması ve varsa mevcut dosya URL'si (`existing_file`) form durumuna yüklenir.
     - Hareket detay modalı kapanarak hızlı işlem bottom sheet'i açılır.
     - Form başlığı dinamik olarak `İşlemi Düzenle` ve ikonu `Pencil`, kaydet butonu `Değişiklikleri Güncelle` olarak güncellenir.
     - Mevcut kayıtlı dosya varsa kullanıcıya bilgi veren `existing-doc-banner` kartı gösterilir; yeni dosya seçilmezse mevcut dosya korunur, yeni dosya seçilirse şifrelenerek güncellenir.
     - Form gönderildiğinde `formData.append('hareket_id', ...)` eklenerek mevcut backend'deki `save_hareket` aksiyonunun `CariHareketleriModel->saveWithAttr()` UPDATE rotasını çalıştırması sağlanır.
     - Güncelleme sonrası hem hareket listesi hem de cari bakiye özetleri anlık olarak yenilenir.
  2. **`CariView.vue`**:
     - Tüm Hareketler (İşlem Geçmişi) modalındaki hareket satırına basıldığında açılan hareket detay modalına da aynı şekilde `Hareketi Düzenle` (`.primary-edit-btn`) butonu eklendi.
     - `openEditMovement(selectedMovement)` ile işlem düzenleme bottom sheet'i açılır; form başlığı `İşlemi Düzenle`, butonu `Değişiklikleri Güncelle` olur.
     - Düzenleme modalı `z-index: 120` (`.ds-modal-layer--quick`) ile geçmiş modalının üzerinde temiz bir katman olarak çalışır; güncelleme tamamlandığında hem cari listesi hem de geçmiş listesi anında taranıp yenilenir.
  3. **Derleme**:
     - `npm run build` ile Vite paketi 0 hata ile başarıyla derlendi.


## Fatura Cari Hareket Yönleri SQL — Sunucu 1064 Hatası (2026-10-18)
- `sql/2026_10_18_fix_fatura_cari_hareket_yonleri.sql` dosyasının içeriği ve sorgu sonları doğrulandı; dosyada sözdizimi hatasına yol açan ek bir ifade bulunmuyor.
- Sunucu SQL editöründeki çalıştırılan metnin sonunda `AND ch.silinme_tarihi IS NULL;ersantrc_personel` bulunduğu görüldü. Noktalı virgülden sonraki `ersantrc_personel` bağımsız/geçersiz SQL olarak yorumlandığı için MariaDB 1064 hatası oluşuyor.
- Çözüm: Editördeki fazladan `ersantrc_personel` metni silinmeli ve sorgu `AND ch.silinme_tarihi IS NULL;` ile bitirilmelidir; repodaki SQL dosyasında değişiklik gerekmemiştir.

## Masaüstü ve Mobil: Cari Hareketleri Renk Standardı (Aldıklarım Kırmızı, Verdiklerim Yeşil) (2026-10-18)
- **Kullanıcı Talebi**: "aldıklarım kırmızı , verdiklerim yeşil olacak özet kartlarda da hem masaüstünde hem mobilde"
- **Temel Mantık**:
  - **Aldıklarım (Giriş / Borç / Alınan Fatura):** Borç doğuran, firmaya borç yükleyen işlem olduğundan **KIRMIZI** (`text-danger`, `bg-danger-subtle`, `-` işareti).
  - **Verdiklerim (Çıkış / Alacak / Satış / Ödeme):** Alacak doğuran veya firmadan çıkan ödeme/tahsilat olduğundan **YEŞİL** (`text-success`, `bg-success-subtle`, `+` işareti).
- **Uygulanan Değişiklikler**:
  1. **Masaüstü ([`views/cari/hesap-hareketleri.php`](file:///opt/lampp/htdocs/ersan_elk/views/cari/hesap-hareketleri.php))**:
     - `TOPLAM GİRİŞ (ALD.")` özet kartı: İkon, tutar, alt metin ve "Girişler (-)" filtresi **KIRMIZI** (`bg-danger-subtle`, `text-danger`, `btn-subtle-danger`) yapıldı.
     - `TOPLAM ÇIKIŞ (VERD.")` özet kartı: İkon, tutar, alt metin ve "Çıkışlar (+)" filtresi **YEŞİL** (`bg-success-subtle`, `text-success`, `btn-subtle-success`) yapıldı.
     - Üst butonlar: `Aldım (-)` butonu **Kırmızı** (`btn-danger`), `Verdim (+)` butonu **Yeşil** (`btn-success`) yapıldı.
     - DataTables satırları ([`views/cari/api.php`](file:///opt/lampp/htdocs/ersan_elk/views/cari/api.php)): `borc` (Aldım) tutarları `text-danger` (kırmızı `#dc2626`), `alacak` (Verdim) tutarları `text-success` (yeşil `#16a34a`) yapıldı.
     - Excel ihracı ([`views/cari/export-hareketler-excel.php`](file:///opt/lampp/htdocs/ersan_elk/views/cari/export-hareketler-excel.php)): Başlıklar `Aldım (-)` ve `Verdim (+)` olarak güncellendi; D sütunu (Aldım) kırmızı, E sütunu (Verdim) yeşil renklendirildi.
     - Hızlı İşlem Modalı ([`views/cari/js/hareketler.js`](file:///opt/lampp/htdocs/ersan_elk/views/cari/js/hareketler.js)): `Aldım (-)` kırmızı, `Verdim (+)` yeşil temaya bağlandı.
  2. **Mobil SPA ([`CariDetailView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariDetailView.vue) & [`CariView.vue`](file:///opt/lampp/htdocs/ersan_elk/mobile-spa/src/views/CariView.vue))**:
     - Mini özet kartlarında: `TOP. ALDIM` nokta ve tutar **KIRMIZI** (`summary-dot--aldim`, `text-aldim`), `TOP. VERDİM` nokta ve tutar **YEŞİL** (`summary-dot--verdim`, `text-verdim`) yapıldı.
     - Hareket listesi: `aldim` (Giriş) satırlarında ikon ve tutar **KIRMIZI (-)**, `verdim` (Çıkış) satırlarında ikon ve tutar **YEŞİL (+)** yapıldı.
     - Hızlı işlem modalında `Aldım (-)` aktifken kırmızı, `Verdim (+)` aktifken yeşil segment buton stili uygulandı.
     - Hareket detay modalında tutarlar `aldim` için kırmızı `-`, `verdim` için yeşil `+` olarak eşitlendi.
  3. **Derleme**:
     - `npm run build` ile Vite paketi 0 hata ile derlendi.

## Mobil SPA: Cari Hesap Hareketleri Masaüstü Uyumluluğu (Giriş/Aldım Yeşil, Çıkış/Verdim Kırmızı) (2026-10-18)
- **Kullanıcı Talebi**: "hesap hareketlerinde galiba yine biri terslik var, masaüstü views/cari/hesap-hareketleri.php buradaki gibi olmalı" (Mobil ekran görüntüsünde Aldım'ın kırmızı `-`, Verdim'in yeşil `+` olarak ters görünmesi ve masaüstünde ise Giriş/Aldım'ın yeşil `+`, Çıkış/Verdim'in kırmızı `-` olması).
- **Tespit Edilen Çelişki**:
  - Masaüstünde (`views/cari/hesap-hareketleri.php` & `views/cari/api.php`):
    - `GİRİŞ (ALD.)` = **YEŞİL (+)** (Kasaya/stoka para/mal girişi, tahsilat). Kart: `TOPLAM GİRİŞ (ALD.")` yeşil, Buton: `Aldım (+)` yeşil (`btn-success`).
    - `ÇIKIŞ (VERD.)` = **KIRMIZI (-)** (Kasadan/stoktan para/mal çıkışı, ödeme). Kart: `TOPLAM ÇIKIŞ (VERD.")` kırmızı, Buton: `Verdim (-)` kırmızı (`btn-danger`).
    - `BENİM DURUMUM (NET)` = Bakiye < 0 ise `Borçluyum (Net)` (Kırmızı), Bakiye > 0 ise `Alacaklıyım (Net)` (Yeşil).
    - Yürüyen Bakiye = Bakiye < 0 ise kırmızı `₺... (B)`, Bakiye > 0 ise yeşil `₺... (A)`.
  - Mobilde ise (`CariDetailView.vue` & `CariView.vue`):
    - `aldim` işlemi yanlışlıkla kırmızı (`text-debt`, `-12.000 ₺`) yapılmıştı.
    - `verdim` işlemi yanlışlıkla yeşil (`text-credit`, `+10.500 ₺`) yapılmıştı.
    - Kartlarda `TOP. ALDIM` kırmızı, `TOP. VERDİM` yeşil olarak ters boyanmıştı.
- **Uygulanan Düzeltmeler**:
  1. **`CariDetailView.vue`**:
     - Mini özet kartlarında `TOP. ALDIM` masaüstündeki gibi **YEŞİL** (`text-aldim`, `summary-dot--aldim`), `TOP. VERDİM` **KIRMIZI** (`text-verdim`, `summary-dot--verdim`) yapıldı.
     - 3. Net Bakiye kartında `cari.bakiye < 0` için `BORÇLUYUM` ve kırmızı, `> 0` için `ALACAKLIYIM` ve yeşil olarak ayarlandı.
     - Hareket listesinde:
       - `aldim` (Giriş) hareketleri: **YEŞİL (+)** (`text-aldim`, `type-aldim`, `+12.000,00 ₺`, giriş oku).
       - `verdim` (Çıkış) hareketleri: **KIRMIZI (-)** (`text-verdim`, `type-verdim`, `-10.500,00 ₺`, çıkış oku).
       - Yürüyen bakiye: Masaüstü standardı olan `Bakiye: 81.400,00 ₺ (B)` formatına ve renklerine bağlandı.
     - Hızlı İşlem Ekle modalı butonları masaüstüyle birebir eşleştirildi: `Aldım (+)` yeşil, `Verdim (-)` kırmızı.
     - Hareket detay modalında başlıklar ve tutarlar `Giriş (Aldım)` (+) yeşil ve `Çıkış (Verdim)` (-) kırmızı olarak güncellendi.
  2. **`CariView.vue`**:
     - Hızlı işlem ekleme modalı toggle butonları `Aldım (+)` yeşil, `Verdim (-)` kırmızı yapıldı.
     - Tüm Hareketler (İşlem Geçmişi) listesinde ve hareket detay modalında tutarlar `aldim` için `+` yeşil, `verdim` için `-` kırmızı olarak eşitlendi.
  3. **Derleme**:
     - `npm run build` ile üretim paketi 0 hata ile derlendi.

## Mobil SPA KM Onayları: Yapay Zeka (AI) ve Eylemlerde CSRF Hatası Düzeltmesi (2026-10-18)
- **Kullanıcı Talebi**: "ai ile onay yapmak istediğmde bu hatayı veriyor" (`Güvenlik doğrulaması (CSRF) geçersiz. Lütfen sayfayı yenileyin.` popup hatası).
- **Kök Neden Tespiti**:
  1. `mobile-spa/src/services/auth.js` oturum durumu `state.csrfToken` (camelCase) alanında tutuluyordu.
  2. Ancak `KmOnaylariView.vue` bileşeni içindeki tüm eylemlerde (`handleApprove`, `submitEditApprove`, `submitReject`, `handleBulkApprove`, `handleAiCheck`, `handleSendReminder`, `handleBulkReminder`) `auth.state.csrf_token` (snake_case) kullanılmıştı.
  3. `auth.state.csrf_token` tanımsız (`undefined`) olduğu için API isteklerinde `X-CSRF-Token` header'ı hiç eklenmiyor ve sunucu 419 ("Güvenlik doğrulaması CSRF geçersiz") yanıtı dönüyordu.
- **Uygulanan Çözümler**:
  1. **Evrensel CSRF Desteği (`mobile-spa/src/services/auth.js`)**:
     - `state` reaktif objesine ve `applySession` metoduna hem `csrfToken` hem de `csrf_token` eklendi; böylece her iki yazım stili de geçerli token'ı taşır hale getirildi.
  2. **Otomatik CSRF Enjeksiyonu (`mobile-spa/src/services/km-onaylari.js`)**:
     - `auth` servisi import edildi; `request` metodunda `csrfToken` parametresi verilmemiş olsa bile otomatik olarak `auth?.state?.csrfToken || auth?.state?.csrf_token` header'a (`X-CSRF-Token`) ve POST JSON body'ye (`csrf_token`) eklendi.
  3. **Görünüm Düzeltmesi (`mobile-spa/src/views/KmOnaylariView.vue`)**:
     - Tüm `kmApi` çağrılarında `auth.state.csrfToken || auth.state.csrf_token` güvencesi uygulandı.
  4. **Backend API Çift Yönlü Token Kontrolü (`public/api/km-onaylari.php` & `dist/api/km-onaylari.php`)**:
     - `verifyCsrfToken` fonksiyonu `HTTP_X_CSRF_TOKEN`, `$_POST['csrf_token']` ve `HTTP_CSRF_TOKEN` kaynaklarını kontrol edecek şekilde sağlamlaştırıldı.
  5. **Üretim Paketi**:
     - `npm run build` ile Vite paketi 0 hata ile derlendi.
- **Kullanıcı Talepleri**:
  1. "araç takip yönetim altında bir araç dashboard yapmak istiyorum, views/efatura/dashboard.php sayfasındaki gibi"
  2. "bunu mobile-spa tarafına da yapalım"
- **Yapılan Geliştirmeler**:
  - **1. Veritabanı & Yetkilendirme**:
    - `sql/2026_10_18_add_arac_dashboard_menu_and_permissions.sql` oluşturuldu ve DB'ye uygulandı (`permissions.permission_key = 'arac-takip/dashboard'`, `permission_policies`, `menus` id: 986 "Dashboard", `user_role_permissions` superadmin & firma sahibi rolleri).
  - **2. Model Katmanı (`App/Model/AracDashboardModel.php`)**:
    - PDO ve parametrik binding kullanılarak MariaDB uyumlu KPI istatistikleri (`getKpiStats`), 12 aylık KM/Yakıt/Servis trendleri (`getMonthlyTrends`), Departman dağılımı (`getDepartmentDistribution`), Mülkiyet ve yakıt türü (`getVehicleTypesDistribution`), Lider araçlar (`getTopVehiclesByKm`, `getTopVehiclesByFuel`), Kritik ve yaklaşan evraklar (`getExpiringDocuments`) ve Son servis/yakıt hareketleri (`getRecentServices`, `getRecentFuelLogs`) metotları yazıldı.
  - **3. Masaüstü Sayfa & Arayüz**:
    - `views/arac-takip/dashboard.php` ve `views/arac-takip/js/dashboard.js` oluşturuldu; `views/arac-takip/api.php` içine `get-dashboard-stats` eklendi; `index.php` rotalarına `'arac-dashboard' => 'arac-takip/dashboard'` tanımlandı.
    - ApexCharts interaktif trend ve dağılım grafikleri, 4'lü KPI kartı, canlı departman ve dönem filtreleri ile masaüstü tam faaliyete geçirildi.
  - **4. Mobil SPA Backend API**:
    - `mobile-spa/public/api/arac.php` ve `mobile-spa/dist/api/arac.php` içine `action === 'dashboard'` endpoint'i eklendi; `parseDateRange` fonksiyonu `this_month`, `last_month`, `last_3_months`, `this_year`, `all`, `custom` dönemleriyle zenginleştirildi; Gate yetki denetimi uygulandı.
  - **5. Mobil SPA Servis & Gezinme**:
    - `mobile-spa/src/services/arac.js`: Varsayılan sekme `dashboard` olarak ayarlandı, `aracApi.dashboard(params)` servisi eklendi.
    - `mobile-spa/src/router.js`: `/arac-dashboard` rotası eklendi.
    - `mobile-spa/src/components/BottomNav.vue`: Çekmecenin ilk elemanı olarak `LayoutDashboard` ikonlu "Dashboard" sekmesi yerleştirildi.
  - **6. Mobil SPA Tasarım & Bileşenler (`AracView.vue`)**:
    - Soft Bento & Claymorphic UI standartlarına (`design-system.css`) tam uyumlu gösterge paneli geliştirildi:
      - **Hero Koyu Bento Kartı**: Dinamik filo doluluk segment çubuğu (`.segmented-track--dark`) ve 4'lü filo, zimmet, net yol ve yakıt gideri metrik hücreleri.
      - **Dönem & Departman Filtre Barı**: Bu Ay, Geçen Ay, Son 3 Ay, Bu Yıl, Tümü, Ay Seç ve Modal destekli Departman filtresi.
      - **4 Detaylı Bento KPI Kartı**: Filo Durumu, KM & Yol, Akaryakıt & Tüketim, Servis & Bakım; alt mini istatistikler ve ilgili sekmeye tek dokunuşla yönlendirme.
      - **Aylık Trend Bar Grafiği**: 12 aylık mobil yatay kaydırılabilir KM ve Yakıt karşılaştırma çubukları.
      - **Departman Bazlı Analiz**: KM, Yakıt ve Araç metrik sekmeleriyle dinamik doluluk çubukları.
      - **Mülkiyet & Yakıt Türü Dağılımı**: Özmal/Kiralık ve Dizel/Benzin/Hibrit/Elektrik yüzdeleri.
      - **Liderler Tablosu (Top 5)**: En çok KM yapanlar ve en çok yakıt harcayanlar listesi (derece rozetleri ve araç detayına gitme desteği).
      - **Kritik & Yaklaşan Evraklar**: Muayene, Trafik Sigortası ve Kasko için kalan gün rozetleri (`-109 gün gecikti`, `10 gün kaldı`); araca tıklandığında detay açılışı.
      - **Son Bakım & Servis Kayıtları**: Son işlemler, servis adı, tutar ve durum rozetleri.
      - **Departman Seçici Bottom Sheet Modalı (`isDepartmanModalOpen`)**: Tek dokunuşla filtreleme.
  - **7. Üretim Derlemesi & Doğrulama**:
    - `npm run build` ile Vite üretim paketi 0 hata ile derlendi (`postbuild` symlink'leri oluşturuldu).
    - PHP CLI (`/opt/lampp/bin/php`) ile API JSON çıktısı başarıyla doğrulandı.

## Mobil SPA Talepler Kartı: Talep Durumu ve Talep Türü Gösterimi (2026-10-18)
- **Kullanıcı Talepleri**:
  1. "talepler kartında durumunu da gösterelim" (İşlem Görenler ve Silinenler sekmelerinde avans/izin kartlarında durumun görünmemesi sorununun çözülmesi; Onaylandı, Reddedildi, Kısmi Onay, Beklemede, İşlemde, Çözüldü, Silindi rozetlerinin tüm sekmelerde eksiksiz gösterilmesi).
  2. "ayrıca tümü seçili iken hangisi izin hangisi avans belli olmuoyr, onu da gösterelim" (Özellikle "Tümü" sekmesinde ve tüm kartlarda talebin Avans mı, İzin mi yoksa Genel Talep mi olduğunun net biçimde rozetle belirtilmesi).
- **Yapılan İyileştirmeler**:
  - **Durum Rozeti (`status-chip`)**:
    - `getStatusMeta(item)` fonksiyonu yazıldı: `onaylandi`, `reddedildi`, `kismi_onaylandi`, `islemde`, `beklemede`, `cozuldu`, `silindi` / `iptal` durumları için renkli rozet sınıfları (`status-chip--approved`, `status-chip--rejected`, `status-chip--partial`, `status-chip--in-progress`, `status-chip--pending`, `status-chip--deleted`) bağlandı.
    - Avans ve izinlerdeki sağ üst tutar/gün alanının hemen altına durum rozeti yerleştirildi. Böylece ekran görüntüsünde kullanıcının işaretlediği kırmızı kutuya uygun olarak ret ve onay durumları anında görünür hale getirildi.
    - Detay modalındaki "DURUM" alanına da aynı durum rozeti entegre edildi.
  - **Talep Türü Rozeti (`type-pill`)**:
    - `getTypeMeta(item)` fonksiyonu eklendi:
      - Avans: `Wallet` ikonu ile zümrüt yeşili `.type-pill--avans` ("Avans Talebi")
      - İzin: `CalendarDays` ikonu ile mavi `.type-pill--izin` ("İzin Talebi")
      - Genel Talep: `Layers` ikonu ile mor `.type-pill--talep` ("Genel Talep")
    - Kartın tarih satırında (`.t-card__details-row`), kullanıcının ekran görüntüsünde işaretlediği sağ boşluk alanına `.type-pill` rozeti yerleştirildi.
    - Personel ismi altındaki alt başlıkta (`.t-card__meta`) departman yanına `· Avans`, `· İzin` veya `· Talep` bilgisi eklendi (`Kesme Açma · Avans`).
  - **Derleme**: Vite ile `npm run build` 0 hata ile çalıştırıldı, `postbuild` symlink'leri doğrulandı.

## Mobil SPA: Hızlı Erişim (Quick Actions) Operasyon Tarzı Renklendirme ve Tam Özelleştirme (2026-10-18)
- **Kullanıcı Talepleri**:
  1. "bunları hafif renkli yapalım, operasyonlar karıt gibi olsun" (Hızlı erişim kutularının operasyonlar kartındaki gibi hafif renkli, çerçeveli ve canlı yapılması).
  2. "ayrıca hızlı işlemleri özelleştirebilmek istiyorum ,yani kullanıcı, yetkisinden olduğu sayfalardan hatta herhangi bir linki yani personel düzenleme sayfasındaki ek ödemeler sekmesini vs. kısayol olarak ekleyebilr mi" (Kullanıcının ana sayfadaki hızlı erişim kısayollarını özelleştirebilmesi, yetkili olduğu sistem sayfalarını, personel ek ödemeler gibi derin sekmeleri ve özel URL bağlantılarını kısayol olarak ekleyebilmesi).
- **Yapılan İyileştirmeler**:
  1. **Operasyon Kartı Standartlarında Tasarım & Renk Temaları (`design-system.css`)**:
     - `.quick-action-tile`: Beyaz zemin (`#ffffff`), 1.5px renkli kenarlık (`border: 1.5px solid rgba(renk, 0.42)`), hafif derinlik gölgesi ve kompakt bento kutusu formuna kavuşturuldu.
     - `.quick-action-icon`: 36x36px, `border-radius: 11px`, `background: rgba(renk, 0.12)` ve `color: renk` ile operasyonlar kartındaki ikon balonu stiline getirildi.
     - 10 farklı renk teması tanımlandı (`.qa-theme--blue`, `.qa-theme--emerald`, `.qa-theme--amber`, `.qa-theme--indigo`, `.qa-theme--teal`, `.qa-theme--red`, `.qa-theme--purple`, `.qa-theme--yellow`, `.qa-theme--slate`, `.qa-theme--pink`).
     - Hızlı Erişim kartının başlığına kompakt `.qa-customize-trigger-btn` ("Özelleştir") butonu eklendi.
  2. **Derin Bağlantı Desteği (Personnel Deep Linking)**:
     - `PersonnelDetailView.vue` ve `PersonnelSectionView.vue` içine son görüntülenen personel ID'sini (`ersan_last_personnel_id`) `localStorage`'a kaydetme mantığı entegre edildi.
     - `HomeView.vue` içindeki `openAction()` metodu `personnel_section:ek_odemeler`, `personnel_section:kesintiler`, `personnel_section:izinler`, `personnel_section:evraklar`, `personnel_section:zimmetler`, `personnel_section:icralar` gibi derin sekme yönlendirmelerini son görüntülenen personel üzerinden anında açacak şekilde yapılandırıldı; son personel yoksa liste sayfasına yönlendirildi.
  3. **Özelleştirme Modalı Bento UI & Görsel İyileştirmeler (Kullanıcı Geri Bildirimi Doğrultusunda)**:
     - **Çift Scrollbar Giderildi**: Nested scrollbar kaldırılarak tek, pürüzsüz ve yerel mobil kaydırma deneyimi sağlandı.
     - **Kapsül Segmented Bar**: Sekme çubuğu (`.qa-capsule-tabs`) tam yuvarlatılmış hap kapsülüne çevrildi, odak çizgileri temizlendi, sekmeler arası geçişte sayfa kaydırması en üste sıfırlandı (`scrollTop = 0`).
     - **Canlı Kısayol Önizleme Kartı**: Özel bağlantı oluşturulurken seçilen başlık, hedef URL, ikon ve tema rengi anlık olarak kart üzerinde dinamik gösterildi.
     - **Hızlı Şablon Butonları (Quick Presets)**: `Ek Ödemeler`, `Maaş Kesintisi`, `İzin Talebi`, `Avans Talebi`, `KM Onayları`, `Kaçak Kontrol` gibi tek dokunuşla tüm formu ve renk/ikon ayarlarını otomatik dolduran hazır chipler eklendi.
     - **İkon ve Renk Paletleri**: 16 yumuşak kavisli ikon butonu ve çift halkalı dairesel renk seçiciler eklendi; form alanlarına sol ikonlar (`Pencil`, `Link2`) yerleştirildi.
     - **Sistem Kataloğu Gruplaması**: "Hızlı Bölümler & Derin Sekmeler" ile "Ana Sistem Modülleri" iki ayrı başlık altında organize edildi.
  4. **Kalıcı Saklama & Sunucu Senkronizasyonu**:
     - Kullanıcının özelleştirdiği kısayollar hem anlık olarak tarayıcıda (`localStorage: ersan_mobile_quick_actions`), hem de `saveDashboardSettings()` API'si üzerinden sunucudaki `mobile_dashboard_settings` tablosunda kalıcı olarak saklandı.
  5. **Derleme & Doğrulama**:
     - `mobile-spa` dizininde `npm run build` ile üretim paketi 0 hata ile derlendi.

## Mobil SPA: Bordro Detayında Ödeme Türleri Akordiyon Yapısı (2026-10-18)
- **Kullanıcı Talebi**: "bordro detayından ödeme türlerini acordion olarak yapaım" (Bordro detay modalında Karşılaştırmalı Hesap tablosundaki ödeme türlerinin - Puantaj Hakedişleri, Nöbet Ödemeleri, Kaçak Kontrol, Ek Ödemeler, Kesintiler vb. - mobilde çok fazla yer kaplamadan akordiyon şeklinde açılıp kapanabilir yapılması).
- **Kök Neden & Analiz**:
  - `views/bordro/api.php` masaüstü için `.parent-row[data-bs-target]` ve `.child-row.collapse` HTML çıktılarını zaten üretiyordu.
  - Ancak `mobile-spa` Vue 3 SPA ortamında Bootstrap JS kütüphanesi bulunmadığından tıklamalar event handler'sız kalıyordu.
  - Ayrıca `mobile-spa/src/views/PayrollView.vue` CSS'inde yer alan `.collapse { display: block !important; }` kuralı tüm alt kırılım satırlarını sürekli açık tutarak mobilde devasa ve yorucu bir liste oluşturuyordu.
- **Yapılan İyileştirmeler**:
  1. **HTML Başlangıç Durumu (`sanitizeDetailHtml`)**:
     - Ana hesap kartları (`.bordro-main-calculation-collapse`) mobilde varsayılan olarak açık (`.show`) ve `aria-expanded="true"` tutuldu.
     - Ödeme türleri alt kırılımları (`.child-row.collapse`) varsayılan olarak kapalı tutuldu ve ebeveyn satırları (`.parent-row`) `aria-expanded="false"` yapıldı.
  2. **Vue Olay Delegasyonu (`handleDetailTableClick`)**:
     - Karşılaştırmalı ve resmî hesap dökümü konteynerlarına `@click="handleDetailTableClick"` eklendi.
     - Tıklanan `.parent-row` satırının `data-bs-target` veya `data-target` niteliğindeki seçici (`.colPuantaj_xxx`, `.colNobet_xxx`, `.cEx_xxx`, `.colKesinti_xxx` vb.) yakalanarak ilgili çocuk satırların `.show` sınıfı animasyonlu olarak açılıp kapatıldı ve `aria-expanded` güncellendi.
     - Kart başlıkları (`.calculation-toggle`) da ana kart panellerini (`data-collapse-target`) tek dokunuşla katlama/açma desteğine kavuşturuldu.
  3. **CSS Vektörel Chevron ve Akordiyon Animasyonları (`PayrollView.vue`)**:
     - Saf CSS vektörel chevron pseudo-elementleri (`::after`) ile retina/mobil ekranlarda kristal netliğinde ok göstergeleri eklendi.
     - `.child-row.collapse:not(.show)` mobilde `display: none !important` yapılarak temiz ve kompakt bir görünüm sağlandı; `.show` olduğunda `childRowExpand` animasyonuyla yumuşak açılması sağlandı.
     - Açık akordiyon satırında chevron 180° yukarı dönerek ve elektrik mavisi renkle aktif duruma geçerek harika bir dokunmatik geri bildirim sundu.
  4. **Doğrulama & Test**:
     - `npm run build` ile üretim paketi 0 hata ile derlendi.
     - Playwright otomatik uçtan uca testi çalıştırılarak kapalı durum (`bordro-detail-accordion-collapsed.png`) ve açık durum (`bordro-detail-accordion-expanded.png`) test edildi ve doğrulandı. Geçici test dosyaları temizlendi.

## Masaüstü: Araç Takip & Filo Yönetimi Altında Araç Dashboard Modülü (2026-10-18)
- **Kullanıcı Talebi**: "araç takip yönetim altında bir araç dashboard yapmak istiyorum, @[views/efatura/dashboard.php] sayfasındaki gibi" (Sol menüdeki "Araç Takip/Yönetim" menüsünün altında, E-Fatura Dashboard sayfasındaki gibi modern, KPI kartları, trend grafikleri, filtreleri ve analizleri olan gösterge paneli geliştirilmesi).
- **Geliştirilen Mimar ve Bileşenler**:
  1. **Veritabanı & Yetkilendirme (`sql/2026_10_18_add_arac_dashboard_menu_and_permissions.sql`)**:
     - `permissions`: `permission_key = 'arac-takip/dashboard'`, 'Araç Dashboard' adı ve 'Araç Yönetimi' grubu ile tanımlandı.
     - `user_role_permissions`: Süper Admin ve Firma Sahibi rollerine `NOT EXISTS` kontrollü atandı.
     - `menus`: Parent ID 56 ('Araç Takip/Yönetim') altına `menu_order = 49` ile ilk sıraya eklendi (`menu_link = 'arac-takip/dashboard'`).
     - `user_menu_orders`: Kullanıcıların özel menü sıralaması tablosunda `submenus[56]` grubunun en başına `986` (Araç Dashboard) yerleştirildi.
     - `permission_policies`: Sayfa (`scope=page, resource=arac-takip/dashboard, GET`) ve API (`scope=api, resource=arac-takip/api, action=get-dashboard-stats`) politikaları bağlandı.
  2. **Model Katmanı (`App/Model/AracDashboardModel.php`)**:
     - `getKpiStats()`: Filo durumu (Toplam, aktif, pasif, zimmetli, boşta, servisteki, ikame), KM & yol kullanımı (Toplam KM, aktif araç, günlük ortalama, araç başı ortalama, lider araç), Yakıt sarfiyatı (Toplam tutar ₺, toplam litre, ortalama litre fiyatı, 100km tüketim, km başı maliyet), Bakım & evraklar (Servis tutarı, işlem adedi, yaklaşan/geciken muayene, sigorta, kasko).
     - `getMonthlyTrends()`: Yılın 12 ayı için aylık yakıt gideri (₺), toplam yapılan KM ve servis harcamaları serileri.
     - `getDepartmentDistribution()`: Departmanların araç sayısı, dönem KM'si ve yakıt tüketimi dağılımı.
     - `getVehicleTypesDistribution()`: Mülkiyet (Özmal/Kiralık) ve yakıt türü dağılımları.
     - `getTopVehiclesByKm()` & `getTopVehiclesByFuel()`: En çok kilometre yapan ve en çok yakıt tüketen ilk 5 araç (sürücü, departman, tutarlar).
     - `getExpiringDocuments()`: Muayenesi, sigortası veya kaskosu geçmiş veya 30 gün içinde dolacak araçlar ve kalan gün hesaplaması.
     - `getRecentServices()` & `getRecentFuelLogs()`: Son bakım/onarım ve son yakıt hareketleri.
  3. **Backend API (`views/arac-takip/api.php`)**:
     - `get-dashboard-stats` eylemi eklendi; Gate yetki denetimi ve JSON yanıt yapısı entegre edildi.
  4. **Frontend Görünüm & Script (`views/arac-takip/dashboard.php` & `views/arac-takip/js/dashboard.js`)**:
     - `views/efatura/dashboard.php` tasarım standardına birebir sadık kalındı:
       - Üst başlık, departman filtresi, hızlı dönem segmented butonları (Bu Ay, Geçen Ay, Son 3 Ay, Bu Yıl, Tümü), Flatpickr tarih seçici ve yenileme butonu.
       - `#dashboard-loader` preloader animasyonu.
       - 4 Adet degrade kenarlıklı `.dashboard-stat-card` (Filo Durumu, KM & Yol, Yakıt Sarfiyatı, Bakım & Evraklar).
       - ApexCharts grafikleri: Aylık Yakıt Maliyeti & KM Trendi (Combo Bar+Line), Departman Dağılımı Donut (KM/Yakıt/Araç metrik butonları ile), Mülkiyet Dağılımı (Pie), Yakıt Tipi Dağılımı (Donut).
       - Sıralamalar & Listeler: Top 5 KM, Top 5 Yakıt, Kritik Evrak Takip Tablosu (Geciken kırmızı rozet, yaklaşan sarı rozet), Son Servis Hareketleri.
       - macOS Dark tema (`html[data-theme-preset="macos-dark"]`) tam uyumluluğu.
  5. **Rota Entegrasyonu (`index.php`)**:
     - `$routeAliases` dizisine `'arac-dashboard' => 'arac-takip/dashboard'` eklendi.

## Mobil SPA: Cari Takip Modülü Bakiye ve Borçlu/Alacaklı Mantığı Düzeltmesi (2026-10-18)
- **Kullanıcı Talebi**: "buradaki mantık benim verdiklerim benim alacağım oluyor yani eğer cariye verdiysem onlar borçlu olmalı" (Görselde yeşil + bakiye ile ALACAKLI rozeti gösterilmesi ve sayaçların/filtrelerin ters eşleşmesi sorunu).
- **Kök Neden & Analiz**:
  - `cari_hareketleri` tablosunda `alacak` = Bizim verdiğimiz (Ödeme / Para Çıkışı / Mal Çıkışı), `borc` = Bizim aldığımız (Tahsilat / Para Girişi / Mal Girişi).
  - Dolayısıyla `bakiye = SUM(alacak) - SUM(borc)`:
    - `bakiye > 0` => Bizim Alacağımız var => **Cari BORÇLU**.
    - `bakiye < 0` => Bizim Borcumuz var => **Cari ALACAKLI**.
  - Eski kodda `$bakiye < 0 ? 'borclu' : 'alacakli'` şeklinde ters mantık kurulmuştu. Bu durum:
    1. Bakiye > 0 olan carilerin kartında `Borçlu` yerine yanlışlıkla `Alacaklı` rozeti göstermesine,
    2. Obsidian Bento özet kartında "Top. Verdim (Alacak)" kutusunda borçlu cari sayısı yerine alacaklı sayısının yazılmasına,
    3. Sekmelerde `Borçlular` ve `Alacaklılar` filtrelerinin ve sayaçlarının ters çalışmasına neden oluyordu.
- **Yapılan İyileştirmeler**:
  1. **Model Katmanı (`App/Model/CariModel.php`)**:
     - `summary()` metodunda `bakiye > 0 THEN 1 ELSE 0` -> `borclu_cari_sayisi`, `bakiye < 0 THEN 1 ELSE 0` -> `alacakli_cari_sayisi` olarak düzeltildi.
     - `ajaxList()` metodunda `balance_filter === 'borclu'` için `> 0`, `balance_filter === 'alacakli'` için `< 0` yapıldı.
  2. **API Katmanı (`mobile-spa/public/api/cari.php` & `dist/api/cari.php`)**:
     - `balanceFilter` filtresinde `borclu` için `> 0`, `alacakli` için `< 0` uygulandı.
     - Liste elemanlarında:
       `'bakiye_status' => $bakiye > 0 ? 'borclu' : ($bakiye < 0 ? 'alacakli' : 'sifir')`
       `'bakiye_label' => $bakiye > 0 ? 'Borçlu' : ($bakiye < 0 ? 'Alacaklı' : 'Bakiye Yok')`
  3. **Vue Arayüzü (`mobile-spa/src/views/CariView.vue`)**:
     - Obsidian Hero kartı sol kutusunda `Top. Verdim (Alacak)` altında `{{ summary.borclu_sayisi || 0 }} borçlu cari` gösterildi ve tıklama `activeFilter === 'borclu'` filtresine bağlandı.
     - Sağ kutusunda `Top. Aldım (Borç)` altında `{{ summary.alacakli_sayisi || 0 }} alacaklı cari` gösterildi ve tıklama `activeFilter === 'alacakli'` filtresine bağlandı.
     - Sekmelerde `Borçlular` (özet borçlu sayısı, yeşil dot) ve `Alacaklılar` (özet alacaklı sayısı, kırmızı/rose dot) sırasıyla ve doğru filtrelerle bağlandı.
     - Cari kartlarında pozitif bakiye (`+`) olan cariler için `Borçlu` (`badge-borclu` / `text-credit`), negatif bakiye (`-`) olan cariler için `Alacaklı` (`badge-alacakli` / `text-debt`) rozeti ve renkleri sağlandı.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile üretim paketi 0 hata ile derlendi.
     - Test scripti ile veritabanı hesaplamaları ve API çıktıları doğrulandı, geçici dosyalar temizlendi.

## Mobil SPA: E-Fatura Görüntüleme Ekrana Sığdırma (Fit-to-Screen), Zoom/Pinch ve PDF Dosya Paylaşımı (2026-10-18)
- **Kullanıcı Talepleri**:
  1. "fatura görüntüleme modalinda faturayı ekrana sığdırsın, istersek yakınlaştırabilelim" (Mobilde faturanın sağ tarafının kesilmesi sorununun giderilmesi, otomatik ekrana tam sığdırma ve dokunmatik/butonlu zoom desteği).
  2. "ayrıca hem burada paylaş butonunda, hem de listelerdeki paylaş butonunda faturayı pdf olarak paylaşmamız gerekiyor, link olarak değil" (Web Share API ile URL yerine doğrudan PDF dosyasının paylaşılması).
- **Tespitler & Çözümler**:
  1. **Ekrana Otomatik Sığdırma (Fit-to-Screen)**:
     - GİB/EDM e-fatura XSLT şablonları A4 standardında (~800-850px) masaüstü tablo genişliği üretir. Mobilde iframe'e doğrudan yüklenince 360-390px ekranlarda faturanın sağ yarısı taşıp kesiliyordu.
     - `onIframeLoaded` ile faturanın doğal boyutları dinamik ölçülerek `fitScale = (viewportWidth - 16) / baseWidth` oranı hesaplandı.
     - İframe, ölçekli wrapper (`.fatura-viewer-scaler`) içine alınarak `transform: scale(zoomScale)` ile tam ekran genişliğine sığdırıldı; yatay taşma ve kesilme tamamen ortadan kaldırıldı.
  2. **Etkileşimli Zoom ve İki Parmakla Kıstırma (Pinch-to-Zoom)**:
     - Çift parmak kıstırma dokunmatik hareketleri (`onTouchStart`, `onTouchMove`, `onTouchEnd`) entegre edildi.
     - Modalın alt orta kısmında modern buzlu cam (frosted glass) dock (`.fatura-zoom-dock`) eklendi:
       - `[-]` Uzaklaştır
       - `[%.. / Sığdırıldı]` ölçek göstergesi ve tek tıkla %100/Sığdır geçiş butonu
       - `[+]` Yakınlaştır
       - `[Sığdır]` doğrudan ekrana tam sığdırma butonu (`Maximize2`)
  3. **PDF Dosya Olarak Paylaşma (Web Share API Files)**:
     - URL paylaşımı yerine `/api/fatura.php?action=download_pdf&id=...` üzerinden PDF ikili dosyası indirilip `new File([blob], `${fatura_no}.pdf`, { type: 'application/pdf' })` oluşturuldu.
     - `navigator.canShare({ files: [file] })` ile WhatsApp, Mail, Telegram ve sistem paylaşım menüsüne doğrudan **PDF belgesi** olarak iletildi.
     - Tarayıcı dosya paylaşımını desteklemiyorsa otomatik PDF indirme fallback'i ve kullanıcı bildirimi sağlandı.
     - Hem liste kartlarındaki paylaş butonu hem de önizleme modalı başlığındaki yeni Paylaş butonu (`Share2`) bu mantığa bağlandı; işlem sırasında spinner yükleniyor animasyonu eklendi.
  4. **Backend Dayanıklılığı (`EInvoiceService::downloadPdf`)**:
     - EDM getInvoicePdf çağrısında hata veya yerel taslak olması durumunda `Dompdf` ile HTML önizlemeden anlık PDF üretip döndüren çift katmanlı fallback eklendi.
  5. **Derleme & Doğrulama**:
     - `mobile-spa` altında `npm run build` ile 0 hata ile derlendi.
     - `php -l` ile PHP sözdizimleri doğrulandı.

- **Kullanıcı Bildirimi**:
  - `GET http://mobile.ersanelektrik.com/api/personnel.php?action=detail&id=... 500 (Internal Server Error)`
  - "personel detayı sayfasında hata oldu" (Personel alınamadı ekran görüntüsü).
- **Tespit Edilen Kök Nedenler**:
  1. `App/Model/MobilePersonelModel.php` içindeki `getEvrakDetails()` metodunda personelin evrak boyutu formatlanırken `$this->formatBytes()` çağrılıyordu. Ancak model sınıfı içinde `formatBytes()` fonksiyonu tanımlı olmadığı için `Fatal error: Call to undefined method App\Model\MobilePersonelModel::formatBytes()` hatası oluşup 500 Internal Server Error üretiyordu.
  2. `mobile-spa/public/api/personnel.php` içinde `personnelPublicRow(array $row)` fonksiyonu ikinci bir `$workDetails` parametresi almadığı halde fonksiyon gövdesinde `$workDetails` değişkeni okunmaya çalışılıyordu (`PHP Warning: Undefined variable $workDetails`).
- **Uygulanan Çözümler**:
  1. `MobilePersonelModel.php` içine bayt değerlerini insan tarafından okunabilir KB, MB, GB birimlerine dönüştüren `formatBytes(int $bytes, int $precision = 2): string` metodu eklendi.
  2. `personnel.php` dosyasındaki `personnelPublicRow` fonksiyonunun imzası `personnelPublicRow(array $row, ?array $workDetails = null): array` olarak güncellendi.
  3. Güncellenen backend API dosyası `dist/api/` ile eşitlendi.
  4. PHP CLI üzerinden `detail` aksiyonu personelin 15 evrak kaydıyla birlikte test edildi ve 200 OK ile sorunsuz çalıştığı doğrulandı.
  5. `npm run build` ile üretim paketi 0 hata ile yeniden derlendi.

## Mobil SPA Talepler Modülü: Avatar 404 Çözümü, İşleme Al Butonu ve Onay Entegrasyonları (2026-10-17)
- **Kullanıcı Talepleri**:
  1. Talepler sayfasında personellerin profil resimlerinin 404 vermesi (`personel_69e48d3ba427b.jpg:1 GET https://mobile.ersantr.com/assets/images/users/... 404 (Not Found)`).
  2. "İşleme al butonu burada olsun, açık yeşil olarak, biraz daha küçük olsun ve işleme al yazsın".
  3. Süreç yönetimi yazısının kaldırılması, talep türleri menüsünün navbar içinde yana doğru transition ile açılması ve X ile kapanması, Bekleyenler/İşlem Görenler/Silinenler filtrelerinin en üstte yer alması, arama ve sıralamanın yan yana olması.
- **Yapılan İyileştirmeler ve Çözümler**:
  - **Avatar 404 Çözümü**:
    - `mobile-spa/public/api/requests.php` ve `dist/api/requests.php` içindeki avatar oluşturma mantığı `resolvePersonelAvatar()` fonksiyonuyla yeniden yazıldı.
    - Ham dosya yolu yerine merkezi güvenli endpoint `/api/personnel-photo.php?id={encrypted_id}` kullanımına geçildi.
    - Veritabanındaki `personel_resim_yolu` ve `resim_yolu` adayları diskte fiziksel varlık (`file_exists()`) kontrolünden geçirildi. Dosya yoksa kırık URL dönülmesi engellendi.
    - `mobile-spa/public/api/personnel-photo.php` ve `dist/api/personnel-photo.php` Gate yetki kontrollerine `talepler`, `avans_talepleri`, `izin_talepleri`, `ariza_talepleri` eklendi.
    - `mobile-spa/src/views/TaleplerView.vue` şablonunda `<img>` etiketine `@error="item.avatar = null"` ve `loading="lazy"` eklendi; resim bulunamayan personeller için `getInitials(name)` baş harf avatarı otomatik devreye alındı.
    - `mobile-spa/package.json`'a `postbuild` hook'u eklenerek `npm run build` sonrasında `dist/uploads` ve `dist/assets/images` symlink'lerinin otomatik oluşturulması sağlandı.
  - **İşleme Al Butonu**:
    - Genel arıza/talepler sekmesinde "İşleme Al" butonu açık yeşil renkli, kompakt boyutta (`clay-btn--subtle-success`), Çözüldü butonunun hemen solunda konumlandırıldı ve tıklandığında durumu "İşlemde"ye alıp ilgili onay/işlem akışını tetikleyecek şekilde entegre edildi.
  - **Build**: Vite derlemesi (`npm run build`) 0 hata ile tamamlandı ve canlı `dist/` dizinine aktarıldı.

## Kesme/Açma Karşılaştırma Kartı & Dağılım Modalı: Dinamik Olumlu/Olumsuz Filtreleme ve İş Türü Tanımlama Entegrasyonu (2026-10-17)
- **Kullanıcı Talepleri**:
  1. "burdaki sayılardan olumsuzları çıkaracağız ancak hangi işlerin olumlu/olumsuz olduğunu hardcode yapıoyoruz , bu çok kullanışlı olmuyor. tanımlamalar altında iş türü tanımlama sayfasında bir alan eklesek, olumlu/olumsuz tipini belirtsek, ana sayfadaki kesme açma kartında Tümü, olumlu, olumsuz toogle butonu ile olumsuz hariç sayıları ayrı görsek"
  2. "bunu buraya ekleyelim, tüm karttaki işlemleri filtre yapsın" (modalın sağ üst başlık alanına filtre haplarının taşınması ve tüm modal bileşenlerinin filtrelenmesi).
  3. "varsayılan olarak olumu seçili gelsin" (hem ana kartta hem modalda ilk açılışta Olumlu seçeneğinin aktif gelmesi).
- **Veritabanı Değişiklikleri (`sql/add_sonuc_tipi_to_tanimlamalar.sql`)**:
  - `tanimlamalar` tablosuna `sonuc_tipi VARCHAR(20) NOT NULL DEFAULT 'olumlu'` kolonu eklendi.
  - Kullanıcının belirttiği olumsuz iş türleri ('Kapalı', 'ADREŞ YANLIŞ ABONE BULUNAMADI', 'SAYAÇ KASKİYE ALINDI', 'ABONE MÜSADE ETMEDİ.', 'TAPA SAĞLAM', 'MÜHÜR VE CONTA' vb.) `'olumsuz'` olarak güncellendi.
- **İş Türü Tanımlama Modülü (`views/tanimlamalar/`)**:
  - `views/tanimlamalar/is-turu.php`: `#actionTable` tablosuna `SONUÇ TİPİ` (`data-filter="select"`) kolonu eklendi; yeşil `Olumlu` ve kırmızı `Olumsuz` rozetleri eklendi. Özet KPI kartına Olumlu ve Olumsuz toplam sayıları eklendi. Ekleme/düzenleme modalına `sonuc_tipi` Select2 alanı eklendi.
  - `views/tanimlamalar/js/is-turu.js`: Modal form sıfırlama ve satır düzenleme olaylarına `sonuc_tipi` entegrasyonu sağlandı.
  - `views/tanimlamalar/api.php`: `is-turu-kaydet`, `is-turu-getir` ve `is-turu-excel-yukle` aksiyonlarına `sonuc_tipi` eklendi.
  - `views/tanimlamalar/is-turu-excel-sablon.php`: Excel dışa aktarım şablonuna `Sonuç Tipi` kolonu eklendi.
- **Model Katmanı (`App/Model/PuantajModel.php`)**:
  - `getComparisonByPeriods()` metoduna `$sonucTipi = 'all'` ('all', 'olumlu', 'olumsuz') parametresi eklendi ve `tn.sonuc_tipi` ile dinamik PDO parametrik filtreleme uygulandı.
  - `getKesmeMonthlyComparisonByDay()` metodu tek seferde `all`, `olumlu` ve `olumsuz` dataset'lerini üretecek şekilde güncellendi.
- **Ana Sayfa ve Modal Entegrasyonu (`views/home.php`, `views/home/api.php`)**:
  - `views/home/api.php`: `get-kesme-comparison` aksiyonu `type`/`sonuc_tipi` parametresi alacak şekilde uyarlandı.
  - `views/home.php`:
    - Kart başlığına `#kesmeCompTypeToggle` (Tümü / Olumlu / Olumsuz) eklendi.
    - `#kesmeDistributionModal` üst pembe gradient başlığına kapat butonu soluna modern cam tasarımlı `.kesme-modal-filter-pills` (Tümü / Olumlu / Olumsuz) yerleştirildi.
    - JS kodunda `switchType()` ile hem modalın üst 4 KPI kartı (`Toplam iş`, `İşlem türü`, `Toplam kayıt`, `Aktif bölge`), hem `İş Sonucu Dağılımı` (yeşil/kırmızı badge), hem `Bölge Katkısı`, hem de ana karttaki tüm metrikler ve tablolar anında senkronize filtrelenecek şekilde kodlandı.

## Mobil SPA: Personel İcra Dosyaları ve Yasal Kesintiler Modülü Tamamlandı (2026-10-17)
- **Kullanıcı Talebi**: "şimdi de icralar sayfasını yapalım" (`/personel/:id/icralar`).
- **Uygulanan Mimari ve Özellikler**:
  1. **Tasarım Standartları (Bento & Claymorphic UI)**:
     - Cari Takip, Ek Ödemeler, Kesintiler ve Evraklar modülleriyle %100 uyumlu Soft Bento & Claymorphic tasarım sistemi uygulandı.
     - **Hero Obsidian Bento Kartı**: Toplam kalan borç, toplam borç tutarı, toplam kesilen bakiye, çift segmentli ilerleme çubuğu (% kesilen / % kalan) ve 4'lü interaktif filtre lejantı (`Tüm Dosyalar`, `Devam Eden`, `Sırada / Bekleyen`, `Tamamlanan`).
     - **Canlı Arama ve Sekme Filtreleri**: Dosya numarası, icra dairesi veya alacaklı adı ile anında arama, filtre hapları (`Tümü`, `Devam Edenler`, `Sıradakiler`, `Tamamlananlar`).
     - **İcra Bento Kartları**: Sıra numarası (#1. Sıra), dosya no, icra dairesi adı, durum rozeti (`clay-badge`), alacaklı kutusu, 3'lü bakiye analizi (`Toplam Borç`, `Kesilen %`, `Kalan Borç`), ilerleme çubuğu, kesinti planı bilgisi (`Sabit Tutar ₺` veya `Maaş Net %`), IBAN kopyalama çipi, not alanı ve işlem butonları.
  2. **İşlevler ve Modallar**:
     - **Yeni Dosya Ekleme & Düzenleme Modalı (`showIcraModal`)**: Aktif icra daireleri listesinden (`icra_daireleri`) otomatik tamamlama, dosya no, sıra, alacaklı, toplam borç, kesinti türü (sabit tutar / net yüzde / asgari yüzde), durum, IBAN, banka bilgisi, tarihler ve açıklama alanları.
     - **Kesinti Geçmişi Modalı (`showIcraKesintiGecmisiModal`)**: İlgili dosyaya ait bordrodan onaylanmış tüm kesintilerin dönem bazında zaman çizelgesi (`personel_kesintileri`), dönem adı, tutar, tarih ve onay rozeti.
     - **Silme İşlemi**: Yumuşak silme (`silinme_tarihi = NOW()`) ve teyit modalı.
  3. **Backend ve Model Mimarisi**:
     - `App/Model/MobilePersonelModel.php`: `getIcraDetails()`, `saveIcra()`, `deleteIcra()`, `getIcraKesintileri()` metotları PDO parametrik binding ile eklendi.
     - `mobile-spa/public/api/personnel.php`: `detail`, `icra_kesintileri`, `save_icra`, `delete_icra` aksiyonları eklendi. Gate yetkilendirmesi (`Gate::allows('personel_duzenle')`), CSRF token ve `SystemLogModel` loglama uygulandı.
     - `mobile-spa/src/services/personnel.js`: API servis fonksiyonları tanımlandı.
     - `mobile-spa/src/views/PersonnelSectionView.vue`: Vue reaktif durumu, şablonu ve özel scoped CSS stilleri eklendi.
  4. **Derleme ve Doğrulama**:
     - `npm run build` ile Vite v6.4.4 üretimi 0 hata ile tamamlandı.
     - PHP dosyalarının sözdizimi `/opt/lampp/bin/php -l` ile doğrulandı.

## Mobil SPA Ana Sayfa: Bugünün Nöbetçileri Personel Fotoğrafı ve Fallback İyileştirmesi (2026-10-17)
- **Kullanıcı Bildirimi**:
  - "bugünkü nöbetçiler kartındaki personel resmi de görünmüoyr" (Yasin İnce'nin avatar kutusunda kırık görsel ve ad-soyad metninin üst üste bindiği ekran görüntüsü).
- **Tespit Edilen Kök Nedenler**:
  1. `App/Model/MobileDashboardModel.php` içindeki `shifts()` metodunda personelin fotoğrafı ham yol (`/assets/images/users/...`) olarak döndürülüyordu. `mobile.ersantr.com` document root'u `mobile-spa/dist` olduğundan bu yol 404 dönüyordu.
  2. Yasin İnce'nin `personel_resim_yolu` alanı boştu; geçerli resmi `resim_yolu` (`assets/images/users/personel_69b2a8e676461.png`) alanında bulunuyordu.
  3. `personnel-photo.php` Gate kontrolünde `nobet_goruntuleme`, `nobet_onaylama`, `nobet_yonetim`, `nobetler` yetkileri eksikti.
  4. `HomeView.vue` nöbetçi kartındaki `<img>` etiketinde `@error="shift.photo = null"` hata yakalayıcısı bulunmadığından kırık görsel ve metin çakışması yaşanıyordu.
- **Uygulanan Çözümler**:
  1. **`MobileDashboardModel.php`**: `shifts()` metodu Kural 12 standardına uygun hale getirildi; ham dosya yolu yerine merkezi `/api/personnel-photo.php?id=` . Security::encrypt() uç noktası bağlandı.
  2. **`personnel-photo.php`**: Gate yetkilerine nöbet görüntüleme ve yönetim yetkileri eklendi.
  3. **`HomeView.vue`**: Nöbetçi avatarındaki `<img>` etiketine `@error="shift.photo = null"` eklendi; yüklenememe veya eksik dosya durumunda kırık görsel yerine temiz gradyanlı baş harf avatarı gösterildi.
  4. **Derleme**: `npm run build` ile Vite v6.4.4 üretimi 0 hata ile tamamlandı.

- **Kullanıcı Bildirimi**:
  - "bu sayfada personellerin resimleri gelmiyor"
  - Konsol hatası: `GET https://mobile.ersantr.com/assets/images/users/personel_69e48d3ba427b.jpg 404 (Not Found)`
- **Tespit Edilen Kök Nedenler**:
  1. `requests.php` API'sinde ham sunucu dosya yolu (`/assets/images/users/...`) dönülüyordu. `mobile.ersantr.com` virtual hostunun kök dizini `mobile-spa/dist` olduğundan, doğrudan yapılan statik resim istekleri 404 Not Found alıyordu.
  2. Veritabanındaki `personel_resim_yolu` alanı (`personel_69e48d3ba427b.jpg`) diskte mevcut değildi; ancak personelin `resim_yolu` alanında geçerli profil fotoğrafı (`personel_69b2a96c4469b.png`) bulunuyordu. Eski kod çoklu aday kontrolü yapmadan diskte olmayan ilk dosyayı döndürüyordu.
  3. Güvenli resim endpoint'i olan `personnel-photo.php` Gate kontrollerinde `talepler`, `avans_talepleri`, `izin_talepleri`, `ariza_talepleri` izinleri yer almıyordu.
  4. Vue kart şablonunda resim yüklenemediğinde `@error` fallback mekanizması olmadığı için kırık resim simgesi görünüyordu.
- **Uygulanan Çözümler**:
  1. **`requests.php` (Avatar Çözümleme)**:
     - `resolvePersonelAvatar()` fonksiyonu yazıldı. Hem `personel_resim_yolu` hem de `resim_yolu` adaylarını diskte varlık kontrolünden geçirerek geçerli dosyayı tespit eder.
     - Geçerli resim bulunduğunda ISO 27001 standardına uygun şifreli `/api/personnel-photo.php?id=` URL'i üretir. Diskte dosya yoksa temiz boş değer döner.
  2. **`personnel-photo.php` (Yetki Genişletme)**:
     - Gate yetkilerine `talepler`, `avans_talepleri`, `izin_talepleri`, `ariza_talepleri` eklendi.
  3. **Symlink Desteği**:
     - `mobile-spa/dist/assets/images` ve `mobile-spa/dist/uploads` sembolik bağları oluşturuldu.
  4. **Vue Arayüz İyileştirmesi (`TaleplerView.vue`)**:
     - `getInitials(name)` fonksiyonu eklendi.
     - `<img>` etiketine `@error="item.avatar = null"` ve `loading="lazy"` eklendi; resmi olmayan veya yüklenemeyen personeller için baş harfler şık biçimde gösterilir.
  5. **Derleme**:
     - `PersonnelSectionView.vue` dosyasındaki eksik `</Teleport>` kapatma hatası giderildi ve `npm run build` 0 hata ile tamamlandı.

## Mobil SPA: Operasyonlar Kartı Online Sorgulama 404 Hatası Giderildi (2026-10-17)
- **Kullanıcı Bildirimi**:
  - "operasyonlar kartındaki sorgula butonu işlem yapmıyor"
  - Konsol çıktısı: `POST https://mobile.ersantr.com/views/puantaj/api.php 404 (Not Found)`
- **Kök Neden**:
  - `mobile.ersantr.com` VirtualHost'unun DocumentRoot'u `/opt/lampp/htdocs/ersan_elk/mobile-spa/dist` olduğundan, masaüstü kök dizinindeki `/views/puantaj/api.php` yolu mobil SPA altında fiziksel olarak mevcut değildi ve 404 Not Found dönüyordu.
  - Ayrıca Kesme/Açma ve Mühürleme sorguları `views/puantaj/api.php` içinde aynı `lock_online_puantaj_sorgula_` kilit anahtarını paylaştığı için paralel isteklerde kilit çakışması riski bulunuyordu.
- **Uygulanan Çözümler**:
  1. **Mobil API Köprüsü (`mobile-spa/public/api/puantaj-sync.php` & `dist/api/puantaj-sync.php`)**:
     - Mobil SPA mimarisine uygun `/api/puantaj-sync.php` köprü dosyası oluşturuldu. Bu dosya masaüstünün `views/puantaj/api.php` mantığını session ve yetki kontrollerini koruyarak doğrudan çalıştırır.
  2. **Servis Katmanı İyileştirmesi (`mobile-spa/src/services/dashboard.js`)**:
     - `syncDailyOperations()` fonksiyonu `/api/puantaj-sync.php` endpoint'ine yönlendirildi.
     - Türkiye saat dilimi (`Europe/Istanbul`) formatı güvenceye alındı.
     - Tüm 4 operasyon türü (Endeks Okuma, Kesme/Açma, Mühürleme, Sayaç Değişimi) lock kilit çakışmalarını önlemek amacıyla ardışık (`for ... of`) sıralamayla çalıştırıldı.
     - Sorgulama bittiğinde taze `getDashboard()` verisi çekilerek arayüzün anında güncellenmesi sağlandı.
  3. **Derleme**:
     - `npm run build` ile Vite üretimi 0 hata ile tamamlandı.

- **Kullanıcı Talebi**: "havuz sekmesinde personellerin resimleri görünmüyor" (ekran görüntüsü ile: avatar kutusunda kırık resim ikonu ve arkasında ad-soyad metni çıkıyordu).
- **Kök Neden Tespiti**:
  1. `mobile-spa/public/api/nobet.php` içinde avatar URL'si ham sunucu yolu (`/assets/images/users/...`) olarak dönülüyordu. SPA alt dizinde (`ersan_elk`) çalıştığından tarayıcı kök dizinde arayıp 404 Not Found alıyordu.
  2. SPA'nın merkezi resim endpoint'i olan `personnel-photo.php` içinde Gate yetki kontrolünde nöbet yetkileri (`nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list`, `shifts`) bulunmadığı için nöbet yöneticisi 403 alıyordu.
  3. `personnel-photo.php` dosyasında `personel_resim_yolu` veritabanında dolu olup diskte bulunmadığında, `resim_yolu` sütunundaki geçerli dosyaya fallback yapılmıyordu (örneğin Serkan Taş'ın resmi bu yüzden açılamıyordu).
  4. Vue tarafında `<img>` etiketinde `@error` handler'ı olmadığı için yüklenemeyen görsellerde tarayıcının kırık görsel ikonu ve alt metni üst üste biniyordu.
- **Uygulanan Çözümler**:
  1. **`personnel-photo.php` Güncellemesi**:
     - Gate yetkilerine `nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list`, `shifts` eklendi.
     - `candidates` dizisiyle hem `personel_resim_yolu` hem de `resim_yolu` diskte kontrol edilerek ilk bulunan geçerli fiziksel görsel sunuldu.
  2. **`nobet.php` API Güncellemesi**:
     - `getAvatarUrl()` fonksiyonu SPA standardı olan `/api/personnel-photo.php?id=` formatına dönüştürüldü; `list`, `calendar`, `personnel_summary` ve `personnel_list` uç noktalarına entegre edildi.
  3. **`NobetOnayView.vue` Güncellemesi**:
     - Havuz, Onay Listesi, Takvim ve Nöbet Planlama modalındaki tüm `<img>` etiketlerine `@error` eklenerek yüklenemeyen görsel durumunda anında şık gradyanlı baş harf avatarına (`initials`) güvenli fallback sağlandı.
  4. **Derleme**:
     - `npm run build` ile Vite bundle'ı 0 hata ile güncellendi ve `dist/api/` dosyaları eşitlendi.
- **Kullanıcı Talepleri**:
  1. "burası daha düzenli gelsin" (Evraklar sayfasındaki unstyled metin yığını yerine düzenli ve ferah görünüm).
  2. "ayrıca personel seçimi bordro sayfaındaki gibi gelsin, değiştir'e basınca 2.resimdeki gibi listes açılsın ve arama kutusu da olsun" (Bordro modülündeki `SEÇİLİ PERSONEL` kartı, `Değiştir ⌵` butonu ve arama kutulu `.period-sheet-list` modal standardı).
- **Uygulanan Çözümler**:
  1. **Bordro Standardında Personel Seçici Kartı (`.person-selector-card`)**:
     - `design-system.css` içine `.person-selector-card`, `.person-selector-card__left`, `.person-selector-avatar-box`, `.person-selector-card__meta`, `.person-selector-sub`, `.person-selector-name`, `.person-mini-status`, `.person-selector-card__right` ve `.person-change-btn` sınıfları entegre edildi.
     - `PersonnelSectionView.vue`: Üstteki eski profil başlığı yerine sol tarafta avatar (tıklanıp büyütülebilen), `SEÇİLİ PERSONEL` etiketi, personel adı, aktiflik rozeti (`● Aktif` / `● Ayrıldı`); sağ tarafta varsa sekme `Kaydet` butonu ve `Değiştir ⌵` butonu yerleştirildi.
     - Kartın sol kısmına veya `Değiştir ⌵` butonuna tıklandığında alttan açılan bottom sheet modalı (`isPersonSheetOpen`) tetikleniyor.
  2. **Arama Kutulu Personel Seçim Modalı (`.payroll-modal-sheet--period`)**:
     - Başlık: `PERSONEL SEÇİMİ`, `Personel Seçin`, `Görüntülemek istediğiniz personeli seçin` ve kapatma butonu (`clay-circle-btn`).
     - Arama Kutusu: `.person-sheet-search-wrap` içinde arama ikonu, `Personel ara (ad, görev, departman)…` inputu ve anlık temizleme (`X`) butonu.
     - Liste: `.period-sheet-list` içinde her bir personel için sol tarafta avatar/baş harf kutusu (`.period-sheet-icon`), isim ve departman/görev bilgisi; sağ tarafta ise `Aktif` / `Ayrıldı` hap etiketi ve seçili personel için mavi dairesel onay imi (`.period-sheet-check`).
     - Personel tıklandığında modal kapanıp ilgili personelin aynı sekmesine anında yönlendiriliyor.
     - `PersonnelDetailView.vue` da aynı bottom sheet modal standardına kavuşturularak SPA genelinde tam tutarlılık sağlandı.
  3. **Evraklar Bölümü Bento Obsidian Hacim Kartı (`PersonnelSectionView.vue` - `/personel/:id/evraklar`)**:
     - Önceden unstyled dikey metin dökülen alan tamamen kaldırılarak Cari Takip / Bento Obsidian standartlarında 4 renkli segmentli dağılım grafiği ve 2x2 interaktif lejant ızgarası (`cari-chart-legend--2x2`) uygulandı:
       - **Özlük & Kimlik** (Açık Mavi - Sky)
       - **Sözleşme & Yasal** (Zümrüt Yeşili - Emerald)
       - **Sağlık & Eğitim** (Mor - Purple)
       - **Diğer Belgeler** (Mercan - Coral)
     - Her lejant kutusu tıklanabilir hale getirilerek ilgili belge grubunun anında filtrelenmesi sağlandı.
     - Altında canlı arama kutusu ve kategori hapları (`clay-filter-pill`) ile kusursuz bir dijital arşiv deneyimi sunuldu.
  4. **Backend Desteği (`MobilePersonelModel.php`)**:
     - `mobileDirectoryQuickList()` metodu içine `avatar` URL alanı eklenerek modal listesindeki personellerin avatarları zenginleştirildi.
  5. **Derleme & Doğrulama**:
     - `npm run build` ile Vite derlemesi 0 hata ile tamamlandı.
     - `/opt/lampp/bin/php -l` ile PHP sözdizimleri doğrulandı.

## Mobil SPA: Personel Fotoğrafı Lightbox, Hızlı Personel Değiştirme ve Evrak Yönetimi (2026-10-17)
- **Kullanıcı Talepleri**:
  1. "1-personel fotografına basınca büyük olarak görmek istiyorum"
  2. "2-personel detay sayfalarında personel adının olduğu yerde personel değiştirme selecti olsun , şık bir tasarım ile"
  3. "3-personel evrakları sayfasını yapalım"
- **Uygulanan Çözümler**:
  1. **Büyük Fotoğraf Görüntüleme (Lightbox Modalı)**:
     - `design-system.css` içine `.clay-lightbox-overlay`, `.clay-lightbox-card`, `.clay-lightbox-image-box`, `.clay-lightbox-avatar-fallback` ve `@keyframes scaleIn` merkezi stilleri eklendi.
     - `PersonnelView.vue` (personel liste görünümü): Avatar kutusuna `@click.stop="openPhotoModal(person)"` eklendi; kart tıklamasından bağımsız olarak avatar tıklandığında büyük fotoğraf modalı açılıyor.
     - `PersonnelDetailView.vue` (detay sayfası): Profil hero avatarına tıklama ile lightbox açılması sağlandı.
     - `PersonnelSectionView.vue` (alt sekmeler): Üst profil avatarına tıklama ile lightbox açılması sağlandı.
     - Fotoğraf bulunmayan personellerde yüksek çözünürlüklü iki harfli degrade avatar fallback'i sağlandı.
  2. **Şık Personel Değiştirme Seçicisi (Personnel Switcher)**:
     - `MobilePersonelModel.php` içine `mobileDirectoryQuickList(int $firmaId): array` metodu eklendi.
     - `personnel.php` API detay yanıtına `personnel_quick_list` eklendi.
     - `design-system.css` içine `.person-switcher-wrap`, `.person-switcher-display`, `.person-switcher-name`, `.person-switcher-pill` ve `.person-switcher-native-select` eklendi.
     - `PersonnelDetailView.vue` ve `PersonnelSectionView.vue`: Personel isminin yerine şık mavi hap (`pill`) içinde isim ve `ChevronDown` ikonu yerleştirildi; tıklandığında yerel mobil sistem seçicisi (native picker) açılarak klavye/dokunmatik gecikmesi olmaksızın firma personelleri listeleniyor.
     - Seçim değiştiğinde `router.push` ile ilgili personelin profiline ve `PersonnelSectionView.vue`'da bulunulan mevcut sekmeye (`calisma`, `izinler`, `evraklar` vb.) anında yönlendirme yapılıyor.
  3. **Personel Evrakları Yönetim Sayfası (`PersonnelSectionView.vue` - `/personel/:id/evraklar`)**:
     - `App/Model/MobilePersonelModel.php`:
       - `getEvrakDetails(int $personelId, int $firmaId): array`: Personelin aktif evraklarını ve kategori dağılımını getirir.
       - `uploadEvrak(int $personelId, array $file, array $data, int $firmaId, int $userId): array`: ISO 27001 standardına uygun olarak dosyayı **AES-256-GCM** (`Security::encryptFile`) ile şifreleyip `uploads/personel_evraklar/{id}/{hash}.enc` yoluna kaydeder, veritabanına ve sistem loguna işler.
       - `deleteEvrak(int $id, int $personelId, int $firmaId, int $userId): array`: Soft delete (`aktif = 0`) uygular ve loglar.
     - `views/personel/evrak-indir.php`: Yetki kontrolü `personel_listesi` ve `personel_goruntule` izinlerine sahip kullanıcıları da kapsayacak şekilde esnetildi; şifreli dosyaları hafızada anlık çözerek (`Security::decryptFile`) tarayıcıda önizleme ve indirme imkanı sağlandı.
     - `mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`: `upload_evrak` ve `delete_evrak` endpoint'leri eklendi.
     - `mobile-spa/src/services/personnel.js`: `uploadEvrak` ve `deleteEvrak` API metodları tanımlandı.
     - `PersonnelSectionView.vue`:
       - Obsidian Bento Hacim Kartı (Toplam evrak adedi ve kategori dağılım hapları).
       - Canlı Arama ve Kategori Filtreleme hapları (`clay-filter-pill`).
       - Evrak kartları: Dosya türü rozeti (PDF, IMG, DOC, XLS), evrak adı, kategori hapı, orijinal dosya adı, yüklenme tarihi, yükleyen kişi, boyut.
       - Güvenli görüntüleme (yeni sekmede / inline deşifre), indirme ve silme butonları.
       - Yeni Evrak Yükleme Bottom-Sheet Modalı: Evrak türü seçimi, başlık, açıklama, Dropzone dosya yükleyici ve ISO 27001 güvenlik rozeti.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile Vite derlemesi 0 hata ile tamamlandı.
     - `/opt/lampp/bin/php -l` ile tüm PHP dosyalarının sözdizimi doğrulandı.

## Mobil SPA Raporlar: Varsayılan Dönem Olarak İçinde Bulunulan Ay ve Akordiyon Detaylarının Kapalı Başlaması (2026-10-17)
- **Kullanıcı Talebi**: "Burda varsayilan donem, icinde bulundugumuz ay olarak, bolgelerde detay kisimlari kapali olarak gelse daha iyi olur" (Ekran görüntüsünde `30.09.2026 - 17.10.2026` tarih alanı ve `Afşin` altındaki açık personel kartları işaretlendi).
- **Kök Nedenler**:
  1. `reports.js` içinde `new Date(year, month, 1).toISOString().slice(0, 10)` çağrısı, yerel saat dilimindeki (UTC+3) gece yarısını UTC'ye çevirirken önceki günün akşamı olan `2026-09-30` tarihini üretiyordu.
  2. Bitiş tarihi ise `today` (`17.10.2026`) olarak sınırlandırıldığından dönem 30.09.2026 - 17.10.2026 olarak görünüyordu; oysa kullanıcı rapor döneminin içinde bulunulan tam ay (`01.10.2026 - 31.10.2026`) olmasını istedi.
  3. `ReportsView.vue` içindeki bölge gözlemcisi (`watch(regions)`) ilk 3 bölgeyi (`idx < 3`) varsayılan olarak açık getiriyordu (`expandedRegions[r.name] = true`).
- **Uygulanan Çözümler**:
  1. **Saat Dilimi Kaymasız Yerel Ay Aralığı (`mobile-spa/src/services/reports.js`)**:
     - `formatLocalYmd()` ve `getCurrentMonthRange()` fonksiyonları tanımlandı.
     - Varsayılan başlangıç tarihi ayın ilk günü (`01.10.2026`), bitiş tarihi ise ayın son takvim günü (`31.10.2026`) olarak ayarlandı. UTC kaymaları tamamen engellendi.
     - `computePresetDates('buay')` doğrudan içinde bulunulan ayın tam takvim aralığını dönecek şekilde güncellendi.
  2. **API Varsayılan Bitiş Tarihi (`mobile-spa/public/api/reports.php` & `dist/api/reports.php`)**:
     - `buay` filtresinde `$endDateStr = date('Y-m-t');` atanarak ayın son gününe kadar olan verileri kapsaması sağlandı.
     - `date_range_label` çıktısı `01.10.2026 – 31.10.2026` olarak doğrulandı.
  3. **Akordiyon Detaylarının Varsayılan Olarak Kapalı Başlaması (`mobile-spa/src/views/ReportsView.vue`)**:
     - Bölgeler yüklendiğinde tüm bölgelerin detay kısımları kapalı (`expandedRegions[r.name] = false`) olarak başlatıldı.
     - Sekme değiştiğinde (`activeTab`) durum haritası sıfırlanarak yeni sekmede de bölgelerin kapalı başlaması sağlandı.
     - Arama yapıldığında eşleşen bölgeler otomatik genişletilip arama temizlendiğinde tekrar kapalı duruma getirilmesi korundu.
     - `selectMonth` fonksiyonunda bugüne kısıtlama kaldırıldı; seçilen ay tam takvim ayı olarak uygulanıyor.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile Vite derlemesi başarıyla tamamlandı.
     - PHP CLI ve cURL ile `01.10.2026 – 31.10.2026` çıktısı ve JSON yapısı doğrulandı.


## Mobil SPA Ana Sayfa: Kart Gizleme Temizliği, Kompakt Sorgula İkonu ve Bildirim Butonu İyileştirmesi (2026-10-17)
- **Kullanıcı Talepleri**:
  1. "kartlardaki gizleme butonlarıı kaldıralım, kartları düzenle modalindan gizlesinler": Kart başlıklarındaki kırmızı `EyeOff` / gizleme butonlarının kaldırılması; gizleme/gösterme işlemlerinin yalnızca "Kartları Düzenle" modalindeki switch kontrolleri üzerinden yapılması.
  2. "sorgula bıtonu yalnızca ikon olsun": Operasyonlar kartındaki `Sorgula` butonundaki metnin kaldırılarak dairesel, kompakt bir `RefreshCw` ikon butonuna dönüştürülmesi.
  3. "bildirim ikou ana sayfada gelmiyor": Ana sayfadaki TopBar'da bildirim çanının (`Bell`) ve kırmızı sayaç rozetinin eksiksiz görünmesi.
- **Uygulanan Çözümler**:
  1. **Gizleme Butonlarının Temizlenmesi (`HomeView.vue`)**:
     - `shifts` (Nöbetçiler), `vehicles` (Araç & Filo), `operations` (Operasyonlar), `quick_actions` (Hızlı Erişim) ve `announcements` (Kurumsal Duyurular) kart başlıklarındaki `card-inner-btn--danger` (`EyeOff`) butonları tamamen kaldırıldı.
     - Gizleme/açma kontrolleri "Kartları Düzenle" modalinde ve alttaki "Gizlenen Kartlar Çubuğu"nda eksiksiz çalışır durumda bırakıldı.
  2. **Yalnızca İkon Olan Sorgula Butonu (`HomeView.vue` & `design-system.css`)**:
     - `HomeView.vue` içindeki `.op-sync-all-btn` butonundan metin (`Sorgula` / `Sorgulanıyor…`) kaldırıldı; sadece dönen/statik `<RefreshCw :size="12" />` ikonu bırakıldı.
     - `design-system.css` içinde `.op-sync-all-btn` 26x26px tam dairesel (`border-radius: 50%; padding: 0; display: inline-flex; justify-content: center;`) kompakt bir ikon butonuna dönüştürüldü.
  3. **Bildirim Çanı ve Profil Avatarı Entegrasyonu (`HomeView.vue` & `design-system.css`)**:
     - `design-system.css` içine `.topbar-bell-btn` ve `.topbar-bell-badge` (kırmızı hap sayaç rozeti) global stilleri eklendi.
     - `HomeView.vue` TopBar slotunda sağ üst kısma `clay-topbar-edit-btn` ("Kartları Düzenle"), `topbar-bell-btn` (bildirim çanı ve sayaç rozeti) ve `clay-avatar-circle` (profil avatar butonu) sırasıyla yerleştirildi.
  4. **Derleme**:
     - `mobile-spa` dizininde `npm run build` çalıştırılarak Vite derlemesi başarıyla tamamlandı (`dist/assets/index-CtSemIDk.js`, `dist/assets/index-ClkJ1_rj.css`).

- **Kullanıcı Bildirimi**: "sayfadaki tablo hala gelmiyor" (Ekran görüntüsünde `index.php?p=personel/puantaj` sayfasının içerik alanı tamamen beyaz/boş).
- **Kök Neden Tespiti**:
  1. `views/personel/puantaj.php` satır 1721'deki `<div class="izin-palette-header">` etiketinin kapanış div'i eksikti. Bu nedenle `puantaj-focus-panel` kapanışı ertelendi ve tablonun kendisi (`.puantaj-grid-card`) yanlışlıkla `.puantaj-focus-panel` içine dahil oldu.
  2. Kullanıcı tablonun sağındaki "üst alanları gizle/genişlet" yukarı okuna (`#btn-puantaj-focus`) bastığında `body.puantaj-focus-mode` sınıfı eklendi ve `localStorage`'a kaydedildi. CSS'teki `body.puantaj-focus-mode .puantaj-focus-panel { max-height: 0; opacity: 0; visibility: hidden; }` kuralı, tablo da bu panelin içinde kaldığı için tablo dahil tüm sayfayı görünmez kıldı.
  3. Dosya sonunda (satır 1983) fazladan bir `</div>` etiketi layout bütünlüğünü bozuyordu.
- **Uygulanan Düzeltmeler**:
  1. `views/personel/puantaj.php`:
     - Satır 1744'e eksik `</div> <!-- end .izin-palette-header -->` eklendi; izin paleti ve filtre panelleri düzgün şekilde kapatıldı.
     - Tablo kartı (`puantaj-grid-card`) focus panelin dışına çıkarıldı; div stack analizi ile tüm açılan 92 div etiketi sıfır hata ile dengelendi (`Remaining open: 0, Extra close: 0`).
     - Dosya sonundaki fazlalık `</div>` etiketi kaldırıldı.
  2. `views/personel/js/puantaj_izin.js`:
     - Eski hatalı `puantaj_focus_mode` `localStorage` kaydı temizlendi (`localStorage.removeItem("puantaj_focus_mode")`), yeni anahtar `puantaj_focus_mode_v2`ye geçirildi ve varsayılan olarak açık (`false`) başlatıldı.
     - Artık kullanıcı yukarı oka bastığında sadece üst filtreler katlanıp tablo boydan boya genişler; tablo asla gizlenmez.
- **Doğrulama**: PHP CLI syntax kontrolü ve cURL ile render testi başarıyla doğrulandı.

## Mobil SPA Ana Sayfa: Operasyon Kutularında Eşit Renkli Kenarlık ve Saf Beyaz Zemin (2026-10-17)
- **Kullanıcı Talebi**: "sol kenar border olmasın yani diğer kenalarlar gibi , ayrıca gradient renk de olmasın, ikonlar ve kenarlar renkli olsun yeter" (Ekran görüntüsünde sol kenardaki kalın şerit işaretlendi).
- **Uygulanan Tasarım Düzeltmesi (`design-system.css`)**:
  - Sol kenardaki `border-left: 3.5px solid ...` kalın vurgusu kaldırıldı.
  - Zeminlerdeki `linear-gradient` kaldırıldı; tüm kutular saf beyaz (`background: var(--ds-surface, #ffffff);`) zemin ile temizlendi.
  - Tüm 4 kenar eşit olacak şekilde zarif renkli çerçeve (`border: 1.5px solid rgba(..., 0.42);`) uygulandı.
  - İkon balonları (`.op-bubble--*`) ve Git butonları (`.op-git--*`) renkli kurumsal kimliğini korudu.
- **Derleme**: `npm run build` ile hatasız derlendi.

## Mobil SPA Puantaj: FM Akordeon Tasarımı ve Aylık Takvim Sığdırma İyileştirmesi (2026-10-17)
- **Kullanıcı Talepleri**:
  1. Hızlı gün statüsü seçici (`status-picker-sheet`) içindeki "Fazla Mesai (FM)" kutusunun akordeon (accordion) olarak açılıp kapanması ve varsayılan olarak kapalı gelmesi.
  2. Personel ay detay modalındaki ("Ay İçi Günlük Takvim") takvimin alt satırının (26-31 günleri) kesilmesi/taşması sorununun giderilmesi, günlerin tam sığması.
- **Uygulanan Çözümler**:
  1. **FM Akordeon Yapısı (`mobile-spa/src/views/PuantajView.vue`)**:
     - `showPickerFmAccordion = ref(false)` state'i entegre edildi; `openDayStatusPicker` ve `closeDayStatusPicker` tetiklendiğinde otomatik `false` sıfırlaması sağlandı.
     - Template içinde `.picker-fm-box` bir akordeona dönüştürüldü: Tetikleyici buton (`.picker-fm-accordion-trigger`) içinde mor saat ikonu, "Fazla Mesai (FM)" başlığı, saat rozeti (`+Xs Mesai` / `(Mesai Yok)`) ve açılma/kapanma yönüne göre 180 derece dönen `ChevronDown` ikonu konumlandırıldı.
     - Akordeon tıklandığında `v-if="showPickerFmAccordion"` ile hızlı saat çipleri (1s - 7.5s, [Kaldır]) ve serbest saat giriş kutusu yumuşak bir border/shadow geçişiyle gösterildi.
  2. **Aylık Takvim Dikey Sığdırma (`clean-calendar-box` & `calendar-tile`)**:
     - `.clean-calendar-box` üzerindeki `overflow: hidden;` kaldırılarak `overflow: visible;` ve `flex-shrink: 0;` uygulandı.
     - `.calendar-tile` için aşırı dikey boy oluşturan `aspect-ratio: 1 / 1.15;` değeri kompakt kareye yakın `aspect-ratio: 1 / 0.95;` oranına çekildi.
     - `.clean-days-header` ve `.calendar-tiles-grid` hücre aralıkları (`gap: 3px;`), gün başlıkları padding'i (`padding: 2px 0 4px;`) ve tile iç boşlukları (`padding: 2.5px 1px 2px;`) optimize edildi.
     - Hızlı Damga Modu kartı (`stamp-card-box`) da dikeyde sıkılaştırılarak (`padding: 10px 12px; gap: 8px;`) takvime ferah alan kazandırıldı; 31 günlük ve 6 haftalık aylarda hiçbir gün kesilmeden tüm ay görünümü tek bakışta sığdırıldı.
  3. **Derleme**:
     - `npm run build` ile Vite v6.4.4 derlemesi 0 hata ile tamamlandı (`dist/assets/index-ATJNUcqG.js`, `dist/assets/index-DipANtRg.css`).

## Mobil SPA Puantaj: Değişken Saatli Fazla Mesai (FM) ve Kesme Uyarısı Entegrasyonu (2026-10-17)
- **Kullanıcı Talebi**:
  - "buradaki yaptığımız ekleme yani fazla mesai işlemlerini @mobile-spa puantaj sayfasında da yapalım"
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Mobil Puantaj API Katmanı (`mobile-spa/public/api/puantaj.php` & `mobile-spa/dist/api/puantaj.php`)**:
     - `use App\Model\PersonelFazlaMesaileriModel;` eklendi ve `$FazlaMesai` modeli örneklendi.
     - `calendar-data` aksiyonunda `$allMesailer = $FazlaMesai->getPersonellerAyMesaileri($firmaId, (int) $yil, (int) $ay);` çekilerek her personele `'overtimes' => $allMesailer[(int) $p->id] ?? (object) []` olarak eklendi.
     - `save-bulk-entries` / `save-bulk` aksiyonunda gelen `overtimes` dizisi işlenerek saat > 0 ise `saveMesai()`, 0 ise `deleteMesai()` çağrıldı; sadece fazla mesai değişikliği yapıldığında da (`empty($data)` kontrolü esnetilerek) kaydın başarıyla tamamlanması sağlandı.
     - `/opt/lampp/bin/php -l` kontrolleriyle her iki dosya doğrulandı.
  2. **Mobil Puantaj Frontend Servis Katmanı (`mobile-spa/src/services/puantaj.js`)**:
     - `saveBulkEntries({ ay, yil, data, overtimes }, csrfToken)` metoduna `overtimes: overtimes || []` parametresi entegre edildi.
  3. **Mobil Puantaj Arayüz ve State Yönetimi (`mobile-spa/src/views/PuantajView.vue`)**:
     - **İkon ve State**: Lucide `Clock` ikonu import edildi. `unsavedOvertimes = ref({})`, `quickOvertimeOptions = [1, 1.5, 2, 3, 4, 5.5, 7.5]`, `customOvertimeInput = ref('')` tanımlandı.
     - `unsavedCount` hem puantaj durumlarını hem de fazla mesai değişikliklerini kapsayacak şekilde güncellendi.
     - `getEffectiveOvertime(personelId, dateStr, serverOvertimes)` ve `applyOvertimeToCell(personelId, dateStr, saat)` helper'ları yazıldı.
     - `savePuantajChanges()` ve `discardUnsavedChanges()` metotlarına `unsavedOvertimes` bağlandı.
     - **Haftalık Kapsüllerde Görünüm**: Kapsülün sağ üst köşesine `+2s`, `+4s` mor overlay rozeti (`.weekly-overtime-pill`) yerleştirildi.
     - **Aylık Takvim Izgarasında Görünüm**: Gün kutucuklarının sağ üst köşesine `+Xs` mor rozeti (`.calendar-tile-overtime-pill`) yerleştirildi. Gün kutucuğuna tıklandığında hem durum hem FM seçilebilmesi için hızlı durum sheet'i (`openDayStatusPicker`) tetiklendi.
     - **Personel Kartı Özeti**: Kartın altındaki metrik satırına ay içi toplam mesai saati (`Mesai: Xs`) eklendi.
     - **Hızlı Gün Statüsü Bottom Sheet (`status-picker-sheet`) Entegrasyonu**:
       - **Kesme Departmanı Uyarısı**: Personelin departmanında "kesme" geçtiğinde (`isKesmeDepartment`) sarı/amber clay uyarı kutusu (`.picker-kesme-alert`) gösterilerek nöbet modülü uyarısı sunuldu.
       - **Fazla Mesai Kartı (`.picker-fm-box`)**: Mevcut mesai saati rozeti, 1s - 7.5s hızlı clay hap butonları, [Kaldır] butonu ve serbest saat giriş kutusu eklendi.
  4. **Derleme ve Doğrulama**:
     - `npm run build` ile Vite v6.4.4 derlemesi 0 hata ile tamamlandı.
     - PHP CLI syntax kontrolleri hatasız.

## Masaüstü Puantaj: Değişken Saatli Fazla Mesai (FM) Çift Katmanlı (Overlay) Girişi ve Bordro Entegrasyonu (2026-10-17)
- **Kullanıcı Talebi**:
  - "bizim puantaj kisminda htc giriyoruz ya, oraya fazla mesaide ekleyebilirmiyiz? Direk fazla mesai yapanlara puantajdan girsek. Ama soyle birsey var fazla mesaiyi suan nobetci olan kesmeciye veriyoruz, o da standart 4 saat yaziliyor. Burda standart degil degisken saat yapabilirmiyiz? Mevcut puantaj türü de korunacak mı mesela hem RTC hem de fazla mesai girebilecek miyiz?" -> "yapalım"
- **Mimari Karar (Çift Katmanlı / Overlay Model)**:
  - Hücredeki ana puantaj türü (X, RTÇ, HTÇ, Yİ vb.) korunur.
  - Değişken saatli Fazla Mesai (FM) değeri, o günün hücresinin üzerine bağımsız bir rozet (`+3s`, `+4.5s` vb.) olarak biner.
  - Böylece bir personel aynı gün hem Resmî Tatil Çalışması (RTÇ) yapabilir hem de akşamına 3.5 saat ek fazla mesai yapabilir; bordroya her iki hakediş de eksiksiz ve ayrı kalemler olarak yansır.
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Veritabanı Şeması (`sql/create_personel_fazla_mesaileri.sql`)**:
     - `personel_fazla_mesaileri` tablosu oluşturuldu.
     - Kolonlar: `id`, `firma_id`, `personel_id`, `tarih`, `saat` (DECIMAL(5,2)), `aciklama`, `onay_durumu`, `kayit_yapan`, `created_at`, `updated_at`, `silinme_tarihi`.
     - Firma ve tarih indeksleri tanımlandı.
  2. **Model Katmanı (`App/Model/PersonelFazlaMesaileriModel.php`)**:
     - `getPersonellerAyMesaileri()`: Toplu puantaj görünümü için tüm personellerin aydaki mesailerini tek sorguda çeker.
     - `getPersonelAyMesaileri()`: Tek personelin ay mesailerini döner.
     - `saveMesai()`: Saat > 0 ise `INSERT ... ON DUPLICATE KEY UPDATE`; saat == 0 ise soft delete (`silinme_tarihi = NOW()`).
     - `deleteMesai()`: Soft delete işlemi.
     - `getDonemToplamMesaiSaati()`: Bordro dönemi için onaylı net toplam saati döner.
  3. **Puantaj API Katmanı (`views/personel/api/puantaj_izin.php`)**:
     - `PersonelFazlaMesaileriModel` entegrasyonu sağlandı.
     - `get-calendar-data` ve `get-personel-month-data` aksiyonlarında `overtimes` verisi döndürülmeye başlandı.
     - `save-bulk-entries` aksiyonu hem izin/çalışma array'ini (`data`) hem de fazla mesai array'ini (`overtimes`) alıp transaction içinde atomik kaydeder.
  4. **Puantaj UI ve JavaScript Katmanı (`views/personel/puantaj.php` & `views/personel/js/puantaj_izin.js`)**:
     - **İzin Paletine Fazla Mesai Butonu & Toggle Saat Kutusu**: `#btn-toggle-fm-mode` (mor buton) eklendi. Saat kutusu (`#fm-hours-input-wrapper`) varsayılan olarak gizlendi ve yalnızca kullanıcı butona tıklayıp modu aktif ettiğinde akıcı biçimde görünür hale getirildi (kapatıldığında veya başka izin seçildiğinde otomatik gizlenir).
     - **Fazla Mesai Standart Bootstrap Modalı (`#fazlaMesaiModal`)**: SweetAlert prompt yerine kullanıcı dostu standart modal geliştirildi; personel adı ve tarihi, Kesme departmanı uyarısı, saat kutusu, hızlı saat butonları (1s, 1.5s, 2s, 3s, 4s, 5.5s, 7.5s), [Mesaiyi Kaldır], [Vazgeç] ve [Kaydet] butonları entegre edildi.
     - **Kesme Departmanı Fazla Mesai Uyarısı**: Kesme departmanındaki personellerin mesaileri normalde Nöbet modülü üzerinden geldiği için; bu personellere puantajdan fazla mesai girilmek istendiğinde modal içinde sarı uyarı kutusu ile bilinçli onay alınması sağlandı.
     - **Puantaj Sürükleme ve Palet Düzeltmesi**: Paletin yüzen bir pencereye dönüşüp tüm div'in tablonun üstüne kayması (`initIzinPalette`) tamamen kaldırıldı, palet yerine sabitlendi; tür sürüklenirken `setDragImage` ile fare ucunda yalnızca küçük tür kutusu (`.izin-box`) görünecek şekilde optimize edildi.
     - **Performans İçin Hover Tooltip İptali**: Binlerce hücrede fare gezinirken performansı düşüren Bootstrap Tooltip dinleyicisi iptal edildi, native title desteğine geçilerek sayfa akıcılığı maksimize edildi.
     - **Hücre İçi Mor Rozet (`.overtime-badge`)**: Hücrenin sağ üst köşesinde zarif mor kapsül rozet (`+2s`, `+3.5s`).
     - **Toplam FM Sütunu**: Puantaj tablosunun sağ tarafına "Toplam FM" sütunu (`sticky-col-right-2`) eklendi; personelin ay içindeki toplam mesai saatini anlık reaktif hesaplar.
     - **State ve Kayıt**: `unsavedOvertimes` haritası ile değişiklikler takip edilir, üstteki "Değişiklikleri Kaydet" butonuna basıldığında sunucuya gönderilir.
  5. **Bordro Hesaplama Motoru Entegrasyonu (`App/Model/BordroPersonelModel.php` & `views/bordro/api.php`)**:
     - `olusturFazlaMesaiOdemeleri()` metodu yazıldı ve `hesaplaMaas()` hattına bağlandı.
     - Kanuni formül: `(Asgari Net / 225) * 1.5 * Toplam Saat` ile personelin net fazla mesai hakedişi hesaplanır. Örneğin asgari ücret neti 28.075,50 ₺ için normal saat ücreti 124,78 ₺ olup, %50 artırımlı net saat ücreti tam olarak **187,17 ₺ / saat**tir.
     - Bordro detay modalında kullanıcı ve yöneticilerin artırımı açıkça görmesi için `187,17 ₺ × X saat (124,78 ₺ × 1,5)` formülü detay satırına işlendi.
     - Resmi brüt tutar gross-up yöntemiyle `(Net Tutar / (1 - SGK - İşsizlik - Damga))` olarak bulunup `personel_ek_odemeler` tablosuna `[Puantaj Fazla Mesai] (X Saat x ... ₺)` formatında resmi ek ödeme olarak işlenir.
     - `getMuhasebeFazlaMesaiNetOzeti()` ve `views/bordro/api.php` içine regex eklenerek nöbet mesaisi ile puantaj mesaisi toplam saat ve tutar olarak birleştirildi, muhasebe Excel'ine ve bordro detay modalına tam entegre edildi.
  6. **Doğrulama**:
     - PHP CLI (`/opt/lampp/bin/php -l`) kontrolleri hatasız.
     - Gross-up ve net hakediş kuruşu kuruşuna CLI'da simüle edilerek doğrulandı.

- **Kullanıcı Talebi**:
  - "kart başlığı düzensiz geldi şimdi" (`media_1792236447288_97036ca8.png` ekran görüntüsü): Hızlı gün statüsü seçici (`status-picker-sheet`) ve personel ay detay modalında (`puantaj-detail-sheet`) handle çizgisinin sola kayması, kapat butonunun aşağı düşmesi ve başlığın çarpık görünmesi sorunu.
- **Kök Neden**:
  - `status-picker-header` bir yatay flex container (`display: flex; justify-content: space-between;`) iken içine `<div class="ds-sheet-handle">` yerleştirilmişti. Bu durum handle'ın sola yaslanmasına, sağdaki kullanıcı bilgileri ve kapat butonunun ise dar alana sıkışıp taşmasına yol açıyordu.
- **Uygulanan Çözümler**:
  1. **Template Hiyerarşisi**:
     - `.ds-sheet-handle` doğrudan `.ds-sheet`'in ilk çocuğu olarak header'ın dışına çıkarıldı ve `margin: 10px auto 4px;` ile tam ortalandı.
     - `status-picker-header` ve `detail-sheet-header` gereksiz iç sarmalayıcılardan arındırılarak doğrudan sol tarafta avatar + kullanıcı adı + tarih/departman meta bilgileri, sağ tarafta ise dairesel kapat butonu (`clay-circle-btn`) olacak şekilde sadeleştirildi.
  2. **CSS Standartları**:
     - `.ds-sheet`: `background: #ffffff; border-radius: 28px 28px 0 0; overflow: hidden;` ile modalın tepeden itibaren tek parça beyaz kavisle açılması sağlandı; arkadaki köşeli basamak hissi tamamen yok edildi.
     - `.status-picker-header`, `.detail-sheet-header`: `display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 8px 18px 12px;` ile kusursuz tek satır simetriye kavuşturuldu.
     - `.ds-sheet-body`: Soft `#f8fafc` zemin rengiyle header'dan ayrılarak içeriklerin pürüzsüz kayması sağlandı.
  3. **Derleme**: `npm run build` ile 0 hata ile derlendi (Vite v6.4.4). `dist/api/puantaj.php` ile `public/api/puantaj.php` senkronize edildi.

- **Kullanıcı Talebi**:
  1. "aylık görünümde günler dışarı taşıyor": Aylık takvim ızgarasındaki Pazar sütununun karttan dışarı taşması sorununun giderilmesi.
  2. "bottom sheet başlığında personel adının ara planında kenarlr köşeli geliyor, sheet başlığında olsun ayrıca bir div olmasın": Sheet'in üst kısmındaki gri zemin üzerinde duran ayrı köşeli beyaz div'in kaldırılması; handle ve personel bilgilerinin doğrudan sheet'in 32px kavisli üst başlığıyla tek parça halinde bütünleştirilmesi.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Sheet Başlığının Bütünleştirilmesi (`.puantaj-detail-sheet` & `.detail-sheet-header`)**:
     - Handle (`.ds-sheet-handle`), personel bilgileri ve kapatma butonu doğrudan `<header class="detail-sheet-header">` içinde toplandı.
     - Ayrı basamaklı köşeli div kaldırıldı; header'a `border-radius: 32px 32px 0 0; background: #ffffff; padding: 10px 18px 14px;` verildi ve `.ds-sheet`'e `overflow: hidden` eklenerek sheet ile %100 kusursuz bir bütünlük sağlandı.
     - Aynı modern bütünleşik yapı `status-picker-sheet`'e de uygulandı.
  2. **Aylık Takvim Hücrelerinin Taşmasının Giderilmesi (`.clean-calendar-box`, `.calendar-tiles-grid`, `.calendar-tile`)**:
     - Grid sütunları `repeat(7, minmax(0, 1fr))` mimarisine geçirilerek çocuk elemanların içeriğe göre sağa taşması (min-width dayatması) engellendi.
     - `.clean-calendar-box` yatay padding'i 10px'e çekilerek 7 sütuna ferah alan açıldı.
     - `.calendar-tile` hücreleri `min-width: 0; box-sizing: border-box; aspect-ratio: 1 / 1.15; overflow: hidden;` ile kompakt, taşmasız ve dengeli hale getirildi.
     - `.calendar-tile-badge` metinleri `8.5px`, `max-width: 90%` ve `text-overflow: ellipsis` ile hücre içine tam sabitlendi.
  3. **Derleme**: `npm run build` ile 0 hata ile derlendi.

## Mobil SPA Kasa Takibi — Cari Takip Obsidian Bento Tasarımı & Önbellek/Build Güncellemesi (2026-10-17)
- **Kullanıcı Geri Bildirimi**: "değişmedi hala aynı" (Ekran görüntüsünde tarayıcının hala eski beyaz zeminli, çift kutulu Kasa Takibi arayüzünü gösterdiği görüldü).
- **Tespit Edilen Kök Nedenler**:
  1. **Eksik/Önceki Derleme Paketi**: Önceki `dist` dizinindeki JS paketinde (`index-C_gAstS6.js`) yeni `kasa-hero-volume-card` bileşeni yer almıyordu ve eski `GÜNCEL NET BAKİYE` şablonu kalmıştı.
  2. **PWA Service Worker Önbelleği (`ersan-mobile-shell-v19`)**: `sw.js` dosyası `v19` olarak kaldığından, istemci tarayıcıları eski shell ve varlık önbelleğini tutmaya devam ediyordu.
  3. **Service Worker Otomatik Güncelleme Eksikliği**: `main.js` içinde `reg.update()` çağrısı bulunmadığından tarayıcı yeni worker'ı hemen algılamıyordu.
- **Uygulanan Çözümler**:
  1. **Temiz Derleme (`vite build`)**: `npm run build` çalıştırılarak yeni üretim paketi (`index-DMCy-om9.js` ve `index-BTm_GUkl.css`) sıfırdan üretildi. Bundle içinde `kasa-hero-volume-card`, `KASA İŞLEM HACMİ`, `Pozitif Denge`, `kasa-segmented-track` bileşenlerinin yer aldığı, eski `GÜNCEL NET BAKİYE` metninin bulunmadığı komut satırından doğrulandı.
  2. **Service Worker Sürüm Yükseltme (`v20`)**: `public/sw.js` ve `dist/sw.js` içinde `CACHE_NAME = 'ersan-mobile-shell-v20'` yapıldı. Bu sayede tarayıcı eski `v19` önbelleğini silip yeni sürümü hemen aktif hale getirecek.
  3. **Anında SW Kontrolü (`main.js`)**: `navigator.serviceWorker.register('/sw.js').then((reg) => { reg.update() })` eklenerek her sayfa açılışında anında güncelleme kontrolü sağlandı.
  4. **Doğrulama ve Git**: Apache üzerinden `curl -I` ile 200 OK yanıtları alındı, değişiklikler `git commit` ve SSH Port 443 üzerinden `git push` ile ana dala gönderildi.

- **Kullanıcı Talebi**:
  1. "burada basınca puantaj türleri açılsın ve oradan seçim yapalım": Haftalık gün kapsüllerine dokunulduğunda döngü yerine tüm izin/çalışma statülerinin listelendiği şık bir seçim modalının açılması ve oradan tek dokunuşla seçim yapılabilmesi.
  2. "ayrıca vizite raporları çekme çalışmıyor": Ekran görüntüsündeki `Class "App\Service\SgkViziteService" not found` hatasının giderilmesi.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **SGK Vizite Servisi Autoload & Namespace Düzeltmesi (`mobile-spa/public/api/puantaj.php` & `dist/api/puantaj.php`)**:
     - `App/Service/SgkViziteService.php` dosyasının global namespace (`\SgkViziteService`) yapısına sahip olması ve PSR-4 autoloader dışında kalması nedeniyle; dosya doğrudan `require_once dirname(__DIR__, 3) . '/App/Service/SgkViziteService.php'` ile dahil edildi ve `new \SgkViziteService(...)` olarak örneklendi.
     - `public/api/puantaj.php` başındaki geçersiz `use App\Service\SgkViziteService;` satırı kaldırıldı.
     - Dosya `dist/api/puantaj.php` ile tam senkronize edildi ve PHP sözdizimi doğrulandı.
  2. **Hızlı Gün Statüsü Seçici Bottom Sheet (`status-picker-sheet` - PuantajView.vue)**:
     - Haftalık gün kapsülüne dokunulduğunda (`handleWeekCellClick -> openDayStatusPicker(personel, dayObj)`) zarif ve modern bir Bottom Sheet açılıyor.
     - **Header**: Personel avatarı, adı, seçili günün tam tarihi (`17 Ekim 2026 Cumartesi`) ve kapatma butonu.
     - **Mevcut Durum Rozeti**: O güne ait kayıtlı statü (örn: `X - Çalışılan Gün`) renkli rozetle gösterilir.
     - **Normal Güne Sıfırla Butonu (`quick-reset-work-btn`)**: Tek tıkla normal çalışılan güne (`X`) dönme seçeneği.
     - **Ücretli & Ücretsiz Sekmeleri (`pickerPaletteList`)**: `Ücretli İzin & Çalışma` ve `Ücretsiz İzin & Rapor` sekmeleri altında tüm tanımlı puantaj türleri (`X`, `HT`, `Yİ`, `Bİ`, `Eİ`, `Üİ`, `RTÇ`, `HTÇ`, `SGK`, `RP`, `Mİ`, `D`, `UZ`, `ÜZ` vb.) 2 sütunlu şık kartlar halinde sunuldu.
     - **Anında Seçim ve Kayıt**: Kullanıcı istediği türe dokunduğunda statü anında güne uygulanır, sheet kapanır, kapsül renklenir, sarı parlama noktası çıkar ve yüzen kaydetme barı anında belirir.
  3. **Derleme**: `npm run build` ile 0 hata ile derlendi.

## Mobil SPA: Kasa Takibi Sayfasının Cari Takip Tasarım Standartlarına Dönüştürülmesi (2026-10-17)
- **Kullanıcı Talebi**: "cari takip sayfasındaki tasarıma benzer tasarım yapalım" (Fotoğraf 1'deki Kasa Takibi sayfasının Fotoğraf 2'deki Cari Takip sayfasının koyu obsidian bento kartı, çift yönlü segmentli barı, oval arama kutusu, hap filtreleri ve kart listesi yapısına dönüştürülmesi).
- **Yapılan İyileştirmeler**:
  1. **TopBar (Başlık ve Aksiyon Çubuğu)**:
     - Cari Takip (`CariView.vue`) ile birebir uyumlu `kasa-topbar`: sol yuvarlak geri butonu (`topbar-btn`), üstte gri küçük `FİNANS & HESAPLAR`, kalın `Kasa Takibi` başlığı, sağda `RefreshCw` yenileme butonu ve siyah yuvarlak `+` (`topbar-btn topbar-btn--primary`) hızlı işlem butonu.
  2. **Hero Obsidian Bento Kartı (`.kasa-hero-volume-card`)**:
     - Siyah koyu zemin (`#191d24`), 28px kavisli köşeler, derin 3D elevation gölge.
     - Başlık: Sol üstte `BarChart3` ikonlu 34px rozet, `KASA İŞLEM HACMİ`, `Tahsilat & Ödeme Dağılım Analizi`, sağda `Pozitif Denge / Negatif Denge` hapı.
     - Tutar: Büyük 26px beyaz Kasa İşlem Hacmi (`totalVolume`), altında Net Kasa Bakiye satırı (`text-emerald` veya `text-debt`).
     - Çift Yönlü Segmentli Bar (`.kasa-segmented-track`): Zümrüt yeşili Gelir (Tahsilat) ve Gökyüzü mavisi Gider (Ödeme) oran çubuğu.
     - Koyu Lejant Izgarası (`.kasa-chart-legend`): `• Top. Gelir (Tahsilat)` ve `• Top. Gider (Ödeme)` için ayrı kutular, yüzdeler, tutarlar ve işlem adetleri.
  3. **Dönem & Arama & Filtreleme (`.kasa-filter-section`)**:
     - Üstte 2 kompakt dönem hapı (Yıl & Ay seçicileri).
     - 999px tam oval beyaz arama çubuğu (`Search` ikonu, temizleme butonu).
     - Cari Takipteki gibi hap tablar (`.kasa-filter-tabs`): `Tümü (879)` (siyah aktif), `• Gelirler (27)` (yeşil noktalı), `• Giderler (852)` (kırmızı noktalı).
  4. **Kasa Kartları Listesi (`.kasa-card`)**:
     - Cari Takipteki `.cari-card` mimarisi (3 kolon: 46px avatar | 1fr bilgi | auto bakiye bloğu).
     - Avatar: 46x46px koyu zeminli 15px kavisli kutu içinde kategorinin 2 harfli baş harfleri (`AK`, `EF`, `MA`, vb.).
     - Bilgiler: 13.5px bold kategori başlığı, alt satırda muhatap hesap, en altta saat ve açıklama/plaka meta satırı.
     - Sağ Tutar Bloğu: `+` / `-` renkli tutar (`text-credit` / `text-debt`), `GELİR` / `GİDER` durum rozeti, hızlı detay butonu (`Eye`) ve `ChevronRight`.
  5. **Kayan Dairesel Butonlar (Floating Buttons)**:
     - Cari Takipteki gibi sağ altta 2 adet üst üste siyah dairesel buton: `History` geçmiş hareketler butonu ve `+` Yeni işlem FAB butonu.
  6. **Cari Takip Standartlarında Bottom Sheet Modalları**:
     - İşlem Ekle/Düzenle: Segmented toggle (Gelir/Gider), ₺ para kutusu, hızlı tutar çipleri (`+250 ₺`, `+500 ₺`, `+1.000 ₺`, `+5.000 ₺`), kategori, hesap, ödeme kanalı, plaka ve açıklama.
     - Detay / Makbuz: Tutar hero kartı, iki sütunlu parametre ızgarası, Düzenle ve Sil butonları.
     - İşlem Geçmişi: Tarih filtreli liste.
  7. **Vite Üretim Derlemesi**: `npm run build` ile 0 hatayla derlendi.

## Mobil SPA: "Daha Fazla" (Diğer) Menüsü Yatay Liste Kartı & Sayfa Başlığı Standardı Dönüşümü (2026-10-17)
- **Kullanıcı Talebi**: "@mobile-spa diğer menüsünde sistem geneline uymayan bir şeyler var ama bilemedim, ikonlar solda , başlık sağda şeklinde mi olsa, sayfa başlıklarındaki tasarım gibi" -> "yapalım"
- **Yapılan İyileştirmeler**:
  1. **Tasarım Analizi & Çözüm**:
     - Mevcut 3 sütunlu mikro ızgara yapısının (`grid-template-columns: repeat(3, 1fr)`) metinleri 2 satıra kırıp 9.5px'e sıkıştırdığı, açıklama ve kategori rehberliği barındırmadığı ve sayfa başlıklarındaki (`TopBar.vue`) dairesel/yatay bilgi mimarisine uymadığı tespit edildi.
     - Kullanıcının önerisi doğrultusunda **ikon solda, kategori (eyebrow) + ana başlık + alt açıklama (subtitle) sağda, sağ uçta chevron oku** olan ferah yatay liste kartı mimarisine geçildi.
  2. **Kategorik Gruplandırma**:
     - 13 modül karmaşık bir ızgara yerine 4 mantıksal bölüme ayrıldı:
       - **FİNANS & MUHASEBE**: E-Fatura & Finans, Kasa Yönetimi, Cari Takip, Bordro.
       - **OPERASYON & SAHA**: Araç Takip, KM Onayları, Evrak Takip, İhbar Yönetimi, Kaçak Kontrol.
       - **PLANLAMA & İK**: Görev Takibi, Nöbet Onay, Puantaj.
       - **SİSTEM & DESTEK**: Destek & Yardım.
  3. **Tipografi ve Stil Standartları**:
     - Sol rozet: 42px dairesel beyaz claymorphic buton (`clay-circle-btn` formu), her modüle uygun pastel arka plan tonu (`emerald`, `purple`, `amber`, `blue`, `coral`, `slate`).
     - Başlık bloğu: 9px uppercase kategori etiketi, 14px kalın ve net modül başlığı, 11px açıklayıcı alt metin.
     - Kapat butonu: Kalın mavi çerçeve yerine TopBar ile birebir uyumlu nötr ve zarif dairesel `clay-circle-btn`.
  4. **Yetkilendirme ve Geriye Dönük Uyumluluk (AGENTS.md)**:
     - `visibleOperationGroups` ve `visibleOperations` ile kullanıcının yetkisi olmayan hiçbir modül veya grup çizilmez (Kural 2).
     - Testlerdeki `.nav-sheet-item` sınıfı ve buton etiketleri aynen korundu.
  5. **Doğrulama & Test**:
     - Otomatik headless Playwright testi ile menü öğelerinin hiyerarşisi, kategori başlıkları, kapat aksiyonu ve odak yönetimi doğrulandı.
     - `npm run build` ile 0 hata derleme alındı (`dist` güncellendi).


- **Kullanıcı Talebi**: "burada başka düzenleme seçenekleri de gelsin" (Giden evrak / Resmî İçerik sekmesinde font seçiminin yanı sıra gelişmiş biçimlendirme ve yazışma araçları).
- **Yapılan İyileştirmeler**:
  1. **Tipografi ve Punto Kontrolleri (Resmî Yazışma Yönetmeliği Standartları)**:
     - Yazı Tipi seçimi: *Times New Roman* (resmî standart) ve *Arial* arasında anlık dinamik geçiş.
     - Punto Boyutu seçimi: Hap formunda `10pt`, `11pt`, `12pt (Standart)`, `14pt (Başlık)` seçenekleri.
  2. **Zengin Metin Biçimlendirme Araç Çubuğu (Formatting Toolbar)**:
     - **Metin Stilleri**: Kalın (`B`), İtalik (`I`), Altı Çizili (`U`), Üstü Çizili (`S`).
     - **Hizalama Araçları**: Sola Yasla, Ortala, Sağa Yasla, **İki Yana Yasla (Justify)** (Resmî yazışma yönetmeliğinde zorunlu).
     - **Listeler ve Girinti**: Madde İşaretli Liste, Numaralı Liste, Girintiyi Azalt (`Outdent`), Girintiyi Artır (`Indent`).
     - **Geçmiş & Temizleme**: Biçimlendirmeyi Temizle (`Eraser`), Geri Al (`Undo`), İleri Al (`Redo`).
     - **HTML Kaynak Kodu Geçişi**: WYSIWYG görsel editör ile ham HTML kaynak görünümü arasında tek dokunuşla geçiş butonu (`Code`).
     - **Mobil Seçim Koruması**: Dokunmatik ekranlarda buton tıklandığında imlecin/seçimin kaybolmaması için tüm araç butonlarına `@mousedown.prevent` uygulandı.
  3. **Hazır Resmî İfadeler (Hızlı Ekleme Çipleri)**:
     - Tek dokunuşla imleç konumuna eklenen standart yazışma bitiş ve başlangıç kalıpları:
       - *"Bilgilerinize arz ederim."*
       - *"Gereğini rica ederim."*
       - *"Bilgilerinizi ve gereğini arz ve rica ederim."*
       - *"İlgide kayıtlı müzekkerenize istinaden;"*
       - *"Borçlu personelin maaşından kanuni 1/4 kesinti yapılmaya başlanmıştır."*
       - *"İlgili personelin şirketimizle ilişiği kesilmiştir."*
       - *"Talep edilen bilgi ve belgeler ekte sunulmuştur."*
  4. **Hazır Resmî Şablonlar Kütüphanesi (`showTemplateModal`)**:
     - Sekme başlığına eklenen `[ BookOpen ] Şablonlar` butonu.
     - Açılan modern alt tabaka (modal sheet) içerisinde 5 adet standart resmî şablon kartı:
       1. *Maaş Haczi Kesintisi Başlatma*
       2. *Borçlu İşten Ayrıldı Bildirimi*
       3. *Öncelikli Haciz Var (Sıraya Alındı)*
       4. *Haciz Kaldırma (Fek) Teyidi*
       5. *Genel Bilgilendirme ve Ek Sunumu*
     - Şablon seçildiğinde evrak konusu ve zengin HTML gövde metni tek tıkla otomatik doldurulur.
  5. **Çift Yönlü Canlı Editör Senkronizasyonu**:
     - `contenteditable` container ile `form.aciklama` arasında anlık reaktif veri senkronizasyonu sağlandı.
     - İcra sihirbazı, AI taslak motoru veya şablon yüklendiğinde editör içeriğini anında tazeleyen `syncEditor()` metodu ve sekme geçiş izleyicisi (`watch(activeTab)`) entegre edildi.
  6. **Derleme & Doğrulama**:
     - `npm run build` ile 0 hata derleme alındı (`dist` başarıyla güncellendi).
- **Kullanıcı Talebi**: "alttaki işlem ekle butonun kaldır, buradaki tasarımı da sistem geneline uygun ama daha kullanıc dostu yap"
- **Yapılan İyileştirmeler**:
  1. **Alttaki Yüzen Butonun Kaldırılması**:
     - `CariDetailView.vue` sayfasındaki alt navigasyon doku üzerinde çakışan ve içeriği kapatan `<aside class="detail-floating-fab">` (ve CSS kuralları) tamamen kaldırıldı. Sayfanın alt boşluğu sistem standardı olan `--ds-nav-clearance` seviyesine çekildi.
  2. **Üst Çubuk (Topbar) & Hızlı Erişim**:
     - `detail-topbar` yapısında uzun cari isimlerinin başlığı taşırıp aksiyon butonlarını ekrandan itmesi `min-width: 0` ve `text-overflow: ellipsis` ile engellendi.
     - Topbar içerisine `+` (Yeni İşlem Ekle) butonu eklendi. Böylece kullanıcı ekranın herhangi bir yerindeyken tek dokunuşla işlem başlatabilir.
     - Liste başlığı yanında yer alan hap formundaki `+ İşlem Ekle` butonu da korundu.
  3. **Hero Profil Kartı & Bento Özet Rozetleri**:
     - Telefon numarası raw rakamlar yerine `formatPhone` ile okunaklı formatlandı (`0536 997 83 81`).
     - Mini özet alanı 3 adet modern Soft Bento metriğine dönüştürüldü (Top. Aldım - Kırmızı nokta/tutar, Top. Verdim - Yeşil nokta/tutar, Net Bakiye / Durum - Dinamik rozet ve tutar).
     - Hızlı aksiyon butonları (Cari Notu, Ara, Paylaş, Ekstre PDF) pastel renkli dairesel kil ikonlarla ve dengeli tipografiyle premium claymorphic görünüme kavuşturuldu.
  4. **"Yeni İşlem Ekle" Modalı (Bottom Sheet)**:
     - Ham radyo butonları yerine belirgin görsel geri bildirim sunan 2'li Segmented Toggle kontrolü getirildi: `Aldım (Borç / Tahsilat)` (Kırmızı vurgulu) ve `Verdim (Alacak / Ödeme)` (Yeşil vurgulu).
     - Tutar girişi belirgin `₺` rozeti, büyük punto (`16px`) ve sayısal klavye moduyla (`inputmode="decimal"`) güçlendirildi; tek dokunuşla eklenebilen hızlı tutar çipleri (`+500 ₺`, `+1.000 ₺`, `+5.000 ₺`, `Tam Bakiye`) eklendi.
     - Açıklama alanına hızlı şablon çipleri eklendi (`Nakit`, `Havale/EFT`, `Fatura Ödemesi`, `Mal/Hizmet`).
     - Tarayıcının varsayılan ham dosya seçicisi (`[ Dosya Seç ] Dosya seçilmedi`) yerine modern kesikli hatlı Bento Dropzone bileşeni getirildi. Dosya seçildiğinde dosya adı, boyutu ve tek tıkla kaldırma butonu (`Kaldır`) sunuldu.
  5. **CariView.vue Uyumlaştırması**:
     - Ana cari listesindeki satırlardan açılan "Yeni İşlem Ekle" modalı da birebir aynı modern yapı ve dropzone ile güncellenerek sistem genelinde tam tutarlılık sağlandı.
  6. **Derleme & Doğrulama**:
     - `npm run build` başarıyla çalıştırıldı (0 hata, 19.36s).

- **Kullanıcı Talebi**: "1-ek ödemelerdeki puantaj primleri dönem bazlı tek kart olarak gelsin, 2- her iki sayfada da dönem bazlı filtre de ekleyelim, ek ödeme ve kesinti dağılım kartları cari takip sayfasındaki gibi olsun ama biraz daha minimal olsun"
- **Yapılan İyileştirmeler ve Geliştirmeler**:
  1. **Cari Takip Tarzı Minimal Obsidian Dağılım Kartı (`.cari-hero-volume-card`)**:
     - `ek_odemeler` ve `kesintiler` sayfalarındaki dağılım kartları, `CariView.vue` sayfasındaki koyu obsidian (`#191d24`) bento kartı mimarisine geçirildi.
     - Aşırı büyük alan kaplamaması için dolgular 16px'e sıkılaştırıldı ve tipografi kompaktlaştırıldı.
     - Kart Başlığı: Sol tarafta 32px pastel ikon kutusu (`Coins` / `Receipt`), 9.5px uppercase kategori etiketi ve 11px alt açıklama; sağ tarafta kompakt yenileme butonu (`RefreshCw`).
     - Tutar Satırı: 24px kalın toplam tutar, altında resmi banka ve elden tutar dağılımı (`Banka: X ₺ · Elden: Y ₺`).
     - Segmentli İlerleme Çubuğu: 12px yüksekliğinde, oransal yüzdeleri (`%X`) gösteren zarif track (`.cari-segmented-track`).
     - Tıklanabilir Filtre/Lejant Kutuları:
       - Ek Ödemeler: 3'lü grid (`Tümü`, `Tek Sefer`, `Sürekli`).
       - Kesintiler: 2x2 grid (`Tümü`, `Tek Sefer`, `Taksitli`, `Sürekli`).
       - Tıklanan filtre kutusu parlayarak aşağıdaki listeyi anında filtreler.
  2. **Dönem Bazlı Filtre Çubuğu (`.period-filter-strip`)**:
     - Hem Ek Ödemeler hem de Kesintiler sayfalarında liste başlığının altına dönem filtresi eklendi.
     - Tüm aktif/kapalı bordro dönemlerini listeleyen native `<select>` ve aktif filtreyi tek dokunuşla sıfırlayan `Temizle` butonu (`X`).
     - Tek seferlik ve taksitli hareketler ilgili döneme göre; sürekli hareketler ise dönemin tarih aralığı (`baslangic_donemi <= dBit && bitis_donemi >= dBas`) esas alınarak filtrelenir.
  3. **Puantaj Primlerinin Dönem Bazlı Tek Kart Olarak Gruplanması**:
     - Aynı döneme ait tüm puantaj kaynaklı primler (`aciklama LIKE '[Puantaj]%'` / `is_puantaj`), ayrı ayrı kartlar yerine **o döneme ait tek bir "Puantaj Primleri" kartı** (`.financial-card--puantaj`) altında toplandı.
     - Kart üzerinde toplam puantaj tutarı, kalem adedi (`X Kalem`) ve dönem bilgisi yer alır.
     - Kart içine entegre edilen "Kalem Dökümünü İncele" akordiyon butonu ile kullanıcı tek dokunuşla puantaj kalemlerini (iş emri sonucu, adet, birim fiyat ve tutar) açıp inceleyebilir.
     - Backend tarafında `MobilePersonelModel::getEkOdemelerDetails` metoduna `is_puantaj` bayrağı eklendi.
  4. **Derleme & Doğrulama**:
     - `mobile-spa` Vite derlemesi çalıştırılarak sıfır hata ile tamamlandı (`dist` güncellendi).

## Mobil SPA Personel Detayı: Maaş ve Finans Menüsünün "Ek Ödemeler" ve "Kesintiler" Olarak Ayrılması & Tam Yönetimi (2026-10-17)
- **Kullanıcı Talebi**: "maaş ve finans menüsünü ek ödemeler ve kesintiler olarak ayıralım, ve bunların işlemlerini tamamlayalım"
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Menü & Modül Ayrımı (`PersonnelDetailView.vue` & `PersonnelSectionView.vue`)**:
     - `PersonnelDetailView.vue` içerisindeki tekil "Maaş ve finans" (`finansal`) modülü kaldırılarak yerine iki bağımsız modül eklendi:
       - **Ek ödemeler** (`ek_odemeler`): Prim, ikramiye, kira ve yardımlar (`icon: BadgeDollarSign`, zümrüt yeşili `#059669`).
       - **Kesintiler** (`kesintiler`): Dönemsel, sürekli ve diğer kesintiler (`icon: Receipt`, mercan kırmızısı `#dc2626`).
     - Modül sayısı rozeti `9 Bölüm` olarak güncellendi.
     - `definitions` objesine `ek_odemeler` ve `kesintiler` tanımları eklendi; eski rota uyumluluğu için `finansal` köprüsü korundu.
  2. **Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - **Ek Ödemeler Metotları**:
       - `getEkOdemelerDetails(int $personelId, int $firmaId)`: Personelin ek ödemelerini (`PersonelEkOdemelerModel`), özet istatistiklerini (toplam ek kazanç, tek seferlik adet/tutar, sürekli adet/tutar, banka/elden tutar), gelir bordro parametrelerini (`BordroParametreModel`) ve açık bordro dönemlerini (`BordroDonemModel`) döner.
       - `saveEkOdeme(array $data, int $firmaId, int $userId)`: Tek seferlik veya sürekli ek ödeme ekleme/güncelleme; kapalı dönem kontrolü; `SystemLogModel::logAction` ile denetim loglaması.
       - `deleteEkOdeme(int $id, int $personelId, int $firmaId, int $userId)`: Kapalı dönem koruması ile soft delete (`silinme_tarihi = NOW()`) ve loglama.
       - `sonlandirEkOdeme(int $id, int $personelId, ?string $bitisDonemi, int $firmaId, int $userId)`: Sürekli ödemeyi belirli bir tarihten itibaren sonlandırma ve loglama.
     - **Kesintiler Metotları**:
       - `getKesintilerDetails(int $personelId, int $firmaId)`: Personelin kesintilerini (`PersonelKesintileriModel`), özet istatistiklerini (toplam kesinti, tek seferlik adet/tutar, taksitli adet/tutar, sürekli adet/tutar, banka/elden tutar), kesinti bordro parametrelerini (`BordroParametreModel`), açık bordro dönemlerini ve personelin devam eden icra dosyalarını (`PersonelIcralariModel`) döner.
       - `saveKesinti(array $data, int $firmaId, int $userId)`: Tek seferlik, taksitli (1-60 taksit) veya sürekli kesinti ekleme/güncelleme; icra dosyası ilişkilendirmesi; kapalı dönem kontrolü ve loglama.
       - `deleteKesinti(int $id, int $personelId, int $firmaId, int $userId)`: Kapalı dönem korumalı soft delete ve loglama.
       - `sonlandirKesinti(int $id, int $personelId, ?string $bitisDonemi, int $firmaId, int $userId)`: Sürekli kesintiyi sonlandırma ve loglama.
  3. **API Katmanı (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `action === 'detail'` içerisine `ek_odemeler`, `ek_odeme_ozet`, `gelir_parametreleri`, `bordro_donemleri`, `kesintiler`, `kesinti_ozet`, `kesinti_parametreleri`, `icra_dosyalari` verileri dahil edildi.
     - POST uç noktaları eklendi: `save_ek_odeme`, `delete_ek_odeme`, `sonlandir_ek_odeme`, `save_kesinti`, `delete_kesinti`, `sonlandir_kesinti`. Tümü CSRF token ve `Gate::allows('personel_duzenle')` yetki doğrulamasıyla korundu.
  4. **Frontend Servisi (`mobile-spa/src/services/personnel.js`)**:
     - `saveEkOdeme`, `deleteEkOdeme`, `sonlandirEkOdeme`, `saveKesinti`, `deleteKesinti`, `sonlandirKesinti` servis fonksiyonları eklendi.
  5. **SPA Arayüzü (`mobile-spa/src/views/PersonnelSectionView.vue`)**:
     - **Ek Ödemeler Ekranı**:
       - **Özet Bento Kartı**: 3'lü Soft Bento Izgarası (Toplam Ek Kazanç - Zümrüt Yeşili, Tek Seferlik - Mavi, Düzenli/Aylık - Kehribar).
       - **Hareketler Kartı**: Sağa tam yaslı `+ Yeni Ek Ödeme` butonu, filtre hapları (Tümü, Tek Seferlik, Sürekli), detaylı Claymorphic hareket kartları (tutar, resmi tutar, hesaplama tipi, dönem/tarih aralığı, banka/elden rozeti, açıklama).
       - **İşlem Aksiyonları**: Düzenle, Sonlandır (sürekli ödemelerde), Sil.
       - **Modallar**: Yeni Ek Ödeme Ekle / Düzenle modal sheet'i (Segmented tür/tip seçiciler, dönem/tarih aralığı, tutarlar, banka/elden kanalı) ve Sürekli Ödeme Sonlandırma modal sheet'i.
     - **Kesintiler Ekranı**:
       - **Özet Bento Kartı**: 3'lü Soft Bento Izgarası (Toplam Kesinti - Mercan Kırmızısı, Tek Seferlik - Mavi, Taksitli & Sürekli - Kehribar).
       - **Hareketler Kartı**: Sağa tam yaslı `+ Yeni Kesinti Ekle` butonu, filtre hapları (Tümü, Tek Seferlik, Taksitli, Sürekli), Claymorphic hareket kartları (tutar, aylık taksit tutarı, icra dosya numarası ve dairesi, dönem/tarih, banka/elden rozeti, açıklama).
       - **İşlem Aksiyonları**: Düzenle, Sonlandır (sürekli kesintilerde), Sil.
       - **Modallar**: Yeni Kesinti Ekle / Düzenle modal sheet'i (Taksit sayısı seçimi, icra dosyası seçimi, dönem, tutar, kesinti kanalı) ve Sürekli Kesinti Sonlandırma modal sheet'i.
  6. **Derleme & Doğrulama**:
     - PHP CLI (`/opt/lampp/bin/php -l`) ile syntax kontrolü yapıldı, 0 hata.
     - Vite derlemesi (`npm run build`) 0 hata ile tamamlandı (`dist` dosyaları güncellendi).

## Mobil SPA Personel Detayı: İzin ve Raporlar Sekmesi (`/personel/:id/izinler`) (2026-10-17)
- **Kullanıcı Talebi**: "izin ve raporlar sayfası ile devam edelim"
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - `getLeaveDetails(int $id, int $firmaId)`: `PersonelIzinleriModel` entegrasyonu ile personelin kanuni yıllık izin hakedişini (`calculateLeaveEntitlement`), kullanılan ve kalan gün bakiyesini, hizmet yıllarına göre hakediş dökümünü (`detay`) ve aktif izin/rapor kayıtlarını (`getPersonelIzinleri`) döner.
     - `getLeaveTypes(int $firmaId)`: `tanimlamalar` tablosundan aktif ve puantaj dışı izin türlerini (Yıllık İzin, Ücretsiz İzin, Rapor, Babalık İzni, Evlilik İzni vb.) ücretli/ücretsiz bilgisiyle çeker.
     - `saveLeave(array $data, int $firmaId, int $userId)`: İzin ekleme ve güncelleme; onay durumu 'Onaylandı' seçildiğinde otomatik seviye 1 onay kaydını `izin_onaylari` tablosuna işler ve `SystemLogModel::logAction` ile loglar.
     - `deleteLeave(int $id, int $personelId, int $firmaId, int $userId)`: Soft delete (`silinme_tarihi = NOW()`, `silen_kullanici = $userId`) uygular ve loglar.
     - `approveOrRejectLeave(int $id, int $personelId, string $status, ?string $note, int $firmaId, int $userId)`: İzin onay veya ret işlemi, `izin_onaylari` seviye 1 onay kaydı oluşturma/güncelleme ve loglama.
  2. **API Katmanı (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `detail` aksiyonuna `leaves`, `entitlement` ve `leave_types` veri alanları dahil edildi.
     - POST aksiyonları eklendi: `save_leave`, `delete_leave`, `approve_or_reject_leave`. Tüm aksiyonlar CSRF token ve `Gate::allows('personel_duzenle')` ile korundu.
  3. **Frontend Servisi (`mobile-spa/src/services/personnel.js`)**:
     - `saveLeave(data, csrfToken)`, `deleteLeave(id, personelId, csrfToken)`, `approveOrRejectLeave(id, personelId, status, note, csrfToken)` metotları tanımlandı.
  4. **SPA Arayüzü (`mobile-spa/src/views/PersonnelSectionView.vue`)**:
     - **Yıllık İzin Bakiyesi Bento Kartı**:
       - 3'lü Soft Bento Metrik Izgarası: Toplam Hakediş (Mavi), Kullanılan İzin (Mercan/Kırmızı), Kalan İzin (Zümrüt/Yeşil - Vurgulu).
       - Başlıkta sağa yaslı **Hakediş Detayı** butonu; tıklandığında açılan alt sayfada (bottom sheet) hizmet yıllarına göre hakediş tarihleri ve gün dökümü.
     - **İzin ve Rapor Hareketleri Kartı**:
       - Başlıkta sağa tam yaslı **`+ Yeni İzin Ekle`** butonu (`.header-action-btn`).
       - Her bir izin/rapor hareketi için Bento kartı: İzin türü adı, Ücretli/Ücretsiz rozeti, Onay Durumu rozeti (`Onaylandı`, `Beklemede`, `Reddedildi`), Tarih aralığı, Gün sayısı rozeti, Yıllık İzne Etki rozeti, açıklama metni ve onaylayan yetkili bilgisi.
       - Hızlı Aksiyonlar: Beklemede olan izinler için tek dokunuşla yeşil Onayla veya kırmızı Ret gerekçesi girme butonları; ayrıca Düzenle ve Silme butonları.
     - **Yeni İzin Ekle / Düzenle Modalı (`clay-modal-sheet`)**:
       - İzin Türü seçimi (tür seçildiğinde ücretli/ücretsiz ve yıllık izne etki durumu otomatik güncellenir).
       - Başlangıç ve bitiş tarih seçicileri (tarihler değiştikçe gün sayısı aralığı otomatik hesaplanır).
       - Süre (gün), onay durumu, yıllık izne etki onay kutusu ve açıklama alanları.
  5. **Derleme & Doğrulama**:
     - `npm run build` ile hatasız üretim derlemesi alındı.
     - CLI üzerinden hem yeni personel hem de çok sayıda izin kaydı bulunan personeller ile hakediş ve liste doğrulaması yapıldı.


## Mobil SPA: Evrak Takip & Ayrı Sekmeli Giden Evrak Sayfası (`/giden-evrak`) (2026-10-17)
- **Kullanıcı Talepleri & Geri Bildirimleri**:
  1. `mobile-spa` burada evrak takip sayfasını yapalım, `mobile` buradan alacağız ancak aynısını yapmak zorunda değiliz daha iyisini daha kullanıcı dostu yap.
  2. `giden evrak sayfası burada olmayacak , @mobile buradaki gibi ayrı bir sayfa ve sekmeli yapı olacak evrak bilgileri, içerik, ekler şeklindeydi`.
  3. `sayfa bozuk geldi` (Tailwind ve material symbols yerine Mobile SPA standart scoped CSS ve Lucide ikonlarına geçiş talebi).
  4. `evrak silerken ayrıca hata verdi` (`Undefined constant App\Model\SystemLogModel::LEVEL_WARNING`).
  5. `sayfa tasarımı evrak sayfasının devamı gibi olmamış` (Evrak Takip listesi ile Giden Evrak düzenleyicisinin tasarım tutarsızlığı: izole beyaz başlık, kutulu sekmeler, gri zeminli inputlar ve alt gezinme çubuğunun [BottomNav] kaybolması).
  6. `sayfa yine bozuk geldi` (Vue scoped CSS yapısı nedeniyle `EvrakView.vue` içindeki form kontrolleri [`.evrak-input`, `.evrak-select`, `.evrak-preset-chips`, `.evrak-form-grid-2`, `.evrak-btn` vb.] stillerinin `GidenEvrakView.vue`'ya geçmemesi ve tarayıcının varsayılan ham siyah çerçeveli inputlarını çizmesi).
- **Uygulanan Tasarım Bütünlüğü & Standartlaştırma (Design Continuity)**:
  1. **TopBar & Kabuk Entegrasyonu**:
     - `router.js` içindeki `chrome: false` parametresi kaldırılarak sayfanın uygulamanın ana kabuğuna (`app-stage`, `app-shell`) ve standart alt gezinme dock'una (`BottomNav`) bağlanması sağlandı.
     - Özel/yabancı header yerine sistemin standart `<TopBar>` bileşeni (`eyebrow="RESMÎ YAZIŞMA & E-İMZA"`, dairesel geri butonu, başlık, alt başlık ve sağ üst köşede PDF Önizleme + Hızlı Kaydet eylem butonları) uygulandı.
  2. **Bento Sihirbaz Kartları (KPI Formatı)**:
     - Üst çubuk altında, `EvrakView` üzerindeki 4'lü Bento KPI kartlarıyla aynı yükseklik, 28px kavis (`--ds-radius-card`), yumuşak gölge ve pastel ikon kutularına sahip 2'li sihirbaz kartı (İcra Sihirbazı - Zümrüt Yeşili ve AI Asistanı - Kehribar Sarısı) yerleştirildi.
  3. **Sekmeler (Clay Filter Pills)**:
     - Kutu içine hapsedilmiş iOS tarzı segmented kontrol yerine, doğrudan slate canvas üzerinde yüzen `.clay-tabs-wrap` ve `.clay-filter-pill` yapısına (`Evrak Bilgileri`, `Resmî İçerik`, `Ekler & E-İmza`) geçildi.
  4. **Form Elemanları ve Bento Kartlar (Scoped CSS İyileştirmesi)**:
     - `EvrakView`'den miras kalmayan tüm form sınıfları (`.evrak-form-group`, `.evrak-form-grid-2`, `.evrak-form-label`, `.evrak-input`, `.evrak-select`, `.evrak-textarea`, `.evrak-preset-chips`, `.evrak-preset-chip`, `.evrak-btn` ailesi, `.evrak-toggle-card`, `.evrak-upload-dropzone`, `.evrak-stepper`) hem `GidenEvrakView.vue` scoped stiline hem de projenin merkezi `design-system.css` dosyasına işlendi.
     - Form kutuları saf beyaz zeminli, 14px kavisli, ince çerçeveli (`#cbd5e1`) ve odaklandığında mavi parlamalı (`#0284c7`) modern form alanlarına dönüştü.
  5. **Modallar ve Adım Geçişleri**:
     - Her sekme kartının altına akıcı ileri/geri adım butonları eklendi.
     - İcra, AI, PDF önizleme ve İmza yetkilisi pencereleri `EvrakView`'in `.evrak-modal-sheet` standardına kavuşturuldu.
  6. **Derleme & Doğrulama**:
     - `npm run build` ile üretim paketi 0 hata ile derlendi (`dist/assets/index-Cx0Go7Qw.css`).
       - ✨ **AI Taslak Sihirbazı**: Gelen evrak görseli/PDF ve talimat vererek otomatik konu, muhatap, ilgi ve içerik metni üreten modal.
       - 📄 **PDF Önizleme Modalı**: Resmî yazının PDF çıktısını mobil ekranda gösteren ve yeni sekmede açan modal.
       - 🛡️ **E-İmza İşlemler Modalı**: İmza sırası takibi, elektronik onay, iade gerekçesi yazma ve geri alma aksiyonları.
     - **Sticky Bottom Action Bar**: "Vazgeç", "Önizle", "Onaya Sun", "İşlemler" ve "Kaydet" butonları.
  3. **Evrak Takip Liste Ekranı (`mobile-spa/src/views/EvrakView.vue`)**:
     - Form modalı yalnızca "Gelen Evrak" için sadeleştirildi, giden evrak geçişleri temizlendi.
     - Hızlı aksiyon butonları (`+ Giden`) ve liste kartlarındaki "Düzenle" / "İncele" butonları doğrudan `/giden-evrak` sayfasına yönlendirildi.
  4. **Yönlendirme (`mobile-spa/src/router.js`)**:
     - `/giden-evrak` ve `/giden-evraklar` rotaları kaydedildi.
  5. **Doğrulama**:
     - PHP sözdizimi `/opt/lampp/bin/php -l` ile hatasız doğrulandı.
     - Vite derlemesi `npm run build` ile 0 hata ile tamamlandı.

## Mobil SPA Kaçak Kontrol: Varsayılan 30 Gün Tarih Aralığı & Yatay Sekme Kaydırma (Wheel + Drag) (2026-10-17)
- **Kullanıcı Talepleri**:
  1. `burada varsayılan olarak son 30 gün gelisin, tümü gelince sayfa yavaş açılıyor` (Sayfa açılışında tüm kayıtlar yerine varsayılan olarak son 30 günün getirilmesi ve sayfanın anında hızlı açılması).
  2. `buradaki sekmeler kaymıyor` (Görselde kırmızı kutuyla işaretlenen durum ve tür sekmelerinin masaüstü/mobilde sağa sola kaydırılamaması sorununun giderilmesi).
- **Uygulanan Düzeltmeler ve Mimari (`KacakView.vue` & `kacak.js`)**:
  1. **Varsayılan Tarih Aralığı (Son 30 Gün)**:
     - `kacakState.startDate` ve `defaultStartDate`: Doğrudan `today - 30 days` (`new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)`) olarak ayarlandı.
     - `KacakView.vue`: `datePreset = ref('30days')` ve `initDates()` içinde `bitis = today`, `baslangic = today - 30 days` tanımlandı.
     - Böylece sayfa ilk açılışta 1000+ kayıt yerine anında son 30 günlük güncel veriyi çekerek son derece hızlı yüklenir.
  2. **Yatay Sekme Kaydırma Çözümü (`.status-pills-scroll` & `.date-preset-group`)**:
     - **Mouse Wheel ile Yatay Kaydırma**: Dikey mouse tekerleği çevrildiğinde bunu yatay `scrollLeft` hareketine dönüştüren `@wheel.passive="handleWheelScroll"` dinleyicisi eklendi.
     - **Mouse ile Tutup Sürükleme (Drag-to-Scroll)**: Masaüstünde fareyle tıklayıp basılı tutarak sağa/sola çekildiğinde doğal bir touch/kaydırma sağlayan `@mousedown="startDragScroll"` fonksiyonu entegre edildi.
     - **CSS Düzeltmeleri**: `flex-shrink: 0 !important; white-space: nowrap !important;` ile butonların sıkışması veya wrap yapması engellendi; `-webkit-overflow-scrolling: touch; touch-action: pan-x; cursor: grab;` özellikleri ile mobilde parmakla, masaüstünde fareyle kusursuz kaydırma sağlandı.
  3. **Derleme**:
     - `npm run build` ile 0 hata ile derlendi.
- **Kullanıcı Talepleri**:
  1. `düzenleme için ayrıca bir butona basmayalım ,düzenleme butonu olmasın yani` (Sayfa açıldığında formlar doğrudan düzenleme modunda açık gelsin; `[ ✎ Düzenle ]` veya `[ ✕ Vazgeç ]` butonları olmasın).
  2. `sadece üstteki personel kartında kaydet olsun` (Üstteki profil kartının sağ tarafına yerleşik tek bir `[ 💾 Kaydet ]` butonu yerleştirilsin; sayfa içi gereksiz barlar veya alt kaydet butonları olmasın).
  3. `butonlar da sağa yaslı gelsin` ("Ekip Atama Geçmişi" ve "Çalışma Dönemleri Geçmişi" başlıklarındaki `+ Yeni Ekip` ve `+ Dönem Ekle` butonları başlık satırının en sağına tam yaslansın).
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Doğrudan Açık Düzenleme Formları (`PersonnelSectionView.vue`)**:
     - Genel Bilgiler (`genel`): Statik okuma modu ve geçiş butonları (`[ ✎ Düzenle ]`, `[ ✕ Vazgeç ]`, `edit-sticky-top-bar`) tamamen kaldırıldı. Sayfaya girildiğinde doğrudan düzenlenebilir `.form-grid` / `.form-field` bento kartları (Kimlik, İletişim, Kişisel/Aile, Fiziksel/Acil, Sistem/Giriş) açık olarak sunuldu.
     - Çalışma Bilgileri (`calisma`): `v-if="isEditingWork"` / `v-else` ayrımı ve `[ ✎ Düzenle ]` butonu kaldırıldı. "Çalışma Yaşam Döngüsü" kartı en üstte durum rozetleri (Aktif/Pasif, Kıdem, SGK) ile birlikte doğrudan düzenlenebilir form alanları (İşe giriş, çıkış, sınıf, saha takibi, araç, firma, departman, görev, ayrılış nedeni) olarak açık getirildi.
  2. **Profil Kartında Tekil Sağ `[ 💾 Kaydet ]` Butonu**:
     - `.personnel-profile-head` sağ kenarına `.head-actions` ve `.profile-save-btn` yerleştirildi.
     - Aktif sekmenin durumuna göre (`route.params.section === 'calisma' ? saveWorkInfo() : saveGeneral()`) tek tıkla kaydetme işlemi gerçekleştirilir. Kayıt tamamlandıktan sonra formlar açık kalmaya devam eder ve güncel verilerle senkronize olur.
  3. **Sağa Yaslı Başlık Butonları (`.header-action-btn` & `.group-header`)**:
     - `.group-header` flex yapısı `justify-content: space-between; width: 100%;` olarak güncellendi.
     - Başlık ve ikon `.group-header-left` içinde gruplanarak butonlara `.header-action-btn { margin-left: auto !important; }` uygulandı.
     - "Ekip Atama Geçmişi" (`+ Yeni Ekip`) ve "Çalışma Dönemleri Geçmişi" (`+ Dönem Ekle`) butonları kart başlığının en sağına kusursuzca yaslandı.
  4. **Derleme**:
     - `npm run build` ile hatasız üretim derlemesi alındı.


## Mobil SPA: Sistem Geneli ve Personel Detayı Kartlar Arası Boşluk Standardı (16px) (2026-10-17)
- **Kullanıcı Talepleri**:
  1. `tüm sistem genelinde kartlar arasındaki boşluk 16px olsun , liste elemanları hariç`
  2. `personel detay sayfasında değişmedi mesela` (Ekran görüntüsü paylaşılarak bildirildi).
- **Kök Neden Tespiti**:
  1. `PersonnelSectionView.vue` görüntüleme modunda:
     - `.quick-contact-bar` ve `.info-group-card` kartlarında scoped CSS'te `margin-bottom: 14px;` kalmıştı.
  2. `PersonnelSectionView.vue` düzenleme modunda:
     - `.genel-edit-form` ve `.calisma-edit-form` form kapsayıcıları `display: flex; flex-direction: column; gap: 14px;` kullanıyordu.
     - İçindeki `.form-bento-section` kartları aynı zamanda `.bento-card` sınıfına sahip olduğu için global `margin-bottom: 16px` de alıyordu.
     - İkisi toplanınca `14px gap + 16px margin = 30px` gibi orantısız bir boşluk oluşuyordu.
     - Üstteki `.edit-sticky-top-bar` da `margin-bottom: 12px;` barındırdığı için gap ile toplanıp `26px` boşluk üretiyordu.
  3. `PersonnelDetailView.vue` sayfasında:
     - Hero kartı altındaki modül listesi `.detail-modules-section` üzerinde `margin-top: 20px;` vardı; hero kartının `margin-bottom: 16px;`'i ile birleşince 36px boşluk oluşuyordu.
- **Uygulanan Geliştirmeler**:
  1. **`PersonnelSectionView.vue`**:
     - `.quick-contact-bar` ve `.info-group-card`: `margin-bottom: var(--ds-card-gap, 16px) !important;` yapıldı (`:last-child { margin-bottom: 0 !important; }`).
     - `.genel-view-stack` ve `.calisma-view-stack`: `display: flex; flex-direction: column; gap: var(--ds-card-gap, 16px);` yapıldı; doğrudan altındaki bento kartların `margin-bottom: 0 !important;` alması sağlanarak gap ile kart marjin çakışması kesin olarak engellendi.
     - `.genel-edit-form` ve `.calisma-edit-form`: `gap: var(--ds-card-gap, 16px);` yapıldı; altındaki `.form-bento-section` ve `.edit-sticky-top-bar` elemanlarının `margin-bottom` değeri `0 !important;` yapıldı.
     - `.genel-section-container` ve `.calisma-section-container`: `gap: var(--ds-card-gap, 16px);` yapıldı.
  2. **`PersonnelDetailView.vue`**:
     - `.detail-modules-section`: `margin-top: 0;` yapılarak hero kartının alt boşluğuyla tam 16px aralık sağlandı.
  3. **`design-system.css`**:
     - Form ve stack içindeki tüm bento kartlar için genel kural eklendi:
       `[class*="-edit-form"] > .bento-card, [class*="-view-stack"] > .bento-card, .home-sections-stack > .bento-card, .genel-edit-form > .bento-card, .calisma-edit-form > .bento-card { margin-bottom: 0 !important; }`.
       Böylece flex gap ile bento card marjininin çakışıp çift boşluk (30-32px) üretmesi sistem genelinde engellendi.
  4. **Derleme**:
     - `npm run build` hatasız tamamlandı.

## Mobil SPA Bordro: Raporlar Formatında Dönem Seçimi, Gömülü Kanal Hero Kartı ve Filtre Sekmelerinin Kaldırılması (2026-10-17)
- **Kullanıcı Talepleri**:
  1. `bordro sayfasındai dönem seçimi raporlar sayfasındaki dönem seçimi gibi olsun` (Raporlar sayfasındaki takvim ikonlu bento kartı ve 'Değiştir ⌵' butonu gösterildi).
  2. `1-alttaki özet kartları üsttekininin içinde olsun ama üstteki kart büyümeden olsun` (Banka, Elden ve Sodexo kartları gösterilip kırmızı ok ile üstteki siyah Net Dağıtım kartına işaretlendi).
  3. `2-bunu kaldır` (Arama kutusunun altındaki 'Tümü, Hesaplanan, Bekleyen, Banka, Elden / Sodexo' filtre sekme çubuğu kırmızı işaretle gösterildi).
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Raporlar Standardında Dönem Seçim Kartı & Bottom Sheet (`PayrollView.vue`)**:
     - Eski hantal HTML select kutusu kaldırıldı; erişilebilirlik ve test uyumluluğu için DOM'da `.sr-only` gizli select olarak korundu.
     - Raporlar sayfası ile 1:1 uyumlu `.date-range-badge` kartı geliştirildi: Sol tarafta `CalendarRange` ikon kutusu, üstte küçük `Bordro Dönemi` etiketi ve tarih aralığı, altta kalın dönem adı (`EYLÜL 2026`) ve kilit durumu rozeti (`Kapalı` / `Açık`), sağ tarafta ise `Değiştir ⌵` hap butonu yerleştirildi.
     - Karta veya `Değiştir ⌵` butonuna tıklandığında açılan **Dönem Seçici Bottom Sheet (`payroll-modal-sheet--period`)** geliştirildi; dönem listesi, tarih aralıkları, kilit rozetleri ve aktif seçili dönem için mavi onay ikonu eklendi.
  2. **Hero Kartı İçi Kompakt Ödeme Dağıtımı (Kanal Kartları Entegrasyonu)**:
     - Dışarıda bulunan 3 ayrı beyaz kart (`BANKA`, `ELDEN`, `SODEXO`) kaldırılarak doğrudan `.payroll-kpi-hero` siyah kartının içine alındı.
     - Kartın dikeyde büyümesini önlemek için:
       - Kart iç padding'i `14px 16px` olarak sıkılaştırıldı.
       - Üst satırda `TOPLAM NET DAĞITIM` hapı ile birlikte `Hakediş` ve `Kesinti` özetleri yer aldı.
       - Orta satırda büyük net tutar (`2.467.862,67 ₺`) ve personel sayısı rozeti (`102 Personel`) konumlandırıldı.
       - Alt satırda ise 3 kanallı koyu cam efekti (`rgba(255,255,255,0.05)`) mikro kartlar (`BANKA`, `ELDEN`, `SODEXO`) yerleştirildi.
       - Toplam kart yüksekliği **~132px** ile önceki tek başına siyah karttan dahi daha az yer kaplayarak dikeyde muazzam alan tasarrufu sağladı.
  3. **Gereksiz Filtre Sekmelerinin Kaldırılması**:
     - Kullanıcının işaret ettiği `.clay-tabs-wrap.payroll-tabs` ('Tümü', 'Hesaplanan', 'Bekleyen'...) satırı kaldırıldı.
     - Arama kutusu doğrudan kartın altında ferah, temiz ve tekil arama alanı olarak konumlandırıldı.
  4. **Doğrulama ve Derleme**:
     - `design-smoke.cjs` 27 viewport kontrolünün tamamından `PASS` aldı.
     - `bordro-main-v3.png` ve `bordro-period-sheet.png` ekran görüntüleri başarıyla alındı ve görsel doğruluk onaylandı.
     - `npm run build` ile hatasız üretim çıktısı alındı.

## Mobil SPA Personel Detayı: Sticky Barın Scroll Esnasında Sabit Kalması (Floating Sticky Düzeltmesi) (2026-10-17)
- **Kullanıcı Talebi**: "üstte sticky olmalıydı ama yukarı gidiyor" (Kaydırma esnasında barın yukarı kaçtığını gösteren ekran görüntüsü iletildi).
- **Kök Neden & Çözüm**:
  1. **`.app-shell` Overflow Engeli Giderildi**: `mobile-spa/src/styles.css` içindeki `.app-shell`'de yer alan `overflow: hidden`, tarayıcının window scroll konteynerını kopardığı için CSS `position: sticky`'nin çalışmasını engelliyordu. `overflow-x: clip; overflow-y: visible;` yapılarak sticky davranışı serbest bırakıldı.
  2. **Garantili Floating Sentinel & Scroll Mekanizması**:
     - `PersonnelSectionView.vue` içine `stickySentinel` ve `isFloatingSticky` kontrolü eklendi.
     - Sayfa kaydırılıp sentinel tepeye ulaştığı anda bar otomatik olarak `.is-floating` sınıfıyla `position: fixed !important; top: 10px; width: calc(100% - 32px); max-width: 448px; left: 50%; transform: translateX(-50%); z-index: 100;` moduna geçer.
     - Sayfa boyunca (ister 100px ister 5000px kaydırılsın) bar **kesinlikle yukarı kaymaz**, ekranın tepesinde yapışık ve sabit kalır.
     - Form içeriğinin sıçramaması için floating moddayken arkada 56px `sticky-bar-placeholder` boşluk tutucu devreye girer.
  3. `npm run build` ile hatasız üretim derlemesi alındı.

## Mobil SPA Personel Detayı: Düzenleme Input Tasarımlarının Görüntüleme Kartı Standardına Uyarlanması (2026-10-17)
- **Kullanıcı Talebi**: "düzenleme modundaki input tasarımları da bu şekilde gelsin" (Görüntüleme modundaki Kimlik Bilgileri bento kartı görseli paylaşıldı).
- **Yapılan İyileştirmeler**:
  1. **Hücre İçi Soft Bento Kapsayıcı Tasarımı (`.form-field`)**:
     - Önceden input dışındayken asılı duran etiket ve kaba HTML input kutusu yapısı kaldırıldı.
     - Tüm form elemanları (`.form-field`), görüntüleme kartındaki gibi tek parça yumuşak açık mavi-gri kavisli bento hücresi (`background: #f1f4f9; border-radius: 14px; padding: 9px 13px; border: 1.5px solid transparent;`) haline getirildi.
     - Hücrenin en üstünde soluk gri, küçük ve büyük harfli etiket (`color: #7b889b; font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 2px;`) konumlandırıldı.
     - Altındaki `input`, `select` ve `textarea` alanları şeffaf zeminli, kenarlıksız (`border: none; background: transparent; padding: 0;`), kalın ve net siyah metin (`font-size: 14.5px; font-weight: 800; color: #191d24;`) formatına dönüştürüldü.
     - Kutuya tıklandığında (`:focus-within`), hücre arka planı saf beyaza (`#ffffff`) geçerek etrafında ince macOS/iOS mavi çerçeve (`border-color: rgba(10, 132, 255, 0.45); box-shadow: 0 4px 14px rgba(10, 132, 255, 0.08), 0 0 0 3px rgba(10, 132, 255, 0.12);`) ile odaklanır.
  2. **Grup Başlıklarının Birebir Eşitlenmesi (`.group-header`)**:
     - Düzenleme modundaki sade/küçük `01`, `02` rozetleri kaldırılarak, görüntüleme modundaki gibi 34x34px renkli yumuşak ikon kutuları (`IdCard`, `Phone`, `Heart`, `Activity`, `KeyRound`, `BriefcaseBusiness`), kalın `h3` başlık ve altındaki açıklayıcı gri `small` metinleri entegre edildi.
  3. `npm run build` ile hatasız üretim derlemesi alındı.

## Mobil SPA Personel Detayı: Minimal Hero Kartı ve Çalışma Bilgileri Sekmesi (2026-10-17)
- **Kullanıcı Talepleri**:
  1. `tamam güzel olmuş ama bu kart biraz daha minimal olsun` (Personel detay sayfasındaki hero kartı dikeyde çok yer kaplamayacak şekilde daha minimal hale getirilsin).
  2. `2-çalışma bilgilierinden devam edelim` (Masaüstü personel detay modülündeki Çalışma Bilgileri sekmesinde yapılabilen tüm işlemler mobil SPA'da da yerel olarak yapılabilsin).
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Minimal Hero Kartı (`PersonnelDetailView.vue`)**:
     - Avatar 60x60px'den 50x50px'e çekildi, isim ve ünvan yazı boyutları dengelendi.
     - Kaba grid kutuları yerine dikeyde sadece 30px yer kaplayan tek parça yatay `.hero-mini-meta-card` ve `.meta-row` tasarımı uygulandı.
     - Departman, Ekip ve İşe Giriş Tarihi tek bir satırda ikonlu ve hafif gri tonda sıkılaştırıldı; sayfada dikeyde ~120px net alan tasarrufu sağlandı.
  2. **Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - `getWorkDetails($id, $firmaId)`: Personelin güncel işe giriş/çıkış, sgk firması, personel sınıfı, araç, saha takibi, departman, görev, ayrılış nedeni/belgesi ile birlikte `ekip_gecmisi` (`personel_ekip_gecmisi` tablosu, ekip kodları, tarihler, ekip şefi durumu) ve `calisma_gecmisi` (`personel_calisma_gecmisi` tablosu, geçmiş dönemler) listelerini bir arada döndürür.
     - `getWorkOptions($firmaId)`: Atama yapılabilecek aktif ekipler (`ekip_kodlari`), bölgeler (`ekip_bolgeleri`), sınıflar, araç seçenekleri ve SGK firma listesini döndürür.
     - `saveWorkInfo($id, $data, $firmaId)`: Güncel çalışma bilgilerini (`ise_giris_tarihi`, `isten_cikis_tarihi`, `personel_sinifi`, `saha_takibi`, `arac_kullanim`, `sgk_yapilan_firma`, `isten_ayrilis_nedeni`, `departman`, `gorev`) günceller ve log kaydı oluşturur.
     - `saveEkipGecmisi($data, $firmaId)` / `deleteEkipGecmisi($id, $firmaId)`: Yeni ekibe atama veya mevcut atamayı düzenleme/silme (çakışma ve ekip şefliği kontrolleriyle).
     - `saveCalismaGecmisi($data, $firmaId)` / `deleteCalismaGecmisi($id, $firmaId)`: Eski çalışma dönemlerini ekleme/düzenleme/silme.
  3. **API Katmanı (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `action === 'detail'`: `work` ve `work_options` alanları yanıta eklendi.
     - POST uç noktaları eklendi: `save_work_info`, `save_team_history`, `delete_team_history`, `save_work_history`, `delete_work_history`.
     - Tüm aksiyonlarda CSRF doğrulama ve `Gate::allows('personel_duzenle')` yetki kontrolü sağlandı.
  4. **Servis Katmanı (`mobile-spa/src/services/personnel.js`)**:
     - `saveWorkInfo`, `saveTeamHistory`, `deleteTeamHistory`, `saveWorkHistory`, `deleteWorkHistory` API fonksiyonları eklendi.
  5. **Çalışma Bilgileri Arayüzü (`PersonnelSectionView.vue`)**:
     - **Çalışma Yaşam Döngüsü Bento Kartı**: Aktif/Ayrılmış durum rozeti, anlık kıdem hesaplayıcı (`calculateTenure`), işe giriş ve çıkış tarihleri, SGK firması, departman, görev, personel sınıfı, saha takibi ve araç kullanım rozetleri.
     - **Çalışma Bilgilerini Düzenleme Formu**: Yetkili kullanıcı "Düzenle" butonuna bastığında açılan, masaüstündeki tüm alanları içeren Soft Bento form yapısı.
     - **Ekip Atama Geçmişi**: Personelin mevcut ve geçmiş ekip görevlendirmeleri, ekip şefi rozeti, tarih aralıkları; "Yeni Ekibe Ata" modalı, düzenleme ve çakışma kontrollü silme desteği.
     - **Çalışma Dönemleri Geçmişi**: Personelin geçmiş giriş-çıkış dönemleri, SGK firması, ayrılış nedeni; yeni dönem ekleme ve silme modalları.
  6. **Derleme ve Doğrulama**: Vite build hatasız tamamlandı (`npm run build`).

## Mobil SPA Personel Detayı ve Genel Bilgiler Sekmesi (2026-10-17)
- **Kullanıcı Talepleri**:
  1. `@[mobile-spa] personel sayfasında alttaki karttaki bilgiler üsttei özet kartı içinde olsun` (Görselde alttaki Departman, Ekip/Bölge ve İşe Giriş Tarihi kartı kırmızı çerçeveye alınıp üstteki hero bento kartına işaretlenmiş).
  2. `artık personel detay sayfalarındaki bilgileri daha detaylandıracağız, genel bilgilier sekmesinden başlayalım, masaüstü modüldeki yapabildiklerimi genel oalrak burada da yapabilmek istiyorum`
- **Uygulanan Geliştirmeler ve Mimari**:
  1. **Personel Detay Sayfası Hero Bento Kartı Entegrasyonu (`PersonnelDetailView.vue`)**:
     - Alttaki ayrık duran `.detail-facts-bento` kartı kaldırıldı.
     - Departman, Ekip/Bölge ve İşe Giriş Tarihi hücreleri (`hero-facts-grid`), üstteki `.detail-hero-bento` kartının içine, iletişim butonlarının hemen altına zarif bir ayırıcı (`.hero-facts-divider`) ile entegre edildi.
     - Tek ve derli toplu, tam Bento standartlarında bir hero profil kartı oluşturuldu.
  2. **Backend ve Model Katmanı (`App/Model/MobilePersonelModel.php`)**:
     - `mobileDirectoryDetail`: `SELECT p.*` yapılarak personelin tüm alanları çekildi; `Security::decrypt()` ile şifreli alanlar (`tc_kimlik_no`, `kan_grubu`, `kaski_sifre`, `kaski_kullanici_adi`) deşifre edilip döndürüldü.
     - `saveMobileGeneralInfo`: Masaüstündeki genel bilgiler sekmesinde yer alan tüm alanları (Ad Soyad, TC Kimlik No - 11 haneli ve hash, Doğum Tarihi, Cinsiyet, Medeni Durum, Kan Grubu, Anne Adı, Baba Adı, Doğum Yeri İl/İlçe, Ehliyet Sınıfı, Seyahat Engeli, Cep Telefonu 1 ve 2, E-posta, Adres, Ayakkabı No, Beden Üst/Alt, Eşi Çalışıyor Mu, Acil Durum Kişisi Ad/Yakınlık/Tel, Program Şifresi `password_hash`, KASKİ Kullanıcı Adı ve Şifresi `Security::encrypt`) doğrulayan, parametrik binding ile güncelleyen ve `SystemLogModel` kaydı üreten metot eklendi.
  3. **API Katmanı (`mobile-spa/public/api/personnel.php` & `dist/api/personnel.php`)**:
     - `personnelPublicRow`: Tüm `general` nesnesini yapılandırarak döndürür hale getirildi.
     - `action === 'save_general'`: CSRF ve `Gate::allows('personel_duzenle')` yetki kontrolüyle çalışan POST uç noktası eklendi.
  4. **Servis Katmanı (`mobile-spa/src/services/personnel.js`)**:
     - `personnelApi.saveGeneral(id, data, csrfToken)` metodu eklendi.
  5. **Genel Bilgiler Arayüzü (`PersonnelSectionView.vue`)**:
     - **Görüntüleme Modu**: Masaüstündeki gibi gruplandırılmış 5 adet Bento kartı:
       * *Hızlı İletişim Çubuğu*: Tek dokunuşla Ara (`tel:`), WhatsApp (`wa.me`), E-posta (`mailto:`).
       * *Kimlik Bilgileri*: TC Kimlik No (tek tıkla panoya kopyalama), Doğum Tarihi & otomatik yaş hesaplama ("34 Yaşında"), Cinsiyet, Medeni Durum, Kan Grubu rozeti.
       * *İletişim & Adres*: 1. ve 2. Cep Telefonu, E-posta, İkametgah Adresi ("Haritada Aç" Google Maps butonu ve "Adresi Kopyala").
       * *Kişisel & Aile Detayları*: Anne/Baba adı, Doğum yeri İl/İlçe, Ehliyet sınıfı, Seyahat engeli, Eşi çalışıyor mu.
       * *Fiziksel & Acil Durum*: Ayakkabı no, Beden üst/alt, Acil durumda aranacak kişi (Ad Soyad, Yakınlık ve tek dokunuşla arama butonu).
       * *Program & APK Erişimi*: Web giriş şifresi durumu, KASKİ kullanıcı adı ve KASKİ şifresi (Göz ikonuyla göster/gizle ve kopyala).
     - **Düzenleme Modu**: Yetkili kullanıcı (`canEdit === true`) "Düzenle" butonuna bastığında form açılır:
       * Masaüstündeki tüm genel bilgiler alanları dokunmatik dostu Bento inputları ile düzenlenebilir.
       * Yerel OCR ile Belge Okuma Desteği: Kullanıcı cihazından kimlik veya evrak seçerek alanları doldurabilir.
       * Program şifresi değiştirme alanı (şifre göster/gizle ikonu ile).
       * Sticky alt kaydetme çubuğu ("Vazgeç" ve "Değişiklikleri Kaydet").
       * Başarı durumunda anında Toast bildirimi ve verilerin sıçramasız güncellenmesi.
  6. **Derleme ve Doğrulama**: `npm run build` ile 0 hata ile derlendi.

## Mobil SPA Profil Sayfası: Çıkış Butonunun Ayarlar Listesine Entegre Edilmesi (2026-10-17)
- **Kullanıcı Talebi**: "profil sayfasındai çıkış butonu da listenin bir elemanı olsun ayrı buton olarak değil," (gönderilen görselde "Yardım merkezi" satırının altındaki boşluğa ok ile alttaki Güvenli Çıkış butonu işaretlenmiş).
- **Yapılan İyileştirmeler**:
  1. `ProfileView.vue`:
     - Alttaki ayrık duran `.logout-btn` butonu kaldırıldı.
     - `settings-bento-card` içindeki ayarlar listesinin en sonuna bir liste satırı (`settings-row settings-row--danger`) olarak "Güvenli Çıkış Yap" eklendi.
     - Diğer satırlarla tam uyumlu olarak sol tarafta `LogOut` ikonu (`settings-icon settings-icon--danger`), ortada kırmızı metin (`text-danger`) ve sağ tarafta `ChevronRight` ok ikonu konumlandırıldı.
     - `.settings-row--danger` için soft kırmızı ikon arka planı (`var(--ds-danger-soft)`) ve kırmızı metin rengi (`var(--ds-danger-ink)`) uygulandı.
  2. `npm run build`: Vite üretim derlemesi hatasız başarıyla tamamlandı.

## Mobil SPA Ana Sayfa: Kart İçi Kompakt Kontroller, En Üst Düzenle Butonu ve Minimal Personel Kartı (2026-10-17)
- **Kullanıcı Talepleri**:
  1. "kart taşıma işlemleri kartların içinde en üstte olsa daha az yer kaplamış olur"
  2. "kartları düzenle butonu en üstte olsun, buton şeklinde"
  3. "personel kartı biraz daha minimal olsun"
- **Yapılan İyileştirmeler**:
  1. **Kart İçi Kompakt Kontroller (`.card-inner-head`, `.card-inner-controls`)**:
     - Kartların dışındaki bağımsız `home-card-head` başlık satırları kaldırıldı.
     - Taşıma tutamağı (`.card-inner-handle` - `GripVertical`), yukarı (`ChevronUp`), aşağı (`ChevronDown`) ve gizleme (`EyeOff`) butonları her bento kartının kendi üst başlık alanının içine taşındı.
     - Dikeyde her kart için fazladan ~35-40px alan tasarrufu sağlandı, ekran kompakt ve derli toplu hale getirildi.
  2. **En Üstte "Kartları Düzenle" Butonu (`.clay-topbar-edit-btn`)**:
     - `TopBar`'ın sağ üst kısmına beyaz zeminli, gölgeli ve ikonlu belirgin `[ <SlidersHorizontal /> Kartları Düzenle ]` hap butonu yerleştirildi.
     - Sayfanın ortasındaki gereksiz "Size Özel Ana Sayfa" beyaz kutusu kaldırıldı; sayfa doğrudan bento kartlarıyla başlayarak dikey ferahlık kazandı.
  3. **Minimal Personel Kartı (`.personnel-minimal-*`)**:
     - Koyu bento kartının yüksekliği yarı yarıya düşürüldü.
     - Sol tarafta tek bakışta büyük **82** / 84 sahada aktif (%98) oranı, sağ tarafta kompakt "Ekip detayı >" linki.
     - 6px zarif segmented progress bar ve altında 3'lü minimal metrik dökümü (Sahada: 82, İzinli: 2, Geç Kalan: 0).
  4. **Sistem Geneli Kartlar Arası Boşluğun 16px Olarak Standartlaştırılması (Liste Elemanları Hariç)**:
     - Merkezi `:root` tokenı olarak `--ds-card-gap: 16px;` tanımlandı.
     - `.bento-card`, `.clay-section`, `.home-sections-stack`, `.workspace-panel`, `.workspace-profile-card`, `.profile-bento-card`, `.settings-bento-card`, `.task-bento-card`, `.region-accordion-card`, `.kpi-card` dahil sistemdeki tüm ana kart ve bölüm yapılarının dikey aralığı net **16px** olarak sabitlendi.
     - Liste elemanları (personel liste satırları, nöbet satırları, tablo satırları vb.) bu kuraldan hariç tutularak kendi kompakt liste ritmini korudu.
  5. **Derleme**: `npm run build` ile 0 hata ile derlendi.




  1. **Backend / Model Katmanı (`App/Model/MobileDashboardModel.php`)**:
     - `shifts()`: Bugünün nöbetçi personelleri (`nobetler` tablosundan onaylı nöbetçiler, ad soyad, başlangıç/bitiş saati, avatar/fotoğraf, telefon, `Security::encrypt` şifrelenmiş ID).
     - `pendingApprovals()`: Bekleyen talep, avans ve izinlerin dökümü ve toplam bekleyen sayısı.
     - `personnel()`: Toplam aktif, çalışan, izinli ve **geç kalan** (`PersonelHareketleriModel::getGecKalanlarCount`) personeller.
     - `workOperations()` & `readings()`: Sayaç, mühürleme, kesme/açma ve endeks okuma için son güncelleme saatleri (`last_update_isler`, `last_update_sayac`, `last_update_endeks`) ve API aksiyonları.
     - `announcements()`: Kurumsal duyurular için başlık, içerik, tarih, etkinlik tarihi ve görsel desteği.
     - `getCardSettings(userId, firmaId)` & `saveCardSettings(userId, firmaId, order, settings)`: `SettingsModel` üzerinden kullanıcının kişisel kart sırasını (`mobile_dashboard_order`) ve görünürlük ayarlarını (`mobile_dashboard_settings`) veritabanında kalıcı saklama.
  2. **API Katmanı (`mobile-spa/public/api/dashboard.php` & `dist/api/dashboard.php`)**:
     - `POST` isteğinde `action: save_card_settings` ile kart sırası ve gizleme ayarları kaydedilir.
     - `GET` isteğinde nöbetçiler, bekleyen onaylar, geç kalanlar ve kullanıcının kayıtlı tercihleri (`card_preferences`) döndürülür.
  3. **Frontend Servisi (`mobile-spa/src/services/dashboard.js`)**:
     - `saveDashboardSettings(order, settings)` fonksiyonu eklendi.
  4. **Tasarım ve Arayüz (`mobile-spa/src/views/HomeView.vue`)**:
     - **Sürükle - Bırak (Drag & Drop)**: Hem HTML5 Drag and Drop (`draggable="true"`) hem de mobil dokunmatik tutamağı (`.home-drag-handle`, `GripVertical`) ile kartlar pürüzsüzce aşağı/yukarı taşınabilir.
     - **Kart Gizleme / Gösterme**: Her kartın sağında hızlı gizle butonu (`EyeOff`), sayfa altında gizlenen kartlar çubuğu ("X kart gizlendi - Yeniden Göster").
     - **Özelleştirme Modalı / Bottom Sheet (`showCustomizeModal`)**: Kullanıcının tüm kartları liste halinde görüp switch ile açıp kapatabildiği, yukarı/aşağı butonlarıyla sıralayabildiği ve tek tıkla "Varsayılan Düzene Sıfırla" yapabildiği zarif claymorphic bottom sheet.
     - **Kalıcı Saklama**: Sıra veya görünürlük değiştiği anda hem `localStorage`'a anında yazılır hem de arka planda API üzerinden kullanıcının `SettingsModel` ayarına kaydedilir; sayfa yenilendiğinde sıçramasız aynı sırada gelir.
     - **Tüm Verilerin Bento Kartları**:
       - *Kurumsal Duyurular*: Gradient bento kartı + tıklandığında açılan tam metin ve görsel bottom sheet modalı.
       - *Bekleyen Onaylar*: Amber uyarı bento kartı (talep, avans, izin kırılımı, `/talepler` bağlantısı).
       - *Bugünün Nöbetçileri*: Avatar, görev saatleri, tek dokunuşla ara butonu (`tel:`) ve `/nobet-onay` takvim bağlantısı.
       - *Personel Durumu*: Koyu bento kartı + 3 segmentli renkli bar (Sahada, İzinli, Geç Kalan).
       - *Araç & Filo*: Sahada, serviste, boşta dağılımı ve filo bağlantısı.
       - *Günün Operasyonları*: Günlük / Aylık toggle geçişi, son güncelleme zamanı ve detay.
       - *Hızlı Erişim*: Yetkili modüllere 8'li grid tile erişimi.
  5. **Tasarım Sistemi (`mobile-spa/src/design-system.css`)**:
     - `.home-sections-stack`, `.home-card-wrapper`, `.is-dragging`, `.drag-over-top`, `.home-drag-handle`, `.shifts-grid`, `.approvals-bento`, `.switch-toggle`, `.home-sheet-modal` eklendi.
  6. **Derleme**: `npm run build` ile 0 hata ile derlendi, `dist` güncellendi.


## Mobil SPA Saha Talepleri: Açık Yeşil Kompakt "İşleme Al" Butonu ve Üst Rozet Entegrasyonu (2026-10-16)
- **Kullanıcı Talebi**: "işlemde işlemini yeşil butonda yapalım, yukarıdakinin işlevini yani, biraz daha küçük olsun ve işleme al yazsın" (gönderilen görselde üstteki rozet ile alttaki yeşil buton eşleştirilmiş).
- **Yapılan İyileştirmeler**:
  - `TaleplerView.vue`:
    1. **Üst Rozet Temizliği**: Genel talepler (`item.type === 'talep'`) için kartın sağ üst köşesindeki turuncu `İşlemde` rozeti kaldırıldı; durumun yönetimi ve gösterimi doğrudan yeşil butona devredildi.
    2. **Kompakt Boyut (`.act-btn--compact`)**: "İşleme Al" butonu Çözüldü butonundan daha küçük ve kibar olacak şekilde `flex: 0 0 auto`, `height: 34px`, `padding: 0 14px`, `font-size: 11px` ile boyutlandırıldı. "Çözüldü" butonu ise `flex: 1` ana aksiyon genişliğinde korundu.
    3. **Metin ve Durum Gösterimi**: Buton metni her zaman **"İşleme Al"** olarak korundu. Talep `islemde` iken açık yeşil parlak zemin (`#bbf7d0`), yeşil kenarlık (`#22c55e`) ve döner ikon (`RotateCw`) ile aktif durumunu gösterir; tıklandığında talep tekrar beklemeye alınabilir veya işleme alınabilir (çift yönlü toggle).
    4. **Detay Bottom Sheet**: Aynı kompakt yeşil buton ve toggle mekanizması detay çekmecesinde de uygulandı.
    5. **Backend**: `mobile-spa/public/api/requests.php` içine `talep-beklemeye-al` uç noktası eklendi.
  - `npm run build` ile 0 hata ile derlendi.

## Mobil SPA Raporlar Modülü: Kullanıcı Geri Bildirimi Revizyonları (2026-10-16)
- **Kullanıcı Talepleri**:
  1. "üstteki hızlı aralık seçmeyi kaldır, zaten dönem seçimi var"
  2. "2-talepler sayfasındaki gibi sayfaya özel navbar yapalım, iş türlerini (endeks okuma, kesme açma vs.)"
  3. "personel kartı da bölgesel dağılım kartı gibi olsun"
  4. "bölge seçimi acordion şeklinde olsun"
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Hızlı Aralık Kaldırıldı**:
     - Sayfanın üstündeki yatay `Bugün`, `Dün`, `Bu Hafta`, `Bu Ay`, `Geçen Ay`, `Ağustos` hap butonları kaldırıldı.
     - Yerine doğrudan dönem seçimi (`01.08.2026 – 31.08.2026 [Değiştir v]`) kartı bırakıldı; tıklandığında ay ve tarih seçim sheet'i açılıyor.
  2. **Sayfaya Özel Bottom Navbar (Talepler / KM Onayları Tarzı)**:
     - Sayfa içi yatay kayan sekmeler (`report-tab-pill`) kaldırılarak `BottomNav.vue` içine entegre edildi.
     - `BottomNav.vue`:
       - `showRaporlarDrawer`: Kullanıcı `/raporlar` sayfasına geldiğinde alt dock bar otomatik olarak 5 iş türü çekmecesine dönüşüyor (`reports-nav-tabs`):
         - 1. **Okuma** (`Gauge`), 2. **Kesme** (`ZapOff`), 3. **Sayaç** (`Wrench`), 4. **Mühür** (`ShieldCheck`), 5. **Kaçak** (`ShieldAlert`) ve 6. **Kapat** (`X`).
       - Kapat butonuna basıldığında standart 5'li navigasyona dönülür; tekrar açmak için ya alt menüdeki `Raporlar` ikonuna dokunulur ya da sayfa üzerinde yüzen `.floating-reports-dock-btn` ("İş Türleri") butonuna basılır.
       - `design-system.css` içerisine `.reports-nav-tabs`, `.reports-nav-tabs__scroll`, `.reports-nav-tabs__close` ve `.floating-reports-dock-btn` eklendi.
  3. **Bölge Akordiyon Mimarisi (`region-accordion-card`)**:
     - Bölgeler ayrı bir kart olmaktan çıkarılıp interaktif akordiyon yapısına geçirildi.
     - **Akordiyon Başlığı**: Sol tarafta renkli `MapPin` ikonu, bölge adı, ekip kadro sayısı rozeti (`X / Y Ekip`), bölge genel payı (`%Pay`); sağ tarafta bölgenin toplam işlem sayısı ve dönen `ChevronDown` oku. Altında bölgenin genel operasyon payı ilerleme çubuğu.
     - Tıklandığında pürüzsüz açılıp kapanır; tek tıkla "Tümünü Aç / Kapat" kontrolü ve canlı arama yapıldığında aramayla eşleşen bölgelerin otomatik açılması sağlandı.
  4. **Bölgesel Dağılım Formatında Personel Kartları (`personnel-bar-card`)**:
     - Her personelin kartı, kullanıcının beğendiği bölgesel dağılım çubuğu tasarımına dönüştürüldü:
       - Üst Satır: Sıralama madalyası (#1 altın, #2 gümüş, #3 bronz kupa), 32px avatar/baş harfler, kalın personel adı, ekip hapı (`EKİP-136`), sağ tarafta kalın işlem sayısı (`1.050 Okuma`) ve detay oku.
       - İlerleme Çubuğu: Personelin o bölge içindeki üretim hacmini temsil eden zarif renkli ilerleme çubuğu (`.pr-track` ve `.region-fill`).
       - Alt Kırılımlar: Kesme/açma, sayaç sökme ve mühürleme için renkli mini iş türü etiketleri (`Ödeme: 291`, `Aparatla Kesim: 583`, `Açma: 411` vb.).
       - Tıklandığında günlük icmal bottom sheet çekmecesi açılır.
  5. **Derleme & Doğrulama**:
     - `npm run build` ile 0 hata ile derlendi (`dist/assets/index-bIAJJn9S.js` ve `dist/assets/index-DEv26V2i.css`).

## Mobil SPA E-Fatura & Finans Modülü: Akıllı Tekil Arama & UI/UX Pro Max Taslak / Fatura Kes Wizard Yenilemesi (2026-10-16)
- **Kullanıcı Talebi**:
  1. Taslak fatura sayfasında ve yeni fatura kes modalında kullanıcı dostu / UI/UX Pro Max düzenli bir tasarım yapılması.
  2. E-fatura modülündeki sayfalarda hem fatura no hem de ürün/kalem için bulunan iki ayrı arama çubuğunun tek bir akıllı arama inputu haline getirilmesi.
- **Yapılan İyileştirmeler & Çözümler**:
  1. **Tekil Akıllı Arama Çubuğu (`.fatura-search-box--unified`)**:
     - `searchQuery` ve `productSearchQuery` iki ayrı input iken tek bir birleşik akıllı arama çubuğuna dönüştürüldü.
     - Tek input üzerinden Fatura No, Müşteri Ünvanı, VKN ve Ürün / Kalem satırları anında filtreleniyor.
     - Arama metni girildiğinde sağ tarafta tek dokunuşla sıfırlayan temizleme butonu (`X`) eklendi.
     - `loadInvoices` fonksiyonunda hem `params.search` hem de `params.urun_ara` tekil arama değerine bağlanarak backend sorgulaması eksiksiz hale getirildi.
  2. **Modern Bento Boş Durum Tasarımı (`.fatura-empty-bento`)**:
     - Eski düzensiz boş durum kaldırıldı. Çift halkalı claymorphic ikon kutusu (`.fatura-empty-bento__icon-wrap`), açıklayıcı başlık ve açıklama eklendi.
     - Belirgin 46px primer CTA butonu (`+ Yeni Taslak Fatura Oluştur` / `Yeni Fatura Kes`) ve hover/active mikromimikleri uygulandı.
     - Taslaklar sekmesine özel güven rozetleri ("Taslaklar resmiyet taşımaz", "İstediğiniz zaman düzenleyebilirsiniz") entegre edildi.
  3. **Yeni Fatura Kesme Sihirbazı (4 Adımlı Wizard) UI/UX Pro Max Yenilemesi**:
     - **Header & İlerleme Çubuğu**: Çift katmanlı gradient ilerleme çubuğu (`.fatura-stepper-progress-fill`) ve 4 adımlı interaktif stepper; tamamlanan adımlarda checkmark ikonu, aktif adımda primary ışıma efekti.
     - **Adım 1 (Alıcı & Cari)**: Eksik ikon giderildi (`<Building2>` entegre edildi). Arama dropdown'unda baş harf avatarları ve yön okları eklendi. VKN/TCKN alanı içerisine entegre `[Sorgula]` mikro butonu (`.fatura-inline-btn`) ile anlık GİB mükellef sorgulama sağlandı. Mükellef rozeti soft alert olarak yeniden tasarlandı.
     - **Adım 2 (Fatura Ayarları)**: Belge türü için açılır `<select>` yerine modern 2'li Segmented Pill (`E-Fatura` / `E-Arşiv`) getirildi. Fatura tipi için 4'lü chip butonlar (Satış, İade, Tevkifat, İstisna) ve Senaryo için chip butonlar (Ticari, Temel, E-Arşiv) entegre edildi. Seri ve Para Birimi 2 sütunlu grid formunda düzenlendi.
     - **Adım 3 (Kalemler & KDV)**: `#1 Kalem` hap rozeti, çöp kutusu aksiyonu, hızlı ürün seçici hapları, 3 sütunlu miktar-birim-fiyat grid'i ve matrah ile KDV dahil tutarı gösteren satır toplamı kartı eklendi. Kesikli yeni kalem ekleme butonu yerleştirildi.
     - **Adım 4 (Özet & Gönder)**: Termal/bento makbuz kartı (`.fatura-wizard-summary-card`) şeklinde alıcı, VKN, senaryo, tarih, kalem adedi dökümü; matrah, iskonto, KDV ve büyük vurgulu Genel Toplam alanı. Altında EDM Bilişim GİB güvenlik onay rozeti eklendi.
     - **Kaydırma Çubuğu Temizliği**: Mobildeki dikey gri kaydırma çubuğu gizlendi (`scrollbar-width: none; ::-webkit-scrollbar: none;`), dokunmatik kaydırma akıcı hale getirildi.
     - **Aksiyon Araç Çubuğu**: Geri, Taslağı Kaydet ve GİB'e Gönder butonları 44px+ dokunma hedefleri ve belirgin renk hiyerarşisiyle yeniden yapılandırıldı.
  4. **Derleme & Doğrulama**:
     - `mobile-spa` altında `npm run build` çalıştırıldı ve sıfır hata ile üretim paketi derlendi.

- **Kullanıcı Talebi**:
  1. Avans ve izin taleplerinde kısmi onay yapılabilmesi.
  2. Genel talepler sekmesinde "İşleme Al" özelliğinin eklenmesi.
  3. `@mobile` (`mobile/pages/talepler.php`) talepler sayfasındaki eksik özelliklerin `mobile-spa`'ya kazandırılması.
- **İncelenen Referanslar**:
  - `mobile/pages/talepler.php`: Klasik mobil Talepler sayfası (Avans: aynı tutar / farklı tutar, İzin: aynı tarih / farklı tarihler, Genel Talep: işleme al / çözüldü / sil, alt detay bottom sheet).
  - `views/talepler/api.php`: Klasik masaüstü API ve `IzinFormuService` üzerinden Resmî Word DOCX izin formu üretimi.
- **Uygulanan Mimari ve Özellikler**:
  1. **Backend API (`mobile-spa/public/api/requests.php` & `dist/api/requests.php`)**:
     - `use App\Service\IzinFormuService;` eklendi.
     - `action=izin-formu-docx-indir` GET uç noktası eklendi; izin ID'si üzerinden doğrudan Word DOCX belgesi üretip indirme sağlandı.
     - Silinen kayıtlarda (`filter=deleted`) `silinme_aciklama` ve `silinme_tarihi` alanları `approval_note` ve `approval_date` olarak standartlaştırıldı.
  2. **Avans Kısmi Onay Sistemi (`TaleplerView.vue`)**:
     - Kartlarda `[Kısmi Onay]` butonu (`SlidersHorizontal` ikonu, `.act-btn--partial-amber`).
     - Avans Onay Modalı: "Aynı Tutar" ve "Farklı Tutar" segmentli geçişi (`setAvansOnayTipi`). Farklı tutar girildiğinde personele gönderilecek açıklama notunu otomatik oluşturma (`updateAvansOtoMesaj`: "Avans talebiniz X TL olarak uygun görülmüştür.").
     - Buton metni dinamik olarak `Kısmi Onayla` veya `Onayla` olarak güncellendi.
  3. **İzin Kısmi Onay Sistemi (`TaleplerView.vue`)**:
     - Kartlarda `[Kısmi Onay]` butonu (`CalendarClock` ikonu, `.act-btn--partial-blue`).
     - İzin Onay Modalı: "Aynı Tarih" ve "Farklı Tarihler" segmentli geçişi (`setIzinOnayTipi`). Başlangıç ve bitiş tarihi değiştirildiğinde otomatik anlık gün hesaplama (`calculateDays`), canlı süre rozeti (`.duration-live-badge`: "Yeni Onaylanan Süre: X Gün") ve otomatik personele iletilecek açıklama metni ("İzin talebiniz X - Y tarihleri arasında Z gün olarak uygun görülmüştür.").
     - Buton metni dinamik olarak `Kısmi Onayla` veya `Onayla` olarak güncellendi.
  4. **Genel Taleplerde "İşleme Al" ve Durum Yönetimi (`TaleplerView.vue`)**:
     - Kartlarda talep henüz işlemde değilse `[İşleme Al]` butonu (`Play` ikonu, `.act-btn--in-progress`), onay modalı (`confirm`) ile `submitTalepIslemeAl`.
     - İşlemdeki talepler için dönen ikona sahip canlı "İşlemde" rozeti (`RotateCw`, `.spin-icon`, `.status-chip--in-progress`).
  5. **Detay Bottom Sheet Geliştirmeleri (`TaleplerView.vue`)**:
     - **Resmî İzin Formu İndirme**: İzin detayında doğrudan Word DOCX belgesini indiren `[Resmî İzin Formunu İndir (Word DOCX)]` aksiyon butonu (`FileDown` ikonu, `.sheet-doc-btn`).
     - **Fotoğraf Büyütme**: Ekli fotoğrafa dokunulduğunda tam boy yeni sekmede açma (`openPhoto`) ve "Büyütmek için dokunun" ipucu (`ZoomIn` ikonu).
     - **Alt Hızlı Aksiyon Butonları (`.sheet-actions-wrap`)**: Detay sayfası incelenirken altta hızlı işlem butonları:
       - Avans: Reddet, Kısmi Onay, Onayla
       - İzin: Reddet, Kısmi Onay, Onayla
       - Saha Talep: İşleme Al, Çözüldü, Sil
  6. **Derleme & Doğrulama**:
     - `npm run build` ile 0 hata ile derlendi.
     - `/opt/lampp/bin/php -l` ile sözdizimi doğrulandı.

## Mobil SPA Raporlar (İşlem Özetleri & Saha Performansı) Modülü Geliştirmesi (2026-10-16)
- **Kullanıcı Talebi**: "@mobile-spa burada raporlar sayfasını yapalım, mobile buradan alacağız ancak aynısını yapmak zorunda değiliz daha iyisini daha kullanıcı dostu yap, ama bütün özelliklerini yapalım, mobile/index.php?p=raporlar bu sayfayı yapacağız"
- **İncelenen Referanslar**:
  - `mobile/pages/raporlar.php`: Klasik mobil Raporlar sayfası (bugün/ay date picker, 5 sekme: Endeks Okuma, Kesme/Açma, Sayaç Sökme/Takma, Mühürleme, Kaçak Kontrol, arama çubuğu, Chart.js bölge grafiği, personel detay bottom sheet'i).
  - `views/puantaj/rapor-getir-mobil.php`: Kartları ve bölge toplamlarını hesaplayan backend mantığı.
  - `views/puantaj/rapor-mobil-detay.php`: Seçilen personelin seçili tarih aralığındaki günlük işlem dökümünü hesaplayan backend mantığı.
- **Uygulanan Mimari ve Özellikler**:
  1. **Backend API (`mobile-spa/public/api/reports.php` & `dist/api/reports.php`)**:
     - Strict types, session ve firma izolasyonu.
     - Permission 61 (`puantaj/raporlar`), `puantaj_raporlama`, `puantaj_yonetim`, `puantaj/list` ve Superadmin yetki denetimi.
     - `action=cards`: 5 sekmenin (Okuma, Kesme, Sayaç Sökme/Takma, Mühürleme, Kaçak) tüm veri modellerini (`EndeksOkumaModel`, `PuantajModel`, `SayacDegisimService`, `TanimlamalarModel`, `PersonelModel`, `EkipHelper`) sorgulayarak bölge performanslarını, genel toplamları, en başarılı personel ve lider bölge istatistiklerini hesaplar; optimize JSON olarak döner.
     - `action=detail`: Seçilen personelin belirtilen tarih aralığında çalıştığı günleri, gün adlarını, o gün ürettiği tüm işlem türlerini ve dönem toplamını günlük icmal dökümü olarak döndürür.
  2. **Frontend Servisi (`mobile-spa/src/services/reports.js`)**:
     - Reaktif `reportsState` (aktif sekme, hazır tarih aralıkları, custom start/end date, arama sorgusu, seçili bölge filtresi, sıralama ölçütü, sayaçlar, kartlar, bölgeler, detay çekmecesi durumu).
     - `reportsApi` (`getCards`, `getDetail`) metotları ve hazır tarih hesaplama yardımcıları.
  3. **Mobil Görünüm Bileşeni (`mobile-spa/src/views/ReportsView.vue`)**:
     - **Tasarım Standardı**: Rule 10 Soft Bento & Claymorphic UI standartlarına tam uyum, Roboto Condensed fontu, Obsidian ve beyaz bento kartları.
     - **TopBar**: Geri butonu, `OPERASYONEL ANALİZ` eyebrow, `İşlem Özetleri` başlığı ve yenileme aksiyonu.
     - **Dönem Gezinme & Hızlı Filtre Barı**: "Bugün", "Dün", "Bu Hafta", "Bu Ay", "Geçen Ay" ve "Tarih Seç" hap butonları (`.clay-filter-pill`), anlık tarih aralığı etiketi kartı.
     - **5 Sekmeli Slider**: Endeks Okuma (`Gauge`), Kesme / Açma (`ZapOff`), Sayaç Sökme/Takma (`Wrench`), Mühürleme (`ShieldCheck`), Kaçak Kontrol (`ShieldAlert`).
     - **4 Bento KPI Kartı (2x2 Grid)**: Toplam İşlem/Okuma, Aktif / Toplam Ekip Kadrosu, En Yüksek Bölge (yüzde ve adet), Kişi Başı Üretim Ortalaması.
     - **İnteraktif Bölge Performans Dağılımı Kartı**: Bölgelerin toplam işlem sayıları, personel adetleri, yüzdelik renkli segment çubukları. Bölge satırına tıklandığında aşağıdaki listeyi anında sadece o bölgeye göre filtreleme desteği.
     - **Canlı Arama & Çift Yönlü Sıralama Toolbar**: Personel, ekip veya bölge adı üzerinde anında arama, sıralama dropdown'u (En Çok İşlem, En Az İşlem, İsim A-Z, Ekip No).
     - **Personel Performans Kartları**: 1., 2., 3. dereceler için özel Altın/Gümüş/Bronz madalya rozetleri (`Trophy`), avatar/baş harfler, isim, ekip rozeti (`EKİP-102`), bölge pini, büyük net işlem sayacı ve kesme/sökme iş türü kırılım etiketleri.
     - **Personel Günlük İcmal Bottom Sheet Drawer**: Avatar, isim, ekip, bölge, dönem toplamı, çalışılan gün sayısı ve günlük ortalama KPI bandı, iş türü toplamları ve gün gün işlem kırılımı (tarih, gün adı, gün toplamı ve kalemler).
     - **Gelişmiş Tarih Seçici Sheet**: 12 aylık buton matrisi ile tek dokunuşla geçmiş ayları seçebilme, yıl artır/azalt stepper'ı ve özel başlangıç-bitiş tarihi seçip uygulayabilme.
  4. **Derleme & Doğrulama**:
     - `npm run build` ile 0 hata ile derlendi.
     - Apache vhost üzerinden `http://mobile.ersanelektrik.com/api/reports.php` gerçek oturum ile hem kartlar hem detay sorgularında uçtan uca test edildi ve doğrulandı.

## Mobil SPA Bildirim Altyapısı ve Web Push Entegrasyonu (2026-10-16)
- **Kullanıcı Geri Bildirimi**: "@mobile-spa'da neden bildirim gelmiyor"
- **Tespit Edilen Eksiklikler**:
  1. Service Worker'da (`sw.js`) push ve notificationclick dinleyicileri yoktu.
  2. İstemci tarafında `Notification.requestPermission()` ve `pushManager.subscribe()` mekanizması bulunmuyordu.
  3. Push aboneliklerini sunucuya (`push_subscriptions` tablosu) kaydeden bir API uç noktası yoktu.
  4. TopBar'da bildirim zili (`Bell`) ikonu, okunmamış bildirim rozeti ve bildirim listesi/çekmecesi bulunmuyordu.
  5. `ProfileView.vue` sayfasındaki "Bildirim tercihleri" satırı işlevsiz (mock) bir butondu.
- **Uygulanan Çözüm ve Mimari**:
  1. **Backend API (`mobile-spa/public/api/notifications.php` & `dist/api/notifications.php`)**:
     - Strict types, session doğrulama, `BildirimModel`, `PushSubscriptionModel` ve `SystemLogModel` entegrasyonu.
     - Aksiyonlar: `unread-count`, `list`, `vapid-key`, `push-status`, `subscribe`, `unsubscribe`, `mark-read`, `mark-all-read`.
  2. **Service Worker Güncellemesi (`sw.js`)**:
     - `self.addEventListener('push', ...)` ve `self.addEventListener('notificationclick', ...)` eklendi.
     - Bildirime dokunulduğunda ilgili SPA rotasına (`/#/talepler`, `/#/km-onaylari`, `/#/bordro` vb.) akıllı yönlendirme sağlandı.
     - Cache sürümü `ersan-mobile-shell-v19` olarak güncellendi.
  3. **İstemci Servisi (`mobile-spa/src/services/notifications.js`)**:
     - VAPID anahtarı yönetimi, `urlBase64ToUint8Array`, push aboneliği ve iptali (`subscribePush`, `unsubscribePush`).
     - Okunmamış sayaç ve bildirim listesi yönetimi (`fetchUnreadCount`, `fetchNotifications`, `markAsRead`, `markAllAsRead`).
     - 45 saniyelik hafif arka plan polling sayacı (`initNotificationsWatcher`).
  4. **TopBar Zil İkonu (`TopBar.vue`)**:
     - Sağ üst köşeye okunmamış bildirim rozetine (`topbar-bell-badge`) sahip `Bell` ikonu eklendi. Dokunulduğunda bildirim çekmecesini açar.
  5. **Bento Bildirim Çekmecesi (`NotificationsDrawer.vue`)**:
     - Soft Bento & Claymorphic tasarım diline uygun alttan açılan (slide-up sheet) bildirim merkezi.
     - Okunmamış/okunmuş rozetleri, "Tümünü Okundu İşaretle" aksiyonu, tekil okundu işaretleme ve tıklandığında ilgili modüle yönlendirme.
     - Push bildirimleri henüz kapalıysa tek tıkla açmayı sağlayan şık Bento öneri kutusu.
  6. **Profil Ekranı Entegrasyonu (`ProfileView.vue`)**:
     - "Bildirim tercihleri" satırına anlık durum rozeti (Açık, Kapalı, Engellendi, Desteklenmiyor) bağlandı.
     - Tıklandığında açılan Bento ayar modalı üzerinden cihaz bildirimleri açma/kapama ve bildirim geçmişini açma işlevleri sunuldu.
  7. **Derleme**: `npm run build` ile 0 hata ile derlendi ve `dist/` klasörüne aktarıldı.

## Mobil SPA Kaçak Kontrol — İhbar Yönetimi Tasarım Diline Tam Uyumlaştırma (2026-10-16)
- **Kullanıcı Geri Bildirimi**: "bu sayfa neden diğer sayfalardan farklı geldi, ihbar yönetimi sayfası gibi olsun" (Ekran görüntüsü paylaşıldı: Ayrı beyaz kutucuklar yerine İhbar sayfasındaki koyu Obsidian Bento Hero kartı, bento arama kartı ve kart yapısı talep edildi).
- **Uygulanan Değişiklikler ve Mimari (`KacakView.vue`)**:
  1. **Obsidian Bento Hero Kartı (`.ihbar-hero-card.bento-card--obsidian`)**:
     - `ihbar-hero-head`: Sol tarafta `GENEL OPERASYON DURUMU`, büyük bold toplam tutanak sayısı (`counts.toplam`); sağ tarafta zümrüt yeşili `%Onay Oranı` rozeti.
     - `segmented-track`: Segmentli renkli progress bar (`seg-pill--amber` Bekleyen, `seg-pill--emerald` Onaylı, `seg-pill--coral` Reddedilen, `seg-pill--slate` İptal).
     - `ihbar-hero-grid`: 4'lü interaktif buton hücresi (`ds-dot--amber` Bekleyen, `ds-dot--emerald` Onaylı, `ds-dot--coral` Reddedilen, `ds-dot--slate` İptal). Dokunulduğunda listeyi doğrudan filtreler ve `is-active` efekti alır.
  2. **Dönem Gezinme ve Filtre Barı (`.ihbar-date-bar`)**:
     - `date-preset-group`: "Son 1 Ay", "30 Gün", "Bu Yıl", "Tümü" hap butonları (`.clay-filter-pill`).
     - `date-range-text`: `Calendar` ikonu ile başlangıç-bitiş tarih metni.
  3. **Arama ve Filtre Kartı (`.ihbar-search-card.bento-card--white`)**:
     - İçeride tam genişlikte arama kutusu (`.search-input`), durum hapları (`.status-pills-scroll`), tür hapları (`Kaçak`, `Abonesiz`, `Usülsüz`, `Pasif Abone`) ve Kahramanmaraş ilçe dropdown'ı (`.ilce-select`).
  4. **Tutanak Bento Kartları (`.ihbar-cards-stack`)**:
     - `ihbar-card` şablonu: Sol üstte `ShieldAlert` ikonu ve ilçe başlığı, sağda durum rozeti (`badge--amber`, `badge--emerald`, `badge--rose`, `badge--slate`), altında abone ve açıklama özeti, etiketler (Tür, Sayaç No, Endeks, Fotoğraf, ZIP İndir bağlantısı) ve altta ekip + tarih bilgisi.
  5. **Detay ve Liderlik Bottom Sheet'leri**:
     - `IhbarView`'deki `.ihbar-drawer-sheet` ve `.ihbar-stats-sheet` CSS ve drag-handle yapısıyla tam uyumlu hale getirildi.
  6. **Derleme**: `npm run build` ile 0 hata ile derlendi.
- **Kullanıcı Talebi**: "personel adlarının altında bu şekilde içinde oldupumuz haftanın günleri gelsin, ay görünümü de ayrıca açılsın" (Yüklenen görsel: Weekly Schedule, Mon-Sun dikey hap kapsülleri, gün numarası ve yuvarlak rozet, sağ üstte ay çipi).
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Hafta Hesaplama Motoru (`PuantajView.vue`)**:
     - `currentWeekDays`: Seçili aya ve bugüne göre içinde bulunulan haftanın (Pazartesi'den Pazar'a 7 gün) `dateStr`, `dayNum`, `dayName` (Pzt...Paz), `isToday`, `isSunday` bilgilerini reaktif olarak üretir.
     - `weekRangeLabel`: "12 - 18 Ekim 2026" gibi dinamik hafta aralığı başlığını sunar.
     - `getPersonelWeekDayEntry`: Her personel için haftanın 7 gününün geçerli hakediş/izin kodunu, renk temasını ve kaydedilmemiş durumunu anlık eşler.
     - `handleWeekCellClick`: Kullanıcı kart üzerindeki herhangi bir gün kapsülüne dokunduğunda doğrudan o personelin o günkü statüsünü döngüsel olarak (`X` -> `HT` -> `Yİ` -> `SGK` -> `X`) günceller ve alttaki yüzen kaydetme barını tetikler.
  2. **Haftalık Puantaj Kapsülleri Bileşeni (`.weekly-schedule-box`)**:
     - Her personel kartında adın hemen altına görseldeki birebir tasarım yerleştirildi.
     - **Header**: Sol tarafta "Haftalık Puantaj" başlığı ve tarih aralığı; sağ tarafta `[Calendar] Ekim 2026` butonu (`.weekly-month-btn`).
     - **7 Sütunlu Dikey Kapsüller (`.weekly-day-capsule`)**:
       - 20px yuvarlatılmış zarif hap formatı.
       - Üstte gün numarası, altta 22x22px yuvarlak renkli durum rozeti (`X`, `HT`, `Yİ`, `SGK` vb.).
       - **Bugün Vurgusu (`is-today`)**: 2px mavi border (`#2563eb`), mavi kalın gün numarası ve hafif elevation.
       - **Pazar Vurgusu (`is-sun`)**: Turuncu/kırmızı pastel tema ve kırmızı `Paz` başlığı.
       - **Kaydedilmemiş Değişiklik (`.weekly-unsaved-glow`)**: Dokunulup değiştirilen günde altın sarısı parlama noktası.
  3. **Ay Görünümü Açılışı**:
     - Hem kartın kendisine hem de haftalık kutunun sağ üstündeki `[Calendar] Ekim 2026` butonuna dokunulduğunda tüm ayı (31 günü) kapsayan Personel Detay & Takvim Bottom Sheet'i açılır.
  4. **Derleme**: `npm run build` ile 0 hata ile derlendi.

## Mobil SPA Nöbet Onay ve Planlama Modülü Geliştirmesi (2026-10-16)
- **Kullanıcı Talebi**: "mobile-spa burada nöbet onay sayfasını yapalım, mobile buradan alacağız ancak aynısını yapmak zorunda değiliz daha iyisini daha kullanıcı dostu yap, ama bütün özelliklerini yapalım"
- **İncelenen Referanslar**:
  - `mobile/pages/nobet.php`: Klasik mobil nöbet sayfası (FullCalendar takvimi, onay bekleyenler listesi, havuz aylık dağılımı, nöbet ekleme bottom sheet'i, tekil onaylama/silme).
  - `views/nobet/onay-islemleri.php`: Web masaüstü nöbet onay ekranı (4 KPI özet kartı, ay/yıl ve nöbet tipi filtreleri, toplu onaylama, toplu silme, onay kaldırma).
  - `views/nobet/api.php` ve `App\Model\NobetModel.php`: Backend nöbet onay metotları (`onaylaNobet`, `onayiKaldirNobet`, `bulkOnaylaNobet`, `bulkSilNobet`, `getOnayBekleyenNobetler`, `getOnayStats`, `getCalendarEvents`, `getAylikNobetDagilimi`, `addNobet`, `deleteNobet`).
- **Uygulanan Mimari ve Özellikler**:
  1. **Backend API (`mobile-spa/public/api/nobet.php` & `dist/api/nobet.php`)**:
     - Strict types, `Gate::allowsAny(['nobet/onay-islemleri', 'yonetici_onayi', 'nobet/list'])` ve `Gate::isSuperAdmin()` yetki denetimi.
     - CSRF token doğrulaması (`verifyCsrfToken`), firma ID ve oturum güvenliği.
     - `list`: Seçili ay/yıl ve filtreye göre nöbet listesi, şifreli ID ve personel token'ları, avatar ve baş harfler, tarih/saat, onay durumu, KPI sayaçları (`total_nobet`, `total_bekleyen`, `total_onaylanan`, `hafta_sonu_bekleyen`, `resmi_tatil_bekleyen`, `hafta_ici_bekleyen`).
     - `calendar`: O ayın tüm günleri için nöbet sayıları, hafta sonu işaretleri ve o günün nöbetçileri listesi.
     - `personnel_summary`: Personellerin aylık nöbet dağılımı, adil dağılım analizi, nöbet ve onay sayıları.
     - `personnel_list`: Yeni nöbet planlama modalı için arama özellikli aktif personel listesi.
     - `approve`: Tekil nöbet onaylama ve denetim logu (`SystemLogModel`).
     - `unapprove`: Onaylanmış nöbetin onayını geri alma ve log kaydı.
     - `delete`: Nöbet kaydını soft delete ile silme ve log kaydı.
     - `bulk_approve`: Çoklu seçilen nöbetleri toplu onaylama.
     - `bulk_unapprove`: Çoklu seçilen nöbetlerin onayını toplu geri alma.
     - `bulk_delete`: Çoklu seçilen nöbetleri toplu silme.
     - `add`: Yeni nöbet planlama (personel, tarih, nöbet tipi, saat aralığı, not, doğrudan onaylı veya bekleyen).
     - `send_reminder`: Personele anlık nöbet hatırlatma push/bildirimi iletme (`PushNotificationService`).
  2. **Servis Katmanı (`mobile-spa/src/services/nobet.js`)**:
     - Reaktif `nobetState` (aktif sekme, ay/yıl, durum ve tip filtreleri, arama, sayaçlar).
     - `nobetApi` istemci metotları (credentials: same-origin, CSRF desteği).
  3. **Görünüm Bileşeni (`mobile-spa/src/views/NobetOnayView.vue`)**:
     - **Soft Bento & Claymorphic UI**: 4 adet etkileşimli minimal Bento KPI kartı (Bekleyenler, Hafta Sonu, Resmi Tatil, Hafta İçi) - tıklanınca listeyi anında filtreler.
     - **Ay/Yıl Seçici**: Önceki/Sonraki ay butonları ve cari dönem rozeti.
     - **3 Segmentli Tab Gezinmesi**:
       - *Onay Listesi*: Arama çubuğu, durum hapları (Bekleyenler, Onaylananlar, Tümü), çoklu seçim modu (Tümünü Seç, Toplu Onayla, Toplu Sil), detaylı nöbet kartları (avatar, ad-soyad, departman, tarih, gün adı, saatler, tip rozeti, onay durumu rozeti, not kutusu, Onayla/Onayı Kaldır, Hatırlatma Gönder, Sil butonları).
       - *Takvim & Planlama*: Mobil 7 sütunlu aylık mini takvim, gün bazında nöbetçi noktaları, güne tıklandığında altta açılan o günün nöbetçi listesi ve "+ Bu Güne Nöbet Ekle" kısayolu.
       - *Havuz & Dağılım*: Personellerin o ayki nöbet dağılımı ve doluluk çubukları, arama filtresi, karta tıklandığında o personele doğrudan nöbet planlama modalı açılması.
     - **Nöbet Planlama Bottom Sheet**: Yukarı kayan mobil modal, arama özellikli personel listesi, tarih seçici, nöbet tipi butonları, hazır saat butonları (18:00 - 08:00, 08:00 - 18:00, 24 saat), açıklama alanı, doğrudan onaylı kaydetme toggle'ı.
     - **Güvenli Eylem ve Onay Modalı**: Silme ve toplu işlemler için onay diyalogu.
     - **Canlı Toast Bildirimleri**: Başarılı/hata durum geri bildirimleri.
  4. **Entegrasyon ve Rotalar**:
     - `mobile-spa/src/router.js`: `/nobet-onay`, `/nobet`, `/nobetler` rotaları eklendi (`permission: 'shifts'`).
     - `mobile-spa/src/components/BottomNav.vue`: "Daha Fazla" menüsündeki Nöbet Onay bağlantısı doğrudan SPA rotasına bağlandı (`to: '/nobet-onay'`).
     - `mobile-spa/src/views/HomeView.vue`: Hızlı erişim butonlarına Nöbetler eklendi.
     - `mobile-spa/public/api/auth.php`: `shifts` izni `nobet/onay-islemleri`, `yonetici_onayi`, `nobet/list` anahtarlarıyla güçlendirildi.
     - `npm run build`: 0 hata ile başarıyla derlendi.
- **Kullanıcı Talebi**: "mobile-spa burada kaçak kontrol sayfasını yapalım, mobile buradan alacağız ancak aynısını yapmak zorunda değiliz daha iyisini daha kullanıcı dostu yap, ama bütün özelliklerini yapalım"
- **İncelenen Referanslar**:
  - `mobile/pages/kacak.php`: Klasik mobil Kaçak Kontrol sayfası (özet sayaçlar, dönem filtresi, arama, durum sekmeleri, tutanak kartları, detay bottom sheet'i, fotoğraf galerisi, tam ekran lightbox, ekip leaderboard özeti, onay/red/iptal/iptali geri al butonları).
  - `mobile/api/kacak-fotograflar.php`: Fotoğraf API'si.
  - `views/kacak/api.php` ve `App/Model/KacakKontrolModel.php`: Backend kaçak kontrol modeli, fotoğraf listeleme/görüntüleme ve zip indirme servisleri.
- **Uygulanan Mimari ve Özellikler**:
  1. **Backend API (`mobile-spa/public/api/kacak.php` & `dist/api/kacak.php`)**:
     - Strict types, `Gate::allowsAny(['kacak_islemleri', 'kacak/list'])`, `kacak_onay`, `kacak_iptal_ekle`, `kacak_iptal` kontrolleri.
     - CSRF doğrulaması (`verifyCsrfToken`), firma ID ve oturum güvenliği.
     - `list`: Genişletilmiş tarih/dönem filtreli kayıtlar, özet sayaçlar (toplam, beklemede, onaylandi, reddedildi, iptal), ekip başarı istatistiği (leaderboard sıralaması ve yüzdeleri), yetkiler ve şifreli tokenlar.
     - `detail`: Tekil tutanak detayları, saha ve tutanak fotoğrafları/videoları/PDF belgeleri, küçük resim thumbnail ve zip indirme URL'leri.
     - `photos`: Tutanak fotoğrafları listesi.
     - `approve`: Tutanağı onaylama, `PushNotificationService` ile personele bildirim ve `SystemLogModel` kaydı.
     - `reject`: Tutanağı reddetme (red gerekçesi ile), push bildirim ve denetim logu.
     - `cancel`: Tutanağı iptal etme (iptal açıklaması ve hakedişten düşme seçeneği) ve log kaydı.
     - `revert_cancel`: İptal edilmiş tutanağı geri alma ve tekrar aktife çekme.
  2. **Servis Katmanı (`mobile-spa/src/services/kacak.js`)**:
     - Reaktif `kacakState` (aktif sekme, arama, tarih aralığı, sayaçlar, liderlik tablosu, yetkiler).
     - `kacakApi` (list, detail, photos, approve, reject, cancel, revertCancel metotları).
  3. **Görünüm Bileşeni (`mobile-spa/src/views/KacakView.vue`)**:
     - **Soft Bento & Claymorphic UI**: Bento KPI ızgarası (4 adet etkileşimli kart: Toplam, Bekleyen, Onaylanan, İptal). Tıklandığında doğrudan ilgili durum filtresini aktif eder.
     - **Dönem ve Hızlı Tarih Barı**: "Son 30 Gün", "Bu Yıl", "Son 1 Yıl", "Tümü" ve açılır özel tarih seçici paneli.
     - **Canlı Arama & Çift Katmanlı Filtre**: Tutanak no, abone, sayaç, ekip ve ilçe üzerinde anında canlı arama; Clay sekmeleri (Tümü, Bekleyen, Onaylı, Reddedilen, İptal) ve Tür hapları (Kaçak, Abonesiz, Usülsüz, Pasif Abone).
     - **Tutanak Kartları**: Tür rozetleri (`Kaçak`, `Abonesiz` vb.), Tarih, Durum rozetleri, Tutanak No ve Abone, İlçe/Ekip bilgileri, Sayaç No, Fotoğraf Sayısı ve tek dokunuşla ZIP İndirme kısayolu.
     - **Ekip Başarı Sıralaması (Leaderboard) Drawer**: Kupa ikonuyla açılan modern bottom sheet; 1., 2., 3. ekiplere özel madalya rozetleri, renkli ilerleme çubukları ve tutanak sayıları.
     - **Detay Bottom Sheet**: 2 sütunlu Bento bilgi ızgarası, Varsa Red Nedeni / İptal Açıklaması kutuları, "Fotoğrafları İncele" ve "ZIP İndir" butonları, Yetki bazlı "Onayla", "Reddet", "İptal Et", "İptali Geri Al" aksiyonları.
     - **Fotoğraf Galerisi & Lightbox**: Tutanak ve Saha fotoğrafları, PDF dokümanları desteği, Tam ekran dokunmatik / butonlu Lightbox ve fotoğraf indirme özelliği.
     - **Aksiyon Onay Modalı**: Red gerekçesi ve İptal açıklaması + Hakedişten düş kutucuğu içeren güvenli eylem modalı.
  4. **Entegrasyon ve Rotalar**:
     - `mobile-spa/src/router.js`: `/kacak-kontrol` ve `/kacak` rotaları tanımlandı (`permission: 'illegal'`).
     - `mobile-spa/src/components/BottomNav.vue`: "Daha Fazla" menüsündeki Kaçak Kontrol bağlantısı doğrudan SPA rotasına bağlandı (`to: '/kacak-kontrol'`).
     - `mobile-spa/src/views/HomeView.vue`: Hızlı erişim butonlarına Kaçak Kontrol eklendi.
     - `mobile-spa/public/api/auth.php`: `illegal_approve`, `illegal_cancel`, `illegal_revert_cancel` izinleri oturum haritasına dahil edildi.
     - `npm run build`: 0 hata ile başarıyla derlendi.
- **Kullanıcı Talebi**: "mobile-spa burada ihbar yönetimi sayfasını yapalım, mobile buradan alacağız ancak aynısını yapmak zorunda değiliz daha iyisini daha kullanıcı dostu yap, ama bütün özelliklerini yapalım"
- **Yapılan İncelemeler**:
  - `mobile/pages/ihbar.php`: Eski PHP sayfası incelendi (dönem filtresi, özet sayaçlar, arama, durum filtreleri, ihbar detay modalı, fotoğraf galerisi, lightbox, ekibe yönlendirme, işlem notu, sonuçlandırma, personel performans bottom sheet'i, excel export).
  - `views/ihbar/api.php` ve `App/Model/IhbarModel.php`: Backend modelleri ve yetkilendirmeleri (`ihbar/list`, `ihbar_duzenle`) incelendi.
- **Uygulanan Mimari ve Özellikler**:
  1. **Backend API (`mobile-spa/public/api/ihbar.php` & `dist/api/ihbar.php`)**:
     - Strict types, `Gate::allows('ihbar/list')`, `Gate::allows('ihbar_duzenle')` yetkilendirmesi, CSRF doğrulaması (`requireCsrfToken`), firma ID ve kullanıcı güvenliği.
     - `list`: Dönem filtreli ihbarlar, sayaçlar, foto sayıları, ilçe listesi, aday personeller.
     - `detail`: İhbarın tam detayları, şifreli token'lar, fotoğraflar/videolar, işlem tarihçesi zaman çizelgesi, atanan personeller.
     - `foto`: `MedyaServis::gonder` ile güvenli fotoğraf akışı/önizlemesi.
     - `stats`: Personel performans istatistikleri, en çok gönderen, en yüksek olumlu oranı, toplam ihbarlar.
     - `assign`: Kaçak ekibine personel yönlendirme (en fazla 2 personel) ve push bildirimi.
     - `addNote`: İşlem notu ekleme ve tarihçeye kaydetme.
     - `close`: İhbarı sonuçlandırma (Olumlu / Olumsuz), Tutanak No ve Olumsuzluk Gerekçesi girişi.
     - `cancelResult`: Sonuçlandırılan ihbarı geri alma ve tekrar işleme açma.
     - `create`: Yeni ihbar kaydı oluşturma (GPS koordinatı, harita linki, ilçe, mahalle, telefon, abone no, çoklu fotoğraf yükleme) ve sorumlulara bildirim.
  2. **Servis Katmanı (`mobile-spa/src/services/ihbar.js`)**:
     - Reaktif `ihbarState` (aktif filtre, arama, ilçe, sayaçlar, drawer durumları).
     - `ihbarApi` modülü (tüm istekler credentials: same-origin ve CSRF token ile).
  3. **Görünüm Bileşeni (`mobile-spa/src/views/IhbarView.vue`)**:
     - **Soft Bento & Claymorphic UI**: Obsidian Bento Hero kartı (toplam ihbar, % başarı oranı, segmentli progress bar, 4'lü interaktif durum hücresi).
     - **Dönem ve Filtreleme**: Hızlı tarih hapları (Son 1 Ay, 30 Gün, Tümü), canlı arama, durum hapları ve Kahramanmaraş ilçe seçici dropdown'u.
     - **İhbar Kartları**: Durum rozetleri, İlçe/Mahalle başlığı, 2 satırlık açıklama önizlemesi, Fotoğraf sayısı/GPS/Tutanak etiketleri, Bildiren personel, Atanan ekip ve tarih.
     - **İhbar Detay Drawer**: Durum, bildiren, telefon (tıkla-ara `tel:`), komşu abone, atanan ekip, açık adres, Google Haritalar'da aç butonu, küçük resimli galeri, tam ekran lightbox (önceki/sonraki kontrolleri), işlem adımları zaman çizelgesi.
     - **Yetkili Aksiyonlar**: Ekibe Yönlendir modalı (arama destekli personel seçimi), İşlem Notu Ekle modalı, İhbarı Sonuçlandır modalı (Olumlu/Olumsuz toggle), Sonucu Geri Al butonu.
     - **Personel Performansı (Liderlik) Sheet**: 4'lü üst KPI (Bildirim yapan personel, En çok gönderen, En yüksek başarı, Saha toplamı), personel bazlı başarı oranı çubukları, "İhbarları Gör" ile tek dokunuşla filtreleme, Excel'e (XLS) indirme.
     - **Yeni İhbar Ekleme Sheet**: Cihaz GPS konumunu tek tıkla alma (`navigator.geolocation`), fotoğraf çekme/galeriden ekleme (önizleme ve silme destekli), ilçe/mahalle/açıklama/telefon/abone alanları.
  4. **Entegrasyon ve Rotalar**:
     - `mobile-spa/src/router.js`: `/ihbar`, `/ihbar-yonetimi`, `/ihbarlar` rotaları eklendi (`permission: 'notices'`).
     - `mobile-spa/src/components/BottomNav.vue`: "Daha Fazla" menüsündeki İhbar Yönetimi linki doğrudan SPA rotasına bağlandı (`to: '/ihbar'`).
     - `mobile-spa/src/views/HomeView.vue`: Hızlı erişim aksiyonlarına `notices` yetkisi varsa İhbarlar butonu eklendi.
     - `npm run build`: Vite derlemesi 0 hata ile tamamlandı.

## Mobil SPA KM Onayları — Puantaj Tarzı Minimal Liste Kartları Tasarımı (2026-10-16)
- **Kullanıcı Talebi**: "liste elemanları puantaj sayfasındaki personel listesi gibi olsun , ama daha minimal"
- **İncelenen Referanslar**:
  - 1. Görsel: "Bildirim Yapmayanlar" listesindeki eski kaba ve yüksek padding'li kartlar.
  - 2. Görsel: Puantaj sayfasındaki zarif, beyaz, hafif gölgeli, üstte tarih çipi ve rozet, ortada 38px dairesel avatar + bold isim + TC/ikincil bilgi, altta ince ayırıcı çizgi ile renkli dot göstergeleri (`🟣 / 🟢 / 🟠`) içeren proje kartı tasarımı.
- **Uygulanan Değişiklikler ve Mimari (`mobile-spa/src/views/KmOnaylariView.vue`)**:
  1. **Yapmayanlar Listesi Kartları (`unreported`)**:
     - `.project-card.km-unreported-card` yapısına geçirildi.
     - **Header**: Sol tarafta zarif tarih/durum çipi (`.project-date-pill`: `Clock` ikonu + `Bugün Bildirim Yok`), sağ tarafta kompakt TR plaka rozeti (`.tr-plate-badge.tr-plate-badge--sm`).
     - **Body**: Sol tarafta 36px dairesel avatar (`.project-avatar.project-avatar--sm`), baş harfler (`getInitials`) veya personel fotoğrafı; yanında bold isim (`.project-title`) ve altında telefon bilgisi (`.project-tc`).
     - **Footer**: Renkli dot göstergesi (`.dot-indicator.dot-purple` veya `dot-orange`) ile eksik bildirim sayısı (`Son 30 günde X akşam eksik`), sağ tarafta minimal hap formunda hatırlatma butonu (`.km-pill-action-btn`: `<Bell /> Hatırlat`).
  2. **KM Bildirim Kartları (`pending`, `approved`, `rejected`)**:
     - Eski 175px'lik kaba hero fotoğraf ve devasa butonlar yerine son derece minimal, kompakt Puantaj kartı hiyerarşisi uygulandı.
     - **Header**: Sol tarafta çoklu seçim checkbox'ı ve tarih/saat çipi (`formatDate` + `formatTime`); sağ tarafta Sabah/Akşam çipi (`.tur-pill-badge`) ve kompakt TR plaka rozeti.
     - **Body**: Sol tarafta personel avatarı, bold ad soyad ve araç modeli; sağ tarafta bold KM sayacı (`.km-number-bold`), varsa yapılan yol farkı rozeti (`+X KM`) ve dokunulduğunda tam ekran Lightbox açan 44x44px yuvarlatılmış mini thumbnail (`.km-thumb-preview`).
     - **Personel Notu & AI Uyarısı**: İnce kenarlı zarif konuşma balonu ve sarı AI uyarısı olarak gövde altına yerleştirildi.
     - **Footer**:
       - Bekleyenler için: Minimal ve derli toplu 30px yükseklikte buton grubu (`.btn-km-subtle--danger` Reddet, `.btn-km-subtle--warning` Düzelt, `.btn-km-subtle--primary` Onayla, `.btn-km-subtle--ai` AI Doğrula).
       - Onaylananlar için: Yeşil dot (`dot-green`) ile onaylayan ve onaylanan KM bilgisi.
       - Reddedilenler için: Kırmızı dot (`dot-red`) ile red gerekçesi.
  3. **Vite Build**:
     - `npm run build` ile 0 hata ile derlendi (`dist/assets/index-8j0yxj6k.css` ve `dist/assets/index-D7_TXp4q.js`).
- **Kullanıcı Talebi**:
  1. "tüm personel, izinli , raporlu butonlarını kaldır, zaten üsttek ikartlar yapıyor, ayrıca arama kutusu ve filtre butonu aynı sırada gelsin": Arama kutusunun üstündeki gereksiz sekme butonları kaldırıldı; üstteki 4 adet Bento KPI kartının filtreleme işlevi korundu ve arama kutusu ile filtre butonu tek satırda yan yana getirildi.
  2. "burada da düzenleme(iyileştirme ) yap": Personel Detay & Takvim Bottom Sheet'inde hızlı damga modundaki yatay çirkin scrollbar kaldırıldı (`hide-scrollbar`), takvim gün hücreleri kaba kutu görünümünden çıkarılarak Görsel 2 sağ ekrandaki gibi zarif pastel hücrelere (`theme.bg`, `theme.border`, `theme.badgeBg`) dönüştürüldü.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Arama ve Filtre Sırası (`mobile-spa/src/views/PuantajView.vue`)**:
     - `.projects-section-head` ve altındaki sekmeler (`Tüm Personel`, `İzinliler`, `Raporlular`) tamamen kaldırıldı.
     - `.puantaj-search-filter-row` ile arama kutusu (`flex: 1`) ve filtreleme ikonu butonu (`.puantaj-filter-btn`, `44x44px`, yuvarlak clay buton, aktif filtre mavi dot rozeti) aynı hizada tek satıra yerleştirildi.
     - Üstteki 2x2 Bento KPI kartları üzerinden "Toplam Personel" (`all`), "İzinli" (`izinli`) ve "Raporlu" (`raporlu`) filtrelemeleri tıklanabilir ve seçili durum çerçevesiyle kesintisiz çalışmaya devam ediyor.
  2. **Personel Ay Detay & Takvim Bottom Sheet İyileştirmeleri (`PuantajView.vue`)**:
     - **Scrollbar Gizleme**: Hem `.stamp-types-scroll` hem de `.ds-sheet-body` için `scrollbar-width: none` ve `::-webkit-scrollbar { display: none }` (`.hide-scrollbar`) tanımlandı.
     - **Zarif Pastel Takvim Hücreleri (`.calendar-tile`)**: Eski sert gri kutu yerine her hücre için `cell.theme` (pastel zemin, ince border, gün numarası ve kontrast rozet) uygulandı. Pazar günleri ve kaydedilmemiş değişiklikler (`.tile-unsaved-dot`) altın sarısı parlama ile belirginleştirildi.
     - **Header & Özet**: Personel adının yanına o ayki çalışma, izin ve rapor durumunu gösteren kompakt özet hapı (`detail-stat-pill`) eklendi.
     - **Hızlı Damga Çipleri**: Kompakt dokunmatik clay pill tasarımına kavuşturuldu; aktif damga banner'ı modern obsidian bar formatında güncellendi.
  3. **Derleme ve Senkronizasyon**:
     - `npm run build` ile Vite derlemesi hatasız (0 error) tamamlandı.
     - `mobile-spa/public/api/puantaj.php` ile `mobile-spa/dist/api/puantaj.php` senkronize tutuldu.

## Mobil SPA 'Daha Fazla' Menü Sadeleştirmesi ve Kart Gölge Dağılımı Optimizasyonu (2026-10-16)
- **Kullanıcı Talebi**: Mobil SPA diğer ("Daha Fazla") menüsünde ana sayfa, profil menülerini ve masaüstü modu butonunu kaldır; kartların arkasındaki gölge dağılımını biraz daha azalt.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **BottomNav.vue Menü Sadeleştirmesi**:
     - `operations` dizisinden `Ana Sayfa` ve `Profil & Ayarlar` menü kartları kaldırıldı.
     - Üstteki `nav-sheet-profile` kartı kaldırıldı; menü açıldığında doğrudan modüllere odaklanıldı.
     - Alt çubuktan (`nav-sheet-footer`) `Masaüstü Modu` butonu kaldırıldı.
     - Kullanılmayan `ChevronRight`, `Monitor`, `UserRound` ikon importları temizlendi.
  2. **Footer Yerleşimi**:
     - `styles.css` ve `design-system.css` içindeki `.nav-sheet-footer` 2 sütundan tek sütuna (`grid-template-columns: 1fr;`) dönüştürüldü; `Güvenli Çıkış` butonu tam genişlikte ergonomik hale getirildi.
  3. **Merkezi Tasarım Sistemi Gölge Optimizasyonu (`design-system.css`)**:
     - Kartların arkasındaki gölge dağılımı (blur ve yayılma) azaltıldı:
       - `--ds-shadow-tile`: `0 6px 16px rgba(15, 23, 42, 0.05)` -> `0 2px 6px rgba(15, 23, 42, 0.035), 0 1px 2px rgba(15, 23, 42, 0.02)`
       - `--ds-shadow-bento`: `0 8px 24px -10px ...` -> `0 4px 14px -6px ...`
       - Diğer yardımcı elevation gölgeleri (`--ds-shadow-bento-dark`, `--ds-shadow-pill`, `--ds-shadow-btn`) daha derli toplu ve yumuşak hale getirildi.
  4. **Smoke Test & Vite Build**:
     - `tests/design-smoke.cjs` güncellendi.
     - `npm run build` ile üretim paketleri hatasız olarak derlendi.

## Mobil SPA Puantaj Modülü — Görsel 2 Tasarımı, Pastel Bento KPI ve Damga Düzeltmesi (2026-10-16)
- **Kullanıcı Talebi**:
  1. "diğer puantaj türleri gelmemiş, ayrıca seçtiğim türü atamadı": Veritabanındaki diğer izin türlerinin eksik gelmesi ve seçilen türün (HT vb.) takvim gününe dokunulduğunda hücreye atanmaması sorununun çözülmesi.
  2. "puantaj tasarımı 2.resimdekine benzer olsun, personel listesi de 2.resimdeki gibi olsun": Sayfa genelinin ve personel listesinin kullanıcının sağladığı 2. görseldeki (2x2 pastel bento kartları, zarif takvim çipli ve renkli durum noktalı proje/personel kartları) tasarıma dönüştürülmesi.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Backend API Tür Haritalama ve Firma İzolasyonu Düzeltmesi (`mobile-spa/public/api/puantaj.php` & `dist/api/puantaj.php`)**:
     - `definitions` sorgusu, firma ID'si farklı olsa bile (`firma_id = 2` vb.) sistemdeki tüm aktif izin türlerini (`Yİ`, `Bİ`, `Mİ`, `RP`, `Eİ`, `D`, `X`, `HT`, `Üİ`, `UZ`, `RTÇ`, `HTÇ`, `ÜZ`, `SGK`) getirecek ve firma önceliğiyle tekilleştirecek şekilde güncellendi.
     - `save-bulk` servisi, hem `type_id` hem de metinsel `code` / `status_code` ile çalışacak şekilde güçlendirildi; `X` seçildiğinde çakışan izin silinip normal çalışılan güne dönmesi, `SGK`/`HT`/`Yİ` seçildiğinde otomatik ID eşlemesi sağlandı.
  2. **Frontend Damga & Takvim Mantığı Düzeltmesi (`mobile-spa/src/views/PuantajView.vue`)**:
     - `applyStatusToCell` fonksiyonu artık hem `type_id` hem de doğrudan `code` saklıyor; `HT` gibi hafta içi günlere damgalanan statülerin `id: 0` nedeniyle `X`'e geri dönmesi engellendi. Dokunulan hücre anında seçilen türün rengini ve kodunu alıyor.
     - `availablePaletteList` hem ücretli (`X`, `HT`, `Yİ`, `Bİ`, `Eİ`, `Üİ`, `RTÇ`, `HTÇ`) hem ücretsiz (`SGK`, `RP`, `Mİ`, `D`, `UZ`, `ÜZ`) sekmelerinde eksiksiz olarak listeleniyor.
  3. **Görsel 2 Tasarım Standardı Uygulaması (`PuantajView.vue`)**:
     - **Header**: Sol tarafta yuvarlak avatar, "Personel Puantaj", Ersan Elektrik; sağ tarafta yuvarlak beyaz clay butonlar içinde SGK rozetli zili ve yenileme butonu.
     - **Overview (2x2 Pastel Bento KPI Grid)**:
       - 1. Lila/Mor Kart (`#f3effe`): Toplam Personel sayısı, mor dalga sparkline grafiği SVG, `Toplam Personel`, `>`.
       - 2. Şeftali/Turuncu Kart (`#fef4ec`): İzinli sayısı, turuncu dalga sparkline, `İzinli (X Gün)`, tıklanabilir filtre.
       - 3. Açık Mavi Kart (`#ebf7fd`): Raporlu sayısı, mavi dalga sparkline, `Raporlu (X Gün)`, tıklanabilir filtre.
       - 4. Açık Nane Yeşili Kart (`#ebf8f2`): Toplam çalışılan gün sayısı, yeşil dalga sparkline, `Toplam Çalışılan Gün`.
     - **Personel Listesi Kartları (Görsel 2'deki Beyaz Proje Kartı Şablonu)**:
       - Sol üstte zarif gri takvim çipi: `<Calendar /> 1 - 31 Ekim 2026`.
       - Sağ üstte departman etiketi ve 3 nokta aksiyon butonu.
       - Ortada personel adı soyadı (büyük, kalın) ve TC kimlik no.
       - Alt satırda renkli durum noktaları: Mor nokta `• Çalışılan: 22 Gün`, Yeşil nokta `• Devam: %71`, Turuncu nokta `• İzin: X G`, Kırmızı nokta `• Rapor: Y G`.
     - **Aylık Takvim Bottom Sheet (Görsel 2 Sağ Ekran Minimalizmi)**:
       - 7 sütunlu ferah ızgara, gün numarası ve statü hapı, seçilen türün anında parlaması ve yüzen kaydetme barı.
  4. **Derleme**: `npm run build` ile 0 hata ile derlendi, `dist/` dosyaları güncellendi.
     - **SGK Vizite Raporları Bottom Sheet**: Seçili aya ait onaylı vizite raporları, personel eşleşmesi, tarih aralığı, vaka türü ve "Puantaja İşle (SGK)" butonu.
     - **Kaydedilmemiş Değişiklik Güvenliği**: Sayfadan veya aydan ayrılmaya çalışıldığında kullanıcıyı uyaran ve onay isteyen dialog.
  4. **SPA Rota ve Menü Entegrasyonu**:
     - `router.js`: `/puantaj` ve `/puantaj-takip` rotaları eklendi.
     - `BottomNav.vue`: "Daha Fazla" menüsündeki legacy link Vue rotasına (`to: '/puantaj'`) bağlandı.
     - `HomeView.vue`: Hızlı işlemler listesindeki Puantaj linki Vue rotasına (`to: '/puantaj'`) bağlandı.
  5. **Üretim Derlemesi (`npm run build`)**: Vite derlemesi başarıyla tamamlandı, `dist/api/puantaj.php` senkronize edildi.

## Mobil SPA KM Onayları Modülü ve Gelişmiş Bento / Claymorphic Sayfası (2026-10-16)
- **Kullanıcı Talebi**: Mobil SPA (`@mobile-spa`) üzerinde KM onayları sayfasının yapılması, eski `mobile/pages/km-onaylari.php` baz alınarak tüm özellikleriyle birlikte çok daha kullanıcı dostu, modern ve zengin olarak yeniden inşa edilmesi.
- **Uygulanan Değişiklikler ve Mimari**:
  1. **Güvenli Backend API (`mobile-spa/public/api/km-onaylari.php`)**:
     - `Gate::allowsAny(['arac_takip_yonetim', 'onaylikm_duzenle', 'arac-takip/km-onaylari'])` ve `Gate::isSuperAdmin()` ile çift katmanlı yetkilendirme.
     - POST isteklerinde `verifyCsrfToken()` ve hem `$_POST` hem `application/json` (php://input) otomatik çözümleme desteği.
     - `list`: Durum filtreleri ('pending', 'unreported', 'approved', 'rejected'), tür filtreleri ('sabah', 'aksam'), arama ve sayfalama ile birlikte canlı sayaçlar (`getReportCounts()`).
     - `approve`: Bildirimi onaylama, `arac_km_kayitlari`na resmi kayıt oluşturma/güncelleme, `araclar` tablosundaki güncel km'yi güncelleme ve `SystemLogModel` kaydı.
     - `edit_approve`: Yönetici tarafından girilen yeni KM değeriyle bildirimi düzeltip onaylama, aracı ve logları güncelleme.
     - `reject`: Neden belirterek bildirimi reddetme.
     - `bulk_approve`: Çoklu seçilen bekleyen bildirimleri topluca onaylama.
     - `ai_check`: `KmBildirimAiKontrolService::kontrolEtVeOnayla` entegrasyonu ile OpenAI Vision destekli gösterge OCR kontrolü ve otomatik onay.
     - `send_reminder`: Bildirim yapmayan personele sistem ve Push (`PushNotificationService`) bildirimi gönderme.
     - `bulk_reminder`: Bildirim yapmayan tüm personellere tek dokunuşla toplu hatırlatma gönderme (`sendBatchKmHatirlatma`).
  2. **Frontend Servisi (`mobile-spa/src/services/km-onaylari.js`)**:
     - `kmState` reaktif durumu ve `kmApi` istek metotları eksiksiz tanımlandı.
  3. **Mobil Görünüm Bileşeni (`mobile-spa/src/views/KmOnaylariView.vue`) & Alt Dock Navbar Çekmecesi (`BottomNav.vue`)**:
     - **Tasarım Standardı**: `design-system.css`, Roboto Condensed tipografi, Bento & Claymorphic UI standartlarına tam uyum.
     - **Navbar Durum Çekmecesi (Talepler Stili)**:
       - Ekran görüntüsündeki statik sayfa içi butonlar kaldırılarak ekranın altındaki yüzen dock navbar'a (`BottomNav.vue`) taşındı.
       - 5 Sekmeli Dock: 1. Bekleyen (`Clock` + sayaç), 2. Yapmayanlar (`AlertCircle` + sayaç), 3. Onaylanan (`CheckCircle2` + sayaç), 4. Reddedilen (`XCircle` + sayaç), 5. Kapat (`X` ikonu).
       - Kapat butonuna basıldığında drawer kapanarak standart navigasyon sekmelerine dönülür.
       - Menü kapalıyken sayfa üzerinde sağ altta yüzen `floating-km-dock-btn` ("Menü") butonu ile alt çekmece tek dokunuşla tekrar açılır.
     - **Minimal Bento Özet Strip & Day/Night Toggle Switch (2. Görsel Standartı)**:
       - Eski yüksek ve 4 kutulu hantal kart yerine; tek satırda aktif sekme başlığı, kayıt sayacı ve 4 renkli mini segment oran çizgisini barındıran minimal Bento şeridi tasarlandı.
       - Arama alanının altındaki statik chip butonlar kaldırılarak özet kartın içine taşındı.
       - 2. görseldeki tasarımla birebir uyumlu **Gündüz / Gece (Sabah ☀️ ⟷ Akşam 🌙) Animasyonlu Day/Night Toggle Switch** entegre edildi. Sabah modunda açık zemin, siyah güneş ikonu ve sol knob; Akşam modunda koyu lacivert zemin, beyaz hilal/yıldızlar ve sağ knob animasyonuyla pürüzsüz geçiş sağlandı. Yanına "Tümü" seçeneği eklendi.
     - **Çoklu Seçim ve Eylem Barı**: Tümünü seç, seçili adet rozeti, AI ile Doğrula ve Toplu Onayla butonları.
     - **Bildirim Kartları**: TR plaka rozeti, araç marka/modeli, sabah/akşam pill rozeti, hero gösterge fotoğrafı (üzerinde saat, tarih, net KM değeri ve sabah KM farkı), personel avatarı ve açıklaması, AI notu, onaylayan/reddeden bilgisi ve ergonomik aksiyon butonları.
     - **Yapmayanlar Kartları**: Personel avatarı, plaka, telefon, son 30 gün akşam eksik rozeti, personele anında hatırlat butonu ve toplu hatırlatma gönderme.
     - **Modallar**:
       - *Görsel Lightbox (Büyüteç)*: Çift tık zoom destekli tam ekran görsel önizleme.
       - *Düzelt ve Onayla*: Bottom sheet tarzı, büyük KM inputu, sabah KM referansı, +10/+50/+100 hızlı ayar çipleri ve not alanı.
       - *Reddet*: Hızlı hazır red şablonları ("Okunaksız fotoğraf", "KM uyuşmazlığı", "Mükerrer bildirim") ve özel açıklama.
       - *Yapay Zeka Analiz Raporu*: Otomatik onaylanan ve manuel kontrolde kalan kayıtların detaylı dökümü.
  4. **SPA Yönlendirme ve Entegrasyon**:
     - `router.js`: `/km-onaylari` rotası eklendi, `beforeEach` yetkilendirmesi güncellendi.
     - `BottomNav.vue`: "Daha Fazla" menüsündeki KM Onayları harici linkten doğrudan SPA rotasına (`to: '/km-onaylari'`) dönüştürüldü.
     - `AracView.vue`: KM sekmesinin en üstüne şık bir "KM Bildirim Onayları" hızlı erişim banner'ı eklendi.
     - `auth.php`: `km_approvals` yetkisi tanımlandı.
  5. **Üretim Derlemesi (`npm run build`)**: Vite derlemesi başarıyla tamamlandı.

## Mobil SPA Tipografi Standardizasyonu (2026-10-16)
- **Kullanıcı Talebi**: Mobil SPA (`@mobile-spa`) sayfalarında genel olarak kullanılan fontun `personel-pwa`'da kullanılan font (`Roboto Condensed`) yapılması.
- **Uygulanan Değişiklikler**:
  1. **HTML & Font Yükleme**: `mobile-spa/index.html` içine Google Fonts `Roboto Condensed` (`ital,wght@0,100..900;1,100..900`) preconnect ve link etiketleri eklendi.
  2. **Global Stiller (`mobile-spa/src/styles.css`)**:
     - `@import` kuralına `Roboto Condensed` eklendi.
     - `:root` ve `body` font ailesi `'Roboto Condensed', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif` olarak ayarlandı.
     - Başlıklar (`h1, h2, h3, strong`) `var(--ds-font-main)` değişkenine bağlandı.
  3. **Merkezi Tasarım Sistemi (`mobile-spa/src/design-system.css`)**:
     - `--ds-font-main` değişkeni `'Roboto Condensed', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif` olarak güncellendi. Tüm bento kartları, butonlar, inputlar, formlar ve etiketler tek merkezden `Roboto Condensed` tipografisine kavuştu.
  4. **Bileşenlerdeki Lokal Geçersiz Kılmalar**: `CariView.vue`, `CariDetailView.vue`, `PersonnelSectionView.vue`, `KasaView.vue` ve `FaturaView.vue` içindeki münferit font-family tanımları merkezi `var(--ds-font-main)` standardına bağlandı.
  5. **Üretim Derlemesi (`npm run build`)**: Vite derlemesi başarıyla tamamlandı, `dist/` klasörü güncellendi.

## Resmî Ücret Pusulası → Dijital Yayın Entegrasyonu / Adım 1-2 (2026-10-16)
- `views/bordro/raporlar/bordro.php` tarafından kullanılan `BordroPusulaService`, personel dijital yayın akışının da tek resmî pusula üreticisi yapıldı.
- Yayın içeriğine sürümlü resmî pusula özeti, kazanç/kesinti toplamları, yasal kesinti kalemleri, matrahlar, çalışma/izin günleri ve vergi istisnaları eklendi.
- Tam `hesaplama_detay` ile elden/Sodexo/diğer ödeme kanallarının personel API'sine sızmaması korundu.
- Personel PWA detayında kazanç toplamı, toplam kesinti ve yasal kesinti kalemleri gösterilmeye başlandı.
- Personelin yeni yayınlardan indirdiği PDF, yönetim raporundaki aynı A4 ücret hesap pusulası şablonundan üretiliyor; dijital beyan, sürüm, yayın zamanı ve SHA-256 bilgisi belgeye ekleniyor.
- PDF canlı kayıtları yeniden okumuyor; yayın anındaki maskeli T.C., SGK, işe giriş/çıkış, firma, dönem ve resmî pusula sunum snapshot'ını kullanıyor. Eski yayınlar legacy PDF ile açılmaya devam ediyor.
- Personel durumları `Bekliyor / Görüntülendi – Onay Bekliyor / Onaylandı / İtiraz Edildi / İptal-Revize Edildi` olarak tek iş durumuna bağlandı; yönetim tablosu ve CSV aynı alanı kullanıyor.
- Beyan metni v2 “Ücret hesap pusulasını okudum ve onaylıyorum” oldu. İçerik hash'i, IP, user-agent ve oturum hash'i onay kanıtına eklendi; tekrar görüntülemeler olay günlüğünde korunuyor.
- Dönem tabanlı benzersiz pusula numarası ile oluşturma/gönderim zamanları eklendi; eski kayıtlar migration içinde geriye dolduruluyor.
- İtiraz kategorileri, süreç durumları, gerekçeli sonuç, AES şifreli 10 MB PDF/görsel eki ve sahiplik/yetki kontrollü indirme tamamlandı.
- Yeni itiraz için bordro yetkililerine uygulama içi bildirim, sonuç için personele push ve yeni sürüm için revizyona özel bildirim metni eklendi.
- `database/migrations/2026_10_16_expand_bordro_yayin_onay.sql` yerel hedef veritabanına uygulandı; yeni alanlar, itiraz eki tablosu ve aktif kayıtların pusula numarası doluluğu doğrulandı.
- Personel PWA bordro modalında indirilen resmî pusulanın personel/işyeri bilgileri, çalışma ve izin günleri, yasal matrah-kesintiler, ek kazançlar, resmî düzeltmeler, vergi istisnaları, özet ve SHA-256 belge bilgisi ayrıca indirme gerekmeden görüntüleniyor. Veriler yayın snapshot'ından okunuyor.
- Aynı modal `Pusula Detayı` ve `İtiraz / İnceleme` sekmelerine ayrıldı. Açık itiraz sayısı sekme rozetinde gösteriliyor; itiraz sonrası kullanıcı ilgili sekmede kalıyor.
- Pusula detayının görsel önceliği parasal kalemlere taşındı: birleşik yemek yardımı dahil gelirler açık yeşil, yasal/özel kesintiler açık kırmızı kartta en üstte; özet, gün, matrah ve personel bilgileri bunların altında. İşyeri bilgi kartı kaldırıldı. Yemek günlük yuvarlama farkı yalnız sunumda yemek yardımına ekleniyor ve ayrı satır çizilmiyor; snapshot hesabı değişmiyor.
- Personel talebiyle pusula ayrıntısından personel bilgileri ve teknik belge doğrulama/SHA-256 kartları kaldırıldı. Onay bekleyen elektronik ücret pusulası bildirim kartına mavi border/shadow pulse eklendi; onay sonrası efekt uygulanmıyor ve `prefers-reduced-motion` tercihinde animasyon kapatılıyor.

## Mobil SPA Evrak Takip Modülü ve Gelişmiş Bento / Claymorphic Sayfası (2026-10-15)
- **Kullanıcı Talebi**: Mobil SPA üzerinde "Evrak Takip" sayfasının yapılması (`@mobile-spa`), eski `mobile/` modülünden ilham alınarak daha kullanıcı dostu, modern ve şık hale getirilmesi.
- **Mimari & Geliştirmeler**:
  1. **Güvenli Backend API (`mobile-spa/public/api/evrak.php`)**:
     - Gate yetki doğrulaması (`evrak-takip/list`, `documents`), CSRF kontrolü ve PDO parametrik bağlama ile `EvrakTakipModel` üzerinden tüm işlemler yürütüldü.
     - **ISO 27001 Fiziksel Belge Şifreleme**: Yüklenen tüm PDF, görsel ve belgeler diske yazılmadan önce `Security::encryptFile()` ile şifrelendi; `stream-file` aksiyonunda anlık deşifre edilip güvenle sunuldu.
     - `list`, `meta`, `detail`, `save`, `delete`, `e-imza-onaya-sun`, `e-imza-onayla`, `e-imza-iade`, `e-imza-geri-al`, `arac-zimmet`, `stream-file`, `pdf` uç noktaları eksiksiz oluşturuldu.
  2. **Frontend Servisi (`mobile-spa/src/services/evrak.js`)**:
     - Reaktif veri akışı, liste, meta, detay, kaydetme, e-imza onay, iade, geri alma ve akıllı araç-zimmet sorgulama metotları tanımlandı.
  3. **Mobil Görünüm (`mobile-spa/src/views/EvrakView.vue`)**:
     - **TopBar**: Geri butonu, modül başlığı ve sağ üstte hızlı `+ Yeni Evrak` butonu.
     - **Acil E-İmza Bekleyen Bildirimi**: Kullanıcının onay sırası gelen evraklar varsa dinamik kehribar uyarı kartı ve tek dokunuşla "İmzamda" filtresi.
     - **4 Minimal Bento KPI Kartı**: Toplam Evrak, Gelen Evrak, Giden Evrak, Cevap Bekleyen.
     - **Canlı Arama & Filtre Hapları**: Evrak no, konu, kurum veya personel araması; Tümü, Gelen, Giden, Cevap Bekleyen, İmzamda filtreleri.
     - **Evrak Bento Kartları**: Yön rozeti (Gelen/Giden), evrak no ve tarih pilleri, dinamik durum rozetleri (İmzanızda!, E-İmzalı, Onayda X/Y, Cevap Bekliyor, Taslak), konu, muhatap kurum, zimmetli ve ilgili personel çipleri, trafik cezası plakası ve tutarı, ek dosya sayısı ve önizleme butonları, doğrudan e-imza / iade / düzenle / sil aksiyonları.
     - **Detay Bottom Sheet**: Kapsamlı evrak bilgileri, kurum ve adres detayları, giden evrak için E-İmza Stepper onay akışı (kimler onayladı, kimde bekliyor, iade gerekçesi), Resmî Yazı PDF önizleme butonu, ekli dosyaların in-app lightbox/iframe önizlemesi ve indirilmesi.
     - **Yeni/Düzenleme Form Sheet**: Gelen/Giden geçişi, otomatik evrak no, hazır konu çipleri (İcra Yazısı, Haciz Kaldırma, Trafik Cezası vb.), kurum adı, ofis ve ilgili personel seçimi, trafik cezalarında araç seçildiğinde otomatik sürücü sorgulama ve %25 erken ödeme indirimini anında hesaplama, giden evraklarda 1-3 imza yetkilisi seçimi, çoklu dosya yükleme.
  4. **SPA Navigasyon & Yönlendirme**:
     - `router.js` içerisine `/evrak-takip` rotası (`permission: 'documents'`) tanımlandı.
     - `BottomNav.vue` "Daha Fazla" menüsündeki "Evrak Takip" öğesi legacy linkten SPA rotasına (`to: '/evrak-takip'`) bağlandı.
     - `HomeView.vue` hızlı işlem linki SPA rotasına bağlandı.
  5. **Üretim Derlemesi (`npm run build`)**: Vite derlemesi 0 hata ile tamamlandı (`dist/assets/index-7biQxaKW.js`, `dist/assets/index-DmMLXj9B.css`).

- **Kullanıcı Talebi**: Gelen ve giden sekmelerinin de düzenlenmesi ve özet kartının minimal olması.
- **Mimari & Geliştirmeler**:
  1. **Minimal Bento Özet Kartı (`.fatura-mini-bento-strip`)**:
     - Kaba 3 kutulu dikey alan tüketen eski özet şeridi yerine; tek satırda başlık, sekmeye özel renkli mini ikon (`Send`, `Inbox`, `Edit3`), net toplam tutar (`formatMoney`) ve yan yana durum hapları (`Onaylı`, `Bekliyor`, `İptal` / `Kabul`, `Yanıt Bekliyor`, `Red`) konumlandırıldı.
     - Kartın en altına 3px yüksekliğinde onay/bekleme oranını gösteren zarif mini segmentli dağılım çizgisi (`.fatura-mini-strip__track`) entegre edildi.
  2. **Dinamik Sayaçlı Durum Filtreleri (`.fatura-filter-pills`)**:
     - Filtre butonlarına her durumun anlık adetlerini gösteren mini sayaç rozetleri (`.fatura-pill-num`) eklendi (Örn: `Tümü (14)`, `Onaylı (12)`, `Bekleyen (2)`).
     - Aktif durum Obsidian koyu zemin ve beyaz sayaçla netleştirildi.
  3. **Ferahlatılmış Fatura Kartları (`.fatura-card`)**:
     - No, belge türü ve durum rozeti dengelendi.
     - Cari adı okunaklı 2 satır tavanlı hale getirildi, takvim ikonu ile tarih ve VKN/TCKN bilgisi ayrıldı.
     - Kalem özeti için açık mor hafif subtle kutucuk sağlandı.
     - Kart footer alanında ödenecek tutar ve aksiyon butonları (`Yanıt Ver`, `Fatura Gör`, `Paylaş`, tahsilat etiketi) dokunmatik ergonomisine uygun hale getirildi.
  4. **Derleme**: `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı.

## Fatura sihirbazı okunabilirlik ve aksiyon düzeni (2026-10-15)
- Merkezi design-system.css üzerinden 16px form değerleri, 13px etiketler, 48px alanlar ve 50px gerçek buton yüzeyleri uygulandı.
- Özet adımında gönderim tam genişlikte ayrı satıra, uzun alıcı adı tam genişlikte ayrı bloğa taşındı. Kalem birim fiyatı tam satıra alındı; dar ekran alanları tek sütuna geçiyor.
- Gönderim/kayıt sırasında geri butonu devre dışı; taslak butonunda işlem durumu gösteriliyor.

## Mobil SPA E-Fatura Taslak Sihirbazı Okunabilirlik İyileştirmesi (2026-10-15)
- Wizard başlığına aktif adım ve o adımın amacı eklendi; adım göstergelerine `aria-current` erişilebilirlik bilgisi tanımlandı.
- Özet ekranı iki sütunlu grid düzenine taşındı; uzun alıcı adları artık değer alanında kontrollü satır kırarak okunuyor.
- Adım göstergeleri, header boşlukları ve alt “Geri / Devam Et” aksiyonları daha belirgin hale getirildi; üçüncü adımda ileri aksiyonu “Özeti Gör” olarak adlandırıldı.

## Mobil SPA E-Fatura & Finans Özet Sayfası Grafik Zenginleştirmesi, Yüzen Buton & Diğer Menüsü Düzeltmesi (2026-10-15)
- **Kullanıcı Talepleri**:
  1. Yüzen menü açma butonunun (`.floating-fatura-dock-btn`) menü kapalıyken biraz daha yukarıda konumlandırılması (alt dock ile çakışmaması).
  2. Alt navigasyon çubuğundaki ortada yer alan "Diğer" (LayoutGrid) butonuna basıldığında sayfanın navbar menüsünün (drawer'ının) açılması davranışının iptal edilmesi; doğrudan standart "Daha Fazla" (Diğer) modal sheet'inin açılması.
  3. E-Fatura & Finans sayfasındaki Özet sekmesinin görsel grafikler, analizler ve Bento kartlarıyla zenginleştirilmesi.
- **Mimari & Geliştirmeler**:
  1. **"Diğer" Butonu Düzeltmesi (`mobile-spa/src/components/BottomNav.vue`)**:
     - `handleCenterNavClick()` içindeki `isFaturaPage` ve `isAracPage` sayfa drawer açma blokları kaldırıldı.
     - Doğrudan `menuOpen.value = !menuOpen.value` çalıştırılarak kullanıcının her zaman "Daha Fazla" menüsüne ulaşması sağlandı.
  2. **Yüzen Menü Butonu Konumu (`mobile-spa/src/design-system.css`)**:
     - `.floating-fatura-dock-btn` ve `.floating-arac-dock-btn` için `bottom: calc(var(--ds-safe-bottom, 16px) + 96px);` tanımlandı.
     - Buton alt navigasyon barının tam 20px üstünde havada süzülerek ergonomik ve çakışmasız hale getirildi.
  3. **Özet Sekmesi Görsel Grafik & Bento Zenginleştirmesi (`mobile-spa/src/views/FaturaView.vue`)**:
     - **Obsidian Black Hero Bento Kartı**: Toplam Ticari İşlem Hacmi (`totalTradeVolume`), Net Ticari Bakiye (`netTradeBalance`) ve pozitif denge / alış ağırlıklı durum rozetleri.
     - **Çift Yönlü Segmentli Dağılım Çubuk Grafiği**: Satış (Zümrüt Yeşili) ve Alış (Gökyüzü Mavisi) hacim oranlarını gösteren dinamik progress çubuğu (`.fatura-segmented-track`) + interaktif detay lejantı (fatura adetleri ve ciro/maliyet tutarları).
     - **Gelişmiş KDV & Vergi Sağlığı Kartı**: Ödenecek veya Devreden KDV net tutarı, Alış KDV'sinin Satış KDV'sini karşılama yüzdesi ve renkli görsel ilerleme skalası (`.fatura-kdv-gauge-track`), Hesaplanan KDV ve İndirilecek KDV karşılaştırma kutuları.
     - **3'lü Finansal Bento Metrikler Şeridi**: Ortalama Satış Faturası tutarı, Ortalama Alış Faturası tutarı ve Efektif KDV Yükü Oranı (`%`).
  4. **Üretim Derlemesi (`npm run build`)**:
     - Vite derlemesi 0 hata ile başarıyla alındı (`dist/assets/index-BktlFprB.js`, `dist/assets/index-BOMhkaTH.css`).

## Mobil SPA E-Fatura Mal/Hizmet Adımı ve Wizard Navigasyonu İyileştirmesi (2026-10-15)
- Mal/hizmet kalem kartları beyaz Bento yüzey, kalem rozeti, daha net toplam satırı ve daha okunur alan aralıklarıyla düzenlendi.
- Wizard alt navigasyonu sağa hizalı, minimum 44px dokunma alanlı ve gölgeli sabit aksiyon çubuğuna dönüştürüldü; geri/devam butonları dengelendi.

## Mobil SPA E-Fatura Taslak Oluşturma Sheet UX Düzenlemesi (2026-10-15)
- `FaturaView.vue` içindeki 4 adımlı fatura sihirbazına her adım için açıklayıcı başlık ve renkli bağlam ikonu eklendi.
- Sheet yüksekliği, içerik alanı, alt aksiyon çubuğu ve güvenli alan boşlukları mobil kullanıma göre düzenlendi.
- Form alanları 44px dokunma yüksekliği, beyaz yüzey, daha net kontrast ve textarea davranışıyla iyileştirildi.

## Mobil SPA “Daha Fazla” Menüsü Renkli Bento İyileştirmesi (2026-10-15)
- `mobile-spa/src/design-system.css` içinde menü yapısı korunarak görsel iyileştirme yapıldı.
- Başlık etiketi mavi vurguya, kapatma butonu mavi çerçeve ve yumuşak gölgeye taşındı.
- Profil kartı açık mavi gradient ve derinlik gölgesi kazandı.
- İşlem ikonları 4’lü döngüyle mavi, zümrüt, mor ve kehribar aksanlara ayrıldı; kartlara hafif Bento gölgesi ve dokunma geri bildirimi eklendi.

## Mobil SPA Evrak Takip (E-Fatura & Finans) Rozet Konumlandırma Düzeltmesi ve Giden/Gelen İkonları (2026-10-15)
- **Kullanıcı Talebi**:
  1. Alt navigasyon çekmecesindeki sayı badge'lerinin (rozetlerinin) ikonlardan çok uzakta durması sorununun giderilmesi.
  2. "Giden" ve "Gelen" sekmelerindeki ok ikonlarının değiştirilmesi (anlamlı ve estetik yeni ikonlar).
- **Mimari & Geliştirmeler**:
  1. **Rozet Konumlandırması (`mobile-spa/src/design-system.css`)**:
     - `.bottom-nav .nav-icon` elemanındaki `position: static !important;` kuralı `position: relative !important;` olarak düzeltildi. Daha önce `static` olduğu için `.nav-bubble-badge` rozeti 24px ikona değil ~70px genişliğindeki butonun en sağına kaçarak komşu sekmelerin arasına taşmaktaydı.
     - `position: relative` ile rozet doğrudan 24x24px ikona çıpalandı (`top: -4px; right: -7px;`). Böylece sayılar (`4`, `15`, `0`) ikonun tam sağ üst köşesine (omuzuna) kusursuz şekilde yapıştı.
  2. **Giden ve Gelen İkonlarının Yenilenmesi (`BottomNav.vue` & `FaturaView.vue`)**:
     - Generic çapraz oklar (`ArrowUpRight` / `ArrowDownLeft`) yerine muhasebe/finans dünyasına uygun semantik ikonlar getirildi:
       - **Giden Faturalar (Satış)**: `Send` (Gönderim / Satış faturası kağıt uçak ikonu).
       - **Gelen Faturalar (Alış)**: `Inbox` (Gelen kutusu tepsisi ikonu).
     - Hem alt çekmecede (`BottomNav.vue`) hem de sayfa içindeki 2'li KPI kartlarında ve hızlı işlem butonlarında (`FaturaView.vue`) aynı ikonlar kullanılarak tam tasarım bütünlüğü sağlandı.
  3. **Üretim Derlemesi (`npm run build`)**:
     - Vite derlemesi 0 hata ile başarıyla tamamlandı (`dist/assets/index-Cx3WbHf3.js`, `dist/assets/index-B8ybxGju.css`).

## Mobil SPA Evrak Takip (E-Fatura & Finans) Sekmelerinin Talepler Gibi Alt Menüye Taşınması (2026-10-15)
- **Kullanıcı Talebi**:
  - Evrak takip (E-Fatura & Finans) sayfasındaki üstte bulunan menünün (Özet, Giden, Gelen, Taslaklar), Talepler sayfasındaki alt menü (obsidian floating dock drawer) gibi yapılması.
- **Mimari & Geliştirmeler**:
  1. **Servis & Reaktif Durum Yönetimi (`mobile-spa/src/services/fatura.js`)**:
     - `faturaState` reaktif nesnesi tanımlandı (`activeTab: 'ozet'`, `drawerOpen: true`, `counts: { giden: 0, gelen: 0, taslak: 0 }`).
     - `setFaturaTab(tab)`, `openFaturaDrawer()`, `closeFaturaDrawer()`, `toggleFaturaDrawer()` yardımcı fonksiyonları eklendi.
  2. **Alt Navigasyon Çekmecesi (`mobile-spa/src/components/BottomNav.vue`)**:
     - `showFaturaDrawer` computed özelliği eklendi (`route.path === '/fatura'` ve `faturaState.drawerOpen`).
     - 5 kolonlu (`fatura-nav-tabs`, `--nav-count: 5`) çekmece yapısı kuruldu:
       - 1. Özet & KDV (`ReceiptText` ikonu)
       - 2. Giden (`ArrowUpRight` ikonu, rozet: `faturaState.counts.giden`)
       - 3. Gelen (`ArrowDownLeft` ikonu, rozet: `faturaState.counts.gelen`)
       - 4. Taslaklar (`Edit3` ikonu, rozet: `faturaState.counts.taslak`)
       - 5. Kapat (`X` ikonu, menüyü kapatıp standart BottomNav'a döner)
     - `handleCenterNavClick` içine Fatura sayfası desteği eklendi; alt menü kapalıyken orta "Diğer" butonuna tıklandığında menü anında tekrar açılır.
     - "Daha Fazla" menüsünden E-Fatura'ya tıklandığında menünün açık gelmesi garanti edildi.
  3. **Fatura Görünümü & Sekme Senkronizasyonu (`mobile-spa/src/views/FaturaView.vue`)**:
     - Üst kısımdaki eski `.clay-tabs-wrap.fatura-tabs` bloğu kaldırılarak ekran dikeyde ferahlatıldı.
     - `activeTab` ve `faturaState.activeTab` iki yönlü reaktif senkronize edildi.
     - Menü kapatıldığında kullanıcının tek dokunuşla geri açabilmesi için `.floating-fatura-dock-btn` yüzen açma butonu eklendi.
     - `loadDashboard` ve `loadInvoices` içinde Giden, Gelen ve Taslak fatura sayıları `faturaState.counts` nesnesine aktarılarak alt menü ikonları üzerinde dinamik badge gösterimi sağlandı.
     - `onMounted` aşamasında `openFaturaDrawer()` çağrıldı; `onBeforeUnmount` aşamasında `closeFaturaDrawer()` ile temizlik yapıldı.
  4. **Tasarım Sistemi (`mobile-spa/src/design-system.css`)**:
     - `.fatura-nav-tabs` ve `.floating-fatura-dock-btn` stilleri merkezi CSS'e entegre edildi.
  5. **Üretim Derlemesi (`npm run build`)**:
     - Vite derlemesi 0 hata ile başarıyla tamamlandı.

## Mobil SPA Araç Takip Minimal Üst Özet Kartı ve TR Plaka Mavi Alanı Yuvarlatma (2026-10-15)
- **Kullanıcı Talebi**:
  1. Üstteki özet kartının (Koyu Bento Hero kartı) biraz daha minimal (dikeyde daha kompakt, daha az yer kaplayan) yapılması.
  2. Plaka görseli (`.tr-plate-badge`) içindeki mavi "TR" alanının (`.tr-band`) kenarlarının biraz `border-radius` (yuvarlatılmış) olması.
- **Mimari & Geliştirmeler**:
  1. **Minimal Koyu Hero Bento Kartı (`mobile-spa/src/views/AracView.vue`)**:
     - `<section class="bento-card bento-card--dark arac-hero-bento">` sınıfı eklendi.
     - `.arac-hero-bento` ile kart dikeyde ~%40 sıkılaştırıldı:
       - İç dolgu: `padding: 13px 15px 14px !important; border-radius: 20px !important;`
       - Başlık alanı: `margin-bottom: 8px !important;`, başlık fontu `15px`, etiket `8px` (`padding: 2px 6px`).
       - Mini aksiyon ikonu: `width: 28px; height: 28px;`
       - Segmentli ilerleme çubuğu: `height: 5px; margin-bottom: 9px; gap: 2.5px;`
       - 4'lü Metrik Izgarası: `gap: 7px;`
       - Metrik hücreleri: `padding: 7px 10px; border-radius: 10px; background: rgba(255, 255, 255, 0.04);`
       - Rakamlar: `font-size: 14.5px;`, etiketler: `font-size: 9.5px;`
  2. **Plaka Görseli TR Mavi Alanı Yuvarlatma (`.tr-plate-badge`, `.tr-band`)**:
     - `.tr-plate-badge` içine `padding: 1.5px; border-radius: 7px;` uygulandı.
     - `.tr-band` mavi alanı için `border-radius: 4px; padding: 3px 5px;` tanımlandı.
     - Böylece mavi TR alanı plaka içinde köşeleri zarifçe yuvarlatılmış ayrı ve net bir rozet kimliği kazandı.
  3. **KM Gösterim Güvenliği**:
     - `formatKm` fonksiyonu ve `km-diff-pill` / form alanlarında `Math.max(0, ...)` ve `Math.abs(...)` kullanılarak olası negatif gösterimlerin önüne geçildi.
  4. **Derleme**:
     - `npm run build` başarıyla tamamlandı.

## Mobil SPA Araç Takip Üst Koyu Bento Kartının Dinamikleştirilmesi ve Alttaki Özet Kartlarının Kaldırılması (2026-10-15)
- **Kullanıcı Talebi**:
  1. Üstteki koyu Bento kartta her sekmenin (Araçlar, Zimmet, Yakıt, KM, Servis) özet verilerinin gelmesi.
  2. Evrak yenileme uyarısının yalnızca "Araçlar" sekmesinde görünmesi.
  3. Alttaki beyaz özet kartlarının (Yakıt toplamı, KM mesafesi) tamamen kaldırılması (çünkü zaten üstte gösterilecek).
- **Mimari & Geliştirmeler**:
  1. **Backend Özet Metrikleri (`mobile-spa/public/api/arac.php` & `dist/api/arac.php`)**:
     - `$tabTotals` dizisi her sekme için zenginleştirildi:
       - `zimmet`: aktif zimmet sayısı, iade zimmet sayısı, toplam hareket sayısı.
       - `yakit`: toplam tutar, toplam litre, toplam fiş sayısı, ortalama dolum tutarı.
       - `km`: toplam yapılan net km, toplam yolculuk kayıt sayısı, ortalama sürüş mesafesi.
       - `servis`: aktif servisteki araç sayısı, tamamlanan bakım sayısı, toplam servis tutarı, toplam servis kayıt sayısı.
  2. **Dinamik Koyu Hero Bento Kartı (`mobile-spa/src/views/AracView.vue`)**:
     - `heroCardMeta` computed özelliği ile sekmeye özel etiket, başlık ve ikon dinamik hale getirildi:
       - `araclar`: CANLI FİLO DURUMU / Filo Operasyon Özeti (`Car` ikonu)
       - `zimmet`: ZİMMET DURUMU / Zimmet Operasyon Özeti (`UserCheck` ikonu)
       - `yakit`: AKARYAKIT & TÜKETİM / Yakıt Operasyon Özeti (`Fuel` ikonu)
       - `km`: YOLCULUK & KİLOMETRE / KM Operasyon Özeti (`Gauge` ikonu)
       - `servis`: BAKIM & ONARIM / Servis Operasyon Özeti (`Wrench` ikonu)
     - Her sekmeye özel segmentli progress track (Zimmet, Filo, Servis oranları) entegre edildi.
     - 4'lü Koyu Metrik Izgarası (`.bento-metrics-grid--dark`) her sekmenin kritik operasyon verilerini tam olarak dolduracak şekilde dinamikleştirildi.
  3. **Evrak Uyarısının Araçlar Sekmesiyle Sınırlandırılması**:
     - `v-if="activeTab === 'araclar' && summary.evrak_uyari_sayisi > 0"` koşulu ile yalnızca Araçlar sekmesinde gösterilmesi sağlandı.
  4. **Alttaki Beyaz Özet Kartlarının Temizlenmesi**:
     - `.yakit-summary-bento` ve `.km-summary-bento` blokları kaldırıldı; mükerrer bilgi ve sayfa kalabalığı ortadan kalktı.
  5. **Derleme & Doğrulama**:
     - `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/assets/index-DNaGdPfR.js`, `dist/assets/index-CXfrJ4ZP.css`).

## Mobil SPA Araç Takip Sekmelerinin Navbar Alt Menüsüne Taşınması (Scrollable & Sabit Kapat Butonlu) (2026-10-15)
- **Kullanıcı Talebi**:
  - Araç takip sayfasındaki üstte bulunan menünün (Araçlar, Zimmet, Yakıt, KM, Servis...), Talepler sayfasındaki alt menü gibi yapılması; sekmelerin yatayda kaydırılabilir (scrollable) olması ve Kapat butonunun sağ tarafta sabit kalması.
- **Mimari & Geliştirmeler**:
  1. **Servis & Durum Yönetimi (`mobile-spa/src/services/arac.js`)**:
     - `aracState` reaktif nesnesi oluşturuldu (`activeTab: 'araclar'`, `drawerOpen: true`).
     - `setAracTab`, `openAracDrawer`, `closeAracDrawer`, `toggleAracDrawer` yardımcı fonksiyonları tanımlandı.
  2. **Navbar Çekmecesi & Kaydırma Yönetimi (`mobile-spa/src/components/BottomNav.vue`)**:
     - Araç Takip modülü için alt çekmece (`showAracDrawer`) entegre edildi.
     - Sekmeler (`Araçlar`, `Zimmet`, `Yakıt`, `KM`, `Servis`) scrollable alana (`.arac-nav-tabs__scroll`) yerleştirildi.
     - Seçili sekme tıklandığında veya aktif tab değiştiğinde `scrollIntoView` ile otomatik odaklama/hizalama sağlandı.
     - En sağdaki `Kapat` butonu (`.arac-nav-tabs__close`) `sticky`/sabit flex elemanı olarak solundaki derinlik gölgesi ve obsidian zemin ile kayan sekmelerin arkasında kalmasını sağladı.
     - Kapat'a basıldığında `closeAracDrawer` ile alt menü kapanarak standart ana navbar sekmelerine dönülmesi sağlandı; kullanıcı Araç sayfasındayken ana bardaki butona bastığında çekmece yeniden açılıyor.
  3. **Tasarım Sistemi & CSS Tokenları (`mobile-spa/src/design-system.css`)**:
     - `.arac-nav-tabs`, `.arac-nav-tabs__scroll`, `.arac-nav-tabs__close` ve `.floating-arac-dock-btn` sınıfları eklendi.
     - Dokunmatik kaydırma optimizasyonları (`-webkit-overflow-scrolling: touch`, `scrollbar-width: none`) uygulandı.
  4. **Araç Takip Görünümü Temizliği (`mobile-spa/src/views/AracView.vue`)**:
     - Sayfa gövdesindeki kaba ve sağa taşan eski `clay-tabs-wrap` menüsü kaldırıldı.
     - `onMounted` ve `onBeforeUnmount` lifecycle hook'ları ile drawer açılış/kapanış senkronizasyonu sağlandı.
     - Alt çekmece kapalıyken kullanıcının dilediğinde menüyü tekrar açabilmesi için yüzen zarif bir tetikleyici buton eklendi.
  5. **Derleme & Doğrulama**:
     - `npm run build` ile Vite üretim derlemesi 0 hata ile tamamlandı (`dist/assets/index-Dla54YOr.js`, `dist/assets/index-Cc4s4sZN.css`).

## Mobil SPA Ana Navbar "Diğer" İkonu ve Talepler Sayfası Üst Çubuk İyileştirmesi (2026-10-15)
- **Kullanıcı Talebi**:
  1. "ana navbarda diğer ikonu düzensiz geldi": Ana navbardaki "Diğer" (LayoutGrid) ikonunun diğer 4 sekme (Ana Sayfa, Personel, Raporlar, Talepler) ile aynı hiza, aynı boyut ve düzenli görünmesi.
  2. "tümü butonu çıkmasın yukarıda gerek yok": Talepler sayfasında durum filtreleri satırında yer alan gereksiz "Tümü ▾" kategori butonunun kaldırılması.
- **Mimari & Geliştirmeler**:
  1. **Ana Navbar "Diğer" İkonu Standardizasyonu (`mobile-spa/src/components/BottomNav.vue`, `src/styles.css`, `src/design-system.css`)**:
     - `styles.css` içindeki eski yükselen yuvarlak buton mirası (`.nav-link--center .nav-icon { position: absolute; top: -20px; width: 48px; ... }`) tamamen kaldırıldı.
     - `BottomNav.vue` içinde "Diğer" butonu diğer tüm sekmelerle birebir aynı yapıya getirildi: standart `nav-link` sınıfı, `LayoutGrid` ikonu (`size: 21`, `stroke-width: 1.9` / aktifken `2.4`), `nav-label` "Diğer".
     - `design-system.css` içinde `.bottom-nav .nav-icon` için `position: static !important; top: 0 !important;` ve `24x24px` merkezi hizalama kuralları katı biçimde tanımlandı; 5 buton da aynı dikey baseline ve grid yüksekliğini paylaşıyor.
  2. **Talepler Sayfasında Üst "Tümü" Butonunun Kaldırılması & Simetrik Durum Pilleri (`mobile-spa/src/views/TaleplerView.vue`)**:
     - Durum filtreleri satırındaki `.pill-category-badge` ("Tümü ▾") butonu şablondan ve stilden tamamen kaldırıldı.
     - `Bekleyenler`, `İşlem Görenler` ve `Silinenler` butonları `.status-pills-group .pill-btn` (`flex: 1; justify-content: center;`) ile satıra tam simetrik ve dengeli dağıtıldı.
     - Kategori filtreleme işlevi alt navbardaki akıcı çekmece menüye (`Tümü`, `Avans`, `İzin`, `Genel`, `Kapat`) devredildi.
  3. **Derleme & Doğrulama**:
     - `npm run build` ile Vite derlemesi 0 hata ile tamamlandı (`dist/assets/index-35oEaH-n.js`, `dist/assets/index-BsKW8aWz.css`).

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
- Modal sonuçlarına alan bazında benzersiz personel sayısı grafiği eklendi. Her personel aynı alan için bir kez sayılıyor; örneğin Yemek Yardımı farkı bulunan kişi adedi yatay çubuk ve sayı olarak gösteriliyor.
- Karşılaştırma sonrasında `Özet ve Açık Farkları Excel'e İndir` işlemi sunuluyor. XLSX dosyasının Özet sayfasında durum sayaçları ve alan bazında kişi sayıları; Açık Fark Satırları sayfasında personel/T.C./durum/alan/muhasebe/gönderilen/fark değerleri filtrelenebilir, gruplanmamış normal satırlar halinde bulunuyor.
- Excel detay düzeni kullanıcı talebiyle kişi matrisi olarak değiştirildi: her personel tek satırda; ilk üç kolon Personel/T.C./Durum, devamında her karşılaştırma kalemi için birleştirilmiş kalem başlığı altında `Muhasebe / Gönderilen / Fark` üçlüsü bulunuyor. Fark hücreleri kırmızı vurgulanıyor, başlık filtresi açık ve ilk üç kolon sabitleniyor.

## 2026-10-16 — Ücret hesap pusulasında yemek yuvarlama sunumu
- `bordro/raporlar/bordro` resmî ücret hesap pusulasında `Yemek günlük yuvarlama farkı` ayrı ek kazanç satırı olarak kaldırıldı. Kayıtlı yuvarlama farkı değiştirilmeden `Yemek yardımı` tutarına ekleniyor ve pusula/PDF üzerinde tek yemek satırı gösteriliyor.
- Bu yalnız sunum değişikliğidir; snapshot, `yuvarlama_farki`, banka ödemesi ve kazanç toplamı korunuyor. Pusula testleri 6 test / 45 doğrulamayla geçti.
- Ücret hesap pusulası/PDF ek kazançlarında `Hafta içi nöbet` etiketi de resmî alacak detayındaki adlandırmayla eşitlenerek `Fazla Mesai Ücreti (Net)` olarak gösteriliyor. Birden fazla hafta içi nöbet kalemi varsa pusulada tek satırda toplanıyor; tutarlar ve snapshot değişmiyor. Pusula testi 6 test / 47 doğrulamaya çıktı.
- Gerçek etiketteki büyük Türkçe `İ`, küçük harfe dönüşürken Unicode birleşik nokta oluşturduğu için ilk eşleme çalışmıyordu. Karşılaştırma metni birleşik noktadan arındırıldı ve gerçek `Hafta İçi Nöbet` yazımıyla regresyon testi doğrulandı.
- Pusuladaki kesinti etiketlerine yayın servisi otomatik `kesintisi` son eki eklediği için `İcra Kesintisi kesintisi` gibi tekrarlar oluşuyordu; ayrıca muhasebe işareti olan negatif tutar doğrudan basılıyordu. Otomatik son ek kaldırıldı, bölüm `KESİNTİLER` olarak sadeleştirildi ve pusulada tutarlar pozitif kesinti büyüklüğüyle gösterildi. Net ödeme ve kayıtlı kesinti değerleri değişmedi; pusula/yayın testleri 14 test / 73 doğrulama geçti.

## 2026-10-16 — Personel test yayını veritabanı düzeltmesi
- `Seçilen Personelde Test Et` işleminin genel hata vermesinin nedeni PHP hata günlüğünde `bordro_yayin_dokum.pusula_no` sütununun bulunmaması olarak doğrulandı; ücret pusulası/onay genişletme migrasyonu uygulama kodundan sonra veritabanına uygulanmamıştı.
- `database/migrations/2026_10_16_expand_bordro_yayin_onay.sql` yerel veritabanına uygulandı. Pusula numarası, oluşturma/gönderim ve onay kanıtı alanları ile itiraz ekleri tablosu oluşturuldu; aktif eski dökümlerin pusula numaraları geriye dönük dolduruldu.
- Şema kontrolünde beklenen 7 döküm alanı ve ek tablosu mevcut, pusula numarası eksik aktif kayıt sayısı `0`. İlgili ücret pusulası/yayın testleri 30 test ve 117 doğrulamayla geçti.
- Bordro yayın test akışında önceki davranışın dönemdeki tüm aktif test dökümlerini pasifleştirdiği doğrulandı. Test tekrarı artık yalnız aynı personelin eski testini soft-delete eder; farklı personellerin aktif testleri birlikte korunur ve takipte listelenir.
- Bordro yayın modalı her açıldığında takip verisi ile kapalı dönemin döküm önizlemesi sunucudan yeniden yükleniyor. Böylece sayfa yenilemesinden sonra tarayıcı belleği boş olsa bile önizleme ve sayaçlar tekrar kuruluyor. Test kayıtları yönetim KPI aktif toplamına dahil edildi.
- Çoklu test regresyonunda iki farklı personelin testinin birlikte aktif kaldığı, aynı personel yeniden test edildiğinde yalnız onun önceki testinin pasifleştiği doğrulandı.
- Muhasebe maaş bordrosu karşılaştırması genişletildi. `T.Gün`, `Normal Gün`, `Hafta Tatili`, `Ücretli İzin` ve `Rapor` kolonları ayrı gün alanları olarak okunuyor; muhasebedeki Normal Gün artık yemek/fiilî günle değil snapshot `normal_gun` değeriyle karşılaştırılıyor.
- Kişi detayında gün alanlarının tamamı ayrı bir Bootstrap accordion tablosunda Muhasebe / Gönderilen / Fark olarak sunuluyor. Toplam gün eşit olsa bile normal, hafta tatili veya ücretli izin farkı kişi durumuna ve fark sayısına yansıyor; diğer parasal farklar ayrı tabloda kalıyor.
- Yeni bordro snapshot'ları hafta tatili ve genel tatil günlerini açıklayıcı matrah alanlarında saklıyor. Eski snapshot'larda hafta tatili, kayıtlı toplam günün normal + ücretli izin + genel tatil dışındaki bakiyesiyle tamamlanıyor; parasal sonuç ve ödeme kanalları değiştirilmiyor.
- Gerçek `files/bordro_ersan_elkt İHALE 3.xlsx` dosyasında 103 satır doğru okundu. Örnek ilk satır 30 toplam / 26 normal / 4 hafta tatili / 0 ücretli izin / 0 rapor olarak ayrıştırıldı. 103 eşleşmede toplam gün aynı olduğu halde alt gün kırılımı farklı 55 satır bulundu.
## 2026-10-18 — Mobil SPA Evrak Takip ek sorgusu şema uyumu

- Evrak detayında `Unknown column 'ee.yukleyen_kullanici_id'` hatasının, çalışan veritabanında `evrak_takip_ekleri.yukleyen_kullanici_id` kolonunun bulunmamasından kaynaklandığı doğrulandı.
- `sql/evrak_takip_coklu_dosya_yukleyen.sql` kolon ve indeks kontrolleri `information_schema` üzerinden tekrarlanabilir hâle getirildi ve çalışan şemaya uygulandı.
- Mevcut eklerin yükleyen kullanıcısı evrak oluşturucusundan tamamlandı; legacy `evrak_takip.dosya_yolu` kayıtlarının ek tablosuna aktarımı korundu.
- Migration iki kez başarıyla çalıştırıldı; kolon/indeks varlığı ve `EvrakTakipModel::getAttachments()` sorgusunun hatasız çalışması doğrulandı.

## 2026-10-18 — Mobil SPA Evrak personel seçenekleri ve araç zimmeti

- Evrak formunun meta API'sindeki personel seçenekleri `PersonelModel::all(true, 'evrak')` ile yalnız aktif personellerle sınırlandı; ofis personeli alt listesi de bu aktif havuzdan üretiliyor.
- Araç plakası değiştiğinde önce eski ilgili personel seçimi temizleniyor, evrak tarihindeki araç zimmeti API'den bulunup `İlgili Personel` alanına otomatik atanıyor.
- Hızlı plaka değişimlerinde eski isteğin yeni seçimi ezmemesi için yanıt-plaka kontrolü eklendi; zimmet bulunamadığında kullanıcı bilgilendiriliyor.
- Araç zimmeti sorgusuna araç/personel firma ve soft-delete izolasyonu ile `0000-00-00` iade tarihi uyumu eklendi.
- Gerçek veride evrak personel havuzu 168 kayıttan 78 aktif kayda düştü ve örnek aktif araç zimmeti doğru personelle çözüldü. PHP sözdizimi, Vite üretim derlemesi ve diff kontrolü geçti.

## 2026-10-18 — Mobil SPA cari hareket tutarları

- Kullanıcı kararıyla cari detayındaki Hesap Hareketleri listesi ve hareket detay penceresinde yön işaretleri geri getirildi: Aldım `-`, Verdim `+` olarak gösteriliyor.
- Mobil cari detayındaki `Top. Aldım` ve `Top. Verdim` özetleri dokunulabilir filtrelere dönüştürüldü; seçili özete yeniden dokunmak tüm hareketleri geri getiriyor.
- Masaüstü cari hesap hareketlerinde de giriş/çıkış özet kartlarının tamamı DataTables sunucu filtresini çalıştırıyor; fare ve klavye etkileşimi ile aktif durum göstergesi eklendi.

# 2026-10-20 — Ana sayfa kesme/açma karşılaştırma toplamı

- Günlük Kesme/Açma kartının aylık değeri tüm ücretli kesme sonuçlarını içerirken karşılaştırma kartının varsayılan olarak yalnız `olumlu` sonuçları göstermesi nedeniyle oluşan 9.860 / 9.857 farkı tespit edildi; üç kayıt olumlu filtre dışında kalıyor.
- Kesme/Açma Karşılaştırma kartının Bölge ve Personel detay tablolarına, aktif sonuç filtresine göre her dönemi ve değişim oranını hesaplayan belirgin `TOPLAM` satırı eklendi.
- `views/home.php` PHP sözdizimi doğrulandı.

## 2026-10-18 — Cari ekstre PDF ve toplu yedek

- Mobil cari detayındaki çalışmayan kök `/views/...` ekstre bağlantısı, alt dizin kurulumundan bağımsız `/api/cari-ekstre.php` indirme endpoint'ine taşındı.
- Tekil PDF üretimi ortak `CariEkstreExportService` üzerinden Model kaynaklı özet ve hareket verisiyle çalışıyor.
- Masaüstü Cari Listesi İşlemler menüsüne ve mobil Cari Takip üst araç çubuğuna `Tüm Ekstreleri Yedekle` eylemi eklendi.
- Toplu çıktı tek ZIP içinde her aktif cari için ayrı PDF, `cari-ozet.csv` ve `BILGI.txt` üretir; mevcut `cari_hesap_hareketleri` yetkisi hem sunucu endpoint'inde hem buton görünürlüğünde uygulanır.
- Gerçek 13 aktif cariyle 13 PDF ve yetkili ZIP endpoint çıktısı doğrulandı.
- Web isteğinde toplu çıktının başarısız olmasının mPDF varsayılan `vendor/.../tmp/mpdf` dizini yazma yetkisi olduğu logdan doğrulandı. PDF servisi, işletim sistemi kullanıcısına özel `0700` sistem geçici dizini kullanacak şekilde düzeltildi.

## 2026-10-18 — Okuma personeli için otomatik HTÇ

- Pazar günü kişi bazında firma eşiğini (varsayılan 10) karşılayan Okuma/Endeks Okuma personeline HTÇ üreten merkezi `OkumaHtcAutomationService` eklendi.
- Mevcut veya sanal HT, 23:45 endeks sorgusundan sonra HTÇ'ye dönüştürülüyor; pazartesi 08:00'de önceki pazar yeniden sorgulanıp yalnız otomatik kayıtlar uzlaştırılıyor.
- Eski HT satırları dönüştürülebilir; geliştirme sonrasında masaüstü veya mobil puantajdan yapılan değişiklikler kaynak/kilit bilgisiyle korunuyor. Kayıtsız çalışma günü `X` seçimi de ayrı otomasyon kilidiyle sonraki crondan korunuyor.
- HT dışındaki manuel kodlar değiştirilmez. Eşik altına düşen yalnız otomatik HTÇ soft delete edilir ve gün varsayılan HT görünümüne döner.
- Firma ayarlarına otomasyonu açma/kapama ve minimum okuma alanları eklendi. Bordro HTÇ tutar hesabı değiştirilmedi; üretim kuralı bordro dokümanına işlendi.
- Migration uygulanmadan özellik çalıştırılmamalıdır: `database/migrations/2026_10_18_add_okuma_htc_automation.sql`.
- Yeni otomasyon karar testleri 4 test / 9 doğrulama geçti. İlgili bordro regresyonlarında 57 test / 601 doğrulama geçti; daha önce mevcut 7 uyarı sürüyor.
- Kapsam kaçak (varsayılan kişi başı 2), sökme-takma (10) ve mühürleme (10) pazar işlemlerine genişletildi. Kişinin dört operasyon türünden en az birinin limitini sağlaması HTÇ için yeterli; her limit firma ayarından değiştirilebilir.
# Puantajda hafta içi nöbet fazla mesaisi (2026-10-19)

- Onaylı `standart` nöbetler, kaynak kayıt çoğaltılmadan masaüstü ve mobil puantajda gün başına 4 saat fazla mesai olarak gösteriliyor.
- Manuel puantaj mesaisi ile nöbet mesaisi günlük ve aylık toplamda birleşiyor; örneğin aynı gün 4 saat nöbet + 4 saat manuel kayıt `X+8S`, ay içindeki başka 4 saatlik nöbetle aylık toplam 12 saat oluyor.
- Puantaj Excel dışa aktarımında günlük kod ve toplam mesai aynı hücrede `X+4S` biçiminde yazılıyor. Nöbet silme/onay kaldırma/iptal işlemleri kaynak sorgudan otomatik yansıyor; bordroda çift ödeme yaratacak `personel_fazla_mesaileri` kopyası oluşturulmuyor.
- Fazla mesai modalındaki işlem kalıcı kayıt sanılmasın diye `Puantaja Uygula` olarak adlandırıldı ve üst araç çubuğundaki `Kaydet` gereksinimi açık uyarıyla belirtildi. Ahmet Giray Uçar'ın 7 Ekim 2026 tarihli ek 4 saati onaylı kaydedilip açık Ekim bordrosu yeniden hesaplandı; snapshot özeti 12 saat / 2.246,04 TL net fazla mesai veriyor.
- Bordro detay sınıflandırması düzeltildi: sözleşme alacağı tarafında hafta içi nöbet, hafta sonu nöbet ve manuel fazla mesai ayrı satırlarda; resmî tarafta hafta içi nöbetin kanuni karşılığı ile manuel mesai tek `Fazla Mesai Ücreti (Net)` satırında 12 saat / 2.246,04 TL olarak gösteriliyor. Kayıtlı snapshot ve ödeme kanalları değiştirilmedi.
- Masaüstü puantajda fazla mesai ekleme/kaldırma artık modal veya hücre işleminden doğrudan API'ye kaydediliyor; ikinci bir üst `Kaydet` adımı gerekmiyor. İşlem sonrası açık bordronun yeniden hesaplanması gerektiği bildiriliyor. Ahmet Giray Uçar'ın 7 Ekim manuel 4 saat kaydı soft-delete edilip Ekim bordrosu yeniden hesaplandı; snapshot yeniden 8 saat / 1.497,36 TL ve banka 30.598,86 TL oldu.
- Eski toplu `Kaydet` isteğinin sunucuda başarısız olmasının kök nedeni `OkumaHtcAutomationModel::softDelete()` metodunun ana Model imzasıyla uyumsuz override edilmesiydi. Özel işlem `softDeleteAutomatic()` olarak ayrıldı ve servis çağrıları güncellendi. Gerçek puantaj API'sinde 4 saat ekle → kaldır testi başarıyla geçti; kayıt soft-delete kaldı.
- Bordro sözleşme alacağı tarafındaki hafta içi nöbet alt detayı teknik 4 saat bilgisinden arındırıldı. Kayıt açıklaması değişmeden görünüm `2 Gün × 500,00 ₺` biçiminde; 4 saat çarpanı yalnız resmî fazla mesai hesabında kullanılıyor.
- Onaylı `hafta_sonu` nöbet tarihleri merkezi HTÇ gün kümesine dahil edildi. Puantaj HTÇ ile aynı tarihe denk gelirse `DISTINCT` tarih mantığıyla tek gün sayılıyor; liste cache'i ve doğrudan hesap aynı kaynağı kullanıyor. Ahmet Giray Uçar Ekim bordrosu 1 HTÇ günüyle yeniden hesaplandı; resmî HTÇ neti 935,85 TL, banka ödemesi 31.534,71 TL oldu.
- Onaylı hafta sonu nöbeti masaüstü ve mobil puantajda ayrıca fiziksel izin kaydı oluşturmadan kilitli `HTÇ` olarak gösteriliyor. Aynı güne manuel HTÇ gönderimi sunucu tarafında reddediliyor; Excel çıktısı da nöbet kaynaklı günü `HTÇ` gösteriyor. Nöbet silinir/onayı kaldırılırsa türetilmiş HTÇ otomatik kayboluyor.
- Bordro detayının sözleşme alacağı tarafında hafta içi ve hafta sonu nöbet hakedişleri tek `Nöbet Ödemeleri` satırında birleştirildi. Açılır detay hafta içini `gün × birim tutar`, hafta sonunu `adet × birim tutar` olarak ayrı gösteriyor; teknik `4 Saat` ibaresi ve manuel fazla mesai bu gruba dahil edilmiyor.
- 2026-10-19: Evrak Takip'te eski ana dosya (`evrak_takip.dosya_yolu`) ile yeni çoklu ekler birlikte gösterilecek şekilde liste ve düzenleme akışı düzeltildi. İkinci belge eklendiğinde ana belge artık görünümden düşmüyor; aynı yol iki kaynakta varsa mükerrer gösterilmiyor.
- 2026-10-19: Evrak Takip masaüstü listesi server-side DataTables yapısına geçirildi. İlk açılışta tüm evrak/ek/onay HTML'i üretilmiyor; sayfalama, genel arama, kolon filtreleri, sıralama, KPI hızlı filtreleri ve “imzamda” filtresi PDO sorgularıyla sunucuda uygulanıyor. Sayfadaki kayıtların ek ve onayları toplu sorgulanıyor. Liste indeks migration'ı aktif veritabanına uygulandı.
- 2026-10-19: Evrak server-side liste geçişinde merkezi gelişmiş filtre modülü, sunucudan gelen satırlara aynı filtreyi istemcide tekrar uyguladığı için kayıtları gizliyordu. Server-side tablolarda istemci eşleştirmesi kapatıldı; `equals:`, tarih aralığı, metin modu ve çoklu seçim protokolü `ajaxList()` içinde çözümleniyor. Kayıtlı `06.08.2026` filtresi gerçek API yanıtında 2 satırla doğrulandı.
## Giden Evrak Dinamik Gönderim Kanalı (2026-10-19)
- Giden Evrak tek sayfasına `normal` ve `kep` seçenekli kart tabanlı gönderim kanalı eklendi; resmî üst yazı / yalnızca ek tercihi kanaldan bağımsız bırakıldı.
- KEP seçildiğinde geniş oluşturma panelinde çoklu alıcı, PTT rehber doğrulaması, ileti/paket türü, ileti detayı, mesaj metni ve dosya özeti gösteriliyor. Sağ panel yalnızca firma KEP durumu, sevk kaydı ve yasal deliller için kullanılıyor.
- KEP taslağı veritabanında saklanıyor; sevk API'si istemciden konu/içerik kabul etmek yerine kayıtlı ve onaylanmış evrak snapshot'ını kullanıyor. Yetki, firma ayarı, kanal, tekrar gönderim, alıcı, paket türü ve belge biçimi sunucuda doğrulanıyor.
- `sql/2026_10_19_add_giden_evrak_gonderim_kanali.sql` aktif geliştirme veritabanına uygulandı. Eski kayıtlar normal, önceden KEP sevk edilmiş kayıtlar KEP kanalı olarak ele alınıyor.

## Evrak Takip Bağımsız KEP Gönderisi Modalı (2026-10-19)
- Kullanıcı geri bildirimiyle KEP oluşturma, resmî üst yazılı Giden Evrak formundan ayrıldı. Araç çubuğundaki tek `Yeni` menüsü Gelen Evrak, Giden Evrak ve yetkiye bağlı KEP Gönderisi seçeneklerini sunuyor; KEP seçeneği PTT arayüzüne benzer geniş modal açıyor.
- Modal; çoklu alıcı, PTT rehber doğrulaması, konu, ileti tipi/detayı, zengin ileti metni, çoklu ek ve ek boyutu özeti sunuyor. Gönderim `kep-dogrudan-gonder` politikasıyla sunucuda ayrıca korunuyor.
- Başarılı bağımsız KEP gönderisi `kep_mesajlar` yanında `evrak_takip` içine `kaynak_tipi=kep` olan kilitli/onaylı bir giden kayıt oluşturuyor; böylece listede KEP rozetiyle izleniyor ve mevcut detay/delil görünümünü kullanıyor.
- Yeni Giden Evrak ekranındaki kanal kartları ve KEP oluşturma paneli kaldırıldı; ekran tekrar yalnızca resmî üst yazı ve ek akışına odaklandı. Eski KEP kayıtlarının takip/delil görünümü korundu.
- KEP modal include dosyasının ana sayfadaki `Form` alias'ını devralmaması nedeniyle oluşan fatal render hatası, helper çağrıları tam `\App\Helper\Form` namespace'iyle yapılarak giderildi. Modal CLI render testiyle doğrulandı; sayfanın yarıda kesilip DataTables verilerinin gelmemesi engellendi.
- KEP modalındaki form sarmalayıcısının Bootstrap scrollable modal flex hiyerarşisini bozması giderildi. Dialog viewport yüksekliğine sınırlandı; form flex-column, modal body bağımsız `overflow-y:auto`, header/footer sabit olacak şekilde düzenlendi ve editör yüksekliği azaltılarak Ekler alanı ile gönderim aksiyonlarına kaydırarak erişim sağlandı.
- PTT `KontorSorgula` servisinin kimlik hatasını `miktar=0`, `cins=Şifre veya Parola Yanlış` biçiminde döndürmesi artık başarı sayılmıyor. KEP gönderi modalı açılışta canlı bağlantı/bakiye kontrolü yapıyor; kayıtlı eski bakiye `Son bakiye` olarak ayrıştırılıyor ve canlı kimlik doğrulaması başarısızsa gönderim butonu kapatılıp açık hata gösteriliyor.
- Evrak Takip'teki `UETS / KEP Ayarları` penceresi UETS ve PTT KEP sekmelerine ayrıldı. Yetkili kullanıcı PTT KEP sekmesinden kurumsal adres, yetkili TCKN, parola, web servis şifresi, WSDL ve ortam ayarlarını yönetebiliyor; bağlantı ve bakiye testlerini aynı pencerede yapabiliyor. Maskeli TCKN forma geri yazılmadığı için yanlışlıkla kaydedilmiyor, boş bırakılan gizli bilgiler mevcut değerlerini koruyor.
- KEP gönderi modalındaki alıcı doğrulaması jQuery çoklu Deferred birleştirmesi yerine açık JSON yanıtlı `Promise.all` akışına geçirildi. Tek veya çoklu adres sonuçları aynı biçimde işleniyor ve HTTP/API hata mesajı artık genel `Doğrulanamadı` metni altında kaybolmuyor. `ersan.2@testkep.pttkep.gov.tr` canlı PTT testinde kayıtlı ve iletiye uygun olarak doğrulandı.
- Rehber doğrulama API case'inin sonundaki eksik `break` nedeniyle başarılı KEP rehber JSON'una e-imza kart sorgusu yanıtı ekleniyordu. Switch fall-through giderildi; `kep-alici-dogrula` artık yalnız kendi JSON yanıtını döndürüyor.
- Bağımsız KEP modalında e-imza kartı ve PIN alanı ilk açılışta gizlendi. Form doğrulamaları geçtikten sonra ilk `İmzala ve Gönder` tıklaması imza adımını açıp kart taramasını POST isteğiyle başlatıyor; PIN girilerek ikinci adımda gönderim onaylanıyor.
- KEP alıcıları için firma bazlı adres defteri eklendi. PTT rehberinde başarıyla doğrulanan adres otomatik ve tekilleştirilerek kaydediliyor; KEP oluşturma modalındaki Select2 alanından sonraki gönderimlerde alıcılara eklenebiliyor. Başarılı gönderimde son kullanım zamanı güncelleniyor.
- E-imza/PIN alanı ana KEP formundan tamamen kaldırıldı. `İmzala ve Gönder` tıklaması kart durumunu, alıcı özetini ve PIN alanını içeren tek SweetAlert onay penceresi açıyor; PIN yalnız bu işlem için kullanılıyor. KEP yetkisine `eimza-kart-bilgisi` API politikası bağlandı.
- PIN onay penceresini durduran tanımsız global `escapeHtml` çağrısı, `evrak-takip.js` içindeki yerel `escapeEvrakHtml` yardımcısıyla giderildi; alıcı ve kart bilgileri HTML'e güvenli biçimde yazılıyor.
- Bootstrap KEP modalının focus trap mekanizmasının SweetAlert PIN alanına klavye odağını engellemesi giderildi. E-imza onay penceresi artık `modalKepGonderisi` içine hedefleniyor ve PIN alanı aynı modal odak kapsamı içinde çalışıyor.
- PTT KepId değerleri `<EYWS...>` biçiminde açılı ayraçla geldiği için SweetAlert kısa kullanımında HTML etiketi gibi yorumlanıp görünmüyordu. Başarı penceresi takip kodunu kaçırılmış `<code>` metni olarak gösteriyor; servis yanıtına `takip_kodu` uyumluluk alanı da eklendi.
- KEP oluşturma modalı kullanıcı dostu kart düzenine geçirildi: gönderen/bakiye şeridi, Alıcılar–İleti–Ekler–İmzala adım göstergesi, ayrı alıcı/ileti/editör kartları, kompakt editör, belirgin ek alanı ve gölgeli sabit aksiyon çubuğu eklendi. Adım göstergesi ek seçimi ve imza penceresiyle dinamik güncelleniyor; mobil yerleşim korunuyor.
- Kullanıcı geri bildirimiyle KEP modalı yeniden minimalleştirildi: stepper kaldırıldı, gönderen KEP ve canlı bakiye modal başlığına taşındı, alıcı girişi ile adres defteri aynı satıra alındı ve ileti tipi Select2 yerine Standart/Resmî segmentli radio kontrolü oldu. Ağır kart arka planları/gölgeleri sade bölüm ayraçlarına dönüştürüldü.

# 2026-10-20 — KEP yerel e-imza politika tamamlama

- Yerel e-imza akışında MIME hazırlama ve imzalı S/MIME sevk aksiyonlarının `permission_policies` kayıtlarının hiç oluşturulmadığı belirlendi.
- İki POST aksiyonu mevcut `evrak-takip/kep` yetkisine bağlayan tekrarlanabilir `sql/2026_10_20_add_kep_local_eimza_permission_policies.sql` migration'ı eklendi.

# 2026-10-20 — Taşınabilir OpenSC içeren e-imza ajanı

- Windows kullanıcısının ayrıca OpenSC kurmasına gerek bırakmamak için resmi OpenSC 0.27.1 Light x64 `pkcs11-tool.exe` ve `opensc.dll` dosyaları ajan JAR'ına gömüldü.
- Ajan dosyaları ilk kullanımda `%LOCALAPPDATA%\\ErsanEImza\\runtime\\opensc-0.27.1-x64` dizinine çıkarıyor, SHA-256 bütünlüğünü doğruluyor ve sistem PATH'i ya da yönetici yetkisi gerektirmeden kullanıyor.
- LGPL-2.1 lisans metni ve kaynak bildirimi pakete dahil edildi; ajan sürümü `1.1.0 (Portable OpenSC)` olarak güncellendi.
- Windows kart sürücüsünün aynı JVM içindeki SunPKCS11 imzasında ajanı tamamen düşürme riski giderildi. Windows imzası gömülü `pkcs11-tool.exe` alt sürecine izole edildi ve ajan sürümü `1.1.1` yapıldı.
- Kartta birden fazla sertifika/anahtar bulunduğunda oluşan `Karttan sertifika okunamadı` hatası giderildi. Ajan PIN ile nesneleri listeliyor, özel imza anahtarıyla aynı CKA_ID değerine sahip sertifikayı seçiyor ve her iki işlemde bu ID'yi kullanıyor; sürüm `1.1.2` oldu.
- PTT KEP'in `S/MIME formatı geçersiz veya bozuk` reddinin nedeni, CMS `CertificateSet [0] IMPLICIT` alanının primitive `0x80` yerine constructed `0xA0` olması gerekirken yanlış kodlanmasıydı. DER etiketi düzeltildi ve ajan `1.1.3` olarak paketlendi. Üretilen detached CAdES-BES örneği OpenSSL CMS doğrulamasından başarıyla geçti.
- `1.1.3` çıktısının PTT test ortamında yine reddedilmesi üzerine kullanıcı tarafından sağlanan `files/wsdl_doc.pdf` ile akış yeniden doğrulandı. SHA-256 CMS `AlgorithmIdentifier` parametresi RFC 5754/OpenSSL uyumlu olarak `NULL` yerine tamamen kaldırıldı. Karttan dönen gerçek RSA imzasının sertifikanın açık anahtarıyla ajan içinde doğrulanması eklendi ve sürüm `1.1.4` oldu.
- Linux kullanıcılarının terminal komutuna ihtiyaç duymaması için ajan penceresine ve sistem tepsisi menüsüne `Ajanı Yeniden Başlat` ile `Tamamen Kapat` eylemleri eklendi. Kapatma tepsi ikonunu açıkça kaldırıyor; JAR ikinci kez açılırsa yeni süreç hata vermek yerine çalışan ajanın penceresini öne getiriyor. Sürüm `1.1.5` oldu.
- Linux kart sürücüsünde sertifika ve özel anahtar CKA_ID değerlerinin farklı olabilmesi nedeniyle oluşan `özel imza anahtarıyla eşleşen sertifika bulunamadı` hatası giderildi. ID metin ayrıştırması genişletildi; doğrudan ID eşleşmesi yoksa karttaki özel anahtarlarla deneme imzası üretilip sertifikaların açık anahtarıyla doğrulanarak gerçek çift seçiliyor. Ajan sürümü `1.1.6` oldu.
- Kurum bilgisayarında `v1.2.0 PAdES` ajanının `Ajan yeniden başlatılıyor` durumunda kalması giderildi. Arayüz eylemi çalışan 9191 servisini kapatıp yeniden bind etmek yerine kart/bağlantı önbelleğini güvenle yeniliyor; HTTP sunucusu dört daemon worker ile eşzamanlı isteklere dayanıklı hale getirildi. Sürüm `1.2.1` oldu.
- `akia-bin` ile kurulan AKİS kartının `/opt/Akia/libakisp11.so` sürücüsü standart adaylarda bulunmadığı için algılanmaması giderildi. Akia ve ArkSigner Linux yolları eklendi; takılı ATR19 kartı gerçek sürücü üzerinden `MEHMET ALİ GÖKMEN` olarak doğrulandı. Kartın EC anahtarlı olması nedeniyle CKM_ECDSA, ham `r||s` → DER dönüşümü, `SHA256withECDSA` doğrulaması ve CMS ECDSA algoritma tanımı eklendi. Sürüm `1.2.2` oldu.
- AKİS'in `wsm.sessionDeactivated` hatasının güvenlik duvarı değil, arka arkaya PKCS#11 sağlayıcı oturumları açılmasından kaynaklandığı belirlendi. Kart sonucu 5 dakika, son başarılı ayrıntılı kimlik sonucu geçici WSM hataları için 30 dakika korunuyor; algılama sırasında ikinci SunPKCS11 oturumu açılmıyor ve tarayıcı sorgu önbelleği 60 saniyeye çıkarıldı. Sürüm `1.2.3` oldu.
- Windows'ta AKİS kartının görülmemesi için `akia_pkcs11.dll`, Program Files AKİA/E-Tuğra yolları ve Java işlem mimarisiyle çalışan gerçek X.509 sertifika kontrollü SunPKCS11 fallback eklendi. Böylece 32-bit Java/AKİS veya 64-bit AKİS kurulumları destekleniyor; yalnız DLL yüklenmesi kart var sayılmıyor. Tarayıcıdaki eski yeşil kart durumu kaldırılarak her UI kontrolü ajan önbelleğinden güncel sonucu alıyor. Sürüm `1.2.4` oldu.
- Windows kurum bilgisayarlarında standart dışı PKCS#11 kurulumu için ajana `Sürücü DLL Seç` arayüzü eklendi. Seçilen DLL kullanıcı tercihlerinde kalıcı saklanıyor ve sonraki açılışlarda ilk aday olarak deneniyor; AKİA/E-Tuğra ile System32/SysWOW64 yollarında sınırlı otomatik keşif de yapılıyor. Sürüm `1.2.5` oldu.
- Windows AKİS sürücüsünün sertifika nesnelerini PIN'siz listelememesi halinde kartın yok sayılması giderildi. Ajan artık seçili DLL üzerinde PKCS#11 slot/token varlığını doğrulayıp kartı hazır kabul ediyor; sertifika PIN aşamasında okunuyor. Arayüze Java mimarisi, seçilen DLL, çıkış kodu ve sürücü çıktısını gösteren `Tanılama` penceresi eklendi. Sürüm `1.2.6` oldu.
- Windows AKİS slot algılamasında sertifika sahibi/T.C. PIN öncesi gizli olduğunda modal artık boş `(TC: ) adına ait` göstermiyor; sertifikanın PIN ile okunacağını açıklıyor. İmzalama sırasında okunan gerçek X.509 kimliği ajan önbelleğine aktarılıyor ve sonraki durum yanıtlarında ad/T.C. bilgisi geliyor. Sürüm `1.2.7` oldu.
- KEP PIN modalına açık kullanıcı eylemli `Sertifikaları Getir` işlevi eklendi. PIN yalnız yerel ajana gönderiliyor; karttaki sertifikalar sahip, T.C., sağlayıcı, geçerlilik ve anahtar algoritması bilgileriyle listeleniyor. Yanlış/kilitli PIN hataları ayrıştırılıyor. Sürüm `1.2.8` oldu.
- Yerel ajanın sıkışık tek ekranlı Swing arayüzü yenilendi. Servis ve kart durumu `Durum` sekmesinde kartlar halinde gösteriliyor; sürücü DLL seçimi, bağlantı yenileme ve tanılama işlemleri `Ayarlar ve Tanılama` sekmesine taşındı. Ortak arka plana alma/tam kapatma eylemleri alt çubukta korundu ve sürüm `1.2.9` oldu.
- Bazı RSA e-imza kartlarının `CKM_SHA256_RSA_PKCS` birleşik mekanizmasını desteklemeyip `CKR_MECHANISM_INVALID` döndürmesi için otomatik uyumluluk yolu eklendi. Ajan bu durumda SHA-256 PKCS#1 DigestInfo yapısını kendisi hazırlayıp kartın `CKM_RSA_PKCS` mekanizmasına geçiyor; oluşan imzayı sertifikanın açık anahtarıyla doğrulamayı sürdürüyor. Anahtar–sertifika kriptografik eşleştirme denemesi de aynı fallback'i kullanıyor; sürüm `1.3.0` oldu.
- RSA uyumluluğu `1.3.1` sürümünde güçlendirildi: ajan kartın mekanizma listesini imzadan önce sorgulayıp birleşik SHA-256 mekanizması yoksa doğrudan `RSA-PKCS` yolunu seçiyor; ayrıca bazı sürücülerin beklenmeyen process exit koduyla döndürdüğü `0x70` hata metni de fallback tetikleyicisi oldu. Web arayüzü artık bağlı ajan sürümünü kart bilgisinde gösteriyor ve PAdES sürüm kontrolü `1.3.x` gibi daha yeni ajanları kabul ediyor.

# 2026-10-20 — Cari hesap hareketleri Excel dışa aktarımı

- Hesap Hareketleri sayfasındaki iki Excel aksiyonunun yalnız `window.open()` kullanan JavaScript olayına bağlı olması nedeniyle sunucuya hiç istek gitmediği tespit edildi.
- Dropdown ve tablo başlığı Excel aksiyonları doğrudan export endpoint'ine bağlı gerçek bağlantılara dönüştürüldü; JavaScript aktif filtre ve genel aramayı bağlantı adresine eklemeye devam ediyor.
- Excel verisi doğrudan view içinde sorgulanmak yerine parametrik PDO kullanan `CariHareketleriModel::getEkstreHareketleri()` üzerinden alınacak şekilde düzenlendi.

# 2026-10-20 — PTT KEP çoklu yetkili kullanıcı hesapları

- Firma başına tek satırda tutulan PTT KEP TCKN/parola/web servis şifresi ayrıştırıldı. Ortak KEP adresi, WSDL, ortam ve bakiye `kep_ayarlar` üzerinde; kullanıcı kimlikleri `kep_kullanici_hesaplari` üzerinde firma + uygulama kullanıcısı izolasyonuyla tutuluyor.
- Evrak Takip KEP ayar API'si yalnız oturumdaki kullanıcının maskeli TCKN ve kimlik durumu bilgisini döndürüyor. Başka kullanıcı aynı KEP adresini görse de gizli alanları devralmıyor ve ilk kayıtta üç kimlik alanını da girmek zorunda.
- Tüm KEP servis çağrıları mevcut oturum kullanıcısına ait şifreli kimlikleri kullanıyor. Eski tekil kimlik, geçişten sonra KEP ekranını ilk kullanan yetkili hesaba atomik olarak taşınıyor; sonraki kullanıcılar için paylaşılmıyor.
- `sql/2026_10_20_add_multi_user_kep_credentials.sql` aktif geliştirme veritabanına uygulandı. PHP/JavaScript sözdizimi, migration varlığı ve iki kullanıcı arasındaki kimlik izolasyonu rollback'li model testiyle doğrulandı.

# 2026-10-20 — Evrak listesinde işlem yapan personel

- Evrak Takip server-side listesine `İşlem Yapan Personel` kolonu eklendi. Gelen evrakta kaydı oluşturan, giden/KEP evrakında işlemi başlatan uygulama kullanıcısının adı gösteriliyor; ad yoksa kullanıcı adı kullanılıyor.
- Otomatik alınmış ve kullanıcı bağı bulunmayan UETS evrakları `Sistem / UETS` olarak etiketleniyor.
- Yeni kolon genel arama, canlı kolon filtresi ve sunucu taraflı sıralamaya dahil edildi; DataTables kolon indeksleri güncellendi. PHP/JavaScript sözdizimi ve gerçek veride 10 satırlık Model yanıtı doğrulandı.

# 2026-10-20 — Onaylanmış normal giden evrakın KEP ile gönderimi

- Normal gönderim kanalıyla hazırlanıp e-imza süreci tamamlanan giden evraklar, sonradan KEP üzerinden sevk edilebilir hale getirildi. Evrak listesindeki KEP yetkisine bağlı gönderim ikonu detay sayfasına yönlendirmeden mevcut KEP modalını açıyor; detay sayfasında da `KEP ile Gönder` aksiyonu bulunuyor.
- Liste modalında alıcı KEP adresleri alınıyor; konu/içerik kilitleniyor, yeni dosya ekleme kapatılıyor ve onaylı üst yazı PDF'i ile kayıtlı ekler değiştirilmeden mevcut yerel CAdES-BES imza ajanı akışına veriliyor.
- API'deki eski `gonderim_kanali = kep` zorunluluğu kaldırıldı; bunun yerine giden evrak, tamamlanmış onay, firma izolasyonu, KEP yetkisi ve daha önce gönderilmemiş olma kontrolleri uygulanıyor.
- KEP taslakları oturum kullanıcısına bağlandı ve 30 dakika ile sınırlandı. MIME hazırlama, sunucu fallback gönderimi ve tamamlamada mükerrer sevk kontrolü eklendi.
- PHP/JavaScript sözdizimi, onaysız evrak engeli ve önceden KEP gönderilmiş evrakın tekrar sevk engeli gerçek veride doğrulandı.
- Onaylı evrak modalından ilk gönderim denemesinde oluşan `strlen(): SoapVar given` hatasının, KEP boyut sınırı kontrolünde SOAP ek nesnelerine `strlen()` uygulanmasından kaynaklandığı doğrulandı. Hem yerel ajan MIME hazırlama hem sunucu fallback yolunda toplam artık PDF/EYP/ek dosyalarının ham byte içerikleri üzerinden hesaplanıyor; okunamayan dosyalar açıklayıcı hatayla durduruluyor.
- Onaylı giden evrakın yazı içeriğinin KEP ileti gövdesine tekrar basılması kaldırıldı. Onaylı PDF zaten ek olarak gönderildiği için hem yerel ajan hem sunucu fallback yolunda KEP gövdesi sunucu tarafında değiştirilemez biçimde `İlgili yazı ektedir.` standart metnine sabitlendi; yeni KEP kayıtlarında konu ve bu standart gövde ayrıca saklanıyor.

# 2026-10-20 — Giden evrak gerçek PAdES e-imza akışı

- Veritabanında onay veren sanal e-imza kaldırıldı. Sıradaki kullanıcı artık yerel ajan v1.2.0, akıllı kartı ve PIN'i ile PDF'i artımlı PAdES-BES olarak imzalamadan onay kaydı oluşturamıyor; evrakı hazırlayan ilk imzacının otomatik imzalanması da kaldırıldı.
- Sunucu değişmez PDF sürümünü hazırlıyor, her sonraki imzacı mevcut imzalı revizyonu imzalıyor. Gelen PDF'in önceki sürümle byte düzeyinde aynı başlangıca sahip olduğu, son `ByteRange` yapısı ve detached CMS imzası OpenSSL ile sunucuda doğrulanıyor.
- Sertifika öznesi, veren makam, seri numarası, SHA-256 parmak izi, geçerlilik tarihleri, PDF özeti ve doğrulama sonucu her onay satırında saklanıyor. İlk kullanılan sertifika kullanıcıya bağlanıyor; farklı sertifika yetkili değişiklik olmadan kabul edilmiyor.
- İmzalı PDF'ler uygulama dosya şifrelemesiyle hash bazlı revizyon dosyalarında tutuluyor. Önizleme ve KEP eki yeniden üretilmiş belge yerine gerçek PAdES PDF'i kullanıyor; eski sanal onaylı kayıtlara artık güvenli e-imza ibaresi basılmıyor.
- PDFBox 3.0.8 içeren Java 8 uyumlu ajan v1.2.0 üretildi. Şema ve API politika SQL'leri aktif geliştirme veritabanına uygulandı.
- Eski ajan sürümü algılandığında gösterilen uyarıya doğrudan `E-İmza v1.2.0 İndir` bağlantısı eklendi; kullanıcı KEP modalını açmadan güncel JAR'a ulaşabiliyor.
- İlk PAdES denemesinde sunucunun hazırlama ve tamamlama isteklerinde mPDF'i iki kez üretmesi nedeniyle görsel olarak aynı belgenin iç nesne/tarih byte'ları değişiyor ve kaynak eşleşmesi reddediliyordu. Hazırlanan PDF artık kullanıcı/firma/evrak bağlı, SHA-256 kontrollü ve 15 dakika geçerli şifreli bir imza oturumunda sabitleniyor; tamamlamada yeniden PDF üretmek yerine tam olarak bu kaynak doğrulanıyor.
- PDFBox çıktısında `ByteRange` dışındaki bölüm yalnız `<CMS hex>` değerini içerdiği, `/Contents` anahtarı ilk imzalı aralıkta kaldığı için sunucu CMS'i bulamıyordu. PAdES ayrıştırıcısı hem yalnız hex değerli PDFBox yerleşimini hem `/Contents <...>` yerleşimini destekleyecek şekilde düzeltildi.
- PAdES imzalama kopyasının PDF alt bilgisine kırmızı çerçeveli görünür e-imza doğrulama bandı eklendi. Bant güvenli elektronik imza ibaresi, deterministik belge doğrulama kodu, takip adresi ve elektronik imzacı adlarını içeriyor; ayrıca mevcut gri e-imza dipnotu korunuyor.
- Giden Evrak formuna `Normal Gönderim` ve `Kayıtlı Elektronik Posta (KEP)` seçenekli Evrak Gönderim Türü alanı geri eklendi. KEP seçildiğinde alıcı KEP adresi alanı açılıyor ve hem istemci hem sunucu tarafında zorunlu/geçerli e-posta biçiminde doğrulanıyor; KEP yetkisi ve oturum kullanıcısına ait KEP kimlikleri zorunlu kalıyor.
- PAdES zincirinin son imzası tamamlandığında API kayıtlı KEP alıcısını tarayıcıya bildiriyor. Tarayıcı aynı PIN oturumu içinde PTT MIME paketini hazırlatıyor, yerel karttan CAdES imzasını alıyor ve `SmimeGonder` ile otomatik sevk ediyor. KEP sevki hata verirse tamamlanmış PAdES imzası geri alınmıyor; evrak imzalı kalıyor ve manuel tekrar sevke uygun açıklayıcı uyarı gösteriliyor.
- Firma 1'de eski `evrak_eimza_goster=0` ayarının gerçek PAdES imzalama kopyasındaki görünür doğrulama bandını da kapattığı tespit edildi. PAdES imzalama kopyasında görünür imza alanı zorunlu hale getirildi; eski ayar yalnız eski/sanal PDF gösterimi için dikkate alınıyor.
- Giden evrak `E-İmza ile Onaya Sun` işlemi başarı veya kayıt-sonrası onaya sunma hatası sonucunda detay sayfasında kalmak yerine Evrak Takip listesine dönüyor.
- Evrak listesindeki ayrı ikon butonları tek `İşlemler` dropdown menüsünde toplandı. Tüm eylemlere açık `title` ve görünür metin verildiği için global sağ tık menüsündeki `İşlem 2` gibi belirsiz fallback etiketleri kaldırıldı. `PAdES ile İmzala` liste/sağ-tık eylemi evrak detayını açıp gerçek kart imza penceresini otomatik başlatıyor; eski sanal onay endpoint'ine gitmiyor.
- Evrak listesindeki e-imza durum rozeti artık PDF önizleme modalını açıyor. Modalın sağ panelinde sıralı imzacılar, bekleyen/mevcut imza aşaması, PAdES sertifika doğrulama bilgisi ve oluşturma–onaya sunma–imza–tamamlanma/iade tarihçesi gösteriliyor.
- Giden evrakın sistem tarafından üretilen üst yazısı fiziksel ek kaydından bağımsız olarak Dosya sütununa `Üst Yazı.pdf` adıyla eklendi; böylece onaylı evraklarda ek bulunmasa bile dosya hücresi boş görünmüyor ve PDF doğrudan önizlenebiliyor.
- PAdES görünür bilgileri kurumsal evrak alt bilgisini yeniden tasarlamayacak şekilde ayrıştırıldı: mevcut adres ve `Bilgi için` bloğu aynen korunuyor; yalnız üstüne ince doğrulama kutusu, altına gri güvenli e-imza notu ekleniyor. Üretilen A4 örneği görsel ve metinsel olarak doğrulandı.
- KEP satırlarında e-imza durum rozetine tıklamanın satır geneli KEP önizleme olayını da tetiklemesi engellendi. E-imza rozeti yalnız evrak PDF + imza tarihçesi modalını, KEP önizleme aksiyonu ise yalnız KEP detay modalını açıyor.
- KEP kanallı giden evrakların ayrı `GİDEN` ve `KEP` rozetleri tek mor `GİDEN / KEP` rozeti olarak birleştirildi. Normal giden ve gelen KEP gösterimleri değiştirilmedi.
- KEP ileti oluşturma modalı, numaralı bölüm kartları ve daha belirgin alıcı/ek alanlarıyla görsel olarak iyileştirildi. Yazılan alıcılar için varsayılan açık `Doğrulanan KEP adreslerini adres defterine kaydet` seçeneği eklendi; PTT doğrulamasından geçen adresler kontrollü olarak firma adres defterine kaydediliyor ve seçim listesi anında yenileniyor.
- KEP modalının alt işlem alanındaki ayrı indirme ve yardım düğmeleri kaldırılarak e-imza cihaz durumu tek, kompakt bir durum kartında toplandı. Evrak Takip `İşlemler` menüsündeki AI taslak ve icra üst yazısı kısayolları kaldırıldı; menünün en altına açıklamalı `E-İmza Uygulamasını İndir` bağlantısı eklendi.
- Giden Evrak normal gönderim kayıtlarına ayrı `Islak İmza ile Onaya Sun` akışı eklendi. Akış türü evrak üzerinde saklanıyor; seçili yetkililer sırayla fiziksel imzayı teyit ediyor, son onayda evrak kilitleniyor. KEP gönderiminde ıslak imza hem arayüzde gizli hem sunucuda engelli; ıslak imza kaydına PAdES uygulanması da sunucu tarafında reddediliyor.
- Kullanıcı düzenleme modalının Yetkilendirme & Organizasyon bölümüne `Evrak İmza Yetkisi` alanı eklendi. Yalnız aktif, mevcut firmaya bağlı ve bu ayarı açık kullanıcılar Giden Evrak imza yetkilileri listesine alınıyor; gönderilen imzacı ID'leri onaya sunma sırasında Model katmanında aynı koşullarla yeniden doğrulanıyor.
- Normal gönderim türüne `Mobil İmza ile Onaya Sun` seçeneği eklendi ve `onay_turu=mobil_imza` olarak ayrı saklanıyor. Evrak kaydedilip kilitleniyor, sıralı yetkili/bildirim akışı oluşturuluyor; gerçek mobil operatör imzalama entegrasyonu tamamlanana kadar onay aksiyonu işlem yapmadan bilgilendirme gösteriyor. Mobil imza KEP'te kullanılamıyor ve PAdES modalına düşmüyor.
- Giden Evrak formunun altındaki mükerrer `KEP / Gönderim Bilgileri` akordeonu kaldırıldı. Gönderim türü ve zorunlu alıcı KEP adresi üstteki evrak bilgileri alanında kalmaya devam ediyor.
- Evrak/imza önizleme modalında imza sırası oturum kullanıcısındaysa `E-İmza ile İmzala` butonu gösteriliyor. Buton gerçek yerel ajan + akıllı kart + PIN ile PAdES akışını çalıştırıyor; son imzada kayıtlı KEP otomatik sevk zinciri de korunuyor.
- İmza modalının işlem alanı sağ alt köşede sabitlendi; sıra kullanıcıdaysa `İade Et` ve `E-İmza ile İmzala` butonları birlikte gösteriliyor. Liste İşlemler menüsündeki `PAdES ile İmzala` etiketi de `E-İmza ile İmzala` olarak sadeleştirildi.
- E-imza PIN girişi ayrı uyarı penceresinden kaldırılarak imza modalının sağ alt işlem alanına alındı. İmzalama başlayınca PIN ve işlem butonları kilitleniyor; ajan kontrolü, PDF hazırlama, PAdES imzalama, sunucu doğrulaması ve varsa KEP sevki yüzde ilerleme çubuğu ile aynı panelde gösteriliyor.
- PDF alt bilgisindeki e-imza doğrulama alanının dört taraflı kırmızı çerçevesi kaldırıldı; yalnız alt kenarda 0,6 mm kalın kırmızı ayraç çizgisi bırakıldı.
- E-imza alt bilgi satırı referans düzene uyarlandı: solda `Belge Doğrulama Kodu` ile deterministik 5 haneli `PIN Kodu`, sağda `Belge Takip Adresi` gösteriliyor. `Elektronik İmzalayan` alanı kaldırıldı.
- E-imza PDF alt bilgisi özgün görsele daha yakın olacak şekilde sıkılaştırıldı: doğrulama paneli ile kurumsal alt bilgi arasındaki mükerrer kırmızı çizgi kaldırıldı, tek 0,6 mm ayraç kurumsal bloğun üstünde bırakıldı; başlık, meta, iletişim ve gri dipnot tipografi/boşlukları küçültüldü.
- Evrak Takip listesindeki giden KEP kayıtlarının PTT delillerindeki son durumu, kalabalığı azaltmak için `Tip` hücresinden ayrılarak hemen yanındaki bağımsız `KEP Durumu` kolonuna taşındı (`Kabul Edildi`, `İletildi`, `Teslim Edildi`, `Okundu`, `İndirildi` veya hata). KEP olmayan kayıtlarda `-` gösteriliyor. Deliller sayfadaki evraklar için firma kapsamlı tek toplu Model sorgusuyla okunuyor; modalda PTT delilleri yenilendiğinde liste aynı sayfada otomatik tazeleniyor.
- Linux çalışma ortamında `tests/integration` ile Git'te izlenen `tests/Integration` arasındaki büyük/küçük harf çakışması giderildi; test dosyaları standart `tests/Integration` yolunda toplandı ve depo için `core.ignorecase=false` ayarlandı.
# Ayarlar — Avans Ayarları Kaydetme Yetki Hatası (2026-10-21)
- Avans Ayarları sekmesi görünmesine rağmen kaydetme isteğinin merkezi API politika katmanında 403 ile reddedildiği tespit edildi.
- Canlı veritabanında `avans_ayarlari_sekmesi` yetkisinin ve `ayarlar/api / save_avans_ayarlari` politikasının eksik olduğu doğrulandı.
- Tekrarlanabilir düzeltme scripti `sql/2026_10_21_fix_avans_ayarlari_permission.sql` oluşturuldu; yetki, API politikası ve yalnızca Süper Admin/Firma Sahibi kapsamındaki rol atamaları tanımlandı.
# 2026-10-21 — Mobil SPA yetkisiz sayfa erişimi denetimi

- Mobil SPA rota koruması, oturum yetki haritası, alt menü/“Diğer” görünürlüğü ve `public/api` Gate kontrolleri salt okunur olarak incelendi.
- Doğrudan URL ile yetkisiz modül sayfası açılmasını sağlayan doğrulanmış bir yol bulunmadı; router kullanıcıyı ana sayfaya döndürüyor ve modül API'leri ayrıca 403 üretiyor.
- Takip edilmesi gereken tutarsızlıklar belirlendi: yetki haritası oturum boyunca yenileniyor ve bazı modüllerde görüntüleme yetkisi yazma aksiyonlarını da kapsıyor.
- `burakkafadar` için görünen Sistem Logları menüsünün kök nedeni doğrulandı: kullanıcının aktif 12 ve 13 numaralı rollerinde `log_kayitlari` yetkisi yok; `auth.php` eski `views/logs/list.php` bağlantısını sorguluyor ve `MenuModel::userCanAccessMenuLink()` tanımsız menü + tanımsız yetki durumunda fail-open biçimde `true` döndürüyor. Böylece istemci `logs=true` alıp menüyü çiziyor; `logs.php` API Gate kontrolü ise erişimi 403 ile reddediyor.
- Tanımsız menü/yetki sorguları fail-closed yapıldı. Mobil oturum haritası, dashboard kartları, rotalar ve ilgili API giriş kontrolleri masaüstündeki aktif `permission_key` değerleriyle eşitlendi; sabit rapor yetki ID kontrolü kaldırıldı. Araç Takip ile KM Onayları ayrı masaüstü yetkilerine bağlandı.
- Mobil SPA alt navigasyonundaki `Diğer` öğesi, kullanıcının görünür menü sayısından bağımsız olarak daima en sağda olacak şekilde son sıraya taşındı.
- Mobil `Personel Puantaj` sayfasının yanlış `puantaj/raporlar` eşlemesi ayrıştırıldı; menü, hızlı işlem, rota ve API artık masaüstündeki `personel/puantaj` yetkisini birlikte kullanıyor.
- Mobil SPA genel yetki denetimi tamamlandı. Giden/Gelen Evrak, Nöbet Onay, Personel Ekle-Düzenle ve personel alt bölümleri ayrı masaüstü yetkilerine bağlandı; her rota geçişinde sunucu yetki haritası tazeleniyor.
- E-Fatura birleşik mobil ekranındaki Özet, Giden, Gelen, Taslak ve Oluştur aksiyonları ayrı yetkilere ayrıldı. Yetkisiz sekmeler çizilmiyor; fatura detay/önizleme/PDF API'leri kaydın yönü ve taslak durumuna göre aynı Gate yetkisini doğruluyor.
- Personel liste yetkisiyle detay API'sinden finansal, izin, evrak, icra, zimmet ve puantaj kırılımlarının dönmesi engellendi. Alt bölümler `personel_duzenle`, evrak bölümü ayrıca `evrak_bilgileri_sekmesi` gerektiriyor.
- Kullanıcının kaydettiği dahili hızlı erişim bağlantıları da hedef sayfanın sunucu kaynaklı yetkisi yoksa ana ekranda çizilmiyor.
- Mobil Puantaj/Raporlar ekranındaki dağılım detayının 500 vermesine, `reports.php` içindeki ortak PDO bağlantısının yetki düzenlemesi sırasında kaldırılmış olması neden oluyordu. Ortak bağlantı Gate kontrolünün ardından yeniden oluşturuldu; bildirilen `distribution/okuma` isteği `burakkafadar` oturumuyla başarılı JSON yanıtı verecek şekilde doğrulandı.
# 2026-10-21 — Bağımsız Pazar HTÇ telafi çalıştırıcısı

- Pazar operasyonlarından otomatik HTÇ uzlaştırması, ana endeks cronunun saat/son çalışma kilidinden ayrılarak `cron/okuma_htc_telafi.php` CLI dosyasına taşınabilir bağımsız bir çalışma yolu kazandı.
- Çalıştırıcı açık web erişimini reddeder; `--date=YYYY-MM-DD` ve isteğe bağlı `--firma-id=ID` kabul eder. Tarih verilmezse son tamamlanmış Pazar gününü kullanır ve Pazartesi 08:10 için ayrı cron örneği içerir.
- Çalışmadan önce `2026_10_18_add_okuma_htc_automation.sql` şemasını Model üzerinden doğrular; firma bazlı otomasyon ve eşik ayarlarını kullanır. Merkezi idempotent `OkumaHtcAutomationService` değişmeden kullanıldığı için tekrar çalıştırma çift HTÇ üretmez, manuel kilitleri korur.

# 2026-10-21 — Puantaj Pazar HTÇ kontrol modalı

- Personel Puantaj sayfasındaki İşlemler menüsüne `Pazar HTÇ Kontrolü` eklendi. Seçili ayın Pazarları modalda seçilir; Endeks, Kaçak, Sökme-Takma ve Mühürleme sayıları personel bazında birlikte gösterilir.
- Firma eşiklerini geçen, HTÇ'si bulunmayan ve manuel puantajla çakışmayan personeller varsayılan seçilir. Eşik altındaki Pazar çalışanları kullanıcı tarafından ayrıca seçilebilir; manuel kilitli, başka kodlu veya zaten HTÇ bulunan satırlar korunur.
- Seçilen kişiler merkezi HTÇ servisi üzerinden transaction içinde, idempotent biçimde atanır. API hem `personel/puantaj` Gate yetkisini hem firma kapsamını doğrular ve işlem sistem loguna yazılır.
- Canlı şemada `personel_izinleri.onaylayan_id` bulunmadığı için otomatik HTÇ INSERT sorgusundaki hatalı alan kaldırıldı; onay durumu mevcut `onay_durumu` alanında korunuyor. Sorgu gerçek PDO transaction içinde insert + rollback ile doğrulandı.
- HTÇ modalındaki seçim kutuları 22px özel durumlu modern checkbox tasarımına geçirildi; toplu seçim için checked/indeterminate durumları senkronize edildi. Modal viewport yüksekliğine sınırlandı, tablo gövdesi dokunmatik destekli bağımsız scroll ve sabit kolon başlığı kazandı.
