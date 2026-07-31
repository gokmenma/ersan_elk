<?php
use App\Model\HakedisSozlesmeModel;
use App\Model\HakedisKalemModel;
use App\Helper\Form;

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

$db = $model->getDb();
$isRevizyonlari = [];
$sureUzatimlari = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, COALESCE(SUM(d.degisim_miktari * d.birim_fiyat), 0) AS toplam_tutar_farki
        FROM hakedis_is_revizyonlari r
        LEFT JOIN hakedis_is_revizyon_kalemleri d ON d.revizyon_id = r.id
        WHERE r.sozlesme_id = ?
        GROUP BY r.id ORDER BY r.revizyon_no DESC
    ");
    $stmt->execute([$sozlesmeId]);
    $isRevizyonlari = $stmt->fetchAll(PDO::FETCH_OBJ);
    $detayStmt = $db->prepare("
        SELECT poz_no, kalem_adi, degisim_miktari, birim, birim_fiyat
        FROM hakedis_is_revizyon_kalemleri WHERE revizyon_id = ? ORDER BY id
    ");
    $kumulatifFark = array_sum(array_map(static function ($r) {
        return floatval($r->toplam_tutar_farki);
    }, $isRevizyonlari));
    foreach ($isRevizyonlari as $revizyon) {
        $revizyon->toplam_artis_orani = floatval($sozlesme->sozlesme_bedeli) > 0
            ? ($kumulatifFark / floatval($sozlesme->sozlesme_bedeli)) * 100
            : 0;
        $kumulatifFark -= floatval($revizyon->toplam_tutar_farki);
        $detayStmt->execute([$revizyon->id]);
        $revizyon->kalemler = $detayStmt->fetchAll(PDO::FETCH_OBJ);
    }
} catch (Throwable $e) {
    $isRevizyonlari = [];
}

try {
    $stmt = $db->prepare("SELECT * FROM hakedis_sure_uzatimlari WHERE sozlesme_id = ? ORDER BY uzatim_no DESC");
    $stmt->execute([$sozlesmeId]);
    $sureUzatimlari = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Throwable $e) {
    $sureUzatimlari = [];
}

$toplamIsArtisTutari = array_sum(array_map(static function ($r) {
    return floatval($r->toplam_tutar_farki);
}, $isRevizyonlari));
$toplamUzatimGunu = array_sum(array_map(static function ($u) {
    return intval($u->uzatim_gun);
}, $sureUzatimlari));
$yeniSozlesmeBedeli = floatval($sozlesme->sozlesme_bedeli) + $toplamIsArtisTutari;

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
                            <tr>
                                <th scope="row">Temel Endeks Ayı :</th>
                                <td>
                                    <?php if ($sozlesme->temel_endeks_ay && $sozlesme->temel_endeks_yil): ?>
                                        <span class="badge bg-info-subtle text-info px-2 py-1">
                                            <i data-feather="calendar" style="width:12px;height:12px" class="me-1"></i>
                                            <?= $aylar[$sozlesme->temel_endeks_ay] . ' ' . $sozlesme->temel_endeks_yil ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr class="my-3">
                <div class="mb-4">
                    <h6 class="fw-bold mb-2"><i class="bx bx-transfer-alt me-1 text-primary"></i> İş Artış/Azalışları</h6>
                    <?php if (!$isRevizyonlari): ?>
                        <div class="text-muted small border rounded p-2">Henüz iş artış/azalış kaydı yok.</div>
                    <?php else: ?>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small">Revizyon</th>
                                        <th class="small">İş Artış Tarihi</th>
                                        <th class="small text-end">İş Artış Tutarı</th>
                                        <th class="small text-end">Oran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($isRevizyonlari as $revizyon):
                                        $fark = floatval($revizyon->toplam_tutar_farki);
                                        $oran = floatval($revizyon->toplam_artis_orani);
                                    ?>
                                        <tr>
                                            <td class="small fw-bold"><?= intval($revizyon->revizyon_no) ?>. Revizyon</td>
                                            <td class="small"><?= date('d.m.Y', strtotime($revizyon->revizyon_tarihi)) ?></td>
                                            <td class="small text-end fw-bold <?= $fark < 0 ? 'text-danger' : 'text-success' ?>">
                                                <?= $fark > 0 ? '+' : '' ?><?= number_format($fark, 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="small text-end">%<?= number_format($oran, 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h6 class="fw-bold mb-2"><i class="bx bx-time-five me-1 text-warning"></i> Süre Uzatımları</h6>
                    <?php if (!$sureUzatimlari): ?>
                        <div class="text-muted small border rounded p-2">Henüz süre uzatımı kaydı yok.</div>
                    <?php else: ?>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small">Uzatım</th>
                                        <th class="small">Uzatım Tarihi</th>
                                        <th class="small text-end">Uzatılan Süre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sureUzatimlari as $uzatim): ?>
                                        <tr>
                                            <td class="small fw-bold"><?= intval($uzatim->uzatim_no) ?>. Uzatım</td>
                                            <td class="small"><?= date('d.m.Y', strtotime($uzatim->uzatim_tarihi)) ?></td>
                                            <td class="small text-end fw-bold text-success">+<?= intval($uzatim->uzatim_gun) ?> gün</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($isRevizyonlari || $sureUzatimlari): ?>
                    <hr class="my-3">
                    <div class="bg-light rounded p-3">
                        <h6 class="fw-bold mb-2"><i class="bx bx-calculator me-1 text-dark"></i> Genel Toplam (Güncel Durum)</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th class="small">Toplam İş Tutarı :</th>
                                    <td class="small text-end fw-bold text-primary">
                                        <?= number_format($yeniSozlesmeBedeli, 2, ',', '.') ?> ₺
                                        <?php if ($toplamIsArtisTutari != 0): ?>
                                            <div class="text-muted" style="font-size: 11px;">
                                                (<?= number_format($sozlesme->sozlesme_bedeli, 2, ',', '.') ?> ₺ +
                                                <?= number_format($toplamIsArtisTutari, 2, ',', '.') ?> ₺)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="small">Toplam İş Süresi :</th>
                                    <td class="small text-end fw-bold">
                                        <?= htmlspecialchars($sozlesme->isin_suresi ?? '') ?> Gün
                                        <?php if ($toplamUzatimGunu > 0): ?>
                                            <span class="text-muted" style="font-size: 11px;">(+<?= $toplamUzatimGunu ?> gün uzatım dahil)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="small">Bitiş Tarihi :</th>
                                    <td class="small text-end fw-bold text-success">
                                        <?= $sozlesme->isin_bitecegi_tarih ? date('d.m.Y', strtotime($sozlesme->isin_bitecegi_tarih)) : '-' ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <!-- Hakediş Dönemleri Listesi -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title">Hakediş Dönemleri</h4>
                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                        <a href="?p=hakedisler/index"
                            class="btn btn-link btn-sm text-secondary text-decoration-none px-3 fw-bold border-end rounded-0">
                            <i class="bx bx-arrow-back me-1"></i> Sözleşmelere Dön
                        </a>
                        <button
                            class="btn btn-primary btn-sm text-white shadow-primary px-3 fw-bold d-flex align-items-center"
                            data-bs-toggle="modal" data-bs-target="#yeniHakedisModal">
                            <i class="bx bx-plus fs-5 me-1"></i> Yeni Hakediş Ekle
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="hakedisTable" class="table table-bordered table-striped align-middle w-100 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Hakediş No</th>
                                <th>Dönem (Ay/Yıl)</th>
                                <th>Uygulanan Endeks (T/G)</th>
                                <th>Tutanak Tasdik Tarihi</th>
                                <th>Tutar (İmalat)</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loading from JS -->
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end align-middle font-size-14">Sayfa Toplamı:</th>
                                <th id="tableSayfaToplam"></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for New Progress Payment (Hakediş) -->
<div class="modal fade" id="yeniHakedisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="yeniHakedisForm" class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i data-feather="edit-3" class="text-success" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="hakedisModalTitle">Hakediş Oluştur/Düzenle</h5>
                        <small class="text-muted">Kayıt bilgilerini aşağıdan güncelleyebilirsiniz.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="id" id="hakedis_id">
            <input type="hidden" name="sozlesme_id" value="<?= $sozlesme->id ?>">
            <input type="hidden" name="temel_endeks_ayi" id="temel_endeks_ayi_hidden" value="">
            <input type="hidden" name="guncel_endeks_ayi" id="guncel_endeks_ayi_hidden" value="">
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Bilgi:</strong> Hakedişin ana parametrelerini girdikten sonra detay (endeksler, fiyat
                    farkları ve miktarlar) girişlerini yapabileceksiniz.
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <?= Form::FormFloatInput('number', 'hakedis_no', '', 'Hakediş No', 'Hakediş No', icon: 'hash', required: true) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <?= Form::FormSelect2('hakedis_tarihi_ay', $aylar, date('n'), 'Hakediş Ayı', icon: 'calendar', required: true) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <?= Form::FormFloatInput('number', 'hakedis_tarihi_yil', date('Y'), 'Hakediş Yılı', 'Hakediş Yılı', icon: 'calendar', required: true) ?>
                    </div>

                    <!-- Temel Endeks Ayı - Sözleşmeden gelen label -->
                    <div class="col-md-6 mb-3">
                        <div class="form-floating form-floating-custom">
                            <div class="form-control bg-light d-flex align-items-center"
                                style="height: auto; min-height: calc(3.5rem + 2px); padding-top: 1.625rem;"
                                id="temelEndeksAyiLabel">
                                <?php if ($sozlesme->temel_endeks_ay && $sozlesme->temel_endeks_yil): ?>
                                    <span class="badge bg-info-subtle text-info px-3 py-2 fs-6">
                                        <i data-feather="anchor" style="width:14px;height:14px" class="me-1"></i>
                                        <?= $aylar[$sozlesme->temel_endeks_ay] . ' ' . $sozlesme->temel_endeks_yil ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sözleşmede tanımlı değil</span>
                                <?php endif; ?>
                            </div>
                            <label>Temel Endeks Ayı (Sözleşme)</label>
                            <div class="form-floating-icon">
                                <i data-feather="anchor"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Güncel Endeks Ayı - Seçilen ay/yıldan otomatik -->
                    <div class="col-md-6 mb-3">
                        <div class="form-floating form-floating-custom">
                            <div class="form-control bg-light d-flex align-items-center"
                                style="height: auto; min-height: calc(3.5rem + 2px); padding-top: 1.625rem;"
                                id="guncelEndeksAyiLabel">
                                <span class="badge bg-warning-subtle text-warning px-3 py-2 fs-6"
                                    id="guncelEndeksAyiBadge">
                                    <i data-feather="trending-up" style="width:14px;height:14px" class="me-1"></i>
                                    <span id="guncelEndeksAyiText"><?= $aylar[date('n')] . ' ' . date('Y') ?></span>
                                </span>
                            </div>
                            <label>Güncel Endeks Ayı (Hakediş)</label>
                            <div class="form-floating-icon">
                                <i data-feather="trending-up"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <?= Form::FormFloatInput('text', 'is_yapilan_ayin_son_gunu', '', '', 'İş Yapılan Ayın Son Günü', icon: 'calendar', class: 'form-control flatpickr') ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <?= Form::FormFloatInput('text', 'tutanak_tasdik_tarihi', '', '', 'Tutanak Tasdik Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <?= Form::FormSelect2('durum', [
                            'taslak' => 'Taslak',
                            'hazirlandi' => 'Hazırlandı',
                            'tamamlandi' => 'Tamamlandı'
                        ], 'taslak', 'Durum', icon: 'flag') ?>
                        <small class="text-success d-block mt-1" style="font-size: 11px;">
                            <i data-feather="check-circle" style="width:12px;height:12px"></i>
                            Bu hakedişin tutarının bir sonraki dönemde otomatik çekilebilmesi için durumun <b>'Tamamlandı'</b> olarak kaydedilmesi gerekir.
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <?= Form::FormFloatInput('text', 'onceki_hakedis_tutari', '0.00', '0.00', 'Önceki Hakediş Tutarı (TL)', class: "form-control money", icon: 'dollar-sign') ?>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            <i data-feather="info" style="width:12px;height:12px"></i>
                            Otomatik olarak bir önceki hakedişten gelecek (ilk hakediş durumunda yazınız)
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary px-4 fw-bold rounded-3"
                    data-bs-dismiss="modal">İptal</button>
                <button type="submit" class="btn btn-success px-4 fw-bold rounded-3 shadow-success"
                    id="btnHakedisSave">Kaydet ve Detaya Git</button>
            </div>
        </form>
    </div>
</div>

<script>
    var currentSozlesmeId = <?= $sozlesme->id ?>;
    var sozlesmeTemelEndeksAy = <?= json_encode($sozlesme->temel_endeks_ay ? $aylar[$sozlesme->temel_endeks_ay] . ' ' . $sozlesme->temel_endeks_yil : '') ?>;
    var sozlesmeTemelEndeksAyNum = <?= intval($sozlesme->temel_endeks_ay ?? 0) ?>;
    var sozlesmeTemelEndeksYil = <?= intval($sozlesme->temel_endeks_yil ?? 0) ?>;

    // Ay/Yıl seçildiğinde Güncel Endeks Ayı label'ını güncelle
    const aylarJS = { 1: 'Ocak', 2: 'Şubat', 3: 'Mart', 4: 'Nisan', 5: 'Mayıs', 6: 'Haziran', 7: 'Temmuz', 8: 'Ağustos', 9: 'Eylül', 10: 'Ekim', 11: 'Kasım', 12: 'Aralık' };

    function updateEndeksLabels() {
        var ay = parseInt($('#hakedis_tarihi_ay').val());
        var yil = parseInt($('#hakedis_tarihi_yil').val());
        if (ay && yil && aylarJS[ay]) {
            var guncelText = aylarJS[ay] + ' ' + yil;
            $('#guncelEndeksAyiText').text(guncelText);
            $('#guncel_endeks_ayi_hidden').val(guncelText);
        }
        // Temel endeks ayı sözleşmeden gelir
        if (sozlesmeTemelEndeksAy) {
            $('#temel_endeks_ayi_hidden').val(sozlesmeTemelEndeksAy);
        }
    }

    $(document).on('change', '#hakedis_tarihi_ay, #hakedis_tarihi_yil', function () {
        updateEndeksLabels();
    });
    $(document).on('keyup', '#hakedis_tarihi_yil', function () {
        updateEndeksLabels();
    });

    // İlk yüklemede label'ları ayarla
    $(document).ready(function () {
        updateEndeksLabels();
    });
</script>
