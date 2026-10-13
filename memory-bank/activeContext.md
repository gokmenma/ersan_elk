# Active Context

## Şu An Ne Üzerinde Çalışılıyor?
- UETS / KEP Entegrasyon Ayarları (`#modalUetsAyarlar`) ve Giden Evrak E-Tebligat Gönderim modalı (`#modalUetsGonder`) form elemanları `App\Helper\Form` (`Form::FormFloatInput()` ve `Form::FormSelect2()`) standartlarına dönüştürüldü.
- `uets_adresi`: `Form::FormFloatInput()` (floating label, `bx bx-id-card` ikon).
- `api_environment`: `Form::FormSelect2()` (floating label, `bx bx-globe` ikon, `dropdownParent: $('#modalUetsAyarlar')`).
- `api_key`: `Form::FormFloatInput()` (`password-field-wrapper`, `btn-toggle-password` ile göster/gizle ikonu, `bx bx-key` sol ikon).
- Modal içerisindeki Select2 elemanları z-index ve katman taşmalarını önlemek için `dropdownParent` ile modal container'a bağlandı.
- CSS tarafında `.password-field-wrapper` ve `.btn-toggle-password` kuralları `assets/css/style.css`'e eklenerek sistem genelinde standartlaştırıldı.

## Sıradaki Adımlar & Odak Noktaları
- Kullanıcı onayından sonra gerçek UETS tebligat çekim ve gönderim testlerinin sürdürülmesi.
