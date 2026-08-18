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

Net ücretli ve puantaj üreten personelde puantajdan oluşan hakediş, sözleşme netinin üzerine ayrıca eklenir. Ancak bu tutar resmî maaş/banka tavanını veya maaşa dahil yemek yardımı tavanını artırmaz; puantaj bakiyesi elden ödenir. Kesintiler önce resmî banka tavanından düşülür; banka tavanını aşan kesinti kalırsa yalnızca bu bakiye elden tutardan mahsup edilir.

### Prim Usulu

Prim usulu net gibi islenir. Varsayilan ek odeme kanali `elden` kabul edilir; parametrede odeme yontemi varsa o yontem kullanilir.
Personelin prim usulu calismasi olsa bile donem icinde calisma gunu varsa (`maasHesapGunu > 0`), puantaj veya ek odeme uretilmemis ya da asgari tabandan dusuk kalmis olsa dahi personelin hakedisi en az calisilan gune tekabul eden `asgariHakedis` (`asgari_ucret_net / 30 * maasHesapGunu`) tutarindan az olamaz.

## Maasa Dahil Sosyal Yardim Kurali

`yemek_yardimi_dahil = 1` veya `es_yardimi_dahil = 1` ise personel maasa dahil sosyal yardim modundadir.

Bu modda maas hesaplamasinin tabani asgari net ucrettir, hedef ise personelin sozlesme net maasidir.

```text
asgariHakedis = (asgari_ucret_net / 30) * maasHesapGunu
hedefHakedis = (hedef_net_maas_tutari / 30) * maasHesapGunu
yemekUstLimiti = max(0, hedefHakedis - asgariHakedis - esYardimi - bankaUzerindenOdenecekRtcHtc)
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

```text
htcHamTutar = (maas_tutari / 30) * htcGun          (personelin kendi gunlugu)
htcResmiNet = (asgari_ucret_net / 30) * htcGun     (bankaya yatan, gross-up edilen kisim)

toplamHakedis = sozlesmeHakedisi + hariciEkOdeme + yuvarlamaFarki    (hariciEkOdeme HTÇ'yi icerir)
```

HTÇ **banka matrahini da yukseltir**. Iki kanaldan girer:

- Resmi neti (`asgari_ucret_net / 30 * htcGun`) dogrudan banka kalemi olarak `yontemliBankaEki`'ne,
- Resmi neti asan fazlasi (`htcNetFazla`) yemek havuzuna eklenir ve yine bankadan odenir.

Bunun calisabilmesi icin yemek/banka tavani HTÇ'nin ham tutari kadar buyutulur:

```text
yemekIcinKalanSozlesmeLimiti = max(0,
    yemekTavanHedefi + htcHamTutar - asgariHakedis - esYardimi - bankaUzerindenOdenecekRtcHtc)
```

`+ htcHamTutar` terimi olmazsa HTÇ'nin resmi neti yemek yardimindan dusulur; banka toplami
degismez ve HTÇ fiilen elden odenmis olur. Bu terim toplam hakedis formulundeki
`+ htcHamTutar` ile birlikte durur, biri olmadan digeri anlamsizdir.

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

```text
esYardimiToplam = min(kalanFark, esYardimiAylikLimit)
```

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
bankaBaz = min(bankaYatacakMinimum + banka kanalli ek odemeler, netMaas - sodexoOdemesi)
bankaOdemesi = max(0, bankaBaz - icraKesintisi)
```

## Manuel Dagilim Kurali

`dagitim_manuel = 1` ise kullanicinin girdigi banka, Sodexo ve diger odeme degerleri korunur.

```text
eldenOdeme = max(0, netMaas - bankaOdemesi - sodexoOdemesi - icraKesintisi - digerOdeme)
```

Manuel dagilimda otomatik banka/Sodexo duzeltmesi yapilmaz.

## Bankaya Yatmayacak Personel Kurali

`sgk_yapilan_firma` alaninda `KUR` geciyorsa otomatik dagilimda banka odemesi sifirlanir ve tutar elden odemeye aktarilir.
`sgk_yapilan_firma` alaninda `Sigortal` geciyorsa banka odemesi sifirlanir, ancak elden odemeye aktarilmaz.

```text
if sgk_yapilan_firma contains "KUR":
    eldenOdeme += bankaOdemesi
    bankaOdemesi = 0

if sgk_yapilan_firma contains "Sigortal":
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
