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





    public function all($kasa_id = null)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->sql_table 
                                    WHERE kasa_id = :kasa_id 
                                    ORDER BY islem_tarihi  DESC, id DESC");
        $sql->execute(['kasa_id' => $kasa_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function find($id)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->sql_table WHERE id = :id");
        $sql->execute(['id' => $id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getGelirGiderTableRow($id)
    {
        $this->table = $this->sql_table;
        $Tanimlama = new TanimlamalarModel();
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);
        $kayit_sayisi = count($this->all());

        //Eğer bakiye 0'dan küçükse danger, büyükse success
        $color = $data->bakiye < 0 ? 'danger' : 'success';



          return '<tr id="gelir_gider_' . $data->id . '" data-id="' . $enc_id . '">
            <td class="text-center">' . $kayit_sayisi . '</td>
            <td class="text-center">' . $data->kayit_tarihi . '</td>
            <td class="text-center">' . Helper::getBadge($data->type) . '</td>
            <td class="text-center">' . $Tanimlama->getTurAdi($data->islem_turu) . '</td>
            <td>' . ($data->islem_tarihi ?: '-') . '</td>
            <td>' . $data->hesap_adi . '</td>
            <td class="text-end">' . Helper::formattedMoney($data->tutar) . '</td>
            <td class="text-end text-' . $color . '">' . Helper::formattedMoney($data->bakiye) . '</td>
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
    public function summary()
    {
        $kasa_id = $_SESSION['kasa_id'];
        $sql = $this->db->prepare("
            SELECT 
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END), 2) AS toplam_gelir,
                ROUND(SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS toplam_gider,
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END) - SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS bakiye
            FROM $this->sql_table 
            WHERE kasa_id = :kasa_id
        ");
        $sql->bindParam(':kasa_id', $kasa_id, PDO::PARAM_INT);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    //Kasa Özetini  getir
    public function getGelirGiderStatics()
    {


        $kasa_id = $_SESSION['kasa_id'] ?? null;

        /**Kasa id boş ise geri dön */
        if (empty($kasa_id)) {
            return null;
        }

        $sql = $this->db->prepare("
            SELECT 
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END), 2) AS toplam_gelir,
                ROUND(SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS toplam_gider,
                ROUND(SUM(CASE WHEN type = 1 THEN tutar ELSE 0 END) - SUM(CASE WHEN type = 2 THEN tutar ELSE 0 END),2) AS bakiye
            FROM $this->sql_table
            WHERE kasa_id = :kasa_id
           
        ");
        $sql->bindParam(':kasa_id', $kasa_id, PDO::PARAM_INT);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

}