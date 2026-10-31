# Bordro Hesaplama Kurallari

Bu dosya `app/Model/BordroPersonelModel.php` icindeki bordro hesaplama fonksiyonlari icin is kuralı sozlesmesidir.

Kapsam:

- `hesaplaMaas()`
- `hesaplaMaasByPersonelDonem()`
- `hesaplaMaasaDahilYardimDagilimi()`
- `hesaplaOrtakGosterimDegerleri()`

Bu kurallar bordro/list ekranindaki gosterim, detay modalı, Excel export ve maas hesaplama sonucunun ayni mantikla calismasi icin korunmalidir.

## Temel Kavramlar

- `asgari_ucret_net`: Donem baslangic tarihine gore `bordro_genel_ayarlar` tablosundan gelir.
- `maasHesapGunu`: Maas carpaninda kullanilan SSK/hesap gunudur.
- `fiiliGunSayisi`: Yemek gibi fiili calisma bazli yardimlarda kullanilir. Oncelik puantajdaki `X` gunleridir.
- `hedef_net_maas_tutari`: Maasa dahil sosyal yardimlarda personelin sozlesme/ hedef net maasidir.
- `asgariHakedis`: Calisilan gune gore asgari net tabandir.
- `toplamKesinti`: Avans, icra, ucretsiz izin vb. bordro kesintileri toplamidir.
- `icraKesintisi`: Odeme dagiliminda ayrica dusulen icra tutaridir.
- `sodexoOdemesi`, `bankaOdemesi`, `eldenOdeme`, `digerOdeme`: Net maasin odeme kanallarina dagilimidir.

### Bordro Detayında Banka ve Fazla Çalışma Gösterimi

- Eski/kompakt bordro detay görünümünün sol sütununda alt alta iki açılır hesap başlığı bulunur: **Sözleşme Alacağı Hesabı** ve **Resmî Alacağı Hesabı**. Başlık tıklanınca ilgili hesap kartı açılır. Sözleşme alacağı kartı, yeni detay görünümündeki Brüt Toplamı açılımıyla; resmî alacağı kartı ise Banka Ödemesi açılımındaki kesinti öncesi resmî kalemlerle aynı veriyi kullanır. Kesintiler, eski yerleşimdeki gibi sağ sütunda ayrı kırmızı kartta gösterilir.
- Kullanıcı **Alt Alta / Yan Yana** yerleşim düğmesiyle detay kartlarının düzenini değiştirebilir. Yan yana görünümde Sözleşme Alacağı, Resmî Alacağı, Kesintiler ve Ödenecek Net Maaş dört eşit kolon olarak gösterilir. Alt alta görünümde ilk iki hesap paneli sol sütunda, Kesintiler sağda ve net ödeme kartı tam genişlikte altta kalır. Tercih sayfaya özgü `localStorage` anahtarında saklanır ve sayfa yenilendiğinde veya detay yeniden açıldığında korunur. Dar ekranlarda okunabilirlik için paneller seçime bakılmaksızın alt alta gösterilir.
- Sözleşme ve resmî alacak ana panellerinin açık/kapalı durumları birbirinden bağımsız olarak `localStorage` içinde saklanır. Detay yeniden açıldığında son durumlar geri yüklenir; puantaj ve diğer panel içi alt kırılımların geçici collapse durumu bu tercihe dahil edilmez.
- Eski detayın sözleşme kazanç satırlarında teknik ek ödeme kodu (`aylik_arac_kirasi`, `kacak_ihbar_primi` vb.) kullanıcıya gösterilmez; bordro döneminde geçerli `bordro_parametreleri.etiket` değeri kullanılır.
- Kendi alacağı hesabında sözleşme günlük ücreti, maaş hesap günü, sözleşme üzerine eklenen tam HTÇ hakedişi ve diğer kazançlar ayrı gösterilir. Prim usulü **Puantaj Hakedişi** akordeon başlığında puantaj kaynaklarından hesaplanan toplam işlem/adet rozeti gösterilir; akordeon açıldığında işlem adı, adet, birim fiyat ve tutar kırılımları doğrudan bu satırın altında listelenir. Bu açıklayıcı görünüm hakediş veya ödeme toplamını değiştirmez.
- Sözleşme alacağı tarafındaki **Hafta İçi Nöbet** detayı yalnız `gün × sözleşme nöbet tutarı` biçiminde (ör. `2 Gün × 500,00 ₺`) gösterilir. Bordro kaynağındaki teknik `4 Saat` bilgisi bu sözleşme detayına yazılmaz; saat bilgisi yalnız resmî **Fazla Mesai Ücreti (Net)** hesabında kullanılır.
- Sözleşme alacağı tarafında hafta içi ve hafta sonu nöbet hakedişleri tek **Nöbet Ödemeleri** açılır başlığında toplanır. Başlık toplam nöbet tutarını; alt satırlar hafta içi için `gün × birim tutar`, hafta sonu için `adet × birim tutar` kırılımını ve her birinin tutarını gösterir. Bu alt satırlar detay açıldığında varsayılan olarak görünürdür; kullanıcı başlıktan kapatabilir. Puantajdan veya arife otomasyonundan gelen fazla mesai hakedişi de sözleşme tarafında ayrı `Fazla Mesai` satırı olarak çizilmez; aynı grupta `Puantaj Nöbeti (saat × birim tutar)` kırılımıyla gösterilir.
- Resmî alacak hesabında günlük net asgari ücret × banka günü, yemek, eş yardımı ve diğer resmî eklemeler gösterilir ve panel kesinti öncesi resmî alacakla tamamlanır. Banka kesintileri sağdaki **Kesintiler** kartında kırmızı eksi kalemler olarak, net banka ödemesi ise alt ödeme sonucunda gösterilir.
- Resmî banka matrahı kayıtlı kalemlere ayrılırken Muhasebe Excel'iyle aynı öncelik uygulanır: asgari/normal ücret ve eş yardımından sonra kayıtlı resmî RTÇ, HTÇ, fazla mesai ve resmî prim kalemleri ayrılır; yemek yardımı kalan resmî matrahla sınırlandırılır. Böylece yemek havuzuna kaynak olan HTÇ gibi bir resmî kalem detayda kaybolmaz ve resmî kart kalemleri toplamı kayıtlı banka matrahını aşmaz.
- Bu öncelik sırası yalnız `BordroPersonelModel::getMuhasebeResmiKalemDagilimi()` metodunda tanımlanır. Bordro detay modalı ve Muhasebe Excel'i bu metodun sonucunu kullanır; görünüm dosyalarında ikinci bir resmî dağıtım hesabı kurulamaz.
- Açılır panel başlıklarındaki tutarlar kesinti sonrası ödeme değildir: **Sözleşme Alacağı Hesabı** başlığı resmî + gayriresmî kesinti öncesi toplam hakedişi, **Resmî Alacağı Hesabı** başlığı kesinti öncesi resmî hakedişi gösterir. Kesintiler uygulandıktan sonra oluşan net banka ve toplam net ödeme panel/footer içinde ayrıca gösterilir.

- Banka ödeme detayındaki **Asgari Ücret (Net)** satırı, net banka sonucundan kalan tutar değildir. Her zaman dönem parametresindeki günlük net asgari ücret ile bankaya esas hak edilmiş günün çarpımıdır: `round(asgari_ucret_net / 30, 2) × banka_asgari_gun`.
- Yemek, eş yardımı, RTÇ/HTÇ ve diğer banka kalemleri asgari tabandan ayrı olarak **Eklenenler** bölümünde yeşil `+` tutarlarla gösterilir.
- Maaşa dahil yemek havuzunu büyüten birden fazla ek kazanç bulunduğunda banka detayındaki açıklama dağılımı resmî niteliği korur: `resmi_alacagina_dahil = 1` kalemlerin yemek tavanı dışında kalan doğrudan banka payı önce kendi adına yazılır; resmî olmayan araç kirası gibi kazançlar yalnız yemek tavanını dolduran kısımlarıyla “Yemek yardımına dahil” bilgisi altında gösterilir. Dizi/kayıt sırası bir kalemi yapay olarak bölüp resmî alacak gibi gösteremez.
- Eski detaydaki Resmî Alacak kartında **Yemek Yardımı** satırı açılmaz; günlük tutar × fiilî gün bilgisi ana satırda gösterilir. Fazla Mesai/Hafta İçi Nöbet ve HTÇ satırları açılabilir; alt satırda sırasıyla toplam saat veya HTÇ günü ile birim tutar formülü gösterilir.
- Sözleşme hakedişi ana satırda tam tutarıyla gösterildiğinde, bu tutarın asgari taban ve yardımlar dışındaki teknik bakiyesi ayrı `Sözleşme Farkı` satırı olarak çizilmez. Bu teknik ayrım toplam hakedişe ikinci bir kazanç gibi sunulamaz.
- Prim usulü personelde sabit sözleşme maaşı bulunmuyorsa `0,00 × gün` sözleşme formülü gösterilmez. Sözleşme tarafındaki **Puantaj Hakedişi**, snapshot'ta bulunan `[Puantaj]`, `[Sayaç]` ve ilgili puantaj kaynaklarının gerçek hedef net toplamıdır; kanuni asgari tabana tamamlanmaz. Puantaj kaydı yoksa bu satır `0,00` gösterilir. Kanuni taban yalnız resmî tarafta **Asgari Ücret (Net)** olarak gösterilir. Sözleşme kartı toplamı gerçek puantaj hakedişi ile sözleşme kaynaklı ek kazançların toplamıdır. Bu ayrım kayıtlı hesaplama ve ödeme kanallarını değiştirmez. Sabit günlük ücrete bağlı olup hesaplanan tutarı sıfır olan HTÇ gibi sözleşme kalemleri çizilmez. Sıfır kesinti tutarları `-0,00` yerine `0,00` gösterilir.
- Avans, icra ve diğer banka mahsupları **Çıkarılanlar** bölümünde kendi adlarıyla kırmızı `-` tutarlarla gösterilir. Kalem detayı bulunmayan eski snapshot kayıtlarında kalan tutar `Diğer banka kesintileri` adıyla gösterilir.
- Kesinti kaydında açıklama varsa, özellikle **Diğer Kesinti** kaleminde, açıklama kesinti adının altında gösterilir; teknik etiket açıklamanın yerine geçmez.
- Gösterilen eklenen ve çıkarılan kalemler `Net Banka Ödemesi` ile kuruş bazında uzlaşır; banka/hakediş tavanı nedeniyle kırpılan bir tutar varsa ayrıca kırmızı bir sınır kalemi olarak açıklanır.
- Bordro detayındaki **Fazla Çalışmalar** kartının ana satırları personele hak edilen **net** tutarı gösterir. Üzerine gelindiğinde hedef net, SGK/işsizlik/vergi kesintileri ve resmî brüt tutar ayrı satırlarda gösterilir. Böylece brüt resmî matrah, personele ödenecek net kazanç gibi sunulmaz.
- Net maaşlı personelin **Normal Kazançlar** kartı sözleşme ücretini günle çarpıp asgari taban gibi göstermez. Ana normal çalışma tutarı `günlük net asgari ücret × maaş hesap günü` olur; sözleşme ücretinden, sosyal yardımlardan ve diğer kazançlardan kaynaklanan tamamlayıcı bölüm ayrı **Sözleşme Farkı** satırında gösterilir. Aynı maaş türü/tutarıyla birden fazla görev geçmişi parçası bulunması bu gösterim kuralını değiştirmez.
- Hesaplanmış bordro snapshot'ındaki `kesintiler` kalemleri banka detayında ödeme kanalıyla birlikte korunur. Böylece kayıtlı avans/icra, yapay bir `Banka / hakediş sınırı` farkına dönüştürülmez.
- Detay modalı footer'ındaki **Brüt Toplamı** kutusu hesap sonucunu tek başına göstermez; hover açılımında sözleşme hakedişi (`sözleşme günlük ücreti × maaş hesap günü`), sözleşme üzerine eklenen HTÇ hakedişi (`sözleşme günlük ücreti × HTÇ günü`) ve varsa diğer kazançlar ayrı satırlarda gösterilir. Açılımdaki kalemler footer toplamıyla kuruş bazında eşit olmalıdır. Fazla Çalışmalar kartındaki resmî net gösterim bu nedenle değiştirilmez.

## Gun Hesaplama Kurali

Maas gunu `getMaasHesapGunu()` ile hesaplanir.

- Personel donem boyunca aktif ve eksik gun yoksa `maasHesapGunu = 30`.
- Personel donem boyunca aktif ama eksik gun varsa:

```text
maasHesapGunu = donemTakvimGunu - ucretsizIzinGunu - raporGunu
```

- Personel ay ortasinda giris/cikis yaptiysa:

```text
maasHesapGunu = aktifTakvimGun - ucretsizIzinGunu - raporGunu
```

- Ucretli izin ve genel tatil maas gununu azaltmaz.
- Gorev gecmisi varsa aktif gun, donem icindeki gecerli gorev gecmisi gunleriyle sinirlanir.

## Maas Turleri

### Brut Maas

Brut maasta SGK, issizlik, gelir vergisi ve damga vergisi hesaplanir.
Eger personelin brut maas tutari tanimsiz veya 0 ise (`maas_tutari <= 0`), calisilan gun varsa ilgili donemin `asgari_ucret_brut` degeri nominal maas olarak baz alinir.

```text
netMaas = brutMaas
        - sgkIsci
        - issizlikIsci
        - gelirVergisi
        - damgaVergisi
        + netEkOdemeler
        + brutEkOdemeler
        - (digerKesintiler - icraKesintisi)
```

### Net Maas

Net maasta vergi/SGK kesintileri hesaplamaya dahil edilmez.
Eger personelin net maas tutari tanimsiz veya 0 ise (`maas_tutari <= 0`), calisilan gun varsa ilgili donemin `asgari_ucret_net` degeri sozlesme maasi olarak baz alinir.

```text
netMaas = brutMaas + toplamEkOdeme - (toplamKesinti - icraKesintisi)
```

### Net Maaşlı Personelde Yemek / Eş Yardımı Olmadığında Dağıtım Kuralı

Maaşa dahil yemek veya eş yardımı tanımlanmamış (`yemek_yardimi_dahil = 0` ve `es_yardimi_dahil = 0`) net maaşlı personellerde:
1. Resmî olarak bankaya yatacak taban tutar, çalışılan güne orantılı **asgari ücret net hakedişidir** (`asgariUcretYatacak = (asgariUcretNet / 30) * calismaGunu`) + varsa bankadan ödenecek ek ödemelerdir.
2. Personelin sözleşme net maaşını tamamlayan geri kalan bakiye tutar (`netAlacagi - bankaOdemesi`), **Elden Ödeme** olarak dağıtılır.
3. Bu kural `hesaplaMaas`, `hesaplaOrtakGosterimDegerleri`, bordro detay modalı ve banka export raporlarında birebir aynı şekilde geçerlidir.

### Puantaj Hakedisi Kurali

`personel.puantaj_hakedis_dahil = 1` ise puantaj/sayac/kacak kontrol calismalari sozlesme netinin
uzerine eklenir ve **tamami resmi kanaldan bildirilir**:

### Puantaj ve Ek Ödeme Kazançlarının Maaşa Dahil Yemek Yardımı Entegrasyonu
Net maaşlı ve maaşa dahil yemek yardımı alan personelde üretilen puantaj kazançları ve "Banka Matrahına Ekle" seçilen ek ödemeler:
1. Personelin toplam hedef hakedişine eklenir (`hedefHakedis = sozlesmeHakedisi + puantajToplami + bankaEkOdemeleri`).
2. Yemek havuzu bu toplam hedef hakedişe göre genişletilir ve **öncelikle personelin günlük yemek tavanını (ör. 300 TL / gün) doldurur**.
3. Günlük yemek tavanına kadar olan kısım yemek yardımı olarak **bankadan ödenir**.
4. Yemek tavanını aşan bakiye tutar ise **elden ödeme** olarak personele yansıtılır.

Fazla mesai, prim/ikramiye, nöbet ve diğer sözleşme dışı kazançlar toplam hakedişi yalnız bir kez artırır. Maaşa dahil yardım hesabında bir kazanç yemek kapasitesini yükseltiyorsa aynı tutar ayrıca ikinci kez banka eki olarak toplam hakedişe eklenemez. Ödeme kanalı toplam hakedişi değiştirmez. Parametresinde `resmi_alacagina_dahil = 1` olan prim/ikramiye resmî banka dağılımında yer alır; resmî olmayan primlerde yemek tavanı ve kalan elden kuralı devam eder.

Muhasebelestirme sirasi sabittir — once yemek, kalan puantaj kalemi olarak:

```text
1) asgariHakedis                                   -> banka
2) yemek yardimi (gunluk limit dahilinde)          -> banka
   yemekTavanHedefi = sozlesmeHakedisi + puantajHakedisi
3) yemege sigmayan puantaj bakiyesi                -> elden

yemekSozlesmePayi = max(0, sozlesmeHakedisi + htcHamTutar - asgariHakedis - esYardimi - rtcHtcBankaNeti)
puantajYemekPayi  = max(0, yemekYardimiToplam - yemekSozlesmePayi)
puantajEldenKalani = puantajHakedisi - puantajYemekPayi
```

Banka hakediş tavanı asgari net, eş yardımı, günlük yemek tavanına kadar olan yemek yardımı ve
diğer doğal resmî banka kalemlerinden oluşur. Puantaj kazancının bu kapasiteyi aşan bölümü elden
kalır. Kesintiler önce resmî banka tavanından düşülür; banka tavanını aşan kesinti kalırsa yalnızca
bu bakiye elden tutardan mahsup edilir.

Bu sıra yalnızca puantajlı personele özgü değildir; maaşa dahil yemek/eş yardımı bulunan bütün
otomatik dağıtımlarda uygulanır. Önceden oluşmuş elden bakiye, banka matrahı kesintiyi karşılayabildiği
sürece korunur. Örnek: toplam hakediş 37.300, resmi banka matrahı 37.111,35 ve kesinti 15.000 ise
banka 22.111,35; elden 188,65; toplam ödeme 22.300 olur. `elden_tutardan` kesintiler bu sıranın
dışındadır ve yalnızca elden bakiyeye uygulanır.

`puantaj_hakedis_dahil = 0` ise `[Puantaj]`, `[Sayaç]` ve `[Kaçak Kontrol]` ek odemeleri hic
olusturulmaz (`olusturPuantajOdemeleri()`, `olusturSayacDegisimOdemeleri()`,
`olusturKacakKontrolPrimleri()`).

Kural: Puantaj hakedisi `hesaplaMaasaDahilYardimDagilimi()` cagrisina **yalnizca `$puantajToplami`
parametresinden** girer. `$bankayaTasinabilirEkOdeme` icinden ayristirilmalidir; aksi halde yemek
matrahinda iki kez sayilir.

### Prim Usulu

Maaşa dahil yemek profili etkin prim usulü personelde yemek hedefi
`max(puantaj hakedişi, asgari net taban) + banka işaretli uygun ek kazanç`
üzerinden kurulur. Yol yardımı gibi kazançlar son yemek tavanında dışlanamaz.
Yemek bir ödeme dağılımıdır; aynı ek kazanç toplam hakedişe ikinci kez eklenmez.

Prim usulu net gibi islenir. Varsayilan ek odeme kanali `elden` kabul edilir; parametrede odeme yontemi varsa o yontem kullanilir.

- Prim usulunde kanuni asgari net taban yalniz normal puantaj/calisma hakedisini tamamlar. Fazla mesai, nobet, resmi tatil ve hafta tatili calismasi gibi ek calisma kazanclari bu tabanin icinde eritilemez; `max(normal puantaj hakedisi, gunluk asgari net taban) + ek calisma kazanclari` sirasi ile toplam hakedise eklenir.
- Ornek: 5 gunluk asgari net taban 4.679,25; normal puantaj hakedisi 4.120,00 ve nobet 500,00 ise toplam net hakedis `max(4.120,00, 4.679,25) + 500,00 = 5.179,25` olur.
- Aynı sıra maaşa dahil yemek/eş yardımı profili bulunan prim usulü personelde de değişmez. Dahil yardım tabanı ile normal puantajın büyüğü belirlendikten sonra nöbet ve diğer ek çalışma kazançları ayrıca eklenir; `max(puantaj + nöbet, asgari taban)` biçiminde nöbet tabana eritilemez.
Personelin prim usulu calismasi olsa bile donem icinde calisma gunu varsa (`maasHesapGunu > 0`), puantaj veya ek odeme uretilmemis ya da asgari tabandan dusuk kalmis olsa dahi personelin hakedisi en az calisilan gune tekabul eden `asgariHakedis` (`asgari_ucret_net / 30 * maasHesapGunu`) tutarindan az olamaz.

### Banka İşaretli Prim ve Özel Kesinti Sırası

- Kayıtlı bordro snapshot'ındaki `banka_matrahina_ekle` alanı yalnız ödeme kanalı tercihidir; bir kalemi tek başına resmî alacağın parçası yapmaz. Detay dağılımında kalem ancak snapshot'ta `resmi_alacagina_dahil = 1` ise veya yemek, eş yardımı, fazla mesai, resmî tatil/hafta tatili çalışması gibi doğal resmî türlerden biriyse ayrı resmî kalem olarak gösterilir. Teknik `yemek_yardimi_dengeleme` kaydı, zaten Yemek Yardımı toplamında bulunduğundan ikinci bir resmî kalem olarak çizilmez. Açıklanamayan uzlaştırma bakiyesi resmî olmayan nöbet ya da teknik dengeleme etiketiyle ilişkilendirilmez.

- Kaçak ihbar primi genel `prim` türüyle değil, ayrı `kacak_ihbar_primi` bordro parametresiyle otomatik oluşturulur. Bu parametrenin banka matrahı, ödeme yöntemi ve resmî alacak seçimleri standart parametre akışından okunur; açıklama metnine bağlı hesap istisnası kullanılmaz. Maaşa dahil sosyal yardım alan personelde kaçak ihbar primi ve genel primler öncelikle personelin yemek tavanını doldurur; yemek tavanını aşan bakiye tutar gayriresmî prim niteliğinde olup banka resmî alacağına eklenemez, Kural 5 gereği doğrudan elden ödemeye aktarılır.
- `[Kaçak İhbar Primi]` dönem eşleştirmesinde ihbar tarihçesindeki son olumlu sonuçlanma tarihi esas alınır. İhbarın oluşturulma tarihi dönemi belirlemez; hesaplama anındaki güncel durumu `olumlu` olan kayıt, olumlu sonuçlandığı bordro döneminde sayılır.
- Maaşa dahil sosyal yardım olmayan net, prim usulü ve karma maaşlarda banka matrahı önce asgari net hakediş + banka kanallı ek ödemeler olarak kurulur. Prim türü olması banka seçimini geçersiz kılamaz.
- Ardından avans/özel kesinti gibi personel kesintileri bu banka matrahından düşülür. Bankayı aşan kesinti bakiyesi elden ödemeye yansır. İcra toplam kesintinin içindeyse ikinci kez düşülmez; yasal vergi/SGK hesabı değişmez.
- Açıkça `elden_tutardan` tanımlanan kesintiler yalnızca elden tutara uygulanır. Manuel dağıtım korunur.
- Maaşa dahil primlerin yemek havuzuna aktarılması, günlük limit ve yuvarlama hesabı korunur; yemek tavanı dışındaki bakiye elden dağıtılır, ayrıca banka kalemi eklenerek çift sayılmaz veya muhasebeye bildirilen banka matrahı şişirilmez.
- Örnek: toplam hakediş 29.795,00; asgari banka tabanı 26.203,80; banka işaretli prim 600,00; diğer banka eki yok; özel kesinti 500,00 ise banka **26.303,80**, elden **2.991,20**, net **29.295,00** olur.
- Liste, detay ve banka Excel/raporu ortak gösterim hesabını; kayıt hesabı da aynı banka dağıtım yardımcısını kullanır.
- Toplu bordro hesaplamasında her personelin otomatik oluşturulan kesintileri, o personelin maaş hesabına geçilmeden önce güncel veritabanı kaydından okunur. İlk personelde oluşturulan dönem önbelleği sonraki personellerin icra kesintilerini gizleyemez; tekil ve toplu hesaplama aynı icra sonucunu üretir.
- Aynı dönemde sabit maaş ve prim usulü görev geçmişi bulunması, maaşa dahil yemek yardımını tek başına kapatmaz. Dönemde personelin seçili yemek koduna ait tek geçerli parametre varsa yemek yardımı karma maaşta da hesaplanır. Otomatik dahil yardım dağıtımı yalnızca aynı bordro dönemine birden fazla geçerli yemek parametresi denk geldiğinde kapatılır. Liste–detay–kayıt hesapları aynı kontrolü kullanır.

## Maasa Dahil Sosyal Yardim Kurali

`yemek_yardimi_dahil = 1` veya `es_yardimi_dahil = 1` ise personel maasa dahil sosyal yardim modundadir.

Bu modda maas hesaplamasinin tabani asgari net ucrettir, hedef ise personelin sozlesme net maasidir.

```text
asgariHakedis = (asgari_ucret_net / 30) * maasHesapGunu
hedefHakedis = (hedef_net_maas_tutari / 30) * maasHesapGunu
yemekUstLimiti = max(0, hedefHakedis - asgariHakedis - esYardimi - resmiRtcNet - resmiFazlaMesaiNet + htcNetFazla)
```

Yemek yardimi bu üst limitten hesaplanir. RTÇ'nin banka üzerinden ödenen resmî neti sözleşme netinin
bileşenidir; yemek yardımını veya toplam hakedişi sözleşme netinin üzerine çıkaramaz
(istisna: asagidaki RTÇ/HTÇ taban yukseltme kurali). HTÇ bu kuralin disindadir — bkz. HTÇ Kurali.

Sistem günlük tutarı yukarı yuvarlar, sonra günlük limitle sınırlar. Bu sıra değiştirilemez:

```text
fiiliGunSayisi = puantajdaki X gunleri
fiiliGunSayisi yoksa maasHesapGunu kullanilir

yemekGunlukHam = yemekUstLimiti / fiiliGunSayisi
yemekGunluk = ceil(yemekGunlukHam)
yemekGunluk = min(yemekGunluk, yemekYardimiGunlukLimit)
yemekYardimiToplam = yemekGunluk * fiiliGunSayisi
yuvarlamaFarki = yemekYardimiToplam - yemekUstLimiti
```

Kural: Yemek yardiminin gunluk tutari, personel veya parametre uzerinden bulunan gunluk yemek limitini asamaz.

Kural: `yuvarlamaFarki` dışında banka limit matrahı sözleşme netini aşamaz. Yemek yardımı yüksek hesaplanıp sonradan banka ödemesinden fark kesintisi düşülemez.

### HTÇ (Hafta Tatili Calismasi) Kurali

HTÇ, personelin kendi gunluk ucretinden hesaplanir ve **sozlesme netinin uzerine eklenir**.
Bu kural hem maasa dahil hem de maasa dahil olmayan personelde gecerlidir.

Nöbet modülündeki onaylı `hafta_sonu` nöbeti aynı tarih için bir HTÇ günü kaynağıdır.
Aynı gün puantajda ayrıca HTÇ kodu bulunuyorsa gün yalnız bir kez sayılır. Silinmiş,
iptal/reddedilmiş, talep aşamasındaki veya yönetici onayı bulunmayan hafta sonu nöbeti
HTÇ üretmez. Hafta sonu nöbetinin sözleşme nöbet ödemesi ile HTÇ hakedişi ayrı
kalemlerdir; liste, detay, Excel ve bordro snapshot hesabı aynı birleşik gün kümesini kullanır.

Okuma personelinde pazar gunu HTÇ puantaji, firma ayarindaki kisi basi minimum
okuma sayisi (varsayilan 10) saglandiginda endeks cron'u tarafindan otomatik
uretilebilir. Varsayilan veya eski `HT` kaydi `HTÇ` olarak donusturulur; HT disinda
manuel kod bulunan gun degistirilmez. Otomatik HTÇ kullanici tarafindan baska bir
koda cevrildiginde manuel tercih kilitlenir ve sonraki cron tarafindan ezilmez.
Kacak personelinde kisi basi 2, sokme-takma ve muhurleme personelinde kisi basi
10 pazar islemi ayni HTÇ kuralini tetikler. Bu limitler firma ayarlarindan
degistirilebilir; personelin operasyonlardan en az birinin limitini saglamasi yeterlidir.
Bu uretim kurali yalniz puantaj gununu belirler; asagidaki HTÇ tutar ve odeme
dagilimi formullerini degistirmez.

Fazla mesai, hafta ici nobet, resmi tatil calismasi ve hafta tatili calismasinin
muhasebeye bildirilecek resmi netleri ayri banka kalemidir. Personelin gercek ek
calisma hakedisinin resmi net hedefini asan kismi, maasa dahil yardim kuralina gore
once gunluk yemek tavanini doldurur; yalniz yemek tavanini asan bakiye elden kalir.
Bu kural liste, detay, Excel ve kayit hesabinda ayni ortak dagilimdan uygulanir.

Hesaplanmis eski snapshot'larda kayitli banka/elden kanallari ve bunlarin kayitli
kalem dagilimi degistirilmez. Yeni muhasebe hedefi eski satirin yerine gosterilmez;
parasal dagilim ancak donem yetkili bicimde acilip bordro yeniden hesaplandiginda
yeni kurala gore degisir.

```text
htcHamTutar = (maas_tutari / 30) * htcGun          (personelin kendi gunlugu)
htcResmiNet = (asgari_ucret_net / 30) * htcGun     (bankaya yatan, gross-up edilen kisim)

toplamHakedis = sozlesmeHakedisi + hariciEkOdeme + yuvarlamaFarki    (hariciEkOdeme HTÇ'yi icerir)
```

HTÇ **banka matrahini da yukseltir**. Iki kanaldan girer:

- Resmi neti (`asgari_ucret_net / 30 * htcGun`) dogrudan banka kalemi olarak `yontemliBankaEki`'ne,
- Resmi neti asan fazlasi (`htcNetFazla`) once yemek havuzuna eklenir; gunluk yemek tavanini asan bakiye elden odemede kalir.

Banka matrahi HTÇ resmi neti kadar buyur; HTÇ'nin resmi neti asan kismi yemek tavanini doldurabilir:

```text
yemekIcinKalanSozlesmeLimiti = max(0, yemekTavanHedefi - asgariHakedis - esYardimi)
```

HTÇ'nin resmi neti bu limitin disinda bankaya eklenir; resmi neti asan personel hakedisi
once yemek kapasitesine, kapasite doluysa elden odemeye gider.
Elden bakiye bulunduğu sürece HTÇ resmî neti "bankada kalan boşluk" kadar kırpılmaz;
gerekli tutar elden bakiyeden bankaya aktarılır ve toplam net hakediş değişmez.

Ayrica `resmiDahilEkToplam` asgari **net** uzerinden biriktigi icin, ondan dusulen
`htcResmiTutarDagilim` de asgari **net** olmalidir (brut kullanilirsa RTÇ + HTÇ birlikteyken
yemek matrahi gun basina ~165 ₺ sapar).

Uyari: Bu davranis `e93a20bd` commit'inde kaybolmus (`+ $htcEkOdeme` ve `$hariciEkOdeme` terimleri
toplamdan cikarilmisti), sonradan geri alinmistir. Toplam hakedis formullerinden HTÇ terimi
cikarilmamalidir.

Ornek (sozlesme neti 33.000 ₺, tam ay, HTÇ = 1 gun):

| Kalem | HTÇ yok | HTÇ = 1 gun |
| --- | --- | --- |
| Sozlesme hakedisi | 33.000,00 | 33.000,00 |
| HTÇ ham (33.000/30 x 1) | – | +1.100,00 |
| HTÇ resmi neti (banka kalemi) | – | 935,85 |
| Yemek yardimi | 4.950,00 | 5.100,00 |
| **Banka odemesi** | **33.025,50** | **34.111,35** |
| Elden odeme | 0,00 | 0,00 |
| **Toplam hakedis** | **33.025,50** | **34.111,35** |

HTÇ'nin tamami bankadan odenir; yemek yardimi azalmaz, aksine `htcNetFazla` kadar artar.

### RTÇ/HTÇ Taban Yukseltme Kurali

Az calisilan donemlerde `asgariHakedis + rtcHtcBankaNeti` toplami sozlesme hakedisini asabilir.
Bu durumda banka matrahi sozlesme netine kirpilir ve RTÇ/HTÇ fiilen odenmemis olur.
Net ucretli ve maasa dahil sosyal yardim modundaki personelde sozlesme hakedisi bu tabana yukseltilir:

```text
rtcHtcBankaNeti           = rtcNet + htcNet     (gross-up sonrasi bankaya yatan NET)
bankaKarsilanabilirEkOdeme = elden zorunlu olmayan ek odemeler (HTÇ ham tutari dahil, puantaj haric)

sozlesmeHakedisi = max(
    sozlesmeHakedisi,
    (asgari_ucret_net / 30) * maasHesapGunu + rtcHtcBankaNeti - bankaKarsilanabilirEkOdeme
)
```

`bankaKarsilanabilirEkOdeme` mahsubu zorunludur: HTÇ zaten sozlesme netinin uzerine eklendigi icin
banka tabanini kendi basina karsilar. Mahsup yapilmazsa ayni gun hem HTÇ olarak hem de taban
yukseltmesi olarak iki kez odenir. Puantaj hakedisi elden odendigi icin bu mahsuba girmez.

Yukseltme, yemek/es dagilimi ve banka matrahi hesaplanmadan once uygulanir; yukseltilmis deger
`hesaplaMaasaDahilYardimDagilimi()` cagrisina, `yemekTavanHedefi` degerine ve banka hakedis tavanina
birlikte gider. Bu sayede `yuvarlamaFarki` disinda banka limit matrahi sozlesme netini asamaz kurali
bozulmadan gecerli kalir.

Kapsam ve sinirlar:

- Yalnizca net ucretli (`isNet`) ve maasa dahil sosyal yardim modundaki personelde uygulanir.
- Brut, prim usulu ve karisik maas (`karisikMaasOzeti`) hesaplarinda sozlesme hakedisi degismez.
- RTÇ/HTÇ gun sayisi yoksa (`rtcHtcBankaNeti = 0`) kural devreye girmez.
- Tam ay calisan personelde `asgariHakedis + rtcHtcBankaNeti` sozlesme netinin altinda kaldigi
  icin kural devreye girmez; mevcut sonuclar degismez.
- RTÇ/HTÇ tutari degismez; kural sadece hakedis tabanini yukseltir.
- HTÇ tek basinayken kural genelde devreye girmez; HTÇ ham tutari tabani zaten karsilar.

Banka hakedis tavani da ayni mantikla kurulur (net + puantaj hâlinde):

```text
bankaHakedisTavani = sozlesmeHakedisi + yuvarlamaFarki + bankaKarsilanabilirEkOdeme
```

Ornek (sozlesme neti 33.000 ₺, maasHesapGunu = 2, RTÇ = 1 gun, puantaj = 430 ₺):

| Kalem | Once | Sonra |
| --- | --- | --- |
| Sozlesme hakedisi | 2.200,00 | 2.807,55 |
| Asgari taban (2 gun) | 1.871,70 | 1.871,70 |
| RTÇ resmi neti (1 gun) | 935,85 | 935,85 |
| Banka limit matrahi (ham) | 2.807,55 | 2.807,55 |
| Banka odemesi | 2.200,00 (kirpildi) | 2.807,55 |
| Elden odeme (puantaj) | 430,00 | 430,00 |
| Toplam hakedis | 2.630,00 | 3.237,55 |

Detay modalindaki "Banka Limit Matrahi" satiri, kirpma sonrasi gercek banka matrahini gosterir.
Kirpma varsa ayrica "Sozlesme Neti Siniri" satiri ile dusulen fark yazilir.

Gunluk limit secimi:

- Personel kartinda ozel yemek tutari varsa dikkate alinir.
- Personelde yemek parametresi seciliyse parametrenin varsayilan tutari dikkate alinir.
- Global `yemek_yardimi_tum` veya `yemek` parametresi varsa dikkate alinir.
- Kod mevcut durumda en yuksek limiti secer. Is kuralı degisecekse bu dosya once guncellenmelidir.

Es yardimi varsa, yemek limitinden sonra kalan farktan hesaplanir.

Yardım önceliği değiştirilemez: sözleşme bakiyesinden önce günlük tavanına kadar yemek yardımı hesaplanır ve günlük yuvarlama tamamlanır; eş yardımı yalnızca yuvarlanmış yemek yardımından sonra hâlâ kalan bakiye üzerinden hesaplanır. Yemek kapasitesi belirlenirken eş yardımı peşinen düşülemez.

```text
esYardimiToplam = min(kalanFark, esYardimiAylikLimit)
```

Yemek yardımındaki `fiiliGunSayisi` yalnız puantajdaki `X` günlerinin birleşik sayısıdır. Personelin aynı bordro döneminde birden fazla çalışma geçmişi aralığı varsa bütün aralıklar birlikte değerlendirilir; yalnız son çalışma aralığına daraltılmaz. `HT`, `HTÇ`, `RTÇ` ve izin günleri yemek günü sayısına eklenmez.

## Maasa Dahil Odeme Dagilimi

Maasa dahil sosyal yardim modunda otomatik dagilim:

```text
bankaOdemesi = asgariHakedis + yemekYardimiToplam + esYardimiToplam
sodexoOdemesi = 0
eldenOdeme = max(0, netMaas - bankaOdemesi)
```

Kesinti varsa hedef hakedis once kesintilerle birlikte degerlendirilir. Kalan net hakedis asgari hakedisi karsilamiyorsa banka odemesi kalan net hakedisi asamaz.

```text
kalanNetHakedis = max(0, hedefHakedis - toplamKesinti)

if kalanNetHakedis >= asgariHakedis:
    bankaOdemesi = asgariHakedis + yemekYardimiToplam + esYardimiToplam
else:
    bankaOdemesi = kalanNetHakedis
```

Bu modda Sodexo otomatik olarak sifirlanir. Yemek maasa dahil oldugu icin odeme banka kanalina eklenir.

## Normal Odeme Dagilimi

Maasa dahil sosyal yardim yoksa:

```text
sodexoOdemesi = (personel.sodexo / 30) * fiiliCalismaGunu + sodexo kanalli ek odemeler
bankaBaz = max(0, netMaas - sodexoOdemesi)
bankaOdemesi = max(0, bankaBaz - icraKesintisi)
eldenOdeme = max(0, netMaas - bankaOdemesi - sodexoOdemesi - icraKesintisi - digerOdeme)
```

Prim usulunde banka icin asgari net taban dikkate alinir: ancak alacağa dahil edilmez

```text
bankaYatacakMinimum = (asgari_ucret_net / 30) * fiiliCalismaGunu
bankaMatrahi = bankaYatacakMinimum + banka kanalli ek odemeler
bankaOdemesi = min(max(0, bankaMatrahi - toplamPersonelKesintisi),
                   max(0, netAlacagi - sodexoOdemesi - digerOdeme))
# toplamPersonelKesintisi icrayi bir kez icerir; elden_tutardan harictir.
```

## Manuel Dagilim Kurali

`dagitim_manuel = 1` ise kullanicinin girdigi banka, Sodexo ve diger odeme degerleri korunur.

```text
eldenOdeme = max(0, netMaas - bankaOdemesi - sodexoOdemesi - icraKesintisi - digerOdeme)
```

Manuel dagilimda otomatik banka/Sodexo duzeltmesi yapilmaz.

## Hesaplanmış Bordronun Gösterim Kaynağı

Açık veya kapalı dönem ayrımı yapılmadan, `hesaplama_tarihi` ve geçerli `hesaplama_detay` snapshot'ı bulunan
bordro satırları liste, detay, özet, Excel ve ödeme raporlarında kayıtlı `bordro_personel` değerlerinden gösterilir.
Personel, puantaj, parametre veya hesaplama kodundaki sonraki değişiklikler kayıtlı bordronun görünümünü kendiliğinden
değiştiremez. Yeni değerler yalnızca kullanıcı bordroyu yeniden hesapladığında kaydedilir ve gösterime girer.

Kayıtlı hakediş, kesinti ve ödeme kanalları değişmez parasal sonuçtur. Özellikle `banka_odemesi`, `elden_odeme`,
`sodexo_odemesi` ve `diger_odeme` değerlerine gösterim sırasında tekrar banka tavanı uygulanmaz; tutarlar banka ile
elden arasında yeniden dağıtılmaz. Bu zorunluluk liste, özet, detay modalı, Excel, banka/ödeme raporları ve bordro
yayını için aynıdır.

Eski snapshot sürümlerinde bulunmayan açıklayıcı RTÇ/HTÇ gibi kırılımlar dönem kaynağından tamamlanabilir. Bu geriye
uyumluluk yalnız açıklama ve mutabakat kırılımı içindir; kayıtlı toplam hakedişi, net alacağı, kesintiyi veya ödeme
kanalı tutarlarını değiştiremez. Yeni hesaplama kuralları geçmiş bordroya ancak kullanıcı dönemi yetkili biçimde açıp
bordroyu yeniden hesapladığında uygulanır.

Yeni hesaplamalarda snapshot, kullanılan dönemsel görev/maaş geçmişi kayıtlarının ID, tarih aralığı, maaş türü ve
maaş tutarını `kaynaklar.gorev_maas_gecmisi` altında; kayıtlı ödeme kanalları mutabakatını da `mutabakat` altında taşır.
Dönem kapatılırken hesaplanmamış/geçersiz snapshot, dönemsel maaş bulunduğu halde sıfır veya farklı nominal maaş,
snapshot ile kayıtlı ödeme kanalı farkı ya da `banka + elden + Sodexo + diğer != net maaş - icra` durumu bloklayıcı
hata sayılır. Bu mali hatalar `force_close` ile geçilemez.

Başarılı her dönem kapanışı ayrıca değişmez kapanış arşivi üretir. Arşiv kişi bazlı tam `bordro_personel` parasal
alanlarını ve `hesaplama_detay` snapshot'ını saklar; her kişi dokümanı SHA-256 ile, dönem manifesti de ayrı SHA-256
ile mühürlenir. Sonraki kapanış sürümü önceki manifest hash'ini taşıyarak sürüm zinciri oluşturur. Dönemin yeniden
açılması veya yeniden hesaplanması önceki arşiv sürümünü güncellemez/silmez. Arşiv ve dönem kapatma aynı transaction
içindedir; arşiv yazılamazsa dönem kapanamaz.

`Geçmiş Bordro Arşivi (ZIP)` aktarımında kullanıcı bir veya birden fazla dönemi seçer ve yalnız seçilen dönemler dışa verilir. Mühürlü kapanış sürümleri ile arşiv sistemi
öncesindeki eski/mühürsüz kayıtlar açıkça ayrılır. Excel; arşiv özeti, personel bordroları, ek ödeme, kesinti, ödeme
mutabakatı, maaş kaynakları ve doğrulama sayfalarını içerir. ZIP içindeki `SHA256SUMS.txt`, Excel dosyasının indirme
anındaki SHA-256 değerini taşır. Kullanıcı kaynaklı hücre metinleri Excel formül enjeksiyonuna karşı güvenli yazılır.

`Maaş Bordroları` sayfası bordro/personel/dönem/arşiv kimliklerini, bütün yasal matrah ve kesintileri, işveren
maliyetlerini, hakedişi ve kayıtlı ödeme kanallarını geri yükleme denetimine uygun ayrıntıda taşır. Excel raporlama
katmanıdır; kayıpsız geri yükleme kaynağı ZIP içindeki `geri_yukleme/manifest.json` ile kişi bazlı tam snapshot JSON
dosyalarıdır. Bu dosyaların tamamı `SHA256SUMS.txt` içine alınır. Geri yükleme ancak firma/dönem yetkisi, hash,
mevcut kayıt yedeği ve transaction kontrolleriyle ayrı bir yetkili işlem olarak yürütülebilir.

Snapshot bulunmayan satırlar canlı hesaplanmaz; tutar yerine `Hesaplanmadı` durumu gösterilir ve ödeme/rapor
çıktılarına dahil edilmez.

## Bankaya Yatmayacak Personel Kurali

`sgk_yapilan_firma` alaninda `KUR` geciyorsa otomatik dagilimda banka odemesi sifirlanir ve tutar elden odemeye aktarilir.
`disardan_sigortali = 1` bayrağı personelin modüllerde görünürlüğünü belirler; ödeme kanalını değiştirmez.
Dışarıdan sigortalı personelin hesaplanan veya kaydedilmiş banka ödemesi korunur.

```text
if sgk_yapilan_firma contains "KUR":
    eldenOdeme += bankaOdemesi
    bankaOdemesi = 0

```

## Ek Odeme Kurallari

Ek odemeler parametreye gore islenir.

- `net`: Net maasa direkt eklenir.
- `brut`: Brut ek odeme olarak eklenir; parametreye gore SGK ve/veya gelir vergisi matrahina dahil edilir.
- `kismi_muaf`: Muaf limite kadar net, limiti asan kisim brut/vergili kabul edilir.
- `gunluk_*`: Gunluk tutar ilgili gun sayisiyla carpilir.
- `aylik_gun_*`: Personel ek odeme tutari gunluk tutar gibi kullanilir ve gun sayisiyla carpilir.
- `aylik_fiili_gun_net`: Puantaj fiili gun sayisina gore net hesaplanir.

Kismi muafiyet:

```text
muafLimit = gunlukMuafLimit * gunSayisi
muafKisim = min(tutar, muafLimit)
vergiliKisim = max(0, tutar - muafLimit)
```

## Kesinti Kurallari

## SGK Firmasi Degisimi ve Banka Asgari Ucret Gunu

- Donem icinde ISKUR/KUR ile normal SGK firmasi arasinda gecis varsa banka asgari ucret payi aylik orana gore dagitilmaz; net asgari ucretin gunluk tutari (`asgari_ucret_net / 30`) yalnizca KUR disindaki hak edilmis gunlerle carpilir.
- SGK firmasi ISKUR/KUR olan calisma gecmisi tarihleri maas hesap gunune ve SGK prim gun sayisina dahil edilmez. Bu tarihler icin sozlesme maasi, asgari taban veya isveren SGK gunu uretilmez.
- Personelin ise girisinden onceki ve isten cikisindan sonraki gunler varsayilan SGK firmasina yazilamaz.
- Calisma gecmisi bulunan donemde yalnizca bir calisma gecmisi kaydinin kapsadigi tarihler aktif kabul edilir; kayitlar arasindaki bosluklar firma gunu sayilmaz.
- Onayli ucretsiz izin ve rapor gunleri denk geldikleri SGK firma gununden dusulur. Boylece eksik gunler KUR ve KUR disi gunlere oransal olarak dagitilmaz.
- Liste, bordro detay modali, kaydedilen banka/elden dagilimi ve Excel ciktilari ayni ortak model sonucunu kullanmalidir.

## Resmi Alacak, Yardim Hesap Matrahi ve Odeme Kanali Ayrimi

- Resmi banka alacaginin dogal kalemleri maas/asgari ucret, maasa dahil yemek yardimi, es yardimi, fazla calisma ile resmi tatil/hafta tatili calismasidir.
- Genel bir ek odemede `banka_matrahina_ekle = 1` secimi tek basina odemeyi resmi alacaga donusturmez. Bu secim, maasa dahil yemek/es yardimi kapasitesinin hesabinda ek kazancin dikkate alinmasini saglar.
- Snapshot'ta `elden_tutardan` olarak kaydedilmiş kesinti, kesintiler kartında toplam kesintiye dahil edilir; ancak banka kesintisi veya kesinti öncesi resmî alacak gibi değerlendirilemez. Eski snapshot yedek hesabı yalnız kesinti kırılımı hiç bulunmayan kayıtlarda kullanılabilir.
- Genel ek odemenin kendisinin resmi banka alacagina eklenmesi icin ilgili bordro parametresinde `resmi_alacagina_dahil = 1` olmasi veya kayitta pozitif `resmi_tutar` bulunmasi gerekir.
- Elle eklenen puantaj disi bir odemenin parametresinde `resmi_alacagina_dahil = 1` ise kullanicidan ayrica odeme kanali secmesi beklenmez; sistem bu kalemi otomatik olarak banka kanalinda degerlendirir. Maasa dahil yardimli personelin prim/puantaj kazancinda yemek tavanini asan bakiyenin elden odenmesi kurali saklidir.
- Resmi alacaga dahil olmayan ek odeme toplam net hak edisi arttirir; resmi banka matrahina eklenmeyen bakiye elden odemeye aktarilir.
- Banka odemesi, resmi banka matrahindan banka oncelikli kesintiler dusulerek bulunur ve toplam net hak edisi asamaz. `Elden = odenecek net toplam - banka - kart/Sodexo - diger kanal` mutabakati korunur.

- Avans, ozel kesinti vb. net hakedisten dusulur.
- Icra kesintisi odeme dagiliminda ayrica izlenir.
- Icra kesintisi net hakedisten onceki matrah uzerinden hesaplanir.
- Oranli icra icin varsayilan oran yoksa `%25` kullanilir.
- Icra tutari kalan borcu ve hesaplanan icra butcesini asamaz.

## İcra kesintisi Kuralları
- Personelin icra borcu ve icra kesintisi tutari yasal limitler dahilinde hesaplanir.
- İcra kesintisi, hesaplanan icra borcunu ve hesaplanan icra butcesini asamaz.
- İcra kesintisi, net hakedisten onceki matrah uzerinden hesaplanir.
- Oranli icra icin varsayilan oran yoksa `%25` kullanilir.
- İcra tutari kalan borcu ve hesaplanan icra butcesini asamaz.
- Devam ediyor şeklinde icra kesintisi varsa bir sonraki icraya geçer

## Otomatik Kayit Yenileme Kurali

`hesaplaMaas()` her calismada otomatik uretilen kesinti/ek odeme kayitlarini temizleyip yeniden olusturur.

Otomatik yeniden olusturulan kaynaklar:

- Surekli kesintiler
- Surekli ek odemeler
- Puantaj odemeleri
- Sayac degisim odemeleri
- Nobet odemeleri
- Kacak kontrol primleri
- Avans kesintileri
- Icra kesintileri
- Profil bazli yemek/es yardimi kayitlari

Manuel eklenen kayitlar korunmalidir.

## Degisiklik Yapmadan Once

Bu fonksiyonlarda degisiklik yapmadan once su sorular cevaplanmali:

- Degisiklik hangi maas turunu etkiliyor: brut, net, prim usulu, maasa dahil?
- Degisiklik banka/Sodexo/elden dagilimini etkiliyor mu?
- Yemek yardimi gunluk limitini veya fiili gun kaynagini degistiriyor mu?
- Kesinti sonrasi banka odemesi formulu degisiyor mu?
- Detay modal, liste ve Excel export ayni sonucu gosterecek mi?

## Zorunlu Kontrol

Degisiklikten sonra en az su kontrol calistirilir:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/check-bordro-hesaplama-rules.ps1
powershell -ExecutionPolicy Bypass -File scripts/check-bordro-banka-export-consistency.ps1
php -l app/Model/BordroPersonelModel.php
php -l views/bordro/api.php
```

## Degisiklik Notu Sablonu

```text
Bordro hesaplama etki alani:
- Degisen kural:
- Etkilenen maas turu:
- Etkilenen odeme kanali:
- Yemek/es yardimi etkisi:
- Kesinti/icra etkisi:
- Kontrol edilen ekran/export:
```

### Muhasebe Excel — ortak ödeme özeti ve prim tekrarı

- Hafta içi nöbetin personel hakedişindeki net tutarı ile muhasebeye bildirilecek resmî fazla mesai neti aynı kavram değildir. Personelin nöbet alacağı sözleşme/diğer kazanç tarafında personelin kayıtlı tutarı (`500 x gün` vb.) olarak aynen korunur; personelin sözleşme alacağı resmî rakama çekilerek şişirilmez. Resmî tarafta ise `Fazla Mesai Ücreti (Net)` olarak `asgari_net / 225 × 1,5 × toplam_saat` hedef neti hesaplanır ve banka matrahına aktarılır. Aradaki fark ödeme dağılımında (banka / elden) dengelenir. Bu açıklayıcı kırılım kayıtlı banka/elden/Sodexo/diğer snapshot toplamlarını bozmaz.
- Hesaplanmış bordroda hafta içi nöbet hedef neti, `hesaplama_detay` içindeki ek ödeme açıklaması ve snapshot `asgari_ucret_net` parametresinden üretilir. Güncel personel veya güncel parametre kullanılarak geçmiş parasal sonuç yeniden hesaplanmaz.
- Bordro detayındaki `Fazla Mesai Ücreti (Net)` satırı ve Banka Ödemesi Detayındaki hafta içi nöbet satırı da aynı resmî hedef neti ve `Fazla Mesai Ücreti (Net)` etiketini kullanır. `Diğer Kazançlar / İkramiyeler` altındaki `Nöbet Ödemesi`, personelin gerçek sözleşme hakedişi (örn. 500 x gün) olarak ayrıca korunur.
- Bordro detay modalında (`views/bordro/api.php` Karşılaştırmalı / Eski Detay görünümü), Kesintiler tablosunda her kesinti satırına tıklandığında açılır (collapse) satırlarla `Resmî Tutardan Kesilen` (-X ₺) ve `Elden Kesilen` (-Y ₺) tutarları kuruşu kuruşuna gösterilir.
- Prim usulü veya nominal sözleşme maaşı bulunmayan personelde hafta tatili çalışmasının maaş farkı negatif hesaplanamaz. Resmî hedef net (`asgari_net / 30 × HTÇ günü`) gösterilir; nominal maaş farkı yalnızca pozitifse buna eklenir.
- Banka Ödemesi Detayı, maaşa dahil yemek/eş yardımı bulunup bulunmamasından bağımsız olarak mevcut asgari, yardım, resmî ek ödeme, kesinti ve banka toplamı kırılımlarını gösterir.

- Muhasebe Excel'i yalnızca `Mutabakat` sayfasını içerir. Bu sayfada personel ve TC bilgisinden hemen sonra toplam bordro günü, fiilî gün, izin günü (ücretli + ücretsiz) ve rapor günü; ardından muhasebenin yazacağı resmî kazanç kalemleri, uygulayacağı kesintiler ve bankaya yatacak tutar yer alır. Gün kırılımları yeni kayıtlarda bordro snapshot'ından okunur; eski snapshot'ta eksik açıklayıcı izin kırılımı dönem kaynağından tamamlanebilir ancak kayıtlı parasal sonuç ve ödeme kanalları değiştirilemez. Eski geniş bilgi sayfası, ara banka matrahı/bankadan düşülecek kontrol kolonları, elden ödeme/elden kesinti, kart ve diğer ödeme kanalları aktarılmaz.
- Muhasebeden geri gelen maaş bordrosu karşılaştırmasında `T.Gün`, `Normal Gün`, `Hafta Tatili`, `Ücretli İzin` ve `Rapor` günleri bağımsız alanlardır. Toplam günün eşit olması alt gün kırılımlarındaki farkları kapatmaz. Yeni hesaplama snapshot'ları `normal_gun`, `hafta_tatili_gunu`, `genel_tatil_gunu`, `ucretli_izin_gunu` ve `rapor_gunu` açıklayıcı alanlarını saklar. Eski snapshot'ta hafta tatili bulunmuyorsa kayıtlı toplam günden normal, ücretli izin ve genel tatil günlerinin bakiyesi alınır; bu tamamlama hiçbir parasal sonucu veya ödeme kanalını değiştiremez.
- Hesaplama tarihi dolu olsa da muhasebe Excel ödeme özeti liste ve detayın kullandığı `hesaplaOrtakGosterimDegerleri()` sonucundan alınır. Eski `banka_odemesi` kaydı otomatik dağıtımda güncel hesabın yerine geçmez; manuel dağıtım ortak hesabın kurallarıyla korunur.
- Excel'de kazanç kaynağı ile ödeme kanalı ayrı gösterilir. `Prim / İkramiye Hakedişi (Bilgi)` personelin gerçek prim kaydını, `Yemek/Banka Dağılımına Dahil Prim (Bilgi)` bu primin ödeme matrahında zaten yer alan kısmını, `Primin Ayrı Ödemede Kalan Kısmı (Bilgi)` ise ayrı kalem olarak dağıtılan kısmını ifade eder. Bu açıklama sütunlarının tamamı `Toplam Hakediş` içinde yer alır ve ödeme toplamına yeniden eklenmez.
- `Ödeme Mutabakatı` sayfasında muhasebenin kullanacağı denklem geçerlidir: `Resmî Banka Matrahı - Bankadan Düşülen Kesinti = Banka Ödemesi`.
- `Ödeme Mutabakatı` sayfasının sırası muhasebe işlem akışını izler: önce açık yeşil zeminde resmî banka matrahına eklenecek kalemler, sonra açık kırmızı zeminde bankadan düşülecek kesintiler ve ardından personelin hesabına yatırılacak banka tutarı gösterilir.
- `Mutabakat` sayfasının kırmızı kesinti alanına snapshot'ta banka kanallı olan kesintiler ile elden kanallı kesintilerden personelin elden hakedişini aşarak bankadan kesilen bakiye tutarlar (`muhasebe_banka_diger_kesinti`) yazılır. Elden hakedişten başarıyla karşılanan kısım bu kırmızı kesintiye taşınmaz; ancak elden tutarın yetmediği bakiye bankadan kesildiğinde yeşil resmi hakediş sütunlarından (asgari ücret, yemek vb.) düşülmeyip kırmızı Diğer Kesintiler sütununda gösterilir. Bu sayfadaki resmî kalem dağılımı `bankaya yatacak tutar + gösterilen banka kesintileri` üzerinden kurulur; böylece yeşil toplamdan kırmızı toplam çıkarıldığında listedeki kayıtlı banka ödemesi elde edilir.
- `Bordro Kontrol` Excel'i yalnızca kullanıcının seçtiği personeller için üretilir. Her personel ayrı yazdırma bloğunda sözleşme alacağı, resmî alacak, kesinti ve banka/elden/kart dağılımını ortak gösterim hesabından sunar; ayrı bir hesaplama yolu oluşturmaz.
- `Bordro Kontrol` Excel'inde `[Puantaj]` ve `[Sayaç]` kaynaklı hareketler tek tek yazılmaz; tutarlar tek `Puantaj Hakedişi` satırında toplanır. `[Kaçak Kontrol]` hareketleri de ayrı tek `Kaçak Kontrol Hakedişi` satırında gruplanır. Puantaj dışı gerçek ek ödemeler ayrı satır olarak korunur.
- Bordro Kontrol sınıflandırması detay modalıyla aynıdır: yemek ve eş yardımı kaynak ek ödeme kayıtları Sözleşme Alacağı tarafında tekrar edilmez, yalnızca Resmî Alacak tarafında ortak hesap tutarıyla gösterilir. Yuvarlama farkı ise ortak hesabın `yuvarlamaFarki` değerinden tek satır olarak Sözleşme Alacağına yazılır.
- Mutabakat sütunları başlık metnine göre otomatik genişletilmez. Tutarların okunmasına yetecek sabit genişlik kullanılır ve uzun başlıklar hücre içinde alt satıra kaydırılır.
- “Ödenecek Net Maaş” sütunu toplam net hakediş değil, **banka ödemesidir**.
- Muhasebe Excel'indeki Yemek sütunu, maaşa dahil yemek yardımına ek olarak ayrı ek ödeme kaydıyla verilen nakit/banka yemek yardımını da içerir. Kart/Sodexo yöntemindeki yemek ödemesi yalnızca Sodexo / Kart sütununda gösterilir ve Yemek sütununda ikinci kez sayılmaz. Ayrı yemek yardımında fiilî gün bulunmuyorsa ortak hesaptaki çalışma günü kullanılır.
- Maaşa dahil yardım modunda banka matrahına eklenen primin tamamı muhasebe Excel'indeki Prim / İkramiye sütunundan çıkarılır. Yemek limitini aşarak elden ödemede kalan kısmı da bu sütunda tekrar gösterilmez; elden ödeme dağılımında kalır. Elden seçilen prim ile dahil yardım modu etkin olmayan personelin primi korunur. Puantaj/sayaç/kaçak kontrol otomatik kazançları bu sütuna eklenmez.
- Bu gizleme yalnızca muhasebe aktarımının Prim / İkramiye sütununa uygulanır; günlük yemek yuvarlaması, hakediş ve banka/elden ödeme dağıtımı değiştirilmez.
- Prim kaydı ve bordro detayındaki asıl kazanç silinmez; bu ayrıştırma yalnızca muhasebe aktarımında mükerrer toplamı önler.
- Maaşa dahil personelde elden seçilen manuel prim de kayıt hesabında hedef hakedişe eklenir; banka seçili olmaması kazancı ortadan kaldırmaz. Liste–kayıt–detay–Excel karşılaştırmasında banka ve elden seçimi birlikte doğrulanır.
- Banka ödeme detayı satırları net banka ödemesiyle matematiksel olarak uzlaşır. Sosyal yardımlar kartındaki yemek tutarı banka detayında da aynı gösterilir. Maaşa dahil yemek havuzunu büyüten bir banka ek ödemesi (prim, ikramiye vb.) yemek tutarının içindeyse ayrıca toplanan `+` satırı yapılmaz; `Yemek yardımına dahil (bilgi)` açıklamasıyla gösterilir. Yalnız yemek dışında kalan kısmı ayrı banka kazancı olarak toplama girer. Bu gösterim kayıtlı hakedişi veya ödeme dağıtımını değiştirmez.
- `Yemek yardımına dahil (bilgi)` satırları yalnız banka ödeme popover'ında açıklama amacıyla gösterilir. Ana bordro detayındaki `Resmî Alacağı` açılır kırılımında bu bilgi satırları tüm personeller için gizlenir; toplama giren asgari ücret, yemek/eş yardımı ve ayrı banka kalemleri gösterilmeye devam eder.
- Maaşa dahil personelin banka detayında asgari ücret, yemek/eş yardımı ve ayrı banka ek ödemelerinden sonra sözleşme netini tamamlayan bakiye `Diğer Banka Payı` adıyla değil `Sözleşme Farkı` adıyla gösterilir. RTÇ/HTÇ ve fazla mesai yalnız ilgili dönemde gerçek puantaj veya ek ödeme kaydı varsa gösterilir; sözleşme farkına dönüştürülmez.
- Hesaplanmış bordronun kayıtlı özeti okunurken banka ek ödeme detayları boşaltılamaz. Snapshot içindeki resmî/banka ek ödemeleri ile dönemin RTÇ/HTÇ günleri yeniden ayrıştırılır; banka detayında fazla mesai ve RTÇ/HTÇ kendi adlarıyla gösterildikten sonra gerçekten açıklanamayan bakiye varsa ancak o tutar sözleşme farkı olabilir.

### Net ve prim usulü bordroda kesinti özeti

Detay Kesintiler kartı banka ve elden kanallı tüm snapshot personel kesintilerini
gösterir. Banka ödeme açıklamasındaki kesinti toplamı ise yalnız bankadan mahsup
edilen kısımdır. `elden_tutardan` kesintisi ana kesinti kartından gizlenemez.

- Net maaşlı veya prim usulü personelde SGK, gelir vergisi ve damga vergisi net hedefe ulaşmak için brüte tamamlama hesabında zaten karşılanır. Detay ekranındaki `Kesintiler Toplamı`, ödenecek netten gerçekten mahsup edilen icra, avans ve diğer personel kesintilerini gösterir; brüte tamamlama içinde karşılanan yasal kesintiler bu özete ikinci kez eklenmez.
- Eski bordro detayının `Kesintiler (Düşürücüler)` bölümünde yasal kesintiler ayrıca satır olarak gösterilmez. Bu bölüm ve bölüm toplamı yalnız net ödemeden gerçekten düşülen personel kesintilerinden oluşur.
- Maaşa dahil yardım dağıtımında kesintinin kayıtlı ödeme kanalı korunur. Banka kesintisi önce resmî banka matrahından, `Elden / Harici` kesinti ise önce elden hakedişten (maksimum elden hakediş tutarı kadar) düşülür; elden hakedişin yetmediği bakiye kesinti ise bankadan mahsup edilir ve muhasebe Excel'inde kesinti (Diğer Kesintiler) olarak gösterilir.
- Hesaplanmış bordroda liste ve detayın personel kesintisi toplamı, snapshot içindeki `kesintiler` kalemlerinden alınır. Eski `bordro_personel.kesinti_tutar` alanında kalmış brüte tamamlama/yasal kesintiler, icra-avans-diğer personel kesintisi gibi gösterilmez.
- Kesinti açıklaması kesinti türüyle uyumlu olmalıdır. `avans` veya başka bir türdeki satıra yanlışlıkla taşınmış `[İcra]` açıklaması gösterilmez; yeniden hesaplanan avans satırının açıklaması ilgili avans kaynağından yenilenir.
- Açıklaması bulunmayan bir kesinti satırı, aynı personelin başka bir kesintisinin açıklamasıyla doldurulmaz. Her satır yalnız kendi snapshot açıklamasını gösterebilir.
- Maaşa dahil yemekte yemek, sözleşme netini ve banka matrahına dahil kazançların uygun bakiyesini günlük tavana kadar tamamlar. HTÇ/fazla çalışmanın resmî neti ayrı banka kalemidir; gerçek hakediş ile resmî net arasındaki fark önce yemek tavanını doldurur, yalnız artan bakiye elden ödenir.
- HTÇ günü bulunan bordroda resmî banka matrahı, diğer fazla çalışma kalemlerinden bağımsız olarak en az `asgari ücret net / 30 × HTÇ günü` kadar artırılır. HTÇ hedef neti snapshot'a ayrı kod ve gün sayısıyla yazılır; aynı tutarlı fazla mesai satırı HTÇ'nin yerine geçemez.
- Fazla çalışma parametresi `fazla_mesai` veya sistemdeki kısa kodu olan `mesai` ile kaydedilmiş olabilir. İki kod da yemek yardımından bağımsız, ayrı resmî banka kalemi olarak aynı kurala tabidir.
- Otomatik dağıtımda ödeme kanalları zorunlu olarak mutabık olmalıdır: `banka + elden + Sodexo + diğer = toplam net hakediş - gerçek personel kesintileri`. Brüte tamamlama içindeki SGK/vergi tutarları bu mutabakatta personel kesintisi sayılmaz. Resmî banka matrahına sığmayan pozitif bakiye kaybolamaz; elden ödemeye tamamlanır.


### Personel PWA resmî döküm yayını

- Resmî döküm, ortak gösterim hesabı ve muhasebe ödeme özetinin resmî banka verilerini kullanır; hesap kuralları değişmez.
- Kayıtlı banka neti ile ortak hesap ve döküm kalem toplamı kuruş bazında uyuşmadan yayın yapılamaz. Günlük yemek yuvarlama farkı ayrı gösterilir.
- Yayınlanan içerik sabit sürüm olarak saklanır; personel ekranı ve PDF bu sürümü okur. Dönem revizyonu eski içeriği/beyanı değiştirmez.
- Elden dağılım personel resmî dökümüne aktarılmaz. Uygulama/kurulum ayrıntıları: `docs/PERSONEL_BORDRO_YAYINI.md`.

### Hafta içi nöbet — resmî taban mutabakatı (2026-10-14)
- Yeni hesaplamada sözleşme nöbet satırı kayıtlı neti korur; resmî banka satırı kanuni hedef neti korur. Sözleşme toplamı resmî asgari + yardım + ek çalışma toplamını karşılamıyorsa, yalnız eksik bakiye `resmi_taban_tamamlama` olarak snapshot özetine yazılır ve ödenecek hakedişi tamamlar; nöbet satırına veya yuvarlama farkına eklenmez. Mevcut elden hakediş yeterliyse ek tamamlama yapılmaz.
- Prim usulünde Sözleşme kartı ve Bordro Kontrol sözleşme bölümü yalnız snapshot'taki gerçek puantaj ve sözleşme kaynaklı ek kazançları gösterir. Hesaplama snapshot'ındaki kanuni taban/tamamlama, liste ödeme toplamı ve muhasebe ödeme kanalları kayıtlı değerleriyle korunur; kesintiler bunlardan sonra uygulanır.
- Örnek: Gerçek puantaj kaydı yok, nöbet 7 × 500 = 3.500,00 TL ve 29 günlük resmî taban 27.139,65 TL ise sözleşme kartında `0,00 TL Puantaj Hakedişi + 3.500,00 TL Nöbet = 3.500,00 TL` gösterilir. Resmî nöbet 28 saat için 5.240,76 TL, resmî banka ve kesintisiz ödeme 32.380,41 TL olarak korunur.
- Kayıtlı snapshot gösterimde değiştirilmez; düzeltme yetkili yeniden hesaplamayla uygulanır.
- Bordro Kontrol Excel'indeki sözleşme alacağı kırılımı canlı ek ödeme tablolarından kurulmaz. Kayıtlı snapshot ek ödeme kalemleri gruplanır. Prim usulünde gerçek puantaj kaynakları **Puantaj Hakedişi**, kanuni taban ise yalnız Resmî Alacak bölümünde **Asgari Ücret (Net)** etiketiyle gösterilir. Sözleşme bölümü satırları kendi gerçek kaynak toplamıyla kuruş bazında eşitlenir. Kesinti kırılımı da aynı snapshot'tan okunur. Böylece detay ve Excel aynı hesaplama anını ve aynı kaynak ayrımını gösterir.
# Puantaj Mutabakatı Ön Koşulu (2026-10-18)

- Mutabakat kapsamı `Endeks Okuma`, `Kesme/Açma İşlemleri`, `Sayaç Sökme-Takma` ve `Mühürleme` kaynaklarıdır ve Ekim 2026 dönemiyle başlar. Daha eski bordro dönemleri bu kontrol nedeniyle bloklanmaz.
- Okuma ve kesme/açma hakediş kaynakları, ay sonu snapshot'ı ile takip eden ayın 1, 2 ve 3'ündeki dış servis sonuçları arasında mutabık olmalıdır.
- Bordro hesaplama ay içindeki taslak çalışma akışıdır; ay sonu snapshot'ı ve final mutabakat henüz oluşmadan da çalıştırılabilir. Bu hesaplama dönemi kesinleştirmez ve final mutabakat sonrasında veri değişmişse yeniden yapılır.
- Ayın 3'ündeki final tarama tamamlanmamışsa, eksik gün/API sayfası varsa veya açık fark bulunuyorsa dönem kapatılamaz; bu blokaj `force_close` ile aşılamaz.
- Yetkili tarafından güncel dış sistem verisi uygulandığında önceden hesaplanmış bordro snapshot'ı otomatik değiştirilmez. İlgili dönemdeki tüm personel bordroları yeniden hesaplanmadan dönem kapatılamaz.
- Mutabakat snapshot'ı ve geçmiş fark/çözüm kayıtları geriye dönük değiştirilmez veya silinmez.

# Puantaj fazla mesaisi yalnız resmî alacaktır (2026-10-31)

- `[Puantaj Fazla Mesai]` kalemi (arife otomasyonu dahil) sözleşme hakedişine eklenmez; snapshot'ta `net_etki = 0`, resmî tutarı `resmi_banka_net` olarak saklanır. Banka tarafında `Fazla Mesai Ücreti (Net)` olarak ödenir ve toplam hakedişi artırmaz; aynı tutar yemek üst limitinden düşülür, elden/yemek ile dengelenir.
- RTÇ resmî neti de sözleşme netinin bileşeni olduğundan yemek üst limitinden düşülür. Böylece resmî kartta yemek yardımı `tam lira günlük × fiilî gün` olarak kalır; banka tavanı kırpması küsurat üretmez.
- RTÇ sözleşme kartında hiçbir maaş türünde satır olarak çizilmez. Hakediş resmî tabanın (asgari + RTÇ + fazla mesai + hafta içi nöbet resmî neti + yardımlar) altında kalırsa toplam hakediş resmî tabana tamamlanır; resmî kalemler banka tavanında kırpılmaz.
- Resmî kartta RTÇ `Resmi Tatil Çalışması (Net)`, fazla mesai ise `Fazla Mesai Ücreti (Net)` satırında gösterilir; fazla mesai açılımı `Nöbetten Gelen` ve `Puantaj Fazla Mesaisi` kaynaklarını saat ve tutarla ayırır.
- Bu kural yalnız yeniden hesaplanan bordrolara uygulanır. Eski snapshot'larda fazla mesai hakedişi `Nöbet Ödemeleri` altında `Puantaj Nöbeti` olarak görünür; kayıtlı tutarlar değişmez. Elle girilen `mesai` ek ödemeleri bu kuralın dışındadır.

# Fazla mesai ve hafta içi nöbet gösterim ayrımı

- Sözleşme alacağı tarafında `Fazla Mesai` etiketi gösterilmez; hafta içi nöbet, hafta sonu nöbet ve puantaj/arife fazla mesai hakedişleri tek `Nöbet Ödemeleri` satırında toplanır. Detay modalı ve Bordro Kontrol Excel'i aynı gruplamayı kullanır. Fazla mesai etiketi yalnız resmî alacak tarafında kullanılır.
- Resmî alacak tarafında hafta içi nöbetin kanuni fazla mesai karşılığı ile puantaj fazla mesaisi tek `Fazla Mesai Ücreti (Net)` satırında birleştirilir; saat detayı iki kaynağın toplamını gösterir.
- Bu sunum ayrımı kayıtlı snapshot tutarlarını veya ödeme kanallarını değiştirmez.


# Otomatik RTÇ ve arife fazla mesaisi kaynağı (2026-10-28)

- `cron/puantaj_ozel_gun_otomasyon.php` tam gün resmi tatilde (`resmi_tatiller.tur = 'tam'`) çalışan personele `personel_izinleri` üzerinden RTÇ (`otomatik_kural = 'resmi_tatil_rtc_v1'`) yazar. Bordro bu kaydı mevcut `resmi_tatil_calismasi = 1` bayrağıyla, manuel RTÇ ile aynı yoldan sayar; hesaplama formülü değişmez.
- Arife (`tur = 'yarim'`) günlerinde kesim saatinden sonra çalışan personele `personel_fazla_mesaileri` üzerinden fazla mesai (`kaynak = 'otomatik'`, `otomatik_kural = 'arife_fm_v1'`, varsayılan 4 saat) yazar. Bordro bunu puantaj fazla mesaisiyle aynı yoldan alır.
- Resmi tatil Pazar'a denk gelirse otomatik HTÇ kaldırılır ve RTÇ uygulanır. Manuel girilmiş kodlar ve fazla mesailer değiştirilmez.
- Otomasyon yalnız açık dönemin puantaj kaynağını üretir. Hesaplanmış veya kapanmış bordro snapshot'ları (Bölüm 5.1) bu kayıtlardan etkilenmez; değişikliğin yansıması için yetkili yeniden hesaplama gerekir.
