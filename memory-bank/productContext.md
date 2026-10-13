# Product Context

## Nasıl Çalışması Gerektiği ve Kullanıcı Deneyimi
- **Kullanıcı Rolleri & Çoklu Firma**: Firma bazlı veri izolasyonu (`firm_id`) ve rol tabanlı yetki kontrolü (`Gate::has()`, `Gate::isSuperAdmin()`).
- **Masaüstü Paneli Standartları**:
  - Breadcrumb tabanlı sayfa navigasyonu.
  - 4 adet özet KPI kartı ve durum filtreleri (açılır/kapanır animasyonlu yapı, localStorage ile durum hatırlama).
  - Canlı filtreleme (Header Filters) destekli DataTables tabloları (`applyLengthStateSave`).
  - Satır bazlı sağ tık menüsü (Context Menu) ve 27x27px işlem butonları.
  - macOS teması ve soluk floating label/parlak değer odaklı form bileşenleri (`App\Helper\Form`).
- **Mobil SPA Standartları**:
  - Tek elle kullanıma uygun, safe-area uyumlu ve dokunmatik odaklı minimalist tasarım (`--ds-*` tokenları).
  - Sunucu kaynaklı yetki haritasına göre dinamik rota, navbar ve "Daha Fazla" menüsü.
- **Bordro & Maaş İş Kuralları**:
  - Maaşa dahil yemek yardımı kuralları (sözleşme netini aşmayan tamamlama bakiyesi mantığı).
  - Gün bazlı sürekli ek ödemeler için tarih aralığı kesişim hesabı.
