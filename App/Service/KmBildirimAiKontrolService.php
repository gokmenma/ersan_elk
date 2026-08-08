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
        // KM ekranındaki silik yedi-segment haneler küçük modelde sıkça atlandığı
        // için bu iş yükü genel AI modelinden ayrı yapılandırılabilir.
        $this->model = trim((string) ($settings['km_ai_model'] ?? 'gpt-5.4'));
        if (!str_starts_with($this->model, 'gpt-')) {
            $this->model = 'gpt-5.4';
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
                $analiz = $this->adjudicateCloseOdometerReading($resim, $analiz, $bildirim);
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
            "Bu fotoğraf bir araç KM bildiriminin kanıtıdır. Önce gösterge panelindeki toplam odometre ekranını bul; hız, devir, yakıt ve trip sayaçlarını odometreyle karıştırma. KRİTİK SEÇİM KURALI: Ekranda birden fazla sayı varsa 'km' yazısının hemen solunda ve AYNI SATIRDA bulunan sayı odometre için birinci adaydır. Saat, sıcaklık, tarih, trip ve alt/üst satırdaki başka sayıları seçme. Örneğin aynı ekranda orta satırda '97125 km', alt satırda '32°C 1801' varsa odometer_km=97125 olmalıdır; 1801 değildir. Görüntü eğikse sayacı zihinsel olarak düz çevir. Odometredeki tüm haneleri soldan sağa tek tek incele, özellikle düşük kontrastlı veya silik ilk haneyi atlama. ÖNEMLİ (Araç Gösterge Kuralları): 1) ÇOĞU BİNEK VE TİCARİ ARAÇTA ODOMETRE DİREKT TAM KİLOMETREYİ GÖSTERİR (örneğin '120928' ekranında tüm haneler tam kilometredir: 120928). 2) Ondalık hane (1/10 km) YALNIZCA en sağdaki hane nokta/virgül ile ayrılmışsa (örn: '8421.6' gösteriminde 8421 tam KM, '.6' ondalıktır) veya özellikle motosikletlerde/mekanik göstergelerde en sağdaki çark farklı renk, zemin ya da ayrı tamburdaysa (örn: siyah çarkların yanında beyaz çark olan '079380' gösteriminde 7938 tam KM, beyaz '0' ondalıktır). Nokta görünmese bile farklı zeminli son tamburu tam KM'ye katma. 3) EĞER NOKTA/VİRGÜL VEYA FARKLI RENKTE ÇARK YOKSA, EKRANDAKİ TÜM HANELERİ TAM KİLOMETRE OLARAK OKU (örn: '120928'). 4) Mavi, yeşil veya siyah-beyaz 7-segment LCD panellerdeki 9, 8, 3, 0, 5, 6 hanelerini ışık yansımasına karşı dikkatle incele; mekanik tamburlarda özellikle 6/8 ayrımını kontrol et ve tüm haneleri soldan sağa eksiksiz oku. 5) Sol baştaki sıfırları (örn: '07938' -> 7938) dikkate alarak temizle. odometer_bbox yalnızca seçtiğin odometre rakamlarını ve yanındaki km birimini çevrelesin; başka satırı çevreleme. Kutuyu görüntünün genişlik ve yüksekliğine göre 0-1000 arası normalize edilmiş x, y, width, height değerleriyle döndür. Konum bulunamazsa null yap. Ayrıca fotoğrafa uygulama tarafından eklenen filigrandaki plaka ile Sabah/Akşam bildirim türünü oku. Beklenen kaydı yalnızca plaka ve tür doğrulaması için kullanacağım: plaka=%s, tür=%s. KM değerini tahmin etme ve bildirilen değerden türetme; yalnızca fotoğrafta gerçekten görülen hanelerin tam kilometre kısmını döndür. Rakam net değilse odometer_km=null yap. Güven değerlerini 0-100 arasında ver.",
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
                            'has_decimal_digit' => ['type' => ['boolean', 'null']],
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
                        'required' => ['dashboard_visible', 'odometer_km', 'has_decimal_digit', 'odometer_bbox', 'odometer_bbox_confidence', 'plate_text', 'report_type', 'km_confidence', 'plate_confidence', 'type_confidence'],
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
        $bildirilenKm = (int) $bildirim->bitis_km;

        if ($ilkKm !== null && $ilkGuven >= 90 && $this->isKmMatching(
            (int) $ilkKm,
            $bildirilenKm,
            ($analiz['has_decimal_digit'] ?? null) === true
        )) {
            return $analiz;
        }

        $bbox = $analiz['odometer_bbox'] ?? null;
        $cropDataUrl = null;

        // Bbox varsa önce bbox ile kırpmayı dene
        if (is_array($bbox) && isset($bbox['x'], $bbox['y'], $bbox['width'], $bbox['height'])) {
            $cropDataUrl = $this->createOdometerCropDataUrl($image, $bbox);
        }

        // Bbox bulunamadıysa veya kırpma başarısız olduysa, gösterge panellerinin ortasındaki varsayılan alanı kırp
        if ($cropDataUrl === null) {
            $fallbackBbox = ['x' => 250, 'y' => 150, 'width' => 500, 'height' => 500];
            $cropDataUrl = $this->createOdometerCropDataUrl($image, $fallbackBbox);
        }

        if ($cropDataUrl === null) {
            return $analiz;
        }

        $oncekiKm = $this->bildirimModel->getLastKm(
            (int) $bildirim->arac_id,
            (string) $bildirim->tarih,
            (string) $bildirim->tur,
            (int) $bildirim->id
        );
        $tutarlilikUyarisi = $ilkKm !== null && $oncekiKm > 0 && (int) $ilkKm < $oncekiKm
            ? ' İlk okuma araç geçmişindeki son KM değerinden düşüktü; odometre geri gitmeyeceği için solda atlanmış silik hane olup olmadığını özellikle kontrol et.'
            : '';
        $prompt = 'Bu görüntü, araç gösterge panelindeki toplam odometre alanının otomatik büyütülmüş kırpımıdır. '
            . 'Hız, devir, yakıt, saat ve trip değerlerini dikkate alma. Birden fazla sayı varsa "km" biriminin hemen solunda ve aynı satırda bulunan sayıyı seç; alt veya üst satırdaki sayıyı seçme. Toplam KM değerindeki haneleri soldan sağa tek tek oku; '
            . 'özellikle 7-segment / LCD dijital ekranlarda (örneğin mavi/yeşil kadranlarda) 9, 8, 3, 0, 5, 6 rakamlarını; mekanik tamburlarda 6/8 ayrımını dikkatle kontrol et. '
            . 'ÖNEMLİ KURAL: Çoğu araçta tüm haneler tam kilometredir (örn: "120928" 6 haneli tam kilometredir). YALNIZCA nokta/virgül ile ayrılmış ondalık (örn: "8421.6") veya özellikle motosiklet/mekanik göstergelerde farklı renkli, farklı zeminli ya da ayrı tamburdaki son çark (örn: "079380") varsa son haneyi katma. Nokta görünmese bile farklı zeminli son çark onda birdir. Böyle bir ayrım yoksa EKRANDAKİ TÜM HANELERİ TAM KİLOMETRE OLARAK OKU. Görüntü eğikse sayacı zihinsel olarak düz çevir. '
            . 'İlk görsel genel bağlam, ikinci görsel odometrenin büyütülmüş halidir. İki görseli birlikte incele ve görünen her rakam hücresini say. '
            . 'Görüntüde kesin seçilemeyen hane varsa odometer_km=null döndür.'
            . $tutarlilikUyarisi;

        $originalDataUrl = 'data:' . $image['mime'] . ';base64,' . base64_encode((string) file_get_contents($image['path']));

        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $originalDataUrl, 'detail' => 'high']],
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
                            'has_decimal_digit' => ['type' => ['boolean', 'null']],
                            'km_confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                        'required' => ['odometer_km', 'has_decimal_digit', 'km_confidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_tokens' => 100,
        ];

        $ikinciAnaliz = $this->sendAiRequest($payload);
        error_log(sprintf(
            'KM AI yakın okuma: bildirim_id=%d model=%s ilk_km=%s ilk_guven=%d ikinci_km=%s ikinci_guven=%d bbox_guven=%d',
            (int) $bildirim->id,
            $this->model,
            $ilkKm === null ? 'null' : (string) $ilkKm,
            $ilkGuven,
            ($ikinciAnaliz['odometer_km'] ?? null) === null ? 'null' : (string) $ikinciAnaliz['odometer_km'],
            (int) ($ikinciAnaliz['km_confidence'] ?? 0),
            (int) ($analiz['odometer_bbox_confidence'] ?? 0)
        ));
        if (($ikinciAnaliz['odometer_km'] ?? null) !== null && (int) ($ikinciAnaliz['km_confidence'] ?? 0) >= 80) {
            $analiz['odometer_km'] = (int) $ikinciAnaliz['odometer_km'];
            $analiz['has_decimal_digit'] = $ikinciAnaliz['has_decimal_digit'] ?? null;
            $analiz['km_confidence'] = (int) $ikinciAnaliz['km_confidence'];
        }

        return $analiz;
    }

    /**
     * İlk okumalar hatalı satırı seçebilir veya yedi-segment rakamları
     * karıştırabilir. Bildirilen değeri bir aday olarak verip orijinal fotoğrafta
     * gerçekten desteklenip desteklenmediğini son kez sınar. Yalnızca çok yüksek
     * güvenli olumlu sonuç OCR değerini düzeltir.
     */
    private function adjudicateCloseOdometerReading(array $image, array $analiz, object $bildirim): array
    {
        $okunanKm = $analiz['odometer_km'] ?? null;
        $bildirilenKm = (int) $bildirim->bitis_km;
        if ($bildirilenKm <= 0 || ($okunanKm !== null && $this->isKmMatching(
            (int) $okunanKm,
            $bildirilenKm,
            ($analiz['has_decimal_digit'] ?? null) === true
        ))) {
            return $analiz;
        }

        $okunanMetin = $okunanKm === null ? 'okunamadı' : (string) (int) $okunanKm;
        $bildirilenMetin = (string) $bildirilenKm;

        $cropDataUrl = null;
        $bbox = $analiz['odometer_bbox'] ?? null;
        if (is_array($bbox) && isset($bbox['x'], $bbox['y'], $bbox['width'], $bbox['height'])) {
            $cropDataUrl = $this->createOdometerCropDataUrl($image, $bbox);
        }
        if ($cropDataUrl === null) {
            $cropDataUrl = $this->createOdometerCropDataUrl(
                $image,
                ['x' => 250, 'y' => 150, 'width' => 500, 'height' => 500]
            );
        }
        if ($cropDataUrl === null) {
            return $analiz;
        }

        $prompt = sprintf(
            'İlk görsel gösterge panelinin tamamı, ikinci görsel ilk OCR tarafından seçilen alanın kırpımıdır; kırpım yanlış satırı göstermiş olabilir. İlk OCR "%s" okudu; sürücünün bildirdiği aday değer "%s". '
            . 'Bildirilen değeri doğru varsayma. Önce orijinal görselde "km" yazısını bul ve hemen solunda AYNI SATIRDA bulunan sayı dizisini seç. Saat, sıcaklık, tarih, trip ve başka satırdaki sayıları kullanma. Ekrandaki rakam hücrelerini soldan sağa say ve her hücrenin yanan/sönük segmentlerini tek tek incele. '
            . 'Özellikle 1/7, 2/3, 6/8 ve yansıma yüzünden iki kez görülmüş olabilecek bitişik haneleri kontrol et. '
            . 'Motosikletlerde ve mekanik tamburlu sayaçlarda en sağdaki farklı renkli/zeminli hane onda bir KM olabilir; '
            . 'nokta görünmese bile farklı renk veya ayrı tambur varsa bu son haneyi tam kilometreye katma. Görüntü eğikse sayacı zihinsel olarak düz çevir. '
            . 'candidate_exact yalnızca görüntüdeki tüm hücreler eksiksiz olarak "%s" değerini destekliyorsa true olsun; '
            . 'tek bir hane dahi belirsizse false döndür. confidence bu adayın görsel kanıtına ilişkin 0-100 güven olsun.',
            $okunanMetin,
            $bildirilenMetin,
            $bildirilenMetin
        );
        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . base64_encode((string) file_get_contents($image['path'])), 'detail' => 'high']],
                    ['type' => 'image_url', 'image_url' => ['url' => $cropDataUrl, 'detail' => 'high']],
                ],
            ]],
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'odometre_yakin_aday_hakemligi',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'candidate_exact' => ['type' => 'boolean'],
                            'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                        'required' => ['candidate_exact', 'confidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_tokens' => 60,
        ];

        $hakem = $this->sendAiRequest($payload);
        $adayKesin = !empty($hakem['candidate_exact']) && (int) ($hakem['confidence'] ?? 0) >= 95;
        error_log(sprintf(
            'KM AI yakın aday hakemliği: bildirim_id=%d ilk_okuma=%s aday=%s sonuc=%s guven=%d',
            (int) $bildirim->id,
            $okunanMetin,
            $bildirilenMetin,
            $adayKesin ? 'kabul' : 'ret',
            (int) ($hakem['confidence'] ?? 0)
        ));
        if ($adayKesin) {
            $analiz['odometer_km'] = $bildirilenKm;
            $analiz['km_confidence'] = (int) $hakem['confidence'];
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

        ob_start();
        imagejpeg($target, null, 95);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        return is_string($jpeg) && $jpeg !== '' ? 'data:image/jpeg;base64,' . base64_encode($jpeg) : null;
    }

    private function sendAiRequest(array $payload): array
    {
        // GPT-5 ailesinde Chat Completions çıktı sınırı max_completion_tokens
        // alanıyla verilir ve varsayılan reasoning=none kullanımında temperature
        // parametresine ihtiyaç yoktur.
        if (str_starts_with((string) ($payload['model'] ?? ''), 'gpt-5')) {
            if (isset($payload['max_tokens'])) {
                $payload['max_completion_tokens'] = $payload['max_tokens'];
                unset($payload['max_tokens']);
            }
            unset($payload['temperature']);
        }

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

    /**
     * AI tarafından okunan KM ile bildirilen KM değerinin eşleşip eşleşmediğini kontrol eder.
     * 1) Birebir eşleşme (örn: okunan 34507 === bildirilen 34507)
     * 2) Görsel kanıt varsa AI en sağdaki tek ondalık haneyi katmışsa
     * 3) Görsel kanıt varsa personel en sağdaki tek ondalık haneyi forma yazmışsa
     */
    private function isKmMatching(?int $okunanKm, int $bildirilenKm, bool $ondalikHaneKaniti = false): bool
    {
        if ($okunanKm === null || $okunanKm <= 0 || $bildirilenKm <= 0) {
            return false;
        }

        // Birebir eşleşme (örn: 34507 === 34507)
        if ($okunanKm === $bildirilenKm) {
            return true;
        }

        // On kat ilişkisi yalnızca fotoğrafta en sağdaki TEK hanenin nokta/virgül
        // veya farklı renk/zeminle ayrıldığı açıkça tespit edildiyse geçerlidir.
        if (!$ondalikHaneKaniti) {
            return false;
        }

        // AI en sağdaki tek ondalık haneyi katmışsa (okunan: 345071, bildirilen: 34507)
        $okunanTam = (int) floor($okunanKm / 10);
        if ($okunanTam === $bildirilenKm) {
            return true;
        }

        // Personel formda göstergedeki ondalık haneyi de yazıp 10 katı girmişse (okunan: 34507, bildirilen: 345071)
        $bildirilenTam = (int) floor($bildirilenKm / 10);
        if ($bildirilenTam === $okunanKm) {
            return true;
        }

        return false;
    }

    private function evaluate(array &$analiz, object $bildirim): array
    {
        $nedenler = [];
        if ($bildirim->tarih === date('Y-m-d') && $bildirim->tur === 'aksam' && (int) date('G') < 13) {
            $nedenler[] = 'Bugünkü akşam bildirimi saat 13:00 öncesinde otomatik onaylanamaz.';
        }

        $okunanKm = $analiz['odometer_km'] !== null ? (int) $analiz['odometer_km'] : null;
        $bildirilenKm = (int) $bildirim->bitis_km;

        // Personel 10 katı girmişse (örneğin göstergedeki 34507.1 değerini forma 345071 yazmışsa, AI ise doğru 34507 okumuşsa):
        if ($okunanKm !== null
            && $bildirilenKm > 0
            && ($analiz['has_decimal_digit'] ?? null) === true) {
            $bildirilenTam = (int) floor($bildirilenKm / 10);
            if ($bildirilenTam === $okunanKm && $bildirilenKm > $okunanKm) {
                // Bildirim objesindeki bitis_km değerini gerçek tam KM ile düzelt
                $bildirim->bitis_km = $okunanKm;
                $bildirilenKm = $okunanKm;
                try {
                    $this->bildirimModel->saveWithAttr([
                        'id' => $bildirim->id,
                        'bitis_km' => $okunanKm,
                    ]);
                } catch (\Throwable $ex) {
                    // DB kaydı güncelleme hatası yutulur
                }
            }
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

        if ($okunanKm === null || (int) ($analiz['km_confidence'] ?? 0) < 90) {
            $nedenler[] = 'KM değeri yeterli güvenle okunamadı.';
        } elseif (!$this->isKmMatching(
            $okunanKm,
            $bildirilenKm,
            ($analiz['has_decimal_digit'] ?? null) === true
        )) {
            $okunanKmFmt = number_format($okunanKm, 0, ',', '.');
            $bildirilenKmFmt = number_format($bildirilenKm, 0, ',', '.');
            $nedenler[] = "Okunan KM ({$okunanKmFmt} KM) bildirilen KM ({$bildirilenKmFmt} KM) ile eşleşmiyor.";
        } else {
            // Eşleşme sağlandı. Eğer AI okuması 10 katıysa (örn okunan 345071, bildirilen 34507),
            // okunan KM değerini bildirilen tam KM (34507) olarak eşitle.
            $analiz['odometer_km'] = $bildirilenKm;
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
