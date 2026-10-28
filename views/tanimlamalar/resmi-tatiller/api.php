<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\PermissionPolicyModel;
use App\Model\ResmiTatilModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$action = (string) ($_POST['action'] ?? (($_GET['action'] ?? '') === 'excel-sablon' ? 'excel-sablon' : ''));
if (!in_array($action, ['liste', 'getir', 'kaydet', 'sil', 'excel-sablon', 'excel-yukle'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanımsız işlem.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy('tanimlamalar/resmi-tatiller/api', $action);
} elseif (!Gate::allows('resmi_tatil_tanimlari')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Resmi tatil tanımları için yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$Model = new ResmiTatilModel();
$Log = new SystemLogModel();
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function resmiTatilJson(array $payload, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($firmaId <= 0 || !$Model->isReady()) {
    resmiTatilJson(['status' => 'error', 'message' => 'Resmi tatil altyapısı hazır değil veya firma seçilmedi.'], 409);
}

function resmiTatilExcelDate($value): ?string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (is_numeric($value) && (float) $value > 20000) {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
    }
    $text = trim((string) $value);
    foreach (['!d.m.Y', '!d/m/Y', '!Y-m-d', '!d-m-Y'] as $format) {
        $date = DateTime::createFromFormat($format, $text);
        if ($date && $date->format(ltrim($format, '!')) === $text) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

function resmiTatilExcelTime($value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return '13:00:00';
    }
    if (is_numeric($value) && (float) $value < 1) {
        $minutes = (int) round((float) $value * 1440);
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
    $text = str_replace('.', ':', trim((string) $value));
    return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:\d{2})?$/', $text, $m) ? sprintf('%02d:%02d:00', $m[1], $m[2]) : null;
}

try {
    if ($action === 'excel-sablon') {
        $year = (int) ($_GET['yil'] ?? date('Y'));
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resmi Tatiller');
        $headers = ['Tarih', 'Açıklama', 'Tür', 'Yarım Gün Saati', 'Fazla Mesai Saati'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '556EE6']],
        ]);
        $rowNo = 2;
        foreach ($Model->listForYear($firmaId, $year) as $row) {
            $isYarim = $row['tur'] === ResmiTatilModel::YARIM;
            $sheet->setCellValueExplicit('A' . $rowNo, date('d.m.Y', strtotime($row['tatil_tarihi'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $rowNo, $row['aciklama']);
            $sheet->setCellValue('C' . $rowNo, $isYarim ? 'Yarım' : 'Tam');
            $sheet->setCellValueExplicit('D' . $rowNo, $isYarim ? substr((string) $row['yarim_gun_saati'], 0, 5) : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $rowNo, $isYarim ? (float) $row['fazla_mesai_saat'] : '');
            $rowNo++;
        }
        $validation = $sheet->getCell('C2')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
            ->setAllowBlank(false)->setShowDropDown(true)->setFormula1('"Tam,Yarım"');
        $sheet->setDataValidation('C2:C500', $validation);
        $sheet->getStyle('A2:A500')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('D2:D500')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        foreach (['A' => 14, 'B' => 48, 'C' => 10, 'D' => 18, 'E' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Açıklama');
        $notes->fromArray([
            ['Alan', 'Kural'],
            ['Tarih', 'GG.AA.YYYY biçiminde (örn. 29.10.2027).'],
            ['Açıklama', 'Zorunlu, en fazla 100 karakter.'],
            ['Tür', '"Tam" = tam gün resmi tatil (çalışana RTÇ). "Yarım" = arife (kesim saatinden sonra çalışana fazla mesai).'],
            ['Yarım Gün Saati', 'Yalnız Yarım için; boşsa 13:00.'],
            ['Fazla Mesai Saati', 'Yalnız Yarım için; boşsa 4. 0,5 ile 12 arası.'],
            ['Not', 'Aynı tarih firmada zaten tanımlıysa güncellenir, yoksa eklenir. Satır silinmesi kaydı silmez.'],
        ], null, 'A1');
        $notes->getStyle('A1:B1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setWidth(20);
        $notes->getColumnDimension('B')->setWidth(100);
        $spreadsheet->setActiveSheetIndex(0);

        header_remove('Content-Type');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="resmi-tatiller-' . $year . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    if ($action === 'excel-yukle') {
        $file = $_FILES['excel_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            resmiTatilJson(['status' => 'error', 'message' => 'Dosya yüklenemedi.'], 422);
        }
        if (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['xlsx', 'xls'], true) || $file['size'] > 5 * 1024 * 1024) {
            resmiTatilJson(['status' => 'error', 'message' => 'Yalnız 5 MB altındaki .xlsx veya .xls dosyaları yüklenebilir.'], 422);
        }

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name'])->getSheet(0);
        $headerRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, false)[0] ?? [];
        $normalize = static fn($v) => str_replace([' ', 'ı', 'İ'], ['', 'i', 'i'], mb_strtolower(trim((string) $v), 'UTF-8'));
        $columns = [];
        foreach ($headerRow as $index => $header) {
            $key = $normalize($header);
            $map = ['tarih' => 'tarih', 'açiklama' => 'aciklama', 'aciklama' => 'aciklama', 'tür' => 'tur', 'tur' => 'tur',
                'yarimgünsaati' => 'saat', 'yarimgunsaati' => 'saat', 'fazlamesaisaati' => 'fm'];
            if (isset($map[$key])) {
                $columns[$map[$key]] = $index;
            }
        }
        if (!isset($columns['tarih'], $columns['aciklama'])) {
            resmiTatilJson(['status' => 'error', 'message' => 'Excel ilk satırında "Tarih" ve "Açıklama" başlıkları bulunmalıdır. Şablonu indirip kullanınız.'], 422);
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $data = $sheet->rangeToArray('A2:' . $sheet->getHighestColumn() . $sheet->getHighestDataRow(), null, true, false);
        foreach ($data as $i => $cells) {
            $line = $i + 2;
            $get = static fn($key) => isset($columns[$key]) ? ($cells[$columns[$key]] ?? null) : null;
            if (count(array_filter($cells, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $date = resmiTatilExcelDate($get('tarih'));
            $aciklama = trim((string) $get('aciklama'));
            $turText = $normalize($get('tur'));
            $tur = (str_contains($turText, 'yar') || str_contains($turText, 'arife')) ? ResmiTatilModel::YARIM : ResmiTatilModel::TAM;
            $saat = resmiTatilExcelTime($get('saat'));
            $fmRaw = trim(str_replace(',', '.', (string) $get('fm')));
            $fm = $fmRaw === '' ? 4.0 : (float) $fmRaw;

            if ($date === null) { $errors[] = "Satır $line: tarih okunamadı."; continue; }
            if ($aciklama === '') { $errors[] = "Satır $line: açıklama boş."; continue; }
            if ($tur === ResmiTatilModel::YARIM && $saat === null) { $errors[] = "Satır $line: yarım gün saati SS:DD olmalı."; continue; }
            if ($tur === ResmiTatilModel::YARIM && ($fm < 0.5 || $fm > 12)) { $errors[] = "Satır $line: fazla mesai saati 0,5–12 arası olmalı."; continue; }
            if (isset($seen[$date])) { $errors[] = "Satır $line: " . date('d.m.Y', strtotime($date)) . " tarihi dosyada tekrar ediyor."; continue; }
            $seen[$date] = true;

            $rows[] = [
                'tatil_tarihi' => $date,
                'aciklama' => $aciklama,
                'tur' => $tur,
                'yarim_gun_saati' => $tur === ResmiTatilModel::YARIM ? $saat : '13:00:00',
                'fazla_mesai_saat' => $tur === ResmiTatilModel::YARIM ? $fm : 4,
            ];
        }

        if (!$rows) {
            resmiTatilJson(['status' => 'error', 'message' => 'Aktarılacak geçerli satır bulunamadı.', 'errors' => array_slice($errors, 0, 50)], 422);
        }
        if (count($rows) > 1000) {
            resmiTatilJson(['status' => 'error', 'message' => 'Tek seferde en fazla 1000 satır yüklenebilir.'], 422);
        }

        $global = !empty($_POST['genel_tanim']) && Gate::isSuperAdmin();
        $result = $Model->importRows($rows, $global ? 0 : $firmaId, $userId);
        $Log->logAction($userId, 'Resmi Tatil Excel Yükleme', sprintf('%s: %d eklendi, %d güncellendi, %d satır hatalı (%s).',
            (string) $file['name'], $result['created'], $result['updated'], count($errors), $global ? 'genel' : 'firma'), SystemLogModel::LEVEL_IMPORTANT);
        resmiTatilJson([
            'status' => 'success',
            'message' => sprintf('%d tatil günü eklendi, %d güncellendi%s.', $result['created'], $result['updated'],
                $errors ? ', ' . count($errors) . ' satır atlandı' : ''),
            'errors' => array_slice($errors, 0, 50),
        ]);
    }

    if ($action === 'liste') {
        $res = $Model->ajaxList($_POST, $firmaId);
        $summary = $Model->summary($firmaId, (int) ($_POST['yil'] ?? 0));
        $days = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        $canManageGlobal = Gate::isSuperAdmin();
        $rows = [];
        foreach ($res['data'] as $row) {
            $isYarim = $row->tur === ResmiTatilModel::YARIM;
            $isGlobal = (int) $row->firma_id === 0;
            $encId = $h(Security::encrypt((int) $row->id));
            $editable = !$isGlobal || $canManageGlobal;
            $actions = '<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">';
            if ($editable) {
                $actions .= '<button type="button" class="btn btn-subtle-warning table-action-btn btn-edit-holiday" data-id="' . $encId . '" title="Düzenle"><i class="bx bx-edit font-size-14"></i></button>'
                    . '<button type="button" class="btn btn-subtle-danger table-action-btn btn-delete-holiday cm-danger" data-id="' . $encId . '" title="Sil"><i class="bx bx-trash font-size-14"></i></button>';
            } else {
                $actions .= '<span class="badge bg-light text-muted border" title="Genel tanımları yalnız Süper Admin değiştirebilir"><i class="bx bx-lock-alt"></i></span>';
            }
            $actions .= '</div>';

            $rows[] = [
                'tarih' => $h(date('d.m.Y', strtotime($row->tatil_tarihi))),
                'gun' => $h($days[(int) date('w', strtotime($row->tatil_tarihi))]),
                'aciklama' => $h($row->aciklama),
                'tur' => $isYarim
                    ? '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Yarım gün / arife</span>'
                    : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Tam gün</span>',
                'islem' => $isYarim
                    ? $h(substr((string) $row->yarim_gun_saati, 0, 5) . ' sonrası +' . rtrim(rtrim(number_format((float) $row->fazla_mesai_saat, 2, ',', ''), '0'), ',') . ' saat FM')
                    : 'RTÇ',
                'kapsam' => $isGlobal
                    ? '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Genel</span>'
                    : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Firma</span>',
                'actions' => $actions,
            ];
        }

        $siradaki = $summary->siradaki;
        resmiTatilJson([
            'draw' => $res['draw'],
            'recordsTotal' => $res['recordsTotal'],
            'recordsFiltered' => $res['recordsFiltered'],
            'data' => $rows,
            'summary' => [
                'yil' => $summary->yil,
                'toplam' => $summary->toplam,
                'tam_gun' => $summary->tam_gun,
                'yarim_gun' => $summary->yarim_gun,
                'gelecek' => $summary->gelecek,
                'siradaki_tarih' => $siradaki ? date('d.m.Y', strtotime($siradaki['tatil_tarihi'])) : null,
                'siradaki_ad' => $siradaki['aciklama'] ?? null,
            ],
        ]);
    }

    if ($action === 'getir') {
        $id = (int) Security::decrypt((string) ($_POST['id'] ?? ''));
        $row = $id > 0 ? $Model->findForFirm($id, $firmaId) : null;
        if (!$row) {
            resmiTatilJson(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
        }
        resmiTatilJson(['status' => 'success', 'data' => [
            'id' => Security::encrypt((int) $row['id']),
            'tatil_tarihi' => date('d.m.Y', strtotime($row['tatil_tarihi'])),
            'aciklama' => $row['aciklama'],
            'tur' => $row['tur'],
            'yarim_gun_saati' => substr((string) $row['yarim_gun_saati'], 0, 5),
            'fazla_mesai_saat' => (float) $row['fazla_mesai_saat'],
            'genel' => (int) $row['firma_id'] === 0,
        ]]);
    }

    if ($action === 'kaydet') {
        $id = !empty($_POST['id']) ? (int) Security::decrypt((string) $_POST['id']) : 0;
        $date = DateTime::createFromFormat('!d.m.Y', trim((string) ($_POST['tatil_tarihi'] ?? '')));
        if (!$date || $date->format('d.m.Y') !== trim((string) $_POST['tatil_tarihi'])) {
            resmiTatilJson(['status' => 'error', 'message' => 'Geçerli bir tarih giriniz (GG.AA.YYYY).'], 422);
        }
        $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
        if ($aciklama === '') {
            resmiTatilJson(['status' => 'error', 'message' => 'Açıklama zorunludur.'], 422);
        }
        $tur = ($_POST['tur'] ?? '') === ResmiTatilModel::YARIM ? ResmiTatilModel::YARIM : ResmiTatilModel::TAM;
        $saat = (string) ($_POST['yarim_gun_saati'] ?? '13:00');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $saat)) {
            resmiTatilJson(['status' => 'error', 'message' => 'Yarım gün başlangıç saati SS:DD biçiminde olmalıdır.'], 422);
        }
        $fm = (float) str_replace(',', '.', (string) ($_POST['fazla_mesai_saat'] ?? '4'));
        if ($tur === ResmiTatilModel::YARIM && ($fm < 0.5 || $fm > 12)) {
            resmiTatilJson(['status' => 'error', 'message' => 'Fazla mesai saati 0,5 ile 12 arasında olmalıdır.'], 422);
        }

        if ($id > 0) {
            $existing = $Model->findForFirm($id, $firmaId);
            if (!$existing) {
                resmiTatilJson(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            if ((int) $existing['firma_id'] === 0 && !Gate::isSuperAdmin()) {
                resmiTatilJson(['status' => 'error', 'message' => 'Genel tanımları yalnız Süper Admin değiştirebilir.'], 403);
            }
        }

        $savedId = $Model->saveHoliday([
            'id' => $id,
            'firma_id' => (!empty($_POST['genel_tanim']) && Gate::isSuperAdmin()) ? 0 : $firmaId,
            'tatil_tarihi' => $date->format('Y-m-d'),
            'aciklama' => $aciklama,
            'tur' => $tur,
            'yarim_gun_saati' => $saat . ':00',
            'fazla_mesai_saat' => $tur === ResmiTatilModel::YARIM ? $fm : 4,
        ], $firmaId, $userId);

        $Log->logAction($userId, 'Resmi Tatil Tanımı', sprintf('%s %s (%s) %s.', $date->format('d.m.Y'), $aciklama,
            $tur === ResmiTatilModel::YARIM ? 'yarım gün' : 'tam gün', $id > 0 ? 'güncellendi' : 'eklendi'), SystemLogModel::LEVEL_IMPORTANT);
        resmiTatilJson(['status' => 'success', 'message' => $id > 0 ? 'Tatil günü güncellendi.' : 'Tatil günü eklendi.', 'id' => Security::encrypt($savedId)]);
    }

    $id = (int) Security::decrypt((string) ($_POST['id'] ?? ''));
    $existing = $id > 0 ? $Model->findForFirm($id, $firmaId) : null;
    if (!$existing) {
        resmiTatilJson(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
    }
    if ((int) $existing['firma_id'] === 0 && !Gate::isSuperAdmin()) {
        resmiTatilJson(['status' => 'error', 'message' => 'Genel tanımları yalnız Süper Admin silebilir.'], 403);
    }
    $Model->softDeleteHoliday($id, $firmaId, $userId);
    $Log->logAction($userId, 'Resmi Tatil Tanımı', sprintf('%s %s tatil tanımı silindi.',
        date('d.m.Y', strtotime($existing['tatil_tarihi'])), (string) $existing['aciklama']), SystemLogModel::LEVEL_IMPORTANT);
    resmiTatilJson(['status' => 'success', 'message' => 'Tatil günü silindi.']);
} catch (RuntimeException $e) {
    resmiTatilJson(['status' => 'error', 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[resmi-tatiller/api] ' . $e->getMessage());
    resmiTatilJson(['status' => 'error', 'message' => 'İşlem sırasında beklenmeyen bir hata oluştu.'], 500);
}
