<?php
use App\Helper\Form;
use App\Helper\Helper;
?>

<div class="row">
    <div class="col-md-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-money me-2"></i>Maaş & Banka Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <?php echo Form::FormFloatInput("text", "iban_numarasi", $personel->iban_numarasi ?? "", "Maaş IBAN", "Maaş IBAN Numarası", "credit-card"); ?>
                    </div>
                    <div class="col-md-3 mb-2">
                        <?php echo Form::FormFloatInput("text", "ek_odeme_iban_numarasi", $personel->ek_odeme_iban_numarasi ?? "", "Ek Ödeme IBAN", "Ek Ödeme IBAN", "credit-card"); ?>
                    </div>

                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormSelect2("maas_durumu", Helper::MAAS_HESAPLAMA_TIPI, $personel->maas_durumu ?? 'Brüt', "Maaş Tipi", "dollar-sign"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormFloatInput("text", "maas_tutari", Helper::formattedMoney($personel->maas_tutari ?? 0), "Maaş Tutarı", "Maaş Tutarı", "dollar-sign", "form-control money"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormFloatInput("text", "gunluk_ucret", Helper::formattedMoney($personel->gunluk_ucret ?? 0), "Günlük Ücreti", "Günlük Ücreti", "calendar", "form-control money"); ?>
                    </div>

                </div>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <?php echo Form::FormSelect2("bes_kesintisi_varmi", ['1' => 'Evet', '0' => 'Hayır'], $personel->bes_kesintisi_varmi ?? '', "Bes Kesintisi Var mı?", "dollar-sign"); ?>
                    </div>
                    <div class="col-md-3 mb-2">
                        <?php echo Form::FormFloatInput("text", "sodexo", Helper::formattedMoney($personel->sodexo ?? 0), "Sodexo Ödemesi Tutarı", "Sodexo", "gift", "form-control money"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormFloatInput("text", "sodexo_kart_no", $personel->sodexo_kart_no ?? "", "Sodexo Kart No", "Sodexo Kart Numarası", "credit-card"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bilgilendirme Mesajı -->
    <div class="col-md-12 mt-3">
        <div class="alert alert-success border-success border-dashed d-flex align-items-center mb-0" role="alert">
            <i class="bx bx-info-circle fs-4 me-3"></i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">Maaş Tipi Geçmişi Sistemi</h6>
                <p class="mb-0 small">
                    Personelin maaş hesaplamaları <strong>Maaş Tipi Geçmişi</strong> kayıtlarına göre yapılmaktadır.
                    Eğer bir bordro dönemi içerisinde (örneğin aynı ay içinde) birden fazla tanım varsa,
                    sistem her bir tanımı geçerli olduğu gün sayısına göre <strong>oranlayarak (pro-rata)</strong>
                    otomatik hesaplar.
                    <br>
                    Eğer tanımlı maaş tipi geçmişi kayıtları bulunamazsa, sistem girdiğiniz <strong>Maaş Tipi</strong>
                    ile hesaplar.
                </p>
            </div>
        </div>
    </div>

    <!-- Görev/Maaş Geçmişi Tablosu -->
    <div class="col-md-12 mt-3">
        <div class="card border h-100">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-briefcase me-2"></i>Maaş Tipi Geçmişi</h5>
                <?php if ($id > 0): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="btnOpenGorevGecmisiModal">
                        <i class="bx bx-plus"></i> Yeni Maaş Tipi Tanımla
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <div class="table-responsive">
                        <table id="tblGorevGecmisi" class="table table-hover mb-0 w-100">
                            <thead class="table-light">
                                <tr>
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