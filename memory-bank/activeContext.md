# Active Context

## Şu An Ne Üzerinde Çalışılıyor?
- Personel PWA izin taleplerindeki “3 günden uzun izinler en az 2 hafta önce bildirilmelidir” kuralı yalnızca yıllık izin türüne sınırlandırıldı. Evlilik ve diğer yıllık olmayan izinler bu ön bildirim kontrolünden muaf; istemci ve API doğrulamaları aynı yıllık izin tespitiyle çalışıyor.
- Demirbaş/Zimmet sayaç kayıtlarında hızlı çift tıklama ve eşzamanlı isteklerin ürettiği mükerrer kayıt engellendi. Standart zimmet düğümü istek süresince kilitleniyor; sayaç API akışı doğrudan tablo yazmak yerine `DemirbasZimmetModel::zimmetVer()` kullanıyor ve demirbaş satırı `FOR UPDATE` ile kilitlenerek stok/mükerrer kontrolü atomik yürütülüyor.
- Geçmiş sayaç zimmet temizliği, ortak/tekrarlı seri değerlerinin geniş sonuç üretmesini önlemek için zorunlu `@target_serial_no` ile tek seri bazında çalışacak şekilde daraltıldı. Script aynı seri farklı demirbaş kartlarında bulunsa da en küçük zimmet ID'sini koruyup diğer zimmetleri ve bağlı hareketleri soft delete ediyor; varsayılan önizleme modunda ve veritabanına uygulanmadı. Model kontrolünde boş, `0`, `-`, `yok`, `n/a` benzeri seri değerleri seri bazlı eşleştirmeden çıkarıldı.
- Demirbaş/Zimmet tablosundaki gerçek çift gönderimleri genel seri tekrarlarından ayırmak için aynı firma, seri, personel ve teslim tarihinde 120 saniye içinde oluşmuş aktif sayaç zimmetlerini listeleyen salt-okunur `sql/2026_10_13_find_duplicate_sayac_zimmet.sql` sorgusu hazırlandı.
- Personel PWA Kaçak sayfasında onaylanmış ve iptal edilmemiş tutanaklara ekip üyelerinin sonradan fotoğraf, video veya açıklama ekleyebilmesi tamamlandı. Açıklama `appendKacakMedia` güvenilir gönderim işlemiyle mevcut açıklamanın sonuna ekleniyor; sahiplik, Kaçak departmanı, onay ve aktiflik kontrolleri sunucuda korunuyor.
- Araç silme güvenliği sıkılaştırıldı: silinmemiş yakıt veya KM kaydı bulunan araçlar sunucu tarafında silinmiyor; engellenen denemeler adet, kullanıcı, IP ve gerekçeyle loglanıyor. Bağımlı kaydı olmayan araçların başarılı silme logu araç anık görüntüsü ve bağlı kayıt adetleriyle aynı transaction içinde yazılıyor.
- `06 DFD 805` (ID 159) ve `46 ALA 432` (ID 164) ile aynı anda silinen bağlı kayıtları zaman damgası kontrollü geri getiren tekrarlanabilir script `sql/2026_10_13_restore_deleted_vehicles_06_dfd_805_46_ala_432.sql` olarak hazırlandı; veritabanına uygulanmadı.
- Personel başına tek aktif araç zimmeti engeli kaldırıldı; araç başına tek aktif zimmet kuralı korundu. Personel PWA KM akışı aktif araç listesini kullanıyor, birden fazla araçta seçim istiyor ve seçilen aracın aktif zimmetini API tarafında doğruluyor.
- Araç Takip > Yakıt Kayıtları sekmesindeki araç kaynağı ana araç listesi filtresinden ayrıldı. Yakıt plaka filtresi ve yakıt kayıt modalı artık aktif normal araçlarla birlikte silinmemiş ikame araçları da listeliyor.
- İhbar Yönetimi listesindeki DataTables kolon filtreleri düzeltildi. `IhbarModel::ajaxList()` artık merkezi filtre bileşeninin gönderdiği metin, tarih, boş/dolu ve çoklu seçim kiplerini ayrıştırıp parametrik SQL koşullarına dönüştürüyor. Durum select filtresi seçenekleri, Model katmanından firmanın tüm aktif ihbarlarında gerçekten kullanılan `DISTINCT` durumlar alınarak oluşturuluyor.
- Kaçak İşlemleri > Ekip Özeti ekranındaki 403 hatası giderildi. Kaçak ekranı artık genel `get-report-table` yerine `get-kacak-report-table` aksiyonunu çağırıyor; yeni aksiyon mevcut `kacak_islemleri` yetkisine bağlı bağımsız API politikasıyla korunuyor.
- Veritabanı politika değişikliği `sql/2026_10_13_add_kacak_report_table_policy.sql` içinde tekrarlanabilir script olarak hazırlandı.
- Evrak Takip ekranındaki UETS senkronizasyonu iki aşamalı hale getirildi: `UETS / KEP Senkronize Et` önce canlı gelen evrakları seçim modalında listeliyor, ardından yalnızca kullanıcının seçtiği evrakları sisteme aktarıyor.
- Önizlemede daha önce UETS kimliğiyle aktarılmış kayıtlar seçilemez; aynı tarih + konu + kurum bilgileriyle eşleşen manuel evraklar olası mükerrer olarak sarı uyarıyla gösterilir.
- UETS indirme işleminden sonra durumun `read` olacağı API dokümanına göre önizleme sorgusuna `read` kayıtları da eklendi; HTTP/API hataları artık boş liste gibi gizlenmek yerine kullanıcıya gösteriliyor.
- UETS mesaj kimlikleri istemciye açık gönderilmiyor; seçim anahtarları `Security::encrypt()` ile korunuyor ve aktarım sırasında sunucuda yeniden çözülüp canlı UETS listesinden doğrulanıyor.
- UETS / KEP Entegrasyon Ayarları (`#modalUetsAyarlar`) ve Giden Evrak E-Tebligat Gönderim modalı (`#modalUetsGonder`) form elemanları `App\Helper\Form` (`Form::FormFloatInput()` ve `Form::FormSelect2()`) standartlarına dönüştürüldü.
- `uets_adresi`: `Form::FormFloatInput()` (floating label, `bx bx-id-card` ikon).
- `api_environment`: `Form::FormSelect2()` (floating label, `bx bx-globe` ikon, `dropdownParent: $('#modalUetsAyarlar')`).
- `api_key`: `Form::FormFloatInput()` (`password-field-wrapper`, `btn-toggle-password` ile göster/gizle ikonu, `bx bx-key` sol ikon).
- Modal içerisindeki Select2 elemanları z-index ve katman taşmalarını önlemek için `dropdownParent` ile modal container'a bağlandı.
- CSS tarafında `.password-field-wrapper` ve `.btn-toggle-password` kuralları `assets/css/style.css`'e eklenerek sistem genelinde standartlaştırıldı.

- Mobile SPA (`mobile-spa/src/views/TaleplerView.vue`): Talepler sayfasında mükerrer kategori sekme barı kaldırıldı; üst KPI bento kartı doğrudan kategori geçiş butonları olarak optimize edildi.
- Aktif kategori kartındaki yazı rengi ve kontrastı düzeltildi (`color: #ffffff !important`); başlık ("Avans", "İzin", "Genel") ve sayaçlar koyu zemin üzerinde net ve okunabilir hale getirildi.
- `npm run build` ile üretim derlemesi başarıyla tamamlandı.

## Sıradaki Adımlar & Odak Noktaları
- `sql/2026_10_13_add_kacak_report_table_policy.sql` scriptinin hedef veritabanında uygulanması.
- `sql/2026_10_13_uets_selected_import_policy.sql` scriptinin hedef veritabanında uygulanması.
- Kullanıcı ortamında gerçek UETS verisiyle önizleme, olası mükerrer uyarısı ve seçili aktarım testlerinin sürdürülmesi.
- Mobil SPA Talepler ekranındaki kullanıcı geri bildirimlerinin takip edilmesi.
