<?php

namespace App\Service;

use App\Helper\RichTextSanitizer;
use App\Model\SettingsModel;
use Exception;

final class EvrakAiTaslakService
{
    public function create(array $file, string $instruction): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new Exception('Yapay zekâya ne yapmak istediğinizi yazınız.');
        }
        if (mb_strlen($instruction, 'UTF-8') > 4000) {
            throw new Exception('Talimat en fazla 4000 karakter olabilir.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new Exception('Gelen evrak dosyasını seçiniz.');
        }
        if ((int) ($file['size'] ?? 0) > 12 * 1024 * 1024) {
            throw new Exception('Gelen evrak en fazla 12 MB olabilir.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new Exception('Yalnızca PDF, JPG, PNG veya WEBP dosyası yükleyebilirsiniz.');
        }

        $settings = (new SettingsModel())->getAllSettingsAsKeyValue((int) ($_SESSION['firma_id'] ?? 0));
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ($_ENV['OPENAI_API_KEY'] ?? '')));
        $model = trim((string) ($settings['ai_agent_model'] ?? ($_ENV['AI_AGENT_MODEL'] ?? 'gpt-4o-mini')));
        if ($apiKey === '') {
            throw new Exception('OpenAI API anahtarı tanımlı değil. Ayarlar bölümünden API anahtarını kaydediniz.');
        }
        if (!str_starts_with($model, 'gpt-')) {
            $model = 'gpt-4o-mini';
        }

        $documentContent = $this->documentContent($file, $mime);
        $prompt = "Kullanıcının vermek istediği cevap veya yapmak istediği işlem:\n{$instruction}\n\n"
            . "Yüklenen gelen evrakı bağımsız olarak incele. Evrakın icra dairesinden geldiğini varsayma; gönderen kurumu, evrak türünü, tarih/sayı bilgilerini, konuyu ve bizden istenen talebi belgeden tespit et. Bu evraka verilecek resmî cevap yazısını hazırla. "
            . "Yazı giriş-gelişme-sonuç mantığında, birbiriyle bağlantılı en az üç ayrı paragraftan oluşmalıdır; ancak metinde 'Giriş', 'Gelişme' ve 'Sonuç' başlıkları bulunmamalıdır. "
            . "GİRİŞ PARAGRAFI zorunlu olarak 'İlgi yazı ile' sözleriyle başlamalıdır. İlgi yazının tarih ve sayısı okunabiliyorsa bunlara değinmeli; gönderen kurumun bizden tam olarak ne talep ettiğini açık, tarafsız ve kısa biçimde açıklamalıdır. "
            . "GELİŞME BÖLÜMÜNDE şirketimiz/kurumumuz kayıtlarındaki mevcut durum, yapılan inceleme, somut bulgular ve kullanıcının verdiği bilgiler açıklanmalıdır. İşlemin hukuki dayanağı varsa ilgili kanun, yönetmelik, sözleşme veya düzenleme ile gerekçeli bağlantı kurulmalıdır. "
            . "Belgede ya da kullanıcı talimatında bulunmayan olay, tarih, tutar, kişi, kayıt veya hukuki dayanak uydurulmamalıdır. Kanun/yönetmelik adı veya madde numarasından emin olunmadığında sahte atıf yapılmamalı; bunun yerine 'ilgili mevzuat hükümleri çerçevesinde' gibi ihtiyatlı bir ifade kullanılmalıdır. "
            . "SONUÇ PARAGRAFINDA kullanıcının vermek istediği cevap, karar veya mesaj tereddüde yer vermeyecek şekilde belirtilmeli; gerekiyorsa yapılacak işlem, sorumlu taraf ve süre açıklanmalıdır. Son cümle yazının niteliğine uygun olarak 'bilgilerinize arz ederiz', 'gereğini rica ederiz' veya hiyerarşik ilişkiye uygun başka bir resmî kapanışla bitmelidir. "
            . "Dil resmî, akademik, hukuki, ölçülü ve gerekçeli olmalıdır. Günlük konuşma dili, aşırı kesinlik, duygusal ifade, tekrar ve gereksiz uzun cümle kullanılmamalıdır. Kullanıcının talimatı ile belge çelişirse bilgi uydurmak yerine çelişki ihtiyatlı biçimde belirtilmelidir. "
            . "MUHATAP ALANLARINI ŞU KESİN KURALLARLA DOLDUR: kurum_adi, cevabın gönderileceği kurum veya kişinin belgede yazan tam resmî adıdır; şehir/ilçe, başkanlık, müdürlük, rektörlük, mahkeme veya daire bilgilerini atlama ve 'İcra Dairesi', 'Belediye' gibi genel bir ada indirgeme. Belge başlığında 'T.C. KOCAELİ İCRA DAİRESİ' yazıyorsa kurum_adi en az 'T.C. KOCAELİ İCRA DAİRESİ' olmalıdır. PDF'de doğrudan muhatap başlığı olarak kullanılacağından, belgede ve kurum adında dil bilgisel olarak uygunsa yönelme ekiyle yaz (örneğin 'T.C. KOCAELİ İCRA DAİRESİNE'). "
            . "muhatap_alt_birim yalnızca belgede kurumdan ayrı ve açıkça yazılmış gerçek daire başkanlığı, müdürlük, fakülte, şube veya servis adıdır. Esas/dosya numarası, evrak türü, makam/unvan ya da tahmin edilen birim değildir. Belgede açık bir alt birim yoksa boş string döndür; 'İcra Müdür Yardımcılığı' gibi bir birim uydurma. "
            . "muhatap_adres yalnızca belgede bulunan fiziksel posta adresidir; kurum adı, şehir adı tek başına, esas numarası, KEP/e-posta ya da alt birim bu alana yazılmaz. Sokak/cadde, bina numarası, ilçe/il gibi gerçek bir posta adresi bulunmuyorsa boş string döndür. Aynı bilgiyi kurum_adi, muhatap_alt_birim ve muhatap_adres alanlarında tekrarlama. "
            . "ilgiler alanına gelen evrakın okunabilen tarih, sayı ve kısa tanımını tek satırda yaz. Birden fazla ilgi varsa her birini ayrı satıra yerleştir. aciklama_html içinde ayrıca 'İlgi:', hitap başlığı, muhatap, imza veya ek listesi oluşturma; bunlar PDF şablonunda ayrıca basılır. Her paragraf için ayrı p etiketi kullan. "
            . "aciklama_html yalnızca p, br, strong, b, em, i, u, ul, ol, li ve table/tr/td/th etiketlerini kullanabilir. "
            . "Şu anahtarlarla JSON nesnesi döndür: konu, kurum_adi, muhatap_alt_birim, muhatap_adres, ilgiler, aciklama_html. "
            . "İlgiler birden fazlaysa her biri ayrı satır olsun.";

        $userContent = [['type' => 'text', 'text' => $prompt]];
        if ($mime === 'application/pdf' && is_string($documentContent)) {
            $userContent[0]['text'] .= "\n\nGelen evrak metni:\n" . $documentContent;
        } elseif ($mime === 'application/pdf') {
            foreach ($documentContent as $imageDataUrl) {
                $userContent[] = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl, 'detail' => 'high']];
            }
        } else {
            $userContent[] = ['type' => 'image_url', 'image_url' => ['url' => $documentContent, 'detail' => 'high']];
        }

        $result = $this->callOpenAi($apiKey, $model, $userContent);
        $institution = trim((string) ($result['kurum_adi'] ?? ''));
        $address = trim((string) ($result['muhatap_adres'] ?? ''));
        $looksLikeInstitution = preg_match('/\b(dairesi|müdürlüğü|başkanlığı|bakanlığı|belediyesi|rektörlüğü|mahkemesi|kurumu|şirketi)\b/iu', $address) === 1;
        $looksLikePostalAddress = preg_match('/\b(cadde(?:si)?|sokak|mah(?:allesi)?|bulvar(?:ı)?|no\s*[:.]?|kat\s*[:.]?|apt\.?|posta kodu)\b|\d+/iu', $address) === 1;
        if ($address !== '' && $looksLikeInstitution && !$looksLikePostalAddress) {
            if (mb_strlen($address, 'UTF-8') > mb_strlen($institution, 'UTF-8')) {
                $institution = $address;
            }
            $address = '';
        }
        return [
            'konu' => trim((string) ($result['konu'] ?? '')),
            'kurum_adi' => $institution,
            'muhatap_alt_birim' => trim((string) ($result['muhatap_alt_birim'] ?? '')),
            'muhatap_adres' => $address,
            'ilgiler' => trim((string) ($result['ilgiler'] ?? '')),
            'aciklama_html' => RichTextSanitizer::sanitize((string) ($result['aciklama_html'] ?? '')),
        ];
    }

    private function documentContent(array $file, string $mime): string|array
    {
        if ($mime !== 'application/pdf') {
            $binary = file_get_contents($file['tmp_name']);
            if ($binary === false) {
                throw new Exception('Gelen evrak okunamadı.');
            }
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        try {
            $text = (new \Smalot\PdfParser\Parser())->parseFile($file['tmp_name'])->getText();
        } catch (\Throwable $e) {
            error_log('Evrak AI PDF okuma hatası: ' . $e->getMessage());
            throw new Exception('PDF dosyası okunamadı.');
        }
        $text = trim($text);
        if ($text === '') {
            return $this->pdfPagesAsImages((string) $file['tmp_name']);
        }
        return mb_substr($text, 0, 60000, 'UTF-8');
    }

    private function pdfPagesAsImages(string $pdfPath): array
    {
        $prefixFile = tempnam(sys_get_temp_dir(), 'evrak_ai_');
        if ($prefixFile === false) {
            throw new Exception('PDF analizi için geçici dosya oluşturulamadı.');
        }
        @unlink($prefixFile);
        // Apache/XAMPP'nin eski libstdc++ kitaplığı sistemdeki Poppler ve
        // ImageMagick ile uyumlu değildir. Araçları sistem kitaplıklarıyla çalıştır.
        $cleanEnvironment = '/usr/bin/env -u LD_LIBRARY_PATH -u LD_PRELOAD ';
        $command = $cleanEnvironment . '/usr/bin/pdftoppm -f 1 -l 3 -jpeg -r 130 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefixFile) . ' 2>&1';
        exec($command, $output, $exitCode);
        $images = glob($prefixFile . '-*.jpg') ?: [];
        if ($images === []) {
            $fallbackImage = $prefixFile . '.jpg';
            $fallbackCommand = $cleanEnvironment . '/usr/bin/convert -density 130 ' . escapeshellarg($pdfPath . '[0]') . ' -quality 85 ' . escapeshellarg($fallbackImage) . ' 2>&1';
            $fallbackOutput = [];
            $fallbackExitCode = 0;
            exec($fallbackCommand, $fallbackOutput, $fallbackExitCode);
            if (is_file($fallbackImage)) {
                $images = [$fallbackImage];
            }
        }
        if ($images === []) {
            error_log('Evrak AI PDF dönüştürme hatası: ' . implode(' | ', array_merge($output, $fallbackOutput ?? [])));
            throw new Exception('Taranmış PDF görüntüye dönüştürülemedi.');
        }

        $dataUrls = [];
        foreach ($images as $image) {
            $binary = file_get_contents($image);
            @unlink($image);
            if ($binary !== false) {
                $dataUrls[] = 'data:image/jpeg;base64,' . base64_encode($binary);
            }
        }
        if ($dataUrls === []) {
            throw new Exception('Taranmış PDF sayfaları okunamadı.');
        }
        return $dataUrls;
    }

    private function callOpenAi(string $apiKey, string $model, array $userContent): array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Sen Türkiye’de resmî, akademik ve hukuki yazışma hazırlayan uzman bir evrak asistanısın. Belge türünü veya gönderen kurumu varsaymaz, yalnızca kaynak belgede ve kullanıcı talimatında bulunan olgularla çalışır, hukuki kaynak ve madde numarası uydurmazsın. Yalnızca geçerli JSON üretirsin.'],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $curlError !== '') {
            throw new Exception('Yapay zekâ servisine ulaşılamadı. Lütfen tekrar deneyiniz.');
        }
        $response = json_decode((string) $body, true);
        if ($status >= 400 || isset($response['error'])) {
            error_log('Evrak AI OpenAI hatası: ' . substr((string) $body, 0, 500));
            throw new Exception($status === 401 ? 'OpenAI API anahtarı geçersiz.' : 'Yapay zekâ servisi isteği kabul etmedi.');
        }
        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        $result = json_decode($content, true);
        if (!is_array($result)) {
            throw new Exception('Yapay zekâ geçerli bir evrak taslağı oluşturamadı.');
        }
        return $result;
    }
}
