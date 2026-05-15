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

```text
netMaas = brutMaas + toplamEkOdeme - (toplamKesinti - icraKesintisi)
```

### Prim Usulu

Prim usulu net gibi islenir. Varsayilan ek odeme kanali `elden` kabul edilir; parametrede odeme yontemi varsa o yontem kullanilir.

## Maasa Dahil Sosyal Yardim Kurali

`yemek_yardimi_dahil = 1` veya `es_yardimi_dahil = 1` ise personel maasa dahil sosyal yardim modundadir.

Bu modda maas hesaplamasinin tabani asgari net ucrettir, hedef ise personelin sozlesme net maasidir.

```text
asgariHakedis = (asgari_ucret_net / 30) * maasHesapGunu
hedefHakedis = (hedef_net_maas_tutari / 30) * maasHesapGunu
yardimFarki = max(0, hedefHakedis - asgariHakedis)
```

Yemek yardimi bu farktan hesaplanir. Sistem gunluk tutari yukari yuvarlar, sonra gunluk limitle sinirlar.

```text
fiiliGunSayisi = puantajdaki X gunleri
fiiliGunSayisi yoksa maasHesapGunu kullanilir

yemekGunlukHam = yardimFarki / fiiliGunSayisi
yemekGunluk = ceil(yemekGunlukHam)
yemekGunluk = min(yemekGunluk, yemekYardimiGunlukLimit)
yemekYardimiToplam = yemekGunluk * fiiliGunSayisi
```

Kural: Yemek yardiminin gunluk tutari, personel veya parametre uzerinden bulunan gunluk yemek limitini asamaz.

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

## KUR / Bankaya Yatmayacak Personel Kurali

`sgk_yapilan_firma` alaninda `KUR` geciyorsa otomatik dagilimda banka odemesi sifirlanir ve tutar elden odemeye aktarilir.

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
