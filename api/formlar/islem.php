<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();
include dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Security;
use App\Model\FormlarModel;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.']);
    exit;
}

$firma_id = (int) ($_SESSION['firma_id'] ?? 1);
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$Formlar = new FormlarModel();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $liste = $Formlar->getAll($firma_id);
        $stats = $Formlar->getStats($firma_id);
        $data = [];
        foreach ($liste as $row) {
            $ext = strtolower(pathinfo((string) $row->dosya_adi, PATHINFO_EXTENSION));
            $format = match ($ext) {
                'docx', 'doc' => 'word',
                'xlsx', 'xls' => 'excel',
                'pdf' => 'pdf',
                default => 'other'
            };
            $data[] = [
                'id' => Security::encrypt($row->id),
                'baslik' => htmlspecialchars((string) $row->baslik, ENT_QUOTES, 'UTF-8'),
                'dosya_adi' => htmlspecialchars((string) $row->dosya_adi, ENT_QUOTES, 'UTF-8'),
                'format' => $format,
                'uzanti' => $ext,
                'ekleyen_adi' => htmlspecialchars((string) ($row->ekleyen_adi ?? '-'), ENT_QUOTES, 'UTF-8'),
                'eklenme_tarihi' => date('d.m.Y H:i', strtotime((string) $row->eklenme_tarihi)),
                'dosya_yolu' => $row->dosya_yolu
            ];
        }
        echo json_encode(['data' => $data, 'stats' => $stats]);
        exit;
    }

    if ($action === 'ekle') {
        $baslik = trim((string) ($_POST['baslik'] ?? ''));

        if (empty($baslik)) {
            throw new Exception("Lütfen başlık giriniz.");
        }

        if (!isset($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Lütfen geçerli bir dosya yükleyiniz.");
        }

        $fileTmp = $_FILES['dosya']['tmp_name'];
        $fileName = $_FILES['dosya']['name'];
        $fileSize = $_FILES['dosya']['size'];

        // Allowed extensions
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($ext, $allowed, true)) {
            throw new Exception("Sadece PDF, Word ve Excel belgeleri yüklenebilir.");
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/formlar';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $newName = time() . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $dest = $uploadDir . '/' . $newName;

        if (!move_uploaded_file($fileTmp, $dest)) {
            throw new Exception("Dosya yüklenirken bir hata oluştu.");
        }

        $Formlar->saveWithAttr([
            'firma_id' => $firma_id,
            'baslik' => $baslik,
            'dosya_yolu' => 'uploads/formlar/' . $newName,
            'dosya_adi' => $fileName,
            'ekleyen_id' => $userId
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Form başarıyla yüklendi.']);
        exit;
    }

    if ($action === 'sil') {
        $idStr = $_POST['id'] ?? '';
        if (!$idStr) {
            throw new Exception("Geçersiz işlem.");
        }
        $id = Security::decrypt($idStr);
        if (!$id) {
            throw new Exception("Geçersiz kimlik.");
        }

        $form = $Formlar->getById((int) $id, $firma_id);
        if (!$form) {
            throw new Exception("Form bulunamadı.");
        }

        $filePath = dirname(__DIR__, 2) . '/' . $form->dosya_yolu;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $Formlar->delete((int) $id, false);

        echo json_encode(['status' => 'success', 'message' => 'Form şablonu başarıyla silindi.']);
        exit;
    }

    throw new Exception("Geçersiz istek.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
