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

## 4. Form ve Select2 Standartları
- Masaüstü modüllerde select alanları doğrudan HTML `<select>` etiketi yazılarak oluşturulmayacak; `App\Helper\Form::FormSelect2()` kullanılacaktır.
- Select2 alanının sınıfında `select2` bulunacak ve JavaScript tarafında `.select2()` ile başlatılacaktır.
- Modal içerisindeki Select2 alanlarında açılır listenin modal sınırları ve katman sırası içinde kalması için `dropdownParent` ilgili modal olarak tanımlanacaktır.

## 5. Bordro — Maaşa Dahil Yemek Yardımı (Değiştirilemez İş Kuralı)
- Bu kural `BordroPersonelModel` içindeki hesaplama, liste, bordro detay modalı, Excel ve ödeme dağıtımı yollarının **tamamında aynı şekilde** uygulanacaktır. Bir yol diğerinden farklı hesap yapamaz.
- Maaşa dahil personelde yemek yardımı, sözleşme netini aşan ayrı bir kazanç değildir; asgari net ve banka üzerinden ödenecek RTÇ/HTÇ gibi kalemlerden sonra sözleşme netini tamamlayan bakiyedir.
- Hesap sırası zorunludur: (1) sözleşme net tavanı belirlenir, (2) asgari net, eş yardımı ve banka üzerinden ödenecek RTÇ/HTÇ kalemleri düşülür, (3) kalan tutar yemek yardımı üst limiti olarak alınır, (4) bu tutar fiilî güne bölünür, (5) günlük tutar yukarı yuvarlanır ve günlük limit ile sınırlandırılır, (6) yuvarlanmış günlük tutar fiilî gün ile çarpılır.
- Günlük yuvarlama sonucu oluşan fark ayrı `yuvarlama_farki` olarak kaydedilir ve gösterilir; günlük hesap doğrudan toplam tutara çevrilerek bu kural atlanamaz.
- Yemek yardımı yüksek hesaplanıp banka ödemesinden sonradan “fark kesintisi” düşülemez. Banka limit matrahı, sözleşme neti ve yalnızca kayıtlı yuvarlama farkı dışında aşamaz.
- Bu kuralları değiştiren her çalışma, önce `docs/BORDRO_HESAPLAMA_KURALLARI.md` dosyasını güncellemek ve liste–detay–kayıt hesaplarının aynı çıktıyı verdiğini doğrulamak zorundadır.
