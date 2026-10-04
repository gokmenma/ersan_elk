# Proje Kuralları ve Standartları (AGENTS.md)

## 1. Veritabanı ve Model Standartları
- PDO kullanılacak ve tüm veritabanı sorgularında prepare()/execute() ile parametrik binding uygulanacak.
- Tüm veritabanı işlemleri Model katmanından geçecek.
- Veritabanı değişiklikleri her zaman ayrı SQL scripti olarak verilecek.
- Silme işlemleri soft delete (`deleted_at = NOW()`, `is_active = 0`) mantığı ile yürütülecek.

## 2. Yetkilendirme ve Güvenlik
- Yetki kontrolü olmayan işlem yapılmayacak.
- Yalnızca Superadmin yetkisindeki sayfalar ve API aksiyonları `Gate::isSuperAdmin()` ile korunacak.
- Hassas nesne ve kayıt ID'leri `Security::encrypt()` ve `Security::decrypt()` ile şifrelenecek.
- HTML çıktısında değişkenler `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` ile sarılacak.

## 3. DataTables Varsayılan Tablo Başlatma Standartları
- **JavaScript Başlatma**: Tüm DataTables tabloları `applyLengthStateSave({ ...getDatatableOptions(), ... })` mantığı ile başlatılacaktır.
- **Canlı Kolon Filtreleme (Header Filters)**:
  - Tablonun `<thead>` kısmındaki `<th>` etiketlerine:
    - Metin aramaları için `data-filter="string"`
    - Seçim filtreleri için `data-filter="select"`
    - Tarih filtreleri için `data-filter="date"`
    nitelikleri eklenecektir.
  - Bu nitelikler eklendiğinde `datatables.init.js` ve `datatable-filters.js` otomatik olarak filtreleme kutularını ve simgelerini tablo başlığına yerleştirir.
- **Sayfa ve Tablo Yerleşimi**:
  - Sayfa başlığı card header içerisinde **yer almayacak**; `layouts/breadcrumb.php` standart yapısı üzerinden sunulacaktır.
  - Kartın üst kısmında durum filtreleri (`status-filter-group`) ve sağ tarafta aksiyon butonu (`personel-action-toolbar`) kullanılacaktır.
- **Merkezi Sıralama İkonları (Sorting Icons)**:
  - DataTables tablolarında sıralama ikonları (`↑↓` oklar) merkezi `assets/css/datatable-filters.css` üzerinden vektörel SVG olarak sunulur ve **varsayılan olarak her zaman belirgin şekilde görünür**.
  - Sıralama ikonu sütun başlığının **en başında (solunda - `left: 10px`)** konumlanır; başlık metni `padding-left: 28px` ile hafifçe sağa kayarak ikonun sağında temiz ve düzenli bir hizada yer alır.
  - Sütun başlığında filtre butonu (`.dt-filter-mode-trigger`) bulunması durumunda filtre butonu sağ kenarda (`right: 8px`), sıralama ikonu ise sol kenarda kalarak başlığın iki ucunda dengeli bir yerleşim oluşturur.
  - Sıralama devre dışı olan sütunlarda (`.sorting_disabled`, işlem butonları, vb.) sıralama ikonu otomatik olarak gizlenir.
- **Özet Kartlarını Açma/Kapama Standardı**:
  - Özet kart bulunan masaüstü sayfalarda, aksiyon araç çubuğunun en sağında kartları gizleyip gösteren yukarı/aşağı ok butonu bulunacaktır.
  - Kart grubu ani `display` değişimiyle değil, yükseklik ve opaklık geçişi (`transition`) ile açılıp kapanacaktır.
  - Kullanıcının açık/kapalı tercihi sayfaya özgü bir `localStorage` anahtarıyla saklanacaktır.
  - Kayıtlı durum, kart HTML'i çizilmeden önce okunup kök elemana durum sınıfı eklenerek uygulanacaktır; ilk yüklemede görünürlük sıçraması oluşturulmayacaktır.
  - Butonun ikonu, `title`, erişilebilir etiketi ve `aria-expanded` değeri görünürlük durumuyla birlikte güncellenecektir.

## 4. Form ve Select2 Standartları
- Masaüstü modüllerde select alanları doğrudan HTML `<select>` etiketi yazılarak oluşturulmayacak; `App\Helper\Form::FormSelect2()` kullanılacaktır.
- Select2 alanının sınıfında `select2` bulunacak ve JavaScript tarafında `.select2()` ile başlatılacaktır.
- Modal içerisindeki Select2 alanlarında açılır listenin modal sınırları ve katman sırası içinde kalması için `dropdownParent` ilgili modal olarak tanımlanacaktır.

## 5. Bordro — Maaşa Dahil Yemek Yardımı (Değiştirilemez İş Kuralı)
- Bu kural `BordroPersonelModel` içindeki hesaplama, liste, bordro detay modalı, Excel ve ödeme dağıtımı yollarının **tamamında aynı şekilde** uygulanacaktır. Bir yol diğerinden farklı hesap yapamaz.
- Maaşa dahil personelde yemek yardımı, sözleşme netini aşan ayrı bir kazanç değildir; asgari net ve banka üzerinden ödenecek RTÇ/HTÇ gibi kalemlerden sonra sözleşme netini tamamlayan bakiyedir.
- Hesap sırası zorunludur: (1) sözleşme net tavanı belirlenir, (2) asgari net, eş yardımı ve banka üzerinden ödenecek RTÇ/HTÇ kalemleri düşülür, (3) kalan tutar yemek yardımı üst limiti olarak alınır, (4) bu tutar fiilî güne bölünür, (5) günlük tutar yukarı yuvarlanır ve günlük limit ile sınırlandırılır, (6) yuvarlanmış günlük tutar fiilî gün ile çarpılır.
- Günlük yuvarlama sonucu oluşan fark ayrı `yuvarlama_farki` olarak kaydedilir ve gösterilir; günlük hesap doğrudan toplam tutara çevrilerek bu kural atlanamaz.
- Yemek yardımı yüksek hesaplanıp banka ödemesinden sonradan “fark kesintisi” düşülemez. Banka limit matrahı, sözleşme neti ve yalnızca kayıtlı yuvarlama farkı dışında aşamaz.
- Net ücretli ve puantaj/ek ödeme üreten personelde kazanç sözleşme netinin **üzerine ayrıca eklenir**. Maaşa dahil yemek yardımı alan personelde kazançlar **öncelikle personelin günlük yemek tavanını (günlük yemek limiti tavanına kadar) doldurur** ve yemek yardımına dahil edilerek bankadan ödenir. Günlük yemek limitini aşan bakiye tutar ise **elden ödeme** olarak dağıtılır. Kesinti, önce resmî banka tavanından mahsup edilir; banka tavanını aşan kesinti varsa ancak bu bakiye elden tutardan düşülür.
- Bu kuralları değiştiren her çalışma, önce `docs/BORDRO_HESAPLAMA_KURALLARI.md` dosyasını güncellemek ve liste–detay–kayıt hesaplarının aynı çıktıyı verdiğini doğrulamak zorundadır.

## 6. Bordro — Gün Bazlı Sürekli Ek Ödemeler (Tarih Aralığı Kuralı)
- Sürekli ek ödemelerden (araç kirası vb.) dönem kaydı üretilirken `baslangic_donemi` ve `bitis_donemi` alanları korunacaktır.
- Gün bazlı ek ödemelerde (`aylik_fiili_gun_net`, `aylik_gun_net`, vb.) personelin ay içindeki fiili veya çalışılan gün sayısı doğrudan tüm döneme çarpılmaz; ek ödemenin geçerlilik aralığı ile bordro döneminin kesişim aralığındaki (`max(donemBas, odemeBas)` ve `min(donemBit, odemeBit)`) fiili gün sayısı esas alınır.

## 7. Sistem ve Git İletişimi (SSH Port 443)
- Geliştirme ortamındaki ağ kısıtlamaları nedeniyle GitHub SSH (Port 22) zaman aşımına uğrayabilmektedir. Git SSH işlemleri `~/.ssh/config` üzerinden `Host github.com -> Hostname ssh.github.com, Port 443` üzerinden yürütülmelidir.

## 8. Yeni Sayfa Tasarım ve DataTables Standartları (Cari / Sözleşme Standardı)
Kullanıcı "yeni sayfa standardı", "varsayılan datatable", "cari sayfasına benzer" veya yeni bir liste/yönetim sayfası talep ettiğinde aşağıdaki mimari ve görsel standartlar **birebir uygulanacaktır**:

### 8.1. Sayfa Başlığı ve Üst Araç Çubuğu
- Sayfa başlığı card header içinde yer almayacak; sayfa başında `<?php include 'layouts/breadcrumb.php'; ?>` ile sunulacaktır.
- Sayfa içeriği `<div class="container-fluid">` ile başlayacaktır.
- **Üst Başlık Satırı (`row align-items-center mb-3`)**:
  - **Sol Taraf**: 44x44px `p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle` kutusu içinde modül ikonu, yanında `h4.font-size-16.fw-bold.text-dark` başlık ve `p.font-size-12.text-muted` alt açıklama.
  - **Sağ Taraf (`.personel-action-toolbar`)**:
    - Ana Ekleme Butonu: `btn btn-primary top-action-btn shadow-sm text-white` (örn: `+ Yeni Sözleşme` / `+ Yeni Cari Ekle`).
    - Varsa İşlemler Dropdown: `btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm`.
    - Özet Kartları Aç/Kapa Butonu: `btn btn-outline-secondary bg-white top-icon-btn shadow-sm` (`#btnToggleSummaryCards`, `<i class="bx bx-chevron-up"></i>`).

### 8.2. 4 Adet Minimal Özet KPI Kartı (`#summaryCardsContainer`)
- Sayfa yüklenmeden önce anlık sıçramasız durum okuma:
  `<script>try { document.documentElement.classList.toggle('{module}-summary-hidden', localStorage.getItem('{module}_summary_cards_state') === 'hidden'); } catch (e) {}</script>`
- CSS Animasyonu:
  `#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }`
  `.{module}-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }`
- 4 Kart Yapısı (`col-12 col-sm-6 col-xl-3`):
  1. **Toplam Kayıt**: İkon `bg-primary-subtle`, değer `summary-kpi-value`, alt metin durum dağılımı, hızlı filtre butonu `summary-pill-btn status-quick-filter active` (`data-status="all"`).
  2. **Birinci Durum/Aktif**: İkon `bg-success-subtle`, değer `text-success`, alt metin, filtre butonu `btn-subtle-success`.
  3. **İkinci Durum/Tamamlanan**: İkon `bg-info-subtle` / `bg-danger-subtle`, değer, filtre butonu.
  4. **Genel Toplam / Hacim / Bakiye**: İkon `bg-warning-subtle`, değer, durum rozeti / badge.

### 8.3. DataTables Liste Kartı (`#{module}ListCard`)
- **Kart Başlığı**:
  - Sol Taraf: 38x38px `bg-primary-subtle` ikon kutusu (`bx bx-list-ul`), `h5.font-size-14.fw-bold` başlık, alt açıklama.
  - Sağ Taraf: Varsa dışa aktarma (Excel) veya **Yazdır** (`#btnHeaderPrint`) butonları (Yenile butonu varsayılan olarak yer almayacak; yalnızca kullanıcı açıkça talep ettiğinde eklenecektir).
- **Tablo Yapısı**:
  - Sınıflar: `<table id="{module}Table" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">`
  - Sütun Başlıkları (`<thead>`): Canlı arama ve filtreleme için uygun `data-filter` nitelikleri (`string`, `date`, `number`, `select`, `none`).
- **Satır Tıklama**: İşlem sütunu hariç herhangi bir hücreye tıklandığında detay sayfasına yönlendirme (`cursor: pointer !important`).
- **İşlem Sütunu**:
  - Genişlik: `120px` - `130px`, `text-center`, `orderable: false`, `searchable: false`.
  - Buton Grubu: `.action-btn-group` içinde 27x27px `.table-action-btn` subtle butonları (`btn-subtle-primary` Detay, `btn-subtle-warning` Düzenle, `btn-subtle-danger` Sil).
- **Durum Rozetleri**: `badge bg-*-subtle text-* border border-*-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold`.

### 8.4. JavaScript ve Model Standartları
- **JavaScript Başlatma**: `applyLengthStateSave({ ...getDatatableOptions(), processing: true, serverSide: true, ajax: { ... } })`.
- **Model Katmanı**: Her modül için `ajaxList(array $params, int $firma_id): array` ve `summary(int $firma_id): object` metotları yazılacak; pagination, sorting, search, column filters PDO parametrik bağlama ile Model içinde yürütülecektir.

### 8.5. Global DataTables Sağ Tık Menüsü (Context Menu) Standardı
Tüm DataTables tablolarında satıra sağ tıklandığında modern floating context menu (`.custom-context-menu`) açılır:
- **Tetiklenme**: Tablo satırına (`table.dataTable tbody tr`) sağ tıklandığında varsayılan tarayıcı menüsü engellenir ve satır `.context-menu-active` sınıfı ile vurgulanır.
- **Menü Başlığı**: Menünün en üstünde satırın birincil numarası, adı veya ID'si modül ikonuyla birlikte yer alır (`.cm-header`).
- **Aksiyonların Otomatik Çıkarılması**: İlgili satırın son sütunundaki (`td:last-child`) tüm aksiyon butonları, linkler ve dropdown öğeleri otomatik taranarak context menu elemanlarına dönüştürülür.
- **Tehlikeli İşlemler**: Silme (`Sil`, `Delete`, `cm-danger`) işlemleri otomatik olarak menünün en altına bir ayırıcı çizgi (`.cm-divider`) ile eklenir ve kırmızı renkte vurgulanır.
- **Etkileşim ve Kapanma**: Menü dışına tıklama, fare tekerleğiyle kaydırma (`scroll`), menü elemanına tıklama veya `Escape` tuşuna basıldığında menü kapanır ve satır vurgusu temizlenir.
- **Ekran Sınırı Koruması**: Menü açılırken ekranın sağ ve alt kenarlarından taşmayacak şekilde koordinat düzeltmesi yapılır.
- **Tema Desteği**: Hem açık (Light) hem de koyu (Dark) temada sorunsuz çalışır.


