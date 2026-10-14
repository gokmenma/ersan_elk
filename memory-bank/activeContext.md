# Active Context

## Mobil SPA — Bordro Modülü Entegrasyonu (2026-10-14)
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
    - Bordro Detay Modalı (Sheet) Şıklaştırması:
      - Modal içerisindeki obsidian hero kartında ele geçen net tutarın (`₺53.405,00`) sağdan taşması `.payroll-hero-amount` sınıfı uygulanarak giderildi (`clamp(22px, 6.5vw, 28px)`).
      - "Resmî Hesaplama Kalemleri" akordeonunda masaüstünden gelen kaba, mükerrer personel bilgi kutusu (`.person-header-card`) ve alt özet rozeti DOM parser ile temizlendi.
      - `.payroll-legacy-container` ve `.bordro-ref-view` için özel Soft Bento stilleri yazıldı: Kazançlar ve kesintiler bölümleri pastel zeminli (`#f0fdf4` yeşil / `#fff1f2` kırmızı), 14px kavisli beyaz kartlar, ince kesikli ayırıcılar, net hizalı sayılar ve mobil ekran genişliğine tam uyumlu kart ızgarasıyla baştan aşağı modernize edildi.
  - Smoke Test & Derleme: `design-smoke.cjs` içine `/bordro` rotası, detay modalı açma adımı ve mock API yanıtı dahil edildi; Playwright ile 320/390/480px ekran boyutlarında 27 kontrol başarıyla (`PASS`) tamamlandı. `npm run build` ile hatasız üretim çıktısı alındı.

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
