<?php

namespace App\Helper;

class Security
{
    public static function escape($data)
    {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    // CSRF Token
    public static function csrf()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $token = bin2hex(random_bytes(48));
            $_SESSION['csrf_token'] = $token;
        } else {
            $token = $_SESSION['csrf_token'];
        }
        return $token;
    }

    public static function checkCsrfToken()
    {
        //kullaNıcının session_token alanı ile Session'daki csrf_token alanını karşılaştırır
        $token = $_SESSION['user']->session_token ?? null;
        return hash_equals($_SESSION['csrf_token'], $token);


    }

    public static function generatePassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function passwordControl($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public static function encrypt($data)
    {
        if ($data === null || $data === '') {
            return '';
        }

        $method = "AES-256-CBC";
        $key = hash('sha256', 'mysecretkey', true); // Gerçek projelerde bu anahtar .env dosyasında olmalıdır
        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt((string) $data, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return '';
        }

        // IV + Encrypted birleştir ve base64 yap
        $base64 = base64_encode($iv . $encrypted);

        // URL'de sorun çıkarmaması için rawurlencode
        return rawurlencode($base64);
    }

    public static function decrypt($data)
    {
        // 0, null ve boş değer kontrolü
        if ($data === '0' || $data === 0 || empty($data)) {
            return 0;
        }

        $method = "AES-256-CBC";
        $key = hash('sha256', 'mysecretkey', true);

        // Önce URL decode, sonra base64 decode
        $decoded = base64_decode(rawurldecode($data));

        // En az 16 byte IV olmalı
        if (strlen($decoded) <= 16) {
            return 0;
        }

        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

        return ($decrypted !== false) ? $decrypted : 0;
    }
}