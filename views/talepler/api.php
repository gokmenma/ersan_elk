<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $talepModel = new TalepModel();
    $avansModel = new AvansModel();
    $izinModel = new PersonelIzinleriModel();

    try {
        switch ($action) {

            // Tüm bekleyen talepleri getir (Avans + İzin + Genel Talepler)
            case 'get-all-pending':
                $tip = $_POST['tip'] ?? 'all';

                $avanslar = [];
                $izinler = [];
                $talepler = [];

                if ($tip == 'all' || $tip == 'avans') {
                    $avanslar = $avansModel->getButunBekleyenAvanslar();
                }

                if ($tip == 'all' || $tip == 'izin') {
                    try {
                        $izinler = $izinModel->getButunBekleyenIzinler();
                    } catch (\Exception $e) {
                        $izinler = [];
                    }
                }

                if ($tip == 'all' || $tip == 'talep') {
                    $talepler = $talepModel->getButunBekleyenTalepler();
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'avanslar' => $avanslar,
                        'izinler' => $izinler,
                        'talepler' => $talepler,
                        'toplam' => count($avanslar) + count($izinler) + count($talepler)
                    ]
                ]);
                break;

            // Tüm işlem yapılmış/çözülmüş talepleri getir
            case 'get-all-approved':
                $tip = $_POST['tip'] ?? 'all';
                $limit = intval($_POST['limit'] ?? 50);

                $avanslar = [];
                $izinler = [];
                $talepler = [];

                if ($tip == 'all' || $tip == 'avans') {
                    $avanslar = $avansModel->getIslenmisAvanslar($limit);
                }

                if ($tip == 'all' || $tip == 'izin') {
                    try {
                        $izinler = $izinModel->getIslenmisIzinler($limit);
                    } catch (\Exception $e) {
                        $izinler = [];
                    }
                }

                if ($tip == 'all' || $tip == 'talep') {
                    $talepler = $talepModel->getCozulmusTalepler($limit);
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'avanslar' => $avanslar,
                        'izinler' => $izinler,
                        'talepler' => $talepler,
                        'toplam' => count($avanslar) + count($izinler) + count($talepler)
                    ]
                ]);
                break;

            // Avans Detayı Getir
            case 'get-avans-detay':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }

                $avans = $avansModel->getAvansDetay($id);

                if (!$avans) {
                    throw new Exception('Avans bulunamadı.');
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $avans
                ]);
                break;

            // İzin Detayı Getir
            case 'get-izin-detay':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }

                $izin = $izinModel->getIzinDetay($id);

                if (!$izin) {
                    throw new Exception('İzin bulunamadı.');
                }

                // İzin gün sayısını hesapla
                $izin->gun_sayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);

                echo json_encode([
                    'status' => 'success',
                    'data' => $izin
                ]);
                break;

            // Talep Detayı Getir
            case 'get-talep-detay':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }

                $talep = $talepModel->getTalepDetay($id);

                if (!$talep) {
                    throw new Exception('Talep bulunamadı.');
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $talep
                ]);
                break;

            // Avans Onayla
            case 'avans-onayla':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $hesaba_isle = isset($_POST['hesaba_isle']) && $_POST['hesaba_isle'] == '1';

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }

                if ($avansModel->updateDurum($id, 'onaylandi', $aciklama)) {
                    // Eğer hesaba işlenecekse
                    if ($hesaba_isle) {
                        $avansModel->avansHesabaIsle($id);
                    }

                    // Push Bildirim Gönder
                    try {
                        $avans = $avansModel->find($id);
                        if ($avans && $avans->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($avans->personel_id, [
                                'title' => '✅ Avans Onaylandı',
                                'body' => number_format($avans->tutar, 2, ',', '.') . ' TL tutarındaki avans talebiniz onaylandı.',
                                'url' => 'index.php?page=bordro'
                            ]);
                        }
                    } catch (Exception $e) {
                        // Bildirim hatası loglansın ama işlemi engellemesin
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Avans talebi onaylandı.'
                    ]);
                } else {
                    throw new Exception('Avans onaylanırken hata oluştu.');
                }
                break;

            // Avans Reddet
            case 'avans-reddet':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }

                if (empty($aciklama)) {
                    throw new Exception('Red açıklaması zorunludur.');
                }

                if ($avansModel->updateDurum($id, 'reddedildi', $aciklama)) {
                    // Push Bildirim Gönder
                    try {
                        $avans = $avansModel->find($id);
                        if ($avans && $avans->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($avans->personel_id, [
                                'title' => '❌ Avans Reddedildi',
                                'body' => 'Avans talebiniz reddedildi. Detaylar için uygulamayı kontrol edin.',
                                'url' => 'index.php?page=bordro'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Avans talebi reddedildi.'
                    ]);
                } else {
                    throw new Exception('Avans reddedilirken hata oluştu.');
                }
                break;

            // İzin Onayla
            case 'izin-onayla':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }

                if ($izinModel->updateDurum($id, 'Onaylandı', $aciklama)) {
                    // Push Bildirim Gönder
                    try {
                        $izin = $izinModel->find($id);
                        if ($izin && $izin->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($izin->personel_id, [
                                'title' => '✅ İzin Onaylandı',
                                'body' => 'İzin talebiniz onaylandı. İyi tatiller!',
                                'url' => 'index.php?page=izin'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'İzin talebi onaylandı.'
                    ]);
                } else {
                    throw new Exception('İzin onaylanırken hata oluştu.');
                }
                break;

            // İzin Reddet
            case 'izin-reddet':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }

                if (empty($aciklama)) {
                    throw new Exception('Red açıklaması zorunludur.');
                }

                if ($izinModel->updateDurum($id, 'Reddedildi', $aciklama)) {
                    // Push Bildirim Gönder
                    try {
                        // find yerine getIzinDetay kullanarak daha detaylı bilgi alalım
                        $izin = $izinModel->getIzinDetay($id);
                        if ($izin && $izin->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($izin->personel_id, [
                                'title' => '❌ İzin Reddedildi',
                                'body' => 'İzin talebiniz reddedildi. Detaylar için uygulamayı kontrol edin.',
                                'url' => 'index.php?page=izin'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'İzin talebi reddedildi.'
                    ]);
                } else {
                    throw new Exception('İzin reddedilirken hata oluştu.');
                }
                break;

            // Talep Çözüldü İşaretle
            case 'talep-cozuldu':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }

                if ($talepModel->updateDurum($id, 'cozuldu', $aciklama)) {
                    // Push Bildirim Gönder
                    try {
                        $talep = $talepModel->find($id);
                        if ($talep && $talep->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($talep->personel_id, [
                                'title' => '✅ Talep Çözüldü',
                                'body' => 'Talebiniz çözüldü olarak işaretlendi.',
                                'url' => 'index.php?page=talep'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Talep çözüldü olarak işaretlendi.'
                    ]);
                } else {
                    throw new Exception('Talep güncellenirken hata oluştu.');
                }
                break;

            // Talep İşleme Al
            case 'talep-isleme-al':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }

                if ($talepModel->updateDurum($id, 'islemde', null)) {
                    // Push Bildirim Gönder
                    try {
                        $talep = $talepModel->getTalepDetay($id);
                        if ($talep && $talep->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($talep->personel_id, [
                                'title' => '⚙️ Talep İşleme Alındı',
                                'body' => 'Talebiniz işleme alındı. En kısa sürede çözüme kavuşturulacaktır.',
                                'url' => 'index.php?page=talep'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Talep işleme alındı.'
                    ]);
                } else {
                    throw new Exception('Talep güncellenirken hata oluştu.');
                }
                break;

            // Dashboard için özet getir
            case 'get-dashboard-summary':
                $limit = intval($_POST['limit'] ?? 10);

                $avanslar = $avansModel->getBekleyenAvanslarForDashboard($limit);

                try {
                    $izinler = $izinModel->getBekleyenIzinlerForDashboard($limit);
                } catch (\Exception $e) {
                    $izinler = [];
                }

                $talepler = $talepModel->getBekleyenTaleplerForDashboard($limit);

                // Personel bilgilerini çek
                $personelModel = new PersonelModel();
                $all_requests = array_merge($avanslar, $izinler, $talepler);

                // Tarihe göre sırala
                usort($all_requests, function ($a, $b) {
                    return strtotime($b->tarih) - strtotime($a->tarih);
                });

                $recent_requests = array_slice($all_requests, 0, $limit);

                // Personel bilgilerini ekle
                foreach ($recent_requests as &$req) {
                    $personel = $personelModel->find($req->personel_id);
                    if ($personel) {
                        $req->adi_soyadi = $personel->adi_soyadi;
                        $req->resim_yolu = $personel->resim_yolu;
                        $req->departman = $personel->departman;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $recent_requests,
                    'counts' => [
                        'avans' => count($avanslar),
                        'izin' => count($izinler),
                        'talep' => count($talepler),
                        'toplam' => count($avanslar) + count($izinler) + count($talepler)
                    ]
                ]);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
