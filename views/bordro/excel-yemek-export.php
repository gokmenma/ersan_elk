<?php
/**
 * Yemek Bedeli Listesi Excel Export
 * Muhasebeye bildirilmek üzere yemek bedellerini içeren Excel dosyası oluşturur.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$donemId = $_GET['donem_id'] ?? null;
$ids = $_GET['ids'] ?? null;
$idArray = [];
if ($ids) {
    $idArray = explode(',', $ids);
    $idArray = array_filter(array_map('intval', $idArray));
}

if (!$donemId) {
    die('Dönem ID belirtilmelidir.');
}

try {
    $BordroPersonel = new BordroPersonelModel();
    $BordroDonem = new BordroDonemModel();
    $BordroParametre = new BordroParametreModel();

    // Dönem bilgisini al
    $donem = $BordroDonem->getDonemById($donemId);
    if (!$donem) {
        die('Dönem bulunamadı.');
    }

    // Asgari ücreti çek
    $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donem->baslangic_tarihi) ?? 17002.12;

    // Dönemdeki personelleri getir
    $personeller = $BordroPersonel->getPersonellerByDonem($donemId, $idArray);

    if (empty($personeller)) {
        die('Bu dönemde kriterlere uygun personel bulunmamaktadır.');
    }

    $yemekVerileri = [];

    foreach ($personeller as $p) {
        // Listeyle aynı güncel hesap; kayıtlı eski ödeme tutarlarına geri dönülmez.
        $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($p, $donem, floatval($asgariUcretNet));
        if (!empty($p->hesaplama_tarihi)) {
            // Kayıtlı bordronun vergi, mesai ve kesinti ayrıntıları.
            $detay = !empty($p->hesaplama_detay) ? json_decode($p->hesaplama_detay, true) : [];
            $ozet = $detay['ozet'] ?? [];
            $matrahlar = $detay['matrahlar'] ?? [];
            $kayitliKesintiler = $detay['kesintiler'] ?? [];
            $kayitliEkOdemeler = $detay['ek_odemeler'] ?? [];

            $avansToplam = 0.0;
            $icra = 0.0;
            $digerKesintiler = 0.0;
            if (!empty($kayitliKesintiler) && is_array($kayitliKesintiler)) {
                foreach ($kayitliKesintiler as $k) {
                    $tur = mb_strtolower((string)($k['tur'] ?? $k['kod'] ?? ''), 'UTF-8');
                    $tutar = floatval($k['tutar'] ?? 0);
                    $hTipi = mb_strtolower((string)($k['hesaplama_tipi'] ?? ''), 'UTF-8');
                    $aciklama = mb_strtolower((string)($k['aciklama'] ?? ''), 'UTF-8');

                    if (strpos($tur, 'avans') !== false || strpos($aciklama, 'avans') !== false) {
                        $avansToplam += $tutar;
                    } elseif ($tur === 'icra' || strpos($aciklama, 'icra') !== false) {
                        $icra += $tutar;
                    } elseif ($tur === 'izin_kesinti' || strpos($tur, 'sendika') !== false || strpos($aciklama, 'sendika') !== false || strpos($tur, 'elden') !== false || $hTipi === 'elden_tutardan') {
                        continue;
                    } else {
                        $digerKesintiler += $tutar;
                    }
                }
            }
            if ($icra <= 0 && floatval($p->icra_kesintisi ?? 0) > 0) {
                $icra = floatval($p->icra_kesintisi);
            }

            $donemYil = (int) date('Y', strtotime($donem->baslangic_tarihi));
            $sgkOrani = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donem->baslangic_tarihi) ?? 14) / 100;
            $issizlikOrani = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donem->baslangic_tarihi) ?? 1) / 100;
            $damgaOrani = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donem->baslangic_tarihi) ?? 0.759) / 100;

            $aylikMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
            $yeniKumulatif = floatval($matrahlar['yeni_kumulatif'] ?? $p->kumulatif_matrah ?? 0);
            $oncekiKumulatif = floatval($matrahlar['kumulatif_matrah'] ?? ($matrahlar['onceki_kumulatif'] ?? ($yeniKumulatif - $aylikMatrah)));
            $raporGun = isset($matrahlar['rapor_gunu']) ? intval($matrahlar['rapor_gunu']) : $BordroPersonel->getGunSayisiByKisaKod($p->personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi, 'RP');
            $kayitliAsgariNet = floatval($detay['parametreler']['asgari_ucret_net'] ?? $asgariUcretNet);

            // Puantaj / Özel Çalışma Gün Sayıları (RTÇ ve HTÇ)
            $rtcGun = intval($matrahlar['rtc_gun'] ?? $BordroPersonel->getOzelCalismaGunSayisi($p->personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi, 'resmi_tatil_calismasi'));
            $htcGun = intval($matrahlar['htc_gun'] ?? $BordroPersonel->getOzelCalismaGunSayisi($p->personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi, 'hafta_tatili_calismasi'));

            $rtcBrut = 0.0;
            $rtcNet = 0.0;
            $htcBrut = 0.0;
            $htcNet = 0.0;
            $fmBrut = 0.0;
            $fmNet = 0.0;

            // RTÇ/HTÇ ayın ana vergi matrahından sonra vergilendirilir (detay modalı ile birebir aynı)
            $kumulatifMatrahGrossUp = floatval($matrahlar['onceki_kumulatif'] ?? 0)
                + floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);

            $rtcMatrahGrossUp = 0.0;
            if ($rtcGun > 0) {
                $rtcNetItem = round($kayitliAsgariNet / 30 * $rtcGun, 2);
                $rtcNet += $rtcNetItem;
                $rtcParametre = $BordroParametre->getByKod('resmi_tatil_calisma', $donem->baslangic_tarihi);
                $rtcGross = $BordroParametre->bruteUpForNetTarget(
                    $rtcNetItem,
                    $kumulatifMatrahGrossUp,
                    !$rtcParametre || !empty($rtcParametre->sgk_matrahi_dahil) ? $sgkOrani : 0.0,
                    !$rtcParametre || !empty($rtcParametre->sgk_matrahi_dahil) ? $issizlikOrani : 0.0,
                    !$rtcParametre || !empty($rtcParametre->damga_vergisi_dahil) ? $damgaOrani : 0.0,
                    $donemYil,
                    !$rtcParametre || !empty($rtcParametre->gelir_vergisi_dahil)
                );
                $rtcBrut += round(floatval($rtcGross['brut'] ?? 0), 2);
                $rtcMatrahGrossUp = floatval($rtcGross['matrah'] ?? 0);
            }

            if ($htcGun > 0) {
                $htcHedefNet = round($kayitliAsgariNet / 30 * $htcGun, 2);
                $nominalMaas = floatval($matrahlar['nominal_maas'] ?? ($p->maas_tutari ?? 0));
                $isInclusive = (intval($p->yemek_yardimi_dahil ?? 0) === 1 || intval($p->es_yardimi_dahil ?? 0) === 1);
                $htcEldenTutar = $isInclusive ? 0.0 : round(($nominalMaas - $kayitliAsgariNet) / 30 * $htcGun, 2);
                $htcNet += round($htcEldenTutar + $htcHedefNet, 2);

                $htcGross = $BordroParametre->bruteUpForNetTarget($htcHedefNet, $kumulatifMatrahGrossUp + $rtcMatrahGrossUp, $sgkOrani, $issizlikOrani, $damgaOrani, $donemYil, true);
                $htcBrut += round(floatval($htcGross['brut'] ?? 0) + $htcEldenTutar, 2);
            }

            if (!empty($kayitliEkOdemeler) && is_array($kayitliEkOdemeler)) {
                foreach ($kayitliEkOdemeler as $eo) {
                    $eoTur = mb_strtolower((string)($eo['tur'] ?? $eo['kod'] ?? ''), 'UTF-8');
                    $aciklama = (string)($eo['aciklama'] ?? '');
                    $tutar = floatval($eo['tutar'] ?? 0);
                    $resmiTutar = floatval($eo['resmi_tutar'] ?? 0);
                    $netEtki = floatval($eo['net_etki'] ?? $tutar);

                    if ($eoTur === 'resmi_tatil_calisma' || $eoTur === 'resmi_tatil') {
                        $rtcBrut += ($resmiTutar > 0 ? $resmiTutar : $tutar);
                        $rtcNet += ($netEtki > 0 ? $netEtki : $tutar);
                    } elseif ($eoTur === 'hafta_tatili_calisma' || $eoTur === 'hafta_tatili') {
                        $htcBrut += ($resmiTutar > 0 ? $resmiTutar : $tutar);
                        $htcNet += ($netEtki > 0 ? $netEtki : $tutar);
                    } elseif ($eoTur === 'hafta_ici_nobet' || $eoTur === 'mesai' || $eoTur === 'fazla_mesai' || (strpos($eoTur, 'nobet') !== false && strpos($eoTur, 'hafta_sonu') === false)) {
                        $rNet = 0.0;
                        $rBrut = $resmiTutar > 0 ? $resmiTutar : $tutar;
                        if ($eoTur === 'hafta_ici_nobet') {
                            $nobetNetHedef = $BordroPersonel->hesaplaHaftaIciNobetNetHedef($aciklama, $donem->baslangic_tarihi);
                            if ($nobetNetHedef > 0) {
                                $rNet = $nobetNetHedef;
                                if ($resmiTutar <= 0) {
                                    $grossUpNobet = $BordroParametre->bruteUpForNetTarget($nobetNetHedef, $kumulatifMatrahGrossUp, $sgkOrani, $issizlikOrani, $damgaOrani, $donemYil, true);
                                    $rBrut = round(floatval($grossUpNobet['brut'] ?? $tutar), 2);
                                }
                            }
                        }
                        if ($rNet <= 0) {
                            $rNet = ($netEtki > 0) ? $netEtki : $tutar;
                        }
                        $fmNet += $rNet;
                        $fmBrut += $rBrut;
                    }
                }
            }
            if ($fmBrut <= 0 && $fmNet <= 0 && floatval($p->fazla_mesai_tutar ?? 0) > 0) {
                $fmNet = floatval($p->fazla_mesai_tutar);
                $fmBrut = $fmNet;
            }

            $muhasebeSatiri = [
                'tc_kimlik' => $p->tc_kimlik_no ?? '-',
                'adi_soyadi' => $p->adi_soyadi ?? '-',
                'rapor_gun' => $raporGun,
                'avans' => $avansToplam,
                'rtc_brut' => $rtcBrut,
                'rtc_net' => $rtcNet,
                'htc_brut' => $htcBrut,
                'htc_net' => $htcNet,
                'fm_brut' => $fmBrut,
                'fm_net' => $fmNet,
                'gelir_vergisi' => floatval($p->gelir_vergisi ?? 0),
                'onceki_kumulatif' => $oncekiKumulatif,
                'aylik_matrah' => $aylikMatrah,
                'diger_kesintiler' => $digerKesintiler,
            ];
        } else {
            // 2. HESAPLANMAMIŞ PERSONELLER İÇİN CANLI HESAPLAMA (FALLBACK)
            
            // Avansları hesapla
            $avansToplam = 0;
            $kesintiler = $BordroPersonel->getDonemKesintileriListe($p->personel_id, $donemId);
            foreach ($kesintiler as $k) {
                $tur = mb_strtolower((string)($k->tur ?? ''), 'UTF-8');
                if (strpos($tur, 'avans') !== false) {
                    $avansToplam += floatval($k->tutar);
                }
            }
            
            // Vergi Matrahları
            $detay = !empty($p->hesaplama_detay) ? json_decode($p->hesaplama_detay, true) : [];
            $matrahlar = $detay['matrahlar'] ?? [];
            $aylikMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
            $yeniKumulatif = floatval($matrahlar['yeni_kumulatif'] ?? $p->kumulatif_matrah ?? 0);
            $oncekiKumulatif = floatval($matrahlar['kumulatif_matrah'] ?? ($yeniKumulatif - $aylikMatrah));

            $donemYil = (int) date('Y', strtotime($donem->baslangic_tarihi));
            $sgkOrani = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donem->baslangic_tarihi) ?? 14) / 100;
            $issizlikOrani = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donem->baslangic_tarihi) ?? 1) / 100;
            $damgaOrani = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donem->baslangic_tarihi) ?? 0.759) / 100;

            // Resmi Tatil Çalışması Net ve Brüt Hesabı
            $rtcGun = intval($hesap['rtcGun'] ?? 0);
            $rtcNet = 0.0;
            $rtcBrut = 0.0;
            if ($rtcGun > 0) {
                $rtcNet = round(floatval($asgariUcretNet) / 30 * $rtcGun, 2);
                $rtcParametre = $BordroParametre->getByKod('resmi_tatil_calisma', $donem->baslangic_tarihi);

                $rtcGross = $BordroParametre->bruteUpForNetTarget(
                    $rtcNet,
                    $oncekiKumulatif,
                    !$rtcParametre || !empty($rtcParametre->sgk_matrahi_dahil) ? $sgkOrani : 0.0,
                    !$rtcParametre || !empty($rtcParametre->sgk_matrahi_dahil) ? $issizlikOrani : 0.0,
                    !$rtcParametre || !empty($rtcParametre->damga_vergisi_dahil) ? $damgaOrani : 0.0,
                    $donemYil,
                    !$rtcParametre || !empty($rtcParametre->gelir_vergisi_dahil)
                );
                $rtcBrut = round(floatval($rtcGross['brut'] ?? 0), 2);
            }

            // Hafta Tatili Çalışması Net ve Brüt Hesabı
            $htcGun = intval($hesap['htcGun'] ?? 0);
            $htcNet = 0.0;
            $htcBrut = 0.0;
            if ($htcGun > 0) {
                $htcHedefNet = round(floatval($asgariUcretNet) / 30 * $htcGun, 2);
                $nominalMaas = floatval($hesap['maasTutari'] ?? 0);
                $isInclusive = (intval($p->yemek_yardimi_dahil ?? 0) === 1 || intval($p->es_yardimi_dahil ?? 0) === 1);
                $htcEldenTutar = $isInclusive ? 0.0 : round(($nominalMaas - floatval($asgariUcretNet)) / 30 * $htcGun, 2);
                $htcNet = round($htcEldenTutar + $htcHedefNet, 2);

                $htcGross = $BordroParametre->bruteUpForNetTarget($htcHedefNet, $oncekiKumulatif, $sgkOrani, $issizlikOrani, $damgaOrani, $donemYil, true);
                $htcBrut = round(floatval($htcGross['brut'] ?? 0) + $htcEldenTutar, 2);
            }

            // Fazla Mesai, Hafta Sonu Nöbet ve Prim Ek Ödemeleri
            $fmBrut = 0.0;
            $fmNet = 0.0;
            $ekOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($p->personel_id, $donemId);
            foreach ($ekOdemeler as $eo) {
                $eoTur = mb_strtolower((string)($eo->tur ?? ''), 'UTF-8');
                $aciklama = (string)($eo->aciklama ?? '');
                $rTutar = floatval($eo->resmi_tutar ?? 0);
                $tutar = floatval($eo->tutar ?? 0);

                if ($eoTur === 'resmi_tatil_calisma' || $eoTur === 'resmi_tatil' || strpos($eoTur, 'resmi_tatil') !== false) {
                    $rtcBrut += ($rTutar > 0 ? $rTutar : $tutar);
                    $rtcNet += $tutar;
                } elseif ($eoTur === 'hafta_tatili_calisma' || $eoTur === 'hafta_tatili' || $eoTur === 'hafta_sonu_nobet' || strpos($eoTur, 'hafta_sonu') !== false) {
                    $htcBrut += ($rTutar > 0 ? $rTutar : $tutar);
                    $htcNet += $tutar;
                } elseif ($eoTur === 'hafta_ici_nobet' || $eoTur === 'mesai' || $eoTur === 'fazla_mesai' || (strpos($eoTur, 'nobet') !== false && strpos($eoTur, 'hafta_sonu') === false)) {
                    $fmBrut += ($rTutar > 0 ? $rTutar : $tutar);
                    
                    $rNet = 0.0;
                    if ($rTutar > 0) {
                        if ($eoTur === 'hafta_ici_nobet') {
                            $nobetNetHedef = $BordroPersonel->hesaplaHaftaIciNobetNetHedef($aciklama, $donem->baslangic_tarihi);
                            if ($nobetNetHedef > 0) {
                                $rNet = $nobetNetHedef;
                            }
                        }
                        if ($rNet <= 0) {
                            $rSgk = $rTutar * 0.15;
                            $rGv = ($rTutar - $rSgk) * 0.15;
                            $rDv = $rTutar * 0.00759;
                            $rNet = round($rTutar - $rSgk - $rGv - $rDv, 2);
                        }
                    } else {
                        $rNet = $tutar;
                    }
                    $fmNet += $rNet;
                }
            }
            if ($fmBrut <= 0 && $fmNet <= 0 && floatval($p->fazla_mesai_tutar ?? 0) > 0) {
                $fmNet = floatval($p->fazla_mesai_tutar);
                $fmBrut = $fmNet;
            }

            $raporGun = $BordroPersonel->getGunSayisiByKisaKod($p->personel_id, $donem->baslangic_tarihi, $donem->bitis_tarihi, 'RP');

            $muhasebeSatiri = [
                'tc_kimlik' => $p->tc_kimlik_no ?? '-',
                'adi_soyadi' => $p->adi_soyadi ?? '-',
                'rapor_gun' => $raporGun,
                'avans' => $avansToplam,
                'rtc_brut' => $rtcBrut,
                'rtc_net' => $rtcNet,
                'htc_brut' => $htcBrut,
                'htc_net' => $htcNet,
                'fm_brut' => $fmBrut,
                'fm_net' => $fmNet,
                'gelir_vergisi' => floatval($p->gelir_vergisi ?? 0),
                'onceki_kumulatif' => $oncekiKumulatif,
                'aylik_matrah' => $aylikMatrah,
                'diger_kesintiler' => (function() use ($BordroPersonel, $p, $donemId) {
                    $digerKesintiToplam = 0;
                    $kesintiKayitlari = $BordroPersonel->getDonemKesintileriListe($p->personel_id, $donemId);
                    foreach ($kesintiKayitlari as $kk) {
                        $tur = mb_strtolower((string)($kk->tur ?? ''), 'UTF-8');
                        $hTipi = mb_strtolower((string)($kk->hesaplama_tipi ?? ''), 'UTF-8');
                        if ($tur === 'icra' || strpos($tur, 'avans') !== false || $tur === 'izin_kesinti') {
                            continue;
                        }
                        if (strpos($tur, 'sendika') !== false || strpos(mb_strtolower($kk->aciklama ?? '', 'UTF-8'), 'sendika') !== false) {
                            continue;
                        }
                        if (strpos($tur, 'elden') !== false || $hTipi === 'elden_tutardan') {
                            continue;
                        }
                        $digerKesintiToplam += floatval($kk->tutar);
                    }
                    return $digerKesintiToplam;
                })(),
            ];
        }
        $yemekVerileri[] = array_merge(
            $muhasebeSatiri,
            $BordroPersonel->getMuhasebeOdemeOzeti($hesap)
        );
    }

    if (empty($yemekVerileri)) {
        die('Bu dönemde veri bulunan personel bulunmamaktadır.');
    }

    // İşlem için log kaydı oluşturulması
    try {
        $SystemLog = new \App\Model\SystemLogModel();
        $SystemLog->logAction(
            $_SESSION['user_id'] ?? 0,
            'Muhasebe Listesi Excel İndirme',
            $donem->donem_adi . ' dönemi için muhasebe listesi Excel dosyası indirildi. Kriter sayısı: ' . count($yemekVerileri),
            \App\Model\SystemLogModel::LEVEL_IMPORTANT
        );
    } catch (Exception $logEx) {
        error_log("Muhasebe Listesi Excel Loglama Hatası: " . $logEx->getMessage());
    }

    // Yeni Excel dosyası oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Muhasebe Listesi');

    // Başlıklar
    $basliklar = [
        'A' => 'ADI SOYADI',
        'B' => 'RAPOR (GÜN)',
        'C' => 'TOPLAM GÜN',
        'D' => 'YEMEK',
        'E' => 'EŞ YARDIMI',
        'F' => 'AVANS',
        'G' => 'İCRA',
        'H' => 'RESMİ TATİL ÇALIŞMASI (NET)',
        'I' => 'H.T. ÇALIŞMASI (NET)',
        'J' => 'PRİM / İKRAMİYE',
        'K' => 'FAZLA MESAİ (NET)',
        'L' => 'ÖDENECEK NET MAAŞ',
        'M' => 'DİĞER KESİNTİLER',
        'N' => 'TC KİMLİK NO',
        'O' => 'FİİLİ GÜN',
        'P' => 'GÜNLÜK YEMEK (NAKİT)',
        'Q' => 'SODEXO / KART',
        'R' => 'ALACAK (ASGARİ ÜCRET)',
        'S' => 'RESMİ TATİL ÇALIŞMASI (BRÜT)',
        'T' => 'HAFTA TATİLİ ÇALIŞMASI (BRÜT)',
        'U' => 'FAZLA MESAİ (BRÜT)',
        'V' => 'ALACAK TOPLAMI',
        'W' => 'GELİR VERGİSİ KESİNTİSİ',
        'X' => 'KÜMÜLATİF VERGİ MATRAHI (BU AY HARİÇ)',
        'Y' => 'AYLIK VERGİ MATRAHI (BU AY)'
    ];

    // Başlık stili
    $baslikStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4B5563'] // Koyu gri/lacivert tonu
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];

    // Başlıkları yaz
    foreach ($basliklar as $kolon => $baslik) {
        $sheet->setCellValue($kolon . '1', $baslik);
        $sheet->getColumnDimension($kolon)->setAutoSize(true);
    }

    // Başlık satırına stil uygula
    $sheet->getStyle('A1:Y1')->applyFromArray($baslikStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Veri stili
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];

    // Verileri ekle
    $satir = 2;
    foreach ($yemekVerileri as $veri) {
        $sheet->setCellValue('A' . $satir, $veri['adi_soyadi']);
        $sheet->setCellValue('B' . $satir, $veri['rapor_gun']);
        $sheet->setCellValue('C' . $satir, $veri['toplam_gun']);
        $sheet->setCellValue('D' . $satir, $veri['nakit_yemek']);
        $sheet->setCellValue('E' . $satir, $veri['es_yardimi']);
        $sheet->setCellValue('F' . $satir, $veri['avans']);
        $sheet->setCellValue('G' . $satir, $veri['icra']);
        $sheet->setCellValue('H' . $satir, $veri['rtc_net']);
        $sheet->setCellValue('I' . $satir, $veri['htc_net']);
        $sheet->setCellValue('J' . $satir, $veri['prim']);
        $sheet->setCellValue('K' . $satir, $veri['fm_net']);
        $sheet->setCellValue('L' . $satir, $veri['net_maas']);
        $sheet->setCellValue('M' . $satir, $veri['diger_kesintiler']);
        $sheet->setCellValueExplicit('N' . $satir, $veri['tc_kimlik'], DataType::TYPE_STRING);
        $sheet->setCellValue('O' . $satir, $veri['fiili_gun']);
        $sheet->setCellValue('P' . $satir, $veri['gunluk_nakit']);
        $sheet->setCellValue('Q' . $satir, $veri['sodexo_yemek']);
        $sheet->setCellValue('R' . $satir, $veri['resmi_alacak_asgari']);
        $sheet->setCellValue('S' . $satir, $veri['rtc_brut']);
        $sheet->setCellValue('T' . $satir, $veri['htc_brut']);
        $sheet->setCellValue('U' . $satir, $veri['fm_brut']);
        $sheet->setCellValue('V' . $satir, $veri['resmi_alacak_toplam']);
        $sheet->setCellValue('W' . $satir, $veri['gelir_vergisi']);
        $sheet->setCellValue('X' . $satir, $veri['onceki_kumulatif']);
        $sheet->setCellValue('Y' . $satir, $veri['aylik_matrah']);
 
        // Formatlar
        $sheet->getStyle('B' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('E' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('F' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('G' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('H' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('I' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('J' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('K' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('L' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('M' . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        $sheet->getStyle('N' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'] as $paraKolonu) {
            $sheet->getStyle($paraKolonu . $satir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        }
        $sheet->getStyle("A{$satir}:Y{$satir}")->applyFromArray($dataStyle);
        
        $satir++;
    }

    // Toplam satırı ekle
    $toplamSatir = $satir;
    $sheet->setCellValue('A' . $toplamSatir, 'TOPLAM');
    $sheet->setCellValue('B' . $toplamSatir, '=SUM(B2:B' . ($satir - 1) . ')');
    $sheet->setCellValue('C' . $toplamSatir, '=SUM(C2:C' . ($satir - 1) . ')');
    $sheet->setCellValue('D' . $toplamSatir, '=SUM(D2:D' . ($satir - 1) . ')');
    $sheet->setCellValue('E' . $toplamSatir, '=SUM(E2:E' . ($satir - 1) . ')');
    $sheet->setCellValue('F' . $toplamSatir, '=SUM(F2:F' . ($satir - 1) . ')');
    $sheet->setCellValue('G' . $toplamSatir, '=SUM(G2:G' . ($satir - 1) . ')');
    $sheet->setCellValue('H' . $toplamSatir, '=SUM(H2:H' . ($satir - 1) . ')');
    $sheet->setCellValue('I' . $toplamSatir, '=SUM(I2:I' . ($satir - 1) . ')');
    $sheet->setCellValue('J' . $toplamSatir, '=SUM(J2:J' . ($satir - 1) . ')');
    $sheet->setCellValue('K' . $toplamSatir, '=SUM(K2:K' . ($satir - 1) . ')');
    $sheet->setCellValue('L' . $toplamSatir, '=SUM(L2:L' . ($satir - 1) . ')');
    $sheet->setCellValue('M' . $toplamSatir, '=SUM(M2:M' . ($satir - 1) . ')');
    $sheet->setCellValue('O' . $toplamSatir, '=SUM(O2:O' . ($satir - 1) . ')');
    foreach (['Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'] as $toplamKolonu) {
        $sheet->setCellValue($toplamKolonu . $toplamSatir, '=SUM(' . $toplamKolonu . '2:' . $toplamKolonu . ($satir - 1) . ')');
    }
    
    $sheet->getStyle('A' . $toplamSatir . ':Y' . $toplamSatir)->getFont()->setBold(true);
    
    $currencyCols = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];
    foreach ($currencyCols as $col) {
        $sheet->getStyle($col . $toplamSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    }
    
    $sheet->getStyle('A' . $toplamSatir . ':Y' . $toplamSatir)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F3F4F6']
        ],
        'borders' => [
            'top' => ['borderStyle' => Border::BORDER_MEDIUM]
        ]
    ]);

    // Muhasebecinin işlem sırasını izleyen ödeme mutabakat sayfası.
    $mutabakatSheet = $spreadsheet->createSheet();
    $mutabakatSheet->setTitle('Ödeme Mutabakatı');
    $mutabakatSheet->mergeCells('A1:B1');
    $mutabakatSheet->mergeCells('C1:K1');
    $mutabakatSheet->mergeCells('L1:Q1');
    $mutabakatSheet->mergeCells('S1:AD1');
    $mutabakatSheet->setCellValue('A1', 'PERSONEL');
    $mutabakatSheet->setCellValue('C1', '1 - EKLENECEK RESMÎ KALEMLER');
    $mutabakatSheet->setCellValue('L1', '2 - ÇIKARILACAK KESİNTİLER (L-N DETAY, O-P UYGULANACAK)');
    $mutabakatSheet->setCellValue('R1', '3 - BANKAYA YATACAK');
    $mutabakatSheet->setCellValue('S1', '4 - BİLGİ VE KONTROL');

    $mutabakatGruplari = [
        ['aralik' => 'A1:B1', 'renk' => '475569'],
        ['aralik' => 'C1:K1', 'renk' => '166534'],
        ['aralik' => 'L1:Q1', 'renk' => '991B1B'],
        ['aralik' => 'R1:R1', 'renk' => '92400E'],
        ['aralik' => 'S1:AD1', 'renk' => '1E40AF'],
    ];
    foreach ($mutabakatGruplari as $grup) {
        $mutabakatSheet->getStyle($grup['aralik'])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $grup['renk']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }
    $mutabakatSheet->getRowDimension(1)->setRowHeight(24);

    $mutabakatBasliklari = [
        'A' => 'ADI SOYADI',
        'B' => 'TC KİMLİK NO',
        'C' => 'ASGARİ / NORMAL ÜCRET',
        'D' => 'YEMEK YARDIMI',
        'E' => 'EŞ YARDIMI',
        'F' => 'RESMÎ TATİL ÇALIŞMASI (NET)',
        'G' => 'H.T. ÇALIŞMASI (NET)',
        'H' => 'FAZLA MESAİ (NET)',
        'I' => 'PRİM / İKRAMİYE (RESMÎ)',
        'J' => 'DİĞER RESMÎ EKLENECEK',
        'K' => 'RESMÎ BANKA MATRAHI (TOPLAM)',
        'L' => 'AVANS (KESİNTİ DETAYI)',
        'M' => 'İCRA (KESİNTİ DETAYI)',
        'N' => 'DİĞER KESİNTİLER (DETAY)',
        'O' => 'BANKADAN DÜŞÜLECEK (UYGULANACAK)',
        'P' => 'ELDEN DÜŞÜLECEK (UYGULANACAK)',
        'Q' => 'TOPLAM PERSONEL KESİNTİSİ (KONTROL)',
        'R' => 'BANKAYA YATACAK TUTAR',
        'S' => 'ELDEN ÖDEME',
        'T' => 'SODEXO / KART',
        'U' => 'DİĞER ÖDEME',
        'V' => 'ÖDENECEK NET TOPLAM',
        'W' => 'TOPLAM HAKEDİŞ',
        'X' => 'PRİM / İKRAMİYE HAKEDİŞİ (BİLGİ)',
        'Y' => 'YEMEK / BANKA DAĞILIMINA DAHİL PRİM (BİLGİ)',
        'Z' => 'PRİMİN AYRI ÖDEMEDE KALAN KISMI (BİLGİ)',
        'AA' => 'DAĞITIM TOPLAMI',
        'AB' => 'BANKA KONTROL FARKI',
        'AC' => 'DAĞITIM KONTROL FARKI',
        'AD' => 'AÇIKLAMA',
    ];
    foreach ($mutabakatBasliklari as $kolon => $baslik) {
        $mutabakatSheet->setCellValue($kolon . '2', $baslik);
    }

    foreach ([
        ['aralik' => 'A2:B2', 'renk' => '64748B'],
        ['aralik' => 'C2:K2', 'renk' => '15803D'],
        ['aralik' => 'L2:Q2', 'renk' => 'B91C1C'],
        ['aralik' => 'R2:R2', 'renk' => 'B45309'],
        ['aralik' => 'S2:AD2', 'renk' => '2563EB'],
    ] as $grup) {
        $mutabakatSheet->getStyle($grup['aralik'])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $grup['renk']]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
    }
    $mutabakatSheet->getRowDimension(2)->setRowHeight(58);
    $mutabakatSheet->freezePane('C3');

    // Başlık uzunluğu kolonları büyütmesin; tutarlar sığsın, uzun başlıklar hücre içinde kırılsın.
    $mutabakatGenislikleri = [
        'A' => 24, 'B' => 15,
        'C' => 16, 'D' => 14, 'E' => 13, 'F' => 16, 'G' => 15, 'H' => 15,
        'I' => 16, 'J' => 16, 'K' => 17,
        'L' => 14, 'M' => 14, 'N' => 15, 'O' => 17, 'P' => 17, 'Q' => 17,
        'R' => 18,
        'S' => 14, 'T' => 14, 'U' => 14, 'V' => 17, 'W' => 16,
        'X' => 18, 'Y' => 19, 'Z' => 19, 'AA' => 16, 'AB' => 16, 'AC' => 16, 'AD' => 46,
    ];
    foreach ($mutabakatGenislikleri as $kolon => $genislik) {
        $mutabakatSheet->getColumnDimension($kolon)->setAutoSize(false)->setWidth($genislik);
    }

    $mutabakatParaKolonlari = [
        'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
        'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC',
    ];
    $mutabakatSatir = 3;
    foreach ($yemekVerileri as $veri) {
        $dagilimaDahilPrim = (float) ($veri['dagilima_dahil_prim_bilgi'] ?? 0);
        $ayriPrim = (float) ($veri['prim'] ?? 0);
        $resmiKalan = max(0.0, round((float) $veri['resmi_banka_matrahi'], 2));
        $resmiEklenenPayi = static function (float $aday) use (&$resmiKalan): float {
            $pay = min(max(0.0, round($aday, 2)), $resmiKalan);
            $resmiKalan = round($resmiKalan - $pay, 2);
            return $pay;
        };
        // Banka matrahını resmî öncelik sırasıyla kalemlere dağıt; yeşil bölümün toplamı K sütununa eşit kalsın.
        $resmiAsgari = $resmiEklenenPayi((float) $veri['resmi_alacak_asgari']);
        $resmiEsYardimi = $resmiEklenenPayi((float) $veri['es_yardimi']);
        $resmiRtc = $resmiEklenenPayi((float) ($veri['resmi_rtc_net'] ?? 0));
        $resmiHtc = $resmiEklenenPayi((float) ($veri['resmi_htc_net'] ?? 0));
        $resmiFazlaMesai = $resmiEklenenPayi((float) ($veri['resmi_fazla_mesai_net'] ?? 0));
        $resmiPrim = $resmiEklenenPayi((float) ($veri['resmi_prim_ikramiye'] ?? 0));
        $resmiYemek = $resmiEklenenPayi((float) ($veri['resmi_yemek_yardimi'] ?? 0));
        $digerResmiEklenen = $resmiKalan;
        $kesintiKalan = max(0.0, round((float) $veri['toplam_personel_kesintisi'], 2));
        $avansKesintiDetayi = min(max(0.0, round((float) $veri['avans'], 2)), $kesintiKalan);
        $kesintiKalan = round($kesintiKalan - $avansKesintiDetayi, 2);
        $icraKesintiDetayi = min(max(0.0, round((float) $veri['icra'], 2)), $kesintiKalan);
        $kesintiKalan = round($kesintiKalan - $icraKesintiDetayi, 2);
        $digerKesintiDetayi = $kesintiKalan;
        $aciklama = !empty($veri['manuel_dagitim']) ? 'Manuel ödeme dağıtımı.' : 'Mutabık.';
        if ($dagilimaDahilPrim > 0) {
            $aciklama = number_format($dagilimaDahilPrim, 2, ',', '.')
                . ' TL prim yemek/banka dağılımına dahildir; yeniden eklenmez.';
            if ($ayriPrim > 0) {
                $aciklama .= ' ' . number_format($ayriPrim, 2, ',', '.') . ' TL prim ayrıca ödenir.';
            }
        } elseif ($ayriPrim > 0) {
            $aciklama = number_format($ayriPrim, 2, ',', '.') . ' TL prim ayrıca ödenir.';
        }
        if (abs($digerResmiEklenen) >= 0.01) {
            $aciklama .= ' Diğer resmî eklenen: ' . number_format($digerResmiEklenen, 2, ',', '.') . ' TL.';
        }

        $mutabakatSheet->setCellValue('A' . $mutabakatSatir, $veri['adi_soyadi']);
        $mutabakatSheet->setCellValueExplicit('B' . $mutabakatSatir, $veri['tc_kimlik'], DataType::TYPE_STRING);
        $mutabakatSheet->setCellValue('C' . $mutabakatSatir, $resmiAsgari);
        $mutabakatSheet->setCellValue('D' . $mutabakatSatir, $resmiYemek);
        $mutabakatSheet->setCellValue('E' . $mutabakatSatir, $resmiEsYardimi);
        $mutabakatSheet->setCellValue('F' . $mutabakatSatir, $resmiRtc);
        $mutabakatSheet->setCellValue('G' . $mutabakatSatir, $resmiHtc);
        $mutabakatSheet->setCellValue('H' . $mutabakatSatir, $resmiFazlaMesai);
        $mutabakatSheet->setCellValue('I' . $mutabakatSatir, $resmiPrim);
        $mutabakatSheet->setCellValue('J' . $mutabakatSatir, $digerResmiEklenen);
        $mutabakatSheet->setCellValue('K' . $mutabakatSatir, $veri['resmi_banka_matrahi']);
        $mutabakatSheet->setCellValue('L' . $mutabakatSatir, $avansKesintiDetayi);
        $mutabakatSheet->setCellValue('M' . $mutabakatSatir, $icraKesintiDetayi);
        $mutabakatSheet->setCellValue('N' . $mutabakatSatir, $digerKesintiDetayi);
        $mutabakatSheet->setCellValue('O' . $mutabakatSatir, $veri['bankadan_dusulen_kesinti']);
        $mutabakatSheet->setCellValue('P' . $mutabakatSatir, $veri['elden_dusulen_kesinti']);
        $mutabakatSheet->setCellValue('Q' . $mutabakatSatir, $veri['toplam_personel_kesintisi']);
        $mutabakatSheet->setCellValue('R' . $mutabakatSatir, $veri['banka_odemesi']);
        $mutabakatSheet->setCellValue('S' . $mutabakatSatir, $veri['elden_odeme']);
        $mutabakatSheet->setCellValue('T' . $mutabakatSatir, $veri['sodexo_yemek']);
        $mutabakatSheet->setCellValue('U' . $mutabakatSatir, $veri['diger_odeme']);
        $mutabakatSheet->setCellValue('V' . $mutabakatSatir, $veri['net_odenecek_toplam']);
        $mutabakatSheet->setCellValue('W' . $mutabakatSatir, $veri['toplam_hakedis']);
        $mutabakatSheet->setCellValue('X' . $mutabakatSatir, $veri['prim_hakedisi_bilgi']);
        $mutabakatSheet->setCellValue('Y' . $mutabakatSatir, $dagilimaDahilPrim);
        $mutabakatSheet->setCellValue('Z' . $mutabakatSatir, $ayriPrim);
        $mutabakatSheet->setCellValue('AA' . $mutabakatSatir, $veri['dagitim_toplami']);
        $mutabakatSheet->setCellValue('AB' . $mutabakatSatir, $veri['banka_kontrol_farki']);
        $mutabakatSheet->setCellValue('AC' . $mutabakatSatir, $veri['dagitim_farki']);
        $mutabakatSheet->setCellValue('AD' . $mutabakatSatir, $aciklama);
        $mutabakatSheet->getStyle('A' . $mutabakatSatir . ':AD' . $mutabakatSatir)->applyFromArray($dataStyle);
        $mutabakatSheet->getStyle('C' . $mutabakatSatir . ':K' . $mutabakatSatir)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF5');
        $mutabakatSheet->getStyle('L' . $mutabakatSatir . ':Q' . $mutabakatSatir)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF2F2');
        $mutabakatSheet->getStyle('R' . $mutabakatSatir)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $mutabakatSheet->getStyle('R' . $mutabakatSatir)->getFont()->setBold(true)->getColor()->setRGB('92400E');
        $mutabakatSheet->getStyle('S' . $mutabakatSatir . ':AD' . $mutabakatSatir)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF6FF');
        foreach ($mutabakatParaKolonlari as $paraKolonu) {
            $mutabakatSheet->getStyle($paraKolonu . $mutabakatSatir)->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
        }
        $kontrolRengi = (abs((float) $veri['banka_kontrol_farki']) < 0.01 && abs((float) $veri['dagitim_farki']) < 0.01)
            ? 'DCFCE7'
            : 'FEE2E2';
        $mutabakatSheet->getStyle('AB' . $mutabakatSatir . ':AC' . $mutabakatSatir)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($kontrolRengi);
        $mutabakatSheet->getStyle('AD' . $mutabakatSatir)->getAlignment()->setWrapText(true);
        $mutabakatSatir++;
    }

    $mutabakatToplamSatir = $mutabakatSatir;
    $mutabakatSheet->setCellValue('A' . $mutabakatToplamSatir, 'TOPLAM');
    foreach ($mutabakatParaKolonlari as $toplamKolonu) {
        $mutabakatSheet->setCellValue(
            $toplamKolonu . $mutabakatToplamSatir,
            '=SUM(' . $toplamKolonu . '3:' . $toplamKolonu . ($mutabakatToplamSatir - 1) . ')'
        );
        $mutabakatSheet->getStyle($toplamKolonu . $mutabakatToplamSatir)
            ->getNumberFormat()->setFormatCode('#,##0.00 "₺"');
    }
    $mutabakatSheet->getStyle('A' . $mutabakatToplamSatir . ':AD' . $mutabakatToplamSatir)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
    ]);
    $mutabakatSheet->getStyle('C' . $mutabakatToplamSatir . ':K' . $mutabakatToplamSatir)
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
    $mutabakatSheet->getStyle('L' . $mutabakatToplamSatir . ':Q' . $mutabakatToplamSatir)
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
    $mutabakatSheet->getStyle('R' . $mutabakatToplamSatir)
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE68A');
    $mutabakatSheet->getStyle('S' . $mutabakatToplamSatir . ':AD' . $mutabakatToplamSatir)
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
    $mutabakatSheet->setAutoFilter('A2:AD' . ($mutabakatToplamSatir - 1));
    $spreadsheet->setActiveSheetIndex(1);
    
    // Dosya adı
    $donemAdiSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $donem->donem_adi);
    $dosyaAdi = 'muhasebe_listesi_' . $donemAdiSlug . '_' . date('Y-m-d') . '.xlsx';
    
    // HTTP başlıkları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Excel dosyasını oluştur ve indir
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}
