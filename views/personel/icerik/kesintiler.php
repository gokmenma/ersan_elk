<?php
use App\Helper\Security;
use App\Helper\Form;

// İstatistikler
$toplamKesinti = 0;
foreach ($kesintiler as $k) {
    $toplamKesinti += $k->tutar;
}

$kesinti_turleri = [
    '' => "Seçiniz",
    'icra' => 'İcra',
    'avans' => 'Avans',
    'nafaka' => 'Nafaka',
    'diger' => 'Diğer'
];
?>

<div class="row">
    <!-- Kesintiler Bölümü -->
    <div class="col-12 mb-4">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-primary"><i class="bx bx-minus-circle me-2"></i>Personel Kesintileri</h5>
                    <span class="badge bg-danger">Toplam: <?= number_format($toplamKesinti, 2, ',', '.') ?> TL</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btnOpenKesintiModal">
                    <i class="bx bx-plus"></i> Yeni Kesinti Ekle
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Dönem</th>
                                <th>Tür</th>
                                <th>Tutar</th>
                                <th>Açıklama</th>
                                <th>İcra Dosyası</th>
                                <th>Tarih</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($kesintiler)): ?>
                                <?php foreach ($kesintiler as $k): ?>
                                    <?php $enc_id = Security::encrypt($k->id); ?>
                                    <tr data-id="<?= $enc_id ?>">
                                        <td><?= App\Helper\Helper::getDonemAdi($k->donem_id) ?></td>
                                        <td>
                                            <span class="badge bg-soft-info text-info">
                                                <?= ucfirst($k->tur) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?= number_format($k->tutar, 2, ',', '.') ?> TL</td>
                                        <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                        <td>
                                            <?php if ($k->tur == 'icra' && $k->dosya_no): ?>
                                                <small><?= htmlspecialchars($k->icra_dairesi . ' - ' . $k->dosya_no) ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($k->created_at)) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-kesinti-sil"
                                                data-id="<?= $k->id ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <span class="text-muted">Kayıtlı kesinti bulunamadı.</span>
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

<!-- Kesinti Ekle Modal -->
<div class="modal fade" id="modalPersonelKesintiEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-minus-circle me-2"></i>Yeni Kesinti Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelKesintiEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "kesinti_donem",
                            options: $acik_donemler, 
                            selectedValue: date('Y-m'),  
                            label:"Dönem Seçin", 
                            icon: "calendar", 
                            valueField: '', 
                            textField: '', 
                            required: true, 
                            ) ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "kesinti_tur",
                            options: $kesinti_turleri, 
                            selectedValue: '',  
                            label:"Kesinti Türü", 
                            icon: "list", 
                            valueField: '', 
                            textField: '', 
                            required: true
                            ) ?>
                    </div>

                    <div class="mb-3 d-none" id="div_icra_secimi">
                        <div class="form-floating form-floating-custom">
                            <select class="form-select select2" id="kesinti_icra_id" name="icra_id" style="width: 100%">
                                <option value="">Dosya seçiniz...</option>
                                <!-- AJAX ile doldurulacak -->
                            </select>
                            <label for="kesinti_icra_id">İcra Dosyası Seçin</label>
                            <div class="form-floating-icon">
                                <i data-feather="file-text"></i>
                            </div>
                        </div>
                        <div class="form-text text-warning mt-2" id="no_icra_warning" style="display:none;">
                            <i class="bx bx-info-circle me-1"></i> Aktif icra dosyası bulunamadı. Lütfen önce icra dosyası ekleyin.
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("number", "tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" id="kesinti_tutar"') ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "message-square", "form-control", false, null, "off", false, 'id="kesinti_aciklama"') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" id="btnPersonelKesintiKaydet">
                        <i class="bx bx-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
