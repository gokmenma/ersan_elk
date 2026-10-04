<?php
namespace App\Service;

use App\Model\EInvoiceModel;
use App\Model\EInvoiceSettingsModel;
use App\Model\FirmaModel;
use App\Service\EdmSoapClient;
use App\Service\UblGeneratorService;
use Exception;

class EInvoiceService
{
    private EInvoiceModel $invoiceModel;
    private EInvoiceSettingsModel $settingsModel;
    private UblGeneratorService $ublService;
    private \Closure $clientFactory;
    private ?string $storageRoot;

    public function __construct(?EInvoiceModel $invoiceModel = null, ?EInvoiceSettingsModel $settingsModel = null, ?\Closure $clientFactory = null, ?string $storageRoot = null)
    {
        $this->invoiceModel = $invoiceModel ?? new EInvoiceModel();
        $this->settingsModel = $settingsModel ?? new EInvoiceSettingsModel();
        $this->ublService = new UblGeneratorService();
        $this->storageRoot = $storageRoot;
        $this->clientFactory = $clientFactory ?? static fn(int $firm) => new EdmSoapClient($firm);
    }

    private function client(int $firmId): EdmSoapClient { return ($this->clientFactory)($firmId); }

    public function checkTaxpayer(int $firmId, string $vkn): array
    {
        return $this->client($firmId)->checkUser($vkn);
    }

    public function createDraft(int $firmId, array $header, array $lines, int $userId): array
    {
        (new InvoiceValidationService())->validateDraft($header, $lines);
        // Request payloads cannot create incoming or already-sent invoices.
        $header['yon'] = 'GIDEN'; $header['entegrator_durum_kodu'] = 'TASLAK';
        unset($header['ettn'], $header['fatura_no'], $header['kaynak_xml']);
        $id = $this->invoiceModel->createInvoice($firmId, $header, $lines, $userId);
        return ['success' => $id !== null, 'invoice_id' => $id, 'message' => $id ? 'Fatura taslağı kaydedildi.' : 'Fatura taslağı kaydedilemedi.'];
    }

    public function updateDraft(int $invoiceId, int $firmId, array $header, array $lines, int $userId): array
    {
        (new InvoiceValidationService())->validateDraft($header, $lines);
        $ok = $this->invoiceModel->updateInvoice($invoiceId, $firmId, $header, $lines, $userId);
        return ['success' => $ok, 'invoice_id' => $invoiceId, 'message' => $ok ? 'Fatura taslağı güncellendi.' : 'Fatura güncellenemedi; yalnız yerel taslaklar düzenlenebilir.'];
    }

    public function supplier(int $firmId): array
    {
        $firma = (new FirmaModel())->getFirma($firmId);
        $settings = $this->settingsModel->getSettings($firmId) ?: [];

        $adres = trim((string)($firma->adres ?? ''));
        $ilce = trim((string)($firma->ilce ?? ''));
        $il = trim((string)($firma->il ?? ''));

        if (empty($ilce) || empty($il)) {
            if (preg_match('/(?:([a-zA-ZçğıöşüÇĞİÖŞÜ]+)\s*[\/,-]\s*([a-zA-ZçğıöşüÇĞİÖŞÜ]+))\s*$/u', $adres, $m)) {
                if (empty($ilce)) $ilce = trim($m[1]);
                if (empty($il)) $il = trim($m[2]);
            }
        }
        if (empty($ilce)) $ilce = 'İskenderun';
        if (empty($il)) $il = 'Hatay';

        $vkn = trim((string)($firma->vergi_no ?? ''));
        if (empty($vkn) || $vkn === '0' || !preg_match('/^\d{10,11}$/', $vkn)) {
            $apiUser = trim((string)($settings['api_username'] ?? ''));
            if (preg_match('/^\d{10,11}$/', $apiUser)) {
                $vkn = $apiUser;
            } else {
                $vkn = '3230512384';
            }
        }

        return [
            'vkn_tckn' => $vkn,
            'unvan' => trim((string)(($firma->firma_unvan ?? '') ?: ($firma->firma_adi ?? 'ER-SAN ELEKTRİK'))),
            'adres' => $adres ?: 'Savaş Mah. Şehitpamir Cad. Dökmeci İşhanı No:35/6',
            'ilce' => $ilce,
            'il' => $il,
            'ulke' => $firma->ulke ?? 'Türkiye',
            'vergi_dairesi' => (!empty($firma->vergi_dairesi) && $firma->vergi_dairesi !== '0') ? $firma->vergi_dairesi : 'İskenderun',
            'telefon' => (!empty($firma->telefon) && $firma->telefon !== '0') ? $firma->telefon : '03805421390',
            'eposta' => $firma->email ?? ($firma->kep_adresi ?? 'info@ersanelektrik.com.tr'),
            'web' => $firma->web_sitesi ?? 'https://ersanelektrik.com.tr'
        ];
    }

    private function storeXml(int $firmId, string $uuid, string $xml): string
    {
        if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $uuid)) throw new \InvalidArgumentException('ETTN biçimi geçersiz.');
        $root = $this->storageRoot ?? (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2));
        $dir = $root . '/storage/invoices/' . $firmId . '/' . date('Y/m');
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) throw new \RuntimeException('Fatura dosya dizini oluşturulamadı.');
            @chmod($dir, 0777);
        }
        $path = $dir . '/' . $uuid . '.xml';
        if (file_put_contents($path, $xml, LOCK_EX) === false) throw new \RuntimeException('Fatura XML dosyası kaydedilemedi.');
        @chmod($path, 0666);
        return $path;
    }

    private function saveState(int $invoiceId, int $firmId, array $data): void
    {
        if (!$this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, $data)) throw new \RuntimeException('Fatura işlem sonucu kaydedilemedi. Durumu sorgulayın.');
    }

    public function sendInvoice(int $invoiceId, int $firmId): array
    {
        if (!$this->invoiceModel->acquireInvoiceLock($invoiceId, $firmId)) return ['success' => false, 'message' => 'Bu fatura için başka bir işlem sürüyor.'];
        $reserved = false; $attempted = false; $remoteSucceeded = false;
        try {
            $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
            if (!$invoice || $invoice['yon'] !== 'GIDEN' || !in_array($invoice['entegrator_durum_kodu'], ['TASLAK','HATALI'], true) || !empty($invoice['islem_belirsiz'])) throw new \InvalidArgumentException('Fatura gönderilemez. Önce mevcut işlem durumunu sorgulayın.');
            if (!$this->invoiceModel->reserveSend($invoiceId, $firmId)) throw new \RuntimeException('Fatura gönderime ayrılamadı.');
            $reserved = true;
            $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
            $settings = $this->settingsModel->getSettings($firmId);
            if (!$settings) throw new \InvalidArgumentException('Firma EDM ayarları eksik.');
            $supplier = $this->supplier($firmId);
            foreach (['vkn_tckn','unvan','adres','ilce','il'] as $field) if ($supplier[$field] === '') throw new \InvalidArgumentException('Firma bilgisi eksik: ' . $field);
            if (!preg_match('/^\d{10,11}$/D', $supplier['vkn_tckn'])) throw new \InvalidArgumentException('Firma VKN/TCKN bilgisi geçersiz.');
            foreach (['alici_adres','alici_il','alici_ilce'] as $field) if (trim($invoice[$field] ?? '') === '') throw new \InvalidArgumentException('Alıcı bilgisi eksik: ' . $field);
            (new InvoiceValidationService())->validateDraft($invoice, $invoice['satirlar']);
            $client = $this->client($firmId);
            $company = $client->getCompany($supplier['vkn_tckn']);
            $product = $invoice['belge_turu'] === 'EFATURA' ? 'EFATURA' : 'EARSIV';
            if ((int)($company->$product ?? 0) !== 70) throw new \InvalidArgumentException('EDM hesabında bu belge ürünü aktif değil.');
            $senderAlias = trim($settings['varsayilan_gonderici_alias'] ?? '');
            $sellerUser = $client->checkUser($supplier['vkn_tckn']);
            if (!$senderAlias || !in_array($senderAlias, $sellerUser['sender_aliases'], true)) throw new \InvalidArgumentException('Aktif EDM gönderici etiketi seçilmelidir.');
            $receiverUser = $client->checkUser($invoice['alici_vkn_tckn']);
            if ($invoice['belge_turu'] === 'EFATURA') {
                $receiverAlias = $invoice['alici_posta_kutusu'] ?? '';
                if (!$receiverUser['is_einvoice_user'] || !in_array($receiverAlias, $receiverUser['aliases'], true)) throw new \InvalidArgumentException('Alıcının aktif e-Fatura posta kutusu seçilmelidir.');
            } else {
                if ($receiverUser['is_einvoice_user']) throw new \InvalidArgumentException('Alıcı e-Fatura mükellefi; e-Fatura düzenleyin.');
                $receiverAlias = 'defaultpk'; // EDM e-Arşiv routing value, not a fabricated GİB alias.
            }
            $year = (int)substr($invoice['fatura_tarihi'], 0, 4);
            $series = $settings[$invoice['belge_turu'] === 'EFATURA' ? 'efatura_seri' : 'earsiv_seri'] ?? '';
            $found = false;
            foreach (EdmSoapClient::items($company->{'SERIALLİST'} ?? $company->SERIALLIST ?? null) as $serial) {
                if (($serial->SERIAL ?? '') !== $series || (int)($serial->YEAR ?? 0) !== $year || (int)($serial->ACTIVEFLAG ?? 0) !== 1 || ((int)($serial->EARCHIVEFLAG ?? 0) === 1) !== ($invoice['belge_turu'] === 'EARSIV')) continue;
                $found = true;
                $this->settingsModel->reconcileSerial($firmId, $invoice['belge_turu'], $series, $year, (int)($serial->{'LASTSERİAL'} ?? $serial->LASTSERIAL ?? 0));
            }
            if (!$found) throw new \InvalidArgumentException('Fatura yılı ve türü için aktif EDM serisi bulunamadı.');
            if (empty($invoice['fatura_no'])) {
                $invoice['fatura_no'] = $this->settingsModel->generateNextInvoiceNumber($firmId, $invoice['belge_turu'], $series, $year);
                $this->saveState($invoiceId, $firmId, ['fatura_no' => $invoice['fatura_no']]);
            }
            $xml = $this->ublService->generateInvoiceXml($invoice, $supplier, $invoice['satirlar']);
            (new InvoiceValidationService())->validateXml($xml, $invoice, $invoice['satirlar']);
            $path = $this->storeXml($firmId, $invoice['ettn'], $xml);
            $this->saveState($invoiceId, $firmId, ['ubl_xml_path' => $path, 'islem_belirsiz' => 'GONDERIM']);
            $attempted = true;
            $result = $client->sendInvoice($xml, $invoice['alici_vkn_tckn'], $receiverAlias, $supplier['vkn_tckn'], $senderAlias, $invoiceId, $invoice['ettn']);
            if (!$result['success']) throw new EdmOperationException($result['kind'], $result['error']);
            $remoteSucceeded = true;
            $this->saveState($invoiceId, $firmId, ['entegrator_durum_kodu' => 'GONDERILDI', 'edm_referans_no' => $result['guid'], 'islem_belirsiz' => null, 'gib_durum_aciklamasi' => 'EDM’ye iletildi; GİB sonucu bekleniyor.']);
            $this->invoiceModel->recordEvent($invoiceId, $firmId, 'GONDERIM', 'BASARILI', 'EDM gönderimi tamamlandı.');
            return ['success' => true, 'message' => 'Fatura EDM’ye iletildi.', 'fatura_no' => $invoice['fatura_no']];
        } catch (\Throwable $e) {
            $unknown = $remoteSucceeded || ($attempted && (!$e instanceof EdmOperationException || $e->kind === 'unknown'));
            if ($reserved && !$remoteSucceeded) $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, ['entegrator_durum_kodu' => $unknown ? 'BELIRSIZ' : ($attempted ? 'HATALI' : 'TASLAK'), 'islem_belirsiz' => $unknown ? 'GONDERIM' : null, 'gib_durum_aciklamasi' => $unknown ? 'İşlem sonucu belirsiz; durumu sorgulayın.' : $this->publicMessage($e)]);
            return ['success' => false, 'message' => $unknown ? 'Gönderim sonucu kayıttan doğrulanamadı. Durumu sorgulayın; tekrar göndermeyin.' : $this->publicMessage($e)];
        } finally { $this->invoiceModel->releaseInvoiceLock($invoiceId, $firmId); }
    }

    private function publicMessage(\Throwable $e): string
    {
        return $e instanceof \InvalidArgumentException || $e instanceof EdmOperationException ? $e->getMessage() : 'İşlem tamamlanamadı. İşlem geçmişini ve sistem kayıtlarını kontrol edin.';
    }

    public function syncStatus(int $invoiceId, int $firmId): array
    {
        if (!$this->invoiceModel->acquireInvoiceLock($invoiceId, $firmId)) return ['success' => false, 'message' => 'Fatura için başka bir işlem sürüyor.'];
        try {
            $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
            if (!$invoice) throw new \InvalidArgumentException('Fatura bulunamadı.');
            $status = $this->client($firmId)->getInvoiceStatus([$invoice['ettn']])[$invoice['ettn']] ?? null;
            if (!$status) throw new \InvalidArgumentException('EDM’de durum bulunamadı. Belirsiz işlem yeniden gönderime açılmadı.');
            $mapped = InvoiceStatusService::map($status);
            $pending = $invoice['islem_belirsiz'] ?? null;
            $resolved = (!$pending) || ($pending === 'GONDERIM' && in_array($mapped['entegrator_durum_kodu'], ['ONAYLANDI','GONDERILDI'], true)) || ($pending === 'IPTAL' && $mapped['entegrator_durum_kodu'] === 'IPTAL') || (str_starts_with($pending ?? '', 'YANIT:') && isset($mapped['ticari_yanit']));
            if ($resolved) $mapped['islem_belirsiz'] = null;
            elseif ($pending === 'GONDERIM') $mapped['entegrator_durum_kodu'] = 'BELIRSIZ';
            $this->saveState($invoiceId, $firmId, $mapped);
            $this->invoiceModel->recordEvent($invoiceId, $firmId, 'DURUM_SORGUSU', 'BASARILI', $mapped['gib_durum_aciklamasi']);
            return ['success' => true, 'durum_kodu' => $mapped['entegrator_durum_kodu'], 'aciklama' => $mapped['gib_durum_aciklamasi'], 'resolved' => $resolved];
        } catch (\Throwable $e) { return ['success' => false, 'message' => $this->publicMessage($e)]; }
        finally { $this->invoiceModel->releaseInvoiceLock($invoiceId, $firmId); }
    }

    public function renderHtmlPreview(int $invoiceId, int $firmId): string
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            return '<div class="alert alert-danger p-3">Fatura kaydı bulunamadı.</div>';
        }

        $firmaModel = new FirmaModel();
        $firma = $firmaModel->getFirma($firmId);
        $settings = $this->settingsModel->getSettings($firmId);
        if (!empty($invoice['kaynak_xml'])) {
            $source = (new UblReaderService())->read($invoice['kaynak_xml'], $invoice['yon']);
            foreach ($source['customer'] as $key => $value) $invoice['alici_' . $key] = $value;
            $seller = $source['supplier'];
            $firma = (object)['firma_unvan' => $seller['unvan'], 'firma_adi' => $seller['unvan'], 'vergi_no' => $seller['vkn_tckn'], 'adres' => $seller['adres'], 'il' => $seller['il'], 'ilce' => $seller['ilce'], 'ulke' => $seller['ulke'], 'vergi_dairesi' => $seller['vergi_dairesi'], 'email' => $seller['eposta'], 'telefon' => $seller['telefon']];
        }


        // Satıcı Bilgileri (Sadece dolu olan alanlar)
        $saticiUnvan = preg_replace('/\s+/', ' ', trim(!empty($firma->firma_unvan) ? $firma->firma_unvan : (!empty($firma->firma_adi) ? $firma->firma_adi : ($_SESSION['firma_adi'] ?? ''))));
        $saticiAdres = trim((string)($firma->adres ?? ''));
        $saticiIlce = trim((string)($firma->ilce ?? ''));
        $saticiIl = trim((string)($firma->il ?? ''));
        $saticiUlke = trim((string)($firma->ulke ?? ''));
        
        $saticiTel = trim((string)($firma->telefon ?? ''));
        if ($saticiTel === '0') $saticiTel = '';
        $saticiFax = trim((string)($firma->fax ?? ''));
        if ($saticiFax === '0') $saticiFax = '';
        
        $saticiEposta = trim((string)(!empty($firma->email) ? $firma->email : (!empty($firma->kep_adresi) ? $firma->kep_adresi : '')));
        $saticiWeb = trim((string)($firma->web_sitesi ?? ''));
        $saticiVd = trim((string)($firma->vergi_dairesi ?? ''));
        if ($saticiVd === '0' || $saticiVd === '-') $saticiVd = '';
        
        $saticiVkn = trim((string)(!empty($firma->vergi_no) && $firma->vergi_no !== '0' ? $firma->vergi_no : ($settings['api_username'] ?? '')));
        if ($saticiVkn === '0') $saticiVkn = '';
        
        $saticiTicaretSicil = trim((string)($firma->ticaret_sicil_no ?? ''));
        $saticiMersis = trim((string)($firma->mersis_no ?? ''));
        $firmaIban = trim((string)($firma->firma_iban ?? ''));

        // Satıcı HTML Satırları
        $saticiHtmlLines = [];
        if (!empty($saticiAdres)) {
            $saticiHtmlLines[] = '<div>' . htmlspecialchars($saticiAdres, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $saticiLokasyonParts = array_filter([$saticiIlce, $saticiIl, $saticiUlke], function($v) {
            return !empty($v) && $v !== '-' && $v !== '/';
        });
        if (!empty($saticiLokasyonParts)) {
            $saticiHtmlLines[] = '<div>' . htmlspecialchars(implode(' / ', $saticiLokasyonParts), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $saticiTelFax = [];
        if (!empty($saticiTel)) {
            $saticiTelFax[] = 'Tel: ' . htmlspecialchars($saticiTel, ENT_QUOTES, 'UTF-8');
        }
        if (!empty($saticiFax)) {
            $saticiTelFax[] = 'Fax: ' . htmlspecialchars($saticiFax, ENT_QUOTES, 'UTF-8');
        }
        if (!empty($saticiTelFax)) {
            $saticiHtmlLines[] = '<div>' . implode(' ', $saticiTelFax) . '</div>';
        }

        if (!empty($saticiEposta)) {
            $saticiHtmlLines[] = '<div>E-Posta: ' . htmlspecialchars($saticiEposta, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($saticiWeb)) {
            $saticiHtmlLines[] = '<div>Web Sitesi: ' . htmlspecialchars($saticiWeb, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($saticiTicaretSicil)) {
            $saticiHtmlLines[] = '<div>Ticaret Sicil No: ' . htmlspecialchars($saticiTicaretSicil, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($saticiMersis)) {
            $saticiHtmlLines[] = '<div>Mersis No: ' . htmlspecialchars($saticiMersis, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($saticiVd)) {
            $saticiHtmlLines[] = '<div>Vergi Dairesi: ' . htmlspecialchars($saticiVd, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($saticiVkn)) {
            $saticiVknLabel = (strlen($saticiVkn) === 11) ? 'TCKN' : 'VKN';
            $saticiHtmlLines[] = '<div>' . $saticiVknLabel . ': ' . htmlspecialchars($saticiVkn, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        // Alıcı Bilgileri (Sadece dolu olan alanlar)
        $aliciUnvan = trim((string)($invoice['alici_unvan'] ?? ''));
        $aliciAdres = trim((string)($invoice['alici_adres'] ?? ''));
        if ($aliciAdres === '/' || $aliciAdres === '-') $aliciAdres = '';

        $aliciIlce = trim((string)($invoice['alici_ilce'] ?? ''));
        $aliciIl = trim((string)($invoice['alici_il'] ?? ''));
        $aliciUlke = trim((string)($invoice['alici_ulke'] ?? ''));
        
        $aliciLokasyonParts = array_filter([$aliciIlce, $aliciIl, $aliciUlke], function($v) {
            return !empty($v) && $v !== '-' && $v !== '/';
        });
        $aliciLokasyon = implode(' ', $aliciLokasyonParts);

        $aliciWeb = trim((string)($invoice['alici_web'] ?? ''));
        $aliciEposta = trim((string)($invoice['alici_eposta'] ?? ''));
        $aliciTel = trim((string)($invoice['alici_telefon'] ?? ''));
        if ($aliciTel === '0') $aliciTel = '';
        $aliciFax = trim((string)($invoice['alici_fax'] ?? ''));
        if ($aliciFax === '0') $aliciFax = '';
        $aliciVd = trim((string)($invoice['alici_vergi_dairesi'] ?? ''));
        if ($aliciVd === '-' || $aliciVd === '0') $aliciVd = '';
        $aliciVkn = trim((string)($invoice['alici_vkn_tckn'] ?? ''));
        if ($aliciVkn === '0') $aliciVkn = '';

        $aliciHtmlLines = [];
        if (!empty($aliciUnvan)) {
            $aliciHtmlLines[] = '<div style="font-weight: bold;">' . htmlspecialchars($aliciUnvan, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($aliciAdres)) {
            $aliciHtmlLines[] = '<div>' . htmlspecialchars($aliciAdres, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($aliciLokasyon)) {
            $aliciHtmlLines[] = '<div>' . htmlspecialchars($aliciLokasyon, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($aliciWeb)) {
            $aliciHtmlLines[] = '<div>Web Sitesi: ' . htmlspecialchars($aliciWeb, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($aliciEposta)) {
            $aliciHtmlLines[] = '<div>E-Posta: ' . htmlspecialchars($aliciEposta, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $aliciTelFax = [];
        if (!empty($aliciTel)) {
            $aliciTelFax[] = 'Tel: ' . htmlspecialchars($aliciTel, ENT_QUOTES, 'UTF-8');
        }
        if (!empty($aliciFax)) {
            $aliciTelFax[] = 'Fax: ' . htmlspecialchars($aliciFax, ENT_QUOTES, 'UTF-8');
        }
        if (!empty($aliciTelFax)) {
            $aliciHtmlLines[] = '<div>' . implode(' ', $aliciTelFax) . '</div>';
        }

        if (!empty($aliciVd)) {
            $aliciHtmlLines[] = '<div>Vergi Dairesi: ' . htmlspecialchars($aliciVd, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (!empty($aliciVkn)) {
            $aliciVknLabel = (strlen($aliciVkn) === 11) ? 'TCKN' : 'VKN';
            $aliciHtmlLines[] = '<div>' . $aliciVknLabel . ': ' . htmlspecialchars($aliciVkn, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        // Banner SVG Base64
        // Banner PNG / SVG Base64
        $bannerBase64 = '';
        $bannerPathPng = (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/yesili_birlikte_yasatalim.png';
        $bannerPathSvg = (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/fatura_yesil_banner.svg';
        if (file_exists($bannerPathPng)) {
            $bannerBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($bannerPathPng));
        } elseif (file_exists($bannerPathSvg)) {
            $bannerBase64 = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($bannerPathSvg));
        }

        // GİB Logosu Base64
        $gibLogoBase64 = '';
        $gibLogoPath = (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/gib_logo.png';
        if (file_exists($gibLogoPath)) {
            $gibLogoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($gibLogoPath));
        }

        // Firma Logosu Base64 (Öncelik: ersan_fatura_logo.png -> firma logo_yolu -> logo.png)
        $companyLogoBase64 = '';
        $logoCandidates = [
            (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/ersan_fatura_logo.png',
            !empty($firma->logo_yolu) ? ((defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/' . ltrim($firma->logo_yolu, '/')) : '',
            (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/logo.png'
        ];
        foreach ($logoCandidates as $cand) {
            if ($cand && file_exists($cand)) {
                $ext = pathinfo($cand, PATHINFO_EXTENSION);
                $mime = ($ext === 'svg') ? 'image/svg+xml' : 'image/png';
                $companyLogoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($cand));
                break;
            }
        }

        // Kaşe / İmza Base64
        $kaseBase64 = '';
        $kasePath = (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/ersan_kase_imza.png';
        if (file_exists($kasePath)) {
            $kaseBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($kasePath));
        }

        // QR Kod (Karekod) Verisi ve Base64
        $curr = strtoupper($invoice['para_birimi'] ?? 'TRY');
        $currLabel = ($curr === 'TRY' || $curr === 'TL') ? 'TL' : $curr;

        $qrDataParts = [];
        if (!empty($saticiVkn)) $qrDataParts[] = 'VKN:' . $saticiVkn;
        if (!empty($aliciVkn)) $qrDataParts[] = 'AVKN:' . $aliciVkn;
        if (!empty($invoice['fatura_no'])) $qrDataParts[] = 'NO:' . $invoice['fatura_no'];
        if (!empty($invoice['fatura_tarihi'])) $qrDataParts[] = 'TRH:' . date('Y-m-d', strtotime($invoice['fatura_tarihi']));
        $qrDataParts[] = 'TTR:' . number_format((float)$invoice['odenecek_tutar'], 2, '.', '') . ' ' . $curr;
        if (!empty($invoice['ettn'])) $qrDataParts[] = 'ETTN:' . $invoice['ettn'];
        $qrDataString = implode(';', $qrDataParts);

        $qrCodeBase64 = \App\Helper\Helper::generateQrCode($qrDataString, 4);

        // Birim Eşleştirmeleri
        $birimMap = [
            'C62' => 'Adet',
            'NIU' => 'Adet',
            'HUR' => 'Saat',
            'DAY' => 'Gün',
            'MON' => 'Ay',
            'ANN' => 'Yıl',
            'KGM' => 'Kg',
            'GRM' => 'Gr',
            'MTR' => 'Metre',
            'MTK' => 'm²',
            'MTQ' => 'm³',
            'LTR' => 'Litre',
            'PK'  => 'Paket',
            'BX'  => 'Koli',
            'SET' => 'Set',
            'PR'  => 'Çift',
            'TNE' => 'Ton'
        ];

        // KDV Oranları Listesi
        $kdvOranlari = [];
        if (!empty($invoice['satirlar']) && is_array($invoice['satirlar'])) {
            foreach ($invoice['satirlar'] as $line) {
                $kdvOranlari[(int)$line['kdv_orani']] = true;
            }
        }
        $kdvOranText = count($kdvOranlari) === 1 ? '(%' . number_format((float)array_key_first($kdvOranlari), 2, '.', '') . ')' : '';

        // Yazıyla Tutar
        $tutarYaziylaTl = \App\Helper\Helper::numberToWordsTr($invoice['odenecek_tutar'], 'TL', 'KR');
        $tutarYaziylaDoviz = ($curr !== 'TRY' && $curr !== 'TL') ? \App\Helper\Helper::numberToWordsTr($invoice['odenecek_tutar'], $curr, 'CENT') : '';

        $belgeTuruText = ($invoice['belge_turu'] === 'EFATURA') ? 'e-FATURA' : 'e-ARŞİV FATURA';
        $vergilerDahil = bcadd((string)$invoice['kdv_matrahi'], (string)$invoice['hesaplanan_kdv'], 2);

        // Notların temizlenmesi (HTML etiketlerinden ve entitylerden arındırma)
        $notSatirlari = [];
        if (!empty($invoice['notlar'])) {
            $rawNotes = html_entity_decode((string)$invoice['notlar'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // <p>, <br>, <div> satır sonlarına çevir
            $rawNotes = preg_replace('/<\/(p|div)>/i', "\n", $rawNotes);
            $rawNotes = preg_replace('/<br\s*\/?>/i', "\n", $rawNotes);
            $cleanNotes = strip_tags($rawNotes);
            $lines = explode("\n", $cleanNotes);
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln !== '') {
                    $notSatirlari[] = htmlspecialchars($ln, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        $html = '
        <div class="efatura-wrapper" style="background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.35; width: 100%; max-width: 820px; margin: 0 auto; padding: 15px; box-sizing: border-box;">
            
            <style>
                .efatura-wrapper * { box-sizing: border-box; }
                .efatura-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 9.5px; margin-bottom: 0; }
                .efatura-table th { border: 1px solid #000; padding: 4px 2px; text-align: center; font-weight: bold; background: #fff; color: #000; font-size: 9.5px; }
                .efatura-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; font-size: 9.5px; }
                .efatura-meta-table { width: 100%; border-collapse: collapse; border: 1px solid #777; font-size: 10px; }
                .efatura-meta-table td { border: 1px solid #777; padding: 2.5px 5px; }
                .efatura-totals-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 10px; }
                .efatura-totals-table td { border: 1px solid #000; padding: 3px 6px; }
                @media print {
                    @page { size: A4 portrait; margin: 6mm 8mm; }
                    body { background: #fff !important; color: #000 !important; margin: 0 !important; padding: 0 !important; }
                    .efatura-wrapper { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
                }
            </style>

            <!-- 1. EN ÜST BÖLÜM: YEŞİL BANNER (SOLA YASLI) VE KAREKOD (SAĞA YASLI) -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
                <tr>
                    <td style="width: 75%; vertical-align: top; text-align: left; padding: 0;">
                        ' . ($bannerBase64 ? '<img src="' . $bannerBase64 . '" style="width: 100%; max-height: 130px; object-fit: contain; object-position: left center; display: block;" alt="Yeşili Birlikte Yaşatalım">' : '') . '
                    </td>
                    <td style="width: 25%; vertical-align: middle; text-align: right; padding-left: 10px;">
                        ' . ($qrCodeBase64 ? '<img src="' . $qrCodeBase64 . '" style="width: 110px; height: 110px; display: inline-block;" alt="Karekod">' : '') . '
                    </td>
                </tr>
            </table>

            <!-- 2. SATICI BİLGİLERİ (SOL) VE GİB LOGOSU / BELGE TÜRÜ (ORTA) -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                <tr>
                    <!-- SOL: SATICI BİLGİLERİ (ÇİFT ÇİZGİLİ ÇERÇEVE) -->
                    <td style="width: 40%; vertical-align: top; padding-right: 15px;">
                        <div style="border-top: 3px double #000; border-bottom: 3px double #000; padding: 4px 0; min-height: 110px;">
                            <div style="font-weight: bold; font-size: 10.5px; text-transform: uppercase; margin-bottom: 2px; color: #000;">
                                ' . htmlspecialchars($saticiUnvan, ENT_QUOTES, 'UTF-8') . '
                            </div>
                            <div style="font-size: 9.5px; line-height: 1.35; color: #111;">
                                ' . implode("\n", $saticiHtmlLines) . '
                            </div>
                        </div>
                    </td>

                    <!-- ORTA: GİB LOGOSU VE BELGE BAŞLIĞI (SAYFA ORTASI) -->
                    <td style="width: 30%; vertical-align: middle; text-align: center; padding: 0 10px;">
                        ' . ($gibLogoBase64 ? '<img src="' . $gibLogoBase64 . '" style="width: 70px; height: 70px; display: inline-block; margin-bottom: 4px;" alt="GİB Logo"><br>' : '') . '
                        <div style="font-size: 13px; font-weight: bold; letter-spacing: 0.5px; color: #000;">' . $belgeTuruText . '</div>
                    </td>

                    <!-- SAĞ: BOŞ ALAN -->
                    <td style="width: 30%; vertical-align: top;">
                        &nbsp;
                    </td>
                </tr>
            </table>

            <!-- 3. SAYIN (ALICI) VE FATURA METADATA TABLOSU -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                <tr>
                    <!-- ALICI BİLGİLERİ (ÇİFT ÇİZGİ ÜST VE ALT) -->
                    <td style="width: 40%; vertical-align: top; padding-right: 15px;">
                        <div style="border-top: 3px double #000; border-bottom: 3px double #000; padding: 4px 0; min-height: 110px;">
                            <div style="font-weight: bold; font-size: 10.5px; margin-bottom: 1px;">SAYIN</div>
                            <div style="font-size: 9.5px; line-height: 1.35; color: #111;">
                                ' . implode("\n", $aliciHtmlLines) . '
                            </div>
                        </div>
                    </td>

                    <!-- ORTA: BOŞ ALAN -->
                    <td style="width: 30%; vertical-align: top;">
                        &nbsp;
                    </td>

                    <!-- SAĞ: FATURA METADATA TABLOSU -->
                    <td style="width: 30%; vertical-align: bottom;">
                        <table class="efatura-meta-table">
                            <tr>
                                <td style="font-weight: bold; width: 44%;">Özelleştirme No:</td>
                                <td style="width: 56%;">TR1.2</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Senaryo:</td>
                                <td>' . htmlspecialchars($invoice['fatura_profili'] ?? 'TEMELFATURA', ENT_QUOTES, 'UTF-8') . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Fatura Tipi:</td>
                                <td>' . htmlspecialchars($invoice['fatura_tipi'] ?? 'SATIS', ENT_QUOTES, 'UTF-8') . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Fatura No:</td>
                                <td>' . htmlspecialchars($invoice['fatura_no'] ?: 'TASLAK', ENT_QUOTES, 'UTF-8') . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Fatura Tarihi:</td>
                                <td>' . date('d-m-Y', strtotime($invoice['fatura_tarihi'] ?? date('Y-m-d'))) . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Fatura Saati:</td>
                                <td>' . (!empty($invoice['duzenleme_saati']) ? htmlspecialchars($invoice['duzenleme_saati'], ENT_QUOTES, 'UTF-8') : date('H:i:s')) . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- ETTN VE ÇİZGİ -->
            <div style="font-size: 10.5px; margin-top: 6px; margin-bottom: 6px; font-weight: normal;">
                <strong>ETTN:</strong> ' . htmlspecialchars($invoice['ettn'] ?? '', ENT_QUOTES, 'UTF-8') . '
            </div>

            <!-- 4. MAL / HİZMET KALEMLERİ TABLOSU -->
            <table class="efatura-table">
                <thead>
                    <tr>
                        <th style="width: 32px;">Sıra<br>No</th>
                        <th>Mal Hizmet</th>
                        <th style="width: 60px;">Miktar</th>
                        <th style="width: 80px;">Birim Fiyat</th>
                        <th style="width: 55px;">İskonto<br>Oranı</th>
                        <th style="width: 60px;">İskonto<br>Tutarı</th>
                        <th style="width: 55px;">KDV<br>Oranı</th>
                        <th style="width: 80px;">KDV Tutarı</th>
                        <th style="width: 75px;">Diğer Vergiler</th>
                        <th style="width: 85px;">Mal Hizmet<br>Tutarı</th>
                    </tr>
                </thead>
                <tbody>';

        $sira = 1;
        $totalLinesCount = 0;
        if (!empty($invoice['satirlar']) && is_array($invoice['satirlar'])) {
            foreach ($invoice['satirlar'] as $line) {
                $totalLinesCount++;
                $unitName = $birimMap[$line['birim']] ?? $line['birim'];
                $iskontoOran = (float)($line['iskonto_orani'] ?? 0);
                $iskontoTutar = (float)($line['iskonto_tutari'] ?? 0);
                $tevkifatTutar = (float)($line['tevkifat_tutari'] ?? 0);

                $html .= '
                    <tr>
                        <td style="text-align: center;">' . $sira++ . '</td>
                        <td style="text-align: start;">' . htmlspecialchars($line['urun_hizmet_adi'] ?? '', ENT_QUOTES, 'UTF-8') . (!empty($line['istisna_kodu']) ? '<br><small>İstisna: ' . htmlspecialchars($line['istisna_kodu'] . ' — ' . ($line['istisna_aciklama'] ?? ''), ENT_QUOTES, 'UTF-8') . '</small>' : '') . (!empty($line['tevkifat_kodu']) ? '<br><small>Tevkifat: ' . htmlspecialchars($line['tevkifat_kodu'] . ' / %' . ($line['tevkifat_orani'] ?? ''), ENT_QUOTES, 'UTF-8') . '</small>' : '') . '</td>
                        <td style="text-align: center; line-height: 1.15;">' . number_format((float)$line['miktar'], 0, ',', '.') . ' ' . htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: right; line-height: 1.15;">' . number_format((float)$line['birim_fiyat'], 2, ',', '.') . '<br>' . $currLabel . '</td>
                        <td style="text-align: center;">' . ($iskontoOran > 0 ? '%' . number_format($iskontoOran, 2, ',', '.') : '') . '</td>
                        <td style="text-align: right;">' . ($iskontoTutar > 0 ? number_format($iskontoTutar, 2, ',', '.') . '<br>' . $currLabel : '') . '</td>
                        <td style="text-align: center;">%' . number_format((float)$line['kdv_orani'], 2, ',', '.') . '</td>
                        <td style="text-align: right; line-height: 1.15;">' . number_format((float)$line['kdv_tutari'], 2, ',', '.') . '<br>' . $currLabel . '</td>
                        <td style="text-align: right;">' . ($tevkifatTutar > 0 ? number_format($tevkifatTutar, 2, ',', '.') . '<br>' . $currLabel : '') . '</td>
                        <td style="text-align: right; line-height: 1.15;">' . number_format((float)($line['miktar'] * $line['birim_fiyat']), 2, ',', '.') . '<br>' . $currLabel . '</td>
                    </tr>';
            }
        }

        // Fatura Form Yüksekliği İçin Boş Çizgi Satırları (12 satıra tamamla)
        $emptyRowsToDraw = max(0, 12 - $totalLinesCount);
        for ($i = 0; $i < $emptyRowsToDraw; $i++) {
            $html .= '
                <tr>
                    <td style="height: 18px;">&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <!-- 5. ALT TOPLAMLAR BÖLÜMÜ -->
            <table style="width: 100%; border-collapse: collapse; margin-top: -1px; margin-bottom: 6px;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                        <!-- Sol boş alan -->
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 0;">
                        <table class="efatura-totals-table">
                            <tr>
                                <td style="text-align: right; font-weight: bold; width: 60%;">Mal Hizmet Toplam Tutarı</td>
                                <td style="text-align: right; width: 40%;">' . number_format((float)$invoice['satir_toplami'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Toplam İskonto</td>
                                <td style="text-align: right;">' . ((float)$invoice['iskonto_toplami'] > 0 ? number_format((float)$invoice['iskonto_toplami'], 2, ',', '.') . ' ' . $currLabel : '') . '</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Hesaplanan KDV' . $kdvOranText . '</td>
                                <td style="text-align: right;">' . number_format((float)$invoice['hesaplanan_kdv'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>';

        if ((float)($invoice['tevkifat_tutari'] ?? 0) > 0) {
            $html .= '
                            <tr>
                                <td style="text-align: right; font-weight: bold;">KDV Tevkifatı (-)</td>
                                <td style="text-align: right;">-' . number_format((float)$invoice['tevkifat_tutari'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>';
        }

        $html .= '
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Vergiler Dahil Toplam Tutar</td>
                                <td style="text-align: right;">' . number_format($vergilerDahil, 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold; font-size: 10.5px;">Ödenecek Tutar</td>
                                <td style="text-align: right; font-weight: bold; font-size: 10.5px;">' . number_format((float)$invoice['odenecek_tutar'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- 6. ÇERÇEVELİ NOTLAR VE AÇIKLAMA KUTUSU -->
            <div style="border: 1px solid #000; padding: 6px 10px; font-size: 10px; line-height: 1.45; margin-top: 6px; min-height: 50px;">
                <div><strong>Not:</strong> TLDOVIZ: ' . $tutarYaziylaTl . '</div>';

        if ($curr !== 'TRY' && $curr !== 'TL' && !empty($tutarYaziylaDoviz)) {
            $html .= '<div><strong>Not:</strong> DOVIZLI: ' . $tutarYaziylaDoviz . '</div>';
        }

        if (!empty($invoice['siparis_no'])) {
            $html .= '<div><strong>Not:</strong> Sipariş No: ' . htmlspecialchars($invoice['siparis_no'], ENT_QUOTES, 'UTF-8') . (!empty($invoice['siparis_tarihi']) ? (' Tarih: ' . date('d.m.Y', strtotime($invoice['siparis_tarihi']))) : '') . '</div>';
        }

        if (!empty($invoice['irsaliye_no'])) {
            $html .= '<div><strong>Not:</strong> İrsaliye No: ' . htmlspecialchars($invoice['irsaliye_no'], ENT_QUOTES, 'UTF-8') . (!empty($invoice['irsaliye_tarihi']) ? (' Tarih: ' . date('d.m.Y', strtotime($invoice['irsaliye_tarihi']))) : '') . '</div>';
        }

        if (!empty($firmaIban)) {
            $html .= '<div><strong>Not:</strong> ' . htmlspecialchars($firmaIban, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        foreach ($notSatirlari as $nLine) {
            $html .= '<div><strong>Not:</strong> ' . $nLine . '</div>';
        }

        if (!empty($invoice['iade_fatura_no'])) {
            $html .= '<div><strong>İade edilen fatura:</strong> ' . htmlspecialchars($invoice['iade_fatura_no'] . ' / ' . ($invoice['iade_fatura_tarihi'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $html .= '
                <div><strong>Ödeme Notu:</strong> ' . htmlspecialchars(!empty($invoice['vade_tarihi']) ? ('VADE: ' . date('d.m.Y', strtotime($invoice['vade_tarihi']))) : 'AÇIK HESAP', ENT_QUOTES, 'UTF-8') . '</div>
            </div>

            <!-- 7. EN ALT EDM DİPNOTU -->
            <div style="text-align: center; color: #1e70bf; font-size: 10px; margin-top: 8px; font-weight: 500;">
                Bu Fatura E-Dönüşüm Merkezi EDM Teknolojileri ile Üretilmiştir
            </div>

        </div>';

        return $html;
    }

    /**
     * Gelen Ticari Faturaya Kabul / Red Yanıtı Verme
     */
    public function respondToIncomingInvoice(int $invoiceId, int $firmId, string $responseType, string $reason = ''): array
    {
        return $this->remoteAction($invoiceId, $firmId, 'YANIT:' . $responseType, $reason);
    }

    public function cancelInvoice(int $invoiceId, int $firmId, string $reason): array
    {
        return $this->remoteAction($invoiceId, $firmId, 'IPTAL', $reason);
    }

    private function remoteAction(int $invoiceId, int $firmId, string $action, string $reason): array
    {
        if (!$this->invoiceModel->acquireInvoiceLock($invoiceId, $firmId)) return ['success' => false, 'message' => 'Bu fatura için başka bir işlem sürüyor.'];
        $attempted = false; $remoteSucceeded = false;
        try {
            $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
            if (!$invoice || !empty($invoice['islem_belirsiz']) || in_array($invoice['entegrator_durum_kodu'], ['IPTAL','BELIRSIZ','GONDERILIYOR'], true)) throw new \InvalidArgumentException('Fatura bu işleme uygun değil veya önceki işlem belirsiz; durumunu sorgulayın.');
            $client = $this->client($firmId);
            $response = str_starts_with($action, 'YANIT:') ? substr($action, 6) : null;
            if ($response !== null) {
                if ($invoice['yon'] !== 'GELEN' || $invoice['belge_turu'] !== 'EFATURA' || $invoice['fatura_profili'] !== 'TICARIFATURA' || ($invoice['ticari_yanit'] ?? 'BEKLIYOR') !== 'BEKLIYOR' || !in_array($response, ['KABUL','RED'], true) || ($response === 'RED' && trim($reason) === '')) throw new \InvalidArgumentException('Yanıtlanmamış gelen ticari fatura ve geçerli yanıt/ret gerekçesi gereklidir.');
                $status = $client->getInvoiceStatus([$invoice['ettn']])[$invoice['ettn']] ?? null;
                if (!$status) throw new \InvalidArgumentException('EDM fatura durumu doğrulanamadı.');
                $prior = InvoiceStatusService::response($status['response_code'] ?? '');
                if ($prior !== null) {
                    $this->saveState($invoiceId, $firmId, ['ticari_yanit' => $prior]);
                    throw new \InvalidArgumentException('Bu faturaya EDM’de zaten yanıt verilmiş.');
                }
                if (InvoiceStatusService::map($status)['entegrator_durum_kodu'] !== 'ONAYLANDI') throw new \InvalidArgumentException('Ticari yanıt için tamamlanmış gelen fatura gereklidir.');
            } else {
                if ($invoice['yon'] !== 'GIDEN' || trim($reason) === '') throw new \InvalidArgumentException('Giden fatura ve iptal gerekçesi gereklidir.');
                if (empty($invoice['ubl_xml_path']) && empty($invoice['kaynak_xml']) && empty($invoice['edm_referans_no'])) throw new \InvalidArgumentException('Yerel taslak için Taslak Sil işlemini kullanın.');
                $status = $client->getInvoiceStatus([$invoice['ettn']])[$invoice['ettn']] ?? null;
                if (!$status) throw new \InvalidArgumentException('İptal öncesi EDM durumu doğrulanamadı.');
                $mapped = InvoiceStatusService::map($status);
                if ($invoice['belge_turu'] === 'EFATURA' && (in_array($mapped['entegrator_durum_kodu'], ['ONAYLANDI','GONDERILDI'], true) || (int)($status['gib_code'] ?? 0) >= 1200)) throw new \InvalidArgumentException('Gönderilmiş e-Fatura EDM iptal metodu ile iptal edilemez.');
                if (!in_array($mapped['entegrator_durum_kodu'], $invoice['belge_turu'] === 'EARSIV' ? ['TASLAK','HATALI','GONDERILDI','ONAYLANDI'] : ['TASLAK','HATALI'], true)) throw new \InvalidArgumentException('EDM durumu iptale uygun değil.');
            }
            $this->saveState($invoiceId, $firmId, ['islem_belirsiz' => $action]);
            $attempted = true;
            if ($response !== null) $client->respondInvoice($invoice['ettn'], $response, $reason, $invoiceId);
            else $client->cancelInvoice($invoice['ettn'], $invoiceId);
            $remoteSucceeded = true;
            $this->saveState($invoiceId, $firmId, ['islem_belirsiz' => null] + ($response !== null ? ['ticari_yanit' => $response] : ['entegrator_durum_kodu' => 'IPTAL']));
            $this->invoiceModel->recordEvent($invoiceId, $firmId, $action, 'BASARILI', $reason ?: 'EDM işlemi tamamlandı.');
            return ['success' => true, 'status' => 'success', 'message' => 'İşlem EDM’de başarıyla tamamlandı.'];
        } catch (\Throwable $e) {
            $unknown = $remoteSucceeded || ($attempted && (!$e instanceof EdmOperationException || $e->kind === 'unknown'));
            if ($attempted && !$unknown) $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, ['islem_belirsiz' => null]);
            if ($attempted) $this->invoiceModel->recordEvent($invoiceId, $firmId, $action, $unknown ? 'BELIRSIZ' : 'BASARISIZ', $this->publicMessage($e));
            return ['success' => false, 'status' => 'error', 'message' => $unknown ? 'EDM işlem sonucu belirsiz. Tekrar işlem yapmadan önce durumu sorgulayın.' : $this->publicMessage($e)];
        } finally { $this->invoiceModel->releaseInvoiceLock($invoiceId, $firmId); }
    }

    public function syncIncomingInvoices(int $firmId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->syncInvoices($firmId, 'GELEN', $startDate ?: date('Y-m-d', strtotime('-7 days')), $endDate ?: date('Y-m-d'));
    }

    public function syncOutgoingInvoices(int $firmId, ?string $startDate = null, ?string $endDate = null): array
    {
        // Preserve the pre-existing same-day outgoing default.
        return $this->syncInvoices($firmId, 'GIDEN', $startDate ?: date('Y-m-d'), $endDate ?: date('Y-m-d'));
    }

    private function syncInvoices(int $firmId, string $direction, string $start, string $end): array
    {
        $client = $this->client($firmId);
        $items = $client->getInvoices($direction === 'GELEN' ? 'IN' : 'OUT', $start, $end, 500, 'CREATE');
        $result = $client->getSyncResult() + ['added_count' => 0, 'updated_count' => 0];
        $reader = new UblReaderService();
        foreach ($items as $item) {
            try {
                $source = $reader->read($item['xml'], $direction);
                if (strcasecmp($source['header']['ettn'], $item['uuid']) !== 0) throw new \InvalidArgumentException('XML ETTN ile EDM ETTN uyuşmuyor.');
                $existing = $this->invoiceModel->getInvoiceByEttn($item['uuid'], $firmId);
                $mapped = InvoiceStatusService::map([
                    'status'      => $item['status'],
                    'status_desc' => $item['status_desc'] ?? '',
                ]);
                $header = array_merge($source['header'], $mapped);
                $locked = $existing ? $this->invoiceModel->acquireInvoiceLock((int)$existing['id'], $firmId) : false;
                if ($existing && !$locked) throw new \InvalidArgumentException('Fatura için başka işlem sürüyor; tekrar senkronize edin.');
                try {
                    $header['ubl_xml_path'] = $this->storeXml($firmId, $item['uuid'], $item['xml']);
                    $id = $this->invoiceModel->importInvoice($firmId, $header, $source['lines'], (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0));
                } finally { if ($locked) $this->invoiceModel->releaseInvoiceLock((int)$existing['id'], $firmId); }
                $result[$existing ? 'updated_count' : 'added_count']++;
            } catch (\Throwable $e) {
                $result['complete'] = false; $result['errors'][] = ['uuid' => $item['uuid'], 'message' => $this->publicMessage($e)];
            }
        }
        $hasChanges = ($result['added_count'] > 0 || $result['updated_count'] > 0);
        $success = $hasChanges || $result['complete'];
        $msg = $hasChanges 
            ? sprintf('%d yeni fatura sisteme aktarıldı, %d fatura güncellendi.', $result['added_count'], $result['updated_count'])
            : 'Seçilen tarih aralığında yeni bir fatura bulunamadı.';
        return $result + [
            'success' => $success,
            'message' => $msg
        ];
    }

    public function downloadPdf(int $invoiceId, int $firmId): string
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) throw new \InvalidArgumentException('Fatura bulunamadı.');
        if ($invoice['entegrator_durum_kodu'] === 'TASLAK' && empty($invoice['kaynak_xml']) && empty($invoice['edm_referans_no'])) throw new \InvalidArgumentException('Yerel taslak için PDF bulunmuyor. Yazdırılabilir önizlemeyi kullanın.');
        return $this->client($firmId)->getInvoicePdf($invoice['ettn'], $invoice['yon'] === 'GELEN' ? 'IN' : 'OUT');
    }

    public function connectionInfo(int $firmId): array
    {
        $supplier = $this->supplier($firmId);
        $company = $this->client($firmId)->getCompany($supplier['vkn_tckn']);
        $safe = [];
        foreach (['UNVAN','VKN','ADRES','IL','ILCE','PK','GB','EFATURA','EARSIV','IS_ACTIVE'] as $field) $safe[$field] = $company->$field ?? null;
        $safe['SERIALS'] = [];
        foreach (EdmSoapClient::items($company->{'SERIALLİST'} ?? $company->SERIALLIST ?? null) as $serial) {
            $safe['SERIALS'][] = ['series' => $serial->SERIAL ?? '', 'year' => $serial->YEAR ?? null, 'active' => $serial->ACTIVEFLAG ?? 0, 'earchive' => $serial->EARCHIVEFLAG ?? 0, 'last' => $serial->{'LASTSERİAL'} ?? $serial->LASTSERIAL ?? null];
        }
        return $safe;
    }

    public function counterInfo(int $firmId): array
    {
        $count = $this->client($firmId)->checkCounter();
        $threshold = (int)($this->settingsModel->getSettings($firmId)['kontor_esik'] ?? 100);
        return ['remaining' => $count, 'threshold' => $threshold, 'low' => $count !== null && $count <= $threshold];
    }

    public function history(int $invoiceId, int $firmId, bool $refresh = false): array
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) throw new \InvalidArgumentException('Fatura bulunamadı.');
        if ($refresh && $invoice['fatura_profili'] === 'TICARIFATURA' && !empty($invoice['fatura_no'])) {
            foreach ($this->client($firmId)->responseDates($invoice['fatura_no'], $invoice['fatura_tarihi'] . 'T00:00:00', date('Y-m-d\T23:59:59')) as $row) {
                if (($row->INVOICENUMBER ?? '') !== $invoice['fatura_no'] || (($row->SUPPLIERTAXNUMBER ?? '') !== ($invoice['yon'] === 'GELEN' ? $invoice['alici_vkn_tckn'] : $this->supplier($firmId)['vkn_tckn']))) continue;
                $response = InvoiceStatusService::response($row->STATUSCODE ?? $row->STATUSDESC ?? '');
                if ($response && !empty($row->INVOICERESPONSEDATE)) {
                    $date = (new \DateTimeImmutable($row->INVOICERESPONSEDATE))->setTimezone(new \DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i:s');
                    $this->invoiceModel->recordRemoteResponse($invoiceId, $firmId, $response, $date);
                }
            }
        }
        return ['events' => $this->invoiceModel->history($invoiceId, $firmId), 'report_status' => $invoice['earsiv_rapor_durum'] ?? null, 'cancel_report_status' => $invoice['earsiv_iptal_rapor_durum'] ?? null, 'pending_operation' => $invoice['islem_belirsiz'] ?? null];
    }
}
