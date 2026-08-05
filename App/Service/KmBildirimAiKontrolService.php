<?php
namespace App\Service;

use App\Model\AracKmBildirimModel;
use App\Model\AracKmModel;
use App\Model\AracModel;
use App\Model\SettingsModel;
use App\Model\SystemLogModel;
use Exception;
use PDO;

/**
 * KM bildirim fotoğraflarını yapay zekâ ile doğrular ve yalnızca kesin
 * eşleşmeleri sisteme işler. Belirsiz sonuçlar daima manuel onayda kalır.
 */
class KmBildirimAiKontrolService
{
    private AracKmBildirimModel $bildirimModel;
    private AracKmModel $kmModel;
    private AracModel $aracModel;
    private PDO $db;
    private string $apiKey;
    private string $model;
    private int $firmaId;

    public function __construct()
    {
        $this->bildirimModel = new AracKmBildirimModel();
        $this->kmModel = new AracKmModel();
        $this->aracModel = new AracModel();
        $this->db = $this->bildirimModel->getDb();
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);

        $settings = (new SettingsModel())->getAllSettingsAsKeyValue($this->firmaId ?: null);
        $this->apiKey = trim((string) ($settings['openai_api_key'] ?? ''));
        $this->model = trim((string) ($settings['ai_agent_model'] ?? 'gpt-4o-mini'));
        if (!str_starts_with($this->model, 'gpt-')) {
            $this->model = 'gpt-4o-mini';
        }
        if ($this->apiKey === '') {
            throw new Exception('OpenAI API anahtarı tanımlı değil.');
        }
    }

    public function kontrolEtVeOnayla(array $ids, ?int $onaylayanId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        if (!$ids) {
            throw new Exception('Yapay zekâ ile kontrol edilecek kayıt seçilmedi.');
        }
        if (count($ids) > 20) {
            throw new Exception('Tek seferde en fazla 20 kayıt analiz edilebilir.');
        }

        $sonuclar = [];
        $onaylanan = 0;
        foreach ($ids as $id) {
            try {
                $bildirim = $this->getBildirim($id);
                if (!$bildirim || $bildirim->durum !== 'beklemede') {
                    $sonuclar[] = ['id' => $id, 'durum' => 'atlandi', 'neden' => 'Kayıt bulunamadı veya artık beklemede değil.'];
                    continue;
                }
                if (empty($bildirim->resim_yolu)) {
                    $neden = 'Fotoğraf bulunmuyor.';
                    $this->bildirimModel->saveWithAttr([
                        'id' => $id,
                        'ai_onaylanmama_nedeni' => $neden,
                    ]);
                    $sonuclar[] = ['id' => $id, 'durum' => 'manuel', 'neden' => $neden];
                    continue;
                }

                $resim = $this->resolveImage((string) $bildirim->resim_yolu);
                $analiz = $this->analyzeImage($resim, $bildirim);
                $analiz = $this->refineOdometerReading($resim, $analiz, $bildirim);
                $karar = $this->evaluate($analiz, $bildirim);

                if (!$karar['uygun']) {
                    $neden = implode(' ', $karar['nedenler']);
                    $this->bildirimModel->saveWithAttr([
                        'id' => $id,
                        'ai_onaylanmama_nedeni' => $neden,
                    ]);
                    $sonuclar[] = [
                        'id' => $id,
                        'plaka' => $bildirim->plaka,
                        'durum' => 'manuel',
                        'neden' => $neden,
                        'okunan_km' => $analiz['odometer_km'] ?? null,
                    ];
                    continue;
                }

                $this->approve($bildirim, $onaylayanId);
                $onaylanan++;
                $sonuclar[] = [
                    'id' => $id,
                    'plaka' => $bildirim->plaka,
                    'durum' => 'onaylandi',
                    'neden' => 'Fotoğraftaki KM, plaka ve bildirim türü kayıtla eşleşti.',
                    'okunan_km' => (int) $analiz['odometer_km'],
                ];
            } catch (\Throwable $e) {
                error_log("KM AI kontrol hatası (ID {$id}): " . $e->getMessage());
                try {
                    $this->bildirimModel->saveWithAttr([
                        'id' => $id,
                        'ai_onaylanmama_nedeni' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ex) {
                    // Ignore DB save error if any
                }
                $sonuclar[] = ['id' => $id, 'durum' => 'hata', 'neden' => $e->getMessage()];
            }
        }

        return [
            'toplam' => count($ids),
            'onaylanan' => $onaylanan,
            'manuel' => count($ids) - $onaylanan,
            'sonuclar' => $sonuclar,
        ];
    }

    private function getBildirim(int $id): ?object
    {
        $stmt = $this->db->prepare("SELECT akb.*, a.plaka
            FROM arac_km_bildirimleri akb
            INNER JOIN araclar a ON a.id = akb.arac_id
            WHERE akb.id = ? AND akb.firma_id = ? AND akb.silinme_tarihi IS NULL LIMIT 1");
        $stmt->execute([$id, $this->firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    private function resolveImage(string $relativePath): array
    {
        $root = realpath(dirname(__DIR__, 2));
        $path = realpath($root . '/' . ltrim($relativePath, '/'));
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new Exception('Fotoğraf dosyası sunucuda bulunamadı.');
        }
        $mime = mime_content_type($path) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new Exception('Fotoğraf biçimi yapay zekâ analizi için uygun değil.');
        }
        return ['path' => $path, 'mime' => $mime];
    }

    private function analyzeImage(array $image, object $bildirim): array
    {
        $dataUrl = 'data:' . $image['mime'] . ';base64,' . base64_encode((string) file_get_contents($image['path']));
        $prompt = sprintf(
            "Bu fotoğraf bir araç KM bildiriminin kanıtıdır. Önce gösterge panelindeki toplam odometre ekranını bul; hız, devir, yakıt ve trip sayaçlarını odometreyle karıştırma. Odometredeki tüm haneleri soldan sağa tek tek incele, özellikle düşük kontrastlı veya silik ilk haneyi atlama. odometer_bbox alanında odometre rakamlarını çevreleyen kutuyu görüntünün genişlik ve yüksekliğine göre 0-1000 arası normalize edilmiş x, y, width, height değerleriyle döndür. Konum bulunamazsa null yap. Ayrıca fotoğrafa uygulama tarafından eklenen filigrandaki plaka ile Sabah/Akşam bildirim türünü oku. Beklenen kaydı yalnızca plaka ve tür doğrulaması için kullanacağım: plaka=%s, tür=%s. KM değerini tahmin etme ve bildirilen değerden türetme; yalnızca fotoğrafta gerçekten görülen tüm haneleri döndür. Rakam net değilse odometer_km=null yap. Güven değerlerini 0-100 arasında ver.",
            (string) $bildirim->plaka,
            (string) $bildirim->tur
        );

        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
                ],
            ]],
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'km_fotograf_kontrolu',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'dashboard_visible' => ['type' => 'boolean'],
                            'odometer_km' => ['type' => ['integer', 'null']],
                            'odometer_bbox' => [
                                'anyOf' => [[
                                    'type' => 'object',
                                    'properties' => [
                                        'x' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
                                        'y' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
                                        'width' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                                        'height' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                                    ],
                                    'required' => ['x', 'y', 'width', 'height'],
                                    'additionalProperties' => false,
                                ], ['type' => 'null']],
                            ],
                            'odometer_bbox_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'plate_text' => ['type' => ['string', 'null']],
                            'report_type' => ['type' => ['string', 'null'], 'enum' => ['sabah', 'aksam', null]],
                            'km_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'plate_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'type_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                        'required' => ['dashboard_visible', 'odometer_km', 'odometer_bbox', 'odometer_bbox_confidence', 'plate_text', 'report_type', 'km_confidence', 'plate_confidence', 'type_confidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_tokens' => 300,
        ];

        return $this->sendAiRequest($payload);
    }

    /**
     * İlk okuma belirsiz veya bildirilen KM ile uyumsuzsa modelin bulduğu odometre
     * alanını dinamik olarak büyütüp yeniden okur. İkinci okumaya bildirilen KM
     * verilmez; böylece kullanıcı girişinin modeli yönlendirmesi engellenir.
     */
    private function refineOdometerReading(array $image, array $analiz, object $bildirim): array
    {
        $ilkKm = $analiz['odometer_km'] ?? null;
        $ilkGuven = (int) ($analiz['km_confidence'] ?? 0);
        if ($ilkKm !== null && $ilkGuven >= 90 && (int) $ilkKm === (int) $bildirim->bitis_km) {
            return $analiz;
        }

        $bbox = $analiz['odometer_bbox'] ?? null;
        if (!is_array($bbox) || (int) ($analiz['odometer_bbox_confidence'] ?? 0) < 70) {
            return $analiz;
        }

        $cropDataUrl = $this->createOdometerCropDataUrl($image, $bbox);
        if ($cropDataUrl === null) {
            return $analiz;
        }

        $prompt = 'Bu görüntü, araç gösterge panelindeki toplam odometre alanının otomatik büyütülmüş kırpımıdır. '
            . 'Hız, devir, yakıt, saat ve trip değerlerini dikkate alma. Toplam KM değerindeki bütün haneleri soldan sağa tek tek oku; '
            . 'özellikle sol taraftaki silik veya düşük kontrastlı ilk haneyi atlama. Görüntüde kesin seçilemeyen hane varsa odometer_km=null döndür.';

        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $cropDataUrl, 'detail' => 'high']],
                ],
            ]],
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'odometre_yakin_okuma',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'odometer_km' => ['type' => ['integer', 'null']],
                            'km_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                        'required' => ['odometer_km', 'km_confidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_tokens' => 100,
        ];

        $ikinciAnaliz = $this->sendAiRequest($payload);
        if (($ikinciAnaliz['odometer_km'] ?? null) !== null && (int) ($ikinciAnaliz['km_confidence'] ?? 0) >= 90) {
            $analiz['odometer_km'] = (int) $ikinciAnaliz['odometer_km'];
            $analiz['km_confidence'] = (int) $ikinciAnaliz['km_confidence'];
        }

        return $analiz;
    }

    private function createOdometerCropDataUrl(array $image, array $bbox): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $sourceBytes = file_get_contents($image['path']);
        $source = $sourceBytes !== false ? @imagecreatefromstring($sourceBytes) : false;
        if ($source === false) {
            return null;
        }

        $imageWidth = imagesx($source);
        $imageHeight = imagesy($source);
        $x = min($imageWidth - 1, (int) round(((int) ($bbox['x'] ?? 0) / 1000) * $imageWidth));
        $y = min($imageHeight - 1, (int) round(((int) ($bbox['y'] ?? 0) / 1000) * $imageHeight));
        $width = (int) round(((int) ($bbox['width'] ?? 0) / 1000) * $imageWidth);
        $height = (int) round(((int) ($bbox['height'] ?? 0) / 1000) * $imageHeight);
        if ($width < 10 || $height < 5) {
            imagedestroy($source);
            return null;
        }

        // Model kutusu rakamlara çok sıkı oturabileceğinden dört yönde pay bırak.
        $padX = (int) round($width * 0.35);
        $padY = (int) round($height * 0.75);
        $cropX = max(0, $x - $padX);
        $cropY = max(0, $y - $padY);
        $cropWidth = min($imageWidth - $cropX, $width + (2 * $padX));
        $cropHeight = min($imageHeight - $cropY, $height + (2 * $padY));
        if ($cropWidth < 1 || $cropHeight < 1) {
            imagedestroy($source);
            return null;
        }

        $targetWidth = min(1600, max(800, $cropWidth * 4));
        $targetHeight = max(1, (int) round($cropHeight * ($targetWidth / $cropWidth)));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false || !imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight)) {
            imagedestroy($source);
            if ($target !== false) {
                imagedestroy($target);
            }
            return null;
        }

        if (function_exists('imagefilter')) {
            @imagefilter($target, IMG_FILTER_CONTRAST, -20);
            @imagefilter($target, IMG_FILTER_BRIGHTNESS, 5);
        }

        ob_start();
        imagejpeg($target, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        return is_string($jpeg) && $jpeg !== '' ? 'data:image/jpeg;base64,' . base64_encode($jpeg) : null;
    }

    private function sendAiRequest(array $payload): array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $curlError !== '') {
            throw new Exception('Yapay zekâ servisine ulaşılamadı.');
        }

        $response = json_decode((string) $body, true);
        if ($status >= 400 || isset($response['error'])) {
            error_log('KM AI OpenAI hatası: ' . substr((string) $body, 0, 500));
            throw new Exception($status === 401 ? 'OpenAI API anahtarı geçersiz.' : 'Yapay zekâ servisi analizi kabul etmedi.');
        }
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode((string) $content, true);
        if (!is_array($data)) {
            throw new Exception('Yapay zekâ geçerli analiz sonucu döndürmedi.');
        }
        return $data;
    }

    private function evaluate(array $analiz, object $bildirim): array
    {
        $nedenler = [];
        if ($bildirim->tarih === date('Y-m-d') && $bildirim->tur === 'aksam' && (int) date('G') < 13) {
            $nedenler[] = 'Bugünkü akşam bildirimi saat 13:00 öncesinde otomatik onaylanamaz.';
        }

        $oncekiKm = $this->bildirimModel->getLastKm(
            (int) $bildirim->arac_id,
            (string) $bildirim->tarih,
            (string) $bildirim->tur,
            (int) $bildirim->id
        );
        if ((int) $bildirim->bitis_km < $oncekiKm) {
            $nedenler[] = "Bildirilen KM önceki sistem KM'sinden ({$oncekiKm}) düşük.";
        }

        if (empty($analiz['dashboard_visible'])) {
            $nedenler[] = 'Gösterge paneli net görünmüyor.';
        }
        if ($analiz['odometer_km'] === null || (int) ($analiz['km_confidence'] ?? 0) < 90) {
            $nedenler[] = 'KM değeri yeterli güvenle okunamadı.';
        } elseif ((int) $analiz['odometer_km'] !== (int) $bildirim->bitis_km) {
            $okunanKmFmt = number_format((int) $analiz['odometer_km'], 0, ',', '.');
            $bildirilenKmFmt = number_format((int) $bildirim->bitis_km, 0, ',', '.');
            $nedenler[] = "Okunan KM ({$okunanKmFmt} KM) bildirilen KM ({$bildirilenKmFmt} KM) ile eşleşmiyor.";
        }

        $okunanPlaka = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($analiz['plate_text'] ?? '')));
        $beklenenPlaka = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $bildirim->plaka));
        if ((int) ($analiz['plate_confidence'] ?? 0) < 80 || $okunanPlaka === '' || $okunanPlaka !== $beklenenPlaka) {
            $nedenler[] = 'Filigrandaki plaka kayıtla güvenli biçimde eşleşmedi.';
        }
        if ((int) ($analiz['type_confidence'] ?? 0) < 80 || ($analiz['report_type'] ?? null) !== $bildirim->tur) {
            $nedenler[] = 'Filigrandaki bildirim türü kayıtla güvenli biçimde eşleşmedi.';
        }
        return ['uygun' => !$nedenler, 'nedenler' => $nedenler];
    }

    private function approve(object $bildirim, ?int $onaylayanId): void
    {
        $aracId = (int) $bildirim->arac_id;
        $km = (int) $bildirim->bitis_km;
        $mevcut = $this->kmModel->kayitVarMi($aracId, $bildirim->tarih, null, true);
        if ($bildirim->tur === 'sabah') {
            $baslangic = $km;
            $bitis = $mevcut ? (int) $mevcut->bitis_km : 0;
        } else {
            $baslangic = $mevcut ? (int) $mevcut->baslangic_km : 0;
            if (!$mevcut) {
                $stmt = $this->db->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                $stmt->execute([$aracId, $bildirim->tarih]);
                $baslangic = (int) ($stmt->fetchColumn() ?: 0);
            }
            $bitis = $km;
        }

        $save = [
            'firma_id' => $bildirim->firma_id,
            'arac_id' => $aracId,
            'tarih' => $bildirim->tarih,
            'baslangic_km' => $baslangic,
            'bitis_km' => $bitis,
            'aciklama' => $bildirim->aciklama,
            'olusturan_kullanici_id' => $onaylayanId,
            'silinme_tarihi' => null,
        ];
        if ($mevcut) {
            $save['id'] = $mevcut->id;
        }
        $this->kmModel->saveWithAttr($save);
        $this->aracModel->updateKm($aracId, $km);
        $this->bildirimModel->saveWithAttr([
            'id' => $bildirim->id,
            'durum' => 'onaylandi',
            'onaylanan_km' => $km,
            'onaylayan_id' => $onaylayanId,
            'ai_onay_mi' => 1,
            'ai_onaylanmama_nedeni' => null,
            'onay_tarihi' => date('Y-m-d H:i:s'),
        ]);

        // Otomatik PWA kontrolünde bir yönetici kullanıcı ID'si bulunmaz.
        // system_logs.user_id NULL kabul etmediği için başarılı onayı log hatasıyla
        // başarısız gibi göstermeyelim; yönetici başlatmışsa normal sistem loguna yaz.
        if ($onaylayanId !== null && $onaylayanId > 0) {
            (new SystemLogModel())->logAction(
                $onaylayanId,
                'KM Bildirim Yapay Zeka Onayı',
                "KM Bildirim ID: {$bildirim->id}, Araç ID: {$aracId}, KM: {$km}; fotoğraf eşleşmesiyle otomatik onaylandı.",
                SystemLogModel::LEVEL_IMPORTANT
            );
        } else {
            error_log("KM Bildirim AI otomatik onaylandı: ID {$bildirim->id}, Araç ID {$aracId}, KM {$km}");
        }
    }
}
