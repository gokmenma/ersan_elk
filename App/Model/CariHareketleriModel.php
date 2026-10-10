<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class CariHareketleriModel extends Model
{
    protected $table = 'cari_hareketleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getHareketler($cari_id)
    {
        $sql = "SELECT *, 
                @bakiye := @bakiye + (alacak - borc) AS yuruyen_bakiye
                FROM $this->table, (SELECT @bakiye := 0) as vars
                WHERE cari_id = :cari_id AND silinme_tarihi IS NULL
                ORDER BY islem_tarihi ASC, id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cari_id' => $cari_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Cariye ait mevcut hareketlerin imzalarını (tarih|borc|alacak|açıklama) adetleriyle döndürür.
     * PDF içe aktarımında mükerrer kayıt tespiti için kullanılır. Aynı imzadan birden fazla
     * kayıt olabileceği için adet tutulur.
     */
    public function getKayitImzalari($cari_id)
    {
        $sql = "SELECT DATE(islem_tarihi) as tarih, borc, alacak, aciklama
                FROM $this->table
                WHERE cari_id = :cari_id AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cari_id' => $cari_id]);

        $imzalar = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $imza = $this->imzaOlustur($row->tarih, $row->borc, $row->alacak, $row->aciklama);
            $imzalar[$imza] = ($imzalar[$imza] ?? 0) + 1;
        }
        return $imzalar;
    }

    public function imzaOlustur($tarih, $borc, $alacak, $aciklama)
    {
        return implode('|', [
            substr((string) $tarih, 0, 10),
            number_format((float) $borc, 2, '.', ''),
            number_format((float) $alacak, 2, '.', ''),
            mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $aciklama)), 'UTF-8')
        ]);
    }

    /**
     * PDF'ten okunan hareketleri tek transaction içinde toplu kaydeder.
     *
     * @param int $cari_id
     * @param array $rows Her satır: ['tarih' => 'Y-m-d', 'aciklama' => string, 'borc' => float, 'alacak' => float, 'belge_no' => string|null]
     * @param int|null $userId Ekleyen kullanıcı ID
     * @return int Eklenen kayıt sayısı
     */
    public function topluEkle($cari_id, array $rows, ?int $userId = null)
    {
        if (empty($rows)) {
            return 0;
        }

        $sql = "INSERT INTO $this->table (cari_id, islem_tarihi, belge_no, aciklama, borc, alacak, ekleyen_kullanici, kayit_tarihi)
                VALUES (:cari_id, :islem_tarihi, :belge_no, :aciklama, :borc, :alacak, :ekleyen_kullanici, NOW())";

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare($sql);
            $eklenen = 0;
            foreach ($rows as $row) {
                $stmt->execute([
                    'cari_id' => $cari_id,
                    'islem_tarihi' => $row['tarih'] . ' 00:00:00',
                    'belge_no' => $row['belge_no'] ?? null,
                    'aciklama' => $row['aciklama'],
                    'borc' => $row['borc'],
                    'alacak' => $row['alacak'],
                    'ekleyen_kullanici' => $userId
                ]);
                $eklenen++;
            }
            $this->db->commit();
            return $eklenen;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Faturayı cari hareketlerine senkronize eder.
     * Gelen fatura -> Carinin hesabına ALACAK
     * Giden fatura -> Carinin hesabına BORÇ
     * Fatura bilgileri cari hareketine açıklama olarak eklenir.
     *
     * @param int $faturaId
     * @param int|null $userId
     * @return int|null Eklenen/güncellenen cari hareket ID'si
     */
    public function syncFaturaHareketi(int $faturaId, ?int $userId = null): ?int
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM faturalar WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $faturaId]);
            $fatura = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$fatura) {
                return null;
            }

            // Fatura silinmiş veya iptal edilmişse mevcut cari hareketini de sil
            if (!empty($fatura->deleted_at) || ($fatura->entegrator_durum_kodu ?? '') === 'IPTAL') {
                $delStmt = $this->db->prepare("UPDATE $this->table SET silinme_tarihi = NOW(), silen_kullanici = :user_id WHERE fatura_id = :fatura_id AND silinme_tarihi IS NULL");
                $delStmt->execute([
                    'user_id' => $userId ?: ($fatura->olusturan_user_id ?: null),
                    'fatura_id' => $faturaId
                ]);
                return null;
            }

            $cariId = !empty($fatura->cari_id) ? (int)$fatura->cari_id : 0;

            // Cari ID yoksa VKN veya Ünvana göre eşleştir
            if ($cariId <= 0) {
                $cariModel = new \App\Model\CariModel();
                $matchedCari = $cariModel->findMatchingCari($fatura->alici_vkn_tckn, $fatura->alici_unvan);
                if ($matchedCari) {
                    $cariId = (int)$matchedCari->id;
                    // Faturadaki cari_id alanını da güncelle
                    $updateFat = $this->db->prepare("UPDATE faturalar SET cari_id = :cari_id WHERE id = :id");
                    $updateFat->execute(['cari_id' => $cariId, 'id' => $faturaId]);
                }
            }

            if ($cariId <= 0) {
                return null;
            }

            $yon = strtoupper(trim((string)($fatura->yon ?? 'GIDEN')));
            $odenecekTutar = round((float)($fatura->odenecek_tutar ?? 0), 2);
            $faturaNo = trim((string)($fatura->fatura_no ?: ($fatura->ettn ?: 'Fatura')));
            $faturaTarihi = !empty($fatura->fatura_tarihi) ? $fatura->fatura_tarihi : date('Y-m-d');
            $saat = !empty($fatura->duzenleme_saati) ? $fatura->duzenleme_saati : '00:00:00';
            $islemTarihi = $faturaTarihi . ' ' . $saat;
            $unvan = trim((string)($fatura->alici_unvan ?? ''));

            // Gelen faturada ALACAK, Giden faturada BORÇ
            if ($yon === 'GELEN') {
                $borc = 0.00;
                $alacak = $odenecekTutar;
                $yonBaslik = 'Gelen e-Fatura';
            } else {
                $borc = $odenecekTutar;
                $alacak = 0.00;
                $yonBaslik = 'Giden e-Fatura';
            }

            $aciklamaParcalari = [];
            $aciklamaParcalari[] = "$yonBaslik: $faturaNo";
            if (!empty($unvan)) {
                $aciklamaParcalari[] = $unvan;
            }
            $aciklamaParcalari[] = date('d.m.Y', strtotime($faturaTarihi));
            $aciklamaParcalari[] = "Tutar: " . \App\Helper\Helper::formattedMoney($odenecekTutar) . " ₺";
            if (!empty($fatura->fatura_tipi)) {
                $aciklamaParcalari[] = "Tip: " . $fatura->fatura_tipi;
            }
            $aciklama = implode(' | ', $aciklamaParcalari);

            $ekleyenUser = $userId ?: (!empty($fatura->olusturan_user_id) ? (int)$fatura->olusturan_user_id : ($_SESSION['id'] ?? $_SESSION['user_id'] ?? null));

            // Mükerrer Kayıt Önleme Kontrolü
            // 1. Öncelik: Fatura ID ile eşleşen mevcut aktif hareket kontrolü
            $checkStmt = $this->db->prepare("SELECT id FROM $this->table WHERE fatura_id = :fatura_id AND silinme_tarihi IS NULL LIMIT 1");
            $checkStmt->execute(['fatura_id' => $faturaId]);
            $existingMovementId = $checkStmt->fetchColumn();

            // 2. Öncelik: Fatura ID bağlı değilse, aynı cariye ait aynı belge_no (fatura no) kontrolü
            if (!$existingMovementId && !empty($faturaNo) && $faturaNo !== 'Fatura') {
                $checkBelgeStmt = $this->db->prepare("
                    SELECT id FROM $this->table 
                    WHERE cari_id = :cari_id AND belge_no = :belge_no AND silinme_tarihi IS NULL 
                    LIMIT 1
                ");
                $checkBelgeStmt->execute([
                    'cari_id' => $cariId,
                    'belge_no' => $faturaNo
                ]);
                $existingMovementId = $checkBelgeStmt->fetchColumn();
            }

            if ($existingMovementId) {
                $upStmt = $this->db->prepare("
                    UPDATE $this->table SET 
                        cari_id = :cari_id,
                        fatura_id = :fatura_id,
                        islem_tarihi = :islem_tarihi,
                        belge_no = :belge_no,
                        aciklama = :aciklama,
                        borc = :borc,
                        alacak = :alacak,
                        ekleyen_kullanici = COALESCE(:ekleyen_kullanici, ekleyen_kullanici)
                    WHERE id = :id
                ");
                $upStmt->execute([
                    'cari_id' => $cariId,
                    'fatura_id' => $faturaId,
                    'islem_tarihi' => $islemTarihi,
                    'belge_no' => $faturaNo,
                    'aciklama' => $aciklama,
                    'borc' => $borc,
                    'alacak' => $alacak,
                    'ekleyen_kullanici' => $ekleyenUser,
                    'id' => $existingMovementId
                ]);
                return (int)$existingMovementId;
            } else {
                $insStmt = $this->db->prepare("
                    INSERT INTO $this->table (
                        cari_id, islem_tarihi, belge_no, aciklama, borc, alacak, ekleyen_kullanici, fatura_id, kayit_tarihi
                    ) VALUES (
                        :cari_id, :islem_tarihi, :belge_no, :aciklama, :borc, :alacak, :ekleyen_kullanici, :fatura_id, NOW()
                    )
                ");
                $insStmt->execute([
                    'cari_id' => $cariId,
                    'islem_tarihi' => $islemTarihi,
                    'belge_no' => $faturaNo,
                    'aciklama' => $aciklama,
                    'borc' => $borc,
                    'alacak' => $alacak,
                    'ekleyen_kullanici' => $ekleyenUser,
                    'fatura_id' => $faturaId
                ]);
                return (int)$this->db->lastInsertId();
            }
        } catch (\Throwable $e) {
            error_log("CariHareketleriModel::syncFaturaHareketi Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tüm aktif faturaları (veya belirli bir carinin faturalarını) cari hareketlerine senkronize eder.
     *
     * @param int|null $cariId
     * @param int|null $userId
     * @return array ['scanned' => int, 'matched' => int, 'created' => int, 'updated' => int]
     */
    public function syncAllInvoices(?int $cariId = null, ?int $userId = null): array
    {
        $stats = [
            'scanned' => 0,
            'matched' => 0,
            'processed' => 0
        ];

        try {
            $where = "deleted_at IS NULL AND (entegrator_durum_kodu != 'IPTAL' OR entegrator_durum_kodu IS NULL)";
            $params = [];

            if ($cariId !== null && $cariId > 0) {
                $cariModel = new \App\Model\CariModel();
                $cari = $cariModel->find($cariId);
                if ($cari) {
                    $vkn = preg_replace('/[^0-9]/', '', (string)($cari->vkn_tckn ?? ''));
                    $conditions = ["cari_id = :cari_id"];
                    $params['cari_id'] = $cariId;

                    if (!empty($vkn) && strlen($vkn) >= 10) {
                        $conditions[] = "alici_vkn_tckn = :vkn";
                        $params['vkn'] = $vkn;
                    }
                    if (!empty($cari->firma)) {
                        $conditions[] = "alici_unvan LIKE :firma";
                        $params['firma'] = '%' . $cari->firma . '%';
                    }
                    if (!empty($cari->CariAdi)) {
                        $conditions[] = "alici_unvan LIKE :cari_adi";
                        $params['cari_adi'] = '%' . $cari->CariAdi . '%';
                    }
                    $where .= " AND (" . implode(" OR ", $conditions) . ")";
                }
            }

            $stmt = $this->db->prepare("SELECT id FROM faturalar WHERE $where ORDER BY fatura_tarihi ASC, id ASC");
            $stmt->execute($params);
            $faturaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stats['scanned'] = count($faturaIds);
            foreach ($faturaIds as $fId) {
                $res = $this->syncFaturaHareketi((int)$fId, $userId);
                if ($res) {
                    $stats['matched']++;
                    $stats['processed']++;
                }
            }

            return $stats;
        } catch (\Throwable $e) {
            error_log("CariHareketleriModel::syncAllInvoices Error: " . $e->getMessage());
            return $stats;
        }
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $cari_id = $params['cari_id'];
        $search = $params['search']['value'] ?? "";
        $orders = $params['order'] ?? [];
        $columns = $params['columns'] ?? [];
        $filter_type = $params['filter_type'] ?? 'all';

        $where = "h.cari_id = :cari_id AND h.silinme_tarihi IS NULL";
        $bindParams = ['cari_id' => $cari_id];

        // Tümü, Girişler, Çıkışlar Filtresi
        if ($filter_type === 'verdim') {
            $where .= " AND h.alacak > 0";
        } elseif ($filter_type === 'aldim') {
            $where .= " AND h.borc > 0";
        }

        // Global Arama
        if (!empty($search)) {
            $where .= " AND (h.belge_no LIKE :search OR h.aciklama LIKE :search OR u.adi_soyadi LIKE :search OR u.user_name LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Sütun Bazlı Arama (Advanced Filters)
        if (!empty($columns)) {
            $colMap = [
                0 => 'h.islem_tarihi',
                1 => 'h.belge_no',
                2 => 'h.aciklama',
                3 => 'h.borc',
                4 => 'h.alacak',
                5 => '(SELECT SUM(alacak - borc) FROM cari_hareketleri WHERE cari_id = h.cari_id AND silinme_tarihi IS NULL AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id)))',
                6 => 'u.adi_soyadi'
            ];
            foreach ($columns as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = $column['search']['value'];
                    $paramName = "col_" . $i;

                    if (strpos($val, ':') !== false) {
                        list($mode, $filterVal) = explode(':', $val, 2);
                        $vals = explode('|', $filterVal);
                        $filterVal = $vals[0];

                        // Sayısal alanlar için temizlik yap
                        if ($field === 'h.borc' || $field === 'h.alacak' || $i === 5) {
                            if (!in_array($mode, ['null', 'not_null'])) {
                                $filterVal = \App\Helper\Helper::formattedMoneyToNumber($filterVal);
                            }
                        }

                        switch ($mode) {
                            case 'contains': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal%"; break;
                            case 'not_contains': $where .= " AND $field NOT LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal%"; break;
                            case 'equals': $where .= " AND $field = :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'not_equals': $where .= " AND $field != :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'starts_with': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "$filterVal%"; break;
                            case 'ends_with': $where .= " AND $field LIKE :$paramName"; $bindParams[$paramName] = "%$filterVal"; break;
                            case 'greater_than': $where .= " AND $field > :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'less_than': $where .= " AND $field < :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'greater_equal': $where .= " AND $field >= :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'less_equal': $where .= " AND $field <= :$paramName"; $bindParams[$paramName] = $filterVal; break;
                            case 'before': $where .= " AND DATE($field) <= :$paramName"; $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal)); break;
                            case 'after': $where .= " AND DATE($field) >= :$paramName"; $bindParams[$paramName] = date('Y-m-d', strtotime($filterVal)); break;
                            case 'between': 
                                if (count($vals) === 2) {
                                    $v1 = $paramName . "_1";
                                    $v2 = $paramName . "_2";
                                    $where .= " AND DATE($field) BETWEEN :$v1 AND :$v2";
                                    $bindParams[$v1] = date('Y-m-d', strtotime($vals[0]));
                                    $bindParams[$v2] = date('Y-m-d', strtotime($vals[1]));
                                }
                                break;
                            case 'null': $where .= " AND ($field IS NULL OR $field = '')"; break;
                            case 'not_null': $where .= " AND ($field IS NOT NULL AND $field != '')"; break;
                        }
                    } else {
                        $where .= " AND $field LIKE :$paramName";
                        $bindParams[$paramName] = "%$val%";
                    }
                }
            }
        }

        // Toplam Kayıt Sayısı
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $cari_id]);
        $totalCount = $stmt->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) FROM $this->table h LEFT JOIN users u ON u.id = h.ekleyen_kullanici WHERE $where");
        foreach($bindParams as $k => $v) {
            $stmtFiltered->bindValue($k, $v);
        }
        $stmtFiltered->execute();
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY h.islem_tarihi DESC, h.id DESC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?? null;
                
                if ($colName === "ekleyen") {
                    $orderArr[] = "u.adi_soyadi $colDir";
                } elseif ($colName && $colName != "actions" && $colName != "yuruyen_bakiye") {
                    $orderArr[] = "h.$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        $sql = "SELECT h.*, 
                u.adi_soyadi as ekleyen_kullanici_adi,
                u.user_name as ekleyen_user_name,
                f.fatura_no as ref_fatura_no,
                f.yon as ref_fatura_yon,
                f.ettn as ref_fatura_ettn,
                (SELECT SUM(alacak - borc) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
                FROM $this->table h
                LEFT JOIN users u ON u.id = h.ekleyen_kullanici
                LEFT JOIN faturalar f ON f.id = h.fatura_id
                WHERE $where
                $orderQuery
                LIMIT :start, :length";

        $stmt = $this->db->prepare($sql);
        foreach($bindParams as $k => $v) {
            $stmt->bindValue($k, $v);
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

    /**
     * Tüm cariler genelinde en son yapılan hesap hareketlerini getirir.
     *
     * @param int $limit
     * @param string $type all|aldim|verdim
     * @param string $search
     * @return array
     */
    public function getSonHareketler(int $limit = 15, string $type = 'all', string $search = ''): array
    {
        $where = "h.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL";
        $params = [];

        if ($type === 'aldim') {
            $where .= " AND h.borc > 0";
        } elseif ($type === 'verdim') {
            $where .= " AND h.alacak > 0";
        }

        if (!empty($search)) {
            $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR h.aciklama LIKE :search OR h.belge_no LIKE :search OR u.adi_soyadi LIKE :search)";
            $params['search'] = "%$search%";
        }

        $sql = "SELECT 
                    h.id, h.cari_id, h.islem_tarihi, h.belge_no, h.aciklama, h.borc, h.alacak, h.fatura_id, h.dosya,
                    c.CariAdi, c.firma, c.vkn_tckn,
                    u.adi_soyadi as ekleyen_kullanici_adi, u.user_name as ekleyen_user_name,
                    f.fatura_no as ref_fatura_no, f.yon as ref_fatura_yon, f.ettn as ref_fatura_ettn
                FROM $this->table h
                JOIN cari c ON c.id = h.cari_id
                LEFT JOIN users u ON u.id = h.ekleyen_kullanici
                LEFT JOIN faturalar f ON f.id = h.fatura_id
                WHERE $where
                ORDER BY h.islem_tarihi DESC, h.id DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}

