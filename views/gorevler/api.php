<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\GorevModel;
use App\Helper\Security;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Gorev = new GorevModel();
    $userId = $_SESSION['user_id'] ?? 0;
    $firmaId = $_SESSION['firma_id'] ?? 0;

    header('Content-Type: application/json');

    try {
        switch ($action) {

            // =====================================================
            // LİSTE İŞLEMLERİ
            // =====================================================
            case 'get-listeler':
                $listeler = $Gorev->getListeler($firmaId, $userId);
                foreach ($listeler as &$liste) {
                    $liste->id = Security::encrypt($liste->id);
                }
                echo json_encode(['success' => true, 'data' => $listeler]);
                break;

            case 'add-liste':
                $baslik = trim($_POST['baslik'] ?? '');
                if (empty($baslik)) {
                    throw new Exception("Liste adı boş olamaz.");
                }

                $id = $Gorev->addListe([
                    'firma_id' => $firmaId,
                    'baslik' => $baslik,
                    'renk' => $_POST['renk'] ?? null,
                    'olusturan_id' => $userId
                ]);

                if ($id) {
                    echo json_encode(['success' => true, 'message' => 'Liste oluşturuldu.', 'id' => Security::encrypt($id)]);
                } else {
                    throw new Exception("Liste oluşturulamadı.");
                }
                break;

            case 'update-liste':
                $id = Security::decrypt($_POST['liste_id']);
                $result = $Gorev->updateListe($id, [
                    'baslik' => $_POST['baslik'] ?? null,
                    'renk' => $_POST['renk'] ?? null
                ]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Liste güncellendi.']);
                } else {
                    throw new Exception("Liste güncellenemedi.");
                }
                break;

            case 'delete-liste':
                $id = Security::decrypt($_POST['liste_id']);
                $liste = $Gorev->findListe($id);
                if (!$liste) {
                    throw new Exception("Liste bulunamadı.");
                }

                $result = $Gorev->deleteListe($id);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Liste ve tüm görevleri silindi.']);
                } else {
                    throw new Exception("Liste silinemedi.");
                }
                break;

            case 'update-liste-sira':
                $siralar = json_decode($_POST['siralar'], true);
                $decrypted = [];
                foreach ($siralar as $s) {
                    $decrypted[] = [
                        'id' => Security::decrypt($s['id']),
                        'sira' => $s['sira']
                    ];
                }
                $Gorev->updateListeSira($decrypted);
                echo json_encode(['success' => true]);
                break;

            // =====================================================
            // GÖREV İŞLEMLERİ
            // =====================================================
            case 'get-gorevler':
                $liste_id = Security::decrypt($_POST['liste_id']);
                $aktifGorevler = $Gorev->getGorevler($liste_id, $userId, 0);
                $tamamlananlar = $Gorev->getTamamlananlar($liste_id, $userId);

                foreach ($aktifGorevler as &$g) {
                    $g->id = Security::encrypt($g->id);
                    $g->liste_id = Security::encrypt($g->liste_id);
                }
                foreach ($tamamlananlar as &$g) {
                    $g->id = Security::encrypt($g->id);
                    $g->liste_id = Security::encrypt($g->liste_id);
                }

                echo json_encode([
                    'success' => true,
                    'data' => $aktifGorevler,
                    'tamamlananlar' => $tamamlananlar
                ]);
                break;

            case 'get-tum-gorevler':
                $listeler = $Gorev->getListeler($firmaId, $userId);
                $result = [];

                foreach ($listeler as $liste) {
                    $aktifGorevler = $Gorev->getGorevler($liste->id, $userId, 0);
                    $tamamlananlar = $Gorev->getTamamlananlar($liste->id, $userId);

                    $encListeId = Security::encrypt($liste->id);

                    foreach ($aktifGorevler as &$g) {
                        $g->id = Security::encrypt($g->id);
                        $g->liste_id = $encListeId;
                    }
                    foreach ($tamamlananlar as &$g) {
                        $g->id = Security::encrypt($g->id);
                        $g->liste_id = $encListeId;
                    }

                    $result[] = [
                        'liste' => [
                            'id' => $encListeId,
                            'baslik' => $liste->baslik,
                            'renk' => $liste->renk,
                            'sira' => $liste->sira,
                            'aktif_gorev_sayisi' => $liste->aktif_gorev_sayisi,
                            'tamamlanan_gorev_sayisi' => $liste->tamamlanan_gorev_sayisi
                        ],
                        'gorevler' => $aktifGorevler,
                        'tamamlananlar' => $tamamlananlar
                    ];
                }

                echo json_encode(['success' => true, 'data' => $result]);
                break;

            case 'add-gorev':
                $liste_id = Security::decrypt($_POST['liste_id']);
                $baslik = trim($_POST['baslik'] ?? '');

                if (empty($baslik)) {
                    throw new Exception("Görev başlığı boş olamaz.");
                }

                $data = [
                    'liste_id' => $liste_id,
                    'firma_id' => $firmaId, // Kept original $firmaId
                    'baslik' => $baslik, // Kept original $baslik
                    'aciklama' => $_POST['aciklama'] ?? null,
                    'tarih' => (isset($_POST['tarih']) && $_POST['tarih'] !== '') ? $_POST['tarih'] : null,
                    'saat' => (isset($_POST['saat']) && $_POST['saat'] !== '') ? $_POST['saat'] : null,
                    'yineleme_sikligi' => (isset($_POST['yineleme_sikligi']) && $_POST['yineleme_sikligi'] !== '') ? $_POST['yineleme_sikligi'] : null,
                    'yineleme_birimi' => $_POST['yineleme_birimi'] ?? null,
                    'yineleme_gunleri' => $_POST['yineleme_gunleri'] ?? null,
                    'yineleme_baslangic' => (isset($_POST['yineleme_baslangic']) && $_POST['yineleme_baslangic'] !== '') ? $_POST['yineleme_baslangic'] : null,
                    'yineleme_bitis_tipi' => $_POST['yineleme_bitis_tipi'] ?? null,
                    'yineleme_bitis_tarihi' => (isset($_POST['yineleme_bitis_tarihi']) && $_POST['yineleme_bitis_tarihi'] !== '') ? $_POST['yineleme_bitis_tarihi'] : null,
                    'yineleme_bitis_adet' => (isset($_POST['yineleme_bitis_adet']) && $_POST['yineleme_bitis_adet'] !== '') ? $_POST['yineleme_bitis_adet'] : null,
                    'olusturan_id' => $userId,
                    'gorev_kullanicilari' => (isset($_POST['gorev_kullanicilari']) && $_POST['gorev_kullanicilari'] !== '') ? $_POST['gorev_kullanicilari'] : null
                ];

                $id = $Gorev->addGorev($data);

                if ($id) {
                    $gorev = $Gorev->findGorev($id);
                    $gorev->id = Security::encrypt($gorev->id);
                    $gorev->liste_id = Security::encrypt($gorev->liste_id);
                    echo json_encode(['success' => true, 'message' => 'Görev eklendi.', 'data' => $gorev]);
                } else {
                    throw new Exception("Görev eklenemedi.");
                }
                break;

            case 'update-gorev':
                $id = Security::decrypt($_POST['gorev_id']);
                $data = [];

                if (isset($_POST['baslik']))
                    $data['baslik'] = $_POST['baslik'];
                if (isset($_POST['aciklama']))
                    $data['aciklama'] = $_POST['aciklama'];
                if (array_key_exists('tarih', $_POST))
                    $data['tarih'] = !empty($_POST['tarih']) ? $_POST['tarih'] : null;
                if (array_key_exists('saat', $_POST))
                    $data['saat'] = !empty($_POST['saat']) ? $_POST['saat'] : null;
                if (isset($_POST['yildizli']))
                    $data['yildizli'] = $_POST['yildizli'];
                if (array_key_exists('yineleme_sikligi', $_POST))
                    $data['yineleme_sikligi'] = !empty($_POST['yineleme_sikligi']) ? $_POST['yineleme_sikligi'] : null;
                if (array_key_exists('yineleme_birimi', $_POST))
                    $data['yineleme_birimi'] = !empty($_POST['yineleme_birimi']) ? $_POST['yineleme_birimi'] : null;
                if (array_key_exists('yineleme_baslangic', $_POST))
                    $data['yineleme_baslangic'] = !empty($_POST['yineleme_baslangic']) ? $_POST['yineleme_baslangic'] : null;
                if (array_key_exists('yineleme_bitis_tipi', $_POST))
                    $data['yineleme_bitis_tipi'] = !empty($_POST['yineleme_bitis_tipi']) ? $_POST['yineleme_bitis_tipi'] : null;
                if (array_key_exists('yineleme_bitis_tarihi', $_POST))
                    $data['yineleme_bitis_tarihi'] = !empty($_POST['yineleme_bitis_tarihi']) ? $_POST['yineleme_bitis_tarihi'] : null;
                if (array_key_exists('yineleme_bitis_adet', $_POST))
                    $data['yineleme_bitis_adet'] = !empty($_POST['yineleme_bitis_adet']) ? $_POST['yineleme_bitis_adet'] : null;
                if (array_key_exists('yineleme_gunleri', $_POST))
                    $data['yineleme_gunleri'] = !empty($_POST['yineleme_gunleri']) ? $_POST['yineleme_gunleri'] : null;
                if (array_key_exists('gorev_kullanicilari', $_POST)) {
                    $kullanicilar = $_POST['gorev_kullanicilari'] ?? '';
                    $realIds = [];
                    if (!empty($kullanicilar)) {
                        $encryptedIds = explode(',', $kullanicilar);
                        foreach ($encryptedIds as $encId) {
                            $decId = Security::decrypt(trim($encId));
                            if ($decId) {
                                $realIds[] = $decId;
                            }
                        }
                    }
                    $data['gorev_kullanicilari'] = !empty($realIds) ? implode(',', $realIds) : null;
                }

                if (isset($_POST['ana_sayfa_goster']))
                    $data['ana_sayfa_goster'] = $_POST['ana_sayfa_goster'];

                if (empty($data)) {
                    echo json_encode(['success' => true, 'message' => 'Değişiklik yok.']);
                    break;
                }

                $result = $Gorev->updateGorev($id, $data);
                if ($result) {
                    $gorev = $Gorev->findGorev($id);
                    $gorev->id = Security::encrypt($gorev->id);
                    $gorev->liste_id = Security::encrypt($gorev->liste_id);
                    echo json_encode(['success' => true, 'message' => 'Görev güncellendi.', 'data' => $gorev]);
                } else {
                    throw new Exception("Güncelleme başarısız.");
                }
                break;

            case 'delete-gorev':
                $id = Security::decrypt($_POST['gorev_id']);
                $result = $Gorev->deleteGorev($id);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Görev silindi.']);
                } else {
                    throw new Exception("Görev silinemedi.");
                }
                break;

            case 'tamamla':
                $id = Security::decrypt($_POST['gorev_id']);
                $result = $Gorev->tamamla($id);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Görev tamamlandı.']);
                } else {
                    throw new Exception("İşlem başarısız.");
                }
                break;

            case 'geri-al':
                $id = Security::decrypt($_POST['gorev_id']);
                $result = $Gorev->tamamlamayiGeriAl($id);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Görev geri alındı.']);
                } else {
                    throw new Exception("İşlem başarısız.");
                }
                break;

            case 'update-sira':
                $gorevler = json_decode($_POST['gorevler'], true);
                $decrypted = [];
                foreach ($gorevler as $g) {
                    $decrypted[] = [
                        'id' => Security::decrypt($g['id']),
                        'sira' => $g['sira'],
                        'liste_id' => Security::decrypt($g['liste_id'])
                    ];
                }
                $Gorev->updateGorevSira($decrypted);
                echo json_encode(['success' => true]);
                break;

            // =====================================================
            // BİLDİRİM İŞLEMLERİ (İstemci Tarafı)
            // =====================================================
            case 'get-upcoming-alarms':
                $Settings = new \App\Model\SettingsModel();
                $recipientsSetting = $Settings->getSettings('gorev_bildirim_kullanicilar');

                $targetUserIds = [];
                if (!empty($recipientsSetting)) {
                    $encryptedIds = explode(',', $recipientsSetting);
                    foreach ($encryptedIds as $encId) {
                        $decId = Security::decrypt(trim($encId));
                        if ($decId) {
                            $targetUserIds[] = (int) $decId;
                        }
                    }
                }

                $bekleyenGorevler = $Gorev->getBildirimBekleyenGorevler($userId);

                $benimGorevlerim = array_filter($bekleyenGorevler, function ($g) use ($userId, $targetUserIds) {
                    $sorumluId = $g->olusturan_id ?? $g->liste_olusturan_id;

                    if (!empty($g->gorev_kullanicilari)) {
                        $taskUserIds = explode(',', $g->gorev_kullanicilari);
                        $usersToNotify = array_map('intval', $taskUserIds);
                    } else {
                        $usersToNotify = !empty($targetUserIds) ? $targetUserIds : [$sorumluId];
                    }

                    $usersToNotify = array_unique(array_filter($usersToNotify));

                    return in_array($userId, $usersToNotify);
                });

                $benimGorevlerim = array_values($benimGorevlerim);

                foreach ($benimGorevlerim as &$g) {
                    $g->id = Security::encrypt($g->id);
                    $g->liste_id = Security::encrypt($g->liste_id);
                }
                echo json_encode(['success' => true, 'data' => $benimGorevlerim]);
                break;

            case 'mark-notified':
                $id = Security::decrypt($_POST['gorev_id']);

                // Görev bilgilerini al (Bildirim içeriği için)
                $sqlGorev = "SELECT g.*, gl.baslik as liste_adi FROM gorevler g 
                            JOIN gorev_listeleri gl ON g.liste_id = gl.id 
                            WHERE g.id = :id";
                $stmtGorev = $Gorev->db->prepare($sqlGorev);
                $stmtGorev->execute([':id' => $id]);
                $gorev = $stmtGorev->fetch(PDO::FETCH_OBJ);

                $result = $Gorev->markBildirimGonderildi($id);

                if ($result && $gorev) {
                    // Ayarlardaki kullanıcıları al
                    $Settings = new \App\Model\SettingsModel();
                    $recipientsSetting = $Settings->getSettings('gorev_bildirim_kullanicilar');

                    $targetUserIds = [];
                    if (!empty($recipientsSetting)) {
                        $encryptedIds = explode(',', $recipientsSetting);
                        foreach ($encryptedIds as $encId) {
                            $decId = Security::decrypt(trim($encId));
                            if ($decId) {
                                $targetUserIds[] = (int) $decId;
                            }
                        }
                    }

                    if (!empty($gorev->gorev_kullanicilari)) {
                        $taskUserIds = explode(',', $gorev->gorev_kullanicilari);
                        $usersToNotify = array_map('intval', $taskUserIds);
                    } else {
                        $usersToNotify = !empty($targetUserIds) ? $targetUserIds : [$gorev->olusturan_id];
                    }

                    $usersToNotify = array_unique(array_filter($usersToNotify));

                    $pushService = new \App\Service\PushNotificationService();
                    $mailService = new \App\Service\MailGonderService();
                    $saatStr = $gorev->saat ? ' (Saat: ' . substr($gorev->saat, 0, 5) . ')' : '';
                    $payload = [
                        'title' => '📋 Görev Hatırlatması',
                        'body' => $gorev->baslik . $saatStr . ' [' . $gorev->liste_adi . ']',
                        'url' => 'index.php?p=gorevler/list'
                    ];

                    // Görev hatırlatması için mail verisi
                    $mailData = [
                        'konu' => 'Görev Hatırlatması: ' . $gorev->baslik,
                        'icerik' => "<b>{$gorev->liste_adi}</b> adlı listedeki görevinizin zamanı geldi/yaklaşıyor.<br><br><b>Görev:</b> {$gorev->baslik}{$saatStr}"
                    ];

                    foreach ($usersToNotify as $targetId) {
                        $pushService->sendToPersonel($targetId, $payload);

                        // Mail gönderimi için kullanıcı e-postasını al
                        $User = new \App\Model\UserModel();
                        $targetUser = $User->find($targetId);
                        if ($targetUser && !empty($targetUser->email)) {
                            \App\Service\MailGonderService::gonder($targetUser->email, $mailData['konu'], $mailData['icerik']);
                        }
                    }
                }

                echo json_encode(['success' => $result]);
                break;

            // =====================================================
            // AYAR İŞLEMLERİ
            // =====================================================
            case 'get-settings':
                $Settings = new \App\Model\SettingsModel();
                $User = new \App\Model\UserModel();

                // Kayıtlı seçili kullanıcıların gerçek ID'lerini al
                $recipientsSetting = $Settings->getSettings('gorev_bildirim_kullanicilar') ?? '';
                $selectedRealIds = !empty($recipientsSetting) ? explode(',', $recipientsSetting) : [];

                $users = $User->getUsers();
                $userList = [];
                foreach ($users as $u) {
                    $userList[] = [
                        'id' => Security::encrypt($u->id),
                        'text' => $u->adi_soyadi,
                        'selected' => in_array($u->id, $selectedRealIds)
                    ];
                }

                $data = [
                    'gorev_bildirim_dakika' => $Settings->getSettings('gorev_bildirim_dakika') ?? '15',
                    'users' => $userList
                ];
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            case 'save-settings':
                $Settings = new \App\Model\SettingsModel();
                $dakika = $_POST['gorev_bildirim_dakika'] ?? '15';
                $kullanicilar = $_POST['gorev_bildirim_kullanicilar'] ?? '';

                $realIds = [];
                if (!empty($kullanicilar)) {
                    $encryptedIds = explode(',', $kullanicilar);
                    foreach ($encryptedIds as $encId) {
                        $decId = Security::decrypt(trim($encId));
                        if ($decId) {
                            $realIds[] = $decId;
                        }
                    }
                }

                $res1 = $Settings->upsertSetting('gorev_bildirim_dakika', $dakika);
                $res2 = $Settings->upsertSetting('gorev_bildirim_kullanicilar', implode(',', $realIds));

                if ($res1 && $res2) {
                    echo json_encode(['success' => true, 'message' => 'Ayarlar kaydedildi.']);
                } else {
                    throw new Exception("Ayarlar kaydedilemedi.");
                }
                break;

            case 'get-settings-for-task':
                $gorevId = Security::decrypt($_POST['gorev_id']);
                $gorev = $Gorev->findGorev($gorevId);

                $selectedRealIds = [];
                if ($gorev && !empty($gorev->gorev_kullanicilari)) {
                    $selectedRealIds = explode(',', $gorev->gorev_kullanicilari);
                }

                $User = new \App\Model\UserModel();
                $users = $User->getUsers();
                $userList = [];
                foreach ($users as $u) {
                    $userList[] = [
                        'id' => Security::encrypt($u->id),
                        'text' => $u->adi_soyadi,
                        'selected' => in_array($u->id, $selectedRealIds)
                    ];
                }

                echo json_encode(['success' => true, 'data' => ['users' => $userList]]);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
