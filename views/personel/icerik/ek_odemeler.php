<?php
use App\Helper\Security;
use App\Helper\Form;

// İstatistikler
$toplamEkOdeme = 0;
foreach ($ek_odemeler as $k) {
    $toplamEkOdeme += $k->tutar;
}

$ek_odeme_turleri = [
    '' => "Seçiniz",
    'prim' => 'Prim',
    'mesai' => 'Fazla Mesai',
    'ikramiye' => 'İkramiye',
    'yol' => 'Yol Yardımı',
    'yemek' => 'Yemek Yardımı',
    'diger' => 'Diğer'
];
?>

<div class="row">
    <!-- Ek Ödemeler Bölümü -->
    <div class="col-12 mb-4">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-success"><i class="bx bx-plus-circle me-2"></i>Personel Ek Ödemeleri</h5>
                    <span class="badge bg-success">Toplam: <?= number_format($toplamEkOdeme, 2, ',', '.') ?> TL</span>
                </div>
                <button type="button" class="btn btn-sm btn-success" id="btnOpenEkOdemeModal">
                    <i class="bx bx-plus"></i> Yeni Ek Ödeme Ekle
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
                                <th>Tarih</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ek_odemeler)): ?>
                                <?php foreach ($ek_odemeler as $k): ?>
                                    <?php $enc_id = Security::encrypt($k->id); ?>
                                    <tr data-id="<?= $enc_id ?>">
                                        <td><?= App\Helper\Helper::getDonemAdi($k->donem_id) ?></td>
                                        <td>
                                            <span class="badge bg-soft-success text-success">
                                                <?= isset($ek_odeme_turleri[$k->tur]) ? $ek_odeme_turleri[$k->tur] : ucfirst($k->tur) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?= number_format($k->tutar, 2, ',', '.') ?> TL</td>
                                        <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($k->created_at)) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-ek-odeme-sil"
                                                data-id="<?= $k->id ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <span class="text-muted">Kayıtlı ek ödeme bulunamadı.</span>
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

<!-- Ek Ödeme Ekle Modal -->
<div class="modal fade" id="modalPersonelEkOdemeEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-plus-circle me-2"></i>Yeni Ek Ödeme Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelEkOdemeEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "ek_odeme_donem",
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
                            name: "ek_odeme_tur",
                            options: $ek_odeme_turleri, 
                            selectedValue: '',  
                            label:"Ek Ödeme Türü", 
                            icon: "list", 
                            valueField: '', 
                            textField: '', 
                            required: true
                            ) ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("number", "tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" id="ek_odeme_tutar"') ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "message-square", "form-control", false, null, "off", false, 'id="ek_odeme_aciklama"') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-success" id="btnPersonelEkOdemeKaydet">
                        <i class="bx bx-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
