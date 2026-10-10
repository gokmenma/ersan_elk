<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class CariModel extends Model
{
    protected $table = 'cari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $search = $params['search']['value'];
        $orders = $params['order'];
        $columns = $params['columns'];

        $where = "c.silinme_tarihi IS NULL";
        $bindParams = [];

        $balance_filter = $params['balance_filter'] ?? 'all';
        if ($balance_filter === 'borclu') {
            $where .= " AND (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) < 0";
        } elseif ($balance_filter === 'alacakli') {
            $where .= " AND (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) > 0";
        }

        if (!empty($search)) {
            $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR c.vkn_tckn LIKE :search OR c.vergi_dairesi LIKE :search OR c.Telefon LIKE :search OR c.Email LIKE :search OR c.il LIKE :search OR c.ilce LIKE :search OR c.Adres LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Sütun Bazlı Arama
        if (isset($params['columns'])) {
            $colMap = [
                1 => 'c.CariAdi',
                2 => 'c.firma',
                3 => 'c.vkn_tckn',
                4 => 'c.Telefon',
                5 => "CONCAT_WS(' / ', c.il, c.ilce)",
                6 => '(SELECT IFNULL(ROUND(SUM(alacak) - SUM(borc), 2), 0) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL)'
            ];
            foreach ($params['columns'] as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = $column['search']['value'];
                    $paramName = "col_" . $i;

                    // Gelişmiş Filtre Ayrıştırıcı (mode:value) desteği
                    if (strpos($val, ':') !== false) {
                        list($mode, $filterVal) = explode(':', $val, 2);
                        
                        // Birden fazla değer desteği (multi-select)
                        $vals = explode('|', $filterVal);
                        $filterVal = $vals[0];

                        // Sayısal değerler için temizleme
                        $cleanNumVal = str_replace(['.', ','], ['', '.'], $filterVal);
                        if (!is_numeric($cleanNumVal)) {
                            $cleanNumVal = (float)$filterVal;
                        }

                        switch ($mode) {
                            case 'multi':
                                if (!empty($vals)) {
                                    $multiConditions = [];
                                    foreach ($vals as $vIdx => $v) {
                                        $vParam = $paramName . "_m_" . $vIdx;
                                        $multiConditions[] = "$field LIKE :$vParam";
                                        $bindParams[$vParam] = "%$v%";
                                    }
                                    $where .= " AND (" . implode(" OR ", $multiConditions) . ")";
                                }
                                break;
                            case 'greater_than':
                                $where .= " AND $field > :$paramName";
                                $bindParams[$paramName] = (float)$cleanNumVal;
                                break;
                            case 'less_than':
                                $where .= " AND $field < :$paramName";
                                $bindParams[$paramName] = (float)$cleanNumVal;
                                break;
                            case 'greater_equal':
                                $where .= " AND $field >= :$paramName";
                                $bindParams[$paramName] = (float)$cleanNumVal;
                                break;
                            case 'less_equal':
                                $where .= " AND $field <= :$paramName";
                                $bindParams[$paramName] = (float)$cleanNumVal;
                                break;
                            case 'contains':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal%";
                                break;
                            case 'not_contains':
                                $where .= " AND $field NOT LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal%";
                                break;
                            case 'equals':
                                if ($i === 6 && is_numeric($cleanNumVal)) {
                                    $where .= " AND $field = :$paramName";
                                    $bindParams[$paramName] = (float)$cleanNumVal;
                                } else {
                                    $where .= " AND $field = :$paramName";
                                    $bindParams[$paramName] = $filterVal;
                                }
                                break;
                            case 'not_equals':
                                if ($i === 6 && is_numeric($cleanNumVal)) {
                                    $where .= " AND $field != :$paramName";
                                    $bindParams[$paramName] = (float)$cleanNumVal;
                                } else {
                                    $where .= " AND $field != :$paramName";
                                    $bindParams[$paramName] = $filterVal;
                                }
                                break;
                            case 'starts_with':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "$filterVal%";
                                break;
                            case 'ends_with':
                                $where .= " AND $field LIKE :$paramName";
                                $bindParams[$paramName] = "%$filterVal";
                                break;
                            case 'null':
                                $where .= " AND ($field IS NULL OR $field = '' OR $field = 0)";
                                break;
                            case 'not_null':
                                $where .= " AND ($field IS NOT NULL AND $field != '' AND $field != 0)";
                                break;
                        }
                    } else {
                        $where .= " AND $field LIKE :$paramName";
                        $bindParams[$paramName] = "%$val%";
                    }
                }
            }
        }

        // Toplam Kayıt Sayısı
        $totalCount = $this->db->query("SELECT COUNT(*) FROM $this->table WHERE silinme_tarihi IS NULL")->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $filteredSql = "SELECT COUNT(*) FROM $this->table c WHERE $where";
        $stmtFiltered = $this->db->prepare($filteredSql);
        $stmtFiltered->execute($bindParams);
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY c.CariAdi ASC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?: $columns[$colIdx]['name'];
                
                if ($colName == "bakiye") {
                    $orderArr[] = "bakiye $colDir";
                } elseif ($colName && $colName != "actions") {
                    $orderArr[] = "c.$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        // Ana Sorgu (Bakiye ile)
        $sql = "SELECT c.*, 
                (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
                FROM $this->table c 
                WHERE $where $orderQuery LIMIT :start, :length";
        
        $stmt = $this->db->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue('length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalCount),
            "recordsFiltered" => intval($filteredCount),
            "data" => $data
        ];
    }

    public function summary()
    {
        $sql = "SELECT 
                COUNT(DISTINCT c.id) as toplam_cari,
                ROUND(COALESCE(SUM(ch.borc), 0), 2) as toplam_borc,
                ROUND(COALESCE(SUM(ch.alacak), 0), 2) as toplam_alacak,
                ROUND(COALESCE(SUM(ch.alacak), 0) - COALESCE(SUM(ch.borc), 0), 2) as genel_bakiye
                FROM cari c
                LEFT JOIN cari_hareketleri ch ON ch.cari_id = c.id AND ch.silinme_tarihi IS NULL
                WHERE c.silinme_tarihi IS NULL";
        $summary = $this->db->query($sql)->fetch(PDO::FETCH_OBJ);

        $countsSql = "SELECT 
            SUM(CASE WHEN bakiye < 0 THEN 1 ELSE 0 END) as borclu_cari_sayisi,
            SUM(CASE WHEN bakiye > 0 THEN 1 ELSE 0 END) as alacakli_cari_sayisi
            FROM (
                SELECT c.id, (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
                FROM cari c WHERE c.silinme_tarihi IS NULL
            ) t";
        $counts = $this->db->query($countsSql)->fetch(PDO::FETCH_OBJ);

        if ($summary) {
            $summary->borclu_cari_sayisi = (int)($counts->borclu_cari_sayisi ?? 0);
            $summary->alacakli_cari_sayisi = (int)($counts->alacakli_cari_sayisi ?? 0);
        }

        return $summary;
    }

    /**
     * Sütun için benzersiz değerleri getirir (Filtreleme için)
     */
    public function getUniqueValues($column, $params = []): array
    {
        $allowed = [
            'CariAdi' => 'c.CariAdi',
            'FirmaUnvan' => 'c.FirmaUnvan',
            'il' => 'c.il',
            'ilce' => 'c.ilce',
            'durum' => 'c.durum',
        ];
        $field = $allowed[$column] ?? (preg_match('/^[a-zA-Z0-9_]+$/', (string)$column) ? "c.$column" : null);
        if (!$field) return [];

        $firma_id = $_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? null;
        $where = "c.silinme_tarihi IS NULL AND $field IS NOT NULL AND $field != ''";
        $bind = [];
        if ($firma_id) {
            $where .= " AND c.firma_id = :firma_id";
            $bind['firma_id'] = $firma_id;
        }

        try {
            $stmt = $this->db->prepare("SELECT DISTINCT $field as val FROM cari c WHERE $where ORDER BY val ASC");
            $stmt->execute($bind);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            error_log("CariModel::getUniqueValues Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Faturadaki VKN/TCKN veya Ünvan bilgisine göre cari tablosunda eşleşen kaydı bulur.
     */
    public function findMatchingCari(?string $vkn, ?string $unvan): ?object
    {
        $vknClean = preg_replace('/[^0-9]/', '', (string)$vkn);
        if (!empty($vknClean) && strlen($vknClean) >= 10) {
            $stmt = $this->db->prepare("SELECT * FROM cari WHERE vkn_tckn = :vkn AND silinme_tarihi IS NULL LIMIT 1");
            $stmt->execute(['vkn' => $vknClean]);
            $cari = $stmt->fetch(PDO::FETCH_OBJ);
            if ($cari) {
                return $cari;
            }
        }

        $unvan = trim((string)$unvan);
        if ($unvan === '') {
            return null;
        }

        // Birebir tam eşleşme
        $stmt = $this->db->prepare("SELECT * FROM cari WHERE (firma = :unvan OR CariAdi = :unvan) AND silinme_tarihi IS NULL LIMIT 1");
        $stmt->execute(['unvan' => $unvan]);
        $cari = $stmt->fetch(PDO::FETCH_OBJ);
        if ($cari) {
            return $cari;
        }

        // Normalizasyon ile akıllı eşleşme
        $normUnvan = $this->normalizeSearchText($unvan);
        if (mb_strlen($normUnvan, 'UTF-8') < 3) {
            return null;
        }

        $cariler = $this->db->query("SELECT * FROM cari WHERE silinme_tarihi IS NULL")->fetchAll(PDO::FETCH_OBJ);
        foreach ($cariler as $c) {
            $normFirma = $this->normalizeSearchText($c->firma ?? '');
            $normCariAdi = $this->normalizeSearchText($c->CariAdi ?? '');

            if ($normFirma !== '' && mb_strlen($normFirma, 'UTF-8') >= 4) {
                if (strpos($normUnvan, $normFirma) !== false || strpos($normFirma, $normUnvan) !== false) {
                    return $c;
                }
            }
            if ($normCariAdi !== '' && mb_strlen($normCariAdi, 'UTF-8') >= 4) {
                if (strpos($normUnvan, $normCariAdi) !== false || strpos($normCariAdi, $normUnvan) !== false) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * Arama ve eşleştirmeler için Türkçe karakterleri normalize eder.
     */
    public function normalizeSearchText(string $str): string
    {
        $str = mb_strtoupper($str, 'UTF-8');
        $str = str_replace(['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['I', 'I', 'S', 'G', 'U', 'O', 'C'], $str);
        $str = preg_replace('/[^A-Z0-9]/', '', $str);
        return $str;
    }

    /**
     * Fatura bilgilerinden otomatik yeni cari kart oluşturur.
     *
     * @param object $fatura
     * @param int|null $userId
     * @return int Oluşturulan cari ID
     */
    public function createFromFatura(object $fatura, ?int $userId = null): int
    {
        $unvan = trim((string)($fatura->alici_unvan ?? ''));
        if ($unvan === '') {
            $unvan = 'Fatura Carisi (' . ($fatura->fatura_no ?: 'Belgesiz') . ')';
        }

        $vknClean = preg_replace('/[^0-9]/', '', (string)($fatura->alici_vkn_tckn ?? ''));
        $aliciTuru = (strlen($vknClean) === 11) ? 'BIREYSEL' : 'KURUMSAL';
        $belgeTuru = (strtoupper(trim((string)($fatura->belge_turu ?? ''))) === 'EFATURA') ? 'EFATURA' : 'EARSIV';

        $sql = "
            INSERT INTO cari (
                CariAdi, firma, vkn_tckn, vergi_dairesi, alici_turu, belge_turu,
                posta_kutusu, Telefon, Email, ulke, il, ilce, Adres,
                kayit_tarihi, Aktif
            ) VALUES (
                :CariAdi, :firma, :vkn_tckn, :vergi_dairesi, :alici_turu, :belge_turu,
                :posta_kutusu, :Telefon, :Email, :ulke, :il, :ilce, :Adres,
                NOW(), 1
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'CariAdi'       => $unvan,
            'firma'         => $unvan,
            'vkn_tckn'      => !empty($vknClean) ? $vknClean : null,
            'vergi_dairesi' => !empty($fatura->alici_vergi_dairesi) ? trim((string)$fatura->alici_vergi_dairesi) : null,
            'alici_turu'    => $aliciTuru,
            'belge_turu'    => $belgeTuru,
            'posta_kutusu'  => !empty($fatura->alici_posta_kutusu) ? trim((string)$fatura->alici_posta_kutusu) : null,
            'Telefon'       => !empty($fatura->alici_telefon) ? trim((string)$fatura->alici_telefon) : null,
            'Email'         => !empty($fatura->alici_eposta) ? trim((string)$fatura->alici_eposta) : null,
            'ulke'          => !empty($fatura->alici_ulke) ? trim((string)$fatura->alici_ulke) : 'Türkiye',
            'il'            => !empty($fatura->alici_il) ? trim((string)$fatura->alici_il) : null,
            'ilce'          => !empty($fatura->alici_ilce) ? trim((string)$fatura->alici_ilce) : null,
            'Adres'         => !empty($fatura->alici_adres) ? trim((string)$fatura->alici_adres) : null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Cari Finansal Dashboard İstatistiklerini ve Grafik Verilerini Döndürür
     */
    public function getDashboardStats(?string $period = 'all', ?string $startDate = null, ?string $endDate = null): array
    {
        $now = date('Y-m-d H:i:s');
        $donemFilterActive = false;
        $periodLabel = 'Tüm Zamanlar';
        $pStart = null;
        $pEnd = null;

        if ($period === 'bu_ay') {
            $pStart = date('Y-m-01 00:00:00');
            $pEnd = date('Y-m-t 23:59:59');
            $periodLabel = 'Bu Ay (' . date('m/Y') . ')';
            $donemFilterActive = true;
        } elseif ($period === 'son_3_ay') {
            $pStart = date('Y-m-d 00:00:00', strtotime('-3 months'));
            $pEnd = $now;
            $periodLabel = 'Son 3 Ay';
            $donemFilterActive = true;
        } elseif ($period === 'son_6_ay') {
            $pStart = date('Y-m-d 00:00:00', strtotime('-6 months'));
            $pEnd = $now;
            $periodLabel = 'Son 6 Ay';
            $donemFilterActive = true;
        } elseif ($period === 'bu_yil') {
            $pStart = date('Y-01-01 00:00:00');
            $pEnd = date('Y-12-31 23:59:59');
            $periodLabel = 'Bu Yıl (' . date('Y') . ')';
            $donemFilterActive = true;
        } elseif ($period === 'son_12_ay') {
            $pStart = date('Y-m-d 00:00:00', strtotime('-12 months'));
            $pEnd = $now;
            $periodLabel = 'Son 1 Yıl';
            $donemFilterActive = true;
        } elseif ($period === 'custom' && !empty($startDate) && !empty($endDate)) {
            $pStart = date('Y-m-d 00:00:00', strtotime($startDate));
            $pEnd = date('Y-m-d 23:59:59', strtotime($endDate));
            $periodLabel = date('d.m.Y', strtotime($pStart)) . ' - ' . date('d.m.Y', strtotime($pEnd));
            $donemFilterActive = true;
        }

        // 1. Genel Cari Sayıları ve Net Bakiyeler (Mevcut Durum)
        $overallSql = "SELECT 
                COUNT(DISTINCT c.id) as toplam_cari,
                COUNT(DISTINCT CASE WHEN c.Aktif = 1 THEN c.id END) as aktif_cari_sayisi,
                ROUND(COALESCE(SUM(ch.borc), 0), 2) as genel_toplam_borc,
                ROUND(COALESCE(SUM(ch.alacak), 0), 2) as genel_toplam_alacak,
                ROUND(COALESCE(SUM(ch.alacak), 0) - COALESCE(SUM(ch.borc), 0), 2) as genel_net_bakiye,
                COUNT(DISTINCT ch.id) as genel_islem_sayisi
            FROM cari c
            LEFT JOIN cari_hareketleri ch ON ch.cari_id = c.id AND ch.silinme_tarihi IS NULL
            WHERE c.silinme_tarihi IS NULL";
        $overall = $this->db->query($overallSql)->fetch(PDO::FETCH_OBJ);

        // 2. Cari Bakiye Dağılımı (Alacaklı vs Borçlu vs Dengede Sayıları ve Kümülatif Bakiyeleri)
        $countsSql = "SELECT 
                SUM(CASE WHEN bakiye > 0 THEN 1 ELSE 0 END) as alacakli_sayisi,
                SUM(CASE WHEN bakiye < 0 THEN 1 ELSE 0 END) as borclu_sayisi,
                SUM(CASE WHEN bakiye = 0 OR bakiye IS NULL THEN 1 ELSE 0 END) as dengede_sayisi,
                ROUND(COALESCE(SUM(CASE WHEN bakiye > 0 THEN bakiye ELSE 0 END), 0), 2) as toplam_alacak_bakiye,
                ROUND(COALESCE(SUM(CASE WHEN bakiye < 0 THEN ABS(bakiye) ELSE 0 END), 0), 2) as toplam_borc_bakiye
            FROM (
                SELECT c.id, (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
                FROM cari c WHERE c.silinme_tarihi IS NULL
            ) t";
        $counts = $this->db->query($countsSql)->fetch(PDO::FETCH_OBJ);

        // 3. Dönem İçi Hareket İstatistikleri (Filtreye göre)
        if ($donemFilterActive && $pStart && $pEnd) {
            $donemSql = "SELECT 
                    ROUND(COALESCE(SUM(borc), 0), 2) as donem_borc,
                    ROUND(COALESCE(SUM(alacak), 0), 2) as donem_alacak,
                    ROUND(COALESCE(SUM(alacak), 0) - COALESCE(SUM(borc), 0), 2) as donem_net,
                    COUNT(id) as donem_islem_sayisi
                FROM cari_hareketleri
                WHERE silinme_tarihi IS NULL AND islem_tarihi BETWEEN :pStart AND :pEnd";
            $stmtDonem = $this->db->prepare($donemSql);
            $stmtDonem->execute(['pStart' => $pStart, 'pEnd' => $pEnd]);
            $donemStats = $stmtDonem->fetch(PDO::FETCH_OBJ);
        } else {
            $donemStats = (object)[
                'donem_borc' => $overall->genel_toplam_borc ?? 0,
                'donem_alacak' => $overall->genel_toplam_alacak ?? 0,
                'donem_net' => $overall->genel_net_bakiye ?? 0,
                'donem_islem_sayisi' => $overall->genel_islem_sayisi ?? 0,
            ];
        }

        // 4. En Çok Alacaklı Olduğumuz İlk 5 Cari (Bizim Paramız Olanlar - Top Alacaklılar)
        $topAlacakSql = "SELECT c.id, c.CariAdi, c.firma, c.vkn_tckn, c.Telefon, c.il, c.ilce,
                (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye,
                (SELECT MAX(islem_tarihi) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as son_islem_tarihi,
                (SELECT COUNT(*) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as islem_sayisi
            FROM cari c
            WHERE c.silinme_tarihi IS NULL
            HAVING bakiye > 0
            ORDER BY bakiye DESC
            LIMIT 5";
        $topAlacaklilar = $this->db->query($topAlacakSql)->fetchAll(PDO::FETCH_OBJ);

        // 5. En Çok Borçlu Olduğumuz İlk 5 Cari (Bizim Borcumuz Olanlar - Top Borçlular)
        $topBorcSql = "SELECT c.id, c.CariAdi, c.firma, c.vkn_tckn, c.Telefon, c.il, c.ilce,
                (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye,
                (SELECT MAX(islem_tarihi) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as son_islem_tarihi,
                (SELECT COUNT(*) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as islem_sayisi
            FROM cari c
            WHERE c.silinme_tarihi IS NULL
            HAVING bakiye < 0
            ORDER BY bakiye ASC
            LIMIT 5";
        $topBorclular = $this->db->query($topBorcSql)->fetchAll(PDO::FETCH_OBJ);

        // 6. Aylık Hareket Trendi (Son 12 Ay Zaman Serisi - ApexCharts)
        $trendStart = date('Y-m-01 00:00:00', strtotime('-11 months'));
        $trendSql = "SELECT 
                DATE_FORMAT(ch.islem_tarihi, '%Y-%m') as ay_kodu,
                ROUND(SUM(ch.alacak), 2) as alacak_toplam,
                ROUND(SUM(ch.borc), 2) as borc_toplam,
                ROUND(SUM(ch.alacak) - SUM(ch.borc), 2) as net_fark,
                COUNT(ch.id) as islem_adedi
            FROM cari_hareketleri ch
            JOIN cari c ON c.id = ch.cari_id AND c.silinme_tarihi IS NULL
            WHERE ch.silinme_tarihi IS NULL AND ch.islem_tarihi >= :trend_start
            GROUP BY DATE_FORMAT(ch.islem_tarihi, '%Y-%m')
            ORDER BY ay_kodu ASC";
        $stmtTrend = $this->db->prepare($trendSql);
        $stmtTrend->execute(['trend_start' => $trendStart]);
        $trendRows = $stmtTrend->fetchAll(PDO::FETCH_OBJ);

        $trendMap = [];
        foreach ($trendRows as $tr) {
            $trendMap[$tr->ay_kodu] = $tr;
        }

        $trAyKisa = [
            '01' => 'Oca', '02' => 'Şub', '03' => 'Mar', '04' => 'Nis',
            '05' => 'May', '06' => 'Haz', '07' => 'Tem', '08' => 'Ağu',
            '09' => 'Eyl', '10' => 'Eki', '11' => 'Kas', '12' => 'Ara'
        ];

        $categories = [];
        $alacakSeries = [];
        $borcSeries = [];
        $netSeries = [];

        // Son 12 ayın tümünü eksiksiz listele
        for ($i = 11; $i >= 0; $i--) {
            $mKey = date('Y-m', strtotime("-$i months"));
            $yearShort = date('y', strtotime("-$i months"));
            $monthNum = date('m', strtotime("-$i months"));
            $catLabel = ($trAyKisa[$monthNum] ?? $monthNum) . ' ' . $yearShort;

            $categories[] = $catLabel;
            if (isset($trendMap[$mKey])) {
                $alacakSeries[] = (float)$trendMap[$mKey]->alacak_toplam;
                $borcSeries[] = (float)$trendMap[$mKey]->borc_toplam;
                $netSeries[] = (float)$trendMap[$mKey]->net_fark;
            } else {
                $alacakSeries[] = 0.0;
                $borcSeries[] = 0.0;
                $netSeries[] = 0.0;
            }
        }

        // 7. Son 10 Hareket (Canlı Akış)
        $recentSql = "SELECT ch.*, c.CariAdi, c.firma, c.vkn_tckn,
                COALESCE(u.adi_soyadi, u.user_name) as ekleyen_adi
            FROM cari_hareketleri ch
            JOIN cari c ON c.id = ch.cari_id AND c.silinme_tarihi IS NULL
            LEFT JOIN users u ON u.id = ch.ekleyen_kullanici
            WHERE ch.silinme_tarihi IS NULL
            ORDER BY ch.islem_tarihi DESC, ch.id DESC
            LIMIT 10";
        $recentMovements = $this->db->query($recentSql)->fetchAll(PDO::FETCH_OBJ);

        return [
            'summary' => [
                'toplam_cari' => (int)($overall->toplam_cari ?? 0),
                'aktif_cari_sayisi' => (int)($overall->aktif_cari_sayisi ?? 0),
                'toplam_alacak' => (float)($overall->genel_toplam_alacak ?? 0),
                'toplam_borc' => (float)($overall->genel_toplam_borc ?? 0),
                'genel_net_bakiye' => (float)($overall->genel_net_bakiye ?? 0),
                'alacakli_sayisi' => (int)($counts->alacakli_sayisi ?? 0),
                'borclu_sayisi' => (int)($counts->borclu_sayisi ?? 0),
                'dengede_sayisi' => (int)($counts->dengede_sayisi ?? 0),
                'toplam_alacak_bakiye' => (float)($counts->toplam_alacak_bakiye ?? 0),
                'toplam_borc_bakiye' => (float)($counts->toplam_borc_bakiye ?? 0),
                'donem_alacak' => (float)($donemStats->donem_alacak ?? 0),
                'donem_borc' => (float)($donemStats->donem_borc ?? 0),
                'donem_net' => (float)($donemStats->donem_net ?? 0),
                'donem_islem_sayisi' => (int)($donemStats->donem_islem_sayisi ?? 0),
                'donem_hacim' => (float)(($donemStats->donem_alacak ?? 0) + ($donemStats->donem_borc ?? 0)),
                'period_label' => $periodLabel,
                'period' => $period,
                'start_date' => $pStart ? substr($pStart, 0, 10) : null,
                'end_date' => $pEnd ? substr($pEnd, 0, 10) : null
            ],
            'top_alacaklilar' => $topAlacaklilar,
            'top_borclular' => $topBorclular,
            'monthly_trend' => [
                'categories' => $categories,
                'alacak' => $alacakSeries,
                'borc' => $borcSeries,
                'net' => $netSeries
            ],
            'distribution' => [
                'alacak_tutar' => (float)($counts->toplam_alacak_bakiye ?? 0),
                'borc_tutar' => (float)($counts->toplam_borc_bakiye ?? 0),
                'alacakli_sayisi' => (int)($counts->alacakli_sayisi ?? 0),
                'borclu_sayisi' => (int)($counts->borclu_sayisi ?? 0),
                'dengede_sayisi' => (int)($counts->dengede_sayisi ?? 0)
            ],
            'recent_movements' => $recentMovements
        ];
    }
}

