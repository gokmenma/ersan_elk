<?php

require_once "vendor/autoload.php";

use App\Helper\Alert;
use App\Helper\Security;
use App\Service\Gate;

if (Gate::allows('yetki_gruplari') || Gate::allows('yetki_gruplari_izleme')) { ?>
    <script>window.canManagePermissionGroups = <?= Gate::allows('yetki_gruplari') ? 'true' : 'false' ?>;</script>
    <script>window.permissionMatrixCsrf = <?= json_encode(Security::csrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <style>
        .permission-matrix-hero {
            background: linear-gradient(135deg, rgba(91, 115, 232, .09), rgba(10, 179, 156, .06));
            border: 1px solid rgba(91, 115, 232, .16);
        }

        .permission-matrix-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .permission-result-card {
            border: 1px solid var(--bs-border-color, #e9e9ef);
            border-radius: 12px;
            background: var(--bs-body-bg, #fff);
        }

        .permission-role-toggle {
            min-width: 210px;
            border-radius: 999px;
            transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        button.permission-role-toggle:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 52, 89, .12);
        }

        .permission-role-toggle.is-enabled {
            color: #059669;
            background: rgba(16, 185, 129, .09);
            border-color: rgba(16, 185, 129, .35);
        }

        .permission-role-toggle.is-disabled {
            color: #64748b;
            background: rgba(148, 163, 184, .08);
            border-color: rgba(148, 163, 184, .3);
        }

        .permission-role-toggle.is-loading {
            opacity: .65;
            pointer-events: none;
        }
    </style>

    <div class="container-fluid">
        <?php
        $maintitle = 'Yetki Grupları';
        $title = 'Yetki Matrisi';
        include 'layouts/breadcrumb.php';
        ?>

        <div class="row align-items-center mb-3">
            <div class="col">
                <div class="d-flex align-items-center gap-3">
                    <div class="permission-matrix-icon p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle">
                        <i class="mdi mdi-table-key font-size-24"></i>
                    </div>
                    <div>
                        <h4 class="font-size-16 fw-bold text-dark mb-1">Yetki Matrisi</h4>
                        <p class="font-size-12 text-muted mb-0">Bir yetkinin tüm gruplardaki durumunu görüntüleyin ve yönetin.</p>
                    </div>
                </div>
            </div>
            <div class="col-auto personel-action-toolbar">
                <a href="index?p=kullanici-gruplari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                    <i class="mdi mdi-arrow-left me-1"></i> Yetki Gruplarına Dön
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-lg-4">
                <div class="permission-matrix-hero rounded-3 p-3 p-lg-4">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-4">
                            <h5 class="font-size-15 fw-bold text-dark mb-1">
                                <i class="mdi mdi-shield-search-outline text-primary me-1"></i> Yetki Ara
                            </h5>
                            <p class="font-size-12 text-muted mb-0">Ad, açıklama, modül veya yetki koduyla arayabilirsiniz.</p>
                        </div>
                        <div class="col-lg-8">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0"><i class="mdi mdi-magnify text-primary"></i></span>
                                <input type="search" id="permissionMatrixSearch" class="form-control border-start-0"
                                       placeholder="Örn. E-Fatura, personel ekleme, efatura/..."
                                       autocomplete="off" autofocus aria-label="Yetki ara">
                                <button type="button" id="permissionMatrixClear" class="btn btn-light border d-none" title="Aramayı temizle">
                                    <i class="mdi mdi-close"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mt-3">
                    <div id="permissionMatrixSummary" class="text-muted font-size-12">Aramak için en az 2 karakter yazın.</div>
                    <div class="d-flex align-items-center gap-2 font-size-11">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">Açık</span>
                        <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">Kapalı</span>
                        <?php if (Gate::allows('yetki_gruplari')) { ?>
                            <span class="text-muted">Durumu değiştirmek için gruba tıklayın.</span>
                        <?php } ?>
                    </div>
                </div>

                <div id="permissionMatrixResults" class="mt-3" aria-live="polite">
                    <div class="text-center text-muted py-5">
                        <i class="mdi mdi-text-search font-size-36 d-block mb-2 text-primary"></i>
                        Aradığınız sayfa veya işlem yetkisini yazın.
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php } else {
    Alert::danger('Bu yetkiye sahip değilsiniz.');
}
?>
