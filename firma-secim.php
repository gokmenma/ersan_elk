<?php
require_once "vendor/autoload.php";

use App\Model\FirmaModel;
use App\Model\UserModel;
use App\Helper\Helper;
use App\Helper\Form;

session_start();

$Firma = new FirmaModel();
$User = new UserModel();

// AJAX İşlemleri
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action == 'save') {
        $data = $_POST;
        $data['kayit_yapan'] = $_SESSION['user']->id ?? 0;

        try {
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK || $_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new \Exception('Firma logosu yüklenemedi veya 2 MB sınırını aşıyor.');
                }
                $logoMimeMap = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                $logoMime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
                if (!isset($logoMimeMap[$logoMime])) {
                    throw new \Exception('Firma logosu PNG veya JPG olmalıdır.');
                }
                $logoDir = __DIR__ . '/uploads/firma-logolari/';
                if (!is_dir($logoDir) && !mkdir($logoDir, 0777, true) && !is_dir($logoDir)) {
                    throw new \Exception('Firma logo klasörü oluşturulamadı.');
                }
                $logoName = 'firma_' . ((int) ($_POST['id'] ?? 0) ?: 'yeni') . '_' . bin2hex(random_bytes(8)) . '.' . $logoMimeMap[$logoMime];
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . $logoName)) {
                    throw new \Exception('Firma logosu kaydedilemedi.');
                }
                $data['logo_yolu'] = 'uploads/firma-logolari/' . $logoName;
            }
            $res = $Firma->saveFirma($data);
            echo json_encode(['status' => 'success', 'message' => 'Firma başarıyla kaydedildi.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'delete') {
        $id = (int) $_POST['id'];
        try {
            $Firma->deleteFirma($id);
            echo json_encode(['status' => 'success', 'message' => 'Firma silindi.']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'get') {
        $id = (int) $_GET['id'];
        $data = $Firma->getFirma($id);
        echo json_encode($data);
        exit;
    }
}

$branchs = $Firma->all();

// Kullanıcının yetkili olduğu firmaları filtrele
$currentUser = $_SESSION['user'] ?? null;
if ($currentUser && isset($currentUser->id)) {
    $freshUser = $User->find($currentUser->id);
    if ($freshUser) {
        $_SESSION['user'] = $freshUser;
        $currentUser = $freshUser;
    }
}

if ($currentUser && isset($currentUser->firma_ids)) {
    $rawFirmaIds = trim((string) $currentUser->firma_ids);
    if ($rawFirmaIds !== '') {
        $allowedFirmaIds = array_map('intval', array_filter(array_map('trim', explode(',', $rawFirmaIds)), 'strlen'));
        if (!empty($allowedFirmaIds)) {
            $branchs = array_filter($branchs, function ($branch) use ($allowedFirmaIds) {
                return isset($branch->id) && in_array((int) $branch->id, $allowedFirmaIds, true);
            });
        }
    }
}

// Varsayılan firma cookie kontrolü - otomatik yönlendirme
$defaultFirma = $Firma->resolveDefaultFirmaFromCookies($_COOKIE, $branchs);
if ($defaultFirma && !isset($_GET['change'])) {
    $_SESSION['firma_id'] = (int) $defaultFirma->id;
    $redirect = "set-session.php?firma_id=" . (int) $defaultFirma->id;
    if (isset($defaultFirma->firma_kodu) && !empty($defaultFirma->firma_kodu)) {
        $redirect .= "&firma_kodu=" . urlencode($defaultFirma->firma_kodu);
    }
    header("Location: " . $redirect);
    exit;
}

// Sadece silinmemiş firmaları göster
$branchs = array_filter($branchs, function ($b) {
    return is_null($b->silinme_tarihi);
});

//**Eğer 1 adet sube varsa direkt yönlendir */
if (count($branchs) == 1 && !isset($_GET['change'])) {
    $only_branch = reset($branchs);
    $_SESSION['sube_id'] = $only_branch->id;
    $_SESSION['firma_id'] = (int) $only_branch->id;
    $redirect = "set-session.php?firma_id=" . $only_branch->id;
    if (isset($only_branch->firma_kodu) && !empty($only_branch->firma_kodu)) {
        $redirect .= "&firma_kodu=" . urlencode($only_branch->firma_kodu);
    }
    header("Location: " . $redirect);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışma Alanı Seçimi | Ersan Elektrik - Personel Yönetim Sistemi</title>
    <link rel="shortcut icon" href="assets/images/favicon.ico">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0b0f19">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo Helper::base_url('assets/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />
    <!-- Bootstrap Css -->
    <link href="<?php echo Helper::base_url('assets/css/bootstrap.min.css'); ?>" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="<?php echo Helper::base_url('assets/css/app.min.css'); ?>" id="app-style" rel="stylesheet" type="text/css" />
    <!-- Custom Style -->
    <link href="<?php echo Helper::base_url('assets/css/style.css'); ?>" rel="stylesheet" type="text/css" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        :root {
            --portal-primary: #e2bd61;
            --portal-primary-hover: #cfab4f;
            --portal-primary-light: rgba(226, 189, 97, 0.12);
            --portal-primary-glow: rgba(226, 189, 97, 0.35);
            --portal-dark: #0b0f19;
            --portal-dark-card: #111827;
            --portal-text-main: #0f172a;
            --portal-text-muted: #64748b;
            --portal-border: #e2e8f0;
            --card-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background-color: #f8fafc;
            color: var(--portal-text-main);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 10% 10%, rgba(226, 189, 97, 0.05) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(59, 130, 246, 0.04) 0px, transparent 50%);
        }

        .selection-navbar {
            padding: 1.25rem 2rem;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
        }

        .nav-inner {
            max-width: 860px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand-logo {
            max-height: 42px;
            width: auto;
            object-fit: contain;
        }

        .nav-user-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            background-color: #f1f5f9;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
        }

        .nav-user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e2bd61 0%, #d4a942 100%);
            color: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .main-container {
            width: 100%;
            max-width: 860px;
            margin: 2.5rem auto auto;
            padding: 0 1.5rem;
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-header-box {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .header-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .header-subtitle {
            font-size: 0.925rem;
            color: #64748b;
            margin: 0;
        }

        .btn-add-firma {
            padding: 0.65rem 1.25rem;
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
        }

        .btn-add-firma:hover {
            background: #1e293b;
            color: #ffffff;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.18);
        }

        .branch-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        /* Modern Firma Card */
        .branch-item {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--card-radius);
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            position: relative;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .branch-item:hover {
            border-color: #cbd5e1;
            background-color: #ffffff;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
        }

        .branch-item.selected {
            border-color: var(--portal-primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px var(--portal-primary-light), 0 10px 25px -5px rgba(226, 189, 97, 0.15);
            transform: translateY(-2px);
        }

        /* Custom Modern Radio Indicator */
        .radio-wrapper {
            padding-top: 0.2rem;
        }

        .custom-radio-ring {
            width: 22px;
            height: 22px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background-color: #ffffff;
        }

        .branch-item:hover .custom-radio-ring {
            border-color: #94a3b8;
        }

        .branch-item.selected .custom-radio-ring {
            border-color: #d97706;
            background-color: #d97706;
        }

        .custom-radio-dot {
            width: 8px;
            height: 8px;
            background-color: #ffffff;
            border-radius: 50%;
            transform: scale(0);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .branch-item.selected .custom-radio-dot {
            transform: scale(1);
        }

        /* Card Content Area */
        .branch-content {
            flex: 1;
        }

        .branch-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .branch-name {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.01em;
            color: #0f172a;
        }

        .badge-varsayilan {
            font-size: 0.725rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 9999px;
            background: rgba(226, 189, 97, 0.18);
            color: #92400e;
            border: 1px solid rgba(226, 189, 97, 0.35);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Details Grid / Meta */
        .branch-meta-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .meta-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .meta-item i, .meta-item svg {
            color: #94a3b8;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .meta-item a {
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }

        .meta-item a:hover {
            color: #d97706;
            text-decoration: underline;
        }

        .meta-address {
            width: 100%;
            display: flex;
            align-items: flex-start;
            gap: 7px;
            margin-top: 2px;
            color: #475569;
        }

        .meta-address i {
            margin-top: 2px;
        }

        /* Actions Dropdown */
        .branch-actions {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
        }

        .action-trigger {
            background: transparent;
            border: 1px solid transparent;
            color: #94a3b8;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.2s;
        }

        .action-trigger:hover {
            background-color: #f1f5f9;
            color: #0f172a;
            border-color: #e2e8f0;
        }

        .action-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            z-index: 20;
            display: none;
            min-width: 150px;
            padding: 6px;
            margin-top: 4px;
        }

        .action-menu.show {
            display: block;
            animation: menuFadeIn 0.15s ease-out;
        }

        @keyframes menuFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-4px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .action-item {
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.15s ease;
        }

        .action-item:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .action-item i, .action-item svg {
            font-size: 1rem;
            color: #64748b;
        }

        .action-item.delete {
            color: #ef4444;
        }

        .action-item.delete i, .action-item.delete svg {
            color: #ef4444;
        }

        .action-item.delete:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        /* Footer Controls */
        .footer-action-bar {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--card-radius);
            padding: 1.5rem 1.75rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .custom-default-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0;
        }

        .custom-default-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 6px;
            border: 1.5px solid #cbd5e1;
            accent-color: #d97706;
            cursor: pointer;
        }

        .btn-continue-portal {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #e2bd61 0%, #d4a942 100%);
            color: #1a1a1a;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(226, 189, 97, 0.35);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-continue-portal:hover:not(:disabled) {
            background: linear-gradient(135deg, #e8c772 0%, #dbb24c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(226, 189, 97, 0.45);
            color: #000000;
        }

        .btn-continue-portal:active:not(:disabled) {
            transform: translateY(0.5px);
        }

        .btn-continue-portal:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* Site Footer */
        .site-footer {
            text-align: center;
            padding: 1.5rem;
            font-size: 0.775rem;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
        }

        /* Modal Overlay & Card Polish */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 640px;
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
            border-radius: 20px;
            overflow: hidden;
            animation: modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid #e2e8f0;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.96) translateY(8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8fafc;
            flex-shrink: 0;
        }

        .modal-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            border-radius: 6px;
            transition: color 0.15s ease;
        }

        .btn-close-modal:hover {
            color: #0f172a;
        }

        .modal-body {
            padding: 1.75rem;
            overflow-y: auto;
            flex: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .modal-footer {
            padding: 1.25rem 1.75rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background-color: #f8fafc;
            flex-shrink: 0;
        }

        .btn-modal-cancel {
            padding: 0.65rem 1.25rem;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-modal-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-modal-save {
            padding: 0.65rem 1.5rem;
            background: linear-gradient(135deg, #e2bd61 0%, #d4a942 100%);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.875rem;
            color: #1a1a1a;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(226, 189, 97, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-modal-save:hover {
            background: linear-gradient(135deg, #e8c772 0%, #dbb24c 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(226, 189, 97, 0.4);
        }
    </style>
</head>

<body>
    <!-- Top Navigation Bar -->
    <header class="selection-navbar">
        <div class="nav-inner">
            <a href="/" class="d-inline-block">
                <img src="assets/images/logo.png" alt="Ersan Elektrik" class="nav-brand-logo">
            </a>
            <?php if ($currentUser): ?>
                <div class="nav-user-badge">
                    <span class="nav-user-avatar">
                        <?php 
                            $name = $currentUser->adi_soyadi ?? $currentUser->user_name ?? 'U';
                            echo mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
                        ?>
                    </span>
                    <span><?php echo htmlspecialchars($currentUser->adi_soyadi ?? $currentUser->user_name ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container -->
    <main class="main-container">
        <div class="page-header-box">
            <div>
                <h1 class="header-title">Çalışma Alanı Seçimi</h1>
                <p class="header-subtitle">İşlem yapmak istediğiniz firmayı seçerek devam edin</p>
            </div>
            <button class="btn-add-firma" onclick="openModal()">
                <i class="mdi mdi-plus-circle-outline font-size-16"></i>
                <span>Yeni Firma Ekle</span>
            </button>
        </div>

        <div class="branch-list">
            <?php foreach ($branchs as $branch) { ?>
                <div class="branch-item" data-id="<?php echo (int) $branch->id; ?>"
                    data-kodu="<?php echo htmlspecialchars($branch->firma_kodu ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="selectBranch(this)">
                    <div class="radio-wrapper">
                        <div class="custom-radio-ring">
                            <div class="custom-radio-dot"></div>
                        </div>
                    </div>
                    <div class="branch-content">
                        <div class="branch-title-row">
                            <span class="branch-name"><?php echo htmlspecialchars($branch->firma_adi, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($branch->varsayilan_mi)) { ?>
                                <span class="badge-varsayilan">Varsayılan</span>
                            <?php } ?>
                        </div>
                        <div class="branch-meta-grid">
                            <?php if (!empty($branch->firma_kodu)) { ?>
                                <div class="meta-item">
                                    <i class="mdi mdi-pound"></i>
                                    <span><?php echo htmlspecialchars($branch->firma_kodu, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($branch->telefon)) { ?>
                                <div class="meta-item">
                                    <i class="mdi mdi-phone-outline"></i>
                                    <span><?php echo htmlspecialchars($branch->telefon, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($branch->web_sitesi)) { ?>
                                <div class="meta-item">
                                    <i class="mdi mdi-web"></i>
                                    <a href="<?php echo htmlspecialchars($branch->web_sitesi, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($branch->web_sitesi, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (!empty($branch->mersis_no)) { ?>
                                <div class="meta-item">
                                    <i class="mdi mdi-fingerprint"></i>
                                    <span>Mersis: <?php echo htmlspecialchars($branch->mersis_no, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($branch->kep_adresi)) { ?>
                                <div class="meta-item">
                                    <i class="mdi mdi-email-seal-outline"></i>
                                    <span>KEP: <?php echo htmlspecialchars($branch->kep_adresi, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($branch->adres)) { ?>
                                <div class="meta-address">
                                    <i class="mdi mdi-map-marker-outline"></i>
                                    <span><?php echo htmlspecialchars($branch->adres, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="branch-actions" onclick="event.stopPropagation()">
                        <button class="action-trigger" onclick="toggleMenu(this)" title="İşlemler" aria-label="İşlemler">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="action-menu">
                            <div class="action-item" onclick="editFirma(<?php echo (int) $branch->id; ?>)">
                                <i class="mdi mdi-pencil-outline"></i> Düzenle
                            </div>
                            <div class="action-item delete" onclick="deleteFirma(<?php echo (int) $branch->id; ?>)">
                                <i class="mdi mdi-trash-can-outline"></i> Sil
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Footer Actions Bar -->
        <div class="footer-action-bar">
            <label class="custom-default-checkbox">
                <input type="checkbox" id="varsayilan-firma">
                <span>Bu seçimi varsayılan firma olarak hatırla ve sonraki girişlerde otomatik yönlendir</span>
            </label>

            <button id="continue-btn" class="btn-continue-portal" disabled onclick="continueAction()">
                <span>Sisteme Giriş Yap</span>
                <i class="mdi mdi-arrow-right font-size-18"></i>
            </button>
        </div>
    </main>

    <!-- Site Footer -->
    <footer class="site-footer">
        © <?php echo date('Y'); ?> Ersan Elektrik A.Ş. Tüm hakları saklıdır.
    </footer>

    <!-- Firma Ekle/Düzenle Modal -->
    <div class="modal-overlay" id="firmaModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="modalTitle">Yeni Firma Ekle</h2>
                <button class="btn-close-modal" onclick="closeModal()" aria-label="Kapat">&times;</button>
            </div>
            <div class="modal-body">
                <form id="firmaForm">
                    <input type="hidden" name="id" id="firma_id">
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_adi", "", "Firma Adı", "Firma Adı", "briefcase", "form-control", true); ?>
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_kodu", "", "Firma Kodu", "Firma Kodu", "hash"); ?>
                        <span class="text-muted font-size-12 mt-1 d-block">Online İcmal Sorgulama ve entegrasyonlarda kullanılacaktır.</span>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "vergi_no", "", "Vergi No", "Vergi No", "hash"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "vergi_dairesi", "", "Vergi Dairesi", "Vergi Dairesi", "hash"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "vergi_dairesi_no", "", "Vergi Dairesi No", "Vergi Dairesi No", "hash"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "mersis_no", "", "Mersis No", "Mersis No", "hash"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "ticaret_sicil_no", "", "Ticaret Sicil No", "Ticaret Sicil No", "hash"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "telefon", "", "Telefon", "Telefon", "phone"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "web_sitesi", "", "Web Sitesi", "Web Sitesi", "globe"); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Form::FormFloatInput("text", "kep_adresi", "", "KEP Adresi", "KEP Adresi", "mail"); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_unvan", "", "Firma Ünvanı", "Firma Ünvanı", "briefcase"); ?>
                    </div>
                    <div class="form-group full">
                        <label for="logo" class="form-label fw-bold font-size-13 mb-1">Firma Logosu</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg">
                        <div class="text-muted mt-1 font-size-12">Resmî yazılarda kullanılır. PNG veya JPG, en fazla 2 MB.</div>
                        <img id="firma_logo_preview" alt="Firma logosu" style="display:none;max-width:120px;max-height:80px;margin-top:10px;object-fit:contain;border-radius:8px;border:1px solid #e2e8f0;padding:4px;background:#f8fafc;">
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_iban", "", "TR00 0000 0000 0000 0000 0000 00", "Firma IBAN", "credit-card", "form-control mask-iban", false, 32); ?>
                    </div>
                    <div class="form-group full">
                        <?php echo Form::FormFloatTextarea("adres", "", "Adres", "Adres", "map-pin"); ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" onclick="closeModal()">İptal</button>
                <button class="btn-modal-save" onclick="saveFirma()">
                    <i class="mdi mdi-content-save-outline font-size-16"></i>
                    <span>Kaydet</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedId = null;

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            const observer = new MutationObserver(() => {
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });

        function selectBranch(element) {
            document.querySelectorAll('.branch-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            selectedId = element.dataset.id;
            document.getElementById('continue-btn').disabled = false;
        }

        function toggleMenu(btn) {
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.action-menu').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        }

        window.onclick = function (event) {
            if (!event.target.closest('.branch-actions')) {
                document.querySelectorAll('.action-menu').forEach(m => m.classList.remove('show'));
            }
            if (event.target.classList.contains('modal-overlay')) {
                closeModal();
            }
        };

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Yeni Firma Ekle';
            document.getElementById('firmaForm').reset();
            document.getElementById('firma_id').value = '';
            const logoPreview = document.getElementById('firma_logo_preview');
            if (logoPreview) {
                logoPreview.removeAttribute('src');
                logoPreview.style.display = 'none';
            }
            document.getElementById('firmaModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('firmaModal').classList.remove('show');
        }

        function editFirma(id) {
            fetch(`firma-secim.php?action=get&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('modalTitle').innerText = 'Firmayı Düzenle';
                    document.getElementById('firma_id').value = data.id;
                    document.getElementById('firma_adi').value = data.firma_adi || '';
                    if (document.getElementById('firma_kodu')) {
                        document.getElementById('firma_kodu').value = data.firma_kodu || '';
                    }
                    document.getElementById('vergi_no').value = data.vergi_no || '';
                    document.getElementById('vergi_dairesi').value = data.vergi_dairesi || '';
                    if (document.getElementById('vergi_dairesi_no')) {
                        document.getElementById('vergi_dairesi_no').value = data.vergi_dairesi_no || '';
                    }
                    if (document.getElementById('mersis_no')) {
                        document.getElementById('mersis_no').value = data.mersis_no || '';
                    }
                    if (document.getElementById('ticaret_sicil_no')) {
                        document.getElementById('ticaret_sicil_no').value = data.ticaret_sicil_no || '';
                    }
                    document.getElementById('telefon').value = data.telefon || '';
                    document.getElementById('adres').value = data.adres || '';
                    if (document.getElementById('firma_unvan')) {
                        document.getElementById('firma_unvan').value = data.firma_unvan || '';
                    }
                    const logoPreview = document.getElementById('firma_logo_preview');
                    if (logoPreview) {
                        if (data.logo_yolu) {
                            logoPreview.src = data.logo_yolu;
                            logoPreview.style.display = 'block';
                        } else {
                            logoPreview.removeAttribute('src');
                            logoPreview.style.display = 'none';
                        }
                    }
                    if (document.getElementById('web_sitesi')) {
                        document.getElementById('web_sitesi').value = data.web_sitesi || '';
                    }
                    if (document.getElementById('kep_adresi')) {
                        document.getElementById('kep_adresi').value = data.kep_adresi || '';
                    }
                    if (document.getElementById('firma_iban')) {
                        document.getElementById('firma_iban').value = data.firma_iban || '';
                    }
                    document.getElementById('firmaModal').classList.add('show');
                });
        }

        function saveFirma() {
            const form = document.getElementById('firmaForm');
            const firmaAdi = document.getElementById('firma_adi').value;
            const firmaKoduEl = document.getElementById('firma_kodu');
            const firmaKodu = firmaKoduEl ? firmaKoduEl.value : '';

            if (!firmaAdi.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uyarı',
                    text: 'Firma adı boş bırakılamaz!',
                    confirmButtonColor: '#e2bd61'
                });
                return;
            }

            if (firmaKodu && firmaKodu.trim().length > 0) {
                const normalized = firmaKodu.trim();
                if (normalized.length < 2) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Uyarı',
                        text: 'Firma kodu en az 2 karakter olmalıdır!',
                        confirmButtonColor: '#e2bd61'
                    });
                    return;
                }
            }

            const formData = new FormData(form);

            fetch('firma-secim.php?action=save', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: data.message,
                            confirmButtonColor: '#10b981'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: data.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: 'Sistem hatası oluştu.',
                        confirmButtonColor: '#ef4444'
                    });
                });
        }

        document.getElementById('logo')?.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.value = '';
                Swal.fire({
                    icon: 'warning',
                    title: 'Geçersiz Logo',
                    text: 'Logo PNG veya JPG formatında ve en fazla 2 MB olmalıdır.',
                    confirmButtonColor: '#e2bd61'
                });
                return;
            }
            const reader = new FileReader();
            reader.onload = event => {
                const preview = document.getElementById('firma_logo_preview');
                preview.src = event.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });

        function deleteFirma(id) {
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu firmayı silmek istediğinize emin misiniz?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0f172a',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', id);
                    fetch('firma-secim.php?action=delete', {
                        method: 'POST',
                        body: formData
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Silindi!',
                                    text: data.message,
                                    confirmButtonColor: '#10b981'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Hata',
                                    text: data.message,
                                    confirmButtonColor: '#ef4444'
                                });
                            }
                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: 'İşlem sırasında hata oluştu.',
                                confirmButtonColor: '#ef4444'
                            });
                        });
                }
            });
        }

        function continueAction() {
            if (!selectedId) return;
            const isDefault = document.getElementById('varsayilan-firma').checked;
            const selectedEl = document.querySelector('.branch-item.selected');
            const selectedCode = selectedEl ? selectedEl.dataset.kodu : null;
            let url = `set-session.php?firma_id=${selectedId}`;
            if (selectedCode) url += `&firma_kodu=${encodeURIComponent(selectedCode)}`;
            if (isDefault) url += '&varsayilan=1';
            window.location.href = url;
        }
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('./sw.js').then(function (registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function (err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>

</html>
