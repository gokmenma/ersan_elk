<?php
use App\Model\PuantajModel;
use App\Helper\Helper;
use App\Helper\Date;

$PuantajModel = new PuantajModel();
$ekip_no = $personel->ekip_no ?? '';
$ise_giris = $personel->ise_giris_tarihi ?? '';
$isten_cikis = $personel->isten_cikis_tarihi ?? date('Y-m-d');

$isler = [];
if ($ekip_no) {
    $sql = "SELECT * FROM yapilan_isler WHERE ekip_kodu = ? AND tarih >= ? AND tarih <= ? ORDER BY tarih DESC";
    $stmt = $PuantajModel->getDb()->prepare($sql);
    $stmt->execute([$ekip_no, $ise_giris, $isten_cikis]);
    $isler = $stmt->fetchAll(PDO::FETCH_OBJ);
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h5 class="card-title">Puantaj / İş Takip (<?php echo count($isler); ?> Kayıt)</h5>
        <p class="text-muted">Ekip No: <?php echo htmlspecialchars($ekip_no); ?> | Tarih Aralığı: <?php echo Date::dmY($ise_giris); ?> - <?php echo Date::dmY($isten_cikis); ?></p>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-success" id="exportExcelPuantaj">
            <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
        </button>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover mb-0 datatable w-100" id="puantajTable">
        <thead class="table-light">
            <tr>
                <th>Tarih</th>
                <th>İş Emri No</th>
                <th>İş Tipi</th>
                <th>Açıklama</th>
                <th>Süre/Miktar</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($isler as $is): ?>
                <tr>
                    <td><?php echo Date::dmY($is->tarih); ?></td>
                    <td><?php echo htmlspecialchars($is->is_emri_no ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($is->is_emri_tipi ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($is->aciklama ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($is->miktar ?? '-'); ?></td>
                    <td>
                        <?php if (($is->onay_durumu ?? '') == 'Onaylandı'): ?>
                            <span class="badge bg-success">Onaylandı</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Beklemede</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    $('#exportExcelPuantaj').on('click', function() {
        const personelId = <?php echo $id; ?>;
        window.location.href = `views/personel/api.php?action=export-puantaj&id=${personelId}`;
    });
});
</script>