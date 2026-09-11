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
