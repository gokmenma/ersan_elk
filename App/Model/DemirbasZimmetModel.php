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
    private $Hareket;

    public function __construct()
    {
        parent::__construct($this->table);
        $this->Hareket = new DemirbasHareketModel();
    }

    /**
     * Bir zimmet kaydı için işlenmiş (iade/sarf) toplam miktarı getirir
     */
    public function getProcessedAmount($zimmet_id)
    {
        $sql = $this->db->prepare("SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = ? AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL");
        $sql->execute([$zimmet_id]);
        return (int) $sql->fetchColumn();
    }

    public function getZimmetCols()
    {
        return "z.id, z.demirbas_id, z.personel_id, z.teslim_tarihi, z.teslim_miktar, z.durum, z.aciklama, z.teslim_eden_id, z.kayit_tarihi, z.guncelleme_tarihi, z.silinme_tarihi";
    }

    public function find($id)
    {
        $id = is_numeric($id) ? $id : Security::decrypt($id);
        if (!$id)
            return null;

        $sql = $this->db->prepare("
            SELECT 
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
                (z.teslim_miktar - " . $this->getProcessedAmountSubquery() . ") as kalan_miktar
            FROM {$this->table} z
            WHERE z.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ) ?? null;
    }

    private function getProcessedAmountSubquery()
    {
        return "(SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler h_sub WHERE h_sub.zimmet_id = z.id AND h_sub.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h_sub.silinme_tarihi IS NULL)";
    }

    private function getProcessedDateSubquery()
    {
        return "(SELECT MAX(tarih) FROM demirbas_hareketler h_sub WHERE h_sub.zimmet_id = z.id AND h_sub.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h_sub.silinme_tarihi IS NULL)";
    }

    /**
     * Override delete to handle stock management
     */
    public function delete($id, $decrypt = true)
    {
        $raw_id = $decrypt ? Security::decrypt($id) : $id;

        $zimmet = $this->find($raw_id);
        if (!$zimmet)
            return false;

        $startedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // If it's active (teslim), restore stock to demirbas
            if ($zimmet->durum === 'teslim') {
                $processed = $this->getProcessedAmount($raw_id);
                $kalan_zimmet = (int) $zimmet->teslim_miktar - $processed;
                if ($kalan_zimmet > 0) {
                    $sqlStok = $this->db->prepare("UPDATE demirbas SET kalan_miktar = kalan_miktar + ? WHERE id = ?");
                    $sqlStok->execute([$kalan_zimmet, $zimmet->demirbas_id]);
                }
            }

            // Delete related movements (optional but recommended for data integrity since the zimmet is as if it never happened)
            $sqlDelHareketArr = $this->db->prepare("DELETE FROM demirbas_hareketler WHERE zimmet_id = ?");
            $sqlDelHareketArr->execute([$raw_id]);

            $result = parent::delete($id, $decrypt);

            if ($startedTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Tüm zimmet kayıtlarını detaylı getirir
     */
    public function getAllWithDetails()
    {
        $sql = $this->db->prepare("
            SELECT 
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
            $demirbas = $this->db->prepare("
                SELECT *, 
                (COALESCE(miktar, 1) - COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'zimmet' AND silinme_tarihi IS NULL), 0) + COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL), 0)) as kalan_miktar
                FROM demirbas WHERE id = ?
            ");
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
            $this->Hareket->hareketEkle([
                'demirbas_id' => $data['demirbas_id'],
                'personel_id' => $data['personel_id'],
                'zimmet_id' => $lastId,
                'hareket_tipi' => 'zimmet',
                'miktar' => $teslim_miktar,
                'tarih' => $data['teslim_tarihi'],
                'islem_id' => $data['islem_id'] ?? null,
                'is_emri_sonucu' => $data['is_emri_sonucu'] ?? null,
                'aciklama' => $data['aciklama'] ?? null,
                'islem_yapan_id' => $data['teslim_eden_id'] ?? ($_SESSION['id'] ?? null),
                'kaynak' => $data['kaynak'] ?? 'manuel'
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
                " . $this->getZimmetCols() . ",
                " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
        $katAdiLower = mb_strtolower($data->kategori_adi ?? '', 'UTF-8');
        $isAparat = str_contains($katAdiLower, 'aparat');
        $durumBadge = $this->getDurumBadge($data->durum, $isAparat);
        $teslimTarihi = date('d.m.Y', strtotime($data->teslim_tarihi));

        $iadeLabel = $isAparat ? 'Tüketildi İşaretle' : 'İade Al';

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
                            ' . ($data->durum === 'teslim' ? '<a href="#" data-id="' . $enc_id . '" data-is-aparat="' . ($isAparat ? '1' : '0') . '" class="dropdown-item zimmet-iade">
                                <span class="mdi mdi-undo font-size-18"></span> ' . $iadeLabel . '
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

    private function getDurumBadge($durum, $isAparat = false)
    {
        if ($isAparat) {
            $badges = [
                'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                'iade' => '<span class="badge bg-danger">Tüketildi</span>',
                'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
            ];
        } else {
            $badges = [
                'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
                'iade' => '<span class="badge bg-success">İade Edildi</span>',
                'kayip' => '<span class="badge bg-danger">Kayıp</span>',
                'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
            ];
        }
        return $badges[$durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
    }


    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT 
                    " . $this->getZimmetCols() . ",
                    " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                    " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
            $colMap = [1 => 'z.id', 2 => 'k.kategori_adi', 3 => 'd.demirbas_adi', 4 => 'd.marka', 5 => 'p.adi_soyadi', 6 => 'z.teslim_miktar', 7 => 'z.teslim_tarihi', 8 => 'z.durum'];
            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;
                    if ($idx == 7) {
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
                        " . $this->getZimmetCols() . ",
                        " . $this->getProcessedAmountSubquery() . " as iade_miktar,
                        " . $this->getProcessedDateSubquery() . " as iade_tarihi,
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
                            OR d.seri_no LIKE :search
                            OR z.id LIKE :search)";
            $params['search'] = "%$search%";
        }

        // Sütun Bazlı Arama
        $colSearchMap = [
            2 => 'k.tur_adi',
            3 => 'd.demirbas_adi',
            4 => 'CONCAT_WS(" ", d.marka, d.model, d.seri_no)',
            5 => 'p.adi_soyadi',
            6 => 'z.teslim_miktar',
            7 => 'z.teslim_tarihi',
            8 => 'z.durum'
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

                            // Tarih sütunu (index 7) için d.m.Y -> Y-m-d dönüşümü
                            if ($colIdx == 7) {
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
                                                if ($colIdx == 7 && strpos($v, '.') !== false) {
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
                        if ($colIdx == 7) { // Tarih
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
            1 => 'z.id',
            2 => 'k.kategori_adi',
            3 => 'd.demirbas_adi',
            4 => 'd.marka',
            5 => 'p.adi_soyadi',
            6 => 'z.teslim_miktar',
            7 => 'z.teslim_tarihi',
            8 => 'z.durum'
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

            $processed = $this->getProcessedAmount($zimmet_id);
            $teslimMiktari = (int) $zimmet->teslim_miktar;
            $yeniToplamIade = $processed + $iade_miktar;
            $yeniDurum = ($yeniToplamIade >= $teslimMiktari) ? 'iade' : 'teslim';

            // Zimmet durumunu güncelle (eski tablo - geriye uyumluluk)
            $sql = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = ?, 
                    aciklama = CONCAT(COALESCE(aciklama, ''), '\n', ?)
                WHERE id = ?
            ");
            $sql->execute([$yeniDurum, $aciklama ?? '', $zimmet_id]);

            // Hareket tablosuna kaydet (yeni sistem)
            $this->Hareket->hareketEkle([
                'demirbas_id' => $zimmet->demirbas_id,
                'personel_id' => $zimmet->personel_id,
                'zimmet_id' => $zimmet_id,
                'hareket_tipi' => 'iade',
                'miktar' => $iade_miktar,
                'tarih' => $iade_tarihi,
                'islem_id' => $islem_id,
                'is_emri_sonucu' => $is_emri_sonucu,
                'aciklama' => $aciklama,
                'islem_yapan_id' => $_SESSION['id'] ?? null,
                'kaynak' => $kaynak
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


    /**
     * Tüketim / Sarf İşlemi (Zimmetten düşer, depoya dönmez, makine/sayaç tüketilmiştir)
     */
    public function tuketimYap($zimmet_id, $tarih, $tuketim_miktar, $aciklama = null, $islem_id = null, $is_emri_sonucu = null, $kaynak = 'manuel')
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

            $processed = $this->getProcessedAmount($zimmet_id);
            $teslimMiktari = (int) $zimmet->teslim_miktar;
            $yeniToplamTuketim = $processed + $tuketim_miktar;
            $yeniDurum = ($yeniToplamTuketim >= $teslimMiktari) ? 'iade' : 'teslim'; // iade means closed in this context

            // Zimmet durumunu güncelle
            $sql = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = ?, 
                    aciklama = CONCAT(COALESCE(aciklama, ''), '\n', ?)
                WHERE id = ?
            ");
            $sql->execute([$yeniDurum, $aciklama ?? '', $zimmet_id]);

            // Hareket tablosuna kaydet (sarf hareketi)
            $this->Hareket->hareketEkle([
                'demirbas_id' => $zimmet->demirbas_id,
                'personel_id' => $zimmet->personel_id,
                'zimmet_id' => $zimmet_id,
                'hareket_tipi' => 'sarf',
                'miktar' => $tuketim_miktar,
                'tarih' => $tarih,
                'islem_id' => $islem_id,
                'is_emri_sonucu' => $is_emri_sonucu,
                'aciklama' => $aciklama,
                'islem_yapan_id' => $_SESSION['id'] ?? null,
                'kaynak' => $kaynak
            ]);

            // Tüketim olduğu için demirbaşın toplam miktarını düşür. (kalan_miktar zimmet verildiğinde düşmüştü zaten)
            $sqlDemirbas = $this->db->prepare("
                UPDATE demirbas 
                SET miktar = miktar - ?
                WHERE id = ?
            ");
            $sqlDemirbas->execute([$tuketim_miktar, $zimmet->demirbas_id]);

            // Eğer demirbaşın kalanı ve miktarı sıfırlandıysa pasif yap
            if ($yeniDurum === 'iade') {
                $this->db->prepare("UPDATE demirbas SET durum = 'pasif' WHERE id = ? AND miktar <= 0 AND kalan_miktar <= 0")->execute([$zimmet->demirbas_id]);
            }

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
    private static $_dusTriggers = null;

    /**
     * İş emri sonucuna göre otomatik zimmet/iade işlemlerini kontrol eder ve yürütür
     * 
     * APARAT KATEGORİSİ İÇİN ÖZEL DAVRANIŞ:
     * - Aparatlar depodan personele MANUEL olarak verilir
     * - otomatik_zimmet_is_emri_id geldiğinde: Personelin mevcut zimmetindeki aparatı "tüketildi" olarak işaretler
     * - otomatik_iade_is_emri_id geldiğinde: Personelden depoya geri alır (stok artar)
     * - otomatik_zimmetten_dus_is_emri_ids geldiğinde: Personeldeki zimmeti TAMAMEN düşürür (kırılma, çalınma vb.)
     */
    public function checkAndProcessAutomaticZimmet($personel_id, $is_emri_sonucu_id, $tarih, $islem_id = null, $miktar = 1, $mode = 'both')
    {
        if (empty($personel_id) || empty($is_emri_sonucu_id))
            return ['status' => 'ignored'];

        // Tetikleyicileri bir kez çek ve cache'le (Performans için) - ID bazlı
        if (self::$_autoTriggers === null) {
            $sql = $this->db->prepare("
                SELECT d.id, d.demirbas_adi, d.otomatik_zimmet_is_emri_ids, d.otomatik_iade_is_emri_ids, 
                       d.otomatik_zimmetten_dus_is_emri_ids, d.kategori_id,
                       COALESCE(k.tur_adi, '') as kategori_adi,
                       d.otomatik_zimmet_is_emri, d.otomatik_iade_is_emri
                FROM demirbas d
                LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                WHERE (
                    (d.otomatik_zimmet_is_emri_ids IS NOT NULL AND d.otomatik_zimmet_is_emri_ids != '')
                    OR (d.otomatik_iade_is_emri_ids IS NOT NULL AND d.otomatik_iade_is_emri_ids != '')
                    OR (d.otomatik_zimmetten_dus_is_emri_ids IS NOT NULL AND d.otomatik_zimmetten_dus_is_emri_ids != '')
                    OR (d.otomatik_zimmet_is_emri IS NOT NULL AND d.otomatik_zimmet_is_emri != '')
                    OR (d.otomatik_iade_is_emri IS NOT NULL AND d.otomatik_iade_is_emri != '')
                ) AND d.firma_id = ?
            ");
            $sql->execute([$_SESSION['firma_id']]);
            self::$_autoTriggers = $sql->fetchAll(PDO::FETCH_OBJ);
        }

        $miktar = (int) ($miktar <= 0 ? 1 : $miktar);

        // Gelen iş emri sonucu ID'sini ve metin karşılığını bul
        $incomingId = null;
        $searchSonuc = trim($is_emri_sonucu_id);

        if (is_numeric($searchSonuc)) {
            $incomingId = (int) $searchSonuc;
            try {
                $sqlTanim = $this->db->prepare("SELECT is_emri_sonucu FROM tanimlamalar WHERE id = ?");
                $sqlTanim->execute([$incomingId]);
                $tanimRes = $sqlTanim->fetch(PDO::FETCH_OBJ);
                if ($tanimRes && !empty($tanimRes->is_emri_sonucu)) {
                    $searchSonuc = trim($tanimRes->is_emri_sonucu);
                }
            } catch (\Exception $e) {
            }
        }

        $searchSonucLower = mb_strtolower($searchSonuc, 'UTF-8');

        $zimmetAdaylari = [];
        $iadeAdaylari = [];
        $dusAdaylari = [];

        foreach (self::$_autoTriggers as $t) {
            // ID bazlı eşleşme (yeni sistem - tüm alanlar çoklu)
            if ($incomingId) {
                // Zimmet - virgülle ayrılmış ID listesi
                if (!empty($t->otomatik_zimmet_is_emri_ids)) {
                    $zimmetIds = array_map('intval', array_filter(explode(',', $t->otomatik_zimmet_is_emri_ids)));
                    if (in_array($incomingId, $zimmetIds)) {
                        $zimmetAdaylari[] = $t;
                    }
                }
                // İade - virgülle ayrılmış ID listesi
                if (!empty($t->otomatik_iade_is_emri_ids)) {
                    $iadeIds = array_map('intval', array_filter(explode(',', $t->otomatik_iade_is_emri_ids)));
                    if (in_array($incomingId, $iadeIds)) {
                        $iadeAdaylari[] = $t;
                    }
                }
                // Zimmetten düşme - virgülle ayrılmış ID listesi
                if (!empty($t->otomatik_zimmetten_dus_is_emri_ids)) {
                    $dusIds = array_map('intval', array_filter(explode(',', $t->otomatik_zimmetten_dus_is_emri_ids)));
                    if (in_array($incomingId, $dusIds)) {
                        $dusAdaylari[] = $t;
                    }
                }
            }

            // Text bazlı eşleşme (eski sistem - geriye uyumluluk)
            if ($t->otomatik_zimmet_is_emri && empty($t->otomatik_zimmet_is_emri_ids) && mb_strtolower(trim($t->otomatik_zimmet_is_emri), 'UTF-8') === $searchSonucLower) {
                if (!in_array($t, $zimmetAdaylari, true)) {
                    $zimmetAdaylari[] = $t;
                }
            }
            if ($t->otomatik_iade_is_emri && empty($t->otomatik_iade_is_emri_ids) && mb_strtolower(trim($t->otomatik_iade_is_emri), 'UTF-8') === $searchSonucLower) {
                if (!in_array($t, $iadeAdaylari, true)) {
                    $iadeAdaylari[] = $t;
                }
            }
        }

        if (empty($zimmetAdaylari) && empty($iadeAdaylari) && empty($dusAdaylari)) {
            return ['status' => 'no_trigger'];
        }

        $results = ['zimmet' => [], 'iade' => [], 'dus' => []];

        // 1. ZİMMET İŞLEMLERİ
        if (($mode === 'both' || $mode === 'zimmet') && !empty($zimmetAdaylari)) {
            // Aparat ve normal zimmetleri ayır
            $aparatAdaylari = [];
            $normalAdaylari = [];
            foreach ($zimmetAdaylari as $d) {
                if ($this->isAparatKategorisi($d)) {
                    $aparatAdaylari[] = $d;
                } else {
                    $normalAdaylari[] = $d;
                }
            }

            // ===== APARAT TÜKETİM: Tüm aparat markaları arasında paylaşılan Global FIFO =====
            if (!empty($aparatAdaylari)) {
                $kalanTuketimIhtiyaci = $miktar;

                $katIds = [];
                foreach ($aparatAdaylari as $d) {
                    if ($d->kategori_id) {
                        $katIds[] = $d->kategori_id;
                    }
                }
                $katIds = array_unique($katIds);

                if (!empty($katIds)) {
                    $placeholders = str_repeat('?,', count($katIds) - 1) . '?';
                    $params = array_merge($katIds, [$personel_id]);

                    try {
                        $sqlMevcutZimmet = $this->db->prepare("
                            SELECT z.id, z.demirbas_id, z.teslim_miktar, z.iade_miktar
                            FROM {$this->table} z
                            INNER JOIN demirbas d ON z.demirbas_id = d.id
                            WHERE d.kategori_id IN ($placeholders) AND z.personel_id = ? AND z.durum = 'teslim'
                            ORDER BY z.teslim_tarihi ASC, z.id ASC
                        ");
                        $sqlMevcutZimmet->execute($params);
                        $mevcutZimmetler = $sqlMevcutZimmet->fetchAll(PDO::FETCH_OBJ);

                        if (empty($mevcutZimmetler)) {
                            $pName = "Personel ID: $personel_id";
                            try {
                                $stmtP = $this->db->prepare("SELECT adi_soyadi FROM personel WHERE id = ?");
                                $stmtP->execute([$personel_id]);
                                $pName = $stmtP->fetchColumn() ?: $pName;
                            } catch (\Exception $e) {
                            }

                            $results['zimmet'][] = [
                                'status' => 'error',
                                'type' => 'no_zimmet_found',
                                'personel_id' => $personel_id,
                                'personel_adi' => $pName,
                                'demirbas_adi' => 'Aparat'
                            ];
                            $kalanTuketimIhtiyaci = 0;
                        } else {
                            foreach ($mevcutZimmetler as $z) {
                                if ($kalanTuketimIhtiyaci <= 0)
                                    break;

                                // Mükerrer kontrolü
                                if ($islem_id) {
                                    $sqlCheckAuto = $this->db->prepare("SELECT id FROM demirbas_hareketler WHERE zimmet_id = ? AND islem_id = ? AND hareket_tipi = 'sarf' AND silinme_tarihi IS NULL LIMIT 1");
                                    $sqlCheckAuto->execute([$z->id, $islem_id]);
                                    if ($sqlCheckAuto->fetch())
                                        continue;
                                }

                                $mevcutIade = (int) ($z->iade_miktar ?? 0);
                                $kalanZimmet = (int) $z->teslim_miktar - $mevcutIade;
                                if ($kalanZimmet <= 0)
                                    continue;

                                $suAnkiTuketim = min($kalanZimmet, $kalanTuketimIhtiyaci);
                                try {
                                    $this->tuketimYap(
                                        $z->id,
                                        $tarih,
                                        $suAnkiTuketim,
                                        "Otomatik Tüketim - Aparat (İşlem ID: $islem_id - Sonuç: $searchSonuc)",
                                        $islem_id,
                                        $searchSonuc,
                                        'puantaj_excel'
                                    );
                                    $kalanTuketimIhtiyaci -= $suAnkiTuketim;
                                    $results['iade'][] = ['status' => 'success', 'type' => 'aparat_tuketim'];
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            // ===== DİĞER KATEGORİLER: Normal zimmet verme (depodan personele) =====
            foreach ($normalAdaylari as $d) {
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
                        'aciklama' => "Otomatik Zimmet (İşlem ID: $islem_id - Sonuç: $searchSonuc)",
                        'islem_id' => $islem_id,
                        'is_emri_sonucu' => $searchSonuc,
                        'kaynak' => 'puantaj_excel'
                    ]);
                    $results['zimmet'][] = ['status' => 'success'];
                } catch (\Exception $e) {
                }
            }

        }

        // 2. İADE İŞLEMLERİ (Otomatik Düşüm / Tüketim ve Hurda Oluşturma)
        if (($mode === 'both' || $mode === 'iade') && !empty($iadeAdaylari)) {
            $aparatIadeAdaylari = [];
            $normalIadeAdaylari = [];
            foreach ($iadeAdaylari as $d) {
                if ($this->isAparatKategorisi($d)) {
                    $aparatIadeAdaylari[] = $d;
                } else {
                    $normalIadeAdaylari[] = $d;
                }
            }

            // ===== APARAT İADE: Sahadan sökülen aparatın zimmete geri eklenmesi =====
            if (!empty($aparatIadeAdaylari)) {
                $kalanIadeIhtiyaci = $miktar;

                $sqlMevcut = $this->db->prepare("
                    SELECT z.id, z.iade_miktar, z.teslim_miktar, d.id as demirbas_id
                    FROM demirbas_zimmet z
                    INNER JOIN demirbas d ON z.demirbas_id = d.id
                    WHERE d.kategori_id = ? AND z.personel_id = ? AND z.durum = 'teslim'
                    ORDER BY z.teslim_tarihi ASC
                ");

                foreach ($aparatIadeAdaylari as $dAuto) {
                    if ($kalanIadeIhtiyaci <= 0)
                        break;

                    $sqlMevcut->execute([$dAuto->kategori_id, $personel_id]);
                    $mevcutZimmetler = $sqlMevcut->fetchAll(PDO::FETCH_OBJ);

                    if (empty($mevcutZimmetler)) {
                        // Personelde zimmet yoksa otomatik zimmet açmıyoruz, sadece raporlama için bilgi dönüyoruz.
                        $pName = "Personel ID: $personel_id";
                        try {
                            $stmtP = $this->db->prepare("SELECT adi_soyadi FROM personel WHERE id = ?");
                            $stmtP->execute([$personel_id]);
                            $pName = $stmtP->fetchColumn() ?: $pName;
                        } catch (\Exception $e) {
                        }

                        $results['iade'][] = [
                            'status' => 'error',
                            'type' => 'no_zimmet_found',
                            'personel_id' => $personel_id,
                            'personel_adi' => $pName,
                            'demirbas_adi' => $dAuto->demirbas_adi
                        ];
                        $kalanIadeIhtiyaci = 0;
                    } else {
                        foreach ($mevcutZimmetler as $z) {
                            if ($kalanIadeIhtiyaci <= 0)
                                break;

                            if ($islem_id) {
                                $sqlCheck = $this->db->prepare("SELECT id FROM demirbas_hareketler WHERE zimmet_id = ? AND islem_id = ? AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL LIMIT 1");
                                $sqlCheck->execute([$z->id, $islem_id]);
                                if ($sqlCheck->fetch()) {
                                    $kalanIadeIhtiyaci = 0;
                                    break;
                                }
                            }

                            $processed = $this->getProcessedAmount($z->id);
                            $buKayitIcinKullanilacak = min($processed, $kalanIadeIhtiyaci);

                            if ($buKayitIcinKullanilacak > 0) {

                                $this->Hareket->hareketEkle([
                                    'demirbas_id' => $z->demirbas_id,
                                    'personel_id' => $personel_id,
                                    'zimmet_id' => $z->id,
                                    'hareket_tipi' => 'iade',
                                    'miktar' => $buKayitIcinKullanilacak,
                                    'tarih' => $tarih,
                                    'islem_id' => $islem_id,
                                    'is_emri_sonucu' => $searchSonuc,
                                    'aciklama' => "Sahadan Geri Alındı (Tüketimden İptal) - $searchSonuc",
                                    'kaynak' => 'puantaj_excel'
                                ]);

                            }

                            if ($kalanIadeIhtiyaci > 0) {

                                $this->Hareket->hareketEkle([
                                    'demirbas_id' => $z->demirbas_id,
                                    'personel_id' => $personel_id,
                                    'zimmet_id' => $z->id,
                                    'hareket_tipi' => 'iade',
                                    'miktar' => $kalanIadeIhtiyaci,
                                    'tarih' => $tarih,
                                    'islem_id' => $islem_id,
                                    'is_emri_sonucu' => $searchSonuc,
                                    'aciklama' => "Sahadan Geri Alındı (Ekstra İade) - $searchSonuc",
                                    'kaynak' => 'puantaj_excel'
                                ]);

                                $kalanIadeIhtiyaci = 0;
                            }
                        }
                    }
                }
                $results['iade'][] = ['status' => 'success', 'type' => 'aparat_sahadan_iade'];
            }

            // ===== NORMAL İADE (Sayaç vb.): Personelden alınıp depoya/hurdaya konulması =====
            if (!empty($normalIadeAdaylari)) {
                // ID bazlı sorgu
                $normalIadeDbIds = array_map(function ($d) {
                    return $d->id;
                }, $normalIadeAdaylari);
                $iadePlaceholders = implode(',', array_fill(0, count($normalIadeDbIds), '?'));

                $sqlIade = $this->db->prepare("
                    SELECT z.id, d.demirbas_adi, d.kategori_id, d.id as demirbas_id, z.teslim_miktar, z.iade_miktar, k.tur_adi
                    FROM {$this->table} z
                    INNER JOIN demirbas d ON z.demirbas_id = d.id
                    LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                    WHERE d.id IN ($iadePlaceholders)
                    AND z.personel_id = ? 
                    AND z.durum = 'teslim'
                    ORDER BY z.teslim_tarihi ASC
                ");
                $iadeParams = array_merge($normalIadeDbIds, [$personel_id]);
                $sqlIade->execute($iadeParams);
                $mevcutZimmetler = $sqlIade->fetchAll(PDO::FETCH_OBJ);

                $kalanIadeIhtiyaci = $miktar;
                foreach ($mevcutZimmetler as $z) {
                    if ($kalanIadeIhtiyaci <= 0)
                        break;

                    if ($islem_id) {
                        $sqlCheckAuto = $this->db->prepare("SELECT id FROM {$this->table} WHERE id = ? AND aciklama LIKE ? LIMIT 1");
                        $sqlCheckAuto->execute([$z->id, "%İşlem ID: $islem_id%"]);
                        if ($sqlCheckAuto->fetch())
                            continue;
                    }

                    $processed = $this->getProcessedAmount($z->id);
                    $kalanZimmet = (int) $z->teslim_miktar - $processed;
                    if ($kalanZimmet <= 0)
                        continue;

                    $suAnkiIade = min($kalanZimmet, $kalanIadeIhtiyaci);
                    try {
                        $katAdiLower = mb_strtolower($z->tur_adi ?? '', 'UTF-8');
                        $isSayac = str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac');

                        if ($isSayac) {
                            $this->tuketimYap($z->id, $tarih, $suAnkiIade, "Otomatik Abone Montajı/Tüketim (İşlem ID: $islem_id - Sonuç: $searchSonuc)", $islem_id, $searchSonuc, 'puantaj_excel');

                            $yeniHurdaAdi = "Sökülen Hurda / " . $z->demirbas_adi;
                            $sqlHurdaInsert = $this->db->prepare("
                                INSERT INTO demirbas 
                                (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, aciklama, kayit_yapan)
                                VALUES (?, ?, ?, ?, ?, 'hurda', ?, ?)
                            ");
                            $sqlHurdaInsert->execute([
                                $_SESSION['firma_id'],
                                $z->kategori_id,
                                $yeniHurdaAdi,
                                $suAnkiIade,
                                $suAnkiIade,
                                "Otomatik Sökülen Hurda Sayaç (İşlem ID: $islem_id)",
                                $_SESSION['id'] ?? null
                            ]);
                            $yeniHurdaId = $this->db->lastInsertId();

                            $this->zimmetVer([
                                'demirbas_id' => $yeniHurdaId,
                                'personel_id' => $personel_id,
                                'teslim_tarihi' => $tarih,
                                'teslim_miktar' => $suAnkiIade,
                                'aciklama' => "Otomatik Hurda Sayaç Zimmeti (İşlem ID: $islem_id - Sonuç: $searchSonuc)",
                                'islem_id' => $islem_id,
                                'is_emri_sonucu' => $searchSonuc,
                                'kaynak' => 'puantaj_excel'
                            ]);
                        } else {
                            $this->iadeYap($z->id, $tarih, $suAnkiIade, "Otomatik İade (İşlem ID: $islem_id - Sonuç: $searchSonuc)", $islem_id, $searchSonuc, 'puantaj_excel');
                        }

                        $kalanIadeIhtiyaci -= $suAnkiIade;
                        $results['iade'][] = ['status' => 'success'];
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        // 3. ZİMMETTEN DÜŞME İŞLEMLERİ (Aparat kırıldı, çalındı vb.)
        // Personeldeki tüm aktif zimmetleri sonuçlandırılmış miktarı kadar düşürür
        if (($mode === 'both' || $mode === 'dus') && !empty($dusAdaylari)) {
            foreach ($dusAdaylari as $d) {
                try {
                    // Ek Güvenlik: Demirbaşın gerçekten bu tetikleyiciye sahip olduğunu doğrula
                    $dusIds = array_map('intval', array_filter(explode(',', $d->otomatik_zimmetten_dus_is_emri_ids ?? '')));
                    if (!in_array($incomingId, $dusIds)) {
                        continue; // Tetikleyici bu demirbaş için aslında tanımlı değil (başka bir demirbaştan gelmiş olabilir)
                    }

                    // Bu demirbaşın personeldeki aktif zimmetlerini çek (sadece bu demirbaşa ait olanlar)
                    $sqlAktifZimmet = $this->db->prepare("
                        SELECT z.id, z.demirbas_id, z.teslim_miktar, z.iade_miktar, d.demirbas_adi
                        FROM {$this->table} z
                        INNER JOIN demirbas d ON z.demirbas_id = d.id
                        WHERE z.demirbas_id = ? AND z.personel_id = ? AND z.durum = 'teslim'
                        ORDER BY z.teslim_tarihi ASC, z.id ASC
                    ");
                    $sqlAktifZimmet->execute([$d->id, $personel_id]);
                    $aktifZimmetler = $sqlAktifZimmet->fetchAll(PDO::FETCH_OBJ);

                    $kalanDusMiktari = $miktar;

                    foreach ($aktifZimmetler as $z) {
                        if ($kalanDusMiktari <= 0)
                            break;

                        // Mükerrer kontrolü
                        if ($islem_id) {
                            $sqlCheckDus = $this->db->prepare("SELECT id FROM demirbas_hareketler WHERE zimmet_id = ? AND islem_id = ? AND hareket_tipi = 'sarf' AND aciklama LIKE '%Zimmetten Düşüldü%' AND silinme_tarihi IS NULL LIMIT 1");
                            $sqlCheckDus->execute([$z->id, $islem_id]);
                            if ($sqlCheckDus->fetch())
                                continue;
                        }

                        $processed = $this->getProcessedAmount($z->id);
                        $kalanZimmet = (int) $z->teslim_miktar - $processed;
                        if ($kalanZimmet <= 0)
                            continue;

                        $suAnkiDus = min($kalanZimmet, $kalanDusMiktari);

                        $this->tuketimYap(
                            $z->id,
                            $tarih,
                            $suAnkiDus,
                            "Zimmetten Düşüldü (Kırılma/Çalınma) - (İşlem ID: $islem_id - Sonuç: $searchSonuc)",
                            $islem_id,
                            $searchSonuc,
                            'puantaj_excel'
                        );

                        $kalanDusMiktari -= $suAnkiDus;
                        $results['dus'][] = ['status' => 'success', 'type' => 'zimmetten_dus', 'demirbas' => $d->demirbas_adi, 'miktar' => $suAnkiDus];
                    }
                } catch (\Exception $e) {
                    $results['dus'][] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    /**
     * Bir demirbaşın aparat kategorisinde olup olmadığını kontrol eder
     */
    private function isAparatKategorisi($demirbas)
    {
        $katAdi = mb_strtolower($demirbas->kategori_adi ?? '', 'UTF-8');
        return str_contains($katAdi, 'aparat') || $demirbas->kategori_id == 645;
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
            $sql = $this->db->prepare("SELECT * FROM demirbas_hareketler WHERE id = ? AND hareket_tipi IN ('iade', 'sarf') AND silinme_tarihi IS NULL");
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
            if ($h->hareket_tipi === 'sarf') {
                $sqlDemirbas = $this->db->prepare("UPDATE demirbas SET miktar = miktar + ? WHERE id = ?");
            } else {
                $sqlDemirbas = $this->db->prepare("UPDATE demirbas SET kalan_miktar = kalan_miktar - ? WHERE id = ?");
            }
            $sqlDemirbas->execute([$h->miktar, $h->demirbas_id]);

            $processed = $this->getProcessedAmount($h->zimmet_id);
            $yeniDurum = ($processed < (int) $z->teslim_miktar) ? 'teslim' : 'iade';

            $sqlUpZimmet = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = ?
                WHERE id = ?
            ");
            $sqlUpZimmet->execute([$yeniDurum, $h->zimmet_id]);

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
