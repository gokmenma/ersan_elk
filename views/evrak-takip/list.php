<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Helper\Form;
use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;
use App\Service\Gate;

$Evrak = new EvrakTakipModel();
$Personel = new PersonelModel();

$evraklar = [];
$evrakEkleriMap = [];
$personeller = $Personel->all(false, 'all_with_external');
$stats = $Evrak->getStats();
$gelen_evraklar = $Evrak->getGelenEvraklar();

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$uetsOkunmamisSayisi = (int) (new \App\Model\EvrakSevkModel())->unreadUets((int) ($_SESSION['firma_id'] ?? 0), $currentUserId, 0)['count'];
$canSendKep = Gate::allows('evrak-takip/kep');
$canUetsYonet = Gate::allows('evrak-takip/uets');
$canMobilImzaAyar = Gate::allows('mobil_imza_ayarlari_sekmesi');
$onayMap = [];
$imzamiBekleyenSayisi = $Evrak->countPendingSignatureForUser($currentUserId);
$onayDetayMap = [];

$onayAkisiIcerigi = static function (array $imzalar, object $evrak): string {
    $esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $durum = $evrak->onay_durumu ?? 'taslak';
    $tarihBicimi = static fn($value): string => !empty($value) ? date('d.m.Y H:i', strtotime((string) $value)) : '';

    $ustBilgi = match ($durum) {
        'onaylandi' => 'Tüm imzalar tamamlandı, evrak elektronik imzalı.',
        'onay_bekliyor' => 'Evrak onaya sunuldu, imzalar sırayla atılıyor.',
        default => 'Evrak henüz onaya sunulmadı. Planlanan imza sırası:',
    };

    $satirlar = '';
    $siradakiBulundu = false;
    foreach ($imzalar as $imza) {
        $imzalandi = $imza['durum'] === 'onaylandi';
        if ($imzalandi) {
            $etiket = '<span class="badge bg-success-subtle text-success">İmzalandı · ' . $esc($tarihBicimi($imza['onay_tarihi'])) . '</span>';
        } elseif ($durum === 'onay_bekliyor' && !$siradakiBulundu) {
            $etiket = '<span class="badge bg-warning text-dark">Sırada</span>';
            $siradakiBulundu = true;
        } else {
            $etiket = '<span class="badge bg-secondary-subtle text-secondary">Bekliyor</span>';
        }
        $unvan = trim((string) $imza['imza_unvani']) !== ''
            ? '<span class="d-block text-muted onay-akis-unvan">' . $esc($imza['imza_unvani']) . '</span>'
            : '';
        $satirlar .= '<li class="onay-akis-satir">'
            . '<span class="badge bg-light text-muted me-1">' . (int) $imza['sira'] . '</span>'
            . '<span class="fw-semibold">' . $esc($imza['adi_soyadi']) . '</span>'
            . $unvan . $etiket
            . '</li>';
    }

    $altBilgi = '';
    if ($durum === 'onaylandi' && !empty($evrak->e_imza_onay_tarihi)) {
        $altBilgi = '<div class="onay-akis-alt text-muted">Onay tamamlanma: ' . $esc($tarihBicimi($evrak->e_imza_onay_tarihi));
        if (!empty($evrak->e_imza_belge_ozeti)) {
            $altBilgi .= '<br>Doğrulama kodu: ' . $esc(strtoupper(substr((string) $evrak->e_imza_belge_ozeti, 0, 12)));
        }
        $altBilgi .= '</div>';
    } elseif (!empty($evrak->e_imza_iade_gerekcesi)) {
        $altBilgi = '<div class="onay-akis-alt text-danger">Son iade gerekçesi: ' . $esc($evrak->e_imza_iade_gerekcesi) . '</div>';
    }

    return '<div class="onay-akis-ust text-muted">' . $esc($ustBilgi) . '</div>'
        . '<ul class="list-unstyled mb-0">' . $satirlar . '</ul>'
        . $altBilgi;
};

$maintitle = "Evrak Takip";
$title = "Evrak Listesi";
?>
<script>try { document.documentElement.classList.toggle('evrak-summary-hidden', localStorage.getItem('evrak_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.evrak-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Standart) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-file fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Evrak Takip<?php if ($uetsOkunmamisSayisi > 0): ?> <span class="badge bg-danger rounded-pill ms-1 font-size-11" id="uetsOkunmamisRozet" title="Okunmamış UETS tebligatı"><i class="bx bx-envelope"></i> <?= (int) $uetsOkunmamisSayisi; ?> okunmamış UETS</span><?php endif; ?></h4>
                <p class="text-muted mb-0 font-size-12">Gelen ve giden tüm resmi evrakların kaydı, e-imza onay akışı ve takibi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Kayıt Menüsü -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-primary top-action-btn dropdown-toggle shadow-sm text-white" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-plus font-size-16"></i> Yeni
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="min-width:250px">
                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnYeniEvrak">
                        <i class="bx bx-down-arrow-circle text-success font-size-20 mt-1"></i><span><strong class="d-block">Gelen Evrak</strong><small class="text-muted">Kuruma ulaşan evrakı kaydedin</small></span>
                    </button>
                    <a href="index?p=evrak-takip/giden-evrak" class="dropdown-item d-flex align-items-start gap-2 py-2">
                        <i class="bx bx-up-arrow-circle text-warning font-size-20 mt-1"></i><span><strong class="d-block">Giden Evrak</strong><small class="text-muted">Resmî üst yazı hazırlayın</small></span>
                    </a>
                    <?php if ($canSendKep): ?>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnYeniKepGonderisi">
                        <i class="bx bx-mail-send text-info font-size-20 mt-1"></i><span><strong class="d-block">KEP Gönderisi</strong><small class="text-muted">PTT KEP üzerinden doğrudan gönderin</small></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <div class="dropdown-divider"></div>
                    <?php if ($canUetsYonet): ?>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnUetsSync">
                        <i class="bx bx-sync me-2 text-primary font-size-16"></i> UETS Senkronize Et
                    </button>
                    <?php endif; ?>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnKepGelenSync">
                        <i class="bx bx-mail-send me-2 text-primary font-size-16"></i> KEP Gelen Kutusunu Al
                    </button>
                    <?php if ($canUetsYonet || $canSendKep): ?>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnUetsSettings">
                        <i class="bx bx-slider me-2 text-secondary font-size-16"></i> UETS / KEP Ayarları
                    </button>
                    <?php endif; ?>
                    <?php if ($canMobilImzaAyar): ?>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnMobilImzaAyarlari" data-bs-toggle="modal" data-bs-target="#modalMobilImzaAyarlari">
                        <i class="bx bx-mobile-alt me-2 text-info font-size-16"></i> Mobil İmza Ayarları
                    </button>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center py-2" href="downloads/ErsanEImza.jar?v=1.3.1" download="ErsanEImza.jar" title="E-İmza Java uygulamasını indir">
                        <i class="bx bxl-java me-2 text-danger font-size-18"></i>
                        <span><span class="d-block fw-semibold">E-İmza Java Uygulamasını İndir</span><small class="text-muted">Masaüstü imza köprüsü (.jar)</small></span>
                    </a>
                </div>
            </div>

            <!-- 4. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM EVRAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM EVRAK</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-file"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_evrak"><?= $stats->toplam_evrak ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_toplam">Gelen: <?= $stats->gelen_evrak ?? 0; ?> | Giden: <?= $stats->giden_evrak ?? 0; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-filter-tip="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: GELEN EVRAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GELEN EVRAK</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-down-arrow-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_gelen_evrak"><?= $stats->gelen_evrak ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_gelen">Giriş Kayıtları</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-tip="gelen">
                            <i class="bx bx-down-arrow-alt"></i> Gelen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: GİDEN EVRAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GİDEN EVRAK</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-up-arrow-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_giden_evrak"><?= $stats->giden_evrak ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_giden">Çıkış Kayıtları</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-tip="giden">
                            <i class="bx bx-up-arrow-alt"></i> Giden
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: CEVAP BEKLEYEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">CEVAP BEKLEYEN</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-time-five"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_cevap_bekleyen"><?= $stats->cevap_bekleyen ?? 0; ?> Adet</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold" id="stat_sub_cevap">Bekleyen Evraklar</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-tip="cevap_bekleyen">
                            <i class="bx bx-time"></i> Bekleyen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- İmzamı Bekleyen Evrak Uyarı Çubuğu -->
    <?php if ($imzamiBekleyenSayisi > 0): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 border-0 shadow-sm mb-3 rounded-3" id="imzaBekleyenUyari">
            <div class="d-flex align-items-center">
                <div class="p-2 bg-warning bg-opacity-25 text-warning-emphasis rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                    <i class="bx bx-edit fs-5"></i>
                </div>
                <span>
                    <strong>İmzanızı bekleyen <?= $imzamiBekleyenSayisi; ?> evrak var.</strong>
                    <span class="small d-block text-muted">Sıra sizde olan giden evrakları imzalayabilir veya düzeltilmek üzere iade edebilirsiniz.</span>
                </span>
            </div>
            <button type="button" id="btnImzaFiltre" class="btn btn-warning btn-sm fw-semibold px-3 rounded-pill shadow-xs" data-aktif="0">
                <i class="bx bx-filter-alt me-1"></i> Sadece Bunları Göster
            </button>
        </div>
    <?php endif; ?>

    <!-- 3. Standart DataTables Evrak Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="evrakListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Evrak Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, canlı sütun filtreleme ve e-imza yönetimi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="evrakTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 45px;" data-filter="none">#</th>
                            <th class="text-center" style="width: 80px;" data-filter="select">TİP</th>
                            <th class="text-center" style="width: 105px;" data-filter="none">KEP DURUMU</th>
                            <th style="width: 100px;" data-filter="date">TARİH</th>
                            <th data-filter="string">KONU / EVRAK NO</th>
                            <th data-filter="string">GELEN/GİDEN KURUM</th>
                            <th data-filter="string">İŞLEM YAPAN PERSONEL</th>
                            <th data-filter="string">ZİMMETLİ (OFİS)</th>
                            <th data-filter="string">İLGİLİ PERSONEL</th>
                            <th class="text-center" style="width: 85px;" data-filter="select">CEVAP</th>
                            <th class="text-center" style="width: 110px;" data-filter="select">E-İMZA</th>
                            <th class="text-center" style="width: 95px;" data-filter="none">DOSYA</th>
                            <th class="text-center" style="min-width: 120px; width: 130px;" data-filter="none">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($evraklar as $evrak):
                            $encryptedEvrakId = Security::encrypt($evrak->id);
                            $onayDurumu = $evrak->onay_durumu ?? 'taslak';
                            $onayBilgisi = $onayMap[(int) $evrak->id] ?? ['toplam' => 0, 'onaylanan' => 0, 'bekleyen_imzam' => false];
                            $kilitli = $onayDurumu !== 'taslak';
                            $geriAlinabilir = $Evrak->canRevokeApproval($evrak, $currentUserId);
                            $siraBende = $onayDurumu === 'onay_bekliyor' && !empty($onayBilgisi['sira_bende']);
                            $evrakTipi = (string) ($evrak->evrak_tipi ?? 'gelen');
                            $cevapDurumu = ($evrakTipi === 'gelen') ? ($evrak->cevap_verildi_mi ? 'EVET' : 'BEKLEMEDE') : '-';
                        ?>
                            <tr data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>"
                                data-imza-bekliyor="<?= $siraBende ? '1' : '0'; ?>"
                                data-tip="<?= htmlspecialchars($evrakTipi, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cevap="<?= htmlspecialchars($cevapDurumu, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="text-center">
                                    <span class="fw-bold text-muted"><?= $i++; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php $isKepEvrak = ($evrak->kaynak_tipi ?? '') === 'kep' || !empty($evrak->kep_mesaj_id) || ($evrak->gonderim_kanali ?? '') === 'kep'; ?>
                                    <?php if ($evrakTipi === 'gelen'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                            <i class="bx bx-down-arrow-alt"></i> GELEN
                                        </span>
                                    <?php elseif ($isKepEvrak): ?>
                                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="KEP ile gönderilen evrak">
                                            <i class="bx bx-mail-send"></i> GİDEN / KEP
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                            <i class="bx bx-up-arrow-alt"></i> GİDEN
                                        </span>
                                    <?php endif; ?>
                                    <?php if (($evrak->kaynak_tipi ?? '') === 'uets' || !empty($evrak->uets_mesaj_id)): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0.5 font-size-10 fw-semibold" title="UETS E-Tebligat">
                                                <i class="bx bx-envelope"></i> UETS
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($evrakTipi === 'gelen' && $isKepEvrak): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 font-size-10 fw-semibold" title="PTT KEP İletisi">
                                                <i class="bx bx-mail-send"></i> KEP
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="text-muted small">-</span></td>
                                <?php
                                $tarihRaw = !empty($evrak->tarih) ? (string) $evrak->tarih : '1970-01-01';
                                $saatRaw = !empty($evrak->olusturma_tarihi)
                                    ? date('H:i:s', strtotime((string) $evrak->olusturma_tarihi))
                                    : (!empty($evrak->olusturulma_tarihi) ? date('H:i:s', strtotime((string) $evrak->olusturulma_tarihi)) : '00:00:00');
                                $orderTarih = date('Y-m-d H:i:s', strtotime($tarihRaw . ' ' . $saatRaw));
                                $saatGosterim = !empty($evrak->olusturma_tarihi)
                                    ? date('H:i', strtotime((string) $evrak->olusturma_tarihi))
                                    : (!empty($evrak->olusturulma_tarihi) ? date('H:i', strtotime((string) $evrak->olusturulma_tarihi)) : '');
                                ?>
                                <td data-order="<?= htmlspecialchars($orderTarih, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark font-size-12"><?= !empty($evrak->tarih) ? date('d.m.Y', strtotime((string) $evrak->tarih)) : '-'; ?></span>
                                        <span class="text-muted font-size-11"><?= htmlspecialchars($saatGosterim !== '' ? $saatGosterim : '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($evrak->son_tarih)): ?>
                                            <span class="badge bg-danger-subtle text-danger font-size-10 px-1 py-0.5 mt-1" title="Son Tarih / Termin">
                                                <i class="bx bx-time-five me-1"></i>Son: <?= date('d.m.Y', strtotime((string)$evrak->son_tarih)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <?php if ($evrakTipi === 'gelen'): ?>
                                            <a href="javascript:void(0);"
                                               class="fw-bold text-dark font-size-13 text-decoration-none evrak-duzenle evrak-konu-text"
                                               data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>"
                                               title="Evrak detayını aç">
                                                <?= htmlspecialchars((string) ($evrak->konu ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a class="fw-bold text-dark font-size-13 text-decoration-none evrak-konu-text"
                                               href="index?p=evrak-takip/giden-evrak&amp;id=<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>"
                                               title="Evrak detayını aç">
                                                <?= htmlspecialchars((string) ($evrak->konu ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <span class="text-muted font-size-11 d-flex align-items-center gap-1 mt-0.5">
                                            <i class="bx bx-hash"></i> <?= htmlspecialchars((string) ($evrak->evrak_no ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark font-size-12"><?= htmlspecialchars((string) ($evrak->kurum_adi ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-muted font-size-11 d-flex align-items-center gap-1">
                                            <i class="bx bx-building"></i> Kurum/Firma
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($evrak->personel_adi)): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-1.5 flex-shrink-0" style="width: 22px; height: 22px; font-size: 11px;">
                                                <i class="bx bx-user-check"></i>
                                            </div>
                                            <span class="font-size-12 fw-semibold text-dark text-truncate" style="max-width: 130px;"><?= htmlspecialchars((string) $evrak->personel_adi, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <button type="button" class="btn btn-link text-primary p-0 ms-1.5 evrak-bildir-manuel flex-shrink-0" 
                                                data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" 
                                                data-personel-id="<?= $evrak->personel_id; ?>"
                                                data-type="personel"
                                                data-last-notified="<?= htmlspecialchars((string) ($evrak->son_bildirim_tarihi_personel ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Bildirim ve Mail Gönder">
                                                <i class="bx bx-bell font-size-14"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($evrak->ilgili_personel_adi)): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center me-1.5 flex-shrink-0" style="width: 22px; height: 22px; font-size: 11px;">
                                                <i class="bx bx-user"></i>
                                            </div>
                                            <span class="font-size-12 fw-semibold text-info text-truncate" style="max-width: 130px;"><?= htmlspecialchars((string) $evrak->ilgili_personel_adi, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <button type="button" class="btn btn-link text-warning p-0 ms-1.5 evrak-bildir-manuel flex-shrink-0" 
                                                data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" 
                                                data-personel-id="<?= $evrak->ilgili_personel_id; ?>"
                                                data-type="ilgili"
                                                data-last-notified="<?= htmlspecialchars((string) ($evrak->son_bildirim_tarihi_ilgili ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Bildirim ve Mail Gönder">
                                                <i class="bx bx-bell font-size-14"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($evrakTipi === 'gelen'): ?>
                                        <?php if ($evrak->cevap_verildi_mi): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" title="Cevap Tarihi: <?= $evrak->cevap_tarihi ? date('d.m.Y', strtotime($evrak->cevap_tarihi)) : '-'; ?>">
                                                EVET
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                BEKLEMEDE
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $imzaKayitlari = $onayDetayMap[(int) $evrak->id] ?? [];
                                    $akisAttr = '';
                                    if ($evrakTipi === 'giden' && $imzaKayitlari !== []) {
                                        $akisAttr = ' tabindex="0" role="button" data-onay-akis="'
                                            . htmlspecialchars($onayAkisiIcerigi($imzaKayitlari, $evrak), ENT_QUOTES, 'UTF-8') . '"';
                                    }
                                    ?>
                                    <?php if ($evrakTipi !== 'giden' || $onayBilgisi['toplam'] === 0): ?>
                                        <span class="text-muted small">-</span>
                                    <?php elseif ($onayDurumu === 'onaylandi'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold e-imza-rozet evrak-imza-onizle" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Evrakı ve imza tarihçesini görüntüle"<?= $akisAttr; ?>>
                                            <i class="bx bx-check-double me-0.5"></i> ONAYLI
                                        </span>
                                    <?php elseif ($onayDurumu === 'onay_bekliyor'): ?>
                                        <span class="badge <?= $siraBende ? 'bg-warning text-dark' : 'bg-warning-subtle text-warning border border-warning-subtle'; ?> rounded-pill px-2 py-1 font-size-11 fw-semibold e-imza-rozet evrak-imza-onizle" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Evrakı ve imza tarihçesini görüntüle"<?= $akisAttr; ?>>
                                            <i class="bx bx-time-five me-0.5"></i> <?= $siraBende ? 'İMZANIZDA' : 'ONAYDA'; ?> <?= $onayBilgisi['onaylanan'] . '/' . $onayBilgisi['toplam']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold e-imza-rozet evrak-imza-onizle" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Evrakı ve imza tarihçesini görüntüle"<?= $akisAttr; ?>>
                                            TASLAK
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $evrakEkleri = $evrakEkleriMap[(int) $evrak->id] ?? [];
                                    if (!empty($evrak->dosya_yolu)) {
                                        $anaDosyaYolu = ltrim((string) $evrak->dosya_yolu, '/');
                                        $anaDosyaListede = array_filter(
                                            $evrakEkleri,
                                            static fn(object $ek): bool => ltrim((string) $ek->dosya_yolu, '/') === $anaDosyaYolu
                                        );
                                        if ($anaDosyaListede === []) {
                                            array_unshift($evrakEkleri, (object) [
                                                'dosya_adi' => basename((string) $evrak->dosya_yolu),
                                                'dosya_yolu' => $evrak->dosya_yolu,
                                            ]);
                                        }
                                    }
                                    $ustYaziDosyasiVar = $evrakTipi === 'giden' && empty($evrak->ust_yazi_gerekli_degil);
                                    ?>
                                    <?php if ($evrakEkleri !== [] || $ustYaziDosyasiVar): ?>
                                        <div class="dropdown d-inline-block">
                                            <button type="button"
                                                class="btn btn-sm btn-subtle-info rounded-pill px-2 py-1 font-size-11 fw-semibold d-inline-flex align-items-center gap-1 dropdown-toggle shadow-xs"
                                                data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false"
                                                title="Evraka ait dosyaları görüntüle">
                                                <i class="bx bx-paperclip"></i>
                                                <?= count($evrakEkleri) + ($ustYaziDosyasiVar ? 1 : 0); ?> DOSYA
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end evrak-dosya-menu shadow-lg border-0">
                                                <?php if ($ustYaziDosyasiVar): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 evrak-pdf-goruntule" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <i class="bx bx-file-blank text-danger flex-shrink-0 font-size-16"></i>
                                                            <span class="text-truncate">Üst Yazı.pdf</span>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <?php foreach ($evrakEkleri as $ekIndex => $ek): ?>
                                                    <li>
                                                        <a class="dropdown-item d-flex align-items-center gap-2"
                                                            href="<?= htmlspecialchars((string) $ek->dosya_yolu, ENT_QUOTES, 'UTF-8'); ?>"
                                                            target="_blank" rel="noopener">
                                                            <i class="bx bx-file text-info flex-shrink-0 font-size-16"></i>
                                                            <span class="text-truncate" title="<?= htmlspecialchars((string) $ek->dosya_adi, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?= ($ekIndex + 1) . '. ' . htmlspecialchars((string) $ek->dosya_adi, ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                            <i class="bx bx-open-external text-muted ms-auto flex-shrink-0 font-size-14"></i>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                                        <?php if ($evrakTipi === 'giden'): ?>
                                            <button type="button" class="btn btn-subtle-info table-action-btn evrak-pdf-goruntule" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Resmî Yazı PDF Önizleme">
                                                <i class="bx bx-file-blank"></i>
                                            </button>
                                            <a href="index?p=evrak-takip/giden-evrak&amp;id=<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" class="btn <?= $kilitli ? 'btn-subtle-secondary' : 'btn-subtle-warning'; ?> table-action-btn" title="<?= $kilitli ? 'Görüntüle (Onaylı evrak kilitlidir)' : 'Düzenle'; ?>">
                                                <i class="bx <?= $kilitli ? 'bx-show' : 'bx-edit-alt'; ?>"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-subtle-primary table-action-btn evrak-duzenle" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Düzenle">
                                                <i class="bx bx-edit-alt"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!empty($evrak->uets_mesaj_id)): ?>
                                            <button type="button" class="btn btn-subtle-info table-action-btn btn-uets-deliller" data-uets-id="<?= htmlspecialchars((string) $evrak->uets_mesaj_id, ENT_QUOTES, 'UTF-8'); ?>" title="UETS İspat Delilleri">
                                                <i class="bx bx-shield-quarter"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!empty($evrak->kep_mesaj_id)): ?>
                                            <a href="index?p=evrak-takip/giden-evrak&id=<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>#collapseGidenKep" class="btn btn-subtle-primary table-action-btn" title="KEP Sevk ve Yasal Delilleri">
                                                <i class="bx bx-mail-send"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($siraBende): ?>
                                            <button type="button" class="btn btn-subtle-success table-action-btn evrak-e-imza-onayla" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="E-İmza ile İmzala">
                                                <i class="bx bx-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-subtle-warning table-action-btn evrak-e-imza-iade" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Düzeltilmek Üzere İade Et">
                                                <i class="bx bx-undo"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($kilitli && $geriAlinabilir): ?>
                                            <button type="button" class="btn btn-subtle-info table-action-btn evrak-e-imza-geri-al" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Evrakı Üzerime Geri Al">
                                                <i class="bx bx-revision"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!$kilitli): ?>
                                            <button type="button" class="btn btn-subtle-danger table-action-btn evrak-sil" data-id="<?= htmlspecialchars($encryptedEvrakId, ENT_QUOTES, 'UTF-8'); ?>" title="Sil">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Tablo Tipografi ve Temel Stiller */
#evrakTable_wrapper {
    overflow: visible !important;
}
#evrakTable {
    font-size: 13px !important;
}
#evrakTable thead .dt-filter-row input,
#evrakTable thead .dt-filter-row select {
    min-width: 0 !important;
    width: 100% !important;
}
#evrakTable thead tr:first-child th:nth-child(1),
#evrakTable thead tr:first-child th:nth-child(2),
#evrakTable thead tr:first-child th:nth-child(3),
#evrakTable thead tr:first-child th:nth-child(4),
#evrakTable thead tr:first-child th:nth-child(10),
#evrakTable thead tr:first-child th:nth-child(11),
#evrakTable thead tr:first-child th:nth-child(12),
#evrakTable thead tr:first-child th:nth-child(13) {
    width: 1px !important;
}
#evrakTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    letter-spacing: 0.3px;
    vertical-align: middle !important;
}
html:not([data-theme-preset="macos-dark"]):not([data-bs-theme="dark"]) body:not([data-layout-mode="dark"]) #evrakTable thead th {
    color: #334155;
    background-color: #f8fafc;
}
#evrakTable tbody td {
    padding: 8px 8px !important;
    vertical-align: middle !important;
}

/* Tablo Butonları Standartları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.evrak-konu-text {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    transition: color 0.15s ease;
}
.evrak-konu-text:hover {
    color: var(--bs-primary) !important;
    text-decoration: underline !important;
}

.e-imza-rozet[data-onay-akis] {
    cursor: pointer;
}

#evrakImzaPanel.evrak-imza-panel-visible { display: flex !important; }

@media (max-width: 991.98px) {
    .evrak-pdf-layout { flex-direction: column; }
    #evrakImzaPanel { width: 100% !important; min-width: 0 !important; max-height: 42vh !important; border-left: 0 !important; border-top: 1px solid var(--bs-border-color); }
}

.onay-akis-popover {
    max-width: 340px;
}

.onay-akis-popover .popover-header {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.onay-akis-popover .popover-body {
    font-size: 0.78rem;
    padding: 0.75rem;
}

.onay-akis-popover .onay-akis-ust {
    font-size: 0.72rem;
    margin-bottom: 0.5rem;
}

.onay-akis-popover .onay-akis-satir {
    padding: 0.35rem 0;
    border-bottom: 1px dashed var(--bs-border-color);
}

.onay-akis-popover .onay-akis-satir:last-child {
    border-bottom: 0;
}

.onay-akis-popover .onay-akis-unvan {
    font-size: 0.7rem;
    margin: 0 0 0.15rem 1.65rem;
}

.onay-akis-popover .onay-akis-satir .badge:not(.bg-light) {
    margin-left: 1.65rem;
    font-size: 0.66rem;
}

.onay-akis-popover .onay-akis-alt {
    font-size: 0.7rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--bs-border-color);
}

.evrak-dosya-menu {
    min-width: 260px;
    max-width: min(360px, calc(100vw - 2rem));
    max-height: 280px;
    overflow-y: auto;
}

.evrak-dosya-menu .dropdown-item {
    min-width: 0;
    padding: 0.6rem 0.75rem;
    font-size: 0.78rem;
}

.evrak-dosya-menu .dropdown-item .text-truncate {
    max-width: 260px;
}

/* ========================================================
   macOS Koyu Tema & Dark Mod Uyumluluğu
   ======================================================== */
[data-bs-theme="dark"] #evrakTable,
html[data-theme-preset="macos-dark"] #evrakTable,
body[data-layout-mode="dark"] #evrakTable,
[data-theme-mode="dark"] #evrakTable {
    --bs-border-color: rgba(255, 255, 255, 0.11) !important;
    --bs-table-border-color: rgba(255, 255, 255, 0.11) !important;
    border: 1px solid rgba(255, 255, 255, 0.11) !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    border-radius: 8px !important;
    overflow: hidden !important;
}

[data-bs-theme="dark"] #evrakTable th,
[data-bs-theme="dark"] #evrakTable td,
html[data-theme-preset="macos-dark"] #evrakTable th,
html[data-theme-preset="macos-dark"] #evrakTable td,
body[data-layout-mode="dark"] #evrakTable th,
body[data-layout-mode="dark"] #evrakTable td,
[data-theme-mode="dark"] #evrakTable th,
[data-theme-mode="dark"] #evrakTable td {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

[data-bs-theme="dark"] #evrakTable thead tr:first-child > th,
html[data-theme-preset="macos-dark"] #evrakTable thead tr:first-child > th,
body[data-layout-mode="dark"] #evrakTable thead tr:first-child > th,
[data-theme-mode="dark"] #evrakTable thead tr:first-child > th {
    background-color: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    color: #f1f5f9 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14) !important;
    --bs-table-bg: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    --bs-table-color: #f1f5f9 !important;
}

[data-bs-theme="dark"] #evrakTable thead .dt-filter-row th,
html[data-theme-preset="macos-dark"] #evrakTable thead .dt-filter-row th,
body[data-layout-mode="dark"] #evrakTable thead .dt-filter-row th,
[data-theme-mode="dark"] #evrakTable thead .dt-filter-row th {
    background-color: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.18) !important;
    --bs-table-bg: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
}

[data-bs-theme="dark"] #evrakTable thead .dt-filter-row input,
[data-bs-theme="dark"] #evrakTable thead .dt-filter-row select,
html[data-theme-preset="macos-dark"] #evrakTable thead .dt-filter-row input,
html[data-theme-preset="macos-dark"] #evrakTable thead .dt-filter-row select,
body[data-layout-mode="dark"] #evrakTable thead .dt-filter-row input,
body[data-layout-mode="dark"] #evrakTable thead .dt-filter-row select {
    background-color: #0f1117 !important;
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    color: #f8fafc !important;
    border-radius: 6px !important;
}
[data-bs-theme="dark"] #evrakTable thead .dt-filter-row input:focus,
[data-bs-theme="dark"] #evrakTable thead .dt-filter-row select:focus,
html[data-theme-preset="macos-dark"] #evrakTable thead .dt-filter-row input:focus,
html[data-theme-preset="macos-dark"] #evrakTable thead .dt-filter-row select:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
    outline: none !important;
}

[data-bs-theme="dark"] #evrakTable tbody td,
html[data-theme-preset="macos-dark"] #evrakTable tbody td,
body[data-layout-mode="dark"] #evrakTable tbody td,
[data-theme-mode="dark"] #evrakTable tbody td {
    background-color: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    color: rgba(235, 238, 245, 0.9) !important;
    --bs-table-bg: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    --bs-table-color: rgba(235, 238, 245, 0.9) !important;
}

[data-bs-theme="dark"] #evrakTable tbody tr:nth-child(even) td,
html[data-theme-preset="macos-dark"] #evrakTable tbody tr:nth-child(even) td,
body[data-layout-mode="dark"] #evrakTable tbody tr:nth-child(even) td,
[data-theme-mode="dark"] #evrakTable tbody tr:nth-child(even) td {
    background-color: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
    --bs-table-bg: var(--datatable-dark-surface, var(--bs-sidebar-bg, #1c2228)) !important;
}

[data-bs-theme="dark"] #evrakTable tbody tr:hover td,
html[data-theme-preset="macos-dark"] #evrakTable tbody tr:hover td,
body[data-layout-mode="dark"] #evrakTable tbody tr:hover td,
[data-theme-mode="dark"] #evrakTable tbody tr:hover td {
    background-color: rgba(59, 130, 246, 0.12) !important;
    --bs-table-hover-bg: rgba(59, 130, 246, 0.12) !important;
}

/* Sıralama İkonları - Koyu Mod */
html[data-theme-preset="macos-dark"] #evrakTable thead tr:first-child > th.sorting,
[data-bs-theme="dark"] #evrakTable thead tr:first-child > th.sorting {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
}
html[data-theme-preset="macos-dark"] #evrakTable thead tr:first-child > th.sorting:hover,
[data-bs-theme="dark"] #evrakTable thead tr:first-child > th.sorting:hover {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8M17 4v16M13 16L17 20L21 16'/%3E%3C/svg%3E") !important;
}
html[data-theme-preset="macos-dark"] #evrakTable thead tr:first-child > th.sorting_asc,
[data-bs-theme="dark"] #evrakTable thead tr:first-child > th.sorting_asc {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%2360a5fa' stroke-width='2.8'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%2364748b' stroke-width='1.8' opacity='0.4'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
}
html[data-theme-preset="macos-dark"] #evrakTable thead tr:first-child > th.sorting_desc,
[data-bs-theme="dark"] #evrakTable thead tr:first-child > th.sorting_desc {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 20V4M3 8L7 4L11 8' stroke='%2364748b' stroke-width='1.8' opacity='0.4'/%3E%3Cpath d='M17 4v16M13 16L17 20L21 16' stroke='%2360a5fa' stroke-width='2.8'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: left 8px center !important;
    background-size: 13px 13px !important;
    padding-left: 28px !important;
}

/* Başlıklar ve Metin Kontrastı */
[data-bs-theme="dark"] #evrakTable .text-dark,
html[data-theme-preset="macos-dark"] #evrakTable .text-dark,
body[data-layout-mode="dark"] #evrakTable .text-dark,
[data-theme-mode="dark"] #evrakTable .text-dark,
[data-bs-theme="dark"] h4.text-dark,
[data-bs-theme="dark"] h5.text-dark,
html[data-theme-preset="macos-dark"] h4.text-dark,
html[data-theme-preset="macos-dark"] h5.text-dark,
body[data-layout-mode="dark"] h4.text-dark,
body[data-layout-mode="dark"] h5.text-dark {
    color: rgba(240, 242, 246, 0.95) !important;
}

[data-bs-theme="dark"] .evrak-konu-text,
html[data-theme-preset="macos-dark"] .evrak-konu-text,
body[data-layout-mode="dark"] .evrak-konu-text {
    color: rgba(240, 242, 246, 0.92) !important;
}
[data-bs-theme="dark"] .evrak-konu-text:hover,
html[data-theme-preset="macos-dark"] .evrak-konu-text:hover,
body[data-layout-mode="dark"] .evrak-konu-text:hover {
    color: #60a5fa !important;
}

/* Üst Araç Çubuğu ve Tablo İşlem Butonları */
[data-bs-theme="dark"] .top-action-btn.bg-white,
[data-bs-theme="dark"] .top-icon-btn.bg-white,
html[data-theme-preset="macos-dark"] .top-action-btn.bg-white,
html[data-theme-preset="macos-dark"] .top-icon-btn.bg-white,
body[data-layout-mode="dark"] .top-action-btn.bg-white,
body[data-layout-mode="dark"] .top-icon-btn.bg-white,
[data-bs-theme="dark"] #evrakTable .action-btn-group .btn.bg-white,
html[data-theme-preset="macos-dark"] #evrakTable .action-btn-group .btn.bg-white,
body[data-layout-mode="dark"] #evrakTable .action-btn-group .btn.bg-white {
    background-color: rgba(35, 36, 42, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: rgba(235, 238, 245, 0.88) !important;
    box-shadow: none !important;
}
[data-bs-theme="dark"] .top-action-btn.bg-white:hover,
[data-bs-theme="dark"] .top-icon-btn.bg-white:hover,
html[data-theme-preset="macos-dark"] .top-action-btn.bg-white:hover,
html[data-theme-preset="macos-dark"] .top-icon-btn.bg-white:hover,
[data-bs-theme="dark"] #evrakTable .action-btn-group .btn.bg-white:hover,
html[data-theme-preset="macos-dark"] #evrakTable .action-btn-group .btn.bg-white:hover {
    background-color: rgba(50, 52, 60, 0.9) !important;
    color: #ffffff !important;
}

/* Özet KPI Kartları */
[data-bs-theme="dark"] .summary-kpi-card,
html[data-theme-preset="macos-dark"] .summary-kpi-card,
body[data-layout-mode="dark"] .summary-kpi-card {
    background: rgba(28, 30, 38, 0.72) !important;
    border-color: rgba(255, 255, 255, 0.09) !important;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45) !important;
}
[data-bs-theme="dark"] .summary-kpi-value,
html[data-theme-preset="macos-dark"] .summary-kpi-value,
body[data-layout-mode="dark"] .summary-kpi-value {
    color: #f3f4f8 !important;
}
[data-bs-theme="dark"] .summary-kpi-label,
html[data-theme-preset="macos-dark"] .summary-kpi-label,
body[data-layout-mode="dark"] .summary-kpi-label {
    color: rgba(200, 205, 218, 0.65) !important;
}
[data-bs-theme="dark"] .summary-kpi-subtext,
html[data-theme-preset="macos-dark"] .summary-kpi-subtext,
body[data-layout-mode="dark"] .summary-kpi-subtext {
    color: rgba(200, 205, 218, 0.6) !important;
}

/* İmza Bekleyen Bildirim Bandı */
[data-bs-theme="dark"] #imzaBekleyenUyari,
html[data-theme-preset="macos-dark"] #imzaBekleyenUyari,
body[data-layout-mode="dark"] #imzaBekleyenUyari {
    background-color: rgba(245, 158, 11, 0.12) !important;
    border: 1px solid rgba(245, 158, 11, 0.28) !important;
    color: #fef3c7 !important;
}
[data-bs-theme="dark"] #imzaBekleyenUyari .text-muted,
html[data-theme-preset="macos-dark"] #imzaBekleyenUyari .text-muted,
body[data-layout-mode="dark"] #imzaBekleyenUyari .text-muted {
    color: rgba(254, 243, 199, 0.75) !important;
}

/* Dropdown Menüler & Popover */
[data-bs-theme="dark"] .dropdown-menu,
html[data-theme-preset="macos-dark"] .dropdown-menu,
body[data-layout-mode="dark"] .dropdown-menu {
    background-color: #1e2025 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: rgba(235, 238, 245, 0.88) !important;
}
[data-bs-theme="dark"] .dropdown-item,
html[data-theme-preset="macos-dark"] .dropdown-item,
body[data-layout-mode="dark"] .dropdown-item {
    color: rgba(235, 238, 245, 0.88) !important;
}
[data-bs-theme="dark"] .dropdown-item:hover,
html[data-theme-preset="macos-dark"] .dropdown-item:hover,
body[data-layout-mode="dark"] .dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .dropdown-divider,
html[data-theme-preset="macos-dark"] .dropdown-divider,
body[data-layout-mode="dark"] .dropdown-divider {
    border-color: rgba(255, 255, 255, 0.08) !important;
}

[data-bs-theme="dark"] .onay-akis-popover,
html[data-theme-preset="macos-dark"] .onay-akis-popover,
body[data-layout-mode="dark"] .onay-akis-popover {
    background-color: #1e2025 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-bs-theme="dark"] .onay-akis-popover .popover-header,
html[data-theme-preset="macos-dark"] .onay-akis-popover .popover-header,
body[data-layout-mode="dark"] .onay-akis-popover .popover-header {
    background-color: rgba(35, 37, 44, 0.95) !important;
    color: #f1f3f9 !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] .onay-akis-popover .popover-body,
html[data-theme-preset="macos-dark"] .onay-akis-popover .popover-body,
body[data-layout-mode="dark"] .onay-akis-popover .popover-body {
    color: rgba(235, 238, 245, 0.85) !important;
}
[data-bs-theme="dark"] .onay-akis-popover .onay-akis-satir,
html[data-theme-preset="macos-dark"] .onay-akis-popover .onay-akis-satir,
body[data-layout-mode="dark"] .onay-akis-popover .onay-akis-satir {
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

/* PDF Önizleme Modalı */
[data-bs-theme="dark"] #evrakPdfModal .modal-content,
html[data-theme-preset="macos-dark"] #evrakPdfModal .modal-content,
body[data-layout-mode="dark"] #evrakPdfModal .modal-content {
    background-color: #181a1f !important;
    color: #e2e8f0 !important;
}
[data-bs-theme="dark"] #evrakPdfModal .modal-header,
html[data-theme-preset="macos-dark"] #evrakPdfModal .modal-header,
body[data-layout-mode="dark"] #evrakPdfModal .modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] #evrakPdfLoader,
html[data-theme-preset="macos-dark"] #evrakPdfLoader,
body[data-layout-mode="dark"] #evrakPdfLoader {
    background-color: #181a1f !important;
}
[data-bs-theme="dark"] #evrakImzaPanel,
html[data-theme-preset="macos-dark"] #evrakImzaPanel,
body[data-layout-mode="dark"] #evrakImzaPanel {
    background-color: #1c1e24 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] #evrakImzaPanel .bg-white,
html[data-theme-preset="macos-dark"] #evrakImzaPanel .bg-white,
body[data-layout-mode="dark"] #evrakImzaPanel .bg-white {
    background-color: #1c1e24 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
[data-bs-theme="dark"] #evrakImzaBelgeBaslik,
html[data-theme-preset="macos-dark"] #evrakImzaBelgeBaslik,
body[data-layout-mode="dark"] #evrakImzaBelgeBaslik {
    color: #f1f3f9 !important;
}
[data-bs-theme="dark"] #evrakImzaOnayAction,
html[data-theme-preset="macos-dark"] #evrakImzaOnayAction,
body[data-layout-mode="dark"] #evrakImzaOnayAction {
    background-color: #1c1e24 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
</style>

<?php include_once "modal/evrak-modal.php"; ?>
<?php include_once "modal/uets-modals.php"; ?>
<?php if ($canSendKep) include_once "modal/kep-send-modal.php"; ?>
<?php if ($canMobilImzaAyar) include_once "modal/mobil-imza-ayarlari-modal.php"; ?>
<?php include_once "modal/kep-preview-modal.php"; ?>

<script>window.evrakMobilImzaAktif = <?= \App\Service\MobilImzaPadesService::isEnabled((int) ($_SESSION['firma_id'] ?? 0)) ? 'true' : 'false'; ?>;</script>
<script src="<?= Helper::assetVersion('views/evrak-takip/js/mobil-imza.js'); ?>"></script>
<script src="<?= Helper::assetVersion('views/evrak-takip/js/evrak-takip.js'); ?>"></script>
