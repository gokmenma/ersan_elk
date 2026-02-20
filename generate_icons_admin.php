<?php
$sourceFile = __DIR__ . '/assets/images/fav.jpg';
$destDir = __DIR__ . '/assets/icons/';

if (!file_exists($sourceFile)) {
    echo "Source file not found!\n";
    exit;
}

if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}

// Function to resize and save image
function resizeImage($source, $dest, $width, $height)
{
    if (!file_exists($source))
        return false;

    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } else {
        return false;
    }

    $newImage = imagecreatetruecolor($width, $height);

    // Maintain transparency for PNG
    if ($info['mime'] == 'image/png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $width, $height, $transparent);
    } else {
        // Fill white background just in case
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);
    }

    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

    imagepng($newImage, $dest);
    imagedestroy($image);
    imagedestroy($newImage);
    echo "Created: $dest\n";
    return true;
}

resizeImage($sourceFile, $destDir . 'icon-72-new.png', 72, 72);
resizeImage($sourceFile, $destDir . 'icon-144-new.png', 144, 144);
resizeImage($sourceFile, $destDir . 'icon-192-new.png', 192, 192);
resizeImage($sourceFile, $destDir . 'icon-512-new.png', 512, 512);

echo "Done.\n";
