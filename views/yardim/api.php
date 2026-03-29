<?php
/**
 * Yardım ve Destek Sistemi (Ticket) API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DestekBiletModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Helper\Security;
use App\Service\MailGonderService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

// Oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$destekBiletModel = new DestekBiletModel();

try {
    switch ($action) {
        // Yeni bilet oluştur (PWA)
        case 'create-ticket':
            $personelId = (int) ($_SESSION['personel_id'] ?? 0);
            if (!$personelId && isset($_SESSION['user_id'])) {
                $personelId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
            }
            if (!$personelId) throw new Exception('Talebi oluşturacak personel kaydı bulunamadı.');

            $konu = trim($_POST['konu'] ?? '');
            $kategori = trim($_POST['kategori'] ?? 'Genel');
            $oncelik = trim($_POST['oncelik'] ?? 'orta');
            $mesaj = trim($_POST['mesaj'] ?? '');

            if (empty($konu) || empty($mesaj)) throw new Exception('Konu ve mesaj gereklidir');

            $dosyaYolu = null;
            if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
                $dosyaYolu = handleFileUpload($_FILES['dosya']);
            }

            $canBypassApproval = isSupportAdminViewer() || Gate::allows('destek_talebi_onaylama');
            $onayDurumu = $canBypassApproval ? 'onaylandi' : 'beklemede';

            $biletId = $destekBiletModel->createTicket($personelId, $konu, $kategori, $oncelik, $mesaj, $dosyaYolu, $onayDurumu);

            // Onaylı taleplerde adminlere, bekleyen taleplerde onaylayıcılara bildirim gönder
            try {
                $ticket = $destekBiletModel->getTicketDetails($biletId);
                if ($ticket) {
                    if ($onayDurumu === 'onaylandi') {
                        notifyAdminsForNewSupportTicket($ticket, $mesaj);
                    } else {
                        notifyApproversForSupportTicket($ticket, $mesaj);
                    }
                }
            } catch (Exception $e) {
                error_log('Destek talebi admin bildirim/mail hatası: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => $onayDurumu === 'beklemede'
                    ? 'Destek talebiniz yönetici onayına gönderildi. Onay sonrası yönetici listesinde görünecektir.'
                    : 'Destek talebiniz başarıyla oluşturuldu.',
                'bilet_id' => $biletId,
                'requires_approval' => $onayDurumu === 'beklemede'
            ]);
            break;

        // Biletleri listele (PWA)
        case 'get-tickets-pwa':
            $personelId = (int) ($_SESSION['personel_id'] ?? 0);
            if (!$personelId && isset($_SESSION['user_id'])) {
                $personelId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
            }

            $isAdminSupport = isSupportAdminViewer();
            $isApproverOnly = Gate::allows('destek_talebi_onaylama') && !$isAdminSupport;

            $ownTickets = $personelId > 0 ? ($destekBiletModel->getPersonelTickets($personelId) ?: []) : [];
            
            // For approvers: get both pending (beklemede) and approved (onaylandi) tickets
            $approvalTickets = [];
            if ($isApproverOnly) {
                $pendingTickets = $destekBiletModel->getAllTickets(null, 'beklemede') ?: [];
                $approvedTickets = $destekBiletModel->getAllTickets(null, 'onaylandi') ?: [];
                
                // Filter approved to only include those approved by this user
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                $approvedTickets = array_filter($approvedTickets, function($ticket) use ($userId) {
                    return (int) ($ticket->onaylayan_user_id ?? 0) === $userId;
                });
                
                $approvalTickets = array_merge($pendingTickets, $approvedTickets);
            }

            $merged = [];
            foreach ($ownTickets as $ticket) {
                $ticket->list_context = 'kendi';
                $merged[(int) $ticket->id] = $ticket;
            }

            foreach ($approvalTickets as $ticket) {
                $id = (int) $ticket->id;
                if (isset($merged[$id])) {
                    $merged[$id]->list_context = 'kendi';
                    continue;
                }

                // Determine context: if pending, it's 'onay'; if approved, it's 'onay_tamamlandi'
                if ((string) ($ticket->onay_durumu ?? '') === 'beklemede') {
                    $ticket->list_context = ((int) ($ticket->personel_id ?? 0) === (int) $personelId) ? 'kendi' : 'onay';
                } else {
                    $ticket->list_context = 'onay_tamamlandi';
                }
                $merged[$id] = $ticket;
            }

            $tickets = array_values($merged);
            usort($tickets, function ($a, $b) {
                $aTime = strtotime((string) ($a->guncelleme_tarihi ?? '1970-01-01 00:00:00'));
                $bTime = strtotime((string) ($b->guncelleme_tarihi ?? '1970-01-01 00:00:00'));
                return $bTime <=> $aTime;
            });

            $ownTicketCount = 0;
            $personnelTicketCount = 0;
            foreach ($tickets as $ticket) {
                if (($ticket->list_context ?? '') === 'kendi') {
                    $ownTicketCount++;
                } else {
                    $personnelTicketCount++;
                }
            }

            $stats = $destekBiletModel->getStats($personelId) ?: (object) ['toplam' => 0, 'bekleyen' => 0, 'yanitlanan' => 0, 'kapali' => 0];
            $approvalStats = $isApproverOnly ? ($destekBiletModel->getStats(null, 'beklemede') ?: null) : null;

            echo json_encode([
                'success' => true,
                'tickets' => $tickets,
                'stats' => $stats,
                'is_approver' => $isApproverOnly,
                'approval_pending_count' => (int) ($approvalStats->toplam ?? 0),
                'own_tickets_count' => $ownTicketCount,
                'personnel_tickets_count' => $personnelTicketCount
            ]);
            break;

        // Biletleri listele (Admin)
        case 'get-tickets-admin':
            $status = $_POST['status'] ?? null;
            $isAdminSupport = isSupportAdminViewer();
            $isApprover = Gate::allows('destek_talebi_onaylama');
            $approvalFilter = 'onaylandi';

            if (!$isAdminSupport && $isApprover) {
                $approvalFilter = 'beklemede';
            }

            $tickets = $destekBiletModel->getAllTickets($status, $approvalFilter);
            $stats = $destekBiletModel->getStats(null, $approvalFilter);

            echo json_encode([
                'success' => true,
                'tickets' => $tickets,
                'stats' => $stats
            ]);
            break;

        // Bilet detaylarını getir (Ortak)
        case 'get-ticket-details':
            $biletId = (int) ($_POST['bilet_id'] ?? 0);
            if (!$biletId) throw new Exception('Bilet ID gereklidir');

            $ticket = $destekBiletModel->getTicketDetails($biletId);
            if (!$ticket) throw new Exception('Bilet bulunamadı');

            // Güvenlik: Süper admin/admin destek/onaylayıcı harici kullanıcılar yalnızca kendi bileti görebilir
            $isAdminViewer = isSupportAdminViewer();
            $isApproverViewer = Gate::allows('destek_talebi_onaylama');
            $isPrivilegedViewer = Gate::isSuperAdmin() || $isAdminViewer || $isApproverViewer;
            if (!$isPrivilegedViewer) {
                if (isset($_SESSION['personel_id']) && $ticket->personel_id != $_SESSION['personel_id']) {
                    throw new Exception('Yetkisiz erişim');
                }
                if (!isset($_SESSION['personel_id']) && isset($_SESSION['user_id'])) {
                    $pId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
                    if ($ticket->personel_id != $pId) throw new Exception('Yetkisiz erişim');
                }
            }

            $ticket->viewer_is_admin = $isAdminViewer;
            $ticket->can_reply = $isAdminViewer ? (($ticket->durum ?? '') !== 'kapali') : $destekBiletModel->canRequesterReply($biletId);
            $ticket->can_approve = (!$isAdminViewer) && $isApproverViewer && (($ticket->onay_durumu ?? 'onaylandi') === 'beklemede');

            echo json_encode([
                'success' => true,
                'ticket' => $ticket
            ]);
            break;

        // Yeni mesaj ekle (Ortak)
        case 'add-message':
            $biletId = (int) ($_POST['bilet_id'] ?? 0);
            $mesaj = trim($_POST['mesaj'] ?? '');

            if (!$biletId || empty($mesaj)) throw new Exception('Bilet ID ve mesaj gereklidir');

            // Güvenlik: Admin olmayanlar sadece kendi bileti mesaj atabilir
            $ticket = $destekBiletModel->getTicketDetails($biletId);
            if (!$ticket) throw new Exception('Bilet bulunamadı');
            
            $isSuperAdmin = Gate::isSuperAdmin();
            if (!$isSuperAdmin) {
                if (isset($_SESSION['personel_id']) && $ticket->personel_id != $_SESSION['personel_id']) {
                    throw new Exception('Yetkisiz erişim');
                }
                if (!isset($_SESSION['personel_id']) && isset($_SESSION['user_id'])) {
                    $pId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
                    if ($ticket->personel_id != $pId) throw new Exception('Yetkisiz erişim');
                }
            }

            if (($ticket->durum ?? '') === 'kapali') {
                throw new Exception('Bu talep kapatılmıştır. Yeni mesaj gönderemezsiniz.');
            }

            if (($ticket->onay_durumu ?? 'onaylandi') !== 'onaylandi') {
                throw new Exception('Bu destek talebi henüz onaylanmadı. Onay sonrası mesajlaşabilirsiniz.');
            }

            $isAdminReply = isSupportAdminViewer();
            $gonderenTip = $isAdminReply ? 'yonetici' : 'personel';
            $gonderenId = 0;

            if ($isAdminReply) {
                $gonderenId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']->id ?? 0);
            } else {
                $gonderenId = (int) ($_SESSION['personel_id'] ?? 0);
                if ($gonderenId <= 0 && isset($_SESSION['user_id'])) {
                    $gonderenId = $destekBiletModel->getPersonelIdByUserId((int) $_SESSION['user_id']);
                }

                if ($gonderenId <= 0) {
                    throw new Exception('Mesaj gönderecek personel kaydı bulunamadı.');
                }

                if (!$destekBiletModel->canRequesterReply($biletId)) {
                    throw new Exception('Yeni mesaj göndermek için yönetici yanıtını beklemelisiniz.');
                }
            }

            $dosyaYolu = null;
            if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
                $dosyaYolu = handleFileUpload($_FILES['dosya']);
            }

            $destekBiletModel->addMessage($biletId, $gonderenTip, $gonderenId, $mesaj, $dosyaYolu);

            // Yönetici cevabında personel/kullanıcıya bildirim ve e-posta gönder
            if ($gonderenTip === 'yonetici') {
                try {
                    notifyTicketOwnerForAdminReply($ticket, $mesaj);
                } catch (Exception $e) {
                    error_log('Destek admin yanıtı bildirim/mail hatası: ' . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Mesajınız başarıyla gönderildi.'
            ]);
            break;

        case 'update-approval':
            if (!Gate::allows('destek_talebi_onaylama')) {
                throw new Exception('Bu işlemi yapmaya yetkiniz yok.');
            }

            $biletId = (int) ($_POST['bilet_id'] ?? 0);
            $onayDurumu = trim((string) ($_POST['onay_durumu'] ?? ''));
            $onayNotu = trim((string) ($_POST['onay_notu'] ?? ''));

            if (!$biletId || !in_array($onayDurumu, ['onaylandi', 'reddedildi'], true)) {
                throw new Exception('Geçersiz parametreler');
            }

            $ticket = $destekBiletModel->getTicketDetails($biletId);
            if (!$ticket) {
                throw new Exception('Bilet bulunamadı');
            }

            if (($ticket->onay_durumu ?? 'onaylandi') !== 'beklemede') {
                throw new Exception('Bu talep zaten işlem görmüş.');
            }

            $destekBiletModel->updateApprovalStatus($biletId, $onayDurumu, (int) ($_SESSION['user_id'] ?? 0), $onayNotu);

            if ($onayDurumu === 'onaylandi') {
                $firstMessage = '';
                if (!empty($ticket->messages) && isset($ticket->messages[0]->mesaj)) {
                    $firstMessage = (string) $ticket->messages[0]->mesaj;
                }
                notifyAdminsForNewSupportTicket($ticket, $firstMessage);
            }

            notifyTicketOwnerForApprovalResult($ticket, $onayDurumu, $onayNotu);

            echo json_encode([
                'success' => true,
                'message' => $onayDurumu === 'onaylandi' ? 'Talep onaylandı.' : 'Talep reddedildi.'
            ]);
            break;

        // Durum güncelle (Kapatma vb.)
        case 'update-status':
            $biletId = (int) ($_POST['bilet_id'] ?? 0);
            $durum = $_POST['durum'] ?? '';

            if (!$biletId || !in_array($durum, ['acik', 'yanitlandi', 'personel_yaniti', 'kapali'])) {
                throw new Exception('Geçersiz parametreler');
            }

            // Güvenlik check
            $ticket = $destekBiletModel->getTicketDetails($biletId);
            if (!$ticket) throw new Exception('Bilet bulunamadı');
            
            $isSuperAdmin = Gate::isSuperAdmin();
            if (!$isSuperAdmin) {
                if (isset($_SESSION['personel_id']) && $ticket->personel_id != $_SESSION['personel_id']) {
                    throw new Exception('Yetkisiz erişim');
                }
                if (!isset($_SESSION['personel_id']) && isset($_SESSION['user_id'])) {
                    $pId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
                    if ($ticket->personel_id != $pId) throw new Exception('Yetkisiz erişim');
                }
            }

            $destekBiletModel->updateStatus($biletId, $durum);
            echo json_encode(['success' => true]);
            break;

        // İstatistikleri getir
        case 'get-stats':
            $personelId = isset($_SESSION['personel_id']) ? $_SESSION['personel_id'] : null;
            if (!$personelId && isset($_SESSION['user_id'])) {
                $personelId = $destekBiletModel->getPersonelIdByUserId($_SESSION['user_id']);
            }
            $stats = $destekBiletModel->getStats($personelId);
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            throw new Exception('Geçersiz işlem: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Dosya yükleme yardımcısı
 */
function handleFileUpload($file) {
    $uploadDir = dirname(__DIR__, 2) . '/uploads/yardim/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) throw new Exception('Sadece resim formatları desteklenmektedir.');
    if ($file['size'] > 5 * 1024 * 1024) throw new Exception('Maksimum dosya boyutu 5MB\'dır.');

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'bilet_' . uniqid() . '_' . time() . '.' . $ext;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        throw new Exception('Dosya kaydedilemedi.');
    }

    return 'uploads/yardim/' . $fileName;
}

function getUsersByPermissionName(string $permissionName): array
{
    $userModel = new UserModel();
    $db = $userModel->getDb();

    $sql = "SELECT DISTINCT u.id, u.adi_soyadi, u.email_adresi
            FROM users u
            INNER JOIN user_role_permissions urp ON FIND_IN_SET(urp.role_id, REPLACE(u.roles, ' ', ''))
            INNER JOIN permissions p ON p.id = urp.permission_id
            WHERE (p.auth_name = :permission OR p.name = :permission)";

    $params = [':permission' => $permissionName];

    if (!empty($_SESSION['owner_id'])) {
        $sql .= " AND u.owner_id = :owner_id";
        $params[':owner_id'] = (int) $_SESSION['owner_id'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
}

function getUsersByPersonelId(int $personelId): array
{
    $userModel = new UserModel();
    $db = $userModel->getDb();

    $sql = "SELECT id, adi_soyadi, email_adresi, email FROM users WHERE personel_id = :personel_id";
    $params = [':personel_id' => $personelId];

    if (!empty($_SESSION['owner_id'])) {
        $sql .= " AND owner_id = :owner_id";
        $params[':owner_id'] = (int) $_SESSION['owner_id'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
}

function extractUserEmail($user): string
{
    return trim((string) ($user->email_adresi ?? ''));
}

function buildTicketAdminLink(int $ticketId): string
{
    $encryptedId = Security::encrypt($ticketId);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        return $scheme . '://' . $host . '/index?p=yardim/view&id=' . urlencode($encryptedId);
    }
    return 'index?p=yardim/view&id=' . urlencode($encryptedId);
}

function buildTicketRoute(int $ticketId): string
{
    return 'index?p=yardim/view&id=' . urlencode(Security::encrypt($ticketId));
}

function isSupportAdminViewer(): bool
{
    return Gate::isSuperAdmin() || Gate::allows('admin_destek_talebi');
}

function notifyAdminsForNewSupportTicket($ticket, string $ilkMesaj): void
{
    $admins = getUsersByPermissionName('admin_destek_talebi');
    if (empty($admins)) {
        return;
    }

    $bildirimModel = new BildirimModel();
    $personelModel = new PersonelModel();
    $personel = $personelModel->find((int) $ticket->personel_id);

    $personelAdi = $personel->adi_soyadi ?? ($ticket->personel_adi ?? 'Personel');
    $ticketLink = buildTicketAdminLink((int) $ticket->id);
    $subject = 'Yeni Destek Talebi - #' . ($ticket->ref_no ?? $ticket->id);
    $snippet = mb_substr(trim($ilkMesaj), 0, 240);

    $emails = [];
    foreach ($admins as $admin) {
        $bildirimModel->createNotification(
            (int) $admin->id,
            'Yeni Destek Talebi',
            $personelAdi . ' tarafından yeni destek talebi açıldı. Konu: ' . ($ticket->konu ?? '-'),
            buildTicketRoute((int) $ticket->id),
            'message-square',
            'warning'
        );

        $email = extractUserEmail($admin);
        if ($email !== '') {
            $emails[] = strtolower($email);
        }
    }

    $emails = array_values(array_unique($emails));
    if (empty($emails)) {
        return;
    }

    $mailContent = "
        <h3>Yeni Destek Talebi</h3>
        <p><strong>Talep No:</strong> #" . htmlspecialchars((string) ($ticket->ref_no ?? $ticket->id), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Talep Eden:</strong> " . htmlspecialchars((string) $personelAdi, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Konu:</strong> " . htmlspecialchars((string) ($ticket->konu ?? '-'), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Mesaj:</strong><br>" . nl2br(htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8')) . "</p>
        <p><a href=\"" . htmlspecialchars($ticketLink, ENT_QUOTES, 'UTF-8') . "\">Talebi Görüntüle</a></p>
    ";

    MailGonderService::gonder($emails, $subject, $mailContent);
}

function notifyApproversForSupportTicket($ticket, string $ilkMesaj): void
{
    $approvers = getUsersByPermissionName('destek_talebi_onaylama');
    if (empty($approvers)) {
        return;
    }

    $bildirimModel = new BildirimModel();
    $personelModel = new PersonelModel();
    $personel = $personelModel->find((int) $ticket->personel_id);

    $personelAdi = $personel->adi_soyadi ?? ($ticket->personel_adi ?? 'Personel');
    $ticketLink = buildTicketAdminLink((int) $ticket->id);
    $subject = 'Destek Talebi Onay Bekliyor - #' . ($ticket->ref_no ?? $ticket->id);
    $snippet = mb_substr(trim($ilkMesaj), 0, 240);

    $emails = [];
    foreach ($approvers as $approver) {
        $bildirimModel->createNotification(
            (int) $approver->id,
            'Destek Talebi Onay Bekliyor',
            $personelAdi . ' tarafından açılan destek talebi onay bekliyor. Konu: ' . ($ticket->konu ?? '-'),
            buildTicketRoute((int) $ticket->id),
            'check-circle',
            'warning'
        );

        $email = extractUserEmail($approver);
        if ($email !== '') {
            $emails[] = strtolower($email);
        }
    }

    $emails = array_values(array_unique($emails));
    if (empty($emails)) {
        return;
    }

    $mailContent = "
        <h3>Onay Bekleyen Destek Talebi</h3>
        <p><strong>Talep No:</strong> #" . htmlspecialchars((string) ($ticket->ref_no ?? $ticket->id), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Talep Eden:</strong> " . htmlspecialchars((string) $personelAdi, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Konu:</strong> " . htmlspecialchars((string) ($ticket->konu ?? '-'), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Mesaj:</strong><br>" . nl2br(htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8')) . "</p>
        <p><a href=\"" . htmlspecialchars($ticketLink, ENT_QUOTES, 'UTF-8') . "\">Talebi İncele</a></p>
    ";

    MailGonderService::gonder($emails, $subject, $mailContent);
}

function notifyTicketOwnerForAdminReply($ticket, string $mesaj): void
{
    $ticketId = (int) $ticket->id;
    $personelId = (int) ($ticket->personel_id ?? 0);
    if ($personelId <= 0) {
        return;
    }

    $linkedUsers = getUsersByPersonelId($personelId);
    $bildirimModel = new BildirimModel();

    foreach ($linkedUsers as $user) {
        $bildirimModel->createNotification(
            (int) $user->id,
            'Destek Talebinize Yanıt Geldi',
            'Talep #' . ($ticket->ref_no ?? $ticketId) . ' için yönetici tarafından yanıt verildi.',
            buildTicketRoute($ticketId),
            'message-square',
            'info'
        );
    }

    $emails = [];
    foreach ($linkedUsers as $user) {
        $email = extractUserEmail($user);
        if ($email !== '') {
            $emails[] = strtolower($email);
        }
    }

    $personelModel = new PersonelModel();
    $personel = $personelModel->find($personelId);
    $personelEmail = trim((string) ($personel->email_adresi ?? ''));
    if ($personelEmail !== '') {
        $emails[] = strtolower($personelEmail);
    }

    $emails = array_values(array_unique($emails));
    if (empty($emails)) {
        return;
    }

    $snippet = mb_substr(trim($mesaj), 0, 300);
    $subject = 'Destek Talebinize Yanıt Geldi - #' . ($ticket->ref_no ?? $ticketId);
    $ticketLink = buildTicketAdminLink($ticketId);
    $mailContent = "
        <h3>Destek Talebinize Yeni Yanıt</h3>
        <p><strong>Talep No:</strong> #" . htmlspecialchars((string) ($ticket->ref_no ?? $ticketId), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Konu:</strong> " . htmlspecialchars((string) ($ticket->konu ?? '-'), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Yanıt:</strong><br>" . nl2br(htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8')) . "</p>
        <p>Detayları görmek için sisteme giriş yapıp destek talebinizi açabilirsiniz.</p>
        <p><a href=\"" . htmlspecialchars($ticketLink, ENT_QUOTES, 'UTF-8') . "\">Talebi Aç</a></p>
    ";

    MailGonderService::gonder($emails, $subject, $mailContent);
}

function notifyTicketOwnerForApprovalResult($ticket, string $onayDurumu, string $onayNotu = ''): void
{
    $ticketId = (int) $ticket->id;
    $personelId = (int) ($ticket->personel_id ?? 0);
    if ($personelId <= 0) {
        return;
    }

    $linkedUsers = getUsersByPersonelId($personelId);
    $bildirimModel = new BildirimModel();

    $isApproved = ($onayDurumu === 'onaylandi');
    $title = $isApproved ? 'Destek Talebiniz Onaylandı' : 'Destek Talebiniz Reddedildi';
    $statusText = $isApproved ? 'onaylandı' : 'reddedildi';
    $description = 'Talep #' . ($ticket->ref_no ?? $ticketId) . ' ' . $statusText . '.';
    if ($onayNotu !== '') {
        $description .= ' Not: ' . $onayNotu;
    }

    foreach ($linkedUsers as $user) {
        $bildirimModel->createNotification(
            (int) $user->id,
            $title,
            $description,
            buildTicketRoute($ticketId),
            $isApproved ? 'check-circle' : 'x-circle',
            $isApproved ? 'success' : 'danger'
        );
    }

    $emails = [];
    foreach ($linkedUsers as $user) {
        $email = extractUserEmail($user);
        if ($email !== '') {
            $emails[] = strtolower($email);
        }
    }

    $personelModel = new PersonelModel();
    $personel = $personelModel->find($personelId);
    $personelEmail = trim((string) ($personel->email_adresi ?? ''));
    if ($personelEmail !== '') {
        $emails[] = strtolower($personelEmail);
    }

    $emails = array_values(array_unique($emails));
    if (empty($emails)) {
        return;
    }

    $subject = $title . ' - #' . ($ticket->ref_no ?? $ticketId);
    $ticketLink = buildTicketAdminLink($ticketId);
    $mailContent = "
        <h3>Destek Talebi Durumu Güncellendi</h3>
        <p><strong>Talep No:</strong> #" . htmlspecialchars((string) ($ticket->ref_no ?? $ticketId), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Konu:</strong> " . htmlspecialchars((string) ($ticket->konu ?? '-'), ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Durum:</strong> " . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . "</p>" .
        ($onayNotu !== ''
            ? "<p><strong>Not:</strong> " . nl2br(htmlspecialchars($onayNotu, ENT_QUOTES, 'UTF-8')) . "</p>"
            : '') .
        "<p><a href=\"" . htmlspecialchars($ticketLink, ENT_QUOTES, 'UTF-8') . "\">Talebi Aç</a></p>
    ";

    MailGonderService::gonder($emails, $subject, $mailContent);
}
