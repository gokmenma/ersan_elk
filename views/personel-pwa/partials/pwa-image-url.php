<?php

if (!function_exists('getPwaImageUrl')) {
function getPwaImageUrl($path)
{
    if (empty($path))
        return '';

    $host = $_SERVER['HTTP_HOST'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https:' : 'http:';

    // Eğer 'personel.' ile başlayan bir subdomain kullanılıyorsa (örn: personel.ersantr.com) 
    // Ana dizin (parent domain) üzerinden dosyayı çekelim.
    if (strpos($host, 'personel.') === 0) {
        $mainHost = substr($host, 9);
        return $protocol . '//' . $mainHost . '/' . $path;
    }

    // Subdomain yoksa veya local klasör yapısı ise klasik ../../uploads mantığı
    return '../../' . $path;
}
}

if (!function_exists('pwaProfilFotoUrl')) {
    function pwaProfilFotoUrl($personel): string
    {
        $adaylar = [$personel->personel_resim_yolu ?? '', $personel->resim_yolu ?? ''];
        foreach ($adaylar as $yol) {
            $yol = ltrim(trim((string) $yol), '/');
            if ($yol === '' || strpos($yol, '..') !== false) {
                continue;
            }
            if (is_file(dirname(__DIR__, 2) . '/' . $yol)) {
                return '../../' . $yol;
            }
            if (is_file(dirname(__DIR__) . '/' . $yol)) {
                return $yol;
            }
        }
        return '';
    }
}
