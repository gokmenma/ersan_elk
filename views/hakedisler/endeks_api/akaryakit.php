<?php
/**
 * EPDK Akaryakıt Bayi Satış Fiyatları - Aylık Ortalama
 * 
 * Fonksiyon olarak kullanım: 
 *   require_once 'endeks_api/akaryakit.php';
 *   $fiyat = getEpdkMotorinFiyati(2026, 1); // Ocak 2026 Motorin fiyatı
 * 
 * Standalone kullanım: akaryakit.php?yil=2026&ay=1
 */

/**
 * EPDK sayfasından belirtilen yıl ve ay için akaryakıt fiyatlarını çeker
 * 
 * @param int $yil Yıl
 * @param int $ay Ay numarası (1-12)
 * @return array ['basarili' => bool, 'veriler' => [...], 'hata' => string|null]
 */
function getEpdkAkaryakitFiyatlari(int $yil, int $ay): array
{
    $ayIsimleri = [
        1 => 'OCAK',
        2 => 'ŞUBAT',
        3 => 'MART',
        4 => 'NİSAN',
        5 => 'MAYIS',
        6 => 'HAZİRAN',
        7 => 'TEMMUZ',
        8 => 'AĞUSTOS',
        9 => 'EYLÜL',
        10 => 'EKİM',
        11 => 'KASIM',
        12 => 'ARALIK'
    ];

    if ($ay < 1 || $ay > 12) {
        return ['basarili' => false, 'hata' => 'Geçersiz ay değeri. 1-12 arası olmalı.', 'veriler' => []];
    }

    $url = "https://www.hakedis.org/endeksler/epdk-akaryakit-bayi-satis-fiyatlari-aylik-ortalama";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $httpCode !== 200) {
        return ['basarili' => false, 'hata' => 'Sayfa yüklenemedi. HTTP Kodu: ' . $httpCode, 'veriler' => []];
    }

    // İlgili yılın tablosunu bul
    $yilBaslik = $yil . ' Yılı Aylık Ortalama Bayi Satış Fiyatları';
    $baslikPos = strpos($html, $yilBaslik);
    if ($baslikPos === false) {
        return ['basarili' => false, 'hata' => $yil . ' yılına ait veri bulunamadı.', 'veriler' => []];
    }

    $tableStartPos = strpos($html, '<table', $baslikPos);
    if ($tableStartPos === false) {
        return ['basarili' => false, 'hata' => $yil . ' yılına ait tablo bulunamadı.', 'veriler' => []];
    }

    $tableEndPos = strpos($html, '</table>', $tableStartPos);
    if ($tableEndPos === false) {
        return ['basarili' => false, 'hata' => 'Tablo sonu bulunamadı.', 'veriler' => []];
    }

    $tableHtml = substr($html, $tableStartPos, $tableEndPos - $tableStartPos + 8);

    // DOMDocument ile tabloyu parse et
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $tableHtml);

    $rows = $dom->getElementsByTagName('tr');
    $sonuclar = [];
    $aySutunIndex = $ay; // 1-indexed: OCAK=1, ŞUBAT=2, ...

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 2)
            continue;

        $akaryakitTuru = trim($cells->item(0)->textContent);
        if (empty($akaryakitTuru))
            continue;

        if ($cells->length > $aySutunIndex) {
            $fiyat = trim($cells->item($aySutunIndex)->textContent);
            $fiyat = str_replace(',', '.', $fiyat);
            $fiyat = preg_replace('/[^\d.]/', '', $fiyat);

            $sonuclar[] = [
                'akaryakit_turu' => $akaryakitTuru,
                'fiyat' => $fiyat !== '' ? floatval($fiyat) : null,
                'fiyat_text' => $fiyat !== '' ? $fiyat : 'Veri yok'
            ];
        }
    }

    return [
        'basarili' => true,
        'yil' => $yil,
        'ay' => $ay,
        'ay_adi' => $ayIsimleri[$ay],
        'donem' => $ayIsimleri[$ay] . ' ' . $yil,
        'veriler' => $sonuclar,
        'hata' => null
    ];
}

/**
 * EPDK sayfasından belirtilen yıl ve ay için Motorin fiyatını döndürür
 * 
 * @param int $yil Yıl
 * @param int $ay Ay numarası (1-12)
 * @return float|null Motorin fiyatı veya null (veri yoksa)
 */
function getEpdkMotorinFiyati(int $yil, int $ay): ?float
{
    $sonuc = getEpdkAkaryakitFiyatlari($yil, $ay);

    if (!$sonuc['basarili']) {
        return null;
    }

    foreach ($sonuc['veriler'] as $veri) {
        // "Motorin" satırını bul (sadece "Motorin", "Motorin (Diğer)" değil)
        if ($veri['akaryakit_turu'] === 'Motorin') {
            return $veri['fiyat'];
        }
    }

    return null;
}

// Standalone çağrım: Eğer doğrudan bu dosya GET ile çağrılıyorsa JSON döndür
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['yil']) || isset($_GET['ay']))) {
        $yil = isset($_GET['yil']) ? intval($_GET['yil']) : intval(date('Y'));
        $ay = isset($_GET['ay']) ? intval($_GET['ay']) : intval(date('m')) - 1;

        header('Content-Type: application/json; charset=utf-8');
        $sonuc = getEpdkAkaryakitFiyatlari($yil, $ay);
        echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
