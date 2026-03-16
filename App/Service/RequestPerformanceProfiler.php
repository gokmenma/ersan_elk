<?php

namespace App\Service;

class RequestPerformanceProfiler
{
    private static bool $started = false;
    private static float $startTime = 0.0;
    private static array $metrics = [
        'endpoint' => '',
        'label' => '',
        'db_total_ms' => 0.0,
        'db_count' => 0,
        'segments' => []
    ];

    public static function start(string $label, array $context = []): void
    {
        if (self::$started) {
            return;
        }

        self::$started = true;
        self::$startTime = microtime(true);
        self::$metrics['label'] = $label;
        self::$metrics['endpoint'] = $_SERVER['REQUEST_URI'] ?? $label;

        foreach ($context as $key => $value) {
            self::$metrics[(string) $key] = $value;
        }

        register_shutdown_function([self::class, 'flush']);
    }

    public static function measure(string $segment, callable $callback, int $dbCount = 0)
    {
        $segmentStart = microtime(true);
        $result = $callback();
        $elapsedMs = round((microtime(true) - $segmentStart) * 1000, 2);

        self::addSegment($segment, $elapsedMs);

        if ($dbCount > 0) {
            self::$metrics['db_total_ms'] += $elapsedMs;
            self::$metrics['db_count'] += $dbCount;
        }

        return $result;
    }

    public static function addDbMetrics(float $elapsedMs, int $dbCount = 1): void
    {
        if (!self::$started) {
            return;
        }

        self::$metrics['db_total_ms'] += max(0, $elapsedMs);
        self::$metrics['db_count'] += max(0, $dbCount);
    }

    public static function addMeta(string $key, $value): void
    {
        if (!self::$started) {
            return;
        }

        self::$metrics[$key] = $value;
    }

    private static function addSegment(string $segment, float $elapsedMs): void
    {
        if (!isset(self::$metrics['segments'][$segment])) {
            self::$metrics['segments'][$segment] = 0.0;
        }

        self::$metrics['segments'][$segment] += $elapsedMs;
    }

    public static function flush(): void
    {
        if (!self::$started) {
            return;
        }

        $totalMs = round((microtime(true) - self::$startTime) * 1000, 2);
        $payload = [
            'timestamp' => date('c'),
            'label' => self::$metrics['label'],
            'endpoint' => self::$metrics['endpoint'],
            'page' => $_GET['p'] ?? 'home',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_id' => (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0),
            'firma_id' => (int) ($_SESSION['firma_id'] ?? 0),
            'total_ms' => $totalMs,
            'db_total_ms' => round((float) self::$metrics['db_total_ms'], 2),
            'db_count' => (int) self::$metrics['db_count'],
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'segments' => self::$metrics['segments']
        ];

        foreach (self::$metrics as $key => $value) {
            if (array_key_exists($key, $payload)) {
                continue;
            }
            if ($key === 'segments') {
                continue;
            }
            $payload[$key] = $value;
        }

        $basePath = dirname(__DIR__, 2) . '/cache/perf';
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0777, true);
        }

        $logFile = $basePath . '/request_metrics.log';
        @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}
