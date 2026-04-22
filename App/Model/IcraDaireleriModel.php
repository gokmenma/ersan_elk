<?php
namespace App\Model;

use App\Model\Model;

class IcraDaireleriModel extends Model
{
    protected $table = 'icra_daireleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
