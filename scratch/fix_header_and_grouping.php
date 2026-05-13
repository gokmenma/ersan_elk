<?php
$filePath = 'c:\xampp\htdocs\ersan_elk\views\bordro\api.php';
$content = file_get_contents($filePath);

// Marker for the preparation part
$prepMarker = '$nominalMaas = floatval($hesap[\'maasTutari\']);';
$prepPos = strpos($content, $prepMarker);

if ($prepPos !== false) {
    $prepNew = '$nominalMaas = floatval($hesap[\'maasTutari\']);
                // USER REQ: Header badges should show contract salary even for piece-rate personnel
                if ($nominalMaas <= 0) {
                    $nominalMaas = floatval($personel->maas_tutari ?? 0);
                }';
    $content = str_replace($prepMarker, $prepNew, $content);
}

// Marker for the grouping logic
$groupMarker = '$puantajGruplu = [];';
$groupPos = strpos($content, $groupMarker);

if ($groupPos !== false) {
    // We want to replace the whole grouping logic with a more generic one or just add it for Nobet/Kacak.
    // Actually, I'll define a helper closure for grouping.
    
    $replacementGrouping = "
                // Helper closure for grouping and parsing supplemental earnings (Quantity x Unit Price)
                \$groupAndParse = function(\$odemeler, \$prefixToRemove) {
                    \$gruplanmis = [];
                    foreach (\$odemeler as \$odeme) {
                        \$aciklama = str_replace(\$prefixToRemove, '', \$odeme->aciklama ?? '');
                        \$anaMetin = trim(\$aciklama);
                        \$detayMetin = '';
                        if (preg_match('/^(.*?)\s*\((.*?)\)\$/', \$aciklama, \$matches)) {
                            \$anaMetin = trim(\$matches[1]);
                            \$detayMetin = trim(\$matches[2]);
                        }
                        
                        \$adet = 0; \$birimFiyat = '';
                        if (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', \$detayMetin, \$detayMatch)) {
                            \$adet = intval(\$detayMatch[1]); 
                            \$birimFiyat = trim(\$detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet/iu', \$aciklama, \$adetMatch)) {
                            \$adet = intval(\$adetMatch[1]);
                        }
                        
                        \$groupKey = mb_strtolower(\$anaMetin, 'UTF-8');
                        if (!isset(\$gruplanmis[\$groupKey])) {
                            \$gruplanmis[\$groupKey] = [
                                'ana' => \$anaMetin,
                                'adet' => 0,
                                'tutar' => 0,
                                'fiyat_kirilim' => []
                            ];
                        }
                        
                        \$gruplanmis[\$groupKey]['adet'] += \$adet;
                        \$gruplanmis[\$groupKey]['tutar'] += floatval(\$odeme->tutar);
                        
                        \$fiyatKey = \$birimFiyat !== '' ? \$birimFiyat : '__unknown__';
                        if (!isset(\$gruplanmis[\$groupKey]['fiyat_kirilim'][\$fiyatKey])) {
                            \$gruplanmis[\$groupKey]['fiyat_kirilim'][\$fiyatKey] = ['birim_fiyat' => \$birimFiyat, 'adet' => 0, 'tutar' => 0];
                        }
                        \$gruplanmis[\$groupKey]['fiyat_kirilim'][\$fiyatKey]['adet'] += \$adet;
                        \$gruplanmis[\$groupKey]['fiyat_kirilim'][\$fiyatKey]['tutar'] += floatval(\$odeme->tutar);
                    }
                    uasort(\$gruplanmis, function (\$a, \$b) { return \$b['tutar'] <=> \$a['tutar']; });
                    return \$gruplanmis;
                };

                \$puantajGruplu = \$groupAndParse(\$puantajOdemeler, ['[Puantaj] ', '[Sayaç] ']);
                \$nobetGruplu = \$groupAndParse(\$nobetOdemeler, ['[Nöbet] ']);
                \$kacakGruplu = \$groupAndParse(\$kacakKontrolOdemeler, ['[Kaçak Kontrol] ']);
";

    // Find the end of the existing puantajGruplu loop to replace it.
    $groupEndMarker = 'uasort($puantajGruplu, function ($a, $b) { return $b[\'tutar\'] <=> $a[\'tutar\']; });';
    $groupEndPos = strpos($content, $groupEndMarker);
    
    if ($groupEndPos !== false) {
        $content = substr($content, 0, $groupPos) . $replacementGrouping . substr($content, $groupEndPos + strlen($groupEndMarker));
    }
}

// Now replace the rendering parts for Nöbet and Kaçak Kontrol
// This is more tricky. I'll just use a direct replace for the loops.

$nobetLoopMarker = 'foreach ($nobetOdemeler as $nb) {';
$nobetLoopPos = strpos($content, $nobetLoopMarker);
if ($nobetLoopPos !== false) {
    $nobetLoopNew = "foreach (\$nobetGruplu as \$grup) {
                        foreach (\$grup['fiyat_kirilim'] as \$kirilim) {
                            \$detStr = \$kirilim['adet'] > 0 ? \$kirilim['adet'] . ' Adet' : '';
                            \$birim = \$kirilim['birim_fiyat'] !== '' ? ' x ' . \$kirilim['birim_fiyat'] . ' ₺' : '';
                            \$html .= '<tr class=\"child-row collapse ' . \$collId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(\$grup['ana']) . ' <small class=\"text-muted\">' . \$detStr . \$birim . '</small></td>
                                        <td class=\"text-end pe-4\">+' . number_format(\$kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }";
    // Find the end of the loop
    $loopEnd = strpos($content, '}', $nobetLoopPos);
    $loopEnd = strpos($content, '}', $loopEnd + 1); // Need to skip the inner loop end if any.
    // Actually, I'll just replace the specific block.
}

// Since I have the content, I'll just use a more robust way: 
// I'll rewrite the api.php logic for detail rendering entirely to be clean.

file_put_contents($filePath, $content);
echo \"Applied header and grouping fixes\";
?>
