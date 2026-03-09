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
$sql = "SELECT d.*, s.idare_adi, s.isin_adi, s.sozlesme_bedeli, s.isin_yuklenicisi,
               s.a1_katsayisi as s_a1, s.b1_katsayisi as s_b1, s.b2_katsayisi as s_b2, s.c_katsayisi as s_c,
               s.asgari_ucret_temel as s_asgari, s.motorin_temel as s_motorin, s.ufe_genel_temel as s_ufe, s.makine_ekipman_temel as s_makine,
               s.kdv_orani as s_kdv, s.tevkifat_orani as s_tevkifat,
               s.temel_endeks_ay as s_temel_endeks_ay, s.temel_endeks_yil as s_temel_endeks_yil
        FROM hakedis_donemleri d
        JOIN hakedis_sozlesmeler s ON d.sozlesme_id = s.id
        WHERE d.id = ? AND s.firma_id = ? AND d.silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute([$hakedisId, $_SESSION['firma_id']]);
$hakedis = $stmt->fetch(PDO::FETCH_OBJ);

if ($hakedis) {
    // Sözleşme bazlı varsayılan değerleri bas (Eğer hakedişte henüz girilmemişse)
    $hakedis->a1_katsayisi = $hakedis->a1_katsayisi ?: $hakedis->s_a1;
    $hakedis->b1_katsayisi = $hakedis->b1_katsayisi ?: $hakedis->s_b1;
    $hakedis->b2_katsayisi = $hakedis->b2_katsayisi ?: $hakedis->s_b2;
    $hakedis->c_katsayisi = $hakedis->c_katsayisi ?: $hakedis->s_c;

    $hakedis->asgari_ucret_temel = $hakedis->asgari_ucret_temel ?: $hakedis->s_asgari;
    $hakedis->motorin_temel = $hakedis->motorin_temel ?: $hakedis->s_motorin;
    $hakedis->ufe_genel_temel = $hakedis->ufe_genel_temel ?: $hakedis->s_ufe;
    $hakedis->makine_ekipman_temel = $hakedis->makine_ekipman_temel ?: $hakedis->s_makine;

    $hakedis->kdv_orani = $hakedis->kdv_orani ?: $hakedis->s_kdv;
    $hakedis->tevkifat_orani = $hakedis->tevkifat_orani ?: $hakedis->s_tevkifat;
}

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
                            <p class="mb-1"><strong>Bedel:</strong>
                                <?= number_format($hakedis->sozlesme_bedeli, 2, ',', '.') ?> ₺
                            </p>
                            <?php if ($hakedis->s_temel_endeks_ay && $hakedis->s_temel_endeks_yil): ?>
                                <p class="mb-1"><strong>Temel Endeks Ayı:</strong>
                                    <span class="badge bg-light text-dark">
                                        <?= $aylar[$hakedis->s_temel_endeks_ay] . ' ' . $hakedis->s_temel_endeks_yil ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <?php if ($hakedis->guncel_endeks_ayi): ?>
                                <p class="mb-1"><strong>Güncel Endeks Ayı:</strong>
                                    <span class="badge bg-warning text-dark">
                                        <?= htmlspecialchars($hakedis->guncel_endeks_ayi) ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <?php
                            $durumLabels = [
                                'taslak' => ['Taslak', 'bg-secondary'],
                                'hazirlandi' => ['Hazırlandı', 'bg-info'],
                                'tamamlandi' => ['Tamamlandı', 'bg-success'],
                                'onaylandi' => ['Onaylandı', 'bg-primary']
                            ];
                            $dLabel = $durumLabels[$hakedis->durum] ?? ['Taslak', 'bg-secondary'];
                            ?>
                            <p class="mb-0"><strong>Durum:</strong>
                                <span class="badge <?= $dLabel[1] ?>"><?= $dLabel[0] ?></span>
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
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" title="İşçilik Katsayısı (a1)">a1 Katsayısı</label>
                            <input type="number" step="0.00001" class="form-control form-control-sm" name="a1_katsayisi"
                                value="<?= $hakedis->a1_katsayisi ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" title="Motorin Katsayısı (b1)">b1 Katsayısı</label>
                            <input type="number" step="0.0000001" class="form-control form-control-sm" name="b1_katsayisi"
                                value="<?= $hakedis->b1_katsayisi ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" title="Yİ-ÜFE Katsayısı (b2)">b2 Katsayısı</label>
                            <input type="number" step="0.00001" class="form-control form-control-sm" name="b2_katsayisi"
                                value="<?= $hakedis->b2_katsayisi ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" title="Makine-Ekipman Katsayısı (c)">c Katsayısı</label>
                            <input type="number" step="0.00001" class="form-control form-control-sm" name="c_katsayisi"
                                value="<?= $hakedis->c_katsayisi ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" title="Temel Sözleşme Ayı">Temel (Sözleşme Ayı) Endeksi</label>
                        <div id="temelEndeksAlanda">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">A.Ücret</span>
                                <input type="number" step="0.01" class="form-control" name="asgari_ucret_temel"
                                    value="<?= $hakedis->asgari_ucret_temel ?>" placeholder="Io">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">Motorin</span>
                                <input type="number" step="0.00001" class="form-control" name="motorin_temel"
                                    value="<?= $hakedis->motorin_temel ?>" placeholder="Mo">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">TÜFE</span>
                                <input type="number" step="0.01" class="form-control" name="ufe_genel_temel"
                                    value="<?= $hakedis->ufe_genel_temel ?>" placeholder="ÜFEo">
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light text-muted" style="width: 80px;">Makine</span>
                                <input type="number" step="0.00001" class="form-control" name="makine_ekipman_temel"
                                    value="<?= $hakedis->makine_ekipman_temel ?>" placeholder="Eo">
                            </div>
                            <!-- Ekstra parametreler yüklendiğinde buraya gelir -->
                            <?php
                            $ekstraParamlar = json_decode($hakedis->ekstra_parametreler ?? '{}', true);
                            if (isset($ekstraParamlar['temel']) && is_array($ekstraParamlar['temel'])):
                                foreach ($ekstraParamlar['temel'] as $key => $val):
                                    ?>
                                    <div class="input-group mt-1 ek-param-row">
                                        <span class="input-group-text bg-light text-muted" style="width: 80px;"
                                            title="<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars(mb_substr($key, 0, 8)) ?>
                                        </span>
                                        <input type="number" step="any" class="form-control"
                                            name="ekstra_temel[<?= htmlspecialchars($key) ?>]"
                                            value="<?= htmlspecialchars($val) ?>">
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="$(this).closest('.input-group').remove()"><i
                                                class="bx bx-trash"></i></button>
                                    </div>
                                <?php endforeach; endif; ?>
                        </div>
                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-soft-secondary"
                                onclick="addEndeksRow('temelEndeksAlanda', 'temel')"><i class="bx bx-plus"></i> Ek Alan
                                Ekle</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label text-warning mb-0" title="Uygulama Ayı">Güncel (Hakediş Ayı) Endeksi</label>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="fetchGuncelEndeksler()" title="TÜİK ve EPDK verilerini web'den çeker">
                                <i class="bx bx-refresh"></i> Web'den Çek
                            </button>
                        </div>
                        <div id="guncelEndeksAlanda">
                            <div class="input-group border border-warning rounded-2 overflow-hidden">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">A.Ücret</span>
                                <input type="number" step="0.01" class="form-control border-warning"
                                    name="asgari_ucret_guncel" value="<?= $hakedis->asgari_ucret_guncel ?>"
                                    placeholder="In">
                            </div>
                            <div class="input-group mt-1 border border-warning rounded-2 overflow-hidden">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">Motorin</span>
                                <input type="number" step="0.0000001" class="form-control border-warning"
                                    name="motorin_guncel" value="<?= $hakedis->motorin_guncel ?>" placeholder="Mn">
                            </div>
                            <div class="input-group mt-1 border border-warning rounded-2 overflow-hidden">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">TÜFE</span>
                                <input type="number" step="0.001" class="form-control border-warning"
                                    name="ufe_genel_guncel" value="<?= $hakedis->ufe_genel_guncel ?>"
                                    placeholder="ÜFEn">
                            </div>
                            <div class="input-group mt-1 border border-warning rounded-2 overflow-hidden">
                                <span class="input-group-text bg-soft-warning text-warning border-warning"
                                    style="width: 80px;">Makine</span>
                                <input type="number" step="0.00001" class="form-control border-warning"
                                    name="makine_ekipman_guncel" value="<?= $hakedis->makine_ekipman_guncel ?>"
                                    placeholder="En">
                            </div>
                            <!-- Ekstra parametreler yüklendiğinde buraya gelir -->
                            <?php
                            if (isset($ekstraParamlar['guncel']) && is_array($ekstraParamlar['guncel'])):
                                foreach ($ekstraParamlar['guncel'] as $key => $val):
                                    ?>
                                    <div class="input-group mt-1 border-warning ek-param-row">
                                        <span class="input-group-text bg-soft-warning text-warning border-warning"
                                            style="width: 80px;" title="<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars(mb_substr($key, 0, 8)) ?>
                                        </span>
                                        <input type="number" step="any" class="form-control border-warning"
                                            name="ekstra_guncel[<?= htmlspecialchars($key) ?>]"
                                            value="<?= htmlspecialchars($val) ?>">
                                        <button type="button" class="btn btn-outline-danger btn-sm border-warning"
                                            onclick="$(this).closest('.input-group').remove()"><i
                                                class="bx bx-trash"></i></button>
                                    </div>
                                <?php endforeach; endif; ?>
                        </div>
                        <div class="mt-2 text-end">
                            <button type="button" class="btn btn-sm btn-soft-warning"
                                onclick="addEndeksRow('guncelEndeksAlanda', 'guncel')"><i class="bx bx-plus"></i> Ek
                                Alan Ekle</button>
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
                    <h5 class="card-title me-2">Hakediş Kalem ve İmalat Girişleri</h5>
                    <div class="ms-auto mt-2 mt-md-0 d-flex align-items-center gap-2">
                        <!-- Navigasyon Butonları (Bordro stilinde) -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 me-2">
                            <a href="?p=hakedisler/index"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-3 fw-bold d-flex align-items-center">
                                <i class="bx bx-list-ul fs-5 me-1"></i> Sözleşmelere Dön
                            </a>
                            <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                            <a href="?p=hakedisler/sozlesme-detay&id=<?= $hakedis->sozlesme_id ?>"
                                class="btn btn-link btn-sm text-info text-decoration-none px-3 fw-bold d-flex align-items-center">
                                <i class="bx bx-file fs-5 me-1"></i> Hakedişlere Dön
                            </a>
                            <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                       

                        <!-- Excel Çıktısı -->
                        <button type="button" class="btn btn-success waves-effect waves-light shadow-success"
                            onclick="exportHakedisToExcel(<?= $hakedis->id ?>)">
                            <i class="bx bx-file me-1"></i> Excel Çıktısı Al
                        </button>
                         </div>
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
                                <th>Bu Ayki Tutar (TL)</th>
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
                                <th colspan="7" class="text-end font-size-15 h5">Bu Ayki Hakediş Tutarı (İmalat):</th>
                                <th colspan="2" class="font-size-15 text-primary h5" id="toplamImalatTutar">0,00 ₺</th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end font-size-15 h5 text-success">Hesaplanan Fiyat Farkı:
                                </th>
                                <th colspan="2" class="font-size-15 text-success h5" id="hesaplananFiyatFarki">0,00 ₺
                                </th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end font-size-15 h5 text-danger">Bu Ayki KDV Dahil Toplam:
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
    var currentHakedisAy = <?= $hakedis->hakedis_tarihi_ay ?>;
    var currentHakedisYil = <?= $hakedis->hakedis_tarihi_yil ?>;
</script>