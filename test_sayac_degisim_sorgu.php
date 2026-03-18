<?php
require_once __DIR__ . '/Autoloader.php';

use App\Service\SayacDegisimService;

function getParam(string $name, $default = null)
{
    if (PHP_SAPI === 'cli') {
        static $opts = null;
        if ($opts === null) {
            $opts = getopt('', [
                'start::',
                'end::',
                'ekip::',
                'limit::',
                'offset::',
                'max-pages::',
                'details::',
                'detail-limit::'
            ]);
        }
        return $opts[$name] ?? $default;
    }

    return $_GET[$name] ?? $default;
}

function normalizeDate(?string $value, string $fallback): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) {
        return $value;
    }

    return $fallback;
}

function toApiDate(string $ymd): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    return $dt ? $dt->format('d/m/Y') : date('d/m/Y');
}

function extractTeamNo(string $team): int
{
    if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $team, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/(\d+)/', $team, $m)) {
        return (int) $m[1];
    }
    return 0;
}

function ekipMatches(string $apiEkip, string $filter): bool
{
    if ($filter === '') {
        return true;
    }

    if (ctype_digit($filter)) {
        return extractTeamNo($apiEkip) === (int) $filter;
    }

    return mb_stripos($apiEkip, $filter, 0, 'UTF-8') !== false;
}

function parseApiTarih(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('d/m/Y H:i:s', $raw);
    if ($dt) {
        return $dt->format('Y-m-d');
    }

    $dt = DateTime::createFromFormat('d/m/Y', $raw);
    if ($dt) {
        return $dt->format('Y-m-d');
    }

    return '';
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderWebPage(array $form, array $summaryRows = [], array $detailRows = [], array $meta = [], ?string $errorMessage = null): void
{
    $hasResult = !empty($summaryRows) || !empty($detailRows) || !empty($meta) || $errorMessage !== null;
    ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sayaç Değişim API Test</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 20px; background: #f6f7fb; color: #202532; }
        .card { max-width: 1100px; margin: 0 auto; background: #fff; border: 1px solid #dbe1ee; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 14px; font-size: 22px; }
        h2 { margin: 20px 0 10px; font-size: 16px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 10px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 12px; color: #4a5568; font-weight: 600; }
        input[type="text"], input[type="date"], input[type="number"] { height: 36px; border: 1px solid #cfd8ea; border-radius: 8px; padding: 0 10px; }
        .actions { margin-top: 14px; display: flex; gap: 10px; align-items: center; }
        button { height: 38px; border: none; background: #0f62fe; color: #fff; border-radius: 8px; padding: 0 14px; font-weight: 600; cursor: pointer; }
        .hint { font-size: 12px; color: #65708a; margin-top: 10px; }
        .stats { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        .badge { background: #eef3ff; color: #21448c; border: 1px solid #c9d8ff; border-radius: 999px; padding: 5px 10px; font-size: 12px; }
        .error { margin-top: 14px; border: 1px solid #f2b8b5; background: #fff2f1; color: #8a231c; border-radius: 8px; padding: 10px; }
        .table-wrap { overflow: auto; border: 1px solid #dde3f0; border-radius: 8px; background: #fff; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #ebeff7; padding: 8px 10px; text-align: left; white-space: nowrap; }
        th { position: sticky; top: 0; background: #f4f7fd; z-index: 1; }
        tr:hover td { background: #fafcff; }
        .muted { color: #6b7280; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Sayaç Değişim API Test</h1>
        <form method="get">
            <input type="hidden" name="run" value="1">
            <div class="grid">
                <div class="field">
                    <label for="start">Başlangıç Tarihi</label>
                    <input type="date" id="start" name="start" value="<?= h($form['start'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="end">Bitiş Tarihi</label>
                    <input type="date" id="end" name="end" value="<?= h($form['end'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="ekip">Ekip Kodu</label>
                    <input type="text" id="ekip" name="ekip" placeholder="43 veya EKİP-43" value="<?= h($form['ekip'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="limit">API Limit</label>
                    <input type="number" id="limit" name="limit" min="1" max="5000" value="<?= (int) ($form['limit'] ?? 100) ?>">
                </div>
                <div class="field">
                    <label for="max-pages">Maks Sayfa</label>
                    <input type="number" id="max-pages" name="max-pages" min="1" max="100" value="<?= (int) ($form['max-pages'] ?? 1) ?>">
                </div>
                <div class="field">
                    <label for="offset">Offset</label>
                    <input type="number" id="offset" name="offset" min="0" value="<?= (int) ($form['offset'] ?? 0) ?>">
                </div>
                <div class="field">
                    <label for="details">Detay (0/1)</label>
                    <input type="number" id="details" name="details" min="0" max="1" value="<?= !empty($form['details']) ? 1 : 0 ?>">
                </div>
                <div class="field">
                    <label for="detail-limit">Detay Limit</label>
                    <input type="number" id="detail-limit" name="detail-limit" min="1" max="2000" value="<?= (int) ($form['detail-limit'] ?? 200) ?>">
                </div>
            </div>
            <div class="actions">
                <button type="submit">API'den Sorgula</button>
            </div>
        </form>
        <div class="hint">Not: Tarayıcıda hız için varsayılan olarak max-pages=1 ve limit=100 kullanılır. Gerekirse artırabilirsiniz.</div>

        <?php if ($hasResult): ?>
            <?php if ($errorMessage !== null): ?>
                <div class="error"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <?php if (!empty($meta)): ?>
                <div class="stats">
                    <div class="badge">API Başlangıç: <?= h($meta['api_start'] ?? '-') ?></div>
                    <div class="badge">API Bitiş: <?= h($meta['api_end'] ?? '-') ?></div>
                    <div class="badge">Çekilen: <?= (int) ($meta['api_fetched_total'] ?? 0) ?></div>
                    <div class="badge">Filtrelenen: <?= (int) ($meta['filtered_total'] ?? 0) ?></div>
                    <div class="badge">Özet Satır: <?= (int) ($meta['summary_count'] ?? 0) ?></div>
                </div>
            <?php endif; ?>

            <h2>Özet</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Ekip</th>
                            <th>Kayıt Sayısı</th>
                            <th>Benzersiz İş Emri</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($summaryRows)): ?>
                            <tr><td colspan="4" class="muted">Kayıt bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($summaryRows as $row): ?>
                                <tr>
                                    <td><?= h($row['tarih'] ?? '') ?></td>
                                    <td><?= h($row['ekip'] ?? '') ?></td>
                                    <td><?= (int) ($row['kayit_sayisi'] ?? 0) ?></td>
                                    <td><?= (int) ($row['benzersiz_isemri_sayisi'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($detailRows)): ?>
                <h2>Detay (İlk <?= (int) count($detailRows) ?> Kayıt)</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Ekip</th>
                                <th>İş Emri No</th>
                                <th>Abone No</th>
                                <th>İş Emri Sonucu</th>
                                <th>Memur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailRows as $d): ?>
                                <tr>
                                    <td><?= h($d['__TARIH_YMD'] ?? '') ?></td>
                                    <td><?= h($d['EKIP'] ?? '') ?></td>
                                    <td><?= h($d['ISEMRI_NO'] ?? '') ?></td>
                                    <td><?= h($d['ABONE_NO'] ?? '') ?></td>
                                    <td><?= h($d['ISEMRI_SONUCU'] ?? '') ?></td>
                                    <td><?= h($d['MEMUR'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
}

$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-d');

$isCli = PHP_SAPI === 'cli';
$shouldRun = $isCli || isset($_GET['run']);

$startDate = normalizeDate((string) getParam('start', $defaultStart), $defaultStart);
$endDate = normalizeDate((string) getParam('end', $defaultEnd), $defaultEnd);
$ekipFilter = trim((string) getParam('ekip', ''));
$defaultLimit = $isCli ? 500 : 100;
$defaultMaxPages = $isCli ? 10 : 1;

$limit = max(1, min((int) getParam('limit', $defaultLimit), 5000));
$offset = max(0, (int) getParam('offset', 0));
$maxPages = max(1, min((int) getParam('max-pages', $defaultMaxPages), 100));
$showDetails = (string) getParam('details', '0') === '1';
$detailLimit = max(1, min((int) getParam('detail-limit', 200), 2000));

if ($endDate < $startDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$apiStart = toApiDate($startDate);
$apiEnd = toApiDate($endDate);

if (!$isCli && !$shouldRun) {
    renderWebPage([
        'start' => $startDate,
        'end' => $endDate,
        'ekip' => $ekipFilter,
        'limit' => $limit,
        'max-pages' => $maxPages,
        'offset' => $offset,
        'details' => $showDetails,
        'detail-limit' => $detailLimit
    ]);
    exit;
}

$svc = new SayacDegisimService();
$allApiRows = [];
$totalFetched = 0;
$page = 0;
$currentOffset = $offset;

try {
    while ($page < $maxPages) {
        $resp = $svc->getData($apiStart, $apiEnd, $limit, $currentOffset);
        $rows = $resp['data']['data'] ?? [];

        if (!is_array($rows) || empty($rows)) {
            break;
        }

        $count = count($rows);
        $totalFetched += $count;
        $allApiRows = array_merge($allApiRows, $rows);

        if ($count < $limit) {
            break;
        }

        $currentOffset += $limit;
        $page++;
    }

    $filtered = [];
    foreach ($allApiRows as $row) {
        $ekip = trim((string) ($row['EKIP'] ?? ''));
        if (!ekipMatches($ekip, $ekipFilter)) {
            continue;
        }

        $tarih = parseApiTarih($row['SONUC_TARIHI'] ?? '');
        if ($tarih === '') {
            $tarih = $startDate;
        }

        if ($tarih < $startDate || $tarih > $endDate) {
            continue;
        }

        $row['__TARIH_YMD'] = $tarih;
        $filtered[] = $row;
    }

    $summary = [];
    foreach ($filtered as $row) {
        $tarih = $row['__TARIH_YMD'];
        $ekip = trim((string) ($row['EKIP'] ?? ''));
        $key = $tarih . '|' . $ekip;

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'tarih' => $tarih,
                'ekip' => $ekip,
                'kayit_sayisi' => 0,
                'isemri_no_sayisi' => []
            ];
        }

        $summary[$key]['kayit_sayisi']++;
        $isemriNo = trim((string) ($row['ISEMRI_NO'] ?? ''));
        if ($isemriNo !== '') {
            $summary[$key]['isemri_no_sayisi'][$isemriNo] = true;
        }
    }

    foreach ($summary as &$s) {
        $s['benzersiz_isemri_sayisi'] = count($s['isemri_no_sayisi']);
        unset($s['isemri_no_sayisi']);
    }
    unset($s);

    $summaryRows = array_values($summary);
    usort($summaryRows, function ($a, $b) {
        return [$a['tarih'], $a['ekip']] <=> [$b['tarih'], $b['ekip']];
    });

    if ($isCli) {
        echo "Sayac Degisim API Test Sorgusu\n";
        echo "start={$startDate} end={$endDate} api_start={$apiStart} api_end={$apiEnd} ekip=" . ($ekipFilter !== '' ? $ekipFilter : '-') . "\n";
        echo "api_limit={$limit} api_offset_baslangic={$offset} max_pages={$maxPages} cekilen_toplam={$totalFetched} filtrelenen_toplam=" . count($filtered) . "\n";
        echo str_repeat('-', 120) . "\n";

        if (empty($summaryRows)) {
            echo "Kayit bulunamadi.\n";
        } else {
            foreach ($summaryRows as $row) {
                echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        if ($showDetails) {
            echo str_repeat('-', 120) . "\n";
            echo "Detay (ilk {$detailLimit} kayit):\n";
            $det = array_slice($filtered, 0, $detailLimit);
            foreach ($det as $d) {
                echo json_encode([
                    'tarih' => $d['__TARIH_YMD'],
                    'ekip' => $d['EKIP'] ?? '',
                    'isemri_no' => $d['ISEMRI_NO'] ?? '',
                    'abone_no' => $d['ABONE_NO'] ?? '',
                    'isemri_sonucu' => $d['ISEMRI_SONUCU'] ?? '',
                    'memur' => $d['MEMUR'] ?? ''
                ], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        exit(0);
    }

    renderWebPage(
        [
            'start' => $startDate,
            'end' => $endDate,
            'ekip' => $ekipFilter,
            'limit' => $limit,
            'max-pages' => $maxPages,
            'offset' => $offset,
            'details' => $showDetails,
            'detail-limit' => $detailLimit
        ],
        $summaryRows,
        $showDetails ? array_slice($filtered, 0, $detailLimit) : [],
        [
            'api_start' => $apiStart,
            'api_end' => $apiEnd,
            'api_fetched_total' => $totalFetched,
            'filtered_total' => count($filtered),
            'summary_count' => count($summaryRows)
        ],
        null
    );
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "HATA: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    renderWebPage(
        [
            'start' => $startDate,
            'end' => $endDate,
            'ekip' => $ekipFilter,
            'limit' => $limit,
            'max-pages' => $maxPages,
            'offset' => $offset,
            'details' => $showDetails,
            'detail-limit' => $detailLimit
        ],
        [],
        [],
        [
            'api_start' => $apiStart,
            'api_end' => $apiEnd
        ],
        $e->getMessage()
    );
}
