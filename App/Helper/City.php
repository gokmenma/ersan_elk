<?php

namespace App\Helper;

use App\Core\Db;
use PDO;
use PDOException;

class City extends Db
{

    public function citySelect($name = 'city', $id = null)
    {
        try {
            $query = $this->db->prepare('SELECT * FROM il');  // Fetch all columns
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);  // Fetch all results

            $select = '<select name="' . $name . '" class="form-select select2" id="' . $name . '" style="width:100%">';
            $select .= '<option value="">Şehir Seçiniz</option>';
            foreach ($results as $row) {  // Loop through results
                $selected = $id == $row->id ? ' selected' : '';  // Mark as selected if id matches
                $select .= '<option value="' . $row->id . '"' . $selected . '>' . $row->city_name . '</option>';
            }
            $select .= '</select>';
            return $select;
        } catch (PDOException $e) {
            return 'Veritabanı hatası: ' . $e->getMessage();
        }
    }

    //City adını getir
    public function getCityName($id)
    {
        //id boş ise boş döndür
        if (empty($id)) {
            return '';
        }


        $query = $this->db->prepare('SELECT city_name FROM il WHERE id = ?');
        $query->execute([$id]);
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result->city_name;

    }

    //İlçe adını getir
    public function getTownName($id)
    {
        //id boş ise boş döndür
        if (empty($id)) {
            return '';
        }

        $query = $this->db->prepare('SELECT ilce_adi FROM ilce WHERE id = ?');
        $query->execute([$id]);
        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result->ilce_adi;
    }

    /**Key, value şeklinde il listesi döndürür */
    public function getCityList()
    {
        $query = $this->db->prepare('SELECT id, city_name FROM il');
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**İlçeleri getir */
    public function getDistricts($il_id)
    {
        $query = $this->db->prepare('SELECT * FROM ilce WHERE il_id = ?');
        $query->execute([$il_id]);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
}