<?php
namespace App\Model;

use App\Helper\Helper;
use App\Model\Model;
use App\Helper\Security;
use PDO;

class RehberModel extends Model
{
    protected $table = 'rehber';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getTableRow($id)
    {
        $data = $this->find(Security::decrypt($id));

        return '<tr data-id="' . $id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->adi_soyadi . '</td>
            <td data-tooltip="true" data-tooltip-title="top">
                <a href="#" data-id="' . $id . '" class="dropdown-item duzenle">
                    ' . $data->kurum_adi . '</a>
            </td>
            <td>' . $data->telefon . '</td>
            <td>' . $data->email . '</td>
            <td>' . $data->adres . '</td>
            <td>' . $data->kayit_tarihi . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="#" data-id="'. $id.'" class="dropdown-item kayit-duzenle">
                                <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                            </a>
                            <a href="#" class="dropdown-item kayit-sil" data-id="' . $id . '" data-name="' . $data->adi_soyadi . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>    
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    /**
     * DataTables Server-Side AJAX Listesi
     *
     * @param array $params DataTables parametreleri
     * @return array
     */
    public function ajaxList(array $params): array
    {
        $base = "FROM {$this->table} WHERE 1=1";
        $bind = [];

        // Toplam kayıt sayısı
        $stmtTotal = $this->db->prepare("SELECT COUNT(*) {$base}");
        $stmtTotal->execute();
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        $where = '';

        // Genel arama
        $search = trim((string) ($params['search']['value'] ?? ''));
        if ($search !== '') {
            $where .= " AND (adi_soyadi LIKE :search OR kurum_adi LIKE :search OR telefon LIKE :search OR email LIKE :search OR adres LIKE :search OR aciklama LIKE :search OR DATE_FORMAT(kayit_tarihi, '%d.%m.%Y') LIKE :search)";
            $bind[':search'] = '%' . $search . '%';
        }

        // Kolon bazlı canlı filtreleme
        $colMap = [
            0 => 'id',
            1 => 'adi_soyadi',
            2 => 'kurum_adi',
            3 => 'telefon',
            4 => 'email',
            5 => 'adres',
            6 => 'aciklama',
            7 => "DATE_FORMAT(kayit_tarihi, '%d.%m.%Y')"
        ];

        foreach (($params['columns'] ?? []) as $i => $column) {
            $val = trim((string) ($column['search']['value'] ?? ''));
            if ($val === '' || !isset($colMap[(int) $i])) {
                continue;
            }
            if (strpos($val, ':') !== false) {
                [, $val] = explode(':', $val, 2);
            }
            $p = ':col_' . (int) $i;
            $where .= " AND {$colMap[(int) $i]} LIKE {$p}";
            $bind[$p] = '%' . $val . '%';
        }

        // Filtrelenmiş toplam
        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) {$base} {$where}");
        $stmtFiltered->execute($bind);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // Sıralama
        $orderColIndex = (int) ($params['order'][0]['column'] ?? 0);
        $orderMap = [
            0 => 'id',
            1 => 'adi_soyadi',
            2 => 'kurum_adi',
            3 => 'telefon',
            4 => 'email',
            5 => 'adres',
            6 => 'aciklama',
            7 => 'kayit_tarihi'
        ];
        $orderCol = $orderMap[$orderColIndex] ?? 'id';
        $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        // Sayfalama
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $limit = $length > 0 ? ' LIMIT ' . $start . ', ' . min($length, 500) : '';

        // Veri çekme
        $sql = "SELECT id, adi_soyadi, kurum_adi, telefon, email, adres, aciklama, kayit_tarihi {$base} {$where} ORDER BY {$orderCol} {$orderDir} {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            'draw' => (int) ($params['draw'] ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows
        ];
    }
}
