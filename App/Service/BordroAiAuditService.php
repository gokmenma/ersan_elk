<?php

namespace App\Service;

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\BordroParametreModel;
use App\Model\PersonelModel;
use App\Model\SettingsModel;
use App\Service\AiAgentService;
use Exception;
use PDO;

class BordroAiAuditService
{
    private BordroDonemModel $donemModel;
    private BordroPersonelModel $bordroPersonelModel;
    private BordroParametreModel $parametreModel;
    private PersonelModel $personelModel;
    private ?AiAgentService $aiAgentService = null;

    public function __construct()
    {
        $this->donemModel = new BordroDonemModel();
        $this->bordroPersonelModel = new BordroPersonelModel();
        $this->parametreModel = new BordroParametreModel();
        $this->personelModel = new PersonelModel();
        
        try {
            $this->aiAgentService = new AiAgentService();
        } catch (Exception $e) {
            error_log("BordroAiAuditService AI Agent Başlatılamadı: " . $e->getMessage());
        }
    }

    /**
     * Seçilen bordro dönemini ve opsiyonel olarak tekil bir personeli denetler.
     *
     * @param int $donemId
     * @param int|null $personelId
     * @param bool $useLlm
     * @param int|null $compareDonemId
     * @return array
     */
    public function auditDonem(int $donemId, ?int $personelId = null, bool $useLlm = true, ?int $compareDonemId = null): array
    {
        $startTime = microtime(true);
        $donem = $this->donemModel->getDonemById($donemId);

        if (!$donem) {
            return [
                'success' => false,
                'message' => 'Geçerli bir bordro dönemi bulunamadı.'
            ];
        }

        $firmaId = $_SESSION['firma_id'] ?? 1;
        $userId = $_SESSION['user_id'] ?? 0;

        // 1. Dönem Parametrelerini Al
        $paramTarih = $donem->baslangic_tarihi ?? date('Y-m-d');
        $parametreler = $this->parametreModel->getAllParametrelerMap($paramTarih);
        
        $asgariUcretNet = (float) ($parametreler['asgari_ucret_net'] ?? 17002.12);
        $gunlukYemekIstisna = (float) ($parametreler['gunluk_yemek_istisnasi'] ?? 300.0);

        // 2. Personel Bordro Listesini Al
        $personeller = $this->bordroPersonelModel->getPersonellerByDonem($donemId, $personelId ? [$personelId] : []);
        if (empty($personeller)) {
            return [
                'success' => false,
                'message' => 'Bu dönemde denetlenecek personel bordro kaydı bulunamadı.'
            ];
        }

        // 3. Karşılaştırma Dönemi
        $oncekiDonem = $compareDonemId ? $this->donemModel->getDonemById($compareDonemId) : $this->donemModel->getPreviousDonem($donem->baslangic_tarihi);
        $oncekiBordroMap = [];
        $oncekiToplamNet = 0.0;
        $oncekiToplamBanka = 0.0;
        $oncekiToplamElden = 0.0;
        $oncekiToplamYemek = 0.0;
        $oncekiPersonelSayisi = 0;

        $oncekiAsgariNet = $asgariUcretNet;
        if ($oncekiDonem) {
            $oncekiParam = $this->parametreModel->getAllParametrelerMap($oncekiDonem->baslangic_tarihi ?? date('Y-m-d'));
            $oncekiAsgariNet = (float) ($oncekiParam['asgari_ucret_net'] ?? $asgariUcretNet);
            $oncekiPersoneller = $this->bordroPersonelModel->getPersonellerByDonem($oncekiDonem->id);
            $oncekiPersonelSayisi = count($oncekiPersoneller);
            foreach ($oncekiPersoneller as $op) {
                $opHesap = $this->bordroPersonelModel->hesaplaOrtakGosterimDegerleri($op, $oncekiDonem, $oncekiAsgariNet);
                $op->hesap_net = (float) ($opHesap['netAlacagi'] ?? 0);
                $op->hesap_banka = (float) ($opHesap['bankaOdemesi'] ?? 0);
                $op->hesap_elden = (float) ($opHesap['eldenOdeme'] ?? 0);
                $op->hesap_yemek = (float) ($opHesap['sodexoOdemesi'] ?? 0);
                $oncekiBordroMap[$op->personel_id] = $op;

                $oncekiToplamNet += $op->hesap_net;
                $oncekiToplamBanka += $op->hesap_banka;
                $oncekiToplamElden += $op->hesap_elden;
                $oncekiToplamYemek += $op->hesap_yemek;
            }
        }

        // Görev geçmişi kayıtlarını toplu çek (Parçalı giriş/çıkış kontrolü için)
        $gorevGecmisiMap = [];
        try {
            $db = $this->bordroPersonelModel->getDb();
            $stmtGorev = $db->prepare("SELECT personel_id, departman, gorev, maas_durumu, maas_tutari, baslangic_tarihi, bitis_tarihi 
                                      FROM personel_gorev_gecmisi 
                                      WHERE baslangic_tarihi <= ? 
                                      AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
                                      ORDER BY baslangic_tarihi ASC, id ASC");
            $stmtGorev->execute([$donem->bitis_tarihi, $donem->baslangic_tarihi]);
            $allGorev = $stmtGorev->fetchAll(PDO::FETCH_OBJ);
            foreach ($allGorev as $g) {
                $gorevGecmisiMap[$g->personel_id][] = [
                    'baslangic' => $g->baslangic_tarihi,
                    'bitis' => $g->bitis_tarihi,
                    'maas_tutari' => (float) ($g->maas_tutari ?? 0)
                ];
            }
        } catch (\Exception $e) {
            error_log("BordroAiAuditService gorev gecmisi alinamadi: " . $e->getMessage());
        }

        // 4. Kural Motoru (Rule Engine) Taraması
        $issues = [];
        $allPersonRows = [];
        $totalRiskAmount = 0.0;
        $personelCount = count($personeller);
        $criticalCount = 0;
        $warningCount = 0;
        $infoCount = 0;

        $toplamNetHakedis = 0.0;
        $toplamBankaOdemesi = 0.0;
        $toplamEldenOdeme = 0.0;
        $toplamYemekOdemesi = 0.0;

        foreach ($personeller as $p) {
            // Dinamik güncel bordro hesaplama değerlerini al
            $hesap = $this->bordroPersonelModel->hesaplaOrtakGosterimDegerleri($p, $donem, $asgariUcretNet);
            $p->hesap_net = (float) ($hesap['netAlacagi'] ?? 0);
            $p->hesap_banka = (float) ($hesap['bankaOdemesi'] ?? 0);
            $p->hesap_elden = (float) ($hesap['eldenOdeme'] ?? 0);
            $p->hesap_yemek = (float) ($hesap['sodexoOdemesi'] ?? 0);
            $p->hesap_calisma_gunu = (int) ($hesap['calismaGunu'] ?? 30);
            $p->hesap_fiili_gun = (int) ($hesap['fiiliCalismaGunu'] ?? 0);

            $toplamNetHakedis += $p->hesap_net;
            $toplamBankaOdemesi += $p->hesap_banka;
            $toplamEldenOdeme += $p->hesap_elden;
            $toplamYemekOdemesi += $p->hesap_yemek;

            $op = $oncekiBordroMap[$p->personel_id] ?? null;
            $personelGorevAraliklari = $gorevGecmisiMap[$p->personel_id] ?? [];

            $pIssues = $this->auditSinglePersonel(
                $p,
                $donem,
                $asgariUcretNet,
                $gunlukYemekIstisna,
                $op,
                $personelGorevAraliklari
            );

            $allPersonRows[] = [
                'personel_id' => $p->personel_id,
                'bordro_id' => $p->id,
                'ad_soyad' => $p->adi_soyadi ?? $p->ad_soyad ?? ($p->ad ?? '') . ' ' . ($p->soyad ?? ''),
                'tc_kimlik' => $p->tc_kimlik_no ?? $p->tc_kimlik ?? '',
                'gorev' => $p->gg_gorev ?? $p->gorev ?? '',
                'departman' => $p->gg_departman ?? $p->departman ?? '',
                'ucret_tipi' => $p->gg_maas_durumu ?? $p->maas_durumu ?? $p->ucret_tipi ?? 'Net',
                'calisma_gunu' => $p->hesap_calisma_gunu,
                'fiili_gun' => $p->hesap_fiili_gun,
                'ise_giris' => $p->ise_giris_tarihi ?? $p->giris_tarihi ?? '',
                'isten_cikis' => $p->isten_cikis_tarihi ?? $p->cikis_tarihi ?? '',
                'net_alacagi' => $p->hesap_net,
                'banka_alacagi' => $p->hesap_banka,
                'elden_odeme' => $p->hesap_elden,
                'sodexo_alacagi' => $p->hesap_yemek,
                'onceki_net' => $op ? $op->hesap_net : null,
                'onceki_banka' => $op ? $op->hesap_banka : null,
                'onceki_elden' => $op ? $op->hesap_elden : null,
                'onceki_yemek' => $op ? $op->hesap_yemek : null,
                'has_issue' => !empty($pIssues),
                'issues' => $pIssues
            ];

            if (!empty($pIssues)) {
                foreach ($pIssues as $iss) {
                    if ($iss['severity'] === 'CRITICAL') {
                        $criticalCount++;
                        $totalRiskAmount += $iss['risk_amount'] ?? 0;
                    } elseif ($iss['severity'] === 'WARNING') {
                        $warningCount++;
                        $totalRiskAmount += $iss['risk_amount'] ?? 0;
                    } else {
                        $infoCount++;
                    }
                }

                $issues[] = [
                    'personel_id' => $p->personel_id,
                    'bordro_id' => $p->id,
                    'ad_soyad' => $p->adi_soyadi ?? $p->ad_soyad ?? ($p->ad ?? '') . ' ' . ($p->soyad ?? ''),
                    'tc_kimlik' => $p->tc_kimlik_no ?? $p->tc_kimlik ?? '',
                    'net_alacagi' => $p->hesap_net,
                    'banka_alacagi' => $p->hesap_banka,
                    'elden_odeme' => $p->hesap_elden,
                    'sodexo_alacagi' => $p->hesap_yemek,
                    'issues' => $pIssues
                ];
            }
        }

        // 5. Sağlık Skoru Hesaplama (0 - 100)
        // Her kritik hata -10 puan, her uyarı -3 puan (personel sayısına normalize edilmiş)
        $penalty = ($criticalCount * 8) + ($warningCount * 3) + ($infoCount * 0.5);
        $penaltyNormalized = min(85, round(($penalty / max(1, $personelCount)) * 25, 1));
        $healthScore = max(15, round(100 - $penaltyNormalized));
        if ($criticalCount === 0 && $warningCount === 0) {
            $healthScore = 100;
        }

        $auditSummary = [
            'donem_id' => $donem->id,
            'donem_adi' => $donem->donem_adi ?? ($donem->yil . ' ' . $donem->ay),
            'donem_tarih' => $donem->baslangic_tarihi . ' - ' . $donem->bitis_tarihi,
            'toplam_personel' => $personelCount,
            'sorunlu_personel_sayisi' => count($issues),
            'sorunsuz_personel_sayisi' => $personelCount - count($issues),
            'kritik_hata_sayisi' => $criticalCount,
            'uyari_sayisi' => $warningCount,
            'bilgi_sayisi' => $infoCount,
            'tahmini_finansal_risk' => round($totalRiskAmount, 2),
            'saglik_skoru' => $healthScore,
            'toplam_net_hakedis' => round($toplamNetHakedis, 2),
            'toplam_banka' => round($toplamBankaOdemesi, 2),
            'toplam_elden' => round($toplamEldenOdeme, 2),
            'toplam_yemek' => round($toplamYemekOdemesi, 2),
            'onceki_donem' => $oncekiDonem ? [
                'id' => $oncekiDonem->id,
                'donem_adi' => $oncekiDonem->donem_adi ?? ($oncekiDonem->yil . ' ' . $oncekiDonem->ay),
                'toplam_personel' => $oncekiPersonelSayisi,
                'toplam_net_hakedis' => round($oncekiToplamNet, 2),
                'toplam_banka' => round($oncekiToplamBanka, 2),
                'toplam_elden' => round($oncekiToplamElden, 2),
                'toplam_yemek' => round($oncekiToplamYemek, 2),
                'net_farki' => round($toplamNetHakedis - $oncekiToplamNet, 2),
                'personel_farki' => $personelCount - $oncekiPersonelSayisi
            ] : null
        ];

        // 6. Yapay Zeka (LLM) Değerlendirmesi
        $aiExecutiveReport = '';
        if ($useLlm && $this->aiAgentService) {
            $aiExecutiveReport = $this->generateAiExecutiveReport($firmaId, $userId, $auditSummary, $issues);
        } else {
            $aiExecutiveReport = $this->generateHeuristicExecutiveReport($auditSummary, $issues);
        }

        $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        return [
            'success' => true,
            'summary' => $auditSummary,
            'issues' => $issues,
            'all_rows' => $allPersonRows,
            'ai_report' => $aiExecutiveReport,
            'execution_time_ms' => $executionTimeMs
        ];
    }

    /**
     * Tek bir personel bordro satırının detaylı kural denetimi
     */
    private function auditSinglePersonel($p, $donem, float $asgariUcretNet, float $gunlukYemekIstisna, $oncekiBordro = null, array $gorevAraliklari = []): array
    {
        $issues = [];

        $netAlacagi = (float) ($p->hesap_net ?? $p->net_maas ?? $p->net_alacagi ?? 0);
        $bankaAlacagi = (float) ($p->hesap_banka ?? $p->banka_odemesi ?? $p->banka_alacagi ?? 0);
        $eldenOdeme = (float) ($p->hesap_elden ?? $p->elden_odeme ?? 0);
        $sodexoAlacagi = (float) ($p->hesap_yemek ?? $p->sodexo_odemesi ?? $p->sodexo_alacagi ?? 0);
        $toplamKesinti = (float) ($p->kesinti_tutar ?? 0);
        $icraKesinti = (float) ($p->hd_icra_kesintisi ?? $p->guncel_icra_kesinti ?? $p->icra_kesintisi ?? 0);
        $calismaGunu = (int) ($p->hesap_calisma_gunu ?? $p->calisan_gun ?? $p->hd_maas_hesap_gunu ?? $p->calisma_gunu ?? 30);
        $fiiliGun = (int) ($p->hesap_fiili_gun ?? $p->hd_fiili_calisma_gunu ?? $p->fiili_gun ?? 0);
        $gunlukYemek = (float) ($p->yemek_yardimi_tutari ?? $p->gunluk_yemek_ucreti ?? 0);
        $maasTutari = (float) ($p->maas_tutari ?? 0);
        $gunlukUcret = (float) ($p->gunluk_ucret ?? ($maasTutari > 0 ? ($maasTutari / 30) : 0));

        $gorev = trim((string) ($p->gg_gorev ?? $p->gorev ?? ''));
        $departman = trim((string) ($p->gg_departman ?? $p->departman ?? ''));
        $maasDurumu = trim((string) ($p->gg_maas_durumu ?? $p->maas_durumu ?? $p->ucret_tipi ?? ''));
        $gorevGecmisiVar = (bool) (!empty($p->gorev_gecmisi_var) || !empty($gorevAraliklari));

        // Kural 0-A: Görev Geçmişi Tanımlı Değil (Kritik)
        if (!$gorevGecmisiVar) {
            $issues[] = [
                'code' => 'MISSING_JOB_HISTORY',
                'severity' => 'CRITICAL',
                'title' => 'Görev Geçmişi Tanımlı Değil',
                'description' => "Personelin bu dönem için geçerli görev geçmişi kaydı bulunmamaktadır. Maaş ve hak edişler personel ana kartındaki veri üzerinden hesaplanmaktadır.",
                'risk_amount' => 0,
                'action_recommendation' => "Personel kartından 'Görev Geçmişi' sekmesine giderek dönem başlangıcına uygun görev ve maaş kaydı oluşturunuz."
            ];
        }

        // Kural 0-B: Görev / Pozisyon veya Ücret Tipi Tanımsız (Kritik)
        if (empty($gorev) || $gorev === '-' || empty($maasDurumu) || $maasDurumu === '-') {
            $eksikler = [];
            if (empty($gorev) || $gorev === '-') $eksikler[] = "Görev/Pozisyon";
            if (empty($maasDurumu) || $maasDurumu === '-') $eksikler[] = "Maaş Tipi (Net/Brüt)";
            $eksikStr = implode(' ve ', $eksikler);

            $issues[] = [
                'code' => 'MISSING_JOB_TYPE',
                'severity' => 'CRITICAL',
                'title' => "{$eksikStr} Tanımsız",
                'description' => "Personelin sistemde {$eksikStr} bilgisi tanımlanmamıştır.",
                'risk_amount' => 0,
                'action_recommendation' => "Personel kartında veya görev geçmişinde personelin görev/pozisyon ve maaş tipini (Net/Brüt) belirleyiniz."
            ];
        }

        // Kural 1: Eksi Bakiye (Kritik)
        if ($netAlacagi < 0) {
            $issues[] = [
                'code' => 'NEG_BALANCE',
                'severity' => 'CRITICAL',
                'title' => 'Eksi (-) Net Hakediş Bakiye Riski',
                'description' => "Personelin net hakedişi kesintiler sonrası negatif bakiyeye düşmüştür ({$netAlacagi} ₺).",
                'risk_amount' => abs($netAlacagi),
                'action_recommendation' => 'Kesinti tutarlarını (özellikle avans/icra) inceleyerek net hakedişi aşmayacak şekilde düzenleyiniz.'
            ];
        }

        // Kural 2: Dağıtım Kaçağı / Matematiksel Eşitsizlik (Kritik)
        $dagilimToplami = round($bankaAlacagi + $eldenOdeme + $sodexoAlacagi, 2);
        $dagilimFarki = round($netAlacagi - $dagilimToplami, 2);
        if (abs($dagilimFarki) > 0.05) {
            $issues[] = [
                'code' => 'DISTRIBUTION_MISMATCH',
                'severity' => 'CRITICAL',
                'title' => 'Ödeme Dağılımı Tutarsızlığı',
                'description' => "Net hakediş ({$netAlacagi} ₺) ile ödeme kanalları toplamı ({$dagilimToplami} ₺) arasında {$dagilimFarki} ₺ fark var.",
                'risk_amount' => abs($dagilimFarki),
                'action_recommendation' => 'Banka, elden ve yemek dağıtım hesaplamasını yeniden çalıştırınız.'
            ];
        }

        // Kural 3: Asgari Ücret Taban Kontrolü (Kritik/Yüksek)
        if ($calismaGunu > 0) {
            $oransalAsgariNet = round(($asgariUcretNet / 30) * min(30, $calismaGunu), 2);
            // Aylık net maaş veya hak edilen net tutar oransal asgari netin altındaysa
            if ($maasTutari > 0 && $maasTutari < ($asgariUcretNet - 50) && ($p->ucret_tipi ?? 'Net') === 'Net') {
                $fark = round($asgariUcretNet - $maasTutari, 2);
                $issues[] = [
                    'code' => 'BELOW_MIN_WAGE',
                    'severity' => 'WARNING',
                    'title' => 'Sözleşme Maaşı Yasal Asgari Ücretin Altında',
                    'description' => "Personelin tanımlı aylık net maaşı ({$maasTutari} ₺), yasal net asgari ücretin ({$asgariUcretNet} ₺) altındadır.",
                    'risk_amount' => $fark,
                    'action_recommendation' => 'Personel kartındaki net maaş tutarını asgari ücret düzeyine güncelleyiniz.'
                ];
            } elseif ($netAlacagi < ($oransalAsgariNet - 50) && $toplamKesinti == 0 && ($p->ucret_tipi ?? 'Net') === 'Net' && $maasTutari >= $asgariUcretNet) {
                $fark = round($oransalAsgariNet - $netAlacagi, 2);
                $issues[] = [
                    'code' => 'BELOW_MIN_WAGE_EARNING',
                    'severity' => 'WARNING',
                    'title' => 'Çalışılan Güne Göre Asgari Taban Altı Hakediş',
                    'description' => "{$calismaGunu} gün çalışma için asgari taban {$oransalAsgariNet} ₺ iken hesaplanan net hakediş {$netAlacagi} ₺ dir.",
                    'risk_amount' => $fark,
                    'action_recommendation' => 'Puantaj ve maaş hesaplama parametrelerini kontrol ediniz.'
                ];
            }
        }

        // Kural 4: Günlük Yemek İstisna Tavanı Aşımı (Yüksek)
        if ($gunlukYemek > ($gunlukYemekIstisna + 5)) {
            $yemekFazlasi = round(($gunlukYemek - $gunlukYemekIstisna) * max(1, $fiiliGun), 2);
            $issues[] = [
                'code' => 'FOOD_LIMIT_EXCEEDED',
                'severity' => 'WARNING',
                'title' => 'Günlük Yemek İstisna Tavanı Aşımı',
                'description' => "Hesaplanan günlük yemek tutarı ({$gunlukYemek} ₺), yasal tavan olan {$gunlukYemekIstisna} ₺ üzerinde.",
                'risk_amount' => $yemekFazlasi,
                'action_recommendation' => 'Maaşa dahil yemek dağıtım kuralına göre günlük yemek limitini aşan tutarı elden ödemeye kaydırınız.'
            ];
        }

        // Kural 5: 0 Fiili Gün Varken Yemek Yardımı Tahakkuku (Yüksek)
        if ($fiiliGun === 0 && $sodexoAlacagi > 0) {
            $issues[] = [
                'code' => 'FOOD_ZERO_WORK_DAYS',
                'severity' => 'WARNING',
                'title' => 'Fiilî Çalışma Günü Olmadan Yemek Yardımı Tahakkuku',
                'description' => "Personelin fiilî çalışma günü 0 olmasına rağmen {$sodexoAlacagi} ₺ yemek yardımı tahakkuk ettirilmiş.",
                'risk_amount' => $sodexoAlacagi,
                'action_recommendation' => 'Puantajdaki X günlerini veya yemek yardımı fiili gün parametresini kontrol ediniz.'
            ];
        }

        // Kural 6: İşe Giriş / Çıkış & Parçalı Görev Geçmişi Gün Uyumsuzluğu (Kritik/Yüksek)
        $takvimAnalizi = $this->calculateMaxTakvimGunu($p, $donem, $gorevAraliklari);
        $maksTakvim = $takvimAnalizi['maks_takvim'];
        $birimGunlukTutar = $gunlukUcret > 0 ? $gunlukUcret : (($netAlacagi > 0) ? ($netAlacagi / max(1, $calismaGunu)) : ($asgariUcretNet / 30));

        if ($takvimAnalizi['has_constraint'] && $calismaGunu > $maksTakvim) {
            $fazlaGun = $calismaGunu - $maksTakvim;
            $riskTutari = round($fazlaGun * $birimGunlukTutar, 2);

            if (!empty($takvimAnalizi['is_parcali'])) {
                $issues[] = [
                    'code' => 'HISTORICAL_WORK_OVER_DAYS',
                    'severity' => 'CRITICAL',
                    'title' => 'Parçalı Görev/Çalışma Geçmişine Göre Fazla Gün Tahakkuku',
                    'description' => "Personelin dönem içi parçalı çalışma aralıkları toplamı en fazla {$maksTakvim} gün olmasına rağmen çalışma günü {$calismaGunu} gün ({$fazlaGun} gün fazla) hesaplanmış. {$takvimAnalizi['detay']}",
                    'risk_amount' => $riskTutari,
                    'action_recommendation' => "Personelin çalışma gün sayısını dönem içi aktif çalışma aralıklarına uygun olarak en fazla {$maksTakvim} gün olarak güncelleyiniz."
                ];
            } elseif (!empty($takvimAnalizi['giris_kisiti']) && !empty($takvimAnalizi['cikis_kisiti'])) {
                $iseGiris = $takvimAnalizi['gecerli_bas'];
                $istenCikis = $takvimAnalizi['gecerli_bit'];
                $issues[] = [
                    'code' => 'ENTRY_EXIT_DATE_OVER_DAYS',
                    'severity' => 'CRITICAL',
                    'title' => 'İşe Giriş/Çıkış Tarih Aralığına Göre Fazla Gün Tahakkuku',
                    'description' => "Personel aynı dönem içinde işe girip ({$iseGiris}) ayrılmıştır ({$istenCikis}). Çalışabileceği maksimum gün {$maksTakvim} gün olmasına rağmen çalışma günü {$calismaGunu} gün ({$fazlaGun} gün fazla) hesaplanmış.",
                    'risk_amount' => $riskTutari,
                    'action_recommendation' => "Personelin çalışma gün sayısını giriş-çıkış aralığına uygun olarak en fazla {$maksTakvim} gün olarak revize ediniz."
                ];
            } elseif (!empty($takvimAnalizi['giris_kisiti'])) {
                $iseGiris = $takvimAnalizi['gecerli_bas'];
                $issues[] = [
                    'code' => 'ENTRY_DATE_OVER_DAYS',
                    'severity' => 'CRITICAL',
                    'title' => 'İşe Giriş Tarihine Göre Fazla Gün Tahakkuku',
                    'description' => "İşe giriş tarihi ({$iseGiris}) dönem içinde olmasına rağmen çalışma günü ({$calismaGunu} gün), kalan takvim gününden ({$maksTakvim} gün) fazla ({$fazlaGun} gün fazla).",
                    'risk_amount' => $riskTutari,
                    'action_recommendation' => "Personelin çalışma gün sayısını en fazla {$maksTakvim} gün olarak revize ediniz."
                ];
            } else {
                $istenCikis = $takvimAnalizi['gecerli_bit'];
                $issues[] = [
                    'code' => 'EXIT_DATE_OVER_DAYS',
                    'severity' => 'CRITICAL',
                    'title' => 'İşten Çıkış Tarihine Göre Fazla Gün Tahakkuku',
                    'description' => "İşten çıkış tarihi ({$istenCikis}) dönem ortasında olmasına rağmen çalışma günü ({$calismaGunu} gün), çalışabileceği günden ({$maksTakvim} gün) fazla ({$fazlaGun} gün fazla).",
                    'risk_amount' => $riskTutari,
                    'action_recommendation' => "Çıkış tarihine göre maaş hesap gününü {$maksTakvim} gün olarak güncelleyiniz."
                ];
            }
        }

        // Kural 7: Aşırı İcra Kesintisi (Yasal 1/4 Sınırı) (Orta/Yüksek)
        if ($icraKesinti > 0 && $netAlacagi > 0) {
            $yasalIcraLimiti = round(($netAlacagi + $toplamKesinti) * 0.25, 2);
            if ($icraKesinti > ($yasalIcraLimiti + 100) && ($p->nafaka_var_mi ?? 0) == 0) {
                $issues[] = [
                    'code' => 'EXCESSIVE_LEVY',
                    'severity' => 'WARNING',
                    'title' => 'Yasal 1/4 İcra Tavanının Üzerinde Kesinti',
                    'description' => "İcra kesintisi ({$icraKesinti} ₺), yasal 1/4 üst sınırını ({$yasalIcraLimiti} ₺) aşmaktadır.",
                    'risk_amount' => round($icraKesinti - $yasalIcraLimiti, 2),
                    'action_recommendation' => 'Nafaka veya özel muvafakatname yoksa icra kesintisini 1/4 tavanına çekiniz.'
                ];
            }
        }

        // Kural 8: Geçmiş Döneme Göre Anormal Dalgalanma (Trend Riski - Orta)
        if ($oncekiBordro) {
            $oncekiNet = (float) ($oncekiBordro->net_maas ?? $oncekiBordro->net_alacagi ?? 0);
            if ($oncekiNet > 5000 && $netAlacagi > 5000) {
                $degisimOrani = (($netAlacagi - $oncekiNet) / $oncekiNet) * 100;
                if ($degisimOrani > 40) {
                    $issues[] = [
                        'code' => 'SPIKE_NET_INCREASE',
                        'severity' => 'INFO',
                        'title' => 'Önceki Döneme Göre Olağandışı Maaş Artışı',
                        'description' => "Personelin net hakedişi bir önceki aya göre %" . round($degisimOrani, 1) . " oranında artmıştır (Önceki: {$oncekiNet} ₺ -> Güncel: {$netAlacagi} ₺).",
                        'risk_amount' => round($netAlacagi - $oncekiNet, 2),
                        'action_recommendation' => 'Eklenen ek ödemelerin, primlerin veya çalışma günü artışının doğruluğunu teyit ediniz.'
                    ];
                } elseif ($degisimOrani < -40 && $calismaGunu >= 25) {
                    $issues[] = [
                        'code' => 'SPIKE_NET_DECREASE',
                        'severity' => 'INFO',
                        'title' => 'Önceki Döneme Göre Belirgin Maaş Düşüşü',
                        'description' => "Tam aya yakın çalışmasına rağmen personelin hakedişinde %" . round(abs($degisimOrani), 1) . " oranında düşüş tespit edildi.",
                        'risk_amount' => round($oncekiNet - $netAlacagi, 2),
                        'action_recommendation' => 'Kesinti veya eksik ek ödeme durumunu kontrol ediniz.'
                    ];
                }
            }
        }

        // Kural 9: IBAN Bilgisi Olmadan Banka Dağıtımı (Bilgi)
        $iban = trim($p->iban_numarasi ?? $p->iban ?? $p->banka_hesap_no ?? '');
        if ($bankaAlacagi > 0 && empty($iban)) {
            $issues[] = [
                'code' => 'MISSING_IBAN',
                'severity' => 'INFO',
                'title' => 'IBAN / Hesap Bilgisi Eksik',
                'description' => "Personel için {$bankaAlacagi} ₺ banka ödemesi hesaplanmış ancak kayıtlı IBAN bilgisi bulunmuyor.",
                'risk_amount' => 0.0,
                'action_recommendation' => 'Banka export dosyasında hata almamak için personel kartına IBAN numarasını giriniz.'
            ];
        }

        return $issues;
    }

    /**
     * AI Agent (Gemini/OpenAI) ile Yönetici Özeti ve Detaylı Denetim Raporu Üretimi
     */
    private function generateAiExecutiveReport(int $firmaId, int $userId, array $summary, array $issues): string
    {
        // En kritik ilk 15 personelin anomalilerini JSON bağlamı olarak hazırla
        $topIssues = array_slice($issues, 0, 15);
        $contextJson = json_encode([
            'donem_bilgisi' => [
                'donem' => $summary['donem_adi'],
                'tarih_araligi' => $summary['donem_tarih'],
                'toplam_personel' => $summary['toplam_personel'],
                'sorunlu_personel_sayisi' => $summary['sorunlu_personel_sayisi'],
                'saglik_skoru' => $summary['saglik_skoru'],
                'tahmini_risk_tutari' => $summary['tahmini_finansal_risk'] . ' TL',
                'toplam_odeme' => [
                    'net_toplam' => $summary['toplam_net_hakedis'],
                    'banka_toplam' => $summary['toplam_banka'],
                    'elden_toplam' => $summary['toplam_elden'],
                    'yemek_toplam' => $summary['toplam_yemek'],
                ]
            ],
            'kritik_bulgular' => $topIssues
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = "Bu bordro dönemi için kapsamlı bir 'Bordro Denetim ve Risk Değerlendirme Raporu' hazırla. Tespit edilen hatalı işlemler, fazla/eksik ödeme riskleri ve yasal uyumsuzluklar hakkında yöneticiye net, aksiyon odaklı öneriler sun.";

        try {
            $res = $this->aiAgentService->processQuery($firmaId, $userId, 'bordro-denetim', $prompt . "\n\nBordro Bağlamı:\n" . $contextJson);
            if ($res['success'] && !empty($res['response']) && !str_contains($res['response'], 'Aradığınız Kriterlere Uygun Kayıt Bulunamadı')) {
                return $res['response'];
            }
        } catch (Exception $e) {
            error_log("AiExecutiveReport Exception: " . $e->getMessage());
        }

        return $this->generateHeuristicExecutiveReport($summary, $issues);
    }

    /**
     * Personelin dönem içindeki parçalı çalışma, görev geçmişi veya tekil giriş/çıkışlarına göre
     * yasal ve fiili olarak çalışabileceği maksimum takvim günü sayısını hesaplar.
     */
    private function calculateMaxTakvimGunu($p, $donem, array $gorevAraliklari = []): array
    {
        $donemBas = $donem->baslangic_tarihi;
        $donemBit = $donem->bitis_tarihi;
        $donemGunSayisi = (int) round((strtotime($donemBit) - strtotime($donemBas)) / 86400) + 1;

        // 1. Görev geçmişi kayıtları varsa aralıkları birleştirip takvim gününü hesapla
        if (!empty($gorevAraliklari)) {
            $intervals = [];
            foreach ($gorevAraliklari as $aralik) {
                $bas = max($donemBas, $aralik['baslangic']);
                $bit = !empty($aralik['bitis']) ? min($donemBit, $aralik['bitis']) : $donemBit;
                if ($bas <= $bit) {
                    $intervals[] = [strtotime($bas), strtotime($bit)];
                }
            }

            if (!empty($intervals)) {
                // Aralıkları başlangıç tarihine göre sırala
                usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
                $merged = [];
                $current = $intervals[0];
                for ($i = 1; $i < count($intervals); $i++) {
                    if ($intervals[$i][0] <= $current[1] + 86400) {
                        $current[1] = max($current[1], $intervals[$i][1]);
                    } else {
                        $merged[] = $current;
                        $current = $intervals[$i];
                    }
                }
                $merged[] = $current;

                $toplamTakvimGunu = 0;
                foreach ($merged as $m) {
                    $toplamTakvimGunu += (int) round(($m[1] - $m[0]) / 86400) + 1;
                }

                $isParcali = count($gorevAraliklari) > 1;
                $hasConstraint = $toplamTakvimGunu < $donemGunSayisi;

                return [
                    'maks_takvim' => min(30, $toplamTakvimGunu),
                    'is_parcali' => $isParcali,
                    'has_constraint' => $hasConstraint,
                    'detay' => $isParcali ? "Dönem içi birden fazla görev/çalışma aralığı mevcut (Toplam: {$toplamTakvimGunu} takvim günü)" : null,
                    'giris_kisiti' => false,
                    'cikis_kisiti' => false,
                    'gecerli_bas' => date('Y-m-d', $merged[0][0]),
                    'gecerli_bit' => date('Y-m-d', end($merged)[1])
                ];
            }
        }

        // 2. Görev geçmişi yoksa standart tekil işe giriş / çıkış tarihlerine bak
        $iseGiris = $p->ise_giris_tarihi ?? $p->giris_tarihi ?? null;
        if (empty($iseGiris) || $iseGiris === '0000-00-00') $iseGiris = null;

        $istenCikis = $p->isten_cikis_tarihi ?? $p->cikis_tarihi ?? null;
        if (empty($istenCikis) || $istenCikis === '0000-00-00') $istenCikis = null;

        $gecerliBas = ($iseGiris && $iseGiris > $donemBas && $iseGiris <= $donemBit) ? $iseGiris : $donemBas;
        $gecerliBit = ($istenCikis && $istenCikis >= $donemBas && $istenCikis < $donemBit) ? $istenCikis : $donemBit;

        $hasConstraint = ($gecerliBas > $donemBas) || ($gecerliBit < $donemBit);
        if ($gecerliBas <= $gecerliBit) {
            $takvim = (int) round((strtotime($gecerliBit) - strtotime($gecerliBas)) / 86400) + 1;
        } else {
            $takvim = 0;
        }

        return [
            'maks_takvim' => min(30, $takvim),
            'is_parcali' => false,
            'has_constraint' => $hasConstraint,
            'detay' => null,
            'giris_kisiti' => ($gecerliBas > $donemBas),
            'cikis_kisiti' => ($gecerliBit < $donemBit),
            'gecerli_bas' => $gecerliBas,
            'gecerli_bit' => $gecerliBit
        ];
    }

    /**
     * AI çağrısı yapılamadığında deterministik Türkçe Yönetici Denetim Raporu üretir
     */
    private function generateHeuristicExecutiveReport(array $summary, array $issues): string
    {
        $saglik = $summary['saglik_skoru'];
        $durumEmoji = $saglik >= 90 ? '🟢' : ($saglik >= 70 ? '🟡' : '🔴');
        $durumMetni = $saglik >= 90 ? 'GÜVENLİ & STABİL' : ($saglik >= 70 ? 'DİKKAT GEREKTİREN RİSKLER MEVCUT' : 'YÜKSEK RİSK & ACİL DÜZELTME GEREKİYOR');

        $out = "### {$durumEmoji} Bordro Dönem Denetim Raporu ({$summary['donem_adi']})\n\n";
        $out .= "**Genel Durum:** `{$durumMetni}` (Bordro Sağlık Skoru: **%{$saglik}**)\n\n";
        $out .= "Bu dönemde toplam **{$summary['toplam_personel']} personel** taranmış olup, **{$summary['sorunlu_personel_sayisi']} personelde** toplam **" . ($summary['kritik_hata_sayisi'] + $summary['uyari_sayisi']) . " adet** dikkat/hata kaydı tespit edilmiştir.\n\n";
        
        $out .= "#### 📊 Finansal ve Risk Özeti\n";
        $out .= "- **Tahmini Finansal Risk:** `" . number_format($summary['tahmini_finansal_risk'], 2, ',', '.') . " ₺`\n";
        $out .= "- **Kritik Hatalar:** `{$summary['kritik_hata_sayisi']} adet` (Eksi bakiye, dağıtım tutarsızlığı, işe giriş/çıkış gün aşımı)\n";
        $out .= "- **Mevzuat & Dağıtım Uyarıları:** `{$summary['uyari_sayisi']} adet` (Yemek istisna tavanı, asgari ücret altı dağıtım)\n";
        $out .= "- **Toplam Net Ödeme:** `" . number_format($summary['toplam_net_hakedis'], 2, ',', '.') . " ₺` (Banka: " . number_format($summary['toplam_banka'], 2, ',', '.') . " ₺, Elden: " . number_format($summary['toplam_elden'], 2, ',', '.') . " ₺, Yemek: " . number_format($summary['toplam_yemek'], 2, ',', '.') . " ₺)\n\n";

        if (!empty($issues)) {
            $out .= "#### 🚨 Öncelikli Aksiyon Gerektiren Personeller\n";
            $count = 0;
            foreach ($issues as $iss) {
                if ($count++ >= 5) break;
                $out .= "- **{$iss['ad_soyad']}**: ";
                $descriptions = [];
                foreach ($iss['issues'] as $item) {
                    $descriptions[] = $item['title'] . " (" . $item['description'] . ")";
                }
                $out .= implode(' | ', $descriptions) . "\n";
            }
            if (count($issues) > 5) {
                $out .= "\n*...ve " . (count($issues) - 5) . " diğer personel tablodan incelenebilir.*\n";
            }
        } else {
            $out .= "🎉 **Harika!** Bu dönemde herhangi bir matematiksel uyumsuzluk, fazla/eksik ödeme veya mevzuat anomalisi tespit edilmedi.\n";
        }

        return $out;
    }
}
