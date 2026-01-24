<?php
/**
 * Personel PWA - API Endpoint
 * Tüm AJAX isteklerini yönetir
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

ini_set('error_log', dirname(dirname(__DIR__)) . '/pwa_api_error.log');
ini_set('log_errors', 1);

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Model\PersonelModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelIzinleriModel;
use App\Model\AvansModel;
use App\Helper\Security;
use App\Helper\Helper;
use App\Service\MailGonderService;
use App\Service\PushNotificationService;
use App\Model\PushSubscriptionModel;
use App\Model\BildirimModel;
use App\Model\UserModel;

// Oturum kontrolü (logout hariç)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$MailGonderService = new MailGonderService();

if ($action !== 'login' && !isset($_SESSION['personel_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu']);
    exit;
}

$personel_id = $_SESSION['personel_id'] ?? 0;

if ($personel_id > 0) {
    $PersonelModel = new PersonelModel();
    $personel = $PersonelModel->find($personel_id);
    if (!$personel) {
        session_destroy();
        echo json_encode(['success' => false, 'error' => 'Session expired', 'redirect' => 'login.php']);
        exit;
    }
}

// Response helper
function response($success, $data = null, $message = '')
{
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        case 'debugSession':
            response(true, $_SESSION);
            break;

        case 'whoami':
            $PersonelModel = new PersonelModel();
            $p = $PersonelModel->find($personel_id);
            response(true, $p);
            break;

        // ===== Dashboard =====
        case 'getDashboardData':
            $BordroModel = new BordroPersonelModel();
            $ozet = $BordroModel->getPersonelFinansalOzet($personel_id);

            $toplam_hakedis = $ozet->toplam_hakedis ?? 0;
            $alinan_odeme = $ozet->alinan_odeme ?? 0;
            $kalan_bakiye = $toplam_hakedis - $alinan_odeme;

            response(true, [
                'total_earning' => $toplam_hakedis,
                'received_payment' => $alinan_odeme,
                'remaining_balance' => $kalan_bakiye
            ]);
            break;

        // ===== Bordro İşlemleri =====
        case 'getBordroStats':
            $AvansModel = new AvansModel();
            $BordroModel = new BordroPersonelModel();

            $limit = $AvansModel->getAvansLimiti($personel_id);
            $bekleyenler = $AvansModel->getBekleyenAvanslar($personel_id);
            $ozet = $BordroModel->getPersonelFinansalOzet($personel_id);

            response(true, [
                'yearly_net' => $ozet->toplam_hakedis ?? 0,
                'advance_limit' => $limit,
                'pending_requests' => count($bekleyenler)
            ]);
            break;

        case 'getBordrolar':
            $BordroModel = new BordroPersonelModel();
            $bordrolar = $BordroModel->getPersonelBordrolari($personel_id);

            // Sadece kapatılmış dönemleri filtrele
            $bordrolar = array_filter($bordrolar, function ($item) {
                return isset($item->kapali_mi) && $item->kapali_mi == 1;
            });

            $data = array_map(function ($item) {
                // Dönem adını belirle
                $donem = $item->donem_adi ?? null;

                // Eğer donem_adi yoksa baslangic_tarihi'nden oluştur
                if (empty($donem) && !empty($item->baslangic_tarihi)) {
                    $tarih = strtotime($item->baslangic_tarihi);
                    $donem = date('Y', $tarih) . '/' . str_pad(date('m', $tarih), 2, '0', STR_PAD_LEFT);
                } else if (empty($donem)) {
                    $donem = 'Dönem ' . ($item->donem_id ?? '?');
                }

                return [
                    'id' => $item->id,
                    'donem' => $donem,
                    'odeme_tarihi' => $item->odeme_tarihi ?? '-',
                    'net_tutar' => $item->net_maas ?? 0,
                    'durum' => 'odendi'
                ];
            }, array_values($bordrolar)); // array_values to reset keys after filter

            response(true, $data);
            break;

        case 'getBordroDetay':
            $id = $_POST['id'] ?? 0;
            $BordroModel = new BordroPersonelModel();

            // Bordro ve dönem bilgisini birlikte çek
            $sql = $BordroModel->getDb()->prepare("
                SELECT bp.*, bd.kapali_mi 
                FROM bordro_personel bp
                INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
                WHERE bp.id = ? AND bp.silinme_tarihi IS NULL
            ");
            $sql->execute([$id]);
            $bordro = $sql->fetch(PDO::FETCH_OBJ);

            if ($bordro && $bordro->personel_id == $personel_id && ($bordro->kapali_mi ?? 0) == 1) {
                response(true, [
                    'id' => $bordro->id,
                    'donem' => 'Dönem ' . $bordro->donem_id,
                    'brut' => $bordro->brut_maas,
                    'sgk' => $bordro->sgk_isci,
                    'vergi' => $bordro->gelir_vergisi,
                    'net' => $bordro->net_maas
                ]);
            } else {
                response(false, null, 'Bordro bulunamadı veya henüz onaylanmadı');
            }
            break;

        // ===== Avans İşlemleri =====
        case 'getAvansTalepleri':
            $AvansModel = new AvansModel();
            $avanslar = $AvansModel->getPersonelAvanslari($personel_id);

            $data = array_map(function ($item) {
                $durum_text = 'Beklemede';
                if ($item->durum == 'onaylandi')
                    $durum_text = 'Onaylandı';
                if ($item->durum == 'reddedildi')
                    $durum_text = 'Reddedildi';

                return [
                    'id' => $item->id,
                    'tutar' => $item->tutar,
                    'tarih' => date('d.m.Y', strtotime($item->talep_tarihi)),
                    'durum' => $item->durum,
                    'durum_text' => $durum_text,
                    'aciklama' => $item->aciklama,
                    'odeme_sekli' => $item->odeme_sekli
                ];
            }, $avanslar);

            response(true, $data);
            break;

        case 'createAvansTalebi':
            $tutar = $_POST['tutar'] ?? 0;
            $odeme_sekli = $_POST['odeme_sekli'] ?? 'tek';
            $aciklama = $_POST['aciklama'] ?? '';

            $AvansModel = new AvansModel();
            $AvansModel->saveWithAttr([
                'personel_id' => $personel_id,
                'tutar' => $tutar,
                'odeme_sekli' => $odeme_sekli,
                'aciklama' => $aciklama,
                'durum' => 'beklemede',
                'talep_tarihi' => date('Y-m-d H:i:s')
            ]);
            $newId = $AvansModel->getDb()->lastInsertId();

            // Bildirim ve Mail Gönderimi
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Avans talebi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep eden: " . ($talep_eden->adi_soyadi ?? 'Bulunamadı') . "\n", FILE_APPEND);

                // 1. Uygulama İçi Bildirimler (Email bağımsız)
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari('avans');
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim gidecek kullanıcı sayısı: " . count($bildirimKullanicilari) . "\n", FILE_APPEND);

                foreach ($bildirimKullanicilari as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            'Yeni Avans Talebi',
                            ($talep_eden->adi_soyadi ?? 'Personel') . ' ' . number_format($tutar, 2, ',', '.') . ' TL avans talep etti.',
                            'index.php?p=talepler/list&tab=avans&id=' . $newId,
                            'bx-money',
                            'success'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim oluşturma hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi (Sadece email adresi olanlara)
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari('avans');
                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        $mail_content = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #5b73e8;'>Yeni Avans Talebi</h2>
                                <p><strong>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</strong> tarafından <strong>" . number_format($tutar, 2, ',', '.') . " TL</strong> tutarında avans talebi oluşturuldu.</p>
                                <p>Talebi incelemek için sisteme giriş yapabilirsiniz.</p>
                            </div>
                        ";

                        $MailGonderService->gonder(
                            [$kullanici->email_adresi],
                            'Yeni Avans Talebi - ' . ($talep_eden->adi_soyadi ?? 'Personel'),
                            $mail_content
                        );
                    } catch (Exception $e) {
                        error_log('Avans mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('Avans bildirim süreci hatası: ' . $e->getMessage());
            }

            response(true, null, 'Avans talebiniz başarıyla oluşturuldu');
            break;

        case 'updateAvansTalebi':
            $id = $_POST['id'] ?? 0;
            $tutar = $_POST['tutar'] ?? 0;
            $odeme_sekli = $_POST['odeme_sekli'] ?? 'tek';
            $aciklama = $_POST['aciklama'] ?? '';

            $AvansModel = new AvansModel();
            $avans = $AvansModel->find($id);

            if ($avans && $avans->personel_id == $personel_id && $avans->durum == 'beklemede') {
                $AvansModel->saveWithAttr([
                    'id' => $id,
                    'tutar' => $tutar,
                    'odeme_sekli' => $odeme_sekli,
                    'aciklama' => $aciklama
                ]);
                response(true, null, 'Avans talebiniz güncellendi');
            } else {
                response(false, null, 'İşlem yapılamaz');
            }
            break;

        case 'deleteAvansTalebi':
            $id = $_POST['id'] ?? 0;
            $AvansModel = new AvansModel();
            $avans = $AvansModel->find($id);

            if ($avans && $avans->personel_id == $personel_id && $avans->durum == 'beklemede') {
                $AvansModel->softDelete($id);
                response(true, null, 'Avans talebi silindi');
            } else {
                response(false, null, 'İşlem yapılamaz');
            }
            break;

        // ===== İzin İşlemleri =====
        case 'getIzinTurleri':
            $TanimlamalarModel = new App\Model\TanimlamalarModel();
            // Personelin görebileceği izin türlerini getir
            $izinTurleri = $TanimlamalarModel->getDb()->query("SELECT * FROM tanimlamalar WHERE grup = 'izin_turu' AND personel_gorebilir = 1 AND silinme_tarihi IS NULL ORDER BY tur_adi ASC")->fetchAll(PDO::FETCH_OBJ);

            $data = array_map(function ($item) {
                return [
                    'id' => $item->id, // ID'yi value olarak kullanacağız
                    'tur_adi' => $item->tur_adi,
                    'renk' => $item->renk ?? 'bg-primary/10 text-primary',
                    'ikon' => $item->ikon ?? 'event',
                    'aciklama' => $item->aciklama
                ];
            }, $izinTurleri);

            response(true, $data);
            break;

        case 'getIzinStats':
            $IzinModel = new PersonelIzinleriModel();
            file_put_contents(dirname(dirname(__DIR__)) . '/debug_pwa.log', "getIzinStats for personel_id: " . $personel_id . "\n", FILE_APPEND);
            $entitlement = $IzinModel->calculateLeaveEntitlement($personel_id);
            file_put_contents(dirname(dirname(__DIR__)) . '/debug_pwa.log', "Entitlement: " . json_encode($entitlement) . "\n", FILE_APPEND);
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            $bekleyen = 0;
            foreach ($izinler as $izin) {
                $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');
                if ($durum == 'beklemede') {
                    $bekleyen++;
                }
            }

            response(true, [
                'kalan_izin' => $entitlement['kalan_izin'],
                'toplam_hakedis' => $entitlement['toplam_hakedis'],
                'kullanilan_izin' => $entitlement['kullanilan_izin'],
                'detay' => $entitlement['detay'],
                'hastalik_izni' => 0,
                'bekleyen' => $bekleyen
            ]);
            break;

        case 'getIzinler':
            $IzinModel = new PersonelIzinleriModel();
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            $data = array_map(function ($item) {
                // Onay durumu kontrolü
                $main_durum = mb_strtolower($item->onay_durumu ?? '', 'UTF-8');
                $cancel_target = mb_strtolower('İptal Edildi', 'UTF-8');

                if ($main_durum === 'iptal edildi' || $main_durum === $cancel_target) {
                    $durum_raw = 'İptal Edildi';
                } else {
                    $durum_raw = $item->onay_durumu_text ?? $item->onay_durumu ?? 'beklemede';
                }

                $durum = mb_strtolower($durum_raw, 'UTF-8');
                $durum_text = mb_convert_case($durum, MB_CASE_TITLE, "UTF-8");

                if ($durum == 'beklemede')
                    $durum_text = 'Beklemede';
                if ($durum == 'onaylandi' || $durum == 'onaylandı') {
                    $durum = 'onaylandi'; // Normalize status for frontend
                    $durum_text = 'Onaylandı';
                }
                if ($durum == 'reddedildi')
                    $durum_text = 'Reddedildi';
                if ($durum == 'iptal edildi' || $durum == $cancel_target) {
                    $durum = 'iptal_edildi'; // Normalize status
                    $durum_text = 'İptal Edildi';
                }

                $izin_tipi_text = $item->izin_tipi_adi ?? 'İzin Türü Belirtilmemiş';
                $renk = 'bg-primary/10 text-primary';
                $ikon = 'event';

                // Red nedenini bul
                $red_nedeni = null;
                if ($durum == 'reddedildi' && !empty($item->onaylar)) {
                    $son_onay = end($item->onaylar);
                    if ($son_onay && $son_onay->durum == 'Reddedildi') {
                        $red_nedeni = $son_onay->aciklama;
                    }
                }

                return [
                    'id' => $item->id,
                    'izin_tipi' => $item->izin_tipi_id,
                    'izin_tipi_text' => $izin_tipi_text,
                    'baslangic' => date('d.m.Y', strtotime($item->baslangic_tarihi)),
                    'bitis' => date('d.m.Y', strtotime($item->bitis_tarihi)),
                    'toplam_gun' => $item->toplam_gun,
                    'talep_tarihi' => date('d.m.Y', strtotime($item->olusturma_tarihi)),
                    'durum' => $durum,
                    'durum_text' => $durum_text,
                    'aciklama' => $item->aciklama,
                    'red_nedeni' => $red_nedeni,
                    'renk' => $renk,
                    'ikon' => $ikon
                ];
            }, $izinler);

            response(true, $data);
            break;

        case 'getIzinlerByYear':
            $IzinModel = new PersonelIzinleriModel();
            $PersonelModel = new PersonelModel();

            // Get personnel info to calculate year ranges
            $personel = $PersonelModel->find($personel_id);
            if (!$personel || empty($personel->ise_giris_tarihi)) {
                response(true, []);
                break;
            }

            // Get approved leave records
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            // Debug log
            $logFile = dirname(dirname(__DIR__)) . '/debug_izin_year.log';
            file_put_contents($logFile, "Total izinler: " . count($izinler) . "\n", FILE_APPEND);

            // Filter only approved and leaves that affect annual leave balance
            $approvedIzinler = array_filter($izinler, function ($izin) use ($logFile) {
                $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');
                $affectsBalance = isset($izin->yillik_izne_etki) && ($izin->yillik_izne_etki == 'Dus' || $izin->yillik_izne_etki == 1);

                // Only include annual leave types (Yıllık İzin) - exclude other types like maternity/paternity leave
                $isAnnualLeave = stripos($izin->izin_tipi_adi ?? '', 'Yıllık') !== false ||
                    stripos($izin->izin_tipi_adi ?? '', 'Yillik') !== false;

                file_put_contents($logFile, "Izin ID: {$izin->id}, Tip: {$izin->izin_tipi_adi}, Durum: {$durum}, yillik_izne_etki: " . ($izin->yillik_izne_etki ?? 'NULL') . ", isAnnualLeave: " . ($isAnnualLeave ? 'YES' : 'NO') . "\n", FILE_APPEND);

                return ($durum == 'onaylandi' || $durum == 'onaylandı' || $durum == 'kabuledildi') && $affectsBalance && $isAnnualLeave;
            });

            file_put_contents($logFile, "Approved izinler: " . count($approvedIzinler) . "\n", FILE_APPEND);

            // Group by work anniversary year
            $giris = new DateTime($personel->ise_giris_tarihi);
            $bugun = new DateTime();
            $yearGroups = [];

            foreach ($approvedIzinler as $izin) {
                if (empty($izin->baslangic_tarihi))
                    continue;

                $izinDate = new DateTime($izin->baslangic_tarihi);

                // Calculate which work year this leave belongs to
                $yilFarki = $giris->diff($izinDate)->y;
                $workYear = $yilFarki + 1; // Year 1-based

                if ($workYear < 1)
                    continue; // Skip leaves before work start

                if (!isset($yearGroups[$workYear])) {
                    $yearGroups[$workYear] = [];
                }

                $yearGroups[$workYear][] = [
                    'id' => $izin->id,
                    'izin_tipi_adi' => $izin->izin_tipi_adi ?? 'Yıllık İzin',
                    'baslangic' => date('d.m.Y', strtotime($izin->baslangic_tarihi)),
                    'bitis' => date('d.m.Y', strtotime($izin->bitis_tarihi)),
                    'toplam_gun' => $izin->toplam_gun
                ];
            }

            file_put_contents($logFile, "Year groups: " . json_encode($yearGroups) . "\n", FILE_APPEND);

            // Also add all approved leaves under 'all' key for frontend to use
            $allLeaves = [];
            foreach ($approvedIzinler as $izin) {
                if (!empty($izin->baslangic_tarihi)) {
                    $allLeaves[] = [
                        'id' => $izin->id,
                        'izin_tipi_adi' => $izin->izin_tipi_adi ?? 'Yıllık İzin',
                        'baslangic' => date('d.m.Y', strtotime($izin->baslangic_tarihi)),
                        'bitis' => date('d.m.Y', strtotime($izin->bitis_tarihi)),
                        'toplam_gun' => $izin->toplam_gun
                    ];
                }
            }
            $yearGroups['all'] = $allLeaves;

            response(true, $yearGroups);
            break;

        case 'createIzinTalebi':
            $izin_tipi = $_POST['izin_tipi'] ?? '';
            $baslangic = $_POST['baslangic_tarihi'] ?? '';
            $bitis = $_POST['bitis_tarihi'] ?? '';

            // Güvenlik: Açıklama alanını temizle (XSS/Script koruması)
            $aciklama = $_POST['aciklama'] ?? '';
            $aciklama = strip_tags($aciklama);
            $aciklama = htmlspecialchars($aciklama, ENT_QUOTES, 'UTF-8');

            if (empty($izin_tipi)) {
                response(false, null, 'Lütfen izin türünü seçiniz.');
            }

            if (empty($baslangic) || empty($bitis)) {
                response(false, null, 'Lütfen tarihleri seçiniz.');
            }

            $IzinModel = new PersonelIzinleriModel();

            // Çakışma Kontrolü
            // Seçilen tarih aralığında (başlangıç ve bitiş dahil) başka bir izin var mı?
            // Durumu 'beklemede', 'onaylandi' veya 'onaylandı' olanları kontrol et.
            $sql = "SELECT COUNT(*) as sayi FROM personel_izinleri 
                    WHERE personel_id = :personel_id 
                    AND silinme_tarihi IS NULL 
                    AND (onay_durumu = 'beklemede' OR onay_durumu = 'onaylandi' OR onay_durumu = 'onaylandı')
                    AND (
                        (baslangic_tarihi <= :bitis AND bitis_tarihi >= :baslangic)
                    )";

            $stmt = $IzinModel->getDb()->prepare($sql);
            $stmt->execute([
                ':personel_id' => $personel_id,
                ':baslangic' => $baslangic,
                ':bitis' => $bitis
            ]);
            $cakisma = $stmt->fetch(PDO::FETCH_OBJ);

            if ($cakisma && $cakisma->sayi > 0) {
                response(false, null, 'Seçilen tarih aralığında bekleyen veya onaylanmış başka bir izin talebiniz bulunmaktadır. Lütfen tarihleri kontrol ediniz.');
            }

            // Gün sayısını hesapla
            $diff = strtotime($bitis) - strtotime($baslangic);
            $toplam_gun = round($diff / (60 * 60 * 24)) + 1;

            // $IzinModel zaten yukarıda tanımlandı

            $IzinModel->saveWithAttr([
                'personel_id' => $personel_id,
                'izin_tipi_id' => $izin_tipi,
                'baslangic_tarihi' => $baslangic,
                'bitis_tarihi' => $bitis,
                'toplam_gun' => $toplam_gun,
                'aciklama' => $aciklama,
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);
            $newId = $IzinModel->getDb()->lastInsertId();

            // İzin onayı yapacak personeli getir ve mail gönder
            // İzin onayı yapacak personeli getir ve bildirim/mail gönder
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin talebi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);

                // İzin türü adını belirle
                $izin_tipi_text = $izin_tipi;
                $izin_tipleri = [
                    'yillik' => 'Yıllık İzin',
                    'mazeret' => 'Mazeret İzni',
                    'hastalik' => 'Hastalık İzni',
                    'dogum' => 'Doğum / Babalık İzni',
                    'ucretsiz' => 'Ücretsiz İzin'
                ];

                if (isset($izin_tipleri[$izin_tipi])) {
                    $izin_tipi_text = $izin_tipleri[$izin_tipi];
                } elseif (is_numeric($izin_tipi)) {
                    $TanimlamalarModel = new App\Model\TanimlamalarModel();
                    $tur = $TanimlamalarModel->find($izin_tipi);
                    if ($tur) {
                        $izin_tipi_text = $tur->tur_adi;
                    }
                }

                // 1. Uygulama İçi Bildirimler
                // Hem onaylayacak kişiye hem de bildirimleri açık olan yöneticilere gönder
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari('izin');
                $izin_onayi_yapacak_personel = $UserModel->getIzinOnayPersonel();

                $recipients = [];
                if ($izin_onayi_yapacak_personel) {
                    $recipients[$izin_onayi_yapacak_personel->id] = $izin_onayi_yapacak_personel;
                }
                foreach ($bildirimKullanicilari as $k) {
                    $recipients[$k->id] = $k;
                }

                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim gidecek kullanıcı sayısı: " . count($recipients) . "\n", FILE_APPEND);
                foreach ($recipients as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            'Yeni İzin Talebi',
                            ($talep_eden->adi_soyadi ?? 'Personel') . ' ' . $izin_tipi_text . ' talep etti.',
                            'index.php?p=talepler/list&tab=izin&id=' . $newId,
                            'calendar',
                            'warning'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin bildirimi oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin bildirim hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi (Sadece email adresi olanlara)
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari('izin');
                if ($izin_onayi_yapacak_personel && !empty($izin_onayi_yapacak_personel->email_adresi)) {
                    // Onaylayacak kişiyi de ekle (eğer listede yoksa)
                    $found = false;
                    foreach ($mailKullanicilari as $mk) {
                        if ($mk->id == $izin_onayi_yapacak_personel->id) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found)
                        $mailKullanicilari[] = $izin_onayi_yapacak_personel;
                }

                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        $mail_template_path = dirname(__DIR__) . '/mail-template/izin_onay.php';
                        if (file_exists($mail_template_path)) {
                            $mail_content = file_get_contents($mail_template_path);
                            $replacements = [
                                '{{ONAYLAYAN_AD_SOYAD}}' => $kullanici->adi_soyadi ?? 'Yetkili',
                                '{{TALEP_EDEN_AD_SOYAD}}' => $talep_eden->adi_soyadi ?? 'Personel',
                                '{{IZIN_TURU}}' => $izin_tipi_text,
                                '{{BASLANGIC_TARIHI}}' => date('d.m.Y', strtotime($baslangic)),
                                '{{BITIS_TARIHI}}' => date('d.m.Y', strtotime($bitis)),
                                '{{ACIKLAMA}}' => !empty($aciklama) ? nl2br(htmlspecialchars($aciklama)) : 'Açıklama belirtilmemiş',
                                '{{ONAY_LINKI}}' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/views/personel/',
                                '{{YIL}}' => date('Y')
                            ];
                            $mail_content = str_replace(array_keys($replacements), array_values($replacements), $mail_content);
                            $MailGonderService->gonder(
                                [$kullanici->email_adresi],
                                'Yeni İzin Talebi - ' . ($talep_eden->adi_soyadi ?? 'Personel'),
                                $mail_content
                            );
                        }
                    } catch (Exception $e) {
                        error_log('İzin talebi mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('İzin bildirim süreci hatası: ' . $e->getMessage());
            }

            response(true, null, 'İzin talebiniz başarıyla oluşturuldu');

            break;

        case 'cancelIzinTalebi':
            $id = $_POST['id'] ?? 0;
            $IzinModel = new PersonelIzinleriModel();
            $izin = $IzinModel->find($id);

            // Onay durumu kontrolünü esnet (büyük/küçük harf duyarsız)
            $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');

            if ($izin && $izin->personel_id == $personel_id && $durum == 'beklemede') {
                $IzinModel->softDelete($id);
                /**onay_durumunu iptal edildi yap */
                $IzinModel->saveWithAttr(
                    [
                        'id' => $id,
                        'onay_durumu' => 'iptal edildi'
                    ]
                );
                response(true, null, 'İzin talebi iptal edildi');
            } else {
                // Debug için detaylı hata mesajı (geliştirme aşamasında)
                // $msg = "İzin: " . ($izin ? 'Var' : 'Yok') . ", PID: " . ($izin->personel_id ?? 'Yok') . " vs $personel_id, Durum: " . ($izin->onay_durumu ?? 'Yok');
                response(false, null, 'İşlem yapılamaz. İzin durumu uygun değil veya yetkiniz yok.');
            }
            break;

        // ===== Talep İşlemleri =====
        case 'getTalepStats':
            $TalepModel = new App\Model\TalepModel();
            $stats = $TalepModel->getStats($personel_id);
            response(true, $stats);
            break;

        case 'getTalepler':
            $TalepModel = new App\Model\TalepModel();
            $talepler = $TalepModel->getPersonelTalepleri($personel_id);

            // Format data for frontend
            $data = array_map(function ($item) {
                $kategori_map = [
                    'ariza' => 'Arıza',
                    'oneri' => 'Öneri',
                    'sikayet' => 'Şikayet',
                    'istek' => 'İstek',
                    'diger' => 'Diğer'
                ];

                $durum_map = [
                    'beklemede' => 'Beklemede',
                    'devam' => 'İnceleniyor',
                    'cozuldu' => 'Çözüldü'
                ];

                $oncelik_map = [
                    'dusuk' => 'Düşük',
                    'orta' => 'Orta',
                    'yuksek' => 'Yüksek'
                ];

                return [
                    'id' => $item->id,
                    'ref_no' => $item->ref_no,
                    'baslik' => $item->baslik ?: $kategori_map[$item->kategori] ?? 'Talep',
                    'kategori' => $item->kategori,
                    'kategori_text' => $kategori_map[$item->kategori] ?? $item->kategori,
                    'konum' => $item->konum,
                    'oncelik' => $item->oncelik,
                    'oncelik_text' => $oncelik_map[$item->oncelik] ?? $item->oncelik,
                    'durum' => $item->durum,
                    'durum_text' => $durum_map[$item->durum] ?? $item->durum,
                    'tarih' => date('d.m.Y H:i', strtotime($item->olusturma_tarihi)),
                    'aciklama' => $item->aciklama,
                    'foto' => $item->foto,
                    'cozum_tarihi' => $item->cozum_tarihi ? date('d.m.Y H:i', strtotime($item->cozum_tarihi)) : null
                ];
            }, $talepler);

            response(true, $data);
            break;

        case 'createTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();

            $konum = $_POST['konum'] ?? '';
            $kategori = $_POST['kategori'] ?? '';
            $oncelik = $_POST['oncelik'] ?? 'orta';
            $aciklama = $_POST['aciklama'] ?? '';
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;

            if (empty($konum) || empty($kategori) || empty($aciklama)) {
                throw new Exception('Lütfen tüm zorunlu alanları doldurun.');
            }

            // Handle file upload
            $foto_path = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                $upload_dir = '../../uploads/talepler/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($file_ext, $allowed)) {
                    $new_name = uniqid('tlp_') . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_name)) {
                        $foto_path = 'uploads/talepler/' . $new_name;
                    }
                }
            }

            // Generate Ref No
            $ref_no = $TalepModel->generateRefNo();

            // Map category to title if needed
            $kategori_titles = [
                'ariza' => 'Arıza Bildirimi',
                'oneri' => 'Öneri',
                'sikayet' => 'Şikayet',
                'istek' => 'İstek',
                'diger' => 'Diğer Talep'
            ];
            $baslik = $kategori_titles[$kategori] ?? 'Yeni Talep';

            $TalepModel->saveWithAttr([
                'personel_id' => $personel_id,
                'ref_no' => $ref_no,
                'konum' => $konum,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'kategori' => $kategori,
                'oncelik' => $oncelik,
                'baslik' => $baslik,
                'aciklama' => $aciklama,
                'foto' => $foto_path,
                'durum' => 'beklemede',
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);

            $newId = (int) $TalepModel->getDb()->lastInsertId();

            // Bildirim ve Mail Gönderimi
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);

                // Kategori bazında bildirim türünü belirle
                $bildirim_turu = ($kategori == 'ariza') ? 'ariza' : 'genel';

                // 1. Uygulama İçi Bildirimler
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari($bildirim_turu);
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi gidecek kullanıcı sayısı: " . count($bildirimKullanicilari) . "\n", FILE_APPEND);

                foreach ($bildirimKullanicilari as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            "Yeni {$baslik}",
                            ($talep_eden->adi_soyadi ?? 'Personel') . " yeni bir talep oluşturdu: {$baslik}",
                            'index.php?p=talepler/list&tab=talep&id=' . $newId,
                            'message-square',
                            'info'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirim hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari($bildirim_turu);
                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        // Öncelik renk haritası
                        $oncelik_renk = [
                            'dusuk' => '#28a745',
                            'orta' => '#ffc107',
                            'yuksek' => '#dc3545'
                        ];

                        $oncelik_text = [
                            'dusuk' => 'Düşük',
                            'orta' => 'Orta',
                            'yuksek' => 'Yüksek'
                        ];

                        $mail_content = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #5b73e8;'>Yeni {$baslik}</h2>
                                <p>Sayın <strong>{$kullanici->adi_soyadi}</strong>,</p>
                                <p><strong>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</strong> tarafından yeni bir talep bildirimi oluşturuldu.</p>
                                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <h3 style='margin-top: 0;'>Talep Detayları</h3>
                                    <table style='width: 100%;'>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Referans No:</strong></td>
                                            <td style='padding: 5px;'><span style='background-color: #e3f2fd; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>{$ref_no}</span></td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Personel:</strong></td>
                                            <td style='padding: 5px;'>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Kategori:</strong></td>
                                            <td style='padding: 5px;'>{$baslik}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Konum:</strong></td>
                                            <td style='padding: 5px;'>{$konum}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Öncelik:</strong></td>
                                            <td style='padding: 5px;'>
                                                <span style='background-color: {$oncelik_renk[$oncelik]}; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>
                                                    {$oncelik_text[$oncelik]}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Açıklama:</strong></td>
                                            <td style='padding: 5px;'>" . nl2br(htmlspecialchars($aciklama)) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Talep Tarihi:</strong></td>
                                            <td style='padding: 5px;'>" . date('d.m.Y H:i') . "</td>
                                        </tr>
                                    </table>
                                </div>
                                <p>Talebi incelemek için sisteme giriş yapabilirsiniz.</p>
                                <hr style='margin: 20px 0;'>
                                <p style='font-size: 12px; color: #666;'>Bu bir otomatik bildirimdir. Lütfen yanıtlamayınız.</p>
                            </div>
                        ";

                        $MailGonderService->gonder(
                            [$kullanici->email_adresi],
                            "Yeni {$baslik} - Ref: {$ref_no}",
                            $mail_content
                        );
                    } catch (Exception $e) {
                        error_log('Talep bildirimi mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('Talep bildirim süreci hatası: ' . $e->getMessage());
            }

            response(true, ['id' => $newId, 'ref_no' => $ref_no], 'Talebiniz başarıyla oluşturuldu. Referans No: ' . $ref_no);
            break;

        case 'updateTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();

            $id = (int) ($_POST['id'] ?? 0);
            $konum = $_POST['konum'] ?? '';
            $kategori = $_POST['kategori'] ?? '';
            $oncelik = $_POST['oncelik'] ?? 'orta';
            $aciklama = $_POST['aciklama'] ?? '';
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;

            if ($id <= 0) {
                throw new Exception('Geçersiz talep.');
            }

            if (empty($konum) || empty($kategori) || empty($aciklama)) {
                throw new Exception('Lütfen tüm zorunlu alanları doldurun.');
            }

            $stmt = $TalepModel->getDb()->prepare("SELECT id, ref_no, foto, durum FROM personel_talepleri WHERE id = ? AND personel_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id, $personel_id]);
            $existing = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$existing) {
                response(false, null, 'Talep bulunamadı.');
            }

            if ($existing->durum !== 'beklemede') {
                response(false, null, 'Sadece beklemede olan talepler güncellenebilir.');
            }

            $foto_path = $existing->foto;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                $upload_dir = '../../uploads/talepler/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($file_ext, $allowed)) {
                    $new_name = uniqid('tlp_') . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_name)) {
                        $foto_path = 'uploads/talepler/' . $new_name;
                    }
                }
            }

            $kategori_titles = [
                'ariza' => 'Arıza Bildirimi',
                'oneri' => 'Öneri',
                'sikayet' => 'Şikayet',
                'istek' => 'İstek',
                'diger' => 'Diğer Talep'
            ];
            $baslik = $kategori_titles[$kategori] ?? 'Yeni Talep';

            $update = $TalepModel->getDb()->prepare("
                UPDATE personel_talepleri 
                SET konum = ?, latitude = ?, longitude = ?, kategori = ?, oncelik = ?, baslik = ?, aciklama = ?, foto = ?
                WHERE id = ? AND personel_id = ? AND durum = 'beklemede' AND deleted_at IS NULL
            ");
            $update->execute([$konum, $latitude, $longitude, $kategori, $oncelik, $baslik, $aciklama, $foto_path, $id, $personel_id]);

            response(true, ['id' => $id, 'ref_no' => $existing->ref_no], 'Talebiniz güncellendi. Referans No: ' . $existing->ref_no);
            break;

        case 'deleteTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Geçersiz talep.');
            }

            $delete = $TalepModel->getDb()->prepare("
                UPDATE personel_talepleri 
                SET deleted_at = NOW()
                WHERE id = ? AND personel_id = ? AND durum = 'beklemede' AND deleted_at IS NULL
            ");
            $delete->execute([$id, $personel_id]);

            if ($delete->rowCount() === 0) {
                response(false, null, 'Talep silinemedi (sadece beklemede olan talepler silinebilir).');
            }

            response(true, ['id' => $id], 'Talep silindi.');
            break;

        // ===== Profil İşlemleri =====
        case 'changePassword':
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';

            // Validasyon
            if (empty($current) || empty($new)) {
                response(false, null, 'Lütfen tüm alanları doldurun.');
            }

            if (strlen($new) < 6) {
                response(false, null, 'Yeni şifre en az 6 karakter olmalıdır.');
            }

            // Personeli bul
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();
            $stmt = $db->prepare("SELECT id, sifre FROM personel WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $personel_id]);
            $personel = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$personel) {
                response(false, null, 'Personel bulunamadı.');
            }

            // Mevcut şifre kontrolü
            if (empty($personel->sifre)) {
                response(false, null, 'Hesabınızda henüz şifre belirlenmemiş.');
            }

            if (!password_verify($current, $personel->sifre)) {
                response(false, null, 'Mevcut şifreniz hatalı.');
            }

            // Yeni şifreyi hash'le ve kaydet
            $newHashedPassword = password_hash($new, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE personel SET sifre = :sifre WHERE id = :id");
            $result = $updateStmt->execute([
                'sifre' => $newHashedPassword,
                'id' => $personel_id
            ]);

            if ($result) {
                response(true, null, 'Şifreniz başarıyla değiştirildi.');
            } else {
                response(false, null, 'Şifre değiştirilirken bir hata oluştu.');
            }
            break;

        case 'updateProfileImage':
            if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
                response(false, null, 'Lütfen bir resim seçiniz.');
            }

            $file = $_FILES['image'];
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                response(false, null, 'Sadece JPG, PNG ve WEBP formatları desteklenir.');
            }

            // Dosya boyutu kontrolü (örn: 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                response(false, null, 'Dosya boyutu 5MB\'dan büyük olamaz.');
            }

            // Hedef klasörler
            $mainAppDir = dirname(dirname(__DIR__));
            $pwaDir = __DIR__;

            $uploadDirMain = $mainAppDir . '/assets/images/users/';
            $uploadDirPwa = $pwaDir . '/assets/images/users/';

            if (!file_exists($uploadDirMain)) {
                mkdir($uploadDirMain, 0777, true);
            }
            if (!file_exists($uploadDirPwa)) {
                mkdir($uploadDirPwa, 0777, true);
            }

            $fileName = uniqid('personel_') . '.' . $ext;
            $targetPathMain = $uploadDirMain . $fileName;
            $targetPathPwa = $uploadDirPwa . $fileName;

            // DB'ye kaydedilecek yol
            $dbPath = 'assets/images/users/' . $fileName;

            // Debug Log
            $logFile = $mainAppDir . '/debug_image_upload.txt';
            $logContent = date('Y-m-d H:i:s') . " - Uploading for ID: $personel_id\n";
            $logContent .= "Target Main: $targetPathMain\n";
            $logContent .= "Target PWA: $targetPathPwa\n";
            $logContent .= "DB Path: $dbPath\n";

            if (move_uploaded_file($file['tmp_name'], $targetPathMain)) {
                // PWA dizinine kopyala
                if (!copy($targetPathMain, $targetPathPwa)) {
                    $logContent .= "ERROR: Failed to copy to PWA directory: $targetPathPwa\n";
                } else {
                    $logContent .= "SUCCESS: Copied to PWA directory.\n";
                }

                $PersonelModel = new PersonelModel();
                $db = $PersonelModel->getDb();

                // Eski resmi silmek istersek burada yapabiliriz ama şimdilik kalsın

                $stmt = $db->prepare("UPDATE personel SET resim_yolu = ? WHERE id = ?");
                $result = $stmt->execute([$dbPath, $personel_id]);
                $rowCount = $stmt->rowCount();

                $logContent .= "Update Result: " . ($result ? 'True' : 'False') . "\n";
                $logContent .= "Row Count: $rowCount\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);

                if ($result) {
                    response(true, ['image_url' => $dbPath], 'Profil resmi güncellendi.');
                } else {
                    @unlink($targetPathMain); // DB güncellenemezse dosyaları sil
                    @unlink($targetPathPwa);
                    response(false, null, 'Veritabanı güncellenemedi.');
                }
            } else {
                $logContent .= "Move Uploaded File Failed\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);
                response(false, null, 'Dosya yüklenirken bir hata oluştu.');
            }
            break;

        // ===== Push Notification =====
        case 'get-vapid-key':
            $configPath = dirname(__DIR__) . '/../App/Config/vapid.php';
            $publicKey = '';

            if (file_exists($configPath)) {
                $config = require $configPath;
                $publicKey = $config['publicKey'];
            } else {
                $publicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
            }

            response(true, ['publicKey' => $publicKey]);
            break;

        case 'save-subscription':
            $subscription = json_decode($_POST['subscription'] ?? '{}', true);

            if (empty($subscription) || empty($subscription['endpoint'])) {
                response(false, null, 'Geçersiz abonelik verisi');
            }

            $PushSubscriptionModel = new PushSubscriptionModel();
            $result = $PushSubscriptionModel->saveSubscription(
                $personel_id,
                $subscription['endpoint'],
                $subscription['keys']['p256dh'] ?? null,
                $subscription['keys']['auth'] ?? null
            );

            if ($result) {
                response(true, null, 'Bildirim aboneliği başarıyla kaydedildi');
            } else {
                response(false, null, 'Abonelik kaydedilirken bir hata oluştu');
            }
            break;

        case 'check-subscription-status':
            $PushSubscriptionModel = new PushSubscriptionModel();
            $db = $PushSubscriptionModel->getDb();

            $stmt = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE personel_id = ?");
            $stmt->execute([$personel_id]);
            $count = (int) $stmt->fetchColumn();

            response(true, ['subscribed' => $count > 0, 'count' => $count]);
            break;

        case 'remove-subscription':
            $PushSubscriptionModel = new PushSubscriptionModel();
            $db = $PushSubscriptionModel->getDb();

            // Tüm abonelikleri sil
            $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE personel_id = ?");
            $result = $stmt->execute([$personel_id]);

            if ($result) {
                response(true, null, 'Bildirim aboneliği kaldırıldı');
            } else {
                response(false, null, 'Abonelik kaldırılırken bir hata oluştu');
            }
            break;

        case 'getMyNotifications':
            // Personele gönderilen push bildirimlerini getir
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';

            $db = $PersonelModel->getDb();

            // personel_bildirim_durumu tablosu yoksa oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS personel_bildirim_durumu (
                id INT AUTO_INCREMENT PRIMARY KEY,
                personel_id INT NOT NULL,
                mesaj_log_id INT NOT NULL,
                okundu TINYINT(1) DEFAULT 0,
                okunma_tarihi DATETIME NULL,
                silindi TINYINT(1) DEFAULT 0,
                silme_tarihi DATETIME NULL,
                UNIQUE KEY unique_personel_mesaj (personel_id, mesaj_log_id)
            )");

            // mesaj_log tablosundan push bildirimlerini çek
            // recipients alanında personel adı veya "Tüm Aboneler" ibaresi geçenleri al
            // silindi olarak işaretlenmemiş olanları getir
            $sql = "SELECT m.*, 
                    COALESCE(pbd.okundu, 0) as okundu,
                    pbd.okunma_tarihi
                    FROM mesaj_log m
                    LEFT JOIN personel_bildirim_durumu pbd ON m.id = pbd.mesaj_log_id AND pbd.personel_id = :personel_id
                    WHERE m.type = 'push' 
                    AND (
                        m.recipients LIKE :personel_adi 
                        OR m.recipients LIKE '%Tüm Aboneler%'
                        OR m.recipients LIKE '%Test Kullanıcısı%'
                    )
                    AND (pbd.silindi IS NULL OR pbd.silindi = 0)
                    ORDER BY m.created_at DESC 
                    LIMIT 50";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':personel_id' => $personel_id,
                ':personel_adi' => '%' . $personelAdi . '%'
            ]);
            $notifications = $stmt->fetchAll(PDO::FETCH_OBJ);

            $data = array_map(function ($item) {
                // Zaman farkını hesapla
                $created = new DateTime($item->created_at);
                $now = new DateTime();
                $diff = $now->diff($created);

                if ($diff->days == 0) {
                    if ($diff->h == 0) {
                        if ($diff->i == 0) {
                            $timeAgo = 'Az önce';
                        } else {
                            $timeAgo = $diff->i . ' dk önce';
                        }
                    } else {
                        $timeAgo = $diff->h . ' saat önce';
                    }
                } elseif ($diff->days == 1) {
                    $timeAgo = 'Dün';
                } elseif ($diff->days < 7) {
                    $timeAgo = $diff->days . ' gün önce';
                } else {
                    $timeAgo = date('d M', strtotime($item->created_at));
                }

                return [
                    'id' => $item->id,
                    'title' => $item->subject,
                    'body' => $item->message,
                    'time_ago' => $timeAgo,
                    'created_at' => $item->created_at,
                    'status' => $item->status,
                    'okundu' => (bool) $item->okundu
                ];
            }, $notifications);

            response(true, $data);
            break;

        case 'markNotificationRead':
            $mesaj_log_id = $_POST['notification_id'] ?? null;

            if (!$mesaj_log_id) {
                response(false, null, 'Bildirim ID gerekli');
            }

            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();

            // Upsert - varsa güncelle, yoksa ekle
            $sql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, okundu, okunma_tarihi)
                    VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE okundu = 1, okunma_tarihi = NOW()";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':personel_id' => $personel_id,
                ':mesaj_log_id' => $mesaj_log_id
            ]);

            if ($result) {
                response(true, null, 'Bildirim okundu olarak işaretlendi');
            } else {
                response(false, null, 'İşlem başarısız');
            }
            break;

        case 'markAllNotificationsRead':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';
            $db = $PersonelModel->getDb();

            // Personele ait tüm bildirimleri bul
            $sql = "SELECT id FROM mesaj_log 
                    WHERE type = 'push' 
                    AND (
                        recipients LIKE :personel_adi 
                        OR recipients LIKE '%Tüm Aboneler%'
                        OR recipients LIKE '%Test Kullanıcısı%'
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([':personel_adi' => '%' . $personelAdi . '%']);
            $notifications = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Her birini okundu olarak işaretle
            $insertSql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, okundu, okunma_tarihi)
                          VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                          ON DUPLICATE KEY UPDATE okundu = 1, okunma_tarihi = NOW()";

            $insertStmt = $db->prepare($insertSql);

            foreach ($notifications as $notifId) {
                $insertStmt->execute([
                    ':personel_id' => $personel_id,
                    ':mesaj_log_id' => $notifId
                ]);
            }

            response(true, null, 'Tüm bildirimler okundu olarak işaretlendi');
            break;

        case 'deleteNotification':
            $mesaj_log_id = $_POST['notification_id'] ?? null;

            if (!$mesaj_log_id) {
                response(false, null, 'Bildirim ID gerekli');
            }

            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();

            // Upsert - varsa güncelle, yoksa ekle (silindi olarak işaretle)
            $sql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, silindi, silme_tarihi)
                    VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE silindi = 1, silme_tarihi = NOW()";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':personel_id' => $personel_id,
                ':mesaj_log_id' => $mesaj_log_id
            ]);

            if ($result) {
                response(true, null, 'Bildirim silindi');
            } else {
                response(false, null, 'İşlem başarısız');
            }
            break;

        case 'deleteAllNotifications':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';
            $db = $PersonelModel->getDb();

            // Personele ait tüm bildirimleri bul
            $sql = "SELECT id FROM mesaj_log 
                    WHERE type = 'push' 
                    AND (
                        recipients LIKE :personel_adi 
                        OR recipients LIKE '%Tüm Aboneler%'
                        OR recipients LIKE '%Test Kullanıcısı%'
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([':personel_adi' => '%' . $personelAdi . '%']);
            $notifications = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Her birini silindi olarak işaretle
            $insertSql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, silindi, silme_tarihi)
                          VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                          ON DUPLICATE KEY UPDATE silindi = 1, silme_tarihi = NOW()";

            $insertStmt = $db->prepare($insertSql);

            foreach ($notifications as $notifId) {
                $insertStmt->execute([
                    ':personel_id' => $personel_id,
                    ':mesaj_log_id' => $notifId
                ]);
            }

            response(true, null, 'Tüm bildirimler silindi');
            break;


        // ===== Puantaj / İş Takip =====
        case 'getPuantajData':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);

            if (!$personelData || empty($personelData->ekip_no)) {
                response(true, [
                    'items' => [],
                    'stats' => ['toplam' => 0, 'sonuclanan' => 0, 'acik' => 0]
                ]);
            }

            $ekipKodu = $personelData->ekip_no;
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $workType = $_POST['work_type'] ?? '';

            // Query ile veri çek
            $sql = "SELECT * FROM yapilan_isler WHERE ekip_kodu = ?";
            $params = [$ekipKodu];

            if (!empty($startDate)) {
                $sql .= " AND tarih >= ?";
                $params[] = $startDate;
            }

            if (!empty($endDate)) {
                $sql .= " AND tarih <= ?";
                $params[] = $endDate;
            }

            if (!empty($workType)) {
                $sql .= " AND is_emri_tipi = ?";
                $params[] = $workType;
            }

            $sql .= " ORDER BY tarih DESC LIMIT 100";

            $stmt = $PersonelModel->getDb()->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_OBJ);

            // İstatistikler
            $totalSonuclanan = 0;
            $totalAcik = 0;

            foreach ($items as $item) {
                $totalSonuclanan += (int) ($item->sonuclanmis ?? 0);
                $totalAcik += (int) ($item->acik_olanlar ?? 0);
            }

            response(true, [
                'items' => $items,
                'stats' => [
                    'toplam' => count($items),
                    'sonuclanan' => $totalSonuclanan,
                    'acik' => $totalAcik
                ]
            ]);
            break;

        case 'getPuantajWorkTypes':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);

            if (!$personelData || empty($personelData->ekip_no)) {
                response(true, []);
            }

            $ekipKodu = $personelData->ekip_no;

            $stmt = $PersonelModel->getDb()->prepare(
                "SELECT DISTINCT is_emri_tipi FROM yapilan_isler WHERE ekip_kodu = ? AND is_emri_tipi IS NOT NULL AND is_emri_tipi != '' ORDER BY is_emri_tipi"
            );
            $stmt->execute([$ekipKodu]);
            $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

            response(true, $types);
            break;

        case 'getRecentActivities':
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();
            $limit = 10;

            // İzin talepleri
            $izinSql = "SELECT 
                            'izin' as type,
                            id,
                            CASE 
                                WHEN onay_durumu = 'Onaylandı' THEN CONCAT(izin_tipi, ' İzni Onaylandı')
                                WHEN onay_durumu = 'Reddedildi' THEN CONCAT(izin_tipi, ' İzni Reddedildi')
                                WHEN onay_durumu = 'iptal edildi' THEN CONCAT(izin_tipi, ' İzni İptal Edildi')
                                ELSE CONCAT(izin_tipi, ' İzin Talebi')
                            END as title,
                            CONCAT(DATE_FORMAT(baslangic_tarihi, '%d.%m'), ' - ', DATE_FORMAT(bitis_tarihi, '%d.%m'), ' tarihli talebiniz') as description,
                            onay_durumu as status,
                            COALESCE(guncelleme_tarihi, olusturma_tarihi) as activity_date
                        FROM personel_izinleri
                        WHERE personel_id = ? AND silinme_tarihi IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Avans talepleri
            $avansSql = "SELECT 
                            'avans' as type,
                            id,
                            CASE 
                                WHEN durum = 'onaylandi' THEN 'Avans Talebi Onaylandı'
                                WHEN durum = 'reddedildi' THEN 'Avans Talebi Reddedildi'
                                ELSE 'Avans Talebi Oluşturuldu'
                            END as title,
                            CONCAT(FORMAT(tutar, 0, 'tr_TR'), ' ₺ tutarında avans talebiniz') as description,
                            durum as status,
                            COALESCE(onay_tarihi, talep_tarihi) as activity_date
                        FROM personel_avanslari
                        WHERE personel_id = ? AND silinme_tarihi IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Genel talepler
            $talepSql = "SELECT 
                            'talep' as type,
                            id,
                            CASE 
                                WHEN durum = 'cozuldu' THEN CONCAT('Talep #', ref_no, ' Çözüldü')
                                WHEN durum = 'devam' THEN CONCAT('Talep #', ref_no, ' İnceleniyor')
                                ELSE CONCAT('Talep #', ref_no, ' Oluşturuldu')
                            END as title,
                            CONCAT(baslik, ' - ', konum) as description,
                            durum as status,
                            COALESCE(cozum_tarihi, olusturma_tarihi) as activity_date
                        FROM personel_talepleri
                        WHERE personel_id = ? AND deleted_at IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Bordrolar
            $bordroSql = "SELECT 
                            'bordro' as type,
                            bp.id,
                            CONCAT('Bordro Hazırlandı - ', bd.donem_adi) as title,
                            CONCAT(FORMAT(bp.net_maas, 2, 'tr_TR'), ' ₺ net ödeme') as description,
                            'tamamlandi' as status,
                            bp.olusturma_tarihi as activity_date
                        FROM bordro_personel bp
                        JOIN bordro_donemi bd ON bp.donem_id = bd.id
                        WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Verileri çek
            $activities = [];

            // İzinler
            $stmt = $db->prepare($izinSql);
            $stmt->execute([$personel_id]);
            $izinler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $izinler);

            // Avanslar
            $stmt = $db->prepare($avansSql);
            $stmt->execute([$personel_id]);
            $avanslar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $avanslar);

            // Talepler
            $stmt = $db->prepare($talepSql);
            $stmt->execute([$personel_id]);
            $talepler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $talepler);

            // Bordrolar
            $stmt = $db->prepare($bordroSql);
            $stmt->execute([$personel_id]);
            $bordrolar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $bordrolar);

            // Tarihe göre sırala (en yeni önce)
            usort($activities, function ($a, $b) {
                return strtotime($b['activity_date']) - strtotime($a['activity_date']);
            });

            // İlk N kaydı al
            $activities = array_slice($activities, 0, $limit);

            // Her etkinlik için formatla
            $formattedActivities = array_map(function ($item) {
                // İkon ve renk belirle
                $iconMap = [
                    'izin' => ['icon' => 'event_available', 'color' => 'blue'],
                    'avans' => ['icon' => 'payments', 'color' => 'green'],
                    'talep' => ['icon' => 'assignment', 'color' => 'orange'],
                    'bordro' => ['icon' => 'receipt_long', 'color' => 'primary']
                ];

                $statusMap = [
                    // İzin durumları
                    'Onaylandı' => ['text' => 'Onaylandı', 'badge' => 'success'],
                    'onaylandi' => ['text' => 'Onaylandı', 'badge' => 'success'],
                    'Reddedildi' => ['text' => 'Reddedildi', 'badge' => 'danger'],
                    'reddedildi' => ['text' => 'Reddedildi', 'badge' => 'danger'],
                    'beklemede' => ['text' => 'Beklemede', 'badge' => 'warning'],
                    'iptal edildi' => ['text' => 'İptal Edildi', 'badge' => 'gray'],
                    // Talep durumları
                    'devam' => ['text' => 'İnceleniyor', 'badge' => 'warning'],
                    'cozuldu' => ['text' => 'Çözüldü', 'badge' => 'success'],
                    // Bordro
                    'tamamlandi' => ['text' => 'Tamamlandı', 'badge' => 'gray']
                ];

                $type = $item['type'];
                $status = strtolower($item['status'] ?? 'beklemede');

                // Göreceli zaman hesapla
                $activityTime = strtotime($item['activity_date']);
                $now = time();
                $diff = $now - $activityTime;

                if ($diff < 60) {
                    $timeAgo = 'Az önce';
                } elseif ($diff < 3600) {
                    $timeAgo = floor($diff / 60) . 'd önce';
                } elseif ($diff < 86400) {
                    $timeAgo = floor($diff / 3600) . 's önce';
                } elseif ($diff < 172800) {
                    $timeAgo = 'Dün';
                } elseif ($diff < 604800) {
                    $timeAgo = floor($diff / 86400) . ' gün önce';
                } else {
                    $timeAgo = date('d M', $activityTime);
                }

                return [
                    'id' => $item['id'],
                    'type' => $type,
                    'icon' => $iconMap[$type]['icon'] ?? 'info',
                    'icon_color' => $iconMap[$type]['color'] ?? 'gray',
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'status' => $status,
                    'status_text' => $statusMap[$status]['text'] ?? ucfirst($status),
                    'status_badge' => $statusMap[$status]['badge'] ?? 'gray',
                    'time_ago' => $timeAgo,
                    'activity_date' => $item['activity_date']
                ];
            }, $activities);

            response(true, $formattedActivities);
            break;

        case 'logout':
            session_destroy();
            response(true, null, 'Çıkış yapıldı');
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }
} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
