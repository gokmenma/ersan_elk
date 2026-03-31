<?php
/**
 * Sistem Logları API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

// Oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if (!Gate::allows("log_kayitlari")) {
    echo json_encode(['success' => false, 'message' => 'Bu sayfayı görme yetkiniz yok']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$systemLogModel = new SystemLogModel();

try {
    switch ($action) {
        case 'get-system-logs':
            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';

            $filters = [
                'limit' => $length,
                'offset' => $start,
                'search' => $search,
                'max_level' => 2 // Page view hariç
            ];

            $logs = $systemLogModel->getAllLogs($filters);
            $totalRecords = $systemLogModel->getLogsCount(['max_level' => 2]);
            $filteredRecords = $systemLogModel->getLogsCount($filters);

            $data = [];
            foreach ($logs as $log) {
                $logLevel = $log->level ?? 0;
                if ($logLevel >= 2) {
                    $levelBadge = '<span class="badge bg-soft-danger text-danger px-2 py-1 border border-danger" style="border-radius: 4px;"><i class="bx bx-error-circle me-1"></i>Kritik</span>';
                    $levelIcon = 'bx bx-error-circle text-muted';
                } elseif ($logLevel >= 1) {
                    $levelBadge = '<span class="badge bg-soft-warning text-warning px-2 py-1 border border-warning" style="border-radius: 4px;"><i class="bx bx-error me-1"></i>Önemli</span>';
                    $levelIcon = 'bx bx-error text-muted';
                } else {
                    $levelBadge = '<span class="badge bg-soft-info text-info px-2 py-1 border border-info" style="border-radius: 4px;"><i class="bx bx-info-circle me-1"></i>Bilgi</span>';
                    $levelIcon = 'bx bx-info-circle text-muted';
                }

                $user_name = $log->adi_soyadi ?? 'Sistem';
                $full_desc = htmlspecialchars($log->description);
                $short_desc = mb_strimwidth($full_desc, 0, 100, "...");
                
                $data[] = [
                    'level' => $levelBadge,
                    'action_type' => '<i class="'.$levelIcon.' me-1"></i> ' . htmlspecialchars($log->action_type),
                    'description' => $short_desc . " <small class='text-muted'>($user_name tarafından)</small>",
                    'date' => '<span data-sort="'.date('YmdHis', strtotime($log->created_at)).'">' . date('d.m.Y H:i', strtotime($log->created_at)) . '</span>',
                    'actions' => '<div class="text-center">
                                    <button type="button" class="btn btn-sm btn-light btn-log-detay"
                                        style="border-radius: 6px; font-weight:500; color:#475569; border: 1px solid #e2e8f0; background: #fff;"
                                        data-title="'.htmlspecialchars($log->action_type).'"
                                        data-user="'.htmlspecialchars($user_name).'"
                                        data-date="'.date('d.m.Y H:i', strtotime($log->created_at)).'"
                                        data-content="'.htmlspecialchars($log->description).'">
                                        <i class="bx bx-show me-1 text-primary"></i> Detay
                                    </button>
                                  </div>'
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $data
            ]);
            break;

        case 'get-personel-logs':
            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';

            $logs = $systemLogModel->getPersonelLoginLogs($length, $start, $search);
            $totalRecords = $systemLogModel->getPersonelLoginLogsCount();
            $filteredRecords = $systemLogModel->getPersonelLoginLogsCount($search);

            $data = [];
            foreach ($logs as $ll) {
                $avatar = mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8');
                $userHtml = '
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title rounded-circle" style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4f46e5; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                '.$avatar.'
                            </span>
                        </div>
                        <div>
                            <h5 class="font-size-14 mb-0" style="color: #334155; font-weight: 600;">
                                '.htmlspecialchars($ll->adi_soyadi ?? '').'
                            </h5>
                            <span class="badge bg-soft-info text-info font-size-11" style="border-radius: 4px;">Personel</span>
                        </div>
                    </div>';

                $dateHtml = '
                    <div class="d-flex flex-column" data-sort="'.date('YmdHis', strtotime($ll->tarih)).'">
                        <span style="color: #475569; font-weight: 500;">'.date('d.m.Y', strtotime($ll->tarih)).'</span>
                        <span style="color: #94a3b8; font-size: 0.75rem;"><i class="bx bx-time-five me-1"></i>'.date('H:i:s', strtotime($ll->tarih)).'</span>
                    </div>';

                $browserHtml = '
                    <div style="background: rgba(241,245,249,0.8); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; color: #475569; border: 1px solid #e2e8f0; display: inline-block;">
                        '.htmlspecialchars($ll->tarayici ?? '-').'
                    </div>';

                $ipHtml = '<span style="font-family: monospace; color: #64748b; font-size: 0.85rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">'.htmlspecialchars($ll->ip_adresi ?? '-').'</span>';

                $data[] = [
                    'user' => $userHtml,
                    'date' => $dateHtml,
                    'browser' => $browserHtml,
                    'ip' => $ipHtml
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $data
            ]);
            break;

        case 'get-user-logs':
            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';

            $logs = $systemLogModel->getUserLoginLogs($length, $start, $search);
            $totalRecords = $systemLogModel->getUserLoginLogsCount();
            $filteredRecords = $systemLogModel->getUserLoginLogsCount($search);

            $data = [];
            foreach ($logs as $ll) {
                $avatar = mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8');
                $userHtml = '
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title rounded-circle" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                '.$avatar.'
                            </span>
                        </div>
                        <div>
                            <h5 class="font-size-14 mb-0" style="color: #334155; font-weight: 600;">
                                '.htmlspecialchars($ll->adi_soyadi ?? '').'
                            </h5>
                            <span class="badge bg-soft-success text-success font-size-11" style="border-radius: 4px;">Kullanıcı</span>
                        </div>
                    </div>';

                $dateHtml = '
                    <div class="d-flex flex-column" data-sort="'.date('YmdHis', strtotime($ll->tarih)).'">
                        <span style="color: #475569; font-weight: 500;">'.date('d.m.Y', strtotime($ll->tarih)).'</span>
                        <span style="color: #94a3b8; font-size: 0.75rem;"><i class="bx bx-time-five me-1"></i>'.date('H:i:s', strtotime($ll->tarih)).'</span>
                    </div>';

                $ipHtml = '<span style="font-family: monospace; color: #64748b; font-size: 0.85rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">'.htmlspecialchars($ll->ip_adresi ?? '-').'</span>';

                $data[] = [
                    'user' => $userHtml,
                    'date' => $dateHtml,
                    'ip' => $ipHtml
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $data
            ]);
            break;

        case 'get-page-view-logs':
            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';

            $logs = $systemLogModel->getPageViewLogs($length, $start, $search);
            $totalRecords = $systemLogModel->getPageViewLogsCount();
            $filteredRecords = $systemLogModel->getPageViewLogsCount($search);

            $data = [];
            foreach ($logs as $log) {
                $displayName = $log->adi_soyadi;
                if (!$displayName && $log->user_id == 0) {
                    if (strpos($log->description, '[Personel PWA]') !== false) {
                        $displayName = 'PWA Personel';
                    } else {
                        $displayName = 'Sistem';
                    }
                }

                $data[] = [
                    'user' => htmlspecialchars($displayName ?? 'Bilinmeyen'),
                    'description' => htmlspecialchars($log->description),
                    'date' => '<span data-sort="'.date('YmdHis', strtotime($log->created_at)).'">' . date('d.m.Y H:i', strtotime($log->created_at)) . '</span>',
                    'actions' => '<div class="text-center">
                                    <button type="button" class="btn btn-sm btn-light btn-log-detay"
                                        style="border-radius: 6px; font-weight:500; color:#475569; border: 1px solid #e2e8f0; background: #fff;"
                                        data-title="Sayfa Görüntüleme"
                                        data-user="'.htmlspecialchars($displayName ?? 'Bilinmeyen').'"
                                        data-date="'.date('d.m.Y H:i', strtotime($log->created_at)).'"
                                        data-content="'.htmlspecialchars($log->description).'">
                                        <i class="bx bx-show me-1 text-primary"></i> Detay
                                    </button>
                                  </div>'
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $data
            ]);
            break;

        default:
            throw new Exception('Geçersiz işlem: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
