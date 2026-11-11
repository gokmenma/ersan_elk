<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Helper;
use App\Model\EndeksOkumaModel;
use App\Model\KacakKontrolModel;
use App\Model\Model;
use App\Model\PersonelModel;
use App\Model\PuantajModel;
use App\Model\TanimlamalarModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$departman = $personel->departman ?? '';
$gorev = $personel->gorev ?? '';

$isSayacSokmeTakma = (stripos($departman, 'Sayaç Sökme Takma') !== false);
$isEndeksOkuma = (stripos($departman, 'Endeks Okuma') !== false);
$isKacakKontrol = (stripos($departman, 'Kaçak Kontrol') !== false || stripos($departman, 'Kaçak') !== false);
$isKesmeAcma = (stripos($departman, 'Kesme-Açma') !== false || stripos($departman, 'Kesme Açma') !== false);
$isBuro = (stripos($departman, 'BÜRO') !== false || stripos($departman, 'Büro') !== false);

$PersonelModel = new PersonelModel();

// Okuma Ekip Şefi mi?
$isOkumaSefi = false;
if ($isEndeksOkuma && stripos($gorev, 'Şef') !== false) {
    try {
        $ekipGecmisi = $PersonelModel->getEkipGecmisi($personelId);
        foreach ($ekipGecmisi as $g) {
            if (((int) ($g->ekip_sefi_mi ?? 0)) === 1 && (empty($g->bitis_tarihi) || $g->bitis_tarihi >= date('Y-m-d'))) {
                $isOkumaSefi = true;
                break;
            }
        }
    } catch (\Throwable $e) {
        error_log('Mobil work.php ekip gecmisi hatasi: ' . $e->getMessage());
    }
}

// Sekme erişilebilirlik kontrolü
$hasIsler = true;
$hasEndeks = true;
$hasSayac = true;
$hasKacak = true;

if ($isKacakKontrol) {
    try {
        $db = (new Model('yapilan_isler'))->getDb();

        $stmt = $db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$personelId, $firmaId]);
        $hasIsler = ((int) $stmt->fetchColumn() > 0);

        $stmt = $db->prepare("SELECT COUNT(*) FROM endeks_okuma WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$personelId, $firmaId]);
        $hasEndeks = ((int) $stmt->fetchColumn() > 0);

        $stmt = $db->prepare("SELECT COUNT(*) FROM sayac_degisim WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$personelId, $firmaId]);
        $hasSayac = ((int) $stmt->fetchColumn() > 0);

        $stmt = $db->prepare("SELECT COUNT(*) FROM kacak_kontrol WHERE (FIND_IN_SET(?, personel_ids) OR FIND_IN_SET(?, personel_ids)) AND firma_id = ? AND " . KacakKontrolModel::raporKosulu());
        $stmt->execute([$personelId, $personelId, $firmaId]);
        $hasKacak = ((int) $stmt->fetchColumn() > 0);
    } catch (\Throwable $e) {
        error_log('Mobil work.php kacak personel kontrol hatasi: ' . $e->getMessage());
    }
}

// Kullanılabilir sekmeler
$availableTabs = [];
if ($isKacakKontrol) {
    if ($hasKacak) $availableTabs[] = ['key' => 'kacak', 'label' => 'Kaçak Kontrol'];
    if ($hasIsler) $availableTabs[] = ['key' => 'kesme', 'label' => 'Kesme-Açma'];
    if ($hasEndeks) $availableTabs[] = ['key' => 'endeks', 'label' => 'Endeks Okuma'];
    if ($hasSayac) $availableTabs[] = ['key' => 'sokme_takma', 'label' => 'Sayaç Değişimi'];
} elseif ($isSayacSokmeTakma) {
    $availableTabs[] = ['key' => 'sokme_takma', 'label' => 'Sayaç Değişimi'];
    $availableTabs[] = ['key' => 'kesme', 'label' => 'Kesme-Açma'];
} else {
    $availableTabs[] = ['key' => 'kesme', 'label' => 'Kesme-Açma'];
    $availableTabs[] = ['key' => 'endeks', 'label' => 'Endeks Okuma'];
}

// Varsayılan sekme
if ($isKacakKontrol && $hasKacak) {
    $defaultTab = 'kacak';
} elseif ($isSayacSokmeTakma) {
    $defaultTab = 'sokme_takma';
} else {
    $defaultTab = $isEndeksOkuma ? 'endeks' : 'kesme';
}

$input = mobile_input();
$action = (string) ($input['action'] ?? 'get_data');

try {
    switch ($action) {
        case 'defter_list': {
            $startDate = (string) ($input['start_date'] ?? date('Y-m-01'));
            $endDate = (string) ($input['end_date'] ?? date('Y-m-d'));

            $TanimlamalarModel = new TanimlamalarModel();
            $db = $TanimlamalarModel->getDb();

            $sql = "SELECT e.defter as kod, e.bolge,
                        (SELECT tt.defter_mahalle 
                         FROM tanimlamalar tt 
                         WHERE tt.tur_adi = e.defter 
                           AND tt.defter_bolge = e.bolge 
                           AND tt.firma_id = e.firma_id 
                           AND tt.grup = 'defter_kodu' 
                           AND tt.silinme_tarihi IS NULL 
                           AND (tt.baslangic_tarihi IS NULL OR tt.baslangic_tarihi <= ?)
                           AND (tt.bitis_tarihi IS NULL OR tt.bitis_tarihi >= ?)
                         LIMIT 1) as isim,
                        (SELECT tt.id 
                         FROM tanimlamalar tt 
                         WHERE tt.tur_adi = e.defter 
                           AND tt.defter_bolge = e.bolge 
                           AND tt.firma_id = e.firma_id 
                           AND tt.grup = 'defter_kodu' 
                           AND tt.silinme_tarihi IS NULL 
                           AND (tt.baslangic_tarihi IS NULL OR tt.baslangic_tarihi <= ?)
                           AND (tt.bitis_tarihi IS NULL OR tt.bitis_tarihi >= ?)
                         LIMIT 1) as id
                    FROM (
                        SELECT bolge, defter, firma_id 
                        FROM endeks_okuma 
                        WHERE personel_id = ? 
                          AND firma_id = ?
                          AND silinme_tarihi IS NULL 
                          AND okunan_abone_sayisi > 0
                          AND tarih BETWEEN ? AND ?
                        GROUP BY bolge, defter
                    ) e
                    ORDER BY e.bolge ASC, CAST(e.defter AS UNSIGNED) ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$endDate, $startDate, $endDate, $startDate, $personelId, $firmaId, $startDate, $endDate]);
            $defterler = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($defterler as &$d) {
                if (empty($d['id'])) {
                    $d['id'] = 'COMP|' . $d['bolge'] . '|' . $d['kod'];
                }
            }

            mobile_ok(['defterler' => $defterler]);
            break;
        }

        case 'defter_query': {
            $defterId = (string) ($input['defter_id'] ?? '');
            $startDate = (string) ($input['start_date'] ?? date('Y-m-01'));
            $endDate = (string) ($input['end_date'] ?? date('Y-m-d'));

            if (empty($defterId) || empty($startDate) || empty($endDate)) {
                mobile_fail('Lütfen defter ve tarih aralığını belirtin.');
            }

            $tur_adi = '';
            $defter_bolge = '';
            $defter_mahalle = '';
            $total_subscribers = 0;

            if (strpos($defterId, 'COMP|') === 0) {
                $parts = explode('|', $defterId);
                $defter_bolge = $parts[1] ?? '';
                $tur_adi = $parts[2] ?? '';
                $defter_mahalle = $tur_adi;
            } else {
                $TanimlamalarModel = new TanimlamalarModel();
                $defterInfo = $TanimlamalarModel->find($defterId);
                if (!$defterInfo) {
                    mobile_fail('Defter tanımı bulunamadı.');
                }
                $tur_adi = (string) $defterInfo->tur_adi;
                $defter_bolge = (string) $defterInfo->defter_bolge;
                $defter_mahalle = (string) ($defterInfo->defter_mahalle ?: $defterInfo->tur_adi);
                $total_subscribers = (int) ($defterInfo->defter_abone_sayisi ?: 0);
            }

            $db = (new Model('endeks_okuma'))->getDb();

            $sql = "SELECT tarih, SUM(okunan_abone_sayisi) as count 
                    FROM endeks_okuma 
                    WHERE defter = ? AND bolge = ? AND tarih BETWEEN ? AND ? AND firma_id = ? AND silinme_tarihi IS NULL
                    GROUP BY tarih
                    ORDER BY tarih DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tur_adi, $defter_bolge, $startDate, $endDate, $firmaId]);
            $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sqlStatus = "SELECT sayac_durum as status, SUM(okunan_abone_sayisi) as count 
                          FROM endeks_okuma 
                          WHERE defter = ? AND bolge = ? AND tarih BETWEEN ? AND ? AND firma_id = ? AND silinme_tarihi IS NULL
                          GROUP BY sayac_durum
                          ORDER BY count DESC";
            $stmtStatus = $db->prepare($sqlStatus);
            $stmtStatus->execute([$tur_adi, $defter_bolge, $startDate, $endDate, $firmaId]);
            $statusSummary = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

            mobile_ok([
                'result' => [
                    'name' => $defter_mahalle,
                    'code' => $tur_adi,
                    'total_subscribers' => $total_subscribers,
                    'breakdown' => $breakdown,
                    'status_summary' => $statusSummary,
                ],
            ]);
            break;
        }

        case 'defter_daily_detail': {
            $defterId = (string) ($input['defter_id'] ?? '');
            $tarih = (string) ($input['tarih'] ?? '');

            if (empty($defterId) || empty($tarih)) {
                mobile_fail('Eksik parametre.');
            }

            $tur_adi = '';
            $defter_bolge = '';
            $defter_mahalle = '';

            if (strpos($defterId, 'COMP|') === 0) {
                $parts = explode('|', $defterId);
                $defter_bolge = $parts[1] ?? '';
                $tur_adi = $parts[2] ?? '';
                $defter_mahalle = $tur_adi;
            } else {
                $TanimlamalarModel = new TanimlamalarModel();
                $defterInfo = $TanimlamalarModel->find($defterId);
                if (!$defterInfo) {
                    mobile_fail('Defter tanımı bulunamadı.');
                }
                $tur_adi = (string) $defterInfo->tur_adi;
                $defter_bolge = (string) $defterInfo->defter_bolge;
                $defter_mahalle = (string) ($defterInfo->defter_mahalle ?: $defterInfo->tur_adi);
            }

            $db = (new Model('endeks_okuma'))->getDb();

            $sql = "SELECT sayac_durum as status, SUM(okunan_abone_sayisi) as count 
                    FROM endeks_okuma 
                    WHERE defter = ? AND bolge = ? AND tarih = ? AND firma_id = ? AND silinme_tarihi IS NULL
                    GROUP BY sayac_durum
                    ORDER BY count DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tur_adi, $defter_bolge, $tarih, $firmaId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

            mobile_ok([
                'detail' => [
                    'tarih' => $tarih,
                    'defter_adi' => $defter_mahalle,
                    'defter_kodu' => $tur_adi,
                    'details' => $details,
                ],
            ]);
            break;
        }

        case 'get_data':
        default: {
            $selectedTab = (string) ($input['tab'] ?? $defaultTab);
            $startDate = (string) ($input['start_date'] ?? date('Y-m-01'));
            $endDate = (string) ($input['end_date'] ?? date('Y-m-d'));
            $workType = (string) ($input['work_type'] ?? '');
            $workResult = (string) ($input['work_result'] ?? '');

            // Okuma Şefleri kısıt kontrolü (1 Nisan 2026)
            $cutoffDate = '2026-04-01';
            $showCutoffWarning = ($isOkumaSefi && $selectedTab === 'kesme');
            if ($isOkumaSefi && $selectedTab === 'kesme') {
                if (empty($startDate) || $startDate < $cutoffDate) {
                    $startDate = $cutoffDate;
                }
            }

            $items = [];
            $stats = [
                'toplam' => 0,
                'sonuclanan' => 0,
                'acik' => 0,
                'okunan_abone' => 0,
            ];

            // Genel Performans Özeti (KPI sayaçları için: Bugün ve Bu Ay)
            $today = date('Y-m-d');
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');

            $db = (new Model('tanimlamalar'))->getDb();
            $adminFilter = "AND tn.is_turu_ucret > 0 AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != ''";
            $cutoffFilter = ($isOkumaSefi) ? " AND (tn.rapor_sekmesi != 'kesme' OR t.tarih >= '$cutoffDate')" : "";

            // Günlük yapılan işler
            $sqlDaily = "SELECT SUM(t.sonuclanmis) as toplam 
                         FROM yapilan_isler t
                         LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                         WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih = ? $adminFilter $cutoffFilter";
            $stmt = $db->prepare($sqlDaily);
            $stmt->execute([$personelId, $firmaId, $today]);
            $dailyCount = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Günlük Sayaç Değişim
            $sqlSayacDaily = "SELECT SUM(is_sayisi) as toplam FROM sayac_degisim WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL AND tarih = ?";
            $stmt = $db->prepare($sqlSayacDaily);
            $stmt->execute([$personelId, $firmaId, $today]);
            $dailyCount += (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Aylık Yapılan İşler
            $sqlMonthly = "SELECT SUM(t.sonuclanmis) as toplam 
                           FROM yapilan_isler t
                           LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                           WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ? $adminFilter $cutoffFilter";
            $stmt = $db->prepare($sqlMonthly);
            $stmt->execute([$personelId, $firmaId, $startOfMonth, $endOfMonth]);
            $monthlyCount = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Aylık Sayaç Değişim
            $sqlSayacMonthly = "SELECT SUM(is_sayisi) as toplam FROM sayac_degisim WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL AND tarih BETWEEN ? AND ?";
            $stmt = $db->prepare($sqlSayacMonthly);
            $stmt->execute([$personelId, $firmaId, $startOfMonth, $endOfMonth]);
            $monthlyCount += (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Günlük & Aylık Endeks Okuma
            $dailyEndeks = 0;
            $monthlyEndeks = 0;
            if (!$isSayacSokmeTakma) {
                $sqlEndeksDaily = "SELECT SUM(t.okunan_abone_sayisi) as toplam 
                                   FROM endeks_okuma t 
                                   LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                                   WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih = ?
                                     AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";
                $stmt = $db->prepare($sqlEndeksDaily);
                $stmt->execute([$personelId, $firmaId, $today]);
                $dailyEndeks = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

                $sqlEndeksMonthly = "SELECT SUM(t.okunan_abone_sayisi) as toplam 
                                     FROM endeks_okuma t 
                                     LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                                     WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ?
                                       AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";
                $stmt = $db->prepare($sqlEndeksMonthly);
                $stmt->execute([$personelId, $firmaId, $startOfMonth, $endOfMonth]);
                $monthlyEndeks = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);
            }

            // Filtre Seçenekleri
            $workTypes = [];
            $workResults = [];

            if ($selectedTab === 'kesme') {
                $PuantajModel = new PuantajModel();
                $types = $PuantajModel->getWorkTypes($personelId);
                $results = $PuantajModel->getWorkResults($personelId, $workType ?: null);

                $rawItems = $PuantajModel->getFiltered($startDate, $endDate, $personelId, $workType, $workResult, 'kesme');

                $totalSonuclanan = 0;
                $filteredSonuclanan = 0;
                $totalAcik = 0;

                foreach ($rawItems as $row) {
                    $sonuclanan = (int) ($row->sonuclanmis ?? 0);
                    $acik = (int) ($row->acik_olanlar ?? 0);
                    $totalSonuclanan += $sonuclanan;
                    $totalAcik += $acik;

                    if (!empty($row->rapor_sekmesi) && ($row->is_turu_ucret > 0)) {
                        $filteredSonuclanan += $sonuclanan;
                    }

                    $items[] = [
                        'id' => (int) ($row->id ?? 0),
                        'tarih' => $row->tarih ?? '',
                        'is_emri_tipi' => $row->is_emri_tipi ?? 'Genel İş',
                        'is_emri_sonucu' => $row->is_emri_sonucu ?? '-',
                        'sonuclanmis' => $sonuclanan,
                        'acik_olanlar' => $acik,
                        'ekip_kodu' => $row->ekip_kodu ?? '',
                        'rapor_sekmesi' => 'kesme',
                        'aciklama' => $row->aciklama ?? '',
                    ];
                }

                $stats['toplam'] = $filteredSonuclanan;
                $stats['sonuclanan'] = $totalSonuclanan;
                $stats['acik'] = $totalAcik;
                $workTypes = array_values($types);
                $workResults = array_values($results);

            } elseif ($selectedTab === 'endeks') {
                $EndeksOkumaModel = new EndeksOkumaModel();
                $rawItems = $EndeksOkumaModel->getFiltered($startDate, $endDate, $personelId);

                $totalOkunan = 0;
                foreach ($rawItems as $row) {
                    $okunan = (int) ($row->okunan_abone_sayisi ?? 0);
                    $totalOkunan += $okunan;

                    $items[] = [
                        'id' => (int) ($row->id ?? 0),
                        'tarih' => $row->tarih ?? '',
                        'is_emri_tipi' => 'Endeks Okuma',
                        'is_emri_sonucu' => $row->sayac_durum ?? 'Okundu',
                        'sonuclanmis' => $okunan,
                        'acik_olanlar' => 0,
                        'ekip_kodu' => $row->ekip_kodu ?? ($row->ekip_adi ?? ''),
                        'defter' => $row->defter ?? '',
                        'bolge' => $row->bolge ?? '',
                        'rapor_sekmesi' => 'endeks',
                        'aciklama' => $row->aciklama ?? '',
                    ];
                }

                $stats['toplam'] = $totalOkunan;
                $stats['sonuclanan'] = $totalOkunan;
                $stats['okunan_abone'] = $totalOkunan;

            } elseif ($selectedTab === 'sokme_takma') {
                $dbSayac = (new Model('sayac_degisim'))->getDb();

                // Work types and results for Sayaç
                $stmt = $dbSayac->prepare("SELECT DISTINCT isemri_sebep FROM sayac_degisim WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL AND isemri_sebep IS NOT NULL AND isemri_sebep != ''");
                $stmt->execute([$personelId, $firmaId]);
                $workTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $sqlRes = "SELECT DISTINCT isemri_sonucu FROM sayac_degisim WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL AND isemri_sonucu IS NOT NULL AND isemri_sonucu != ''";
                $paramsRes = [$personelId, $firmaId];
                if ($workType) {
                    $sqlRes .= " AND isemri_sebep = ?";
                    $paramsRes[] = $workType;
                }
                $stmtRes = $dbSayac->prepare($sqlRes);
                $stmtRes->execute($paramsRes);
                $workResults = $stmtRes->fetchAll(PDO::FETCH_COLUMN);

                // Fetch items
                $sqlSayac = "SELECT MIN(id) as id, tarih, isemri_sebep as is_emri_tipi, isemri_sonucu as is_emri_sonucu, 
                                    SUM(is_sayisi) as sonuclanmis, 0 as acik_olanlar, ekip as ekip_kodu, 
                                    'sokme_takma' as rapor_sekmesi
                             FROM sayac_degisim 
                             WHERE personel_id = ? AND firma_id = ? AND silinme_tarihi IS NULL";
                $paramsSayac = [$personelId, $firmaId];

                if ($startDate) {
                    $sqlSayac .= " AND tarih >= ?";
                    $paramsSayac[] = $startDate;
                }
                if ($endDate) {
                    $sqlSayac .= " AND tarih <= ?";
                    $paramsSayac[] = $endDate;
                }
                if ($workType) {
                    $sqlSayac .= " AND isemri_sebep = ?";
                    $paramsSayac[] = $workType;
                }
                if ($workResult) {
                    $sqlSayac .= " AND isemri_sonucu = ?";
                    $paramsSayac[] = $workResult;
                }

                $sqlSayac .= " GROUP BY tarih, isemri_sebep, isemri_sonucu, ekip ORDER BY tarih DESC";
                $stmt = $dbSayac->prepare($sqlSayac);
                $stmt->execute($paramsSayac);
                $rawItems = $stmt->fetchAll(PDO::FETCH_OBJ);

                $totalSonuclanan = 0;
                foreach ($rawItems as $row) {
                    $sonuclanan = (int) ($row->sonuclanmis ?? 0);
                    $totalSonuclanan += $sonuclanan;

                    $items[] = [
                        'id' => (int) ($row->id ?? 0),
                        'tarih' => $row->tarih ?? '',
                        'is_emri_tipi' => $row->is_emri_tipi ?? 'Sayaç Değişim',
                        'is_emri_sonucu' => $row->is_emri_sonucu ?? '-',
                        'sonuclanmis' => $sonuclanan,
                        'acik_olanlar' => 0,
                        'ekip_kodu' => $row->ekip_kodu ?? '',
                        'rapor_sekmesi' => 'sokme_takma',
                        'aciklama' => '',
                    ];
                }

                $stats['toplam'] = $totalSonuclanan;
                $stats['sonuclanan'] = $totalSonuclanan;

            } elseif ($selectedTab === 'kacak') {
                $dbKacak = (new Model('kacak_kontrol'))->getDb();
                $sqlKacak = "SELECT id, tarih, ekip_adi, sayi, aciklama, olusturma_tarihi
                             FROM kacak_kontrol
                             WHERE (FIND_IN_SET(?, personel_ids) OR FIND_IN_SET(?, personel_ids))
                               AND firma_id = ?
                               AND " . KacakKontrolModel::raporKosulu();

                $paramsKacak = [$personelId, $personelId, $firmaId];

                if (!empty($startDate)) {
                    $sqlKacak .= " AND tarih >= ?";
                    $paramsKacak[] = $startDate;
                }
                if (!empty($endDate)) {
                    $sqlKacak .= " AND tarih <= ?";
                    $paramsKacak[] = $endDate;
                }

                $sqlKacak .= " ORDER BY tarih DESC, olusturma_tarihi DESC";
                $stmt = $dbKacak->prepare($sqlKacak);
                $stmt->execute($paramsKacak);
                $rawItems = $stmt->fetchAll(PDO::FETCH_OBJ);

                $totalKacak = 0;
                foreach ($rawItems as $row) {
                    $sayi = (int) ($row->sayi ?? 0);
                    $totalKacak += $sayi;

                    $items[] = [
                        'id' => (int) ($row->id ?? 0),
                        'tarih' => $row->tarih ?? '',
                        'is_emri_tipi' => 'Kaçak Kontrol / Tutanak',
                        'is_emri_sonucu' => 'Tutanak Düzenlendi',
                        'sonuclanmis' => $sayi,
                        'acik_olanlar' => 0,
                        'ekip_kodu' => $row->ekip_adi ?? '',
                        'rapor_sekmesi' => 'kacak',
                        'aciklama' => $row->aciklama ?? '',
                    ];
                }

                $stats['toplam'] = $totalKacak;
                $stats['sonuclanan'] = $totalKacak;
            }

            $lastUpdateYapilanIsler = Helper::getLastUpdateDate('yapilan_isler');
            $lastUpdateEndeks = Helper::getLastUpdateDate('endeks_okuma');

            mobile_ok([
                'available_tabs' => $availableTabs,
                'current_tab' => $selectedTab,
                'default_tab' => $defaultTab,
                'is_okuma_sefi' => $isOkumaSefi,
                'show_cutoff_warning' => $showCutoffWarning,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'work_type' => $workType,
                    'work_result' => $workResult,
                    'work_types' => $workTypes,
                    'work_results' => $workResults,
                ],
                'summary_kpi' => [
                    'daily_jobs' => $dailyCount,
                    'monthly_jobs' => $monthlyCount,
                    'daily_endeks' => $dailyEndeks,
                    'monthly_endeks' => $monthlyEndeks,
                ],
                'stats' => $stats,
                'items' => $items,
                'last_updates' => [
                    'yapilan_isler' => $lastUpdateYapilanIsler,
                    'endeks' => $lastUpdateEndeks,
                ],
            ]);
            break;
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil work.php genel hata: ' . $e->getMessage());
    mobile_fail('İş takip verileri alınırken bir hata oluştu: ' . $e->getMessage(), 500);
}
