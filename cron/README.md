# Otomatik Görev Sonlandırma Sistemi

Bu sistem, personelin PWA üzerinden başlattığı ancak sonlandırmayı unuttuğu görevleri otomatik olarak sonlandırır.

## 🎯 Çalışma Mantığı

1. **Lazy Check (Anında)**: Personel uygulamayı açtığında, eğer açık bir görevi varsa ve:
   - Başlangıç günü geçmişse VEYA
   - Aynı gün saat 23:50'yi geçmişse
   
   Görev otomatik olarak 23:50 olarak sonlandırılır.

2. **Cron Job (Zamanlı)**: Her gece 23:55'te tüm açık görevler toplu olarak kontrol edilir ve sonlandırılır.

---

## 🔧 Windows Task Scheduler Kurulumu

### Adım 1: Task Scheduler'ı Açın
- `Win + R` tuşlarına basın
- `taskschd.msc` yazın ve Enter'a basın

### Adım 2: Yeni Görev Oluşturun
1. Sağ panelden **"Create Basic Task..."** seçin
2. **Name**: `Ersan ELK - Gorev Sonlandir`
3. **Description**: `Personel görevlerini 23:50'de otomatik sonlandırır`

### Adım 3: Tetikleyici (Trigger)
1. **Daily** seçin
2. **Start**: Bugünün tarihini seçin
3. **Time**: `23:55:00` yazın
4. **Recur every**: `1 days`

### Adım 4: Eylem (Action)
1. **Start a program** seçin
2. **Program/script**: `C:\xampp\php\php.exe`
3. **Add arguments**: `C:\xampp\htdocs\ersan_elk\cron\gorev_sonlandir.php`
4. **Start in**: `C:\xampp\htdocs\ersan_elk\cron`

### Adım 5: Son Ayarlar
1. **Finish** butonuna tıklayın
2. Oluşturulan göreve çift tıklayın
3. **General** sekmesinde:
   - ✅ "Run whether user is logged on or not" seçin
   - ✅ "Run with highest privileges" işaretleyin

---

## 🧪 Test Etme

### Tarayıcıdan Test
```
http://localhost/ersan_elk/cron/test_gorev_sonlandir.php
```

### Komut Satırından Test
```bash
cd C:\xampp\htdocs\ersan_elk\cron
C:\xampp\php\php.exe gorev_sonlandir.php
```

### Manuel Sonlandırma (Test)
```
http://localhost/ersan_elk/cron/test_gorev_sonlandir.php?run=1
```

---

## 📁 Dosya Yapısı

```
ersan_elk/
├── cron/
│   ├── gorev_sonlandir.php      # Ana cron scripti
│   ├── test_gorev_sonlandir.php # Test aracı
│   ├── logs/                    # Log dosyaları (otomatik oluşur)
│   │   └── gorev_sonlandir_YYYY-MM.log
│   └── README.md                # Bu dosya
└── App/
    └── Model/
        └── PersonelHareketleriModel.php # Lazy check mantığı
```

---

## 📊 Log Dosyaları

Log dosyaları `cron/logs/` klasöründe aylık olarak tutulur:
- `gorev_sonlandir_2026-02.log`
- `gorev_sonlandir_2026-03.log`
- ...

---

## ⚠️ Önemli Notlar

1. **Veritabanı Migration**: İlk kullanımdan önce aşağıdaki SQL'i çalıştırın:
   ```sql
   ALTER TABLE personel_hareketleri 
   ADD COLUMN aciklama VARCHAR(255) NULL 
   COMMENT 'İşlem açıklaması (otomatik sonlandırma vb)' 
   AFTER ip_adresi;
   ```

2. **Zaman Dilimi**: Script `Europe/Istanbul` zaman dilimini kullanır.

3. **Hata Durumu**: Eğer `aciklama` kolonu eklenmezse sistem yine çalışır, sadece açıklama kaydedilmez.

---

## 🔄 Bakım

- Log dosyalarını periyodik olarak kontrol edin
- 6 aydan eski log dosyalarını silebilirsiniz
- Test scripti ile sistemi düzenli aralıklarla kontrol edin
