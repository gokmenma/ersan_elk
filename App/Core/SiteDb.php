<?php

namespace App\Core;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PDO;
use RuntimeException;

class SiteDb
{
    private static ?PDO $paylasilan = null;

    public $db;

    public function __construct()
    {
        $this->db = self::baglan();
    }

    private static function baglan(): PDO
    {
        if (self::$paylasilan !== null) {
            return self::$paylasilan;
        }

        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

        $host = (string) ($_ENV['SITE_DB_HOST'] ?? '');
        $ad = (string) ($_ENV['SITE_DB_NAME'] ?? '');
        $kullanici = (string) ($_ENV['SITE_DB_USER'] ?? '');
        $sifre = (string) ($_ENV['SITE_DB_PASS'] ?? '');
        if ($host === '' || $ad === '' || $kullanici === '') {
            error_log('SiteDb: SITE_DB_HOST, SITE_DB_NAME veya SITE_DB_USER tanımlı değil.');
            throw new RuntimeException('Site veritabanı yapılandırılmamış.');
        }

        try {
            $pdo = new PDO("mysql:host={$host};dbname={$ad};charset=utf8mb4", $kullanici, $sifre, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ]);
            $pdo->exec('set names utf8mb4');
            if (!empty($_SESSION['observer_mode']['active'])) {
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            }
        } catch (\PDOException $e) {
            error_log('SiteDb bağlantı hatası: ' . $e->getMessage());
            throw $e;
        }

        self::$paylasilan = $pdo;
        return $pdo;
    }
}
