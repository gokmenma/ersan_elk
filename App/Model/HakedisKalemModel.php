<?php

namespace App\Model;

use App\Model\Model;

class HakedisKalemModel extends Model
{
    protected $table = 'hakedis_kalemleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
