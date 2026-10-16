# Personel PWA — Resmî bordro yayını

## Kullanım

1. Bordroyu hesaplayıp dönemi kapatın.
2. Bordro listesinin araç çubuğunda **Bordro Yayını** düğmesini açın.
3. **Önizlemeyi hazırla** ile personel dökümlerini ve hesap kontrol hatalarını inceleyin. Hata varsa yayın engellenir; düzeltme için dönemi yeniden açıp hesaplayın.
4. **Kontrol edilen dökümleri yayınla** ile değişmez sürümü oluşturun. Yayın ile bildirim kuyruğu aynı transaction içinde kaydedilir.
5. Yayın takip tablosunda görüntüleme, okuma beyanı ve inceleme taleplerini takip edin. Personelin talebini **İncele** üzerinden gerekçeli yanıtlayın. Filtrelenen tablo CSV olarak indirilebilir. İlk push gönderiminin durumu, kalıcı gönderim hataları ve sürümün işlem geçmişi ayrıca izlenebilir.

Tek personelle kontrollü PWA denemesi için önizlemedeki seçim düğmesinden personeli seçip **Seçilen personelde bildirimsiz test et** işlemini kullanın. Test sürümü yalnız seçilen personelin hesabında görünür, bildirim kuyruğu üretmez ve `T1`, `T2` biçiminde resmî sürümlerden ayrı numaralanır. Aynı dönemde yeni test oluşturulduğunda önceki test dökümü soft delete ile pasifleştirilir; aynı anda yalnız bir test personeli görünür.

Personel PWA bordro sekmesi resmî dökümleri ve PDF'lerini gösterir; ana sayfa okuma beyanı bekleyen dökümleri bildirir. Dökümün görüntülenmesi ve PDF indirme okuma beyanı değildir. Beyan kutusu başlangıçta boştur. İnceleme talebi, beyandan bağımsız açılır; aynı sürümde tek açık talep bulunabilir, sonuçlanan talepten sonra yeni talep açılabilir.

Mevcut `personel_gorsun` alanı yeni yayın akışını başlatmaz. Eski finansal özetlerin görünürlük davranışı için korunmuştur. Eski ham bordro detay/PDF yolları yeni resmî yayın kaydına yönlendirilmiştir; ham bordro ID ile erişim yoktur. Kapanmış bordroları otomatik “Ödendi” gösteren eski son etkinlik kaydı kaldırılmıştır.

## Hesap ve kayıt ilkeleri

- Yönetim tarafındaki **Resmî Ücret Hesap Pusulası** ile personel dijital yayını aynı `BordroPusulaService` üreticisini kullanır. Yayın snapshot'ında resmî kazanç toplamı, yasal/özel kesinti toplamları, yasal kesinti kalemleri, resmî matrahlar, çalışma/izin günleri ve vergi istisnaları içerik sürümüyle birlikte mühürlenir.
- Yeni yayınların personel PDF'i, yönetim raporundaki aynı **Ücret Hesap Pusulası** A4 şablonundan ve yalnız mühürlü yayın snapshot'ından üretilir. Canlı personel/firma/bordro kaydı yeniden okunmaz; T.C. kimlik numarası yayın snapshot'ında maskeli saklanır. Eski yayınlar kendi tarihsel PDF görünümüyle geriye uyumlu kalır.
- Personel yayınına tam `hesaplama_detay`, elden/Sodexo/diğer ödeme kanalları veya teknik iç hesap alanları aktarılmaz. Personel arayüzü ve API yalnız resmî pusulanın sunulabilir alt kümesini döndürür.
- `hesaplaOrtakGosterimDegerleri()` ve `getMuhasebeOdemeOzeti()` çıktıları kullanılır. Maaş, yemek, RTÇ/HTÇ veya kesinti dağıtım kuralları değiştirilmez.
- Ortak modelin resmî ücret tabanı ve banka kesinti etiketleri sunum için ayrıca dışa açılır. Elden ve iç hesap bilgileri yayın içeriğine girmez.
- Kayıtlı `banka_odemesi`, ortak hesap neti ve döküm kalem toplamı kuruş bazında eşit olmalıdır. Eksik hesaplanmış, manuel dağılımı açıklanamayan veya tutarsız kayıtlar yayını durdurur.
- Günlük yemek yuvarlama farkı ayrı satırdır. Ortak hesabın kazanç tavanı, kazanç kalemlerinden düşükse uygulanmış tavan farkı ayrı satır gösterilir; pozitif açıklanamayan fark yeni kazanç gibi tamamlanmaz.
- Banka kesinti kalemlerinin toplamı gerçekleşen banka kesintisine eşitse ayrı gösterilir. Tavan/dağılım nedeniyle yalnız bir kısmı bankadan düşmüşse ortak model satır payı üretmediği için gerçekleşen banka kesintisi toplam olarak gösterilir; elden kesinti bilgisi yayınlanmaz.
- Yayın içeriği personel adı, dönem/görev bilgileri, günler ve resmî kalemleri içerir. Sürüm, SHA-256, yayınlayan, yayın zamanı ve olay geçmişi saklanır. Hash içerik bütünlüğü kontrolüdür.
- Onay metni v2: **Ücret hesap pusulasını okudum ve onaylıyorum.** Sunucu zamanı, personel, döküm/sürüm, metin sürümü, içerik hash'i ve teknik işlem kanıtları kaydedilir. OTP/e-imza eklenmemiştir.
- Yeniden açma aynı dönem satırını kilitleyip mevcut yayını `revizyon` durumuna geçirir. Önceki içerik/beyan korunur. Tekrar kapatıp yayınlama eski sürümü arşivler ve yeni sürümde yeniden beyan ister. Yayın, yeniden açma, beyan ve bildirim işlemleri aynı dönem kilidini kullanır.
- Döküm/PDF ağdan alınır. Service worker güncellemesi eski sayfa önbelleğini temizler; bordro navigasyonları ve önceden önbellekleme hariç tutulur. Beyan/talep çevrimdışı kuyruğa alınmaz.

## Kurulum

Önce ayrı SQL scriptini uygulayın:

`database/migrations/2026_10_01_bordro_yayin.sql`

Mevcut kurulumlarda durum, onay kanıtı, pusula numarası ve itiraz eki genişletmesini ardından uygulayın:

`database/migrations/2026_10_16_expand_bordro_yayin_onay.sql`

Script altı yeni InnoDB tablo oluşturur; bordro hesaplama tablolarını/verilerini değiştirmez. Canlı veritabanına bu çalışma kapsamında otomatik uygulanmaz. Uygulama dosyalarıyla birlikte dağıtılmalıdır; yeni uç noktalar tablolar olmadan çalışmaz.

Genişletme migration'ı eski dökümlerin pusula numarası ile oluşturma/gönderim zamanlarını mevcut dönem ve yayın kaydından geriye doldurur. İtiraz ekleri `uploads/bordro-itiraz/` altında AES şifreli saklanır; yalnız firma yetkili kullanıcısı veya kaydın sahibi personel şifreli token ile görüntüleyebilir.

## Pusula durumları ve itiraz

- Personel iş durumu veriden türetilir: `bekliyor`, `goruntulendi`, `onaylandi`, `itiraz_edildi`, `revize_edildi`. Görüntüleme onay sayılmaz; ilk görüntüleme tarihi korunurken sonraki görüntülemeler de olay günlüğüne eklenir.
- Onay metni v2: **Ücret hesap pusulasını okudum ve onaylıyorum.** Onayda sunucu zamanı, personel, pusula sürümü, içerik SHA-256, IP, user-agent ve oturum hash'i kaydedilir.
- İtiraz konusu; eksik çalışma, fazla mesai, prim, avans, kesinti, yemek/yol veya diğer olarak seçilir. PDF/JPG/PNG/WebP türünde en fazla 10 MB ek AES şifreli saklanabilir.
- İtiraz durumları `alindi`, `inceleniyor`, `ek_bilgi_bekleniyor`, `sonuclandi` biçimindedir. Yeni itiraz bordro yetkililerine uygulama içi bildirim, sonuç ise personele push bildirimi üretir. Revize edilen sürümün ilk bildirimi normal yayından ayrı metin kullanır.

Bildirim işçisini uygulamanın PHP sürümüyle 5 dakikada bir CLI üzerinden çalıştırın:

```cron
*/5 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/views/cron/bordro_yayin_cron.php
```

Yollar kurulum dizinine göre uyarlanır. Webden cron çağrısı reddedilir. Cron aktif edilmezse yayın ve PWA kartı çalışır, push gönderimi çalışmaz. Mevcut VAPID yapılandırması ve personel push aboneliği kullanılır; bordro bildirimleri için e-posta fallback gönderilmez.

İlk yayın ve yayın tarihinden 3/7 gün sonraki bildirimler kalıcı kuyruğa kaydedilir. Beyan verilmiş, arşiv/revizyondaki veya kapalı olmayan dönemler; pasif/ayrılmış personel ve aboneliği olmayan alıcılar atlanır. Hatalı gönderim bir saat sonra tekrar denenir, en fazla beş deneme yapılır. Kesilen işçinin kilidi 15 dakika sonra geri alınır. Bildirimlerde tutar yoktur. Servis çıktısı gönderilen/atlanan/yeniden bekleyen sayıları içerir; hata ayrıntıları uygulama hata günlüğüne yazılır. Kuyruk `basarisiz` kayıtları operasyonel takip gerektirir.

Push teslimi dış sistemle transaction yapamadığından işçi gönderim sonrası kesilirse tekrar teslim olabilir; sabit bildirim `tag` aynı döküm/hatırlatma bildirimini cihazda birleştirir. Bildirim başarısızlığı yayın kayıtlarını silmez.

## Doğrulama

```sh
/opt/lampp/bin/php vendor/bin/phpunit tests/Unit/BordroBankaDagilimiTest.php tests/Unit/BordroCalismaGecmisiGunSayisiTest.php tests/Unit/BordroGorevGecmisiTest.php tests/Unit/BordroYayinIcerikTest.php tests/Unit/BordroYayinWorkflowTest.php
node node_modules/@playwright/test/cli.js test tests/e2e/bordro-yayin.spec.ts --project=chromium --reporter=line --workers=1
BORDRO_YAYIN_MYSQL_TEST=1 /opt/lampp/bin/php tests/Integration/bordro-yayin-mysql.php
```

İş akışı birim testleri izole SQLite ile erişim, sürüm, olay, transaction rollback ve kuyruk durumlarını sınar. Ek MySQL testi ayrı, rastgele isimli geçici şema oluşturup migration scriptini, eşzamanlı yayın/yeniden açma/beyan kilitlerini ve 0/3/7 gün kuyruğunu doğrular; sonunda şemayı siler. MySQL testinin hesabı geçici şema oluşturma/silme yetkisine ihtiyaç duyar; uygulama veritabanına veri yazmaz. PDF endpointi de bu geçici MySQL şemasıyla çalıştırılarak Türkçe içerik ve indirme sırasında beyan oluşturmama doğrulanır. Tarayıcı testleri gerçek PWA JavaScript'iyle kontrollü API yanıtlarını kullanır; canlı personel hesabına veya veritabanına yazmaz.

İlk kullanımda tek kapalı dönemle kontrol edin: liste–detay–kayıt–muhasebe banka neti, gerçek personel oturumuyla PDF, bildirimden giriş sonrası döküme dönüş ve iki ayrı MySQL bağlantısından eşzamanlı yayın/yeniden açma/beyan. Geçmiş dönemler otomatik yayınlanmaz.
