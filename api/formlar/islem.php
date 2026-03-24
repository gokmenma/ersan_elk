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

$firma_id = $_SESSION['firma_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$Formlar = new FormlarModel();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $liste = $Formlar->getAll($firma_id);
        $data = [];
        foreach ($liste as $row) {
            $data[] = [
                'id' => Security::encrypt($row->id),
                'baslik' => htmlspecialchars($row->baslik),
                'dosya_adi' => htmlspecialchars($row->dosya_adi),
                'ekleyen_adi' => htmlspecialchars($row->ekleyen_adi),
                'eklenme_tarihi' => date('d.m.Y H:i', strtotime($row->eklenme_tarihi)),
                'dosya_yolu' => $row->dosya_yolu
            ];
        }
        echo json_encode(['data' => $data]);
        exit;
    }

    if ($action === 'ekle') {
        $baslik = $_POST['baslik'] ?? '';

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
        if (!in_array($ext, $allowed)) {
            throw new Exception("Sadece PDF, Word ve Excel belgeleri yüklenebilir.");
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/formlar';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $newName = time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
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

        $form = $Formlar->getById($id, $firma_id);
        if (!$form) {
            throw new Exception("Form bulunamadı.");
        }

        // Fişi de sil
        $filePath = dirname(__DIR__, 2) . '/' . $form->dosya_yolu;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Use standard decrypt=false because it is already decrypted
        $Formlar->delete($id, false);

        echo json_encode(['status' => 'success', 'message' => 'Form silindi.']);
        exit;
    }

    throw new Exception("Geçersiz istek.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
