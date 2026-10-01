# Bordro liste açılışı performansı

1 Ekim 2026 tarihinde mevcut HTML tablo korunarak liste hesap kaynakları toplu yüklemeye geçirildi. Bordro hesaplama kuralları değiştirilmedi.

## Değişiklikler

- SGK firma dağılımı için listelenen personellerin **tüm** çalışma geçmişi tek sorguda yüklenir. Tekil sorgudaki `ise_giris_tarihi ASC` sırası ve tarih kesişimi korunur; dönemle sınırlı geçmiş kullanılmaz.
- Devir matrahları ve önceki bordrolar iki toplu sorguyla alınır. Ay başına ilk bordro seçimi, JSON matrahı ve brüt–SGK–işsizlik yedek hesabı aynı ortak toplama metodunu kullanır.
- Her yeni liste okuması kaynak önbelleklerini temizler; firma/dönem değişimi eski kaynakları taşımaz. Aynı dönemi yeniden okumak da güncel kayıtları getirir. Maaş yazma akışı bu yeni liste önbelleklerini kullanmaz.
- Aynı girdili brüte tamamlama sonuçları istek boyunca paylaşılır. Parametre yazma yollarının mevcut `clearRequestCache()` çağrısı bu önbelleği de temizler.
- Bordro ve AI denetim JavaScript dosyaları `filemtime()` ile sürümlenir; her açılışta farklı URL üretilmez.
- Bordro tablosu, satır HTML'inin hemen arkasında gerekli çekirdek dosyalarla başlatılır. Sayfanın sonundaki eklentiler, CDN dosyaları ve `document.ready` beklenmez. `require_once` çekirdeğin ikinci kez yüklenmesini önler. Gelişmiş kolon filtreleri ilk görünür çizimden sonra hazırlanır.
- Pace üst yükleme çubuğu bordro sayfasında başta tek kez yüklenir. Aynı hazırlık süresince tablo gövdesinin üzerinde, DataTables kayıtlarına dahil olmayan bir yükleme katmanı gösterilir. AJAX takibi kapatıldığı için Pace kendiliğinden ikinci kez başlamaz; DataTables kurulumu, gelişmiş kolon filtreleri, kolon yerleşimi ve ilk satır görsel hazırlığı tamamlanıp tarayıcı son yerleşimi çizdiğinde iki gösterge birlikte durdurulur. Tablo AJAX ile yenilenirken ikisi birlikte yeniden gösterilir.
- İcra açıklamaları `<template>` içinde tutulur ve ilk üzerine gelindiğinde DOM'a eklenir. Büyük fotoğraf önizlemeleri ilk hover sırasında yüklenir. Bordro modallarındaki Select2 ve tarih alanları yalnız modal açıldığında, bir kez başlatılır; Select2 `dropdownParent` modalın kendisidir.
- Bordro tablosu hazır olduğunda uygulamanın genel `#preloader` katmanı doğrudan kapatılır; genel yerleşimdeki `window.load + 350 ms + fadeOut` beklemesi uygulanmaz. Pace üst çubuğu sayfa başında tek kez başlar, AJAX isteklerinde yeniden başlamaz ve tablo hazır olduğunda durur. Select2, doğrulama, SweetAlert ve Toastify bu sayfada yerel dosyalardan yüklenir; eski Popper CDN isteği atlanır. Tema fontlarının çalışması için Google Fonts korunur ve bordro sayfasında render engellemeyen `media=print/onload` yöntemiyle yüklenir. İlk çizim için sayfa başında bulunan Feather Icons vendor bölümünde ikinci kez yüklenmez. Bordronun kullanmadığı Responsive, Buttons/JSZip, ColReorder, FixedHeader ve ikinci Sortable yüklemeleri bu sayfaya eklenmez.
- `deferRender` ayarı korunur; HTML/DOM kaynaklı mevcut tabloda satırlar sunucudan zaten DOM olarak gelir. Bu ayarın tek başına HTML satırlarını ertelemesi beklenmez.

## Ölçüm

Yerel veritabanında 9 aktif dönem, toplam 369 personel satırı değişiklik öncesi modelin ayrı kopyasıyla karşılaştırıldı. Gösterim hesaplarının tüm alanları birebir eşleşti. Karşılaştırmanın her tarafında parametre önbellekleri temizlendi: toplam model sorgusu **486 → 124**, aynı koşullu örnek çalıştırmada liste + gösterim süresi **595 → 412 ms**. Süreler makine yüküne göre değişir.

103 satırlı sayfanın CLI üzerinden HTML üretimini içeren beş dönüşümlü ölçümün medyanları:

| Aşama | Önce | Sonra |
| --- | ---: | ---: |
| Liste ve kaynak yükleme | 54 ms | 72 ms |
| Gösterim ve açıklama hesapları | 152 ms | 91 ms |
| Hesap sonrası HTML üretimi | 6 ms | 5 ms |
| Sayfa PHP toplamı | 217 ms | 176 ms |

Toplu yükleme işi liste aşamasına taşır; toplam PHP süresi bu örnekte yaklaşık %19 azalır. Ölçüm giriş denetimini, ağ süresini ve tarayıcı çizimini kapsamaz. Önceki model kopyasıyla karşılaştırma geçici yerel araçla yapıldı; bu kopya depoya eklenmedi.

Ana sorgunun `EXPLAIN` çıktısında bordro için `unique_donem_personel`, kesintiler için `idx_donem_silinme`, ek ödemeler için `donem`, görev geçmişi için `idx_personel_dates`, ekip geçmişi için `idx_personel_firma` kullanıldığı doğrulandı. Eski performans migration dosyasındaki indeks adları bu veritabanında yok; alternatif indeksler mevcut. Yeni indeks gerektiren ölçülmüş bir darboğaz bulunmadığından SQL değişikliği eklenmedi.

## İzleme ve tekrar doğrulama

- Tarayıcı konsolunda `window.bordroServerTiming`: `list_ms`, `calculation_ms`, `html_ms`, `page_ms`.
- `window.bordroClientTiming.datatable_ms`: tablonun görünür ilk çizim süresi.
- `window.bordroClientTiming.filters_ms`: görünür çizimden sonra hazırlanan başlık filtrelerinin süresi.
- `window.bordroClientTiming.table_ready_ms`: navigasyon başlangıcından tablonun hazırlanmasına kadar geçen süre; dosya yükleme beklemesini de içerir. `before_dom_ready` tablonun genel sayfa hazırlığından önce açıldığını gösterir.
- Mevcut `cache/perf/request_metrics.log` kayıtlarında `bordro_timing` alanı bulunur.
- İsteğe bağlı `BORDRO_PROFILE=1` ortam ayarı SELECT sayısını da ekler ve PHP hata günlüğüne `bordro-performance` kaydı yazar. Normal kullanımda ek sayaç sorguları çalışmaz. Kayıtlar personel içeriği içermez.
- Salt okunur ölçüm ve aynı nesne/farklı dönem kontrolü: `/opt/lampp/bin/php tests/Integration/bordro-performance.php`.
- Bordro testleri: `/opt/lampp/bin/php vendor/bin/pest tests/Unit --filter Bordro --do-not-cache-result`.

103 gerçek satırla izole Chromium kontrolünde tablo açılışı, sayfalama, sıralama, arama ve tüm sayfalardaki toplu seçim doğrulandı; JavaScript hatası oluşmadı. HTTP istekleri izole edilmiştir; oturumlu detay API ve gerçek Excel indirmesi bu tarayıcı kontrolüne dahil değildir. Mevcut bordro testleri liste/Excel hesap tutarlılığını kontrol eder.

Ek kontrolde sayfanın tam modal HTML'i ve gerçek form kütüphaneleri kullanıldı. Tablo sonrasındaki bir script yüklemesi yapay olarak 2 saniye geciktirildi. Erken başlatma tablonun bu script tamamlanmadan ve DOM-ready olmadan hazır olmasını sağladı (`before_dom_ready=true`); DOM-ready bekleyen karşılaştırma bu yüklemeyi bekledi. Bu kontrollü senaryo gerçek CDN gecikmesini ölçmez; sonradan yüklenen dosyaların tabloyu bekletmediğini doğrular. Modal Select2/tarih alanlarının ilk açılışta başlatılması, ikinci açılışta korunması, templateli açıklamanın ilk hover ile oluşturulması, sayfalama, toplu seçim ve kolon araması doğrulandı.
