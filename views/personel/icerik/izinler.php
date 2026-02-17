<?php

use App\Helper\Form;
use App\Helper\Helper;


$db = (new \App\Core\Db())->getConnection();
$izin_turleri_query = $db->query("SELECT id, tur_adi, ucretli_mi FROM tanimlamalar WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL ORDER BY tur_adi ASC");
$izin_turleri = [];
$izin_turleri_all = [];
while ($row = $izin_turleri_query->fetch(PDO::FETCH_OBJ)) {
    $izin_turleri_all[] = $row;
    if ($row->ucretli_mi == 1) {
        $izin_turleri[$row->id] = $row->tur_adi;
    }
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
    'Onaylandı' => 'Onaylandı',
    'Reddedildi' => 'Reddedildi'
];
//Helper::dd($izin_turleri);
?>


<div class="row mb-3">
    <div class="col-12">
        <div class="card border border-primary">
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
            <div
                class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-calendar-event me-2"></i>İzin Bilgileri</h5>
                <div class="d-flex align-items-center gap-2">
                    <!-- Yıl Seçicisi (Sadece Takvim Görünümünde Görünür) -->
                    <div id="takvimYilSecici" style="display: none;">
                        <div class="d-flex align-items-center gap-2 me-2">
                            <label class="mb-0 small fw-bold text-muted">Yıl:</label>
                            <select class="form-select form-select-sm" id="yillik_takvim_yil"
                                style="width: 80px; border-radius: 6px;">
                                <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Görünüm Toggle -->
                    <div class="btn-group btn-group-sm p-1 bg-light rounded" role="group"
                        style="border: 1px solid #eee;">
                        <button type="button" class="btn btn-sm btn-white active shadow-sm" id="btnListView"
                            title="Liste Görünümü">
                            <i class="bx bx-list-ul"></i> <span class="d-none d-sm-inline ms-1">Liste</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-white" id="btnCalendarView" title="Takvim Görünümü">
                            <i class="bx bx-calendar"></i> <span class="d-none d-sm-inline ms-1">Takvim</span>
                        </button>
                    </div>

                    <!-- Tam Ekran Butonu (Sadece Takvim Görünümünde) -->
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btnCalendarFullscreen"
                        title="Tam Ekran" style="display: none;">
                        <i class="bx bx-fullscreen"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal"
                        data-bs-target="#modalIzinEkle"><i class="bx bx-plus"></i> <span class="d-none d-sm-inline">Yeni
                            Ekle</span></button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Liste Görünümü -->
                <div id="izinListContainer" class="p-0">
                    <div class="table-responsive">
                        <table id="izinlerTable" class="table table-selected datatable table-bordered nowrap w-100">
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
                                            <td><?= htmlspecialchars($izin->toplam_gun ?? '-') ?></td>
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
                                                            <div><strong><?= htmlspecialchars($onay->adi ?? 'Bilinmiyor') ?></strong>
                                                            </div>
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
                                                <div class="btn-group" role="group">
                                                    <?php
                                                    $durum = $izin->son_durum ?? 'Beklemede';
                                                    $onayaTabi = ($izin->yetkili_onayina_tabi ?? 0) == 1;
                                                    $isKilitli = $onayaTabi && $durum == 'Onaylandı';

                                                    if (!$isKilitli): ?>
                                                        <?php if ($durum == 'Beklemede'): ?>
                                                            <button type="button" class="btn btn-sm btn-success btn-izin-onayla"
                                                                data-id="<?= $izin->id ?>"
                                                                data-personel="<?= htmlspecialchars($personel->adi_soyadi ?? '') ?>"
                                                                data-tur="<?= htmlspecialchars($izin->izin_tipi_adi ?? $izin->izin_tipi ?? '') ?>"
                                                                data-gun="<?= htmlspecialchars($izin->sure ?? '-') ?>" title="Onayla">
                                                                <i class="bx bx-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger btn-izin-reddet"
                                                                data-id="<?= $izin->id ?>"
                                                                data-personel="<?= htmlspecialchars($personel->adi_soyadi ?? '') ?>"
                                                                title="Reddet">
                                                                <i class="bx bx-x"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <button type="button" class="btn btn-sm btn-warning btn-izin-duzenle"
                                                            data-id="<?= $izin->id ?>"
                                                            data-json='<?= htmlspecialchars(json_encode($izin), ENT_QUOTES, 'UTF-8') ?>'
                                                            title="Düzenle">
                                                            <i class="bx bx-edit-alt"></i>
                                                        </button>

                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-izin-sil"
                                                            data-id="<?= $izin->id ?>" data-durum="<?= htmlspecialchars($durum) ?>"
                                                            data-onaya-tabi="<?= $onayaTabi ? 1 : 0 ?>" title="Sil">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-info btn-izin-detay"
                                                            data-id="<?= $izin->id ?>" title="Detay">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Takvim Görünümü -->
                <div id="izinCalendarContainer" style="display: none;" class="p-3 bg-light">
                    <!-- Tam Ekran Başlığı -->
                    <div id="calendarFullscreenHeader">
                        <h5 class="mb-0 text-primary">
                            <i class="bx bx-calendar-event me-2"></i>
                            <span id="fsPersonelAdi"><?= htmlspecialchars($personel->adi_soyadi ?? '') ?></span> -
                            <span id="fsYilGosterge"></span> Yıllık Takvim
                        </h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-danger" id="btnExitCalendarFullscreen">
                                <i class="bx bx-exit-fullscreen"></i> Tam Ekrandan Çık
                            </button>
                        </div>
                    </div>

                    <div id="yillikTakvimContainer" class="row g-3">
                        <!-- JS ile doldurulacak -->
                    </div>
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
                <div>
                    <h5 class="modal-title mb-0" id="modalIzinEkleLabel">Yeni Ekle</h5>
                    <small class="text-muted"><i
                            class="bx bx-user me-1"></i><?= isset($personel) ? htmlspecialchars($personel->adi_soyadi) : '' ?></small>
                </div>
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
                                <span class="fs-5 fw-bold text-success"
                                    id="kalan_izin_gun"><?= htmlspecialchars($formatGun($kalan_izin)) ?>
                                    Gün</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <form id="formIzinEkle">
                    <input type="hidden" name="id" id="izin_id" value="0">
                    <input type="hidden" name="personel_id" id="personel_id" value="<?= $id ?? 0 ?>">

                    <h6 class="mb-3 text-primary border-bottom pb-2">İzin Bilgileri</h6>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="izin_ucret_durumu" id="ucretli_izin"
                                    value="1" checked>
                                <label class="form-check-label" for="ucretli_izin">Ücretli İzin</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="izin_ucret_durumu" id="ucretsiz_izin"
                                    value="0">
                                <label class="form-check-label" for="ucretsiz_izin">Ücretsiz İzin</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?= Form::FormSelect2("izin_tipi", $izin_turleri, 'Yıllık İzin', "İzin Türü", "archive") ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "baslangic_tarihi", "", "", "Başlangıç Tarihi", "calendar", "form-control flatpickr") ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput("text", "bitis_tarihi", "", "", "Bitiş Tarihi", "calendar", "form-control flatpickr") ?>
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



                    <div class="row mb-3" id="izin_durum_row">
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

                    <!-- Burayı şimdilik kapatıyoruz, tekrar açma -->

                    <div id="onaylayan_section">
                        <h6 class="mb-3 text-primary border-bottom pb-2">Onaylayan</h6>

                        <div class="row mb-3">
                            <!-- <div class="col-md-6">
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
                            </div> -->
                            <input type="hidden" id="onaylayan_id" name="onaylayan_id">
                            <div class="col-md-6">
                                <?= Form::FormSelect2("onay_durumu", $onay_durumlari, 'Beklemede', "Onay Durumu", "info") ?>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3" id="onay_detaylari_row" style="display: none;">
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



<!-- İzin Onay Modal -->
<div class="modal fade" id="modalIzinOnayPersonel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-calendar-check me-2"></i>İzin Onayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formIzinOnayPersonel">
                <input type="hidden" name="id" id="izin_onay_id">
                <div class="modal-body">
                    <div class="alert alert-success">
                        <strong id="izin_onay_personel"></strong> personelinin
                        <strong id="izin_onay_gun"></strong> günlük <strong id="izin_onay_tur"></strong> talebini
                        onaylamak istediğinize emin misiniz?
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Açıklama (Opsiyonel)</label>
                        <textarea class="form-control" name="aciklama" rows="2"
                            placeholder="Onay açıklaması..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Onayla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İzin Red Modal -->
<div class="modal fade" id="modalIzinRedPersonel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bx bx-x-circle me-2"></i>İzin Reddi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formIzinRedPersonel">
                <input type="hidden" name="id" id="izin_red_id">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong id="izin_red_personel"></strong> personelinin izin talebini reddetmek istediğinize emin
                        misiniz?
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Red Açıklaması <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="aciklama" rows="3"
                            placeholder="Red sebebini açıklayınız..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger"><i class="bx bx-x me-1"></i>Reddet</button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
    .year-calendar-month {
        background: #fff;
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    .year-calendar-header {
        text-align: center;
        font-weight: bold;
        margin-bottom: 10px;
        color: #495057;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
    }

    .year-calendar-table {
        width: 100%;
        border-collapse: collapse;
    }

    .year-calendar-table th {
        font-size: 10px;
        text-align: center;
        padding: 2px;
        color: #adb5bd;
        text-transform: uppercase;
    }

    .year-calendar-table td {
        width: 14.28%;
        height: 30px;
        text-align: center;
        vertical-align: middle;
        font-size: 12px;
        border: 1px solid #f8f9fa;
        position: relative;
    }

    .year-calendar-day {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        cursor: default;
    }

    .year-calendar-day.other-month {
        color: #dee2e6;
    }

    .year-calendar-day.has-event {
        font-weight: bold;
        color: #fff;
        border-radius: 4px;
    }

    .year-calendar-day.today {
        border: 2px solid #556ee6;
        border-radius: 4px;
    }

    .year-calendar-event-dot {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        font-size: 10px;
    }

    /* Takvim Tam Ekran Stilleri */
    body.calendar-fullscreen {
        overflow: hidden !important;
    }

    body.calendar-fullscreen #izinCalendarContainer {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 9999;
        background: var(--bs-body-bg, #f3f3f9);
        padding: 20px;
        overflow-y: auto;
        display: block !important;
    }

    body.calendar-fullscreen .year-calendar-month {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Tam ekranda başlığı da gösterelim */
    #calendarFullscreenHeader {
        display: none;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #dee2e6;
    }

    body.calendar-fullscreen #calendarFullscreenHeader {
        display: flex !important;
        justify-content: space-between;
        align-items: center;
    }
</style>

<script>
    var allIzinTurleri = <?= json_encode($izin_turleri_all) ?>;
    var currentUserId = <?= json_encode($_SESSION['user_id'] ?? 0) ?>;

    $(document).ready(function () {
        // Onay durumu değişikliği
        $(document).off('change select2:select', '#onay_durumu').on('change select2:select', '#onay_durumu', function () {
            var durum = $(this).val();
            var form = $(this).closest('form');
            var row = form.find('#onay_detaylari_row');
            var aciklamaInput = form.find('[name="onay_aciklama"]');
            var tarihInput = form.find('[name="onay_tarihi"]');
            var onaylayanIdInput = form.find('#onaylayan_id');

            if (durum === 'Onaylandı') {
                row.attr('style', 'display: flex !important');
                aciklamaInput.val('Otomatik onaylandı');

                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var formattedDate = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes;

                tarihInput.val(formattedDate);
                if (tarihInput[0] && tarihInput[0]._flatpickr) {
                    tarihInput[0]._flatpickr.setDate(formattedDate);
                }

                if (typeof currentUserId !== 'undefined') {
                    onaylayanIdInput.val(currentUserId);
                }
            } else {
                row.attr('style', 'display: none !important');
                aciklamaInput.val('');
                tarihInput.val('');
                if (tarihInput[0] && tarihInput[0]._flatpickr) {
                    tarihInput[0]._flatpickr.clear();
                }
                onaylayanIdInput.val('');
            }
        });

        // İzin türü değişikliği - Rapor seçildiğinde otomatik onay ve alanları gizle
        $(document).off('change select2:select', '#izin_tipi').on('change select2:select', '#izin_tipi', function () {
            handleIzinTuruChange();
        });

        // Select2 olmadan da çalışması için
        $(document).off('change', '#izin_tipi').on('change', '#izin_tipi', function () {
            handleIzinTuruChange();
        });

        function handleIzinTuruChange() {
            var selectedId = $('#izin_tipi').val();
            var selectedText = $('#izin_tipi option:selected').text().toLowerCase();

            // Rapor kontrolü - 'rapor' kelimesi içeriyorsa
            var isRapor = selectedText.includes('rapor') || selectedText.includes('hastalık');

            if (isRapor) {
                // Rapor seçildi - alanları gizle
                $('#izin_durum_row').hide();
                $('#onaylayan_section').hide();

                // Otomatik onay yap
                $('#onay_durumu').val('Onaylandı').trigger('change');

                // Otomatik değerler ata
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var formattedDate = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes;

                $('[name="onay_aciklama"]').val('Rapor - Otomatik onaylandı');
                $('[name="onay_tarihi"]').val(formattedDate);
                if ($('[name="onay_tarihi"]')[0] && $('[name="onay_tarihi"]')[0]._flatpickr) {
                    $('[name="onay_tarihi"]')[0]._flatpickr.setDate(formattedDate);
                }
                $('#onaylayan_id').val(currentUserId);
            } else {
                // Normal izin - alanları göster
                $('#izin_durum_row').show();
                $('#onaylayan_section').show();
            }
        }

        // Tarih string'ini Date objesine çevir (dd.mm.yyyy veya yyyy-mm-dd formatını destekler)
        function parseTarih(tarihStr) {
            if (!tarihStr) return null;

            // dd.mm.yyyy formatı
            if (tarihStr.includes('.')) {
                var parts = tarihStr.split('.');
                if (parts.length === 3) {
                    return new Date(parts[2], parts[1] - 1, parts[0]);
                }
            }
            // yyyy-mm-dd formatı
            if (tarihStr.includes('-')) {
                var parts = tarihStr.split('-');
                if (parts.length === 3) {
                    return new Date(parts[0], parts[1] - 1, parts[2]);
                }
            }
            return new Date(tarihStr);
        }



        // Modal açıldığında form sıfırla ve ilk durumu ayarla
        $('#modalIzinEkle').on('show.bs.modal', function () {
            // Alanları göster (varsayılan durum)
            $('#izin_durum_row').show();
            $('#onaylayan_section').show();

            // İzin türü değişikliğini kontrol et
            setTimeout(function () {
                handleIzinTuruChange();
            }, 100);
        });

        // Yıllık Takvim İşlemleri
        const ayIsimleri = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
        const gunIsimleri = ["Pt", "Sa", "Ça", "Pe", "Cu", "Ct", "Pz"];

        $(document).off('click', '#btnListView').on('click', '#btnListView', function () {
            $(this).addClass('active btn-white shadow-sm').siblings().removeClass('active btn-white shadow-sm');
            $('#izinListContainer').show();
            $('#izinCalendarContainer').hide();
            $('#takvimYilSecici').hide();
            $('#btnCalendarFullscreen').hide();
        });

        $(document).off('click', '#btnCalendarView').on('click', '#btnCalendarView', function () {
            $(this).addClass('active btn-white shadow-sm').siblings().removeClass('active btn-white shadow-sm');
            $('#izinListContainer').hide();
            $('#izinCalendarContainer').show();
            $('#takvimYilSecici').show();
            $('#btnCalendarFullscreen').show();
            loadYearlyCalendar();
        });

        $(document).off('click', '#btnCalendarFullscreen').on('click', '#btnCalendarFullscreen', function () {
            $('body').addClass('calendar-fullscreen');
            $('#fsYilGosterge').text($('#yillik_takvim_yil').val());
        });

        $(document).off('click', '#btnExitCalendarFullscreen').on('click', '#btnExitCalendarFullscreen', function () {
            $('body').removeClass('calendar-fullscreen');
        });

        // ESC tuşu ile tam ekrandan çıkma
        $(document).on('keydown', function (e) {
            if (e.key === "Escape" && $('body').hasClass('calendar-fullscreen')) {
                $('body').removeClass('calendar-fullscreen');
            }
        });

        $(document).off('change', '#yillik_takvim_yil').on('change', '#yillik_takvim_yil', function () {
            loadYearlyCalendar();
            $('#fsYilGosterge').text($(this).val());
        });

        function loadYearlyCalendar() {
            const yil = $('#yillik_takvim_yil').val();
            const personelId = $('#personel_id').val();

            $('#yillikTakvimContainer').html('<div class="col-12 text-center p-5"><div class="spinner-border text-primary"></div></div>');

            $.post('views/personel/api/puantaj_izin.php', {
                action: 'get-personel-yearly-data',
                personel_id: personelId,
                yil: yil
            }, function (res) {
                if (res.status === 'success') {
                    renderYearlyCalendar(yil, res.data);
                } else {
                    $('#yillikTakvimContainer').html('<div class="col-12 text-center p-5"><div class="alert alert-danger">Veriler yüklenemedi.</div></div>');
                }
            });
        }

        function renderYearlyCalendar(year, events) {
            let html = '';
            for (let month = 0; month < 12; month++) {
                html += `<div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="year-calendar-month shadow-sm border">
                        <div class="year-calendar-header bg-light-subtle rounded-top py-2 mb-2">${ayIsimleri[month]}</div>
                        <div class="p-2">
                            <table class="year-calendar-table">
                                <thead>
                                    <tr>${gunIsimleri.map(g => `<th>${g}</th>`).join('')}</tr>
                                </thead>
                                <tbody>
                                    ${getMonthRows(year, month, events)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;
            }
            $('#yillikTakvimContainer').html(html);

            // Tooltipleri başlat
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('#yillikTakvimContainer [data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        function getMonthRows(year, month, events) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            let startingDay = firstDay.getDay(); // 0 (Paz) - 6 (Cmt)
            // Pt formatına çevir (Pazartesi 1, Pazar 7)
            startingDay = (startingDay === 0) ? 7 : startingDay;

            const totalDays = lastDay.getDate();
            let rows = '';
            let day = 1;

            for (let i = 0; i < 6; i++) {
                let cells = '';
                for (let j = 1; j <= 7; j++) {
                    if (i === 0 && j < startingDay) {
                        cells += '<td></td>';
                    } else if (day > totalDays) {
                        cells += '<td></td>';
                    } else {
                        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        const dayEvents = (events && events[dateStr]) ? events[dateStr] : [];
                        let cellContent = day;
                        let style = '';
                        let titleAttr = '';

                        if (dayEvents.length > 0) {
                            const event = dayEvents[0];
                            const eventStyle = getStyleFromTailwindProxy(event.color);
                            style = `background-color: ${eventStyle.bg} !important; color: ${eventStyle.color} !important; border-radius: 4px; font-weight: bold;`;
                            cellContent = event.kisa_kod;
                            titleAttr = `data-bs-toggle="tooltip" title="${event.name}"`;
                        }

                        const isToday = new Date().toISOString().split('T')[0] === dateStr;
                        const todayClass = isToday ? 'today' : '';

                        cells += `<td class="${todayClass}" style="${style}" ${titleAttr}>${cellContent}</td>`;
                        day++;
                    }
                }
                rows += `<tr>${cells}</tr>`;
                if (day > totalDays) break;
            }
            return rows;
        }

        function getStyleFromTailwindProxy(tailwindClass) {
            if (!tailwindClass)
                return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };

            // Check if it's already a hex
            if (tailwindClass.startsWith("#")) {
                return {
                    bg: tailwindClass + "26", // 15% opacity hex
                    color: tailwindClass,
                };
            }

            if (tailwindClass.includes("blue"))
                return { bg: "#dbeafe", color: "#2563eb" };
            if (tailwindClass.includes("amber") || tailwindClass.includes("warning"))
                return { bg: "#fef3c7", color: "#d97706" };
            if (tailwindClass.includes("red") || tailwindClass.includes("danger"))
                return { bg: "#fee2e2", color: "#dc2626" };
            if (tailwindClass.includes("pink"))
                return { bg: "#fce7f3", color: "#db2777" };
            if (tailwindClass.includes("gray"))
                return { bg: "#f3f4f6", color: "#4b5563" };
            if (tailwindClass.includes("green") || tailwindClass.includes("success"))
                return { bg: "#dcfce7", color: "#16a34a" };
            if (tailwindClass.includes("purple"))
                return { bg: "#f3e8ff", color: "#9333ea" };

            // Default to primary theme color (light style)
            return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };
        }
    });
</script>