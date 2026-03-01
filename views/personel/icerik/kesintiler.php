<?php
use App\Helper\Security;
use App\Helper\Form;
use App\Helper\Date;
use App\Model\BordroParametreModel;

// Bordro parametrelerinden kesinti türlerini getir
$BordroParametreModel = new BordroParametreModel();
$kesinti_turleri_param = $BordroParametreModel->getKesintiTurleri();

// İstatistikler ve Gruplama
$toplamKesinti = 0;
$aktifSurekliKesinti = 0;
$bekleyenKesinti = 0;
$grouped_kesintiler = [];

foreach ($kesintiler as $k) {
    // İstatistikler
    if ($k->tekrar_tipi == 'surekli' && $k->aktif == 1) {
        $aktifSurekliKesinti++;
    }
    // Onaylanmış tek seferlik kesintilerin tutarını topla
    if (($k->tekrar_tipi == 'tek_sefer' || ($k->tekrar_tipi == 'surekli' && $k->hesaplama_tipi == 'sabit')) && ($k->durum ?? 'beklemede') == 'onaylandi') {
        $toplamKesinti += $k->tutar ?? 0;
    }
    // Bekleyen kesintileri say
    if (($k->durum ?? 'beklemede') == 'beklemede') {
        $bekleyenKesinti++;
    }

    // Gruplama
    $grup_adi = $k->parametre_adi ?? ucfirst($k->tur);
    if (!isset($grouped_kesintiler[$grup_adi])) {
        $grouped_kesintiler[$grup_adi] = [
            'items' => [],
            'toplam_tutar' => 0,
            'count' => 0
        ];
    }
    $grouped_kesintiler[$grup_adi]['items'][] = $k;
    $grouped_kesintiler[$grup_adi]['count']++;
    
    // Grup toplam (Sadece onaylı ve sabit olanlar)
    if (($k->tekrar_tipi == 'tek_sefer' || ($k->tekrar_tipi == 'surekli' && $k->hesaplama_tipi == 'sabit')) && ($k->durum ?? 'beklemede') == 'onaylandi') {
        $grouped_kesintiler[$grup_adi]['toplam_tutar'] += $k->tutar ?? 0;
    }
}
?>

<div class="row">
    <!-- Kesintiler Bölümü -->
    <div class="col-12 mb-4">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="card-title mb-0 text-primary"><i class="bx bx-minus-circle me-2"></i>Personel Kesintileri
                    </h5>
                    <span class="badge bg-danger">Toplam: <?= number_format($toplamKesinti, 2, ',', '.') ?> TL</span>
                    <?php if ($bekleyenKesinti > 0): ?>
                        <span class="badge bg-warning text-dark"><i class="bx bx-time me-1"></i><?= $bekleyenKesinti ?> Onay
                            Bekliyor</span>
                    <?php endif; ?>
                    <?php if ($aktifSurekliKesinti > 0): ?>
                        <span class="badge bg-warning text-dark"><i
                                class="bx bx-refresh me-1"></i><?= $aktifSurekliKesinti ?> Sürekli Kesinti</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Görünüm Modu -->
                    <div class="segmented-control-container bg-light-subtle">
                        <input type="radio" class="segmented-control-input" name="kesintiViewMode" id="kesintiViewListe"
                            autocomplete="off" onchange="toggleKesintiView('liste')">
                        <label class="segmented-control-label py-1" for="kesintiViewListe">
                            <i class="bx bx-list-ul me-1"></i>Liste
                        </label>

                        <input type="radio" class="segmented-control-input" name="kesintiViewMode" id="kesintiViewGruplu"
                            autocomplete="off" checked onchange="toggleKesintiView('gruplu')">
                        <label class="segmented-control-label py-1" for="kesintiViewGruplu">
                            <i class="bx bx-grid-alt me-1"></i>Gruplu
                        </label>
                    </div>

                    <!-- İşlemler -->
                    <div class="action-container">
                        <button type="button" class="btn btn-primary d-flex align-items-center"
                            id="btnOpenKesintiModal">
                            <i class="bx bx-plus me-1 fs-5"></i> Yeni Kesinti Ekle
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <!-- Gruplu Görünüm -->
                    <table class="table table-hover mb-0 w-100" id="tblKesintilerGruplu">
                        <thead class="table-light">
                            <tr>
                                <th>Tür</th>
                                <th>Kayıt Sayısı</th>
                                <th>Toplam Tutar (Onaylı)</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped_kesintiler as $grup_adi => $grup): ?>
                                <?php $row_id = 'grp_kes_' . md5($grup_adi . rand()); ?>
                                <tr style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $row_id ?>" aria-expanded="false">
                                    <td class="fw-bold text-primary">
                                        <i class="bx bx-chevron-right me-1"></i> <?= htmlspecialchars($grup_adi) ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $grup['count'] ?> Adet</span></td>
                                    <td class="fw-bold text-danger"><?= number_format($grup['toplam_tutar'], 2, ',', '.') ?> TL</td>
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
                                                                <span class="badge bg-soft-info text-info">
                                                                    <?= $k->parametre_adi ?? ucfirst($k->tur) ?>
                                                                </span>
                                                                <?php if ($k->tur == 'icra' && $k->dosya_no): ?>
                                                                    <br><small
                                                                        class="text-muted"><?= htmlspecialchars($k->icra_dairesi . ' - ' . $k->dosya_no) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($k->tekrar_tipi == 'surekli'): ?>
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
                                                                <?php if ($k->tekrar_tipi == 'surekli'): ?>
                                                                    <small>
                                                                        <?= $k->baslangic_donemi ? date('d.m.Y', strtotime($k->baslangic_donemi)) : '-' ?>
                                                                        <i class="bx bx-right-arrow-alt"></i>
                                                                        <?= $k->bitis_donemi ? date('d.m.Y', strtotime($k->bitis_donemi)) : '<span class="text-success">Süresiz</span>' ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <?= $k->donem_adi ?? App\Helper\Helper::getDonemAdi($k->donem_id) ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                                            <td>
                                                                <?php
                                                                $durum = $k->durum ?? 'beklemede';
                                                                $durumBadges = [
                                                                    'beklemede' => '<span class="badge bg-warning text-dark"><i class="bx bx-time me-1"></i>Beklemede</span>',
                                                                    'onaylandi' => '<span class="badge bg-success"><i class="bx bx-check me-1"></i>Onaylandı</span>',
                                                                    'reddedildi' => '<span class="badge bg-danger"><i class="bx bx-x me-1"></i>Reddedildi</span>'
                                                                ];
                                                                echo $durumBadges[$durum] ?? $durumBadges['beklemede'];
                                                                ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if ($durum == 'beklemede'): ?>
                                                                    <button type="button" class="btn btn-sm btn-success btn-personel-kesinti-onayla"
                                                                        data-id="<?= $k->id ?>" title="Onayla">
                                                                        <i class="bx bx-check"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-danger btn-personel-kesinti-reddet"
                                                                        data-id="<?= $k->id ?>" title="Reddet">
                                                                        <i class="bx bx-x"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($k->tekrar_tipi == 'surekli' && $k->aktif == 1): ?>
                                                                    <button type="button" class="btn btn-sm btn-warning btn-personel-kesinti-sonlandir"
                                                                        data-id="<?= $k->id ?>" title="Sonlandır">
                                                                        <i class="bx bx-stop"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-sm btn-primary btn-personel-kesinti-duzenle"
                                                                    data-id="<?= $k->id ?>" title="Düzenle">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                                <?php if (!($k->kapali_mi ?? 0)): ?>
                                                                    <button type="button" class="btn btn-sm btn-danger btn-personel-kesinti-sil"
                                                                        data-id="<?= $k->id ?>" title="Sil">
                                                                        <i class="bx bx-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
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
                    <table class="table table-hover mb-0 datatable w-100 d-none" id="tblKesintilerListe">
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
                            <?php foreach ($kesintiler as $k): ?>
                                <?php $enc_id = Security::encrypt($k->id); ?>
                                <tr data-id="<?= $enc_id ?>">
                                    <td>
                                        <span class="badge bg-soft-info text-info">
                                            <?= $k->parametre_adi ?? ucfirst($k->tur) ?>
                                        </span>
                                        <?php if ($k->tur == 'icra' && $k->dosya_no): ?>
                                            <br><small
                                                class="text-muted"><?= htmlspecialchars($k->icra_dairesi . ' - ' . $k->dosya_no) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($k->tekrar_tipi == 'surekli'): ?>
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
                                        <?php if ($k->tekrar_tipi == 'surekli'): ?>
                                            <small>
                                                <?= $k->baslangic_donemi ? date('d.m.Y', strtotime($k->baslangic_donemi)) : '-' ?>
                                                <i class="bx bx-right-arrow-alt"></i>
                                                <?= $k->bitis_donemi ? date('d.m.Y', strtotime($k->bitis_donemi)) : '<span class="text-success">Süresiz</span>' ?>
                                            </small>
                                        <?php else: ?>
                                            <?= $k->donem_adi ?? App\Helper\Helper::getDonemAdi($k->donem_id) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($k->aciklama ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $durum = $k->durum ?? 'beklemede';
                                        $durumBadges = [
                                            'beklemede' => '<span class="badge bg-warning text-dark"><i class="bx bx-time me-1"></i>Beklemede</span>',
                                            'onaylandi' => '<span class="badge bg-success"><i class="bx bx-check me-1"></i>Onaylandı</span>',
                                            'reddedildi' => '<span class="badge bg-danger"><i class="bx bx-x me-1"></i>Reddedildi</span>'
                                        ];
                                        echo $durumBadges[$durum] ?? $durumBadges['beklemede'];
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($durum == 'beklemede'): ?>
                                            <button type="button" class="btn btn-sm btn-success btn-personel-kesinti-onayla"
                                                data-id="<?= $k->id ?>" title="Onayla">
                                                <i class="bx bx-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-kesinti-reddet"
                                                data-id="<?= $k->id ?>" title="Reddet">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($k->tekrar_tipi == 'surekli' && $k->aktif == 1): ?>
                                            <button type="button" class="btn btn-sm btn-warning btn-personel-kesinti-sonlandir"
                                                data-id="<?= $k->id ?>" title="Sonlandır">
                                                <i class="bx bx-stop"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-personel-kesinti-duzenle"
                                            data-id="<?= $k->id ?>" title="Düzenle">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <?php if (!($k->kapali_mi ?? 0)): ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-kesinti-sil"
                                                data-id="<?= $k->id ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
function toggleKesintiView(mode) {
    if (mode === 'liste') {
        document.getElementById('tblKesintilerGruplu').classList.add('d-none');
        document.getElementById('tblKesintilerListe').classList.remove('d-none');
        localStorage.setItem('kesintiViewMode', 'liste');
    } else {
        document.getElementById('tblKesintilerListe').classList.add('d-none');
        document.getElementById('tblKesintilerGruplu').classList.remove('d-none');
        localStorage.setItem('kesintiViewMode', 'gruplu');
    }
}

// Sayfa yüklendiğinde tercihi uygula
(function() {
    var savedMode = localStorage.getItem('kesintiViewMode') || 'gruplu';
    
    // Radio butonunu güncelle
    if (savedMode === 'liste') {
        if(document.getElementById('kesintiViewListe')) document.getElementById('kesintiViewListe').checked = true;
    } else {
        if(document.getElementById('kesintiViewGruplu')) document.getElementById('kesintiViewGruplu').checked = true;
    }
    
    // Görünümü uygula
    toggleKesintiView(savedMode);
})();
</script>

<!-- Kesinti Ekle Modal -->
<div class="modal fade" id="modalPersonelKesintiEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-minus-circle me-2"></i>Yeni Kesinti Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelKesintiEkle" novalidate>
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <!-- Kesinti Türü Seçimi (Parametrelerden) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kesinti Türü <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="kesinti_parametre_id" name="parametre_id" required
                            style="width: 100%">
                            <option value="">Kesinti türü seçiniz...</option>
                            <?php foreach ($kesinti_turleri_param as $param): ?>
                                <option value="<?= $param->id ?>" data-kod="<?= $param->kod ?>"
                                    data-hesaplama="<?= $param->hesaplama_tipi ?>" data-oran="<?= $param->oran ?? 0 ?>"
                                    data-tutar="<?= $param->varsayilan_tutar ?? 0 ?>">
                                    <?= htmlspecialchars($param->etiket) ?>
                                    <?php if (strpos($param->hesaplama_tipi, 'oran') !== false && $param->oran > 0): ?>
                                        (%<?= $param->oran ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                

                    <!-- Tekrar Tipi Seçimi -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kesinti Tipi</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-primary">
                                <input class="form-check-input" type="radio" name="tekrar_tipi" id="tekrar_tek_sefer"
                                    value="tek_sefer" checked>
                                <label class="form-check-label" for="tekrar_tek_sefer">
                                    <i class="bx bx-calendar-check me-1"></i> Tek Seferlik
                                </label>
                            </div>
                            <div class="form-check form-check-warning">
                                <input class="form-check-input" type="radio" name="tekrar_tipi" id="tekrar_surekli"
                                    value="surekli">
                                <label class="form-check-label" for="tekrar_surekli">
                                    <i class="bx bx-refresh me-1"></i> Sürekli (Her Ay)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Dönem Seçimi - Tek Seferlik -->
                    <div class="mb-3" id="div_tek_sefer_donem">
                        <?= Form::FormSelect2(
                            name: "kesinti_donem",
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
                    <div class="row d-none" id="div_surekli_donem">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="kesinti_baslangic_donemi"
                                name="baslangic_donemi" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bitiş Tarihi <small class="text-muted">(Boş =
                                    Süresiz)</small></label>
                            <input type="date" class="form-control" id="kesinti_bitis_donemi" name="bitis_donemi">
                        </div>
                    </div>

                    <!-- Hesaplama Tipi -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hesaplama Tipi</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hesaplama_tipi" id="hesaplama_sabit"
                                    value="sabit" checked>
                                <label class="form-check-label" for="hesaplama_sabit">
                                    <i class="bx bx-money me-1"></i> Sabit Tutar
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hesaplama_tipi"
                                    id="hesaplama_oran_net" value="oran_net">
                                <label class="form-check-label" for="hesaplama_oran_net">
                                    <i class="bx bx-percent me-1"></i> Net Maaş Üzerinden (%)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hesaplama_tipi"
                                    id="hesaplama_oran_brut" value="oran_brut">
                                <label class="form-check-label" for="hesaplama_oran_brut">
                                    <i class="bx bx-percent me-1"></i> Brüt Maaş Üzerinden (%)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tutar/Oran Girişi -->
                    <div class="row">
                        <div class="col-12 mb-3 d-none" id="div_ucretsiz_izin_secenek">
                            <label class="form-label fw-bold d-block mb-2">Kesinti Yöntemi</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="rad_kesinti_tip" id="kesinti_tip_tutar" value="tutar" checked>
                                <label class="btn btn-outline-danger" for="kesinti_tip_tutar"><i class="bx bx-lira me-1"></i> Tutar Gir</label>

                                <input type="radio" class="btn-check" name="rad_kesinti_tip" id="kesinti_tip_gun" value="gun">
                                <label class="btn btn-outline-danger" for="kesinti_tip_gun"><i class="bx bx-calendar me-1"></i> Gün Gir</label>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3 d-none" id="div_kesinti_gun">
                            <?= Form::FormFloatInput("number", "kesinti_gun", "", "0", "Gün Sayısı", "calendar", "form-control", false, null, "off", false, 'id="kesinti_gun_sayisi" min="0" step="1"') ?>
                        </div>

                        <div class="col-md-6 mb-3" id="div_tutar">
                            <?= Form::FormFloatInput("number", "kesinti_tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" min="0" id="kesinti_tutar"') ?>
                        </div>
                        <div class="col-md-6 mb-3 d-none" id="div_oran">
                            <?= Form::FormFloatInput("number", "oran", "", "0", "Oran (%)", "percent", "form-control", false, null, "off", false, 'step="0.01" id="kesinti_oran" min="0" max="100"') ?>
                        </div>
                        <!-- Tarih Seçimi -->
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("text", "kesinti_tarih", Date::today(), "Kesinti Tarihi", "Tarih", "calendar", "form-control flatpickr", true, null, "off", false) ?>
                        </div>
                    </div>

                    <!-- İcra Dosyası Seçimi -->
                    <div class="mb-3 d-none" id="div_icra_secimi">
                        <div class="form-floating form-floating-custom">
                            <select class="form-select select2" id="kesinti_icra_id" name="icra_id" style="width: 100%">
                                <option value="">Dosya seçiniz...</option>
                                <!-- AJAX ile doldurulacak -->
                            </select>
                            <label for="kesinti_icra_id">İcra Dosyası Seçin</label>
                            <div class="form-floating-icon">
                                <i data-feather="file-text"></i>
                            </div>
                        </div>
                        <div class="form-text text-warning mt-2" id="no_icra_warning" style="display:none;">
                            <i class="bx bx-info-circle me-1"></i> Aktif icra dosyası bulunamadı. Lütfen önce icra
                            dosyası ekleyin.
                        </div>
                    </div>

                    <!-- Açıklama -->
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "message-square", "form-control", false, null, "off", false, 'id="kesinti_aciklama"') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" id="btnPersonelKesintiKaydet">
                        <i class="bx bx-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>