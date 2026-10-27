<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
use App\Helper\Form;

$startDate = date('d.m.Y', strtotime('-30 days'));
$endDate = date('d.m.Y');
$maintitle = "Mail & SMS";
$title = "Gönderim Geçmişi";
?>
<script>try { document.documentElement.classList.toggle('mesajlog-summary-hidden', localStorage.getItem('mesajlog_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.mesajlog-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include dirname(__DIR__, 2) . '/layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-envelope fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Gönderim Geçmişi</h4>
                <p class="text-muted mb-0 font-size-12">Gönderilen e-posta, SMS ve push bildirim kayıtları</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <?php
        $cards = [
            ['TOPLAM GÖNDERİM', 'toplam', 'primary', 'bx-layer', 'all', 'Tümü', 'bx-layer', 'btn-subtle-primary', 'active'],
            ['E-POSTA', 'email', 'success', 'bx-envelope', 'email', 'E-posta', 'bx-envelope', 'btn-subtle-success', ''],
            ['SMS', 'sms', 'warning', 'bx-message-detail', 'sms', 'SMS', 'bx-message-detail', 'btn-subtle-warning', ''],
            ['PUSH BİLDİRİM', 'push', 'info', 'bx-bell', 'push', 'Push', 'bx-bell', 'btn-subtle-info', ''],
        ];
        foreach ($cards as [$label, $key, $color, $icon, $filter, $btnText, $btnIcon, $btnClass, $active]): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label"><?= $label ?></span>
                        <div class="summary-kpi-icon bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle">
                            <i class="bx <?= $icon ?>"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_<?= $key ?>">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_<?= $key ?>">&nbsp;</span>
                        <button type="button" class="btn btn-sm <?= $btnClass ?> rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn <?= $active ?>" data-type="<?= $filter ?>">
                            <i class="bx <?= $btnIcon ?>"></i> <?= $btnText ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card summary-kpi-card mb-3" id="mesajlogListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Mesaj Gönderim Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, sütun filtreleme ve tarih aralığı</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <div style="width: 170px;"><?php echo Form::FormDate('start_date', $startDate, 'Başlangıç Tarihi', 'calendar'); ?></div>
                <div style="width: 170px;"><?php echo Form::FormDate('end_date', $endDate, 'Bitiş Tarihi', 'calendar'); ?></div>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="logTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="string" style="width: 70px;" class="text-center">ID</th>
                            <th data-filter="none" style="width: 110px;" class="text-center">TİP</th>
                            <th data-filter="string">GÖNDEREN</th>
                            <th data-filter="string">ALICILAR</th>
                            <th data-filter="string">KONU / MESAJ ÖZETİ</th>
                            <th data-filter="none" style="width: 100px;" class="text-center">DURUM</th>
                            <th data-filter="none" style="width: 130px;">TARİH</th>
                            <th data-filter="none" style="width: 80px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detay Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div id="modalTypeIcon"
                        class="detail-modal-icon rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fas fa-envelope fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-semibold">Mesaj Detayı</h5>
                        <small class="text-muted" id="modalDateSmall"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <!-- Info Cards Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="bg-light rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-paper-plane text-primary"></i>
                                <span class="text-muted small fw-medium">Gönderen</span>
                            </div>
                            <p class="mb-0 fw-semibold text-dark" id="modalSender"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-calendar text-primary"></i>
                                <span class="text-muted small fw-medium">Gönderim Tarihi</span>
                            </div>
                            <p class="mb-0 fw-semibold text-dark" id="modalDate"></p>
                        </div>
                    </div>
                </div>

                <!-- Recipients Section -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-users text-primary"></i>
                        <span class="fw-medium text-dark">Alıcılar</span>
                    </div>
                    <div class="bg-light rounded-3 p-3" style="max-height: 100px; overflow-y: auto;">
                        <div id="modalRecipients" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Subject Section (Email only) -->
                <div class="mb-4" id="modalSubjectRow">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-heading text-primary"></i>
                        <span class="fw-medium text-dark">Konu</span>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                        <p class="mb-0 fw-semibold text-primary" id="modalSubject"></p>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-file-alt text-primary"></i>
                        <span class="fw-medium text-dark">Mesaj İçeriği</span>
                    </div>
                    <div class="border rounded-3 bg-white" style="min-height: 150px;">
                        <pre id="modalMessageText" class="mb-0 p-3 d-none" style="white-space: pre-wrap; max-height: 350px; overflow-y: auto; font-family: inherit;"></pre>
                        <iframe id="modalMessageFrame" class="w-100 border-0 d-none" sandbox="" style="height: 350px;"></iframe>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="mb-0" id="modalAttachmentsRow" style="display: none;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-paperclip text-primary"></i>
                        <span class="fw-medium text-dark">Ekler</span>
                    </div>
                    <div id="modalAttachments" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-soft-secondary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #detailModal .detail-modal-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
    }

    #detailModal .detail-modal-icon.bg-primary-soft {
        background-color: rgba(85, 110, 230, 0.15) !important;
        color: #556ee6;
    }

    #detailModal .detail-modal-icon.bg-warning-soft {
        background-color: rgba(241, 180, 76, 0.15) !important;
        color: #f1b44c;
    }

    #detailModal .detail-modal-icon.bg-info-soft {
        background-color: rgba(80, 165, 241, 0.15) !important;
        color: #50a5f1;
    }

    #detailModal .recipient-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 20px;
        font-size: 13px;
        color: #495057;
    }

    #detailModal .recipient-badge i {
        font-size: 11px;
        color: #6c757d;
    }

    #detailModal .attachment-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 13px;
        color: #495057;
        text-decoration: none;
        transition: all 0.2s;
    }

    #detailModal .attachment-item:hover {
        background: #e9ecef;
        color: #212529;
    }

    #detailModal .attachment-item i {
        color: #6c757d;
    }
</style>

<script src="<?php echo \App\Helper\Helper::assetVersion('views/mail-sms/js/list.js'); ?>"></script>