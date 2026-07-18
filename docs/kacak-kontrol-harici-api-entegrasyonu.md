# Kaçak Kontrol Harici API Entegrasyon Kılavuzu

Bu doküman, dış bir sistemin (veya bu döküman verilerek geliştirilecek bir web sayfasının) kaçak/abonesiz tutanak kayıtlarını doğrudan bu sisteme göndermesi için gereken tüm bilgileri içerir.

## Endpoint

```
POST https://app.ersantr.com/api/external/kacak-kontrol.php
Content-Type: application/json
```

## Kimlik Doğrulama

İstek **üç** kimlik bilgisiyle birlikte gönderilmelidir, üçü de doğru olmalıdır:

| Bilgi | Nerede gönderilir |
|---|---|
| API Key | `X-Api-Key` header'ı |
| Kullanıcı adı | JSON gövdesinde `username` alanı |
| Şifre | JSON gövdesinde `password` alanı |

Bu üç değer size ayrıca güvenli bir kanaldan iletilecektir. Aşağıdaki örneklerde `SIZE_VERILECEK_API_KEY`, `SIZE_VERILECEK_KULLANICI_ADI`, `SIZE_VERILECEK_SIFRE` yerine gerçek değerleri yazmanız gerekir.

## Gönderilecek Alanlar

| Alan | Zorunlu mu? | Açıklama |
|---|---|---|
| `tarih` | Evet | Tutanak tarihi. `2026-07-17` veya `17.07.2026` formatlarından biri kabul edilir. |
| `ilce` | Evet | Tutanağın düzenlendiği ilçe (örn: `Onikişubat`). |
| `tutanak_no` | Evet | Tutanak numarası. **Mükerrer kayıt kontrolü bu alan + tarih üzerinden yapılır** — aynı tutanak_no + tarih ikilisi ikinci kez gönderilirse kayıt tekrar oluşturulmaz, `409` hatası döner. |
| `tur` | Hayır (varsayılan: `Kaçak`) | Sadece `Kaçak` veya `Abonesiz` değerlerinden biri olabilir. |
| `abone_adi` | Hayır | Abone adı soyadı. |
| `sayac_no` | Hayır | Sayaç seri numarası. |
| `endeks` | Hayır | Sayaç endeksi. |
| `sayi` | Hayır (varsayılan: `1`) | İşlem/kayıt sayısı, tam sayı. |
| `aciklama` | Hayır | Serbest metin açıklama. |
| `personel_ids` | Hayır | Sistemdeki personel ID'lerinden oluşan tam sayı dizisi. Örn: `[12, 15]`. Biliniyorsa bu tercih edilmelidir. |
| `personel_isimleri` | Hayır | `personel_ids` bilinmiyorsa, personelin tam adı soyadı ile gönderilebilir. Örn: `["Hasan Akkaya", "Onur Akçadağ"]`. Sistemde tam olarak eşleşen isim bulunamazsa o kişi kayda eklenmez, yanıtta `uyari` alanında bildirilir. |

`personel_ids` ve `personel_isimleri` aynı anda gönderilirse sadece `personel_ids` dikkate alınır.

## Yanıtlar

| HTTP Kodu | Anlamı |
|---|---|
| `201` | Kayıt başarıyla oluşturuldu. |
| `400` | Gönderilen veri eksik/hatalı. `hatalar` dizisinde detay bulunur. |
| `401` | API key / kullanıcı adı / şifre yanlış. |
| `405` | POST dışında bir metodla istek atıldı. |
| `409` | Bu `tutanak_no` + `tarih` için zaten bir kayıt var (mükerrer). |
| `429` | Aynı IP'den kısa sürede çok fazla başarısız kimlik doğrulama denemesi yapıldı. |
| `500` | Sunucu tarafında beklenmeyen bir hata oluştu. |

Başarılı yanıt örneği:
```json
{
  "status": "success",
  "message": "Kayıt başarıyla oluşturuldu.",
  "kayit_id": 507
}
```

## Örnek: JavaScript (fetch) — Basit bir web sayfasından gönderim

```html
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kaçak Kontrol Tutanağı Gönder</title>
</head>
<body>
    <h1>Kaçak Kontrol Tutanağı Gönder</h1>

    <form id="tutanakForm">
        <label>Tarih: <input type="date" name="tarih" required></label><br>
        <label>İlçe: <input type="text" name="ilce" required></label><br>
        <label>Tür:
            <select name="tur">
                <option value="Kaçak">Kaçak</option>
                <option value="Abonesiz">Abonesiz</option>
            </select>
        </label><br>
        <label>Tutanak No: <input type="text" name="tutanak_no" required></label><br>
        <label>Abone Adı Soyadı: <input type="text" name="abone_adi"></label><br>
        <label>Sayaç No: <input type="text" name="sayac_no"></label><br>
        <label>Endeks: <input type="text" name="endeks"></label><br>
        <label>Sayı: <input type="number" name="sayi" value="1" min="1"></label><br>
        <label>Açıklama: <textarea name="aciklama"></textarea></label><br>
        <label>Personel (virgülle ayırarak isim yazın): <input type="text" id="personelInput"></label><br>
        <button type="submit">Gönder</button>
    </form>

    <pre id="sonuc"></pre>

    <script>
        // Bu üç bilgi size ayrı ve güvenli bir kanaldan iletilecektir.
        // Tarayıcıda çalışan bir sayfada API key/şifre tutmak güvenli değildir;
        // gerçek kullanımda bu isteğin kendi sunucunuz üzerinden (backend) atılması önerilir.
        const API_URL = 'https://app.ersantr.com/api/external/kacak-kontrol.php';
        const API_KEY = 'SIZE_VERILECEK_API_KEY';
        const USERNAME = 'SIZE_VERILECEK_KULLANICI_ADI';
        const PASSWORD = 'SIZE_VERILECEK_SIFRE';

        document.getElementById('tutanakForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const form = e.target;
            const personelIsimleri = document.getElementById('personelInput').value
                .split(',')
                .map(function (s) { return s.trim(); })
                .filter(function (s) { return s.length > 0; });

            const gonderilecekVeri = {
                username: USERNAME,
                password: PASSWORD,
                tarih: form.tarih.value,
                ilce: form.ilce.value,
                tur: form.tur.value,
                tutanak_no: form.tutanak_no.value,
                abone_adi: form.abone_adi.value,
                sayac_no: form.sayac_no.value,
                endeks: form.endeks.value,
                sayi: parseInt(form.sayi.value, 10) || 1,
                aciklama: form.aciklama.value,
                personel_isimleri: personelIsimleri
            };

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': API_KEY
                    },
                    body: JSON.stringify(gonderilecekVeri)
                });

                const sonuc = await response.json();
                document.getElementById('sonuc').textContent = JSON.stringify(sonuc, null, 2);

                if (response.ok) {
                    form.reset();
                }
            } catch (err) {
                document.getElementById('sonuc').textContent = 'Bağlantı hatası: ' + err.message;
            }
        });
    </script>
</body>
</html>
```

## Örnek: PHP (sunucu tarafından gönderim — önerilen yöntem)

API key ve şifrenin tarayıcı tarafında (JavaScript içinde) bulunması güvenli değildir. Mümkünse bu isteği kendi sunucunuzdan (PHP, Node.js vb.) atın:

```php
<?php
$veri = [
    'username'   => 'SIZE_VERILECEK_KULLANICI_ADI',
    'password'   => 'SIZE_VERILECEK_SIFRE',
    'tarih'      => '2026-07-17',
    'ilce'       => 'Onikişubat',
    'tur'        => 'Kaçak',
    'tutanak_no' => '42697',
    'abone_adi'  => 'Hüseyin Ertanrıdağ',
    'sayac_no'   => '2590726',
    'endeks'     => '78',
    'sayi'       => 1,
    'aciklama'   => 'Açıklama metni',
    'personel_isimleri' => ['Hasan Akkaya', 'Onur Akçadağ']
];

$ch = curl_init('https://app.ersantr.com/api/external/kacak-kontrol.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Api-Key: SIZE_VERILECEK_API_KEY'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($veri, JSON_UNESCAPED_UNICODE));

$yanit = curl_exec($ch);
$httpKod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$sonuc = json_decode($yanit, true);
echo "HTTP $httpKod: " . $yanit . "\n";
```

## Örnek: cURL (komut satırından test için)

```bash
curl -X POST https://app.ersantr.com/api/external/kacak-kontrol.php \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: SIZE_VERILECEK_API_KEY" \
  -d '{
    "username": "SIZE_VERILECEK_KULLANICI_ADI",
    "password": "SIZE_VERILECEK_SIFRE",
    "tarih": "2026-07-17",
    "ilce": "Onikişubat",
    "tur": "Kaçak",
    "tutanak_no": "42697",
    "abone_adi": "Hüseyin Ertanrıdağ",
    "sayac_no": "2590726",
    "endeks": "78",
    "sayi": 1,
    "aciklama": "Açıklama metni",
    "personel_isimleri": ["Hasan Akkaya", "Onur Akçadağ"]
  }'
```

## Notlar

- Aynı `tutanak_no` + `tarih` ikilisi tekrar gönderilirse sistem otomatik olarak reddeder (`409`), tekrar kayıt oluşmaz. Bu sayede aynı isteği güvenle tekrar gönderebilirsiniz (idempotent).
- `personel_isimleri` gönderirken isimlerin sistemdeki kayıtlarla **birebir** (boşluk, büyük/küçük harf dahil) aynı olması gerekir; eşleşmezse o kişi olmadan kayıt oluşur ve yanıtta uyarı döner.
- Tüm istekler (başarılı/başarısız) sunucu tarafında loglanır.
