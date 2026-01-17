<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class DefineModel extends Model
{
    protected $table = 'define_number';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    //Üyeler tablosundaki uye_no alanının  en büyük değerini getirir
    public function getUyeNo()
    {
        $sql = "SELECT MAX(uye_no) as max_uye_no FROM uyeler";
        $result = $this->db->query($sql)->fetch(PDO::FETCH_OBJ);
        return $result->max_uye_no + 1;
    }
    


    //uye_no alanının değerini 1 artırır
    public function setUyeNo()
    {
        $sql = "UPDATE {$this->table} SET uye_no = uye_no + 1";
        return $this->db->exec($sql);
    }

    //evrak_no alanının değerini 1 artırılmış olarak getir
    //Örnek dğere : 5 ise 6 döner
    public function getEvrakNo()
    {
        $sql = "SELECT evrak_no FROM {$this->table} LIMIT 1";
        $result = $this->db->query($sql)->fetch(PDO::FETCH_OBJ);
        return $result->evrak_no + 1;
    }

    //evrak_no alanının değerini 1 artırır
    public function setEvrakNo()
    {
        $sql = "UPDATE {$this->table} SET evrak_no = evrak_no + 1";
        return $this->db->exec($sql);
    }
}