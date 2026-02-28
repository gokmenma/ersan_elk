<?php

namespace App\Model;

use App\Helper\Date;
use App\Helper\Helper;
use App\Model\Model;
use App\Helper\Security;
use PDO;

class DemirbasZimmetModel extends Model
{
    protected $table = 'demirbas_zimmet';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm zimmet kayıtlarını detaylı getirir
     */
    public function getAllWithDetails()
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                d.seri_no,
                k.tur_adi as kategori_adi,
                p.adi_soyadi AS personel_adi,
                p.cep_telefonu AS personel_telefon
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE d.firma_id = ?
            ORDER BY z.kayit_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personele ait zimmetleri getirir
     */
    public function getByPersonel($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                d.seri_no,
                k.tur_adi as kategori_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE z.personel_id = ?
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Demirbaşa ait zimmetleri getirir
     */
    public function getByDemirbas($demirbas_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                p.adi_soyadi AS personel_adi,
                p.cep_telefonu AS personel_telefon
            FROM {$this->table} z
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.demirbas_id = ?
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute([$demirbas_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif zimmetleri getirir (iade edilmemiş)
     */
    public function getActiveZimmetler()
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                k.tur_adi as kategori_adi,
                p.adi_soyadi AS personel_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.durum = 'teslim' AND d.firma_id = ?
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }



    /**
     * Yeni zimmet eklerken stok kontrolü ve düşürme
     */
    public function zimmetVer($data)
    {
        $startedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // Demirbaş stok kontrolü
            $demirbas = $this->db->prepare("SELECT * FROM demirbas WHERE id = ?");
            $demirbas->execute([$data['demirbas_id']]);
            $demirbasData = $demirbas->fetch(PDO::FETCH_OBJ);

            if (!$demirbasData) {
                throw new \Exception("Demirbaş bulunamadı.");
            }

            $teslim_miktar = $data['teslim_miktar'] ?? 1;

            if ($demirbasData->kalan_miktar < $teslim_miktar) {
                throw new \Exception("Yeterli stok bulunmuyor. Mevcut: {$demirbasData->kalan_miktar}");
            }

            // Zimmet kaydı oluştur (eski tablo - geriye uyumluluk)
            $insertSql = $this->db->prepare("
                INSERT INTO {$this->table} 
                (demirbas_id, personel_id, teslim_tarihi, teslim_miktar, durum, aciklama, teslim_eden_id)
                VALUES (?, ?, ?, ?, 'teslim', ?, ?)
            ");
            $insertSql->execute([
                $data['demirbas_id'],
                $data['personel_id'],
                $data['teslim_tarihi'],
                $teslim_miktar,
                $data['aciklama'] ?? null,
                $data['teslim_eden_id'] ?? null
            ]);

            $lastId = $this->db->lastInsertId();

            // Hareket tablosuna kaydet (yeni sistem)
            $hareketSql = $this->db->prepare("
                INSERT INTO demirbas_hareketler 
                (demirbas_id, personel_id, zimmet_id, hareket_tipi, miktar, tarih, islem_id, is_emri_sonucu, aciklama, islem_yapan_id, kaynak)
                VALUES (?, ?, ?, 'zimmet', ?, ?, ?, ?, ?, ?, ?)
            ");
            $hareketSql->execute([
                $data['demirbas_id'],
                $data['personel_id'],
                $lastId,
                $teslim_miktar,
                $data['teslim_tarihi'],
                $data['islem_id'] ?? null,
                $data['is_emri_sonucu'] ?? null,
                $data['aciklama'] ?? null,
                $data['teslim_eden_id'] ?? ($_SESSION['id'] ?? null),
                $data['kaynak'] ?? 'manuel'
            ]);

            // Stok miktarını düşür
            $updateStock = $this->db->prepare("
                UPDATE demirbas 
                SET kalan_miktar = kalan_miktar - ?
                WHERE id = ?
            ");
            $updateStock->execute([$teslim_miktar, $data['demirbas_id']]);

            if ($startedTransaction) {
                $this->db->commit();
            }
            return Security::encrypt($lastId);
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }


    /**
     * Zimmet özet istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN z.durum = 'teslim' THEN 1 END) as aktif_zimmet,
                COUNT(CASE WHEN z.durum = 'iade' THEN 1 END) as iade_edilen,
                COUNT(CASE WHEN z.durum = 'kayip' THEN 1 END) as kayip,
                COUNT(*) as toplam
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            WHERE d.firma_id = ?
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Tablo satırı oluştur
     */
    public function getTableRow($id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                k.tur_adi as kategori_adi,
                p.adi_soyadi AS personel_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.id = ?
        ");
        $sql->execute([$id]);
        $data = $sql->fetch(PDO::FETCH_OBJ);

        if (!$data) {
            return '';
        }

        $enc_id = Security::encrypt($data->id);
        $durumBadge = $this->getDurumBadge($data->durum);
        $teslimTarihi = date('d.m.Y', strtotime($data->teslim_tarihi));

        return '<tr data-id="' . $enc_id . '">
            <td class="text-center">' . $data->id . '</td>
            <td>' . $data->kategori_adi . '</td>
            <td>' . $data->demirbas_adi . '</td>
            <td>' . ($data->marka ?? '-') . ' ' . ($data->model ?? '') . '</td>
            <td>' . $data->personel_adi . '</td>
            <td class="text-center">' . $data->teslim_miktar . '</td>
            <td>' . $teslimTarihi . '</td>
            <td class="text-center">' . $durumBadge . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            ' . ($data->durum === 'teslim' ? '<a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-iade">
                                <span class="mdi mdi-undo font-size-18"></span> İade Al
                            </a>' : '') . '
                            <a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-detay">
                                <span class="mdi mdi-eye font-size-18"></span> Detay
                            </a>
                            <a href="#" class="dropdown-item zimmet-sil" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    private function getDurumBadge($durum)
    {
        $badges = [
            'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
            'iade' => '<span class="badge bg-success">İade Edildi</span>',
            'kayip' => '<span class="badge bg-danger">Kayıp</span>',
            'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
        ];
        return $badges[$durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
    }

    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT 
                    z.*,
                    d.demirbas_no,
                    d.demirbas_adi,
                    d.marka,
                    d.model,
                    d.seri_no,
                    k.tur_adi as kategori_adi,
                    p.adi_soyadi AS personel_adi,
                    p.cep_telefonu AS personel_telefon
                FROM {$this->table} z
                LEFT JOIN demirbas d ON z.demirbas_id = d.id
                LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                LEFT JOIN personel p ON z.personel_id = p.id
                WHERE d.firma_id = :firma_id";

        $params = ['firma_id' => $_SESSION['firma_id']];

        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (d.demirbas_no LIKE :term OR d.demirbas_adi LIKE :term OR p.adi_soyadi LIKE :term OR k.kategori_adi LIKE :term)";
            $params['term'] = $term;
        }

        if (!empty($colSearches)) {
            $colMap = [0 => 'z.id', 1 => 'k.kategori_adi', 2 => 'd.demirbas_adi', 3 => 'd.marka', 4 => 'p.adi_soyadi', 5 => 'z.teslim_miktar', 6 => 'z.teslim_tarihi', 7 => 'z.durum'];
            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;
                    if ($idx == 6) {
                        $sql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                    } else {
                        $sql .= " AND $field LIKE :$paramName";
                    }
                    $params[$paramName] = "%$val%";
                }
            }
        }

        $sql .= " ORDER BY z.kayit_tarihi DESC";
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * DataTables server-side listesi için verileri getirir
     */
    public function getDatatableList($request)
    {
        $start = $request['start'] ?? 0;
        $length = $request['length'] ?? 10;
        $search = $request['search']['value'] ?? null;
        $orderCol = $request['order'][0]['column'] ?? null;
        $orderDir = $request['order'][0]['dir'] ?? 'DESC';

        $baseSql = "SELECT 
                        z.*,
                        d.demirbas_no,
                        d.demirbas_adi,
                        d.marka,
                        d.model,
                        d.seri_no,
                        k.tur_adi as kategori_adi,
                        p.adi_soyadi AS personel_adi,
                        p.cep_telefonu AS personel_telefon
                    FROM {$this->table} z
                    LEFT JOIN demirbas d ON z.demirbas_id = d.id
                    LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                    LEFT JOIN personel p ON z.personel_id = p.id
                    WHERE z.silinme_tarihi IS NULL AND d.firma_id = :firma_id";

        $params = ['firma_id' => $_SESSION['firma_id']];

        // Global Arama
        $searchWhere = "";
        if (!empty($search)) {
            $searchWhere .= " AND (d.demirbas_no LIKE :search 
                            OR d.demirbas_adi LIKE :search 
                            OR p.adi_soyadi LIKE :search 
                            OR k.tur_adi LIKE :search
                            OR d.marka LIKE :search
                            OR d.model LIKE :search
                            OR z.id LIKE :search)";
            $params['search'] = "%$search%";
        }

        // Sütun Bazlı Arama
        $colSearchMap = [
            0 => 'z.id',
            1 => 'k.tur_adi',
            2 => 'd.demirbas_adi',
            3 => 'CONCAT_WS(" ", d.marka, d.model)',
            4 => 'p.adi_soyadi',
            5 => 'z.teslim_miktar',
            6 => 'z.teslim_tarihi',
            7 => 'z.durum'
        ];

        if (isset($request['columns']) && is_array($request['columns'])) {
            foreach ($request['columns'] as $colIdx => $col) {
                if (!empty($col['search']['value']) && isset($colSearchMap[$colIdx])) {
                    $field = $colSearchMap[$colIdx];
                    $searchValue = $col['search']['value'];
                    $paramKey = "col_search_" . $colIdx;

                    // Gelişmiş Filtre Ayrıştırıcı (mode:value)
                    if (strpos($searchValue, ':') !== false) {
                        list($mode, $val) = explode(':', $searchValue, 2);

                        // Değerleri ayır
                        $vals = explode('|', $val);
                        $val = $vals[0];
                        $val2 = isset($vals[1]) ? $vals[1] : null;

                        if ($val !== '' || $val2 !== null || in_array($mode, ['null', 'not_null', 'multi'])) {
                            // Değer eşleme (Mapping)
                            $durumReverseMap = [
                                'Zimmetli' => 'teslim',
                                'İade Edildi' => 'iade',
                                'Kayıp' => 'kayip',
                                'Arızalı' => 'arizali'
                            ];

                            // Tarih sütunu (index 6) için d.m.Y -> Y-m-d dönüşümü
                            if ($colIdx == 6) {
                                if ($val && strpos($val, '.') !== false)
                                    $val = \App\Helper\Date::Ymd($val, 'Y-m-d');
                                if ($val2 && strpos($val2, '.') !== false)
                                    $val2 = \App\Helper\Date::Ymd($val2, 'Y-m-d');
                            }

                            // Durum eşleme
                            if ($field == 'z.durum') {
                                if (isset($durumReverseMap[$val]))
                                    $val = $durumReverseMap[$val];
                                if ($val2 && isset($durumReverseMap[$val2]))
                                    $val2 = $durumReverseMap[$val2];
                            }

                            switch ($mode) {
                                case 'multi':
                                    if (!empty($vals)) {
                                        $orConditions = [];
                                        foreach ($vals as $vIdx => $v) {
                                            $vParam = $paramKey . "_" . $vIdx;

                                            // Mapping apply to multi
                                            if ($field == 'z.durum' && isset($durumReverseMap[$v]))
                                                $v = $durumReverseMap[$v];

                                            if ($v === '(Boş)') {
                                                $orConditions[] = "($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                            } else {
                                                if ($colIdx == 6 && strpos($v, '.') !== false) {
                                                    $v = \App\Helper\Date::Ymd($v, 'Y-m-d');
                                                    $orConditions[] = "$field = :$vParam";
                                                    $params[$vParam] = $v;
                                                } else {
                                                    $orConditions[] = "$field LIKE :$vParam";
                                                    $params[$vParam] = "%$v%";
                                                }
                                            }
                                        }
                                        $searchWhere .= " AND (" . implode(" OR ", $orConditions) . ")";
                                    }
                                    break;
                                case 'contains':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'not_contains':
                                    $searchWhere .= " AND $field NOT LIKE :$paramKey";
                                    $params[$paramKey] = "%$val%";
                                    break;
                                case 'starts_with':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "$val%";
                                    break;
                                case 'ends_with':
                                    $searchWhere .= " AND $field LIKE :$paramKey";
                                    $params[$paramKey] = "%$val";
                                    break;
                                case 'equals':
                                    $searchWhere .= " AND $field = :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'not_equals':
                                    $searchWhere .= " AND $field != :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'before':
                                    $searchWhere .= " AND $field < :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'after':
                                    $searchWhere .= " AND $field > :$paramKey";
                                    $params[$paramKey] = $val;
                                    break;
                                case 'between':
                                    if ($val && $val2) {
                                        $p1 = $paramKey . "_1";
                                        $p2 = $paramKey . "_2";
                                        $searchWhere .= " AND $field BETWEEN :$p1 AND :$p2";
                                        $params[$p1] = $val;
                                        $params[$p2] = $val2;
                                    }
                                    break;
                                case 'null':
                                    $searchWhere .= " AND ($field IS NULL OR $field = '' OR $field = '0000-00-00')";
                                    break;
                                case 'not_null':
                                    $searchWhere .= " AND $field IS NOT NULL AND $field != '' AND $field != '0000-00-00'";
                                    break;
                            }
                        }
                    } else {
                        // Normal (Eski) Filtre Mantığı (Sadece LIKE)
                        if ($colIdx == 6) { // Tarih
                            $searchWhere .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramKey";
                        } else {
                            $searchWhere .= " AND $field LIKE :$paramKey";
                        }
                        $params[$paramKey] = "%$searchValue%";
                    }
                }
            }
        }

        // Kategori Bazlı Filtreleme
        $filterType = $request['filter_type'] ?? 'all';
        $sayacKatIds = $request['sayac_kat_ids'] ?? [];
        $aparatKatIds = $request['aparat_kat_ids'] ?? [];

        if ($filterType === 'sayac' && !empty($sayacKatIds)) {
            $ids = implode(',', array_map('intval', $sayacKatIds));
            $searchWhere .= " AND d.kategori_id IN ($ids)";
        } elseif ($filterType === 'aparat' && !empty($aparatKatIds)) {
            $ids = implode(',', array_map('intval', $aparatKatIds));
            $searchWhere .= " AND d.kategori_id IN ($ids)";
        } elseif ($filterType === 'demirbas') {
            $allExclude = array_filter(array_merge((array) $sayacKatIds, (array) $aparatKatIds));
            if (!empty($allExclude)) {
                $ids = implode(',', array_map('intval', $allExclude));
                $searchWhere .= " AND (d.kategori_id NOT IN ($ids) OR d.kategori_id IS NULL)";
            }
        }

        // Personel Bazlı Filtreleme
        $personelId = $request['personel_id'] ?? 'all';
        if ($personelId !== 'all' && $personelId > 0) {
            $searchWhere .= " AND z.personel_id = :personel_id";
            $params['personel_id'] = $personelId;
        }

        // Toplam kayıt sayısı (filtresiz)
        $totalSql = "SELECT COUNT(*) FROM {$this->table} z LEFT JOIN demirbas d ON z.demirbas_id = d.id WHERE z.silinme_tarihi IS NULL AND d.firma_id = :firma_id_total";
        $stmtTotal = $this->db->prepare($totalSql);
        $stmtTotal->execute(['firma_id_total' => $_SESSION['firma_id']]);
        $totalRecords = $stmtTotal->fetchColumn();

        // Filtrelenmiş kayıt sayısı
        $filterSql = "SELECT COUNT(*) FROM ({$baseSql} {$searchWhere}) AS temp";
        $stmtFilter = $this->db->prepare($filterSql);
        foreach ($params as $key => $val) {
            $stmtFilter->bindValue($key, $val);
        }
        $stmtFilter->execute();
        $recordsFiltered = $stmtFilter->fetchColumn();

        // Sıralama
        $colMapOrder = [
            0 => 'z.id',
            1 => 'k.kategori_adi',
            2 => 'd.demirbas_adi',
            3 => 'd.marka',
            4 => 'p.adi_soyadi',
            5 => 'z.teslim_miktar',
            6 => 'z.teslim_tarihi',
            7 => 'z.durum'
        ];

        $orderSql = "";
        if ($orderCol !== null && isset($colMapOrder[$orderCol])) {
            $orderSql = " ORDER BY " . $colMapOrder[$orderCol] . " " . ($orderDir === 'asc' ? 'ASC' : 'DESC');
        } else {
            $orderSql = " ORDER BY z.kayit_tarihi DESC";
        }

        // Limit
        $limitSql = " LIMIT :start, :length";

        $finalSql = $baseSql . $searchWhere . $orderSql . $limitSql;
        $stmt = $this->db->prepare($finalSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue('length', (int) $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ];
    }
    /**
     * Zimmet iade işlemi (Kısmi iade destekli)
     */
    public function iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama = null, $islem_id = null, $is_emri_sonucu = null, $kaynak = 'manuel')
    {
        $startedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // Zimmet bilgisini al
            $sql = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $sql->execute([$zimmet_id]);
            $zimmet = $sql->fetch(PDO::FETCH_OBJ);

            if (!$zimmet) {
                throw new \Exception("Zimmet kaydı bulunamadı.");
            }

            // Mevcut iade edilmiş miktar ve kalan kontrolü
            $mevcutIadeMiktari = (int) ($zimmet->iade_miktar ?? 0);
            $teslimMiktari = (int) $zimmet->teslim_miktar;
            $kalanZimmetMiktari = $teslimMiktari - $mevcutIadeMiktari;

            // İade edilecek miktar, elimizdeki zimmetten fazla olamaz
            if ($iade_miktar > $kalanZimmetMiktari) {
                $iade_miktar = $kalanZimmetMiktari;
            }

            if ($iade_miktar <= 0) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }
                return true; // Zaten iade edilmiş veya kalan yok
            }

            $yeniToplamIade = $mevcutIadeMiktari + $iade_miktar;
            $yeniDurum = ($yeniToplamIade >= $teslimMiktari) ? 'iade' : 'teslim';

            // Zimmet durumunu güncelle (eski tablo - geriye uyumluluk)
            $sql = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = ?, 
                    iade_tarihi = ?, 
                    iade_miktar = ?,
                    aciklama = CONCAT(COALESCE(aciklama, ''), '\n', ?)
                WHERE id = ?
            ");
            $sql->execute([$yeniDurum, Date::Ymd($iade_tarihi, 'Y-m-d'), $yeniToplamIade, $aciklama ?? '', $zimmet_id]);

            // Hareket tablosuna kaydet (yeni sistem)
            $hareketSql = $this->db->prepare("
                INSERT INTO demirbas_hareketler 
                (demirbas_id, personel_id, zimmet_id, hareket_tipi, miktar, tarih, islem_id, is_emri_sonucu, aciklama, islem_yapan_id, kaynak)
                VALUES (?, ?, ?, 'iade', ?, ?, ?, ?, ?, ?, ?)
            ");
            $hareketSql->execute([
                $zimmet->demirbas_id,
                $zimmet->personel_id,
                $zimmet_id,
                $iade_miktar,
                Date::Ymd($iade_tarihi, 'Y-m-d'),
                $islem_id,
                $is_emri_sonucu,
                $aciklama,
                $_SESSION['id'] ?? null,
                $kaynak
            ]);

            // Demirbaş stok miktarını artır
            $sqlDemirbas = $this->db->prepare("
                UPDATE demirbas 
                SET kalan_miktar = kalan_miktar + ?
                WHERE id = ?
            ");
            $sqlDemirbas->execute([$iade_miktar, $zimmet->demirbas_id]);

            if ($startedTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }


    private static $_autoTriggers = null;

    /**
     * İş emri sonucuna göre otomatik zimmet/iade işlemlerini kontrol eder ve yürütür
     */
    public function checkAndProcessAutomaticZimmet($personel_id, $is_emri_sonucu, $tarih, $islem_id = null, $miktar = 1, $mode = 'both')
    {
        if (empty($personel_id) || empty($is_emri_sonucu))
            return ['status' => 'ignored'];

        // Tetikleyicileri bir kez çek ve cache'le (Performans için)
        if (self::$_autoTriggers === null) {
            $sql = $this->db->prepare("
                SELECT id, demirbas_adi, otomatik_zimmet_is_emri, otomatik_iade_is_emri 
                FROM demirbas 
                WHERE ((otomatik_zimmet_is_emri IS NOT NULL AND otomatik_zimmet_is_emri != '')
                OR (otomatik_iade_is_emri IS NOT NULL AND otomatik_iade_is_emri != '')) AND firma_id = ?
            ");
            $sql->execute([$_SESSION['firma_id']]);
            self::$_autoTriggers = $sql->fetchAll(PDO::FETCH_OBJ);
        }

        $miktar = (int) ($miktar <= 0 ? 1 : $miktar);
        $searchSonuc = trim($is_emri_sonucu);

        $zimmetAdaylari = [];
        $iadeAdaylari = [];

        foreach (self::$_autoTriggers as $t) {
            if ($t->otomatik_zimmet_is_emri && trim($t->otomatik_zimmet_is_emri) === $searchSonuc) {
                $zimmetAdaylari[] = $t;
            }
            if ($t->otomatik_iade_is_emri && trim($t->otomatik_iade_is_emri) === $searchSonuc) {
                $iadeAdaylari[] = $t;
            }
        }

        if (empty($zimmetAdaylari) && empty($iadeAdaylari)) {
            return ['status' => 'no_trigger'];
        }

        $results = ['zimmet' => [], 'iade' => []];

        // 1. ZİMMET İŞLEMLERİ
        if (($mode === 'both' || $mode === 'zimmet') && !empty($zimmetAdaylari)) {
            foreach ($zimmetAdaylari as $d) {
                try {
                    // Mükerrer kontrolü
                    if ($islem_id) {
                        $checkSql = $this->db->prepare("SELECT id FROM {$this->table} WHERE demirbas_id = ? AND personel_id = ? AND aciklama LIKE ? LIMIT 1");
                        $checkSql->execute([$d->id, $personel_id, "%İşlem ID: $islem_id%"]);
                        if ($checkSql->fetch())
                            continue;
                    }

                    $this->zimmetVer([
                        'demirbas_id' => $d->id,
                        'personel_id' => $personel_id,
                        'teslim_tarihi' => $tarih,
                        'teslim_miktar' => $miktar,
                        'aciklama' => "Otomatik Zimmet (İşlem ID: $islem_id - Sonuç: $is_emri_sonucu)",
                        'islem_id' => $islem_id,
                        'is_emri_sonucu' => $is_emri_sonucu,
                        'kaynak' => 'puantaj_excel'
                    ]);
                    $results['zimmet'][] = ['status' => 'success'];
                } catch (\Exception $e) {
                }
            }
        }

        // 2. İADE İŞLEMLERİ
        if (($mode === 'both' || $mode === 'iade') && !empty($iadeAdaylari)) {
            // Sadece bu iş emri sonucuna bağlı iadeleri bul
            $sqlIade = $this->db->prepare("
                SELECT z.id, d.demirbas_adi, d.id as demirbas_id, z.teslim_miktar, z.iade_miktar
                FROM {$this->table} z
                INNER JOIN demirbas d ON z.demirbas_id = d.id
                WHERE TRIM(d.otomatik_iade_is_emri) = ? 
                AND z.personel_id = ? 
                AND z.durum = 'teslim'
                ORDER BY z.teslim_tarihi ASC
            ");
            $sqlIade->execute([$searchSonuc, $personel_id]);
            $mevcutZimmetler = $sqlIade->fetchAll(PDO::FETCH_OBJ);

            $kalanIadeIhtiyaci = $miktar;
            foreach ($mevcutZimmetler as $z) {
                if ($kalanIadeIhtiyaci <= 0)
                    break;

                // Mükerrer kontrolü
                if ($islem_id) {
                    $sqlCheckAuto = $this->db->prepare("SELECT id FROM {$this->table} WHERE id = ? AND aciklama LIKE ? LIMIT 1");
                    $sqlCheckAuto->execute([$z->id, "%İşlem ID: $islem_id%"]);
                    if ($sqlCheckAuto->fetch())
                        continue;
                }

                $mevcutIade = (int) ($z->iade_miktar ?? 0);
                $kalanZimmet = (int) $z->teslim_miktar - $mevcutIade;
                if ($kalanZimmet <= 0)
                    continue;

                $suAnkiIade = min($kalanZimmet, $kalanIadeIhtiyaci);
                try {
                    $this->iadeYap($z->id, $tarih, $suAnkiIade, "Otomatik İade (İşlem ID: $islem_id - Sonuç: $is_emri_sonucu)", $islem_id, $is_emri_sonucu, 'puantaj_excel');
                    $kalanIadeIhtiyaci -= $suAnkiIade;
                    $results['iade'][] = ['status' => 'success'];
                } catch (\Exception $e) {
                }
            }
        }

        return $results;
    }

    /**
     * İade işlemini siler (Geri alır)
     */
    public function iadeSil($hareket_id)
    {
        $startedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // 1. Hareket bilgisini al
            $sql = $this->db->prepare("SELECT * FROM demirbas_hareketler WHERE id = ? AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL");
            $sql->execute([$hareket_id]);
            $h = $sql->fetch(PDO::FETCH_OBJ);

            if (!$h) {
                throw new \Exception("İade hareket kaydı bulunamadı.");
            }

            // 2. Zimmet bilgisini al
            $sqlZimmet = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $sqlZimmet->execute([$h->zimmet_id]);
            $z = $sqlZimmet->fetch(PDO::FETCH_OBJ);

            if (!$z) {
                throw new \Exception("Zimmet kaydı bulunamadı.");
            }

            // 3. Demirbaş stok miktarını düzelte (azalt - çünkü iade siliniyor)
            $sqlDemirbas = $this->db->prepare("
                UPDATE demirbas 
                SET kalan_miktar = kalan_miktar - ?
                WHERE id = ?
            ");
            $sqlDemirbas->execute([$h->miktar, $h->demirbas_id]);

            // 4. Zimmet kaydını güncelle
            $yeniIadeMiktari = (int) ($z->iade_miktar ?? 0) - (int) $h->miktar;
            if ($yeniIadeMiktari < 0)
                $yeniIadeMiktari = 0;

            // Eğer iade miktarı teslim miktarından az ise durum 'teslim' olur
            $yeniDurum = ($yeniIadeMiktari < (int) $z->teslim_miktar) ? 'teslim' : 'iade';

            $sqlUpZimmet = $this->db->prepare("
                UPDATE {$this->table} 
                SET iade_miktar = ?, durum = ?, iade_tarihi = CASE WHEN ? = 0 THEN NULL ELSE iade_tarihi END
                WHERE id = ?
            ");
            $sqlUpZimmet->execute([$yeniIadeMiktari, $yeniDurum, $yeniIadeMiktari, $h->zimmet_id]);

            // 5. Hareketi sil (soft delete)
            $sqlDelHareket = $this->db->prepare("UPDATE demirbas_hareketler SET silinme_tarihi = NOW() WHERE id = ?");
            $sqlDelHareket->execute([$hareket_id]);

            if ($startedTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

}
