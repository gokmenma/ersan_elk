# Kurumsal Site (Next.js + Personel Sistemi) — Canlıya Alma Kontrol Listesi

Bu liste yeni kurumsal siteyi (`site-next/`) ve personel sistemindeki **Site Yönetimi** modülünü canlıya almak içindir. Eski site (`site/admin`) bu adımlar tamamlanıp doğrulanana kadar çalışmaya devam eder.

## 1. Mimari özeti

```
Ziyaretçi ──► Next.js (site-next, cPanel Node.js)
                 │  X-Api-Key ile sunucudan sunucuya
                 ▼
          Personel sistemi  api/external/*.php   ──►  Site veritabanı (ersantrc_site)
          Yönetim: index.php?p=site-yonetimi/*  ──►  Personel veritabanı (yetki, oturum, log)
```

- Next.js hiçbir veritabanına doğrudan bağlanmaz; içeriği PHP API'lerinden okur ve **60 saniye** önbelleğe alır. Yönetim panelinde yapılan değişiklik sitede en geç yaklaşık 1 dakikada görünür.
- İki ayrı veritabanı vardır: **personel** (mevcut) ve **site** (yeni). Herkese açık siteye ait SQL açığı personel verisine ulaşamaz.

## 2. Veritabanı (sıra önemlidir)

| Sıra | Veritabanı | Betik | Not |
|---|---|---|---|
| 1 | Site (`ersantrc_site`, yeni oluşturulacak) | `sql/2026_11_07_site_yonetimi_SITE_DB.sql` | Tablolar + en sonda isteğe bağlı **örnek veri** bölümleri. Örnek metinler uydurmadır, gerçek içerik girilecekse atlayın. |
| 2 | Personel (`ersantrc_personel`) | `sql/2026_11_07_site_yonetimi_PERSONEL_DB.sql` | Yetkiler, menüler, politikalar. Süper Admin ve Firma Sahibi rollerine otomatik atanır. |
| 3 | Eski site (`ersantrc_website`) | `sql/2026_11_07_site_yonetimi_WEBSITE_DB.sql` | **Yalnızca eski site kariyer sayfası geçici olarak personel ilanlarını gösterecekse.** Değerleri (`personel_url`, `personel_api_key`) doldurmadan çalıştırmayın. Yeni siteye geçince gerekmez. |

- Betikler tekrar çalıştırılabilir (yerelde iki kez çalıştırılıp tekrar kayıt oluşmadığı görüldü).
- Site veritabanı için **ayrı bir MySQL kullanıcısı** açın; yalnızca `ersantrc_site` üzerinde yetkili olsun (personel veritabanında hiçbir yetkisi olmasın).
- Personel veritabanı betiğinden sonra yeni yetkilerin görünmesi için kullanıcıların oturumunu yenilemesi gerekebilir.

## 3. Personel sistemi `.env` (canlı)

```
SITE_DB_HOST=localhost
SITE_DB_NAME=ersantrc_site
SITE_DB_USER=<site veritabanı kullanıcısı>
SITE_DB_PASS=<şifre>
SITE_API_KEY=<en az 32 karakter rastgele anahtar>
SITE_FIRMA_ID=<bildirim alıcılarının firma kimliği>
KARIYER_API_KEY=<en az 32 karakter rastgele anahtar>
KARIYER_FIRMA_ID=<iş ilanlarının firma kimliği>
```

- `SITE_API_KEY` içerik uçları içindir (`site-icerik.php`, `site-hizmetler.php`, `site-projeler.php`, `site-iletisim.php`). `KARIYER_API_KEY` kariyer ilanları uçu (`kariyer-ilanlar.php`) içindir. Anahtar üretmek için: `openssl rand -hex 32`.
- Anahtarlar kod içinde bulunmaz; `.env` dosyası web kökünden erişilemez olmalıdır (kök `.htaccess` zaten engeller, canlıda doğrulayın).
- `uploads/site/` altına web sunucusu kullanıcısı yazabilmelidir (mevcut `uploads/` izinleriyle aynı). İlk görsel yüklemede alt klasörler ve PHP çalıştırmayı engelleyen `.htaccess` otomatik oluşur.

## 4. Next.js uygulaması (cPanel → Setup Node.js App)

**Ortam değişkenleri** (uygulama ayarlarında):

| Değişken | Değer |
|---|---|
| `PERSONEL_URL` | Personel sisteminin kök adresi, sonunda `/` olmadan (ör. `https://personel.ersantr.com`) |
| `PERSONEL_API_KEY` | Personel `.env` içindeki `KARIYER_API_KEY` ile aynı |
| `SITE_API_KEY` | Personel `.env` içindeki `SITE_API_KEY` ile aynı |
| `SITE_URL` | Sitenin açık adresi (ör. `https://www.ersantr.com`); sitemap, robots ve paylaşım etiketleri için |
| `NODE_ENV` | `production` |

> `PERSONEL_URL` değişince **yeniden derleme** gerekir (görsel izin deseni derleme anında üretilir).

**Derleme ve yükleme** (hosting'de derleme yapmayın, yerelde derleyin):

1. Çalışan `next dev` / `next start` süreçlerini durdurun (aynı `.next` klasörü çakışıp "Invariant" / ENOENT hatası verir).
2. `cd site-next && PERSONEL_URL=... npm ci && npm run build`
3. Yüklenecekler: `.next/standalone/` içeriği + `.next/static/` (→ `.next/static`) + `public/`. `node_modules` yüklenmez, standalone kendi bağımlılıklarını getirir.
4. Başlangıç dosyası `server.js`, Node sürümü **20.x** (yerel doğrulama 24'te yapıldı; canlı 20.20.2).
5. Uygulama URL'si ana alan adına bağlanmalı; personel sistemi farklı alt alan adında kalmalıdır.

**Reverse proxy:** İletişim formundaki saatlik 5 mesaj sınırı Next.js'in gördüğü istemci IP'sine bağlıdır. Passenger/Apache arkasında `X-Forwarded-For` başlığının iletildiğinden emin olun; aksi halde tüm ziyaretçiler tek IP görünür ve sınır herkesi birlikte kısıtlar.

## 5. İçerik girişi (ilk gün)

Personel sistemine Süper Admin ile girip **Site Yönetimi** panelinden sırasıyla:

- [ ] **Site Ayarları:** firma adı, telefon, e-posta, adres, çalışma saatleri, hakkımızda, misyon/vizyon, çalışma adımları, slayt metinleri, sosyal medya, SEO başlığı/açıklaması.
- [ ] **Hizmetler:** gerçek metinler ve görseller (örnek görseller canlıya gitmez, panelden yükleyin).
- [ ] **Projeler**, **Referanslar**, **Sık Sorulanlar**, **İstatistikler:** örnek verileri gerçek içerikle değiştirin ya da silin.
- [ ] **Mesajlar:** test mesajı gönderip bildirimin geldiğini doğrulayın (Profil → bildirim ayarlarında "Site Mesajları").
- [ ] Yetkisiz bir kullanıcı ile Site Yönetimi menüsünün görünmediğini doğrulayın.

## 6. Canlı doğrulama

- [ ] Her sayfa 200 dönüyor: `/`, `/hakkimizda`, `/hizmetlerimiz`, `/hizmetlerimiz/<slug>`, `/projeler`, `/projeler/<slug>`, `/referanslar`, `/kariyer`, `/iletisim`; olmayan adres 404 sayfası gösteriyor.
- [ ] Panelde değişiklik → siteye yansıma ≤ ~1 dakika.
- [ ] İletişim formu: geçerli gönderim kaydı mesajlara düşüyor, hatalı gönderimde uyarılar görünüyor, KVKK onaysız gönderilemiyor.
- [ ] Kariyer sayfası yayındaki ilanları gösteriyor, başvuru bağlantısı çalışıyor.
- [ ] `/robots.txt`, `/sitemap.xml` doğru alan adıyla açılıyor; sayfa kaynağında `og:` etiketleri ve `application/ld+json` var.
- [ ] Anahtarsız API isteği 401 dönüyor: `curl -i https://<personel>/api/external/site-icerik.php`
- [ ] `https://<personel>/uploads/site/` altına yüklenmiş `.php` dosyası çalışmıyor (403).
- [ ] Mobil görünüm ve menü kontrol edildi.

## 7. Eski siteden geçiş

1. Yeni site içeriği girilip doğrulandıktan sonra alan adı Next.js uygulamasına çevrilir.
2. Eski adresler (`/hizmetlerimiz/<slug>`, `/projeler/<slug>`, `/iletisim`, `/kariyer`) yeni sitede **aynı yollarla** vardır; eski sitede kullanılan farklı adresler varsa yönlendirme (301) ekleyin.
3. Eski `site/admin` klasörü ve içindeki **migrate.php, set_admin.php, _fix_db.php, final_seed.php, repair_chars*.php, check_*.php, settings_dump.txt, scratch/** gibi dosyalar web kökünden kaldırılır; `db.php` içindeki gömülü veritabanı şifresi iptal edilip değiştirilir.
4. Eski `ersantrc_website` veritabanı yedeklenip bir süre saklanır, sonra kaldırılır.

## 8. Geri dönüş

- Next.js sorunluysa: cPanel'de alan adını eski siteye geri yönlendirin (eski site silinmediği sürece anlık).
- Yönetim modülünde sorun varsa: yeni menüler `is_active = 0` yapılarak gizlenebilir; site veritabanı personel sistemini etkilemediği için ayrıca temizlik gerekmez.

## 9. Bilinen sınırlar

- Slayt görselleri kodda sabittir (`site-next/public/images`); yalnızca slayt metinleri panelden değişir.
- Yeni mesaj bildirimi uygulama içi ve push olarak gider; e-posta bildirimi yoktur.
- Proje ve hizmetlerde tek kapak görseli vardır, galeri yoktur.
- Sayfa başlığı soneki firma adından otomatik oluşur; sabit metinler (menü adları, bazı alt başlıklar) panelden değişmez.
