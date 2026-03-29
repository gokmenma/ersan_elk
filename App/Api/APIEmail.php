<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Service\MailGonderService;



/**
 * Convert Turkish characters to ASCII equivalents for safer attachment filenames.
 */
function tr_filename_ascii(string $name): string
{
    $map = [
        'ç' => 'c',
        'Ç' => 'C',
        'ğ' => 'g',
        'Ğ' => 'G',
        'ı' => 'i',
        'I' => 'I',
        'İ' => 'I',
        'ö' => 'o',
        'Ö' => 'O',
        'ş' => 's',
        'Ş' => 'S',
        'ü' => 'u',
        'Ü' => 'U',
    ];

    $out = strtr($name, $map);

    // If intl exists, make a best-effort general transliteration too
    if (class_exists('Transliterator')) {
        try {
            $tr = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($tr) {
                $out = $tr->transliterate($out);
            }
        } catch (Throwable $e) {
            // ignore, fallback to basic mapping
        }
    }
    return $out;
}


if ($_POST['action'] == 'email_gonder') {
    $toEmail = json_decode($_POST['to'] ?? '', true);
    $cc = json_decode($_POST['cc'] ?? '', true);
    $bcc = json_decode($_POST['bcc'] ?? '', true);
    $konu = $_POST['subject'] ?? 'Varsayılan Konu';
    $mesaj = $_POST['message'] ?? 'Varsayılan Mesaj';
    $attachmentPaths = []; // items: ['path' => '...', 'name' => '...']
    $uploadedAttachmentPathsForCleanup = [];
    //mail adreslerini array olarak al
    $toEmail = is_array($toEmail) ? $toEmail : [$toEmail];

    if (!is_array($toEmail) || empty($toEmail)) {
        throw new Exception("Geçerli bir alıcı listesi gönderilmedi.");
    }

    // Pasif kullanıcıları filtrele
    $UserModel = new \App\Model\UserModel();
    $activeToEmail = [];
    foreach ($toEmail as $email) {
        $email = trim($email);
        $user = $UserModel->checkUser($email); // checkUser email_adresi'ne de bakar
        if ($user) {
            if ($user->durum == 'Aktif') {
                $activeToEmail[] = $email;
            }
        } else {
            // Sistemde kayıtlı olmayan bir mail ise gönderime izin ver (manuel giriş vs.)
            $activeToEmail[] = $email;
        }
    }
    
    // Eğer tüm alıcılar pasif ise ve liste boşaldıysa
    if (empty($activeToEmail)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Alıcıların tamamı pasif olduğu için gönderim yapılmadı.'
        ]);
        exit;
    }
    
    $toEmail = $activeToEmail;

    // Attachments (optional)
    // Accept 
    //  - attachments[]  (multiple)
    // Save to /uploads/mail_attachments/ with randomized names
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $uploadDir = dirname(__DIR__, levels: 3) . '/uploads/mail_attachments';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $maxFiles = 5;
        $maxBytesPerFile = 10 * 1024 * 1024; // 10MB

        $allowedMime = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'text/plain',
            'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        $count = count($_FILES['attachments']['name']);
        if ($count > $maxFiles) {
            throw new Exception("En fazla {$maxFiles} dosya ekleyebilirsiniz.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        for ($i = 0; $i < $count; $i++) {
            $err = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new Exception("Dosya yükleme hatası (kod: {$err}).");
            }
            $tmp = $_FILES['attachments']['tmp_name'][$i] ?? '';
            $size = (int) ($_FILES['attachments']['size'][$i] ?? 0);
            if (!$tmp || !is_uploaded_file($tmp)) {
                throw new Exception("Geçersiz dosya yüklemesi.");
            }
            if ($size <= 0 || $size > $maxBytesPerFile) {
                throw new Exception("Dosya boyutu 0 ile 10MB arasında olmalı.");
            }

            $origName = (string) ($_FILES['attachments']['name'][$i] ?? 'file');
            // Visible filename in email: spaces -> underscore (and basic sanitization)
            $displayName = tr_filename_ascii($origName);
            $displayName = preg_replace('/\s+/', '_', trim($displayName));
            // avoid path tricks, keep basename only
            $displayName = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $displayName));
            // allow only safe characters in display name
            $displayName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $displayName);
            if ($displayName === '' || $displayName === '.' || $displayName === '..') {
                $displayName = 'attachment';
            }

            // prevent very long names in email clients
            if (strlen($displayName) > 160) {
                $extForDisplay = pathinfo($displayName, PATHINFO_EXTENSION);
                $base = pathinfo($displayName, PATHINFO_FILENAME);
                $base = substr($base, 0, 140);
                $displayName = $extForDisplay ? ($base . '.' . $extForDisplay) : $base;
            }
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $mime = $finfo->file($tmp);

            if ($mime && !in_array($mime, $allowedMime, true)) {
                throw new Exception("Bu dosya türüne izin verilmiyor: {$origName}");
            }

            // Randomized file name, keep extension (if any)
            $safeExt = $ext ? ('.' . preg_replace('/[^a-z0-9]/i', '', $ext)) : '';
            $newName = bin2hex(random_bytes(16)) . $safeExt;
            $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

            if (!move_uploaded_file($tmp, $dest)) {
                throw new Exception("Dosya kaydedilemedi: {$origName}");
            }

            $uploadedAttachmentPathsForCleanup[] = $dest;
            $attachmentPaths[] = [
                'path' => $dest,
                'name' => $displayName
            ];
        }
    }


    $sentOk = false;
    $logStatus = 'failed';
    try {
        $sentOk = MailGonderService::gonder(
            $toEmail,
            $konu,
            $mesaj,
            $attachmentPaths,
            $cc,
            $bcc
        );
        if ($sentOk) {
            $logStatus = 'success';
        }
    } catch (Exception $e) {
        error_log('Email gönderme hatası: ' . $e->getMessage());
        $sentOk = false;
        $logStatus = 'failed';
    } finally {
        // Cleanup uploaded temp attachments regardless of send result
        foreach ($uploadedAttachmentPathsForCleanup as $p) {
            if (is_string($p) && $p !== '' && file_exists($p)) {
                @unlink($p);
            }
        }

        // Loglama işlemi
        try {
            $SettingsModel = new \App\Model\SettingsModel();
            $allSettings = $SettingsModel->getAllSettingsAsKeyValue();

            $MesajLogModel = new \App\Model\MesajLogModel();
            $firmaId = $_SESSION['firma_id'] ?? $_SESSION['site_id'] ?? 0;
            $sender = $allSettings['SMTP_FROM'] ?? 'noreply@yonapp.com.tr';

            $MesajLogModel->logEmail(
                $firmaId,
                $sender,
                $toEmail,
                $konu,
                $mesaj,
                $attachmentPaths,
                $logStatus
            );
        } catch (Exception $e) {
            error_log('Email loglama hatası: ' . $e->getMessage());
        }
    }


    if ($sentOk) {
        // Başarılı gönderim işlemi
        $status = "success";
        $message = count($toEmail) . " alıcıya email başarıyla gönderildi.";

    } else {
        $status = "error";
        $message = "Email gönderilemedi.";
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'tomail' => $toEmail

    ];


    echo json_encode($res);
    exit;

}
// file_put_contents('php://input', $json_data);