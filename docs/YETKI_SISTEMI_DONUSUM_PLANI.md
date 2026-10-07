# Yetki Sistemi Dönüşüm Planı

## Hedef Mimari

- Yetkinin kalıcı kimliği `permissions.permission_key` alanıdır.
- Menü görünürlüğü `menus.permission_id` ile açıkça bir yetkiye bağlanır.
- Sayfa ve API erişimi menü görünürlüğünden bağımsız olarak Gate/policy katmanında korunur.
- Varsayılan davranış tanımsız rotalar için erişimi reddetmektir.
- Kullanıcılar yetkiyi roller üzerinden alır; istisnai doğrudan yetkiler süreli ve açıklamalı olur.

## Aşama 1 — Uyumlu Veri Temeli

SQL: `sql/2026_10_06_permission_system_phase_1.sql`

- `permissions.permission_key` eklenir ve benzersizleştirilir.
- `menus.permission_id` eklenir.
- Mevcut kayıtlar rota, görünen ad ve eski ID eşitliği önceliğiyle bağlanır.
- Eski `auth_name`, menü linki ve ID eşleştirmeleri henüz kaldırılmaz.
- Eşleşmeyen korumalı menüler raporlanır ve manuel olarak doğrulanır.

Başarı ölçütü: Aktif ve korumalı menülerin tamamı geçerli bir `permission_id` değerine sahip olmalıdır.

## Aşama 2 — Çift Okuma ve Yetki Kataloğu

Durum: Uygulama katmanı tamamlandı. Faz 1 SQL'i uygulandıktan sonra katalogdaki eski eşleşmeler açık ilişkilere dönüşür.

- Menü ve denetim modelleri önce `menus.permission_id`, yoksa eski eşleştirmeyi kullanır.
- Yetki Kataloğu ekranı modül → sayfa → işlem hiyerarşisini gösterir.
- Eski yöntemle eşleşen kayıtlar “Legacy” olarak işaretlenir.
- Eksik, mükerrer ve çelişkili bağlantılar arayüzden raporlanır.

Başarı ölçütü: Üretimde kullanılan tüm menüler açık ilişki üzerinden çözümlenmelidir.

## Aşama 3 — Route ve API Politikaları

Durum: Merkezi politika altyapısı ve sayfa seviyesindeki varsayılan-ret mekanizması hazırlandı. Politika tablosu SQL'i uygulanmadan sistem mevcut menü kontrolüyle uyumlu çalışır. SQL uygulandığında yalnızca kayıtlı sayfa politikaları açılır. Kullanıcı grupları API'si aksiyon bazlı politikaya ve yazma işlemlerinde ortak CSRF kontrolüne geçirildi. Personel API'sindeki 38 GET/POST aksiyonu görüntüleme ve düzenleme yetkileriyle; Bordro API'sindeki 47 aksiyon dönem, parametre ve rapor yetkileriyle ayrı ayrı politika kapsamına alındı. Cari, gelir-gider ve kasa API'lerindeki toplam 28 aksiyon finans yetkilerine bağlandı. Demirbaş API'sindeki 67 aksiyon ana kayıt, sayaç deposu, aparat deposu, zimmet ve servis yetkilerine ayrıldı. Araç takip API'sindeki 56 aksiyon araç yönetimi, puantaj, performans, KM onayı ve AI ajanı yetkileriyle ayrıştırıldı. Puantaj API'sindeki 47 aksiyon veri yükleme, rapor, defter bazlı rapor, sorgulama, kaçak ve ayar yetkilerine ayrıldı. Kaçak API'sindeki 38 aksiyon temel erişim, düzenleme, onay, iptal, arşiv, sicil ve bildirim personeli yetkilerine; ihbar API'sindeki 15 aksiyon görüntüleme ve yönetim yetkilerine bağlandı. Kesme/açma API'sindeki 26 aksiyon mahalle, mesaj, atama, kalan iş, nöbet ve analiz yetkilerine; aparat takip API'sindeki 26 aksiyon depo, tanım, iptal, transfer ve sayım yetkilerine ayrıldı. Notlar API'sindeki 9 kişisel aksiyon ve bildirim API'sindeki 4 kişisel aksiyon yalnızca geçerli oturum isteyen `authenticated_only` politikalarına alındı; görev API'sindeki 21 aksiyon `gorevler`, duyuru API'sindeki 3 aksiyon `duyuru_etkinlik`, bildirim yönetimindeki 3 aksiyon ise `gorev-bildirimler` yetkisine bağlandı. Sistem ayarlarındaki 9 aksiyon genel ayarlar, SGK, evrak ve avans sekme yetkilerine ayrıldı; menü yönetimindeki 9 aksiyon Superadmin politikasıyla, sistem loglarındaki 6 aksiyon `log_kayitlari` yetkisiyle korundu. Evrak, nöbet, talepler, canlı destek, ana sayfa, profil, kullanıcı, rehber, toplu rapor, maliyet, personel takip, tanımlamalar, icra daireleri ve yardım API'leri de merkezi politikaya taşındı. Personel PWA, personel oturumunu tanıyan genel politika ile mevcut kayıt sahipliği ve departman kontrollerini birlikte uygular; `debugSession` yalnızca Superadmin'e ayrılmıştır. Ana `views` katmanındaki 36 API girişinin tamamı merkezi politika katmanına alınmıştır. Kritik kümülatif matrah bakım aksiyonu yalnızca Superadmin'e sınırlandı.

- Her sayfa ve API aksiyonu bir `permission_key` ister.
- Menü görünürlüğü güvenlik kontrolü olarak kullanılmaz.
- Tanımsız rota varsayılan olarak reddedilir.
- Superadmin geçişleri ayrıca loglanır.

Başarı ölçütü: Denetim raporunda korumasız rota kalmamalıdır.

## Aşama 4 — Kullanıcı–Rol Normalizasyonu

Durum: Tarihçeli `user_role_assignments` tablosu ve tekrar çalıştırılabilir geri doldurma SQL'i hazırlandı. Gate, yetki çözümü, rol matrisi ve yetki denetimi yeni tabloyu önceleyip geçiş tamamlanana kadar `users.roles` alanına geri düşer. Kullanıcı ekleme/düzenleme akışı iki kaynağı birlikte günceller.

- `users.roles` virgüllü alanı yerine `user_role_assignments` bağlantı tablosu kullanılır.
- Atayan kullanıcı, başlangıç ve bitiş tarihi kaydedilir.
- Geçiş boyunca eski alan çift okunur; doğrulama sonrasında eski alan kaldırılır.

Başarı ölçütü: `FIND_IN_SET()` kullanan yetki sorgusu kalmamalıdır.

## Aşama 5 — Eski Eşleştirmelerin Kaldırılması

Durum: Canlı geçiş ve ilk doğrulama tamamlandı. Yetki denetimi sayfa politikası eksiklerini, menü–politika çelişkilerini ve eski/yeni rol kaynaklarının uyuşmazlığını raporlar. 30 kullanıcı/rol öznesinde kritik bulgu, uyarı ve rol kaynağı uyuşmazlığı kalmadı; kaynak kodda kullanılan 53 mevcut statik sayfa rotasının tamamı politikaya bağlıdır. Legacy fallback yalnızca en az bir üretim gözlem döngüsü sorunsuz tamamlandıktan sonra kaldırılacaktır.

Geçiş doğrulaması: Aşama 1, 3 ve 4 SQL'leri önce mevcut şema ve gerçek yetki verilerinin geçici kopyasında iki kez, ardından yedek alınarak canlı veritabanında başarıyla çalıştırıldı. Aktif 50 kullanıcı–rol ataması oluştu; eşleşmeyen korumalı menü, geçersiz politika foreign key'i, mükerrer aktif atama veya denetim bulgusu kalmadı. Uygulama sırası ve üretim kontrol listesi `docs/YETKI_SISTEMI_GECIS_UYGULAMA.md` dosyasındadır.

- `permission.id = menu.id`, ad ve rota tahminleri kaldırılır.
- Yalnızca açık foreign key ve kanonik `permission_key` kullanılır.
- Rol matrisi taslak → önizleme → kaydet akışına geçirilir.
- Yetki değişiklikleri etkilenen kullanıcı sayısıyla birlikte loglanır.

## Geri Dönüş Güvencesi

- Her aşama tamamlanmadan bir sonraki aşamadaki eski alan kaldırılmaz.
- Geçiş SQL’leri tekrar çalıştırılabilir olmalıdır.
- Veri silme işlemi ayrı, açıkça onaylanan bir temizlik scriptinde yapılır.
- Liste, detay, sidebar ve doğrudan rota erişimi aynı örnek kullanıcılarla doğrulanır.
