<?php

namespace App\Helper;

class ReturnUrl
{
    private const SESSION_KEY = 'return_url';
    private const MAX_LENGTH = 1024;
    private const BLOCKED_PAGES = ['login', 'logout', 'firma-secim', 'set-session', 'auth-api', 'reset-password', 'unauthorize'];

    public static function sanitize($url): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '' || strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $url) || preg_match('/%(0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $url)) {
            return null;
        }

        if (strpos($url, '//') === 0) {
            return null;
        }

        $url = ltrim($url, '/');
        if ($url === '' || strpos($url, '//') !== false || strpos($url, '..') !== false) {
            return null;
        }

        $parts = explode('?', $url, 2);
        $path = $parts[0];

        if (!preg_match('#^[A-Za-z0-9_\-./]+$#', $path)) {
            return null;
        }

        if (isset($parts[1]) && !preg_match('#^[A-Za-z0-9_\-./%=&+,:;@~\[\]]*$#', $parts[1])) {
            return null;
        }

        $page = strtolower(preg_replace('/\.php$/i', '', basename($path)));
        if (in_array($page, self::BLOCKED_PAGES, true) || preg_match('#(^|/)(api|kaski|mobile)(/|$)#i', $path) || preg_match('/api(\.php)?$/i', $page)) {
            return null;
        }

        return $url;
    }

    public static function capture(): ?string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && strpos($uri, $base . '/') === 0) {
            $uri = substr($uri, strlen($base));
        }

        return self::sanitize($uri);
    }

    public static function isAjaxRequest(): bool
    {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $fetchMode = strtolower($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');

        return ($fetchMode !== '' && $fetchMode !== 'navigate') || strpos($accept, 'application/json') === 0;
    }

    public static function store(?string $url): void
    {
        $url = self::sanitize($url);
        if ($url !== null) {
            $_SESSION[self::SESSION_KEY] = $url;
        }
    }

    public static function consume(): ?string
    {
        $url = self::sanitize($_SESSION[self::SESSION_KEY] ?? null);
        unset($_SESSION[self::SESSION_KEY]);

        return $url;
    }

    public static function peek(): ?string
    {
        return self::sanitize($_SESSION[self::SESSION_KEY] ?? null);
    }

    public static function loginLocation(?string $url = null): string
    {
        $url = $url ?? self::capture();

        return $url !== null ? 'login.php?returnurl=' . rawurlencode($url) : 'login.php';
    }

    public static function respondSessionExpired(): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'session_expired' => true,
            'message' => 'Oturumunuz sonlanmış. Lütfen tekrar giriş yapın.',
            'data' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
