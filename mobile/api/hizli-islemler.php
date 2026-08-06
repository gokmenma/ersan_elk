<?php
/**
 * Mobil Hızlı İşlemler AJAX API
 * Kullanıcının mobil hızlı işlem tercihlerini kaydeder ve yönetir.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

use App\Model\UserModel;
use App\Model\SystemLogModel;

session_start();
header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, $data = null, string $message = ''): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($currentUserId <= 0 || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    jsonResponse(false, null, 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userModel = new UserModel();

try {
    switch ($action) {
        case 'update_hizli_islemler':
            $rawItems = $_POST['hizli_islemler'] ?? [];
            if (is_string($rawItems)) {
                $rawItems = json_decode($rawItems, true) ?? [];
            }
            if (!is_array($rawItems)) {
                jsonResponse(false, null, 'Geçersiz veri biçimi.');
            }

            $success = $userModel->saveMobileHizliIslemler($currentUserId, $rawItems);
            if ($success) {
                try {
                    $logModel = new SystemLogModel();
                    $logModel->logAction(
                        $currentUserId,
                        'Mobil Hızlı İşlemler Güncellendi',
                        "[Mobil Admin] Kullanıcı (ID: {$currentUserId}) mobil hızlı işlem tercihlerini güncelledi.",
                        SystemLogModel::LEVEL_INFO
                    );
                } catch (\Exception $ex) {
                    error_log('Mobil Hizli Islemler log error: ' . $ex->getMessage());
                }

                $updatedItems = $userModel->getMobileHizliIslemler($currentUserId);
                jsonResponse(true, [
                    'hizli_islemler' => $updatedItems
                ], 'Hızlı işlemler başarıyla güncellendi.');
            } else {
                jsonResponse(false, null, 'Hızlı işlemler kaydedilirken bir hata oluştu.');
            }
            break;

        case 'reset_hizli_islemler':
            $db = (new \App\Core\Db())->getConnection();
            $stmt = $db->prepare("UPDATE users SET mobile_hizli_islemler = NULL WHERE id = ?");
            $success = $stmt->execute([$currentUserId]);

            if ($success) {
                try {
                    $logModel = new SystemLogModel();
                    $logModel->logAction(
                        $currentUserId,
                        'Mobil Hızlı İşlemler Sıfırlandı',
                        "[Mobil Admin] Kullanıcı (ID: {$currentUserId}) mobil hızlı işlemlerini varsayılana sıfırladı.",
                        SystemLogModel::LEVEL_INFO
                    );
                } catch (\Exception $ex) {
                    error_log('Mobil Hizli Islemler reset log error: ' . $ex->getMessage());
                }

                jsonResponse(true, null, 'Hızlı işlemler varsayılana sıfırlandı.');
            } else {
                jsonResponse(false, null, 'Sıfırlama sırasında bir hata oluştu.');
            }
            break;

        default:
            jsonResponse(false, null, 'Geçersiz işlem parametresi.');
            break;
    }
} catch (\Throwable $e) {
    error_log('Mobile hizli-islemler API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Sunucuda bir hata oluştu. Lütfen tekrar deneyin.');
}
