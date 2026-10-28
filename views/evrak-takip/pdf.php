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
    $downloadType = (string) ($_GET['type'] ?? '');
    $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    $model = new EvrakTakipModel();

    // UETS Delil PDF İndirme / Görüntüleme
    if ($downloadType === 'uets_delil') {
        $evidenceId = trim((string) ($_GET['evidence_id'] ?? ''));
        if ($evidenceId === '') {
            throw new RuntimeException('Delil ID belirtilmedi.');
        }
        $evidenceModel = new \App\Model\UetsEvidenceModel();
        $delil = $evidenceModel->getByEvidenceId($evidenceId);
        if (!$delil) {
            throw new RuntimeException('Delil kaydı bulunamadı.');
        }
        $delilMesaj = (new \App\Model\UetsMesajModel())->getById((int) ($delil['uets_mesaj_id'] ?? 0), $firmaId);
        if (!(new \App\Model\EvrakSevkModel())->canViewMesaj($delilMesaj)) {
            throw new RuntimeException('Bu UETS delilini görüntüleme yetkiniz yok.');
        }

        $pdfBytes = '';
        if (!empty($delil['delil_pdf_yolu'])) {
            $pdfFullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $delil['delil_pdf_yolu'], '/');
            if (file_exists($pdfFullPath)) {
                $raw = file_get_contents($pdfFullPath);
                try {
                    $pdfBytes = Security::decryptFile($raw);
                } catch (\Throwable $e) {
                    $pdfBytes = $raw;
                }
            }
        }

        if (empty($pdfBytes)) {
            $settings = (new \App\Model\UetsSettingsModel())->getSettings($firmaId);
            if (!$settings || empty($settings['api_key_decrypted'])) {
                throw new RuntimeException('UETS API ayarları yapılandırılmamış.');
            }
            $client = new \App\Service\UetsClientService($settings['api_key_decrypted'], $settings['api_environment'] ?? 'test');
            $pdfBytes = $client->downloadEvidencePdf($evidenceId);

            // Yerel olarak ISO 27001 gereği şifreleyerek önbelleğe al
            $storageDir = dirname(__DIR__, 2) . '/uploads/evrak-takip/uets';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            $filename = 'delil_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $evidenceId) . '.pdf';
            $fullPath = $storageDir . '/' . $filename;
            file_put_contents($fullPath, Security::encryptFile($pdfBytes));
            $evidenceModel->updatePdfPath($evidenceId, 'uploads/evrak-takip/uets/' . $filename);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="uets_delil_' . $evidenceId . '.pdf"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }

    // UETS EYP Paketi İndirme
    if ($downloadType === 'uets_eyp') {
        $msgIdEnc = (string) ($_GET['msg_id'] ?? '');
        $uetsMesajId = (string) ($_GET['uets_id'] ?? '');
        $mesajModel = new \App\Model\UetsMesajModel();
        
        $mesaj = null;
        if ($uetsMesajId !== '') {
            $mesaj = $mesajModel->getByUetsId($firmaId, $uetsMesajId);
        } elseif ($msgIdEnc !== '') {
            $localId = (int) Security::decrypt($msgIdEnc);
            $mesaj = $mesajModel->getById($localId, $firmaId);
        }

        if (!$mesaj || empty($mesaj['ham_eyp_yolu'])) {
            throw new RuntimeException('UETS EYP paketi bulunamadı.');
        }
        $eypSevk = new \App\Model\EvrakSevkModel();
        if (!$eypSevk->canViewMesaj($mesaj)) {
            throw new RuntimeException('Bu UETS paketini indirme yetkiniz yok.');
        }
        if (!empty($mesaj['evrak_id'])) {
            $eypSevk->recordHistory($firmaId, (int) $mesaj['evrak_id'], 'eyp_indirme', \App\Model\EvrakSevkModel::currentUserId(), null, null);
        }

        $eypFullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $mesaj['ham_eyp_yolu'], '/');
        if (!file_exists($eypFullPath)) {
            throw new RuntimeException('EYP paket dosyası diskte bulunamadı.');
        }

        $rawEyp = file_get_contents($eypFullPath);
        try {
            $eypBytes = Security::decryptFile($rawEyp);
        } catch (\Throwable $e) {
            $eypBytes = $rawEyp;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="uets_' . ($mesaj['uets_message_id'] ?? 'tebligat') . '.eyp"');
        header('Content-Length: ' . strlen($eypBytes));
        echo $eypBytes;
        exit;
    }

    // PTT KEP Yasal Delil XML İndirme
    if ($downloadType === 'kep_delil') {
        $delilId = trim((string) ($_GET['delil_id'] ?? ''));
        if ($delilId === '') {
            throw new RuntimeException('KEP Delil ID belirtilmedi.');
        }

        $kepService = new \App\Service\KepService();
        $delilRes = $kepService->downloadEvidenceXml($firmaId, $delilId);
        $xmlData = $delilRes['xml'] ?? '';

        if (empty($xmlData)) {
            throw new RuntimeException('Delil içeriği boş veya indirilemedi.');
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="kep_delil_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $delilId) . '.xml"');
        header('Content-Length: ' . strlen($xmlData));
        echo $xmlData;
        exit;
    }

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
            'ust_yazi_gerekli_degil' => !empty($_POST['ust_yazi_gerekli_degil']) ? 1 : 0,
            'yazi_tipi' => ($_POST['yazi_tipi'] ?? 'times_new_roman') === 'arial' ? 'arial' : 'times_new_roman',
            'ilgiler' => trim((string) ($_POST['ilgiler'] ?? '')),
            'ekler' => trim((string) ($_POST['ekler'] ?? '')),
        ];
        $decryptId = static function ($val): int {
            if (empty($val)) return 0;
            if (is_numeric($val)) return (int) $val;
            $d = Security::decrypt((string) $val);
            return is_numeric($d) ? (int) $d : 0;
        };

        $signerIds = [];
        foreach (array_slice((array) ($_POST['imza_kullanici_ids'] ?? []), 0, 3) as $encryptedSignerId) {
            $signerId = $decryptId($encryptedSignerId);
            if ($signerId > 0) {
                $signerIds[] = $signerId;
            }
        }

        $existingById = [];
        if (!empty($_POST['id'])) {
            $evrakRealId = $decryptId($_POST['id']);
            if ($evrakRealId > 0) {
                $existingDoc = $model->getById($evrakRealId);
                $dbAttachments = $model->getAttachments($evrakRealId);
                $removedEncryptedIds = json_decode((string) ($_POST['silinen_ek_ids_json'] ?? '[]'), true) ?: [];
                $removedIds = array_map($decryptId, $removedEncryptedIds);

                if ($existingDoc && !empty($existingDoc->dosya_yolu)) {
                    $mainFullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $existingDoc->dosya_yolu, '/');
                    if (file_exists($mainFullPath)) {
                        $existingById[0] = [
                            'path' => $mainFullPath,
                            'name' => basename($existingDoc->dosya_yolu),
                            'type' => mime_content_type($mainFullPath) ?: '',
                        ];
                    }
                }
                foreach ($dbAttachments as $att) {
                    if (!in_array((int) $att->id, $removedIds, true)) {
                        $fullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $att->dosya_yolu, '/');
                        if (file_exists($fullPath)) {
                            $existingById[(int) $att->id] = [
                                'path' => $fullPath,
                                'name' => $att->dosya_adi,
                                'type' => $att->mime_tipi,
                            ];
                        }
                    }
                }
            }
        }

        $newUploadedByKeys = [];
        if (!empty($_FILES['ek_dosyalari']['tmp_name']) && is_array($_FILES['ek_dosyalari']['tmp_name'])) {
            foreach ($_FILES['ek_dosyalari']['tmp_name'] as $key => $tmpPath) {
                if (!empty($tmpPath) && is_uploaded_file($tmpPath)) {
                    $name = $_FILES['ek_dosyalari']['name'][$key] ?? 'Ek Dosya';
                    $type = $_FILES['ek_dosyalari']['type'][$key] ?? '';
                    $newUploadedByKeys[(string) $key] = [
                        'path' => $tmpPath,
                        'name' => $name,
                        'type' => $type,
                    ];
                }
            }
        }

        if (!empty($_FILES['dosya']['tmp_name']) && is_uploaded_file($_FILES['dosya']['tmp_name'])) {
            $newUploadedByKeys['main_file'] = [
                'path' => $_FILES['dosya']['tmp_name'],
                'name' => $_FILES['dosya']['name'] ?? 'Evrak Dosyası',
                'type' => $_FILES['dosya']['type'] ?? '',
            ];
        }

        $orderInfo = json_decode((string) ($_POST['ek_duzen_json'] ?? '[]'), true) ?: [];
        $ekDosyaYollari = [];

        if (!empty($orderInfo)) {
            foreach ($orderInfo as $item) {
                $type = $item['type'] ?? '';
                if ($type === 'existing') {
                    $attId = $decryptId($item['id'] ?? '');
                    if ($attId > 0 && isset($existingById[$attId])) {
                        $ekDosyaYollari[] = $existingById[$attId];
                        unset($existingById[$attId]);
                    }
                } elseif ($type === 'new') {
                    $key = (string) ($item['key'] ?? '');
                    if ($key !== '' && isset($newUploadedByKeys[$key])) {
                        $ekDosyaYollari[] = $newUploadedByKeys[$key];
                        unset($newUploadedByKeys[$key]);
                    }
                }
            }
        }

        foreach ($existingById as $att) {
            $ekDosyaYollari[] = $att;
        }
        foreach ($newUploadedByKeys as $att) {
            $ekDosyaYollari[] = $att;
        }

        $evrak->ek_dosya_yollari = $ekDosyaYollari;
    } else {
        $decryptId = static function ($val): int {
            if (empty($val)) return 0;
            if (is_numeric($val)) return (int) $val;
            $d = Security::decrypt((string) $val);
            return is_numeric($d) ? (int) $d : 0;
        };

        $id = $decryptId($_GET['id'] ?? '');
        if ($id <= 0 || !($evrak = $model->getById($id))) {
            throw new RuntimeException('Evrak bulunamadı.');
        }
        $signerIds = json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: [];
        $dbAttachments = $model->getAttachments($id);
        $ekDosyaYollari = [];

        if (!empty($evrak->dosya_yolu)) {
            $mainFullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->dosya_yolu, '/');
            if (file_exists($mainFullPath)) {
                $ekDosyaYollari[] = [
                    'path' => $mainFullPath,
                    'name' => basename($evrak->dosya_yolu),
                    'type' => mime_content_type($mainFullPath) ?: '',
                ];
            }
        }

        foreach ($dbAttachments as $att) {
            $fullPath = dirname(__DIR__, 2) . '/' . ltrim((string) $att->dosya_yolu, '/');
            if (file_exists($fullPath)) {
                if (!empty($evrak->dosya_yolu) && ltrim((string) $att->dosya_yolu, '/') === ltrim((string) $evrak->dosya_yolu, '/')) {
                    continue;
                }
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

    if (!empty($evrak->pades_pdf_yolu)) {
        $signedPath = dirname(__DIR__, 2) . '/' . ltrim((string) $evrak->pades_pdf_yolu, '/');
        if (!is_file($signedPath)) {
            throw new RuntimeException('İmzalı PAdES PDF dosyası bulunamadı.');
        }
        $pdf = Security::decryptFile((string) file_get_contents($signedPath));
        if (!empty($evrak->pades_pdf_sha256) && !hash_equals((string) $evrak->pades_pdf_sha256, hash('sha256', $pdf))) {
            throw new RuntimeException('İmzalı PAdES PDF bütünlük kontrolünden geçemedi.');
        }
    } else {
        $pdf = (new EvrakPdfService())->render($evrak);
    }
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
