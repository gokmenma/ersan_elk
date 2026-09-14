<?php
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;

/** Ekip Geçmişini Getir */
$gecmis = $id > 0 ? $PersonelModel->getEkipGecmisi($id) : [];
$ekip_kodlari_raw = $TanimlamalarModel->getMusaitEkipKodlari();

/** Ekip Bölgeleri */
$ekip_bolgeleri_raw = $TanimlamalarModel->getFilteredEkipBolgeleri();
$ekip_bolge_options = ['' => 'Tüm Bölgeler'];
foreach ($ekip_bolgeleri_raw as $bolge) {
    if ($bolge)
        $ekip_bolge_options[$bolge] = $bolge;
}

$calismaGecmisi = $id > 0 ? $PersonelModel->getCalismaGecmisi($id) : [];
$bugun = date('Y-m-d');
$aktifEkipSayisi = 0;
$aktifCalismaVar = false;
foreach ($gecmis as $ekipKaydi) {
    if ($ekipKaydi->baslangic_tarihi <= $bugun && (empty($ekipKaydi->bitis_tarihi) || $ekipKaydi->bitis_tarihi >= $bugun)) {
        $aktifEkipSayisi++;
    }
}
foreach ($calismaGecmisi as $calismaKaydi) {
    if ($calismaKaydi->ise_giris_tarihi <= $bugun && (empty($calismaKaydi->isten_cikis_tarihi) || $calismaKaydi->isten_cikis_tarihi >= $bugun)) {
        $aktifCalismaVar = true;
        break;
    }
}
if (!$aktifCalismaVar && empty($calismaGecmisi) && $id > 0 && !empty($personel->ise_giris_tarihi)) {
    $aktifCalismaVar = $personel->ise_giris_tarihi <= $bugun
        && (empty($personel->isten_cikis_tarihi) || $personel->isten_cikis_tarihi >= $bugun);
}
?>

<style>
    #calisma .work-overview {
        background: linear-gradient(135deg, rgba(85, 110, 230, .13), rgba(80, 165, 241, .05));
        border: 1px solid rgba(85, 110, 230, .18);
        border-radius: 14px;
        overflow: hidden;
    }
    #calisma .work-overview-icon,
    #modalCalismaBilgi .work-guide-icon {
        align-items: center;
        background: rgba(85, 110, 230, .12);
        border-radius: 12px;
        color: #556ee6;
        display: inline-flex;
        flex: 0 0 auto;
        height: 48px;
        justify-content: center;
        width: 48px;
    }
    #calisma .work-stat {
        background: var(--bs-body-bg, #fff);
        border: 1px solid rgba(128, 137, 150, .18);
        border-radius: 10px;
        min-width: 132px;
        padding: .65rem .85rem;
    }
    #calisma .work-stat-label { color: #74788d; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; }
    #calisma .work-stat-value { color: var(--bs-heading-color, #343a40); font-size: .95rem; font-weight: 600; }
    #calisma .work-summary-card {
        background: rgba(255, 255, 255, .72);
        border: 1px solid rgba(128, 137, 150, .18) !important;
        border-radius: 10px !important;
        padding: .8rem;
    }
    #calisma .work-summary-card .work-detail-label { color: #74788d; font-size: .72rem; margin-bottom: .35rem; white-space: nowrap; }
    #calisma .work-summary-card .work-detail-value { color: var(--bs-heading-color, #343a40); font-size: .9rem; font-weight: 600; margin: 0; }
    #calisma .work-table-card { border-radius: 14px !important; overflow: hidden; }
    #calisma .work-table-card .card-header { background: var(--bs-body-bg, #fff); }
    #calisma .work-table-card .table > :not(caption) > * > * { padding: .85rem .9rem; }
    #calisma .work-table-card .table thead th { color: #74788d; font-size: .75rem; letter-spacing: .025em; text-transform: uppercase; white-space: nowrap; }
    #modalCalismaBilgi .work-guide-step { border-left: 2px solid rgba(85, 110, 230, .2); padding: 0 0 1.25rem 1.25rem; position: relative; }
    #modalCalismaBilgi .work-guide-step:last-child { border-left-color: transparent; padding-bottom: 0; }
    #modalCalismaBilgi .work-guide-step::before { background: #556ee6; border: 4px solid #eef0fd; border-radius: 50%; content: ''; height: 14px; left: -8px; position: absolute; top: 3px; width: 14px; }
    [data-bs-theme="dark"] #calisma .work-overview { background: linear-gradient(135deg, rgba(85, 110, 230, .2), rgba(80, 165, 241, .08)); }
    [data-bs-theme="dark"] #calisma .work-summary-card { background: rgba(255, 255, 255, .035); }
    @media (max-width: 767.98px) {
        #calisma .work-stat { flex: 1 1 calc(50% - .5rem); min-width: 0; }
        #calisma .work-table-card .card-header { align-items: stretch !important; flex-direction: column; gap: .75rem; }
        #calisma .work-table-card .card-header .btn { width: 100%; }
    }
</style>

<div class="work-overview mb-3 p-3 p-lg-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div class="d-flex align-items-start gap-3">
            <span class="work-overview-icon"><i class="bx bx-briefcase-alt-2 fs-3"></i></span>
            <div>
                <h4 class="mb-1 text-dark">Çalışma yaşam döngüsü</h4>
                <p class="text-muted mb-0">Güncel bilgiler, ekip atamaları ve geçmiş çalışma dönemleri tek ekrandan yönetilir.</p>
            </div>
        </div>
        <button type="button" class="btn btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalCalismaBilgi">
            <i class="bx bx-help-circle me-1"></i> Tablolar nasıl çalışır?
        </button>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <div class="work-stat">
            <div class="work-stat-label">Çalışma durumu</div>
            <div class="work-stat-value mt-1" id="workCurrentStatus">
                <?php if ($id <= 0): ?>
                    <span class="text-muted">● Taslak</span>
                <?php elseif ($aktifCalismaVar): ?>
                    <span class="text-success">● Aktif</span>
                <?php else: ?>
                    <span class="text-secondary">● Pasif</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="work-stat">
            <div class="work-stat-label">Aktif ekip</div>
            <div class="work-stat-value mt-1"><span id="workActiveTeamCount"><?= (int) $aktifEkipSayisi ?></span> kayıt</div>
        </div>
        <div class="work-stat">
            <div class="work-stat-label">Çalışma geçmişi</div>
            <div class="work-stat-value mt-1"><span id="workHistoryCount"><?= count($calismaGecmisi) ?></span> dönem</div>
        </div>
    </div>

    <!-- Hidden inputs to preserve parent form submission -->
    <input type="hidden" name="ise_giris_tarihi" value="<?= Date::dmY($personel->ise_giris_tarihi ?? Date::today()) ?>">
    <input type="hidden" name="isten_cikis_tarihi" value="<?= Date::dmY($personel->isten_cikis_tarihi ?? null) ?>">
    <input type="hidden" name="personel_sinifi" id="personel_sinifi" value="<?= htmlspecialchars($personel->personel_sinifi ?? 'Beyaz Yaka') ?>">
    <input type="hidden" name="saha_takibi" id="saha_takibi" value="<?= htmlspecialchars($personel->saha_takibi ?? '0') ?>">
    <input type="hidden" name="arac_kullanim" id="arac_kullanim" value="<?= htmlspecialchars($personel->arac_kullanim ?? 'Yok') ?>">
    <input type="hidden" name="sgk_yapilan_firma" id="sgk_yapilan_firma" value="<?= htmlspecialchars($personel->sgk_yapilan_firma ?? 'Yok') ?>">
    <input type="hidden" name="gorunum_modulleri" id="gorunum_modulleri" value="<?= htmlspecialchars($personel->gorunum_modulleri ?? 'bordro,personel') ?>">

    <div class="border-top mt-3 pt-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="small fw-semibold text-dark"><i class="bx bx-calendar-check me-1 text-primary"></i> Güncel dönem bilgileri</span>
            <span class="badge bg-primary">Güncel Dönem</span>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-calendar me-1 text-primary"></i>İşe Giriş</div><p class="work-detail-value" id="display_ise_giris_tarihi"><?= Date::dmY($personel->ise_giris_tarihi ?? Date::today()) ?></p></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-calendar-x me-1 text-danger"></i>İşten Çıkış</div><p class="work-detail-value" id="display_isten_cikis_tarihi"><?= !empty($personel->isten_cikis_tarihi) ? Date::dmY($personel->isten_cikis_tarihi) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?></p></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-users me-1 text-info"></i>Personel Sınıfı</div><p class="work-detail-value" id="display_personel_sinifi"><?= htmlspecialchars($personel->personel_sinifi ?? 'Beyaz Yaka') ?></p></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-map-pin me-1 text-warning"></i>Saha Takibi</div><p class="work-detail-value" id="display_saha_takibi"><?= ($personel->saha_takibi ?? 0) == 1 ? '<span class="badge bg-soft-success text-success">Evet</span>' : '<span class="badge bg-soft-danger text-danger">Hayır</span>' ?></p></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-car me-1 text-primary"></i>Araç Kullanımı</div><p class="work-detail-value" id="display_arac_kullanim"><?= htmlspecialchars($personel->arac_kullanim ?? 'Yok') ?></p></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="work-summary-card h-100"><div class="work-detail-label"><i class="bx bx-book-open me-1 text-purple"></i>SGK Firması</div><p class="work-detail-value" id="display_display_sgk_yapilan_firma"><span class="badge bg-soft-purple text-purple" id="display_sgk_yapilan_firma"><?= htmlspecialchars($personel->sgk_yapilan_firma ?? 'Yok') ?></span></p></div></div>
        </div>

        <div class="mt-2" id="display_ayrilis_nedeni_wrapper" style="<?= empty($personel->isten_cikis_tarihi) ? 'display:none;' : '' ?>">
            <div class="p-2 px-3 border rounded d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2" style="background: rgba(244, 106, 106, 0.05); border-color: rgba(244, 106, 106, 0.2) !important;">
                <div class="text-dark small"><i class="bx bx-info-circle me-1 text-danger"></i><strong>Ayrılış nedeni:</strong> <span id="display_isten_ayrilis_nedeni"><?= htmlspecialchars($personel->isten_ayrilis_nedeni ?? 'Nedeni belirtilmedi') ?></span></div>
                <div id="display_ayrilis_belge_wrapper" style="<?= empty($personel->isten_ayrilis_belge_yolu) ? 'display:none;' : '' ?>">
                    <a id="display_btn_ayrilis_belge" href="<?= htmlspecialchars($personel->isten_ayrilis_belge_yolu ?? '#') ?>" target="_blank" class="btn btn-sm btn-soft-danger"><i class="bx bx-download me-1"></i>Ayrılış Belgesi</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">

    <!-- Sağ Kolon: Ekip Bilgileri -->
    <div class="col-md-12">
        <div class="card border h-100 shadow-sm work-table-card">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="card-title mb-1 text-primary fw-bold"><i class="bx bx-group me-2"></i>Ekip Atama Geçmişi</h5>
                    <div class="small text-muted">Personelin görev aldığı ekipleri ve ekip şefliği durumunu tarih bazında yönetin.</div>
                </div>
                <?php if ($id > 0): ?>
                    <button type="button" class="btn btn-sm btn-primary px-3 shadow-none" id="btnOpenEkipGecmisiModal">
                        <i class="bx bx-plus me-1"></i> Yeni Ekip Tanımla
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <div class="table-responsive">
                        <table id="tblEkipGecmisi" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">Ekip Adı / Kodu</th>
                                    <th data-filter="date">Başlangıç Tarihi</th>
                                    <th data-filter="date">Bitiş Tarihi</th>
                                    <th data-filter="select">Ekip Şefi</th>
                                    <th data-filter="select">Durum</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($gecmis)): ?>
                                    <?php foreach ($gecmis as $g): ?>
                                        <tr>
                                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($g->ekip_adi ?? '') ?></span>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($g->baslangic_tarihi)) ?></td>
                                            <td><?= $g->bitis_tarihi ? date('d.m.Y', strtotime($g->bitis_tarihi)) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?>
                                            </td>
                                            <td>
                                                <?= isset($g->ekip_sefi_mi) && $g->ekip_sefi_mi == 1 ? '<span class="badge bg-success">Evet</span>' : '<span class="badge bg-secondary">Hayır</span>' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $bugun = date('Y-m-d');
                                                if ($g->baslangic_tarihi <= $bugun && ($g->bitis_tarihi === null || $g->bitis_tarihi >= $bugun)) {
                                                    echo '<span class="badge bg-success">Aktif</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">Pasif</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <button type="button"
                                                    class="btn btn-sm btn-soft-primary btn-ekip-gecmisi-duzenle me-1"
                                                    data-id="<?= $g->id ?>" title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-ekip-gecmisi-sil"
                                                    data-id="<?= $g->id ?>" title="Sil">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bx bx-info-circle fs-2 mb-2 d-block"></i>
                        Yeni personel eklerken önce personeli kaydedin, ardından ekip tanımlaması yapabilirsiniz.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon altı: Çalışma Bilgileri Geçmişi -->
    <div class="col-md-12 mt-3">
        <div class="card border h-100 shadow-sm work-table-card">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="card-title mb-1 text-primary fw-bold"><i class="bx bx-book-open me-2"></i>Çalışma Bilgileri Geçmişi</h5>
                    <div class="small text-muted">SGK firması ve çalışma tercihlerini dönemler halinde kayıt altında tutun.</div>
                </div>
                <?php if ($id > 0): ?>
                    <button type="button" class="btn btn-sm btn-primary px-3 shadow-none" id="btnOpenCalismaGecmisiModal">
                        <i class="bx bx-plus me-1"></i> Yeni Çalışma Dönemi Ekle
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <div class="table-responsive">
                        <table id="tblCalismaGecmisi" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="display:none">ID</th>
                                    <th data-filter="string">SGK Yapılan Firma</th>
                                    <th data-filter="date">İşe Giriş Tarihi</th>
                                    <th data-filter="date">İşten Çıkış Tarihi</th>
                                    <th data-filter="select">Sınıf</th>
                                    <th data-filter="select">Saha Takibi</th>
                                    <th data-filter="select">Araç Kullanım</th>
                                    <th data-filter="select">Durum</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($calismaGecmisi)): ?>
                                    <?php
                                    $bugun = date('Y-m-d');
                                    foreach ($calismaGecmisi as $c):
                                        $iseGiris = $c->ise_giris_tarihi;
                                        $istenCikis = $c->isten_cikis_tarihi ? $c->isten_cikis_tarihi : null;
                                        $isAktif = ($iseGiris <= $bugun && ($istenCikis === null || $istenCikis >= $bugun));
                                    ?>
                                        <tr>
                                            <td style="display:none"><?= $c->id ?></td>
                                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($c->sgk_yapilan_firma ?? '') ?></span></td>
                                            <td><?= date('d.m.Y', strtotime($iseGiris)) ?></td>
                                            <td><?= $istenCikis ? date('d.m.Y', strtotime($istenCikis)) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?></td>
                                            <td>
                                                <?= $c->personel_sinifi === 'Beyaz Yaka' 
                                                    ? '<span class="badge bg-soft-info text-info"><i class="bx bx-user me-1"></i>Beyaz Yaka</span>' 
                                                    : '<span class="badge bg-soft-warning text-warning"><i class="bx bx-wrench me-1"></i>Mavi Yaka</span>' ?>
                                            </td>
                                            <td>
                                                <?= $c->saha_takibi == 1 
                                                    ? '<span class="badge bg-soft-success text-success"><i class="bx bx-check-circle me-1"></i>Evet</span>' 
                                                    : '<span class="badge bg-soft-danger text-danger"><i class="bx bx-x-circle me-1"></i>Hayır</span>' ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary"><i class="bx bx-car me-1"></i><?= htmlspecialchars($c->arac_kullanim) ?></span>
                                            </td>
                                            <td>
                                                <?= $isAktif ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <?php if (!empty($c->isten_ayrilis_belge_yolu)): ?>
                                                    <a href="<?= htmlspecialchars($c->isten_ayrilis_belge_yolu) ?>" target="_blank" class="btn btn-sm btn-soft-danger me-1" title="Ayrılış Belgesi">
                                                        <i class="bx bx-file"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-soft-primary btn-calisma-gecmisi-duzenle me-1"
                                                    data-id="<?= $c->id ?>" title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-calisma-gecmisi-sil"
                                                    data-id="<?= $c->id ?>" title="Sil">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bx bx-info-circle fs-2 mb-2 d-block"></i>
                        Yeni personel eklerken önce personeli kaydedin, ardından çalışma bilgileri tanımlaması yapabilirsiniz.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
