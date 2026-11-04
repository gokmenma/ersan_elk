<?php

namespace App\Helper;

class MobileSpa
{
    private const ROUTE_MAP = [
        'home' => '/',
        'talepler' => '/talepler',
        'evrak-takip' => '/evrak-takip',
        'giden-evrak' => '/giden-evrak',
        'arac' => '/arac-takip',
        'km-onaylari' => '/km-onaylari',
        'gorevler' => '/gorevler',
        'raporlar' => '/raporlar',
        'profil' => '/profil',
        'personel' => '/personel',
        'bordro' => '/bordro',
        'cari-takip' => '/cari-takip',
        'efatura' => '/efatura',
        'gelir-gider' => '/gelir-gider',
        'puantaj' => '/puantaj',
        'kacak' => '/kacak-kontrol',
        'nobet' => '/nobet',
        'ihbar' => '/ihbar',
        'yardim' => '/canli-destek',
    ];

    public static function baseUrl(): string
    {
        $env = trim((string) ($_ENV['MOBILE_SPA_URL'] ?? getenv('MOBILE_SPA_URL') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $kok = '';
        foreach (['/mobile-spa/', '/mobile/', '/views/', '/api/'] as $isaret) {
            $konum = strpos($script, $isaret);
            if ($konum !== false) {
                $kok = substr($script, 0, $konum);
                break;
            }
        }
        if ($kok === '') {
            $kok = rtrim(dirname($script), '/');
        }

        return ($https ? 'https://' : 'http://') . $host . $kok . '/mobile-spa/dist';
    }

    public static function url(string $route = '/'): string
    {
        if ($route === '' || $route[0] !== '/') {
            $route = '/' . $route;
        }
        return self::baseUrl() . '/#' . $route;
    }

    public static function fromLegacy(string $page, array $query = []): string
    {
        $page = preg_replace('/[^a-z0-9\-_]/', '', strtolower($page)) ?: 'home';
        $route = self::ROUTE_MAP[$page] ?? '/';

        if ($page === 'personel-duzenle') {
            $id = preg_replace('/[^A-Za-z0-9%=+\/_-]/', '', (string) ($query['id'] ?? ''));
            $route = $id !== '' ? '/personel/' . rawurlencode($id) . '/duzenle' : '/personel';
        }

        return self::url($route);
    }
}
