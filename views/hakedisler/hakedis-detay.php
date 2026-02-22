<?php
use App\Model\HakedisDonemModel;
use App\Model\HakedisKalemModel;
use App\Model\HakedisMiktarModel;
use App\Model\HakedisSozlesmeModel;

$hakedisId = $_GET['id'] ?? 0;
if (!$hakedisId) {
    echo "<div class='alert alert-danger'>Hakediş ID bulunamadı.</div>";
    return;
}

$donemModel = new HakedisDonemModel();
$db = $donemModel->getDb();

// Hakediş ve Sözleşme bilgisini alalım
$sql = "SELECT d.*, s.idare_adi, s.isin_adi, s.sozlesme_bedeli, s.isin_yuklenicisi 
        FROM hakedis_donemleri d
        JOIN hakedis_sozlesmeler s ON d.sozlesme_id = s.id
        WHERE d.id = ? AND s.firma_id = ? AND d.silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute([$hakedisId, $_SESSION['firma_id']]);
$hakedis = $stmt->fetch(PDO::FETCH_OBJ);

if (!$hakedis) {
    echo "<div class='alert alert-danger'>Geçerli bir hakediş bulunamadı veya yetkiniz yok.</div>";
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
$donemBaslik = $aylar[$hakedis->hakedis_tarihi_ay] . " " . $hakedis->hakedis_tarihi_yil;
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Hakediş #
                <?= $hakedis->hakedis_no ?> -
                <?= $donemBaslik ?>
            </h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="?p=hakedisler/index">Sözleşmeler</a></li>
                    <li class="breadcrumb-item"><a
                            href="?p=hakedisler/sozlesme-detay&id=<?= $hakedis->sozlesme_id ?>">Hakedişler</a></li>
                    <li class="breadcrumb-item active">Detay ve Miktarlar</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3">
        <div class="card overflow-hidden">
            <div class="bg-primary">
                <div class="row">
                    <div class="col-12">
                        <div class="text-white p-3">
                            <h5 class="text-white">Sözleşme Bilgisi</h5>
                            <p class="mb-1"><strong>İdare:</strong>
                                <?= htmlspecialchars($hakedis->idare_adi) ?>
                            </p>
                            <p class="mb-1"><strong>İş Adı:</strong>
                                <?= htmlspecialchars(mb_substr($hakedis->isin_adi, 0, 50)) ?>...
                            </p>
                            <p class="mb-0"><strong>Bedel:</strong>
                                <?= number_format($hakedis->sozlesme_bedeli, 2, ',', '.') ?> ₺
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-transparent border-bottom">
                <div class="d-flex flex-wrap align-items-center">
                    <h5 class="card-title mt-2 mb-0">Hakediş Özeti</h5>
                </div>
            </div>
            <div class="card-body">
                <form id="hakedisParametreForm">
                    <input type="hidden" name="hakedis_id" value="<?= $hakedis->id ?>">

                    <h6 class="text-primary"><i class="bx bx-cog me-1"></i> Fiyat Farkı Çarpanları</h6>
                    <!-- Burada excel formatındaki Pn, Asgari ücret, vb girişleri dinamik alacağız -->
                    <div class="mb-3">
                        <label class="form-label" title="İşçilik Katsayısı (Genelde 0.28)">a1 (İşçilik)
                            Katsayısı</label>
                        <input type="number" step="0.00001" class="form-control" name="a1_katsayisi"
                            value="<?= $hakedis->a1_katsayisi ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" title="Temel Sözleşme Ayı">Temel (Sözleşme Ayı) Endeksi</label>
                        <div id="temelEndeksAlanda">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">A.Ücret</span>
                                <input type="number" step="0.01" class="form-control" name="asgari_ucret_temel"
                                    value="<?= $hakedis->asgari_ucret_temel ?>" placeholder="Örn: 26005.50">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">Motorin</span>
                                <input type="number" step="0.00001" class="form-control" name="motorin_temel"
                                    value="<?= $hakedis->motorin_temel ?>" placeholder="Örn: 54.13308">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">TÜFE</span>
                                <input type="number" step="0.01" class="form-control" name="ufe_genel_temel"
                                    value="<?= $hakedis->ufe_genel_temel ?>" placeholder="Örn: 4632.89">
                            </div>
                            <!-- Ekstra parametreler yüklendiğinde buraya gelir -->
                            <?php
                            $ekstraParamlar = json_decode($hakedis->ekstra_parametreler ?? '{}', true);
                            if(isset($ekstraParamlar['temel']) && is_array($ekstraParamlar['temel'])):
                                foreach($ekstraParamlar['temel'] as $key => $val):
                            ?>
                            <div class="input-group mt-1 ek-param-row">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;" title="<?= htmlspecialchars($key) ?>">
                                    <?= htmlspecialchars(mb_substr($key, 0, 8)) ?>
                                </span>
                                <input type="number" step="any" class="form-control" name="ekstra_temel[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="$(this).closest('.input-group').remove()"><i class="bx bx-trash"></i></button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-soft-secondary" onclick="addEndeksRow('temelEndeksAlanda', 'temel')"><i class="bx bx-plus"></i> Ek Alan Ekle</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-warning" title="Uygulama Ayı">Güncel (Hakediş Ayı) Endeksi</label>
                        <div id="guncelEndeksAlanda">
                            <div class="input-group border-warning">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">A.Ücret</span>
                                <input type="number" step="0.01" class="form-control border-warning" name="asgari_ucret_guncel"
                                    value="<?= $hakedis->asgari_ucret_guncel ?>" placeholder="Örn: 33075.50">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">Motorin</span>
                                <input type="number" step="0.00001" class="form-control border-warning" name="motorin_guncel"
                                    value="<?= $hakedis->motorin_guncel ?>">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-soft-warning text-warning border-warning" style="width: 80px;">TÜFE</span>
                                <input type="number" step="0.01" class="form-control border-warning" name="ufe_genel_guncel"
                                    value="<?= $hakedis->ufe_genel_guncel ?>">
                            </div>
                            <!-- Ekstra parametreler yüklendiğinde buraya gelir -->
                            <?php
                            if(isset($ekstraParamlar['guncel']) && is_array($ekstraParamlar['guncel'])):
                                foreach($ekstraParamlar['guncel'] as $key => $val):
                            ?>
                            <div class="input-group mt-1 border-warning ek-param-row">
                                <span class="input-group-text bg-soft-warning text-warning border-warning" style="width: 80px;" title="<?= htmlspecialchars($key) ?>">
                                    <?= htmlspecialchars(mb_substr($key, 0, 8)) ?>
                                </span>
                                <input type="number" step="any" class="form-control border-warning" name="ekstra_guncel[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>">
                                <button type="button" class="btn btn-outline-danger btn-sm border-warning" onclick="$(this).closest('.input-group').remove()"><i class="bx bx-trash"></i></button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-soft-warning" onclick="addEndeksRow('guncelEndeksAlanda', 'guncel')"><i class="bx bx-plus"></i> Ek Alan Ekle</button>
                        </div>
                    </div>

                    <h6 class="text-primary mt-4"><i class="bx bx-calculator me-1"></i> Kesinti Oranları</h6>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label">KDV (%)</label>
                            <input type="number" step="0.01" class="form-control" name="kdv_orani"
                                value="<?= $hakedis->kdv_orani ?>">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">Tevkifat</label>
                            <input type="text" class="form-control" name="tevkifat_orani"
                                value="<?= $hakedis->tevkifat_orani ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-2"><i class="bx bx-save me-1"></i>
                        Parametreleri Kaydet</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-9">

        <!-- Kalemler ve Miktarlar Ekleme Alanı -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center mb-4">
                    <h5 class="card-title me-2 border-bottom pb-2">Hakediş Kalem ve İmalat Girişleri</h5>
                    <div class="ms-auto mt-2 mt-md-0">
                        <!-- Excel Çıktısı -->
                        <button type="button" class="btn btn-success waves-effect waves-light"
                            onclick="exportHakedisToExcel(<?= $hakedis->id ?>)">
                            <i class="bx bx-file me-1"></i> Excel Çıktısı Al
                        </button>
                        <button type="button" class="btn btn-primary" onclick="addNewKalemRow()">
                            <i class="bx bx-plus me-1"></i> Listeye Kalem Ekle
                        </button>
                    </div>
                </div>

                <!-- Main dynamic table for Kalemler & Miktarlar (İcmal structure) -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle table-nowrap table-hover" id="miktarlarTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Sıra</th>
                                <th>İmalatın Cinsi (Kalem)</th>
                                <th>Birim</th>
                                <th>Teklif Birim Fiyat (TL)</th>
                                <th>Önceki T. Miktar</th>
                                <th>Bu Ayki Miktar</th>
                                <th class="text-bg-warning">Toplam Miktar</th>
                                <th>Toplam Tutar (TL)</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="kalemlerBody">
                            <tr>
                                <td colspan="9" class="text-center"><i class="bx bx-loader bx-spin"></i> Veriler
                                    Yükleniyor...</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="7" class="text-end font-size-15 h5">Toplam Hakediş Tutarı (İmalat):</th>
                                <th colspan="2" class="font-size-15 text-primary h5" id="toplamImalatTutar">0,00 ₺</th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end font-size-15 h5 text-success">Hesaplanan Fiyat Farkı:
                                </th>
                                <th colspan="2" class="font-size-15 text-success h5" id="hesaplananFiyatFarki">0,00 ₺
                                </th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end font-size-15 h5 text-danger">KDV Dahil Genel Toplam:
                                </th>
                                <th colspan="2" class="font-size-15 text-danger h5" id="kdvDahilToplam">0,00 ₺</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    var currentHakedisId = <?= $hakedis->id ?>;
    var currentSozlesmeId = <?= $hakedis->sozlesme_id ?>;
</script>
<script src="views/hakedisler/js/hakedis-detay.js?v=<?= time() ?>"></script>