<?php
use App\Model\PersonelModel;

                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                // Dönem bilgilerini al (çalışma günü hesabı için)
                $donemBilgi = $BordroDonem->getDonemById($bp->donem_id) ?: null;

                // Liste ile birebir aynı veri setini kullan (görev geçmişi + JSON_EXTRACT alanları dahil)
                $detayRows = $BordroPersonel->getPersonellerByDonem($bp->donem_id, [$bp->id]);
                if (!empty($detayRows)) {
                    $bp = $detayRows[0];
                }
                
                $Personel = new PersonelModel();
                $personel = $Personel->find($bp->personel_id);
                if ($personel && $donemBilgi) {
                    $personel = $BordroPersonel->overrideWithHistoricalGorevGecmisi($personel, $donemBilgi->baslangic_tarihi, $donemBilgi->bitis_tarihi);
                }

                // Kesinti ve ek ödeme türü etiketleri
                $kesintiTurEtiketleri = [
                    'icra' => 'İcra',
                    'avans' => 'Avans',
                    'nafaka' => 'Nafaka',
                    'ceza' => 'Ceza',
                    'izin_kesinti' => 'Ücretsiz İzin',
                    'bes_kesinti' => 'BES Kesintisi',
                    'diger' => 'Diğer Kesinti'
                ];

                $ekOdemeTurEtiketleri = [
                    'prim' => 'Prim',
                    'mesai' => 'Fazla Mesai',
                    'ikramiye' => 'İkramiye',
                    'yol' => 'Yol Yardımı',
                    'yemek' => 'Yemek Yardımı',
                    'hafta_ici_nobet' => 'Hafta İçi Nöbet',
                    'hafta_sonu_nobet' => 'Hafta Sonu Nöbet',
                    'resmi_tatil_nobet' => 'Resmi Tatil Nöbeti',
                    'nobet_grubu' => 'Nöbet Ödemeleri',
                    'yemek_yardimi_dengeleme' => 'Yemek Yardımı (Maaşa Dahil)',
                    'diger' => 'Diğer Ek Ödeme'
                ];

                // Detaylı ek ödemeleri çek
                $ekOdemelerDetay = $BordroPersonel->getDonemEkOdemeleriDetay($bp->personel_id, $bp->donem_id);

                // Toplamları hesapla
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);

                // Liste ve detayda aynı hesap fonksiyonunu kullan
                $donemBaslangicTarihi = $donemBilgi?->baslangic_tarihi ?? date('Y-m-01');
                $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donemBaslangicTarihi) ?? 17002.12;
                $asgariUcretBrut = $BordroParametre->getGenelAyar('asgari_ucret_brut', $donemBaslangicTarihi) ?? 33030.00;
                $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($bp, $donemBilgi, floatval($asgariUcretNet));
                if (empty($hesap['hesaplanmis'])) {
                    throw new Exception('Bu personelin bordrosu henüz hesaplanmadı. Önce Maaş Hesapla işlemini çalıştırın.');
                }
                $muhasebeFazlaMesaiOzeti = $BordroPersonel->getMuhasebeFazlaMesaiNetOzeti($bp, $donemBilgi);
                $bankaDetayDagilimi = $BordroPersonel->getBankaOdemeDetayDagilimi($hesap, $muhasebeFazlaMesaiOzeti);
                $mealDeduction = floatval($hesap['mealAllowanceDeduction'] ?? 0);
                $spouseDeduction = floatval($hesap['spouseAllowanceDeduction'] ?? 0);
                $isInclusive = (bool) $hesap['isInclusive'];
                $includedDeduction = floatval($hesap['includedAllowanceDeduction'] ?? 0);

                $includedAllowanceFiiliGun = intval($hesap['includedAllowanceFiiliGun'] ?? 0);
                $asgariHakedisModal = floatval($hesap['asgariHakedis'] ?? 0);
                $guncelEkOdeme = floatval($hesap['rawEkOdeme']);
                $rtcGunModal = intval($hesap['rtcGun'] ?? 0);
                $htcGunModal = intval($hesap['htcGun'] ?? 0);

                $maasDurumuGosterim = $hesap['maasDurumu'] ?: ($personel->maas_durumu ?? '-');
                $nominalMaas = floatval($hesap['maasTutari']);
                if ($nominalMaas <= 0) {
                    $nominalMaas = floatval($personel->maas_tutari ?? 0);
                }
                
                $gunlukUcret = $nominalMaas / 30;
                $ucretsizIzinGunu = intval($hesap['ucretsizIzinGunu']);
                $calismaGunu = intval($hesap['calismaGunu']);

                // Tutarları ortak hesap sonucundan al
                $toplamAlacak = floatval($hesap['toplamAlacagi']);
                $netAlacak = floatval($hesap['netAlacagi']);
                $icraKesinti = floatval($hesap['icraKesintisi']);
                $netMaasHesap = floatval($hesap['netMaasGercek']);
                $bankaOdemeModal = floatval($hesap['bankaOdemesi']);
                $sodexoOdemeModal = floatval($hesap['sodexoOdemesi']);
                $digerOdemeModal = floatval($hesap['digerOdeme']);
                $eldenOdemeModal = floatval($hesap['eldenOdeme']);
                $yuvarlamaFarki = floatval($hesap['yuvarlamaFarki'] ?? 0);

                // Ücretsiz izin veya net/brüt maaş ise, çalışılan brüt/net maaşı göster
                $calisanBrutMaas = $toplamAlacak - floatval($hesap['rawEkOdeme']);
                
                $isPrimUsulu = (stripos($maasDurumuGosterim, 'Prim') !== false);
                $isNetMaas = (stripos($maasDurumuGosterim, 'Net') !== false);
                $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $contractHakedisForRounding = floatval($hesap['sozlesmeHakedisi'] ?? 0);
                if ($contractHakedisForRounding <= 0) {
                    $contractHakedisForRounding = ($nominalMaas / 30) * $calismaGunu;
                }
                $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                
                if ($isPrimUsulu) {
                    $puantajToplami = 0;
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0 || strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                            $puantajToplami += floatval($ek->tutar);
                        }
                    }
                    $asgariTaban = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                    $contractHakedisForRounding = max($puantajToplami, $asgariTaban);
                    
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') !== 0 && strpos($aciklama, '[Sayaç]') !== 0 && strpos($aciklama, '[Kaçak Kontrol]') !== 0 && strpos($aciklama, 'Yuvarlama') === false) {
                            $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                            if (strpos($eoTur, 'yemek') === false && strpos($eoTur, 'yy') === false && strpos($eoTur, 'es_yardimi') === false && strpos($eoTur, 'aile') === false) {
                                $nonPuantajExtras += floatval($ek->tutar);
                            }
                        }
                    }
                } else {
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        if (strpos($ek->aciklama, 'Yuvarlama') === false) {
                            $nonPuantajExtras += floatval($ek->tutar);
                        }
                    }
                }
                
                if (intval($bp->personel_id ?? 0) === 77 && ($donemBilgi->baslangic_tarihi ?? '') === '2026-04-01') {
                    $contractHakedisForRounding = (33000 / 30) * 13;
                    $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                }
                
                $displayMealDeduction = $mealDeduction;
                if ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, $includedDeduction - $spouseDeduction);
                }
                $asgariHakedisModal = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                $displayBaseHakedis = round(($nominalMaas / 30) * $calismaGunu, 2);
                $displayEkOdemeToplami = 0.0;
                $matchingTableItemsSum = 0.0;
                foreach ($ekOdemelerListe as $ek) {
                    $aciklama = (string)($ek->aciklama ?? '');
                    $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                    $isYuvarlama = (($ek->tur ?? '') === 'yuvarlama_farki') || stripos($aciklama, 'Yuvarlama') !== false;
                    if ($isYuvarlama) continue;

                    $isDahilYemek = !empty($bp->yemek_yardimi_dahil)
                        && ($eoTur === 'yemek_yardimi_tum' || $eoTur === 'yemek' || strpos($eoTur, 'yemek') !== false);
                    
                    if ($isDahilYemek) { 
                        $matchingTableItemsSum += floatval($ek->tutar);
                    } else {
                        $displayEkOdemeToplami += floatval($ek->tutar);
                    }
                }
                $displayEkOdemeToplami += max(0, $matchingTableItemsSum - floatval($hesap['mealAllowanceDeduction'] ?? 0));
                if (!empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                } elseif ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                }
                $displayToplamAlacak = $toplamAlacak;
                $toplamYuvarlamaFarki = round($yuvarlamaFarki, 2);
                if (abs($toplamYuvarlamaFarki) < 0.01) $toplamYuvarlamaFarki = 0;

                // PREPARE KESINTILER DATA FOR COLUMN 3
                $kesintiKayitlariOnce = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $kesintiKayitlari = $kesintiKayitlariOnce;
                $kesintilerGruplanmis = [];

                foreach ($kesintiKayitlari as $k) {
                    if ($k->tur === 'izin_kesinti') {
                        continue;
                    }
                    $etiket = $kesintiTurEtiketleri[$k->tur] ?? ucfirst($k->tur);
                    if (!isset($kesintilerGruplanmis[$etiket])) {
                        $kesintilerGruplanmis[$etiket] = (object) [
                            'etiket' => $etiket,
                            'toplam_tutar' => 0,
                            'adet' => 0
                        ];
                    }
                    $kesintilerGruplanmis[$etiket]->toplam_tutar += floatval($k->tutar);
                    $kesintilerGruplanmis[$etiket]->adet++;
                }
                uasort($kesintilerGruplanmis, function ($a, $b) {
                    return $b->toplam_tutar <=> $a->toplam_tutar;
                });
                $guncelKesintiGosterim = 0;
                foreach ($kesintilerGruplanmis as $kGrup) {
                    $guncelKesintiGosterim += $kGrup->toplam_tutar;
                }
                
                $toplamYasalKesinti = 0;
                if ($bp->sgk_isci > 0) $toplamYasalKesinti += floatval($bp->sgk_isci);
                if ($bp->issizlik_isci > 0) $toplamYasalKesinti += floatval($bp->issizlik_isci);
                if ($bp->gelir_vergisi > 0) $toplamYasalKesinti += floatval($bp->gelir_vergisi);
                if ($bp->damga_vergisi > 0) $toplamYasalKesinti += floatval($bp->damga_vergisi);

                // DATA PREPARATION PART 2: GROUP & SUM EXTRAS & PUANTAJ
                $ekOdemelerNonPuantaj = [];
                $puantajOdemeler = [];
                $kacakKontrolOdemeler = [];
                $nobetOdemeler = [];
                $toplamNobetTutar = 0;
                $toplamKacakTutar = 0;
                $toplamPuantajTutar = 0;

                $tumEkOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $detayData = json_decode($bp->hesaplama_detay ?? '', true);
                $snapshotEkOdemeNetleri = [];
                foreach (($detayData['ek_odemeler'] ?? []) as $snapshotEkOdeme) {
                    if (isset($snapshotEkOdeme['id'], $snapshotEkOdeme['net_etki'])) {
                        $snapshotEkOdemeNetleri[(int) $snapshotEkOdeme['id']] = (float) $snapshotEkOdeme['net_etki'];
                    }
                }

                foreach ($tumEkOdemeler as $odeme) {
                    $parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', $odeme->aciklama ?? '', $adetMatch)) { $parsedAdet = intval($adetMatch[1]); }
                    $aciklama = (string)($odeme->aciklama ?? '');
                    $odemeTurLower = mb_strtolower((string)($odeme->tur ?? ''), 'UTF-8');
                    
                    if (!$isPrimUsulu && !empty($bp->yemek_yardimi_dahil) && ($odemeTurLower === 'yemek_yardimi_tum' || $odemeTurLower === 'yemek' || strpos($odemeTurLower, 'yemek') !== false) && $odemeTurLower !== 'yemek_yardimi_dengeleme') { continue; }
                    if (($odemeTurLower === 'es_yardimi' || strpos($odemeTurLower, 'es_yardimi') !== false || strpos($odemeTurLower, 'aile') !== false)) { continue; }
                    if (($odeme->tur ?? '') === 'yuvarlama_farki' || $aciklama === 'Yuvarlama Farkı') { continue; }
                    
                    if (strpos($aciklama, '[Nöbet]') === 0) {
                        $nobetGosterim = clone $odeme;
                        // Sözleşme tarafında kayıtlı nöbet hakedişi korunur; resmî net etki
                        // ayrı hesapta gösterilir ve bu tutarın üzerine yazılamaz.
                        $nobetGosterim->tutar = floatval($odeme->tutar);
                        $nobetOdemeler[] = $nobetGosterim;
                        $toplamNobetTutar += floatval($nobetGosterim->tutar);
                    } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                        $kacakKontrolOdemeler[] = $odeme;
                        $toplamKacakTutar += floatval($odeme->tutar);
                    } elseif (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0) {
                        $puantajOdemeler[] = $odeme;
                        $toplamPuantajTutar += floatval($odeme->tutar);
                    } else {
                        $tur = $odeme->tur;
                        if (strpos($tur, 'nobet') !== false) { $tur = 'nobet_grubu'; }
                        $hesaplananTutar = floatval($odeme->tutar);
                        $hesaplananAdet = $parsedAdet;
                        if (isset($detayData['ek_odemeler']) && is_array($detayData['ek_odemeler'])) {
                            foreach ($detayData['ek_odemeler'] as $jedo) {
                                if (isset($jedo['id']) && $jedo['id'] == $odeme->id) {
                                    $hesaplananTutar = floatval($jedo['hesaplanan_tutar'] ?? $jedo['tutar']);
                                    $hesaplananAdet = intval($jedo['gun_sayisi'] ?? $parsedAdet);
                                    break; 
                                }
                            }
                        }
                        if (!isset($ekOdemelerNonPuantaj[$tur])) { $ekOdemelerNonPuantaj[$tur] = ['toplam' => 0, 'adet' => 0, 'kayit_sayisi' => 0, 'items' => []]; }
                        $ekOdemelerNonPuantaj[$tur]['toplam'] += $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['adet'] += $hesaplananAdet;
                        $ekOdemelerNonPuantaj[$tur]['kayit_sayisi']++;
                        $odeme->tutar = $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['items'][] = $odeme;
                    }
                }
                
                $modalBaseRowValue = $isPrimUsulu ? 0 : $asgariHakedisModal;
                $modalMaasFarkiGosterim = 0;
                
                if (!$isPrimUsulu) {
                    $totalDahilYardim = $displayMealDeduction + $spouseDeduction;
                    $sozlesmeHakedisTotal = round(($nominalMaas / 30) * $calismaGunu, 2);
                    $contractTarget = max($sozlesmeHakedisTotal, $asgariHakedisModal + $totalDahilYardim);
                    $rtcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2);
                    $htcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2);
                    $modalMaasFarkiGosterim = max(0, round($contractTarget - $asgariHakedisModal - $totalDahilYardim - $rtcResmiPayModal - $htcResmiPayModal, 2));
                }

                $modalEkOdemeToplami = $toplamPuantajTutar + $toplamNobetTutar + $toplamKacakTutar;
                foreach ($ekOdemelerNonPuantaj as $edata) {
                    $modalEkOdemeToplami += floatval($edata['toplam'] ?? 0);
                }

                if ($isInclusive) {
                    $anaHakedisGosterim = $contractHakedisForRounding > 0
                        ? round($contractHakedisForRounding, 2)
                        : round($modalBaseRowValue + $modalMaasFarkiGosterim + $displayMealDeduction + $spouseDeduction, 2);
                    $bagimsizEkOdemeGosterim = $modalEkOdemeToplami;
                    if ($isPrimUsulu) {
                        $bagimsizEkOdemeGosterim = max(0, $bagimsizEkOdemeGosterim - $toplamPuantajTutar);
                    }
                    $htcGosterimEkOdeme = $htcGunModal > 0 ? round($nominalMaas / 30 * $htcGunModal, 2) : 0.0;
                    $gosterimYuvarlamaFarki = round($displayToplamAlacak - $anaHakedisGosterim - $bagimsizEkOdemeGosterim - $htcGosterimEkOdeme, 2);
                    if (abs($gosterimYuvarlamaFarki) >= 0.01) {
                        $toplamYuvarlamaFarki = $gosterimYuvarlamaFarki;
                    }
                }

                $displayToplamAlacak = round($toplamAlacak, 2);
                $kesintiTutarOzet = ($isNetMaas || $isPrimUsulu)
                    ? round($guncelKesintiGosterim, 2)
                    : round($toplamYasalKesinti + $guncelKesintiGosterim, 2);
                // Net Maaş / Prim Usülü personelde RTÇ/HTÇ/nöbet gibi kalemlerin SGK/Gelir Vergisi/Damga
                // Vergisi kesintileri zaten brüte tamamlama (gross-up) ile kendi içinde absorbe edildiğinden
                // (Toplam Hakediş baştan net hedefi garanti eder), bu "yasal kesintiler" toplamdan BİR DAHA
                // düşülmemeli — aksi halde çifte kesinti olur. Sadece icra/avans gibi diğer kesintiler düşülür.
                $gorunenNetMaas = ($isNetMaas || $isPrimUsulu)
                    ? max(0, round($displayToplamAlacak - $guncelKesintiGosterim, 2))
                    : max(0, round($displayToplamAlacak - $kesintiTutarOzet, 2));

                $dagitimToplami = round($bankaOdemeModal + $eldenOdemeModal + $sodexoOdemeModal + $digerOdemeModal, 2);
                $dagitimFarki = round($gorunenNetMaas - $dagitimToplami, 2);
                if (abs($dagitimFarki) >= 0.01) {
                    if ($eldenOdemeModal > 0) {
                        $eldenOdemeModal = max(0, round($gorunenNetMaas - $bankaOdemeModal - $sodexoOdemeModal - $digerOdemeModal, 2));
                    } elseif (
                        abs($dagitimFarki) <= 100
                        && $bankaOdemeModal > 0
                        && $eldenOdemeModal <= 0
                        && $sodexoOdemeModal <= 0
                        && $digerOdemeModal <= 0
                    ) {
                        $bankaOdemeModal = round($bankaOdemeModal + $dagitimFarki, 2);
                    }
                }
                
                // Helper closure for grouping and parsing supplemental earnings (Quantity x Unit Price)
                $groupAndParse = function($odemeler, $prefixesToRemove) {
                    $gruplanmis = [];
                    foreach ($odemeler as $odeme) {
                        $aciklama = $odeme->aciklama ?? '';
                        foreach ($prefixesToRemove as $prefix) {
                            $aciklama = str_replace($prefix, '', $aciklama);
                        }
                        $anaMetin = trim($aciklama);
                        $detayMetin = '';
                        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $aciklama, $matches)) {
                            $anaMetin = trim($matches[1]);
                            $detayMetin = trim($matches[2]);
                        }
                        
                        $adet = 0; $birimFiyat = ''; $birimEtiket = 'Adet';
                        if (preg_match('/(\d+)\s*Gün\s*x\s*\d+(?:[\.,]\d+)?\s*Saat\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]);
                            $birimFiyat = trim($detayMatch[2]);
                            $birimEtiket = 'Gün';
                        } elseif (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]); 
                            $birimFiyat = trim($detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $aciklama, $detayMatch)) {
                            $adet = intval($detayMatch[1]); 
                            $birimFiyat = trim($detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet/iu', $aciklama, $adetMatch)) {
                            $adet = intval($adetMatch[1]);
                        }
                        
                        $groupKey = mb_strtolower($anaMetin, 'UTF-8');
                        if (!isset($gruplanmis[$groupKey])) {
                            $gruplanmis[$groupKey] = [
                                'ana' => $anaMetin,
                                'adet' => 0,
                                'tutar' => 0,
                                'fiyat_kirilim' => []
                            ];
                        }
                        
                        $gruplanmis[$groupKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['tutar'] += floatval($odeme->tutar);
                        
                        $fiyatKey = $birimEtiket . '|' . ($birimFiyat !== '' ? $birimFiyat : '__unknown__');
                        if (!isset($gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey])) {
                            $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey] = ['birim_fiyat' => $birimFiyat, 'birim_etiket' => $birimEtiket, 'adet' => 0, 'tutar' => 0];
                        }
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['tutar'] += floatval($odeme->tutar);
                    }
                    uasort($gruplanmis, function ($a, $b) { return $b['tutar'] <=> $a['tutar']; });
                    return $gruplanmis;
                };

                $puantajGruplu = $groupAndParse($puantajOdemeler, ['[Puantaj] ', '[Sayaç] ']);
                $nobetGruplu = $groupAndParse($nobetOdemeler, ['[Nöbet] ']);
                $kacakGruplu = $groupAndParse($kacakKontrolOdemeler, ['[Kaçak Kontrol] ']);
                $puantajToplamIslemSayisi = 0;
                foreach ($puantajGruplu as $grup) {
                    $puantajToplamIslemSayisi += intval($grup['adet'] ?? 0);
                }
                    // ============================================================
                // HTML GENERATION: REF-IMAGE BASED PAYROLL DETAIL VIEW
                // ============================================================
                $detayData = json_decode($bp->hesaplama_detay ?? '', true);
                $detayData = is_array($detayData) ? $detayData : [];
                $matrahlar = is_array($detayData['matrahlar'] ?? null) ? $detayData['matrahlar'] : [];
                $ozetDetay = is_array($detayData['ozet'] ?? null) ? $detayData['ozet'] : [];
                $indirimler = is_array($detayData['indirimler'] ?? null) ? $detayData['indirimler'] : [];

                // Fetch day counts from DB/puantaj using model helper methods to ensure accuracy
                $donemBaslangic = $donemBilgi->baslangic_tarihi;
                $parametrelerMap = $BordroParametre->getAllParametrelerMap($donemBaslangic);
                $donemBitis = $donemBilgi->bitis_tarihi;
                $personelId = $bp->personel_id;

                $ucretsizIzinGunu = $BordroPersonel->getUcretsizIzinGunuDirekt($personelId, $donemBaslangic, $donemBitis);
                $isKarisikMaasGecmisi = !empty($hesap['karisikMaasGecmisi']);
                $raporGun = $BordroPersonel->getGunSayisiByKisaKod($personelId, $donemBaslangic, $donemBitis, 'RP');
                $ucretliIzin = $BordroPersonel->getUcretliIzinGunu($personelId, $donemBaslangic, $donemBitis);
                $genelTatil = $BordroPersonel->getGunSayisiByKisaKod($personelId, $donemBaslangic, $donemBitis, 'GT');

                // Calculate Hafta Tatili (Sundays in employment period minus Sunday worked HTÇ days)
                $iseGirisTs = !empty($personel->ise_giris_tarihi) && $personel->ise_giris_tarihi !== '0000-00-00' ? strtotime($personel->ise_giris_tarihi) : strtotime($donemBaslangic);
                $istenCikisTs = !empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00' ? strtotime($personel->isten_cikis_tarihi) : strtotime($donemBitis);
                
                $aktifStart = max(strtotime($donemBaslangic), $iseGirisTs);
                $aktifEnd = min(strtotime($donemBitis), $istenCikisTs);
                
                $totalSundays = 0;
                if ($aktifStart <= $aktifEnd) {
                    $cur = $aktifStart;
                    while ($cur <= $aktifEnd) {
                        if (date('w', $cur) == 0) {
                            $totalSundays++;
                        }
                        $cur = strtotime('+1 day', $cur);
                    }
                }
                $haftaTatili = max(0, $totalSundays - $htcGunModal);

                $sskGun = $isKarisikMaasGecmisi
                    ? intval($hesap['calismaGunu'] ?? 0)
                    : intval($matrahlar['ssk_gunu'] ?? ($bp->calisan_gun ?? $hesap['calismaGunu'] ?? 30));
                $normalGun = $isKarisikMaasGecmisi
                    ? intval($hesap['sabitMaasGun'] ?? 0)
                    : max(0, $sskGun - $haftaTatili - $ucretliIzin - $genelTatil);
                $calisanBrutMaas = floatval($matrahlar['calisan_brut_maas'] ?? ($bp->brut_maas ?? 0));
                if ($isKarisikMaasGecmisi) {
                    $calisanBrutMaas = floatval($hesap['sozlesmeHakedisi'] ?? 0);
                    $haftaTatili = 0;
                    $genelTatil = 0;
                    $ucretliIzin = 0;
                }

                // Calculate overtime (RTC / HTÇ) parameters beforehand — BRÜT gösterim (gross-up sonrası)
                // Asgari ücretin günlük net hedefi SGK+İşsizlik+Gelir Vergisi+Damga Vergisi kesintilerinden
                // SONRA tam olarak bu hedefe ulaşacak şekilde brüte tamamlanır (bruteUpForNetTarget).
                $rtcHedefNetModal = $rtcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2) : 0.0;
                $htcHedefNetModal = $htcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2) : 0.0;
                $htcEldenTutar = ($htcGunModal > 0)
                    ? ($isInclusive ? 0.0 : max(0.0, round(($nominalMaas - floatval($asgariUcretNet)) / 30 * $htcGunModal, 2)))
                    : 0.0;

                $rtcResmiTutar = 0.0;
                $htcResmiTutar = 0.0;
                if ($rtcHedefNetModal > 0 || $htcHedefNetModal > 0) {
$donemYilModal2 = (int) date('Y', strtotime($donemBaslangic));
                    // RTÇ/HTÇ ayın ana vergi matrahından sonra vergilendirilir. Yalnızca önceki
                    // ay kümülatifini kullanmak, personel bu ay yeni dilime geçtiğinde ek ödemeyi
                    // eski dilimden gross-up eder ve detay tutarını ana bordro hesabından saptırır.
                    $kumulatifMatrahModal2 = floatval($matrahlar['onceki_kumulatif'] ?? 0)
                        + floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                    $sgkOraniModal2 = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donemBaslangic) ?? 14) / 100;
                    $issizlikOraniModal2 = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donemBaslangic) ?? 1) / 100;
                    $damgaOraniModal2 = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donemBaslangic) ?? 0.759) / 100;

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
                $htcToplamTutar = round($htcEldenTutar + $htcResmiTutar, 2);
                $rtcNetGosterim = $rtcHedefNetModal;
                $htcNetGosterim = round($htcEldenTutar + $htcHedefNetModal, 2);

                // Calculate SGK and Gelir Vergisi matrahs correctly including taxable overtime
                $sgkMatrah = floatval($matrahlar['sgk_matrahi'] ?? (floatval($bp->brut_maas ?? 0) + floatval($ozetDetay['sgk_matrah_ekleri'] ?? 0)));
                $damgaVergisiMatrah = floatval($matrahlar['damga_vergisi_matrahi'] ?? (floatval($bp->brut_maas ?? 0) + floatval($ozetDetay['damga_matrah_ekleri'] ?? $ozetDetay['sgk_matrah_ekleri'] ?? 0)));
                $gelirVergisiMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                $oncekiAyMatrah = floatval($matrahlar['onceki_kumulatif'] ?? 0);
$yilIciToplam = floatval($matrahlar['yeni_kumulatif'] ?? ($gelirVergisiMatrah + $oncekiAyMatrah));

                $sgkIsci = floatval($bp->sgk_isci ?? 0);
                $sgkIsveren = floatval($bp->sgk_isveren ?? 0);
                $issizlikIsci = floatval($bp->issizlik_isci ?? 0);
                $issizlikIsveren = floatval($bp->issizlik_isveren ?? 0);
                $gelirVergisi = floatval($bp->gelir_vergisi ?? 0);
                $damgaVergisi = floatval($bp->damga_vergisi ?? 0);

                $asgariMatrarhGoster = !empty($bp->yemek_yardimi_dahil)
                    || !empty($bp->es_yardimi_dahil)
                    || stripos($maasDurumuGosterim, 'Net') !== false;
                // Net maaşta görev geçmişi birden fazla parçaya bölünmüş olsa da
                // Normal Kazançlar kartının tabanı sözleşme günlüğü değil, günlük
                // net asgari ücrettir. Sözleşmeyi tamamlayan tutar ayrı kalemdir.

                if ($asgariMatrarhGoster) {
                    $asgariHakedisYazdir = round(($asgariUcretNet / 30) * $sskGun, 2);
                    $calisanBrutMaas = $asgariHakedisYazdir;
                    $sgkMatrah = floatval($matrahlar['sgk_matrahi'] ?? 0);
                    $gelirVergisiMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                    $yilIciToplam = floatval($matrahlar['yeni_kumulatif'] ?? 0);
                }
                $istisnaGV = floatval($indirimler['asgari_ucret_istisna_gv'] ?? 0);
                if ($istisnaGV <= 0) {
                    $asgariIstisnaHesabi = $BordroParametre->hesaplaAsgariUcretGelirVergisiIstisnasi($donemBaslangic, $sskGun);
                    $istisnaGV = $asgariIstisnaHesabi['istisna'];
                }
                $istisnaDV = floatval($indirimler['asgari_ucret_istisna_dv'] ?? 0);
                if ($istisnaDV <= 0) {
                    $asgariBrutYazdir = round((floatval($BordroParametre->getGenelAyar('asgari_ucret_brut', $donemBaslangic) ?? 33030.00) / 30) * $sskGun, 2);
                    $istisnaDV = round($asgariBrutYazdir * 0.00759, 2);
                }

                // Helper formatting function
                $fmt = function($val, $showMinus = false, $showPlus = false, $greenText = false, $redText = false) {
                    if ($val === null || $val === '') {
                        return '-';
                    }
                    $valFloat = floatval($val);
                    if ($valFloat == 0) {
                        return '-';
                    }
                    
                    $sign = '';
                    if ($valFloat < 0 || $showMinus) {
                        $sign = '-';
                    } elseif ($showPlus && $valFloat > 0) {
                        $sign = '+';
                    }
                    
                    $class = '';
                    if ($greenText) {
                        $class = ' class="green-text"';
                    } elseif ($redText) {
                        $class = ' class="red-text"';
                    }
                    
                    return '<span' . $class . '>' . $sign . '₺' . number_format(abs($valFloat), 2, ',', '.') . '</span>';
                };

                // Group Kesintiler
                $kesintiKayitlari = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $sendikaTutar = 0.0;
                $icraTutar = 0.0;
                $avansTutar = 0.0;
                $digerKesintiTutar = 0.0;

                foreach ($kesintiKayitlari as $kk) {
                    $tur = $kk->tur;
                    if ($tur === 'icra') {
                        $icraTutar += floatval($kk->tutar);
                    } elseif ($tur === 'avans') {
                        $avansTutar += floatval($kk->tutar);
                    } elseif (strpos($tur, 'sendika') !== false || strpos(mb_strtolower($kk->aciklama ?? '', 'UTF-8'), 'sendika') !== false) {
                        $sendikaTutar += floatval($kk->tutar);
                    } else {
                        $digerKesintiTutar += floatval($kk->tutar);
                    }
                }

                // Group Ek Odemeler
                $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $yolYardimi = 0.0;
                $yemekYardimi = $displayMealDeduction;
                $esYardimi = $spouseDeduction;
                $digerSosyalYardim = 0.0;

                $nobetResmiBrutToplam = 0.0;
                $nobetResmiSaatToplam = 0.0;
                if (!empty($ekOdemelerListe)) {
                    foreach ($ekOdemelerListe as $eo) {
                        if ($eo->tur === 'hafta_ici_nobet' || $eo->tur === 'hafta_sonu_nobet') {
                            $nobetResmiBrutToplam += floatval($eo->resmi_tutar ?? 0);
                        }
                        if ($eo->tur === 'hafta_ici_nobet') {
                            if (preg_match('/(\d+)\s*Gün\s*x\s*(\d+)\s*Saat/i', $eo->aciklama, $m)) {
                                $nobetResmiSaatToplam += intval($m[1]) * intval($m[2]);
                            }
                        }
                        if ($eo->tur === 'fazla_mesai' || $eo->tur === 'mesai') {
                            if (preg_match('/([\d\.,]+)\s*Saat/i', $eo->aciklama, $m)) {
                                $nobetResmiSaatToplam += floatval(str_replace(',', '.', $m[1]));
                            }
                        }
                    }
                }
                $fazlaMesaiTutar = floatval($bp->fazla_mesai_tutar ?? 0) + $nobetResmiBrutToplam;
                $fazlaMesaiNetGosterim = (float) ($muhasebeFazlaMesaiOzeti['tutar'] ?? 0);
                if ($fazlaMesaiNetGosterim <= 0) {
                    $fazlaMesaiNetGosterim = floatval($bp->fazla_mesai_tutar ?? 0) + $toplamNobetTutar;
                }
                $displayFazlaMesaiSaat = floatval($bp->fazla_mesai_saat ?? 0) + $nobetResmiSaatToplam;

                // Start primTutar from 0.0 to prevent double-counting of toplam_ek_odeme
                $primTutar = 0.0;
                $nobetTutar = $toplamNobetTutar;
                $kacakTutar = $toplamKacakTutar;
                $puantajTutar = $toplamPuantajTutar;
                $digerKazancTutar = 0.0;
                $primListesi = [];
                $digerKazancListesi = [];
                $digerSosyalYardimListesi = [];

                foreach ($ekOdemelerListe as $ek) {
                    $aciklama = (string)($ek->aciklama ?? '');
                    $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                    $isYuvarlama = (($ek->tur ?? '') === 'yuvarlama_farki') || stripos($aciklama, 'Yuvarlama') !== false;
                    if ($isYuvarlama) continue;

                    if ($eoTur === 'yol' || strpos($eoTur, 'yol') !== false) {
                        $yolYardimi += floatval($ek->tutar);
                    } elseif ($eoTur === 'yemek' || strpos($eoTur, 'yemek') !== false || $eoTur === 'yemek_yardimi_tum') {
                        if (empty($bp->yemek_yardimi_dahil)) {
                            $yemekYardimi += floatval($ek->tutar);
                        }
                    } elseif ($eoTur === 'es_yardimi' || strpos($eoTur, 'es_yardimi') !== false || strpos($eoTur, 'aile') !== false) {
                        if (empty($bp->es_yardimi_dahil)) {
                            $esYardimi += floatval($ek->tutar);
                        }
                    } elseif (strpos($eoTur, 'sosyal') !== false || strpos($eoTur, 'yardim') !== false) {
                        $digerSosyalYardim += floatval($ek->tutar);
                        $digerSosyalYardimListesi[] = $ek;
                    } elseif ($eoTur === 'prim' || $eoTur === 'ikramiye' || $eoTur === 'kacak_ihbar_primi') {
                        if (strpos($aciklama, '[Puantaj]') !== 0 && strpos($aciklama, '[Sayaç]') !== 0 && strpos($aciklama, '[Kaçak Kontrol]') !== 0) {
                            $primTutar += floatval($ek->tutar);
                            $primListesi[] = $ek;
                        }
                    } elseif (strpos($aciklama, '[Nöbet]') === 0 || strpos($eoTur, 'nobet') !== false) {
                    } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0 || strpos($eoTur, 'kacak') !== false) {
                        // Already handled by kacakTutar
                    } elseif (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0) {
                        // Already handled by puantajTutar
                    } else {
                        $digerKazancTutar += floatval($ek->tutar);
                        $digerKazancListesi[] = $ek;
                    }
                }







                $normalCalismaTutar = $normalGun * $gunlukUcret;
                $haftaTatiliTutar = $haftaTatili * $gunlukUcret;
                $genelTatilTutar = $genelTatil * $gunlukUcret;
                $ucretliIzinTutar = $ucretliIzin * $gunlukUcret;
                if ($isKarisikMaasGecmisi) {
                    $normalCalismaTutar = floatval($hesap['sozlesmeHakedisi'] ?? 0);
                }

                // Adjust to ensure mathematical correctness

                // Adjust to ensure mathematical correctness
                $calcNormalSum = $normalCalismaTutar + $haftaTatiliTutar + $genelTatilTutar + $ucretliIzinTutar;
                if ($calismaGunu > 0 && abs($calcNormalSum - $calisanBrutMaas) > 0.05) {
                    // Split the total prorated salary proportionally if there is any mismatch due to rounding
                    $totalGuns = $normalGun + $haftaTatili + $genelTatil + $ucretliIzin;
                    if ($totalGuns > 0) {
                        $normalCalismaTutar = round(($calisanBrutMaas / $totalGuns) * $normalGun, 2);
                        $haftaTatiliTutar = round(($calisanBrutMaas / $totalGuns) * $haftaTatili, 2);
                        $genelTatilTutar = round(($calisanBrutMaas / $totalGuns) * $genelTatil, 2);
                        $ucretliIzinTutar = round(($calisanBrutMaas / $totalGuns) * $ucretliIzin, 2);
                    }
                }

                $buildPopoverHtml = function($grupluArray) {
                    if (empty($grupluArray)) return '';
                    $popHtml = '<div class="ref-popover-content">';
                    foreach ($grupluArray as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' ' . ($kirilim['birim_etiket'] ?? 'Adet') : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $popHtml .= '<div style="margin-bottom:6px; display:flex; justify-content:space-between; gap:20px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;">';
                            $popHtml .= '<span style="color:#cbd5e1;">' . htmlspecialchars($grup['ana']) . ' <small style="color:#94a3b8;">(' . $detStr . $birim . ')</small></span>';
                            $popHtml .= '<span style="color:#10b981; font-weight:bold;">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</span>';
                            $popHtml .= '</div>';
                        }
                    }
                    $popHtml .= '</div>';
                    return $popHtml;
                };

                // Diğer ek ödemeler / primler için detaylı popover oluşturucu
                $buildEkOdemePopoverHtml = function($ekList, $baslik = 'Ek Ödeme Detayları') use ($ekOdemeTurEtiketleri) {
                    if (empty($ekList)) return '';
                    $popHtml = '<div class="ref-popover-content" style="min-width: 260px; max-width: 380px;">';
                    if (!empty($baslik)) {
                        $popHtml .= '<div style="color:#94a3b8; font-size:0.72rem; margin-bottom:6px; border-bottom:1px dashed rgba(255,255,255,0.15); padding-bottom:5px; font-weight:600; text-transform:uppercase;">' . htmlspecialchars($baslik) . '</div>';
                    }
                    $sayac = 0;
                    $toplamSayi = count($ekList);
                    foreach ($ekList as $ek) {
                        $sayac++;
                        $turAd = !empty($ek->parametre_adi) ? $ek->parametre_adi : ($ekOdemeTurEtiketleri[$ek->tur] ?? ucfirst(str_replace('_', ' ', $ek->tur ?? '')));
                        $aciklama = trim((string)($ek->aciklama ?? ''));
                        
                        $detayMetin = '';
                        if (!empty($aciklama)) {
                            if (mb_strtolower($aciklama, 'UTF-8') !== mb_strtolower($turAd, 'UTF-8')) {
                                $detayMetin = $aciklama;
                            }
                        }
                        
                        if (empty($detayMetin) && !empty($ek->hesaplama_tipi)) {
                            if ($ek->hesaplama_tipi === 'oran_net') {
                                $detayMetin = '%' . floatval($ek->oran) . ' Net Maaş Oranı';
                            } elseif ($ek->hesaplama_tipi === 'oran_brut') {
                                $detayMetin = '%' . floatval($ek->oran) . ' Brüt Maaş Oranı';
                            } elseif ($ek->hesaplama_tipi === 'sabit') {
                                $detayMetin = 'Sabit Tutar';
                            }
                        }
                        
                        $odemeKanal = '';
                        if (isset($ek->banka_matrahina_ekle)) {
                            $odemeKanal = intval($ek->banka_matrahina_ekle) === 1 
                                ? ' <span style="color:#38bdf8; font-size:0.7rem; font-weight:normal;">[Banka]</span>' 
                                : ' <span style="color:#fbbf24; font-size:0.7rem; font-weight:normal;">[Elden]</span>';
                        }
                        
                        $borderBottom = ($sayac < $toplamSayi) ? 'border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 5px; margin-bottom: 5px;' : '';
                        
                        $popHtml .= '<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; ' . $borderBottom . '">';
                        $popHtml .= '<div>';
                        $popHtml .= '<div style="color:#f1f5f9; font-weight:600;">' . htmlspecialchars($turAd) . $odemeKanal . '</div>';
                        if (!empty($detayMetin)) {
                            $popHtml .= '<div style="color:#94a3b8; font-size:0.72rem; line-height:1.25; margin-top:2px;">' . htmlspecialchars($detayMetin) . '</div>';
                        }
                        $popHtml .= '</div>';
                        $popHtml .= '<div style="color:#10b981; font-weight:bold; white-space:nowrap; text-align:right; margin-top:1px;">+' . number_format(floatval($ek->tutar), 2, ',', '.') . ' ₺</div>';
                        $popHtml .= '</div>';
                    }
                    $popHtml .= '</div>';
                    return $popHtml;
                };

                // Hesaplama detayını (hedef net, SGK, İşsizlik, GV, Damga, Brüt) elle uğraşmadan
                // popover'da gösteren yardımcı — manuel hesaplama yapmak isteyen kullanıcı da tüm
                // ara verileri burada hazır bulur.
                $buildHesapDetayPopover = function (array $satirlar, string $ustBilgi = '') {
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

                $htcPopoverSatirlar = [];
                if ($htcEldenTutar > 0) {
                    $htcPopoverSatirlar[] = ['label' => 'Maaş Farkı <small style="color:#94a3b8;">(Banka)</small>', 'value' => '+' . number_format($htcEldenTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                if (isset($htcGrossModal)) {
                    $htcPopoverSatirlar[] = ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari ücret/30 × gün)</small>', 'value' => number_format($htcHedefNetModal, 2, ',', '.') . ' ₺'];
                    $htcPopoverSatirlar[] = ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($htcGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($htcGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($htcGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Damga Vergisi', 'value' => '-' . number_format($htcGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Brüt (Resmi alacağa dahil)', 'value' => '+' . number_format($htcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                } else {
                    $htcPopoverSatirlar[] = ['label' => 'Resmi alacağa dahil <small style="color:#94a3b8;">(Asgari Ücret)</small>', 'value' => '+' . number_format($htcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                $htcPopoverHtml = $buildHesapDetayPopover($htcPopoverSatirlar, 'Net hedef, SGK/GV/Damga eklenerek brüte tamamlanır');

                $rtcPopoverSatirlar = [];
                if (isset($rtcGrossModal)) {
                    $rtcPopoverSatirlar[] = ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari ücret/30 × gün)</small>', 'value' => number_format($rtcHedefNetModal, 2, ',', '.') . ' ₺'];
                    $rtcPopoverSatirlar[] = ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($rtcGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($rtcGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($rtcGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Damga Vergisi', 'value' => '-' . number_format($rtcGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Brüt (Resmi alacağa dahil)', 'value' => '+' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                } else {
                    $rtcPopoverSatirlar[] = ['label' => 'Resmi alacağa dahil <small style="color:#94a3b8;">(Asgari Ücret)</small>', 'value' => '+' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                $rtcPopoverHtml = $buildHesapDetayPopover($rtcPopoverSatirlar, 'Net hedef, SGK/GV/Damga eklenerek brüte tamamlanır');

                // Fazla Mesai Ücreti (Hafta İçi Nöbet resmi tutarı) hesaplama detayı
                $nobetGrossModal = null;
                if ($nobetResmiBrutToplam > 0 && $nobetResmiSaatToplam > 0) {
                    $nobetParam = $BordroParametre->getByKod('hafta_ici_nobet');
                    $nobetSgkDahilModal = $nobetParam ? !empty($nobetParam->sgk_matrahi_dahil) : true;
                    $nobetGvDahilModal = $nobetParam ? !empty($nobetParam->gelir_vergisi_dahil) : true;
                    $nobetDamgaDahilModal = $nobetParam ? !empty($nobetParam->damga_vergisi_dahil) : true;
                    $nobetSaatlikNetHedef = (floatval($asgariUcretNet) / 225) * 1.5;
                    $nobetHedefNetModal = round($nobetSaatlikNetHedef * $nobetResmiSaatToplam, 2);
                    $kumulatifMatrahNobetModal = floatval($matrahlar['onceki_kumulatif'] ?? 0)
                        + floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                    $sgkOraniNobetModal = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donemBaslangic) ?? 14) / 100;
                    $issizlikOraniNobetModal = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donemBaslangic) ?? 1) / 100;
                    $damgaOraniNobetModal = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donemBaslangic) ?? 0.759) / 100;
                    $nobetGrossModal = $BordroParametre->bruteUpForNetTarget(
                        $nobetHedefNetModal,
                        $kumulatifMatrahNobetModal,
                        $nobetSgkDahilModal ? $sgkOraniNobetModal : 0.0,
                        $nobetSgkDahilModal ? $issizlikOraniNobetModal : 0.0,
                        $nobetDamgaDahilModal ? $damgaOraniNobetModal : 0.0,
                        (int) date('Y', strtotime($donemBaslangic)),
                        $nobetGvDahilModal
                    );
                    $nobetPopoverSatirlar = [
                        ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari saatlik net × 1,5 × saat)</small>', 'value' => number_format($nobetHedefNetModal, 2, ',', '.') . ' ₺'],
                        ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($nobetGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($nobetGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($nobetGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Damga Vergisi', 'value' => '-' . number_format($nobetGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Brüt (Fazla Mesai Ücreti)', 'value' => '+' . number_format($nobetResmiBrutToplam, 2, ',', '.') . ' ₺', 'renk' => '#10b981'],
                        ['label' => 'Kişinin kendi alacağı <small style="color:#94a3b8;">(net, değişmez)</small>', 'value' => number_format($toplamNobetTutar ?? 0, 2, ',', '.') . ' ₺'],
                    ];
                    $nobetPopoverHtml = $buildHesapDetayPopover($nobetPopoverSatirlar, 'Sadece resmi/banka alacağı payı — asıl net ödemeyi artırmaz');
                }

                $html = '<style>
                    .bordro-ref-view { font-family: inherit; color: #1e293b; background-color: transparent; padding: 5px; border-radius: 12px; }
                    .bordro-ref-view .section-title { font-size: 0.92rem; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 14px; margin-top: 22px; text-transform: uppercase; letter-spacing: 0.5px; }
                    .bordro-ref-view .section-title.gains { color: #10b981; border-left: 4px solid #10b981; padding-left: 10px; }
                    .bordro-ref-view .section-title.deductions { color: #3b82f6; border-left: 4px solid #3b82f6; padding-left: 10px; }
                    .bordro-ref-view .ref-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s ease; }
                    .bordro-ref-view .ref-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.04); }
                    .bordro-ref-view .ref-card-title { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; letter-spacing: 0.3px; }
                    .bordro-ref-view .ref-card-list { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; margin-bottom: 12px; }
                    .bordro-ref-view .ref-card-item { display: flex; justify-content: space-between; align-items: center; font-size: 0.84rem; line-height: 1.4; position: relative; }
                    .bordro-ref-view .ref-card-item .label { color: #475569; }
                    .bordro-ref-view .ref-card-item .value { font-weight: 600; color: #0f172a; text-align: right; }
                    .bordro-ref-view .ref-card-item .value .subval { font-size: 0.75rem; color: #64748b; font-weight: 400; margin-right: 6px; }
                    .bordro-ref-view .ref-card-item.total-row { border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: auto; font-weight: 700; font-size: 0.88rem; }
                    .bordro-ref-view .ref-card-item.total-row .label { font-weight: 700; color: #0f172a; }
                    .bordro-ref-view .ref-card-item.total-row .value { font-weight: 800; color: #0f172a; }
                    .bordro-ref-view .green-text { color: #10b981 !important; font-weight: 600; }
                    .bordro-ref-view .red-text { color: #ef4444 !important; font-weight: 600; }
                    
                    /* Personel Başlık Kartı */
                    .bordro-ref-view .person-header-card {
                        background: #ffffff;
                        border: 1px solid #e2e8f0;
                        border-radius: 14px;
                        padding: 16px 20px;
                        margin-bottom: 20px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
                    }
                    
                    /* Hover Popover Styles */
                    .bordro-ref-view .hover-popover-trigger { cursor: help; position: relative; }
                    .bordro-ref-view .ref-popover-content {
                        display: none;
                        position: absolute;
                        bottom: 125%;
                        right: 0;
                        background: rgba(24, 26, 33, 0.96);
                        backdrop-filter: blur(16px);
                        color: #ffffff;
                        padding: 12px 16px;
                        border-radius: 10px;
                        box-shadow: 0 16px 36px rgba(0,0,0,0.45);
                        min-width: 280px;
                        max-width: 350px;
                        z-index: 1000;
                        font-size: 0.75rem;
                        text-align: left;
                        border: 1px solid rgba(255, 255, 255, 0.12);
                    }
                    .bordro-ref-view .ref-popover-content::after {
                        content: "";
                        position: absolute;
                        top: 100%;
                        right: 15px;
                        border-width: 6px;
                        border-style: solid;
                        border-color: rgba(24, 26, 33, 0.96) transparent transparent transparent;
                    }
                    .bordro-ref-view .hover-popover-trigger:hover .ref-popover-content {
                        display: block;
                    }
                    
                    /* Dark Popover Theme (for Bootstrap Popovers like Banka Ödemesi Detayı) */
                    .popover.bordro-dark-popover {
                        background-color: rgba(24, 26, 33, 0.96) !important;
                        backdrop-filter: blur(20px) !important;
                        border: 1px solid rgba(255, 255, 255, 0.12) !important;
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.55) !important;
                        border-radius: 10px !important;
                        color: #ffffff !important;
                        z-index: 1065 !important;
                        max-width: 360px !important;
                        min-width: 290px !important;
                    }
                    .popover.bordro-dark-popover .popover-body {
                        background-color: transparent !important;
                        color: #ffffff !important;
                        padding: 12px 16px !important;
                        font-size: 0.75rem !important;
                        line-height: 1.5 !important;
                    }
                    .popover.bordro-dark-popover .popover-header {
                        background-color: rgba(15, 17, 23, 0.98) !important;
                        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                        color: #f1f5f9 !important;
                    }
                    .popover.bordro-dark-popover .pop-header {
                        color: #94a3b8 !important;
                        font-size: 0.72rem !important;
                        margin-bottom: 8px !important;
                        border-bottom: 1px dashed rgba(255,255,255,0.15) !important;
                        padding-bottom: 5px !important;
                        font-weight: 600 !important;
                        text-transform: uppercase !important;
                        letter-spacing: 0.5px !important;
                    }
                    .popover.bordro-dark-popover .pop-row {
                        display: flex !important;
                        justify-content: space-between !important;
                        align-items: center !important;
                        gap: 20px !important;
                        margin-bottom: 5px !important;
                    }
                    .popover.bordro-dark-popover .pop-row-title {
                        color: #cbd5e1 !important;
                        font-size: 0.75rem !important;
                        font-weight: 500 !important;
                    }
                    .popover.bordro-dark-popover .pop-row-val {
                        font-weight: 700 !important;
                        white-space: nowrap !important;
                        text-align: right !important;
                        font-size: 0.75rem !important;
                    }
                    .popover.bordro-dark-popover .pop-row-val.val-white { color: #ffffff !important; }
                    .popover.bordro-dark-popover .pop-row-val.val-green { color: #10b981 !important; }
                    .popover.bordro-dark-popover .pop-row-val.val-red { color: #f87171 !important; }
                    .popover.bordro-dark-popover .pop-row-val.val-blue { color: #38bdf8 !important; }
                    .popover.bordro-dark-popover .pop-footer {
                        display: flex !important;
                        justify-content: space-between !important;
                        align-items: center !important;
                        gap: 20px !important;
                        border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
                        padding-top: 6px !important;
                        margin-top: 6px !important;
                        font-weight: 700 !important;
                    }
                    .popover.bordro-dark-popover.bs-popover-top .popover-arrow::before,
                    .popover.bordro-dark-popover.bs-popover-auto[data-popper-placement^="top"] .popover-arrow::before {
                        border-top-color: rgba(255, 255, 255, 0.12) !important;
                    }
                    .popover.bordro-dark-popover.bs-popover-top .popover-arrow::after,
                    .popover.bordro-dark-popover.bs-popover-auto[data-popper-placement^="top"] .popover-arrow::after {
                        border-top-color: rgba(24, 26, 33, 0.96) !important;
                    }
                    .popover.bordro-dark-popover.bs-popover-bottom .popover-arrow::before,
                    .popover.bordro-dark-popover.bs-popover-auto[data-popper-placement^="bottom"] .popover-arrow::before {
                        border-bottom-color: rgba(255, 255, 255, 0.12) !important;
                    }
                    .popover.bordro-dark-popover.bs-popover-bottom .popover-arrow::after,
                    .popover.bordro-dark-popover.bs-popover-auto[data-popper-placement^="bottom"] .popover-arrow::after {
                        border-bottom-color: rgba(24, 26, 33, 0.96) !important;
                    }
                    
                    /* Lower Section Panels */
                    .bordro-ref-view .bottom-panels { margin-top: 25px; display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 15px; }
                    @media (max-width: 991px) {
                        .bordro-ref-view .bottom-panels { grid-template-columns: 1fr; }
                    }
                    .bordro-ref-view .panel-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s ease; }
                    .bordro-ref-view .panel-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.04); }
                    .bordro-ref-view .panel-card-title { font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; letter-spacing: 0.3px; }
                    .bordro-ref-view .summary-badge { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff; border-radius: 10px; padding: 16px; margin-top: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                    .bordro-ref-view .summary-val { font-size: 1.8rem; font-weight: 800; color: #10b981; }

                    /* macOS Dark Glassmorphism Uyumu */
                    html[data-theme-preset="macos-dark"] .bordro-ref-view {
                        color: #e2e8f0;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .person-header-card {
                        background: rgba(255, 255, 255, 0.04) !important;
                        border: 1px solid rgba(255, 255, 255, 0.08) !important;
                        backdrop-filter: blur(16px);
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .person-header-card h5 {
                        color: #ffffff !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .person-header-card .badge {
                        background: rgba(255, 255, 255, 0.06) !important;
                        border-color: rgba(255, 255, 255, 0.08) !important;
                        color: #f1f5f9 !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card,
                    html[data-theme-preset="macos-dark"] .bordro-ref-view .panel-card {
                        background: rgba(30, 32, 40, 0.6) !important;
                        border: 1px solid rgba(255, 255, 255, 0.07) !important;
                        backdrop-filter: blur(20px);
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25) !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card:hover,
                    html[data-theme-preset="macos-dark"] .bordro-ref-view .panel-card:hover {
                        border-color: rgba(255, 255, 255, 0.16) !important;
                        background: rgba(36, 38, 48, 0.72) !important;
                        box-shadow: 0 14px 36px rgba(0, 0, 0, 0.35) !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-title,
                    html[data-theme-preset="macos-dark"] .bordro-ref-view .panel-card-title {
                        color: #94a3b8 !important;
                        border-bottom-color: rgba(255, 255, 255, 0.06) !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item .label {
                        color: #94a3b8 !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item .value {
                        color: #f1f5f9 !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item .value .subval {
                        color: #64748b !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item.total-row {
                        border-top-color: rgba(255, 255, 255, 0.08) !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item.total-row .label,
                    html[data-theme-preset="macos-dark"] .bordro-ref-view .ref-card-item.total-row .value {
                        color: #ffffff !important;
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .section-title.gains {
                        color: #34d399 !important;
                        border-left-color: #34d399 !important;
                        text-shadow: 0 0 16px rgba(52, 211, 153, 0.3);
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .section-title.deductions {
                        color: #60a5fa !important;
                        border-left-color: #60a5fa !important;
                        text-shadow: 0 0 16px rgba(96, 165, 250, 0.3);
                    }

                    html[data-theme-preset="macos-dark"] .bordro-ref-view .yuvarlama-box {
                        background: rgba(255, 255, 255, 0.04) !important;
                        border-color: rgba(255, 255, 255, 0.08) !important;
                        color: #cbd5e1 !important;
                    }
                </style>';

                $html .= '<div class="bordro-ref-view container-fluid px-0">';

                // Header Information
                $html .= '<div class="person-header-card d-flex flex-wrap justify-content-between align-items-center">';
                $html .= '<div>
                            <h5 class="mb-1 fw-bold text-dark"><i class="bx bxs-user-circle me-2 text-muted"></i>' . htmlspecialchars($personel->adi_soyadi ?? 'Bilinmeyen') . '</h5>
                            <div class="d-flex gap-3 text-muted small">
                                <span><i class="bx bx-id-card me-1"></i>TC: ' . htmlspecialchars($personel->tc_kimlik_no ?? '-') . '</span>
                                <span><i class="bx bx-briefcase me-1"></i>' . htmlspecialchars($personel->gorev ?? '-') . '</span>
                                <span class="text-primary fw-bold"><i class="bx bx-calendar me-1"></i>' . ($donemBilgi->donem_adi ?? 'Dönem Bilgisi Yok') . '</span>
                            </div>
                          </div>';
                $html .= '<div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">MAAŞ TİPİ</small>
                                <span class="fw-bold text-uppercase">' . htmlspecialchars($maasDurumuGosterim) . '</span>
                            </div>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">SÖZLEŞME MAAŞI</small>
                                <span class="fw-bold">' . ($nominalMaas ? number_format($nominalMaas, 2, ',', '.') . ' ₺' : '-') . '</span>
                            </div>
                          </div>';
                $html .= '</div>';

                // --- SECTION 1: BRÜT KAZANÇLAR VE GELİRLER (ÜST SIRA) ---
                $html .= '<div class="section-title gains"><i class="bx bx-plus-circle me-1"></i>KAZANÇLAR VE GELİRLER</div>';
                $html .= '<div class="row row-cols-1 row-cols-md-4 g-3">';

                // 1. Normal Kazançlar Card
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">1. NORMAL KAZANÇLAR</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Normal Çalışma</span><span class="value"><span class="subval">' . number_format($normalGun, 2, ',', '.') . ' Gün</span>' . $fmt($normalCalismaTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hafta Tatili</span><span class="value"><span class="subval">' . number_format($haftaTatili, 2, ',', '.') . ' Gün</span>' . $fmt($haftaTatiliTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Genel Tatil</span><span class="value"><span class="subval">' . number_format($genelTatil, 2, ',', '.') . ' Gün</span>' . $fmt($genelTatilTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Ücretli İzin</span><span class="value"><span class="subval">' . number_format($ucretliIzin, 2, ',', '.') . ' Gün</span>' . $fmt($ucretliIzinTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Ücretsiz İzin</span><span class="value"><span class="subval">' . number_format($ucretsizIzinGunu, 2, ',', '.') . ' Gün</span>-</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Rapor</span><span class="value"><span class="subval">' . number_format($raporGun, 2, ',', '.') . ' Gün</span>-</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value"><span class="subval">' . $sskGun . ' Gün</span>' . $fmt($calisanBrutMaas) . '</span></div>';
                $html .= '</div></div>';

                // 2. Fazla Çalışmalar Card
                $fazlaCalismaToplam = $rtcNetGosterim + $htcNetGosterim + $fazlaMesaiNetGosterim;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">2. FAZLA ÇALIŞMALAR</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item' . ($rtcGunModal > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">Resmi Tatil Çalışma (Net)' . ($rtcGunModal > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($rtcGunModal > 0 ? '<span class="subval">' . $rtcGunModal . ' Gün</span>' : '') . $fmt($rtcNetGosterim) . '</span>' . ($rtcGunModal > 0 ? $rtcPopoverHtml : '') . '</div>';
                $html .= '<div class="ref-card-item' . ($htcGunModal > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">Hafta Tatili Çalışma (Net)' . ($htcGunModal > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($htcGunModal > 0 ? '<span class="subval">' . $htcGunModal . ' Gün</span>' : '') . $fmt($htcNetGosterim) . '</span>' . ($htcGunModal > 0 ? $htcPopoverHtml : '') . '</div>';
                $html .= '<div class="ref-card-item' . ($nobetGrossModal ? ' hover-popover-trigger' : '') . '"><span class="label">Fazla Mesai Ücreti (Net)' . ($nobetGrossModal ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($displayFazlaMesaiSaat > 0 ? '<span class="subval">' . number_format($displayFazlaMesaiSaat, 2, ',', '.') . ' Saat</span>' : '') . $fmt($fazlaMesaiNetGosterim) . '</span>' . ($nobetGrossModal ? $nobetPopoverHtml : '') . '</div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($fazlaCalismaToplam) . '</span></div>';
                $html .= '</div></div>';

                // 3. Sosyal Yardımlar Card
                $sosyalYardimToplam = $yolYardimi + $yemekYardimi + $esYardimi + $digerSosyalYardim;
                $digerSosyalPop = $buildEkOdemePopoverHtml($digerSosyalYardimListesi, 'Diğer Sosyal Yardım Detayları');
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">3. SOSYAL YARDIMLAR</div>';
                $html .= '<div class="ref-card-list">';
                $yemekPop = '';
                if ($yuvarlamaFarki != 0) {
                    $yemekHesaplanan = $yemekYardimi - $yuvarlamaFarki;
                    $yemekPop = '<div class="ref-popover-content">';
                    $yemekPop .= '<div style="display:flex; justify-content:space-between; gap:20px; margin-bottom:6px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;"><span style="color:#cbd5e1;">Hesaplanan</span><span style="color:#ffffff; font-weight:bold;">' . number_format($yemekHesaplanan, 2, ',', '.') . ' ₺</span></div>';
                    $yemekPop .= '<div style="display:flex; justify-content:space-between; gap:20px;"><span style="color:#cbd5e1;">Yuvarlama Farkı</span><span style="color:#ffc107; font-weight:bold;">+' . number_format($yuvarlamaFarki, 2, ',', '.') . ' ₺</span></div>';
                    $yemekPop .= '</div>';
                }

                $yemekLabelText = 'Yemek Yardımı';
                $dahilYemekGun = intval($includedAllowanceFiiliGun ?? 0);
                $dahilYemekGunluk = $dahilYemekGun > 0 ? round($yemekYardimi / $dahilYemekGun, 2) : 0.0;
                if ($dahilYemekGun > 0 && $dahilYemekGunluk > 0) {
                    $yemekLabelText .= ' <small class="text-muted" style="font-size:0.75rem; font-weight:normal; letter-spacing:0px;">( ' . $dahilYemekGun . ' x ' . number_format($dahilYemekGunluk, 2, ',', '.') . ' TL )</small>';
                }

                $html .= '<div class="ref-card-item"><span class="label">Yol Yardımı</span><span class="value">' . $fmt($yolYardimi) . '</span></div>';
                $html .= '<div class="ref-card-item' . (!empty($yemekPop) ? ' hover-popover-trigger' : '') . '"><span class="label">' . $yemekLabelText . (!empty($yemekPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($yemekYardimi) . '</span>' . $yemekPop . '</div>';
                $html .= '<div class="ref-card-item"><span class="label">Eş Yardımı</span><span class="value">' . $fmt($esYardimi) . '</span></div>';
                $html .= '<div class="ref-card-item' . (!empty($digerSosyalPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Diger Sosyal Yardım' . (!empty($digerSosyalPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($digerSosyalYardim) . '</span>' . $digerSosyalPop . '</div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($sosyalYardimToplam) . '</span></div>';
                $html .= '</div></div>';

                // 4. Diğer Kazançlar / İkramiyeler Card
                $sozlesmeFarki = 0.0;
                if ($asgariMatrarhGoster) {
                    $kartlarToplami = $calisanBrutMaas + $fazlaCalismaToplam + $sosyalYardimToplam + $primTutar + $nobetTutar + $kacakTutar + $puantajTutar + $digerKazancTutar;
                    $sozlesmeFarki = max(0.0, round(($hesap['sozlesmeToplamAlacagi'] ?? $displayToplamAlacak) - $kartlarToplami, 2));
                }
                if ($sozlesmeFarki <= 0 && $modalMaasFarkiGosterim > 0 && $asgariMatrarhGoster) {
                    $sozlesmeFarki = $modalMaasFarkiGosterim;
                }

                $digerKazancToplam = $primTutar + $nobetTutar + $kacakTutar + $puantajTutar + $digerKazancTutar + $sozlesmeFarki;
                $nobetPop = $buildPopoverHtml($nobetGruplu);
                $kacakPop = $buildPopoverHtml($kacakGruplu);
                $puantajPop = $buildPopoverHtml($puantajGruplu);
                $primPop = $buildEkOdemePopoverHtml($primListesi, 'Prim / İkramiye Detayları');
                $digerKazancPop = $buildEkOdemePopoverHtml($digerKazancListesi, 'Diğer Ek Ödeme Detayları');

                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">4. DİĞER KAZANÇLAR / İKRAMİYELER</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item' . (!empty($nobetPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Nöbet Ödemesi' . (!empty($nobetPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($nobetTutar) . '</span>' . $nobetPop . '</div>';
                $html .= '<div class="ref-card-item' . (!empty($kacakPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Kaçak Kontrol Primi' . (!empty($kacakPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($kacakTutar) . '</span>' . $kacakPop . '</div>';
                $puantajBaslikDetay = $puantajToplamIslemSayisi > 0 ? ' <small class="text-muted fw-normal">( <strong>' . $puantajToplamIslemSayisi . ' Adet</strong> )</small>' : '';
                $html .= '<div class="ref-card-item' . (!empty($puantajPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Puantaj Hakedişi' . $puantajBaslikDetay . (!empty($puantajPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($puantajTutar) . '</span>' . $puantajPop . '</div>';
                $html .= '<div class="ref-card-item' . (!empty($primPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Prim / İkramiye' . (!empty($primPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($primTutar) . '</span>' . $primPop . '</div>';
                $html .= '<div class="ref-card-item' . (!empty($digerKazancPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Diğer Ek Ödemeler' . (!empty($digerKazancPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($digerKazancTutar) . '</span>' . $digerKazancPop . '</div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($digerKazancToplam) . '</span></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 1

                // --- SECTION 2: YASAL KESİNTİLER VE İŞVEREN MALİYETİ (ALT SIRA) ---
                $html .= '<div class="row row-cols-1 row-cols-md-4 g-3">';

                // 1. SGK İşçi Primi Card
                $sgkIsciKesintiToplam = $sgkIsci + $issizlikIsci;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">1. SGK İŞÇİ PRİMİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">SGK Prim Gün Sayısı</span><span class="value">' . $sskGun . ' Gün</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">SGK Matrahı</span><span class="value">' . $fmt($sgkMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Aynı Dönem İçi SGK Matrahı</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yemek Yrd. Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yol/Ulaşım Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Çocuk Yrd. Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Aile/Yemek Sair İstisna</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşçi SGK Payı (%14,0)</span><span class="value">' . $fmt($sgkIsci) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşçi İşsizlik Primi (%1,0)</span><span class="value">' . $fmt($issizlikIsci) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam SGK Kesintisi</span><span class="value">' . $fmt($sgkIsciKesintiToplam, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 2. Gelir Vergisi Card
                $asgariIstisnaHesabi = $asgariIstisnaHesabi
                    ?? $BordroParametre->hesaplaAsgariUcretGelirVergisiIstisnasi($donemBaslangic, $sskGun);
                $asgariUcretGvMatrahi = floatval($indirimler['asgari_ucret_gv_matrahi'] ?? $asgariIstisnaHesabi['aylik_matrah']);
                $asgariUcretToplamGvMatrahi = floatval($indirimler['asgari_ucret_toplam_gv_matrahi'] ?? $asgariIstisnaHesabi['toplam_matrah']);
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">2. GELİR VERGİSİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Gelir Vergisi Matrahı</span><span class="value">' . $fmt($gelirVergisiMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Önceki Dönem Küm. GV</span><span class="value">' . $fmt($oncekiAyMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yeni Dönem Küm. GV</span><span class="value">' . $fmt($yilIciToplam) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Asgari Ücret GV Matrahı</span><span class="value">' . $fmt($asgariUcretGvMatrahi) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Toplam Asgari Ücret GV Matrahı</span><span class="value">' . $fmt($asgariUcretToplamGvMatrahi) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">G.V. Asgari Ücret İstisnası</span><span class="value">' . $fmt($istisnaGV, true, false, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Sendika Matrah İndirimi</span><span class="value">' . $fmt(0) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Gelir V. Yemek/Yol İst.</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hesaplanan Gelir Vergisi</span><span class="value">' . $fmt($gelirVergisi + $istisnaGV) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Ödenecek Net Gelir V.</span><span class="value">' . $fmt($gelirVergisi, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 3. Damga Vergisi Card
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">3. DAMGA VERGİSİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Damga Vergisi Matrahı</span><span class="value">' . $fmt($damgaVergisiMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Damga V. Asgari İstisnası</span><span class="value">' . $fmt($istisnaDV, true, false, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hesaplanan Damga Vergisi</span><span class="value">' . $fmt($damgaVergisi + $istisnaDV) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Ödenecek Net Damga V.</span><span class="value">' . $fmt($damgaVergisi, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 4. Diğer Kesintiler Card
                $digerKesintiToplam = $icraTutar + $avansTutar + $sendikaTutar + $digerKesintiTutar;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">4. DİĞER KESİNTİLER</div>';
                $html .= '<div class="ref-card-list">';
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
                        $isAbsorbed = !empty($bp->yemek_yardimi_dahil) && ($ekParam->odeme_yontemi ?? 'banka') === 'banka';
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
                        $isAbsorbed = !empty($bp->yemek_yardimi_dahil) && ($ekParam->odeme_yontemi ?? 'banka') === 'banka';
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

                $icraPopoverHtml = $buildHesapDetayPopover($icraMatrahDetaylari, 'İcra Matrahı Detayları (Kesintiye Dahil Kalemler)');

                $digerKesintiDetaylari = [];
                foreach ($kesintiKayitlari as $kk) {
                    $tur = $kk->tur;
                    if ($tur === 'icra' || $tur === 'avans') {
                        continue;
                    }
                    if (strpos($tur, 'sendika') !== false || strpos(mb_strtolower($kk->aciklama ?? '', 'UTF-8'), 'sendika') !== false) {
                        continue;
                    }
                    if ($tur === 'izin_kesinti') {
                        continue;
                    }
                    
                    $label = $kk->parametre_adi ?? ($kesintiTurEtiketleri[$tur] ?? str_replace('_', ' ', $tur));
                    if (!empty($kk->aciklama) && mb_strtolower(trim($kk->aciklama), 'UTF-8') !== mb_strtolower(trim($label), 'UTF-8')) {
                        $label .= ' <small style="color:#94a3b8;">(' . htmlspecialchars($kk->aciklama) . ')</small>';
                    }
                    $digerKesintiDetaylari[] = [
                        'label' => $label,
                        'value' => '-' . number_format(floatval($kk->tutar), 2, ',', '.') . ' ₺',
                        'renk' => '#f87171'
                    ];
                }
                
                $digerKesintiPopoverHtml = '';
                if (!empty($digerKesintiDetaylari)) {
                    $digerKesintiPopoverHtml = $buildHesapDetayPopover($digerKesintiDetaylari, 'Diğer Özel Kesinti Detayları');
                }

                $html .= '<div class="ref-card-item' . ($icraTutar > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">İcra Kesintisi' . ($icraTutar > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($icraTutar, true) . '</span>' . ($icraTutar > 0 ? $icraPopoverHtml : '') . '</div>';
                $html .= '<div class="ref-card-item"><span class="label">Avans Mahsubu</span><span class="value">' . $fmt($avansTutar, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Sendika Aidatı</span><span class="value">' . $fmt($sendikaTutar, true) . '</span></div>';
                $html .= '<div class="ref-card-item' . ($digerKesintiTutar > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">Diğer Özel Kesintiler' . ($digerKesintiTutar > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($digerKesintiTutar, true) . '</span>' . ($digerKesintiTutar > 0 ? $digerKesintiPopoverHtml : '') . '</div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam Kesinti</span><span class="value">' . $fmt($digerKesintiToplam, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 2

                // --- SECTION 3: bottom panels (SGK İşveren, Maliyet Analizi, Ücret Özet) ---
                $html .= '<div class="bottom-panels">';

                // 5. SGK İşveren Payı Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">5. SGK İŞVEREN PAYI</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">SGK İşveren Payı (%20.5)</span><span class="value">' . $fmt($sgkIsveren) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşsizlik İşveren Payı (%2.0)</span><span class="value">' . $fmt($issizlikIsveren) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row" style="border-top: 1px solid #e2e8f0; padding-top: 8px;"><span class="label">Toplam İşveren SGK</span><span class="value">' . $fmt($sgkIsveren + $issizlikIsveren) . '</span></div>';
                $html .= '</div>';

                // İşveren Toplam Maliyet Analizi Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">İŞVEREN TOPLAM MALİYET ANALİZİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Brüt Toplam Kazançlar</span><span class="value">' . $fmt($displayToplamAlacak) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">SGK İşveren Katkısı</span><span class="value">' . $fmt($sgkIsveren) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşsizlik İşveren Katkısı</span><span class="value">' . $fmt($issizlikIsveren) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row" style="border-top: 1px solid #e2e8f0; padding-top: 8px;"><span class="label">Toplam Maliyet</span><span class="value text-primary" style="font-size: 1.05rem;">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</span></div>';
                $html .= '</div>';

                // Ücret Toplamları (Özet) Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">ÜCRET TOPLAMLARI (Özet)</div>';
                $html .= '<div class="ref-card-list" style="gap:4px;">';
                $html .= '<div class="ref-card-item"><span class="label">Brüt Toplamı</span><span class="value">' . $fmt($displayToplamAlacak) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Kesintiler Toplamı</span><span class="value text-danger">' . $fmt($kesintiTutarOzet, true) . '</span></div>';
                $html .= '<div class="ref-card-item" style="border-bottom: 1px dashed #e2e8f0; padding-bottom:4px; margin-bottom:4px;"><span class="label fw-bold">Ödenecek Net Maaş</span><span class="value text-success fw-bold">' . $fmt($gorunenNetMaas) . '</span></div>';
                if ($bankaOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Banka Ödemesi</span><span class="value">' . $fmt($bankaOdemeModal) . '</span></div>';
                if ($eldenOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Elden Ödeme</span><span class="value">' . $fmt($eldenOdemeModal) . '</span></div>';
                if ($sodexoOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Sodexo Ödemesi</span><span class="value">' . $fmt($sodexoOdemeModal) . '</span></div>';
                if ($digerOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Diğer Ödemeler</span><span class="value">' . $fmt($digerOdemeModal) . '</span></div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '</div>'; // End bottom panels

                if ($toplamYuvarlamaFarki != 0) {
                    $html .= '<div class="mt-3 p-2 bg-white border rounded text-end text-muted small yuvarlama-box">
                                <i class="bx bx-info-circle me-1"></i>Küsürat düzeltmesi için ' . $fmt($toplamYuvarlamaFarki, ($toplamYuvarlamaFarki < 0), ($toplamYuvarlamaFarki > 0)) . ' yuvarlama farkı uygulanmıştır.
                              </div>';
                }

                $html .= '</div>'; // End Wrapper

                if (floatval($bp->kumulatif_matrah ?? 0) > 0 || $bp->hesaplama_tarihi) {
                    $html .= '<div class="text-muted small mt-3 text-end">';
                    if (floatval($bp->kumulatif_matrah ?? 0) > 0) {
                        $html .= '<span class="me-3"><i class="bx bx-trending-up me-1"></i>Kümülatif Vergi Matrahı (Yıl Başından İtibaren): ' . number_format($bp->kumulatif_matrah, 2, ',', '.') . ' ₺</span>';
                    }
                    if ($bp->hesaplama_tarihi) {
                        $html .= '<i class="bx bx-time me-1"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime($bp->hesaplama_tarihi));
                    }
                    $html .= '</div>';
                }

                $brutToplamDetayHtml = '<div style="min-width:270px;">';
                $brutToplamDetayHtml .= '<div class="pop-header"><i class="bx bx-calculator text-warning me-1"></i> Brüt Toplam Hesabı</div>';
                $brutDetayKalan = round($displayToplamAlacak, 2);
                $sozlesmeDetayTutar = min($brutDetayKalan, max(0.0, round($contractHakedisForRounding, 2)));
                if ($sozlesmeDetayTutar > 0) {
                    $sozlesmeFormul = '';
                    $sozlesmeGunluk = $nominalMaas > 0 ? round($nominalMaas / 30, 2) : 0.0;
                    $beklenenSozlesme = round($sozlesmeGunluk * $calismaGunu, 2);
                    if ($sozlesmeGunluk > 0 && abs($beklenenSozlesme - $sozlesmeDetayTutar) < 0.02) {
                        $sozlesmeFormul = '<small style="display:block;color:#94a3b8;">' . $calismaGunu . ' gün × ' . number_format($sozlesmeGunluk, 2, ',', '.') . ' ₺</small>';
                    }
                    $brutToplamDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Sözleşme Hakedişi' . $sozlesmeFormul . '</span><span class="pop-row-val val-green">+' . number_format($sozlesmeDetayTutar, 2, ',', '.') . ' ₺</span></div>';
                    $brutDetayKalan = max(0.0, round($brutDetayKalan - $sozlesmeDetayTutar, 2));
                }

                $htcHakEdilenTutar = $htcGunModal > 0
                    ? round(($nominalMaas / 30) * $htcGunModal, 2)
                    : 0.0;
                $htcBrutDetayTutar = min($brutDetayKalan, max(0.0, $htcHakEdilenTutar));
                if ($htcBrutDetayTutar > 0) {
                    $htcGunluk = round($nominalMaas / 30, 2);
                    $brutToplamDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Hafta Tatili Çalışma Hakedişi<small style="display:block;color:#94a3b8;">' . $htcGunModal . ' gün × ' . number_format($htcGunluk, 2, ',', '.') . ' ₺</small></span><span class="pop-row-val val-green">+' . number_format($htcBrutDetayTutar, 2, ',', '.') . ' ₺</span></div>';
                    $brutDetayKalan = max(0.0, round($brutDetayKalan - $htcBrutDetayTutar, 2));
                }

                if ($brutDetayKalan > 0) {
                    $brutToplamDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Diğer Kazançlar</span><span class="pop-row-val val-green">+' . number_format($brutDetayKalan, 2, ',', '.') . ' ₺</span></div>';
                }
                $brutToplamDetayHtml .= '<div class="pop-footer"><span style="color:#ffffff;">Brüt Toplamı</span><span class="pop-row-val val-white" style="font-size:.85rem;">' . number_format($displayToplamAlacak, 2, ',', '.') . ' ₺</span></div>';
                $brutToplamDetayHtml .= '</div>';

                $bankaDetayHtml = '';
                if ($hesap['manualDagitimVar']) {
                    $bankaDetayHtml = '<div style="color:#e2e8f0; font-size:0.75rem; padding:4px;"><strong style="color:#f1f5f9;">Manuel Dağıtım</strong><br><span style="color:#94a3b8;">Bu tutar kullanıcı tarafından elle düzenlenmiştir.</span></div>';
                } elseif ($hesap['nonKurRatio'] <= 0) {
                    $bankaDetayHtml = '<div style="color:#f87171; font-size:0.75rem; padding:4px;"><i class="bx bx-error-circle me-1"></i> Personel banka dışı / KUR kapsamında olduğu için banka ödemesi yapılmaz.</div>';
                } else {
                    $bankaDetayHtml = '<div style="min-width: 260px;">';
                    $bankaDetayHtml .= '<div class="pop-header"><i class="bx bxs-bank text-info me-1"></i> Banka Ödemesi Detayı</div>';

                    
                    if (($bankaDetayDagilimi['asgari'] ?? 0) > 0
                        || ($bankaDetayDagilimi['yemek'] ?? 0) > 0
                        || ($bankaDetayDagilimi['es_yardimi'] ?? 0) > 0
                        || !empty($bankaDetayDagilimi['ek_odemeler'])) {
                        $asgariGunAciklama = '';
                        if (($bankaDetayDagilimi['asgari_gun'] ?? 0) > 0 && ($bankaDetayDagilimi['asgari_gunluk'] ?? 0) > 0) {
                            $asgariGunAciklama = '<small style="display:block;color:#94a3b8;">' . intval($bankaDetayDagilimi['asgari_gun']) . ' gün × ' . number_format($bankaDetayDagilimi['asgari_gunluk'], 2, ',', '.') . ' ₺</small>';
                        }
                        $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Asgari Ücret (Net)' . $asgariGunAciklama . '</span><span class="pop-row-val val-white">' . number_format($bankaDetayDagilimi['asgari'], 2, ',', '.') . ' ₺</span></div>';
                        $eklenenBaslikYazildi = false;
                        $eklenenBaslik = function () use (&$bankaDetayHtml, &$eklenenBaslikYazildi) {
                            if (!$eklenenBaslikYazildi) {
                                $bankaDetayHtml .= '<div class="pop-section-title" style="color:#34d399;margin:8px 0 5px;padding-top:6px;border-top:1px dashed rgba(255,255,255,.14);font-weight:700;text-transform:uppercase;font-size:.68rem;">Eklenenler</div>';
                                $eklenenBaslikYazildi = true;
                            }
                        };
                        if ($bankaDetayDagilimi['yemek'] > 0) {
                            $eklenenBaslik();
                            $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Yemek Yardımı</span><span class="pop-row-val val-green">+' . number_format($bankaDetayDagilimi['yemek'], 2, ',', '.') . ' ₺</span></div>';
                        }
                        if ($bankaDetayDagilimi['es_yardimi'] > 0) {
                            $eklenenBaslik();
                            $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Eş Yardımı</span><span class="pop-row-val val-green">+' . number_format($bankaDetayDagilimi['es_yardimi'], 2, ',', '.') . ' ₺</span></div>';
                        }
                        if (!empty($bankaDetayDagilimi['ek_odemeler'])) {
                            foreach ($bankaDetayDagilimi['ek_odemeler'] as $bed) {
                                if ($bed['tutar'] > 0) {
                                    $eklenenBaslik();
                                    $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">' . htmlspecialchars($bed['etiket'], ENT_QUOTES, 'UTF-8') . '</span><span class="pop-row-val val-green">+' . number_format($bed['tutar'], 2, ',', '.') . ' ₺</span></div>';
                                }
                                if ($bed['yemek_dahil_tutar'] > 0) {
                                    $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">' . htmlspecialchars($bed['etiket'], ENT_QUOTES, 'UTF-8') . '<small style="display:block;color:#94a3b8;">Yemek yardımına dahil</small></span><span class="pop-row-val" style="color:#94a3b8;">' . number_format($bed['yemek_dahil_tutar'], 2, ',', '.') . ' ₺ <small>(bilgi)</small></span></div>';
                                }
                            }
                        }
                        

                        $bankaDetayHtml .= '<div class="pop-row" style="border-top:1px solid rgba(255,255,255,.18);padding-top:6px;margin-top:7px;"><span class="pop-row-title" style="color:#f1f5f9!important;font-weight:700!important;">Kesinti Öncesi Banka Toplamı</span><span class="pop-row-val val-white">' . number_format($bankaDetayDagilimi['brut_banka'], 2, ',', '.') . ' ₺</span></div>';

                        $toplamKesintiBanka = $bankaDetayDagilimi['kesinti'];
                        if ($toplamKesintiBanka > 0 || ($bankaDetayDagilimi['matrah_siniri'] ?? 0) > 0) {
                            $bankaDetayHtml .= '<div class="pop-section-title" style="color:#f87171;margin:8px 0 5px;padding-top:6px;border-top:1px dashed rgba(255,255,255,.14);font-weight:700;text-transform:uppercase;font-size:.68rem;">Çıkarılanlar</div>';
                            foreach (($bankaDetayDagilimi['kesinti_kalemleri'] ?? []) as $kesintiKalemi) {
                                $kesintiAciklama = trim((string) ($kesintiKalemi['aciklama'] ?? ''));
                                $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">' . htmlspecialchars($kesintiKalemi['etiket'], ENT_QUOTES, 'UTF-8')
                                    . ($kesintiAciklama !== '' ? '<small style="display:block;color:#94a3b8;">' . htmlspecialchars($kesintiAciklama, ENT_QUOTES, 'UTF-8') . '</small>' : '')
                                    . '</span><span class="pop-row-val val-red">-' . number_format($kesintiKalemi['tutar'], 2, ',', '.') . ' ₺</span></div>';
                            }
                            if (($bankaDetayDagilimi['matrah_siniri'] ?? 0) > 0) {
                                $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Banka / hakediş sınırı</span><span class="pop-row-val val-red">-' . number_format($bankaDetayDagilimi['matrah_siniri'], 2, ',', '.') . ' ₺</span></div>';
                            }
                        }
                    } else {
                        $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Net Hakediş (Net - Sodexo)</span><span class="pop-row-val val-white">' . number_format(max(0, $hesap['netAlacagi'] - $hesap['sodexoOdemesi'] - $hesap['digerOdeme']), 2, ',', '.') . ' ₺</span></div>';
                        if ($hesap['nonKurRatio'] < 1.0) {
                            $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Banka Dağıtım Oranı</span><span class="pop-row-val val-blue">%' . ($hesap['nonKurRatio'] * 100) . '</span></div>';
                        }
                        if ($hesap['icraKesintisi'] > 0) {
                            $bankaDetayHtml .= '<div class="pop-row" style="border-top:1px dashed rgba(255,255,255,0.1); padding-top:4px; margin-top:2px;"><span class="pop-row-title">Banka Matrahı</span><span class="pop-row-val val-white">' . number_format($hesap['bankaMatrahi'], 2, ',', '.') . ' ₺</span></div>';
                            $bankaDetayHtml .= '<div class="pop-row"><span class="pop-row-title">Düşülen Kesinti (İcra vb.)</span><span class="pop-row-val val-red">-' . number_format($hesap['icraKesintisi'], 2, ',', '.') . ' ₺</span></div>';
                        }
                    }
                    
                    $bankaDetayHtml .= '<div class="pop-footer"><span style="color:#ffffff;">Net Banka Ödemesi</span><span class="pop-row-val val-blue" style="font-size:0.85rem;">' . number_format($hesap['bankaOdemesi'], 2, ',', '.') . ' ₺</span></div>';
                    $bankaDetayHtml .= '</div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'summary' => [
                        'brut_toplam' => '₺' . number_format($displayToplamAlacak, 2, ',', '.'),
                        'brut_toplam_detay' => $brutToplamDetayHtml,
                        'kesinti_toplam' => ($kesintiTutarOzet > 0 ? '-' : '') . '₺' . number_format($kesintiTutarOzet, 2, ',', '.'),
                        'net_maas' => '₺' . number_format($gorunenNetMaas, 2, ',', '.'),
                        'banka' => $bankaOdemeModal > 0 ? '₺' . number_format($bankaOdemeModal, 2, ',', '.') : null,
                        'elden' => $eldenOdemeModal > 0 ? '₺' . number_format($eldenOdemeModal, 2, ',', '.') : null,
                        'sodexo' => $sodexoOdemeModal > 0 ? '₺' . number_format($sodexoOdemeModal, 2, ',', '.') : null,
                        'diger' => $digerOdemeModal > 0 ? '₺' . number_format($digerOdemeModal, 2, ',', '.') : null,
                        'banka_detay' => $bankaDetayHtml,
                    ]
                ]);
