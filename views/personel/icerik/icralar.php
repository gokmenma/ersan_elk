<?php
use App\Helper\Security;
use App\Helper\Form;

// İstatistikler
$aktifIcra = 0;
foreach ($icralar as $i) {
    if ($i->durum === 'devam_ediyor') {
        $aktifIcra++;
    }
}
?>

<div class="row">
    <!-- İcra Dosyaları Bölümü -->
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-warning"><i class="bx bx-gavel me-2"></i>İcra Dosyaları</h5>
                    <span class="badge bg-warning"><?= $aktifIcra ?> Devam Eden</span>
                </div>
                <button type="button" class="btn btn-sm btn-warning" id="btnOpenIcraModal">
                    <i class="bx bx-plus"></i> İcra Dosyası Ekle
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>İcra Dairesi</th>
                                <th>Dosya No</th>
                                <th>Toplam Borç</th>
                                <th>Aylık Kesinti</th>
                                <th>Başlangıç</th>
                                <th>Durum</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($icralar)): ?>
                                <?php foreach ($icralar as $i): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($i->icra_dairesi) ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($i->dosya_no) ?></td>
                                        <td><?= number_format($i->toplam_borc, 2, ',', '.') ?> TL</td>
                                        <td><?= number_format($i->aylik_kesinti_tutari, 2, ',', '.') ?> TL</td>
                                        <td><?= date('d.m.Y', strtotime($i->baslangic_tarihi)) ?></td>
                                        <td>
                                            <?php if ($i->durum == 'devam_ediyor'): ?>
                                                <span class="badge bg-success">Devam Ediyor</span>
                                            <?php elseif ($i->durum == 'bitti'): ?>
                                                <span class="badge bg-secondary">Bitti</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Durduruldu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-icra-sil"
                                                data-id="<?= $i->id ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <span class="text-muted">İcra dosyası bulunamadı.</span>
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

<!-- İcra Dosyası Ekle Modal -->
<div class="modal fade" id="modalPersonelIcraEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bx bx-gavel me-2"></i>Yeni İcra Dosyası Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelIcraEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "icra_dairesi", "", "İcra dairesi adı", "İcra Dairesi", "home", "form-control", true, null, "off", false, 'id="icra_dairesi"') ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "dosya_no", "", "Dosya numarasını giriniz", "Dosya No", "file-text", "form-control", true, null, "off", false, 'id="icra_dosya_no"') ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "toplam_borc", "", "0,00", "Toplam Borç", "dollar-sign", "form-control", true, null, "off", false, 'step="0.01" id="icra_toplam_borc"') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "aylik_kesinti_tutari", "", "0,00", "Aylık Kesinti", "minus-circle", "form-control", true, null, "off", false, 'step="0.01" id="icra_aylik_kesinti"') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "baslangic_tarihi", date('Y-m-d'), "", "Başlangıç Tarihi", "calendar", "form-control flatpickr", false, null, "off", false, 'id="icra_baslangic"') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning" id="btnPersonelIcraKaydet">
                        <i class="bx bx-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    if (typeof flatpickr !== 'undefined') {
        $(".flatpickr").flatpickr({
            dateFormat: "Y-m-d",
            locale: "tr"
        });
    }
</script>
