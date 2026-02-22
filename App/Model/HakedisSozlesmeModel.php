<?php

namespace App\Model;

use App\Model\Model;

class HakedisSozlesmeModel extends Model
{
    protected $table = 'hakedis_sozlesmeler';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
