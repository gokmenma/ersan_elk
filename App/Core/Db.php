<?php



namespace App\Core;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
use Dotenv\Dotenv;


class Db
{


    private $host = "";
    private $db_name = ""; // Update with your actual database name
    private $username = ""; // Update with your actual username
    private $password = ""; // Update with your actual password

    // private $db_name = "mbeyazil_ersanelektrik"; // Update with your actual database name
    // private $username = "mbeyazil_root"; // Update with your actual username
    // private $password = "G=zGEX1_-6+Zms}&"; // Update with your actual password

    //  $this->db = new PDO("mysql:host=localhost;dbname=cansagl1_cansaglik", "cansagl1_root", "dg49~wkAQmrm");


    public $db;

    //__construct() method is called when a new object is created

    public function __construct()
    {


        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        /** .env dosyasından verileri oku (Önce $_ENV, sonra getenv() kontrol et) */
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? '';
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? '';
        $this->password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';


        $this->getConnection();
    }


    public function getConnection()
    {
        $this->db = null;
        try {
            $this->db = new \PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->exec("set names utf8mb4");
        } catch (\PDOException $e) {
            error_log("Conection error: " . $e->getMessage());
            throw $e;
        }
        return $this->db;
    }
}
