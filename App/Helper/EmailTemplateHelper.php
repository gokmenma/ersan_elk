<?php

namespace App\Helper;

class EmailTemplateHelper
{
    /**
     * Professional HTML e-posta şablonu oluşturur
     * 
     * @param string $title Başlık (H1)
     * @param string $content HTML içerik
     * @param string|null $buttonText Buton metni
     * @param string|null $buttonUrl Buton URL'i
     * @return string Hazırlanmış HTML
     */
    public static function getTemplate($title, $content, $buttonText = null, $buttonUrl = null)
    {
        $primaryColor = '#0F172A'; // Navy
        $ctaColor = '#0369A1'; // Blue
        $bgColor = '#F8FAFC'; // Light gray background
        $textColor = '#020617'; // Dark text
        $mutedText = '#475569'; // Muted slate

        $buttonHtml = '';
        if ($buttonText && $buttonUrl) {
            $buttonHtml = "
            <div style='margin-top: 30px; text-align: center;'>
                <a href='{$buttonUrl}' style='background-color: {$ctaColor}; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; font-family: \"Poppins\", sans-serif; transition: background-color 0.2s;'>
                    {$buttonText}
                </a>
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='tr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap' rel='stylesheet'>
            <style>
                body { margin: 0; padding: 0; background-color: {$bgColor}; font-family: \"Open Sans\", sans-serif; color: {$textColor}; line-height: 1.6; }
                .container { max-width: 600px; margin: 40px auto; padding: 0; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0; }
                .header { background-color: {$primaryColor}; padding: 30px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-family: \"Poppins\", sans-serif; font-size: 24px; font-weight: 600; letter-spacing: -0.025em; }
                .body { padding: 40px; }
                .footer { padding: 20px; text-align: center; font-size: 13px; color: {$mutedText}; border-top: 1px solid #f1f5f9; }
                p { margin-bottom: 20px; }
                .highlight { color: {$ctaColor}; font-weight: 600; }
                @media only screen and (max-width: 600px) {
                    .container { margin: 20px; border-radius: 8px; }
                    .body { padding: 30px 20px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$title}</h1>
                </div>
                <div class='body'>
                    {$content}
                    {$buttonHtml}
                </div>
                <div class='footer'>
                    <p style='margin: 0;'><b>Ersan Elektrik</b> | Personel Yönetim Sistemi</p>
                    <p style='margin: 5px 0 0 0;'>Bu bir sistem e-postasıdır, lütfen yanıtlamayınız.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
