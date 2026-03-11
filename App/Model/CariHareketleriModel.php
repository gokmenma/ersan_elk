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

    public function ajaxList($params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $cari_id = $params['cari_id'];

        // Toplam Kayıt Sayısı
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmt->execute(['cari_id' => $cari_id]);
        $totalCount = $stmt->fetchColumn();

        // Tüm hareketleri çekip yürüyen bakiye hesaplamalıyız çünkü yürüyen bakiye sıralamaya bağlıdır
        // DataTables limit kullandığında yürüyen bakiye yanlış hesaplanabilir.
        // Bu yüzden genellikle tüm listeyi çekip bakiye ekleyip sonra dilimliyoruz (veya SQL ile çözüyoruz)
        
        $sql = "SELECT h.*, 
                (SELECT SUM(alacak - borc) FROM $this->table WHERE cari_id = :cari_id AND silinme_tarihi IS NULL AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
                FROM $this->table h
                WHERE h.cari_id = :cari_id AND h.silinme_tarihi IS NULL
                ORDER BY h.islem_tarihi DESC, h.id DESC
                LIMIT :start, :length";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('cari_id', $cari_id, PDO::PARAM_INT);
        $stmt->bindValue('start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue('length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalCount),
            "recordsFiltered" => intval($totalCount),
            "data" => $data
        ];
    }
}
