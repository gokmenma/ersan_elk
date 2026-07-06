<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DemirbasZimmetFotoModel extends Model
{
    protected $table = 'demirbas_zimmet_fotograflari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function addFoto($zimmetId, $foto_turu, $dosyaAdi, $orijinalAd, $mimeTipi, $boyut, $yukleyenId)
    {
        $sql = $this->db->prepare("
            INSERT INTO {$this->table}
                (firma_id, zimmet_id, foto_turu, dosya_adi, orijinal_ad, mime_tipi, boyutu, yukleyen_id)
            VALUES
                (:firma_id, :zimmet_id, :foto_turu, :dosya_adi, :orijinal_ad, :mime_tipi, :boyutu, :yukleyen_id)
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'zimmet_id' => $zimmetId,
            'foto_turu' => $foto_turu,
            'dosya_adi' => $dosyaAdi,
            'orijinal_ad' => $orijinalAd,
            'mime_tipi' => $mimeTipi,
            'boyutu' => $boyut,
            'yukleyen_id' => $yukleyenId
        ]);
        return $this->db->lastInsertId();
    }

    public function getByZimmet($zimmetId)
    {
        $sql = $this->db->prepare("
            SELECT id, zimmet_id, foto_turu, orijinal_ad, mime_tipi, boyutu, olusturma_tarihi
            FROM {$this->table}
            WHERE zimmet_id = :zimmet_id
            AND firma_id = :firma_id
            AND silinme_tarihi IS NULL
            ORDER BY foto_turu ASC, id ASC
        ");
        $sql->execute([
            'zimmet_id' => $zimmetId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getById($id)
    {
        $sql = $this->db->prepare("
            SELECT id, zimmet_id, foto_turu, dosya_adi, orijinal_ad, mime_tipi, boyutu
            FROM {$this->table}
            WHERE id = :id
            AND firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function softDeleteFoto($id)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table}
            SET silinme_tarihi = NOW()
            WHERE id = :id AND firma_id = :firma_id
        ");
        return $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }
}
