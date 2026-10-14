# Ersan Mobile — Mobil uygulama tasarımı

14 Ekim 2026 tarihinde yenilenen arayüz; referanstaki sade yüzeyleri, kompakt gezinmeyi ve alttan açılan panelleri Ersan'ın operasyon ekranlarına uyarlar.

Referans: https://www.pinterest.com/pin/558939003771833803/

## Merkezi tasarım sistemi

Renkler, gölgeler, köşe yarıçapları, tipografi ve güvenli alanlar `src/design-system.css` içindeki `--ds-*` tokenlarından yönetilir. `styles.css` mevcut ekranların yerleşimini içerir; temel paleti bu tokenlardan alır. Mevcut modüllerin scoped CSS'leri de temel yüzey ve durum renklerinde aynı tokenları kullanır.

- Mat gri canvas (`--ds-canvas`), beyaz kartlar ve obsidian vurgu yüzeyleri.
- Sistem fontu, belirgin başlıklar, sade ikonlar ve yumuşak gölgeler.
- Ana sayfada gerçek API verisine bağlı saha özeti, hızlı erişim, ekip/filo dağılımı, operasyon sayaçları ve duyurular.
- Personel yüzdeleri sıfır toplamda sıfırdır; gösterim için yapay avatar veya sayılar üretilmez.
- Ortak `TopBar`: firma seçimi, geri dönüş, yetkili kullanıcılar için talepler ve profil.
- Koyu yüzen alt dock: mevcut yetkilere göre sütun sayısı değişir; görünmeyen menüler boş sütun bırakmaz.
- “Daha Fazla”: beyaz alt panel, kaydırılabilir modüller, Escape ile kapanma, odak döngüsü ve arka plan kaydırma kilidi.
- Giriş ve firma seçimi aynı yüzeyleri ve butonları kullanır.
- Ana kontrol alanları en az 44px; form değerleri mobil tarayıcının otomatik yakınlaştırmasını önlemek için 16px.
- Safe-area ve dock yüksekliği için içerik payı; reduced-motion desteği ve görünür klavye odağı.

## Yetkilendirme ve kapsam

Rota, hızlı erişim, alt menü ve modül görünürlüğü oturum API'sindeki yetki haritasından üretilir. API ve iş hesapları bu tasarım çalışmasında değiştirilmemiştir. Görevler/Raporlar ekranlarındaki mevcut prototip verileri bu çalışmada canlı servise dönüştürülmemiştir.

## Doğrulama

`npm run build` üretim çıktısını `dist/` klasörüne üretir. Tarayıcı kontrolleri 320, 390 ve 480px genişliklerde sayfa taşmasını; izinli/izinsiz menüleri; alt panelin odağını, kapanmasını ve gezinme davranışını kapsar. Bu arayüz kontrollerinde API yanıtları örnek verilerle taklit edilir; canlı veri işlemleri ayrı doğrulanır.

Tarayıcı kontrolünü tekrar çalıştırmak için kök projenin mevcut `@playwright/test` bağımlılığı ve Chromium kurulumu gerekir. Mobil SPA geliştirme sunucusu açıkken proje kökünden:

```bash
node mobile-spa/tests/design-smoke.cjs
```

Farklı bir sunucu adresi için `MOBILE_SPA_TEST_URL` kullanılabilir. Ekran görüntüleri işletim sisteminin geçici klasörüne yazılır; gerçek API uçlarına veri gönderilmez.
