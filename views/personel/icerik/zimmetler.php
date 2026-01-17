<?php

use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasModel;

$ZimmetModel = new DemirbasZimmetModel();
$DemirbasModel = new DemirbasModel();

// Personelin zimmetlerini getir
$zimmetler = $ZimmetModel->getByPersonel($id);
$demirbaslar = $DemirbasModel->getInStock();

// İstatistikler
$aktifZimmet = 0;
$iadeEdilen = 0;
foreach ($zimmetler as $z) {
    if ($z->durum === 'teslim') {
        $aktifZimmet++;
    } else {
        $iadeEdilen++;
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-primary"><i class="bx bx-devices me-2"></i>Zimmet İşlemleri</h5>
                    <span class="badge bg-warning"><?= $aktifZimmet ?> Aktif</span>
                    <span class="badge bg-success"><?= $iadeEdilen ?> İade</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btnOpenZimmetModal">
                    <i class="bx bx-plus"></i> Yeni Zimmet Ver
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th>Demirbaş Adı</th>
                                <th>Marka/Model</th>
                                <th class="text-center">Miktar</th>
                                <th>Teslim Tarihi</th>
                                <th>İade Tarihi</th>
                                <th class="text-center">Durum</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($zimmetler)): ?>
                                <?php foreach ($zimmetler as $zimmet): ?>
                                    <?php
                                    $enc_id = Security::encrypt($zimmet->id);
                                    $teslimTarihi = date('d.m.Y', strtotime($zimmet->teslim_tarihi));
                                    $iadeTarihi = $zimmet->iade_tarihi ? date('d.m.Y', strtotime($zimmet->iade_tarihi)) : '-';
                                    ?>
                                    <tr data-id="<?= $enc_id ?>">
                                        <td>
                                            <span class="badge bg-soft-primary text-primary">
                                                <?= htmlspecialchars($zimmet->kategori_adi ?? 'Kategorisiz') ?>
                                            </span>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($zimmet->demirbas_adi ?? '-') ?></td>
                                        <td><?= htmlspecialchars(($zimmet->marka ?? '') . ' ' . ($zimmet->model ?? '')) ?></td>
                                        <td class="text-center"><?= $zimmet->teslim_miktar ?? 1 ?></td>
                                        <td><?= $teslimTarihi ?></td>
                                        <td><?= $iadeTarihi ?></td>
                                        <td class="text-center">
                                            <?php if ($zimmet->durum === 'teslim'): ?>
                                                <span class="badge bg-warning">Zimmetli</span>
                                            <?php elseif ($zimmet->durum === 'iade'): ?>
                                                <span class="badge bg-success">İade Edildi</span>
                                            <?php elseif ($zimmet->durum === 'kayip'): ?>
                                                <span class="badge bg-danger">Kayıp</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($zimmet->durum) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <?php if ($zimmet->durum === 'teslim'): ?>
                                                <button type="button" class="btn btn-sm btn-info btn-personel-zimmet-iade"
                                                    data-id="<?= $enc_id ?>"
                                                    data-demirbas="<?= htmlspecialchars($zimmet->demirbas_adi ?? '') ?>"
                                                    data-miktar="<?= $zimmet->teslim_miktar ?? 1 ?>" title="İade Al">
                                                    <i class="bx bx-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-zimmet-sil"
                                                data-id="<?= $enc_id ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bx bx-package display-6 text-muted d-block mb-2"></i>
                                        <span class="text-muted">Bu personele henüz zimmet verilmemiş.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Zimmet Ekle Modal -->
<div class="modal fade" id="modalPersonelZimmetEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bx bx-transfer me-2"></i>Personele Zimmet Ver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelZimmetEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="alert alert-info p-2 mb-3">
                        <small><i class="bx bx-info-circle me-1"></i> Zimmetlenen miktar stoktan düşülecektir.</small>
                    </div>

                    <div class="mb-3">
                        <label for="personel_demirbas_id" class="form-label">Demirbaş Seçin <span
                                class="text-danger">*</span></label>
                        <select class="form-select" id="personel_demirbas_id" name="demirbas_id" required>
                            <option value="">Demirbaş seçiniz...</option>
                            <?php foreach ($demirbaslar as $d): ?>
                                <option value="<?= $d->id ?>" data-kalan="<?= $d->kalan_miktar ?? 1 ?>">
                                    <?= ($d->demirbas_no ?? '-') . ' - ' . $d->demirbas_adi . ' (' . ($d->kategori_adi ?? '-') . ') - Kalan: ' . ($d->kalan_miktar ?? 1) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="personel_teslim_miktar" class="form-label">Teslim Miktarı <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="personel_teslim_miktar"
                                    name="teslim_miktar" value="1" min="1" required>
                                <span class="input-group-text">
                                    Kalan: <span id="personelKalanMiktar" class="ms-1 fw-bold">-</span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="personel_teslim_tarihi" class="form-label">Teslim Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr" id="personel_teslim_tarihi"
                                name="teslim_tarihi" value="<?= date('d.m.Y') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="personel_zimmet_aciklama" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="personel_zimmet_aciklama" name="aciklama" rows="2"
                            placeholder="Notlar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning" id="btnPersonelZimmetKaydet">
                        <i class="bx bx-transfer me-1"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İade Modal -->
<div class="modal fade" id="modalPersonelIade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-undo me-2"></i>Zimmet İade Al</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelIade">
                <input type="hidden" name="zimmet_id" id="personel_iade_zimmet_id">
                <div class="modal-body">
                    <div class="alert alert-secondary mb-3">
                        <strong>Demirbaş:</strong> <span id="personel_iade_demirbas_adi">-</span><br>
                        <strong>Teslim Miktarı:</strong> <span id="personel_iade_miktar_goster">-</span>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="personel_iade_miktar" class="form-label">İade Miktarı <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="personel_iade_miktar" name="iade_miktar"
                                value="1" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="personel_iade_tarihi" class="form-label">İade Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr" id="personel_iade_tarihi"
                                name="iade_tarihi" value="<?= date('d.m.Y') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="personel_iade_aciklama" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="personel_iade_aciklama" name="iade_aciklama" rows="2"
                            placeholder="İade notu..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-info" id="btnPersonelIadeKaydet">
                        <i class="bx bx-undo me-1"></i>İade Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>