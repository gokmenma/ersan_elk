<?php

namespace App\Service;

use App\Model\SettingsModel;
use Exception;

final class AiBelgeOkuyucuService
{
    private const IZINLI_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function dogrula(array $file, int $maxMb = 12): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new Exception('Belge dosyasını seçiniz.');
        }
        if ((int) ($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            throw new Exception('Belge en fazla ' . $maxMb . ' MB olabilir.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, self::IZINLI_MIME, true)) {
            throw new Exception('Yalnızca PDF, JPG, PNG veya WEBP dosyası yükleyebilirsiniz.');
        }
        return $mime;
    }

    public function ayarlar(): array
    {
        $settings = (new SettingsModel())->getAllSettingsAsKeyValue((int) ($_SESSION['firma_id'] ?? 0));
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ($_ENV['OPENAI_API_KEY'] ?? '')));
        $model = trim((string) ($settings['ai_agent_model'] ?? ($_ENV['AI_AGENT_MODEL'] ?? 'gpt-4o-mini')));
        if ($apiKey === '') {
            throw new Exception('OpenAI API anahtarı tanımlı değil. Ayarlar bölümünden API anahtarını kaydediniz.');
        }
        if (!str_starts_with($model, 'gpt-')) {
            $model = 'gpt-4o-mini';
        }
        return [$apiKey, $model];
    }

    public function kullaniciIcerigi(string $prompt, array $file, string $mime): array
    {
        $belge = $this->belgeIcerigi($file, $mime);
        $icerik = [['type' => 'text', 'text' => $prompt]];

        if ($mime === 'application/pdf' && is_string($belge)) {
            $icerik[0]['text'] .= "\n\nBelge metni:\n" . $belge;
            return $icerik;
        }
        if ($mime === 'application/pdf') {
            foreach ($belge as $imageDataUrl) {
                $icerik[] = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl, 'detail' => 'high']];
            }
            return $icerik;
        }

        $icerik[] = ['type' => 'image_url', 'image_url' => ['url' => $belge, 'detail' => 'high']];
        return $icerik;
    }

    public function belgeIcerigi(array $file, string $mime): string|array
    {
        if ($mime !== 'application/pdf') {
            $binary = file_get_contents($file['tmp_name']);
            if ($binary === false) {
                throw new Exception('Belge okunamadı.');
            }
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        try {
            $text = (new \Smalot\PdfParser\Parser())->parseFile($file['tmp_name'])->getText();
        } catch (\Throwable $e) {
            error_log('AI belge PDF okuma hatası: ' . $e->getMessage());
            throw new Exception('PDF dosyası okunamadı.');
        }
        $text = trim($text);
        if ($text === '') {
            return $this->pdfSayfalariniGoruntuyeCevir((string) $file['tmp_name']);
        }
        return mb_substr($text, 0, 60000, 'UTF-8');
    }

    public function jsonIste(string $apiKey, string $model, string $sistemMesaji, array $kullaniciIcerigi, float $temperature = 0.2): array
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
                    ['role' => 'system', 'content' => $sistemMesaji],
                    ['role' => 'user', 'content' => $kullaniciIcerigi],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => $temperature,
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
            error_log('AI belge OpenAI hatası: ' . substr((string) $body, 0, 500));
            throw new Exception($status === 401 ? 'OpenAI API anahtarı geçersiz.' : 'Yapay zekâ servisi isteği kabul etmedi.');
        }
        $result = json_decode((string) ($response['choices'][0]['message']['content'] ?? ''), true);
        if (!is_array($result)) {
            throw new Exception('Yapay zekâ geçerli bir sonuç üretemedi.');
        }
        return $result;
    }

    private function pdfSayfalariniGoruntuyeCevir(string $pdfPath): array
    {
        $prefixFile = tempnam(sys_get_temp_dir(), 'ai_belge_');
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
            error_log('AI belge PDF dönüştürme hatası: ' . implode(' | ', array_merge($output, $fallbackOutput ?? [])));
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
}
