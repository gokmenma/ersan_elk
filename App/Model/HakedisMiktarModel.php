<?php

namespace App\Model;

use App\Model\Model;

class HakedisMiktarModel extends Model
{
    protected $table = 'hakedis_miktarlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
