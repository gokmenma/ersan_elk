<?php

use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasModel;

$ZimmetModel = new DemirbasZimmetModel();
$DemirbasModel = new DemirbasModel();

// Personelin zimmetlerini getir
$zimmetler = $ZimmetModel->getByPersonel($id);
$demirbaslar = $DemirbasModel->getInStock();

// İstatistikler ve Kategoriler
$aktifZimmet = 0;
$iadeEdilen = 0;
$kategoriler = [];
$grouped_zimmetler = [];

foreach ($zimmetler as $z) {
    if ($z->durum === 'teslim') {
        $aktifZimmet++;
    } else {
        $iadeEdilen++;
    }
    
    // Kategori listesi oluştur
    $kat = $z->kategori_adi ?? 'Kategorisiz';
    if (!in_array($kat, $kategoriler)) {
        $kategoriler[] = $kat;
    }
    
    // Gruplama
    if (!isset($grouped_zimmetler[$kat])) {
        $grouped_zimmetler[$kat] = [
            'items' => [],
            'count' => 0
        ];
    }
    $grouped_zimmetler[$kat]['items'][] = $z;
    $grouped_zimmetler[$kat]['count']++;
}
sort($kategoriler);
?>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <h5 class="card-title mb-0 text-primary"><i data-feather="package" class="me-2 icon-sm"></i>Zimmet
                            İşlemleri</h5>
                        <span class="badge bg-warning"><?= $aktifZimmet ?> Aktif</span>
                        <span class="badge bg-success"><?= $iadeEdilen ?> İade</span>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="zimmetViewMode" id="zimmetViewListe" autocomplete="off" onchange="toggleZimmetView('liste')">
                            <label class="btn btn-outline-secondary" for="zimmetViewListe"><i class="bx bx-list-ul me-1"></i>Liste</label>

                            <input type="radio" class="btn-check" name="zimmetViewMode" id="zimmetViewGruplu" autocomplete="off" checked onchange="toggleZimmetView('gruplu')">
                            <label class="btn btn-outline-secondary" for="zimmetViewGruplu"><i class="bx bx-grid-alt me-1"></i>Gruplu</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="btnOpenZimmetModal">
                            <i data-feather="plus" class="icon-xs"></i> Yeni Zimmet Ver
                        </button>
                    </div>
                </div>
                
                <!-- Kategori Filtreleri -->
                <div class="d-flex gap-2 overflow-auto pb-1" id="zimmetKategoriFiltreleri">
                    <button type="button" class="btn btn-sm btn-soft-primary active" onclick="filterZimmetKategori('all', this)">
                        Tümü
                    </button>
                    <?php foreach ($kategoriler as $kat): ?>
                        <button type="button" class="btn btn-sm btn-soft-secondary" onclick="filterZimmetKategori('<?= htmlspecialchars($kat) ?>', this)">
                            <?= htmlspecialchars($kat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <!-- Gruplu Görünüm -->
                    <table id="tblZimmetlerGruplu" class="table table-hover mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th>Kayıt Sayısı</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped_zimmetler as $kat_adi => $grup): ?>
                                <?php $row_id = 'grp_zim_' . md5($kat_adi . rand()); ?>
                                <tr class="group-header" data-kategori="<?= htmlspecialchars($kat_adi) ?>" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $row_id ?>" aria-expanded="false">
                                    <td class="fw-bold text-primary">
                                        <i class="bx bx-chevron-right me-1"></i> <?= htmlspecialchars($kat_adi) ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $grup['count'] ?> Adet</span></td>
                                    <td class="text-end"><i class="bx bx-chevron-down"></i></td>
                                </tr>
                                <tr class="collapse group-content" id="<?= $row_id ?>" data-kategori="<?= htmlspecialchars($kat_adi) ?>">
                                    <td colspan="3" class="p-0">
                                        <div class="p-3 bg-light">
                                            <table class="table table-sm table-bordered bg-white mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Demirbaş Adı</th>
                                                        <th>Marka/Model</th>
                                                        <th class="text-center">Miktar</th>
                                                        <th>Teslim Tarihi</th>
                                                        <th>İade Tarihi</th>
                                                        <th class="text-center">Durum</th>
                                                        <th class="text-center">İşlem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($grup['items'] as $zimmet): ?>
                                                        <?php
                                                        $enc_id = Security::encrypt($zimmet->id);
                                                        $teslimTarihi = date('d.m.Y', strtotime($zimmet->teslim_tarihi));
                                                        $iadeTarihi = $zimmet->iade_tarihi ? date('d.m.Y', strtotime($zimmet->iade_tarihi)) : '-';
                                                        ?>
                                                        <tr data-id="<?= $enc_id ?>">
                                                            <td class="fw-medium"><?= htmlspecialchars($zimmet->demirbas_adi ?? '-') ?></td>
                                                            <td><?= htmlspecialchars(($zimmet->marka ?? '') . ' ' . ($zimmet->model ?? '')) ?></td>
                                                            <td class="text-center"><?= $zimmet->teslim_miktar ?? 1 ?></td>
                                                            <td><?= $teslimTarihi ?></td>
                                                            <td><?= $iadeTarihi ?></td>
                                                            <td class="text-center">
                                                                <?php if ($zimmet->durum === 'teslim'): ?>
                                                                    <span class="badge bg-warning">Zimmetli</span>
                                                                <?php elseif ($zimmet->durum === 'iade'): ?>
                                                                    <span class="badge bg-success">İade Edildi</span>
                                                                <?php elseif ($zimmet->durum === 'kayip'): ?>
                                                                    <span class="badge bg-danger">Kayıp</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary"><?= htmlspecialchars($zimmet->durum) ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center text-nowrap">
                                                                <?php if ($zimmet->durum === 'teslim'): ?>
                                                                    <button type="button" class="btn btn-sm btn-info btn-personel-zimmet-iade"
                                                                        data-id="<?= $enc_id ?>"
                                                                        data-demirbas="<?= htmlspecialchars($zimmet->demirbas_adi ?? '') ?>"
                                                                        data-miktar="<?= $zimmet->teslim_miktar ?? 1 ?>" title="İade Al">
                                                                        <i data-feather="rotate-ccw" class="icon-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-sm btn-danger btn-personel-zimmet-sil"
                                                                    data-id="<?= $enc_id ?>" title="Sil">
                                                                    <i data-feather="trash-2" class="icon-xs"></i>
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
                    <table id="tblZimmetlerListe" class="table datatable table-hover mb-0 w-100 d-none">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th>Demirbaş Adı</th>
                                <th>Marka/Model</th>
                                <th class="text-center">Miktar</th>
                                <th>Teslim Tarihi</th>
                                <th>İade Tarihi</th>
                                <th class="text-center">Durum</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($zimmetler)): ?>
                                <?php foreach ($zimmetler as $zimmet): ?>
                                    <?php
                                    $enc_id = Security::encrypt($zimmet->id);
                                    $teslimTarihi = date('d.m.Y', strtotime($zimmet->teslim_tarihi));
                                    $iadeTarihi = $zimmet->iade_tarihi ? date('d.m.Y', strtotime($zimmet->iade_tarihi)) : '-';
                                    ?>
                                    <tr data-id="<?= $enc_id ?>" data-kategori="<?= htmlspecialchars($zimmet->kategori_adi ?? 'Kategorisiz') ?>">
                                        <td>
                                            <span class="badge bg-soft-primary text-primary">
                                                <?= htmlspecialchars($zimmet->kategori_adi ?? 'Kategorisiz') ?>
                                            </span>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($zimmet->demirbas_adi ?? '-') ?></td>
                                        <td><?= htmlspecialchars(($zimmet->marka ?? '') . ' ' . ($zimmet->model ?? '')) ?></td>
                                        <td class="text-center"><?= $zimmet->teslim_miktar ?? 1 ?></td>
                                        <td><?= $teslimTarihi ?></td>
                                        <td><?= $iadeTarihi ?></td>
                                        <td class="text-center">
                                            <?php if ($zimmet->durum === 'teslim'): ?>
                                                <span class="badge bg-warning">Zimmetli</span>
                                            <?php elseif ($zimmet->durum === 'iade'): ?>
                                                <span class="badge bg-success">İade Edildi</span>
                                            <?php elseif ($zimmet->durum === 'kayip'): ?>
                                                <span class="badge bg-danger">Kayıp</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($zimmet->durum) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <?php if ($zimmet->durum === 'teslim'): ?>
                                                <button type="button" class="btn btn-sm btn-info btn-personel-zimmet-iade"
                                                    data-id="<?= $enc_id ?>"
                                                    data-demirbas="<?= htmlspecialchars($zimmet->demirbas_adi ?? '') ?>"
                                                    data-miktar="<?= $zimmet->teslim_miktar ?? 1 ?>" title="İade Al">
                                                    <i data-feather="rotate-ccw" class="icon-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-personel-zimmet-sil"
                                                data-id="<?= $enc_id ?>" title="Sil">
                                                <i data-feather="trash-2" class="icon-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleZimmetView(mode) {
    if (mode === 'liste') {
        document.getElementById('tblZimmetlerGruplu').classList.add('d-none');
        document.getElementById('tblZimmetlerListe').classList.remove('d-none');
        localStorage.setItem('zimmetViewMode', 'liste');
    } else {
        document.getElementById('tblZimmetlerListe').classList.add('d-none');
        document.getElementById('tblZimmetlerGruplu').classList.remove('d-none');
        localStorage.setItem('zimmetViewMode', 'gruplu');
    }
}

function filterZimmetKategori(kategori, btn) {
    // Buton aktiflik durumu
    var buttons = document.querySelectorAll('#zimmetKategoriFiltreleri button');
    buttons.forEach(b => {
        b.classList.remove('active', 'btn-soft-primary');
        b.classList.add('btn-soft-secondary');
    });
    btn.classList.remove('btn-soft-secondary');
    btn.classList.add('active', 'btn-soft-primary');

    // Gruplu görünüm filtresi
    var groupHeaders = document.querySelectorAll('.group-header');
    var groupContents = document.querySelectorAll('.group-content');
    
    groupHeaders.forEach(row => {
        if (kategori === 'all' || row.dataset.kategori === kategori) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
    
    // Açık olan detayları da gizle/göster
    groupContents.forEach(row => {
        if (kategori === 'all' || row.dataset.kategori === kategori) {
            // Eğer collapse açıksa görünür kalmalı
        } else {
            // Filtre dışıysa kapatılabilir ama bootstrap collapse olduğu için d-none yeterli
            // row.classList.remove('show');
        }
    });

    // Liste görünüm filtresi
    var listRows = document.querySelectorAll('#tblZimmetlerListe tbody tr');
    listRows.forEach(row => {
        if (kategori === 'all' || row.dataset.kategori === kategori) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
}

// Sayfa yüklendiğinde tercihi uygula
(function() {
    var savedMode = localStorage.getItem('zimmetViewMode') || 'gruplu';
    
    // Radio butonunu güncelle
    if (savedMode === 'liste') {
        if(document.getElementById('zimmetViewListe')) document.getElementById('zimmetViewListe').checked = true;
    } else {
        if(document.getElementById('zimmetViewGruplu')) document.getElementById('zimmetViewGruplu').checked = true;
    }
    
    // Görünümü uygula
    toggleZimmetView(savedMode);
})();
</script>

<!-- Yeni Zimmet Ekle Modal -->
<div class="modal fade" id="modalPersonelZimmetEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i data-feather="repeat" class="me-2"></i>Personele Zimmet Ver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelZimmetEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="alert alert-info p-2 mb-3">
                        <small><i class="bx bx-info-circle me-1"></i> Zimmetlenen miktar stoktan düşülecektir.</small>
                    </div>

                    <div class="mb-3">
                        <?php
                        $demirbasOptions = [];
                        $demirbasMap = [];
                        foreach ($demirbaslar as $d) {
                            $text = ($d->demirbas_no ?? '-') . ' - ' . $d->demirbas_adi . ' (' . ($d->kategori_adi ?? '-') . ') - Kalan: ' . ($d->kalan_miktar ?? 1);
                            $demirbasOptions[$d->id] = $text;
                            $demirbasMap[$d->id] = $d->kalan_miktar ?? 1;
                        }
                        echo Form::FormSelect2(
                            'demirbas_id',
                            $demirbasOptions,
                            '',
                            'Demirbaş Seçin *',
                            'package',
                            'key',
                            '',
                            'form-select select2',
                            true,
                            'width:100%',
                            'data-stok=\'' . json_encode($demirbasMap, JSON_FORCE_OBJECT) . '\''
                        );
                        ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput(
                                'number',
                                'teslim_miktar',
                                '1',
                                'Miktar',
                                'Teslim Miktarı *',
                                'hash',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'min="1"'
                            ) ?>
                            <div class="mt-1">
                                <small class="text-muted">Stoktaki Kalan: <span id="personelKalanMiktar"
                                        class="fw-bold">-</span></small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput(
                                'text',
                                'teslim_tarihi',
                                date('d.m.Y'),
                                'Tarih',
                                'Teslim Tarihi *',
                                'calendar',
                                'form-control flatpickr',
                                true,
                                null,
                                'on',
                                false
                            ) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            'aciklama',
                            '',
                            'Notlar...',
                            'Açıklama',
                            'file-text',
                            'form-control',
                            false,
                            '80px',
                            2
                        ) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning" id="btnPersonelZimmetKaydet">
                        <i data-feather="check" class="me-1 icon-xs"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İade Modal -->
<div class="modal fade" id="modalPersonelIade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i data-feather="rotate-ccw" class="me-2"></i>Zimmet İade Al</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelIade">
                <input type="hidden" name="zimmet_id" id="personel_iade_zimmet_id">
                <div class="modal-body">
                    <div class="alert alert-secondary mb-3">
                        <strong>Demirbaş:</strong> <span id="personel_iade_demirbas_adi">-</span><br>
                        <strong>Teslim Miktarı:</strong> <span id="personel_iade_miktar_goster">-</span>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput(
                                'number',
                                'iade_miktar',
                                '1',
                                'Miktar',
                                'İade Miktarı *',
                                'hash',
                                'form-control',
                                true,
                                null,
                                'on',
                                false,
                                'min="1"'
                            ) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput(
                                'text',
                                'iade_tarihi',
                                date('d.m.Y'),
                                'Tarih',
                                'İade Tarihi *',
                                'calendar',
                                'form-control flatpickr',
                                true,
                                null,
                                'on',
                                false
                            ) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            'iade_aciklama',
                            '',
                            'İade notu...',
                            'Açıklama',
                            'file-text',
                            'form-control',
                            false,
                            '80px',
                            2
                        ) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-info" id="btnPersonelIadeKaydet">
                        <i data-feather="rotate-ccw" class="me-1 icon-xs"></i>İade Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>