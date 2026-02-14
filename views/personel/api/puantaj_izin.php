<?php
session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\TanimlamalarModel;
use App\Model\PersonelModel;
use App\Model\PuantajModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Helper;

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$firma_id = $_SESSION['firma_id'] ?? 0;

if (!$firma_id) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum sonlanmış.']);
    exit;
}

$Tanimlamalar = new TanimlamalarModel();
$Personel = new PersonelModel();
$Puantaj = new PuantajModel();
$PersonelIzinleri = new PersonelIzinleriModel();

try {
    if ($action === 'get-definitions') {
        $ucretli = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk, ikon FROM tanimlamalar WHERE grup = 'izin_turu' AND ucretli_mi = 1 AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL");
        $ucretli->execute([$firma_id]);
        $ucretli_list = $ucretli->fetchAll(PDO::FETCH_OBJ);

        $ucretsiz = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk, ikon FROM tanimlamalar WHERE grup = 'izin_turu' AND (ucretli_mi = 0 OR ucretli_mi IS NULL) AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL");
        $ucretsiz->execute([$firma_id]);
        $ucretsiz_list = $ucretsiz->fetchAll(PDO::FETCH_OBJ);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'ucretli' => $ucretli_list,
                'ucretsiz' => $ucretsiz_list
            ]
        ]);
    } elseif ($action === 'get-calendar-data') {
        $ay = $_POST['ay'] ?? date('m');
        $yil = $_POST['yil'] ?? date('Y');

        $startDate = "$yil-$ay-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        // Aktif personelleri getir
        $personeller = $Personel->db->prepare("SELECT id, adi_soyadi, resim_yolu, ekip_no, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL ORDER BY adi_soyadi ASC");
        $personeller->execute([$firma_id]);
        $personel_list = $personeller->fetchAll(PDO::FETCH_OBJ);

        // Puantaj ve İzin verilerini getir
        // yapilan_isler (puantaj) ve personel_izinleri tablolarından
        $data = [];

        foreach ($personel_list as $p) {
            $p_info = [
                'id' => $p->id,
                'encrypt_id' => Security::encrypt($p->id),
                'adi_soyadi' => $p->adi_soyadi,
                'tc_kimlik_no' => $p->tc_kimlik_no ?? '',
                'resim' => $p->resim_yolu ?: 'assets/images/users/user-dummy-img.jpg',
                'entries' => []
            ];

            // İzinler
            $izin_stmt = $PersonelIzinleri->db->prepare("
                SELECT pi.id, pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.tur_adi, t.kisa_kod, t.renk 
                FROM personel_izinleri pi
                JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL 
                AND ((pi.baslangic_tarihi BETWEEN ? AND ?) OR (pi.bitis_tarihi BETWEEN ? AND ?))
            ");
            $izin_stmt->execute([$p->id, $startDate, $endDate, $startDate, $endDate]);
            $izinler = $izin_stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($izinler as $izin) {
                $cur = strtotime($izin->baslangic_tarihi);
                $end = strtotime($izin->bitis_tarihi);
                while ($cur <= $end) {
                    $date_str = date('Y-m-d', $cur);
                    if ($date_str >= $startDate && $date_str <= $endDate) {
                        $p_info['entries'][$date_str][] = [
                            'type' => 'izin',
                            'id' => $izin->id,
                            'tip_id' => $izin->izin_tipi_id,
                            'name' => $izin->tur_adi,
                            'kisa_kod' => $izin->kisa_kod,
                            'color' => $izin->renk ?: '#34c38f'
                        ];
                    }
                    $cur = strtotime("+1 day", $cur);
                }
            }

            $data[] = $p_info;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } elseif ($action === 'save-bulk-entries') {
        $data = json_decode($_POST['data'] ?? '[]', true);
        $excelPersonnelIds = json_decode($_POST['excelPersonnelIds'] ?? '[]', true);
        $ay = $_POST['ay'] ?? date('m');
        $yil = $_POST['yil'] ?? date('Y');

        if (empty($data)) {
            throw new Exception("Kaydedilecek veri bulunamadı.");
        }

        $kayit_sayisi = 0;
        $user_id = $_SESSION['user_id'] ?? 0;

        // Ay için tarih aralığı
        $startDate = "$yil-$ay-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        // Transaction başlat - Tüm işlemler tek seferde commit edilecek
        $Puantaj->db->beginTransaction();

        try {
            // Excel'den yüklenen personellerin o aydaki TÜM kayıtlarını sil
            if (!empty($excelPersonnelIds)) {
                $placeholders = implode(',', array_fill(0, count($excelPersonnelIds), '?'));
                $deleteAllStmt = $Puantaj->db->prepare("
                    DELETE FROM personel_izinleri 
                    WHERE personel_id IN ($placeholders) 
                    AND silinme_tarihi IS NULL
                    AND (
                        (baslangic_tarihi >= ? AND baslangic_tarihi <= ?)
                        OR (bitis_tarihi >= ? AND bitis_tarihi <= ?)
                        OR (baslangic_tarihi <= ? AND bitis_tarihi >= ?)
                    )
                ");
                $params = array_merge($excelPersonnelIds, [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
                $deleteAllStmt->execute($params);
            }
            // Prepared statements'ları bir kez hazırla ve tekrar kullan
            // Çakışan tüm izinleri sil (izin tipi fark etmez - aynı tarihte başka izin varsa silinir)
            $deleteStmt = $Puantaj->db->prepare("
                DELETE FROM personel_izinleri 
                WHERE personel_id = ? 
                AND silinme_tarihi IS NULL
                AND (
                    (baslangic_tarihi >= ? AND baslangic_tarihi <= ?)
                    OR (bitis_tarihi >= ? AND bitis_tarihi <= ?)
                    OR (baslangic_tarihi <= ? AND bitis_tarihi >= ?)
                )
            ");

            $insertStmt = $Puantaj->db->prepare("
                INSERT INTO personel_izinleri 
                (personel_id, izin_tipi_id, baslangic_tarihi, bitis_tarihi, toplam_gun, onay_durumu, talep_tarihi) 
                VALUES (?, ?, ?, ?, ?, 'Onaylandı', CURDATE())
            ");

            $onayStmt = $Puantaj->db->prepare("
                INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, aciklama, seviye_no)
                VALUES (?, ?, 'Onaylandı', NOW(), 'Puantaj üzerinden otomatik onaylandı', 1)
            ");

            foreach ($data as $row) {
                $p_id = $row['personel_id'];
                $type_id = $row['type_id'] ?? ($row['typeId'] ?? null);
                $baslangic = $row['baslangic_tarihi'] ?? ($row['date'] ?? null);
                $bitis = $row['bitis_tarihi'] ?? ($row['date'] ?? null);

                if (!$baslangic || !$bitis) {
                    continue;
                }

                // Toplam gün hesapla
                $start = new DateTime($baslangic);
                $end = new DateTime($bitis);
                $toplam_gun = $start->diff($end)->days + 1;

                // Çakışan kayıtları sil (Her durumda sil, çünkü üzerine yazıyoruz veya siliyoruz)
                $deleteStmt->execute([
                    $p_id,
                    $baslangic,
                    $bitis,
                    $baslangic,
                    $bitis,
                    $baslangic,
                    $bitis
                ]);

                // Eğer type_id varsa yeni kayıt ekle
                if ($type_id) {
                    $insertStmt->execute([$p_id, $type_id, $baslangic, $bitis, $toplam_gun]);
                    $izin_id = $Puantaj->db->lastInsertId();

                    // Onay kaydı ekle
                    if ($izin_id) {
                        $onayStmt->execute([$izin_id, $user_id]);
                    }
                    $kayit_sayisi++;
                }
            }

            // Tüm işlemler başarılı, commit et
            $Puantaj->db->commit();

        } catch (Exception $e) {
            // Hata durumunda geri al
            $Puantaj->db->rollBack();
            throw $e;
        }

        $mesaj = $kayit_sayisi == 1
            ? "1 kayıt başarıyla eklendi."
            : "$kayit_sayisi kayıt başarıyla eklendi.";

        echo json_encode(['status' => 'success', 'message' => $mesaj]);
    } elseif ($action === 'save-entry') {
        $personel_ids = $_POST['personel_ids'] ?? []; // Array
        $dates = $_POST['dates'] ?? []; // Array of Y-m-d
        $type_id = $_POST['type_id'] ?? 0;

        if (empty($personel_ids) || empty($dates) || !$type_id) {
            throw new Exception("Yetersiz veri.");
        }

        foreach ($personel_ids as $p_id) {
            foreach ($dates as $date) {
                // Mevcut kaydı kontrol et/sil? (Üzerine yazma mantığı)
                $Puantaj->db->prepare("DELETE FROM personel_izinleri WHERE personel_id = ? AND baslangic_tarihi = ? AND bitis_tarihi = ?")->execute([$p_id, $date, $date]);

                $data = [
                    'personel_id' => $p_id,
                    'izin_tipi_id' => $type_id,
                    'baslangic_tarihi' => $date,
                    'bitis_tarihi' => $date,
                    'toplam_gun' => 1,
                    'onay_durumu' => 'Onaylandı',
                    'talep_tarihi' => date('Y-m-d')
                ];
                $encrypt_id = $PersonelIzinleri->saveWithAttr($data);
                $izin_id = Security::decrypt($encrypt_id);

                if ($izin_id) {
                    $Puantaj->db->prepare("
                        INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, aciklama, seviye_no)
                        VALUES (?, ?, 'Onaylandı', NOW(), 'Puantaj üzerinden otomatik onaylandı', 1)
                    ")->execute([$izin_id, $_SESSION['user_id'] ?? 0]);
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Kayıtlar başarıyla eklendi.']);
    } elseif ($action === 'delete-entry') {
        $id = $_POST['id'] ?? 0;
        if (!$id)
            throw new Exception("ID gerekli.");

        // Önce onayları sil
        $PersonelIzinleri->db->prepare("DELETE FROM izin_onaylari WHERE izin_id = ?")->execute([$id]);

        // Sonra izni tamamen sil
        $res = $PersonelIzinleri->db->prepare("DELETE FROM personel_izinleri WHERE id = ?")->execute([$id]);

        if ($res) {
            echo json_encode(['status' => 'success', 'message' => 'Kayıt veritabanından tamamen silindi.']);
        } else {
            throw new Exception("Kayıt silinemedi.");
        }
    } elseif ($action === 'get-sgk-onaylanmis-raporlar') {
        // SGK Onaylanmış Raporları Getir
        require_once dirname(__DIR__, 3) . '/App/Service/SgkViziteService.php';

        $ay = $_POST['ay'] ?? date('m');
        $yil = $_POST['yil'] ?? date('Y');

        // Ayın ilk ve son günü
        $tarih1 = new DateTime("$yil-$ay-01");
        $tarih2 = new DateTime($tarih1->format('Y-m-t'));

        // Settings'den SGK bilgilerini al
        $Settings = new \App\Model\SettingsModel();
        $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

        $kullaniciAdi = $allSettings['sgk_kullanici_adi'] ?? '';
        $isyeriKodu = $allSettings['sgk_isyeri_kodu'] ?? '';
        $encryptedPassword = $allSettings['sgk_isyeri_sifresi'] ?? '';
        $isyeriSifresi = !empty($encryptedPassword) ? Security::decrypt($encryptedPassword) : '';

        if (empty($kullaniciAdi) || empty($isyeriKodu) || empty($isyeriSifresi)) {
            throw new Exception("SGK bilgileri eksik. Lütfen Ayarlar > SGK Vizite Ayarları bölümünden bilgilerinizi girin.");
        }

        // SGK Servisinden raporları getir
        $sgkService = new SgkViziteService($kullaniciAdi, $isyeriKodu, $isyeriSifresi);
        $raporlar = $sgkService->onayliRaporlariGetir($tarih1, $tarih2);

        // Personel listesini getir (TC Kimlik No eşleştirmesi için)
        $personelStmt = $Personel->db->prepare("SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL");
        $personelStmt->execute([$firma_id]);
        $personelList = $personelStmt->fetchAll(PDO::FETCH_OBJ);

        // TC -> Personel ID eşleşmesi
        $tcToPersonel = [];
        foreach ($personelList as $p) {
            if (!empty($p->tc_kimlik_no)) {
                $tcToPersonel[$p->tc_kimlik_no] = [
                    'id' => $p->id,
                    'adi_soyadi' => $p->adi_soyadi
                ];
            }
        }

        // Raporları işle ve eşleşmeleri bul
        $islenecekRaporlar = [];
        foreach ($raporlar as $rapor) {
            $tc = $rapor['TCKIMLIKNO'] ?? '';
            $personelData = $tcToPersonel[$tc] ?? null;

            // Tarihleri yakala - Tüm olası alanları hiyerarşik olarak tara
            $baslangicRaw = '';
            $checkKeysStart = ['ABASTAR', 'istirahatBaslangicTarihi', 'POLIKLINIKTAR', 'istirahatBaslangic', 'raporBaslangicTarihi', 'YATRAPBASTAR'];
            foreach ($checkKeysStart as $key) {
                if (!empty($rapor[$key])) {
                    $baslangicRaw = $rapor[$key];
                    break;
                }
            }

            // İş başı tarihini yakala (Dökümandaki ISBASKONTTAR veya ABITTAR)
            $bitisRaw = '';
            $checkKeysEnd = ['ISBASKONTTAR', 'ABITTAR', 'istirahatBitisTarihi', 'istirahatBitis', 'raporBitisTarihi', 'YATRAPBITTAR'];
            foreach ($checkKeysEnd as $key) {
                if (!empty($rapor[$key])) {
                    $bitisRaw = $rapor[$key];
                    break;
                }
            }

            // Tarihleri işle ve Formatla (Y-m-d iç işlemler, d.m.Y gösterim için)
            $baslangic = ''; // Puantaj başlangıç
            $bitis = '';     // Puantaj bitiş (İşbaşı - 1 gün)
            $toplam_gun = 0;

            try {
                // Başlangıç Normalizasyon
                if (!empty($baslangicRaw)) {
                    $bDate = (strpos($baslangicRaw, '.') !== false)
                        ? DateTime::createFromFormat('d.m.Y', $baslangicRaw)
                        : new DateTime($baslangicRaw);
                    if ($bDate) {
                        $baslangic = $bDate->format('Y-m-d');
                        $baslangicRaw = $bDate->format('d.m.Y');
                    }
                }

                // İş Başı (bitisRaw) Normalizasyon ve Puantaj Bitiş (bitis) Hesaplama
                if (!empty($bitisRaw)) {
                    $eDate = (strpos($bitisRaw, '.') !== false)
                        ? DateTime::createFromFormat('d.m.Y', $bitisRaw)
                        : new DateTime($bitisRaw);
                    if ($eDate) {
                        $bitisRaw = $eDate->format('d.m.Y'); // Modalda görünen İşbaşı (SGK'dan gelen orijinal tarih)

                        // Puantaj bitişi = İşbaşı - 1 gün (Personel iş başında çalışır)
                        $eDate->modify('-1 day');
                        $bitis = $eDate->format('Y-m-d');
                    }
                }

                // Toplam Gün Hesapla (Yeni aralığa göre: Başlangıç'tan İşbaşı-1'e kadar)
                if (!empty($baslangic) && !empty($bitis)) {
                    $d1 = new DateTime($baslangic);
                    $d2 = new DateTime($bitis);
                    $toplam_gun = $d1->diff($d2)->days + 1;
                    if ($d1 > $d2)
                        $toplam_gun = 0;
                }
            } catch (Exception $e) {
            }

            $islenecekRaporlar[] = [
                'tc_kimlik' => $tc,
                'ad_soyad' => $rapor['SIGORTALIADSOYAD'] ?? ($rapor['AD'] . ' ' . $rapor['SOYAD']),
                'vaka_adi' => $rapor['VAKAADI'] ?? 'Bilinmiyor',
                'baslangic' => $baslangic,
                'baslangic_raw' => $baslangicRaw,
                'bitis' => $bitis,
                'bitis_raw' => $bitisRaw,
                'toplam_gun' => $toplam_gun,
                'is_basi' => $rapor['ISBASKONTTAR'] ?? '',
                'rapor_id' => $rapor['MEDULARAPORID'] ?? '',
                'personel_id' => $personelData ? $personelData['id'] : null,
                'personel_adi' => $personelData ? $personelData['adi_soyadi'] : null,
                'eslesti' => $personelData !== null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $islenecekRaporlar,
            'toplam' => count($islenecekRaporlar),
            'eslesen' => count(array_filter($islenecekRaporlar, fn($r) => $r['eslesti']))
        ]);

    } elseif ($action === 'get-sgk-onay-bekleyen-raporlar') {
        // SGK Onay Bekleyen Raporları Getir
        require_once dirname(__DIR__, 3) . '/App/Service/SgkViziteService.php';

        $ay = $_POST['ay'] ?? date('m');
        $yil = $_POST['yil'] ?? date('Y');

        // Ayın son günü
        $tarih = new DateTime("$yil-$ay-" . date('t', strtotime("$yil-$ay-01")));

        // Settings'den SGK bilgilerini al
        $Settings = new \App\Model\SettingsModel();
        $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

        $kullaniciAdi = $allSettings['sgk_kullanici_adi'] ?? '';
        $isyeriKodu = $allSettings['sgk_isyeri_kodu'] ?? '';
        $encryptedPassword = $allSettings['sgk_isyeri_sifresi'] ?? '';
        $isyeriSifresi = !empty($encryptedPassword) ? Security::decrypt($encryptedPassword) : '';

        if (empty($kullaniciAdi) || empty($isyeriKodu) || empty($isyeriSifresi)) {
            throw new Exception("SGK bilgileri eksik. Lütfen Ayarlar > SGK Vizite Ayarları bölümünden bilgilerinizi girin.");
        }

        // SGK Servisinden raporları getir
        $sgkService = new SgkViziteService($kullaniciAdi, $isyeriKodu, $isyeriSifresi);
        $raporlar = $sgkService->raporlariGetir($tarih, false); // arsiv=false -> sadece aktif raporlar

        // Personel listesini getir (TC Kimlik No eşleştirmesi için)
        $personelStmt = $Personel->db->prepare("SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL");
        $personelStmt->execute([$firma_id]);
        $personelList = $personelStmt->fetchAll(PDO::FETCH_OBJ);

        // TC -> Personel ID eşleşmesi
        $tcToPersonel = [];
        foreach ($personelList as $p) {
            if (!empty($p->tc_kimlik_no)) {
                $tcToPersonel[$p->tc_kimlik_no] = [
                    'id' => $p->id,
                    'adi_soyadi' => $p->adi_soyadi
                ];
            }
        }

        // Sadece seçili ayın raporlarını filtrele
        $ayBaslangic = "$yil-$ay-01";
        $ayBitis = date('Y-m-t', strtotime($ayBaslangic));

        // Raporları işle ve eşleşmeleri bul
        $islenecekRaporlar = [];
        foreach ($raporlar as $rapor) {
            $tc = $rapor['TCKIMLIKNO'] ?? '';
            $personelData = $tcToPersonel[$tc] ?? null;

            // Tarihleri yakala
            $baslangicRaw = $rapor['POLIKLINIKTAR'] ?? '';
            $bitisRaw = $rapor['ISBASKONTTAR'] ?? $rapor['ABITTAR'] ?? '';

            $baslangic = '';
            $bitis = '';
            $toplam_gun = 0;

            try {
                if (!empty($baslangicRaw)) {
                    $bDate = (strpos($baslangicRaw, '.') !== false)
                        ? DateTime::createFromFormat('d.m.Y', $baslangicRaw)
                        : new DateTime($baslangicRaw);
                    if ($bDate) {
                        $baslangic = $bDate->format('Y-m-d');
                        $baslangicRaw = $bDate->format('d.m.Y');
                    }
                }

                if (!empty($bitisRaw)) {
                    $eDate = (strpos($bitisRaw, '.') !== false)
                        ? DateTime::createFromFormat('d.m.Y', $bitisRaw)
                        : new DateTime($bitisRaw);
                    if ($eDate) {
                        $bitisRaw = $eDate->format('d.m.Y');
                        $eDate->modify('-1 day');
                        $bitis = $eDate->format('Y-m-d');
                    }
                }

                if (!empty($baslangic) && !empty($bitis)) {
                    $d1 = new DateTime($baslangic);
                    $d2 = new DateTime($bitis);
                    $toplam_gun = $d1->diff($d2)->days + 1;
                    if ($d1 > $d2)
                        $toplam_gun = 0;
                }
            } catch (Exception $e) {
            }

            // Filtreleme: Ay aralığıyla çakışıyor mu?
            if (!empty($baslangic) && !empty($bitis)) {
                if (!($baslangic <= $ayBitis && $bitis >= $ayBaslangic)) {
                    continue;
                }
            }

            $islenecekRaporlar[] = [
                'tc_kimlik' => $tc,
                'ad_soyad' => $rapor['SIGORTALIADSOYAD'] ?? ($rapor['AD'] . ' ' . $rapor['SOYAD']),
                'vaka_adi' => $rapor['VAKAADI'] ?? 'Bilinmiyor',
                'baslangic' => $baslangic,
                'baslangic_raw' => $baslangicRaw,
                'bitis' => $bitis,
                'bitis_raw' => $bitisRaw,
                'toplam_gun' => $toplam_gun,
                'rapor_id' => $rapor['MEDULARAPORID'] ?? '',
                'rapor_durumu' => $rapor['RAPORDURUMU'] ?? '',
                'personel_id' => $personelData ? $personelData['id'] : null,
                'personel_adi' => $personelData ? $personelData['adi_soyadi'] : null,
                'eslesti' => $personelData !== null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $islenecekRaporlar,
            'toplam' => count($islenecekRaporlar),
            'eslesen' => count(array_filter($islenecekRaporlar, fn($r) => $r['eslesti']))
        ]);

    } elseif ($action === 'sgk-raporlari-isle') {
        // Seçilen SGK raporlarını puantaja işle
        $raporlar = json_decode($_POST['raporlar'] ?? '[]', true);
        $ay = $_POST['ay'] ?? date('m');
        $yil = $_POST['yil'] ?? date('Y');

        // Ayın ilk ve son günü (Sınırlama için)
        $ayBaslangic = "$yil-$ay-01";
        $ayBitis = date('Y-m-t', strtotime($ayBaslangic));

        if (empty($raporlar)) {
            throw new Exception("İşlenecek rapor bulunamadı.");
        }

        // RP (Raporlu) izin türünü bul
        $rpTur = $Tanimlamalar->db->prepare("
            SELECT id FROM tanimlamalar 
            WHERE grup = 'izin_turu' 
            AND (kisa_kod = 'RP' OR tur_adi LIKE '%Raporlu%' OR tur_adi LIKE '%Rapor%')
            AND (firma_id = ? OR firma_id = 0) 
            AND silinme_tarihi IS NULL 
            LIMIT 1
        ");
        $rpTur->execute([$firma_id]);
        $rpTurId = $rpTur->fetchColumn();

        if (!$rpTurId) {
            throw new Exception("'Raporlu' (RP) izin türü bulunamadı. Lütfen önce bir RP izin türü tanımlayın.");
        }

        $user_id = $_SESSION['user_id'] ?? 0;
        $kayit_sayisi = 0;

        // Transaction başlat
        $Puantaj->db->beginTransaction();

        try {
            foreach ($raporlar as $rapor) {
                $personel_id = $rapor['personel_id'] ?? null;
                $baslangic = $rapor['baslangic'] ?? null;
                $bitis = $rapor['bitis'] ?? null;

                if (!$personel_id || !$baslangic) {
                    continue; // Eşleşmeyen veya tarihi olmayan raporları atla
                }

                // Bitiş yoksa başlangıç ile aynı gün
                if (empty($bitis))
                    $bitis = $baslangic;

                // GÜVENLİK VE DÖNEM KONTROLÜ: 
                // Rapor tarihlerini sadece bu ayın sınırları içine çek (Örn: 26 Aralık - 5 Ocak raporu, Ocak ayında sadece 1-5 Ocak olarak işlenir)
                if ($baslangic < $ayBaslangic)
                    $baslangic = $ayBaslangic;
                if ($bitis > $ayBitis)
                    $bitis = $ayBitis;

                // Eğer kısıtlama sonrası rapor süresi geçersizse (tümü başka bir ayda ise) atla
                if ($baslangic > $bitis) {
                    continue;
                }

                // Toplam gün hesapla
                $startDate = new DateTime($baslangic);
                $endDate = new DateTime($bitis);
                $toplam_gun = $startDate->diff($endDate)->days + 1;

                // Çakışan kayıtları sil
                $Puantaj->db->prepare("
                    DELETE FROM personel_izinleri 
                    WHERE personel_id = ? 
                    AND silinme_tarihi IS NULL
                    AND (
                        (baslangic_tarihi >= ? AND baslangic_tarihi <= ?)
                        OR (bitis_tarihi >= ? AND bitis_tarihi <= ?)
                        OR (baslangic_tarihi <= ? AND bitis_tarihi >= ?)
                    )
                ")->execute([
                            $personel_id,
                            $baslangic,
                            $bitis,
                            $baslangic,
                            $bitis,
                            $baslangic,
                            $bitis
                        ]);

                // Yeni kayıt ekle
                $Puantaj->db->prepare("
                    INSERT INTO personel_izinleri 
                    (personel_id, izin_tipi_id, baslangic_tarihi, bitis_tarihi, toplam_gun, onay_durumu, talep_tarihi, aciklama) 
                    VALUES (?, ?, ?, ?, ?, 'Onaylandı', CURDATE(), ?)
                ")->execute([
                            $personel_id,
                            $rpTurId,
                            $baslangic,
                            $bitis,
                            $toplam_gun,
                            'SGK Vizite - ' . ($rapor['vaka_adi'] ?? 'Rapor')
                        ]);

                $izin_id = $Puantaj->db->lastInsertId();

                // Onay kaydı ekle
                if ($izin_id) {
                    $Puantaj->db->prepare("
                        INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, aciklama, seviye_no)
                        VALUES (?, ?, 'Onaylandı', NOW(), 'SGK Vizite üzerinden otomatik işlendi', 1)
                    ")->execute([$izin_id, $user_id]);
                }

                $kayit_sayisi++;
            }

            $Puantaj->db->commit();

        } catch (Exception $e) {
            $Puantaj->db->rollBack();
            throw $e;
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Seçilen dönem için $kayit_sayisi rapor başarıyla puantaja işlendi."
        ]);

    } elseif ($action === 'export-excel') {
        $ay = $_GET['ay'] ?? date('m');
        $yil = $_GET['yil'] ?? date('Y');

        $startDate = "$yil-$ay-01";
        $daysCount = date('t', strtotime($startDate));
        $endDate = "$yil-$ay-$daysCount";

        // Aktif personelleri getir
        $personeller = $Personel->db->prepare("SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL ORDER BY adi_soyadi ASC");
        $personeller->execute([$firma_id]);
        $personel_list = $personeller->fetchAll(PDO::FETCH_OBJ);

        // Ücretsiz izin türlerini getir
        $ucretsizIdsStmt = $Tanimlamalar->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'izin_turu' AND (ucretli_mi = 0 OR ucretli_mi IS NULL) AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL");
        $ucretsizIdsStmt->execute([$firma_id]);
        $ucretsizIds = $ucretsizIdsStmt->fetchAll(PDO::FETCH_COLUMN);

        // PHPSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Puantaj");

        // Header Style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4B39B9']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ];

        // Column style
        $pNameStyle = [
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DASHED]]
        ];

        // Set Headers
        $sheet->setCellValue('A1', 'TC Kimlik No');
        $sheet->setCellValue('B1', 'Personel');
        for ($d = 1; $d <= $daysCount; $d++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
            $sheet->setCellValue($col . '1', $d);
        }
        $toplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $daysCount);
        $fiiliCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $daysCount);
        $sheet->setCellValue($toplamCol . '1', 'Toplam Ç.G.');
        $sheet->setCellValue($fiiliCol . '1', 'Fiili Ç.G.');

        $lastCol = $fiiliCol;
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

        // Column Widths
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($d = 1; $d <= $daysCount; $d++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
            $sheet->getColumnDimension($col)->setWidth(4);
        }
        $sheet->getColumnDimension($toplamCol)->setWidth(12);
        $sheet->getColumnDimension($fiiliCol)->setWidth(12);

        // Fill Data
        $row = 2;
        foreach ($personel_list as $p) {
            $sheet->setCellValue('A' . $row, $p->tc_kimlik_no ?? '');
            $sheet->setCellValue('B' . $row, $p->adi_soyadi);
            $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($pNameStyle);

            // Fetch entries for this personnel
            $izin_stmt = $PersonelIzinleri->db->prepare("
                SELECT pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.kisa_kod, t.renk 
                FROM personel_izinleri pi
                JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL 
                AND ((pi.baslangic_tarihi BETWEEN ? AND ?) OR (pi.bitis_tarihi BETWEEN ? AND ?))
            ");
            $izin_stmt->execute([$p->id, $startDate, $endDate, $startDate, $endDate]);
            $izinler = $izin_stmt->fetchAll(PDO::FETCH_OBJ);

            $dayData = [];
            foreach ($izinler as $izin) {
                $cur = strtotime($izin->baslangic_tarihi);
                $end = strtotime($izin->bitis_tarihi);
                while ($cur <= $end) {
                    $date_str = date('Y-m-d', $cur);
                    if ($date_str >= $startDate && $date_str <= $endDate) {
                        // Tailwind/Bootstrap sınıflarını Hex koduna dönüştür
                        $rawColor = $izin->renk ?: 'bg-success/10';
                        $hexColor = '34C38F'; // Varsayılan yeşil

                        if (strpos($rawColor, 'bg-primary') !== false)
                            $hexColor = 'D1E4FF';
                        elseif (strpos($rawColor, 'bg-amber') !== false || strpos($rawColor, 'warning') !== false)
                            $hexColor = 'FEF3C7';
                        elseif (strpos($rawColor, 'bg-purple') !== false)
                            $hexColor = 'F3E8FF';
                        elseif (strpos($rawColor, 'bg-red') !== false || strpos($rawColor, 'danger') !== false)
                            $hexColor = 'FEE2E2';
                        elseif (strpos($rawColor, 'bg-success') !== false)
                            $hexColor = 'D1FAE5';
                        elseif (strpos($rawColor, '#') !== false) {
                            $hexColor = str_replace('#', '', $rawColor);
                        }

                        $dayData[date('j', $cur)] = [
                            'code' => $izin->kisa_kod,
                            'color' => $hexColor,
                            'tip_id' => $izin->izin_tipi_id
                        ];
                    }
                    $cur = strtotime("+1 day", $cur);
                }
            }

            for ($d = 1; $d <= $daysCount; $d++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
                $cell = $col . $row;

                // Her hücre için yeni bir stil dizisi oluştur (hizalama ve kesikli kenarlık)
                $currentStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DASHED,
                            'color' => ['argb' => 'FFCED4DA']
                        ]
                    ]
                ];

                if (isset($dayData[$d])) {
                    $sheet->setCellValue($cell, $dayData[$d]['code']);
                    $color = trim($dayData[$d]['color']);

                    // Renk hex formatında değilse (örn. names) veya hatalıysa varsayılan kullan
                    if (strlen($color) !== 6) {
                        $color = '34C385';
                    }

                    $currentStyle['font'] = [
                        'bold' => true,
                        'color' => ['argb' => 'FF000000']
                    ];
                    $currentStyle['fill'] = [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF' . strtoupper($color)]
                    ];
                }

                $sheet->getStyle($cell)->applyFromArray($currentStyle);
            }

            // Totals
            $unpaidCount = 0;
            foreach ($dayData as $dEntry) {
                if (in_array($dEntry['tip_id'], $ucretsizIds)) {
                    $unpaidCount++;
                }
            }
            $allEntriesCount = count($dayData);

            $toplamCalismaGunu = $daysCount - $unpaidCount;
            $fiiliCalismaGunu = $daysCount - $allEntriesCount;

            $sheet->setCellValue($toplamCol . $row, $toplamCalismaGunu);
            $sheet->setCellValue($fiiliCol . $row, $fiiliCalismaGunu);

            // Center totals
            $sheet->getStyle($toplamCol . $row . ':' . $fiiliCol . $row)->applyFromArray([
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'font' => ['bold' => true],
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
            ]);

            $row++;
        }

        // Headers for file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Puantaj_' . $yil . '_' . $ay . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;

    } else {
        throw new Exception("Geçersiz işlem: " . $action);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}


