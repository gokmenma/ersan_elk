<?php
/**
 * Mevcut personel kayıtlarındaki hassas alanları AES-256-CBC ile şifreler.
 *
 * Çalıştırmak için: php database/migrations/2026_07_01_encrypt_personel_sensitive_fields.php
 * Web erişimine kapalı dizinde olduğu için doğrudan URL ile çalıştırılamaz.
 */

define('PROJECT_ROOT', dirname(__DIR__, 2));
require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
$dotenv->load();

use App\Helper\Security;
use App\Core\Db;

$db = new Db();
$pdo = $db->getConnection();

$fields = ['kaski_sifre', 'kaski_kullanici_adi', 'iban_numarasi', 'ek_odeme_iban_numarasi', 'kan_grubu'];

$fieldList = implode(', ', $fields);
$stmt = $pdo->query("SELECT id, $fieldList FROM personel WHERE silinme_tarihi IS NULL");
$records = $stmt->fetchAll(PDO::FETCH_OBJ);

$updated = 0;
$skipped = 0;

foreach ($records as $record) {
    $updates = [];
    $params = [':id' => $record->id];

    foreach ($fields as $field) {
        $value = $record->$field ?? '';
        if (empty($value)) {
            continue;
        }

        $test = Security::decrypt($value);
        if ($test !== 0 && $test !== false && $test !== '') {
            $skipped++;
            continue;
        }

        $encrypted = Security::encrypt($value);
        $updates[] = "$field = :$field";
        $params[":$field"] = $encrypted;
    }

    if (!empty($updates)) {
        $sql = "UPDATE personel SET " . implode(', ', $updates) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        $updated++;
    }
}

echo "Tamamlandı. Güncellenen: $updated kayıt. Zaten şifreli (atlandı): $skipped alan.\n";
