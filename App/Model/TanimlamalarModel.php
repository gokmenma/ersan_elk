<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class TanimlamalarModel extends Model
{
    protected $table = 'tanimlamalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }



    public function getGelirGiderTurleriSelect($type)
    {
        $sql = $this->db->prepare("SELECT id,tur_adi FROM $this->table WHERE type = ?");
        $sql->execute([$type]);
        $result = $sql->fetchAll(PDO::FETCH_OBJ);

        $select = "<option value='0' disabled >Seçiniz</option>";

        foreach ($result as $item) {
            $select .= '<option value="' . $item->id . '">' . $item->tur_adi . '</option>';
        }

        return $select;
    }


    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getTableRow($id)
    {
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);


        return '<tr id="row_' . $data->id . '"> 
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center" style="width:8%">' . Helper::getBadge($data->type) . '</td>
            <td>' . $data->tur_adi . '</td>
            <td>' . $data->aciklama . '</td>
            <td>' . $data->kayit_tarihi . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                             <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit" style="font-size: 18px;"></span> Düzenle
                            </a>
                            <a class="dropdown-item sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete" style="font-size: 18px;"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    // Ekip Kodu için tablo satırı
    public function getEkipKoduTableRow($id)
    {
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);

        return '<tr id="row_' . $data->id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->tur_adi . '</td>
            <td class="text-center">' . $data->aciklama . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                             <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit" style="font-size: 18px;"></span> Düzenle
                            </a>
                            <a class="dropdown-item sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete" style="font-size: 18px;"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    public function getEkipKodlari()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? ORDER BY id DESC");
        $sql->execute(['ekip_kodu']);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    //Tur adını getir
    public function getTurAdi($id)
    {
        $data = $this->find($id);
        return $data->tur_adi;
    }
}