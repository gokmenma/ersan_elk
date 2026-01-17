<?php

// Bu endpoint her koşulda JSON döndürmeli.
ob_start();

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\IsbankPosmatikClient;
use App\Helper\IsbankPosmatikXml;
use App\Model\GelirGiderModel;
use App\Model\KasaModel;
use App\Helper\Helper;

header('Content-Type: application/json; charset=utf-8');

// GET ile açılırsa (tarayıcıda direkt açma vb.) boş sayfa yerine anlamlı JSON dön.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'online-api up',
        'debug' => [
            'method' => 'GET',
            'expected' => 'POST with action=isbank-online-cek|isbank-online-import',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatalTypes, true)) return;

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error: ' . ($err['message'] ?? 'unknown') . ' @ ' . ($err['file'] ?? '') . ':' . ($err['line'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
});

/**
 * Bu endpoint'in temel kontratı:
 * - Her koşulda JSON döndürür.
 * - POST action:
 *   - isbank-online-cek   => hareketleri çeker, rows + raw_xml_b64 döner
 *   - isbank-online-import=> raw XML'i (base64) alıp gelir_gider'e aktarır
 */

function respond(array $payload): void
{
    // warning/notice gibi çıktılar varsa JSON'u bozmasın.
    // Note: birden fazla output buffer katmanı olabildiği için hepsini temizle.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $message, array $debug = []): void
{
    respond([
        'status' => 'error',
        'message' => $message,
        'debug' => $debug,
    ]);
}

function requirePostAction(): string
{
    return (string)($_POST['action'] ?? '');
}

function decodeRawXmlFromRequest(): string
{
    // Import XML'i iki şekilde alabilir:
    // - raw_xml_b64 (önerilen)
    // - raw_xml (geriye dönük uyumluluk)
    $rawXmlB64 = (string)($_POST['raw_xml_b64'] ?? '');
    $rawXml = (string)($_POST['raw_xml'] ?? '');

    if (trim($rawXmlB64) !== '') {
        $decoded = base64_decode($rawXmlB64, true);
        if ($decoded === false || trim($decoded) === '') {
            throw new Exception('raw_xml_b64 çözümlenemedi.');
        }
        $rawXml = $decoded;
    }

    if (trim($rawXml) === '') {
        throw new Exception('Aktarım için raw_xml_b64 (veya raw_xml) zorunlu.');
    }

    return $rawXml;
}

function parseJsonArrayFromPost(string $key): array
{
    $raw = (string)($_POST[$key] ?? '');
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    // virgüllü string desteği
    if (strpos($raw, ',') !== false) {
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }
    return [];
}

function parseJsonMapFromPost(string $key): array
{
    $raw = (string)($_POST[$key] ?? '');
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * kasalar.hesap_no -> kasa_id map'i.
 */
function buildKasaMapByOwnerId(int $ownerId): array
{
    $map = []; // hesap_no(string) => kasa_id(int)
    if ($ownerId <= 0) {
        return $map;
    }

    $Kasa = new KasaModel();
    foreach ($Kasa->getKasaListByOwner($ownerId) as $k) {
        $hn = trim((string)($k->hesap_no ?? ''));
        if ($hn !== '') {
            $map[$hn] = (int)$k->id;
        }
    }
    return $map;
}

function gelirGiderExistsByIslemId(GelirGiderModel $GelirGider, int $kasaId, string $islemId): bool
{
    $dup = $GelirGider->getDb()->prepare(
        "SELECT COUNT(*) FROM gelir_gider WHERE kasa_id = :kasa_id AND islem_id = :islem_id"
    );
    $dup->execute([
        'kasa_id' => $kasaId,
        'islem_id' => $islemId,
    ]);
    return (int)$dup->fetchColumn() > 0;
}

function importTxToGelirGider(
    GelirGiderModel $GelirGider,
    int $kasaId,
    string $accNo,
    array $tx,
    ?string $descriptionOverride = null
): bool {
    $islId = (string)($tx['isl_id'] ?? '');
    if ($islId === '') {
        return false;
    }

    // Yeni şema: idempotency artık gelir_gider.islem_id üzerinden.
    if (gelirGiderExistsByIslemId($GelirGider, $kasaId, $islId)) {
        return false;
    }

    $amount = (float)($tx['amount'] ?? 0);
    $type = $amount >= 0 ? 1 : 2; // 1=gelir, 2=gider
    $islemTuru = $type === 1 ? 2 : 1; // excel import'taki default mantıkla uyumlu

    $desc = $descriptionOverride !== null ? trim((string)$descriptionOverride) : trim((string)($tx['description'] ?? ''));
    if ($desc === '') {
        $desc = 'Online Hesap Hareketi';
    }

    $txDate = (string)($tx['date'] ?? '');
    $islemTarihi = $txDate !== '' ? ($txDate . ' 00:00:00') : date('Y-m-d H:i:s');

    $data = [
        'id' => 0,
        'islem_tarihi' => $islemTarihi,
        'kasa_id' => $kasaId,
        'type' => $type,
        'islem_turu' => $islemTuru,
        'hesap_adi' => $accNo,
        'tutar' => (string)abs($amount),
        'aciklama' => $desc,
        'islem_id' => $islId,
        'uye_id' => 0,
    ];
    $GelirGider->saveWithAttr($data);
    return true;
}

$action = requirePostAction();

try {
    if ($action === 'isbank-online-cek') {
        $uid = (string)($_POST['uid'] ?? '');
        $pwd = (string)($_POST['pwd'] ?? '');
        $begin = $_POST['BeginDate'] ?? null;
        $end = $_POST['EndDate'] ?? null;

        $client = new IsbankPosmatikClient();
        $xml = $client->fetchXml($uid, $pwd, $begin, $end);
        $parsed = IsbankPosmatikXml::parse($xml);

        $rows = [];
        foreach ($parsed['accounts'] as $acc) {
            foreach ($acc['transactions'] as $tx) {
                $rows[] = [
                    'account_no' => $acc['account_no'] ?? null,
                    'branch_name' => $acc['branch_name'] ?? null,
                    'currency' => $acc['currency'] ?? null,
                    'date' => $tx['date'] ?? null,
                    'amount' => $tx['amount'] ?? null,
                    'balance' => $tx['balance'] ?? null,
                    'description' => $tx['description'] ?? null,
                    'isl_id' => $tx['isl_id'] ?? null,
                    'isl_tur_grup' => $tx['isl_tur_grup'] ?? null,
                    'isl_tur_acik' => $tx['isl_tur_acik'] ?? null,
                    'vkn' => $tx['vkn'] ?? null,
                    'source' => $tx['source'] ?? null,
                ];
            }
        }
   
        respond([
            'status' => 'success',
            'message' => 'Kayıtlar çekildi.',
            'data' => [
                'generated_date' => $parsed['generated_date'],
                'accounts' => $parsed['accounts'],
                // XML'i JSON içinde güvenli taşımak için base64 encode ediyoruz.
                'raw_xml_b64' => base64_encode($xml),
                'rows' => $rows,
            ],
        ]);
    }

    if ($action === 'isbank-online-import') {
        $rawXml = decodeRawXmlFromRequest();
        $parsed = IsbankPosmatikXml::parse($rawXml);

        // UI'dan seçili satırlar gelirse sadece onları aktar
        $selectedIslIds = parseJsonArrayFromPost('selected_isl_ids');
        $selectedSet = [];
        foreach ($selectedIslIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') $selectedSet[$id] = true;
        }

        // UI açıklama override desteği: {"isl_id": "Yeni açıklama"}
        $descOverrides = parseJsonMapFromPost('desc_overrides');

        // Gelir/Gider'e aktarım için kasa eşlemesi: kasalar.hesap_no == XMLEXBAT HesapNo
        $ownerId = (int)($_SESSION['owner_id'] ?? 0);
        $kasaMap = buildKasaMapByOwnerId($ownerId);


        $GelirGider = new GelirGiderModel();
        $ggInserted = 0;
        $ggSkipped = 0;

        foreach ($parsed['accounts'] as $acc) {
            $rawAccountJson = json_encode($acc, JSON_UNESCAPED_UNICODE);
            $accNo = trim((string)($acc['account_no'] ?? ''));
            $matchedKasaId = $accNo !== '' && isset($kasaMap[$accNo]) ? (int)$kasaMap[$accNo] : 0;
            foreach ($acc['transactions'] as $tx) {
                if (empty($tx['isl_id'])) continue;

                $islId = (string)$tx['isl_id'];
                if (!empty($selectedSet) && !isset($selectedSet[$islId])) {
                    continue;
                }
                // Bu array şimdilik sadece debug amaçlı dursun diye korunuyor (UI'ye dönmüyoruz).
                // İstersen ileride import response'una da ekleyebiliriz.
                $rows[] = [
                    'isl_id' => (string)$tx['isl_id'],
                    'hesap_no' => $acc['account_no'] ?? null,
                    'sube_adi' => $acc['branch_name'] ?? null,
                    'doviz_turu' => $acc['currency'] ?? null,
                    'islem_tarihi' => $tx['date'] ?? null,
                    'tutar' => $tx['amount'] ?? null,
                    'bakiye' => $tx['balance'] ?? null,
                    'aciklama' => $tx['description'] ?? null,
                    'isl_tur_grup' => $tx['isl_tur_grup'] ?? null,
                    'isl_tur_acik' => $tx['isl_tur_acik'] ?? null,
                    'vkn' => $tx['vkn'] ?? null,
                    'kaynak' => $tx['source'] ?? null,
                    'raw_account_json' => $rawAccountJson,
                    'raw_tx_json' => json_encode($tx, JSON_UNESCAPED_UNICODE),
                ];

                // Gelir/Gider'e de yaz (kasa eşleşmesi varsa)
                if ($matchedKasaId > 0) {
                    $override = null;
                    if (isset($descOverrides[$islId])) {
                        $override = (string)$descOverrides[$islId];
                    }

                    $ok = importTxToGelirGider($GelirGider, $matchedKasaId, $accNo, $tx, $override);
                    if ($ok) {
                        $ggInserted++;
                    } else {
                        $ggSkipped++;
                    }
                }
            }
        }

        respond([
            'status' => 'success',
            'message' => 'Aktarım tamamlandı.',
            'data' => [
                // Artık sadece gelir_gider tablosuna aktarım yapılıyor
                'inserted' => $ggInserted,
                'skipped' => $ggSkipped,
                'total' => $ggInserted + $ggSkipped,
                'debug' => [
                    'matched_accounts' => count($kasaMap),
                    'selected_count' => !empty($selectedSet) ? count($selectedSet) : 0,
                ],
            ],
        ]);
    }

    respond([
        'status' => 'error',
        'message' => 'Geçersiz action.',
        'debug' => [
            'action' => $action,
        ],
    ]);
} catch (Throwable $e) {
    $noise = '';
    if (ob_get_length()) {
        $noise = trim((string)ob_get_contents());
    }

    $msg = $e->getMessage();
    if ($noise !== '') {
        $msg .= ' | Output: ' . mb_substr($noise, 0, 500);
    }

    errorResponse($msg, [
        'action' => $action,
        'has_uid' => isset($_POST['uid']) && trim((string)$_POST['uid']) !== '',
        'has_pwd' => isset($_POST['pwd']) && trim((string)$_POST['pwd']) !== '',
        'begin' => $_POST['BeginDate'] ?? null,
        'end' => $_POST['EndDate'] ?? null,
    ]);
}

// Normalde buraya gelinmemeli (respond() exit yapar). Yine de boş body riski olmasın.
respond([
    'status' => 'error',
    'message' => 'Beklenmeyen durum: yanıt üretilmedi.',
    'debug' => [
        'action' => $action,
    ],
]);
