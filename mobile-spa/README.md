# Ersan Mobile SPA

Yeni mobil yönetim arayüzünün Vue 3 tabanlı, mevcut PHP mobil panelinden bağımsız prototipidir.

## Geliştirme

```bash
npm install
npm run dev
```

## Üretim build

```bash
npm run build
```

`dist/` Git'e dahil edilir. Bu nedenle build geliştirme ortamında alınmalı ve kaynak
değişiklikleriyle birlikte commit edilmelidir:

```bash
npm ci
npm run build
git add mobile-spa App/Model/MobilePersonelModel.php
git commit
git push
```

Sunucuda Node veya npm çalıştırmak gerekmez. Güncelleme için:

```bash
git pull
sudo bash mobile-spa/bin/deploy-production.sh
```

Deploy betiği commit edilmiş `dist/` çıktısını kontrol eder, PHP dosyalarının sözdizimini
doğrular ve Apache/PHP OPcache'i yeniler. `npm run dev` yalnızca yerel geliştirme içindir.

Apache vhost belge kökü `mobile-spa/dist` klasörünü göstermelidir. Vue Router alt yollarının çalışması için `public/.htaccess` build sırasında `dist/.htaccess` olarak kopyalanır.

Yerel XAMPP kurulumu için, production build alındıktan sonra:

```bash
sudo bash mobile-spa/bin/install-local-vhost.sh
```

Betik mevcut Apache ve hosts dosyalarını değiştirmeden önce zaman damgalı yedeklerini oluşturur, yapılandırmayı test eder ve yalnızca başarılı yapılandırma üzerinden Apache'yi yeniden yükler.

Giriş ve personel modülünün mobil API bağlantıları aktiftir.
