<?php

use App\Model\DuyuruModel;
use App\Model\PersonelModel;
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;

$duyuruModel = new DuyuruModel();
$personelModel = new PersonelModel();

$duyurular = $duyuruModel->getAll();
$stats = $duyuruModel->getStats();

$personeller = $personelModel->all(true);
$personelList = [];
foreach ($personeller as $p) {
    $personelList[$p->id] = $p->adi_soyadi;
}

?>

<div class="container-fluid">
    <?php
    $maintitle = "Kurumsal";
    $title = "Duyuru ve Etkinlik Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst İstatistik Kartları -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #667eea; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(102, 126, 234, 0.1);">
                            <i class="bx bx-news fs-4 text-primary"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TOPLAM</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->toplam ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #10b981; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="bx bx-check-circle fs-4 text-success"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">AKTİF</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->aktif ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                            <i class="bx bx-time-five fs-4 text-danger"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SÜRESİ DOLMUŞ</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->suresi_dolmus ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 15px;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">Duyuru Listesi</h5>
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">

                <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2"
                    id="btnExportExcel" title="Excel'e Aktar">
                    <i class="mdi mdi-file-excel fs-5"></i> Excele Aktar
                </button>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm text-nowrap"
                    data-bs-toggle="modal" data-bs-target="#duyuruModal" onclick="resetForm()" title="Yeni Duyuru">
                    <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="duyuruTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Başlık</th>
                            <th>İçerik</th>
                            <th>Hedef</th>
                            <th>Kitle</th>
                            <th>Yayın / Etkinlik Tarihi</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duyurular as $idx => $d): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($d->resim): ?>
                                            <img src="<?= $d->resim ?>" class="rounded me-2"
                                                style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                style="width: 40px; height: 40px;">
                                                <i class="bx bx-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fw-bold"><?= htmlspecialchars($d->baslik) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;"
                                        title="<?= htmlspecialchars($d->icerik) ?>">
                                        <?= htmlspecialchars($d->icerik) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <?php if ($d->ana_sayfada_goster): ?>
                                            <span class="badge bg-soft-primary text-primary px-2 py-1"
                                                style="font-size: 10px;">Admin Ana Sayfa</span>
                                        <?php endif; ?>
                                        <?php if ($d->pwa_goster): ?>
                                            <span class="badge bg-soft-info text-info px-2 py-1"
                                                style="font-size: 10px;">Personel Ana Sayfa</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($d->alici_tipi == 'toplu'): ?>
                                        <span class="badge bg-soft-success text-success">Tüm Personel</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-warning text-warning" title="<?= $d->alici_ids ?>">Özel
                                            (<?= count(explode(',', $d->alici_ids)) ?> kişi)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted">Yayın: <?= date('d.m.Y', strtotime($d->tarih)) ?></div>
                                    <?php if ($d->etkinlik_tarihi): ?>
                                        <div class="fw-bold text-primary">Etk:
                                            <?= date('d.m.Y', strtotime($d->etkinlik_tarihi)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d->durum === 'Taslak'): ?>
                                        <div class="mb-1"><span class="badge bg-secondary">Taslak</span></div>
                                    <?php elseif ($d->durum === 'Kapalı'): ?>
                                        <div class="mb-1"><span class="badge bg-dark">Kapalı</span></div>
                                    <?php else: ?>
                                        <div class="mb-1"><span class="badge bg-success">Yayında</span></div>
                                    <?php endif; ?>

                                    <?php
                                    $isExpired = $d->etkinlik_tarihi && $d->etkinlik_tarihi < date('Y-m-d');
                                    if ($isExpired): ?>
                                        <span class="badge bg-danger">Süresi Doldu</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Güncel</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-soft-primary btn-sm btn-edit" data-id="<?= $d->id ?>">
                                        <i class="bx bx-edit-alt"></i>
                                    </button>
                                    <button class="btn btn-soft-danger btn-sm btn-delete" data-id="<?= $d->id ?>">
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

<!-- Modal -->
<div class="modal fade" id="duyuruModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 45px; height: 45px;">
                        <i data-feather="plus-circle" class="text-success" style="width: 24px; height: 24px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle">Yeni Duyuru Ekle</h5>
                        <small class="text-muted">Yeni kayıt oluşturmak için bilgileri doldurun.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="duyuruForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="duyuruId">
                <input type="hidden" name="action" value="save">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <?= Form::FormFloatInput('text', 'baslik', '', 'Duyuru başlığı...', 'Başlık *', 'type', 'form-control', true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatTextarea('icerik', '', 'Duyuru içeriği...', 'İçerik', 'align-left', 'form-control', false, '100px') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('date', 'etkinlik_tarihi', '', '', 'Etkinlik/Bitiş Tarihi', 'calendar', 'form-control', false) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormSelect2('durum', ['Yayında' => 'Yayında', 'Taslak' => 'Taslak', 'Kapalı' => 'Kapalı'], 'Yayında', 'Yayın Durumu', 'activity', 'key', '', 'form-select select2') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Duyuru Resmi</label>
                            <div id="imageUploadZone"
                                class="border-2 border-dashed rounded-3 p-3 text-center position-relative bg-light"
                                style="cursor: pointer; transition: all 0.2s; border-color: #dee2e6 !important;">
                                <input type="file" name="resim" id="duyuruResim" accept="image/*"
                                    class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                    style="cursor:pointer; z-index:2;">
                                <div id="uploadPlaceholder">
                                    <i class="mdi mdi-image-plus text-primary" style="font-size: 2rem;"></i>
                                    <p class="fw-semibold mb-0">Duyuru Görseli Seçin veya Sürükleyin</p>
                                    <p class="text-muted small mb-0">Optimal görünüm için yatay görseller önerilir
                                        (.jpg, .png)</p>
                                </div>
                                <div id="uploadPreview" class="d-none">
                                    <i class="mdi mdi-check-circle text-success" style="font-size: 2rem;"></i>
                                    <p class="fw-semibold mb-0" id="fileNameDisplay">Görsel Seçildi</p>
                                    <p class="text-muted small mb-0">Değiştirmek için tekrar tıklayın veya sürükleyin
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded border">
                                <label class="fw-bold mb-2">Görünürlük</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="ana_sayfada_goster"
                                            id="ana_sayfada_goster">
                                        <label class="form-check-label" for="ana_sayfada_goster">Admin Ana
                                            Sayfası</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pwa_goster"
                                            id="pwa_goster">
                                        <label class="form-check-label" for="pwa_goster">Personel Ana Sayfası
                                            (PWA)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded border">
                                <label class="fw-bold mb-2">Hedef Kitle</label>
                                <div class="d-flex gap-3 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="alici_tipi" id="tipToplu"
                                            value="toplu" checked>
                                        <label class="form-check-label" for="tipToplu">Tüm Personeller</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="alici_tipi" id="tipTekli"
                                            value="tekli">
                                        <label class="form-check-label" for="tipTekli">Belirli Personeller</label>
                                    </div>
                                </div>
                                <div id="personelSecimContainer" style="display:none;">
                                    <?= Form::FormMultipleSelect2('personel_ids', $personelList, [], 'Personel Seçiniz', 'users', 'key', '', 'form-select select2') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput('text', 'hedef_sayfa', '', 'Eğer bir sayfaya yönlendirilecekse URL girin...', 'Hedef URL (Opsiyonel)', 'link', 'form-control') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-3"
                    style="border-bottom-left-radius: 15px; border-bottom-right-radius: 15px;">
                    <button type="button" class="btn btn-vazgec px-4" data-bs-dismiss="modal">
                        <i data-feather="x" class="me-1" style="width: 16px; height: 16px;"></i> Vazgeç
                    </button>
                    <button type="submit" class="btn btn-kaydet px-4">
                        <i data-feather="save" class="me-1" style="width: 16px; height: 16px;"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        const API_URL = 'views/duyuru/api.php';
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Flatpickr Başlatma
        if ($('input[name="etkinlik_tarihi"]').length > 0) {
            $('input[name="etkinlik_tarihi"]').flatpickr({
                locale: 'tr',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y'
            });
        }

        // Resim Yükleme Önizleme
        $('#duyuruResim').on('change', function () {
            const fileName = $(this).val().split('\\').pop();
            if (fileName) {
                $('#uploadPlaceholder').addClass('d-none');
                $('#uploadPreview').removeClass('d-none');
                $('#fileNameDisplay').text(fileName);
            } else {
                $('#uploadPlaceholder').removeClass('d-none');
                $('#uploadPreview').addClass('d-none');
            }
        });

        let dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        dtOptions.buttons = [
            {
                extend: 'excel',
                text: 'Excel',
                className: 'd-none',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            }
        ];

        let localTable = $('#duyuruTable').DataTable(dtOptions);

        $('#btnExportExcel').click(function () {
            localTable.button('.buttons-excel').trigger();
        });

        $('input[name="alici_tipi"]').change(function () {
            if ($(this).val() === 'tekli') {
                $('#personelSecimContainer').slideDown();
            } else {
                $('#personelSecimContainer').slideUp();
            }
        });

        $('#duyuruForm').submit(function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            Swal.fire({
                title: 'Kaydediliyor...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-edit', function () {
            const id = $(this).data('id');
            resetForm();

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get&id=' + id
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        $('#duyuruId').val(d.id);
                        $('#modalTitle').text('Duyuruyu Düzenle');
                        $('input[name="baslik"]').val(d.baslik);
                        $('textarea[name="icerik"]').val(d.icerik);
                        if (d.etkinlik_tarihi) {
                            $('input[name="etkinlik_tarihi"]').val(d.etkinlik_tarihi).trigger('change');
                        }
                        $('input[name="hedef_sayfa"]').val(d.hedef_sayfa);
                        if (d.durum) {
                            $('select[name="durum"]').val(d.durum).trigger('change');
                        }

                        if (d.ana_sayfada_goster == 1) $('#ana_sayfada_goster').prop('checked', true);
                        if (d.pwa_goster == 1) $('#pwa_goster').prop('checked', true);

                        if (d.alici_tipi == 'tekli') {
                            $('#tipTekli').prop('checked', true).trigger('change');
                            const ids = d.alici_ids.split(',');
                            $('#personel_ids').val(ids).trigger('change');
                        } else {
                            $('#tipToplu').prop('checked', true).trigger('change');
                        }

                        $('#duyuruModal').modal('show');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-delete', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu duyuru silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete&id=' + id
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('Silindi!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Hata', data.message, 'error');
                            }
                        });
                }
            });
        });
    });

    function resetForm() {
        $('#duyuruForm')[0].reset();
        $('#duyuruId').val('');
        $('#modalTitle').text('Yeni Duyuru Ekle');
        $('select[name="durum"]').val('Yayında').trigger('change');
        const fp = document.querySelector('input[name="etkinlik_tarihi"]')._flatpickr;
        if (fp) fp.clear();
        $('#uploadPlaceholder').removeClass('d-none');
        $('#uploadPreview').addClass('d-none');
        $('#personelSecimContainer').hide();
        $('#personel_ids').val(null).trigger('change');
    }
</script>

<style>
    .bg-soft-primary {
        background-color: rgba(102, 126, 234, 0.1);
    }

    .bg-soft-success {
        background-color: rgba(16, 185, 129, 0.1);
    }

    .bg-soft-info {
        background-color: rgba(14, 165, 233, 0.1);
    }

    .bg-soft-warning {
        background-color: rgba(245, 158, 11, 0.1);
    }

    .bg-soft-danger {
        background-color: rgba(244, 63, 94, 0.1);
    }

    .btn-soft-primary {
        color: #667eea;
        background-color: rgba(102, 126, 234, 0.1);
        border: none;
    }

    .btn-soft-primary:hover {
        background-color: #667eea;
        color: white;
    }

    .btn-soft-danger {
        color: #f43f5e;
        background-color: rgba(244, 63, 94, 0.1);
        border: none;
    }

    .btn-soft-danger:hover {
        background-color: #f43f5e;
        color: white;
    }

    .btn-white {
        background: white;
        color: #667eea;
        border: none;
    }

    .btn-white:hover {
        background: #f8f9fa;
        color: #764ba2;
    }

    .bg-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
    }

    /* Özel Buton Stilleri */
    #imageUploadZone:hover {
        border-color: #556ee6 !important;
        background-color: rgba(85, 110, 230, 0.05) !important;
    }

    .btn-vazgec {
        background-color: #74788d;
        color: #fff;
        border: none;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-vazgec:hover {
        background-color: #636678;
        color: #fff;
        transform: translateY(-1px);
    }

    .btn-kaydet {
        background-color: #2a3042;
        color: #fff;
        border: none;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-kaydet:hover {
        background-color: #1f2431;
        color: #fff;
        transform: translateY(-1px);
    }

    .modal-content {
        overflow: hidden;
    }
</style>