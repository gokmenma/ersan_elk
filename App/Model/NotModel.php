<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Date;
use App\Helper\Security;

class NotModel extends Model
{
    protected $table = 'uye_notlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }


    // Son eklenen kaydın bilgilerini satır olarak getir
    public function getNotesTableRow($id)
    {
        $data = $this->find(Security::decrypt($id));


        return '<tr data-id="' . $id . '">
            <td>' . $data->id . '</td>
            <td>' . Date::dmy($data->tarih) . '</td>
            <td>' . $data->not_aciklama . '</td>
            <td>' . $data->kayit_tarihi . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="#" data-id="' . $id . '" class="dropdown-item note-duzenle">
                                <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                            </a>
                            <a href="#" class="dropdown-item note-sil" data-id="' . $id . '" data-name="' . $data->tarih . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';

    }
}