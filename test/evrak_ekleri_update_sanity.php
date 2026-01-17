<?php
// Quick sanity check for "update document without deleting existing attachments" behavior.
// This is not a PHPUnit test; it's a small script you can run manually in your dev env.
//
// What it checks:
// - When saving an existing "giden" evrak without any new file uploads (no $_FILES['files']),
//   the attachments in `evrak_ekleri` should NOT be deleted.
//
// Usage (typical):
// 1) Open an existing giden evrak that has attachments.
// 2) Click "Kaydet" WITHOUT selecting new files.
// 3) Verify attachments still exist in UI.
//
// Optional deeper check:
// - Put the encrypted evrak id below and run this script via browser/cli to list current attachments.

require_once dirname(__DIR__) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\EvrakEkleriModel;

$EvrakEkleri = new EvrakEkleriModel();

$encId = $_GET['evrak_id'] ?? '';
if (!$encId) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Provide ?evrak_id=<encrypted_id>\n";
    exit;
}

try {
    $evrakId = (int) Security::decrypt($encId);
    $ekler = $EvrakEkleri->getEvrakEkleri($evrakId, 'giden');

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'evrak_id' => $evrakId,
        'count' => count($ekler),
        'ekler' => $ekler,
    ]);
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
