<?php
use App\Helper\Form;
use App\Helper\Helper;
?>

<style>
    /* Select2 Multiple Floating Label Fix */
    .form-floating-custom .select2-container--default .select2-selection--multiple {
        min-height: 58px !important;
        padding-top: 20px !important;
        padding-bottom: 4px !important;
    }

    .form-floating-custom .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        padding-left: 45px !important;
    }

    .form-floating-custom .select2-container--default .select2-selection--multiple .select2-selection__choice {
        margin-top: 2px !important;
        background-color: rgba(28, 132, 238, 0.1) !important;
        border: 1px solid rgba(28, 132, 238, 0.2) !important;
        color: #1c84ee !important;
        font-weight: 500;
    }

    .form-floating-custom .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #ef4444 !important;
        margin-right: 5px !important;
    }

    /* Fixed icon alignment for multiple select */
    .form-floating-custom .form-floating-icon {
        z-index: 10;
        pointer-events: none;
    }

    .readonly {
        opacity: 0.6;
        background-color: #f8f9fa;

    }

    /* Toggle Switch Styling */
    .form-switch-lg .form-check-input {
        width: 3rem;
        height: 1.5rem;
        cursor: pointer;
    }
    .form-switch-lg .form-check-label {
        padding-left: 0.5rem;
        padding-top: 0.2rem;
        cursor: pointer;
        font-size: 1rem;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-money me-2"></i>Maaş & Banka Bilgileri</h5>
            </div>
            <div class="card-body">
                <!-- 1. Grup: Banka Bilgileri -->
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3"><i class="bx bx-building-house me-1"></i>Banka Bilgileri</h6>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <?php echo Form::FormFloatInput("text", "iban_numarasi", $personel->iban_numarasi ?? "", "Maaş IBAN", "Maaş IBAN Numarası", "credit-card"); ?>
                        </div>
                        <div class="col-md-3 mb-2">
                            <?php echo Form::FormFloatInput("text", "ek_odeme_iban_numarasi", $personel->ek_odeme_iban_numarasi ?? "", "Ek Ödeme IBAN", "Ek Ödeme IBAN", "credit-card"); ?>
                        </div>
                    </div>
                </div>

                <!-- YAN HAKLAR VE BANKA BİLGİLERİ -->

                <!-- 3. Grup: Yan Haklar & Kesintiler -->
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3"><i class="bx bx-star me-1"></i>Yan Haklar & Kesintiler</h6>
                    <div class="row">
                        <div class="col-md-2 mb-2">
                            <?php echo Form::FormSelect2("bes_kesintisi_varmi", ['1' => 'Evet', '0' => 'Hayır'], $personel->bes_kesintisi_varmi ?? '', "Bes Kesintisi Var mı?", "dollar-sign"); ?>
                        </div>
                        <div class="col-md-3 mb-2">
                            <?php echo Form::FormFloatInput("text", "sodexo", Helper::formattedMoney($personel->sodexo ?? 0), "Sodexo Ödemesi Tutarı", "Sodexo", "gift", "form-control money"); ?>
                        </div>
                        <div class="col-md-3 mb-2">
                            <?php echo Form::FormFloatInput("text", "sodexo_kart_no", $personel->sodexo_kart_no ?? "", "Sodexo Kart No", "Sodexo Kart Numarası", "credit-card"); ?>
                        </div>
                    </div>
                </div>

                <!-- 4. Grup: Ek Sosyal Yardımlar -->
                <div class="mt-4 pt-2 border-top">
                    <h6 class="fw-bold text-muted mb-3"><i class="bx bx-plus-circle me-1"></i>Ek Sosyal Yardımlar</h6>
                    <div class="row">
                        <!-- Yemek Yardımı -->
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch mb-3">
                                        <input type="hidden" name="yemek_yardimi_aliyor" value="0">
                                        <input class="form-check-input" type="checkbox" id="yemek_yardimi_aliyor" name="yemek_yardimi_aliyor" value="1" <?= ($personel->yemek_yardimi_aliyor ?? 0) == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="yemek_yardimi_aliyor">
                                            <i class="bx bx-dish me-1 text-primary"></i> Yemek Yardımı Alıyor mu?
                                        </label>
                                    </div>
                                    <div id="sectionYemekParametre" style="display: <?= ($personel->yemek_yardimi_aliyor ?? 0) == 1 ? 'block' : 'none' ?>;">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <?php 
                                                $BordroParametreModel = new \App\Model\BordroParametreModel();
                                                $yemekParams = $BordroParametreModel->where('aktif', 1);
                                                $yemekOptions = [];
                                                foreach ($yemekParams as $yp) {
                                                    if (stripos($yp->kod, 'yemek') !== false) {
                                                        $yemekOptions[$yp->id] = $yp->etiket . " (" . $yp->kod . ")";
                                                    }
                                                }
                                                echo Form::FormSelect2("yemek_yardimi_parametre_id", $yemekOptions, $personel->yemek_yardimi_parametre_id ?? '', "Yemek Yardımı Parametresi", "file-text");
                                                ?>
                                            </div>
                                            <div class="col-md-5">
                                                <?php echo Form::FormFloatInput("text", "yemek_yardimi_tutari", Helper::formattedMoney($personel->yemek_yardimi_tutari ?? 0), "Manuel Yemek Tutarı", "Manuel Tutar", "dollar-sign", "form-control money"); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Eş Yardımı -->
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch mb-3">
                                        <input type="hidden" name="es_yardimi_aliyor" value="0">
                                        <input class="form-check-input" type="checkbox" id="es_yardimi_aliyor" name="es_yardimi_aliyor" value="1" <?= ($personel->es_yardimi_aliyor ?? 0) == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="es_yardimi_aliyor">
                                            <i class="bx bx-heart me-1 text-danger"></i> Eş Yardımı Alıyor mu?
                                        </label>
                                    </div>
                                    <div id="sectionEsParametre" style="display: <?= ($personel->es_yardimi_aliyor ?? 0) == 1 ? 'block' : 'none' ?>;">
                                        <?php 
                                        $esParams = $BordroParametreModel->where('aktif', 1);
                                        $esOptions = [];
                                        foreach ($esParams as $ep) {
                                            if (stripos($ep->kod, 'es_yardimi') !== false || stripos($ep->kod, 'aile') !== false) {
                                                $esOptions[$ep->id] = $ep->etiket . " (" . $ep->kod . ")";
                                            }
                                        }
                                        echo Form::FormSelect2("es_yardimi_parametre_id", $esOptions, $personel->es_yardimi_parametre_id ?? '', "Eş Yardımı Parametresi", "file-text");
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Görev/Maaş Geçmişi Tablosu -->
    <div class="col-md-12 mt-3">
        <div class="card border h-100">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-briefcase me-2"></i>Maaş Tipi Geçmişi</h5>
                <div id="gorevGecmisiButtonContainer">
                    <?php if ($id > 0): ?>
                        <?php 
                        $aktifGorevCheck = $PersonelModel->getAktifGorevGecmisi($id);
                        if (!$aktifGorevCheck): 
                        ?>
                            <button type="button" class="btn btn-sm btn-primary" id="btnOpenGorevGecmisiModal">
                                <i class="bx bx-plus"></i> Yeni Maaş Tipi Tanımla
                            </button>
                        <?php else: ?>
                            <span class="badge bg-soft-warning text-warning p-2">
                                <i class="bx bx-info-circle me-1"></i> Aktif görev kaydı varken yenisi eklenemez.
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <div class="table-responsive">
                        <table id="tblGorevGecmisi" class="table table-hover mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="display:none">ID</th>
                                    <th>Departman & Görev</th>
                                    <th>Maaş Tipi</th>
                                    <th>Tutar</th>
                                    <th>Başlangıç Tarihi</th>
                                    <th>Bitiş Tarihi</th>
                                    <th>Durum</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $gorevGecmisi = $PersonelModel->getGorevGecmisi($id);
                                if (!empty($gorevGecmisi)):
                                    foreach ($gorevGecmisi as $g):
                                        ?>
                                        <tr>
                                            <td style="display:none"><?= $g->id ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span
                                                        class="fw-bold text-dark"><?= htmlspecialchars($g->gorev ?? 'Belirtilmemiş') ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($g->departman ?? '') ?></small>
                                                </div>
                                            </td>
                                            <td><span
                                                    class="fw-bold text-dark"><?= htmlspecialchars($g->maas_durumu ?? '') ?></span>
                                            </td>
                                            <td><?= Helper::formattedMoney($g->maas_tutari ?? 0) ?></td>
                                            <td><?= date('d.m.Y', strtotime($g->baslangic_tarihi)) ?></td>
                                            <td><?= $g->bitis_tarihi ? date('d.m.Y', strtotime($g->bitis_tarihi)) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $bugun = date('Y-m-d');
                                                if ($g->baslangic_tarihi <= $bugun && ($g->bitis_tarihi === null || $g->bitis_tarihi >= $bugun)) {
                                                    if ($g->bitis_tarihi === null) {
                                                        echo '<span class="badge bg-success">Aktif</span>';
                                                    } elseif ($g->bitis_tarihi == $bugun) {
                                                        echo '<span class="badge bg-warning">Bitti</span>';
                                                    } else {
                                                        echo '<span class="badge bg-info">Süreli Aktif</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">Pasif</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <button type="button"
                                                    class="btn btn-sm btn-soft-primary btn-gorev-gecmisi-duzenle me-1"
                                                    data-id="<?= $g->id ?>" title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-gorev-gecmisi-sil"
                                                    data-id="<?= $g->id ?>" title="Sil">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bx bx-info-circle fs-2 mb-2 d-block"></i>
                        Yeni personel eklerken önce personeli kaydedin, ardından maaş tipi tanımlaması yapabilirsiniz.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
$(document).ready(function() {
    // Yemek Yardımı Toggle
    $('input[name="yemek_yardimi_aliyor"]').on('change', function() {
      if ($(this).is(':checked')) {
        $('#sectionYemekParametre').slideDown();
      } else {
        $('#sectionYemekParametre').slideUp();
        $('select[name="yemek_yardimi_parametre_id"]').val('').trigger('change');
        $('input[name="yemek_yardimi_tutari"]').val('0,00');
      }
    });

    // Eş Yardımı Toggle
    $('input[name="es_yardimi_aliyor"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#sectionEsParametre').slideDown();
        } else {
            $('#sectionEsParametre').slideUp();
            $('select[name="es_yardimi_parametre_id"]').val('').trigger('change');
        }
    });
});
</script>
