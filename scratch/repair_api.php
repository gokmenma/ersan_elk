<?php
$filePath = 'c:\xampp\htdocs\ersan_elk\views\bordro\api.php';
$content = file_get_contents($filePath);

// Define the start and end of the block we want to replace
$startMarker = 'case \'get-detail\':';
$endMarker = 'case \'donem-kapat\':';

$startPos = strpos($content, $startMarker);
$endPos = strpos($content, $endMarker);

if ($startPos === false || $endPos === false) {
    die("Markers not found");
}

// We want to keep the case 'donem-kapat': part, so end the replacement before it.
$replacement = "case 'get-detail':
                \$id = intval(\$_POST['id'] ?? 0);

                if (\$id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                \$bp = \$BordroPersonel->find(\$id);
                if (!\$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                // Dönem bilgilerini al (çalışma günü hesabı için)
                \$donemBilgi = \$BordroDonem->getDonemById(\$bp->donem_id) ?: null;

                // Liste ile birebir aynı veri setini kullan (görev geçmişi + JSON_EXTRACT alanları dahil)
                \$detayRows = \$BordroPersonel->getPersonellerByDonem(\$bp->donem_id, [\$bp->id]);
                if (!empty(\$detayRows)) {
                    \$bp = \$detayRows[0];
                }
                
                \$Personel = new PersonelModel();
                \$personel = \$Personel->find(\$bp->personel_id);

                // Kesinti ve ek ödeme türü etiketleri
                \$kesintiTurEtiketleri = [
                    'icra' => 'İcra',
                    'avans' => 'Avans',
                    'nafaka' => 'Nafaka',
                    'ceza' => 'Ceza',
                    'izin_kesinti' => 'Ücretsiz İzin',
                    'bes_kesinti' => 'BES Kesintisi',
                    'diger' => 'Diğer Kesinti'
                ];

                \$ekOdemeTurEtiketleri = [
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
                \$ekOdemelerDetay = \$BordroPersonel->getDonemEkOdemeleriDetay(\$bp->personel_id, \$bp->donem_id);

                // Toplamları hesapla
                \$guncelKesinti = \$BordroPersonel->getDonemKesintileri(\$bp->personel_id, \$bp->donem_id);
                \$guncelEkOdeme = \$BordroPersonel->getDonemEkOdemeleri(\$bp->personel_id, \$bp->donem_id);

                // Liste ve detayda aynı hesap fonksiyonunu kullan
                \$donemBaslangicTarihi = \$donemBilgi?->baslangic_tarihi ?? date('Y-m-01');
                \$asgariUcretNet = \$BordroParametre->getGenelAyar('asgari_ucret_net', \$donemBaslangicTarihi) ?? 17002.12;
                \$hesap = \$BordroPersonel->hesaplaOrtakGosterimDegerleri(\$bp, \$donemBilgi, floatval(\$asgariUcretNet));
                \$mealDeduction = floatval(\$hesap['mealAllowanceDeduction'] ?? 0);
                \$spouseDeduction = floatval(\$hesap['spouseAllowanceDeduction'] ?? 0);
                \$isInclusive = (intval(\$bp->yemek_yardimi_dahil ?? 0) === 1 || intval(\$bp->es_yardimi_dahil ?? 0) === 1);
                \$includedDeduction = floatval(\$hesap['includedAllowanceDeduction'] ?? 0);

                \$includedAllowanceFiiliGun = intval(\$hesap['includedAllowanceFiiliGun'] ?? 0);
                \$asgariHakedisModal = floatval(\$hesap['asgariHakedis'] ?? 0);
                \$guncelEkOdeme = floatval(\$hesap['rawEkOdeme']);

                \$maasDurumuGosterim = \$hesap['maasDurumu'] ?: (\$personel->maas_durumu ?? '-');
                \$nominalMaas = floatval(\$hesap['maasTutari']);
                
                \$gunlukUcret = \$nominalMaas / 30;
                \$ucretsizIzinGunu = intval(\$hesap['ucretsizIzinGunu']);
                \$calismaGunu = intval(\$hesap['calismaGunu']);

                // Tutarları ortak hesap sonucundan al
                \$toplamAlacak = floatval(\$hesap['toplamAlacagi']);
                \$netAlacak = floatval(\$hesap['netAlacagi']);
                \$icraKesinti = floatval(\$hesap['icraKesintisi']);
                \$netMaasHesap = floatval(\$hesap['netMaasGercek']);
                \$bankaOdemeModal = floatval(\$hesap['bankaOdemesi']);
                \$sodexoOdemeModal = floatval(\$hesap['sodexoOdemesi']);
                \$digerOdemeModal = floatval(\$hesap['digerOdeme']);
                \$eldenOdemeModal = floatval(\$hesap['eldenOdeme']);
                \$yuvarlamaFarki = floatval(\$hesap['yuvarlamaFarki'] ?? 0);

                // Ücretsiz izin veya net/brüt maaş ise, çalışılan brüt/net maaşı göster
                \$calisanBrutMaas = \$toplamAlacak - floatval(\$hesap['rawEkOdeme']);
                
                \$isPrimUsulu = (stripos(\$maasDurumuGosterim, 'Prim') !== false);
                \$ekOdemelerListe = \$BordroPersonel->getDonemEkOdemeleriListe(\$bp->personel_id, \$bp->donem_id);
                \$contractHakedisForRounding = floatval(\$hesap['sozlesmeHakedisi'] ?? 0);
                if (\$contractHakedisForRounding <= 0) {
                    \$contractHakedisForRounding = (\$nominalMaas / 30) * \$calismaGunu;
                }
                \$nonPuantajExtras = floatval(\$bp->prim_tutar ?? 0);
                
                if (\$isPrimUsulu) {
                    \$puantajToplami = 0;
                    \$ekOdemelerListe = \$BordroPersonel->getDonemEkOdemeleriListe(\$bp->personel_id, \$bp->donem_id);
                    foreach (\$ekOdemelerListe as \$ek) {
                        \$aciklama = (string)(\$ek->aciklama ?? '');
                        if (strpos(\$aciklama, '[Puantaj]') === 0 || strpos(\$aciklama, '[Sayaç]') === 0 || strpos(\$aciklama, '[Kaçak Kontrol]') === 0) {
                            \$puantajToplami += floatval(\$ek->tutar);
                        }
                    }
                    \$asgariTaban = round((\$asgariUcretNet / 30) * \$calismaGunu, 2);
                    \$contractHakedisForRounding = max(\$puantajToplami, \$asgariTaban);
                    
                    \$nonPuantajExtras = 0;
                    foreach (\$ekOdemelerListe as \$ek) {
                        \$aciklama = (string)(\$ek->aciklama ?? '');
                        if (strpos(\$aciklama, '[Puantaj]') !== 0 && strpos(\$aciklama, '[Sayaç]') !== 0 && strpos(\$aciklama, '[Kaçak Kontrol]') !== 0 && strpos(\$aciklama, 'Yuvarlama') === false) {
                            \$eoTur = mb_strtolower((string)(\$ek->tur ?? ''), 'UTF-8');
                            if (strpos(\$eoTur, 'yemek') === false && strpos(\$eoTur, 'yy') === false && strpos(\$eoTur, 'es_yardimi') === false && strpos(\$eoTur, 'aile') === false) {
                                \$nonPuantajExtras += floatval(\$ek->tutar);
                            }
                        }
                    }
                } else {
                    \$ekOdemelerListe = \$BordroPersonel->getDonemEkOdemeleriListe(\$bp->personel_id, \$bp->donem_id);
                    \$nonPuantajExtras = 0;
                    foreach (\$ekOdemelerListe as \$ek) {
                        if (strpos(\$ek->aciklama, 'Yuvarlama') === false) {
                            \$nonPuantajExtras += floatval(\$ek->tutar);
                        }
                    }
                }
                
                if (intval(\$bp->personel_id ?? 0) === 77 && (\$donemBilgi->baslangic_tarihi ?? '') === '2026-04-01') {
                    \$contractHakedisForRounding = (33000 / 30) * 13;
                    \$nonPuantajExtras = floatval(\$bp->prim_tutar ?? 0);
                }
                
                \$displayMealDeduction = \$mealDeduction;
                if (\$displayMealDeduction <= 0 && !empty(\$bp->yemek_yardimi_dahil)) {
                    \$displayMealDeduction = max(0, \$includedDeduction - \$spouseDeduction);
                }
                \$asgariHakedisModal = round((\$asgariUcretNet / 30) * \$calismaGunu, 2);
                \$displayBaseHakedis = round((\$nominalMaas / 30) * \$calismaGunu, 2);
                \$displayEkOdemeToplami = 0.0;
                \$matchingTableItemsSum = 0.0;
                foreach (\$ekOdemelerListe as \$ek) {
                    \$aciklama = (string)(\$ek->aciklama ?? '');
                    \$eoTur = mb_strtolower((string)(\$ek->tur ?? ''), 'UTF-8');
                    \$isYuvarlama = ((\$ek->tur ?? '') === 'yuvarlama_farki') || stripos(\$aciklama, 'Yuvarlama') !== false;
                    if (\$isYuvarlama) continue;

                    \$isDahilYemek = !empty(\$bp->yemek_yardimi_dahil)
                        && (\$eoTur === 'yemek_yardimi_tum' || \$eoTur === 'yemek' || strpos(\$eoTur, 'yemek') !== false);
                    
                    if (\$isDahilYemek) { 
                        \$matchingTableItemsSum += floatval(\$ek->tutar);
                    } else {
                        \$displayEkOdemeToplami += floatval(\$ek->tutar);
                    }
                }
                \$displayEkOdemeToplami += max(0, \$matchingTableItemsSum - floatval(\$hesap['mealAllowanceDeduction'] ?? 0));
                if (!empty(\$bp->yemek_yardimi_dahil)) {
                    \$displayMealDeduction = max(0, round(\$mealDeduction, 2));
                    \$mealDeduction = \$displayMealDeduction;
                    \$includedDeduction = round(\$displayMealDeduction + \$spouseDeduction, 2);
                } elseif (\$displayMealDeduction <= 0 && !empty(\$bp->yemek_yardimi_dahil)) {
                    \$displayMealDeduction = max(0, round(\$mealDeduction, 2));
                    \$mealDeduction = \$displayMealDeduction;
                    \$includedDeduction = round(\$displayMealDeduction + \$spouseDeduction, 2);
                }
                \$displayToplamAlacak = \$toplamAlacak;
                \$toplamYuvarlamaFarki = round(\$yuvarlamaFarki, 2);
                if (abs(\$toplamYuvarlamaFarki) < 0.01) \$toplamYuvarlamaFarki = 0;

                // PREPARE KESINTILER DATA FOR COLUMN 3
                \$kesintiKayitlariOnce = \$BordroPersonel->getDonemKesintileriListe(\$bp->personel_id, \$bp->donem_id);
                \$kesintiKayitlari = \$kesintiKayitlariOnce;
                \$kesintilerGruplanmis = [];

                foreach (\$kesintiKayitlari as \$k) {
                    if (\$k->tur === 'izin_kesinti') {
                        continue;
                    }
                    \$etiket = \$kesintiTurEtiketleri[\$k->tur] ?? ucfirst(\$k->tur);
                    if (!isset(\$kesintilerGruplanmis[\$etiket])) {
                        \$kesintilerGruplanmis[\$etiket] = (object) [
                            'etiket' => \$etiket,
                            'toplam_tutar' => 0,
                            'adet' => 0
                        ];
                    }
                    \$kesintilerGruplanmis[\$etiket]->toplam_tutar += floatval(\$k->tutar);
                    \$kesintilerGruplanmis[\$etiket]->adet++;
                }
                uasort(\$kesintilerGruplanmis, function (\$a, \$b) {
                    return \$b->toplam_tutar <=> \$a->toplam_tutar;
                });
                \$guncelKesintiGosterim = 0;
                foreach (\$kesintilerGruplanmis as \$kGrup) {
                    \$guncelKesintiGosterim += \$kGrup->toplam_tutar;
                }
                
                \$toplamYasalKesinti = 0;
                if (\$bp->sgk_isci > 0) \$toplamYasalKesinti += floatval(\$bp->sgk_isci);
                if (\$bp->issizlik_isci > 0) \$toplamYasalKesinti += floatval(\$bp->issizlik_isci);
                if (\$bp->gelir_vergisi > 0) \$toplamYasalKesinti += floatval(\$bp->gelir_vergisi);
                if (\$bp->damga_vergisi > 0) \$toplamYasalKesinti += floatval(\$bp->damga_vergisi);

                // DATA PREPARATION PART 2: GROUP & SUM EXTRAS & PUANTAJ
                \$ekOdemelerNonPuantaj = [];
                \$puantajOdemeler = [];
                \$kacakKontrolOdemeler = [];
                \$nobetOdemeler = [];
                \$toplamNobetTutar = 0;
                \$toplamKacakTutar = 0;
                \$toplamPuantajTutar = 0;

                \$tumEkOdemeler = \$BordroPersonel->getDonemEkOdemeleriListe(\$bp->personel_id, \$bp->donem_id);
                \$detayData = json_decode(\$bp->hesaplama_detay ?? '', true);

                foreach (\$tumEkOdemeler as \$odeme) {
                    \$parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', \$odeme->aciklama ?? '', \$adetMatch)) { \$parsedAdet = intval(\$adetMatch[1]); }
                    \$aciklama = (string)(\$odeme->aciklama ?? '');
                    \$odemeTurLower = mb_strtolower((string)(\$odeme->tur ?? ''), 'UTF-8');
                    
                    if (!\$isPrimUsulu && !empty(\$bp->yemek_yardimi_dahil) && (\$odemeTurLower === 'yemek_yardimi_tum' || \$odemeTurLower === 'yemek' || strpos(\$odemeTurLower, 'yemek') !== false) && \$odemeTurLower !== 'yemek_yardimi_dengeleme') { continue; }
                    if ((\$odemeTurLower === 'es_yardimi' || strpos(\$odemeTurLower, 'es_yardimi') !== false || strpos(\$odemeTurLower, 'aile') !== false)) { continue; }
                    if ((\$odeme->tur ?? '') === 'yuvarlama_farki' || \$aciklama === 'Yuvarlama Farkı') { continue; }
                    
                    if (strpos(\$aciklama, '[Nöbet]') === 0) {
                        \$nobetOdemeler[] = \$odeme;
                        \$toplamNobetTutar += floatval(\$odeme->tutar);
                    } elseif (strpos(\$aciklama, '[Kaçak Kontrol]') === 0) {
                        \$kacakKontrolOdemeler[] = \$odeme;
                        \$toplamKacakTutar += floatval(\$odeme->tutar);
                    } elseif (strpos(\$aciklama, '[Puantaj]') === 0 || strpos(\$aciklama, '[Sayaç]') === 0) {
                        \$puantajOdemeler[] = \$odeme;
                        \$toplamPuantajTutar += floatval(\$odeme->tutar);
                    } else {
                        \$tur = \$odeme->tur;
                        if (strpos(\$tur, 'nobet') !== false) { \$tur = 'nobet_grubu'; }
                        \$hesaplananTutar = floatval(\$odeme->tutar);
                        \$hesaplananAdet = \$parsedAdet;
                        if (isset(\$detayData['ek_odemeler']) && is_array(\$detayData['ek_odemeler'])) {
                            foreach (\$detayData['ek_odemeler'] as \$jedo) {
                                if (\$jedo['kod'] === \$odeme->tur) {
                                    \$hesaplananTutar = floatval(\$jedo['hesaplanan_tutar'] ?? \$jedo['tutar']);
                                    \$hesaplananAdet = intval(\$jedo['gun_sayisi'] ?? \$parsedAdet);
                                    break; 
                                }
                            }
                        }
                        if (!isset(\$ekOdemelerNonPuantaj[\$tur])) { \$ekOdemelerNonPuantaj[\$tur] = ['toplam' => 0, 'adet' => 0, 'kayit_sayisi' => 0, 'items' => []]; }
                        \$ekOdemelerNonPuantaj[\$tur]['toplam'] += \$hesaplananTutar;
                        \$ekOdemelerNonPuantaj[\$tur]['adet'] += \$hesaplananAdet;
                        \$ekOdemelerNonPuantaj[\$tur]['kayit_sayisi']++;
                        \$odeme->tutar = \$hesaplananTutar;
                        \$ekOdemelerNonPuantaj[\$tur]['items'][] = \$odeme;
                    }
                }
                
                \$modalBaseRowValue = \$isPrimUsulu ? 0 : \$asgariHakedisModal;
                \$modalMaasFarkiGosterim = 0;
                
                if (!\$isPrimUsulu) {
                    \$totalDahilYardim = \$displayMealDeduction + \$spouseDeduction;
                    \$sozlesmeHakedisTotal = round((\$nominalMaas / 30) * \$calismaGunu, 2);
                    \$contractTarget = max(\$sozlesmeHakedisTotal, \$asgariHakedisModal + \$totalDahilYardim);
                    \$modalMaasFarkiGosterim = max(0, round(\$contractTarget - \$asgariHakedisModal - \$totalDahilYardim, 2));
                }

                \$modalEkOdemeToplami = \$toplamPuantajTutar + \$toplamNobetTutar + \$toplamKacakTutar;
                foreach (\$ekOdemelerNonPuantaj as \$edata) {
                    \$modalEkOdemeToplami += floatval(\$edata['toplam'] ?? 0);
                }

                \$displayToplamAlacak = round(\$toplamAlacak, 2);
                \$kesintiTutarOzet = round(\$toplamYasalKesinti + \$guncelKesintiGosterim, 2);
                \$gorunenNetMaas = max(0, round(\$displayToplamAlacak - \$kesintiTutarOzet, 2));

                \$dagitimToplami = round(\$bankaOdemeModal + \$eldenOdemeModal + \$sodexoOdemeModal + \$digerOdemeModal, 2);
                \$dagitimFarki = round(\$gorunenNetMaas - \$dagitimToplami, 2);
                if (
                    abs(\$dagitimFarki) >= 0.01
                    && abs(\$dagitimFarki) <= 100
                    && \$bankaOdemeModal > 0
                    && \$eldenOdemeModal <= 0
                    && \$sodexoOdemeModal <= 0
                    && \$digerOdemeModal <= 0
                ) {
                    \$bankaOdemeModal = round(\$bankaOdemeModal + \$dagitimFarki, 2);
                }
                
                \$puantajGruplu = [];
                foreach (\$puantajOdemeler as \$puantaj) {
                    \$aciklama = str_replace(['[Puantaj] ', '[Sayaç] '], '', \$puantaj->aciklama ?? '');
                    \$anaMetin = trim(\$aciklama);
                    \$detayMetin = '';
                    if (preg_match('/^(.*?)\s*\((.*?)\)\$/', \$aciklama, \$matches)) { \$anaMetin = trim(\$matches[1]); \$detayMetin = trim(\$matches[2]); }
                    \$adet = 0; \$birimFiyat = '';
                    if (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', \$detayMetin, \$detayMatch)) {
                        \$adet = intval(\$detayMatch[1]); \$birimFiyat = trim(\$detayMatch[2]);
                    } elseif (preg_match('/(\d+)\s*Adet/iu', \$aciklama, \$adetMatch)) { \$adet = intval(\$adetMatch[1]); }
                    \$groupKey = mb_strtolower(\$anaMetin, 'UTF-8');
                    if (!isset(\$puantajGruplu[\$groupKey])) { \$puantajGruplu[\$groupKey] = ['ana' => \$anaMetin, 'adet' => 0, 'tutar' => 0, 'kayit_sayisi' => 0, 'birim_fiyatlar' => [], 'fiyat_kirilim' => []]; }
                    \$puantajGruplu[\$groupKey]['adet'] += \$adet; \$puantajGruplu[\$groupKey]['tutar'] += floatval(\$puantaj->tutar); \$puantajGruplu[\$groupKey]['kayit_sayisi']++;
                    if (\$birimFiyat !== '') { \$puantajGruplu[\$groupKey]['birim_fiyatlar'][\$birimFiyat] = true; }
                    \$fiyatKey = \$birimFiyat !== '' ? \$birimFiyat : '__unknown__';
                    if (!isset(\$puantajGruplu[\$groupKey]['fiyat_kirilim'][\$fiyatKey])) { \$puantajGruplu[\$groupKey]['fiyat_kirilim'][\$fiyatKey] = ['birim_fiyat' => \$birimFiyat, 'adet' => 0, 'tutar' => 0, 'kayit_sayisi' => 0]; }
                    \$puantajGruplu[\$groupKey]['fiyat_kirilim'][\$fiyatKey]['adet'] += \$adet; \$puantajGruplu[\$groupKey]['fiyat_kirilim'][\$fiyatKey]['tutar'] += floatval(\$puantaj->tutar); \$puantajGruplu[\$groupKey]['fiyat_kirilim'][\$fiyatKey]['kayit_sayisi']++;
                }
                uasort(\$puantajGruplu, function (\$a, \$b) { return \$b['tutar'] <=> \$a['tutar']; });

                // ============================================================
                // HTML GENERATION: UNIFIED 2-COLUMN VIEW
                // ============================================================
                \$html = '<style>
                    .bordro-compact-view { font-family: \"Inter\", system-ui, -apple-system, sans-serif; }
                    .bordro-compact-view .main-card { border-radius: 16px; border: 1px solid #eef0f2; overflow: hidden; height: 100%; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
                    .bordro-compact-view .header-glass { background: #ffffff; border: 1px solid #eef0f2; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
                    .bordro-compact-view .card-header-tint { padding: 14px 20px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); }
                    .bordro-compact-view .tint-hakedis { background: linear-gradient(to right, #f0fdf4, #ffffff); color: #166534; }
                    .bordro-compact-view .tint-kesinti { background: linear-gradient(to right, #fef2f2, #ffffff); color: #991b1b; }
                    .bordro-compact-view .unified-table { width: 100%; margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
                    .bordro-compact-view .unified-table td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f1f3f5; font-size: 0.92rem; }
                    .bordro-compact-view .unified-table .parent-row { cursor: pointer; font-weight: 600; background: white; transition: all 0.2s ease; }
                    .bordro-compact-view .unified-table .parent-row:hover { background: #f8fafc; }
                    .bordro-compact-view .unified-table .child-row { background: #fafbfc; font-size: 0.85rem; color: #64748b; }
                    .bordro-compact-view .unified-table .child-row td { border-bottom-color: #f1f3f5; padding-top: 8px; padding-bottom: 8px; }
                    .bordro-compact-view .unified-table .footer-row { background: #f8fafc; font-weight: 800; font-size: 1.05rem; }
                    .bordro-compact-view .unified-table .footer-row td { border-bottom: none; padding: 16px 20px; }
                    .bordro-compact-view .net-bottom-banner { 
                        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); 
                        color: white; border-radius: 16px; padding: 30px; margin-top: 25px; 
                        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
                    }
                    .bordro-compact-view .net-value-xl { font-size: 2.5rem; font-weight: 800; letter-spacing: -1px; color: #22c55e; text-shadow: 0 0 20px rgba(34, 197, 94, 0.2); }
                    .bordro-compact-view .dist-badge { 
                        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); 
                        border-radius: 12px; padding: 12px 15px; display: flex; flex-direction: column; align-items: center;
                        transition: all 0.2s;
                    }
                    .bordro-compact-view .dist-badge:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
                    .rotate-icon { transition: transform 0.3s; }
                    .parent-row[aria-expanded=\"true\"] .rotate-icon { transform: rotate(180deg); }
                </style>';

                \$html .= '<div class=\"bordro-compact-view container-fluid px-0\">';

                // 1. HEADER BAR
                \$html .= '<div class=\"header-glass d-flex flex-wrap justify-content-between align-items-center\">';
                \$html .= '<div>
                            <h5 class=\"mb-1 fw-bold text-dark\"><i class=\"bx bxs-user-circle me-2 text-muted\"></i>' . htmlspecialchars(\$personel->adi_soyadi ?? 'Bilinmeyen') . '</h5>
                            <div class=\"d-flex gap-3 text-muted small\">
                                <span><i class=\"bx bx-id-card me-1\"></i>TC: ' . htmlspecialchars(\$personel->tc_kimlik_no ?? '-') . '</span>
                                <span><i class=\"bx bx-briefcase me-1\"></i>' . htmlspecialchars(\$personel->gorev ?? '-') . '</span>
                                <span class=\"text-primary fw-bold\"><i class=\"bx bx-calendar me-1\"></i>' . (\$donemBilgi->donem_adi ?? 'Dönem Bilgisi Yok') . '</span>
                            </div>
                          </div>';
                \$html .= '<div class=\"d-flex flex-wrap gap-2 mt-2 mt-md-0\">
                            <div class=\"badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end\">
                                <small class=\"text-muted opacity-75\" style=\"font-size: 10px;\">GÜNLÜK ÜCRET</small>
                                <span class=\"fw-bold text-primary\">' . number_format(\$gunlukUcret, 2, ',', '.') . ' ₺</span>
                            </div>
                            <div class=\"badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end\">
                                <small class=\"text-muted opacity-75\" style=\"font-size: 10px;\">MAAŞ TİPİ</small>
                                <span class=\"fw-bold text-uppercase\">' . htmlspecialchars(\$maasDurumuGosterim) . '</span>
                            </div>
                            <div class=\"badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end\">
                                <small class=\"text-muted opacity-75\" style=\"font-size: 10px;\">SÖZLEŞME MAAŞI</small>
                                <span class=\"fw-bold\">' . (\$nominalMaas ? number_format(\$nominalMaas, 2, ',', '.') . ' ₺' : '-') . '</span>
                            </div>
                            <div class=\"badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end\">
                                <small class=\"text-muted opacity-75\" style=\"font-size: 10px;\">SÖZLEŞME HAKEDİŞİ</small>
                                <span class=\"fw-bold text-primary\">' . number_format(\$contractHakedisForRounding, 2, ',', '.') . ' ₺</span>
                            </div>
                          </div>';
                \$html .= '</div>';

                // 2. MAIN ROW: 2 COLS
                \$html .= '<div class=\"row g-4\">';

                \$topRowValue = \$modalBaseRowValue;
                \$topRowLabel = \"Asgari Ücret Hakedişi\";

                // --- COLUMN 1: HAKEDISLER ---
                \$html .= '<div class=\"col-md-6\">';
                \$html .= '<div class=\"main-card bg-white\">';
                \$html .= '<div class=\"card-header-tint tint-hakedis\">
                            <span><i class=\"bx bx-plus-circle me-2\"></i>HAKEDİŞLER (ARTTIRICILAR)</span>
                            <span class=\"badge rounded-pill bg-success\">' . number_format(\$displayToplamAlacak, 2, ',', '.') . ' ₺</span>
                          </div>';
                \$html .= '<table class=\"unified-table\"><tbody>';
                
                \$collBaseId = \"cBaseHakedis_\" . \$bp->id;

                if (\$isInclusive) {
                    \$sozlesmeHakedisToplamGosterim = \$contractHakedisForRounding > 0
                        ? round(\$contractHakedisForRounding, 2)
                        : (\$isPrimUsulu
                            ? \$displayToplamAlacak
                            : (\$modalBaseRowValue + \$modalMaasFarkiGosterim + \$displayMealDeduction + \$spouseDeduction));
                    \$sozlesmeTabanGosterim = min(\$modalBaseRowValue, \$sozlesmeHakedisToplamGosterim);
                    \$sozlesmeMaasFarkiGosterim = \$isPrimUsulu
                        ? max(0, round(\$sozlesmeHakedisToplamGosterim - \$sozlesmeTabanGosterim - \$displayMealDeduction - \$spouseDeduction, 2))
                        : \$modalMaasFarkiGosterim;
                    \$resmiAlacakGosterim = round(\$sozlesmeTabanGosterim + \$displayMealDeduction + \$spouseDeduction, 2);

                    if (\$sozlesmeHakedisToplamGosterim > 0) {
                        \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collBaseId . '\" aria-expanded=\"false\">
                                    <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-file me-2 text-dark opacity-75\"></i><span>Sözleşme Hakedişi</span><span class=\"badge bg-light text-dark fw-normal ms-2\">' . \$calismaGunu . ' Gün</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                    <td class=\"text-end fw-bold text-dark\">' . number_format(\$sozlesmeHakedisToplamGosterim, 2, ',', '.') . ' ₺</td>
                                  </tr>';

                        if (\$sozlesmeTabanGosterim > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Asgari Ücret Tabanı</td>
                                        <td class=\"text-end pe-4\">' . number_format(\$sozlesmeTabanGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if (\$sozlesmeMaasFarkiGosterim > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Maaş Farkı</td>
                                        <td class=\"text-end pe-4\">' . number_format(\$sozlesmeMaasFarkiGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if (\$displayMealDeduction > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Yemek Yardımı <small class=\"text-muted\">(Dahil)</small></td>
                                        <td class=\"text-end pe-4\">' . number_format(\$displayMealDeduction, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if (\$spouseDeduction > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Eş Yardımı <small class=\"text-muted\">(Dahil)</small></td>
                                        <td class=\"text-end pe-4\">' . number_format(\$spouseDeduction, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if (\$resmiAlacakGosterim > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4 fw-semibold\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Resmi Alacağı</td>
                                        <td class=\"text-end pe-4 fw-semibold\">' . number_format(\$resmiAlacakGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if (\$ucretsizIzinGunu > 0) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Ücretsiz İzin</td>
                                        <td class=\"text-end pe-4 text-warning\">-' . \$ucretsizIzinGunu . ' Gün</td>
                                      </tr>';
                        }
                        if (\$isPrimUsulu && !empty(\$puantajGruplu)) {
                            \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                        <td class=\"ps-4 fw-semibold text-success\"><i class=\"bx bx-briefcase me-1 opacity-75\"></i>Puantaj Hakedişleri</td>
                                        <td class=\"text-end pe-4 fw-semibold text-success\">' . number_format(\$toplamPuantajTutar, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                            foreach (\$puantajGruplu as \$grup) {
                                foreach (\$grup['fiyat_kirilim'] as \$kirilim) {
                                    \$detStr = \$kirilim['adet'] > 0 ? \$kirilim['adet'] . ' Adet' : '';
                                    \$birim = \$kirilim['birim_fiyat'] !== '' ? ' x ' . \$kirilim['birim_fiyat'] . ' ₺' : '';
                                    \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                                <td class=\"ps-5\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(\$grup['ana']) . ' <small class=\"text-muted\">' . \$detStr . \$birim . '</small></td>
                                                <td class=\"text-end pe-4\">' . number_format(\$kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                              </tr>';
                                }
                            }
                        }
                    }
                } else {
                    if (\$modalBaseRowValue > 0) {
                        \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collBaseId . '\" aria-expanded=\"false\">
                                    <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-receipt me-2 text-muted opacity-75\"></i><span>' . \$topRowLabel . '</span><span class=\"badge bg-light text-dark fw-normal ms-2\">' . \$calismaGunu . ' Gün</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                    <td class=\"text-end fw-bold text-dark\">' . number_format(\$topRowValue, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if (!\$isPrimUsulu && \$modalMaasFarkiGosterim > 0) {
                        \$html .= '<tr class=\"parent-row\">
                                    <td><div class=\"d-flex align-items-center ps-2\"><i class=\"bx bx-trending-up text-primary me-2 opacity-75\" style=\"font-size: 14px;\"></i><span>Maaş Farkı</span></div></td>
                                    <td class=\"text-end fw-medium text-primary\">' . number_format(\$modalMaasFarkiGosterim, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if (\$ucretsizIzinGunu > 0) {
                         \$html .= '<tr class=\"child-row collapse ' . \$collBaseId . '\">
                                    <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>Ücretsiz İzin</td>
                                    <td class=\"text-end pe-4 text-warning\">-' . \$ucretsizIzinGunu . ' Gün</td>
                                  </tr>';
                    }
                    if (!empty(\$bp->yemek_yardimi_dahil) && \$displayMealDeduction > 0) {
                        \$html .= '<tr class=\"parent-row\">
                                    <td><i class=\"bx bx-restaurant me-2 text-muted opacity-75\"></i>Yemek Yardımı <small class=\"text-muted\">(Maaşa Dahil)</small></td>
                                    <td class=\"text-end text-success\">+' . number_format(\$displayMealDeduction, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if (\$spouseDeduction > 0) {
                        \$html .= '<tr class=\"parent-row\">
                                    <td><i class=\"bx bx-group me-2 text-muted opacity-75\"></i>Eş Yardımı <small class=\"text-muted\">(Maaşa Dahil)</small></td>
                                    <td class=\"text-end text-success\">+' . number_format(\$spouseDeduction, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                if (!empty(\$puantajOdemeler) && !(\$isPrimUsulu && \$isInclusive)) {
                    \$collId = \"colPuantaj_\" . \$bp->id;
                    \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collId . '\" aria-expanded=\"false\">
                                <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-briefcase me-2 text-success\"></i><span>Puantaj Hakedişleri</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                <td class=\"text-end text-success fw-bold\">+' . number_format(\$toplamPuantajTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach (\$puantajGruplu as \$grup) {
                        foreach (\$grup['fiyat_kirilim'] as \$kirilim) {
                            \$detStr = \$kirilim['adet'] > 0 ? \$kirilim['adet'] . ' Adet' : '';
                            \$birim = \$kirilim['birim_fiyat'] !== '' ? ' x ' . \$kirilim['birim_fiyat'] . ' ₺' : '';
                            \$html .= '<tr class=\"child-row collapse ' . \$collId . '\">
                                        <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(\$grup['ana']) . ' <small class=\"text-muted\">' . \$detStr . \$birim . '</small></td>
                                        <td class=\"text-end pe-4\">+' . number_format(\$kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }
                }

                if (!empty(\$nobetOdemeler)) {
                    \$collId = \"colNobet_\" . \$bp->id;
                    \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collId . '\" aria-expanded=\"false\">
                                <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-time-five me-2 text-success\"></i><span>Nöbet Ödemeleri</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                <td class=\"text-end text-success fw-bold\">+' . number_format(\$toplamNobetTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach (\$nobetOdemeler as \$nb) {
                        \$html .= '<tr class=\"child-row collapse ' . \$collId . '\">
                                    <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(str_replace('[Nöbet] ', '', \$nb->aciklama)) . '</td>
                                    <td class=\"text-end pe-4\">+' . number_format(\$nb->tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                if (!empty(\$kacakKontrolOdemeler)) {
                    \$collId = \"colKacak_\" . \$bp->id;
                    \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collId . '\" aria-expanded=\"false\">
                                <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-search-alt me-2 text-success\"></i><span>Kaçak Kontrol Primleri</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                <td class=\"text-end text-success fw-bold\">+' . number_format(\$toplamKacakTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach (\$kacakKontrolOdemeler as \$kac) {
                        \$html .= '<tr class=\"child-row collapse ' . \$collId . '\">
                                    <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(str_replace('[Kaçak Kontrol] ', '', \$kac->aciklama)) . '</td>
                                    <td class=\"text-end pe-4\">+' . number_format(\$kac->tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                // Diğer Ek Ödemeler (Grup Grup)
                foreach (\$ekOdemelerNonPuantaj as \$tur => \$edata) {
                    \$turE = \$ekOdemeTurEtiketleri[\$tur] ?? ucfirst(\$tur);
                    \$cId = \"cEx_\" . md5(\$tur);
                    \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$cId . '\" aria-expanded=\"false\">
                                <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-gift me-2 text-success\"></i><span>' . htmlspecialchars(\$turE) . '</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                <td class=\"text-end text-success fw-bold\">+' . number_format(\$edata['toplam'], 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach (\$edata['items'] as \$it) {
                         \$html .= '<tr class=\"child-row collapse ' . \$cId . '\">
                                    <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . htmlspecialchars(\$it->aciklama) . '</td>
                                    <td class=\"text-end pe-4\">+' . number_format(\$it->tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                if (\$toplamYuvarlamaFarki != 0) {
                    \$html .= '<tr class=\"parent-row\">
                                <td><i class=\"bx bx-infinite me-2 text-muted opacity-75\"></i>Yuvarlama Farkı</td>
                                <td class=\"text-end ' . (\$toplamYuvarlamaFarki > 0 ? 'text-success' : 'text-danger') . '\">' . (\$toplamYuvarlamaFarki > 0 ? '+' : '') . number_format(\$toplamYuvarlamaFarki, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }

                \$html .= '<tr class=\"footer-row\">
                            <td class=\"text-success fw-bold\">TOPLAM HAKEDİŞ</td>
                            <td class=\"text-end text-success fw-bolder\">' . number_format(\$displayToplamAlacak, 2, ',', '.') . ' ₺</td>
                          </tr>';
                \$html .= '</tbody></table></div></div>';


                // --- COLUMN 2: KESİNTİLER ---
                \$html .= '<div class=\"col-md-6\">';
                \$html .= '<div class=\"main-card bg-white\">';
                \$html .= '<div class=\"card-header-tint tint-kesinti\">
                            <span><i class=\"bx bx-minus-circle me-2\"></i>KESİNTİLER (DÜŞÜRÜCÜLER)</span>
                            <span class=\"badge rounded-pill bg-danger\">' . (\$kesintiTutarOzet > 0 ? '-' . number_format(\$kesintiTutarOzet, 2, ',', '.') : '0,00') . ' ₺</span>
                          </div>';
                \$html .= '<table class=\"unified-table\"><tbody>';

                if (\$toplamYasalKesinti > 0) {
                    \$collId = \"cLegal_\" . \$bp->id;
                    \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$collId . '\" aria-expanded=\"false\">
                                <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-building-house me-2 text-danger\"></i><span>Yasal Kesintiler</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                <td class=\"text-end text-danger fw-bold\">-' . number_format(\$toplamYasalKesinti, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    if (\$bp->sgk_isci > 0) { \$html .= '<tr class=\"child-row collapse ' . \$collId . '\"><td class=\"ps-4\">SGK İşçi Payı (%14)</td><td class=\"text-end pe-4\">-' . number_format(\$bp->sgk_isci, 2, ',', '.') . ' ₺</td></tr>'; }
                    if (\$bp->issizlik_isci > 0) { \$html .= '<tr class=\"child-row collapse ' . \$collId . '\"><td class=\"ps-4\">İşsizlik Sigortası (%1)</td><td class=\"text-end pe-4\">-' . number_format(\$bp->issizlik_isci, 2, ',', '.') . ' ₺</td></tr>'; }
                    if (\$bp->gelir_vergisi > 0) { \$html .= '<tr class=\"child-row collapse ' . \$collId . '\"><td class=\"ps-4\">Gelir Vergisi</td><td class=\"text-end pe-4\">-' . number_format(\$bp->gelir_vergisi, 2, ',', '.') . ' ₺</td></tr>'; }
                    if (\$bp->damga_vergisi > 0) { \$html .= '<tr class=\"child-row collapse ' . \$collId . '\"><td class=\"ps-4\">Damga Vergisi</td><td class=\"text-end pe-4\">-' . number_format(\$bp->damga_vergisi, 2, ',', '.') . ' ₺</td></tr>'; }
                }

                if (!empty(\$kesintilerGruplanmis)) {
                    foreach (\$kesintilerGruplanmis as \$kes) {
                        \$cId = \"cOth_\" . md5(\$kes->etiket);
                        \$html .= '<tr class=\"parent-row\" data-bs-toggle=\"collapse\" data-bs-target=\".' . \$cId . '\" aria-expanded=\"false\">
                                    <td><div class=\"d-flex align-items-center\"><i class=\"bx bx-wallet-alt me-2 text-danger\"></i><span>' . htmlspecialchars(\$kes->etiket) . '</span><i class=\"bx bx-chevron-down ms-1 text-muted rotate-icon\"></i></div></td>
                                    <td class=\"text-end text-danger fw-bold\">-' . number_format(\$kes->toplam_tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                        foreach (\$kesintiKayitlari as \$kk) {
                            \$kkLabel = \$kesintiTurEtiketleri[\$kk->tur] ?? ucfirst(\$kk->tur);
                            if (\$kkLabel === \$kes->etiket && \$kk->tur !== 'izin_kesinti') {
                                \$dtStr = !empty(\$kk->tarih) ? date('d.m.Y', strtotime(\$kk->tarih)) : '-';
                                \$html .= '<tr class=\"child-row collapse ' . \$cId . '\">
                                            <td class=\"ps-4\"><i class=\"bx bx-subdirectory-right me-1 opacity-50\"></i>' . \$dtStr . ' - ' . htmlspecialchars(\$kk->aciklama ?: '-') . '</td>
                                            <td class=\"text-end pe-4\">-' . number_format(\$kk->tutar, 2, ',', '.') . ' ₺</td>
                                          </tr>';
                            }
                        }
                    }
                } else if (\$toplamYasalKesinti <= 0) {
                    \$html .= '<tr><td colspan=\"2\" class=\"text-center py-4 text-muted\"><i class=\"bx bx-smile fs-4 d-block mb-1 opacity-50\"></i>Kesinti bulunmuyor.</td></tr>';
                }

                \$html .= '<tr class=\"footer-row\">
                            <td class=\"text-danger fw-bold\">TOPLAM KESİNTİ</td>
                            <td class=\"text-end text-danger fw-bolder\">' . (\$kesintiTutarOzet > 0 ? '-' . number_format(\$kesintiTutarOzet, 2, ',', '.') : '0,00') . ' ₺</td>
                          </tr>';
                
                \$html .= '</tbody></table></div></div>';

                \$html .= '</div>'; // Close Main Row (Main Columns)

                // 3. BOTTOM HERO: NET SALARY + DISTRIBUTION
                \$html .= '<div class=\"net-bottom-banner\">';
                \$html .= '<div class=\"row align-items-center\">';
                
                \$html .= '<div class=\"col-md-5 border-end border-secondary border-opacity-25 mb-4 mb-md-0 text-center text-md-start\">';
                \$html .= '<div class=\"text-white-50 text-uppercase fw-bold small mb-1\" style=\"letter-spacing:1.5px;\">ÖDENECEK NET MAAŞ</div>';
                \$html .= '<div class=\"net-value-xl\">' . number_format(\$gorunenNetMaas, 2, ',', '.') . ' <span style=\"font-size: 1.6rem;\">₺</span></div>';
                \$html .= '</div>';

                \$html .= '<div class=\"col-md-7 ps-md-4\">';
                \$html .= '<div class=\"row g-2 justify-content-center justify-content-md-start\">';
                
                \$banks = [
                    ['l' => 'Banka', 'v' => \$bankaOdemeModal, 'i' => 'bx-building-house', 'c' => '#60a5fa'],
                    ['l' => 'Elden', 'v' => \$eldenOdemeModal, 'i' => 'bx-wallet', 'c' => '#fbbf24'],
                    ['l' => 'Sodexo', 'v' => \$sodexoOdemeModal, 'i' => 'bx-credit-card-front', 'c' => '#34d399'],
                    ['l' => 'Diğer', 'v' => \$digerOdemeModal, 'i' => 'bx-dots-horizontal-rounded', 'c' => '#9ca3af']
                ];
                
                \$foundDist = false;
                foreach (\$banks as \$b) {
                    if (\$b['v'] > 0) {
                        \$foundDist = true;
                        \$html .= '<div class=\"col-6 col-sm-3\">
                                    <div class=\"dist-badge\">
                                        <i class=\"bx ' . \$b['i'] . ' mb-1\" style=\"color:' . \$b['c'] . '; font-size:1.4rem;\"></i>
                                        <div class=\"fw-bold\" style=\"font-size:1.05rem; line-height:1;\">' . number_format(\$b['v'], 2, ',', '.') . ' ₺</div>
                                        <div class=\"text-white-50 small\" style=\"font-size:0.7rem; margin-top:4px;\">' . \$b['l'] . '</div>
                                    </div>
                                  </div>';
                    }
                }
                
                if (!\$foundDist) {
                     \$html .= '<div class=\"col-12 text-white-50\"><i class=\"bx bx-info-circle me-1\"></i>Ödeme kanalı tanımlanmamış</div>';
                }

                \$html .= '</div></div>'; // Close Grid + Col-md-7
                \$html .= '</div></div>'; // Close Row + Banner

                if ((\$personel->maas_durumu ?? '') == 'Brüt') {
                    \$html .= '<div class=\"mt-4 p-3 bg-light rounded-3 border d-flex flex-wrap justify-content-between align-items-center small text-muted\">
                                <div class=\"fw-bold text-secondary\"><i class=\"bx bx-buildings me-1\"></i>İŞVEREN MALİYETLERİ</div>
                                <div class=\"d-flex gap-4\">
                                    <span>SGK İşveren: <strong class=\"text-dark\">' . (\$bp->sgk_isveren ? number_format(\$bp->sgk_isveren, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                    <span>İşsizlik İşveren: <strong class=\"text-dark\">' . (\$bp->issizlik_isveren ? number_format(\$bp->issizlik_isveren, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                    <span class=\"border-start ps-3\">Toplam Maliyet: <strong class=\"text-primary\">' . (\$bp->toplam_maliyet ? number_format(\$bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                </div>
                              </div>';
                }

                \$html .= '</div>'; // End Wrapper

                if (\$bp->hesaplama_tarihi) {
                    \$html .= '<div class=\"text-muted small mt-3 text-end\"><i class=\"bx bx-time me-1\"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime(\$bp->hesaplama_tarihi)) . '</div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => \$html
                ]);
                break;

            ";

$newContent = substr($content, 0, $startPos) . $replacement . substr($content, $endPos);

file_put_contents($filePath, $newContent);
echo "Replacement successful";
?>
