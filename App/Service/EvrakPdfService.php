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
        $content = RichTextSanitizer::sanitize($data['aciklama'] ?? '');
        if ($content === '') {
            $content = '<p>İçerik girilmemiştir.</p>';
        }

        $organization = trim((string) ($data['kurum_basligi'] ?? $data['firma_unvan'] ?? $data['firma_adi'] ?? ''));
        $recipient = mb_strtoupper(trim((string) ($data['kurum_adi'] ?? '')), 'UTF-8');
        $signers = array_slice((array) ($data['imza_kisileri'] ?? []), 0, 3);
        $fontChoice = ($data['yazi_tipi'] ?? 'times_new_roman') === 'arial' ? 'arial' : 'timesnewroman';
        $fontSize = $fontChoice === 'arial' ? 11 : 12;
        $logoPath = (string) ($data['logo_path'] ?? '');
        $logo = $logoPath !== '' && is_file($logoPath)
            ? '<img src="' . $escape($logoPath) . '" style="width:22mm;height:22mm;object-fit:contain">'
            : '';

        $contactParts = array_values(array_filter([
            trim((string) ($data['firma_adres'] ?? '')),
            trim((string) ($data['firma_telefon'] ?? '')) !== '' ? 'Tel: ' . trim((string) $data['firma_telefon']) : '',
        ]));
        $contact = implode(' &nbsp; | &nbsp; ', array_map($escape, $contactParts));

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
                $interestRows .= '<div style="margin-bottom:1mm;"><span class="interest-prefix">' . $prefix . '</span>' . $escape($interest) . '</div>';
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

        $footerHtml = '<div class="footer"><table class="footer-table"><tr><td>' . $contact . '</td><td class="footer-right">' . $contactPersonHtml . '</td></tr></table></div>';

        $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><style>
            @page{margin-top:5mm;margin-right:15mm;margin-bottom:30mm;margin-left:15mm}
            body{font-family:' . $fontChoice . ';font-size:' . $fontSize . 'pt;color:#000;line-height:1.2;margin:0;padding:0}
            p{margin:0;padding:0}.letterhead{width:100%;border-collapse:collapse;height:25mm;margin-bottom:9mm}
            .letterhead td{border:0;vertical-align:middle}.logo{width:25%;text-align:left}.heading{width:50%;text-align:center;font-weight:bold;line-height:1.15}.heading div{margin-bottom:2px}.spacer{width:25%}
            .document-info{width:100%;border-collapse:collapse;margin-bottom:8mm;line-height:1.2}.document-info td{border:0;padding:0;vertical-align:top}
            .recipient{text-align:center;font-weight:normal;line-height:1.25;margin:0 8mm 8mm}.recipient-address{font-weight:normal;text-transform:none;font-size:' . max(9, $fontSize - 1) . 'pt;margin-top:1mm}
            .interest,.attachments{width:100%;border-collapse:collapse;margin:0 0 5mm}.interest td,.attachments td{border:0;vertical-align:top;padding:0}.section-label{width:10mm}.separator{width:3mm}.interest-prefix{display:inline-block;width:8mm}
            .content{font-size:' . $fontSize . 'pt;line-height:1.2;text-align:justify}.content p,.content div{line-height:1.2!important;margin:0!important;padding:0!important}.content p{text-indent:12.5mm;text-align:justify}.content ul,.content ol{margin:0 0 0 12mm;padding-top:0;padding-bottom:0}
            .content table{width:100%;border-collapse:collapse;margin:3mm 0}.content td,.content th{border:0.3mm solid #000;padding:1.5mm;font-size:10pt}
            .content h1,.content h2,.content h3,.content h4{font-size:12pt;margin:3mm 0;font-weight:bold}
            .signatures{width:100%;border-collapse:collapse;margin-top:14mm}.signatures td{border:0;text-align:center;vertical-align:top;line-height:1.25}.signature-name{font-weight:normal}.attachments{margin-top:8mm}
            .record-info{font-size:10pt;line-height:1.2;margin-bottom:4mm}.footer,.footer td,.footer div,.footer span{font-size:10px!important;line-height:1.2}.footer{border-top:0.3mm solid #000;padding-top:2mm}.footer-table{width:100%;border-collapse:collapse}.footer-table td{border:0;vertical-align:top}.footer-right{text-align:right}
        </style></head><body>
        <div class="record-info">Evrak Tarih ve Sayısı: ' . $escape($date) . '-' . $escape($data['evrak_no'] ?? '') . '</div>
        <table class="letterhead"><tr>
            <td class="logo">' . $logo . '</td>
            <td class="heading"><div>' . $escape($organization) . '</div></td>
            <td class="spacer">&nbsp;</td>
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
        $mpdf->SetHTMLFooter($footerHtml);
        $mpdf->WriteHTML($html);

        $ekDosyalari = (array) ($data['ek_dosya_yollari'] ?? []);
        $mpdf->SetHTMLFooter();

        foreach ($ekDosyalari as $ekIndex => $ek) {
            $filePath = is_array($ek) ? ($ek['path'] ?? '') : (string) $ek;
            $fileName = is_array($ek) ? ($ek['name'] ?? 'Ek ' . ($ekIndex + 1)) : basename($filePath);

            if (empty($filePath) || !file_exists($filePath)) {
                continue;
            }

            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = is_array($ek) ? ($ek['type'] ?? '') : '';

            if ($ext === 'pdf' || $mime === 'application/pdf') {
                try {
                    $pageCount = $mpdf->setSourceFile($filePath);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                        $tplId = $mpdf->importPage($pageNo);
                        $mpdf->useTemplate($tplId);
                    }
                } catch (\Throwable $e) {
                    error_log('mPDF Import Page Error: ' . $e->getMessage());
                }
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $mpdf->AddPage('P', '', '', '', '', 10, 10, 10, 10, 0, 0);
                $mpdf->WriteHTML('
                    <div style="text-align:center; padding-top:5mm;">
                        <div style="font-size:10pt; font-weight:bold; margin-bottom:4mm; font-family:sans-serif;">EK ' . ($ekIndex + 1) . ': ' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</div>
                        <img src="' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . '" style="max-width:180mm; max-height:240mm; object-fit:contain;" />
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
}
