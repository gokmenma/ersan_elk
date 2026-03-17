<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Form;
use App\Service\Gate;

// Yetki kontrolü (Varsayılan olarak personel_puantaj yetkisi olsun)
// Gate::authorize('personel_puantaj');

?>

<div class="container-fluid">
    <!-- start page title -->
    <?php
    $maintitle = "Personel Yönetimi";
    $title = "Puantaj ve İzin Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <!-- Material Icons -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <style>
        .calendar-card {
            min-height: 500px;
        }

        .izin-type-card {
            cursor: grab;
            transition: transform 0.2s;
            margin-bottom: 10px;
            border-left: 5px solid transparent;
        }

        .izin-type-card:hover {
            transform: scale(1.02);
        }

        .draggable-izin {
            cursor: grab;
            transition: all 0.2s;
            border: none !important;
            font-weight: 600;
        }



        .izin-chip-placeholder {
            display: none;
        }

        .table-puantaj {
            border-collapse: separate !important;
            border-spacing: 4px !important;
        }

        .table-puantaj th:not(.sticky-col):not(.sticky-col-right-1) {
            width: 32px;
            height: 50px;
            padding: 4px 2px !important;
            border: none !important;
            border-radius: 6px;
            vertical-align: middle;
            background-color: #5a67d8;
            color: #ffffff;
            display: table-cell;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-puantaj .day-cell {
            width: 32px;
            height: 32px;
            cursor: cell;
            user-select: none;
            position: relative;
            padding: 0 !important;
            border: none !important;
            border-radius: 6px;
            background-color: #edf2f7;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .table-puantaj .is-sunday {
            background-color: #fee2e2 !important;
            color: #ef4444 !important;
        }

        .table-puantaj th.is-sunday {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            border: none !important;
        }

        .day-header-pill {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.2;
        }

        .day-header-pill .day-name {
            font-size: 10px;
            font-weight: 500;
            opacity: 0.9;
        }

        .day-header-pill .day-number {
            font-size: 13px;
            font-weight: 700;
        }

        .table-puantaj .day-cell.has-entry {
            font-weight: bold;
        }

        .table-puantaj .day-cell.selected {
            background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
            border: 2px dashed var(--bs-primary) !important;
            z-index: 2;
        }

        .table-puantaj .day-cell.unsaved {
            position: relative;
        }

        .table-puantaj .day-cell.unsaved::after {
            content: '●';
            position: absolute;
            top: 2px;
            left: 2px;
            font-size: 8px;
            color: #f1b44c;
        }

        .izin-box {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            cursor: grab;
            transition: all 0.2s;
            user-select: none;
        }

        .izin-box:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .izin-item-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            width: 36px;
        }

        .izin-item-container span {
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        /* Prevent Layout Shift during Drag & Drop */
        .sortable-ghost {
            opacity: 1 !important;
        }

        .sortable-drag {
            opacity: 0.8;
            transform: scale(0.8);
            z-index: 1000;
        }

        .day-cell .izin-item-container {
            display: none !important;
        }

        .table-puantaj .personel-info {
            text-align: left;
            width: 180px !important;
            min-width: 180px !important;
            max-width: 180px !important;
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 8px 12px !important;
            vertical-align: middle;
            border: 1px dashed #ced4da !important;
            border-radius: 4px;
        }

        .table-puantaj .personel-info .d-flex {
            width: 170px;
        }

        .text-truncate-name {
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cell-content {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            position: relative;
            border-radius: 6px;
            cursor: grab;
        }

        .btn-delete-cell {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ff3d60;
            color: white;
            border-radius: 50%;
            width: 12px;
            height: 12px;
            font-size: 8px;
            line-height: 12px;
            text-align: center;
            cursor: pointer;
            display: none;
            z-index: 5;
        }

        .day-cell:hover .btn-delete-cell {
            display: block;
        }

        .badge-izin {
            position: relative;
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #fff;
            width: 100%;
        }

        .badge-izin .btn-delete {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff3d60;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 14px;
            font-size: 10px;
            line-height: 14px;
            text-align: center;
            cursor: pointer;
            display: none;
        }

        .badge-izin:hover .btn-delete {
            display: block;
        }

        .tab-content>.tab-pane {
            display: none;
        }

        .tab-content>.active {
            display: block;
        }

        .fade {
            transition: opacity 0.15s linear;
        }

        .tab-pane.fade {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateY(10px);
            opacity: 0;
        }

        .tab-pane.fade.show {
            transform: translateY(0);
            opacity: 1;
        }

        .sticky-col {
            position: sticky;
            left: 0;
            background-color: #ffffff !important;
            z-index: 20;
            border: 1px solid #ced4da !important;
            width: 180px !important;
            min-width: 180px !important;
            max-width: 180px !important;
        }

        .sticky-col-right-1 {
            position: sticky;
            right: 0;
            background-color: #f8f9fa !important;
            z-index: 20;
            border: 1px solid #ced4da !important;
            width: 70px !important;
            min-width: 70px !important;
            max-width: 70px !important;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
            font-weight: 600;
        }

        /* Nöbet'ten gelen Pill-Tab Stili */
        .view-buttons {
            display: flex;
            gap: 4px;
            background: #f4f4f5;
            padding: 4px;
            border-radius: 8px;
            width: fit-content;
        }

        .view-buttons .nav-link {
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            border: none !important;
            background: transparent;
            color: #71717a;
            transition: all 0.2s ease;
            margin-bottom: 0 !important;
        }

        .view-buttons .nav-link:hover {
            color: #18181b;
        }

        .view-buttons .nav-link.active {
            background: #fff !important;
            color: #18181b !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* Dark mode uyumu */
        [data-bs-theme="dark"] .view-buttons {
            background: #191e22 !important;
            border: 1px solid #32394e !important;
        }

        [data-bs-theme="dark"] .view-buttons .nav-link {
            color: #74788d !important;
        }

        [data-bs-theme="dark"] .view-buttons .nav-link:hover {
            color: #eff2f7 !important;
        }

        [data-bs-theme="dark"] .view-buttons .nav-link.active {
            background: #282f36 !important;
            color: #eff2f7 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        [data-bs-theme="dark"] .sticky-col,
        [data-bs-theme="dark"] .sticky-col-right-1 {
            background-color: #282f36 !important;
            border-color: #32394e !important;
            color: #eff2f7 !important;
        }

        [data-bs-theme="dark"] .puantaj-table-wrapper {
            background: #191e22 !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead tr,
        [data-bs-theme="dark"] .table-puantaj thead th {
            background-color: #282f36 !important;
            border-color: #32394e !important;
            color: #eff2f7 !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .table-puantaj .day-cell {
            background-color: #191e22 !important;
            border-color: #32394e !important;
        }

        [data-bs-theme="dark"] .table-puantaj .personel-info {
            background-color: #282f36 !important;
            border-color: #32394e !important;
            color: #eff2f7 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .is-sunday {
            background-color: rgba(244, 106, 106, 0.05) !important;
            color: #f46a6a !important;
        }

        [data-bs-theme="dark"] .table-puantaj th.is-sunday {
            background-color: rgba(244, 106, 106, 0.1) !important;
            color: #f46a6a !important;
        }

        [data-bs-theme="dark"] .puantaj-table-header,
        [data-bs-theme="dark"] .card-izin-turleri {
            background-color: #282f36 !important;
            border-bottom-color: #32394e !important;
        }

        [data-bs-theme="dark"] #puantaj-full-container {
            background: #191e22 !important;
        }

        [data-bs-theme="dark"] .bg-soft-success {
            background-color: rgba(16, 185, 129, 0.1) !important;
        }

        [data-bs-theme="dark"] .bg-soft-info {
            background-color: rgba(59, 130, 246, 0.1) !important;
        }

        [data-bs-theme="dark"] .action-button-container {
            background-color: #191e22 !important;
            border-color: #32394e !important;
        }

        /* Sticky Header Improvements */
        .puantaj-table-header {
            position: sticky;
            top: 70px;
            z-index: 1025;
            background-color: var(--bs-card-bg, #fff);
        }

        .card-izin-turleri {
            position: sticky;
            top: 135px;
            /* Tahmini header yüksekliği */
            z-index: 1020;
            background-color: var(--bs-card-bg, #fff);
            border-bottom: 1px solid #dee2e6;
        }

        /* Fullscreen Modu Stilleri */
        body.puantaj-fullscreen {
            overflow: hidden !important;
        }

        body.puantaj-fullscreen #puantaj-full-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1050;
            background: var(--bs-body-bg, #f3f3f9);
            padding: 20px;
            overflow-y: auto;
        }

        body.puantaj-fullscreen .puantaj-table-header {
            top: 0 !important;
        }

        body.puantaj-fullscreen .card-izin-turleri {
            top: 70px !important;
        }

        footer {
            display: none;
        }

        body.puantaj-fullscreen .puantaj-table-wrapper {
            max-height: calc(100vh - 200px) !important;
        }

        .puantaj-table-wrapper {
            max-height: calc(100vh - 395px);
            overflow: auto;
            background: #fff;
            /* Scroll snap removed for fluid scrolling without exact row jumps */
        }



        /* Thead sticky - wrapper içinde sabit kalır */
        .table-puantaj thead {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
        }



        /* Thead satırına arka plan ver - border-spacing boşluğunu kapat */
        .table-puantaj thead tr {
            background-color: transparent;
        }

        [data-bs-theme="dark"] .table-puantaj thead tr {
            background-color: #282f36 !important;
        }

        .table-puantaj thead th {
            vertical-align: middle;
            text-align: center;
        }

        .table-puantaj thead th.sticky-col,
        .table-puantaj thead th.sticky-col-right-1 {
            background-color: #f8f9fa !important;
            border: 1px solid #ced4da !important;
            height: 50px;
            /* Match other th */
            box-shadow: 0 1px 0 0 #fff;
        }

        /* Footer sticky - wrapper içinde, ekranın altında sabit kalır */
        .table-puantaj tfoot {
            position: sticky;
            bottom: 0;
            z-index: 50;
        }

        .table-puantaj tfoot tr {
            background-color: #f7f7f7;
            height: 40px;
        }

        [data-bs-theme="dark"] .table-puantaj tfoot tr {
            background-color: #282f36 !important;
        }

        .table-puantaj tfoot td {
            background-color: #f8f9fa !important;
            vertical-align: middle;
            border: 1px solid #ced4da !important;
            height: 40px;
            box-shadow: 0 -1px 0 0 #fff;
        }

        /* Footer sol ve sağ kolonlar */
        .table-puantaj tfoot td.sticky-col {
            z-index: 60;
            background-color: #f8f9fa !important;
            border-bottom-left-radius: 8px;
        }

        .table-puantaj tfoot td.sticky-col-right-1 {
            z-index: 60;
            background-color: #f8f9fa !important;
        }

        [data-bs-theme="dark"] .table-puantaj tfoot td.sticky-col,
        [data-bs-theme="dark"] .table-puantaj tfoot td.sticky-col-right-1 {
            background-color: #282f36 !important;
        }

        /* Header'daki sol ve sağ kolonun z-index'i en yüksek olmalı */
        .table-puantaj thead th.sticky-col,
        .table-puantaj thead th.sticky-col-right-1 {
            z-index: 60;
            background-color: #f8f9fa !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.sticky-col,
        [data-bs-theme="dark"] .table-puantaj thead th.sticky-col-right-1 {
            background-color: #282f36 !important;
        }

        @media (max-width: 1600px) {
            .table-puantaj {
                border-spacing: 3px !important;
            }

            .table-puantaj th:not(.sticky-col):not(.sticky-col-right-1),
            .table-puantaj .day-cell {
                width: 30px;
            }

            .table-puantaj .day-cell {
                height: 30px;
            }

            .day-header-pill .day-number {
                font-size: 12px;
            }
        }

        /* Puantaj Preloader */
        .puantaj-preloader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.82);
            display: none;
            z-index: 1060;
            border-radius: 4px;
            backdrop-filter: blur(3px);
        }

        [data-bs-theme="dark"] .puantaj-preloader {
            background: rgba(25, 30, 34, 0.85);
        }

        .puantaj-preloader .loader-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 250px;
        }

        [data-bs-theme="dark"] .puantaj-preloader .loader-content {
            background: #2a3042;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        .custom-context-menu {
            display: none;
            position: fixed;
            z-index: 10000;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            padding: 6px 0;
            min-width: 200px;
            animation: menuFadeIn 0.2s ease-out;
        }

        @keyframes menuFadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .custom-context-menu .menu-item {
            padding: 10px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            transition: all 0.2s;
        }

        .custom-context-menu .menu-item:hover {
            background: #f9fafb;
            color: #4f46e5;
        }

        .custom-context-menu .menu-item i,
        .custom-context-menu .menu-item .menu-item-code {
            font-size: 11px;
            width: 28px;
            height: 28px;
            line-height: 26px;
            text-align: center;
            border-radius: 6px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .custom-context-menu .menu-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 6px 0;
        }

        .custom-context-menu .menu-header {
            padding: 6px 16px;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .custom-context-menu .menu-item.active {
            background: #f1f5f9;
            color: #4f46e5;
            font-weight: 700;
        }

        .custom-context-menu .menu-item.active::after {
            content: '\eb7a';
            font-family: 'boxicons' !important;
            margin-left: auto;
            font-size: 18px;
        }

        .custom-context-menu .menu-item.text-danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Dark Mode Styles */
        [data-bs-theme="dark"] .custom-context-menu {
            background: #2a3042;
            border-color: #32394e;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-item {
            color: #a6b0cf;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-item:hover {
            background: #32394e;
            color: #fff;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-divider {
            background: #32394e;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-header {
            color: #74788d;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-item.active {
            background: #32394e;
            color: #fff;
        }
    </style>

    <div id="puantaj-full-container">
        <div class="row">
            <!-- Üst Satır: Ay/Yıl ve Butonlar -->
            <div class="col-12">
                <div class="card mb-2 puantaj-table-header">
                    <div
                        class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom">
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 130px;">
                                    <?php
                                    $yillar = [];
                                    for ($y = date('Y'); $y >= 2024; $y--) {
                                        $yillar[$y] = $y;
                                    }
                                    echo Form::FormSelect2("select-yil", $yillar, date('Y'), "Yıl", "calendar", 'key', '', "form-control select2");
                                    ?>
                                </div>
                                <div style="width: 150px;">
                                    <?php
                                    $aylar = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
                                    $aylar_list = [];
                                    foreach ($aylar as $i => $ay) {
                                        $aylar_list[str_pad($i + 1, 2, '0', STR_PAD_LEFT)] = $ay;
                                    }
                                    echo Form::FormSelect2("select-ay", $aylar_list, date('m'), "Ay", "calendar", 'key', '', "form-control select2");
                                    ?>
                                </div>
                                <div style="width: 250px;">
                                    <?php echo Form::FormFloatInput('text', 'personel-filter', '', 'Personel Ara...', 'Personel Ara', 'search'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Taraf: Arama ve Aksiyon Butonları -->
                        <div class="col-lg-6 col-md-12 mt-lg-0 mt-3">
                            <div class="d-flex align-items-center justify-content-lg-end gap-2">

                                <div
                                    class="action-button-container d-flex align-items-center border rounded shadow-sm p-1 gap-1">
                                    <button type="button"
                                        class="btn btn-link btn-sm text-decoration-none px-2 d-flex align-items-center"
                                        id="btn-fullscreen">
                                        <i class="mdi mdi-fullscreen fs-5 me-1"></i>Tam Ekran <span
                                            class="d-none d-xl-inline ms-1"></span>
                                    </button>

                                    <div class="vr mx-1 my-1" style="height: 30px;"></div>

                                    <?php if (Gate::allows("puantaj_sgk_rapor_islemleri")): ?>
                                        <div class="dropdown">
                                            <button type="button"
                                                class="btn btn-link btn-sm text-info text-decoration-none dropdown-toggle px-2 d-flex align-items-center"
                                                data-bs-toggle="dropdown">
                                                <i class="mdi mdi-hospital-building fs-5"></i> <span
                                                    class="d-none d-xl-inline ms-1">SGK</span> <i
                                                    class="mdi mdi-chevron-down ms-1"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item py-2" href="javascript:void(0);"
                                                        id="btn-sgk-onaylanmis-raporlar">
                                                        <i class="mdi mdi-check-circle text-success me-2"></i> Onaylanmış
                                                        Raporlar</a></li>
                                                <li><a class="dropdown-item py-2" href="javascript:void(0);"
                                                        id="btn-sgk-onay-bekleyen-raporlar">
                                                        <i class="mdi mdi-clock-outline text-warning me-2"></i> Bekleyen
                                                        Raporlar</a></li>
                                            </ul>
                                        </div>

                                        <div class="vr mx-1 my-1" style="height: 30px;"></div>

                                    <?php endif; ?>

                                    <div class="dropdown">
                                        <button type="button"
                                            class="btn btn-link btn-sm text-primary text-decoration-none dropdown-toggle px-2 d-flex align-items-center"
                                            data-bs-toggle="dropdown">
                                            <i class="mdi mdi-file-check-outline font-size-18"></i> <span
                                                class="d-none d-xl-inline ms-1">İşlemler</span> <i
                                                class="mdi mdi-chevron-down ms-1"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li><a class="dropdown-item py-2" href="javascript:void(0);"
                                                    id="btn-export-excel">
                                                    <i class="mdi mdi-file-export-outline text-success me-2"></i> Excele
                                                    Aktar</a></li>
                                            <li><a class="dropdown-item py-2" href="javascript:void(0);"
                                                    id="btn-open-excel-modal">
                                                    <i class="mdi mdi-file-import-outline text-primary me-2"></i>
                                                    Excelden Yükle</a></li>
                                        </ul>
                                    </div>

                                    <div class="vr mx-1 my-1" style="height: 30px;"></div>

                                    <button type="button"
                                        class="btn btn-primary px-4 fw-bold shadow-primary pulsate-on-change"
                                        id="btn-save-selected">
                                        <i class="mdi mdi-content-save-outline me-1"></i> Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>

            <!-- Orta Satır: İzin Türleri (Tabloya Daha Yakın) -->
            <div class="col-12">
                <div class="card mb-2 card-izin-turleri border-0 shadow-sm">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div class="view-buttons nav" role="tablist">
                                <a class="nav-link active" data-bs-toggle="tab" href="#ucretli-izinler" role="tab">
                                    Ücretli
                                </a>
                                <a class="nav-link" data-bs-toggle="tab" href="#ucretsiz-izinler" role="tab">
                                    Ücretsiz
                                </a>
                            </div>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="ucretli-izinler" role="tabpanel">
                                <div id="ucretli-list" class="d-flex flex-wrap justify-content-center gap-1 p-2">
                                    <!-- API'den gelecek -->
                                </div>
                            </div>
                            <div class="tab-pane fade" id="ucretsiz-izinler" role="tabpanel">
                                <div id="ucretsiz-list" class="d-flex flex-wrap justify-content-center gap-1 p-2">
                                    <!-- API'den gelecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alt Satır: Tablo -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-0 position-relative">
                        <!-- Preloader - Tablonun Genelinde Çıkması İçin Buraya Taşındı -->
                        <div class="puantaj-preloader" id="puantaj-loader">
                            <div class="loader-content">
                                <div class="spinner-border text-primary m-1" role="status">
                                    <span class="sr-only">Yükleniyor...</span>
                                </div>
                                <h5 class="mt-2 mb-0">Veriler Hazırlanıyor...</h5>
                                <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                            </div>
                        </div>

                        <div class="table-responsive puantaj-table-wrapper">
                            <table class="table table-puantaj mb-0" id="puantaj-table">
                                <thead>
                                    <tr id="table-header">
                                        <th class="sticky-col">Personel</th>
                                        <!-- Günler dinamik gelecek -->
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    <!-- Personeller ve veriler dinamik gelecek -->
                                </tbody>
                                <tfoot id="table-footer" class="table-light fw-bold">
                                    <!-- Toplamlar dinamik gelecek -->
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Excel Import Modal -->
<div class="modal fade" id="excelImportModal" tabindex="-1" aria-labelledby="excelImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="excelImportModalLabel">Excel'den Personel Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Şablon İndirme Alanı -->
                <div class="card bg-soft-success border-success mb-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar-sm me-3">
                                <span class="avatar-title bg-soft-success text-success rounded-circle font-size-20">
                                    <i class="mdi mdi-download"></i>
                                </span>
                            </div>
                            <div>
                                <h5 class="font-size-14 mb-1 text-success">Şablon Dosyasını İndirin</h5>
                                <p class="text-muted mb-0 font-size-12">Personelleri Excelden yüklemek için şablonunu
                                    indirin.</p>
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-success btn-sm w-100" id="btn-download-template-modal">
                                <i class="mdi mdi-download me-1"></i> Personel Şablonunu İndir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dosya Seçme Alanı -->
                <div class="mb-3">
                    <label for="import-excel-file-modal" class="form-label font-size-13">Excel Dosyası Seçin (.xlsx,
                        .xls)</label>
                    <input class="form-control" type="file" id="import-excel-file-modal" accept=".xlsx, .xls">
                    <div class="form-text mt-2">
                        <i class="mdi mdi-information-outline me-1"></i> Format: İlk sütun <b>Personel</b> adı, sonraki
                        sütunlar gün numaraları (1, 2, 3, ...). Hücrelere izin kodlarını yazın (MI, RP, D vb.)
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary px-4" id="btn-import-excel-submit">Yükle</button>
            </div>
        </div>
    </div>
</div>

<!-- SGK Rapor Modal -->
<div class="modal fade" id="sgkRaporModal" tabindex="-1" aria-labelledby="sgkRaporModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white" id="sgkRaporModalLabel">SGK Raporları</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="sgkRaporModalBody">
                <!-- İçerik JS ile dolacak -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="mdi mdi-close me-1"></i> Vazgeç
                </button>
                <button type="button" class="btn btn-primary px-4 fw-bold" id="btn-sgk-rapor-onayla">
                    <i class="mdi mdi-check-all me-1"></i> Seçilenleri Puantaja İşle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Context Menu -->
<div id="custom-context-menu" class="custom-context-menu shadow-lg">
    <div class="menu-header" id="menu-header-text">İŞLEMLER</div>
    <div class="menu-divider"></div>
    <div id="context-menu-items">
        <!-- Dinamik olarak dolacak -->
    </div>
    <div class="menu-divider"></div>
    <div class="menu-item text-danger" id="menu-item-delete">
        <i class="bx bx-trash"></i>
        <span>Sil</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="views/personel/js/puantaj_izin.js"></script>