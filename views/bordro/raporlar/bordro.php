<?php
use App\Helper\Form;
use App\Helper\Security;
use App\Model\BordroDonemModel;
use App\Model\BordroPusulaModel;
use App\Service\Gate;

if (!Gate::allows('bordro/raporlar')) { http_response_code(403); return; }
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$donemModel = new BordroDonemModel();
$selectedYil = (int) ($_GET['yil'] ?? date('Y'));
$selectedDonemId = (int) ($_GET['donem'] ?? (isset($_GET['yil']) ? 0 : ($_SESSION['selectedDonemId'] ?? 0)));
// Firma sınırı tüm dönem seçeneklerinde ve rapor modelinde uygulanır.
$tumDonemler = $donemModel->getAllDonemsForFilter();
$yillar = []; $donemler = []; $selectedDonem = null;
foreach ($tumDonemler as $d) {
    $y = (int) date('Y', strtotime($d->baslangic_tarihi));
    $yillar[$y] = $y;
    if ((int) $d->id === $selectedDonemId) { $selectedDonem = $d; $selectedYil = $y; }
}
foreach ($tumDonemler as $d) {
    if ((int) date('Y', strtotime($d->baslangic_tarihi)) === $selectedYil) $donemler[$d->id] = $d->donem_adi;
}
if (!$selectedDonem && !$selectedDonemId && $donemler) {
    $selectedDonemId = (int) array_key_first($donemler);
    foreach ($tumDonemler as $d) if ((int) $d->id === $selectedDonemId) $selectedDonem = $d;
}
$rapor = $selectedDonem ? (new BordroPusulaModel())->rapor((int) $_SESSION['firma_id'], $selectedDonemId) : null;
$kayitlar = $rapor['kayitlar'] ?? [];
$hazir = count(array_filter($kayitlar, static fn($k) => $k['pusula'] !== null));
$token = $selectedDonem ? Security::encrypt($selectedDonemId) : '';
$pdfUrl = 'views/bordro/bordro-yazdir.php?donem=' . $token;
$maintitle = 'Bordro'; $subtitle = 'Raporlar'; $title = 'Resmî Ücret Hesap Pusulaları';
?>
<div class="container-fluid">
<?php include 'layouts/breadcrumb.php'; ?>
<div class="row align-items-center mb-3">
    <div class="col-md-6 d-flex align-items-center gap-2">
        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="bx bx-receipt font-size-24"></i></div>
        <div><h4 class="font-size-16 fw-bold text-dark mb-1">Resmî Ücret Hesap Pusulaları</h4><p class="font-size-12 text-muted mb-0">Her personel için ayrı A4 sayfa; tüm dönem tek PDF.</p></div>
    </div>
    <div class="col-md-6 personel-action-toolbar d-flex justify-content-end gap-2 flex-wrap mt-2 mt-md-0">
    <?php if ($hazir > 0 && $hazir === count($kayitlar)): ?>
        <a href="<?= $e($pdfUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary top-action-btn shadow-sm text-white"><i class="bx bx-printer me-1"></i>Tümünü Yazdır (<?= $hazir ?>)</a>
        <a href="<?= $e($pdfUrl . '&indir=1') ?>" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm"><i class="bx bxs-file-pdf me-1"></i>Toplu PDF İndir</a>
    <?php endif; ?>
        <a href="index?p=bordro/raporlar&amp;donem=<?= $selectedDonemId ?>" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">Raporlara Dön</a>
    </div>
</div>
<div class="card"><div class="card-body"><div class="row g-3 align-items-center">
<div class="col-md-3"><?= Form::FormSelect2(name: 'yilSelectBordro', options: $yillar, selectedValue: $selectedYil, label: 'Yıl', icon: 'calendar', class: 'form-select select2') ?></div>
<div class="col-md-5"><?= Form::FormSelect2(name: 'donemSelectBordro', options: $donemler, selectedValue: $selectedDonemId, label: 'Dönem', icon: 'calendar', class: 'form-select select2') ?></div>
<div class="col-md-4 text-muted font-size-12"><?= count($kayitlar) ?> personel · <?= $hazir ?> çıktı hazır · <?= count($kayitlar) - $hazir ?> hesaplanmadı / kontrol gerekli</div>
</div></div></div>
<div class="alert alert-info font-size-12">Resmî banka tutarları ve yasal kesintiler kayıtlı bordrodan alınır. Dönemde işten ayrılan personel dahildir. Muhasebe kaydıyla farklılık varsa önce mutabakat yapılmalıdır. Dönem kaydında bulunmayan alanlar “—” gösterilir.</div>
<?php if ($hazir < count($kayitlar)): ?><div class="alert alert-warning">Toplu PDF için tüm personelin geçerli hesaplanmış bordrosu bulunmalıdır. Aşağıdaki uyarılı kayıtları kontrol edin; hazır kayıtları tek tek indirebilirsiniz.</div><?php endif; ?>
<div class="card" id="bordroPusulaListCard">
<div class="card-header bg-transparent border-0 d-flex align-items-center gap-2"><div class="p-2 bg-primary-subtle text-primary rounded-3"><i class="bx bx-list-ul font-size-20"></i></div><div><h5 class="font-size-14 fw-bold mb-1">Personel Pusulaları</h5><p class="font-size-12 text-muted mb-0"><?= $e($selectedDonem->donem_adi ?? 'Dönem seçiniz') ?></p></div></div>
<div class="card-body"><div class="table-responsive">
<table id="bordroListesiTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
<thead><tr><th data-filter="number">#</th><th data-filter="string">Ad Soyad</th><th data-filter="select">Departman</th><th data-filter="string">Görev</th><th data-filter="number">Resmî Net / Banka</th><th data-filter="select">Durum</th><th data-filter="none" class="text-center">İşlemler</th></tr></thead>
<tbody>
<?php foreach ($kayitlar as $i => $item): $row = $item['kayit']; $p = $item['pusula']; $url = $pdfUrl . '&id=' . Security::encrypt($row->id); ?>
<tr<?= $p ? ' data-href="' . $e($url) . '"' : '' ?>>
<td><?= $i + 1 ?></td><td><?= $e($row->adi_soyadi) ?></td><td><?= $e($p['departman'] ?? $row->departman ?? '') ?></td><td><?= $e($p['gorev'] ?? $row->gorev ?? '') ?></td>
<td class="text-end" data-order="<?= $p ? $p['resmi']['banka_net_kurus'] : -1 ?>"><?= $p ? number_format($p['resmi']['banka_net_kurus'] / 100, 2, ',', '.') . ' ₺' : '—' ?></td>
<td><?php if ($p): ?><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Hazır</span><?php else: ?><span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="<?= $e($item['hata']) ?>">Hesaplanmadı / Kontrol gerekli</span><div class="small text-muted"><?= $e($item['hata']) ?></div><?php endif; ?></td>
<td class="text-center"><div class="action-btn-group"><?php if ($p): ?><a href="<?= $e($url) ?>" target="_blank" rel="noopener" class="table-action-btn btn-subtle-primary" title="Pusulayı Görüntüle" aria-label="Pusulayı Görüntüle"><i class="bx bx-show"></i></a><a href="<?= $e($url . '&indir=1') ?>" class="table-action-btn btn-subtle-success" title="PDF İndir" aria-label="PDF İndir"><i class="bx bx-download"></i></a><?php endif; ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></div></div>
<style>#bordroListesiTable tr[data-href] td:not(:last-child) { cursor: pointer !important; }</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#yilSelectBordro, #donemSelectBordro').select2({width: '100%'});
    $('#yilSelectBordro').on('change', function () { window.location.href = 'index?p=bordro/raporlar/bordro&yil=' + encodeURIComponent(this.value); });
    $('#donemSelectBordro').on('change', function () { window.location.href = 'index?p=bordro/raporlar/bordro&donem=' + encodeURIComponent(this.value); });
    $('#bordroListesiTable').DataTable(applyLengthStateSave({...getDatatableOptions(), responsive: false, order: [[1, 'asc']], columnDefs: [{targets: 6, width: '120px', orderable: false, searchable: false}]}));
    $('#bordroListesiTable tbody').on('click', 'tr[data-href] td:not(:last-child)', function (event) {
        if ($(event.target).closest('a,button,input,.custom-context-menu').length) return;
        window.open(this.parentElement.dataset.href, '_blank', 'noopener');
    });
});
</script>
