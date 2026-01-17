<?php
// Deduplicate settings table by set_name, keeping the highest id per set_name.
// Run: php admin\test\settings_deduplicate.php
// Tip: First run in dry-run mode by setting $dryRun=true.

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Core\Db;

$dryRun = true; // set false to actually delete

$db = (new Db())->getConnection();

// Get duplicates (keep max(id))
$sql = "SELECT set_name, MAX(id) AS keep_id, COUNT(*) AS cnt
        FROM settings
        GROUP BY set_name
        HAVING COUNT(*) > 1";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No duplicates to clean.\n";
    exit(0);
}

echo "Will process " . count($rows) . " duplicated keys. DryRun=" . ($dryRun ? 'true' : 'false') . "\n";

$db->beginTransaction();
try {
    $deleteStmt = $db->prepare("DELETE FROM settings WHERE set_name = :set_name AND id <> :keep_id");

    foreach ($rows as $r) {
        $setName = $r['set_name'];
        $keepId = (int)$r['keep_id'];
        $cnt = (int)$r['cnt'];

        echo "- {$setName}: count={$cnt}, keep_id={$keepId}\n";

        if (!$dryRun) {
            $deleteStmt->execute([
                ':set_name' => $setName,
                ':keep_id' => $keepId,
            ]);
            echo "  deleted=" . $deleteStmt->rowCount() . "\n";
        }
    }

    if ($dryRun) {
        $db->rollBack();
        echo "Dry-run complete (no changes committed).\n";
    } else {
        $db->commit();
        echo "Deduplication complete and committed.\n";
    }
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
