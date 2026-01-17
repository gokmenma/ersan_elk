<?php


namespace App\Model;

use App\Model\Model;
use PDO;

class SmsSablonModel extends Model
{
    protected $table = 'sms_sablonlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }


    /** Tüm SMS şablonlarını getirir. */
    public function getAllTemplates(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
                                    WHERE aktif_mi = ?
                                    and silinme_tarihi IS NULL");
        $stmt->execute([1]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
