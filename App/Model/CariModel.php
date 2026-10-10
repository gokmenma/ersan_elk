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
}
