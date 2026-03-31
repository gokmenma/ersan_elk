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

        $current_user_id = $_SESSION['user_id'] ?? 0;
        $db = (new \App\Core\Db())->db;

        // Get restriction from DB instead of hardcoded array
        $restricted_dept = null;
        $is_restricted = false;

        if ($current_user_id && !\App\Service\Gate::isSuperAdmin()) {
            $uStmt = $db->prepare("SELECT yonetilen_departman FROM users WHERE id = ?");
            $uStmt->execute([$current_user_id]);
            $uRow = $uStmt->fetch(PDO::FETCH_OBJ);
            if ($uRow && !empty($uRow->yonetilen_departman)) {
                $is_restricted = true;
                $restricted_dept = $uRow->yonetilen_departman;
            }
        }

        $extra_where = $is_restricted ? " AND p.departman = ?" : "";

        // Aktif personelleri ve o ay veya sonrasında işten çıkanları getir
        $personeller_sql = "
            SELECT p.id, p.adi_soyadi, p.resim_yolu, p.ekip_no, p.tc_kimlik_no, p.isten_cikis_tarihi, p.ise_giris_tarihi,
                   CASE WHEN gg.personel_id IS NOT NULL THEN 1 ELSE 0 END as gorev_gecmisi_var,
                   COALESCE(gg_days.toplam_gun, 0) as gg_toplam_gun
            FROM personel p
            LEFT JOIN (
                SELECT pgg.personel_id
                FROM personel_gorev_gecmisi pgg
                WHERE pgg.baslangic_tarihi <= ?
                  AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
            ) gg ON p.id = gg.personel_id
            LEFT JOIN (
                SELECT pgg.personel_id, 
                       SUM(DATEDIFF(LEAST(COALESCE(pgg.bitis_tarihi, ?), ?), GREATEST(pgg.baslangic_tarihi, ?)) + 1) as toplam_gun
                FROM personel_gorev_gecmisi pgg
                WHERE pgg.baslangic_tarihi <= ? AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
                GROUP BY pgg.personel_id
            ) gg_days ON p.id = gg_days.personel_id
            WHERE p.firma_id = ? 
            AND p.silinme_tarihi IS NULL 
            AND (p.aktif_mi = 1 OR (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi >= ?))
            AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00' OR p.isten_cikis_tarihi >= ?)
            AND (p.disardan_sigortali = 0 OR FIND_IN_SET('puantaj', p.gorunum_modulleri))
            AND (p.ise_giris_tarihi IS NULL OR p.ise_giris_tarihi = '' OR p.ise_giris_tarihi <= ?)
            $extra_where
            AND NOT EXISTS (
                SELECT 1 FROM personel_gorev_gecmisi pgg
                WHERE pgg.personel_id = p.id
                AND pgg.baslangic_tarihi <= ?
                AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
                AND (pgg.maas_durumu = 'Maaş Hesaplanmayan')
            )
            ORDER BY p.adi_soyadi ASC
        ";
        
        $personeller_params = [$endDate, $startDate, $endDate, $endDate, $startDate, $endDate, $startDate, $firma_id, $startDate, $startDate, $endDate];
        if ($is_restricted) {
            $personeller_params[] = $restricted_dept;
        }
        $personeller_params = array_merge($personeller_params, [$endDate, $startDate]);

        $personeller = $Personel->db->prepare($personeller_sql);
        $personeller->execute($personeller_params);
        $personel_list = $personeller->fetchAll(PDO::FETCH_OBJ);

        // Varsayılan tanımlamaları al
        $varsayilan_X = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'X' OR kisa_kod = 'x' OR tur_adi LIKE '%Çalışılan Gün%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $varsayilan_X->execute([$firma_id]);
        $x_tanim = $varsayilan_X->fetch(PDO::FETCH_OBJ);

        $varsayilan_HT = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'HT' OR kisa_kod = 'ht' OR tur_adi LIKE '%Hafta Tatili%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $varsayilan_HT->execute([$firma_id]);
        $ht_tanim = $varsayilan_HT->fetch(PDO::FETCH_OBJ);

        // Puantaj ve İzin verilerini getir
        // 1. Tüm personellerin izinlerini tek seferde çek
        $izin_stmt = $PersonelIzinleri->db->prepare("
            SELECT pi.personel_id, pi.id, pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.tur_adi, t.kisa_kod, t.renk 
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.silinme_tarihi IS NULL AND pi.onay_durumu != 'Reddedildi'
            AND pi.personel_id IN (SELECT id FROM personel WHERE firma_id = ? AND silinme_tarihi IS NULL)
            AND (
                (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
            )
        ");
        $izin_stmt->execute([$firma_id, $endDate, $startDate]);
        $all_izinler = $izin_stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_OBJ);

        $data = [];

        foreach ($personel_list as $p) {
            $p_info = [
                'id' => $p->id,
                'encrypt_id' => Security::encrypt($p->id),
                'adi_soyadi' => $p->adi_soyadi,
                'tc_kimlik_no' => $p->tc_kimlik_no ?? '',
                'resim' => $p->resim_yolu ?: 'assets/images/users/user-dummy-img.jpg',
                'isten_cikis_tarihi' => $p->isten_cikis_tarihi,
                'ise_giris_tarihi' => $p->ise_giris_tarihi,
                'gorev_gecmisi_var' => $p->gorev_gecmisi_var,
                'gg_toplam_gun' => $p->gg_toplam_gun,
                'entries' => []
            ];

            $izinler = $all_izinler[$p->id] ?? [];
            $mevcut_gunler = [];

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
                        $mevcut_gunler[$date_str] = true;
                    }
                    $cur = strtotime("+1 day", $cur);
                }
            }

            // Boş günleri X ve HT ile doldur
            $cur = strtotime($startDate);
            $end = strtotime($endDate);
            $p_giris = $p->ise_giris_tarihi && $p->ise_giris_tarihi != '0000-00-00' ? strtotime($p->ise_giris_tarihi) : 0;
            $p_cikis = $p->isten_cikis_tarihi && $p->isten_cikis_tarihi != '0000-00-00' ? strtotime($p->isten_cikis_tarihi) : PHP_INT_MAX;

            while ($cur <= $end) {
                $date_str = date('Y-m-d', $cur);
                $isWeekend = date('w', $cur) == 0; // Sadece Pazar
                if (!isset($mevcut_gunler[$date_str])) {
                    if ($cur >= $p_giris && $cur <= $p_cikis) {
                        if ($isWeekend && $ht_tanim) {
                            $p_info['entries'][$date_str][] = [
                                'type' => 'default',
                                'id' => 0,
                                'tip_id' => $ht_tanim->id,
                                'name' => $ht_tanim->tur_adi,
                                'kisa_kod' => $ht_tanim->kisa_kod,
                                'color' => $ht_tanim->renk ?: '#f46a6a'
                            ];
                        } elseif (!$isWeekend && $x_tanim) {
                            $p_info['entries'][$date_str][] = [
                                'type' => 'default',
                                'id' => 0,
                                'tip_id' => $x_tanim->id,
                                'name' => $x_tanim->tur_adi,
                                'kisa_kod' => $x_tanim->kisa_kod,
                                'color' => $x_tanim->renk ?: '#556ee6'
                            ];
                        }
                    }
                }
                $cur = strtotime("+1 day", $cur);
            }

            $data[] = $p_info;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } elseif ($action === 'get-personel-yearly-data') {
        $personel_id = $_POST['personel_id'] ?? 0;
        $yil = $_POST['yil'] ?? date('Y');

        if (!$personel_id) {
            throw new Exception("Personel ID gerekli.");
        }

        $startDate = "$yil-01-01";
        $endDate = "$yil-12-31";

        // İzinler, Raporlar, Devamsızlıklar vb.
        $izin_stmt = $PersonelIzinleri->db->prepare("
            SELECT pi.id, pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.tur_adi, t.kisa_kod, t.renk 
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL AND pi.onay_durumu != 'Reddedildi'
            AND t.kisa_kod NOT IN ('X', 'x')
            AND (
                (pi.baslangic_tarihi BETWEEN ? AND ?) 
                OR (pi.bitis_tarihi BETWEEN ? AND ?)
                OR (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
            )
        ");
        $izin_stmt->execute([$personel_id, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        $izinler = $izin_stmt->fetchAll(PDO::FETCH_OBJ);

        $entries = [];
        foreach ($izinler as $izin) {
            $cur = strtotime($izin->baslangic_tarihi);
            $end = strtotime($izin->bitis_tarihi);
            while ($cur <= $end) {
                $date_str = date('Y-m-d', $cur);
                if ($date_str >= $startDate && $date_str <= $endDate) {
                    $entries[$date_str][] = [
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

        echo json_encode([
            'status' => 'success',
            'data' => $entries
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
        $personelStmt = $Personel->db->prepare("SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL AND (disardan_sigortali = 0 OR FIND_IN_SET('puantaj', gorunum_modulleri))");
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
        $personelStmt = $Personel->db->prepare("SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = ? AND aktif_mi = 1 AND silinme_tarihi IS NULL AND (disardan_sigortali = 0 OR FIND_IN_SET('puantaj', gorunum_modulleri))");
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

        $personeller = $Personel->db->prepare("
            SELECT p.id, p.adi_soyadi, p.tc_kimlik_no, p.isten_cikis_tarihi, p.ise_giris_tarihi
            FROM personel p
            WHERE p.firma_id = ? 
            AND p.silinme_tarihi IS NULL 
            AND (p.aktif_mi = 1 OR (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi >= ?))
            AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00' OR p.isten_cikis_tarihi >= ?)
            AND (p.disardan_sigortali = 0 OR FIND_IN_SET('puantaj', p.gorunum_modulleri))
            AND (p.ise_giris_tarihi IS NULL OR p.ise_giris_tarihi = '' OR p.ise_giris_tarihi <= ?)
            AND NOT EXISTS (
                SELECT 1 FROM personel_gorev_gecmisi pgg
                WHERE pgg.personel_id = p.id
                AND pgg.baslangic_tarihi <= ?
                AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
                AND (pgg.maas_durumu = 'Maaş Hesaplanmayan')
            )
            ORDER BY p.adi_soyadi ASC
        ");
        $personeller->execute([$firma_id, $startDate, $startDate, $endDate, $endDate, $startDate]);
        $personel_list = $personeller->fetchAll(PDO::FETCH_OBJ);

        $varsayilan_X = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'X' OR kisa_kod = 'x' OR tur_adi LIKE '%Çalışılan Gün%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $varsayilan_X->execute([$firma_id]);
        $x_tanim = $varsayilan_X->fetch(PDO::FETCH_OBJ);

        $varsayilan_HT = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'HT' OR kisa_kod = 'ht' OR tur_adi LIKE '%Hafta Tatili%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
        $varsayilan_HT->execute([$firma_id]);
        $ht_tanim = $varsayilan_HT->fetch(PDO::FETCH_OBJ);

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
                WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL AND pi.onay_durumu != 'Reddedildi'
                AND ((pi.baslangic_tarihi BETWEEN ? AND ?) OR (pi.bitis_tarihi BETWEEN ? AND ?))
            ");
            $izin_stmt->execute([$p->id, $startDate, $endDate, $startDate, $endDate]);
            $izinler = $izin_stmt->fetchAll(PDO::FETCH_OBJ);

            $dayData = [];
            $mevcut_gunler = [];
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
                            'tip_id' => $izin->izin_tipi_id,
                            'is_default' => false
                        ];
                        $mevcut_gunler[$date_str] = true;
                    }
                    $cur = strtotime("+1 day", $cur);
                }
            }

            // Boş günleri X ve HT ile doldur
            $cur = strtotime($startDate);
            $end = strtotime($endDate);
            $p_giris = $p->ise_giris_tarihi && $p->ise_giris_tarihi != '0000-00-00' ? strtotime($p->ise_giris_tarihi) : 0;
            $p_cikis = $p->isten_cikis_tarihi && $p->isten_cikis_tarihi != '0000-00-00' ? strtotime($p->isten_cikis_tarihi) : PHP_INT_MAX;

            while ($cur <= $end) {
                $date_str = date('Y-m-d', $cur);
                $d = (int)date('j', $cur);
                $isWeekend = date('w', $cur) == 0; // Sadece Pazar

                if (!isset($mevcut_gunler[$date_str]) && $cur >= $p_giris && $cur <= $p_cikis) {
                    if ($isWeekend && $ht_tanim) {
                        $dayData[$d] = [
                            'code' => $ht_tanim->kisa_kod,
                            'color' => 'F46A6A', // Default red
                            'tip_id' => $ht_tanim->id,
                            'is_default' => true
                        ];
                    } elseif (!$isWeekend && $x_tanim) {
                        $dayData[$d] = [
                            'code' => $x_tanim->kisa_kod,
                            'color' => '556EE6', // Default blue
                            'tip_id' => $x_tanim->id,
                            'is_default' => true
                        ];
                    }
                }
                $cur = strtotime("+1 day", $cur);
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
            $disabledDaysCount = 0;
            for ($d = 1; $d <= $daysCount; $d++) {
                $dateObj = new DateTime(sprintf("%s-%s-%02d", $yil, $ay, $d));
                $isDisabled = false;

                if (!empty($p->isten_cikis_tarihi) && $p->isten_cikis_tarihi != '0000-00-00') {
                    $cikisDate = new DateTime($p->isten_cikis_tarihi);
                    if ($dateObj > $cikisDate)
                        $isDisabled = true;
                }

                if (!empty($p->ise_giris_tarihi) && $p->ise_giris_tarihi != '0000-00-00') {
                    $baslamaDate = new DateTime($p->ise_giris_tarihi);
                    if ($dateObj < $baslamaDate)
                        $isDisabled = true;
                }

                if ($isDisabled)
                    $disabledDaysCount++;
            }

            $unpaidCount = 0;
            $allEntriesCount = 0;
            foreach ($dayData as $dEntry) {
                if (in_array($dEntry['tip_id'], $ucretsizIds)) {
                    $unpaidCount++;
                }
                if (empty($dEntry['is_default'])) {
                    $allEntriesCount++;
                }
            }

            $calisilmasiGerekenGun = max(0, $daysCount - $disabledDaysCount);
            $toplamCalismaGunu = max(0, $calisilmasiGerekenGun - $unpaidCount);
            $fiiliCalismaGunu = max(0, $calisilmasiGerekenGun - $allEntriesCount);

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

        // İzin Türleri Açıklamalarını Ekle
        $row += 2;
        $sheet->setCellValue('B' . $row, 'İzin Kodları Açıklamaları');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $izinTurleriQuery = $Tanimlamalar->db->prepare("SELECT kisa_kod, tur_adi, ucretli_mi FROM tanimlamalar WHERE grup = 'izin_turu' AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL ORDER BY ucretli_mi DESC, kisa_kod ASC");
        $izinTurleriQuery->execute([$firma_id]);
        $izinTurleriData = $izinTurleriQuery->fetchAll(PDO::FETCH_OBJ);

        foreach ($izinTurleriData as $it) {
            $typeStr = ($it->ucretli_mi == 1) ? "(Ücretli)" : "(Ücretsiz)";
            $sheet->setCellValue('B' . $row, $it->kisa_kod . " : " . $it->tur_adi . " " . $typeStr);
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


