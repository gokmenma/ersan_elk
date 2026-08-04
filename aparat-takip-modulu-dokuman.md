# Kesme-Açma Aparat Takip Modülü — İhtiyaç Dökümanı

**Firma:** Er-San Elektrik
**İş kalemi:** KASKİ — Borçtan su kesme / açma
**Modül adı (öneri):** Aparat Takip
**Hazırlanma tarihi:** 04.08.2026
**Durum:** Kararlaştırıldı ve geliştirildi — Bölüm 10'daki maddeler karara bağlandı, modül `ersan_elk` projesine eklendi (bkz. Bölüm 11)

---

## 1. Amaç ve mevcut problem

Kesme-açma ekiplerimiz borçlu abonenin suyunu keserken sayaca bir **aparat** takıyor, abone borcunu ödeyince aparat sökülüp su tekrar açılıyor. Elimizde **5 farklı tipte aparat** ve **12 kişilik kesme-açma saha personeli** (ekipler halinde çalışıyor) var.

Bugün aparatların ekip bazlı takibi yapılamıyor. Sebebi:

- Suyu **ekip-3** kesiyor, aynı aboneyi günler sonra **ekip-5** açıyor.
- Ekip-3'ün hangi tip aparatı taktığı kayıt altında olmadığı için o ekibin stoğundan düşülemiyor.
- Ekip-5'in hangi tip aparatı söküp geri getirdiği bilinmediği için o ekibin stoğuna eklenemiyor.

Tek tip aparat olsaydı basit bir sayaçla idare edilebilirdi; 5 tip olunca elle takip pratikte imkânsız hale geliyor.

**Hedef:** Her saha işleminde kullanılan aparatın tipi kayıt altına alınsın; ekiplerin elindeki aparat sayısı tip bazında otomatik ve sürekli güncellensin; ekipler arası aparat devri de sisteme işlensin.

---

## 2. Temel kavramlar

| Kavram | Açıklama |
|---|---|
| **Aparat tipi** | 5 farklı tip (örn. küresel aparat vb.). Tipler tablodan yönetilebilir olmalı, koda gömülmemeli. |
| **Ekip** | Sahada birlikte çalışan personel grubu (ekip-1, ekip-2 …). Panelde ekip kavramı zaten mevcut. |
| **Ekip stoğu** | Bir ekibin aracında/üzerinde bulunan, henüz sayaca takılmamış aparat adedi. **Her aparat tipi için ayrı sayılır.** |
| **Depo** | Merkezdeki ana stok. Ekiplere aparat buradan verilir. |
| **Sahada takılı** | Kesme işleminde sayaca takılmış, henüz sökülmemiş aparatlar. Bir ekibe ait değildir; sistemin genelinde ayrı bir havuzdur. |
| **Hareket (ledger)** | Stoğu değiştiren her kayıt (kesme, açma, transfer, depo çıkışı, sayım düzeltmesi…). |

---

## 3. Stok mantığı (modülün kalbi)

Aparatlar 4 havuzdan birinde bulunur ve toplam adet daima sabittir:

```
                 ┌──────────┐
                 │  DEPO    │
                 └────┬─────┘
                      │ depo çıkışı (+) / depo iadesi (−)
                      ▼
   transfer     ┌──────────┐      kesme (−1)     ┌────────────────┐
 ekip ↔ ekip ◄──┤ EKİP     ├───────────────────► │ SAHADA TAKILI  │
                │ STOĞU    │ ◄─────────────────  │  (sayaç üstü)  │
                └────┬─────┘   açma (+1)         └────────────────┘
                     │ hurda / kayıp
                     ▼
              ┌──────────────┐
              │ HURDA-KAYIP  │
              └──────────────┘
```

**Kural:**

- **Kesme işlemi** → işlemi yapan ekibin, seçilen aparat tipindeki stoğundan **−1**. Aparat "sahada takılı" havuzuna geçer.
- **Açma işlemi** → işlemi yapan ekibin, seçilen aparat tipindeki stoğuna **+1**. Aparat "sahada takılı" havuzundan düşer.

Bu sayede ekip-3'ün taktığı aparatı ekip-5 söktüğünde, aparat doğal olarak ekip-5'in stoğuna geçmiş olur. Kimin taktığı ile kimin söktüğünün farklı olması bir sorun değil, sistemin normal işleyişidir.

**Doğrulama formülü (her an tutmalı):**

```
Toplam alınan aparat = Depo + Σ(tüm ekip stokları) + Sahada takılı + Hurda/Kayıp
```

Her aparat tipi için ayrı hesaplanır.

---

## 4. Kullanıcı rolleri

| Rol | Yetkileri |
|---|---|
| **Saha personeli** (kesme-açma ekipleri) | Telefondan işlem kaydı girer, kendi ekibinin stoğunu görür, transfer talebi oluşturur/onaylar. |
| **Şef / koordinatör** | Tüm ekiplerin stoğunu görür, depo çıkışı yapar, hatalı kayıtları iptal eder, sayım başlatır, raporları alır. |
| **Yönetici** | Aparat tiplerini tanımlar, tüm yetkiler. |

---

## 5. Akışlar

### 5.1. Saha işlem kaydı (telefon uygulaması — asıl akış)

Personel abonede işlemi yaptıktan sonra telefondan:

1. **İşlem tipi seçer:** `Kesme` / `Açma`
2. **Abone bilgisi girer:** abone no / sayaç no (ve varsa ilçe-mahalle; konum otomatik alınır)
3. **Aparat tipi seçer:** 5 tipten biri (büyük, parmakla basılabilir butonlar)
4. **İki fotoğraf yükler:**
   - Sayaç fotoğrafı (zorunlu)
   - Aparat fotoğrafı (zorunlu)
5. **Kaydet** → ekibin ilgili tipteki stoğu anında güncellenir ve ekranda "Ekip-3 / Küresel aparat: 47 adet kaldı" şeklinde gösterilir.

**Açma işleminde ek bir soru sorulmalı:** *Aparat geri alındı mı?*
`Alındı` (stoğa +1) / `Hasarlı geldi` (stoğa +1 ama hurda işaretli) / `Bulunamadı-kayıp` (stoğa +0, kayıp havuzuna yazılır).
Bu olmazsa abone tarafından kırılan/kaybolan aparatlar sistemde sonsuza kadar "sahada takılı" görünür ve sayım hiçbir zaman tutmaz.

### 5.2. Ekipler arası transfer

Örnek: Ekip-3, Ekip-5'e 50 adet küresel aparat veriyor.

1. **Veren ekip** transfer kaydı oluşturur: alan ekip + aparat tipi + adet.
2. Kayıt `Beklemede` durumuna düşer, **alan ekibe** bildirim gider.
3. **Alan ekip onaylar** (veya reddeder / adedi düzeltip onaylar).
4. Onay anında: veren ekipten −50, alan ekibe +50 işlenir.

> **Neden çift onay?** Tek taraflı yazılırsa "ben verdim / bana gelmedi" tartışmasında sistem hakem olamaz. Onay basit tutulmalı (tek buton), yoksa saha kullanmaz.
> Alternatif: şef kullanıcılar onay beklemeden doğrudan transfer işleyebilsin.

### 5.3. Depo giriş / çıkış

- **Depo girişi:** Satın alınan yeni aparatlar (irsaliye no + adet + tip).
- **Depoya iade:** Ekibin fazla aparatını merkeze geri vermesi.
- **Hurda / kayıp çıkışı:** Kırılan, kaybolan aparatların stoktan düşülmesi (gerekçe zorunlu).

### 5.4. Sayım (mutabakat)

Ayda bir (veya istenildiğinde) şef sayım başlatır; her ekip elindeki aparatları tip bazında fiilen sayıp sisteme girer. Sistem, kayıtlı stok ile sayılan adedi karşılaştırır, farkı `sayım düzeltmesi` hareketi olarak yazar. Fark için açıklama zorunlu olmalı.

---

## 6. Ekranlar

### 6.1. Telefon uygulaması (mevcut `personel-pwa` içine)

| Ekran | İçerik |
|---|---|
| **Yeni işlem** | 5.1'deki form. Sağ alttaki **yüzen (+) butonundan** açılan bottom sheet içinde; aparat tipi seçimi büyük butonlarla. |
| **Ekibim** | Ekipte bugün çalışan personelin adları + tip bazında güncel aparat sayıları. |
| **Transferler** | Bana gelen transferleri onayla / yeni transfer gönder. |
| **Geçmiş** | Son 7 günde girdiğim kayıtlar. |

> **Görünürlük kuralı:** Stok ekibe zimmetli olsa da tüm ekranlarda (panel stok tablosu, ekip seçim listeleri, telefon başlığı) ekip kodu yerine **o ekipte bugün çalışan personelin adı** gösterilir; ekip kodu ikinci satırda/parantezde kalır. Ekip üyeliği `personel_ekip_gecmisi`'nden tarih geçerliliğine göre okunur.

### 6.2. Web paneli

| Ekran | İçerik |
|---|---|
| **Stok tablosu (ana ekran)** | Satırlar = ekipler + Depo + Sahada takılı + Hurda; sütunlar = 5 aparat tipi; hücrelerde adet; en altta toplam satırı. Tek bakışta tüm durum görünsün. |
| **Hareket dökümü** | Tarih, ekip, personel, hareket tipi, aparat tipi, +/− adet, abone no, fotoğraflar, açıklama. Filtreler: tarih aralığı, ekip, aparat tipi, hareket tipi. Excel'e aktarılabilir. |
| **İşlem kayıtları** | Saha işlemlerinin listesi; fotoğraflara tıklayınca büyük görünsün. |
| **Transferler** | Bekleyen / onaylanan / reddedilen transferler; şef müdahalesi. |
| **Sayım** | Sayım başlat, ekip girişlerini gör, fark raporu. |
| **Tanımlar** | Aparat tipleri (ekle/düzenle/pasife al). |

---

## 7. Veritabanı tasarımı (öneri)

> Proje kendi MVC düzenini ve PDO kullanıyor; tablolar mevcut isimlendirme ile uyumlu tutuldu. Tüm tablolarda **`firma_id`** bulunmalı (panel çok firmalı).

### `aparat_tipleri`
| Alan | Tip | Not |
|---|---|---|
| id | INT PK | |
| firma_id | INT | |
| ad | VARCHAR(100) | örn. "Küresel Aparat" |
| kod | VARCHAR(20) | kısa kod |
| aciklama | TEXT | |
| is_active | TINYINT | |
| created_at / created_by | | |

### `aparat_stok` (anlık bakiye — hızlı okuma için)
| Alan | Tip | Not |
|---|---|---|
| id | INT PK | |
| firma_id | INT | |
| sahip_tipi | ENUM('ekip','depo','saha','hurda') | |
| sahip_id | INT NULL | ekip id (depo/saha/hurda için NULL) |
| aparat_tip_id | INT | |
| adet | INT | |
| updated_at | DATETIME | |

**UNIQUE (firma_id, sahip_tipi, sahip_id, aparat_tip_id)**

### `aparat_hareket` (ana defter — gerçeğin kaynağı)
| Alan | Tip | Not |
|---|---|---|
| id | INT PK | |
| firma_id | INT | |
| aparat_tip_id | INT | |
| hareket_tipi | ENUM('kesme','acma','transfer_cikis','transfer_giris','depo_cikis','depo_giris','hurda','kayip','sayim_duzeltme') | |
| sahip_tipi / sahip_id | | hareketin etkilediği havuz |
| adet | INT | işaretli (+/−) |
| ekip_id | INT NULL | |
| personel_id | INT NULL | işlemi yapan |
| referans_tipi | VARCHAR(30) | 'kesme_acma_islem', 'aparat_transfer' … |
| referans_id | INT | ilgili kaydın id'si |
| aciklama | TEXT | |
| iptal_mi | TINYINT | |
| tarih | DATETIME | |
| kaydeden_id | INT | |

### `kesme_acma_islem`
| Alan | Tip | Not |
|---|---|---|
| id | INT PK | |
| firma_id | INT | |
| islem_tipi | ENUM('kesme','acma') | |
| ekip_id / personel_id | INT | |
| abone_no / sayac_no | VARCHAR | |
| ilce / mahalle / adres | VARCHAR | |
| enlem / boylam | DECIMAL(10,7) | telefondan otomatik |
| aparat_tip_id | INT | |
| aparat_durumu | ENUM('alindi','hasarli','bulunamadi') | sadece açma işleminde |
| sayac_foto / aparat_foto | VARCHAR | dosya yolu |
| cihaz_zamani | DATETIME | telefonun kaydettiği an (offline senaryo için) |
| durum | ENUM('aktif','iptal') | |
| tarih / kaydeden_id | | |

### `aparat_transfer`
| Alan | Tip | Not |
|---|---|---|
| id | INT PK | |
| firma_id | INT | |
| veren_ekip_id / alan_ekip_id | INT | |
| aparat_tip_id | INT | |
| adet | INT | |
| durum | ENUM('beklemede','onaylandi','reddedildi','iptal') | |
| olusturan_id / onaylayan_id | INT | |
| tarih / onay_tarihi | DATETIME | |
| aciklama | TEXT | |

### `aparat_sayim` ve `aparat_sayim_detay`
Başlık (tarih, ekip, durum, açıklama) + satır (aparat_tip_id, sistem_adet, sayilan_adet, fark).

---

## 8. İş kuralları

1. **Ana defter esastır.** `aparat_stok` sadece hızlı okuma içindir; her hareket ile **aynı transaction** içinde güncellenir. Bakiye, hareketlerden her an yeniden hesaplanabilmelidir.
2. **Stok negatife düşemez.** Ekibin elinde o tipten aparat yokken kesme kaydı girilmek istenirse uyarı verilmeli. *(Karar gerekli: tamamen engellensin mi, yoksa uyarı verip şef onayıyla geçilsin mi? Sahayı kilitlememek için ikinci seçenek daha güvenli olabilir.)*
3. **Kayıt silinmez, iptal edilir.** İptal edilen kaydın ters hareketi yazılır, iz kaybolmaz. İptal yetkisi sadece şefte.
4. **Aynı abonede aynı gün ikinci kez aynı işlem** girilirse mükerrer kayıt uyarısı verilmeli.
5. **Fotoğraflar zorunlu.** Fotoğrafsız işlem kaydedilememeli. Dosyalar sunucuda küçültülerek (örn. genişlik 1280px, ~%70 kalite) saklanmalı — 12 personel × günlük onlarca işlem × 2 fotoğraf ciddi yer tutar.
6. **Offline çalışma:** Sahada internet kesilirse kayıt telefonda kuyruğa alınıp bağlantı gelince gönderilebilmeli. Bu yüzden `cihaz_zamani` ayrı tutulur. *(Kapsam dışı bırakılabilir — bkz. Bölüm 10.)*
7. **Geçmişe dönük düzenleme** yapılırsa hareket tarihi değil, düzeltme tarihi esas alınır; log tutulur.
8. **Yetki:** Personel yalnızca kendi ekibinin verisini görür; şef tümünü görür.

---

## 9. Raporlar

- **Ekip bazlı aparat durumu** (tip × ekip matrisi) — anlık ve tarih seçmeli.
- **Dönemsel hareket özeti:** Bu ay kaç kesme, kaç açma, tip bazında kaç aparat sahaya çıktı / geri geldi.
- **Sahada takılı aparat listesi:** Hangi abonede, hangi tip aparat, kaç gündür takılı. (Uzun süredir takılı olanlar takip edilebilir.)
- **Kayıp / hurda raporu:** Ekip bazında kayıp oranı.
- **Sayım fark raporu.**

Tüm raporlar Excel'e aktarılabilmeli.

---

## 10. Karara bağlanan konular

| # | Konu | Karar |
|---|---|---|
| 1 | Panelde kesme-açma kaydı var mı? | **Var ama abone bazlı değil.** `yapilan_isler` tablosu KASKİ API'sinden cron ile çekilen *ekip × gün × iş emri sonucu × adet* özetini tutuyor; `abone_no` boş geliyor. Bu yüzden abone bazlı yeni tablo (`kesme_acma_islem`) açıldı. API özetindeki "APARATLA KESİM YAPILDI." sonucu, panele girilen kayıtlarla **çapraz doğrulama** için kullanılıyor (Bölüm 9). |
| 2 | Ekip yapısı | Ekipler `tanimlamalar` tablosunda (`grup='ekip_kodu'`), üyelik `personel_ekip_gecmisi` tablosunda tarih aralıklı tutuluyor; üyeler gün gün değişebiliyor. Bu nedenle **aparat ekibe zimmetlendi**, personele değil. Kesme ekipleri `ekip_aralik_kesme` ayarı (varsayılan 1-40) ile süzülüyor. |
| 3 | Aparat tiplerinin listesi | Koda gömülmedi; **Tanımlar ekranından** girilecek (Bölüm 13, kurulum adımı 2). |
| 4 | Başlangıç stoğu | `acilis` hareket türü ile "Açılış Stoğu" olarak girilir (Depo/Havuz Hareketi ekranı). |
| 5 | Aparatsız kesme | **"Aparat kullanılmadı" seçeneği var.** İşaretlenirse kayıt açılır ama stok hareketi yazılmaz. |
| 6 | Bir işlemde birden fazla aparat | **Adet alanı kondu, varsayılan 1.** |
| 7 | Negatif stok | **Uyarılır ama engellenmez.** Kayıt `negatif_stok = 1` işaretlenir, sahada personele uyarı çıkar, panelde kırmızı gösterilir ve `error_log`'a yazılır. Saha kilitlenmez. |
| 8 | Transfer onayı | **Çift taraflı onay zorunlu.** Veren ekibin beyanı yetmez; stok yalnızca alan ekip onayladığında işlenir. Şef bekleyen transferi yalnızca **iptal** edebilir, onaylayamaz. |
| 9 | Offline çalışma | **İlk sürüme dahil edildi.** Mevcut `pwa-offline-queue.js` altyapısı kullanıldı; kayıt + sayaç fotoğrafı ana istekte, aparat fotoğrafı ayrı istekte gider. `client_uuid` ile mükerrer kayıt engellenir. |
| 10 | KASKİ'ye raporlama yükümlülüğü | Netleşmedi. Hareket dökümü ve stok tablosu Excel'e aktarılabildiği için format gerektiğinde uyarlanabilir. |

### Karar notu — hasarlı aparat

Dökümanın 5.1 maddesinde "Hasarlı geldi → stoğa +1 ama hurda işaretli" yazıyordu. Uygulamada **hasarlı aparat ekip stoğuna değil doğrudan hurda havuzuna** yazılıyor; aksi halde kullanılamaz aparat ekip stoğunu şişirir ve kesmede kullanılabilir görünür. Toplam adet yine korunuyor.

---

## 11. Kurulum ve teslim durumu

### Eklenen dosyalar

| Katman | Dosya |
|---|---|
| Veritabanı | `sql/aparat_takip_modulu.sql` (8 tablo + menü + 6 yetki) |
| Model | `App/Model/AparatTipiModel.php`, `AparatHareketModel.php`, `AparatStokModel.php`, `KesmeAcmaIslemModel.php`, `AparatTransferModel.php`, `AparatSayimModel.php` |
| Servis | `App/Service/AparatStokService.php` (tüm hareketlerin tek giriş noktası) |
| Panel | `views/aparat-takip/` → `list.php`, `api.php`, `js/list.js`, `export-excel.php`, `foto-goruntule.php` |
| Telefon | `views/personel-pwa/pages/aparat.php` + `views/personel-pwa/api.php` içine 7 yeni aksiyon |

### Kurulum adımları

1. `sql/aparat_takip_modulu.sql` çalıştırılır (tekrar çalıştırmak güvenlidir).
2. **Aparat Takip → Tanımlar** sekmesinden 5 aparat tipi girilir (ad + kısa kod).
3. **Depo / Havuz Hareketi → Depo Girişi** ile mevcut aparat mevcudu depoya işlenir.
4. Her ekip için **Açılış Stoğu** hareketiyle eldeki adetler girilir (bir kereye mahsus).
5. Kesme-açma personeli telefonda **Hızlı İşlemler → Aparat Takip** ekranından kayda başlar.

### Yetkiler

`aparat_takip` (görüntüleme), `aparat_depo` (depo/havuz hareketleri, manuel saha kaydı), `aparat_iptal` (kayıt iptali), `aparat_sayim`, `aparat_tanim`, `aparat_transfer_yonet`. Script, `kacak_islemleri` yetkisi olan rollere bu yetkileri otomatik verir.

Telefon tarafı ayrıca yetki gerektirmez; personelin departmanı "Kesme-Açma" ise ekran görünür.

---

## 12. Teknik notlar (mevcut sistem)

Modül, `ersan_elk` projesine mevcut yapıya uygun şekilde eklenecek:

- Yönlendirme: `index.php?p=aparat-takip/list` (`views/aparat-takip/` altında `api.php` + sayfa dosyaları, `views/talepler` modülü örnek alınabilir).
- Model: `App\Model\AparatModel`, `App\Model\AparatHareketModel` vb. — `App\Model\Model` temel sınıfından türetilir (PDO, `find()`, `where()`, `saveWithAttr()`…).
- Menü kaydı `menus` tablosuna eklenmeli, yoksa sayfa `unauthorize.php`'ye düşer. Erişim `MenuModel->userCanAccessMenuLink()` ile denetleniyor.
- Oturumda `user_id` ve `firma_id` mevcut; tüm sorgular `firma_id` ile filtrelenmeli.
- Telefon tarafı: `views/personel-pwa/pages/` altına yeni sayfalar (mevcut `zimmetler`, `ekip-takibi` sayfaları örnek alınabilir).
- Arayüz Bootstrap 5 tabanlı admin şablonu + jQuery.
- Loglama için `App\Model\SystemLogModel`, yetki için `App\Service\Gate` kullanılıyor.

---

## 13. Kabul kriterleri

Modül şu maddeler sağlandığında tamamlanmış sayılır:

- [x] Saha personeli telefondan 2 fotoğraf + işlem tipi + aparat tipi ile kayıt girebiliyor.
- [x] Kesme kaydında ekip stoğu ilgili tipte 1 azalıyor, açma kaydında 1 artıyor.
- [x] Ekip-3'ün kestiği aboneyi ekip-5 açtığında, aparat ekip-5'in stoğuna geçiyor.
- [x] Panelde ekip × aparat tipi matrisi anlık ve doğru görüntüleniyor.
- [x] Ekipler arası transfer yapılıp onaylandığında her iki ekibin stoğu doğru güncelleniyor.
- [x] Her hareket ana defterde iz bırakıyor; hatalı kayıt iptal edilebiliyor.
- [x] `Depo + ekipler + sahada takılı + hurda + kayıp = toplam` eşitliği her tip için tutuyor.
- [x] Hareket dökümü ve stok raporu Excel'e aktarılabiliyor.
- [x] Çevrimdışı girilen kayıt bağlantı gelince gönderiliyor, iki kez düşmüyor.
