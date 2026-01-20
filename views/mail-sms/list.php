<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Model\MesajLogModel;

$MesajLogModel = new MesajLogModel();

// Varsayılan filtreler (Son 30 gün)
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$type = '';

if (isset($_GET['start_date']))
    $startDate = $_GET['start_date'];
if (isset($_GET['end_date']))
    $endDate = $_GET['end_date'];
if (isset($_GET['type']))
    $type = $_GET['type'];

$firmaId = $_SESSION['firma_id'] ?? $_SESSION['site_id'] ?? 0;

$filters = [
    'firma_id' => $firmaId,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'type' => $type
];

$logs = $MesajLogModel->getLogs($filters);

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Mail & SMS";
    $title = "Gönderim Geçmişi";
    ?>
    <?php include dirname(__DIR__, 2) . '/layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Mesaj Gönderim Listesi</h4>
                    <p class="card-title-desc">Gönderilen Email ve SMS kayıtlarını buradan inceleyebilirsiniz.</p>
                </div>
                <div class="card-body">

                    <!-- Filtreleme Alanı -->
                    <form method="GET" action="index?p=mail-sms/list" class="row g-3 mb-4 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tip</label>
                            <select name="type" class="form-select">
                                <option value="">Tümü</option>
                                <option value="email" <?php echo $type == 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="sms" <?php echo $type == 'sms' ? 'selected' : ''; ?>>SMS</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>
                                Filtrele</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="logTable" class="table table-bordered table-striped dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tip</th>
                                    <th>Gönderen</th>
                                    <th>Alıcılar</th>
                                    <th>Konu / Mesaj Özeti</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $recipients = json_decode($log->recipients, true);
                                    $recipientCount = is_array($recipients) ? count($recipients) : 1;
                                    $recipientText = is_array($recipients) ? implode(', ', array_slice($recipients, 0, 3)) . ($recipientCount > 3 ? " (+$recipientCount)" : "") : $log->recipients;

                                    $messagePreview = mb_substr(strip_tags($log->message), 0, 50) . '...';
                                    $subject = $log->type == 'email' ? $log->subject : $messagePreview;

                                    $statusBadge = $log->status == 'success'
                                        ? '<span class="badge bg-success">Başarılı</span>'
                                        : '<span class="badge bg-danger">Başarısız</span>';

                                    if($log->type == 'email'){
                                        $icon = '<i class="fas fa-envelope text-primary fa-lg"></i>';
                                    }elseif($log->type == 'sms'){
                                        $icon = '<i class="fas fa-sms text-warning fa-lg"></i>';
                                    }elseif($log->type == 'push'){
                                        $icon = '<i class="fas fa-bell text-info fa-lg"></i>';
                                    }else{
                                        $icon = '<i class="fas fa-question text-secondary fa-lg"></i>';
                                    }
                                    
                                    ?>
                                    <tr>
                                        <td><?php echo $log->id; ?></td>
                                        <td class="text-center"><?php echo $icon; ?></td>
                                        <td><?php echo htmlspecialchars($log->sender); ?></td>
                                        <td><?php echo htmlspecialchars($recipientText); ?></td>
                                        <td><?php echo htmlspecialchars($subject); ?></td>
                                        <td class="text-center"><?php echo $statusBadge; ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-sm view-details"
                                                data-id="<?php echo $log->id; ?>" data-type="<?php echo $log->type; ?>"
                                                data-sender="<?php echo htmlspecialchars($log->sender); ?>"
                                                data-recipients='<?php echo htmlspecialchars($log->recipients, ENT_QUOTES); ?>'
                                                data-subject="<?php echo htmlspecialchars($log->subject); ?>"
                                                data-message='<?php echo htmlspecialchars($log->message, ENT_QUOTES); ?>'
                                                data-attachments='<?php echo htmlspecialchars($log->attachments, ENT_QUOTES); ?>'
                                                data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>">
                                                <i class="fas fa-eye"></i> Detay
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detay Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mesaj Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 row">
                    <label class="col-sm-3 col-form-label fw-bold">Tarih:</label>
                    <div class="col-sm-9">
                        <p class="form-control-plaintext" id="modalDate"></p>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-3 col-form-label fw-bold">Gönderen:</label>
                    <div class="col-sm-9">
                        <p class="form-control-plaintext" id="modalSender"></p>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-3 col-form-label fw-bold">Alıcılar:</label>
                    <div class="col-sm-9">
                        <div class="form-control-plaintext" id="modalRecipients"
                            style="max-height: 100px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="mb-3 row" id="modalSubjectRow">
                    <label class="col-sm-3 col-form-label fw-bold">Konu:</label>
                    <div class="col-sm-9">
                        <p class="form-control-plaintext" id="modalSubject"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Mesaj İçeriği:</label>
                    <div class="border p-3 rounded bg-light" id="modalMessage"
                        style="min-height: 150px; max-height: 400px; overflow-y: auto;"></div>
                </div>
                <div class="mb-3" id="modalAttachmentsRow">
                    <label class="form-label fw-bold">Ekler:</label>
                    <div id="modalAttachments"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script src="views/mail-sms/js/list.js"></script>