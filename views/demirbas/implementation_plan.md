# Demirbaş Modülü Yeniden Yapılandırma Planı

Bu doküman, [views/demirbas/list.php](views/demirbas/list.php) içindeki sekmeli yapının ayrı sayfalara bölünmesi ve WhatsApp konuşmalarındaki yeni Sayaç/Aparat depo konseptinin uygulanması için detaylı bir yol haritasıdır.

Not: Bu dosya yalnızca plan içerir. Henüz kod uygulaması yapılmayacaktır.

## 1. Mevcut Durum Özeti

- Tüm ana akışlar (Demirbaş, Sayaç, Aparat, Servis, Zimmet) tek sayfada toplanmıştır: [views/demirbas/list.php](views/demirbas/list.php).
- Tüm istemci mantığı tek JS dosyasında toplanmıştır: [views/demirbas/js/demirbas.js](views/demirbas/js/demirbas.js).
- API katmanında çok sayıda action tek uçtan yönetilmektedir: [views/demirbas/api.php](views/demirbas/api.php).
- Ayrık hedef sayfalar mevcut ama boştur:
  - [views/demirbas/sayac-deposu.php](views/demirbas/sayac-deposu.php)
  - [views/demirbas/aparat-deposu.php](views/demirbas/aparat-deposu.php)
  - [views/demirbas/servis.php](views/demirbas/servis.php)
  - [views/demirbas/zimmet.php](views/demirbas/zimmet.php)
- Sayfa bağımlılıkları ve script yükleme koşulları şu an ağırlıklı olarak yalnızca [views/demirbas/list.php](views/demirbas/list.php) için tanımlıdır: [layouts/vendor-scripts.php](layouts/vendor-scripts.php).

## 2. Hedef Mimari

### 2.1 Sayfa Ayrımı

- [views/demirbas/list.php](views/demirbas/list.php): sadece Demirbaş Listesi ekranı kalacak.
- [views/demirbas/sayac-deposu.php](views/demirbas/sayac-deposu.php): personel bazlı Sayaç ekranı + Hareketler alt sekmesi.
- [views/demirbas/aparat-deposu.php](views/demirbas/aparat-deposu.php): personel bazlı Aparat ekranı + Hareketler alt sekmesi.
- [views/demirbas/servis.php](views/demirbas/servis.php): servis listeleme, filtre ve servis modal akışı.
- [views/demirbas/zimmet.php](views/demirbas/zimmet.php): zimmet listeleme, toplu iade/toplu sil akışları.

### 2.2 JS Modülerleşme

- [views/demirbas/js/demirbas.js](views/demirbas/js/demirbas.js) dosyası parçalara ayrılacak:
  - [views/demirbas/js/list.js](views/demirbas/js/list.js)
  - [views/demirbas/js/sayac-deposu.js](views/demirbas/js/sayac-deposu.js)
  - [views/demirbas/js/aparat-deposu.js](views/demirbas/js/aparat-deposu.js)
  - [views/demirbas/js/servis.js](views/demirbas/js/servis.js)
  - [views/demirbas/js/zimmet.js](views/demirbas/js/zimmet.js)
  - Ortak yardımcılar (opsiyonel): [views/demirbas/js/common.js](views/demirbas/js/common.js)

### 2.3 API Stratejisi

- İlk aşamada geriye uyumluluk için mevcut action isimleri korunacak.
- Yeni personel bazlı ekranları desteklemek için yeni action seti eklenecek.
- Tüm yeni uçlar yine [views/demirbas/api.php](views/demirbas/api.php) içinde başlayacak; ikinci fazda controller/service ayrıştırması değerlendirilecek.

## 3. Fonksiyonel Kapsam (Konuşma Notlarına Göre)

### 3.1 Sayaç Deposu

- Ana sekmeler: SAYAÇLAR, Hareketler.
- SAYAÇLAR sekmesi:
  - Global üst kartlar: depodaki yeni, depodaki hurda, zimmetli yeni, zimmetli hurda.
  - Ana tablo: demirbaş listesi yerine personel listesi.
  - Personel detayı (satır açılımı veya modal/yan panel):
    - Bizden toplam aldığı sayaç
    - Toplam taktığı sayaç
    - Elinde kalan toplam yeni sayaç
    - Toplam hurda sayaç
    - Teslim edilen hurda sayaç
    - Elinde kalan toplam hurda sayaç
  - Detay altında gün gün aldığı/verdiği sayaç hareket tablosu.
- Hareketler sekmesi:
  - Tüm personellerin kümülatif sayaç hareketleri (eski toplu hareket mantığı).

### 3.2 Aparat Deposu

- Sayaç deposundaki yapının aparat alanına uyarlanmış aynısı.
- Ana sekmeler: APARATLAR, Hareketler.
- Personel bazlı özet + personel detay hareketleri.

### 3.3 Servis ve Zimmet

- Servis akışı [views/demirbas/servis.php](views/demirbas/servis.php) içinde bağımsız yönetilecek.
- Zimmet akışı [views/demirbas/zimmet.php](views/demirbas/zimmet.php) içinde bağımsız yönetilecek.
- [views/demirbas/list.php](views/demirbas/list.php) içinden bu sekme/buton bağımlılıkları kaldırılacak.

## 4. Veri ve API Tasarımı

### 4.1 Yeni Action Önerileri

- Sayaç:
  - sayac-personel-list
  - sayac-personel-summary
  - sayac-personel-history
  - sayac-hareketler-list
- Aparat:
  - aparat-personel-list
  - aparat-personel-summary
  - aparat-personel-history
  - aparat-hareketler-list

Not: İsimler, mevcut style ile uyumlu olacak şekilde finalde netleştirilecek.

### 4.2 Veri Kaynağı ve Hesap Kuralları

- Kaynak tablolar: demirbas, demirbas_zimmet, demirbas_hareketler, personel/cari ilişkisi.
- Hesaplar mümkün olduğunca SQL tarafında aggregate edilerek DataTable'a hazır dönecek.
- Kategori ayrımı mevcut sayaç/aparat kategori tespiti mantığıyla uyumlu ilerleyecek.
- Hurda ve kaskiye teslim gibi durumlar metriklere dahil/dahil değil kuralları net tanımlanacak.

### 4.3 Performans Önlemleri

- Personel listesi için server-side DataTable zorunlu.
- Personel detayı lazy-load olacak (satıra tıklayınca çağrı).
- Gerekirse özet endpointleri için kısa süreli cache katmanı kullanılacak.

## 5. UI/UX Kararları

- Önerilen varsayılan: personel detayında satır altı genişleme (row expansion).
- Gerekçe:
  - Liste bağlamı kaybolmaz.
  - Aynı ekranda çoklu personel kıyaslanabilir.
  - Ekstra modal state yönetimi azalır.
- Mobilde row expansion dar gelirse fallback olarak tam ekran modal kullanılabilir.

## 6. Uygulama Fazları (Kodlamaya Geçmeden Önce Yol Haritası)

### Faz 0 - Karar ve Kapsam Netleştirme

- Açık soruların ürün sahibinden onayı alınır.
- Menüde hangi başlıkların görüneceği netleştirilir.
- Personel bazlı metriklerin kesin formülleri yazılı olarak onaylanır.

### Faz 1 - Sayfa İskeletlerinin Çıkarılması

- Ayrı sayfaların temel HTML/layout iskeletleri hazırlanır.
- [views/demirbas/list.php](views/demirbas/list.php) sadeleştirme planı çıkarılır.
- Ortak modal include stratejisi belirlenir.

### Faz 2 - Script Yükleme ve Modül Ayrımı

- [layouts/vendor-scripts.php](layouts/vendor-scripts.php) içinde yeni sayfalar için script koşulları tanımlanır.
- [views/demirbas/js/demirbas.js](views/demirbas/js/demirbas.js) sorumluluklarına göre bölünür.

### Faz 3 - API Uçları ve Query Katmanı

- Yeni action uçları eklenir.
- Personel özet ve hareket query'leri geliştirilir.
- Eski actionlar bozulmadan korunur.

### Faz 4 - Ekran Entegrasyonu

- Sayaç/Aparat personel listesi ve detay panelleri bağlanır.
- Servis ve Zimmet sayfaları ayrı çalışır hale getirilir.
- Eski sekme bağımlılıkları temizlenir.

### Faz 5 - Geçiş, Doğrulama, Stabilizasyon

- Eski URL davranışlarının etkisi kontrol edilir.
- Yetki kontrolleri (Gate) yeni sayfalarda doğrulanır.
- Görsel ve fonksiyonel regresyon kontrolleri tamamlanır.

## 7. Etki Analizi ve Riskler

- Menü/route uyumsuzluğu riski: yeni sayfalar eklenmezse kullanıcı boş ekran görebilir.
- Script bağımlılık riski: Datatable/ApexCharts koşulları yeni sayfalar için güncellenmezse JS hatası alınır.
- Veri modeli riski: personel bazlı metriklerin tamamı mevcut tablolardan üretilemezse ek endpoint veya iş kuralı gerekir.
- Yetki riski: tek sayfadan çok sayfaya geçişte Gate kontrolleri dağılabilir.

## 8. Doğrulama Planı

### 8.1 Teknik Kontroller

- php -l ile değişen PHP dosyalarında syntax kontrolü.
- Tarayıcı console ve network hatalarının kontrolü.
- DataTable response formatlarının (draw, recordsTotal, recordsFiltered, data) doğrulanması.

### 8.2 Manuel Senaryolar

- Menüden her yeni sayfaya erişim testi.
- Sayaç/Aparat personel listesi yüklenmesi.
- Personel detay kartları ve günlük hareketlerin doğruluğu.
- Hareketler sekmesinde kümülatif kayıt görünümü.
- Servis ve Zimmet işlemlerinde eski fonksiyonların korunması.

## 9. Açık Sorular (Onay Bekleyen)

1. Personel detayı varsayılanı row expansion mı, modal mı olmalı?
2. Demirbaşlar ekranı tamamen [views/demirbas/list.php](views/demirbas/list.php) ile sınırlanıp diğerleri ayrı menü başlıkları mı olacak?
3. Sayaç/Aparat tarafında hareket tanımı için tek tarih sütunu mu, işlem tipi bazlı ayrı kolonlar mı isteniyor?
4. Personel kaynağı kesin olarak personel tablosu mu, cari tablosu mu, yoksa hibrit mi olacak?

## 10. Kabul Kriterleri

- [views/demirbas/list.php](views/demirbas/list.php) yalnız demirbaş listesi işlevini içerir.
- Sayaç/Aparat ekranları personel bazlı çalışır ve detay metrikleri doğru hesaplanır.
- Servis ve Zimmet ekranları bağımsız sayfa olarak çalışır.
- [views/demirbas/js/demirbas.js](views/demirbas/js/demirbas.js) tek parça ana bağımlılık olmaktan çıkar.
- API tarafında mevcut işlevler kırılmadan yeni uçlar devreye alınır.
