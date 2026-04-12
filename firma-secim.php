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
    <title>Firma Seçimi | Ersan Elektrik</title>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#135bec">
    <link rel="apple-touch-icon" href="assets/icons/icon-192-new.png">

    <script>
        (function () {
            const htmlAttributes = [
                { name: 'data-theme-mode', target: 'html' },
                { name: 'data-bs-theme', target: 'html' },
                { name: 'dir', target: 'html' }
            ];

            const applyAttribute = (attr, value) => {
                const targetEl = document.documentElement;
                if (attr.name === 'dir') {
                    targetEl.setAttribute('dir', value);
                } else {
                    targetEl.setAttribute(attr.name, value);
                }
            };

            htmlAttributes.forEach(attr => {
                const value = localStorage.getItem(attr.name);
                if (value) applyAttribute(attr, value);
            });
        })();
    </script>

    <!-- Geist Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo Helper::base_url('assets/css/icons.min.css'); ?>" rel="stylesheet" type="text/css" />
    <!-- Bootstrap Css -->
    <link href="<?php echo Helper::base_url('assets/css/bootstrap.min.css'); ?>" id="bootstrap-style" rel="stylesheet"
        type="text/css" />
    <!-- App Css-->
    <link href="<?php echo Helper::base_url('assets/css/app.min.css'); ?>" id="app-style" rel="stylesheet"
        type="text/css" />
    <!-- Custom Style -->
    <link href="<?php echo Helper::base_url('assets/css/style.css'); ?>" rel="stylesheet" type="text/css" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        :root {
            --radius: 0.75rem;
            --bs-secondary-bg: #f0f0f0;
        }

        body {
            font-family: 'Geist', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 40px 20px;
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
        }

        .container {
            width: 100%;
            max-width: 700px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
        }

        .header-info h1 {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .header-info p {
            color: var(--bs-secondary-color);
            font-size: 1rem;
            margin-top: 0.25rem;
        }

        .btn-add-new {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .branch-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .branch-item {
            background: var(--bs-card-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }

        .branch-item:hover {
            border-color: var(--bs-primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .branch-item.selected {
            border-color: var(--bs-primary);
        }

        .radio-wrapper {
            padding-top: 0.25rem;
        }

        .custom-radio {
            width: 1rem;
            height: 1rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .branch-item.selected .custom-radio {
            border-color: var(--bs-primary);
        }

        .custom-radio::after {
            content: '';
            width: 0.625rem;
            height: 0.625rem;
            background-color: var(--bs-primary);
            border-radius: 50%;
            transform: scale(0);
        }

        .branch-item.selected .custom-radio::after {
            transform: scale(1);
        }

        .branch-content {
            flex: 1;
        }

        .branch-title-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .branch-name {
            font-weight: 500;
            font-size: 1rem;
        }

        .badge-varsayilan {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.125rem 0.5rem;
            border-radius: 2rem;
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
        }

        .branch-details {
            color: var(--bs-secondary-color);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .branch-details p {
            margin-bottom: 0.25rem;
        }

        .branch-actions {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
        }

        .action-trigger {
            background: none;
            border: none;
            color: var(--bs-secondary-color);
            cursor: pointer;
            font-size: 1.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .action-trigger:hover {
            background-color: var(--bs-secondary-bg);
            color: var(--bs-body-color);
        }

        .action-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bs-card-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            display: none;
            min-width: 140px;
            padding: 0.25rem;
        }

        .action-menu.show {
            display: block;
        }

        .action-item {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            color: var(--bs-body-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 0.375rem;
        }

        .action-item:hover {
            background-color: var(--bs-secondary-bg);
        }

        .action-item i,
        .action-item svg {
            width: 14px;
            height: 14px;
            color: var(--bs-secondary-color);
        }

        .action-item.delete {
            color: #ef4444;
        }

        .action-item.delete i,
        .action-item.delete svg {
            color: #ef4444;
        }

        .action-item.delete:hover {
            background-color: rgba(239, 68, 68, 0.05);
        }

        .footer-actions {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .varsayilan-check {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            user-select: none;
            font-size: 0.875rem;
            color: var(--bs-secondary-color);
        }

        .btn-continue {
            width: 100%;
            padding: 1rem;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Styles Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-card {
            background-color: var(--bs-body-bg);
            width: 100%;
            max-width: 600px;
            border-radius: var(--radius);
            overflow: hidden;
            animation: modalIn 0.3s ease-out;
            border: 1px solid var(--bs-border-color);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            z-index: 101;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--bs-border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--bs-body-bg);
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--bs-secondary-color);
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full {
            grid-column: span 2;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--bs-border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background-color: var(--bs-body-bg);
        }

        .btn-save {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-cancel {
            font-weight: 600;
        }

        .mr-2 {
            margin-right: 0.5rem;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="page-header">
            <div class="header-info">
                <h1>Firmalarım</h1>
                <p>İşlem yapmak istediğiniz firmayı seçin</p>
            </div>
            <button class="btn btn-primary waves-effect btn-label waves-light btn-add-new mr-2" onclick="openModal()">
                Yeni Ekle
            </button>
        </div>

        <div class="branch-list">
            <?php foreach ($branchs as $branch) { ?>
                <div class="branch-item" data-id="<?php echo $branch->id ?>"
                    data-kodu="<?php echo htmlspecialchars($branch->firma_kodu ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="selectBranch(this)">
                    <div class="radio-wrapper">
                        <div class="custom-radio"></div>
                    </div>
                    <div class="branch-content">
                        <div class="branch-title-row">
                            <span class="branch-name"><?php echo $branch->firma_adi ?></span>
                            <?php if ($branch->varsayilan_mi) { ?>
                                <span class="badge-varsayilan">Varsayılan</span>
                            <?php } ?>
                        </div>
                        <div class="branch-details">
                            <?php if (!empty($branch->firma_kodu)) { ?>
                                <p><i class="fa fa-hashtag" style="width: 1.25rem;"></i>
                                    <?php echo htmlspecialchars($branch->firma_kodu, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php } ?>
                            <?php if (!empty($branch->mersis_no)) { ?>
                                <p><i class="fa fa-fingerprint" style="width: 1.25rem;"></i> Mersis:
                                    <?php echo htmlspecialchars($branch->mersis_no, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php } ?>
                            <?php if (!empty($branch->ticaret_sicil_no)) { ?>
                                <p><i class="fa fa-id-card" style="width: 1.25rem;"></i> Tic. Sicil:
                                    <?php echo htmlspecialchars($branch->ticaret_sicil_no, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php } ?>
                            <?php if ($branch->adres) { ?>
                                <p><i class="fa fa-location-dot" style="width: 1.25rem;"></i> <?php echo $branch->adres ?>
                                </p>
                            <?php } ?>
                            <?php if ($branch->telefon) { ?>
                                <p><i class="fa fa-phone" style="width: 1.25rem;"></i> <?php echo $branch->telefon ?></p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="branch-actions" onclick="event.stopPropagation()">
                        <button class="action-trigger" onclick="toggleMenu(this)">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <div class="action-menu">
                            <div class="action-item" onclick="editFirma(<?php echo $branch->id ?>)">
                                <i data-feather="edit-2"></i> Düzenle
                            </div>
                            <div class="action-item delete" onclick="deleteFirma(<?php echo $branch->id ?>)">
                                <i data-feather="trash-2"></i> Sil
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="footer-actions">
            <label class="varsayilan-check">
                <input type="checkbox" id="varsayilan-firma" style="width: 1rem; height: 1rem; cursor: pointer;">
                Varsayılan firma olarak ayarla
            </label>

            <button id="continue-btn" class="btn btn-primary waves-effect btn-label waves-light btn-continue" disabled
                onclick="continueAction()">
                <i class="bx bx-right-arrow-alt label-icon"></i>
                Devam Et
            </button>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="firmaModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="modalTitle">Yeni Firma Ekle</h2>
                <button class="btn-close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="firmaForm">
                    <input type="hidden" name="id" id="firma_id">
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_adi", "", "Firma Adı", "Firma Adı", "briefcase", "form-control", true); ?>
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_kodu", "", "Firma Kodu", "Firma Kodu", "hash"); ?>
                        <span class="text-muted">Online İcmal Sorgulama raporunda kullanılacaktır.</span>
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
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_unvan", "", "Firma Ünvanı", "Firma Ünvanı", "briefcase"); ?>
                    </div>
                    <div class="form-group">
                        <?php echo Form::FormFloatInput("text", "firma_iban", "", "Firma IBAN", "Firma IBAN", "credit-card"); ?>
                    </div>
                    <div class="form-group full">
                        <?php echo Form::FormFloatTextarea("adres", "", "Adres", "Adres", "map-pin"); ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary waves-effect btn-label waves-light" onclick="closeModal()">
                    <i class="bx bx-x label-icon"></i> İptal</button>
                <button class="btn btn-primary waves-effect btn-label waves-light btn-save" onclick="saveFirma()">
                    <i class="bx bx-save label-icon"></i>
                    Kaydet
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

            // MutationObserver to handle dynamically added feather icons
            const observer = new MutationObserver(() => {
                feather.replace();
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
            if (!event.target.matches('.action-trigger') && !event.target.matches('.bx-dots-vertical-rounded')) {
                document.querySelectorAll('.action-menu').forEach(m => m.classList.remove('show'));
            }
            if (event.target.matches('.modal-overlay')) {
                closeModal();
            }
        }

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Yeni Firma Ekle';
            document.getElementById('firmaForm').reset();
            document.getElementById('firma_id').value = '';
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
                    document.getElementById('firma_adi').value = data.firma_adi;
                    if (document.getElementById('firma_kodu')) {
                        document.getElementById('firma_kodu').value = data.firma_kodu ?? '';
                    }
                    document.getElementById('vergi_no').value = data.vergi_no;
                    document.getElementById('vergi_dairesi').value = data.vergi_dairesi;
                    if (document.getElementById('vergi_dairesi_no')) {
                        document.getElementById('vergi_dairesi_no').value = data.vergi_dairesi_no ?? '';
                    }
                    if (document.getElementById('mersis_no')) {
                        document.getElementById('mersis_no').value = data.mersis_no ?? '';
                    }
                    if (document.getElementById('ticaret_sicil_no')) {
                        document.getElementById('ticaret_sicil_no').value = data.ticaret_sicil_no ?? '';
                    }
                    document.getElementById('telefon').value = data.telefon;
                    document.getElementById('adres').value = data.adres;
                    if (document.getElementById('firma_unvan')) {
                        document.getElementById('firma_unvan').value = data.firma_unvan ?? '';
                    }
                    if (document.getElementById('firma_iban')) {
                        document.getElementById('firma_iban').value = data.firma_iban ?? '';
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
                Swal.fire('Hata', 'Firma adı boş bırakılamaz!', 'error');
                return;
            }

            // firma_kodu opsiyonel; doluysa temel format kontrolü
            if (firmaKodu && firmaKodu.trim().length > 0) {
                const normalized = firmaKodu.trim();
                if (normalized.length < 2) {
                    Swal.fire('Hata', 'Firma kodu en az 2 karakter olmalıdır!', 'error');
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
                        Swal.fire('Başarılı', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                });
        }

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
                                Swal.fire('Silindi!', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Hata', data.message, 'error');
                            }
                        });
                }
            });
        }

        function continueAction() {
            if (!selectedId) return;
            const isDefault = document.getElementById('varsayilan-firma').checked;
            const selectedEl = document.querySelector(`.branch-item.selected`);
            const selectedCode = selectedEl ? selectedEl.dataset.kodu : null;
            let url = `/set-session.php?firma_id=${selectedId}`;
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