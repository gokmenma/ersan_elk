<?php



namespace App\Core;

class Db {

   
     private $host = "localhost";
    private $db_name = "ersan_personel"; // Update with your actual database name
    private $username = "root"; // Update with your actual username
    private $password = ""; // Update with your actual password

    // private $db_name = "cansagl1_cansaglik"; // Update with your actual database name
    // private $username = "cansagl1_root"; // Update with your actual username
    // private $password = "dg49~wkAQmrm"; // Update with your actual password

    //  $this->db = new PDO("mysql:host=localhost;dbname=cansagl1_cansaglik", "cansagl1_root", "dg49~wkAQmrm");


    public $db;

    //__construct() method is called when a new object is created
    public function __construct() {
        $this->getConnection();
    }


    public function getConnection() {
        $this->db = null;
        try {
            $this->db = new \PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->exec("set names utf8");
        } catch(\PDOException $e) {
            error_log("Conection error: " . $e->getMessage());
            throw $e;
        }
        return $this->db;
    }
}