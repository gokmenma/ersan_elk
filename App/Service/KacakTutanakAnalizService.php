<?php
namespace App\Service;

use App\Model\KacakKontrolModel;
use App\Model\SettingsModel;
use Exception;
use PDO;

/**
 * KASKİ kaçak/abonesiz tutanaklarını OpenAI ile okuyup alanları çıkartır.
 * Hem masaüstü (views/kacak/api.php) hem de personel PWA tarafından kullanılır.
 */
class KacakTutanakAnalizService
{
    private KacakKontrolModel $model;
    private SettingsModel $settings;

    public function __construct()
    {
        $this->model = new KacakKontrolModel();
        $this->settings = new SettingsModel();
    }

    /**
     * @param array $file   $_FILES elemanı
     * @param string $varsayilanTarih  Tutanakta tarih okunamazsa kullanılacak tarih
     * @param array $personelAdaylari  [['id' => 1, 'name' => '...'], ...]
     * @return array Çıkartılan satırların dizisi
     */
    public function analyze(array $file, string $varsayilanTarih, array $personelAdaylari = []): array
    {
        // Ayarlar firma bazında kaydedilebildiği için global anahtarı doğrudan
        // okumak yerine firma ayarını (yoksa globali) kullan.
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $aiSettings = $this->settings->getAllSettingsAsKeyValue($firmaId > 0 ? $firmaId : null);
        $apiKey = trim((string) ($aiSettings['openai_api_key'] ?? ''));
        $model = trim((string) ($aiSettings['ai_agent_model'] ?? 'gpt-4o-mini'));
        if (empty($apiKey)) {
            throw new Exception('Lütfen Ayarlar sayfasından OpenAI API anahtarını girin.');
        }

        if (!str_starts_with($model, 'gpt-')) {
            $model = 'gpt-4o-mini';
        }

        if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Tutanak dosyası yüklenemedi.');
        }

        $uzanti = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $base64Image = null;
        $mimeType = '';
        $belgeMetni = '';

        if (in_array($uzanti, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $base64Image = base64_encode(file_get_contents($file['tmp_name']));
            $mimeType = 'image/' . ($uzanti === 'jpg' ? 'jpeg' : $uzanti);
        } elseif ($uzanti === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $belgeMetni = $parser->parseFile($file['tmp_name'])->getText();
            } catch (\Throwable $e) {
                error_log('Kaçak tutanak PDF okuma hatası: ' . $e->getMessage());
                throw new Exception('PDF dosyası okunamadı.');
            }
            if (trim($belgeMetni) === '') {
                throw new Exception('PDF dosyasından metin çıkartılamadı. Belge taranmış görsel olabilir, lütfen resim olarak yükleyin.');
            }
        } elseif (in_array($uzanti, ['xls', 'xlsx', 'csv'], true)) {
            $belgeMetni = $this->excelToText($file['tmp_name']);
        } else {
            throw new Exception('Desteklenmeyen dosya türü. Görsel, PDF veya Excel yükleyin.');
        }

        $prompt = $this->buildPrompt($varsayilanTarih, $personelAdaylari, $base64Image !== null, $belgeMetni);

        $userContent = $base64Image !== null
            ? [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . $base64Image]],
            ]
            : $prompt;

        $cevap = $this->callOpenAi($apiKey, $model, $userContent);
        $satirlar = $this->parseResponse($cevap);

        foreach ($satirlar as &$satir) {
            $satir['ilce'] = $this->model->normalizeIlce((string) ($satir['ilce'] ?? $satir['ilçe'] ?? ''));
            $satir['tur'] = in_array($satir['tur'] ?? '', KacakKontrolModel::TURLER, true) ? $satir['tur'] : 'Kaçak';
            $satir['sayi'] = max(1, (int) ($satir['sayi'] ?? 1));
            $satir['tarih'] = $this->normalizeTarih($satir['tarih'] ?? '', $varsayilanTarih);
            $satir['personel_ids'] = $this->filterPersonelIds($satir['personel_ids'] ?? [], $personelAdaylari);
            unset($satir['ilçe']);
        }
        unset($satir);

        return $satirlar;
    }

    /**
     * Analiz promptunda kullanılacak personel aday listesini hazırlar.
     * Adaylar, daha önce fiilen kaçak tutanağında görev almış personelle sınırlandırılır.
     */
    public function getPersonelAdaylari(array $dropdownPersonel): array
    {
        $db = $this->model->getDb();
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);

        $gecmisIds = [];
        $stmt = $db->prepare("SELECT DISTINCT personel_ids FROM kacak_kontrol
                              WHERE firma_id = ? AND personel_ids IS NOT NULL AND personel_ids != '' AND silinme_tarihi IS NULL");
        $stmt->execute([$firmaId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idsStr) {
            foreach (explode(',', (string) $idsStr) as $pid) {
                $pid = (int) trim($pid);
                if ($pid > 0) {
                    $gecmisIds[$pid] = true;
                }
            }
        }

        $adaylar = $dropdownPersonel;
        if (count($gecmisIds) >= 2) {
            $kesisim = array_values(array_filter($dropdownPersonel, static fn($p) => isset($gecmisIds[(int) $p['id']])));
            if (count($kesisim) >= 2) {
                $adaylar = $kesisim;
            }
        }

        $ids = array_map(static fn($p) => (int) $p['id'], $adaylar);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtUnvan = $db->prepare("SELECT id, gorev FROM personel WHERE id IN ($placeholders)");
        $stmtUnvan->execute($ids);
        $unvanMap = [];
        foreach ($stmtUnvan->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $unvanMap[(int) $row['id']] = trim((string) $row['gorev']);
        }

        $sonuc = [];
        foreach ($adaylar as $p) {
            $pid = (int) $p['id'];
            $unvan = $unvanMap[$pid] ?? '';
            $sonuc[] = [
                'id' => $pid,
                'name' => $p['name'],
                'unvan' => $unvan,
                'sef_mi' => stripos($unvan, 'Şef') !== false,
            ];
        }

        return $sonuc;
    }

    private function buildPrompt(string $varsayilanTarih, array $personelAdaylari, bool $gorselMi, string $belgeMetni): string
    {
        $ilceler = implode(', ', KacakKontrolModel::ILCELER);
        $personelJson = json_encode($personelAdaylari, JSON_UNESCAPED_UNICODE);
        $kaynak = $gorselMi ? 'görseldeki' : 'metindeki';

        $turler = implode(', ', KacakKontrolModel::TURLER);

        $prompt = "Aşağıdaki {$kaynak} KASKİ kaçak/abonesiz/usülsüz tutanak verilerinden tarih, ilçe, tür ({$turler}), tutanak no, abone adı, sayaç no, endeks, sayı, açıklama ve görevli personel verilerini ayıklamanı istiyorum.
Verileri kesinlikle geçerli bir JSON dizisi (Array) olarak dön. Ek açıklama, markdown veya kod bloğu (```json gibi) ekleme.

Sistemde kayıtlı ve bu ekranda seçilebilir personel listesi aşağıdadır. SADECE buradaki ID'leri kullan, listede olmayan bir ID asla üretme. 'unvan' kişinin görevini, 'sef_mi' ekip şefi olup olmadığını gösterir:
{$personelJson}

Geçerli ilçeler (sadece bunlardan birini yaz): {$ilceler}

Kritik Kurallar:
1. Tarih: Tutanaktaki fiili tespit/düzenleme tarihini dikkatli oku. Tutanak altındaki 'MEMNU İŞİ YAPAN' veya imza atan görevliler alanının yanındaki el yazısı tarihi öncelikli oku. Doğum/abone tarihlerini tespit tarihi sanma. YYYY-MM-DD formatına çevir. Hiç okunmuyorsa {$varsayilanTarih} kullan ve 'guven.tarih' değerine 30 gibi düşük bir yüzde ver.
2. İlçe: 'İlçesi' kutucuğundaki el yazısını dikkatli oku ve yukarıdaki geçerli ilçe listesiyle birebir eşleştir. Yanlış ilçe eşleştirmekten kaçın (görselde 'Onikişubat' yazıyorsa 'Dulkadiroğlu' yazma). Net değilse en yakın tahmini yaz ve 'guven.ilce' değerine düşük yüzde ver.
3. Personel Eşleştirme (çok sıkı, asla varsayım yapma):
   - Tutanağın altında 'KONTROL EDENLER' / 'Tutanak Düzenleyen Memurlar' alanındaki imza veya parafların baş harflerini tespit et.
   - Ekip şefleri ('sef_mi' true) genellikle sol altta imzalar; bunu sadece ipucu say, kesin kural sayma.
   - Baş harfleri SADECE yukarıdaki listeyle karşılaştır. Listede olmayan birine benzetme yapma, önceki tutanaklardan hatırladığın isimleri kullanma.
   - Birden fazla kişiyle eşleşme ihtimali varsa veya yazı okunaksızsa o kişiyi ekleme ve 'guven.personel_ids' değerine düşük yüzde ver.
   - En fazla 2 personel ID'si ekle. Emin değilsen boş dizi [] dön.

Alanlar:
- tarih (YYYY-MM-DD)
- ilce (yukarıdaki geçerli ilçelerden biri)
- tur (SADECE şunlardan biri: {$turler}. Tutanakta kaçak tespiti/kaçak kullanma geçiyorsa 'Kaçak'; abonesiz kullanım geçiyorsa 'Abonesiz'; usülsüz kullanım/usulsuz geçiyorsa 'Usülsüz' yaz.)
- tutanak_no ('SERİ / A Sıra No' kutusundaki numara)
- abone_adi ('Adı Soyadı' kutusundaki kişi)
- sayac_no ('Sayaç Seri No.' kutusundaki numara)
- endeks ('Sayaç Endeksi' kutusundaki değer)
- sayi (tamsayı, yoksa 1)
- aciklama (açıklama alanı veya durum detayı)
- personel_ids (tespit edilen personel ID dizisi, örn: [12, 15]; eşleşme yoksa [])
- guven (her alan için 0-100 arası güven yüzdesi; anahtarlar: tarih, ilce, tur, tutanak_no, abone_adi, sayac_no, endeks, sayi, aciklama, personel_ids)

";

        return $prompt . ($gorselMi ? 'Görsel analiz edilerek bu alanlar çıkartılmalıdır.' : "Belge Metni:\n" . $belgeMetni);
    }

    private function callOpenAi(string $apiKey, string $model, $userContent): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Sen KASKİ tutanaklarından veri çıkartan profesyonel bir veri analisti asistanısın. Sadece saf JSON dizi formatında çıktı üretirsin.'],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0.1,
            ]),
        ]);

        $sonuc = curl_exec($ch);
        if (curl_errno($ch)) {
            $hata = curl_error($ch);
            curl_close($ch);
            error_log('Kaçak tutanak OpenAI bağlantı hatası: ' . $hata);
            throw new Exception('Yapay zeka servisine ulaşılamadı, lütfen tekrar deneyin.');
        }
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode((string) $sonuc, true);
        if ($httpStatus >= 400 || isset($json['error'])) {
            $errorCode = (string) ($json['error']['code'] ?? '');
            $errorType = (string) ($json['error']['type'] ?? '');
            error_log(sprintf(
                'Kaçak tutanak OpenAI API hatası (HTTP %d, kod: %s, tip: %s): %s',
                $httpStatus,
                $errorCode ?: '-',
                $errorType ?: '-',
                substr((string) ($json['error']['message'] ?? $sonuc), 0, 500)
            ));

            if ($httpStatus === 401 || $errorCode === 'invalid_api_key') {
                throw new Exception('OpenAI API anahtarı geçersiz. Lütfen sistem yöneticisinden Ayarlar > Online Sorgulama Ayarları bölümündeki API anahtarını yenilemesini isteyin.');
            }
            if ($httpStatus === 429 || $errorCode === 'insufficient_quota') {
                throw new Exception('OpenAI kullanım kotası veya istek limiti dolmuş. Lütfen sistem yöneticinizle görüşün.');
            }
            if ($errorCode === 'model_not_found') {
                throw new Exception('Yapay zeka modeli kullanılamıyor. Lütfen sistem yöneticinizin model ayarını kontrol etmesini isteyin.');
            }

            throw new Exception('Yapay zeka servisi isteği kabul etmedi (HTTP ' . $httpStatus . '). Lütfen daha sonra tekrar deneyin.');
        }

        $icerik = $json['choices'][0]['message']['content'] ?? '';
        if ($icerik === '') {
            error_log('Kaçak tutanak OpenAI boş yanıt: ' . substr((string) $sonuc, 0, 500));
            throw new Exception('Yapay zeka geçerli bir sonuç döndürmedi.');
        }

        return $icerik;
    }

    private function parseResponse(string $icerik): array
    {
        $icerik = trim($icerik);
        if (str_starts_with($icerik, '```')) {
            $icerik = trim(preg_replace('/^```(?:json)?|```$/m', '', $icerik));
        }

        $veri = json_decode($icerik, true);
        if (!is_array($veri)) {
            error_log('Kaçak tutanak OpenAI geçersiz JSON: ' . substr($icerik, 0, 500));
            throw new Exception('Yapay zeka veriyi ayıklayamadı. Lütfen dosyayı kontrol edip tekrar deneyin.');
        }

        if (isset($veri['tarih']) || isset($veri['ilce']) || isset($veri['ilçe'])) {
            $veri = [$veri];
        }

        return array_values(array_filter($veri, 'is_array'));
    }

    private function normalizeTarih($tarih, string $varsayilan): string
    {
        $tarih = trim((string) $tarih);
        if ($tarih === '') {
            return $varsayilan;
        }
        $ts = strtotime($tarih);
        return $ts !== false ? date('Y-m-d', $ts) : $varsayilan;
    }

    private function filterPersonelIds($ids, array $adaylar): array
    {
        if (!is_array($ids)) {
            return [];
        }
        $gecerli = array_map(static fn($p) => (int) $p['id'], $adaylar);
        $sonuc = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => in_array($id, $gecerli, true))));
        return array_slice($sonuc, 0, 2);
    }

    private function excelToText(string $path): string
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $satirlar = [];
        foreach ($spreadsheet->getActiveSheet()->toArray() as $idx => $row) {
            $filtreli = array_filter(array_map('trim', array_map('strval', $row)));
            if (!empty($filtreli)) {
                $satirlar[] = 'Satır ' . ($idx + 1) . ': ' . implode(' | ', $filtreli);
            }
        }
        if (empty($satirlar)) {
            throw new Exception('Yüklenen dosya içeriği boş.');
        }
        return implode("\n", $satirlar);
    }
}
