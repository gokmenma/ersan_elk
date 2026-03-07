<?php

use App\Helper\Security;
use App\Helper\Helper;
use App\Model\PersonelEvrakModel;

$EvrakModel = new PersonelEvrakModel();

// Personelin evraklarını getir
$evraklar = $EvrakModel->getByPersonel($id);
$stats = $EvrakModel->getStats($id);

// Dosya ikonunu belirle
function getFileIcon($mimeType)
{
    if (strpos($mimeType, 'pdf') !== false) {
        return ['file-text', 'text-danger'];
    } elseif (strpos($mimeType, 'image') !== false) {
        return ['image', 'text-info'];
    } elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) {
        return ['file-text', 'text-primary'];
    } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'sheet') !== false) {
        return ['file', 'text-success'];
    } else {
        return ['file', 'text-secondary'];
    }
}

// Dosya boyutunu formatla
function formatFileSize($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}


?>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-primary"><i data-feather="file" class="me-2 icon-sm"></i>Personel
                        Evrakları</h5>
                    <span class="badge bg-primary">
                        <?= $stats->toplam_evrak ?? 0 ?> Evrak
                    </span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btnOpenEvrakModal">
                    <i data-feather="upload" class="icon-xs"></i> Yeni Evrak Yükle
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 datatable w-100" id="tblEvraklar">
                        <thead class="table-light">
                            <tr>
                                <th>Evrak Adı</th>
                                <th>Tür</th>
                                <th>Boyut</th>
                                <th>Yükleme Tarihi</th>
                                <th>Yükleyen</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evraklar as $evrak): ?>
                                <?php
                                $enc_id = Security::encrypt($evrak->id);
                                $yuklemeTarihi = date('d.m.Y H:i', strtotime($evrak->yukleme_tarihi));
                                $icon = getFileIcon($evrak->dosya_tipi);
                                $dosyaYolu = 'uploads/personel_evraklar/' . $id . '/' . $evrak->dosya_adi;
                                ?>
                                <tr data-id="<?= $enc_id ?>">
                                    <td>
                                        <i data-feather="<?= $icon[0] ?>" class="<?= $icon[1] ?> me-2 icon-sm"></i>
                                        <span class="fw-medium">
                                            <?= htmlspecialchars($evrak->evrak_adi) ?>
                                        </span>
                                        <?php if (!empty($evrak->aciklama)): ?>
                                            <i data-feather="info" class="text-muted ms-1 icon-xs" data-bs-toggle="tooltip"
                                                title="<?= htmlspecialchars($evrak->aciklama) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary">
                                            <?= $evrakTurleri[$evrak->evrak_turu] ?? ucfirst($evrak->evrak_turu) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= formatFileSize($evrak->dosya_boyutu) ?>
                                    </td>
                                    <td>
                                        <?= $yuklemeTarihi ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($evrak->yukleyen_adi ?? '-') ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <!-- Görüntüle -->
                                        <button type="button" class="btn btn-sm btn-info btn-evrak-goruntule"
                                            data-id="<?= $enc_id ?>" data-dosya="<?= $dosyaYolu ?>"
                                            data-tip="<?= $evrak->dosya_tipi ?>"
                                            data-ad="<?= htmlspecialchars($evrak->evrak_adi) ?>" title="Görüntüle">
                                            <i data-feather="eye" class="icon-xs"></i>
                                        </button>
                                        <!-- İndir -->
                                        <a href="<?= $dosyaYolu ?>" class="btn btn-sm btn-success"
                                            download="<?= htmlspecialchars($evrak->orijinal_dosya_adi) ?>" title="İndir">
                                            <i data-feather="download" class="icon-xs"></i>
                                        </a>
                                        <!-- Sil -->
                                        <button type="button" class="btn btn-sm btn-danger btn-evrak-sil"
                                            data-id="<?= $enc_id ?>" data-ad="<?= htmlspecialchars($evrak->evrak_adi) ?>"
                                            title="Sil">
                                            <i data-feather="trash-2" class="icon-xs"></i>
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

<!-- Yeni Evrak Yükle Modal -->
<div class="modal fade" id="modalEvrakYukle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i data-feather="upload" class="me-2"></i>Yeni Evrak Yükle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEvrakYukle" enctype="multipart/form-data">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="evrak_yukle">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= \App\Helper\Form::FormFloatInput(
                            'text',
                            'evrak_adi',
                            '',
                            'Örn: İş Sözleşmesi',
                            'Evrak Adı *',
                            'file-text',
                            'form-control',
                            true
                        ) ?>
                    </div>

                    <div class="mb-3">
                        <?= \App\Helper\Form::FormSelect2(
                            'evrak_turu',
                            Helper::EVRAK_TURLERI,
                            '',
                            'Evrak Türü *',
                            'layers',
                            'key',
                            '',
                            'form-select select2',
                            true
                        ) ?>
                    </div>

                    <div class="mb-3">
                        <?= \App\Helper\Form::FormFileInput(
                            'evrak_dosyasi',
                            'Dosya Seç *',
                            'upload',
                            'form-control',
                            true
                        ) ?>
                        <small class="text-muted">
                            Desteklenen formatlar: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX (Max: 10MB)
                        </small>
                    </div>

                    <div class="mb-3">
                        <?= \App\Helper\Form::FormFloatTextarea(
                            'aciklama',
                            '',
                            'Evrak hakkında notlar...',
                            'Açıklama',
                            'edit-3',
                            'form-control',
                            false,
                            '80px',
                            2
                        ) ?>
                    </div>

                    <!-- Yükleme Progress -->
                    <div class="progress d-none" id="uploadProgress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: 0%">0%</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary" id="btnEvrakKaydet">
                        <i data-feather="upload" class="me-1"></i>Yükle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Evrak Görüntüleme Modal -->
<div class="modal fade" id="modalEvrakGoruntule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-file me-2"></i><span id="evrakGoruntuleBaslik">Evrak</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="evrakIcerik" class="text-center">
                    <!-- PDF veya resim buraya yüklenecek -->
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="evrakIndirLink" class="btn btn-success" download>
                    <i class="bx bx-download me-1"></i>İndir
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>