<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;
use App\Model\BildirimModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $talepModel = new TalepModel();
    $avansModel = new AvansModel();
    $izinModel = new PersonelIzinleriModel();
    $bildirimModel = new BildirimModel();
    $systemLogModel = new SystemLogModel();
    $currentUserId = intval($_SESSION['user_id'] ?? 0);

    try {
        if ($currentUserId <= 0) {
            throw new Exception('Oturum sonlanmış veya geçersiz.');
        }

        switch ($action) {

            // Tüm bekleyen talepleri getir (Avans + İzin + Genel Talepler)
            case 'get-all-pending':
                $tip = $_POST['tip'] ?? 'all';

                $avanslar = [];
                $izinler = [];
                $talepler = [];

                if (($tip == 'all' || $tip == 'avans') && Gate::allows('avans_talepleri')) {
                    $avanslar = $avansModel->getButunBekleyenAvanslar();
                }

                if (($tip == 'all' || $tip == 'izin') && Gate::allows('izin_talepleri')) {
                    try {
                        $izinler = $izinModel->getButunBekleyenIzinler();
                    } catch (\Exception $e) {
                        $izinler = [];
                    }
                }

                if (($tip == 'all' || $tip == 'talep') && Gate::allows('ariza_talepleri')) {
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

                if (($tip == 'all' || $tip == 'avans') && Gate::allows('avans_talepleri')) {
                    $avanslar = $avansModel->getIslenmisAvanslar($limit);
                }

                if (($tip == 'all' || $tip == 'izin') && Gate::allows('izin_talepleri')) {
                    try {
                        $izinler = $izinModel->getIslenmisIzinler($limit);
                    } catch (\Exception $e) {
                        $izinler = [];
                    }
                }

                if (($tip == 'all' || $tip == 'talep') && Gate::allows('ariza_talepleri')) {
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
                if (!Gate::allows('avans_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

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
                if (!Gate::allows('izin_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

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
                if (!Gate::allows('ariza_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

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
                if (!Gate::allows('avans_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $hesaba_isle = isset($_POST['hesaba_isle']) && $_POST['hesaba_isle'] == '1';
                
                $onay_tipi = $_POST['onay_tipi'] ?? 'ayni'; // 'ayni' or 'farkli'
                $onaylanan_tutar = null;
                if ($onay_tipi === 'farkli' && isset($_POST['farkli_tutar'])) {
                    $onaylanan_tutar = str_replace(',', '.', str_replace('.', '', $_POST['farkli_tutar']));
                    $onaylanan_tutar = floatval($onaylanan_tutar);
                    
                    // Otomatik açıklama ekle
                    $tutarFormat = number_format($onaylanan_tutar, 2, ',', '.') . ' TL';
                    $oto_mesaj = "Avans talebiniz $tutarFormat olarak uygun görülmüştür.";
                    if (empty($aciklama)) {
                        $aciklama = $oto_mesaj;
                    }
                }

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }

                if ($avansModel->updateDurum($id, 'onaylandi', $aciklama, $onaylanan_tutar)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'avans', $id);
                    }

                    // Eğer hesaba işlenecekse
                    if ($hesaba_isle) {
                        $avansModel->avansHesabaIsle($id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Avans Onaylama',
                        "Avans talebi onaylandı. ID: $id" . ($onaylanan_tutar ? ", Onaylanan Tutar: $onaylanan_tutar TL" : "") . ($aciklama ? ", Açıklama: $aciklama" : ""),
                        SystemLogModel::LEVEL_IMPORTANT
                    );

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
                if (!Gate::allows('avans_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }

                if (empty($aciklama)) {
                    throw new Exception('Red açıklaması zorunludur.');
                }

                if ($avansModel->updateDurum($id, 'reddedildi', $aciklama)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'avans', $id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Avans Reddetme',
                        "Avans talebi reddedildi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );

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
                if (!Gate::allows('izin_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                
                $onay_tipi = $_POST['onay_tipi'] ?? 'ayni'; // 'ayni' or 'farkli'
                $farkli_baslangic = $_POST['farkli_baslangic'] ?? null;
                $farkli_bitis = $_POST['farkli_bitis'] ?? null;

                if ($onay_tipi === 'farkli' && $farkli_baslangic && $farkli_bitis) {
                    // Otomatik açıklama ekle
                    $gun = $izinModel->hesaplaIzinGunu($farkli_baslangic, $farkli_bitis);
                    $basFormat = date('d.m.Y', strtotime($farkli_baslangic));
                    $bitFormat = date('d.m.Y', strtotime($farkli_bitis));
                    
                    $oto_mesaj = "İzin talebiniz $basFormat - $bitFormat tarihleri arasında $gun gün olarak uygun görülmüştür.";
                    if (empty($aciklama)) {
                        $aciklama = $oto_mesaj;
                    }
                }

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }

                if ($izinModel->updateDurum($id, 'Onaylandı', $aciklama, $farkli_baslangic, $farkli_bitis)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'izin', $id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'İzin Onaylama',
                        "İzin talebi onaylandı. ID: $id" . ($farkli_baslangic ? ", Tarih: $farkli_baslangic / $farkli_bitis" : "") . ($aciklama ? ", Açıklama: $aciklama" : ""),
                        SystemLogModel::LEVEL_IMPORTANT
                    );

                    // Push Bildirim Gönder
                    try {
                        $izin = $izinModel->find($id);
                        if ($izin && $izin->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($izin->personel_id, [
                                'title' => '✅ İzin Onaylandı',
                                'body' => date('d.m.Y', strtotime($izin->baslangic_tarihi)) . ' - ' . date('d.m.Y', strtotime($izin->bitis_tarihi)) . ' tarihleri arasındaki izniniz onaylandı.',
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
                if (!Gate::allows('izin_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }

                if (empty($aciklama)) {
                    throw new Exception('Red açıklaması zorunludur.');
                }

                if ($izinModel->updateDurum($id, 'Reddedildi', $aciklama)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'izin', $id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'İzin Reddetme',
                        "İzin talebi reddedildi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );

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
                if (!Gate::allows('ariza_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }

                if ($talepModel->updateDurum($id, 'cozuldu', $aciklama)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'talep', $id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Talep Çözüldü İşaretleme',
                        "Talep çözüldü olarak işaretlendi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );

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
                if (!Gate::allows('ariza_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }

                if ($talepModel->updateDurum($id, 'islemde', null)) {
                    if ($currentUserId > 0) {
                        $bildirimModel->markRequestNotificationAsRead($currentUserId, 'talep', $id);
                    }

                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Talep İşleme Alma',
                        "Talep işleme alındı. ID: $id",
                        SystemLogModel::LEVEL_IMPORTANT
                    );

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

                $avanslar = [];
                if (Gate::allows('avans_talepleri')) {
                    $avanslar = $avansModel->getBekleyenAvanslarForDashboard($limit);
                }

                $izinler = [];
                if (Gate::allows('izin_talepleri')) {
                    try {
                        $izinler = $izinModel->getBekleyenIzinlerForDashboard($limit);
                    } catch (\Exception $e) {
                        $izinler = [];
                    }
                }

                $talepler = [];
                if (Gate::allows('ariza_talepleri')) {
                    $talepler = $talepModel->getBekleyenTaleplerForDashboard($limit);
                }

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

            // Avans Sil
            case 'avans-sil':
                if (!Gate::allows('avans_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz avans ID.');
                }
                if (empty($aciklama)) {
                    throw new Exception('Silme gerekçesi zorunludur.');
                }

                $avans = $avansModel->find($id);
                if ($avans) {
                    // Bildirimi gönder
                    try {
                        if ($avans->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($avans->personel_id, [
                                'title' => '🗑️ Avans Talebi Silindi',
                                'body' => 'Avans talebiniz sistemden silindi. Sebep: ' . $aciklama,
                                'url' => 'index.php?page=bordro'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    // Kaydı soft delete ile işaretle
                    $db = $avansModel->getDb();
                    $stmt = $db->prepare("UPDATE personel_avanslari SET silinme_tarihi = NOW(), silen_kullanici = ?, silinme_aciklama = ? WHERE id = ?");
                    $stmt->execute([$currentUserId, $aciklama, $id]);
                    
                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Avans Silme',
                        "Avans talebi silindi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );
                    
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Avans talebi başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Silinecek avans bulunamadı.');
                }
                break;

            // İzin Sil
            case 'izin-sil':
                if (!Gate::allows('izin_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz izin ID.');
                }
                if (empty($aciklama)) {
                    throw new Exception('Silme gerekçesi zorunludur.');
                }

                $izin = $izinModel->find($id);
                if ($izin) {
                    // Bildirimi gönder
                    try {
                        if ($izin->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($izin->personel_id, [
                                'title' => '🗑️ İzin Talebi Silindi',
                                'body' => 'İzin talebiniz sistemden silindi. Sebep: ' . $aciklama,
                                'url' => 'index.php?page=izin'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    // Kaydı soft delete ile işaretle
                    $db = $izinModel->getDb();
                    $stmt = $db->prepare("UPDATE personel_izinleri SET silinme_tarihi = NOW(), silen_kullanici = ?, silinme_aciklama = ? WHERE id = ?");
                    $stmt->execute([$currentUserId, $aciklama, $id]);
                    
                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'İzin Silme',
                        "İzin talebi silindi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );
                    
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'İzin talebi başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Silinecek izin bulunamadı.');
                }
                break;

            // Talep Sil
            case 'talep-sil':
                if (!Gate::allows('ariza_talepleri')) {
                    throw new Exception('Bu işlem için gerekli yetkiye sahip değilsiniz.');
                }

                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz talep ID.');
                }
                if (empty($aciklama)) {
                    throw new Exception('Silme gerekçesi zorunludur.');
                }

                $talep = $talepModel->find($id);
                if ($talep) {
                    // Bildirimi gönder
                    try {
                        if ($talep->personel_id) {
                            $pushService = new PushNotificationService();
                            $pushService->sendToPersonel($talep->personel_id, [
                                'title' => '🗑️ Talep Silindi',
                                'body' => 'Talebiniz sistemden silindi. Sebep: ' . $aciklama,
                                'url' => 'index.php?page=talep'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Push notification error: ' . $e->getMessage());
                    }

                    // Kaydı soft delete ile işaretle
                    $db = $talepModel->getDb();
                    $stmt = $db->prepare("UPDATE personel_talepleri SET silinme_tarihi = NOW(), silen_kullanici = ?, silinme_aciklama = ? WHERE id = ?");
                    $stmt->execute([$currentUserId, $aciklama, $id]);
                    
                    // Log yaz
                    $systemLogModel->logAction(
                        $currentUserId,
                        'Talep Silme',
                        "Talep silindi. ID: $id, Açıklama: $aciklama",
                        SystemLogModel::LEVEL_IMPORTANT
                    );
                    
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Talep başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Silinecek talep bulunamadı.');
                }
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
