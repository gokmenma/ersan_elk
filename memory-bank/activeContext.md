# Active Context

## Şu An Ne Üzerinde Çalışılıyor?
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

## Sıradaki Adımlar & Odak Noktaları
- Kullanıcıdan Ana Sayfa'nın yeni Clay/Bento tasarımı hakkında geri bildirim alınması.
- İhtiyaç duyulursa aynı tasarım dilinin diğer ekranlara (Talepler, Personel, Raporlar) yaygınlaştırılması.




## 2026-10-14 - Muhasebe ve Bordro Kontrol Excel'i
- `bordro/list` muhasebe aktarımı tek `Mutabakat` sayfasına indirildi. TC sonrasına Fiilî Gün ve Rapor Günü eklendi; yalnızca muhasebenin gireceği resmî kalemler, uygulayacağı kesintiler ve banka tutarı bırakıldı.
- Seçili personeller için ortak bordro hesabını kullanan, kişi bazında yazdırılabilir bloklar üreten `Bordro Kontrol (Excel)` aksiyonu eklendi.
- Sunucudaki PhpSpreadsheet sürümüyle uyumsuz `SheetView::setShowGridLines()` çağrısı, desteklenen `Worksheet::setShowGridlines(false)` çağrısıyla düzeltildi.
- Bordro Kontrol Excel'indeki yüzlerce puantaj/sayaç hareketi tek `Puantaj Hakedişi`, kaçak kontrol hareketleri tek `Kaçak Kontrol Hakedişi` satırında toplandı; teknik yuvarlama detayı tekrarı kaldırıldı.
- Bordro Kontrol sınıflandırması detay modalıyla eşlendi: yemek/eş yardımı Sözleşme Alacağında tekrar edilmiyor, Yuvarlama Farkı ortak hesap değerinden sözleşme tarafında tek satır gösteriliyor.
