<?php
$s1 = getimagesize("assets/images/auth-bg.jpg");
echo "auth-bg: {$s1[0]}x{$s1[1]}\n";
$s2 = getimagesize("assets/images/profile-bg.jpg");
echo "profile-bg: {$s2[0]}x{$s2[1]}\n";
