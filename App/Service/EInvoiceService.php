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

        // Satıcı / Firma Bilgilerini Getir
        $supplier = [
            'vkn_tckn'      => $settings['api_username'] ?? '',
            'unvan'         => $_SESSION['firma_adi'] ?? 'ERSAN ELEKTRİK LTD. ŞTİ.',
            'adres'         => 'Merkez Mah.',
            'ilce'          => 'Merkez',
            'il'            => 'Kayseri',
            'vergi_dairesi' => 'Erciyes Vergi Dairesi'
        ];

        // UBL-TR XML Üret
        $xmlContent = $this->ublService->generateInvoiceXml($invoice, $supplier, $invoice['satirlar'] ?? []);

        // XML Dosyasını Kaydet
        $storageDir = PROJECT_ROOT . '/storage/invoices/' . $firmId . '/' . date('Y/m');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $xmlPath = $storageDir . '/' . $invoice['ettn'] . '.xml';
        file_put_contents($xmlPath, $xmlContent);

        // EDM Bilişim'e Gönder
        try {
            $edmClient = new EdmSoapClient($firmId);

            if ($invoice['belge_turu'] === 'EFATURA') {
                $receiverAlias = $invoice['alici_posta_kutusu'] ?: 'urn:mail:defaultpk';
                $sendResult = $edmClient->sendInvoice($xmlContent, $receiverAlias, $settings['varsayilan_gonderici_alias'], $invoiceId);
            } else {
                $sendResult = $edmClient->archiveInvoice($xmlContent, $invoice['alici_eposta'], $invoiceId);
            }

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

                $this->invoiceModel->updateInvoiceStatus($invoiceId, $firmId, [
                    'entegrator_durum_kodu' => $newStatus,
                    'gib_durum_kodu'        => $st['gib_code'] ?? null,
                    'gib_durum_aciklamasi'  => $st['gib_desc'] ?: $st['status_desc'],
                    'ticari_yanit'          => $st['response_code'] ?? $invoice['ticari_yanit']
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
     * HTML Önizleme Render Eder
     */
    public function renderHtmlPreview(int $invoiceId, int $firmId): string
    {
        $invoice = $this->invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            return '<div class="alert alert-danger">Fatura bulunamadı.</div>';
        }

        $html = '
        <div class="card p-4 border" style="font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #fff;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">' . htmlspecialchars($_SESSION['firma_adi'] ?? 'ERSAN ELEKTRİK', ENT_QUOTES, 'UTF-8') . '</h3>
                    <p class="text-muted mb-0">E-Fatura & E-Arşiv Belgesi</p>
                </div>
                <div class="text-end">
                    <span class="badge ' . ($invoice['belge_turu'] === 'EFATURA' ? 'bg-success' : 'bg-info') . ' fs-6 px-3 py-2 mb-2 d-inline-block">
                        ' . htmlspecialchars($invoice['belge_turu'], ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($invoice['fatura_profili'], ENT_QUOTES, 'UTF-8') . ')
                    </span>
                    <div class="fw-bold fs-6">Fatura No: ' . htmlspecialchars($invoice['fatura_no'] ?: 'TASLAK', ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="text-muted small">ETTN: ' . htmlspecialchars($invoice['ettn'], ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="small">Tarih: ' . date('d.m.Y', strtotime($invoice['fatura_tarihi'])) . ' ' . htmlspecialchars($invoice['duzenleme_saati'], ENT_QUOTES, 'UTF-8') . '</div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6 border-end">
                    <h6 class="text-uppercase fw-bold text-secondary mb-2">Satıcı Bilgileri</h6>
                    <div class="fw-bold">' . htmlspecialchars($_SESSION['firma_adi'] ?? 'ERSAN ELEKTRİK', ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="small text-muted">Erciyes Vergi Dairesi</div>
                </div>
                <div class="col-md-6 ps-md-4">
                    <h6 class="text-uppercase fw-bold text-secondary mb-2">Alıcı / Müşteri Bilgileri</h6>
                    <div class="fw-bold fs-6 text-dark">' . htmlspecialchars($invoice['alici_unvan'], ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="small"><strong>VKN / TCKN:</strong> ' . htmlspecialchars($invoice['alici_vkn_tckn'], ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="small"><strong>Vergi Dairesi:</strong> ' . htmlspecialchars($invoice['alici_vergi_dairesi'] ?? '-', ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="small"><strong>Adres:</strong> ' . htmlspecialchars($invoice['alici_adres'] ?? '-', ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($invoice['alici_ilce'] ?? '', ENT_QUOTES, 'UTF-8') . ' / ' . htmlspecialchars($invoice['alici_il'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Ürün / Hizmet Açıklaması</th>
                            <th class="text-end" style="width: 90px;">Miktar</th>
                            <th class="text-center" style="width: 70px;">Birim</th>
                            <th class="text-end" style="width: 110px;">Birim Fiyat</th>
                            <th class="text-center" style="width: 80px;">KDV %</th>
                            <th class="text-end" style="width: 100px;">KDV Tutarı</th>
                            <th class="text-end" style="width: 120px;">Satır Toplamı</th>
                        </tr>
                    </thead>
                    <tbody>';

        $sira = 1;
        foreach ($invoice['satirlar'] as $line) {
            $html .= '
                        <tr>
                            <td class="text-center">' . $sira++ . '</td>
                            <td class="fw-semibold">' . htmlspecialchars($line['urun_hizmet_adi'], ENT_QUOTES, 'UTF-8') . '</td>
                            <td class="text-end">' . number_format($line['miktar'], 2, ',', '.') . '</td>
                            <td class="text-center">' . htmlspecialchars($line['birim'], ENT_QUOTES, 'UTF-8') . '</td>
                            <td class="text-end">' . number_format($line['birim_fiyat'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                            <td class="text-center">%' . (int)$line['kdv_orani'] . '</td>
                            <td class="text-end">' . number_format($line['kdv_tutari'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                            <td class="text-end fw-bold">' . number_format($line['satir_toplami'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                        </tr>';
        }

        $html .= '
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">Mal / Hizmet Toplamı:</td>
                            <td class="text-end fw-semibold">' . number_format($invoice['satir_toplami'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Hesaplanan KDV:</td>
                            <td class="text-end fw-semibold">' . number_format($invoice['hesaplanan_kdv'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                        </tr>';

        if ((float)$invoice['tevkifat_tutari'] > 0) {
            $html .= '
                        <tr>
                            <td class="text-danger">KDV Tevkifatı (-):</td>
                            <td class="text-end text-danger fw-semibold">-' . number_format($invoice['tevkifat_tutari'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                        </tr>';
        }

        $html .= '
                        <tr class="border-top fs-5">
                            <td class="fw-bold text-primary">Ödenecek Tutar:</td>
                            <td class="text-end fw-bold text-primary">' . number_format($invoice['odenecek_tutar'], 2, ',', '.') . ' ' . $invoice['para_birimi'] . '</td>
                        </tr>
                    </table>
                </div>
            </div>';

        if (!empty($invoice['notlar'])) {
            $html .= '
            <div class="border-top pt-3 mt-3">
                <strong>Notlar:</strong> ' . nl2br(htmlspecialchars($invoice['notlar'], ENT_QUOTES, 'UTF-8')) . '
            </div>';
        }

        $html .= '</div>';
        return $html;
    }
}
