<?php
namespace App\Model;

use App\Model\Model;

class IzinOnaylariModel extends Model
{
    protected $table = 'izin_onaylari';

    public function __construct()
    {
        parent::__construct($this->table);
    }
}
