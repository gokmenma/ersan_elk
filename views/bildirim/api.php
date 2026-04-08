<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\PushNotificationService;
use App\Model\PushSubscriptionModel;
use App\Model\PersonelModel;
use App\Model\MesajLogModel;
use App\Model\BildirimModel;
use App\Helper\Helper;

header('Content-Type: application/json; charset=utf-8');

// Hataları ekrana basma, JSON yapısını bozar
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $pushService = new PushNotificationService();
    $subscriptionModel = new PushSubscriptionModel();
    $personelModel = new PersonelModel();
    $mesajLogModel = new MesajLogModel();

    try {
        switch ($action) {
            case 'send-notification':
                $alici_tipi = $_POST['alici_tipi'] ?? 'tekli';
                $baslik = trim($_POST['baslik'] ?? '');
                $mesaj = trim($_POST['mesaj'] ?? '');
                $hedef_sayfa = $_POST['hedef_sayfa'] ?? '';

                if (empty($baslik) || empty($mesaj)) {
                    throw new Exception('Başlık ve mesaj zorunludur.');
                }

                // DEBUG: Dosya bilgilerini logla
                $debugLog = dirname(__DIR__, 2) . '/debug_upload.txt';
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);

                // Resim Yükleme İşlemi
                $imageUrl = null;
                if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR;

                    // Klasör yoksa oluştur
                    if (!is_dir($uploadDir)) {
                        if (!@mkdir($uploadDir, 0755, true)) {
                            throw new Exception('Yükleme klasörü oluşturulamadı: ' . $uploadDir);
                        }
                    }

                    // Klasör yazılabilir mi kontrol et
                    if (!is_writable($uploadDir)) {
                        throw new Exception('Yükleme klasörü yazılabilir değil.');
                    }

                    $fileInfo = pathinfo($_FILES['resim']['name']);
                    $extension = strtolower($fileInfo['extension'] ?? '');
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (empty($extension) || !in_array($extension, $allowedExtensions)) {
                        throw new Exception('Geçersiz dosya formatı. Sadece resim dosyaları (jpg, jpeg, png, gif, webp) yüklenebilir.');
                    }

                    // MIME type kontrolü
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['resim']['tmp_name']);
                    finfo_close($finfo);

                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mimeType, $allowedMimes)) {
                        throw new Exception('Geçersiz dosya tipi: ' . $mimeType);
                    }

                    if ($_FILES['resim']['size'] > 2 * 1024 * 1024) { // 2MB
                        throw new Exception('Dosya boyutu 2MB\'dan büyük olamaz.');
                    }

                    $fileName = uniqid('push_') . '.' . $extension;
                    $uploadFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['resim']['tmp_name'], $uploadFile)) {
                        // URL oluştur - mutlak URL gerekli
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];

                        // Base path'i DOCUMENT_ROOT'tan hesapla
                        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        $uploadPath = str_replace('\\', '/', dirname(__DIR__, 2) . '/uploads/notifications/');
                        $relativePath = str_replace($docRoot, '', $uploadPath);
                        $relativePath = '/' . ltrim($relativePath, '/');

                        $imageUrl = "{$protocol}://{$host}{$relativePath}{$fileName}";
                    } else {
                        throw new Exception('Dosya yüklenirken bir hata oluştu. Hata kodu: ' . $_FILES['resim']['error']);
                    }
                } elseif (isset($_FILES['resim']) && $_FILES['resim']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Dosya seçilmiş ama hata var
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'Dosya boyutu PHP ini limitini aşıyor.',
                        UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
                        UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                        UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
                        UPLOAD_ERR_EXTENSION => 'PHP eklentisi dosya yüklemesini durdurdu.',
                    ];
                    $errorMsg = $uploadErrors[$_FILES['resim']['error']] ?? 'Bilinmeyen hata.';
                    throw new Exception('Resim yükleme hatası: ' . $errorMsg);
                }

                $payload = [
                    'title' => $baslik,
                    'body' => $mesaj,
                    'url' => $hedef_sayfa ?: 'index.php'
                ];

                if ($imageUrl) {
                    $payload['image'] = $imageUrl;  // Android Chrome'da büyük resim olarak görünür
                    // icon'u payload'a eklemeyin - Service Worker varsayılan logoyu kullanacak
                }

                // DEBUG: Payload'ı logla
                $debugLog = dirname(__DIR__, 2) . '/debug_upload.txt';
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " - PAYLOAD: " . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

                $gonderildi = 0;
                $hata = 0;

                if ($alici_tipi === 'toplu') {
                    // Tüm abonelere gönder
                    $db = $subscriptionModel->getDb();
                    $stmt = $db->query("SELECT DISTINCT personel_id FROM push_subscriptions");
                    $personelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($personelIds as $pid) {
                        try {
                            if ($pushService->sendToPersonel($pid, $payload)) {
                                $gonderildi++;
                            } else {
                                $hata++;
                            }
                        } catch (Exception $e) {
                            $hata++;
                        }
                    }
                } else {
                    // Seçili personellere gönder
                    $personelIds = $_POST['personel_ids'] ?? [];

                    if (empty($personelIds)) {
                        throw new Exception('Lütfen en az bir personel seçin.');
                    }

                    foreach ($personelIds as $pid) {
                        try {
                            if ($pushService->sendToPersonel($pid, $payload)) {
                                $gonderildi++;
                            } else {
                                $hata++;
                            }
                        } catch (Exception $e) {
                            $hata++;
                        }
                    }
                }

                $recipientNames = [];
                if ($alici_tipi === 'toplu') {
                    $recipientNames[] = "Tüm Aboneler (" . count($personelIds) . " kişi)";
                } else {
                    foreach ($personelIds as $pid) {
                        $p = $personelModel->find($pid);
                        if ($p) {
                            $recipientNames[] = $p->adi_soyadi;
                        }
                    }
                }

                $logStatus = ($hata == 0) ? 'success' : (($gonderildi > 0) ? 'partial' : 'failed');
                $mesajLogModel->logPush(
                    $_SESSION['firma_id'] ?? 0,
                    $baslik,
                    $mesaj,
                    $recipientNames,
                    $payload,
                    $logStatus
                );

                $message = "Bildirim gönderildi. Başarılı: $gonderildi";
                if ($hata > 0) {
                    $message .= ", Hatalı: $hata";
                }



                $response = [
                    'status' => 'success',
                    'message' => $message,
                    'gonderildi' => $gonderildi,
                    'hata' => $hata
                ];

                // Debug için image URL'i de ekle
                if ($imageUrl) {
                    $response['debug_image_url'] = $imageUrl;
                }

                echo json_encode($response);
                break;

            case 'test-notification':
                // Mevcut kullanıcının personel_id'sini bul
                $user_id = $_SESSION['user_id'] ?? 0;

                // Test için örnek payload
                $payload = [
                    'title' => '🔔 Test Bildirimi',
                    'body' => 'Bu bir test bildirimidir. Bildirimler düzgün çalışıyor!',
                    'url' => 'index.php'
                ];

                // Eğer kullanıcının kendisi bir personelse ve aboneliği varsa
                // Abone olan herhangi birine gönder
                $db = $subscriptionModel->getDb();
                $stmt = $db->query("SELECT DISTINCT personel_id FROM push_subscriptions LIMIT 1");
                $testPersonelId = $stmt->fetchColumn();

                if (!$testPersonelId) {
                    throw new Exception('Henüz hiç bildirim aboneliği yok. Önce PWA uygulamasında bildirim iznini verin.');
                }

                if ($pushService->sendToPersonel($testPersonelId, $payload)) {
                    $mesajLogModel->logPush(
                        $_SESSION['firma_id'] ?? 0,
                        $payload['title'],
                        $payload['body'],
                        ['Test Kullanıcısı'],
                        $payload,
                        'success'
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Test bildirimi gönderildi!'
                    ]);
                } else {
                    $mesajLogModel->logPush(
                        $_SESSION['firma_id'] ?? 0,
                        $payload['title'],
                        $payload['body'],
                        ['Test Kullanıcısı'],
                        $payload,
                        'success'
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Test bildirimi gönderildi!'
                    ]);
                }
                break;

            // ===== In-App Notifications =====
            case 'get-unread':
                $userId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
                if ($userId <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                $BildirimModel = new BildirimModel();
                $notifications = $BildirimModel->getUnreadNotifications($userId);
                
                $formattedNormal = [];
                $formattedSupport = [];
                $normalCount = 0;
                $supportCount = 0;

                foreach ($notifications as $n) {
                    // Timezone-aware time_ago hesaplama
                    $timeAgo = 'şimdi';
                    if (!empty($n->created_at)) {
                        try {
                            $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
                            $created = new DateTime($n->created_at, new DateTimeZone('Europe/Istanbul'));
                            $diff = $now->diff($created);

                            if ($diff->y > 0) {
                                $timeAgo = $diff->y . ' yıl önce';
                            } elseif ($diff->m > 0) {
                                $timeAgo = $diff->m . ' ay önce';
                            } elseif ($diff->d > 0) {
                                $timeAgo = $diff->d . ' gün önce';
                            } elseif ($diff->h > 0) {
                                $timeAgo = $diff->h . ' saat önce';
                            } elseif ($diff->i > 0) {
                                $timeAgo = $diff->i . ' dakika önce';
                            } else {
                                $timeAgo = 'şimdi';
                            }
                        } catch (Exception $e) {
                            $timeAgo = date('H:i', strtotime($n->created_at));
                        }
                    }

                    $item = [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message ?? '',
                        'link' => $n->link ?? '#',
                        'icon' => $n->icon ?? 'bell',
                        'color' => $n->color ?? 'primary',
                        'time_ago' => $timeAgo
                    ];

                    // Destek talebi bildirimlerini ayır (p=yardim içeren linkler)
                    if (stripos($item['link'], 'p=yardim') !== false) {
                        $formattedSupport[] = $item;
                        $supportCount++;
                    } else {
                        $formattedNormal[] = $item;
                        $normalCount++;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'count' => $normalCount,
                    'support_count' => $supportCount,
                    'notifications' => $formattedNormal,
                    'support_notifications' => $formattedSupport
                ]);
                break;

            case 'mark-read':
                $userId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
                $id = $_POST['id'] ?? 0;

                if ($userId <= 0 || $id <= 0) {
                    throw new Exception('Geçersiz parametre.');
                }

                $BildirimModel = new BildirimModel();
                $BildirimModel->markAsRead($id, $userId);

                echo json_encode(['status' => 'success']);
                break;

            case 'mark-all-read':
                $userId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));

                if ($userId <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                $BildirimModel = new BildirimModel();
                $BildirimModel->markAllAsRead($userId);

                echo json_encode(['status' => 'success']);
                break;

            case 'datatable-list':
                $userId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
                if ($userId <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                $BildirimModel = new BildirimModel();
                $db = $BildirimModel->getDb();

                $draw = (int) ($_POST['draw'] ?? 1);
                $start = max(0, (int) ($_POST['start'] ?? 0));
                $length = (int) ($_POST['length'] ?? 20);
                if ($length < 1) {
                    $length = 20;
                }

                $searchValue = trim((string) ($_POST['search']['value'] ?? ''));
                $filter = trim((string) ($_POST['filter'] ?? 'all'));
                $category = trim((string) ($_POST['cat'] ?? ''));
                $columnSearchDate = trim((string) ($_POST['columns'][0]['search']['value'] ?? ''));
                $columnSearchType = trim((string) ($_POST['columns'][1]['search']['value'] ?? ''));
                $columnSearchContent = trim((string) ($_POST['columns'][2]['search']['value'] ?? ''));

                $columnMap = [
                    0 => 'created_at',
                    1 => 'title',
                    2 => 'message',
                    3 => 'id'
                ];
                $orderIndex = (int) ($_POST['order'][0]['column'] ?? 0);
                $orderDir = strtolower((string) ($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
                $orderColumn = $columnMap[$orderIndex] ?? 'created_at';

                $where = ['user_id = :user_id'];
                $params = [':user_id' => $userId];

                if ($filter === 'unread') {
                    $where[] = 'is_read = 0';
                }

                if ($category !== '') {
                    $where[] = '(title LIKE :category OR message LIKE :category OR link LIKE :category)';
                    $params[':category'] = '%' . $category . '%';
                }

                if ($searchValue !== '') {
                    $where[] = '(title LIKE :search OR message LIKE :search OR link LIKE :search OR DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") LIKE :search)';
                    $params[':search'] = '%' . $searchValue . '%';
                }

                if ($columnSearchDate !== '') {
                    $where[] = 'DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") LIKE :col_date';
                    $params[':col_date'] = '%' . $columnSearchDate . '%';
                }

                if ($columnSearchType !== '') {
                    $where[] = '(title LIKE :col_type OR message LIKE :col_type OR link LIKE :col_type)';
                    $params[':col_type'] = '%' . $columnSearchType . '%';
                }

                if ($columnSearchContent !== '') {
                    $where[] = '(title LIKE :col_content OR message LIKE :col_content)';
                    $params[':col_content'] = '%' . $columnSearchContent . '%';
                }

                $whereSql = ' WHERE ' . implode(' AND ', $where);

                $countStmt = $db->prepare('SELECT COUNT(*) FROM bildirimler' . $whereSql);
                $countStmt->execute($params);
                $filteredCount = (int) $countStmt->fetchColumn();

                $totalStmt = $db->prepare('SELECT COUNT(*) FROM bildirimler WHERE user_id = :user_id');
                $totalStmt->execute([':user_id' => $userId]);
                $totalCount = (int) $totalStmt->fetchColumn();

                $listSql = 'SELECT id, title, message, link, is_read, created_at
                            FROM bildirimler'
                    . $whereSql
                    . " ORDER BY {$orderColumn} {$orderDir}, id DESC LIMIT :start, :length";
                $listStmt = $db->prepare($listSql);
                foreach ($params as $k => $v) {
                    $listStmt->bindValue($k, $v);
                }
                $listStmt->bindValue(':start', $start, PDO::PARAM_INT);
                $listStmt->bindValue(':length', $length, PDO::PARAM_INT);
                $listStmt->execute();
                $rows = $listStmt->fetchAll(PDO::FETCH_OBJ);

                $unreadCount = (int) $BildirimModel->getUnreadCount($userId);

                $data = [];
                foreach ($rows as $row) {
                    $title = trim((string) ($row->title ?? ''));
                    $message = trim((string) ($row->message ?? ''));
                    $link = (string) ($row->link ?? '');
                    $typeSource = $title . ' ' . $message . ' ' . $link;

                    $groupedType = 'Sistem Bildirimi';
                    $badgeClass = 'bg-secondary';
                    if (
                        stripos($typeSource, 'destek') !== false ||
                        stripos($typeSource, 'yardim') !== false ||
                        stripos($typeSource, 'yardım') !== false
                    ) {
                        $groupedType = 'Destek Talebi';
                        $badgeClass = 'bg-info';
                    } elseif (stripos($typeSource, 'avans') !== false) {
                        $groupedType = 'Avans Talebi';
                        $badgeClass = 'bg-primary';
                    } elseif (
                        stripos($typeSource, 'izin') !== false ||
                        stripos($typeSource, 'İzin') !== false ||
                        stripos($typeSource, 'Izin') !== false
                    ) {
                        $groupedType = 'İzin Talebi';
                        $badgeClass = 'bg-success';
                    } elseif (
                        stripos($typeSource, 'arıza') !== false ||
                        stripos($typeSource, 'ariza') !== false
                    ) {
                        $groupedType = 'Arıza Talebi';
                        $badgeClass = 'bg-warning text-dark';
                    }

                    $safeTitle = htmlspecialchars($title !== '' ? $title : 'Bildirim', ENT_QUOTES, 'UTF-8');
                    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
                    $safeLink = htmlspecialchars((string) ($row->link ?? '#'), ENT_QUOTES, 'UTF-8');
                    $safeGroupedType = htmlspecialchars($groupedType, ENT_QUOTES, 'UTF-8');

                    $contentHtml = '<div class="d-flex flex-column">'
                        . '<div class="fw-semibold mb-1">' . $safeTitle . '</div>'
                        . '<div class="text-muted">' . $safeMessage . '</div>'
                        . '</div>';

                    $actionsHtml = '<div class="d-flex align-items-center gap-1">';
                    if ((int) $row->is_read === 0) {
                        $actionsHtml .= '<button type="button" class="btn btn-soft-success btn-sm mark-as-read-btn" data-id="' . (int) $row->id . '" title="Okundu işaretle"><i class="bx bx-check"></i></button>';
                    }
                    if (!empty($row->link)) {
                        $actionsHtml .= '<a class="btn btn-soft-primary btn-sm mark-read-and-go" data-id="' . (int) $row->id . '" href="' . $safeLink . '" title="Detaya git"><i class="bx bx-link-external"></i></a>';
                    }
                    $actionsHtml .= '</div>';

                    $data[] = [
                        'tarih' => !empty($row->created_at) ? date('d.m.Y H:i', strtotime($row->created_at)) : '-',
                        'turu' => '<span class="badge ' . $badgeClass . '">' . $safeGroupedType . '</span>',
                        'content' => $contentHtml,
                        'islemler' => $actionsHtml,
                        'unread' => ((int) $row->is_read === 0)
                    ];
                }

                echo json_encode([
                    'draw' => $draw,
                    'recordsTotal' => $totalCount,
                    'recordsFiltered' => $filteredCount,
                    'data' => $data,
                    'unreadCount' => $unreadCount
                ]);
                break;
            
            case 'save-subscription':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    throw new Exception('Geçersiz veri.');
                }

                $endpoint = $input['endpoint'] ?? '';
                $publicKey = $input['keys']['p256dh'] ?? '';
                $authToken = $input['keys']['auth'] ?? '';
                $contentEncoding = $input['contentEncoding'] ?? 'aes128gcm';

                if (empty($endpoint) || empty($publicKey) || empty($authToken)) {
                    throw new Exception('Eksik abonelik bilgileri.');
                }

                $personel_id = $_SESSION['id'] ?? $_SESSION['personel_id'] ?? 0;
                $user_id = $_SESSION['user_id'] ?? 0;

                if ($personel_id > 0) {
                    $subscriptionModel->saveSubscription($personel_id, $endpoint, $publicKey, $authToken, $contentEncoding);
                }
                
                if ($user_id > 0) {
                    $subscriptionModel->saveUserSubscription($user_id, $endpoint, $publicKey, $authToken, $contentEncoding);
                }

                if ($personel_id <= 0 && $user_id <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                echo json_encode(['status' => 'success']);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
