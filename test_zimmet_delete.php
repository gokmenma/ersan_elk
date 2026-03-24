<?php
require_once __DIR__ . '/Autoloader.php';
// Start session for mocks
session_start();
$_SESSION['id'] = 1;
$_SESSION['firma_id'] = 1;

use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasModel;
use App\Helper\Security;

$Zimmet = new DemirbasZimmetModel();
$Demirbas = new DemirbasModel();

// 1. Create a fake demirbas record for testing
$enc_demirbas_id = $Demirbas->saveWithAttr([
    'demirbas_no' => 'TEST-01',
    'demirbas_adi' => 'Test Sayaç',
    'seri_no' => 'TXT-12345',
    'kalan_miktar' => 1,
    'miktar' => 1,
    'firma_id' => 1,
    'durum' => 'Aktif'
]);
$demirbas_id = Security::decrypt($enc_demirbas_id);
echo "Created Demirbas ID: $demirbas_id\n";

$db = $Demirbas->db;
$pid = $db->query('SELECT id FROM personel LIMIT 1')->fetchColumn();

// 2. Zimmet ver (this should reduce kalan_miktar to 0)
$enc_zimmet_id = $Zimmet->zimmetVer([
    'demirbas_id' => $demirbas_id,
    'personel_id' => $pid,
    'teslim_tarihi' => '2026-03-24',
    'teslim_miktar' => 1,
    'aciklama' => 'Test zimmet',
    'teslim_eden_id' => $pid
]);
$zimmet_id = Security::decrypt($enc_zimmet_id);
echo "Created Zimmet ID: $zimmet_id\n";

// Check stock
$d = $Demirbas->find($demirbas_id);
echo "Stock after Zimmet: {$d->kalan_miktar}\n"; // should be 0

// 3. Delete Zimmet
$result = $Zimmet->delete($enc_zimmet_id);
echo "Delete result: " . ($result === true ? "TRUE" : "FALSE/Exception") . "\n";

// Check stock again
$d2 = $Demirbas->find($demirbas_id);
echo "Stock after Delete: {$d2->kalan_miktar}\n"; // should be 1

// Clean up
$Demirbas->db->prepare("DELETE FROM demirbas WHERE id = ?")->execute([$demirbas_id]);
$Zimmet->db->prepare("DELETE FROM demirbas_zimmet WHERE id = ?")->execute([$zimmet_id]);
echo "Cleanup done.\n";
