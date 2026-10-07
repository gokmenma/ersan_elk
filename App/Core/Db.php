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



    public $db;

    //__construct() method is called when a new object is created

    public function __construct()
    {


        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        /** .env dosyasından verileri oku */
        $this->host = $_ENV['DB_HOST'];
        $this->db_name = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];


        $this->getConnection();
    }


    private static $shared_db = null;

    public function getConnection()
    {
        if (self::$shared_db !== null) {
            $this->db = self::$shared_db;
            return $this->db;
        }

        $this->db = null;
        try {
            $this->db = new \PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->exec("set names utf8mb4");

            // Gözlem oturumunda uygulama bağlantısındaki tüm yazmaları DB seviyesinde engelle.
            // Audit kayıtları ObserverAuditModel'in ayrı bağlantısından yazılır.
            if (!empty($_SESSION['observer_mode']['active'])) {
                $this->assertObserverRequestIsReadOnly($this->db);
                $this->db->exec('START TRANSACTION READ ONLY');
                $observerPdo = $this->db;
                register_shutdown_function(static function () use ($observerPdo): void {
                    if ($observerPdo->inTransaction()) {
                        $observerPdo->rollBack();
                    }
                });
            }

            self::$shared_db = $this->db;
        } catch (\PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            throw $e;
        }
        return $this->db;
    }

    private function assertObserverRequestIsReadOnly(\PDO $pdo): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $relative = ltrim(str_starts_with($script, $root) ? substr($script, strlen($root)) : $script, '/');
        $resource = preg_replace('#\.php$#', '', preg_replace('#^views/#', '', $relative));
        $action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
        $allowed = false;

        try {
            $column = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $column->execute(['permission_policies', 'access_mode']);
            if ((int) $column->fetchColumn() > 0 && $resource !== '') {
                $stmt = $pdo->prepare(
                    "SELECT access_mode FROM permission_policies
                     WHERE scope = 'api' AND resource = ? AND action IN (?, '*')
                       AND http_method IN (?, '*') AND is_active = 1
                     ORDER BY (action = ?) DESC, (http_method = ?) DESC LIMIT 1"
                );
                $stmt->execute([$resource, $action, $method, $action, $method]);
                $allowed = $stmt->fetchColumn() === 'read';
            }
        } catch (\Throwable $e) {
            $allowed = false;
        }

        if ($allowed) {
            return;
        }

        $state = (array) ($_SESSION['observer_mode'] ?? []);
        try {
            $audit = $pdo->prepare(
                "INSERT INTO observer_session_logs
                 (actor_user_id, target_user_id, firma_id, event_type, resource, action_name, ip_address, context_json)
                 VALUES (?, ?, ?, 'write_blocked', ?, ?, ?, ?)"
            );
            $audit->execute([
                (int) ($state['actor_user_id'] ?? 0),
                (int) ($state['target_user_id'] ?? 0),
                (int) ($_SESSION['firma_id'] ?? 0) ?: null,
                $resource ?: null,
                $action ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                json_encode(['method' => $method, 'guard' => 'database_bootstrap'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('Gözlem engelleme kaydı yazılamadı: ' . $e->getMessage());
        }

        throw new \RuntimeException('Gözlem modunda yalnızca salt okunur API aksiyonları kullanılabilir.');
    }

    public static function createIsolatedConnection(): \PDO
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();
        $pdo = new \PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    }
}
