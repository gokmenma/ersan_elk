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

    protected function sanitizeAttributes()
    {
        foreach ($this->attributes as $key => &$value) {
            if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                $lowerKey = strtolower($key);
                if (
                    strpos($lowerKey, 'tarih') !== false ||
                    strpos($lowerKey, 'date') !== false ||
                    strpos($lowerKey, 'time') !== false ||
                    $lowerKey === 'created_at' ||
                    $lowerKey === 'updated_at'
                ) {
                    $value = null;
                }
            }
        }
        unset($value);
    }

    protected function insert()
    {
        $this->sanitizeAttributes();
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
        $this->sanitizeAttributes();
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
     * whereIn clause
     */
    public function whereIn($column, array $values)
    {
        if (empty($values)) return [];
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $sql = "SELECT * FROM $this->table WHERE $column IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($values));
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get array of department names the current user is allowed to see (Leave/Avans/Talep module only).
     * Returns null if no restriction.
     */
    public function getRestrictedDeptArray()
    {
        $current_user_id = $_SESSION['user_id'] ?? 0;
        if (!$current_user_id) return null;

        if (class_exists('\App\Service\Gate') && \App\Service\Gate::isSuperAdmin()) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT yonetilen_departman FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        $raw = isset($user->yonetilen_departman) ? trim($user->yonetilen_departman) : '';
        if ($raw === '') return null;

        // Support '|', ';', and ',' delimiters
        if (strpos($raw, '|') !== false) {
            $depts = explode('|', $raw);
        } elseif (strpos($raw, ';') !== false) {
            $depts = explode(';', $raw);
        } else {
            $depts = explode(',', $raw);
        }

        $depts = array_values(array_filter(array_map('trim', $depts), function($v) {
            return $v !== '';
        }));

        return !empty($depts) ? $depts : null;
    }

    /**
     * Get pipe-separated string representation of restricted departments.
     * Returns null if no restriction.
     */
    public function getRestrictedDept()
    {
        $depts = $this->getRestrictedDeptArray();
        return $depts ? implode('|', $depts) : null;
    }

    /**
     * Build SQL fragment and append bind parameters for department restriction.
     * Uses IN (...) instead of FIND_IN_SET to safely handle department names containing commas.
     */
    public function getRestrictedDeptSql($deptColumn = 'p.departman', &$bindParams = [])
    {
        $depts = $this->getRestrictedDeptArray();
        if ($depts === null) {
            return '';
        }

        $placeholders = implode(',', array_fill(0, count($depts), '?'));
        if (is_array($bindParams)) {
            foreach ($depts as $d) {
                $bindParams[] = $d;
            }
        }

        return " AND (TRIM($deptColumn) IN ($placeholders) OR TRIM($deptColumn) = '' OR $deptColumn IS NULL) AND p.disardan_sigortali = 0";
    }
}
