<?php
use App\Helper\Security;
use App\Helper\Form;
use App\Model\IcraDaireleriModel;

$IcraDaireleriModel = new IcraDaireleriModel();
$icraDaireleri = $IcraDaireleriModel->where('aktif', 1);
$icraDaireleriOptions = ['' => 'Seçiniz veya Yazınız...'];
$icraDaireleriData = [];
foreach($icraDaireleri as $daire) {
    $icraDaireleriOptions[$daire->daire_adi] = $daire->daire_adi;
    $icraDaireleriData[$daire->daire_adi] = [
        'iban' => $daire->iban ?? '',
        'vergi_dairesi' => $daire->vergi_dairesi ?? '',
        'vergi_no' => $daire->vergi_no ?? '',
        'il' => $daire->il ?? '',
        'ilce' => $daire->ilce ?? ''
    ];
}
?>
<?php

// Personel ID (Manage.php'den geliyor olmalı, gelmiyorsa decrypt et)
if (!isset($id) || empty($id)) {
    $id = Security::decrypt($_GET['id'] ?? 0);
}

// İstatistikler
$aktifIcra = 0;
$toplamBorc = 0;
$toplamKesilen = 0;
$toplamKalan = 0;
$nextSira = 1;
if (!empty($icralar)) {
    foreach ($icralar as $i) {
        if ($i->durum === 'devam_ediyor') {
            $aktifIcra++;
        }
        $toplamBorc += floatval($i->toplam_borc);
        $toplamKesilen += floatval($i->toplam_kesilen);
        $toplamKalan += floatval($i->kalan_tutar);
        if (intval($i->sira) >= $nextSira) {
            $nextSira = intval($i->sira) + 1;
        }
    }
}
?>

<style>
    /* Tablo Düzeni */
    .table-responsive,
    .card-body,
    .tab-pane,
    .dataTables_wrapper {
        overflow: visible !important;
    }

    .datatable-icra td {
        vertical-align: middle;
    }

    .dropdown-toggle::after {
        display: none !important;
    }

    .btn-action-trigger {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: #64748b;
        transition: all 0.2s;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 0;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .btn-action-trigger:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Icra Kart Başlığı */
    .icra-header-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, rgba(226, 189, 97, 0.2) 0%, rgba(226, 189, 97, 0.05) 100%);
        color: #e2bd61;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(226, 189, 97, 0.2);
    }

    .icra-badge-active {
        background: rgba(52, 195, 143, 0.15) !important;
        color: #34c38f !important;
        border: 1px solid rgba(52, 195, 143, 0.3) !important;
    }

    .icra-badge-warning {
        background: rgba(241, 180, 76, 0.15) !important;
        color: #f1b44c !important;
        border: 1px solid rgba(241, 180, 76, 0.3) !important;
    }

    .icra-badge-secondary {
        background: rgba(116, 120, 141, 0.15) !important;
        color: #74788d !important;
        border: 1px solid rgba(116, 120, 141, 0.3) !important;
    }

    .icra-badge-info {
        background: rgba(80, 165, 241, 0.15) !important;
        color: #50a5f1 !important;
        border: 1px solid rgba(80, 165, 241, 0.3) !important;
    }

    .icra-badge-danger {
        background: rgba(244, 106, 106, 0.15) !important;
        color: #f46a6a !important;
        border: 1px solid rgba(244, 106, 106, 0.3) !important;
    }

    .icra-badge-purple {
        background: rgba(155, 89, 182, 0.15) !important;
        color: #9b59b6 !important;
        border: 1px solid rgba(155, 89, 182, 0.3) !important;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 1cm;
        }

        /* Hide everything by default */
        body * {
            visibility: hidden;
        }

        /* Show only the modal and its content */
        #modalIcraListeYazdir,
        #modalIcraListeYazdir * {
            visibility: visible;
        }

        /* Reset modal positioning for print */
        #modalIcraListeYazdir {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            background: #fff !important;
        }

        /* Essential resets */
        .modal-dialog {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .modal-content {
            border: none !important;
            box-shadow: none !important;
        }

        .modal-backdrop {
            display: none !important;
        }

        .no-print,
        .modal-header .btn-close,
        .modal-footer {
            display: none !important;
        }

        /* Table styles for print */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #000 !important;
            padding: 4px !important;
            font-size: 10pt !important;
            color: #000 !important;
        }

        .bg-light {
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }

    .print-header {
        border-bottom: 2px solid #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
    }

    .print-title {
        font-size: 20px;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card border border-light shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                    <div class="icra-header-icon me-3">
                        <i data-feather="folder-plus" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">İcra Dosyaları</h5>
                        <p class="text-muted mb-0 small"><?= $aktifIcra ?> Adet Aktif Dosya Takibi</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button"
                        class="btn btn-outline-primary btn-sm fw-bold px-3 d-flex align-items-center shadow-sm rounded-pill"
                        id="btnIcraListYazdir">
                        <i data-feather="printer" class="me-1" style="width: 14px; height: 14px;"></i> Yazdır
                    </button>
                    <button type="button"
                        class="btn btn-warning btn-sm fw-bold px-3 d-flex align-items-center shadow-sm rounded-pill"
                        id="btnOpenIcraModal" data-next-sira="<?= $nextSira ?>">
                        <i data-feather="plus-circle" class="me-1" style="width: 14px; height: 14px;"></i> Dosya Ekle
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 datatable datatable-icra w-100 align-middle">
                        <thead>
                            <tr class="bg-light bg-opacity-50 border-bottom">
                                <th style="width:70px" class="text-center py-3 text-muted small fw-bold text-uppercase">
                                    Sıra</th>
                                <th class="text-muted small fw-bold text-uppercase">Kurum / Dosya Bilgisi</th>
                                <th class="text-muted small fw-bold text-uppercase">Toplam Borç</th>
                                <th class="text-muted small fw-bold text-uppercase">Aylık Kesinti</th>
                                <th class="text-muted small fw-bold text-uppercase text-primary">Kesilen</th>
                                <th class="text-muted small fw-bold text-uppercase text-danger">Kalan</th>
                                <th class="text-muted small fw-bold text-uppercase">Başlangıç</th>
                                <th class="text-muted small fw-bold text-uppercase">Bitiş</th>
                                <th class="text-center text-muted small fw-bold text-uppercase">Durum</th>
                                <th class="text-center text-muted small fw-bold text-uppercase" style="width: 80px;">
                                    İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($icralar as $i): ?>
                                <tr>
                                    <td class="text-center fw-bold text-secondary"><?= $i->sira ?></td>
                                    <td class="btn-icra-duzenle" style="cursor: pointer;" data-id="<?= $i->id ?>">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($i->icra_dairesi) ?></div>
                                        <div class="text-muted small d-flex align-items-center mt-1">
                                            <i data-feather="file-text" class="me-1 text-muted"
                                                style="width: 12px; height: 12px;"></i>
                                            <?= htmlspecialchars($i->dosya_no) ?>
                                        </div>
                                        <?php if(!empty($i->iban)): ?>
                                            <div class="text-primary small mt-1">
                                                <i data-feather="credit-card" class="me-1" style="width: 12px; height: 12px;"></i>
                                                <b>IBAN:</b> <?= htmlspecialchars($i->iban) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($i->hesap_bilgileri)): ?>
                                            <div class="text-info small mt-1">
                                                <i data-feather="info" class="me-1" style="width: 12px; height: 12px;"></i>
                                                <b>Hesap:</b> <?= htmlspecialchars($i->hesap_bilgileri) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-dark"><?= number_format($i->toplam_borc, 2, ',', '.') ?> <small
                                             class="text-muted">TL</small></td>
                                    <td>
                                        <div class="badge bg-light text-dark fw-medium">
                                            <?php if (($i->kesinti_tipi ?? 'tutar') === 'tutar'): ?>
                                                <?= number_format($i->aylik_kesinti_tutari, 2, ',', '.') ?> TL
                                            <?php elseif ($i->kesinti_tipi === 'net_yuzde'): ?>
                                                %<?= number_format($i->kesinti_orani, 2, ',', '.') ?> (Net)
                                            <?php elseif ($i->kesinti_tipi === 'asgari_yuzde'): ?>
                                                %<?= number_format($i->kesinti_orani, 2, ',', '.') ?> (Asgari)
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-primary fw-bold btn-icra-kesinti-detay" style="cursor: pointer;" data-id="<?= $i->id ?>" data-icra-dairesi="<?= htmlspecialchars($i->icra_dairesi) ?>" data-dosya-no="<?= htmlspecialchars($i->dosya_no) ?>" data-toplam-borc="<?= $i->toplam_borc ?>">
                                        <?= number_format($i->toplam_kesilen, 2, ',', '.') ?>
                                        <small>TL</small>
                                    </td>
                                    <td>
                                        <?php
                                        $kalanTutar = floatval($i->kalan_tutar);
                                        $kalanClass = $kalanTutar > 0 ? 'text-danger' : 'text-success';
                                        ?>
                                        <span
                                            class="fw-bold <?= $kalanClass ?>"><?= number_format($kalanTutar, 2, ',', '.') ?>
                                            <small>TL</small></span>
                                    </td>
                                    <td class="text-muted small">
                                        <i data-feather="calendar" class="me-1" style="width: 12px; height: 12px;"></i>
                                        <?= ($i->baslangic_tarihi && $i->baslangic_tarihi != '0000-00-00') ? date('d.m.Y', strtotime($i->baslangic_tarihi)) : '-' ?>
                                    </td>
                                    <td class="text-muted small">
                                        <i data-feather="calendar" class="me-1" style="width: 12px; height: 12px;"></i>
                                        <?= ($i->bitis_tarihi && $i->bitis_tarihi != '0000-00-00') ? date('d.m.Y', strtotime($i->bitis_tarihi)) : '-' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        switch ($i->durum) {
                                            case 'bekliyor':
                                                echo '<span class="badge icra-badge-warning rounded-pill px-3 py-2">Bekliyor</span>';
                                                break;
                                            case 'devam_ediyor':
                                                echo '<span class="badge icra-badge-active rounded-pill px-3 py-2">Devam Ediyor</span>';
                                                break;
                                            case 'fekki_geldi':
                                                echo '<span class="badge icra-badge-info rounded-pill px-3 py-2">Fekki Geldi</span>';
                                                break;
                                            case 'kesinti_bitti':
                                                echo '<span class="badge icra-badge-purple rounded-pill px-3 py-2">Kesinti Bitti</span>';
                                                break;
                                            case 'bitti':
                                                echo '<span class="badge icra-badge-secondary rounded-pill px-3 py-2">Tamamlandı</span>';
                                                break;
                                            case 'durduruldu':
                                                echo '<span class="badge icra-badge-danger rounded-pill px-3 py-2">Durduruldu</span>';
                                                break;
                                            default:
                                                echo '<span class="badge icra-badge-secondary rounded-pill px-3 py-2">' . htmlspecialchars($i->durum) . '</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn-action-trigger shadow-none" type="button"
                                                data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                                <i data-feather="more-horizontal" style="width: 18px; height: 18px;"></i>
                                            </button>
                                            <ul
                                                class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2 rounded-3 animate slideIn">
                                                <li>
                                                    <a class="dropdown-item btn-icra-kesinti-detay py-2 d-flex align-items-center"
                                                        href="javascript:void(0);" data-id="<?= $i->id ?>"
                                                        data-icra-dairesi="<?= htmlspecialchars($i->icra_dairesi) ?>"
                                                        data-dosya-no="<?= htmlspecialchars($i->dosya_no) ?>"
                                                        data-toplam-borc="<?= $i->toplam_borc ?>">
                                                        <i data-feather="activity" class="me-2 text-info"
                                                            style="width: 14px; height: 14px;"></i> Kesinti Geçmişi
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item btn-icra-duzenle py-2 d-flex align-items-center"
                                                        href="javascript:void(0);" data-id="<?= $i->id ?>">
                                                        <i data-feather="edit-3" class="me-2 text-primary"
                                                            style="width: 14px; height: 14px;"></i> Bilgileri Güncelle
                                                    </a>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider opacity-50">
                                                </li>
                                                <li>
                                                    <a class="dropdown-item btn-personel-icra-sil py-2 d-flex align-items-center text-danger"
                                                        href="javascript:void(0);" data-id="<?= $i->id ?>">
                                                        <i data-feather="trash-2" class="me-2"
                                                            style="width: 14px; height: 14px;"></i> Dosyayı Sil
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($icralar) && count($icralar) > 1): ?>
                <div class="card-footer bg-light bg-opacity-50 border-top py-3">
                    <div class="row g-3 align-items-center text-center text-md-start">
                        <div class="col-md-auto ms-auto border-end pe-4 d-none d-md-block">
                            <small class="text-muted d-block text-uppercase fw-bold"
                                style="font-size: 9px; letter-spacing: 1px;">Genel Toplam Borç</small>
                            <span class="fw-bold fs-6 text-dark"><?= number_format($toplamBorc, 2, ',', '.') ?> TL</span>
                        </div>
                        <div class="col-md-auto border-end pe-4 px-md-4">
                            <small class="text-muted d-block text-uppercase fw-bold"
                                style="font-size: 9px; letter-spacing: 1px;">Toplam Kesilen</small>
                            <span class="fw-bold fs-6 text-primary"><?= number_format($toplamKesilen, 2, ',', '.') ?>
                                TL</span>
                        </div>
                        <div class="col-md-auto ps-md-4">
                            <small class="text-muted d-block text-uppercase fw-bold"
                                style="font-size: 9px; letter-spacing: 1px;">Kalan Borç Toplamı</small>
                            <span
                                class="fw-bold fs-6 <?= $toplamKalan > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($toplamKalan, 2, ',', '.') ?>
                                TL</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- İcra Dosyası Ekle/Düzenle Modal -->
<div class="modal fade" id="modalPersonelIcraEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0">
                <h5 class="modal-title" id="icraModalTitle"><i data-feather="plus-circle" class="me-2"
                        style="width: 20px; height: 20px;"></i>Yeni İcra Dosyası Ekle</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersonelIcraEkle">
                <input type="hidden" name="personel_id" value="<?= $id ?>">
                <input type="hidden" name="id" id="icra_id_hidden" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Temel Dosya Bilgileri -->
                        <div class="col-12">
                            <h6 class="fw-bold mb-3 text-primary border-bottom pb-2"><i data-feather="file-text" class="icon-sm me-1"></i> Dosya Bilgileri</h6>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <?= Form::FormFloatInput("number", "icra_sira", "1", "Sıra", "Sıra No", "list", "form-control shadow-none", true, null, "off", false) ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?= Form::FormSelect2("icra_durum", [
                                        "bekliyor" => "Bekliyor",
                                        "devam_ediyor" => "Devam Ediyor",
                                        "fekki_geldi" => "Fekki Geldi",
                                        "kesinti_bitti" => "Kesinti Bitti",
                                        "bitti" => "Tamamlandı",
                                        "durduruldu" => "Durduruldu"
                                    ], "bekliyor", "Dosya Durumu", "info", "key", "", "form-select select2 shadow-none", true, 'width:100%') ?>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <?= Form::FormFloatInput("number", "icra_toplam_borc", "", "Toplam Borç", "Borç (TL)", "dollar-sign", "form-control shadow-none", true, null, "off", false, 'step="0.01"') ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?= Form::FormSelect2("icra_dairesi", $icraDaireleriOptions, "", "İcra Dairesi", "home", "key", "", "form-select select2 shadow-none", true, 'width:100%', 'data-tags="true"') ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?= Form::FormFloatInput("text", "icra_dosya_no", "", "Dosya No", "Esas No", "file-text", "form-control shadow-none", true, null, "off", false) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Banka Bilgileri -->
                        <div class="col-md-12">
                            <h6 class="fw-bold mb-3 text-info border-bottom pb-2"><i data-feather="credit-card" class="icon-sm me-1"></i> Banka Bilgileri</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <?= Form::FormFloatInput("text", "icra_iban", "", "İcra Dairesi IBAN", "TR00...", "credit-card", "form-control shadow-none", false, null, "off", false) ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?= Form::FormFloatTextarea("icra_hesap_bilgileri", "", "Hesap Bilgileri", "Banka ve Şube", "info", "form-control shadow-none", false, "38px", 1) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Kesinti Ayarları -->
                        <div class="col-12">
                            <h6 class="fw-bold mb-3 text-success border-bottom pb-2"><i data-feather="settings" class="icon-sm me-1"></i> Kesinti Detayları</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <?= Form::FormSelect2("icra_kesinti_tipi", [
                                        "tutar" => "Sabit Tutar",
                                        "net_yuzde" => "Net Ücretin Yüzdesi",
                                        "asgari_yuzde" => "Net Asgari Ücretin Yüzdesi"
                                    ], "tutar", "Kesinti Türü", "list", "key", "", "form-select select2 shadow-none", true, 'width:100%') ?>
                                </div>
                                <div class="col-md-4 mb-3" id="div_icra_aylik_kesinti">
                                    <?= Form::FormFloatInput("number", "icra_aylik_kesinti", "", "Aylık Kesinti", "Tutar (TL)", "minus-circle", "form-control shadow-none", true, null, "off", false, 'step="0.01"') ?>
                                </div>
                                <div class="col-md-4 mb-3" id="div_icra_kesinti_orani" style="display:none;">
                                    <?= Form::FormFloatInput("number", "icra_kesinti_orani", "25", "Kesinti Oranı (%)", "Oran (%)", "percent", "form-control shadow-none", false, null, "off", false, 'step="0.01" min="0" max="100"') ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?= Form::FormFloatInput("text", "icra_baslangic", "", "Başlangıç", "Tarih", "calendar", "form-control flatpickr", false, null, "off", false) ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?= Form::FormFloatInput("text", "icra_bitis", "", "Bitiş (Varsa)", "Tarih", "calendar", "form-control flatpickr", false, null, "off", false) ?>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <?= Form::FormFloatTextarea("icra_aciklama", "", "Açıklama", "Dosya Notu", "edit-3", "form-control shadow-none", false, "38px", 1) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning px-4 fw-bold rounded-pill" id="btnPersonelIcraKaydet">
                        <i data-feather="save" class="me-2" style="width: 16px; height: 16px;"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İcra Kesinti Detay Modal -->
<div class="modal fade" id="modalIcraKesintileri" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white border-bottom-0">
                <h5 class="modal-title"><i data-feather="activity" class="me-2"
                        style="width: 20px; height: 20px;"></i>İcra Kesinti Detayları</h5>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block mb-1 fw-bold text-uppercase" style="font-size: 10px;">İcra
                                Dairesi</small>
                            <span id="icraDetayDairesi" class="fw-bold text-dark">-</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block mb-1 fw-bold text-uppercase" style="font-size: 10px;">Dosya
                                No</small>
                            <span id="icraDetayDosyaNo" class="fw-bold text-dark">-</span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 mt-2"
                            id="btnIcraKesintileriExcel">
                            <i data-feather="download" class="me-1" style="width: 14px; height: 14px;"></i> Excel'e
                            Aktar
                        </button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-white border-start border-4 border-info shadow-sm rounded-2">
                            <small class="text-muted d-block">Toplam Borç</small>
                            <h5 id="icraDetayToplamBorc" class="mb-0 fw-bold">0,00 TL</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border-start border-4 border-primary shadow-sm rounded-2">
                            <small class="text-muted d-block">Toplam Kesilen</small>
                            <h5 id="icraDetayToplamKesilen" class="mb-0 fw-bold text-primary">0,00 TL</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border-start border-4 border-danger shadow-sm rounded-2">
                            <small class="text-muted d-block">Kalan Tutar</small>
                            <h5 id="icraDetayKalanTutar" class="mb-0 fw-bold text-danger">0,00 TL</h5>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="tblIcraKesintileri">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Dönem</th>
                                <th>Detay</th>
                                <th>Açıklama</th>
                                <th class="text-end">Tutar</th>
                                <th class="text-center">Ödeme</th>
                                <th class="text-center">Dekont</th>
                                <th class="text-center">Durum</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody id="icraKesintileriBody">
                            <!-- JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yazdırma Modalı -->
<div class="modal fade" id="modalIcraListeYazdir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between no-print">
                <h5 class="modal-title">İcra Dosyaları Listesi - <?= htmlspecialchars($personel->adi_soyadi ?? '') ?>
                </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" onclick="window.print();">
                        <i class="bx bx-printer me-1"></i> Yazdır
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-5">
                <div class="print-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="assets/images/logo.png" alt="Logo" height="50" class="me-3">
                        <div>
                            <h4 class="mb-0 fw-bold">ERSAN ELEKTRİK</h4>
                            <p class="text-muted mb-0 small">Personel İcra Dosyaları Dökümü</p>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold"><?= date('d.m.Y H:i') ?></div>
                        <div class="small text-muted">Rapor Tarihi</div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th class="bg-light" style="width: 150px;">Adı Soyadı</th>
                                <td><?= htmlspecialchars($personel->adi_soyadi ?? '') ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">TC Kimlik No</th>
                                <td><?= htmlspecialchars($personel->tc_kimlik_no ?? '---') ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Ekip No / Adı</th>
                                <td><?= htmlspecialchars($personel->ekip_adi ?? '---') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th class="bg-light" style="width: 150px;">Departman</th>
                                <td><?= htmlspecialchars($personel->departman ?? '---') ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Görev</th>
                                <td><?= htmlspecialchars($personel->gorev ?? '---') ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">İcra Sayısı</th>
                                <td><?= count($icralar) ?> Adet (<?= $aktifIcra ?> Aktif)</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-bordered align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">Sıra</th>
                                <th style="width: 25%;">İcra Dairesi / Kurum</th>
                                <th style="width: 15%;">Dosya Numarası</th>
                                <th class="text-end" style="width: 11%;">Toplam Borç</th>
                                <th class="text-end" style="width: 11%;">Aylık Kesinti</th>
                                <th class="text-end" style="width: 11%;">Top. Kesilen</th>
                                <th class="text-end" style="width: 11%;">Kalan Borç</th>
                                <th class="text-center" style="width: 9%;">Başlangıç</th>
                                <th class="text-center" style="width: 9%;">Bitiş</th>
                                <th class="text-center" style="width: 9%;">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($icralar)): ?>
                                <?php foreach ($icralar as $idx => $i): ?>
                                    <tr>
                                        <td class="text-center"><?= $i->sira ?></td>
                                        <td>
                                            <?= htmlspecialchars($i->icra_dairesi) ?>
                                            <?php if(!empty($i->iban)): ?>
                                                <br><small class="text-muted"><b>IBAN:</b> <?= htmlspecialchars($i->iban) ?></small>
                                            <?php endif; ?>
                                            <?php if(!empty($i->hesap_bilgileri)): ?>
                                                <br><small class="text-muted"><b>Hesap:</b> <?= htmlspecialchars($i->hesap_bilgileri) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($i->dosya_no) ?></td>
                                        <td class="text-end fw-bold"><?= number_format($i->toplam_borc, 2, ',', '.') ?> TL</td>
                                        <td class="text-end"><?php if (($i->kesinti_tipi ?? 'tutar') === 'tutar'): ?>
                                                <?= number_format($i->aylik_kesinti_tutari, 2, ',', '.') ?> TL
                                            <?php elseif ($i->kesinti_tipi === 'net_yuzde'): ?>
                                                %<?= number_format($i->kesinti_orani, 2, ',', '.') ?> (Net)
                                            <?php elseif ($i->kesinti_tipi === 'asgari_yuzde'): ?>
                                                %<?= number_format($i->kesinti_orani, 2, ',', '.') ?> (Asgari)
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-success fw-bold">
                                            <?= number_format($i->toplam_kesilen, 2, ',', '.') ?> TL
                                        </td>
                                        <td class="text-end text-danger fw-bold">
                                            <?= number_format($i->kalan_tutar, 2, ',', '.') ?> TL
                                        </td>
                                        <td class="text-center small">
                                            <?= ($i->baslangic_tarihi && $i->baslangic_tarihi != '0000-00-00') ? date('d.m.Y', strtotime($i->baslangic_tarihi)) : '-' ?>
                                        </td>
                                        <td class="text-center small">
                                            <?= ($i->bitis_tarihi && $i->bitis_tarihi != '0000-00-00') ? date('d.m.Y', strtotime($i->bitis_tarihi)) : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $durumMap = [
                                                'bekliyor' => 'Bekliyor',
                                                'devam_ediyor' => 'Devam Ediyor',
                                                'fekki_geldi' => 'Fekki Geldi',
                                                'kesinti_bitti' => 'Kesinti Bitti',
                                                'bitti' => 'Tamamlandı',
                                                'durduruldu' => 'Durduruldu'
                                            ];
                                            echo $durumMap[$i->durum] ?? $i->durum;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">Kayıtlı icra dosyası bulunamadı.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">GENEL TOPLAM</td>
                                <td class="text-end"><?= number_format($toplamBorc, 2, ',', '.') ?> TL</td>
                                <td colspan="1"></td>
                                <td class="text-end text-success"><?= number_format($toplamKesilen, 2, ',', '.') ?> TL
                                </td>
                                <td class="text-end text-danger"><?= number_format($toplamKalan, 2, ',', '.') ?> TL</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mt-5 pt-4">
                    <div class="col-4 text-center">
                        <div class="mb-5 pb-5 fw-bold">Hazırlayan</div>
                        <div class="border-top pt-2"><?= $_SESSION['adi_soyadi'] ?? 'Sistem Yöneticisi' ?></div>
                    </div>
                    <div class="col-4"></div>
                    <div class="col-4 text-center">
                        <div class="mb-5 pb-5 fw-bold">Onay</div>
                        <div class="border-top pt-2">İmza / Kaşe</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3 no-print">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary px-4 rounded-pill fw-bold" onclick="window.print();">
                    <i class="bx bx-printer me-1"></i> Yazdır
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var icraDaireleriData = <?= json_encode($icraDaireleriData ?? []) ?>;
    $(document).ready(function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
            setTimeout(function () { feather.replace(); }, 100);
            setTimeout(function () { feather.replace(); }, 500);
        }

        // İcra Dairesi Seçildiğinde IBAN ve Hesap Bilgilerini Doldur
        $(document).on('change', 'select[name="icra_dairesi"]', function(e, isProgrammatic) {
            if (isProgrammatic) return;

            const daireAdi = $(this).val();
            const data = icraDaireleriData[daireAdi];
            
            if (data) {
                $('input[name="icra_iban"]').val(data.iban).trigger('input');
                
                let hesapBilgisi = '';
                if (data.vergi_dairesi) hesapBilgisi += data.vergi_dairesi + ' V.D. ';
                if (data.vergi_no) hesapBilgisi += 'No: ' + data.vergi_no + '\n';
                if (data.il) hesapBilgisi += data.il + (data.ilce ? ' / ' + data.ilce : '');
                
                $('textarea[name="icra_hesap_bilgileri"]').val(hesapBilgisi.trim());
            }
        });
    });
</script>