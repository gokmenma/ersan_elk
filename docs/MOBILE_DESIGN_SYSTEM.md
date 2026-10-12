# Ersan Mobile Design System

Mobil SPA'nın görsel kaynağı `mobile-spa/src/design-system.css` dosyasıdır.

## Temel ilkeler

- Açık gri, kesintisiz bir uygulama tuvali
- Beyaz veya yarı saydam, ince sınırlı yüzeyler
- Lacivert ana vurgu; modül renkleri yalnızca ikon seviyesinde
- Başlıklarda Manrope, içerikte DM Sans
- 44px ve üzeri dokunma alanları
- iOS ve Android safe-area desteği
- Yetkiye göre oluşturulan navigasyon ve içerik

## Token grupları

- `--ds-canvas`, `--ds-surface`, `--ds-surface-soft`
- `--ds-ink`, `--ds-muted`, `--ds-line`
- `--ds-navy`, `--ds-blue`
- `--ds-radius-sm`, `--ds-radius-md`, `--ds-radius-lg`
- `--ds-shadow-card`, `--ds-shadow-float`
- `--ds-safe-top`, `--ds-safe-bottom`

Yeni mobil ekranlar kendi renk/radius değerlerini çoğaltmak yerine bu tokenları ve mevcut `ds-*` bileşen kalıplarını genişletmelidir.
