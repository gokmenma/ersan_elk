<?php
use App\Model\SystemLogModel;
use App\Service\Gate;

if (Gate::allows("log_kayitlari")) {

    $systemLogModel = new SystemLogModel();
    ?>
    <div class="container-fluid">

        <!-- start page title -->
        <?php
        $maintitle = "Loglar";
        $title = "Sistem Logları";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #fff;">
                    <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-3" style="border-bottom: 1px solid rgba(226,232,240,0.6) !important;">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2 mb-3 mt-1" style="font-family: 'Outfit', sans-serif;">
                            <i class="bx bx-list-ul text-primary fs-4"></i> Sistem Kayıtları
                        </h5>
                        <ul class="nav nav-tabs card-header-tabs m-0" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#sistem-loglari-tab" role="tab">
                                    <i class="bx bx-error-circle me-1"></i> Sistem Olayları
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#personel-giris-tab" role="tab">
                                    <i class="bx bx-user me-1"></i> Personel Girişleri
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#kullanici-giris-tab" role="tab">
                                    <i class="bx bx-shield-quarter me-1"></i> Yönetici Girişleri
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#sayfa-goruntulemeleri-tab" role="tab">
                                    <i class="bx bx-search-alt me-1"></i> Sayfa Görüntülemeleri
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Sistem Logları Tab'ı -->
                            <div class="tab-pane active" id="sistem-loglari-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap table-hover mb-0 align-middle w-100" id="logsTable">
                                        <thead style="background: rgba(248,250,252,0.8);">
                                            <tr>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Seviye</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">İşlem Tipi</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">İçerik</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Tarih</th>
                                                <th class="text-center" style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Personel Girişleri Tab'ı -->
                            <div class="tab-pane" id="personel-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-nowrap align-middle mb-0 w-100" id="personelLogsTable">
                                        <thead style="background: rgba(248,250,252,0.8);">
                                            <tr style="border-bottom: 2px solid #f1f5f9;">
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Ad Soyad</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarih</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarayıcı</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">IP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Yönetici Girişleri Tab'ı -->
                            <div class="tab-pane" id="kullanici-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-nowrap align-middle mb-0 w-100" id="kullaniciLogsTable">
                                        <thead style="background: rgba(248,250,252,0.8);">
                                            <tr style="border-bottom: 2px solid #f1f5f9;">
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Ad Soyad</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarih</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">IP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Sayfa Görüntülemeleri Tab'ı -->
                            <div class="tab-pane" id="sayfa-goruntulemeleri-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap table-hover mb-0 align-middle w-100" id="pageViewLogsTable">
                                        <thead style="background: rgba(248,250,252,0.8);">
                                            <tr>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Kullanıcı</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">İçerik</th>
                                                <th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Tarih</th>
                                                <th class="text-center" style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600;">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div> <!-- end tab-content -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Detay Modal -->
        <style>
            #modalLogDetay .modal-content {
                border: none;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 25px 70px rgba(0, 0, 0, 0.18);
            }
            #modalLogDetay .log-modal-header {
                background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
                padding: 1.5rem 1.75rem;
                position: relative;
                overflow: hidden;
            }
            #modalLogDetay .log-modal-header::before {
                content: ''; position: absolute; top: -40px; right: -40px; width: 140px; height: 140px;
                background: rgba(255, 255, 255, 0.07); border-radius: 50%;
            }
            #modalLogDetay .log-modal-header::after {
                content: ''; position: absolute; bottom: -50px; left: 30px; width: 100px; height: 100px;
                background: rgba(255, 255, 255, 0.05); border-radius: 50%;
            }
            #modalLogDetay .modal-icon-wrap {
                width: 44px; height: 44px; background: rgba(255, 255, 255, 0.15); border-radius: 12px;
                display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); flex-shrink: 0;
            }
            #modalLogDetay .log-meta-card {
                background: #f8f9fc; border: 1px solid #e9ecf3; border-radius: 12px; padding: 0.85rem 1rem;
            }
            #modalLogDetay .log-meta-label {
                font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #8a94ad; margin-bottom: 3px;
            }
            #modalLogDetay .log-meta-value {
                font-size: 0.925rem; font-weight: 700; color: #2d3a56; margin: 0;
            }
            #modalLogDetay .log-content-box {
                background: linear-gradient(135deg, #f8f9fc 0%, #f0f3ff 100%); border: 1px solid #dde2f1;
                border-radius: 14px; padding: 1.1rem 1.25rem; min-height: 60px;
            }
            #modalLogDetay .log-content-box .change-table {
                border-radius: 10px; overflow: hidden; border: 1px solid #dde2f1; margin-top: 0.75rem; width: 100%;
            }
            #modalLogDetay .log-content-box .change-table thead th {
                background: #4361ee; color: #fff; font-size: 0.78rem; font-weight: 600; padding: 0.6rem 0.9rem; border: none;
            }
            #modalLogDetay .log-content-box .change-table tbody td {
                padding: 0.55rem 0.9rem; font-size: 0.85rem; border-color: #eaecf4;
            }
            #modalLogDetay .change-arrow {
                display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem;
            }
            #modalLogDetay .change-arrow .from-val {
                background: #fee2e2; color: #b91c1c; padding: 1px 8px; border-radius: 20px; font-size: 0.78rem;
            }
            #modalLogDetay .change-arrow .to-val {
                background: #dcfce7; color: #15803d; padding: 1px 8px; border-radius: 20px; font-size: 0.78rem;
            }
            #modalLogDetay .change-arrow .arrow-icon { color: #94a3b8; font-size: 1rem; }
            #modalLogDetay .section-divider {
                display: flex; align-items: center; gap: 10px; margin: 1.1rem 0 0.85rem; color: #8a94ad; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            }
            #modalLogDetay .section-divider::before, #modalLogDetay .section-divider::after {
                content: ''; flex: 1; height: 1px; background: #e2e6f0;
            }
            #modalLogDetay .btn-close-modal {
                background: linear-gradient(135deg, #4361ee, #3a0ca3); color: #fff; border: none; padding: 0.55rem 1.75rem; border-radius: 10px; font-size: 0.875rem; font-weight: 600; box-shadow: 0 4px 14px rgba(67, 97, 238, 0.35);
            }
        </style>

        <div class="modal fade" id="modalLogDetay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="log-modal-header d-flex align-items-center gap-3" style="position:relative;z-index:1;">
                        <div class="modal-icon-wrap"><i class="bx bx-bell text-white fs-5"></i></div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 text-white fw-bold" style="font-size:1rem;">Bildirim Detayı</h5>
                            <small class="text-white" style="opacity:0.65;font-size:0.75rem;">Sistem Olay Kaydı</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-1">
                            <div class="col-md-5">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-tag me-1"></i>İşlem Tipi</div>
                                    <p id="logDetayTitle" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-user me-1"></i>İşlemi Yapan</div>
                                    <p id="logDetayUser" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-calendar me-1"></i>Tarih</div>
                                    <p id="logDetayDate" class="log-meta-value" style="font-size:0.82rem;">-</p>
                                </div>
                            </div>
                        </div>
                        <div class="section-divider">İçerik Detayı</div>
                        <div id="logDetayContent" class="log-content-box" style="white-space:pre-wrap;">-</div>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn-close-modal" data-bs-dismiss="modal"><i class="bx bx-x me-1"></i>Kapat</button>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- container-fluid -->

    <!-- Load DataTables scripts directly in case they are not part of global layout -->
    <script src="assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Setup DataTables parameters
            const dtOptions = {
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
                },
                pageLength: 25,
                ordering: true
            };

            if ($.fn.DataTable) {
                // Initialize tables with server-side processing
                $('#logsTable').DataTable($.extend({}, dtOptions, {
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'views/logs/api.php',
                        type: 'POST',
                        data: { action: 'get-system-logs' }
                    },
                    columns: [
                        { data: 'level' },
                        { data: 'action_type' },
                        { data: 'description' },
                        { data: 'date' },
                        { data: 'actions', orderable: false }
                    ],
                    order: [[3, 'desc']]
                }));

                $('#personelLogsTable').DataTable($.extend({}, dtOptions, {
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'views/logs/api.php',
                        type: 'POST',
                        data: { action: 'get-personel-logs' }
                    },
                    columns: [
                        { data: 'user' },
                        { data: 'date' },
                        { data: 'browser' },
                        { data: 'ip' }
                    ],
                    order: [[1, 'desc']]
                }));

                $('#kullaniciLogsTable').DataTable($.extend({}, dtOptions, {
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'views/logs/api.php',
                        type: 'POST',
                        data: { action: 'get-user-logs' }
                    },
                    columns: [
                        { data: 'user' },
                        { data: 'date' },
                        { data: 'ip' }
                    ],
                    order: [[1, 'desc']]
                }));

                $('#pageViewLogsTable').DataTable($.extend({}, dtOptions, {
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'views/logs/api.php',
                        type: 'POST',
                        data: { action: 'get-page-view-logs' }
                    },
                    columns: [
                        { data: 'user' },
                        { data: 'description' },
                        { data: 'date' },
                        { data: 'actions', orderable: false }
                    ],
                    order: [[2, 'desc']]
                }));
            }

            // Tab change event: redraw DataTable to prevent layout issues on hidden tabs
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });

            // Log Detay Modal JS logic
            $('body').on('click', '.btn-log-detay', function () {
                var btn = $(this);
                var title = btn.data('title');
                var user = btn.data('user');
                var date = btn.data('date');
                var content = btn.data('content');
                document.getElementById('logDetayTitle').textContent = title;
                document.getElementById('logDetayUser').textContent = user;
                document.getElementById('logDetayDate').textContent = date;

                if (content.indexOf('(Güncellenen veriler: {') !== -1) {
                    try {
                        let parts = content.split(' (Güncellenen veriler: { ');
                        let mainText = parts[0];
                        let changesPart = parts[1].replace(/ ?\}\)?$/, '');
                        let changes = changesPart.split(', ');

                        let formattedContent = `<div class="d-flex align-items-start gap-2 mb-3">
                        <i class='bx bx-edit-alt text-primary mt-1' style='font-size:1.1rem;flex-shrink:0;'></i>
                        <span style='font-size:0.875rem;color:#374151;line-height:1.55;'>${mainText}</span>
                    </div>`;

                        if (changes.some(c => c.indexOf(': ') !== -1)) {
                            formattedContent += `<div class="change-table">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Alan</th><th>Değişim</th></tr></thead>
                                <tbody>`;
                            changes.forEach(change => {
                                if (change.indexOf(': ') !== -1) {
                                    let sepIdx = change.indexOf(': ');
                                    let key = change.substring(0, sepIdx).trim();
                                    let val = change.substring(sepIdx + 2).trim();
                                    let displayVal = val;
                                    if (val.indexOf(' -> ') !== -1) {
                                        let parts = val.split(' -> ');
                                        displayVal = `<span class="change-arrow"><span class="from-val">${parts[0] || 'Boş'}</span><i class='bx bx-right-arrow-alt arrow-icon'></i><span class="to-val">${parts[1] || 'Boş'}</span></span>`;
                                    } else if (val.indexOf(' → ') !== -1) {
                                        let parts = val.split(' → ');
                                        displayVal = `<span class="change-arrow"><span class="from-val">${parts[0] || 'Boş'}</span><i class='bx bx-right-arrow-alt arrow-icon'></i><span class="to-val">${parts[1] || 'Boş'}</span></span>`;
                                    }
                                    formattedContent += `<tr><td class="field-cell" style="width:30%;font-weight:600;color:#4361ee;">${key}</td><td>${displayVal}</td></tr>`;
                                }
                            });
                            formattedContent += `</tbody></table></div>`;
                        }
                        document.getElementById('logDetayContent').innerHTML = formattedContent;
                    } catch (e) {
                        document.getElementById('logDetayContent').textContent = content;
                    }
                } else {
                    document.getElementById('logDetayContent').innerHTML =
                        '<i class="bx bx-info-circle text-primary me-2" style="font-size:1rem;vertical-align:middle;"></i>' +
                        content.replace(/\n/g, '<br>');
                }
                var myModal = new bootstrap.Modal(document.getElementById('modalLogDetay'));
                myModal.show();
            });
        });
    </script>
<?php } ?>
