<?php
function cropCenter($source, $dest, $targetWidth, $targetHeight)
{
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } else
        return;

    $origWidth = $info[0];
    $origHeight = $info[1];

    $newImage = imagecreatetruecolor($targetWidth, $targetHeight);

    $sourceAspect = $origWidth / $origHeight;
    $targetAspect = $targetWidth / $targetHeight;

    if ($sourceAspect > $targetAspect) {
        $tempHeight = $targetHeight;
        $tempWidth = (int) ($targetHeight * $sourceAspect);
    } else {
        $tempWidth = $targetWidth;
        $tempHeight = (int) ($targetWidth / $sourceAspect);
    }

    $tempImage = imagecreatetruecolor($tempWidth, $tempHeight);
    imagecopyresampled($tempImage, $image, 0, 0, 0, 0, $tempWidth, $tempHeight, $origWidth, $origHeight);

    $x0 = ($tempWidth - $targetWidth) / 2;
    $y0 = ($tempHeight - $targetHeight) / 2;
    imagecopy($newImage, $tempImage, 0, 0, $x0, $y0, $targetWidth, $targetHeight);

    imagejpeg($newImage, $dest, 90);
    imagedestroy($image);
    imagedestroy($newImage);
    imagedestroy($tempImage);
}

cropCenter('assets/images/bg-1.jpg', 'assets/images/screenshot-mobile.jpg', 600, 1200);
cropCenter('assets/images/bg-2.jpg', 'assets/images/screenshot-desktop.jpg', 1200, 800);
echo "Screenshots generated!\n";
