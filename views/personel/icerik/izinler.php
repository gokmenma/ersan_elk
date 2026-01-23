<?php

use App\Helper\Form;
use App\Helper\Helper;


$db = (new \App\Core\Db())->getConnection();
$izin_turleri_query = $db->query("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL ORDER BY tur_adi ASC");
$izin_turleri = [];
while ($row = $izin_turleri_query->fetch(PDO::FETCH_OBJ)) {
    $izin_turleri[$row->id] = $row->tur_adi;
}

$izin_durumlari = [
    'Gerceklesti' => 'Gerçekleşti',
    'Planlandi' => 'Planlandı'
];
$yillik_izne_etki = [
    'IslemYapma' => 'İşlem Yapma',
    'Dus' => 'Düşülsün'
];
$bordroya_aktar = [
    'Evet' => 'Evet',
    'Hayir' => 'Hayır'
];
$onay_durumlari = [
    'Beklemede' => 'Beklemede',
    'KabulEdildi' => 'Kabul Edildi',
    'Reddedildi' => 'Reddedildi'
];
//Helper::dd($izin_turleri);
?>


<div class="row mb-3">
    <div class="col-12">
        <div class="card border border-primary bg-primary bg-opacity-10">
            <div class="card-body p-3">
                <div class="row text-center align-items-center">
                    <div class="col-md-4 border-end border-primary border-opacity-25 cursor-pointer"
                        data-bs-toggle="modal" data-bs-target="#modalHakedisDetay">
                        <strong class="d-block small text-primary text-uppercase mb-1">Toplam Hakediş</strong>
                        <span class="fs-4 fw-bold text-primary"><?= isset($toplam_hakedis) ? $toplam_hakedis : 0 ?>
                            Gün</span>
                        <div class="small text-muted mt-1"><i class="bx bx-info-circle"></i> Detay için tıklayın</div>
                    </div>
                    <div class="col-md-4 border-end border-primary border-opacity-25">
                        <strong class="d-block small text-danger text-uppercase mb-1">Kullanılan</strong>
                        <span class="fs-4 fw-bold text-danger"><?= isset($kullanilan_izin) ? $kullanilan_izin : 0 ?>
                            Gün</span>
                    </div>
                    <div class="col-md-4">
                        <strong class="d-block small text-success text-uppercase mb-1">Kalan</strong>
                        <span class="fs-4 fw-bold text-success"><?= isset($kalan_izin) ? $kalan_izin : 0 ?> Gün</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hakediş Detay Modal -->
<div class="modal fade" id="modalHakedisDetay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yıllık İzin Hakediş Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hizmet Yılı</th>
                                <th>Hakediş Tarihi</th>
                                <th class="text-end">Hakediş (Gün)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($entitlement['detay']) && !empty($entitlement['detay'])): ?>
                                <?php foreach ($entitlement['detay'] as $detay): ?>
                                    <tr>
                                        <td><?= $detay['yil'] ?>. Yıl</td>
                                        <td><?= date('d.m.Y', strtotime($detay['hakedis_tarihi'])) ?></td>
                                        <td class="text-end fw-bold"><?= $detay['hakedis_gun'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-active">
                                    <td colspan="2" class="fw-bold text-end">Toplam:</td>
                                    <td class="text-end fw-bold text-primary"><?= $toplam_hakedis ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Henüz hakediş bulunmuyor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-calendar-event me-2"></i>İzin Bilgileri</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                    data-bs-target="#modalIzinEkle"><i class="bx bx-plus"></i> Yeni İzin Ekle</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table
                        class="table table-selected datatable table-responsive dt-responsive table-bordered nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>İzin Türü</th>
                                <th>Başlangıç</th>
                                <th>Bitiş</th>
                                <th>Süre (Gün)</th>
                                <th>Durum</th>
                                <th>Onay Bilgisi</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($izinler) && count($izinler) > 0): ?>
                                <?php foreach ($izinler as $izin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($izin->izin_tipi_adi ?? $izin->izin_tipi ?? '') ?></td>
                                        <td><?= date('d.m.Y', strtotime($izin->baslangic_tarihi ?? 'now')) ?></td>
                                        <td><?= date('d.m.Y', strtotime($izin->bitis_tarihi ?? 'now')) ?></td>
                                        <td><?= htmlspecialchars($izin->sure ?? '-') ?></td>
                                        <td>
                                            <?php
                                            // Son durumu belirle
                                            $durum = $izin->son_durum ?? 'Bekliyor';
                                            if ($durum == 'Onaylandı'): ?>
                                                <span class="badge bg-success">Onaylandı</span>
                                            <?php elseif ($durum == 'Reddedildi'): ?>
                                                <span class="badge bg-danger">Reddedildi</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><?= htmlspecialchars($durum) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($izin->onaylar)): ?>
                                                <?php foreach ($izin->onaylar as $onay): ?>
                                                    <div class="mb-1 border-bottom pb-1">
                                                        <div><strong><?= htmlspecialchars($onay->adi ?? 'Bilinmiyor') ?></strong></div>
                                                        <div class="small text-muted">
                                                            <?= !empty($onay->tarih) ? date('d.m.Y H:i', strtotime($onay->tarih)) : '-' ?>
                                                            - <?= htmlspecialchars($onay->durum) ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($izin->son_durum ?? 'Beklemede') == 'Beklemede'): ?>
                                                <a href="javascript:void()" class="btn btn-sm btn-outline-danger btn-izin-sil"
                                                    data-id="<?= $izin->id ?>"
                                                    data-durum="<?= htmlspecialchars($izin->son_durum ?? 'Beklemede') ?>"><i
                                                        class="bx bx-trash"></i></a>
                                            <?php else: ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Herhangi bir izin kaydı bulunamadı.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni İzin Ekle Modal -->
<div class="modal fade" id="modalIzinEkle" tabindex="-1" aria-labelledby="modalIzinEkleLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalIzinEkleLabel">Yeni İzin Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($toplam_hakedis)): ?>
                    <?php
                    $formatGun = function ($n) {
                        $n = (float) $n;
                        if (abs($n - round($n)) < 0.00001) {
                            return (string) (int) round($n);
                        }
                        return number_format($n, 1, ',', '.');
                    };
                    ?>
                    <div class="alert alert-info p-2 mb-3">
                        <div class="row text-center">
                            <div class="col-4 border-end">
                                <strong class="d-block small text-muted text-uppercase">Toplam Hakediş</strong>
                                <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($formatGun($toplam_hakedis)) ?>
                                    Gün</span>
                            </div>
                            <div class="col-4 border-end">
                                <strong class="d-block small text-muted text-uppercase">Kullanılan</strong>
                                <span class="fs-5 fw-bold text-danger"><?= htmlspecialchars($formatGun($kullanilan_izin)) ?>
                                    Gün</span>
                            </div>
                            <div class="col-4">
                                <strong class="d-block small text-muted text-uppercase">Kalan</strong>
                                <span class="fs-5 fw-bold text-success"><?= htmlspecialchars($formatGun($kalan_izin)) ?>
                                    Gün</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <form id="formIzinEkle">
                    <input type="hidden" name="personel_id" id="personel_id" value="<?= $id ?? 0 ?>">

                    <h6 class="mb-3 text-primary border-bottom pb-2">İzin Bilgileri</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?= Form::FormSelect2("izin_tipi", $izin_turleri, 'Yıllık İzin', "İzin Türü", "archive") ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "personel_adi", isset($personel) ? $personel->adi_soyadi : '', "Personel", "Personel", "user", "form-control", false, null, "off", true) ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "baslangic_tarihi", "", "", "İzin Başlangıç", "calendar", "form-control flatpickr") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "bitis_tarihi", "", "", "İzin Bitiş", "calendar", "form-control flatpickr") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("number", "sure", "", "0,00", "İzinli Gün", "clock", "form-control", false, null, "off", false, 'step="0.5"') ?>
                        </div>
                    </div>


                    <div class="row mb-3">
                        <div class="col-12">
                            <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "file-text") ?>
                        </div>
                    </div>



                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= Form::FormSelect2("izin_durumu", $izin_durumlari, 'Gerceklesti', "İzin Durumu", "check-circle") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormSelect2("yillik_izne_etki", $yillik_izne_etki, 'IslemYapma', "Yıllık İzne Etkisi", "sliders") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormSelect2("bordroya_aktar", $bordroya_aktar, 'Evet', "Bordroya Aktar", "file-plus") ?>
                        </div>
                    </div>

                    <h6 class="mb-3 text-primary border-bottom pb-2">Onaylayan</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group" style="height: 58px;">
                                <span class="input-group-text"><i class="bx bx-search"></i></span>
                                <div class="form-floating form-floating-custom flex-grow-1">
                                    <input type="text" class="form-control" id="onaylayan_ara"
                                        placeholder="Onaylayan Personel Ara"
                                        style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                    <label for="onaylayan_ara">Onaylayan Personel Ara</label>
                                </div>
                                <input type="hidden" id="onaylayan_id" name="onaylayan_id">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormSelect2("onay_durumu", $onay_durumlari, 'Beklemede', "Onay Durumu", "info") ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <?= Form::FormFloatInput("text", "onay_aciklama", "", "Onay açıklaması", "Onay Açıklama", "message-square") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "onay_tarihi", "", "", "Onay Tarihi", "calendar", "form-control flatpickr-date") ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bx bx-x"></i>
                    Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnIzinKaydet"><i class="bx bx-save"></i>
                    Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab içeriği yüklendiğinde flatpickr'ı başlat
    if (typeof flatpickr !== 'undefined') {
        $(".flatpickr-date").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            locale: "tr"
        });
    }
</script>