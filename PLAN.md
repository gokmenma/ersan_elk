# Ana Firma → Sözleşme/İhale Bazlı Çalışma Yapısına Geçiş

## Özet

- `firmalar`, Ersan Elektrik ve Meta Yapı gibi gerçek tüzel kişileri temsil etmeye devam edecek.
- Mevcut “Sözleşme Dönemi” kavramı kaldırılarak her operasyon alanı doğrudan bir `Sözleşme/İhale` kaydına bağlanacak.
- Maraş 2025, Maraş 2026, Bursa 2027 ve Adana 2027 gibi işler aynı ana firma altında, tarihleri çakışabilen ve eşzamanlı aktif sözleşmeler olacak.
- Mevcut `hakedis_sozlesmeler` tablosu sözleşme/ihale ana kaydı olarak kullanılacak; ikinci bir iş/proje tablosu oluşturulmayacak.
- Kullanıcı üst menüden tek bir aktif iş seçecek. Ana sayfa firma portföy özetini ve seçili işin operasyon istatistiklerini birlikte gösterecek.

## Veri ve Kapsam Mimarisi

- Oturum iki ayrı kapsam taşıyacak:
  - `firma_id`: Ana firma/organizasyon.
  - `aktif_sozlesme_id`: Seçili sözleşme/ihale.
- Dönem işlemleri tamamen iptal edilir: `sozlesme_donemleri`, `aktif_donem_id` ve `sozlesme_donem_id` kaldırılır, uyumluluk katmanı kurulmaz. Üst menüdeki dönem gösterimi ve dönem bazlı hâle getirilmiş ana sayfa verileri sözleşme bazlıya çevrilir.
- Sözleşmelerin tarihleri çakışabilecek; tek aktif dönem kısıtı kaldırılacak. Durumlar aktif, tamamlandı ve pasif olacak; taslak durumu yoktur, yeni sözleşme normal sözleşme gibi davranır.
- Sözleşme kaydında kısa ad/kod, işin adı, il/ilçe, idare, ihale numarası, sözleşme ve iş tarihleri ile dış servis profili bulunacak.
- KASKİ, ASKİ veya başka kurumlara ait servis adresi, firma kodu ve şifreli erişim bilgileri ana firmada değil sözleşme bazında tutulacak.
- Fatura ve evrak kayıtları yalnız ana firmaya ait olacak; sözleşmeyle ilişkilendirilmeyecek.
- Personel ana firmada tekil ana kayıt olarak kalacak ve tarih aralıklı çalışma kayıtlarıyla (`personel_calisma_gecmisi`) işe bağlanacak. Araç ve demirbaşlar sözleşme bazlıdır: her kayıt tek bir `sozlesme_id` taşır ve sözleşmeler arası aktarımla taşınır (Ek D-4, D-8).
  - Bir personel aynı tarihte yalnız bir işe atanabilecek.
  - İş değişiminde eski atama kapatılıp yeni atama başlatılacak.
  - Tarih çakışması sunucu tarafında transaction içinde reddedilecek.
- Bordro, puantaj, izin ve personel talepleri seçili sözleşme/ihale kapsamında yürütülecek.
- Operasyon tablolarına doğrudan `sozlesme_id` eklenecek. Yeni kayıtlarda yalnız tarihten sözleşme tahmini yapılmayacak.
- Ekip, bölge, iş türü, ücret ve operasyon parametreleri sözleşme bazlı olacak; gerçekten ortak tanımlar firma seviyesinde nullable sözleşme bağlantısıyla paylaşılabilecek.

## Uygulama ve Yetkilendirme

- “Dönem Tanımları” ekranı “Sözleşme / İhale Yönetimi” olarak yeniden tasarlanacak ve mevcut hakediş sözleşme formuyla birleştirilecek.
- Üst menü seçicisi kullanıcının erişebildiği sözleşmeleri gösterecek; tamamlanan işler geçmiş veri incelemesi için seçilebilecek ancak yeni operasyon kaydı alamayacak.
- Kullanıcı–sözleşme erişimi ayrı çoktan çoğa tabloyla tutulacak. Mevcut `Gate` yetkisi ile sözleşme erişimi birlikte doğrulanacak. Süper Admin oturum firmasındaki tüm sözleşmelere erişir; başka firmaların sözleşmelerine erişmez.
- Her API çağrısı sözleşmenin oturum firmasına ait olduğunu, silinmediğini ve kullanıcının erişimi bulunduğunu sunucuda kontrol edecek.
- Ana sayfada:
  - Firma genelinde aktif iş, personel, araç ve merkezi kayıt özetleri gösterilecek (Süper Admin dışındaki kullanıcılarda yalnız erişebildikleri sözleşmeler sayılır).
  - İşlem sayıları ve operasyon kartları yalnız `aktif_sozlesme_id` kapsamından üretilecek.
  - İş değiştirilince sözleşme ID’li cache anahtarları kullanılacak.
- Personel, araç, ekip, iş takip, rapor, hakediş, puantaj, bordro, izin, talepler, dış servis sorguları, yüklemeler ve dışa aktarımlar sözleşme ID’siyle filtrelenecek.
- Fatura ve evrak ekranları yalnız firma kapsamında kalacak; iş değişiminden etkilenmeyecek.
- Masaüstü, Mobil SPA ve Personel PWA aynı firma+sözleşme bağlamını ve aynı sunucu doğrulamasını kullanacak.
- Doğrudan URL, şifreli ID veya değiştirilmiş istekle başka işe ait kayıt erişimi reddedilecek.

## Geçiş ve Veri Taşıma

- Değişiklikler tekrarlanabilir, aşamalı SQL scriptleriyle yapılacak; önce nullable alanlar ve indeksler eklenecek.
- Firma 1’e ait mevcut KASKİ operasyonları, mevcut aktif KASKİ hakediş sözleşmesine bağlanacak.
- `firma_id = 0`, firma uyuşmazlığı veya açık biçimde eşlenemeyen kayıtlar otomatik taşınmayacak; migration raporunda düzeltilecek kayıt olarak listelenecek.
- Mevcut `sozlesme_donemleri` kayıtları (2 adet) dönüştürülmez; tablo ve bağımlılıkları kaldırılmadan önce salt okunur yedeği alınır. Yeni sözleşmeler Sözleşme / İhale Yönetimi ekranından normal sözleşme olarak girilir.
- Mevcut personel KASKİ sözleşmesine başlangıç çalışma kaydıyla bağlanacak; tarihler mevcut görev ve ekip geçmişlerinden üretilecek, belirsiz kayıtlar raporlanacak. Mevcut araçlar ve demirbaşlar doğrudan 1 nolu sözleşmeye bağlanacak.
- Bordro, izin, talep ve operasyon geçmişi kayıt tarihindeki personel atamasına göre doldurulacak. Birden fazla veya sıfır eşleşme sessizce atanmayacak.
- Operasyon tablolarında `sozlesme_id` doğrulandıktan sonra zorunlu yapılır; dönem alanlarının kaldırılması aynı sürümde, ayrı SQL scriptiyle yapılır.
- Son aşamada `sozlesme_donemleri`, `sozlesme_donem_id` (`hakedis_sozlesmeler`, `puantaj_mutabakat_donem`) ve `aktif_donem_id` bağımlılıkları kaldırılır; çalışma ağacındaki dönem bağlayıcı `2026_10_22_*` scriptleri sözleşme bazlıya uyarlanır.
- Her aşamada kayıt sayıları, toplam işlem sayıları ve mali toplamlar geçiş öncesi/sonrası mutabakat raporuyla karşılaştırılacak.

## Test Planı

- Aynı firma altında tarihleri çakışan Maraş, Bursa ve Adana işlerinin aynı anda aktif olabildiğini doğrula.
- İş değiştirildiğinde operasyon, personel, araç, bordro, izin, talep, hakediş ve rapor verilerinin birbirine karışmadığını doğrula.
- Fatura ve evrakların iş değişiminden etkilenmeden firma genelinde kaldığını doğrula.
- Personel ve aracın çakışan tarihlerde iki işe atanmasının reddedildiğini; tarihli transferin doğru çalıştığını doğrula.
- Kullanıcının yalnız atandığı işleri seçebildiğini ve başka işe doğrudan URL/API ile erişemediğini doğrula.
- Her dış servis sorgusunun seçili sözleşmenin servis profili ve tarih sınırlarıyla çalıştığını doğrula.
- Mobil SPA, Personel PWA ve masaüstünde aynı sözleşme kapsamının kullanıldığını doğrula.
- Migration’ın tekrar çalıştırılabilirliğini, mevcut KASKİ verilerinin tek sözleşmeye bağlandığını ve belirsiz kayıtların raporlandığını doğrula.
- Geçiş öncesi ve sonrası operasyon adetleri, bordro/hakediş tutarları ve personel/araç sayılarının mutabakatını doğrula.
- Dönem alanları kaldırıldıktan sonra kod tabanında `aktif_donem_id` ve operasyonel `sozlesme_donem_id` kullanımı kalmadığını doğrula (aylık bordro/hakediş dönemleri hariç).

## Varsayımlar

- Mevcut `hakedis_sozlesmeler` kaydı yeni yapının sözleşme/ihale ana kaydıdır.
- Kullanıcı aynı anda tek iş kapsamında çalışır; birleşik çoklu iş operasyon ekranı ilk sürümde olmayacaktır.
- Ana sayfa firma portföyünü ve seçili iş operasyonlarını birlikte gösterecektir.
- Personel ana firmada tekil tutulur ancak aynı tarihte yalnız bir işe atanabilir. Araç ve demirbaş sözleşme bazlıdır.
- Fatura ve evrak yalnız ana firma kapsamındadır.
- Bordro, puantaj, izin ve talepler iş bazlıdır.
- Tarihleri çakışan sözleşmelere izin verilir; kayıt sahipliği tarihten tahmin edilmez, açık `sozlesme_id` ile belirlenir.
- Tüm veriler, karşılaştırmalar ve raporlar tamamen sözleşme bazlıdır; sözleşmeler arası "devam" ilişkisi yoktur. İl bazında veri alınacak sayfalar daha sonra ayrıca belirlenecektir.
- Geçiş tamamlandığında `memory-bank/activeContext.md` ve `memory-bank/progress.md` güncellenecektir.

## Ek A — Tablo Envanteri ve Sınıflandırma

Kaynak: `ersantrc_personel` veritabanı, 188 tablo (yedek tablolar dahil). Satır sayıları 2026-10-22 tarihli yaklaşık değerlerdir. Her tablo için karar: **S** = doğrudan `sozlesme_id` eklenecek, **P** = üst kayıttan miras (kendi kolonu eklenmez), **F** = firma düzeyinde kalır, **A** = tarih aralıklı atama ile bağlanır, **K** = karar bekliyor.

### A.1. Operasyon (S)

| Tablo | Satır | Not |
|---|---|---|
| yapilan_isler | ~18.000 | 353 kaydın `firma_id` değeri 0; otomatik taşınmaz, rapora yazılır |
| yapilan_isler_sorgu | 229 | |
| ihbarlar | 1.212 | `ihbar_atamalar`, `ihbar_fotograflari`, `ihbar_tarihce` → P |
| kacak_kontrol | 1.221 | `kacak_kontrol_fotograflari`, `kacak_sicil_eksik`, `kacak_teslim_takip` aynı kapsamda S |
| kesme_acma_islem | 0 | `kesme_acma_islem_foto`, `_gunlugu`, `_kontrol_durum`, `_uyari`, `_uyari_gerekce`, `_nobet_taslak` → P |
| kesme_acma_kontrol_madde, kesme_acma_kural_degeri | 12 / 62 | Parametre; sözleşme bazlı (S) |
| endeks_okuma | ~88.000 | `endeks_okuma_detay`, `_detay_dosya` → P. İndeks zorunlu |
| sayac_degisim | ~19.000 | İndeks zorunlu |
| ekip_gunluk_durum, ekip_mahalle_atama | 0 | S |
| nobetler, nobet_plani, nobet_ilce_plani, telefon_nobet | 573 / 34 / 15 / 36 | `nobet_degisim_talepleri`, `nobet_devir_kayitlari`, `nobet_bildirim_loglari` → P |
| personel_hareketleri | ~21.600 | S (iş bağlamı); giriş/çıkış kayıtlarının sahipliği K |
| personel_fazla_mesaileri | 6 | S |
| puantaj_otomasyon_kilitleri | 0 | S |
| puantaj_mutabakat_donem | 0 | Zaten `sozlesme_donem_id` var; `sozlesme_id`'ye çevrilecek. Alt tablolar (`_calisma`, `_fark`, `_guncel`, `_snapshot`) → P |
| mahalle, mahalle_mesaj | 99 / 0 | K: ortak tanım ise nullable `sozlesme_id` |
| aparat_hareket, aparat_sayim, aparat_stok, aparat_tipleri, aparat_transfer | küçük | K |

### A.2. Bordro, izin, talep (S, kişi bazlı)

| Tablo | Satır | Not |
|---|---|---|
| bordro_donemi | 18 | `firma_id`, `baslangic_tarihi`, `bitis_tarihi` zaten var; `sozlesme_id` eklenecek ve dönem tarihleri sözleşme aralığına göre açılacak (Ek D-1) |
| bordro_personel | ~1.600 | `donem_id` üzerinden miras (P). Ay ortası transfer ve sözleşme bitişinde ayrı dönem satırları Ek D-1'de |
| bordro_parametreleri | 42 | S veya nullable ortak: K |
| bordro_yayin, bordro_kapanis_arsiv | 3 / 0 | Snapshot ve mühür zinciri sözleşme bazlı sürdürülür |
| personel_izinleri, personel_izin_bakiyeleri, izin_onaylari | 1.628 / 0 / 934 | S; bakiye sözleşme geçişinde taşınır mı: K |
| personel_talepleri | 245 | S |
| personel_avanslari, personel_kesintileri, personel_ek_odemeler | 97 / 4.248 / 8.158 | Bordro dönemi üzerinden P; sürekli ek ödemeler S |
| personel_icralari | 33 | F (personel borcu, işe bağlı değil) |
| personel_is_turu_ucretleri, is_turu_ucret_gecmisi | 11 / 413 | S (ücret sözleşme bazlı) |
| personel_calisma_gecmisi, personel_gorev_gecmisi, personel_ekip_gecmisi | 184 / 198 / 224 | Atama kaynağı olarak A tablosuna dönüştürülecek |

### A.3. Hakediş

| Tablo | Not |
|---|---|
| hakedis_sozlesmeler | Ana kayıt. Mevcut `sozlesme_donem_id` kolonu kaldırılacak |
| hakedis_donemleri, hakedis_kalemleri, hakedis_is_revizyonlari, hakedis_sure_uzatimlari | `sozlesme_id` zaten var |
| hakedis_miktarlari, hakedis_is_revizyon_kalemleri | `hakedis_donem_id` / revizyon üzerinden P |

### A.4. Personel, araç, envanter (A veya K)

| Tablo | Satır | Karar |
|---|---|---|
| personel | 182 | A: atama kaynağı mevcut `personel_calisma_gecmisi` (Ek C) |
| araclar | 86 | S: sözleşme bazlı; ayrı atama tablosu açılmaz. Sözleşmeler arası taşıma aktarımla yapılır (Ek D-4) |
| arac_zimmetleri, arac_km_kayitlari, arac_km_bildirimleri, arac_yakit_kayitlari, arac_servis_kayitlari, arac_bakim_kayitlari, arac_sigorta_kayitlari, arac_zimmet_fotograflari | 276 / 7.768 / 7.520 / 2.013 / 149 / 0 / 0 / 2.207 | S: kayıt işlendiği andaki aracın sözleşmesini taşır; aktarımdan sonra geçmiş kayıtlar eski sözleşmede kalır. Araç zimmeti aktarımda kapatılır |
| demirbas, demirbas_zimmet, demirbas_hareketler, demirbas_servis_kayitlari, demirbas_zimmet_fotograflari | 30.761 / 27.218 / 51.243 / 125 / 893 | S: demirbaş ve zimmetleri sözleşme bazlıdır. Mevcut kayıtlar 1 nolu sözleşmeye bağlanır. `demirbas_kategorileri` ortak tanım (nullable) |
| personel_giris_loglari, personel_canli_konumlari, personel_konum_istekleri | 78.988 / 97 / 6.360 | F (log); sorgu filtresi atamadan türetilir |

### A.5. Firma düzeyinde kalanlar (F)

faturalar, fatura_satirlari, fatura_tahsilatlari, efatura_*, evrak_takip, evrak_takip_ekleri, evrak_takip_onaylari, evrak_sablonlari, evrak_sablon_ekleri, cari, cari_hareketleri, kasalar, kasa_hareketleri, gelir_gider, gelir_gider_turu, manuel_giderler, firmalar, users, user_*, permissions, permission_policies, menus, settings, sgk_*, kep_*, uets_*, destek_*, bildirimler, bildirim_kuyrugu, push_subscriptions, duyurular, formlar, notlar, not_defterleri, gorevler, gorev_listeleri, rehber, system_logs, ai_agent_*, observer_session_logs, il, ilce, resmi_tatiller, icra_daireleri, tanimlamalar (karma; satır bazında nullable `sozlesme_id`, bkz. Ek B-5).

### A.6. Kapsam dışı / temizlik

`bak_20261009_sgk_calisma`, `bak_20261009_sgk_personel`, `perm_mig_bak_20261006_205757_*` (5 tablo), `temp_Table`. Migration'a dahil edilmeyecek; temizlik ayrıca değerlendirilecek.

## Ek B — Envanterden Çıkan Bulgular ve Açık Kararlar

1. **Bordro ve puantaj bölünmesi (karara bağlandı).** Bordro kaydı `bordro_donemi` → `bordro_personel` zinciriyle kişi ve ay bazındadır. Personel ay ortasında işten işe geçerse seçenekler: (a) ay sonu atamasına göre tek sözleşmeye yazmak, (b) iki sözleşmede ayrı bordro satırı üretmek. **Karar verildi:** sözleşme bitişinde çıkış, yeni sözleşmede yeniden giriş yapılacağı için seçenek (b) uygulanır; bkz. Ek C.
2. **Mevcut sözleşme verisi (karara bağlandı).** `hakedis_sozlesmeler` içinde yalnız 1 nolu sözleşme kaldı (2 nolu kayıt kullanıcı tarafından silindi, 2026-10-22 itibarıyla doğrulandı). Mevcut KASKİ operasyon, personel, araç ve demirbaş verisi buna bağlanır. Migration öncesi silinen kayda bağlı yetim `hakedis_donemleri`, `hakedis_kalemleri` vb. kayıt olup olmadığı kontrol edilip raporlanır.
3. **Dönem tabloları.** `sozlesme_donemleri` içindeki 2 kayıt (1. Dönem 2025-11-03 → 2026-12-31 aktif, 2. Dönem 2026-11-05 → 2027-12-31 pasif) dönüştürülmez; dönem işlemleri iptal edildiği için tablo yedeklenip kaldırılır. 2. dönem için sözleşme gerekiyorsa kullanıcı normal sözleşme olarak girer.
4. **Firma izolasyonu eksik tablolar.** `faturalar`, `cari`, `kasalar`, `gelir_gider`, `bordro_personel`, `personel_izinleri`, `personel_talepleri`, `demirbas_hareketler` tablolarında `firma_id` kolonu yok; firma bağı üst kayıt üzerinden kurulmuş. Sözleşme erişim kontrolü bu tablolarda JOIN gerektirir. Her sorgu için model katmanında tek tip kapsam yardımcısı yazılacak.
5. **`tanimlamalar` tablosu (~2.400 satır).** Karma tanım deposu. Satırların hangi `grup` değerlerinin sözleşme bazlı, hangilerinin ortak olduğu ayrı bir listeyle çıkarılacak; ortak satırlarda `sozlesme_id IS NULL` koşulu tüm sorgularda tek yardımcıdan gelecek.
6. **Mevcut yarım çalışma.** Veritabanında `hakedis_sozlesmeler.sozlesme_donem_id` ve `puantaj_mutabakat_donem.sozlesme_donem_id` kolonları, çalışma ağacında `2026_10_22_*` scriptleri ve `ActiveContractPeriod.php` var. Dönem işlemleri iptal edildiği için bunlar sözleşme bazlıya çevrilir: ana sayfa ve Mobil SPA verileri `ActiveContractScope` (sözleşme kapsamı) kullanır, dönem kolonları kaldırma scriptiyle düşürülür. Bu çevrim uygulama sırasında 2. adımın (kapsam yardımcısı) ilk işidir.
7. **Fatura–hakediş bağı.** `hakedis_donemleri.fatura_id` bağı korunur. Fatura firma düzeyinde kalır, hakediş sözleşme düzeyindedir; bu bağ faturanın hangi sözleşmeye ait olduğunu zaten gösterir ve ek kolon gerekmez.
8. **Yeni yetkiler.** "Sözleşme / İhale Yönetimi" ve "Sözleşme Erişimi" için `permission_key`, menü, politika ve Süper Admin / Firma Sahibi ataması (AGENTS.md §2) ayrı SQL scriptinde verilecek. Mevcut `tanimlamalar/donem-tanimlari` yetkisi yeni ekrana yönlendirilecek, eski yetki pasife alınacak.
9. **Uygulama sırası.** (1) Yeni tablolar ve nullable kolonlar, (2) kapsam yardımcısı ve oturum, (3) hakediş ve operasyon, (4) personel çalışma kayıtları, araç ve demirbaş sözleşme bağlantısı, (5) bordro, izin, talep, (6) mobil istemciler, (7) zorunlu kılma ve eski dönem bağımlılıklarının kaldırılması. Her aşama için rollback scripti ve öncesinde yedek alınacak.

## Ek C — Personelin Sözleşme Bitişinde Çıkış ve Yeni Sözleşmede Yeniden Giriş

Karar: Personel sözleşme bitiş tarihinde işten çıkış yapılır, yeni sözleşmede yeniden işe başlatılır. Personel ana kaydı (`personel`) tekil kalır; her sözleşmedeki çalışma süresi ayrı bir çalışma kaydıdır.

### C.1. Veri modeli
- Mevcut `personel_calisma_gecmisi` (MyISAM motorunda; transaction ve FK için önce InnoDB'ye çevrilecek, ayrı SQL scriptiyle) (`ise_giris_tarihi`, `isten_cikis_tarihi`, `isten_ayrilis_nedeni`, `sgk_yapilan_firma`, `personel_sinifi` vb.) zaten bir personelin birden fazla giriş/çıkışını tutuyor (184 kayıt, 176 personel). Ayrı bir atama tablosu açılmayacak; bu tablo atama kaynağı olacak.
- Tabloya `sozlesme_id` (nullable → doğrulamadan sonra zorunlu), `silinme_tarihi` ve `is_active` eklenecek. Her çalışma kaydı tek bir sözleşmeye bağlıdır.
- Kural: aynı personelin tarihleri çakışan iki çalışma kaydı olamaz. Doğrulama sunucuda, transaction içinde ve personel satırı `FOR UPDATE` ile kilitlenerek yapılır.
- `personel.ise_giris_tarihi` / `isten_cikis_tarihi` alanları, en güncel çalışma kaydının özeti olarak model katmanında senkron tutulur; doğrudan düzenlenmez.
- `personel` tablosuna `ilk_ise_giris_tarihi` (date) eklenir. Değer personelin ilk çalışma kaydının giriş tarihidir; devirlerde değişmez, yalnızca yetkili düzeltmeyle değiştirilir. Kıdem ve yıllık izin hakedişi bu alandan hesaplanır. `ise_giris_tarihi` ise güncel sözleşmedeki giriş tarihini gösterir.
- Mevcut kayıtlar için ayrı SQL scriptiyle geri doldurma yapılır: `ilk_ise_giris_tarihi` = personelin `personel_calisma_gecmisi` içindeki en eski `ise_giris_tarihi`; çalışma kaydı yoksa `personel.ise_giris_tarihi`. İkisi de boşsa kayıt listelenir, tahmin edilmez.
- Personel formunda ve detay sayfasında alan `App\Helper\Form` ile gösterilir; yalnızca yetkili kullanıcı düzenleyebilir ve her değişiklik loglanır.
- Çıkışta kapatılacak personel bağlı kayıtlar (hepsi çıkış tarihiyle kapatılır, silinmez):
  - `personel_ekip_gecmisi`: açık ekip kodu ve ekip şefliği satırı.
  - `personel_gorev_gecmisi`: açık görev, departman ve maaş durumu satırı.
  - `personel.ekip_no` ve `personel.ekip_bolge`: güncel alanlar boşaltılır; yeni sözleşmede yeniden atanır.
  - `ekip_mahalle_atama`, `nobet_plani`, `nobet_ilce_plani`, `telefon_nobet`: çıkış tarihinden sonraki açık planlar kaldırılır veya yeni sözleşmeye taşınmak üzere listelenir.
  - Mobil SPA ve Personel PWA oturumları ile cihaz kayıtları kaynak sözleşme kapsamında geçersiz kılınır; yeni giriş yeni sözleşme kapsamıyla açılır.

### C.2. Sözleşme Kapanış / Personel Devri akışı
- Otomatik çalışmaz; yetkili kullanıcı Sözleşme / İhale Yönetimi ekranından "Personel Devri" sihirbazını çalıştırır.
  1. Kaynak sözleşme ve hedef sözleşme seçilir. Hedef sözleşme aktif olmalı ve aynı firmaya ait olmalıdır; taslak durumu yoktur.
  2. Kaynak sözleşmede açık çalışma kaydı olan personel listelenir; devredilecekler işaretlenir. Devredilmeyenler için normal çıkış gerekçesi girilir.
  3. Çıkış tarihi varsayılan olarak kaynak sözleşmenin bitiş tarihi, yeniden giriş tarihi hedef sözleşmenin başlangıç tarihidir. İkisi de değiştirilebilir; aralarındaki boşluk uyarı olarak gösterilir.
  4. Hedef sözleşmedeki ekip kodu, görev ve maaş durumu her personel için sihirbazda belirlenir; boş bırakılırsa personel "atama bekliyor" durumunda yeni sözleşmeye girer ve ekip gerektiren işlemlerde seçilemez.
  5. Onayda tek transaction içinde: eski çalışma kaydı kapatılır (`isten_ayrilis_nedeni` = "Sözleşme sonu / devir"), C.1'deki bağlı ekip, görev ve plan kayıtları kapatılır, yeni çalışma kaydı ve yeni ekip/görev satırları açılır, log yazılır. Biri başarısızsa hiçbiri uygulanmaz.
- Sözleşme bitiş tarihi geçmiş ve açık personeli kalan sözleşmeler için sistem uyarı gösterir; çıkışı kendisi yapmaz.
- Tamamlandı durumuna geçiş, açık çalışma kaydı kalan sözleşmede engellenir (devir veya çıkış yapılmadan sözleşme kapatılamaz).

### C.3. Bordro, puantaj, izin etkisi
- Çıkış ay sonuna denk gelmiyorsa o ay iki bordro satırı oluşur: eski sözleşmede çıkış tarihine kadar, yeni sözleşmede giriş tarihinden itibaren. Gün bazlı hesaplar her sözleşmede kendi aralığının kesişimiyle yapılır (AGENTS.md §6 mantığı). Her satır kendi sözleşmesinin snapshot'ıdır (AGENTS.md §5.1).
- Kümülatif vergi matrahı firma bazında yıl içi okunur (Ek D-2). Aynı firmadaki sözleşmeler arasında kesintisiz devam eder; SGK gün sayısı personel bazında ay içinde 30 günü aşamaz.
- Yeni giriş, kayıt olarak yeni işe giriş sayılsa da kıdem ve yıllık izin süreklilik gösterir (karar C.6-2). İzin hakediş tarihi `ilk_ise_giris_tarihi` alanından hesaplanır; yeni sözleşme izin bakiyesini sıfırlamaz. 
- Eski sözleşmede kalan açık kayıtlar (onaylanmamış izin, bekleyen talep) çıkış anında bloklayıcı olarak listelenir; sonuçlandırılmadan çıkış yapılamaz.

### C.4. Taşınan ve taşınmayan kayıtlar
| Kayıt | Davranış |
|---|---|
| Geçmiş puantaj, bordro, izin, talep | Eski sözleşmede kalır, değişmez |
| İzin bakiyesi | Personel bazlı süreklilik; devir kaydı log'lanır |
| Avans bakiyesi, icra | Personel bazlı; sözleşmeden bağımsız devam eder |
| Sürekli ek ödeme / kesinti | Eski sözleşmede `bitis_donemi` ile kapatılır; yeni sözleşmede devam edecekler yeniden tanımlanır |
| Araç, demirbaş ve personel zimmeti | Devirde açık zimmetler çıkış tarihiyle kapatılır (iade kaydı); yeni sözleşmede yeniden zimmet verilir. Demirbaş sözleşme bazlı olduğundan devredilecek demirbaşlar sihirbazda seçilir ve `demirbas_hareketler` içinde sözleşmeler arası transfer hareketi yazılır; seçilmeyenler kaynak sözleşmede kalır |
| Belgeler (evrak, sözleşme PDF) | Personel bazlı kalır |
| SGK işe giriş / çıkış bildirgesi | Çıkış bildirgesi kaynak sözleşmenin, giriş bildirgesi hedef sözleşmenin SGK işyeri sicil numarasıyla hazırlanır (Ek D-3); gönderim SGK modülünden yapılır |

### C.5. Yetki ve log
- Yeni yetki: "Personel Devri" (`permission_key`, menü, politika ve Süper Admin / Firma Sahibi ataması AGENTS.md §2'ye göre, ayrı SQL scriptinde).
- Her devir, çıkış ve giriş için kullanıcı, tarih, kaynak/hedef sözleşme ve personel listesiyle log yazılır. Toplu işlem tek bir devir kimliğiyle gruplanır; geri alma yerine "devri iptal" işlemi yalnız kaynak sözleşme dönemi açıkken ve hedefte bordro oluşmamışsa yapılabilir.

### C.6. Kararlar
1. **Boşluk olabilir.** Çıkış ve yeniden giriş arasında gün boşluğu serbesttir; sihirbaz boşluk gün sayısını gösterir, engellemez. Boşluk günlerinde bordro, puantaj ve SGK günü üretilmez. Boşluk, yıllık izin ve kıdem sürekliliğini bozmaz (madde 2).
2. **Yıllık izin ve kıdem süreklilik gösterir.** İzin hakedişi ve izin bakiyesi `ilk_ise_giris_tarihi` üzerinden kesintisiz sürer; yeni sözleşme bakiyeyi sıfırlamaz.
3. **Kümülatif vergi matrahı firma bazında devam eder.** Personelin yıl içindeki matrahı, çalıştığı firma bazında hesaplanır; aynı firmadaki sözleşme geçişi matrahı sıfırlamaz, personel başka firmaya geçtiğinde yeni firmada yalnız o firmadaki çalışması esas alınır (Ek D-2).
4. **Tazminat ödenmez, kıdem yeni sözleşmeye aktarılır.** Devir çıkışında kıdem veya ihbar tazminatı hesaplanmaz ve çıkış bordrosunda tazminat kalemi üretilmez. Kıdem süresi `ilk_ise_giris_tarihi` ile kesintisiz devam eder; ayrı bir tazminat başlangıç alanı tutulmaz.
   - Devir çıkışı, `isten_ayrilis_nedeni` içinde ayrı bir "Sözleşme sonu / devir (kıdem aktarımlı)" kodu ile kaydedilir. Bu kod tazminat hesaplama ve raporlarında bu çıkışın tazminat doğurmadığını gösterir.
   - Devredilmeyen, normal çıkış yapılan personelde tazminat süreci mevcut kurallarla yürür; bu plan onu değiştirmez.
   - Kullanılmamış yıllık izin günleri çıkışta ücretlendirilmez, bakiye olarak yeni sözleşmeye aktarılır (madde 2).
   - Devirde personelin yazılı onayı ve kıdem aktarım belgesi `personel_evraklar` altında saklanır; belge yüklenmeden devir tamamlanmaz.

### C.7. Test
- Devir sonrası aynı personelin yalnız bir açık çalışma kaydı, yalnız bir açık ekip ve görev satırı olduğunu doğrula.
- Devirde `ilk_ise_giris_tarihi` değerinin değişmediğini; geri doldurma scriptinin tekrar çalıştırılabildiğini ve belirsiz kayıtları raporladığını doğrula.
- Çıkış sonrası eski sözleşme ekibine ait planda personelin görünmediğini doğrula.
- Ay ortası devirde iki bordro satırının, kesişim günlerinin ve toplam tutarın doğru olduğunu doğrula.
- Kümülatif matrah ve izin bakiyesinin sözleşme geçişinde korunduğunu doğrula.
- Açık talebi olan personelin devrinin bloklandığını doğrula.
- Devir transaction'ının yarıda kesildiğinde hiçbir kaydı değiştirmediğini doğrula.
- Başka firmanın sözleşmesine devir isteğinin reddedildiğini doğrula.
- Boşluklu devirde boşluk günlerinde bordro/SGK günü üretilmediğini ve izin bakiyesinin kesintisiz sürdüğünü doğrula.
- Devir çıkışında tazminat kaleminin üretilmediğini, kıdem ve izin bakiyesinin yeni sözleşmeye aktarıldığını ve onay belgesi olmadan devrin tamamlanmadığını doğrula.

## Ek D — Bordro Dönemi, Matrah, SGK, Araç, Dış Servis ve Bütünlük Kuralları

### D-1. Bordro tamamen sözleşme bazlı
- Bordro dönemi sözleşme başına açılır: `bordro_donemi.sozlesme_id` eklenir; `(sozlesme_id, baslangic_tarihi, bitis_tarihi)` benzersizdir ve tarihleri çakışan iki dönem aynı sözleşmede açılamaz.
- Dönem açılırken başlangıç ve bitiş tarihi kullanıcıdan alınır. Varsayılan değer ayın ilk/son günüdür ve sözleşme aralığıyla kesiştirilir (sözleşme ayın 15'inde bitiyorsa bitiş 15, ay ortasında başlıyorsa başlangıç o gün). Kullanıcı tarihleri düzeltebilir ama sözleşme aralığının dışına çıkamaz; çıkış sunucuda reddedilir.
- Bordro hesabı (gün sayısı, fiilî gün, asgari net oranı, yemek yardımı günlük limit hesabı) dönemin kendi tarih aralığına göre yapılır; 15 günlük dönemde 15 gün esas alınır.
- Personel ay içinde iki sözleşmede çalıştıysa iki ayrı bordro satırı oluşur; her satır kendi sözleşmesinin snapshot'ıdır.
- AGENTS.md §5 gereği bu çalışmadan önce `docs/BORDRO_HESAPLAMA_KURALLARI.md` güncellenir ve liste–detay–kayıt çıktılarının aynı olduğu doğrulanır. Bu belge güncellenmeden bordro aşaması başlamaz.
- Doğrulama: aynı personelin aynı takvim ayında tüm sözleşmelerdeki SGK günü toplamı 30'u aşamaz; aşan dönemler hesaplamada uyarı verir.

### D-2. Yıllık kümülatif matrah firma bazında
- Personelin yıl içi kümülatif gelir vergisi matrahı ve ilgili istisnalar `(firma_id, personel_id, yıl)` bazında tutulur ve okunur.
- Aynı firmadaki sözleşmeler arasında matrah devam eder. Personel başka firmaya geçerse yeni firmada o yılda yalnız o firmada çalıştığı süre esas alınır; önceki firmanın matrahı devredilmez.
- Mevcut `add_kumulatif_matrah_devir.sql` mekanizmasının bu kurala uygunluğu uygulama öncesinde incelenir.

### D-3. SGK işyeri sicil numarası
- SGK işyeri sicil numarası sözleşme bazında tutulur (`hakedis_sozlesmeler` içinde yeni alan). Bordro/SGK bildirgeleri, SGK sorguları ve devirdeki çıkış–giriş bildirgeleri ilgili sözleşmenin sicilini kullanır.
- `sgk_ws_tokens` ve `sgk_sorgu_onbellek` (şu an firma düzeyinde) sözleşme bazlı sicil ve kimlik bilgisiyle çalışacak şekilde sözleşmeye bağlanır. Şifreli bilgiler `.env` kaynaklı anahtarla saklanır, kodda varsayılan anahtar bulunmaz.
- SGK modülü Personel, Maaş, İcra, SGK, Bordro ayrımı gereği ayrı kalır.

### D-4. Araç aktarımı
- Araçlar sözleşme bazlıdır: `araclar` kaydı tek bir `sozlesme_id` taşır, ayrı atama tablosu açılmaz. Plaka ve ruhsat bilgisi araç kaydında tek kalır.
- Aktarım demirbaş aktarımıyla (D-8) aynı servisi ve kurgusunu kullanır: aynı araç kaydının `sozlesme_id` değeri hedef sözleşmeye güncellenir, yeni kayıt açılmaz. Araç aktarımı personel devri sihirbazındaki araç adımından veya ayrı "Araç Aktarımı" ekranından yapılır.
- Aktarım geçmişi yeni `arac_sozlesme_gecmisi` tablosuna yazılır (araç, kaynak ve hedef sözleşme, tarih, kullanıcı, açıklama); soft delete alanları AGENTS.md §1'e uygundur. Hareket tarihi hedef sözleşmenin tarih aralığında olmalıdır.
- Açık araç zimmeti aktarımda önce kapatılır (iade kaydı), hedef sözleşmede yeniden zimmet verilir; aynı transaction'da yapılır.
- Yakıt, servis, bakım, sigorta, km ve bildirim kayıtları işlendikleri sözleşmede kalır; aktarım geçmiş kayıtları değiştirmez. Araç raporları kayıt bazlı `sozlesme_id` ile filtrelenir.
- Kaynak ve hedef aynı firmaya ait olmalıdır; erişimsiz veya tamamlanmış/pasif hedefe aktarım reddedilir.
- Yeni yetki "Araç Aktarımı" (AGENTS.md §2 kuralları, ayrı SQL scripti) ve toplu işlem kimliğiyle log gerekir.
- Test: aktarımda araç sayısının ve geçmiş yakıt/km toplamlarının değişmediğini, açık zimmetin kapandığını, erişimsiz sözleşmeye aktarımın reddedildiğini ve yarıda kesilen işlemin hiçbir kaydı değiştirmediğini doğrula.

### D-5. Dış servis verisi (gün bazlı eşleştirme)
- Mevcut dış servis verileri Kahramanmaraş sözleşmelerine aittir. İçe aktarma sırasında kayıt, çekim tarihini kapsayan ve aynı servis profiline sahip sözleşmeye yazılır (örn. 22.10.2026'da çekilen veri, o tarihi kapsayan sözleşmeye).
- Eşleşme tam olarak tek sözleşmeye düşmelidir. Sıfır veya birden fazla sözleşme eşleşirse kayıt yazılmaz, olay loglanır ve kullanıcıya düzeltilecek kayıt olarak raporlanır; sessiz atama yapılmaz.
- Bu kural yalnız dış servis içe aktarımı için istisnadır; diğer tüm kayıtlarda `sozlesme_id` açık verilir.
- Geriye dönük çekimlerde hedef sözleşme kullanıcı tarafından açıkça seçilebilir.

### D-6. Veri bütünlüğü (FK)
- `hakedis_sozlesmeler` üzerinde `(id, firma_id)` benzersiz indeksi tanımlanır. `firma_id` kolonu bulunan tablolarda `(sozlesme_id, firma_id)` birleşik FK, bulunmayan tablolarda `sozlesme_id` basit FK eklenir. Silme `RESTRICT` olur; silmeler soft delete ile yürüdüğü için fiilî silme yapılmaz.
- Çalışma kaydı, atama ve bordro dönemi tablolarında FK için motorun InnoDB olması şarttır. Veritabanında MyISAM olan tablolar: `personel_calisma_gecmisi` (çalışma kaydı olarak kullanılacağı için InnoDB'ye çevrilecek), `il`, `ilce` (ortak tanım, FK dışı) ve `bak_20261009_sgk_calisma` (yedek, kapsam dışı).
- Migration, FK eklemeden önce mevcut kayıtlarda firma uyuşmazlığı ve yetim `sozlesme_id` bulunup bulunmadığını raporlar; temiz olmayan tabloya FK eklenmez.

### D-7. Personel PWA ve Mobil SPA
- Personel PWA'da sözleşme seçici yoktur; personelin kapsamı açık çalışma kaydındaki sözleşmeden türetilir. Sözleşme değişiminde personel yeni sözleşme kapsamını yeni bir alan gibi görür; işlem, ekip ve görev verileri yeni sözleşmeden başlar.
- Açık çalışma kaydı olmayan günlerde (boşluk) personel işlem yapamaz ve "aktif sözleşme yok" mesajı görür.
- PWA'da personel eski sözleşmeye ait belge veya bordro pusulası görmez; yalnız izinlerini görür. Gösterilen izin kayıtları ve işlem tarihleri aktif sözleşmenin tarih aralığıyla sınırlıdır; aralık dışı kayıt listelenmez ve aralık dışına yeni kayıt (izin talebi vb.) girilemez. Bu sınır sunucuda doğrulanır (AGENTS.md §2).
- Aynı sınır tüm istemcilerde geçerlidir: seçili sözleşmeye yazılan kayıtların tarihi sözleşmenin başlangıç–bitiş aralığının dışına çıkamaz.
- Mobil SPA kullanıcıları (yönetici) Süper Admin dışında yalnız erişimi olan sözleşmeleri görür ve sözleşme seçici sunucu kaynaklı yetki haritasından üretilir (AGENTS.md §2).

### D-8. Demirbaş aktarımı (sıfırdan girişsiz)
Telefon, SIM kart, yazıcı gibi demirbaşlar yeni sözleşmede yeniden tanımlanmaz; mevcut kayıt aktarılır. Aktarım yalnız personel devrine bağlı değildir, ayrı bir "Demirbaş Aktarımı" ekranıyla da yapılır; personel devri sihirbazındaki demirbaş adımı aynı servisi kullanır.

- **Tam aktarım (seri numaralı veya tek adetli demirbaş):** Aynı `demirbas` kaydının `sozlesme_id` değeri hedef sözleşmeye güncellenir. Kayıt, seri no, edinme bilgisi, fotoğraf ve geçmiş hareketleri korunur; yeni kayıt açılmaz.
- **Kısmi aktarım (miktarlı stok, örn. SIM kart, sarf):** `demirbas` kayıtları `miktar` ve `kalan_miktar` ile stok tuttuğu için kaynak kaydın `kalan_miktar` değeri düşer; hedef sözleşmede aynı kategori, marka, model ve adla türetilmiş kayıt otomatik oluşur ve kaynak kayda bağlanır (`kaynak_demirbas_id`). Kullanıcıdan yeniden veri girişi istenmez.
- **Görünürlük (karar):** Aktarılan demirbaş yalnız hedef sözleşmede listelenir; kaynak sözleşmenin demirbaş listesinde ve stok toplamlarında görünmez. Aktarım öncesi hareketleri (zimmet, iade, sarf, transfer) demirbaşın detayında korunur ve hedef sözleşmede de izlenebilir. Kısmi aktarımda kaynakta kalan miktar kaynak sözleşmede görünür. Araç kaydı da aynı şekilde yalnız hedef sözleşmede görünür; geçmiş yakıt, km ve servis kayıtları ise işlendikleri sözleşmenin raporlarında kalır (D-4).
- **Seçim:** Kategori (Telefon, SIM Kart, Yazıcı, Laptop vb.), lokasyon, durum ve seri no filtresiyle toplu seçim yapılır. Aktarım tarihi ve açıklama girilir.
- **Zimmetli demirbaş:** Açık zimmeti olan demirbaş, aktarımda önce zimmetten düşürülür (iade kaydı, aktarım tarihiyle); hedef sözleşmede yeniden zimmet verilir. Personel devrinde zimmetten düşme ve aktarım aynı transaction'da yapılır.
- **Hareket kaydı:** `demirbas_hareketler` içine sözleşmeler arası transfer hareketi yazılır (kaynak ve hedef sözleşme, miktar, tarih, kullanıcı). Mevcut `hareket_tipi` değerleri zimmet, iade ve sarf olduğundan "transfer" değeri ayrı SQL scriptiyle eklenir.
- **Kısıtlar:** Kaynak ve hedef aynı firmaya ait olmalıdır; kaynak ile hedef aynıysa veya hedef tamamlandı/pasif ise işlem reddedilir. Hareket tarihi hedef sözleşmenin tarih aralığında olmalıdır.
- **Yetki ve log:** "Demirbaş Aktarımı" için yeni yetki (AGENTS.md §2 kuralları, ayrı SQL scripti); her aktarım toplu işlem kimliğiyle loglanır. Kullanıcı yalnız erişimi olduğu kaynak ve hedef sözleşmeler arasında aktarım yapabilir.
- **Test:** Tam ve kısmi aktarımda toplam miktarın (kaynak + hedef) değişmediğini, geçmiş hareketlerin korunduğunu, zimmetli demirbaşın zimmetten düşüp aktarıldığını, erişimsiz sözleşmeye aktarımın reddedildiğini ve işlemin yarıda kesilince hiçbir kaydı değiştirmediğini doğrula.

### D-9. İleride eklenecek konular
- İl bazlı veri alınacak sayfaların listesi kullanıcıdan gelecek.
- Karşılaştırma ekranlarına ilişkin eklemeler kullanıcıdan gelecek.
