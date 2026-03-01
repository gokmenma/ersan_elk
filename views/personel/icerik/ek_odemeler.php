<?php
use App\Helper\Security;
use App\Helper\Form;
use App\Helper\Date;
use App\Model\BordroParametreModel;

// Bordro parametrelerinden gelir türlerini getir
$BordroParametreModel = new BordroParametreModel();
$gelir_turleri_param = $BordroParametreModel->getGelirTurleri();

// İstatistikler ve Gruplama
$toplamEkOdeme = 0;
$aktifSurekliOdeme = 0;
$grouped_ek_odemeler = [];

foreach ($ek_odemeler as $k) {
    // İstatistikler
    if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli' && ($k->aktif ?? 1) == 1) {
        $aktifSurekliOdeme++;
    }
    // Tek seferlik ve sabit tutarların toplamı
    if (($k->tekrar_tipi ?? 'tek_sefer') == 'tek_sefer' || (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli' && ($k->hesaplama_tipi ?? 'sabit') == 'sabit')) {
        $toplamEkOdeme += $k->tutar ?? 0;
    }

    // Gruplama
    $grup_adi = $k->parametre_adi ?? ucfirst($k->tur ?? 'Diğer');
    if (!isset($grouped_ek_odemeler[$grup_adi])) {
        $grouped_ek_odemeler[$grup_adi] = [
            'items' => [],
            'toplam_tutar' => 0,
            'count' => 0
        ];
    }
    $grouped_ek_odemeler[$grup_adi]['items'][] = $k;
    $grouped_ek_odemeler[$grup_adi]['count']++;
    
    // Grup toplam tutar (Sadece sabit tutarlı olanlar eklenir, oranlılar eklenmez çünkü maaşa göre değişir)
    if (($k->tekrar_tipi ?? 'tek_sefer') == 'tek_sefer' || (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli' && ($k->hesaplama_tipi ?? 'sabit') == 'sabit')) {
        $grouped_ek_odemeler[$grup_adi]['toplam_tutar'] += $k->tutar ?? 0;
    }
}
?>

<div class="row">
    <!-- Ek Ödemeler Bölümü -->
    <div class="col-12 mb-4">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-success"><i class="bx bx-plus-circle me-2"></i>Personel Ek Ödemeleri
                    </h5>
                    <span class="badge bg-success">Toplam: <?= number_format($toplamEkOdeme, 2, ',', '.') ?> TL</span>
                    <?php if ($aktifSurekliOdeme > 0): ?>
                        <span class="badge bg-warning text-dark"><i class="bx bx-refresh me-1"></i><?= $aktifSurekliOdeme ?>
                            Sürekli Ödeme</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Görünüm Modu -->
                    <div class="segmented-control-container bg-light-subtle">
                        <input type="radio" class="segmented-control-input" name="ekOdemeViewMode" id="ekOdemeViewListe"
                            autocomplete="off" onchange="toggleEkOdemeView('liste')">
                        <label class="segmented-control-label py-1" for="ekOdemeViewListe">
                            <i class="bx bx-list-ul me-1"></i>Liste
                        </label>

                        <input type="radio" class="segmented-control-input" name="ekOdemeViewMode" id="ekOdemeViewGruplu"
                            autocomplete="off" checked onchange="toggleEkOdemeView('gruplu')">
                        <label class="segmented-control-label py-1" for="ekOdemeViewGruplu">
                            <i class="bx bx-grid-alt me-1"></i>Gruplu
                        </label>
                    </div>

                    <!-- İşlemler -->
                    <div class="action-container">
                        <button type="button" class="btn btn-success d-flex align-items-center"
                            id="btnOpenEkOdemeModal">
                            <i class="bx bx-plus me-1 fs-5"></i> Yeni Ek Ödeme Ekle
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <!-- Gruplu Görünüm -->
                    <table class="table table-hover mb-0 w-100" id="tblEkOdemelerGruplu">
                        <thead class="table-light">
                            <tr>
                                <th>Tür</th>
                                <th>Kayıt Sayısı</th>
                                <th>Toplam Tutar</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped_ek_odemeler as $grup_adi => $grup): ?>
                                <?php $row_id = 'grp_' . md5($grup_adi . rand()); ?>
                                <tr style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $row_id ?>" aria-expanded="false">
                                    <td class="fw-bold text-success">
                                        <i class="bx bx-chevron-right me-1"></i> <?= htmlspecialchars($grup_adi) ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $grup['count'] ?> Adet</span></td>
                                    <td class="fw-bold"><?= number_format($grup['toplam_tutar'], 2, ',', '.') ?> TL</td>
                                    <td class="text-end"><i class="bx bx-chevron-down"></i></td>
                                </tr>
                                <tr class="collapse" id="<?= $row_id ?>">
                                    <td colspan="4" class="p-0">
                                        <div class="p-3 bg-light">
                                            <table class="table table-sm table-bordered bg-white mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Tür</th>
                                                        <th>Tekrar</th>
                                                        <th>Hesaplama</th>
                                                        <th>Tutar / Oran</th>
                                                        <th>Tarih</th>
                                                        <th>Dönem</th>
                                                        <th>Açıklama</th>
                                                        <th>Durum</th>
                                                        <th class="text-center">İşlem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($grup['items'] as $k): ?>
                                                        <?php $enc_id = Security::encrypt($k->id); ?>
                                                        <tr data-id="<?= $enc_id ?>">
                                                            <td>
                                                                <span class="badge bg-soft-danger text-white">
                                                                    <?= $k->parametre_adi ?? ucfirst($k->tur ?? 'Diğer') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                                                    <span class="badge bg-warning text-dark"><i
                                                                            class="bx bx-refresh me-1"></i>Sürekli</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Tek Seferlik</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $hesaplama_labels = [
                                                                    'sabit' => '<i class="bx bx-money"></i> Sabit Tutar',
                                                                    'oran_net' => '<i class="bx bx-percent"></i> Net Üzerinden',
                                                                    'oran_brut' => '<i class="bx bx-percent"></i> Brüt Üzerinden'
                                                                ];
                                                                echo $hesaplama_labels[$k->hesaplama_tipi ?? 'sabit'] ?? 'Sabit Tutar';
                                                                ?>
                                                            </td>
                                                            <td class="fw-bold">
                                                                <?php if (($k->hesaplama_tipi ?? 'sabit') == 'sabit'): ?>
                                                                    <?= number_format($k->tutar ?? 0, 2, ',', '.') ?> TL
                                                                <?php else: ?>
                                                                    %<?= number_format($k->oran ?? 0, 2, ',', '.') ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= !empty($k->tarih) ? date('d.m.Y', strtotime($k->tarih)) : '-' ?></td>
                                                            <td>
                                                                <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                                                    <small>
                                                                        <?= $k->baslangic_donemi ? date('d.m.Y', strtotime($k->baslangic_donemi)) : '-' ?>
                                                                        <i class="bx bx-right-arrow-alt"></i>
                                                                        <?= $k->bitis_donemi ? date('d.m.Y', strtotime($k->bitis_donemi)) : '<span class="text-success">Süresiz</span>' ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <?= App\Helper\Helper::getDonemAdi($k->donem_id) ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                                            <td>
                                                                <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                                                    <?php if (($k->aktif ?? 1) == 1): ?>
                                                                        <span class="badge bg-success">Aktif</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Pasif</span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="badge bg-light text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli' && ($k->aktif ?? 1) == 1): ?>
                                                                    <button type="button" class="btn btn-sm btn-warning btn-personel-ek-odeme-sonlandir"
                                                                        data-id="<?= $k->id ?>" title="Sonlandır">
                                                                        <i class="bx bx-stop"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-sm btn-primary btn-personel-ek-odeme-duzenle"
                                                                    data-id="<?= $k->id ?>" title="Düzenle">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger btn-personel-ek-odeme-sil"
                                                                    data-id="<?= $k->id ?>" title="Sil">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Liste Görünümü -->
                    <table class="table table-hover mb-0 datatable w-100 d-none" id="tblEkOdemelerListe">
                        <thead class="table-light">
                            <tr>
                                <th>Tür</th>
                                <th>Tekrar</th>
                                <th>Hesaplama</th>
                                <th>Tutar / Oran</th>
                                <th>Tarih</th>
                                <th>Dönem</th>
                                <th>Açıklama</th>
                                <th>Durum</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ek_odemeler as $k): ?>
                                <?php $enc_id = Security::encrypt($k->id); ?>
                                <tr data-id="<?= $enc_id ?>">
                                    <td>
                                        <span class="badge bg-soft-danger text-white">
                                            <?= $k->parametre_adi ?? ucfirst($k->tur ?? 'Diğer') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                            <span class="badge bg-warning text-dark"><i
                                                    class="bx bx-refresh me-1"></i>Sürekli</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tek Seferlik</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $hesaplama_labels = [
                                            'sabit' => '<i class="bx bx-money"></i> Sabit Tutar',
                                            'oran_net' => '<i class="bx bx-percent"></i> Net Üzerinden',
                                            'oran_brut' => '<i class="bx bx-percent"></i> Brüt Üzerinden'
                                        ];
                                        echo $hesaplama_labels[$k->hesaplama_tipi ?? 'sabit'] ?? 'Sabit Tutar';
                                        ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php if (($k->hesaplama_tipi ?? 'sabit') == 'sabit'): ?>
                                            <?= number_format($k->tutar ?? 0, 2, ',', '.') ?> TL
                                        <?php else: ?>
                                            %<?= number_format($k->oran ?? 0, 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($k->tarih) ? date('d.m.Y', strtotime($k->tarih)) : '-' ?></td>
                                    <td>
                                        <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                            <small>
                                                <?= $k->baslangic_donemi ? date('d.m.Y', strtotime($k->baslangic_donemi)) : '-' ?>
                                                <i class="bx bx-right-arrow-alt"></i>
                                                <?= $k->bitis_donemi ? date('d.m.Y', strtotime($k->bitis_donemi)) : '<span class="text-success">Süresiz</span>' ?>
                                            </small>
                                        <?php else: ?>
                                            <?= App\Helper\Helper::getDonemAdi($k->donem_id) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                    <td>
                                        <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                            <?php if (($k->aktif ?? 1) == 1): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli' && ($k->aktif ?? 1) == 1): ?>
                                            <button type="button" class="btn btn-sm btn-warning btn-personel-ek-odeme-sonlandir"
                                                data-id="<?= $k->id ?>" title="Sonlandır">
                                                <i class="bx bx-stop"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-personel-ek-odeme-duzenle"
                                            data-id="<?= $k->id ?>" title="Düzenle">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-personel-ek-odeme-sil"
                                            data-id="<?= $k->id ?>" title="Sil">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEkOdemeView(mode) {
    if (mode === 'liste') {
        document.getElementById('tblEkOdemelerGruplu').classList.add('d-none');
        document.getElementById('tblEkOdemelerListe').classList.remove('d-none');
        localStorage.setItem('ekOdemeViewMode', 'liste');
    } else {
        document.getElementById('tblEkOdemelerListe').classList.add('d-none');
        document.getElementById('tblEkOdemelerGruplu').classList.remove('d-none');
        localStorage.setItem('ekOdemeViewMode', 'gruplu');
    }
}

// Sayfa yüklendiğinde tercihi uygula
(function() {
    var savedMode = localStorage.getItem('ekOdemeViewMode') || 'gruplu';
    
    // Radio butonunu güncelle
    if (savedMode === 'liste') {
        if(document.getElementById('ekOdemeViewListe')) document.getElementById('ekOdemeViewListe').checked = true;
    } else {
        if(document.getElementById('ekOdemeViewGruplu')) document.getElementById('ekOdemeViewGruplu').checked = true;
    }
    
    // Görünümü uygula
    toggleEkOdemeView(savedMode);
})();
</script>

<!-- Ek Ödeme Ekle Modal -->
<div class="modal fade" id="modalPersonelEkOdemeEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-plus-circle me-2"></i>Yeni Ek Ödeme Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelEkOdemeEkle" novalidate>
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <!-- Ek Ödeme Türü Seçimi (Parametrelerden) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ek Ödeme Türü <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="ek_odeme_parametre_id" name="parametre_id" required
                            style="width: 100%">
                            <option value="">Ek ödeme türü seçiniz...</option>
                            <?php foreach ($gelir_turleri_param as $param): ?>
                                <option value="<?= $param->id ?>" data-kod="<?= $param->kod ?>"
                                    data-hesaplama="<?= $param->hesaplama_tipi ?>" data-oran="<?= $param->oran ?? 0 ?>"
                                    data-tutar="<?= $param->varsayilan_tutar ?? 0 ?>">
                                    <?= htmlspecialchars($param->etiket) ?>
                                    <?php if (strpos($param->hesaplama_tipi ?? '', 'oran') !== false && ($param->oran ?? 0) > 0): ?>
                                        (%<?= $param->oran ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                 

                    <!-- Tekrar Tipi Seçimi -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ödeme Tipi</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-success">
                                <input class="form-check-input" type="radio" name="ek_tekrar_tipi"
                                    id="ek_tekrar_tek_sefer" value="tek_sefer" checked>
                                <label class="form-check-label" for="ek_tekrar_tek_sefer">
                                    <i class="bx bx-calendar-check me-1"></i> Tek Seferlik
                                </label>
                            </div>
                            <div class="form-check form-check-warning">
                                <input class="form-check-input" type="radio" name="ek_tekrar_tipi"
                                    id="ek_tekrar_surekli" value="surekli">
                                <label class="form-check-label" for="ek_tekrar_surekli">
                                    <i class="bx bx-refresh me-1"></i> Sürekli (Her Ay)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Dönem Seçimi - Tek Seferlik -->
                    <div class="mb-3" id="ek_div_tek_sefer_donem">
                        <?= Form::FormSelect2(
                            name: "ek_odeme_donem",
                            options: $acik_donemler,
                            selectedValue: array_key_first($acik_donemler) ?? '',
                            label: "Dönem Seçin",
                            icon: "calendar",
                            valueField: '',
                            textField: '',
                            required: true,
                        ) ?>
                    </div>

                    <!-- Tarih Aralığı - Sürekli -->
                    <div class="row d-none" id="ek_div_surekli_donem">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ek_odeme_baslangic_donemi"
                                name="baslangic_donemi" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bitiş Tarihi <small class="text-muted">(Boş =
                                    Süresiz)</small></label>
                            <input type="date" class="form-control" id="ek_odeme_bitis_donemi" name="bitis_donemi">
                        </div>
                    </div>

                    <!-- Hesaplama Tipi -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hesaplama Tipi</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ek_hesaplama_tipi"
                                    id="ek_hesaplama_sabit" value="sabit" checked>
                                <label class="form-check-label" for="ek_hesaplama_sabit">
                                    <i class="bx bx-money me-1"></i> Sabit Tutar
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ek_hesaplama_tipi"
                                    id="ek_hesaplama_oran_net" value="oran_net">
                                <label class="form-check-label" for="ek_hesaplama_oran_net">
                                    <i class="bx bx-percent me-1"></i> Net Maaş Üzerinden (%)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ek_hesaplama_tipi"
                                    id="ek_hesaplama_oran_brut" value="oran_brut">
                                <label class="form-check-label" for="ek_hesaplama_oran_brut">
                                    <i class="bx bx-percent me-1"></i> Brüt Maaş Üzerinden (%)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tutar/Oran Girişi -->
                    <div class="row">
                        <div class="col-md-6 mb-3" id="ek_div_tutar">
                            <?= Form::FormFloatInput("number", "ek_odeme_tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" id="ek_odeme_tutar" min="0"') ?>
                        </div>
                        <div class="col-md-6 mb-3 d-none" id="ek_div_oran">
                            <?= Form::FormFloatInput("number", "oran", "", "0", "Oran (%)", "percent", "form-control", false, null, "off", false, 'step="0.01" id="ek_odeme_oran" min="0" max="100"') ?>
                        </div>
                        <!-- Tarih Seçimi -->
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("text", "ek_odeme_tarih", Date::today(), "Ek Ödeme Tarihi", "Tarih", "calendar", "form-control flatpickr", true, null, "off", false) ?>
                        </div>
                    </div>

                    <!-- Açıklama -->
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