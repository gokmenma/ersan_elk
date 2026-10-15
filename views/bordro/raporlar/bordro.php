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
<div class="card mb-3" id="muhasebeKarsilastirmaCard">
<div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2"><div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle"><i class="bx bx-git-compare font-size-20"></i></div><div><h5 class="font-size-14 fw-bold mb-1">Muhasebe Excel Karşılaştırması</h5><p class="font-size-12 text-muted mb-0">Muhasebeden gelen .xlsx bordroyu seçili dönem snapshot'ıyla karşılaştırır; kayıtları değiştirmez.</p></div></div>
</div>
<div class="card-body pt-0">
    <form id="muhasebeKarsilastirmaForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $e(Security::csrf()) ?>">
        <input type="hidden" name="donem" value="<?= $e($token) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-8"><?= Form::FormFileInput('muhasebe_excel', 'Muhasebe bordro Exceli (.xlsx)', 'bx bx-spreadsheet', 'form-control', true, 'accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"', 'muhasebeExcel') ?></div>
            <div class="col-md-4"><button type="submit" id="btnMuhasebeKarsilastir" class="btn btn-info text-white w-100 top-action-btn shadow-sm" style="height:48px"><i class="bx bx-search-alt me-1"></i>Exceli Analiz Et ve Karşılaştır</button></div>
        </div>
    </form>
    <div id="muhasebeKarsilastirmaHata" class="alert alert-danger mt-3 mb-0 d-none"></div>
    <div id="muhasebeKarsilastirmaSonuc" class="d-none mt-3">
        <div class="row g-2 mb-3" id="muhasebeKarsilastirmaOzet"></div>
        <div class="d-flex gap-2 flex-wrap mb-2" id="muhasebeDurumFiltreleri"></div>
        <div class="table-responsive"><table id="muhasebeKarsilastirmaTable" class="table table-bordered table-hover align-middle w-100 mb-0">
            <thead><tr><th>Personel</th><th>T.C.</th><th>Durum</th><th>Fark Sayısı</th><th>Açıklama</th><th class="text-center">Detay</th></tr></thead><tbody></tbody>
        </table></div>
    </div>
</div></div>
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

    let muhasebeTable = null;
    let karsilastirmaData = null;
    let muhasebeStatusFilter = null;
    const durumBilgisi = {
        farkli: ['Farklı', 'danger'], eslesen: ['Eşleşen', 'success'],
        yalniz_muhasebe: ['Yalnız Muhasebede', 'warning'], yalniz_sistem: ['Yalnız Sistemde', 'info']
    };
    const esc = value => $('<div>').text(value == null ? '' : String(value)).html();
    const formatValue = (value, type) => type === 'para'
        ? (Number(value || 0) / 100).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺'
        : Number(value || 0).toLocaleString('tr-TR');
    function renderDetay(row) {
        if (!row.farklar.length) return '<div class="p-3 text-muted font-size-12">Alan farkı bulunmadı.</div>';
        return `<div class="p-2"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Alan</th><th class="text-end">Muhasebe</th><th class="text-end">Sistem</th><th class="text-end">Fark</th></tr></thead><tbody>${row.farklar.map(f => `<tr><td>${esc(f.etiket)}</td><td class="text-end">${esc(formatValue(f.muhasebe, f.tur))}</td><td class="text-end">${esc(formatValue(f.sistem, f.tur))}</td><td class="text-end fw-semibold ${f.fark ? 'text-danger' : ''}">${esc(formatValue(f.fark, f.tur))}</td></tr>`).join('')}</tbody></table></div>`;
    }
    function renderKarsilastirma(data) {
        karsilastirmaData = data;
        if (muhasebeStatusFilter) {
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => fn !== muhasebeStatusFilter);
            muhasebeStatusFilter = null;
        }
        const cards = [
            ['toplam', 'Toplam Sonuç', 'primary'], ['farkli', 'Farklı', 'danger'],
            ['yalniz_muhasebe', 'Yalnız Muhasebede', 'warning'], ['yalniz_sistem', 'Yalnız Sistemde', 'info'], ['eslesen', 'Eşleşen', 'success']
        ];
        $('#muhasebeKarsilastirmaOzet').html(cards.map(([key, label, color]) => `<div class="col-6 col-lg"><div class="border rounded-3 p-2 h-100 bg-${color}-subtle"><div class="font-size-11 text-muted">${label}</div><div class="font-size-18 fw-bold text-${color}">${data.sayilar[key]}</div></div></div>`).join(''));
        $('#muhasebeDurumFiltreleri').html(`<button type="button" class="btn btn-sm btn-dark muhasebe-filter active" data-status="">Tümü</button>${cards.slice(1).map(([key, label, color]) => `<button type="button" class="btn btn-sm btn-subtle-${color} muhasebe-filter" data-status="${key}">${label} (${data.sayilar[key]})</button>`).join('')}`);
        if (muhasebeTable) { muhasebeTable.destroy(); $('#muhasebeKarsilastirmaTable tbody').empty(); }
        data.sonuclar.forEach((row, index) => {
            const [label, color] = durumBilgisi[row.durum];
            $('#muhasebeKarsilastirmaTable tbody').append(`<tr data-index="${index}" data-status="${row.durum}"><td class="fw-semibold">${esc(row.personel)}</td><td>${esc(row.tc || '—')}</td><td><span class="badge bg-${color}-subtle text-${color} border border-${color}-subtle rounded-pill">${label}</span></td><td class="text-center">${row.fark_sayisi}</td><td class="font-size-12">${esc(row.aciklama)}</td><td class="text-center"><button type="button" class="table-action-btn btn-subtle-primary btn-karsilastirma-detay" aria-label="Fark detayını aç"><i class="bx bx-chevron-down"></i></button></td></tr>`);
        });
        muhasebeTable = $('#muhasebeKarsilastirmaTable').DataTable(applyLengthStateSave({...getDatatableOptions(), responsive: false, pageLength: 25, order: [[2, 'asc'], [0, 'asc']], columnDefs: [{targets: 5, orderable: false, searchable: false}]}));
        $('#muhasebeKarsilastirmaSonuc').removeClass('d-none');
    }
    $('#muhasebeKarsilastirmaForm').on('submit', function (event) {
        event.preventDefault();
        const button = $('#btnMuhasebeKarsilastir');
        const original = button.html();
        $('#muhasebeKarsilastirmaHata').addClass('d-none').text('');
        button.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Analiz ediliyor...');
        $.ajax({url: 'views/bordro/raporlar/bordro-karsilastir.php', method: 'POST', data: new FormData(this), processData: false, contentType: false, dataType: 'json'})
            .done(response => { if (response.status !== 'success') throw new Error(response.message || 'Karşılaştırma tamamlanamadı.'); renderKarsilastirma(response.data); })
            .fail(xhr => { const message = xhr.responseJSON?.message || 'Excel karşılaştırması tamamlanamadı.'; $('#muhasebeKarsilastirmaHata').removeClass('d-none').text(message); })
            .always(() => button.prop('disabled', false).html(original));
    });
    $(document).on('click', '.muhasebe-filter', function () {
        $('.muhasebe-filter').removeClass('active'); $(this).addClass('active');
        const status = this.dataset.status;
        if (muhasebeStatusFilter) $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => fn !== muhasebeStatusFilter);
        muhasebeStatusFilter = function (settings, data, dataIndex) { return settings.nTable.id !== 'muhasebeKarsilastirmaTable' || !status || $(settings.aoData[dataIndex].nTr).data('status') === status; };
        $.fn.dataTable.ext.search.push(muhasebeStatusFilter); muhasebeTable.draw();
    });
    $('#muhasebeKarsilastirmaTable tbody').on('click', '.btn-karsilastirma-detay', function () {
        const tr = $(this).closest('tr'); const dtRow = muhasebeTable.row(tr); const index = Number(tr.data('index'));
        if (dtRow.child.isShown()) { dtRow.child.hide(); $(this).find('i').attr('class', 'bx bx-chevron-down'); }
        else { dtRow.child(renderDetay(karsilastirmaData.sonuclar[index]), 'bg-light').show(); $(this).find('i').attr('class', 'bx bx-chevron-up'); }
    });
});
</script>
