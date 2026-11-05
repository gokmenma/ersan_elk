<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\GorevModel;
use App\Helper\Security;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Gorev = new GorevModel();
    $userId = $_SESSION['user_id'] ?? 0;
    $firmaId = $_SESSION['firma_id'] ?? 0;

    if ($userId <= 0 || $firmaId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu.']);
        exit;
    }

    $permissionPolicy = new PermissionPolicyModel();
    if ($permissionPolicy->isReady()) {
        Gate::authorizeApiPolicy('gorevler/api', (string) $action);
    } elseif (!Gate::allows('gorevler')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Görev işlemleri için yetkiniz bulunmamaktadır.']);
        exit;
    }
    $logla = function (string $tip, string $aciklama) use ($userId) {
        try {
            (new \App\Model\SystemLogModel())->logAction($userId, $tip, $aciklama, \App\Model\SystemLogModel::LEVEL_INFO);
        } catch (\Throwable $t) {
            error_log('Görev log hatası: ' . $t->getMessage());
        }
    };
    $listeSahibiOl = function ($listeId) use ($Gorev, $userId, $firmaId) {
        if ($Gorev->listeYetkisi($listeId, $userId, $firmaId) !== 'sahip') {
            throw new Exception("Bu işlem yalnızca liste sahibi tarafından yapılabilir.");
        }
    };
    $listeDuzenleyebilir = function ($listeId) use ($Gorev, $userId, $firmaId) {
        if (!in_array($Gorev->listeYetkisi($listeId, $userId, $firmaId), ['sahip', 'duzenle'], true)) {
            throw new Exception("Bu listede değişiklik yapma yetkiniz yok.");
        }
    };
    $gorevDuzenleyebilir = function ($gorevId) use ($Gorev, $userId, $firmaId) {
        if (!in_array($Gorev->gorevYetkisi($gorevId, $userId, $firmaId), ['sahip', 'duzenle'], true)) {
            throw new Exception("Bu görevde değişiklik yapma yetkiniz yok.");
        }
    };
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
                    $logla('Görev Listesi Oluşturma', "'$baslik' listesi oluşturuldu.");
                    echo json_encode(['success' => true, 'message' => 'Liste oluşturuldu.', 'id' => Security::encrypt($id)]);
                } else {
                    throw new Exception("Liste oluşturulamadı.");
                }
                break;

            case 'update-liste':
                $id = Security::decrypt($_POST['liste_id']);
                $listeSahibiOl($id);
                $result = $Gorev->updateListe($id, [
                    'baslik' => $_POST['baslik'] ?? null,
                    'renk' => $_POST['renk'] ?? null
                ]);

                if ($result) {
                    $logla('Görev Listesi Güncelleme', "Liste #$id güncellendi.");
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
                $listeSahibiOl($id);

                $result = $Gorev->deleteListe($id);
                if ($result) {
                    $logla('Görev Listesi Silme', "'{$liste->baslik}' listesi silindi.");
                    echo json_encode(['success' => true, 'message' => 'Liste ve tüm görevleri silindi.']);
                } else {
                    throw new Exception("Liste silinemedi.");
                }
                break;

            case 'update-liste-sira':
                $siralar = json_decode($_POST['siralar'], true);
                $decrypted = [];
                foreach ((array) $siralar as $s) {
                    if ($Gorev->listeYetkisi(Security::decrypt($s['id']), $userId, $firmaId) === null) {
                        continue;
                    }
                    $decrypted[] = [
                        'id' => Security::decrypt($s['id']),
                        'sira' => $s['sira']
                    ];
                }
                $Gorev->updateListeSira($decrypted);
                echo json_encode(['success' => true]);
                break;

            case 'get-liste-paylasim':
                $liste_id = Security::decrypt($_POST['liste_id']);
                $listeSahibiOl($liste_id);

                $paylasimlar = [];
                foreach ($Gorev->getPaylasimlar($liste_id) as $p) {
                    $paylasimlar[(int) $p->user_id] = $p->yetki;
                }

                $kullanicilar = [];
                foreach ((new \App\Model\UserModel())->getUsers() as $u) {
                    if ((int) $u->id === (int) $userId) {
                        continue;
                    }
                    $kullanicilar[] = [
                        'id' => Security::encrypt($u->id),
                        'text' => $u->adi_soyadi,
                        'yetki' => $paylasimlar[(int) $u->id] ?? null
                    ];
                }
                echo json_encode(['success' => true, 'data' => ['kullanicilar' => $kullanicilar]]);
                break;

            case 'save-liste-paylasim':
                $liste_id = Security::decrypt($_POST['liste_id']);
                $listeSahibiOl($liste_id);

                $gelen = json_decode($_POST['paylasimlar'] ?? '[]', true);
                if (!is_array($gelen)) {
                    throw new Exception("Geçersiz paylaşım verisi.");
                }

                $gecerliIds = [];
                foreach ((new \App\Model\UserModel())->getUsers() as $u) {
                    $gecerliIds[(int) $u->id] = true;
                }

                $kayitlar = [];
                foreach ($gelen as $k) {
                    $hedefId = (int) Security::decrypt($k['id'] ?? '');
                    if ($hedefId <= 0 || $hedefId === (int) $userId || !isset($gecerliIds[$hedefId])) {
                        continue;
                    }
                    $kayitlar[$hedefId] = [
                        'user_id' => $hedefId,
                        'yetki' => ($k['yetki'] ?? '') === 'goruntule' ? 'goruntule' : 'duzenle'
                    ];
                }

                if (!$Gorev->savePaylasimlar($liste_id, $userId, array_values($kayitlar))) {
                    throw new Exception("Paylaşım kaydedilemedi.");
                }
                $logla('Görev Listesi Paylaşımı', "Liste #$liste_id " . count($kayitlar) . " kullanıcı ile paylaşıldı.");
                echo json_encode(['success' => true, 'message' => 'Paylaşım ayarları kaydedildi.']);
                break;

            // =====================================================
            // GÖREV İŞLEMLERİ
            // =====================================================
            case 'get-gorevler':
                $liste_id = Security::decrypt($_POST['liste_id']);
                if ($Gorev->findListe($liste_id) === false || $Gorev->findListe($liste_id)->firma_id != $firmaId) {
                    throw new Exception("Liste bulunamadı.");
                }
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

                $kullaniciAdlari = [];
                foreach ((new \App\Model\UserModel())->getUsers() as $u) {
                    $kullaniciAdlari[(int) $u->id] = (string) $u->adi_soyadi;
                }
                $kisileriEkle = function ($g) use ($kullaniciAdlari) {
                    $atananlar = [];
                    foreach (array_filter(array_map('intval', explode(',', (string) $g->gorev_kullanicilari))) as $uid) {
                        if (isset($kullaniciAdlari[$uid])) {
                            $atananlar[] = ['id' => $uid, 'ad' => $kullaniciAdlari[$uid]];
                        }
                    }
                    $g->atananlar = $atananlar;
                    $g->olusturan_adi = $kullaniciAdlari[(int) $g->olusturan_id] ?? null;
                };

                foreach ($listeler as $liste) {
                    $aktifGorevler = $Gorev->getGorevler($liste->id, $userId, 0);
                    $tamamlananlar = $Gorev->getTamamlananlar($liste->id, $userId);

                    $encListeId = Security::encrypt($liste->id);

                    foreach ($aktifGorevler as &$g) {
                        $kisileriEkle($g);
                        $g->id = Security::encrypt($g->id);
                        $g->liste_id = $encListeId;
                    }
                    unset($g);
                    foreach ($tamamlananlar as &$g) {
                        $kisileriEkle($g);
                        $g->id = Security::encrypt($g->id);
                        $g->liste_id = $encListeId;
                    }
                    unset($g);

                    $result[] = [
                        'liste' => [
                            'id' => $encListeId,
                            'baslik' => $liste->baslik,
                            'renk' => $liste->renk,
                            'sira' => $liste->sira,
                            'sahip' => (bool) $liste->sahip,
                            'erisim' => $liste->erisim,
                            'sahip_adi' => $liste->sahip_adi,
                            'paylasim_sayisi' => (int) $liste->paylasim_sayisi,
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
                $listeDuzenleyebilir($liste_id);

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
                    $logla('Görev Ekleme', "'$baslik' görevi eklendi.");
                    $gorev->id = Security::encrypt($gorev->id);
                    $gorev->liste_id = Security::encrypt($gorev->liste_id);
                    echo json_encode(['success' => true, 'message' => 'Görev eklendi.', 'data' => $gorev]);
                } else {
                    throw new Exception("Görev eklenemedi.");
                }
                break;

            case 'update-gorev':
                $id = Security::decrypt($_POST['gorev_id']);
                $gorevDuzenleyebilir($id);
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
                $gorevDuzenleyebilir($id);
                $result = $Gorev->deleteGorev($id);

                if ($result) {
                    $logla('Görev Silme', "Görev #$id silindi.");
                    echo json_encode(['success' => true, 'message' => 'Görev silindi.']);
                } else {
                    throw new Exception("Görev silinemedi.");
                }
                break;

            case 'tamamla':
                $id = Security::decrypt($_POST['gorev_id']);
                $gorevDuzenleyebilir($id);
                $result = $Gorev->tamamla($id);

                if ($result) {
                    $logla('Görev Tamamlama', "Görev #$id tamamlandı.");
                    echo json_encode(['success' => true, 'message' => 'Görev tamamlandı.']);
                } else {
                    throw new Exception("İşlem başarısız.");
                }
                break;

            case 'geri-al':
                $id = Security::decrypt($_POST['gorev_id']);
                $gorevDuzenleyebilir($id);
                $result = $Gorev->tamamlamayiGeriAl($id);

                if ($result) {
                    $logla('Görev Geri Alma', "Görev #$id tamamlanma durumu geri alındı.");
                    echo json_encode(['success' => true, 'message' => 'Görev geri alındı.']);
                } else {
                    throw new Exception("İşlem başarısız.");
                }
                break;

            case 'update-sira':
                $gorevler = json_decode($_POST['gorevler'], true);
                $decrypted = [];
                foreach ((array) $gorevler as $g) {
                    $gId = Security::decrypt($g['id']);
                    $hedefListeId = Security::decrypt($g['liste_id']);
                    if (!in_array($Gorev->gorevYetkisi($gId, $userId, $firmaId), ['sahip', 'duzenle'], true)
                        || !in_array($Gorev->listeYetkisi($hedefListeId, $userId, $firmaId), ['sahip', 'duzenle'], true)) {
                        continue;
                    }
                    $decrypted[] = [
                        'id' => $gId,
                        'sira' => $g['sira'],
                        'liste_id' => $hedefListeId
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
                        $pushService->sendToUser($targetId, $payload, false, \App\Model\UserNotificationPreferenceModel::TYPE_TASK);

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

            case 'save-subscription':
                $subscription = json_decode($_POST['subscription'] ?? '{}', true);
                if (empty($subscription) || empty($subscription['endpoint'])) {
                    throw new Exception('Geçersiz abonelik verisi');
                }
                $PushSubscriptionModel = new \App\Model\PushSubscriptionModel();
                $result = $PushSubscriptionModel->saveUserSubscription(
                    $userId,
                    $subscription['endpoint'],
                    $subscription['keys']['p256dh'] ?? null,
                    $subscription['keys']['auth'] ?? null
                );

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Bildirim aboneliği başarıyla kaydedildi']);
                } else {
                    throw new Exception('Abonelik kaydedilirken bir hata oluştu');
                }
                break;

            case 'check-subscription-status':
                $PushSubscriptionModel = new \App\Model\PushSubscriptionModel();
                $isSubscribed = $PushSubscriptionModel->checkUserSubscription($userId);
                echo json_encode(['success' => true, 'subscribed' => $isSubscribed]);
                break;

            case 'remove-subscription':
                $PushSubscriptionModel = new \App\Model\PushSubscriptionModel();
                $result = $PushSubscriptionModel->deleteByUser($userId);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Bildirim aboneliği kaldırıldı']);
                } else {
                    throw new Exception('Abonelik kaldırılırken bir hata oluştu');
                }
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
