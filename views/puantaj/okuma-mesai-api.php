<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Model\MenuModel;
use App\Model\OkumaDetayModel;
use App\Model\SystemLogModel;
use App\Service\OkumaExcelParserService;

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if (empty($_SESSION['loggedin']) || $currentUserId <= 0 || $firmaId <= 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!(new MenuModel())->userCanAccessMenuLink($currentUserId, 'puantaj/okuma-denetim')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    error_log('okuma-mesai-api yetki hatasi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'İşlem tamamlanamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'excel-yukle') {
    $sonuclar = [];

    try {
        if (empty($_FILES['excel_dosyalari'])) {
            throw new Exception('Dosya seçilmedi.');
        }

        $Model = new OkumaDetayModel();
        $parser = new OkumaExcelParserService();
        $ekipTanimlari = $Model->getEkipEslesmeleri();

        $dosyalar = $_FILES['excel_dosyalari'];
        $adet = is_array($dosyalar['name']) ? count($dosyalar['name']) : 0;

        if ($adet === 0) {
            throw new Exception('Dosya seçilmedi.');
        }

        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $izinliUzantilar = ['xlsx', 'xls', 'csv'];

        for ($i = 0; $i < $adet; $i++) {
            $orijinalAd = (string) $dosyalar['name'][$i];
            $satir = [
                'dosya' => $orijinalAd,
                'durum' => 'hatali',
                'satir_sayisi' => 0,
                'atlanan_tarih' => 0,
                'atlanan_tekrar' => 0,
                'mesaj' => '',
            ];

            try {
                if ($dosyalar['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception('Dosya yüklenemedi.');
                }

                $uzanti = strtolower(pathinfo($orijinalAd, PATHINFO_EXTENSION));
                if (!in_array($uzanti, $izinliUzantilar, true)) {
                    throw new Exception('Desteklenmeyen dosya türü (.' . $uzanti . '). Yalnızca xlsx, xls, csv kabul edilir.');
                }

                $gecici = $dosyalar['tmp_name'][$i];
                if (!is_uploaded_file($gecici)) {
                    throw new Exception('Geçersiz yükleme.');
                }

                $ayrisma = $parser->ayristir($gecici, $orijinalAd);

                if ($ayrisma['hata'] !== null) {
                    throw new Exception($ayrisma['hata']);
                }

                $parser->ekipleriEslestir($ayrisma['satirlar'], $ekipTanimlari);

                $tarihler = array_column($ayrisma['satirlar'], 'tarih');
                sort($tarihler);

                $dosyaId = $Model->dosyaEkle([
                    'firma_id' => $firmaId,
                    'orijinal_adi' => mb_substr($orijinalAd, 0, 255),
                    'dosya_hash' => hash_file('md5', $gecici),
                    'dosya_boyutu' => (int) $dosyalar['size'][$i],
                    'satir_sayisi' => 0,
                    'atlanan_tarih' => $ayrisma['atlanan_tarih'],
                    'atlanan_tekrar' => 0,
                    'ilk_tarih' => $tarihler[0] ?? null,
                    'son_tarih' => end($tarihler) ?: null,
                    'durum' => 'basarili',
                    'hata_mesaji' => null,
                    'yukleyen' => $currentUserId,
                ]);

                $eklenen = $Model->satirlariEkle($dosyaId, $firmaId, $ayrisma['satirlar']);
                $tekrar = count($ayrisma['satirlar']) - $eklenen;

                $Model->dosyaGuncelle($dosyaId, [
                    'firma_id' => $firmaId,
                    'satir_sayisi' => $eklenen,
                    'atlanan_tarih' => $ayrisma['atlanan_tarih'],
                    'atlanan_tekrar' => $tekrar,
                    'ilk_tarih' => $tarihler[0] ?? null,
                    'son_tarih' => end($tarihler) ?: null,
                    'durum' => 'basarili',
                    'hata_mesaji' => null,
                ]);

                $satir['durum'] = 'basarili';
                $satir['satir_sayisi'] = $eklenen;
                $satir['atlanan_tarih'] = $ayrisma['atlanan_tarih'];
                $satir['atlanan_tekrar'] = $tekrar;
                $satir['mesaj'] = $eklenen . ' okuma satırı eklendi.';

                (new SystemLogModel())->logAction(
                    $currentUserId,
                    'Okuma Mesai - Excel Yükleme',
                    "$orijinalAd dosyasından $eklenen satır eklendi. "
                        . "Tarihi okunamayan: {$ayrisma['atlanan_tarih']}, tekrar: $tekrar.",
                    SystemLogModel::LEVEL_IMPORTANT
                );
            } catch (Exception $e) {
                $satir['mesaj'] = $e->getMessage();

                try {
                    $Model->dosyaEkle([
                        'firma_id' => $firmaId,
                        'orijinal_adi' => mb_substr($orijinalAd, 0, 255),
                        'dosya_hash' => null,
                        'dosya_boyutu' => (int) ($dosyalar['size'][$i] ?? 0),
                        'satir_sayisi' => 0,
                        'atlanan_tarih' => 0,
                        'atlanan_tekrar' => 0,
                        'ilk_tarih' => null,
                        'son_tarih' => null,
                        'durum' => 'hatali',
                        'hata_mesaji' => mb_substr($e->getMessage(), 0, 500),
                        'yukleyen' => $currentUserId,
                    ]);
                } catch (Exception $ic) {
                    error_log('okuma-mesai dosya kaydi hatasi: ' . $ic->getMessage());
                }
            }

            $sonuclar[] = $satir;
        }

        echo json_encode([
            'status' => 'success',
            'sonuclar' => $sonuclar,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        error_log('okuma-mesai yukleme hatasi: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'sonuclar' => $sonuclar,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($action === 'dosya-sil') {
    try {
        $dosyaId = (int) ($_POST['dosya_id'] ?? 0);
        if ($dosyaId <= 0) {
            throw new Exception('Geçersiz dosya.');
        }

        $Model = new OkumaDetayModel();
        $dosya = $Model->dosyaBul($dosyaId, $firmaId);

        if (!$dosya) {
            throw new Exception('Dosya bulunamadı.');
        }

        $silinen = $Model->dosyaSil($dosyaId, $firmaId);

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Okuma Mesai - Dosya Silme',
            "{$dosya->orijinal_adi} dosyası ve $silinen okuma satırı silindi.",
            SystemLogModel::LEVEL_IMPORTANT
        );

        echo json_encode([
            'status' => 'success',
            'message' => "$silinen okuma satırı listeden çıkarıldı.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        error_log('okuma-mesai silme hatasi: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
