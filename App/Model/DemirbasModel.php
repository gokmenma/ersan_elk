<?php
namespace App\Model;

use App\Helper\Helper;
use App\Model\Model;
use App\Helper\Security;
use PDO;

class DemirbasModel extends Model
{
    protected $table = 'demirbas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm demirbaşları kategori bilgisiyle beraber getirir
     */
    public function getAllWithCategory()
    {
        $sql = $this->db->prepare("
            SELECT 
                d.*,
                k.tur_adi as kategori_adi,
                COALESCE(d.miktar, 1) as miktar,
                COALESCE(d.kalan_miktar, 1) as kalan_miktar,
                (SELECT id FROM demirbas_servis_kayitlari WHERE demirbas_id = d.id AND iade_tarihi IS NULL AND silinme_tarihi IS NULL LIMIT 1) as active_servis_id
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            ORDER BY d.kayit_tarihi DESC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Stokta kalan demirbaşları getirir
     */
    public function getInStock()
    {
        $sql = $this->db->prepare("
            SELECT 
                d.*,
                k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kalan_miktar > 0
            ORDER BY k.tur_adi, d.demirbas_adi
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kategoriye göre demirbaşları getirir
     */
    public function getByCategory($kategori_id)
    {
        $sql = $this->db->prepare("
            SELECT d.*, k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kategori_id = ?
            ORDER BY d.demirbas_adi
        ");
        $sql->execute([$kategori_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Demirbaş stok özeti
     */
    public function getStockSummary()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_cesit,
                COALESCE(SUM(miktar), 0) as toplam_adet,
                COALESCE(SUM(kalan_miktar), 0) as stokta_kalan,
                (COALESCE(SUM(miktar), 0) - COALESCE(SUM(kalan_miktar), 0)) as zimmetli_adet
            FROM {$this->table}
        ");
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Select2 için demirbaş listesi
     */
    public function getForSelect($search = '')
    {
        $searchTerm = '%' . $search . '%';
        $sql = $this->db->prepare("
            SELECT 
                d.id,
                CONCAT(d.demirbas_no, ' - ', d.demirbas_adi, ' (', COALESCE(k.tur_adi, 'Kategorisiz'), ')') as text,
                d.kalan_miktar
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.kalan_miktar > 0
                AND (d.demirbas_no LIKE ? OR d.demirbas_adi LIKE ? OR d.marka LIKE ?)
            ORDER BY d.demirbas_adi
            LIMIT 20
        ");
        $sql->execute([$searchTerm, $searchTerm, $searchTerm]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
     */
    public function getTableRow($id)
    {
        $sql = $this->db->prepare("
            SELECT d.*, k.tur_adi as kategori_adi
            FROM {$this->table} d
            LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
            WHERE d.id = ?
        ");
        $sql->execute([$id]);
        $data = $sql->fetch(PDO::FETCH_OBJ);

        if (!$data) {
            return '';
        }

        $enc_id = Security::encrypt($data->id);
        $miktar = $data->miktar ?? 1;
        $kalan = $data->kalan_miktar ?? 1;
        $minStok = $data->minimun_stok_uyari_miktari ?? 0;

        // Stok durumu badge
        if ($kalan == 0) {
            $stokBadge = '<span class="badge bg-danger">Stok Yok</span>';
        } elseif ($minStok > 0 && $kalan <= $minStok) {
            $stokBadge = '<span class="badge bg-soft-danger text-danger border border-danger">Stok Azaldı (' . $kalan . '/' . $miktar . ')</span>';
        } elseif ($kalan < $miktar) {
            $stokBadge = '<span class="badge bg-warning">' . $kalan . '/' . $miktar . '</span>';
        } else {
            $stokBadge = '<span class="badge bg-success">' . $kalan . '/' . $miktar . '</span>';
        }

        // Durum badge
        $durumText = $data->durum ?? 'aktif';
        $durumMap = [
            'aktif' => '<span class="badge bg-soft-success text-success">Aktif</span>',
            'pasif' => '<span class="badge bg-soft-secondary text-secondary">Pasif</span>',
            'arizali' => '<span class="badge bg-soft-warning text-warning">Arızalı</span>',
            'hurda' => '<span class="badge bg-soft-danger text-danger">Hurda</span>',
        ];
        $durumBadge = $durumMap[strtolower($durumText)] ?? '<span class="badge bg-soft-secondary text-secondary">' . $durumText . '</span>';

        return '<tr data-id="' . $enc_id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->demirbas_no . '</td>
            <td><span class="badge bg-soft-primary text-primary">' . ($data->kategori_adi ?? 'Kategorisiz') . '</span></td>
            <td>
                <a href="#" data-id="' . $enc_id . '" class="text-dark duzenle fw-medium">
                    ' . $data->demirbas_adi . '</a>
            </td>
            <td>
                <div>' . ($data->marka ?? '-') . ' ' . ($data->model ?? '') . '</div>
                <small class="text-muted">' . ($data->seri_no ? 'SN: ' . $data->seri_no : '') . '</small>
            </td>
            <td class="text-center">' . $stokBadge . '</td>
            <td class="text-center">' . $durumBadge . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->edinme_tutari ?? 0) . '</td>
            <td>' . ($data->edinme_tarihi ?? '-') . '</td>
            <td class="text-center text-nowrap">
                ' . ($kalan > 0 ? '<button type="button" class="btn btn-sm btn-soft-warning waves-effect waves-light zimmet-ver" data-id="' . $enc_id . '" data-name="' . $data->demirbas_adi . '" data-kalan="' . $kalan . '" title="Zimmet Ver"><i class="bx bx-transfer"></i></button>' : '') . '
                <button type="button" class="btn btn-sm btn-soft-primary waves-effect waves-light duzenle" data-id="' . $enc_id . '" title="Düzenle"><i class="bx bx-edit"></i></button>
                <button type="button" class="btn btn-sm btn-soft-danger waves-effect waves-light demirbas-sil" data-id="' . $enc_id . '" data-name="' . $data->demirbas_adi . '" title="Sil"><i class="bx bx-trash"></i></button>
            </td>
        </tr>';
    }

    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT d.*, k.tur_adi as kategori_adi
                FROM {$this->table} d
                LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                WHERE 1=1";

        $params = [];

        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (d.demirbas_no LIKE :term OR d.demirbas_adi LIKE :term OR d.marka LIKE :term OR d.model LIKE :term OR k.kategori_adi LIKE :term)";
            $params['term'] = $term;
        }

        if (!empty($colSearches)) {
            $colMap = [1 => 'd.demirbas_no', 2 => 'k.kategori_adi', 3 => 'd.demirbas_adi', 4 => 'd.marka', 6 => 'd.durum', 7 => 'd.edinme_tutari', 8 => 'd.edinme_tarihi'];
            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;
                    if ($idx == 8) {
                        $sql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                    } else {
                        $sql .= " AND $field LIKE :$paramName";
                    }
                    $params[$paramName] = "%$val%";
                }
            }
        }

        $sql .= " ORDER BY d.kayit_tarihi DESC";
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
}