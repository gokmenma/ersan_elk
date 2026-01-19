<?php
namespace App\Service;

use App\InterFaces\LoggerInterface;

class FileLogger implements LoggerInterface
{
    private string $logFilePath;

    public function __construct(string $filePath)
    {
        $this->logFilePath = $filePath;
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

    
    public function log(string $level, string $message, array $context = []): void
    {
        // {user_id} gibi yer tutucuları gerçek değerlerle değiştir.
        foreach ($context as $key => $val) {
            $message = str_replace("{{$key}}", $val, $message);
        }

        $date = date('Y-m-d H:i:s');
        // --- YENİ EKLENEN KISIM ---
        // Eğer context dizisi boş değilse, onu JSON formatına çevir.
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        // Formatlanmış log satırı: Tarih, Seviye, Mesaj ve Context
        $logLine = "[{$date}] [{$level}] {$message} {$contextString}" . PHP_EOL;
        
        // Hata kontrolü ile birlikte dosyaya yazma
        @file_put_contents($this->logFilePath, $logLine, FILE_APPEND | LOCK_EX);
    }
}