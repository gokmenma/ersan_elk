<?php

namespace App\Model;

use App\Model\Model;

class HakedisDonemModel extends Model
{
    protected $table = 'hakedis_donemleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
