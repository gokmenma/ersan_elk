<?php
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;

/** Ekip Geçmişini Getir */
$gecmis = $id > 0 ? $PersonelModel->getEkipGecmisi($id) : [];
$ekip_kodlari_raw = $TanimlamalarModel->getMusaitEkipKodlari();

/** Ekip Bölgeleri */
$ekip_bolgeleri_raw = $TanimlamalarModel->getEkipBolgeleri();
$ekip_bolge_options = ['' => 'Tüm Bölgeler'];
foreach ($ekip_bolgeleri_raw as $bolge) {
    if ($bolge)
        $ekip_bolge_options[$bolge] = $bolge;
}
?>

<div class="row">
    <!-- Sol Kolon: Pozisyon ve Durum -->
    <div class="col-md-12 mb-3">
        <div class="card border h-100 mb-2 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-soft-primary border-bottom d-flex align-items-center justify-content-between py-3">
                <h5 class="card-title mb-0 text-primary fw-bold"><i class="bx bx-briefcase me-2"></i>Aktif Çalışma Bilgileri</h5>
                <span class="badge bg-primary text-uppercase px-3 py-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Güncel Dönem</span>
            </div>
            <div class="card-body bg-light-soft p-4">
                <div class="alert alert-info border-0 shadow-none d-flex align-items-center mb-4" style="background: rgba(80, 165, 241, 0.08); border-radius: 10px;">
                    <i class="bx bx-info-circle text-primary fs-3 me-3"></i>
                    <div class="small text-dark">
                        Bu alanlar personelin **Çalışma Bilgileri Geçmişi** tablosundan otomatik olarak belirlenir. Değişiklik yapmak veya yeni bir çalışma dönemi eklemek için lütfen sayfanın altındaki geçmiş tablosunu kullanın.
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

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-calendar me-1 text-primary"></i>İşe Giriş Tarihi</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_ise_giris_tarihi"><?= Date::dmY($personel->ise_giris_tarihi ?? Date::today()) ?></h5>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-calendar-x me-1 text-danger"></i>İşten Çıkış Tarihi</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_isten_cikis_tarihi">
                                <?= $personel->isten_cikis_tarihi ? Date::dmY($personel->isten_cikis_tarihi) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?>
                            </h5>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-users me-1 text-info"></i>Personel Sınıfı</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_personel_sinifi"><?= htmlspecialchars($personel->personel_sinifi ?? 'Beyaz Yaka') ?></h5>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-map-pin me-1 text-warning"></i>Saha Takibi</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_saha_takibi">
                                <?= ($personel->saha_takibi ?? 0) == 1 ? '<span class="badge bg-soft-success text-success">Evet</span>' : '<span class="badge bg-soft-danger text-danger">Hayır</span>' ?>
                            </h5>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-car me-1 text-primary"></i>Araç Kullanımı</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_arac_kullanim"><?= htmlspecialchars($personel->arac_kullanim ?? 'Yok') ?></h5>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-white shadow-none h-100 d-flex flex-column justify-content-between">
                            <div class="text-muted small mb-1"><i class="bx bx-book-open me-1 text-purple"></i>SGK Yapılan Firma</div>
                            <h5 class="fw-bold text-dark mb-0" id="display_display_sgk_yapilan_firma">
                                <span class="badge bg-soft-purple text-purple px-2 py-1" id="display_sgk_yapilan_firma"><?= htmlspecialchars($personel->sgk_yapilan_firma ?? 'Yok') ?></span>
                            </h5>
                        </div>
                    </div>

                    <!-- Ayrılış Detayları (Eğer çıkış tarihi varsa görüntülenir) -->
                    <div class="col-md-12" id="display_ayrilis_nedeni_wrapper" style="<?= empty($personel->isten_cikis_tarihi) ? 'display:none;' : '' ?>">
                        <div class="p-3 border rounded bg-soft-danger-light shadow-none" style="background: rgba(244, 106, 106, 0.05); border-color: rgba(244, 106, 106, 0.2); border-radius: 8px;">
                            <div class="text-danger small fw-bold mb-2"><i class="bx bx-info-circle me-1"></i> İşten Ayrılış Bilgileri</div>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-10">
                                    <div class="text-dark small"><strong>Ayrılış Nedeni:</strong> <span id="display_isten_ayrilis_nedeni"><?= htmlspecialchars($personel->isten_ayrilis_nedeni ?? 'Nedeni belirtilmedi') ?></span></div>
                                </div>
                                <div class="col-md-2 text-md-end" id="display_ayrilis_belge_wrapper" style="<?= empty($personel->isten_ayrilis_belge_yolu) ? 'display:none;' : '' ?>">
                                    <a id="display_btn_ayrilis_belge" href="<?= htmlspecialchars($personel->isten_ayrilis_belge_yolu ?? '#') ?>" target="_blank" class="btn btn-sm btn-danger px-3 shadow-none">
                                        <i class="bx bx-download me-1"></i> Ayrılış Belgesi
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: Ekip Bilgileri -->
    <div class="col-md-12">
        <div class="card border h-100 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 text-primary fw-bold"><i class="bx bx-group me-2"></i>Ekip Kodu İşlemleri</h5>
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
                                    <th>Ekip Adı / Kodu</th>
                                    <th>Başlangıç Tarihi</th>
                                    <th>Bitiş Tarihi</th>
                                    <th>Ekip Şefi</th>
                                    <th>Durum</th>
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
        <div class="card border h-100 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 text-primary fw-bold"><i class="bx bx-book-open me-2"></i>Çalışma Bilgileri Geçmişi</h5>
                <?php if ($id > 0): ?>
                    <button type="button" class="btn btn-sm btn-primary px-3 shadow-none" id="btnOpenCalismaGecmisiModal">
                        <i class="bx bx-plus me-1"></i> Yeni Çalışma Dönemi Ekle
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <?php
                    $calismaGecmisi = $PersonelModel->getCalismaGecmisi($id);
                    ?>
                    <div class="table-responsive">
                        <table id="tblCalismaGecmisi" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="display:none">ID</th>
                                    <th>SGK Yapılan Firma</th>
                                    <th>İşe Giriş Tarihi</th>
                                    <th>İşten Çıkış Tarihi</th>
                                    <th>Sınıf</th>
                                    <th>Saha Takibi</th>
                                    <th>Araç Kullanım</th>
                                    <th>Durum</th>
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