<?php
$file = 'App/Model/BordroPersonelModel.php';
$content = file_get_contents($file);

$search = '/\/\/\s*1\.\s*Yemek\s*Yardımı\s*if\s*\(!empty\(\$personel->yemek_yardimi_aliyor\)\s*&&\s*!empty\(\$personel->yemek_yardimi_parametre_id\)\)\s*\{\s*\$param\s*=\s*\$this->cachedParametreModel->find\(\$personel->yemek_yardimi_parametre_id\);\s*if\s*\(\$param\)\s*\{\s*\$tutar\s*=\s*floatval\(\$param->varsayilan_tutar\s*\?\?\s*0\);/u';

// Let's use a simpler regex
$search = '/(\/\/\s*1\.\s*Yemek\s*Yardımı.*?)(\$tutar\s*=\s*floatval\(\$param->varsayilan_tutar\s*\?\?\s*0\);)/su';

$replacement = '$1// Eğer personelde manuel yemek tutarı girilmişse (0\'dan büyükse) onu kullan, yoksa parametredeki varsayılanı kullan
                $tutar = (floatval($personel->yemek_yardimi_tutari ?? 0) > 0) 
                    ? floatval($personel->yemek_yardimi_tutari) 
                    : floatval($param->varsayilan_tutar ?? 0);';

// Only replace the first occurrence
$newContent = preg_replace($search, $replacement, $content, 1);

if ($newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Success";
} else {
    echo "Failed to match";
}
