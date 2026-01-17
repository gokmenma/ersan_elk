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
                k.kategori_adi,
                COALESCE(d.miktar, 1) as miktar,
                COALESCE(d.kalan_miktar, 1) as kalan_miktar
            FROM {$this->table} d
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
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
                k.kategori_adi
            FROM {$this->table} d
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
            WHERE d.kalan_miktar > 0
            ORDER BY k.kategori_adi, d.demirbas_adi
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
            SELECT d.*, k.kategori_adi
            FROM {$this->table} d
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
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
                CONCAT(d.demirbas_no, ' - ', d.demirbas_adi, ' (', COALESCE(k.kategori_adi, 'Kategorisiz'), ')') as text,
                d.kalan_miktar
            FROM {$this->table} d
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
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
            SELECT d.*, k.kategori_adi
            FROM {$this->table} d
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
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
        $zimmetli = $miktar - $kalan;

        // Stok durumu badge
        if ($kalan == 0) {
            $stokBadge = '<span class="badge bg-danger">Stok Yok</span>';
        } elseif ($kalan < $miktar) {
            $stokBadge = '<span class="badge bg-warning">' . $kalan . '/' . $miktar . '</span>';
        } else {
            $stokBadge = '<span class="badge bg-success">' . $kalan . '/' . $miktar . '</span>';
        }

        return '<tr data-id="' . $enc_id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->demirbas_no . '</td>
            <td>' . ($data->kategori_adi ?? '-') . '</td>
            <td data-tooltip="true" data-tooltip-title="top">
                <a href="#" data-id="' . $enc_id . '" class="dropdown-item duzenle">
                    ' . $data->demirbas_adi . '</a>
            </td>
            <td>' . ($data->marka ?? '-') . ' ' . ($data->model ?? '') . '</td>
            <td class="text-center">' . $stokBadge . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->edinme_tutari ?? 0) . '</td>
            <td>' . ($data->edinme_tarihi ?? '-') . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            ' . ($kalan > 0 ? '<a href="#" data-id="' . $enc_id . '" data-name="' . $data->demirbas_adi . '" data-kalan="' . $kalan . '" class="dropdown-item zimmet-ver">
                                <span class="mdi mdi-hand-extended font-size-18"></span> Zimmet Ver
                            </a>' : '') . '
                            <a href="#" data-id="' . $enc_id . '" class="dropdown-item duzenle">
                                <span class="mdi mdi-pencil font-size-18"></span> Düzenle
                            </a>
                            <a href="#" class="dropdown-item demirbas-sil" data-id="' . $enc_id . '" data-name="' . $data->demirbas_adi . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>    
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

}