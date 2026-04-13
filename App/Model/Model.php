<?php

namespace App\Model;




//Root tanımlı değilse tanımla
// !defined("APP") ? define("APP" ,$_SERVER["DOCUMENT_ROOT"]) : false;
//!defined("APP") ? define("APP", $_SERVER["DOCUMENT_ROOT"] . '/cansen/') : false;

// require_once $_SERVER['DOCUMENT_ROOT'] . '/Database/db.php';


// require_once APP . '/admin/App/Core/db.php';
// require_once ROOT . '/backend/App/Helper/Security.php';

use App\Helper\Security;
use App\Core\Db;
use PDO;


class Model extends Db
{
    public $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $isNew = true;

    protected $query = [];

    public function __construct($table = null)
    {
        parent::__construct();
        if ($table) {
            $this->table = $table;
        }
    }

    public function getDb()
    {
        return $this->db;
    }
    protected function getTableName()
    {
        $className = get_called_class();
        $parts = explode('\\', $className);
        $className = end($parts);
        return strtolower($className) . 's';
    }
    //public function all()
    // {
    //     $sql = $this->db->prepare("SELECT * FROM $this->table");
    //     $sql->execute();
    //     return $sql->fetchAll(PDO::FETCH_OBJ);
    // }
 
    
    public function all() {
        $this->query['select'] = "SELECT * FROM {$this->table}";
        return $this;
    }

    public function orderBy($column, $direction = 'asc') {
        $this->query['order'] = "ORDER BY {$column} {$direction}";
        return $this;
    }

    public function get() {
        $sqlParts = [];
        if (isset($this->query['select'])) $sqlParts[] = $this->query['select'];
        if (isset($this->query['where']))  $sqlParts[] = $this->query['where'];
        if (isset($this->query['order']))  $sqlParts[] = $this->query['order'];
        $sql = implode(' ', $sqlParts);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
 
    public function find($id)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE $this->primaryKey = ?");
        $sql->execute(array($id));
        return $sql->fetch(PDO::FETCH_OBJ) ?? null;
    }

    public function save()
    {
        if ($this->isNew) {
            return $this->insert();
        } else {
            $this->update();
        }
    }

    public function saveWithAttr($data)
    {
        $this->attributes = $data;
        if (isset($data[$this->primaryKey]) && $data[$this->primaryKey] > 0) {
            $this->update();
            return Security::encrypt($this->attributes[$this->primaryKey]);
        } else {
            return $this->insert();
        }
    }

    protected function insert()
    {
        $columns = implode(', ', array_keys($this->attributes));
        $values = ':' . implode(', :', array_keys($this->attributes));
        $sql = $this->db->prepare("INSERT INTO $this->table ($columns) VALUES ($values)");

        foreach ($this->attributes as $key => $value) {
            $sql->bindValue(":$key", $value);
        }

        $sql->execute();

        $this->isNew = false;
        $this->attributes[$this->primaryKey] = $this->db->lastInsertId();

        return Security::encrypt($this->attributes[$this->primaryKey]);
    }

    protected function update()
    {
        $setClause = '';

        if (!$this->find($this->attributes[$this->primaryKey])) {
            throw new \Exception('Kayıt bulunamadı.' . $this->attributes[$this->primaryKey]);
        }

        $params = [];
        foreach ($this->attributes as $key => $value) {
            if ($key === $this->primaryKey) continue;
            $setClause .= "$key = :$key, ";
            $params[":$key"] = $value;
        }
        $setClause = rtrim($setClause, ', ');

        if (empty($setClause)) {
            return;
        }

        $sql = $this->db->prepare("UPDATE $this->table SET $setClause WHERE $this->primaryKey = :primary_key_id");

        $sql->bindValue(":primary_key_id", $this->attributes[$this->primaryKey], PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $sql->bindValue($key, $value);
        }

        $sql->execute();

        // if ($sql->rowCount() === 0) {
        //     throw new Exception("Kayıt güncellenemedi.");
        // }
    }

    public function reload()
    {
        if (!$this->isNew) {
            $sql = $this->db->prepare("SELECT * FROM $this->table WHERE $this->primaryKey = ?");
            $sql->execute(array($this->attributes[$this->primaryKey]));
            $data = $sql->fetch(PDO::FETCH_OBJ);
        }
    }

    public function delete($id, $decrypt=true)
    {
        if($decrypt){
            $id = Security::decrypt($id);
        }
        $sql = $this->db->prepare("DELETE FROM $this->table WHERE $this->primaryKey = ?");
        $sql->execute(array($id));

        if ($sql->rowCount() === 0) {
            return new \Exception('Kayıt bulunamadı veya silinemedi.');
        }
        return true;
    }

    //Soft delete
    public function softDelete($id)
    {
        //$id = Security::decrypt($id);
        $sql = $this->db->prepare("UPDATE $this->table SET silinme_tarihi = NOW() WHERE $this->primaryKey = ?");
        $sql->execute(array($id));

        if ($sql->rowCount() === 0) {
            return new \Exception('Kayıt bulunamadı veya silinemedi.');
        }
        return true;
    }

    /**Soft Delete with where clause */
    public function softDeleteWhere($column, $value)
    {
        $sql = $this->db->prepare("UPDATE $this->table SET deleted_at = NOW() WHERE $column = ?");
        $sql->execute(array($value));

        if ($sql->rowCount() === 0) {
            return new \Exception('Kayıt bulunamadı veya silinemedi.');
        }
        return true;
    }

    //where clause
    public function where($column, $value)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE $column = ?");
        $sql->execute(array($value));
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get the department name the current user is allowed to see (Leave/Avans/Talep module only).
     * Returns null if no restriction.
     */
    public function getRestrictedDept()
    {
        $current_user_id = $_SESSION['user_id'] ?? 0;
        if (!$current_user_id) return null;

        // Note: SuperAdmin check is handled by Gate::isSuperAdmin check inside components that use this model
        // To be safe, we check it here too if class is available
        if (class_exists('\App\Service\Gate') && \App\Service\Gate::isSuperAdmin()) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT yonetilen_departman FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        $dept = isset($user->yonetilen_departman) ? trim($user->yonetilen_departman) : '';
        // Remove spaces after commas for FIND_IN_SET compatibility
        $dept = str_replace(', ', ',', $dept);
        return ($dept !== '') ? $dept : null;
    }
}
