<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class NotModel extends Model
{
    protected $table = 'notlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    // =====================================================
    // NOT DEFTERİ İŞLEMLERİ
    // =====================================================

    public function getDefterler($firma_id, $user_id = null)
    {
        $userFilter = $user_id ? " AND (olusturan_id = :user_id)" : "";
        $sql = "SELECT nd.*, 
                (SELECT COUNT(*) FROM notlar n WHERE n.defter_id = nd.id AND n.silinme_tarihi IS NULL) as not_sayisi
                FROM not_defterleri nd 
                WHERE nd.firma_id = :firma_id AND nd.silinme_tarihi IS NULL $userFilter
                ORDER BY nd.sira ASC, nd.id ASC";
        $stmt = $this->db->prepare($sql);
        $params = [':firma_id' => $firma_id];
        if ($user_id) $params[':user_id'] = $user_id;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addDefter($data)
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sira), 0) + 1 as next_sira FROM not_defterleri WHERE firma_id = :firma_id");
        $stmt->execute([':firma_id' => $data['firma_id']]);
        $nextSira = $stmt->fetch(PDO::FETCH_OBJ)->next_sira;

        $sql = "INSERT INTO not_defterleri (firma_id, baslik, sira, renk, icon, olusturan_id) 
                VALUES (:firma_id, :baslik, :sira, :renk, :icon, :olusturan_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':firma_id' => $data['firma_id'],
            ':baslik' => $data['baslik'],
            ':sira' => $nextSira,
            ':renk' => $data['renk'] ?? '#4285f4',
            ':icon' => $data['icon'] ?? 'bx-book',
            ':olusturan_id' => $data['olusturan_id']
        ]);
        return $this->db->lastInsertId();
    }

    public function updateDefter($id, $data)
    {
        $sets = [];
        $params = [':id' => $id];

        if (isset($data['baslik'])) {
            $sets[] = 'baslik = :baslik';
            $params[':baslik'] = $data['baslik'];
        }
        if (isset($data['renk'])) {
            $sets[] = 'renk = :renk';
            $params[':renk'] = $data['renk'];
        }
        if (isset($data['icon'])) {
            $sets[] = 'icon = :icon';
            $params[':icon'] = $data['icon'];
        }

        if (empty($sets)) return false;

        $sql = "UPDATE not_defterleri SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteDefter($id)
    {
        // Soft delete
        $sql = "UPDATE not_defterleri SET silinme_tarihi = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // =====================================================
    // NOT İŞLEMLERİ
    // =====================================================

    public function getNotlar($defter_id, $firma_id, $user_id = null)
    {
        $userFilter = $user_id ? " AND (n.olusturan_id = :user_id)" : "";
        $sql = "SELECT n.*, nd.baslik as defter_adi, nd.renk as defter_renk 
                FROM notlar n
                JOIN not_defterleri nd ON n.defter_id = nd.id
                WHERE n.defter_id = :defter_id 
                AND n.firma_id = :firma_id 
                AND n.silinme_tarihi IS NULL $userFilter
                ORDER BY n.pinli DESC, n.sira ASC, n.updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $params = [':defter_id' => $defter_id, ':firma_id' => $firma_id];
        if ($user_id) $params[':user_id'] = $user_id;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getTumNotlar($firma_id, $user_id = null)
    {
        $userFilter = $user_id ? " AND (n.olusturan_id = :user_id)" : "";
        $sql = "SELECT n.*, nd.baslik as defter_adi, nd.renk as defter_renk 
                FROM notlar n 
                JOIN not_defterleri nd ON n.defter_id = nd.id 
                WHERE n.firma_id = :firma_id 
                AND n.silinme_tarihi IS NULL 
                AND nd.silinme_tarihi IS NULL $userFilter
                ORDER BY n.pinli DESC, n.updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $params = [':firma_id' => $firma_id];
        if ($user_id) $params[':user_id'] = $user_id;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addNot($data)
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sira), 0) + 1 as next_sira FROM notlar WHERE defter_id = :defter_id");
        $stmt->execute([':defter_id' => $data['defter_id']]);
        $nextSira = $stmt->fetch(PDO::FETCH_OBJ)->next_sira;

        $sql = "INSERT INTO notlar (defter_id, firma_id, baslik, icerik, renk, pinli, sira, olusturan_id) 
                VALUES (:defter_id, :firma_id, :baslik, :icerik, :renk, :pinli, :sira, :olusturan_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':defter_id' => $data['defter_id'],
            ':firma_id' => $data['firma_id'],
            ':baslik' => $data['baslik'] ?? null,
            ':icerik' => $data['icerik'] ?? null,
            ':renk' => $data['renk'] ?? null,
            ':pinli' => $data['pinli'] ?? 0,
            ':sira' => $nextSira,
            ':olusturan_id' => $data['olusturan_id']
        ]);
        return $this->db->lastInsertId();
    }

    public function updateNot($id, $data)
    {
        $sets = [];
        $params = [':id' => $id];

        $allowedFields = ['defter_id', 'baslik', 'icerik', 'renk', 'pinli', 'sira'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($sets)) return false;

        $sql = "UPDATE notlar SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteNot($id)
    {
        $sql = "UPDATE notlar SET silinme_tarihi = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function pinNot($id, $pinli)
    {
        $sql = "UPDATE notlar SET pinli = :pinli WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':pinli' => $pinli]);
    }

    public function findNot($id)
    {
        $sql = "SELECT * FROM notlar WHERE id = :id AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
}