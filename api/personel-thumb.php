<?php
declare(strict_types=1);

use App\Helper\Security;
use App\Service\Gate;

require_once dirname(__DIR__) . '/Autoloader.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (
    empty($_SESSION['user_id']) || empty($_SESSION['firma_id'])
    || (!Gate::allowsAny(['personel_listesi', 'personel-takip/list', 'personel/puantaj', 'puantaj_sgk_rapor_islemleri', 'arac_takip', 'arac_takip_yonetim', 'arac_km_onaylari', 'yonetici_onayi', 'nobet/list', 'talepler', 'avans_talepleri', 'izin_talepleri', 'ariza_talepleri', 'canli_sohbet_ayarlari_sekmesi']) && !Gate::isSuperAdmin())
) {
    http_response_code(403);
    exit;
}
session_write_close();

$relative = str_replace('\\', '/', ltrim((string) Security::decrypt((string) ($_GET['p'] ?? '')), '/'));
$projectRoot = realpath(dirname(__DIR__));
$usersDir = $projectRoot ? realpath($projectRoot . '/assets/images/users') : false;
$file = ($usersDir && $relative !== '' && str_starts_with($relative, 'assets/images/users/'))
    ? realpath($projectRoot . '/' . $relative)
    : false;

if (!$file || !$usersDir || !str_starts_with($file, $usersDir . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';
if (!str_starts_with($mime, 'image/')) {
    http_response_code(415);
    exit;
}

$width = (int) ($_GET['w'] ?? 96);
$width = min(256, max(32, $width));
$serve = $file;
$serveMime = $mime;
$version = (string) filemtime($file) . '-' . (string) filesize($file);

if (in_array($mime, ['image/jpeg', 'image/png'], true) && function_exists('imagecreatetruecolor')) {
    $cacheDir = $projectRoot . '/uploads/personel-thumb';
    $thumb = $cacheDir . '/' . sha1($file . '|' . $version . '|' . $width) . '.jpg';
    if (!is_file($thumb) && is_dir($cacheDir) && is_writable($cacheDir)) {
        $src = $mime === 'image/png' ? @imagecreatefrompng($file) : @imagecreatefromjpeg($file);
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $side = min($sw, $sh);
            $dst = imagecreatetruecolor($width, $width);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, (int) (($sw - $side) / 2), (int) (($sh - $side) / 4), $width, $width, $side, $side);
            $tmp = $thumb . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@imagejpeg($dst, $tmp, 80)) {
                @rename($tmp, $thumb);
            }
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
    if (is_file($thumb)) {
        $serve = $thumb;
        $serveMime = 'image/jpeg';
        $version .= '-w' . $width;
    }
}

$etag = '"' . sha1($version) . '"';
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=604800');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $serveMime);
header('Content-Length: ' . (string) filesize($serve));
readfile($serve);
