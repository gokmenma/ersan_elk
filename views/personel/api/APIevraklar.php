<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\PersonelEvrakModel;
use App\Helper\Security;
use App\Core\Db;

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Uploads dizini
define('UPLOAD_DIR', dirname(__DIR__, 3) . '/uploads/personel_evraklar/');

// Uploads dizinini oluştur
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $action = $_POST['action'] ?? '';
    $EvrakModel = new PersonelEvrakModel();

    // EVRAK YÜKLE
    if ($action === 'evrak_yukle') {
        $personel_id = $_POST['personel_id'] ?? 0;
        $evrak_adi = trim($_POST['evrak_adi'] ?? '');
        $evrak_turu = $_POST['evrak_turu'] ?? 'diger';
        $aciklama = trim($_POST['aciklama'] ?? '');
        
        // Doğrulamalar
        if (empty($personel_id)) {
            throw new Exception('Personel ID bulunamadı.');
        }
        
        if (empty($evrak_adi)) {
            throw new Exception('Evrak adı zorunludur.');
        }
        
        // Dosya kontrolü
        if (!isset($_FILES['evrak_dosyasi']) || $_FILES['evrak_dosyasi']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
                UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
                UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.',
                UPLOAD_ERR_EXTENSION => 'Dosya uzantısı engellendi.'
            ];
            $errorCode = $_FILES['evrak_dosyasi']['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new Exception($uploadErrors[$errorCode] ?? 'Dosya yükleme hatası.');
        }
        
        $file = $_FILES['evrak_dosyasi'];
        
        // Dosya tipi kontrolü
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Bu dosya türü desteklenmiyor. Desteklenen türler: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX');
        }
        
        // Dosya boyutu kontrolü (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('Dosya boyutu 10MB\'ı geçemez.');
        }
        
        // Personel klasörünü oluştur
        $personelDir = UPLOAD_DIR . $personel_id . '/';
        if (!is_dir($personelDir)) {
            mkdir($personelDir, 0755, true);
        }
        
        // Benzersiz dosya adı oluştur
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('evrak_') . '_' . time() . '.' . $extension;
        $targetPath = $personelDir . $newFileName;
        
        // Dosyayı taşı
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Dosya kaydedilemedi.');
        }
        
        // Kullanıcı ID'sini al (session'dan)
        session_start();
        $yukleyen_id = $_SESSION['user_id'] ?? null;
        
        // Veritabanına kaydet
        $evrakData = [
            'id' => 0,
            'personel_id' => $personel_id,
            'evrak_adi' => $evrak_adi,
            'evrak_turu' => $evrak_turu,
            'dosya_adi' => $newFileName,
            'orijinal_dosya_adi' => $file['name'],
            'dosya_boyutu' => $file['size'],
            'dosya_tipi' => $mimeType,
            'aciklama' => $aciklama,
            'yukleyen_id' => $yukleyen_id
        ];
        
        $encryptedId = $EvrakModel->saveWithAttr($evrakData);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Evrak başarıyla yüklendi.',
            'id' => $encryptedId
        ]);
    }
    
    // EVRAK SİL
    elseif ($action === 'evrak_sil') {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            throw new Exception('Geçersiz ID.');
        }
        
        // Şifreli ID'yi çöz
        if (!is_numeric($id)) {
            $id = Security::decrypt($id);
        }
        
        // Evrak bilgilerini al
        $evrak = $EvrakModel->getById($id);
        if (!$evrak) {
            throw new Exception('Evrak bulunamadı.');
        }
        
        // Dosyayı sil (opsiyonel - soft delete yapacağız)
        // $filePath = UPLOAD_DIR . $evrak->personel_id . '/' . $evrak->dosya_adi;
        // if (file_exists($filePath)) {
        //     unlink($filePath);
        // }
        
        // Soft delete
        $result = $EvrakModel->softDeleteEvrak($id);
        
        if ($result) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Evrak başarıyla silindi.'
            ]);
        } else {
            throw new Exception('Silme işlemi sırasında bir hata oluştu.');
        }
    }
    
    // EVRAK GÖRÜNTÜLE/İNDİR
    elseif ($action === 'evrak_getir') {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            throw new Exception('Geçersiz ID.');
        }
        
        // Şifreli ID'yi çöz
        if (!is_numeric($id)) {
            $id = Security::decrypt($id);
        }
        
        $evrak = $EvrakModel->getById($id);
        if (!$evrak) {
            throw new Exception('Evrak bulunamadı.');
        }
        
        $filePath = 'uploads/personel_evraklar/' . $evrak->personel_id . '/' . $evrak->dosya_adi;
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => Security::encrypt($evrak->id),
                'evrak_adi' => $evrak->evrak_adi,
                'evrak_turu' => $evrak->evrak_turu,
                'dosya_adi' => $evrak->dosya_adi,
                'orijinal_dosya_adi' => $evrak->orijinal_dosya_adi,
                'dosya_tipi' => $evrak->dosya_tipi,
                'dosya_boyutu' => $evrak->dosya_boyutu,
                'dosya_yolu' => $filePath,
                'aciklama' => $evrak->aciklama,
                'yukleme_tarihi' => $evrak->yukleme_tarihi,
                'yukleyen_adi' => $evrak->yukleyen_adi
            ]
        ]);
    }
    
    // EVRAK LİSTESİ
    elseif ($action === 'evrak_listele') {
        $personel_id = $_POST['personel_id'] ?? 0;
        
        if (empty($personel_id)) {
            throw new Exception('Personel ID bulunamadı.');
        }
        
        $evraklar = $EvrakModel->getByPersonel($personel_id);
        $stats = $EvrakModel->getStats($personel_id);
        
        $data = [];
        foreach ($evraklar as $evrak) {
            $data[] = [
                'id' => Security::encrypt($evrak->id),
                'evrak_adi' => $evrak->evrak_adi,
                'evrak_turu' => $evrak->evrak_turu,
                'dosya_tipi' => $evrak->dosya_tipi,
                'dosya_boyutu' => $evrak->dosya_boyutu,
                'yukleme_tarihi' => date('d.m.Y H:i', strtotime($evrak->yukleme_tarihi)),
                'yukleyen_adi' => $evrak->yukleyen_adi ?? '-'
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'stats' => $stats
        ]);
    }
    
    else {
        throw new Exception('Geçersiz işlem.');
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
