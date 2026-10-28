<?php $kepSettings = (new \App\Model\KepSettingsModel())->getSettings((int) ($_SESSION['firma_id'] ?? 0), null, true); ?>
<style>
/* ==========================================================================
   KEP GÖNDERİ MODALİ - MODERN SAAS / ENTERPRISE BENTO UI
   ========================================================================== */
#modalKepGonderisi .modal-dialog {
    height: calc(100vh - 1.5rem);
    max-height: calc(100vh - 1.5rem);
    margin: 0.75rem auto;
    max-width: 1040px;
}

#modalKepGonderisi .modal-content {
    height: 100%;
    max-height: 100%;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 24px;
    background: #f8fafc;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(15, 23, 42, 0.04);
}

#modalKepGonderisi #formKepGonderisi {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
}

/* Header */
#modalKepGonderisi .modal-header {
    flex: 0 0 auto;
    padding: 1.15rem 1.75rem;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid #eef2f6;
    z-index: 10;
}

#modalKepGonderisi .kep-header-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 18px -4px rgba(2, 132, 199, 0.38);
    font-size: 24px;
    flex-shrink: 0;
}

#modalKepGonderisi .kep-header-account-pill {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 14px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    margin-left: auto;
    margin-right: 1.25rem;
    transition: all 0.2s ease;
}

#modalKepGonderisi .kep-header-account-pill:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
}

#modalKepGonderisi .kep-header-account-pill .kep-sender-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #64748b;
    margin-right: -4px;
}

#modalKepGonderisi .kep-header-account-pill .kep-sender-addr {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

#modalKepGonderisi .kep-header-account-pill #modalKepBalanceBadge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Modal Body & Bento Cards */
#modalKepGonderisi .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    background: #f8fafc;
    padding: 1.5rem 1.75rem !important;
}

#modalKepGonderisi .kep-compose-card {
    padding: 1.25rem 1.35rem;
    margin-bottom: 1.2rem;
    border: 1px solid #e5eaf2;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.035);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

#modalKepGonderisi .kep-compose-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 22px -2px rgba(15, 23, 42, 0.06);
}

#modalKepGonderisi .kep-compose-card:last-of-type {
    margin-bottom: 0;
}

/* Section Titles */
#modalKepGonderisi .kep-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

#modalKepGonderisi .kep-section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
}

#modalKepGonderisi .kep-section-index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0369a1;
    font-size: 13px;
    font-weight: 800;
}

#modalKepGonderisi .kep-section-title strong {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.2px;
}

#modalKepGonderisi .kep-section-title small {
    font-size: 11.5px;
    color: #64748b;
}

/* Inputs & Form Groups */
#modalKepGonderisi .form-floating:has(:required) > label::after {
    content: " *";
    color: #ef4444;
    font-weight: 700;
}

#modalKepGonderisi .form-floating-custom .form-control {
    border-radius: 12px;
    border-color: #dbe2ea;
    font-size: 13px;
    transition: all 0.2s ease;
}

#modalKepGonderisi .form-floating-custom .form-control:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}

#modalKepGonderisi .font-monospace {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
}

/* Alıcı Doğrulama Barı & Chip'ler */
#modalKepGonderisi .kep-recipient-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 0.5rem;
    padding: 0.4rem 0.65rem;
    background: #f8fafc;
    border: 1px solid #eef2f6;
    border-radius: 10px;
}

#modalKepGonderisi .kep-recipient-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 0.55rem;
    min-height: 0;
}

#modalKepGonderisi .kep-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-size: 11.5px;
    font-family: ui-monospace, SFMono-Regular, monospace;
}

#modalKepGonderisi .kep-chip.invalid {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

#modalKepGonderisi .kep-chip .chip-remove {
    cursor: pointer;
    opacity: 0.6;
    font-size: 14px;
    transition: opacity 0.15s ease;
}

#modalKepGonderisi .kep-chip .chip-remove:hover {
    opacity: 1;
}

#modalKepGonderisi .kep-address-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
}

#modalKepGonderisi .kep-save-option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #334155;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
}

#modalKepGonderisi .kep-save-option .form-check-input {
    margin: 0;
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 5px;
    border-color: #cbd5e1;
    cursor: pointer;
}

#modalKepGonderisi .kep-save-option .form-check-input:checked {
    background-color: #0284c7;
    border-color: #0284c7;
}

#modalKepGonderisi .kep-address-book-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 0.45rem;
    color: #64748b;
    font-size: 11px;
}

/* İleti Tipi Segmented Switch */
#modalKepGonderisi .kep-type-switch {
    display: flex;
    gap: 6px;
    padding: 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    height: 58px;
}

#modalKepGonderisi .kep-type-switch .btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    margin: 0;
    border: 0;
    border-radius: 8px;
    color: #64748b;
    font-size: 11.5px;
    font-weight: 600;
    line-height: 1.2;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

#modalKepGonderisi .kep-type-switch .btn i {
    font-size: 16px;
    margin-bottom: 2px;
}

#modalKepGonderisi .kep-type-switch .btn-check:checked + .btn {
    background: #ffffff;
    color: #0284c7;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}

/* Summernote & Editör */
#modalKepGonderisi .note-editor.note-frame {
    margin-bottom: 0;
    border: 1px solid #dbe2ea !important;
    border-radius: 14px !important;
    overflow: hidden;
    background: #ffffff;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.02);
    transition: all 0.2s ease;
}

#modalKepGonderisi .note-editor.note-frame.focus {
    border-color: #0ea5e9 !important;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15) !important;
}

#modalKepGonderisi .note-toolbar {
    background: #f8fafc !important;
    border-bottom: 1px solid #eef2f6 !important;
    padding: 6px 10px !important;
}

#modalKepGonderisi .note-btn {
    border-radius: 6px !important;
    border: 1px solid transparent !important;
    background: transparent !important;
    color: #475569 !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
}

#modalKepGonderisi .note-btn:hover {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

#modalKepGonderisi .note-editable {
    min-height: 140px !important;
    max-height: 200px;
    overflow-y: auto;
    background: #ffffff;
    font-size: 13.5px;
    color: #1e293b;
    line-height: 1.6;
    padding: 14px 16px !important;
}

/* Hızlı Şablon Butonları */
#modalKepGonderisi .kep-template-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0.65rem;
    flex-wrap: wrap;
}

#modalKepGonderisi .kep-template-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    color: #475569;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

#modalKepGonderisi .kep-template-btn:hover {
    background: #e0f2fe;
    border-color: #bae6fd;
    color: #0284c7;
}

/* Modern Ek Yükleme Dropzone Kutusu */
#modalKepGonderisi .kep-dropzone-box {
    position: relative;
    border: 2px dashed #cbd5e1 !important;
    border-radius: 14px;
    background: #fbfcfe !important;
    padding: 1rem 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

#modalKepGonderisi .kep-dropzone-box:hover,
#modalKepGonderisi .kep-dropzone-box.dragover {
    border-color: #0284c7 !important;
    background: #f0f9ff !important;
    transform: translateY(-1px);
}

#modalKepGonderisi .kep-dropzone-box input[type="file"] {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 5;
}

#modalKepGonderisi .kep-dropzone-icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 6px;
    border-radius: 10px;
    background: #e0f2fe;
    color: #0284c7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

#modalKepGonderisi .kep-file-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 0.75rem;
}

#modalKepGonderisi .kep-file-pill-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 11.5px;
    color: #334155;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

#modalKepGonderisi .kep-file-pill-item i {
    font-size: 15px;
    color: #0284c7;
}

#modalKepGonderisi .kep-file-pill-item .file-size {
    color: #94a3b8;
    font-size: 10.5px;
}

/* Footer & E-İmza Durum Barı */
#modalKepGonderisi .modal-footer {
    flex: 0 0 auto;
    padding: 0.85rem 1.75rem;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-top: 1px solid #eef2f6;
    box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.04);
    z-index: 10;
}

#modalKepGonderisi .kep-sign-status {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.45rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}

#modalKepGonderisi .kep-sign-status:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

#modalKepGonderisi .kep-sign-status-icon {
    position: relative;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    border-radius: 10px;
    background: #e0f2fe;
    color: #0284c7;
    font-size: 20px;
}

#modalKepGonderisi .kep-sign-pulse-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #f59e0b;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    animation: kepPulseAmber 2s infinite;
}

#modalKepGonderisi .kep-sign-pulse-dot.ready {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: kepPulseGreen 2s infinite;
}

@keyframes kepPulseAmber {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

@keyframes kepPulseGreen {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

#modalKepGonderisi .kep-sign-status-copy {
    min-width: 0;
    line-height: 1.25;
}

#modalKepGonderisi .kep-sign-status-copy .badge {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    font-weight: 700;
}

#modalKepGonderisi .kep-sign-status-copy #modalKepAgentDetails {
    display: block !important;
    max-width: 480px;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #64748b;
}

/* Footer Butonları */
#modalKepGonderisi .kep-footer-actions .btn {
    min-height: 42px;
    border-radius: 11px;
    font-weight: 600;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 1.25rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

#modalKepGonderisi #btnModalKepGonder {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: #ffffff;
    box-shadow: 0 6px 18px -2px rgba(16, 185, 129, 0.35);
}

#modalKepGonderisi #btnModalKepGonder:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px -2px rgba(16, 185, 129, 0.45);
}

#modalKepGonderisi #btnModalKepGonder:active:not(:disabled) {
    transform: translateY(0);
}

#modalKepGonderisi #btnModalKepGonder:disabled {
    opacity: 0.6;
    background: #94a3b8;
    box-shadow: none;
    cursor: not-allowed;
}

/* ==========================================================================
   DARK MODE / MACOS DARK DESTEĞİ
   ========================================================================== */
html[data-theme-preset="macos-dark"] #modalKepGonderisi .modal-content,
[data-bs-theme="dark"] #modalKepGonderisi .modal-content,
body[data-layout-mode="dark"] #modalKepGonderisi .modal-content {
    background: #151821 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #e2e8f0 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .modal-header,
[data-bs-theme="dark"] #modalKepGonderisi .modal-header,
body[data-layout-mode="dark"] #modalKepGonderisi .modal-header {
    background: rgba(22, 26, 36, 0.95) !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .modal-body,
[data-bs-theme="dark"] #modalKepGonderisi .modal-body,
body[data-layout-mode="dark"] #modalKepGonderisi .modal-body {
    background: #151821 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-compose-card,
[data-bs-theme="dark"] #modalKepGonderisi .kep-compose-card,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-compose-card {
    background: #1c212d !important;
    border-color: rgba(255, 255, 255, 0.07) !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-section-title,
[data-bs-theme="dark"] #modalKepGonderisi .kep-section-title,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-section-title {
    border-bottom-color: rgba(255, 255, 255, 0.06) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-section-title strong,
[data-bs-theme="dark"] #modalKepGonderisi .kep-section-title strong,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-section-title strong {
    color: #f1f5f9 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-header-account-pill,
[data-bs-theme="dark"] #modalKepGonderisi .kep-header-account-pill,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-header-account-pill {
    background: #1e2433 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-header-account-pill .kep-sender-addr,
[data-bs-theme="dark"] #modalKepGonderisi .kep-header-account-pill .kep-sender-addr,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-header-account-pill .kep-sender-addr {
    color: #e2e8f0 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-type-switch,
[data-bs-theme="dark"] #modalKepGonderisi .kep-type-switch,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-type-switch {
    background: #161922 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-type-switch .btn-check:checked + .btn,
[data-bs-theme="dark"] #modalKepGonderisi .kep-type-switch .btn-check:checked + .btn,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-type-switch .btn-check:checked + .btn {
    background: #242b3a !important;
    color: #38bdf8 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .note-editor.note-frame,
[data-bs-theme="dark"] #modalKepGonderisi .note-editor.note-frame,
body[data-layout-mode="dark"] #modalKepGonderisi .note-editor.note-frame {
    border-color: rgba(255, 255, 255, 0.1) !important;
    background: #161922 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .note-toolbar,
[data-bs-theme="dark"] #modalKepGonderisi .note-toolbar,
body[data-layout-mode="dark"] #modalKepGonderisi .note-toolbar {
    background: #1c212d !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .note-editable,
[data-bs-theme="dark"] #modalKepGonderisi .note-editable,
body[data-layout-mode="dark"] #modalKepGonderisi .note-editable {
    background: #161922 !important;
    color: #e2e8f0 !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-dropzone-box,
[data-bs-theme="dark"] #modalKepGonderisi .kep-dropzone-box,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-dropzone-box {
    background: #161a24 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .modal-footer,
[data-bs-theme="dark"] #modalKepGonderisi .modal-footer,
body[data-layout-mode="dark"] #modalKepGonderisi .modal-footer {
    background: rgba(22, 26, 36, 0.95) !important;
    border-top-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalKepGonderisi .kep-sign-status,
[data-bs-theme="dark"] #modalKepGonderisi .kep-sign-status,
body[data-layout-mode="dark"] #modalKepGonderisi .kep-sign-status {
    background: #1e2433 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}

@media (max-width: 767.98px) {
    #modalKepGonderisi .kep-header-account-pill { display: none; }
    #modalKepGonderisi .kep-recipient-grid { row-gap: 12px; }
    #modalKepGonderisi .kep-address-actions { align-items: flex-start; flex-direction: column; }
}

@media (max-width: 575.98px) {
    #modalKepGonderisi .modal-dialog { height: 100vh; max-height: 100vh; margin: 0; }
    #modalKepGonderisi .modal-content { border-radius: 0 !important; }
    #modalKepGonderisi .modal-body { padding: 1rem !important; }
    #modalKepGonderisi .modal-header, #modalKepGonderisi .modal-footer { padding: 0.85rem 1rem; }
    #modalKepGonderisi .kep-sign-status { width: 100%; }
    #modalKepGonderisi .kep-footer-actions { width: 100%; }
    #modalKepGonderisi .kep-footer-actions .btn { flex: 1; }
}
</style>

<div class="modal fade" id="modalKepGonderisi" tabindex="-1" aria-labelledby="modalKepGonderisiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <form id="formKepGonderisi" enctype="multipart/form-data">
                <!-- Modal Header -->
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="kep-header-icon-box">
                            <i class="bx bx-paper-plane"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title fw-bold mb-0 text-dark" id="modalKepGonderisiLabel">KEP İletisi Oluştur</h5>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-11 px-2 py-1">PTT KEP</span>
                            </div>
                            <div class="small text-muted mt-1">PTT KEP altyapısı üzerinden delilli, resmî ve güvenli ileti gönderin</div>
                        </div>
                    </div>

                    <?php if ($kepSettings && !empty($kepSettings['kep_adresi'])): ?>
                    <div class="kep-header-account-pill" title="Gönderici KEP Hesabı ve Bakiye Bilgisi">
                        <span class="kep-sender-title">GÖNDEREN:</span>
                        <span class="kep-sender-addr"><?php echo htmlspecialchars((string) $kepSettings['kep_adresi'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span id="modalKepBalanceBadge" class="badge bg-secondary-subtle text-secondary border">
                            <i class="bx bx-loader-alt bx-spin"></i> Kontrol ediliyor
                        </span>
                    </div>
                    <?php endif; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <?php if (!$kepSettings || empty($kepSettings['kep_adresi']) || empty($kepSettings['has_user_credentials'])): ?>
                        <div class="alert alert-warning border-warning-subtle d-flex align-items-center mb-3 rounded-3 shadow-sm">
                            <i class="bx bx-error-circle fs-3 text-warning me-3 flex-shrink-0"></i>
                            <div>
                                <strong class="text-dark">KEP kullanıcı ayarlarınız tamamlanmamış.</strong>
                                <div class="small text-muted mt-1">İleti gönderebilmek için İşlemler menüsündeki <strong>UETS / KEP Ayarları</strong> bölümünden KEP kullanıcı bilgilerinizi ve şifrenizi tanımlamalısınız.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 1. ALICI BİLGİLERİ -->
                    <section class="kep-compose-card">
                        <div class="kep-section-title">
                            <div class="kep-section-heading">
                                <span class="kep-section-index">1</span>
                                <div>
                                    <strong>Alıcı Bilgileri <span class="text-danger" title="Zorunlu Alan">*</span></strong>
                                    <small class="d-block">Alıcı KEP adreslerini girin veya adres defterinden hızlıca ekleyin</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" id="btnModalKepAliciTemizle" class="btn btn-sm btn-link text-muted p-0 font-size-11 text-decoration-none">
                                    <i class="bx bx-trash me-1"></i>Temizle
                                </button>
                            </div>
                        </div>

                        <div class="row kep-recipient-grid align-items-stretch g-3">
                            <div class="col-md-7">
                                <?php echo \App\Helper\Form::FormFloatTextarea(
                                    'modal_kep_alicilar', 
                                    '', 
                                    'kurum@hs01.kep.tr; sirket@hs02.kep.tr', 
                                    'Alıcı KEP Adresleri (Birden fazla adresi noktalı virgül ile ayırın)', 
                                    'users', 
                                    'form-control font-monospace', 
                                    true, 
                                    '74px', 
                                    2, 
                                    'data-form-name="kep_alicilar"'
                                ); ?>
                                <div class="kep-recipient-toolbar">
                                    <span class="font-size-11 text-muted" id="kepAliciSayac">
                                        <i class="bx bx-check-double text-success me-1"></i><span id="kepAliciAdet">0</span> alıcı belirlendi
                                    </span>
                                    <button type="button" id="btnModalKepAliciDogrula" class="btn btn-xs btn-outline-primary px-2 py-1 font-size-11" title="PTT KEP rehberinde sorgula ve geçerliliği kontrol et">
                                        <i class="bx bx-user-check me-1"></i> PTT Rehberinde Doğrula
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <?php echo \App\Helper\Form::FormSelect2(
                                    'modal_kep_adres_defteri', 
                                    [], 
                                    '', 
                                    'Adres Defterinden Seç', 
                                    'book-open', 
                                    'key', 
                                    '', 
                                    'form-select select2', 
                                    false, 
                                    'width:100%', 
                                    'data-placeholder="Kayıtlı KEP adresi arayın..."'
                                ); ?>
                                <div class="kep-address-book-hint">
                                    <i class="bx bx-info-circle text-primary"></i> Seçtiğiniz adres otomatik olarak alıcı listesine eklenir.
                                </div>
                            </div>
                        </div>

                        <div class="kep-address-actions">
                            <label class="kep-save-option" for="modal_kep_adres_defterine_kaydet">
                                <input class="form-check-input" type="checkbox" id="modal_kep_adres_defterine_kaydet" checked>
                                <span>Doğrulanan yeni KEP adreslerini otomatik olarak Adres Defteri'ne kaydet</span>
                            </label>
                            <span class="small text-muted font-size-11">
                                <i class="bx bx-shield-quarter text-success me-1"></i>PTT KEP Rehber Onayı
                            </span>
                        </div>
                        <div id="modalKepDogrulamaSonuc" class="small mb-0"></div>
                    </section>

                    <!-- 2. İLETİ BİLGİLERİ -->
                    <section class="kep-compose-card">
                        <div class="kep-section-title">
                            <div class="kep-section-heading">
                                <span class="kep-section-index">2</span>
                                <div>
                                    <strong>İleti Bilgileri <span class="text-danger" title="Zorunlu Alan">*</span></strong>
                                    <small class="d-block">İletinin konusunu, türünü ve dosya/başvuru referansını belirleyin</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <?php echo \App\Helper\Form::FormFloatInput(
                                    'text', 
                                    'modal_kep_konu', 
                                    '', 
                                    'İletinin konusunu yazınız (Örn: Sözleşme İhtarname Yanıtı)', 
                                    'Konu', 
                                    'type', 
                                    'form-control', 
                                    true, 
                                    255, 
                                    'off', 
                                    false, 
                                    'data-form-name="kep_konu"'
                                ); ?>
                            </div>
                            <div class="col-md-4">
                                <input type="hidden" id="modal_kep_ileti_tipi" value="standart" data-form-name="kep_ileti_tipi">
                                <div class="kep-type-switch" role="radiogroup" aria-label="İleti tipi">
                                    <input class="btn-check" type="radio" name="modal_kep_ileti_tipi_secim" id="kepTipStandart" value="standart" checked>
                                    <label class="btn" for="kepTipStandart">
                                        <i class="bx bx-envelope"></i>
                                        <span>Standart KEP</span>
                                    </label>
                                    
                                    <input class="btn-check" type="radio" name="modal_kep_ileti_tipi_secim" id="kepTipResmi" value="resmi">
                                    <label class="btn" for="kepTipResmi">
                                        <i class="bx bx-certification"></i>
                                        <span>Resmî (e-Yazışma)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <?php echo \App\Helper\Form::FormFloatInput(
                                    'text', 
                                    'modal_kep_ileti_detayi', 
                                    '', 
                                    'İsteğe bağlı evrak referans numarası, dosya no veya kısa açıklama', 
                                    'İleti Detayı / Referans No', 
                                    'info', 
                                    'form-control', 
                                    false, 
                                    500, 
                                    'off', 
                                    false, 
                                    'data-form-name="kep_ileti_detayi"'
                                ); ?>
                            </div>
                        </div>
                    </section>

                    <!-- 3. İLETİ METNİ VE EKLER -->
                    <section class="kep-compose-card">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="kep-section-title">
                                    <div class="kep-section-heading">
                                        <span class="kep-section-index">3</span>
                                        <div>
                                            <strong>İleti Metni <span class="text-danger" title="Zorunlu Alan">*</span></strong>
                                            <small class="d-block">Alıcıya iletilecek yasal bildirimi ve açıklama metnini yazın</small>
                                        </div>
                                    </div>
                                    <div class="kep-template-bar">
                                        <span class="text-muted font-size-11 me-1"><i class="bx bx-notepad me-1"></i>Hızlı Şablon:</span>
                                        <button type="button" class="kep-template-btn" data-template="ustyazi">
                                            <i class="bx bx-file"></i> Üst Yazı Ektedir
                                        </button>
                                        <button type="button" class="kep-template-btn" data-template="bilgiedinme">
                                            <i class="bx bx-info-circle"></i> Bilgi Edinme
                                        </button>
                                        <button type="button" class="kep-template-btn" data-template="tebligat">
                                            <i class="bx bx-check-shield"></i> Tebligat Cevabı
                                        </button>
                                    </div>
                                </div>

                                <textarea id="modal_kep_icerik" class="form-control" rows="8" data-form-name="kep_icerik" required></textarea>
                            </div>

                            <!-- Ekler / Dropzone -->
                            <div class="col-12">
                                <div class="kep-dropzone-box" id="kepDropzoneBox">
                                    <input type="file" id="modal_kep_ekler" name="kep_ekler[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.odt,.ods,.zip,.eyp">
                                    <div class="kep-dropzone-icon">
                                        <i class="bx bx-cloud-upload"></i>
                                    </div>
                                    <div class="fw-semibold text-dark font-size-13 mb-1">
                                        Dosyaları buraya sürükleyin veya <span class="text-primary text-decoration-underline">bilgisayarınızdan seçin</span>
                                    </div>
                                    <div class="font-size-11 text-muted">
                                        PDF, Word, Excel, Görsel, ZIP veya e-Yazışma (EYP) dosyaları · En fazla 20 dosya (PTT: 100 MB, Diğer KEP: 25 MB)
                                    </div>
                                    <div class="mt-2">
                                        <span id="modalKepEkOzeti" class="badge bg-white text-dark border px-3 py-1 font-size-11 shadow-sm">
                                            0 dosya · 0 KB
                                        </span>
                                    </div>
                                </div>
                                <div id="kepSelectedFilesContainer" class="kep-file-pills"></div>
                            </div>

                            <!-- Onaylı Evrak Gönderim Bloğu (Var ise) -->
                            <div class="col-12 d-none" id="kepOnayliDosyaBlok">
                                <div class="border rounded-3 p-3 bg-light-subtle mt-1">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <strong><i class="bx bx-paperclip text-primary me-1"></i>Gönderilecek Onaylı Dosyalar</strong>
                                            <div class="small text-muted">Onaylı evrakın mühürlü üst yazısı ve ekleri değişmeden gönderilir.</div>
                                        </div>
                                        <span id="kepOnayliDosyaSayisi" class="badge bg-white text-dark border font-size-11"></span>
                                    </div>
                                    <div id="kepOnayliDosyaListe" class="list-group list-group-flush border rounded-3 bg-white"></div>
                                    <div id="kepOnayliOnizleme" class="d-none mt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-semibold text-truncate" id="kepOnayliOnizlemeAd"></span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnKepOnayliOnizlemeKapat">Kapat</button>
                                        </div>
                                        <iframe id="kepOnayliOnizlemeFrame" title="Gönderilecek dosya önizleme" class="w-100 border rounded-3 bg-white" style="height:55vh"></iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <input type="hidden" data-form-name="kep_paket_turu" value="Standart">
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <!-- E-İmza Ajan Durum Kutusu -->
                    <div id="modalKepAgentStatusWrapper" class="kep-sign-status" title="E-İmza bağlantı durumunu görüntülemek veya yardım almak için tıklayın">
                        <div class="kep-sign-status-icon">
                            <i class="bx bx-shield-quarter"></i>
                            <span class="kep-sign-pulse-dot" id="kepSignPulseDot"></span>
                        </div>
                        <div class="kep-sign-status-copy">
                            <span id="modalKepAgentBadge" class="badge bg-secondary-subtle text-secondary border font-size-11">
                                <i class="bx bx-loader-alt bx-spin me-1"></i> E-İmza Kontrol Ediliyor...
                            </span>
                            <span id="modalKepAgentDetails" class="small text-muted font-size-11"></span>
                        </div>
                        <span class="btn btn-xs btn-light rounded-circle ms-1 p-1 text-muted" id="btnAgentHelpModalIcon" title="Yardım ve Ajan İndir">
                            <i class="bx bx-help-circle font-size-15"></i>
                        </span>
                    </div>

                    <!-- Buton Aksiyonları -->
                    <div class="kep-footer-actions d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">
                            <i class="bx bx-x me-1"></i>Vazgeç
                        </button>
                        
                        <button type="button" class="btn btn-outline-primary px-3" id="btnKepIletiOnizle" data-gonderen="<?php echo htmlspecialchars((string) ($kepSettings['kep_adresi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="bx bx-show me-1"></i>İletiyi Önizle
                        </button>

                        <div class="dropup">
                            <button type="button" class="btn btn-success px-4 dropdown-toggle" id="btnModalKepGonder" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" data-settings-ready="<?php echo ($kepSettings && !empty($kepSettings['kep_adresi']) && !empty($kepSettings['has_user_credentials'])) ? '1' : '0'; ?>" disabled>
                                <i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder <i class="bx bx-chevron-up ms-1"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2 rounded-3">
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2" id="btnModalKepEimzaGonder">
                                        <i class="bx bx-shield-quarter text-success font-size-18"></i> 
                                        <div>
                                            <strong>E-İmza İle İmzala ve Gönder</strong>
                                            <div class="font-size-11 text-muted">Akıllı kart / USB token ile anında imzalanır</div>
                                        </div>
                                    </button>
                                </li>
                                <?php if (\App\Service\MobilImzaPadesService::isEnabled((int) ($_SESSION['firma_id'] ?? 0))): ?>
                                <li class="dropdown-divider"></li>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2" id="btnModalKepMobilGonder">
                                        <i class="bx bx-mobile-alt text-info font-size-18"></i> 
                                        <div>
                                            <strong>Mobil İmza İle İmzala ve Gönder</strong>
                                            <div class="font-size-11 text-muted">Turkcell / Vodafone / Türk Telekom SIM imza</div>
                                        </div>
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- E-İmza Yerel Ajanı Yardım Modalı -->
<div class="modal fade" id="modalEimzaAgentHelp" tabindex="-1" aria-labelledby="modalEimzaAgentHelpLabel" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-grid place-items-center bg-primary-subtle text-primary rounded-circle" style="width:36px;height:36px">
                        <i class="bx bx-chip fs-5"></i>
                    </span>
                    <h6 class="modal-title fw-bold mb-0" id="modalEimzaAgentHelpLabel">E-İmza Yerel Köprü Ajanı</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 px-3 small mb-3 rounded-3">
                    <i class="bx bx-info-circle me-1"></i>
                    Tarayıcınızın USB E-İmza kartınızla (SafeNet, Akis, E-Tuğra vb.) güvenli iletişim kurabilmesi için UYAP ve e-Devlet sistemlerinde olduğu gibi Java imza köprüsü çalışmalıdır.
                </div>

                <div class="text-center my-3">
                    <a href="downloads/ErsanEImza.jar?v=1.3.1" download="ErsanEImza.jar" class="btn btn-primary btn-lg w-100 shadow-sm py-2 rounded-3">
                        <i class="bx bxs-download me-2 fs-5 align-middle"></i><strong>Ersan E-İmza Java Uygulamasını İndir</strong>
                        <div class="font-size-11 opacity-75 mt-1">ErsanEImza.jar (Çift tıklayarak çalıştırın)</div>
                    </a>
                </div>
                
                <h6 class="fw-semibold font-size-13 mb-2"><i class="bx bx-check-shield text-success me-1"></i>Kullanım Adımları:</h6>
                <ol class="small text-muted ps-3 mb-3">
                    <li class="mb-2">USB E-İmza cihazınızın bilgisayarınıza takılı olduğunu doğrulayın.</li>
                    <li class="mb-2">Yukarıdaki butondan <strong>ErsanEImza.jar</strong> dosyasını indirin ve <strong>çift tıklayarak açın</strong>.</li>
                    <li class="mb-1">Uygulama arka planda sessizce başlayacak ve web sayfamız e-imza kartınızı otomatik algılayacaktır.</li>
                </ol>

                <div class="p-2 bg-light rounded text-center small text-muted">
                    <i class="bx bx-chip me-1"></i>Uyumlu Cihazlar: SafeNet (TÜRKKEP / E-GÜVEN), AKİS (KamuSM), E-Tuğra
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnCheckAgentAgain">
                    <i class="bx bx-refresh me-1"></i>Bağlantıyı Yeniden Kontrol Et
                </button>
            </div>
        </div>
    </div>
</div>
