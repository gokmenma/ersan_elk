<?php
namespace App\Service;

use App\InterFaces\LoggerInterface;
use PDO;
use PDOException;

/**
 * DatabaseLogger, LoggerInterface sözleşmesini uygulayan ve logları
 * bir veritabanı tablosuna yazan somut bir sınıftır.
 */
class DataBaseLogger implements LoggerInterface
{
    /**
     * Veritabanı bağlantı nesnesi.
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Sınıf oluşturulduğunda, veritabanı bağlantısını alır.
     * Bu yönteme "Dependency Injection" (Bağımlılık Enjeksiyonu) denir.
     * Sınıf, kendi veritabanı bağlantısını oluşturmak yerine, dışarıdan hazır alır.
     * Bu, kodu daha esnek ve test edilebilir hale getirir.
     *
     * @param PDO $pdo_connection Aktif bir PDO veritabanı bağlantısı.
     */
    public function __construct(PDO $pdo_connection)
    {
        $this->pdo = $pdo_connection;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    
    /**
     * Tüm loglama metotlarının kullandığı merkezi metot.
     * Log verilerini veritabanına kaydeder.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // {user_id} gibi yer tutucuları gerçek değerlerle değiştir.
        foreach ($context as $key => $val) {
             if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $message = str_replace("{{$key}}", (string)$val, $message);
            }
        }

        try {
            $sql = "INSERT INTO logs (level, message, context) VALUES (:level, :message, :context)";
            $stmt = $this->pdo->prepare($sql);
            
            // Eğer context dizisi boş ise veritabanına NULL yaz, doluysa JSON formatında yaz.
            $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt->execute([
                ':level'   => strtoupper($level),
                ':message' => $message,
                ':context' => $contextJson
            ]);

        } catch (PDOException $e) {
            // Veritabanına log yazılamazsa ne olacak?
            // Bu kritik bir durum. En azından PHP'nin hata loguna yazmalıyız ki,
            // hem orijinal logu kaybetmeyelim hem de veritabanı sorunundan haberdar olalım.
            error_log(
                "CRITICAL: DatabaseLogger failed to write to database. Error: " . $e->getMessage() . 
                " | Original Log: [{$level}] {$message} " . ($contextJson ?? '')
            );
        }
    }
}