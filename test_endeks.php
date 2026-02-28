<?php
require_once __DIR__ . '/Autoloader.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Service\EndeskOkumaService;
use App\Model\SettingsModel;

header('Content-Type: text/html; charset=utf-8');

echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #1c1e21; padding: 20px; line-height: 1.5; }
    pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 8px; overflow: auto; max-height: 500px; font-size: 13px; border: 1px solid #3e4451; }
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #e4e6eb; }
    h1 { color: #1a73e8; margin-bottom: 20px; font-weight: 600; }
    h2 { color: #3c4043; border-bottom: 1px solid #dadce0; padding-bottom: 12px; margin-top: 0; font-size: 1.25rem; }
    .success { color: #188038; font-weight: 600; }
    .error { color: #d93025; font-weight: 600; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; border-radius: 8px; overflow: hidden; border: 1px solid #dadce0; }
    table th { background-color: #f8f9fa; color: #5f6368; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
    table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dadce0; }
    table tr:last-child td { border-bottom: none; }
    table tr:hover { background-color: #f1f3f4; }
    .btn { background: #1a73e8; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; }
    .btn:hover { background: #1557b0; }
    input[type='text'] { padding: 9px 12px; border: 1px solid #dadce0; border-radius: 6px; width: 150px; }
    .nav { margin-bottom: 20px; }
    .nav a { margin-right: 15px; text-decoration: none; color: #5f6368; font-weight: 500; }
    .nav a.active { color: #1a73e8; border-bottom: 2px solid #1a73e8; padding-bottom: 5px; }
</style>";

echo "<div class='nav'>
    <a href='test_api.php'>Kesme/Açma Testi</a>
    <a href='test_endeks.php' class='active'>Endeks Okuma Testi</a>
</div>";

echo "<h1>Endeks Okuma API Test Paneli</h1>";

try {
    $settingsModel = new SettingsModel();
    $settings = $settingsModel->getAllSettingsAsKeyValue();

    $apiUrl = $settings['api_endeks_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_okuma_secure.php?action=getData';
    $apiKey = $settings['api_endeks_sifre'] ?? 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';

    echo "<div class='card'>";
    echo "<h2>API Yapılandırması</h2>";
    echo "<ul>";
    echo "<li><b>URL:</b> $apiUrl</li>";
    echo "<li><b>Key:</b> " . substr($apiKey, 0, 10) . "..." . "</li>";
    echo "</ul>";
    echo "</div>";

    $tarih = $_GET['tarih'] ?? date('d/m/Y');

    echo "<div class='card'>";
    echo "<h2>Test Sorgusu</h2>";
    echo "<form method='GET'>";
    echo "Tarih: <input type='text' name='tarih' value='$tarih' placeholder='dd/mm/yyyy'> ";
    echo "<button type='submit' class='btn'>Sorgula</button>";
    echo "</form>";
    echo "</div>";

    $service = new EndeskOkumaService();

    echo "<div class='card'>";
    echo "<h2>API Yanıtı</h2>";

    $startTime = microtime(true);
    try {
        // limit 10, offset 0 for testing
        $response = $service->getData($tarih, $tarih, 17, 17, 10, 0);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        echo "<p>Süre: <span class='success'>$duration ms</span></p>";

        if (isset($response['success']) && $response['success']) {
            echo "<p class='success'>API İsteği Başarılı!</p>";

            $dataCount = count($response['data']['data'] ?? []);
            echo "<p>Dönen Kayıt Sayısı: <b>$dataCount</b></p>";

            if ($dataCount > 0) {
                echo "<h3>Örnek Veri (İlk Kayıt)</h3>";
                echo "<pre>" . json_encode($response['data']['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

                echo "<h3>Kayıt Listesi (İlk 10)</h3>";
                echo "<table>";
                echo "<thead><tr>";
                // Headerları ilk kayıttan al
                foreach (array_keys($response['data']['data'][0]) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr></thead><tbody>";

                foreach ($response['data']['data'] as $row) {
                    echo "<tr>";
                    foreach ($row as $val) {
                        echo "<td>" . (is_array($val) ? json_encode($val) : $val) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p>Bu tarih için veri bulunamadı.</p>";
                echo "<h3>Ham Yanıt:</h3>";
                echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        } else {
            echo "<p class='error'>API Hatası Bildirdi!</p>";
            echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>HATA OLUŞTU:</p>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card' style='border-left: 5px solid red;'>";
    echo "<h2>Sistem Hatası</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "</div>";
}
