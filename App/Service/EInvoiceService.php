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

    public function __construct()
    {
        $this->invoiceModel = new EInvoiceModel();
        $this->settingsModel = new EInvoiceSettingsModel();
        $this->ublService = new UblGeneratorService();
    }

    /**
     * VKN/TCKN GİB Mükellefiyet Sorgulama
     */
    public function checkTaxpayer(int $firmId, string $vkn): array
    {
        try {
            $edmClient = new EdmSoapClient($firmId);
            return $edmClient->checkUser($vkn);
        } catch (Exception $e) {
            error_log("EInvoiceService::checkTaxpayer Error: " . $e->getMessage());
            return [
                'is_einvoice_user' => false,
                'vkn_tckn'         => $vkn,
                'error'            => $e->getMessage()
            ];
        }
    }

    /**
     * Taslak Fatura Oluşturma
     */
    public function createDraft(int $firmId, array $header, array $lines, int $userId): array
    {
        $invoiceId = $this->invoiceModel->createInvoice($firmId, $header, $lines, $userId);
        if (!$invoiceId) {
            return ['success' => false, 'message' => 'Fatura taslağı veritabanına kaydedilemedi.'];
        }

        return [
            'success'    => true,
            'invoice_id' => $invoiceId,
            'message'    => 'Fatura taslağı başarıyla oluşturuldu.'
        ];
    }

    /**
     * Taslak Fatura Güncelleme
     */
    public function updateDraft(int $invoiceId, int $firmId, array $header, array $lines, int $userId): array
    {
        $res = $this->invoiceModel->updateInvoice($invoiceId, $firmId, $header, $lines, $userId);
        if (!$res) {
            return ['success' => false, 'message' => 'Fatura taslağı güncellenemedi veya fatura artık taslak durumunda değil.'];
        }

        return [
            'success'    => true,
            'invoice_id' => $invoiceId,
            'message'    => 'Fatura taslağı başarıyla güncellendi.'
        ];
    }

    /**
     * Faturayı EDM / GİB Sistemine Gönderir
     */
    public function sendInvoice(int $invoiceId, int $firmId): array
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Fatura kaydı bulunamadı.'];
        }

        if (in_array($invoice['entegrator_durum_kodu'], ['ONAYLANDI', 'GONDERILDI'])) {
            return ['success' => false, 'message' => 'Bu fatura zaten gönderilmiş durumdadır.'];
        }

        $settings = $this->settingsModel->getSettings($firmId);
        if (!$settings) {
            return ['success' => false, 'message' => 'Firma EDM API ayarları eksik. Lütfen önce ayarları yapılandırın.'];
        }

        // Fatura Numarası Henüz Yoksa Üret
        if (empty($invoice['fatura_no'])) {
            $seri = ($invoice['belge_turu'] === 'EFATURA') ? ($settings['efatura_seri'] ?: 'ERS') : ($settings['earsiv_seri'] ?: 'ERA');
            $faturaNo = $this->settingsModel->generateNextInvoiceNumber($firmId, $invoice['belge_turu'], $seri);
            $invoice['fatura_no'] = $faturaNo;
            $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, ['fatura_no' => $faturaNo]);
        }

        $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);

        // Satıcı / Firma Bilgilerini Getir
        $firmaModel = new \App\Model\FirmaModel();
        $firma = $firmaModel->getFirma($firmId);

        $sellerVkn = (!empty($firma->vergi_no) && ctype_digit($firma->vergi_no)) ? $firma->vergi_no : ($settings['api_username'] ?? '');
        if (!ctype_digit($sellerVkn)) {
            $sellerVkn = '3230512384';
        }

        $supplier = [
            'vkn_tckn'      => $sellerVkn,
            'unvan'         => !empty($firma->firma_unvan) ? $firma->firma_unvan : (!empty($firma->firma_adi) ? $firma->firma_adi : ($_SESSION['firma_adi'] ?? 'ERSAN ELEKTRİK LTD. ŞTİ.')),
            'adres'         => !empty($firma->adres) ? $firma->adres : 'Merkez Mah.',
            'ilce'          => !empty($firma->ilce) ? $firma->ilce : 'Merkez',
            'il'            => !empty($firma->il) ? $firma->il : 'Kayseri',
            'vergi_dairesi' => !empty($firma->vergi_dairesi) ? $firma->vergi_dairesi : 'Erciyes Vergi Dairesi',
            'telefon'       => $firma->telefon ?? '',
            'eposta'        => !empty($firma->email) ? $firma->email : (!empty($firma->kep_adresi) ? $firma->kep_adresi : ''),
            'web'           => $firma->web_sitesi ?? ''
        ];

        // UBL-TR XML Üret
        $xmlContent = $this->ublService->generateInvoiceXml($invoice, $supplier, $invoice['satirlar'] ?? []);

        // XML Dosyasını Kaydet
        $storageDir = $projectRoot . '/storage/invoices/' . $firmId . '/' . date('Y/m');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $xmlPath = $storageDir . '/' . $invoice['ettn'] . '.xml';
        file_put_contents($xmlPath, $xmlContent);

        // EDM Bilişim'e Gönder
        try {
            $edmClient = new EdmSoapClient($firmId);
            $receiverVkn = $invoice['alici_vkn_tckn'] ?: '1111111111';
            $receiverAlias = ($invoice['belge_turu'] === 'EFATURA')
                ? ($invoice['alici_posta_kutusu'] ?: 'urn:mail:defaultpk')
                : 'defaultpk';
            $senderVkn = $sellerVkn;
            $senderAlias = $settings['varsayilan_gonderici_alias'] ?: 'urn:mail:defaultgb@edmbilisim.com.tr';

            $sendResult = $edmClient->sendInvoice($xmlContent, $receiverVkn, $receiverAlias, $senderVkn, $senderAlias, $invoiceId, $invoice['ettn'] ?? null);

            if ($sendResult['success']) {
                $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                    'entegrator_durum_kodu' => 'GONDERILDI',
                    'edm_referans_no'       => $sendResult['guid'] ?? null,
                    'ubl_xml_path'          => $xmlPath,
                    'gib_durum_aciklamasi'  => 'EDM Bilişim sistemine iletildi. GİB onayı bekleniyor.'
                ]);

                return [
                    'success'   => true,
                    'message'   => 'Fatura başarıyla EDM / GİB sistemine iletildi.',
                    'fatura_no' => $invoice['fatura_no']
                ];
            } else {
                $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                    'entegrator_durum_kodu' => 'HATALI',
                    'gib_durum_aciklamasi'  => $sendResult['error'] ?? 'EDM gönderim hatası'
                ]);

                return [
                    'success' => false,
                    'message' => 'EDM Gönderim Hatası: ' . ($sendResult['error'] ?? 'Bilinmeyen hata')
                ];
            }
        } catch (Exception $e) {
            error_log("EInvoiceService::sendInvoice Error: " . $e->getMessage());
            $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                'entegrator_durum_kodu' => 'HATALI',
                'gib_durum_aciklamasi'  => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'Gönderim esnasında hata oluştu: ' . $e->getMessage()];
        }
    }

    /**
     * GİB Durumunu EDM'den Senkronize Eder
     */
    public function syncStatus(int $invoiceId, int $firmId): array
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice || empty($invoice['ettn'])) {
            return ['success' => false, 'message' => 'Fatura veya ETTN bilgisi bulunamadı.'];
        }

        try {
            $edmClient = new EdmSoapClient($firmId);
            $statusList = $edmClient->getInvoiceStatus([$invoice['ettn']]);

            if (isset($statusList[$invoice['ettn']])) {
                $st = $statusList[$invoice['ettn']];
                $newStatus = 'GONDERILDI';
                if ($st['status_code'] == '1300' || $st['gib_code'] == 1300) {
                    $newStatus = 'ONAYLANDI';
                } elseif (in_array($st['status_code'], ['1160', '1161', '1162', '1163'])) {
                    $newStatus = 'HATALI';
                }

                $ticariYanit = $invoice['ticari_yanit'] ?: 'BEKLIYOR';
                if (!empty($st['response_code'])) {
                    $respUpper = strtoupper(trim($st['response_code']));
                    if (in_array($respUpper, ['KABUL', 'RED', 'BEKLIYOR'])) {
                        $ticariYanit = $respUpper;
                    }
                }

                $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                    'entegrator_durum_kodu' => $newStatus,
                    'gib_durum_kodu'        => $st['gib_code'] ?? null,
                    'gib_durum_aciklamasi'  => $st['gib_desc'] ?: $st['status_desc'],
                    'ticari_yanit'          => $ticariYanit
                ]);

                return [
                    'success'     => true,
                    'durum_kodu'  => $newStatus,
                    'aciklama'    => $st['gib_desc'] ?: $st['status_desc']
                ];
            }

            return ['success' => false, 'message' => 'EDM sisteminden durum bilgisi alınamadı.'];
        } catch (Exception $e) {
            error_log("EInvoiceService::syncStatus Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Senkronizasyon hatası: ' . $e->getMessage()];
        }
    }

    /**
     * HTML Önizleme ve Yazdırma Formatı (GİB Standart Şablonu)
     */
    public function renderHtmlPreview(int $invoiceId, int $firmId): string
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            return '<div class="alert alert-danger p-3">Fatura kaydı bulunamadı.</div>';
        }

        $firmaModel = new FirmaModel();
        $firma = $firmaModel->getFirma($firmId);
        $settings = $this->settingsModel->getSettings($firmId);

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

        // GİB Logosu Base64
        $gibLogoBase64 = '';
        $gibLogoPath = (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2)) . '/assets/images/gib_logo.png';
        if (file_exists($gibLogoPath)) {
            $gibLogoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($gibLogoPath));
        }

        // QR Kod (Karekod) Verisi ve Base64
        $qrDataParts = [];
        if (!empty($saticiVkn)) $qrDataParts[] = 'VKN:' . $saticiVkn;
        if (!empty($aliciVkn)) $qrDataParts[] = 'AVKN:' . $aliciVkn;
        if (!empty($invoice['fatura_no'])) $qrDataParts[] = 'NO:' . $invoice['fatura_no'];
        if (!empty($invoice['fatura_tarihi'])) $qrDataParts[] = 'TRH:' . date('Y-m-d', strtotime($invoice['fatura_tarihi']));
        $qrDataParts[] = 'TTR:' . number_format((float)$invoice['odenecek_tutar'], 2, '.', '') . ' ' . $curr;
        if (!empty($invoice['ettn'])) $qrDataParts[] = 'ETTN:' . $invoice['ettn'];
        $qrDataString = implode(';', $qrDataParts);

        $qrCodeBase64 = \App\Helper\Helper::generateQrCode($qrDataString, 3);

        // Para Birimi Sembolü / Kodu
        $curr = strtoupper($invoice['para_birimi'] ?? 'TRY');
        $currLabel = ($curr === 'TRY' || $curr === 'TL') ? 'TL' : $curr;

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

        $vergilerDahil = (float)$invoice['satir_toplami'] - (float)$invoice['iskonto_toplami'] + (float)$invoice['hesaplanan_kdv'];

        $html = '
        <div class="efatura-wrapper" style="background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; width: 100%; max-width: 820px; margin: 0 auto; padding: 15px; box-sizing: border-box;">
            
            <style>
                .efatura-wrapper * { box-sizing: border-box; }
                .efatura-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 10px; margin-bottom: 0; }
                .efatura-table th { border: 1px solid #000; padding: 4px 2px; text-align: center; font-weight: bold; background: #fff; color: #000; }
                .efatura-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
                .efatura-meta-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 10.5px; }
                .efatura-meta-table td { border: 1px solid #000; padding: 2px 5px; }
                .efatura-totals-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 10.5px; }
                .efatura-totals-table td { border: 1px solid #000; padding: 2px 6px; }
                @media print {
                    @page { size: A4 portrait; margin: 8mm 10mm; }
                    body { background: #fff !important; color: #000 !important; margin: 0 !important; padding: 0 !important; }
                    .efatura-wrapper { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
                }
            </style>

            <!-- 1. ÜST BÖLÜM: SATICI BİLGİLERİ (SOL), GİB LOGO (ORTA) VE QR KOD (SAĞ) -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                <tr>
                    <!-- SOL: SATICI BİLGİLERİ -->
                    <td style="width: 48%; vertical-align: top; padding-right: 10px;">
                        <div style="font-weight: bold; font-size: 13px; text-transform: uppercase; margin-bottom: 3px;">
                            ' . htmlspecialchars($saticiUnvan, ENT_QUOTES, 'UTF-8') . '
                        </div>
                        <div style="font-size: 11px; line-height: 1.4;">
                            ' . implode("\n", $saticiHtmlLines) . '
                        </div>
                    </td>

                    <!-- ORTA: GİB LOGOSU VE BELGE BAŞLIĞI -->
                    <td style="width: 28%; vertical-align: top; text-align: center; padding: 0 5px;">
                        ' . ($gibLogoBase64 ? '<img src="' . $gibLogoBase64 . '" style="width: 76px; height: 76px; display: inline-block; margin-bottom: 3px;" alt="GİB Logo"><br>' : '') . '
                        <span style="font-size: 14px; font-weight: bold; letter-spacing: 0.5px;">' . $belgeTuruText . '</span>
                    </td>

                    <!-- SAĞ: QR KOD (KAREKOD) -->
                    <td style="width: 24%; vertical-align: top; text-align: right; padding-left: 5px;">
                        ' . ($qrCodeBase64 ? '
                        <div style="display: inline-block; text-align: center;">
                            <img src="' . $qrCodeBase64 . '" style="width: 78px; height: 78px; border: 1px solid #000; padding: 2px; background: #fff;" alt="Karekod"><br>
                            <span style="font-size: 8.5px; color: #333; display: block; margin-top: 2px; font-weight: 600;">KAREKOD</span>
                        </div>' : '') . '
                    </td>
                </tr>
            </table>

            <!-- ÜST AYIRICI ÇİFT ÇİZGİ -->
            <div style="border-top: 3px solid #000; border-bottom: 1px solid #000; height: 2px; margin: 4px 0 12px 0;"></div>

            <!-- 2. ORTA BÖLÜM: SAYIN (ALICI) VE FATURA METADATA -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                <tr>
                    <!-- ALICI BİLGİLERİ -->
                    <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                        <div style="font-weight: bold; font-size: 12px; margin-bottom: 3px;">SAYIN</div>
                        <div style="font-size: 11px; line-height: 1.4;">
                            ' . implode("\n", $aliciHtmlLines) . '
                        </div>
                    </td>

                    <!-- FATURA BİLGİLERİ TABLOSU -->
                    <td style="width: 45%; vertical-align: top;">
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
                                <td>' . htmlspecialchars($invoice['duzenleme_saati'] ?? date('H:i:s'), ENT_QUOTES, 'UTF-8') . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- 3. ETTN SATIRI -->
            <div style="font-size: 11px; margin-bottom: 6px; font-weight: normal;">
                <strong>ETTN:</strong> ' . htmlspecialchars($invoice['ettn'] ?? '', ENT_QUOTES, 'UTF-8') . '
            </div>

            <!-- 4. MAL / HİZMET KALEMLERİ TABLOSU -->
            <table class="efatura-table">
                <thead>
                    <tr>
                        <th style="width: 26px;">SN</th>
                        <th style="width: 75px;">Ürün Kodu</th>
                        <th>Mal Hizmet</th>
                        <th style="width: 50px;">Miktar</th>
                        <th style="width: 65px;">Birim Fiyat</th>
                        <th style="width: 50px;">İskonto<br>Oranı</th>
                        <th style="width: 55px;">İskonto<br>Tutarı</th>
                        <th style="width: 55px;">KDV Oranı</th>
                        <th style="width: 65px;">KDV Tutarı</th>
                        <th style="width: 75px;">Diğer Vergiler</th>
                        <th style="width: 80px;">Mal Hizmet<br>Tutarı</th>
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
                        <td style="text-align: start;">' . htmlspecialchars($line['urun_kodu'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: start;">' . htmlspecialchars($line['urun_hizmet_adi'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: center; line-height: 1.15;">' . number_format((float)$line['miktar'], 1, ',', '.') . '<br>' . htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: right;">' . number_format((float)$line['birim_fiyat'], 2, ',', '.') . ' ' . $currLabel . '</td>
                        <td style="text-align: center;">' . ($iskontoOran > 0 ? '%' . number_format($iskontoOran, 2, ',', '.') : '') . '</td>
                        <td style="text-align: right;">' . ($iskontoTutar > 0 ? number_format($iskontoTutar, 2, ',', '.') : '') . '</td>
                        <td style="text-align: center;">%' . number_format((float)$line['kdv_orani'], 2, ',', '.') . '</td>
                        <td style="text-align: right;">' . number_format((float)$line['kdv_tutari'], 2, ',', '.') . ' ' . $currLabel . '</td>
                        <td style="text-align: right;">' . ($tevkifatTutar > 0 ? number_format($tevkifatTutar, 2, ',', '.') . ' ' . $currLabel : '') . '</td>
                        <td style="text-align: right;">' . number_format((float)($line['miktar'] * $line['birim_fiyat']), 2, ',', '.') . ' ' . $currLabel . '</td>
                    </tr>';
            }
        }

        // Fatura Form Yüksekliği İçin Boş Çizgi Satırları (En az 16 satır grid)
        $emptyRowsToDraw = max(0, 16 - $totalLinesCount);
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
                    <td></td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <!-- 5. ALT TOPLAMLAR BÖLÜMÜ -->
            <table style="width: 100%; border-collapse: collapse; margin-top: -1px; margin-bottom: 8px;">
                <tr>
                    <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                        <!-- Sol taraf boş veya denge -->
                    </td>
                    <td style="width: 45%; vertical-align: top; padding: 0;">
                        <table class="efatura-totals-table">
                            <tr>
                                <td style="text-align: right; font-weight: bold; width: 62%;">Mal Hizmet Toplam Tutarı</td>
                                <td style="text-align: right; width: 38%;">' . number_format((float)$invoice['satir_toplami'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Toplam İskonto</td>
                                <td style="text-align: right;">' . number_format((float)$invoice['iskonto_toplami'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Toplam Masraf</td>
                                <td style="text-align: right;">0,00 ' . $currLabel . '</td>
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
                                <td style="text-align: right; font-weight: bold; font-size: 11px;">Ödenecek Tutar</td>
                                <td style="text-align: right; font-weight: bold; font-size: 11px;">' . number_format((float)$invoice['odenecek_tutar'], 2, ',', '.') . ' ' . $currLabel . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- 6. NOTLAR VE AÇIKLAMA KUTUSU -->
            <div style="border: 2px solid #000; padding: 6px 10px; font-size: 10.5px; line-height: 1.45; margin-top: 4px;">
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

        if (!empty($invoice['notlar'])) {
            $lines = explode("\n", trim($invoice['notlar']));
            foreach ($lines as $nLine) {
                $nLine = trim($nLine);
                if ($nLine !== '') {
                    $html .= '<div><strong>Not:</strong> ' . htmlspecialchars($nLine, ENT_QUOTES, 'UTF-8') . '</div>';
                }
            }
        }

        $html .= '
                <div><strong>Ödeme Notu:</strong> ' . htmlspecialchars(!empty($invoice['vade_tarihi']) ? ('VADE: ' . date('d.m.Y', strtotime($invoice['vade_tarihi']))) : 'AÇIK HESAP', ENT_QUOTES, 'UTF-8') . '</div>
            </div>

        </div>';

        return $html;
    }

    /**
     * Gelen Ticari Faturaya Kabul / Red Yanıtı Verme
     */
    public function respondToIncomingInvoice(int $invoiceId, int $firmId, string $responseType, string $reason = ''): array
    {
        try {
            $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Fatura bulunamadı.'];
            }

            if ($invoice['yon'] !== 'GELEN' || $invoice['fatura_profili'] !== 'TICARIFATURA') {
                return ['success' => false, 'message' => 'Yalnızca gelen ticari faturalara yanıt verilebilir.'];
            }

            // Durumu güncelle
            $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                'ticari_yanit' => $responseType,
                'gib_durum_aciklamasi' => ($invoice['gib_durum_aciklamasi'] ?? '') . " [Ticari Yanıt: $responseType - $reason]"
            ]);

            return [
                'success' => true,
                'message' => "Faturaya {$responseType} yanıtı başarıyla işlendi."
            ];
        } catch (Exception $e) {
            error_log("EInvoiceService::respondToIncomingInvoice Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Yanıt işlenirken bir hata oluştu: ' . $e->getMessage()];
        }
    }

    /**
     * EDM'den Gelen Faturaları Çekme / Senkronize Etme
     */
    /**
     * EDM'den Gelen Faturaları Çekme ve Senkronize Etme
     */
    public function syncIncomingInvoices(int $firmId, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $client = new EdmSoapClient($firmId);
            $invoices = $client->getInvoices('IN', $startDate ?: date('Y-m-d', strtotime('-7 days')), $endDate ?: date('Y-m-d'), 100, 'CREATE');

            $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);
            $addedCount = 0;
            $updatedCount = 0;

            foreach ($invoices as $inv) {
                $uuid = $inv['uuid'];
                $faturaNo = $inv['fatura_no'];
                $xml = $inv['xml'];

                if (empty($uuid)) continue;

                // Fatura sistemde var mı kontrol et
                $existing = $this->invoiceModel->getInvoiceByEttn($uuid, $firmId);
                if ($existing) {
                    if (!empty($faturaNo) && ($existing['fatura_no'] !== $faturaNo || empty($existing['fatura_no']))) {
                        $this->invoiceModel->updateInvoiceStatus((int)$existing['id'], $firmId, [
                            'fatura_no' => $faturaNo,
                            'entegrator_durum_kodu' => 'ONAYLANDI'
                        ]);
                        $updatedCount++;
                    }
                    continue;
                }

                // Gelen faturada düzenleyen (tedarikçi/satıcı) bilgileri
                $tedarikciUnvan = $inv['supplier'] ?: 'Tedarikçi Firma';
                $tedarikciVkn = $inv['sender'] ?: '';
                $faturaTarihi = $inv['issue_date'] ?: date('Y-m-d');
                $tutar = (float)($inv['payable_amount'] ?: 0.00);
                $belgeTuru = (strpos($faturaNo, 'EAR') === 0) ? 'EARSIV' : 'EFATURA';
                $faturaProfili = $inv['profile_id'] ?: 'TICARIFATURA';

                if (!empty($xml)) {
                    $xmlObj = @simplexml_load_string($xml);
                    if ($xmlObj) {
                        $faturaTarihi = (string)($xmlObj->xpath('//cbc:IssueDate')[0] ?? $faturaTarihi);
                        $faturaProfili = (string)($xmlObj->xpath('//cbc:ProfileID')[0] ?? $faturaProfili);
                        $payableAmt = (float)($xmlObj->xpath('//cac:LegalMonetaryTotal/cbc:PayableAmount')[0] ?? 0);
                        if ($payableAmt > 0) $tutar = $payableAmt;

                        $partyName = $xmlObj->xpath('//cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name')[0] ?? null;
                        $personName = $xmlObj->xpath('//cac:AccountingSupplierParty/cac:Party/cac:Person/cbc:FirstName')[0] ?? null;
                        $personFamily = $xmlObj->xpath('//cac:AccountingSupplierParty/cac:Party/cac:Person/cbc:FamilyName')[0] ?? null;

                        if ($partyName) {
                            $tedarikciUnvan = (string)$partyName;
                        } elseif ($personName) {
                            $tedarikciUnvan = trim((string)$personName . ' ' . (string)$personFamily);
                        }

                        $vknEl = $xmlObj->xpath('//cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID')[0] ?? null;
                        if ($vknEl) {
                            $tedarikciVkn = (string)$vknEl;
                        }
                    }
                }

                // XML Dosyasını Kaydet
                $xmlPath = null;
                if (!empty($xml)) {
                    $storageDir = $projectRoot . '/storage/invoices/' . $firmId . '/gelen/' . date('Y/m');
                    if (!is_dir($storageDir)) {
                        mkdir($storageDir, 0775, true);
                    }
                    $xmlPath = $storageDir . '/' . $uuid . '.xml';
                    file_put_contents($xmlPath, $xml);
                }

                $header = [
                    'cari_id'               => null,
                    'ettn'                  => $uuid,
                    'fatura_no'             => $faturaNo ?: 'Gelen Fatura',
                    'yon'                   => 'GELEN',
                    'belge_turu'            => $belgeTuru,
                    'fatura_profili'        => $faturaProfili,
                    'fatura_tipi'           => 'SATIS',
                    'fatura_tarihi'         => $faturaTarihi,
                    'duzenleme_saati'       => date('H:i:s'),
                    'vade_tarihi'           => null,
                    'alici_vkn_tckn'        => $tedarikciVkn,
                    'alici_unvan'           => $tedarikciUnvan,
                    'alici_vergi_dairesi'   => null,
                    'alici_adres'           => null,
                    'alici_il'              => null,
                    'alici_ilce'            => null,
                    'alici_ulke'            => 'Türkiye',
                    'alici_eposta'          => null,
                    'alici_telefon'         => null,
                    'alici_posta_kutusu'    => null,
                    'para_birimi'           => 'TRY',
                    'doviz_kuru'            => 1.0000,
                    'satir_toplami'         => $tutar,
                    'iskonto_toplami'       => 0,
                    'kdv_matrahi'           => $tutar,
                    'hesaplanan_kdv'        => 0,
                    'tevkifat_tutari'       => 0,
                    'odenecek_tutar'        => $tutar,
                    'notlar'                => 'EDM Gelen Fatura Senkronizasyonu',
                    'entegrator_durum_kodu' => 'ONAYLANDI',
                    'ticari_yanit'          => 'BEKLIYOR',
                    'ubl_xml_path'          => $xmlPath,
                    'olusturan_user_id'     => (int)($_SESSION['user_id'] ?? 1)
                ];

                $lines = [
                    [
                        'mal_hizmet_adi' => 'Gelen Fatura Kalemi (EDM)',
                        'miktar'         => 1,
                        'birim_kodu'     => 'C62',
                        'birim_fiyat'    => $tutar,
                        'iskonto_orani'  => 0,
                        'kdv_orani'      => 0
                    ]
                ];

                $newId = $this->invoiceModel->createInvoice($firmId, $header, $lines, (int)($_SESSION['user_id'] ?? 1));
                if ($newId) {
                    $addedCount++;
                }
            }

            return [
                'success'       => true,
                'message'       => "EDM Gelen Faturalar senkronize edildi: {$addedCount} yeni fatura sisteme eklendi, {$updatedCount} kayıt güncellendi.",
                'added_count'   => $addedCount,
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            error_log("EInvoiceService::syncIncomingInvoices Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gelen faturalar taranırken hata: ' . $e->getMessage()
            ];
        }
    }

    /**
     * EDM'den Giden / Taslak Faturaları Çekme ve Senkronize Etme
     */
    public function syncOutgoingInvoices(int $firmId, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $client = new EdmSoapClient($firmId);
            $invoices = $client->getInvoices('OUT', $startDate ?: date('Y-m-d', strtotime('-3 days')), $endDate ?: date('Y-m-d'), 100, 'CREATE');

            $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);
            $addedCount = 0;
            $updatedCount = 0;

            foreach ($invoices as $inv) {
                $uuid = $inv['uuid'];
                $faturaNo = $inv['fatura_no'];
                $xml = $inv['xml'];

                if (empty($uuid)) continue;

                // XML ve Header'dan temel bilgileri al
                $aliciUnvan = $inv['customer'] ?: 'Alıcı Müşteri';
                $aliciVkn = $inv['receiver'] ?: '';
                $faturaTarihi = $inv['issue_date'] ?: date('Y-m-d');
                $tutar = (float)($inv['payable_amount'] ?: 0.00);
                $belgeTuru = (strpos($faturaNo, 'EAR') === 0) ? 'EARSIV' : 'EFATURA';
                $faturaProfili = $inv['profile_id'] ?: 'TICARIFATURA';

                if (!empty($xml)) {
                    $xmlObj = @simplexml_load_string($xml);
                    if ($xmlObj) {
                        $faturaTarihi = (string)($xmlObj->xpath('//cbc:IssueDate')[0] ?? $faturaTarihi);
                        $faturaProfili = (string)($xmlObj->xpath('//cbc:ProfileID')[0] ?? $faturaProfili);
                        $payableAmt = (float)($xmlObj->xpath('//cac:LegalMonetaryTotal/cbc:PayableAmount')[0] ?? 0);
                        if ($payableAmt > 0) $tutar = $payableAmt;
                        
                        $partyName = $xmlObj->xpath('//cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name')[0] ?? null;
                        $personName = $xmlObj->xpath('//cac:AccountingCustomerParty/cac:Party/cac:Person/cbc:FirstName')[0] ?? null;
                        $personFamily = $xmlObj->xpath('//cac:AccountingCustomerParty/cac:Party/cac:Person/cbc:FamilyName')[0] ?? null;

                        if ($partyName) {
                            $aliciUnvan = (string)$partyName;
                        } elseif ($personName) {
                            $aliciUnvan = trim((string)$personName . ' ' . (string)$personFamily);
                        }

                        $vknEl = $xmlObj->xpath('//cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID')[0] ?? null;
                        if ($vknEl) {
                            $aliciVkn = (string)$vknEl;
                        }
                    }
                }

                // Belge Türü Normalizasyonu
                $belgeTuru = 'EFATURA';
                if (strpos($faturaNo, 'EAR') === 0 || strpos($faturaNo, 'VCA') === 0 || strpos($faturaNo, 'ERA') === 0) {
                    $belgeTuru = 'EARSIV';
                }
                if (!empty($inv['header']->EARCHIVE) || !empty($inv['header']->INTERNETSALES)) {
                    $belgeTuru = 'EARSIV';
                }

                // Profil Normalizasyonu (TICARIFATURA, TEMELFATURA, EARSIVFATURA, KAMU, IHRACAT)
                $profUpper = strtoupper(str_replace([' ', '_', '-'], '', (string)$faturaProfili));
                if (strpos($profUpper, 'EARSIV') !== false) {
                    $faturaProfili = 'EARSIVFATURA';
                } elseif (strpos($profUpper, 'TICARI') !== false) {
                    $faturaProfili = 'TICARIFATURA';
                } elseif (strpos($profUpper, 'KAMU') !== false) {
                    $faturaProfili = 'KAMU';
                } elseif (strpos($profUpper, 'IHRACAT') !== false) {
                    $faturaProfili = 'IHRACAT';
                } elseif (strpos($profUpper, 'TEMEL') !== false) {
                    $faturaProfili = 'TEMELFATURA';
                } else {
                    $faturaProfili = ($belgeTuru === 'EARSIV') ? 'EARSIVFATURA' : 'TICARIFATURA';
                }

                // Fatura Tipi Normalizasyonu (SATIS, IADE, TEVKIFAT, ISTISNA, OZELMATRAH, IHRACKAYITLI)
                $invTypeRaw = (string)($inv['header']->INVOICE_TYPE ?? 'SATIS');
                $tipUpper = strtoupper(str_replace([' ', '_', '-'], '', $invTypeRaw));
                $tipUpper = str_replace(['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['I', 'I', 'S', 'G', 'U', 'O', 'C'], $tipUpper);
                if (strpos($tipUpper, 'IADE') !== false) $faturaTipi = 'IADE';
                elseif (strpos($tipUpper, 'TEVKIFAT') !== false) $faturaTipi = 'TEVKIFAT';
                elseif (strpos($tipUpper, 'ISTISNA') !== false) $faturaTipi = 'ISTISNA';
                elseif (strpos($tipUpper, 'OZELMATRAH') !== false) $faturaTipi = 'OZELMATRAH';
                elseif (strpos($tipUpper, 'IHRACKAYITLI') !== false) $faturaTipi = 'IHRACKAYITLI';
                else $faturaTipi = 'SATIS';

                $isDraft = (empty($faturaNo) || $faturaNo === 'Taslak' || strpos($faturaNo, 'PSL') === 0);
                $statusKodu = $isDraft ? 'TASLAK' : 'GONDERILDI';
                $rawStatus = (string)($inv['header']->STATUS ?? '');
                if (stripos($rawStatus, 'CANCEL') !== false || stripos($rawStatus, 'IPTAL') !== false) {
                    $statusKodu = 'IPTAL';
                } elseif (stripos($rawStatus, 'SUCCEED') !== false && !$isDraft) {
                    $statusKodu = 'ONAYLANDI';
                }

                // XML Dosyasını Kaydet
                $xmlPath = null;
                if (!empty($xml)) {
                    $storageDir = $projectRoot . '/storage/invoices/' . $firmId . '/giden/' . date('Y/m');
                    if (!is_dir($storageDir)) {
                        mkdir($storageDir, 0775, true);
                    }
                    $xmlPath = $storageDir . '/' . $uuid . '.xml';
                    file_put_contents($xmlPath, $xml);
                }

                // Fatura sistemde var mı kontrol et (Güncelleme Kontrolü)
                $existing = $this->invoiceModel->getInvoiceByEttn($uuid, $firmId);
                if ($existing) {
                    $updateData = [];
                    if (!empty($faturaNo) && $faturaNo !== $existing['fatura_no']) {
                        $updateData['fatura_no'] = $faturaNo;
                    }
                    if ($statusKodu !== $existing['entegrator_durum_kodu']) {
                        $updateData['entegrator_durum_kodu'] = $statusKodu;
                    }
                    if (abs($tutar - (float)$existing['odenecek_tutar']) > 0.01) {
                        $updateData['odenecek_tutar'] = $tutar;
                        $updateData['satir_toplami'] = $tutar;
                        $updateData['kdv_matrahi'] = $tutar;
                    }
                    if (!empty($aliciUnvan) && $aliciUnvan !== 'Alıcı Müşteri' && $aliciUnvan !== $existing['alici_unvan']) {
                        $updateData['alici_unvan'] = $aliciUnvan;
                    }
                    if (!empty($aliciVkn) && $aliciVkn !== $existing['alici_vkn_tckn']) {
                        $updateData['alici_vkn_tckn'] = $aliciVkn;
                    }
                    if (!empty($xmlPath) && empty($existing['ubl_xml_path'])) {
                        $updateData['ubl_xml_path'] = $xmlPath;
                    }

                    if (!empty($updateData)) {
                        $this->invoiceModel->updateInvoiceStatus((int)$existing['id'], $firmId, $updateData);
                        $updatedCount++;
                    }
                    continue;
                }

                // Veritabanına kaydet
                $header = [
                    'cari_id'               => null,
                    'ettn'                  => $uuid,
                    'fatura_no'             => $faturaNo ?: 'Taslak',
                    'yon'                   => 'GIDEN',
                    'belge_turu'            => $belgeTuru,
                    'fatura_profili'        => $faturaProfili,
                    'fatura_tipi'           => $faturaTipi,
                    'fatura_tarihi'         => $faturaTarihi,
                    'duzenleme_saati'       => date('H:i:s'),
                    'vade_tarihi'           => null,
                    'alici_vkn_tckn'        => $aliciVkn,
                    'alici_unvan'           => $aliciUnvan,
                    'alici_vergi_dairesi'   => null,
                    'alici_adres'           => null,
                    'alici_il'              => null,
                    'alici_ilce'            => null,
                    'alici_ulke'            => 'Türkiye',
                    'alici_eposta'          => null,
                    'alici_telefon'         => null,
                    'alici_posta_kutusu'    => null,
                    'para_birimi'           => 'TRY',
                    'doviz_kuru'            => 1.0000,
                    'satir_toplami'         => $tutar,
                    'iskonto_toplami'       => 0,
                    'kdv_matrahi'           => $tutar,
                    'hesaplanan_kdv'        => 0,
                    'tevkifat_tutari'       => 0,
                    'odenecek_tutar'        => $tutar,
                    'notlar'                => 'EDM Sisteminden Senkronize Edildi',
                    'entegrator_durum_kodu' => $isDraft ? 'TASLAK' : 'GONDERILDI',
                    'ubl_xml_path'          => $xmlPath,
                    'olusturan_user_id'     => (int)($_SESSION['user_id'] ?? 1)
                ];

                $lines = [
                    [
                        'mal_hizmet_adi' => 'Hizmet / Ürün Kalemi (EDM)',
                        'miktar'         => 1,
                        'birim_kodu'     => 'C62',
                        'birim_fiyat'    => $tutar,
                        'iskonto_orani'  => 0,
                        'kdv_orani'      => 0
                    ]
                ];

                $newId = $this->invoiceModel->createInvoice($firmId, $header, $lines, (int)($_SESSION['user_id'] ?? 1));
                if ($newId) {
                    $addedCount++;
                }
            }

            return [
                'success'       => true,
                'message'       => "EDM senkronizasyonu tamamlandı: {$addedCount} yeni fatura sisteme eklendi, {$updatedCount} kayıt güncellendi.",
                'added_count'   => $addedCount,
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            error_log("EInvoiceService::syncOutgoingInvoices Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Senkronizasyon sırasında hata oluştu: ' . $e->getMessage()
            ];
        }
    }
}
