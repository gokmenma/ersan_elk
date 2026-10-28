<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AracPoliceModel extends Model
{
    protected $table = 'arac_police_dosyalari';

    public const TURLER = ['sigorta', 'kasko'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getByArac(int $aracId): array
    {
        $sql = $this->db->prepare("
            SELECT id, arac_id, police_turu, sirket_adi, police_no, baslangic_tarihi, bitis_tarihi,
                   prim_tutari, orijinal_ad, mime_tipi, boyutu, dosya_adi
            FROM {$this->table}
            WHERE arac_id = :arac_id AND firma_id = :firma_id AND silinme_tarihi IS NULL
            ORDER BY police_turu ASC, bitis_tarihi DESC, id DESC
        ");
        $sql->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getById(int $id)
    {
        $sql = $this->db->prepare("
            SELECT id, arac_id, police_turu, sirket_adi, police_no, bitis_tarihi, dosya_adi, orijinal_ad, mime_tipi, boyutu
            FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function addPolice(int $aracId, array $veri): int
    {
        $sql = $this->db->prepare("
            INSERT INTO {$this->table}
                (firma_id, arac_id, police_turu, sirket_adi, police_no, baslangic_tarihi, bitis_tarihi,
                 prim_tutari, dosya_adi, orijinal_ad, mime_tipi, boyutu, yukleyen_id)
            VALUES
                (:firma_id, :arac_id, :police_turu, :sirket_adi, :police_no, :baslangic_tarihi, :bitis_tarihi,
                 :prim_tutari, :dosya_adi, :orijinal_ad, :mime_tipi, :boyutu, :yukleyen_id)
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'arac_id' => $aracId,
            'police_turu' => $veri['police_turu'],
            'sirket_adi' => $veri['sirket_adi'],
            'police_no' => $veri['police_no'],
            'baslangic_tarihi' => $veri['baslangic_tarihi'],
            'bitis_tarihi' => $veri['bitis_tarihi'],
            'prim_tutari' => $veri['prim_tutari'],
            'dosya_adi' => $veri['dosya_adi'],
            'orijinal_ad' => $veri['orijinal_ad'],
            'mime_tipi' => $veri['mime_tipi'],
            'boyutu' => $veri['boyutu'],
            'yukleyen_id' => $veri['yukleyen_id']
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function policeSil(int $id): bool
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

    public function enGecBitis(int $aracId, string $tur): ?string
    {
        $sql = $this->db->prepare("
            SELECT MAX(bitis_tarihi) FROM {$this->table}
            WHERE arac_id = :arac_id AND firma_id = :firma_id AND police_turu = :tur
              AND silinme_tarihi IS NULL AND bitis_tarihi IS NOT NULL
        ");
        $sql->execute([
            'arac_id' => $aracId,
            'firma_id' => $_SESSION['firma_id'],
            'tur' => $tur
        ]);
        return $sql->fetchColumn() ?: null;
    }
}
