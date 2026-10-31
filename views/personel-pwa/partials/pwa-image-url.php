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
