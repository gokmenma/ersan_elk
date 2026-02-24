<?php
/**
 * Hizmet İşleri Fiyat Farkı Endeksleri (Kasım 2013 sonrası)
 * 
 * Kaynak: https://www.hakedis.org/endeksler/hizmet-isleri-fiyat-farki-endeksleri-kasim-2013-sonrasi
 * 
 * Fonksiyon olarak kullanım:
 *   require_once 'endeks_api/hizmet_endeks.php';
 *   $asgariUcret = getHizmetEndeksVerisi(2026, 1, 'asgari_ucret');
 *   $ufe          = getHizmetEndeksVerisi(2026, 1, 'ufe');
 *   $makine       = getHizmetEndeksVerisi(2026, 1, 'makine');
 * 
 * veya toplu:
 *   $veriler = getHizmetEndeksleri(2026, 1);
 *   // ['asgari_ucret' => 33030, 'ufe' => 4910.53, 'makine' => 3624.85]
 */

/**
 * Hizmet İşleri Endeks sayfasından belirtilen yıl ve ay için tüm satırları çeker
 * 
 * @param int $yil Yıl
 * @param int $ay Ay numarası (1-12)
 * @return array ['basarili' => bool, 'satirlar' => [...], 'hata' => string|null]
 */
function getHizmetEndeksTablosu(int $yil, int $ay): array
{
    if ($ay < 1 || $ay > 12) {
        return ['basarili' => false, 'hata' => 'Geçersiz ay değeri. 1-12 arası olmalı.', 'satirlar' => []];
    }

    $url = "https://www.hakedis.org/endeksler/hizmet-isleri-fiyat-farki-endeksleri-kasim-2013-sonrasi";

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
        return ['basarili' => false, 'hata' => 'Sayfa yüklenemedi. HTTP Kodu: ' . $httpCode, 'satirlar' => []];
    }

    // İlgili yılın tablosunu bul
    $yilBaslik = $yil . ' Yılı Fiyat Farkı Endeks Katsayıları';
    $baslikPos = strpos($html, $yilBaslik);
    if ($baslikPos === false) {
        // Alternatif başlık formatını dene
        $yilBaslik = $yil . ' Yılı';
        $baslikPos = strpos($html, $yilBaslik);
    }

    if ($baslikPos === false) {
        return ['basarili' => false, 'hata' => $yil . ' yılına ait veri bulunamadı.', 'satirlar' => []];
    }

    $tableStartPos = strpos($html, '<table', $baslikPos);
    if ($tableStartPos === false) {
        return ['basarili' => false, 'hata' => $yil . ' yılına ait tablo bulunamadı.', 'satirlar' => []];
    }

    $tableEndPos = strpos($html, '</table>', $tableStartPos);
    if ($tableEndPos === false) {
        return ['basarili' => false, 'hata' => 'Tablo sonu bulunamadı.', 'satirlar' => []];
    }

    $tableHtml = substr($html, $tableStartPos, $tableEndPos - $tableStartPos + 8);

    // DOMDocument ile tabloyu parse et
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $tableHtml);

    $rows = $dom->getElementsByTagName('tr');
    $satirlar = [];

    // Sütun yapısı: 0=ENDEKS KODU, 1=ENDEKS ADI, 2=OCAK, 3=ŞUBAT, ..., 13=ARALIK
    // Ay sütun indeksi = ay + 1 (0-indexed: OCAK=2, ŞUBAT=3, ...)
    $aySutunIndex = $ay + 1;

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 3)
            continue;

        $endeksKodu = trim($cells->item(0)->textContent);
        $endeksAdi = trim($cells->item(1)->textContent);

        if (empty($endeksKodu) && empty($endeksAdi))
            continue;

        // İlgili ayın değerini al
        $deger = null;
        if ($cells->length > $aySutunIndex) {
            $raw = trim($cells->item($aySutunIndex)->textContent);
            $raw = str_replace(',', '.', $raw);
            $raw = preg_replace('/[^\d.]/', '', $raw);
            if ($raw !== '') {
                $deger = floatval($raw);
            }
        }

        $satirlar[] = [
            'endeks_kodu' => $endeksKodu,
            'endeks_adi' => $endeksAdi,
            'deger' => $deger
        ];
    }

    return [
        'basarili' => true,
        'yil' => $yil,
        'ay' => $ay,
        'satirlar' => $satirlar,
        'hata' => null
    ];
}

/**
 * Belirtilen yıl ve ay için Asgari Ücret, Yİ-ÜFE ve Makine endeks değerlerini döndürür
 * 
 * @param int $yil Yıl
 * @param int $ay Ay numarası (1-12)
 * @return array ['asgari_ucret' => float|null, 'ufe' => float|null, 'makine' => float|null]
 */
function getHizmetEndeksleri(int $yil, int $ay): array
{
    $sonuc = [
        'asgari_ucret' => null,
        'ufe' => null,
        'makine' => null
    ];

    $tablo = getHizmetEndeksTablosu($yil, $ay);

    if (!$tablo['basarili']) {
        return $sonuc;
    }

    foreach ($tablo['satirlar'] as $satir) {
        $kod = $satir['endeks_kodu'];
        $adi = $satir['endeks_adi'];

        // Asgari Ücret: Endeks kodu "İ" (Türkçe noktalı İ) 
        // "İşçilikle ilgili Temel Asgari Ücret ve Güncel Asgari Ücreti"
        if (($kod === 'İ' || $kod === 'I') && stripos($adi, 'Asgari') !== false) {
            $sonuc['asgari_ucret'] = $satir['deger'];
        }

        // Yİ-ÜFE: Endeks kodu "Yİ-ÜFE"
        // "(GENEL) Yİ-ÜFE Genel, Üretici Fiyatları Alt Sektörlere Göre Endeks Sonuçları Tablosu (2003=100)"
        if ($kod === 'Yİ-ÜFE' || ($kod === 'YI-ÜFE') || ($kod === 'Yİ-ÜFE')) {
            $sonuc['ufe'] = $satir['deger'];
        }

        // Makine ve Ekipman: Endeks kodu "28"
        // "Makine ve ekipmanlar b.y.s."
        if ($kod === '28' && stripos($adi, 'Makine') !== false) {
            $sonuc['makine'] = $satir['deger'];
        }
    }

    return $sonuc;
}

/**
 * Belirli bir endeks türünün değerini döndürür
 * 
 * @param int $yil Yıl
 * @param int $ay Ay numarası (1-12)
 * @param string $tur 'asgari_ucret', 'ufe' veya 'makine'
 * @return float|null
 */
function getHizmetEndeksVerisi(int $yil, int $ay, string $tur): ?float
{
    $endeksler = getHizmetEndeksleri($yil, $ay);
    return $endeksler[$tur] ?? null;
}

// Standalone çağrım
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['yil']) || isset($_GET['ay']))) {
        $yil = isset($_GET['yil']) ? intval($_GET['yil']) : intval(date('Y'));
        $ay = isset($_GET['ay']) ? intval($_GET['ay']) : intval(date('m')) - 1;

        header('Content-Type: application/json; charset=utf-8');
        $endeksler = getHizmetEndeksleri($yil, $ay);
        echo json_encode([
            'basarili' => true,
            'yil' => $yil,
            'ay' => $ay,
            'veriler' => $endeksler
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
