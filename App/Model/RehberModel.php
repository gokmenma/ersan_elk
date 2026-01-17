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

}