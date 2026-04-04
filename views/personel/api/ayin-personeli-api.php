<?php

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\AyinPersoneliModel;
use App\Service\Gate;

header('Content-Type: application/json');

if (!Gate::allows("personel_listesi")) {
    echo json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim!']);
    exit;
}

$model = new AyinPersoneliModel();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get-candidates':
        $donem = $_GET['donem'] ?? date('Y-m');
        $firma_id = $_SESSION['firma_id'] ?? 0;
        
        try {
            $candidates = $model->getTopCandidates($donem, $firma_id, 10);
            echo json_encode(['status' => 'success', 'data' => $candidates]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'save-winner':
        $personel_id = $_POST['personel_id'] ?? null;
        $hediye_id = $_POST['hediye_id'] ?? null;
        $donem = $_POST['donem'] ?? date('Y-m');
        $mesaj = $_POST['mesaj'] ?? '';
        $firma_id = $_SESSION['firma_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;

        if (!$personel_id || !$hediye_id) {
            echo json_encode(['status' => 'error', 'message' => 'Lütfen personel ve hediye seçiniz!']);
            exit;
        }

        try {
            // Check if already selected for this month
            $existing = $model->getWinnerForMonth($donem, $firma_id);
            if ($existing) {
                 // Update instead of error? Or let admin know.
                 // For now, let's allow updating by deleting first or update directly if ID matches
            }

            $data = [
                'personel_id' => $personel_id,
                'donem' => $donem,
                'hediye_id' => $hediye_id,
                'aciklama' => $mesaj,
                'firma_id' => $firma_id,
                'ekleyen_user_id' => $user_id
            ];
            
            $result = $model->saveWinner($data);
            
            if ($result) {
                // Here you could also send a notification to the winner
                echo json_encode(['status' => 'success', 'message' => 'Kazanan başarıyla kaydedildi!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt sırasında bir hata oluştu.']);
            }
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem!']);
        break;
}
