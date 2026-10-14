# Bordro aşamalı geçişi

## Aşama 1 — Denetim (2026-10-14)

`BordroPersonelModel::auditKayitliBordro()` kayıtlı snapshot ve ödeme kanallarını
salt okunur olarak karşılaştırır. Hesaplama formülü veya ödeme kaydı değiştirmez.
Hakediş eksi snapshot personel kesintileri ile kanal toplamı kuruş bazında
karşılaştırılır; kanal toplamı tutsa da JSON/kolon farkları ayrıca raporlanır.
Sürümsüz geçmiş kayıtta net_maas anlamı belirsizse bu işaretlenir. Böyle kayıtlar
otomatik düzeltilmez. Bu denetim henüz kapanış/export engeline bağlanmamıştır.

İncelemede görülen geçiş riskleri:
- Kapanış kontrolü yalnız icrayı düşüyor; diğer personel kesintileri farklı okunuyor.
- Gösterim kesintiyi snapshot kalemlerinden, eski alanlardan farklı türetiyor.
- Sürümsüz net_maas bazı yollarda hakediş, bazılarında kesinti sonrası net olarak kullanılıyor.
- Detay dağılımı satırları kalan banka kapasitesine kırpıyor; eksik kaynak bilgisi
  aynı tutarlı fazla mesai/HTÇ satırının yanlış etiketlenmesine yol açabiliyor.
- Mevcut karma maaş regresyonunda 600 TL fark var; yeni motorun devreye alınmasından
  önce nedeni ve beklenen iş kuralı doğrulanmalı.

## Aşama 2 — Tek hesap sonucu

İlk parça: `getSurumluBordroKarsilastirma()` sürüm 1 karşılaştırma yapısını
hazırlar. Para alanları tam sayı kuruş olarak taşınır. Kayıtlı kanallar aynen
korunur; toplam ödeme mutabık değilse hakediş/ödenecek net alanları bilinmeyen
(`null`) olarak işaretlenir. Eksik resmî net ve gün/saat bilgileri tahmin edilmez.
Kalem kimliği ve snapshot sırası korunur. Bu bir uyumluluk/karşılaştırma
okuyucusudur; yeni hesaplama motoru henüz devreye alınmamıştır.

Sürümlü sonuç: toplam hakediş, gerçek kesintiler, ödenecek net, resmî matrah,
yemek günlük limiti/yuvarlaması ve dört ödeme kanalı ayrı alanlarda saklanacak.
Kalemler kaynak kimliği, kod, gerçek hakediş ve resmî net taşıyacak.
Banka işaretli kazancın resmî net üstü payı önce yemek kapasitesini dolduracak.
Eski hesapla karşılaştırmalı çalıştırılıp farklar açıklanmadan aktive edilmeyecek.

## Aşama 3 — Gösterim geçişi

Yeni snapshot'larda `ortak_sonuc` sürümlü gösterim sonucu saklanır. Okuyucu,
kayıtlı kanalları bu sonuçla karşılaştırır; farklılık varsa sessiz yeniden
hesaplama yapmaz. Eski snapshot uyumluluğu korunur.

Liste, detay, Excel ve yayın sürümlü sonucu okuyacak. Eski snapshot'lar kayıtlı
kanalları koruyan uyumluluk okuyucusunda kalacak.

## Aşama 4 — Kayıt ve kapanış

Kuruş mutabakatı yeni kayıt öncesinde zorunlu olacak. Dönem bazlı rapor ve mevcut
kapanış kontrolü aynı denetimi kullanacak. Yetkili yeniden hesaplama geçmişten
yeni sürüme geçişin tek yolu olacak.

## Uygulanan geçiş ve doğrulama

- Yeni hesaplama `hesaplaMaas()` sonunda ödeme mutabakatını doğrular, ortak
  gösterim sonucunu `ortak_sonuc.surum = 1` ile aynı JSON snapshot'a yazar.
- `hesaplaOrtakGosterimDegerleri()` sürüm 1 kayıtta saklanan sonucu döndürür.
  Liste, detay ve Excel'in ortak metodu bu sonucu tüketir; kayıtlı kanallar
  uyuşmazsa hata üretir. Yeni snapshot RTÇ/HTÇ günlerini de saklar.
- `saveBordroHesaplama()` sürümlü sonuçta tutarsız kaydı SQL öncesinde engeller.
- Manuel dağıtım ortak sonucu ve JSON kanallarını aynı transaction içinde
  günceller; toplamı ödenecek netten farklı olan dağıtımı reddeder.
- Kapanış, yeni sürümlü kayıtta ortak denetimi kullanır. Eski sürümsüz kapanış
  uyumluluğu değiştirilmemiştir; tarihsel net_maas anlamı otomatik tahmin edilmez.
- Karma maaş testinin banka beklentisinde yemek havuzuna alınan kaçak ihbar
  priminin ikinci kez bankaya eklenmesi giderildi; hesap kuralı değiştirilmedi.
- 88 bordro testi / 652 doğrulama başarılı. Üretim dönemine yeniden hesaplama
  veya veri göçü uygulanmadı. Canlı dönem mutabakatı hedef ortamda ayrıca yapılmalı.

Sınır: bu çalışma mevcut Model motorunu ortak sonuç sözleşmesine bağlar.
Eski Model'in ayrı bir hesaplama servisine fiziksel taşınması ve tüm detay
açıklama şablonlarının sadeleştirilmesi bu geçişin tamamlandığı anlamına gelmez.
