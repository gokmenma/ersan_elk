# Sayaç Zimmet Sistemi – Yönetim Ekran Tasarımı

## 1. Ana Dashboard

### KPI Kutuları
- Depo Yeni
- Depo Hurda
- Personelde Yeni
- Personelde Hurda
- KASKİ Alınan
- KASKİ Verilen
- Açık / Fark

### Uyarılar
- Personel bazlı eksikler
- Depo kritik stok
- Açık sayaçlar

### Personel Özeti
| Personel | Yeni | Hurda | Fark |
|----------|------|-------|------|
| Ahmet    | 25   | 20    | -5   |
| Mehmet   | 30   | 30    | 0    |

---

## 2. Depo Hareket Ekranı

| Tarih | İşlem | Tür | Adet | Nereden | Nereye |
|------|------|-----|------|--------|--------|
| 01.04 | KASKİ→Depo | Yeni | 1000 | KASKİ | Depo |
| 02.04 | Depo→Ahmet | Yeni | 50 | Depo | Ahmet |
| 03.04 | Ahmet→Depo | Hurda | 45 | Ahmet | Depo |
| 04.04 | Depo→KASKİ | Hurda | 900 | Depo | KASKİ |

Filtreler:
- Tarih
- Personel
- Tür (Yeni/Hurda)
- İşlem tipi

---

## 3. Personel Zimmet Ekranı

| Personel | Aldığı Yeni | Üzerinde Yeni | Teslim Hurda | Üzerinde Hurda | Fark |
|----------|------------|--------------|--------------|----------------|------|
| Ahmet    | 100        | 25           | 75           | 5              | -5   |
| Mehmet   | 120        | 30           | 90           | 30             | 0    |

### Detay
- 50 yeni aldı
- 30 yeni aldı
- 45 hurda verdi
- 30 hurda verdi

---

## Sonuç

Bu 3 ekran ile:
- Depo kontrol edilir
- Personel zimmeti izlenir
- KASKİ farkı anlık görülür

Sistem gerçek bir yönetim sistemine dönüşür.
