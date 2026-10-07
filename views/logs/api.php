<?php
/**
 * Sistem Logları API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\SystemLogModel;
use App\Model\PermissionPolicyModel;
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
$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('logs/api', (string) $action);
}
$systemLogModel = new SystemLogModel();

$getColumnFilter = static function (int $index): string {
    $raw = trim((string) ($_POST['columns'][$index]['search']['value'] ?? ''));
    if ($raw === '') {
        return '';
    }
    $separator = strpos($raw, ':');
    return $separator === false ? $raw : trim(substr($raw, $separator + 1));
};

$normalizeFilterDate = static function (string $value): string {
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $value, $parts)) {
        return sprintf('%04d-%02d-%02d', (int) $parts[3], (int) $parts[2], (int) $parts[1]);
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
};

try {
    switch ($action) {
        case 'get-unified-logs':
            $draw = (int) ($_POST['draw'] ?? 1);
            $filters = [
                'limit' => (int) ($_POST['length'] ?? 25),
                'offset' => (int) ($_POST['start'] ?? 0),
                'search' => trim((string) ($_POST['search']['value'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'scope' => in_array($_POST['scope'] ?? '30', ['30', '90', 'all', 'archive'], true) ? ($_POST['scope'] ?? '30') : '30',
                'date' => $normalizeFilterDate($getColumnFilter(0)),
                'user' => $getColumnFilter(1),
                'type' => $getColumnFilter(2),
                'module' => $getColumnFilter(3),
                'detail' => $getColumnFilter(4),
                'related' => $getColumnFilter(5),
                'include_ai' => Gate::allows('ai_is_ajani_arac_takip'),
            ];
            $logs = $systemLogModel->getUnifiedActivities($filters);
            $totalRecords = $systemLogModel->getUnifiedActivitiesCount(['include_ai' => $filters['include_ai'], 'scope' => $filters['scope']]);
            $filteredRecords = $systemLogModel->getUnifiedActivitiesCount($filters);
            $badgeMap = [
                'view' => ['bx-show', 'Sayfa Ziyareti', 'audit-badge-view'],
                'login' => ['bx-log-in', 'Giriş', 'audit-badge-login'],
                'critical' => ['bx-error-circle', 'Kritik Olay', 'audit-badge-critical'],
                'delete' => ['bx-trash', 'Silme', 'audit-badge-delete'],
                'ai' => ['bx-bot', 'Yapay Zeka', 'audit-badge-ai'],
                'operation' => ['bx-pointer', 'İşlem', 'audit-badge-operation'],
            ];
            $data = [];
            foreach ($logs as $log) {
                $category = (string) ($log->category ?? 'operation');
                [$icon, $fallbackLabel, $badgeClass] = $badgeMap[$category] ?? $badgeMap['operation'];
                $dateValue = strtotime((string) $log->activity_date);
                $userName = (string) ($log->user_name ?: 'Sistem');
                $initial = htmlspecialchars(mb_substr($userName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $escapedUser = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
                $eventType = htmlspecialchars((string) ($log->event_type ?: $fallbackLabel), ENT_QUOTES, 'UTF-8');
                $module = htmlspecialchars((string) ($log->module_name ?: 'Sistem'), ENT_QUOTES, 'UTF-8');
                $detail = htmlspecialchars((string) ($log->detail ?? ''), ENT_QUOTES, 'UTF-8');
                $related = htmlspecialchars((string) ($log->related_record ?? '-'), ENT_QUOTES, 'UTF-8');
                $data[] = [
                    'date' => '<div class="audit-date" data-sort="'.date('YmdHis', $dateValue).'"><strong>'.date('d.m.Y H:i:s', $dateValue).'</strong><small>'.($dateValue >= time() - 60 ? 'Az önce' : date('H:i', $dateValue)).'</small></div>',
                    'user' => '<div class="audit-user"><span class="audit-avatar">'.$initial.'</span><strong>'.$escapedUser.'</strong></div>',
                    'type' => '<span class="audit-event-badge '.$badgeClass.'"><i class="bx '.$icon.'"></i>'.$eventType.'</span>',
                    'module' => '<span class="audit-module-badge"><i class="bx bx-cube-alt"></i>'.$module.'</span>',
                    'detail' => '<button type="button" class="audit-detail-btn btn-log-detay" data-title="'.$eventType.'" data-user="'.$escapedUser.'" data-date="'.date('d.m.Y H:i', $dateValue).'" data-content="'.$detail.'" data-changes="W10="><i class="bx bx-show"></i> Detay</button><span class="audit-detail-text">'.$detail.'</span>',
                    'related' => '<span class="audit-related-badge">'.$related.'</span>',
                ];
            }
            echo json_encode(['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $data]);
            break;

        case 'get-system-logs':
            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';

            $filters = [
                'limit' => $length,
                'offset' => $start,
                'search' => $search,
                'max_level' => 2, // Page view hariç
                'column_user' => $getColumnFilter(1),
                'column_action' => $getColumnFilter(2),
                'column_description' => $getColumnFilter(3),
                'column_date' => $normalizeFilterDate($getColumnFilter(4)),
            ];

            $levelFilter = mb_strtolower($getColumnFilter(0), 'UTF-8');
            if ($levelFilter !== '') {
                $filters['level'] = str_contains($levelFilter, 'kritik') ? 2 : (str_contains($levelFilter, 'önemli') ? 1 : 0);
            }

            $logs = $systemLogModel->getAllLogs($filters);
            $totalRecords = $systemLogModel->getLogsCount(['max_level' => 2]);
            $filteredRecords = $systemLogModel->getLogsCount($filters);

            $data = [];
            $personelAlanEtiketleri = [
                'adi_soyadi' => 'Adı Soyadı',
                'tc_kimlik_no' => 'T.C. Kimlik No',
                'telefon' => 'Telefon',
                'email' => 'E-posta',
                'adres' => 'Adres',
                'dogum_tarihi' => 'Doğum Tarihi',
                'ise_giris_tarihi' => 'İşe Giriş Tarihi',
                'isten_cikis_tarihi' => 'İşten Çıkış Tarihi',
                'aktif_mi' => 'Aktiflik Durumu',
                'ekip_no' => 'Ekip No',
                'iban_numarasi' => 'Maaş IBAN',
                'ek_odeme_iban_numarasi' => 'Ek Ödeme IBAN',
                'maas' => 'Maaş',
                'kumulatif_matrah_devir' => 'Kümülatif Matrah Devri',
                'sodexo' => 'Sodexo Ödemesi',
                'sodexo_kart_no' => 'Sodexo Kart No',
                'bes_kesintisi_varmi' => 'BES Kesintisi',
                'yemek_yardimi_aliyor' => 'Yemek Yardımı',
                'yemek_yardimi_tutari' => 'Yemek Yardımı Tutarı',
                'yemek_yardimi_dahil' => 'Yemek Yardımı Maaşa Dahil',
                'es_yardimi_aliyor' => 'Eş Yardımı',
                'es_yardimi_tutari' => 'Eş Yardımı Tutarı',
                'es_yardimi_dahil' => 'Eş Yardımı Maaşa Dahil',
                'puantaj_hakedis_dahil' => 'Puantaj Hakediş Durumu'
            ];

            $parsePersonelChanges = static function (string $description) use ($personelAlanEtiketleri): array {
                $result = ['summary' => $description, 'changes' => []];
                if (!preg_match('/^(.*?)\s*\(Güncellenen veriler:\s*\{\s*(.*?)\s*\}\)\s*$/us', $description, $matches)) {
                    return $result;
                }

                $result['summary'] = trim($matches[1]);
                $changesText = trim($matches[2]);
                if ($changesText === '' || $changesText === 'Değişiklik yok') {
                    return $result;
                }

                foreach (preg_split('/,\s+(?=[a-zA-Z0-9_]+:\s)/u', $changesText) as $change) {
                    if (!preg_match('/^([^:]+):\s*(.*?)\s+(?:->|→)\s+(.*)$/us', trim($change), $parts)) {
                        continue;
                    }
                    $field = trim($parts[1]);
                    $fallbackLabel = mb_convert_case(str_replace('_', ' ', $field), MB_CASE_TITLE, 'UTF-8');
                    $result['changes'][] = [
                        'field' => $personelAlanEtiketleri[$field] ?? $fallbackLabel,
                        'old' => trim($parts[2]),
                        'new' => trim($parts[3])
                    ];
                }

                return $result;
            };

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

                $user_name = (string) ($log->adi_soyadi ?? 'Sistem');
                $escapedUserName = htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8');
                $userInitial = htmlspecialchars(mb_substr($user_name, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $short_desc = htmlspecialchars(
                    mb_strimwidth((string) $log->description, 0, 100, '...', 'UTF-8'),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $escapedActionType = htmlspecialchars((string) $log->action_type, ENT_QUOTES, 'UTF-8');
                $description = (string) $log->description;
                $personelChangeData = $log->action_type === 'Personel Güncelleme'
                    ? $parsePersonelChanges($description)
                    : ['summary' => $description, 'changes' => []];
                $encodedChanges = base64_encode(json_encode($personelChangeData['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $userHtml = '<div class="d-flex align-items-center gap-2">'
                    .'<span class="avatar-title rounded-circle bg-soft-primary text-primary flex-shrink-0" '
                    .'style="width:30px;height:30px;font-size:0.75rem;">'.$userInitial.'</span>'
                    .'<span class="fw-semibold text-dark">'.$escapedUserName.'</span></div>';
                
                $data[] = [
                    'level' => $levelBadge,
                    'user' => $userHtml,
                    'action_type' => '<i class="'.$levelIcon.' me-1"></i> ' . $escapedActionType,
                    'description' => $short_desc,
                    'date' => '<span data-sort="'.date('YmdHis', strtotime($log->created_at)).'">' . date('d.m.Y H:i', strtotime($log->created_at)) . '</span>',
                    'actions' => '<div class="text-center">
                                    <button type="button" class="btn btn-sm btn-light btn-log-detay"
                                        style="border-radius: 6px; font-weight:500; color:#475569; border: 1px solid #e2e8f0; background: #fff;"
                                        data-title="'.$escapedActionType.'"
                                        data-user="'.$escapedUserName.'"
                                        data-date="'.date('d.m.Y H:i', strtotime($log->created_at)).'"
                                        data-content="'.htmlspecialchars($personelChangeData['summary'], ENT_QUOTES, 'UTF-8').'"
                                        data-changes="'.htmlspecialchars($encodedChanges, ENT_QUOTES, 'UTF-8').'">
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
            if ($search === '') {
                $search = $getColumnFilter(0) ?: ($getColumnFilter(2) ?: $getColumnFilter(3));
            }

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
            if ($search === '') {
                $search = $getColumnFilter(0) ?: $getColumnFilter(2);
            }

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

        case 'get-ai-agent-logs':
            if (!\App\Service\Gate::allows('ai_is_ajani_arac_takip')) {
                echo json_encode(["draw" => 1, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
                break;
            }

            $draw = intval($_POST['draw'] ?? 1);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $search = $_POST['search']['value'] ?? '';
            if ($search === '') {
                $search = $getColumnFilter(0) ?: ($getColumnFilter(1) ?: ($getColumnFilter(2) ?: ($getColumnFilter(3) ?: $getColumnFilter(5))));
            }

            $logs = $systemLogModel->getAiAgentLogs($length, $start, $search);
            $totalRecords = $systemLogModel->getAiAgentLogsCount();
            $filteredRecords = $systemLogModel->getAiAgentLogsCount($search);

            $data = [];
            foreach ($logs as $ll) {
                $userName = (string) ($ll->adi_soyadi ?? '');
                $avatar = htmlspecialchars(mb_substr($userName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $escapedUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
                $escapedPrompt = htmlspecialchars((string) $ll->prompt, ENT_QUOTES, 'UTF-8');
                $response = (string) ($ll->response ?? '');
                $responsePreview = mb_strlen($response, 'UTF-8') > 120
                    ? mb_substr($response, 0, 120, 'UTF-8').'…'
                    : $response;
                $escapedResponsePreview = htmlspecialchars($responsePreview ?: 'Cevap kaydı bulunamadı.', ENT_QUOTES, 'UTF-8');
                $escapedResponse = htmlspecialchars($response ?: 'Cevap kaydı bulunamadı.', ENT_QUOTES, 'UTF-8');
                $formattedDate = date('d.m.Y H:i:s', strtotime($ll->created_at));
                $userHtml = '
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title rounded-circle" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                '.$avatar.'
                            </span>
                        </div>
                        <div>
                            <h5 class="font-size-14 mb-0" style="color: #334155; font-weight: 600;">
                                '.$escapedUserName.'
                            </h5>
                            <span class="badge bg-soft-warning text-warning font-size-11" style="border-radius: 4px;">AI Kullanıcısı</span>
                        </div>
                    </div>';

                $dateHtml = '
                    <div class="d-flex flex-column" data-sort="'.date('YmdHis', strtotime($ll->created_at)).'">
                        <span style="color: #334155; font-weight: 600;">'.date('d.m.Y', strtotime($ll->created_at)).'</span>
                        <span style="color: #64748b; font-size: 0.75rem;">'.date('H:i:s', strtotime($ll->created_at)).'</span>
                    </div>';

                $promptHtml = '<span class="fw-semibold text-dark text-break">'.$escapedPrompt.'</span>';
                $responseHtml = '<div class="d-flex align-items-center gap-2">'
                    .'<span class="text-break">'.$escapedResponsePreview.'</span>'
                    .'<button type="button" class="btn btn-sm btn-soft-primary btn-log-detay btn-ai-response flex-shrink-0" '
                    .'data-title="Yapay Zeka Cevabı" data-user="'.$escapedUserName.'" '
                    .'data-date="'.htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8').'" '
                    .'data-content="'.$escapedResponse.'" title="Cevabın tamamını göster">'
                    .'<i class="bx bx-show"></i></button></div>';
                $modelHtml = '<span class="badge bg-soft-info text-info">'.htmlspecialchars($ll->model_used ?? 'gpt-4o-mini', ENT_QUOTES, 'UTF-8').'</span>';
                $statusHtml = ($ll->status === 'success') ? '<span class="badge bg-soft-success text-success">Başarılı</span>' : '<span class="badge bg-soft-danger text-danger">Hata</span>';

                $data[] = [
                    $userHtml,
                    $promptHtml,
                    $responseHtml,
                    $modelHtml,
                    $dateHtml,
                    $statusHtml
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
            if ($search === '') {
                $search = $getColumnFilter(0) ?: $getColumnFilter(1);
            }

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
