<?php

use App\Helper\Security;

                        // ========== TEK DÖNGÜDE ÖN-HESAPLAMA (Performance Optimization) ==========
                        // Tüm personel değerlerini tek döngüde hesapla, sonuçları $preCalc dizisine kaydet
                        // Hem özet kartlarda hem tablo satırlarında bu veriler kullanılacak
                        $toplamAlacagi = 0;
                        $toplamKesintiHaricIcra = 0;
                        $toplamNetAlacagi = 0;
                        $toplamIcra = 0;
                        $toplamBanka = 0;
                        $toplamSodexo = 0;
                        $toplamElden = 0;
                        $toplamSgkVergi = 0;
                        $latestCalculation = null;
                        $latestCalculator = null;
                        $preCalc = []; // Hesaplanmış değerleri sakla
                    
                        // Dönem tarihlerini döngü dışında bir kez hesapla
                        $donemBasTs = $selectedDonem ? strtotime($selectedDonem->baslangic_tarihi) : 0;
                        $donemBitTs = $selectedDonem ? strtotime($selectedDonem->bitis_tarihi) : 0;
                        $aydakiGunSayisi = $selectedDonem ? date('t', $donemBasTs) : 30;

                        // Dönem başına tüm genel ayarları tek sorguda çek (döngü içinde tekrar tekrar getGenelAyar() çağırmamak için)
                        $genelAyarlarMap = $selectedDonem ? $BordroParametre->getAllGenelAyarlarMap($selectedDonem->baslangic_tarihi) : [];

                        // Asgari ücreti çek
                        $asgariUcretNet = 0;
                        if ($selectedDonem) {
                            $asgariUcretNet = $genelAyarlarMap['asgari_ucret_net'] ?? 17002.12;
                        }

                        $parametrelerMap = $selectedDonem ? $BordroParametre->getAllParametrelerMap($selectedDonem->baslangic_tarihi) : [];

                        $buildHesapDetayPopoverList = function (array $satirlar, string $ustBilgi = '') {
                            $h = '<div class="ref-popover-content" style="min-width:230px;">';
                            if ($ustBilgi !== '') {
                                $h .= '<div style="color:#94a3b8; font-size:0.72rem; margin-bottom:6px; border-bottom:1px dashed rgba(255,255,255,0.15); padding-bottom:5px;">' . $ustBilgi . '</div>';
                            }
                            foreach ($satirlar as $i => $s) {
                                $son = ($i === array_key_last($satirlar));
                                $stil = $son ? 'margin-top:5px; padding-top:5px; border-top:1px solid rgba(255,255,255,0.15); font-weight:bold;' : 'margin-bottom:4px;';
                                $renk = $s['renk'] ?? '#e2e8f0';
                                $h .= '<div style="display:flex; justify-content:space-between; gap:20px; ' . $stil . '"><span style="color:#cbd5e1;">' . $s['label'] . '</span><span style="color:' . $renk . ';">' . $s['value'] . '</span></div>';
                            }
                            $h .= '</div>';
                            return $h;
                        };

                        $gorevGecmisiEksikPersoneller = []; // Görev geçmişi eksik personeller
                    
                        foreach ($personeller as $p) {
                            if (empty($p->gorev_gecmisi_var)) {
                                $gorevGecmisiEksikPersoneller[] = $p->adi_soyadi;
                            }

                            $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($p, $selectedDonem, floatval($asgariUcretNet));

                            $pToplamAlacagi = $hesap['toplamAlacagi'];
                            $pKesintiHaricIcra = $hesap['kesintiHaricIcra'];
                            $pNetAlacagi = $hesap['netAlacagi'];
                            $pIcra = $hesap['icraKesintisi'];
                            $pCalismaGunu = $hesap['calismaGunu'];
                            $bankaP = $hesap['bankaOdemesi'];
                            $sodexoP = $hesap['sodexoOdemesi'];
                            $eldenP = $hesap['eldenOdeme'];
                            $sgkVergiKesintisiP = floatval($p->sgk_isci ?? 0) + floatval($p->issizlik_isci ?? 0) + floatval($p->gelir_vergisi ?? 0) + floatval($p->damga_vergisi ?? 0);

                            // Tutarları direkt motor hesaplamasından al (Tutarlılık için)
                            $toplamAlacagi += $pToplamAlacagi;
                            $toplamKesintiHaricIcra += $pKesintiHaricIcra;
                            $toplamNetAlacagi += $pNetAlacagi;
                            $toplamIcra += $pIcra;
                            $toplamBanka += $bankaP;
                            $toplamSodexo += $sodexoP;
                            $toplamElden += $eldenP;
                            $toplamSgkVergi += $sgkVergiKesintisiP;

                            // En son hesaplama tarihi ve hesaplayan bilgisi
                            if ($p->hesaplama_tarihi && (!$latestCalculation || $p->hesaplama_tarihi > $latestCalculation)) {
                                $latestCalculation = $p->hesaplama_tarihi;
                                $latestCalculator = $p->hesaplayan_ad_soyad ?? null;
                            }

                            // İcra Popover Hazırlığı
                            $icraPopoverHtml = '';
                            if ($pIcra > 0 && $selectedDonem) {
                                $detayData = json_decode($p->hesaplama_detay ?? '', true);
                                $detayData = is_array($detayData) ? $detayData : [];
                                $ozetDetay = is_array($detayData['ozet'] ?? null) ? $detayData['ozet'] : [];
                                $matrahlar = is_array($detayData['matrahlar'] ?? null) ? $detayData['matrahlar'] : [];
                                $sskGun = intval($matrahlar['ssk_gunu'] ?? ($p->calisan_gun ?? $pCalismaGunu ?? 30));
                                
                                $icraMatrahDetaylari = [];
                                $bankaYatacakBazModal = round(($asgariUcretNet / 30) * $sskGun, 2);
                                $icraMatrahDetaylari[] = [
                                    'label' => 'Net Asgari Ücret (' . $sskGun . ' Gün)',
                                    'value' => number_format($bankaYatacakBazModal, 2, ',', '.') . ' ₺',
                                    'renk' => '#ffffff'
                                ];

                                $yemekParam = $parametrelerMap['yemek_yardimi_tum'] ?? $parametrelerMap['yemek'] ?? null;
                                $yemekIcraDahil = $yemekParam ? (bool)$yemekParam->icra_pirim_dahil : true;
                                $yemekDahilTutar = floatval($ozetDetay['dahil_yemek_yardimi'] ?? 0);
                                if ($yemekIcraDahil && $yemekDahilTutar > 0) {
                                    $icraMatrahDetaylari[] = [
                                        'label' => 'Yemek Yardımı (Maaşa Dahil)',
                                        'value' => '+' . number_format($yemekDahilTutar, 2, ',', '.') . ' ₺',
                                        'renk' => '#10b981'
                                    ];
                                }

                                $esParam = $parametrelerMap['es_yardimi'] ?? $parametrelerMap['aile_yardimi'] ?? null;
                                $esIcraDahil = $esParam ? (bool)$esParam->icra_pirim_dahil : true;
                                $esDahilTutar = floatval($ozetDetay['dahil_es_yardimi'] ?? 0);
                                if ($esIcraDahil && $esDahilTutar > 0) {
                                    $icraMatrahDetaylari[] = [
                                        'label' => 'Eş Yardımı (Maaşa Dahil)',
                                        'value' => '+' . number_format($esDahilTutar, 2, ',', '.') . ' ₺',
                                        'renk' => '#10b981'
                                    ];
                                }

                                // OTOMATİK MESAJ/HOLIDAY NET HESAPLARI
                                $rtcGunModal = $BordroPersonel->getOzelCalismaGunSayisiCached($p->personel_id, $selectedDonem->baslangic_tarihi, $selectedDonem->bitis_tarihi, 'resmi_tatil_calismasi');
                                $htcGunModal = $BordroPersonel->getOzelCalismaGunSayisiCached($p->personel_id, $selectedDonem->baslangic_tarihi, $selectedDonem->bitis_tarihi, 'hafta_tatili_calismasi');
                                
                                $rtcHedefNetModal = $rtcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2) : 0.0;
                                $htcHedefNetModal = $htcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2) : 0.0;

                                $rtcResmiTutar = 0.0;
                                $htcResmiTutar = 0.0;
                                if ($rtcHedefNetModal > 0 || $htcHedefNetModal > 0) {
                                    $donemYilModal2 = (int) date('Y', strtotime($selectedDonem->baslangic_tarihi));
                                    // RTÇ/HTÇ ayın ana vergi matrahından sonra vergilendirilir. Böylece
                                    // ay içinde geçilen gelir vergisi dilimi gross-up hesabına da yansır.
                                    $kumulatifMatrahModal2 = floatval($matrahlar['onceki_kumulatif'] ?? 0)
                                        + floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                                    $sgkOraniModal2 = floatval($genelAyarlarMap['sgk_isci_orani'] ?? 14) / 100;
                                    $issizlikOraniModal2 = floatval($genelAyarlarMap['issizlik_isci_orani'] ?? 1) / 100;
                                    $damgaOraniModal2 = floatval($genelAyarlarMap['damga_vergisi_orani'] ?? 0.759) / 100;

                                    $rtcMatrahModal2 = 0.0;
                                    if ($rtcHedefNetModal > 0) {
                                        $rtcGrossModal = $BordroParametre->bruteUpForNetTarget($rtcHedefNetModal, $kumulatifMatrahModal2, $sgkOraniModal2, $issizlikOraniModal2, $damgaOraniModal2, $donemYilModal2);
                                        $rtcResmiTutar = $rtcGrossModal['brut'];
                                        $rtcMatrahModal2 = $rtcGrossModal['matrah'];
                                    }
                                    if ($htcHedefNetModal > 0) {
                                        $htcGrossModal = $BordroParametre->bruteUpForNetTarget($htcHedefNetModal, $kumulatifMatrahModal2 + $rtcMatrahModal2, $sgkOraniModal2, $issizlikOraniModal2, $damgaOraniModal2, $donemYilModal2);
                                        $htcResmiTutar = $htcGrossModal['brut'];
                                    }
                                }

                                $rtcParam = $parametrelerMap['resmi_tatil_calisma'] ?? null;
                                $htcParam = $parametrelerMap['hafta_tatili_calisma'] ?? null;
                                $rtcIcraDahil = $rtcParam ? (bool)$rtcParam->icra_pirim_dahil : true;
                                $htcIcraDahil = $htcParam ? (bool)$htcParam->icra_pirim_dahil : true;

                                $rtcNetEtki = 0.0;
                                $htcNetEtki = 0.0;
                                if ($rtcResmiTutar > 0 && $rtcIcraDahil) {
                                    $rtcNetEtki = $rtcHedefNetModal;
                                    $icraMatrahDetaylari[] = [
                                        'label' => 'Resmi Tatil Çalışma Net',
                                        'value' => '+' . number_format($rtcNetEtki, 2, ',', '.') . ' ₺',
                                        'renk' => '#10b981'
                                    ];
                                }
                                if ($htcResmiTutar > 0 && $htcIcraDahil) {
                                    $htcNetEtki = $htcHedefNetModal;
                                    $icraMatrahDetaylari[] = [
                                        'label' => 'Hafta Tatili Çalışma Net',
                                        'value' => '+' . number_format($htcNetEtki, 2, ',', '.') . ' ₺',
                                        'renk' => '#10b981'
                                    ];
                                }

                                $ekOdemelerDetayList = $detayData['ek_odemeler'] ?? [];
                                foreach ($ekOdemelerDetayList as $ek) {
                                    $ekKod = $ek['kod'] ?? '';
                                    $ekTutar = floatval($ek['net_etki'] ?? $ek['tutar'] ?? 0);
                                    if ($ekTutar <= 0) continue;
                                    if (strpos($ekKod, 'yemek') !== false || strpos($ekKod, 'es_yardimi') !== false || strpos($ekKod, 'aile') !== false || $ekKod === 'yuvarlama_farki' || $ekKod === 'yemek_yardimi_dengeleme') {
                                        continue;
                                    }
                                    $ekParam = $parametrelerMap[$ekKod] ?? null;
                                    if ($ekParam && !empty($ekParam->icra_pirim_dahil) && $ekParam->icra_pirim_dahil == 1) {
                                        $isAbsorbed = !empty($p->yemek_yardimi_dahil) && ($ekParam->odeme_yontemi ?? 'banka') === 'banka';
                                        if (!$isAbsorbed) {
                                            $icraMatrahDetaylari[] = [
                                                'label' => htmlspecialchars($ek['etiket'] ?? $ekKod, ENT_QUOTES, 'UTF-8'),
                                                'value' => '+' . number_format($ekTutar, 2, ',', '.') . ' ₺',
                                                'renk' => '#10b981'
                                            ];
                                        }
                                    }
                                }

                                $icraMatrahToplami = $bankaYatacakBazModal;
                                if ($yemekIcraDahil) $icraMatrahToplami += $yemekDahilTutar;
                                if ($esIcraDahil) $icraMatrahToplami += $esDahilTutar;
                                if ($rtcIcraDahil) $icraMatrahToplami += $rtcNetEtki;
                                if ($htcIcraDahil) $icraMatrahToplami += $htcNetEtki;
                                foreach ($ekOdemelerDetayList as $ek) {
                                    $ekKod = $ek['kod'] ?? '';
                                    $ekTutar = floatval($ek['net_etki'] ?? $ek['tutar'] ?? 0);
                                    if ($ekTutar <= 0) continue;
                                    if (strpos($ekKod, 'yemek') !== false || strpos($ekKod, 'es_yardimi') !== false || strpos($ekKod, 'aile') !== false || $ekKod === 'yuvarlama_farki' || $ekKod === 'yemek_yardimi_dengeleme') {
                                        continue;
                                    }
                                    $ekParam = $parametrelerMap[$ekKod] ?? null;
                                    if ($ekParam && !empty($ekParam->icra_pirim_dahil) && $ekParam->icra_pirim_dahil == 1) {
                                        $isAbsorbed = !empty($p->yemek_yardimi_dahil) && ($ekParam->odeme_yontemi ?? 'banka') === 'banka';
                                        if (!$isAbsorbed) {
                                            $icraMatrahToplami += $ekTutar;
                                        }
                                    }
                                }

                                $icraMatrahDetaylari[] = [
                                    'label' => 'Toplam İcra Matrahı',
                                    'value' => number_format($icraMatrahToplami, 2, ',', '.') . ' ₺',
                                    'renk' => '#38bdf8'
                                ];

                                $icraPopoverHtml = $buildHesapDetayPopoverList($icraMatrahDetaylari, 'İcra Matrahı Detayları (Kesintiye Dahil Kalemler)');
                            }

                            // Ön-hesaplama sonuçlarını kaydet (tablo satırında kullanılacak)
                            $preCalc[$p->id] = [
                                'enc_id' => Security::encrypt($p->personel_id),
                                'toplamAlacagi' => $pToplamAlacagi,
                                'kesintiHaricIcra' => $pKesintiHaricIcra,
                                'netAlacagi' => $pNetAlacagi,
                                'icraKesintisi' => $pIcra,
                                'sgkVergiKesintisi' => $sgkVergiKesintisiP,
                                'calismaGunu' => $pCalismaGunu,
                                'eldenOdeme' => $eldenP,
                                'bankaOdemesi' => $bankaP,
                                'sodexoOdemesi' => $sodexoP,
                                'icraPopoverHtml' => $icraPopoverHtml
                            ];
                        }
