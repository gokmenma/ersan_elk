<?php
use App\Model\HakedisSozlesmeModel;
use App\Model\HakedisKalemModel;

$sozlesmeId = $_GET['id'] ?? 0;
if (!$sozlesmeId) {
    echo "<div class='alert alert-danger'>Sözleşme ID bulunamadı.</div>";
    return;
}

$model = new HakedisSozlesmeModel();
$sozlesme = $model->find($sozlesmeId);

if (!$sozlesme || $sozlesme->firma_id != $_SESSION['firma_id'] || $sozlesme->silinme_tarihi != null) {
    echo "<div class='alert alert-danger'>Geçerli bir sözleşme bulunamadı.</div>";
    return;
}

$aylar = [
    1 => 'Ocak',
    2 => 'Şubat',
    3 => 'Mart',
    4 => 'Nisan',
    5 => 'Mayıs',
    6 => 'Haziran',
    7 => 'Temmuz',
    8 => 'Ağustos',
    9 => 'Eylül',
    10 => 'Ekim',
    11 => 'Kasım',
    12 => 'Aralık'
];
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Sözleşme Detayı : <?= htmlspecialchars($sozlesme->idare_adi) ?></h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="?p=hakedisler/index">Sözleşmeler</a></li>
                    <li class="breadcrumb-item active">Detay</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4">
        <div class="card overflow-hidden">
            <div class="bg-primary">
                <div class="row">
                    <div class="col-12">
                        <div class="text-white p-3">
                            <h5 class="text-white"><?= htmlspecialchars($sozlesme->isin_yuklenicisi) ?></h5>
                            <p class="mb-0"><?= htmlspecialchars($sozlesme->isin_adi) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table table-nowrap mb-0">
                        <tbody>
                            <tr>
                                <th scope="row">Sözleşme Bedeli :</th>
                                <td><?= number_format($sozlesme->sozlesme_bedeli, 2, ',', '.') ?> ₺</td>
                            </tr>
                            <tr>
                                <th scope="row">İhale Kayıt No :</th>
                                <td><?= htmlspecialchars($sozlesme->ihale_kayit_no) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">İşin Süresi :</th>
                                <td><?= htmlspecialchars($sozlesme->isin_suresi ?? '') ?> Gün</td>
                            </tr>
                            <tr>
                                <th scope="row">Bitiş Tarihi :</th>
                                <td><?= $sozlesme->isin_bitecegi_tarih ? date('d.m.Y', strtotime($sozlesme->isin_bitecegi_tarih)) : '-' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <!-- Hakediş Dönemleri Listesi -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title">Hakediş Dönemleri</h4>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniHakedisModal">
                        <i class="bx bx-plus me-1"></i> Yeni Hakediş Ekle
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="hakedisTable" class="table table-bordered table-striped align-middle w-100 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Hakediş No</th>
                                <th>Dönem (Ay/Yıl)</th>
                                <th>Uygulanan Endeks (T/G)</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loading from JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for New Progress Payment (Hakediş) -->
<div class="modal fade" id="yeniHakedisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white">Yeni Hakediş Oluştur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="yeniHakedisForm">
                <input type="hidden" name="sozlesme_id" value="<?= $sozlesme->id ?>">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Bilgi:</strong> Hakedişin ana parametrelerini girdikten sonra detay (endeksler, fiyat
                        farkları ve miktarlar) girişlerini yapabileceksiniz.
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hakediş No <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="hakedis_no" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hakediş Ayı <span class="text-danger">*</span></label>
                            <select class="form-select" name="hakedis_tarihi_ay" required>
                                <?php foreach ($aylar as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= date('n') == $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hakediş Yılı <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="hakedis_tarihi_yil" value="<?= date('Y') ?>"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Temel Endeks Ayı <span class="text-muted">(Sözleşme başı - Örn:
                                    Eylül 2025)</span></label>
                            <input type="text" class="form-control" name="temel_endeks_ayi" placeholder="Eylül 2025"
                                required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Güncel Endeks Ayı <span class="text-muted">(Hakediş ayı - Örn:
                                    Şubat 2026)</span></label>
                            <input type="text" class="form-control" name="guncel_endeks_ayi" placeholder="Şubat 2026"
                                required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">İş Yapılan Ayın Son Günü</label>
                            <input type="date" class="form-control" name="is_yapilan_ayin_son_gunu">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Oluştur ve Detaya Git</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var currentSozlesmeId = <?= $sozlesme->id ?>;
</script>
<script src="views/hakedisler/js/sozlesme-detay.js?v=<?= time() ?>"></script>