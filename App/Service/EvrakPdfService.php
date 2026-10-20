<?php

namespace App\Service;

use App\Helper\RichTextSanitizer;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

final class EvrakPdfService
{
    public function render(object|array $evrak): string
    {
        $data = (array) $evrak;
        $escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $date = !empty($data['tarih']) ? date('d.m.Y', strtotime((string) $data['tarih'])) : date('d.m.Y');
        $ustYaziGerekliDegil = !empty($data['ust_yazi_gerekli_degil']);
        $content = RichTextSanitizer::sanitize($data['aciklama'] ?? '');
        if ($ustYaziGerekliDegil) {
            if ($content === '') {
                $content = '<div style="text-align:center; padding: 25px 0; color: #64748b; font-style: italic; font-size: 11pt;">(Bu evrak üst yazısız olarak tanzim edilmiştir.)</div>';
            }
        } elseif ($content === '') {
            $content = '<p>İçerik girilmemiştir.</p>';
        }

        $organization = trim((string) ($data['kurum_basligi'] ?? $data['firma_unvan'] ?? $data['firma_adi'] ?? ''));
        $recipient = mb_strtoupper(trim((string) ($data['kurum_adi'] ?? '')), 'UTF-8');
        $signers = array_slice((array) ($data['imza_kisileri'] ?? []), 0, 3);
        $fontChoice = ($data['yazi_tipi'] ?? 'times_new_roman') === 'arial' ? 'arial' : 'timesnewroman';
        $fontSize = $fontChoice === 'arial' ? 11 : 12;

        $renderLogoHtml = function (?string $path) use ($escape): string {
            if ($path === null || $path === '' || !is_file($path)) {
                return '&nbsp;';
            }
            $imageInfo = @getimagesize($path);
            if ($imageInfo !== false && !empty($imageInfo[0]) && !empty($imageInfo[1])) {
                $origW = (float) $imageInfo[0];
                $origH = (float) $imageInfo[1];
                $maxW = 45.0; // mm (25% of 180mm printable width)
                $maxH = 20.0; // mm (max header height)

                $ratio = min($maxW / $origW, $maxH / $origH);
                $calcW = round($origW * $ratio, 2);
                $calcH = round($origH * $ratio, 2);

                return '<img src="' . $escape($path) . '" style="width:' . $calcW . 'mm;height:' . $calcH . 'mm;">';
            }
            return '<img src="' . $escape($path) . '" style="max-height:20mm;max-width:45mm;">';
        };

        $solLogoPath = (string) ($data['sol_logo_path'] ?? $data['logo_path'] ?? '');
        $sagLogoPath = (string) ($data['sag_logo_path'] ?? '');

        $solLogoHtml = $renderLogoHtml($solLogoPath);
        $sagLogoHtml = $renderLogoHtml($sagLogoPath);

        $hasAntetSettings = isset($data['evrak_antet_baslik_1']) || isset($data['evrak_antet_baslik_2']) || isset($data['evrak_antet_baslik_3']) || isset($data['evrak_antet_baslik_4']);

        $headerLines = [];
        if ($hasAntetSettings) {
            for ($i = 1; $i <= 4; $i++) {
                $line = trim((string) ($data["evrak_antet_baslik_{$i}"] ?? ''));
                $headerLines[] = '<div>' . ($line !== '' ? $escape($line) : '&nbsp;') . '</div>';
            }
        } else {
            $organization = trim((string) ($data['kurum_basligi'] ?? $data['firma_unvan'] ?? $data['firma_adi'] ?? ''));
            if ($organization !== '') {
                $headerLines[] = '<div>' . $escape($organization) . '</div>';
                $headerLines[] = '<div>&nbsp;</div>';
                $headerLines[] = '<div>&nbsp;</div>';
                $headerLines[] = '<div>&nbsp;</div>';
            }
        }
        $headingHtml = implode('', $headerLines);

        $footerCustomLines = [];
        for ($i = 1; $i <= 4; $i++) {
            $line = trim((string) ($data["evrak_alt_bilgi_{$i}"] ?? ''));
            if ($line !== '') {
                $footerCustomLines[] = '<div>' . $escape($line) . '</div>';
            }
        }

        if (!empty($footerCustomLines)) {
            $contact = implode('', $footerCustomLines);
        } else {
            $address = trim((string) ($data['firma_adres'] ?? ''));
            $phone = trim((string) ($data['firma_telefon'] ?? ''));
            $website = trim((string) ($data['firma_web_sitesi'] ?? ''));
            $kepAddress = trim((string) ($data['firma_kep_adresi'] ?? ''));
            $contactLines = [];
            if ($address !== '' || $phone !== '') {
                $firstLine = [];
                if ($address !== '') {
                    $firstLine[] = $escape($address);
                }
                if ($phone !== '') {
                    $firstLine[] = 'Tel: ' . $escape($phone);
                }
                $contactLines[] = implode(' &nbsp; | &nbsp; ', $firstLine);
            }
            if ($website !== '') {
                $contactLines[] = '<strong>Web:</strong> ' . $escape($website);
            }
            if ($kepAddress !== '') {
                $contactLines[] = '<strong>KEP:</strong> ' . $escape($kepAddress);
            }
            $contact = implode('<br>', $contactLines);
        }

        $recipientLines = ['<div>' . $escape($recipient) . '</div>'];
        if (!empty($data['muhatap_alt_birim'])) {
            $altBirim = mb_strtoupper(trim((string) $data['muhatap_alt_birim']), 'UTF-8');
            $recipientLines[] = '<div>' . $escape($altBirim) . '</div>';
        }
        if (!empty($data['muhatap_adres'])) {
            $recipientLines[] = '<div class="recipient-address">(' . nl2br($escape($data['muhatap_adres'])) . ')</div>';
        }

        $interests = self::lines($data['ilgiler'] ?? '');
        $interestHtml = '';
        if ($interests !== []) {
            $interestRows = '';
            foreach ($interests as $index => $interest) {
                $prefix = chr(ord('a') + $index) . ')&nbsp;';
                $mb = ($index < count($interests) - 1) ? 'margin-bottom:1mm;' : '';
                $interestRows .= '<div style="' . $mb . '"><span class="interest-prefix">' . $prefix . '</span>' . $escape($interest) . '</div>';
            }
            $interestHtml = '<table class="interest"><tr><td class="section-label">İlgi</td><td class="separator">:</td><td>' . $interestRows . '</td></tr></table>';
        }

        $attachments = self::lines($data['ekler'] ?? '');
        $attachmentHtml = '';
        if (count($attachments) === 1) {
            $attachmentHtml = '<div class="attachments"><strong>Ek:</strong> ' . $escape($attachments[0]) . '</div>';
        } elseif ($attachments !== []) {
            $rows = '';
            foreach ($attachments as $index => $attachment) {
                $rows .= '<div>' . ($index + 1) . '- ' . $escape($attachment) . '</div>';
            }
            $attachmentHtml = '<table class="attachments"><tr><td class="section-label"><strong>Ek</strong></td><td class="separator">:</td><td>' . $rows . '</td></tr></table>';
        }

        $signatureHtml = self::signatureHtml($signers, $escape);
        $preparer = (object) ($data['hazirlayan_kullanici'] ?? []);
        $contactPersonHtml = !empty($preparer->adi_soyadi)
            ? '<div><strong>Bilgi için:</strong> ' . $escape($preparer->adi_soyadi) . '</div>' .
              (!empty($preparer->unvani) ? '<div>' . $escape($preparer->unvani) . '</div>' : '') .
              (!empty($preparer->telefon) ? '<div>' . $escape($preparer->telefon) . '</div>' : '')
            : '';

        $isPadesSigningCopy = !empty($data['pades_imzalama_kopyasi']);
        // Gerçek PAdES belgesindeki görünür doğrulama alanı kapatılamaz. Eski
        // `evrak_eimza_goster` ayarı yalnız sanal/eski PDF gösterimini yönetir.
        $showEimzaInfo = $isPadesSigningCopy
            || !isset($data['evrak_eimza_goster'])
            || (string) $data['evrak_eimza_goster'] === '1'
            || $data['evrak_eimza_goster'] === true
            || $data['evrak_eimza_goster'] === 1;
        $eImzaOnayli = (($data['onay_durumu'] ?? 'taslak') === 'onaylandi')
            && (!empty($data['pades_pdf_sha256']) || $isPadesSigningCopy)
            && $showEimzaInfo;
        $eImzaNotu = 'Bu belge, güvenli elektronik imza ile imzalanmıştır.';
        $verificationSeed = implode('|', [
            (string) ($data['firma_id'] ?? ''),
            (string) ($data['id'] ?? ''),
            (string) ($data['evrak_no'] ?? ''),
            (string) ($data['tarih'] ?? ''),
        ]);
        $verificationCode = 'EVR-' . strtoupper(substr(hash('sha256', $verificationSeed), 0, 16));
        $verificationPin = str_pad((string) (hexdec(substr(hash('sha256', $verificationSeed . '|pin'), 0, 8)) % 100000), 5, '0', STR_PAD_LEFT);
        $trackingHost = trim((string) ($_ENV['APP_BASE'] ?? $_SERVER['HTTP_HOST'] ?? 'app.ersantr.com'), " \t\n\r\0\x0B\"'");
        $trackingHost = preg_replace('#^https?://#i', '', $trackingHost);
        $trackingAddress = 'https://' . rtrim((string) $trackingHost, '/');
        $visibleSignatureInfo = $eImzaOnayli
            ? '<div class="footer-eimza-panel">'
                . '<div class="footer-eimza-title">' . $escape($eImzaNotu) . '</div>'
                . '<table class="footer-eimza-meta"><tr>'
                . '<td><strong>Belge Doğrulama Kodu:</strong> ' . $escape($verificationCode)
                . ' <strong>PIN Kodu:</strong> ' . $escape($verificationPin) . '</td>'
                . '<td class="footer-eimza-tracking"><strong>Belge Takip Adresi:</strong> ' . $escape($trackingAddress) . '</td>'
                . '</tr></table>'
                . '</div>'
            : '';

        // Kurumsal alt bilgi şablonu değişmeden kalır; elektronik imza bilgileri
        // yalnızca mevcut bloğun önüne ve sonuna eklenir.
        $corporateFooterHtml = '<div class="footer"><table class="footer-table"><tr><td class="footer-left">'
            . $contact . '</td><td class="footer-right">' . $contactPersonHtml . '</td></tr></table></div>';
        $footerHtml = $visibleSignatureInfo
            . $corporateFooterHtml
            . ($eImzaOnayli ? '<div class="footer-eimza-alt">' . $escape($eImzaNotu) . '</div>' : '');

        $evrakNoVal = trim((string) ($data['evrak_no'] ?? ''));
        $recordInfoText = 'Evrak Tarih ve Sayısı: ' . $escape($date) . ($evrakNoVal !== '' ? '-' . $escape($evrakNoVal) : '');
        $recordInfoHtml = '<div class="record-info">' . $recordInfoText . '</div>';

        $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><style>
            body{font-family:' . $fontChoice . ';font-size:' . $fontSize . 'pt;color:#000;line-height:1.2;margin:0;padding:0}
            p{margin:0;padding:0}.letterhead{width:100%;border-collapse:collapse;margin-bottom:0;margin-top:3mm}
            .letterhead td{border:0;vertical-align:middle}.logo{width:25%;text-align:left}.heading{width:50%;text-align:center;font-weight:bold;line-height:1.15}.heading div{margin-bottom:2px}.spacer{width:25%;text-align:right}
            .document-info{width:100%;border-collapse:collapse;margin-top:18mm;margin-bottom:8.5mm;line-height:1.2}.document-info td{border:0;padding:0;vertical-align:top}
            .recipient{text-align:center;font-weight:normal;line-height:1.25;margin:0 0 8.5mm 0}.recipient-address{font-weight:normal;text-transform:none;font-size:' . max(9, $fontSize - 1) . 'pt;margin-top:1mm}
            .interest,.attachments{width:100%;border-collapse:collapse;margin:0 0 8.5mm}.interest td,.attachments td{border:0;vertical-align:top;padding:0}.section-label{width:12mm}.separator{width:3mm}.interest-prefix{display:inline-block;width:8mm}
            .content{font-size:' . $fontSize . 'pt;line-height:1.2;text-align:justify}.content p,.content div{line-height:1.2!important;margin:0!important;padding:0!important}.content p{text-indent:12.5mm;text-align:justify}.content ul,.content ol{margin:0 0 0 12mm;padding-top:0;padding-bottom:0}
            .content table{width:100%;border-collapse:collapse;margin:3mm 0}.content td,.content th{border:0.3mm solid #000;padding:1.5mm;font-size:10pt}
            .content h1,.content h2,.content h3,.content h4{font-size:12pt;margin:3mm 0;font-weight:bold}
            .signatures{width:100%;border-collapse:collapse;margin-top:14mm}.signatures td{border:0;text-align:center;vertical-align:top;line-height:1.25}.signature-name{font-weight:normal}.attachments{margin-top:8mm}
            .record-info{font-size:10pt;line-height:1.2;margin-bottom:4mm}.footer,.footer td,.footer div,.footer span{font-size:10px!important;line-height:1.2}.footer{border-top:0.2mm solid #e53935;padding-top:1.2mm}
            .footer-eimza-panel{border:0;border-bottom:0.6mm solid #e53935;padding:1.2mm 2mm 1.5mm;margin-bottom:1.5mm;color:#111;background:#fff}
            .footer-eimza-title{font-size:9px!important;color:#e53935;text-align:center;font-weight:bold;line-height:1.2;padding-bottom:1mm}
            .footer-eimza-meta{width:100%;border-collapse:collapse;table-layout:fixed}.footer-eimza-meta td{border:0;font-size:8px!important;line-height:1.2;padding:.4mm 0;vertical-align:top}.footer-eimza-tracking{text-align:right}
            .footer-eimza-alt{font-size:12px;color:#a0a0a0;text-align:left;line-height:1.2;padding-top:1.2mm}.footer-table{width:100%;table-layout:fixed;border-collapse:collapse}.footer-table td{width:50%;border:0;vertical-align:top;overflow-wrap:break-word}.footer-left{text-align:left;padding-right:4mm}.footer-right{text-align:right;padding-left:4mm}
        </style></head><body>
        ' . $recordInfoHtml . '
        <table class="letterhead"><tr>
            <td class="logo">' . $solLogoHtml . '</td>
            <td class="heading">' . $headingHtml . '</td>
            <td class="spacer" style="text-align:right;">' . $sagLogoHtml . '</td>
        </tr></table>
        <table class="document-info">
            <tr>
                <td style="width:70%;">
                    <table style="border-collapse:collapse;margin:0;padding:0;">
                        <tr>
                            <td style="width:12mm;white-space:nowrap;padding:0 0 1mm 0;">Sayı</td>
                            <td style="width:3mm;white-space:nowrap;padding:0 0 1mm 0;">:</td>
                            <td style="padding:0 0 1mm 0;">' . $escape($data['evrak_no'] ?? '') . '</td>
                        </tr>
                        <tr>
                            <td style="width:12mm;white-space:nowrap;padding:0;">Konu</td>
                            <td style="width:3mm;white-space:nowrap;padding:0;">:</td>
                            <td style="padding:0;">' . $escape($data['konu'] ?? '') . '</td>
                        </tr>
                    </table>
                </td>
                <td style="width:30%;text-align:right;vertical-align:top;">' . $escape($date) . '</td>
            </tr>
        </table>
        <div class="recipient">' . implode('', $recipientLines) . '</div>
        ' . $interestHtml . '
        <div class="content">' . $content . '</div>
        ' . $signatureHtml . '
        ' . $attachmentHtml . '
        </body></html>';

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        $projectRoot = dirname(__DIR__, 2);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 5,
            'margin_right' => 15,
            'margin_bottom' => $eImzaOnayli ? 40 : 30,
            'margin_left' => 15,
            'margin_footer' => 5,
            'fontDir' => array_merge($fontDirs, [
                $projectRoot . '/fonts/times',
                '/usr/share/fonts/liberation',
            ]),
            'fontdata' => $fontData + [
                'timesnewroman' => [
                    'R' => 'times.ttf',
                    'B' => 'timesbd.ttf',
                    'I' => 'timesi.ttf',
                    'BI' => 'timesbi.ttf',
                ],
                'arial' => [
                    'R' => 'LiberationSans-Regular.ttf',
                    'B' => 'LiberationSans-Bold.ttf',
                    'I' => 'LiberationSans-Italic.ttf',
                    'BI' => 'LiberationSans-BoldItalic.ttf',
                ],
            ],
            'default_font' => $fontChoice,
            'default_font_size' => $fontSize,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetTitle(($data['evrak_no'] ?? '') . ' - ' . ($data['konu'] ?? 'Evrak'));
        $ekDosyalari = (array) ($data['ek_dosya_yollari'] ?? []);

        if (!$ustYaziGerekliDegil) {
            $mpdf->SetHTMLFooter($footerHtml);
            $mpdf->WriteHTML($html);

            if ($ekDosyalari !== []) {
                $mpdf->writeHTMLFooters();
                $mpdf->SetHTMLFooter('');
                $mpdf->SetHTMLFooter('', 'E');
                $mpdf->SetHTMLFooter('', 'O');
                $mpdf->SetHTMLHeader('');
                $mpdf->SetHTMLHeader('', 'E');
                $mpdf->SetHTMLHeader('', 'O');
            }
        } elseif ($ekDosyalari === []) {
            $mpdf->WriteHTML('
                <!doctype html><html lang="tr"><head><meta charset="UTF-8"><style>
                    body{font-family:sans-serif;text-align:center;padding-top:50mm;color:#64748b;}
                </style></head><body>
                    <div style="font-size:16pt;font-weight:bold;color:#1e293b;margin-bottom:6mm;">' . $escape($data['konu'] ?? 'Evrak') . '</div>
                    <div style="font-size:11pt;margin-bottom:4mm;">(Bu evrak üst yazısız olarak tanzim edilmiştir.)</div>
                    <div style="font-size:10pt;color:#94a3b8;">Eklenmiş herhangi bir belge bulunmamaktadır.</div>
                </body></html>
            ');
        }

        foreach ($ekDosyalari as $ekIndex => $ek) {
            $filePath = is_array($ek) ? ($ek['path'] ?? '') : (string) $ek;
            $fileName = is_array($ek) ? ($ek['name'] ?? 'Ek ' . ($ekIndex + 1)) : basename($filePath);

            if (empty($filePath) || !file_exists($filePath) || !is_readable($filePath)) {
                continue;
            }

            $fileNameExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $filePathExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $ext = $fileNameExt !== '' ? $fileNameExt : $filePathExt;

            $mime = is_array($ek) ? ($ek['type'] ?? '') : '';
            $finfoMime = @(new \finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: '';

            $isPdf = ($ext === 'pdf' || $mime === 'application/pdf' || $mime === 'application/x-pdf' || $finfoMime === 'application/pdf' || $finfoMime === 'application/x-pdf');
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)
                || str_starts_with($finfoMime, 'image/')
                || str_starts_with($mime, 'image/');

            if ($isPdf) {
                $imported = false;

                // 1. Doğrudan içe aktarma denemesi
                try {
                    $pageCount = $mpdf->setSourceFile($filePath);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                        $mpdf->SetHTMLFooter('');
                        $mpdf->SetHTMLFooter('', 'E');
                        $mpdf->SetHTMLFooter('', 'O');
                        $mpdf->SetHTMLHeader('');
                        $mpdf->SetHTMLHeader('', 'E');
                        $mpdf->SetHTMLHeader('', 'O');
                        $tplId = $mpdf->importPage($pageNo);
                        $mpdf->useTemplate($tplId);
                    }
                    $imported = true;
                } catch (\Throwable $e) {
                    error_log('mPDF Direct Import Error (' . $fileName . '): ' . $e->getMessage());
                }

                // 2. Sıkıştırma hatası durumunda qpdf veya ghostscript ile normalize edip aktarma
                if (!$imported) {
                    $normalizedPath = self::normalizePdfForImport($filePath);
                    if ($normalizedPath !== null) {
                        try {
                            $pageCount = $mpdf->setSourceFile($normalizedPath);
                            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                                $mpdf->SetHTMLFooter('');
                                $mpdf->SetHTMLFooter('', 'E');
                                $mpdf->SetHTMLFooter('', 'O');
                                $mpdf->SetHTMLHeader('');
                                $mpdf->SetHTMLHeader('', 'E');
                                $mpdf->SetHTMLHeader('', 'O');
                                $tplId = $mpdf->importPage($pageNo);
                                $mpdf->useTemplate($tplId);
                            }
                            $imported = true;
                        } catch (\Throwable $e2) {
                            error_log('mPDF Normalized Import Error (' . $fileName . '): ' . $e2->getMessage());
                        }
                        @unlink($normalizedPath);
                    }
                }

                // 3. Normalize de edilemezse pdftoppm ile sayfaları yüksek çözünürlükte görsele çevirip ekleme
                if (!$imported && is_executable('/usr/bin/pdftoppm')) {
                    $tempPrefix = sys_get_temp_dir() . '/ppm_' . bin2hex(random_bytes(8)) . '_';
                    $cmd = '/usr/bin/pdftoppm -png -r 150 ' . escapeshellarg($filePath) . ' ' . escapeshellarg($tempPrefix) . ' 2>/dev/null';
                    @exec($cmd, $out, $ret);
                    $pngFiles = glob($tempPrefix . '*.png');
                    if (!empty($pngFiles)) {
                        natsort($pngFiles);
                        foreach ($pngFiles as $pngPath) {
                            $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                            $mpdf->SetHTMLFooter('');
                            $mpdf->SetHTMLFooter('', 'E');
                            $mpdf->SetHTMLFooter('', 'O');
                            $mpdf->SetHTMLHeader('');
                            $mpdf->SetHTMLHeader('', 'E');
                            $mpdf->SetHTMLHeader('', 'O');
                            $mpdf->WriteHTML('
                                <div style="text-align:center; padding-top:5mm;">
                                    <img src="' . htmlspecialchars($pngPath, ENT_QUOTES, 'UTF-8') . '" style="max-width:180mm; max-height:260mm; object-fit:contain;" />
                                </div>
                            ');
                            @unlink($pngPath);
                        }
                        $imported = true;
                    }
                }

                // 4. Dosya tamamen bozuk veya okunamıyorsa son çare bilgi kartı
                if (!$imported) {
                    $mpdf->AddPage('P', '', '', '', '', 15, 15, 15, 15, 0, 0);
                    $mpdf->SetHTMLFooter('');
                    $mpdf->SetHTMLFooter('', 'E');
                    $mpdf->SetHTMLFooter('', 'O');
                    $mpdf->SetHTMLHeader('');
                    $mpdf->SetHTMLHeader('', 'E');
                    $mpdf->SetHTMLHeader('', 'O');
                    $mpdf->WriteHTML('
                        <div style="text-align:center; padding-top:40mm; font-family:sans-serif;">
                            <div style="font-size:14pt; font-weight:bold; color:#1e293b; margin-bottom:4mm;">EK ' . ($ekIndex + 1) . ': ' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</div>
                            <div style="font-size:10pt; color:#64748b; margin-bottom:6mm;">(PDF Belgesi Evrak Kaydına Eklenmiştir)</div>
                            <div style="display:inline-block; border:1px solid #cbd5e1; border-radius:8px; padding:12px 24px; background:#f8fafc; font-size:9pt; color:#475569;">
                                Dosya Adı: <b>' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</b>
                            </div>
                        </div>
                    ');
                }
            } elseif ($isImage) {
                $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                $mpdf->SetHTMLFooter('');
                $mpdf->SetHTMLFooter('', 'E');
                $mpdf->SetHTMLFooter('', 'O');
                $mpdf->SetHTMLHeader('');
                $mpdf->SetHTMLHeader('', 'E');
                $mpdf->SetHTMLHeader('', 'O');
                $mpdf->WriteHTML('
                    <div style="text-align:center; padding-top:5mm;">
                        <div style="font-size:10pt; font-weight:bold; margin-bottom:4mm; font-family:sans-serif;">EK ' . ($ekIndex + 1) . ': ' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</div>
                        <img src="' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . '" style="max-width:180mm; max-height:240mm; object-fit:contain;" />
                    </div>
                ');
            } else {
                $mpdf->AddPage('P', '', '', '', '', 15, 15, 15, 15, 0, 0);
                $mpdf->SetHTMLFooter('');
                $mpdf->SetHTMLFooter('', 'E');
                $mpdf->SetHTMLFooter('', 'O');
                $mpdf->SetHTMLHeader('');
                $mpdf->SetHTMLHeader('', 'E');
                $mpdf->SetHTMLHeader('', 'O');
                $mpdf->WriteHTML('
                    <div style="text-align:center; padding-top:40mm; font-family:sans-serif;">
                        <div style="font-size:14pt; font-weight:bold; color:#1e293b; margin-bottom:4mm;">EK ' . ($ekIndex + 1) . ': ' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</div>
                        <div style="font-size:10pt; color:#64748b; margin-bottom:6mm;">(Elektronik Dosya Eki — ' . strtoupper($ext ?: 'DOSYA') . ')</div>
                        <div style="display:inline-block; border:1px solid #cbd5e1; border-radius:8px; padding:12px 24px; background:#f8fafc; font-size:9pt; color:#475569;">
                            Dosya Adı: <b>' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</b>
                        </div>
                    </div>
                ');
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private static function lines(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $value) ?: []), fn($line) => $line !== ''));
    }

    private static function signatureHtml(array $signers, callable $escape): string
    {
        $signers = array_values($signers);
        $count = count($signers);
        if ($count === 0) {
            return '';
        }

        $col1 = '&nbsp;';
        $col2 = '&nbsp;';
        $col3 = '&nbsp;';

        $renderCell = function ($signer) use ($escape) {
            $signer = (object) $signer;
            $name = $escape($signer->adi_soyadi ?? '');
            $kiminAdina = !empty($signer->kimin_adina) ? '<div>' . $escape($signer->kimin_adina) . '</div>' : '';
            $unvan = !empty($signer->imza_unvani) ? '<div>' . $escape($signer->imza_unvani) . '</div>' : '';
            return '<div class="signature-name">' . $name . '</div>' . $kiminAdina . $unvan;
        };

        if ($count === 1) {
            $col3 = $renderCell($signers[0]);
        } elseif ($count === 2) {
            $col2 = $renderCell($signers[0]);
            $col3 = $renderCell($signers[1]);
        } else {
            $col1 = $renderCell($signers[0]);
            $col2 = $renderCell($signers[1]);
            $col3 = $renderCell($signers[2]);
        }

        return '<table class="signatures"><tr>' .
            '<td style="width:33.33%;">' . $col1 . '</td>' .
            '<td style="width:33.33%;">' . $col2 . '</td>' .
            '<td style="width:33.33%;">' . $col3 . '</td>' .
            '</tr></table>';
    }

    private static function normalizePdfForImport(string $sourcePdfPath): ?string
    {
        $tempNormalized = sys_get_temp_dir() . '/norm_' . bin2hex(random_bytes(8)) . '.pdf';

        // 1. QPDF ile sıkıştırmayı açma ve PDF 1.4 formatına getirme (en hızlı ve kaliteli)
        if (is_executable('/usr/bin/qpdf')) {
            $cmd = '/usr/bin/qpdf --decrypt --object-streams=disable ' . escapeshellarg($sourcePdfPath) . ' ' . escapeshellarg($tempNormalized) . ' 2>/dev/null';
            @exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($tempNormalized) && filesize($tempNormalized) > 0) {
                return $tempNormalized;
            }
        }

        // 2. Ghostscript (CompatibilityLevel=1.4) ile normalize etme
        if (is_executable('/usr/bin/gs')) {
            $cmd = '/usr/bin/gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=' . escapeshellarg($tempNormalized) . ' ' . escapeshellarg($sourcePdfPath) . ' 2>/dev/null';
            @exec($cmd, $out, $ret);
            if ($ret === 0 && file_exists($tempNormalized) && filesize($tempNormalized) > 0) {
                return $tempNormalized;
            }
        }

        return null;
    }

    /**
     * Verilen evrak ID'sine ait üst yazı ve eklerin birleştirilmiş PDF'ini geçici dosya olarak üretir
     *
     * @param int $evrakId
     * @return string Geçici PDF dosyasının tam dosya yolu
     * @throws \Exception
     */
    public function generateTempPdf(int $evrakId, bool $padesSigningCopy = false): string
    {
        $evrakModel = new \App\Model\EvrakTakipModel();
        $evrak = $evrakModel->getById($evrakId);
        if (!$evrak) {
            throw new \Exception("Evrak bulunamadı (ID: {$evrakId})");
        }
        if (!$padesSigningCopy && !empty($evrak->pades_pdf_yolu)) {
            $signedPath = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->pades_pdf_yolu, '/');
            if (!is_file($signedPath)) {
                throw new \Exception('İmzalı PAdES PDF dosyası bulunamadı.');
            }
            $signedPdf = \App\Helper\Security::decryptFile((string) file_get_contents($signedPath));
            if (!empty($evrak->pades_pdf_sha256) && !hash_equals((string) $evrak->pades_pdf_sha256, hash('sha256', $signedPdf))) {
                throw new \Exception('İmzalı PAdES PDF bütünlük kontrolünden geçemedi.');
            }
            $tmpSigned = sys_get_temp_dir() . '/evrak_pades_' . $evrakId . '_' . bin2hex(random_bytes(6)) . '.pdf';
            file_put_contents($tmpSigned, $signedPdf, LOCK_EX);
            return $tmpSigned;
        }

        $firmId = (int) ($evrak->firma_id ?? ($_SESSION['firma_id'] ?? 1));
        $firma = (new \App\Model\FirmaModel())->find($firmId);
        $settingsModel = new \App\Model\SettingsModel();
        $settings = $settingsModel->getAllSettingsAsKeyValue($firmId);

        $signerIds = json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: [];
        $signingUsers = $evrakModel->getSigningUsersByIds($signerIds);
        $userMap = [];
        foreach ($signingUsers as $u) {
            $userMap[(int) $u->id] = $u;
        }

        $kiminAdinaList = json_decode((string) ($evrak->imza_kimin_adina_json ?? '[]'), true) ?: [];
        $orderedSigners = [];
        foreach ($signerIds as $idx => $sId) {
            if (isset($userMap[(int) $sId])) {
                $uObj = clone $userMap[(int) $sId];
                $uObj->kimin_adina = $kiminAdinaList[$idx] ?? '';
                $orderedSigners[] = $uObj;
            }
        }

        $firmaTel = (($firma->telefon ?? '') === '0') ? '' : trim((string) ($firma->telefon ?? ''));
        $preparerId = !empty($evrak->olusturan_kullanici_id) ? (int) $evrak->olusturan_kullanici_id : 0;
        $preparerUser = $preparerId > 0 ? (new \App\Model\UserModel())->find($preparerId) : null;
        $preparerPhone = $firmaTel !== '' ? $firmaTel : trim((string) ($preparerUser->telefon ?? ''));

        // Evrak ek dosyaları
        $ekDosyaYollari = [];
        $evrakEkleri = $evrakModel->getAttachments($evrakId);
        foreach ($evrakEkleri as $att) {
            if (!empty($att->dosya_yolu)) {
                $fullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $att->dosya_yolu, '/');
                if (file_exists($fullPath)) {
                    $ekDosyaYollari[] = [
                        'path' => $fullPath,
                        'name' => $att->dosya_adi,
                        'type' => $att->mime_tipi,
                    ];
                }
            }
        }

        $solLogo = (string) ($settings['sol_logo_yolu'] ?? $firma->logo_yolu ?? '');
        $sagLogo = (string) ($settings['sag_logo_yolu'] ?? '');

        $pdfEvrak = (object) [
            'id' => $evrak->id,
            'firma_id' => $firmId,
            'evrak_tipi' => $evrak->evrak_tipi ?? 'giden',
            'tarih' => $evrak->tarih,
            'evrak_no' => $evrak->evrak_no,
            'konu' => $evrak->konu,
            'kurum_adi' => $evrak->kurum_adi,
            'muhatap_alt_birim' => $evrak->muhatap_alt_birim ?? '',
            'muhatap_adres' => $evrak->muhatap_adres ?? '',
            'aciklama' => $evrak->aciklama ?? '',
            'ust_yazi_gerekli_degil' => !empty($evrak->ust_yazi_gerekli_degil) ? 1 : 0,
            'yazi_tipi' => $evrak->yazi_tipi ?? 'times_new_roman',
            'ilgiler' => $evrak->ilgiler ?? '',
            'ekler' => $evrak->ekler ?? '',
            'imza_kisileri' => $orderedSigners,
            'hazirlayan_kullanici' => [
                'adi_soyadi' => $preparerUser->adi_soyadi ?? '',
                'unvani' => trim(($preparerUser->unvani ?? '') ?: ($preparerUser->gorevi ?? '')),
                'telefon' => $preparerPhone,
            ],
            'kurum_basligi' => trim((string) ($firma->firma_unvan ?? '')) ?: trim((string) ($firma->firma_adi ?? '')),
            'firma_adres' => (($firma->adres ?? '') === '0') ? '' : (string) ($firma->adres ?? ''),
            'firma_telefon' => $firmaTel,
            'firma_web_sitesi' => (($firma->web_sitesi ?? '') === '0') ? '' : trim((string) ($firma->web_sitesi ?? '')),
            'firma_kep_adresi' => (($firma->kep_adresi ?? '') === '0') ? '' : trim((string) ($firma->kep_adresi ?? '')),
            'logo_path' => $solLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($solLogo, '/') : '',
            'sol_logo_path' => $solLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($solLogo, '/') : '',
            'sag_logo_path' => $sagLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($sagLogo, '/') : '',
            'evrak_antet_baslik_1' => trim((string) ($settings['evrak_antet_baslik_1'] ?? '')),
            'evrak_antet_baslik_2' => trim((string) ($settings['evrak_antet_baslik_2'] ?? '')),
            'evrak_antet_baslik_3' => trim((string) ($settings['evrak_antet_baslik_3'] ?? '')),
            'evrak_antet_baslik_4' => trim((string) ($settings['evrak_antet_baslik_4'] ?? '')),
            'evrak_alt_bilgi_1' => trim((string) ($settings['evrak_alt_bilgi_1'] ?? '')),
            'evrak_alt_bilgi_2' => trim((string) ($settings['evrak_alt_bilgi_2'] ?? '')),
            'evrak_alt_bilgi_3' => trim((string) ($settings['evrak_alt_bilgi_3'] ?? '')),
            'evrak_alt_bilgi_4' => trim((string) ($settings['evrak_alt_bilgi_4'] ?? '')),
            'evrak_eimza_goster' => (string) ($settings['evrak_eimza_goster'] ?? '1'),
            'e_imza_belge_ozeti' => $evrak->e_imza_belge_ozeti ?? null,
            'e_imza_onay_tarihi' => $evrak->e_imza_onay_tarihi ?? null,
            'onay_durumu' => $padesSigningCopy ? 'onaylandi' : ($evrak->onay_durumu ?? null),
            'pades_pdf_sha256' => $evrak->pades_pdf_sha256 ?? null,
            'pades_imzalama_kopyasi' => $padesSigningCopy ? 1 : 0,
            'ek_dosya_yollari' => $ekDosyaYollari,
        ];

        $pdfContent = $this->render($pdfEvrak);
        $tmpFile = sys_get_temp_dir() . '/evrak_' . $evrakId . '_' . bin2hex(random_bytes(6)) . '.pdf';
        file_put_contents($tmpFile, $pdfContent);
        return $tmpFile;
    }
}
