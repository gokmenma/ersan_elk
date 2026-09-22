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
        /* -------------------------------------------------------------
         * Modern Soft SaaS Puantaj Design System (Light & Dark)
         * ----------------------------------------------------------- */
        #puantaj-full-container {
            --pnt-primary: #4f46e5;
            --pnt-primary-light: #6366f1;
            --pnt-primary-soft: #eef2ff;
            --pnt-primary-border: #e0e7ff;
            --pnt-primary-text: #4338ca;
            
            --pnt-text-main: #0f172a;
            --pnt-text-muted: #64748b;
            --pnt-text-subtle: #94a3b8;
            
            --pnt-border-subtle: #f1f5f9;
            --pnt-border-light: #e2e8f0;
            --pnt-border-medium: #cbd5e1;
            
            --pnt-surface-card: #ffffff;
            --pnt-surface-alt: #f8fafc;
            --pnt-surface-hover: #f8faff;
            
            --pnt-sunday-bg: #fff1f2;
            --pnt-sunday-text: #e11d48;
            --pnt-sunday-border: #ffe4e6;
            --pnt-sunday-column-bg: #fffcfc;
            
            --pnt-today-bg: #eef2ff;
            --pnt-today-text: #4338ca;
            --pnt-today-border: #c7d2fe;
            --pnt-today-column-bg: #f8faff;
        }

        #puantaj-full-container > .row { row-gap: 8px; }
        #puantaj-full-container > .row > .col-12 > .card { margin-bottom: 0 !important; }

        /* Üst Filtre Kartı */
        .puantaj-table-header {
            overflow: visible;
            border: 1px solid var(--pnt-border-light) !important;
            border-radius: 14px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03) !important;
            background: var(--pnt-surface-card);
        }

        .puantaj-table-header .card-header {
            min-height: 68px;
            padding: 10px 16px !important;
            border: 0 !important;
        }

        .puantaj-filter-cluster { gap: 8px !important; }
        .puantaj-filter-cluster > div { position: relative; }

        .puantaj-filter-cluster .form-control,
        .puantaj-filter-cluster .select2-selection {
            border-color: #e2e8f0 !important;
            background-color: #f8fafc !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            font-size: 13px !important;
            color: var(--pnt-text-main) !important;
            transition: all 0.15s ease;
        }

        .puantaj-filter-cluster .form-control:focus,
        .puantaj-filter-cluster .select2-container--open .select2-selection {
            border-color: #6366f1 !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12) !important;
        }

        .iskur-filter {
            min-width: 120px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .action-button-container {
            padding: 4px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }

        .action-button-container .btn {
            min-height: 34px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        #btn-save-selected {
            border: 0;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.28) !important;
            transition: all 0.18s ease;
        }

        #btn-save-selected:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.38) !important;
        }

        /* Puantaj Türleri Palet Kartı */
        .card-izin-turleri {
            position: relative;
            top: auto !important;
            border: 1px solid var(--pnt-border-light) !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
            background: var(--pnt-surface-card);
        }
        body:not(.puantaj-palette-floating) .card-izin-turleri {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
        }

        .card-izin-turleri .card-body { padding: 8px 14px !important; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .card-body {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        body:not(.puantaj-palette-floating) .card-izin-turleri .izin-palette-header { display: contents; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .izin-palette-header > :first-child { flex: 0 0 auto; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .izin-palette-header > :last-child { order: 4; margin-left: auto; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .view-buttons { order: 2; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .tab-content { order: 3; flex: 1 1 auto; min-width: 0; }
        body:not(.puantaj-palette-floating) .card-izin-turleri .tab-pane > div {
            justify-content: flex-start !important;
            padding: 0 !important;
        }

        .izin-palette-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            min-height: 32px;
        }

        .izin-palette-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--pnt-text-main);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .izin-palette-title::before {
            content: '';
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--pnt-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .izin-palette-helper {
            margin-left: 8px;
            color: var(--pnt-text-muted);
            font-size: 11px;
            font-weight: 500;
        }

        .izin-palette-handle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            padding: 0;
            border: 0;
            border-radius: 6px;
            color: var(--pnt-text-subtle);
            background: transparent;
            cursor: grab;
            touch-action: none;
            transition: all 0.15s ease;
        }
        .izin-palette-handle:hover { color: var(--pnt-primary); background: var(--pnt-primary-soft); }
        .izin-palette-handle:active { cursor: grabbing; }
        .izin-palette-restore { display: none; }

        /* Modern Segmented Control / View Buttons */
        .view-buttons {
            display: flex;
            flex-direction: row !important;
            flex-wrap: nowrap;
            gap: 2px;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            width: fit-content;
        }

        .view-buttons .nav-link {
            display: block;
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
            border: none !important;
            background: transparent;
            color: var(--pnt-text-muted);
            transition: all 0.15s ease;
            margin-bottom: 0 !important;
        }

        .view-buttons .nav-link:hover {
            color: var(--pnt-text-main);
        }

        .view-buttons .nav-link.active {
            background: #ffffff !important;
            color: var(--pnt-primary-text) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            font-weight: 700;
        }

        /* İzin Çipleri Paleti (Draggable Box) */
        .izin-box {
            width: 40px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            font-weight: 700;
            font-size: 12px;
            cursor: grab;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .izin-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .izin-item-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            width: 40px;
        }

        .draggable-izin {
            cursor: grab;
            transition: all 0.15s ease;
            user-select: none;
        }

        #izin-turleri-palette .draggable-izin.is-selected .izin-box {
            transform: translateY(-2px);
            box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px var(--pnt-primary), 0 4px 10px rgba(79, 70, 229, 0.25);
        }

        #izin-turleri-palette .draggable-izin.is-selected::after {
            content: '✓';
            position: absolute;
            top: -5px;
            right: -4px;
            display: grid;
            place-items: center;
            width: 15px;
            height: 15px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            background: var(--pnt-primary);
            color: #ffffff;
            font-size: 9px;
            font-weight: 900;
            z-index: 2;
        }

        /* Puantaj Tablo Kartı */
        .puantaj-grid-card {
            overflow: hidden;
            border: 1px solid var(--pnt-border-light) !important;
            border-radius: 14px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 14px rgba(15, 23, 42, 0.03) !important;
            background: var(--pnt-surface-card);
        }

        .puantaj-grid-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 48px;
            padding: 8px 16px;
            border-bottom: 1px solid var(--pnt-border-light);
            background: #f8fafc;
        }

        .puantaj-grid-title {
            color: var(--pnt-text-main);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .puantaj-grid-subtitle { color: var(--pnt-text-muted); font-size: 11px; }
        .puantaj-live-stats { display: flex; align-items: center; gap: 6px; }
        
        .puantaj-stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 28px;
            padding: 3px 10px;
            border: 1px solid var(--pnt-border-light);
            border-radius: 999px;
            background: #ffffff;
            color: var(--pnt-text-muted);
            font-size: 11px;
            font-weight: 600;
        }
        .puantaj-stat-pill i { color: var(--pnt-primary); font-size: 14px; }
        .puantaj-stat-pill.is-unsaved { border-color: #fde68a; background: #fffbeb; color: #b45309; }
        .puantaj-stat-pill.is-unsaved i { color: #d97706; }

        .puantaj-focus-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            border: 1px solid var(--pnt-border-light);
            border-radius: 50%;
            background: #ffffff;
            color: var(--pnt-text-muted);
            font-size: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .puantaj-focus-toggle:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
            color: var(--pnt-text-main);
        }

        /* Odak Modu (Focus Mode) Geçiş ve Gizleme */
        .puantaj-focus-panel {
            transition: max-height 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.22s ease, margin 0.28s ease, padding 0.28s ease;
            max-height: 500px;
            opacity: 1;
            overflow: visible;
        }

        body.puantaj-focus-transition .puantaj-focus-panel {
            overflow: hidden !important;
        }

        body.puantaj-focus-mode .puantaj-focus-panel {
            max-height: 0 !important;
            opacity: 0 !important;
            margin: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }

        body.puantaj-focus-mode .puantaj-table-wrapper {
            max-height: calc(100vh - 210px) !important;
        }

        /* -------------------------------------------------------------
         * Modern Tablo & Grid Stilleri (Pixel-Perfect & Ergonomik)
         * ----------------------------------------------------------- */
        .puantaj-table-wrapper {
            max-height: calc(100vh - 350px);
            overflow: auto;
            background: #ffffff;
            scrollbar-color: #cbd5e1 transparent;
            scrollbar-width: thin;
        }

        .table-puantaj {
            width: 100%;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: #ffffff;
            font-family: inherit;
        }

        /* Sticky Header */
        .table-puantaj thead {
            position: sticky;
            top: 0;
            z-index: 30;
            background: #f8fafc;
        }

        .table-puantaj thead tr {
            background: #f8fafc;
        }

        /* Gün Başlık Hücreleri */
        .table-puantaj thead th:not(.sticky-col):not(.sticky-col-right-1) {
            width: 44px;
            min-width: 44px;
            max-width: 44px;
            height: 52px;
            padding: 0 !important;
            vertical-align: middle;
            text-align: center;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0 !important;
            border-right: 1px solid #edf2f7 !important;
            color: var(--pnt-text-muted);
            user-select: none;
            transition: background 0.15s ease;
        }

        .day-header-pill {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.2;
            padding: 4px 2px;
        }

        .day-header-pill .day-name {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }

        .day-header-pill .day-number {
            margin-top: 2px;
            font-size: 14px;
            font-weight: 750;
            color: #1e293b;
        }

        /* Pazar Gün Başlığı: Soft Rose */
        .table-puantaj thead th.is-sunday:not(.sticky-col) {
            background: #fff1f2 !important;
            border-bottom: 2px solid #fda4af !important;
            border-right: 1px solid #ffe4e6 !important;
        }
        .table-puantaj thead th.is-sunday .day-name {
            color: #e11d48 !important;
        }
        .table-puantaj thead th.is-sunday .day-number {
            color: #be123c !important;
        }

        /* Bugün Gün Başlığı: Soft Indigo */
        .table-puantaj thead th.is-today:not(.sticky-col) {
            background: #eef2ff !important;
            border-bottom: 2px solid #6366f1 !important;
            border-right: 1px solid #e0e7ff !important;
        }
        .table-puantaj thead th.is-today .day-name {
            color: #4f46e5 !important;
        }
        .table-puantaj thead th.is-today .day-number {
            color: #3730a3 !important;
        }

        /* Tablo Gövdesi ve Satırları */
        .table-puantaj tbody tr {
            background: #ffffff;
            transition: background 0.12s ease;
        }

        .table-puantaj tbody tr:hover {
            background: #f8faff !important;
        }

        /* Personel Kolonu (Sabit Sol) */
        .sticky-col {
            position: sticky;
            left: 0;
            z-index: 25;
            background-color: #ffffff !important;
            border-right: 1px solid #e2e8f0 !important;
            border-bottom: 1px solid #edf2f7 !important;
            width: 220px !important;
            min-width: 220px !important;
            max-width: 220px !important;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.02);
        }

        .table-puantaj thead th.sticky-col {
            z-index: 35;
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-right: 1px solid #e2e8f0 !important;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            text-align: left;
            padding: 0 14px !important;
            vertical-align: middle;
            height: 52px;
        }

        .table-puantaj .personel-info {
            height: 48px;
            padding: 6px 14px !important;
            vertical-align: middle;
            font-size: 13px;
            background-color: #ffffff !important;
        }

        .table-puantaj tbody tr:hover .personel-info {
            background-color: #f8faff !important;
            border-left: 3px solid #6366f1 !important;
            padding-left: 11px !important;
        }

        .table-puantaj .personel-info .d-flex {
            width: 100%;
            gap: 10px;
        }

        .personel-avatar-mini {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .personel-avatar-tone-0 { background: #eff6ff; color: #2563eb; }
        .personel-avatar-tone-1 { background: #f0fdf4; color: #16a34a; }
        .personel-avatar-tone-2 { background: #fff7ed; color: #ea580c; }
        .personel-avatar-tone-3 { background: #f5f3ff; color: #7c3aed; }
        .personel-avatar-tone-4 { background: #fff1f2; color: #e11d48; }

        .table-puantaj .text-truncate-name {
            display: inline-block;
            max-width: 155px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #1e293b !important;
            font-weight: 600 !important;
            font-size: 12.5px;
            transition: color 0.15s ease;
        }

        .table-puantaj .text-truncate-name:hover {
            color: var(--pnt-primary) !important;
        }

        /* Gün Hücresi (.day-cell) */
        .table-puantaj .day-cell {
            width: 44px;
            min-width: 44px;
            max-width: 44px;
            height: 48px;
            padding: 3px !important;
            vertical-align: middle;
            text-align: center;
            border-right: 1px solid #edf2f7 !important;
            border-bottom: 1px solid #edf2f7 !important;
            background: #ffffff;
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: background 0.12s ease;
        }

        /* Pazar Sütunu Hücreleri: Soft Pastel Rose */
        .table-puantaj .day-cell.is-sunday {
            background-color: #fffcfc !important;
            border-right: 1px solid #ffe4e6 !important;
        }

        /* Bugün Sütunu Hücreleri: Soft Pastel Indigo */
        .table-puantaj .day-cell.is-today {
            background-color: #f8faff !important;
            border-right: 1px solid #e0e7ff !important;
        }

        /* Devre Dışı Gün Hücreleri */
        .table-puantaj .day-cell.disabled {
            background-color: #f8fafc !important;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Hücre Hover */
        .table-puantaj .day-cell:hover:not(.disabled) {
            z-index: 5;
            box-shadow: inset 0 0 0 1.5px #6366f1, 0 2px 8px rgba(99, 102, 241, 0.15) !important;
            border-radius: 6px;
        }

        /* Hücre İçi İzin Çipi (.cell-content) */
        .table-puantaj .cell-content {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            min-height: 34px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 750;
            letter-spacing: 0.01em;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.12s ease;
        }

        .table-puantaj .cell-content:hover {
            transform: scale(1.03);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        }

        /* Silme Butonu (Hover'da Çıkan Mini X) */
        .btn-delete-cell {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: #ffffff;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 11px;
            line-height: 14px;
            text-align: center;
            cursor: pointer;
            display: none;
            z-index: 10;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
            font-weight: 900;
        }

        .day-cell:hover .btn-delete-cell {
            display: block;
        }

        /* Kaydedilmemiş Değişiklik Göstergesi */
        .table-puantaj .day-cell.unsaved::after {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #f59e0b;
            box-shadow: 0 0 0 1.5px #ffffff;
            z-index: 8;
        }

        /* Toplam Gün Kolonu (Sabit Sağ) */
        .sticky-col-right-1 {
            position: sticky;
            right: 0;
            z-index: 25;
            background-color: #f8fafc !important;
            border-left: 1px solid #e2e8f0 !important;
            border-bottom: 1px solid #edf2f7 !important;
            width: 80px !important;
            min-width: 80px !important;
            max-width: 80px !important;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
            font-weight: 750;
            color: #1e293b;
        }

        .table-puantaj thead th.sticky-col-right-1 {
            z-index: 35;
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-left: 1px solid #e2e8f0 !important;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            height: 52px;
            padding: 0 !important;
        }

        .table-puantaj .toplam-calisma-gunu {
            font-size: 13px;
            font-weight: 750;
            color: #334155;
        }

        /* Footer (Toplamlar Satırı) */
        .table-puantaj tfoot {
            position: sticky;
            bottom: 0;
            z-index: 30;
            background: #f8fafc;
        }

        .table-puantaj tfoot tr {
            height: 46px;
            background: #f8fafc !important;
        }

        .table-puantaj tfoot td {
            height: 46px;
            vertical-align: middle;
            border-top: 1px solid #cbd5e1 !important;
            border-bottom: 0 !important;
            border-right: 1px solid #edf2f7 !important;
            background: #f8fafc !important;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            padding: 0 !important;
        }

        .table-puantaj tfoot td.sticky-col {
            z-index: 35;
            background: #f8fafc !important;
            border-right: 1px solid #e2e8f0 !important;
            padding: 0 14px !important;
        }

        .table-puantaj tfoot td.sticky-col-right-1 {
            z-index: 35;
            background: #f8fafc !important;
            border-left: 1px solid #e2e8f0 !important;
        }

        .table-puantaj tfoot td.is-sunday {
            background: #fff1f2 !important;
            color: #be123c !important;
        }

        /* Preloader */
        .puantaj-preloader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.85);
            display: none;
            z-index: 1060;
            backdrop-filter: blur(4px);
        }

        .puantaj-preloader .loader-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #ffffff;
            padding: 2rem;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            text-align: center;
            min-width: 220px;
        }

        /* Context Menu */
        .custom-context-menu {
            display: none;
            position: fixed;
            z-index: 10000;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            padding: 5px;
            min-width: 200px;
            animation: menuFadeIn 0.15s ease-out;
        }

        @keyframes menuFadeIn {
            from { opacity: 0; transform: translateY(-4px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .custom-context-menu .menu-item {
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            transition: all 0.12s ease;
        }

        .custom-context-menu .menu-item:hover {
            background: #f1f5f9;
            color: var(--pnt-primary);
        }

        .custom-context-menu .menu-item i,
        .custom-context-menu .menu-item .menu-item-code {
            font-size: 11px;
            width: 24px;
            height: 24px;
            line-height: 22px;
            text-align: center;
            border-radius: 5px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .custom-context-menu .menu-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 4px 0;
        }

        .custom-context-menu .menu-header {
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Floating Palet & Fullscreen Modları */
        body.puantaj-palette-floating .card-izin-turleri {
            position: fixed;
            z-index: 1205;
            width: min(380px, calc(100vw - 32px));
            margin: 0 !important;
            border: 1px solid rgba(99, 102, 241, 0.2) !important;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18) !important;
        }

        body.puantaj-palette-floating .card-izin-turleri .card-body { padding: 10px !important; }
        body.puantaj-palette-floating .izin-palette-restore { display: inline-flex; }
        body.puantaj-palette-floating .card-izin-turleri .tab-content { max-height: 180px; overflow-y: auto; }

        body.puantaj-fullscreen { overflow: hidden !important; }
        body.puantaj-fullscreen .vertical-menu,
        body.puantaj-fullscreen #page-topbar,
        body.puantaj-fullscreen .quick-favorites-bar { display: none !important; }

        body.puantaj-fullscreen #puantaj-full-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1050;
            background: #f1f5f9;
            padding: 10px;
            overflow: hidden;
        }

        body.puantaj-fullscreen .puantaj-table-wrapper {
            height: calc(100vh - 65px) !important;
            max-height: calc(100vh - 65px) !important;
        }

        .puantaj-save-fab { display: none; }
        body.puantaj-fullscreen .puantaj-save-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 2145;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 18px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
            transition: all 0.15s ease;
        }
        body.puantaj-fullscreen .puantaj-save-fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(79, 70, 229, 0.45);
            color: #ffffff;
        }

        /* -------------------------------------------------------------
         * DARK MODE TAM UYUM KURALLARI
         * ----------------------------------------------------------- */
        [data-bs-theme="dark"] #puantaj-full-container,
        [data-layout-mode="dark"] #puantaj-full-container,
        body.dark #puantaj-full-container {
            --pnt-border-light: #334155;
            --pnt-border-subtle: #1e293b;
            --pnt-surface-card: #1f2733;
            --pnt-surface-alt: #161b22;
            --pnt-text-main: #f8fafc;
            --pnt-text-muted: #94a3b8;
        }

        [data-bs-theme="dark"] .puantaj-table-header,
        [data-layout-mode="dark"] .puantaj-table-header,
        [data-bs-theme="dark"] .card-izin-turleri,
        [data-layout-mode="dark"] .card-izin-turleri,
        [data-bs-theme="dark"] .puantaj-grid-card,
        [data-layout-mode="dark"] .puantaj-grid-card {
            background: #1f2733 !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .puantaj-filter-cluster .form-control,
        [data-bs-theme="dark"] .puantaj-filter-cluster .form-control:focus,
        [data-bs-theme="dark"] .puantaj-filter-cluster .select2-selection,
        [data-bs-theme="dark"] .iskur-filter,
        [data-layout-mode="dark"] .puantaj-filter-cluster .form-control,
        [data-layout-mode="dark"] .puantaj-filter-cluster .select2-selection,
        [data-layout-mode="dark"] .iskur-filter {
            background-color: #161b22 !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .puantaj-filter-cluster .form-floating label,
        [data-layout-mode="dark"] .puantaj-filter-cluster .form-floating label {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .action-button-container,
        [data-layout-mode="dark"] .action-button-container {
            background: #161b22 !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .view-buttons,
        [data-layout-mode="dark"] .view-buttons {
            background: #161b22 !important;
        }

        [data-bs-theme="dark"] .view-buttons .nav-link,
        [data-layout-mode="dark"] .view-buttons .nav-link {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .view-buttons .nav-link.active,
        [data-layout-mode="dark"] .view-buttons .nav-link.active {
            background: #283342 !important;
            color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }

        [data-bs-theme="dark"] .puantaj-grid-summary,
        [data-layout-mode="dark"] .puantaj-grid-summary {
            background: #19202a !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .puantaj-stat-pill,
        [data-layout-mode="dark"] .puantaj-stat-pill {
            background: #161b22 !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .puantaj-focus-toggle,
        [data-layout-mode="dark"] .puantaj-focus-toggle {
            background: #161b22 !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .puantaj-table-wrapper,
        [data-layout-mode="dark"] .puantaj-table-wrapper,
        [data-bs-theme="dark"] .table-puantaj,
        [data-layout-mode="dark"] .table-puantaj {
            background: #161b22 !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead,
        [data-bs-theme="dark"] .table-puantaj thead tr,
        [data-layout-mode="dark"] .table-puantaj thead,
        [data-layout-mode="dark"] .table-puantaj thead tr {
            background: #12161c !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th:not(.sticky-col):not(.sticky-col-right-1),
        [data-layout-mode="dark"] .table-puantaj thead th:not(.sticky-col):not(.sticky-col-right-1) {
            background: #12161c !important;
            border-color: #2b3542 !important;
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th .day-name,
        [data-layout-mode="dark"] .table-puantaj thead th .day-name {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th .day-number,
        [data-layout-mode="dark"] .table-puantaj thead th .day-number {
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.is-sunday:not(.sticky-col),
        [data-layout-mode="dark"] .table-puantaj thead th.is-sunday:not(.sticky-col) {
            background: rgba(244, 63, 94, 0.22) !important;
            border-bottom: 2px solid #f43f5e !important;
            border-right-color: rgba(244, 63, 94, 0.25) !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.is-sunday .day-name,
        [data-bs-theme="dark"] .table-puantaj thead th.is-sunday .day-number,
        [data-layout-mode="dark"] .table-puantaj thead th.is-sunday .day-name,
        [data-layout-mode="dark"] .table-puantaj thead th.is-sunday .day-number {
            color: #fda4af !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.is-today:not(.sticky-col),
        [data-layout-mode="dark"] .table-puantaj thead th.is-today:not(.sticky-col) {
            background: rgba(99, 102, 241, 0.25) !important;
            border-bottom: 2px solid #818cf8 !important;
            border-right-color: rgba(99, 102, 241, 0.3) !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.is-today .day-name,
        [data-bs-theme="dark"] .table-puantaj thead th.is-today .day-number,
        [data-layout-mode="dark"] .table-puantaj thead th.is-today .day-name,
        [data-layout-mode="dark"] .table-puantaj thead th.is-today .day-number {
            color: #c7d2fe !important;
        }

        [data-bs-theme="dark"] .table-puantaj thead th.sticky-col,
        [data-bs-theme="dark"] .table-puantaj thead th.sticky-col-right-1,
        [data-layout-mode="dark"] .table-puantaj thead th.sticky-col,
        [data-layout-mode="dark"] .table-puantaj thead th.sticky-col-right-1 {
            background: #12161c !important;
            border-color: #2b3542 !important;
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tbody tr,
        [data-layout-mode="dark"] .table-puantaj tbody tr {
            background: #161b22 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tbody tr:hover,
        [data-layout-mode="dark"] .table-puantaj tbody tr:hover {
            background: #202732 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .personel-info,
        [data-layout-mode="dark"] .table-puantaj .personel-info {
            background: #1a2028 !important;
            border-color: #2b3542 !important;
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tbody tr:hover .personel-info,
        [data-layout-mode="dark"] .table-puantaj tbody tr:hover .personel-info {
            background: #232c38 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .text-truncate-name,
        [data-layout-mode="dark"] .table-puantaj .text-truncate-name {
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .personel-avatar-mini,
        [data-layout-mode="dark"] .table-puantaj .personel-avatar-mini {
            background: #2b3542 !important;
            color: #93c5fd !important;
        }

        [data-bs-theme="dark"] .table-puantaj .day-cell,
        [data-layout-mode="dark"] .table-puantaj .day-cell {
            background: #1a2028 !important;
            border-color: #2b3542 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tbody tr:hover .day-cell,
        [data-layout-mode="dark"] .table-puantaj tbody tr:hover .day-cell {
            background: #202732 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .day-cell.is-sunday,
        [data-layout-mode="dark"] .table-puantaj .day-cell.is-sunday {
            background-color: rgba(244, 63, 94, 0.08) !important;
            border-color: rgba(244, 63, 94, 0.2) !important;
        }

        [data-bs-theme="dark"] .table-puantaj .day-cell.is-today,
        [data-layout-mode="dark"] .table-puantaj .day-cell.is-today {
            background-color: rgba(99, 102, 241, 0.12) !important;
            border-color: rgba(99, 102, 241, 0.25) !important;
        }

        [data-bs-theme="dark"] .table-puantaj .day-cell.disabled,
        [data-layout-mode="dark"] .table-puantaj .day-cell.disabled {
            background: #12161c !important;
            opacity: 0.35;
        }

        [data-bs-theme="dark"] .table-puantaj .sticky-col-right-1,
        [data-layout-mode="dark"] .table-puantaj .sticky-col-right-1 {
            background: #1a2028 !important;
            border-color: #2b3542 !important;
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj .toplam-calisma-gunu,
        [data-layout-mode="dark"] .table-puantaj .toplam-calisma-gunu {
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tfoot,
        [data-bs-theme="dark"] .table-puantaj tfoot tr,
        [data-bs-theme="dark"] .table-puantaj tfoot td,
        [data-layout-mode="dark"] .table-puantaj tfoot,
        [data-layout-mode="dark"] .table-puantaj tfoot tr,
        [data-layout-mode="dark"] .table-puantaj tfoot td {
            background: #12161c !important;
            border-color: #2b3542 !important;
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .table-puantaj tfoot td.is-sunday,
        [data-layout-mode="dark"] .table-puantaj tfoot td.is-sunday {
            background: rgba(244, 63, 94, 0.15) !important;
            color: #fda4af !important;
        }

        /* Dark Mode İzin Çipleri Renk Tanımları */
        [data-bs-theme="dark"] .cell-content[data-shortcode="X"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="x"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="X"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="x"],
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="X"] .izin-box,
        [data-layout-mode="dark"] .izin-item-container[data-shortcode="X"] .izin-box {
            background-color: rgba(59, 130, 246, 0.22) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.4) !important;
        }

        [data-bs-theme="dark"] .cell-content[data-shortcode="HT"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="ht"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="HT"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="ht"],
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="HT"] .izin-box,
        [data-layout-mode="dark"] .izin-item-container[data-shortcode="HT"] .izin-box {
            background-color: rgba(245, 158, 11, 0.22) !important;
            color: #fcd34d !important;
            border: 1px solid rgba(245, 158, 11, 0.4) !important;
        }

        [data-bs-theme="dark"] .cell-content[data-shortcode="RP"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="D"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="ÜZ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="RP"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="D"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="ÜZ"],
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="RP"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="D"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="ÜZ"] .izin-box {
            background-color: rgba(244, 63, 94, 0.22) !important;
            color: #fda4af !important;
            border: 1px solid rgba(244, 63, 94, 0.4) !important;
        }

        [data-bs-theme="dark"] .cell-content[data-shortcode="Yİ"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="Üİ"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="RTÇ"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="HTÇ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="Yİ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="Üİ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="RTÇ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="HTÇ"],
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="Yİ"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="Üİ"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="RTÇ"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="HTÇ"] .izin-box {
            background-color: rgba(16, 185, 129, 0.22) !important;
            color: #6ee7b7 !important;
            border: 1px solid rgba(16, 185, 129, 0.4) !important;
        }

        [data-bs-theme="dark"] .cell-content[data-shortcode="Mİ"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="Bİ"],
        [data-bs-theme="dark"] .cell-content[data-shortcode="Eİ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="Mİ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="Bİ"],
        [data-layout-mode="dark"] .cell-content[data-shortcode="Eİ"],
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="Mİ"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="Bİ"] .izin-box,
        [data-bs-theme="dark"] .izin-item-container[data-shortcode="Eİ"] .izin-box {
            background-color: rgba(168, 85, 247, 0.22) !important;
            color: #d8b4fe !important;
            border: 1px solid rgba(168, 85, 247, 0.4) !important;
        }

        [data-bs-theme="dark"] .custom-context-menu,
        [data-layout-mode="dark"] .custom-context-menu {
            background: #1f2733 !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5) !important;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-item,
        [data-layout-mode="dark"] .custom-context-menu .menu-item {
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .custom-context-menu .menu-item:hover,
        [data-layout-mode="dark"] .custom-context-menu .menu-item:hover {
            background: #2b3542 !important;
            color: #ffffff !important;
        }
    </style>

    <div id="puantaj-full-container">
        <div class="row">
            <!-- Üst Satır: Ay/Yıl ve Butonlar -->
            <div class="col-12 puantaj-focus-panel">
                <div class="card mb-2 puantaj-table-header">
                    <div
                        class="card-header d-flex flex-wrap justify-content-between align-items-center bg-transparent border-bottom gap-2">
                        <div class="d-flex align-items-center flex-wrap">
                            <div class="d-flex align-items-center flex-wrap gap-2 puantaj-filter-cluster">
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
                                <div style="width: 180px;">
                                    <?php echo Form::FormSelect2("select-departman", ["" => "Tüm Departmanlar"], "", "Departman", "briefcase", 'key', '', "form-control select2"); ?>
                                </div>
                                <div style="width: 180px;">
                                    <?php echo Form::FormSelect2("select-bolge", ["" => "Tüm Bölgeler"], "", "Bölge", "map-pin", 'key', '', "form-control select2"); ?>
                                </div>
                                <div class="d-flex align-items-center iskur-filter">
                                    <div class="form-check form-switch form-switch-md mb-0">
                                        <input class="form-check-input" type="checkbox" id="check-iskur-dahil" checked>
                                        <label class="form-check-label fw-semibold text-muted ms-1" style="font-size: 12px; cursor: pointer;" for="check-iskur-dahil">İŞKUR Dahil</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Taraf: Arama ve Aksiyon Butonları -->
                        <div class="mt-lg-0 mt-2 ms-auto">
                            <div class="d-flex align-items-center justify-content-end gap-2">

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
                                                        <i class="mdi mdi-check-circle text-success me-2"></i> İşlenecek
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
            <div class="col-12 puantaj-focus-panel puantaj-focus-panel--palette">
                <div class="card mb-2 card-izin-turleri border-0 shadow-sm" id="izin-turleri-palette">
                    <div class="card-body p-2">
                        <div class="izin-palette-header">
                            <div>
                                <span class="izin-palette-title">Puantaj türleri</span>
                                <span class="izin-palette-helper">Bir tür seçin, ardından takvim hücresine tıklayın</span>
                            </div>
                            <div class="view-buttons nav" role="tablist">
                                <a class="nav-link active" data-bs-toggle="tab" href="#ucretli-izinler" role="tab">
                                    Ücretli
                                </a>
                                <a class="nav-link" data-bs-toggle="tab" href="#ucretsiz-izinler" role="tab">
                                    Ücretsiz
                                </a>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-light izin-palette-restore" id="izin-palette-restore" title="Paleti tabloya sabitle" aria-label="Paleti tabloya sabitle">
                                    <i class="mdi mdi-pin-outline"></i>
                                </button>
                                <button type="button" class="izin-palette-handle" id="izin-palette-handle" title="Puantaj türlerini sürükleyerek yüzen palete dönüştür" aria-label="Puantaj türlerini sürükle">
                                    <i class="bx bx-grid-vertical fs-5"></i>
                                </button>
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
                <div class="card puantaj-grid-card">
                    <div class="card-body p-0 position-relative">
                        <div class="puantaj-grid-summary">
                            <div>
                                <div class="puantaj-grid-title"><i class="mdi mdi-calendar-edit-outline me-1"></i> Aylık çalışma planı</div>
                                <div class="puantaj-grid-subtitle" id="puantaj-period-label">Dönem verileri hazırlanıyor</div>
                            </div>
                            <div class="puantaj-live-stats">
                                <span class="puantaj-stat-pill"><i class="mdi mdi-account-group-outline"></i><span id="puantaj-visible-count">0 personel</span></span>
                                <span class="puantaj-stat-pill" id="puantaj-change-pill"><i class="mdi mdi-check-circle-outline"></i><span id="puantaj-change-count">Tüm değişiklikler kayıtlı</span></span>
                                <button type="button" class="puantaj-focus-toggle" id="btn-puantaj-focus" title="Üst alanları gizle" aria-label="Üst alanları gizle" aria-expanded="true">
                                    <i class="mdi mdi-chevron-up"></i>
                                </button>
                            </div>
                        </div>
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

<!-- Tam ekranda üst araç çubuğu yerine sabit hızlı kaydetme eylemi -->
<button type="button" class="puantaj-save-fab" id="btn-save-floating" aria-label="Puantaj değişikliklerini kaydet">
    <i class="mdi mdi-content-save-outline fs-5"></i>
    <span>Kaydet</span>
</button>

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
<div class="modal fade" id="sgkRaporModal" tabindex="-1" aria-labelledby="sgkRaporModalLabel" aria-hidden="true" data-modal-icon="mdi mdi-hospital-building" data-modal-subtitle="SGK Vizite Servisinden çekilen raporlar.">
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
<script src="views/personel/js/puantaj_izin.js?v=<?= filemtime(__DIR__ . '/js/puantaj_izin.js') ?>"></script>
