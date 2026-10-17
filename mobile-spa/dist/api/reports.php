<?php

declare(strict_types=1);

use App\Core\Db;
use App\Helper\Date;
use App\Helper\EkipHelper;
use App\Helper\Security;
use App\Model\EndeksOkumaModel;
use App\Model\FirmaModel;
use App\Model\MenuModel;
use App\Model\PersonelModel;
use App\Model\PuantajModel;
use App\Model\SettingsModel;
use App\Model\TanimlamalarModel;
use App\Service\Gate;
use App\Service\SayacDegisimService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function reportsApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if ($userId <= 0 || $firmaId <= 0) {
    reportsApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

// 1. Yetki Doğrulaması (Raporlar permission ID: 61, puantaj/raporlar, puantaj_raporlama, puantaj_yonetim)
$db = (new Db())->getConnection();
$userRoles = !empty($_SESSION['user']->roles) ? explode(',', (string) $_SESSION['user']->roles) : [];
$hasReportPermission = false;

if (!empty($userRoles)) {
    $placeholders = implode(',', array_fill(0, count($userRoles), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_role_permissions WHERE role_id IN ($placeholders) AND permission_id = 61");
    $stmt->execute($userRoles);
    $hasReportPermission = ((int) $stmt->fetchColumn()) > 0;
}

$menuModel = new MenuModel();
$hasAccess = $hasReportPermission
    || Gate::allowsAny(['puantaj_raporlama', 'puantaj_yonetim', 'puantaj/raporlar', 'puantaj/list'])
    || $menuModel->userCanAccessMenuLink($userId, 'puantaj/raporlar')
    || $menuModel->userCanAccessMenuLink($userId, 'puantaj/list')
    || Gate::isSuperAdmin();

if (!$hasAccess) {
    reportsApiResponse(['success' => false, 'message' => 'Raporlar modülüne erişim yetkiniz bulunmuyor.'], 403);
}

$action = $_GET['action'] ?? 'cards';

function cleanTeamName(string $raw): string
{
    $cleaned = preg_replace('/^(?:ER-?SAN\s+ELEKTR[İI]K\s*)/ui', '', trim($raw));
    return trim($cleaned ?: $raw);
}

function getShortCode(string $text): string
{
    $clean = preg_replace('/[^A-ZÇĞİIÖŞÜ\s]/u', '', mb_strtoupper($text, 'UTF-8'));
    $words = explode(' ', (string) $clean);
    $code = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $code .= mb_substr($word, 0, 1, 'UTF-8');
        }
    }
    return $code ?: mb_substr($text, 0, 2, 'UTF-8');
}

// -------------------------------------------------------------
// ACTION: CARDS / SUMMARY LIST
// -------------------------------------------------------------
if ($action === 'cards' || $action === 'summary') {
    try {
        $activeTab = (string) ($_GET['tab'] ?? 'okuma');
        $validTabs = ['okuma', 'kesme', 'sokme_takma', 'muhurleme', 'kacakkontrol'];
        if (!in_array($activeTab, $validTabs, true)) {
            $activeTab = 'okuma';
        }

        $filterType = (string) ($_GET['filter_type'] ?? 'bugun');
        $startDateStr = (string) ($_GET['start_date'] ?? '');
        $endDateStr = (string) ($_GET['end_date'] ?? '');

        // Tarih doğrulaması
        $todayStr = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateStr)) {
            if ($filterType === 'buay') {
                $startDateStr = date('Y-m-01');
                $endDateStr = date('Y-m-t');
            } elseif ($filterType === 'dun') {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $startDateStr = $yesterday;
                $endDateStr = $yesterday;
            } elseif ($filterType === 'hafta') {
                $startDateStr = date('Y-m-d', strtotime('monday this week'));
                $endDateStr = $todayStr;
            } elseif ($filterType === 'gecenay') {
                $startDateStr = date('Y-m-01', strtotime('first day of last month'));
                $endDateStr = date('Y-m-t', strtotime('last day of last month'));
            } else {
                $startDateStr = $todayStr;
                $endDateStr = $todayStr;
            }
        }

        $Tanimlamalar = new TanimlamalarModel();
        $EndeksOkuma = new EndeksOkumaModel();
        $Puantaj = new PuantajModel();
        $Personel = new PersonelModel();
        $Settings = new SettingsModel();

        $workTypes = [];
        if ($activeTab === 'okuma') {
            $summary = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
        } elseif ($activeTab === 'kacakkontrol') {
            $summary = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
        } elseif ($activeTab === 'sokme_takma') {
            $SayacDegisim = new SayacDegisimService();
            $summary = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
            $workTypes = $SayacDegisim->getDistinctWorkTypes();
        } else {
            $summary = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
            $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);
            if (empty($workTypes) && $activeTab === 'kesme') {
                $workTypes = $Tanimlamalar->getUcretliIsTurleri();
            }
        }

        $workTypeCols = [];
        if ($activeTab !== 'okuma' && !empty($workTypes)) {
            foreach ($workTypes as $ut) {
                $workTypeCols[] = [
                    'id' => (int) ($ut->id ?? 0),
                    'name' => (string) ($ut->is_emri_sonucu ?? ''),
                    'code' => getShortCode((string) ($ut->is_emri_sonucu ?? '')),
                ];
            }
        }

        $allPersonel = $Personel->all(false, 'puantaj', $endDateStr);
        $personelById = [];
        $webRoot = dirname(__DIR__, 2);
        foreach ($allPersonel as $p) {
            $photoPath = '';
            if (!empty($p->fotograf) && file_exists($webRoot . '/assets/images/users/' . $p->fotograf)) {
                $photoPath = 'assets/images/users/' . $p->fotograf;
            }
            $p->fotograf_url = $photoPath;
            $personelById[(int) $p->id] = $p;
        }

        $activeAssignments = $Personel->getAllActiveAssignmentsInRange($startDateStr, $endDateStr);
        $allTeams = $Tanimlamalar->getEkipKodlari();
        $teamById = [];
        foreach ($allTeams as $t) {
            $teamById[(int) $t->id] = $t;
        }

        $kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

        // Çiftler ve eşleştirmeler
        $validPairs = [];

        if (!empty($summary)) {
            foreach ($summary as $pId => $teams) {
                if ($activeTab === 'kacakkontrol') {
                    $teamName = (string) $pId;
                    $matchingTeams = array_filter($allTeams, static fn($t) => (string) ($t->tur_adi ?? '') === $teamName);
                    $team = !empty($matchingTeams) ? reset($matchingTeams) : null;
                    $tId = $team ? (int) $team->id : 0;
                    $teamNo = EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
                    if ($team && $teamNo > 0) {
                        if (!EkipHelper::isTeamInTabRange($teamNo, 'kacakkontrol', $Settings)) {
                            continue;
                        }
                    }
                    $validPairs['kacak_' . $teamName] = [
                        'pId' => 'kacak_' . $teamName,
                        'tId' => $tId,
                        'isKacak' => true,
                        'teamName' => $teamName,
                        'ekipKodu' => $teamName,
                        'compositeKey' => $teamName,
                    ];
                } else {
                    foreach ($teams as $compositeKey => $data) {
                        $parts = explode('|', (string) $compositeKey, 2);
                        $tId = (int) $parts[0];
                        $ekipKoduStr = $parts[1] ?? '';

                        $hasRelevantData = true;
                        if ($activeTab !== 'okuma') {
                            $hasRelevantData = false;
                            if (is_array($data)) {
                                foreach ($data as $day => $workTypeCounts) {
                                    if (is_array($workTypeCounts)) {
                                        foreach ($workTypeCounts as $workTypeName => $count) {
                                            foreach ($workTypeCols as $wtCol) {
                                                if ($wtCol['name'] === $workTypeName && (int) $count > 0) {
                                                    $hasRelevantData = true;
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $teamNo = 0;
                        if ($tId > 0 && isset($teamById[$tId])) {
                            $team = $teamById[$tId];
                            $teamNo = EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
                        } elseif (!empty($ekipKoduStr)) {
                            $teamNo = EkipHelper::extractTeamNo($ekipKoduStr);
                        }

                        if ($teamNo > 0) {
                            if (!$hasRelevantData && !EkipHelper::isTeamInTabRange($teamNo, $activeTab, $Settings)) {
                                continue;
                            }
                        } elseif (!$hasRelevantData) {
                            continue;
                        }

                        $validPairs[$pId . '_' . $compositeKey] = [
                            'pId' => $pId,
                            'tId' => $tId,
                            'ekipKodu' => $ekipKoduStr,
                            'compositeKey' => $compositeKey,
                        ];
                    }
                }
            }
        }

        foreach ($activeAssignments as $assign) {
            $assignTeamId = (int) ($assign->ekip_kodu_id ?? 0);
            if (!isset($teamById[$assignTeamId])) {
                continue;
            }
            $team = $teamById[$assignTeamId];
            $teamNo = EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
            if ($teamNo <= 0) {
                continue;
            }
            $isValid = false;
            $personelDepts = !empty($assign->departman) ? array_map('trim', explode(',', (string) $assign->departman)) : [];

            if ($activeTab === 'okuma') {
                if (EkipHelper::isTeamInTabRange($teamNo, 'okuma', $Settings) && (in_array('Endeks Okuma', $personelDepts, true) || in_array('Okuma', $personelDepts, true))) {
                    $isValid = true;
                }
            } elseif ($activeTab === 'kesme') {
                if (EkipHelper::isTeamInTabRange($teamNo, 'kesme', $Settings) && (in_array('Kesme Açma', $personelDepts, true) || in_array('Kesme-Açma', $personelDepts, true))) {
                    $isValid = true;
                }
            } elseif ($activeTab === 'sokme_takma') {
                if (EkipHelper::isTeamInTabRange($teamNo, 'sokme_takma', $Settings) && (in_array('Sayaç Sökme Takma', $personelDepts, true) || in_array('Sökme Takma', $personelDepts, true))) {
                    $isValid = true;
                }
            } elseif ($activeTab === 'muhurleme') {
                if (EkipHelper::isTeamInTabRange($teamNo, 'muhurleme', $Settings)) {
                    $isValid = true;
                }
            }

            if ($isValid) {
                $compositeKey = $assignTeamId . '|' . ($team->tur_adi ?? '');
                $validPairs[$assign->personel_id . '_' . $compositeKey] = [
                    'pId' => (int) $assign->personel_id,
                    'tId' => $assignTeamId,
                    'ekipKodu' => $team->tur_adi ?? '',
                    'compositeKey' => $compositeKey,
                ];
            }
        }

        // Kart dizisi oluşturma
        $cards = [];
        foreach ($validPairs as $pair) {
            $pId = $pair['pId'];
            $tId = (int) $pair['tId'];
            $compositeKey = (string) $pair['compositeKey'];

            $rawTeamName = $teamById[$tId]->tur_adi ?? ($pair['ekipKodu'] ?? '-');
            $cleanTeam = cleanTeamName((string) $rawTeamName);
            $regionName = trim((string) ($teamById[$tId]->ekip_bolge ?? 'TANIMSIZ'));
            if ($regionName === '' || $regionName === '-') {
                $regionName = 'TANIMSIZ';
            }

            if (isset($pair['isKacak'])) {
                $teamName = (string) $pair['teamName'];
                $cleanKacakTeamName = cleanTeamName($teamName);

                $pIdsStr = $kacakPersonelMapping[$teamName] ?? '';
                if (empty($pIdsStr)) {
                    $mappedIds = [];
                    foreach ($activeAssignments as $assign) {
                        if ((int) $assign->ekip_kodu_id === $tId && $tId > 0) {
                            $mappedIds[] = (int) $assign->personel_id;
                        }
                    }
                    $pIdsStr = implode(',', array_unique($mappedIds));
                }
                $names = [];
                foreach (explode(',', (string) $pIdsStr) as $id) {
                    $intId = (int) trim($id);
                    if (isset($personelById[$intId])) {
                        $names[] = $personelById[$intId]->adi_soyadi;
                    }
                }

                $cards[] = [
                    'id' => 'kacak_' . $teamName,
                    'pId' => 0,
                    'name' => !empty($names) ? implode(', ', $names) : $cleanKacakTeamName,
                    'team' => $cleanKacakTeamName,
                    'region' => $regionName,
                    'totals' => [],
                    'total' => 0,
                    'breakdown' => [],
                    'is_kacak' => true,
                    'composite_key' => $compositeKey,
                    'photo' => '',
                ];
            } else {
                $intPId = (int) $pId;
                $p = $personelById[$intPId] ?? null;
                $name = $p ? (string) $p->adi_soyadi : ('Eşleşmeyen Ekip: ' . $cleanTeam);
                $photo = $p ? (string) ($p->fotograf_url ?? '') : '';

                $cards[] = [
                    'id' => (string) $intPId,
                    'pId' => $intPId,
                    'name' => $name,
                    'team' => $cleanTeam,
                    'region' => $regionName,
                    'totals' => [],
                    'total' => 0,
                    'breakdown' => [],
                    'is_kacak' => false,
                    'composite_key' => $compositeKey,
                    'photo' => $photo,
                ];
            }
        }

        // Totals hesaplama
        foreach ($cards as &$card) {
            $lookupId = $card['is_kacak'] ? $card['composite_key'] : $card['pId'];
            $compKey = $card['composite_key'];

            if ($activeTab === 'okuma') {
                $totalVal = 0;
                if (isset($summary[$lookupId][$compKey])) {
                    $dataToSum = $summary[$lookupId][$compKey];
                    if (is_array($dataToSum)) {
                        foreach ($dataToSum as $dayData) {
                            $totalVal += (int) $dayData;
                        }
                    } else {
                        $totalVal += (int) $dataToSum;
                    }
                }
                $card['totals'] = ['A' => $totalVal];
                $card['total'] = $totalVal;
                $card['breakdown'] = [
                    ['name' => 'Okunan Abone', 'code' => 'OKU', 'count' => $totalVal],
                ];
            } elseif ($activeTab === 'kacakkontrol') {
                $totalVal = 0;
                if (isset($summary[$lookupId])) {
                    foreach ($summary[$lookupId] as $dayStr => $sayiVal) {
                        if (!is_array($sayiVal)) {
                            $totalVal += (int) $sayiVal;
                        }
                    }
                }
                $card['totals'] = ['Sayı' => $totalVal];
                $card['total'] = $totalVal;
                $card['breakdown'] = [
                    ['name' => 'Tutanak Sayısı', 'code' => 'TUT', 'count' => $totalVal],
                ];
            } elseif ($activeTab === 'muhurleme') {
                $totalVal = 0;
                if (isset($summary[$lookupId][$compKey])) {
                    foreach ($summary[$lookupId][$compKey] as $dayData) {
                        foreach ($workTypeCols as $wt) {
                            $totalVal += (int) ($dayData[$wt['name']] ?? 0);
                        }
                    }
                }
                $card['totals'] = ['Mühürleme' => $totalVal];
                $card['total'] = $totalVal;
                $card['breakdown'] = [
                    ['name' => 'Mühürleme', 'code' => 'MÜH', 'count' => $totalVal],
                ];
            } else {
                $cT = [];
                foreach ($workTypeCols as $wt) {
                    $cT[$wt['name']] = 0;
                }
                if (isset($summary[$lookupId][$compKey])) {
                    foreach ($summary[$lookupId][$compKey] as $dayData) {
                        foreach ($workTypeCols as $wt) {
                            $cT[$wt['name']] += (int) ($dayData[$wt['name']] ?? 0);
                        }
                    }
                }
                $card['totals'] = $cT;
                $card['total'] = array_sum($cT);

                $bd = [];
                foreach ($cT as $wtName => $cnt) {
                    if ($cnt > 0) {
                        $bd[] = [
                            'name' => $wtName,
                            'code' => getShortCode($wtName),
                            'count' => $cnt,
                        ];
                    }
                }
                $card['breakdown'] = $bd;
            }
        }
        unset($card);

        // Bugün haricinde aktivitesi olmayan (0 olan) kayıtları filtrele,
        // ancak bugün ise ekiplerin tamamını listele (kimin nöbette/sahada olduğunu görmek için)
        $filteredCards = [];
        $isTodayFilter = ($startDateStr === $todayStr && $endDateStr === $todayStr);

        foreach ($cards as $c) {
            if ($c['total'] > 0 || $isTodayFilter) {
                $filteredCards[] = $c;
            }
        }

        // Sıralama: Bölgeye göre ve sonra işlem sayısına göre (azalan)
        usort($filteredCards, static function ($a, $b) {
            if ($a['region'] !== $b['region']) {
                return strcoll($a['region'], $b['region']);
            }
            if ($b['total'] !== $a['total']) {
                return $b['total'] - $a['total'];
            }
            return strcoll($a['name'], $b['name']);
        });

        // Bölge istatistikleri ve renk paleti
        $regionPalette = [
            ['name' => 'blue', 'color' => '#2563eb', 'bg' => 'rgba(37, 99, 235, 0.1)'],
            ['name' => 'emerald', 'color' => '#059669', 'bg' => 'rgba(5, 150, 105, 0.1)'],
            ['name' => 'amber', 'color' => '#d97706', 'bg' => 'rgba(217, 119, 6, 0.1)'],
            ['name' => 'purple', 'color' => '#9333ea', 'bg' => 'rgba(147, 51, 234, 0.1)'],
            ['name' => 'cyan', 'color' => '#0891b2', 'bg' => 'rgba(8, 145, 178, 0.1)'],
            ['name' => 'rose', 'color' => '#e11d48', 'bg' => 'rgba(225, 29, 72, 0.1)'],
            ['name' => 'indigo', 'color' => '#4f46e5', 'bg' => 'rgba(79, 70, 229, 0.1)'],
        ];

        $regionStatsMap = [];
        $grandTotalOperations = 0;

        foreach ($filteredCards as $c) {
            $r = $c['region'];
            if (!isset($regionStatsMap[$r])) {
                $regionStatsMap[$r] = [
                    'name' => $r,
                    'total' => 0,
                    'personnel_count' => 0,
                    'active_count' => 0,
                ];
            }
            $regionStatsMap[$r]['personnel_count']++;
            $regionStatsMap[$r]['total'] += $c['total'];
            if ($c['total'] > 0) {
                $regionStatsMap[$r]['active_count']++;
            }
            $grandTotalOperations += $c['total'];
        }

        // Bölgeleri toplam işlem sayısına göre sırala
        uasort($regionStatsMap, static fn($a, $b) => $b['total'] - $a['total']);

        $regions = [];
        $colorIdx = 0;
        foreach ($regionStatsMap as $rName => $rData) {
            $palette = $regionPalette[$colorIdx % count($regionPalette)];
            $percentage = $grandTotalOperations > 0 ? round(($rData['total'] / $grandTotalOperations) * 100, 1) : 0;
            $regions[] = [
                'name' => $rName,
                'total' => $rData['total'],
                'personnel_count' => $rData['personnel_count'],
                'active_count' => $rData['active_count'],
                'percentage' => $percentage,
                'color' => $palette['color'],
                'bg' => $palette['bg'],
            ];
            $colorIdx++;
        }

        // En başarılı personel ve bölge
        $topPerformer = null;
        $maxCardTotal = -1;
        foreach ($filteredCards as $c) {
            if ($c['total'] > $maxCardTotal && $c['total'] > 0) {
                $maxCardTotal = $c['total'];
                $topPerformer = [
                    'name' => $c['name'],
                    'team' => $c['team'],
                    'region' => $c['region'],
                    'total' => $c['total'],
                ];
            }
        }

        $topRegion = !empty($regions) ? $regions[0] : null;

        $stats = [
            'total_operations' => $grandTotalOperations,
            'total_personnel' => count($filteredCards),
            'active_personnel' => count(array_filter($filteredCards, static fn($c) => $c['total'] > 0)),
            'total_regions' => count($regions),
            'top_performer' => $topPerformer,
            'top_region' => $topRegion ? [
                'name' => $topRegion['name'],
                'total' => $topRegion['total'],
                'percentage' => $topRegion['percentage'],
            ] : null,
            'avg_per_active' => ($stats['active_personnel'] ?? 0) > 0 ? round($grandTotalOperations / $stats['active_personnel'], 1) : 0,
        ];

        // Format dates for UI display
        $formattedDateRange = Date::dmY($startDateStr);
        if ($startDateStr !== $endDateStr) {
            $formattedDateRange .= ' – ' . Date::dmY($endDateStr);
        }

        reportsApiResponse([
            'success' => true,
            'tab' => $activeTab,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'date_range_label' => $formattedDateRange,
            'stats' => $stats,
            'regions' => $regions,
            'cards' => $filteredCards,
            'tabs' => [
                ['id' => 'okuma', 'name' => 'Endeks Okuma', 'icon' => 'Gauge', 'unit' => 'Okuma'],
                ['id' => 'kesme', 'name' => 'Kesme / Açma', 'icon' => 'ZapOff', 'unit' => 'İşlem'],
                ['id' => 'sokme_takma', 'name' => 'Sayaç Sökme/Takma', 'icon' => 'Wrench', 'unit' => 'Sayaç'],
                ['id' => 'muhurleme', 'name' => 'Mühürleme', 'icon' => 'ShieldCheck', 'unit' => 'Mühür'],
                ['id' => 'kacakkontrol', 'name' => 'Kaçak Kontrol', 'icon' => 'ShieldAlert', 'unit' => 'Tutanak'],
            ],
        ]);
    } catch (Throwable $e) {
        error_log('reports cards error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        reportsApiResponse(['success' => false, 'message' => 'Rapor verileri alınırken bir hata oluştu.'], 500);
    }
}

// -------------------------------------------------------------
// ACTION: DETAIL (Daily Breakdown for Person / Team)
// -------------------------------------------------------------
if ($action === 'detail') {
    try {
        $pId = (string) ($_GET['pId'] ?? '');
        $activeTab = (string) ($_GET['tab'] ?? 'okuma');
        $startDateStr = (string) ($_GET['start_date'] ?? date('Y-m-d'));
        $endDateStr = (string) ($_GET['end_date'] ?? date('Y-m-d'));
        $compositeKeyFilter = isset($_GET['composite_key']) ? (string) $_GET['composite_key'] : null;

        if ($pId === '') {
            reportsApiResponse(['success' => false, 'message' => 'Geçersiz personel/ekip ID.'], 400);
        }

        $Tanimlamalar = new TanimlamalarModel();
        $EndeksOkuma = new EndeksOkumaModel();
        $Puantaj = new PuantajModel();

        $workTypes = [];
        if ($activeTab === 'okuma') {
            $summary = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
        } elseif ($activeTab === 'kacakkontrol') {
            $summary = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
        } elseif ($activeTab === 'sokme_takma') {
            $SayacDegisim = new SayacDegisimService();
            $summary = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
            $workTypes = $SayacDegisim->getDistinctWorkTypes();
        } else {
            $summary = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
            $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);
            if (empty($workTypes) && $activeTab === 'kesme') {
                $workTypes = $Tanimlamalar->getUcretliIsTurleri();
            }
        }

        $workTypeCols = [];
        if ($activeTab !== 'okuma' && !empty($workTypes)) {
            foreach ($workTypes as $ut) {
                $workTypeCols[] = [
                    'name' => (string) ($ut->is_emri_sonucu ?? ''),
                ];
            }
        }

        $personelData = [];
        $lookupKey = is_numeric($pId) ? (int) $pId : $pId;

        if (isset($summary[$lookupKey])) {
            if ($activeTab === 'okuma') {
                foreach ($summary[$lookupKey] as $compKey => $dayDataArray) {
                    if ($compositeKeyFilter !== null && (string) $compKey !== $compositeKeyFilter) {
                        continue;
                    }
                    if (is_array($dayDataArray)) {
                        foreach ($dayDataArray as $day => $val) {
                            if (!isset($personelData[$day])) {
                                $personelData[$day] = [];
                            }
                            $personelData[$day]['Okunan Abone'] = ($personelData[$day]['Okunan Abone'] ?? 0) + (int) $val;
                        }
                    }
                }
            } elseif ($activeTab === 'kacakkontrol') {
                foreach ($summary[$lookupKey] as $day => $val) {
                    if (!isset($personelData[$day])) {
                        $personelData[$day] = [];
                    }
                    $personelData[$day]['İşlem Sayısı'] = ($personelData[$day]['İşlem Sayısı'] ?? 0) + (int) $val;
                }
            } else {
                foreach ($summary[$lookupKey] as $compKey => $dayDataArray) {
                    if ($compositeKeyFilter !== null && (string) $compKey !== $compositeKeyFilter) {
                        continue;
                    }
                    if (is_array($dayDataArray)) {
                        foreach ($dayDataArray as $day => $vals) {
                            if (!isset($personelData[$day])) {
                                $personelData[$day] = [];
                            }
                            foreach ($workTypeCols as $wt) {
                                $count = (int) ($vals[$wt['name']] ?? 0);
                                if ($count > 0) {
                                    $personelData[$day][$wt['name']] = ($personelData[$day][$wt['name']] ?? 0) + $count;
                                }
                            }
                        }
                    }
                }
            }
        }

        krsort($personelData); // Tarihe göre azalan sırala

        $days = [];
        $overallWorkTypeCounts = [];
        $totalAllDays = 0;

        foreach ($personelData as $day => $metrics) {
            $hasSomeValue = false;
            foreach ($metrics as $cnt) {
                if ($cnt > 0) {
                    $hasSomeValue = true;
                    break;
                }
            }
            if (!$hasSomeValue) {
                continue;
            }

            if (empty($day) || !str_contains((string) $day, '-')) {
                continue;
            }

            $dayArr = explode('-', (string) $day);
            if (count($dayArr) < 3) {
                continue;
            }

            $mIndex = (int) $dayArr[1];
            $mName = Date::MONTHS[$mIndex] ?? '';
            $dayFormatted = $dayArr[2] . ' ' . $mName . ' ' . $dayArr[0];
            $dayName = Date::gunAdi((string) $day);

            $dayItems = [];
            $dayTotal = 0;
            foreach ($metrics as $metricName => $count) {
                if ((int) $count > 0) {
                    $dayItems[] = [
                        'label' => $metricName,
                        'count' => (int) $count,
                    ];
                    $dayTotal += (int) $count;
                    $overallWorkTypeCounts[$metricName] = ($overallWorkTypeCounts[$metricName] ?? 0) + (int) $count;
                }
            }

            $totalAllDays += $dayTotal;

            $days[] = [
                'date' => (string) $day,
                'date_formatted' => $dayFormatted,
                'day_name' => $dayName,
                'total' => $dayTotal,
                'items' => $dayItems,
            ];
        }

        $overallBreakdown = [];
        foreach ($overallWorkTypeCounts as $label => $cnt) {
            $overallBreakdown[] = [
                'label' => $label,
                'count' => $cnt,
            ];
        }

        $titleStr = Date::dmY($startDateStr);
        if ($startDateStr !== $endDateStr) {
            $titleStr .= ' – ' . Date::dmY($endDateStr);
        }

        reportsApiResponse([
            'success' => true,
            'pId' => $pId,
            'title' => $titleStr . ' İşlem Dökümü',
            'total_operations' => $totalAllDays,
            'total_days' => count($days),
            'work_types_summary' => $overallBreakdown,
            'days' => $days,
        ]);
    } catch (Throwable $e) {
        error_log('reports detail error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        reportsApiResponse(['success' => false, 'message' => 'Personel detayları alınırken bir hata oluştu.'], 500);
    }
}

reportsApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem.'], 400);
