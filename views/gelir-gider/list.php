<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\GelirGiderModel;
use App\Helper\Financial;
use App\Model\TanimlamalarModel;

$GelirGider = new GelirGiderModel();
$Financial = new Financial();
$Tanimlama = new TanimlamalarModel();

/* -------- Filtre seçenekleri -------- */
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedAy  = $_GET['ay'] ?? '';
$selectedTip = $_GET['tip'] ?? '';

$yilSecenekleri = [
    '' => 'Tüm Yıllar'
];
for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--) {
    $yilSecenekleri[$y] = (string) $y;
}

$aySecenekleri = [
    ''   => 'Tüm Aylar',
    '1'  => 'Ocak',   '2'  => 'Şubat',  '3'  => 'Mart',
    '4'  => 'Nisan',  '5'  => 'Mayıs',  '6'  => 'Haziran',
    '7'  => 'Temmuz', '8'  => 'Ağustos','9'  => 'Eylül',
    '10' => 'Ekim',   '11' => 'Kasım',  '12' => 'Aralık',
];

$summary = $GelirGider->summary(['yil' => $selectedYil, 'ay' => $selectedAy, 'tip' => $selectedTip]);
?>
<script>try { document.documentElement.classList.toggle('gelir-gider-summary-hidden', localStorage.getItem('gelir_gider_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.gelir-gider-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

/* Modern Tablo Tipografi */
#gelirGiderTable {
    font-size: 13px !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
#gelirGiderTable thead th,
#gelirGiderTable thead tr:first-child > th,
.table-responsive #gelirGiderTable thead tr:first-child > th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    box-shadow: none !important;
    vertical-align: middle !important;
    position: relative !important;
    border-bottom: 1px solid #e2e8f0 !important;
    cursor: pointer !important;
}
#gelirGiderTable thead tr:first-child > th.dt-draggable-header {
    cursor: pointer !important;
}
#gelirGiderTable thead tr:first-child > th.dt-draggable-header:active {
    cursor: grabbing !important;
}

/* Sıralama kapalı kolonlar */
#gelirGiderTable thead tr:first-child > th.sorting_disabled,
#gelirGiderTable thead tr:first-child > th.no-sort,
#gelirGiderTable thead tr:first-child > th.dt-actions-header {
    cursor: default !important;
    background-image: none !important;
    padding-left: 8px !important;
}

/* Sıralama İkonları (Sol Kenar - Vektörel SVG) */
#gelirGiderTable thead tr:first-child > th.sorting,
.table-responsive #gelirGiderTable thead tr:first-child > th.sorting {
    cursor: pointer !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23334155' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
    padding-right: 34px !important;
}

#gelirGiderTable thead tr:first-child > th.sorting:hover,
.table-responsive #gelirGiderTable thead tr:first-child > th.sorting:hover {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
}

#gelirGiderTable thead tr:first-child > th.sorting_asc,
.table-responsive #gelirGiderTable thead tr:first-child > th.sorting_asc {
    cursor: pointer !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%232563eb' stroke-width='2.8'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%2394a3b8' stroke-width='1.8' opacity='0.4'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
    padding-right: 34px !important;
}

#gelirGiderTable thead tr:first-child > th.sorting_desc,
.table-responsive #gelirGiderTable thead tr:first-child > th.sorting_desc {
    cursor: pointer !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%2394a3b8' stroke-width='1.8' opacity='0.4'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%232563eb' stroke-width='2.8'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
    padding-right: 34px !important;
}

#gelirGiderTable tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}

#gelirGiderTable tbody tr:last-child td {
    border-bottom: 1px solid #e2e8f0 !important;
}

.table-responsive {
    border-bottom: 1px solid #e2e8f0 !important;
}

/* Şık Checkbox Stili */
.custom-table-check,
.row-check,
#checkAll {
    width: 18px !important;
    height: 18px !important;
    border-radius: 5px !important;
    border: 1.5px solid #94a3b8 !important;
    background-color: #fff !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
    margin: 0 auto !important;
    vertical-align: middle !important;
}
.custom-table-check:checked,
.row-check:checked,
#checkAll:checked {
    background-color: #3b82f6 !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3) !important;
}
.custom-table-check:focus,
.row-check:focus,
#checkAll:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}

/* Tablo Butonları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}
.top-action-btn {
    height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
}
.top-icon-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 18px;
}
.summary-kpi-card {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    cursor: pointer;
}
.summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.07);
    border-color: #3b82f6 !important;
}
.summary-kpi-card.active {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
}
.summary-kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.summary-kpi-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #64748b;
    text-transform: uppercase;
}
.summary-kpi-value {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}
.summary-kpi-subtext {
    font-size: 11.5px;
    color: #64748b;
}
.summary-pill-btn {
    font-size: 11px;
    font-weight: 600;
}

/* Modal Özel Styling (Kusursuz ve Temiz Tasarım) */
#gelirGiderModal .modal-content, #importExcelModal .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}
#gelirGiderModal .modal-header, #importExcelModal .modal-header {
    background: #f8fafc;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    padding: 1.25rem 1.5rem;
}
.modal-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.form-selectgroup-label {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s ease;
    background: white;
    cursor: pointer;
}
.form-selectgroup-input:checked + .form-selectgroup-label {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
}

/* Modal Form Alanları İyileştirmesi */
#gelirGiderModal .modal-custom-control {
    height: 40px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
}
#gelirGiderModal .modal-custom-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Select2 Modal Özel Düzeltmesi (Çift Ok ve Bozulmaları Önler) */
#gelirGiderModal .select2-container--default .select2-selection--single {
    height: 40px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    background-color: #fff !important;
}
#gelirGiderModal .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px !important;
    padding-left: 12px !important;
    padding-right: 32px !important;
    font-size: 13px !important;
    color: #1e293b !important;
}
#gelirGiderModal .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
    right: 8px !important;
}
#gelirGiderModal .select2-container--default.select2-container--focus .select2-selection--single,
#gelirGiderModal .select2-container--default.select2-container--open .select2-selection--single {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}

/* Header FormSelect2 Uyumu */
.header-filter-wrapper {
    min-width: 140px;
}
.header-filter-wrapper .form-floating {
    margin-bottom: 0 !important;
}
.header-filter-wrapper .form-floating > .select2-container--default .select2-selection--single {
    height: 38px !important;
    padding-top: 14px !important;
    border-radius: 8px !important;
}
.header-filter-wrapper .form-floating > label {
    padding: 6px 10px !important;
    font-size: 11px !important;
}

/* macOS Koyu Tema & Genel Dark Tema Uyumu */
[data-bs-theme="dark"] .summary-kpi-card,
html[data-theme-preset="macos-dark"] .summary-kpi-card {
    background: rgba(28, 30, 38, 0.72) !important;
    border-color: rgba(255, 255, 255, 0.09) !important;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45) !important;
}
[data-bs-theme="dark"] .summary-kpi-card:hover,
html[data-theme-preset="macos-dark"] .summary-kpi-card:hover {
    border-color: #3b82f6 !important;
}
[data-bs-theme="dark"] .summary-kpi-value,
html[data-theme-preset="macos-dark"] .summary-kpi-value {
    color: #f3f4f8 !important;
}
[data-bs-theme="dark"] .summary-kpi-label,
html[data-theme-preset="macos-dark"] .summary-kpi-label {
    color: rgba(200, 205, 218, 0.65) !important;
}
[data-bs-theme="dark"] .summary-kpi-subtext,
html[data-theme-preset="macos-dark"] .summary-kpi-subtext {
    color: rgba(200, 205, 218, 0.6) !important;
}
[data-bs-theme="dark"] h4.text-dark,
[data-bs-theme="dark"] h5.text-dark,
html[data-theme-preset="macos-dark"] h4.text-dark,
html[data-theme-preset="macos-dark"] h5.text-dark {
    color: rgba(240, 242, 246, 0.95) !important;
}

/* Üst Araç Çubuğu Butonları */
[data-bs-theme="dark"] .top-action-btn.bg-white,
[data-bs-theme="dark"] .top-icon-btn.bg-white,
html[data-theme-preset="macos-dark"] .top-action-btn.bg-white,
html[data-theme-preset="macos-dark"] .top-icon-btn.bg-white {
    background-color: rgba(35, 36, 42, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: rgba(235, 238, 245, 0.88) !important;
    box-shadow: none !important;
}

/* Header Filtre Select2 (Yıl & Ay) */
[data-bs-theme="dark"] .header-filter-wrapper .form-floating > .select2-container--default .select2-selection--single,
html[data-theme-preset="macos-dark"] .header-filter-wrapper .form-floating > .select2-container--default .select2-selection--single {
    background-color: rgba(28, 30, 38, 0.85) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-bs-theme="dark"] .header-filter-wrapper .form-floating > .select2-container--default .select2-selection--single .select2-selection__rendered,
html[data-theme-preset="macos-dark"] .header-filter-wrapper .form-floating > .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: rgba(235, 238, 245, 0.88) !important;
}
[data-bs-theme="dark"] .header-filter-wrapper .form-floating > label,
html[data-theme-preset="macos-dark"] .header-filter-wrapper .form-floating > label {
    color: rgba(200, 205, 218, 0.65) !important;
}

/* Tablo Başlığı (Thead) */
[data-bs-theme="dark"] #gelirGiderTable thead tr:first-child > th,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead tr:first-child > th,
html[data-theme-preset="macos-dark"] .table-responsive #gelirGiderTable thead tr:first-child > th {
    background-color: rgba(39, 40, 44, .96) !important;
    color: rgba(224, 225, 229, .88) !important;
    border-color: rgba(255, 255, 255, .08) !important;
}

/* Tablo Filtre Satırı (dt-filter-row) */
[data-bs-theme="dark"] #gelirGiderTable thead .dt-filter-row th,
[data-bs-theme="dark"] .dt-filter-row > th,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead .dt-filter-row th,
html[data-theme-preset="macos-dark"] .dt-filter-row > th,
html[data-theme-preset="macos-dark"] .table-responsive #gelirGiderTable thead .dt-filter-row th {
    background-color: rgba(30, 32, 38, .98) !important;
    background-image: none !important;
    color: rgba(224, 225, 229, .72) !important;
    border-color: rgba(255, 255, 255, .08) !important;
}

/* Filtre Inputları */
[data-bs-theme="dark"] #gelirGiderTable thead .dt-filter-row input,
[data-bs-theme="dark"] #gelirGiderTable thead .dt-filter-row select,
[data-bs-theme="dark"] .dt-filter-control,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead .dt-filter-row input,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead .dt-filter-row select,
html[data-theme-preset="macos-dark"] .dt-filter-control {
    background-color: rgba(20, 22, 26, .94) !important;
    color: rgba(225, 226, 230, .85) !important;
    border-color: rgba(255, 255, 255, .13) !important;
}
[data-bs-theme="dark"] #gelirGiderTable thead .dt-filter-row input::placeholder,
[data-bs-theme="dark"] .dt-filter-control::placeholder,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead .dt-filter-row input::placeholder,
html[data-theme-preset="macos-dark"] .dt-filter-control::placeholder {
    color: rgba(202, 209, 222, .42) !important;
}
[data-bs-theme="dark"] .dt-filter-mode-trigger,
html[data-theme-preset="macos-dark"] .dt-filter-mode-trigger {
    background-color: rgba(35, 37, 43, 0.9) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: rgba(200, 205, 218, 0.65) !important;
}
[data-bs-theme="dark"] .dt-filter-mode-trigger:hover,
html[data-theme-preset="macos-dark"] .dt-filter-mode-trigger:hover {
    background-color: rgba(50, 53, 62, 0.9) !important;
    color: #fff !important;
}

/* Filtre Açılır Menüleri (Funnel & Excel) */
[data-bs-theme="dark"] .dt-filter-mode-dropdown,
[data-bs-theme="dark"] .dt-filter-excel-dropdown,
html[data-theme-preset="macos-dark"] .dt-filter-mode-dropdown,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown {
    background-color: #1e2028 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #f1f5f9 !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6) !important;
}
[data-bs-theme="dark"] .dt-filter-mode-dropdown .mode-opt,
[data-bs-theme="dark"] .dt-filter-excel-dropdown .option-item,
html[data-theme-preset="macos-dark"] .dt-filter-mode-dropdown .mode-opt,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown .option-item {
    color: rgba(230, 235, 245, 0.85) !important;
}
[data-bs-theme="dark"] .dt-filter-mode-dropdown .mode-opt:hover,
[data-bs-theme="dark"] .dt-filter-excel-dropdown .option-item:hover,
html[data-theme-preset="macos-dark"] .dt-filter-mode-dropdown .mode-opt:hover,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown .option-item:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: #38bdf8 !important;
}
[data-bs-theme="dark"] .dt-filter-excel-dropdown .search-box input,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown .search-box input {
    background-color: #15171d !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #f1f5f9 !important;
}
[data-bs-theme="dark"] .dt-filter-excel-dropdown .search-box,
[data-bs-theme="dark"] .dt-filter-excel-dropdown .dt-filter-footer,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown .search-box,
html[data-theme-preset="macos-dark"] .dt-filter-excel-dropdown .dt-filter-footer {
    border-color: rgba(255, 255, 255, 0.08) !important;
    background-color: #1a1c23 !important;
}

/* Tablo Gövdesi (Tbody Tr/Td) */
[data-bs-theme="dark"] #gelirGiderTable,
[data-bs-theme="dark"] #gelirGiderTable tbody tr:last-child td,
[data-bs-theme="dark"] .table-responsive,
html[data-theme-preset="macos-dark"] #gelirGiderTable,
html[data-theme-preset="macos-dark"] #gelirGiderTable tbody tr:last-child td,
html[data-theme-preset="macos-dark"] .table-responsive {
    border-color: rgba(255, 255, 255, .08) !important;
}
[data-bs-theme="dark"] #gelirGiderTable tbody td,
html[data-theme-preset="macos-dark"] #gelirGiderTable tbody td {
    background-color: rgba(30, 32, 38, .85) !important;
    color: rgba(232, 233, 236, .85) !important;
    border-color: rgba(255, 255, 255, .08) !important;
}
[data-bs-theme="dark"] #gelirGiderTable tbody tr:hover td,
html[data-theme-preset="macos-dark"] #gelirGiderTable tbody tr:hover td {
    background-color: rgba(255, 255, 255, .05) !important;
}
[data-bs-theme="dark"] #gelirGiderTable tbody tr.selected td,
html[data-theme-preset="macos-dark"] #gelirGiderTable tbody tr.selected td {
    background-color: rgba(59, 130, 246, .18) !important;
}

/* Checkbox Dark Mode */
[data-bs-theme="dark"] .custom-table-check,
[data-bs-theme="dark"] .row-check,
[data-bs-theme="dark"] #checkAll,
html[data-theme-preset="macos-dark"] .custom-table-check,
html[data-theme-preset="macos-dark"] .row-check,
html[data-theme-preset="macos-dark"] #checkAll {
    background-color: rgba(20, 22, 26, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.25) !important;
}

/* DataTables Pagination & Info */
[data-bs-theme="dark"] .dataTables_info,
html[data-theme-preset="macos-dark"] .dataTables_info {
    color: rgba(200, 205, 218, 0.65) !important;
}
[data-bs-theme="dark"] .dataTables_length select,
html[data-theme-preset="macos-dark"] .dataTables_length select {
    background-color: rgba(28, 30, 38, 0.85) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: rgba(235, 238, 245, 0.88) !important;
}
[data-bs-theme="dark"] .dataTables_paginate .pagination .page-item .page-link,
html[data-theme-preset="macos-dark"] .dataTables_paginate .pagination .page-item .page-link {
    background-color: rgba(255, 255, 255, 0.06) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: rgba(232, 235, 242, 0.8) !important;
}
[data-bs-theme="dark"] .dataTables_paginate .pagination .page-item.active .page-link,
html[data-theme-preset="macos-dark"] .dataTables_paginate .pagination .page-item.active .page-link {
    background-color: #3b82f6 !important;
    border-color: #3b82f6 !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .dataTables_paginate .pagination .page-item.disabled .page-link,
html[data-theme-preset="macos-dark"] .dataTables_paginate .pagination .page-item.disabled .page-link {
    background-color: rgba(255, 255, 255, 0.02) !important;
    border-color: rgba(255, 255, 255, 0.05) !important;
    color: rgba(232, 235, 242, 0.3) !important;
}

/* Subtle Butonlar */
[data-bs-theme="dark"] .btn-subtle-primary,
html[data-theme-preset="macos-dark"] .btn-subtle-primary {
    background-color: rgba(59, 130, 246, 0.15) !important;
    color: #60a5fa !important;
    border: 1px solid rgba(59, 130, 246, 0.25) !important;
}
[data-bs-theme="dark"] .btn-subtle-success,
html[data-theme-preset="macos-dark"] .btn-subtle-success {
    background-color: rgba(34, 197, 94, 0.15) !important;
    color: #4ade80 !important;
    border: 1px solid rgba(34, 197, 94, 0.25) !important;
}
[data-bs-theme="dark"] .btn-subtle-danger,
html[data-theme-preset="macos-dark"] .btn-subtle-danger {
    background-color: rgba(239, 68, 68, 0.15) !important;
    color: #f87171 !important;
    border: 1px solid rgba(239, 68, 68, 0.25) !important;
}
[data-bs-theme="dark"] .btn-subtle-warning,
html[data-theme-preset="macos-dark"] .btn-subtle-warning {
    background-color: rgba(245, 158, 11, 0.15) !important;
    color: #fbbf24 !important;
    border: 1px solid rgba(245, 158, 11, 0.25) !important;
}
[data-bs-theme="dark"] .btn-subtle-secondary,
html[data-theme-preset="macos-dark"] .btn-subtle-secondary {
    background-color: rgba(148, 163, 184, 0.15) !important;
    color: #cbd5e1 !important;
    border: 1px solid rgba(148, 163, 184, 0.25) !important;
}

/* Dropdown Menüler */
[data-bs-theme="dark"] .dropdown-menu,
html[data-theme-preset="macos-dark"] .dropdown-menu {
    background-color: #1e2028 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #f1f5f9 !important;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5) !important;
}
[data-bs-theme="dark"] .dropdown-item,
html[data-theme-preset="macos-dark"] .dropdown-item {
    color: rgba(235, 238, 245, 0.85) !important;
}
[data-bs-theme="dark"] .dropdown-item:hover,
html[data-theme-preset="macos-dark"] .dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .dropdown-divider,
html[data-theme-preset="macos-dark"] .dropdown-divider {
    border-color: rgba(255, 255, 255, 0.08) !important;
}

/* Modal Stilleri */
[data-bs-theme="dark"] #gelirGiderModal .modal-content,
[data-bs-theme="dark"] #importExcelModal .modal-content,
html[data-theme-preset="macos-dark"] #gelirGiderModal .modal-content,
html[data-theme-preset="macos-dark"] #importExcelModal .modal-content {
    background-color: #181a20 !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important;
}
[data-bs-theme="dark"] #gelirGiderModal .modal-header,
[data-bs-theme="dark"] #importExcelModal .modal-header,
html[data-theme-preset="macos-dark"] #gelirGiderModal .modal-header,
html[data-theme-preset="macos-dark"] #importExcelModal .modal-header {
    background: #131418 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] #gelirGiderModal .modal-footer,
[data-bs-theme="dark"] #importExcelModal .modal-footer,
html[data-theme-preset="macos-dark"] #gelirGiderModal .modal-footer,
html[data-theme-preset="macos-dark"] #importExcelModal .modal-footer {
    background: #131418 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] .form-selectgroup-label,
html[data-theme-preset="macos-dark"] .form-selectgroup-label {
    background: #21232b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #f1f5f9 !important;
}
[data-bs-theme="dark"] .form-selectgroup-input:checked + .form-selectgroup-label,
html[data-theme-preset="macos-dark"] .form-selectgroup-input:checked + .form-selectgroup-label {
    background: rgba(59, 130, 246, 0.2) !important;
    border-color: #3b82f6 !important;
}
[data-bs-theme="dark"] #gelirGiderModal .modal-custom-control,
[data-bs-theme="dark"] #importExcelModal .form-control,
html[data-theme-preset="macos-dark"] #gelirGiderModal .modal-custom-control,
html[data-theme-preset="macos-dark"] #importExcelModal .form-control {
    background-color: #21232b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #f1f5f9 !important;
}
[data-bs-theme="dark"] #gelirGiderModal .select2-container--default .select2-selection--single,
html[data-theme-preset="macos-dark"] #gelirGiderModal .select2-container--default .select2-selection--single {
    background-color: #21232b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-bs-theme="dark"] #gelirGiderModal .select2-container--default .select2-selection--single .select2-selection__rendered,
html[data-theme-preset="macos-dark"] #gelirGiderModal .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f1f5f9 !important;
}

/* Sıralama İkonları Dark */
[data-bs-theme="dark"] #gelirGiderTable thead tr:first-child > th.sorting,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead tr:first-child > th.sorting {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
}
[data-bs-theme="dark"] #gelirGiderTable thead tr:first-child > th.sorting:hover,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead tr:first-child > th.sorting:hover {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
}
[data-bs-theme="dark"] #gelirGiderTable thead tr:first-child > th.sorting_asc,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead tr:first-child > th.sorting_asc {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%2360a5fa' stroke-width='2.8'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%2364748b' stroke-width='1.8' opacity='0.4'/%3E%3C/svg%3E") !important;
}
[data-bs-theme="dark"] #gelirGiderTable thead tr:first-child > th.sorting_desc,
html[data-theme-preset="macos-dark"] #gelirGiderTable thead tr:first-child > th.sorting_desc {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%2364748b' stroke-width='1.8' opacity='0.4'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%2360a5fa' stroke-width='2.8'/%3E%3C/svg%3E") !important;
}
</style>

<?php 
$maintitle = "Finans";
$title = "Gelir - Gider Yönetimi";
include 'layouts/breadcrumb.php'; 
?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-transfer-alt fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Gelir - Gider Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Tüm gelir ve gider kayıtları, hesap hareketleri ve finansal bakiye durumu</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- Toplu Silme Butonu (Seçim yapıldığında görünür) -->
            <button type="button" class="btn btn-danger top-action-btn shadow-sm text-white d-none" id="btnBulkDelete">
                <i class="bx bx-trash font-size-16"></i> <span id="bulkDeleteText">Seçilenleri Sil (0)</span>
            </button>

            <!-- 1. Yeni Gelir/Gider Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="gelirGiderEkle" data-bs-toggle="modal" data-bs-target="#gelirGiderModal">
                <i class="bx bx-plus font-size-16"></i> Yeni İşlem
            </button>

            <!-- Sütunlar Dropdown (ColVis & Reorder) -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnColumnToggle">
                    <i class="bx bx-columns font-size-16 text-primary"></i> Sütunlar
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="min-width: 230px; max-height: 380px; overflow-y: auto;" id="columnListDropdown">
                    <div class="d-flex align-items-center justify-content-between px-2 py-1 mb-1 border-bottom">
                        <span class="fw-bold font-size-12 text-muted">SÜTUN GÖRÜNÜRLÜĞÜ</span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-primary font-size-11" id="btnResetColumns">Sıfırla</button>
                    </div>
                    <div id="columnListContainer"></div>
                </div>
            </div>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                        <i class="bx bx-cloud-upload me-2 font-size-16 text-success"></i> Excel'den Yükle
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="views/gelir-gider/excel-sablon.php">
                        <i class="bx bx-download me-2 text-info font-size-16"></i> Excel Şablonu İndir
                    </a>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM İŞLEM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0 active" data-tip="" id="cardSummaryAll">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM İŞLEM</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-transfer"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_islem"><?= $summary->toplam_islem ?? 0 ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_gelir_gider">Gelir: <?= $summary->gelir_adet ?? 0 ?> | Gider: <?= $summary->gider_adet ?? 0 ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-tip="">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TOPLAM GELİR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0" data-tip="1" id="cardSummaryGelir">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM GELİR</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-trending-up"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="card_toplam_gelir"><?= Helper::formattedMoney($summary->toplam_gelir ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_gelir_adet"><?= $summary->gelir_adet ?? 0 ?> Gelir Kaydı</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tip="1">
                            <i class="bx bx-check-circle"></i> Gelirler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: TOPLAM GİDER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0" data-tip="2" id="cardSummaryGider">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM GİDER</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-trending-down"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="card_toplam_gider"><?= Helper::formattedMoney($summary->toplam_gider ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_gider_adet"><?= $summary->gider_adet ?? 0 ?> Gider Kaydı</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tip="2">
                            <i class="bx bx-minus-circle"></i> Giderler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: NET BAKİYE -->
        <?php 
        $bakiyeVal = (float)($summary->bakiye ?? 0); 
        $bakiyeColor = $bakiyeVal < 0 ? 'text-danger' : ($bakiyeVal > 0 ? 'text-success' : 'text-dark');
        $bakiyeBadge = $bakiyeVal < 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : ($bakiyeVal > 0 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-secondary-subtle text-secondary border-secondary-subtle');
        $bakiyeBadgeText = $bakiyeVal < 0 ? 'Borç / Açık' : ($bakiyeVal > 0 ? 'Kasa Fazlası' : 'Dengede');
        ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0" id="cardSummaryBakiye">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">NET BAKİYE</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 <?= $bakiyeColor ?>" id="card_net_bakiye"><?= Helper::formattedMoney($bakiyeVal) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bakiye_durum_metni">Gelir - Gider Farkı</span>
                        <span class="badge <?= $bakiyeBadge ?> border rounded-pill px-2 py-1 font-size-11 fw-semibold" id="bakiye_bilgi">
                            <?= $bakiyeBadgeText ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Gelir-Gider Listesi Kartı -->
    <div class="card summary-kpi-card mb-3 table-card-container" id="gelirGiderListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Gelir & Gider Hareketleri</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, sütun filtreleme, sürükle-bırak sıralama ve işlem geçmişi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu: Form::FormSelect2 Yıl / Ay Filtreleri & Dışa Aktarma -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Yıl Dropdown (Form::FormSelect2) -->
                <div class="header-filter-wrapper">
                    <?php 
                    echo Form::FormSelect2(
                        name: "filter_yil",
                        options: $yilSecenekleri,
                        selectedValue: $selectedYil,
                        label: "Yıl",
                        icon: "calendar",
                        valueField: "key",
                        textField: "",
                        class: "form-select select2",
                        required: false,
                        style: "width:100%",
                        attributes: "",
                        id: "filterYil"
                    ); 
                    ?>
                </div>

                <!-- Ay Dropdown (Form::FormSelect2) -->
                <div class="header-filter-wrapper">
                    <?php 
                    echo Form::FormSelect2(
                        name: "filter_ay",
                        options: $aySecenekleri,
                        selectedValue: $selectedAy,
                        label: "Ay",
                        icon: "calendar",
                        valueField: "key",
                        textField: "",
                        class: "form-select select2",
                        required: false,
                        style: "width:100%",
                        attributes: "",
                        id: "filterAy"
                    ); 
                    ?>
                </div>

                <!-- Excel Export -->
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>

                <!-- Print -->
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <div id="dtDropzoneOverlay" class="dt-hide-dropzone-overlay">
                    <i class="bx bx-trash"></i>
                    <span>Sütunu Gizlemek İçin Buraya Bırakın</span>
                </div>
                <table id="gelirGiderTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="none" style="width: 35px;" class="text-center sorting_disabled no-drag">
                                <input class="form-check-input custom-table-check" type="checkbox" id="checkAll" title="Tümünü Seç">
                            </th>
                            <th data-filter="number" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="date" class="text-center" style="width: 125px;">KAYIT TARİHİ</th>
                            <th data-filter="select" class="text-center" style="width: 90px;">TÜR</th>
                            <th data-filter="string">HESAP ADI</th>
                            <th data-filter="select">KATEGORİ</th>
                            <th data-filter="select" class="text-center" style="width: 110px;">PLAKA</th>
                            <th data-filter="select" class="text-center" style="width: 120px;">ÖDEME ŞEKLİ</th>
                            <th data-filter="select" class="text-center" style="width: 120px;">BANKA</th>
                            <th data-filter="date" class="text-center" style="width: 125px;">İŞLEM TARİHİ</th>
                            <th data-filter="number" class="text-end" style="width: 120px;">TUTAR</th>
                            <th data-filter="number" class="text-end" style="width: 120px;">BAKİYE</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="none" style="width: 85px;" class="text-center sorting_disabled no-drag">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Gelir / Gider Ekle - Düzenle Modal -->
<div class="modal fade" id="gelirGiderModal" tabindex="-1" aria-labelledby="gelirGiderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <div class="modal-icon-box bg-primary-subtle text-primary me-3 flex-shrink-0">
                    <i class="bx bx-transfer-alt font-size-22"></i>
                </div>
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="gelirGiderModalLabel">Gelir / Gider İşlemi</h5>
                    <small class="text-muted">Lütfen işlem detaylarını eksiksiz doldurunuz.</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="gelirGiderForm">
                    <input type="hidden" name="gelir_gider_id" id="gelir_gider_id" value="0">

                    <!-- 1. İşlem Türü (Gelir / Gider Seçim Kutuları) -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-selectgroup-item w-100 mb-0">
                                <input type="radio" name="type" value="1" class="form-selectgroup-input d-none">
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <div class="p-2 bg-success-subtle text-success rounded-3 me-3">
                                        <i class="bx bx-trending-up font-size-22"></i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-bold text-dark font-size-14">Gelir</span>
                                        <span class="d-block text-muted font-size-12">Kasaya Giriş</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="form-selectgroup-item w-100 mb-0">
                                <input type="radio" name="type" value="2" class="form-selectgroup-input d-none" checked>
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <div class="p-2 bg-danger-subtle text-danger rounded-3 me-3">
                                        <i class="bx bx-trending-down font-size-22"></i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-bold text-dark font-size-14">Gider</span>
                                        <span class="d-block text-muted font-size-12">Kasadan Çıkış</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 2. Hesap Adı & Kategori -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Hesap Adı</label>
                            <select name="hesap_adi" id="hesap_adi" class="modal-select-field w-100" data-placeholder="Hesap Adı Seçiniz veya Yazınız">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Kategori / İşlem Türü <span class="text-danger">*</span></label>
                            <select name="islem_turu" id="islem_turu" class="modal-select-field w-100" data-placeholder="Kategori Seçiniz" required>
                                <option value=""></option>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Plaka & Ödeme Şekli -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Plaka (Araç)</label>
                            <select name="plaka" id="plaka" class="modal-select-field w-100" data-placeholder="Plaka Seçiniz veya Yazınız">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Ödeme Şekli</label>
                            <select name="odeme_sekli" id="odeme_sekli" class="modal-select-field w-100" data-placeholder="Ödeme Şekli Seçiniz">
                                <option value=""></option>
                                <option value="Nakit">Nakit</option>
                                <option value="Banka Havalesi / EFT / FAST">Banka Havalesi / EFT / FAST</option>
                                <option value="Kredi Kartı / Banka Kartı">Kredi Kartı / Banka Kartı</option>
                                <option value="Çek">Çek</option>
                                <option value="Senet">Senet</option>
                                <option value="Otomatik Ödeme">Otomatik Ödeme</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Banka Adı & İşlem Tarihi -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Banka Adı</label>
                            <select name="banka_adi" id="banka_adi" class="modal-select-field w-100" data-placeholder="Banka Seçiniz veya Yazınız">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">İşlem Tarihi <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-calendar text-muted"></i></span>
                                <input type="text" name="islem_tarihi" id="islem_tarihi" class="form-control flatpickr border-start-0 ps-0 modal-custom-control" value="<?= date('d.m.Y H:i') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Tutar -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label font-size-12 fw-semibold text-muted mb-1">Tutar (₺) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold text-primary px-3">₺</span>
                                <input type="text" name="tutar" id="tutar" class="form-control money border-start-0 ps-0 text-end fw-bold font-size-16 modal-custom-control" placeholder="0,00" required>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Açıklama -->
                    <div class="mb-0">
                        <label class="form-label font-size-12 fw-semibold text-muted mb-1">Açıklama</label>
                        <textarea name="aciklama" id="aciklama" class="form-control rounded-3" rows="3" placeholder="İşlem ile ilgili detaylı açıklama girebilirsiniz..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex align-items-center justify-content-between">
                <button type="button" id="yeniIslemModal" class="btn btn-outline-secondary btn-sm px-3 rounded-3 d-flex align-items-center gap-1">
                    <i class="bx bx-refresh font-size-15"></i> Formu Temizle
                </button>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3 rounded-3" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" id="gelirGiderKaydet" class="btn btn-primary btn-sm px-4 rounded-3 d-flex align-items-center gap-1 shadow-sm">
                        <i class="bx bx-save font-size-16"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Excel Import Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <div class="modal-icon-box bg-success-subtle text-success me-3 flex-shrink-0">
                    <i class="bx bx-cloud-upload font-size-22"></i>
                </div>
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="importExcelModalLabel">Excel'den Gelir-Gider Yükle</h5>
                    <small class="text-muted">Geçerli bir Excel dosyası seçiniz (.xlsx, .xls)</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-success bg-success-subtle border-0 mb-3 p-3 rounded-3">
                    <div class="d-flex align-items-start">
                        <div class="p-2 bg-success text-white rounded-circle me-3 flex-shrink-0">
                            <i class="bx bx-download font-size-18"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold text-success font-size-13">Şablon Dosyasını İndirin</h6>
                            <p class="mb-2 font-size-12 text-muted">İşlemleri doğru aktarmak için hazır şablon dosyasını kullanınız.</p>
                            <a href="views/gelir-gider/excel-sablon.php" class="btn btn-sm btn-success rounded-pill px-3 py-1 font-size-12">
                                <i class="bx bx-file me-1"></i> Şablonu İndir
                            </a>
                        </div>
                    </div>
                </div>
                <form id="importExcelForm" enctype="multipart/form-data">
                    <div class="mb-0">
                        <label for="excelFile" class="form-label font-size-12 fw-semibold text-muted mb-1">Excel Dosyası (.xlsx, .xls)</label>
                        <input class="form-control rounded-3" type="file" id="excelFile" name="excelFile" accept=".xlsx, .xls" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex align-items-center justify-content-end gap-2">
                <button type="button" class="btn btn-secondary btn-sm px-3 rounded-3" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success btn-sm px-4 rounded-3 d-flex align-items-center gap-1 shadow-sm" id="btnUploadExcel">
                    <i class="bx bx-upload font-size-16"></i> Yükle
                </button>
            </div>
        </div>
    </div>
</div>
