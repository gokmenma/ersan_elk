# Proje Kuralları ve Standartları (AGENTS.md)

## 1. Veritabanı ve Model Standartları
- PDO kullanılacak ve tüm veritabanı sorgularında prepare()/execute() ile parametrik binding uygulanacak.
- Tüm veritabanı işlemleri Model katmanından geçecek.
- Veritabanı değişiklikleri her zaman ayrı SQL scripti olarak verilecek.
- Silme işlemleri soft delete (`deleted_at = NOW()`, `is_active = 0`) mantığı ile yürütülecek.

## 2. Yetkilendirme ve Güvenlik
- Yetki kontrolü olmayan işlem yapılmayacak.
- Yalnızca Superadmin yetkisindeki sayfalar ve API aksiyonları `Gate::isSuperAdmin()` ile korunacak.
- Hassas nesne ve kayıt ID'leri `Security::encrypt()` ve `Security::decrypt()` ile şifrelenecek.
- HTML çıktısında değişkenler `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` ile sarılacak.

## 3. DataTables Varsayılan Tablo Başlatma Standartları
- **JavaScript Başlatma**: Tüm DataTables tabloları `applyLengthStateSave({ ...getDatatableOptions(), ... })` mantığı ile başlatılacaktır.
- **Canlı Kolon Filtreleme (Header Filters)**:
  - Tablonun `<thead>` kısmındaki `<th>` etiketlerine:
    - Metin aramaları için `data-filter="string"`
    - Seçim filtreleri için `data-filter="select"`
    - Tarih filtreleri için `data-filter="date"`
    nitelikleri eklenecektir.
  - Bu nitelikler eklendiğinde `datatables.init.js` ve `datatable-filters.js` otomatik olarak filtreleme kutularını ve simgelerini tablo başlığına yerleştirir.
- **Sayfa ve Tablo Yerleşimi**:
  - Sayfa başlığı card header içerisinde **yer almayacak**; `layouts/breadcrumb.php` standart yapısı üzerinden sunulacaktır.
  - Kartın üst kısmında durum filtreleri (`status-filter-group`) ve sağ tarafta aksiyon butonu (`personel-action-toolbar`) kullanılacaktır.
