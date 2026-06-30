<?php
use App\Helper\Security;

if (!isset($id) || empty($id)) {
    $id = Security::decrypt($_GET['id'] ?? 0);
}

$vergiMatrahlari = $vergiMatrahlari ?? [];
$sonKumulatif = !empty($vergiMatrahlari) ? end($vergiMatrahlari)['yeni_kumulatif'] : 0;
$toplamGelirVergisi = 0;
$toplamSgkIsci = 0;
$toplamIssizlik = 0;
$toplamDamga = 0;
foreach ($vergiMatrahlari as $vm) {
    $toplamGelirVergisi += $vm['gelir_vergisi'];
    $toplamSgkIsci += $vm['sgk_isci'];
    $toplamIssizlik += $vm['issizlik_isci'];
    $toplamDamga += $vm['damga_vergisi'];
}
?>

<div class="row">
    <div class="col-12">
        <div class="card border border-light shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                    <div class="icra-header-icon me-3">
                        <i data-feather="trending-up" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">Vergi Matrahları</h5>
                        <p class="text-muted mb-0 small">Ay bazında gelir vergisi matrahı ve kümülatif tutar geçmişi</p>
                    </div>
                </div>
            </div>

            <div class="card-body border-bottom py-3">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white border-start border-4 border-primary shadow-sm rounded-2">
                            <small class="text-muted d-block">Güncel Kümülatif Matrah</small>
                            <h5 class="mb-0 fw-bold text-primary"><?= number_format($sonKumulatif, 2, ',', '.') ?> ₺</h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white border-start border-4 border-danger shadow-sm rounded-2">
                            <small class="text-muted d-block">Toplam Gelir Vergisi</small>
                            <h5 class="mb-0 fw-bold text-danger"><?= number_format($toplamGelirVergisi, 2, ',', '.') ?> ₺</h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white border-start border-4 border-warning shadow-sm rounded-2">
                            <small class="text-muted d-block">Toplam SGK + İşsizlik (İşçi)</small>
                            <h5 class="mb-0 fw-bold text-warning"><?= number_format($toplamSgkIsci + $toplamIssizlik, 2, ',', '.') ?> ₺</h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white border-start border-4 border-info shadow-sm rounded-2">
                            <small class="text-muted d-block">Toplam Damga Vergisi</small>
                            <h5 class="mb-0 fw-bold text-info"><?= number_format($toplamDamga, 2, ',', '.') ?> ₺</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 w-100 align-middle">
                        <thead>
                            <tr class="bg-light bg-opacity-50 border-bottom">
                                <th class="text-muted small fw-bold text-uppercase">Dönem</th>
                                <th class="text-end text-muted small fw-bold text-uppercase">Bu Ayki Matrah</th>
                                <th class="text-end text-muted small fw-bold text-uppercase">Önceki Kümülatif</th>
                                <th class="text-end text-muted small fw-bold text-uppercase text-primary">Yeni Kümülatif</th>
                                <th class="text-end text-muted small fw-bold text-uppercase">SGK İşçi</th>
                                <th class="text-end text-muted small fw-bold text-uppercase">İşsizlik</th>
                                <th class="text-end text-muted small fw-bold text-uppercase text-danger">Gelir Vergisi</th>
                                <th class="text-end text-muted small fw-bold text-uppercase">Damga Vergisi</th>
                                <th class="text-muted small fw-bold text-uppercase">Hesaplama Tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vergiMatrahlari)): ?>
                                <?php foreach ($vergiMatrahlari as $vm): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <?= htmlspecialchars($vm['donem_adi'] ?: ($vm['baslangic_tarihi'] ? date('m/Y', strtotime($vm['baslangic_tarihi'])) : '-')) ?>
                                        </td>
                                        <td class="text-end"><?= number_format($vm['ay_matrahi'], 2, ',', '.') ?> ₺</td>
                                        <td class="text-end text-muted"><?= number_format($vm['onceki_kumulatif'], 2, ',', '.') ?> ₺</td>
                                        <td class="text-end fw-bold text-primary"><?= number_format($vm['yeni_kumulatif'], 2, ',', '.') ?> ₺</td>
                                        <td class="text-end"><?= $vm['sgk_isci'] > 0 ? number_format($vm['sgk_isci'], 2, ',', '.') . ' ₺' : '-' ?></td>
                                        <td class="text-end"><?= $vm['issizlik_isci'] > 0 ? number_format($vm['issizlik_isci'], 2, ',', '.') . ' ₺' : '-' ?></td>
                                        <td class="text-end text-danger fw-bold"><?= $vm['gelir_vergisi'] > 0 ? number_format($vm['gelir_vergisi'], 2, ',', '.') . ' ₺' : '-' ?></td>
                                        <td class="text-end"><?= $vm['damga_vergisi'] > 0 ? number_format($vm['damga_vergisi'], 2, ',', '.') . ' ₺' : '-' ?></td>
                                        <td class="text-muted small">
                                            <?= $vm['hesaplama_tarihi'] ? date('d.m.Y H:i', strtotime($vm['hesaplama_tarihi'])) : '-' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bx bx-info-circle fs-4 d-block mb-1 opacity-50"></i>
                                        Bu personel için henüz hesaplanmış bordro kaydı bulunmuyor.
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

<script>
    $(document).ready(function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
            setTimeout(function () { feather.replace(); }, 100);
        }
    });
</script>
