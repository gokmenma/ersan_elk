<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\MesajLogModel;
use App\Model\PermissionPolicyModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
if (!in_array($action, ['log-ajax-list', 'log-detay'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanımsız işlem.']);
    exit;
}

if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy('mail-sms/api/log', $action);
} elseif (!Gate::allows('mail-sms/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz bulunmamaktadır.']);
    exit;
}

$firmaId = (int) ($_SESSION['firma_id'] ?? $_SESSION['site_id'] ?? 0);
$MesajLog = new MesajLogModel();

try {
    if ($action === 'log-ajax-list') {
        $res = $MesajLog->ajaxList($_POST, $firmaId);
        $summary = $MesajLog->summary($firmaId, $_POST);

        $decoded = [];
        $tokens = [];
        foreach ($res['data'] as $row) {
            $list = json_decode($row->recipients ?? '', true);
            $decoded[$row->id] = $list;
            if (is_array($list)) {
                $tokens = array_merge($tokens, array_slice($list, 0, 3));
            }
        }
        $names = $MesajLog->resolveRecipientNames($tokens);

        $rows = [];
        foreach ($res['data'] as $row) {
            $recipients = $decoded[$row->id];
            if (is_array($recipients)) {
                $recipients = array_map(fn($r) => $names[$r] ?? $r, $recipients);
                $count = count($recipients);
                $recipientText = implode(', ', array_slice($recipients, 0, 3)) . ($count > 3 ? ' (+' . ($count - 3) . ')' : '');
            } else {
                $recipientText = (string) ($row->recipients ?? '');
            }

            $preview = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $row->message_preview)));
            $subject = $row->type === 'email' && trim((string) $row->subject) !== ''
                ? $row->subject
                : mb_substr($preview, 0, 80) . (mb_strlen($preview) > 80 ? '...' : '');

            $typeMap = [
                'email' => ['E-posta', 'primary', 'bx-envelope'],
                'sms' => ['SMS', 'warning', 'bx-message-detail'],
                'push' => ['Push', 'info', 'bx-bell'],
            ];
            [$typeLabel, $typeColor, $typeIcon] = $typeMap[$row->type] ?? [$row->type, 'secondary', 'bx-question-mark'];
            $ok = $row->status === 'success';
            $encId = Security::encrypt($row->id);
            $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

            $rows[] = [
                'id' => (int) $row->id,
                'type' => '<span class="badge bg-' . $typeColor . '-subtle text-' . $typeColor . ' border border-' . $typeColor . '-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx ' . $typeIcon . ' me-1"></i>' . $h($typeLabel) . '</span>',
                'sender' => $h($row->sender),
                'recipients' => $h($recipientText),
                'subject' => $h($subject),
                'status' => $ok
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Başarılı</span>'
                    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Başarısız</span>',
                'created_at' => $h(date('d.m.Y H:i', strtotime($row->created_at))),
                'actions' => '<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">'
                    . '<button type="button" class="btn btn-subtle-primary table-action-btn view-details" data-id="' . $h($encId) . '" title="Detay"><i class="bx bx-show font-size-14"></i></button>'
                    . '</div>',
            ];
        }

        echo json_encode([
            'draw' => $res['draw'],
            'recordsTotal' => $res['recordsTotal'],
            'recordsFiltered' => $res['recordsFiltered'],
            'data' => $rows,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int) Security::decrypt($_POST['id'] ?? '');
    $log = $id > 0 ? $MesajLog->getDetail($id, $firmaId) : null;
    if (!$log) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
        exit;
    }

    (new SystemLogModel())->logAction(
        (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0),
        'Mesaj Log Görüntüleme',
        "Gönderim kaydı #{$id} detayı görüntülendi.",
        SystemLogModel::LEVEL_INFO
    );

    $recipients = json_decode($log->recipients ?? '', true);
    if (!is_array($recipients)) {
        $recipients = [(string) $log->recipients];
    }
    $names = $MesajLog->resolveRecipientNames($recipients);
    $recipients = array_map(fn($r) => $names[$r] ?? $r, $recipients);
    $attachments = json_decode($log->attachments ?? '', true);
    $attachmentNames = [];
    if ($log->type === 'email' && is_array($attachments)) {
        foreach ($attachments as $att) {
            $attachmentNames[] = is_array($att) ? (string) ($att['name'] ?? $att['path'] ?? 'Dosya') : (string) $att;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'type' => $log->type,
            'sender' => $log->sender,
            'recipients' => array_values(array_map('strval', $recipients)),
            'subject' => $log->subject,
            'message' => $log->message,
            'attachments' => $attachmentNames,
            'date' => date('d.m.Y H:i', strtotime($log->created_at)),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('mail-sms/api/log: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.']);
}
