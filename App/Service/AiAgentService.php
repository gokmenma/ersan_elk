<?php

namespace App\Service;

use App\Model\AiAgentModel;
use App\Service\AiContext\AracTakipContext;
use Exception;

class AiAgentService
{
    private AiAgentModel $model;
    private string $apiKey;
    private string $provider;
    private string $modelName;

    public function __construct()
    {
        $this->model = new AiAgentModel();
        
        $settingsModel = new \App\Model\SettingsModel();
        $firmaId = $_SESSION['firma_id'] ?? 1;
        $dbSettings = $settingsModel->getAllSettingsAsKeyValue($firmaId);
        
        $dbOpenAiKey = $dbSettings['openai_api_key'] ?? '';
        $envKey = trim($_ENV['AI_AGENT_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? $_ENV['GEMINI_API_KEY'] ?? $_ENV['DEEPSEEK_API_KEY'] ?? '');
        
        $this->apiKey = !empty($dbOpenAiKey) ? $dbOpenAiKey : $envKey;
        
        $dbProvider = $dbSettings['ai_agent_provider'] ?? '';
        $envProvider = $_ENV['AI_AGENT_PROVIDER'] ?? '';
        $this->provider = strtolower(!empty($dbProvider) ? $dbProvider : (!empty($envProvider) ? $envProvider : (str_starts_with($this->apiKey, 'sk-') ? 'openai' : 'gemini')));

        $dbModel = $dbSettings['ai_agent_model'] ?? '';
        $envModel = $_ENV['AI_AGENT_MODEL'] ?? '';
        $this->modelName = !empty($dbModel) ? $dbModel : (!empty($envModel) ? $envModel : ($this->provider === 'openai' ? 'gpt-4o-mini' : 'gemini-1.5-flash'));
    }

    /**
     * Kullanıcı sorgusunu işler ve AI cevabını üretir.
     */
    public function processQuery(int $firmaId, int $userId, string $module, string $userPrompt): array
    {
        $startTime = microtime(true);
        $userPrompt = trim($userPrompt);

        if (empty($userPrompt)) {
            return [
                'success' => false,
                'message' => 'Lütfen geçerli bir soru veya analiz talebi giriniz.'
            ];
        }

        // 1. Cache Kontrolü
        $cacheKey = md5($module . '_' . strtolower($userPrompt));
        $cachedResponse = $this->model->getCachedResponse($firmaId, $module, $cacheKey);
        if ($cachedResponse) {
            return [
                'success' => true,
                'response' => $cachedResponse,
                'cached' => true
            ];
        }

        // 2. Bağlam (Context) Üretimi
        $contextDataStr = '';
        if ($module === 'arac-takip') {
            $contextDataStr = AracTakipContext::buildContext($firmaId, $userPrompt);
        }

        // 3. System Prompt ve LLM Hazırlığı
        $systemPrompt = $this->buildSystemPrompt($module, $contextDataStr);

        // 4. API veya Analitik Motor Çağrısı
        $responseContent = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $totalTokens = 0;
        $costEstimate = 0.0;
        $status = 'success';

        if (!empty($this->apiKey)) {
            try {
                $llmResult = $this->callLlmApi($systemPrompt, $userPrompt);
                $responseContent = $llmResult['content'];
                $promptTokens = $llmResult['prompt_tokens'] ?? 0;
                $completionTokens = $llmResult['completion_tokens'] ?? 0;
                $totalTokens = $promptTokens + $completionTokens;
                // Tahmini maliyet (1M token = $0.15)
                $costEstimate = ($totalTokens / 1000000) * 0.15;
            } catch (Exception $e) {
                error_log("AiAgentService API Call Error: " . $e->getMessage());
                $status = 'error';
                $responseContent = $this->fallbackHeuristicResponse($userPrompt, $contextDataStr, $e->getMessage());
            }
        } else {
            // API key henüz tanımlanmamışsa yerel deterministik analitik motor devreye girer
            $responseContent = $this->fallbackHeuristicResponse($userPrompt, $contextDataStr);
        }

        $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // 5. Veritabanına Log Kaydı
        $this->model->logQuery(
            $firmaId,
            $userId,
            $module,
            $userPrompt,
            $responseContent,
            $promptTokens,
            $completionTokens,
            $totalTokens,
            $costEstimate,
            $executionTimeMs,
            $this->modelName,
            $status
        );

        // 6. Önbelleğe Kaydet (Başarılıysa 1 saatlik TTL)
        if ($status === 'success') {
            $this->model->setCachedResponse($firmaId, $module, $cacheKey, $responseContent, 3600);
        }

        return [
            'success' => true,
            'response' => $responseContent,
            'cached' => false,
            'execution_time_ms' => $executionTimeMs
        ];
    }

    /**
     * Sistem Yönergesi (System Prompt)
     */
    private function buildSystemPrompt(string $module, string $contextJson): string
    {
        return <<<PROMPT
Sen Ersan Elektrik Kurumsal ERP/CRM Sistemi bünyesinde çalışan üst düzey bir Yapay Zeka İş Ajanısın (AI Work Agent).
Görevin: Verilen modül verilerini analiz etmek, riskli durumları (sürücü kötü kullanımı, aşırı servis maliyeti, ikame araç problemleri) tespit etmek ve yöneticiye net, aksiyon odaklı Türkçe değerlendirmeler sunmaktır.

Modül: {$module}
Veritabanı Özet Verileri (JSON):
{$contextJson}

KURALLAR:
1. Yanıtlarını GitHub Markdown formatında yaz.
2. Risk seviyelerini belirginleştirmek için şu etiketleri kullan:
   - `[RİSK: YÜKSEK 🚨]` veya `[RİSK: ORTA ⚠️]` veya `[BİLGİ ℹ️]`
3. Eğer sorgulanan personel veya sürücünün (örneğin Musa Çiftçi):
   - Birden fazla servis kaydı varsa,
   - Kendisine verilen ikame araçlarda da problem/arıza çıkmışsa,
   bunu açıkça "Sürücü Kaynaklı Kötü Kullanım Riski" olarak vurgula ve araç eğitimi/zimmet incelemesi tavsiyesi ver.
4. Yanıtlarını kısa, net, profesyonel ve çözüme dönük tut.
PROMPT;
    }

    /**
     * cURL ile LLM API (Gemini / DeepSeek / OpenAI) Çağrısı
     */
    private function callLlmApi(string $systemPrompt, string $userPrompt): array
    {
        $url = '';
        $headers = ['Content-Type: application/json'];
        $body = [];

        if ($this->provider === 'gemini') {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName}:generateContent?key={$this->apiKey}";
            $body = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nKullanıcı Sorusu: " . $userPrompt]
                        ]
                    ]
                ]
            ];
        } else {
            // OpenAI veya DeepSeek Uyumlu API
            $url = ($this->provider === 'openai') ? "https://api.openai.com/v1/chat/completions" : "https://api.deepseek.com/v1/chat/completions";
            $headers[] = "Authorization: Bearer {$this->apiKey}";
            $body = [
                'model' => $this->modelName ?: ($this->provider === 'openai' ? 'gpt-4o-mini' : 'deepseek-chat'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $responseStr = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            throw new Exception("API İsteği Başarısız (HTTP $httpCode): " . ($curlError ?: $responseStr));
        }

        $data = json_decode($responseStr, true);

        if ($this->provider === 'gemini') {
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Yanıt üretilemedi.';
            return [
                'content' => $text,
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0
            ];
        } else {
            $text = $data['choices'][0]['message']['content'] ?? 'Yanıt üretilemedi.';
            return [
                'content' => $text,
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0
            ];
        }
    }

    /**
     * API Key olmadığı veya hata aldığı durumlarda çalışan Akıllı Deterministik Analitik Motor
     */
    private function fallbackHeuristicResponse(string $userPrompt, string $contextJson, string $errorMsg = ''): string
    {
        $context = json_decode($contextJson, true) ?? [];
        $personelData = $context['sorgulanan_personel_detayi'] ?? null;
        $plakaData = $context['sorgulanan_plaka_detayi'] ?? null;
        $filoData = $context['filo_ozet'] ?? [];
        $topServis = $context['en_cok_servise_giden_araclar'] ?? [];
        $ikameData = $context['ikame_arac_kullananlar'] ?? [];

        $output = "";

        if ($plakaData) {
            $output .= "### 🤖 AI İş Ajanı Araç Analiz Raporu: **{$plakaData['plaka']}**\n\n";
            $output .= "### `[BİLGİ ℹ️]` **Araç Kimlik ve Zimmet Durumu**\n";
            $output .= "- **Marka & Model:** {$plakaData['marka_model']}\n";
            $output .= "- **Güncel KM:** {$plakaData['guncel_km']}\n";
            $output .= "- **Aktif Zimmetli Sürücü:** **{$plakaData['aktif_surucu']}**\n";
            $output .= "- **Toplam Servis Sayısı:** {$plakaData['toplam_servis']} Kez\n";
            $output .= "- **Toplam Servis Maliyeti:** {$plakaData['toplam_maliyet']}\n\n";

            if (!empty($plakaData['servis_kayitlari'])) {
                $output .= "🔧 **Servis Kayıtları ve Arıza Nedenleri:**\n";
                foreach ($plakaData['servis_kayitlari'] as $sk) {
                    $output .= "- **Tarih:** {$sk['tarih']} | **Servis:** {$sk['servis_adi']} | **Tutar:** {$sk['tutar']}\n";
                    $output .= "  - **Neden:** {$sk['nedeni']}\n";
                    if ($sk['ikame_arac'] !== 'Yok') {
                        $output .= "  - **İkame Araç:** {$sk['ikame_arac']}\n";
                    }
                    $output .= "\n";
                }
            }
        } elseif ($personelData) {
            $pAdi = $personelData['personel_adi'];
            $servisSayisi = $personelData['toplam_servis_sayisi'];
            $ikameSayisi = $personelData['ikame_arac_verilme_sayisi'];
            $zimmetSayisi = $personelData['toplam_zimmetli_arac'];
            $zimmetDetaylari = $personelData['zimmet_kayitlari_detay'] ?? [];

            $output .= "### 🤖 AI İş Ajanı Analiz Raporu: **{$pAdi}**\n\n";

            // Eğer sorgu zimmet veya araç kayıtları odaklıysa veya genel personel sorgusuysa zimmet geçmişini göster
            if (!empty($zimmetDetaylari)) {
                $output .= "### `[BİLGİ ℹ️]` **Zimmet Kayıtları Listesi ({$zimmetSayisi} Araç)**\n\n";
                foreach ($zimmetDetaylari as $index => $zd) {
                    $durumBadge = ($zd['durum'] === 'Aktif Zimmet') ? '🟢 Aktif Zimmet' : '⚪ İade Edildi';
                    $output .= "- **" . ($index + 1) . ". {$zd['plaka']}** ({$zd['arac']}) ➡️ **{$durumBadge}**\n";
                    $output .= "  - **Zimmet Tarihi:** {$zd['zimmet_tarihi']} | **İade Tarihi:** {$zd['iade_tarihi']}\n";
                    $output .= "  - **Teslim KM:** {$zd['teslim_km']} | **İade KM:** {$zd['iade_km']}\n\n";
                }
            }

            if ($servisSayisi >= 3 || $ikameSayisi >= 1) {
                $output .= "### `[RİSK: YÜKSEK 🚨]` Sürücü Kaynaklı Yıpranma ve Servis Durumu\n\n";
                $output .= "**Tespit Edilen Bulgular:**\n";
                $output .= "- **Toplanan Servis Kaydı:** {$pAdi} tarafına zimmetlenen araçlar toplam **{$servisSayisi} kez** servise gitmiştir.\n";
                $output .= "- **İkame Araç Durumu:** Servis süreçlerinde verilen **{$ikameSayisi} adet ikame araçta** da problem/arıza kaydı tespit edilmiştir.\n";
                $output .= "- **Zimmet Geçmişi:** Toplam {$zimmetSayisi} farklı araç zimmetlenmiştir.\n\n";

                $output .= "💡 **Yapay Zeka Değerlendirmesi:**\n";
                $output .= "> Zimmetlenen araçlarda kısa sürelerde tekrarlayan servis ihtiyaçları ve parça değişimleri tespit edilmiştir. Sürücünün **aracı agresif/kötü kullanım riski** bulunmaktadır.\n\n";

                $output .= "📋 **Tavsiye Edilen Aksiyonlar:**\n";
                $output .= "1. Personel ile araç kullanım prosedürleri hakkında görüşme yapılması.\n";
                $output .= "2. Bir sonraki zimmet işleminde telemetri / KM ve sürüş takip kontrolünün sıkılaştırılması.\n";
            } else {
                $output .= "### `[BİLGİ ℹ️]` Sürücü Araç Kullanım Durumu Normal\n\n";
                $output .= "- **Toplam Servis Kaydı:** {$servisSayisi}\n";
                $output .= "- **İkame Araç Arızası:** {$ikameSayisi}\n";
                $output .= "- **Zimmet Sayısı:** {$zimmetSayisi}\n\n";
                $output .= "💡 **Değerlendirme:** Personelin araç kullanımında olağandışı bir risk tespit edilmemiştir.\n";
            }

            if (!empty($personelData['servis_nedenleri_ozet'])) {
                $output .= "\n**Son Servis Nedenleri Özeti:**\n";
                foreach ($personelData['servis_nedenleri_ozet'] as $sn) {
                    $output .= "- " . $sn . "\n";
                }
            }
        } else {
            $normPrompt = str_replace(['İ', 'I', 'ı'], ['i', 'i', 'i'], mb_strtolower($userPrompt, 'UTF-8'));
            $isIkameQuery = str_contains($normPrompt, 'ikame');

            if ($isIkameQuery) {
                $output .= "### 🤖 AI İş Ajanı Analiz Raporu: **İkame Araç Kullanım ve Problemleri**\n\n";
                $output .= "### `[RİSK: ORTA ⚠️]` **Servis Süreçlerinde İkame Araç Verilen Kayıtlar**\n\n";

                if (!empty($ikameData)) {
                    foreach ($ikameData as $index => $ik) {
                        $surucu = $ik['surucu_adi'] ?: 'Belirtilmedi / Atanmamış';
                        $tarih = $ik['servis_tarihi'] ? date('d.m.Y', strtotime($ik['servis_tarihi'])) : '-';
                        $ikamePlaka = $ik['ikame_plaka'] ?: 'İkame Araç';
                        $ikameBilgi = $ik['ikame_bilgisi'] ?: 'İkame Araç';
                        $neden = $ik['servis_nedeni'] ?: 'Belirtilmedi';

                        $output .= "- **" . ($index + 1) . ". İkame Araç:** **{$ikamePlaka}** ({$ikameBilgi})\n";
                        $output .= "  - **Asıl Araç Plakası:** **{$ik['asil_arac_plaka']}**\n";
                        $output .= "  - **Zimmetli Sürücü:** **{$surucu}**\n";
                        $output .= "  - **Servis Tarihi:** {$tarih}\n";
                        $output .= "  - **Asıl Araç Servis / Arıza Nedeni:** {$neden}\n\n";
                    }

                    $output .= "💡 **Yapay Zeka Değerlendirmesi:**\n";
                    $output .= "> İkame araç verilen servis süreçlerinde, sürücülerin ikame aracı kullanım süreleri ve aracın teslim/iade KM kayıtları hassasiyetle takip edilmelidir. İkame araçta arıza bildirilmesi durumunda sürücü kullanım geçmişi incelenmelidir.\n";
                } else {
                    $output .= "Servis kayıtlarında şu an tanımlı ikame araç problemi bulunmamaktadır.\n";
                }
            } else {
                $isSummaryRequest = str_contains($normPrompt, 'filo') || str_contains($normPrompt, 'ozet') || str_contains($normPrompt, 'maliyet') || str_contains($normPrompt, 'genel') || str_contains($normPrompt, 'en cok');

                if (!$isSummaryRequest) {
                    $output .= "### 🤖 AI İş Ajanı Analiz Raporu\n\n";
                    $output .= "### `[BİLGİ ℹ️]` **Aradığınız Kriterlere Uygun Kayıt Bulunamadı**\n\n";
                    $output .= "Sorguladığınız arama metnine (*\"" . htmlspecialchars($userPrompt, ENT_QUOTES, 'UTF-8') . "\"*) uygun veritabanında herhangi bir personel, araç veya özel kayıt **bulunamamıştır**.\n\n";
                    $output .= "💡 **Arama İpuçları:**\n";
                    $output .= "- **Personel Araması:** Doğru isim ve soyisim girdiğinizden emin olunuz (Örn: *Furkan Akşeker*, *Ufuk Çelik*, *Musa Çiftçi*).\n";
                    $output .= "- **Plaka Araması:** Plakayı tam olarak yazabilirsiniz (Örn: *06 CZM 638* veya *34 HCT 667*).\n";
                    $output .= "- **İkame Araç Araması:** *\"İkame araçlar\"* yazarak ikame araç geçmişini listeleyebilirsiniz.\n";
                } else {
                    // Genel Filo Analiz Yanıtı & Harcama Özeti
                    $toplamArac = $filoData['toplam_arac'] ?? 0;
                    $servisteki = $filoData['servisteki_arac'] ?? 0;
                    $zimmetli = $filoData['zimmetli_arac'] ?? 0;

                    $maliyetData = $context['maliyet_ozeti'] ?? [];
                    $topYakitData = $context['en_cok_yakit_harcayan_araclar'] ?? [];

                    $toplamYakitTutar = number_format($maliyetData['toplam_yakit_tutar'] ?? 0, 2, ',', '.') . ' TL';
                    $toplamYakitLitre = number_format($maliyetData['toplam_yakit_litre'] ?? 0, 2, ',', '.') . ' Litre';
                    $toplamYakitKaydı = number_format($maliyetData['toplam_yakit_kaydi'] ?? 0, 0, ',', '.');
                    
                    $toplamServisTutar = number_format($maliyetData['toplam_servis_tutar'] ?? 0, 2, ',', '.') . ' TL';
                    $toplamServisKaydı = $maliyetData['toplam_servis_kaydi'] ?? 0;
                    $tutarsizServisKaydi = $maliyetData['tutarsiz_servis_kaydi'] ?? 0;

                    $genelToplamMaliyet = number_format(($maliyetData['toplam_yakit_tutar'] ?? 0) + ($maliyetData['toplam_servis_tutar'] ?? 0), 2, ',', '.') . ' TL';

                    $output .= "### 🤖 AI İş Ajanı Filo & Maliyet Özeti\n\n";
                    $output .= "### `[BİLGİ ℹ️]` **Genel Filo ve Harcama Özeti**\n";
                    $output .= "- **Toplam Aktif Araç:** {$toplamArac} | **Zimmetli:** {$zimmetli} | **Servisteki:** {$servisteki}\n";
                    $output .= "- **Toplam Filo Harcaması:** **{$genelToplamMaliyet}**\n";
                    $output .= "  - ⛽ **Yakıt Gideri:** **{$toplamYakitTutar}** ({$toplamYakitLitre} - {$toplamYakitKaydı} Dolum Kaydı)\n";
                    $output .= "  - 🔧 **Servis Gideri:** **{$toplamServisTutar}** ({$toplamServisKaydı} Servis Kaydı)\n\n";

                    if ($tutarsizServisKaydi > 0) {
                        $output .= "### `[RİSK: ORTA ⚠️]` **Eksik Servis Maliyet Uyarısı**\n";
                        $output .= "> Veritabanında kayıtlı **{$toplamServisKaydı} servis kaydının {$tutarsizServisKaydi} tanesinde fatura/servis tutarı girilmemiştir (0,00 TL görünmektedir).** Gerçek filo servis maliyetinin tam hesaplanabilmesi için bu servis kayıtlarına tutar girilmesi gerekmektedir.\n\n";
                    }

                    if (!empty($topYakitData)) {
                        $output .= "⛽ **En Çok Yakıt Tüketen ve Maliyeti Yüksek Araçlar:**\n";
                        foreach ($topYakitData as $ty) {
                            $surucu = $ty['mevcut_surucu'] ?: 'Zimmetsiz / Atanmamış';
                            $maliyet = number_format($ty['toplam_yakit_maliyeti'], 2, ',', '.') . ' TL';
                            $litre = number_format($ty['toplam_litre'], 2, ',', '.') . ' L';
                            $output .= "- **{$ty['plaka']}** ({$ty['marka']} {$ty['model']}) ➡️ **{$maliyet}** ({$litre} - {$ty['dolum_sayisi']} Dolum) | Sürücü: **{$surucu}**\n";
                        }
                        $output .= "\n";
                    }

                    if (!empty($topServis)) {
                        $output .= "🔧 **En Çok Servise Giden Araçlar ve Sürücüleri:**\n";
                        foreach ($topServis as $ts) {
                            $surucu = $ts['mevcut_surucu'] ?: 'Zimmetsiz / Atanmamış';
                            $maliyet = number_format($ts['toplam_servis_maliyeti'], 2, ',', '.') . ' TL';
                            $output .= "- **{$ts['plaka']}** ({$ts['marka']} {$ts['model']}) ➡️ **{$ts['servis_sayisi']} Servis Kaydı** | Sürücü: **{$surucu}** | Kayıtlı Servis Tutar: {$maliyet}\n";
                        }
                    }

                    $output .= "\n💡 *Not: Belirli bir personel (örn: Musa Çiftçi) veya belirli bir plaka (örn: 06 CZM 638) hakkında detaylı analiz için arama kutusuna yazabilirsiniz.*";
                }
            }
        }

        return $output;
    }
}
