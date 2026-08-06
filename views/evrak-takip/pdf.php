<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Date;
use App\Helper\RichTextSanitizer;
use App\Helper\Security;
use App\Model\EvrakTakipModel;
use App\Model\MenuModel;
use App\Model\FirmaModel;
use App\Model\UserModel;
use App\Service\EvrakPdfService;

if (empty($_SESSION['loggedin']) || empty($_SESSION['firma_id'])) {
    http_response_code(401);
    exit('Oturum gerekli.');
}

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($currentUserId <= 0 || !(new MenuModel())->userCanAccessMenuLink($currentUserId, 'evrak-takip/list')) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz yok.');
}

try {
    $model = new EvrakTakipModel();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $personelId = (int) ($_POST['personel_id'] ?? 0);
        $ilgiliPersonelId = (int) ($_POST['ilgili_personel_id'] ?? 0);

        $evrak = (object) [
            'evrak_tipi' => ($_POST['evrak_tipi'] ?? 'gelen') === 'giden' ? 'giden' : 'gelen',
            'tarih' => !empty($_POST['tarih']) ? Date::Ymd($_POST['tarih']) : date('Y-m-d'),
            'evrak_no' => trim((string) ($_POST['evrak_no'] ?? '')),
            'konu' => trim((string) ($_POST['konu'] ?? '')),
            'kurum_adi' => trim((string) ($_POST['kurum_adi'] ?? '')),
            'muhatap_alt_birim' => trim((string) ($_POST['muhatap_alt_birim'] ?? '')),
            'muhatap_adres' => trim((string) ($_POST['muhatap_adres'] ?? '')),
            'personel_adi' => $model->getPersonelName($personelId),
            'ilgili_personel_adi' => $model->getPersonelName($ilgiliPersonelId),
            'aciklama' => RichTextSanitizer::sanitize($_POST['aciklama'] ?? ''),
            'yazi_tipi' => ($_POST['yazi_tipi'] ?? 'times_new_roman') === 'arial' ? 'arial' : 'times_new_roman',
            'ilgiler' => trim((string) ($_POST['ilgiler'] ?? '')),
            'ekler' => trim((string) ($_POST['ekler'] ?? '')),
        ];
        $signerIds = [];
        foreach (array_slice((array) ($_POST['imza_kullanici_ids'] ?? []), 0, 3) as $encryptedSignerId) {
            $signerId = (int) Security::decrypt((string) $encryptedSignerId);
            if ($signerId > 0) {
                $signerIds[] = $signerId;
            }
        }

        $ekDosyaYollari = [];
        if (!empty($_FILES['ek_dosyalari']['tmp_name']) && is_array($_FILES['ek_dosyalari']['tmp_name'])) {
            foreach ($_FILES['ek_dosyalari']['tmp_name'] as $key => $tmpPath) {
                if (!empty($tmpPath) && is_uploaded_file($tmpPath)) {
                    $name = $_FILES['ek_dosyalari']['name'][$key] ?? 'Ek Dosya';
                    $type = $_FILES['ek_dosyalari']['type'][$key] ?? '';
                    $ekDosyaYollari[] = [
                        'path' => $tmpPath,
                        'name' => $name,
                        'type' => $type,
                    ];
                }
            }
        }
        if (!empty($_POST['id'])) {
            $evrakRealId = (int) Security::decrypt((string) $_POST['id']);
            if ($evrakRealId > 0) {
                $dbAttachments = $model->getAttachments($evrakRealId);
                $removedIds = json_decode((string) ($_POST['silinen_ek_ids_json'] ?? '[]'), true) ?: [];
                $orderInfo = json_decode((string) ($_POST['ek_duzen_json'] ?? '[]'), true) ?: [];

                $existingById = [];
                foreach ($dbAttachments as $att) {
                    if (!in_array((int) $att->id, array_map('intval', $removedIds), true)) {
                        $fullPath = dirname(__DIR__, 2) . '/' . ltrim($att->dosya_yolu, '/');
                        if (file_exists($fullPath)) {
                            $existingById[(int) $att->id] = [
                                'path' => $fullPath,
                                'name' => $att->dosya_adi,
                                'type' => $att->mime_tipi,
                            ];
                        }
                    }
                }
                if ($orderInfo !== []) {
                    $orderedEkler = [];
                    foreach ($orderInfo as $item) {
                        if (($item['type'] ?? '') === 'existing' && isset($existingById[(int) ($item['id'] ?? 0)])) {
                            $orderedEkler[] = $existingById[(int) $item['id']];
                            unset($existingById[(int) $item['id']]);
                        }
                    }
                    foreach ($existingById as $att) {
                        $orderedEkler[] = $att;
                    }
                    $ekDosyaYollari = array_merge($orderedEkler, $ekDosyaYollari);
                } else {
                    $ekDosyaYollari = array_merge(array_values($existingById), $ekDosyaYollari);
                }
            }
        }
        $evrak->ek_dosya_yollari = $ekDosyaYollari;
    } else {
        $id = (int) Security::decrypt($_GET['id'] ?? '');
        if ($id <= 0 || !($evrak = $model->getById($id))) {
            throw new RuntimeException('Evrak bulunamadı.');
        }
        $signerIds = json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: [];
        $dbAttachments = $model->getAttachments($id);
        $ekDosyaYollari = [];
        foreach ($dbAttachments as $att) {
            $fullPath = dirname(__DIR__, 2) . '/' . ltrim($att->dosya_yolu, '/');
            if (file_exists($fullPath)) {
                $ekDosyaYollari[] = [
                    'path' => $fullPath,
                    'name' => $att->dosya_adi,
                    'type' => $att->mime_tipi,
                ];
            }
        }
        $evrak->ek_dosya_yollari = $ekDosyaYollari;
    }

    $Settings = new \App\Model\SettingsModel();
    $settings = $Settings->getAllSettingsAsKeyValue((int) $_SESSION['firma_id']);

    $firma = (new FirmaModel())->find((int) $_SESSION['firma_id']);

    $evrak->evrak_antet_baslik_1 = trim((string) ($settings['evrak_antet_baslik_1'] ?? ''));
    $evrak->evrak_antet_baslik_2 = trim((string) ($settings['evrak_antet_baslik_2'] ?? ''));
    $evrak->evrak_antet_baslik_3 = trim((string) ($settings['evrak_antet_baslik_3'] ?? ''));
    $evrak->evrak_antet_baslik_4 = trim((string) ($settings['evrak_antet_baslik_4'] ?? ''));

    $evrak->evrak_alt_bilgi_1 = trim((string) ($settings['evrak_alt_bilgi_1'] ?? ''));
    $evrak->evrak_alt_bilgi_2 = trim((string) ($settings['evrak_alt_bilgi_2'] ?? ''));
    $evrak->evrak_alt_bilgi_3 = trim((string) ($settings['evrak_alt_bilgi_3'] ?? ''));
    $evrak->evrak_alt_bilgi_4 = trim((string) ($settings['evrak_alt_bilgi_4'] ?? ''));
    $evrak->evrak_eimza_goster = (string) ($settings['evrak_eimza_goster'] ?? '1');

    $evrak->kurum_basligi = trim((string) ($firma->firma_unvan ?? '')) ?: trim((string) ($firma->firma_adi ?? ''));
    $evrak->firma_adres = (($firma->adres ?? '') === '0') ? '' : (string) ($firma->adres ?? '');
    $firmaTel = (($firma->telefon ?? '') === '0') ? '' : trim((string) ($firma->telefon ?? ''));
    $evrak->firma_telefon = $firmaTel;
    $evrak->firma_web_sitesi = (($firma->web_sitesi ?? '') === '0') ? '' : trim((string) ($firma->web_sitesi ?? ''));
    $evrak->firma_kep_adresi = (($firma->kep_adresi ?? '') === '0') ? '' : trim((string) ($firma->kep_adresi ?? ''));

    $solLogo = (string) ($settings['sol_logo_yolu'] ?? $firma->logo_yolu ?? '');
    $sagLogo = (string) ($settings['sag_logo_yolu'] ?? '');

    $evrak->logo_path = $solLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($solLogo, '/') : '';
    $evrak->sol_logo_path = $solLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($solLogo, '/') : '';
    $evrak->sag_logo_path = $sagLogo !== '' ? dirname(__DIR__, 2) . '/' . ltrim($sagLogo, '/') : '';

    $signingUsers = $model->getSigningUsersByIds($signerIds);
    $userMap = [];
    foreach ($signingUsers as $u) {
        $userMap[(int) $u->id] = $u;
    }

    if (isset($_POST['kimin_adina_1']) || isset($_POST['kimin_adina_2']) || isset($_POST['kimin_adina_3'])) {
        $kiminAdinaList = [
            trim((string) ($_POST['kimin_adina_1'] ?? '')),
            trim((string) ($_POST['kimin_adina_2'] ?? '')),
            trim((string) ($_POST['kimin_adina_3'] ?? '')),
        ];
    } else {
        $kiminAdinaList = json_decode((string) ($evrak->imza_kimin_adina_json ?? '[]'), true) ?: [];
    }

    $orderedSigners = [];
    foreach ($signerIds as $idx => $sId) {
        if (isset($userMap[(int) $sId])) {
            $uObj = clone $userMap[(int) $sId];
            $uObj->kimin_adina = $kiminAdinaList[$idx] ?? '';
            $orderedSigners[] = $uObj;
        }
    }
    $evrak->imza_kisileri = $orderedSigners;

    $preparerId = !empty($evrak->olusturan_kullanici_id) ? (int) $evrak->olusturan_kullanici_id : $currentUserId;
    $preparerUser = (new UserModel())->find($preparerId);
    $preparerPhone = $firmaTel !== '' ? $firmaTel : trim((string) ($preparerUser->telefon ?? ''));

    $evrak->hazirlayan_kullanici = [
        'adi_soyadi' => $preparerUser->adi_soyadi ?? '',
        'unvani' => trim(($preparerUser->unvani ?? '') ?: ($preparerUser->gorevi ?? '')),
        'telefon' => $preparerPhone,
    ];

    $logoPath = (string) ($firma->logo_yolu ?? '');
    $evrak->logo_path = $logoPath !== '' ? dirname(__DIR__, 2) . '/' . ltrim($logoPath, '/') : '';

    $pdf = (new EvrakPdfService())->render($evrak);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="evrak-onizleme.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store, max-age=0');
    echo $pdf;
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF oluşturulamadı: ' . $e->getMessage();
}
