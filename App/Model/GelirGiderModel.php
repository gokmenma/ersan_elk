<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Helper;
use App\Helper\Security;
use PDO;

use App\Model\TanimlamalarModel;



class GelirGiderModel extends Model
{
    protected $table = 'gelir_gider';

    protected $sql_table = "sql_gelir_gider";

    public function __construct()
    {
        parent::__construct($this->table);
    }





    public function all($yil = null, $ay = null, $kategori = null)
    {
        $where = "1=1";
        $params = [];
        
        if ($yil) {
            $where .= " AND YEAR(tarih) = :yil";
            $params['yil'] = $yil;
        }
        if ($ay) {
            $where .= " AND MONTH(tarih) = :ay";
            $params['ay'] = $ay;
        }
        if ($kategori) {
            $where .= " AND type = :kategori";
            $params['kategori'] = $kategori;
        }

        $sql = $this->db->prepare("SELECT g.*, g.kategori as kategori_adi 
                                    FROM $this->table g
                                    WHERE $where
                                    ORDER BY g.tarih DESC, g.id DESC");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function find($id)
    {
        $sql = $this->db->prepare("SELECT g.*, g.kategori as kategori_adi 
                                    FROM $this->table g
                                    WHERE g.id = :id");
        $sql->execute(['id' => $id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function delete($id, $decrypt = true)
    {
        if ($decrypt) {
            $id = Security::decrypt($id);
        }
        $userId = $_SESSION['id'] ?? 0;
        $stmt = $this->db->prepare("UPDATE {$this->table} SET silinme_tarihi = NOW(), silen_kullanici = :uid, duplicate_hash = NULL WHERE id = :id");
        return $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    public function bulkDelete(array $ids, $userId = 0)
    {
        if (empty($ids)) return false;
        $cleanIds = [];
        foreach ($ids as $id) {
            $dec = is_numeric($id) ? (int)$id : (int)Security::decrypt($id);
            if ($dec > 0) $cleanIds[] = $dec;
        }
        if (empty($cleanIds)) return false;

        $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
        $sql = "UPDATE {$this->table} SET silinme_tarihi = NOW(), silen_kullanici = ?, duplicate_hash = NULL WHERE id IN ($inClause)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array_merge([$userId], $cleanIds));
    }

    public function getPlakalar()
    {
        try {
            return $this->db->query("SELECT DISTINCT plaka FROM araclar WHERE (silinme_tarihi IS NULL OR silinme_tarihi = '0000-00-00 00:00:00') AND plaka IS NOT NULL AND plaka != '' ORDER BY plaka ASC")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getBankalar()
    {
        try {
            $kasalar = $this->db->query("SELECT DISTINCT kasa_adi FROM kasalar WHERE silinme_tarihi IS NULL AND kasa_adi IS NOT NULL AND kasa_adi != '' ORDER BY kasa_adi ASC")->fetchAll(PDO::FETCH_COLUMN);
            $defaultBankalar = ['Ziraat Bankası', 'Garanti BBVA', 'İş Bankası', 'Yapı Kredi', 'Akbank', 'QNB Finansbank', 'VakıfBank', 'Halkbank', 'DenizBank', 'Kuveyt Türk', 'Enpara', 'TEB'];
            return array_values(array_unique(array_filter(array_merge($kasalar, $defaultBankalar))));
        } catch (\Exception $e) {
            return ['Ziraat Bankası', 'Garanti BBVA', 'İş Bankası', 'Yapı Kredi', 'Akbank', 'QNB Finansbank', 'VakıfBank', 'Halkbank', 'DenizBank', 'Kuveyt Türk', 'Enpara', 'TEB'];
        }
    }

    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getGelirGiderTableRow($id)
    {
        $Tanimlama = new TanimlamalarModel();
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);
        
        // Bu metod artık DataTables server-side ile uyumlu formatta veri döndürmeli
        // Ancak eski kodun çalışması için bir miktar uyumluluk bırakıyorum
        $kayit_sayisi = 0; // Bu artık server-side'da farklı hesaplanıyor

        //Eğer bakiye 0'dan küçükse danger, büyükse success
        $color = ($data->type == 2) ? 'danger' : 'success'; // Basit bakiye mantığı

          return '<tr id="gelir_gider_' . $data->id . '" data-id="' . $enc_id . '">
            <td class="text-center">' . $kayit_sayisi . '</td>
            <td class="text-center">' . date('d.m.Y H:i', strtotime($data->kayit_tarihi)) . '</td>
            <td class="text-center">' . Helper::getBadge($data->type) . '</td>
            <td class="text-center">' . ($data->kategori_adi ?: '-') . '</td>
            <td>' . ($data->tarih ?: '-') . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->tutar) . '</td>
            <td class="text-end text-' . $color . '">' . Helper::formattedMoney($data->tutar) . '</td>
            <td>' . ($data->aciklama ?: '-') . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                            </a>
                            <a class="dropdown-item gelir-gider-sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    //Toplam gelir, gider ve bakiye getir
    public function summary($params = [])
    {
        $where = "g.silinme_tarihi IS NULL";
        $bindParams = [];
        
        $this->prepareFilters($params, $where, $bindParams);
        
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) AS toplam_islem,
                COALESCE(SUM(CASE WHEN g.type = 1 THEN 1 ELSE 0 END), 0) AS gelir_adet,
                COALESCE(SUM(CASE WHEN g.type = 2 THEN 1 ELSE 0 END), 0) AS gider_adet,
                ROUND(COALESCE(SUM(CASE WHEN g.type = 1 THEN CAST(g.tutar AS DECIMAL(15,2)) ELSE 0 END), 0), 2) AS toplam_gelir,
                ROUND(COALESCE(SUM(CASE WHEN g.type = 2 THEN CAST(g.tutar AS DECIMAL(15,2)) ELSE 0 END), 0), 2) AS toplam_gider,
                ROUND(COALESCE(SUM(CASE WHEN g.type = 1 THEN CAST(g.tutar AS DECIMAL(15,2)) ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN g.type = 2 THEN CAST(g.tutar AS DECIMAL(15,2)) ELSE 0 END), 0), 2) AS bakiye
            FROM {$this->table} g
            WHERE $where
        ");
        $sql->execute($bindParams);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    //Kasa Özetini  getir
    public function getGelirGiderStatics()
    {
        $sql = $this->db->prepare("
            SELECT 
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END), 2) AS toplam_gelir,
                ROUND(SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS toplam_gider,
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END) - SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS bakiye
            FROM $this->table
            WHERE silinme_tarihi IS NULL
        ");
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function ajaxList($params)
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $search = $params['search']['value'] ?? '';
        $orders = $params['order'] ?? [];
        $columns = $params['columns'] ?? [];

        // Filtreler (Yıl, Ay, Tip)
        $yil = $params['yil'] ?? null;
        $ay = $params['ay'] ?? null;
        $tip = $params['tip'] ?? null;

        $where = "g.silinme_tarihi IS NULL";
        $bindParams = [];

        $this->prepareFilters($params, $where, $bindParams);

        // Toplam Kayıt Sayısı
        $totalSql = "SELECT COUNT(*) FROM {$this->table} WHERE silinme_tarihi IS NULL";
        $totalCount = $this->db->query($totalSql)->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı (Hızlı Sayım - Base table üzerinden)
        $filteredSql = "SELECT COUNT(*) FROM {$this->table} g WHERE $where";
        $stmtFiltered = $this->db->prepare($filteredSql);
        $stmtFiltered->execute($bindParams);
        $filteredCount = $stmtFiltered->fetchColumn();

        // Sıralama
        $orderQuery = "ORDER BY g.tarih DESC, g.id DESC";
        if (!empty($orders)) {
            $orderArr = [];
            foreach ($orders as $order) {
                $colIdx = $order['column'];
                $colDir = $order['dir'];
                $colName = $columns[$colIdx]['data'] ?: $columns[$colIdx]['name'];
                if ($colName == "kategori_adi") $colName = "g.kategori";
                else if ($colName && $colName != "actions") $colName = "g." . $colName;
                
                if ($colName && $colName != "actions") {
                    $orderArr[] = "$colName $colDir";
                }
            }
            if (!empty($orderArr)) {
                $orderQuery = "ORDER BY " . implode(", ", $orderArr);
            }
        }

        // Ana Sorgu
        $sql = "SELECT g.*, g.kategori as kategori_adi 
                FROM sql_gelir_gider g 
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

    /**
     * Gelişmiş filtreleri işleyip SQL sorgusuna ekler
     */
    private function processAdvancedFilters($column, $searchVal, &$query, &$params, $idx)
    {
        if (empty($searchVal)) return;

        // Mode ve değeri ayır (e.g., "contains:test", "multi:1|2")
        $parts = explode(':', $searchVal, 2);
        if (count($parts) < 2) {
            // Standart arama
            $query .= " AND {$column} LIKE :col_search_{$idx}";
            $params[":col_search_{$idx}"] = "%{$searchVal}%";
            return;
        }

        $mode = $parts[0];
        $value = $parts[1];

        // Tutar gibi varchar kolonlar için sayısal karşılaştırma gerekebilir
        $isNumericCol = strpos($column, 'tutar') !== false || strpos($column, 'bakiye') !== false;
        $compareCol = $isNumericCol ? "CAST({$column} AS DECIMAL(15,2))" : $column;

        switch ($mode) {
            case 'contains':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}%";
                break;
            case 'not_contains':
                $query .= " AND {$column} NOT LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}%";
                break;
            case 'starts_with':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "{$value}%";
                break;
            case 'ends_with':
                $query .= " AND {$column} LIKE :col_search_{$idx}";
                $params[":col_search_{$idx}"] = "%{$value}";
                break;
            case 'equals':
                $query .= " AND {$compareCol} = :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'not_equals':
                $query .= " AND {$compareCol} != :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'greater_than':
                $query .= " AND {$compareCol} > :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'less_than':
                $query .= " AND {$compareCol} < :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'greater_equal':
                $query .= " AND {$compareCol} >= :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'less_equal':
                $query .= " AND {$compareCol} <= :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'between':
                $range = explode('|', $value);
                if (count($range) == 2) {
                    $query .= " AND {$compareCol} BETWEEN :col_search_{$idx}_1 AND :col_search_{$idx}_2";
                    $params[":col_search_{$idx}_1"] = $range[0];
                    $params[":col_search_{$idx}_2"] = $range[1];
                }
                break;
            case 'multi':
                $values = explode('|', $value);
                if (!empty($values)) {
                    $placeholders = [];
                    foreach ($values as $vIdx => $v) {
                        $pName = ":col_search_{$idx}_{$vIdx}";
                        $placeholders[] = $pName;
                        $params[$pName] = $v;
                    }
                    $query .= " AND {$column} IN (" . implode(',', $placeholders) . ")";
                }
                break;
            case 'null':
                $query .= " AND ({$column} IS NULL OR {$column} = '')";
                break;
            case 'not_null':
                $query .= " AND ({$column} IS NOT NULL AND {$column} != '')";
                break;
            case 'before':
                $query .= " AND {$compareCol} < :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
            case 'after':
                $query .= " AND {$compareCol} > :col_search_{$idx}";
                $params[":col_search_{$idx}"] = $value;
                break;
        }
    }

    /**
     * Ortak filtre hazırlama mantığı
     */
    private function prepareFilters($params, &$where, &$bindParams)
    {
        $yil = $params['yil'] ?? null;
        $ay = $params['ay'] ?? null;
        $tip = $params['tip'] ?? ($params['kategori'] ?? null);
        $search = $params['search']['value'] ?? null;
        $columns = $params['columns'] ?? [];

        $prefix = (strpos($where, 'g.') !== false || isset($params['draw']) || isset($params['action'])) ? 'g.' : '';

        if ($yil) {
            $where .= " AND YEAR({$prefix}tarih) = :yil";
            $bindParams['yil'] = $yil;
        }
        if ($ay) {
            $where .= " AND MONTH({$prefix}tarih) = :ay";
            $bindParams['ay'] = $ay;
        }
        if ($tip) {
            $where .= " AND {$prefix}type = :tip";
            $bindParams['tip'] = $tip;
        }

        // Global Arama
        if (!empty($search)) {
            $where .= " AND ({$prefix}aciklama LIKE :search OR {$prefix}kategori LIKE :search)";
            $bindParams['search'] = "%$search%";
        }

        // Sütun Bazlı Arama (Gelişmiş Filtreler)
        foreach ($columns as $i => $column) {
            if (!empty($column['search']['value'])) {
                $searchVal = $column['search']['value'];
                $colName = $column['data'] ?: $column['name'];
                
                // SQL view field mapping
                if ($colName == "kategori_adi" || $colName == "kategori") {
                    $dbField = "{$prefix}kategori"; 
                } else if ($colName != "actions") {
                    $dbField = "{$prefix}" . $colName;
                } else {
                    continue;
                }

                $this->processAdvancedFilters($dbField, $searchVal, $where, $bindParams, $i);
            }
        }
    }

    /**
     * DataTable'daki select filtreleri için benzersiz değerleri döner
     */
    public function getUniqueValues($column, $params = [])
    {
        // View alanlarını eşle
        $field = ($column == "kategori_adi" || $column == "kategori") ? 'kategori' : $column;
        
        $sql = "SELECT DISTINCT {$field} FROM {$this->table} WHERE {$field} IS NOT NULL AND {$field} != '' ORDER BY {$field} ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Excel dosyasından gelir-gider kayıtlarını içe aktarır
     *
     * @param string $filePath Geçici dosya yolu
     * @param int $userId İşlemi yapan kullanıcı ID
     * @param string $originalFilename Kullanıcının yüklediği dosyanın adı
     * @return array [status => 'success'|'error', 'message' => string, 'count' => int]
     */
    public function importFromExcel(string $filePath, int $userId = 0, string $originalFilename = ''): array
    {
        if (!file_exists($filePath)) {
            return ['status' => 'error', 'message' => 'Excel dosyası bulunamadı.', 'count' => 0];
        }

        try {
            $fileHash = hash_file('sha256', $filePath);
            if ($fileHash === false) {
                return ['status' => 'error', 'message' => 'Excel dosyasının özeti oluşturulamadı.', 'count' => 0];
            }

            $fileCheck = $this->db->prepare("SELECT id FROM gelir_gider_excel_imports WHERE file_hash = :file_hash LIMIT 1");
            $fileCheck->execute(['file_hash' => $fileHash]);
            if ($fileCheck->fetchColumn()) {
                return [
                    'status' => 'error',
                    'message' => 'Bu Excel dosyası daha önce yüklenmiş. Aynı dosya tekrar içe aktarılamaz.',
                    'count' => 0,
                    'duplicate_count' => 0
                ];
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            if (empty($rows) || count($rows) < 2) {
                return ['status' => 'error', 'message' => 'Excel dosyasında içe aktarılacak veri satırı bulunamadı.', 'count' => 0];
            }

            $headers = array_shift($rows);
            $headerMap = [];

            $trSearch = ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'];
            $trReplace = ['i', 'i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];

            foreach ($headers as $colIdx => $headerTitle) {
                $norm = str_replace($trSearch, $trReplace, trim((string)$headerTitle));
                $norm = mb_strtolower($norm, 'UTF-8');

                if (str_contains($norm, 'islem tarihi') || ($norm === 'tarih')) {
                    $headerMap['tarih'] = $colIdx;
                } elseif (str_contains($norm, 'hesap') || str_contains($norm, 'cari')) {
                    $headerMap['hesap_adi'] = $colIdx;
                } elseif (str_contains($norm, 'tutar')) {
                    $headerMap['tutar'] = $colIdx;
                } elseif (str_contains($norm, 'kategori')) {
                    $headerMap['kategori'] = $colIdx;
                } elseif (str_contains($norm, 'plaka')) {
                    $headerMap['plaka'] = $colIdx;
                } elseif (str_contains($norm, 'aciklama')) {
                    $headerMap['aciklama'] = $colIdx;
                } elseif (str_contains($norm, 'tur') || str_contains($norm, 'tip')) {
                    $headerMap['type'] = $colIdx;
                } elseif (str_contains($norm, 'odeme')) {
                    $headerMap['odeme_sekli'] = $colIdx;
                } elseif (str_contains($norm, 'banka')) {
                    $headerMap['banka_adi'] = $colIdx;
                } elseif (str_contains($norm, 'kayit tarihi')) {
                    $headerMap['kayit_tarihi'] = $colIdx;
                }
            }

            // Varsayılan kolon indeksleri (Resimdeki formata göre: 0:Sıra, 1:İşlem Tarihi, 2:Hesap Adı, 3:Tutar, 4:Kategori, 5:Plaka, 6:Açıklama, 7:Tür, 8:Ödeme Şekli, 9:Banka Adı, 10:Bakiye, 11:Kayıt Tarihi)
            $idxTarih = $headerMap['tarih'] ?? 1;
            $idxHesap = $headerMap['hesap_adi'] ?? 2;
            $idxTutar = $headerMap['tutar'] ?? 3;
            $idxKategori = $headerMap['kategori'] ?? 4;
            $idxPlaka = $headerMap['plaka'] ?? 5;
            $idxAciklama = $headerMap['aciklama'] ?? 6;
            $idxType = $headerMap['type'] ?? 7;
            $idxOdemeSekli = $headerMap['odeme_sekli'] ?? 8;
            $idxBankaAdi = $headerMap['banka_adi'] ?? 9;
            $idxKayitTarihi = $headerMap['kayit_tarihi'] ?? 11;

            $insertData = [];
            $seenHashes = [];
            $skipped = 0;

            foreach ($rows as $rowIndex => $row) {
                // Boş satır kontrolü
                $allEmpty = true;
                foreach ($row as $cellVal) {
                    if ($cellVal !== null && trim((string)$cellVal) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) {
                    continue;
                }

                $rawTutar = $row[$idxTutar] ?? null;
                $tutar = (float)Helper::formattedMoneyToNumber($rawTutar);
                $rawHesap = trim((string)($row[$idxHesap] ?? ''));
                $rawKategori = trim((string)($row[$idxKategori] ?? ''));
                $rawAciklama = trim((string)($row[$idxAciklama] ?? ''));

                if ($tutar <= 0 && empty($rawHesap) && empty($rawKategori) && empty($rawAciklama)) {
                    $skipped++;
                    continue;
                }

                // Tür tespiti (1: Gelir, 2: Gider) - GELİR, GİDER, GELIR, GIDER, vb.
                $rawType = trim((string)($row[$idxType] ?? ''));
                $normType = str_replace($trSearch, $trReplace, $rawType);
                $normType = mb_strtolower($normType, 'UTF-8');
                $normType = trim($normType);

                $type = 1;
                if ($normType === '2' || str_contains($normType, 'gider') || str_contains($normType, 'cikis') || $normType === 'g') {
                    $type = 2;
                } elseif ($normType === '1' || str_contains($normType, 'gelir') || str_contains($normType, 'giris') || $normType === 'a') {
                    $type = 1;
                } else {
                    $type = 1;
                }

                // İşlem Tarihi
                $rawTarih = $row[$idxTarih] ?? null;
                $tarih = null;
                if (!empty($rawTarih)) {
                    $tarih = \App\Helper\Date::convertExcelDate($rawTarih, 'Y-m-d H:i:s');
                    if (!$tarih && is_string($rawTarih)) {
                        $time = strtotime($rawTarih);
                        if ($time !== false && $time > 0) {
                            $tarih = date('Y-m-d H:i:s', $time);
                        }
                    }
                }
                if (!$tarih) {
                    $tarih = date('Y-m-d H:i:s');
                }

                // Kayıt Tarihi
                $rawKayitTarihi = $row[$idxKayitTarihi] ?? null;
                $kayitTarihi = null;
                if (!empty($rawKayitTarihi)) {
                    $kayitTarihi = \App\Helper\Date::convertExcelDate($rawKayitTarihi, 'Y-m-d H:i:s');
                    if (!$kayitTarihi && is_string($rawKayitTarihi)) {
                        $time = strtotime($rawKayitTarihi);
                        if ($time !== false && $time > 0) {
                            $kayitTarihi = date('Y-m-d H:i:s', $time);
                        }
                    }
                }
                if (!$kayitTarihi) {
                    $kayitTarihi = date('Y-m-d H:i:s');
                }

                $data = [
                    'type' => $type,
                    'tarih' => $tarih,
                    'kategori' => $rawKategori,
                    'hesap_adi' => $rawHesap,
                    'tutar' => $tutar,
                    'aciklama' => $rawAciklama,
                    'plaka' => trim((string)($row[$idxPlaka] ?? '')),
                    'odeme_sekli' => trim((string)($row[$idxOdemeSekli] ?? '')),
                    'banka_adi' => trim((string)($row[$idxBankaAdi] ?? '')),
                    'kayit_tarihi' => $kayitTarihi,
                    'kayit_yapan' => $userId > 0 ? $userId : 0
                ];

                $data['duplicate_hash'] = $this->buildExcelDuplicateHash($data);
                if (isset($seenHashes[$data['duplicate_hash']])) {
                    $skipped++;
                    continue;
                }
                $seenHashes[$data['duplicate_hash']] = true;
                $insertData[] = $data;
            }

            if (empty($insertData)) {
                return ['status' => 'error', 'message' => 'İçe aktarılacak geçerli kayıt bulunamadı.', 'count' => 0];
            }

            $this->db->beginTransaction();

            $importStmt = $this->db->prepare("
                INSERT INTO gelir_gider_excel_imports
                    (file_hash, original_filename, total_rows, imported_rows, duplicate_rows, uploaded_by)
                VALUES
                    (:file_hash, :original_filename, :total_rows, 0, 0, :uploaded_by)
            ");
            $importStmt->execute([
                'file_hash' => $fileHash,
                'original_filename' => mb_substr(basename($originalFilename ?: $filePath), 0, 255, 'UTF-8'),
                'total_rows' => count($insertData) + $skipped,
                'uploaded_by' => $userId
            ]);
            $importId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (type, tarih, kategori, hesap_adi, tutar, aciklama, plaka, odeme_sekli, banka_adi, kayit_tarihi, kayit_yapan, duplicate_hash, excel_import_id) 
                VALUES 
                (:type, :tarih, :kategori, :hesap_adi, :tutar, :aciklama, :plaka, :odeme_sekli, :banka_adi, :kayit_tarihi, :kayit_yapan, :duplicate_hash, :excel_import_id)
            ");

            $existsStmt = $this->db->prepare("
                SELECT id
                FROM {$this->table}
                WHERE silinme_tarihi IS NULL
                  AND (
                    duplicate_hash = :duplicate_hash
                    OR (
                        type = :type AND tarih = :tarih
                        AND ROUND(CAST(tutar AS DECIMAL(15,2)), 2) = :tutar
                        AND TRIM(COALESCE(kategori, '')) = :kategori
                        AND TRIM(COALESCE(hesap_adi, '')) = :hesap_adi
                        AND TRIM(COALESCE(aciklama, '')) = :aciklama
                        AND TRIM(COALESCE(plaka, '')) = :plaka
                        AND TRIM(COALESCE(odeme_sekli, '')) = :odeme_sekli
                        AND TRIM(COALESCE(banka_adi, '')) = :banka_adi
                    )
                  )
                LIMIT 1
            ");

            $imported = 0;
            foreach ($insertData as $data) {
                $existsStmt->execute([
                    'duplicate_hash' => $data['duplicate_hash'],
                    'type' => $data['type'],
                    'tarih' => $data['tarih'],
                    'tutar' => number_format((float)$data['tutar'], 2, '.', ''),
                    'kategori' => $data['kategori'],
                    'hesap_adi' => $data['hesap_adi'],
                    'aciklama' => $data['aciklama'],
                    'plaka' => $data['plaka'],
                    'odeme_sekli' => $data['odeme_sekli'],
                    'banka_adi' => $data['banka_adi']
                ]);
                if ($existsStmt->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $data['excel_import_id'] = $importId;
                try {
                    $stmt->execute($data);
                    $imported++;
                } catch (\PDOException $insertException) {
                    if ($insertException->getCode() === '23000') {
                        $skipped++;
                        continue;
                    }
                    throw $insertException;
                }
            }

            $updateImport = $this->db->prepare("
                UPDATE gelir_gider_excel_imports
                SET imported_rows = :imported_rows, duplicate_rows = :duplicate_rows
                WHERE id = :id
            ");
            $updateImport->execute([
                'imported_rows' => $imported,
                'duplicate_rows' => $skipped,
                'id' => $importId
            ]);

            $this->db->commit();

            return [
                'status' => 'success',
                'message' => $imported . ' kayıt yüklendi, ' . $skipped . ' mükerrer/geçersiz satır atlandı.',
                'count' => $imported,
                'duplicate_count' => $skipped
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("GelirGiderModel::importFromExcel error: " . $e->getMessage());
            if ($e instanceof \PDOException && $e->getCode() === '23000') {
                return [
                    'status' => 'error',
                    'message' => 'Bu Excel dosyası daha önce yüklenmiş. Aynı dosya tekrar içe aktarılamaz.',
                    'count' => 0,
                    'duplicate_count' => 0
                ];
            }
            return [
                'status' => 'error',
                'message' => 'Excel dosyası işlenirken bir hata oluştu: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }

    private function buildExcelDuplicateHash(array $data): string
    {
        $normalize = static function ($value): string {
            $value = trim((string)$value);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            return mb_strtolower($value, 'UTF-8');
        };

        $parts = [
            (string)(int)$data['type'],
            date('Y-m-d H:i:s', strtotime((string)$data['tarih'])),
            $normalize($data['kategori']),
            $normalize($data['hesap_adi']),
            number_format((float)$data['tutar'], 2, '.', ''),
            $normalize($data['aciklama']),
            $normalize($data['plaka']),
            $normalize($data['odeme_sekli']),
            $normalize($data['banka_adi'])
        ];

        return hash('sha256', implode('|', $parts));
    }
}
